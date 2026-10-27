<?php
/**
 * Lucid University Course Registration System
 * Admin Portal - Create New Course Section
 *
 * Allows administrators to schedule new course sections for future semesters
 * with full validation of faculty load, time conflicts, department matching,
 * room availability, and class capacity constraints.
 */

require_once 'admin_guard.php';
require_once '../db_connect.php';
require_once '../profile_hover_card.php';

$admin_id = (int)$userID;

$fullName = $_SESSION["User"]["User_FName"] ?? "Administrator";
if (!empty($_SESSION["User"]["User_MName"])) {
    $fullName .= " " . $_SESSION["User"]["User_MName"];
}
if (!empty($_SESSION["User"]["User_LName"])) {
    $fullName .= " " . $_SESSION["User"]["User_LName"];
}
$fullName = strip_tags($fullName);

$successMessage = $_SESSION["success_message"] ?? "";
$errorMessage   = $_SESSION["error_message"] ?? "";
$newSectionData = $_SESSION["new_section_data"] ?? null;

unset($_SESSION["success_message"], $_SESSION["error_message"], $_SESSION["new_section_data"]);

// 1. Fetch Future Semesters (Start_Date > CURRENT_DATE())
$semStmt = $conn->prepare("
    SELECT Semester_ID, Semester_Name, Semester_Year, Start_Date, End_Date
    FROM SEMESTER
    WHERE Start_Date > CURRENT_DATE()
    ORDER BY Start_Date ASC
");
$semStmt->execute();
$futureSemesters = $semStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$semStmt->close();

// 2. Fetch All Courses with Department Name
$courseStmt = $conn->prepare("
    SELECT c.Course_ID, c.Course_Name, c.Department_ID, d.Department_Name
    FROM COURSES c
    JOIN DEPARTMENT d ON c.Department_ID = d.Department_ID
    ORDER BY c.Course_ID ASC
");
$courseStmt->execute();
$courses = $courseStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$courseStmt->close();

// 3. Fetch Faculty Members with Department and Type
$facultyStmt = $conn->prepare("
    SELECT f.Faculty_ID, f.Faculty_Type, u.User_FName, u.User_MName, u.User_LName,
           d.Department_ID, d.Department_Name
    FROM FACULTY f
    JOIN `USER` u ON f.Faculty_ID = u.User_ID
    LEFT JOIN DEPARTMENT_ROSTER dr ON f.Faculty_ID = dr.Faculty_ID
    LEFT JOIN DEPARTMENT d ON dr.Department_ID = d.Department_ID
    ORDER BY u.User_LName ASC, u.User_FName ASC
");
$facultyStmt->execute();
$facultyMembers = $facultyStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$facultyStmt->close();

// 4. Fetch Active Time Slots
$tsStmt = $conn->prepare("
    SELECT ts.Time_Slot_ID, ts.Time_Slot_Day, p.Start_Time, p.End_Time
    FROM TIME_SLOT ts
    JOIN PERIOD p ON ts.Period_ID = p.Period_ID
    WHERE ts.Time_Slot_ID IN (101, 102, 103, 104, 105, 106, 107, 108, 109, 110, 111, 112, 113, 114, 115, 116, 10, 20)
    ORDER BY ts.Time_Slot_ID ASC
");
$tsStmt->execute();
$timeSlots = $tsStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$tsStmt->close();

// 5. Fetch Classrooms (Lecture & Lab)
$roomStmt = $conn->prepare("
    SELECT r.Room_ID, r.Room_NO, r.Room_Type, b.Building_Name,
           COALESCE(l.Lecture_Seats, lab.Lab_Stations, 0) AS Capacity
    FROM ROOM r
    JOIN BUILDING b ON r.Building_ID = b.Building_ID
    LEFT JOIN LECTURE l ON r.Room_ID = l.Lecture_ID
    LEFT JOIN LAB lab ON r.Room_ID = lab.Lab_ID
    WHERE r.Room_Type IN ('Lecture', 'Lab')
    ORDER BY r.Room_ID ASC
");
$roomStmt->execute();
$rooms = $roomStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$roomStmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Course Section | Lucid University Admin</title>
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
                        <a class="nav-link active" aria-current="page" href="create_course_section.php">Create Course Section</a>
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

    <div class="container mb-5">

        <!-- Page Header -->
        <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
            <div>
                <h2 class="fw-bold mb-1">Create Course Section</h2>
                <div class="text-secondary">
                    Schedule a new section offering for an upcoming academic term.
                </div>
            </div>
            <div>
                <a href="dashboard.php" class="btn btn-outline-secondary btn-sm">&larr; Back to Dashboard</a>
            </div>
        </div>

        <!-- Success Flash Alert -->
        <?php if ($successMessage): ?>
            <div class="alert alert-success alert-dismissible fade show shadow-sm" role="alert">
                <h5 class="alert-heading fw-bold mb-1">Section Created Successfully</h5>
                <p class="mb-2"><?= htmlspecialchars($successMessage, ENT_QUOTES, 'UTF-8') ?></p>
                <?php if ($newSectionData): ?>
                    <hr>
                    <h6 class="fw-bold mb-2">New Section Summary:</h6>
                    <ul class="mb-0 small">
                        <?php foreach ($newSectionData as $label => $val): ?>
                            <li>
                                <strong><?= htmlspecialchars(ucwords(str_replace('_', ' ', $label)), ENT_QUOTES, 'UTF-8') ?>:</strong>
                                <?= htmlspecialchars((string)$val, ENT_QUOTES, 'UTF-8') ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <!-- Error Flash Alert -->
        <?php if ($errorMessage): ?>
            <div class="alert alert-danger alert-dismissible fade show shadow-sm" role="alert">
                <strong>Schedule Error:</strong> <?= htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8') ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <!-- Creation Form Card -->
        <div class="card shadow-sm border-0">
            <div class="card-body p-4 bg-white rounded-3">
                <form action="create_course_section_process.php" method="POST">

                    <h5 class="fw-bold text-dark border-bottom pb-2 mb-3">Course Section Parameters</h5>

                    <div class="row g-3 mb-4">
                        <!-- 1. Semester Selection (Future Only) -->
                        <div class="col-md-6">
                            <label for="semester_id" class="form-label small fw-bold">Target Semester <span class="text-danger">*</span></label>
                            <select name="semester_id" id="semester_id" class="form-select" required>
                                <option value="">Select Modifiable Future Semester</option>
                                <?php foreach ($futureSemesters as $sem): ?>
                                    <option value="<?= (int)$sem['Semester_ID'] ?>">
                                        <?= htmlspecialchars($sem['Semester_Name'] . ' ' . $sem['Semester_Year'] . ' (Starts: ' . $sem['Start_Date'] . ')', ENT_QUOTES, 'UTF-8') ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="form-text small">Only future semesters with start date ahead of today can be scheduled.</div>
                        </div>

                        <!-- 2. Course Selection -->
                        <div class="col-md-6">
                            <label for="course_id" class="form-label small fw-bold">Course Offering <span class="text-danger">*</span></label>
                            <select name="course_id" id="course_id" class="form-select" required>
                                <option value="">Select Academic Course</option>
                                <?php foreach ($courses as $c): ?>
                                    <option value="<?= htmlspecialchars($c['Course_ID'], ENT_QUOTES, 'UTF-8') ?>">
                                        <?= htmlspecialchars($c['Course_ID'] . ' - ' . $c['Course_Name'] . ' (' . $c['Department_Name'] . ')', ENT_QUOTES, 'UTF-8') ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="form-text small">Section numbers are automatically incremented based on existing offerings.</div>
                        </div>

                        <!-- 3. Faculty Instructor -->
                        <div class="col-md-6">
                            <label for="faculty_id" class="form-label small fw-bold">Instructor / Faculty <span class="text-danger">*</span></label>
                            <select name="faculty_id" id="faculty_id" class="form-select" required>
                                <option value="">Select Faculty Instructor</option>
                                <?php foreach ($facultyMembers as $f): 
                                    $fName = trim($f['User_FName'] . ' ' . ($f['User_MName'] ? $f['User_MName'] . ' ' : '') . $f['User_LName']);
                                ?>
                                    <option value="<?= (int)$f['Faculty_ID'] ?>">
                                        <?= htmlspecialchars($fName . ' (' . $f['Faculty_Type'] . ' - ' . ($f['Department_Name'] ?? 'No Dept') . ')', ENT_QUOTES, 'UTF-8') ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="form-text small">Must belong to the course's department. Max load: Part-Time = 1 section, Full-Time = 2 sections.</div>
                        </div>

                        <!-- 4. Time Slot -->
                        <div class="col-md-6">
                            <label for="time_slot_id" class="form-label small fw-bold">Day &amp; Period Time Slot <span class="text-danger">*</span></label>
                            <select name="time_slot_id" id="time_slot_id" class="form-select" required>
                                <option value="">Select Bell Time Slot</option>
                                <?php foreach ($timeSlots as $ts): 
                                    $startTime = date('g:i A', strtotime($ts['Start_Time']));
                                    $endTime = date('g:i A', strtotime($ts['End_Time']));
                                ?>
                                    <option value="<?= (int)$ts['Time_Slot_ID'] ?>">
                                        Slot <?= (int)$ts['Time_Slot_ID'] ?>: <?= htmlspecialchars($ts['Time_Slot_Day'] . ' ' . $startTime . ' - ' . $endTime, ENT_QUOTES, 'UTF-8') ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="form-text small">Validated against instructor and room schedules to prevent double-booking.</div>
                        </div>

                        <!-- 5. Room Assignment -->
                        <div class="col-md-6">
                            <label for="room_id" class="form-label small fw-bold">Classroom / Facility <span class="text-danger">*</span></label>
                            <select name="room_id" id="room_id" class="form-select" required>
                                <option value="">Select Classroom Room</option>
                                <?php foreach ($rooms as $r): ?>
                                    <option value="<?= htmlspecialchars($r['Room_ID'], ENT_QUOTES, 'UTF-8') ?>">
                                        <?= htmlspecialchars($r['Room_ID'] . ' - ' . $r['Building_Name'] . ' ' . $r['Room_NO'] . ' (' . $r['Room_Type'] . ($r['Capacity'] > 0 ? ', Cap: ' . $r['Capacity'] : '') . ')', ENT_QUOTES, 'UTF-8') ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="form-text small">Only academic lecture halls and instructional labs are available for course scheduling.</div>
                        </div>

                        <!-- 6. Available Seats -->
                        <div class="col-md-6">
                            <label for="available_seats" class="form-label small fw-bold">Available Seats Capacity <span class="text-danger">*</span></label>
                            <input type="number" name="available_seats" id="available_seats" class="form-control" min="5" max="10" value="10" required>
                            <div class="form-text small">System specification rule: initial section capacity must be between 5 and 10 seats.</div>
                        </div>
                    </div>

                    <div class="d-flex gap-2 justify-content-end border-top pt-3">
                        <a href="dashboard.php" class="btn btn-outline-secondary">Cancel</a>
                        <button type="submit" class="btn btn-success px-4">
                            Create Course Section
                        </button>
                    </div>

                </form>
            </div>
        </div>

    </div>

    <!-- Bootstrap 5.3 JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
