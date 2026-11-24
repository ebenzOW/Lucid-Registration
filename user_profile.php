<?php
/**
 * Lucid University Course Registration System
 * Standalone User Profile Viewer
 *
 * Dedicated profile viewing interface displaying user account information
 * and granular role-specific academic and professional records.
 * Supports public faculty directory browsing, teaching schedule inspection across terms,
 * section roster drill-downs, and advisee list tracking.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION["User_ID"])) {
    header("Location: login.php");
    exit;
}

$viewer_id   = (int)$_SESSION["User_ID"];
$viewer_type = $_SESSION["User_Type"] ?? '';

require_once 'db_connect.php';
require_once __DIR__ . '/includes/ui_helpers.php';
require_once __DIR__ . '/includes/auth_helpers.php';
require_once __DIR__ . '/includes/student_helpers.php';
require_once __DIR__ . '/includes/faculty_helpers.php';
require_once __DIR__ . '/includes/schedule_helpers.php';

$target_id = isset($_GET['user_id']) ? (int)$_GET['user_id'] : $viewer_id;

// 1. Fetch Target User Record
$userStmt = $conn->prepare("
    SELECT u.User_ID, u.User_Type, u.User_FName, u.User_MName, u.User_LName,
           u.User_Gender, u.User_DOB, u.User_PhoneNo,
           u.Street_No, u.Street_Name, u.City, u.State, u.Country, u.Zip_Code,
           l.User_Email
    FROM `USER` u
    LEFT JOIN LOGIN l ON u.User_ID = l.User_ID
    WHERE u.User_ID = ?
");
$userStmt->bind_param("i", $target_id);
$userStmt->execute();
$user = $userStmt->get_result()->fetch_assoc();
$userStmt->close();

if (!$user) {
    // If target not found, fall back to viewer's own profile
    $target_id = $viewer_id;
    $userStmt = $conn->prepare("
        SELECT u.User_ID, u.User_Type, u.User_FName, u.User_MName, u.User_LName,
               u.User_Gender, u.User_DOB, u.User_PhoneNo,
               u.Street_No, u.Street_Name, u.City, u.State, u.Country, u.Zip_Code,
               l.User_Email
        FROM `USER` u
        LEFT JOIN LOGIN l ON u.User_ID = l.User_ID
        WHERE u.User_ID = ?
    ");
    $userStmt->bind_param("i", $target_id);
    $userStmt->execute();
    $user = $userStmt->get_result()->fetch_assoc();
    $userStmt->close();
}

if (!$user) {
    die("User record not found.");
}

$target_type = $user['User_Type'];

// 2. Permission & Access Control
// - Users can always view their own profile.
// - Stat-Dept users can only view their own profile.
// - Admins can view any profile.
// - All authenticated users can view Faculty profiles in directory view.
// - Faculty can view student profiles if they advise or teach them.
$hasAccess = false;
$canViewPrivate = false;

if ($target_id === $viewer_id) {
    $hasAccess = true;
    $canViewPrivate = true;
} elseif ($viewer_type === 'Admin') {
    $hasAccess = true;
    $canViewPrivate = true;
} elseif ($viewer_type === 'Stat-Dept') {
    // Stat-Dept can only view aggregate statistics, not individual profiles
    $hasAccess = false; 
    $canViewPrivate = false;
} elseif ($target_type === 'Faculty') {
    // Public directory view for all logged-in students and faculty
    $hasAccess = true;
    $canViewPrivate = ($viewer_type === 'Admin' || $viewer_id === $target_id);
} elseif ($viewer_type === 'Faculty' && $target_type === 'Student') {
    $hasAccess = canViewerAccessStudentRecord($conn, $viewer_id, $viewer_type, $target_id);
    $canViewPrivate = $hasAccess;
}

if (!$hasAccess) {
    header("Location: user_profile.php?user_id=" . $viewer_id);
    exit;
}

// 3. Format Base Identity Information (with privacy masking for directory views)
$fullName = trim($user['User_FName'] . ' ' . (!empty($user['User_MName']) ? $user['User_MName'] . ' ' : '') . $user['User_LName']);

if ($canViewPrivate) {
    $phoneDisplay   = $user['User_PhoneNo'] ?? 'N/A';
    $genderDisplay  = $user['User_Gender'] ?? 'N/A';
    $dobDisplay     = $user['User_DOB'] ?? 'N/A';
    $fullAddress    = formatAddress($user);
    $cityStateZip   = '';
    $countryDisplay = '';
} else {
    $phoneDisplay   = '<span class="text-muted fst-italic">Protected (Directory View)</span>';
    $genderDisplay  = '<span class="text-muted fst-italic">Protected</span>';
    $dobDisplay     = '<span class="text-muted fst-italic">Protected</span>';
    $fullAddress    = '<span class="text-muted fst-italic">Protected (Directory View)</span>';
    $cityStateZip   = '';
    $countryDisplay = '';
}

// 4. Fetch Role-Specific Details
$studentData      = null;
$facultyData      = null;
$adminData        = null;
$statData         = null;
$facultyAdvisees  = [];
$teachingSections = [];
$rosterSection    = null;
$rosterStudents   = null;

if ($target_type === 'Student') {
    $sStmt = $conn->prepare("
        SELECT s.Student_Year, s.Student_Type, s.Credits_Earned,
               d.Department_Name,
               COALESCE(ug.Undergrad_Type, g.Grad_Type, 'Full-Time') AS Attendance_Type,
               g.Grad_Program, g.Thesis
        FROM STUDENT s
        LEFT JOIN DEPARTMENT d ON s.Dept_ID = d.Department_ID
        LEFT JOIN UNDERGRADUATE_STUDENT ug ON s.Student_ID = ug.Student_ID
        LEFT JOIN GRADUATE_STUDENT g ON s.Student_ID = g.Student_ID
        WHERE s.Student_ID = ?
    ");
    $sStmt->bind_param("i", $target_id);
    $sStmt->execute();
    $studentData = $sStmt->get_result()->fetch_assoc();
    $sStmt->close();

    $yearMap = [1 => 'Freshman', 2 => 'Sophomore', 3 => 'Junior', 4 => 'Senior', 5 => 'Graduate'];
    $yearVal = (int)($studentData['Student_Year'] ?? 1);
    $studentData['class_standing'] = (strcasecmp($studentData['Student_Type'] ?? '', 'Graduate') === 0)
        ? 'Graduate'
        : ($yearMap[$yearVal] ?? 'Undergraduate');

    // Fetch Cumulative GPA
    $transcript = getStudentTranscriptData($conn, $target_id);
    $studentData['cum_gpa'] = $transcript['cum_gpa'];

    // Fetch Majors
    $mStmt = $conn->prepare("
        SELECT m.Major_Name
        FROM STUDENT_MAJORS sm
        JOIN MAJORS m ON sm.Major_ID = m.Major_ID
        WHERE sm.Student_ID = ?
        ORDER BY sm.Major_ID ASC
    ");
    $mStmt->bind_param("i", $target_id);
    $mStmt->execute();
    $mRes = $mStmt->get_result();
    $majors = [];
    while ($mRow = $mRes->fetch_assoc()) {
        $majors[] = $mRow['Major_Name'];
    }
    $mStmt->close();
    $studentData['majors'] = $majors;

    // Fetch Minor
    $minStmt = $conn->prepare("
        SELECT m.Minor_Name
        FROM STUDENT_MINORS sm
        JOIN MINORS m ON sm.Minor_ID = m.Minor_ID
        WHERE sm.Student_ID = ?
        ORDER BY sm.Minor_ID ASC
    ");
    $minStmt->bind_param("i", $target_id);
    $minStmt->execute();
    $minRes = $minStmt->get_result();
    $minors = [];
    while ($minRow = $minRes->fetch_assoc()) {
        $minors[] = $minRow['Minor_Name'];
    }
    $minStmt->close();
    $studentData['minor'] = $minors[0] ?? null;

    // Fetch Assigned Advisor
    $advStmt = $conn->prepare("
        SELECT u.User_FName, u.User_LName, l.User_Email, f.Faculty_ID
        FROM ADVISOR_STUDENT a
        JOIN FACULTY f ON a.Faculty_ID = f.Faculty_ID
        JOIN `USER` u ON f.Faculty_ID = u.User_ID
        LEFT JOIN LOGIN l ON u.User_ID = l.User_ID
        WHERE a.Student_ID = ?
        LIMIT 1
    ");
    $advStmt->bind_param("i", $target_id);
    $advStmt->execute();
    $studentData['advisor'] = $advStmt->get_result()->fetch_assoc();
    $advStmt->close();

    // Fetch Active Holds Count
    $holds = getStudentHolds($conn, $target_id);
    $studentData['holds_count'] = count($holds);

} elseif ($target_type === 'Faculty') {
    // 1. Fetch Faculty Profile via Helper
    $facultyProfile = getFacultyProfile($conn, $target_id);
    $facultyData = $facultyProfile ?: [];
    $facultyData['Building_Name'] = $facultyProfile['Office_Building_Name'] ?? '';
    $facultyData['Room_NO'] = $facultyProfile['Office_Room_NO'] ?? '';

    // 2. Department Roster Assignments
    $drStmt = $conn->prepare("
        SELECT d.Department_Name, dr.Percent_Time, dr.Date_of_Assignment
        FROM DEPARTMENT_ROSTER dr
        JOIN DEPARTMENT d ON dr.Department_ID = d.Department_ID
        WHERE dr.Faculty_ID = ?
        ORDER BY dr.Percent_Time DESC
    ");
    $drStmt->bind_param("i", $target_id);
    $drStmt->execute();
    $drRes = $drStmt->get_result();
    $departments = [];
    while ($drRow = $drRes->fetch_assoc()) {
        $departments[] = $drRow;
    }
    $drStmt->close();
    $facultyData['departments'] = $departments;

    // 3. Leadership Roles
    $leadStmt = $conn->prepare("
        SELECT d.Department_Name, 'Department Chair' AS Role_Title
        FROM DEPARTMENT d
        WHERE d.Chair_ID = ?
        UNION
        SELECT d.Department_Name, dm.Role AS Role_Title
        FROM DEPARTMENT_MANAGERS dm
        JOIN DEPARTMENT d ON dm.Department_ID = d.Department_ID
        WHERE dm.Faculty_ID = ?
    ");
    $leadStmt->bind_param("ii", $target_id, $target_id);
    $leadStmt->execute();
    $leadRes = $leadStmt->get_result();
    $leadership = [];
    while ($leadRow = $leadRes->fetch_assoc()) {
        $leadership[] = $leadRow;
    }
    $leadStmt->close();
    $facultyData['leadership'] = $leadership;

    // 4. Teaching Section Counts (Fall 2026 and Spring 2027)
    $secCountStmt = $conn->prepare("
        SELECT
            (SELECT COUNT(*) FROM COURSE_SECTIONS WHERE Faculty_ID = ? AND Semester_ID = 202608) AS fall_load,
            (SELECT COUNT(*) FROM COURSE_SECTIONS WHERE Faculty_ID = ? AND Semester_ID = 202701) AS spring_load
    ");
    $secCountStmt->bind_param("ii", $target_id, $target_id);
    $secCountStmt->execute();
    $loadCounts = $secCountStmt->get_result()->fetch_assoc();
    $secCountStmt->close();
    $facultyData['fall_load']   = (int)($loadCounts['fall_load'] ?? 0);
    $facultyData['spring_load'] = (int)($loadCounts['spring_load'] ?? 0);

    // 5. Advisees List & Count
    if ($canViewPrivate) {
        $facultyAdvisees = getFacultyAdvisees($conn, $target_id);
        $facultyData['advisee_count'] = count($facultyAdvisees);
    } else {
        $advCountStmt = $conn->prepare("SELECT COUNT(*) AS cnt FROM ADVISOR_STUDENT WHERE Faculty_ID = ?");
        $advCountStmt->bind_param("i", $target_id);
        $advCountStmt->execute();
        $facultyData['advisee_count'] = (int)($advCountStmt->get_result()->fetch_assoc()['cnt'] ?? 0);
        $advCountStmt->close();
    }

    // 6. Full Teaching Schedule across Semesters
    $teachingSchedules = getFacultyScheduleBySemester($conn, $target_id, null);
    $teachingSections = $teachingSchedules['sections'];

    // 7. Section Roster Drill-Down Resolution
    $roster_crn = isset($_GET['roster_crn']) ? filter_var($_GET['roster_crn'], FILTER_VALIDATE_INT) : null;
    if ($roster_crn && ($canViewPrivate || $viewer_type === 'Admin')) {
        foreach ($teachingSections as $secItem) {
            if ((int)$secItem['CRN'] === $roster_crn) {
                $rosterSection = $secItem;
                break;
            }
        }
        if ($rosterSection || $viewer_type === 'Admin') {
            $rosterStudents = getSectionRoster($conn, $roster_crn, ($viewer_type === 'Admin' ? null : $target_id));
        }
    }

} elseif ($target_type === 'Admin') {
    $aStmt = $conn->prepare("SELECT Security_Level FROM ADMIN WHERE Admin_ID = ?");
    $aStmt->bind_param("i", $target_id);
    $aStmt->execute();
    $adminData = $aStmt->get_result()->fetch_assoc();
    $aStmt->close();

} elseif ($target_type === 'Stat-Dept') {
    $statData = [
        'title' => 'Statistical & Institutional Research Department Staff',
        'status' => 'Full-Time Staff Member',
        'clearance' => 'Anonymized Institutional Academic Records Clearance'
    ];
}

$validAdminViews = ['details', 'enrollment', 'grades', 'attendance', 'transcript', 'audit'];
$adminView = isset($_GET['view']) && in_array($_GET['view'], $validAdminViews, true) ? $_GET['view'] : 'details';

$fallSchedule = null;
$springSchedule = null;
$attRecords = null;
$transcriptData = null;
$auditData = null;
$studentHolds = [];

if ($target_type === 'Student' && $viewer_type === 'Admin') {
    $studentHolds = getStudentHolds($conn, $target_id);
    if (in_array($adminView, ['enrollment', 'grades'])) {
        $fallSchedule = getStudentScheduleBySemester($conn, $target_id, 202608);
        $springSchedule = getStudentScheduleBySemester($conn, $target_id, 202701);
    } elseif ($adminView === 'attendance') {
        $attRecords = getStudentAttendanceRecords($conn, $target_id, 202608);
    } elseif ($adminView === 'transcript') {
        $transcriptData = getStudentTranscriptData($conn, $target_id);
    } elseif ($adminView === 'audit') {
        $auditData = getDegreeAuditData($conn, $target_id);
    }
}

// Determine Dashboard Return URL
$dashboardUrl = match($viewer_type) {
    'Student'   => 'student/student_dashboard.php',
    'Faculty'   => 'faculty/faculty_dashboard.php',
    'Admin'     => 'admin/admin_dashboard.php',
    'Stat-Dept' => 'stat_department/stat_department_dashboard.php',
    default     => 'login.php'
};
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Profile - <?= htmlspecialchars($fullName, ENT_QUOTES, 'UTF-8') ?> | Lucid University</title>
    <!-- Bootstrap 5.3 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

    <!-- Top Navigation Bar -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark mb-4 shadow-sm">
        <div class="container-fluid px-lg-5">
            <a class="navbar-brand fw-bold" href="<?= htmlspecialchars($dashboardUrl, ENT_QUOTES, 'UTF-8') ?>">
                Lucid University
                <span class="badge bg-secondary ms-1 fs-6"><?= htmlspecialchars($viewer_type, ENT_QUOTES, 'UTF-8') ?></span>
            </a>
            <div class="d-flex align-items-center gap-3">
                <a href="<?= htmlspecialchars($dashboardUrl, ENT_QUOTES, 'UTF-8') ?>" class="btn btn-outline-light btn-sm">
                    &larr; Return to Dashboard
                </a>
                <form action="logout.php" method="POST" class="m-0">
                    <button type="submit" class="btn btn-outline-danger btn-sm">Log Out</button>
                </form>
            </div>
        </div>
    </nav>

    <div class="container-fluid px-lg-5 mb-5">

    <?php if ($target_type === 'Student' && $viewer_type === 'Admin'): 
        $standing = $studentData['class_standing'] ?? 'Undergraduate';
        $attType = $studentData['Attendance_Type'] ?? 'Full-Time';
        $cumGpa = number_format((float)($studentData['cum_gpa'] ?? 0), 2);
        $creditsEarned = (int)($studentData['Credits_Earned'] ?? 0);
        $majorsList = !empty($studentData['majors']) ? implode(', ', $studentData['majors']) : 'Undeclared';
        $minorName = $studentData['minor'] ?? 'None';
        $deptName = $studentData['Department_Name'] ?? 'General Academics';
        $holdsCount = count($studentHolds);
    ?>
        <!-- Student Summary Header Card -->
        <div class="card shadow-sm mb-4 border-0 border-top border-primary border-4">
            <div class="card-body p-4 bg-white rounded-3">
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
                    <div class="d-flex align-items-center gap-3">
                        <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center fw-bold fs-2" style="width: 72px; height: 72px;">
                            <?= htmlspecialchars(strtoupper(substr($user['User_FName'], 0, 1) . substr($user['User_LName'], 0, 1)), ENT_QUOTES, 'UTF-8') ?>
                        </div>
                        <div>
                            <div class="d-flex align-items-center gap-2 flex-wrap mb-1">
                                <h2 class="fw-bold mb-0"><?= htmlspecialchars($fullName, ENT_QUOTES, 'UTF-8') ?></h2>
                                <span class="badge bg-primary fs-6">ID: <?= (int)$target_id ?></span>
                                <span class="badge bg-secondary"><?= htmlspecialchars($standing, ENT_QUOTES, 'UTF-8') ?></span>
                                <span class="badge bg-info text-dark"><?= htmlspecialchars($attType, ENT_QUOTES, 'UTF-8') ?></span>
                                <?php if ($holdsCount > 0): ?>
                                    <span class="badge bg-danger"><?= $holdsCount ?> Active Hold(s)</span>
                                <?php else: ?>
                                    <span class="badge bg-success">No Holds</span>
                                <?php endif; ?>
                            </div>
                            <div class="text-secondary small">
                                Department: <strong><?= htmlspecialchars($deptName, ENT_QUOTES, 'UTF-8') ?></strong> &bull;
                                Major: <strong><?= htmlspecialchars($majorsList, ENT_QUOTES, 'UTF-8') ?></strong> &bull;
                                Minor: <strong><?= htmlspecialchars($minorName, ENT_QUOTES, 'UTF-8') ?></strong> &bull;
                                GPA: <strong class="text-dark"><?= $cumGpa ?></strong> &bull;
                                Earned Credits: <strong class="text-dark"><?= $creditsEarned ?></strong>
                            </div>
                        </div>
                    </div>
                    <div class="d-flex gap-2">
                        <a href="admin/view_users.php?view=undergrads" class="btn btn-outline-secondary btn-sm">
                            &larr; User Directory
                        </a>
                        <a href="admin/admin_dashboard.php" class="btn btn-outline-primary btn-sm">
                            Admin Dashboard &rarr;
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- 2-Column Student Inspection Workspace -->
        <div class="row g-4">
            <!-- Left Column: Navigation Menu -->
            <div class="col-lg-3">
                <div class="card shadow-sm border-0 sticky-top" style="top: 20px;">
                    <div class="card-header bg-white py-3">
                        <h6 class="mb-0 fw-bold text-muted text-uppercase small">Student Navigation</h6>
                    </div>
                    <div class="list-group list-group-flush p-2">
                        <a href="user_profile.php?user_id=<?= $target_id ?>&view=details" class="list-group-item list-group-item-action rounded mb-1 <?= $adminView === 'details' ? 'active fw-bold' : '' ?>">
                            Demographic &amp; Student Details
                        </a>
                        <a href="user_profile.php?user_id=<?= $target_id ?>&view=enrollment" class="list-group-item list-group-item-action rounded mb-1 <?= $adminView === 'enrollment' ? 'active fw-bold' : '' ?>">
                            Current &amp; Upcoming Enrollment
                        </a>
                        <a href="user_profile.php?user_id=<?= $target_id ?>&view=grades" class="list-group-item list-group-item-action rounded mb-1 <?= $adminView === 'grades' ? 'active fw-bold' : '' ?>">
                            Current Semester Grades
                        </a>
                        <a href="user_profile.php?user_id=<?= $target_id ?>&view=attendance" class="list-group-item list-group-item-action rounded mb-1 <?= $adminView === 'attendance' ? 'active fw-bold' : '' ?>">
                            Fall 2026 Attendance
                        </a>
                        <a href="user_profile.php?user_id=<?= $target_id ?>&view=transcript" class="list-group-item list-group-item-action rounded mb-1 <?= $adminView === 'transcript' ? 'active fw-bold' : '' ?>">
                            Academic Transcript
                        </a>
                        <a href="user_profile.php?user_id=<?= $target_id ?>&view=audit" class="list-group-item list-group-item-action rounded <?= $adminView === 'audit' ? 'active fw-bold' : '' ?>">
                            Degree Audit Progress
                        </a>
                    </div>
                </div>
            </div>

            <!-- Right Column: Content Workspace -->
            <div class="col-lg-9">
                <?php if ($adminView === 'enrollment'): ?>
                    <!-- Enrollment Tab -->
                    <!-- Fall 2026 Card -->
                    <div class="card shadow-sm border-0 mb-4">
                        <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                            <h5 class="mb-0 fw-bold">Fall 2026 Enrolled Sections (Current Term)</h5>
                            <span class="badge bg-primary"><?= (int)($fallSchedule['total_credits'] ?? 0) ?> Total Credits</span>
                        </div>
                        <div class="card-body p-0">
                            <?php if (empty($fallSchedule['courses'])): ?>
                                <div class="p-4 text-center text-muted">No course sections enrolled for Fall 2026.</div>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-hover table-striped mb-0 align-middle small">
                                        <thead class="table-light">
                                            <tr>
                                                <th>CRN</th>
                                                <th>Course ID</th>
                                                <th>Sec</th>
                                                <th>Course Name</th>
                                                <th class="text-center">Credits</th>
                                                <th>Instructor</th>
                                                <th>Schedule</th>
                                                <th>Room</th>
                                                <th class="text-center">Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($fallSchedule['courses'] as $c): ?>
                                                <tr>
                                                    <td><strong><?= (int)$c['CRN'] ?></strong></td>
                                                    <td><span class="badge text-bg-light border"><?= htmlspecialchars($c['Course_ID'], ENT_QUOTES, 'UTF-8') ?></span></td>
                                                    <td><?= htmlspecialchars((string)$c['Section_Number'], ENT_QUOTES, 'UTF-8') ?></td>
                                                    <td class="fw-semibold"><?= htmlspecialchars($c['Course_Name'], ENT_QUOTES, 'UTF-8') ?></td>
                                                    <td class="text-center fw-bold"><?= (int)$c['Course_Credits'] ?></td>
                                                    <td><?= renderFacultyLink($c['Faculty_ID'] ?? 0, $c['Instructor_Name'], '') ?></td>
                                                    <td><?= formatSchedule($c['Time_Slot_Day'], $c['Start_Time'], $c['End_Time']) ?></td>
                                                    <td><?= htmlspecialchars($c['Building_Name'] . ' ' . $c['Room_NO'], ENT_QUOTES, 'UTF-8') ?></td>
                                                    <td class="text-center">
                                                        <span class="badge <?= ($c['Status'] ?? 'Active') === 'Active' ? 'bg-success' : 'bg-secondary' ?>">
                                                            <?= htmlspecialchars($c['Status'] ?? 'Active', ENT_QUOTES, 'UTF-8') ?>
                                                        </span>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Spring 2027 Card -->
                    <div class="card shadow-sm border-0 mb-4">
                        <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                            <h5 class="mb-0 fw-bold">Spring 2027 Pre-Registration (Upcoming Term)</h5>
                            <span class="badge bg-primary"><?= (int)($springSchedule['total_credits'] ?? 0) ?> Total Credits</span>
                        </div>
                        <div class="card-body p-0">
                            <?php if (empty($springSchedule['courses'])): ?>
                                <div class="p-4 text-center text-muted">No pre-registered course sections for Spring 2027.</div>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-hover table-striped mb-0 align-middle small">
                                        <thead class="table-light">
                                            <tr>
                                                <th>CRN</th>
                                                <th>Course ID</th>
                                                <th>Sec</th>
                                                <th>Course Name</th>
                                                <th class="text-center">Credits</th>
                                                <th>Instructor</th>
                                                <th>Schedule</th>
                                                <th>Room</th>
                                                <th class="text-center">Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($springSchedule['courses'] as $c): ?>
                                                <tr>
                                                    <td><strong><?= (int)$c['CRN'] ?></strong></td>
                                                    <td><span class="badge text-bg-light border"><?= htmlspecialchars($c['Course_ID'], ENT_QUOTES, 'UTF-8') ?></span></td>
                                                    <td><?= htmlspecialchars((string)$c['Section_Number'], ENT_QUOTES, 'UTF-8') ?></td>
                                                    <td class="fw-semibold"><?= htmlspecialchars($c['Course_Name'], ENT_QUOTES, 'UTF-8') ?></td>
                                                    <td class="text-center fw-bold"><?= (int)$c['Course_Credits'] ?></td>
                                                    <td><?= renderFacultyLink($c['Faculty_ID'] ?? 0, $c['Instructor_Name'], '') ?></td>
                                                    <td><?= formatSchedule($c['Time_Slot_Day'], $c['Start_Time'], $c['End_Time']) ?></td>
                                                    <td><?= htmlspecialchars($c['Building_Name'] . ' ' . $c['Room_NO'], ENT_QUOTES, 'UTF-8') ?></td>
                                                    <td class="text-center">
                                                        <span class="badge <?= ($c['Status'] ?? 'Active') === 'Active' ? 'bg-success' : 'bg-secondary' ?>">
                                                            <?= htmlspecialchars($c['Status'] ?? 'Active', ENT_QUOTES, 'UTF-8') ?>
                                                        </span>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                <?php elseif ($adminView === 'grades'): ?>
                    <!-- Grades Tab -->
                    <div class="card shadow-sm border-0 mb-4">
                        <div class="card-header bg-white py-3">
                            <h5 class="mb-0 fw-bold">Current Semester Grades (Fall 2026)</h5>
                        </div>
                        <div class="card-body p-0">
                            <?php if (empty($fallSchedule['courses'])): ?>
                                <div class="p-4 text-center text-muted">No enrolled course sections found for Fall 2026.</div>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-hover table-striped mb-0 align-middle small">
                                        <thead class="table-light">
                                            <tr>
                                                <th>CRN</th>
                                                <th>Course</th>
                                                <th>Instructor</th>
                                                <th class="text-center">Credits</th>
                                                <th class="text-center">Midterm Grade</th>
                                                <th class="text-center">Final Exam Grade</th>
                                                <th class="text-center">Overall Course Grade</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($fallSchedule['courses'] as $c): ?>
                                                <tr>
                                                    <td><strong><?= (int)$c['CRN'] ?></strong></td>
                                                    <td>
                                                        <div class="fw-semibold"><?= htmlspecialchars($c['Course_ID'] . ' - ' . $c['Course_Name'], ENT_QUOTES, 'UTF-8') ?></div>
                                                        <small class="text-muted">Sec <?= htmlspecialchars((string)$c['Section_Number'], ENT_QUOTES, 'UTF-8') ?></small>
                                                    </td>
                                                    <td><?= renderFacultyLink($c['Faculty_ID'] ?? 0, $c['Instructor_Name'], '') ?></td>
                                                    <td class="text-center fw-bold"><?= (int)$c['Course_Credits'] ?></td>
                                                    <td class="text-center">
                                                        <?php if (!empty($c['Midterm_Grade'])): ?>
                                                            <span class="badge bg-info text-dark fs-6"><?= htmlspecialchars($c['Midterm_Grade'], ENT_QUOTES, 'UTF-8') ?></span>
                                                        <?php else: ?>
                                                            <span class="text-muted">&mdash;</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td class="text-center">
                                                        <?php if (!empty($c['Final_Grade'])): ?>
                                                            <span class="badge bg-primary fs-6"><?= htmlspecialchars($c['Final_Grade'], ENT_QUOTES, 'UTF-8') ?></span>
                                                        <?php else: ?>
                                                            <span class="text-muted">&mdash;</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td class="text-center">
                                                        <?php if (!empty($c['Grade'])): ?>
                                                            <span class="badge bg-success fs-6"><?= htmlspecialchars($c['Grade'], ENT_QUOTES, 'UTF-8') ?></span>
                                                        <?php else: ?>
                                                            <span class="badge bg-secondary-subtle text-secondary">In Progress</span>
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

                <?php elseif ($adminView === 'attendance'): ?>
                    <!-- Attendance Tab -->
                    <div class="mb-4">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5 class="mb-0 fw-bold">Fall 2026 Student Attendance Records</h5>
                            <span class="badge bg-secondary">Term 202608</span>
                        </div>
                        <?php if (empty($attRecords)): ?>
                            <div class="card shadow-sm border-0 p-5 text-center text-muted">
                                <h6>No Attendance Records Found</h6>
                                <p class="mb-0 small">No attendance sessions recorded for this student in Fall 2026.</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($attRecords as $cb): ?>
                                <div class="card shadow-sm border-0 mb-4">
                                    <div class="card-header bg-white py-3 d-flex flex-wrap justify-content-between align-items-center gap-2">
                                        <div>
                                            <span class="badge bg-primary fs-6">CRN <?= (int)$cb['CRN'] ?></span>
                                            <strong class="ms-1"><?= htmlspecialchars($cb['Course_ID'] . ' - ' . $cb['Course_Name'], ENT_QUOTES, 'UTF-8') ?></strong>
                                            <span class="text-muted small ms-1">(Sec <?= htmlspecialchars((string)$cb['Section_Number'], ENT_QUOTES, 'UTF-8') ?>)</span>
                                            <span class="text-secondary small ms-2">&bull; Instructor: <?= htmlspecialchars($cb['Instructor_Name'], ENT_QUOTES, 'UTF-8') ?></span>
                                        </div>
                                        <div class="d-flex gap-2 align-items-center">
                                            <span class="badge bg-light text-dark border">Recorded: <?= (int)$cb['total_sessions'] ?></span>
                                            <span class="badge bg-success-subtle text-success border border-success-subtle">Present: <?= (int)$cb['present_count'] ?></span>
                                            <span class="badge bg-danger-subtle text-danger border border-danger-subtle">Absent: <?= (int)$cb['absent_count'] ?></span>
                                            <span class="badge <?= $cb['attendance_pct'] >= 80 ? 'bg-success' : ($cb['attendance_pct'] >= 65 ? 'bg-warning text-dark' : 'bg-danger') ?>">
                                                Rate: <?= $cb['attendance_pct'] ?>%
                                            </span>
                                        </div>
                                    </div>
                                    <div class="card-body p-0">
                                        <?php if (empty($cb['records'])): ?>
                                            <div class="p-3 text-center text-muted small">No session logs for this course.</div>
                                        <?php else: ?>
                                            <div class="table-responsive" style="max-height: 280px;">
                                                <table class="table table-sm table-striped mb-0 align-middle small">
                                                    <thead class="table-light sticky-top">
                                                        <tr>
                                                            <th>Class Date</th>
                                                            <th>Day of Week</th>
                                                            <th>Attendance Status</th>
                                                            <th>Recorded Timestamp</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php foreach ($cb['records'] as $rec): 
                                                            $dayName = date('l', strtotime($rec['Class_Date']));
                                                        ?>
                                                            <tr>
                                                                <td class="fw-semibold"><?= htmlspecialchars($rec['Class_Date'], ENT_QUOTES, 'UTF-8') ?></td>
                                                                <td><?= $dayName ?></td>
                                                                <td>
                                                                    <?php if ($rec['Status'] === 'Present'): ?>
                                                                        <span class="badge bg-success">Present</span>
                                                                    <?php elseif ($rec['Status'] === 'Absent'): ?>
                                                                        <span class="badge bg-danger">Absent</span>
                                                                    <?php else: ?>
                                                                        <span class="badge bg-secondary"><?= htmlspecialchars($rec['Status'], ENT_QUOTES, 'UTF-8') ?></span>
                                                                    <?php endif; ?>
                                                                </td>
                                                                <td class="text-muted"><?= htmlspecialchars($rec['Recorded_At'] ?? 'Auto-logged', ENT_QUOTES, 'UTF-8') ?></td>
                                                            </tr>
                                                        <?php endforeach; ?>
                                                    </tbody>
                                                </table>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>

                <?php elseif ($adminView === 'transcript'): ?>
                    <!-- Academic Transcript Tab -->
                    <div class="card shadow-sm border-0 mb-4">
                        <div class="card-header bg-white py-3">
                            <h5 class="mb-0 fw-bold">Official Student Academic Transcript</h5>
                        </div>
                        <div class="card-body p-4">
                            <div class="row g-3 mb-4">
                                <div class="col-md-6">
                                    <div class="p-3 bg-light rounded border text-center">
                                        <div class="text-muted small text-uppercase fw-semibold">Cumulative GPA</div>
                                        <div class="display-6 fw-bold text-primary"><?= number_format((float)($transcriptData['cum_gpa'] ?? 0), 2) ?></div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="p-3 bg-light rounded border text-center">
                                        <div class="text-muted small text-uppercase fw-semibold">Total Credits Earned</div>
                                        <div class="display-6 fw-bold text-success"><?= (int)($transcriptData['total_credits'] ?? 0) ?></div>
                                    </div>
                                </div>
                            </div>

                            <!-- Semester Breakdown Tables -->
                            <h6 class="fw-bold mb-3">Coursework by Semester</h6>

                            <?php if (empty($transcriptData['terms'])): ?>
                                <div class="p-4 text-center text-muted border rounded">No historical transcript records on file.</div>
                            <?php else: ?>
                                <?php foreach ($transcriptData['terms'] as $semId => $sem): ?>
                                    <div class="card border mb-3">
                                        <div class="card-header bg-light py-2 d-flex justify-content-between align-items-center">
                                            <strong><?= htmlspecialchars($sem['semester_label'], ENT_QUOTES, 'UTF-8') ?></strong>
                                            <span class="small text-muted">Term GPA: <strong><?= number_format((float)($sem['term_gpa'] ?? 0), 2) ?></strong> &bull; Term Credits: <strong><?= (int)($sem['term_credits_earned'] ?? 0) ?></strong></span>
                                        </div>
                                        <div class="card-body p-0">
                                            <div class="table-responsive">
                                                <table class="table table-sm table-hover mb-0 align-middle small">
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
                                                        <?php foreach ($sem['courses'] as $sc): ?>
                                                            <tr>
                                                                <td><span class="badge text-bg-light border font-monospace"><?= htmlspecialchars($sc['Course_ID'], ENT_QUOTES, 'UTF-8') ?></span></td>
                                                                <td><?= htmlspecialchars($sc['Course_Name'], ENT_QUOTES, 'UTF-8') ?></td>
                                                                <td class="text-center"><?= (int)$sc['credits_attempted'] ?></td>
                                                                <td class="text-center"><?= (int)$sc['credits_earned'] ?></td>
                                                                <td class="text-center"><span class="badge bg-success"><?= htmlspecialchars($sc['Grade'], ENT_QUOTES, 'UTF-8') ?></span></td>
                                                            </tr>
                                                        <?php endforeach; ?>
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>

                <?php elseif ($adminView === 'audit'): ?>
                    <!-- Degree Audit Tab -->
                    <div class="card shadow-sm border-0 mb-4">
                        <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                            <h5 class="mb-0 fw-bold">Degree Audit &amp; Program Requirements Progress</h5>
                            <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-3 py-2">
                                Target: <?= (int)($auditData['degree_credit_target'] ?? 120) ?> Total Credits
                            </span>
                        </div>
                        <div class="card-body p-4">
                            <?php 
                                $degreeTarget = (int)($auditData['degree_credit_target'] ?? 120);
                                $totalEarned = (int)($auditData['total_earned_credits'] ?? 0);
                                $totalInProgress = (int)($auditData['total_in_progress_credits'] ?? 0);
                                $progressEarnedPct = $degreeTarget > 0 ? min(100, round(($totalEarned / $degreeTarget) * 100)) : 0;
                                $progressIPPct = $degreeTarget > 0 ? min(100 - $progressEarnedPct, round(($totalInProgress / $degreeTarget) * 100)) : 0;
                            ?>
                            
                            <!-- KPI Metrics Cards -->
                            <div class="row g-3 mb-4">
                                <div class="col-md-3">
                                    <div class="p-3 bg-light rounded border text-center h-100">
                                        <div class="text-muted small text-uppercase fw-semibold mb-1">Required Program Credits</div>
                                        <div class="fs-4 fw-bold text-dark mb-0">
                                            <?= (int)($auditData['unique_required_credits_completed'] ?? 0) ?> <span class="fs-6 text-muted">/ <?= (int)($auditData['unique_required_credits_total'] ?? 0) ?> Cr</span>
                                        </div>
                                        <div class="small text-primary fw-medium">
                                            +<?= (int)($auditData['unique_required_credits_in_progress'] ?? 0) ?> Cr In Progress
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="p-3 bg-light rounded border text-center h-100">
                                        <div class="text-muted small text-uppercase fw-semibold mb-1">Total Remaining to Target</div>
                                        <div class="fs-4 fw-bold text-danger mb-0">
                                            <?= (int)($auditData['total_credits_remaining_to_target'] ?? 0) ?> <span class="fs-6 text-muted">Cr Left</span>
                                        </div>
                                        <div class="small text-muted">
                                            <?= (int)($auditData['total_credits_remaining_after_in_progress'] ?? 0) ?> Cr left after In-Progress
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="p-3 bg-light rounded border text-center h-100">
                                        <div class="text-muted small text-uppercase fw-semibold mb-1">Completed Degree Credits</div>
                                        <div class="fs-4 fw-bold text-success mb-0"><?= $totalEarned ?> Cr</div>
                                        <div class="small text-muted">earned toward <?= $degreeTarget ?> credit degree</div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="p-3 bg-light rounded border text-center h-100">
                                        <div class="text-muted small text-uppercase fw-semibold mb-1">In-Progress Credits</div>
                                        <div class="fs-4 fw-bold text-primary mb-0"><?= $totalInProgress ?> Cr</div>
                                        <div class="small text-muted">current term enrollment</div>
                                    </div>
                                </div>
                            </div>

                            <!-- Degree Progress Bar -->
                            <div class="mb-2 d-flex justify-content-between align-items-center">
                                <span class="fw-bold small text-uppercase text-secondary">Overall Degree Completion</span>
                                <span class="small fw-semibold text-muted">
                                    Earned: <?= $totalEarned ?> Cr &bull; In Progress: <?= $totalInProgress ?> Cr &bull; Target: <?= $degreeTarget ?> Cr
                                </span>
                            </div>
                            <div class="progress" style="height: 24px;">
                                <div class="progress-bar bg-success fw-bold" role="progressbar" style="width: <?= $progressEarnedPct ?>%;">
                                    <?php if ($progressEarnedPct >= 8): ?><?= $progressEarnedPct ?>% Earned<?php endif; ?>
                                </div>
                                <?php if ($progressIPPct > 0): ?>
                                    <div class="progress-bar bg-primary progress-bar-striped progress-bar-animated fw-bold" role="progressbar" style="width: <?= $progressIPPct ?>%;">
                                        <?php if ($progressIPPct >= 8): ?>+<?= $progressIPPct ?>% In Progress<?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="d-flex justify-content-between align-items-center mt-2 small text-muted">
                                <div>
                                    <span class="badge bg-success me-1">&nbsp;</span> Completed Credits (<?= $totalEarned ?> Cr)
                                    <span class="badge bg-primary ms-3 me-1">&nbsp;</span> In-Progress Credits (<?= $totalInProgress ?> Cr)
                                </div>
                                <div>
                                    <strong><?= $progressEarnedPct + $progressIPPct ?>%</strong> Projected Completion
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Major Requirements Cards -->
                    <?php if (!empty($auditData['majors'])): ?>
                        <?php foreach ($auditData['majors'] as $major): ?>
                            <div class="card shadow-sm border-0 mb-4">
                                <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                                    <h5 class="card-title mb-0 fw-bold">
                                        Major Requirements: <?= htmlspecialchars($major['Major_Name'] ?? 'No Major Declared', ENT_QUOTES, 'UTF-8') ?>
                                    </h5>
                                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-3 py-2">
                                        <?= (int)($major['No_Credits_Required'] ?? 0) ?> Total Program Credits
                                    </span>
                                </div>
                                <div class="card-body p-0">
                                    <?php if (empty($major['requirements'])): ?>
                                        <div class="p-4 text-center text-muted">
                                            No curriculum requirements configured for this major.
                                        </div>
                                    <?php else: ?>
                                        <div class="table-responsive">
                                            <table class="table table-hover align-middle mb-0 small">
                                                <thead class="table-light">
                                                    <tr>
                                                        <th style="width: 14%;">Course ID</th>
                                                        <th style="width: 26%;">Course Name</th>
                                                        <th style="width: 16%;">Department</th>
                                                        <th class="text-center" style="width: 8%;">Credits</th>
                                                        <th class="text-center" style="width: 8%;">Min Grade</th>
                                                        <th class="text-center" style="width: 14%;">Semester Taken / Enrolled</th>
                                                        <th class="text-center" style="width: 7%;">Grade</th>
                                                        <th class="text-center" style="width: 7%;">Status</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($major['courses_by_department'] as $deptName => $deptReqs): ?>
                                                        <tr class="table-secondary">
                                                            <td colspan="8" class="fw-bold py-2">
                                                                <?= htmlspecialchars($deptName, ENT_QUOTES, 'UTF-8') ?>
                                                                <span class="badge bg-secondary ms-2">
                                                                    <?= count($deptReqs) ?> <?= count($deptReqs) === 1 ? 'Course' : 'Courses' ?> 
                                                                    (<?= array_sum(array_column($deptReqs, 'Course_Credits')) ?> Credits)
                                                                </span>
                                                            </td>
                                                        </tr>
                                                        <?php foreach ($deptReqs as $req): ?>
                                                            <tr>
                                                                <td class="fw-semibold font-monospace"><?= htmlspecialchars($req['Course_ID'], ENT_QUOTES, 'UTF-8') ?></td>
                                                                <td><?= htmlspecialchars($req['Course_Name'], ENT_QUOTES, 'UTF-8') ?></td>
                                                                <td><small class="text-muted"><?= htmlspecialchars($req['Department_Name'] ?? 'General', ENT_QUOTES, 'UTF-8') ?></small></td>
                                                                <td class="text-center"><?= (int)$req['Course_Credits'] ?></td>
                                                                <td class="text-center fw-medium"><?= htmlspecialchars($req['Min_Grade'], ENT_QUOTES, 'UTF-8') ?></td>
                                                                <td class="text-center">
                                                                    <?php if (!empty($req['Term_Taken'])): ?>
                                                                        <span class="badge <?= (strpos($req['Term_Taken'], 'In Progress') !== false) ? 'text-bg-primary' : 'text-bg-light border text-dark' ?>">
                                                                            <?= htmlspecialchars($req['Term_Taken'], ENT_QUOTES, 'UTF-8') ?>
                                                                        </span>
                                                                    <?php else: ?>
                                                                        <span class="text-muted">&mdash;</span>
                                                                    <?php endif; ?>
                                                                </td>
                                                                <td class="text-center fw-semibold">
                                                                    <?php if (!empty($req['Grade_Earned'])): ?>
                                                                        <span class="<?= $req['Grade_Earned'] === 'In Progress' ? 'text-primary' : 'text-dark' ?>">
                                                                            <?= htmlspecialchars($req['Grade_Earned'], ENT_QUOTES, 'UTF-8') ?>
                                                                        </span>
                                                                    <?php else: ?>
                                                                        <span class="text-muted">&mdash;</span>
                                                                    <?php endif; ?>
                                                                </td>
                                                                <td class="text-center">
                                                                    <span class="badge <?= htmlspecialchars($req['Status_Badge_Class'] ?? 'bg-secondary', ENT_QUOTES, 'UTF-8') ?>">
                                                                        <?= htmlspecialchars($req['Status'], ENT_QUOTES, 'UTF-8') ?>
                                                                    </span>
                                                                </td>
                                                            </tr>
                                                        <?php endforeach; ?>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>

                    <!-- Minor Requirements Cards -->
                    <?php if (!empty($auditData['minors'])): ?>
                        <?php foreach ($auditData['minors'] as $minor): ?>
                            <div class="card shadow-sm border-0 mb-4">
                                <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                                    <h5 class="card-title mb-0 fw-bold">
                                        Minor Requirements: <?= htmlspecialchars($minor['Minor_Name'], ENT_QUOTES, 'UTF-8') ?>
                                    </h5>
                                    <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle px-3 py-2">
                                        <?= (int)($minor['No_Credits_Required'] ?? 0) ?> Total Minor Credits
                                    </span>
                                </div>
                                <div class="card-body p-0">
                                    <?php if (empty($minor['requirements'])): ?>
                                        <div class="p-4 text-center text-muted">
                                            No curriculum requirements configured for this minor.
                                        </div>
                                    <?php else: ?>
                                        <div class="table-responsive">
                                            <table class="table table-hover align-middle mb-0 small">
                                                <thead class="table-light">
                                                    <tr>
                                                        <th style="width: 14%;">Course ID</th>
                                                        <th style="width: 26%;">Course Name</th>
                                                        <th style="width: 16%;">Department</th>
                                                        <th class="text-center" style="width: 8%;">Credits</th>
                                                        <th class="text-center" style="width: 8%;">Min Grade</th>
                                                        <th class="text-center" style="width: 14%;">Semester Taken / Enrolled</th>
                                                        <th class="text-center" style="width: 7%;">Grade</th>
                                                        <th class="text-center" style="width: 7%;">Status</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($minor['courses_by_department'] as $deptName => $deptReqs): ?>
                                                        <tr class="table-secondary">
                                                            <td colspan="8" class="fw-bold py-2">
                                                                <?= htmlspecialchars($deptName, ENT_QUOTES, 'UTF-8') ?>
                                                                <span class="badge bg-secondary ms-2">
                                                                    <?= count($deptReqs) ?> <?= count($deptReqs) === 1 ? 'Course' : 'Courses' ?> 
                                                                    (<?= array_sum(array_column($deptReqs, 'Course_Credits')) ?> Credits)
                                                                </span>
                                                            </td>
                                                        </tr>
                                                        <?php foreach ($deptReqs as $req): ?>
                                                            <tr>
                                                                <td class="fw-semibold font-monospace"><?= htmlspecialchars($req['Course_ID'], ENT_QUOTES, 'UTF-8') ?></td>
                                                                <td><?= htmlspecialchars($req['Course_Name'], ENT_QUOTES, 'UTF-8') ?></td>
                                                                <td><small class="text-muted"><?= htmlspecialchars($req['Department_Name'] ?? 'General', ENT_QUOTES, 'UTF-8') ?></small></td>
                                                                <td class="text-center"><?= (int)$req['Course_Credits'] ?></td>
                                                                <td class="text-center fw-medium"><?= htmlspecialchars($req['Min_Grade'], ENT_QUOTES, 'UTF-8') ?></td>
                                                                <td class="text-center">
                                                                    <?php if (!empty($req['Term_Taken'])): ?>
                                                                        <span class="badge <?= (strpos($req['Term_Taken'], 'In Progress') !== false) ? 'text-bg-primary' : 'text-bg-light border text-dark' ?>">
                                                                            <?= htmlspecialchars($req['Term_Taken'], ENT_QUOTES, 'UTF-8') ?>
                                                                        </span>
                                                                    <?php else: ?>
                                                                        <span class="text-muted">&mdash;</span>
                                                                    <?php endif; ?>
                                                                </td>
                                                                <td class="text-center fw-semibold">
                                                                    <?php if (!empty($req['Grade_Earned'])): ?>
                                                                        <span class="<?= $req['Grade_Earned'] === 'In Progress' ? 'text-primary' : 'text-dark' ?>">
                                                                            <?= htmlspecialchars($req['Grade_Earned'], ENT_QUOTES, 'UTF-8') ?>
                                                                        </span>
                                                                    <?php else: ?>
                                                                        <span class="text-muted">&mdash;</span>
                                                                    <?php endif; ?>
                                                                </td>
                                                                <td class="text-center">
                                                                    <span class="badge <?= htmlspecialchars($req['Status_Badge_Class'] ?? 'bg-secondary', ENT_QUOTES, 'UTF-8') ?>">
                                                                        <?= htmlspecialchars($req['Status'], ENT_QUOTES, 'UTF-8') ?>
                                                                    </span>
                                                                </td>
                                                            </tr>
                                                        <?php endforeach; ?>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>

                    <!-- Dedicated Elective Courses & Remaining Elective Credits Card -->
                    <div class="card shadow-sm border-0 mb-4">
                        <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                            <h5 class="card-title mb-0 fw-bold">
                                Elective Courses &amp; Elective Credits Progress
                            </h5>
                            <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-3 py-2">
                                Target: <?= $degreeTarget ?> Total Degree Credits
                            </span>
                        </div>
                        <div class="card-body p-4">
                            <!-- 4 KPI Metric Boxes -->
                            <div class="row g-3 mb-4">
                                <div class="col-sm-6 col-lg-3">
                                    <div class="p-3 bg-light rounded border text-center h-100">
                                        <div class="text-muted small text-uppercase fw-semibold mb-1">Required Electives</div>
                                        <div class="fs-2 fw-bold text-dark"><?= (int)($auditData['elective_credits_needed_total'] ?? 0) ?></div>
                                        <div class="small text-muted">credits toward degree</div>
                                    </div>
                                </div>
                                <div class="col-sm-6 col-lg-3">
                                    <div class="p-3 bg-light rounded border text-center h-100">
                                        <div class="text-muted small text-uppercase fw-semibold mb-1">Completed Electives</div>
                                        <div class="fs-2 fw-bold text-success"><?= (int)($auditData['elective_credits_completed'] ?? 0) ?></div>
                                        <div class="small text-muted">credits earned</div>
                                    </div>
                                </div>
                                <div class="col-sm-6 col-lg-3">
                                    <div class="p-3 bg-light rounded border text-center h-100">
                                        <div class="text-muted small text-uppercase fw-semibold mb-1">In-Progress Electives</div>
                                        <div class="fs-2 fw-bold text-primary"><?= (int)($auditData['elective_credits_in_progress'] ?? 0) ?></div>
                                        <div class="small text-muted">credits enrolled</div>
                                    </div>
                                </div>
                                <div class="col-sm-6 col-lg-3">
                                    <div class="p-3 bg-light rounded border text-center h-100">
                                        <div class="text-muted small text-uppercase fw-semibold mb-1">Remaining Electives</div>
                                        <div class="fs-2 fw-bold text-danger"><?= (int)($auditData['elective_credits_remaining_completed_only'] ?? 0) ?></div>
                                        <div class="small text-muted"><?= (int)($auditData['elective_credits_remaining_after_in_progress'] ?? 0) ?> after in-progress</div>
                                    </div>
                                </div>
                            </div>

                            <h6 class="fw-bold mb-3">Completed &amp; In-Progress Elective Courses</h6>
                            <?php if (empty($auditData['elective_courses'])): ?>
                                <div class="alert alert-light border text-muted d-flex align-items-center mb-0 p-3" role="alert">
                                    <div>
                                        No general elective courses have been completed or registered yet. Non-major courses at the student's academic level will automatically count toward elective degree requirements.
                                    </div>
                                </div>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-hover align-middle mb-0 small">
                                        <thead class="table-light">
                                            <tr>
                                                <th style="width: 14%;">Course ID</th>
                                                <th style="width: 30%;">Course Name</th>
                                                <th style="width: 20%;">Department</th>
                                                <th class="text-center" style="width: 8%;">Credits</th>
                                                <th class="text-center" style="width: 14%;">Semester Taken / Enrolled</th>
                                                <th class="text-center" style="width: 7%;">Grade</th>
                                                <th class="text-center" style="width: 7%;">Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($auditData['elective_courses'] as $el): ?>
                                                <tr>
                                                    <td class="fw-semibold font-monospace"><?= htmlspecialchars($el['Course_ID'], ENT_QUOTES, 'UTF-8') ?></td>
                                                    <td><?= htmlspecialchars($el['Course_Name'], ENT_QUOTES, 'UTF-8') ?></td>
                                                    <td><small class="text-muted"><?= htmlspecialchars($el['Department_Name'] ?? 'General', ENT_QUOTES, 'UTF-8') ?></small></td>
                                                    <td class="text-center"><?= (int)$el['Course_Credits'] ?></td>
                                                    <td class="text-center">
                                                        <?php if (!empty($el['Term_Taken'])): ?>
                                                            <span class="badge <?= (strpos($el['Term_Taken'], 'In Progress') !== false) ? 'text-bg-primary' : 'text-bg-light border text-dark' ?>">
                                                                <?= htmlspecialchars($el['Term_Taken'], ENT_QUOTES, 'UTF-8') ?>
                                                            </span>
                                                        <?php else: ?>
                                                            <span class="text-muted">&mdash;</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td class="text-center fw-semibold">
                                                        <?php if (!empty($el['Grade'])): ?>
                                                            <span class="<?= $el['Grade'] === 'In Progress' ? 'text-primary' : 'text-dark' ?>">
                                                                <?= htmlspecialchars($el['Grade'], ENT_QUOTES, 'UTF-8') ?>
                                                            </span>
                                                        <?php else: ?>
                                                            <span class="text-muted">&mdash;</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td class="text-center">
                                                        <span class="badge <?= htmlspecialchars($el['Status_Badge_Class'] ?? 'bg-secondary', ENT_QUOTES, 'UTF-8') ?>">
                                                            <?= htmlspecialchars($el['Status'], ENT_QUOTES, 'UTF-8') ?>
                                                        </span>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                <?php elseif ($adminView === 'details'): ?>
                    <!-- Details Tab -->
                    <div class="row g-4 mb-4">
                        <div class="col-md-6">
                            <div class="card shadow-sm border-0 h-100">
                                <div class="card-header bg-white py-3">
                                    <h6 class="mb-0 fw-bold">Demographic &amp; Contact Details</h6>
                                </div>
                                <div class="card-body p-3">
                                    <table class="table table-borderless table-sm mb-0 small">
                                        <tbody>
                                            <tr>
                                                <th class="text-muted w-40">User ID:</th>
                                                <td><strong><?= (int)$target_id ?></strong></td>
                                            </tr>
                                            <tr>
                                                <th class="text-muted">Full Name:</th>
                                                <td><?= htmlspecialchars($fullName, ENT_QUOTES, 'UTF-8') ?></td>
                                            </tr>
                                            <tr>
                                                <th class="text-muted">University Email:</th>
                                                <td><a href="mailto:<?= htmlspecialchars($user['User_Email'] ?? '', ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($user['User_Email'] ?? 'N/A', ENT_QUOTES, 'UTF-8') ?></a></td>
                                            </tr>
                                            <tr>
                                                <th class="text-muted">Phone Number:</th>
                                                <td><?= htmlspecialchars($user['User_PhoneNo'] ?? 'N/A', ENT_QUOTES, 'UTF-8') ?></td>
                                            </tr>
                                            <tr>
                                                <th class="text-muted">Gender:</th>
                                                <td><?= htmlspecialchars($user['User_Gender'] ?? 'N/A', ENT_QUOTES, 'UTF-8') ?></td>
                                            </tr>
                                            <tr>
                                                <th class="text-muted">Date of Birth:</th>
                                                <td><?= htmlspecialchars($user['User_DOB'] ?? 'N/A', ENT_QUOTES, 'UTF-8') ?></td>
                                            </tr>
                                            <tr>
                                                <th class="text-muted align-top">Full Address:</th>
                                                <td><?= htmlspecialchars(formatAddress($user) ?: 'No address on file', ENT_QUOTES, 'UTF-8') ?></td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="card shadow-sm border-0 h-100">
                                <div class="card-header bg-white py-3">
                                    <h6 class="mb-0 fw-bold">Academic Standing &amp; Advising</h6>
                                </div>
                                <div class="card-body p-3">
                                    <table class="table table-borderless table-sm mb-0 small">
                                        <tbody>
                                            <tr>
                                                <th class="text-muted w-40">Class Standing:</th>
                                                <td><span class="badge bg-primary"><?= htmlspecialchars($standing, ENT_QUOTES, 'UTF-8') ?></span> (<?= htmlspecialchars($attType, ENT_QUOTES, 'UTF-8') ?>)</td>
                                            </tr>
                                            <tr>
                                                <th class="text-muted">Student Level:</th>
                                                <td><?= htmlspecialchars($studentData['Student_Type'] ?? 'Undergraduate', ENT_QUOTES, 'UTF-8') ?></td>
                                            </tr>
                                            <tr>
                                                <th class="text-muted">Department:</th>
                                                <td><?= htmlspecialchars($deptName, ENT_QUOTES, 'UTF-8') ?></td>
                                            </tr>
                                            <tr>
                                                <th class="text-muted">Major(s):</th>
                                                <td><?= htmlspecialchars($majorsList, ENT_QUOTES, 'UTF-8') ?></td>
                                            </tr>
                                            <tr>
                                                <th class="text-muted">Minor:</th>
                                                <td><?= htmlspecialchars($minorName, ENT_QUOTES, 'UTF-8') ?></td>
                                            </tr>
                                            <tr>
                                                <th class="text-muted align-top">Academic Advisor:</th>
                                                <td>
                                                    <?php if (!empty($studentData['advisor'])): ?>
                                                        <strong><?= renderFacultyLink($studentData['advisor']['Faculty_ID'] ?? 0, 'Dr. ' . $studentData['advisor']['User_FName'] . ' ' . $studentData['advisor']['User_LName'], '') ?></strong><br>
                                                        <small class="text-muted"><a href="mailto:<?= htmlspecialchars($studentData['advisor']['User_Email'] ?? '', ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($studentData['advisor']['User_Email'] ?? '', ENT_QUOTES, 'UTF-8') ?></a></small>
                                                    <?php else: ?>
                                                        <span class="text-muted">No advisor assigned</span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                            <?php if (($studentData['Student_Type'] ?? '') === 'Graduate'): ?>
                                                <tr>
                                                    <th class="text-muted">Grad Program:</th>
                                                    <td><?= htmlspecialchars($studentData['Grad_Program'] ?? 'MS', ENT_QUOTES, 'UTF-8') ?></td>
                                                </tr>
                                                <?php if (!empty($studentData['Thesis'])): ?>
                                                    <tr>
                                                        <th class="text-muted">Thesis Title:</th>
                                                        <td><em><?= htmlspecialchars(cleanThesisTitle($studentData['Thesis']), ENT_QUOTES, 'UTF-8') ?></em></td>
                                                    </tr>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <!-- Active Holds Card -->
                        <div class="col-12">
                            <div class="card shadow-sm border-0">
                                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                                    <h6 class="mb-0 fw-bold">Active Account Holds</h6>
                                    <a href="admin/manage_holds.php" class="btn btn-outline-primary btn-sm py-0 px-2" style="font-size: 0.8rem;">
                                        Manage Holds &rarr;
                                    </a>
                                </div>
                                <div class="card-body p-0">
                                    <?php if (empty($studentHolds)): ?>
                                        <div class="p-3 text-center text-success small">
                                            Clear &mdash; No active holds on this student account.
                                        </div>
                                    <?php else: ?>
                                        <div class="table-responsive">
                                            <table class="table table-sm table-striped mb-0 align-middle small">
                                                <thead class="table-light">
                                                    <tr>
                                                        <th>Hold Type</th>
                                                        <th>Description</th>
                                                        <th>Date Placed</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($studentHolds as $h): ?>
                                                        <tr>
                                                            <td><span class="badge bg-danger"><?= htmlspecialchars($h['Hold_Type'] ?? $h['Hold_Name'] ?? 'Hold', ENT_QUOTES, 'UTF-8') ?></span></td>
                                                            <td><?= htmlspecialchars($h['Hold_Description'] ?? $h['Description'] ?? 'Registration restriction', ENT_QUOTES, 'UTF-8') ?></td>
                                                            <td class="text-muted"><?= htmlspecialchars($h['Date_Placed'] ?? 'Active', ENT_QUOTES, 'UTF-8') ?></td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>

    <?php else: ?>
        <!-- Standard User Profile View -->
        <!-- Header Card -->
        <div class="card shadow-sm mb-4 border-0">
            <div class="card-body p-4 bg-white rounded-3">
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
                    <div class="d-flex align-items-center gap-3">
                        <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center fw-bold fs-3" style="width: 64px; height: 64px;">
                            <?= htmlspecialchars(strtoupper(substr($user['User_FName'], 0, 1) . substr($user['User_LName'], 0, 1)), ENT_QUOTES, 'UTF-8') ?>
                        </div>
                        <div>
                            <h2 class="fw-bold mb-1"><?= htmlspecialchars($fullName, ENT_QUOTES, 'UTF-8') ?></h2>
                            <div class="text-secondary small">
                                User ID: <strong><?= (int)$target_id ?></strong> &bull;
                                Role: <span class="badge bg-primary"><?= htmlspecialchars($target_type, ENT_QUOTES, 'UTF-8') ?></span>
                                <?php if ($target_id === $viewer_id): ?>
                                    <span class="badge bg-success ms-1">Your Account</span>
                                <?php elseif (!$canViewPrivate && $target_type === 'Faculty'): ?>
                                    <span class="badge bg-info text-dark ms-1">Directory View</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <div>
                        <a href="<?= htmlspecialchars($dashboardUrl, ENT_QUOTES, 'UTF-8') ?>" class="btn btn-outline-secondary">
                            &larr; Back to Dashboard
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-4 mb-4">

            <!-- Card 1: General User Information -->
            <div class="col-lg-6">
                <div class="card shadow-sm border-0 h-100">
                    <div class="card-header bg-white py-3">
                        <h5 class="mb-0 fw-bold">General Account Information</h5>
                    </div>
                    <div class="card-body p-4">
                        <table class="table table-borderless table-sm mb-0">
                            <tbody>
                                <tr>
                                    <th class="text-muted w-35">User ID:</th>
                                    <td><strong><?= (int)$target_id ?></strong></td>
                                </tr>
                                <tr>
                                    <th class="text-muted">Account Role:</th>
                                    <td><?= htmlspecialchars($target_type, ENT_QUOTES, 'UTF-8') ?></td>
                                </tr>
                                <tr>
                                    <th class="text-muted">Full Name:</th>
                                    <td><?= htmlspecialchars($fullName, ENT_QUOTES, 'UTF-8') ?></td>
                                </tr>
                                <tr>
                                    <th class="text-muted">University Email:</th>
                                    <td>
                                        <a href="mailto:<?= htmlspecialchars($user['User_Email'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                                            <?= htmlspecialchars($user['User_Email'] ?? 'N/A', ENT_QUOTES, 'UTF-8') ?>
                                        </a>
                                    </td>
                                </tr>
                                <tr>
                                    <th class="text-muted">Phone Number:</th>
                                    <td><?= $phoneDisplay ?></td>
                                </tr>
                                <tr>
                                    <th class="text-muted">Gender:</th>
                                    <td><?= $genderDisplay ?></td>
                                </tr>
                                <tr>
                                    <th class="text-muted">Date of Birth:</th>
                                    <td><?= $dobDisplay ?></td>
                                </tr>
                                <tr>
                                    <th class="text-muted align-top">Residential Address:</th>
                                    <td>
                                        <?php if ($canViewPrivate): ?>
                                            <?php if ($fullAddress || $cityStateZip): ?>
                                                <?= htmlspecialchars($fullAddress, ENT_QUOTES, 'UTF-8') ?><br>
                                                <?= htmlspecialchars($cityStateZip, ENT_QUOTES, 'UTF-8') ?><br>
                                                <?= htmlspecialchars($countryDisplay, ENT_QUOTES, 'UTF-8') ?>
                                            <?php else: ?>
                                                <span class="text-muted">No address on file</span>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <?= $fullAddress ?>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Card 2: Role-Specific Details -->
            <div class="col-lg-6">
                <div class="card shadow-sm border-0 h-100">
                    <div class="card-header bg-white py-3">
                        <h5 class="mb-0 fw-bold">
                            <?php if ($target_type === 'Student'): ?>
                                Academic Details &amp; Standing
                            <?php elseif ($target_type === 'Faculty'): ?>
                                Faculty Appointment &amp; Workload
                            <?php elseif ($target_type === 'Admin'): ?>
                                System Administration Scope
                            <?php else: ?>
                                Department Details
                            <?php endif; ?>
                        </h5>
                    </div>
                    <div class="card-body p-4">

                        <?php if ($target_type === 'Student' && $studentData): ?>
                            <table class="table table-borderless table-sm mb-0">
                                <tbody>
                                    <tr>
                                        <th class="text-muted w-35">Class Standing:</th>
                                        <td>
                                            <span class="badge bg-primary">
                                                <?= htmlspecialchars($studentData['class_standing'], ENT_QUOTES, 'UTF-8') ?>
                                            </span>
                                            (<?= htmlspecialchars($studentData['Attendance_Type'], ENT_QUOTES, 'UTF-8') ?>)
                                        </td>
                                    </tr>
                                    <tr>
                                        <th class="text-muted">Student Level:</th>
                                        <td><?= htmlspecialchars($studentData['Student_Type'], ENT_QUOTES, 'UTF-8') ?></td>
                                    </tr>
                                    <tr>
                                        <th class="text-muted">Home Department:</th>
                                        <td><?= htmlspecialchars($studentData['Department_Name'] ?? 'General Academics', ENT_QUOTES, 'UTF-8') ?></td>
                                    </tr>
                                    <tr>
                                        <th class="text-muted">Cumulative GPA:</th>
                                        <td><strong><?= number_format((float)$studentData['cum_gpa'], 2) ?></strong> / 4.00</td>
                                    </tr>
                                    <tr>
                                        <th class="text-muted">Earned Credits:</th>
                                        <td><?= (int)$studentData['Credits_Earned'] ?> credits</td>
                                    </tr>
                                    <tr>
                                        <th class="text-muted">Declared Major(s):</th>
                                        <td>
                                            <?= !empty($studentData['majors']) ? htmlspecialchars(implode(', ', $studentData['majors']), ENT_QUOTES, 'UTF-8') : '<span class="text-muted">Undeclared</span>' ?>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th class="text-muted">Declared Minor:</th>
                                        <td>
                                            <?= !empty($studentData['minor']) ? htmlspecialchars($studentData['minor'], ENT_QUOTES, 'UTF-8') : '<span class="text-muted">None</span>' ?>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th class="text-muted align-top">Assigned Advisor:</th>
                                        <td>
                                            <?php if ($studentData['advisor']): ?>
                                                <strong><?= renderFacultyLink($studentData['advisor']['Faculty_ID'] ?? 0, 'Dr. ' . $studentData['advisor']['User_FName'] . ' ' . $studentData['advisor']['User_LName'], '') ?></strong><br>
                                                <small class="text-muted">
                                                    Email: <a href="mailto:<?= htmlspecialchars($studentData['advisor']['User_Email'] ?? '', ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($studentData['advisor']['User_Email'] ?? '', ENT_QUOTES, 'UTF-8') ?></a>
                                                </small>
                                            <?php else: ?>
                                                <span class="text-muted">No academic advisor assigned</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th class="text-muted">Account Holds:</th>
                                        <td>
                                            <?php if ($studentData['holds_count'] > 0): ?>
                                                <span class="badge bg-danger"><?= $studentData['holds_count'] ?> Active Hold(s)</span>
                                            <?php else: ?>
                                                <span class="badge bg-success">Clear (No Active Holds)</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php if ($studentData['Student_Type'] === 'Graduate'): ?>
                                        <tr>
                                            <th class="text-muted">Grad Program:</th>
                                            <td><?= htmlspecialchars($studentData['Grad_Program'] ?? 'General', ENT_QUOTES, 'UTF-8') ?></td>
                                        </tr>
                                        <?php if (!empty($studentData['Thesis'])): ?>
                                            <tr>
                                                <th class="text-muted">Thesis Title:</th>
                                                <td><em><?= htmlspecialchars(cleanThesisTitle($studentData['Thesis']), ENT_QUOTES, 'UTF-8') ?></em></td>
                                            </tr>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>

                        <?php elseif ($target_type === 'Faculty' && $facultyData): ?>
                            <table class="table table-borderless table-sm mb-0">
                                <tbody>
                                    <tr>
                                        <th class="text-muted w-35">Academic Rank:</th>
                                        <td><strong><?= htmlspecialchars($facultyData['Rank'] ?? 'Instructor', ENT_QUOTES, 'UTF-8') ?></strong></td>
                                    </tr>
                                    <tr>
                                        <th class="text-muted">Appointment Status:</th>
                                        <td><?= htmlspecialchars($facultyData['Faculty_Type'] ?? 'Full-Time', ENT_QUOTES, 'UTF-8') ?></td>
                                    </tr>
                                    <tr>
                                        <th class="text-muted">Specialty:</th>
                                        <td><?= htmlspecialchars($facultyData['Specialty'] ?? 'General', ENT_QUOTES, 'UTF-8') ?></td>
                                    </tr>
                                    <tr>
                                        <th class="text-muted">Office Location:</th>
                                        <td>
                                            <?php if (!empty($facultyData['Office_ID'])): ?>
                                                <strong><?= htmlspecialchars($facultyData['Office_ID'], ENT_QUOTES, 'UTF-8') ?></strong>
                                                <?php if (!empty($facultyData['Building_Name'])): ?>
                                                    (<?= htmlspecialchars($facultyData['Building_Name'] . ' Room ' . $facultyData['Room_NO'], ENT_QUOTES, 'UTF-8') ?>)
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <span class="text-muted">Office TBA</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th class="text-muted align-top">Department Roster:</th>
                                        <td>
                                            <?php if (!empty($facultyData['departments'])): ?>
                                                <ul class="list-unstyled mb-0">
                                                    <?php foreach ($facultyData['departments'] as $d): ?>
                                                        <li>
                                                            <strong><?= htmlspecialchars($d['Department_Name'], ENT_QUOTES, 'UTF-8') ?></strong>
                                                            <span class="badge bg-light text-dark border"><?= (float)$d['Percent_Time'] ?>% Time</span>
                                                        </li>
                                                    <?php endforeach; ?>
                                                </ul>
                                            <?php else: ?>
                                                <span class="text-muted">Unassigned</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php if (!empty($facultyData['leadership'])): ?>
                                        <tr>
                                            <th class="text-muted align-top">Leadership Roles:</th>
                                            <td>
                                                <ul class="list-unstyled mb-0">
                                                    <?php foreach ($facultyData['leadership'] as $l): ?>
                                                        <li>
                                                            <span class="badge bg-warning text-dark"><?= htmlspecialchars($l['Role_Title'], ENT_QUOTES, 'UTF-8') ?></span>
                                                            &ndash; <?= htmlspecialchars($l['Department_Name'], ENT_QUOTES, 'UTF-8') ?>
                                                        </li>
                                                    <?php endforeach; ?>
                                                </ul>
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                    <tr>
                                        <th class="text-muted">Teaching Load:</th>
                                        <td>
                                            Fall 2026: <strong><?= $facultyData['fall_load'] ?></strong> sections &bull;
                                            Spring 2027: <strong><?= $facultyData['spring_load'] ?></strong> sections
                                        </td>
                                    </tr>
                                    <tr>
                                        <th class="text-muted">Assigned Advisees:</th>
                                        <td>
                                            <strong><?= $facultyData['advisee_count'] ?></strong> / 15 Advisees
                                        </td>
                                    </tr>
                                </tbody>
                            </table>

                        <?php elseif ($target_type === 'Admin' && $adminData): ?>
                            <table class="table table-borderless table-sm mb-0">
                                <tbody>
                                    <tr>
                                        <th class="text-muted w-35">Security Level:</th>
                                        <td>
                                            <span class="badge bg-danger fs-6">Level <?= (int)$adminData['Security_Level'] ?></span>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th class="text-muted">System Permissions:</th>
                                        <td>
                                            <?php if ((int)$adminData['Security_Level'] >= 2): ?>
                                                <span class="text-success fw-bold">Read &amp; Write Access (Full Administration)</span>
                                            <?php else: ?>
                                                <span class="text-secondary fw-semibold">Read-Only Access (Auditor)</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th class="text-muted">Scope of Control:</th>
                                        <td>User account creation, course section scheduling, semester calendar auditing.</td>
                                    </tr>
                                </tbody>
                            </table>

                        <?php elseif ($target_type === 'Stat-Dept'): ?>
                            <table class="table table-borderless table-sm mb-0">
                                <tbody>
                                    <tr>
                                        <th class="text-muted w-35">Division:</th>
                                        <td>Statistical &amp; Institutional Research Department</td>
                                    </tr>
                                    <tr>
                                        <th class="text-muted">Employment:</th>
                                        <td><span class="badge bg-info text-dark">Full-Time Staff Member</span></td>
                                    </tr>
                                    <tr>
                                        <th class="text-muted">Clearance:</th>
                                        <td>Anonymized Institutional Academic Records Clearance</td>
                                    </tr>
                                    <tr>
                                        <th class="text-muted">Scope:</th>
                                        <td>Institutional enrollment reporting, section utilization statistics, grade distribution analysis.</td>
                                    </tr>
                                </tbody>
                            </table>
                        <?php endif; ?>

                    </div>
                </div>
            </div>

        </div>

        <?php if ($target_type === 'Faculty'): ?>

            <?php if ($rosterSection): ?>
                <!-- Section Roster Drill-Down Card -->
                <div id="faculty-roster-view" class="card shadow-sm border-0 border-top border-primary border-4 mb-4">
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
                                Semester: <strong><?= htmlspecialchars(($rosterSection['Semester_Name'] ?? '') . ' ' . ($rosterSection['Semester_Year'] ?? ''), ENT_QUOTES, 'UTF-8') ?></strong> &bull;
                                Schedule: <?= formatSchedule($rosterSection['Time_Slot_Day'], $rosterSection['Start_Time'], $rosterSection['End_Time']) ?> &bull;
                                Room: <?= htmlspecialchars($rosterSection['Building_Name'] . ' ' . $rosterSection['Room_NO'], ENT_QUOTES, 'UTF-8') ?>
                            </div>
                        </div>
                        <div>
                            <a href="user_profile.php?user_id=<?= (int)$target_id ?>" class="btn btn-outline-secondary btn-sm">
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
                                            <th>Email</th>
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
                                                    <a href="user_profile.php?user_id=<?= (int)$st['Student_ID'] ?>" class="text-decoration-none fw-semibold text-primary">
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
                                                    <a href="user_profile.php?user_id=<?= (int)$st['Student_ID'] ?>" class="btn btn-outline-primary btn-sm py-0 px-2" style="font-size: 0.8rem;">
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

            <!-- Teaching Schedule Card -->
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <h5 class="mb-0 fw-bold">
                        <?= $canViewPrivate ? 'Teaching Schedule Across Semesters' : 'Current &amp; Upcoming Course Offerings' ?>
                    </h5>
                    <span class="badge bg-secondary"><?= count($teachingSections) ?> Total Section<?= count($teachingSections) !== 1 ? 's' : '' ?></span>
                </div>
                <div class="card-body p-0">
                    <?php
                    $displaySections = $teachingSections;
                    if (!$canViewPrivate) {
                        // Directory view: only show current & upcoming course offerings
                        $displaySections = array_filter($teachingSections, fn($s) => (int)$s['Semester_ID'] >= 202608);
                    }
                    ?>
                    <?php if (empty($displaySections)): ?>
                        <div class="p-5 text-center text-muted">
                            <h6 class="fw-bold text-secondary mb-1">No Scheduled Teaching Sections</h6>
                            <p class="small mb-0">No course sections scheduled for this instructor.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover table-striped mb-0 align-middle">
                                <thead class="table-light">
                                    <tr>
                                        <th>Term</th>
                                        <th>CRN</th>
                                        <th>Course ID</th>
                                        <th>Sec</th>
                                        <th>Course Name</th>
                                        <th class="text-center">Credits</th>
                                        <th>Day / Time</th>
                                        <th>Room</th>
                                        <?php if ($canViewPrivate): ?>
                                            <th class="text-center">Enrollment / Allowed</th>
                                        <?php else: ?>
                                            <th class="text-center">Available Seats</th>
                                        <?php endif; ?>
                                        <th class="text-center">Status</th>
                                        <?php if ($canViewPrivate): ?>
                                            <th class="text-end pe-3">Roster</th>
                                        <?php endif; ?>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($displaySections as $ts):
                                        $tsCrn = (int)$ts['CRN'];
                                        $tsSemId = (int)$ts['Semester_ID'];
                                        $isPast = ($tsSemId < 202608);
                                        $enr = (int)($ts['Enrolled_Count'] ?? 0);
                                        $seats = (int)($ts['Available_seats'] ?? 0);
                                        $allowed = $enr + $seats;
                                    ?>
                                        <tr>
                                            <td>
                                                <span class="badge bg-light text-dark border">
                                                    <?= htmlspecialchars(($ts['Semester_Name'] ?? '') . ' ' . ($ts['Semester_Year'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                                                </span>
                                            </td>
                                            <td><strong><?= $tsCrn ?></strong></td>
                                            <td><span class="badge text-bg-light border"><?= htmlspecialchars($ts['Course_ID'], ENT_QUOTES, 'UTF-8') ?></span></td>
                                            <td><?= htmlspecialchars((string)$ts['Section_Number'], ENT_QUOTES, 'UTF-8') ?></td>
                                            <td>
                                                <span class="fw-semibold"><?= htmlspecialchars($ts['Course_Name'], ENT_QUOTES, 'UTF-8') ?></span>
                                                <span class="badge bg-secondary-subtle text-secondary small ms-1"><?= htmlspecialchars($ts['Course_Type'], ENT_QUOTES, 'UTF-8') ?></span>
                                            </td>
                                            <td class="text-center fw-bold"><?= (int)$ts['Course_Credits'] ?></td>
                                            <td><?= formatSchedule($ts['Time_Slot_Day'], $ts['Start_Time'], $ts['End_Time']) ?></td>
                                            <td><?= htmlspecialchars($ts['Building_Name'] . ' ' . $ts['Room_NO'], ENT_QUOTES, 'UTF-8') ?></td>
                                            <?php if ($canViewPrivate): ?>
                                                <td class="text-center">
                                                    <?php if ($isPast || ($ts['Status'] ?? '') === 'Past'): ?>
                                                        <span class="badge bg-secondary"><?= $enr ?> Enrolled</span>
                                                    <?php else: ?>
                                                        <?php if ($seats <= 0): ?>
                                                            <span class="badge bg-danger"><?= $enr ?> / <?= $allowed ?> (Full)</span>
                                                        <?php else: ?>
                                                            <span class="badge bg-success"><?= $enr ?> / <?= $allowed ?></span>
                                                        <?php endif; ?>
                                                    <?php endif; ?>
                                                </td>
                                            <?php else: ?>
                                                <td class="text-center">
                                                    <?php if ($seats > 0): ?>
                                                        <span class="badge bg-success"><?= $seats ?> open</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-danger">Full (0)</span>
                                                    <?php endif; ?>
                                                </td>
                                            <?php endif; ?>
                                            <td class="text-center">
                                                <?php
                                                    $secStatus = $ts['Status'] ?? 'Active';
                                                    if ($secStatus === 'Active'):
                                                ?>
                                                    <span class="badge bg-success">Active</span>
                                                <?php elseif ($secStatus === 'Past'): ?>
                                                    <span class="badge bg-secondary">Past</span>
                                                <?php elseif ($secStatus === 'Cancelled'): ?>
                                                    <span class="badge bg-danger">Cancelled</span>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary"><?= htmlspecialchars($secStatus, ENT_QUOTES, 'UTF-8') ?></span>
                                                <?php endif; ?>
                                            </td>
                                            <?php if ($canViewPrivate): ?>
                                                <td class="text-end pe-3">
                                                    <a href="user_profile.php?user_id=<?= (int)$target_id ?>&roster_crn=<?= $tsCrn ?>#faculty-roster-view" class="btn btn-sm btn-outline-primary py-0 px-2" style="font-size: 0.8rem;">
                                                        Roster &rarr;
                                                    </a>
                                                </td>
                                            <?php endif; ?>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <?php if ($canViewPrivate): ?>
                <!-- Assigned Advisees Card -->
                <div class="card shadow-sm border-0 mb-4">
                    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                        <h5 class="mb-0 fw-bold">Assigned Academic Advisees</h5>
                        <span class="badge <?= count($facultyAdvisees) >= 15 ? 'bg-danger' : 'bg-primary' ?>">
                            <?= count($facultyAdvisees) ?> / 15 Advisees
                        </span>
                    </div>
                    <div class="card-body p-0">
                        <?php if (empty($facultyAdvisees)): ?>
                            <div class="p-5 text-center text-muted">
                                <h6 class="fw-bold text-secondary mb-1">No Advisees Assigned</h6>
                                <p class="small mb-0">This faculty advisor currently has no active student advisees assigned.</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover table-striped mb-0 align-middle">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Student ID</th>
                                            <th>Student Name</th>
                                            <th>Email</th>
                                            <th>Standing</th>
                                            <th>Attendance</th>
                                            <th>Department</th>
                                            <th>Major(s)</th>
                                            <th>Date Assigned</th>
                                            <th>Holds</th>
                                            <th class="text-end pe-3">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($facultyAdvisees as $adv): ?>
                                            <tr>
                                                <td><strong><?= (int)$adv['Student_ID'] ?></strong></td>
                                                <td>
                                                    <a href="user_profile.php?user_id=<?= (int)$adv['Student_ID'] ?>" class="text-decoration-none fw-semibold text-primary">
                                                        <?= htmlspecialchars($adv['User_FName'] . ' ' . $adv['User_LName'], ENT_QUOTES, 'UTF-8') ?>
                                                    </a>
                                                </td>
                                                <td>
                                                    <a href="mailto:<?= htmlspecialchars($adv['User_Email'] ?? '', ENT_QUOTES, 'UTF-8') ?>" class="text-muted small">
                                                        <?= htmlspecialchars($adv['User_Email'] ?? 'N/A', ENT_QUOTES, 'UTF-8') ?>
                                                    </a>
                                                </td>
                                                <td><span class="badge bg-secondary"><?= htmlspecialchars($adv['Student_Type'] ?? 'Undergrad', ENT_QUOTES, 'UTF-8') ?></span></td>
                                                <td><small class="text-muted"><?= htmlspecialchars($adv['Attendance_Type'] ?? 'Full-Time', ENT_QUOTES, 'UTF-8') ?></small></td>
                                                <td><small><?= htmlspecialchars($adv['Department_Name'] ?? 'General', ENT_QUOTES, 'UTF-8') ?></small></td>
                                                <td><small><?= htmlspecialchars($adv['Majors'] ?? 'Undeclared', ENT_QUOTES, 'UTF-8') ?></small></td>
                                                <td><small class="text-muted"><?= !empty($adv['Date_Assigned']) ? date('M j, Y', strtotime($adv['Date_Assigned'])) : 'N/A' ?></small></td>
                                                <td>
                                                    <?php if ((int)($adv['Active_Holds_Count'] ?? 0) > 0): ?>
                                                        <span class="badge bg-danger"><?= (int)$adv['Active_Holds_Count'] ?> Hold(s)</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-success">Clear</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="text-end pe-3">
                                                    <a href="user_profile.php?user_id=<?= (int)$adv['Student_ID'] ?>" class="btn btn-outline-primary btn-sm py-0 px-2" style="font-size: 0.8rem;">
                                                        View Profile &rarr;
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

        <?php endif; ?>

    <?php endif; ?>

    </div>

    <!-- Bootstrap 5.3 JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
