<?php
/**
 * Lucid University Course Registration System
 * Faculty Main Portal Dashboard
 *
 * Full-featured Bootstrap 5.3 dashboard providing teaching schedule overviews,
 * live section roster drill-downs, advisee tracking, grade submission, attendance recording, and profile hover card.
 */

require_once 'faculty_guard.php';
$faculty_id = (int)$userID;

require_once '../db_connect.php';
require_once __DIR__ . '/../includes/ui_helpers.php';
require_once __DIR__ . '/../includes/faculty_helpers.php';
require_once __DIR__ . '/../includes/schedule_helpers.php';
require_once __DIR__ . '/../includes/student_helpers.php';

// 1. Fetch Faculty Profile and Department Details
$faculty = getFacultyProfile($conn, $faculty_id);
if (!$faculty) {
    die("Faculty record not found.");
}

$faculty_full_name = $faculty['full_name'];
$faculty_rank      = $faculty['Rank'] ?? 'Instructor';
$faculty_type      = $faculty['Faculty_Type'] ?? 'Full-Time';
$faculty_office    = $faculty['Office_ID'] ?? 'TBA';
$faculty_dept      = $faculty['Department_Names'] ?? 'General Academics';
$faculty_specialty = $faculty['Specialty'] ?? 'General';

// 2. Fetch Fall 2026 and Spring 2027 Teaching Schedules
$fallSchedule   = getFacultyScheduleBySemester($conn, $faculty_id, 202608);
$springSchedule = getFacultyScheduleBySemester($conn, $faculty_id, 202701);
$fallSections   = $fallSchedule['sections'];
$springSections = $springSchedule['sections'];

// 3. Fetch Assigned Advisees
$advisees = getFacultyAdvisees($conn, $faculty_id);

// 4. Section Management Workspace (Roster, Attendance, Grades)
$roster_crn = isset($_GET['roster_crn']) ? filter_var($_GET['roster_crn'], FILTER_VALIDATE_INT) : null;
$rosterStudents = null;
$rosterSectionInfo = null;
$isCurrentSemester = false;
$activeTab = isset($_GET['tab']) && in_array($_GET['tab'], ['roster', 'attendance', 'grades'], true) ? $_GET['tab'] : 'roster';

if ($roster_crn) {
    // Modularity cleanup: Resolve section info directly from schedules without redundant SQL query
    foreach ($fallSections as $sec) {
        if ((int)$sec['CRN'] === $roster_crn) {
            $rosterSectionInfo = $sec;
            $isCurrentSemester = true;
            break;
        }
    }
    if (!$rosterSectionInfo) {
        foreach ($springSections as $sec) {
            if ((int)$sec['CRN'] === $roster_crn) {
                $rosterSectionInfo = $sec;
                $isCurrentSemester = false;
                break;
            }
        }
    }

    if ($rosterSectionInfo) {
        $rosterStudents = getSectionRoster($conn, $roster_crn, $faculty_id);

        // Attendance & Grades are restricted exclusively to Current Semester (202608)
        if (!$isCurrentSemester && $activeTab !== 'roster') {
            $activeTab = 'roster';
        }

        // Fetch Information Relation for grade windows if current semester
        $infoRel = null;
        $midtermOpen = false;
        $finalOpen = false;
        $meetingMeta = null;
        $attendanceMap = [];

        if ($isCurrentSemester) {
            $irStmt = $conn->prepare("
                SELECT Date_Of_Midterm_Grade_Recording, Date_Of_Final_Grades_Recording
                FROM INFORMATION_RELATION
                WHERE Semester_ID = 202608
            ");
            $irStmt->execute();
            $infoRel = $irStmt->get_result()->fetch_assoc();
            $irStmt->close();

            if ($infoRel) {
                $midtermOpen = isWithinGradeWindow($infoRel['Date_Of_Midterm_Grade_Recording'], 72);
                $finalOpen = isWithinGradeWindow($infoRel['Date_Of_Final_Grades_Recording'], 72);
            }

            // Meeting dates and attendance records via unified helper
            $attMatrix = getSectionAttendanceMatrix($conn, $roster_crn);
            $meetingMeta = $attMatrix['meeting_meta'];
            $attendanceMap = $attMatrix['attendance_map'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Faculty Dashboard | Lucid University</title>
    <!-- Minimal Clean Bootstrap 5.3 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .sticky-col {
            position: sticky;
            left: 0;
            background-color: #fff;
            z-index: 2;
        }
        .table-hover tbody tr:hover .sticky-col {
            background-color: #f8f9fa;
        }
    </style>
</head>
<body class="bg-light">

    <!-- Unified Faculty Top Navigation -->
    <?= renderFacultyNavbar($conn, $faculty_id, 'dashboard.php') ?>

    <div class="container mb-5">

        <!-- Flash Message Alerts -->
        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="alert alert-success alert-dismissible fade show mb-4 shadow-sm" role="alert">
                <?= htmlspecialchars($_SESSION['success_message'], ENT_QUOTES, 'UTF-8') ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php unset($_SESSION['success_message']); ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="alert alert-danger alert-dismissible fade show mb-4 shadow-sm" role="alert">
                <?= htmlspecialchars($_SESSION['error_message'], ENT_QUOTES, 'UTF-8') ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php unset($_SESSION['error_message']); ?>
        <?php endif; ?>

        <!-- Faculty Welcome & Workload Summary Card -->
        <div class="card shadow-sm mb-4 border-0">
            <div class="card-body p-4 bg-white rounded-3">
                <div class="row align-items-center g-3">
                    <div class="col-lg-7">
                        <h2 class="fw-bold mb-1">
                            Welcome, <?= htmlspecialchars($faculty_full_name, ENT_QUOTES, 'UTF-8') ?>
                        </h2>
                        <p class="text-secondary mb-3">
                            Faculty ID: <strong><?= (int)$faculty_id ?></strong> &bull;
                            Department of <?= htmlspecialchars($faculty_dept, ENT_QUOTES, 'UTF-8') ?>
                        </p>
                        <div class="d-flex flex-wrap gap-2">
                            <span class="badge text-bg-primary fs-6 py-2 px-3">
                                Rank: <?= htmlspecialchars($faculty_rank, ENT_QUOTES, 'UTF-8') ?>
                            </span>
                            <span class="badge text-bg-secondary fs-6 py-2 px-3">
                                Status: <?= htmlspecialchars($faculty_type, ENT_QUOTES, 'UTF-8') ?>
                            </span>
                            <span class="badge text-bg-light border fs-6 py-2 px-3">
                                Office: <?= htmlspecialchars($faculty_office, ENT_QUOTES, 'UTF-8') ?>
                            </span>
                            <span class="badge text-bg-light border fs-6 py-2 px-3">
                                Specialty: <?= htmlspecialchars($faculty_specialty, ENT_QUOTES, 'UTF-8') ?>
                            </span>
                        </div>
                    </div>
                    <div class="col-lg-5">
                        <div class="row g-2 text-center">
                            <div class="col-4">
                                <div class="p-3 bg-light rounded border">
                                    <div class="h3 fw-bold mb-0 text-primary"><?= count($fallSections) ?></div>
                                    <div class="small text-muted text-uppercase" style="font-size: 0.72rem;">Fall 2026 Load</div>
                                </div>
                            </div>
                            <div class="col-4">
                                <div class="p-3 bg-light rounded border">
                                    <div class="h3 fw-bold mb-0 text-primary"><?= count($springSections) ?></div>
                                    <div class="small text-muted text-uppercase" style="font-size: 0.72rem;">Spring 2027 Load</div>
                                </div>
                            </div>
                            <div class="col-4">
                                <div class="p-3 bg-light rounded border">
                                    <div class="h3 fw-bold mb-0 text-dark"><?= count($advisees) ?><span class="fs-6 text-muted">/15</span></div>
                                    <div class="small text-muted text-uppercase" style="font-size: 0.72rem;">Advisees</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Section Management Card (Displayed when roster_crn is set) -->
        <?php if ($roster_crn !== null): ?>
            <div id="section-workspace" class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-white py-3">
                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                        <div>
                            <h5 class="mb-0 fw-bold">
                                <?php if ($rosterSectionInfo): ?>
                                    Section Management: <?= htmlspecialchars($rosterSectionInfo['Course_ID'], ENT_QUOTES, 'UTF-8') ?> - Section <?= (int)$rosterSectionInfo['Section_Number'] ?> (<?= htmlspecialchars($rosterSectionInfo['Course_Name'], ENT_QUOTES, 'UTF-8') ?>)
                                <?php else: ?>
                                    Section Management (CRN: <?= (int)$roster_crn ?>)
                                <?php endif; ?>
                            </h5>
                            <?php if ($rosterSectionInfo): ?>
                                <small class="text-muted">
                                    <?= htmlspecialchars($rosterSectionInfo['Semester_Name'] . ' ' . $rosterSectionInfo['Semester_Year'], ENT_QUOTES, 'UTF-8') ?> &bull;
                                    CRN: <strong><?= (int)$roster_crn ?></strong> &bull;
                                    Room: <?= htmlspecialchars($rosterSectionInfo['Building_Name'] . ' ' . $rosterSectionInfo['Room_NO'], ENT_QUOTES, 'UTF-8') ?>
                                </small>
                            <?php endif; ?>
                        </div>
                        <a href="dashboard.php" class="btn btn-outline-secondary btn-sm">Close Workspace &times;</a>
                    </div>

                    <!-- Bootstrap Nav Tabs for Roster | Attendance | Grades -->
                    <ul class="nav nav-tabs card-header-tabs">
                        <li class="nav-item">
                            <a class="nav-link <?= ($activeTab === 'roster') ? 'active fw-bold' : '' ?>" href="dashboard.php?roster_crn=<?= (int)$roster_crn ?>&tab=roster#section-workspace">
                                Roster
                            </a>
                        </li>
                        <?php if ($isCurrentSemester): ?>
                            <li class="nav-item">
                                <a class="nav-link <?= ($activeTab === 'attendance') ? 'active fw-bold' : '' ?>" href="dashboard.php?roster_crn=<?= (int)$roster_crn ?>&tab=attendance#section-workspace">
                                    Attendance
                                    <?php if ($meetingMeta && $meetingMeta['is_live_now']): ?>
                                        <span class="badge bg-success ms-1">Live</span>
                                    <?php endif; ?>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link <?= ($activeTab === 'grades') ? 'active fw-bold' : '' ?>" href="dashboard.php?roster_crn=<?= (int)$roster_crn ?>&tab=grades#section-workspace">
                                    Grades
                                    <?php if ($midtermOpen || $finalOpen): ?>
                                        <span class="badge bg-primary ms-1">Open</span>
                                    <?php endif; ?>
                                </a>
                            </li>
                        <?php endif; ?>
                    </ul>
                </div>

                <div class="card-body p-0">
                    <?php if ($rosterStudents === null): ?>
                        <div class="alert alert-danger m-3 mb-3">
                            You are not authorized to view or manage CRN <?= (int)$roster_crn ?>.
                        </div>
                    <?php elseif (empty($rosterStudents)): ?>
                        <div class="p-4 text-center text-muted">
                            No students are currently registered in this section.
                        </div>
                    <?php else: ?>

                        <!-- TAB 1: ROSTER VIEW -->
                        <?php if ($activeTab === 'roster'): ?>
                            <div class="table-responsive">
                                <table class="table table-hover table-striped mb-0 align-middle">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Student ID</th>
                                            <th>Full Name</th>
                                            <th>Email</th>
                                            <th>Class Standing</th>
                                            <th>Major(s)</th>
                                            <th class="text-center">Midterm Grade</th>
                                            <th class="text-center">Final Exam Grade</th>
                                            <th class="text-center">Final Course Grade</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($rosterStudents as $st): ?>
                                            <tr>
                                                <td><strong><?= (int)$st['Student_ID'] ?></strong></td>
                                                <td class="fw-semibold">
                                                    <a href="../user_profile.php?user_id=<?= (int)$st['Student_ID'] ?>" class="text-decoration-none text-primary fw-semibold">
                                                        <?= htmlspecialchars($st['full_name'], ENT_QUOTES, 'UTF-8') ?>
                                                    </a>
                                                </td>
                                                <td><?= htmlspecialchars($st['User_Email'] ?? '-', ENT_QUOTES, 'UTF-8') ?></td>
                                                <td><?= htmlspecialchars($st['class_standing'], ENT_QUOTES, 'UTF-8') ?></td>
                                                <td><?= htmlspecialchars($st['Majors'] ?? 'Undeclared', ENT_QUOTES, 'UTF-8') ?></td>
                                                <td class="text-center">
                                                    <?php if (!empty($st['Midterm_Grade'])): ?>
                                                        <span class="badge bg-secondary fs-6"><?= htmlspecialchars($st['Midterm_Grade'], ENT_QUOTES, 'UTF-8') ?></span>
                                                    <?php else: ?>
                                                        <span class="text-muted fst-italic">Pending</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="text-center">
                                                    <?php if (!empty($st['Final_Grade'])): ?>
                                                        <span class="badge bg-info text-dark fs-6"><?= htmlspecialchars($st['Final_Grade'], ENT_QUOTES, 'UTF-8') ?></span>
                                                    <?php else: ?>
                                                        <span class="text-muted fst-italic">Pending</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="text-center">
                                                    <?php if (!empty($st['Grade'])): ?>
                                                        <span class="badge bg-primary fs-6"><?= htmlspecialchars($st['Grade'], ENT_QUOTES, 'UTF-8') ?></span>
                                                    <?php else: ?>
                                                        <span class="text-muted fst-italic">Ungraded</span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <?php if ($isCurrentSemester): ?>
                                <div class="p-3 bg-light border-top text-end">
                                    <span class="text-muted me-2 small">Need to enter or update grades?</span>
                                    <a href="dashboard.php?roster_crn=<?= (int)$roster_crn ?>&tab=grades#section-workspace" class="btn btn-outline-primary btn-sm">
                                        Open Grade Entry &rarr;
                                    </a>
                                </div>
                            <?php endif; ?>

                        <!-- TAB 2: ATTENDANCE MATRIX -->
                        <?php elseif ($activeTab === 'attendance' && $isCurrentSemester): ?>
                            <div class="p-3">
                                <?php if ($meetingMeta && $meetingMeta['is_live_now']): ?>
                                    <div class="alert alert-success d-flex align-items-center mb-3" role="alert">
                                        <div>
                                            <strong>Live Attendance Window is OPEN:</strong> Today's class session is active (<?= htmlspecialchars($meetingMeta['start_time']) ?> &ndash; <?= htmlspecialchars($meetingMeta['end_time']) ?>). Check present students and submit.
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <div class="alert alert-secondary mb-3" role="alert">
                                        <strong>Attendance Window Closed:</strong> Attendance can only be modified during scheduled live class hours (<?= htmlspecialchars($meetingMeta['start_time'] ?? 'TBA') ?> &ndash; <?= htmlspecialchars($meetingMeta['end_time'] ?? 'TBA') ?>). Past and future dates are locked.
                                    </div>
                                <?php endif; ?>

                                <?php if (empty($meetingMeta['dates'])): ?>
                                    <div class="text-center text-muted p-4">
                                        No scheduled class meeting dates found for this section.
                                    </div>
                                <?php else: ?>
                                    <form method="POST" action="section_actions_process.php">
                                        <input type="hidden" name="crn" value="<?= (int)$roster_crn ?>">
                                        <input type="hidden" name="return_tab" value="attendance">

                                        <div id="attendance-scroll-container" class="table-responsive" style="max-height: 500px; overflow-x: auto;">
                                            <table class="table table-bordered table-hover align-middle mb-0" style="min-width: <?= max(800, count($meetingMeta['dates']) * 90 + 220) ?>px;">
                                                <thead class="table-light sticky-top">
                                                    <tr>
                                                        <th class="sticky-col bg-light" style="width: 220px; min-width: 220px;">Student (ID &amp; Name)</th>
                                                        <?php
                                                        $today = date('Y-m-d');
                                                        $nowTime = date('H:i:s');
                                                        foreach ($meetingMeta['dates'] as $dInfo):
                                                            $d = $dInfo['date'];
                                                            $isToday = ($d === $today);
                                                            $colClass = $isToday ? 'bg-primary-subtle text-primary fw-bold' : '';
                                                            $todayColId = $isToday ? ' id="attendance-today-col"' : '';
                                                        ?>
                                                            <th<?= $todayColId ?> class="text-center small <?= $colClass ?>" style="min-width: 90px;">
                                                                <div><?= htmlspecialchars($dInfo['label'], ENT_QUOTES, 'UTF-8') ?></div>
                                                                <?php if ($isToday && $meetingMeta['is_live_now']): ?>
                                                                    <span class="badge bg-success" style="font-size: 0.65rem;">LIVE</span>
                                                                <?php elseif ($isToday): ?>
                                                                    <span class="badge bg-secondary" style="font-size: 0.65rem;">TODAY</span>
                                                                <?php endif; ?>
                                                            </th>
                                                        <?php endforeach; ?>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($rosterStudents as $st):
                                                        $sid = (int)$st['Student_ID'];
                                                    ?>
                                                        <tr>
                                                            <td class="sticky-col fw-semibold">
                                                                <div class="text-truncate" style="max-width: 200px;" title="<?= htmlspecialchars($st['full_name'], ENT_QUOTES, 'UTF-8') ?>">
                                                                    <?= htmlspecialchars($st['full_name'], ENT_QUOTES, 'UTF-8') ?>
                                                                </div>
                                                                <small class="text-muted">#<?= $sid ?></small>
                                                            </td>
                                                            <?php foreach ($meetingMeta['dates'] as $dInfo):
                                                                $d = $dInfo['date'];
                                                                $status = $attendanceMap[$sid][$d] ?? null;
                                                                $isLiveCol = ($d === $today && $meetingMeta['is_live_now'] === true);
                                                                $isPastCol = ($d < $today) || ($d === $today && $nowTime > $meetingMeta['end_time']);
                                                            ?>
                                                                <td class="text-center">
                                                                    <?php if ($isLiveCol): ?>
                                                                        <input type="checkbox" name="present_students[]" value="<?= $sid ?>" <?= ($status === 'Present') ? 'checked' : '' ?> class="form-check-input">
                                                                    <?php elseif ($isPastCol): ?>
                                                                        <?php if ($status === 'Present'): ?>
                                                                            <span class="badge bg-success">Present</span>
                                                                        <?php elseif ($status === 'Absent'): ?>
                                                                            <span class="badge bg-danger">Absent</span>
                                                                        <?php else: ?>
                                                                            <span class="text-muted">&mdash;</span>
                                                                        <?php endif; ?>
                                                                    <?php else: ?>
                                                                        <span class="badge bg-light text-muted border">Locked</span>
                                                                    <?php endif; ?>
                                                                </td>
                                                            <?php endforeach; ?>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>

                                        <div class="mt-3 d-flex justify-content-between align-items-center">
                                            <small class="text-muted">
                                                * Past attendance records are permanently locked per institutional academic policy.
                                            </small>
                                            <button type="submit" name="action" value="submit_attendance" class="btn btn-primary" <?= $meetingMeta['is_live_now'] ? '' : 'disabled' ?>>
                                                Submit Attendance
                                            </button>
                                        </div>
                                    </form>

                                    <script>
                                    document.addEventListener('DOMContentLoaded', function () {
                                        var container = document.getElementById('attendance-scroll-container');
                                        var todayCol = document.getElementById('attendance-today-col');
                                        if (container && todayCol) {
                                            container.scrollLeft = Math.max(0, todayCol.offsetLeft - 260);
                                        }
                                    });
                                    </script>
                                <?php endif; ?>
                            </div>

                        <!-- TAB 3: GRADES SUBMISSION -->
                        <?php elseif ($activeTab === 'grades' && $isCurrentSemester): ?>
                            <div class="p-3">
                                <!-- Grade Window Badges & Policy Notice -->
                                <div class="row g-3 mb-4">
                                    <div class="col-md-6">
                                        <div class="p-3 border rounded bg-white h-100">
                                            <div class="d-flex justify-content-between align-items-center mb-1">
                                                <h6 class="fw-bold mb-0">Midterm Grade Submission Window</h6>
                                                <?= $midtermOpen
                                                    ? '<span class="badge bg-success fs-6">Window Open</span>'
                                                    : '<span class="badge bg-secondary fs-6">Window Closed</span>'
                                                ?>
                                            </div>
                                            <small class="text-muted d-block">
                                                Recording Start Date: <strong><?= htmlspecialchars($infoRel['Date_Of_Midterm_Grade_Recording'] ?? 'N/A', ENT_QUOTES, 'UTF-8') ?></strong> (Active for 72 Hours)
                                            </small>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="p-3 border rounded bg-white h-100">
                                            <div class="d-flex justify-content-between align-items-center mb-1">
                                                <h6 class="fw-bold mb-0">Final Grade Submission Window</h6>
                                                <?= $finalOpen
                                                    ? '<span class="badge bg-success fs-6">Window Open</span>'
                                                    : '<span class="badge bg-secondary fs-6">Window Closed</span>'
                                                ?>
                                            </div>
                                            <small class="text-muted d-block">
                                                Recording Start Date: <strong><?= htmlspecialchars($infoRel['Date_Of_Final_Grades_Recording'] ?? 'N/A', ENT_QUOTES, 'UTF-8') ?></strong> (Active for 72 Hours)
                                            </small>
                                        </div>
                                    </div>
                                </div>

                                <form method="POST" action="section_actions_process.php">
                                    <input type="hidden" name="crn" value="<?= (int)$roster_crn ?>">
                                    <input type="hidden" name="return_tab" value="grades">

                                    <div class="table-responsive">
                                        <table class="table table-hover table-striped align-middle mb-0">
                                            <thead class="table-light">
                                                <tr>
                                                    <th>Student ID</th>
                                                    <th>Full Name</th>
                                                    <th>Email</th>
                                                    <th style="min-width: 160px;">Midterm Grade</th>
                                                    <th style="min-width: 160px;">Final Exam Grade</th>
                                                    <th class="text-center" style="min-width: 150px;">Recorded Course Grade</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php
                                                $gradeOptions = ['A', 'A-', 'B+', 'B', 'B-', 'C+', 'C', 'C-', 'D+', 'D', 'F'];
                                                foreach ($rosterStudents as $st):
                                                    $sid = (int)$st['Student_ID'];
                                                    $curMidterm = $st['Midterm_Grade'] ?? '';
                                                ?>
                                                    <tr>
                                                        <td><strong><?= $sid ?></strong></td>
                                                        <td class="fw-semibold">
                                                            <a href="../user_profile.php?user_id=<?= $sid ?>" class="text-decoration-none text-primary">
                                                                <?= htmlspecialchars($st['full_name'], ENT_QUOTES, 'UTF-8') ?>
                                                            </a>
                                                        </td>
                                                        <td><?= htmlspecialchars($st['User_Email'] ?? '-', ENT_QUOTES, 'UTF-8') ?></td>
                                                        <td>
                                                            <select name="midterm_grades[<?= $sid ?>]" class="form-select form-select-sm">
                                                                <option value="">-- Not Recorded --</option>
                                                                <?php foreach ($gradeOptions as $opt): ?>
                                                                    <option value="<?= $opt ?>" <?= ($curMidterm === $opt) ? 'selected' : '' ?>>
                                                                        <?= $opt ?>
                                                                    </option>
                                                                <?php endforeach; ?>
                                                            </select>
                                                        </td>
                                                        <td>
                                                            <?php $curFinalExam = $st['Final_Grade'] ?? ''; ?>
                                                            <select name="final_exam_grades[<?= $sid ?>]" class="form-select form-select-sm">
                                                                <option value="">-- Not Recorded --</option>
                                                                <?php foreach ($gradeOptions as $opt): ?>
                                                                    <option value="<?= $opt ?>" <?= ($curFinalExam === $opt) ? 'selected' : '' ?>><?= $opt ?></option>
                                                                <?php endforeach; ?>
                                                            </select>
                                                        </td>
                                                        <td class="text-center">
                                                            <?php if (!empty($st['Grade'])): ?>
                                                                <span class="badge bg-primary fs-6"><?= htmlspecialchars($st['Grade'], ENT_QUOTES, 'UTF-8') ?></span>
                                                            <?php else: ?>
                                                                <span class="text-muted fst-italic">Pending</span>
                                                            <?php endif; ?>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>

                                    <div class="mt-4 d-flex flex-wrap gap-2 justify-content-end bg-light p-3 rounded border">
                                        <button type="submit" name="action" value="submit_midterm" class="btn btn-primary">
                                            Submit Midterm Grades
                                        </button>
                                        <button type="submit" name="action" value="submit_final" class="btn btn-success">
                                            Submit &amp; Average Final Grades
                                        </button>
                                    </div>
                                </form>
                            </div>
                        <?php endif; ?>

                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Fall 2026 Teaching Schedule Table (Current Semester) -->
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <div>
                    <h5 class="mb-0 fw-bold">Fall 2026 Teaching Schedule</h5>
                    <small class="text-muted">Active Teaching Term (Semester ID: 202608)</small>
                </div>
                <span class="badge bg-primary fs-6"><?= count($fallSections) ?> Section(s)</span>
            </div>
            <div class="card-body p-0">
                <?php if (empty($fallSections)): ?>
                    <div class="p-4 text-center text-muted">
                        No teaching sections assigned for Fall 2026.
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
                                    <th>Day &amp; Time</th>
                                    <th>Location</th>
                                    <th>Enrollment / Seats</th>
                                    <th class="text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($fallSections as $sec):
                                    $sCrn = (int)$sec['CRN'];
                                    $isSelected = ($roster_crn === $sCrn);
                                ?>
                                    <tr class="<?= $isSelected ? 'table-primary' : '' ?>">
                                        <td><strong><?= $sCrn ?></strong></td>
                                        <td><span class="badge bg-secondary"><?= htmlspecialchars($sec['Course_ID'], ENT_QUOTES, 'UTF-8') ?></span></td>
                                        <td><?= (int)$sec['Section_Number'] ?></td>
                                        <td class="fw-semibold"><?= htmlspecialchars($sec['Course_Name'], ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><?= (int)$sec['Course_Credits'] ?></td>
                                        <td><?= formatSchedule($sec['Time_Slot_Day'], $sec['Start_Time'], $sec['End_Time']) ?></td>
                                        <td><?= htmlspecialchars($sec['Building_Name'] . ' ' . $sec['Room_NO'], ENT_QUOTES, 'UTF-8') ?></td>
                                        <td>
                                            <strong><?= (int)$sec['Enrolled_Count'] ?></strong> enrolled
                                            <span class="text-muted">(<?= (int)$sec['Available_seats'] ?> open)</span>
                                        </td>
                                        <td class="text-end">
                                            <div class="btn-group btn-group-sm" role="group">
                                                <a href="?roster_crn=<?= $sCrn ?>&tab=roster#section-workspace" class="btn btn-outline-primary <?= ($isSelected && $activeTab === 'roster') ? 'active' : '' ?>">
                                                    Roster
                                                </a>
                                                <a href="?roster_crn=<?= $sCrn ?>&tab=attendance#section-workspace" class="btn btn-outline-success <?= ($isSelected && $activeTab === 'attendance') ? 'active' : '' ?>">
                                                    Attendance
                                                </a>
                                                <a href="?roster_crn=<?= $sCrn ?>&tab=grades#section-workspace" class="btn btn-outline-warning <?= ($isSelected && $activeTab === 'grades') ? 'active' : '' ?>">
                                                    Grades
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Spring 2027 Teaching Schedule Table (Upcoming Registration Term) -->
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <div>
                    <h5 class="mb-0 fw-bold">Spring 2027 Teaching Schedule</h5>
                    <small class="text-muted">Upcoming Registration Term (Semester ID: 202701)</small>
                </div>
                <span class="badge bg-info text-dark fs-6"><?= count($springSections) ?> Section(s)</span>
            </div>
            <div class="card-body p-0">
                <?php if (empty($springSections)): ?>
                    <div class="p-4 text-center text-muted">
                        No teaching sections assigned for Spring 2027.
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
                                    <th>Day &amp; Time</th>
                                    <th>Location</th>
                                    <th>Enrollment / Seats</th>
                                    <th class="text-end">Roster</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($springSections as $sec):
                                    $sCrn = (int)$sec['CRN'];
                                    $isSelected = ($roster_crn === $sCrn);
                                ?>
                                    <tr class="<?= $isSelected ? 'table-info' : '' ?>">
                                        <td><strong><?= $sCrn ?></strong></td>
                                        <td><span class="badge bg-secondary"><?= htmlspecialchars($sec['Course_ID'], ENT_QUOTES, 'UTF-8') ?></span></td>
                                        <td><?= (int)$sec['Section_Number'] ?></td>
                                        <td class="fw-semibold"><?= htmlspecialchars($sec['Course_Name'], ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><?= (int)$sec['Course_Credits'] ?></td>
                                        <td><?= formatSchedule($sec['Time_Slot_Day'], $sec['Start_Time'], $sec['End_Time']) ?></td>
                                        <td><?= htmlspecialchars($sec['Building_Name'] . ' ' . $sec['Room_NO'], ENT_QUOTES, 'UTF-8') ?></td>
                                        <td>
                                            <strong><?= (int)$sec['Enrolled_Count'] ?></strong> enrolled
                                            <span class="text-muted">(<?= (int)$sec['Available_seats'] ?> open)</span>
                                        </td>
                                        <td class="text-end">
                                            <a href="?roster_crn=<?= $sCrn ?>&tab=roster#section-workspace" class="btn btn-outline-primary btn-sm <?= ($isSelected && $activeTab === 'roster') ? 'active' : '' ?>">
                                                View Roster
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
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
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

    </div>

    <!-- Bootstrap 5.3 JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
