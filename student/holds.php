<?php
/**
 * Lucid University Course Registration System
 * Student Account Holds Portal
 *
 * View active account holds, restriction types, placement dates,
 * and resolution guidelines.
 */

require_once 'student_guard.php';
$student_id = (int)$userID;

require_once '../db_connect.php';
require_once 'academic_helpers.php';
require_once '../profile_hover_card.php';

// 1. Fetch Student Profile
$profile = getStudentProfile($conn, $student_id);
if (!$profile) {
    die("Student profile record not found.");
}
$student_full_name = $profile['full_name'];

// 2. Query Student Active Holds
$holdStmt = $conn->prepare("
    SELECT sh.Hold_ID, h.Hold_Type, sh.Hold_Date
    FROM STUDENT_HOLDS sh
    JOIN HOLDS h ON sh.Hold_ID = h.Hold_ID
    WHERE sh.Student_ID = ?
    ORDER BY sh.Hold_Date DESC
");
$holdStmt->bind_param("i", $student_id);
$holdStmt->execute();
$holdRes = $holdStmt->get_result();
$holds = [];
while ($hRow = $holdRes->fetch_assoc()) {
    $holds[] = $hRow;
}
$holdStmt->close();

$has_active_holds = !empty($holds);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Account Holds | Lucid University</title>
    <!-- Minimal Clean Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

    <!-- Unified Student Top Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark mb-4">
        <div class="container">
            <a class="navbar-brand fw-bold" href="dashboard.php">Lucid University</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#studentNav" aria-controls="studentNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="studentNav">
                <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                    <li class="nav-item">
                        <a class="nav-link" href="dashboard.php">Dashboard</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="schedule.php">My Schedule</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="master_schedule.php">Master Schedule</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="register.php">Register / Add-Drop</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="transcript.php">Transcript</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="degree_audit.php">Degree Audit</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" aria-current="page" href="holds.php">Holds</a>
                    </li>
                </ul>
                <div class="d-flex align-items-center gap-3">
                    <span class="navbar-text text-white small d-none d-lg-inline">
                        Welcome, <?= getUserNavbarProfileHtml($conn, $student_id, 'Student') ?>
                    </span>
                    <form action="../logout.php" method="POST" class="m-0">
                        <button type="submit" class="btn btn-outline-light btn-sm">Log Out</button>
                    </form>
                </div>
            </div>
        </div>
    </nav>

    <div class="container mb-5">

        <!-- Page Header -->
        <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
            <div>
                <h2 class="fw-bold mb-1">Student Account Holds</h2>
                <div class="text-secondary">
                    Review active administrative, financial, or academic restrictions on your account.
                </div>
            </div>
            <div>
                <a href="dashboard.php" class="btn btn-outline-secondary btn-sm">
                    &larr; Return to Dashboard
                </a>
            </div>
        </div>

        <?php if (!$has_active_holds): ?>
            <!-- 0 Holds State -->
            <div class="alert alert-success border-2 shadow-sm p-4 mb-4" role="alert">
                <div class="d-flex align-items-center gap-3">
                    <div class="fs-1 text-success">&check;</div>
                    <div>
                        <h5 class="alert-heading fw-bold mb-1">No Active Holds</h5>
                        <p class="mb-0">Your student account currently has 0 active holds. You have full registration privileges and access to official university services.</p>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <!-- Active Holds Warning Alert -->
            <div class="alert alert-danger border-2 shadow-sm p-3 mb-4" role="alert">
                <h5 class="alert-heading fw-bold mb-1">Notice: Active Registration Restriction</h5>
                <p class="mb-0">
                    Your account has <strong><?= count($holds) ?></strong> active hold(s). Active holds prevent course registration and add/drop actions. 
                    Per university policy, <strong>only an Administrator or the designated department can remove account holds</strong>.
                </p>
            </div>

            <!-- Holds Table Card -->
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                    <h5 class="mb-0 fw-bold">Active Account Holds</h5>
                    <span class="badge bg-danger"><?= count($holds) ?> Active</span>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover table-striped mb-0 align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>Hold ID</th>
                                    <th>Hold Type</th>
                                    <th>Date Placed</th>
                                    <th>Resolution Instructions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($holds as $h): ?>
                                    <tr>
                                        <td><strong>#<?= (int)$h['Hold_ID'] ?></strong></td>
                                        <td>
                                            <span class="badge bg-danger-subtle text-danger border border-danger-subtle px-2 py-1 fs-6">
                                                <?= htmlspecialchars($h['Hold_Type'], ENT_QUOTES, 'UTF-8') ?> Hold
                                            </span>
                                        </td>
                                        <td><?= htmlspecialchars($h['Hold_Date'], ENT_QUOTES, 'UTF-8') ?></td>
                                        <td class="text-secondary small">
                                            <?php if (stripos($h['Hold_Type'], 'Bursar') !== false || stripos($h['Hold_Type'], 'Financial') !== false): ?>
                                                Contact the Office of the Bursar to settle outstanding balances.
                                            <?php elseif (stripos($h['Hold_Type'], 'Health') !== false || stripos($h['Hold_Type'], 'Immunization') !== false): ?>
                                                Submit required health/immunization documentation to Student Health Services.
                                            <?php elseif (stripos($h['Hold_Type'], 'Academic') !== false || stripos($h['Hold_Type'], 'Advising') !== false): ?>
                                                Meet with your assigned academic advisor to complete advising clearance.
                                            <?php else: ?>
                                                Contact the University Registrar or an Administrator to resolve this hold.
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="card-footer bg-white py-3 text-muted small">
                    <i class="bi bi-info-circle"></i> Once the issuing department clears the requirement, an Administrator will release the hold from your record.
                </div>
            </div>
        <?php endif; ?>

    </div>

    <!-- Bootstrap 5 JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
