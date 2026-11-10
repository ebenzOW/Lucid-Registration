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

// Why: Ensure Security_Level is always available in session for permission checks
if (!isset($_SESSION["Security_Level"]) && isset($userID)) {
    require_once __DIR__ . '/../db_connect.php';
    $secStmt = $conn->prepare("SELECT Security_Level FROM ADMIN WHERE Admin_ID = ?");
    $secStmt->bind_param("i", $userID);
    $secStmt->execute();
    $secRes = $secStmt->get_result()->fetch_assoc();
    $secStmt->close();
    $_SESSION["Security_Level"] = (int)($secRes["Security_Level"] ?? 1);
}

// Why: SRS rules enforce that Security Level 1 administrators are read-only and cannot mutate users or course sections.
if (!function_exists('requireAdminWriteAccess')) {
    function requireAdminWriteAccess() {
        if ((int)($_SESSION['Security_Level'] ?? 1) < 2) {
            throw new Exception("Read-only administrators (Security Level 1) do not have permission to modify system records.");
        }
    }
}
?>
