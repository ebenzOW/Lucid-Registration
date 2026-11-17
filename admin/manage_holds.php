<?php
/**
 * Lucid University Course Registration System
 * Admin Student Holds Management Portal
 */

require_once 'admin_guard.php';
require_once '../db_connect.php';
require_once __DIR__ . '/../includes/academic_helpers.php';
require_once '../profile_hover_card.php';

$admin_id = (int)$userID;
$can_write = ((int)($_SESSION['Security_Level'] ?? 1) >= 2);

// Session Flash Messages
$successMessage = $_SESSION['success_message'] ?? null;
$errorMessage = $_SESSION['error_message'] ?? null;
unset($_SESSION['success_message'], $_SESSION['error_message']);

// Fetch list of all hold types for modal & dropdown
$holdsListStmt = $conn->prepare("SELECT Hold_ID, Hold_Type FROM HOLDS ORDER BY Hold_ID ASC");
$holdsListStmt->execute();
$allHolds = $holdsListStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$holdsListStmt->close();

// Filters from GET
$filter_student_id = isset($_GET['student_id']) ? trim($_GET['student_id']) : '';
$filter_fname      = isset($_GET['fname']) ? trim($_GET['fname']) : '';
$filter_lname      = isset($_GET['lname']) ? trim($_GET['lname']) : '';
$filter_hold_id    = isset($_GET['hold_id']) ? filter_var($_GET['hold_id'], FILTER_VALIDATE_INT) : null;

// Build query for students with active holds
$query = "
    SELECT s.Student_ID, s.Student_Type,
           u.User_FName, u.User_MName, u.User_LName,
           l.User_Email,
           sh.Hold_ID, h.Hold_Type, sh.Hold_Date
    FROM STUDENT s
    JOIN `USER` u ON s.Student_ID = u.User_ID
    LEFT JOIN LOGIN l ON s.Student_ID = l.User_ID
    JOIN STUDENT_HOLDS sh ON s.Student_ID = sh.Student_ID
    JOIN HOLDS h ON sh.Hold_ID = h.Hold_ID
    WHERE 1=1
";

$types = "";
$params = [];

if ($filter_student_id !== '') {
    $query .= " AND CAST(s.Student_ID AS CHAR) LIKE ? ";
    $types .= "s";
    $params[] = '%' . $filter_student_id . '%';
}

if ($filter_fname !== '') {
    $query .= " AND u.User_FName LIKE ? ";
    $types .= "s";
    $params[] = '%' . $filter_fname . '%';
}

if ($filter_lname !== '') {
    $query .= " AND u.User_LName LIKE ? ";
    $types .= "s";
    $params[] = '%' . $filter_lname . '%';
}

if ($filter_hold_id) {
    // If hold_id is filtered, use EXISTS so all active holds of matching students are still selected
    $query .= " AND EXISTS (SELECT 1 FROM STUDENT_HOLDS sh2 WHERE sh2.Student_ID = s.Student_ID AND sh2.Hold_ID = ?) ";
    $types .= "i";
    $params[] = $filter_hold_id;
}

$query .= " ORDER BY s.Student_ID ASC, sh.Hold_Date DESC, h.Hold_Type ASC";

$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

// Aggregate rows by Student_ID in PHP so a student with multiple holds is strictly ONE row
$studentsWithHolds = [];
while ($row = $result->fetch_assoc()) {
    $sid = (int)$row['Student_ID'];
    if (!isset($studentsWithHolds[$sid])) {
        $fullName = trim($row['User_FName'] . ' ' . (!empty($row['User_MName']) ? $row['User_MName'] . ' ' : '') . $row['User_LName']);
        $studentsWithHolds[$sid] = [
            'Student_ID'   => $sid,
            'User_FName'   => $row['User_FName'],
            'User_LName'   => $row['User_LName'],
            'full_name'    => $fullName,
            'User_Email'   => $row['User_Email'],
            'Student_Type' => $row['Student_Type'],
            'holds'        => []
        ];
    }
    $studentsWithHolds[$sid]['holds'][] = [
        'Hold_ID'   => (int)$row['Hold_ID'],
        'Hold_Type' => $row['Hold_Type'],
        'Hold_Date' => $row['Hold_Date']
    ];
}
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Student Holds | Lucid University Admin</title>
    <!-- Minimal Clean Bootstrap 5.3 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        <?= getProfileHoverCardStyles() ?>
    </style>
</head>
<body class="bg-light">

    <?= renderAdminNavbar($conn, $admin_id, basename($_SERVER['PHP_SELF'])) ?>

    <div class="container mb-5">

        <!-- Flash Messages -->
        <?php if ($successMessage): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <strong>Success:</strong> <?= htmlspecialchars($successMessage, ENT_QUOTES, 'UTF-8') ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <?php if ($errorMessage): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <strong>Error:</strong> <?= htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8') ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <!-- Page Header -->
        <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
            <div>
                <h2 class="fw-bold mb-1">Student Account Holds Management</h2>
                <div class="text-secondary">
                    Review active academic, financial, disciplinary, and health holds preventing course registration.
                </div>
            </div>
            <div class="d-flex gap-2">
                <?php if ($can_write): ?>
                    <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addHoldModal">
                        + Add Hold to Student
                    </button>
                <?php endif; ?>
                <a href="dashboard.php" class="btn btn-outline-secondary btn-sm">&larr; Back to Dashboard</a>
            </div>
        </div>

        <!-- Search & Filter Card -->
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-body bg-white p-3">
                <form method="GET" action="manage_holds.php" class="row g-2 align-items-end">
                    <div class="col-md-3">
                        <label for="filter_student_id" class="form-label small fw-bold">Student ID</label>
                        <input type="text" name="student_id" id="filter_student_id" class="form-control form-control-sm" placeholder="e.g. 100001" value="<?= htmlspecialchars($filter_student_id, ENT_QUOTES, 'UTF-8') ?>">
                    </div>
                    <div class="col-md-3">
                        <label for="filter_fname" class="form-label small fw-bold">First Name</label>
                        <input type="text" name="fname" id="filter_fname" class="form-control form-control-sm" placeholder="First Name" value="<?= htmlspecialchars($filter_fname, ENT_QUOTES, 'UTF-8') ?>">
                    </div>
                    <div class="col-md-3">
                        <label for="filter_lname" class="form-label small fw-bold">Last Name</label>
                        <input type="text" name="lname" id="filter_lname" class="form-control form-control-sm" placeholder="Last Name" value="<?= htmlspecialchars($filter_lname, ENT_QUOTES, 'UTF-8') ?>">
                    </div>
                    <div class="col-md-3">
                        <label for="filter_hold_id" class="form-label small fw-bold">Hold Type</label>
                        <select name="hold_id" id="filter_hold_id" class="form-select form-select-sm">
                            <option value="">All Hold Types</option>
                            <?php foreach ($allHolds as $h): ?>
                                <option value="<?= (int)$h['Hold_ID'] ?>" <?= ($filter_hold_id === (int)$h['Hold_ID']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($h['Hold_Type'], ENT_QUOTES, 'UTF-8') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-12 d-flex justify-content-end gap-2 mt-3">
                        <button type="submit" class="btn btn-primary btn-sm">Filter</button>
                        <?php if ($filter_student_id !== '' || $filter_fname !== '' || $filter_lname !== '' || $filter_hold_id): ?>
                            <a href="manage_holds.php" class="btn btn-outline-secondary btn-sm">Reset</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>

        <!-- One-Row-Per-Student Aggregated Holds Table -->
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <h5 class="mb-0 fw-bold">Students with Active Holds</h5>
                <span class="badge bg-secondary"><?= count($studentsWithHolds) ?> Students Flagged</span>
            </div>
            <div class="card-body p-0">
                <?php if (empty($studentsWithHolds)): ?>
                    <div class="p-5 text-center text-muted">
                        No students with active registration holds match your search criteria.
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover table-striped mb-0 align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th style="width: 120px;">Student ID</th>
                                    <th>First Name</th>
                                    <th>Last Name</th>
                                    <th>Email</th>
                                    <th>Student Type</th>
                                    <th>Active Holds &amp; Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($studentsWithHolds as $sid => $st): ?>
                                    <tr>
                                        <td>
                                            <a href="../user_profile.php?user_id=<?= $sid ?>" class="fw-bold text-decoration-none">
                                                <?= $sid ?>
                                            </a>
                                        </td>
                                        <td><?= htmlspecialchars($st['User_FName'], ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><?= htmlspecialchars($st['User_LName'], ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><?= htmlspecialchars($st['User_Email'] ?? '-', ENT_QUOTES, 'UTF-8') ?></td>
                                        <td>
                                            <span class="badge bg-secondary-subtle text-secondary border">
                                                <?= htmlspecialchars($st['Student_Type'], ENT_QUOTES, 'UTF-8') ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="d-flex flex-wrap gap-2 align-items-center">
                                                <?php foreach ($st['holds'] as $h): ?>
                                                    <span class="badge bg-danger-subtle text-danger border border-danger-subtle p-2 d-inline-flex align-items-center gap-2">
                                                        <span>
                                                            <strong><?= htmlspecialchars($h['Hold_Type'], ENT_QUOTES, 'UTF-8') ?></strong>
                                                            <small class="text-muted ms-1">(<?= htmlspecialchars($h['Hold_Date'], ENT_QUOTES, 'UTF-8') ?>)</small>
                                                        </span>
                                                        <?php if ($can_write): ?>
                                                            <form method="POST" action="manage_holds_process.php" class="d-inline m-0" onsubmit="return confirm('Remove <?= htmlspecialchars($h['Hold_Type'], ENT_QUOTES, 'UTF-8') ?> hold from Student <?= $sid ?>?');">
                                                                <input type="hidden" name="action" value="remove_hold">
                                                                <input type="hidden" name="student_id" value="<?= $sid ?>">
                                                                <input type="hidden" name="hold_id" value="<?= $h['Hold_ID'] ?>">
                                                                <button type="submit" class="btn btn-outline-danger btn-sm py-0 px-1" style="font-size: 0.72rem; line-height: 1.2;" title="Remove Hold">
                                                                    &times; Remove
                                                                </button>
                                                            </form>
                                                        <?php endif; ?>
                                                    </span>
                                                <?php endforeach; ?>
                                            </div>
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

    <!-- Add Hold Modal -->
    <?php if ($can_write): ?>
        <div class="modal fade" id="addHoldModal" tabindex="-1" aria-labelledby="addHoldModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <form method="POST" action="manage_holds_process.php">
                        <input type="hidden" name="action" value="add_hold">
                        <div class="modal-header">
                            <h5 class="modal-title fw-bold" id="addHoldModalLabel">Add Administrative Hold to Student</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <div class="mb-3">
                                <label for="modal_student_id" class="form-label fw-semibold">Student ID <span class="text-danger">*</span></label>
                                <input type="number" name="student_id" id="modal_student_id" class="form-control" placeholder="Enter 6-7 digit Student ID (e.g. 100001)" required min="100000" max="9999999">
                                <div class="form-text">Must be a valid registered student account.</div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Select Hold(s) to Apply <span class="text-danger">*</span></label>
                                <div class="border rounded p-3 bg-light">
                                    <?php foreach ($allHolds as $h): ?>
                                        <div class="form-check mb-2">
                                            <input class="form-check-input" type="checkbox" name="hold_ids[]" value="<?= (int)$h['Hold_ID'] ?>" id="hold_check_<?= (int)$h['Hold_ID'] ?>">
                                            <label class="form-check-label" for="hold_check_<?= (int)$h['Hold_ID'] ?>">
                                                <strong><?= htmlspecialchars($h['Hold_Type'], ENT_QUOTES, 'UTF-8') ?> Hold</strong>
                                            </label>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                <div class="form-text">Check one or more administrative hold types to apply immediately.</div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-primary btn-sm">Apply Hold(s)</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Bootstrap 5.3 JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

