<?php
require_once 'student_guard.php';
$student_id = (int)$userID;

require_once '../db_connect.php';
require_once __DIR__ . '/../includes/ui_helpers.php';
require_once __DIR__ . '/../includes/student_helpers.php';

// Fetch profile and transcript data
$profile = getStudentProfile($conn, $student_id);
if (!$profile) {
    die("Student record not found.");
}

$transcriptData = getStudentTranscriptData($conn, $student_id);

$student_name    = $profile['full_name'];
$student_type    = $profile['Student_Type'] ?? 'Undergraduate';
$department_name = $profile['Department_Name'] ?? 'General Academics';
$primary_major   = $profile['primary_major']['Major_Name'] ?? 'Undeclared';
$minor_name      = $profile['minor']['Minor_Name'] ?? 'None';
$rootPrefix      = '../';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Unofficial Transcript - Lucid University</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
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

        <?php require __DIR__ . '/../includes/partials/student_transcript_body.php'; ?>
    </div>

    <!-- Bootstrap 5 JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
