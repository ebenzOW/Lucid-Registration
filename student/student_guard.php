<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// If the user is not logged in, boot them back to the login page
if (!isset($_SESSION["User_ID"])) {
    header("Location: ../login.php");
    exit;
}
if (!($_SESSION["User_Type"] === "Student")) {
    header("Location: ../login.php");
    exit;
}
$userID = $_SESSION["User_ID"];

// Why: Ensure user name sub-keys are always populated in session for dashboard greetings
require_once __DIR__ . '/../db_connect.php';
require_once __DIR__ . '/../includes/auth_helpers.php';
ensureSessionUserLoaded($conn, $userID);

