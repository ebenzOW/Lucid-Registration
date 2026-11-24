<?php
/**
 * Lucid University Course Registration System
 * Administrator Master Course Schedule & Roster Drill-Down Portal
 *
 * Full-featured administrative catalog browser across current, upcoming, and past semesters.
 * Displays live section capacities, real-time enrollment counts, clickable faculty profile links,
 * prerequisite inspector modals, and direct student roster drill-downs.
 */

require_once 'admin_guard.php';
$admin_id = (int)$userID;

require_once '../db_connect.php';
require_once __DIR__ . '/../includes/ui_helpers.php';
require_once __DIR__ . '/../includes/schedule_helpers.php';
require_once __DIR__ . '/../includes/faculty_helpers.php';

// 1. Fetch Dropdown Filter Options
$filterOptions = getMasterScheduleFilterOptions($conn);
$semesters   = $filterOptions['semesters'];
$departments = $filterOptions['departments'];
$timeSlots   = $filterOptions['time_slots'];

// 2. Resolve Selected Semester (Default: Fall 2026 = 202608)
$selected_sem_id = isset($_GET['semester']) ? filter_var($_GET['semester'], FILTER_VALIDATE_INT) : null;
if (!$selected_sem_id) {
    $selected_sem_id = 202608;
}

$is_past_sem = ($selected_sem_id < 202608);

// 3. Fetch Semester Metadata & Academic Deadlines
$semStmt = $conn->prepare("
    SELECT s.Semester_ID, s.Semester_Name, s.Semester_Year, s.Start_Date, s.End_Date,
           ir.Last_Day_Of_Registration, ir.Last_Day_Of_Dropping_Course,
           ir.Date_Of_Midterm_Grade_Recording, ir.Date_Of_Final_Grades_Recording
    FROM SEMESTER s
    LEFT JOIN INFORMATION_RELATION ir ON s.Semester_ID = ir.Semester_ID
    WHERE s.Semester_ID = ?
");
$semStmt->bind_param("i", $selected_sem_id);
$semStmt->execute();
$semMeta = $semStmt->get_result()->fetch_assoc();
$semStmt->close();

$semester_display = $semMeta ? ($semMeta['Semester_Name'] . ' ' . $semMeta['Semester_Year']) : "Term " . $selected_sem_id;

if ($selected_sem_id == 202608) {
    $termClassification = 'Current Term';
    $termBadgeClass = 'bg-success';
} elseif ($selected_sem_id > 202608) {
    $termClassification = 'Upcoming Term';
    $termBadgeClass = 'bg-primary';
} else {
    $termClassification = 'Historical Term';
    $termBadgeClass = 'bg-secondary';
}

// 4. Extract Filter Query Parameters
$filterCrn        = isset($_GET['crn']) ? filter_var($_GET['crn'], FILTER_VALIDATE_INT) : null;
$filterDept       = isset($_GET['dept']) ? filter_var($_GET['dept'], FILTER_VALIDATE_INT) : null;
$filterSearch     = isset($_GET['search']) ? trim($_GET['search']) : '';
$filterSection    = (isset($_GET['section']) && trim($_GET['section']) !== '') ? trim($_GET['section']) : null;
$filterTimeSlot   = isset($_GET['time_slot_id']) ? filter_var($_GET['time_slot_id'], FILTER_VALIDATE_INT) : null;
$filterInstructor = isset($_GET['instructor']) ? trim($_GET['instructor']) : '';
$filterRoom       = isset($_GET['room']) ? trim($_GET['room']) : '';
$filterCredits    = isset($_GET['credits']) ? (trim($_GET['credits']) !== '' ? filter_var($_GET['credits'], FILTER_VALIDATE_INT) : '') : '';
$filterStatus     = isset($_GET['status']) && in_array($_GET['status'], ['Active', 'Past', 'Cancelled', 'All'], true) ? $_GET['status'] : 'All';
$filterOpenOnly   = !empty($_GET['open_only']);

$searchFilters = [
    'semester_id'       => $selected_sem_id,
    'crn'               => $filterCrn,
    'dept'              => $filterDept,
    'search'            => $filterSearch,
    'section'           => $filterSection,
    'time_slot_id'      => $filterTimeSlot,
    'instructor'        => $filterInstructor,
    'room'              => $filterRoom,
    'credits'           => ($filterCredits !== '' ? $filterCredits : null),
    'status'            => $filterStatus,
    'open_only'         => $filterOpenOnly,
    'include_cancelled' => true,
];

// 5. Query Master Schedule Results
$sections = searchMasterSchedule($conn, $searchFilters);
$totalSections = count($sections);
$totalEnrolled = array_sum(array_column($sections, 'Enrolled_Count'));

// 6. Inline Section Roster Drill-Down Resolution
$roster_crn = isset($_GET['roster_crn']) ? filter_var($_GET['roster_crn'], FILTER_VALIDATE_INT) : null;
$rosterSection = null;
$rosterStudents = null;

if ($roster_crn) {
    $rSecStmt = $conn->prepare("
        SELECT cs.CRN, cs.Course_ID, cs.Section_Number, cs.Semester_ID, cs.Status, cs.Faculty_ID,
               c.Course_Name, c.Course_Credits, dept.Department_Name,
               ts.Time_Slot_Day, p.Start_Time, p.End_Time,
               b.Building_Name, r.Room_NO,
               s.Semester_Name, s.Semester_Year,
               COALESCE(CONCAT(u_fac.User_FName, ' ', u_fac.User_LName), 'Staff') AS Instructor_Name
        FROM COURSE_SECTIONS cs
        JOIN COURSES c ON cs.Course_ID = c.Course_ID
        LEFT JOIN DEPARTMENT dept ON c.Department_ID = dept.Department_ID
        JOIN TIME_SLOT ts ON cs.Time_Slot_ID = ts.Time_Slot_ID
        JOIN PERIOD p ON ts.Period_ID = p.Period_ID
        JOIN ROOM r ON cs.Room_ID = r.Room_ID
        JOIN BUILDING b ON r.Building_ID = b.Building_ID
        LEFT JOIN SEMESTER s ON cs.Semester_ID = s.Semester_ID
        LEFT JOIN FACULTY f ON cs.Faculty_ID = f.Faculty_ID
        LEFT JOIN `USER` u_fac ON f.Faculty_ID = u_fac.User_ID
        WHERE cs.CRN = ?
    ");
    $rSecStmt->bind_param("i", $roster_crn);
    $rSecStmt->execute();
    $rosterSection = $rSecStmt->get_result()->fetch_assoc();
    $rSecStmt->close();

    if ($rosterSection) {
        $rosterStudents = getSectionRoster($conn, $roster_crn, null);
    }
}

// 7. Inline Section Attendance Drill-Down Resolution
$attendance_crn = isset($_GET['attendance_crn']) ? filter_var($_GET['attendance_crn'], FILTER_VALIDATE_INT) : null;
$attendanceSection = null;
$attendanceMatrix = null;

if ($attendance_crn) {
    $attSecStmt = $conn->prepare("
        SELECT cs.CRN, cs.Course_ID, cs.Section_Number, cs.Semester_ID, cs.Status, cs.Faculty_ID,
               c.Course_Name, c.Course_Credits, dept.Department_Name,
               ts.Time_Slot_Day, p.Start_Time, p.End_Time,
               b.Building_Name, r.Room_NO,
               s.Semester_Name, s.Semester_Year,
               COALESCE(CONCAT(u_fac.User_FName, ' ', u_fac.User_LName), 'Staff') AS Instructor_Name
        FROM COURSE_SECTIONS cs
        JOIN COURSES c ON cs.Course_ID = c.Course_ID
        LEFT JOIN DEPARTMENT dept ON c.Department_ID = dept.Department_ID
        JOIN TIME_SLOT ts ON cs.Time_Slot_ID = ts.Time_Slot_ID
        JOIN PERIOD p ON ts.Period_ID = p.Period_ID
        JOIN ROOM r ON cs.Room_ID = r.Room_ID
        JOIN BUILDING b ON r.Building_ID = b.Building_ID
        LEFT JOIN SEMESTER s ON cs.Semester_ID = s.Semester_ID
        LEFT JOIN FACULTY f ON cs.Faculty_ID = f.Faculty_ID
        LEFT JOIN `USER` u_fac ON f.Faculty_ID = u_fac.User_ID
        WHERE cs.CRN = ?
    ");
    $attSecStmt->bind_param("i", $attendance_crn);
    $attSecStmt->execute();
    $attendanceSection = $attSecStmt->get_result()->fetch_assoc();
    $attSecStmt->close();

    if ($attendanceSection) {
        $attendanceMatrix = getSectionAttendanceMatrix($conn, $attendance_crn);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Master Schedule - Lucid University</title>
    <!-- Bootstrap 5.3 CDN -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

    <!-- Admin Navigation Bar -->
    <?= renderAdminNavbar($conn, $admin_id, 'master_schedule.php') ?>

    <div class="container-fluid px-lg-5 mb-5">

        <!-- Page Header & Action Bar -->
        <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
            <div>
                <h2 class="fw-bold mb-1">Master Course Schedule</h2>
                <p class="text-secondary mb-0">
                    Comprehensive catalog browser, room capacity auditor, and enrollment roster inspector.
                </p>
            </div>
            <div class="d-flex gap-2">
                <a href="create_course_section.php" class="btn btn-success shadow-sm">
                    + Create Course Section
                </a>
                <a href="admin_dashboard.php" class="btn btn-outline-secondary shadow-sm">
                    &larr; Return to Dashboard
                </a>
            </div>
        </div>

        <!-- Semester KPI & Deadline Summary Banner -->
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-body p-4 bg-white rounded-3">
                <div class="row align-items-center g-3">
                    <div class="col-lg-3 border-end-lg">
                        <div class="d-flex align-items-center gap-2 mb-1">
                            <span class="badge <?= $termBadgeClass ?> px-2 py-1"><?= htmlspecialchars($termClassification, ENT_QUOTES, 'UTF-8') ?></span>
                            <span class="text-muted small">Term <?= (int)$selected_sem_id ?></span>
                        </div>
                        <h3 class="fw-bold mb-1 text-dark"><?= htmlspecialchars($semester_display, ENT_QUOTES, 'UTF-8') ?></h3>
                        <div class="text-secondary small">
                            <?= !empty($semMeta['Start_Date']) ? date('M j, Y', strtotime($semMeta['Start_Date'])) : 'TBA' ?>
                            &ndash;
                            <?= !empty($semMeta['End_Date']) ? date('M j, Y', strtotime($semMeta['End_Date'])) : 'TBA' ?>
                        </div>
                    </div>
                    <div class="col-lg-3 col-sm-6 text-center border-end-md">
                        <div class="text-muted small text-uppercase fw-semibold">Scheduled Sections</div>
                        <div class="display-6 fw-bold text-primary"><?= (int)$totalSections ?></div>
                        <div class="text-secondary small">Course offerings matching filters</div>
                    </div>
                    <div class="col-lg-3 col-sm-6 text-center border-end-lg">
                        <div class="text-muted small text-uppercase fw-semibold"><?= $is_past_sem ? 'Completed Enrollments' : 'Active Enrollments' ?></div>
                        <div class="display-6 fw-bold text-success"><?= (int)$totalEnrolled ?></div>
                        <div class="text-secondary small">Student seats filled</div>
                    </div>
                    <div class="col-lg-3">
                        <div class="small">
                            <div class="d-flex justify-content-between mb-1">
                                <span class="text-muted">Last Day to Reg:</span>
                                <span class="fw-semibold"><?= !empty($semMeta['Last_Day_Of_Registration']) ? date('M j, Y', strtotime($semMeta['Last_Day_Of_Registration'])) : 'N/A' ?></span>
                            </div>
                            <div class="d-flex justify-content-between mb-1">
                                <span class="text-muted">Add/Drop Deadline:</span>
                                <span class="fw-semibold"><?= !empty($semMeta['Last_Day_Of_Dropping_Course']) ? date('M j, Y', strtotime($semMeta['Last_Day_Of_Dropping_Course'])) : 'N/A' ?></span>
                            </div>
                            <div class="d-flex justify-content-between mb-1">
                                <span class="text-muted">Midterm Grading:</span>
                                <span class="fw-semibold"><?= !empty($semMeta['Date_Of_Midterm_Grade_Recording']) ? date('M j, Y', strtotime($semMeta['Date_Of_Midterm_Grade_Recording'])) : 'N/A' ?></span>
                            </div>
                            <div class="d-flex justify-content-between">
                                <span class="text-muted">Final Grading:</span>
                                <span class="fw-semibold"><?= !empty($semMeta['Date_Of_Final_Grades_Recording']) ? date('M j, Y', strtotime($semMeta['Date_Of_Final_Grades_Recording'])) : 'N/A' ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filter Card -->
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-header bg-white py-3">
                <h5 class="mb-0 fw-bold">Master Schedule Filters</h5>
            </div>
            <div class="card-body p-4">
                <form method="GET" action="master_schedule.php">
                    <div class="row g-3">
                        <!-- 1. Semester Selector -->
                        <div class="col-md-3">
                            <label for="filter_semester" class="form-label fw-semibold small text-muted">Semester</label>
                            <select name="semester" id="filter_semester" class="form-select">
                                <?php foreach ($semesters as $sem):
                                    $semId = (int)$sem['Semester_ID'];
                                    $semLabel = $sem['Semester_Name'] . ' ' . $sem['Semester_Year'];
                                    if ($semId == 202608) {
                                        $semLabel .= ' (Current)';
                                    } elseif ($semId > 202608) {
                                        $semLabel .= ' (Upcoming)';
                                    } else {
                                        $semLabel .= ' (Past)';
                                    }
                                ?>
                                    <option value="<?= $semId ?>" <?= $semId === (int)$selected_sem_id ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($semLabel, ENT_QUOTES, 'UTF-8') ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- 2. Department -->
                        <div class="col-md-3">
                            <label for="filter_dept" class="form-label fw-semibold small text-muted">Department</label>
                            <select name="dept" id="filter_dept" class="form-select">
                                <option value="">All Departments</option>
                                <?php foreach ($departments as $dept): ?>
                                    <option value="<?= (int)$dept['Department_ID'] ?>" <?= $filterDept !== null && (int)$dept['Department_ID'] === $filterDept ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($dept['Department_Name'], ENT_QUOTES, 'UTF-8') ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- 3. Course Search Keyword -->
                        <div class="col-md-3">
                            <label for="filter_search" class="form-label fw-semibold small text-muted">Course ID or Name</label>
                            <input type="text" name="search" id="filter_search" class="form-control" placeholder="e.g. CS101 or Computer" value="<?= htmlspecialchars($filterSearch, ENT_QUOTES, 'UTF-8') ?>">
                        </div>

                        <!-- 4. Section Status -->
                        <div class="col-md-3">
                            <label for="filter_status" class="form-label fw-semibold small text-muted">Section Status</label>
                            <select name="status" id="filter_status" class="form-select">
                                <option value="All" <?= $filterStatus === 'All' ? 'selected' : '' ?>>All Statuses</option>
                                <option value="Active" <?= $filterStatus === 'Active' ? 'selected' : '' ?>>Active Only</option>
                                <option value="Past" <?= $filterStatus === 'Past' ? 'selected' : '' ?>>Past Only</option>
                                <option value="Cancelled" <?= $filterStatus === 'Cancelled' ? 'selected' : '' ?>>Cancelled Only</option>
                            </select>
                        </div>

                        <!-- 5. CRN -->
                        <div class="col-md-2">
                            <label for="filter_crn" class="form-label fw-semibold small text-muted">CRN</label>
                            <input type="number" name="crn" id="filter_crn" class="form-control" placeholder="e.g. 262100" value="<?= $filterCrn ? (int)$filterCrn : '' ?>">
                        </div>

                        <!-- 6. Section Number -->
                        <div class="col-md-2">
                            <label for="filter_section" class="form-label fw-semibold small text-muted">Section #</label>
                            <input type="text" name="section" id="filter_section" class="form-control" placeholder="e.g. 1" value="<?= htmlspecialchars((string)($filterSection ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                        </div>

                        <!-- 7. Time Slot -->
                        <div class="col-md-3">
                            <label for="filter_timeslot" class="form-label fw-semibold small text-muted">Time Slot</label>
                            <select name="time_slot_id" id="filter_timeslot" class="form-select">
                                <option value="">All Time Slots</option>
                                <?php foreach ($timeSlots as $ts):
                                    $tsId = (int)$ts['Time_Slot_ID'];
                                    $tsLabel = formatSchedule($ts['Time_Slot_Day'], $ts['Start_Time'], $ts['End_Time']);
                                ?>
                                    <option value="<?= $tsId ?>" <?= $filterTimeSlot !== null && $tsId === $filterTimeSlot ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($tsLabel, ENT_QUOTES, 'UTF-8') ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- 8. Instructor -->
                        <div class="col-md-3">
                            <label for="filter_instructor" class="form-label fw-semibold small text-muted">Instructor Name</label>
                            <input type="text" name="instructor" id="filter_instructor" class="form-control" placeholder="e.g. King or Anastasia" value="<?= htmlspecialchars($filterInstructor, ENT_QUOTES, 'UTF-8') ?>">
                        </div>

                        <!-- 9. Room / Location -->
                        <div class="col-md-2">
                            <label for="filter_room" class="form-label fw-semibold small text-muted">Room / Building</label>
                            <input type="text" name="room" id="filter_room" class="form-control" placeholder="e.g. Academic or 102" value="<?= htmlspecialchars($filterRoom, ENT_QUOTES, 'UTF-8') ?>">
                        </div>

                        <!-- 10. Credits -->
                        <div class="col-md-2">
                            <label for="filter_credits" class="form-label fw-semibold small text-muted">Credits</label>
                            <select name="credits" id="filter_credits" class="form-select">
                                <option value="" <?= $filterCredits === '' ? 'selected' : '' ?>>All Credits</option>
                                <option value="1" <?= $filterCredits === 1 ? 'selected' : '' ?>>1 Credit</option>
                                <option value="2" <?= $filterCredits === 2 ? 'selected' : '' ?>>2 Credits</option>
                                <option value="3" <?= $filterCredits === 3 ? 'selected' : '' ?>>3 Credits</option>
                                <option value="4" <?= $filterCredits === 4 ? 'selected' : '' ?>>4 Credits</option>
                            </select>
                        </div>

                        <!-- 11. Open Seats Only & Filter Actions -->
                        <div class="col-md-10 d-flex align-items-end justify-content-between flex-wrap gap-2">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="open_only" value="1" id="filter_open_only" <?= $filterOpenOnly ? 'checked' : '' ?>>
                                <label class="form-check-label fw-semibold text-secondary" for="filter_open_only">
                                    Show Open Sections Only (Available Seats &gt; 0)
                                </label>
                            </div>
                            <div class="d-flex gap-2">
                                <button type="submit" class="btn btn-primary px-4 fw-semibold">
                                    Apply Filters
                                </button>
                                <a href="master_schedule.php?semester=<?= (int)$selected_sem_id ?>" class="btn btn-outline-secondary">
                                    Reset Filters
                                </a>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <?php if ($attendanceSection && $attendanceMatrix): 
            $attStudents = $attendanceMatrix['students'] ?? [];
            $meetingDates = $attendanceMatrix['meeting_meta']['dates'] ?? [];
            $attMap = $attendanceMatrix['attendance_map'] ?? [];
            $totals = $attendanceMatrix['student_totals'] ?? [];
            $totalRecordedSessions = 0;
            foreach ($meetingDates as $md) {
                $hasRec = false;
                foreach ($attStudents as $st) {
                    if (isset($attMap[(int)$st['Student_ID']][$md['date']])) {
                        $hasRec = true;
                        break;
                    }
                }
                if ($hasRec) $totalRecordedSessions++;
            }
            $totalAttPct = 0;
            $studentCount = count($attStudents);
            foreach ($attStudents as $st) {
                $sid = (int)$st['Student_ID'];
                $totalAttPct += ($totals[$sid]['attendance_pct'] ?? 0);
            }
            $avgAttPct = $studentCount > 0 ? round($totalAttPct / $studentCount, 1) : 0;
        ?>
            <!-- Inline Section Attendance Card Drill-Down -->
            <div id="admin-attendance-card" class="card shadow-sm border-0 border-top border-info border-4 mb-4">
                <div class="card-header bg-white py-3 d-flex flex-wrap justify-content-between align-items-center gap-2">
                    <div>
                        <div class="d-flex align-items-center gap-2">
                            <span class="badge bg-info text-dark fs-6">CRN <?= (int)$attendanceSection['CRN'] ?> &bull; Attendance Matrix</span>
                            <h5 class="fw-bold mb-0 text-dark">
                                <?= htmlspecialchars($attendanceSection['Course_ID'] . ' - ' . $attendanceSection['Course_Name'], ENT_QUOTES, 'UTF-8') ?>
                                (Sec <?= htmlspecialchars((string)$attendanceSection['Section_Number'], ENT_QUOTES, 'UTF-8') ?>)
                            </h5>
                        </div>
                        <div class="text-secondary small mt-1">
                            Instructor: <strong><?= renderFacultyLink($attendanceSection['Faculty_ID'] ?? 0, $attendanceSection['Instructor_Name'], '../') ?></strong> &bull;
                            Term: <?= htmlspecialchars($attendanceSection['Semester_Name'] . ' ' . $attendanceSection['Semester_Year'], ENT_QUOTES, 'UTF-8') ?> &bull;
                            Schedule: <?= formatSchedule($attendanceSection['Time_Slot_Day'], $attendanceSection['Start_Time'], $attendanceSection['End_Time']) ?> &bull;
                            Room: <?= htmlspecialchars($attendanceSection['Building_Name'] . ' ' . $attendanceSection['Room_NO'], ENT_QUOTES, 'UTF-8') ?>
                        </div>
                    </div>
                    <div>
                        <a href="master_schedule.php?semester=<?= (int)$selected_sem_id ?>" class="btn btn-outline-secondary btn-sm">
                            Close Attendance &times;
                        </a>
                    </div>
                </div>
                <div class="card-body p-3 bg-light border-bottom">
                    <div class="row g-2 text-center">
                        <div class="col-sm-4">
                            <div class="bg-white p-2 rounded border">
                                <div class="text-muted small">Recorded Sessions</div>
                                <div class="fs-5 fw-bold text-dark"><?= $totalRecordedSessions ?></div>
                            </div>
                        </div>
                        <div class="col-sm-4">
                            <div class="bg-white p-2 rounded border">
                                <div class="text-muted small">Enrolled Students</div>
                                <div class="fs-5 fw-bold text-dark"><?= $studentCount ?></div>
                            </div>
                        </div>
                        <div class="col-sm-4">
                            <div class="bg-white p-2 rounded border">
                                <div class="text-muted small">Average Attendance Rate</div>
                                <div class="fs-5 fw-bold <?= $avgAttPct >= 80 ? 'text-success' : ($avgAttPct >= 65 ? 'text-warning' : 'text-danger') ?>"><?= $avgAttPct ?>%</div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($attStudents)): ?>
                        <div class="p-5 text-center text-muted">
                            <h6 class="fw-bold text-secondary mb-1">No Enrolled Students</h6>
                            <p class="small mb-0">No student enrollment records found for section CRN <?= (int)$attendanceSection['CRN'] ?>.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive" style="max-height: 480px;">
                            <table class="table table-hover table-striped mb-0 align-middle small">
                                <thead class="table-light sticky-top">
                                    <tr>
                                        <th style="min-width: 100px;">Student ID</th>
                                        <th style="min-width: 160px;">Name</th>
                                        <th class="text-center" style="min-width: 70px;">Present</th>
                                        <th class="text-center" style="min-width: 70px;">Absent</th>
                                        <th class="text-center" style="min-width: 80px;">Rate</th>
                                        <?php foreach ($meetingDates as $md): ?>
                                            <th class="text-center text-nowrap px-1" style="min-width: 55px;" title="<?= htmlspecialchars($md['weekday'] . ' ' . $md['date'], ENT_QUOTES, 'UTF-8') ?>">
                                                <?= htmlspecialchars($md['label'], ENT_QUOTES, 'UTF-8') ?>
                                            </th>
                                        <?php endforeach; ?>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($attStudents as $st): 
                                        $sid = (int)$st['Student_ID'];
                                        $sTotals = $totals[$sid] ?? ['present_count' => 0, 'absent_count' => 0, 'attendance_pct' => 0];
                                    ?>
                                        <tr>
                                            <td><strong><?= $sid ?></strong></td>
                                            <td>
                                                <a href="../user_profile.php?user_id=<?= $sid ?>" class="text-decoration-none fw-semibold">
                                                    <?= htmlspecialchars($st['full_name'], ENT_QUOTES, 'UTF-8') ?>
                                                </a>
                                            </td>
                                            <td class="text-center fw-bold text-success"><?= $sTotals['present_count'] ?></td>
                                            <td class="text-center fw-bold text-danger"><?= $sTotals['absent_count'] ?></td>
                                            <td class="text-center">
                                                <span class="badge <?= $sTotals['attendance_pct'] >= 80 ? 'bg-success' : ($sTotals['attendance_pct'] >= 65 ? 'bg-warning text-dark' : 'bg-danger') ?>">
                                                    <?= $sTotals['attendance_pct'] ?>%
                                                </span>
                                            </td>
                                            <?php foreach ($meetingDates as $md): 
                                                $dStr = $md['date'];
                                                $status = $attMap[$sid][$dStr] ?? null;
                                            ?>
                                                <td class="text-center p-1">
                                                    <?php if ($status === 'Present'): ?>
                                                        <span class="badge bg-success-subtle text-success border border-success-subtle px-1">P</span>
                                                    <?php elseif ($status === 'Absent'): ?>
                                                        <span class="badge bg-danger-subtle text-danger border border-danger-subtle px-1">A</span>
                                                    <?php else: ?>
                                                        <span class="text-muted">&middot;</span>
                                                    <?php endif; ?>
                                                </td>
                                            <?php endforeach; ?>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($rosterSection): ?>
            <!-- Inline Section Roster Card Drill-Down -->
            <div id="admin-roster-card" class="card shadow-sm border-0 border-top border-primary border-4 mb-4">
                <div class="card-header bg-white py-3 d-flex flex-wrap justify-content-between align-items-center gap-2">
                    <div>
                        <div class="d-flex align-items-center gap-2">
                            <span class="badge bg-primary fs-6">CRN <?= (int)$rosterSection['CRN'] ?></span>
                            <h5 class="fw-bold mb-0 text-dark">
                                <?= htmlspecialchars($rosterSection['Course_ID'] . ' - ' . $rosterSection['Course_Name'], ENT_QUOTES, 'UTF-8') ?>
                                (Sec <?= htmlspecialchars((string)$rosterSection['Section_Number'], ENT_QUOTES, 'UTF-8') ?>)
                            </h5>
                        </div>
                        <div class="text-secondary small mt-1">
                            Instructor: <strong><?= renderFacultyLink($rosterSection['Faculty_ID'] ?? 0, $rosterSection['Instructor_Name'], '../') ?></strong> &bull;
                            Term: <?= htmlspecialchars($rosterSection['Semester_Name'] . ' ' . $rosterSection['Semester_Year'], ENT_QUOTES, 'UTF-8') ?> &bull;
                            Schedule: <?= formatSchedule($rosterSection['Time_Slot_Day'], $rosterSection['Start_Time'], $rosterSection['End_Time']) ?> &bull;
                            Room: <?= htmlspecialchars($rosterSection['Building_Name'] . ' ' . $rosterSection['Room_NO'], ENT_QUOTES, 'UTF-8') ?>
                        </div>
                    </div>
                    <div>
                        <a href="master_schedule.php?semester=<?= (int)$selected_sem_id ?>" class="btn btn-outline-secondary btn-sm">
                            Close Roster &times;
                        </a>
                    </div>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($rosterStudents)): ?>
                        <div class="p-5 text-center text-muted">
                            <h6 class="fw-bold text-secondary mb-1">No Enrolled Students</h6>
                            <p class="small mb-0">No student enrollment records found for section CRN <?= (int)$rosterSection['CRN'] ?>.</p>
                        </div>
                    <?php else: 
                        $isPastRoster = ((int)($rosterSection['Semester_ID'] ?? 202608) < 202608) || (($rosterSection['Status'] ?? '') === 'Past');
                    ?>
                        <div class="table-responsive">
                            <table class="table table-hover table-striped mb-0 align-middle">
                                <thead class="table-light">
                                    <tr>
                                        <th>Student ID</th>
                                        <th>Student Name</th>
                                        <th>University Email</th>
                                        <th>Class Standing</th>
                                        <th>Attendance</th>
                                        <th>Declared Major(s)</th>
                                        <?php if ($isPastRoster): ?>
                                            <th class="text-center">Final Grade</th>
                                        <?php else: ?>
                                            <th class="text-center">Midterm Grade</th>
                                            <th class="text-center">Final Exam Grade</th>
                                            <th class="text-center">Final Course Grade</th>
                                        <?php endif; ?>
                                        <th class="text-end pe-3">Profile</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($rosterStudents as $st): ?>
                                        <tr>
                                            <td><strong><?= (int)$st['Student_ID'] ?></strong></td>
                                            <td>
                                                <a href="../user_profile.php?user_id=<?= (int)$st['Student_ID'] ?>" class="text-decoration-none fw-semibold text-primary">
                                                    <?= htmlspecialchars($st['full_name'], ENT_QUOTES, 'UTF-8') ?>
                                                </a>
                                            </td>
                                            <td>
                                                <a href="mailto:<?= htmlspecialchars($st['User_Email'] ?? '', ENT_QUOTES, 'UTF-8') ?>" class="text-muted small">
                                                    <?= htmlspecialchars($st['User_Email'] ?? 'N/A', ENT_QUOTES, 'UTF-8') ?>
                                                </a>
                                            </td>
                                            <td><span class="badge bg-secondary"><?= htmlspecialchars($st['class_standing'], ENT_QUOTES, 'UTF-8') ?></span></td>
                                            <td><small class="text-muted"><?= htmlspecialchars($st['Attendance_Type'] ?? 'Full-Time', ENT_QUOTES, 'UTF-8') ?></small></td>
                                            <td><small><?= htmlspecialchars($st['Major_Name'] ?? 'Undeclared', ENT_QUOTES, 'UTF-8') ?></small></td>
                                            <?php if ($isPastRoster): ?>
                                                <td class="text-center">
                                                    <?= !empty($st['Grade']) ? '<span class="badge bg-success">' . htmlspecialchars($st['Grade'], ENT_QUOTES, 'UTF-8') . '</span>' : '<span class="text-muted">&mdash;</span>' ?>
                                                </td>
                                            <?php else: ?>
                                                <td class="text-center">
                                                    <?= !empty($st['Midterm_Grade']) ? '<span class="badge bg-info text-dark">' . htmlspecialchars($st['Midterm_Grade'], ENT_QUOTES, 'UTF-8') . '</span>' : '<span class="text-muted">&mdash;</span>' ?>
                                                </td>
                                                <td class="text-center">
                                                    <?= !empty($st['Final_Grade']) ? '<span class="badge bg-primary">' . htmlspecialchars($st['Final_Grade'], ENT_QUOTES, 'UTF-8') . '</span>' : '<span class="text-muted">&mdash;</span>' ?>
                                                </td>
                                                <td class="text-center">
                                                    <?= !empty($st['Grade']) ? '<span class="badge bg-success">' . htmlspecialchars($st['Grade'], ENT_QUOTES, 'UTF-8') . '</span>' : '<span class="text-muted">&mdash;</span>' ?>
                                                </td>
                                            <?php endif; ?>
                                            <td class="text-end pe-3">
                                                <a href="../user_profile.php?user_id=<?= (int)$st['Student_ID'] ?>" class="btn btn-outline-primary btn-sm py-0 px-2" style="font-size: 0.8rem;">
                                                    View &rarr;
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
        <?php endif; ?>

        <!-- Results Table Card -->
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <h5 class="mb-0 fw-bold">
                    Schedule Offerings for <?= htmlspecialchars($semester_display, ENT_QUOTES, 'UTF-8') ?>
                </h5>
                <span class="badge bg-secondary"><?= (int)$totalSections ?> Section<?= $totalSections !== 1 ? 's' : '' ?> Found</span>
            </div>
            <div class="card-body p-0">
                <?php if (empty($sections)): ?>
                    <div class="p-5 text-center text-muted">
                        <h5 class="fw-bold text-secondary">No Course Sections Found</h5>
                        <p class="mb-3">No course sections match your filter criteria in <?= htmlspecialchars($semester_display, ENT_QUOTES, 'UTF-8') ?>.</p>
                        <a href="master_schedule.php?semester=<?= (int)$selected_sem_id ?>" class="btn btn-outline-primary btn-sm">
                            Clear Filters &amp; View All Sections
                        </a>
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
                                    <th>Instructor</th>
                                    <th>Day / Time</th>
                                    <th>Location</th>
                                    <th class="text-center">Enrollment / Allowed</th>
                                    <th class="text-center">Status</th>
                                    <th class="text-end pe-3">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($sections as $sec):
                                    $secCrn = (int)$sec['CRN'];
                                    $secCourseId = $sec['Course_ID'];
                                    $seatsAvailable = (int)$sec['Available_seats'];
                                    $enrolledCount = (int)($sec['Enrolled_Count'] ?? 0);
                                    $allowedSeats = $enrolledCount + $seatsAvailable;
                                    $secStatus = $sec['Status'] ?? 'Active';
                                ?>
                                    <tr>
                                        <td><strong><?= $secCrn ?></strong></td>
                                        <td><span class="badge text-bg-light border"><?= htmlspecialchars($secCourseId, ENT_QUOTES, 'UTF-8') ?></span></td>
                                        <td><?= htmlspecialchars((string)$sec['Section_Number'], ENT_QUOTES, 'UTF-8') ?></td>
                                        <td>
                                            <span class="fw-semibold"><?= htmlspecialchars($sec['Course_Name'], ENT_QUOTES, 'UTF-8') ?></span>
                                            <span class="badge bg-secondary-subtle text-secondary small ms-1"><?= htmlspecialchars($sec['Course_Type'], ENT_QUOTES, 'UTF-8') ?></span>
                                            <div class="mt-1 d-flex gap-1">
                                                <button type="button" class="btn btn-outline-info btn-sm py-0 px-2" style="font-size: 0.75rem;"
                                                        data-bs-toggle="modal" data-bs-target="#prereqModal"
                                                        data-course-id="<?= htmlspecialchars($secCourseId, ENT_QUOTES, 'UTF-8') ?>"
                                                        data-course-name="<?= htmlspecialchars($sec['Course_Name'], ENT_QUOTES, 'UTF-8') ?>"
                                                        data-prereqs="<?= htmlspecialchars(json_encode($sec['prerequisites'] ?? []), ENT_QUOTES, 'UTF-8') ?>">
                                                    Prerequisites
                                                </button>
                                                <button type="button" class="btn btn-outline-secondary btn-sm py-0 px-2" style="font-size: 0.75rem;"
                                                        data-bs-toggle="modal" data-bs-target="#courseDescModal"
                                                        data-course-id="<?= htmlspecialchars($secCourseId, ENT_QUOTES, 'UTF-8') ?>"
                                                        data-course-name="<?= htmlspecialchars($sec['Course_Name'], ENT_QUOTES, 'UTF-8') ?>"
                                                        data-course-desc="<?= htmlspecialchars($sec['Course_Description'] ?? 'No description available for this course.', ENT_QUOTES, 'UTF-8') ?>">
                                                    Description
                                                </button>
                                            </div>
                                        </td>
                                        <td><small class="text-muted"><?= htmlspecialchars($sec['Department_Name'] ?? 'General', ENT_QUOTES, 'UTF-8') ?></small></td>
                                        <td class="text-center fw-bold"><?= (int)$sec['Course_Credits'] ?></td>
                                        <td>
                                            <?= renderFacultyLink($sec['Faculty_ID'] ?? 0, $sec['Instructor_Name'], '../') ?>
                                            <?php if (!empty($sec['Instructor_Rank'])): ?>
                                                <div class="text-muted" style="font-size: 0.75rem;"><?= htmlspecialchars($sec['Instructor_Rank'], ENT_QUOTES, 'UTF-8') ?></div>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= formatSchedule($sec['Time_Slot_Day'], $sec['Start_Time'], $sec['End_Time']) ?></td>
                                        <td>
                                            <?= htmlspecialchars($sec['Building_Name'] . ' ' . $sec['Room_NO'], ENT_QUOTES, 'UTF-8') ?>
                                            <div class="text-muted" style="font-size: 0.75rem;">Type: <?= htmlspecialchars($sec['Room_Type'] ?? 'Lecture', ENT_QUOTES, 'UTF-8') ?></div>
                                        </td>
                                        <td class="text-center">
                                            <?php if ($is_past_sem || $secStatus === 'Past'): ?>
                                                <span class="badge bg-secondary"><?= $enrolledCount ?> Enrolled</span>
                                            <?php else: ?>
                                                <?php if ($seatsAvailable <= 0): ?>
                                                    <span class="badge bg-danger"><?= $enrolledCount ?> / <?= $allowedSeats ?> (Full)</span>
                                                <?php else: ?>
                                                    <span class="badge bg-success"><?= $enrolledCount ?> / <?= $allowedSeats ?></span>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center">
                                            <?php if ($secStatus === 'Active'): ?>
                                                <span class="badge bg-success">Active</span>
                                            <?php elseif ($secStatus === 'Cancelled'): ?>
                                                <span class="badge bg-danger">Cancelled</span>
                                            <?php elseif ($secStatus === 'Past'): ?>
                                                <span class="badge bg-secondary">Past</span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary"><?= htmlspecialchars($secStatus, ENT_QUOTES, 'UTF-8') ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end pe-3">
                                            <div class="btn-group btn-group-sm">
                                                <a href="master_schedule.php?semester=<?= (int)$selected_sem_id ?>&roster_crn=<?= $secCrn ?>#admin-roster-card" class="btn btn-outline-primary" title="View Student Enrollment Roster">
                                                    Roster
                                                </a>
                                                <a href="master_schedule.php?semester=<?= (int)$selected_sem_id ?>&attendance_crn=<?= $secCrn ?>#admin-attendance-card" class="btn btn-outline-info" title="View Attendance Matrix">
                                                    Attendance
                                                </a>
                                                <?php if (($_SESSION['Security_Level'] ?? 1) >= 2 && !$is_past_sem && $secStatus !== 'Past'): ?>
                                                    <a href="edit_course_section.php?crn=<?= $secCrn ?>" class="btn btn-outline-warning" title="Edit Section">
                                                        Edit
                                                    </a>
                                                <?php endif; ?>
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

        <!-- Reusable Prerequisites Modal -->
        <div class="modal fade" id="prereqModal" tabindex="-1" aria-labelledby="prereqModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title fs-6 fw-bold" id="prereqModalLabel">Prerequisites</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body" id="prereqModalContent">
                        <!-- Populated by JavaScript -->
                    </div>
                    <div class="modal-footer py-2">
                        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Reusable Course Description Modal -->
        <div class="modal fade" id="courseDescModal" tabindex="-1" aria-labelledby="courseDescModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title fs-6 fw-bold" id="courseDescModalLabel">Course Description</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <p class="mb-0 text-secondary" id="courseDescContent" style="white-space: pre-line;">
                            <!-- Populated by JavaScript -->
                        </p>
                    </div>
                    <div class="modal-footer py-2">
                        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
                    </div>
                </div>
            </div>
        </div>

    </div>

    <!-- Bootstrap 5.3 JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const prereqModal = document.getElementById('prereqModal');
        if (prereqModal) {
            prereqModal.addEventListener('show.bs.modal', function(event) {
                const btn = event.relatedTarget;
                const courseId = btn.getAttribute('data-course-id') || '';
                const courseName = btn.getAttribute('data-course-name') || '';
                let prereqs = [];
                try {
                    prereqs = JSON.parse(btn.getAttribute('data-prereqs') || '[]');
                } catch(e) {}

                document.getElementById('prereqModalLabel').textContent = 'Prerequisites: ' + courseId + ' - ' + courseName;
                const container = document.getElementById('prereqModalContent');
                container.replaceChildren();

                if (prereqs && prereqs.length > 0) {
                    const p = document.createElement('p');
                    p.className = 'small text-muted mb-2';
                    p.textContent = 'Required prerequisite course(s):';
                    container.appendChild(p);

                    const ul = document.createElement('ul');
                    ul.className = 'list-group list-group-flush border rounded';
                    prereqs.forEach(function(pr) {
                        const li = document.createElement('li');
                        li.className = 'list-group-item d-flex justify-content-between align-items-center';
                        li.textContent = pr.Course_ID + ' - ' + pr.Course_Name;
                        ul.appendChild(li);
                    });
                    container.appendChild(ul);
                } else {
                    const div = document.createElement('div');
                    div.className = 'alert alert-secondary mb-0 py-2 small';
                    div.textContent = 'None — No prerequisites required for this course.';
                    container.appendChild(div);
                }
            });
        }

        const descModal = document.getElementById('courseDescModal');
        if (descModal) {
            descModal.addEventListener('show.bs.modal', function(event) {
                const btn = event.relatedTarget;
                const courseId = btn.getAttribute('data-course-id') || '';
                const courseName = btn.getAttribute('data-course-name') || '';
                const desc = btn.getAttribute('data-course-desc') || 'No description available for this course.';

                document.getElementById('courseDescModalLabel').textContent = 'Description: ' + courseId + ' - ' + courseName;
                document.getElementById('courseDescContent').textContent = desc;
            });
        }
    });
    </script>
</body>
</html>
