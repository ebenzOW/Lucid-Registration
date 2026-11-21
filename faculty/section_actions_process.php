<?php
/**
 * Lucid University Course Registration System
 * Faculty Section Actions Process (Midterm Grades, Final Grades, Attendance)
 */

require_once 'faculty_guard.php';
$faculty_id = (int)$userID;

require_once '../db_connect.php';
require_once __DIR__ . '/../includes/schedule_helpers.php';
require_once __DIR__ . '/../includes/student_helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: dashboard.php");
    exit;
}

$crn = isset($_POST['crn']) ? (int)$_POST['crn'] : 0;
$action = $_POST['action'] ?? '';
$returnTab = in_array($_POST['return_tab'] ?? '', ['roster', 'attendance', 'grades'], true) ? $_POST['return_tab'] : 'roster';

if ($crn <= 0) {
    $_SESSION['error_message'] = "Invalid section requested.";
    header("Location: dashboard.php");
    exit;
}

// Verify section belongs to logged-in faculty and is Current Semester (202608)
$secStmt = $conn->prepare("
    SELECT cs.CRN, cs.Semester_ID, ir.Date_Of_Midterm_Grade_Recording, ir.Date_Of_Final_Grades_Recording
    FROM COURSE_SECTIONS cs
    JOIN INFORMATION_RELATION ir ON cs.Semester_ID = ir.Semester_ID
    WHERE cs.CRN = ? AND cs.Faculty_ID = ? AND cs.Semester_ID = 202608
");
$secStmt->bind_param("ii", $crn, $faculty_id);
$secStmt->execute();
$sec = $secStmt->get_result()->fetch_assoc();
$secStmt->close();

if (!$sec) {
    $_SESSION['error_message'] = "Unauthorized or section not eligible for grading/attendance.";
    header("Location: dashboard.php");
    exit;
}

$allowedGrades = ['', 'A', 'A-', 'B+', 'B', 'B-', 'C+', 'C', 'C-', 'D+', 'D', 'F'];
$inTransaction = false;

try {
    switch ($action) {
        case 'submit_midterm':
            if (!isWithinGradeWindow($sec['Date_Of_Midterm_Grade_Recording'], 72)) {
                $_SESSION['error_message'] = "Can not submit grades outside of time-window.";
                header("Location: dashboard.php?roster_crn={$crn}&tab=grades#section-workspace");
                exit;
            }

            $conn->begin_transaction();
            $inTransaction = true;

            // Lock enrolled rows for this section
            $lockStmt = $conn->prepare("SELECT Student_ID FROM ENROLLMENT WHERE CRN = ? FOR UPDATE");
            $lockStmt->bind_param("i", $crn);
            $lockStmt->execute();
            $enrolledRes = $lockStmt->get_result();
            $enrolledStudents = [];
            while ($r = $enrolledRes->fetch_assoc()) {
                $enrolledStudents[] = (int)$r['Student_ID'];
            }
            $lockStmt->close();

            $submittedGrades = $_POST['midterm_grades'] ?? [];
            $updateStmt = $conn->prepare("UPDATE ENROLLMENT SET Midterm_Grade = ? WHERE CRN = ? AND Student_ID = ?");

            foreach ($enrolledStudents as $sid) {
                if (isset($submittedGrades[$sid])) {
                    $gradeVal = trim((string)$submittedGrades[$sid]);
                    if (in_array($gradeVal, $allowedGrades, true)) {
                        $gradeParam = ($gradeVal === '') ? null : $gradeVal;
                        $updateStmt->bind_param("sii", $gradeParam, $crn, $sid);
                        $updateStmt->execute();
                    }
                }
            }
            $updateStmt->close();

            $conn->commit();
            $inTransaction = false;
            $_SESSION['success_message'] = "Midterm grades submitted successfully.";
            header("Location: dashboard.php?roster_crn={$crn}&tab=grades#section-workspace");
            exit;

        case 'submit_final':
            if (!isWithinGradeWindow($sec['Date_Of_Final_Grades_Recording'], 72)) {
                $_SESSION['error_message'] = "Can not submit grades outside of time-window.";
                header("Location: dashboard.php?roster_crn={$crn}&tab=grades#section-workspace");
                exit;
            }

            $conn->begin_transaction();
            $inTransaction = true;

            // Lock enrolled rows with current Midterm_Grade
            $lockStmt = $conn->prepare("SELECT Student_ID, Midterm_Grade FROM ENROLLMENT WHERE CRN = ? FOR UPDATE");
            $lockStmt->bind_param("i", $crn);
            $lockStmt->execute();
            $enrolledRes = $lockStmt->get_result();
            $enrolledData = [];
            while ($r = $enrolledRes->fetch_assoc()) {
                $enrolledData[] = $r;
            }
            $lockStmt->close();

            $submittedFinals = $_POST['final_exam_grades'] ?? [];
            $updateGradeStmt = $conn->prepare("UPDATE ENROLLMENT SET Final_Grade = ?, Grade = ? WHERE CRN = ? AND Student_ID = ?");

            foreach ($enrolledData as $row) {
                $sid = (int)$row['Student_ID'];
                if (isset($submittedFinals[$sid])) {
                    $examGrade = trim((string)$submittedFinals[$sid]);
                    if ($examGrade !== '' && in_array($examGrade, $allowedGrades, true)) {
                        $finalCourseGrade = averageMidtermAndFinalGrade($row['Midterm_Grade'], $examGrade);
                        $updateGradeStmt->bind_param("ssii", $examGrade, $finalCourseGrade, $crn, $sid);
                        $updateGradeStmt->execute();
                    }
                }
            }
            $updateGradeStmt->close();

            $conn->commit();
            $inTransaction = false;
            $_SESSION['success_message'] = "Final grades averaged with midterm grades and submitted successfully.";
            header("Location: dashboard.php?roster_crn={$crn}&tab=grades#section-workspace");
            exit;

        case 'submit_attendance':
            $meetingMeta = getSectionMeetingDates($conn, $crn);
            if (!$meetingMeta['is_live_now']) {
                $_SESSION['error_message'] = "Cannot submit attendance outside the scheduled class date and time-slot.";
                header("Location: dashboard.php?roster_crn={$crn}&tab=attendance#section-workspace");
                exit;
            }

            $conn->begin_transaction();
            $inTransaction = true;

            // Lock enrolled rows
            $lockStmt = $conn->prepare("SELECT Student_ID FROM ENROLLMENT WHERE CRN = ? FOR UPDATE");
            $lockStmt->bind_param("i", $crn);
            $lockStmt->execute();
            $enrolledRes = $lockStmt->get_result();
            $enrolledStudents = [];
            while ($r = $enrolledRes->fetch_assoc()) {
                $enrolledStudents[] = (int)$r['Student_ID'];
            }
            $lockStmt->close();

            $presentIds = isset($_POST['present_students']) && is_array($_POST['present_students'])
                ? array_map('intval', $_POST['present_students'])
                : [];

            $today = date('Y-m-d');
            $attStmt = $conn->prepare("
                INSERT INTO ATTENDANCE (CRN, Student_ID, Class_Date, Status)
                VALUES (?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE Status = VALUES(Status), Recorded_At = CURRENT_TIMESTAMP
            ");

            foreach ($enrolledStudents as $sid) {
                $status = in_array($sid, $presentIds, true) ? 'Present' : 'Absent';
                $attStmt->bind_param("iiss", $crn, $sid, $today, $status);
                $attStmt->execute();
            }
            $attStmt->close();

            $conn->commit();
            $inTransaction = false;
            $_SESSION['success_message'] = "Attendance submission successful";
            header("Location: dashboard.php?roster_crn={$crn}&tab=attendance#section-workspace");
            exit;

        default:
            $_SESSION['error_message'] = "Unrecognized action requested.";
            header("Location: dashboard.php?roster_crn={$crn}&tab={$returnTab}#section-workspace");
            exit;
    }
} catch (Exception $e) {
    if ($inTransaction) {
        $conn->rollback();
        $inTransaction = false;
    }
    $_SESSION['error_message'] = "Transaction error: " . $e->getMessage();
    header("Location: dashboard.php?roster_crn={$crn}&tab={$returnTab}#section-workspace");
    exit;
}
