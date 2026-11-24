<?php
/**
 * Lucid University Course Registration System
 * Standalone User Profile Viewer
 *
 * Dedicated profile viewing interface displaying user account information
 * and granular role-specific academic and professional records.
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
require_once __DIR__ . '/includes/user_helpers.php';
require_once __DIR__ . '/includes/student_helpers.php';
require_once __DIR__ . '/includes/faculty_helpers.php';
require_once __DIR__ . '/includes/schedule_helpers.php';

$target_id = isset($_GET['user_id']) ? (int)$_GET['user_id'] : $viewer_id;

// 1. Fetch Target User Record (fallback to viewer's own profile if missing)
$user = getUserAccountById($conn, $target_id);
if (!$user) {
    $target_id = $viewer_id;
    $user = getUserAccountById($conn, $target_id);
}
if (!$user) {
    die("User record not found.");
}

$target_type = $user['User_Type'];

// 2. Permission & Access Control (FERPA + Directory Rules)
$hasAccess = false;
$canViewPrivate = false;

if ($target_id === $viewer_id || $viewer_type === 'Admin') {
    $hasAccess = true;
    $canViewPrivate = true;
} elseif ($viewer_type === 'Stat-Dept') {
    $hasAccess = true;
    $canViewPrivate = false;
} elseif ($target_type === 'Faculty') {
    $hasAccess = true;
    $canViewPrivate = false;
} elseif ($viewer_type === 'Faculty' && $target_type === 'Student') {
    $hasAccess = canViewerAccessStudentRecord($conn, $viewer_id, $viewer_type, $target_id);
    $canViewPrivate = $hasAccess;
}

if (!$hasAccess) {
    header("Location: user_profile.php?user_id=" . $viewer_id);
    exit;
}

$fullName = trim(($user['User_FName'] ?? '') . ' ' . (!empty($user['User_MName']) ? $user['User_MName'] . ' ' : '') . ($user['User_LName'] ?? ''));
$rootPrefix = '';

$validAdminViews = ['details', 'enrollment', 'grades', 'attendance', 'transcript', 'audit'];
$adminView = (isset($_GET['view']) && in_array($_GET['view'], $validAdminViews, true)) ? $_GET['view'] : 'details';

// 3. Load Role-Specific Profile & Tab Payloads
$studentData      = null;
$facultyData      = null;
$adminData        = null;
$statData         = null;
$facultyAdvisees  = [];
$teachingSections = [];
$rosterSection    = null;
$rosterStudents   = null;
$fallSchedule     = null;
$springSchedule   = null;
$attRecords       = null;
$transcriptData   = null;
$auditData        = null;
$studentHolds     = [];

if ($target_type === 'Student') {
    $studentData = getStudentProfileDetails($conn, $target_id);

    if ($viewer_type === 'Admin') {
        $studentHolds = $studentData['holds'] ?? getStudentHolds($conn, $target_id);
        if (in_array($adminView, ['enrollment', 'grades'], true)) {
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
} elseif ($target_type === 'Faculty') {
    $facultyData = getFacultyProfileDetails($conn, $target_id, $canViewPrivate);
    $facultyAdvisees = $facultyData['advisees'] ?? [];
    $teachingSchedules = getFacultyScheduleBySemester($conn, $target_id, null);
    $teachingSections = $teachingSchedules['sections'];

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
    $securityLevel = getAdminSecurityLevel($conn, $target_id);
    $adminData = ($securityLevel !== null) ? ['Security_Level' => $securityLevel] : null;
} elseif ($target_type === 'Stat-Dept') {
    $statData = [
        'title'     => 'Statistical & Institutional Research Department Staff',
        'status'    => 'Full-Time Staff Member',
        'clearance' => 'Anonymized Institutional Academic Records Clearance',
    ];
}

$dashboardUrl = match ($viewer_type) {
    'Student' => 'student/dashboard.php',
    'Faculty' => 'faculty/dashboard.php',
    'Admin'   => 'admin/dashboard.php',
    default   => 'login.php',
};
$initials = strtoupper(substr($user['User_FName'] ?? '', 0, 1) . substr($user['User_LName'] ?? '', 0, 1));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Profile - <?= htmlspecialchars($fullName, ENT_QUOTES, 'UTF-8') ?> | Lucid University</title>
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
        <?php if ($target_type === 'Student' && $viewer_type === 'Admin'): ?>
            <?php require __DIR__ . '/includes/partials/admin_student_workspace.php'; ?>
        <?php else: ?>
            <!-- Standard User Profile Header Card -->
            <div class="card shadow-sm mb-4 border-0">
                <div class="card-body p-4 bg-white rounded-3">
                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
                        <div class="d-flex align-items-center gap-3">
                            <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center fw-bold fs-3" style="width: 64px; height: 64px;">
                                <?= htmlspecialchars($initials, ENT_QUOTES, 'UTF-8') ?>
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
                        <div class="d-flex flex-wrap gap-2">
                            <?php if (in_array($viewer_type, ['Student', 'Faculty'], true) && $target_id === $viewer_id): ?>
                                <a href="<?= $viewer_type === 'Faculty' ? 'faculty' : 'student' ?>/profile_requests.php" class="btn btn-primary">
                                    Request Profile Change
                                </a>
                            <?php elseif ($viewer_type === 'Admin'): ?>
                                <a href="admin/manage_change_requests.php?search_user=<?= (int)$target_id ?>" class="btn btn-outline-primary">
                                    Change Requests &amp; Audit Log
                                </a>
                            <?php endif; ?>
                            <a href="<?= htmlspecialchars($dashboardUrl, ENT_QUOTES, 'UTF-8') ?>" class="btn btn-outline-secondary">
                                &larr; Back to Dashboard
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row g-4 mb-4">
                <?php
                    $isAdminDetailsView = false;
                    $demographicsOnly = ($target_type !== 'Student' || !$studentData);
                    $colClass = 'col-lg-6';
                    require __DIR__ . '/includes/partials/student_profile_cards.php';
                ?>

                <?php if ($target_type !== 'Student' || !$studentData): ?>
                    <?php require __DIR__ . '/includes/partials/user_role_details_card.php'; ?>
                <?php endif; ?>
            </div>

            <?php if ($target_type === 'Faculty'): ?>
                <?php require __DIR__ . '/includes/partials/faculty_profile_sections.php'; ?>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
