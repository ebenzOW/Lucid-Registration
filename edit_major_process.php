<?php
/**
 * Lucid University Course Registration System
 * Admin Portal - Process Major Updates & Course Requirements
 */

require_once 'admin_guard.php';
require_once '../db_connect.php';

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: majors_minors.php");
    exit;
}

$major_id = isset($_POST['major_id']) ? (int)$_POST['major_id'] : 0;
$action   = trim($_POST['action'] ?? '');

if ($major_id <= 0) {
    $_SESSION['error_message'] = "Invalid Major ID specified.";
    header("Location: majors_minors.php");
    exit;
}

try {
    // 1. Enforce admin write permission (Security Level >= 2)
    requireAdminWriteAccess();

    if ($action === 'update_metadata') {
        $major_name          = trim($_POST['major_name'] ?? '');
        $department_id       = isset($_POST['department_id']) ? (int)$_POST['department_id'] : 0;
        $no_credits_required = isset($_POST['no_credits_required']) ? (int)$_POST['no_credits_required'] : 0;

        if (empty($major_name)) {
            throw new Exception("Major program name cannot be empty.");
        }
        if ($department_id <= 0) {
            throw new Exception("Please select a valid academic department.");
        }
        if ($no_credits_required <= 0) {
            throw new Exception("Required credits must be greater than zero.");
        }

        // Check duplicate name within department for other majors
        $dupStmt = $conn->prepare("SELECT Major_ID FROM MAJORS WHERE Department_ID = ? AND Major_Name = ? AND Major_ID <> ?");
        $dupStmt->bind_param("isi", $department_id, $major_name, $major_id);
        $dupStmt->execute();
        if ($dupStmt->get_result()->num_rows > 0) {
            $dupStmt->close();
            throw new Exception("A major named '{$major_name}' already exists in that department.");
        }
        $dupStmt->close();

        // Update MAJORS
        $updStmt = $conn->prepare("
            UPDATE MAJORS 
            SET Major_Name = ?, Department_ID = ?, No_Credits_Required = ?
            WHERE Major_ID = ?
        ");
        $updStmt->bind_param("siii", $major_name, $department_id, $no_credits_required, $major_id);
        if (!$updStmt->execute()) {
            throw new Exception("Failed to update major attributes: " . $updStmt->error);
        }
        $updStmt->close();

        $_SESSION['success_message'] = "Major '{$major_name}' attributes successfully updated!";
        header("Location: edit_major.php?id=" . $major_id);
        exit;

    } elseif ($action === 'add_course') {
        $course_id = trim($_POST['course_id'] ?? '');
        $min_grade = strtoupper(trim($_POST['min_grade'] ?? 'C'));

        if (empty($course_id)) {
            throw new Exception("Please select a valid course to require.");
        }
        if (!in_array($min_grade, ['A', 'B', 'C', 'D'], true)) {
            $min_grade = 'C';
        }

        // Verify course exists
        $cStmt = $conn->prepare("SELECT Course_ID FROM COURSES WHERE Course_ID = ?");
        $cStmt->bind_param("s", $course_id);
        $cStmt->execute();
        if ($cStmt->get_result()->num_rows === 0) {
            $cStmt->close();
            throw new Exception("Course {$course_id} does not exist in the catalog.");
        }
        $cStmt->close();

        // Insert requirement
        $insStmt = $conn->prepare("
            INSERT INTO MAJOR_REQUIREMENTS (Major_ID, Course_ID, Min_Grade)
            VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE Min_Grade = VALUES(Min_Grade)
        ");
        $insStmt->bind_param("iss", $major_id, $course_id, $min_grade);
        if (!$insStmt->execute()) {
            throw new Exception("Failed to add course requirement: " . $insStmt->error);
        }
        $insStmt->close();

        $_SESSION['success_message'] = "Course {$course_id} added to major requirements with minimum grade {$min_grade}.";
        header("Location: edit_major.php?id=" . $major_id);
        exit;

    } elseif ($action === 'remove_course') {
        $course_id = trim($_POST['course_id'] ?? '');

        if (empty($course_id)) {
            throw new Exception("Course ID cannot be empty.");
        }

        $delStmt = $conn->prepare("DELETE FROM MAJOR_REQUIREMENTS WHERE Major_ID = ? AND Course_ID = ?");
        $delStmt->bind_param("is", $major_id, $course_id);
        if (!$delStmt->execute()) {
            throw new Exception("Failed to delete course requirement: " . $delStmt->error);
        }
        $delStmt->close();

        $_SESSION['success_message'] = "Course {$course_id} removed from major requirements.";
        header("Location: edit_major.php?id=" . $major_id);
        exit;

    } else {
        throw new Exception("Unknown action request.");
    }

} catch (Exception $e) {
    $_SESSION['error_message'] = $e->getMessage();
    header("Location: edit_major.php?id=" . $major_id);
    exit;
}
?>
