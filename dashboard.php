<?php
session_start();
if (!isset($_SESSION["User_ID"])) {
    header("Location: login.php");
    exit;
}
$userType = $_SESSION["User_Type"] ?? '';
match ($userType) {
    'Student'   => header("Location: student/student_dashboard.php"),
    'Faculty'   => header("Location: faculty/faculty_dashboard.php"),
    'Admin'     => header("Location: admin/admin_dashboard.php"),
    'Stat-Dept' => header("Location: stat_dept/stat_department_dashboard.php"),
    default     => header("Location: login.php"),
};
exit;
