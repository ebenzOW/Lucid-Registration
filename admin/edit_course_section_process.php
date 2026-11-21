<?php
/**
 * Lucid University Course Registration System
 * Admin Portal - Edit Course Section Action Handler
 *
 * Enforces transaction safety with SELECT ... FOR UPDATE, local boolean rollback tracking,
 * and comprehensive validation of faculty department rosters, workloads, room capacity,
 * and time-slot conflict checks according to semester lifecycle rules.
 */

require_once 'admin_guard.php';
require_once '../db_connect.php';
require_once __DIR__ . '/../includes/auth_helpers.php';
require_once __DIR__ . '/../includes/faculty_helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: master_schedule.php");
    exit;
}

$crn         = isset($_POST['crn']) ? filter_var($_POST['crn'], FILTER_VALIDATE_INT) : null;
$semester_id = isset($_POST['semester_id']) ? filter_var($_POST['semester_id'], FILTER_VALIDATE_INT) : null;

if (!$crn) {
    $_SESSION['error_message'] = "Invalid Section CRN specified.";
    header("Location: master_schedule.php");
    exit;
}

$inTransaction = false;

try {
    // 1. Enforce Admin Security Level 2+ Write Access
    requireAdminWriteAccess();

    $conn->begin_transaction();
    $inTransaction = true;

    // 2. Lock target course section for atomic modification
    $secStmt = $conn->prepare("
        SELECT cs.CRN, cs.Course_ID, cs.Section_Number, cs.Semester_ID, cs.Faculty_ID,
               cs.Room_ID, cs.Time_Slot_ID, cs.Available_seats, cs.Status,
               c.Department_ID,
               (SELECT COUNT(*) FROM ENROLLMENT WHERE CRN = cs.CRN) AS Enrolled_Count
        FROM COURSE_SECTIONS cs
        JOIN COURSES c ON cs.Course_ID = c.Course_ID
        WHERE cs.CRN = ?
        FOR UPDATE
    ");
    $secStmt->bind_param("i", $crn);
    $secStmt->execute();
    $sec = $secStmt->get_result()->fetch_assoc();
    $secStmt->close();

    if (!$sec) {
        throw new Exception("Course section CRN {$crn} does not exist.");
    }

    $semId = (int)$sec['Semester_ID'];
    $isPast = ($semId < 202608 || ($sec['Status'] ?? '') === 'Past');
    $isCurrent = ($semId === 202608 && !$isPast);
    $isFuture = ($semId > 202608);
    $enrolledCount = (int)$sec['Enrolled_Count'];
    $deptId = (int)$sec['Department_ID'];

    if ($isPast) {
        throw new Exception("Sections belonging to historical terms cannot be modified.");
    }

    $newFacultyId = filter_input(INPUT_POST, 'faculty_id', FILTER_VALIDATE_INT);
    $newRoomId    = trim($_POST['room_id'] ?? '');

    if (!$newFacultyId || empty($newRoomId)) {
        throw new Exception("Please select both a valid instructor and an assigned room.");
    }

    if ($isCurrent) {
        // Current Semester: Time slot, seats, and status are fixed
        $targetTimeSlotId = (int)$sec['Time_Slot_ID'];

        // Validate Instructor
        $drStmt = $conn->prepare("SELECT 1 FROM DEPARTMENT_ROSTER WHERE Faculty_ID = ? AND Department_ID = ?");
        $drStmt->bind_param("ii", $newFacultyId, $deptId);
        $drStmt->execute();
        $isDeptMember = (bool)$drStmt->get_result()->fetch_row();
        $drStmt->close();

        if (!$isDeptMember) {
            throw new Exception("The selected instructor does not belong to the course's academic department.");
        }

        // Validate Instructor Load (if changing faculty)
        if ($newFacultyId !== (int)$sec['Faculty_ID']) {
            $fTypeStmt = $conn->prepare("SELECT Faculty_Type FROM FACULTY WHERE Faculty_ID = ?");
            $fTypeStmt->bind_param("i", $newFacultyId);
            $fTypeStmt->execute();
            $facTypeRow = $fTypeStmt->get_result()->fetch_assoc();
            $fTypeStmt->close();

            if (!$facTypeRow) {
                throw new Exception("Selected faculty member not found.");
            }

            $isFT = (stripos($facTypeRow['Faculty_Type'], 'Full') !== false);
            $maxLoad = $isFT ? 2 : 1;

            $loadStmt = $conn->prepare("
                SELECT COUNT(*) AS load_count
                FROM COURSE_SECTIONS
                WHERE Faculty_ID = ? AND Semester_ID = ? AND CRN != ? AND COALESCE(Status, 'Active') = 'Active'
            ");
            $loadStmt->bind_param("iii", $newFacultyId, $semId, $crn);
            $loadStmt->execute();
            $curLoad = (int)($loadStmt->get_result()->fetch_assoc()['load_count'] ?? 0);
            $loadStmt->close();

            if ($curLoad >= $maxLoad) {
                throw new Exception("The selected instructor has reached maximum teaching capacity ({$maxLoad} sections) for this term.");
            }

            // Validate Instructor Time Slot Conflict
            $fConfStmt = $conn->prepare("
                SELECT 1
                FROM COURSE_SECTIONS cs
                JOIN TIME_SLOT ts ON cs.Time_Slot_ID = ts.Time_Slot_ID
                JOIN TIME_SLOT_DAY tsd ON ts.Time_Slot_ID = tsd.Time_Slot_ID
                WHERE cs.Faculty_ID = ?
                  AND cs.Semester_ID = ?
                  AND cs.CRN != ?
                  AND COALESCE(cs.Status, 'Active') = 'Active'
                  AND ts.Period_ID = (SELECT Period_ID FROM TIME_SLOT WHERE Time_Slot_ID = ?)
                  AND tsd.Day_ID IN (SELECT Day_ID FROM TIME_SLOT_DAY WHERE Time_Slot_ID = ?)
                LIMIT 1
            ");
            $fConfStmt->bind_param("iiiii", $newFacultyId, $semId, $crn, $targetTimeSlotId, $targetTimeSlotId);
            $fConfStmt->execute();
            $hasFConf = (bool)$fConfStmt->get_result()->fetch_row();
            $fConfStmt->close();

            if ($hasFConf) {
                throw new Exception("The selected instructor already has another section scheduled at this time slot.");
            }
        }

        // Validate Room Capacity & Conflict (if changing room)
        if (strcasecmp($newRoomId, (string)$sec['Room_ID']) !== 0) {
            $rCapStmt = $conn->prepare("
                SELECT COALESCE(l.Lecture_Seats, lab.Lab_Stations, 0) AS Room_Capacity
                FROM ROOM r
                LEFT JOIN LECTURE l ON r.Room_ID = l.Lecture_ID
                LEFT JOIN LAB lab ON r.Room_ID = lab.Lab_ID
                WHERE r.Room_ID = ?
            ");
            $rCapStmt->bind_param("s", $newRoomId);
            $rCapStmt->execute();
            $rCapRow = $rCapStmt->get_result()->fetch_assoc();
            $rCapStmt->close();

            if (!$rCapRow) {
                throw new Exception("Selected room record does not exist.");
            }

            $roomCap = (int)$rCapRow['Room_Capacity'];
            if ($roomCap < $enrolledCount) {
                throw new Exception("The selected room capacity ({$roomCap}) cannot accommodate current enrollments ({$enrolledCount}).");
            }

            $rConfStmt = $conn->prepare("
                SELECT 1
                FROM COURSE_SECTIONS cs
                JOIN TIME_SLOT ts ON cs.Time_Slot_ID = ts.Time_Slot_ID
                JOIN TIME_SLOT_DAY tsd ON ts.Time_Slot_ID = tsd.Time_Slot_ID
                WHERE cs.Room_ID = ?
                  AND cs.Semester_ID = ?
                  AND cs.CRN != ?
                  AND COALESCE(cs.Status, 'Active') = 'Active'
                  AND ts.Period_ID = (SELECT Period_ID FROM TIME_SLOT WHERE Time_Slot_ID = ?)
                  AND tsd.Day_ID IN (SELECT Day_ID FROM TIME_SLOT_DAY WHERE Time_Slot_ID = ?)
                LIMIT 1
            ");
            $rConfStmt->bind_param("siiii", $newRoomId, $semId, $crn, $targetTimeSlotId, $targetTimeSlotId);
            $rConfStmt->execute();
            $hasRConf = (bool)$rConfStmt->get_result()->fetch_row();
            $rConfStmt->close();

            if ($hasRConf) {
                throw new Exception("The selected room is already reserved by another section at this time slot.");
            }
        }

        // Execute Update
        $upStmt = $conn->prepare("UPDATE COURSE_SECTIONS SET Faculty_ID = ?, Room_ID = ? WHERE CRN = ?");
        $upStmt->bind_param("isi", $newFacultyId, $newRoomId, $crn);
        $upStmt->execute();
        $upStmt->close();

    } elseif ($isFuture) {
        // Future Semester: Instructor, Room, Time Slot, Seats, and Status can be modified
        $newTimeSlotId   = filter_input(INPUT_POST, 'time_slot_id', FILTER_VALIDATE_INT);
        $newSeats        = filter_input(INPUT_POST, 'available_seats', FILTER_VALIDATE_INT);
        $newStatus       = trim($_POST['status'] ?? 'Active');

        if (!$newTimeSlotId) {
            throw new Exception("Please select a valid scheduled time slot.");
        }
        if (!$newSeats || $newSeats < 5 || $newSeats > 10) {
            throw new Exception("Available seats must be configured between 5 and 10.");
        }
        if ($newSeats < $enrolledCount) {
            throw new Exception("Available seats cannot be set lower than currently enrolled students ({$enrolledCount}).");
        }
        if (!in_array($newStatus, ['Active', 'Cancelled'], true)) {
            $newStatus = 'Active';
        }

        // Validate Instructor Department
        $drStmt = $conn->prepare("SELECT 1 FROM DEPARTMENT_ROSTER WHERE Faculty_ID = ? AND Department_ID = ?");
        $drStmt->bind_param("ii", $newFacultyId, $deptId);
        $drStmt->execute();
        $isDeptMember = (bool)$drStmt->get_result()->fetch_row();
        $drStmt->close();

        if (!$isDeptMember) {
            throw new Exception("The selected instructor does not belong to the course's academic department.");
        }

        // Validate Instructor Load (if active)
        if ($newStatus === 'Active') {
            $fTypeStmt = $conn->prepare("SELECT Faculty_Type FROM FACULTY WHERE Faculty_ID = ?");
            $fTypeStmt->bind_param("i", $newFacultyId);
            $fTypeStmt->execute();
            $facTypeRow = $fTypeStmt->get_result()->fetch_assoc();
            $fTypeStmt->close();

            $isFT = (stripos($facTypeRow['Faculty_Type'] ?? '', 'Full') !== false);
            $maxLoad = $isFT ? 2 : 1;

            $loadStmt = $conn->prepare("
                SELECT COUNT(*) AS load_count
                FROM COURSE_SECTIONS
                WHERE Faculty_ID = ? AND Semester_ID = ? AND CRN != ? AND COALESCE(Status, 'Active') = 'Active'
            ");
            $loadStmt->bind_param("iii", $newFacultyId, $semId, $crn);
            $loadStmt->execute();
            $curLoad = (int)($loadStmt->get_result()->fetch_assoc()['load_count'] ?? 0);
            $loadStmt->close();

            if ($curLoad >= $maxLoad) {
                throw new Exception("The selected instructor has reached maximum teaching capacity ({$maxLoad} sections) for this term.");
            }

            // Validate Instructor Time Slot Conflict
            $fConfStmt = $conn->prepare("
                SELECT 1
                FROM COURSE_SECTIONS cs
                JOIN TIME_SLOT ts ON cs.Time_Slot_ID = ts.Time_Slot_ID
                JOIN TIME_SLOT_DAY tsd ON ts.Time_Slot_ID = tsd.Time_Slot_ID
                WHERE cs.Faculty_ID = ?
                  AND cs.Semester_ID = ?
                  AND cs.CRN != ?
                  AND COALESCE(cs.Status, 'Active') = 'Active'
                  AND ts.Period_ID = (SELECT Period_ID FROM TIME_SLOT WHERE Time_Slot_ID = ?)
                  AND tsd.Day_ID IN (SELECT Day_ID FROM TIME_SLOT_DAY WHERE Time_Slot_ID = ?)
                LIMIT 1
            ");
            $fConfStmt->bind_param("iiiii", $newFacultyId, $semId, $crn, $newTimeSlotId, $newTimeSlotId);
            $fConfStmt->execute();
            $hasFConf = (bool)$fConfStmt->get_result()->fetch_row();
            $fConfStmt->close();

            if ($hasFConf) {
                throw new Exception("The selected instructor already has another section scheduled at this time slot.");
            }

            // Validate Room Capacity & Conflict
            $rCapStmt = $conn->prepare("
                SELECT COALESCE(l.Lecture_Seats, lab.Lab_Stations, 0) AS Room_Capacity
                FROM ROOM r
                LEFT JOIN LECTURE l ON r.Room_ID = l.Lecture_ID
                LEFT JOIN LAB lab ON r.Room_ID = lab.Lab_ID
                WHERE r.Room_ID = ?
            ");
            $rCapStmt->bind_param("s", $newRoomId);
            $rCapStmt->execute();
            $rCapRow = $rCapStmt->get_result()->fetch_assoc();
            $rCapStmt->close();

            if (!$rCapRow) {
                throw new Exception("Selected room record does not exist.");
            }

            $roomCap = (int)$rCapRow['Room_Capacity'];
            if ($roomCap < $newSeats) {
                throw new Exception("The selected room capacity ({$roomCap}) is smaller than configured section seats ({$newSeats}).");
            }

            $rConfStmt = $conn->prepare("
                SELECT 1
                FROM COURSE_SECTIONS cs
                JOIN TIME_SLOT ts ON cs.Time_Slot_ID = ts.Time_Slot_ID
                JOIN TIME_SLOT_DAY tsd ON ts.Time_Slot_ID = tsd.Time_Slot_ID
                WHERE cs.Room_ID = ?
                  AND cs.Semester_ID = ?
                  AND cs.CRN != ?
                  AND COALESCE(cs.Status, 'Active') = 'Active'
                  AND ts.Period_ID = (SELECT Period_ID FROM TIME_SLOT WHERE Time_Slot_ID = ?)
                  AND tsd.Day_ID IN (SELECT Day_ID FROM TIME_SLOT_DAY WHERE Time_Slot_ID = ?)
                LIMIT 1
            ");
            $rConfStmt->bind_param("siiii", $newRoomId, $semId, $crn, $newTimeSlotId, $newTimeSlotId);
            $rConfStmt->execute();
            $hasRConf = (bool)$rConfStmt->get_result()->fetch_row();
            $rConfStmt->close();

            if ($hasRConf) {
                throw new Exception("The selected room is already reserved by another section at this time slot.");
            }
        }

        // Execute Update
        $upStmt = $conn->prepare("
            UPDATE COURSE_SECTIONS
            SET Faculty_ID = ?, Room_ID = ?, Time_Slot_ID = ?, Available_seats = ?, Status = ?
            WHERE CRN = ?
        ");
        $upStmt->bind_param("isiisi", $newFacultyId, $newRoomId, $newTimeSlotId, $newSeats, $newStatus, $crn);
        $upStmt->execute();
        $upStmt->close();
    }

    $conn->commit();
    $inTransaction = false;

    $_SESSION['success_message'] = "Course section CRN {$crn} successfully updated.";
    header("Location: master_schedule.php?semester={$semId}&crn={$crn}");
    exit;

} catch (Exception $e) {
    if ($inTransaction) {
        $conn->rollback();
    }
    $_SESSION['error_message'] = $e->getMessage();
    header("Location: edit_course_section.php?crn={$crn}");
    exit;
}
