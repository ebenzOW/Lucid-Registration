<?php
/**
 * Lucid University Course Registration System
 * Admin Portal - Manage Academic Departments
 */

require_once 'admin_guard.php';
require_once '../db_connect.php';
require_once '../department_helpers.php';
require_once '../profile_hover_card.php';
require_once __DIR__ . '/../includes/ui_helpers.php';

$admin_id = (int)$userID;
$canWrite = ((int)($_SESSION['Security_Level'] ?? 1) >= 2);

if (empty($_SESSION['department_admin_csrf'])) {
    $_SESSION['department_admin_csrf'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['department_admin_csrf'];

$flash = $_SESSION['department_admin_flash'] ?? null;
unset($_SESSION['department_admin_flash']);

$departments = [];
$offices = [];
$chairs = [];
$errorMessage = null;

try {
    //Fetch departments and their academic program counts.
    $deptStmt = $conn->prepare("
        SELECT d.Department_ID, d.Department_Name, d.Department_Email,
               d.Department_Phone, d.Office_ID, d.Chair_ID,
               (SELECT COUNT(*) FROM MAJORS m WHERE m.Department_ID = d.Department_ID) AS Major_Count,
               (SELECT COUNT(*) FROM MINORS n WHERE n.Department_ID = d.Department_ID) AS Minor_Count,
               (SELECT COUNT(*) FROM COURSES c WHERE c.Department_ID = d.Department_ID) AS Course_Count
        FROM DEPARTMENT d
        ORDER BY d.Department_Name ASC, d.Department_ID ASC
    ");
    $deptStmt->execute();
    $departments = $deptStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $deptStmt->close();

    //Fetch available office and chair choices for the Create/Edit popup.
    if ($canWrite) {
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
} catch (Throwable $e) {
    error_log('Manage departments error: ' . $e->getMessage());
    $errorMessage = 'Unable to load departments right now.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Departments | Lucid University Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        <?= getProfileHoverCardStyles() ?>
    </style>
</head>
<body class="bg-light">
    <!-- Unified Admin Top Navigation -->
    <?= renderAdminNavbar($conn, $admin_id, basename($_SERVER['PHP_SELF'])) ?>

    <main class="container-fluid px-lg-5 mb-5">
        <!-- Page Header -->
        <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
            <div>
                <h2 class="fw-bold mb-1">Academic Departments</h2>
            </div>
            <div class="d-flex gap-2">
                <?php if ($canWrite && !$errorMessage): ?>
                    <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#departmentFormModal" data-action="create">+ Add New Department</button>
                    <button type="button" class="btn btn-outline-danger btn-sm" data-bs-toggle="modal" data-bs-target="#deleteDepartmentModal" <?= empty($departments) ? 'disabled' : '' ?>>Delete Department</button>
                <?php endif; ?>
                <a href="admin_dashboard.php" class="btn btn-outline-secondary btn-sm">&larr; Back to Dashboard</a>
            </div>
        </div>

        <?php if ($flash): ?>
            <div class="alert alert-<?= ($flash['type'] ?? '') === 'success' ? 'success' : 'danger' ?> alert-dismissible fade show">
                <?= escapeHtml($flash['message'] ?? '') ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
        <?php if (!$canWrite): ?>
            <div class="alert alert-secondary">Security Level 1 administrators have read-only access.</div>
        <?php endif; ?>
        <?php if ($errorMessage): ?>
            <div class="alert alert-danger"><?= escapeHtml($errorMessage) ?></div>
        <?php elseif (!$departments): ?>
            <div class="alert alert-info">No departments have been added yet.</div>
        <?php else: ?>
            <div class="row g-3">
                <?php foreach ($departments as $department): ?>
                    <div class="col-md-6 col-xl-4">
                        <div class="card shadow-sm border-0 h-100">
                            <div class="card-body p-4 d-flex flex-column">
                                <h2 class="h5 fw-bold mb-2">
                                    <a class="link-dark text-decoration-none" href="admin_department_details.php?department_id=<?= (int)$department['Department_ID'] ?>">
                                        <?= escapeHtml($department['Department_Name']) ?>
                                    </a>
                                </h2>
                                <div class="small text-secondary mb-1">Department ID: <?= (int)$department['Department_ID'] ?></div>
                                <div class="small text-secondary mb-3">
                                    <?= (int)$department['Major_Count'] ?> majors &middot;
                                    <?= (int)$department['Minor_Count'] ?> minors &middot;
                                    <?= (int)$department['Course_Count'] ?> courses
                                </div>
                                <div class="d-flex gap-2 mt-auto">
                                    <a href="admin_department_details.php?department_id=<?= (int)$department['Department_ID'] ?>" class="btn btn-outline-primary btn-sm">View Details</a>
                                    <?php if ($canWrite): ?>
                                        <button type="button" class="btn btn-outline-secondary btn-sm edit-department"
                                                data-bs-toggle="modal" data-bs-target="#departmentFormModal"
                                                data-action="update"
                                                data-department-id="<?= (int)$department['Department_ID'] ?>"
                                                data-name="<?= escapeHtml($department['Department_Name']) ?>"
                                                data-email="<?= escapeHtml($department['Department_Email']) ?>"
                                                data-phone="<?= escapeHtml($department['Department_Phone']) ?>"
                                                data-office-id="<?= escapeHtml($department['Office_ID']) ?>"
                                                data-chair-id="<?= (int)($department['Chair_ID'] ?? 0) ?>">Edit</button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </main>

    <?php if ($canWrite && !$errorMessage): ?>
        <!-- One form handles BOTH create and edit; its fields are populated by JavaScript. -->
        <div class="modal fade" id="departmentFormModal" tabindex="-1" aria-labelledby="departmentFormTitle" aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
                <div class="modal-content">
                    <form id="departmentForm" action="manage_departments_process.php" method="POST">
                        <input type="hidden" name="action" id="department-action" value="create">
                        <input type="hidden" name="csrf_token" value="<?= escapeHtml($csrfToken) ?>">
                        <div class="modal-header">
                            <h2 class="modal-title h5" id="departmentFormTitle">Create Department</h2>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body p-4">
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <label for="department-id" class="form-label">Department ID <span class="text-danger">*</span></label>
                                    <input type="number" id="department-id" name="department_id" class="form-control" min="1" max="2147483647" required>
                                    <div class="form-text">IDs cannot be changed after creation.</div>
                                </div>
                                <div class="col-md-8">
                                    <label for="department-name" class="form-label">Department Name <span class="text-danger">*</span></label>
                                    <input id="department-name" name="department_name" class="form-control" maxlength="100" required>
                                </div>
                                <div class="col-md-6">
                                    <label for="department-email" class="form-label">Email <span class="text-danger">*</span></label>
                                    <input type="email" id="department-email" name="department_email" class="form-control" maxlength="100" required>
                                </div>
                                <div class="col-md-6">
                                    <label for="department-phone" class="form-label">Phone <span class="text-danger">*</span></label>
                                    <input type="tel" id="department-phone" name="department_phone" class="form-control" maxlength="15" required>
                                </div>
                                <div class="col-md-6">
                                    <label for="department-office" class="form-label">Office (optional)</label>
                                    <select id="department-office" name="office_id" class="form-select">
                                        <option value="">No office assigned</option>
                                        <?php foreach ($offices as $office): ?>
                                            <option value="<?= escapeHtml($office['Office_ID']) ?>">
                                                <?= escapeHtml($office['Building_Name'] . ' ' . $office['Room_NO'] . ' (' . $office['Office_ID'] . ')') ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label for="department-chair" class="form-label">Department Chair (optional)</label>
                                    <select id="department-chair" name="chair_id" class="form-select">
                                        <option value="">No chair assigned</option>
                                        <?php foreach ($chairs as $chair): ?>
                                            <option value="<?= (int)$chair['Faculty_ID'] ?>">
                                                <?= escapeHtml($chair['User_FName'] . ' ' . $chair['User_LName']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" id="department-save" class="btn btn-primary">Create Department</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="modal fade" id="deleteDepartmentModal" tabindex="-1" aria-labelledby="deleteDepartmentTitle" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <form action="manage_departments_process.php" method="POST">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="csrf_token" value="<?= escapeHtml($csrfToken) ?>">
                        <div class="modal-header">
                            <h2 class="modal-title h5" id="deleteDepartmentTitle">Delete Department</h2>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body p-4">
                            <label for="delete-department" class="form-label">Select Department</label>
                            <select id="delete-department" name="department_id" class="form-select mb-3" required>
                                <option value="">Select a department</option>
                                <?php foreach ($departments as $department): ?>
                                    <option value="<?= (int)$department['Department_ID'] ?>"><?= escapeHtml($department['Department_Name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <div class="alert alert-warning small">
                                A department with associated majors, minors, courses, students, or faculty records cannot be deleted.
                            </div>
                            <label for="delete-confirmation" class="form-label">Type <strong>DELETE</strong> to confirm</label>
                            <input id="delete-confirmation" name="delete_confirmation" class="form-control" pattern="DELETE" required autocomplete="off">
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-danger">Delete Department</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <?php if ($canWrite && !$errorMessage): ?>
        <script>
            // Reuse the same Bootstrap popup for creating and editing departments.
            const departmentModal = document.getElementById('departmentFormModal');
            departmentModal.addEventListener('show.bs.modal', function (event) {
                const button = event.relatedTarget;
                const isEdit = button && button.dataset.action === 'update';
                const form = document.getElementById('departmentForm');
                form.reset();

                document.getElementById('department-action').value = isEdit ? 'update' : 'create';
                document.getElementById('department-id').readOnly = isEdit;
                document.getElementById('departmentFormTitle').textContent = isEdit ? 'Edit Department' : 'Create Department';
                document.getElementById('department-save').textContent = isEdit ? 'Save Changes' : 'Create Department';

                if (isEdit) {
                    document.getElementById('department-id').value = button.dataset.departmentId;
                    document.getElementById('department-name').value = button.dataset.name;
                    document.getElementById('department-email').value = button.dataset.email;
                    document.getElementById('department-phone').value = button.dataset.phone;
                    document.getElementById('department-office').value = button.dataset.officeId || '';
                    document.getElementById('department-chair').value = button.dataset.chairId === '0' ? '' : button.dataset.chairId;
                }
            });

            <?php
                $editId = filter_input(INPUT_GET, 'edit', FILTER_VALIDATE_INT,
                    ['options' => ['min_range' => 1]]);
            ?>
            <?php if ($editId !== false && $editId !== null): ?>
                const editButton = document.querySelector('.edit-department[data-department-id="<?= (int)$editId ?>"]');
                if (editButton) {
                    bootstrap.Modal.getOrCreateInstance(departmentModal).show(editButton);
                }
            <?php endif; ?>
        </script>
    <?php endif; ?>
</body>
</html>
