<?php
/**
 * Lucid University Course Registration System
 * Admin Portal - Update Minor & Course Requirements (Use Case: Updating Minors)
 */

require_once 'admin_guard.php';
require_once '../db_connect.php';
require_once '../profile_hover_card.php';

$admin_id = (int)$userID;

$successMessage = $_SESSION["success_message"] ?? "";
$errorMessage   = $_SESSION["error_message"] ?? "";
unset($_SESSION["success_message"], $_SESSION["error_message"]);

$minor_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($minor_id <= 0) {
    $_SESSION['error_message'] = "Invalid Minor ID specified.";
    header("Location: majors_minors.php");
    exit;
}

// 1. Fetch Minor
$stmt = $conn->prepare("
    SELECT mn.Minor_ID, mn.Minor_Name, mn.No_Credits_Required, mn.Department_ID, d.Department_Name
    FROM MINORS mn
    JOIN DEPARTMENT d ON mn.Department_ID = d.Department_ID
    WHERE mn.Minor_ID = ?
");
$stmt->bind_param("i", $minor_id);
$stmt->execute();
$minor = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$minor) {
    $_SESSION['error_message'] = "Minor ID {$minor_id} was not found in the database.";
    header("Location: majors_minors.php");
    exit;
}

// 2. Fetch Departments for dropdown
$deptStmt = $conn->prepare("SELECT Department_ID, Department_Name FROM DEPARTMENT ORDER BY Department_Name ASC");
$deptStmt->execute();
$departments = $deptStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$deptStmt->close();

// 3. Fetch Currently Required Courses for this Minor
$reqStmt = $conn->prepare("
    SELECT mnr.Course_ID, mnr.Min_Grade, c.Course_Name, c.Course_Type, c.Course_Credits, d.Department_Name
    FROM MINOR_REQUIREMENTS mnr
    JOIN COURSES c ON mnr.Course_ID = c.Course_ID
    JOIN DEPARTMENT d ON c.Department_ID = d.Department_ID
    WHERE mnr.Minor_ID = ?
    ORDER BY c.Course_ID ASC
");
$reqStmt->bind_param("i", $minor_id);
$reqStmt->execute();
$requiredCourses = $reqStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$reqStmt->close();

$totalReqCredits = 0;
foreach ($requiredCourses as $rc) {
    $totalReqCredits += (int)$rc['Course_Credits'];
}

// 4. Fetch Available Courses to add as requirements (excluding already required)
$availStmt = $conn->prepare("
    SELECT c.Course_ID, c.Course_Name, c.Course_Type, c.Course_Credits, d.Department_Name
    FROM COURSES c
    JOIN DEPARTMENT d ON c.Department_ID = d.Department_ID
    WHERE c.Course_ID NOT IN (
        SELECT Course_ID FROM MINOR_REQUIREMENTS WHERE Minor_ID = ?
    )
    ORDER BY c.Department_ID ASC, c.Course_ID ASC
");
$availStmt->bind_param("i", $minor_id);
$availStmt->execute();
$availableCourses = $availStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$availStmt->close();

$canWrite = ((int)($_SESSION['Security_Level'] ?? 1) >= 2);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Minor: <?= htmlspecialchars($minor['Minor_Name']) ?> | Lucid University Admin</title>
    <!-- Minimal Clean Bootstrap 5.3 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        <?= getProfileHoverCardStyles() ?>
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
                    <li class="nav-item"><a class="nav-link" href="dashboard.php">Dashboard</a></li>
                    <li class="nav-item"><a class="nav-link" href="create_user.php">Create User</a></li>
                    <li class="nav-item"><a class="nav-link" href="view_users.php">View Users</a></li>
                    <li class="nav-item"><a class="nav-link" href="create_course_section.php">Create Course Section</a></li>
                    <li class="nav-item"><a class="nav-link" href="semesters.php">Semesters</a></li>
                    <li class="nav-item"><a class="nav-link active" aria-current="page" href="majors_minors.php">Majors & Minors</a></li>
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

    <div class="container mb-5" style="max-width: 960px;">

        <!-- Breadcrumb / Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <a href="majors_minors.php" class="text-decoration-none text-secondary small">&larr; Back to Majors & Minors</a>
                <h2 class="fw-bold mb-1 mt-1">Configure Minor: <?= htmlspecialchars($minor['Minor_Name']) ?></h2>
                <div class="text-secondary small">Update minor attributes, required credits, and required concentration course curriculum.</div>
            </div>
            <div>
                <span class="badge bg-secondary border px-3 py-2">
                    Security Level: <?= htmlspecialchars((string)($_SESSION["Security_Level"] ?? '1')) ?>
                </span>
            </div>
        </div>

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

        <!-- Minor Details Card -->
        <div class="card shadow-sm border-0 rounded-3 mb-4">
            <div class="card-header bg-white py-3 border-0">
                <h5 class="fw-bold mb-0">1. Minor Track Attributes</h5>
            </div>
            <div class="card-body p-4 pt-0">
                <form action="edit_minor_process.php" method="POST">
                    <input type="hidden" name="action" value="update_metadata">
                    <input type="hidden" name="minor_id" value="<?= (int)$minor['Minor_ID'] ?>">

                    <div class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Minor ID</label>
                            <input type="text" class="form-control font-monospace" value="<?= (int)$minor['Minor_ID'] ?>" readonly>
                        </div>
                        <div class="col-md-5">
                            <label for="minor_name" class="form-label fw-semibold">Minor Name</label>
                            <input type="text" class="form-control" id="minor_name" name="minor_name" 
                                   value="<?= htmlspecialchars($minor['Minor_Name']) ?>" <?= $canWrite ? 'required' : 'readonly' ?> maxlength="100">
                        </div>
                        <div class="col-md-4">
                            <label for="department_id" class="form-label fw-semibold">Host Department</label>
                            <?php if ($canWrite): ?>
                                <select class="form-select" id="department_id" name="department_id" required>
                                    <?php foreach ($departments as $dept): ?>
                                        <option value="<?= (int)$dept['Department_ID'] ?>" <?= ((int)$dept['Department_ID'] === (int)$minor['Department_ID']) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($dept['Department_Name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            <?php else: ?>
                                <input type="text" class="form-control" value="<?= htmlspecialchars($minor['Department_Name']) ?>" readonly>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-6">
                            <label for="no_credits_required" class="form-label fw-semibold">Total Required Credits</label>
                            <input type="number" class="form-control font-monospace" id="no_credits_required" name="no_credits_required" 
                                   value="<?= (int)$minor['No_Credits_Required'] ?>" <?= $canWrite ? 'required min="1"' : 'readonly' ?>>
                            <div class="form-text small">Statutory minor credit requirement (typically 15 to 20 credits).</div>
                        </div>
                        <div class="col-md-6 d-flex align-items-end">
                            <?php if ($canWrite): ?>
                                <button type="submit" class="btn btn-success w-100 fw-semibold">Save Minor Attributes</button>
                            <?php else: ?>
                                <button type="button" class="btn btn-secondary w-100" disabled>Read-Only Access</button>
                            <?php endif; ?>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Curriculum Requirements Card -->
        <div class="card shadow-sm border-0 rounded-3 mb-4">
            <div class="card-header bg-white py-3 border-0 d-flex justify-content-between align-items-center">
                <div>
                    <h5 class="fw-bold mb-0">2. Required Minor Curriculum (Minor Requirements)</h5>
                    <div class="text-secondary small">
                        Courses required for completion of this minor. Total enrolled curriculum: 
                        <strong class="text-success"><?= $totalReqCredits ?> credits</strong> across <strong><?= count($requiredCourses) ?> courses</strong>.
                    </div>
                </div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-4">Course ID</th>
                                <th>Course Title</th>
                                <th>Type</th>
                                <th>Department</th>
                                <th class="text-center">Credits</th>
                                <th class="text-center">Min. Grade</th>
                                <?php if ($canWrite): ?>
                                    <th class="text-end pe-4">Action</th>
                                <?php endif; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($requiredCourses)): ?>
                                <tr>
                                    <td colspan="<?= $canWrite ? '7' : '6' ?>" class="text-center py-4 text-muted">
                                        No specific required courses configured for this minor yet.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($requiredCourses as $rc): ?>
                                    <tr>
                                        <td class="ps-4 font-monospace fw-bold text-dark">
                                            <?= htmlspecialchars($rc['Course_ID']) ?>
                                        </td>
                                        <td><?= htmlspecialchars($rc['Course_Name']) ?></td>
                                        <td>
                                            <span class="badge bg-light text-dark border"><?= htmlspecialchars($rc['Course_Type']) ?></span>
                                        </td>
                                        <td class="small text-secondary"><?= htmlspecialchars($rc['Department_Name']) ?></td>
                                        <td class="text-center font-monospace fw-semibold"><?= (int)$rc['Course_Credits'] ?></td>
                                        <td class="text-center font-monospace">
                                            <span class="badge bg-success-subtle text-success border border-success-subtle px-2">
                                                Grade <?= htmlspecialchars($rc['Min_Grade']) ?>
                                            </span>
                                        </td>
                                        <?php if ($canWrite): ?>
                                            <td class="text-end pe-4">
                                                <form action="edit_minor_process.php" method="POST" class="d-inline" onsubmit="return confirm('Remove course <?= htmlspecialchars($rc['Course_ID']) ?> from this minor requirements?');">
                                                    <input type="hidden" name="action" value="remove_course">
                                                    <input type="hidden" name="minor_id" value="<?= (int)$minor['Minor_ID'] ?>">
                                                    <input type="hidden" name="course_id" value="<?= htmlspecialchars($rc['Course_ID']) ?>">
                                                    <button type="submit" class="btn btn-outline-danger btn-sm py-0 px-2" title="Remove course requirement">
                                                        &times; Remove
                                                    </button>
                                                </form>
                                            </td>
                                        <?php endif; ?>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <?php if ($canWrite): ?>
                <!-- Add Course Requirement Form -->
                <div class="card-footer bg-light p-4 border-top">
                    <h6 class="fw-bold mb-3">+ Add Course to Minor Requirements</h6>
                    <form action="edit_minor_process.php" method="POST" class="row g-2 align-items-center">
                        <input type="hidden" name="action" value="add_course">
                        <input type="hidden" name="minor_id" value="<?= (int)$minor['Minor_ID'] ?>">

                        <div class="col-md-6">
                            <select name="course_id" class="form-select form-select-sm" required>
                                <option value="">Select Course from Catalog...</option>
                                <?php foreach ($availableCourses as $ac): ?>
                                    <option value="<?= htmlspecialchars($ac['Course_ID']) ?>">
                                        <?= htmlspecialchars($ac['Course_ID']) ?>: <?= htmlspecialchars($ac['Course_Name']) ?> (<?= (int)$ac['Course_Credits'] ?> cr - <?= htmlspecialchars($ac['Department_Name']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <select name="min_grade" class="form-select form-select-sm" required>
                                <option value="C" selected>Min Grade: C</option>
                                <option value="B">Min Grade: B</option>
                                <option value="A">Min Grade: A</option>
                                <option value="D">Min Grade: D</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <button type="submit" class="btn btn-success btn-sm w-100 fw-semibold">
                                + Add Requirement
                            </button>
                        </div>
                    </form>
                </div>
            <?php endif; ?>
        </div>

    </div>

    <!-- Bootstrap 5.3 JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
