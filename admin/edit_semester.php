<?php
/**
 * Lucid University Course Registration System
 * Admin Portal - Edit / Create Semester and Academic Deadlines
 *
 * Implements semester configuration and calendar deadline updates.
 * Enforces SRS constraints:
 * 1. Read-only admins (Security Level 1) cannot mutate records.
 * 2. Past semester schedules cannot be altered.
 * 3. Start_Date must precede End_Date.
 * 4. Academic deadlines must adhere to logical chronological sequence.
 */

require_once 'admin_guard.php';
require_once '../db_connect.php';
require_once __DIR__ . '/../includes/academic_helpers.php';
require_once '../profile_hover_card.php';

$admin_id = (int)$userID;

$successMessage = $_SESSION["success_message"] ?? "";
$errorMessage   = $_SESSION["error_message"] ?? "";
unset($_SESSION["success_message"], $_SESSION["error_message"]);

$semester_id = isset($_GET['id']) ? (int)$_GET['id'] : null;
$isNew = empty($semester_id);

$semester = null;
$infoRelation = null;

if (!$isNew) {
    // Fetch semester
    $stmt = $conn->prepare("SELECT * FROM SEMESTER WHERE Semester_ID = ?");
    $stmt->bind_param("i", $semester_id);
    $stmt->execute();
    $semester = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$semester) {
        $_SESSION['error_message'] = "Semester ID {$semester_id} was not found in the system.";
        header("Location: semesters.php");
        exit;
    }

    // Fetch information relation
    $stmt = $conn->prepare("SELECT * FROM INFORMATION_RELATION WHERE Semester_ID = ?");
    $stmt->bind_param("i", $semester_id);
    $stmt->execute();
    $infoRelation = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

$today = date('Y-m-d');
$isPast = false;
$isOngoing = false;

if ($semester) {
    $isPast = ($today > $semester['End_Date']);
    $isOngoing = ($today >= $semester['Start_Date'] && $today <= $semester['End_Date']);
}

$readOnlyMode = $isPast || ((int)($_SESSION['Security_Level'] ?? 1) < 2);

// Calculate suggested next semester ID if new
$defaultYear = (int)date('Y') + 1;
$suggestedID = $defaultYear * 100 + 1; // e.g. 202701
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $isNew ? 'Configure New Semester' : 'Edit Academic Term' ?> | Lucid University Admin</title>
    <!-- Minimal Clean Bootstrap 5.3 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        <?= getProfileHoverCardStyles() ?>
    </style>
</head>
<body class="bg-light">

    <?= renderAdminNavbar($conn, $admin_id, basename($_SERVER['PHP_SELF'])) ?>

    <div class="container mb-5" style="max-width: 900px;">

        <!-- Breadcrumb / Back button -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <a href="semesters.php" class="text-decoration-none text-secondary small">&larr; Back to Semesters List</a>
                <h2 class="fw-bold mb-1 mt-1">
                    <?= $isNew ? 'Add New Academic Semester' : 'Update Semester & Calendar Deadlines' ?>
                </h2>
                <div class="text-secondary small">
                    <?= $isNew ? 'Provision a new term and configure its academic calendar milestones.' : 'Adjust term duration and institutional deadlines.' ?>
                </div>
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

        <?php if ($isPast): ?>
            <div class="alert alert-warning shadow-sm border-0" role="alert">
                <h6 class="fw-bold mb-1">&#9888; Past Semester Constraint Active</h6>
                <div class="small">
                    This semester concluded on <strong><?= htmlspecialchars($semester['End_Date']) ?></strong>. Per university SRS safety constraints, past semester schedules and information relations cannot be mutated. Fields are displayed in read-only mode.
                </div>
            </div>
        <?php elseif ($isOngoing): ?>
            <div class="alert alert-info shadow-sm border-0" role="alert">
                <h6 class="fw-bold mb-1">&#8505; Active Semester Operational Notice</h6>
                <div class="small">
                    This semester is currently in progress (Start: <?= htmlspecialchars($semester['Start_Date']) ?> &ndash; End: <?= htmlspecialchars($semester['End_Date']) ?>). You may calibrate remaining active deadlines, but term start and concluded milestones must remain intact.
                </div>
            </div>
        <?php endif; ?>

        <!-- Form Card -->
        <div class="card shadow-sm border-0 rounded-3">
            <div class="card-body p-4 p-md-5">
                <form action="edit_semester_process.php" method="POST">
                    <input type="hidden" name="is_new" value="<?= $isNew ? '1' : '0' ?>">

                    <!-- Section 1: Semester Core Metadata -->
                    <h5 class="fw-bold text-dark border-bottom pb-2 mb-4">1. Semester Information</h5>
                    <div class="row g-3 mb-4">
                        <div class="col-md-4">
                            <label for="semester_id" class="form-label fw-semibold">Semester ID</label>
                            <input type="number" class="form-control font-monospace" id="semester_id" name="semester_id" 
                                   value="<?= htmlspecialchars((string)($semester['Semester_ID'] ?? $suggestedID)) ?>" 
                                   <?= (!$isNew || $readOnlyMode) ? 'readonly' : 'required' ?> min="100000" max="999999">
                            <div class="form-text small">Standard format: Year + Month (e.g. 202701 for Spring, 202708 for Fall).</div>
                        </div>

                        <div class="col-md-4">
                            <label for="semester_name" class="form-label fw-semibold">Term Name</label>
                            <?php if ($readOnlyMode): ?>
                                <input type="text" class="form-control" value="<?= htmlspecialchars($semester['Semester_Name'] ?? '') ?>" readonly>
                            <?php else: ?>
                                <select class="form-select" id="semester_name" name="semester_name" required>
                                    <?php $curName = $semester['Semester_Name'] ?? 'Spring'; ?>
                                    <option value="Spring" <?= $curName === 'Spring' ? 'selected' : '' ?>>Spring</option>
                                    <option value="Fall" <?= $curName === 'Fall' ? 'selected' : '' ?>>Fall</option>
                                    <option value="Summer" <?= $curName === 'Summer' ? 'selected' : '' ?>>Summer</option>
                                    <option value="Winter" <?= $curName === 'Winter' ? 'selected' : '' ?>>Winter</option>
                                </select>
                            <?php endif; ?>
                        </div>

                        <div class="col-md-4">
                            <label for="semester_year" class="form-label fw-semibold">Academic Year</label>
                            <input type="number" class="form-control" id="semester_year" name="semester_year" 
                                   value="<?= htmlspecialchars((string)($semester['Semester_Year'] ?? $defaultYear)) ?>" 
                                   <?= $readOnlyMode ? 'readonly' : 'required' ?> min="2020" max="2050">
                        </div>

                        <div class="col-md-6">
                            <label for="start_date" class="form-label fw-semibold">Term Start Date</label>
                            <input type="date" class="form-control" id="start_date" name="start_date" 
                                   value="<?= htmlspecialchars($semester['Start_Date'] ?? '') ?>" 
                                   <?= $readOnlyMode ? 'readonly' : 'required' ?>>
                        </div>

                        <div class="col-md-6">
                            <label for="end_date" class="form-label fw-semibold">Term End Date</label>
                            <input type="date" class="form-control" id="end_date" name="end_date" 
                                   value="<?= htmlspecialchars($semester['End_Date'] ?? '') ?>" 
                                   <?= $readOnlyMode ? 'readonly' : 'required' ?>>
                            <div class="form-text small">Must be on or after the term start date.</div>
                        </div>
                    </div>

                    <!-- Section 2: Information Relation (Academic Deadlines) -->
                    <h5 class="fw-bold text-dark border-bottom pb-2 mb-4">2. Academic Calendar Deadlines (Information Relation)</h5>
                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <label for="last_day_reg" class="form-label fw-semibold">Last Day of Registration</label>
                            <input type="date" class="form-control" id="last_day_reg" name="last_day_registration" 
                                   value="<?= htmlspecialchars($infoRelation['Last_Day_Of_Registration'] ?? '') ?>" 
                                   <?= $readOnlyMode ? 'readonly' : 'required' ?>>
                            <div class="form-text small">Registration cutoff for students.</div>
                        </div>

                        <div class="col-md-6">
                            <label for="last_day_drop" class="form-label fw-semibold">Last Day to Drop Courses</label>
                            <input type="date" class="form-control" id="last_day_drop" name="last_day_dropping" 
                                   value="<?= htmlspecialchars($infoRelation['Last_Day_Of_Dropping_Course'] ?? '') ?>" 
                                   <?= $readOnlyMode ? 'readonly' : 'required' ?>>
                            <div class="form-text small">Add/drop period deadline without academic penalty.</div>
                        </div>

                        <div class="col-md-6">
                            <label for="midterm_grades" class="form-label fw-semibold">Date of Midterm Grade Recording</label>
                            <input type="date" class="form-control" id="midterm_grades" name="date_midterm_grades" 
                                   value="<?= htmlspecialchars($infoRelation['Date_Of_Midterm_Grade_Recording'] ?? '') ?>" 
                                   <?= $readOnlyMode ? 'readonly' : 'required' ?>>
                            <div class="form-text small">Faculty deadline to submit midterm marks.</div>
                        </div>

                        <div class="col-md-6">
                            <label for="final_grades" class="form-label fw-semibold">Date of Final Grade Recording</label>
                            <input type="date" class="form-control" id="final_grades" name="date_final_grades" 
                                   value="<?= htmlspecialchars($infoRelation['Date_Of_Final_Grades_Recording'] ?? '') ?>" 
                                   <?= $readOnlyMode ? 'readonly' : 'required' ?>>
                            <div class="form-text small">Final grading window closure date.</div>
                        </div>
                    </div>

                    <!-- Submission buttons -->
                    <div class="d-flex justify-content-between align-items-center pt-3 border-top">
                        <a href="semesters.php" class="btn btn-outline-secondary">Cancel</a>
                        <?php if (!$readOnlyMode): ?>
                            <button type="submit" class="btn btn-primary px-4 fw-semibold">
                                <?= $isNew ? 'Create Semester & Deadlines' : 'Save Changes' ?>
                            </button>
                        <?php else: ?>
                            <button type="button" class="btn btn-secondary px-4 fw-semibold" disabled>
                                Read-Only (Modifications Locked)
                            </button>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>

    </div>

    <!-- Bootstrap 5.3 JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
