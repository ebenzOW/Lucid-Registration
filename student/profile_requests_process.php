<?php
/**
 * Lucid University Course Registration System
 * Student Portal - Process Profile Change Requests
 */

require_once 'student_guard.php';
require_once '../db_connect.php';
require_once __DIR__ . '/../includes/profile_request_helpers.php';

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: profile_requests.php");
    exit;
}

handleProfileChangeRequestPost($conn, (int)$userID, $_POST, 'profile_requests.php');