<?php
/**
 * Lucid University Course Registration System
 * Admin Campus Facilities Management — Request Processor
 */

// The supplied admin guard ends with a closing PHP tag; buffer its whitespace
// so that AJAX responses remain clean JSON and headers can be set safely.
ob_start();
require_once 'admin_guard.php';
require_once '../db_connect.php';
ob_clean();

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    http_response_code(405);
    echo json_encode(['ok' => false, 'message' => 'Only POST requests are accepted.']);
    exit;
}

function facilitiesLength(string $value): int {
    return function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : strlen($value);
}

/** Validate and normalize the fields actually defined in the supplied schema. */
function facilitiesValidate(array $input): array {
    $entity = trim((string)($input['entity'] ?? ''));
    $operation = trim((string)($input['operation'] ?? ''));
    if (!in_array($entity, ['building', 'room'], true) || !in_array($operation, ['create', 'update'], true)) {
        throw new InvalidArgumentException('Invalid facility operation.');
    }

    if ($entity === 'building') {
        $id = trim((string)($input['building_id'] ?? ''));
        $name = trim((string)($input['building_name'] ?? ''));
        $purpose = trim((string)($input['usage_purpose'] ?? ''));
        if ($id === '' || facilitiesLength($id) > 10) {
            throw new InvalidArgumentException('A building ID of 1–10 characters is required.');
        }
        if ($name === '' || facilitiesLength($name) > 100) {
            throw new InvalidArgumentException('A building name of 1–100 characters is required.');
        }
        if (facilitiesLength($purpose) > 100) {
            throw new InvalidArgumentException('Building usage purpose must not exceed 100 characters.');
        }
        return [
            'entity' => 'building', 'operation' => $operation,
            'building_id' => $id, 'building_name' => $name,
            'usage_purpose' => $purpose,
        ];
    }

    $id = trim((string)($input['room_id'] ?? ''));
    $buildingId = trim((string)($input['room_building_id'] ?? ''));
    $number = trim((string)($input['room_no'] ?? ''));
    $type = trim((string)($input['room_type'] ?? ''));
    $capacityRaw = trim((string)($input['capacity'] ?? ''));
    if ($id === '' || facilitiesLength($id) > 15) {
        throw new InvalidArgumentException('A room ID of 1–15 characters is required.');
    }
    if ($buildingId === '' || facilitiesLength($buildingId) > 10) {
        throw new InvalidArgumentException('Select an existing building.');
    }
    if ($number === '' || facilitiesLength($number) > 10) {
        throw new InvalidArgumentException('A room number of 1–10 characters is required.');
    }
    if (!in_array($type, ['Lecture', 'Lab', 'Office'], true)) {
        throw new InvalidArgumentException('Room type must be Lecture, Lab, or Office.');
    }
    if (!preg_match('/^[0-9]+$/D', $capacityRaw)) {
        throw new InvalidArgumentException('Enter a positive whole number for desks, stations, or seats.');
    }
    $capacity = filter_var($capacityRaw, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 2147483647]]);
    if ($capacity === false) {
        throw new InvalidArgumentException('Capacity must be a positive whole number.');
    }
    return [
        'entity' => 'room', 'operation' => $operation,
        'room_id' => $id, 'room_building_id' => $buildingId,
        'room_no' => $number, 'room_type' => $type, 'capacity' => $capacity,
    ];
}

/**
 * Availability here means the building ID or room ID/number is not in use.
 * It is not a course time-slot availability check.
 */
function facilitiesAvailability(mysqli $conn, array $facility): array {
    if ($facility['entity'] === 'building') {
        $stmt = $conn->prepare('SELECT Building_ID FROM BUILDING WHERE Building_ID = ? LIMIT 1');
        $stmt->bind_param('s', $facility['building_id']);
        $stmt->execute();
        $exists = $stmt->get_result()->fetch_assoc() !== null;
        $stmt->close();
        if ($facility['operation'] === 'create' && $exists) {
            return [false, 'That building ID is already in use.'];
        }
        if ($facility['operation'] === 'update' && !$exists) {
            return [false, 'This building no longer exists. Refresh the page.'];
        }
        return [true, $facility['operation'] === 'create' ? 'Building ID is available.' : 'Building is available to update.'];
    }

    $stmt = $conn->prepare('SELECT Building_ID FROM BUILDING WHERE Building_ID = ? LIMIT 1');
    $stmt->bind_param('s', $facility['room_building_id']);
    $stmt->execute();
    $buildingExists = $stmt->get_result()->fetch_assoc() !== null;
    $stmt->close();
    if (!$buildingExists) {
        return [false, 'The selected building does not exist.'];
    }

    $stmt = $conn->prepare('SELECT Room_ID FROM ROOM WHERE Room_ID = ? LIMIT 1');
    $stmt->bind_param('s', $facility['room_id']);
    $stmt->execute();
    $roomExists = $stmt->get_result()->fetch_assoc() !== null;
    $stmt->close();
    if ($facility['operation'] === 'create' && $roomExists) {
        return [false, 'That room ID is already in use.'];
    }
    if ($facility['operation'] === 'update' && !$roomExists) {
        return [false, 'This room no longer exists. Refresh the page.'];
    }

    // Different rooms in the same building are allowed. Only a repeated room
    // number *within that same building* is rejected.
    $stmt = $conn->prepare(
        'SELECT Room_ID FROM ROOM WHERE Building_ID = ? AND Room_NO = ? AND Room_ID <> ? LIMIT 1'
    );
    $stmt->bind_param('sss', $facility['room_building_id'], $facility['room_no'], $facility['room_id']);
    $stmt->execute();
    $duplicate = $stmt->get_result()->fetch_assoc() !== null;
    $stmt->close();
    if ($duplicate) {
        return [false, 'Room number ' . $facility['room_no'] . ' is already used in this building.'];
    }
    return [true, 'Room ID and room number are available in this building.'];
}

/** The successful check is signed and bound to the exact normalized form fields. */
function facilitiesMakeTicket(array $facility): string {
    $issued = time();
    $payload = $issued . '.' . json_encode($facility, JSON_UNESCAPED_UNICODE);
    $signature = hash_hmac('sha256', $payload, $_SESSION['facilities_check_secret']);
    return $issued . '.' . $signature;
}

function facilitiesTicketValid(string $ticket, array $facility): bool {
    if (!preg_match('/^([0-9]{10})\.([a-f0-9]{64})$/D', $ticket, $matches)) {
        return false;
    }
    $issued = (int)$matches[1];
    if ($issued > time() || time() - $issued > 600) {
        return false;
    }
    $payload = $issued . '.' . json_encode($facility, JSON_UNESCAPED_UNICODE);
    $expected = hash_hmac('sha256', $payload, $_SESSION['facilities_check_secret']);
    return hash_equals($expected, $matches[2]);
}

// Handle availability checks and saves for the facilities management page.
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    $lockHeld = false;
    $transactionStarted = false;
    try {
        if (empty($_SESSION['facilities_csrf']) || empty($_SESSION['facilities_check_secret']) ||
            !hash_equals($_SESSION['facilities_csrf'], (string)($_POST['csrf'] ?? ''))) {
            throw new RuntimeException('Your session expired. Refresh the page and try again.');
        }
        // Level 1 is highest authority. The shared admin_guard helper currently
        // uses the reverse policy, so enforce facility permissions here.
        // Levels 2–4 remain read-only and cannot call check/save directly.
        if ((int)($_SESSION['Security_Level'] ?? 4) !== 1) {
            throw new RuntimeException('Security Level 1 administrator access is required to create or edit facilities.');
        }
        $action = (string)($_POST['action'] ?? '');
        if (!in_array($action, ['check', 'save'], true)) {
            throw new InvalidArgumentException('Invalid action.');
        }
        $facility = facilitiesValidate($_POST);

        if ($action === 'check') {
            [$available, $message] = facilitiesAvailability($conn, $facility);
            echo json_encode([
                'ok' => true, 'available' => $available, 'message' => $message,
                'ticket' => $available ? facilitiesMakeTicket($facility) : null,
            ]);
        } else {
            if (!facilitiesTicketValid((string)($_POST['ticket'] ?? ''), $facility)) {
                throw new RuntimeException('Check availability for these exact details before submitting.');
            }

            // Serializes concurrent facility saves made through this page without
            // requiring any database migration or new UNIQUE index.
            $lockResult = $conn->query("SELECT GET_LOCK('lucid_facilities_manage_save', 10) AS obtained")->fetch_assoc();
            if ((int)($lockResult['obtained'] ?? 0) !== 1) {
                throw new RuntimeException('Facilities are being updated. Try submitting again.');
            }
            $lockHeld = true;
            $conn->begin_transaction();
            $transactionStarted = true;

            // Always re-check at submission because records can change after a check.
            [$available, $message] = facilitiesAvailability($conn, $facility);
            if (!$available) {
                throw new RuntimeException($message . ' Check availability again.');
            }

            if ($facility['entity'] === 'building') {
                if ($facility['operation'] === 'create') {
                    $stmt = $conn->prepare('INSERT INTO BUILDING (Building_ID, Building_Name, Usage_Purpose) VALUES (?, ?, ?)');
                    $stmt->bind_param('sss', $facility['building_id'], $facility['building_name'], $facility['usage_purpose']);
                } else {
                    $stmt = $conn->prepare('UPDATE BUILDING SET Building_Name = ?, Usage_Purpose = ? WHERE Building_ID = ?');
                    $stmt->bind_param('sss', $facility['building_name'], $facility['usage_purpose'], $facility['building_id']);
                }
                $stmt->execute();
                $stmt->close();
            } else {
                $roomId = $facility['room_id'];
                $type = $facility['room_type'];
                $capacity = $facility['capacity'];
                $oldType = null;
                if ($facility['operation'] === 'create') {
                    $stmt = $conn->prepare('INSERT INTO ROOM (Room_ID, Building_ID, Room_NO, Room_Type) VALUES (?, ?, ?, ?)');
                    $stmt->bind_param('ssss', $roomId, $facility['room_building_id'], $facility['room_no'], $type);
                    $stmt->execute();
                    $stmt->close();
                } else {
                    $stmt = $conn->prepare('SELECT Room_Type FROM ROOM WHERE Room_ID = ? FOR UPDATE');
                    $stmt->bind_param('s', $roomId);
                    $stmt->execute();
                    $previous = $stmt->get_result()->fetch_assoc();
                    $stmt->close();
                    if (!$previous) {
                        throw new RuntimeException('This room was removed. Refresh the page.');
                    }
                    $oldType = ucfirst(strtolower((string)$previous['Room_Type']));

                    // OFFICE is referenced by FACULTY and DEPARTMENT. Prevent
                    // changing its type from silently clearing those assignments.
                    if ($oldType === 'Office' && $type !== 'Office') {
                        $stmt = $conn->prepare('SELECT COUNT(*) AS n FROM FACULTY WHERE Office_ID = ?');
                        $stmt->bind_param('s', $roomId);
                        $stmt->execute();
                        $facultyUses = (int)$stmt->get_result()->fetch_assoc()['n'];
                        $stmt->close();
                        $stmt = $conn->prepare('SELECT COUNT(*) AS n FROM DEPARTMENT WHERE Office_ID = ?');
                        $stmt->bind_param('s', $roomId);
                        $stmt->execute();
                        $departmentUses = (int)$stmt->get_result()->fetch_assoc()['n'];
                        $stmt->close();
                        if ($facultyUses + $departmentUses > 0) {
                            throw new RuntimeException('This office is assigned to faculty or a department. Reassign it before changing the room type.');
                        }
                    }
                    $stmt = $conn->prepare('UPDATE ROOM SET Building_ID = ?, Room_NO = ?, Room_Type = ? WHERE Room_ID = ?');
                    $stmt->bind_param('ssss', $facility['room_building_id'], $facility['room_no'], $type, $roomId);
                    $stmt->execute();
                    $stmt->close();
                }

                // ROOM is the parent record. Its type-specific child must be
                // synchronized in the matching table in the same transaction.
                $subtypes = [
                    'Office' => ['OFFICE', 'Office_ID', 'Office_Desks'],
                    'Lab' => ['LAB', 'Lab_ID', 'Lab_Stations'],
                    'Lecture' => ['LECTURE', 'Lecture_ID', 'Lecture_Seats'],
                ];
                foreach ($subtypes as $subtype => [$table, $idColumn, $capacityColumn]) {
                    if ($subtype === $type) {
                        $stmt = $conn->prepare(
                            "INSERT INTO {$table} ({$idColumn}, {$capacityColumn}) VALUES (?, ?) " .
                            "ON DUPLICATE KEY UPDATE {$capacityColumn} = VALUES({$capacityColumn})"
                        );
                        $stmt->bind_param('si', $roomId, $capacity);
                    } else {
                        $stmt = $conn->prepare("DELETE FROM {$table} WHERE {$idColumn} = ?");
                        $stmt->bind_param('s', $roomId);
                    }
                    $stmt->execute();
                    $stmt->close();
                }
            }

            $conn->commit();
            $transactionStarted = false;
            $_SESSION['success_message'] = ($facility['entity'] === 'building' ? 'Building' : 'Room') .
                ($facility['operation'] === 'create' ? ' created successfully.' : ' updated successfully.');
            echo json_encode(['ok' => true, 'saved' => true]);
        }
    } catch (Throwable $error) {
        if ($transactionStarted) {
            $conn->rollback();
        }
        $isDbError = $error instanceof mysqli_sql_exception;
        if ($isDbError) {
            error_log('manage_facilities_process.php SQL error: ' . $error->getMessage());
        }
        http_response_code($isDbError ? 409 : 422);
        echo json_encode([
            'ok' => false,
            'message' => $isDbError
                ? 'Database could not save this facility. Check for duplicate or referenced records.'
                : $error->getMessage(),
        ]);
    } finally {
        if ($lockHeld) {
            $conn->query("SELECT RELEASE_LOCK('lucid_facilities_manage_save')");
        }
    }
    exit;
}