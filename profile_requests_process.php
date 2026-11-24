<?php
/**
 * Lucid University Course Registration System
 * Student Portal - Process Profile Change Requests
 *
 * Enforces SRS Constraints:
 * 1. Students cannot change their User_ID or User_Email (strictly forbidden).
 * 2. Students can request updates to allowed personal attributes:
 *    First/Middle/Last Name, Gender, Date of Birth, Phone Number, and Residential Address.
 * 3. Validates required fields, phone format, date format, and ensures at least one field
 *    differs from current profile before creating a Pending request.
 */

require_once 'student_guard.php';
$student_id = (int)$userID;

require_once '../db_connect.php';

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: profile_requests.php");
    exit;
}

// Ensure PROFILE_CHANGE_REQUESTS table exists in case schema migration hasn't run yet
$conn->query("
    CREATE TABLE IF NOT EXISTS PROFILE_CHANGE_REQUESTS (
        Request_ID INT NOT NULL AUTO_INCREMENT,
        User_ID INT NOT NULL,
        Req_FName VARCHAR(50) NOT NULL,
        Req_MName VARCHAR(50),
        Req_LName VARCHAR(50) NOT NULL,
        Req_Gender VARCHAR(15),
        Req_DOB DATE,
        Req_PhoneNo VARCHAR(15),
        Req_Street_No VARCHAR(10),
        Req_Street_Name VARCHAR(50),
        Req_City VARCHAR(50),
        Req_State VARCHAR(20),
        Req_Country VARCHAR(50),
        Req_Zip_Code VARCHAR(15),
        Request_Reason VARCHAR(255),
        Request_Status VARCHAR(20) NOT NULL DEFAULT 'Pending',
        Requested_At DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        Reviewed_By INT,
        Reviewed_At DATETIME,
        Admin_Notes VARCHAR(255),
        PRIMARY KEY (Request_ID),
        FOREIGN KEY (User_ID) REFERENCES `USER`(User_ID) ON DELETE CASCADE,
        FOREIGN KEY (Reviewed_By) REFERENCES ADMIN(Admin_ID) ON DELETE SET NULL
    ) ENGINE=InnoDB
");

$action = trim($_POST['action'] ?? 'submit_request');

try {
    if ($action === 'cancel_request') {
        $request_id = isset($_POST['request_id']) ? (int)$_POST['request_id'] : 0;
        if ($request_id <= 0) {
            throw new Exception("Invalid request ID provided.");
        }

        // Verify the request belongs to this student and is still Pending
        $chkStmt = $conn->prepare("
            SELECT Request_Status 
            FROM PROFILE_CHANGE_REQUESTS 
            WHERE Request_ID = ? AND User_ID = ?
        ");
        $chkStmt->bind_param("ii", $request_id, $student_id);
        $chkStmt->execute();
        $reqRow = $chkStmt->get_result()->fetch_assoc();
        $chkStmt->close();

        if (!$reqRow) {
            throw new Exception("Profile change request not found or unauthorized.");
        }
        if ($reqRow['Request_Status'] !== 'Pending') {
            throw new Exception("Only Pending requests can be cancelled.");
        }

        $delStmt = $conn->prepare("DELETE FROM PROFILE_CHANGE_REQUESTS WHERE Request_ID = ? AND User_ID = ?");
        $delStmt->bind_param("ii", $request_id, $student_id);
        if (!$delStmt->execute()) {
            throw new Exception("Failed to cancel request: " . $delStmt->error);
        }
        $delStmt->close();

        $_SESSION['success_message'] = "Your pending profile change request (#{$request_id}) has been cancelled.";
        header("Location: profile_requests.php");
        exit;
    }

    // Explicitly block any attempt to pass User_ID or User_Email mutations
    if (isset($_POST['user_id']) && (int)$_POST['user_id'] !== $student_id) {
        throw new Exception("Security Violation: Students are strictly forbidden from modifying User ID.");
    }
    if (isset($_POST['user_email'])) {
        throw new Exception("Security Violation: Students are strictly forbidden from modifying University Email.");
    }

    // Check if the student already has a Pending change request
    $pendStmt = $conn->prepare("
        SELECT Request_ID 
        FROM PROFILE_CHANGE_REQUESTS 
        WHERE User_ID = ? AND Request_Status = 'Pending'
        LIMIT 1
    ");
    $pendStmt->bind_param("i", $student_id);
    $pendStmt->execute();
    $existingPending = $pendStmt->get_result()->fetch_assoc();
    $pendStmt->close();

    if ($existingPending) {
        throw new Exception("You already have a pending profile change request (#{$existingPending['Request_ID']}). Please wait for administrator review or cancel your existing request before submitting a new one.");
    }

    // Fetch current student profile from USER table
    $curStmt = $conn->prepare("
        SELECT User_FName, User_MName, User_LName, User_Gender, User_DOB,
               User_PhoneNo, Street_No, Street_Name, City, State, Country, Zip_Code
        FROM `USER`
        WHERE User_ID = ?
    ");
    $curStmt->bind_param("i", $student_id);
    $curStmt->execute();
    $current = $curStmt->get_result()->fetch_assoc();
    $curStmt->close();

    if (!$current) {
        throw new Exception("Student record not found.");
    }

    // Sanitize requested values
    $req_fname       = trim($_POST['req_fname'] ?? '');
    $req_mname       = trim($_POST['req_mname'] ?? '');
    $req_lname       = trim($_POST['req_lname'] ?? '');
    $req_gender      = trim($_POST['req_gender'] ?? '');
    $req_dob         = trim($_POST['req_dob'] ?? '');
    $req_phone       = trim($_POST['req_phone'] ?? '');
    $req_street_no   = trim($_POST['req_street_no'] ?? '');
    $req_street_name = trim($_POST['req_street_name'] ?? '');
    $req_city        = trim($_POST['req_city'] ?? '');
    $req_state       = trim($_POST['req_state'] ?? '');
    $req_country     = trim($_POST['req_country'] ?? '');
    $req_zip         = trim($_POST['req_zip'] ?? '');
    $request_reason  = trim($_POST['request_reason'] ?? '');

    // Validate required fields
    if ($req_fname === '' || $req_lname === '') {
        throw new Exception("First Name and Last Name cannot be blank.");
    }
    if (strlen($req_fname) > 50 || strlen($req_lname) > 50 || strlen($req_mname) > 50) {
        throw new Exception("Name fields cannot exceed 50 characters.");
    }
    if ($req_dob !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $req_dob)) {
        throw new Exception("Date of Birth must be in valid YYYY-MM-DD format.");
    }
    if ($req_phone !== '' && strlen($req_phone) > 15) {
        throw new Exception("Phone Number cannot exceed 15 characters.");
    }
    if ($req_street_no !== '' && strlen($req_street_no) > 10) {
        throw new Exception("Street Number cannot exceed 10 characters.");
    }
    if ($req_zip !== '' && strlen($req_zip) > 15) {
        throw new Exception("Zip Code cannot exceed 15 characters.");
    }

    // Normalize empty optional fields to null for comparison and storage
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

    // Verify that at least one field is actually different from current profile
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

    // Insert into PROFILE_CHANGE_REQUESTS
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
        $student_id,
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
        throw new Exception("Failed to submit profile change request: " . $insStmt->error);
    }
    $newReqId = $insStmt->insert_id;
    $insStmt->close();

    $_SESSION['success_message'] = "Profile change request (#{$newReqId}) submitted for administrator review! Changed fields: " . implode(', ', $changedFields) . ".";
    header("Location: profile_requests.php");
    exit;

} catch (Exception $e) {
    $_SESSION['error_message'] = $e->getMessage();
    header("Location: profile_requests.php");
    exit;
}
?>
