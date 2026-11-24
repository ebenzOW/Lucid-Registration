<?php
/**
 * Lucid University Course Registration System
 * Faculty Portal - Assigned Academic Advisees
 */

require_once 'faculty_guard.php';
$faculty_id = (int)$userID;

require_once '../db_connect.php';
require_once __DIR__ . '/../includes/ui_helpers.php';
require_once __DIR__ . '/../includes/faculty_helpers.php';

$advisees = getFacultyAdvisees($conn, $faculty_id);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Advisees | Lucid University Faculty</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light d-flex flex-column min-vh-100">

    <?= renderFacultyNavbar($conn, $faculty_id, 'view_advisees.php') ?>

    <main class="container mb-5 flex-grow-1">
        <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
            <div>
                <h2 class="fw-bold mb-1">View Advisees</h2>
                <p class="text-secondary mb-0">Review your assigned students and open their degree audits.</p>
            </div>
            <a href="dashboard.php" class="btn btn-outline-secondary btn-sm">&larr; Back to Dashboard</a>
        </div>

        <!-- Assigned Academic Advisees Table -->
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <div>
                    <h5 class="mb-0 fw-bold">Assigned Academic Advisees</h5>
                    <small class="text-muted">Formal Advisees (Policy Cap: 15 Students)</small>
                </div>
                <span class="badge bg-secondary fs-6"><?= count($advisees) ?> / 15 Assigned</span>
            </div>
            <div class="card-body p-0">
                <?php if (empty($advisees)): ?>
                    <div class="p-4 text-center text-muted">
                        No students currently assigned as academic advisees.
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover table-striped mb-0 align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>Student ID</th>
                                    <th>Full Name</th>
                                    <th>Email</th>
                                    <th>Phone</th>
                                    <th>Class Standing</th>
                                    <th>Attendance</th>
                                    <th>Major(s)</th>
                                    <th class="text-center">Earned Credits</th>
                                    <th class="text-center">Holds</th>
                                    <th class="text-center">Degree Audit</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($advisees as $adv): ?>
                                    <tr>
                                        <td><strong><?= (int)$adv['Student_ID'] ?></strong></td>
                                        <td class="fw-semibold">
                                            <a href="../user_profile.php?user_id=<?= (int)$adv['Student_ID'] ?>" class="text-decoration-none text-primary fw-semibold">
                                                <?= htmlspecialchars($adv['full_name'], ENT_QUOTES, 'UTF-8') ?>
                                            </a>
                                        </td>
                                        <td><?= htmlspecialchars($adv['User_Email'] ?? '-', ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><?= htmlspecialchars($adv['User_PhoneNo'] ?? '-', ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><?= htmlspecialchars($adv['class_standing'], ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><?= htmlspecialchars($adv['Attendance_Type'], ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><?= htmlspecialchars($adv['Majors'] ?? 'Undeclared', ENT_QUOTES, 'UTF-8') ?></td>
                                        <td class="text-center fw-bold"><?= (int)$adv['Credits_Earned'] ?></td>
                                        <td class="text-center">
                                            <?php if ((int)$adv['Active_Holds_Count'] > 0): ?>
                                                <span class="badge bg-danger">
                                                    <?= (int)$adv['Active_Holds_Count'] ?> Hold<?= (int)$adv['Active_Holds_Count'] > 1 ? 's' : '' ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="badge bg-success">Clear</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center">
                                            <a href="advisee_degree_audit.php?student_id=<?= (int)$adv['Student_ID'] ?>"
                                               class="btn btn-outline-primary btn-sm text-nowrap">View Degree Audit</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>


    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
