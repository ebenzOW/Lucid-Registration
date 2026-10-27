<?php
/**
 * Lucid University Course Registration System
 * Student Personal Schedule Portal
 *
 * View personal class schedule supporting:
 * - Current Semester: Fall 2026 (202608)
 * - Next Semester: Spring 2027 (202701)
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
$student_type      = $profile['Student_Type'] ?? 'Undergraduate';
$department_name   = $profile['Department_Name'] ?? 'General Academics';
$primary_major     = $profile['primary_major']['Major_Name'] ?? 'Undeclared';
$minor_name        = $profile['minor']['Minor_Name'] ?? 'None';

// 2. Determine Selected Semester (Default: Fall 2026 = 202608)
$selected_sem_id = isset($_GET['semester']) ? filter_var($_GET['semester'], FILTER_VALIDATE_INT) : null;
if (!$selected_sem_id) {
    $selected_sem_id = 202608; // Default to Current Active Semester
}

// Fetch semester metadata
$semInfo = getSemesterInfo($conn, $selected_sem_id);
$semester_name = $semInfo ? ($semInfo['Semester_Name'] . ' ' . $semInfo['Semester_Year']) : ($selected_sem_id === 202701 ? 'Spring 2027' : 'Fall 2026');
$is_current_term = ($selected_sem_id === 202608);
$is_next_term    = ($selected_sem_id === 202701);
$is_reg_open     = $semInfo ? (bool)$semInfo['is_reg_open'] : false;

// 3. Fetch Enrolled Courses for the Selected Semester
$scheduleData = getStudentScheduleBySemester($conn, $student_id, $selected_sem_id);
$enrolledCourses = $scheduleData['courses'];
$total_credits   = $scheduleData['total_credits'];
$course_count    = count($enrolledCourses);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Schedule &bull; <?= htmlspecialchars($semester_name, ENT_QUOTES, 'UTF-8') ?> | Lucid University</title>
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
                        <a class="nav-link active" aria-current="page" href="schedule.php">My Schedule</a>
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

        <!-- Header & Action Row -->
        <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
            <div>
                <h2 class="fw-bold mb-1">Personal Class Schedule</h2>
                <div class="text-secondary">
                    Review your enrolled courses, time slots, room locations, and instructors.
                </div>
            </div>
            <div class="d-flex gap-2">
                <a href="master_schedule.php?semester=<?= $selected_sem_id ?>" class="btn btn-outline-secondary btn-sm">
                    Browse Master Schedule
                </a>
                <?php if ($is_next_term || $is_reg_open): ?>
                    <a href="register.php?semester=<?= $selected_sem_id ?>" class="btn btn-primary btn-sm">
                        Manage / Add-Drop Classes
                    </a>
                <?php endif; ?>
            </div>
        </div>

        <!-- Semester Selection Nav Pills / Tabs -->
        <div class="card shadow-sm mb-4">
            <div class="card-body p-2 bg-white">
                <ul class="nav nav-pills nav-fill">
                    <li class="nav-item">
                        <a class="nav-link <?= $is_current_term ? 'active' : '' ?>" href="schedule.php?semester=202608">
                            <strong>Fall 2026</strong> &mdash; Current Semester
                            <?php if ($is_current_term): ?>
                                <span class="badge text-bg-light ms-1">Active</span>
                            <?php endif; ?>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= $is_next_term ? 'active' : '' ?>" href="schedule.php?semester=202701">
                            <strong>Spring 2027</strong> &mdash; Next Semester
                            <?php if ($is_next_term): ?>
                                <span class="badge text-bg-light ms-1">Registration Open</span>
                            <?php endif; ?>
                        </a>
                    </li>
                </ul>
            </div>
        </div>

        <!-- Schedule Summary Card -->
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-white py-3">
                <div class="d-flex justify-content-between align-items-center">
                    <h5 class="mb-0 fw-bold">
                        Schedule Summary &bull; <?= htmlspecialchars($semester_name, ENT_QUOTES, 'UTF-8') ?>
                    </h5>
                    <?php if ($is_current_term): ?>
                        <span class="badge bg-success">Current Semester (In Progress)</span>
                    <?php elseif ($is_next_term): ?>
                        <span class="badge bg-info text-dark">Upcoming Term (Spring 2027)</span>
                    <?php else: ?>
                        <span class="badge bg-secondary">Term ID: <?= (int)$selected_sem_id ?></span>
                    <?php endif; ?>
                </div>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-3">
                        <div class="border rounded p-3 h-100 bg-white">
                            <span class="text-muted small d-block">Student</span>
                            <div class="fw-bold fs-6"><?= htmlspecialchars($student_full_name, ENT_QUOTES, 'UTF-8') ?></div>
                            <div class="text-secondary small">ID: <?= (int)$student_id ?></div>
                            <div class="small mt-1 text-muted">Major: <?= htmlspecialchars($primary_major, ENT_QUOTES, 'UTF-8') ?></div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="border rounded p-3 h-100 bg-white">
                            <span class="text-muted small d-block">Academic Level</span>
                            <div class="fw-bold fs-6"><?= htmlspecialchars($student_type, ENT_QUOTES, 'UTF-8') ?></div>
                            <div class="text-secondary small">Dept: <?= htmlspecialchars($department_name, ENT_QUOTES, 'UTF-8') ?></div>
                            <div class="small mt-1 text-muted">Minor: <?= htmlspecialchars($minor_name, ENT_QUOTES, 'UTF-8') ?></div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="border rounded p-3 h-100 bg-white">
                            <span class="text-muted small d-block">Total Enrolled Courses</span>
                            <div class="fs-4 fw-bold text-primary"><?= (int)$course_count ?></div>
                            <div class="text-muted small">Course sections registered</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="border rounded p-3 h-100 bg-white">
                            <span class="text-muted small d-block">Total Enrolled Credits</span>
                            <div class="fs-4 fw-bold text-success"><?= (int)$total_credits ?> Credits</div>
                            <div class="text-muted small">Scheduled credit hours</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Enrolled Courses Table -->
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <h5 class="mb-0 fw-bold">Class Schedule Details</h5>
                <span class="badge bg-primary"><?= (int)$course_count ?> Courses</span>
            </div>
            <div class="card-body p-0">
                <?php if (empty($enrolledCourses)): ?>
                    <div class="p-5 text-center text-muted">
                        <h5 class="fw-bold text-secondary">No Classes Scheduled</h5>
                        <p class="mb-3">You are not currently registered for any classes in <?= htmlspecialchars($semester_name, ENT_QUOTES, 'UTF-8') ?>.</p>
                        <?php if ($is_next_term): ?>
                            <a href="register.php?semester=202701" class="btn btn-primary btn-sm">
                                Register for Spring 2027 Classes
                            </a>
                        <?php else: ?>
                            <a href="master_schedule.php?semester=<?= $selected_sem_id ?>" class="btn btn-outline-secondary btn-sm">
                                View Master Schedule
                            </a>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover table-striped mb-0 align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>CRN</th>
                                    <th>Course ID</th>
                                    <th>Sec</th>
                                    <th>Course Name</th>
                                    <th>Department</th>
                                    <th class="text-center">Credits</th>
                                    <th>Day / Time</th>
                                    <th>Building &amp; Room</th>
                                    <th>Instructor</th>
                                    <th class="text-center">Status / Grade</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($enrolledCourses as $sec): ?>
                                    <tr>
                                        <td><strong><?= (int)$sec['CRN'] ?></strong></td>
                                        <td><span class="badge text-bg-light border"><?= htmlspecialchars($sec['Course_ID'], ENT_QUOTES, 'UTF-8') ?></span></td>
                                        <td><?= htmlspecialchars((string)$sec['Section_Number'], ENT_QUOTES, 'UTF-8') ?></td>
                                        <td>
                                            <span class="fw-semibold"><?= htmlspecialchars($sec['Course_Name'], ENT_QUOTES, 'UTF-8') ?></span>
                                            <span class="badge bg-secondary-subtle text-secondary small ms-1"><?= htmlspecialchars($sec['Course_Type'], ENT_QUOTES, 'UTF-8') ?></span>
                                        </td>
                                        <td><small class="text-muted"><?= htmlspecialchars($sec['Department_Name'] ?? 'General', ENT_QUOTES, 'UTF-8') ?></small></td>
                                        <td class="text-center fw-bold"><?= (int)$sec['Course_Credits'] ?></td>
                                        <td><?= formatSchedule($sec['Time_Slot_Day'], $sec['Start_Time'], $sec['End_Time']) ?></td>
                                        <td><?= htmlspecialchars($sec['Building_Name'] . ' ' . $sec['Room_NO'], ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><?= htmlspecialchars($sec['Instructor_Name'], ENT_QUOTES, 'UTF-8') ?></td>
                                        <td class="text-center">
                                            <?php if (!empty($sec['Grade'])): ?>
                                                <span class="badge text-bg-success"><?= htmlspecialchars($sec['Grade'], ENT_QUOTES, 'UTF-8') ?></span>
                                            <?php elseif ($selected_sem_id === 202701): ?>
                                                <span class="badge text-bg-secondary">Registered</span>
                                            <?php else: ?>
                                                <span class="badge text-bg-primary">In Progress</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot class="table-light">
                                <tr>
                                    <td colspan="5" class="fw-bold text-end">Total Scheduled Credits:</td>
                                    <td class="text-center fw-bold fs-6 text-success"><?= (int)$total_credits ?></td>
                                    <td colspan="4"></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
            <?php if (!empty($enrolledCourses) && ($is_next_term || $is_reg_open)): ?>
                <div class="card-footer bg-white d-flex justify-content-between align-items-center py-3">
                    <span class="text-muted small">Need to make adjustments to your upcoming schedule?</span>
                    <a href="register.php?semester=<?= $selected_sem_id ?>" class="btn btn-outline-primary btn-sm">
                        Add / Drop <?= htmlspecialchars($semester_name, ENT_QUOTES, 'UTF-8') ?> Classes
                    </a>
                </div>
            <?php endif; ?>
        </div>

    </div>

    <!-- Bootstrap 5 JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
