<?php
/**
 * Shared Partial: Student Academic Transcript Body
 * Expects: array $transcriptData (from getStudentTranscriptData())
 */
$terms = $transcriptData['terms'] ?? [];
$cumGpa = (float)($transcriptData['cum_gpa'] ?? 0.0);
$totalCredits = (int)($transcriptData['total_credits'] ?? 0);
?>
<div class="row g-3 mb-4">
    <div class="col-md-6">
        <div class="p-3 bg-light rounded border text-center h-100">
            <div class="text-muted small text-uppercase fw-semibold">Cumulative GPA</div>
            <div class="display-6 fw-bold text-primary"><?= number_format($cumGpa, 2) ?></div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="p-3 bg-light rounded border text-center h-100">
            <div class="text-muted small text-uppercase fw-semibold">Total Credits Earned</div>
            <div class="display-6 fw-bold text-success"><?= $totalCredits ?></div>
        </div>
    </div>
</div>

<h6 class="fw-bold mb-3">Coursework by Semester</h6>

<?php if (empty($terms)): ?>
    <div class="p-4 text-center text-muted border rounded bg-white">
        No historical transcript records on file.
    </div>
<?php else: ?>
    <?php foreach ($terms as $sem): ?>
        <div class="card border mb-3">
            <div class="card-header bg-light py-2 d-flex flex-wrap justify-content-between align-items-center gap-2">
                <strong><?= htmlspecialchars($sem['semester_label'] ?? '', ENT_QUOTES, 'UTF-8') ?></strong>
                <span class="small text-muted">
                    Term GPA: <strong><?= number_format((float)($sem['term_gpa'] ?? 0), 2) ?></strong>
                    &bull; Term Credits Earned: <strong><?= (int)($sem['term_credits_earned'] ?? 0) ?></strong>
                </span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm table-hover mb-0 align-middle small">
                        <thead class="table-light">
                            <tr>
                                <th>Course ID</th>
                                <th>Course Name</th>
                                <th class="text-center">Credits Attempted</th>
                                <th class="text-center">Credits Earned</th>
                                <th class="text-center">Final Grade</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($sem['courses'] as $sc): ?>
                                <tr>
                                    <td><span class="badge text-bg-light border font-monospace"><?= htmlspecialchars($sc['Course_ID'] ?? '', ENT_QUOTES, 'UTF-8') ?></span></td>
                                    <td><?= htmlspecialchars($sc['Course_Name'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                                    <td class="text-center"><?= (int)($sc['credits_attempted'] ?? 0) ?></td>
                                    <td class="text-center"><?= (int)($sc['credits_earned'] ?? 0) ?></td>
                                    <td class="text-center">
                                        <span class="badge bg-success"><?= htmlspecialchars($sc['Grade'] ?? '', ENT_QUOTES, 'UTF-8') ?></span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
<?php endif; ?>
