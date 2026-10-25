<?php
require_once 'faculty_guard.php';

require_once '../db_connect.php';
$fullName = $_SESSION["User"]["User_FName"];

if (!empty($_SESSION["User"]["User_MName"])) {
    $fullName .= " " . $_SESSION["User"]["User_MName"];
}

$fullName .= " " . $_SESSION["User"]["User_LName"];
$fullName = strip_tags($fullName);

?>



<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Lucid University - Faculty Dashboard</title>
</head>
<body>
    <div class="dashboard-container">
        <h1>Lucid University Portal</h1>
        <h2>Faculty Dashboard</h2>
        <hr>

        <p>Welcome, <strong><?= htmlspecialchars($fullName) ?></strong>!</p>
        <hr>


        <!-- This is for later
        <div class="nav-links">
            <h3>Student Portal Actions:</h3>
            <ul>
                <li><a href="register_classes.php">Register for Classes</a></li>
                <li><a href="view_classes.php">View My Classes</a></li>
                <li><a href="view_schedule.php">View Schedule</a></li>
                <li><a href="profile.php">My Profile</a></li>
            </ul>
        </div>
        -->

        
        <form action="../logout.php" method="POST" class="logout-form">
            <button type="submit" class="logout-btn">Log Out</button>
        </form>
    </div>
</body>
</html>
