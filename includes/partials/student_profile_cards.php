<?php
/**
 * Shared Partial: User Demographic & Student Academic Profile Cards
 * Expects:
 *   array  $user
 *   int    $target_id
 *   string $target_type
 *   string $fullName
 *   bool   $canViewPrivate
 *   string $viewer_type (optional)
 *   int    $viewer_id (optional)
 *   ?array $studentData (optional)
 *   string $colClass (optional, e.g. 'col-md-6' or 'col-lg-6')
 *   bool   $isAdminDetailsView (optional)
 *   bool   $demographicsOnly (optional)
 *   string $rootPrefix (optional, '' from root or '../' from subdirectories)
 */
$colClass = $colClass ?? 'col-md-6';
$isAdminDetailsView = !empty($isAdminDetailsView);
$demographicsOnly = !empty($demographicsOnly);
$rootPrefix = $rootPrefix ?? '';
$viewer_type = $viewer_type ?? '';
$viewer_id = (int)($viewer_id ?? 0);
$canRequestUpdate = !$isAdminDetailsView && in_array($viewer_type, ['Student', 'Faculty'], true) && $target_id === $viewer_id;
$formattedAddress = $canViewPrivate ? formatAddress($user) : '';
?>
<div class="<?= htmlspecialchars($colClass, ENT_QUOTES, 'UTF-8') ?>">
    <div class="card shadow-sm border-0 h-100">
        <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
            <?php if ($isAdminDetailsView): ?>
                <h6 class="mb-0 fw-bold">Demographic &amp; Contact Details</h6>
            <?php else: ?>
                <h5 class="mb-0 fw-bold">General Account Information</h5>
            <?php endif; ?>
            <?php if ($canRequestUpdate): ?>
                <a href="<?= htmlspecialchars($rootPrefix . ($viewer_type === 'Faculty' ? 'faculty' : 'student') . '/profile_requests.php', ENT_QUOTES, 'UTF-8') ?>" class="btn btn-outline-primary btn-sm">
                    Request Info Update &rarr;
                </a>
            <?php endif; ?>
        </div>
        <div class="card-body <?= $isAdminDetailsView ? 'p-3' : 'p-4' ?>">
            <table class="table table-borderless table-sm mb-0 <?= $isAdminDetailsView ? 'small' : '' ?>">
                <tbody>
                    <tr>
                        <th class="text-muted <?= $isAdminDetailsView ? 'w-40' : 'w-35' ?>">User ID:</th>
                        <td><strong><?= (int)$target_id ?></strong></td>
                    </tr>
                    <?php if (!$isAdminDetailsView): ?>
                        <tr>
                            <th class="text-muted">Account Role:</th>
                            <td><?= htmlspecialchars($target_type, ENT_QUOTES, 'UTF-8') ?></td>
                        </tr>
                    <?php endif; ?>
                    <tr>
                        <th class="text-muted">Full Name:</th>
                        <td><?= htmlspecialchars($fullName, ENT_QUOTES, 'UTF-8') ?></td>
                    </tr>
                    <tr>
                        <th class="text-muted">University Email:</th>
                        <td>
                            <a href="mailto:<?= htmlspecialchars($user['User_Email'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                                <?= htmlspecialchars($user['User_Email'] ?? 'N/A', ENT_QUOTES, 'UTF-8') ?>
                            </a>
                        </td>
                    </tr>
                    <tr>
                        <th class="text-muted">Phone Number:</th>
                        <td>
                            <?php if ($canViewPrivate): ?>
                                <?= htmlspecialchars($user['User_PhoneNo'] ?? 'N/A', ENT_QUOTES, 'UTF-8') ?>
                            <?php else: ?>
                                <span class="text-muted fst-italic">Protected (Directory View)</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th class="text-muted">Gender:</th>
                        <td>
                            <?php if ($canViewPrivate): ?>
                                <?= htmlspecialchars($user['User_Gender'] ?? 'N/A', ENT_QUOTES, 'UTF-8') ?>
                            <?php else: ?>
                                <span class="text-muted fst-italic">Protected</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th class="text-muted">Date of Birth:</th>
                        <td>
                            <?php if ($canViewPrivate): ?>
                                <?= htmlspecialchars($user['User_DOB'] ?? 'N/A', ENT_QUOTES, 'UTF-8') ?>
                            <?php else: ?>
                                <span class="text-muted fst-italic">Protected</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th class="text-muted align-top"><?= $isAdminDetailsView ? 'Full Address:' : 'Residential Address:' ?></th>
                        <td>
                            <?php if ($canViewPrivate): ?>
                                <?php if ($formattedAddress !== ''): ?>
                                    <?= htmlspecialchars($formattedAddress, ENT_QUOTES, 'UTF-8') ?>
                                <?php else: ?>
                                    <span class="text-muted">No address on file</span>
                                <?php endif; ?>
                            <?php else: ?>
                                <span class="text-muted fst-italic">Protected (Directory View)</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php if (!$demographicsOnly && $target_type === 'Student' && !empty($studentData)):
    $standing = $studentData['class_standing'] ?? 'Undergraduate';
    $attType = $studentData['Attendance_Type'] ?? 'Full-Time';
    $deptName = $studentData['Department_Name'] ?? 'General Academics';
    $majorsList = !empty($studentData['majors']) ? implode(', ', $studentData['majors']) : null;
    $minorName = $studentData['minor'] ?? null;
    $holdsCount = (int)($studentData['holds_count'] ?? 0);
    $cleanThesis = cleanThesisTitle($studentData['Thesis'] ?? null);
?>
<div class="<?= htmlspecialchars($colClass, ENT_QUOTES, 'UTF-8') ?>">
    <div class="card shadow-sm border-0 h-100">
        <div class="card-header bg-white py-3">
            <?php if ($isAdminDetailsView): ?>
                <h6 class="mb-0 fw-bold">Academic Standing &amp; Advising</h6>
            <?php else: ?>
                <h5 class="mb-0 fw-bold">Academic Details &amp; Standing</h5>
            <?php endif; ?>
        </div>
        <div class="card-body <?= $isAdminDetailsView ? 'p-3' : 'p-4' ?>">
            <table class="table table-borderless table-sm mb-0 <?= $isAdminDetailsView ? 'small' : '' ?>">
                <tbody>
                    <tr>
                        <th class="text-muted <?= $isAdminDetailsView ? 'w-40' : 'w-35' ?>">Class Standing:</th>
                        <td>
                            <span class="badge bg-primary"><?= htmlspecialchars($standing, ENT_QUOTES, 'UTF-8') ?></span>
                            (<?= htmlspecialchars($attType, ENT_QUOTES, 'UTF-8') ?>)
                        </td>
                    </tr>
                    <tr>
                        <th class="text-muted">Student Level:</th>
                        <td><?= htmlspecialchars($studentData['Student_Type'] ?? 'Undergraduate', ENT_QUOTES, 'UTF-8') ?></td>
                    </tr>
                    <tr>
                        <th class="text-muted"><?= $isAdminDetailsView ? 'Department:' : 'Home Department:' ?></th>
                        <td><?= htmlspecialchars($deptName, ENT_QUOTES, 'UTF-8') ?></td>
                    </tr>
                    <tr>
                        <th class="text-muted">Cumulative GPA:</th>
                        <td><strong><?= number_format((float)($studentData['cum_gpa'] ?? 0), 2) ?></strong> / 4.00</td>
                    </tr>
                    <tr>
                        <th class="text-muted">Earned Credits:</th>
                        <td><?= (int)($studentData['Credits_Earned'] ?? 0) ?> credits</td>
                    </tr>
                    <tr>
                        <th class="text-muted"><?= $isAdminDetailsView ? 'Major(s):' : 'Declared Major(s):' ?></th>
                        <td>
                            <?php if ($majorsList !== null): ?>
                                <?= htmlspecialchars($majorsList, ENT_QUOTES, 'UTF-8') ?>
                            <?php else: ?>
                                <span class="text-muted">Undeclared</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th class="text-muted"><?= $isAdminDetailsView ? 'Minor:' : 'Declared Minor:' ?></th>
                        <td>
                            <?php if ($minorName !== null): ?>
                                <?= htmlspecialchars($minorName, ENT_QUOTES, 'UTF-8') ?>
                            <?php else: ?>
                                <span class="text-muted">None</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th class="text-muted align-top"><?= $isAdminDetailsView ? 'Academic Advisor:' : 'Assigned Advisor:' ?></th>
                        <td>
                            <?php if (!empty($studentData['advisor'])): ?>
                                <strong><?= renderFacultyLink($studentData['advisor']['Faculty_ID'] ?? 0, 'Dr. ' . ($studentData['advisor']['User_FName'] ?? '') . ' ' . ($studentData['advisor']['User_LName'] ?? ''), $rootPrefix) ?></strong><br>
                                <small class="text-muted">
                                    Email: <a href="mailto:<?= htmlspecialchars($studentData['advisor']['User_Email'] ?? '', ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($studentData['advisor']['User_Email'] ?? '', ENT_QUOTES, 'UTF-8') ?></a>
                                </small>
                            <?php else: ?>
                                <span class="text-muted">No academic advisor assigned</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th class="text-muted">Account Holds:</th>
                        <td>
                            <?php if ($holdsCount > 0): ?>
                                <span class="badge bg-danger"><?= $holdsCount ?> Active Hold(s)</span>
                            <?php else: ?>
                                <span class="badge bg-success">Clear (No Active Holds)</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php if (($studentData['Student_Type'] ?? '') === 'Graduate'): ?>
                        <tr>
                            <th class="text-muted">Grad Program:</th>
                            <td><?= htmlspecialchars($studentData['Grad_Program'] ?? 'General', ENT_QUOTES, 'UTF-8') ?></td>
                        </tr>
                        <?php if ($cleanThesis !== ''): ?>
                            <tr>
                                <th class="text-muted">Thesis Title:</th>
                                <td><em><?= htmlspecialchars($cleanThesis, ENT_QUOTES, 'UTF-8') ?></em></td>
                            </tr>
                        <?php endif; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>
