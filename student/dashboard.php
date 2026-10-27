<?php
/**
 * Lucid University Course Registration System
 * Student Main Portal Dashboard
 *
 * Clean Bootstrap 5.3 dashboard providing student profile overview,
 * quick academic metrics, active holds status, and direct navigation.
 */

require_once 'student_guard.php';
$student_id = (int)$userID;

require_once '../db_connect.php';
require_once 'academic_helpers.php';
require_once '../profile_hover_card.php';

// 1. Fetch Student Demographic & Academic Profile
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
$minor_name        = $profile['minor']['Minor_Name'] ?? 'None';

// 2. Fetch Transcript Totals
$transcript = getStudentTranscriptData($conn, $student_id);
$cum_gpa       = $transcript['cum_gpa'];
$total_credits = $transcript['total_credits'];

// 3. Fetch Active Account Holds
$holdStmt = $conn->prepare("
    SELECT h.Hold_Type, sh.Hold_Date
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

// 4. Fetch Enrolled Schedules for Current (Fall 2026) and Next (Spring 2027) Terms
$fallSchedule   = getStudentScheduleBySemester($conn, $student_id, 202608);
$springSchedule = getStudentScheduleBySemester($conn, $student_id, 202701);

$fallCourses   = $fallSchedule['courses'];
$fallCredits   = $fallSchedule['total_credits'];
$springCourses = $springSchedule['courses'];
$springCredits = $springSchedule['total_credits'];

// 5. Fetch Spring 2027 Registration Window
$springInfo = getSemesterInfo($conn, 202701);
$is_spring_reg_open = $springInfo ? (bool)$springInfo['is_reg_open'] : true;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard | Lucid University</title>
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
                        <a class="nav-link active" aria-current="page" href="dashboard.php">Dashboard</a>
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

        <!-- Simplified Active Holds Banner -->
        <?php if ($has_active_holds): ?>
            <div class="alert alert-danger border-2 p-3 mb-4 shadow-sm d-flex flex-wrap justify-content-between align-items-center gap-2" role="alert">
                <div>
                    <h5 class="alert-heading fw-bold mb-1">Attention: Active Account Holds</h5>
                    <div class="mb-0">
                        Attention: You currently have <strong><?= count($holds) ?></strong> active hold(s) on your account preventing registration.
                    </div>
                </div>
                <a href="holds.php" class="btn btn-danger btn-sm text-nowrap">View Account Holds</a>
            </div>
        <?php endif; ?>

        <!-- Welcome Profile Card -->
        <div class="card shadow-sm mb-4 border-0">
            <div class="card-body p-4 bg-white rounded">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <h2 class="fw-bold mb-1">
                            Welcome back, <?= getUserNavbarProfileHtml($conn, $student_id, 'Student', 'text-dark') ?>!
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
                                Enrollment Status: <?= htmlspecialchars($student_type, ENT_QUOTES, 'UTF-8') ?> (<?= htmlspecialchars($attendance_type, ENT_QUOTES, 'UTF-8') ?>)
                            </span>
                            <span class="badge text-bg-light border fs-6 py-2 px-3">
                                Major: <?= htmlspecialchars($primary_major, ENT_QUOTES, 'UTF-8') ?>
                            </span>
                            <?php if ($minor_name !== 'None'): ?>
                                <span class="badge text-bg-light border fs-6 py-2 px-3">
                                    Minor: <?= htmlspecialchars($minor_name, ENT_QUOTES, 'UTF-8') ?>
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="col-md-4 text-md-end mt-3 mt-md-0">
                        <a href="register.php" class="btn btn-primary px-3 py-2">
                            Spring 2027 Registration &rarr;
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

        <!-- Student Portal Action Navigation Grid -->
        <h4 class="fw-bold mb-3">Student Portal Actions</h4>
        <div class="row g-3 mb-4">
            <!-- 1. Personal Schedule -->
            <div class="col-md-6 col-lg-4">
                <div class="card shadow-sm h-100 border-0">
                    <div class="card-body d-flex flex-column">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <h5 class="card-title fw-bold mb-0">My Personal Schedule</h5>
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
                            <h5 class="card-title fw-bold mb-0">Master Schedule Search</h5>
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
                            <h5 class="card-title fw-bold mb-0">Register / Add-Drop</h5>
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
                            <h5 class="card-title fw-bold mb-0">Unofficial Academic Transcript</h5>
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
                            <h5 class="card-title fw-bold mb-0">Academic Degree Audit</h5>
                            <span class="badge text-bg-dark">Progress</span>
                        </div>
                        <p class="card-text text-secondary small flex-grow-1">
                            Track your graduation progress bar, declared major and minor requirements, completed prerequisites, and outstanding courses.
                        </p>
                        <a href="degree_audit.php" class="btn btn-outline-dark btn-sm mt-2">
                            View Degree Audit &rarr;
                        </a>
                    </div>
                </div>
            </div>

            <!-- 6. Account Holds -->
            <div class="col-md-6 col-lg-4">
                <div class="card shadow-sm h-100 border-0">
                    <div class="card-body d-flex flex-column">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <h5 class="card-title fw-bold mb-0">View Account Holds</h5>
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
