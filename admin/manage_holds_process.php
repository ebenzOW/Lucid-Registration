<?php
/**
 * Lucid University Course Registration System
 * Admin Student Holds POST Processor
 */

require_once 'admin_guard.php';
require_once '../db_connect.php';

$admin_id = (int)$userID;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['error_message'] = "Invalid request method.";
    header("Location: manage_holds.php");
    exit;
}

$action = $_POST['action'] ?? '';

try {
    // Enforce Security Level >= 2
    requireAdminWriteAccess();

    $conn->begin_transaction();

    if ($action === 'add_hold') {
        $student_id = filter_var($_POST['student_id'] ?? null, FILTER_VALIDATE_INT);
        if (!$student_id || $student_id <= 0) {
            throw new Exception("Please provide a valid numeric Student ID.");
        }

        // Verify student exists in STUDENT table
        $checkStmt = $conn->prepare("SELECT Student_ID FROM STUDENT WHERE Student_ID = ?");
        $checkStmt->bind_param("i", $student_id);
        $checkStmt->execute();
        $studentExists = $checkStmt->get_result()->fetch_assoc();
        $checkStmt->close();

        if (!$studentExists) {
            throw new Exception("Student ID {$student_id} was not found in the student registry.");
        }

        $hold_ids = $_POST['hold_ids'] ?? [];
        if (!is_array($hold_ids) || empty($hold_ids)) {
            throw new Exception("Please select at least one hold type to apply.");
        }

        $appliedNames = [];

        foreach ($hold_ids as $hidRaw) {
            $hid = filter_var($hidRaw, FILTER_VALIDATE_INT);
            if (!$hid || $hid <= 0) continue;

            // Verify hold exists
            $hStmt = $conn->prepare("SELECT Hold_Type FROM HOLDS WHERE Hold_ID = ?");
            $hStmt->bind_param("i", $hid);
            $hStmt->execute();
            $hRow = $hStmt->get_result()->fetch_assoc();
            $hStmt->close();

            if (!$hRow) {
                continue;
            }

            $hold_type = $hRow['Hold_Type'];

            // Check if already placed on this student (pessimistic lock)
            $existStmt = $conn->prepare("SELECT 1 FROM STUDENT_HOLDS WHERE Student_ID = ? AND Hold_ID = ? FOR UPDATE");
            $existStmt->bind_param("ii", $student_id, $hid);
            $existStmt->execute();
            $alreadyActive = $existStmt->get_result()->fetch_assoc();
            $existStmt->close();

            if (!$alreadyActive) {
                // Insert into STUDENT_HOLDS
                $insStmt = $conn->prepare("INSERT INTO STUDENT_HOLDS (Hold_ID, Student_ID, Hold_Date) VALUES (?, ?, CURRENT_DATE())");
                $insStmt->bind_param("ii", $hid, $student_id);
                $insStmt->execute();
                $insStmt->close();

                // Audit log insertion
                $changedAttr = substr("Applied Hold: " . $hold_type, 0, 50);
                $logStmt = $conn->prepare("INSERT INTO USER_UPDATE_LOG (Admin_ID, User_ID, Changed_Attribute, Date_Of_Change) VALUES (?, ?, ?, NOW())");
                $logStmt->bind_param("iis", $admin_id, $student_id, $changedAttr);
                $logStmt->execute();
                $logStmt->close();

                $appliedNames[] = $hold_type;
            }
        }

        if (empty($appliedNames)) {
            throw new Exception("The selected hold(s) are already active on Student {$student_id}.");
        }

        $conn->commit();
        $_SESSION['success_message'] = "Successfully applied hold(s) (" . implode(', ', $appliedNames) . ") to Student {$student_id}.";

    } elseif ($action === 'remove_hold') {
        $student_id = filter_var($_POST['student_id'] ?? null, FILTER_VALIDATE_INT);
        $hold_id    = filter_var($_POST['hold_id'] ?? null, FILTER_VALIDATE_INT);

        if (!$student_id || !$hold_id) {
            throw new Exception("Invalid parameters provided for hold removal.");
        }

        // Verify hold exists on student
        $verStmt = $conn->prepare("
            SELECT h.Hold_Type
            FROM STUDENT_HOLDS sh
            JOIN HOLDS h ON sh.Hold_ID = h.Hold_ID
            WHERE sh.Student_ID = ? AND sh.Hold_ID = ?
            FOR UPDATE
        ");
        $verStmt->bind_param("ii", $student_id, $hold_id);
        $verStmt->execute();
        $holdRow = $verStmt->get_result()->fetch_assoc();
        $verStmt->close();

        if (!$holdRow) {
            throw new Exception("The specified hold does not exist for Student {$student_id}.");
        }

        $hold_type = $holdRow['Hold_Type'];

        // Remove from STUDENT_HOLDS
        $delStmt = $conn->prepare("DELETE FROM STUDENT_HOLDS WHERE Student_ID = ? AND Hold_ID = ?");
        $delStmt->bind_param("ii", $student_id, $hold_id);
        $delStmt->execute();
        $delStmt->close();

        // Audit log insertion
        $changedAttr = substr("Removed Hold: " . $hold_type, 0, 50);
        $logStmt = $conn->prepare("INSERT INTO USER_UPDATE_LOG (Admin_ID, User_ID, Changed_Attribute, Date_Of_Change) VALUES (?, ?, ?, NOW())");
        $logStmt->bind_param("iis", $admin_id, $student_id, $changedAttr);
        $logStmt->execute();
        $logStmt->close();

        $conn->commit();
        $_SESSION['success_message'] = "Successfully removed {$hold_type} hold from Student {$student_id}.";

    } else {
        throw new Exception("Unrecognized action requested.");
    }

} catch (Exception $e) {
    if (isset($conn) && $conn->in_transaction) {
        $conn->rollback();
    }
    $_SESSION['error_message'] = $e->getMessage();
}

header("Location: manage_holds.php");
exit;

