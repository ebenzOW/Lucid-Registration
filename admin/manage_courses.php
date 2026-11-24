<?php
/**
 * Lucid University Course Registration System
 * Admin Course Catalog & Prerequisite Management Portal
 */

require_once 'admin_guard.php';
require_once '../db_connect.php';
require_once __DIR__ . '/../includes/ui_helpers.php';
require_once __DIR__ . '/../includes/schedule_helpers.php';

$admin_id = (int)$userID;
$can_write = ((int)($_SESSION['Security_Level'] ?? 1) >= 2);

// Session Flash Messages
$successMessage = $_SESSION['success_message'] ?? null;
$errorMessage = $_SESSION['error_message'] ?? null;
unset($_SESSION['success_message'], $_SESSION['error_message']);

// Fetch list of departments for dropdowns & modals
$deptStmt = $conn->prepare("SELECT Department_ID, Department_Name FROM DEPARTMENT ORDER BY Department_Name ASC");
$deptStmt->execute();
$departmentsList = $deptStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$deptStmt->close();

// Fetch list of all existing courses for prerequisite selectors
$allCoursesStmt = $conn->prepare("SELECT Course_ID, Course_Name, Course_Type, Course_Credits, Department_ID FROM COURSES ORDER BY Course_ID ASC");
$allCoursesStmt->execute();
$allCoursesList = $allCoursesStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$allCoursesStmt->close();

// Multi-attribute filter parameters from GET
$filter_course_id   = isset($_GET['course_id']) ? trim($_GET['course_id']) : '';
$filter_course_name = isset($_GET['course_name']) ? trim($_GET['course_name']) : '';
$filter_course_type = isset($_GET['course_type']) ? trim($_GET['course_type']) : '';
$filter_credits     = (isset($_GET['course_credits']) && trim($_GET['course_credits']) !== '') ? filter_var($_GET['course_credits'], FILTER_VALIDATE_INT) : null;
$filter_dept_id     = (isset($_GET['department_id']) && trim($_GET['department_id']) !== '') ? filter_var($_GET['department_id'], FILTER_VALIDATE_INT) : null;
$filter_description = isset($_GET['description']) ? trim($_GET['description']) : '';
$filter_prereq_id   = isset($_GET['prereq_id']) ? trim($_GET['prereq_id']) : '';

// Build dynamic WHERE clause
$whereClause = " WHERE 1=1 ";
$types = "";
$params = [];

if ($filter_course_id !== '') {
    $whereClause .= " AND c.Course_ID LIKE ? ";
    $types .= "s";
    $params[] = '%' . $filter_course_id . '%';
}

if ($filter_course_name !== '') {
    $whereClause .= " AND c.Course_Name LIKE ? ";
    $types .= "s";
    $params[] = '%' . $filter_course_name . '%';
}

if ($filter_course_type !== '' && in_array($filter_course_type, ['Undergraduate', 'Graduate'], true)) {
    $whereClause .= " AND c.Course_Type = ? ";
    $types .= "s";
    $params[] = $filter_course_type;
}

if ($filter_credits !== null && $filter_credits > 0) {
    $whereClause .= " AND c.Course_Credits = ? ";
    $types .= "i";
    $params[] = $filter_credits;
}

if ($filter_dept_id !== null && $filter_dept_id > 0) {
    $whereClause .= " AND c.Department_ID = ? ";
    $types .= "i";
    $params[] = $filter_dept_id;
}

if ($filter_description !== '') {
    $whereClause .= " AND c.Course_Description LIKE ? ";
    $types .= "s";
    $params[] = '%' . $filter_description . '%';
}

if ($filter_prereq_id !== '') {
    $whereClause .= " AND EXISTS (SELECT 1 FROM COURSE_PREREQUISITE cp WHERE cp.Course_ID = c.Course_ID AND cp.Prerequisite_Course_ID = ?) ";
    $types .= "s";
    $params[] = $filter_prereq_id;
}

$coursesQuery = "
    SELECT c.Course_ID, c.Course_Type, c.Course_Name, c.Course_Description,
           c.Course_Credits, c.Department_ID, d.Department_Name
    FROM COURSES c
    LEFT JOIN DEPARTMENT d ON c.Department_ID = d.Department_ID
    {$whereClause}
    ORDER BY c.Course_ID ASC
";

$stmt = $conn->prepare($coursesQuery);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$courses = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Fetch all prerequisite pairings for rapid badge rendering & pre-populating modals
$prereqMap = [];
$pStmt = $conn->prepare("
    SELECT cp.Course_ID, cp.Prerequisite_Course_ID, pc.Course_Name, pc.Course_Type
    FROM COURSE_PREREQUISITE cp
    JOIN COURSES pc ON cp.Prerequisite_Course_ID = pc.Course_ID
    ORDER BY cp.Course_ID ASC, cp.Prerequisite_Course_ID ASC
");
$pStmt->execute();
$pRes = $pStmt->get_result();
while ($pRow = $pRes->fetch_assoc()) {
    $prereqMap[$pRow['Course_ID']][] = $pRow;
}
$pStmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Courses &amp; Prerequisites | Lucid University Admin</title>
    <!-- Minimal Clean Bootstrap 5.3 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

    <?= renderAdminNavbar($conn, $admin_id, basename($_SERVER['PHP_SELF'])) ?>

    <div class="container-fluid px-lg-5 mb-5">

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
                <h2 class="fw-bold mb-1">Course Catalog &amp; Prerequisite Management</h2>
                <div class="text-secondary">
                    Create, update, inspect, and configure curriculum course offerings and prerequisite dependency rules.
                </div>
            </div>
            <div class="d-flex gap-2">
                <?php if ($can_write): ?>
                    <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addCourseModal">
                        + Add New Course
                    </button>
                <?php endif; ?>
                <a href="admin_dashboard.php" class="btn btn-outline-secondary btn-sm">&larr; Back to Dashboard</a>
            </div>
        </div>

        <!-- Multi-Attribute Filter Bar -->
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-body bg-white p-3">
                <form method="GET" action="manage_courses.php" class="row g-2 align-items-end">
                    <div class="col-md-2">
                        <label for="filter_course_id" class="form-label small fw-bold">Course ID</label>
                        <input type="text" name="course_id" id="filter_course_id" class="form-control form-control-sm" placeholder="e.g. CS101" value="<?= htmlspecialchars($filter_course_id, ENT_QUOTES, 'UTF-8') ?>">
                    </div>
                    <div class="col-md-2">
                        <label for="filter_course_name" class="form-label small fw-bold">Course Name</label>
                        <input type="text" name="course_name" id="filter_course_name" class="form-control form-control-sm" placeholder="e.g. Algorithms" value="<?= htmlspecialchars($filter_course_name, ENT_QUOTES, 'UTF-8') ?>">
                    </div>
                    <div class="col-md-2">
                        <label for="filter_course_type" class="form-label small fw-bold">Level / Type</label>
                        <select name="course_type" id="filter_course_type" class="form-select form-select-sm">
                            <option value="">All Types</option>
                            <option value="Undergraduate" <?= ($filter_course_type === 'Undergraduate') ? 'selected' : '' ?>>Undergraduate</option>
                            <option value="Graduate" <?= ($filter_course_type === 'Graduate') ? 'selected' : '' ?>>Graduate</option>
                        </select>
                    </div>
                    <div class="col-md-1">
                        <label for="filter_credits" class="form-label small fw-bold">Credits</label>
                        <input type="number" name="course_credits" id="filter_credits" min="1" max="6" class="form-control form-control-sm" value="<?= $filter_credits ? (int)$filter_credits : '' ?>">
                    </div>
                    <div class="col-md-2">
                        <label for="filter_dept_id" class="form-label small fw-bold">Department</label>
                        <select name="department_id" id="filter_dept_id" class="form-select form-select-sm">
                            <option value="">All Departments</option>
                            <?php foreach ($departmentsList as $dept): ?>
                                <option value="<?= (int)$dept['Department_ID'] ?>" <?= ($filter_dept_id === (int)$dept['Department_ID']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($dept['Department_Name'], ENT_QUOTES, 'UTF-8') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label for="filter_prereq_id" class="form-label small fw-bold">Requires Prereq</label>
                        <input type="text" name="prereq_id" id="filter_prereq_id" class="form-control form-control-sm" placeholder="e.g. MA110" value="<?= htmlspecialchars($filter_prereq_id, ENT_QUOTES, 'UTF-8') ?>">
                    </div>
                    <div class="col-md-1 d-flex gap-1">
                        <button type="submit" class="btn btn-primary btn-sm flex-grow-1">Filter</button>
                    </div>
                    <div class="col-md-10">
                        <label for="filter_description" class="form-label small fw-bold">Description Keyword</label>
                        <input type="text" name="description" id="filter_description" class="form-control form-control-sm" placeholder="Search keywords in syllabus / course description..." value="<?= htmlspecialchars($filter_description, ENT_QUOTES, 'UTF-8') ?>">
                    </div>
                    <div class="col-md-2 d-flex justify-content-end">
                        <?php if ($filter_course_id !== '' || $filter_course_name !== '' || $filter_course_type !== '' || $filter_credits !== null || $filter_dept_id !== null || $filter_description !== '' || $filter_prereq_id !== ''): ?>
                            <a href="manage_courses.php" class="btn btn-outline-secondary btn-sm w-100">Reset Filters</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>

        <!-- Courses Table Card -->
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <h5 class="mb-0 fw-bold">Course Offerings Catalog</h5>
                <span class="badge bg-secondary"><?= count($courses) ?> Courses Found</span>
            </div>
            <div class="card-body p-0">
                <?php if (empty($courses)): ?>
                    <div class="p-5 text-center text-muted">
                        No courses match the specified search and filter criteria.
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover table-striped mb-0 align-middle small">
                            <thead class="table-light">
                                <tr>
                                    <th style="width: 100px;">Course ID</th>
                                    <th>Course Name</th>
                                    <th>Type</th>
                                    <th class="text-center">Credits</th>
                                    <th>Department</th>
                                    <th>Description</th>
                                    <th>Prerequisites</th>
                                    <?php if ($can_write): ?>
                                        <th class="text-end pe-3" style="width: 160px;">Actions</th>
                                    <?php endif; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($courses as $c): 
                                    $cid = $c['Course_ID'];
                                    $reqPrereqs = $prereqMap[$cid] ?? [];
                                ?>
                                    <tr>
                                        <td><strong><?= htmlspecialchars($cid, ENT_QUOTES, 'UTF-8') ?></strong></td>
                                        <td class="fw-semibold"><?= htmlspecialchars($c['Course_Name'], ENT_QUOTES, 'UTF-8') ?></td>
                                        <td>
                                            <?php if ($c['Course_Type'] === 'Graduate'): ?>
                                                <span class="badge bg-primary-subtle text-primary border border-primary-subtle">Graduate</span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary-subtle text-secondary border">Undergrad</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center fw-bold"><?= (int)$c['Course_Credits'] ?></td>
                                        <td><?= htmlspecialchars($c['Department_Name'] ?? 'General', ENT_QUOTES, 'UTF-8') ?></td>
                                        <td style="max-width: 280px;">
                                            <span class="text-secondary" title="<?= htmlspecialchars($c['Course_Description'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                                                <?php 
                                                    $desc = trim($c['Course_Description'] ?? '');
                                                    echo htmlspecialchars(mb_strimwidth($desc, 0, 75, '...'), ENT_QUOTES, 'UTF-8') ?: '<em class="text-muted">None</em>';
                                                ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if (empty($reqPrereqs)): ?>
                                                <span class="badge bg-light text-muted border">None</span>
                                            <?php else: ?>
                                                <div class="d-flex flex-wrap gap-1">
                                                    <?php foreach ($reqPrereqs as $pr): ?>
                                                        <span class="badge bg-info-subtle text-info border" title="<?= htmlspecialchars($pr['Course_Name'], ENT_QUOTES, 'UTF-8') ?>">
                                                            <?= htmlspecialchars($pr['Prerequisite_Course_ID'], ENT_QUOTES, 'UTF-8') ?>
                                                        </span>
                                                    <?php endforeach; ?>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <?php if ($can_write): ?>
                                            <td class="text-end pe-3">
                                                <div class="d-flex justify-content-end gap-1">
                                                    <button type="button" class="btn btn-sm btn-outline-primary py-0 px-2 edit-course-btn" style="font-size: 0.78rem;"
                                                        data-bs-toggle="modal"
                                                        data-bs-target="#editCourseModal"
                                                        data-course-id="<?= htmlspecialchars($cid, ENT_QUOTES, 'UTF-8') ?>"
                                                        data-course-name="<?= htmlspecialchars($c['Course_Name'], ENT_QUOTES, 'UTF-8') ?>"
                                                        data-course-type="<?= htmlspecialchars($c['Course_Type'], ENT_QUOTES, 'UTF-8') ?>"
                                                        data-credits="<?= (int)$c['Course_Credits'] ?>"
                                                        data-dept-id="<?= (int)$c['Department_ID'] ?>"
                                                        data-description="<?= htmlspecialchars($c['Course_Description'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                                                        data-prereqs="<?= htmlspecialchars(json_encode(array_column($reqPrereqs, 'Prerequisite_Course_ID')), ENT_QUOTES, 'UTF-8') ?>">
                                                        Edit
                                                    </button>
                                                    <form method="POST" action="manage_courses_process.php" class="d-inline m-0" onsubmit="return confirm('Delete course <?= htmlspecialchars($cid, ENT_QUOTES, 'UTF-8') ?>?');">
                                                        <input type="hidden" name="action" value="delete_course">
                                                        <input type="hidden" name="course_id" value="<?= htmlspecialchars($cid, ENT_QUOTES, 'UTF-8') ?>">
                                                        <button type="submit" class="btn btn-sm btn-outline-danger py-0 px-2" style="font-size: 0.78rem;">
                                                            Delete
                                                        </button>
                                                    </form>
                                                </div>
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

    </div>

    <!-- Create Course Modal -->
    <?php if ($can_write): ?>
        <div class="modal fade" id="addCourseModal" tabindex="-1" aria-labelledby="addCourseModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
                <div class="modal-content">
                    <form method="POST" action="manage_courses_process.php">
                        <input type="hidden" name="action" value="create_course">
                        <div class="modal-header">
                            <h5 class="modal-title fw-bold" id="addCourseModalLabel">+ Add New Catalog Course</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <label for="new_course_id" class="form-label fw-semibold">Course ID <span class="text-danger">*</span></label>
                                    <input type="text" name="course_id" id="new_course_id" class="form-control" placeholder="e.g. CS210" required maxlength="15" pattern="[A-Za-z0-9]+">
                                    <div class="form-text">Unique code, max 15 chars (e.g. MA150).</div>
                                </div>
                                <div class="col-md-8">
                                    <label for="new_course_name" class="form-label fw-semibold">Course Name <span class="text-danger">*</span></label>
                                    <input type="text" name="course_name" id="new_course_name" class="form-control" placeholder="e.g. Data Structures &amp; Algorithms" required maxlength="100">
                                </div>
                                <div class="col-md-4">
                                    <label for="new_course_type" class="form-label fw-semibold">Course Type <span class="text-danger">*</span></label>
                                    <select name="course_type" id="new_course_type" class="form-select" required>
                                        <option value="Undergraduate">Undergraduate</option>
                                        <option value="Graduate">Graduate</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label for="new_course_credits" class="form-label fw-semibold">Credits <span class="text-danger">*</span></label>
                                    <input type="number" name="course_credits" id="new_course_credits" class="form-control" min="1" max="6" value="4" required>
                                    <div class="form-text">Between 1 and 6 credit hours.</div>
                                </div>
                                <div class="col-md-4">
                                    <label for="new_department_id" class="form-label fw-semibold">Department <span class="text-danger">*</span></label>
                                    <select name="department_id" id="new_department_id" class="form-select" required>
                                        <option value="">Select Department...</option>
                                        <?php foreach ($departmentsList as $dept): ?>
                                            <option value="<?= (int)$dept['Department_ID'] ?>">
                                                <?= htmlspecialchars($dept['Department_Name'], ENT_QUOTES, 'UTF-8') ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-12">
                                    <label for="new_course_description" class="form-label fw-semibold">Course Description</label>
                                    <textarea name="course_description" id="new_course_description" rows="3" class="form-control" placeholder="Enter syllabus details and topics covered..."></textarea>
                                </div>
                                <div class="col-12">
                                    <label class="form-label fw-semibold">Prerequisite Courses (Optional)</label>

                                    <!-- Filter by Department Checkbox Bar -->
                                    <div class="card bg-white border p-2 mb-2">
                                        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
                                            <span class="small fw-bold text-secondary">Filter Prereqs by Department:</span>
                                            <div class="btn-group btn-group-sm">
                                                <button type="button" class="btn btn-outline-secondary py-0 px-2 btn-dept-all" style="font-size: 0.75rem;">Select All</button>
                                                <button type="button" class="btn btn-outline-secondary py-0 px-2 btn-dept-clear" style="font-size: 0.75rem;">Clear</button>
                                                <button type="button" class="btn btn-outline-primary py-0 px-2 btn-dept-same" style="font-size: 0.75rem;">Same Dept Only</button>
                                            </div>
                                        </div>
                                        <div class="d-flex flex-wrap gap-2 dept-checkboxes-container">
                                            <?php foreach ($departmentsList as $dept): ?>
                                                <div class="form-check form-check-inline m-0">
                                                    <input class="form-check-input dept-filter-cb" type="checkbox" id="add_dept_cb_<?= (int)$dept['Department_ID'] ?>" value="<?= (int)$dept['Department_ID'] ?>" checked>
                                                    <label class="form-check-label small" for="add_dept_cb_<?= (int)$dept['Department_ID'] ?>" style="font-size: 0.78rem;">
                                                        <?= htmlspecialchars($dept['Department_Name'], ENT_QUOTES, 'UTF-8') ?>
                                                    </label>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>

                                    <div class="border rounded p-3 bg-light" style="max-height: 220px; overflow-y: auto;">
                                        <div class="row g-2 prereq-list-container">
                                            <?php foreach ($allCoursesList as $optC): ?>
                                                <div class="col-md-6 prereq-item" data-dept-id="<?= (int)$optC['Department_ID'] ?>" data-course-id="<?= htmlspecialchars($optC['Course_ID'], ENT_QUOTES, 'UTF-8') ?>">
                                                    <div class="form-check">
                                                        <input class="form-check-input prereq-checkbox" type="checkbox" name="prerequisite_ids[]" value="<?= htmlspecialchars($optC['Course_ID'], ENT_QUOTES, 'UTF-8') ?>" id="new_prereq_<?= htmlspecialchars($optC['Course_ID'], ENT_QUOTES, 'UTF-8') ?>">
                                                        <label class="form-check-label small" for="new_prereq_<?= htmlspecialchars($optC['Course_ID'], ENT_QUOTES, 'UTF-8') ?>">
                                                            <strong><?= htmlspecialchars($optC['Course_ID'], ENT_QUOTES, 'UTF-8') ?></strong> &ndash; <?= htmlspecialchars($optC['Course_Name'], ENT_QUOTES, 'UTF-8') ?>
                                                            <span class="badge bg-secondary-subtle text-secondary py-0" style="font-size: 0.68rem;"><?= htmlspecialchars($optC['Course_Type'], ENT_QUOTES, 'UTF-8') ?></span>
                                                        </label>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                    <div class="form-text">Check any courses required prior to registration.</div>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-primary btn-sm">Create Course</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Reusable Edit Course Modal -->
        <div class="modal fade" id="editCourseModal" tabindex="-1" aria-labelledby="edit_modal_title" aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
                <div class="modal-content">
                    <form method="POST" action="manage_courses_process.php">
                        <input type="hidden" name="action" value="update_course">
                        <input type="hidden" name="course_id" id="edit_course_id" value="">
                        <div class="modal-header">
                            <h5 class="modal-title fw-bold" id="edit_modal_title">Edit Course</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <label class="form-label fw-semibold">Course ID</label>
                                    <input type="text" id="edit_course_id_display" class="form-control bg-light" value="" disabled>
                                    <div class="form-text">Course ID primary key cannot be altered.</div>
                                </div>
                                <div class="col-md-8">
                                    <label for="edit_course_name" class="form-label fw-semibold">Course Name <span class="text-danger">*</span></label>
                                    <input type="text" name="course_name" id="edit_course_name" class="form-control" value="" required maxlength="100">
                                </div>
                                <div class="col-md-4">
                                    <label for="edit_course_type" class="form-label fw-semibold">Course Type <span class="text-danger">*</span></label>
                                    <select name="course_type" id="edit_course_type" class="form-select" required>
                                        <option value="Undergraduate">Undergraduate</option>
                                        <option value="Graduate">Graduate</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label for="edit_course_credits" class="form-label fw-semibold">Credits <span class="text-danger">*</span></label>
                                    <input type="number" name="course_credits" id="edit_course_credits" class="form-control" min="1" max="6" value="4" required>
                                </div>
                                <div class="col-md-4">
                                    <label for="edit_department_id" class="form-label fw-semibold">Department <span class="text-danger">*</span></label>
                                    <select name="department_id" id="edit_department_id" class="form-select" required>
                                        <?php foreach ($departmentsList as $dept): ?>
                                            <option value="<?= (int)$dept['Department_ID'] ?>">
                                                <?= htmlspecialchars($dept['Department_Name'], ENT_QUOTES, 'UTF-8') ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-12">
                                    <label for="edit_course_description" class="form-label fw-semibold">Course Description</label>
                                    <textarea name="course_description" id="edit_course_description" rows="3" class="form-control"></textarea>
                                </div>
                                <div class="col-12">
                                    <label class="form-label fw-semibold">Prerequisites</label>

                                    <!-- Filter by Department Checkbox Bar -->
                                    <div class="card bg-white border p-2 mb-2">
                                        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
                                            <span class="small fw-bold text-secondary">Filter Prereqs by Department:</span>
                                            <div class="btn-group btn-group-sm">
                                                <button type="button" class="btn btn-outline-secondary py-0 px-2 btn-dept-all" style="font-size: 0.75rem;">Select All</button>
                                                <button type="button" class="btn btn-outline-secondary py-0 px-2 btn-dept-clear" style="font-size: 0.75rem;">Clear</button>
                                                <button type="button" class="btn btn-outline-primary py-0 px-2 btn-dept-same" style="font-size: 0.75rem;">Same Dept Only</button>
                                            </div>
                                        </div>
                                        <div class="d-flex flex-wrap gap-2 dept-checkboxes-container">
                                            <?php foreach ($departmentsList as $dept): ?>
                                                <div class="form-check form-check-inline m-0">
                                                    <input class="form-check-input dept-filter-cb" type="checkbox" id="edit_dept_cb_<?= (int)$dept['Department_ID'] ?>" value="<?= (int)$dept['Department_ID'] ?>" checked>
                                                    <label class="form-check-label small" for="edit_dept_cb_<?= (int)$dept['Department_ID'] ?>" style="font-size: 0.78rem;">
                                                        <?= htmlspecialchars($dept['Department_Name'], ENT_QUOTES, 'UTF-8') ?>
                                                    </label>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>

                                    <div class="border rounded p-3 bg-light" style="max-height: 220px; overflow-y: auto;">
                                        <div class="row g-2 prereq-list-container">
                                            <?php foreach ($allCoursesList as $optC): ?>
                                                <div class="col-md-6 prereq-item" data-dept-id="<?= (int)$optC['Department_ID'] ?>" data-course-id="<?= htmlspecialchars($optC['Course_ID'], ENT_QUOTES, 'UTF-8') ?>">
                                                    <div class="form-check">
                                                        <input class="form-check-input prereq-checkbox" type="checkbox" name="prerequisite_ids[]" value="<?= htmlspecialchars($optC['Course_ID'], ENT_QUOTES, 'UTF-8') ?>" id="edit_prereq_<?= htmlspecialchars($optC['Course_ID'], ENT_QUOTES, 'UTF-8') ?>">
                                                        <label class="form-check-label small" for="edit_prereq_<?= htmlspecialchars($optC['Course_ID'], ENT_QUOTES, 'UTF-8') ?>">
                                                            <strong><?= htmlspecialchars($optC['Course_ID'], ENT_QUOTES, 'UTF-8') ?></strong> &ndash; <?= htmlspecialchars($optC['Course_Name'], ENT_QUOTES, 'UTF-8') ?>
                                                            <span class="badge bg-secondary-subtle text-secondary py-0" style="font-size: 0.68rem;"><?= htmlspecialchars($optC['Course_Type'], ENT_QUOTES, 'UTF-8') ?></span>
                                                        </label>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                    <div class="form-text">Update required prerequisite courses.</div>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-primary btn-sm">Save Changes</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Bootstrap 5.3 JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        function setupPrereqFilter(modalEl, getSameDeptId) {
            const filterContainer = modalEl.querySelector('.dept-checkboxes-container');
            if (!filterContainer) return;
            const cbs = filterContainer.querySelectorAll('.dept-filter-cb');
            const items = modalEl.querySelectorAll('.prereq-item');
            const btnAll = modalEl.querySelector('.btn-dept-all');
            const btnClear = modalEl.querySelector('.btn-dept-clear');
            const btnSame = modalEl.querySelector('.btn-dept-same');

            function updateVisibility() {
                const checkedDeptIds = new Set();
                cbs.forEach(cb => {
                    if (cb.checked) checkedDeptIds.add(cb.value);
                });
                const currentCourseId = modalEl.getAttribute('data-current-course-id') || '';

                items.forEach(item => {
                    const itemDept = item.getAttribute('data-dept-id');
                    const itemCid = item.getAttribute('data-course-id');
                    if (itemCid && itemCid === currentCourseId) {
                        item.classList.add('d-none');
                    } else if (checkedDeptIds.has(itemDept)) {
                        item.classList.remove('d-none');
                    } else {
                        item.classList.add('d-none');
                    }
                });
            }

            cbs.forEach(cb => cb.addEventListener('change', updateVisibility));

            if (btnAll) {
                btnAll.addEventListener('click', function() {
                    cbs.forEach(cb => cb.checked = true);
                    updateVisibility();
                });
            }

            if (btnClear) {
                btnClear.addEventListener('click', function() {
                    cbs.forEach(cb => cb.checked = false);
                    updateVisibility();
                });
            }

            if (btnSame) {
                btnSame.addEventListener('click', function() {
                    const sameDept = getSameDeptId ? getSameDeptId() : '';
                    cbs.forEach(cb => {
                        cb.checked = (Boolean(sameDept) && String(cb.value) === String(sameDept));
                    });
                    updateVisibility();
                });
            }

            modalEl._updatePrereqVisibility = updateVisibility;
        }

        const addModal = document.getElementById('addCourseModal');
        if (addModal) {
            setupPrereqFilter(addModal, function() {
                return addModal.querySelector('#new_department_id').value;
            });
        }

        const editModal = document.getElementById('editCourseModal');
        if (editModal) {
            setupPrereqFilter(editModal, function() {
                return editModal.querySelector('#edit_department_id').value;
            });

            editModal.addEventListener('show.bs.modal', function(event) {
                const btn = event.relatedTarget;
                if (!btn) return;

                const cid = btn.getAttribute('data-course-id') || '';
                const name = btn.getAttribute('data-course-name') || '';
                const type = btn.getAttribute('data-course-type') || 'Undergraduate';
                const credits = btn.getAttribute('data-credits') || '4';
                const deptId = btn.getAttribute('data-dept-id') || '';
                const desc = btn.getAttribute('data-description') || '';
                let prereqs = [];
                try {
                    prereqs = JSON.parse(btn.getAttribute('data-prereqs') || '[]');
                } catch(e) {
                    prereqs = [];
                }

                editModal.setAttribute('data-current-course-id', cid);
                const titleEl = editModal.querySelector('#edit_modal_title');
                if (titleEl) titleEl.textContent = 'Edit Course: ' + cid;

                const idInput = editModal.querySelector('#edit_course_id');
                if (idInput) idInput.value = cid;

                const idDisplay = editModal.querySelector('#edit_course_id_display');
                if (idDisplay) idDisplay.value = cid;

                const nameInput = editModal.querySelector('#edit_course_name');
                if (nameInput) nameInput.value = name;

                const typeInput = editModal.querySelector('#edit_course_type');
                if (typeInput) typeInput.value = type;

                const creditsInput = editModal.querySelector('#edit_course_credits');
                if (creditsInput) creditsInput.value = credits;

                const deptInput = editModal.querySelector('#edit_department_id');
                if (deptInput) deptInput.value = deptId;

                const descInput = editModal.querySelector('#edit_course_description');
                if (descInput) descInput.value = desc;

                // Sync prereq checkboxes
                const prereqCbs = editModal.querySelectorAll('.prereq-checkbox');
                prereqCbs.forEach(cb => {
                    cb.checked = prereqs.includes(cb.value);
                });

                // Reset department checkboxes to checked
                const deptCbs = editModal.querySelectorAll('.dept-filter-cb');
                deptCbs.forEach(cb => cb.checked = true);

                if (editModal._updatePrereqVisibility) {
                    editModal._updatePrereqVisibility();
                }
            });
        }
    });
    </script>

    <!-- Bootstrap 5.3 JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
