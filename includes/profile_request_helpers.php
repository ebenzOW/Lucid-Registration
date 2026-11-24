<?php
/**
 * Lucid University Course Registration System
 * Shared Profile Change Request Helpers (Student & Faculty)
 */

/**
 * Fetch current USER + LOGIN record for the profile request form.
 */
function getProfileRequestUser(mysqli $conn, int $user_id): ?array {
    $stmt = $conn->prepare("
        SELECT u.User_ID, u.User_Type, u.User_FName, u.User_MName, u.User_LName,
               u.User_Gender, u.User_DOB, u.User_PhoneNo,
               u.Street_No, u.Street_Name, u.City, u.State, u.Country, u.Zip_Code,
               l.User_Email
        FROM `USER` u
        LEFT JOIN LOGIN l ON u.User_ID = l.User_ID
        WHERE u.User_ID = ?
        LIMIT 1
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return $user ?: null;
}

/**
 * Fetch a user's profile change request history (newest first).
 */
function getUserProfileChangeRequests(mysqli $conn, int $user_id): array {
    $stmt = $conn->prepare("
        SELECT r.*,
               a_u.User_FName AS Admin_FName,
               a_u.User_LName AS Admin_LName
        FROM PROFILE_CHANGE_REQUESTS r
        LEFT JOIN `USER` a_u ON r.Reviewed_By = a_u.User_ID
        WHERE r.User_ID = ?
        ORDER BY r.Requested_At DESC, r.Request_ID DESC
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $res = $stmt->get_result();

    $requests = [];
    while ($row = $res->fetch_assoc()) {
        $requests[] = $row;
    }
    $stmt->close();

    return $requests;
}

/**
 * Process POST submit/cancel actions for Student or Faculty profile change requests.
 */
function handleProfileChangeRequestPost(mysqli $conn, int $user_id, array $post, string $redirectUrl): void {
    $action = trim($post['action'] ?? 'submit_request');
    $inTransaction = false;

    try {
        if ($action === 'cancel_request') {
            $request_id = isset($post['request_id']) ? (int)$post['request_id'] : 0;
            if ($request_id <= 0) {
                throw new Exception("Invalid request ID provided.");
            }

            // Why: Atomic status check in DELETE prevents deleting a request an admin just approved/rejected.
            $conn->begin_transaction();
            $inTransaction = true;

            $delStmt = $conn->prepare("
                DELETE FROM PROFILE_CHANGE_REQUESTS
                WHERE Request_ID = ? AND User_ID = ? AND Request_Status = 'Pending'
            ");
            $delStmt->bind_param("ii", $request_id, $user_id);
            if (!$delStmt->execute()) {
                $err = $delStmt->error;
                $delStmt->close();
                throw new Exception("Failed to cancel request: " . $err);
            }
            $affected = $delStmt->affected_rows;
            $delStmt->close();

            if ($affected !== 1) {
                throw new Exception("Request #{$request_id} could not be cancelled (not found, unauthorized, or already processed by an administrator).");
            }

            $conn->commit();
            $inTransaction = false;

            $_SESSION['success_message'] = "Your pending profile change request (#{$request_id}) has been cancelled.";
            header("Location: " . $redirectUrl);
            exit;
        }

        if ($action !== 'submit_request') {
            throw new Exception("Unsupported action requested.");
        }

        // Why: Enforce SRS constraints prohibiting users from mutating User_ID or University Email.
        if (isset($post['user_id']) && (int)$post['user_id'] !== $user_id) {
            throw new Exception("Security Violation: Users are strictly forbidden from modifying User ID.");
        }
        if (isset($post['user_email'])) {
            throw new Exception("Security Violation: Users are strictly forbidden from modifying University Email.");
        }

        // Sanitize requested values
        $req_fname       = trim($post['req_fname'] ?? '');
        $req_mname       = trim($post['req_mname'] ?? '');
        $req_lname       = trim($post['req_lname'] ?? '');
        $req_gender      = trim($post['req_gender'] ?? '');
        $req_dob         = trim($post['req_dob'] ?? '');
        $req_phone       = trim($post['req_phone'] ?? '');
        $req_street_no   = trim($post['req_street_no'] ?? '');
        $req_street_name = trim($post['req_street_name'] ?? '');
        $req_city        = trim($post['req_city'] ?? '');
        $req_state       = trim($post['req_state'] ?? '');
        $req_country     = trim($post['req_country'] ?? '');
        $req_zip         = trim($post['req_zip'] ?? '');
        $request_reason  = trim($post['request_reason'] ?? '');

        // Validate required fields, formats, allowlists, and exact schema column lengths
        if ($req_fname === '' || $req_lname === '') {
            throw new Exception("First Name and Last Name cannot be blank.");
        }
        if (strlen($req_fname) > 50 || strlen($req_lname) > 50 || strlen($req_mname) > 50) {
            throw new Exception("Name fields cannot exceed 50 characters.");
        }

        $allowedGenders = ['', 'Male', 'Female', 'Non-Binary', 'Other'];
        if (!in_array($req_gender, $allowedGenders, true)) {
            throw new Exception("Invalid gender option selected.");
        }

        if ($req_dob !== '') {
            $dobObj = DateTime::createFromFormat('Y-m-d', $req_dob);
            if (!$dobObj || $dobObj->format('Y-m-d') !== $req_dob) {
                throw new Exception("Date of Birth must be a valid calendar date in YYYY-MM-DD format.");
            }
            if ($req_dob > date('Y-m-d')) {
                throw new Exception("Date of Birth cannot be in the future.");
            }
        }

        if (strlen($req_phone) > 15) {
            throw new Exception("Phone Number cannot exceed 15 characters.");
        }
        if (strlen($req_street_no) > 10) {
            throw new Exception("Street Number cannot exceed 10 characters.");
        }
        if (strlen($req_street_name) > 50) {
            throw new Exception("Street Name cannot exceed 50 characters.");
        }
        if (strlen($req_city) > 50) {
            throw new Exception("City cannot exceed 50 characters.");
        }
        if (strlen($req_state) > 20) {
            throw new Exception("State cannot exceed 20 characters.");
        }
        if (strlen($req_country) > 50) {
            throw new Exception("Country cannot exceed 50 characters.");
        }
        if (strlen($req_zip) > 15) {
            throw new Exception("Zip Code cannot exceed 15 characters.");
        }
        if (strlen($request_reason) > 255) {
            throw new Exception("Reason / supporting note cannot exceed 255 characters.");
        }

        // Why: Lock the user row first to serialize concurrent submissions and prevent duplicate Pending requests.
        $conn->begin_transaction();
        $inTransaction = true;

        $curStmt = $conn->prepare("
            SELECT User_FName, User_MName, User_LName, User_Gender, User_DOB,
                   User_PhoneNo, Street_No, Street_Name, City, State, Country, Zip_Code
            FROM `USER`
            WHERE User_ID = ?
            FOR UPDATE
        ");
        $curStmt->bind_param("i", $user_id);
        $curStmt->execute();
        $current = $curStmt->get_result()->fetch_assoc();
        $curStmt->close();

        if (!$current) {
            throw new Exception("User account record not found.");
        }

        $pendStmt = $conn->prepare("
            SELECT Request_ID
            FROM PROFILE_CHANGE_REQUESTS
            WHERE User_ID = ? AND Request_Status = 'Pending'
            LIMIT 1
            FOR UPDATE
        ");
        $pendStmt->bind_param("i", $user_id);
        $pendStmt->execute();
        $existingPending = $pendStmt->get_result()->fetch_assoc();
        $pendStmt->close();

        if ($existingPending) {
            throw new Exception("You already have a pending profile change request (#{$existingPending['Request_ID']}). Please wait for administrator review or cancel your existing request before submitting a new one.");
        }

        // Verify at least one allowed field differs from the current on-file profile
        $comparisons = [
            'User_FName'   => [$current['User_FName'] ?? '', $req_fname],
            'User_MName'   => [$current['User_MName'] ?? '', $req_mname],
            'User_LName'   => [$current['User_LName'] ?? '', $req_lname],
            'User_Gender'  => [$current['User_Gender'] ?? '', $req_gender],
            'User_DOB'     => [$current['User_DOB'] ?? '', $req_dob],
            'User_PhoneNo' => [$current['User_PhoneNo'] ?? '', $req_phone],
            'Street_No'    => [$current['Street_No'] ?? '', $req_street_no],
            'Street_Name'  => [$current['Street_Name'] ?? '', $req_street_name],
            'City'         => [$current['City'] ?? '', $req_city],
            'State'        => [$current['State'] ?? '', $req_state],
            'Country'      => [$current['Country'] ?? '', $req_country],
            'Zip_Code'     => [$current['Zip_Code'] ?? '', $req_zip],
        ];

        $changedFields = [];
        foreach ($comparisons as $col => $pair) {
            if (trim((string)$pair[0]) !== trim((string)$pair[1])) {
                $changedFields[] = $col;
            }
        }

        if (empty($changedFields)) {
            throw new Exception("No changes were detected compared to your current profile information.");
        }

        // Normalize blank optional values to NULL for storage
        $req_mname_db       = $req_mname !== '' ? $req_mname : null;
        $req_gender_db      = $req_gender !== '' ? $req_gender : null;
        $req_dob_db         = $req_dob !== '' ? $req_dob : null;
        $req_phone_db       = $req_phone !== '' ? $req_phone : null;
        $req_street_no_db   = $req_street_no !== '' ? $req_street_no : null;
        $req_street_name_db = $req_street_name !== '' ? $req_street_name : null;
        $req_city_db        = $req_city !== '' ? $req_city : null;
        $req_state_db       = $req_state !== '' ? $req_state : null;
        $req_country_db     = $req_country !== '' ? $req_country : null;
        $req_zip_db         = $req_zip !== '' ? $req_zip : null;
        $reason_db          = $request_reason !== '' ? $request_reason : null;

        $insStmt = $conn->prepare("
            INSERT INTO PROFILE_CHANGE_REQUESTS (
                User_ID,
                Req_FName, Req_MName, Req_LName,
                Req_Gender, Req_DOB, Req_PhoneNo,
                Req_Street_No, Req_Street_Name, Req_City, Req_State, Req_Country, Req_Zip_Code,
                Request_Reason, Request_Status, Requested_At
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Pending', CURRENT_TIMESTAMP)
        ");

        $insStmt->bind_param(
            "isssssssssssss",
            $user_id,
            $req_fname,
            $req_mname_db,
            $req_lname,
            $req_gender_db,
            $req_dob_db,
            $req_phone_db,
            $req_street_no_db,
            $req_street_name_db,
            $req_city_db,
            $req_state_db,
            $req_country_db,
            $req_zip_db,
            $reason_db
        );

        if (!$insStmt->execute()) {
            $err = $insStmt->error;
            $insStmt->close();
            throw new Exception("Failed to submit profile change request: " . $err);
        }
        $newReqId = $insStmt->insert_id;
        $insStmt->close();

        $conn->commit();
        $inTransaction = false;

        $_SESSION['success_message'] = "Profile change request (#{$newReqId}) submitted for administrator review! Changed fields: " . implode(', ', $changedFields) . ".";
        header("Location: " . $redirectUrl);
        exit;

    } catch (Exception $e) {
        if ($inTransaction) {
            $conn->rollback();
            $inTransaction = false;
        }
        $_SESSION['error_message'] = $e->getMessage();
        header("Location: " . $redirectUrl);
        exit;
    }
}