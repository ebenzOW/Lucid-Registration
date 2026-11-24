<?php
/**
 * Shared Partial: Admin Student Inspection Workspace
 * Expects:
 *   array   $user
 *   int     $target_id
 *   string  $target_type
 *   string  $fullName
 *   bool    $canViewPrivate
 *   string  $viewer_type
 *   int     $viewer_id
 *   ?array  $studentData
 *   array   $studentHolds
 *   string  $adminView
 *   ?array  $fallSchedule
 *   ?array  $springSchedule
 *   ?array  $attRecords
 *   ?array  $transcriptData
 *   ?array  $auditData
 *   string  $rootPrefix
 */
$rootPrefix = $rootPrefix ?? '';
$standing = $studentData['class_standing'] ?? 'Undergraduate';
$attType = $studentData['Attendance_Type'] ?? 'Full-Time';
$cumGpa = number_format((float)($studentData['cum_gpa'] ?? 0), 2);
$creditsEarned = (int)($studentData['Credits_Earned'] ?? 0);
$majorsList = !empty($studentData['majors']) ? implode(', ', $studentData['majors']) : 'Undeclared';
$minorName = $studentData['minor'] ?? 'None';
$deptName = $studentData['Department_Name'] ?? 'General Academics';
$holdsCount = count($studentHolds);
$initials = strtoupper(substr($user['User_FName'] ?? '', 0, 1) . substr($user['User_LName'] ?? '', 0, 1));

$adminNavTabs = [
    'details'    => 'Demographic & Student Details',
    'enrollment' => 'Current & Upcoming Enrollment',
    'grades'     => 'Current Semester Grades',
    'attendance' => 'Fall 2026 Attendance',
    'transcript' => 'Academic Transcript',
    'audit'      => 'Degree Audit Progress',
];
?>
<!-- Student Summary Header Card -->
<div class="card shadow-sm mb-4 border-0 border-top border-primary border-4">
    <div class="card-body p-4 bg-white rounded-3">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
            <div class="d-flex align-items-center gap-3">
                <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center fw-bold fs-2" style="width: 72px; height: 72px;">
                    <?= htmlspecialchars($initials, ENT_QUOTES, 'UTF-8') ?>
                </div>
                <div>
                    <div class="d-flex align-items-center gap-2 flex-wrap mb-1">
                        <h2 class="fw-bold mb-0"><?= htmlspecialchars($fullName, ENT_QUOTES, 'UTF-8') ?></h2>
                        <span class="badge bg-primary fs-6">ID: <?= (int)$target_id ?></span>
                        <span class="badge bg-secondary"><?= htmlspecialchars($standing, ENT_QUOTES, 'UTF-8') ?></span>
                        <span class="badge bg-info text-dark"><?= htmlspecialchars($attType, ENT_QUOTES, 'UTF-8') ?></span>
                        <?php if ($holdsCount > 0): ?>
                            <span class="badge bg-danger"><?= $holdsCount ?> Active Hold(s)</span>
                        <?php else: ?>
                            <span class="badge bg-success">No Holds</span>
                        <?php endif; ?>
                    </div>
                    <div class="text-secondary small">
                        Department: <strong><?= htmlspecialchars($deptName, ENT_QUOTES, 'UTF-8') ?></strong> &bull;
                        Major: <strong><?= htmlspecialchars($majorsList, ENT_QUOTES, 'UTF-8') ?></strong> &bull;
                        Minor: <strong><?= htmlspecialchars($minorName, ENT_QUOTES, 'UTF-8') ?></strong> &bull;
                        GPA: <strong class="text-dark"><?= $cumGpa ?></strong> &bull;
                        Earned Credits: <strong class="text-dark"><?= $creditsEarned ?></strong>
                    </div>
                </div>
            </div>
            <div class="d-flex flex-wrap gap-2">
                <a href="admin/manage_change_requests.php?search_user=<?= (int)$target_id ?>" class="btn btn-primary btn-sm">
                    Change Requests &amp; Audit Log
                </a>
                <a href="admin/view_users.php?view=undergrads" class="btn btn-outline-secondary btn-sm">
                    &larr; User Directory
                </a>
                <a href="admin/dashboard.php" class="btn btn-outline-primary btn-sm">
                    Admin Dashboard &rarr;
                </a>
            </div>
        </div>
    </div>
</div>

<!-- 2-Column Student Inspection Workspace -->
<div class="row g-4">
    <!-- Left Column: Navigation Menu -->
    <div class="col-lg-3">
        <div class="card shadow-sm border-0 sticky-top" style="top: 20px;">
            <div class="card-header bg-white py-3">
                <h6 class="mb-0 fw-bold text-muted text-uppercase small">Student Navigation</h6>
            </div>
            <div class="list-group list-group-flush p-2">
                <?php $tabKeys = array_keys($adminNavTabs); $lastTabKey = end($tabKeys); ?>
                <?php foreach ($adminNavTabs as $tabKey => $tabLabel): ?>
                    <a href="user_profile.php?user_id=<?= (int)$target_id ?>&amp;view=<?= urlencode($tabKey) ?>"
                       class="list-group-item list-group-item-action rounded <?= $tabKey !== $lastTabKey ? 'mb-1 ' : '' ?><?= $adminView === $tabKey ? 'active fw-bold' : '' ?>">
                        <?= htmlspecialchars($tabLabel, ENT_QUOTES, 'UTF-8') ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Right Column: Content Workspace -->
    <div class="col-lg-9">
        <?php if ($adminView === 'enrollment'):
            $termSchedules = [
                ['title' => 'Fall 2026 Enrolled Sections (Current Term)', 'empty' => 'No course sections enrolled for Fall 2026.', 'data' => $fallSchedule],
                ['title' => 'Spring 2027 Pre-Registration (Upcoming Term)', 'empty' => 'No pre-registered course sections for Spring 2027.', 'data' => $springSchedule],
            ];
        ?>
            <?php foreach ($termSchedules as $termBlock):
                $schedData = $termBlock['data'] ?? [];
                $schedCourses = $schedData['courses'] ?? [];
            ?>
                <div class="card shadow-sm border-0 mb-4">
                    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                        <h5 class="mb-0 fw-bold"><?= htmlspecialchars($termBlock['title'], ENT_QUOTES, 'UTF-8') ?></h5>
                        <span class="badge bg-primary"><?= (int)($schedData['total_credits'] ?? 0) ?> Total Credits</span>
                    </div>
                    <div class="card-body p-0">
                        <?php if (empty($schedCourses)): ?>
                            <div class="p-4 text-center text-muted"><?= htmlspecialchars($termBlock['empty'], ENT_QUOTES, 'UTF-8') ?></div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover table-striped mb-0 align-middle small">
                                    <thead class="table-light">
                                        <tr>
                                            <th>CRN</th>
                                            <th>Course ID</th>
                                            <th>Sec</th>
                                            <th>Course Name</th>
                                            <th class="text-center">Credits</th>
                                            <th>Instructor</th>
                                            <th>Schedule</th>
                                            <th>Room</th>
                                            <th class="text-center">Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($schedCourses as $c):
                                            $cStatus = $c['Status'] ?? 'Active';
                                            $statusBadge = ($cStatus === 'Active') ? 'bg-success' : (($cStatus === 'Cancelled') ? 'bg-danger' : 'bg-secondary');
                                        ?>
                                            <tr>
                                                <td><strong><?= (int)$c['CRN'] ?></strong></td>
                                                <td><span class="badge text-bg-light border"><?= htmlspecialchars($c['Course_ID'], ENT_QUOTES, 'UTF-8') ?></span></td>
                                                <td><?= htmlspecialchars((string)$c['Section_Number'], ENT_QUOTES, 'UTF-8') ?></td>
                                                <td class="fw-semibold"><?= htmlspecialchars($c['Course_Name'], ENT_QUOTES, 'UTF-8') ?></td>
                                                <td class="text-center fw-bold"><?= (int)$c['Course_Credits'] ?></td>
                                                <td><?= renderFacultyLink($c['Faculty_ID'] ?? 0, $c['Instructor_Name'], $rootPrefix) ?></td>
                                                <td><?= formatSchedule($c['Time_Slot_Day'], $c['Start_Time'], $c['End_Time']) ?></td>
                                                <td><?= htmlspecialchars($c['Building_Name'] . ' ' . $c['Room_NO'], ENT_QUOTES, 'UTF-8') ?></td>
                                                <td class="text-center">
                                                    <span class="badge <?= $statusBadge ?>">
                                                        <?= htmlspecialchars($cStatus, ENT_QUOTES, 'UTF-8') ?>
                                                    </span>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>

        <?php elseif ($adminView === 'grades'): ?>
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-white py-3">
                    <h5 class="mb-0 fw-bold">Current Semester Grades (Fall 2026)</h5>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($fallSchedule['courses'])): ?>
                        <div class="p-4 text-center text-muted">No enrolled course sections found for Fall 2026.</div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover table-striped mb-0 align-middle small">
                                <thead class="table-light">
                                    <tr>
                                        <th>CRN</th>
                                        <th>Course</th>
                                        <th>Instructor</th>
                                        <th class="text-center">Credits</th>
                                        <th class="text-center">Midterm Grade</th>
                                        <th class="text-center">Final Exam Grade</th>
                                        <th class="text-center">Overall Course Grade</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($fallSchedule['courses'] as $c): ?>
                                        <tr>
                                            <td><strong><?= (int)$c['CRN'] ?></strong></td>
                                            <td>
                                                <div class="fw-semibold"><?= htmlspecialchars($c['Course_ID'] . ' - ' . $c['Course_Name'], ENT_QUOTES, 'UTF-8') ?></div>
                                                <small class="text-muted">Sec <?= htmlspecialchars((string)$c['Section_Number'], ENT_QUOTES, 'UTF-8') ?></small>
                                            </td>
                                            <td><?= renderFacultyLink($c['Faculty_ID'] ?? 0, $c['Instructor_Name'], $rootPrefix) ?></td>
                                            <td class="text-center fw-bold"><?= (int)$c['Course_Credits'] ?></td>
                                            <td class="text-center">
                                                <?php if (!empty($c['Midterm_Grade'])): ?>
                                                    <span class="badge bg-info text-dark fs-6"><?= htmlspecialchars($c['Midterm_Grade'], ENT_QUOTES, 'UTF-8') ?></span>
                                                <?php else: ?>
                                                    <span class="text-muted">&mdash;</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-center">
                                                <?php if (!empty($c['Final_Grade'])): ?>
                                                    <span class="badge bg-primary fs-6"><?= htmlspecialchars($c['Final_Grade'], ENT_QUOTES, 'UTF-8') ?></span>
                                                <?php else: ?>
                                                    <span class="text-muted">&mdash;</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-center">
                                                <?php if (!empty($c['Grade'])): ?>
                                                    <span class="badge bg-success fs-6"><?= htmlspecialchars($c['Grade'], ENT_QUOTES, 'UTF-8') ?></span>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary-subtle text-secondary">In Progress</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

        <?php elseif ($adminView === 'attendance'): ?>
            <div class="mb-4">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="mb-0 fw-bold">Fall 2026 Student Attendance Records</h5>
                    <span class="badge bg-secondary">Term 202608</span>
                </div>
                <?php if (empty($attRecords)): ?>
                    <div class="card shadow-sm border-0 p-5 text-center text-muted">
                        <h6>No Attendance Records Found</h6>
                        <p class="mb-0 small">No attendance sessions recorded for this student in Fall 2026.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($attRecords as $cb): ?>
                        <div class="card shadow-sm border-0 mb-4">
                            <div class="card-header bg-white py-3 d-flex flex-wrap justify-content-between align-items-center gap-2">
                                <div>
                                    <span class="badge bg-primary fs-6">CRN <?= (int)$cb['CRN'] ?></span>
                                    <strong class="ms-1"><?= htmlspecialchars($cb['Course_ID'] . ' - ' . $cb['Course_Name'], ENT_QUOTES, 'UTF-8') ?></strong>
                                    <span class="text-muted small ms-1">(Sec <?= htmlspecialchars((string)$cb['Section_Number'], ENT_QUOTES, 'UTF-8') ?>)</span>
                                    <span class="text-secondary small ms-2">&bull; Instructor: <?= htmlspecialchars($cb['Instructor_Name'], ENT_QUOTES, 'UTF-8') ?></span>
                                </div>
                                <div class="d-flex gap-2 align-items-center flex-wrap">
                                    <span class="badge bg-light text-dark border">Recorded: <?= (int)$cb['total_sessions'] ?></span>
                                    <span class="badge bg-success-subtle text-success border border-success-subtle">Present: <?= (int)$cb['present_count'] ?></span>
                                    <span class="badge bg-danger-subtle text-danger border border-danger-subtle">Absent: <?= (int)$cb['absent_count'] ?></span>
                                    <span class="badge <?= $cb['attendance_pct'] >= 80 ? 'bg-success' : ($cb['attendance_pct'] >= 65 ? 'bg-warning text-dark' : 'bg-danger') ?>">
                                        Rate: <?= htmlspecialchars((string)$cb['attendance_pct'], ENT_QUOTES, 'UTF-8') ?>%
                                    </span>
                                </div>
                            </div>
                            <div class="card-body p-0">
                                <?php if (empty($cb['records'])): ?>
                                    <div class="p-3 text-center text-muted small">No session logs for this course.</div>
                                <?php else: ?>
                                    <div class="table-responsive" style="max-height: 280px;">
                                        <table class="table table-sm table-striped mb-0 align-middle small">
                                            <thead class="table-light sticky-top">
                                                <tr>
                                                    <th>Class Date</th>
                                                    <th>Day of Week</th>
                                                    <th>Attendance Status</th>
                                                    <th>Recorded Timestamp</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($cb['records'] as $rec):
                                                    $dayName = date('l', strtotime($rec['Class_Date']));
                                                ?>
                                                    <tr>
                                                        <td class="fw-semibold"><?= htmlspecialchars($rec['Class_Date'], ENT_QUOTES, 'UTF-8') ?></td>
                                                        <td><?= htmlspecialchars($dayName, ENT_QUOTES, 'UTF-8') ?></td>
                                                        <td>
                                                            <?php if ($rec['Status'] === 'Present'): ?>
                                                                <span class="badge bg-success">Present</span>
                                                            <?php elseif ($rec['Status'] === 'Absent'): ?>
                                                                <span class="badge bg-danger">Absent</span>
                                                            <?php else: ?>
                                                                <span class="badge bg-secondary"><?= htmlspecialchars($rec['Status'], ENT_QUOTES, 'UTF-8') ?></span>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td class="text-muted"><?= htmlspecialchars($rec['Recorded_At'] ?? 'Auto-logged', ENT_QUOTES, 'UTF-8') ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

        <?php elseif ($adminView === 'transcript'): ?>
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-white py-3">
                    <h5 class="mb-0 fw-bold">Official Student Academic Transcript</h5>
                </div>
                <div class="card-body p-4">
                    <?php require __DIR__ . '/student_transcript_body.php'; ?>
                </div>
            </div>

        <?php elseif ($adminView === 'audit'): ?>
            <?php require __DIR__ . '/student_degree_audit_body.php'; ?>

        <?php else: ?>
            <div class="row g-4 mb-4">
                <?php
                    $isAdminDetailsView = true;
                    $demographicsOnly = false;
                    $colClass = 'col-md-6';
                    require __DIR__ . '/student_profile_cards.php';
                ?>

                <!-- Active Holds Card -->
                <div class="col-12">
                    <div class="card shadow-sm border-0">
                        <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                            <h6 class="mb-0 fw-bold">Active Account Holds</h6>
                            <a href="admin/manage_holds.php" class="btn btn-outline-primary btn-sm py-0 px-2" style="font-size: 0.8rem;">
                                Manage Holds &rarr;
                            </a>
                        </div>
                        <div class="card-body p-0">
                            <?php if (empty($studentHolds)): ?>
                                <div class="p-3 text-center text-success small">
                                    Clear &mdash; No active holds on this student account.
                                </div>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-sm table-striped mb-0 align-middle small">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Hold Type</th>
                                                <th>Description</th>
                                                <th>Date Placed</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($studentHolds as $h): ?>
                                                <tr>
                                                    <td><span class="badge bg-danger"><?= htmlspecialchars($h['Hold_Type'] ?? $h['Hold_Name'] ?? 'Hold', ENT_QUOTES, 'UTF-8') ?></span></td>
                                                    <td><?= htmlspecialchars($h['Hold_Description'] ?? $h['Description'] ?? 'Registration restriction', ENT_QUOTES, 'UTF-8') ?></td>
                                                    <td class="text-muted"><?= htmlspecialchars($h['Hold_Date'] ?? $h['Date_Placed'] ?? 'Active', ENT_QUOTES, 'UTF-8') ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>
