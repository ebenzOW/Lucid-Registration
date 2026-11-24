<?php
/**
 * Lucid University - Faculty & Section Administration Helpers
 */

require_once __DIR__ . '/ui_helpers.php';
require_once __DIR__ . '/schedule_helpers.php';

/**
 * Retrieve comprehensive faculty profile, office, and department appointment details.
 */
function getFacultyProfile(mysqli $conn, int $faculty_id): ?array {
    $fid = (int)$faculty_id;
    $stmt = $conn->prepare("
        SELECT f.Faculty_ID, f.Faculty_Type, f.Rank, f.Specialty, f.Office_ID,
               u.User_FName, u.User_MName, u.User_LName, u.User_PhoneNo,
               u.User_Gender, u.User_DOB, u.Street_No, u.Street_Name, u.City, u.State, u.Zip_Code, u.Country,
               l.User_Email,
               r.Room_NO AS Office_Room_NO, b.Building_Name AS Office_Building_Name, o.Office_Desks,
               GROUP_CONCAT(DISTINCT d.Department_Name ORDER BY d.Department_Name SEPARATOR ', ') AS Department_Names
        FROM FACULTY f
        JOIN `USER` u ON f.Faculty_ID = u.User_ID
        LEFT JOIN LOGIN l ON u.User_ID = l.User_ID
        LEFT JOIN ROOM r ON f.Office_ID = r.Room_ID
        LEFT JOIN BUILDING b ON r.Building_ID = b.Building_ID
        LEFT JOIN OFFICE o ON f.Office_ID = o.Office_ID
        LEFT JOIN DEPARTMENT_ROSTER dr ON f.Faculty_ID = dr.Faculty_ID
        LEFT JOIN DEPARTMENT d ON dr.Department_ID = d.Department_ID
        WHERE f.Faculty_ID = ?
        GROUP BY f.Faculty_ID, f.Faculty_Type, f.Rank, f.Specialty, f.Office_ID,
                 u.User_FName, u.User_MName, u.User_LName, u.User_PhoneNo,
                 u.User_Gender, u.User_DOB, u.Street_No, u.Street_Name, u.City, u.State, u.Zip_Code, u.Country,
                 l.User_Email, r.Room_NO, b.Building_Name, o.Office_Desks
    ");
    $stmt->bind_param("i", $fid);
    $stmt->execute();
    $faculty = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$faculty) { 
        return null; 
    }
    $faculty['full_name'] = trim($faculty['User_FName'] . ' ' . (!empty($faculty['User_MName']) ? $faculty['User_MName'] . ' ' : '') . $faculty['User_LName']);
    return $faculty;
}

/**
 * Retrieve extended faculty profile details including department roster, leadership,
 * term teaching loads, and optional advisee list.
 */
function getFacultyProfileDetails(mysqli $conn, int $faculty_id, bool $includeAdviseeList = false): array {
    $facultyProfile = getFacultyProfile($conn, $faculty_id);
    $facultyData = $facultyProfile ?: [];
    $facultyData['Building_Name'] = $facultyProfile['Office_Building_Name'] ?? '';
    $facultyData['Room_NO'] = $facultyProfile['Office_Room_NO'] ?? '';

    $drStmt = $conn->prepare("
        SELECT d.Department_Name, dr.Percent_Time, dr.Date_of_Assignment
        FROM DEPARTMENT_ROSTER dr
        JOIN DEPARTMENT d ON dr.Department_ID = d.Department_ID
        WHERE dr.Faculty_ID = ?
        ORDER BY dr.Percent_Time DESC
    ");
    $drStmt->bind_param("i", $faculty_id);
    $drStmt->execute();
    $drRes = $drStmt->get_result();
    $departments = [];
    while ($drRow = $drRes->fetch_assoc()) {
        $departments[] = $drRow;
    }
    $drStmt->close();
    $facultyData['departments'] = $departments;

    $leadStmt = $conn->prepare("
        SELECT d.Department_Name, 'Department Chair' AS Role_Title
        FROM DEPARTMENT d
        WHERE d.Chair_ID = ?
        UNION
        SELECT d.Department_Name, dm.Role AS Role_Title
        FROM DEPARTMENT_MANAGERS dm
        JOIN DEPARTMENT d ON dm.Department_ID = d.Department_ID
        WHERE dm.Faculty_ID = ?
    ");
    $leadStmt->bind_param("ii", $faculty_id, $faculty_id);
    $leadStmt->execute();
    $leadRes = $leadStmt->get_result();
    $leadership = [];
    while ($leadRow = $leadRes->fetch_assoc()) {
        $leadership[] = $leadRow;
    }
    $leadStmt->close();
    $facultyData['leadership'] = $leadership;

    $secCountStmt = $conn->prepare("
        SELECT
            (SELECT COUNT(*) FROM COURSE_SECTIONS WHERE Faculty_ID = ? AND Semester_ID = 202608) AS fall_load,
            (SELECT COUNT(*) FROM COURSE_SECTIONS WHERE Faculty_ID = ? AND Semester_ID = 202701) AS spring_load
    ");
    $secCountStmt->bind_param("ii", $faculty_id, $faculty_id);
    $secCountStmt->execute();
    $loadCounts = $secCountStmt->get_result()->fetch_assoc();
    $secCountStmt->close();
    $facultyData['fall_load'] = (int)($loadCounts['fall_load'] ?? 0);
    $facultyData['spring_load'] = (int)($loadCounts['spring_load'] ?? 0);

    if ($includeAdviseeList) {
        $advisees = getFacultyAdvisees($conn, $faculty_id);
        $facultyData['advisees'] = $advisees;
        $facultyData['advisee_count'] = count($advisees);
    } else {
        $facultyData['advisees'] = [];
        $advCountStmt = $conn->prepare("SELECT COUNT(*) AS cnt FROM ADVISOR_STUDENT WHERE Faculty_ID = ?");
        $advCountStmt->bind_param("i", $faculty_id);
        $advCountStmt->execute();
        $facultyData['advisee_count'] = (int)($advCountStmt->get_result()->fetch_assoc()['cnt'] ?? 0);
        $advCountStmt->close();
    }

    return $facultyData;
}

/**
 * Fetch teaching schedule for a faculty member in a specific semester (or across all semesters if $semester_id is null).
 */
function getFacultyScheduleBySemester(mysqli $conn, int $faculty_id, ?int $semester_id = null): array {
    $faculty_id = (int)$faculty_id;
    $where = ["cs.Faculty_ID = ?"];
    $params = [$faculty_id];
    $types = "i";

    if ($semester_id !== null) {
        $where[] = "cs.Semester_ID = ?";
        $params[] = (int)$semester_id;
        $types .= "i";
    }

    $sql = "
        SELECT cs.CRN, cs.Course_ID, cs.Section_Number, cs.Semester_ID, cs.Available_seats, cs.Status,
               c.Course_Name, c.Course_Credits, c.Course_Type, c.Course_Description,
               dept.Department_Name,
               ts.Time_Slot_Day, p.Start_Time, p.End_Time,
               b.Building_Name, r.Room_NO, r.Room_Type,
               s.Semester_Name, s.Semester_Year, s.Start_Date, s.End_Date,
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
        WHERE " . implode(" AND ", $where) . "
        ORDER BY cs.Semester_ID DESC, c.Course_ID ASC, cs.Section_Number ASC
    ";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $res = $stmt->get_result();

    $sections = [];
    $crns = [];
    while ($row = $res->fetch_assoc()) {
        $row['Allowed_Seats'] = (int)$row['Enrolled_Count'] + (int)$row['Available_seats'];
        $sections[] = $row;
        $crns[] = (int)$row['CRN'];
    }
    $stmt->close();

    return [
        'sections'       => $sections,
        'crns'           => $crns,
        'total_sections' => count($sections),
    ];
}

/**
 * Query students enrolled in a specific section (CRN).
 * MUST select: e.Midterm_Grade, e.Final_Grade, e.Grade (for non-historical terms)
 * and NULL AS Midterm_Grade, NULL AS Final_Grade, sh.Grade (for historical terms).
 */
function getSectionRoster(mysqli $conn, int $crn, ?int $faculty_id = null): ?array {
    $crn = (int)$crn;

    $secStmt = $conn->prepare("SELECT CRN, Semester_ID, Faculty_ID FROM COURSE_SECTIONS WHERE CRN = ?");
    $secStmt->bind_param("i", $crn);
    $secStmt->execute();
    $sec = $secStmt->get_result()->fetch_assoc();
    $secStmt->close();

    if (!$sec) {
        return [];
    }

    if ($faculty_id !== null && (int)$sec['Faculty_ID'] !== (int)$faculty_id) {
        return null;
    }

    $isHistorical = ((int)$sec['Semester_ID'] < 202608);

    if ($isHistorical) {
        $stmt = $conn->prepare("
            SELECT s.Student_ID,
                   u.User_FName, u.User_MName, u.User_LName,
                   l.User_Email,
                   s.Student_Year, s.Student_Type,
                   d.Department_Name,
                   NULL AS Midterm_Grade,
                   NULL AS Final_Grade,
                   sh.Grade,
                   COALESCE(ug.Undergrad_Type, g.Grad_Type, 'Full-Time') AS Attendance_Type,
                   (SELECT GROUP_CONCAT(m.Major_Name SEPARATOR ', ')
                    FROM STUDENT_MAJORS sm
                    JOIN MAJORS m ON sm.Major_ID = m.Major_ID
                    WHERE sm.Student_ID = s.Student_ID) AS Majors
            FROM STUDENT_HISTORY sh
            JOIN STUDENT s ON sh.Student_ID = s.Student_ID
            JOIN `USER` u ON s.Student_ID = u.User_ID
            LEFT JOIN LOGIN l ON u.User_ID = l.User_ID
            LEFT JOIN DEPARTMENT d ON s.Dept_ID = d.Department_ID
            LEFT JOIN UNDERGRADUATE_STUDENT ug ON s.Student_ID = ug.Student_ID
            LEFT JOIN GRADUATE_STUDENT g ON s.Student_ID = g.Student_ID
            WHERE sh.CRN = ?
            ORDER BY u.User_LName ASC, u.User_FName ASC
        ");
    } else {
        $stmt = $conn->prepare("
            SELECT s.Student_ID,
                   u.User_FName, u.User_MName, u.User_LName,
                   l.User_Email,
                   s.Student_Year, s.Student_Type,
                   d.Department_Name,
                   e.Midterm_Grade,
                   e.Final_Grade,
                   e.Grade,
                   COALESCE(ug.Undergrad_Type, g.Grad_Type, 'Full-Time') AS Attendance_Type,
                   (SELECT GROUP_CONCAT(m.Major_Name SEPARATOR ', ')
                    FROM STUDENT_MAJORS sm
                    JOIN MAJORS m ON sm.Major_ID = m.Major_ID
                    WHERE sm.Student_ID = s.Student_ID) AS Majors
            FROM ENROLLMENT e
            JOIN STUDENT s ON e.Student_ID = s.Student_ID
            JOIN `USER` u ON s.Student_ID = u.User_ID
            LEFT JOIN LOGIN l ON u.User_ID = l.User_ID
            LEFT JOIN DEPARTMENT d ON s.Dept_ID = d.Department_ID
            LEFT JOIN UNDERGRADUATE_STUDENT ug ON s.Student_ID = ug.Student_ID
            LEFT JOIN GRADUATE_STUDENT g ON s.Student_ID = g.Student_ID
            WHERE e.CRN = ?
            ORDER BY u.User_LName ASC, u.User_FName ASC
        ");
    }
    $stmt->bind_param("i", $crn);
    $stmt->execute();
    $res = $stmt->get_result();

    $yearMap = [
        1 => 'Freshman',
        2 => 'Sophomore',
        3 => 'Junior',
        4 => 'Senior',
        5 => 'Graduate'
    ];

    $roster = [];
    while ($row = $res->fetch_assoc()) {
        $fullName = trim($row['User_FName'] . ' ' . (!empty($row['User_MName']) ? $row['User_MName'] . ' ' : '') . $row['User_LName']);
        $row['full_name'] = $fullName;
        $yearVal = (int)($row['Student_Year'] ?? 1);
        if (strcasecmp($row['Student_Type'] ?? '', 'Graduate') === 0) {
            $row['class_standing'] = 'Graduate';
        } else {
            $row['class_standing'] = $yearMap[$yearVal] ?? 'Undergraduate';
        }
        $row['Major_Name'] = $row['Majors'] ?? 'Undeclared';
        $roster[] = $row;
    }
    $stmt->close();

    return $roster;
}

/**
 * Query advisees assigned to a faculty advisor.
 */
function getFacultyAdvisees(mysqli $conn, int $faculty_id): array {
    $stmt = $conn->prepare("
        SELECT a.Student_ID, a.Date_Assigned,
               u.User_FName, u.User_MName, u.User_LName,
               l.User_Email, u.User_PhoneNo,
               s.Student_Year, s.Student_Type, s.Credits_Earned,
               d.Department_Name,
               COALESCE(ug.Undergrad_Type, g.Grad_Type, 'Full-Time') AS Attendance_Type,
               (SELECT GROUP_CONCAT(m.Major_Name SEPARATOR ', ')
                FROM STUDENT_MAJORS sm
                JOIN MAJORS m ON sm.Major_ID = m.Major_ID
                WHERE sm.Student_ID = s.Student_ID) AS Majors,
               (SELECT COUNT(*) FROM STUDENT_HOLDS sh WHERE sh.Student_ID = s.Student_ID) AS Active_Holds_Count
        FROM ADVISOR_STUDENT a
        JOIN STUDENT s ON a.Student_ID = s.Student_ID
        JOIN `USER` u ON s.Student_ID = u.User_ID
        LEFT JOIN LOGIN l ON u.User_ID = l.User_ID
        LEFT JOIN DEPARTMENT d ON s.Dept_ID = d.Department_ID
        LEFT JOIN UNDERGRADUATE_STUDENT ug ON s.Student_ID = ug.Student_ID
        LEFT JOIN GRADUATE_STUDENT g ON s.Student_ID = g.Student_ID
        WHERE a.Faculty_ID = ?
        ORDER BY u.User_LName ASC, u.User_FName ASC
    ");
    $stmt->bind_param("i", $faculty_id);
    $stmt->execute();
    $res = $stmt->get_result();

    $yearMap = [
        1 => 'Freshman',
        2 => 'Sophomore',
        3 => 'Junior',
        4 => 'Senior',
        5 => 'Graduate'
    ];

    $advisees = [];
    while ($row = $res->fetch_assoc()) {
        $fullName = trim($row['User_FName'] . ' ' . (!empty($row['User_MName']) ? $row['User_MName'] . ' ' : '') . $row['User_LName']);
        $row['full_name'] = $fullName;
        $yearVal = (int)($row['Student_Year'] ?? 1);
        if (strcasecmp($row['Student_Type'] ?? '', 'Graduate') === 0) {
            $row['class_standing'] = 'Graduate';
        } else {
            $row['class_standing'] = $yearMap[$yearVal] ?? 'Undergraduate';
        }
        $advisees[] = $row;
    }
    $stmt->close();

    return $advisees;
}

/**
 * Build unified section attendance matrix reused by Faculty Dashboard and Admin Master Schedule.
 */
function getSectionAttendanceMatrix(mysqli $conn, int $crn): array {
    $crn = (int)$crn;
    $students = getSectionRoster($conn, $crn, null) ?? [];
    $meetingMeta = getSectionMeetingDates($conn, $crn);

    $attStmt = $conn->prepare("SELECT Student_ID, Class_Date, Status FROM ATTENDANCE WHERE CRN = ?");
    $attStmt->bind_param("i", $crn);
    $attStmt->execute();
    $attRes = $attStmt->get_result();

    $attendanceMap = [];
    while ($arow = $attRes->fetch_assoc()) {
        $attendanceMap[(int)$arow['Student_ID']][$arow['Class_Date']] = $arow['Status'];
    }
    $attStmt->close();

    $studentTotals = [];
    foreach ($students as $st) {
        $sid = (int)$st['Student_ID'];
        $present = 0;
        $absent = 0;
        if (isset($attendanceMap[$sid])) {
            foreach ($attendanceMap[$sid] as $status) {
                if ($status === 'Present') {
                    $present++;
                } elseif ($status === 'Absent') {
                    $absent++;
                }
            }
        }
        $totalRecorded = $present + $absent;
        $pct = $totalRecorded > 0 ? round(($present / $totalRecorded) * 100, 1) : 0.0;

        $studentTotals[$sid] = [
            'present_count'  => $present,
            'absent_count'   => $absent,
            'total_recorded' => $totalRecorded,
            'attendance_pct' => $pct,
        ];
    }

    return [
        'students'       => $students,
        'meeting_meta'   => $meetingMeta,
        'attendance_map' => $attendanceMap,
        'student_totals' => $studentTotals,
    ];
}

/**
 * Fetches eligible faculty for a course section.
 * - Belong to $department_id in DEPARTMENT_ROSTER
 * - Active load in $semester_id (excluding $exclude_crn) < maxLoad (FT=2, PT=1)
 * - Zero time-slot overlap in $semester_id (excluding $exclude_crn) via Period_ID + Day_ID
 * - Always includes $current_faculty_id if set (marked 'is_current' => true).
 */
function getEligibleProfessorsForSection(
    mysqli $conn,
    int $semester_id,
    int $department_id,
    int $time_slot_id,
    ?int $exclude_crn = null,
    ?int $current_faculty_id = null
): array {
    $stmt = $conn->prepare("
        SELECT f.Faculty_ID, f.Faculty_Type, u.User_FName, u.User_MName, u.User_LName,
               d.Department_Name
        FROM DEPARTMENT_ROSTER dr
        JOIN FACULTY f ON dr.Faculty_ID = f.Faculty_ID
        JOIN `USER` u ON f.Faculty_ID = u.User_ID
        JOIN DEPARTMENT d ON dr.Department_ID = d.Department_ID
        WHERE dr.Department_ID = ?
        ORDER BY u.User_LName ASC, u.User_FName ASC
    ");
    $stmt->bind_param("i", $department_id);
    $stmt->execute();
    $facultyList = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    $loadSql = "
        SELECT COUNT(*) AS load_count
        FROM COURSE_SECTIONS cs
        WHERE cs.Faculty_ID = ?
          AND cs.Semester_ID = ?
          AND COALESCE(cs.Status, 'Active') = 'Active'
    ";
    if ($exclude_crn !== null) {
        $loadSql .= " AND cs.CRN != ?";
    }
    $loadStmt = $conn->prepare($loadSql);

    $conflictSql = "
        SELECT 1
        FROM COURSE_SECTIONS cs
        JOIN TIME_SLOT ts ON cs.Time_Slot_ID = ts.Time_Slot_ID
        JOIN TIME_SLOT_DAY tsd ON ts.Time_Slot_ID = tsd.Time_Slot_ID
        WHERE cs.Faculty_ID = ?
          AND cs.Semester_ID = ?
          AND COALESCE(cs.Status, 'Active') = 'Active'
          AND ts.Period_ID = (SELECT Period_ID FROM TIME_SLOT WHERE Time_Slot_ID = ?)
          AND tsd.Day_ID IN (SELECT Day_ID FROM TIME_SLOT_DAY WHERE Time_Slot_ID = ?)
    ";
    if ($exclude_crn !== null) {
        $conflictSql .= " AND cs.CRN != ?";
    }
    $conflictSql .= " LIMIT 1";
    $conflictStmt = $conn->prepare($conflictSql);

    $eligible = [];
    foreach ($facultyList as $fac) {
        $fid = (int)$fac['Faculty_ID'];
        $isCurrent = ($current_faculty_id !== null && $fid === (int)$current_faculty_id);

        // Load check
        if ($exclude_crn !== null) {
            $loadStmt->bind_param("iii", $fid, $semester_id, $exclude_crn);
        } else {
            $loadStmt->bind_param("ii", $fid, $semester_id);
        }
        $loadStmt->execute();
        $curLoad = (int)($loadStmt->get_result()->fetch_assoc()['load_count'] ?? 0);

        $isFT = (stripos($fac['Faculty_Type'], 'Full') !== false);
        $maxLoad = $isFT ? 2 : 1;

        // Conflict check
        if ($exclude_crn !== null) {
            $conflictStmt->bind_param("iiiii", $fid, $semester_id, $time_slot_id, $time_slot_id, $exclude_crn);
        } else {
            $conflictStmt->bind_param("iiii", $fid, $semester_id, $time_slot_id, $time_slot_id);
        }
        $conflictStmt->execute();
        $hasConflict = (bool)$conflictStmt->get_result()->fetch_row();

        if ($isCurrent || (!$hasConflict && $curLoad < $maxLoad)) {
            $fName = trim($fac['User_FName'] . ' ' . (!empty($fac['User_MName']) ? $fac['User_MName'] . ' ' : '') . $fac['User_LName']);
            $eligible[] = [
                'Faculty_ID'      => $fid,
                'full_name'       => $fName,
                'Faculty_Type'    => $fac['Faculty_Type'],
                'Department_Name' => $fac['Department_Name'],
                'current_load'    => $curLoad,
                'max_load'        => $maxLoad,
                'is_current'      => $isCurrent,
                'has_conflict'    => $hasConflict,
            ];
        }
    }
    $loadStmt->close();
    $conflictStmt->close();

    return $eligible;
}

/**
 * Fetches eligible lecture/lab rooms for a course section.
 * - r.Room_Type IN ('Lecture', 'Lab')
 * - Physical capacity >= $min_capacity
 * - Zero time-slot overlap in $semester_id (excluding $exclude_crn) via Period_ID + Day_ID
 * - Always includes $current_room_id if valid (marked 'is_current' => true).
 */
function getEligibleRoomsForSection(
    mysqli $conn,
    int $semester_id,
    int $time_slot_id,
    int $min_capacity,
    ?int $exclude_crn = null,
    ?string $current_room_id = null
): array {
    $stmt = $conn->prepare("
        SELECT r.Room_ID, r.Building_ID, r.Room_NO, r.Room_Type,
               b.Building_Name,
               COALESCE(l.Lecture_Seats, lab.Lab_Stations, 0) AS Room_Capacity
        FROM ROOM r
        JOIN BUILDING b ON r.Building_ID = b.Building_ID
        LEFT JOIN LECTURE l ON r.Room_ID = l.Lecture_ID
        LEFT JOIN LAB lab ON r.Room_ID = lab.Lab_ID
        WHERE r.Room_Type IN ('Lecture', 'Lab')
        ORDER BY b.Building_Name ASC, r.Room_NO ASC
    ");
    $stmt->execute();
    $rooms = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    $conflictSql = "
        SELECT 1
        FROM COURSE_SECTIONS cs
        JOIN TIME_SLOT ts ON cs.Time_Slot_ID = ts.Time_Slot_ID
        JOIN TIME_SLOT_DAY tsd ON ts.Time_Slot_ID = tsd.Time_Slot_ID
        WHERE cs.Room_ID = ?
          AND cs.Semester_ID = ?
          AND COALESCE(cs.Status, 'Active') = 'Active'
          AND ts.Period_ID = (SELECT Period_ID FROM TIME_SLOT WHERE Time_Slot_ID = ?)
          AND tsd.Day_ID IN (SELECT Day_ID FROM TIME_SLOT_DAY WHERE Time_Slot_ID = ?)
    ";
    if ($exclude_crn !== null) {
        $conflictSql .= " AND cs.CRN != ?";
    }
    $conflictSql .= " LIMIT 1";
    $conflictStmt = $conn->prepare($conflictSql);

    $eligible = [];
    foreach ($rooms as $r) {
        $rid = $r['Room_ID'];
        $cap = (int)$r['Room_Capacity'];
        $isCurrent = ($current_room_id !== null && strcasecmp($rid, $current_room_id) === 0);

        if ($exclude_crn !== null) {
            $conflictStmt->bind_param("siiii", $rid, $semester_id, $time_slot_id, $time_slot_id, $exclude_crn);
        } else {
            $conflictStmt->bind_param("siii", $rid, $semester_id, $time_slot_id, $time_slot_id);
        }
        $conflictStmt->execute();
        $hasConflict = (bool)$conflictStmt->get_result()->fetch_row();

        if ($isCurrent || (!$hasConflict && $cap >= $min_capacity)) {
            $eligible[] = [
                'Room_ID'       => $rid,
                'Building_ID'   => $r['Building_ID'],
                'Building_Name' => $r['Building_Name'],
                'Room_NO'       => $r['Room_NO'],
                'Room_Type'     => $r['Room_Type'],
                'Room_Capacity' => $cap,
                'is_current'    => $isCurrent,
                'has_conflict'  => $hasConflict,
            ];
        }
    }
    $conflictStmt->close();

    return $eligible;
}
