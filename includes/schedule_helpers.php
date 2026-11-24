<?php
/**
 * Lucid University - Master Schedule & Course Helpers
 */

/**
 * Fetch detailed semester metadata including registration and drop windows.
 */
function getSemesterInfo(mysqli $conn, int $semester_id): ?array {
    $stmt = $conn->prepare("
        SELECT s.Semester_ID, s.Semester_Name, s.Semester_Year, s.Start_Date, s.End_Date,
               ir.Last_Day_Of_Registration, ir.Last_Day_Of_Dropping_Course,
               ir.Date_Of_Midterm_Grade_Recording, ir.Date_Of_Final_Grades_Recording,
               (CURRENT_DATE() <= ir.Last_Day_Of_Registration) AS is_reg_open,
               (CURRENT_DATE() <= ir.Last_Day_Of_Dropping_Course) AS is_drop_open
        FROM SEMESTER s
        LEFT JOIN INFORMATION_RELATION ir ON s.Semester_ID = ir.Semester_ID
        WHERE s.Semester_ID = ?
    ");
    $stmt->bind_param("i", $semester_id);
    $stmt->execute();
    $info = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $info;
}

/**
 * Fetch dropdown options for Master Schedule search: semesters, departments, and time slots.
 */
function getMasterScheduleFilterOptions(mysqli $conn): array {
    // 1. Semesters with existing course sections
    $semStmt = $conn->prepare("
        SELECT DISTINCT s.Semester_ID, s.Semester_Name, s.Semester_Year
        FROM SEMESTER s
        JOIN COURSE_SECTIONS cs ON s.Semester_ID = cs.Semester_ID
        ORDER BY s.Semester_ID DESC
    ");
    $semStmt->execute();
    $semRes = $semStmt->get_result();
    $semesters = [];
    while ($row = $semRes->fetch_assoc()) {
        $semesters[] = $row;
    }
    $semStmt->close();

    // 2. All academic departments
    $deptStmt = $conn->prepare("SELECT Department_ID, Department_Name FROM DEPARTMENT ORDER BY Department_Name ASC");
    $deptStmt->execute();
    $deptRes = $deptStmt->get_result();
    $departments = [];
    while ($row = $deptRes->fetch_assoc()) {
        $departments[] = $row;
    }
    $deptStmt->close();

    // 3. Time slots actively used in COURSE_SECTIONS
    $tsStmt = $conn->prepare("
        SELECT DISTINCT ts.Time_Slot_ID, ts.Time_Slot_Day, p.Start_Time, p.End_Time
        FROM TIME_SLOT ts
        JOIN PERIOD p ON ts.Period_ID = p.Period_ID
        JOIN COURSE_SECTIONS cs ON ts.Time_Slot_ID = cs.Time_Slot_ID
        ORDER BY ts.Time_Slot_ID ASC
    ");
    $tsStmt->execute();
    $tsRes = $tsStmt->get_result();
    $timeSlots = [];
    while ($row = $tsRes->fetch_assoc()) {
        $timeSlots[] = $row;
    }
    $tsStmt->close();

    return [
        'semesters'   => $semesters,
        'departments' => $departments,
        'time_slots'  => $timeSlots,
    ];
}

/**
 * Return an associative array of course prerequisites keyed by Course_ID for the given list of course IDs.
 */
function getPrerequisitesForCourses(mysqli $conn, array $courseIds): array {
    $uniqueCourseIds = array_values(array_unique(array_filter($courseIds)));
    if (empty($uniqueCourseIds)) {
        return [];
    }

    $placeholders = implode(',', array_fill(0, count($uniqueCourseIds), '?'));
    $prereqTypes = str_repeat('s', count($uniqueCourseIds));
    $pStmt = $conn->prepare("
        SELECT cp.Course_ID, cp.Prerequisite_Course_ID, c.Course_Name AS Prerequisite_Course_Name
        FROM COURSE_PREREQUISITE cp
        JOIN COURSES c ON cp.Prerequisite_Course_ID = c.Course_ID
        WHERE cp.Course_ID IN ($placeholders)
        ORDER BY cp.Course_ID ASC, cp.Prerequisite_Course_ID ASC
    ");
    $pStmt->bind_param($prereqTypes, ...$uniqueCourseIds);
    $pStmt->execute();
    $pRes = $pStmt->get_result();
    $prereqMap = [];
    while ($row = $pRes->fetch_assoc()) {
        $cid = $row['Course_ID'];
        if (!isset($prereqMap[$cid])) {
            $prereqMap[$cid] = [];
        }
        $prereqMap[$cid][] = [
            'Course_ID'   => $row['Prerequisite_Course_ID'],
            'Course_Name' => $row['Prerequisite_Course_Name']
        ];
    }
    $pStmt->close();

    return $prereqMap;
}

/**
 * Iterative BFS over COURSE_PREREQUISITE starting at $candidate_prereq_id
 * to check if $course_id is reachable (prevents both direct and transitive circular prerequisites).
 */
function hasPrerequisiteCycle(mysqli $conn, string $course_id, string $candidate_prereq_id): bool {
    $course_id = trim($course_id);
    $candidate_prereq_id = trim($candidate_prereq_id);

    if ($course_id === '' || $candidate_prereq_id === '') {
        return false;
    }
    if ($course_id === $candidate_prereq_id) {
        return true;
    }

    $queue = [$candidate_prereq_id];
    $visited = [$candidate_prereq_id => true];

    $stmt = $conn->prepare("SELECT Prerequisite_Course_ID FROM COURSE_PREREQUISITE WHERE Course_ID = ?");

    while (!empty($queue)) {
        $curr = array_shift($queue);
        $stmt->bind_param("s", $curr);
        $stmt->execute();
        $res = $stmt->get_result();

        while ($row = $res->fetch_assoc()) {
            $next = trim($row['Prerequisite_Course_ID']);
            if ($next === $course_id) {
                $stmt->close();
                return true;
            }
            if (!isset($visited[$next])) {
                $visited[$next] = true;
                $queue[] = $next;
            }
        }
    }
    $stmt->close();

    return false;
}

/**
 * Search course sections in COURSE_SECTIONS using dynamic parameterized filters.
 */
function searchMasterSchedule(mysqli $conn, array $filters = []): array {
    $where = [];
    $params = [];
    $types = "";

    // 1. semester_id
    $semesterId = !empty($filters['semester_id']) ? (int)$filters['semester_id'] : 202608;
    $where[] = "cs.Semester_ID = ?";
    $params[] = $semesterId;
    $types .= "i";

    // 2. crn
    if (!empty($filters['crn'])) {
        $where[] = "cs.CRN = ?";
        $params[] = (int)$filters['crn'];
        $types .= "i";
    }

    // 3. dept
    if (!empty($filters['dept'])) {
        $where[] = "c.Department_ID = ?";
        $params[] = (int)$filters['dept'];
        $types .= "i";
    }

    // 4. search
    if (isset($filters['search']) && trim($filters['search']) !== '') {
        $where[] = "(c.Course_ID LIKE ? OR c.Course_Name LIKE ?)";
        $term = '%' . trim($filters['search']) . '%';
        $params[] = $term;
        $params[] = $term;
        $types .= "ss";
    }

    // 5. section
    if (isset($filters['section']) && $filters['section'] !== '' && is_numeric($filters['section'])) {
        $where[] = "cs.Section_Number = ?";
        $params[] = (int)$filters['section'];
        $types .= "i";
    }

    // 6. time_slot_id
    if (!empty($filters['time_slot_id'])) {
        $where[] = "cs.Time_Slot_ID = ?";
        $params[] = (int)$filters['time_slot_id'];
        $types .= "i";
    }

    // 7. instructor
    if (isset($filters['instructor']) && trim($filters['instructor']) !== '') {
        $where[] = "(CONCAT(u_fac.User_FName, ' ', u_fac.User_LName) LIKE ? OR u_fac.User_LName LIKE ?)";
        $term = '%' . trim($filters['instructor']) . '%';
        $params[] = $term;
        $params[] = $term;
        $types .= "ss";
    }

    // 8. room
    if (isset($filters['room']) && trim($filters['room']) !== '') {
        $where[] = "(CONCAT(b.Building_Name, ' ', r.Room_NO) LIKE ? OR cs.Room_ID LIKE ?)";
        $term = '%' . trim($filters['room']) . '%';
        $params[] = $term;
        $params[] = $term;
        $types .= "ss";
    }

    // 9. credits
    if (isset($filters['credits']) && $filters['credits'] !== '' && is_numeric($filters['credits'])) {
        $where[] = "c.Course_Credits = ?";
        $params[] = (int)$filters['credits'];
        $types .= "i";
    }

    // 10. open_only
    if (!empty($filters['open_only'])) {
        $where[] = "cs.Available_seats > 0";
    }

    // 11. status filtering:
    // If !empty($filters['status']) && $filters['status'] !== 'All' ('Active', 'Past', 'Cancelled'), filter COALESCE(cs.Status, 'Active') = ?
    // Else if empty($filters['include_cancelled']), filter COALESCE(cs.Status, 'Active') <> 'Cancelled'
    // Admins pass 'include_cancelled' => true so 'All' shows 'Active', 'Past', and 'Cancelled'
    if (!empty($filters['status']) && $filters['status'] !== 'All') {
        $where[] = "COALESCE(cs.Status, 'Active') = ?";
        $params[] = $filters['status'];
        $types .= "s";
    } elseif (empty($filters['include_cancelled'])) {
        $where[] = "COALESCE(cs.Status, 'Active') <> 'Cancelled'";
    }

    $sql = "
        SELECT cs.CRN, cs.Course_ID, cs.Section_Number, cs.Available_seats, cs.Semester_ID,
               COALESCE(cs.Status, 'Active') AS Status, cs.Faculty_ID,
               c.Course_Name, c.Course_Credits, c.Course_Type, c.Course_Description, c.Department_ID,
               dept.Department_Name,
               cs.Time_Slot_ID, ts.Time_Slot_Day, p.Start_Time, p.End_Time,
               cs.Room_ID, b.Building_Name, r.Room_NO, r.Room_Type,
               s.Semester_Name, s.Semester_Year, s.Start_Date, s.End_Date,
               f.Rank AS Instructor_Rank, l_fac.User_Email AS Instructor_Email,
               COALESCE(CONCAT(u_fac.User_FName, ' ', u_fac.User_LName), 'Staff') AS Instructor_Name,
               CASE 
                   WHEN cs.Semester_ID < 202608 THEN (SELECT COUNT(*) FROM STUDENT_HISTORY sh WHERE sh.CRN = cs.CRN)
                   ELSE (SELECT COUNT(*) FROM ENROLLMENT e WHERE e.CRN = cs.CRN)
               END AS Enrolled_Count
        FROM COURSE_SECTIONS cs
        JOIN COURSES c ON cs.Course_ID = c.Course_ID
        LEFT JOIN DEPARTMENT dept ON c.Department_ID = dept.Department_ID
        JOIN TIME_SLOT ts ON cs.Time_Slot_ID = ts.Time_Slot_ID
        JOIN PERIOD p ON ts.Period_ID = p.Period_ID
        JOIN ROOM r ON cs.Room_ID = r.Room_ID
        JOIN BUILDING b ON r.Building_ID = b.Building_ID
        LEFT JOIN SEMESTER s ON cs.Semester_ID = s.Semester_ID
        LEFT JOIN FACULTY f ON cs.Faculty_ID = f.Faculty_ID
        LEFT JOIN `USER` u_fac ON f.Faculty_ID = u_fac.User_ID
        LEFT JOIN LOGIN l_fac ON u_fac.User_ID = l_fac.User_ID
        WHERE " . implode(" AND ", $where) . "
        ORDER BY c.Course_ID ASC, cs.Section_Number ASC
    ";

    $stmt = $conn->prepare($sql);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $res = $stmt->get_result();

    $results = [];
    while ($row = $res->fetch_assoc()) {
        $row['Allowed_Seats'] = (int)$row['Enrolled_Count'] + (int)$row['Available_seats'];
        $results[] = $row;
    }
    $stmt->close();

    if (empty($results)) {
        return [];
    }

    $uniqueCourseIds = array_values(array_unique(array_column($results, 'Course_ID')));
    $prereqMap = getPrerequisitesForCourses($conn, $uniqueCourseIds);

    foreach ($results as &$secRow) {
        $cid = $secRow['Course_ID'];
        $secRow['prerequisites'] = $prereqMap[$cid] ?? [];
    }
    unset($secRow);

    return $results;
}

/**
 * Check if the current time falls within the grade submission window (default 72 hours).
 */
function isWithinGradeWindow($targetDateStr, $hours = 72): bool {
    if (empty($targetDateStr)) {
        return false;
    }
    $datePart = substr(trim((string)$targetDateStr), 0, 10);
    $startTs = strtotime($datePart . ' 00:00:00');
    if ($startTs === false) {
        return false;
    }
    $endTs = $startTs + ((int)$hours * 3600);
    $now = time();
    return ($now >= $startTs && $now <= $endTs);
}

/**
 * Retrieve scheduled class meeting dates and live session status for a course section.
 */
function getSectionMeetingDates(mysqli $conn, int $crn): array {
    $crn = (int)$crn;
    $stmt = $conn->prepare("
        SELECT s.Start_Date, s.End_Date, p.Start_Time, p.End_Time, d.Weekday
        FROM COURSE_SECTIONS cs
        JOIN SEMESTER s ON cs.Semester_ID = s.Semester_ID
        JOIN TIME_SLOT ts ON cs.Time_Slot_ID = ts.Time_Slot_ID
        JOIN PERIOD p ON ts.Period_ID = p.Period_ID
        JOIN TIME_SLOT_DAY tsd ON ts.Time_Slot_ID = tsd.Time_Slot_ID
        JOIN DAY d ON tsd.Day_ID = d.Day_ID
        WHERE cs.CRN = ?
    ");
    $stmt->bind_param("i", $crn);
    $stmt->execute();
    $res = $stmt->get_result();

    $startDateStr = null;
    $endDateStr = null;
    $startTime = null;
    $endTime = null;
    $weekdays = [];

    while ($row = $res->fetch_assoc()) {
        $startDateStr = $row['Start_Date'];
        $endDateStr = $row['End_Date'];
        $startTime = $row['Start_Time'];
        $endTime = $row['End_Time'];
        $weekdays[] = ucfirst(strtolower(trim($row['Weekday'])));
    }
    $stmt->close();

    $weekdays = array_unique($weekdays);

    if (empty($startDateStr) || empty($endDateStr) || empty($weekdays)) {
        return [
            'dates'       => [],
            'start_time'  => $startTime,
            'end_time'    => $endTime,
            'is_live_now' => false,
        ];
    }

    $dates = [];
    $today = date('Y-m-d');
    $nowTime = date('H:i:s');
    $isTodayInDates = false;

    try {
        $startDate = new DateTime($startDateStr);
        $endDate = new DateTime($endDateStr);
        $endDate->modify('+1 day'); // inclusive
        $interval = new DateInterval('P1D');
        $period = new DatePeriod($startDate, $interval, $endDate);

        foreach ($period as $dt) {
            $weekday = $dt->format('l'); // Monday, Tuesday, etc.
            if (in_array($weekday, $weekdays, true)) {
                $dateStr = $dt->format('Y-m-d');
                if ($dateStr === $today) {
                    $isTodayInDates = true;
                }
                $dates[] = [
                    'date'    => $dateStr,
                    'label'   => $dt->format('M/d (D)'),
                    'weekday' => $weekday,
                ];
            }
        }
    } catch (Exception $e) {
        // Fallback on date parse failure
    }

    $isLiveNow = false;
    if ($isTodayInDates && !empty($startTime) && !empty($endTime)) {
        if ($nowTime >= $startTime && $nowTime <= $endTime) {
            $isLiveNow = true;
        }
    }

    return [
        'dates'       => $dates,
        'start_time'  => $startTime,
        'end_time'    => $endTime,
        'is_live_now' => $isLiveNow,
    ];
}
