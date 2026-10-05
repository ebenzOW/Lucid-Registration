<?php
session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    //Clears all session variables
    $_SESSION = [];
    session_destroy();
}

header("Location: login.php");
exit;
?>