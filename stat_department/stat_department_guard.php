<?php
/**
 * Lucid University Course Registration System
 * Statistical Department Portal - Authentication & Read-Only Access Guard
 *
 * Only authenticated Stat-Dept users with a STAT_DEPT record can open these pages.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['User_ID']) || ($_SESSION['User_Type'] ?? '') !== 'Stat-Dept') {
    header('Location: ../login.php');
    exit;
}

$userID = (int)$_SESSION['User_ID'];
require_once __DIR__ . '/../db_connect.php';
require_once __DIR__ . '/../includes/auth_helpers.php';

// Confirm this login still belongs to an authorized Statistical Department account.
$roleStmt = $conn->prepare("
    SELECT 1
    FROM STAT_DEPT sd
    JOIN `USER` u ON u.User_ID = sd.Stat_ID
    WHERE sd.Stat_ID = ? AND u.User_Type = 'Stat-Dept'
    LIMIT 1
");
$roleStmt->bind_param('i', $userID);
$roleStmt->execute();
$isStatDepartmentUser = ($roleStmt->get_result()->fetch_row() !== null);
$roleStmt->close();

if (!$isStatDepartmentUser) {
    http_response_code(403);
    exit('Access denied. Statistical Department access is required.');
}

ensureSessionUserLoaded($conn, $userID);
