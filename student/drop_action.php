<?php
require_once 'student_guard.php';
$student_id = (int)$userID;

$post_semester = filter_var($_POST['semester'] ?? null, FILTER_VALIDATE_INT) ?: 202608;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: register.php?semester=" . (int)$post_semester);
    exit;
}

$crn = filter_var($_POST['crn'] ?? null, FILTER_VALIDATE_INT);
if (!$crn || $crn <= 0) {
    $_SESSION['flash_error'] = "Invalid CRN provided.";
    header("Location: register.php?semester=" . (int)$post_semester);
    exit;
}

require_once '../db_connect.php';

$semester_id = $post_semester;

try {
    $conn->begin_transaction();

    // 1. Lock enrollment and section to prevent double-drop race conditions
    $stmt = $conn->prepare("
        SELECT cs.Course_ID, cs.Semester_ID, cs.Available_seats
        FROM ENROLLMENT e
        JOIN COURSE_SECTIONS cs ON e.CRN = cs.CRN
        WHERE e.Student_ID = ? AND e.CRN = ?
        FOR UPDATE
    ");
    $stmt->bind_param("ii", $student_id, $crn);
    $stmt->execute();
    $enrollment = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$enrollment) {
        throw new Exception("You are not currently registered for this course section.");
    }

    $course_id = $enrollment['Course_ID'];
    $semester_id = (int)$enrollment['Semester_ID'];

    // 2. Sole Constraint: Verify semester drop deadline in INFORMATION_RELATION
    $winStmt = $conn->prepare("
        SELECT (CURRENT_DATE() <= ir.Last_Day_Of_Dropping_Course) AS can_drop
        FROM INFORMATION_RELATION ir
        WHERE ir.Semester_ID = ?
    ");
    $winStmt->bind_param("i", $semester_id);
    $winStmt->execute();
    $win = $winStmt->get_result()->fetch_assoc();
    $winStmt->close();

    if (!$win || !(int)$win['can_drop']) {
        throw new Exception("The deadline to drop courses for this semester has passed.");
    }

    // 3. Atomically remove enrollment and restore seat
    $del = $conn->prepare("DELETE FROM ENROLLMENT WHERE CRN = ? AND Student_ID = ?");
    $del->bind_param("ii", $crn, $student_id);
    $del->execute();
    $deleted = $del->affected_rows;
    $del->close();

    if ($deleted !== 1) {
        throw new Exception("Course drop could not be processed.");
    }

    $upd = $conn->prepare("UPDATE COURSE_SECTIONS SET Available_seats = Available_seats + 1 WHERE CRN = ?");
    $upd->bind_param("i", $crn);
    $upd->execute();
    $upd->close();

    $conn->commit();
    $_SESSION['flash_success'] = "Successfully dropped {$course_id} (CRN: {$crn}).";
} catch (Exception $e) {
    if (isset($conn) && $conn->in_transaction) {
        $conn->rollback();
    }
    $_SESSION['flash_error'] = $e->getMessage();
}

$redirect_semester = $semester_id ?: $post_semester;
header("Location: register.php?semester=" . (int)$redirect_semester);
exit;
