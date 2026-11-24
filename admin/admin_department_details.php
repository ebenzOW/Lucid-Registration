<?php
/**
 * Lucid University Course Registration System
 * Admin Portal - Academic Department Details
 */

require_once 'admin_guard.php';
require_once '../db_connect.php';
require_once '../department_helpers.php';
require_once '../profile_hover_card.php';

$admin_id = (int)$userID;
$canWrite = ((int)($_SESSION['Security_Level'] ?? 1) >= 2);

// Use the same security token and flash messages as the department management page.
if (empty($_SESSION['department_admin_csrf'])) {
    $_SESSION['department_admin_csrf'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['department_admin_csrf'];
$flash = $_SESSION['department_admin_flash'] ?? null;
unset($_SESSION['department_admin_flash']);

$offices = [];
$chairs = [];

$department = null;
$majors = [];
$minors = [];
$departmentCourses = [];
$majorRequiredCourses = [];
$minorRequiredCourses = [];
$errorMessage = null;

$departmentId = filter_input(INPUT_GET, 'department_id', FILTER_VALIDATE_INT,
    ['options' => ['min_range' => 1]]);

if ($departmentId === false || $departmentId === null) {
    http_response_code(400);
    $errorMessage = 'Please choose a valid department.';
} else {
    try {
        // Fetch department information and its majors/minors using existing helpers.
        // Include office and chair so the Edit popup can preselect current values.
        $deptStmt = $conn->prepare("
            SELECT Department_ID, Department_Name, Department_Email,
                   Department_Phone, Office_ID, Chair_ID
            FROM DEPARTMENT
            WHERE Department_ID = ?
        ");
        $deptStmt->bind_param('i', $departmentId);
        $deptStmt->execute();
        $department = $deptStmt->get_result()->fetch_assoc();
        $deptStmt->close();

        if (!$department) {
            http_response_code(404);
            $errorMessage = 'This department could not be found.';
        } else {
            $majors = getMajorsByDepartment($conn, $departmentId);
            $minors = getMinorsByDepartment($conn, $departmentId);
            $departmentCourses = getCoursesByDepartment($conn, $departmentId);
            $majorRequiredCourses = getMajorRequiredCoursesByDepartment($conn, $departmentId);
            $minorRequiredCourses = getMinorRequiredCoursesByDepartment($conn, $departmentId);

            if ($canWrite) {
                // Dropdown options for the Edit Department popup.
                $officeStmt = $conn->prepare("
                    SELECT o.Office_ID, b.Building_Name, r.Room_NO
                    FROM OFFICE o
                    JOIN ROOM r ON o.Office_ID = r.Room_ID
                    JOIN BUILDING b ON r.Building_ID = b.Building_ID
                    ORDER BY b.Building_Name ASC, r.Room_NO ASC
                ");
                $officeStmt->execute();
                $offices = $officeStmt->get_result()->fetch_all(MYSQLI_ASSOC);
                $officeStmt->close();

                $chairStmt = $conn->prepare("
                    SELECT f.Faculty_ID, u.User_FName, u.User_LName
                    FROM FACULTY f
                    JOIN `USER` u ON f.Faculty_ID = u.User_ID
                    ORDER BY u.User_LName ASC, u.User_FName ASC
                ");
                $chairStmt->execute();
                $chairs = $chairStmt->get_result()->fetch_all(MYSQLI_ASSOC);
                $chairStmt->close();
            }
        }
    } catch (Throwable $e) {
        error_log('Admin department details error: ' . $e->getMessage());
        http_response_code(503);
        $errorMessage = 'Unable to load department information right now.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $department ? escapeHtml($department['Department_Name']) : 'Department Details' ?> | Lucid University Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        <?= getProfileHoverCardStyles() ?>
    </style>
</head>
<body class="bg-light d-flex flex-column min-vh-100">
    <!-- Unified Admin Top Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark mb-4 shadow-sm">
        <div class="container-fluid px-lg-5">
            <a class="navbar-brand fw-bold" href="dashboard.php">Lucid University <span class="badge bg-danger ms-1">Admin</span></a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#adminNav" aria-controls="adminNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="adminNav">
                <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                    <li class="nav-item"><a class="nav-link" href="dashboard.php">Dashboard</a></li>
                    <li class="nav-item"><a class="nav-link" href="create_user.php">Create User</a></li>
                    <li class="nav-item"><a class="nav-link" href="view_users.php">View Users</a></li>
                    <li class="nav-item"><a class="nav-link" href="create_course_section.php">Create Course Section</a></li>
                    <li class="nav-item"><a class="nav-link" href="manage_courses.php">Manage Courses</a></li>
                    <li class="nav-item"><a class="nav-link" href="manage_holds.php">Manage Holds</a></li>
                    <li class="nav-item"><a class="nav-link" href="semesters.php">Semesters</a></li>
                    <li class="nav-item"><a class="nav-link" href="majors_minors.php">Majors &amp; Minors</a></li>
                    <li class="nav-item"><a class="nav-link" href="manage_advisors.php">Advisors</a></li>
                    <li class="nav-item"><a class="nav-link active" aria-current="page" href="manage_departments.php">Departments</a></li>
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

    <main class="container-fluid px-lg-5 mb-5 flex-grow-1">
        <?php if ($flash): ?>
            <div class="alert alert-<?= ($flash['type'] ?? '') === 'success' ? 'success' : 'danger' ?> alert-dismissible fade show" role="alert">
                <?= escapeHtml($flash['message'] ?? '') ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
        <?php if ($errorMessage): ?>
            <div class="d-flex justify-content-end mb-4">
                <a href="manage_departments.php" class="btn btn-outline-secondary btn-sm">&larr; Back to Departments</a>
            </div>
            <div class="alert alert-warning"><?= escapeHtml($errorMessage) ?></div>
        <?php else: ?>
            <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
                <div>
                    <h1 class="display-6 fw-bold text-dark mb-3"><?= escapeHtml($department['Department_Name']) ?></h1>
                    <p class="text-secondary mb-1"><strong>Email:</strong>
                        <a href="mailto:<?= escapeHtml($department['Department_Email']) ?>"><?= escapeHtml($department['Department_Email']) ?></a>
                    </p>
                    <p class="text-secondary mb-0"><strong>Phone:</strong> <?= escapeHtml($department['Department_Phone']) ?></p>
                </div>
                <div class="d-flex gap-2">
                    <?php if ($canWrite): ?>
                        <button type="button" class="btn btn-outline-primary btn-sm"
                                data-bs-toggle="modal" data-bs-target="#editDepartmentModal">
                            Edit Department
                        </button>
                    <?php endif; ?>
                    <a href="manage_departments.php" class="btn btn-outline-secondary btn-sm">&larr; Back to Departments</a>
                </div>
            </div>

            <div class="row g-4">
                <section class="col-lg-6" aria-labelledby="majors-heading">
                    <div class="card shadow-sm border-0 h-100">
                        <div class="card-header bg-white p-4 d-flex justify-content-between align-items-center">
                            <h2 class="h4 mb-0" id="majors-heading">Majors</h2>
                            <span class="badge text-bg-primary"><?= count($majors) ?></span>
                        </div>
                        <div class="card-body p-4">
                            <?php if (!$majors): ?>
                                <p class="text-secondary mb-0">No majors are currently listed for this department.</p>
                            <?php else: ?>
                                <ul class="list-group list-group-flush">
                                    <?php foreach ($majors as $major): ?>
                                        <li class="list-group-item px-0 py-3">
                                            <button type="button" class="btn btn-link text-start text-decoration-none fw-semibold p-0"
                                                    data-bs-toggle="modal" data-bs-target="#majorCoursesModal-<?= (int)$major['Major_ID'] ?>">
                                                <?= escapeHtml($major['Major_Name']) ?>
                                            </button>
                                            <div class="small text-secondary"><?= (int)$major['No_Credits_Required'] ?> credits required</div>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                        </div>
                    </div>
                </section>

                <section class="col-lg-6" aria-labelledby="minors-heading">
                    <div class="card shadow-sm border-0 h-100">
                        <div class="card-header bg-white p-4 d-flex justify-content-between align-items-center">
                            <h2 class="h4 mb-0" id="minors-heading">Minors</h2>
                            <span class="badge text-bg-primary"><?= count($minors) ?></span>
                        </div>
                        <div class="card-body p-4">
                            <?php if (!$minors): ?>
                                <p class="text-secondary mb-0">No minors are currently listed for this department.</p>
                            <?php else: ?>
                                <ul class="list-group list-group-flush">
                                    <?php foreach ($minors as $minor): ?>
                                        <li class="list-group-item px-0 py-3">
                                            <button type="button" class="btn btn-link text-start text-decoration-none fw-semibold p-0"
                                                    data-bs-toggle="modal" data-bs-target="#minorCoursesModal-<?= (int)$minor['Minor_ID'] ?>">
                                                <?= escapeHtml($minor['Minor_Name']) ?>
                                            </button>
                                            <div class="small text-secondary"><?= (int)$minor['No_Credits_Required'] ?> credits required</div>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                        </div>
                    </div>
                </section>
            </div>
        <?php endif; ?>
    </main>

    <?php if ($department && $canWrite && !$errorMessage): ?>
        <!-- Edit this department without leaving the details page. -->
        <div class="modal fade" id="editDepartmentModal" tabindex="-1" aria-labelledby="editDepartmentTitle" aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
                <div class="modal-content">
                    <form action="manage_departments_process.php" method="POST">
                        <input type="hidden" name="action" value="update">
                        <input type="hidden" name="department_id" value="<?= (int)$department['Department_ID'] ?>">
                        <input type="hidden" name="csrf_token" value="<?= escapeHtml($csrfToken) ?>">
                        <input type="hidden" name="return_to" value="details">

                        <div class="modal-header">
                            <h2 class="modal-title h5" id="editDepartmentTitle">Edit Department</h2>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body p-4">
                            <p class="small text-secondary">Department ID: <?= (int)$department['Department_ID'] ?> (cannot be changed)</p>
                            <div class="row g-3">
                                <div class="col-12">
                                    <label for="edit-department-name" class="form-label">Department Name <span class="text-danger">*</span></label>
                                    <input id="edit-department-name" name="department_name" class="form-control" maxlength="100" required
                                           value="<?= escapeHtml($department['Department_Name']) ?>">
                                </div>
                                <div class="col-md-6">
                                    <label for="edit-department-email" class="form-label">Email <span class="text-danger">*</span></label>
                                    <input type="email" id="edit-department-email" name="department_email" class="form-control" maxlength="100" required
                                           value="<?= escapeHtml($department['Department_Email']) ?>">
                                </div>
                                <div class="col-md-6">
                                    <label for="edit-department-phone" class="form-label">Phone <span class="text-danger">*</span></label>
                                    <input type="tel" id="edit-department-phone" name="department_phone" class="form-control" maxlength="15" required
                                           value="<?= escapeHtml($department['Department_Phone']) ?>">
                                </div>
                                <div class="col-md-6">
                                    <label for="edit-department-office" class="form-label">Office (optional)</label>
                                    <select id="edit-department-office" name="office_id" class="form-select">
                                        <option value="">No office assigned</option>
                                        <?php foreach ($offices as $office): ?>
                                            <option value="<?= escapeHtml($office['Office_ID']) ?>"
                                                    <?= (string)($department['Office_ID'] ?? '') === (string)$office['Office_ID'] ? 'selected' : '' ?>>
                                                <?= escapeHtml($office['Building_Name'] . ' ' . $office['Room_NO'] . ' (' . $office['Office_ID'] . ')') ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label for="edit-department-chair" class="form-label">Department Chair (optional)</label>
                                    <select id="edit-department-chair" name="chair_id" class="form-select">
                                        <option value="">No chair assigned</option>
                                        <?php foreach ($chairs as $chair): ?>
                                            <option value="<?= (int)$chair['Faculty_ID'] ?>"
                                                    <?= (int)($department['Chair_ID'] ?? 0) === (int)$chair['Faculty_ID'] ? 'selected' : '' ?>>
                                                <?= escapeHtml($chair['User_FName'] . ' ' . $chair['User_LName']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-primary">Save Changes</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($department): ?>
        <!-- Keep the existing public major/minor course popups. -->
        <?php foreach ($majors as $major): ?>
            <?php
                $modalId = 'majorCoursesModal-' . (int)$major['Major_ID'];
                $programType = 'Major';
                $program = $major;
                $program['Program_Name'] = $major['Major_Name'];
                $requiredCourses = $majorRequiredCourses[(int)$major['Major_ID']] ?? [];
                include '../program_courses.php';
            ?>
        <?php endforeach; ?>

        <?php foreach ($minors as $minor): ?>
            <?php
                $modalId = 'minorCoursesModal-' . (int)$minor['Minor_ID'];
                $programType = 'Minor';
                $program = $minor;
                $program['Program_Name'] = $minor['Minor_Name'];
                $requiredCourses = $minorRequiredCourses[(int)$minor['Minor_ID']] ?? [];
                include '../program_courses.php';
            ?>
        <?php endforeach; ?>
    <?php endif; ?>

    <footer class="bg-white border-top py-3 mt-auto">
        <div class="container text-center text-muted small">&copy; <?= date('Y') ?> Lucid University. All rights reserved.</div>
    </footer>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
