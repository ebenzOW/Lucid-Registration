<?php
/**
 * Lucid University Course Registration System
 * Admin Portal - Process Profile Change Requests & Audit Logging
 */

require_once 'admin_guard.php';
require_once '../db_connect.php';
require_once __DIR__ . '/../includes/audit_helpers.php';

$admin_id = (int)$userID;

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: manage_change_requests.php");
    exit;
}

$request_id  = isset($_POST['request_id']) ? (int)$_POST['request_id'] : 0;
$decision    = trim($_POST['decision'] ?? '');
$admin_notes = trim($_POST['admin_notes'] ?? '');
$return_tab  = trim($_POST['return_tab'] ?? 'requests');
if (!in_array($return_tab, ['requests', 'audit_log'], true)) {
    $return_tab = 'requests';
}

$inTransaction = false;

try {
    // 1. Enforce Security Level >= 2 write permission per SRS
    requireAdminWriteAccess();

    if ($request_id <= 0 || !in_array($decision, ['approve', 'reject'], true)) {
        throw new Exception("Invalid request ID or decision action specified.");
    }

    // Why: Begin transaction and lock the request row before checking status to prevent concurrent double-processing.
    $conn->begin_transaction();
    $inTransaction = true;

    $reqStmt = $conn->prepare("
        SELECT *
        FROM PROFILE_CHANGE_REQUESTS
        WHERE Request_ID = ?
        LIMIT 1
        FOR UPDATE
    ");
    $reqStmt->bind_param("i", $request_id);
    $reqStmt->execute();
    $request = $reqStmt->get_result()->fetch_assoc();
    $reqStmt->close();

    if (!$request) {
        throw new Exception("Profile change request (#{$request_id}) was not found.");
    }
    if ($request['Request_Status'] !== 'Pending') {
        throw new Exception("Profile change request (#{$request_id}) has already been processed ({$request['Request_Status']}).");
    }

    $target_user_id = (int)$request['User_ID'];
    $notes_db = $admin_notes !== '' ? substr($admin_notes, 0, 255) : null;

    // 2. Lock and fetch current USER record for accurate diffing and atomic update
    $uStmt = $conn->prepare("
        SELECT User_ID, User_FName, User_MName, User_LName, User_Gender, User_DOB,
               User_PhoneNo, Street_No, Street_Name, City, State, Country, Zip_Code
        FROM `USER`
        WHERE User_ID = ?
        LIMIT 1
        FOR UPDATE
    ");
    $uStmt->bind_param("i", $target_user_id);
    $uStmt->execute();
    $currentUser = $uStmt->get_result()->fetch_assoc();
    $uStmt->close();

    if (!$currentUser) {
        throw new Exception("Target user account (ID: {$target_user_id}) does not exist.");
    }

    if ($decision === 'reject') {
        $rejStmt = $conn->prepare("
            UPDATE PROFILE_CHANGE_REQUESTS
            SET Request_Status = 'Rejected',
                Reviewed_By = ?,
                Reviewed_At = CURRENT_TIMESTAMP,
                Admin_Notes = ?
            WHERE Request_ID = ?
        ");
        $rejStmt->bind_param("isi", $admin_id, $notes_db, $request_id);
        if (!$rejStmt->execute()) {
            $err = $rejStmt->error;
            $rejStmt->close();
            throw new Exception("Failed to reject request: " . $err);
        }
        $rejStmt->close();

        $rejDetails = "Rejected profile change request for User ID {$target_user_id}";
        if ($admin_notes !== '') {
            $rejDetails .= " (Notes: {$admin_notes})";
        }
        logAdminActivity($conn, $admin_id, 'Profile Request', $request_id, 'Reject', $rejDetails);

        $conn->commit();
        $inTransaction = false;

        $_SESSION['success_message'] = "Profile change request (#{$request_id}) for User ID {$target_user_id} has been rejected.";
        header("Location: manage_change_requests.php?tab=" . urlencode($return_tab));
        exit;
    }

    // 3. Decision === 'approve': Compute changed attributes against locked USER row
    $fieldMapping = [
        'User_FName'   => $request['Req_FName'],
        'User_MName'   => $request['Req_MName'],
        'User_LName'   => $request['Req_LName'],
        'User_Gender'  => $request['Req_Gender'],
        'User_DOB'     => $request['Req_DOB'],
        'User_PhoneNo' => $request['Req_PhoneNo'],
        'Street_No'    => $request['Req_Street_No'],
        'Street_Name'  => $request['Req_Street_Name'],
        'City'         => $request['Req_City'],
        'State'        => $request['Req_State'],
        'Country'      => $request['Req_Country'],
        'Zip_Code'     => $request['Req_Zip_Code'],
    ];

    $changedAttributes = [];
    foreach ($fieldMapping as $colName => $newVal) {
        $oldVal = $currentUser[$colName] ?? null;
        if (trim((string)$oldVal) !== trim((string)$newVal)) {
            $changedAttributes[] = $colName;
        }
    }

    // 4. Update USER table (only allowed personal attributes; User_ID and User_Email untouched)
    $updUserStmt = $conn->prepare("
        UPDATE `USER`
        SET User_FName   = ?,
            User_MName   = ?,
            User_LName   = ?,
            User_Gender  = ?,
            User_DOB     = ?,
            User_PhoneNo = ?,
            Street_No    = ?,
            Street_Name  = ?,
            City         = ?,
            State        = ?,
            Country      = ?,
            Zip_Code     = ?
        WHERE User_ID = ?
    ");

    $updUserStmt->bind_param(
        "ssssssssssssi",
        $request['Req_FName'],
        $request['Req_MName'],
        $request['Req_LName'],
        $request['Req_Gender'],
        $request['Req_DOB'],
        $request['Req_PhoneNo'],
        $request['Req_Street_No'],
        $request['Req_Street_Name'],
        $request['Req_City'],
        $request['Req_State'],
        $request['Req_Country'],
        $request['Req_Zip_Code'],
        $target_user_id
    );

    if (!$updUserStmt->execute()) {
        $err = $updUserStmt->error;
        $updUserStmt->close();
        throw new Exception("Failed to update USER profile record: " . $err);
    }
    $updUserStmt->close();

    // 5. Write per-column audit entries to USER_UPDATE_LOG
    if (empty($changedAttributes)) {
        $changedAttributes[] = 'Profile_Verified';
    }

    foreach ($changedAttributes as $attrName) {
        logUserUpdate($conn, $admin_id, $target_user_id, $attrName);
    }

    // 6. Mark request as Approved
    $appStmt = $conn->prepare("
        UPDATE PROFILE_CHANGE_REQUESTS
        SET Request_Status = 'Approved',
            Reviewed_By = ?,
            Reviewed_At = CURRENT_TIMESTAMP,
            Admin_Notes = ?
        WHERE Request_ID = ?
    ");
    $appStmt->bind_param("isi", $admin_id, $notes_db, $request_id);
    if (!$appStmt->execute()) {
        $err = $appStmt->error;
        $appStmt->close();
        throw new Exception("Failed to update request status: " . $err);
    }
    $appStmt->close();

    logAdminActivity(
        $conn,
        $admin_id,
        'Profile Request',
        $request_id,
        'Approve',
        "Approved request for User ID {$target_user_id}: " . implode(', ', $changedAttributes)
    );

    $conn->commit();
    $inTransaction = false;

    $_SESSION['success_message'] = "Approved Request #{$request_id} for User ID {$target_user_id}! Updated USER profile and recorded " . count($changedAttributes) . " audit entry/entries in USER_UPDATE_LOG (" . implode(', ', $changedAttributes) . ").";
    header("Location: manage_change_requests.php?tab=" . urlencode($return_tab));
    exit;

} catch (Exception $e) {
    if ($inTransaction) {
        $conn->rollback();
        $inTransaction = false;
    }
    $_SESSION['error_message'] = $e->getMessage();
    header("Location: manage_change_requests.php?tab=" . urlencode($return_tab));
    exit;
}