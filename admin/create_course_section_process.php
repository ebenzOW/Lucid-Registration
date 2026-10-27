<?php
/**
 * Lucid University Course Registration System
 * Admin Portal - Create Course Section Backend Processor
 *
 * Validates semester eligibility, faculty teaching load limits, department roster
 * membership, classroom and instructor time collisions, and atomical insertion.
 */

require_once 'admin_guard.php';
require_once '../db_connect.php';

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: create_course_section.php");
    exit;
}

$semester_id     = filter_var($_POST['semester_id'] ?? null, FILTER_VALIDATE_INT);
$course_id       = trim($_POST['course_id'] ?? '');
$faculty_id      = filter_var($_POST['faculty_id'] ?? null, FILTER_VALIDATE_INT);
$time_slot_id    = filter_var($_POST['time_slot_id'] ?? null, FILTER_VALIDATE_INT);
$room_id         = trim($_POST['room_id'] ?? '');
$available_seats = filter_var($_POST['available_seats'] ?? null, FILTER_VALIDATE_INT);

try {
    // 0. Preliminary Presence Check
    if (!$semester_id || empty($course_id) || !$faculty_id || !$time_slot_id || empty($room_id) || !$available_seats) {
        throw new Exception("Please fill in all required fields to create a course section.");
    }

    $conn->begin_transaction();

    // 1. Constraint 1: Target Semester Eligibility Check (Must not have started)
    $semStmt = $conn->prepare("
        SELECT Semester_Name, Semester_Year, Start_Date, (Start_Date > CURRENT_DATE()) AS is_future
        FROM SEMESTER
        WHERE Semester_ID = ?
    ");
    $semStmt->bind_param("i", $semester_id);
    $semStmt->execute();
    $semInfo = $semStmt->get_result()->fetch_assoc();
    $semStmt->close();

    if (!$semInfo) {
        throw new Exception("The selected semester does not exist in the academic calendar.");
    }
    if (!$semInfo['is_future']) {
        throw new Exception("Cannot create or modify course sections for semesters that have already started or concluded.");
    }

    // 2. Constraint 2: Available Seats Check (Policy: min 5, max 10)
    if ($available_seats < 5 || $available_seats > 10) {
        throw new Exception("Available seats must be between 5 and 10 (per system design policy).");
    }

    // 3. Constraint 3: Department Roster Match Check
    // Verify the selected Faculty_ID belongs to the same Department_ID as the selected Course_ID
    $courseDeptStmt = $conn->prepare("
        SELECT c.Course_Name, c.Department_ID, d.Department_Name
        FROM COURSES c
        JOIN DEPARTMENT d ON c.Department_ID = d.Department_ID
        WHERE c.Course_ID = ?
    ");
    $courseDeptStmt->bind_param("s", $course_id);
    $courseDeptStmt->execute();
    $courseData = $courseDeptStmt->get_result()->fetch_assoc();
    $courseDeptStmt->close();

    if (!$courseData) {
        throw new Exception("The selected course record does not exist.");
    }

    $deptMatchStmt = $conn->prepare("
        SELECT 1
        FROM DEPARTMENT_ROSTER
        WHERE Department_ID = ? AND Faculty_ID = ?
    ");
    $deptMatchStmt->bind_param("ii", $courseData['Department_ID'], $faculty_id);
    $deptMatchStmt->execute();
    $isDeptMatched = $deptMatchStmt->get_result()->fetch_row();
    $deptMatchStmt->close();

    if (!$isDeptMatched) {
        throw new Exception("Selected faculty member does not belong to the course's department.");
    }

    // 4. Constraint 4: Faculty Teaching Load Limit Check
    // Part-Time max = 1 section; Full-Time max = 2 sections per semester
    $facStmt = $conn->prepare("
        SELECT f.Faculty_Type, u.User_FName, u.User_LName,
               (SELECT COUNT(*) FROM COURSE_SECTIONS cs WHERE cs.Faculty_ID = f.Faculty_ID AND cs.Semester_ID = ?) AS current_load
        FROM FACULTY f
        JOIN `USER` u ON f.Faculty_ID = u.User_ID
        WHERE f.Faculty_ID = ?
    ");
    $facStmt->bind_param("ii", $semester_id, $faculty_id);
    $facStmt->execute();
    $facData = $facStmt->get_result()->fetch_assoc();
    $facStmt->close();

    if (!$facData) {
        throw new Exception("Faculty member record not found.");
    }

    $isFullTime = (stripos($facData['Faculty_Type'], 'Full') !== false);
    $maxLoad = $isFullTime ? 2 : 1;
    if ((int)$facData['current_load'] >= $maxLoad) {
        throw new Exception("Faculty member has reached their maximum teaching load for this semester.");
    }

    // 5. Constraint 5: Faculty Time-Slot Conflict Check
    // Verify faculty is not already assigned to another section in the same semester and time slot
    $facConflictStmt = $conn->prepare("
        SELECT cs.CRN, cs.Course_ID
        FROM COURSE_SECTIONS cs
        JOIN TIME_SLOT ts_exist ON cs.Time_Slot_ID = ts_exist.Time_Slot_ID
        JOIN TIME_SLOT ts_new ON ts_new.Time_Slot_ID = ?
        WHERE cs.Semester_ID = ? AND cs.Faculty_ID = ?
          AND (
              cs.Time_Slot_ID = ts_new.Time_Slot_ID
              OR (ts_exist.Time_Slot_Day = ts_new.Time_Slot_Day AND ts_exist.Period_ID = ts_new.Period_ID)
          )
        LIMIT 1
    ");
    $facConflictStmt->bind_param("iii", $time_slot_id, $semester_id, $faculty_id);
    $facConflictStmt->execute();
    $facConflict = $facConflictStmt->get_result()->fetch_assoc();
    $facConflictStmt->close();

    if ($facConflict) {
        throw new Exception("Time conflict: Faculty member is already teaching another section in this time slot.");
    }

    // 6. Constraint 6: Room Time-Slot Conflict Check
    // Verify room is not already booked during the same semester and time slot
    $roomConflictStmt = $conn->prepare("
        SELECT cs.CRN, cs.Course_ID
        FROM COURSE_SECTIONS cs
        JOIN TIME_SLOT ts_exist ON cs.Time_Slot_ID = ts_exist.Time_Slot_ID
        JOIN TIME_SLOT ts_new ON ts_new.Time_Slot_ID = ?
        WHERE cs.Semester_ID = ? AND cs.Room_ID = ?
          AND (
              cs.Time_Slot_ID = ts_new.Time_Slot_ID
              OR (ts_exist.Time_Slot_Day = ts_new.Time_Slot_Day AND ts_exist.Period_ID = ts_new.Period_ID)
          )
        LIMIT 1
    ");
    $roomConflictStmt->bind_param("iis", $time_slot_id, $semester_id, $room_id);
    $roomConflictStmt->execute();
    $roomConflict = $roomConflictStmt->get_result()->fetch_assoc();
    $roomConflictStmt->close();

    if ($roomConflict) {
        throw new Exception("Room conflict: The selected room is already booked during this time slot.");
    }

    // 7. Auto-Calculate Next CRN & Section Number
    // Section_Number: COALESCE(MAX(Section_Number), 0) + 1 for that Course_ID and Semester_ID
    $secNumStmt = $conn->prepare("
        SELECT COALESCE(MAX(Section_Number), 0) + 1 AS next_sec
        FROM COURSE_SECTIONS
        WHERE Course_ID = ? AND Semester_ID = ?
    ");
    $secNumStmt->bind_param("si", $course_id, $semester_id);
    $secNumStmt->execute();
    $nextSectionNumber = (int)$secNumStmt->get_result()->fetch_assoc()['next_sec'];
    $secNumStmt->close();

    // Unique CRN calculation
    $yrShort = substr((string)$semester_id, 2, 2);
    $termDigit = (substr((string)$semester_id, 4, 2) === '08') ? '2' : '1';
    $baseCrn = (int)($yrShort . $termDigit . '000');

    $crnStmt = $conn->prepare("
        SELECT COALESCE(MAX(CRN), ?) AS max_sem_crn
        FROM COURSE_SECTIONS
        WHERE Semester_ID = ?
    ");
    $crnStmt->bind_param("ii", $baseCrn, $semester_id);
    $crnStmt->execute();
    $maxSemCrn = (int)$crnStmt->get_result()->fetch_assoc()['max_sem_crn'];
    $crnStmt->close();

    $newCrn = max($baseCrn + 1, $maxSemCrn + 1);

    // Global collision protection loop
    $checkGlobalCrn = $conn->prepare("SELECT 1 FROM COURSE_SECTIONS WHERE CRN = ?");
    while (true) {
        $checkGlobalCrn->bind_param("i", $newCrn);
        $checkGlobalCrn->execute();
        if ($checkGlobalCrn->get_result()->num_rows === 0) {
            break;
        }
        $newCrn++;
    }
    $checkGlobalCrn->close();

    // 8. Constraint 8: Insert Record into COURSE_SECTIONS
    $status = 'Active';
    $insertStmt = $conn->prepare("
        INSERT INTO COURSE_SECTIONS (CRN, Course_ID, Section_Number, Faculty_ID, Time_Slot_ID, Room_ID, Semester_ID, Available_seats, Status)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $insertStmt->bind_param(
        "isiissiis",
        $newCrn,
        $course_id,
        $nextSectionNumber,
        $faculty_id,
        $time_slot_id,
        $room_id,
        $semester_id,
        $available_seats,
        $status
    );

    if (!$insertStmt->execute()) {
        throw new Exception("Database insertion failed: " . $insertStmt->error);
    }
    $insertStmt->close();

    $conn->commit();

    // Fetch human-friendly summary data for session display
    $tsDetailStmt = $conn->prepare("
        SELECT ts.Time_Slot_Day, p.Start_Time, p.End_Time
        FROM TIME_SLOT ts
        JOIN PERIOD p ON ts.Period_ID = p.Period_ID
        WHERE ts.Time_Slot_ID = ?
    ");
    $tsDetailStmt->bind_param("i", $time_slot_id);
    $tsDetailStmt->execute();
    $tsDetail = $tsDetailStmt->get_result()->fetch_assoc();
    $tsDetailStmt->close();

    $roomDetailStmt = $conn->prepare("
        SELECT b.Building_Name, r.Room_NO
        FROM ROOM r
        JOIN BUILDING b ON r.Building_ID = b.Building_ID
        WHERE r.Room_ID = ?
    ");
    $roomDetailStmt->bind_param("s", $room_id);
    $roomDetailStmt->execute();
    $roomDetail = $roomDetailStmt->get_result()->fetch_assoc();
    $roomDetailStmt->close();

    $timeSlotFormatted = $tsDetail 
        ? ($tsDetail['Time_Slot_Day'] . ' ' . date('g:i A', strtotime($tsDetail['Start_Time'])) . ' - ' . date('g:i A', strtotime($tsDetail['End_Time'])))
        : "Time Slot $time_slot_id";
    $roomFormatted = $roomDetail 
        ? ($roomDetail['Building_Name'] . ' ' . $roomDetail['Room_NO'])
        : $room_id;
    $facultyName = trim($facData['User_FName'] . ' ' . $facData['User_LName']);

    $_SESSION["success_message"] = "Course section created successfully! Assigned CRN: " . $newCrn;
    $_SESSION["new_section_data"] = [
        'crn'             => $newCrn,
        'course'          => $course_id . ' - ' . $courseData['Course_Name'] . ' (' . $courseData['Department_Name'] . ')',
        'section_number'  => $nextSectionNumber,
        'semester'        => $semInfo['Semester_Name'] . ' ' . $semInfo['Semester_Year'],
        'instructor'      => $facultyName . ' (' . $facData['Faculty_Type'] . ')',
        'time_slot'       => $timeSlotFormatted,
        'location'        => $roomFormatted,
        'available_seats' => $available_seats,
        'status'          => 'Active',
    ];

} catch (Exception $e) {
    if (isset($conn) && $conn->in_transaction) {
        $conn->rollback();
    }
    $_SESSION["error_message"] = $e->getMessage();
    unset($_SESSION["new_section_data"]);
}

header("Location: create_course_section.php");
exit;
