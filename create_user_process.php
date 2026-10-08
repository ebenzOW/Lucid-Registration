<?php
require_once 'admin_authentication.php';
require_once 'db_connect.php';
require_once 'user_functions.php';

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: create_user.php");
    exit;
}

$userType = $_POST["user_type"] ?? "";

try {

    if (!in_array($userType, ["Admin", "Student", "Faculty"], true)) {
        throw new Exception("Invalid user type.");
    }

    if (
        empty($_POST["first_name"]) ||
        empty($_POST["last_name"]) ||
        empty($_POST["email"]) ||
        empty($_POST["password"])
    ) {
        throw new Exception("Please fill in all required fields.");
    }

    if (!filter_var($_POST["email"], FILTER_VALIDATE_EMAIL)) {
        throw new Exception("Invalid email address.");
    }

    $conn->begin_transaction();

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

    $conn->commit();

    $_SESSION["success_message"] =
        "User successfully created. User ID: " . $userID;

    $sessionPackage = $_POST;
    unset($sessionPackage["password"]); 
    
    $_SESSION["new_user_data"] = $sessionPackage;
    $_SESSION["new_user_data"]["user_id"] = $userID;

} catch (Exception $e) {

    if (isset($conn) && $conn->in_transaction) {
        $conn->rollback();
    }

    $_SESSION["error_message"] = $e->getMessage();

    unset($_SESSION["new_user_data"]);
}

header("Location: create_user.php");
exit;