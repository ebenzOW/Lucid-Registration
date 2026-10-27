<?php
session_start();

// Grab the saved email if it exists
$savedEmail = $_SESSION['reset_email'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password | Lucid University</title>
    <!-- Minimal Clean Bootstrap 5.3 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light d-flex flex-column min-vh-100">

    <!-- Unified Public Top Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark shadow-sm">
        <div class="container">
            <a class="navbar-brand fw-bold" href="index.html">Lucid University</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#publicNav" aria-controls="publicNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="publicNav">
                <ul class="navbar-nav ms-auto mb-2 mb-lg-0">
                    <li class="nav-item">
                        <a class="nav-link" href="index.html">Home</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="login.php">Login</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Centered Form Card -->
    <main class="container my-auto py-5">
        <div class="row justify-content-center">
            <div class="col-sm-10 col-md-8 col-lg-5">
                <div class="card shadow border-0 rounded-3">
                    <div class="card-body p-4 p-md-5">
                        <div class="text-center mb-4">
                            <h2 class="fw-bold text-dark mb-1">Set New Password</h2>
                            <p class="text-secondary small">Enter the 6-digit verification code sent to your email.</p>
                        </div>

                        <?php if (isset($_GET['error'])): ?>
                            <div class="alert alert-danger py-2 small" role="alert">
                                <?php 
                                    if ($_GET['error'] == 'empty') echo "Please fill in all fields.";
                                    if ($_GET['error'] == 'invalid_code') echo "Invalid Code.";
                                    if ($_GET['error'] == 'nomatch') echo "Passwords Must Match!";
                                    if ($_GET['error'] == 'locked') echo "Your Account is Now Locked, a password reset code has been sent to your email.<br>Please reset your password to unlock your account.";
                                    if ($_GET['error'] === 'policy') echo "Password must be 5-10 characters, contain at least 1 uppercase letter, and at least 1 symbol.";
                                ?>
                            </div>
                        <?php endif; ?>

                        <form action="reset_password_process.php" method="POST">
                            <div class="mb-3">
                                <label for="user_email" class="form-label small fw-semibold">Email Address</label>
                                <input type="email" name="user_email" id="user_email" class="form-control" value="<?= htmlspecialchars($savedEmail) ?>" required readonly>
                            </div>

                            <div class="mb-3">
                                <label for="reset_code" class="form-label small fw-semibold">6-Digit Code</label>
                                <input type="text" name="reset_code" id="reset_code" class="form-control" maxlength="6" pattern="\d{6}" required placeholder="123456" autofocus>
                            </div>

                            <div class="mb-3">
                                <label for="new_password" class="form-label small fw-semibold">New Password</label>
                                <input type="password" name="new_password" id="new_password" class="form-control" minlength="5" maxlength="10" required>
                                <div class="form-text small">Must be 5–10 characters, include at least 1 uppercase letter and 1 symbol.</div>
                            </div>

                            <div class="mb-3">
                                <label for="confirm_password" class="form-label small fw-semibold">Confirm New Password</label>
                                <input type="password" name="confirm_password" id="confirm_password" class="form-control" minlength="5" maxlength="10" required>
                            </div>

                            <div class="d-grid mt-4">
                                <button type="submit" class="btn btn-primary btn-lg shadow-sm">
                                    Submit New Password
                                </button>
                            </div>
                            <div class="text-center mt-3">
                                <a href="login.php" class="small text-decoration-none">&larr; Return to Login</a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- Footer -->
    <footer class="bg-white border-top py-3 mt-auto">
        <div class="container text-center text-muted small">
            &copy; <?= date('Y') ?> Lucid University. All rights reserved.
        </div>
    </footer>

    <!-- Bootstrap 5.3 JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>