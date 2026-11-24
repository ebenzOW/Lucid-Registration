<?php
/**
 * Lucid University Course Registration System
 * Faculty Portal - Profile Information & Change Requests
 */

require_once 'faculty_guard.php';
$portalUserId = (int)$userID;

require_once '../db_connect.php';
require_once __DIR__ . '/../includes/ui_helpers.php';
require_once __DIR__ . '/../includes/profile_request_helpers.php';

$successMessage = $_SESSION['success_message'] ?? '';
$errorMessage   = $_SESSION['error_message'] ?? '';
unset($_SESSION['success_message'], $_SESSION['error_message']);

$user = getProfileRequestUser($conn, $portalUserId);
if (!$user) {
    die("Faculty record not found.");
}

$requests     = getUserProfileChangeRequests($conn, $portalUserId);
$idFieldLabel = 'Faculty ID';
$navbarHtml   = renderFacultyNavbar($conn, $portalUserId, 'profile_requests.php');

require __DIR__ . '/../includes/profile_request_view.php';