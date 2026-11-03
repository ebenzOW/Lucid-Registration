<?php
/**
 * Lucid University Course Registration System
 * Standalone User Profile Viewer
 *
 * Dedicated profile viewing interface displaying user account information
 * and granular role-specific academic and professional records.
 */

session_start();

if (!isset($_SESSION["User_ID"])) {
    header("Location: login.php");
    exit;
}

$viewer_id   = (int)$_SESSION["User_ID"];
$viewer_type = $_SESSION["User_Type"] ?? '';

require_once 'db_connect.php';
require_once 'student/academic_helpers.php';

$target_id = isset($_GET['user_id']) ? (int)$_GET['user_id'] : $viewer_id;

// Permission validation:
// Users can view their own profile. Admins/Stat-Dept can view any profile.
// Faculty can view their own profile or advisees/students via canViewerAccessStudentRecord.
$hasAccess = false;
if ($target_id === $viewer_id || $viewer_type === 'Admin' || $viewer_type === 'Stat-Dept') {
    $hasAccess = true;
} elseif ($viewer_type === 'Faculty') {
    $hasAccess = canViewerAccessStudentRecord($conn, $viewer_id, $viewer_type, $target_id);
}

if (!$hasAccess) {
    // Default to viewer's own profile if unauthorized
    $target_id = $viewer_id;
}

// 1. Fetch Base User & Login Data
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
    die("User record not found.");
}

$target_type = $user['User_Type'];
$fullName = trim($user['User_FName'] . ' ' . (!empty($user['User_MName']) ? $user['User_MName'] . ' ' : '') . $user['User_LName']);
$fullAddress = trim(($user['Street_No'] ?? '') . ' ' . ($user['Street_Name'] ?? ''));
$cityStateZip = trim(($user['City'] ?? '') . ($user['City'] && $user['State'] ? ', ' : '') . ($user['State'] ?? '') . ' ' . ($user['Zip_Code'] ?? ''));

// 2. Fetch Role-Specific Details
$studentData = null;
$facultyData = null;
$adminData   = null;
$statData    = null;

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
    $fStmt = $conn->prepare("
        SELECT f.Rank, f.Faculty_Type, f.Specialty, f.Office_ID,
               r.Room_NO, b.Building_Name
        FROM FACULTY f
        LEFT JOIN ROOM r ON f.Office_ID = r.Room_ID
        LEFT JOIN BUILDING b ON r.Building_ID = b.Building_ID
        WHERE f.Faculty_ID = ?
    ");
    $fStmt->bind_param("i", $target_id);
    $fStmt->execute();
    $facultyData = $fStmt->get_result()->fetch_assoc();
    $fStmt->close();

    // Department Roster Assignments
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

    // Leadership Roles
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

    // Teaching Section Counts (Fall 2026 and Spring 2027)
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

    // Advisees Count
    $advCountStmt = $conn->prepare("SELECT COUNT(*) AS cnt FROM ADVISOR_STUDENT WHERE Faculty_ID = ?");
    $advCountStmt->bind_param("i", $target_id);
    $advCountStmt->execute();
    $facultyData['advisee_count'] = (int)($advCountStmt->get_result()->fetch_assoc()['cnt'] ?? 0);
    $advCountStmt->close();

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
    <!-- Minimal Clean Bootstrap 5.3 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

    <!-- Top Navigation Bar -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark mb-4 shadow-sm">
        <div class="container">
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

    <div class="container mb-5">

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

        <div class="row g-4">

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
                                    <th class="text-muted align-top">Residential Address:</th>
                                    <td>
                                        <?php if ($fullAddress || $cityStateZip): ?>
                                            <?= htmlspecialchars($fullAddress, ENT_QUOTES, 'UTF-8') ?><br>
                                            <?= htmlspecialchars($cityStateZip, ENT_QUOTES, 'UTF-8') ?><br>
                                            <?= htmlspecialchars($user['Country'] ?? 'USA', ENT_QUOTES, 'UTF-8') ?>
                                        <?php else: ?>
                                            <span class="text-muted">No address on file</span>
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
                                                <strong>Dr. <?= htmlspecialchars($studentData['advisor']['User_FName'] . ' ' . $studentData['advisor']['User_LName'], ENT_QUOTES, 'UTF-8') ?></strong><br>
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

    </div>

    <!-- Bootstrap 5.3 JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

