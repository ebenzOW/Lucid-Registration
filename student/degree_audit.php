<?php
/**
 * Lucid University Course Registration System
 * Student Portal - Degree Audit & Graduation Requirements
 */

require_once 'student_guard.php';
$student_id = (int)$userID;

require_once '../db_connect.php';
require_once __DIR__ . '/../includes/academic_helpers.php';
require_once '../profile_hover_card.php';

// Fetch profile, transcript totals, and audit requirements
$profile = getStudentProfile($conn, $student_id);
if (!$profile) {
    die("Student record not found.");
}

$audit = getDegreeAuditData($conn, $student_id);

$student_name   = $profile['full_name'];
$student_type   = $profile['Student_Type'] ?? 'Undergraduate';

$primaryMajor   = $audit['primary_major'] ?? null;
$secondaryMajor = $audit['secondary_major'] ?? null;
$minor          = $audit['minor'] ?? null;

$degreeTarget    = (int)$audit['degree_credit_target'];
$totalEarned     = (int)$audit['total_earned_credits'];
$totalInProgress = (int)$audit['total_in_progress_credits'];

$progressEarnedPct = ($degreeTarget > 0) ? min(100, round(($totalEarned / $degreeTarget) * 100)) : 0;
$progressIPPct     = ($degreeTarget > 0) ? min(100 - $progressEarnedPct, round(($totalInProgress / $degreeTarget) * 100)) : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Academic Degree Audit | Lucid University</title>
    <!-- Minimal Clean Bootstrap 5.3 CSS & Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        <?= getProfileHoverCardStyles() ?>
    </style>
</head>
<body class="bg-light">

    <!-- Unified Student Top Navigation -->
    <?= renderStudentNavbar($conn, $student_id, 'degree_audit.php') ?>

    <div class="container mb-5">
        <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
            <div>
                <h2 class="fw-bold mb-1">Academic Degree Audit</h2>
                <p class="text-secondary mb-0">
                    Comprehensive evaluation of statutory degree requirements, departmental concentrations, and elective credit progress.
                </p>
            </div>
            <div>
                <a href="majors_minors.php" class="btn btn-outline-primary shadow-sm">
                    <i class="bi bi-mortarboard me-1"></i> Declare / View Majors &amp; Minors
                </a>
            </div>
        </div>

        <!-- Degree Progress Overview Card -->
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0 fw-bold">
                    <i class="bi bi-speedometer2 me-2 text-primary"></i>Degree Progress Overview
                </h5>
                <span class="badge bg-dark px-3 py-2">
                    Graduation Target: <?= $degreeTarget ?> Credits
                </span>
            </div>
            <div class="card-body p-4">
                <div class="row g-3 mb-4">
                    <div class="col-md-3">
                        <div class="p-3 bg-light rounded border h-100">
                            <div class="text-muted small text-uppercase fw-semibold mb-1">Student &amp; Level</div>
                            <div class="fw-bold text-dark"><?= htmlspecialchars($student_name, ENT_QUOTES, 'UTF-8') ?></div>
                            <div class="small text-secondary">ID: <?= (int)$student_id ?> &bull; <?= htmlspecialchars($student_type, ENT_QUOTES, 'UTF-8') ?></div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="p-3 bg-light rounded border h-100">
                            <div class="text-muted small text-uppercase fw-semibold mb-1">Declared Programs</div>
                            <div class="fw-bold text-dark"><?= htmlspecialchars($primaryMajor['Major_Name'] ?? 'Undeclared', ENT_QUOTES, 'UTF-8') ?></div>
                            <div class="small text-secondary">
                                <?php if ($secondaryMajor): ?>
                                    2nd Major: <?= htmlspecialchars($secondaryMajor['Major_Name'], ENT_QUOTES, 'UTF-8') ?><br>
                                <?php endif; ?>
                                Minor: <?= htmlspecialchars($minor['Minor_Name'] ?? 'None', ENT_QUOTES, 'UTF-8') ?>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="p-3 bg-light rounded border h-100">
                            <div class="text-muted small text-uppercase fw-semibold mb-1">Major/Minor Required</div>
                            <div class="fs-4 fw-bold text-dark mb-0">
                                <?= (int)$audit['unique_required_credits_completed'] ?> <span class="fs-6 text-muted">/ <?= (int)$audit['unique_required_credits_total'] ?> Cr</span>
                            </div>
                            <?php if ($audit['unique_required_credits_in_progress'] > 0): ?>
                                <div class="small text-primary fw-medium">+<?= (int)$audit['unique_required_credits_in_progress'] ?> Cr In Progress</div>
                            <?php else: ?>
                                <div class="small text-muted">0 Cr In Progress</div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="p-3 bg-light rounded border h-100">
                            <div class="text-muted small text-uppercase fw-semibold mb-1">Total Remaining to 120</div>
                            <div class="fs-4 fw-bold text-danger mb-0">
                                <?= (int)$audit['total_credits_remaining_to_target'] ?> <span class="fs-6 text-muted">Cr Left</span>
                            </div>
                            <div class="small text-muted">
                                <?= (int)$audit['total_credits_remaining_after_in_progress'] ?> Cr left after In-Progress
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Degree Progress Bar -->
                <div class="mb-2 d-flex justify-content-between align-items-center">
                    <span class="fw-bold small text-uppercase text-secondary">Overall Degree Completion</span>
                    <span class="small fw-semibold text-muted">
                        Earned: <?= $totalEarned ?> Cr &bull; In Progress: <?= $totalInProgress ?> Cr &bull; Target: <?= $degreeTarget ?> Cr
                    </span>
                </div>
                <div class="progress" style="height: 24px;">
                    <div class="progress-bar bg-success fw-bold" role="progressbar" style="width: <?= $progressEarnedPct ?>%;" aria-valuenow="<?= $progressEarnedPct ?>" aria-valuemin="0" aria-valuemax="100">
                        <?php if ($progressEarnedPct >= 8): ?><?= $progressEarnedPct ?>% Earned<?php endif; ?>
                    </div>
                    <?php if ($progressIPPct > 0): ?>
                        <div class="progress-bar bg-primary progress-bar-striped progress-bar-animated fw-bold" role="progressbar" style="width: <?= $progressIPPct ?>%;" aria-valuenow="<?= $progressIPPct ?>" aria-valuemin="0" aria-valuemax="100">
                            <?php if ($progressIPPct >= 8): ?>+<?= $progressIPPct ?>% In Progress<?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="d-flex justify-content-between align-items-center mt-2 small text-muted">
                    <div>
                        <span class="badge bg-success me-1">&nbsp;</span> Completed Credits (<?= $totalEarned ?> Cr)
                        <span class="badge bg-primary ms-3 me-1">&nbsp;</span> In-Progress Credits (<?= $totalInProgress ?> Cr)
                    </div>
                    <div>
                        <strong><?= $progressEarnedPct + $progressIPPct ?>%</strong> Projected Completion
                    </div>
                </div>
            </div>
        </div>

        <!-- Primary Major Requirements Table -->
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0 fw-bold">
                    <i class="bi bi-mortarboard-fill me-2 text-primary"></i>Primary Major Requirements: <?= htmlspecialchars($primaryMajor['Major_Name'] ?? 'No Major Declared', ENT_QUOTES, 'UTF-8') ?>
                </h5>
                <?php if ($primaryMajor): ?>
                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-3 py-2">
                        <?= (int)$primaryMajor['No_Credits_Required'] ?> Total Program Credits
                    </span>
                <?php endif; ?>
            </div>
            <div class="card-body p-0">
                <?php if (empty($primaryMajor['requirements'])): ?>
                    <div class="p-4 text-center text-muted">
                        <i class="bi bi-info-circle me-1"></i> No curriculum requirements configured or major not yet declared.
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th style="width: 14%;">Course ID</th>
                                    <th style="width: 26%;">Course Name</th>
                                    <th style="width: 16%;">Department</th>
                                    <th class="text-center" style="width: 8%;">Credits</th>
                                    <th class="text-center" style="width: 8%;">Min Grade</th>
                                    <th class="text-center" style="width: 14%;">Semester Taken / Enrolled</th>
                                    <th class="text-center" style="width: 7%;">Grade</th>
                                    <th class="text-center" style="width: 7%;">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($primaryMajor['courses_by_department'] as $deptName => $deptReqs): ?>
                                    <tr class="table-secondary">
                                        <td colspan="8" class="fw-bold py-2">
                                            <i class="bi bi-building me-2"></i><?= htmlspecialchars($deptName, ENT_QUOTES, 'UTF-8') ?>
                                            <span class="badge bg-secondary ms-2">
                                                <?= count($deptReqs) ?> <?= count($deptReqs) === 1 ? 'Course' : 'Courses' ?> 
                                                (<?= array_sum(array_column($deptReqs, 'Course_Credits')) ?> Credits)
                                            </span>
                                        </td>
                                    </tr>
                                    <?php foreach ($deptReqs as $req): ?>
                                        <tr>
                                            <td class="fw-semibold font-monospace"><?= htmlspecialchars($req['Course_ID'], ENT_QUOTES, 'UTF-8') ?></td>
                                            <td><?= htmlspecialchars($req['Course_Name'], ENT_QUOTES, 'UTF-8') ?></td>
                                            <td><small class="text-muted"><?= htmlspecialchars($req['Department_Name'] ?? 'General', ENT_QUOTES, 'UTF-8') ?></small></td>
                                            <td class="text-center"><?= (int)$req['Course_Credits'] ?></td>
                                            <td class="text-center fw-medium"><?= htmlspecialchars($req['Min_Grade'], ENT_QUOTES, 'UTF-8') ?></td>
                                            <td class="text-center">
                                                <?php if (!empty($req['Term_Taken'])): ?>
                                                    <span class="badge <?= (strpos($req['Term_Taken'], 'In Progress') !== false) ? 'text-bg-primary' : 'text-bg-light border text-dark' ?>">
                                                        <?= htmlspecialchars($req['Term_Taken'], ENT_QUOTES, 'UTF-8') ?>
                                                    </span>
                                                <?php else: ?>
                                                    <span class="text-muted">&mdash;</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-center fw-semibold">
                                                <?php if (!empty($req['Grade_Earned'])): ?>
                                                    <span class="<?= $req['Grade_Earned'] === 'In Progress' ? 'text-primary' : 'text-dark' ?>">
                                                        <?= htmlspecialchars($req['Grade_Earned'], ENT_QUOTES, 'UTF-8') ?>
                                                    </span>
                                                <?php else: ?>
                                                    <span class="text-muted">&mdash;</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-center">
                                                <span class="badge <?= htmlspecialchars($req['Status_Badge_Class'], ENT_QUOTES, 'UTF-8') ?>">
                                                    <?= htmlspecialchars($req['Status'], ENT_QUOTES, 'UTF-8') ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Secondary Major Requirements Table (if declared) -->
        <?php if ($secondaryMajor): ?>
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0 fw-bold">
                        <i class="bi bi-mortarboard me-2 text-info"></i>Secondary Major Requirements: <?= htmlspecialchars($secondaryMajor['Major_Name'], ENT_QUOTES, 'UTF-8') ?>
                    </h5>
                    <span class="badge bg-info-subtle text-info border border-info-subtle px-3 py-2">
                        <?= (int)$secondaryMajor['No_Credits_Required'] ?> Total Program Credits
                    </span>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($secondaryMajor['requirements'])): ?>
                        <div class="p-4 text-center text-muted">
                            <i class="bi bi-info-circle me-1"></i> No curriculum requirements configured for this secondary major.
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th style="width: 14%;">Course ID</th>
                                        <th style="width: 26%;">Course Name</th>
                                        <th style="width: 16%;">Department</th>
                                        <th class="text-center" style="width: 8%;">Credits</th>
                                        <th class="text-center" style="width: 8%;">Min Grade</th>
                                        <th class="text-center" style="width: 14%;">Semester Taken / Enrolled</th>
                                        <th class="text-center" style="width: 7%;">Grade</th>
                                        <th class="text-center" style="width: 7%;">Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($secondaryMajor['courses_by_department'] as $deptName => $deptReqs): ?>
                                        <tr class="table-secondary">
                                            <td colspan="8" class="fw-bold py-2">
                                                <i class="bi bi-building me-2"></i><?= htmlspecialchars($deptName, ENT_QUOTES, 'UTF-8') ?>
                                                <span class="badge bg-secondary ms-2">
                                                    <?= count($deptReqs) ?> <?= count($deptReqs) === 1 ? 'Course' : 'Courses' ?> 
                                                    (<?= array_sum(array_column($deptReqs, 'Course_Credits')) ?> Credits)
                                                </span>
                                            </td>
                                        </tr>
                                        <?php foreach ($deptReqs as $req): ?>
                                            <tr>
                                                <td class="fw-semibold font-monospace"><?= htmlspecialchars($req['Course_ID'], ENT_QUOTES, 'UTF-8') ?></td>
                                                <td><?= htmlspecialchars($req['Course_Name'], ENT_QUOTES, 'UTF-8') ?></td>
                                                <td><small class="text-muted"><?= htmlspecialchars($req['Department_Name'] ?? 'General', ENT_QUOTES, 'UTF-8') ?></small></td>
                                                <td class="text-center"><?= (int)$req['Course_Credits'] ?></td>
                                                <td class="text-center fw-medium"><?= htmlspecialchars($req['Min_Grade'], ENT_QUOTES, 'UTF-8') ?></td>
                                                <td class="text-center">
                                                    <?php if (!empty($req['Term_Taken'])): ?>
                                                        <span class="badge <?= (strpos($req['Term_Taken'], 'In Progress') !== false) ? 'text-bg-primary' : 'text-bg-light border text-dark' ?>">
                                                            <?= htmlspecialchars($req['Term_Taken'], ENT_QUOTES, 'UTF-8') ?>
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="text-muted">&mdash;</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="text-center fw-semibold">
                                                    <?php if (!empty($req['Grade_Earned'])): ?>
                                                        <span class="<?= $req['Grade_Earned'] === 'In Progress' ? 'text-primary' : 'text-dark' ?>">
                                                            <?= htmlspecialchars($req['Grade_Earned'], ENT_QUOTES, 'UTF-8') ?>
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="text-muted">&mdash;</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="text-center">
                                                    <span class="badge <?= htmlspecialchars($req['Status_Badge_Class'], ENT_QUOTES, 'UTF-8') ?>">
                                                        <?= htmlspecialchars($req['Status'], ENT_QUOTES, 'UTF-8') ?>
                                                    </span>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Minor Requirements Table (if declared) -->
        <?php if ($minor): ?>
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0 fw-bold">
                        <i class="bi bi-bookmark-check me-2 text-warning"></i>Minor Requirements: <?= htmlspecialchars($minor['Minor_Name'], ENT_QUOTES, 'UTF-8') ?>
                    </h5>
                    <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle px-3 py-2">
                        <?= (int)$minor['No_Credits_Required'] ?> Total Minor Credits
                    </span>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($minor['requirements'])): ?>
                        <div class="p-4 text-center text-muted">
                            <i class="bi bi-info-circle me-1"></i> No curriculum requirements configured for this minor.
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th style="width: 14%;">Course ID</th>
                                        <th style="width: 26%;">Course Name</th>
                                        <th style="width: 16%;">Department</th>
                                        <th class="text-center" style="width: 8%;">Credits</th>
                                        <th class="text-center" style="width: 8%;">Min Grade</th>
                                        <th class="text-center" style="width: 14%;">Semester Taken / Enrolled</th>
                                        <th class="text-center" style="width: 7%;">Grade</th>
                                        <th class="text-center" style="width: 7%;">Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($minor['courses_by_department'] as $deptName => $deptReqs): ?>
                                        <tr class="table-secondary">
                                            <td colspan="8" class="fw-bold py-2">
                                                <i class="bi bi-building me-2"></i><?= htmlspecialchars($deptName, ENT_QUOTES, 'UTF-8') ?>
                                                <span class="badge bg-secondary ms-2">
                                                    <?= count($deptReqs) ?> <?= count($deptReqs) === 1 ? 'Course' : 'Courses' ?> 
                                                    (<?= array_sum(array_column($deptReqs, 'Course_Credits')) ?> Credits)
                                                </span>
                                            </td>
                                        </tr>
                                        <?php foreach ($deptReqs as $req): ?>
                                            <tr>
                                                <td class="fw-semibold font-monospace"><?= htmlspecialchars($req['Course_ID'], ENT_QUOTES, 'UTF-8') ?></td>
                                                <td><?= htmlspecialchars($req['Course_Name'], ENT_QUOTES, 'UTF-8') ?></td>
                                                <td><small class="text-muted"><?= htmlspecialchars($req['Department_Name'] ?? 'General', ENT_QUOTES, 'UTF-8') ?></small></td>
                                                <td class="text-center"><?= (int)$req['Course_Credits'] ?></td>
                                                <td class="text-center fw-medium"><?= htmlspecialchars($req['Min_Grade'], ENT_QUOTES, 'UTF-8') ?></td>
                                                <td class="text-center">
                                                    <?php if (!empty($req['Term_Taken'])): ?>
                                                        <span class="badge <?= (strpos($req['Term_Taken'], 'In Progress') !== false) ? 'text-bg-primary' : 'text-bg-light border text-dark' ?>">
                                                            <?= htmlspecialchars($req['Term_Taken'], ENT_QUOTES, 'UTF-8') ?>
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="text-muted">&mdash;</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="text-center fw-semibold">
                                                    <?php if (!empty($req['Grade_Earned'])): ?>
                                                        <span class="<?= $req['Grade_Earned'] === 'In Progress' ? 'text-primary' : 'text-dark' ?>">
                                                            <?= htmlspecialchars($req['Grade_Earned'], ENT_QUOTES, 'UTF-8') ?>
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="text-muted">&mdash;</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="text-center">
                                                    <span class="badge <?= htmlspecialchars($req['Status_Badge_Class'], ENT_QUOTES, 'UTF-8') ?>">
                                                        <?= htmlspecialchars($req['Status'], ENT_QUOTES, 'UTF-8') ?>
                                                    </span>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Dedicated Elective Courses & Remaining Elective Credits Card -->
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0 fw-bold">
                    <i class="bi bi-journal-bookmark me-2 text-primary"></i>Elective Courses &amp; Elective Credits Progress
                </h5>
                <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-3 py-2">
                    Target: <?= $degreeTarget ?> Total Degree Credits
                </span>
            </div>
            <div class="card-body p-4">
                <!-- 4 KPI Metric Boxes -->
                <div class="row g-3 mb-4">
                    <div class="col-sm-6 col-lg-3">
                        <div class="p-3 bg-light rounded border text-center h-100">
                            <div class="text-muted small text-uppercase fw-semibold mb-1">Required Electives</div>
                            <div class="fs-2 fw-bold text-dark"><?= (int)$audit['elective_credits_needed_total'] ?></div>
                            <div class="small text-muted">credits toward degree</div>
                        </div>
                    </div>
                    <div class="col-sm-6 col-lg-3">
                        <div class="p-3 bg-light rounded border text-center h-100">
                            <div class="text-muted small text-uppercase fw-semibold mb-1">Completed Electives</div>
                            <div class="fs-2 fw-bold text-success"><?= (int)$audit['elective_credits_completed'] ?></div>
                            <div class="small text-muted">credits earned</div>
                        </div>
                    </div>
                    <div class="col-sm-6 col-lg-3">
                        <div class="p-3 bg-light rounded border text-center h-100">
                            <div class="text-muted small text-uppercase fw-semibold mb-1">In-Progress Electives</div>
                            <div class="fs-2 fw-bold text-primary"><?= (int)$audit['elective_credits_in_progress'] ?></div>
                            <div class="small text-muted">credits enrolled</div>
                        </div>
                    </div>
                    <div class="col-sm-6 col-lg-3">
                        <div class="p-3 bg-light rounded border text-center h-100">
                            <div class="text-muted small text-uppercase fw-semibold mb-1">Remaining Electives</div>
                            <div class="fs-2 fw-bold text-danger"><?= (int)$audit['elective_credits_remaining_completed_only'] ?></div>
                            <div class="small text-muted"><?= (int)$audit['elective_credits_remaining_after_in_progress'] ?> after in-progress</div>
                        </div>
                    </div>
                </div>

                <h6 class="fw-bold mb-3"><i class="bi bi-list-check me-2"></i>Completed &amp; In-Progress Elective Courses</h6>
                <?php if (empty($audit['elective_courses'])): ?>
                    <div class="alert alert-light border text-muted d-flex align-items-center mb-0 p-3" role="alert">
                        <i class="bi bi-info-circle me-3 fs-4 text-primary"></i>
                        <div>
                            No general elective courses have been completed or registered yet. Non-major courses at your academic level (<strong><?= htmlspecialchars($student_type, ENT_QUOTES, 'UTF-8') ?></strong>) will automatically count toward your elective degree requirement.
                        </div>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th style="width: 14%;">Course ID</th>
                                    <th style="width: 30%;">Course Name</th>
                                    <th style="width: 20%;">Department</th>
                                    <th class="text-center" style="width: 8%;">Credits</th>
                                    <th class="text-center" style="width: 16%;">Semester Taken / Enrolled</th>
                                    <th class="text-center" style="width: 6%;">Grade</th>
                                    <th class="text-center" style="width: 6%;">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($audit['elective_courses'] as $el): ?>
                                    <tr>
                                        <td class="fw-semibold font-monospace"><?= htmlspecialchars($el['Course_ID'], ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><?= htmlspecialchars($el['Course_Name'], ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><small class="text-muted"><?= htmlspecialchars($el['Department_Name'], ENT_QUOTES, 'UTF-8') ?></small></td>
                                        <td class="text-center"><?= (int)$el['Course_Credits'] ?></td>
                                        <td class="text-center">
                                            <span class="badge <?= (strpos($el['Term_Taken'], 'In Progress') !== false) ? 'text-bg-primary' : 'text-bg-light border text-dark' ?>">
                                                <?= htmlspecialchars($el['Term_Taken'], ENT_QUOTES, 'UTF-8') ?>
                                            </span>
                                        </td>
                                        <td class="text-center fw-semibold">
                                            <span class="<?= $el['Grade'] === 'In Progress' ? 'text-primary' : 'text-dark' ?>">
                                                <?= htmlspecialchars($el['Grade'], ENT_QUOTES, 'UTF-8') ?>
                                            </span>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge <?= htmlspecialchars($el['Status_Badge_Class'], ENT_QUOTES, 'UTF-8') ?>">
                                                <?= htmlspecialchars($el['Status'], ENT_QUOTES, 'UTF-8') ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

    </div>

    <!-- Bootstrap 5 JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
