<?php
/**
 * Lucid University Course Registration System
 * Admin Portal - Profile Change Requests & USER_UPDATE_LOG Audit Hub
 *
 * Implements SRS Use Cases & Constraints:
 * 1. View student profile change requests with side-by-side diff of Current vs. Requested fields.
 * 2. Permit (Approve) or Reject change requests (Security Level >= 2 required).
 * 3. Automatically updates `USER` and records audit rows in `USER_UPDATE_LOG` upon approval.
 * 4. Displays the complete `USER_UPDATE_LOG` audit trail.
 */

require_once 'admin_guard.php';
require_once '../db_connect.php';
require_once __DIR__ . '/../includes/ui_helpers.php';

$admin_id = (int)$userID;
$canWrite = ((int)($_SESSION['Security_Level'] ?? 1) >= 2);

// Ensure PROFILE_CHANGE_REQUESTS table exists
$conn->query("
    CREATE TABLE IF NOT EXISTS PROFILE_CHANGE_REQUESTS (
        Request_ID INT NOT NULL AUTO_INCREMENT,
        User_ID INT NOT NULL,
        Req_FName VARCHAR(50) NOT NULL,
        Req_MName VARCHAR(50),
        Req_LName VARCHAR(50) NOT NULL,
        Req_Gender VARCHAR(15),
        Req_DOB DATE,
        Req_PhoneNo VARCHAR(15),
        Req_Street_No VARCHAR(10),
        Req_Street_Name VARCHAR(50),
        Req_City VARCHAR(50),
        Req_State VARCHAR(20),
        Req_Country VARCHAR(50),
        Req_Zip_Code VARCHAR(15),
        Request_Reason VARCHAR(255),
        Request_Status VARCHAR(20) NOT NULL DEFAULT 'Pending',
        Requested_At DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        Reviewed_By INT,
        Reviewed_At DATETIME,
        Admin_Notes VARCHAR(255),
        PRIMARY KEY (Request_ID),
        FOREIGN KEY (User_ID) REFERENCES `USER`(User_ID) ON DELETE CASCADE,
        FOREIGN KEY (Reviewed_By) REFERENCES ADMIN(Admin_ID) ON DELETE SET NULL
    ) ENGINE=InnoDB
");

$successMessage = $_SESSION['success_message'] ?? '';
$errorMessage   = $_SESSION['error_message'] ?? '';
unset($_SESSION['success_message'], $_SESSION['error_message']);

$activeTab = $_GET['tab'] ?? 'requests';
if (!in_array($activeTab, ['requests', 'audit_log'], true)) {
    $activeTab = 'requests';
}

$statusFilter = $_GET['status'] ?? 'All';
if (!in_array($statusFilter, ['All', 'Pending', 'Approved', 'Rejected'], true)) {
    $statusFilter = 'All';
}

$searchUser = isset($_GET['search_user']) ? trim($_GET['search_user']) : '';

// -----------------------------------------------------------------------------
// 1. Fetch All Profile Change Requests Joined with Current USER & Reviewer Info
// -----------------------------------------------------------------------------
$requestsSql = "
    SELECT 
        r.*,
        u.User_Type,
        u.User_FName AS Cur_FName,
        u.User_MName AS Cur_MName,
        u.User_LName AS Cur_LName,
        u.User_Gender AS Cur_Gender,
        u.User_DOB AS Cur_DOB,
        u.User_PhoneNo AS Cur_PhoneNo,
        u.Street_No AS Cur_Street_No,
        u.Street_Name AS Cur_Street_Name,
        u.City AS Cur_City,
        u.State AS Cur_State,
        u.Country AS Cur_Country,
        u.Zip_Code AS Cur_Zip_Code,
        l.User_Email,
        rev_u.User_FName AS Reviewer_FName,
        rev_u.User_LName AS Reviewer_LName
    FROM PROFILE_CHANGE_REQUESTS r
    JOIN `USER` u ON r.User_ID = u.User_ID
    LEFT JOIN LOGIN l ON u.User_ID = l.User_ID
    LEFT JOIN `USER` rev_u ON r.Reviewed_By = rev_u.User_ID
    ORDER BY 
        CASE WHEN r.Request_Status = 'Pending' THEN 0 ELSE 1 END ASC,
        r.Requested_At DESC,
        r.Request_ID DESC
";

$reqRes = $conn->query($requestsSql);
$allRequests = [];
$pendingCount  = 0;
$approvedCount = 0;
$rejectedCount = 0;

if ($reqRes) {
    while ($row = $reqRes->fetch_assoc()) {
        if ($row['Request_Status'] === 'Pending') {
            $pendingCount++;
        } elseif ($row['Request_Status'] === 'Approved') {
            $approvedCount++;
        } elseif ($row['Request_Status'] === 'Rejected') {
            $rejectedCount++;
        }

        if ($statusFilter !== 'All' && $row['Request_Status'] !== $statusFilter) {
            continue;
        }
        if ($searchUser !== '') {
            $fullNameMatch = stripos($row['Cur_FName'] . ' ' . $row['Cur_LName'], $searchUser) !== false
                          || stripos($row['Req_FName'] . ' ' . $row['Req_LName'], $searchUser) !== false
                          || stripos((string)$row['User_ID'], $searchUser) !== false;
            if (!$fullNameMatch) {
                continue;
            }
        }

        $allRequests[] = $row;
    }
}

// -----------------------------------------------------------------------------
// 2. Fetch USER_UPDATE_LOG Audit Trail
// -----------------------------------------------------------------------------
$logsSql = "
    SELECT 
        lg.Log_ID,
        lg.Admin_ID,
        lg.User_ID,
        lg.Changed_Attribute,
        lg.Date_Of_Change,
        u_target.User_FName AS Target_FName,
        u_target.User_LName AS Target_LName,
        u_target.User_Type AS Target_Type,
        u_admin.User_FName AS Admin_FName,
        u_admin.User_LName AS Admin_LName
    FROM USER_UPDATE_LOG lg
    LEFT JOIN `USER` u_target ON lg.User_ID = u_target.User_ID
    LEFT JOIN `USER` u_admin ON lg.Admin_ID = u_admin.User_ID
    ORDER BY lg.Date_Of_Change DESC, lg.Log_ID DESC
";
$logsRes = $conn->query($logsSql);
$auditLogs = [];
if ($logsRes) {
    while ($lRow = $logsRes->fetch_assoc()) {
        if ($searchUser !== '') {
            $logMatch = stripos((string)$lRow['User_ID'], $searchUser) !== false
                     || stripos((string)$lRow['Admin_ID'], $searchUser) !== false
                     || stripos(($lRow['Target_FName'] ?? '') . ' ' . ($lRow['Target_LName'] ?? ''), $searchUser) !== false
                     || stripos($lRow['Changed_Attribute'] ?? '', $searchUser) !== false;
            if (!$logMatch) {
                continue;
            }
        }
        $auditLogs[] = $lRow;
    }
}

/**
 * Helper to compute field-level diffs between Current and Requested values
 */
function computeRequestDiffs(array $r): array {
    $fields = [
        'First Name'  => ['col' => 'User_FName',   'cur' => $r['Cur_FName'] ?? '',       'req' => $r['Req_FName'] ?? ''],
        'Middle Name' => ['col' => 'User_MName',   'cur' => $r['Cur_MName'] ?? '',       'req' => $r['Req_MName'] ?? ''],
        'Last Name'   => ['col' => 'User_LName',   'cur' => $r['Cur_LName'] ?? '',       'req' => $r['Req_LName'] ?? ''],
        'Gender'      => ['col' => 'User_Gender',  'cur' => $r['Cur_Gender'] ?? '',      'req' => $r['Req_Gender'] ?? ''],
        'Birth Date'  => ['col' => 'User_DOB',     'cur' => $r['Cur_DOB'] ?? '',         'req' => $r['Req_DOB'] ?? ''],
        'Phone No.'   => ['col' => 'User_PhoneNo', 'cur' => $r['Cur_PhoneNo'] ?? '',     'req' => $r['Req_PhoneNo'] ?? ''],
        'Street No.'  => ['col' => 'Street_No',    'cur' => $r['Cur_Street_No'] ?? '',   'req' => $r['Req_Street_No'] ?? ''],
        'Street Name' => ['col' => 'Street_Name',  'cur' => $r['Cur_Street_Name'] ?? '', 'req' => $r['Req_Street_Name'] ?? ''],
        'City'        => ['col' => 'City',         'cur' => $r['Cur_City'] ?? '',        'req' => $r['Req_City'] ?? ''],
        'State'       => ['col' => 'State',        'cur' => $r['Cur_State'] ?? '',       'req' => $r['Req_State'] ?? ''],
        'Country'     => ['col' => 'Country',      'cur' => $r['Cur_Country'] ?? '',     'req' => $r['Req_Country'] ?? ''],
        'Zip Code'    => ['col' => 'Zip_Code',     'cur' => $r['Cur_Zip_Code'] ?? '',    'req' => $r['Req_Zip_Code'] ?? ''],
    ];

    $diffs = [];
    foreach ($fields as $label => $meta) {
        $c = trim((string)$meta['cur']);
        $q = trim((string)$meta['req']);
        if ($c !== $q) {
            $diffs[$label] = [
                'column' => $meta['col'],
                'old'    => $c !== '' ? $c : '(Empty)',
                'new'    => $q !== '' ? $q : '(Empty)'
            ];
        }
    }
    return $diffs;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile Change Requests &amp; Audit Log | Lucid University Admin</title>
    <!-- Minimal Clean Bootstrap 5.3 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .diff-old {
            background-color: #f8d7da;
            color: #842029;
            text-decoration: line-through;
            padding: 1px 6px;
            border-radius: 4px;
        }
        .diff-new {
            background-color: #d1e7dd;
            color: #0f5132;
            font-weight: 600;
            padding: 1px 6px;
            border-radius: 4px;
        }
    </style>
</head>
<body class="bg-light">

    <!-- Unified Admin Top Navigation -->
    <?= renderAdminNavbar($conn, $admin_id, basename($_SERVER['PHP_SELF'])) ?>

    <div class="container-fluid px-lg-5 mb-5">

        <!-- Flash Messages -->
        <?php if ($successMessage !== ''): ?>
            <div class="alert alert-success alert-dismissible fade show shadow-sm" role="alert">
                <strong>Success!</strong> <?= htmlspecialchars($successMessage, ENT_QUOTES, 'UTF-8') ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <?php if ($errorMessage !== ''): ?>
            <div class="alert alert-danger alert-dismissible fade show shadow-sm" role="alert">
                <strong>Error:</strong> <?= htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8') ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <!-- Page Header & Summary KPI Cards -->
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-body p-4 bg-white rounded-3">
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
                    <div>
                        <h2 class="fw-bold mb-1">Profile Change Requests &amp; User Update Log</h2>
                        <p class="text-secondary mb-0">
                            Review student profile update requests, approve or reject modifications, and inspect the <code>USER_UPDATE_LOG</code> audit trail.
                        </p>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <?php if ($canWrite): ?>
                            <span class="badge bg-success-subtle text-success border border-success-subtle px-3 py-2 fs-6">
                                Security Level <?= (int)($_SESSION['Security_Level'] ?? 2) ?>: Write Access Enabled
                            </span>
                        <?php else: ?>
                            <span class="badge bg-warning-subtle text-warning border border-warning-subtle px-3 py-2 fs-6">
                                Security Level 1: Read-Only Mode
                            </span>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="row g-3 mt-3">
                    <div class="col-sm-6 col-lg-3">
                        <div class="p-3 bg-light rounded-3 border">
                            <div class="text-secondary small fw-semibold text-uppercase">Pending Review</div>
                            <div class="h3 fw-bold mb-0 text-warning"><?= $pendingCount ?></div>
                            <div class="small text-muted">Awaiting admin decision</div>
                        </div>
                    </div>
                    <div class="col-sm-6 col-lg-3">
                        <div class="p-3 bg-light rounded-3 border">
                            <div class="text-secondary small fw-semibold text-uppercase">Approved Requests</div>
                            <div class="h3 fw-bold mb-0 text-success"><?= $approvedCount ?></div>
                            <div class="small text-muted">Applied to USER table</div>
                        </div>
                    </div>
                    <div class="col-sm-6 col-lg-3">
                        <div class="p-3 bg-light rounded-3 border">
                            <div class="text-secondary small fw-semibold text-uppercase">Rejected Requests</div>
                            <div class="h3 fw-bold mb-0 text-danger"><?= $rejectedCount ?></div>
                            <div class="small text-muted">Declined by administrator</div>
                        </div>
                    </div>
                    <div class="col-sm-6 col-lg-3">
                        <div class="p-3 bg-light rounded-3 border">
                            <div class="text-secondary small fw-semibold text-uppercase">Audit Log Entries</div>
                            <div class="h3 fw-bold mb-0 text-primary"><?= count($auditLogs) ?></div>
                            <div class="small text-muted">Recorded in USER_UPDATE_LOG</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Navigation Tabs & Search Filter Bar -->
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
            <ul class="nav nav-pills" role="tablist">
                <li class="nav-item">
                    <a class="nav-link <?= $activeTab === 'requests' ? 'active fw-bold' : 'text-dark' ?>"
                       href="?tab=requests&status=<?= urlencode($statusFilter) ?>&search_user=<?= urlencode($searchUser) ?>">
                        Student Change Requests (<?= count($allRequests) ?>)
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $activeTab === 'audit_log' ? 'active fw-bold' : 'text-dark' ?>"
                       href="?tab=audit_log&search_user=<?= urlencode($searchUser) ?>">
                        USER_UPDATE_LOG Audit Trail (<?= count($auditLogs) ?>)
                    </a>
                </li>
            </ul>

            <form method="GET" action="manage_change_requests.php" class="d-flex flex-wrap gap-2 align-items-center">
                <input type="hidden" name="tab" value="<?= htmlspecialchars($activeTab, ENT_QUOTES, 'UTF-8') ?>">
                <?php if ($activeTab === 'requests'): ?>
                    <select name="status" class="form-select form-select-sm" style="width: auto;" onchange="this.form.submit()">
                        <option value="All" <?= $statusFilter === 'All' ? 'selected' : '' ?>>All Statuses</option>
                        <option value="Pending" <?= $statusFilter === 'Pending' ? 'selected' : '' ?>>Pending Only (<?= $pendingCount ?>)</option>
                        <option value="Approved" <?= $statusFilter === 'Approved' ? 'selected' : '' ?>>Approved Only (<?= $approvedCount ?>)</option>
                        <option value="Rejected" <?= $statusFilter === 'Rejected' ? 'selected' : '' ?>>Rejected Only (<?= $rejectedCount ?>)</option>
                    </select>
                <?php endif; ?>
                <input type="text" name="search_user" class="form-control form-control-sm" style="width: 240px;"
                       placeholder="Search Student ID or Name..." value="<?= htmlspecialchars($searchUser, ENT_QUOTES, 'UTF-8') ?>">
                <button type="submit" class="btn btn-primary btn-sm">Filter</button>
                <?php if ($searchUser !== '' || $statusFilter !== 'All'): ?>
                    <a href="manage_change_requests.php?tab=<?= urlencode($activeTab) ?>" class="btn btn-outline-secondary btn-sm">Reset</a>
                <?php endif; ?>
            </form>
        </div>

        <?php if ($activeTab === 'requests'): ?>
            <!-- =============================================================== -->
            <!-- TAB 1: STUDENT PROFILE CHANGE REQUESTS                          -->
            <!-- =============================================================== -->
            <div class="card shadow-sm border-0">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-dark">
                                <tr>
                                    <th>Req #</th>
                                    <th>Student Details</th>
                                    <th>Submitted At</th>
                                    <th style="min-width: 340px;">Requested Changes (Current &rarr; Requested)</th>
                                    <th>Student Reason</th>
                                    <th>Status / Reviewer</th>
                                    <th class="text-end pe-3" style="min-width: 240px;">Admin Decision</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($allRequests)): ?>
                                    <tr>
                                        <td colspan="7" class="text-center py-5 text-muted">
                                            No profile change requests found matching your filter criteria.
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($allRequests as $r): ?>
                                        <?php
                                            $diffs = computeRequestDiffs($r);
                                            $curFullName = trim(($r['Cur_FName'] ?? '') . ' ' . ($r['Cur_MName'] ? $r['Cur_MName'] . ' ' : '') . ($r['Cur_LName'] ?? ''));
                                            $statusBadge = match($r['Request_Status']) {
                                                'Approved' => 'bg-success',
                                                'Rejected' => 'bg-danger',
                                                default    => 'bg-warning text-dark'
                                            };
                                        ?>
                                        <tr>
                                            <td class="font-monospace fw-bold">#<?= (int)$r['Request_ID'] ?></td>
                                            <td>
                                                <a href="../user_profile.php?user_id=<?= (int)$r['User_ID'] ?>" class="fw-bold text-dark text-decoration-none">
                                                    <?= htmlspecialchars($curFullName, ENT_QUOTES, 'UTF-8') ?>
                                                </a>
                                                <div class="small text-secondary font-monospace">ID: <?= (int)$r['User_ID'] ?></div>
                                                <div class="small text-muted"><?= htmlspecialchars($r['User_Email'] ?? '', ENT_QUOTES, 'UTF-8') ?></div>
                                            </td>
                                            <td class="small text-secondary">
                                                <?= htmlspecialchars($r['Requested_At'], ENT_QUOTES, 'UTF-8') ?>
                                            </td>
                                            <td>
                                                <?php if (empty($diffs)): ?>
                                                    <span class="text-muted small"><em>Values already match current on-file record</em></span>
                                                <?php else: ?>
                                                    <div class="small">
                                                        <?php foreach ($diffs as $label => $d): ?>
                                                            <div class="mb-1">
                                                                <span class="fw-semibold text-dark"><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></span>
                                                                <span class="badge bg-light text-secondary border font-monospace" style="font-size: 0.7rem;"><?= htmlspecialchars($d['column'], ENT_QUOTES, 'UTF-8') ?></span>:
                                                                <span class="diff-old"><?= htmlspecialchars($d['old'], ENT_QUOTES, 'UTF-8') ?></span>
                                                                &rarr;
                                                                <span class="diff-new"><?= htmlspecialchars($d['new'], ENT_QUOTES, 'UTF-8') ?></span>
                                                            </div>
                                                        <?php endforeach; ?>
                                                    </div>
                                                <?php endif; ?>
                                            </td>
                                            <td class="small">
                                                <?php if (!empty($r['Request_Reason'])): ?>
                                                    <span class="text-dark">"<?= htmlspecialchars($r['Request_Reason'], ENT_QUOTES, 'UTF-8') ?>"</span>
                                                <?php else: ?>
                                                    <span class="text-muted">No note provided</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="badge <?= $statusBadge ?>"><?= htmlspecialchars($r['Request_Status'], ENT_QUOTES, 'UTF-8') ?></span>
                                                <?php if ($r['Request_Status'] !== 'Pending'): ?>
                                                    <div class="small text-muted mt-1" style="font-size: 0.76rem;">
                                                        By: <?= !empty($r['Reviewer_FName']) ? htmlspecialchars($r['Reviewer_FName'] . ' ' . $r['Reviewer_LName'], ENT_QUOTES, 'UTF-8') : ('Admin #' . (int)$r['Reviewed_By']) ?><br>
                                                        At: <?= htmlspecialchars($r['Reviewed_At'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                                                        <?php if (!empty($r['Admin_Notes'])): ?>
                                                            <br><em>Note: <?= htmlspecialchars($r['Admin_Notes'], ENT_QUOTES, 'UTF-8') ?></em>
                                                        <?php endif; ?>
                                                    </div>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-end pe-3">
                                                <?php if ($r['Request_Status'] === 'Pending'): ?>
                                                    <?php if ($canWrite): ?>
                                                        <form action="manage_change_requests_process.php" method="POST" class="d-flex flex-column gap-1 align-items-end">
                                                            <input type="hidden" name="request_id" value="<?= (int)$r['Request_ID'] ?>">
                                                            <input type="hidden" name="return_tab" value="requests">
                                                            <input type="text" name="admin_notes" class="form-control form-control-sm"
                                                                   placeholder="Optional admin note..." maxlength="255" style="max-width: 220px;">
                                                            <div class="d-flex gap-1 mt-1">
                                                                <button type="submit" name="decision" value="approve" class="btn btn-success btn-sm fw-semibold"
                                                                        onclick="return confirm('Permit Request #<?= (int)$r['Request_ID'] ?>? This will update the USER table and log all modified attributes in USER_UPDATE_LOG.');">
                                                                    &#10003; Permit Change
                                                                </button>
                                                                <button type="submit" name="decision" value="reject" class="btn btn-outline-danger btn-sm"
                                                                        onclick="return confirm('Reject Request #<?= (int)$r['Request_ID'] ?>?');">
                                                                    &times; Reject
                                                                </button>
                                                            </div>
                                                        </form>
                                                    <?php else: ?>
                                                        <span class="badge bg-light text-muted border">Level 2 Required</span>
                                                    <?php endif; ?>
                                                <?php else: ?>
                                                    <span class="badge bg-light text-secondary border">Processed</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        <?php else: ?>
            <!-- =============================================================== -->
            <!-- TAB 2: USER_UPDATE_LOG AUDIT TRAIL                              -->
            <!-- =============================================================== -->
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                    <h5 class="mb-0 fw-bold">USER_UPDATE_LOG Audit Records</h5>
                    <span class="badge bg-primary"><?= count($auditLogs) ?> Log Entries</span>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover table-striped align-middle mb-0">
                            <thead class="table-dark">
                                <tr>
                                    <th>Log ID</th>
                                    <th>Date &amp; Time of Change</th>
                                    <th>Target User Updated</th>
                                    <th>Changed Attribute (<code>USER</code> Column)</th>
                                    <th>Permitted By Administrator</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($auditLogs)): ?>
                                    <tr>
                                        <td colspan="5" class="text-center py-5 text-muted">
                                            No user update audit entries recorded in <code>USER_UPDATE_LOG</code> yet.
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($auditLogs as $log): ?>
                                        <tr>
                                            <td class="font-monospace fw-bold">#<?= (int)$log['Log_ID'] ?></td>
                                            <td class="font-monospace small"><?= htmlspecialchars($log['Date_Of_Change'], ENT_QUOTES, 'UTF-8') ?></td>
                                            <td>
                                                <a href="../user_profile.php?user_id=<?= (int)$log['User_ID'] ?>" class="fw-bold text-decoration-none">
                                                    <?= htmlspecialchars(trim(($log['Target_FName'] ?? '') . ' ' . ($log['Target_LName'] ?? '')), ENT_QUOTES, 'UTF-8') ?: ('User #' . (int)$log['User_ID']) ?>
                                                </a>
                                                <span class="badge bg-secondary-subtle text-secondary border ms-1 font-monospace">ID: <?= (int)$log['User_ID'] ?></span>
                                            </td>
                                            <td>
                                                <span class="badge bg-info-subtle text-dark border border-info-subtle font-monospace px-2 py-1">
                                                    <?= htmlspecialchars($log['Changed_Attribute'], ENT_QUOTES, 'UTF-8') ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="fw-semibold">
                                                    <?= htmlspecialchars(trim(($log['Admin_FName'] ?? '') . ' ' . ($log['Admin_LName'] ?? '')), ENT_QUOTES, 'UTF-8') ?: ('Admin #' . (int)$log['Admin_ID']) ?>
                                                </span>
                                                <span class="small text-muted font-monospace ms-1">(ID: <?= (int)$log['Admin_ID'] ?>)</span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        <?php endif; ?>

    </div>

    <!-- Bootstrap 5.3 JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
