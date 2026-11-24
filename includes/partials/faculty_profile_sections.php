<?php
/**
 * Shared Partial: Faculty Profile Drill-Down Sections
 * Expects:
 *   int    $target_id
 *   bool   $canViewPrivate
 *   ?array $rosterSection
 *   ?array $rosterStudents
 *   array  $teachingSections
 *   array  $facultyAdvisees
 */
?>
<?php if ($rosterSection): ?>
    <!-- Section Roster Drill-Down Card -->
    <div id="faculty-roster-view" class="card shadow-sm border-0 border-top border-primary border-4 mb-4">
        <div class="card-header bg-white py-3 d-flex flex-wrap justify-content-between align-items-center gap-2">
            <div>
                <div class="d-flex align-items-center gap-2">
                    <span class="badge bg-primary fs-6">CRN <?= (int)$rosterSection['CRN'] ?></span>
                    <h5 class="fw-bold mb-0 text-dark">
                        <?= htmlspecialchars($rosterSection['Course_ID'] . ' - ' . $rosterSection['Course_Name'], ENT_QUOTES, 'UTF-8') ?>
                        (Sec <?= htmlspecialchars((string)$rosterSection['Section_Number'], ENT_QUOTES, 'UTF-8') ?>)
                    </h5>
                </div>
                <div class="text-secondary small mt-1">
                    Semester: <strong><?= htmlspecialchars(($rosterSection['Semester_Name'] ?? '') . ' ' . ($rosterSection['Semester_Year'] ?? ''), ENT_QUOTES, 'UTF-8') ?></strong> &bull;
                    Schedule: <?= formatSchedule($rosterSection['Time_Slot_Day'], $rosterSection['Start_Time'], $rosterSection['End_Time']) ?> &bull;
                    Room: <?= htmlspecialchars($rosterSection['Building_Name'] . ' ' . $rosterSection['Room_NO'], ENT_QUOTES, 'UTF-8') ?>
                </div>
            </div>
            <div>
                <a href="user_profile.php?user_id=<?= (int)$target_id ?>" class="btn btn-outline-secondary btn-sm">
                    Close Roster &times;
                </a>
            </div>
        </div>
        <div class="card-body p-0">
            <?php if (empty($rosterStudents)): ?>
                <div class="p-5 text-center text-muted">
                    <h6 class="fw-bold text-secondary mb-1">No Enrolled Students</h6>
                    <p class="small mb-0">No student enrollment records found for section CRN <?= (int)$rosterSection['CRN'] ?>.</p>
                </div>
            <?php else:
                $isPastRoster = ((int)($rosterSection['Semester_ID'] ?? 202608) < 202608) || (($rosterSection['Status'] ?? '') === 'Past');
            ?>
                <div class="table-responsive">
                    <table class="table table-hover table-striped mb-0 align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Student ID</th>
                                <th>Student Name</th>
                                <th>Email</th>
                                <th>Class Standing</th>
                                <th>Attendance</th>
                                <th>Declared Major(s)</th>
                                <?php if ($isPastRoster): ?>
                                    <th class="text-center">Final Grade</th>
                                <?php else: ?>
                                    <th class="text-center">Midterm Grade</th>
                                    <th class="text-center">Final Exam Grade</th>
                                    <th class="text-center">Final Course Grade</th>
                                <?php endif; ?>
                                <th class="text-end pe-3">Profile</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($rosterStudents as $st): ?>
                                <tr>
                                    <td><strong><?= (int)$st['Student_ID'] ?></strong></td>
                                    <td>
                                        <a href="user_profile.php?user_id=<?= (int)$st['Student_ID'] ?>" class="text-decoration-none fw-semibold text-primary">
                                            <?= htmlspecialchars($st['full_name'], ENT_QUOTES, 'UTF-8') ?>
                                        </a>
                                    </td>
                                    <td>
                                        <a href="mailto:<?= htmlspecialchars($st['User_Email'] ?? '', ENT_QUOTES, 'UTF-8') ?>" class="text-muted small">
                                            <?= htmlspecialchars($st['User_Email'] ?? 'N/A', ENT_QUOTES, 'UTF-8') ?>
                                        </a>
                                    </td>
                                    <td><span class="badge bg-secondary"><?= htmlspecialchars($st['class_standing'], ENT_QUOTES, 'UTF-8') ?></span></td>
                                    <td><small class="text-muted"><?= htmlspecialchars($st['Attendance_Type'] ?? 'Full-Time', ENT_QUOTES, 'UTF-8') ?></small></td>
                                    <td><small><?= htmlspecialchars($st['Major_Name'] ?? 'Undeclared', ENT_QUOTES, 'UTF-8') ?></small></td>
                                    <?php if ($isPastRoster): ?>
                                        <td class="text-center">
                                            <?= !empty($st['Grade']) ? '<span class="badge bg-success">' . htmlspecialchars($st['Grade'], ENT_QUOTES, 'UTF-8') . '</span>' : '<span class="text-muted">&mdash;</span>' ?>
                                        </td>
                                    <?php else: ?>
                                        <td class="text-center">
                                            <?= !empty($st['Midterm_Grade']) ? '<span class="badge bg-info text-dark">' . htmlspecialchars($st['Midterm_Grade'], ENT_QUOTES, 'UTF-8') . '</span>' : '<span class="text-muted">&mdash;</span>' ?>
                                        </td>
                                        <td class="text-center">
                                            <?= !empty($st['Final_Grade']) ? '<span class="badge bg-primary">' . htmlspecialchars($st['Final_Grade'], ENT_QUOTES, 'UTF-8') . '</span>' : '<span class="text-muted">&mdash;</span>' ?>
                                        </td>
                                        <td class="text-center">
                                            <?= !empty($st['Grade']) ? '<span class="badge bg-success">' . htmlspecialchars($st['Grade'], ENT_QUOTES, 'UTF-8') . '</span>' : '<span class="text-muted">&mdash;</span>' ?>
                                        </td>
                                    <?php endif; ?>
                                    <td class="text-end pe-3">
                                        <a href="user_profile.php?user_id=<?= (int)$st['Student_ID'] ?>" class="btn btn-outline-primary btn-sm py-0 px-2" style="font-size: 0.8rem;">
                                            View &rarr;
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>

<!-- Teaching Schedule Card -->
<?php
$displaySections = $teachingSections;
if (!$canViewPrivate) {
    // Directory view: only show current & upcoming course offerings
    $displaySections = array_filter($teachingSections, fn($s) => (int)$s['Semester_ID'] >= 202608);
}
?>
<div class="card shadow-sm border-0 mb-4">
    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
        <h5 class="mb-0 fw-bold">
            <?= $canViewPrivate ? 'Teaching Schedule Across Semesters' : 'Current &amp; Upcoming Course Offerings' ?>
        </h5>
        <span class="badge bg-secondary"><?= count($displaySections) ?> Total Section<?= count($displaySections) !== 1 ? 's' : '' ?></span>
    </div>
    <div class="card-body p-0">
        <?php if (empty($displaySections)): ?>
            <div class="p-5 text-center text-muted">
                <h6 class="fw-bold text-secondary mb-1">No Scheduled Teaching Sections</h6>
                <p class="small mb-0">No course sections scheduled for this instructor.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover table-striped mb-0 align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Term</th>
                            <th>CRN</th>
                            <th>Course ID</th>
                            <th>Sec</th>
                            <th>Course Name</th>
                            <th class="text-center">Credits</th>
                            <th>Day / Time</th>
                            <th>Room</th>
                            <?php if ($canViewPrivate): ?>
                                <th class="text-center">Enrollment / Allowed</th>
                            <?php else: ?>
                                <th class="text-center">Available Seats</th>
                            <?php endif; ?>
                            <th class="text-center">Status</th>
                            <?php if ($canViewPrivate): ?>
                                <th class="text-end pe-3">Roster</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($displaySections as $ts):
                            $tsCrn = (int)$ts['CRN'];
                            $tsSemId = (int)$ts['Semester_ID'];
                            $isPast = ($tsSemId < 202608);
                            $enr = (int)($ts['Enrolled_Count'] ?? 0);
                            $seats = (int)($ts['Available_seats'] ?? 0);
                            $allowed = $enr + $seats;
                            $secStatus = $ts['Status'] ?? 'Active';
                        ?>
                            <tr>
                                <td>
                                    <span class="badge bg-light text-dark border">
                                        <?= htmlspecialchars(($ts['Semester_Name'] ?? '') . ' ' . ($ts['Semester_Year'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                                    </span>
                                </td>
                                <td><strong><?= $tsCrn ?></strong></td>
                                <td><span class="badge text-bg-light border"><?= htmlspecialchars($ts['Course_ID'], ENT_QUOTES, 'UTF-8') ?></span></td>
                                <td><?= htmlspecialchars((string)$ts['Section_Number'], ENT_QUOTES, 'UTF-8') ?></td>
                                <td>
                                    <span class="fw-semibold"><?= htmlspecialchars($ts['Course_Name'], ENT_QUOTES, 'UTF-8') ?></span>
                                    <span class="badge bg-secondary-subtle text-secondary small ms-1"><?= htmlspecialchars($ts['Course_Type'], ENT_QUOTES, 'UTF-8') ?></span>
                                </td>
                                <td class="text-center fw-bold"><?= (int)$ts['Course_Credits'] ?></td>
                                <td><?= formatSchedule($ts['Time_Slot_Day'], $ts['Start_Time'], $ts['End_Time']) ?></td>
                                <td><?= htmlspecialchars($ts['Building_Name'] . ' ' . $ts['Room_NO'], ENT_QUOTES, 'UTF-8') ?></td>
                                <?php if ($canViewPrivate): ?>
                                    <td class="text-center">
                                        <?php if ($isPast || $secStatus === 'Past'): ?>
                                            <span class="badge bg-secondary"><?= $enr ?> Enrolled</span>
                                        <?php elseif ($seats <= 0): ?>
                                            <span class="badge bg-danger"><?= $enr ?> / <?= $allowed ?> (Full)</span>
                                        <?php else: ?>
                                            <span class="badge bg-success"><?= $enr ?> / <?= $allowed ?></span>
                                        <?php endif; ?>
                                    </td>
                                <?php else: ?>
                                    <td class="text-center">
                                        <?php if ($seats > 0): ?>
                                            <span class="badge bg-success"><?= $seats ?> open</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger">Full (0)</span>
                                        <?php endif; ?>
                                    </td>
                                <?php endif; ?>
                                <td class="text-center">
                                    <?php if ($secStatus === 'Active'): ?>
                                        <span class="badge bg-success">Active</span>
                                    <?php elseif ($secStatus === 'Past'): ?>
                                        <span class="badge bg-secondary">Past</span>
                                    <?php elseif ($secStatus === 'Cancelled'): ?>
                                        <span class="badge bg-danger">Cancelled</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary"><?= htmlspecialchars($secStatus, ENT_QUOTES, 'UTF-8') ?></span>
                                    <?php endif; ?>
                                </td>
                                <?php if ($canViewPrivate): ?>
                                    <td class="text-end pe-3">
                                        <a href="user_profile.php?user_id=<?= (int)$target_id ?>&amp;roster_crn=<?= $tsCrn ?>#faculty-roster-view" class="btn btn-sm btn-outline-primary py-0 px-2" style="font-size: 0.8rem;">
                                            Roster &rarr;
                                        </a>
                                    </td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php if ($canViewPrivate): ?>
    <!-- Assigned Advisees Card -->
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
            <h5 class="mb-0 fw-bold">Assigned Academic Advisees</h5>
            <span class="badge <?= count($facultyAdvisees) >= 15 ? 'bg-danger' : 'bg-primary' ?>">
                <?= count($facultyAdvisees) ?> / 15 Advisees
            </span>
        </div>
        <div class="card-body p-0">
            <?php if (empty($facultyAdvisees)): ?>
                <div class="p-5 text-center text-muted">
                    <h6 class="fw-bold text-secondary mb-1">No Advisees Assigned</h6>
                    <p class="small mb-0">This faculty advisor currently has no active student advisees assigned.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover table-striped mb-0 align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Student ID</th>
                                <th>Student Name</th>
                                <th>Email</th>
                                <th>Standing</th>
                                <th>Attendance</th>
                                <th>Department</th>
                                <th>Major(s)</th>
                                <th>Date Assigned</th>
                                <th>Holds</th>
                                <th class="text-end pe-3">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($facultyAdvisees as $adv): ?>
                                <tr>
                                    <td><strong><?= (int)$adv['Student_ID'] ?></strong></td>
                                    <td>
                                        <a href="user_profile.php?user_id=<?= (int)$adv['Student_ID'] ?>" class="text-decoration-none fw-semibold text-primary">
                                            <?= htmlspecialchars($adv['User_FName'] . ' ' . $adv['User_LName'], ENT_QUOTES, 'UTF-8') ?>
                                        </a>
                                    </td>
                                    <td>
                                        <a href="mailto:<?= htmlspecialchars($adv['User_Email'] ?? '', ENT_QUOTES, 'UTF-8') ?>" class="text-muted small">
                                            <?= htmlspecialchars($adv['User_Email'] ?? 'N/A', ENT_QUOTES, 'UTF-8') ?>
                                        </a>
                                    </td>
                                    <td><span class="badge bg-secondary"><?= htmlspecialchars($adv['Student_Type'] ?? 'Undergrad', ENT_QUOTES, 'UTF-8') ?></span></td>
                                    <td><small class="text-muted"><?= htmlspecialchars($adv['Attendance_Type'] ?? 'Full-Time', ENT_QUOTES, 'UTF-8') ?></small></td>
                                    <td><small><?= htmlspecialchars($adv['Department_Name'] ?? 'General', ENT_QUOTES, 'UTF-8') ?></small></td>
                                    <td><small><?= htmlspecialchars($adv['Majors'] ?? 'Undeclared', ENT_QUOTES, 'UTF-8') ?></small></td>
                                    <td><small class="text-muted"><?= !empty($adv['Date_Assigned']) ? htmlspecialchars(date('M j, Y', strtotime($adv['Date_Assigned'])), ENT_QUOTES, 'UTF-8') : 'N/A' ?></small></td>
                                    <td>
                                        <?php if ((int)($adv['Active_Holds_Count'] ?? 0) > 0): ?>
                                            <span class="badge bg-danger"><?= (int)$adv['Active_Holds_Count'] ?> Hold(s)</span>
                                        <?php else: ?>
                                            <span class="badge bg-success">Clear</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end pe-3">
                                        <a href="user_profile.php?user_id=<?= (int)$adv['Student_ID'] ?>" class="btn btn-outline-primary btn-sm py-0 px-2" style="font-size: 0.8rem;">
                                            View Profile &rarr;
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>
