<?php
require_once 'admin_authentication.php';
require_once 'db_connect.php';

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
    <title>Lucid University - Admin Create New Users</title>
</head>
<body>
    <div class="dashboard-container">
        <h1>Lucid University Portal</h1>
        <h2>Admin Create New Users</h2>
        <hr>

        <p>Administrator <strong><?= htmlspecialchars($fullName) ?></strong></p>
        <hr>
        

        
        <form action="logout.php" method="POST" class="logout-form">
            <button type="submit" class="logout-btn">Log Out</button>
        </form>
    </div>
</body>
</html>
