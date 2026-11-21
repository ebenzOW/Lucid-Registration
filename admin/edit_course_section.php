<?php
/**
 * Lucid University Course Registration System
 * Admin Portal - Edit Course Section
 *
 * Allows Level 2+ administrators to update section assignments (Faculty, Room,
 * Time Slot, Seats, and Status) subject to semester lifecycle rules:
 * - Past Semesters (< 202608 or 'Past'): Readonly, no edits allowed.
 * - Current Semester (Fall 2026 = 202608): Instructor & Room only.
 * - Future Semesters (> 202608): Full editing (Instructor, Room, Time Slot, Seats, Status).
 */

require_once 'admin_guard.php';
require_once '../db_connect.php';
require_once __DIR__ . '/../includes/auth_helpers.php';
require_once __DIR__ . '/../includes/ui_helpers.php';
require_once __DIR__ . '/../includes/faculty_helpers.php';
require_once __DIR__ . '/../includes/schedule_helpers.php';

$admin_id = (int)$userID;
$secLevel = (int)($_SESSION["Security_Level"] ?? 1);

$successMessage = $_SESSION["success_message"] ?? "";
$errorMessage   = $_SESSION["error_message"] ?? "";
unset($_SESSION["success_message"], $_SESSION["error_message"]);

$crn = isset($_GET['crn']) ? filter_var($_GET['crn'], FILTER_VALIDATE_INT) : null;
if (!$crn) {
    $_SESSION['error_message'] = "Invalid Section CRN specified.";
    header("Location: master_schedule.php");
    exit;
}

// 1. Fetch Section Details
$stmt = $conn->prepare("
    SELECT cs.CRN, cs.Course_ID, cs.Section_Number, cs.Semester_ID, cs.Faculty_ID,
           cs.Room_ID, cs.Time_Slot_ID, cs.Available_seats, cs.Status,
           c.Course_Name, c.Course_Credits, c.Course_Type, c.Department_ID,
           d.Department_Name,
           s.Semester_Name, s.Semester_Year, s.Start_Date, s.End_Date,
           ts.Time_Slot_Day, p.Start_Time, p.End_Time,
           b.Building_Name, r.Room_NO,
           COALESCE(CONCAT(u_fac.User_FName, ' ', u_fac.User_LName), 'Staff') AS Instructor_Name,
           (SELECT COUNT(*) FROM ENROLLMENT WHERE CRN = cs.CRN) AS Enrolled_Count
    FROM COURSE_SECTIONS cs
    JOIN COURSES c ON cs.Course_ID = c.Course_ID
    JOIN DEPARTMENT d ON c.Department_ID = d.Department_ID
    JOIN SEMESTER s ON cs.Semester_ID = s.Semester_ID
    JOIN TIME_SLOT ts ON cs.Time_Slot_ID = ts.Time_Slot_ID
    JOIN PERIOD p ON ts.Period_ID = p.Period_ID
    JOIN ROOM r ON cs.Room_ID = r.Room_ID
    JOIN BUILDING b ON r.Building_ID = b.Building_ID
    LEFT JOIN FACULTY f ON cs.Faculty_ID = f.Faculty_ID
    LEFT JOIN `USER` u_fac ON f.Faculty_ID = u_fac.User_ID
    WHERE cs.CRN = ?
");
$stmt->bind_param("i", $crn);
$stmt->execute();
$sec = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$sec) {
    $_SESSION['error_message'] = "Course Section CRN {$crn} not found.";
    header("Location: master_schedule.php");
    exit;
}

$semId = (int)$sec['Semester_ID'];
$isPast = ($semId < 202608 || ($sec['Status'] ?? '') === 'Past');
$isCurrent = ($semId === 202608 && !$isPast);
$isFuture = ($semId > 202608);
$enrolledCount = (int)$sec['Enrolled_Count'];

// Fetch Eligible Faculty & Rooms for current assignment
$eligibleFaculty = getEligibleProfessorsForSection(
    $conn,
    $semId,
    (int)$sec['Department_ID'],
    (int)$sec['Time_Slot_ID'],
    $crn,
    (int)$sec['Faculty_ID']
);

$eligibleRooms = getEligibleRoomsForSection(
    $conn,
    $semId,
    (int)$sec['Time_Slot_ID'],
    $enrolledCount,
    $crn,
    (string)$sec['Room_ID']
);

// Fetch All Time Slots for Future editing
$filterOpts = getMasterScheduleFilterOptions($conn);
$timeSlots = $filterOpts['time_slots'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Section CRN <?= $crn ?> | Lucid University Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

    <?= renderAdminNavbar($conn, $admin_id, 'master_schedule.php') ?>

    <div class="container mb-5" style="max-width: 900px;">

        <!-- Header -->
        <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
            <div>
                <h2 class="fw-bold mb-1">Edit Course Section</h2>
                <div class="text-secondary small">
                    CRN: <strong><?= $crn ?></strong> &bull;
                    <?= htmlspecialchars($sec['Course_ID'] . ' - ' . $sec['Course_Name'], ENT_QUOTES, 'UTF-8') ?>
                    (Sec <?= htmlspecialchars((string)$sec['Section_Number'], ENT_QUOTES, 'UTF-8') ?>)
                </div>
            </div>
            <div>
                <a href="master_schedule.php?semester=<?= $semId ?>&crn=<?= $crn ?>" class="btn btn-outline-secondary btn-sm">
                    &larr; Back to Master Schedule
                </a>
            </div>
        </div>

        <!-- Flash Alerts -->
        <?php if ($successMessage): ?>
            <div class="alert alert-success alert-dismissible fade show shadow-sm" role="alert">
                <?= htmlspecialchars($successMessage, ENT_QUOTES, 'UTF-8') ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <?php if ($errorMessage): ?>
            <div class="alert alert-danger alert-dismissible fade show shadow-sm" role="alert">
                <?= htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8') ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <?php if ($secLevel < 2): ?>
            <div class="alert alert-warning shadow-sm">
                <strong>Read-Only Access:</strong> Your account is Security Level 1 (Read-Only). You do not have permission to modify course sections.
            </div>
        <?php endif; ?>

        <?php if ($isPast): ?>
            <div class="alert alert-secondary shadow-sm">
                <strong>Historical Section:</strong> This section belongs to a concluded term (<?= htmlspecialchars($sec['Semester_Name'] . ' ' . $sec['Semester_Year'], ENT_QUOTES, 'UTF-8') ?>) and cannot be edited.
            </div>
        <?php elseif ($isCurrent): ?>
            <div class="alert alert-info shadow-sm">
                <strong>Active Term Lifecycle:</strong> Fall 2026 is currently in session. Course schedule time slot and capacity are locked; only Instructor and Room re-assignments are permitted.
            </div>
        <?php elseif ($isFuture): ?>
            <div class="alert alert-primary shadow-sm">
                <strong>Upcoming Term:</strong> <?= htmlspecialchars($sec['Semester_Name'] . ' ' . $sec['Semester_Year'], ENT_QUOTES, 'UTF-8') ?> has not started. Full editing of Instructor, Room, Time Slot, Seats, and Status is permitted.
            </div>
        <?php endif; ?>

        <!-- Edit Form Card -->
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-header bg-white py-3">
                <h5 class="mb-0 fw-bold">Section Configuration &amp; Assignments</h5>
            </div>
            <div class="card-body p-4">
                <form method="POST" action="edit_course_section_process.php">
                    <input type="hidden" name="crn" value="<?= $crn ?>">
                    <input type="hidden" name="semester_id" value="<?= $semId ?>">

                    <!-- Readonly Section Summary Info -->
                    <div class="row g-3 mb-4 p-3 bg-light rounded border">
                        <div class="col-md-3">
                            <label class="form-label small text-muted text-uppercase fw-semibold mb-0">CRN</label>
                            <div class="fw-bold fs-5 text-dark"><?= $crn ?></div>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small text-muted text-uppercase fw-semibold mb-0">Term</label>
                            <div class="fw-semibold text-dark"><?= htmlspecialchars($sec['Semester_Name'] . ' ' . $sec['Semester_Year'], ENT_QUOTES, 'UTF-8') ?></div>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small text-muted text-uppercase fw-semibold mb-0">Course</label>
                            <div class="fw-semibold text-dark"><?= htmlspecialchars($sec['Course_ID'], ENT_QUOTES, 'UTF-8') ?> (<?= (int)$sec['Course_Credits'] ?> Cr)</div>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small text-muted text-uppercase fw-semibold mb-0">Enrolled Students</label>
                            <div class="fw-bold text-success fs-5"><?= $enrolledCount ?> Enrolled</div>
                        </div>
                    </div>

                    <div class="row g-3">
                        <!-- 1. Assigned Instructor -->
                        <div class="col-md-6">
                            <label for="faculty_id" class="form-label fw-bold small text-muted">
                                Assigned Instructor <span class="text-danger">*</span>
                            </label>
                            <?php if ($isPast || $secLevel < 2): ?>
                                <input type="text" class="form-control" value="<?= htmlspecialchars($sec['Instructor_Name'], ENT_QUOTES, 'UTF-8') ?>" disabled>
                            <?php else: ?>
                                <select name="faculty_id" id="faculty_id" class="form-select" required>
                                    <?php foreach ($eligibleFaculty as $fac): 
                                        $fId = (int)$fac['Faculty_ID'];
                                        $selected = ($fId === (int)$sec['Faculty_ID']) ? 'selected' : '';
                                        $label = $fac['full_name'] . ' (' . $fac['Faculty_Type'] . ' - Load: ' . $fac['current_load'] . '/' . $fac['max_load'] . ')';
                                    ?>
                                        <option value="<?= $fId ?>" <?= $selected ?>>
                                            <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="form-text small">Only instructors from <?= htmlspecialchars($sec['Department_Name'], ENT_QUOTES, 'UTF-8') ?> with open load capacity are listed.</div>
                            <?php endif; ?>
                        </div>

                        <!-- 2. Assigned Room -->
                        <div class="col-md-6">
                            <label for="room_id" class="form-label fw-bold small text-muted">
                                Assigned Room <span class="text-danger">*</span>
                            </label>
                            <?php if ($isPast || $secLevel < 2): ?>
                                <input type="text" class="form-control" value="<?= htmlspecialchars($sec['Building_Name'] . ' ' . $sec['Room_NO'], ENT_QUOTES, 'UTF-8') ?>" disabled>
                            <?php else: ?>
                                <select name="room_id" id="room_id" class="form-select" required>
                                    <?php foreach ($eligibleRooms as $rm): 
                                        $rId = $rm['Room_ID'];
                                        $selected = (strcasecmp($rId, (string)$sec['Room_ID']) === 0) ? 'selected' : '';
                                        $label = $rm['Building_Name'] . ' Room ' . $rm['Room_NO'] . ' (' . $rm['Room_Type'] . ' - Cap: ' . $rm['Room_Capacity'] . ')';
                                    ?>
                                        <option value="<?= htmlspecialchars($rId, ENT_QUOTES, 'UTF-8') ?>" <?= $selected ?>>
                                            <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="form-text small">Only conflict-free rooms with capacity &gt;= <?= $enrolledCount ?> are listed.</div>
                            <?php endif; ?>
                        </div>

                        <!-- 3. Time Slot -->
                        <div class="col-md-6">
                            <label for="time_slot_id" class="form-label fw-bold small text-muted">
                                Scheduled Time Slot <span class="text-danger">*</span>
                            </label>
                            <?php if (!$isFuture || $secLevel < 2): ?>
                                <input type="text" class="form-control" value="<?= htmlspecialchars(formatSchedule($sec['Time_Slot_Day'], $sec['Start_Time'], $sec['End_Time']), ENT_QUOTES, 'UTF-8') ?>" disabled>
                                <div class="form-text small text-muted">Locked for active or past terms.</div>
                            <?php else: ?>
                                <select name="time_slot_id" id="time_slot_id" class="form-select" required>
                                    <?php foreach ($timeSlots as $ts): 
                                        $tsId = (int)$ts['Time_Slot_ID'];
                                        $selected = ($tsId === (int)$sec['Time_Slot_ID']) ? 'selected' : '';
                                        $tsLabel = formatSchedule($ts['Time_Slot_Day'], $ts['Start_Time'], $ts['End_Time']);
                                    ?>
                                        <option value="<?= $tsId ?>" <?= $selected ?>>
                                            <?= htmlspecialchars($tsLabel, ENT_QUOTES, 'UTF-8') ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            <?php endif; ?>
                        </div>

                        <!-- 4. Available Seats -->
                        <div class="col-md-3">
                            <label for="available_seats" class="form-label fw-bold small text-muted">
                                Available Seats <span class="text-danger">*</span>
                            </label>
                            <?php if (!$isFuture || $secLevel < 2): ?>
                                <input type="number" class="form-control" value="<?= (int)$sec['Available_seats'] ?>" disabled>
                                <div class="form-text small text-muted">Locked for active term.</div>
                            <?php else: ?>
                                <input type="number" name="available_seats" id="available_seats" class="form-control"
                                       value="<?= (int)$sec['Available_seats'] ?>" min="5" max="10" required>
                                <div class="form-text small">Policy: between 5 and 10 seats.</div>
                            <?php endif; ?>
                        </div>

                        <!-- 5. Status -->
                        <div class="col-md-3">
                            <label for="status" class="form-label fw-bold small text-muted">
                                Section Status <span class="text-danger">*</span>
                            </label>
                            <?php if (!$isFuture || $secLevel < 2): ?>
                                <input type="text" class="form-control" value="<?= htmlspecialchars($sec['Status'] ?? 'Active', ENT_QUOTES, 'UTF-8') ?>" disabled>
                            <?php else: ?>
                                <select name="status" id="status" class="form-select" required>
                                    <option value="Active" <?= ($sec['Status'] ?? 'Active') === 'Active' ? 'selected' : '' ?>>Active</option>
                                    <option value="Cancelled" <?= ($sec['Status'] ?? '') === 'Cancelled' ? 'selected' : '' ?>>Cancelled</option>
                                </select>
                            <?php endif; ?>
                        </div>
                    </div>

                    <?php if (!$isPast && $secLevel >= 2): ?>
                        <hr class="my-4">
                        <div class="d-flex justify-content-between align-items-center">
                            <a href="master_schedule.php?semester=<?= $semId ?>&crn=<?= $crn ?>" class="btn btn-outline-secondary">
                                Cancel
                            </a>
                            <button type="submit" class="btn btn-primary px-4 fw-semibold">
                                Save Section Changes
                            </button>
                        </div>
                    <?php endif; ?>
                </form>
            </div>
        </div>

    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
