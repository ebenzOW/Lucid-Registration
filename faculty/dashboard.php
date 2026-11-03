<?php
/**
 * Lucid University Course Registration System
 * Faculty Main Portal Dashboard
 *
 * Full-featured Bootstrap 5.3 dashboard providing teaching schedule overviews,
 * live section roster drill-downs, advisee tracking, and profile hover card.
 */

require_once 'faculty_guard.php';
$faculty_id = (int)$userID;

require_once '../db_connect.php';
require_once __DIR__ . '/../includes/academic_helpers.php';
require_once '../profile_hover_card.php';

// 1. Fetch Faculty Profile and Department Details
$stmt = $conn->prepare("
    SELECT f.Faculty_ID, f.Faculty_Type, f.Rank, f.Specialty, f.Office_ID,
           u.User_FName, u.User_MName, u.User_LName, u.User_PhoneNo,
           l.User_Email,
           GROUP_CONCAT(DISTINCT d.Department_Name ORDER BY d.Department_Name SEPARATOR ', ') AS Department_Names
    FROM FACULTY f
    JOIN `USER` u ON f.Faculty_ID = u.User_ID
    LEFT JOIN LOGIN l ON u.User_ID = l.User_ID
    LEFT JOIN DEPARTMENT_ROSTER dr ON f.Faculty_ID = dr.Faculty_ID
    LEFT JOIN DEPARTMENT d ON dr.Department_ID = d.Department_ID
    WHERE f.Faculty_ID = ?
    GROUP BY f.Faculty_ID, f.Faculty_Type, f.Rank, f.Specialty, f.Office_ID,
             u.User_FName, u.User_MName, u.User_LName, u.User_PhoneNo, l.User_Email
");
$stmt->bind_param("i", $faculty_id);
$stmt->execute();
$faculty = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$faculty) {
    die("Faculty record not found.");
}

$faculty_full_name = trim($faculty['User_FName'] . ' ' . (!empty($faculty['User_MName']) ? $faculty['User_MName'] . ' ' : '') . $faculty['User_LName']);
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

// 4. Section Roster Drill-Down Check
$roster_crn = isset($_GET['roster_crn']) ? filter_var($_GET['roster_crn'], FILTER_VALIDATE_INT) : null;
$rosterStudents = null;
$rosterSectionInfo = null;

if ($roster_crn) {
    // Why: Enforces that faculty can only view rosters for sections assigned to them.
    $rosterStudents = getSectionRoster($conn, $roster_crn, $faculty_id);
    if ($rosterStudents !== null) {
        $secInfoStmt = $conn->prepare("
            SELECT cs.CRN, cs.Course_ID, cs.Section_Number, cs.Semester_ID,
                   c.Course_Name, s.Semester_Name, s.Semester_Year,
                   b.Building_Name, r.Room_NO
            FROM COURSE_SECTIONS cs
            JOIN COURSES c ON cs.Course_ID = c.Course_ID
            JOIN SEMESTER s ON cs.Semester_ID = s.Semester_ID
            JOIN ROOM r ON cs.Room_ID = r.Room_ID
            JOIN BUILDING b ON r.Building_ID = b.Building_ID
            WHERE cs.CRN = ?
        ");
        $secInfoStmt->bind_param("i", $roster_crn);
        $secInfoStmt->execute();
        $rosterSectionInfo = $secInfoStmt->get_result()->fetch_assoc();
        $secInfoStmt->close();
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
        <?= getProfileHoverCardStyles() ?>
    </style>
</head>
<body class="bg-light">

    <!-- Unified Faculty Top Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark mb-4 shadow-sm">
        <div class="container">
            <a class="navbar-brand fw-bold" href="dashboard.php">
                Lucid University <span class="badge bg-warning text-dark ms-1">Faculty</span>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#facultyNav" aria-controls="facultyNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="facultyNav">
                <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                    <li class="nav-item">
                        <a class="nav-link active" aria-current="page" href="dashboard.php">Dashboard</a>
                    </li>
                </ul>
                <div class="d-flex align-items-center gap-3">
                    <?= getUserNavbarProfileHtml($conn, $faculty_id, 'Faculty') ?>
                    <form action="../logout.php" method="POST" class="m-0">
                        <button type="submit" class="btn btn-outline-light btn-sm">Log Out</button>
                    </form>
                </div>
            </div>
        </div>
    </nav>

    <div class="container mb-5">

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

        <!-- Section Roster Drill-Down View (Displayed when roster_crn is set) -->
        <?php if ($roster_crn !== null): ?>
            <div id="roster" class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-white py-3 d-flex flex-wrap justify-content-between align-items-center gap-2">
                    <div>
                        <h5 class="mb-0 fw-bold">
                            <?php if ($rosterSectionInfo): ?>
                                Section Roster: <?= htmlspecialchars($rosterSectionInfo['Course_ID'], ENT_QUOTES, 'UTF-8') ?> - Section <?= (int)$rosterSectionInfo['Section_Number'] ?> (<?= htmlspecialchars($rosterSectionInfo['Course_Name'], ENT_QUOTES, 'UTF-8') ?>)
                            <?php else: ?>
                                Section Roster (CRN: <?= (int)$roster_crn ?>)
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
                    <a href="dashboard.php" class="btn btn-outline-secondary btn-sm">Close Roster &times;</a>
                </div>
                <div class="card-body p-0">
                    <?php if ($rosterStudents === null): ?>
                        <div class="alert alert-danger m-3 mb-3">
                            You are not authorized to view the enrollment roster for CRN <?= (int)$roster_crn ?>.
                        </div>
                    <?php elseif (empty($rosterStudents)): ?>
                        <div class="p-4 text-center text-muted">
                            No students are currently registered in this section.
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover table-striped mb-0 align-middle">
                                <thead class="table-light">
                                    <tr>
                                        <th>Student ID</th>
                                        <th>Full Name</th>
                                        <th>Email</th>
                                        <th>Class Standing</th>
                                        <th>Major(s)</th>
                                        <th class="text-center">Current Grade</th>
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
                                                <?php if (!empty($st['Grade'])): ?>
                                                    <span class="badge bg-secondary fs-6"><?= htmlspecialchars($st['Grade'], ENT_QUOTES, 'UTF-8') ?></span>
                                                <?php else: ?>
                                                    <span class="text-muted fst-italic">Ungraded</span>
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
        <?php endif; ?>

        <!-- Fall 2026 Teaching Schedule Table -->
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
                                    <th class="text-end">Roster</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($fallSections as $sec): ?>
                                    <tr>
                                        <td><strong><?= (int)$sec['CRN'] ?></strong></td>
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
                                            <a href="?roster_crn=<?= (int)$sec['CRN'] ?>#roster" class="btn btn-outline-primary btn-sm">
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

        <!-- Spring 2027 Teaching Schedule Table -->
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
                                <?php foreach ($springSections as $sec): ?>
                                    <tr>
                                        <td><strong><?= (int)$sec['CRN'] ?></strong></td>
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
                                            <a href="?roster_crn=<?= (int)$sec['CRN'] ?>#roster" class="btn btn-outline-primary btn-sm">
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
