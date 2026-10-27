<?php
/**
 * Lucid University Course Registration System
 * Standalone Master Course Schedule Search Portal
 *
 * Supports comprehensive search and filtering across all academic terms,
 * departments, instructors, time slots, locations, and seat availability.
 */

require_once 'student_guard.php';
$student_id = (int)$userID;

require_once '../db_connect.php';
require_once 'academic_helpers.php';
require_once '../profile_hover_card.php';

// 1. Fetch Student Profile
$profile = getStudentProfile($conn, $student_id);
if (!$profile) {
    die("Student profile record not found.");
}
$student_full_name = $profile['full_name'];

// 2. Fetch Dropdown Filter Options
$filterOptions = getMasterScheduleFilterOptions($conn);
$semesters   = $filterOptions['semesters'];
$departments = $filterOptions['departments'];
$timeSlots   = $filterOptions['time_slots'];

// 3. Determine Selected Semester (Default: Fall 2026 = 202608)
$selected_sem_id = isset($_GET['semester']) ? filter_var($_GET['semester'], FILTER_VALIDATE_INT) : null;
if (!$selected_sem_id) {
    $selected_sem_id = 202608; // Default to Fall 2026 Current Term
}

// Past semester flag: semesters before Fall 2026 (202608) are past semesters
$is_past_sem = ($selected_sem_id < 202608);

// Fetch semester metadata and registration window status
$semInfo = getSemesterInfo($conn, $selected_sem_id);
$semester_name = $semInfo ? ($semInfo['Semester_Name'] . ' ' . $semInfo['Semester_Year']) : "Term " . $selected_sem_id;
$is_reg_open   = $semInfo ? (bool)$semInfo['is_reg_open'] : false;

// 4. Extract Search Filter Inputs (credits defaults to 4)
$filterCrn        = isset($_GET['crn']) ? filter_var($_GET['crn'], FILTER_VALIDATE_INT) : null;
$filterDept       = isset($_GET['dept']) ? filter_var($_GET['dept'], FILTER_VALIDATE_INT) : null;
$filterSearch     = isset($_GET['search']) ? trim($_GET['search']) : '';
$filterSection    = (isset($_GET['section']) && trim($_GET['section']) !== '') ? trim($_GET['section']) : null;
$filterTimeSlot   = isset($_GET['time_slot_id']) ? filter_var($_GET['time_slot_id'], FILTER_VALIDATE_INT) : null;
$filterInstructor = isset($_GET['instructor']) ? trim($_GET['instructor']) : '';
$filterRoom       = isset($_GET['room']) ? trim($_GET['room']) : '';
$filterCredits    = isset($_GET['credits']) ? (trim($_GET['credits']) !== '' ? filter_var($_GET['credits'], FILTER_VALIDATE_INT) : '') : 4;
$filterOpenOnly   = (!$is_past_sem && !empty($_GET['open_only']));

$searchFilters = [
    'semester_id'  => $selected_sem_id,
    'crn'          => $filterCrn,
    'dept'         => $filterDept,
    'search'       => $filterSearch,
    'section'      => $filterSection,
    'time_slot_id' => $filterTimeSlot,
    'instructor'   => $filterInstructor,
    'room'         => $filterRoom,
    'credits'      => ($filterCredits !== '' ? $filterCredits : null),
    'open_only'    => $filterOpenOnly,
];

// 5. Query Master Schedule Results
$sections = searchMasterSchedule($conn, $searchFilters);
$totalResults = count($sections);

// 6. Fetch Student's Enrolled Courses for this Semester to display status badges
$mySchedule = getStudentScheduleBySemester($conn, $student_id, $selected_sem_id);
$enrolledCrns = $mySchedule['crns'];
$enrolledCourseIds = $mySchedule['course_ids'];

// Check if any filters are active (beyond semester selection)
$hasActiveFilters = ($filterCrn || $filterDept || $filterSearch !== '' || $filterSection !== null ||
                     $filterTimeSlot || $filterInstructor !== '' || $filterRoom !== '' ||
                     ($filterCredits !== '' && $filterCredits != 4) || $filterOpenOnly);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Master Schedule Search &bull; <?= htmlspecialchars($semester_name, ENT_QUOTES, 'UTF-8') ?> | Lucid University</title>
    <!-- Minimal Clean Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        <?= getProfileHoverCardStyles() ?>
    </style>
</head>
<body class="bg-light">

    <!-- Unified Student Top Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark mb-4">
        <div class="container">
            <a class="navbar-brand fw-bold" href="dashboard.php">Lucid University</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#studentNav" aria-controls="studentNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="studentNav">
                <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                    <li class="nav-item">
                        <a class="nav-link" href="dashboard.php">Dashboard</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="schedule.php">My Schedule</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" aria-current="page" href="master_schedule.php">Master Schedule</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="register.php">Register / Add-Drop</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="transcript.php">Transcript</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="degree_audit.php">Degree Audit</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="holds.php">Holds</a>
                    </li>
                </ul>
                <div class="d-flex align-items-center gap-3">
                    <?= getUserNavbarProfileHtml($conn, $student_id, 'Student') ?>
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
                <h2 class="fw-bold mb-1">Master Course Schedule Search</h2>
                <div class="text-secondary">
                    Browse and search course section offerings for <strong><?= htmlspecialchars($semester_name, ENT_QUOTES, 'UTF-8') ?></strong>.
                </div>
            </div>
            <div>
                <?php if ($is_reg_open): ?>
                    <a href="register.php?semester=<?= (int)$selected_sem_id ?>" class="btn btn-success btn-sm">
                        Register for <?= htmlspecialchars($semester_name, ENT_QUOTES, 'UTF-8') ?>
                    </a>
                <?php else: ?>
                    <span class="badge text-bg-secondary p-2">Registration Closed for <?= htmlspecialchars($semester_name, ENT_QUOTES, 'UTF-8') ?></span>
                <?php endif; ?>
            </div>
        </div>

        <!-- Master Filter Card -->
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-white py-3">
                <h5 class="mb-0 fw-bold">Search &amp; Filter Course Sections</h5>
            </div>
            <div class="card-body bg-white">
                <form method="GET" action="master_schedule.php">
                    <div class="row g-3">
                        <!-- Row 1: Semester, Department, Course Name/ID, CRN -->
                        <div class="col-md-3">
                            <label for="filter-semester" class="form-label small fw-bold">Academic Semester</label>
                            <select name="semester" id="filter-semester" class="form-select form-select-sm" onchange="this.form.submit()">
                                <?php foreach ($semesters as $s): ?>
                                    <option value="<?= (int)$s['Semester_ID'] ?>" <?= ($selected_sem_id === (int)$s['Semester_ID']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($s['Semester_Name'] . ' ' . $s['Semester_Year'], ENT_QUOTES, 'UTF-8') ?>
                                        <?= ((int)$s['Semester_ID'] === 202608) ? ' (Current)' : (((int)$s['Semester_ID'] === 202701) ? ' (Registration)' : '') ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label for="filter-dept" class="form-label small fw-bold">Academic Department</label>
                            <select name="dept" id="filter-dept" class="form-select form-select-sm">
                                <option value="">All Departments</option>
                                <?php foreach ($departments as $d): ?>
                                    <option value="<?= (int)$d['Department_ID'] ?>" <?= ($filterDept === (int)$d['Department_ID']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($d['Department_Name'], ENT_QUOTES, 'UTF-8') ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label for="filter-search" class="form-label small fw-bold">Course ID or Keyword</label>
                            <input type="text" name="search" id="filter-search" class="form-control form-control-sm" placeholder="e.g. CS101, Operating Systems, Calculus" value="<?= htmlspecialchars($filterSearch, ENT_QUOTES, 'UTF-8') ?>">
                        </div>
                        <div class="col-md-2">
                            <label for="filter-crn" class="form-label small fw-bold">CRN</label>
                            <input type="number" name="crn" id="filter-crn" class="form-control form-control-sm" placeholder="e.g. 1001" value="<?= $filterCrn ? (int)$filterCrn : '' ?>">
                        </div>

                        <!-- Row 2: Section, Day/Time Slot, Instructor, Room, Credits -->
                        <div class="col-md-2">
                            <label for="filter-section" class="form-label small fw-bold">Section #</label>
                            <input type="number" name="section" id="filter-section" class="form-control form-control-sm" placeholder="e.g. 1" value="<?= ($filterSection !== null) ? (int)$filterSection : '' ?>">
                        </div>
                        <div class="col-md-3">
                            <label for="filter-time-slot" class="form-label small fw-bold">Day / Period Time Slot</label>
                            <select name="time_slot_id" id="filter-time-slot" class="form-select form-select-sm">
                                <option value="">Any Time Slot</option>
                                <?php foreach ($timeSlots as $ts): ?>
                                    <option value="<?= (int)$ts['Time_Slot_ID'] ?>" <?= ($filterTimeSlot === (int)$ts['Time_Slot_ID']) ? 'selected' : '' ?>>
                                        <?= formatSchedule($ts['Time_Slot_Day'], $ts['Start_Time'], $ts['End_Time']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label for="filter-instructor" class="form-label small fw-bold">Faculty / Instructor</label>
                            <input type="text" name="instructor" id="filter-instructor" class="form-control form-control-sm" placeholder="e.g. Alan Turing, Knuth" value="<?= htmlspecialchars($filterInstructor, ENT_QUOTES, 'UTF-8') ?>">
                        </div>
                        <div class="col-md-2">
                            <label for="filter-room" class="form-label small fw-bold">Building / Room</label>
                            <input type="text" name="room" id="filter-room" class="form-control form-control-sm" placeholder="e.g. Science, 101" value="<?= htmlspecialchars($filterRoom, ENT_QUOTES, 'UTF-8') ?>">
                        </div>
                        <div class="col-md-2">
                            <label for="filter-credits" class="form-label small fw-bold">Credits</label>
                            <input type="number" name="credits" id="filter-credits" min="1" max="6" class="form-control form-control-sm" value="<?= htmlspecialchars((string)($filterCredits ?? 4)) ?>">
                        </div>

                        <!-- Row 3: Checkbox & Submit / Reset Buttons -->
                        <div class="col-12 d-flex flex-wrap justify-content-between align-items-center pt-2 border-top">
                            <div>
                                <?php if (!$is_past_sem): ?>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="open_only" value="1" id="filter-open-only" <?= $filterOpenOnly ? 'checked' : '' ?>>
                                        <label class="form-check-label small fw-bold" for="filter-open-only">
                                            Show only sections with open seats available
                                        </label>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="d-flex gap-2">
                                <button type="submit" class="btn btn-primary btn-sm px-4">
                                    Search Master Schedule
                                </button>
                                <?php if ($hasActiveFilters): ?>
                                    <a href="master_schedule.php?semester=<?= (int)$selected_sem_id ?>" class="btn btn-outline-secondary btn-sm">
                                        Reset Filters
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Search Results Table Card -->
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <h5 class="mb-0 fw-bold">
                    Schedule Offerings for <?= htmlspecialchars($semester_name, ENT_QUOTES, 'UTF-8') ?>
                </h5>
                <span class="badge bg-secondary"><?= (int)$totalResults ?> Section<?= $totalResults !== 1 ? 's' : '' ?> Found</span>
            </div>
            <div class="card-body p-0">
                <?php if (empty($sections)): ?>
                    <div class="p-5 text-center text-muted">
                        <h5 class="fw-bold text-secondary">No Course Sections Found</h5>
                        <p class="mb-3">No course sections match your filter criteria in <?= htmlspecialchars($semester_name, ENT_QUOTES, 'UTF-8') ?>.</p>
                        <a href="master_schedule.php?semester=<?= (int)$selected_sem_id ?>" class="btn btn-outline-primary btn-sm">
                            Clear Filters &amp; View All Sections
                        </a>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover table-striped mb-0 align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>CRN</th>
                                    <th>Course ID</th>
                                    <th>Sec</th>
                                    <th>Course Name</th>
                                    <th>Department</th>
                                    <th class="text-center">Credits</th>
                                    <th>Instructor</th>
                                    <th>Day / Time</th>
                                    <th>Building &amp; Room</th>
                                    <?php if (!$is_past_sem): ?>
                                        <th class="text-center">Seats</th>
                                    <?php endif; ?>
                                    <th class="text-end pe-3">Status / Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($sections as $sec): 
                                    $secCrn = (int)$sec['CRN'];
                                    $secCourseId = $sec['Course_ID'];
                                    $seatsAvailable = (int)$sec['Available_seats'];
                                    $isEnrolledInCrn = in_array($secCrn, $enrolledCrns, true);
                                    $isEnrolledInCourse = in_array($secCourseId, $enrolledCourseIds, true);
                                ?>
                                    <tr>
                                        <td><strong><?= $secCrn ?></strong></td>
                                        <td><span class="badge text-bg-light border"><?= htmlspecialchars($secCourseId, ENT_QUOTES, 'UTF-8') ?></span></td>
                                        <td><?= htmlspecialchars((string)$sec['Section_Number'], ENT_QUOTES, 'UTF-8') ?></td>
                                        <td>
                                            <span class="fw-semibold"><?= htmlspecialchars($sec['Course_Name'], ENT_QUOTES, 'UTF-8') ?></span>
                                            <span class="badge bg-secondary-subtle text-secondary small ms-1"><?= htmlspecialchars($sec['Course_Type'], ENT_QUOTES, 'UTF-8') ?></span>
                                            <div class="mt-1">
                                                <button type="button" class="btn btn-outline-info btn-sm py-0 px-2" style="font-size: 0.75rem;" data-bs-toggle="modal" data-bs-target="#prereqModal-<?= $secCrn ?>">
                                                    Prerequisites
                                                </button>
                                            </div>
                                        </td>
                                        <td><small class="text-muted"><?= htmlspecialchars($sec['Department_Name'] ?? 'General', ENT_QUOTES, 'UTF-8') ?></small></td>
                                        <td class="text-center fw-bold"><?= (int)$sec['Course_Credits'] ?></td>
                                        <td><?= htmlspecialchars($sec['Instructor_Name'], ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><?= formatSchedule($sec['Time_Slot_Day'], $sec['Start_Time'], $sec['End_Time']) ?></td>
                                        <td><?= htmlspecialchars($sec['Building_Name'] . ' ' . $sec['Room_NO'], ENT_QUOTES, 'UTF-8') ?></td>
                                        <?php if (!$is_past_sem): ?>
                                            <td class="text-center">
                                                <?php if ($seatsAvailable > 0): ?>
                                                    <span class="badge bg-success"><?= $seatsAvailable ?> open</span>
                                                <?php else: ?>
                                                    <span class="badge bg-danger">Full (0)</span>
                                                <?php endif; ?>
                                            </td>
                                        <?php endif; ?>
                                        <td class="text-end pe-3">
                                            <?php if ($isEnrolledInCrn): ?>
                                                <span class="badge bg-success">Enrolled</span>
                                            <?php elseif ($isEnrolledInCourse): ?>
                                                <span class="badge bg-secondary" title="Enrolled in another section of this course">Enrolled (Other Sec)</span>
                                            <?php elseif ($is_reg_open): ?>
                                                <?php if ($seatsAvailable > 0): ?>
                                                    <a href="register.php?semester=<?= (int)$selected_sem_id ?>&search=<?= urlencode($secCourseId) ?>" class="btn btn-sm btn-outline-primary">
                                                        Register
                                                    </a>
                                                <?php else: ?>
                                                    <button class="btn btn-sm btn-outline-danger" disabled>Full</button>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">Registration Closed</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <?php if (!empty($sections)): ?>
            <?php foreach ($sections as $sec): 
                $secCrn = (int)$sec['CRN'];
                $secCourseId = $sec['Course_ID'];
                $prereqs = $sec['prerequisites'] ?? [];
            ?>
                <!-- Prerequisites Modal for CRN <?= $secCrn ?> -->
                <div class="modal fade" id="prereqModal-<?= $secCrn ?>" tabindex="-1" aria-labelledby="prereqModalLabel-<?= $secCrn ?>" aria-hidden="true">
                    <div class="modal-dialog modal-dialog-centered">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title fs-6 fw-bold" id="prereqModalLabel-<?= $secCrn ?>">
                                    Prerequisites: <?= htmlspecialchars($secCourseId, ENT_QUOTES, 'UTF-8') ?> &ndash; <?= htmlspecialchars($sec['Course_Name'], ENT_QUOTES, 'UTF-8') ?>
                                </h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <?php if (!empty($prereqs)): ?>
                                    <p class="small text-muted mb-2">Required prerequisite course(s):</p>
                                    <ul class="list-group list-group-flush border rounded">
                                        <?php foreach ($prereqs as $pr): ?>
                                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                                <span>
                                                    <strong><?= htmlspecialchars($pr['Course_ID'], ENT_QUOTES, 'UTF-8') ?></strong>
                                                    &ndash; <?= htmlspecialchars($pr['Course_Name'], ENT_QUOTES, 'UTF-8') ?>
                                                </span>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php else: ?>
                                    <div class="alert alert-secondary mb-0 py-2 small">
                                        None &mdash; No prerequisites required for this course.
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="modal-footer py-2">
                                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>

    </div>

    <!-- Bootstrap 5 JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
