<?php
/**
 * Lucid University Course Registration System
 * Admin Portal - Process Creation of New Minor (Generating Minors)
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

    $minor_id            = isset($_POST['minor_id']) ? (int)$_POST['minor_id'] : 0;
    $minor_name          = trim($_POST['minor_name'] ?? '');
    $department_id       = isset($_POST['department_id']) ? (int)$_POST['department_id'] : 0;
    $no_credits_required = isset($_POST['no_credits_required']) ? (int)$_POST['no_credits_required'] : 0;

    if ($minor_id <= 0) {
        throw new Exception("Please specify a valid numeric Minor ID.");
    }
    if (empty($minor_name)) {
        throw new Exception("Minor Track Name cannot be blank.");
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

    // 3. Check Minor_ID uniqueness
    $idStmt = $conn->prepare("SELECT Minor_ID FROM MINORS WHERE Minor_ID = ?");
    $idStmt->bind_param("i", $minor_id);
    $idStmt->execute();
    if ($idStmt->get_result()->num_rows > 0) {
        $idStmt->close();
        throw new Exception("A minor track with ID {$minor_id} already exists.");
    }
    $idStmt->close();

    // 4. Check (Department_ID, Minor_Name) uniqueness
    $nameStmt = $conn->prepare("SELECT Minor_ID FROM MINORS WHERE Department_ID = ? AND Minor_Name = ?");
    $nameStmt->bind_param("is", $department_id, $minor_name);
    $nameStmt->execute();
    if ($nameStmt->get_result()->num_rows > 0) {
        $nameStmt->close();
        throw new Exception("A minor named '{$minor_name}' already exists in the {$deptRow['Department_Name']} department.");
    }
    $nameStmt->close();

    // 5. Insert into MINORS
    $insStmt = $conn->prepare("
        INSERT INTO MINORS (Minor_ID, Department_ID, Minor_Name, No_Credits_Required)
        VALUES (?, ?, ?, ?)
    ");
    $insStmt->bind_param("iisi", $minor_id, $department_id, $minor_name, $no_credits_required);
    if (!$insStmt->execute()) {
        throw new Exception("Database insert failed: " . $insStmt->error);
    }
    $insStmt->close();

    $_SESSION['success_message'] = "Minor track '{$minor_name}' (ID: {$minor_id}) successfully created!";
    header("Location: majors_minors.php");
    exit;

} catch (Exception $e) {
    $_SESSION['error_message'] = $e->getMessage();
    header("Location: create_minor.php");
    exit;
}
?>
