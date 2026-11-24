<?php
/**
 * Lucid University Course Registration System
 * Statistical Department - University Demographics Dashboard
 *
 * Read-only statistics dashboard. All chart values come from aggregate helper queries.
 */

require_once __DIR__ . '/stat_department_guard.php';
require_once __DIR__ . '/../includes/stat_department_helpers.php';
require_once __DIR__ . '/../includes/ui_helpers.php';

$stat_id = (int)$userID;
$profile = getStatDepartmentProfile($conn, $stat_id);
if (!$profile) {
    http_response_code(403);
    exit('Statistical Department account not found.');
}

$stats = getStatDepartmentDashboardData($conn);
$summary = $stats['summary'];

// Chart configuration is for display only; the data is fetched in stat_department_helpers.php.
$charts = [
    ['key' => 'gender', 'title' => 'Student Gender', 'description' => 'Distribution of student gender values on file.', 'type' => 'doughnut'],
    ['key' => 'student_levels', 'title' => 'Student Academic Level', 'description' => 'Undergraduate and graduate student headcount.', 'type' => 'pie'],
    ['key' => 'age_groups', 'title' => 'Student Age Groups', 'description' => 'Age calculated from date of birth as of today.', 'type' => 'bar'],
    ['key' => 'class_standing', 'title' => 'Class Standing', 'description' => 'Students grouped by academic year and graduate status.', 'type' => 'bar'],
    ['key' => 'departments', 'title' => 'Students by Department', 'description' => 'Top 12 departments by assigned student headcount.', 'type' => 'horizontalBar'],
    ['key' => 'attendance_types', 'title' => 'Student Enrollment Status', 'description' => 'Full-time versus part-time students.', 'type' => 'doughnut'],
    ['key' => 'faculty_ranks', 'title' => 'Faculty by Rank', 'description' => 'Faculty headcount grouped by rank.', 'type' => 'bar'],
    ['key' => 'popular_majors', 'title' => 'Most Declared Majors', 'description' => 'Top 10 majors by declarations (double majors count in each major).', 'type' => 'horizontalBar'],
    ['key' => 'enrollment_trend', 'title' => 'Enrolled Students by Semester', 'description' => 'Unique registered students per term, including historical enrollment.', 'type' => 'line'],
];

// Encode untrusted database labels safely before placing them in a JavaScript expression.
$chartValues = [];
foreach ($charts as $chart) {
    $chartValues[$chart['key']] = $stats[$chart['key']];
}
$chartJson = json_encode(
    $chartValues,
    JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_INVALID_UTF8_SUBSTITUTE
);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Statistical Department Dashboard | Lucid University</title>
    <!-- Bootstrap 5.3 and Chart.js; charts use aggregate live database results. -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .metric-card { border-left: 4px solid #0d6efd !important; }
        .chart-container { position: relative; height: 295px; width: 100%; }
        .chart-container.tall { height: 350px; }
        .chart-card { min-width: 0; }
        .chart-card .card-header { background: #fff; }
        .report-table { max-height: 220px; overflow: auto; }
        @media (max-width: 575.98px) { .chart-container { height: 260px; } }
    </style>
</head>
<body class="bg-light">

    <!-- Unified Statistical Department Top Navigation -->
    <?= renderStatDepartmentNavbar($profile) ?>

    <main class="container-fluid px-lg-5 mb-5">
        <!-- Welcome and Personal Information -->
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-body p-4 bg-white rounded-3">
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
                    <div>
                        <h1 class="h3 fw-bold mb-1">Statistical Department Dashboard</h1>
                        <p class="text-secondary mb-1">
                            Welcome, <strong><?= htmlspecialchars($profile['full_name'], ENT_QUOTES, 'UTF-8') ?></strong>
                        </p>
                        <div class="text-muted small">
                            Staff ID: <strong><?= (int)$stat_id ?></strong>
                            &bull; Email: <?= htmlspecialchars($profile['User_Email'] ?: 'Not recorded', ENT_QUOTES, 'UTF-8') ?>
                            &bull; Phone: <?= htmlspecialchars($profile['User_PhoneNo'] ?: 'Not recorded', ENT_QUOTES, 'UTF-8') ?>
                        </div>
                    </div>
                    <div class="d-flex flex-wrap gap-2">
                        <span class="badge text-bg-info fs-6 py-2 px-3">Statistical Department</span>
                        <span class="badge text-bg-light border fs-6 py-2 px-3">Read-only Access</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- University Summary Statistics -->
        <div class="d-flex flex-wrap justify-content-between align-items-end gap-2 mb-3">
            <div>
                <h2 class="h4 fw-bold mb-1">University Overview</h2>
                <p class="text-secondary small mb-0">School-wide counts from the current Lucid University database.</p>
            </div>
            <span class="text-secondary small">Report date: <?= htmlspecialchars(date('F j, Y'), ENT_QUOTES, 'UTF-8') ?></span>
        </div>

        <div class="row g-3 mb-4">
            <?php
            $metrics = [
                ['label' => 'Total Students', 'value' => $summary['students']],
                ['label' => 'Total Faculty', 'value' => $summary['faculty']],
                ['label' => 'Departments', 'value' => $summary['departments']],
                ['label' => 'Major Programs', 'value' => $summary['majors']],
            ];
            foreach ($metrics as $metric):
            ?>
                <div class="col-sm-6 col-xl-3">
                    <div class="card metric-card shadow-sm border-0 h-100">
                        <div class="card-body py-3 px-4">
                            <div class="text-secondary small text-uppercase fw-semibold mb-1">
                                <?= htmlspecialchars($metric['label'], ENT_QUOTES, 'UTF-8') ?>
                            </div>
                            <div class="h2 fw-bold text-dark mb-0">
                                <?= number_format($metric['value']) ?>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Demographic Charts -->
        <h2 class="h4 fw-bold mb-1">Demographics &amp; Academic Statistics</h2>
        <p class="text-secondary small mb-3">
            Charts contain aggregate information only. Select "View underlying counts" to see exact values.
        </p>
        <div class="row g-3">
            <?php foreach ($charts as $chart): ?>
                <?php $chartRows = $stats[$chart['key']]; ?>
                <div class="col-12 col-xl-6">
                    <section class="card chart-card shadow-sm border-0 h-100" aria-labelledby="title-<?= htmlspecialchars($chart['key'], ENT_QUOTES, 'UTF-8') ?>">
                        <div class="card-header px-4 py-3 border-bottom">
                            <h3 id="title-<?= htmlspecialchars($chart['key'], ENT_QUOTES, 'UTF-8') ?>" class="h5 fw-bold mb-1">
                                <?= htmlspecialchars($chart['title'], ENT_QUOTES, 'UTF-8') ?>
                            </h3>
                            <p class="text-secondary small mb-0">
                                <?= htmlspecialchars($chart['description'], ENT_QUOTES, 'UTF-8') ?>
                            </p>
                        </div>
                        <div class="card-body p-4">
                            <?php if (empty($chartRows) || array_sum(array_column($chartRows, 'count')) === 0): ?>
                                <div class="text-center text-secondary py-5">No data available for this chart yet.</div>
                            <?php else: ?>
                                <div class="chart-container<?= $chart['type'] === 'horizontalBar' ? ' tall' : '' ?>">
                                    <canvas id="chart-<?= htmlspecialchars($chart['key'], ENT_QUOTES, 'UTF-8') ?>"
                                            role="img"
                                            aria-label="<?= htmlspecialchars($chart['title'] . ' statistical chart', ENT_QUOTES, 'UTF-8') ?>"></canvas>
                                </div>
                                <div id="chart-error-<?= htmlspecialchars($chart['key'], ENT_QUOTES, 'UTF-8') ?>" class="text-danger small d-none">
                                    The chart could not load. Use the table below to view the data.
                                </div>
                                <details class="mt-3">
                                    <summary class="text-primary small fw-semibold" style="cursor:pointer">View underlying counts</summary>
                                    <div class="report-table mt-2">
                                        <table class="table table-sm table-striped mb-0">
                                            <thead><tr><th scope="col">Category</th><th scope="col" class="text-end">Count</th></tr></thead>
                                            <tbody>
                                            <?php foreach ($chartRows as $row): ?>
                                                <tr>
                                                    <td><?= htmlspecialchars($row['label'], ENT_QUOTES, 'UTF-8') ?></td>
                                                    <td class="text-end"><?= number_format($row['count']) ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </details>
                            <?php endif; ?>
                        </div>
                    </section>
                </div>
            <?php endforeach; ?>
        </div>
        <p class="text-secondary small mt-4 mb-0">
            Department and major charts show the largest categories; other charts show all recorded categories.
            Gender and age reports include students with missing values as "Not specified" or "Not recorded".
            No student-level records or editing tools are available on this dashboard.
        </p>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js"></script>
    <script>
    // Render the aggregate chart data using Chart.js; the tables above remain available.
    const dashboardChartData = <?= $chartJson ?: '{}' ?>;
    const dashboardChartConfigs = <?= json_encode(array_map(
        fn(array $c): array => ['key' => $c['key'], 'type' => $c['type']],
        $charts
    ), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    const palette = ['#0d6efd', '#20c997', '#6f42c1', '#fd7e14', '#dc3545', '#0dcaf0', '#6c757d', '#198754', '#ffc107', '#6610f2', '#adb5bd', '#343a40'];

    dashboardChartConfigs.forEach((config) => {
        const canvas = document.getElementById('chart-' + config.key);
        if (!canvas) return;
        const rows = dashboardChartData[config.key] || [];
        if (!rows.length) return;
        const error = document.getElementById('chart-error-' + config.key);
        if (typeof Chart === 'undefined') {
            error?.classList.remove('d-none');
            return;
        }
        const isHorizontal = config.type === 'horizontalBar';
        const isCircle = ['pie', 'doughnut'].includes(config.type);
        const isLine = config.type === 'line';
        const chartType = isHorizontal ? 'bar' : config.type;
        const chartColors = rows.map((_, i) => palette[i % palette.length]);
        const dataset = {
            label: 'Headcount',
            data: rows.map((row) => row.count),
            backgroundColor: isLine ? 'rgba(13, 110, 253, 0.12)' : (isCircle ? chartColors : '#0d6efd'),
            borderColor: isLine ? '#0d6efd' : (isCircle ? '#fff' : '#0d6efd'),
            borderWidth: isCircle ? 2 : 1,
            borderRadius: isCircle || isLine ? 0 : 4,
            tension: isLine ? 0.3 : 0,
            fill: isLine,
            pointRadius: isLine ? 4 : 0,
        };
        const options = {
            responsive: true,
            maintainAspectRatio: false,
            indexAxis: isHorizontal ? 'y' : 'x',
            plugins: {
                legend: { display: isCircle, position: 'bottom', labels: { boxWidth: 12, padding: 14 } },
                tooltip: { callbacks: { label: (context) => {
                    const value = isCircle ? context.parsed : (isHorizontal ? context.parsed.x : context.parsed.y);
                    return context.label + ': ' + (typeof value === 'number' ? value.toLocaleString() : String(value));
                } } }
            },
            scales: isCircle ? {} : {
                x: {
                    beginAtZero: true,
                    ...(isHorizontal ? { ticks: { precision: 0 } } : { ticks: { maxRotation: 40, minRotation: 0 } }),
                    grid: { display: isHorizontal },
                },
                y: { beginAtZero: !isHorizontal, ticks: { precision: isHorizontal ? undefined : 0 } },
            },
        };
        // Keep numerical scales on the appropriate axis for horizontal bars.
        if (isHorizontal) {
            options.scales.x.ticks = { precision: 0 };
            options.scales.y.beginAtZero = false;
        }
        try {
            new Chart(canvas, {
                type: chartType,
                data: { labels: rows.map((row) => row.label), datasets: [dataset] },
                options,
            });
        } catch (e) {
            error?.classList.remove('d-none');
        }
    });
    </script>
</body>
</html>
