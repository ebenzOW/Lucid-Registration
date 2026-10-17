<?php
require_once 'admin_authentication.php';
require_once 'db_connect.php';
require_once 'user_functions.php';

$fullName = $_SESSION["User"]["User_FName"];

if (!empty($_SESSION["User"]["User_MName"])) {
    $fullName .= " " . $_SESSION["User"]["User_MName"];
}

$fullName .= " " . $_SESSION["User"]["User_LName"];

$successMessage = $_SESSION["success_message"] ?? "";
$errorMessage = $_SESSION["error_message"] ?? "";

$newUserData = $_SESSION["new_user_data"] ?? null;

unset($_SESSION["success_message"], $_SESSION["error_message"], $_SESSION["new_user_data"]);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Lucid University - Create User</title>
</head>

<body>

<h1>Lucid University Portal</h1>
<h2>Create New User</h2>

<p>
    Administrator:
    <strong><?= htmlspecialchars($fullName) ?></strong>
</p>

<hr>

<nav>
    <li id=""><a href="admin_dashboard.php">Dashboard</a></li>
</nav>

<?php if ($successMessage): ?>
    <p><?= htmlspecialchars($successMessage) ?></p>
    <?php if ($newUserData): ?>
        <h3>Created User Details:</h3>
        <ul>
            <?php foreach ($newUserData as $key => $value): ?>
                <?php // Skip blank form inputs and passwords for display cleanliness ?>
                <?php if (!empty($value) && $key !== 'password'): ?>
                    <li>
                        <strong><?= htmlspecialchars(ucwords(str_replace('_', ' ', $key))) ?>:</strong>
                        <?= htmlspecialchars($value) ?>
                    </li>
                <?php endif; ?>
            <?php endforeach; ?>
        </ul>
        <hr>
    <?php endif; ?>
   
<?php endif; ?>

<?php if ($errorMessage): ?>
    <p><?= htmlspecialchars($errorMessage) ?></p>
<?php endif; ?>

<form action="create_user_process.php" method="POST">

    <h3>Account Information</h3>

    <label for="user_type">User Type:</label>
    <select name="user_type" id="user_type" required onchange="toggleFormSections()">
        <option value="">Select User Type</option>
        <option value="Admin">Administrator</option>
        <option value="Student">Student</option>
        <option value="Faculty">Faculty</option>
    </select>

    <br><br>

    <label for="email">Email:</label>
    <input type="email" name="email" id="email" required>

    <br><br>

    <label for="password">Initial Password:</label>
    <input type="password" name="password" id="password" required>

    <h3>Personal Information</h3>

    <label for="first_name">First Name:</label>
    <input type="text" name="first_name" id="first_name" required>

    <br><br>

    <label for="middle_name">Middle Name:</label>
    <input type="text" name="middle_name" id="middle_name">

    <br><br>

    <label for="last_name">Last Name:</label>
    <input type="text" name="last_name" id="last_name" required>

    <br><br>

    <label for="gender">Gender:</label>
    <input type="text" name="gender" id="gender">

    <br><br>

    <label for="dob">Date of Birth:</label>
    <input type="date" name="dob" id="dob">

    <br><br>

    <label for="phone">Phone Number:</label>
    <input type="text" name="phone" id="phone">

    <h3>Address</h3>

    <label for="street_no">Street Number:</label>
    <input type="text" name="street_no" id="street_no">

    <br><br>

    <label for="street_name">Street Name:</label>
    <input type="text" name="street_name" id="street_name">

    <br><br>

    <label for="city">City:</label>
    <input type="text" name="city" id="city">

    <br><br>

    <label for="state">State:</label>
    <input type="text" name="state" id="state">

    <br><br>

    <label for="country">Country:</label>
    <input type="text" name="country" id="country">

    <br><br>

    <label for="zip_code">ZIP Code:</label>
    <input type="text" name="zip_code" id="zip_code">

    <div id="section_Admin" class="subtype-section" style="display: none;">
        <h3>Administrator Information</h3>
        <label for="security_level">Security Level:</label>
        <input type="number" name="security_level" id="security_level" min="1">
    </div>

    <div id="section_Student" class="subtype-section" style="display: none;">
        <h3>Student Information</h3>

        <label for="student_year">Student Year:</label>
        <input type="number" name="student_year" id="student_year" min="1">

        <br><br>

        <label for="department_id">Department ID:</label>
        <input type="number" name="department_id" id="department_id">

        <br><br>

        <label for="student_level">Student Level:</label>
        <select name="student_level" id="student_level" onchange="toggleStudentSublevels()">
            <option value="">Select</option>
            <option value="Undergraduate">Undergraduate</option>
            <option value="Graduate">Graduate</option>
        </select>

        <br><br>

        <div id="undergrad_fields" style="display: none;">
            <br>

            <label for="undergrad_type">Undergraduate Type:</label>
            <select name="undergrad_type" id="undergrad_type">
                <option value="">Select</option>
                <option value="Full_Time">Full Time</option>
                <option value="Part_Time">Part Time</option>
            </select>
        </div>
       
        <!-- Inner Graduate Conditional Section -->
        <div id="grad_fields" style="display: none;">
            <br>

            <label for="grad_type">Graduate Type:</label>
            <select name="grad_type" id="grad_type">
                <option value="">Select</option>
                <option value="Full_Time">Full Time</option>
                <option value="Part_Time">Part Time</option>
            </select>

            <br><br>

            <label for="grad_program">Graduate Program:</label>
            <input type="text" name="grad_program" id="grad_program">

            <br><br>

            <label for="thesis">Thesis:</label>
            <input type="text" name="thesis" id="thesis">
        </div>
    </div>

    <div id="section_Faculty" class="subtype-section" style="display: none;">
        <h3>Faculty Information</h3>

        <label for="specialty">Specialty:</label>
        <input type="text" name="specialty" id="specialty">

        <br><br>

        <label for="rank">Rank:</label>
        <input type="text" name="rank" id="rank">

        <br><br>

        <label for="office_id">Office ID:</label>
        <input type="text" name="office_id" id="office_id">

        <br><br>

        <label for="faculty_status">Faculty Status:</label>
        <select name="faculty_status" id="faculty_status">
            <option value="">Select</option>
            <option value="Full_Time">Full Time</option>
            <option value="Part_Time">Part Time</option>
        </select>

        <br><br>

        <label for="no_classes">Number of Classes:</label>
        <input type="number" name="no_classes" id="no_classes" min="0">
    </div>

    <br><br>
    <button type="submit">Create User</button>

</form>

<br>

<form action="logout.php" method="POST">
    <button type="submit">Log Out</button>
</form>

<script src="create_user.js"></script>

</body>
</html>