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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password | Lucid University</title>
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
                            <h2 class="fw-bold text-dark mb-1">Forgot Password</h2>
                            <p class="text-secondary small">Enter your registered email to receive a password reset verification code.</p>
                        </div>

                        <?php if ($error): ?>
                            <div class="alert alert-danger py-2 small" role="alert">
                                <?= htmlspecialchars($error) ?>
                            </div>
                        <?php endif; ?>

                        <form action="forgot_password.php" method="POST">
                            <div class="mb-3">
                                <label for="user_email" class="form-label small fw-semibold">University Email Address</label>
                                <input type="email" name="user_email" id="user_email" class="form-control" placeholder="user@lucid.edu" required autofocus>
                            </div>

                            <div class="d-grid mt-4">
                                <button type="submit" class="btn btn-primary btn-lg shadow-sm">
                                    Send Reset Code
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