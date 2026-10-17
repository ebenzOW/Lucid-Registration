<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Lucid University - Login</title>
</head>

<body>
    <h1>Lucid University Login</h1>

    <h2>Login</h2>

    <!-- Error Message -->
    <?php if (isset($_GET['error'])): ?>
        <p style="color: red;">
            <?php
            if ($_GET['error'] == 'empty') echo "Please fill in all fields.";
            if ($_GET['error'] == 'invalid') echo "Invalid email or password.";
            if ($_GET['error'] == 'locked') echo "That Account is locked due to too many failed login attempts, password reset email has been sent.";
            ?>
        </p>
    <?php endif ?>
    <?php if (isset($_GET['reset'])): ?>
        <p style="color: green;">
            <?php echo "Reset Successful."; ?>

        </p>

    <?php endif; ?>

    <form action="login_process.php" method="POST">
        <label>Email:</label>
        <input type="email" name="user_email" required>
        <br><br>

        <label>Password:</label>
        <input type="password" name="user_password" required>
        <br><br>


        <button type="submit">Log In</button>
    </form>


    <a href=forgot_password.php>Forgot Password?</a>
</body>

</html>