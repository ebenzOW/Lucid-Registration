<?php
session_start();
require_once 'db_connect.php';
require_once 'email_functions.php';

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['user_email'] ?? '');

    if (!empty($email)) {
        // Send reset code to email and save reset email in $_Session Cookies so that can be used to verify info
        sendPasswordResetCode($conn, $email);
    
        $conn->close();
        header("Location: reset_password.php");
        exit;
        
    } else {
        $error = "Please enter an email address.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Forgot Password</title>
</head>
<body>
    <h2>Forgot Password</h2>

    <form action="forgot_password.php" method="POST">
        <label>Enter your registered email:</label><br>
        <?php if ($error): ?><p style="color: red;"><?= htmlspecialchars($error) ?></p><?php endif; ?>
        <input type="email" name="user_email" required><br><br>
        <button type="submit">Send Reset Code</button>
    </form>
</body>
</html>