<?php
require_once 'admin_guard.php';
require_once '../db_connect.php';
require_once '../profile_hover_card.php';

$admin_id = (int)$userID;

$fullName = $_SESSION["User"]["User_FName"] ?? "Administrator";
if (!empty($_SESSION["User"]["User_MName"])) {
    $fullName .= " " . $_SESSION["User"]["User_MName"];
}
if (!empty($_SESSION["User"]["User_LName"])) {
    $fullName .= " " . $_SESSION["User"]["User_LName"];
}
$fullName = strip_tags($fullName);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard | Lucid University</title>
    <!-- Minimal Clean Bootstrap 5.3 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        <?= getProfileHoverCardStyles() ?>
    </style>
</head>
<body class="bg-light">

    <!-- Unified Admin Top Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark mb-4 shadow-sm">
        <div class="container">
            <a class="navbar-brand fw-bold" href="dashboard.php">Lucid University <span class="badge bg-danger ms-1">Admin</span></a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#adminNav" aria-controls="adminNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="adminNav">
                <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                    <li class="nav-item">
                        <a class="nav-link active" aria-current="page" href="dashboard.php">Dashboard</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="create_user.php">Create User</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="view_users.php">View Users</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="create_course_section.php">Create Course Section</a>
                    </li>
                </ul>
                <div class="d-flex align-items-center gap-3">
                    <?= getUserNavbarProfileHtml($conn, $admin_id, 'Admin') ?>
                    <form action="../logout.php" method="POST" class="m-0">
                        <button type="submit" class="btn btn-outline-light btn-sm">Log Out</button>
                    </form>
                </div>
            </div>
        </div>
    </nav>

    <div class="container mb-5">

        <!-- Welcome Banner Card -->
        <div class="card shadow-sm mb-4 border-0">
            <div class="card-body p-4 bg-white rounded-3">
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
                    <div>
                        <h2 class="fw-bold mb-1">Administrator Dashboard</h2>
                        <div class="text-secondary">
                            Welcome back, <?= getUserNavbarProfileHtml($conn, $admin_id, 'Admin') ?>
                        </div>
                    </div>
                    <div>
                        <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-3 py-2 fs-6">
                            Security Level: <?= htmlspecialchars((string)($_SESSION["Security_Level"] ?? '1')) ?>
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Admin Portal Actions Grid -->
        <h4 class="fw-bold text-dark mb-3">Administrative Services</h4>
        <div class="row g-3">
            <div class="col-md-4">
                <div class="card h-100 shadow-sm border-0">
                    <div class="card-body p-4 d-flex flex-column">
                        <h5 class="fw-bold text-dark mb-2">Create New User</h5>
                        <p class="text-secondary small flex-grow-1">
                            Provision new student, faculty, and administrator accounts with profile data, system credentials, and department assignments.
                        </p>
                        <a href="create_user.php" class="btn btn-primary btn-sm mt-2">
                            Create User &rarr;
                        </a>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card h-100 shadow-sm border-0">
                    <div class="card-body p-4 d-flex flex-column">
                        <h5 class="fw-bold text-dark mb-2">View All Users</h5>
                        <p class="text-secondary small flex-grow-1">
                            Browse, inspect, and review all active registered university accounts, personal contact details, and role classifications.
                        </p>
                        <a href="view_users.php" class="btn btn-outline-secondary btn-sm mt-2">
                            View Directory &rarr;
                        </a>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card h-100 shadow-sm border-0">
                    <div class="card-body p-4 d-flex flex-column">
                        <h5 class="fw-bold text-dark mb-2">Create Course Section</h5>
                        <p class="text-secondary small flex-grow-1">
                            Schedule new course sections for future semesters with instructor teaching load validations, time slots, room assignments, and seat capacities.
                        </p>
                        <a href="create_course_section.php" class="btn btn-success btn-sm mt-2">
                            Create Section &rarr;
                        </a>
                    </div>
                </div>
            </div>
        </div>

    </div>

    <!-- Bootstrap 5.3 JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
