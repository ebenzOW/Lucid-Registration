<?php
/**
 * Lucid University Course Registration System
 * Admin Campus Facilities Management
 *
 * Implements building & room management, room subtype synchronization,
 * capacity viewing, and role-based permissions (Security Level >= 2 for write access).
 */

require_once 'admin_guard.php';
require_once '../db_connect.php';
require_once __DIR__ . '/../includes/academic_helpers.php';
require_once '../profile_hover_card.php';

$admin_id  = (int)$userID;
$can_write = ((int)($_SESSION['Security_Level'] ?? 1) >= 2);

$successMessage = $_SESSION['success_message'] ?? null;
$errorMessage   = $_SESSION['error_message'] ?? null;
unset($_SESSION['success_message'], $_SESSION['error_message']);

// Fetch Buildings with Room Counts
$bStmt = $conn->prepare("
    SELECT b.Building_ID, b.Building_Name, b.Usage_Purpose, COUNT(r.Room_ID) AS Room_Count
    FROM BUILDING b
    LEFT JOIN ROOM r ON b.Building_ID = r.Building_ID
    GROUP BY b.Building_ID, b.Building_Name, b.Usage_Purpose
    ORDER BY b.Building_Name ASC, b.Building_ID ASC
");
$bStmt->execute();
$buildings = $bStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$bStmt->close();

// Fetch Rooms with Subtype Capacities
$rStmt = $conn->prepare("
    SELECT r.Room_ID, r.Building_ID, r.Room_NO, r.Room_Type, b.Building_Name,
           COALESCE(lec.Lecture_Seats, l.Lab_Stations, o.Office_Desks, 0) AS Capacity
    FROM ROOM r
    JOIN BUILDING b ON r.Building_ID = b.Building_ID
    LEFT JOIN LECTURE lec ON r.Room_ID = lec.Lecture_ID
    LEFT JOIN LAB l ON r.Room_ID = l.Lab_ID
    LEFT JOIN OFFICE o ON r.Room_ID = o.Office_ID
    ORDER BY b.Building_Name ASC, r.Room_NO ASC
");
$rStmt->execute();
$rooms = $rStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$rStmt->close();

$counts = ['Lecture' => 0, 'Lab' => 0, 'Office' => 0];
foreach ($rooms as $r) {
    $t = ucfirst(strtolower((string)$r['Room_Type']));
    if (isset($counts[$t])) {
        $counts[$t]++;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Campus Facilities | Lucid University Admin</title>
    <!-- Minimal Clean Bootstrap 5.3 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        <?= getProfileHoverCardStyles() ?>
        .badge-lecture { background-color: #0d6efd; color: #fff; }
        .badge-lab { background-color: #fd7e14; color: #fff; }
        .badge-office { background-color: #6c757d; color: #fff; }
    </style>
</head>
<body class="bg-light">

    <?= renderAdminNavbar($conn, $admin_id, basename($_SERVER['PHP_SELF'])) ?>

    <div class="container-fluid px-lg-5 mb-5">

        <!-- Page Header -->
        <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
            <div>
                <h2 class="fw-bold mb-1">Campus Facilities Management</h2>
                <div class="text-secondary">
                    Configure campus buildings, instructional classrooms, computer laboratories, and faculty offices.
                </div>
            </div>
            <div class="d-flex align-items-center gap-2">
                <?php if ($can_write): ?>
                    <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addBuildingModal">
                        + Add Building
                    </button>
                    <button type="button" class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#addRoomModal" <?= empty($buildings) ? 'disabled' : '' ?>>
                        + Add Room
                    </button>
                <?php else: ?>
                    <span class="badge bg-secondary-subtle text-secondary border px-3 py-2">
                        <i class="bi bi-eye"></i> Read-Only (Security Level 1)
                    </span>
                <?php endif; ?>
                <a href="dashboard.php" class="btn btn-outline-secondary btn-sm">&larr; Dashboard</a>
            </div>
        </div>

        <!-- Flash Message Alerts -->
        <?php if (!empty($successMessage)): ?>
            <div class="alert alert-success alert-dismissible fade show shadow-sm" role="alert">
                <strong>Success:</strong> <?= htmlspecialchars($successMessage, ENT_QUOTES, 'UTF-8') ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
        <?php if (!empty($errorMessage)): ?>
            <div class="alert alert-danger alert-dismissible fade show shadow-sm" role="alert">
                <strong>Error:</strong> <?= htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8') ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <!-- KPI Metric Summary Cards -->
        <div class="row g-3 mb-4">
            <div class="col-6 col-md-2">
                <div class="card shadow-sm border-0 h-100">
                    <div class="card-body p-3">
                        <div class="text-uppercase text-secondary small fw-bold">Buildings</div>
                        <div class="fs-3 fw-bold text-dark mt-1"><?= count($buildings) ?></div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-2">
                <div class="card shadow-sm border-0 h-100">
                    <div class="card-body p-3">
                        <div class="text-uppercase text-secondary small fw-bold">Total Rooms</div>
                        <div class="fs-3 fw-bold text-dark mt-1"><?= count($rooms) ?></div>
                    </div>
                </div>
            </div>
            <div class="col-4 col-md-2">
                <div class="card shadow-sm border-0 h-100 border-start border-primary border-4">
                    <div class="card-body p-3">
                        <div class="text-uppercase text-primary small fw-bold">Lecture Halls</div>
                        <div class="fs-3 fw-bold text-primary mt-1"><?= $counts['Lecture'] ?></div>
                    </div>
                </div>
            </div>
            <div class="col-4 col-md-3">
                <div class="card shadow-sm border-0 h-100 border-start border-warning border-4">
                    <div class="card-body p-3">
                        <div class="text-uppercase text-warning-emphasis small fw-bold">Laboratories</div>
                        <div class="fs-3 fw-bold text-dark mt-1"><?= $counts['Lab'] ?></div>
                    </div>
                </div>
            </div>
            <div class="col-4 col-md-3">
                <div class="card shadow-sm border-0 h-100 border-start border-secondary border-4">
                    <div class="card-body p-3">
                        <div class="text-uppercase text-secondary small fw-bold">Faculty Offices</div>
                        <div class="fs-3 fw-bold text-secondary mt-1"><?= $counts['Office'] ?></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filter & Search Bar -->
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-body p-3">
                <div class="row g-2 align-items-center">
                    <div class="col-md-5">
                        <input type="search" id="facilitySearch" class="form-control form-control-sm" placeholder="Search buildings, rooms, or purpose...">
                    </div>
                    <div class="col-md-4">
                        <select id="buildingFilter" class="form-select form-select-sm">
                            <option value="">All Buildings</option>
                            <?php foreach ($buildings as $b): ?>
                                <option value="<?= htmlspecialchars($b['Building_ID'], ENT_QUOTES, 'UTF-8') ?>">
                                    <?= htmlspecialchars($b['Building_Name'] . ' (' . $b['Building_ID'] . ')', ENT_QUOTES, 'UTF-8') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <select id="roomTypeFilter" class="form-select form-select-sm">
                            <option value="">All Room Types</option>
                            <option value="Lecture">Lecture Hall</option>
                            <option value="Lab">Laboratory</option>
                            <option value="Office">Office</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <!-- Buildings Card & Table -->
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-header bg-white py-3 border-0 d-flex justify-content-between align-items-center">
                <h5 class="fw-bold mb-0">Campus Buildings (<?= count($buildings) ?>)</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0" id="buildingsTable">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 15%;">Building ID</th>
                                <th style="width: 30%;">Building Name</th>
                                <th style="width: 35%;">Usage Purpose</th>
                                <th style="width: 10%;" class="text-center">Rooms</th>
                                <?php if ($can_write): ?>
                                    <th style="width: 10%;" class="text-end pe-4">Actions</th>
                                <?php endif; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($buildings)): ?>
                                <tr>
                                    <td colspan="<?= $can_write ? 5 : 4 ?>" class="text-center py-4 text-secondary">
                                        No campus buildings found.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($buildings as $b): ?>
                                    <tr class="building-row"
                                        data-building-id="<?= htmlspecialchars(strtolower($b['Building_ID']), ENT_QUOTES, 'UTF-8') ?>"
                                        data-building-name="<?= htmlspecialchars(strtolower($b['Building_Name']), ENT_QUOTES, 'UTF-8') ?>"
                                        data-purpose="<?= htmlspecialchars(strtolower($b['Usage_Purpose'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                                        <td class="fw-bold font-monospace text-primary">
                                            <?= htmlspecialchars($b['Building_ID'], ENT_QUOTES, 'UTF-8') ?>
                                        </td>
                                        <td class="fw-semibold">
                                            <?= htmlspecialchars($b['Building_Name'], ENT_QUOTES, 'UTF-8') ?>
                                        </td>
                                        <td class="text-secondary small">
                                            <?= htmlspecialchars($b['Usage_Purpose'] ?? 'N/A', ENT_QUOTES, 'UTF-8') ?>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge bg-secondary-subtle text-secondary border">
                                                <?= (int)$b['Room_Count'] ?> room<?= (int)$b['Room_Count'] === 1 ? '' : 's' ?>
                                            </span>
                                        </td>
                                        <?php if ($can_write): ?>
                                            <td class="text-end pe-4">
                                                <button type="button" class="btn btn-outline-primary btn-sm btn-edit-building"
                                                    data-id="<?= htmlspecialchars($b['Building_ID'], ENT_QUOTES, 'UTF-8') ?>"
                                                    data-name="<?= htmlspecialchars($b['Building_Name'], ENT_QUOTES, 'UTF-8') ?>"
                                                    data-purpose="<?= htmlspecialchars($b['Usage_Purpose'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                                                    Edit
                                                </button>
                                            </td>
                                        <?php endif; ?>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Rooms Card & Table -->
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white py-3 border-0 d-flex justify-content-between align-items-center">
                <h5 class="fw-bold mb-0">Classrooms, Labs &amp; Offices (<?= count($rooms) ?>)</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0" id="roomsTable">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 15%;">Room ID</th>
                                <th style="width: 25%;">Building</th>
                                <th style="width: 15%;">Room No</th>
                                <th style="width: 15%;">Room Type</th>
                                <th style="width: 18%;">Capacity</th>
                                <?php if ($can_write): ?>
                                    <th style="width: 12%;" class="text-end pe-4">Actions</th>
                                <?php endif; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($rooms)): ?>
                                <tr>
                                    <td colspan="<?= $can_write ? 6 : 5 ?>" class="text-center py-4 text-secondary">
                                        No rooms configured.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($rooms as $r): 
                                    $rType = ucfirst(strtolower((string)$r['Room_Type']));
                                    $badgeClass = match ($rType) {
                                        'Lecture' => 'badge-lecture',
                                        'Lab'     => 'badge-lab',
                                        'Office'  => 'badge-office',
                                        default   => 'bg-secondary',
                                    };
                                    $unitLabel = match ($rType) {
                                        'Lecture' => 'seats',
                                        'Lab'     => 'stations',
                                        'Office'  => 'desks',
                                        default   => 'capacity',
                                    };
                                ?>
                                    <tr class="room-row"
                                        data-room-id="<?= htmlspecialchars(strtolower($r['Room_ID']), ENT_QUOTES, 'UTF-8') ?>"
                                        data-building-id="<?= htmlspecialchars($r['Building_ID'], ENT_QUOTES, 'UTF-8') ?>"
                                        data-building-name="<?= htmlspecialchars(strtolower($r['Building_Name']), ENT_QUOTES, 'UTF-8') ?>"
                                        data-room-no="<?= htmlspecialchars(strtolower($r['Room_NO']), ENT_QUOTES, 'UTF-8') ?>"
                                        data-room-type="<?= htmlspecialchars($rType, ENT_QUOTES, 'UTF-8') ?>">
                                        <td class="fw-bold font-monospace text-dark">
                                            <?= htmlspecialchars($r['Room_ID'], ENT_QUOTES, 'UTF-8') ?>
                                        </td>
                                        <td>
                                            <span class="fw-semibold"><?= htmlspecialchars($r['Building_Name'], ENT_QUOTES, 'UTF-8') ?></span>
                                            <span class="text-secondary small font-monospace">(<?= htmlspecialchars($r['Building_ID'], ENT_QUOTES, 'UTF-8') ?>)</span>
                                        </td>
                                        <td>
                                            <?= htmlspecialchars($r['Room_NO'], ENT_QUOTES, 'UTF-8') ?>
                                        </td>
                                        <td>
                                            <span class="badge <?= $badgeClass ?> px-2 py-1">
                                                <?= htmlspecialchars($rType, ENT_QUOTES, 'UTF-8') ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="fw-bold"><?= (int)$r['Capacity'] ?></span>
                                            <span class="text-secondary small"><?= $unitLabel ?></span>
                                        </td>
                                        <?php if ($can_write): ?>
                                            <td class="text-end pe-4">
                                                <button type="button" class="btn btn-outline-primary btn-sm btn-edit-room"
                                                    data-id="<?= htmlspecialchars($r['Room_ID'], ENT_QUOTES, 'UTF-8') ?>"
                                                    data-building="<?= htmlspecialchars($r['Building_ID'], ENT_QUOTES, 'UTF-8') ?>"
                                                    data-roomno="<?= htmlspecialchars($r['Room_NO'], ENT_QUOTES, 'UTF-8') ?>"
                                                    data-type="<?= htmlspecialchars($rType, ENT_QUOTES, 'UTF-8') ?>"
                                                    data-capacity="<?= (int)$r['Capacity'] ?>">
                                                    Edit
                                                </button>
                                            </td>
                                        <?php endif; ?>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </div>

    <?php if ($can_write): ?>
        <!-- Modal: Add Building -->
        <div class="modal fade" id="addBuildingModal" tabindex="-1" aria-labelledby="addBuildingModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <form action="manage_facilities_process.php" method="POST" class="modal-content shadow">
                    <input type="hidden" name="action" value="create_building">
                    <div class="modal-header">
                        <h5 class="modal-title fw-bold" id="addBuildingModalLabel">Add Campus Building</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="b_id" class="form-label small fw-bold">Building Code / ID <span class="text-danger">*</span></label>
                            <input type="text" name="building_id" id="b_id" class="form-control text-uppercase" maxlength="10" placeholder="e.g. SCI, ARTS, LIB" required>
                            <div class="form-text small">1 to 10 uppercase characters or hyphens.</div>
                        </div>
                        <div class="mb-3">
                            <label for="b_name" class="form-label small fw-bold">Building Name <span class="text-danger">*</span></label>
                            <input type="text" name="building_name" id="b_name" class="form-control" maxlength="100" placeholder="e.g. Science Center" required>
                        </div>
                        <div class="mb-3">
                            <label for="b_purpose" class="form-label small fw-bold">Usage Purpose</label>
                            <input type="text" name="usage_purpose" id="b_purpose" class="form-control" maxlength="100" placeholder="e.g. Science classrooms and faculty labs">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Create Building</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Modal: Edit Building -->
        <div class="modal fade" id="editBuildingModal" tabindex="-1" aria-labelledby="editBuildingModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <form action="manage_facilities_process.php" method="POST" class="modal-content shadow">
                    <input type="hidden" name="action" value="update_building">
                    <input type="hidden" name="building_id" id="edit_b_id">
                    <div class="modal-header">
                        <h5 class="modal-title fw-bold" id="editBuildingModalLabel">Edit Building Details</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label small fw-bold">Building ID</label>
                            <input type="text" id="disp_edit_b_id" class="form-control bg-light font-monospace" readonly>
                        </div>
                        <div class="mb-3">
                            <label for="edit_b_name" class="form-label small fw-bold">Building Name <span class="text-danger">*</span></label>
                            <input type="text" name="building_name" id="edit_b_name" class="form-control" maxlength="100" required>
                        </div>
                        <div class="mb-3">
                            <label for="edit_b_purpose" class="form-label small fw-bold">Usage Purpose</label>
                            <input type="text" name="usage_purpose" id="edit_b_purpose" class="form-control" maxlength="100">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Modal: Add Room -->
        <div class="modal fade" id="addRoomModal" tabindex="-1" aria-labelledby="addRoomModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <form action="manage_facilities_process.php" method="POST" class="modal-content shadow">
                    <input type="hidden" name="action" value="create_room">
                    <div class="modal-header">
                        <h5 class="modal-title fw-bold" id="addRoomModalLabel">Add Campus Room</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="r_building_id" class="form-label small fw-bold">Building <span class="text-danger">*</span></label>
                            <select name="building_id" id="r_building_id" class="form-select" required>
                                <option value="">Select Building</option>
                                <?php foreach ($buildings as $b): ?>
                                    <option value="<?= htmlspecialchars($b['Building_ID'], ENT_QUOTES, 'UTF-8') ?>">
                                        <?= htmlspecialchars($b['Building_Name'] . ' (' . $b['Building_ID'] . ')', ENT_QUOTES, 'UTF-8') ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="row g-2 mb-3">
                            <div class="col-6">
                                <label for="r_no" class="form-label small fw-bold">Room Number <span class="text-danger">*</span></label>
                                <input type="text" name="room_no" id="r_no" class="form-control" maxlength="10" placeholder="e.g. 101, 204B" required>
                            </div>
                            <div class="col-6">
                                <label for="r_id" class="form-label small fw-bold">Room ID <span class="text-danger">*</span></label>
                                <input type="text" name="room_id" id="r_id" class="form-control text-uppercase font-monospace" maxlength="15" placeholder="e.g. SCI-101" required>
                            </div>
                        </div>
                        <div class="row g-2 mb-3">
                            <div class="col-6">
                                <label for="r_type" class="form-label small fw-bold">Room Type <span class="text-danger">*</span></label>
                                <select name="room_type" id="r_type" class="form-select" required>
                                    <option value="Lecture">Lecture Hall</option>
                                    <option value="Lab">Laboratory</option>
                                    <option value="Office">Office</option>
                                </select>
                            </div>
                            <div class="col-6">
                                <label for="r_capacity" class="form-label small fw-bold">Capacity <span class="text-danger">*</span></label>
                                <input type="number" name="capacity" id="r_capacity" class="form-control" min="1" value="25" required>
                                <div class="form-text small" id="r_capacity_help">Seats / stations / desks.</div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success">Create Room</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Modal: Edit Room -->
        <div class="modal fade" id="editRoomModal" tabindex="-1" aria-labelledby="editRoomModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <form action="manage_facilities_process.php" method="POST" class="modal-content shadow">
                    <input type="hidden" name="action" value="update_room">
                    <input type="hidden" name="room_id" id="edit_r_id">
                    <div class="modal-header">
                        <h5 class="modal-title fw-bold" id="editRoomModalLabel">Edit Room Parameters</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label small fw-bold">Room ID</label>
                            <input type="text" id="disp_edit_r_id" class="form-control bg-light font-monospace" readonly>
                        </div>
                        <div class="mb-3">
                            <label for="edit_r_building_id" class="form-label small fw-bold">Building <span class="text-danger">*</span></label>
                            <select name="building_id" id="edit_r_building_id" class="form-select" required>
                                <?php foreach ($buildings as $b): ?>
                                    <option value="<?= htmlspecialchars($b['Building_ID'], ENT_QUOTES, 'UTF-8') ?>">
                                        <?= htmlspecialchars($b['Building_Name'] . ' (' . $b['Building_ID'] . ')', ENT_QUOTES, 'UTF-8') ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="row g-2 mb-3">
                            <div class="col-6">
                                <label for="edit_r_no" class="form-label small fw-bold">Room Number <span class="text-danger">*</span></label>
                                <input type="text" name="room_no" id="edit_r_no" class="form-control" maxlength="10" required>
                            </div>
                            <div class="col-6">
                                <label for="edit_r_type" class="form-label small fw-bold">Room Type <span class="text-danger">*</span></label>
                                <select name="room_type" id="edit_r_type" class="form-select" required>
                                    <option value="Lecture">Lecture Hall</option>
                                    <option value="Lab">Laboratory</option>
                                    <option value="Office">Office</option>
                                </select>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="edit_r_capacity" class="form-label small fw-bold">Capacity <span class="text-danger">*</span></label>
                            <input type="number" name="capacity" id="edit_r_capacity" class="form-control" min="1" required>
                            <div class="form-text small">Cannot be lowered below scheduled course section capacities.</div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    <?php endif; ?>

    <!-- Bootstrap 5.3 JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

    <!-- Client-Side Filter & Modal Wireup Script -->
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        const searchInput    = document.getElementById('facilitySearch');
        const buildingFilter = document.getElementById('buildingFilter');
        const roomTypeFilter = document.getElementById('roomTypeFilter');

        function applyFilters() {
            const query = (searchInput.value || '').trim().toLowerCase();
            const selBuilding = (buildingFilter.value || '').trim();
            const selType = (roomTypeFilter.value || '').trim();

            // Filter Buildings Table
            document.querySelectorAll('.building-row').forEach(row => {
                const bId = row.dataset.buildingId || '';
                const bName = row.dataset.buildingName || '';
                const bPurpose = row.dataset.purpose || '';

                const matchesQuery = !query || bId.includes(query) || bName.includes(query) || bPurpose.includes(query);
                const matchesBuilding = !selBuilding || bId.toUpperCase() === selBuilding.toUpperCase();

                row.style.display = (matchesQuery && matchesBuilding) ? '' : 'none';
            });

            // Filter Rooms Table
            document.querySelectorAll('.room-row').forEach(row => {
                const rId = row.dataset.roomId || '';
                const rBldId = row.dataset.buildingId || '';
                const rBldName = row.dataset.buildingName || '';
                const rNo = row.dataset.roomNo || '';
                const rType = row.dataset.roomType || '';

                const matchesQuery = !query || rId.includes(query) || rBldId.toLowerCase().includes(query) || rBldName.includes(query) || rNo.includes(query);
                const matchesBuilding = !selBuilding || rBldId.toUpperCase() === selBuilding.toUpperCase();
                const matchesType = !selType || rType.toLowerCase() === selType.toLowerCase();

                row.style.display = (matchesQuery && matchesBuilding && matchesType) ? '' : 'none';
            });
        }

        if (searchInput) searchInput.addEventListener('input', applyFilters);
        if (buildingFilter) buildingFilter.addEventListener('change', applyFilters);
        if (roomTypeFilter) roomTypeFilter.addEventListener('change', applyFilters);

        // Auto-suggest Room ID on Add Room
        const rBldSelect = document.getElementById('r_building_id');
        const rNoInput   = document.getElementById('r_no');
        const rIdInput   = document.getElementById('r_id');

        function autoSuggestRoomId() {
            if (rBldSelect && rNoInput && rIdInput) {
                const bCode = (rBldSelect.value || '').trim();
                const no = (rNoInput.value || '').trim();
                if (bCode && no && (!rIdInput.value || rIdInput.dataset.autoGenerated === 'true')) {
                    rIdInput.value = `${bCode}-${no}`.toUpperCase();
                    rIdInput.dataset.autoGenerated = 'true';
                }
            }
        }
        if (rBldSelect) rBldSelect.addEventListener('change', autoSuggestRoomId);
        if (rNoInput) rNoInput.addEventListener('input', autoSuggestRoomId);
        if (rIdInput) {
            rIdInput.addEventListener('input', function() {
                rIdInput.dataset.autoGenerated = 'false';
            });
        }

        // Edit Building Modal Wireup
        const editBuildingModal = document.getElementById('editBuildingModal');
        if (editBuildingModal) {
            const editModalObj = new bootstrap.Modal(editBuildingModal);
            document.querySelectorAll('.btn-edit-building').forEach(btn => {
                btn.addEventListener('click', function () {
                    document.getElementById('edit_b_id').value = this.dataset.id || '';
                    document.getElementById('disp_edit_b_id').value = this.dataset.id || '';
                    document.getElementById('edit_b_name').value = this.dataset.name || '';
                    document.getElementById('edit_b_purpose').value = this.dataset.purpose || '';
                    editModalObj.show();
                });
            });
        }

        // Edit Room Modal Wireup
        const editRoomModal = document.getElementById('editRoomModal');
        if (editRoomModal) {
            const editRoomModalObj = new bootstrap.Modal(editRoomModal);
            document.querySelectorAll('.btn-edit-room').forEach(btn => {
                btn.addEventListener('click', function () {
                    document.getElementById('edit_r_id').value = this.dataset.id || '';
                    document.getElementById('disp_edit_r_id').value = this.dataset.id || '';
                    document.getElementById('edit_r_building_id').value = this.dataset.building || '';
                    document.getElementById('edit_r_no').value = this.dataset.roomno || '';
                    document.getElementById('edit_r_type').value = this.dataset.type || 'Lecture';
                    document.getElementById('edit_r_capacity').value = this.dataset.capacity || '25';
                    editRoomModalObj.show();
                });
            });
        }
    });
    </script>
</body>
</html>
