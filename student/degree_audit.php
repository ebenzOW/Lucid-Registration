<?php
/**
 * Lucid University Course Registration System
 * Student Portal - Degree Audit & Graduation Requirements
 */

require_once 'student_guard.php';
$student_id = (int)$userID;

require_once '../db_connect.php';
require_once __DIR__ . '/../includes/ui_helpers.php';
require_once __DIR__ . '/../includes/student_helpers.php';

// Fetch profile and degree audit requirements
$profile = getStudentProfile($conn, $student_id);
if (!$profile) {
    die("Student record not found.");
}

$auditData = getDegreeAuditData($conn, $student_id);
$rootPrefix = '../';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Academic Degree Audit | Lucid University</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
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

        <?php require __DIR__ . '/../includes/partials/student_degree_audit_body.php'; ?>
    </div>

    <!-- Bootstrap 5 JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
