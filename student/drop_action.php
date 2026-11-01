<?php
/**
 * Lucid University Course Registration System
 * Backend POST Handler: Drop Course-Section with Concurrency Protection
 */

require_once 'student_guard.php';
$student_id = (int)$userID;

// Validate Request Method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['flash_error'] = "Invalid request method.";
    header("Location: register.php");
    exit;
}

// Validate CRN input
if (!isset($_POST['crn']) || empty($_POST['crn'])) {
    $_SESSION['flash_error'] = "Please specify a valid course section to drop.";
    header("Location: register.php");
    exit;
}

$crn = filter_var($_POST['crn'], FILTER_VALIDATE_INT);
if ($crn === false || $crn <= 0) {
    $_SESSION['flash_error'] = "Invalid CRN provided.";
    header("Location: register.php");
    exit;
}

// Database Connection
require_once '../db_connect.php';

// 1. Verify that the student is actually enrolled in this section
$enrollCheckStmt = $conn->prepare("
    SELECT cs.Course_ID, cs.Semester_ID
    FROM ENROLLMENT e
    JOIN COURSE_SECTIONS cs ON e.CRN = cs.CRN
    WHERE e.Student_ID = ? AND e.CRN = ?
");
$enrollCheckStmt->bind_param("ii", $student_id, $crn);
$enrollCheckStmt->execute();
$enrollCheckRes = $enrollCheckStmt->get_result();
$enrollRow = $enrollCheckRes->fetch_assoc();
$enrollCheckStmt->close();

if (!$enrollRow) {
    $_SESSION['flash_error'] = "You are not currently registered for this course section.";
    header("Location: register.php");
    exit;
}

$course_id   = $enrollRow['Course_ID'];
$semester_id = (int)$enrollRow['Semester_ID'];

// 2. Check Drop Deadline in INFORMATION_RELATION for this section's semester
// Why: Enforce academic calendar deadline; courses cannot be dropped after Last_Day_Of_Dropping_Course.
$dropWinStmt = $conn->prepare("
    SELECT (CURRENT_DATE() <= ir.Last_Day_Of_Dropping_Course) AS can_drop
    FROM INFORMATION_RELATION ir
    WHERE ir.Semester_ID = ?
");
$dropWinStmt->bind_param("i", $semester_id);
$dropWinStmt->execute();
$dropWinRes = $dropWinStmt->get_result();
$dropWinRow = $dropWinRes->fetch_assoc();
$dropWinStmt->close();

if (!$dropWinRow || !(int)$dropWinRow['can_drop']) {
    $_SESSION['flash_error'] = "The deadline to drop courses for this semester has passed.";
    header("Location: register.php");
    exit;
}

// 3. Check for Active Account Holds
// Why: Students with active holds cannot drop or add courses per SRS hold constraints.
$holdCheckStmt = $conn->prepare("SELECT COUNT(*) AS cnt FROM STUDENT_HOLDS WHERE Student_ID = ?");
$holdCheckStmt->bind_param("i", $student_id);
$holdCheckStmt->execute();
$holdCheckRes = $holdCheckStmt->get_result();
$holdCount = (int)($holdCheckRes->fetch_assoc()['cnt'] ?? 0);
$holdCheckStmt->close();

if ($holdCount > 0) {
    $_SESSION['flash_error'] = "Cannot drop course: You have an active hold on your account.";
    header("Location: register.php");
    exit;
}

// 4. Perform atomic drop transaction with row-level locking
try {
    $conn->begin_transaction();

    // Why: Row-lock the COURSE_SECTIONS record to prevent race conditions during seat mutations.
    $lockStmt = $conn->prepare("SELECT Available_seats FROM COURSE_SECTIONS WHERE CRN = ? FOR UPDATE");
    $lockStmt->bind_param("i", $crn);
    $lockStmt->execute();
    $lockStmt->close();

    // Delete the record from ENROLLMENT
    $delStmt = $conn->prepare("DELETE FROM ENROLLMENT WHERE CRN = ? AND Student_ID = ?");
    $delStmt->bind_param("ii", $crn, $student_id);
    $delStmt->execute();
    $delStmt->close();

    // Increment seat availability
    $incStmt = $conn->prepare("UPDATE COURSE_SECTIONS SET Available_seats = Available_seats + 1 WHERE CRN = ?");
    $incStmt->bind_param("i", $crn);
    $incStmt->execute();
    $incStmt->close();

    $conn->commit();

    $_SESSION['flash_success'] = "Successfully dropped " . $course_id . " (CRN: " . $crn . ")!";
    header("Location: register.php");
    exit;

} catch (Exception $e) {
    if (isset($conn) && $conn->in_transaction) {
        $conn->rollback();
    }
    $_SESSION['flash_error'] = "Failed to drop course due to a system error: " . $e->getMessage();
    header("Location: register.php");
    exit;
}
