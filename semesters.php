<?php
/**
 * Lucid University Course Registration System
 * Admin Portal - View Semesters & Academic Calendar Deadlines
 *
 * Implements View/Manage Semester use cases, listing all academic terms,
 * start/end dates, section counts, and key deadlines from INFORMATION_RELATION.
 */

require_once 'admin_guard.php';
require_once '../db_connect.php';
require_once '../profile_hover_card.php';

$admin_id = (int)$userID;

$successMessage = $_SESSION["success_message"] ?? "";
$errorMessage   = $_SESSION["error_message"] ?? "";
unset($_SESSION["success_message"], $_SESSION["error_message"]);

// Fetch all semesters with their information relations and section counts
$sql = "
    SELECT 
        s.Semester_ID,
        s.Semester_Name,
        s.Semester_Year,
        s.Start_Date,
        s.End_Date,
        ir.Last_Day_Of_Registration,
        ir.Last_Day_Of_Dropping_Course,
        ir.Date_Of_Midterm_Grade_Recording,
        ir.Date_Of_Final_Grades_Recording,
        COUNT(cs.CRN) AS Section_Count
    FROM SEMESTER s
    LEFT JOIN INFORMATION_RELATION ir ON s.Semester_ID = ir.Semester_ID
    LEFT JOIN COURSE_SECTIONS cs ON s.Semester_ID = cs.Semester_ID
    GROUP BY 
        s.Semester_ID, s.Semester_Name, s.Semester_Year, s.Start_Date, s.End_Date,
        ir.Last_Day_Of_Registration, ir.Last_Day_Of_Dropping_Course,
        ir.Date_Of_Midterm_Grade_Recording, ir.Date_Of_Final_Grades_Recording
    ORDER BY s.Start_Date DESC
";

$result = $conn->query($sql);
$semesters = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $semesters[] = $row;
    }
}

$today = date('Y-m-d');
$totalSemesters = count($semesters);
$activeCount = 0;
$upcomingCount = 0;

foreach ($semesters as $sem) {
    if ($today >= $sem['Start_Date'] && $today <= $sem['End_Date']) {
        $activeCount++;
    } elseif ($today < $sem['Start_Date']) {
        $upcomingCount++;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Academic Semesters & Calendar | Lucid University Admin</title>
    <!-- Minimal Clean Bootstrap 5.3 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        <?= getProfileHoverCardStyles() ?>
        .table-hover tbody tr:hover {
            background-color: #f8f9fa;
        }
        .status-badge {
            min-width: 95px;
            text-align: center;
        }
    </style>
</head>
<body class="bg-light">

    <!-- Unified Admin Top Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark mb-4 shadow-sm">
        <div class="container">
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
                        <a class="nav-link active" aria-current="page" href="semesters.php">Semesters</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="majors_minors.php">Majors & Minors</a>
                    </li>
                </ul>
                <div class="d-flex align-items-center gap-3">
                    <?= getUserNavbarProfileHtml($conn, $admin_id, 'Admin') ?>
                    <form action="../logout.php" method="POST" class="m-0">
                        <button type="submit" class="btn btn-outline-light btn-sm">Log Out</button>
                    </form>
                </div>
            </div>
        </div>
    </nav>

    <div class="container-fluid px-lg-5 mb-5">

        <!-- Flash Alerts -->
        <?php if (!empty($successMessage)): ?>
            <div class="alert alert-success alert-dismissible fade show shadow-sm" role="alert">
                <strong>Success!</strong> <?= htmlspecialchars($successMessage) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <?php if (!empty($errorMessage)): ?>
            <div class="alert alert-danger alert-dismissible fade show shadow-sm" role="alert">
                <strong>Error:</strong> <?= htmlspecialchars($errorMessage) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <!-- Page Header -->
        <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
            <div>
                <h2 class="fw-bold mb-1">Academic Semesters & Calendar</h2>
                <div class="text-secondary">
                    Review university term schedules, registration cutoffs, add/drop periods, and grade recording windows.
                </div>
            </div>
            <div class="d-flex gap-2">
                <a href="dashboard.php" class="btn btn-outline-secondary btn-sm">&larr; Dashboard</a>
                <?php if ((int)($_SESSION['Security_Level'] ?? 1) >= 2): ?>
                    <a href="edit_semester.php" class="btn btn-primary btn-sm">+ Add New Semester</a>
                <?php endif; ?>
            </div>
        </div>

        <!-- Metrics Overview Cards -->
        <div class="row g-3 mb-4">
            <div class="col-sm-6 col-lg-3">
                <div class="card border-0 shadow-sm rounded-3">
                    <div class="card-body p-3">
                        <div class="text-secondary small text-uppercase fw-bold">Configured Semesters</div>
                        <div class="fs-4 fw-bold text-dark"><?= $totalSemesters ?> Terms</div>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-lg-3">
                <div class="card border-0 shadow-sm rounded-3">
                    <div class="card-body p-3">
                        <div class="text-secondary small text-uppercase fw-bold">Active / Ongoing</div>
                        <div class="fs-4 fw-bold text-success"><?= $activeCount ?> Term<?= $activeCount === 1 ? '' : 's' ?></div>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-lg-3">
                <div class="card border-0 shadow-sm rounded-3">
                    <div class="card-body p-3">
                        <div class="text-secondary small text-uppercase fw-bold">Upcoming Semesters</div>
                        <div class="fs-4 fw-bold text-primary"><?= $upcomingCount ?> Term<?= $upcomingCount === 1 ? '' : 's' ?></div>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-lg-3">
                <div class="card border-0 shadow-sm rounded-3">
                    <div class="card-body p-3">
                        <div class="text-secondary small text-uppercase fw-bold">Admin Permission</div>
                        <div class="fs-4 fw-bold text-info">Level <?= htmlspecialchars((string)($_SESSION["Security_Level"] ?? '1')) ?></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Semesters Table Card -->
        <div class="card shadow-sm border-0 rounded-3">
            <div class="card-header bg-white py-3 border-0">
                <div class="row g-2 align-items-center">
                    <div class="col-md-6">
                        <h5 class="fw-bold mb-0">Master Academic Terms Directory</h5>
                    </div>
                    <div class="col-md-6">
                        <input type="text" id="semesterSearch" class="form-control form-control-sm" placeholder="Filter terms by name, year, ID, or status...">
                    </div>
                </div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0" id="semestersTable">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-4">Semester ID</th>
                                <th>Term & Year</th>
                                <th>Status</th>
                                <th>Term Dates</th>
                                <th>Registration Cutoff</th>
                                <th>Add/Drop Deadline</th>
                                <th>Midterm Grades</th>
                                <th>Final Grades</th>
                                <th class="text-center">Sections</th>
                                <th class="text-end pe-4">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($semesters)): ?>
                                <tr>
                                    <td colspan="10" class="text-center py-4 text-muted">
                                        No semesters found in the database.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($semesters as $sem): ?>
                                    <?php 
                                        $semID = (int)$sem['Semester_ID'];
                                        $isOngoing = ($today >= $sem['Start_Date'] && $today <= $sem['End_Date']);
                                        $isPast = ($today > $sem['End_Date']);
                                        $isUpcoming = ($today < $sem['Start_Date']);
                                        
                                        if ($isOngoing) {
                                            $statusText = 'Ongoing';
                                            $statusClass = 'bg-success';
                                        } elseif ($isUpcoming) {
                                            $statusText = 'Upcoming';
                                            $statusClass = 'bg-primary';
                                        } else {
                                            $statusText = 'Past';
                                            $statusClass = 'bg-secondary';
                                        }
                                    ?>
                                    <tr>
                                        <td class="ps-4 font-monospace fw-semibold text-dark">
                                            <?= htmlspecialchars((string)$sem['Semester_ID']) ?>
                                        </td>
                                        <td>
                                            <span class="fw-bold text-dark"><?= htmlspecialchars($sem['Semester_Name']) ?> <?= htmlspecialchars((string)$sem['Semester_Year']) ?></span>
                                        </td>
                                        <td>
                                            <span class="badge <?= $statusClass ?> status-badge"><?= $statusText ?></span>
                                        </td>
                                        <td class="small">
                                            <div><strong>Start:</strong> <?= htmlspecialchars($sem['Start_Date']) ?></div>
                                            <div><strong>End:</strong> <?= htmlspecialchars($sem['End_Date']) ?></div>
                                        </td>
                                        <td class="small">
                                            <?= !empty($sem['Last_Day_Of_Registration']) ? htmlspecialchars($sem['Last_Day_Of_Registration']) : '<span class="text-muted">Not Set</span>' ?>
                                        </td>
                                        <td class="small">
                                            <?= !empty($sem['Last_Day_Of_Dropping_Course']) ? htmlspecialchars($sem['Last_Day_Of_Dropping_Course']) : '<span class="text-muted">Not Set</span>' ?>
                                        </td>
                                        <td class="small">
                                            <?= !empty($sem['Date_Of_Midterm_Grade_Recording']) ? htmlspecialchars($sem['Date_Of_Midterm_Grade_Recording']) : '<span class="text-muted">Not Set</span>' ?>
                                        </td>
                                        <td class="small">
                                            <?= !empty($sem['Date_Of_Final_Grades_Recording']) ? htmlspecialchars($sem['Date_Of_Final_Grades_Recording']) : '<span class="text-muted">Not Set</span>' ?>
                                        </td>
                                        <td class="text-center font-monospace fw-semibold">
                                            <span class="badge bg-light text-dark border"><?= (int)$sem['Section_Count'] ?></span>
                                        </td>
                                        <td class="text-end pe-4">
                                            <?php if ((int)($_SESSION['Security_Level'] ?? 1) >= 2): ?>
                                                <?php if ($isPast): ?>
                                                    <a href="edit_semester.php?id=<?= $semID ?>" class="btn btn-outline-secondary btn-sm" title="Inspect past term configuration">
                                                        Inspect (Past)
                                                    </a>
                                                <?php else: ?>
                                                    <a href="edit_semester.php?id=<?= $semID ?>" class="btn btn-outline-primary btn-sm">
                                                        Edit Term & Deadlines
                                                    </a>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <a href="edit_semester.php?id=<?= $semID ?>" class="btn btn-outline-secondary btn-sm">
                                                    View Details
                                                </a>
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

    </div>

    <!-- Bootstrap 5.3 JS Bundle & Filter Script -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.getElementById('semesterSearch').addEventListener('input', function() {
            const query = this.value.toLowerCase().trim();
            const rows = document.querySelectorAll('#semestersTable tbody tr');
            rows.forEach(row => {
                const text = row.innerText.toLowerCase();
                row.style.display = text.includes(query) ? '' : 'none';
            });
        });
    </script>
</body>
</html>
