<?php
/**
 * Lucid University Course Registration System
 * Admin Portal - Majors & Minors Management Hub
 *
 * Implements Admin Majors and Minors Page (Sitemap & SRS).
 * Allows administrators to review, search, configure, and update all academic majors,
 * minors, credit requirements, and degree track course requirements.
 */

require_once 'admin_guard.php';
require_once '../db_connect.php';
require_once __DIR__ . '/../includes/academic_helpers.php';
require_once '../profile_hover_card.php';

$admin_id = (int)$userID;

$successMessage = $_SESSION["success_message"] ?? "";
$errorMessage   = $_SESSION["error_message"] ?? "";
unset($_SESSION["success_message"], $_SESSION["error_message"]);

// 1. Fetch All Majors with Department and Count of Required Courses
$majorsSql = "
    SELECT 
        m.Major_ID,
        m.Major_Name,
        m.No_Credits_Required,
        d.Department_ID,
        d.Department_Name,
        COUNT(mr.Course_ID) AS Required_Course_Count
    FROM MAJORS m
    JOIN DEPARTMENT d ON m.Department_ID = d.Department_ID
    LEFT JOIN MAJOR_REQUIREMENTS mr ON m.Major_ID = mr.Major_ID
    GROUP BY m.Major_ID, m.Major_Name, m.No_Credits_Required, d.Department_ID, d.Department_Name
    ORDER BY d.Department_Name ASC, m.Major_ID ASC
";
$majorsStmt = $conn->prepare($majorsSql);
$majorsStmt->execute();
$majorsRes = $majorsStmt->get_result();
$majors = [];
if ($majorsRes) {
    while ($row = $majorsRes->fetch_assoc()) {
        $majors[] = $row;
    }
}
$majorsStmt->close();

// 2. Fetch All Minors with Department and Count of Required Courses
$minorsSql = "
    SELECT 
        mn.Minor_ID,
        mn.Minor_Name,
        mn.No_Credits_Required,
        d.Department_ID,
        d.Department_Name,
        COUNT(mnr.Course_ID) AS Required_Course_Count
    FROM MINORS mn
    JOIN DEPARTMENT d ON mn.Department_ID = d.Department_ID
    LEFT JOIN MINOR_REQUIREMENTS mnr ON mn.Minor_ID = mnr.Minor_ID
    GROUP BY mn.Minor_ID, mn.Minor_Name, mn.No_Credits_Required, d.Department_ID, d.Department_Name
    ORDER BY d.Department_Name ASC, mn.Minor_ID ASC
";
$minorsStmt = $conn->prepare($minorsSql);
$minorsStmt->execute();
$minorsRes = $minorsStmt->get_result();
$minors = [];
if ($minorsRes) {
    while ($row = $minorsRes->fetch_assoc()) {
        $minors[] = $row;
    }
}
$minorsStmt->close();

$canWrite = ((int)($_SESSION['Security_Level'] ?? 1) >= 2);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Academic Majors & Minors | Lucid University Admin</title>
    <!-- Minimal Clean Bootstrap 5.3 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        <?= getProfileHoverCardStyles() ?>
        .table-hover tbody tr:hover {
            background-color: #f8f9fa;
        }
        .nav-pills .nav-link.active {
            background-color: #0d6efd;
            font-weight: 600;
        }
    </style>
</head>
<body class="bg-light">

    <?= renderAdminNavbar($conn, $admin_id, basename($_SERVER['PHP_SELF'])) ?>

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
                <h2 class="fw-bold mb-1">Academic Majors & Minors Management</h2>
                <div class="text-secondary">
                    Configure university degree programs, minor tracks, credit benchmarks, and required curriculum courses.
                </div>
            </div>
            <div class="d-flex gap-2">
                <a href="dashboard.php" class="btn btn-outline-secondary btn-sm">&larr; Dashboard</a>
                <?php if ($canWrite): ?>
                    <a href="create_major.php" class="btn btn-primary btn-sm">+ Create New Major</a>
                    <a href="create_minor.php" class="btn btn-outline-primary btn-sm">+ Create New Minor</a>
                <?php endif; ?>
            </div>
        </div>

        <!-- Metrics Overview Cards -->
        <div class="row g-3 mb-4">
            <div class="col-sm-6 col-lg-3">
                <div class="card border-0 shadow-sm rounded-3">
                    <div class="card-body p-3">
                        <div class="text-secondary small text-uppercase fw-bold">Degree Majors</div>
                        <div class="fs-4 fw-bold text-primary"><?= count($majors) ?> Programs</div>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-lg-3">
                <div class="card border-0 shadow-sm rounded-3">
                    <div class="card-body p-3">
                        <div class="text-secondary small text-uppercase fw-bold">Academic Minors</div>
                        <div class="fs-4 fw-bold text-success"><?= count($minors) ?> Tracks</div>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-lg-3">
                <div class="card border-0 shadow-sm rounded-3">
                    <div class="card-body p-3">
                        <div class="text-secondary small text-uppercase fw-bold">Academic Departments</div>
                        <div class="fs-4 fw-bold text-dark">12 Departments</div>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-lg-3">
                <div class="card border-0 shadow-sm rounded-3">
                    <div class="card-body p-3">
                        <div class="text-secondary small text-uppercase fw-bold">Security Level</div>
                        <div class="fs-4 fw-bold text-info">Level <?= htmlspecialchars((string)($_SESSION["Security_Level"] ?? '1')) ?></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Nav Tabs & Search Bar -->
        <div class="card shadow-sm border-0 rounded-3 mb-4">
            <div class="card-header bg-white py-3 border-0">
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
                    <ul class="nav nav-pills" id="majorMinorTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active px-4" id="majors-tab" data-bs-toggle="pill" data-bs-target="#majors-pane" type="button" role="tab" aria-controls="majors-pane" aria-selected="true">
                                Degree Majors (<?= count($majors) ?>)
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link px-4" id="minors-tab" data-bs-toggle="pill" data-bs-target="#minors-pane" type="button" role="tab" aria-controls="minors-pane" aria-selected="false">
                                Academic Minors (<?= count($minors) ?>)
                            </button>
                        </li>
                    </ul>
                    <div style="min-width: 280px;">
                        <input type="text" id="directoryFilter" class="form-control form-control-sm" placeholder="Filter majors, minors, departments, or IDs...">
                    </div>
                </div>
            </div>
            <div class="card-body p-0">
                <div class="tab-content" id="majorMinorTabContent">
                    
                    <!-- TAB 1: MAJORS -->
                    <div class="tab-pane fade show active" id="majors-pane" role="tabpanel" aria-labelledby="majors-tab" tabindex="0">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0" id="majorsTable">
                                <thead class="table-light">
                                    <tr>
                                        <th class="ps-4">Major ID</th>
                                        <th>Major Name</th>
                                        <th>Level</th>
                                        <th>Department</th>
                                        <th class="text-center">Credits Required</th>
                                        <th class="text-center">Required Courses</th>
                                        <th class="text-end pe-4">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($majors)): ?>
                                        <tr>
                                            <td colspan="7" class="text-center py-4 text-muted">No majors registered in the system.</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($majors as $m): ?>
                                            <?php 
                                                $name = $m['Major_Name'];
                                                if (stripos($name, 'Ph.D.') !== false || stripos($name, 'Doctor') !== false) {
                                                    $lvlBadge = '<span class="badge bg-dark">Doctoral (Ph.D.)</span>';
                                                } elseif (stripos($name, 'M.S.') !== false || stripos($name, 'M.A.') !== false || stripos($name, 'M.F.A.') !== false || stripos($name, 'Master') !== false) {
                                                    $lvlBadge = '<span class="badge bg-purple text-white" style="background-color: #6f42c1;">Graduate (M.S.)</span>';
                                                } else {
                                                    $lvlBadge = '<span class="badge bg-primary">Undergraduate (B.S./B.A.)</span>';
                                                }
                                            ?>
                                            <tr>
                                                <td class="ps-4 font-monospace fw-semibold text-dark">
                                                    <?= htmlspecialchars((string)$m['Major_ID']) ?>
                                                </td>
                                                <td>
                                                    <span class="fw-bold text-dark"><?= htmlspecialchars($m['Major_Name']) ?></span>
                                                </td>
                                                <td>
                                                    <?= $lvlBadge ?>
                                                </td>
                                                <td>
                                                    <span class="badge bg-light text-dark border"><?= htmlspecialchars($m['Department_Name']) ?></span>
                                                </td>
                                                <td class="text-center font-monospace fw-semibold">
                                                    <?= (int)$m['No_Credits_Required'] ?> cr
                                                </td>
                                                <td class="text-center font-monospace">
                                                    <span class="badge bg-light text-primary border border-primary-subtle">
                                                        <?= (int)$m['Required_Course_Count'] ?> courses
                                                    </span>
                                                </td>
                                                <td class="text-end pe-4">
                                                    <?php if ($canWrite): ?>
                                                        <a href="edit_major.php?id=<?= (int)$m['Major_ID'] ?>" class="btn btn-outline-primary btn-sm">
                                                            Edit & Courses
                                                        </a>
                                                    <?php else: ?>
                                                        <a href="edit_major.php?id=<?= (int)$m['Major_ID'] ?>" class="btn btn-outline-secondary btn-sm">
                                                            View Curriculum
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

                    <!-- TAB 2: MINORS -->
                    <div class="tab-pane fade" id="minors-pane" role="tabpanel" aria-labelledby="minors-tab" tabindex="0">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0" id="minorsTable">
                                <thead class="table-light">
                                    <tr>
                                        <th class="ps-4">Minor ID</th>
                                        <th>Minor Name</th>
                                        <th>Department</th>
                                        <th class="text-center">Credits Required</th>
                                        <th class="text-center">Required Courses</th>
                                        <th class="text-end pe-4">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($minors)): ?>
                                        <tr>
                                            <td colspan="6" class="text-center py-4 text-muted">No minors registered in the system.</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($minors as $mn): ?>
                                            <tr>
                                                <td class="ps-4 font-monospace fw-semibold text-dark">
                                                    <?= htmlspecialchars((string)$mn['Minor_ID']) ?>
                                                </td>
                                                <td>
                                                    <span class="fw-bold text-dark"><?= htmlspecialchars($mn['Minor_Name']) ?></span>
                                                </td>
                                                <td>
                                                    <span class="badge bg-light text-dark border"><?= htmlspecialchars($mn['Department_Name']) ?></span>
                                                </td>
                                                <td class="text-center font-monospace fw-semibold">
                                                    <?= (int)$mn['No_Credits_Required'] ?> cr
                                                </td>
                                                <td class="text-center font-monospace">
                                                    <span class="badge bg-light text-success border border-success-subtle">
                                                        <?= (int)$mn['Required_Course_Count'] ?> courses
                                                    </span>
                                                </td>
                                                <td class="text-end pe-4">
                                                    <?php if ($canWrite): ?>
                                                        <a href="edit_minor.php?id=<?= (int)$mn['Minor_ID'] ?>" class="btn btn-outline-success btn-sm">
                                                            Edit & Courses
                                                        </a>
                                                    <?php else: ?>
                                                        <a href="edit_minor.php?id=<?= (int)$mn['Minor_ID'] ?>" class="btn btn-outline-secondary btn-sm">
                                                            View Curriculum
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
        </div>

    </div>

    <!-- Bootstrap 5.3 JS Bundle & Live Filter Script -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.getElementById('directoryFilter').addEventListener('input', function() {
            const query = this.value.toLowerCase().trim();
            ['majorsTable', 'minorsTable'].forEach(tableId => {
                const rows = document.querySelectorAll('#' + tableId + ' tbody tr');
                rows.forEach(row => {
                    const text = row.innerText.toLowerCase();
                    row.style.display = text.includes(query) ? '' : 'none';
                });
            });
        });
    </script>
</body>
</html>
