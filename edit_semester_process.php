<?php
/**
 * Lucid University Course Registration System
 * Admin Portal - Process Semester & Calendar Deadlines Update
 */

require_once 'admin_guard.php';
require_once '../db_connect.php';

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: semesters.php");
    exit;
}

$semester_id = isset($_POST['semester_id']) ? (int)$_POST['semester_id'] : 0;
$isNew = !empty($_POST['is_new']) && $_POST['is_new'] === '1';

try {
    // 1. Enforce admin write permission (Security Level >= 2)
    requireAdminWriteAccess();

    if ($semester_id <= 0) {
        throw new Exception("Invalid Semester ID provided.");
    }

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

    if (empty($last_day_reg) || empty($last_day_drop) || empty($midterm_grades) || empty($final_grades)) {
        throw new Exception("Please specify all academic calendar deadlines for this term.");
    }

    // 3. Date chronology checks
    if ($start_date > $end_date) {
        throw new Exception("Term Start Date ({$start_date}) cannot be after Term End Date ({$end_date}).");
    }

    if ($last_day_reg > $last_day_drop) {
        throw new Exception("Registration cutoff ({$last_day_reg}) cannot be after the add/drop deadline ({$last_day_drop}).");
    }

    if ($midterm_grades > $final_grades) {
        throw new Exception("Midterm grading recording date ({$midterm_grades}) cannot be after final grading date ({$final_grades}).");
    }

    $today = date('Y-m-d');

    // 4. Past term constraint validation per SRS
    if (!$isNew) {
        $checkStmt = $conn->prepare("SELECT End_Date FROM SEMESTER WHERE Semester_ID = ?");
        $checkStmt->bind_param("i", $semester_id);
        $checkStmt->execute();
        $existing = $checkStmt->get_result()->fetch_assoc();
        $checkStmt->close();

        if ($existing && $today > $existing['End_Date']) {
            throw new Exception("Cannot modify past semester schedules per university SRS constraints.");
        }
    }

    $conn->begin_transaction();

    if ($isNew) {
        // Verify Semester_ID uniqueness
        $dupStmt = $conn->prepare("SELECT Semester_ID FROM SEMESTER WHERE Semester_ID = ?");
        $dupStmt->bind_param("i", $semester_id);
        $dupStmt->execute();
        if ($dupStmt->get_result()->num_rows > 0) {
            $dupStmt->close();
            throw new Exception("A semester with ID {$semester_id} already exists in the system.");
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

        $_SESSION['success_message'] = "Academic Semester {$semester_name} {$semester_year} (ID: {$semester_id}) and calendar deadlines successfully configured!";
    } else {
        // Update SEMESTER
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

        $_SESSION['success_message'] = "Academic Semester {$semester_name} {$semester_year} (ID: {$semester_id}) dates and deadlines successfully updated!";
    }

    $conn->commit();
    header("Location: semesters.php");
    exit;

} catch (Exception $e) {
    if (isset($conn) && $conn instanceof mysqli && $conn->connect_errno === 0) {
        @$conn->rollback();
    }
    $_SESSION['error_message'] = $e->getMessage();
    if ($semester_id > 0 && !$isNew) {
        header("Location: edit_semester.php?id=" . $semester_id);
    } else {
        header("Location: edit_semester.php");
    }
    exit;
}
?>
