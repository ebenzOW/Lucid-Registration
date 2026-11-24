<?php
/**
 * Lucid University Course Registration System
 * Admin Portal - Process Semester & Calendar Deadlines Update
 */

require_once 'admin_guard.php';
require_once '../db_connect.php';
require_once __DIR__ . '/../includes/auth_helpers.php';
require_once __DIR__ . '/../includes/audit_helpers.php';

$admin_id = (int)$userID;

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: semesters.php");
    exit;
}

$isNew = !empty($_POST['is_new']) && $_POST['is_new'] === '1';
$semester_id = isset($_POST['semester_id']) ? (int)$_POST['semester_id'] : 0;
$inTransaction = false;

try {
    // 1. Enforce admin write permission (Security Level >= 2)
    requireAdminWriteAccess();

    $semester_name = trim($_POST['semester_name'] ?? '');
    $semester_year = isset($_POST['semester_year']) ? (int)$_POST['semester_year'] : 0;
    $start_date    = trim($_POST['start_date'] ?? '');
    $end_date      = trim($_POST['end_date'] ?? '');

    $last_day_reg   = trim($_POST['last_day_registration'] ?? '');
    $last_day_drop  = trim($_POST['last_day_dropping'] ?? '');
    $midterm_grades = trim($_POST['date_midterm_grades'] ?? '');
    $final_grades   = trim($_POST['date_final_grades'] ?? '');

    // 2. Validate required inputs
    if (empty($semester_name) || $semester_year <= 0 || empty($start_date) || empty($end_date)) {
        throw new Exception("Please fill in all required semester fields.");
    }

    if ($semester_year < 2020 || $semester_year > 2050) {
        throw new Exception("Academic year must be between 2020 and 2050.");
    }

    if (empty($last_day_reg) || empty($last_day_drop) || empty($midterm_grades) || empty($final_grades)) {
        throw new Exception("Please specify all academic calendar deadlines for this term.");
    }

    // Term codes mapping: Spring => 01, Summer => 05, Fall => 08, Winter => 12
    $termCodes = [
        'Spring' => '01',
        'Summer' => '05',
        'Fall'   => '08',
        'Winter' => '12',
    ];

    if (!isset($termCodes[$semester_name])) {
        throw new Exception("Invalid semester term selected: '{$semester_name}'.");
    }

    // Compute or verify Semester_ID strictly
    if ($isNew) {
        $semester_id = (int)($semester_year . $termCodes[$semester_name]);
    } else {
        if ($semester_id <= 0) {
            throw new Exception("Invalid Semester ID provided.");
        }
    }

    // 3. Sensible calendar window validation
    $y = $semester_year;
    switch ($semester_name) {
        case 'Spring':
            $sMin = "{$y}-01-01"; $sMax = "{$y}-02-29";
            $eMin = "{$y}-04-01"; $eMax = "{$y}-06-15";
            break;
        case 'Summer':
            $sMin = "{$y}-05-01"; $sMax = "{$y}-06-30";
            $eMin = "{$y}-07-01"; $eMax = "{$y}-08-31";
            break;
        case 'Fall':
            $sMin = "{$y}-08-01"; $sMax = "{$y}-09-30";
            $eMin = "{$y}-11-15"; $eMax = "{$y}-12-31";
            break;
        case 'Winter':
            $yNext = $y + 1;
            $sMin = "{$y}-12-01"; $sMax = "{$y}-12-31";
            $eMin = "{$yNext}-01-05"; $eMax = "{$yNext}-01-31";
            break;
        default:
            throw new Exception("Invalid semester term selected.");
    }

    if ($start_date < $sMin || $start_date > $sMax) {
        throw new Exception("Term Start Date ({$start_date}) is outside the permitted window ({$sMin} to {$sMax}) for {$semester_name} {$y}.");
    }
    if ($end_date < $eMin || $end_date > $eMax) {
        throw new Exception("Term End Date ({$end_date}) is outside the permitted window ({$eMin} to {$eMax}) for {$semester_name} {$y}.");
    }

    // 4. Chronological date sequence checks
    if ($start_date >= $end_date) {
        throw new Exception("Term Start Date ({$start_date}) must precede Term End Date ({$end_date}).");
    }

    if ($start_date > $last_day_reg) {
        throw new Exception("Term Start Date ({$start_date}) cannot be after the registration deadline ({$last_day_reg}).");
    }

    if ($last_day_reg > $last_day_drop) {
        throw new Exception("Registration cutoff ({$last_day_reg}) cannot be after the add/drop deadline ({$last_day_drop}).");
    }

    if ($last_day_drop > $midterm_grades) {
        throw new Exception("Last day to drop courses ({$last_day_drop}) cannot be after the midterm grade recording date ({$midterm_grades}).");
    }

    if ($midterm_grades >= $final_grades) {
        throw new Exception("Midterm grading recording date ({$midterm_grades}) must precede final grading date ({$final_grades}).");
    }

    if ($final_grades > $end_date) {
        throw new Exception("Final grades deadline ({$final_grades}) cannot be after term end date ({$end_date}).");
    }

    $today = date('Y-m-d');

    $conn->begin_transaction();
    $inTransaction = true;

    if ($isNew) {
        // Verify Semester_ID uniqueness with FOR UPDATE
        $dupStmt = $conn->prepare("SELECT Semester_ID FROM SEMESTER WHERE Semester_ID = ? FOR UPDATE");
        $dupStmt->bind_param("i", $semester_id);
        $dupStmt->execute();
        if ($dupStmt->get_result()->num_rows > 0) {
            $dupStmt->close();
            throw new Exception("A semester with ID {$semester_id} ({$semester_name} {$semester_year}) already exists in the system.");
        }
        $dupStmt->close();

        // Insert into SEMESTER
        $insStmt = $conn->prepare("
            INSERT INTO SEMESTER (Semester_ID, Semester_Name, Semester_Year, Start_Date, End_Date)
            VALUES (?, ?, ?, ?, ?)
        ");
        $insStmt->bind_param("isiss", $semester_id, $semester_name, $semester_year, $start_date, $end_date);
        if (!$insStmt->execute()) {
            throw new Exception("Failed to insert semester: " . $insStmt->error);
        }
        $insStmt->close();

        // Insert into INFORMATION_RELATION
        $irStmt = $conn->prepare("
            INSERT INTO INFORMATION_RELATION (
                Semester_ID, Last_Day_Of_Registration, Last_Day_Of_Dropping_Course,
                Date_Of_Midterm_Grade_Recording, Date_Of_Final_Grades_Recording
            ) VALUES (?, ?, ?, ?, ?)
        ");
        $irStmt->bind_param("issss", $semester_id, $last_day_reg, $last_day_drop, $midterm_grades, $final_grades);
        if (!$irStmt->execute()) {
            throw new Exception("Failed to insert academic deadlines: " . $irStmt->error);
        }
        $irStmt->close();

        logAdminActivity($conn, $admin_id, 'Semester', $semester_id, 'Create', "{$semester_name} {$semester_year} ({$start_date} to {$end_date})");

        $conn->commit();
        $inTransaction = false;

        $_SESSION['success_message'] = "Academic Semester {$semester_name} {$semester_year} (ID: {$semester_id}) and calendar deadlines successfully configured!";
    } else {
        // Verify existence & past term constraint with FOR UPDATE
        $checkStmt = $conn->prepare("SELECT Semester_ID, End_Date FROM SEMESTER WHERE Semester_ID = ? FOR UPDATE");
        $checkStmt->bind_param("i", $semester_id);
        $checkStmt->execute();
        $existing = $checkStmt->get_result()->fetch_assoc();
        $checkStmt->close();

        if (!$existing) {
            throw new Exception("Semester ID {$semester_id} was not found.");
        }

        if ($today > $existing['End_Date']) {
            throw new Exception("Cannot modify past semester schedules per university policy.");
        }

        // Update SEMESTER (Semester_ID is immutable)
        $updStmt = $conn->prepare("
            UPDATE SEMESTER 
            SET Semester_Name = ?, Semester_Year = ?, Start_Date = ?, End_Date = ?
            WHERE Semester_ID = ?
        ");
        $updStmt->bind_param("sissi", $semester_name, $semester_year, $start_date, $end_date, $semester_id);
        if (!$updStmt->execute()) {
            throw new Exception("Failed to update semester: " . $updStmt->error);
        }
        $updStmt->close();

        // Update or Insert into INFORMATION_RELATION
        $irUpdStmt = $conn->prepare("
            INSERT INTO INFORMATION_RELATION (
                Semester_ID, Last_Day_Of_Registration, Last_Day_Of_Dropping_Course,
                Date_Of_Midterm_Grade_Recording, Date_Of_Final_Grades_Recording
            ) VALUES (?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                Last_Day_Of_Registration = VALUES(Last_Day_Of_Registration),
                Last_Day_Of_Dropping_Course = VALUES(Last_Day_Of_Dropping_Course),
                Date_Of_Midterm_Grade_Recording = VALUES(Date_Of_Midterm_Grade_Recording),
                Date_Of_Final_Grades_Recording = VALUES(Date_Of_Final_Grades_Recording)
        ");
        $irUpdStmt->bind_param("issss", $semester_id, $last_day_reg, $last_day_drop, $midterm_grades, $final_grades);
        if (!$irUpdStmt->execute()) {
            throw new Exception("Failed to update calendar deadlines: " . $irUpdStmt->error);
        }
        $irUpdStmt->close();

        logAdminActivity($conn, $admin_id, 'Semester', $semester_id, 'Update', "{$semester_name} {$semester_year} ({$start_date} to {$end_date})");

        $conn->commit();
        $inTransaction = false;

        $_SESSION['success_message'] = "Academic Semester {$semester_name} {$semester_year} (ID: {$semester_id}) dates and deadlines successfully updated!";
    }

    header("Location: semesters.php");
    exit;

} catch (Exception $e) {
    if ($inTransaction) {
        $conn->rollback();
    }
    $_SESSION['error_message'] = $e->getMessage();
    if ($semester_id > 0 && !$isNew) {
        header("Location: edit_semester.php?id=" . $semester_id);
    } else {
        header("Location: edit_semester.php");
    }
    exit;
}
