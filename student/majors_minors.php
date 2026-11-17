<?php
/**
 * Lucid University Course Registration System
 * Student Portal - Academic Majors & Minors Hub
 *
 * Implements SRS & Sitemap Use Cases:
 * 1. Browse All Academic Majors, Minors & Course Requirements
 * 2. Declare, Change, or Drop Major(s) (Undergrad max 2, Grad max 1)
 * 3. Declare, Change, or Drop Minor (Undergrad max 1, Grad forbidden)
 */

require_once 'student_guard.php';
$student_id = (int)$userID;

require_once '../db_connect.php';
require_once __DIR__ . '/../includes/academic_helpers.php';
require_once '../profile_hover_card.php';

$successMessage = $_SESSION["success_message"] ?? "";
$errorMessage   = $_SESSION["error_message"] ?? "";
unset($_SESSION["success_message"], $_SESSION["error_message"]);

// Fetch Student Profile & Program Details
$stuStmt = $conn->prepare("
    SELECT s.Student_ID, s.Student_Type, s.Dept_ID, d.Department_Name,
           u.User_FName, u.User_LName,
           gs.Grad_Program
    FROM STUDENT s
    JOIN `USER` u ON s.Student_ID = u.User_ID
    LEFT JOIN DEPARTMENT d ON s.Dept_ID = d.Department_ID
    LEFT JOIN GRADUATE_STUDENT gs ON s.Student_ID = gs.Student_ID
    WHERE s.Student_ID = ?
");
$stuStmt->bind_param("i", $student_id);
$stuStmt->execute();
$student = $stuStmt->get_result()->fetch_assoc();
$stuStmt->close();

$studentType = $student['Student_Type'] ?? 'Undergraduate';
$gradProgram = strtoupper(trim((string)($student['Grad_Program'] ?? '')));

// Fetch Current Majors
$smStmt = $conn->prepare("
    SELECT sm.Major_ID, m.Major_Name, m.No_Credits_Required, m.Department_ID, d.Department_Name
    FROM STUDENT_MAJORS sm
    JOIN MAJORS m ON sm.Major_ID = m.Major_ID
    LEFT JOIN DEPARTMENT d ON m.Department_ID = d.Department_ID
    WHERE sm.Student_ID = ?
    ORDER BY sm.Major_ID ASC
");
$smStmt->bind_param("i", $student_id);
$smStmt->execute();
$currentMajors = $smStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$smStmt->close();
$currentMajorIds = array_column($currentMajors, 'Major_ID');

// Fetch Current Minor
$smnStmt = $conn->prepare("
    SELECT smn.Minor_ID, mn.Minor_Name, mn.No_Credits_Required, mn.Department_ID, d.Department_Name
    FROM STUDENT_MINORS smn
    JOIN MINORS mn ON smn.Minor_ID = mn.Minor_ID
    LEFT JOIN DEPARTMENT d ON mn.Department_ID = d.Department_ID
    WHERE smn.Student_ID = ?
");
$smnStmt->bind_param("i", $student_id);
$smnStmt->execute();
$currentMinor = $smnStmt->get_result()->fetch_assoc();
$smnStmt->close();

// Fetch All Majors
$majorsStmt = $conn->prepare("
    SELECT m.Major_ID, m.Major_Name, m.No_Credits_Required, m.Department_ID, d.Department_Name
    FROM MAJORS m
    JOIN DEPARTMENT d ON m.Department_ID = d.Department_ID
    ORDER BY d.Department_Name ASC, m.Major_Name ASC
");
$majorsStmt->execute();
$allMajors = $majorsStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$majorsStmt->close();

// Fetch Major Requirements
$mReqStmt = $conn->prepare("
    SELECT mr.Major_ID, mr.Course_ID, mr.Min_Grade, c.Course_Name, c.Course_Credits, d.Department_Name
    FROM MAJOR_REQUIREMENTS mr
    JOIN COURSES c ON mr.Course_ID = c.Course_ID
    LEFT JOIN DEPARTMENT d ON c.Department_ID = d.Department_ID
    ORDER BY mr.Major_ID ASC, mr.Course_ID ASC
");
$mReqStmt->execute();
$mReqRes = $mReqStmt->get_result();
$majorRequirements = [];
while ($row = $mReqRes->fetch_assoc()) {
    $majorRequirements[(int)$row['Major_ID']][] = $row;
}
$mReqStmt->close();

// Fetch All Minors
$minorsStmt = $conn->prepare("
    SELECT mn.Minor_ID, mn.Minor_Name, mn.No_Credits_Required, mn.Department_ID, d.Department_Name
    FROM MINORS mn
    JOIN DEPARTMENT d ON mn.Department_ID = d.Department_ID
    ORDER BY d.Department_Name ASC, mn.Minor_Name ASC
");
$minorsStmt->execute();
$allMinors = $minorsStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$minorsStmt->close();

// Fetch Minor Requirements
$mnReqStmt = $conn->prepare("
    SELECT mnr.Minor_ID, mnr.Course_ID, mnr.Min_Grade, c.Course_Name, c.Course_Credits, d.Department_Name
    FROM MINOR_REQUIREMENTS mnr
    JOIN COURSES c ON mnr.Course_ID = c.Course_ID
    LEFT JOIN DEPARTMENT d ON c.Department_ID = d.Department_ID
    ORDER BY mnr.Minor_ID ASC, mnr.Course_ID ASC
");
$mnReqStmt->execute();
$mnReqRes = $mnReqStmt->get_result();
$minorRequirements = [];
while ($row = $mnReqRes->fetch_assoc()) {
    $minorRequirements[(int)$row['Minor_ID']][] = $row;
}
$mnReqStmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Majors &amp; Minors | Lucid University</title>
    <!-- Minimal Clean Bootstrap 5.3 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        <?= getProfileHoverCardStyles() ?>
        .badge-undergrad { background-color: #0d6efd; color: #fff; }
        .badge-grad { background-color: #6f42c1; color: #fff; }
        .badge-doc { background-color: #212529; color: #fff; }
    </style>
</head>
<body class="bg-light">

    <?= renderStudentNavbar($conn, $student_id, basename($_SERVER['PHP_SELF'])) ?>

    <div class="container mb-5">

        <!-- Header -->
        <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
            <div>
                <h2 class="fw-bold mb-1">Academic Majors &amp; Minors</h2>
                <div class="text-secondary">
                    Review degree tracks, inspect curriculum requirements, and declare or update your academic programs.
                </div>
            </div>
            <div>
                <a href="degree_audit.php" class="btn btn-outline-primary btn-sm">&larr; View Degree Audit</a>
            </div>
        </div>

        <!-- Flash Alerts -->
        <?php if (!empty($successMessage)): ?>
            <div class="alert alert-success alert-dismissible fade show shadow-sm" role="alert">
                <strong>Success:</strong> <?= htmlspecialchars($successMessage, ENT_QUOTES, 'UTF-8') ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
        <?php if (!empty($errorMessage)): ?>
            <div class="alert alert-danger alert-dismissible fade show shadow-sm" role="alert">
                <strong>Error:</strong> <?= htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8') ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <!-- Active Declarations Card -->
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-header bg-white py-3 border-0">
                <h5 class="fw-bold mb-0">My Current Academic Declarations</h5>
            </div>
            <div class="card-body p-4 pt-0">
                <div class="row g-3">
                    <!-- Majors -->
                    <div class="col-md-7">
                        <div class="border rounded p-3 h-100 bg-light">
                            <div class="text-uppercase small fw-bold text-secondary mb-2">Declared Major(s)</div>
                            <?php if (empty($currentMajors)): ?>
                                <p class="text-danger small mb-0 fw-semibold">No major declared. Please select and declare a primary major below.</p>
                            <?php else: ?>
                                <ul class="list-group list-group-flush bg-transparent">
                                    <?php foreach ($currentMajors as $idx => $m): ?>
                                        <li class="list-group-item bg-transparent px-0 d-flex justify-content-between align-items-center">
                                            <div>
                                                <span class="badge bg-primary me-2"><?= $idx === 0 ? 'Primary' : 'Secondary' ?></span>
                                                <strong><?= htmlspecialchars($m['Major_Name'], ENT_QUOTES, 'UTF-8') ?></strong>
                                                <div class="small text-secondary">
                                                    Department: <?= htmlspecialchars($m['Department_Name'], ENT_QUOTES, 'UTF-8') ?> | <?= (int)$m['No_Credits_Required'] ?> Required Credits
                                                </div>
                                            </div>
                                            <form action="majors_minors_process.php" method="POST" class="m-0" onsubmit="return confirm('Are you sure you want to drop this major?');">
                                                <input type="hidden" name="action" value="drop_major">
                                                <input type="hidden" name="major_id" value="<?= (int)$m['Major_ID'] ?>">
                                                <button type="submit" class="btn btn-outline-danger btn-sm">Drop</button>
                                            </form>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Minor -->
                    <div class="col-md-5">
                        <div class="border rounded p-3 h-100 bg-light">
                            <div class="text-uppercase small fw-bold text-secondary mb-2">Declared Minor</div>
                            <?php if ($studentType === 'Graduate'): ?>
                                <p class="text-secondary small mb-0">Graduate students are not permitted to declare undergraduate minors.</p>
                            <?php elseif (!$currentMinor): ?>
                                <p class="text-muted small mb-0">No academic minor declared (optional).</p>
                            <?php else: ?>
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <strong><?= htmlspecialchars($currentMinor['Minor_Name'], ENT_QUOTES, 'UTF-8') ?></strong>
                                        <div class="small text-secondary">
                                            Department: <?= htmlspecialchars($currentMinor['Department_Name'], ENT_QUOTES, 'UTF-8') ?> | <?= (int)$currentMinor['No_Credits_Required'] ?> Credits
                                        </div>
                                    </div>
                                    <form action="majors_minors_process.php" method="POST" class="m-0" onsubmit="return confirm('Are you sure you want to drop your minor?');">
                                        <input type="hidden" name="action" value="drop_minor">
                                        <input type="hidden" name="minor_id" value="<?= (int)$currentMinor['Minor_ID'] ?>">
                                        <button type="submit" class="btn btn-outline-danger btn-sm">Drop</button>
                                    </form>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Directory Filter -->
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-body p-3">
                <input type="search" id="progFilter" class="form-control" placeholder="Search majors, minors, departments, or degree types...">
            </div>
        </div>

        <!-- Majors Listing Table -->
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-header bg-white py-3 border-0">
                <h5 class="fw-bold mb-0">Academic Majors (<?= count($allMajors) ?>)</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0" id="majorsTable">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 35%;">Major Name</th>
                                <th style="width: 20%;">Degree Level</th>
                                <th style="width: 20%;">Department</th>
                                <th style="width: 10%;" class="text-center">Credits</th>
                                <th style="width: 15%;" class="text-end pe-4">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($allMajors as $m): 
                                $mid = (int)$m['Major_ID'];
                                $mName = $m['Major_Name'];
                                $mCredits = (int)$m['No_Credits_Required'];
                                $isDeclared = in_array($mid, $currentMajorIds, true);

                                if (stripos($mName, 'Ph.D.') !== false) {
                                    $levelBadge = '<span class="badge badge-doc">Doctoral (Ph.D.)</span>';
                                    $levelType = 'phd';
                                } elseif ($mCredits < 100) {
                                    $levelBadge = '<span class="badge badge-grad">Graduate (Master)</span>';
                                    $levelType = 'ms';
                                } else {
                                    $levelBadge = '<span class="badge badge-undergrad">Undergraduate</span>';
                                    $levelType = 'ug';
                                }

                                // Eligibility
                                $canDeclare = false;
                                $disableReason = '';
                                if ($isDeclared) {
                                    $disableReason = 'Already Declared';
                                } elseif ($studentType === 'Undergraduate') {
                                    if (count($currentMajorIds) >= 2) {
                                        $disableReason = 'Max 2 Majors Reached';
                                    } elseif ($levelType !== 'ug') {
                                        $disableReason = 'Graduate Program';
                                    } else {
                                        $canDeclare = true;
                                    }
                                } elseif ($studentType === 'Graduate') {
                                    if (count($currentMajorIds) >= 1) {
                                        $disableReason = 'Max 1 Program Reached';
                                    } elseif ($gradProgram === 'MS' && $levelType !== 'ms') {
                                        $disableReason = 'Must match M.S. Program';
                                    } elseif ($gradProgram === 'PHD' && $levelType !== 'phd') {
                                        $disableReason = 'Must match Ph.D. Program';
                                    } else {
                                        $canDeclare = true;
                                    }
                                }
                            ?>
                                <tr class="prog-row" data-search="<?= htmlspecialchars(strtolower($mName . ' ' . $m['Department_Name']), ENT_QUOTES, 'UTF-8') ?>">
                                    <td>
                                        <span class="fw-bold text-dark"><?= htmlspecialchars($mName, ENT_QUOTES, 'UTF-8') ?></span>
                                    </td>
                                    <td><?= $levelBadge ?></td>
                                    <td>
                                        <span class="badge bg-light text-dark border"><?= htmlspecialchars($m['Department_Name'], ENT_QUOTES, 'UTF-8') ?></span>
                                    </td>
                                    <td class="text-center fw-semibold"><?= $mCredits ?> cr</td>
                                    <td class="text-end pe-4">
                                        <div class="d-inline-flex gap-1">
                                            <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#mReqModal<?= $mid ?>">
                                                Courses
                                            </button>
                                            <?php if ($canDeclare): ?>
                                                <form action="majors_minors_process.php" method="POST" class="m-0">
                                                    <input type="hidden" name="action" value="declare_major">
                                                    <input type="hidden" name="major_id" value="<?= $mid ?>">
                                                    <button type="submit" class="btn btn-primary btn-sm">Declare</button>
                                                </form>
                                            <?php else: ?>
                                                <button type="button" class="btn btn-light btn-sm text-secondary border" disabled title="<?= htmlspecialchars($disableReason, ENT_QUOTES, 'UTF-8') ?>">
                                                    <?= htmlspecialchars($disableReason, ENT_QUOTES, 'UTF-8') ?>
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Minors Listing Table -->
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white py-3 border-0">
                <h5 class="fw-bold mb-0">Academic Minors (<?= count($allMinors) ?>)</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0" id="minorsTable">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 40%;">Minor Name</th>
                                <th style="width: 30%;">Department</th>
                                <th style="width: 15%;" class="text-center">Credits</th>
                                <th style="width: 15%;" class="text-end pe-4">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($allMinors as $mn): 
                                $mnId = (int)$mn['Minor_ID'];
                                $mnName = $mn['Minor_Name'];
                                $isMinorDeclared = ($currentMinor && (int)$currentMinor['Minor_ID'] === $mnId);

                                $canDeclareMinor = false;
                                $minorReason = '';
                                if ($studentType === 'Graduate') {
                                    $minorReason = 'Undergraduate Only';
                                } elseif ($isMinorDeclared) {
                                    $minorReason = 'Already Declared';
                                } elseif ($currentMinor) {
                                    $minorReason = 'Max 1 Minor Reached';
                                } else {
                                    $canDeclareMinor = true;
                                }
                            ?>
                                <tr class="prog-row" data-search="<?= htmlspecialchars(strtolower($mnName . ' ' . $mn['Department_Name']), ENT_QUOTES, 'UTF-8') ?>">
                                    <td>
                                        <span class="fw-bold text-dark"><?= htmlspecialchars($mnName, ENT_QUOTES, 'UTF-8') ?></span>
                                    </td>
                                    <td>
                                        <span class="badge bg-light text-dark border"><?= htmlspecialchars($mn['Department_Name'], ENT_QUOTES, 'UTF-8') ?></span>
                                    </td>
                                    <td class="text-center fw-semibold"><?= (int)$mn['No_Credits_Required'] ?> cr</td>
                                    <td class="text-end pe-4">
                                        <div class="d-inline-flex gap-1">
                                            <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#mnReqModal<?= $mnId ?>">
                                                Courses
                                            </button>
                                            <?php if ($canDeclareMinor): ?>
                                                <form action="majors_minors_process.php" method="POST" class="m-0">
                                                    <input type="hidden" name="action" value="declare_minor">
                                                    <input type="hidden" name="minor_id" value="<?= $mnId ?>">
                                                    <button type="submit" class="btn btn-success btn-sm">Declare</button>
                                                </form>
                                            <?php else: ?>
                                                <button type="button" class="btn btn-light btn-sm text-secondary border" disabled title="<?= htmlspecialchars($minorReason, ENT_QUOTES, 'UTF-8') ?>">
                                                    <?= htmlspecialchars($minorReason, ENT_QUOTES, 'UTF-8') ?>
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </div>

    <!-- Modals for Major Requirements -->
    <?php foreach ($allMajors as $m): 
        $mid = (int)$m['Major_ID'];
        $reqs = $majorRequirements[$mid] ?? [];
    ?>
        <div class="modal fade" id="mReqModal<?= $mid ?>" tabindex="-1" aria-labelledby="mReqModalLabel<?= $mid ?>" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <div class="modal-content shadow">
                    <div class="modal-header">
                        <h5 class="modal-title fw-bold" id="mReqModalLabel<?= $mid ?>">
                            <?= htmlspecialchars($m['Major_Name'], ENT_QUOTES, 'UTF-8') ?> — Curriculum Requirements
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body p-0">
                        <?php if (empty($reqs)): ?>
                            <div class="p-4 text-center text-muted">No specific course requirements registered for this major.</div>
                        <?php else: ?>
                            <table class="table table-sm table-striped mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th class="ps-3">Course ID</th>
                                        <th>Course Name</th>
                                        <th>Department</th>
                                        <th class="text-center">Credits</th>
                                        <th class="text-center">Min Grade</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($reqs as $r): ?>
                                        <tr>
                                            <td class="ps-3 font-monospace fw-bold"><?= htmlspecialchars($r['Course_ID'], ENT_QUOTES, 'UTF-8') ?></td>
                                            <td><?= htmlspecialchars($r['Course_Name'], ENT_QUOTES, 'UTF-8') ?></td>
                                            <td class="small text-secondary"><?= htmlspecialchars($r['Department_Name'] ?? 'General', ENT_QUOTES, 'UTF-8') ?></td>
                                            <td class="text-center"><?= (int)$r['Course_Credits'] ?></td>
                                            <td class="text-center"><span class="badge bg-secondary"><?= htmlspecialchars($r['Min_Grade'], ENT_QUOTES, 'UTF-8') ?></span></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
                    </div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>

    <!-- Modals for Minor Requirements -->
    <?php foreach ($allMinors as $mn): 
        $mnId = (int)$mn['Minor_ID'];
        $reqs = $minorRequirements[$mnId] ?? [];
    ?>
        <div class="modal fade" id="mnReqModal<?= $mnId ?>" tabindex="-1" aria-labelledby="mnReqModalLabel<?= $mnId ?>" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <div class="modal-content shadow">
                    <div class="modal-header">
                        <h5 class="modal-title fw-bold" id="mnReqModalLabel<?= $mnId ?>">
                            <?= htmlspecialchars($mn['Minor_Name'], ENT_QUOTES, 'UTF-8') ?> — Course Requirements
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body p-0">
                        <?php if (empty($reqs)): ?>
                            <div class="p-4 text-center text-muted">No specific course requirements registered for this minor.</div>
                        <?php else: ?>
                            <table class="table table-sm table-striped mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th class="ps-3">Course ID</th>
                                        <th>Course Name</th>
                                        <th>Department</th>
                                        <th class="text-center">Credits</th>
                                        <th class="text-center">Min Grade</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($reqs as $r): ?>
                                        <tr>
                                            <td class="ps-3 font-monospace fw-bold"><?= htmlspecialchars($r['Course_ID'], ENT_QUOTES, 'UTF-8') ?></td>
                                            <td><?= htmlspecialchars($r['Course_Name'], ENT_QUOTES, 'UTF-8') ?></td>
                                            <td class="small text-secondary"><?= htmlspecialchars($r['Department_Name'] ?? 'General', ENT_QUOTES, 'UTF-8') ?></td>
                                            <td class="text-center"><?= (int)$r['Course_Credits'] ?></td>
                                            <td class="text-center"><span class="badge bg-secondary"><?= htmlspecialchars($r['Min_Grade'], ENT_QUOTES, 'UTF-8') ?></span></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
                    </div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>

    <!-- Bootstrap 5.3 JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

    <!-- Client-side directory filter script -->
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        const filterInput = document.getElementById('progFilter');
        if (filterInput) {
            filterInput.addEventListener('input', function () {
                const q = this.value.toLowerCase().trim();
                document.querySelectorAll('.prog-row').forEach(row => {
                    const text = row.dataset.search || '';
                    row.style.display = (!q || text.includes(q)) ? '' : 'none';
                });
            });
        }
    });
    </script>
</body>
</html>

