<?php
/**
 * Lucid University Course Registration System
 * Admin Campus Facilities Management — Request Processor
 *
 * Handles creation and modification of campus buildings and rooms with:
 * - Transactional integrity (SELECT ... FOR UPDATE)
 * - Security level enforcement (Security Level >= 2 required for write access)
 * - Subtype synchronization (OFFICE, LAB, LECTURE)
 * - Foreign key and capacity validation
 * - Standard Post-Redirect-Get pattern with session flash alerts
 */

require_once 'admin_guard.php';
require_once '../db_connect.php';

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: manage_facilities.php");
    exit;
}

$action = trim($_POST['action'] ?? '');

try {
    // 1. Enforce admin write permission (Security Level >= 2)
    requireAdminWriteAccess();

    if ($action === 'create_building') {
        $building_id    = strtoupper(trim($_POST['building_id'] ?? ''));
        $building_name  = trim($_POST['building_name'] ?? '');
        $usage_purpose  = trim($_POST['usage_purpose'] ?? '');

        if ($building_id === '' || strlen($building_id) > 10 || !preg_match('/^[A-Z0-9\-]+$/', $building_id)) {
            throw new Exception("Building ID must be 1 to 10 alphanumeric characters or hyphens.");
        }
        if ($building_name === '' || strlen($building_name) > 100) {
            throw new Exception("Building Name is required and must not exceed 100 characters.");
        }
        if (strlen($usage_purpose) > 100) {
            throw new Exception("Usage Purpose must not exceed 100 characters.");
        }

        $conn->begin_transaction();

        // Check duplicate Building ID
        $checkStmt = $conn->prepare("SELECT 1 FROM BUILDING WHERE Building_ID = ? FOR UPDATE");
        $checkStmt->bind_param("s", $building_id);
        $checkStmt->execute();
        if ($checkStmt->get_result()->num_rows > 0) {
            $checkStmt->close();
            throw new Exception("A building with ID '{$building_id}' already exists.");
        }
        $checkStmt->close();

        $insStmt = $conn->prepare("INSERT INTO BUILDING (Building_ID, Building_Name, Usage_Purpose) VALUES (?, ?, ?)");
        $insStmt->bind_param("sss", $building_id, $building_name, $usage_purpose);
        if (!$insStmt->execute()) {
            throw new Exception("Failed to create building: " . $insStmt->error);
        }
        $insStmt->close();

        $conn->commit();

        $_SESSION['success_message'] = "Building '{$building_name}' ({$building_id}) was successfully created!";
        header("Location: manage_facilities.php");
        exit;

    } elseif ($action === 'update_building') {
        $building_id    = strtoupper(trim($_POST['building_id'] ?? ''));
        $building_name  = trim($_POST['building_name'] ?? '');
        $usage_purpose  = trim($_POST['usage_purpose'] ?? '');

        if ($building_id === '') {
            throw new Exception("Building ID is required for update.");
        }
        if ($building_name === '' || strlen($building_name) > 100) {
            throw new Exception("Building Name is required and must not exceed 100 characters.");
        }
        if (strlen($usage_purpose) > 100) {
            throw new Exception("Usage Purpose must not exceed 100 characters.");
        }

        $conn->begin_transaction();

        $checkStmt = $conn->prepare("SELECT 1 FROM BUILDING WHERE Building_ID = ? FOR UPDATE");
        $checkStmt->bind_param("s", $building_id);
        $checkStmt->execute();
        if ($checkStmt->get_result()->num_rows === 0) {
            $checkStmt->close();
            throw new Exception("Building '{$building_id}' does not exist.");
        }
        $checkStmt->close();

        $updStmt = $conn->prepare("UPDATE BUILDING SET Building_Name = ?, Usage_Purpose = ? WHERE Building_ID = ?");
        $updStmt->bind_param("sss", $building_name, $usage_purpose, $building_id);
        if (!$updStmt->execute()) {
            throw new Exception("Failed to update building: " . $updStmt->error);
        }
        $updStmt->close();

        $conn->commit();

        $_SESSION['success_message'] = "Building '{$building_id}' was successfully updated!";
        header("Location: manage_facilities.php");
        exit;

    } elseif ($action === 'create_room') {
        $room_id     = strtoupper(trim($_POST['room_id'] ?? ''));
        $building_id = strtoupper(trim($_POST['building_id'] ?? ''));
        $room_no     = trim($_POST['room_no'] ?? '');
        $room_type   = ucfirst(strtolower(trim($_POST['room_type'] ?? '')));
        $capacity    = (int)($_POST['capacity'] ?? 0);

        if ($room_id === '' || strlen($room_id) > 15 || !preg_match('/^[A-Z0-9\-]+$/', $room_id)) {
            throw new Exception("Room ID must be 1 to 15 alphanumeric characters or hyphens.");
        }
        if ($building_id === '') {
            throw new Exception("Please select a valid building.");
        }
        if ($room_no === '' || strlen($room_no) > 10) {
            throw new Exception("Room Number must be 1 to 10 characters.");
        }
        if (!in_array($room_type, ['Lecture', 'Lab', 'Office'], true)) {
            throw new Exception("Room Type must be Lecture, Lab, or Office.");
        }
        if ($capacity < 1) {
            throw new Exception("Room capacity must be at least 1.");
        }

        $conn->begin_transaction();

        // Verify Building exists
        $bStmt = $conn->prepare("SELECT 1 FROM BUILDING WHERE Building_ID = ? FOR UPDATE");
        $bStmt->bind_param("s", $building_id);
        $bStmt->execute();
        if ($bStmt->get_result()->num_rows === 0) {
            $bStmt->close();
            throw new Exception("The selected building '{$building_id}' does not exist.");
        }
        $bStmt->close();

        // Check duplicate Room_ID
        $rCheck = $conn->prepare("SELECT 1 FROM ROOM WHERE Room_ID = ? FOR UPDATE");
        $rCheck->bind_param("s", $room_id);
        $rCheck->execute();
        if ($rCheck->get_result()->num_rows > 0) {
            $rCheck->close();
            throw new Exception("A room with ID '{$room_id}' already exists.");
        }
        $rCheck->close();

        // Check duplicate (Building_ID, Room_NO)
        $noCheck = $conn->prepare("SELECT 1 FROM ROOM WHERE Building_ID = ? AND Room_NO = ? FOR UPDATE");
        $noCheck->bind_param("ss", $building_id, $room_no);
        $noCheck->execute();
        if ($noCheck->get_result()->num_rows > 0) {
            $noCheck->close();
            throw new Exception("Room number '{$room_no}' already exists in building '{$building_id}'.");
        }
        $noCheck->close();

        // Insert into ROOM
        $insRoom = $conn->prepare("INSERT INTO ROOM (Room_ID, Building_ID, Room_NO, Room_Type) VALUES (?, ?, ?, ?)");
        $insRoom->bind_param("ssss", $room_id, $building_id, $room_no, $room_type);
        if (!$insRoom->execute()) {
            throw new Exception("Failed to insert room record: " . $insRoom->error);
        }
        $insRoom->close();

        // Insert into Subtype table
        if ($room_type === 'Lecture') {
            $subStmt = $conn->prepare("INSERT INTO LECTURE (Lecture_ID, Lecture_Seats) VALUES (?, ?)");
            $subStmt->bind_param("si", $room_id, $capacity);
            $subStmt->execute();
            $subStmt->close();
        } elseif ($room_type === 'Lab') {
            $subStmt = $conn->prepare("INSERT INTO LAB (Lab_ID, Lab_Stations) VALUES (?, ?)");
            $subStmt->bind_param("si", $room_id, $capacity);
            $subStmt->execute();
            $subStmt->close();
        } elseif ($room_type === 'Office') {
            $subStmt = $conn->prepare("INSERT INTO OFFICE (Office_ID, Office_Desks) VALUES (?, ?)");
            $subStmt->bind_param("si", $room_id, $capacity);
            $subStmt->execute();
            $subStmt->close();
        }

        $conn->commit();

        $_SESSION['success_message'] = "Room '{$room_id}' ({$room_type}, capacity: {$capacity}) was successfully added to building '{$building_id}'!";
        header("Location: manage_facilities.php");
        exit;

    } elseif ($action === 'update_room') {
        $room_id     = strtoupper(trim($_POST['room_id'] ?? ''));
        $building_id = strtoupper(trim($_POST['building_id'] ?? ''));
        $room_no     = trim($_POST['room_no'] ?? '');
        $room_type   = ucfirst(strtolower(trim($_POST['room_type'] ?? '')));
        $capacity    = (int)($_POST['capacity'] ?? 0);

        if ($room_id === '') {
            throw new Exception("Room ID is required for update.");
        }
        if ($building_id === '') {
            throw new Exception("Please select a valid building.");
        }
        if ($room_no === '' || strlen($room_no) > 10) {
            throw new Exception("Room Number must be 1 to 10 characters.");
        }
        if (!in_array($room_type, ['Lecture', 'Lab', 'Office'], true)) {
            throw new Exception("Room Type must be Lecture, Lab, or Office.");
        }
        if ($capacity < 1) {
            throw new Exception("Room capacity must be at least 1.");
        }

        $conn->begin_transaction();

        // Lock existing Room row
        $rStmt = $conn->prepare("SELECT Room_Type, Building_ID, Room_NO FROM ROOM WHERE Room_ID = ? FOR UPDATE");
        $rStmt->bind_param("s", $room_id);
        $rStmt->execute();
        $oldRoom = $rStmt->get_result()->fetch_assoc();
        $rStmt->close();

        if (!$oldRoom) {
            throw new Exception("Room '{$room_id}' does not exist.");
        }

        $oldType = ucfirst(strtolower((string)$oldRoom['Room_Type']));

        // Verify target Building exists
        $bStmt = $conn->prepare("SELECT 1 FROM BUILDING WHERE Building_ID = ? FOR UPDATE");
        $bStmt->bind_param("s", $building_id);
        $bStmt->execute();
        if ($bStmt->get_result()->num_rows === 0) {
            $bStmt->close();
            throw new Exception("Building '{$building_id}' does not exist.");
        }
        $bStmt->close();

        // Enforce uniqueness of (Building_ID, Room_NO) across different Room_IDs
        $dupCheck = $conn->prepare("SELECT 1 FROM ROOM WHERE Building_ID = ? AND Room_NO = ? AND Room_ID != ? FOR UPDATE");
        $dupCheck->bind_param("sss", $building_id, $room_no, $room_id);
        $dupCheck->execute();
        if ($dupCheck->get_result()->num_rows > 0) {
            $dupCheck->close();
            throw new Exception("Room number '{$room_no}' is already in use in building '{$building_id}'.");
        }
        $dupCheck->close();

        // Validate type transitions against foreign key constraints
        if ($oldType === 'Office' && in_array($room_type, ['Lecture', 'Lab'], true)) {
            // Verify FACULTY (Office_ID) and DEPARTMENT (Office_ID) have 0 references
            $facCheck = $conn->prepare("SELECT COUNT(*) AS Cnt FROM FACULTY WHERE Office_ID = ?");
            $facCheck->bind_param("s", $room_id);
            $facCheck->execute();
            $facCnt = (int)($facCheck->get_result()->fetch_assoc()['Cnt'] ?? 0);
            $facCheck->close();

            $deptCheck = $conn->prepare("SELECT COUNT(*) AS Cnt FROM DEPARTMENT WHERE Office_ID = ?");
            $deptCheck->bind_param("s", $room_id);
            $deptCheck->execute();
            $deptCnt = (int)($deptCheck->get_result()->fetch_assoc()['Cnt'] ?? 0);
            $deptCheck->close();

            if ($facCnt > 0 || $deptCnt > 0) {
                throw new Exception("Cannot convert Office '{$room_id}' to an instructional classroom/lab: it is currently assigned as an office to {$facCnt} faculty member(s) and {$deptCnt} department(s).");
            }
        } elseif (in_array($oldType, ['Lecture', 'Lab'], true) && $room_type === 'Office') {
            // Verify COURSE_SECTIONS (Room_ID) has 0 references
            $secCheck = $conn->prepare("SELECT COUNT(*) AS Cnt FROM COURSE_SECTIONS WHERE Room_ID = ?");
            $secCheck->bind_param("s", $room_id);
            $secCheck->execute();
            $secCnt = (int)($secCheck->get_result()->fetch_assoc()['Cnt'] ?? 0);
            $secCheck->close();

            if ($secCnt > 0) {
                throw new Exception("Cannot convert room '{$room_id}' to an Office: {$secCnt} course section(s) are currently scheduled in this room.");
            }
        }

        // Capacity check for Lecture / Lab
        if (in_array($room_type, ['Lecture', 'Lab'], true)) {
            $maxCheck = $conn->prepare("SELECT MAX(Available_seats) AS Max_Seats FROM COURSE_SECTIONS WHERE Room_ID = ?");
            $maxCheck->bind_param("s", $room_id);
            $maxCheck->execute();
            $maxRow = $maxCheck->get_result()->fetch_assoc();
            $maxCheck->close();
            $maxSeats = isset($maxRow['Max_Seats']) ? (int)$maxRow['Max_Seats'] : 0;

            if ($maxSeats > 0 && $capacity < $maxSeats) {
                throw new Exception("Specified capacity ({$capacity}) cannot be less than the maximum seats configured for scheduled course sections in this room ({$maxSeats}).");
            }
        }

        // Update ROOM table
        $updRoom = $conn->prepare("UPDATE ROOM SET Building_ID = ?, Room_NO = ?, Room_Type = ? WHERE Room_ID = ?");
        $updRoom->bind_param("ssss", $building_id, $room_no, $room_type, $room_id);
        if (!$updRoom->execute()) {
            throw new Exception("Failed to update room: " . $updRoom->error);
        }
        $updRoom->close();

        // Synchronize subtype tables
        if ($room_type === 'Lecture') {
            $lecStmt = $conn->prepare("INSERT INTO LECTURE (Lecture_ID, Lecture_Seats) VALUES (?, ?) ON DUPLICATE KEY UPDATE Lecture_Seats = VALUES(Lecture_Seats)");
            $lecStmt->bind_param("si", $room_id, $capacity);
            $lecStmt->execute();
            $lecStmt->close();

            $delLab = $conn->prepare("DELETE FROM LAB WHERE Lab_ID = ?");
            $delLab->bind_param("s", $room_id);
            $delLab->execute();
            $delLab->close();

            $delOff = $conn->prepare("DELETE FROM OFFICE WHERE Office_ID = ?");
            $delOff->bind_param("s", $room_id);
            $delOff->execute();
            $delOff->close();
        } elseif ($room_type === 'Lab') {
            $labStmt = $conn->prepare("INSERT INTO LAB (Lab_ID, Lab_Stations) VALUES (?, ?) ON DUPLICATE KEY UPDATE Lab_Stations = VALUES(Lab_Stations)");
            $labStmt->bind_param("si", $room_id, $capacity);
            $labStmt->execute();
            $labStmt->close();

            $delLec = $conn->prepare("DELETE FROM LECTURE WHERE Lecture_ID = ?");
            $delLec->bind_param("s", $room_id);
            $delLec->execute();
            $delLec->close();

            $delOff = $conn->prepare("DELETE FROM OFFICE WHERE Office_ID = ?");
            $delOff->bind_param("s", $room_id);
            $delOff->execute();
            $delOff->close();
        } elseif ($room_type === 'Office') {
            $offStmt = $conn->prepare("INSERT INTO OFFICE (Office_ID, Office_Desks) VALUES (?, ?) ON DUPLICATE KEY UPDATE Office_Desks = VALUES(Office_Desks)");
            $offStmt->bind_param("si", $room_id, $capacity);
            $offStmt->execute();
            $offStmt->close();

            $delLec = $conn->prepare("DELETE FROM LECTURE WHERE Lecture_ID = ?");
            $delLec->bind_param("s", $room_id);
            $delLec->execute();
            $delLec->close();

            $delLab = $conn->prepare("DELETE FROM LAB WHERE Lab_ID = ?");
            $delLab->bind_param("s", $room_id);
            $delLab->execute();
            $delLab->close();
        }

        $conn->commit();

        $_SESSION['success_message'] = "Room '{$room_id}' was successfully updated!";
        header("Location: manage_facilities.php");
        exit;

    } else {
        throw new Exception("Unknown action specified.");
    }

} catch (Exception $e) {
    if (isset($conn) && $conn->in_transaction) {
        $conn->rollback();
    }
    $_SESSION['error_message'] = $e->getMessage();
    header("Location: manage_facilities.php");
    exit;
}
