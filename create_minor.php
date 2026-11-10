<?php
/**
 * Lucid University Course Registration System
 * Admin Portal - Create New Academic Minor (Use Case: Generating Minors)
 */

require_once 'admin_guard.php';
require_once '../db_connect.php';
require_once '../profile_hover_card.php';

$admin_id = (int)$userID;

$successMessage = $_SESSION["success_message"] ?? "";
$errorMessage   = $_SESSION["error_message"] ?? "";
unset($_SESSION["success_message"], $_SESSION["error_message"]);

// Fetch Departments
$deptStmt = $conn->prepare("SELECT Department_ID, Department_Name FROM DEPARTMENT ORDER BY Department_Name ASC");
$deptStmt->execute();
$departments = $deptStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$deptStmt->close();

// Suggest next Minor ID
$maxStmt = $conn->query("SELECT MAX(Minor_ID) AS MaxID FROM MINORS");
$maxRow = $maxStmt ? $maxStmt->fetch_assoc() : null;
$suggestedMinorID = ($maxRow && $maxRow['MaxID']) ? ((int)$maxRow['MaxID'] + 1) : 1;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Academic Minor | Lucid University Admin</title>
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
                    <li class="nav-item"><a class="nav-link" href="dashboard.php">Dashboard</a></li>
                    <li class="nav-item"><a class="nav-link" href="create_user.php">Create User</a></li>
                    <li class="nav-item"><a class="nav-link" href="view_users.php">View Users</a></li>
                    <li class="nav-item"><a class="nav-link" href="create_course_section.php">Create Course Section</a></li>
                    <li class="nav-item"><a class="nav-link" href="semesters.php">Semesters</a></li>
                    <li class="nav-item"><a class="nav-link active" aria-current="page" href="majors_minors.php">Majors & Minors</a></li>
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

    <div class="container mb-5" style="max-width: 760px;">

        <!-- Breadcrumb / Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <a href="majors_minors.php" class="text-decoration-none text-secondary small">&larr; Back to Majors & Minors</a>
                <h2 class="fw-bold mb-1 mt-1">Create New Academic Minor</h2>
                <div class="text-secondary small">Provision a secondary academic concentration and designate credit benchmarks.</div>
            </div>
            <div>
                <span class="badge bg-secondary border px-3 py-2">
                    Security Level: <?= htmlspecialchars((string)($_SESSION["Security_Level"] ?? '1')) ?>
                </span>
            </div>
        </div>

        <!-- Flash Alerts -->
        <?php if (!empty($successMessage)): ?>
            <div class="alert alert-success alert-dismissible fade show shadow-sm" role="alert">
                <strong>Success!</strong> <?= htmlspecialchars($successMessage) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <?php if (!empty($errorMessage)): ?>
            <div class="alert alert-danger alert-dismissible fade show shadow-sm" role="alert">
                <strong>Error:</strong> <?= htmlspecialchars($errorMessage) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <!-- Create Minor Form -->
        <div class="card shadow-sm border-0 rounded-3">
            <div class="card-body p-4 p-md-5">
                <form action="create_minor_process.php" method="POST">
                    
                    <div class="mb-3">
                        <label for="minor_id" class="form-label fw-semibold">Minor ID</label>
                        <input type="number" class="form-control font-monospace" id="minor_id" name="minor_id" 
                               value="<?= htmlspecialchars((string)$suggestedMinorID) ?>" required min="1" max="99999">
                        <div class="form-text small">Unique numeric primary key for this academic minor track.</div>
                    </div>

                    <div class="mb-3">
                        <label for="minor_name" class="form-label fw-semibold">Minor Name</label>
                        <input type="text" class="form-control" id="minor_name" name="minor_name" 
                               placeholder="e.g. Computer Science Minor or Creative Writing Minor" required maxlength="100">
                    </div>

                    <div class="mb-3">
                        <label for="department_id" class="form-label fw-semibold">Host Academic Department</label>
                        <select class="form-select" id="department_id" name="department_id" required>
                            <option value="">Select Department...</option>
                            <?php foreach ($departments as $dept): ?>
                                <option value="<?= (int)$dept['Department_ID'] ?>">
                                    <?= htmlspecialchars($dept['Department_Name']) ?> (ID: <?= (int)$dept['Department_ID'] ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-4">
                        <label for="no_credits_required" class="form-label fw-semibold">Required Minor Credits</label>
                        <input type="number" class="form-control font-monospace" id="no_credits_required" name="no_credits_required" 
                               value="20" required min="1" max="50">
                        <div class="form-text small">Total credits required to earn this minor (typically 15 to 20 credits).</div>
                    </div>

                    <div class="d-flex justify-content-between align-items-center pt-3 border-top">
                        <a href="majors_minors.php" class="btn btn-outline-secondary">Cancel</a>
                        <button type="submit" class="btn btn-success px-4 fw-semibold">
                            Create Minor &rarr;
                        </button>
                    </div>

                </form>
            </div>
        </div>

    </div>

    <!-- Bootstrap 5.3 JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
