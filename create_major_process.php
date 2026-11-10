<?php
/**
 * Lucid University Course Registration System
 * Admin Portal - Process Creation of New Major (Generating Majors)
 */

require_once 'admin_guard.php';
require_once '../db_connect.php';

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: majors_minors.php");
    exit;
}

try {
    // 1. Enforce admin write permission (Security Level >= 2)
    requireAdminWriteAccess();

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

    // 2. Validate department existence
    $deptStmt = $conn->prepare("SELECT Department_Name FROM DEPARTMENT WHERE Department_ID = ?");
    $deptStmt->bind_param("i", $department_id);
    $deptStmt->execute();
    $deptRow = $deptStmt->get_result()->fetch_assoc();
    $deptStmt->close();

    if (!$deptRow) {
        throw new Exception("The selected department does not exist in the database.");
    }

    // 3. Check Major_ID uniqueness
    $idStmt = $conn->prepare("SELECT Major_ID FROM MAJORS WHERE Major_ID = ?");
    $idStmt->bind_param("i", $major_id);
    $idStmt->execute();
    if ($idStmt->get_result()->num_rows > 0) {
        $idStmt->close();
        throw new Exception("A major program with ID {$major_id} already exists.");
    }
    $idStmt->close();

    // 4. Check (Department_ID, Major_Name) uniqueness
    $nameStmt = $conn->prepare("SELECT Major_ID FROM MAJORS WHERE Department_ID = ? AND Major_Name = ?");
    $nameStmt->bind_param("is", $department_id, $major_name);
    $nameStmt->execute();
    if ($nameStmt->get_result()->num_rows > 0) {
        $nameStmt->close();
        throw new Exception("A major named '{$major_name}' already exists in the {$deptRow['Department_Name']} department.");
    }
    $nameStmt->close();

    // 5. Insert into MAJORS
    $insStmt = $conn->prepare("
        INSERT INTO MAJORS (Major_ID, Department_ID, Major_Name, No_Credits_Required)
        VALUES (?, ?, ?, ?)
    ");
    $insStmt->bind_param("iisi", $major_id, $department_id, $major_name, $no_credits_required);
    if (!$insStmt->execute()) {
        throw new Exception("Database insert failed: " . $insStmt->error);
    }
    $insStmt->close();

    $_SESSION['success_message'] = "Major program '{$major_name}' (ID: {$major_id}) successfully created!";
    header("Location: majors_minors.php");
    exit;

} catch (Exception $e) {
    $_SESSION['error_message'] = $e->getMessage();
    header("Location: create_major.php");
    exit;
}
?>
