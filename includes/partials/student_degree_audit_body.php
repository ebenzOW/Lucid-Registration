<?php
/**
 * Shared Partial: Student Degree Audit Body
 * Expects: array $auditData (from getDegreeAuditData())
 */
$degreeTarget = (int)($auditData['degree_credit_target'] ?? 120);
$totalEarned = (int)($auditData['total_earned_credits'] ?? 0);
$totalInProgress = (int)($auditData['total_in_progress_credits'] ?? 0);
$progressEarnedPct = $degreeTarget > 0 ? min(100, (int)round(($totalEarned / $degreeTarget) * 100)) : 0;
$progressIPPct = $degreeTarget > 0 ? min(100 - $progressEarnedPct, (int)round(($totalInProgress / $degreeTarget) * 100)) : 0;
$studentLevelLabel = $auditData['student_type'] ?? 'Undergraduate';

// Build unified list of Major and Minor requirement sections to render with one table template
$programSections = [];
if (!empty($auditData['majors'])) {
    foreach ($auditData['majors'] as $idx => $major) {
        $isPrimary = ($idx === 0);
        $programSections[] = [
            'title_prefix' => 'Major Requirements:',
            'name'         => $major['Major_Name'] ?? 'No Major Declared',
            'credits'      => (int)($major['No_Credits_Required'] ?? 0),
            'credits_unit' => 'Total Program Credits',
            'badge_class'  => $isPrimary
                ? 'bg-primary-subtle text-primary border border-primary-subtle'
                : 'bg-info-subtle text-info border border-info-subtle',
            'empty_msg'    => 'No curriculum requirements configured for this major.',
            'data'         => $major,
        ];
    }
} else {
    $programSections[] = [
        'title_prefix' => 'Major Requirements:',
        'name'         => 'No Major Declared',
        'credits'      => 0,
        'credits_unit' => 'Total Program Credits',
        'badge_class'  => 'bg-primary-subtle text-primary border border-primary-subtle',
        'empty_msg'    => 'No curriculum requirements configured or major not yet declared.',
        'data'         => ['requirements' => [], 'courses_by_department' => []],
    ];
}

if (!empty($auditData['minors'])) {
    foreach ($auditData['minors'] as $minor) {
        $programSections[] = [
            'title_prefix' => 'Minor Requirements:',
            'name'         => $minor['Minor_Name'] ?? 'Declared Minor',
            'credits'      => (int)($minor['No_Credits_Required'] ?? 0),
            'credits_unit' => 'Total Minor Credits',
            'badge_class'  => 'bg-warning-subtle text-warning-emphasis border border-warning-subtle',
            'empty_msg'    => 'No curriculum requirements configured for this minor.',
            'data'         => $minor,
        ];
    }
}
?>

<!-- Degree Progress Overview Card -->
<div class="card shadow-sm border-0 mb-4">
    <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">
        <h5 class="mb-0 fw-bold">Degree Audit &amp; Program Requirements Progress</h5>
        <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-3 py-2">
            Target: <?= $degreeTarget ?> Total Credits
        </span>
    </div>
    <div class="card-body p-4">
        <div class="row g-3 mb-4">
            <div class="col-md-3">
                <div class="p-3 bg-light rounded border text-center h-100">
                    <div class="text-muted small text-uppercase fw-semibold mb-1">Required Program Credits</div>
                    <div class="fs-4 fw-bold text-dark mb-0">
                        <?= (int)($auditData['unique_required_credits_completed'] ?? 0) ?>
                        <span class="fs-6 text-muted">/ <?= (int)($auditData['unique_required_credits_total'] ?? 0) ?> Cr</span>
                    </div>
                    <div class="small text-primary fw-medium">
                        +<?= (int)($auditData['unique_required_credits_in_progress'] ?? 0) ?> Cr In Progress
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="p-3 bg-light rounded border text-center h-100">
                    <div class="text-muted small text-uppercase fw-semibold mb-1">Total Remaining to Target</div>
                    <div class="fs-4 fw-bold text-danger mb-0">
                        <?= (int)($auditData['total_credits_remaining_to_target'] ?? 0) ?>
                        <span class="fs-6 text-muted">Cr Left</span>
                    </div>
                    <div class="small text-muted">
                        <?= (int)($auditData['total_credits_remaining_after_in_progress'] ?? 0) ?> Cr left after In-Progress
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="p-3 bg-light rounded border text-center h-100">
                    <div class="text-muted small text-uppercase fw-semibold mb-1">Completed Degree Credits</div>
                    <div class="fs-4 fw-bold text-success mb-0"><?= $totalEarned ?> Cr</div>
                    <div class="small text-muted">earned toward <?= $degreeTarget ?> credit degree</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="p-3 bg-light rounded border text-center h-100">
                    <div class="text-muted small text-uppercase fw-semibold mb-1">In-Progress Credits</div>
                    <div class="fs-4 fw-bold text-primary mb-0"><?= $totalInProgress ?> Cr</div>
                    <div class="small text-muted">current term enrollment</div>
                </div>
            </div>
        </div>

        <div class="mb-2 d-flex justify-content-between align-items-center flex-wrap gap-2">
            <span class="fw-bold small text-uppercase text-secondary">Overall Degree Completion</span>
            <span class="small fw-semibold text-muted">
                Earned: <?= $totalEarned ?> Cr &bull; In Progress: <?= $totalInProgress ?> Cr &bull; Target: <?= $degreeTarget ?> Cr
            </span>
        </div>
        <div class="progress" style="height: 24px;">
            <div class="progress-bar bg-success fw-bold" role="progressbar" style="width: <?= $progressEarnedPct ?>%;" aria-valuenow="<?= $progressEarnedPct ?>" aria-valuemin="0" aria-valuemax="100">
                <?php if ($progressEarnedPct >= 8): ?><?= $progressEarnedPct ?>% Earned<?php endif; ?>
            </div>
            <?php if ($progressIPPct > 0): ?>
                <div class="progress-bar bg-primary progress-bar-striped progress-bar-animated fw-bold" role="progressbar" style="width: <?= $progressIPPct ?>%;" aria-valuenow="<?= $progressIPPct ?>" aria-valuemin="0" aria-valuemax="100">
                    <?php if ($progressIPPct >= 8): ?>+<?= $progressIPPct ?>% In Progress<?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
        <div class="d-flex justify-content-between align-items-center mt-2 small text-muted flex-wrap gap-2">
            <div>
                <span class="badge bg-success me-1">&nbsp;</span> Completed Credits (<?= $totalEarned ?> Cr)
                <span class="badge bg-primary ms-3 me-1">&nbsp;</span> In-Progress Credits (<?= $totalInProgress ?> Cr)
            </div>
            <div>
                <strong><?= $progressEarnedPct + $progressIPPct ?>%</strong> Projected Completion
            </div>
        </div>
    </div>
</div>

<!-- Major & Minor Requirements Cards -->
<?php foreach ($programSections as $section):
    $program = $section['data'];
?>
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">
            <h5 class="card-title mb-0 fw-bold">
                <?= htmlspecialchars($section['title_prefix'], ENT_QUOTES, 'UTF-8') ?>
                <?= htmlspecialchars($section['name'], ENT_QUOTES, 'UTF-8') ?>
            </h5>
            <?php if ($section['credits'] > 0): ?>
                <span class="badge <?= htmlspecialchars($section['badge_class'], ENT_QUOTES, 'UTF-8') ?> px-3 py-2">
                    <?= $section['credits'] ?> <?= htmlspecialchars($section['credits_unit'], ENT_QUOTES, 'UTF-8') ?>
                </span>
            <?php endif; ?>
        </div>
        <div class="card-body p-0">
            <?php if (empty($program['requirements'])): ?>
                <div class="p-4 text-center text-muted">
                    <?= htmlspecialchars($section['empty_msg'], ENT_QUOTES, 'UTF-8') ?>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0 small">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 14%;">Course ID</th>
                                <th style="width: 26%;">Course Name</th>
                                <th style="width: 16%;">Department</th>
                                <th class="text-center" style="width: 8%;">Credits</th>
                                <th class="text-center" style="width: 8%;">Min Grade</th>
                                <th class="text-center" style="width: 14%;">Semester Taken / Enrolled</th>
                                <th class="text-center" style="width: 7%;">Grade</th>
                                <th class="text-center" style="width: 7%;">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($program['courses_by_department'] as $deptName => $deptReqs): ?>
                                <tr class="table-secondary">
                                    <td colspan="8" class="fw-bold py-2">
                                        <?= htmlspecialchars($deptName, ENT_QUOTES, 'UTF-8') ?>
                                        <span class="badge bg-secondary ms-2">
                                            <?= count($deptReqs) ?> <?= count($deptReqs) === 1 ? 'Course' : 'Courses' ?>
                                            (<?= (int)array_sum(array_column($deptReqs, 'Course_Credits')) ?> Credits)
                                        </span>
                                    </td>
                                </tr>
                                <?php foreach ($deptReqs as $req): ?>
                                    <tr>
                                        <td class="fw-semibold font-monospace"><?= htmlspecialchars($req['Course_ID'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><?= htmlspecialchars($req['Course_Name'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><small class="text-muted"><?= htmlspecialchars($req['Department_Name'] ?? 'General', ENT_QUOTES, 'UTF-8') ?></small></td>
                                        <td class="text-center"><?= (int)($req['Course_Credits'] ?? 0) ?></td>
                                        <td class="text-center fw-medium"><?= htmlspecialchars($req['Min_Grade'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                                        <td class="text-center">
                                            <?php if (!empty($req['Term_Taken'])): ?>
                                                <span class="badge <?= (strpos($req['Term_Taken'], 'In Progress') !== false) ? 'text-bg-primary' : 'text-bg-light border text-dark' ?>">
                                                    <?= htmlspecialchars($req['Term_Taken'], ENT_QUOTES, 'UTF-8') ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="text-muted">&mdash;</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center fw-semibold">
                                            <?php if (!empty($req['Grade_Earned'])): ?>
                                                <span class="<?= $req['Grade_Earned'] === 'In Progress' ? 'text-primary' : 'text-dark' ?>">
                                                    <?= htmlspecialchars($req['Grade_Earned'], ENT_QUOTES, 'UTF-8') ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="text-muted">&mdash;</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge <?= htmlspecialchars($req['Status_Badge_Class'] ?? 'text-bg-secondary', ENT_QUOTES, 'UTF-8') ?>">
                                                <?= htmlspecialchars($req['Status'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
<?php endforeach; ?>

<!-- Dedicated Elective Courses & Remaining Elective Credits Card -->
<div class="card shadow-sm border-0 mb-4">
    <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">
        <h5 class="card-title mb-0 fw-bold">
            Elective Courses &amp; Elective Credits Progress
        </h5>
        <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-3 py-2">
            Target: <?= $degreeTarget ?> Total Degree Credits
        </span>
    </div>
    <div class="card-body p-4">
        <div class="row g-3 mb-4">
            <div class="col-sm-6 col-lg-3">
                <div class="p-3 bg-light rounded border text-center h-100">
                    <div class="text-muted small text-uppercase fw-semibold mb-1">Required Electives</div>
                    <div class="fs-2 fw-bold text-dark"><?= (int)($auditData['elective_credits_needed_total'] ?? 0) ?></div>
                    <div class="small text-muted">credits toward degree</div>
                </div>
            </div>
            <div class="col-sm-6 col-lg-3">
                <div class="p-3 bg-light rounded border text-center h-100">
                    <div class="text-muted small text-uppercase fw-semibold mb-1">Completed Electives</div>
                    <div class="fs-2 fw-bold text-success"><?= (int)($auditData['elective_credits_completed'] ?? 0) ?></div>
                    <div class="small text-muted">credits earned</div>
                </div>
            </div>
            <div class="col-sm-6 col-lg-3">
                <div class="p-3 bg-light rounded border text-center h-100">
                    <div class="text-muted small text-uppercase fw-semibold mb-1">In-Progress Electives</div>
                    <div class="fs-2 fw-bold text-primary"><?= (int)($auditData['elective_credits_in_progress'] ?? 0) ?></div>
                    <div class="small text-muted">credits enrolled</div>
                </div>
            </div>
            <div class="col-sm-6 col-lg-3">
                <div class="p-3 bg-light rounded border text-center h-100">
                    <div class="text-muted small text-uppercase fw-semibold mb-1">Remaining Electives</div>
                    <div class="fs-2 fw-bold text-danger"><?= (int)($auditData['elective_credits_remaining_completed_only'] ?? 0) ?></div>
                    <div class="small text-muted"><?= (int)($auditData['elective_credits_remaining_after_in_progress'] ?? 0) ?> after in-progress</div>
                </div>
            </div>
        </div>

        <h6 class="fw-bold mb-3">Completed &amp; In-Progress Elective Courses</h6>
        <?php if (empty($auditData['elective_courses'])): ?>
            <div class="alert alert-light border text-muted d-flex align-items-center mb-0 p-3" role="alert">
                <div>
                    No general elective courses have been completed or registered yet. Non-major courses at the student's academic level (<strong><?= htmlspecialchars($studentLevelLabel, ENT_QUOTES, 'UTF-8') ?></strong>) will automatically count toward elective degree requirements.
                </div>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 small">
                    <thead class="table-light">
                        <tr>
                            <th style="width: 14%;">Course ID</th>
                            <th style="width: 30%;">Course Name</th>
                            <th style="width: 20%;">Department</th>
                            <th class="text-center" style="width: 8%;">Credits</th>
                            <th class="text-center" style="width: 14%;">Semester Taken / Enrolled</th>
                            <th class="text-center" style="width: 7%;">Grade</th>
                            <th class="text-center" style="width: 7%;">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($auditData['elective_courses'] as $el): ?>
                            <tr>
                                <td class="fw-semibold font-monospace"><?= htmlspecialchars($el['Course_ID'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars($el['Course_Name'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                                <td><small class="text-muted"><?= htmlspecialchars($el['Department_Name'] ?? 'General', ENT_QUOTES, 'UTF-8') ?></small></td>
                                <td class="text-center"><?= (int)($el['Course_Credits'] ?? 0) ?></td>
                                <td class="text-center">
                                    <?php if (!empty($el['Term_Taken'])): ?>
                                        <span class="badge <?= (strpos($el['Term_Taken'], 'In Progress') !== false) ? 'text-bg-primary' : 'text-bg-light border text-dark' ?>">
                                            <?= htmlspecialchars($el['Term_Taken'], ENT_QUOTES, 'UTF-8') ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-muted">&mdash;</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center fw-semibold">
                                    <?php if (!empty($el['Grade'])): ?>
                                        <span class="<?= $el['Grade'] === 'In Progress' ? 'text-primary' : 'text-dark' ?>">
                                            <?= htmlspecialchars($el['Grade'], ENT_QUOTES, 'UTF-8') ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-muted">&mdash;</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <span class="badge <?= htmlspecialchars($el['Status_Badge_Class'] ?? 'text-bg-secondary', ENT_QUOTES, 'UTF-8') ?>">
                                        <?= htmlspecialchars($el['Status'] ?? '', ENT_QUOTES, 'UTF-8') ?>
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
