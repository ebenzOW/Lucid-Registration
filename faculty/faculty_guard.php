<?php
session_start();
// If the user is not logged in, boot them back to the login page
if (!isset($_SESSION["User_ID"])) {
    header("Location: ../login.php");
    exit;
}
if (!($_SESSION["User_Type"] === "Faculty")) {
    header("Location: ../login.php");
    exit;
}
$userID = $_SESSION["User_ID"];

// Why: Ensure user name sub-keys are always populated in session for dashboard greetings
if (!isset($_SESSION["User"]["User_FName"]) && isset($userID)) {
    require_once __DIR__ . '/../db_connect.php';
    $uStmt = $conn->prepare("SELECT User_FName, User_MName, User_LName FROM `USER` WHERE User_ID = ?");
    $uStmt->bind_param("i", $userID);
    $uStmt->execute();
    $uRow = $uStmt->get_result()->fetch_assoc();
    $uStmt->close();
    if ($uRow) {
        $_SESSION["User"] = $uRow;
    }
}

