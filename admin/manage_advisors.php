<?php
/**
 * Lucid University Course Registration System
 * Admin Portal - Student & Faculty Academic Advising Management
 *
 * Implements SRS & Sitemap Use Cases:
 * 1. View Student's Faculty Advisors (Admin's View)
 * 2. View Faculty's Advisee Roster & Capacity
 * 3. Assign / Reassign eligible faculty advisors to students (Full-Time only, <= 15 capacity, department match)
 * 4. Multi-select student assignment with capacity safeguards
 */

require_once 'admin_guard.php';
require_once '../db_connect.php';
require_once '../profile_hover_card.php';

$admin_id = (int)$userID;
$adminNavHtml = getUserNavbarProfileHtml($conn, $admin_id, 'Admin');
$canWrite = ((int)($_SESSION['Security_Level'] ?? 1) >= 2);

$successMessage = $_SESSION["success_message"] ?? "";
$errorMessage   = $_SESSION["error_message"] ?? "";
unset($_SESSION["success_message"], $_SESSION["error_message"]);

$activeTab = $_GET['tab'] ?? 'students';
if (!in_array($activeTab, ['students', 'faculty'], true)) {
    $activeTab = 'students';
}

// -----------------------------------------------------------------------------
// 1. Fetch All Academic Departments
// -----------------------------------------------------------------------------
$departments = [];
$deptRes = $conn->query("SELECT Department_ID, Department_Name FROM DEPARTMENT ORDER BY Department_Name ASC");
if ($deptRes) {
    while ($dRow = $deptRes->fetch_assoc()) {
        $departments[(int)$dRow['Department_ID']] = $dRow['Department_Name'];
    }
}

// -----------------------------------------------------------------------------
// 2. Fetch All Students, Declared Majors, and Assigned Advisors
// -----------------------------------------------------------------------------
$studentsSql = "
    SELECT 
        s.Student_ID,
        s.Student_Year,
        s.Student_Type,
        s.Dept_ID AS Student_Dept_ID,
        d_s.Department_Name AS Student_Dept_Name,
        u_s.User_FName AS Student_FName,
        u_s.User_MName AS Student_MName,
        u_s.User_LName AS Student_LName,
        u_s.User_PhoneNo AS Student_Phone,
        l_s.User_Email AS Student_Email,
        m.Major_ID,
        m.Major_Name,
        m.Department_ID AS Major_Dept_ID,
        adv.Faculty_ID,
        adv.Date_Assigned,
        u_f.User_FName AS Faculty_FName,
        u_f.User_LName AS Faculty_LName,
        l_f.User_Email AS Faculty_Email,
        u_f.User_PhoneNo AS Faculty_Phone,
        f.Office_ID AS Faculty_Office,
        f.Faculty_Type,
        f.Rank AS Faculty_Rank,
        d_f.Department_Name AS Faculty_Dept_Name
    FROM STUDENT s
    JOIN `USER` u_s ON s.Student_ID = u_s.User_ID
    LEFT JOIN LOGIN l_s ON s.Student_ID = l_s.User_ID
    LEFT JOIN DEPARTMENT d_s ON s.Dept_ID = d_s.Department_ID
    LEFT JOIN STUDENT_MAJORS sm ON s.Student_ID = sm.Student_ID
    LEFT JOIN MAJORS m ON sm.Major_ID = m.Major_ID
    LEFT JOIN ADVISOR_STUDENT adv ON s.Student_ID = adv.Student_ID
    LEFT JOIN FACULTY f ON adv.Faculty_ID = f.Faculty_ID
    LEFT JOIN `USER` u_f ON f.Faculty_ID = u_f.User_ID
    LEFT JOIN LOGIN l_f ON f.Faculty_ID = l_f.User_ID
    LEFT JOIN DEPARTMENT_ROSTER dr ON f.Faculty_ID = dr.Faculty_ID
    LEFT JOIN DEPARTMENT d_f ON dr.Department_ID = d_f.Department_ID
    ORDER BY u_s.User_LName ASC, u_s.User_FName ASC, s.Student_ID ASC
";

$studentsResult = $conn->query($studentsSql);
$students = [];
$studentsAdvisedCount = 0;
$studentsUnadvisedCount = 0;

if ($studentsResult) {
    while ($row = $studentsResult->fetch_assoc()) {
        $sid = (int)$row['Student_ID'];
        if (!isset($students[$sid])) {
            $students[$sid] = [
                'Student_ID'        => $sid,
                'Student_Year'      => (int)$row['Student_Year'],
                'Student_Type'      => $row['Student_Type'],
                'Student_Dept_ID'   => (int)$row['Student_Dept_ID'],
                'Student_Dept_Name' => $row['Student_Dept_Name'] ?? 'Unassigned',
                'Student_FName'     => $row['Student_FName'],
                'Student_MName'     => $row['Student_MName'],
                'Student_LName'     => $row['Student_LName'],
                'Student_Phone'     => $row['Student_Phone'],
                'Student_Email'     => $row['Student_Email'] ?? 'N/A',
                'Majors'            => [],
                'All_Dept_IDs'      => [],
                'Advisors'          => []
            ];
            if (!empty($row['Student_Dept_ID'])) {
                $students[$sid]['All_Dept_IDs'][] = (int)$row['Student_Dept_ID'];
            }
        }

        if (!empty($row['Major_ID']) && !isset($students[$sid]['Majors'][$row['Major_ID']])) {
            $students[$sid]['Majors'][$row['Major_ID']] = [
                'Major_ID'      => (int)$row['Major_ID'],
                'Major_Name'    => $row['Major_Name'],
                'Department_ID' => (int)$row['Major_Dept_ID']
            ];
            if (!empty($row['Major_Dept_ID'])) {
                $students[$sid]['All_Dept_IDs'][] = (int)$row['Major_Dept_ID'];
            }
        }

        if (!empty($row['Faculty_ID']) && !isset($students[$sid]['Advisors'][$row['Faculty_ID']])) {
            $students[$sid]['Advisors'][$row['Faculty_ID']] = [
                'Faculty_ID'        => (int)$row['Faculty_ID'],
                'Faculty_FName'     => $row['Faculty_FName'],
                'Faculty_LName'     => $row['Faculty_LName'],
                'Faculty_Email'     => $row['Faculty_Email'] ?? 'N/A',
                'Faculty_Phone'     => $row['Faculty_Phone'] ?? 'N/A',
                'Faculty_Office'    => $row['Faculty_Office'] ?? 'N/A',
                'Faculty_Type'      => $row['Faculty_Type'],
                'Faculty_Rank'      => $row['Faculty_Rank'],
                'Faculty_Dept_Name' => $row['Faculty_Dept_Name'] ?? 'N/A',
                'Date_Assigned'     => $row['Date_Assigned']
            ];
        }
    }
}

// Compute Advising Stats
foreach ($students as &$stu) {
    $stu['All_Dept_IDs'] = array_values(array_unique($stu['All_Dept_IDs']));
    if (!empty($stu['Advisors'])) {
        $studentsAdvisedCount++;
    } else {
        $studentsUnadvisedCount++;
    }
}
unset($stu);

// -----------------------------------------------------------------------------
// 3. Fetch Faculty Members, Departments, Advisee Counts, and Capacities
// -----------------------------------------------------------------------------
$facultySql = "
    SELECT 
        f.Faculty_ID,
        f.Faculty_Type,
        f.Rank,
        f.Office_ID,
        f.Specialty,
        u.User_FName,
        u.User_MName,
        u.User_LName,
        l.User_Email,
        u.User_PhoneNo,
        GROUP_CONCAT(DISTINCT d.Department_ID) AS Dept_IDs,
        GROUP_CONCAT(DISTINCT d.Department_Name ORDER BY d.Department_Name SEPARATOR ', ') AS Dept_Names,
        COUNT(DISTINCT adv.Student_ID) AS Advisee_Count
    FROM FACULTY f
    JOIN `USER` u ON f.Faculty_ID = u.User_ID
    LEFT JOIN LOGIN l ON f.Faculty_ID = l.User_ID
    LEFT JOIN DEPARTMENT_ROSTER dr ON f.Faculty_ID = dr.Faculty_ID
    LEFT JOIN DEPARTMENT d ON dr.Department_ID = d.Department_ID
    LEFT JOIN ADVISOR_STUDENT adv ON f.Faculty_ID = adv.Faculty_ID
    GROUP BY f.Faculty_ID, f.Faculty_Type, f.Rank, f.Office_ID, f.Specialty,
             u.User_FName, u.User_MName, u.User_LName, l.User_Email, u.User_PhoneNo
    ORDER BY u.User_LName ASC, u.User_FName ASC
";

$facultyResult = $conn->query($facultySql);
$facultyList = [];
$totalFullTimeFaculty = 0;
$totalPartTimeFaculty = 0;
$totalAvailableSpots = 0;

if ($facultyResult) {
    while ($fRow = $facultyResult->fetch_assoc()) {
        $fid = (int)$fRow['Faculty_ID'];
        $isFT = ($fRow['Faculty_Type'] === 'Full-Time');
        $advCount = (int)$fRow['Advisee_Count'];
        $availSpots = $isFT ? max(0, 15 - $advCount) : 0;

        if ($isFT) {
            $totalFullTimeFaculty++;
            $totalAvailableSpots += $availSpots;
        } else {
            $totalPartTimeFaculty++;
        }

        $rawDeptIds = !empty($fRow['Dept_IDs']) ? explode(',', $fRow['Dept_IDs']) : [];
        $deptIdArray = array_map('intval', $rawDeptIds);

        $facultyList[$fid] = [
            'Faculty_ID'      => $fid,
            'Faculty_Type'    => $fRow['Faculty_Type'],
            'Rank'            => $fRow['Rank'] ?? 'Faculty',
            'Office_ID'       => $fRow['Office_ID'] ?? 'N/A',
            'Specialty'       => $fRow['Specialty'] ?? 'N/A',
            'User_FName'      => $fRow['User_FName'],
            'User_MName'      => $fRow['User_MName'],
            'User_LName'      => $fRow['User_LName'],
            'User_Email'      => $fRow['User_Email'] ?? 'N/A',
            'User_PhoneNo'    => $fRow['User_PhoneNo'] ?? 'N/A',
            'Dept_IDs'        => $deptIdArray,
            'Dept_Names'      => $fRow['Dept_Names'] ?? 'Unassigned',
            'Advisee_Count'   => $advCount,
            'Available_Spots' => $availSpots,
            'Is_Full_Time'    => $isFT,
            'Advisees'        => []
        ];
    }
}

// -----------------------------------------------------------------------------
// 4. Fetch Detailed Advisee Roster per Faculty Member
// -----------------------------------------------------------------------------
$adviseesSql = "
    SELECT 
        adv.Faculty_ID,
        adv.Student_ID,
        adv.Date_Assigned,
        u.User_FName AS Student_FName,
        u.User_LName AS Student_LName,
        l.User_Email AS Student_Email,
        u.User_PhoneNo AS Student_Phone,
        s.Student_Type,
        s.Student_Year,
        d.Department_Name AS Student_Home_Dept,
        GROUP_CONCAT(DISTINCT m.Major_Name SEPARATOR ', ') AS Majors_List
    FROM ADVISOR_STUDENT adv
    JOIN STUDENT s ON adv.Student_ID = s.Student_ID
    JOIN `USER` u ON s.Student_ID = u.User_ID
    LEFT JOIN LOGIN l ON s.Student_ID = l.User_ID
    LEFT JOIN DEPARTMENT d ON s.Dept_ID = d.Department_ID
    LEFT JOIN STUDENT_MAJORS sm ON s.Student_ID = sm.Student_ID
    LEFT JOIN MAJORS m ON sm.Major_ID = m.Major_ID
    GROUP BY adv.Faculty_ID, adv.Student_ID, adv.Date_Assigned,
             u.User_FName, u.User_LName, l.User_Email, u.User_PhoneNo,
             s.Student_Type, s.Student_Year, d.Department_Name
    ORDER BY u.User_LName ASC, u.User_FName ASC
";

$adviseesResult = $conn->query($adviseesSql);
if ($adviseesResult) {
    while ($aRow = $adviseesResult->fetch_assoc()) {
        $fid = (int)$aRow['Faculty_ID'];
        if (isset($facultyList[$fid])) {
            $facultyList[$fid]['Advisees'][] = $aRow;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Academic Advising Management | Lucid University Admin</title>
    <!-- Minimal Clean Bootstrap 5.3 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        <?= getProfileHoverCardStyles() ?>
        .table-hover tbody tr:hover {
            background-color: #f8f9fa;
        }
        .capacity-bar {
            height: 6px;
            border-radius: 3px;
        }
        .badge-dept {
            font-size: 0.75rem;
            background-color: #e9ecef;
            color: #495057;
            border: 1px solid #ced4da;
        }
    </style>
</head>
<body class="bg-light">

    <!-- Unified Admin Top Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark mb-4 shadow-sm">
        <div class="container-fluid px-lg-5">
            <a class="navbar-brand fw-bold" href="dashboard.php">Lucid University <span class="badge bg-danger ms-1">Admin</span></a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#adminNav" aria-controls="adminNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="adminNav">
                <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                    <li class="nav-item">
                        <a class="nav-link" href="dashboard.php">Dashboard</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="create_user.php">Create User</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="view_users.php">View Users</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="create_course_section.php">Create Course Section</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="manage_courses.php">Manage Courses</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="manage_holds.php">Manage Holds</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="semesters.php">Semesters</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="majors_minors.php">Majors &amp; Minors</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" aria-current="page" href="manage_advisors.php">Advisors</a>
                    </li>
                </ul>
                <div class="d-flex align-items-center gap-3">
                    <?= $adminNavHtml ?>
                    <form action="../logout.php" method="POST" class="m-0">
                        <button type="submit" class="btn btn-outline-light btn-sm">Log Out</button>
                    </form>
                </div>
            </div>
        </div>
    </nav>

    <div class="container-fluid px-lg-5 mb-5">

        <!-- Flash Message Alerts -->
        <?php if (!empty($successMessage)): ?>
            <div class="alert alert-success alert-dismissible fade show shadow-sm" role="alert">
                <strong>Success!</strong> <?= htmlspecialchars($successMessage) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <?php if (!empty($errorMessage)): ?>
            <div class="alert alert-danger alert-dismissible fade show shadow-sm" role="alert">
                <strong>Notice:</strong> <?= htmlspecialchars($errorMessage) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <!-- Page Header & Key Metrics -->
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-body p-4 bg-white rounded-3">
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
                    <div>
                        <h2 class="fw-bold mb-1">Academic Advising Management</h2>
                        <p class="text-secondary mb-0">
                            Inspect student advisor assignments, review faculty advisee rosters, verify statutory advising capacities (max 15), and assign eligible full-time faculty advisors.
                        </p>
                    </div>
                    <div>
                        <?php if ($canWrite): ?>
                            <span class="badge bg-success-subtle text-success border border-success-subtle px-3 py-2 fs-6">
                                Security Level <?= htmlspecialchars((string)($_SESSION["Security_Level"] ?? '2')) ?>: Full Write Access
                            </span>
                        <?php else: ?>
                            <span class="badge bg-warning-subtle text-warning border border-warning-subtle px-3 py-2 fs-6">
                                Security Level 1: Read-Only Mode
                            </span>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- KPI Metric Counters -->
                <div class="row g-3 mt-3">
                    <div class="col-sm-6 col-lg-3">
                        <div class="p-3 bg-light rounded-3 border">
                            <div class="text-secondary small fw-semibold text-uppercase">Total Students</div>
                            <div class="h3 fw-bold mb-0 text-dark"><?= count($students) ?></div>
                            <div class="small text-muted"><?= $studentsAdvisedCount ?> Advised &bull; <?= $studentsUnadvisedCount ?> Pending</div>
                        </div>
                    </div>
                    <div class="col-sm-6 col-lg-3">
                        <div class="p-3 bg-light rounded-3 border">
                            <div class="text-secondary small fw-semibold text-uppercase">Students With Advisors</div>
                            <div class="h3 fw-bold mb-0 text-success"><?= $studentsAdvisedCount ?></div>
                            <div class="small text-muted"><?= count($students) > 0 ? round(($studentsAdvisedCount / count($students)) * 100, 1) : 0 ?>% Coverage</div>
                        </div>
                    </div>
                    <div class="col-sm-6 col-lg-3">
                        <div class="p-3 bg-light rounded-3 border">
                            <div class="text-secondary small fw-semibold text-uppercase">Eligible Faculty (Full-Time)</div>
                            <div class="h3 fw-bold mb-0 text-primary"><?= $totalFullTimeFaculty ?></div>
                            <div class="small text-muted"><?= $totalPartTimeFaculty ?> Part-Time (Ineligible per SRS)</div>
                        </div>
                    </div>
                    <div class="col-sm-6 col-lg-3">
                        <div class="p-3 bg-light rounded-3 border">
                            <div class="text-secondary small fw-semibold text-uppercase">Available Advising Slots</div>
                            <div class="h3 fw-bold mb-0 text-info"><?= $totalAvailableSpots ?></div>
                            <div class="small text-muted">Across <?= $totalFullTimeFaculty ?> Full-Time Professors (Max 15/prof)</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Navigation Tabs: Students View vs. Faculty View -->
        <ul class="nav nav-pills mb-4" id="advisingTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <a class="nav-link <?= $activeTab === 'students' ? 'active fw-bold' : 'text-dark' ?>" 
                   id="students-tab" href="?tab=students" role="tab">
                    Student Advising Directory (<?= count($students) ?>)
                </a>
            </li>
            <li class="nav-item" role="presentation">
                <a class="nav-link <?= $activeTab === 'faculty' ? 'active fw-bold' : 'text-dark' ?>" 
                   id="faculty-tab" href="?tab=faculty" role="tab">
                    Faculty Advising Roster &amp; Capacity (<?= count($facultyList) ?>)
                </a>
            </li>
        </ul>

        <?php if ($activeTab === 'students'): ?>
            <!-- =============================================================== -->
            <!-- TAB 1: STUDENT ADVISING DIRECTORY                               -->
            <!-- =============================================================== -->

            <!-- Multi-Select Bulk Assignment Action Bar (Hidden by default, shown via JS when >= 1 checkbox checked) -->
            <div id="bulkActionBar" class="card shadow-sm border-primary mb-4 bg-primary-subtle d-none">
                <div class="card-body p-3">
                    <form action="admin_manage_advisors_process.php" method="POST" id="bulkAssignForm" class="row g-2 align-items-center">
                        <input type="hidden" name="action" value="assign_batch">
                        <input type="hidden" name="return_tab" value="students">
                        <div id="bulkStudentHiddenInputs"></div>

                        <div class="col-md-4">
                            <span class="fw-bold text-primary" id="selectedStudentCountText">0 students selected</span>
                            <div class="small text-muted">Assign an eligible Full-Time advisor in common department</div>
                        </div>
                        <div class="col-md-5">
                            <select name="faculty_id" id="bulkFacultySelect" class="form-select form-select-sm" required <?= $canWrite ? '' : 'disabled' ?>>
                                <option value="">-- Choose Eligible Full-Time Faculty Member --</option>
                                <?php foreach ($facultyList as $fac): ?>
                                    <?php if ($fac['Is_Full_Time'] && $fac['Available_Spots'] > 0): ?>
                                        <option value="<?= $fac['Faculty_ID'] ?>" 
                                                data-dept-ids="<?= implode(',', $fac['Dept_IDs']) ?>"
                                                data-spots="<?= $fac['Available_Spots'] ?>">
                                            Prof. <?= htmlspecialchars($fac['User_FName'] . ' ' . $fac['User_LName']) ?> 
                                            (<?= htmlspecialchars($fac['Dept_Names']) ?> &bull; <?= $fac['Advisee_Count'] ?>/15 Advisees &bull; <?= $fac['Available_Spots'] ?> spots open)
                                        </option>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3 text-md-end">
                            <button type="submit" class="btn btn-primary btn-sm px-3 fw-semibold" <?= $canWrite ? '' : 'disabled' ?>>
                                Assign Advisor to Selected
                            </button>
                            <button type="button" class="btn btn-outline-secondary btn-sm" onclick="clearSelectedStudents()">
                                Cancel
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Student Filter Controls -->
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-body p-3 bg-white">
                    <div class="row g-2">
                        <div class="col-md-4">
                            <input type="text" id="studentSearchInput" class="form-control form-control-sm" 
                                   placeholder="Search by student name or student ID...">
                        </div>
                        <div class="col-md-4">
                            <select id="studentDeptFilter" class="form-select form-select-sm">
                                <option value="">All Academic Departments (<?= count($departments) ?>)</option>
                                <?php foreach ($departments as $dId => $dName): ?>
                                    <option value="<?= $dId ?>"><?= htmlspecialchars($dName) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <select id="studentStatusFilter" class="form-select form-select-sm">
                                <option value="">All Advising Statuses</option>
                                <option value="advised">Has Assigned Advisor (<?= $studentsAdvisedCount ?>)</option>
                                <option value="unadvised">Needs Advisor (<?= $studentsUnadvisedCount ?>)</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Students Table -->
            <div class="card shadow-sm border-0">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0" id="studentsTable">
                            <thead class="table-dark">
                                <tr>
                                    <th style="width: 40px;" class="text-center">
                                        <input type="checkbox" id="selectAllStudentsCheckbox" class="form-check-input" <?= $canWrite ? '' : 'disabled' ?>>
                                    </th>
                                    <th>Student Details</th>
                                    <th>Level / Year</th>
                                    <th>Program / Major(s)</th>
                                    <th>Assigned Advisor(s)</th>
                                    <th class="text-end pe-3">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($students)): ?>
                                    <tr>
                                        <td colspan="6" class="text-center py-4 text-muted">No student records found in the database.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($students as $s): ?>
                                        <?php 
                                            $hasAdvisor = !empty($s['Advisors']);
                                            $deptAttr = implode(',', $s['All_Dept_IDs']);
                                            $fullName = trim($s['Student_FName'] . ' ' . $s['Student_LName']);
                                        ?>
                                        <tr class="student-row" 
                                            data-sid="<?= $s['Student_ID'] ?>"
                                            data-name="<?= htmlspecialchars(strtolower($fullName)) ?>"
                                            data-depts="<?= $deptAttr ?>"
                                            data-status="<?= $hasAdvisor ? 'advised' : 'unadvised' ?>">
                                            <td class="text-center">
                                                <input type="checkbox" class="form-check-input student-select-checkbox" 
                                                       value="<?= $s['Student_ID'] ?>" 
                                                       data-depts="<?= $deptAttr ?>"
                                                       data-name="<?= htmlspecialchars($fullName) ?>"
                                                       <?= $canWrite ? '' : 'disabled' ?>>
                                            </td>
                                            <td>
                                                <div class="fw-bold text-dark"><?= htmlspecialchars($fullName) ?></div>
                                                <div class="small text-secondary font-monospace">ID: <?= $s['Student_ID'] ?></div>
                                                <div class="small text-muted"><?= htmlspecialchars($s['Student_Email']) ?></div>
                                            </td>
                                            <td>
                                                <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle">
                                                    <?= htmlspecialchars($s['Student_Type']) ?>
                                                </span>
                                                <div class="small text-muted mt-1">Class of <?= (int)$s['Student_Year'] ?></div>
                                            </td>
                                            <td>
                                                <?php if (!empty($s['Majors'])): ?>
                                                    <?php foreach ($s['Majors'] as $m): ?>
                                                        <div class="badge bg-primary-subtle text-primary border border-primary-subtle mb-1 text-wrap text-start">
                                                            <?= htmlspecialchars($m['Major_Name']) ?>
                                                        </div>
                                                    <?php endforeach; ?>
                                                <?php else: ?>
                                                    <span class="text-muted small">Undeclared &bull; Dept: <?= htmlspecialchars($s['Student_Dept_Name']) ?></span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($hasAdvisor): ?>
                                                    <?php foreach ($s['Advisors'] as $adv): ?>
                                                        <div class="p-2 mb-1 bg-white border rounded shadow-sm">
                                                            <div class="fw-semibold text-dark">
                                                                Prof. <?= htmlspecialchars($adv['Faculty_FName'] . ' ' . $adv['Faculty_LName']) ?>
                                                                <span class="badge bg-info-subtle text-info border border-info-subtle ms-1 font-monospace">
                                                                    ID: <?= $adv['Faculty_ID'] ?>
                                                                </span>
                                                            </div>
                                                            <div class="small text-secondary">
                                                                <?= htmlspecialchars($adv['Faculty_Dept_Name']) ?> &bull; Office: <?= htmlspecialchars($adv['Faculty_Office']) ?>
                                                            </div>
                                                            <div class="small text-muted">
                                                                Assigned: <?= htmlspecialchars($adv['Date_Assigned']) ?>
                                                            </div>
                                                            <?php if ($canWrite): ?>
                                                                <form action="admin_manage_advisors_process.php" method="POST" class="mt-1 m-0 d-inline" onsubmit="return confirm('Are you sure you want to remove Prof. <?= htmlspecialchars($adv['Faculty_FName'] . ' ' . $adv['Faculty_LName']) ?> as advisor for <?= htmlspecialchars($fullName) ?>?');">
                                                                    <input type="hidden" name="action" value="unassign">
                                                                    <input type="hidden" name="student_id" value="<?= $s['Student_ID'] ?>">
                                                                    <input type="hidden" name="faculty_id" value="<?= $adv['Faculty_ID'] ?>">
                                                                    <input type="hidden" name="return_tab" value="students">
                                                                    <button type="submit" class="btn btn-outline-danger btn-xs py-0 px-2" style="font-size: 0.72rem;">
                                                                        &times; Remove
                                                                    </button>
                                                                </form>
                                                            <?php endif; ?>
                                                        </div>
                                                    <?php endforeach; ?>
                                                <?php else: ?>
                                                    <span class="badge bg-warning-subtle text-warning border border-warning-subtle px-2 py-1">
                                                        No Advisor Assigned
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-end pe-3">
                                                <?php if ($canWrite): ?>
                                                    <button type="button" class="btn btn-primary btn-sm" 
                                                            onclick="openSingleAssignModal(<?= $s['Student_ID'] ?>, '<?= htmlspecialchars(addslashes($fullName)) ?>', '<?= $deptAttr ?>')">
                                                        Assign Advisor &rarr;
                                                    </button>
                                                <?php else: ?>
                                                    <span class="badge bg-light text-muted border">Read Only</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        <?php else: ?>
            <!-- =============================================================== -->
            <!-- TAB 2: FACULTY ADVISING ROSTER & CAPACITY                       -->
            <!-- =============================================================== -->

            <!-- Faculty Filter Controls -->
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-body p-3 bg-white">
                    <div class="row g-2">
                        <div class="col-md-4">
                            <input type="text" id="facultySearchInput" class="form-control form-control-sm" 
                                   placeholder="Search by faculty name or faculty ID...">
                        </div>
                        <div class="col-md-4">
                            <select id="facultyDeptFilter" class="form-select form-select-sm">
                                <option value="">All Academic Departments (<?= count($departments) ?>)</option>
                                <?php foreach ($departments as $dId => $dName): ?>
                                    <option value="<?= $dId ?>"><?= htmlspecialchars($dName) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <select id="facultyStatusFilter" class="form-select form-select-sm">
                                <option value="">All Advising Statuses</option>
                                <option value="available">Available Spots (Capacity &lt; 15)</option>
                                <option value="full">At Maximum Capacity (15/15)</option>
                                <option value="ineligible">Part-Time (Ineligible per SRS)</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Faculty Roster Table -->
            <div class="card shadow-sm border-0">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0" id="facultyTable">
                            <thead class="table-dark">
                                <tr>
                                    <th>Faculty Member</th>
                                    <th>Academic Department(s)</th>
                                    <th>Faculty Type &amp; Rank</th>
                                    <th style="min-width: 200px;">Advising Load / Capacity</th>
                                    <th>Status</th>
                                    <th class="text-end pe-3">Advisees</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($facultyList)): ?>
                                    <tr>
                                        <td colspan="6" class="text-center py-4 text-muted">No faculty records found in the database.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($facultyList as $f): ?>
                                        <?php 
                                            $isFT = $f['Is_Full_Time'];
                                            $count = $f['Advisee_Count'];
                                            $spots = $f['Available_Spots'];
                                            $fullName = trim($f['User_FName'] . ' ' . $f['User_LName']);
                                            $deptAttr = implode(',', $f['Dept_IDs']);
                                            
                                            $statusKey = 'available';
                                            if (!$isFT) {
                                                $statusKey = 'ineligible';
                                            } elseif ($count >= 15) {
                                                $statusKey = 'full';
                                            }
                                        ?>
                                        <tr class="faculty-row"
                                            data-fid="<?= $f['Faculty_ID'] ?>"
                                            data-name="<?= htmlspecialchars(strtolower($fullName)) ?>"
                                            data-depts="<?= $deptAttr ?>"
                                            data-status="<?= $statusKey ?>">
                                            <td>
                                                <div class="fw-bold text-dark">Prof. <?= htmlspecialchars($fullName) ?></div>
                                                <div class="small text-secondary font-monospace">ID: <?= $f['Faculty_ID'] ?> &bull; Office: <?= htmlspecialchars($f['Office_ID']) ?></div>
                                                <div class="small text-muted"><?= htmlspecialchars($f['User_Email']) ?></div>
                                            </td>
                                            <td>
                                                <div class="fw-semibold text-dark"><?= htmlspecialchars($f['Dept_Names']) ?></div>
                                                <div class="small text-muted">Specialty: <?= htmlspecialchars($f['Specialty']) ?></div>
                                            </td>
                                            <td>
                                                <?php if ($isFT): ?>
                                                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle">
                                                        Full-Time
                                                    </span>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle">
                                                        Part-Time
                                                    </span>
                                                <?php endif; ?>
                                                <div class="small text-muted mt-1"><?= htmlspecialchars($f['Rank']) ?></div>
                                            </td>
                                            <td>
                                                <?php if ($isFT): ?>
                                                    <div class="d-flex justify-content-between align-items-center small mb-1">
                                                        <span class="fw-semibold font-monospace"><?= $count ?> / 15 Advisees</span>
                                                        <span class="text-secondary"><?= $spots ?> spot(s) open</span>
                                                    </div>
                                                    <div class="progress capacity-bar bg-light border">
                                                        <?php 
                                                            $percent = min(100, round(($count / 15) * 100));
                                                            $barColor = $count >= 15 ? 'bg-danger' : ($count >= 12 ? 'bg-warning' : 'bg-success');
                                                        ?>
                                                        <div class="progress-bar <?= $barColor ?>" role="progressbar" style="width: <?= $percent ?>%;" aria-valuenow="<?= $count ?>" aria-valuemin="0" aria-valuemax="15"></div>
                                                    </div>
                                                <?php else: ?>
                                                    <span class="text-muted small">0 / 0 (Advising not permitted)</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if (!$isFT): ?>
                                                    <span class="badge bg-light text-secondary border">
                                                        Ineligible (Part-Time)
                                                    </span>
                                                <?php elseif ($count >= 15): ?>
                                                    <span class="badge bg-danger-subtle text-danger border border-danger-subtle">
                                                        Capacity Full (15/15)
                                                    </span>
                                                <?php else: ?>
                                                    <span class="badge bg-success-subtle text-success border border-success-subtle">
                                                        Available (<?= $spots ?> open)
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-end pe-3">
                                                <button type="button" class="btn btn-outline-secondary btn-sm" 
                                                        data-bs-toggle="collapse" data-bs-target="#collapseAdvisees_<?= $f['Faculty_ID'] ?>" 
                                                        aria-expanded="false" aria-controls="collapseAdvisees_<?= $f['Faculty_ID'] ?>">
                                                    View Advisees (<?= count($f['Advisees']) ?>)
                                                </button>
                                            </td>
                                        </tr>
                                        <!-- Collapsible Advisees Row -->
                                        <tr class="collapse-row">
                                            <td colspan="6" class="p-0 border-0">
                                                <div class="collapse" id="collapseAdvisees_<?= $f['Faculty_ID'] ?>">
                                                    <div class="p-3 bg-light border-bottom">
                                                        <h6 class="fw-bold text-dark mb-2">
                                                            Students Advised by Prof. <?= htmlspecialchars($fullName) ?> (<?= count($f['Advisees']) ?> total):
                                                        </h6>
                                                        <?php if (empty($f['Advisees'])): ?>
                                                            <div class="text-muted small py-2">This faculty member currently has no assigned student advisees.</div>
                                                        <?php else: ?>
                                                            <div class="table-responsive bg-white rounded border">
                                                                <table class="table table-sm table-striped mb-0">
                                                                    <thead class="table-light">
                                                                        <tr>
                                                                            <th>Student ID</th>
                                                                            <th>Student Name</th>
                                                                            <th>Email</th>
                                                                            <th>Level / Year</th>
                                                                            <th>Major Program</th>
                                                                            <th>Date Assigned</th>
                                                                            <?php if ($canWrite): ?>
                                                                                <th class="text-end">Action</th>
                                                                            <?php endif; ?>
                                                                        </tr>
                                                                    </thead>
                                                                    <tbody>
                                                                        <?php foreach ($f['Advisees'] as $stuAdv): ?>
                                                                            <tr>
                                                                                <td class="font-monospace"><?= $stuAdv['Student_ID'] ?></td>
                                                                                <td class="fw-semibold"><?= htmlspecialchars($stuAdv['Student_FName'] . ' ' . $stuAdv['Student_LName']) ?></td>
                                                                                <td><?= htmlspecialchars($stuAdv['Student_Email']) ?></td>
                                                                                <td><?= htmlspecialchars($stuAdv['Student_Type']) ?> (<?= (int)$stuAdv['Student_Year'] ?>)</td>
                                                                                <td><?= htmlspecialchars($stuAdv['Majors_List'] ?? $stuAdv['Student_Home_Dept']) ?></td>
                                                                                <td class="small text-muted"><?= htmlspecialchars($stuAdv['Date_Assigned']) ?></td>
                                                                                <?php if ($canWrite): ?>
                                                                                    <td class="text-end">
                                                                                        <form action="admin_manage_advisors_process.php" method="POST" class="m-0 d-inline" onsubmit="return confirm('Remove student <?= htmlspecialchars($stuAdv['Student_FName'] . ' ' . $stuAdv['Student_LName']) ?> from this advisor?');">
                                                                                            <input type="hidden" name="action" value="unassign">
                                                                                            <input type="hidden" name="faculty_id" value="<?= $f['Faculty_ID'] ?>">
                                                                                            <input type="hidden" name="student_id" value="<?= $stuAdv['Student_ID'] ?>">
                                                                                            <input type="hidden" name="return_tab" value="faculty">
                                                                                            <button type="submit" class="btn btn-outline-danger btn-xs py-0 px-2" style="font-size: 0.72rem;">
                                                                                                &times; Unassign
                                                                                            </button>
                                                                                        </form>
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
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        <?php endif; ?>

    </div>

    <!-- ======================================================================= -->
    <!-- MODAL: ASSIGN ADVISOR TO SINGLE STUDENT                                 -->
    <!-- ======================================================================= -->
    <div class="modal fade" id="singleAssignModal" tabindex="-1" aria-labelledby="singleAssignModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form action="admin_manage_advisors_process.php" method="POST">
                    <input type="hidden" name="action" value="assign_single">
                    <input type="hidden" name="student_id" id="modalStudentIdInput" value="">
                    <input type="hidden" name="return_tab" value="students">

                    <div class="modal-header bg-dark text-white">
                        <h5 class="modal-title fw-bold" id="singleAssignModalLabel">Assign Faculty Advisor</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body p-4">
                        <div class="alert alert-light border mb-3">
                            <div class="small text-secondary text-uppercase fw-semibold">Target Student</div>
                            <div class="h5 fw-bold text-dark mb-0" id="modalStudentName">Student Name</div>
                            <div class="small text-muted font-monospace" id="modalStudentIdText">ID: 000000</div>
                        </div>

                        <div class="mb-3">
                            <label for="modalFacultySelect" class="form-label fw-semibold">
                                Select Eligible Full-Time Faculty Advisor:
                            </label>
                            <select name="faculty_id" id="modalFacultySelect" class="form-select" required>
                                <option value="">-- Choose Faculty Advisor in Matching Department --</option>
                                <?php foreach ($facultyList as $fac): ?>
                                    <?php if ($fac['Is_Full_Time'] && $fac['Available_Spots'] > 0): ?>
                                        <option value="<?= $fac['Faculty_ID'] ?>" 
                                                data-dept-ids="<?= implode(',', $fac['Dept_IDs']) ?>"
                                                data-name="Prof. <?= htmlspecialchars($fac['User_FName'] . ' ' . $fac['User_LName']) ?>">
                                            Prof. <?= htmlspecialchars($fac['User_FName'] . ' ' . $fac['User_LName']) ?> 
                                            (<?= htmlspecialchars($fac['Dept_Names']) ?> &bull; <?= $fac['Advisee_Count'] ?>/15 Advisees &bull; <?= $fac['Available_Spots'] ?> spots open)
                                        </option>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </select>
                            <div class="form-text small">
                                Per university SRS rules, only Full-Time faculty in the student's department with available advising capacity (&lt; 15 advisees) are eligible.
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary fw-semibold" <?= $canWrite ? '' : 'disabled' ?>>
                            Confirm Advisor Assignment &rarr;
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Bootstrap 5.3 JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

    <!-- Client-side Filtering & Multi-select Interactions -->
    <script>
        // Single Student Assignment Modal
        function openSingleAssignModal(studentId, studentName, deptIdsStr) {
            document.getElementById('modalStudentIdInput').value = studentId;
            document.getElementById('modalStudentName').textContent = studentName;
            document.getElementById('modalStudentIdText').textContent = 'Student ID: ' + studentId;

            const studentDeptIds = deptIdsStr ? deptIdsStr.split(',').map(Number) : [];
            const selectElem = document.getElementById('modalFacultySelect');
            const options = selectElem.querySelectorAll('option');

            let visibleMatches = 0;
            options.forEach((opt, idx) => {
                if (idx === 0) return; // Keep placeholder
                const optDeptIds = opt.getAttribute('data-dept-ids') ? opt.getAttribute('data-dept-ids').split(',').map(Number) : [];
                const hasIntersection = studentDeptIds.some(id => optDeptIds.includes(id));
                if (studentDeptIds.length === 0 || hasIntersection) {
                    opt.style.display = '';
                    opt.disabled = false;
                    visibleMatches++;
                } else {
                    opt.style.display = 'none';
                    opt.disabled = true;
                }
            });

            selectElem.value = '';
            const modal = new bootstrap.Modal(document.getElementById('singleAssignModal'));
            modal.show();
        }

        // Student Table Search & Filters
        const studentSearch = document.getElementById('studentSearchInput');
        const studentDeptFilter = document.getElementById('studentDeptFilter');
        const studentStatusFilter = document.getElementById('studentStatusFilter');

        function filterStudentsTable() {
            if (!studentSearch) return;
            const query = studentSearch.value.trim().toLowerCase();
            const deptVal = studentDeptFilter.value;
            const statusVal = studentStatusFilter.value;

            document.querySelectorAll('.student-row').forEach(row => {
                const name = row.getAttribute('data-name');
                const sid = row.getAttribute('data-sid');
                const depts = (row.getAttribute('data-depts') || '').split(',');
                const status = row.getAttribute('data-status');

                let matchQuery = !query || name.includes(query) || sid.includes(query);
                let matchDept = !deptVal || depts.includes(deptVal);
                let matchStatus = !statusVal || status === statusVal;

                if (matchQuery && matchDept && matchStatus) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        }

        if (studentSearch) {
            studentSearch.addEventListener('input', filterStudentsTable);
            studentDeptFilter.addEventListener('change', filterStudentsTable);
            studentStatusFilter.addEventListener('change', filterStudentsTable);
        }

        // Faculty Table Search & Filters
        const facultySearch = document.getElementById('facultySearchInput');
        const facultyDeptFilter = document.getElementById('facultyDeptFilter');
        const facultyStatusFilter = document.getElementById('facultyStatusFilter');

        function filterFacultyTable() {
            if (!facultySearch) return;
            const query = facultySearch.value.trim().toLowerCase();
            const deptVal = facultyDeptFilter.value;
            const statusVal = facultyStatusFilter.value;

            document.querySelectorAll('.faculty-row').forEach(row => {
                const fid = row.getAttribute('data-fid');
                const name = row.getAttribute('data-name');
                const depts = (row.getAttribute('data-depts') || '').split(',');
                const status = row.getAttribute('data-status');

                let matchQuery = !query || name.includes(query) || fid.includes(query);
                let matchDept = !deptVal || depts.includes(deptVal);
                let matchStatus = !statusVal || status === statusVal;

                if (matchQuery && matchDept && matchStatus) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                    // also collapse expanded advisee sub-rows if hidden
                    const nextRow = row.nextElementSibling;
                    if (nextRow && nextRow.classList.contains('collapse-row')) {
                        const collapseEl = nextRow.querySelector('.collapse');
                        if (collapseEl && collapseEl.classList.contains('show')) {
                            bootstrap.Collapse.getInstance(collapseEl)?.hide();
                        }
                    }
                }
            });
        }

        if (facultySearch) {
            facultySearch.addEventListener('input', filterFacultyTable);
            facultyDeptFilter.addEventListener('change', filterFacultyTable);
            facultyStatusFilter.addEventListener('change', filterFacultyTable);
        }

        // Multi-select Checkbox Handling for Bulk Advisor Assignment
        const selectAllCb = document.getElementById('selectAllStudentsCheckbox');
        const studentCbs = document.querySelectorAll('.student-select-checkbox');
        const bulkActionBar = document.getElementById('bulkActionBar');
        const bulkCountText = document.getElementById('selectedStudentCountText');
        const bulkHiddenInputs = document.getElementById('bulkStudentHiddenInputs');
        const bulkFacultySelect = document.getElementById('bulkFacultySelect');

        function updateBulkSelection() {
            if (!bulkActionBar) return;
            const checkedCbs = Array.from(studentCbs).filter(cb => cb.checked && cb.closest('tr').style.display !== 'none');
            const count = checkedCbs.length;

            if (count > 0) {
                bulkActionBar.classList.remove('d-none');
                bulkCountText.textContent = count + ' student' + (count > 1 ? 's' : '') + ' selected';

                // Repopulate hidden inputs
                bulkHiddenInputs.innerHTML = '';
                let allSelectedDepts = [];

                checkedCbs.forEach(cb => {
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = 'student_ids[]';
                    input.value = cb.value;
                    bulkHiddenInputs.appendChild(input);

                    const depts = (cb.getAttribute('data-depts') || '').split(',').map(Number);
                    allSelectedDepts.push(...depts);
                });

                allSelectedDepts = Array.from(new Set(allSelectedDepts));

                // Filter bulk faculty select options by capacity and department
                if (bulkFacultySelect) {
                    const options = bulkFacultySelect.querySelectorAll('option');
                    options.forEach((opt, idx) => {
                        if (idx === 0) return;
                        const spots = parseInt(opt.getAttribute('data-spots') || '0', 10);
                        const optDeptIds = (opt.getAttribute('data-dept-ids') || '').split(',').map(Number);
                        const hasCommonDept = allSelectedDepts.some(id => optDeptIds.includes(id));

                        if (spots >= count && hasCommonDept) {
                            opt.style.display = '';
                            opt.disabled = false;
                        } else {
                            opt.style.display = 'none';
                            opt.disabled = true;
                        }
                    });
                }
            } else {
                bulkActionBar.classList.add('d-none');
                bulkHiddenInputs.innerHTML = '';
            }
        }

        if (selectAllCb) {
            selectAllCb.addEventListener('change', () => {
                const isChecked = selectAllCb.checked;
                document.querySelectorAll('.student-row').forEach(row => {
                    if (row.style.display !== 'none') {
                        const cb = row.querySelector('.student-select-checkbox');
                        if (cb) cb.checked = isChecked;
                    }
                });
                updateBulkSelection();
            });
        }

        studentCbs.forEach(cb => {
            cb.addEventListener('change', updateBulkSelection);
        });

        function clearSelectedStudents() {
            if (selectAllCb) selectAllCb.checked = false;
            studentCbs.forEach(cb => cb.checked = false);
            updateBulkSelection();
        }
    </script>
</body>
</html>
