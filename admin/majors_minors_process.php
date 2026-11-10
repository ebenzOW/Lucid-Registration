<?php
/**
 * Lucid University Course Registration System
 * Admin Portal - Process Majors, Minors & Course Requirements
 *
 * Implements transaction-wrapped CRUD for academic majors, minors,
 * and curriculum course requirements.
 */

require_once 'admin_guard.php';
require_once '../db_connect.php';
require_once __DIR__ . '/../includes/academic_helpers.php';

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: majors_minors.php");
    exit;
}

$action = trim($_POST['action'] ?? '');

try {
    // 1. Enforce admin write permission (Security Level >= 2)
    requireAdminWriteAccess();

    if ($action === 'create_major') {
        $major_id            = isset($_POST['major_id']) ? (int)$_POST['major_id'] : 0;
        $major_name          = trim($_POST['major_name'] ?? '');
        $department_id       = isset($_POST['department_id']) ? (int)$_POST['department_id'] : 0;
        $no_credits_required = isset($_POST['no_credits_required']) ? (int)$_POST['no_credits_required'] : 0;

        if ($major_id <= 0) {
            throw new Exception("Please specify a valid numeric Major ID.");
        }
        if (empty($major_name)) {
            throw new Exception("Major Program Name cannot be blank.");
        }
        if ($department_id <= 0) {
            throw new Exception("Please select a valid academic department.");
        }
        if ($no_credits_required <= 0) {
            throw new Exception("Required credits must be greater than zero.");
        }

        $conn->begin_transaction();

        $dupStmt = $conn->prepare("SELECT 1 FROM MAJORS WHERE Major_ID = ? FOR UPDATE");
        $dupStmt->bind_param("i", $major_id);
        $dupStmt->execute();
        if ($dupStmt->get_result()->num_rows > 0) {
            $dupStmt->close();
            throw new Exception("Major ID {$major_id} already exists in the system.");
        }
        $dupStmt->close();

        $insStmt = $conn->prepare("INSERT INTO MAJORS (Major_ID, Department_ID, Major_Name, No_Credits_Required) VALUES (?, ?, ?, ?)");
        $insStmt->bind_param("iisi", $major_id, $department_id, $major_name, $no_credits_required);
        if (!$insStmt->execute()) {
            throw new Exception("Database error inserting major: " . $insStmt->error);
        }
        $insStmt->close();

        $conn->commit();
        $_SESSION['success_message'] = "Academic Major '{$major_name}' (ID: {$major_id}) was successfully created!";
        header("Location: majors_minors.php");
        exit;

    } elseif ($action === 'update_major') {
        $major_id            = isset($_POST['major_id']) ? (int)$_POST['major_id'] : 0;
        $major_name          = trim($_POST['major_name'] ?? '');
        $department_id       = isset($_POST['department_id']) ? (int)$_POST['department_id'] : 0;
        $no_credits_required = isset($_POST['no_credits_required']) ? (int)$_POST['no_credits_required'] : 0;

        if ($major_id <= 0) {
            throw new Exception("Invalid Major ID.");
        }
        if (empty($major_name)) {
            throw new Exception("Major Program Name cannot be blank.");
        }
        if ($department_id <= 0) {
            throw new Exception("Please select a valid academic department.");
        }
        if ($no_credits_required <= 0) {
            throw new Exception("Required credits must be greater than zero.");
        }

        $conn->begin_transaction();

        $checkStmt = $conn->prepare("SELECT 1 FROM MAJORS WHERE Major_ID = ? FOR UPDATE");
        $checkStmt->bind_param("i", $major_id);
        $checkStmt->execute();
        if ($checkStmt->get_result()->num_rows === 0) {
            $checkStmt->close();
            throw new Exception("Major ID {$major_id} does not exist.");
        }
        $checkStmt->close();

        $updStmt = $conn->prepare("UPDATE MAJORS SET Department_ID = ?, Major_Name = ?, No_Credits_Required = ? WHERE Major_ID = ?");
        $updStmt->bind_param("isii", $department_id, $major_name, $no_credits_required, $major_id);
        if (!$updStmt->execute()) {
            throw new Exception("Failed to update major: " . $updStmt->error);
        }
        $updStmt->close();

        $conn->commit();
        $_SESSION['success_message'] = "Major '{$major_name}' was successfully updated!";
        header("Location: edit_major.php?id=" . $major_id);
        exit;

    } elseif ($action === 'add_major_requirement') {
        $major_id  = isset($_POST['major_id']) ? (int)$_POST['major_id'] : 0;
        $course_id = strtoupper(trim($_POST['course_id'] ?? ''));
        $min_grade = strtoupper(trim($_POST['min_grade'] ?? 'C'));

        if ($major_id <= 0 || empty($course_id)) {
            throw new Exception("Valid Major and Course ID are required.");
        }

        $conn->begin_transaction();

        $insStmt = $conn->prepare("
            INSERT INTO MAJOR_REQUIREMENTS (Major_ID, Course_ID, Min_Grade)
            VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE Min_Grade = VALUES(Min_Grade)
        ");
        $insStmt->bind_param("iss", $major_id, $course_id, $min_grade);
        if (!$insStmt->execute()) {
            throw new Exception("Failed to add major requirement: " . $insStmt->error);
        }
        $insStmt->close();

        $conn->commit();
        $_SESSION['success_message'] = "Course requirement {$course_id} (Min Grade: {$min_grade}) added to major!";
        header("Location: edit_major.php?id=" . $major_id);
        exit;

    } elseif ($action === 'delete_major_requirement') {
        $major_id  = isset($_POST['major_id']) ? (int)$_POST['major_id'] : 0;
        $course_id = strtoupper(trim($_POST['course_id'] ?? ''));

        if ($major_id <= 0 || empty($course_id)) {
            throw new Exception("Invalid parameters for requirement removal.");
        }

        $conn->begin_transaction();

        $delStmt = $conn->prepare("DELETE FROM MAJOR_REQUIREMENTS WHERE Major_ID = ? AND Course_ID = ?");
        $delStmt->bind_param("is", $major_id, $course_id);
        if (!$delStmt->execute()) {
            throw new Exception("Failed to remove course requirement: " . $delStmt->error);
        }
        $delStmt->close();

        $conn->commit();
        $_SESSION['success_message'] = "Course {$course_id} removed from major requirements.";
        header("Location: edit_major.php?id=" . $major_id);
        exit;

    } elseif ($action === 'create_minor') {
        $minor_id            = isset($_POST['minor_id']) ? (int)$_POST['minor_id'] : 0;
        $minor_name          = trim($_POST['minor_name'] ?? '');
        $department_id       = isset($_POST['department_id']) ? (int)$_POST['department_id'] : 0;
        $no_credits_required = isset($_POST['no_credits_required']) ? (int)$_POST['no_credits_required'] : 0;

        if ($minor_id <= 0) {
            throw new Exception("Please specify a valid numeric Minor ID.");
        }
        if (empty($minor_name)) {
            throw new Exception("Minor Program Name cannot be blank.");
        }
        if ($department_id <= 0) {
            throw new Exception("Please select a valid academic department.");
        }
        if ($no_credits_required <= 0) {
            throw new Exception("Required credits must be greater than zero.");
        }

        $conn->begin_transaction();

        $dupStmt = $conn->prepare("SELECT 1 FROM MINORS WHERE Minor_ID = ? FOR UPDATE");
        $dupStmt->bind_param("i", $minor_id);
        $dupStmt->execute();
        if ($dupStmt->get_result()->num_rows > 0) {
            $dupStmt->close();
            throw new Exception("Minor ID {$minor_id} already exists in the system.");
        }
        $dupStmt->close();

        $insStmt = $conn->prepare("INSERT INTO MINORS (Minor_ID, Department_ID, Minor_Name, No_Credits_Required) VALUES (?, ?, ?, ?)");
        $insStmt->bind_param("iisi", $minor_id, $department_id, $minor_name, $no_credits_required);
        if (!$insStmt->execute()) {
            throw new Exception("Database error inserting minor: " . $insStmt->error);
        }
        $insStmt->close();

        $conn->commit();
        $_SESSION['success_message'] = "Academic Minor '{$minor_name}' (ID: {$minor_id}) was successfully created!";
        header("Location: majors_minors.php");
        exit;

    } elseif ($action === 'update_minor') {
        $minor_id            = isset($_POST['minor_id']) ? (int)$_POST['minor_id'] : 0;
        $minor_name          = trim($_POST['minor_name'] ?? '');
        $department_id       = isset($_POST['department_id']) ? (int)$_POST['department_id'] : 0;
        $no_credits_required = isset($_POST['no_credits_required']) ? (int)$_POST['no_credits_required'] : 0;

        if ($minor_id <= 0) {
            throw new Exception("Invalid Minor ID.");
        }
        if (empty($minor_name)) {
            throw new Exception("Minor Program Name cannot be blank.");
        }
        if ($department_id <= 0) {
            throw new Exception("Please select a valid academic department.");
        }
        if ($no_credits_required <= 0) {
            throw new Exception("Required credits must be greater than zero.");
        }

        $conn->begin_transaction();

        $checkStmt = $conn->prepare("SELECT 1 FROM MINORS WHERE Minor_ID = ? FOR UPDATE");
        $checkStmt->bind_param("i", $minor_id);
        $checkStmt->execute();
        if ($checkStmt->get_result()->num_rows === 0) {
            $checkStmt->close();
            throw new Exception("Minor ID {$minor_id} does not exist.");
        }
        $checkStmt->close();

        $updStmt = $conn->prepare("UPDATE MINORS SET Department_ID = ?, Minor_Name = ?, No_Credits_Required = ? WHERE Minor_ID = ?");
        $updStmt->bind_param("isii", $department_id, $minor_name, $no_credits_required, $minor_id);
        if (!$updStmt->execute()) {
            throw new Exception("Failed to update minor: " . $updStmt->error);
        }
        $updStmt->close();

        $conn->commit();
        $_SESSION['success_message'] = "Minor '{$minor_name}' was successfully updated!";
        header("Location: edit_minor.php?id=" . $minor_id);
        exit;

    } elseif ($action === 'add_minor_requirement') {
        $minor_id  = isset($_POST['minor_id']) ? (int)$_POST['minor_id'] : 0;
        $course_id = strtoupper(trim($_POST['course_id'] ?? ''));
        $min_grade = strtoupper(trim($_POST['min_grade'] ?? 'C'));

        if ($minor_id <= 0 || empty($course_id)) {
            throw new Exception("Valid Minor and Course ID are required.");
        }

        $conn->begin_transaction();

        $insStmt = $conn->prepare("
            INSERT INTO MINOR_REQUIREMENTS (Minor_ID, Course_ID, Min_Grade)
            VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE Min_Grade = VALUES(Min_Grade)
        ");
        $insStmt->bind_param("iss", $minor_id, $course_id, $min_grade);
        if (!$insStmt->execute()) {
            throw new Exception("Failed to add minor requirement: " . $insStmt->error);
        }
        $insStmt->close();

        $conn->commit();
        $_SESSION['success_message'] = "Course requirement {$course_id} (Min Grade: {$min_grade}) added to minor!";
        header("Location: edit_minor.php?id=" . $minor_id);
        exit;

    } elseif ($action === 'delete_minor_requirement') {
        $minor_id  = isset($_POST['minor_id']) ? (int)$_POST['minor_id'] : 0;
        $course_id = strtoupper(trim($_POST['course_id'] ?? ''));

        if ($minor_id <= 0 || empty($course_id)) {
            throw new Exception("Invalid parameters for requirement removal.");
        }

        $conn->begin_transaction();

        $delStmt = $conn->prepare("DELETE FROM MINOR_REQUIREMENTS WHERE Minor_ID = ? AND Course_ID = ?");
        $delStmt->bind_param("is", $minor_id, $course_id);
        if (!$delStmt->execute()) {
            throw new Exception("Failed to remove course requirement: " . $delStmt->error);
        }
        $delStmt->close();

        $conn->commit();
        $_SESSION['success_message'] = "Course {$course_id} removed from minor requirements.";
        header("Location: edit_minor.php?id=" . $minor_id);
        exit;

    } else {
        throw new Exception("Unrecognized action.");
    }

} catch (Exception $e) {
    if (isset($conn) && $conn->in_transaction) {
        $conn->rollback();
    }
    $_SESSION['error_message'] = $e->getMessage();
    header("Location: majors_minors.php");
    exit;
}

