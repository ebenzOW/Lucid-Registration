<?php
// Why: Ensure PHP date/time functions use Eastern Time instead of XAMPP's default Europe/Berlin timezone.
date_default_timezone_set('America/New_York');

require_once 'db_login.php';

$conn = new mysqli($hostname, $username, $password);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$conn->query("CREATE DATABASE IF NOT EXISTS $db");
$conn->select_db($db);
$conn->set_charset("utf8mb4");

$tzOffset = date('P');
$conn->query("SET time_zone = '" . $conn->real_escape_string($tzOffset) . "'");
