<?php
/**
 * Lucid University Course Registration System
 * Admin Portal - Department Management Request Processor
 *
 * Processes department creation, updates, and deletion using server-side
 * validations and authorization checks.
 */

ob_start();
require_once 'admin_guard.php';
require_once '../db_connect.php';

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: manage_departments.php");
    exit;
}

$action = (string) ($_POST['action'] ?? '');
$departmentId = filter_var($_POST['department_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$transactionActive = false;

// Only edits submitted from a department details page return to that page.
$redirectUrl = 'manage_departments.php';
if ($action === 'update' && ($_POST['return_to'] ?? '') === 'details'
    && $departmentId !== false && $departmentId !== null) {
    $redirectUrl = 'admin_department_details.php?department_id=' . (int)$departmentId;
}

try {
    //Enforce administrator write permission (Security Level >= 2).
    requireAdminWriteAccess();

    $submittedToken = (string) ($_POST['csrf_token'] ?? '');
    $expectedToken = (string) ($_SESSION['department_admin_csrf'] ?? '');
    if ($expectedToken === '' || !hash_equals($expectedToken, $submittedToken)) {
        throw new DomainException('Your form expired or the security token was invalid. Refresh the page and try again.');
    }

    //Validate the requested action and department ID.
    if (!in_array($action, ['create', 'update', 'delete'], true)) {
        throw new DomainException('Invalid department action.');
    }
    if ($departmentId === false || $departmentId === null) {
        throw new DomainException('Please provide a valid positive Department ID.');
    }

    //Create or update the department record.
    if ($action === 'create' || $action === 'update') {
        $name = trim((string) ($_POST['department_name'] ?? ''));
        $email = trim((string) ($_POST['department_email'] ?? ''));
        $phone = trim((string) ($_POST['department_phone'] ?? ''));
        $office = trim((string) ($_POST['office_id'] ?? ''));
        $chairRaw = trim((string) ($_POST['chair_id'] ?? ''));

        if ($name === '' || strlen($name) > 100) {
            throw new DomainException('Department name must be between 1 and 100 characters.');
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 100) {
            throw new DomainException('Enter a valid department email (100 characters maximum).');
        }
        if ($phone === '' || strlen($phone) > 15) {
            throw new DomainException('Phone number must be between 1 and 15 characters.');
        }
        if (strlen($office) > 15) {
            throw new DomainException('Invalid office ID.');
        }
        $officeId = $office === '' ? null : $office;
        $chairId = null;
        if ($chairRaw !== '') {
            $chairId = filter_var($chairRaw, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if ($chairId === false) {
                throw new DomainException('Invalid faculty ID for department chair.');
            }
        }

        if ($action === 'create') {
            $stmt = $conn->prepare('SELECT 1 FROM DEPARTMENT WHERE Department_ID = ?');
            $stmt->bind_param('i', $departmentId);
            $stmt->execute();
            $alreadyExists = $stmt->get_result()->fetch_row() !== null;
            $stmt->close();
            if ($alreadyExists) {
                throw new DomainException('That Department ID is already in use. Choose a different ID.');
            }
            $stmt = $conn->prepare('INSERT INTO DEPARTMENT
                (Department_ID, Department_Name, Department_Email, Department_Phone, Office_ID, Chair_ID)
                VALUES (?, ?, ?, ?, ?, ?)');
            $stmt->bind_param('issssi', $departmentId, $name, $email, $phone, $officeId, $chairId);
            $stmt->execute();
            $stmt->close();
            $success = "Department “{$name}” created successfully.";
        } else {
            $stmt = $conn->prepare('UPDATE DEPARTMENT
                SET Department_Name = ?, Department_Email = ?, Department_Phone = ?, Office_ID = ?, Chair_ID = ?
                WHERE Department_ID = ?');
            $stmt->bind_param('ssssii', $name, $email, $phone, $officeId, $chairId, $departmentId);
            $stmt->execute();
            $stmt->close();
            // MySQL affected_rows is 0 when data did not change: check existence separately.
            $stmt = $conn->prepare('SELECT 1 FROM DEPARTMENT WHERE Department_ID = ?');
            $stmt->bind_param('i', $departmentId);
            $stmt->execute();
            $exists = $stmt->get_result()->fetch_row() !== null;
            $stmt->close();
            if (!$exists) {
                throw new DomainException('That department does not exist.');
            }
            $success = "Department “{$name}” updated successfully.";
        }
    } else {
        if (($_POST['delete_confirmation'] ?? '') !== 'DELETE') {
            throw new DomainException('To delete a department, type DELETE exactly as shown.');
        }

        //Lock the department before checking for dependent records.
        $conn->begin_transaction();
        $transactionActive = true;
        $stmt = $conn->prepare('SELECT Department_Name FROM DEPARTMENT WHERE Department_ID = ? FOR UPDATE');
        $stmt->bind_param('i', $departmentId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$row) {
            throw new DomainException('The selected department no longer exists.');
        }

        // DEPARTMENT has ON DELETE CASCADE children in the university schema.
        // Never silently remove their majors, minors, courses, or related records.
        // Prevent cascading deletes of related academic and personnel records.
        $dependencies = [
            'majors' => ['MAJORS', 'Department_ID'],
            'minors' => ['MINORS', 'Department_ID'],
            'courses' => ['COURSES', 'Department_ID'],
            'student assignments' => ['STUDENT', 'Dept_ID'],
            'faculty assignments' => ['DEPARTMENT_ROSTER', 'Department_ID'],
            'department managers' => ['DEPARTMENT_MANAGERS', 'Department_ID'],
        ];
        $blocking = [];
        foreach ($dependencies as $label => [$table, $column]) {
            // Identifiers above are static constants defined in the code, not user input.
            $stmt = $conn->prepare("SELECT COUNT(*) AS total FROM `$table` WHERE `$column` = ?");
            $stmt->bind_param('i', $departmentId);
            $stmt->execute();
            $count = (int)$stmt->get_result()->fetch_assoc()['total'];
            $stmt->close();
            if ($count > 0) {
                $blocking[] = "$label ($count)";
            }
        }
        if ($blocking) {
            throw new DomainException('Cannot delete this department while it has linked records: ' . implode(', ', $blocking) . '. Reassign or remove those records first.');
        }

        $stmt = $conn->prepare('DELETE FROM DEPARTMENT WHERE Department_ID = ?');
        $stmt->bind_param('i', $departmentId);
        $stmt->execute();
        $stmt->close();
        $conn->commit();
        $transactionActive = false;
        $success = 'Department “' . $row['Department_Name'] . '” deleted successfully.';
    }

    $_SESSION['department_admin_flash'] = ['type' => 'success', 'message' => $success];
} catch (Throwable $e) {
    if ($transactionActive) {
        try {
            $conn->rollback();
        } catch (Throwable $ignored) {
            error_log('Failed rolling back department transaction: ' . $ignored->getMessage());
        }
    }
    if ($e instanceof DomainException) {
        $error = $e->getMessage();
    } else {
        error_log('Department CRUD failed: ' . $e->getMessage());
        $error = 'The operation could not be completed. Check that the department information is valid and any selected office or chair exists.';
    }
    $_SESSION['department_admin_flash'] = ['type' => 'danger', 'message' => $error];
}

header("Location: " . $redirectUrl, true, 303);
exit;
