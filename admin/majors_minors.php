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
require_once __DIR__ . '/../includes/ui_helpers.php';

$admin_id = (int)$userID;

$successMessage = $_SESSION["success_message"] ?? "";
$errorMessage   = $_SESSION["error_message"] ?? "";
unset($_SESSION["success_message"], $_SESSION["error_message"]);

// 1. Fetch All Departments
$deptStmt = $conn->prepare("SELECT Department_ID, Department_Name FROM DEPARTMENT ORDER BY Department_Name ASC");
$deptStmt->execute();
$departments = $deptStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$deptStmt->close();

// 2. Fetch All Majors with Department, Program_Level and Count of Required Courses
$majorsSql = "
    SELECT 
        m.Major_ID,
        m.Major_Name,
        m.Program_Level,
        m.No_Credits_Required,
        d.Department_ID,
        d.Department_Name,
        COUNT(mr.Course_ID) AS Required_Course_Count
    FROM MAJORS m
    JOIN DEPARTMENT d ON m.Department_ID = d.Department_ID
    LEFT JOIN MAJOR_REQUIREMENTS mr ON m.Major_ID = mr.Major_ID
    GROUP BY m.Major_ID, m.Major_Name, m.Program_Level, m.No_Credits_Required, d.Department_ID, d.Department_Name
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

// 3. Fetch All Minors with Department, Program_Level and Count of Required Courses
$minorsSql = "
    SELECT 
        mn.Minor_ID,
        mn.Minor_Name,
        mn.Program_Level,
        mn.No_Credits_Required,
        d.Department_ID,
        d.Department_Name,
        COUNT(mnr.Course_ID) AS Required_Course_Count
    FROM MINORS mn
    JOIN DEPARTMENT d ON mn.Department_ID = d.Department_ID
    LEFT JOIN MINOR_REQUIREMENTS mnr ON mn.Minor_ID = mnr.Minor_ID
    GROUP BY mn.Minor_ID, mn.Minor_Name, mn.Program_Level, mn.No_Credits_Required, d.Department_ID, d.Department_Name
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

// Suggested IDs for modal generation
$maxMajStmt = $conn->query("SELECT MAX(Major_ID) AS MaxID FROM MAJORS");
$maxMajRow = $maxMajStmt ? $maxMajStmt->fetch_assoc() : null;
$suggestedMajorID = ($maxMajRow && $maxMajRow['MaxID']) ? ((int)$maxMajRow['MaxID'] + 1) : 101;

$maxMinStmt = $conn->query("SELECT MAX(Minor_ID) AS MaxID FROM MINORS");
$maxMinRow = $maxMinStmt ? $maxMinStmt->fetch_assoc() : null;
$suggestedMinorID = ($maxMinRow && $maxMinRow['MaxID']) ? ((int)$maxMinRow['MaxID'] + 1) : 201;

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
                <strong>Success!</strong> <?= htmlspecialchars($successMessage, ENT_QUOTES, 'UTF-8') ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <?php if (!empty($errorMessage)): ?>
            <div class="alert alert-danger alert-dismissible fade show shadow-sm" role="alert">
                <strong>Error:</strong> <?= htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8') ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <!-- Page Header -->
        <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
            <div>
                <h2 class="fw-bold mb-1">Academic Majors &amp; Minors Management</h2>
                <div class="text-secondary">
                    Configure university degree programs, minor tracks, credit benchmarks, and required curriculum courses.
                </div>
            </div>
            <div class="d-flex gap-2">
                <a href="dashboard.php" class="btn btn-outline-secondary btn-sm">&larr; Dashboard</a>
                <?php if ($canWrite): ?>
                    <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#createMajorModal">
                        + Create New Major
                    </button>
                    <button type="button" class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#createMinorModal">
                        + Create New Minor
                    </button>
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
                        <div class="fs-4 fw-bold text-dark"><?= count($departments) ?> Departments</div>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-lg-3">
                <div class="card border-0 shadow-sm rounded-3">
                    <div class="card-body p-3">
                        <div class="text-secondary small text-uppercase fw-bold">Security Level</div>
                        <div class="fs-4 fw-bold text-info">Level <?= htmlspecialchars((string)($_SESSION["Security_Level"] ?? '1'), ENT_QUOTES, 'UTF-8') ?></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Multi-Attribute Filter Bar -->
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-body bg-white p-3">
                <div class="row g-2 align-items-end">
                    <div class="col-md-2">
                        <label for="filterId" class="form-label small fw-bold">ID</label>
                        <input type="text" id="filterId" class="form-control form-control-sm" placeholder="e.g. 101">
                    </div>
                    <div class="col-md-3">
                        <label for="filterName" class="form-label small fw-bold">Program Name</label>
                        <input type="text" id="filterName" class="form-control form-control-sm" placeholder="Search name...">
                    </div>
                    <div class="col-md-2">
                        <label for="filterLevel" class="form-label small fw-bold">Level</label>
                        <select id="filterLevel" class="form-select form-select-sm">
                            <option value="all">All Levels</option>
                            <option value="Undergraduate">Undergraduate</option>
                            <option value="Graduate">Graduate</option>
                            <option value="Ph.D.">Ph.D.</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label for="filterDept" class="form-label small fw-bold">Department</label>
                        <select id="filterDept" class="form-select form-select-sm">
                            <option value="all">All Departments</option>
                            <?php foreach ($departments as $dept): ?>
                                <option value="<?= htmlspecialchars($dept['Department_Name'], ENT_QUOTES, 'UTF-8') ?>">
                                    <?= htmlspecialchars($dept['Department_Name'], ENT_QUOTES, 'UTF-8') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label for="filterCredits" class="form-label small fw-bold">Credits Required</label>
                        <select id="filterCredits" class="form-select form-select-sm">
                            <option value="all">All Credits</option>
                            <option value="<=18">&le; 18 cr</option>
                            <option value="19-36">19 - 36 cr</option>
                            <option value="37-60">37 - 60 cr</option>
                            <option value="60+">60+ cr</option>
                        </select>
                    </div>
                    <div class="col-md-1 d-flex">
                        <button type="button" id="resetFilters" class="btn btn-outline-secondary btn-sm w-100">Reset</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Nav Tabs & Content Card -->
        <div class="card shadow-sm border-0 rounded-3 mb-4">
            <div class="card-header bg-white py-3 border-0">
                <ul class="nav nav-pills" id="majorMinorTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active px-4" id="majors-tab" data-bs-toggle="pill" data-bs-target="#majors-pane" type="button" role="tab" aria-controls="majors-pane" aria-selected="true">
                            Degree Majors (<span id="majorsBadgeCount"><?= count($majors) ?></span>)
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link px-4" id="minors-tab" data-bs-toggle="pill" data-bs-target="#minors-pane" type="button" role="tab" aria-controls="minors-pane" aria-selected="false">
                            Academic Minors (<span id="minorsBadgeCount"><?= count($minors) ?></span>)
                        </button>
                    </li>
                </ul>
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
                                        <?php foreach ($majors as $m):
                                            $progLevel = $m['Program_Level'] ?? 'Undergraduate';
                                            if ($progLevel === 'Ph.D.') {
                                                $lvlBadge = '<span class="badge bg-dark">Ph.D.</span>';
                                            } elseif ($progLevel === 'Graduate') {
                                                $lvlBadge = '<span class="badge text-white" style="background-color: #6f42c1;">Graduate</span>';
                                            } else {
                                                $lvlBadge = '<span class="badge bg-primary">Undergraduate</span>';
                                            }
                                        ?>
                                            <tr data-id="<?= (int)$m['Major_ID'] ?>"
                                                data-name="<?= htmlspecialchars(strtolower($m['Major_Name']), ENT_QUOTES, 'UTF-8') ?>"
                                                data-level="<?= htmlspecialchars($progLevel, ENT_QUOTES, 'UTF-8') ?>"
                                                data-dept="<?= htmlspecialchars($m['Department_Name'], ENT_QUOTES, 'UTF-8') ?>"
                                                data-credits="<?= (int)$m['No_Credits_Required'] ?>">
                                                <td class="ps-4 font-monospace fw-semibold text-dark">
                                                    <?= (int)$m['Major_ID'] ?>
                                                </td>
                                                <td>
                                                    <span class="fw-bold text-dark"><?= htmlspecialchars($m['Major_Name'], ENT_QUOTES, 'UTF-8') ?></span>
                                                </td>
                                                <td>
                                                    <?= $lvlBadge ?>
                                                </td>
                                                <td>
                                                    <span class="badge bg-light text-dark border"><?= htmlspecialchars($m['Department_Name'], ENT_QUOTES, 'UTF-8') ?></span>
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
                                                            Edit &amp; Courses
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
                                        <th>Level</th>
                                        <th>Department</th>
                                        <th class="text-center">Credits Required</th>
                                        <th class="text-center">Required Courses</th>
                                        <th class="text-end pe-4">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($minors)): ?>
                                        <tr>
                                            <td colspan="7" class="text-center py-4 text-muted">No minors registered in the system.</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($minors as $mn):
                                            $progLevel = $mn['Program_Level'] ?? 'Undergraduate';
                                            if ($progLevel === 'Ph.D.') {
                                                $lvlBadge = '<span class="badge bg-dark">Ph.D.</span>';
                                            } elseif ($progLevel === 'Graduate') {
                                                $lvlBadge = '<span class="badge text-white" style="background-color: #6f42c1;">Graduate</span>';
                                            } else {
                                                $lvlBadge = '<span class="badge bg-primary">Undergraduate</span>';
                                            }
                                        ?>
                                            <tr data-id="<?= (int)$mn['Minor_ID'] ?>"
                                                data-name="<?= htmlspecialchars(strtolower($mn['Minor_Name']), ENT_QUOTES, 'UTF-8') ?>"
                                                data-level="<?= htmlspecialchars($progLevel, ENT_QUOTES, 'UTF-8') ?>"
                                                data-dept="<?= htmlspecialchars($mn['Department_Name'], ENT_QUOTES, 'UTF-8') ?>"
                                                data-credits="<?= (int)$mn['No_Credits_Required'] ?>">
                                                <td class="ps-4 font-monospace fw-semibold text-dark">
                                                    <?= (int)$mn['Minor_ID'] ?>
                                                </td>
                                                <td>
                                                    <span class="fw-bold text-dark"><?= htmlspecialchars($mn['Minor_Name'], ENT_QUOTES, 'UTF-8') ?></span>
                                                </td>
                                                <td>
                                                    <?= $lvlBadge ?>
                                                </td>
                                                <td>
                                                    <span class="badge bg-light text-dark border"><?= htmlspecialchars($mn['Department_Name'], ENT_QUOTES, 'UTF-8') ?></span>
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
                                                            Edit &amp; Courses
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

    <!-- Create Major Modal -->
    <?php if ($canWrite): ?>
        <div class="modal fade" id="createMajorModal" tabindex="-1" aria-labelledby="createMajorModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <form method="POST" action="majors_minors_process.php">
                        <input type="hidden" name="action" value="create_major">
                        <div class="modal-header">
                            <h5 class="modal-title fw-bold" id="createMajorModalLabel">Create New Academic Major</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <div class="mb-3">
                                <label for="modal_major_id" class="form-label fw-semibold">Major ID</label>
                                <input type="number" class="form-control font-monospace" id="modal_major_id" name="major_id" value="<?= $suggestedMajorID ?>" required min="1">
                            </div>
                            <div class="mb-3">
                                <label for="modal_major_name" class="form-label fw-semibold">Major Program Name</label>
                                <input type="text" class="form-control" id="modal_major_name" name="major_name" placeholder="e.g. Computer Science (B.S.)" required maxlength="100">
                            </div>
                            <div class="mb-3">
                                <label for="modal_major_level" class="form-label fw-semibold">Program Level</label>
                                <select name="program_level" id="modal_major_level" class="form-select" required>
                                    <option value="Undergraduate" selected>Undergraduate</option>
                                    <option value="Graduate">Graduate</option>
                                    <option value="Ph.D.">Ph.D.</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label for="modal_major_dept" class="form-label fw-semibold">Department</label>
                                <select name="department_id" id="modal_major_dept" class="form-select" required>
                                    <option value="">Select Department...</option>
                                    <?php foreach ($departments as $dept): ?>
                                        <option value="<?= (int)$dept['Department_ID'] ?>"><?= htmlspecialchars($dept['Department_Name'], ENT_QUOTES, 'UTF-8') ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label for="modal_major_credits" class="form-label fw-semibold">Required Credits</label>
                                <input type="number" class="form-control font-monospace" id="modal_major_credits" name="no_credits_required" value="120" required min="1">
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-primary">Create Major</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Create Minor Modal -->
        <div class="modal fade" id="createMinorModal" tabindex="-1" aria-labelledby="createMinorModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <form method="POST" action="majors_minors_process.php">
                        <input type="hidden" name="action" value="create_minor">
                        <div class="modal-header">
                            <h5 class="modal-title fw-bold" id="createMinorModalLabel">Create New Academic Minor</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <div class="mb-3">
                                <label for="modal_minor_id" class="form-label fw-semibold">Minor ID</label>
                                <input type="number" class="form-control font-monospace" id="modal_minor_id" name="minor_id" value="<?= $suggestedMinorID ?>" required min="1">
                            </div>
                            <div class="mb-3">
                                <label for="modal_minor_name" class="form-label fw-semibold">Minor Name</label>
                                <input type="text" class="form-control" id="modal_minor_name" name="minor_name" placeholder="e.g. Computer Science Minor" required maxlength="100">
                            </div>
                            <div class="mb-3">
                                <label for="modal_minor_level" class="form-label fw-semibold">Program Level</label>
                                <select name="program_level" id="modal_minor_level" class="form-select" required>
                                    <option value="Undergraduate" selected>Undergraduate</option>
                                    <option value="Graduate">Graduate</option>
                                    <option value="Ph.D.">Ph.D.</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label for="modal_minor_dept" class="form-label fw-semibold">Department</label>
                                <select name="department_id" id="modal_minor_dept" class="form-select" required>
                                    <option value="">Select Department...</option>
                                    <?php foreach ($departments as $dept): ?>
                                        <option value="<?= (int)$dept['Department_ID'] ?>"><?= htmlspecialchars($dept['Department_Name'], ENT_QUOTES, 'UTF-8') ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label for="modal_minor_credits" class="form-label fw-semibold">Required Credits</label>
                                <input type="number" class="form-control font-monospace" id="modal_minor_credits" name="no_credits_required" value="20" required min="1">
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-success">Create Minor</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Bootstrap 5.3 JS Bundle & Multi-Attribute Filter Script -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function applyMultiFilters() {
            const idVal = document.getElementById('filterId').value.trim().toLowerCase();
            const nameVal = document.getElementById('filterName').value.trim().toLowerCase();
            const levelVal = document.getElementById('filterLevel').value.trim();
            const deptVal = document.getElementById('filterDept').value.trim();
            const creditsVal = document.getElementById('filterCredits').value.trim();

            ['majorsTable', 'minorsTable'].forEach(tableId => {
                const table = document.getElementById(tableId);
                if (!table) return;
                const rows = table.querySelectorAll('tbody tr[data-id]');
                rows.forEach(row => {
                    const rowId = (row.getAttribute('data-id') || '').toLowerCase();
                    const rowName = (row.getAttribute('data-name') || '').toLowerCase();
                    const rowLevel = row.getAttribute('data-level') || '';
                    const rowDept = row.getAttribute('data-dept') || '';
                    const rowCredits = parseInt(row.getAttribute('data-credits') || '0', 10);

                    let match = true;

                    if (idVal && !rowId.includes(idVal)) {
                        match = false;
                    }
                    if (nameVal && !rowName.includes(nameVal)) {
                        match = false;
                    }
                    if (levelVal && levelVal !== 'all' && rowLevel !== levelVal) {
                        match = false;
                    }
                    if (deptVal && deptVal !== 'all' && rowDept !== deptVal) {
                        match = false;
                    }
                    if (creditsVal && creditsVal !== 'all') {
                        if (creditsVal === '<=18' && !(rowCredits <= 18)) {
                            match = false;
                        } else if (creditsVal === '19-36' && !(rowCredits >= 19 && rowCredits <= 36)) {
                            match = false;
                        } else if (creditsVal === '37-60' && !(rowCredits >= 37 && rowCredits <= 60)) {
                            match = false;
                        } else if (creditsVal === '60+' && !(rowCredits > 60)) {
                            match = false;
                        }
                    }

                    row.style.display = match ? '' : 'none';
                });
            });
        }

        ['filterId', 'filterName'].forEach(id => {
            const el = document.getElementById(id);
            if (el) el.addEventListener('input', applyMultiFilters);
        });

        ['filterLevel', 'filterDept', 'filterCredits'].forEach(id => {
            const el = document.getElementById(id);
            if (el) el.addEventListener('change', applyMultiFilters);
        });

        const resetBtn = document.getElementById('resetFilters');
        if (resetBtn) {
            resetBtn.addEventListener('click', function() {
                document.getElementById('filterId').value = '';
                document.getElementById('filterName').value = '';
                document.getElementById('filterLevel').value = 'all';
                document.getElementById('filterDept').value = 'all';
                document.getElementById('filterCredits').value = 'all';
                applyMultiFilters();
            });
        }
    </script>
</body>
</html>
