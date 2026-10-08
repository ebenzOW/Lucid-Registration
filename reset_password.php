<?php
session_start();

// Grab the saved email if it exists
$savedEmail = $_SESSION['reset_email'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Lucid University - Reset Password</title>
</head>
<body>
    <h1>Lucid University</h1>
    <h2>Enter Verification Code</h2>

    <?php if (isset($_GET['error'])): ?>
        <p style="color: red;">
            <?php 
                if ($_GET['error'] == 'empty') echo "Please fill in all fields.";
                if ($_GET['error'] == 'invalid_code') echo "Invalid Code.";
                if ($_GET['error'] == 'nomatch') echo "Passwords Must Match!";
                if ($_GET['error'] == 'locked') echo "Your Account is Now Locked, a password reset code has been sent to your email.<br>Please reset your password to unlock your account.";
            ?>
        </p>
        <?php endif; ?>

    <form action="reset_password_process.php" method="POST">
        <!-- Pre-filled email from login_process -->
        <label>Email:</label><br>
        <input type="email" name="user_email" value="<?= htmlspecialchars($savedEmail) ?>" required readonly><br><br>

        <label>6-Digit Code:</label><br>
        <input type="text" name="reset_code" maxlength="6" pattern="\d{6}" required placeholder="123456"><br><br>

        <label>New Password:</label><br>
        <input type="password" name="new_password" required><br><br>

        <label>Confirm Password:</label><br>
        <input type="password" name="confirm_password" required><br><br>

        <button type="submit">Submit New Password</button>
    </form>
</body>
</html>