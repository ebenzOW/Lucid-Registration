<?php
session_start();
// If the user is not logged in, boot them back to the login page
if (!isset($_SESSION["User_ID"])) {
    header("Location: ../login.php");
    exit;
}
if (!($_SESSION["User_Type"] === "Admin")) {
    header("Location: ../login.php");
    exit;
}
$userID = $_SESSION["User_ID"];
?>
