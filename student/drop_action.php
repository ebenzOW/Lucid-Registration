<?php
/**
 * Lucid University Course Registration System
 * Backend POST Handler: Drop Course-Section for Spring 2027 (Semester_ID = 202701)
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

// Relative Path Database Connection Handling
if (file_exists(__DIR__ . '/../dblogin.php')) {
    require_once __DIR__ . '/../dblogin.php';
}

if (!isset($conn) || !($conn instanceof mysqli)) {
    if (isset($hostname) && isset($username) && isset($db)) {
        $conn = new mysqli($hostname, $username, $password ?? '', $db);
    } elseif (file_exists(__DIR__ . '/../db_connect.php')) {
        require_once __DIR__ . '/../db_connect.php';
    }
}

if (!isset($conn) || $conn->connect_error) {
    $_SESSION['flash_error'] = "Database connection error. Please try again later.";
    header("Location: register.php");
    exit;
}

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
$dropWinStmt = $conn->prepare("
    SELECT ir.Last_Day_Of_Dropping_Course, 
           (CURRENT_DATE() <= ir.Last_Day_Of_Dropping_Course) AS can_drop,
           s.Semester_Name, s.Semester_Year
    FROM INFORMATION_RELATION ir
    JOIN SEMESTER s ON ir.Semester_ID = s.Semester_ID
    WHERE ir.Semester_ID = ?
");
$dropWinStmt->bind_param("i", $semester_id);
$dropWinStmt->execute();
$dropWinRes = $dropWinStmt->get_result();
$dropWinRow = $dropWinRes->fetch_assoc();
$dropWinStmt->close();

if (!$dropWinRow || !(int)$dropWinRow['can_drop']) {
    $semLabel = $dropWinRow ? ($dropWinRow['Semester_Name'] . ' ' . $dropWinRow['Semester_Year']) : "this semester";
    $_SESSION['flash_error'] = "The deadline to drop courses for " . $semLabel . " has passed.";
    header("Location: register.php");
    exit;
}

// 3. Perform atomic drop transaction
try {
    $conn->begin_transaction();

    // Remove from ENROLLMENT
    $delStmt = $conn->prepare("DELETE FROM ENROLLMENT WHERE CRN = ? AND Student_ID = ?");
    $delStmt->bind_param("ii", $crn, $student_id);
    $delStmt->execute();
    $delStmt->close();

    // Increment Available_seats back in COURSE_SECTIONS
    $incStmt = $conn->prepare("UPDATE COURSE_SECTIONS SET Available_seats = Available_seats + 1 WHERE CRN = ?");
    $incStmt->bind_param("i", $crn);
    $incStmt->execute();
    $incStmt->close();

    $conn->commit();

    $_SESSION['flash_success'] = "Successfully dropped " . $course_id . " (CRN: " . $crn . ")!";
    header("Location: register.php");
    exit;

} catch (Exception $e) {
    if ($conn) {
        $conn->rollback();
    }
    $_SESSION['flash_error'] = "Failed to drop course due to a system error: " . $e->getMessage();
    header("Location: register.php");
    exit;
}
