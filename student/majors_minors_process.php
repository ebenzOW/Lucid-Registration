<?php
/**
 * Lucid University Course Registration System
 * Student Portal - Process Major & Minor Declarations
 *
 * Implements SRS Constraints:
 * 1. Undergraduate: max 2 majors, max 1 minor. Undergraduate majors & minors only.
 * 2. Graduate: max 1 major matching Grad_Program (MS vs PHD). Minors forbidden.
 * 3. Primary major change updates STUDENT.Dept_ID.
 * 4. Concurrency protected with database transactions and row locks.
 */

require_once 'student_guard.php';
$student_id = (int)$userID;

require_once '../db_connect.php';
require_once __DIR__ . '/../includes/academic_helpers.php';

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: majors_minors.php");
    exit;
}

$action = trim($_POST['action'] ?? '');

try {
    $conn->begin_transaction();

    // Lock and fetch student classification
    $stuStmt = $conn->prepare("
        SELECT s.Student_ID, s.Student_Type, s.Dept_ID, gs.Grad_Program
        FROM STUDENT s
        LEFT JOIN GRADUATE_STUDENT gs ON s.Student_ID = gs.Student_ID
        WHERE s.Student_ID = ?
        FOR UPDATE
    ");
    $stuStmt->bind_param("i", $student_id);
    $stuStmt->execute();
    $student = $stuStmt->get_result()->fetch_assoc();
    $stuStmt->close();

    if (!$student) {
        throw new Exception("Student record not found.");
    }

    $studentType = $student['Student_Type'];
    $gradProgram = strtoupper(trim((string)($student['Grad_Program'] ?? '')));

    if ($action === 'declare_major') {
        $major_id = isset($_POST['major_id']) ? (int)$_POST['major_id'] : 0;
        if ($major_id <= 0) {
            throw new Exception("Please select a valid academic major.");
        }

        // Lock target Major
        $mStmt = $conn->prepare("SELECT Major_ID, Department_ID, Major_Name, No_Credits_Required FROM MAJORS WHERE Major_ID = ? FOR UPDATE");
        $mStmt->bind_param("i", $major_id);
        $mStmt->execute();
        $major = $mStmt->get_result()->fetch_assoc();
        $mStmt->close();

        if (!$major) {
            throw new Exception("The selected major program does not exist.");
        }

        // Lock existing majors
        $curStmt = $conn->prepare("SELECT Major_ID FROM STUDENT_MAJORS WHERE Student_ID = ? FOR UPDATE");
        $curStmt->bind_param("i", $student_id);
        $curStmt->execute();
        $curRes = $curStmt->get_result();
        $existingMajorIds = [];
        while ($row = $curRes->fetch_assoc()) {
            $existingMajorIds[] = (int)$row['Major_ID'];
        }
        $curStmt->close();

        if (in_array($major_id, $existingMajorIds, true)) {
            throw new Exception("You are already declared in " . $major['Major_Name'] . ".");
        }

        $mName = $major['Major_Name'];
        $mCredits = (int)$major['No_Credits_Required'];

        // Academic Level Validation
        if ($studentType === 'Undergraduate') {
            if (count($existingMajorIds) >= 2) {
                throw new Exception("Undergraduate students may declare a maximum of two majors simultaneously.");
            }
            if ($mCredits < 100) {
                throw new Exception("Undergraduate students cannot declare graduate degree programs.");
            }
        } elseif ($studentType === 'Graduate') {
            if (count($existingMajorIds) >= 1) {
                throw new Exception("Graduate students may declare exactly one degree program.");
            }
            if ($gradProgram === 'MS') {
                if (stripos($mName, 'Ph.D.') !== false || $mCredits >= 100) {
                    throw new Exception("Master's degree students (M.S.) can only declare graduate Master's programs (M.S./M.A./M.F.A.).");
                }
            } elseif ($gradProgram === 'PHD') {
                if (stripos($mName, 'Ph.D.') === false) {
                    throw new Exception("Doctoral students (Ph.D.) can only declare doctoral degree programs (Ph.D.).");
                }
            } else {
                throw new Exception("Graduate program classification ('{$gradProgram}') not recognized.");
            }
        }

        // Insert Major
        $insStmt = $conn->prepare("INSERT INTO STUDENT_MAJORS (Major_ID, Student_ID) VALUES (?, ?)");
        $insStmt->bind_param("ii", $major_id, $student_id);
        if (!$insStmt->execute()) {
            throw new Exception("Failed to declare major: " . $insStmt->error);
        }
        $insStmt->close();

        // Update home department if primary major
        if (empty($existingMajorIds)) {
            $updDept = $conn->prepare("UPDATE STUDENT SET Dept_ID = ? WHERE Student_ID = ?");
            $updDept->bind_param("ii", $major['Department_ID'], $student_id);
            $updDept->execute();
            $updDept->close();
        }

        $conn->commit();
        $_SESSION['success_message'] = "Successfully declared " . htmlspecialchars($major['Major_Name']) . "!";
        header("Location: majors_minors.php");
        exit;

    } elseif ($action === 'drop_major') {
        $major_id = isset($_POST['major_id']) ? (int)$_POST['major_id'] : 0;
        if ($major_id <= 0) {
            throw new Exception("Invalid major specified.");
        }

        // Delete Major
        $delStmt = $conn->prepare("DELETE FROM STUDENT_MAJORS WHERE Major_ID = ? AND Student_ID = ?");
        $delStmt->bind_param("ii", $major_id, $student_id);
        $delStmt->execute();
        $delStmt->close();

        // Check remaining majors
        $remStmt = $conn->prepare("
            SELECT sm.Major_ID, m.Department_ID
            FROM STUDENT_MAJORS sm
            JOIN MAJORS m ON sm.Major_ID = m.Major_ID
            WHERE sm.Student_ID = ?
            ORDER BY sm.Major_ID ASC
            LIMIT 1
        ");
        $remStmt->bind_param("i", $student_id);
        $remStmt->execute();
        $remRow = $remStmt->get_result()->fetch_assoc();
        $remStmt->close();

        $newDept = $remRow ? (int)$remRow['Department_ID'] : null;
        $updDept = $conn->prepare("UPDATE STUDENT SET Dept_ID = ? WHERE Student_ID = ?");
        $updDept->bind_param("ii", $newDept, $student_id);
        $updDept->execute();
        $updDept->close();

        $conn->commit();
        $_SESSION['success_message'] = "Major successfully dropped.";
        header("Location: majors_minors.php");
        exit;

    } elseif ($action === 'declare_minor') {
        $minor_id = isset($_POST['minor_id']) ? (int)$_POST['minor_id'] : 0;
        if ($minor_id <= 0) {
            throw new Exception("Please select a valid academic minor.");
        }

        if ($studentType === 'Graduate') {
            throw new Exception("Graduate students are not permitted to declare undergraduate minors.");
        }

        // Lock existing minors
        $curStmt = $conn->prepare("SELECT Minor_ID FROM STUDENT_MINORS WHERE Student_ID = ? FOR UPDATE");
        $curStmt->bind_param("i", $student_id);
        $curStmt->execute();
        if ($curStmt->get_result()->num_rows >= 1) {
            $curStmt->close();
            throw new Exception("Students may declare a maximum of one academic minor. Please drop your current minor first.");
        }
        $curStmt->close();

        // Lock target minor
        $minStmt = $conn->prepare("SELECT Minor_ID, Minor_Name FROM MINORS WHERE Minor_ID = ? FOR UPDATE");
        $minStmt->bind_param("i", $minor_id);
        $minStmt->execute();
        $minor = $minStmt->get_result()->fetch_assoc();
        $minStmt->close();

        if (!$minor) {
            throw new Exception("The selected minor does not exist.");
        }

        $insStmt = $conn->prepare("INSERT INTO STUDENT_MINORS (Minor_ID, Student_ID) VALUES (?, ?)");
        $insStmt->bind_param("ii", $minor_id, $student_id);
        if (!$insStmt->execute()) {
            throw new Exception("Failed to declare minor: " . $insStmt->error);
        }
        $insStmt->close();

        $conn->commit();
        $_SESSION['success_message'] = "Successfully declared " . htmlspecialchars($minor['Minor_Name']) . "!";
        header("Location: majors_minors.php");
        exit;

    } elseif ($action === 'drop_minor') {
        $minor_id = isset($_POST['minor_id']) ? (int)$_POST['minor_id'] : 0;
        if ($minor_id <= 0) {
            throw new Exception("Invalid minor specified.");
        }

        $delStmt = $conn->prepare("DELETE FROM STUDENT_MINORS WHERE Minor_ID = ? AND Student_ID = ?");
        $delStmt->bind_param("ii", $minor_id, $student_id);
        $delStmt->execute();
        $delStmt->close();

        $conn->commit();
        $_SESSION['success_message'] = "Minor successfully dropped.";
        header("Location: majors_minors.php");
        exit;

    } else {
        throw new Exception("Unrecognized action.");
    }

} catch (Exception $e) {
    if (isset($conn) && $conn->in_transaction) {
        $conn->rollback();
    }
    $_SESSION['error_message'] = $e->getMessage();
    header("Location: majors_minors.php");
    exit;
}

