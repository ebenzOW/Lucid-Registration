<?php
require_once 'department_helpers.php';

$department = null;
$majors = [];
$minors = [];
$departmentCourses = [];
$majorRequiredCourses = [];
$minorRequiredCourses = [];
$errorMessage = null;

// Validate the department ID received from view_departments.php.
$departmentId = filter_input(
    INPUT_GET,
    'department_id',
    FILTER_VALIDATE_INT,
    ['options' => ['min_range' => 1]]
);

if ($departmentId === null || $departmentId === false) {
    http_response_code(400);
    $errorMessage = 'Please select a valid department from the department directory.';
} else {
    try {
        require_once __DIR__ . '/db_connect.php';
        $department = getDepartmentById($conn, $departmentId);

        if ($department === null) {
            http_response_code(404);
            $errorMessage = 'That department could not be found.';
        } else {
            $majors = getMajorsByDepartment($conn, $departmentId);
            $minors = getMinorsByDepartment($conn, $departmentId);
            $departmentCourses = getCoursesByDepartment($conn, $departmentId);
            $majorRequiredCourses = getMajorRequiredCoursesByDepartment($conn, $departmentId);
            $minorRequiredCourses = getMinorRequiredCoursesByDepartment($conn, $departmentId);
        }
    } catch (Throwable $e) {
        error_log('Department details error: ' . $e->getMessage());
        http_response_code(503);
        $errorMessage = 'Department information is temporarily unavailable. Please try again later.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $department ? escapeHtml($department['Department_Name']) : 'Department Details' ?> - Lucid University</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light d-flex flex-column min-vh-100">

    <nav class="navbar navbar-expand-lg navbar-dark bg-dark shadow-sm">
        <div class="container">
            <a class="navbar-brand fw-bold" href="index.html">Lucid University</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#publicNav"
                aria-controls="publicNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="publicNav">
                <ul class="navbar-nav ms-auto mb-2 mb-lg-0">
                    <li class="nav-item">
                        <a class="nav-link" href="index.html">Home</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" aria-current="page" href="view_departments.php">Departments</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link btn btn-outline-light btn-sm ms-lg-2 px-3 text-white" href="login.php">Login</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <main class="container py-5 flex-grow-1">
        <?php if ($errorMessage !== null): ?>
            <div class="d-flex justify-content-end mb-4">
                <a href="view_departments.php" class="btn btn-outline-secondary btn-sm">&larr; Back to Departments</a>
            </div>
            <div class="alert alert-warning" role="alert"><?= escapeHtml($errorMessage) ?></div>
        <?php else: ?>
            <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-4">
                <div>
                    <h1 class="display-6 fw-bold text-dark mb-3"><?= escapeHtml($department['Department_Name']) ?></h1>
                    <p class="text-secondary mb-1">
                        <strong>Email:</strong>
                        <a href="mailto:<?= escapeHtml($department['Department_Email']) ?>">
                            <?= escapeHtml($department['Department_Email']) ?>
                        </a>
                    </p>
                    <p class="text-secondary mb-0">
                        <strong>Phone:</strong>
                        <a href="tel:<?= escapeHtml(preg_replace('/[^0-9+]/', '', $department['Department_Phone'])) ?>">
                            <?= escapeHtml($department['Department_Phone']) ?>
                        </a>
                    </p>
                </div>
                <a href="view_departments.php" class="btn btn-outline-secondary btn-sm">&larr; Back to Departments</a>
            </div>

            <div class="row g-4">
                <section class="col-lg-6" aria-labelledby="majors-heading">
                    <div class="card shadow-sm border-0 h-100">
                        <div class="card-header bg-white p-4 border-bottom d-flex justify-content-between align-items-center">
                            <h2 class="h4 mb-0" id="majors-heading">Majors</h2>
                            <span class="badge text-bg-primary"><?= count($majors) ?></span>
                        </div>
                        <div class="card-body p-4">
                            <?php if (empty($majors)): ?>
                                <p class="text-secondary mb-0">No majors are currently listed for this department.</p>
                            <?php else: ?>
                                <ul class="list-group list-group-flush">
                                    <?php foreach ($majors as $major): ?>
                                        <li class="list-group-item px-0 py-3">
                                            <button type="button"
                                                class="btn btn-link text-start text-decoration-none fw-semibold p-0"
                                                data-bs-toggle="modal"
                                                data-bs-target="#majorCoursesModal-<?= (int) $major['Major_ID'] ?>">
                                                <?= escapeHtml($major['Major_Name']) ?>
                                            </button>
                                            <div class="text-secondary small">
                                                <?= (int) $major['No_Credits_Required'] ?> credits required
                                            </div>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                        </div>
                    </div>
                </section>

                <section class="col-lg-6" aria-labelledby="minors-heading">
                    <div class="card shadow-sm border-0 h-100">
                        <div class="card-header bg-white p-4 border-bottom d-flex justify-content-between align-items-center">
                            <h2 class="h4 mb-0" id="minors-heading">Minors</h2>
                            <span class="badge text-bg-primary"><?= count($minors) ?></span>
                        </div>
                        <div class="card-body p-4">
                            <?php if (empty($minors)): ?>
                                <p class="text-secondary mb-0">No minors are currently listed for this department.</p>
                            <?php else: ?>
                                <ul class="list-group list-group-flush">
                                    <?php foreach ($minors as $minor): ?>
                                        <li class="list-group-item px-0 py-3">
                                            <button type="button"
                                                class="btn btn-link text-start text-decoration-none fw-semibold p-0"
                                                data-bs-toggle="modal"
                                                data-bs-target="#minorCoursesModal-<?= (int) $minor['Minor_ID'] ?>">
                                                <?= escapeHtml($minor['Minor_Name']) ?>
                                            </button>
                                            <div class="text-secondary small">
                                                <?= (int) $minor['No_Credits_Required'] ?> credits required
                                            </div>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                        </div>
                    </div>
                </section>
            </div>
        <?php endif; ?>
    </main>

    <?php if ($errorMessage === null): ?>
        <!-- Put modals outside the cards to avoid z-index/overflow issues. -->
        <?php foreach ($majors as $major): ?>
            <?php
                $modalId = 'majorCoursesModal-' . (int) $major['Major_ID'];
                $programType = 'Major';
                $program = $major;
                $program['Program_Name'] = $major['Major_Name'];
                $requiredCourses = $majorRequiredCourses[(int) $major['Major_ID']] ?? [];
                include __DIR__ . '/program_courses.php';
            ?>
        <?php endforeach; ?>

        <?php foreach ($minors as $minor): ?>
            <?php
                $modalId = 'minorCoursesModal-' . (int) $minor['Minor_ID'];
                $programType = 'Minor';
                $program = $minor;
                $program['Program_Name'] = $minor['Minor_Name'];
                $requiredCourses = $minorRequiredCourses[(int) $minor['Minor_ID']] ?? [];
                include __DIR__ . '/program_courses.php';
            ?>
        <?php endforeach; ?>
    <?php endif; ?>

    <footer class="bg-white border-top py-3 mt-auto">
        <div class="container text-center text-muted small">
            &copy; <?= date('Y') ?> Lucid University. All rights reserved.
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
