<?php
/**
 * Lucid University Course Registration System
 * Academic Helpers (Procedural PHP)
 */

/**
 * Convert a letter grade to numeric quality points (4.0 scale).
 * Non-punitive marks ('P', 'W', 'I') return null.
 */
function getGradePoint($grade) {
    if ($grade === null) {
        return null;
    }
    $grade = strtoupper(trim((string)$grade));
    return match ($grade) {
        'A'  => 4.0,
        'A-' => 3.7,
        'B+' => 3.3,
        'B'  => 3.0,
        'B-' => 2.7,
        'C+' => 2.3,
        'C'  => 2.0,
        'C-' => 1.7,
        'D+' => 1.3,
        'D'  => 1.0,
        'F'  => 0.0,
        default => null,
    };
}

/**
 * Fetch demographic profile, department, and declared major/minor.
 */
function getStudentProfile($conn, $student_id) {
    // 1. Fetch user and student academic standing
    $stmt = $conn->prepare("
        SELECT u.User_FName, u.User_MName, u.User_LName,
               u.User_PhoneNo, u.Street_No, u.Street_Name, u.City, u.State, u.Zip_Code,
               l.User_Email,
               s.Student_ID, s.Student_Year, s.Student_Type, s.Credits_Earned,
               COALESCE(ug.Undergrad_Type, g.Grad_Type, 'Full-Time') AS Attendance_Type,
               d.Department_Name
        FROM `USER` u
        JOIN STUDENT s ON u.User_ID = s.Student_ID
        LEFT JOIN LOGIN l ON u.User_ID = l.User_ID
        LEFT JOIN UNDERGRADUATE_STUDENT ug ON s.Student_ID = ug.Student_ID
        LEFT JOIN GRADUATE_STUDENT g ON s.Student_ID = g.Student_ID
        LEFT JOIN DEPARTMENT d ON s.Dept_ID = d.Department_ID
        WHERE s.Student_ID = ?
    ");
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    $profile = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$profile) {
        return null;
    }

    $fullName = trim($profile['User_FName'] . ' ' . (!empty($profile['User_MName']) ? $profile['User_MName'] . ' ' : '') . $profile['User_LName']);
    $profile['full_name'] = $fullName;

    // Map Student_Year to class standing label
    $yearMap = [
        1 => 'Freshman',
        2 => 'Sophomore',
        3 => 'Junior',
        4 => 'Senior',
        5 => 'Graduate'
    ];
    $yearVal = (int)($profile['Student_Year'] ?? 1);
    if (strcasecmp($profile['Student_Type'] ?? '', 'Graduate') === 0) {
        $profile['year_label'] = 'Graduate';
    } else {
        $profile['year_label'] = $yearMap[$yearVal] ?? 'Undergraduate';
    }

    // 2. Fetch declared major(s)
    $stmt = $conn->prepare("
        SELECT m.Major_ID, m.Major_Name, m.No_Credits_Required
        FROM STUDENT_MAJORS sm
        JOIN MAJORS m ON sm.Major_ID = m.Major_ID
        WHERE sm.Student_ID = ?
        ORDER BY sm.Major_ID ASC
    ");
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $majors = [];
    while ($row = $res->fetch_assoc()) {
        $majors[] = $row;
    }
    $stmt->close();

    $profile['majors'] = $majors;
    $profile['primary_major'] = $majors[0] ?? null;
    $profile['secondary_major'] = $majors[1] ?? null;

    // 3. Fetch declared minor(s)
    $stmt = $conn->prepare("
        SELECT m.Minor_ID, m.Minor_Name, m.No_Credits_Required
        FROM STUDENT_MINORS sm
        JOIN MINORS m ON sm.Minor_ID = m.Minor_ID
        WHERE sm.Student_ID = ?
        ORDER BY sm.Minor_ID ASC
    ");
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $minors = [];
    while ($row = $res->fetch_assoc()) {
        $minors[] = $row;
    }
    $stmt->close();

    $profile['minors'] = $minors;
    $profile['minor'] = $minors[0] ?? null;

    return $profile;
}

/**
 * Fetch past completed courses grouped by semester and compute GPA.
 * Quality points math is kept internal solely for GPA calculations.
 */
function getStudentTranscriptData($conn, $student_id) {
    // Query completed history sorted chronologically
    $stmt = $conn->prepare("
        SELECT sh.Course_ID, sh.Semester_ID, sh.Grade,
               s.Semester_Name, s.Semester_Year, s.Start_Date,
               c.Course_Name, c.Course_Credits
        FROM STUDENT_HISTORY sh
        JOIN SEMESTER s ON sh.Semester_ID = s.Semester_ID
        JOIN COURSES c ON sh.Course_ID = c.Course_ID
        WHERE sh.Student_ID = ?
        ORDER BY s.Semester_Year ASC, s.Start_Date ASC, c.Course_ID ASC
    ");
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    $res = $stmt->get_result();

    $terms = [];

    // Group courses by semester and compute quality points for GPA math
    while ($row = $res->fetch_assoc()) {
        $semId = (int)$row['Semester_ID'];
        $credits = (int)$row['Course_Credits'];
        $grade = trim($row['Grade']);
        $pt = getGradePoint($grade);

        // Grade point >= 1.0 (D or higher) or Pass 'P' earns credits
        $isPassing = ($pt !== null && $pt >= 1.0) || (strtoupper($grade) === 'P');
        $creditsEarned = $isPassing ? $credits : 0;

        $row['credits_attempted'] = $credits;
        $row['credits_earned'] = $creditsEarned;

        if (!isset($terms[$semId])) {
            $terms[$semId] = [
                'semester_id' => $semId,
                'semester_label' => $row['Semester_Name'] . ' ' . $row['Semester_Year'],
                'courses' => [],
                'term_credits_earned' => 0,
                'term_credits_attempted' => 0,
                'term_quality_points' => 0.0,
                'term_graded_credits' => 0,
                'term_gpa' => 0.0,
            ];
        }

        $terms[$semId]['courses'][] = $row;
        $terms[$semId]['term_credits_attempted'] += $credits;
        $terms[$semId]['term_credits_earned'] += $creditsEarned;

        // Internal GPA quality points calculation
        if ($pt !== null) {
            $terms[$semId]['term_quality_points'] += ($credits * $pt);
            $terms[$semId]['term_graded_credits'] += $credits;
        }
    }
    $stmt->close();

    $cumQualityPoints = 0.0;
    $cumGradedCredits = 0;
    $totalCreditsEarned = 0;

    // Calculate term GPA and cumulative totals
    foreach ($terms as $semId => &$term) {
        // Divide term quality points by graded credits for term GPA
        $term['term_gpa'] = ($term['term_graded_credits'] > 0)
            ? round($term['term_quality_points'] / $term['term_graded_credits'], 2)
            : 0.0;

        $cumQualityPoints += $term['term_quality_points'];
        $cumGradedCredits += $term['term_graded_credits'];
        $totalCreditsEarned += $term['term_credits_earned'];
    }
    unset($term);

    // Cumulative GPA divisor check
    $cumGpa = ($cumGradedCredits > 0) ? round($cumQualityPoints / $cumGradedCredits, 2) : 0.0;

    return [
        'terms' => $terms,
        'cum_gpa' => $cumGpa,
        'total_credits' => $totalCreditsEarned,
    ];
}

/**
 * Helper to evaluate a list of requirements against completed history and active Fall 2026 courses.
 */
function evaluateRequirementList($reqList, $historyCourses, $fallEnrolled) {
    $evaluated = [];

    foreach ($reqList as $req) {
        $cid = $req['Course_ID'];
        $minGrade = trim($req['Min_Grade']);
        $minPoint = getGradePoint($minGrade) ?? 2.0;

        $status = 'Unfulfilled';
        $badgeClass = 'text-bg-secondary';
        $gradeEarned = null;

        // Check if course was completed in history
        if (isset($historyCourses[$cid])) {
            $hasSatisfied = false;
            $bestGrade = null;
            $bestPoint = -1.0;

            foreach ($historyCourses[$cid] as $attemptGrade) {
                $pt = getGradePoint($attemptGrade);
                if ($pt !== null && $pt >= $minPoint) {
                    $hasSatisfied = true;
                }
                if ($pt !== null && $pt > $bestPoint) {
                    $bestPoint = $pt;
                    $bestGrade = $attemptGrade;
                } elseif ($bestGrade === null) {
                    $bestGrade = $attemptGrade;
                }
            }

            $gradeEarned = $bestGrade;

            // Assign status: Satisfied if grade met minimum, otherwise Deficient
            if ($hasSatisfied) {
                $status = 'Satisfied';
                $badgeClass = 'text-bg-success';
            } else {
                $status = 'Deficient';
                $badgeClass = 'text-bg-warning';
            }
        }

        // Check active enrollment in Fall 2026 if not already satisfied
        if ($status !== 'Satisfied' && isset($fallEnrolled[$cid])) {
            $status = 'In Progress';
            $badgeClass = 'text-bg-primary';
        }

        $req['Status'] = $status;
        $req['Status_Badge_Class'] = $badgeClass;
        $req['Grade_Earned'] = $gradeEarned;

        $evaluated[] = $req;
    }

    return $evaluated;
}

/**
 * Fetch degree audit requirements for declared major(s) and minor.
 */
function getDegreeAuditData($conn, $student_id) {
    // 1. Fetch majors
    $stmt = $conn->prepare("
        SELECT m.Major_ID, m.Major_Name, m.No_Credits_Required
        FROM STUDENT_MAJORS sm
        JOIN MAJORS m ON sm.Major_ID = m.Major_ID
        WHERE sm.Student_ID = ?
        ORDER BY sm.Major_ID ASC
    ");
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $majors = [];
    while ($row = $res->fetch_assoc()) {
        $majors[] = $row;
    }
    $stmt->close();

    // 2. Fetch minors
    $stmt = $conn->prepare("
        SELECT m.Minor_ID, m.Minor_Name, m.No_Credits_Required
        FROM STUDENT_MINORS sm
        JOIN MINORS m ON sm.Minor_ID = m.Minor_ID
        WHERE sm.Student_ID = ?
        ORDER BY sm.Minor_ID ASC
    ");
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $minors = [];
    while ($row = $res->fetch_assoc()) {
        $minors[] = $row;
    }
    $stmt->close();

    // 3. Fetch completed courses from history
    $stmt = $conn->prepare("SELECT Course_ID, Grade FROM STUDENT_HISTORY WHERE Student_ID = ?");
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $historyCourses = [];
    while ($row = $res->fetch_assoc()) {
        $historyCourses[$row['Course_ID']][] = $row['Grade'];
    }
    $stmt->close();

    // 4. Fetch registered courses in Fall 2026 (Semester_ID = 202608)
    $fallSemId = 202608;
    $stmt = $conn->prepare("
        SELECT cs.Course_ID
        FROM ENROLLMENT e
        JOIN COURSE_SECTIONS cs ON e.CRN = cs.CRN
        WHERE e.Student_ID = ? AND cs.Semester_ID = ?
    ");
    $stmt->bind_param("ii", $student_id, $fallSemId);
    $stmt->execute();
    $res = $stmt->get_result();
    $fallEnrolled = [];
    while ($row = $res->fetch_assoc()) {
        $fallEnrolled[$row['Course_ID']] = true;
    }
    $stmt->close();

    // 5. Evaluate requirements for each declared major
    $majorsData = [];
    foreach ($majors as $m) {
        $mid = (int)$m['Major_ID'];
        $reqStmt = $conn->prepare("
            SELECT mr.Course_ID, mr.Min_Grade, c.Course_Name, c.Course_Credits
            FROM MAJOR_REQUIREMENTS mr
            JOIN COURSES c ON mr.Course_ID = c.Course_ID
            WHERE mr.Major_ID = ?
            ORDER BY mr.Course_ID ASC
        ");
        $reqStmt->bind_param("i", $mid);
        $reqStmt->execute();
        $rRes = $reqStmt->get_result();
        $reqs = [];
        while ($rRow = $rRes->fetch_assoc()) {
            $reqs[] = $rRow;
        }
        $reqStmt->close();

        $m['requirements'] = evaluateRequirementList($reqs, $historyCourses, $fallEnrolled);
        $majorsData[] = $m;
    }

    // 6. Evaluate requirements for declared minor
    $minorsData = [];
    foreach ($minors as $min) {
        $minId = (int)$min['Minor_ID'];
        $reqStmt = $conn->prepare("
            SELECT mr.Course_ID, mr.Min_Grade, c.Course_Name, c.Course_Credits
            FROM MINOR_REQUIREMENTS mr
            JOIN COURSES c ON mr.Course_ID = c.Course_ID
            WHERE mr.Minor_ID = ?
            ORDER BY mr.Course_ID ASC
        ");
        $reqStmt->bind_param("i", $minId);
        $reqStmt->execute();
        $rRes = $reqStmt->get_result();
        $reqs = [];
        while ($rRow = $rRes->fetch_assoc()) {
            $reqs[] = $rRow;
        }
        $reqStmt->close();

        $min['requirements'] = evaluateRequirementList($reqs, $historyCourses, $fallEnrolled);
        $minorsData[] = $min;
    }

    return [
        'primary_major'   => $majorsData[0] ?? null,
        'secondary_major' => $majorsData[1] ?? null,
        'minor'           => $minorsData[0] ?? null,
    ];
}

/**
 * Format day and start/end period times cleanly (e.g. "M/W 08:00 AM - 09:40 AM").
 */
if (!function_exists('formatSchedule')) {
    function formatSchedule($day, $start, $end) {
        if (empty($start) || empty($end)) {
            return htmlspecialchars($day ?? 'TBA', ENT_QUOTES, 'UTF-8');
        }
        $st = DateTime::createFromFormat('H:i:s', $start);
        $et = DateTime::createFromFormat('H:i:s', $end);
        $formattedTime = ($st && $et) ? ($st->format('g:i A') . ' - ' . $et->format('g:i A')) : ($start . ' - ' . $end);
        return htmlspecialchars(($day ? $day . ' ' : '') . $formattedTime, ENT_QUOTES, 'UTF-8');
    }
}

/**
 * Fetch detailed semester metadata including registration and drop windows.
 */
function getSemesterInfo($conn, $semester_id) {
    $stmt = $conn->prepare("
        SELECT s.Semester_ID, s.Semester_Name, s.Semester_Year, s.Start_Date, s.End_Date,
               ir.Last_Day_Of_Registration, ir.Last_Day_Of_Dropping_Course,
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
 * Fetch personal schedule and enrolled courses for a student in a specific semester.
 * Returns an associative array:
 * ['courses' => $courses, 'crns' => $crns, 'course_ids' => $course_ids, 'total_credits' => $total_credits]
 */
function getStudentScheduleBySemester($conn, $student_id, $semester_id) {
    $stmt = $conn->prepare("
        SELECT e.CRN, cs.Course_ID, cs.Section_Number, cs.Semester_ID,
               c.Course_Name, c.Course_Credits, c.Course_Type,
               dept.Department_Name,
               ts.Time_Slot_Day, p.Start_Time, p.End_Time,
               b.Building_Name, r.Room_NO,
               e.Grade,
               COALESCE(CONCAT(u_fac.User_FName, ' ', u_fac.User_LName), 'Staff') AS Instructor_Name
        FROM ENROLLMENT e
        JOIN COURSE_SECTIONS cs ON e.CRN = cs.CRN
        JOIN COURSES c ON cs.Course_ID = c.Course_ID
        LEFT JOIN DEPARTMENT dept ON c.Department_ID = dept.Department_ID
        JOIN TIME_SLOT ts ON cs.Time_Slot_ID = ts.Time_Slot_ID
        JOIN PERIOD p ON ts.Period_ID = p.Period_ID
        JOIN ROOM r ON cs.Room_ID = r.Room_ID
        JOIN BUILDING b ON r.Building_ID = b.Building_ID
        LEFT JOIN FACULTY f ON cs.Faculty_ID = f.Faculty_ID
        LEFT JOIN `USER` u_fac ON f.Faculty_ID = u_fac.User_ID
        WHERE e.Student_ID = ? AND cs.Semester_ID = ?
        ORDER BY c.Course_ID ASC, cs.Section_Number ASC
    ");
    $stmt->bind_param("ii", $student_id, $semester_id);
    $stmt->execute();
    $res = $stmt->get_result();

    $courses = [];
    $crns = [];
    $course_ids = [];
    $total_credits = 0;

    while ($row = $res->fetch_assoc()) {
        $courses[] = $row;
        $crns[] = (int)$row['CRN'];
        $course_ids[] = $row['Course_ID'];
        $total_credits += (int)$row['Course_Credits'];
    }
    $stmt->close();

    return [
        'courses'       => $courses,
        'crns'          => $crns,
        'course_ids'    => $course_ids,
        'total_credits' => $total_credits,
    ];
}

/**
 * Fetch dropdown options for Master Schedule search: semesters, departments, and time slots.
 */
function getMasterScheduleFilterOptions($conn) {
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
 * Search course sections in COURSE_SECTIONS using dynamic parameterized filters.
 */
function searchMasterSchedule($conn, $filters = []) {
    $where = [];
    $params = [];
    $types = "";

    // 1. semester_id (int, exact match on cs.Semester_ID, defaults to 202608 if not passed)
    $semesterId = !empty($filters['semester_id']) ? (int)$filters['semester_id'] : 202608;
    $where[] = "cs.Semester_ID = ?";
    $params[] = $semesterId;
    $types .= "i";

    // 2. crn (int, exact match on cs.CRN)
    if (!empty($filters['crn'])) {
        $where[] = "cs.CRN = ?";
        $params[] = (int)$filters['crn'];
        $types .= "i";
    }

    // 3. dept (int, exact match on c.Department_ID)
    if (!empty($filters['dept'])) {
        $where[] = "c.Department_ID = ?";
        $params[] = (int)$filters['dept'];
        $types .= "i";
    }

    // 4. search (string, LIKE match on c.Course_ID LIKE ? OR c.Course_Name LIKE ?)
    if (isset($filters['search']) && trim($filters['search']) !== '') {
        $where[] = "(c.Course_ID LIKE ? OR c.Course_Name LIKE ?)";
        $term = '%' . trim($filters['search']) . '%';
        $params[] = $term;
        $params[] = $term;
        $types .= "ss";
    }

    // 5. section (int, exact match on cs.Section_Number)
    if (isset($filters['section']) && $filters['section'] !== '' && is_numeric($filters['section'])) {
        $where[] = "cs.Section_Number = ?";
        $params[] = (int)$filters['section'];
        $types .= "i";
    }

    // 6. time_slot_id (int, exact match on cs.Time_Slot_ID)
    if (!empty($filters['time_slot_id'])) {
        $where[] = "cs.Time_Slot_ID = ?";
        $params[] = (int)$filters['time_slot_id'];
        $types .= "i";
    }

    // 7. instructor (string, LIKE match on full name or last name)
    if (isset($filters['instructor']) && trim($filters['instructor']) !== '') {
        $where[] = "(CONCAT(u_fac.User_FName, ' ', u_fac.User_LName) LIKE ? OR u_fac.User_LName LIKE ?)";
        $term = '%' . trim($filters['instructor']) . '%';
        $params[] = $term;
        $params[] = $term;
        $types .= "ss";
    }

    // 8. room (string, LIKE match on Building+Room or Room_ID)
    if (isset($filters['room']) && trim($filters['room']) !== '') {
        $where[] = "(CONCAT(b.Building_Name, ' ', r.Room_NO) LIKE ? OR cs.Room_ID LIKE ?)";
        $term = '%' . trim($filters['room']) . '%';
        $params[] = $term;
        $params[] = $term;
        $types .= "ss";
    }

    // 9. credits (int, exact match on c.Course_Credits)
    if (isset($filters['credits']) && $filters['credits'] !== '' && is_numeric($filters['credits'])) {
        $where[] = "c.Course_Credits = ?";
        $params[] = (int)$filters['credits'];
        $types .= "i";
    }

    // 10. open_only (bool, cs.Available_seats > 0)
    if (!empty($filters['open_only'])) {
        $where[] = "cs.Available_seats > 0";
    }

    $sql = "
        SELECT cs.CRN, cs.Course_ID, cs.Section_Number, cs.Available_seats, cs.Semester_ID,
               c.Course_Name, c.Course_Credits, c.Course_Type, c.Department_ID,
               dept.Department_Name,
               cs.Time_Slot_ID, ts.Time_Slot_Day, p.Start_Time, p.End_Time,
               cs.Room_ID, b.Building_Name, r.Room_NO,
               COALESCE(CONCAT(u_fac.User_FName, ' ', u_fac.User_LName), 'Staff') AS Instructor_Name
        FROM COURSE_SECTIONS cs
        JOIN COURSES c ON cs.Course_ID = c.Course_ID
        LEFT JOIN DEPARTMENT dept ON c.Department_ID = dept.Department_ID
        JOIN TIME_SLOT ts ON cs.Time_Slot_ID = ts.Time_Slot_ID
        JOIN PERIOD p ON ts.Period_ID = p.Period_ID
        JOIN ROOM r ON cs.Room_ID = r.Room_ID
        JOIN BUILDING b ON r.Building_ID = b.Building_ID
        LEFT JOIN FACULTY f ON cs.Faculty_ID = f.Faculty_ID
        LEFT JOIN `USER` u_fac ON f.Faculty_ID = u_fac.User_ID
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
        $results[] = $row;
    }
    $stmt->close();

    // Attach course prerequisites map to each section row
    $prereqMap = getAllCoursePrerequisitesMap($conn);
    foreach ($results as &$secRow) {
        $cid = $secRow['Course_ID'];
        $secRow['prerequisites'] = $prereqMap[$cid] ?? [];
    }
    unset($secRow);

    return $results;
}

/**
 * Return an associative array of all course prerequisites keyed by Course_ID.
 */
function getAllCoursePrerequisitesMap($conn) {
    $sql = "
        SELECT cp.Course_ID, cp.Prerequisite_Course_ID, c.Course_Name AS Prerequisite_Course_Name
        FROM COURSE_PREREQUISITE cp
        JOIN COURSES c ON cp.Prerequisite_Course_ID = c.Course_ID
        ORDER BY cp.Course_ID ASC, cp.Prerequisite_Course_ID ASC
    ";
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $res = $stmt->get_result();
    $map = [];
    while ($row = $res->fetch_assoc()) {
        $cid = $row['Course_ID'];
        if (!isset($map[$cid])) {
            $map[$cid] = [];
        }
        $map[$cid][] = [
            'Course_ID'   => $row['Prerequisite_Course_ID'],
            'Course_Name' => $row['Prerequisite_Course_Name']
        ];
    }
    $stmt->close();
    return $map;
}
