<?php
/**
 * Shared Partial: Non-Student Role-Specific Profile Card (Faculty, Admin, Stat-Dept)
 * Expects:
 *   string $target_type
 *   ?array $facultyData
 *   ?array $adminData
 *   ?array $statData
 */
$cardTitle = match ($target_type) {
    'Faculty'   => 'Faculty Appointment & Workload',
    'Admin'     => 'System Administration Scope',
    'Stat-Dept' => 'Department Details',
    default     => 'Academic Details & Standing',
};
?>
<div class="col-lg-6">
    <div class="card shadow-sm border-0 h-100">
        <div class="card-header bg-white py-3">
            <h5 class="mb-0 fw-bold"><?= htmlspecialchars($cardTitle, ENT_QUOTES, 'UTF-8') ?></h5>
        </div>
        <div class="card-body p-4">
            <?php if ($target_type === 'Faculty' && $facultyData): ?>
                <table class="table table-borderless table-sm mb-0">
                    <tbody>
                        <tr>
                            <th class="text-muted w-35">Academic Rank:</th>
                            <td><strong><?= htmlspecialchars($facultyData['Rank'] ?? 'Instructor', ENT_QUOTES, 'UTF-8') ?></strong></td>
                        </tr>
                        <tr>
                            <th class="text-muted">Appointment Status:</th>
                            <td><?= htmlspecialchars($facultyData['Faculty_Type'] ?? 'Full-Time', ENT_QUOTES, 'UTF-8') ?></td>
                        </tr>
                        <tr>
                            <th class="text-muted">Specialty:</th>
                            <td><?= htmlspecialchars($facultyData['Specialty'] ?? 'General', ENT_QUOTES, 'UTF-8') ?></td>
                        </tr>
                        <tr>
                            <th class="text-muted">Office Location:</th>
                            <td>
                                <?php if (!empty($facultyData['Office_ID'])): ?>
                                    <strong><?= htmlspecialchars($facultyData['Office_ID'], ENT_QUOTES, 'UTF-8') ?></strong>
                                    <?php if (!empty($facultyData['Building_Name'])): ?>
                                        (<?= htmlspecialchars($facultyData['Building_Name'] . ' Room ' . $facultyData['Room_NO'], ENT_QUOTES, 'UTF-8') ?>)
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="text-muted">Office TBA</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <th class="text-muted align-top">Department Roster:</th>
                            <td>
                                <?php if (!empty($facultyData['departments'])): ?>
                                    <ul class="list-unstyled mb-0">
                                        <?php foreach ($facultyData['departments'] as $d): ?>
                                            <li>
                                                <strong><?= htmlspecialchars($d['Department_Name'], ENT_QUOTES, 'UTF-8') ?></strong>
                                                <span class="badge bg-light text-dark border"><?= (float)$d['Percent_Time'] ?>% Time</span>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php else: ?>
                                    <span class="text-muted">Unassigned</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php if (!empty($facultyData['leadership'])): ?>
                            <tr>
                                <th class="text-muted align-top">Leadership Roles:</th>
                                <td>
                                    <ul class="list-unstyled mb-0">
                                        <?php foreach ($facultyData['leadership'] as $l): ?>
                                            <li>
                                                <span class="badge bg-warning text-dark"><?= htmlspecialchars($l['Role_Title'], ENT_QUOTES, 'UTF-8') ?></span>
                                                &ndash; <?= htmlspecialchars($l['Department_Name'], ENT_QUOTES, 'UTF-8') ?>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                </td>
                            </tr>
                        <?php endif; ?>
                        <tr>
                            <th class="text-muted">Teaching Load:</th>
                            <td>
                                Fall 2026: <strong><?= (int)$facultyData['fall_load'] ?></strong> sections &bull;
                                Spring 2027: <strong><?= (int)$facultyData['spring_load'] ?></strong> sections
                            </td>
                        </tr>
                        <tr>
                            <th class="text-muted">Assigned Advisees:</th>
                            <td><strong><?= (int)$facultyData['advisee_count'] ?></strong> / 15 Advisees</td>
                        </tr>
                    </tbody>
                </table>
            <?php elseif ($target_type === 'Admin' && $adminData): ?>
                <table class="table table-borderless table-sm mb-0">
                    <tbody>
                        <tr>
                            <th class="text-muted w-35">Security Level:</th>
                            <td><span class="badge bg-danger fs-6">Level <?= (int)$adminData['Security_Level'] ?></span></td>
                        </tr>
                        <tr>
                            <th class="text-muted">System Permissions:</th>
                            <td>
                                <?php if ((int)$adminData['Security_Level'] >= 2): ?>
                                    <span class="text-success fw-bold">Read &amp; Write Access (Full Administration)</span>
                                <?php else: ?>
                                    <span class="text-secondary fw-semibold">Read-Only Access (Auditor)</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <th class="text-muted">Scope of Control:</th>
                            <td>User account creation, course section scheduling, semester calendar auditing.</td>
                        </tr>
                    </tbody>
                </table>
            <?php elseif ($target_type === 'Stat-Dept' && $statData): ?>
                <table class="table table-borderless table-sm mb-0">
                    <tbody>
                        <tr>
                            <th class="text-muted w-35">Division:</th>
                            <td><?= htmlspecialchars($statData['title'], ENT_QUOTES, 'UTF-8') ?></td>
                        </tr>
                        <tr>
                            <th class="text-muted">Employment:</th>
                            <td><span class="badge bg-info text-dark"><?= htmlspecialchars($statData['status'], ENT_QUOTES, 'UTF-8') ?></span></td>
                        </tr>
                        <tr>
                            <th class="text-muted">Clearance:</th>
                            <td><?= htmlspecialchars($statData['clearance'], ENT_QUOTES, 'UTF-8') ?></td>
                        </tr>
                        <tr>
                            <th class="text-muted">Scope:</th>
                            <td>Institutional enrollment reporting, section utilization statistics, grade distribution analysis.</td>
                        </tr>
                    </tbody>
                </table>
            <?php else: ?>
                <p class="text-muted mb-0 small">No additional role-specific details on file.</p>
            <?php endif; ?>
        </div>
    </div>
</div>
