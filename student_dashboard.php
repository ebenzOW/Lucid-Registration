<?php

//Everything below this will added to an authenticate user session file later
session_start();
require_once 'dbconnect.php';

// If the user is not logged in, boot them back to the login page
if (!isset($_SESSION["User_ID"])) {
    header("Location: login.php");
    exit;
}

$userID = $_SESSION["User_ID"];

// Get the student's information
$stmt = $conn->prepare("
    SELECT User_FName, User_MName, User_LName
    FROM Users
    WHERE User_ID = ?
");

if (!$stmt) {
    die("Database error: " . $conn->error);
}

$stmt->bind_param("i", $userID);
$stmt->execute();

$result = $stmt->get_result();

if ($result->num_rows !== 1) {
    // User ID in session doesn't exist in Users table
    session_destroy();
    header("Location: login.php");
    exit;
}

$user = $result->fetch_assoc();

$stmt->close();
$conn->close();

$fullName = $user["User_FName"];

if (!empty($user["User_MName"])) {
    $fullName .= " " . $user["User_MName"];
}

$fullName .= " " . $user["User_LName"];

//Everything above this will added to an authenticate user session file later
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Lucid University - Student Dashboard</title>
</head>
<body>
    <div class="dashboard-container">
        <h1>Lucid University Portal</h1>
        <h2>Student Dashboard</h2>
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

        
        <form action="logout.php" method="POST" class="logout-form">
            <button type="submit" class="logout-btn">Log Out</button>
        </form>
    </div>
</body>
</html>
