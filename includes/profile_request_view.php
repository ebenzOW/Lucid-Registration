<?php
/**
 * Shared View Partial for Student & Faculty Profile Change Requests
 * Expects: $user, $requests, $portalUserId, $idFieldLabel, $navbarHtml, $successMessage, $errorMessage
 */

$hasPendingRequest = false;
$pendingRequest = null;
foreach ($requests as $row) {
    if ($row['Request_Status'] === 'Pending') {
        $hasPendingRequest = true;
        $pendingRequest = $row;
        break;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile Change Requests | Lucid University</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .locked-field {
            background-color: #e9ecef !important;
            cursor: not-allowed;
        }
    </style>
</head>
<body class="bg-light">

    <?= $navbarHtml ?>

    <div class="container mb-5">

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

        <!-- Page Header -->
        <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
            <div>
                <h2 class="fw-bold mb-1">Personal Profile Change Requests</h2>
                <div class="text-secondary">
                    Submit a request to update your allowed personal profile details. Approved updates are logged in the university audit log.
                </div>
            </div>
            <div class="d-flex gap-2">
                <a href="../user_profile.php?user_id=<?= (int)$portalUserId ?>" class="btn btn-outline-primary btn-sm">
                    View My Full Profile
                </a>
                <a href="dashboard.php" class="btn btn-outline-secondary btn-sm">
                    &larr; Return to Dashboard
                </a>
            </div>
        </div>

        <div class="row g-4">

            <!-- Left Column: Submit Change Request Form -->
            <div class="col-lg-7">
                <div class="card shadow-sm border-0">
                    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                        <h5 class="mb-0 fw-bold">Request Profile Information Update</h5>
                        <?php if ($hasPendingRequest): ?>
                            <span class="badge bg-warning text-dark">Pending Request #<?= (int)$pendingRequest['Request_ID'] ?></span>
                        <?php else: ?>
                            <span class="badge bg-primary">Ready to Submit</span>
                        <?php endif; ?>
                    </div>
                    <div class="card-body p-4">

                        <?php if ($hasPendingRequest): ?>
                            <div class="alert alert-warning border-warning mb-4">
                                <div class="fw-bold mb-1">Active Pending Change Request (#<?= (int)$pendingRequest['Request_ID'] ?>)</div>
                                <div class="small mb-2">
                                    You submitted a profile change request on <strong><?= htmlspecialchars($pendingRequest['Requested_At'], ENT_QUOTES, 'UTF-8') ?></strong> that is currently awaiting administrator review.
                                    You may cancel your pending request below if you wish to submit a revised request.
                                </div>
                                <form action="profile_requests_process.php" method="POST" class="m-0" onsubmit="return confirm('Cancel your pending profile change request?');">
                                    <input type="hidden" name="action" value="cancel_request">
                                    <input type="hidden" name="request_id" value="<?= (int)$pendingRequest['Request_ID'] ?>">
                                    <button type="submit" class="btn btn-warning btn-sm fw-semibold">
                                        &times; Cancel Pending Request #<?= (int)$pendingRequest['Request_ID'] ?>
                                    </button>
                                </form>
                            </div>
                        <?php endif; ?>

                        <form action="profile_requests_process.php" method="POST">
                            <input type="hidden" name="action" value="submit_request">

                            <!-- Immutable Identifiers -->
                            <div class="p-3 bg-light rounded border mb-4">
                                <div class="small fw-bold text-uppercase text-secondary mb-2">
                                    Immutable University Identifiers (Contact the Registrar for Changes)
                                </div>
                                <div class="row g-3">
                                    <div class="col-md-4">
                                        <label class="form-label small fw-semibold text-muted mb-1"><?= htmlspecialchars($idFieldLabel, ENT_QUOTES, 'UTF-8') ?> (Locked)</label>
                                        <input type="text" class="form-control form-control-sm locked-field font-monospace" value="<?= (int)$user['User_ID'] ?>" disabled readonly>
                                    </div>
                                    <div class="col-md-8">
                                        <label class="form-label small fw-semibold text-muted mb-1">University Email (Locked)</label>
                                        <input type="email" class="form-control form-control-sm locked-field" value="<?= htmlspecialchars($user['User_Email'] ?? '', ENT_QUOTES, 'UTF-8') ?>" disabled readonly>
                                    </div>
                                </div>
                            </div>

                            <!-- Editable Personal Identity Fields -->
                            <h6 class="fw-bold text-dark border-bottom pb-2 mb-3">1. Legal Name &amp; Demographic Details</h6>
                            <div class="row g-3 mb-4">
                                <div class="col-md-4">
                                    <label for="req_fname" class="form-label small fw-bold">First Name <span class="text-danger">*</span></label>
                                    <input type="text" name="req_fname" id="req_fname" class="form-control form-control-sm" maxlength="50" required
                                           value="<?= htmlspecialchars($user['User_FName'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                                           <?= $hasPendingRequest ? 'disabled' : '' ?>>
                                </div>
                                <div class="col-md-4">
                                    <label for="req_mname" class="form-label small fw-bold">Middle Name</label>
                                    <input type="text" name="req_mname" id="req_mname" class="form-control form-control-sm" maxlength="50"
                                           value="<?= htmlspecialchars($user['User_MName'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                                           <?= $hasPendingRequest ? 'disabled' : '' ?>>
                                </div>
                                <div class="col-md-4">
                                    <label for="req_lname" class="form-label small fw-bold">Last Name <span class="text-danger">*</span></label>
                                    <input type="text" name="req_lname" id="req_lname" class="form-control form-control-sm" maxlength="50" required
                                           value="<?= htmlspecialchars($user['User_LName'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                                           <?= $hasPendingRequest ? 'disabled' : '' ?>>
                                </div>
                                <div class="col-md-4">
                                    <label for="req_gender" class="form-label small fw-bold">Gender</label>
                                    <?php $curGender = $user['User_Gender'] ?? ''; ?>
                                    <select name="req_gender" id="req_gender" class="form-select form-select-sm" <?= $hasPendingRequest ? 'disabled' : '' ?>>
                                        <option value="" <?= $curGender === '' ? 'selected' : '' ?>>-- Unspecified --</option>
                                        <option value="Male" <?= $curGender === 'Male' ? 'selected' : '' ?>>Male</option>
                                        <option value="Female" <?= $curGender === 'Female' ? 'selected' : '' ?>>Female</option>
                                        <option value="Non-Binary" <?= $curGender === 'Non-Binary' ? 'selected' : '' ?>>Non-Binary</option>
                                        <option value="Other" <?= $curGender === 'Other' ? 'selected' : '' ?>>Other</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label for="req_dob" class="form-label small fw-bold">Date of Birth</label>
                                    <input type="date" name="req_dob" id="req_dob" class="form-control form-control-sm"
                                           max="<?= date('Y-m-d') ?>"
                                           value="<?= htmlspecialchars($user['User_DOB'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                                           <?= $hasPendingRequest ? 'disabled' : '' ?>>
                                </div>
                                <div class="col-md-4">
                                    <label for="req_phone" class="form-label small fw-bold">Phone Number</label>
                                    <input type="text" name="req_phone" id="req_phone" class="form-control form-control-sm" maxlength="15"
                                           placeholder="e.g. 555-234-5678"
                                           value="<?= htmlspecialchars($user['User_PhoneNo'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                                           <?= $hasPendingRequest ? 'disabled' : '' ?>>
                                </div>
                            </div>

                            <!-- Editable Residential Address Fields -->
                            <h6 class="fw-bold text-dark border-bottom pb-2 mb-3">2. Residential Address</h6>
                            <div class="row g-3 mb-4">
                                <div class="col-md-3">
                                    <label for="req_street_no" class="form-label small fw-bold">Street No.</label>
                                    <input type="text" name="req_street_no" id="req_street_no" class="form-control form-control-sm" maxlength="10"
                                           placeholder="e.g. 123"
                                           value="<?= htmlspecialchars($user['Street_No'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                                           <?= $hasPendingRequest ? 'disabled' : '' ?>>
                                </div>
                                <div class="col-md-9">
                                    <label for="req_street_name" class="form-label small fw-bold">Street Name</label>
                                    <input type="text" name="req_street_name" id="req_street_name" class="form-control form-control-sm" maxlength="50"
                                           placeholder="e.g. Oak Avenue"
                                           value="<?= htmlspecialchars($user['Street_Name'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                                           <?= $hasPendingRequest ? 'disabled' : '' ?>>
                                </div>
                                <div class="col-md-4">
                                    <label for="req_city" class="form-label small fw-bold">City</label>
                                    <input type="text" name="req_city" id="req_city" class="form-control form-control-sm" maxlength="50"
                                           value="<?= htmlspecialchars($user['City'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                                           <?= $hasPendingRequest ? 'disabled' : '' ?>>
                                </div>
                                <div class="col-md-3">
                                    <label for="req_state" class="form-label small fw-bold">State</label>
                                    <input type="text" name="req_state" id="req_state" class="form-control form-control-sm" maxlength="20"
                                           value="<?= htmlspecialchars($user['State'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                                           <?= $hasPendingRequest ? 'disabled' : '' ?>>
                                </div>
                                <div class="col-md-2">
                                    <label for="req_zip" class="form-label small fw-bold">Zip Code</label>
                                    <input type="text" name="req_zip" id="req_zip" class="form-control form-control-sm" maxlength="15"
                                           value="<?= htmlspecialchars($user['Zip_Code'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                                           <?= $hasPendingRequest ? 'disabled' : '' ?>>
                                </div>
                                <div class="col-md-3">
                                    <label for="req_country" class="form-label small fw-bold">Country</label>
                                    <input type="text" name="req_country" id="req_country" class="form-control form-control-sm" maxlength="50"
                                           placeholder="e.g. USA"
                                           value="<?= htmlspecialchars($user['Country'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                                           <?= $hasPendingRequest ? 'disabled' : '' ?>>
                                </div>
                            </div>

                            <!-- Reason for Request -->
                            <div class="mb-4">
                                <label for="request_reason" class="form-label small fw-bold">Reason / Supporting Note for Administrator</label>
                                <input type="text" name="request_reason" id="request_reason" class="form-control form-control-sm" maxlength="255"
                                       placeholder="e.g. Moved to new residence, updated phone number, legal name update"
                                       <?= $hasPendingRequest ? 'disabled' : '' ?>>
                            </div>

                            <div class="d-flex justify-content-end gap-2">
                                <button type="reset" class="btn btn-outline-secondary btn-sm" <?= $hasPendingRequest ? 'disabled' : '' ?>>
                                    Reset to Current Values
                                </button>
                                <button type="submit" class="btn btn-primary btn-sm px-4 fw-semibold" <?= $hasPendingRequest ? 'disabled' : '' ?>>
                                    Submit Change Request &rarr;
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Right Column: Current Active Profile Summary & Request History -->
            <div class="col-lg-5">
                <div class="card shadow-sm border-0 mb-4">
                    <div class="card-header bg-white py-3">
                        <h6 class="mb-0 fw-bold">Current On-File Record</h6>
                    </div>
                    <div class="card-body p-3 small">
                        <div class="row g-2">
                            <div class="col-5 text-muted">Full Name:</div>
                            <div class="col-7 fw-semibold">
                                <?= htmlspecialchars(trim(($user['User_FName'] ?? '') . ' ' . (!empty($user['User_MName']) ? $user['User_MName'] . ' ' : '') . ($user['User_LName'] ?? '')), ENT_QUOTES, 'UTF-8') ?>
                            </div>
                            <div class="col-5 text-muted">Gender / DOB:</div>
                            <div class="col-7">
                                <?= htmlspecialchars($user['User_Gender'] ?: 'N/A', ENT_QUOTES, 'UTF-8') ?> &bull;
                                <?= htmlspecialchars($user['User_DOB'] ?: 'N/A', ENT_QUOTES, 'UTF-8') ?>
                            </div>
                            <div class="col-5 text-muted">Phone Number:</div>
                            <div class="col-7"><?= htmlspecialchars($user['User_PhoneNo'] ?: 'N/A', ENT_QUOTES, 'UTF-8') ?></div>
                            <div class="col-5 text-muted">Address:</div>
                            <div class="col-7">
                                <?= htmlspecialchars(formatAddress($user) ?: 'No address on file', ENT_QUOTES, 'UTF-8') ?>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card shadow-sm border-0">
                    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                        <h6 class="mb-0 fw-bold">My Request History</h6>
                        <span class="badge bg-secondary"><?= count($requests) ?> Total</span>
                    </div>
                    <div class="card-body p-0">
                        <?php if (empty($requests)): ?>
                            <div class="p-4 text-center text-muted small">
                                You have not submitted any profile change requests yet.
                            </div>
                        <?php else: ?>
                            <div class="list-group list-group-flush">
                                <?php foreach ($requests as $r): ?>
                                    <?php
                                        $statusBadge = match($r['Request_Status']) {
                                            'Approved' => 'bg-success',
                                            'Rejected' => 'bg-danger',
                                            default    => 'bg-warning text-dark'
                                        };
                                        $reqFullName = trim($r['Req_FName'] . ' ' . (!empty($r['Req_MName']) ? $r['Req_MName'] . ' ' : '') . $r['Req_LName']);
                                        $reqAddr = formatAddress([
                                            'Street_No'   => $r['Req_Street_No'] ?? '',
                                            'Street_Name' => $r['Req_Street_Name'] ?? '',
                                            'City'        => $r['Req_City'] ?? '',
                                            'State'       => $r['Req_State'] ?? '',
                                            'Zip_Code'    => $r['Req_Zip_Code'] ?? '',
                                            'Country'     => $r['Req_Country'] ?? '',
                                        ]);
                                    ?>
                                    <div class="list-group-item p-3 small">
                                        <div class="d-flex justify-content-between align-items-center mb-1">
                                            <span class="fw-bold font-monospace">Request #<?= (int)$r['Request_ID'] ?></span>
                                            <span class="badge <?= $statusBadge ?>"><?= htmlspecialchars($r['Request_Status'], ENT_QUOTES, 'UTF-8') ?></span>
                                        </div>
                                        <div class="text-muted mb-2" style="font-size: 0.78rem;">
                                            Submitted: <?= htmlspecialchars($r['Requested_At'], ENT_QUOTES, 'UTF-8') ?>
                                        </div>
                                        <div class="bg-light p-2 rounded border mb-2">
                                            <div><strong>Requested Name:</strong> <?= htmlspecialchars($reqFullName, ENT_QUOTES, 'UTF-8') ?></div>
                                            <div><strong>Phone / Gender / DOB:</strong> <?= htmlspecialchars($r['Req_PhoneNo'] ?: '-', ENT_QUOTES, 'UTF-8') ?> &bull; <?= htmlspecialchars($r['Req_Gender'] ?: '-', ENT_QUOTES, 'UTF-8') ?> &bull; <?= htmlspecialchars($r['Req_DOB'] ?: '-', ENT_QUOTES, 'UTF-8') ?></div>
                                            <div><strong>Requested Address:</strong> <?= htmlspecialchars($reqAddr ?: '-', ENT_QUOTES, 'UTF-8') ?></div>
                                            <?php if (!empty($r['Request_Reason'])): ?>
                                                <div class="mt-1 text-secondary"><em>Reason: "<?= htmlspecialchars($r['Request_Reason'], ENT_QUOTES, 'UTF-8') ?>"</em></div>
                                            <?php endif; ?>
                                        </div>
                                        <?php if ($r['Request_Status'] !== 'Pending'): ?>
                                            <div class="text-secondary" style="font-size: 0.78rem;">
                                                Reviewed by Admin <?= !empty($r['Admin_FName']) ? htmlspecialchars($r['Admin_FName'] . ' ' . $r['Admin_LName'], ENT_QUOTES, 'UTF-8') : ('#' . (int)$r['Reviewed_By']) ?>
                                                on <?= htmlspecialchars($r['Reviewed_At'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                                                <?php if (!empty($r['Admin_Notes'])): ?>
                                                    <br><strong>Admin Note:</strong> <?= htmlspecialchars($r['Admin_Notes'], ENT_QUOTES, 'UTF-8') ?>
                                                <?php endif; ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>