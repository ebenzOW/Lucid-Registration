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

session_start();

if (!isset($_SESSION["User_ID"])) {
    header("Location: login.php");
    exit;
}

$viewer_id   = (int)$_SESSION["User_ID"];
$viewer_type = $_SESSION["User_Type"] ?? '';

require_once 'db_connect.php';
require_once __DIR__ . '/includes/academic_helpers.php';
require_once 'profile_hover_card.php';

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
// - Admins and Stat-Dept can view any profile.
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
    $hasAccess = true;
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
    $fullAddress    = trim(($user['Street_No'] ?? '') . ' ' . ($user['Street_Name'] ?? ''));
    $cityStateZip   = trim(($user['City'] ?? '') . ($user['City'] && $user['State'] ? ', ' : '') . ($user['State'] ?? '') . ' ' . ($user['Zip_Code'] ?? ''));
    $countryDisplay = $user['Country'] ?? 'USA';
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

// Determine Dashboard Return URL
$dashboardUrl = match($viewer_type) {
    'Student'   => 'student/dashboard.php',
    'Faculty'   => 'faculty/dashboard.php',
    'Admin'     => 'admin/dashboard.php',
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
                                                <td><em><?= htmlspecialchars($studentData['Thesis'], ENT_QUOTES, 'UTF-8') ?></em></td>
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
                        <?php else: ?>
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
                                            <th>Midterm</th>
                                            <th>Final Grade</th>
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
                                                <td>
                                                    <?= !empty($st['Midterm_Grade']) ? '<span class="badge bg-info text-dark">' . htmlspecialchars($st['Midterm_Grade'], ENT_QUOTES, 'UTF-8') . '</span>' : '<span class="text-muted">&mdash;</span>' ?>
                                                </td>
                                                <td>
                                                    <?= !empty($st['Grade']) ? '<span class="badge bg-success">' . htmlspecialchars($st['Grade'], ENT_QUOTES, 'UTF-8') . '</span>' : '<span class="text-muted">&mdash;</span>' ?>
                                                </td>
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
                                            <th class="text-center">Enrollment / Capacity</th>
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
                                        $cap = (int)($ts['Room_Capacity'] ?? 0);
                                        $enr = (int)($ts['Enrolled_Count'] ?? 0);
                                        $seats = (int)($ts['Available_seats'] ?? 0);
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
                                                    <?php if ($isPast): ?>
                                                        <span class="badge bg-secondary"><?= $enr ?> Enrolled</span>
                                                    <?php else: ?>
                                                        <?php if ($seats <= 0): ?>
                                                            <span class="badge bg-danger"><?= $enr ?> / <?= $cap ?> (Full)</span>
                                                        <?php else: ?>
                                                            <span class="badge bg-success"><?= $enr ?> / <?= $cap ?> (<?= $seats ?> open)</span>
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
                                                <?php if (($ts['Status'] ?? 'Active') === 'Active'): ?>
                                                    <span class="badge bg-success">Active</span>
                                                <?php else: ?>
                                                    <span class="badge bg-danger">Cancelled</span>
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

    </div>

    <!-- Bootstrap 5.3 JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
