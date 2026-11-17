<?php
/**
 * Reusable Bootstrap modal partial for one major or minor.
 * Set these variables before including:
 *   $modalId, $program, $programType, $requiredCourses, $departmentCourses
 *
 * NOTE: Courses outside the program's requirement list are only potential
 * electives. Our schema has no program-specific elective designations.
 */
$requiredCourseIds = array_fill_keys(array_column($requiredCourses, 'Course_ID'), true);
$potentialElectives = array_values(array_filter(
    $departmentCourses,
    static fn(array $course): bool => !isset($requiredCourseIds[$course['Course_ID']])
));
?>
<div class="modal fade" id="<?= escapeHtml($modalId) ?>" tabindex="-1"
     aria-labelledby="<?= escapeHtml($modalId) ?>-title" aria-hidden="true">
    <!-- No static backdrop: Bootstrap closes this popup on an outside click. -->
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <span class="badge text-bg-secondary mb-2"><?= escapeHtml($programType) ?></span>
                    <h2 class="modal-title h4 mb-1" id="<?= escapeHtml($modalId) ?>-title">
                        <?= escapeHtml($program['Program_Name']) ?>
                    </h2>
                    <p class="small text-secondary mb-0">
                        <?= (int) $program['No_Credits_Required'] ?> total program credits required
                    </p>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body p-4">
                <section aria-labelledby="<?= escapeHtml($modalId) ?>-required-title" class="mb-4">
                    <div class="d-flex align-items-center justify-content-between gap-2 mb-3">
                        <h3 class="h5 mb-0" id="<?= escapeHtml($modalId) ?>-required-title">Required Courses</h3>
                        <span class="badge text-bg-primary"><?= count($requiredCourses) ?></span>
                    </div>
                    <?php if (empty($requiredCourses)): ?>
                        <p class="text-secondary mb-0">No required courses have been assigned to this program in the database.</p>
                    <?php else: ?>
                        <ul class="list-group">
                            <?php foreach ($requiredCourses as $course): ?>
                                <li class="list-group-item py-3">
                                    <div class="d-flex flex-wrap justify-content-between gap-2">
                                        <div>
                                            <div class="fw-semibold">
                                                <span class="text-secondary"><?= escapeHtml($course['Course_ID']) ?></span>
                                                &mdash; <?= escapeHtml($course['Course_Name']) ?>
                                            </div>
                                            <div class="small text-secondary">
                                                <?= escapeHtml($course['Course_Department_Name']) ?>
                                                &middot; <?= escapeHtml($course['Course_Type']) ?>
                                            </div>
                                        </div>
                                        <div class="text-end small">
                                            <span class="badge text-bg-light border">
                                                <?= (int) $course['Course_Credits'] ?> credits
                                            </span>
                                            <div class="text-secondary mt-1">
                                                Minimum grade: <?= escapeHtml($course['Min_Grade']) ?>
                                            </div>
                                        </div>
                                    </div>
                                    <?php if (!empty($course['Course_Description'])): ?>
                                        <p class="small text-secondary mt-2 mb-0"><?= escapeHtml($course['Course_Description']) ?></p>
                                    <?php endif; ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </section>

                <section aria-labelledby="<?= escapeHtml($modalId) ?>-electives-title">
                    <div class="d-flex align-items-center justify-content-between gap-2 mb-2">
                        <h3 class="h5 mb-0" id="<?= escapeHtml($modalId) ?>-electives-title">
                            Potential Electives
                        </h3>
                        <span class="badge text-bg-secondary"><?= count($potentialElectives) ?></span>
                    </div>
                    <p class="small text-secondary">
                        Other courses in this department that aren't marked as required for this program.
                        Your database does not confirm whether these count toward elective requirements.
                    </p>
                    <?php if (empty($potentialElectives)): ?>
                        <p class="text-secondary mb-0">No additional department courses are listed.</p>
                    <?php else: ?>
                        <ul class="list-group">
                            <?php foreach ($potentialElectives as $course): ?>
                                <li class="list-group-item py-3">
                                    <div class="d-flex flex-wrap justify-content-between gap-2">
                                        <div>
                                            <div class="fw-semibold">
                                                <span class="text-secondary"><?= escapeHtml($course['Course_ID']) ?></span>
                                                &mdash; <?= escapeHtml($course['Course_Name']) ?>
                                            </div>
                                            <div class="small text-secondary"><?= escapeHtml($course['Course_Type']) ?></div>
                                        </div>
                                        <span class="badge text-bg-light border align-self-start">
                                            <?= (int) $course['Course_Credits'] ?> credits
                                        </span>
                                    </div>
                                    <?php if (!empty($course['Course_Description'])): ?>
                                        <p class="small text-secondary mt-2 mb-0"><?= escapeHtml($course['Course_Description']) ?></p>
                                    <?php endif; ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </section>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>
