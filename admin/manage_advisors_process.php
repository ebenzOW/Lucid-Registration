<?php
/**
 * Lucid University Course Registration System
 * Admin Portal - Process Student & Faculty Advisor Assignments
 *
 * Implements SRS Constraints:
 * 1. Read-only admins (Security Level 1) cannot mutate records.
 * 2. Full-Time faculty cannot have more than 15 advisees.
 * 3. Part-Time faculty will have no advisees (strictly forbidden).
 * 4. Faculty advisor must belong to student's academic department (via DEPARTMENT_ROSTER).
 * 5. Full transactional concurrency control with SELECT ... FOR UPDATE and USER_UPDATE_LOG auditing.
 */

require_once 'admin_guard.php';
require_once '../db_connect.php';

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: manage_advisors.php");
    exit;
}

$admin_id = (int)$userID;
$action   = trim($_POST['action'] ?? '');

try {
    // 1. Enforce admin write permission (Security Level >= 2)
    requireAdminWriteAccess();

    if ($action === 'assign_single') {
        $student_id = isset($_POST['student_id']) ? (int)$_POST['student_id'] : 0;
        $faculty_id = isset($_POST['faculty_id']) ? (int)$_POST['faculty_id'] : 0;

        if ($student_id <= 0 || $faculty_id <= 0) {
            throw new Exception("Please select a valid student and faculty member.");
        }

        // Start transaction
        $conn->begin_transaction();

        // 2. Lock and validate Faculty exists and is Full-Time
        $facStmt = $conn->prepare("
            SELECT f.Faculty_ID, f.Faculty_Type, u.User_FName, u.User_LName
            FROM FACULTY f
            JOIN `USER` u ON f.Faculty_ID = u.User_ID
            WHERE f.Faculty_ID = ?
            FOR UPDATE
        ");
        $facStmt->bind_param("i", $faculty_id);
        $facStmt->execute();
        $faculty = $facStmt->get_result()->fetch_assoc();
        $facStmt->close();

        if (!$faculty) {
            throw new Exception("Selected faculty member (ID: {$faculty_id}) does not exist in the database.");
        }

        if ($faculty['Faculty_Type'] !== 'Full-Time') {
            throw new Exception("Part-Time faculty are strictly prohibited from advising students per university regulations.");
        }

        // 3. Lock and count current advisees
        $capStmt = $conn->prepare("SELECT COUNT(*) AS Advisee_Count FROM ADVISOR_STUDENT WHERE Faculty_ID = ? FOR UPDATE");
        $capStmt->bind_param("i", $faculty_id);
        $capStmt->execute();
        $capRow = $capStmt->get_result()->fetch_assoc();
        $capStmt->close();
        $currentAdvisees = (int)($capRow['Advisee_Count'] ?? 0);

        if ($currentAdvisees >= 15) {
            throw new Exception("Faculty member Prof. {$faculty['User_FName']} {$faculty['User_LName']} has reached the maximum statutory capacity of 15 advisees.");
        }

        // 4. Validate Student exists
        $stuStmt = $conn->prepare("
            SELECT s.Student_ID, s.Dept_ID, u.User_FName, u.User_LName
            FROM STUDENT s
            JOIN `USER` u ON s.Student_ID = u.User_ID
            WHERE s.Student_ID = ?
            FOR UPDATE
        ");
        $stuStmt->bind_param("i", $student_id);
        $stuStmt->execute();
        $student = $stuStmt->get_result()->fetch_assoc();
        $stuStmt->close();

        if (!$student) {
            throw new Exception("Selected student (ID: {$student_id}) does not exist in the database.");
        }

        // 5. Check duplicate assignment
        $dupStmt = $conn->prepare("SELECT 1 FROM ADVISOR_STUDENT WHERE Faculty_ID = ? AND Student_ID = ? FOR UPDATE");
        $dupStmt->bind_param("ii", $faculty_id, $student_id);
        $dupStmt->execute();
        if ($dupStmt->get_result()->num_rows > 0) {
            $dupStmt->close();
            throw new Exception("Prof. {$faculty['User_FName']} {$faculty['User_LName']} is already assigned as an advisor for student {$student['User_FName']} {$student['User_LName']}.");
        }
        $dupStmt->close();

        // 6. Verify Department Match between Student and Faculty
        $stuDeptIds = [];
        if (!empty($student['Dept_ID'])) {
            $stuDeptIds[] = (int)$student['Dept_ID'];
        }
        $mStmt = $conn->prepare("
            SELECT m.Department_ID 
            FROM STUDENT_MAJORS sm 
            JOIN MAJORS m ON sm.Major_ID = m.Major_ID 
            WHERE sm.Student_ID = ?
        ");
        $mStmt->bind_param("i", $student_id);
        $mStmt->execute();
        $mRes = $mStmt->get_result();
        while ($mRow = $mRes->fetch_assoc()) {
            if (!empty($mRow['Department_ID'])) {
                $stuDeptIds[] = (int)$mRow['Department_ID'];
            }
        }
        $mStmt->close();
        $stuDeptIds = array_unique($stuDeptIds);

        // Fetch Faculty Departments
        $facDeptIds = [];
        $fDeptStmt = $conn->prepare("SELECT Department_ID FROM DEPARTMENT_ROSTER WHERE Faculty_ID = ?");
        $fDeptStmt->bind_param("i", $faculty_id);
        $fDeptStmt->execute();
        $fDeptRes = $fDeptStmt->get_result();
        while ($fRow = $fDeptRes->fetch_assoc()) {
            $facDeptIds[] = (int)$fRow['Department_ID'];
        }
        $fDeptStmt->close();

        if (empty(array_intersect($stuDeptIds, $facDeptIds))) {
            throw new Exception("Prof. {$faculty['User_FName']} {$faculty['User_LName']} does not belong to any academic department associated with {$student['User_FName']} {$student['User_LName']}'s degree program or major.");
        }

        // 7. Insert Assignment
        $insStmt = $conn->prepare("
            INSERT INTO ADVISOR_STUDENT (Faculty_ID, Student_ID, Date_Assigned)
            VALUES (?, ?, CURDATE())
        ");
        $insStmt->bind_param("ii", $faculty_id, $student_id);
        if (!$insStmt->execute()) {
            throw new Exception("Database insert failed: " . $insStmt->error);
        }
        $insStmt->close();

        // 8. Log to USER_UPDATE_LOG
        $attr = substr("Assigned Advisor ID: " . $faculty_id, 0, 50);
        $logStmt = $conn->prepare("INSERT INTO USER_UPDATE_LOG (Admin_ID, User_ID, Changed_Attribute, Date_Of_Change) VALUES (?, ?, ?, NOW())");
        $logStmt->bind_param("iis", $admin_id, $student_id, $attr);
        $logStmt->execute();
        $logStmt->close();

        $conn->commit();

        $_SESSION['success_message'] = "Prof. {$faculty['User_FName']} {$faculty['User_LName']} was successfully assigned as an advisor to student {$student['User_FName']} {$student['User_LName']} (ID: {$student_id})!";
        header("Location: manage_advisors.php?tab=students&dept=" . ($student['Dept_ID'] ?? ''));
        exit;

    } elseif ($action === 'assign_batch') {
        $student_ids = $_POST['student_ids'] ?? [];
        $faculty_id  = isset($_POST['faculty_id']) ? (int)$_POST['faculty_id'] : 0;

        if (!is_array($student_ids) || empty($student_ids)) {
            throw new Exception("Please select at least one student to assign.");
        }
        if ($faculty_id <= 0) {
            throw new Exception("Please choose a valid faculty advisor.");
        }

        // Filter and sanitize IDs
        $cleanStudentIds = [];
        foreach ($student_ids as $sid) {
            $sidInt = (int)$sid;
            if ($sidInt > 0) {
                $cleanStudentIds[] = $sidInt;
            }
        }
        $cleanStudentIds = array_values(array_unique($cleanStudentIds));

        if (empty($cleanStudentIds)) {
            throw new Exception("No valid students were selected.");
        }

        // 1. Start transaction BEFORE checking capacity
        $conn->begin_transaction();

        // 2. Lock faculty row
        $facStmt = $conn->prepare("
            SELECT f.Faculty_ID, f.Faculty_Type, u.User_FName, u.User_LName
            FROM FACULTY f
            JOIN `USER` u ON f.Faculty_ID = u.User_ID
            WHERE f.Faculty_ID = ?
            FOR UPDATE
        ");
        $facStmt->bind_param("i", $faculty_id);
        $facStmt->execute();
        $faculty = $facStmt->get_result()->fetch_assoc();
        $facStmt->close();

        if (!$faculty) {
            throw new Exception("Selected faculty member (ID: {$faculty_id}) does not exist.");
        }

        if ($faculty['Faculty_Type'] !== 'Full-Time') {
            throw new Exception("Part-Time faculty are strictly prohibited from advising students per university regulations.");
        }

        // 3. Fetch existing advisee IDs for this faculty member
        $advStmt = $conn->prepare("SELECT Student_ID FROM ADVISOR_STUDENT WHERE Faculty_ID = ? FOR UPDATE");
        $advStmt->bind_param("i", $faculty_id);
        $advStmt->execute();
        $advRes = $advStmt->get_result();
        $existingAdvisees = [];
        while ($row = $advRes->fetch_assoc()) {
            $existingAdvisees[] = (int)$row['Student_ID'];
        }
        $advStmt->close();

        // 4. Calculate how many students in $cleanStudentIds are NOT already assigned
        $newStudentsToAssign = array_values(array_diff($cleanStudentIds, $existingAdvisees));

        if (count($existingAdvisees) + count($newStudentsToAssign) > 15) {
            $availableSpots = max(0, 15 - count($existingAdvisees));
            throw new Exception("Prof. {$faculty['User_FName']} {$faculty['User_LName']} only has {$availableSpots} available advising spot(s) remaining (Current: " . count($existingAdvisees) . "/15), but " . count($newStudentsToAssign) . " new students were selected. Statutory limit is 15 advisees.");
        }

        // Fetch Faculty Departments
        $facDeptIds = [];
        $fDeptStmt = $conn->prepare("SELECT Department_ID FROM DEPARTMENT_ROSTER WHERE Faculty_ID = ?");
        $fDeptStmt->bind_param("i", $faculty_id);
        $fDeptStmt->execute();
        $fDeptRes = $fDeptStmt->get_result();
        while ($fRow = $fDeptRes->fetch_assoc()) {
            $facDeptIds[] = (int)$fRow['Department_ID'];
        }
        $fDeptStmt->close();

        // Prepare statements ONCE outside foreach loop
        $stuDeptStmt = $conn->prepare("SELECT Dept_ID FROM STUDENT WHERE Student_ID = ?");
        $stuMajorsStmt = $conn->prepare("
            SELECT m.Department_ID 
            FROM STUDENT_MAJORS sm 
            JOIN MAJORS m ON sm.Major_ID = m.Major_ID 
            WHERE sm.Student_ID = ?
        ");
        $insStmt = $conn->prepare("
            INSERT INTO ADVISOR_STUDENT (Faculty_ID, Student_ID, Date_Assigned)
            VALUES (?, ?, CURDATE())
            ON DUPLICATE KEY UPDATE Date_Assigned = VALUES(Date_Assigned)
        ");
        $logStmt = $conn->prepare("
            INSERT INTO USER_UPDATE_LOG (Admin_ID, User_ID, Changed_Attribute, Date_Of_Change)
            VALUES (?, ?, ?, NOW())
        ");

        $assignedCount = 0;
        $skippedCount  = 0;

        foreach ($cleanStudentIds as $sid) {
            // Verify student department affiliation
            $stuDeptIds = [];
            $stuDeptStmt->bind_param("i", $sid);
            $stuDeptStmt->execute();
            $sRow = $stuDeptStmt->get_result()->fetch_assoc();
            if ($sRow && !empty($sRow['Dept_ID'])) {
                $stuDeptIds[] = (int)$sRow['Dept_ID'];
            }

            $stuMajorsStmt->bind_param("i", $sid);
            $stuMajorsStmt->execute();
            $mRes = $stuMajorsStmt->get_result();
            while ($mRow = $mRes->fetch_assoc()) {
                if (!empty($mRow['Department_ID'])) {
                    $stuDeptIds[] = (int)$mRow['Department_ID'];
                }
            }
            $stuDeptIds = array_unique($stuDeptIds);

            if (empty(array_intersect($stuDeptIds, $facDeptIds))) {
                $skippedCount++;
                continue; // Student does not belong to faculty's department
            }

            $insStmt->bind_param("ii", $faculty_id, $sid);
            if ($insStmt->execute()) {
                $assignedCount++;
                $attr = substr("Assigned Advisor ID: " . $faculty_id, 0, 50);
                $logStmt->bind_param("iis", $admin_id, $sid, $attr);
                $logStmt->execute();
            }
        }

        $stuDeptStmt->close();
        $stuMajorsStmt->close();
        $insStmt->close();
        $logStmt->close();

        $conn->commit();

        $msg = "Successfully assigned {$assignedCount} student(s) to Prof. {$faculty['User_FName']} {$faculty['User_LName']}!";
        if ($skippedCount > 0) {
            $msg .= " ({$skippedCount} student(s) were skipped due to department mismatch).";
        }
        $_SESSION['success_message'] = $msg;
        header("Location: manage_advisors.php?tab=students");
        exit;

    } elseif ($action === 'unassign') {
        $student_id = isset($_POST['student_id']) ? (int)$_POST['student_id'] : 0;
        $faculty_id = isset($_POST['faculty_id']) ? (int)$_POST['faculty_id'] : 0;

        if ($student_id <= 0 || $faculty_id <= 0) {
            throw new Exception("Invalid student or faculty ID provided.");
        }

        $conn->begin_transaction();

        $delStmt = $conn->prepare("DELETE FROM ADVISOR_STUDENT WHERE Faculty_ID = ? AND Student_ID = ?");
        $delStmt->bind_param("ii", $faculty_id, $student_id);
        if (!$delStmt->execute()) {
            throw new Exception("Failed to remove advising assignment: " . $delStmt->error);
        }
        $delStmt->close();

        $attr = substr("Removed Advisor ID: " . $faculty_id, 0, 50);
        $logStmt = $conn->prepare("INSERT INTO USER_UPDATE_LOG (Admin_ID, User_ID, Changed_Attribute, Date_Of_Change) VALUES (?, ?, ?, NOW())");
        $logStmt->bind_param("iis", $admin_id, $student_id, $attr);
        $logStmt->execute();
        $logStmt->close();

        $conn->commit();

        $_SESSION['success_message'] = "Advising assignment between Student (ID: {$student_id}) and Faculty (ID: {$faculty_id}) was successfully removed.";
        header("Location: manage_advisors.php?tab=" . urlencode($_POST['return_tab'] ?? 'students'));
        exit;

    } else {
        throw new Exception("Unknown action specified.");
    }

} catch (Exception $e) {
    if (isset($conn) && $conn->in_transaction) {
        $conn->rollback();
    }
    $_SESSION['error_message'] = $e->getMessage();
    header("Location: manage_advisors.php?tab=" . urlencode($_POST['return_tab'] ?? 'students'));
    exit;
}
