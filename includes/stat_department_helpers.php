<?php
/**
 * Lucid University Course Registration System
 * Statistical Department - Read-Only Reporting Helpers
 *
 * Aggregate queries only: no individual student records and no database writes.
 */

/**
 * Retrieve the logged-in Statistical Department employee's own contact details.
 */
function getStatDepartmentProfile(mysqli $conn, int $stat_id): ?array {
    $stmt = $conn->prepare("
        SELECT sd.Stat_ID, u.User_FName, u.User_MName, u.User_LName,
               u.User_PhoneNo, l.User_Email
        FROM STAT_DEPT sd
        JOIN `USER` u ON u.User_ID = sd.Stat_ID
        LEFT JOIN LOGIN l ON l.User_ID = u.User_ID
        WHERE sd.Stat_ID = ? AND u.User_Type = 'Stat-Dept'
    ");
    $stmt->bind_param('i', $stat_id);
    $stmt->execute();
    $profile = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$profile) {
        return null;
    }

    $profile['full_name'] = trim(
        $profile['User_FName'] . ' '
        . (!empty($profile['User_MName']) ? $profile['User_MName'] . ' ' : '')
        . $profile['User_LName']
    );
    return $profile;
}

/**
 * Execute a fixed, aggregate-only SELECT and normalize its label/count rows.
 * Only call with static SQL queries defined in this helper file.
 */
function getStatCategoryCounts(mysqli $conn, string $sql): array {
    $result = $conn->query($sql);
    $rows = [];
    while ($row = $result->fetch_assoc()) {
        $rows[] = [
            'label' => (string)$row['label'],
            'count' => (int)$row['total'],
        ];
    }
    $result->free();
    return $rows;
}

/**
 * Compile university demographics and academic headcounts for the dashboard.
 * All totals are generated from the live database, not sample values.
 */
function getStatDepartmentDashboardData(mysqli $conn): array {
    // School-wide summary cards.
    $summary = $conn->query("
        SELECT
            (SELECT COUNT(*) FROM STUDENT) AS students,
            (SELECT COUNT(*) FROM FACULTY) AS faculty,
            (SELECT COUNT(*) FROM DEPARTMENT) AS departments,
            (SELECT COUNT(*) FROM MAJORS) AS majors
    ")->fetch_assoc();

    // Gender reflects the value saved in USER, including unspecified entries.
    $gender = getStatCategoryCounts($conn, "
        SELECT COALESCE(NULLIF(TRIM(u.User_Gender), ''), 'Not specified') AS label,
               COUNT(*) AS total
        FROM STUDENT s
        JOIN `USER` u ON u.User_ID = s.Student_ID
        GROUP BY label
        ORDER BY total DESC, label ASC
    ");

    // Age is calculated from birth date on the day the report is viewed.
    $ageGroups = getStatCategoryCounts($conn, "
        SELECT
            CASE
                WHEN u.User_DOB IS NULL THEN 'Not recorded'
                WHEN TIMESTAMPDIFF(YEAR, u.User_DOB, CURDATE()) < 18 THEN 'Under 18'
                WHEN TIMESTAMPDIFF(YEAR, u.User_DOB, CURDATE()) BETWEEN 18 AND 20 THEN '18-20'
                WHEN TIMESTAMPDIFF(YEAR, u.User_DOB, CURDATE()) BETWEEN 21 AND 24 THEN '21-24'
                WHEN TIMESTAMPDIFF(YEAR, u.User_DOB, CURDATE()) BETWEEN 25 AND 29 THEN '25-29'
                ELSE '30+'
            END AS label,
            COUNT(*) AS total
        FROM STUDENT s
        JOIN `USER` u ON u.User_ID = s.Student_ID
        GROUP BY label
        ORDER BY FIELD(label, 'Under 18', '18-20', '21-24', '25-29', '30+', 'Not recorded')
    ");

    $studentLevels = getStatCategoryCounts($conn, "
        SELECT COALESCE(NULLIF(TRIM(s.Student_Type), ''), 'Not specified') AS label,
               COUNT(*) AS total
        FROM STUDENT s
        GROUP BY label
        ORDER BY total DESC, label ASC
    ");

    $classStanding = getStatCategoryCounts($conn, "
        SELECT
            CASE
                WHEN s.Student_Type = 'Graduate' THEN 'Graduate'
                WHEN s.Student_Year = 1 THEN 'Freshman'
                WHEN s.Student_Year = 2 THEN 'Sophomore'
                WHEN s.Student_Year = 3 THEN 'Junior'
                WHEN s.Student_Year = 4 THEN 'Senior'
                WHEN s.Student_Year = 5 THEN 'Fifth Year'
                ELSE 'Other / Not recorded'
            END AS label,
            COUNT(*) AS total
        FROM STUDENT s
        GROUP BY label
        ORDER BY FIELD(label, 'Freshman', 'Sophomore', 'Junior', 'Senior', 'Fifth Year', 'Graduate', 'Other / Not recorded')
    ");

    // A student's primary department is STUDENT.Dept_ID, not department roster membership.
    $departments = getStatCategoryCounts($conn, "
        SELECT COALESCE(d.Department_Name, 'No department assigned') AS label,
               COUNT(*) AS total
        FROM STUDENT s
        LEFT JOIN DEPARTMENT d ON d.Department_ID = s.Dept_ID
        GROUP BY label
        ORDER BY total DESC, label ASC
        LIMIT 12
    ");

    // Full-time / part-time values are stored in the respective student subtype tables.
    $attendanceTypes = getStatCategoryCounts($conn, "
        SELECT COALESCE(NULLIF(ug.Undergrad_Type, ''), NULLIF(g.Grad_Type, ''), 'Not recorded') AS label,
               COUNT(*) AS total
        FROM STUDENT s
        LEFT JOIN UNDERGRADUATE_STUDENT ug ON ug.Student_ID = s.Student_ID
        LEFT JOIN GRADUATE_STUDENT g ON g.Student_ID = s.Student_ID
        GROUP BY label
        ORDER BY total DESC, label ASC
    ");

    $facultyRanks = getStatCategoryCounts($conn, "
        SELECT COALESCE(NULLIF(TRIM(f.Rank), ''), 'Not recorded') AS label,
               COUNT(*) AS total
        FROM FACULTY f
        GROUP BY label
        ORDER BY total DESC, label ASC
    ");

    // A double major contributes to two program counts; these are major declarations.
    $popularMajors = getStatCategoryCounts($conn, "
        SELECT m.Major_Name AS label,
               COUNT(DISTINCT sm.Student_ID) AS total
        FROM STUDENT_MAJORS sm
        JOIN MAJORS m ON m.Major_ID = sm.Major_ID
        GROUP BY m.Major_ID, m.Major_Name
        ORDER BY total DESC, label ASC
        LIMIT 10
    ");

    // Semester headcounts include historical and current/future registrations.
    // UNION avoids counting a student twice if both tables contain the same term.
    $enrollmentTrend = getStatCategoryCounts($conn, "
        SELECT CONCAT(sem.Semester_Name, ' ', sem.Semester_Year) AS label,
               COUNT(DISTINCT enroll.Student_ID) AS total
        FROM SEMESTER sem
        LEFT JOIN (
            SELECT cs.Semester_ID, e.Student_ID
            FROM ENROLLMENT e
            JOIN COURSE_SECTIONS cs ON cs.CRN = e.CRN
            UNION
            SELECT sh.Semester_ID, sh.Student_ID
            FROM STUDENT_HISTORY sh
        ) enroll ON enroll.Semester_ID = sem.Semester_ID
        GROUP BY sem.Semester_ID, sem.Semester_Name, sem.Semester_Year, sem.Start_Date
        ORDER BY sem.Start_Date ASC
    ");

    return [
        'summary' => [
            'students' => (int)$summary['students'],
            'faculty' => (int)$summary['faculty'],
            'departments' => (int)$summary['departments'],
            'majors' => (int)$summary['majors'],
        ],
        'gender' => $gender,
        'age_groups' => $ageGroups,
        'student_levels' => $studentLevels,
        'class_standing' => $classStanding,
        'departments' => $departments,
        'attendance_types' => $attendanceTypes,
        'faculty_ranks' => $facultyRanks,
        'popular_majors' => $popularMajors,
        'enrollment_trend' => $enrollmentTrend,
    ];
}
