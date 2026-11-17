<?php
/**
 * Lucid University Course Registration System
 * Student Main Portal Dashboard
 *
 * Clean Bootstrap 5.3 dashboard providing student profile overview,
 * quick academic metrics, dual-semester calendar & deadlines,
 * active holds status, and direct navigation.
 */

require_once 'student_guard.php';
$student_id = (int)$userID;

require_once '../db_connect.php';
require_once __DIR__ . '/../includes/academic_helpers.php';
require_once '../profile_hover_card.php';

// 1. Enforce failed prerequisites and fetch notices
$notices = checkAndEnforceFailedPrerequisites($conn, $student_id);

// 2. Fetch Student Demographic & Academic Profile
$profile = getStudentProfile($conn, $student_id);
if (!$profile) {
    die("Student profile record not found.");
}

$student_full_name = $profile['full_name'];
$student_type      = $profile['Student_Type'] ?? 'Undergraduate';
$class_year        = $profile['year_label'] ?? 'Undergraduate';
$attendance_type   = $profile['Attendance_Type'] ?? 'Full-Time';
$department_name   = $profile['Department_Name'] ?? 'General Academics';
$primary_major     = $profile['primary_major']['Major_Name'] ?? 'Undeclared';
$secondary_major   = $profile['secondary_major']['Major_Name'] ?? null;
$minor_name        = $profile['minor']['Minor_Name'] ?? 'None';

// 3. Fetch Transcript Totals
$transcript = getStudentTranscriptData($conn, $student_id);
$cum_gpa       = $transcript['cum_gpa'];
$total_credits = $transcript['total_credits'];

// 4. Fetch Active Account Holds
$holds = getStudentHolds($conn, $student_id);
$has_active_holds = !empty($holds);

// 5. Fetch Enrolled Schedules for Current (Fall 2026) and Next (Spring 2027) Terms
$fallSchedule   = getStudentScheduleBySemester($conn, $student_id, 202608);
$springSchedule = getStudentScheduleBySemester($conn, $student_id, 202701);

$fallCourses   = $fallSchedule['courses'];
$fallCredits   = $fallSchedule['total_credits'];
$springCourses = $springSchedule['courses'];
$springCredits = $springSchedule['total_credits'];

// 6. Fetch Dual-Semester Metadata (Fall 2026 & Spring 2027)
$fallInfo   = getSemesterInfo($conn, 202608);
$springInfo = getSemesterInfo($conn, 202701);

function formatSemDate(?string $d): string {
    if (empty($d) || $d === '0000-00-00') {
        return 'TBD';
    }
    $ts = strtotime($d);
    return $ts ? date('M j, Y', $ts) : htmlspecialchars($d, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard | Lucid University</title>
    <!-- Minimal Clean Bootstrap 5.3 CSS & Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        <?= getProfileHoverCardStyles() ?>
    </style>
</head>
<body class="bg-light">

    <!-- Unified Student Top Navigation -->
    <?= renderStudentNavbar($conn, $student_id, 'dashboard.php') ?>

    <div class="container mb-5">

        <!-- Prerequisite Auto-Drop Enforcement Banner -->
        <?php if (!empty($notices)): ?>
            <div class="alert alert-danger border-2 p-3 mb-4 shadow-sm" role="alert">
                <h5 class="alert-heading fw-bold mb-2">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i>Prerequisite Enforcement Notice
                </h5>
                <ul class="mb-0">
                    <?php foreach ($notices as $notice): ?>
                        <li><?= htmlspecialchars($notice, ENT_QUOTES, 'UTF-8') ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <!-- Simplified Active Holds Banner -->
        <?php if ($has_active_holds): ?>
            <div class="alert alert-danger border-2 p-3 mb-4 shadow-sm d-flex flex-wrap justify-content-between align-items-center gap-2" role="alert">
                <div>
                    <h5 class="alert-heading fw-bold mb-1">
                        <i class="bi bi-slash-circle-fill me-2"></i>Attention: Active Account Holds
                    </h5>
                    <div class="mb-0">
                        Attention: You currently have <strong><?= count($holds) ?></strong> active hold(s) on your account preventing registration.
                    </div>
                </div>
                <a href="holds.php" class="btn btn-danger btn-sm text-nowrap">View Account Holds &rarr;</a>
            </div>
        <?php endif; ?>

        <!-- Welcome Profile Card -->
        <div class="card shadow-sm mb-4 border-0">
            <div class="card-body p-4 bg-white rounded">
                <div class="row align-items-center">
                    <div class="col-lg-8">
                        <h2 class="fw-bold mb-1">
                            Welcome back, <?= htmlspecialchars($student_full_name, ENT_QUOTES, 'UTF-8') ?>!
                        </h2>
                        <p class="text-secondary mb-3">
                            Student ID: <strong><?= (int)$student_id ?></strong> &bull; 
                            Department of <?= htmlspecialchars($department_name, ENT_QUOTES, 'UTF-8') ?>
                        </p>
                        <div class="d-flex flex-wrap gap-2 align-items-center">
                            <span class="badge text-bg-primary fs-6 py-2 px-3">
                                Class Year: <?= htmlspecialchars($class_year, ENT_QUOTES, 'UTF-8') ?>
                            </span>
                            <span class="badge text-bg-secondary fs-6 py-2 px-3">
                                Level: <?= htmlspecialchars($student_type, ENT_QUOTES, 'UTF-8') ?> (<?= htmlspecialchars($attendance_type, ENT_QUOTES, 'UTF-8') ?>)
                            </span>
                            <span class="badge text-bg-light border fs-6 py-2 px-3">
                                Major: <?= htmlspecialchars($primary_major, ENT_QUOTES, 'UTF-8') ?>
                            </span>
                            <?php if ($secondary_major): ?>
                                <span class="badge text-bg-light border fs-6 py-2 px-3">
                                    2nd Major: <?= htmlspecialchars($secondary_major, ENT_QUOTES, 'UTF-8') ?>
                                </span>
                            <?php endif; ?>
                            <?php if ($minor_name !== 'None'): ?>
                                <span class="badge text-bg-light border fs-6 py-2 px-3">
                                    Minor: <?= htmlspecialchars($minor_name, ENT_QUOTES, 'UTF-8') ?>
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="col-lg-4 text-lg-end mt-3 mt-lg-0 d-flex flex-wrap flex-lg-column gap-2 justify-content-lg-center">
                        <a href="register.php" class="btn btn-primary px-3 py-2 text-nowrap shadow-sm">
                            <i class="bi bi-pencil-square me-1"></i> Spring 2027 Registration &rarr;
                        </a>
                        <a href="majors_minors.php" class="btn btn-outline-primary px-3 py-2 text-nowrap shadow-sm">
                            <i class="bi bi-mortarboard me-1"></i> Declare / View Majors &amp; Minors &rarr;
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Academic Metrics -->
        <div class="row g-3 mb-4">
            <div class="col-sm-6 col-lg-3">
                <div class="card shadow-sm h-100 border-0">
                    <div class="card-body">
                        <div class="text-muted small text-uppercase fw-semibold">Cumulative GPA</div>
                        <div class="fs-2 fw-bold text-dark my-1"><?= number_format($cum_gpa, 2) ?></div>
                        <div class="small text-muted">4.00 Grade Point Scale</div>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-lg-3">
                <div class="card shadow-sm h-100 border-0">
                    <div class="card-body">
                        <div class="text-muted small text-uppercase fw-semibold">Earned Credits</div>
                        <div class="fs-2 fw-bold text-dark my-1"><?= (int)$total_credits ?></div>
                        <div class="small text-muted">Institutional credits earned</div>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-lg-3">
                <div class="card shadow-sm h-100 border-0">
                    <div class="card-body">
                        <div class="text-muted small text-uppercase fw-semibold">Fall 2026 (Current)</div>
                        <div class="fs-2 fw-bold text-primary my-1"><?= count($fallCourses) ?> Courses</div>
                        <div class="small text-muted"><?= (int)$fallCredits ?> credits in progress</div>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-lg-3">
                <div class="card shadow-sm h-100 border-0">
                    <div class="card-body">
                        <div class="text-muted small text-uppercase fw-semibold">Spring 2027 (Next)</div>
                        <div class="fs-2 fw-bold text-success my-1"><?= count($springCourses) ?> Registered</div>
                        <div class="small text-muted"><?= (int)$springCredits ?> credits scheduled</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Dual-Semester Academic Deadlines & Calendar -->
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0 fw-bold">
                    <i class="bi bi-calendar3 me-2 text-primary"></i>Dual-Semester Academic Deadlines &amp; Calendar
                </h5>
                <span class="badge bg-secondary">Academic Year 2026 &ndash; 2027</span>
            </div>
            <div class="card-body p-4">
                <div class="row g-4">
                    <!-- Fall 2026 Term Column -->
                    <div class="col-lg-6">
                        <div class="border rounded p-3 h-100 bg-white shadow-sm">
                            <div class="d-flex justify-content-between align-items-center mb-3 pb-2 border-bottom">
                                <h6 class="fw-bold text-dark mb-0">
                                    <i class="bi bi-calendar-event me-2 text-primary"></i>Fall 2026 (Current Term)
                                </h6>
                                <span class="badge bg-primary">Term ID: 202608</span>
                            </div>
                            <?php if ($fallInfo): ?>
                                <ul class="list-group list-group-flush small">
                                    <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                                        <span class="text-muted fw-semibold">Term Dates:</span>
                                        <span class="fw-bold text-dark">
                                            <?= formatSemDate($fallInfo['Start_Date']) ?> &ndash; <?= formatSemDate($fallInfo['End_Date']) ?>
                                        </span>
                                    </li>
                                    <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                                        <span class="text-muted fw-semibold">Last Day to Add / Register:</span>
                                        <div>
                                            <span class="fw-bold text-dark me-2"><?= formatSemDate($fallInfo['Last_Day_Of_Registration']) ?></span>
                                            <?php if (!empty($fallInfo['is_reg_open'])): ?>
                                                <span class="badge bg-success">Open</span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">Closed</span>
                                            <?php endif; ?>
                                        </div>
                                    </li>
                                    <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                                        <span class="text-muted fw-semibold">Last Day to Drop Course:</span>
                                        <div>
                                            <span class="fw-bold text-dark me-2"><?= formatSemDate($fallInfo['Last_Day_Of_Dropping_Course']) ?></span>
                                            <?php if (!empty($fallInfo['is_drop_open'])): ?>
                                                <span class="badge bg-warning text-dark">Open</span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">Closed</span>
                                            <?php endif; ?>
                                        </div>
                                    </li>
                                    <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                                        <span class="text-muted fw-semibold">Midterm Grades Due:</span>
                                        <span class="fw-bold text-dark"><?= formatSemDate($fallInfo['Date_Of_Midterm_Grade_Recording']) ?></span>
                                    </li>
                                    <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                                        <span class="text-muted fw-semibold">Final Grades Due:</span>
                                        <span class="fw-bold text-dark"><?= formatSemDate($fallInfo['Date_Of_Final_Grades_Recording']) ?></span>
                                    </li>
                                </ul>
                            <?php else: ?>
                                <p class="text-muted small mb-0">Fall 2026 semester calendar data unavailable.</p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Spring 2027 Term Column -->
                    <div class="col-lg-6">
                        <div class="border rounded p-3 h-100 bg-white shadow-sm">
                            <div class="d-flex justify-content-between align-items-center mb-3 pb-2 border-bottom">
                                <h6 class="fw-bold text-dark mb-0">
                                    <i class="bi bi-calendar-check me-2 text-success"></i>Spring 2027 (Upcoming Term)
                                </h6>
                                <span class="badge bg-success">Term ID: 202701</span>
                            </div>
                            <?php if ($springInfo): ?>
                                <ul class="list-group list-group-flush small">
                                    <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                                        <span class="text-muted fw-semibold">Term Dates:</span>
                                        <span class="fw-bold text-dark">
                                            <?= formatSemDate($springInfo['Start_Date']) ?> &ndash; <?= formatSemDate($springInfo['End_Date']) ?>
                                        </span>
                                    </li>
                                    <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                                        <span class="text-muted fw-semibold">Last Day to Add / Register:</span>
                                        <div>
                                            <span class="fw-bold text-dark me-2"><?= formatSemDate($springInfo['Last_Day_Of_Registration']) ?></span>
                                            <?php if (!empty($springInfo['is_reg_open'])): ?>
                                                <span class="badge bg-success">Registration Open</span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">Closed</span>
                                            <?php endif; ?>
                                        </div>
                                    </li>
                                    <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                                        <span class="text-muted fw-semibold">Last Day to Drop Course:</span>
                                        <div>
                                            <span class="fw-bold text-dark me-2"><?= formatSemDate($springInfo['Last_Day_Of_Dropping_Course']) ?></span>
                                            <?php if (!empty($springInfo['is_drop_open'])): ?>
                                                <span class="badge bg-warning text-dark">Drop Window Open</span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">Closed</span>
                                            <?php endif; ?>
                                        </div>
                                    </li>
                                    <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                                        <span class="text-muted fw-semibold">Midterm Grades Due:</span>
                                        <span class="fw-bold text-dark"><?= formatSemDate($springInfo['Date_Of_Midterm_Grade_Recording']) ?></span>
                                    </li>
                                    <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                                        <span class="text-muted fw-semibold">Final Grades Due:</span>
                                        <span class="fw-bold text-dark"><?= formatSemDate($springInfo['Date_Of_Final_Grades_Recording']) ?></span>
                                    </li>
                                </ul>
                            <?php else: ?>
                                <p class="text-muted small mb-0">Spring 2027 semester calendar data unavailable.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Student Portal Action Navigation Grid -->
        <h4 class="fw-bold mb-3">Student Portal Actions</h4>
        <div class="row g-3 mb-4">
            <!-- 1. Personal Schedule -->
            <div class="col-md-6 col-lg-4">
                <div class="card shadow-sm h-100 border-0">
                    <div class="card-body d-flex flex-column">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <h5 class="card-title fw-bold mb-0">
                                <i class="bi bi-clock me-1 text-primary"></i>My Personal Schedule
                            </h5>
                            <span class="badge text-bg-primary">Fall &bull; Spring</span>
                        </div>
                        <p class="card-text text-secondary small flex-grow-1">
                            View your weekly class schedule, meeting times, building/room locations, and instructors for Fall 2026 and Spring 2027.
                        </p>
                        <a href="schedule.php" class="btn btn-outline-primary btn-sm mt-2">
                            View My Schedule &rarr;
                        </a>
                    </div>
                </div>
            </div>

            <!-- 2. Master Schedule -->
            <div class="col-md-6 col-lg-4">
                <div class="card shadow-sm h-100 border-0">
                    <div class="card-body d-flex flex-column">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <h5 class="card-title fw-bold mb-0">
                                <i class="bi bi-search me-1 text-info"></i>Master Schedule Search
                            </h5>
                            <span class="badge text-bg-info text-dark">University-Wide</span>
                        </div>
                        <p class="card-text text-secondary small flex-grow-1">
                            Search all university course sections across departments, day/time slots, faculty members, and live seat counts.
                        </p>
                        <a href="master_schedule.php" class="btn btn-outline-info btn-sm mt-2 text-dark">
                            Search Master Schedule &rarr;
                        </a>
                    </div>
                </div>
            </div>

            <!-- 3. Course Registration -->
            <div class="col-md-6 col-lg-4">
                <div class="card shadow-sm h-100 border-0">
                    <div class="card-body d-flex flex-column">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <h5 class="card-title fw-bold mb-0">
                                <i class="bi bi-pencil-square me-1 text-success"></i>Register / Add-Drop
                            </h5>
                            <span class="badge bg-success">Spring 2027</span>
                        </div>
                        <p class="card-text text-secondary small flex-grow-1">
                            Register for Spring 2027 courses, browse open catalog sections, monitor credit caps, and drop registered sections.
                        </p>
                        <a href="register.php" class="btn btn-outline-success btn-sm mt-2">
                            Add / Drop Classes &rarr;
                        </a>
                    </div>
                </div>
            </div>

            <!-- 4. Unofficial Transcript -->
            <div class="col-md-6 col-lg-4">
                <div class="card shadow-sm h-100 border-0">
                    <div class="card-body d-flex flex-column">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <h5 class="card-title fw-bold mb-0">
                                <i class="bi bi-file-earmark-text me-1 text-secondary"></i>Unofficial Transcript
                            </h5>
                            <span class="badge text-bg-secondary">History</span>
                        </div>
                        <p class="card-text text-secondary small flex-grow-1">
                            Review your completed academic coursework, semester-by-semester grades, term GPAs, and cumulative GPA records.
                        </p>
                        <a href="transcript.php" class="btn btn-outline-secondary btn-sm mt-2">
                            View Transcript &rarr;
                        </a>
                    </div>
                </div>
            </div>

            <!-- 5. Degree Audit -->
            <div class="col-md-6 col-lg-4">
                <div class="card shadow-sm h-100 border-0">
                    <div class="card-body d-flex flex-column">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <h5 class="card-title fw-bold mb-0">
                                <i class="bi bi-speedometer2 me-1 text-dark"></i>Academic Degree Audit
                            </h5>
                            <span class="badge text-bg-dark">Progress</span>
                        </div>
                        <p class="card-text text-secondary small flex-grow-1">
                            Track your 120-credit progress bar, departmental curriculum groupings, completed prerequisites, and elective credits.
                        </p>
                        <a href="degree_audit.php" class="btn btn-outline-dark btn-sm mt-2">
                            View Degree Audit &rarr;
                        </a>
                    </div>
                </div>
            </div>

            <!-- 6. Majors & Minors -->
            <div class="col-md-6 col-lg-4">
                <div class="card shadow-sm h-100 border-0">
                    <div class="card-body d-flex flex-column">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <h5 class="card-title fw-bold mb-0">
                                <i class="bi bi-mortarboard me-1 text-primary"></i>Majors &amp; Minors
                            </h5>
                            <span class="badge bg-primary">Curriculum</span>
                        </div>
                        <p class="card-text text-secondary small flex-grow-1">
                            Declare or change your primary major, secondary major, or minor within degree-level limits and view required courses.
                        </p>
                        <a href="majors_minors.php" class="btn btn-outline-primary btn-sm mt-2">
                            Declare Majors &amp; Minors &rarr;
                        </a>
                    </div>
                </div>
            </div>

            <!-- 7. Account Holds -->
            <div class="col-md-6 col-lg-4">
                <div class="card shadow-sm h-100 border-0">
                    <div class="card-body d-flex flex-column">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <h5 class="card-title fw-bold mb-0">
                                <i class="bi bi-shield-exclamation me-1 text-danger"></i>View Account Holds
                            </h5>
                            <span class="badge <?= $has_active_holds ? 'bg-danger' : 'bg-success' ?>"><?= count($holds) ?> Active</span>
                        </div>
                        <p class="card-text text-secondary small flex-grow-1">
                            Check for administrative, bursar, or advising restrictions on your student account and view clearance instructions.
                        </p>
                        <a href="holds.php" class="btn btn-outline-danger btn-sm mt-2">
                            View Account Holds &rarr;
                        </a>
                    </div>
                </div>
            </div>
        </div>

    </div>

    <!-- Bootstrap 5 JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
