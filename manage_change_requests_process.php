<?php
/**
 * Lucid University Course Registration System
 * Admin Portal - Process Student Profile Change Requests & Audit Logging
 *
 * Enforces SRS Constraints:
 * 1. Read-only admins (Security Level 1) cannot approve or reject requests (`requireAdminWriteAccess()`).
 * 2. Admins can only modify user profile info in response to an explicit Pending request.
 * 3. When an admin permits (approves) a change request:
 *    - Updates the allowed attributes in `USER` (`User_FName`, `User_MName`, `User_LName`,
 *      `User_Gender`, `User_DOB`, `User_PhoneNo`, `Street_No`, `Street_Name`, `City`, `State`, `Country`, `Zip_Code`).
 *    - Compares old vs. new values for each column and inserts a corresponding audit row into
 *      `USER_UPDATE_LOG` (`Admin_ID`, `User_ID`, `Changed_Attribute`, `Date_Of_Change`) for every modified column.
 *    - Marks the request as `'Approved'` with `Reviewed_By = $admin_id` and `Reviewed_At = CURRENT_TIMESTAMP`.
 */

require_once 'admin_guard.php';
require_once '../db_connect.php';

$admin_id = (int)$userID;

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: manage_change_requests.php");
    exit;
}

$request_id  = isset($_POST['request_id']) ? (int)$_POST['request_id'] : 0;
$decision    = trim($_POST['decision'] ?? ''); // 'approve' or 'reject'
$admin_notes = trim($_POST['admin_notes'] ?? '');
$return_tab  = trim($_POST['return_tab'] ?? 'requests');

try {
    // 1. Enforce Security Level >= 2 write permission
    requireAdminWriteAccess();

    if ($request_id <= 0 || !in_array($decision, ['approve', 'reject'], true)) {
        throw new Exception("Invalid request ID or decision action specified.");
    }

    // 2. Fetch the Pending Request
    $reqStmt = $conn->prepare("
        SELECT *
        FROM PROFILE_CHANGE_REQUESTS
        WHERE Request_ID = ?
        LIMIT 1
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

    // 3. Fetch Current User Record for Diff Comparison
    $uStmt = $conn->prepare("
        SELECT User_ID, User_FName, User_MName, User_LName, User_Gender, User_DOB,
               User_PhoneNo, Street_No, Street_Name, City, State, Country, Zip_Code
        FROM `USER`
        WHERE User_ID = ?
        LIMIT 1
    ");
    $uStmt->bind_param("i", $target_user_id);
    $uStmt->execute();
    $currentUser = $uStmt->get_result()->fetch_assoc();
    $uStmt->close();

    if (!$currentUser) {
        throw new Exception("Target user account (ID: {$target_user_id}) does not exist.");
    }

    $conn->begin_transaction();

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
            throw new Exception("Failed to reject request: " . $rejStmt->error);
        }
        $rejStmt->close();

        $conn->commit();
        $_SESSION['success_message'] = "Profile change request (#{$request_id}) for User ID {$target_user_id} has been rejected.";
        header("Location: manage_change_requests.php?tab=" . urlencode($return_tab));
        exit;
    }

    // 4. Decision === 'approve': Determine which attributes changed
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

    // 5. Update USER table with requested values
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
        throw new Exception("Failed to update USER profile record: " . $updUserStmt->error);
    }
    $updUserStmt->close();

    // 6. Insert Audit Log entries into USER_UPDATE_LOG for every changed attribute
    if (empty($changedAttributes)) {
        $changedAttributes[] = 'Profile_Verified';
    }

    $logStmt = $conn->prepare("
        INSERT INTO USER_UPDATE_LOG (Admin_ID, User_ID, Changed_Attribute, Date_Of_Change)
        VALUES (?, ?, ?, CURRENT_TIMESTAMP)
    ");

    foreach ($changedAttributes as $attrName) {
        $attrClean = substr($attrName, 0, 50);
        $logStmt->bind_param("iis", $admin_id, $target_user_id, $attrClean);
        if (!$logStmt->execute()) {
            throw new Exception("Failed to write to USER_UPDATE_LOG: " . $logStmt->error);
        }
    }
    $logStmt->close();

    // 7. Mark request as Approved in PROFILE_CHANGE_REQUESTS
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
        throw new Exception("Failed to update request status: " . $appStmt->error);
    }
    $appStmt->close();

    $conn->commit();

    $_SESSION['success_message'] = "Approved Request #{$request_id} for User ID {$target_user_id}! Updated USER profile and recorded " . count($changedAttributes) . " audit entry/entries in USER_UPDATE_LOG (" . implode(', ', $changedAttributes) . ").";
    header("Location: manage_change_requests.php?tab=" . urlencode($return_tab));
    exit;

} catch (Exception $e) {
    if (isset($conn) && $conn instanceof mysqli && $conn->connect_errno === 0) {
        @$conn->rollback();
    }
    $_SESSION['error_message'] = $e->getMessage();
    header("Location: manage_change_requests.php?tab=" . urlencode($return_tab));
    exit;
}
?>
