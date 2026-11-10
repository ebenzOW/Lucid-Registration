<?php
/**
 * Lucid University Course Registration System
 * Admin Campus Facilities Management
 *
 * The companion manage_facilities_process.php handles availability and changes.
 */
require_once 'admin_guard.php';
require_once '../db_connect.php';
require_once '../profile_hover_card.php';

$admin_id = (int)$userID;
// Security Level 1 is the highest privilege; Levels 2–4 can view only.
$can_write = ((int)($_SESSION['Security_Level'] ?? 4) === 1);

if (empty($_SESSION['facilities_csrf'])) {
    $_SESSION['facilities_csrf'] = bin2hex(random_bytes(32));
}
if (empty($_SESSION['facilities_check_secret'])) {
    $_SESSION['facilities_check_secret'] = bin2hex(random_bytes(32));
}

$successMessage = $_SESSION['success_message'] ?? null;
$errorMessage = $_SESSION['error_message'] ?? null;
unset($_SESSION['success_message'], $_SESSION['error_message']);

function facilitiesEsc($value): string {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

// Display page only: all availability checks and writes use manage_facilities_process.php.

// Load buildings, including those that contain zero rooms. One building may
// contain any number of different rooms (ROOM.Building_ID is a foreign key).
$buildings = [];
$rooms = [];
$readError = '';
try {
    $stmt = $conn->prepare(
        'SELECT b.Building_ID, b.Building_Name, b.Usage_Purpose, COUNT(r.Room_ID) AS Room_Count '
        . 'FROM BUILDING b LEFT JOIN ROOM r ON r.Building_ID = b.Building_ID '
        . 'GROUP BY b.Building_ID, b.Building_Name, b.Usage_Purpose '
        . 'ORDER BY b.Building_Name, b.Building_ID'
    );
    $stmt->execute();
    $buildings = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    $stmt = $conn->prepare(
        'SELECT r.Room_ID, r.Building_ID, r.Room_NO, r.Room_Type, b.Building_Name, '
        . 'o.Office_Desks, l.Lab_Stations, lec.Lecture_Seats '
        . 'FROM ROOM r '
        . 'JOIN BUILDING b ON r.Building_ID = b.Building_ID '
        . 'LEFT JOIN OFFICE o ON r.Room_ID = o.Office_ID '
        . 'LEFT JOIN LAB l ON r.Room_ID = l.Lab_ID '
        . 'LEFT JOIN LECTURE lec ON r.Room_ID = lec.Lecture_ID '
        . 'ORDER BY b.Building_Name, r.Room_NO, r.Room_ID'
    );
    $stmt->execute();
    $rooms = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
} catch (Throwable $error) {
    error_log('manage_facilities.php read error: ' . $error->getMessage());
    $readError = 'Unable to load facilities using the existing database connection.';
}

$roomCounts = ['Lecture' => 0, 'Lab' => 0, 'Office' => 0];
foreach ($rooms as $room) {
    $type = ucfirst(strtolower((string)$room['Room_Type']));
    if (isset($roomCounts[$type])) {
        ++$roomCounts[$type];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Facilities | Lucid University Admin</title>
</head>
<body>
    <header>
        <nav aria-label="Administrator navigation">
            <a href="dashboard.php">Lucid University Admin</a>
            <a href="dashboard.php">Dashboard</a>
            <a href="create_user.php">Create User</a>
            <a href="view_users.php">View Users</a>
            <a href="create_course_section.php">Create Course Section</a>
            <a href="manage_courses.php">Manage Courses</a>
            <a href="manage_holds.php">Manage Holds</a>
            <a href="manage_facilities.php" aria-current="page">Manage Facilities</a>
            <?= getUserNavbarProfileHtml($conn, $admin_id, 'Admin') ?>
            <form action="../logout.php" method="POST"><button type="submit">Log Out</button></form>
        </nav>
    </header>

    <main>
        <?php if ($successMessage): ?>
            <p role="status"><strong>Success:</strong> <?= facilitiesEsc($successMessage) ?></p>
        <?php endif; ?>
        <?php if ($errorMessage): ?>
            <p role="alert"><strong>Error:</strong> <?= facilitiesEsc($errorMessage) ?></p>
        <?php endif; ?>
        <?php if ($readError): ?>
            <p role="alert"><?= facilitiesEsc($readError) ?></p>
        <?php endif; ?>

        <h1>Campus Facilities Management</h1>
        <p>View and update buildings and their lecture rooms, labs, and offices.</p>
        <?php if ($can_write && !$readError): ?>
            <p>
                <button type="button" id="createBuildingButton">New Building</button>
                <button type="button" id="createRoomButton" <?= !$buildings ? 'disabled' : '' ?>>New Room</button>
            </p>
        <?php elseif (!$can_write): ?>
            <p>Read-only access (Security Levels 2–4).</p>
        <?php endif; ?>
        <p><a href="dashboard.php">Back to Dashboard</a></p>

        <section aria-labelledby="summaryTitle">
            <h2 id="summaryTitle">Overview</h2>
            <p>
                Buildings: <?= count($buildings) ?> |
                Total rooms: <?= count($rooms) ?> |
                Lecture rooms: <?= $roomCounts['Lecture'] ?> |
                Labs: <?= $roomCounts['Lab'] ?> |
                Offices: <?= $roomCounts['Office'] ?>
            </p>
        </section>

        <section aria-labelledby="buildingsTitle">
            <h2 id="buildingsTitle">Buildings (<?= count($buildings) ?>)</h2>
            <label for="buildingSearch">Search buildings</label>
            <input id="buildingSearch" type="search" placeholder="Search buildings...">
            <table id="buildingsTable">
                <thead>
                    <tr>
                        <th scope="col">Building ID</th>
                        <th scope="col">Building Name</th>
                        <th scope="col">Usage Purpose</th>
                        <th scope="col">Number of Rooms</th>
                        <?php if ($can_write): ?><th scope="col">Actions</th><?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($buildings as $building): ?>
                        <tr data-search="<?= facilitiesEsc(strtolower(implode(' ', [$building['Building_ID'], $building['Building_Name'], $building['Usage_Purpose']]))) ?>">
                            <td><?= facilitiesEsc($building['Building_ID']) ?></td>
                            <td><?= facilitiesEsc($building['Building_Name']) ?></td>
                            <td><?= facilitiesEsc($building['Usage_Purpose'] ?: '—') ?></td>
                            <td><?= (int)$building['Room_Count'] ?></td>
                            <?php if ($can_write): ?>
                                <td>
                                    <button type="button" class="edit-building"
                                        data-id="<?= facilitiesEsc($building['Building_ID']) ?>"
                                        data-name="<?= facilitiesEsc($building['Building_Name']) ?>"
                                        data-purpose="<?= facilitiesEsc($building['Usage_Purpose']) ?>">Edit</button>
                                </td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$buildings): ?>
                        <tr><td colspan="<?= $can_write ? 5 : 4 ?>">No buildings have been registered.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </section>

        <section aria-labelledby="roomsTitle">
            <h2 id="roomsTitle">Rooms, Labs, Offices, and Lecture Rooms</h2>
            <p>A building may contain multiple rooms of different types.</p>
            <label for="roomSearch">Search rooms</label>
            <input id="roomSearch" type="search" placeholder="Search rooms...">
            <label for="buildingFilter">Building</label>
            <select id="buildingFilter">
                <option value="">All buildings</option>
                <?php foreach ($buildings as $building): ?>
                    <option value="<?= facilitiesEsc($building['Building_ID']) ?>"><?= facilitiesEsc($building['Building_Name']) ?></option>
                <?php endforeach; ?>
            </select>
            <label for="roomTypeFilter">Room type</label>
            <select id="roomTypeFilter">
                <option value="">All room types</option>
                <option value="Lecture">Lecture</option>
                <option value="Lab">Lab</option>
                <option value="Office">Office</option>
            </select>

            <table id="roomsTable">
                <thead>
                    <tr>
                        <th scope="col">Room ID</th>
                        <th scope="col">Building</th>
                        <th scope="col">Room Number</th>
                        <th scope="col">Room Type</th>
                        <th scope="col">Desks / Stations / Seats</th>
                        <?php if ($can_write): ?><th scope="col">Actions</th><?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rooms as $room):
                        $type = ucfirst(strtolower((string)$room['Room_Type']));
                        $capacity = $type === 'Office' ? $room['Office_Desks'] : ($type === 'Lab' ? $room['Lab_Stations'] : ($type === 'Lecture' ? $room['Lecture_Seats'] : null));
                    ?>
                        <tr data-building="<?= facilitiesEsc($room['Building_ID']) ?>"
                            data-type="<?= facilitiesEsc($type) ?>"
                            data-search="<?= facilitiesEsc(strtolower(implode(' ', [$room['Room_ID'], $room['Room_NO'], $room['Room_Type'], $room['Building_Name'], $room['Building_ID']]))) ?>">
                            <td><?= facilitiesEsc($room['Room_ID']) ?></td>
                            <td><?= facilitiesEsc($room['Building_Name']) ?> (<?= facilitiesEsc($room['Building_ID']) ?>)</td>
                            <td><?= facilitiesEsc($room['Room_NO']) ?></td>
                            <td><?= facilitiesEsc($type) ?></td>
                            <td><?= $capacity === null ? '—' : (int)$capacity ?></td>
                            <?php if ($can_write): ?>
                                <td>
                                    <button type="button" class="edit-room"
                                        data-id="<?= facilitiesEsc($room['Room_ID']) ?>"
                                        data-building="<?= facilitiesEsc($room['Building_ID']) ?>"
                                        data-number="<?= facilitiesEsc($room['Room_NO']) ?>"
                                        data-type="<?= facilitiesEsc($type) ?>"
                                        data-capacity="<?= facilitiesEsc($capacity) ?>">Edit</button>
                                </td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$rooms): ?>
                        <tr><td colspan="<?= $can_write ? 6 : 5 ?>">No rooms have been registered.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
            <p><span id="visibleRoomsCount"><?= count($rooms) ?></span> room(s) shown</p>
        </section>
    </main>

    <?php if ($can_write && !$readError): ?>
    <dialog id="facilityModal" aria-labelledby="facilityModalTitle">
        <form id="facilityForm" autocomplete="off">
            <h2 id="facilityModalTitle">Add Facility</h2>
            <p>Complete the fields, click <strong>Check Availability</strong>, and then click <strong>Submit</strong>. Changes require a new check.</p>
            <p id="facilityFormMessage" role="status" hidden></p>
            <input type="hidden" name="csrf" value="<?= facilitiesEsc($_SESSION['facilities_csrf']) ?>">
            <input type="hidden" name="entity" id="entityInput" value="building">
            <input type="hidden" name="operation" id="operationInput" value="create">
            <input type="hidden" name="ticket" id="ticketInput" value="">

            <section id="buildingFields">
                <h3>Building Details</h3>
                <p>
                    <label for="buildingId">Building ID *</label>
                    <input name="building_id" id="buildingId" maxlength="10" placeholder="e.g. SCI" required>
                    <small>Unique building ID; cannot be changed later.</small>
                </p>
                <p>
                    <label for="buildingName">Building Name *</label>
                    <input name="building_name" id="buildingName" maxlength="100" placeholder="e.g. Science Center" required>
                </p>
                <p>
                    <label for="usagePurpose">Usage Purpose</label>
                    <input name="usage_purpose" id="usagePurpose" maxlength="100" placeholder="e.g. Science education and research">
                </p>
            </section>

            <section id="roomFields" hidden>
                <h3>Room Details</h3>
                <p>
                    <label for="roomId">Room ID *</label>
                    <input name="room_id" id="roomId" maxlength="15" placeholder="e.g. SCI-101">
                    <small>Unique across all buildings; cannot be changed later.</small>
                </p>
                <p>
                    <label for="roomBuilding">Building *</label>
                    <select name="room_building_id" id="roomBuilding">
                        <option value="">Choose a building...</option>
                        <?php foreach ($buildings as $building): ?>
                            <option value="<?= facilitiesEsc($building['Building_ID']) ?>"><?= facilitiesEsc($building['Building_Name']) ?> (<?= facilitiesEsc($building['Building_ID']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </p>
                <p>
                    <label for="roomNumber">Room Number *</label>
                    <input name="room_no" id="roomNumber" maxlength="10" placeholder="e.g. 101">
                </p>
                <p>
                    <label for="roomType">Room Type *</label>
                    <select name="room_type" id="roomType">
                        <option value="">Select room type...</option>
                        <option value="Lecture">Lecture</option>
                        <option value="Lab">Lab</option>
                        <option value="Office">Office</option>
                    </select>
                </p>
                <p>
                    <label for="roomCapacity" id="capacityLabel">Capacity *</label>
                    <input name="capacity" id="roomCapacity" type="number" min="1" max="2147483647" step="1">
                    <small id="capacityHelp">Choose a room type to see the capacity units.</small>
                </p>
            </section>
            <p>
                <button type="button" id="cancelFacilityButton">Cancel</button>
                <button type="button" id="checkAvailabilityButton">Check Availability</button>
                <button type="submit" id="submitFacilityButton" disabled>Submit</button>
            </p>
        </form>
    </dialog>
    <?php endif; ?>

    <script>
    (() => {
        'use strict';
        const byId = id => document.getElementById(id);
        const buildingSearch = byId('buildingSearch');
        const roomSearch = byId('roomSearch');
        const buildingFilter = byId('buildingFilter');
        const typeFilter = byId('roomTypeFilter');
        const buildingRows = [...document.querySelectorAll('#buildingsTable tbody tr[data-search]')];
        const roomRows = [...document.querySelectorAll('#roomsTable tbody tr[data-search]')];
        function filterFacilities() {
            const buildingTerm = buildingSearch.value.trim().toLocaleLowerCase();
            const roomTerm = roomSearch.value.trim().toLocaleLowerCase();
            let displayed = 0;
            buildingRows.forEach(row => { row.hidden = !row.dataset.search.includes(buildingTerm); });
            roomRows.forEach(row => {
                row.hidden = !(row.dataset.search.includes(roomTerm)
                    && (!buildingFilter.value || row.dataset.building === buildingFilter.value)
                    && (!typeFilter.value || row.dataset.type === typeFilter.value));
                if (!row.hidden) displayed++;
            });
            byId('visibleRoomsCount').textContent = String(displayed);
        }
        [buildingSearch, roomSearch, buildingFilter, typeFilter].forEach(el => {
            el.addEventListener('input', filterFacilities);
            el.addEventListener('change', filterFacilities);
        });

        const form = byId('facilityForm');
        if (!form) return; // Read-only access for Security Levels 2–4.
        const dialog = byId('facilityModal');
        const modal = { show: () => dialog.showModal(), hide: () => dialog.close() };
        byId('cancelFacilityButton').addEventListener('click', () => modal.hide());
        dialog.addEventListener('close', () => { form.reset(); invalidateCheck(); });
        const checkButton = byId('checkAvailabilityButton');
        const submitButton = byId('submitFacilityButton');
        const message = byId('facilityFormMessage');
        const buildingFields = byId('buildingFields');
        const roomFields = byId('roomFields');
        const roomType = byId('roomType');
        let pending = false;
        let changeVersion = 0;

        function showMessage(text, available = false) {
            message.textContent = text;
            message.hidden = false;
        }
        function invalidateCheck() {
            changeVersion++;
            byId('ticketInput').value = '';
            submitButton.disabled = true;
            message.hidden = true;
            message.textContent = '';
        }
        function updateCapacityLabel() {
            const unit = { Lecture: ['Lecture seats', 'Number of seats in the lecture room.'], Lab: ['Lab stations', 'Number of lab workstations.'], Office: ['Office desks', 'Number of desks in the office.'] }[roomType.value];
            byId('capacityLabel').textContent = unit ? unit[0] + ' *' : 'Capacity *';
            byId('capacityHelp').textContent = unit ? unit[1] : 'Choose a room type to see the capacity units.';
        }
        function setMode(entity, operation) {
            byId('entityInput').value = entity;
            byId('operationInput').value = operation;
            buildingFields.hidden = entity !== 'building';
            roomFields.hidden = entity !== 'room';
            byId('buildingId').required = entity === 'building';
            byId('buildingName').required = entity === 'building';
            ['roomId', 'roomBuilding', 'roomNumber', 'roomType', 'roomCapacity'].forEach(id => {
                byId(id).required = entity === 'room';
            });
            byId('buildingId').readOnly = operation === 'update';
            byId('roomId').readOnly = operation === 'update';
            byId('facilityModalTitle').textContent = (operation === 'create' ? 'Create New ' : 'Edit ') + (entity === 'building' ? 'Building' : 'Room');
            updateCapacityLabel();
        }
        function startNew(entity) {
            form.reset();
            setMode(entity, 'create');
            invalidateCheck();
            modal.show();
        }
        byId('createBuildingButton').addEventListener('click', () => startNew('building'));
        byId('createRoomButton').addEventListener('click', () => startNew('room'));
        document.querySelectorAll('.edit-building').forEach(btn => btn.addEventListener('click', () => {
            form.reset();
            setMode('building', 'update');
            byId('buildingId').value = btn.dataset.id;
            byId('buildingName').value = btn.dataset.name;
            byId('usagePurpose').value = btn.dataset.purpose;
            invalidateCheck();
            modal.show();
        }));
        document.querySelectorAll('.edit-room').forEach(btn => btn.addEventListener('click', () => {
            form.reset();
            setMode('room', 'update');
            byId('roomId').value = btn.dataset.id;
            byId('roomBuilding').value = btn.dataset.building;
            byId('roomNumber').value = btn.dataset.number;
            byId('roomType').value = btn.dataset.type;
            byId('roomCapacity').value = btn.dataset.capacity;
            updateCapacityLabel();
            invalidateCheck();
            modal.show();
        }));
        roomType.addEventListener('change', updateCapacityLabel);
        form.addEventListener('input', invalidateCheck);
        form.addEventListener('change', invalidateCheck);

        function activeFieldsAreValid() {
            const section = byId('entityInput').value === 'building' ? buildingFields : roomFields;
            for (const control of section.querySelectorAll('input, select')) {
                if (!control.checkValidity()) {
                    control.reportValidity();
                    return false;
                }
            }
            return true;
        }
        async function postAction(action) {
            const data = new FormData(form);
            data.set('action', action);
            const response = await fetch('manage_facilities_process.php', {
                method: 'POST', credentials: 'same-origin',
                headers: { 'Accept': 'application/json' }, body: data,
            });
            let result;
            try {
                result = await response.json();
            } catch (_) {
                throw new Error('The server returned an unexpected response. Refresh and try again.');
            }
            if (!response.ok || !result.ok) {
                throw new Error(result.message || 'The operation could not be completed.');
            }
            return result;
        }
        checkButton.addEventListener('click', async () => {
            if (pending || !activeFieldsAreValid()) return;
            invalidateCheck();
            pending = true;
            const checkedVersion = changeVersion;
            checkButton.disabled = true;
            try {
                const result = await postAction('check');
                if (changeVersion !== checkedVersion) return; // The form changed during the request.
                if (result.available && result.ticket) {
                    byId('ticketInput').value = result.ticket;
                    submitButton.disabled = false;
                }
                showMessage(result.message, Boolean(result.available));
            } catch (error) {
                if (changeVersion === checkedVersion) showMessage(error.message);
            } finally {
                pending = false;
                checkButton.disabled = false;
            }
        });
        form.addEventListener('submit', async event => {
            event.preventDefault();
            if (pending || !activeFieldsAreValid() || !byId('ticketInput').value) return;
            pending = true;
            checkButton.disabled = true;
            submitButton.disabled = true;
            try {
                await postAction('save');
                window.location.assign('manage_facilities.php');
            } catch (error) {
                showMessage(error.message);
                byId('ticketInput').value = '';
            } finally {
                pending = false;
                checkButton.disabled = false;
            }
        });
    })();
    </script>
</body>
</html>
