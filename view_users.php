<?php

require_once 'admin_authentication.php';
require_once 'db_connect.php';

$sql = "
    SELECT
        User_ID,
        User_Type,
        User_FName,
        User_MName,
        User_LName,
        User_Gender,
        User_DOB,
        User_PhoneNo,
        Street_No,
        Street_Name,
        City,
        State,
        Country,
        Zip_Code
    FROM `USER`
    ORDER BY User_ID
";

$result = $conn->query($sql);

if (!$result) {
    die("Failed to retrieve users.");
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Lucid University - View Users</title>
</head>

<body>

<h1>Lucid University Portal</h1>
<h2>All Users</h2>

<table border="1">
    <tr>
        <th>User ID</th>
        <th>User Type</th>
        <th>First Name</th>
        <th>Middle Name</th>
        <th>Last Name</th>
        <th>Gender</th>
        <th>Date of Birth</th>
        <th>Phone</th>
        <th>Street No.</th>
        <th>Street Name</th>
        <th>City</th>
        <th>State</th>
        <th>Country</th>
        <th>ZIP Code</th>
    </tr>

    <?php while ($user = $result->fetch_assoc()): ?>

        <tr>
            <td><?= htmlspecialchars($user["User_ID"]) ?></td>
            <td>
                <?=
                    $user["User_Type"] === "Admin"
                        ? "Administrator"
                        : htmlspecialchars($user["User_Type"])
                ?>
            </td>
            <td><?= htmlspecialchars($user["User_FName"]) ?></td>
            <td><?= htmlspecialchars($user["User_MName"] ?? "") ?></td>
            <td><?= htmlspecialchars($user["User_LName"]) ?></td>
            <td><?= htmlspecialchars($user["User_Gender"] ?? "") ?></td>
            <td><?= htmlspecialchars($user["User_DOB"] ?? "") ?></td>
            <td><?= htmlspecialchars($user["User_PhoneNo"] ?? "") ?></td>
            <td><?= htmlspecialchars($user["Street_No"] ?? "") ?></td>
            <td><?= htmlspecialchars($user["Street_Name"] ?? "") ?></td>
            <td><?= htmlspecialchars($user["City"] ?? "") ?></td>
            <td><?= htmlspecialchars($user["State"] ?? "") ?></td>
            <td><?= htmlspecialchars($user["Country"] ?? "") ?></td>
            <td><?= htmlspecialchars($user["Zip_Code"] ?? "") ?></td>
        </tr>

    <?php endwhile; ?>

</table>

<br>

<a href="admin_dashboard.php">Back to Dashboard</a>

</body>
</html>