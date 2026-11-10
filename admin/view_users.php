<?php
/**
 * Lucid University Course Registration System
 * Admin User Directory with Role Subtype Views
 */

require_once 'admin_guard.php';
require_once '../db_connect.php';
require_once __DIR__ . '/../includes/academic_helpers.php';
require_once '../profile_hover_card.php';

$admin_id = (int)$userID;

// Read selected view tab
$validViews = ['all', 'admins', 'undergrads', 'graduates', 'faculty'];
$view = $_GET['view'] ?? 'all';
if (!in_array($view, $validViews, true)) {
    $view = 'all';
}

// Read filter parameters
$search_id    = isset($_GET['search_id']) ? trim($_GET['search_id']) : '';
$search_name  = isset($_GET['search_name']) ? trim($_GET['search_name']) : '';
$search_email = isset($_GET['search_email']) ? trim($_GET['search_email']) : '';

// Common WHERE clause builder
$whereClause = " WHERE 1=1 ";
$types = "";
$params = [];

if ($search_id !== '') {
    $whereClause .= " AND CAST(u.User_ID AS CHAR) LIKE ? ";
    $types .= "s";
    $params[] = '%' . $search_id . '%';
}

if ($search_name !== '') {
    $whereClause .= " AND (u.User_FName LIKE ? OR u.User_LName LIKE ? OR CONCAT(u.User_FName, ' ', u.User_LName) LIKE ?) ";
    $types .= "sss";
    $params[] = '%' . $search_name . '%';
    $params[] = '%' . $search_name . '%';
    $params[] = '%' . $search_name . '%';
}

if ($search_email !== '') {
    $whereClause .= " AND l.User_Email LIKE ? ";
    $types .= "s";
    $params[] = '%' . $search_email . '%';
}

// Select query tailored to current view
switch ($view) {
    case 'admins':
        $sql = "
            SELECT
                u.User_ID,
                a.Admin_ID,
                a.Security_Level,
                u.User_Type,
                u.User_FName,
                u.User_MName,
                u.User_LName,
                u.User_Gender,
                u.User_DOB,
                u.User_PhoneNo,
                u.Street_No,
                u.Street_Name,
                u.City,
                u.State,
                u.Country,
                u.Zip_Code,
                l.User_Email,
                l.IsLock
            FROM `USER` u
            JOIN ADMIN a ON u.User_ID = a.Admin_ID
            LEFT JOIN LOGIN l ON u.User_ID = l.User_ID
            {$whereClause}
            ORDER BY a.Admin_ID ASC
        ";
        break;

    case 'undergrads':
        $sql = "
            SELECT
                u.User_ID,
                s.Student_ID,
                s.Student_Year,
                s.Credits_Earned,
                ug.Undergrad_Type,
                d.Department_Name,
                u.User_FName,
                u.User_MName,
                u.User_LName,
                u.User_PhoneNo,
                u.Street_No,
                u.Street_Name,
                u.City,
                u.State,
                u.Country,
                u.Zip_Code,
                l.User_Email,
                l.IsLock,
                (SELECT GROUP_CONCAT(m.Major_Name ORDER BY m.Major_Name SEPARATOR ', ')
                 FROM STUDENT_MAJORS sm
                 JOIN MAJORS m ON sm.Major_ID = m.Major_ID
                 WHERE sm.Student_ID = s.Student_ID) AS majors_list,
                (SELECT GROUP_CONCAT(mn.Minor_Name ORDER BY mn.Minor_Name SEPARATOR ', ')
                 FROM STUDENT_MINORS smn
                 JOIN MINORS mn ON smn.Minor_ID = mn.Minor_ID
                 WHERE smn.Student_ID = s.Student_ID) AS minors_list,
                (SELECT GROUP_CONCAT(h.Hold_Type ORDER BY h.Hold_Type SEPARATOR ', ')
                 FROM STUDENT_HOLDS sh
                 JOIN HOLDS h ON sh.Hold_ID = h.Hold_ID
                 WHERE sh.Student_ID = s.Student_ID) AS holds_list
            FROM `USER` u
            JOIN STUDENT s ON u.User_ID = s.Student_ID
            JOIN UNDERGRADUATE_STUDENT ug ON s.Student_ID = ug.Student_ID
            LEFT JOIN DEPARTMENT d ON s.Dept_ID = d.Department_ID
            LEFT JOIN LOGIN l ON u.User_ID = l.User_ID
            {$whereClause}
            ORDER BY s.Student_ID ASC
        ";
        break;

    case 'graduates':
        $sql = "
            SELECT
                u.User_ID,
                s.Student_ID,
                s.Student_Year,
                s.Credits_Earned,
                g.Grad_Type,
                g.Grad_Program,
                g.Thesis,
                d.Department_Name,
                u.User_FName,
                u.User_MName,
                u.User_LName,
                u.User_PhoneNo,
                u.Street_No,
                u.Street_Name,
                u.City,
                u.State,
                u.Country,
                u.Zip_Code,
                l.User_Email,
                l.IsLock,
                (SELECT GROUP_CONCAT(m.Major_Name ORDER BY m.Major_Name SEPARATOR ', ')
                 FROM STUDENT_MAJORS sm
                 JOIN MAJORS m ON sm.Major_ID = m.Major_ID
                 WHERE sm.Student_ID = s.Student_ID) AS majors_list,
                (SELECT GROUP_CONCAT(h.Hold_Type ORDER BY h.Hold_Type SEPARATOR ', ')
                 FROM STUDENT_HOLDS sh
                 JOIN HOLDS h ON sh.Hold_ID = h.Hold_ID
                 WHERE sh.Student_ID = s.Student_ID) AS holds_list
            FROM `USER` u
            JOIN STUDENT s ON u.User_ID = s.Student_ID
            JOIN GRADUATE_STUDENT g ON s.Student_ID = g.Student_ID
            LEFT JOIN DEPARTMENT d ON s.Dept_ID = d.Department_ID
            LEFT JOIN LOGIN l ON u.User_ID = l.User_ID
            {$whereClause}
            ORDER BY s.Student_ID ASC
        ";
        break;

    case 'faculty':
        $sql = "
            SELECT
                u.User_ID,
                f.Faculty_ID,
                f.Faculty_Type,
                f.Specialty,
                f.Office_ID,
                f.`Rank`,
                u.User_FName,
                u.User_MName,
                u.User_LName,
                u.User_PhoneNo,
                u.Street_No,
                u.Street_Name,
                u.City,
                u.State,
                u.Country,
                u.Zip_Code,
                l.User_Email,
                l.IsLock,
                (SELECT GROUP_CONCAT(CONCAT(d.Department_Name, ' (', dr.Percent_Time, '%)') ORDER BY d.Department_Name SEPARATOR ', ')
                 FROM DEPARTMENT_ROSTER dr
                 JOIN DEPARTMENT d ON dr.Department_ID = d.Department_ID
                 WHERE dr.Faculty_ID = f.Faculty_ID) AS depts_list
            FROM `USER` u
            JOIN FACULTY f ON u.User_ID = f.Faculty_ID
            LEFT JOIN FULL_TIME_FACULTY ftf ON f.Faculty_ID = ftf.Faculty_ID
            LEFT JOIN LOGIN l ON u.User_ID = l.User_ID
            {$whereClause}
            ORDER BY f.Faculty_ID ASC
        ";
        break;

    case 'all':
    default:
        $sql = "
            SELECT
                u.User_ID,
                u.User_Type,
                u.User_FName,
                u.User_MName,
                u.User_LName,
                u.User_Gender,
                u.User_DOB,
                u.User_PhoneNo,
                u.Street_No,
                u.Street_Name,
                u.City,
                u.State,
                u.Country,
                u.Zip_Code,
                l.User_Email,
                l.IsLock
            FROM `USER` u
            LEFT JOIN LOGIN l ON u.User_ID = l.User_ID
            {$whereClause}
            ORDER BY u.User_ID ASC
        ";
        break;
}

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

$yearMap = [
    1 => 'Freshman',
    2 => 'Sophomore',
    3 => 'Junior',
    4 => 'Senior',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Directory &bull; <?= ucfirst($view) ?> | Lucid University Admin</title>
    <!-- Minimal Clean Bootstrap 5.3 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        <?= getProfileHoverCardStyles() ?>
    </style>
</head>
<body class="bg-light">

    <?= renderAdminNavbar($conn, $admin_id, basename($_SERVER['PHP_SELF'])) ?>

    <div class="container-fluid px-lg-5 mb-5">

        <!-- Page Header -->
        <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
            <div>
                <h2 class="fw-bold mb-1">University User Directory</h2>
                <div class="text-secondary">
                    Browse and inspect university user accounts by role classification and subtype academic details.
                </div>
            </div>
            <div class="d-flex gap-2">
                <a href="create_user.php" class="btn btn-primary btn-sm">+ Create New User</a>
                <a href="dashboard.php" class="btn btn-outline-secondary btn-sm">&larr; Back to Dashboard</a>
            </div>
        </div>

        <!-- Role Subtype View Tabs -->
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-body p-2 bg-white">
                <ul class="nav nav-pills nav-fill">
                    <li class="nav-item">
                        <a class="nav-link <?= ($view === 'all') ? 'active' : '' ?>" href="view_users.php?view=all">
                            <strong>All Users</strong>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= ($view === 'admins') ? 'active' : '' ?>" href="view_users.php?view=admins">
                            <strong>Admins</strong>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= ($view === 'undergrads') ? 'active' : '' ?>" href="view_users.php?view=undergrads">
                            <strong>Undergraduates</strong>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= ($view === 'graduates') ? 'active' : '' ?>" href="view_users.php?view=graduates">
                            <strong>Graduates</strong>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= ($view === 'faculty') ? 'active' : '' ?>" href="view_users.php?view=faculty">
                            <strong>Faculty</strong>
                        </a>
                    </li>
                </ul>
            </div>
        </div>

        <!-- Search & Filter Card -->
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-body bg-white p-3">
                <form method="GET" action="view_users.php" class="row g-2 align-items-end">
                    <input type="hidden" name="view" value="<?= htmlspecialchars($view, ENT_QUOTES, 'UTF-8') ?>">
                    <div class="col-md-3">
                        <label for="search_id" class="form-label small fw-bold">User / Role ID</label>
                        <input type="text" name="search_id" id="search_id" class="form-control form-control-sm" placeholder="e.g. 100001" value="<?= htmlspecialchars($search_id, ENT_QUOTES, 'UTF-8') ?>">
                    </div>
                    <div class="col-md-4">
                        <label for="search_name" class="form-label small fw-bold">Name</label>
                        <input type="text" name="search_name" id="search_name" class="form-control form-control-sm" placeholder="First or Last Name" value="<?= htmlspecialchars($search_name, ENT_QUOTES, 'UTF-8') ?>">
                    </div>
                    <div class="col-md-3">
                        <label for="search_email" class="form-label small fw-bold">Email Address</label>
                        <input type="text" name="search_email" id="search_email" class="form-control form-control-sm" placeholder="e.g. @lucid.edu" value="<?= htmlspecialchars($search_email, ENT_QUOTES, 'UTF-8') ?>">
                    </div>
                    <div class="col-md-2 d-flex gap-2">
                        <button type="submit" class="btn btn-primary btn-sm flex-grow-1">Filter</button>
                        <?php if ($search_id !== '' || $search_name !== '' || $search_email !== ''): ?>
                            <a href="view_users.php?view=<?= urlencode($view) ?>" class="btn btn-outline-secondary btn-sm">Reset</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>

        <!-- Users Table Card -->
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <h5 class="mb-0 fw-bold">
                    Registered <?= ucfirst($view) ?> Records
                </h5>
                <span class="badge bg-secondary"><?= $result->num_rows ?> Total Accounts</span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover table-striped mb-0 align-middle small">
                        <thead class="table-light">
                            <?php if ($view === 'all'): ?>
                                <tr>
                                    <th>User ID</th>
                                    <th>Role</th>
                                    <th>Status</th>
                                    <th>Full Name</th>
                                    <th>Email</th>
                                    <th>Gender</th>
                                    <th>Date of Birth</th>
                                    <th>Phone</th>
                                    <th>Street Address</th>
                                    <th>City, State ZIP</th>
                                    <th>Country</th>
                                </tr>
                            <?php elseif ($view === 'admins'): ?>
                                <tr>
                                    <th>Admin ID</th>
                                    <th>Full Name</th>
                                    <th>Email</th>
                                    <th>Security Level</th>
                                    <th>Status</th>
                                    <th>Gender</th>
                                    <th>Date of Birth</th>
                                    <th>Phone</th>
                                    <th>Full Address</th>
                                </tr>
                            <?php elseif ($view === 'undergrads'): ?>
                                <tr>
                                    <th>Student ID</th>
                                    <th>Full Name</th>
                                    <th>Email</th>
                                    <th>Class Standing</th>
                                    <th>Attendance</th>
                                    <th>Department</th>
                                    <th>Major(s)</th>
                                    <th>Minor</th>
                                    <th>Credits Earned</th>
                                    <th>Active Holds</th>
                                    <th>Phone</th>
                                    <th>Address</th>
                                </tr>
                            <?php elseif ($view === 'graduates'): ?>
                                <tr>
                                    <th>Student ID</th>
                                    <th>Full Name</th>
                                    <th>Email</th>
                                    <th>Program</th>
                                    <th>Attendance</th>
                                    <th>Thesis Title</th>
                                    <th>Department</th>
                                    <th>Major</th>
                                    <th>Credits Earned</th>
                                    <th>Active Holds</th>
                                    <th>Phone</th>
                                    <th>Address</th>
                                </tr>
                            <?php elseif ($view === 'faculty'): ?>
                                <tr>
                                    <th>Faculty ID</th>
                                    <th>Full Name</th>
                                    <th>Email</th>
                                    <th>Rank</th>
                                    <th>Type</th>
                                    <th>Specialty</th>
                                    <th>Office</th>
                                    <th>Department(s) &amp; % Time</th>
                                    <th>Phone</th>
                                    <th>Address</th>
                                </tr>
                            <?php endif; ?>
                        </thead>
                        <tbody>
                            <?php if ($result->num_rows === 0): ?>
                                <tr>
                                    <td colspan="12" class="p-4 text-center text-muted">
                                        No users found matching your search criteria.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php while ($user = $result->fetch_assoc()): 
                                    $uid = (int)$user["User_ID"];
                                    $fullName = trim($user["User_FName"] . ' ' . ($user["User_MName"] ? $user["User_MName"] . ' ' : '') . $user["User_LName"]);
                                    $fullAddr = trim(($user["Street_No"] ?? '') . ' ' . ($user["Street_Name"] ?? ''));
                                    $cityStateZip = trim(($user["City"] ?? '') . ($user["State"] ? ', ' . $user["State"] : '') . ' ' . ($user["Zip_Code"] ?? ''));
                                    $combinedAddr = trim($fullAddr . ($fullAddr && $cityStateZip ? ' &bull; ' : '') . $cityStateZip);
                                    $isLocked = !empty($user['IsLock']);
                                ?>
                                    <?php if ($view === 'all'): 
                                        $userType = $user["User_Type"];
                                        $typeBadgeClass = match($userType) {
                                            'Admin' => 'bg-danger',
                                            'Faculty' => 'bg-info text-dark',
                                            'Student' => 'bg-success',
                                            'Stat-Dept' => 'bg-warning text-dark',
                                            default => 'bg-secondary'
                                        };
                                    ?>
                                        <tr>
                                            <td>
                                                <a href="../user_profile.php?user_id=<?= $uid ?>" class="fw-bold text-decoration-none">
                                                    <?= $uid ?>
                                                </a>
                                            </td>
                                            <td>
                                                <span class="badge <?= $typeBadgeClass ?>">
                                                    <?= ($userType === "Admin") ? "Administrator" : htmlspecialchars($userType, ENT_QUOTES, 'UTF-8') ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php if ($isLocked): ?>
                                                    <span class="badge bg-danger">Locked</span>
                                                <?php else: ?>
                                                    <span class="badge bg-success">Active</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="fw-semibold">
                                                <?= htmlspecialchars($fullName, ENT_QUOTES, 'UTF-8') ?>
                                            </td>
                                            <td><?= htmlspecialchars($user["User_Email"] ?? "-", ENT_QUOTES, 'UTF-8') ?></td>
                                            <td><?= htmlspecialchars($user["User_Gender"] ?? "-", ENT_QUOTES, 'UTF-8') ?></td>
                                            <td><?= htmlspecialchars($user["User_DOB"] ?? "-", ENT_QUOTES, 'UTF-8') ?></td>
                                            <td><?= htmlspecialchars($user["User_PhoneNo"] ?? "-", ENT_QUOTES, 'UTF-8') ?></td>
                                            <td><?= htmlspecialchars($fullAddr ?: '-', ENT_QUOTES, 'UTF-8') ?></td>
                                            <td><?= htmlspecialchars($cityStateZip ?: '-', ENT_QUOTES, 'UTF-8') ?></td>
                                            <td><?= htmlspecialchars($user["Country"] ?? "-", ENT_QUOTES, 'UTF-8') ?></td>
                                        </tr>

                                    <?php elseif ($view === 'admins'): 
                                        $secLevel = (int)($user['Security_Level'] ?? 1);
                                    ?>
                                        <tr>
                                            <td>
                                                <a href="../user_profile.php?user_id=<?= $uid ?>" class="fw-bold text-decoration-none">
                                                    <?= $uid ?>
                                                </a>
                                            </td>
                                            <td class="fw-semibold">
                                                <?= htmlspecialchars($fullName, ENT_QUOTES, 'UTF-8') ?>
                                            </td>
                                            <td><?= htmlspecialchars($user["User_Email"] ?? "-", ENT_QUOTES, 'UTF-8') ?></td>
                                            <td>
                                                <?php if ($secLevel >= 2): ?>
                                                    <span class="badge bg-danger">Level <?= $secLevel ?> &bull; Read/Write</span>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary">Level 1 &bull; Read-Only</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($isLocked): ?>
                                                    <span class="badge bg-danger">Locked</span>
                                                <?php else: ?>
                                                    <span class="badge bg-success">Active</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?= htmlspecialchars($user["User_Gender"] ?? "-", ENT_QUOTES, 'UTF-8') ?></td>
                                            <td><?= htmlspecialchars($user["User_DOB"] ?? "-", ENT_QUOTES, 'UTF-8') ?></td>
                                            <td><?= htmlspecialchars($user["User_PhoneNo"] ?? "-", ENT_QUOTES, 'UTF-8') ?></td>
                                            <td><?= htmlspecialchars($combinedAddr ?: '-', ENT_QUOTES, 'UTF-8') ?></td>
                                        </tr>

                                    <?php elseif ($view === 'undergrads'): 
                                        $yearVal = (int)($user['Student_Year'] ?? 1);
                                        $standing = $yearMap[$yearVal] ?? 'Undergraduate';
                                        $holdsStr = trim($user['holds_list'] ?? '');
                                    ?>
                                        <tr>
                                            <td>
                                                <a href="../user_profile.php?user_id=<?= $uid ?>" class="fw-bold text-decoration-none">
                                                    <?= $uid ?>
                                                </a>
                                            </td>
                                            <td class="fw-semibold">
                                                <?= htmlspecialchars($fullName, ENT_QUOTES, 'UTF-8') ?>
                                            </td>
                                            <td><?= htmlspecialchars($user["User_Email"] ?? "-", ENT_QUOTES, 'UTF-8') ?></td>
                                            <td>
                                                <span class="badge bg-info-subtle text-info border">
                                                    <?= htmlspecialchars($standing, ENT_QUOTES, 'UTF-8') ?>
                                                </span>
                                            </td>
                                            <td><?= htmlspecialchars($user["Undergrad_Type"] ?? 'Full-Time', ENT_QUOTES, 'UTF-8') ?></td>
                                            <td><?= htmlspecialchars($user["Department_Name"] ?? 'General', ENT_QUOTES, 'UTF-8') ?></td>
                                            <td><?= htmlspecialchars($user["majors_list"] ?? 'Undeclared', ENT_QUOTES, 'UTF-8') ?></td>
                                            <td><?= htmlspecialchars($user["minors_list"] ?? 'None', ENT_QUOTES, 'UTF-8') ?></td>
                                            <td class="fw-bold text-center"><?= (int)($user["Credits_Earned"] ?? 0) ?></td>
                                            <td>
                                                <?php if (!empty($holdsStr)): ?>
                                                    <span class="badge bg-danger" title="<?= htmlspecialchars($holdsStr, ENT_QUOTES, 'UTF-8') ?>">
                                                        <?= htmlspecialchars($holdsStr, ENT_QUOTES, 'UTF-8') ?>
                                                    </span>
                                                <?php else: ?>
                                                    <span class="badge bg-success-subtle text-success">None</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?= htmlspecialchars($user["User_PhoneNo"] ?? "-", ENT_QUOTES, 'UTF-8') ?></td>
                                            <td><?= htmlspecialchars($combinedAddr ?: '-', ENT_QUOTES, 'UTF-8') ?></td>
                                        </tr>

                                    <?php elseif ($view === 'graduates'): 
                                        $holdsStr = trim($user['holds_list'] ?? '');
                                    ?>
                                        <tr>
                                            <td>
                                                <a href="../user_profile.php?user_id=<?= $uid ?>" class="fw-bold text-decoration-none">
                                                    <?= $uid ?>
                                                </a>
                                            </td>
                                            <td class="fw-semibold">
                                                <?= htmlspecialchars($fullName, ENT_QUOTES, 'UTF-8') ?>
                                            </td>
                                            <td><?= htmlspecialchars($user["User_Email"] ?? "-", ENT_QUOTES, 'UTF-8') ?></td>
                                            <td>
                                                <span class="badge bg-primary-subtle text-primary border">
                                                    <?= htmlspecialchars($user["Grad_Program"] ?? 'MS', ENT_QUOTES, 'UTF-8') ?>
                                                </span>
                                            </td>
                                            <td><?= htmlspecialchars($user["Grad_Type"] ?? 'Full-Time', ENT_QUOTES, 'UTF-8') ?></td>
                                            <td><em><?= htmlspecialchars($user["Thesis"] ?: 'Non-Thesis', ENT_QUOTES, 'UTF-8') ?></em></td>
                                            <td><?= htmlspecialchars($user["Department_Name"] ?? 'General', ENT_QUOTES, 'UTF-8') ?></td>
                                            <td><?= htmlspecialchars($user["majors_list"] ?? 'Undeclared', ENT_QUOTES, 'UTF-8') ?></td>
                                            <td class="fw-bold text-center"><?= (int)($user["Credits_Earned"] ?? 0) ?></td>
                                            <td>
                                                <?php if (!empty($holdsStr)): ?>
                                                    <span class="badge bg-danger" title="<?= htmlspecialchars($holdsStr, ENT_QUOTES, 'UTF-8') ?>">
                                                        <?= htmlspecialchars($holdsStr, ENT_QUOTES, 'UTF-8') ?>
                                                    </span>
                                                <?php else: ?>
                                                    <span class="badge bg-success-subtle text-success">None</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?= htmlspecialchars($user["User_PhoneNo"] ?? "-", ENT_QUOTES, 'UTF-8') ?></td>
                                            <td><?= htmlspecialchars($combinedAddr ?: '-', ENT_QUOTES, 'UTF-8') ?></td>
                                        </tr>

                                    <?php elseif ($view === 'faculty'): ?>
                                        <tr>
                                            <td>
                                                <a href="../user_profile.php?user_id=<?= $uid ?>" class="fw-bold text-decoration-none">
                                                    <?= $uid ?>
                                                </a>
                                            </td>
                                            <td class="fw-semibold">
                                                <?= htmlspecialchars($fullName, ENT_QUOTES, 'UTF-8') ?>
                                            </td>
                                            <td><?= htmlspecialchars($user["User_Email"] ?? "-", ENT_QUOTES, 'UTF-8') ?></td>
                                            <td>
                                                <span class="badge bg-info-subtle text-info border">
                                                    <?= htmlspecialchars($user["Rank"] ?? 'Instructor', ENT_QUOTES, 'UTF-8') ?>
                                                </span>
                                            </td>
                                            <td><?= htmlspecialchars($user["Faculty_Type"] ?? 'Full-Time', ENT_QUOTES, 'UTF-8') ?></td>
                                            <td><?= htmlspecialchars($user["Specialty"] ?? '-', ENT_QUOTES, 'UTF-8') ?></td>
                                            <td><?= htmlspecialchars($user["Office_ID"] ?? 'TBA', ENT_QUOTES, 'UTF-8') ?></td>
                                            <td><?= htmlspecialchars($user["depts_list"] ?? 'None', ENT_QUOTES, 'UTF-8') ?></td>
                                            <td><?= htmlspecialchars($user["User_PhoneNo"] ?? "-", ENT_QUOTES, 'UTF-8') ?></td>
                                            <td><?= htmlspecialchars($combinedAddr ?: '-', ENT_QUOTES, 'UTF-8') ?></td>
                                        </tr>
                                    <?php endif; ?>
                                <?php endwhile; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </div>

    <!-- Bootstrap 5.3 JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
