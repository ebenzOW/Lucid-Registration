<?php
require_once 'admin_guard.php';
require_once '../db_connect.php';
require_once __DIR__ . '/../includes/user_helpers.php';
require_once __DIR__ . '/../includes/auth_helpers.php';

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: create_user.php");
    exit;
}

$inTransaction = false;

try {
    requireAdminWriteAccess();

    $required = [
        "user_type",
        "first_name",
        "last_name",
        "email",
        "password"
    ];

    foreach ($required as $field) {
        if (empty($_POST[$field])) {
            throw new Exception("Please fill in all required fields.");
        }
    }

    $userType = $_POST["user_type"];

    if (!filter_var($_POST["email"], FILTER_VALIDATE_EMAIL)) {
        throw new Exception("Invalid email address.");
    }

    $conn->begin_transaction();
    $inTransaction = true;

    $userID = generateUserID($conn);

    createUser($conn, $userID, $_POST);
    createLogin($conn, $userID, $_POST);

    if ($userType === "Admin") {
        createAdmin($conn, $userID, $_POST);
    }

    if ($userType === "Student") {
        createStudent($conn, $userID, $_POST);
    }

    if ($userType === "Faculty") {
        createFaculty($conn, $userID, $_POST);
    }

    if ($userType === "Stat-Dept") {
        createStatDept($conn, $userID, $_POST);
    }

    $conn->commit();
    $inTransaction = false;

    $_SESSION["success_message"] =
        "User successfully created. User ID: " . $userID;

    $sessionPackage = $_POST;
    unset($sessionPackage["password"]); 
    
    $_SESSION["new_user_data"] = $sessionPackage;
    $_SESSION["new_user_data"]["user_id"] = $userID;

} catch (Exception $e) {
    if ($inTransaction) {
        $conn->rollback();
        $inTransaction = false;
    }

    $_SESSION["error_message"] = $e->getMessage();
    unset($_SESSION["new_user_data"]);
}

header("Location: create_user.php");
exit;
