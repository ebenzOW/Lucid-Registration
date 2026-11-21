<?php
require_once __DIR__ . '/includes/department_helpers.php';

$departments = [];
$loadError = false;

try {
    require_once __DIR__ . '/db_connect.php';
    $departments = getAllDepartments($conn);
} catch (Throwable $e) {
    error_log('Department directory error: ' . $e->getMessage());
    http_response_code(503);
    $loadError = true;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Departments - Lucid University</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light d-flex flex-column min-vh-100">

    <!-- Match the homepage's public navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark shadow-sm">
        <div class="container">
            <a class="navbar-brand fw-bold" href="index.html">Lucid University</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#publicNav"
                aria-controls="publicNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="publicNav">
                <ul class="navbar-nav ms-auto mb-2 mb-lg-0">
                    <li class="nav-item">
                        <a class="nav-link" href="index.html">Home</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" aria-current="page" href="view_departments.php">Departments</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link btn btn-outline-light btn-sm ms-lg-2 px-3 text-white" href="login.php">Login</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <main class="container py-5 flex-grow-1">
        <div class="mb-4">
            <h1 class="display-6 fw-bold text-dark mb-2">Academic Departments</h1>
            <p class="text-secondary mb-0">Explore Lucid University's departments and the majors and minors they offer.</p>
        </div>

        <?php if ($loadError): ?>
            <div class="alert alert-danger" role="alert">
                The department directory is temporarily unavailable. Please try again later.
            </div>
        <?php elseif (empty($departments)): ?>
            <div class="alert alert-info" role="status">There are no departments listed at this time.</div>
        <?php else: ?>
            <div class="row g-4">
                <?php foreach ($departments as $department): ?>
                    <div class="col-md-6 col-lg-4">
                        <div class="card h-100 shadow-sm border-0 position-relative">
                            <div class="card-body p-4">
                                <h2 class="h5 fw-semibold mb-3">
                                    <a class="stretched-link text-decoration-none link-dark"
                                       href="department_details.php?department_id=<?= (int) $department['Department_ID'] ?>">
                                        <?= escapeHtml($department['Department_Name']) ?>
                                    </a>
                                </h2>
                                <p class="text-secondary mb-0">View majors and minors</p>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </main>

    <footer class="bg-white border-top py-3 mt-auto">
        <div class="container text-center text-muted small">
            &copy; <?= date('Y') ?> Lucid University. All rights reserved.
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
