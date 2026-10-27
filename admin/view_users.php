<?php
require_once 'admin_guard.php';
require_once '../db_connect.php';
require_once '../profile_hover_card.php';

$admin_id = (int)$userID;

$sql = "
    SELECT
        User_ID,
        User_Type,
        User_FName,
        User_MName,
        User_LName,
        User_Gender,
        User_DOB,
        User_PhoneNo,
        Street_No,
        Street_Name,
        City,
        State,
        Country,
        Zip_Code
    FROM `USER`
    ORDER BY User_ID ASC
";

$result = $conn->query($sql);
if (!$result) {
    die("Failed to retrieve users.");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>All Users Directory | Lucid University Admin</title>
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
                        <a class="nav-link" href="dashboard.php">Dashboard</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="create_user.php">Create User</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" aria-current="page" href="view_users.php">View Users</a>
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

    <div class="container-fluid px-lg-5 mb-5">

        <!-- Page Header -->
        <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
            <div>
                <h2 class="fw-bold mb-1">University User Directory</h2>
                <div class="text-secondary">
                    Complete registry of active students, faculty, and administrative accounts.
                </div>
            </div>
            <div class="d-flex gap-2">
                <a href="create_user.php" class="btn btn-primary btn-sm">+ Create New User</a>
                <a href="dashboard.php" class="btn btn-outline-secondary btn-sm">&larr; Back to Dashboard</a>
            </div>
        </div>

        <!-- Users Table Card -->
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <h5 class="mb-0 fw-bold">Registered Users</h5>
                <span class="badge bg-secondary"><?= $result->num_rows ?> Total Accounts</span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover table-striped mb-0 align-middle small">
                        <thead class="table-light">
                            <tr>
                                <th>User ID</th>
                                <th>Role</th>
                                <th>Full Name</th>
                                <th>Gender</th>
                                <th>Date of Birth</th>
                                <th>Phone</th>
                                <th>Street Address</th>
                                <th>City, State ZIP</th>
                                <th>Country</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($user = $result->fetch_assoc()): 
                                $userType = $user["User_Type"];
                                $typeBadgeClass = 'bg-secondary';
                                if ($userType === 'Admin') $typeBadgeClass = 'bg-danger';
                                elseif ($userType === 'Faculty') $typeBadgeClass = 'bg-info text-dark';
                                elseif ($userType === 'Student') $typeBadgeClass = 'bg-success';
                            ?>
                                <tr>
                                    <td><strong><?= (int)$user["User_ID"] ?></strong></td>
                                    <td>
                                        <span class="badge <?= $typeBadgeClass ?>">
                                            <?= ($userType === "Admin") ? "Administrator" : htmlspecialchars($userType, ENT_QUOTES, 'UTF-8') ?>
                                        </span>
                                    </td>
                                    <td class="fw-semibold">
                                        <?= htmlspecialchars(trim($user["User_FName"] . ' ' . ($user["User_MName"] ? $user["User_MName"] . ' ' : '') . $user["User_LName"]), ENT_QUOTES, 'UTF-8') ?>
                                    </td>
                                    <td><?= htmlspecialchars($user["User_Gender"] ?? "-", ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><?= htmlspecialchars($user["User_DOB"] ?? "-", ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><?= htmlspecialchars($user["User_PhoneNo"] ?? "-", ENT_QUOTES, 'UTF-8') ?></td>
                                    <td>
                                        <?= htmlspecialchars(trim(($user["Street_No"] ?? '') . ' ' . ($user["Street_Name"] ?? '')), ENT_QUOTES, 'UTF-8') ?: '-' ?>
                                    </td>
                                    <td>
                                        <?= htmlspecialchars(trim(($user["City"] ?? '') . ($user["State"] ? ', ' . $user["State"] : '') . ' ' . ($user["Zip_Code"] ?? '')), ENT_QUOTES, 'UTF-8') ?: '-' ?>
                                    </td>
                                    <td><?= htmlspecialchars($user["Country"] ?? "-", ENT_QUOTES, 'UTF-8') ?></td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </div>

    <!-- Bootstrap 5.3 JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
