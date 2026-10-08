<?php
session_start();
require_once 'db_connect.php';

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email       = trim($_SESSION['reset_email'] ?? '');
    $code        = trim($_POST['reset_code'] ?? '');
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPass = $_POST['confirm_password'] ?? '';

    // Check for missing inputs
    if (empty($email) || empty($code) || empty($newPassword) || empty($confirmPass)) {
        header("Location: reset_password.php?error=empty");
        exit;
    }

    // Check password confirmation match
    if ($newPassword !== $confirmPass) {
        header("Location: reset_password.php?error=nomatch");
        exit;
    }

    $len       = strlen($newPassword);
    $hasUpper  = preg_match('/[A-Z]/', $newPassword);
    $hasSymbol = preg_match('/[^a-zA-Z0-9]/', $newPassword);

    if ($len < 5 || $len > 10 || !$hasUpper || !$hasSymbol) {
        header("Location: reset_password.php?error=policy");
        exit;
    }

    // Locate User_ID from LOGIN table
    $userStmt = $conn->prepare("SELECT User_ID FROM LOGIN WHERE User_Email = ?");
    $userStmt->bind_param("s", $email);
    $userStmt->execute();
    $userResult = $userStmt->get_result();
    $userStmt->close();

    if ($userResult->num_rows !== 1) {
        //User email not part of records
        header("Location: reset_password.php?error=invalid_code");
        exit;
    }

    $user = $userResult->fetch_assoc();
    $userId   = $user['User_ID'];

    //Retrieve reset code record from PASSWORD_RESET table
    $codeStmt = $conn->prepare("SELECT Reset_Code, Time_Code_Sent 
                                FROM PASSWORD_RESET 
                                WHERE User_ID = ?");
    $codeStmt->bind_param("i", $userId);
    $codeStmt->execute();
    $codeResult = $codeStmt->get_result();
    $codeStmt->close();

    if ($codeResult->num_rows !== 1) {
        //reset code for email does not exist
        header("Location: reset_password.php?error=invalid_code");
        exit;
    }

    $resetRecord = $codeResult->fetch_assoc();

    // Verify user inputted code against record in db
    if ((string)$resetRecord['Reset_Code'] !== (string)$code) {
        header("Location: reset_password.php?error=invalid_code");
        exit;
    }

    //Hash password 
    $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);

    //Update LOGIN table: set new hash, reset attempts, remove lockout
    $updateStmt = $conn->prepare("UPDATE LOGIN 
                                  SET User_Password = ?, 
                                      No_Login_Attempts = 0, 
                                      is_Locked = 0 
                                  WHERE User_ID = ?");
    $updateStmt->bind_param("si", $hashedPassword, $userId);
    $updateStmt->execute();
    $updateStmt->close();

    //Clean  PASSWORD_RESET table
    $deleteStmt = $conn->prepare("DELETE FROM PASSWORD_RESET WHERE User_ID = ?");
    $deleteStmt->bind_param("i", $userId);
    $deleteStmt->execute();
    $deleteStmt->close();

    // Clear all session variables and destroy the temporary session
    $_SESSION = [];
    session_destroy();

    //Redirect back to login with success flag
    header("Location: login.php?reset=success");
    exit;
}

// Fallback redirect if accessed outside POST
if (isset($conn) && $conn instanceof mysqli) {
    $conn->close();
}
header("Location: login.php");
exit;

?>