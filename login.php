<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lucid University - Login</title>
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
                        <a class="nav-link active" aria-current="page" href="login.php">Login</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Centered Login Card -->
    <main class="container my-auto py-5">
        <div class="row justify-content-center">
            <div class="col-sm-10 col-md-8 col-lg-5">
                <div class="card shadow border-0 rounded-3">
                    <div class="card-body p-4 p-md-5">
                        <div class="text-center mb-4">
                            <h2 class="fw-bold text-dark mb-1">Account Login</h2>
                            <p class="text-secondary small">Sign in to your Lucid University account</p>
                        </div>

                        <!-- Error Messages -->
                        <?php if (isset($_GET['error'])): ?>
                            <div class="alert alert-danger py-2 small" role="alert">
                                <?php
                                if ($_GET['error'] == 'empty') echo "Please fill in all fields.";
                                if ($_GET['error'] == 'invalid') echo "Invalid email or password.";
                                if ($_GET['error'] == 'locked') echo "That Account is locked due to too many failed login attempts, password reset email has been sent.";
                                ?>
                            </div>
                        <?php endif; ?>

                        <!-- Password Reset Success Notification -->
                        <?php if (isset($_GET['reset']) && $_GET['reset'] == 'success'): ?>
                            <div class="alert alert-success py-2 small" role="alert">
                                Reset Successful. Please log in with your new password.
                            </div>
                        <?php elseif (isset($_GET['reset'])): ?>
                            <div class="alert alert-success py-2 small" role="alert">
                                Reset Successful.
                            </div>
                        <?php endif; ?>

                        <form action="login_process.php" method="POST">
                            <div class="mb-3">
                                <label for="user_email" class="form-label small fw-semibold">University Email</label>
                                <input type="email" name="user_email" id="user_email" class="form-control" placeholder="user@lucid.edu" required autofocus>
                            </div>

                            <div class="mb-3">
                                <div class="d-flex justify-content-between align-items-center">
                                    <label for="user_password" class="form-label small fw-semibold mb-0">Password</label>
                                    <a href="forgot_password.php" class="small text-decoration-none">Forgot Password?</a>
                                </div>
                                <input type="password" name="user_password" id="user_password" class="form-control mt-1" placeholder="Enter password" required>
                            </div>

                            <div class="d-grid mt-4">
                                <button type="submit" class="btn btn-primary btn-lg shadow-sm">
                                    Log In
                                </button>
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