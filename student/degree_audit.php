<?php
require_once 'student_guard.php';
$student_id = (int)$userID;

require_once '../db_connect.php';
require_once 'academic_helpers.php';
require_once '../profile_hover_card.php';

// Fetch profile, transcript totals, and audit requirements
$profile = getStudentProfile($conn, $student_id);
if (!$profile) {
    die("Student record not found.");
}

$transcript = getStudentTranscriptData($conn, $student_id);
$audit = getDegreeAuditData($conn, $student_id);

$student_name         = $profile['full_name'];
$student_type         = $profile['Student_Type'] ?? 'Undergraduate';
$total_credits_earned = $transcript['total_credits'];

$primaryMajor   = $audit['primary_major'] ?? null;
$secondaryMajor = $audit['secondary_major'] ?? null;
$minor          = $audit['minor'] ?? null;

$creditsRequired = $primaryMajor ? (int)$primaryMajor['No_Credits_Required'] : 120;
// Calculate progress percentage toward major requirements
$progressPct = ($creditsRequired > 0) ? min(100, round(($total_credits_earned / $creditsRequired) * 100)) : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Degree Audit - Lucid University</title>
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
                        <a class="nav-link active" aria-current="page" href="degree_audit.php">Degree Audit</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="holds.php">Holds</a>
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
        <h2 class="mb-3">Academic Degree Audit</h2>

        <!-- Program Overview Card -->
        <div class="card mb-4">
            <div class="card-header">
                <strong>Degree Progress Overview</strong>
            </div>
            <div class="card-body">
                <div class="row mb-3">
                    <div class="col-md-4">
                        <div><strong>Student:</strong> <?= htmlspecialchars($student_name, ENT_QUOTES, 'UTF-8') ?> (ID: <?= htmlspecialchars((string)$student_id, ENT_QUOTES, 'UTF-8') ?>)</div>
                    </div>
                    <div class="col-md-4">
                        <div><strong>Program:</strong> <?= htmlspecialchars($primaryMajor['Major_Name'] ?? 'Undeclared', ENT_QUOTES, 'UTF-8') ?></div>
                    </div>
                    <div class="col-md-4">
                        <div><strong>Credits Earned:</strong> <?= (int)$total_credits_earned ?> / <?= (int)$creditsRequired ?></div>
                    </div>
                </div>

                <!-- Degree Progress Bar -->
                <div class="progress" style="height: 20px;">
                    <div class="progress-bar" role="progressbar" style="width: <?= $progressPct ?>%;" aria-valuenow="<?= $progressPct ?>" aria-valuemin="0" aria-valuemax="100">
                        <?= $progressPct ?>%
                    </div>
                </div>
            </div>
        </div>

        <!-- Primary Major Requirements Table -->
        <div class="card mb-4">
            <div class="card-header">
                <strong>Major Requirements: <?= htmlspecialchars($primaryMajor['Major_Name'] ?? 'Major', ENT_QUOTES, 'UTF-8') ?></strong>
            </div>
            <div class="card-body p-0">
                <?php if (empty($primaryMajor['requirements'])): ?>
                    <div class="p-3 text-muted">No major requirements found.</div>
                <?php else: ?>
                    <table class="table table-bordered table-sm mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Course ID</th>
                                <th>Course Name</th>
                                <th class="text-center">Credits</th>
                                <th class="text-center">Min. Grade</th>
                                <th class="text-center">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($primaryMajor['requirements'] as $req): ?>
                                <tr>
                                    <td><?= htmlspecialchars($req['Course_ID'], ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><?= htmlspecialchars($req['Course_Name'], ENT_QUOTES, 'UTF-8') ?></td>
                                    <td class="text-center"><?= (int)$req['Course_Credits'] ?></td>
                                    <td class="text-center"><?= htmlspecialchars($req['Min_Grade'], ENT_QUOTES, 'UTF-8') ?></td>
                                    <td class="text-center">
                                        <span class="badge <?= $req['Status_Badge_Class'] ?>">
                                            <?= htmlspecialchars($req['Status'], ENT_QUOTES, 'UTF-8') ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>

        <!-- Secondary Major Requirements Table (if declared) -->
        <?php if ($secondaryMajor): ?>
            <div class="card mb-4">
                <div class="card-header">
                    <strong>Secondary Major Requirements: <?= htmlspecialchars($secondaryMajor['Major_Name'], ENT_QUOTES, 'UTF-8') ?></strong>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($secondaryMajor['requirements'])): ?>
                        <div class="p-3 text-muted">No secondary major requirements found.</div>
                    <?php else: ?>
                        <table class="table table-bordered table-sm mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Course ID</th>
                                    <th>Course Name</th>
                                    <th class="text-center">Credits</th>
                                    <th class="text-center">Min. Grade</th>
                                    <th class="text-center">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($secondaryMajor['requirements'] as $req): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($req['Course_ID'], ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><?= htmlspecialchars($req['Course_Name'], ENT_QUOTES, 'UTF-8') ?></td>
                                        <td class="text-center"><?= (int)$req['Course_Credits'] ?></td>
                                        <td class="text-center"><?= htmlspecialchars($req['Min_Grade'], ENT_QUOTES, 'UTF-8') ?></td>
                                        <td class="text-center">
                                            <span class="badge <?= $req['Status_Badge_Class'] ?>">
                                                <?= htmlspecialchars($req['Status'], ENT_QUOTES, 'UTF-8') ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Minor Requirements Table (if declared) -->
        <?php if ($minor): ?>
            <div class="card mb-4">
                <div class="card-header">
                    <strong>Minor Requirements: <?= htmlspecialchars($minor['Minor_Name'], ENT_QUOTES, 'UTF-8') ?></strong>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($minor['requirements'])): ?>
                        <div class="p-3 text-muted">No minor requirements found.</div>
                    <?php else: ?>
                        <table class="table table-bordered table-sm mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Course ID</th>
                                    <th>Course Name</th>
                                    <th class="text-center">Credits</th>
                                    <th class="text-center">Min. Grade</th>
                                    <th class="text-center">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($minor['requirements'] as $req): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($req['Course_ID'], ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><?= htmlspecialchars($req['Course_Name'], ENT_QUOTES, 'UTF-8') ?></td>
                                        <td class="text-center"><?= (int)$req['Course_Credits'] ?></td>
                                        <td class="text-center"><?= htmlspecialchars($req['Min_Grade'], ENT_QUOTES, 'UTF-8') ?></td>
                                        <td class="text-center">
                                            <span class="badge <?= $req['Status_Badge_Class'] ?>">
                                                <?= htmlspecialchars($req['Status'], ENT_QUOTES, 'UTF-8') ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

    </div>

    <!-- Bootstrap 5 JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
