<?php
/**
 * Lucid University Course Registration System
 * Admin Course Catalog & Prerequisite POST Processor
 */

require_once 'admin_guard.php';
require_once '../db_connect.php';

$admin_id = (int)$userID;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['error_message'] = "Invalid request method.";
    header("Location: manage_courses.php");
    exit;
}

$action = $_POST['action'] ?? '';

try {
    // Enforce Security Level >= 2
    requireAdminWriteAccess();

    $conn->begin_transaction();

    if ($action === 'create_course') {
        $course_id          = strtoupper(trim($_POST['course_id'] ?? ''));
        $course_name        = trim($_POST['course_name'] ?? '');
        $course_type        = trim($_POST['course_type'] ?? '');
        $course_credits     = filter_var($_POST['course_credits'] ?? null, FILTER_VALIDATE_INT);
        $department_id      = filter_var($_POST['department_id'] ?? null, FILTER_VALIDATE_INT);
        $course_description = trim($_POST['course_description'] ?? '');
        $prerequisite_ids   = $_POST['prerequisite_ids'] ?? [];

        // 1. Validation
        if (empty($course_id) || strlen($course_id) > 15 || !preg_match('/^[A-Z0-9]+$/', $course_id)) {
            throw new Exception("Course ID must be alphanumeric and up to 15 characters (e.g. CS101, MA150).");
        }

        if (empty($course_name) || strlen($course_name) > 100) {
            throw new Exception("Course Name is required and must not exceed 100 characters.");
        }

        if (!in_array($course_type, ['Undergraduate', 'Graduate'], true)) {
            throw new Exception("Course Type must be either Undergraduate or Graduate.");
        }

        if (!$course_credits || $course_credits < 1 || $course_credits > 6) {
            throw new Exception("Course Credits must be between 1 and 6.");
        }

        if (!$department_id || $department_id <= 0) {
            throw new Exception("Please select a valid Department.");
        }

        // Verify department exists
        $dStmt = $conn->prepare("SELECT Department_ID FROM DEPARTMENT WHERE Department_ID = ?");
        $dStmt->bind_param("i", $department_id);
        $dStmt->execute();
        $deptFound = $dStmt->get_result()->fetch_assoc();
        $dStmt->close();
        if (!$deptFound) {
            throw new Exception("The selected Department does not exist.");
        }

        // Verify Course_ID uniqueness
        $chkStmt = $conn->prepare("SELECT Course_ID FROM COURSES WHERE Course_ID = ?");
        $chkStmt->bind_param("s", $course_id);
        $chkStmt->execute();
        $exists = $chkStmt->get_result()->fetch_assoc();
        $chkStmt->close();
        if ($exists) {
            throw new Exception("Course ID '{$course_id}' already exists in the course catalog.");
        }

        // 2. Prerequisite validations
        $cleanPrereqs = [];
        if (is_array($prerequisite_ids)) {
            foreach ($prerequisite_ids as $pidRaw) {
                $pid = strtoupper(trim($pidRaw));
                if (empty($pid)) continue;

                // Check self-prerequisite
                if ($pid === $course_id) {
                    throw new Exception("A course cannot have itself as a prerequisite.");
                }

                // Check prerequisite existence and course type
                $chkP = $conn->prepare("SELECT Course_ID, Course_Type FROM COURSES WHERE Course_ID = ?");
                $chkP->bind_param("s", $pid);
                $chkP->execute();
                $pRow = $chkP->get_result()->fetch_assoc();
                $chkP->close();

                if (!$pRow) {
                    throw new Exception("Prerequisite course '{$pid}' was not found in the catalog.");
                }

                if ($course_type === 'Undergraduate' && $pRow['Course_Type'] === 'Graduate') {
                    throw new Exception("An Undergraduate course cannot require a Graduate prerequisite course ({$pid}).");
                }

                $cleanPrereqs[] = $pid;
            }
        }
        $cleanPrereqs = array_unique($cleanPrereqs);

        // 3. Insert Course
        $ins = $conn->prepare("
            INSERT INTO COURSES (Course_ID, Course_Type, Course_Name, Course_Description, Course_Credits, Department_ID)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $ins->bind_param("ssssii", $course_id, $course_type, $course_name, $course_description, $course_credits, $department_id);
        $ins->execute();
        $ins->close();

        // 4. Insert Prerequisites
        if (!empty($cleanPrereqs)) {
            $pIns = $conn->prepare("INSERT INTO COURSE_PREREQUISITE (Prerequisite_Course_ID, Course_ID) VALUES (?, ?)");
            foreach ($cleanPrereqs as $pid) {
                $pIns->bind_param("ss", $pid, $course_id);
                $pIns->execute();
            }
            $pIns->close();
        }

        $conn->commit();
        $_SESSION['success_message'] = "Course {$course_id} ({$course_name}) was successfully created with " . count($cleanPrereqs) . " prerequisite(s).";

    } elseif ($action === 'update_course') {
        $course_id          = strtoupper(trim($_POST['course_id'] ?? ''));
        $course_name        = trim($_POST['course_name'] ?? '');
        $course_type        = trim($_POST['course_type'] ?? '');
        $course_credits     = filter_var($_POST['course_credits'] ?? null, FILTER_VALIDATE_INT);
        $department_id      = filter_var($_POST['department_id'] ?? null, FILTER_VALIDATE_INT);
        $course_description = trim($_POST['course_description'] ?? '');
        $prerequisite_ids   = $_POST['prerequisite_ids'] ?? [];

        if (empty($course_id)) {
            throw new Exception("Invalid Course ID provided for update.");
        }

        // Verify course exists
        $verStmt = $conn->prepare("SELECT Course_ID FROM COURSES WHERE Course_ID = ? FOR UPDATE");
        $verStmt->bind_param("s", $course_id);
        $verStmt->execute();
        $courseFound = $verStmt->get_result()->fetch_assoc();
        $verStmt->close();
        if (!$courseFound) {
            throw new Exception("Course '{$course_id}' was not found in the course catalog.");
        }

        // Validate inputs
        if (empty($course_name) || strlen($course_name) > 100) {
            throw new Exception("Course Name is required and must not exceed 100 characters.");
        }

        if (!in_array($course_type, ['Undergraduate', 'Graduate'], true)) {
            throw new Exception("Course Type must be either Undergraduate or Graduate.");
        }

        if (!$course_credits || $course_credits < 1 || $course_credits > 6) {
            throw new Exception("Course Credits must be between 1 and 6.");
        }

        if (!$department_id || $department_id <= 0) {
            throw new Exception("Please select a valid Department.");
        }

        $dStmt = $conn->prepare("SELECT Department_ID FROM DEPARTMENT WHERE Department_ID = ?");
        $dStmt->bind_param("i", $department_id);
        $dStmt->execute();
        $deptFound = $dStmt->get_result()->fetch_assoc();
        $dStmt->close();
        if (!$deptFound) {
            throw new Exception("The selected Department does not exist.");
        }

        // Prerequisite validations
        $cleanPrereqs = [];
        if (is_array($prerequisite_ids)) {
            foreach ($prerequisite_ids as $pidRaw) {
                $pid = strtoupper(trim($pidRaw));
                if (empty($pid)) continue;

                // Check self-prerequisite
                if ($pid === $course_id) {
                    throw new Exception("A course cannot have itself as a prerequisite.");
                }

                // Check prerequisite existence and course type
                $chkP = $conn->prepare("SELECT Course_ID, Course_Type FROM COURSES WHERE Course_ID = ?");
                $chkP->bind_param("s", $pid);
                $chkP->execute();
                $pRow = $chkP->get_result()->fetch_assoc();
                $chkP->close();

                if (!$pRow) {
                    throw new Exception("Prerequisite course '{$pid}' was not found in the catalog.");
                }

                if ($course_type === 'Undergraduate' && $pRow['Course_Type'] === 'Graduate') {
                    throw new Exception("An Undergraduate course cannot require a Graduate prerequisite course ({$pid}).");
                }

                // Check direct circular prerequisite: A <-> B (does pid already require course_id?)
                $circStmt = $conn->prepare("SELECT 1 FROM COURSE_PREREQUISITE WHERE Course_ID = ? AND Prerequisite_Course_ID = ?");
                $circStmt->bind_param("ss", $pid, $course_id);
                $circStmt->execute();
                $isCircular = $circStmt->get_result()->fetch_assoc();
                $circStmt->close();

                if ($isCircular) {
                    throw new Exception("Direct circular prerequisite conflict detected: '{$pid}' already has '{$course_id}' listed as a prerequisite.");
                }

                $cleanPrereqs[] = $pid;
            }
        }
        $cleanPrereqs = array_unique($cleanPrereqs);

        // Update Course
        $upd = $conn->prepare("
            UPDATE COURSES
            SET Course_Type = ?, Course_Name = ?, Course_Description = ?, Course_Credits = ?, Department_ID = ?
            WHERE Course_ID = ?
        ");
        $upd->bind_param("sssiss", $course_type, $course_name, $course_description, $course_credits, $department_id, $course_id);
        $upd->execute();
        $upd->close();

        // Delete existing prerequisites for this course and re-insert
        $delP = $conn->prepare("DELETE FROM COURSE_PREREQUISITE WHERE Course_ID = ?");
        $delP->bind_param("s", $course_id);
        $delP->execute();
        $delP->close();

        if (!empty($cleanPrereqs)) {
            $insP = $conn->prepare("INSERT INTO COURSE_PREREQUISITE (Prerequisite_Course_ID, Course_ID) VALUES (?, ?)");
            foreach ($cleanPrereqs as $pid) {
                $insP->bind_param("ss", $pid, $course_id);
                $insP->execute();
            }
            $insP->close();
        }

        $conn->commit();
        $_SESSION['success_message'] = "Course {$course_id} was successfully updated with " . count($cleanPrereqs) . " prerequisite(s).";

    } elseif ($action === 'delete_course') {
        $course_id = strtoupper(trim($_POST['course_id'] ?? ''));
        if (empty($course_id)) {
            throw new Exception("Invalid Course ID specified for deletion.");
        }

        // Verify course exists
        $verStmt = $conn->prepare("SELECT Course_ID FROM COURSES WHERE Course_ID = ? FOR UPDATE");
        $verStmt->bind_param("s", $course_id);
        $verStmt->execute();
        $courseFound = $verStmt->get_result()->fetch_assoc();
        $verStmt->close();
        if (!$courseFound) {
            throw new Exception("Course '{$course_id}' was not found in the course catalog.");
        }

        // 1. Check if referenced in COURSE_SECTIONS
        $chkSec = $conn->prepare("SELECT COUNT(*) AS cnt FROM COURSE_SECTIONS WHERE Course_ID = ?");
        $chkSec->bind_param("s", $course_id);
        $chkSec->execute();
        $secCount = (int)$chkSec->get_result()->fetch_assoc()['cnt'];
        $chkSec->close();
        if ($secCount > 0) {
            throw new Exception("Cannot delete course '{$course_id}': {$secCount} active or historical course section(s) exist for this course in the master schedule.");
        }

        // 2. Check if referenced in STUDENT_HISTORY
        $chkHist = $conn->prepare("SELECT COUNT(*) AS cnt FROM STUDENT_HISTORY WHERE Course_ID = ?");
        $chkHist->bind_param("s", $course_id);
        $chkHist->execute();
        $histCount = (int)$chkHist->get_result()->fetch_assoc()['cnt'];
        $chkHist->close();
        if ($histCount > 0) {
            throw new Exception("Cannot delete course '{$course_id}': {$histCount} student historical transcript grade record(s) reference this course.");
        }

        // 3. Check if referenced in MAJOR_REQUIREMENTS
        $chkMaj = $conn->prepare("SELECT COUNT(*) AS cnt FROM MAJOR_REQUIREMENTS WHERE Course_ID = ?");
        $chkMaj->bind_param("s", $course_id);
        $chkMaj->execute();
        $majCount = (int)$chkMaj->get_result()->fetch_assoc()['cnt'];
        $chkMaj->close();
        if ($majCount > 0) {
            throw new Exception("Cannot delete course '{$course_id}': It is currently configured as a graduation requirement for {$majCount} academic major(s).");
        }

        // 4. Check if referenced in MINOR_REQUIREMENTS
        $chkMin = $conn->prepare("SELECT COUNT(*) AS cnt FROM MINOR_REQUIREMENTS WHERE Course_ID = ?");
        $chkMin->bind_param("s", $course_id);
        $chkMin->execute();
        $minCount = (int)$chkMin->get_result()->fetch_assoc()['cnt'];
        $chkMin->close();
        if ($minCount > 0) {
            throw new Exception("Cannot delete course '{$course_id}': It is currently configured as a requirement for {$minCount} academic minor(s).");
        }

        // Remove from COURSE_PREREQUISITE where it is either the prerequisite or the course
        $delPrereqs = $conn->prepare("DELETE FROM COURSE_PREREQUISITE WHERE Course_ID = ? OR Prerequisite_Course_ID = ?");
        $delPrereqs->bind_param("ss", $course_id, $course_id);
        $delPrereqs->execute();
        $delPrereqs->close();

        // Delete from COURSES
        $delC = $conn->prepare("DELETE FROM COURSES WHERE Course_ID = ?");
        $delC->bind_param("s", $course_id);
        $delC->execute();
        $delC->close();

        $conn->commit();
        $_SESSION['success_message'] = "Course '{$course_id}' was successfully deleted from the catalog.";

    } else {
        throw new Exception("Unrecognized action requested.");
    }

} catch (Exception $e) {
    if (isset($conn) && $conn->in_transaction) {
        $conn->rollback();
    }
    $_SESSION['error_message'] = $e->getMessage();
}

header("Location: manage_courses.php");
exit;

