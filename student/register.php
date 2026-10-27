<?php
/**
 * Lucid University Course Registration System
 * Student Course Registration Portal - Spring 2027 (Semester_ID = 202701)
 *
 * Clean, lightweight PHP/HTML interface with standard Bootstrap styling
 * and pure HTML form submissions (zero custom JavaScript).
 */

require_once 'student_guard.php';
$student_id = (int)$userID;

require_once '../db_connect.php';
require_once 'academic_helpers.php';
require_once '../profile_hover_card.php';

// 1. Fetch Student Profile & Academic Classification
$studStmt = $conn->prepare("
    SELECT u.User_FName, u.User_MName, u.User_LName,
           s.Student_ID, s.Student_Type, s.Student_Year, s.Credits_Earned,
           COALESCE(ug.Undergrad_Type, g.Grad_Type, 'Full-Time') AS Attendance_Type
    FROM `USER` u
    JOIN STUDENT s ON u.User_ID = s.Student_ID
    LEFT JOIN UNDERGRADUATE_STUDENT ug ON s.Student_ID = ug.Student_ID
    LEFT JOIN GRADUATE_STUDENT g ON s.Student_ID = g.Student_ID
    WHERE s.Student_ID = ?
");
$studStmt->bind_param("i", $student_id);
$studStmt->execute();
$studRes = $studStmt->get_result();
$student = $studRes->fetch_assoc();
$studStmt->close();

if (!$student) {
    die("Student profile record not found.");
}

$student_full_name = trim($student['User_FName'] . ' ' . ($student['User_MName'] ? $student['User_MName'] . ' ' : '') . $student['User_LName']);
$student_type      = $student['Student_Type'];
$attendance_type   = $student['Attendance_Type'] ?: 'Full-Time';
$max_credits       = (strcasecmp($attendance_type, 'Part-Time') === 0) ? 8 : 16;

// 2. Fetch Active Account Holds
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

// 3. Fetch Semesters where course sections exist AND registration is not past Last_Day_Of_Registration
$semStmt = $conn->prepare("
    SELECT DISTINCT s.Semester_ID, s.Semester_Name, s.Semester_Year,
           ir.Last_Day_Of_Registration, ir.Last_Day_Of_Dropping_Course,
           (CURRENT_DATE() <= ir.Last_Day_Of_Registration) AS is_reg_open,
           (CURRENT_DATE() <= ir.Last_Day_Of_Dropping_Course) AS is_drop_open
    FROM SEMESTER s
    JOIN INFORMATION_RELATION ir ON s.Semester_ID = ir.Semester_ID
    JOIN COURSE_SECTIONS cs ON s.Semester_ID = cs.Semester_ID
    WHERE CURRENT_DATE() <= ir.Last_Day_Of_Registration
    ORDER BY s.Semester_ID ASC
");
$semStmt->execute();
$semRes = $semStmt->get_result();
$availableSemesters = [];
while ($sRow = $semRes->fetch_assoc()) {
    $availableSemesters[(int)$sRow['Semester_ID']] = $sRow;
}
$semStmt->close();

// Determine selected semester
$selected_sem_id = isset($_GET['semester']) ? filter_var($_GET['semester'], FILTER_VALIDATE_INT) : null;
if (!$selected_sem_id || !isset($availableSemesters[$selected_sem_id])) {
    if (!empty($availableSemesters)) {
        // Default to the first open semester with existing sections
        $firstSem = reset($availableSemesters);
        $selected_sem_id = (int)$firstSem['Semester_ID'];
    } else {
        $selected_sem_id = 202701;
    }
}

// Fetch registration window for the selected semester
$currentSem = $availableSemesters[$selected_sem_id] ?? null;
if (!$currentSem) {
    $singleWinStmt = $conn->prepare("
        SELECT s.Semester_ID, s.Semester_Name, s.Semester_Year,
               ir.Last_Day_Of_Registration, ir.Last_Day_Of_Dropping_Course,
               (CURRENT_DATE() <= ir.Last_Day_Of_Registration) AS is_reg_open,
               (CURRENT_DATE() <= ir.Last_Day_Of_Dropping_Course) AS is_drop_open
        FROM SEMESTER s
        LEFT JOIN INFORMATION_RELATION ir ON s.Semester_ID = ir.Semester_ID
        WHERE s.Semester_ID = ?
    ");
    $singleWinStmt->bind_param("i", $selected_sem_id);
    $singleWinStmt->execute();
    $currentSem = $singleWinStmt->get_result()->fetch_assoc();
    $singleWinStmt->close();
}

$semester_label = $currentSem ? ($currentSem['Semester_Name'] . ' ' . $currentSem['Semester_Year']) : "Spring 2027";
$is_reg_open    = $currentSem ? (bool)$currentSem['is_reg_open'] : false;
$is_drop_open   = $currentSem ? (bool)$currentSem['is_drop_open'] : false;

// 4. Fetch Currently Enrolled Courses for the Selected Semester via reusable helper
$scheduleData = getStudentScheduleBySemester($conn, $student_id, $selected_sem_id);
$enrolledCourses = $scheduleData['courses'];
$enrolledCrns = $scheduleData['crns'];
$enrolledCourseIds = $scheduleData['course_ids'];
$total_enrolled_credits = $scheduleData['total_credits'];

// 5. Fetch Filter Options
$filterOptions = getMasterScheduleFilterOptions($conn);
$departments   = $filterOptions['departments'];
$timeSlots     = $filterOptions['time_slots'];

// 6. Master Catalog Browser Query via searchMasterSchedule()
$filterDept       = isset($_GET['dept']) ? filter_var($_GET['dept'], FILTER_VALIDATE_INT) : null;
$filterSearch     = isset($_GET['search']) ? trim($_GET['search']) : '';
$filterCrn        = isset($_GET['crn']) ? filter_var($_GET['crn'], FILTER_VALIDATE_INT) : null;
$filterSection    = (isset($_GET['section']) && trim($_GET['section']) !== '') ? trim($_GET['section']) : null;
$filterTimeSlot   = isset($_GET['time_slot_id']) ? filter_var($_GET['time_slot_id'], FILTER_VALIDATE_INT) : null;
$filterInstructor = isset($_GET['instructor']) ? trim($_GET['instructor']) : '';
$filterCredits    = isset($_GET['credits']) ? (trim($_GET['credits']) !== '' ? filter_var($_GET['credits'], FILTER_VALIDATE_INT) : '') : 4;
$filterOpenOnly   = !empty($_GET['open_only']);

$searchFilters = [
    'semester_id'  => $selected_sem_id,
    'dept'         => $filterDept,
    'search'       => $filterSearch,
    'crn'          => $filterCrn,
    'section'      => $filterSection,
    'time_slot_id' => $filterTimeSlot,
    'instructor'   => $filterInstructor,
    'credits'      => ($filterCredits !== '' ? $filterCredits : null),
    'open_only'    => $filterOpenOnly,
];

$catalogSections = searchMasterSchedule($conn, $searchFilters);

// Retrieve and clear flash messages
$flashError = $_SESSION['flash_error'] ?? null;
$flashSuccess = $_SESSION['flash_success'] ?? null;
unset($_SESSION['flash_error'], $_SESSION['flash_success']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Spring 2027 Registration | Lucid University</title>
    <!-- Minimal Clean Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        <?= getProfileHoverCardStyles() ?>
    </style>
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
                        <a class="nav-link active" aria-current="page" href="register.php">Register / Add-Drop</a>
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
                    <?= getUserNavbarProfileHtml($conn, $student_id, 'Student') ?>
                    <form action="../logout.php" method="POST" class="m-0">
                        <button type="submit" class="btn btn-outline-light btn-sm">Log Out</button>
                    </form>
                </div>
            </div>
        </div>
    </nav>

    <div class="container mb-5">

        <!-- Flash Messages -->
        <?php if ($flashSuccess): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <strong>Success:</strong> <?= htmlspecialchars($flashSuccess, ENT_QUOTES, 'UTF-8') ?>
            </div>
        <?php endif; ?>

        <?php if ($flashError): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <strong>Registration Notice:</strong> <?= htmlspecialchars($flashError, ENT_QUOTES, 'UTF-8') ?>
            </div>
        <?php endif; ?>

        <!-- Active Holds High-Visibility Banner -->
        <?php if ($has_active_holds): ?>
            <div class="alert alert-danger border-2 p-3 mb-4" role="alert">
                <h5 class="alert-heading fw-bold mb-1">Registration Blocked: Active Account Holds</h5>
                <p class="mb-2">Your account currently has active holds preventing new course registrations. Please contact the registrar or bursar office to resolve them:</p>
                <ul class="mb-0">
                    <?php foreach ($holds as $h): ?>
                        <li><strong><?= htmlspecialchars($h['Hold_Type'], ENT_QUOTES, 'UTF-8') ?> Hold</strong> (Date placed: <?= htmlspecialchars($h['Hold_Date'], ENT_QUOTES, 'UTF-8') ?>)</li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <!-- Student Status & Credit Counter Dashboard -->
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-white py-3">
                <h5 class="mb-0 fw-bold">Student Status &amp; <?= htmlspecialchars($semester_label, ENT_QUOTES, 'UTF-8') ?> Registration Summary</h5>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-4">
                        <div class="border rounded p-3 h-100 bg-white">
                            <span class="text-muted small d-block">Student Info</span>
                            <div class="fw-bold fs-5"><?= htmlspecialchars($student_full_name, ENT_QUOTES, 'UTF-8') ?></div>
                            <div class="text-secondary small">ID: <?= htmlspecialchars((string)$student_id, ENT_QUOTES, 'UTF-8') ?></div>
                            <div class="small mt-1">
                                Status: <strong><?= htmlspecialchars($student_type, ENT_QUOTES, 'UTF-8') ?> (<?= htmlspecialchars($attendance_type, ENT_QUOTES, 'UTF-8') ?>)</strong>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="border rounded p-3 h-100 bg-white">
                            <span class="text-muted small d-block"><?= htmlspecialchars($semester_label, ENT_QUOTES, 'UTF-8') ?> Credit Counter</span>
                            <div class="fs-4 fw-bold">
                                <?= $total_enrolled_credits ?> / <?= $max_credits ?> Credits
                            </div>
                            <?php $pct = min(100, round(($total_enrolled_credits / max(1, $max_credits)) * 100)); ?>
                            <div class="progress my-2" style="height: 10px;">
                                <div class="progress-bar <?= ($pct >= 100) ? 'bg-danger' : 'bg-primary' ?>" style="width: <?= $pct ?>%;"></div>
                            </div>
                            <div class="text-muted small">Max allowed for <?= htmlspecialchars($attendance_type, ENT_QUOTES, 'UTF-8') ?>: <?= $max_credits ?> credits</div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="border rounded p-3 h-100 bg-white">
                            <span class="text-muted small d-block">Registration Window</span>
                            <div class="fw-bold"><?= htmlspecialchars($semester_label, ENT_QUOTES, 'UTF-8') ?> Term (<?= $selected_sem_id ?>)</div>
                            <div class="small mt-1">Status: 
                                <?php if ($is_reg_open): ?>
                                    <span class="badge bg-success">Registration Open</span>
                                <?php else: ?>
                                    <span class="badge bg-danger">Registration Closed</span>
                                <?php endif; ?>
                            </div>
                            <div class="text-muted small mt-1">Last Day to Register: <?= htmlspecialchars($currentSem['Last_Day_Of_Registration'] ?? 'N/A', ENT_QUOTES, 'UTF-8') ?></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Current Enrolled Courses Table -->
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <h5 class="mb-0 fw-bold">Current <?= htmlspecialchars($semester_label, ENT_QUOTES, 'UTF-8') ?> Enrolled Courses</h5>
                <span class="badge bg-primary"><?= count($enrolledCourses) ?> Registered</span>
            </div>
            <div class="card-body p-0">
                <?php if (empty($enrolledCourses)): ?>
                    <div class="p-4 text-center text-muted">
                        You are not currently registered for any courses in <?= htmlspecialchars($semester_label, ENT_QUOTES, 'UTF-8') ?>.
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover table-striped mb-0 align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>CRN</th>
                                    <th>Course ID</th>
                                    <th>Section</th>
                                    <th>Course Name</th>
                                    <th>Credits</th>
                                    <th>Day / Period</th>
                                    <th>Building &amp; Room</th>
                                    <th>Instructor</th>
                                    <th class="text-end pe-3">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($enrolledCourses as $sec): ?>
                                    <tr>
                                        <td><strong><?= (int)$sec['CRN'] ?></strong></td>
                                        <td><?= htmlspecialchars($sec['Course_ID'], ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><?= htmlspecialchars((string)$sec['Section_Number'], ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><?= htmlspecialchars($sec['Course_Name'], ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><?= htmlspecialchars((string)$sec['Course_Credits'], ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><?= formatSchedule($sec['Time_Slot_Day'], $sec['Start_Time'], $sec['End_Time']) ?></td>
                                        <td><?= htmlspecialchars($sec['Building_Name'] . ' ' . $sec['Room_NO'], ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><?= htmlspecialchars($sec['Instructor_Name'], ENT_QUOTES, 'UTF-8') ?></td>
                                        <td class="text-end pe-3">
                                            <!-- Plain HTML POST Form to drop_action.php -->
                                            <form method="POST" action="drop_action.php" class="d-inline">
                                                <input type="hidden" name="crn" value="<?= (int)$sec['CRN'] ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-danger" <?= !$is_drop_open ? 'disabled' : '' ?>>
                                                    Drop
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Master Catalog Browser -->
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-white py-3">
                <h5 class="mb-0 fw-bold">Master Course Catalog Browser &bull; <?= htmlspecialchars($semester_label, ENT_QUOTES, 'UTF-8') ?></h5>
            </div>
            
            <!-- Filter Controls Form -->
            <div class="p-3 border-bottom bg-white">
                <form method="GET" action="register.php">
                    <div class="row g-2 align-items-end">
                        <?php if (!empty($availableSemesters)): ?>
                            <div class="col-md-2">
                                <label for="filter-semester" class="form-label small fw-bold">Semester</label>
                                <select name="semester" id="filter-semester" class="form-select form-select-sm" onchange="this.form.submit()">
                                    <?php foreach ($availableSemesters as $semId => $semData): ?>
                                        <option value="<?= $semId ?>" <?= ($selected_sem_id === $semId) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($semData['Semester_Name'] . ' ' . $semData['Semester_Year'], ENT_QUOTES, 'UTF-8') ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        <?php endif; ?>
                        <div class="col-md-3">
                            <label for="filter-dept" class="form-label small fw-bold">Department</label>
                            <select name="dept" id="filter-dept" class="form-select form-select-sm">
                                <option value="">All Departments</option>
                                <?php foreach ($departments as $dept): ?>
                                    <option value="<?= (int)$dept['Department_ID'] ?>" <?= ($filterDept === (int)$dept['Department_ID']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($dept['Department_Name'], ENT_QUOTES, 'UTF-8') ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label for="filter-search" class="form-label small fw-bold">Course Name / ID</label>
                            <input type="text" name="search" id="filter-search" class="form-control form-control-sm" placeholder="e.g. CS101, Algorithms" value="<?= htmlspecialchars($filterSearch, ENT_QUOTES, 'UTF-8') ?>">
                        </div>
                        <div class="col-md-2">
                            <label for="filter-crn" class="form-label small fw-bold">CRN</label>
                            <input type="number" name="crn" id="filter-crn" class="form-control form-control-sm" placeholder="e.g. 1001" value="<?= $filterCrn ? (int)$filterCrn : '' ?>">
                        </div>
                        <div class="col-md-2">
                            <label for="filter-section" class="form-label small fw-bold">Section #</label>
                            <input type="number" name="section" id="filter-section" class="form-control form-control-sm" placeholder="e.g. 1" value="<?= ($filterSection !== null) ? (int)$filterSection : '' ?>">
                        </div>
                        <div class="col-md-2">
                            <label for="filter-credits" class="form-label small fw-bold">Credits</label>
                            <input type="number" name="credits" id="filter-credits" min="1" max="6" class="form-control form-control-sm" value="<?= htmlspecialchars((string)($filterCredits ?? 4)) ?>">
                        </div>
                        <div class="col-md-3">
                            <label for="filter-time-slot" class="form-label small fw-bold">Day / Period</label>
                            <select name="time_slot_id" id="filter-time-slot" class="form-select form-select-sm">
                                <option value="">Any Time Slot</option>
                                <?php foreach ($timeSlots as $ts): ?>
                                    <option value="<?= (int)$ts['Time_Slot_ID'] ?>" <?= ($filterTimeSlot === (int)$ts['Time_Slot_ID']) ? 'selected' : '' ?>>
                                        <?= formatSchedule($ts['Time_Slot_Day'], $ts['Start_Time'], $ts['End_Time']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label for="filter-instructor" class="form-label small fw-bold">Instructor</label>
                            <input type="text" name="instructor" id="filter-instructor" class="form-control form-control-sm" placeholder="e.g. Knuth, Lovelace" value="<?= htmlspecialchars($filterInstructor, ENT_QUOTES, 'UTF-8') ?>">
                        </div>
                        <div class="col-md-2 pb-1">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="open_only" value="1" id="filter-open-only" <?= $filterOpenOnly ? 'checked' : '' ?>>
                                <label class="form-check-label small fw-bold" for="filter-open-only">
                                    Open seats only
                                </label>
                            </div>
                        </div>
                        <div class="col-md-4 d-flex gap-2">
                            <button type="submit" class="btn btn-sm btn-primary flex-grow-1">Filter Catalog</button>
                            <?php if ($filterDept || $filterSearch !== '' || $filterCrn || $filterSection !== null || $filterTimeSlot || $filterInstructor !== '' || ($filterCredits !== '' && $filterCredits != 4) || $filterOpenOnly || (isset($_GET['semester']) && $_GET['semester'] != $selected_sem_id)): ?>
                                <a href="register.php?semester=<?= (int)$selected_sem_id ?>" class="btn btn-sm btn-outline-secondary">Reset</a>
                            <?php endif; ?>
                            <a href="master_schedule.php?semester=<?= (int)$selected_sem_id ?>" class="btn btn-sm btn-outline-info">Master Schedule</a>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Catalog Results Table -->
            <div class="card-body p-0">
                <?php if (empty($catalogSections)): ?>
                    <div class="p-4 text-center text-muted">
                        No course sections match your search criteria.
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
                                    <th>Credits</th>
                                    <th>Instructor</th>
                                    <th>Day / Time</th>
                                    <th>Room</th>
                                    <th>Available Seats</th>
                                    <th class="text-end pe-3">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($catalogSections as $sec): 
                                    $secCrn = (int)$sec['CRN'];
                                    $secCourseId = $sec['Course_ID'];
                                    $seatsAvailable = (int)$sec['Available_seats'];
                                    $isEnrolled = in_array($secCrn, $enrolledCrns, true);
                                    $isEnrolledInCourse = in_array($secCourseId, $enrolledCourseIds, true);
                                ?>
                                    <tr>
                                        <td><strong><?= $secCrn ?></strong></td>
                                        <td><?= htmlspecialchars($secCourseId, ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><?= htmlspecialchars((string)$sec['Section_Number'], ENT_QUOTES, 'UTF-8') ?></td>
                                        <td>
                                            <span class="fw-semibold"><?= htmlspecialchars($sec['Course_Name'], ENT_QUOTES, 'UTF-8') ?></span>
                                            <span class="badge bg-secondary-subtle text-secondary small ms-1"><?= htmlspecialchars($sec['Course_Type'], ENT_QUOTES, 'UTF-8') ?></span>
                                            <div class="mt-1">
                                                <button type="button" class="btn btn-outline-info btn-sm py-0 px-2" style="font-size: 0.75rem;" data-bs-toggle="modal" data-bs-target="#prereqModal-<?= $secCrn ?>">
                                                    Prerequisites
                                                </button>
                                            </div>
                                        </td>
                                        <td><?= htmlspecialchars((string)$sec['Course_Credits'], ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><?= htmlspecialchars($sec['Instructor_Name'], ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><?= formatSchedule($sec['Time_Slot_Day'], $sec['Start_Time'], $sec['End_Time']) ?></td>
                                        <td><?= htmlspecialchars($sec['Building_Name'] . ' ' . $sec['Room_NO'], ENT_QUOTES, 'UTF-8') ?></td>
                                        <td>
                                            <?php if ($seatsAvailable > 0): ?>
                                                <span class="badge bg-success"><?= $seatsAvailable ?> seats</span>
                                            <?php else: ?>
                                                <span class="badge bg-danger">Full (0)</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end pe-3">
                                            <?php if ($isEnrolled): ?>
                                                <span class="badge bg-success">Enrolled</span>
                                            <?php elseif ($isEnrolledInCourse): ?>
                                                <span class="badge bg-secondary" title="Already enrolled in another section of this course">Enrolled (Other Sec)</span>
                                            <?php elseif ($has_active_holds): ?>
                                                <button type="button" class="btn btn-sm btn-secondary" disabled title="Registration blocked by active hold">Hold</button>
                                            <?php elseif (!$is_reg_open): ?>
                                                <button type="button" class="btn btn-sm btn-secondary" disabled>Closed</button>
                                            <?php elseif ($seatsAvailable <= 0): ?>
                                                <button type="button" class="btn btn-sm btn-danger" disabled>Full</button>
                                            <?php else: ?>
                                                <!-- Plain HTML POST Form to register_action.php -->
                                                <form method="POST" action="register_action.php" class="d-inline">
                                                    <input type="hidden" name="crn" value="<?= $secCrn ?>">
                                                    <button type="submit" class="btn btn-sm btn-primary">Register</button>
                                                </form>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <?php if (!empty($catalogSections)): ?>
            <?php foreach ($catalogSections as $sec): 
                $secCrn = (int)$sec['CRN'];
                $secCourseId = $sec['Course_ID'];
                $prereqs = $sec['prerequisites'] ?? [];
            ?>
                <!-- Prerequisites Modal for CRN <?= $secCrn ?> -->
                <div class="modal fade" id="prereqModal-<?= $secCrn ?>" tabindex="-1" aria-labelledby="prereqModalLabel-<?= $secCrn ?>" aria-hidden="true">
                    <div class="modal-dialog modal-dialog-centered">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title fs-6 fw-bold" id="prereqModalLabel-<?= $secCrn ?>">
                                    Prerequisites: <?= htmlspecialchars($secCourseId, ENT_QUOTES, 'UTF-8') ?> &ndash; <?= htmlspecialchars($sec['Course_Name'], ENT_QUOTES, 'UTF-8') ?>
                                </h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <?php if (!empty($prereqs)): ?>
                                    <p class="small text-muted mb-2">Required prerequisite course(s):</p>
                                    <ul class="list-group list-group-flush border rounded">
                                        <?php foreach ($prereqs as $pr): ?>
                                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                                <span>
                                                    <strong><?= htmlspecialchars($pr['Course_ID'], ENT_QUOTES, 'UTF-8') ?></strong>
                                                    &ndash; <?= htmlspecialchars($pr['Course_Name'], ENT_QUOTES, 'UTF-8') ?>
                                                </span>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php else: ?>
                                    <div class="alert alert-secondary mb-0 py-2 small">
                                        None &mdash; No prerequisites required for this course.
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="modal-footer py-2">
                                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>

    </div>

    <!-- Bootstrap 5 JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
