<?php
session_start();
if (!isset($_SESSION["User_ID"])) {
    header("Location: login.php");
    exit;
}
$userType = $_SESSION["User_Type"] ?? '';
match ($userType) {
    'Student'   => header("Location: student/dashboard.php"),
    'Faculty'   => header("Location: faculty/dashboard.php"),
    'Admin'     => header("Location: admin/dashboard.php"),
    'Stat-Dept' => header("Location: stat_dept/dashboard.php"),
    default     => header("Location: login.php"),
};
exit;
