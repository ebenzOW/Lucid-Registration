<?php
require_once 'student_guard.php';
$student_id = (int)$userID;

require_once '../db_connect.php';
require_once __DIR__ . '/../includes/academic_helpers.php';
require_once '../profile_hover_card.php';

// Fetch profile and transcript data
$profile = getStudentProfile($conn, $student_id);
if (!$profile) {
    die("Student record not found.");
}

$transcript = getStudentTranscriptData($conn, $student_id);
$terms = $transcript['terms'];

$student_name    = $profile['full_name'];
$student_type    = $profile['Student_Type'] ?? 'Undergraduate';
$department_name = $profile['Department_Name'] ?? 'General Academics';
$primary_major   = $profile['primary_major']['Major_Name'] ?? 'Undeclared';
$minor_name      = $profile['minor']['Minor_Name'] ?? 'None';

$cum_gpa       = $transcript['cum_gpa'];
$total_credits = $transcript['total_credits'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Unofficial Transcript - Lucid University</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        <?= getProfileHoverCardStyles() ?>
    </style>
</head>
<body class="bg-light">

    <!-- Unified Student Top Navigation -->
    <?= renderStudentNavbar($conn, $student_id, 'transcript.php') ?>

    <div class="container mb-5">
        <h2 class="mb-3">Unofficial Academic Transcript</h2>

        <!-- Student Profile Card -->
        <div class="card mb-4">
            <div class="card-header">
                <strong>Student Record</strong>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-4">
                        <div><strong>Name:</strong> <?= htmlspecialchars($student_name, ENT_QUOTES, 'UTF-8') ?></div>
                        <div><strong>Student ID:</strong> <?= htmlspecialchars((string)$student_id, ENT_QUOTES, 'UTF-8') ?></div>
                    </div>
                    <div class="col-md-4">
                        <div><strong>Department:</strong> <?= htmlspecialchars($department_name, ENT_QUOTES, 'UTF-8') ?></div>
                        <div><strong>Level:</strong> <?= htmlspecialchars($student_type, ENT_QUOTES, 'UTF-8') ?></div>
                    </div>
                    <div class="col-md-4">
                        <div><strong>Major:</strong> <?= htmlspecialchars($primary_major, ENT_QUOTES, 'UTF-8') ?></div>
                        <div><strong>Minor:</strong> <?= htmlspecialchars($minor_name, ENT_QUOTES, 'UTF-8') ?></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Academic Summary Cards -->
        <div class="row mb-4">
            <div class="col-md-6 mb-2">
                <div class="card">
                    <div class="card-body">
                        <div class="text-muted small">Cumulative GPA</div>
                        <div class="fs-4 fw-bold"><?= number_format($cum_gpa, 2) ?></div>
                    </div>
                </div>
            </div>
            <div class="col-md-6 mb-2">
                <div class="card">
                    <div class="card-body">
                        <div class="text-muted small">Total Institutional Credits Earned</div>
                        <div class="fs-4 fw-bold"><?= (int)$total_credits ?></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Semester Coursework History -->
        <h4 class="mb-3">Coursework by Semester</h4>

        <?php if (empty($terms)): ?>
            <div class="card">
                <div class="card-body text-center text-muted">
                    No completed course history found.
                </div>
            </div>
        <?php else: ?>
            <?php foreach ($terms as $semId => $term): ?>
                <div class="card mb-3">
                    <div class="card-header">
                        <strong><?= htmlspecialchars($term['semester_label'], ENT_QUOTES, 'UTF-8') ?></strong>
                    </div>
                    <div class="card-body p-0">
                        <table class="table table-bordered table-sm mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Course ID</th>
                                    <th>Course Name</th>
                                    <th class="text-center">Credits Attempted</th>
                                    <th class="text-center">Credits Earned</th>
                                    <th class="text-center">Final Grade</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($term['courses'] as $course): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($course['Course_ID'], ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><?= htmlspecialchars($course['Course_Name'], ENT_QUOTES, 'UTF-8') ?></td>
                                        <td class="text-center"><?= (int)$course['credits_attempted'] ?></td>
                                        <td class="text-center"><?= (int)$course['credits_earned'] ?></td>
                                        <td class="text-center"><?= htmlspecialchars($course['Grade'], ENT_QUOTES, 'UTF-8') ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="card-footer d-flex justify-content-between">
                        <span>Total Term Credits Earned: <strong><?= (int)$term['term_credits_earned'] ?></strong></span>
                        <span>Term GPA: <strong><?= number_format($term['term_gpa'], 2) ?></strong></span>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- Bootstrap 5 JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
