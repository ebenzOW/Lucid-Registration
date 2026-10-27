<?php
/**
 * Lucid University Course Registration System
 * Backend POST Handler: Register Course-Section for Spring 2027 (Semester_ID = 202701)
 *
 * Enforces all 8 business rules with prepared statements, strict parameter binding,
 * and pessimistic row locking for concurrency safety.
 */
require_once 'student_guard.php';
$student_id = (int)$userID;

// Validate Request Method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['flash_error'] = "Invalid request method.";
    header("Location: register.php");
    exit;
}

// Validate CRN input
if (!isset($_POST['crn']) || empty($_POST['crn'])) {
    $_SESSION['flash_error'] = "Please select a course section to register.";
    header("Location: register.php");
    exit;
}

$crn = filter_var($_POST['crn'], FILTER_VALIDATE_INT);
if ($crn === false || $crn <= 0) {
    $_SESSION['flash_error'] = "Invalid CRN provided.";
    header("Location: register.php");
    exit;
}

// Relative Path Database Connection Handling
if (file_exists(__DIR__ . '/../dblogin.php')) {
    require_once __DIR__ . '/../dblogin.php';
}

if (!isset($conn) || !($conn instanceof mysqli)) {
    if (isset($hostname) && isset($username) && isset($db)) {
        $conn = new mysqli($hostname, $username, $password ?? '', $db);
    } elseif (file_exists(__DIR__ . '/../db_connect.php')) {
        require_once __DIR__ . '/../db_connect.php';
    }
}

if (!isset($conn) || $conn->connect_error) {
    $_SESSION['flash_error'] = "Database connection error. Please try again later.";
    header("Location: register.php");
    exit;
}

// -----------------------------------------------------------------------------
// Step 1: Target Course & Section Retrieval
// -----------------------------------------------------------------------------
$secStmt = $conn->prepare("
    SELECT cs.CRN, cs.Course_ID, cs.Section_Number, cs.Semester_ID, cs.Available_seats,
           cs.Time_Slot_ID, ts.Period_ID, c.Course_Type, c.Course_Credits, c.Course_Name
    FROM COURSE_SECTIONS cs
    JOIN COURSES c ON cs.Course_ID = c.Course_ID
    JOIN TIME_SLOT ts ON cs.Time_Slot_ID = ts.Time_Slot_ID
    WHERE cs.CRN = ?
");
$secStmt->bind_param("i", $crn);
$secStmt->execute();
$secRes = $secStmt->get_result();
$target_section = $secRes->fetch_assoc();
$secStmt->close();

if (!$target_section) {
    $_SESSION['flash_error'] = "Course section not found.";
    header("Location: register.php");
    exit;
}

$semester_id     = (int)$target_section['Semester_ID'];
$course_id       = $target_section['Course_ID'];
$course_type     = $target_section['Course_Type'];
$course_credits  = (int)$target_section['Course_Credits'];
$time_slot_id    = (int)$target_section['Time_Slot_ID'];
$period_id       = (int)$target_section['Period_ID'];
$available_seats = (int)$target_section['Available_seats'];
$course_name     = $target_section['Course_Name'];

// -----------------------------------------------------------------------------
// Rule 1: Semester Existence & Registration Window Validation
// -----------------------------------------------------------------------------
// 1a: Verify that course_sections for the chosen semester exist
$secExistsStmt = $conn->prepare("SELECT 1 FROM COURSE_SECTIONS WHERE Semester_ID = ? LIMIT 1");
$secExistsStmt->bind_param("i", $semester_id);
$secExistsStmt->execute();
$secExistsRes = $secExistsStmt->get_result();
$hasSections = $secExistsRes->fetch_assoc();
$secExistsStmt->close();

if (!$hasSections) {
    $_SESSION['flash_error'] = "No course sections exist for the selected semester.";
    header("Location: register.php");
    exit;
}

// 1b: Verify that registration for that selected semester is not past Last_Day_Of_Registration
$winStmt = $conn->prepare("
    SELECT ir.Last_Day_Of_Registration, 
           (CURRENT_DATE() <= ir.Last_Day_Of_Registration) AS is_open,
           s.Semester_Name, s.Semester_Year
    FROM INFORMATION_RELATION ir
    JOIN SEMESTER s ON ir.Semester_ID = s.Semester_ID
    WHERE ir.Semester_ID = ?
");
$winStmt->bind_param("i", $semester_id);
$winStmt->execute();
$winRes = $winStmt->get_result();
$winRow = $winRes->fetch_assoc();
$winStmt->close();

if (!$winRow || !(int)$winRow['is_open']) {
    $semLabel = $winRow ? ($winRow['Semester_Name'] . ' ' . $winRow['Semester_Year']) : "the selected semester";
    $_SESSION['flash_error'] = "Registration for " . $semLabel . " is currently closed.";
    header("Location: register.php");
    exit;
}

// -----------------------------------------------------------------------------
// Rule 2: Account Holds Check (STUDENT_HOLDS & HOLDS)
// -----------------------------------------------------------------------------
$holdStmt = $conn->prepare("
    SELECT h.Hold_Type
    FROM STUDENT_HOLDS sh
    JOIN HOLDS h ON sh.Hold_ID = h.Hold_ID
    WHERE sh.Student_ID = ?
");
$holdStmt->bind_param("i", $student_id);
$holdStmt->execute();
$holdRes = $holdStmt->get_result();
$activeHolds = [];
while ($hRow = $holdRes->fetch_assoc()) {
    $activeHolds[] = $hRow['Hold_Type'];
}
$holdStmt->close();

if (!empty($activeHolds)) {
    $_SESSION['flash_error'] = "Registration blocked. You have active account holds: " . implode(', ', $activeHolds) . ".";
    header("Location: register.php");
    exit;
}

// -----------------------------------------------------------------------------
// Rule 3: Academic Level Restriction (COURSES.Course_Type vs STUDENT.Student_Type)
// -----------------------------------------------------------------------------
$typeStmt = $conn->prepare("SELECT Student_Type FROM STUDENT WHERE Student_ID = ?");
$typeStmt->bind_param("i", $student_id);
$typeStmt->execute();
$typeRes = $typeStmt->get_result();
$studentRow = $typeRes->fetch_assoc();
$typeStmt->close();

if (!$studentRow) {
    $_SESSION['flash_error'] = "Student record not found.";
    header("Location: register.php");
    exit;
}

$student_type = trim($studentRow['Student_Type']);

if (strcasecmp($student_type, 'Undergraduate') === 0 && strcasecmp($course_type, 'Graduate') === 0) {
    $_SESSION['flash_error'] = "Undergraduate students cannot register for graduate courses.";
    header("Location: register.php");
    exit;
}

if (strcasecmp($student_type, 'Graduate') === 0 && strcasecmp($course_type, 'Undergraduate') === 0) {
    $_SESSION['flash_error'] = "Graduate students cannot register for undergraduate courses.";
    header("Location: register.php");
    exit;
}

// -----------------------------------------------------------------------------
// Rule 4: Repeat Course & Prior Completion Check
// -----------------------------------------------------------------------------
// Check 4a: Student already enrolled in this exact CRN
$alreadyCrnStmt = $conn->prepare("SELECT 1 FROM ENROLLMENT WHERE CRN = ? AND Student_ID = ?");
$alreadyCrnStmt->bind_param("ii", $crn, $student_id);
$alreadyCrnStmt->execute();
$alreadyCrnRes = $alreadyCrnStmt->get_result();
$alreadyInThisCrn = $alreadyCrnRes->fetch_assoc();
$alreadyCrnStmt->close();

if ($alreadyInThisCrn) {
    $_SESSION['flash_error'] = "You are already registered for this section.";
    header("Location: register.php");
    exit;
}

// Check 4b: Student already enrolled in any section of the same Course_ID in this semester
$alreadyCourseStmt = $conn->prepare("
    SELECT cs.CRN, cs.Section_Number
    FROM ENROLLMENT e
    JOIN COURSE_SECTIONS cs ON e.CRN = cs.CRN
    WHERE e.Student_ID = ? AND cs.Course_ID = ? AND cs.Semester_ID = ?
    LIMIT 1
");
$alreadyCourseStmt->bind_param("isi", $student_id, $course_id, $semester_id);
$alreadyCourseStmt->execute();
$alreadyCourseRes = $alreadyCourseStmt->get_result();
$alreadyInCourse = $alreadyCourseRes->fetch_assoc();
$alreadyCourseStmt->close();

if ($alreadyInCourse) {
    $_SESSION['flash_error'] = "You are already enrolled in a section of " . $course_id . " for this semester (CRN: " . $alreadyInCourse['CRN'] . ").";
    header("Location: register.php");
    exit;
}

// Check 4c: Student completed and passed Course_ID previously in STUDENT_HISTORY
$historyStmt = $conn->prepare("
    SELECT Grade
    FROM STUDENT_HISTORY
    WHERE Student_ID = ? AND Course_ID = ? AND Grade IN ('A', 'A-', 'B+', 'B', 'B-', 'C+', 'C', 'D+', 'D', 'P')
    LIMIT 1
");
$historyStmt->bind_param("is", $student_id, $course_id);
$historyStmt->execute();
$historyRes = $historyStmt->get_result();
$passedPrior = $historyRes->fetch_assoc();
$historyStmt->close();

if ($passedPrior) {
    $_SESSION['flash_error'] = "You have already completed and passed this course.";
    header("Location: register.php");
    exit;
}

// -----------------------------------------------------------------------------
// Rule 5: Prerequisites Check (Historical + Conditional In-Progress)
// -----------------------------------------------------------------------------
$prereqStmt = $conn->prepare("SELECT Prerequisite_Course_ID FROM COURSE_PREREQUISITE WHERE Course_ID = ?");
$prereqStmt->bind_param("s", $course_id);
$prereqStmt->execute();
$prereqRes = $prereqStmt->get_result();
$prerequisites = [];
while ($pRow = $prereqRes->fetch_assoc()) {
    $prerequisites[] = $pRow['Prerequisite_Course_ID'];
}
$prereqStmt->close();

if (!empty($prerequisites)) {
    // Undergrad passing grades: A through C ('A', 'A-', 'B+', 'B', 'B-', 'C+', 'C')
    // Grad passing grades: A through B ('A', 'A-', 'B+', 'B', 'B-')
    $isGrad = (strcasecmp($student_type, 'Graduate') === 0);
    $passingGrades = $isGrad 
        ? ["'A'", "'A-'", "'B+'", "'B'", "'B-'"] 
        : ["'A'", "'A-'", "'B+'", "'B'", "'B-'", "'C+'", "'C'"];
    $passingGradesSql = implode(',', $passingGrades);

    foreach ($prerequisites as $prereqId) {
        $satisfied = false;

        // a) Check historical completion in STUDENT_HISTORY with a passing grade
        $hCheckStmt = $conn->prepare("
            SELECT 1
            FROM STUDENT_HISTORY
            WHERE Student_ID = ? AND Course_ID = ? AND Grade IN ($passingGradesSql)
            LIMIT 1
        ");
        $hCheckStmt->bind_param("is", $student_id, $prereqId);
        $hCheckStmt->execute();
        $hRes = $hCheckStmt->get_result();
        if ($hRes->fetch_assoc()) {
            $satisfied = true;
        }
        $hCheckStmt->close();

        // b) If not satisfied in history, check conditionally in-progress in ENROLLMENT for Fall 2026 (Semester_ID = 202608)
        if (!$satisfied) {
            $ipCheckStmt = $conn->prepare("
                SELECT 1
                FROM ENROLLMENT e
                JOIN COURSE_SECTIONS cs ON e.CRN = cs.CRN
                WHERE e.Student_ID = ? AND cs.Course_ID = ? AND cs.Semester_ID = 202608
                LIMIT 1
            ");
            $ipCheckStmt->bind_param("is", $student_id, $prereqId);
            $ipCheckStmt->execute();
            $ipRes = $ipCheckStmt->get_result();
            if ($ipRes->fetch_assoc()) {
                $satisfied = true;
            }
            $ipCheckStmt->close();
        }

        if (!$satisfied) {
            $_SESSION['flash_error'] = "Prerequisite not met: " . $prereqId . " is required.";
            header("Location: register.php");
            exit;
        }
    }
}

// -----------------------------------------------------------------------------
// Rule 6: Semester Credit Limits
// -----------------------------------------------------------------------------
$statStmt = $conn->prepare("
    SELECT COALESCE(u.Undergrad_Type, g.Grad_Type, 'Full-Time') AS Attendance_Type
    FROM STUDENT s
    LEFT JOIN UNDERGRADUATE_STUDENT u ON s.Student_ID = u.Student_ID
    LEFT JOIN GRADUATE_STUDENT g ON s.Student_ID = g.Student_ID
    WHERE s.Student_ID = ?
");
$statStmt->bind_param("i", $student_id);
$statStmt->execute();
$statRes = $statStmt->get_result();
$statRow = $statRes->fetch_assoc();
$statStmt->close();

$attendance_type = $statRow['Attendance_Type'] ?? 'Full-Time';
$max_allowed = (strcasecmp($attendance_type, 'Part-Time') === 0) ? 8 : 16;

$credStmt = $conn->prepare("
    SELECT COALESCE(SUM(c.Course_Credits), 0) AS total_enrolled
    FROM ENROLLMENT e
    JOIN COURSE_SECTIONS cs ON e.CRN = cs.CRN
    JOIN COURSES c ON cs.Course_ID = c.Course_ID
    WHERE e.Student_ID = ? AND cs.Semester_ID = ?
");
$credStmt->bind_param("ii", $student_id, $semester_id);
$credStmt->execute();
$credRes = $credStmt->get_result();
$credRow = $credRes->fetch_assoc();
$credStmt->close();

$total_enrolled = (int)($credRow['total_enrolled'] ?? 0);

if ($total_enrolled + $course_credits > $max_allowed) {
    $_SESSION['flash_error'] = "Adding this course exceeds your limit of " . $max_allowed . " credits (Current: " . $total_enrolled . ", Course: " . $course_credits . ").";
    header("Location: register.php");
    exit;
}

// -----------------------------------------------------------------------------
// Rule 7: Time-Slot Conflict Detection
// -----------------------------------------------------------------------------
$confStmt = $conn->prepare("
    SELECT cs.CRN, c.Course_Name
    FROM ENROLLMENT e
    JOIN COURSE_SECTIONS cs ON e.CRN = cs.CRN
    JOIN COURSES c ON cs.Course_ID = c.Course_ID
    JOIN TIME_SLOT ts ON cs.Time_Slot_ID = ts.Time_Slot_ID
    JOIN TIME_SLOT_DAY tsd ON ts.Time_Slot_ID = tsd.Time_Slot_ID
    WHERE e.Student_ID = ?
      AND cs.Semester_ID = ?
      AND ts.Period_ID = ?
      AND tsd.Day_ID IN (SELECT Day_ID FROM TIME_SLOT_DAY WHERE Time_Slot_ID = ?)
    LIMIT 1
");
$confStmt->bind_param("iiii", $student_id, $semester_id, $period_id, $time_slot_id);
$confStmt->execute();
$confRes = $confStmt->get_result();
$conflict = $confRes->fetch_assoc();
$confStmt->close();

if ($conflict) {
    $_SESSION['flash_error'] = "Schedule conflict: Overlaps with " . $conflict['Course_Name'] . " (CRN: " . $conflict['CRN'] . ").";
    header("Location: register.php");
    exit;
}

// -----------------------------------------------------------------------------
// Rule 8: Concurrency Row Lock & Atomic Registration
// -----------------------------------------------------------------------------
try {
    $conn->begin_transaction();

    // Lock target section row for pessimistic concurrency control
    $lockStmt = $conn->prepare("SELECT Available_seats FROM COURSE_SECTIONS WHERE CRN = ? FOR UPDATE");
    $lockStmt->bind_param("i", $crn);
    $lockStmt->execute();
    $lockRes = $lockStmt->get_result();
    $sectionRow = $lockRes->fetch_assoc();
    $lockStmt->close();

    if (!$sectionRow || (int)$sectionRow['Available_seats'] <= 0) {
        $conn->rollback();
        $_SESSION['flash_error'] = "Course section is full (0 available seats).";
        header("Location: register.php");
        exit;
    }


    //MAKING SURE COURSE IS ONE OF STUDENTS MAJORS OR MINORS
    // Check that the course belongs to the student's declared major(s) or minor
    $allowed_query = "
        SELECT 1 
        FROM COURSES c
        WHERE c.Course_ID = ?
        AND (
            c.Department_ID IN (
                SELECT m.Department_ID 
                FROM STUDENT_MAJORS sm 
                JOIN MAJORS m ON sm.Major_ID = m.Major_ID 
                WHERE sm.Student_ID = ?
            )
            OR c.Department_ID IN (
                SELECT mn.Department_ID 
                FROM STUDENT_MINORS smn 
                JOIN MINORS mn ON smn.Minor_ID = mn.Minor_ID 
                WHERE smn.Student_ID = ?
            )
        )
        LIMIT 1
    ";

    $stmt = $conn->prepare($allowed_query);
    $stmt->bind_param("sii", $course_id, $student_id, $student_id);
    $stmt->execute();
    $is_allowed = $stmt->get_result()->fetch_row();
    $stmt->close();

    if (!$is_allowed) {
        $_SESSION['flash_error'] = "Registration blocked: You may only register for courses within your declared major(s) or minor.";
        header("Location: register.php");
        exit;
    }

    // Insert into ENROLLMENT
    $enrollStmt = $conn->prepare("INSERT INTO ENROLLMENT (CRN, Student_ID, Grade) VALUES (?, ?, NULL)");
    $enrollStmt->bind_param("ii", $crn, $student_id);
    $enrollStmt->execute();
    $enrollStmt->close();

    // Decrement Available_seats in COURSE_SECTIONS
    $updateStmt = $conn->prepare("UPDATE COURSE_SECTIONS SET Available_seats = Available_seats - 1 WHERE CRN = ?");
    $updateStmt->bind_param("i", $crn);
    $updateStmt->execute();
    $updateStmt->close();

    // Commit transaction atomically
    $conn->commit();

    $_SESSION['flash_success'] = "Successfully registered for " . $course_id . " (CRN: " . $crn . ")!";
    header("Location: register.php");
    exit;

} catch (Exception $e) {
    if ($conn) {
        $conn->rollback();
    }
    $_SESSION['flash_error'] = "Registration failed due to a system error: " . $e->getMessage();
    header("Location: register.php");
    exit;
}
