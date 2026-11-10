<?php
/**
 * Lucid University Course Registration System
 * Admin Portal - Create New Course Section
 *
 * Allows administrators to schedule new course sections for future semesters
 * with full validation of faculty load, time conflicts, department matching,
 * room availability, and class capacity constraints.
 * Upgraded with multi-directional dynamic JavaScript filtering.
 */

require_once 'admin_guard.php';
require_once '../db_connect.php';
require_once __DIR__ . '/../includes/academic_helpers.php';
require_once '../profile_hover_card.php';

$admin_id = (int)$userID;

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

// Default select first future semester
$defaultSemesterId = !empty($futureSemesters) ? (int)$futureSemesters[0]['Semester_ID'] : null;

// 2. Fetch All Courses with Department Name
$courseStmt = $conn->prepare("
    SELECT c.Course_ID, c.Course_Name, c.Course_Type, c.Department_ID, d.Department_Name
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
           GROUP_CONCAT(DISTINCT dr.Department_ID ORDER BY dr.Department_ID SEPARATOR ',') AS Dept_IDs,
           GROUP_CONCAT(DISTINCT d.Department_Name ORDER BY d.Department_Name SEPARATOR ', ') AS Department_Name
    FROM FACULTY f
    JOIN `USER` u ON f.Faculty_ID = u.User_ID
    LEFT JOIN DEPARTMENT_ROSTER dr ON f.Faculty_ID = dr.Faculty_ID
    LEFT JOIN DEPARTMENT d ON dr.Department_ID = d.Department_ID
    GROUP BY f.Faculty_ID, f.Faculty_Type, u.User_FName, u.User_MName, u.User_LName
    ORDER BY u.User_LName ASC, u.User_FName ASC
");
$facultyStmt->execute();
$facultyMembers = $facultyStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$facultyStmt->close();

// 4. Fetch Active Time Slots & Build Overlap Conflict Map
$tsStmt = $conn->prepare("
    SELECT ts.Time_Slot_ID, ts.Time_Slot_Day, p.Start_Time, p.End_Time
    FROM TIME_SLOT ts
    JOIN PERIOD p ON ts.Period_ID = p.Period_ID
    WHERE (ts.Time_Slot_ID BETWEEN 101 AND 116) OR ts.Time_Slot_Day = 'Friday'
    ORDER BY ts.Time_Slot_ID ASC
");
$tsStmt->execute();
$timeSlots = $tsStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$tsStmt->close();

$tsMapStmt = $conn->prepare("
    SELECT ts.Time_Slot_ID, ts.Period_ID, tsd.Day_ID
    FROM TIME_SLOT ts
    JOIN TIME_SLOT_DAY tsd ON ts.Time_Slot_ID = tsd.Time_Slot_ID
    WHERE (ts.Time_Slot_ID BETWEEN 101 AND 116) OR ts.Time_Slot_Day = 'Friday'
");
$tsMapStmt->execute();
$tsDayRows = $tsMapStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$tsMapStmt->close();

$slotDetails = [];
foreach ($tsDayRows as $row) {
    $sid = (int)$row['Time_Slot_ID'];
    $slotDetails[$sid]['period'] = (int)$row['Period_ID'];
    $slotDetails[$sid]['days'][] = (int)$row['Day_ID'];
}

$timeSlotConflicts = [];
foreach ($slotDetails as $slotA => $detA) {
    $timeSlotConflicts[$slotA] = [];
    foreach ($slotDetails as $slotB => $detB) {
        if ($detA['period'] === $detB['period']) {
            if (!empty(array_intersect($detA['days'], $detB['days']))) {
                $timeSlotConflicts[$slotA][] = (int)$slotB;
            }
        }
    }
}

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

// 6. Fetch Existing Active Future Semester Sections
$secStmt = $conn->prepare("
    SELECT cs.Semester_ID, cs.CRN, cs.Course_ID, cs.Faculty_ID, cs.Time_Slot_ID, cs.Room_ID
    FROM COURSE_SECTIONS cs
    JOIN SEMESTER s ON cs.Semester_ID = s.Semester_ID
    WHERE s.Start_Date > CURRENT_DATE()
      AND COALESCE(cs.Status, 'Active') = 'Active'
");
$secStmt->execute();
$existingSections = $secStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$secStmt->close();

// Prepare JSON payload objects
$coursesPayload = [];
foreach ($courses as $c) {
    $coursesPayload[$c['Course_ID']] = [
        'id'       => $c['Course_ID'],
        'name'     => $c['Course_Name'],
        'type'     => $c['Course_Type'],
        'deptId'   => (int)$c['Department_ID'],
        'deptName' => $c['Department_Name'],
    ];
}

$facultyPayload = [];
foreach ($facultyMembers as $f) {
    $fid = (int)$f['Faculty_ID'];
    $rawDeptIds = !empty($f['Dept_IDs']) ? explode(',', $f['Dept_IDs']) : [];
    $deptIds = array_map('intval', $rawDeptIds);
    $fName = trim($f['User_FName'] . ' ' . ($f['User_MName'] ? $f['User_MName'] . ' ' : '') . $f['User_LName']);
    $isFT = (stripos($f['Faculty_Type'], 'Full') !== false);
    $facultyPayload[$fid] = [
        'id'        => $fid,
        'name'      => $fName,
        'type'      => $f['Faculty_Type'],
        'maxLoad'   => $isFT ? 2 : 1,
        'deptIds'   => $deptIds,
        'deptNames' => $f['Department_Name'] ?? 'No Department',
    ];
}

$roomPayload = [];
foreach ($rooms as $r) {
    $roomPayload[$r['Room_ID']] = [
        'id'           => $r['Room_ID'],
        'buildingName' => $r['Building_Name'],
        'roomNo'       => $r['Room_NO'],
        'type'         => $r['Room_Type'],
        'capacity'     => (int)$r['Capacity'],
    ];
}
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

    <?= renderAdminNavbar($conn, $admin_id, basename($_SERVER['PHP_SELF'])) ?>

    <div class="container mb-5">

        <!-- Page Header -->
        <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
            <div>
                <h2 class="fw-bold mb-1">Create Course Section</h2>
                <div class="text-secondary">
                    Schedule a new section offering for an upcoming academic term with real-time constraint validation.
                </div>
            </div>
            <div class="d-flex gap-2">
                <button type="button" id="btnResetSelections" class="btn btn-outline-warning btn-sm">
                    Reset Selections
                </button>
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
                <form action="create_course_section_process.php" method="POST" id="createSectionForm">

                    <h5 class="fw-bold text-dark border-bottom pb-2 mb-3">Course Section Parameters</h5>

                    <div class="row g-3 mb-4">
                        <!-- 1. Semester Selection (Future Only) -->
                        <div class="col-md-6">
                            <label for="semester_id" class="form-label small fw-bold">
                                Target Semester <span class="text-danger">*</span>
                            </label>
                            <select name="semester_id" id="semester_id" class="form-select" required>
                                <option value="">Select Modifiable Future Semester</option>
                                <?php foreach ($futureSemesters as $sem): 
                                    $isSelected = ($defaultSemesterId === (int)$sem['Semester_ID']);
                                ?>
                                    <option value="<?= (int)$sem['Semester_ID'] ?>" <?= $isSelected ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($sem['Semester_Name'] . ' ' . $sem['Semester_Year'] . ' (Starts: ' . $sem['Start_Date'] . ')', ENT_QUOTES, 'UTF-8') ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="form-text small">Only future semesters can be scheduled.</div>
                        </div>

                        <!-- 2. Course Selection -->
                        <div class="col-md-6">
                            <label for="course_id" class="form-label small fw-bold">
                                Course Offering <span class="text-danger">*</span>
                                <span class="badge bg-secondary ms-1" id="course_count_badge"></span>
                            </label>
                            <select name="course_id" id="course_id" class="form-select" required>
                                <option value="">Select Academic Course</option>
                                <?php foreach ($courses as $c): ?>
                                    <option value="<?= htmlspecialchars($c['Course_ID'], ENT_QUOTES, 'UTF-8') ?>" data-dept-id="<?= (int)$c['Department_ID'] ?>">
                                        <?= htmlspecialchars($c['Course_ID'] . ' - ' . $c['Course_Name'] . ' (' . $c['Department_Name'] . ')', ENT_QUOTES, 'UTF-8') ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="form-text small">Filtered dynamically by instructor department match.</div>
                        </div>

                        <!-- 3. Faculty Instructor -->
                        <div class="col-md-6">
                            <label for="faculty_id" class="form-label small fw-bold">
                                Instructor / Faculty <span class="text-danger">*</span>
                                <span class="badge bg-secondary ms-1" id="faculty_count_badge"></span>
                            </label>
                            <select name="faculty_id" id="faculty_id" class="form-select" required>
                                <option value="">Select Faculty Instructor</option>
                                <?php foreach ($facultyMembers as $f): 
                                    $fName = trim($f['User_FName'] . ' ' . ($f['User_MName'] ? $f['User_MName'] . ' ' : '') . $f['User_LName']);
                                ?>
                                    <option value="<?= (int)$f['Faculty_ID'] ?>" data-dept-ids="<?= htmlspecialchars($f['Dept_IDs'] ?? '', ENT_QUOTES, 'UTF-8') ?>" data-type="<?= htmlspecialchars($f['Faculty_Type'], ENT_QUOTES, 'UTF-8') ?>">
                                        <?= htmlspecialchars($fName . ' (' . $f['Faculty_Type'] . ' - ' . ($f['Department_Name'] ?? 'No Dept') . ')', ENT_QUOTES, 'UTF-8') ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="form-text small">Max load: Part-Time = 1 section, Full-Time = 2 sections.</div>
                        </div>

                        <!-- 4. Time Slot -->
                        <div class="col-md-6">
                            <label for="time_slot_id" class="form-label small fw-bold">
                                Day &amp; Period Time Slot <span class="text-danger">*</span>
                                <span class="badge bg-secondary ms-1" id="slot_count_badge"></span>
                            </label>
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
                            <div class="form-text small">Validated to prevent double-booking instructors or rooms.</div>
                        </div>

                        <!-- 5. Room Assignment -->
                        <div class="col-md-6">
                            <label for="room_id" class="form-label small fw-bold">
                                Classroom / Facility <span class="text-danger">*</span>
                                <span class="badge bg-secondary ms-1" id="room_count_badge"></span>
                            </label>
                            <select name="room_id" id="room_id" class="form-select" required>
                                <option value="">Select Classroom Room</option>
                                <?php foreach ($rooms as $r): ?>
                                    <option value="<?= htmlspecialchars($r['Room_ID'], ENT_QUOTES, 'UTF-8') ?>" data-capacity="<?= (int)$r['Capacity'] ?>">
                                        <?= htmlspecialchars($r['Room_ID'] . ' - ' . $r['Building_Name'] . ' ' . $r['Room_NO'] . ' (' . $r['Room_Type'] . ($r['Capacity'] > 0 ? ', Cap: ' . $r['Capacity'] : '') . ')', ENT_QUOTES, 'UTF-8') ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="form-text small">Only instructional lecture halls and labs are available.</div>
                        </div>

                        <!-- 6. Available Seats -->
                        <div class="col-md-6">
                            <label for="available_seats" class="form-label small fw-bold">Available Seats Capacity <span class="text-danger">*</span></label>
                            <input type="number" name="available_seats" id="available_seats" class="form-control" min="5" max="10" value="10" required>
                            <div class="form-text small">Initial section capacity must be between 5 and 10 seats.</div>
                        </div>
                    </div>

                    <div class="d-flex gap-2 justify-content-end border-top pt-3">
                        <a href="dashboard.php" class="btn btn-outline-secondary">Cancel</a>
                        <button type="submit" class="btn btn-success px-4" id="btnSubmitSection">
                            Create Course Section
                        </button>
                    </div>

                </form>
            </div>
        </div>

    </div>

    <!-- Bootstrap 5.3 JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

    <!-- Constraint Dataset Objects -->
    <script>
    const coursesData       = <?= json_encode($coursesPayload, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    const facultyData       = <?= json_encode($facultyPayload, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    const timeSlotConflicts = <?= json_encode($timeSlotConflicts, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    const roomData          = <?= json_encode($roomPayload, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    const existingSections  = <?= json_encode($existingSections, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;

    function updateDynamicSectionFilters() {
        const semSelect   = document.getElementById('semester_id');
        const courseSelect= document.getElementById('course_id');
        const facSelect   = document.getElementById('faculty_id');
        const slotSelect  = document.getElementById('time_slot_id');
        const roomSelect  = document.getElementById('room_id');
        const seatsInput  = document.getElementById('available_seats');

        const selSem    = semSelect.value ? parseInt(semSelect.value, 10) : null;
        const selCourse = courseSelect.value || null;
        const selFac    = facSelect.value ? parseInt(facSelect.value, 10) : null;
        const selSlot   = slotSelect.value ? parseInt(slotSelect.value, 10) : null;
        const selRoom   = roomSelect.value || null;
        const selSeats  = seatsInput.value ? parseInt(seatsInput.value, 10) : 5;

        // 1. Compute for selSem:
        const facultyLoad = {};
        const facultyBookedSlots = {};
        const roomBookedSlots = {};

        if (selSem) {
            existingSections.forEach(sec => {
                if (parseInt(sec.Semester_ID, 10) === selSem) {
                    const fid = parseInt(sec.Faculty_ID, 10);
                    const rid = sec.Room_ID;
                    const sid = parseInt(sec.Time_Slot_ID, 10);

                    facultyLoad[fid] = (facultyLoad[fid] || 0) + 1;

                    if (!facultyBookedSlots[fid]) facultyBookedSlots[fid] = [];
                    facultyBookedSlots[fid].push(sid);

                    if (!roomBookedSlots[rid]) roomBookedSlots[rid] = [];
                    roomBookedSlots[rid].push(sid);
                }
            });
        }

        function hasSlotConflict(slotId, bookedSlotsArray) {
            if (!slotId || !bookedSlotsArray || bookedSlotsArray.length === 0) return false;
            const conflicts = timeSlotConflicts[slotId] || [slotId];
            return bookedSlotsArray.some(bSlot => conflicts.includes(bSlot));
        }

        function isFacultyAvailableInSemester(fid) {
            const f = facultyData[fid];
            if (!f) return false;
            const load = facultyLoad[fid] || 0;
            if (load >= f.maxLoad) return false;
            if (selSlot && hasSlotConflict(selSlot, facultyBookedSlots[fid])) return false;
            return true;
        }

        // Filter Course Dropdown (#course_id)
        let validCourses = 0;
        Array.from(courseSelect.options).forEach(opt => {
            if (!opt.value) return;
            const cId = opt.value;
            const c = coursesData[cId];
            let isValid = true;

            if (selFac) {
                const fDepts = facultyData[selFac]?.deptIds || [];
                if (!fDepts.includes(c.deptId)) isValid = false;
            } else if (selSem) {
                const deptId = c.deptId;
                const hasEligibleFaculty = Object.values(facultyData).some(f =>
                    f.deptIds.includes(deptId) && isFacultyAvailableInSemester(f.id)
                );
                if (!hasEligibleFaculty) isValid = false;
            }

            opt.hidden = !isValid;
            opt.disabled = !isValid;
            opt.style.display = isValid ? '' : 'none';
            if (isValid) validCourses++;
        });

        // Filter Faculty Dropdown (#faculty_id)
        let validFaculty = 0;
        Array.from(facSelect.options).forEach(opt => {
            if (!opt.value) return;
            const fid = parseInt(opt.value, 10);
            const f = facultyData[fid];
            let isValid = true;

            if (!f || f.deptIds.length === 0) {
                isValid = false;
            }
            if (selSem) {
                const load = facultyLoad[fid] || 0;
                if (load >= f.maxLoad) isValid = false;
                if (selSlot && hasSlotConflict(selSlot, facultyBookedSlots[fid])) isValid = false;
            }
            if (selCourse) {
                const c = coursesData[selCourse];
                if (!c || !f.deptIds.includes(c.deptId)) isValid = false;
            }

            // Dynamically update option label to display live load
            const curLoad = selSem ? (facultyLoad[fid] || 0) : 0;
            const maxL = f ? f.maxLoad : 2;
            const deptText = f ? f.deptNames : 'No Dept';
            const profName = f ? f.name : opt.text;
            opt.text = `${profName} (${f ? f.type : 'Faculty'} - ${deptText} | Load: ${curLoad}/${maxL})`;

            opt.hidden = !isValid;
            opt.disabled = !isValid;
            opt.style.display = isValid ? '' : 'none';
            if (isValid) validFaculty++;
        });

        // Filter Time Slot Dropdown (#time_slot_id)
        let validSlots = 0;
        Array.from(slotSelect.options).forEach(opt => {
            if (!opt.value) return;
            const tsId = parseInt(opt.value, 10);
            let isValid = true;

            if (selSem) {
                if (selFac && hasSlotConflict(tsId, facultyBookedSlots[selFac])) isValid = false;
                if (selRoom && hasSlotConflict(tsId, roomBookedSlots[selRoom])) isValid = false;
            }

            opt.hidden = !isValid;
            opt.disabled = !isValid;
            opt.style.display = isValid ? '' : 'none';
            if (isValid) validSlots++;
        });

        // Filter Classroom / Facility Dropdown (#room_id)
        let validRooms = 0;
        Array.from(roomSelect.options).forEach(opt => {
            if (!opt.value) return;
            const rid = opt.value;
            const r = roomData[rid];
            let isValid = true;

            if (!r || r.capacity < selSeats) isValid = false;
            if (selSem && selSlot && hasSlotConflict(selSlot, roomBookedSlots[rid])) isValid = false;

            opt.hidden = !isValid;
            opt.disabled = !isValid;
            opt.style.display = isValid ? '' : 'none';
            if (isValid) validRooms++;
        });

        // Auto-reset invalid selections
        let resetNeeded = false;
        if (courseSelect.value && courseSelect.selectedOptions[0]?.disabled) {
            courseSelect.value = '';
            resetNeeded = true;
        }
        if (facSelect.value && facSelect.selectedOptions[0]?.disabled) {
            facSelect.value = '';
            resetNeeded = true;
        }
        if (slotSelect.value && slotSelect.selectedOptions[0]?.disabled) {
            slotSelect.value = '';
            resetNeeded = true;
        }
        if (roomSelect.value && roomSelect.selectedOptions[0]?.disabled) {
            roomSelect.value = '';
            resetNeeded = true;
        }

        if (resetNeeded) {
            updateDynamicSectionFilters();
            return;
        }

        // Live Count Badges
        const cBadge = document.getElementById('course_count_badge');
        if (cBadge) cBadge.textContent = `${validCourses} eligible`;
        const fBadge = document.getElementById('faculty_count_badge');
        if (fBadge) fBadge.textContent = `${validFaculty} available`;
        const sBadge = document.getElementById('slot_count_badge');
        if (sBadge) sBadge.textContent = `${validSlots} open`;
        const rBadge = document.getElementById('room_count_badge');
        if (rBadge) rBadge.textContent = `${validRooms} open`;
    }

    document.addEventListener('DOMContentLoaded', function () {
        ['semester_id', 'course_id', 'faculty_id', 'time_slot_id', 'room_id', 'available_seats'].forEach(id => {
            const el = document.getElementById(id);
            if (el) {
                el.addEventListener('change', updateDynamicSectionFilters);
                el.addEventListener('input', updateDynamicSectionFilters);
            }
        });

        const btnReset = document.getElementById('btnResetSelections');
        if (btnReset) {
            btnReset.addEventListener('click', function () {
                document.getElementById('course_id').value = '';
                document.getElementById('faculty_id').value = '';
                document.getElementById('time_slot_id').value = '';
                document.getElementById('room_id').value = '';
                document.getElementById('available_seats').value = '10';
                updateDynamicSectionFilters();
            });
        }

        // Initial run to activate constraints for default semester
        updateDynamicSectionFilters();
    });
    </script>
</body>
</html>
