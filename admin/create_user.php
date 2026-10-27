<?php
require_once 'admin_guard.php';
require_once '../db_connect.php';
require_once '../user_functions.php';
require_once '../profile_hover_card.php';

$admin_id = (int)$userID;

$fullName = $_SESSION["User"]["User_FName"] ?? "Administrator";
if (!empty($_SESSION["User"]["User_MName"])) {
    $fullName .= " " . $_SESSION["User"]["User_MName"];
}
if (!empty($_SESSION["User"]["User_LName"])) {
    $fullName .= " " . $_SESSION["User"]["User_LName"];
}
$fullName = strip_tags($fullName);

$successMessage = $_SESSION["success_message"] ?? "";
$errorMessage = $_SESSION["error_message"] ?? "";
$newUserData = $_SESSION["new_user_data"] ?? null;

unset($_SESSION["success_message"], $_SESSION["error_message"], $_SESSION["new_user_data"]);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create User | Lucid University Admin</title>
    <!-- Minimal Clean Bootstrap 5.3 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        <?= getProfileHoverCardStyles() ?>
    </style>
</head>
<body class="bg-light">

    <!-- Unified Admin Top Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark mb-4 shadow-sm">
        <div class="container">
            <a class="navbar-brand fw-bold" href="dashboard.php">Lucid University <span class="badge bg-danger ms-1">Admin</span></a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#adminNav" aria-controls="adminNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="adminNav">
                <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                    <li class="nav-item">
                        <a class="nav-link" href="dashboard.php">Dashboard</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" aria-current="page" href="create_user.php">Create User</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="view_users.php">View Users</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="create_course_section.php">Create Course Section</a>
                    </li>
                </ul>
                <div class="d-flex align-items-center gap-3">
                    <?= getUserNavbarProfileHtml($conn, $admin_id, 'Admin') ?>
                    <form action="../logout.php" method="POST" class="m-0">
                        <button type="submit" class="btn btn-outline-light btn-sm">Log Out</button>
                    </form>
                </div>
            </div>
        </div>
    </nav>

    <div class="container mb-5">

        <!-- Page Header -->
        <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
            <div>
                <h2 class="fw-bold mb-1">Create New University User</h2>
                <div class="text-secondary">
                    Provision accounts for Students, Faculty, and Administrators.
                </div>
            </div>
            <div>
                <a href="dashboard.php" class="btn btn-outline-secondary btn-sm">&larr; Back to Dashboard</a>
            </div>
        </div>

        <!-- Success Flash Alert -->
        <?php if ($successMessage): ?>
            <div class="alert alert-success alert-dismissible fade show shadow-sm" role="alert">
                <h5 class="alert-heading fw-bold mb-1">Account Created Successfully</h5>
                <p class="mb-2"><?= htmlspecialchars($successMessage) ?></p>
                <?php if ($newUserData): ?>
                    <hr>
                    <h6 class="fw-bold mb-2">Created User Summary:</h6>
                    <ul class="mb-0 small">
                        <?php foreach ($newUserData as $key => $value): ?>
                            <?php if (!empty($value) && $key !== 'password'): ?>
                                <li>
                                    <strong><?= htmlspecialchars(ucwords(str_replace('_', ' ', $key))) ?>:</strong>
                                    <?= htmlspecialchars($value) ?>
                                </li>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <!-- Error Flash Alert -->
        <?php if ($errorMessage): ?>
            <div class="alert alert-danger alert-dismissible fade show shadow-sm" role="alert">
                <strong>Error Creating User:</strong> <?= htmlspecialchars($errorMessage) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <!-- User Creation Form Card -->
        <div class="card shadow-sm border-0">
            <div class="card-body p-4 bg-white rounded-3">
                <form action="create_user_process.php" method="POST">

                    <!-- Section 1: Account Information -->
                    <h5 class="fw-bold text-dark border-bottom pb-2 mb-3">1. Account Credentials &amp; Role</h5>
                    <div class="row g-3 mb-4">
                        <div class="col-md-4">
                            <label for="user_type" class="form-label small fw-bold">User Role / Type <span class="text-danger">*</span></label>
                            <select name="user_type" id="user_type" class="form-select" required onchange="toggleFormSections()">
                                <option value="">Select User Type</option>
                                <option value="Admin">Administrator</option>
                                <option value="Student">Student</option>
                                <option value="Faculty">Faculty</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label for="email" class="form-label small fw-bold">University Email <span class="text-danger">*</span></label>
                            <input type="email" name="email" id="email" class="form-control" placeholder="user@lucid.edu" required>
                        </div>
                        <div class="col-md-4">
                            <label for="password" class="form-label small fw-bold">Initial Password <span class="text-danger">*</span></label>
                            <input type="password" name="password" id="password" class="form-control" placeholder="Min 5 chars, 1 upper, 1 symbol" required>
                        </div>
                    </div>

                    <!-- Section 2: Personal Information -->
                    <h5 class="fw-bold text-dark border-bottom pb-2 mb-3">2. Personal Information</h5>
                    <div class="row g-3 mb-4">
                        <div class="col-md-4">
                            <label for="first_name" class="form-label small fw-bold">First Name <span class="text-danger">*</span></label>
                            <input type="text" name="first_name" id="first_name" class="form-control" required>
                        </div>
                        <div class="col-md-4">
                            <label for="middle_name" class="form-label small fw-bold">Middle Name</label>
                            <input type="text" name="middle_name" id="middle_name" class="form-control">
                        </div>
                        <div class="col-md-4">
                            <label for="last_name" class="form-label small fw-bold">Last Name <span class="text-danger">*</span></label>
                            <input type="text" name="last_name" id="last_name" class="form-control" required>
                        </div>
                        <div class="col-md-4">
                            <label for="gender" class="form-label small fw-bold">Gender</label>
                            <input type="text" name="gender" id="gender" class="form-control" placeholder="e.g. Male, Female, Other">
                        </div>
                        <div class="col-md-4">
                            <label for="dob" class="form-label small fw-bold">Date of Birth</label>
                            <input type="date" name="dob" id="dob" class="form-control">
                        </div>
                        <div class="col-md-4">
                            <label for="phone" class="form-label small fw-bold">Phone Number</label>
                            <input type="text" name="phone" id="phone" class="form-control" placeholder="e.g. 555-0199">
                        </div>
                    </div>

                    <!-- Section 3: Address -->
                    <h5 class="fw-bold text-dark border-bottom pb-2 mb-3">3. Residential Address</h5>
                    <div class="row g-3 mb-4">
                        <div class="col-md-2">
                            <label for="street_no" class="form-label small fw-bold">Street No.</label>
                            <input type="text" name="street_no" id="street_no" class="form-control" placeholder="e.g. 123">
                        </div>
                        <div class="col-md-4">
                            <label for="street_name" class="form-label small fw-bold">Street Name</label>
                            <input type="text" name="street_name" id="street_name" class="form-control" placeholder="e.g. University Ave">
                        </div>
                        <div class="col-md-3">
                            <label for="city" class="form-label small fw-bold">City</label>
                            <input type="text" name="city" id="city" class="form-control" placeholder="e.g. Old Westbury">
                        </div>
                        <div class="col-md-3">
                            <label for="state" class="form-label small fw-bold">State</label>
                            <input type="text" name="state" id="state" class="form-control" placeholder="e.g. NY">
                        </div>
                        <div class="col-md-3">
                            <label for="country" class="form-label small fw-bold">Country</label>
                            <input type="text" name="country" id="country" class="form-control" value="United States">
                        </div>
                        <div class="col-md-3">
                            <label for="zip_code" class="form-label small fw-bold">ZIP Code</label>
                            <input type="text" name="zip_code" id="zip_code" class="form-control" placeholder="e.g. 11568">
                        </div>
                    </div>

                    <!-- Section 4: Role-Specific Information (Dynamically toggled by create_user.js) -->
                    <div id="section_Admin" class="subtype-section mb-4 p-3 border rounded bg-light" style="display: none;">
                        <h5 class="fw-bold text-dark border-bottom pb-2 mb-3">Administrator Details</h5>
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label for="security_level" class="form-label small fw-bold">Security Level <span class="text-danger">*</span></label>
                                <input type="number" name="security_level" id="security_level" class="form-control" min="1" max="10" placeholder="1-10">
                            </div>
                        </div>
                    </div>

                    <div id="section_Student" class="subtype-section mb-4 p-3 border rounded bg-light" style="display: none;">
                        <h5 class="fw-bold text-dark border-bottom pb-2 mb-3">Student Details</h5>
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label for="student_year" class="form-label small fw-bold">Student Year (1-5) <span class="text-danger">*</span></label>
                                <input type="number" name="student_year" id="student_year" class="form-control" min="1" max="5" placeholder="1=Freshman, 4=Senior, 5=Grad">
                            </div>
                            <div class="col-md-4">
                                <label for="department_id" class="form-label small fw-bold">Department ID</label>
                                <input type="number" name="department_id" id="department_id" class="form-control" placeholder="e.g. 1 for CS">
                            </div>
                            <div class="col-md-4">
                                <label for="student_level" class="form-label small fw-bold">Student Level <span class="text-danger">*</span></label>
                                <select name="student_level" id="student_level" class="form-select" onchange="toggleStudentSublevels()">
                                    <option value="">Select Level</option>
                                    <option value="Undergraduate">Undergraduate</option>
                                    <option value="Graduate">Graduate</option>
                                </select>
                            </div>

                            <!-- Undergrad Subfields -->
                            <div id="undergrad_fields" class="col-12" style="display: none;">
                                <div class="row g-3 border-top pt-2 mt-1">
                                    <div class="col-md-4">
                                        <label for="undergrad_type" class="form-label small fw-bold">Undergraduate Type <span class="text-danger">*</span></label>
                                        <select name="undergrad_type" id="undergrad_type" class="form-select">
                                            <option value="">Select Attendance</option>
                                            <option value="Full_Time">Full-Time</option>
                                            <option value="Part_Time">Part-Time</option>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <!-- Graduate Subfields -->
                            <div id="grad_fields" class="col-12" style="display: none;">
                                <div class="row g-3 border-top pt-2 mt-1">
                                    <div class="col-md-4">
                                        <label for="grad_type" class="form-label small fw-bold">Graduate Type <span class="text-danger">*</span></label>
                                        <select name="grad_type" id="grad_type" class="form-select">
                                            <option value="">Select Attendance</option>
                                            <option value="Full_Time">Full-Time</option>
                                            <option value="Part_Time">Part-Time</option>
                                        </select>
                                    </div>
                                    <div class="col-md-4">
                                        <label for="grad_program" class="form-label small fw-bold">Graduate Program <span class="text-danger">*</span></label>
                                        <input type="text" name="grad_program" id="grad_program" class="form-control" placeholder="e.g. M.S. Computer Science">
                                    </div>
                                    <div class="col-md-4">
                                        <label for="thesis" class="form-label small fw-bold">Thesis Title / Topic</label>
                                        <input type="text" name="thesis" id="thesis" class="form-control" placeholder="Optional thesis topic">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div id="section_Faculty" class="subtype-section mb-4 p-3 border rounded bg-light" style="display: none;">
                        <h5 class="fw-bold text-dark border-bottom pb-2 mb-3">Faculty Details</h5>
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label for="specialty" class="form-label small fw-bold">Academic Specialty</label>
                                <input type="text" name="specialty" id="specialty" class="form-control" placeholder="e.g. Artificial Intelligence">
                            </div>
                            <div class="col-md-4">
                                <label for="rank" class="form-label small fw-bold">Academic Rank</label>
                                <input type="text" name="rank" id="rank" class="form-control" placeholder="e.g. Full Professor, Associate">
                            </div>
                            <div class="col-md-4">
                                <label for="office_id" class="form-label small fw-bold">Office Room ID</label>
                                <input type="text" name="office_id" id="office_id" class="form-control" placeholder="e.g. ACAD-201">
                            </div>
                            <div class="col-md-4">
                                <label for="faculty_status" class="form-label small fw-bold">Faculty Status <span class="text-danger">*</span></label>
                                <select name="faculty_status" id="faculty_status" class="form-select">
                                    <option value="">Select Status</option>
                                    <option value="Full_Time">Full-Time</option>
                                    <option value="Part_Time">Part-Time</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label for="no_classes" class="form-label small fw-bold">Number of Classes</label>
                                <input type="number" name="no_classes" id="no_classes" class="form-control" min="0" value="0">
                            </div>
                        </div>
                    </div>

                    <div class="d-flex gap-2 justify-content-end border-top pt-3">
                        <a href="dashboard.php" class="btn btn-outline-secondary">Cancel</a>
                        <button type="submit" class="btn btn-primary px-4">
                            Create User Account
                        </button>
                    </div>

                </form>
            </div>
        </div>

    </div>

    <!-- User Form Interactivity Script -->
    <script src="create_user.js"></script>
    <!-- Bootstrap 5.3 JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
