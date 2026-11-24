<?php
/**
 * Lucid University - Authentication & Access Control Helpers
 */

/**
 * Hydrate session user name sub-keys if missing.
 */
function ensureSessionUserLoaded(mysqli $conn, int $userID): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (!isset($_SESSION["User"]["User_FName"]) && !empty($userID)) {
        $uid = (int)$userID;
        $stmt = $conn->prepare("SELECT User_FName, User_MName, User_LName FROM `USER` WHERE User_ID = ?");
        $stmt->bind_param("i", $uid);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if ($row) {
            $_SESSION["User"] = $row;
        }
    }
}

/**
 * FERPA access verification:
 * - Students can only access their own records.
 * - Administrators and Stat-Dept staff can access all student records.
 * - Faculty can access advisees (ADVISOR_STUDENT) or students enrolled in their sections (COURSE_SECTIONS -> ENROLLMENT).
 */
function canViewerAccessStudentRecord(mysqli $conn, int $viewer_id, string $viewer_type, int $target_student_id): bool {
    $viewer_id = (int)$viewer_id;
    $target_student_id = (int)$target_student_id;

    if ($viewer_type === 'Student') {
        return $viewer_id === $target_student_id;
    }

    if ($viewer_type === 'Admin' || $viewer_type === 'Stat-Dept') {
        return true;
    }

    if ($viewer_type === 'Faculty') {
        // 1. Advisee check
        $advStmt = $conn->prepare("
            SELECT 1 FROM ADVISOR_STUDENT WHERE Faculty_ID = ? AND Student_ID = ? LIMIT 1
        ");
        $advStmt->bind_param("ii", $viewer_id, $target_student_id);
        $advStmt->execute();
        $isAdvisee = $advStmt->get_result()->fetch_row();
        $advStmt->close();
        if ($isAdvisee) {
            return true;
        }

        // 2. Enrolled student check
        $secStmt = $conn->prepare("
            SELECT 1 
            FROM ENROLLMENT e
            JOIN COURSE_SECTIONS cs ON e.CRN = cs.CRN
            WHERE cs.Faculty_ID = ? AND e.Student_ID = ?
            LIMIT 1
        ");
        $secStmt->bind_param("ii", $viewer_id, $target_student_id);
        $secStmt->execute();
        $isEnrolled = $secStmt->get_result()->fetch_row();
        $secStmt->close();
        if ($isEnrolled) {
            return true;
        }

        return false;
    }

    return false;
}

/**
 * Enforce Admin Write Access (Security_Level >= 2).
 * Throws Exception if unauthorized.
 */
function requireAdminWriteAccess(): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (($_SESSION['Security_Level'] ?? 1) < 2) {
        throw new Exception("Unauthorized: Administrator write access level 2+ required.");
    }
}
