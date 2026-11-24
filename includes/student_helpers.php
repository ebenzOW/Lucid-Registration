<?php
/**
 * Lucid University - Student Academic & Degree Helpers
 */

/**
 * Grade to grade point conversion map.
 */
function getGradePoint($grade): ?float {
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
 * Average midterm grade with final exam grade to determine overall course grade.
 */
function averageMidtermAndFinalGrade($midtermGrade, $finalExamGrade) {
    $map = [
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
    ];

    $midterm = !empty($midtermGrade) ? strtoupper(trim((string)$midtermGrade)) : null;
    $final   = !empty($finalExamGrade) ? strtoupper(trim((string)$finalExamGrade)) : null;

    if ($final === null || !array_key_exists($final, $map)) {
        return $finalExamGrade;
    }

    if ($midterm === null || !array_key_exists($midterm, $map)) {
        return $final;
    }

    $avgPts = ($map[$midterm] + $map[$final]) / 2.0;

    if ($avgPts >= 3.85) {
        return 'A';
    } elseif ($avgPts >= 3.50) {
        return 'A-';
    } elseif ($avgPts >= 3.15) {
        return 'B+';
    } elseif ($avgPts >= 2.85) {
        return 'B';
    } elseif ($avgPts >= 2.50) {
        return 'B-';
    } elseif ($avgPts >= 2.15) {
        return 'C+';
    } elseif ($avgPts >= 1.85) {
        return 'C';
    } elseif ($avgPts >= 1.50) {
        return 'C-';
    } elseif ($avgPts >= 1.15) {
        return 'D+';
    } elseif ($avgPts >= 0.50) {
        return 'D';
    } else {
        return 'F';
    }
}

/**
 * Fetch active holds for a student.
 */
function getStudentHolds(mysqli $conn, int $student_id): array {
    $stmt = $conn->prepare("
        SELECT sh.Hold_ID, h.Hold_Type, sh.Hold_Date
        FROM STUDENT_HOLDS sh
        JOIN HOLDS h ON sh.Hold_ID = h.Hold_ID
        WHERE sh.Student_ID = ?
        ORDER BY sh.Hold_Date DESC
    ");
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $holds = [];
    while ($row = $res->fetch_assoc()) {
        $holds[] = $row;
    }
    $stmt->close();
    return $holds;
}

/**
 * Fetch demographic profile, department, and declared major/minor.
 */
function getStudentProfile(mysqli $conn, int $student_id): ?array {
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

    // Declared major(s)
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

    // Declared minor(s)
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

    $profile['max_credits'] = (strcasecmp($profile['Attendance_Type'] ?? '', 'Part-Time') === 0) ? 8 : 16;

    return $profile;
}

/**
 * Fetch comprehensive student profile payload needed for user_profile.php.
 */
function getStudentProfileDetails(mysqli $conn, int $student_id): ?array {
    $stmt = $conn->prepare("
        SELECT s.Student_Year, s.Student_Type, s.Credits_Earned,
               d.Department_Name,
               COALESCE(ug.Undergrad_Type, g.Grad_Type, 'Full-Time') AS Attendance_Type,
               g.Grad_Program, g.Thesis
        FROM STUDENT s
        LEFT JOIN DEPARTMENT d ON s.Dept_ID = d.Department_ID
        LEFT JOIN UNDERGRADUATE_STUDENT ug ON s.Student_ID = ug.Student_ID
        LEFT JOIN GRADUATE_STUDENT g ON s.Student_ID = g.Student_ID
        WHERE s.Student_ID = ?
        LIMIT 1
    ");
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    $studentData = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$studentData) {
        return null;
    }

    $yearMap = [
        1 => 'Freshman',
        2 => 'Sophomore',
        3 => 'Junior',
        4 => 'Senior',
        5 => 'Graduate',
    ];
    $yearVal = (int)($studentData['Student_Year'] ?? 1);
    $studentData['class_standing'] = (strcasecmp($studentData['Student_Type'] ?? '', 'Graduate') === 0)
        ? 'Graduate'
        : ($yearMap[$yearVal] ?? 'Undergraduate');

    $transcript = getStudentTranscriptData($conn, $student_id);
    $studentData['cum_gpa'] = $transcript['cum_gpa'];

    $mStmt = $conn->prepare("
        SELECT m.Major_Name
        FROM STUDENT_MAJORS sm
        JOIN MAJORS m ON sm.Major_ID = m.Major_ID
        WHERE sm.Student_ID = ?
        ORDER BY sm.Major_ID ASC
    ");
    $mStmt->bind_param("i", $student_id);
    $mStmt->execute();
    $mRes = $mStmt->get_result();
    $majors = [];
    while ($mRow = $mRes->fetch_assoc()) {
        $majors[] = $mRow['Major_Name'];
    }
    $mStmt->close();
    $studentData['majors'] = $majors;

    $minStmt = $conn->prepare("
        SELECT m.Minor_Name
        FROM STUDENT_MINORS sm
        JOIN MINORS m ON sm.Minor_ID = m.Minor_ID
        WHERE sm.Student_ID = ?
        ORDER BY sm.Minor_ID ASC
    ");
    $minStmt->bind_param("i", $student_id);
    $minStmt->execute();
    $minRes = $minStmt->get_result();
    $minors = [];
    while ($minRow = $minRes->fetch_assoc()) {
        $minors[] = $minRow['Minor_Name'];
    }
    $minStmt->close();
    $studentData['minor'] = $minors[0] ?? null;

    $advStmt = $conn->prepare("
        SELECT f.Faculty_ID, u.User_FName, u.User_LName, l.User_Email
        FROM ADVISOR_STUDENT a
        JOIN FACULTY f ON a.Faculty_ID = f.Faculty_ID
        JOIN `USER` u ON f.Faculty_ID = u.User_ID
        LEFT JOIN LOGIN l ON u.User_ID = l.User_ID
        WHERE a.Student_ID = ?
        LIMIT 1
    ");
    $advStmt->bind_param("i", $student_id);
    $advStmt->execute();
    $studentData['advisor'] = $advStmt->get_result()->fetch_assoc() ?: null;
    $advStmt->close();

    $holds = getStudentHolds($conn, $student_id);
    $studentData['holds'] = $holds;
    $studentData['holds_count'] = count($holds);

    return $studentData;
}

/**
 * Fetch enrolled schedule for a student in a specific semester.
 * MUST select: e.Midterm_Grade, e.Final_Grade, e.Grade, cs.Faculty_ID, cs.Status, c.Course_Description.
 */
function getStudentScheduleBySemester(mysqli $conn, int $student_id, int $semester_id): array {
    $stmt = $conn->prepare("
        SELECT e.CRN, cs.Course_ID, cs.Section_Number, cs.Semester_ID, cs.Faculty_ID, cs.Status,
               c.Course_Name, c.Course_Credits, c.Course_Type, c.Course_Description,
               dept.Department_Name,
               ts.Time_Slot_Day, p.Start_Time, p.End_Time,
               b.Building_Name, r.Room_NO,
               e.Midterm_Grade, e.Final_Grade, e.Grade,
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
        'sections'      => $courses,
        'crns'          => $crns,
        'course_ids'    => $course_ids,
        'total_credits' => $total_credits,
    ];
}

/**
 * Fetch past completed courses grouped by semester and compute GPA.
 */
function getStudentTranscriptData(mysqli $conn, int $student_id): array {
    $stmt = $conn->prepare("
        SELECT sh.Course_ID, sh.Semester_ID, sh.Grade,
               s.Semester_Name, s.Semester_Year, s.Start_Date,
               c.Course_Name, c.Course_Credits, c.Course_Type,
               st.Student_Type
        FROM STUDENT_HISTORY sh
        JOIN SEMESTER s ON sh.Semester_ID = s.Semester_ID
        JOIN COURSES c ON sh.Course_ID = c.Course_ID
        JOIN STUDENT st ON sh.Student_ID = st.Student_ID
        WHERE sh.Student_ID = ?
        ORDER BY s.Semester_Year ASC, s.Start_Date ASC, c.Course_ID ASC
    ");
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    $res = $stmt->get_result();

    $terms = [];
    $levelCreditsEarned = 0;

    while ($row = $res->fetch_assoc()) {
        $semId = (int)$row['Semester_ID'];
        $credits = (int)$row['Course_Credits'];
        $grade = trim($row['Grade']);
        $pt = getGradePoint($grade);

        $isPassing = ($pt !== null && $pt >= 1.0) || (strtoupper($grade) === 'P');
        $creditsEarned = $isPassing ? $credits : 0;

        $row['credits_attempted'] = $credits;
        $row['credits_earned'] = $creditsEarned;

        if ($isPassing && strcasecmp($row['Course_Type'] ?? '', $row['Student_Type'] ?? '') === 0) {
            $levelCreditsEarned += $creditsEarned;
        }

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

        if ($pt !== null) {
            $terms[$semId]['term_quality_points'] += ($credits * $pt);
            $terms[$semId]['term_graded_credits'] += $credits;
        }
    }
    $stmt->close();

    $cumQualityPoints = 0.0;
    $cumGradedCredits = 0;
    $totalCreditsEarned = 0;

    foreach ($terms as $semId => &$term) {
        $term['term_gpa'] = ($term['term_graded_credits'] > 0)
            ? round($term['term_quality_points'] / $term['term_graded_credits'], 2)
            : 0.0;

        $cumQualityPoints += $term['term_quality_points'];
        $cumGradedCredits += $term['term_graded_credits'];
        $totalCreditsEarned += $term['term_credits_earned'];
    }
    unset($term);

    $cumGpa = ($cumGradedCredits > 0) ? round($cumQualityPoints / $cumGradedCredits, 2) : 0.0;

    return [
        'terms' => $terms,
        'cum_gpa' => $cumGpa,
        'total_credits' => $totalCreditsEarned,
        'level_credits_earned' => $levelCreditsEarned,
    ];
}

/**
 * Helper to evaluate a list of requirements with detailed attempt metadata.
 */
function evaluateDetailedRequirements($reqList, $allCourseAttempts, $inProgressCourses) {
    $evaluated = [];

    foreach ($reqList as $req) {
        $cid = $req['Course_ID'];
        $minGrade = trim($req['Min_Grade']);
        $minPoint = getGradePoint($minGrade) ?? 2.0;

        $status = 'Unfulfilled';
        $badgeClass = 'text-bg-secondary';
        $gradeEarned = null;
        $termTaken = null;

        if (!empty($allCourseAttempts[$cid])) {
            $hasSatisfied = false;
            $bestAttempt = null;
            $bestPassingAttempt = null;
            $bestPoint = -1.0;
            $bestPassingPoint = -1.0;

            foreach ($allCourseAttempts[$cid] as $attempt) {
                $grade = strtoupper(trim((string)$attempt['Grade']));
                $pt = getGradePoint($grade);

                if ($grade === 'P' || ($pt !== null && $pt >= $minPoint)) {
                    $hasSatisfied = true;
                    if ($pt !== null && $pt > $bestPassingPoint) {
                        $bestPassingPoint = $pt;
                        $bestPassingAttempt = $attempt;
                    } elseif ($bestPassingAttempt === null) {
                        $bestPassingAttempt = $attempt;
                    }
                }

                if ($pt !== null && $pt > $bestPoint) {
                    $bestPoint = $pt;
                    $bestAttempt = $attempt;
                } elseif ($bestAttempt === null) {
                    $bestAttempt = $attempt;
                }
            }

            if ($hasSatisfied && $bestPassingAttempt) {
                $status = 'Completed';
                $badgeClass = 'text-bg-success';
                $gradeEarned = $bestPassingAttempt['Grade'];
                $termTaken = $bestPassingAttempt['Term_Label'];
            } elseif ($bestAttempt) {
                $status = 'Deficient';
                $badgeClass = 'text-bg-warning';
                $gradeEarned = $bestAttempt['Grade'];
                $termTaken = $bestAttempt['Term_Label'];
            }
        }

        if ($status !== 'Completed' && isset($inProgressCourses[$cid])) {
            $status = 'In Progress';
            $badgeClass = 'text-bg-primary';
            $gradeEarned = 'In Progress';
            $termTaken = $inProgressCourses[$cid]['Term_Label'];
        }

        $req['Status'] = $status;
        $req['Status_Badge_Class'] = $badgeClass;
        $req['Grade_Earned'] = $gradeEarned;
        $req['Term_Taken'] = $termTaken;

        $evaluated[] = $req;
    }

    return $evaluated;
}

/**
 * Compatibility helper to evaluate a list of requirements against completed history and active courses.
 */
function evaluateRequirementList($reqList, $historyCourses, $activeEnrolled) {
    $evaluated = [];

    foreach ($reqList as $req) {
        $cid = $req['Course_ID'];
        $minGrade = trim($req['Min_Grade']);
        $minPoint = getGradePoint($minGrade) ?? 2.0;

        $status = 'Unfulfilled';
        $badgeClass = 'text-bg-secondary';
        $gradeEarned = null;

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

            if ($hasSatisfied) {
                $status = 'Completed';
                $badgeClass = 'text-bg-success';
            } else {
                $status = 'Deficient';
                $badgeClass = 'text-bg-warning';
            }
        }

        if ($status !== 'Completed' && isset($activeEnrolled[$cid])) {
            $status = 'In Progress';
            $badgeClass = 'text-bg-primary';
            $gradeEarned = 'In Progress';
        }

        $req['Status'] = $status;
        $req['Status_Badge_Class'] = $badgeClass;
        $req['Grade_Earned'] = $gradeEarned;

        $evaluated[] = $req;
    }

    return $evaluated;
}

/**
 * Fetch detailed degree audit requirements, departmental groupings, and elective credit progress.
 */
function getDegreeAuditData(mysqli $conn, int $student_id): array {
    $stuStmt = $conn->prepare("SELECT Student_Type FROM STUDENT WHERE Student_ID = ?");
    $stuStmt->bind_param("i", $student_id);
    $stuStmt->execute();
    $stuRow = $stuStmt->get_result()->fetch_assoc();
    $stuStmt->close();
    $studentType = $stuRow['Student_Type'] ?? 'Undergraduate';

    // 1. Majors
    $stmt = $conn->prepare("
        SELECT m.Major_ID, m.Major_Name, m.No_Credits_Required, m.Department_ID, d.Department_Name
        FROM STUDENT_MAJORS sm
        JOIN MAJORS m ON sm.Major_ID = m.Major_ID
        LEFT JOIN DEPARTMENT d ON m.Department_ID = d.Department_ID
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

    // 2. Minors
    $stmt = $conn->prepare("
        SELECT m.Minor_ID, m.Minor_Name, m.No_Credits_Required, m.Department_ID, d.Department_Name
        FROM STUDENT_MINORS sm
        JOIN MINORS m ON sm.Minor_ID = m.Minor_ID
        LEFT JOIN DEPARTMENT d ON m.Department_ID = d.Department_ID
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

    // 3. Historical course attempts
    $hStmt = $conn->prepare("
        SELECT sh.CRN, sh.Course_ID, c.Course_Name, c.Course_Credits, c.Course_Type,
               c.Department_ID, d.Department_Name,
               sh.Semester_ID, sem.Semester_Name, sem.Semester_Year,
               sh.Grade
        FROM STUDENT_HISTORY sh
        JOIN COURSES c ON sh.Course_ID = c.Course_ID
        LEFT JOIN DEPARTMENT d ON c.Department_ID = d.Department_ID
        LEFT JOIN SEMESTER sem ON sh.Semester_ID = sem.Semester_ID
        WHERE sh.Student_ID = ?
        ORDER BY sem.Start_Date ASC, sh.CRN ASC
    ");
    $hStmt->bind_param("i", $student_id);
    $hStmt->execute();
    $hRes = $hStmt->get_result();
    $allCourseAttempts = [];
    while ($row = $hRes->fetch_assoc()) {
        $row['Term_Label'] = trim(($row['Semester_Name'] ?? '') . ' ' . ($row['Semester_Year'] ?? ''));
        $allCourseAttempts[$row['Course_ID']][] = $row;
    }
    $hStmt->close();

    // 4. Active enrollments
    $actStmt = $conn->prepare("
        SELECT e.CRN, cs.Course_ID, c.Course_Name, c.Course_Credits, c.Course_Type,
               c.Department_ID, d.Department_Name,
               cs.Semester_ID, sem.Semester_Name, sem.Semester_Year,
               e.Grade
        FROM ENROLLMENT e
        JOIN COURSE_SECTIONS cs ON e.CRN = cs.CRN
        JOIN COURSES c ON cs.Course_ID = c.Course_ID
        LEFT JOIN DEPARTMENT d ON c.Department_ID = d.Department_ID
        LEFT JOIN SEMESTER sem ON cs.Semester_ID = sem.Semester_ID
        WHERE e.Student_ID = ?
        ORDER BY sem.Start_Date ASC, cs.CRN ASC
    ");
    $actStmt->bind_param("i", $student_id);
    $actStmt->execute();
    $actRes = $actStmt->get_result();
    $inProgressCourses = [];
    while ($row = $actRes->fetch_assoc()) {
        if ($row['Grade'] !== null && trim($row['Grade']) !== '') {
            $row['Term_Label'] = trim(($row['Semester_Name'] ?? '') . ' ' . ($row['Semester_Year'] ?? ''));
            $allCourseAttempts[$row['Course_ID']][] = $row;
        } else {
            $row['Term_Label'] = trim(($row['Semester_Name'] ?? '') . ' ' . ($row['Semester_Year'] ?? '')) . ' (In Progress)';
            $inProgressCourses[$row['Course_ID']] = $row;
        }
    }
    $actStmt->close();

    // 5. Major requirements
    $majorsData = [];
    foreach ($majors as $m) {
        $mid = (int)$m['Major_ID'];
        $reqStmt = $conn->prepare("
            SELECT mr.Course_ID, mr.Min_Grade, c.Course_Name, c.Course_Credits, c.Course_Type, c.Department_ID, d.Department_Name
            FROM MAJOR_REQUIREMENTS mr
            JOIN COURSES c ON mr.Course_ID = c.Course_ID
            LEFT JOIN DEPARTMENT d ON c.Department_ID = d.Department_ID
            WHERE mr.Major_ID = ?
            ORDER BY d.Department_Name ASC, mr.Course_ID ASC
        ");
        $reqStmt->bind_param("i", $mid);
        $reqStmt->execute();
        $rRes = $reqStmt->get_result();
        $reqs = $rRes->fetch_all(MYSQLI_ASSOC);
        $reqStmt->close();

        $evaluatedReqs = evaluateDetailedRequirements($reqs, $allCourseAttempts, $inProgressCourses);
        $m['requirements'] = $evaluatedReqs;

        $byDept = [];
        foreach ($evaluatedReqs as $r) {
            $dName = $r['Department_Name'] ?: 'General Program Requirements';
            $byDept[$dName][] = $r;
        }
        $m['courses_by_department'] = $byDept;
        $majorsData[] = $m;
    }

    // 6. Minor requirements
    $minorsData = [];
    foreach ($minors as $min) {
        $minId = (int)$min['Minor_ID'];
        $reqStmt = $conn->prepare("
            SELECT mr.Course_ID, mr.Min_Grade, c.Course_Name, c.Course_Credits, c.Course_Type, c.Department_ID, d.Department_Name
            FROM MINOR_REQUIREMENTS mr
            JOIN COURSES c ON mr.Course_ID = c.Course_ID
            LEFT JOIN DEPARTMENT d ON c.Department_ID = d.Department_ID
            WHERE mr.Minor_ID = ?
            ORDER BY d.Department_Name ASC, mr.Course_ID ASC
        ");
        $reqStmt->bind_param("i", $minId);
        $reqStmt->execute();
        $rRes = $reqStmt->get_result();
        $reqs = $rRes->fetch_all(MYSQLI_ASSOC);
        $reqStmt->close();

        $evaluatedReqs = evaluateDetailedRequirements($reqs, $allCourseAttempts, $inProgressCourses);
        $min['requirements'] = $evaluatedReqs;

        $byDept = [];
        foreach ($evaluatedReqs as $r) {
            $dName = $r['Department_Name'] ?: 'General Program Requirements';
            $byDept[$dName][] = $r;
        }
        $min['courses_by_department'] = $byDept;
        $minorsData[] = $min;
    }

    // 7. Track Required Credits
    $allRequiredCourseIds = [];
    $uniqueRequiredCreditsTotal = 0;
    $uniqueRequiredCreditsCompleted = 0;
    $uniqueRequiredCreditsInProgress = 0;
    $courseCreditsMap = [];
    $courseStatusMap = [];

    foreach ($majorsData as $m) {
        foreach ($m['requirements'] as $req) {
            $cid = $req['Course_ID'];
            if (!in_array($cid, $allRequiredCourseIds, true)) {
                $allRequiredCourseIds[] = $cid;
                $courseCreditsMap[$cid] = (int)$req['Course_Credits'];
            }
            if ($req['Status'] === 'Completed') {
                $courseStatusMap[$cid] = 'Completed';
            } elseif ($req['Status'] === 'In Progress' && ($courseStatusMap[$cid] ?? '') !== 'Completed') {
                $courseStatusMap[$cid] = 'In Progress';
            }
        }
    }
    foreach ($minorsData as $min) {
        foreach ($min['requirements'] as $req) {
            $cid = $req['Course_ID'];
            if (!in_array($cid, $allRequiredCourseIds, true)) {
                $allRequiredCourseIds[] = $cid;
                $courseCreditsMap[$cid] = (int)$req['Course_Credits'];
            }
            if ($req['Status'] === 'Completed') {
                $courseStatusMap[$cid] = 'Completed';
            } elseif ($req['Status'] === 'In Progress' && ($courseStatusMap[$cid] ?? '') !== 'Completed') {
                $courseStatusMap[$cid] = 'In Progress';
            }
        }
    }

    foreach ($allRequiredCourseIds as $cid) {
        $cCredits = $courseCreditsMap[$cid] ?? 0;
        $uniqueRequiredCreditsTotal += $cCredits;
        $st = $courseStatusMap[$cid] ?? '';
        if ($st === 'Completed') {
            $uniqueRequiredCreditsCompleted += $cCredits;
        } elseif ($st === 'In Progress') {
            $uniqueRequiredCreditsInProgress += $cCredits;
        }
    }

    // 8. Track Electives matching student level
    $minPassPt = ($studentType === 'Graduate') ? 3.0 : 2.0;
    $electiveCourses = [];
    $completedElectives = [];
    $inProgressElectives = [];
    $seenElectives = [];

    foreach ($allCourseAttempts as $cid => $attempts) {
        if (in_array($cid, $allRequiredCourseIds, true)) {
            continue;
        }
        $first = $attempts[0];
        if (strcasecmp($first['Course_Type'] ?? '', $studentType) !== 0) {
            continue;
        }

        $bestPassed = null;
        $bestPt = -1.0;
        foreach ($attempts as $att) {
            $g = strtoupper(trim((string)$att['Grade']));
            $pt = getGradePoint($g);
            if ($g === 'P' || ($pt !== null && $pt >= $minPassPt)) {
                if ($pt !== null && $pt > $bestPt) {
                    $bestPt = $pt;
                    $bestPassed = $att;
                } elseif ($bestPassed === null) {
                    $bestPassed = $att;
                }
            }
        }

        if ($bestPassed) {
            $elItem = [
                'Course_ID'          => $cid,
                'Course_Name'        => $bestPassed['Course_Name'] ?? $cid,
                'Department_Name'    => $bestPassed['Department_Name'] ?? 'General',
                'Course_Credits'     => (int)$bestPassed['Course_Credits'],
                'Term_Taken'         => $bestPassed['Term_Label'],
                'Grade'              => $bestPassed['Grade'],
                'Status'             => 'Completed',
                'Status_Badge_Class' => 'text-bg-success',
            ];
            $electiveCourses[] = $elItem;
            $completedElectives[] = $elItem;
            $seenElectives[$cid] = true;
        }
    }

    foreach ($inProgressCourses as $cid => $ip) {
        if (in_array($cid, $allRequiredCourseIds, true) || isset($seenElectives[$cid])) {
            continue;
        }
        if (strcasecmp($ip['Course_Type'] ?? '', $studentType) !== 0) {
            continue;
        }
        $elItem = [
            'Course_ID'          => $cid,
            'Course_Name'        => $ip['Course_Name'] ?? $cid,
            'Department_Name'    => $ip['Department_Name'] ?? 'General',
            'Course_Credits'     => (int)$ip['Course_Credits'],
            'Term_Taken'         => $ip['Term_Label'],
            'Grade'              => 'In Progress',
            'Status'             => 'In Progress',
            'Status_Badge_Class' => 'text-bg-primary',
        ];
        $electiveCourses[] = $elItem;
        $inProgressElectives[] = $elItem;
        $seenElectives[$cid] = true;
    }

    $electiveCreditsCompleted = 0;
    foreach ($completedElectives as $el) {
        $electiveCreditsCompleted += (int)$el['Course_Credits'];
    }
    $electiveCreditsInProgress = 0;
    foreach ($inProgressElectives as $el) {
        $electiveCreditsInProgress += (int)$el['Course_Credits'];
    }

    $degreeCreditTarget = ($studentType === 'Undergraduate') ? 120 : (!empty($majorsData) ? (int)$majorsData[0]['No_Credits_Required'] : 24);
    $electiveCreditsNeededTotal = max(0, $degreeCreditTarget - $uniqueRequiredCreditsTotal);
    $electiveCreditsRemainingCompletedOnly = max(0, $electiveCreditsNeededTotal - $electiveCreditsCompleted);
    $electiveCreditsRemainingAfterInProgress = max(0, $electiveCreditsNeededTotal - $electiveCreditsCompleted - $electiveCreditsInProgress);

    $totalEarnedCredits = $uniqueRequiredCreditsCompleted + $electiveCreditsCompleted;
    $totalInProgressCredits = $uniqueRequiredCreditsInProgress + $electiveCreditsInProgress;
    $totalCreditsRemainingToTarget = max(0, $degreeCreditTarget - $totalEarnedCredits);
    $totalCreditsRemainingAfterInProgress = max(0, $degreeCreditTarget - $totalEarnedCredits - $totalInProgressCredits);

    return [
        'student_id'                               => $student_id,
        'student_type'                             => $studentType,
        'primary_major'                            => $majorsData[0] ?? null,
        'secondary_major'                          => $majorsData[1] ?? null,
        'minor'                                    => $minorsData[0] ?? null,
        'majors'                                   => $majorsData,
        'minors'                                   => $minorsData,
        'all_required_course_ids'                  => $allRequiredCourseIds,
        'unique_required_credits_total'            => $uniqueRequiredCreditsTotal,
        'unique_required_credits_completed'        => $uniqueRequiredCreditsCompleted,
        'unique_required_credits_in_progress'      => $uniqueRequiredCreditsInProgress,
        'elective_courses'                         => $electiveCourses,
        'completed_electives'                      => $completedElectives,
        'in_progress_electives'                    => $inProgressElectives,
        'degree_credit_target'                     => $degreeCreditTarget,
        'elective_credits_needed_total'            => $electiveCreditsNeededTotal,
        'elective_credits_completed'               => $electiveCreditsCompleted,
        'elective_credits_in_progress'             => $electiveCreditsInProgress,
        'elective_credits_remaining_completed_only'=> $electiveCreditsRemainingCompletedOnly,
        'elective_credits_remaining_after_in_progress' => $electiveCreditsRemainingAfterInProgress,
        'total_earned_credits'                     => $totalEarnedCredits,
        'total_in_progress_credits'                => $totalInProgressCredits,
        'total_credits_remaining_to_target'        => $totalCreditsRemainingToTarget,
        'total_credits_remaining_after_in_progress'=> $totalCreditsRemainingAfterInProgress,
    ];
}

/**
 * Fetch attendance records for a student across all enrolled sections in a semester.
 * Returns an array of course attendance blocks with summary totals and chronological history.
 */
function getStudentAttendanceRecords(mysqli $conn, int $student_id, int $semester_id = 202608): array {
    $student_id = (int)$student_id;
    $semester_id = (int)$semester_id;

    $stmt = $conn->prepare("
        SELECT cs.CRN, cs.Course_ID, c.Course_Name, cs.Section_Number,
               COALESCE(CONCAT(u.User_FName, ' ', u.User_LName), 'Staff') AS Instructor_Name
        FROM ENROLLMENT e
        JOIN COURSE_SECTIONS cs ON e.CRN = cs.CRN
        JOIN COURSES c ON cs.Course_ID = c.Course_ID
        LEFT JOIN FACULTY f ON cs.Faculty_ID = f.Faculty_ID
        LEFT JOIN `USER` u ON f.Faculty_ID = u.User_ID
        WHERE e.Student_ID = ? AND cs.Semester_ID = ?
        ORDER BY cs.Course_ID ASC, cs.Section_Number ASC
    ");
    $stmt->bind_param("ii", $student_id, $semester_id);
    $stmt->execute();
    $sectionsRes = $stmt->get_result();
    $sections = [];
    while ($row = $sectionsRes->fetch_assoc()) {
        $sections[] = $row;
    }
    $stmt->close();

    $attStmt = $conn->prepare("
        SELECT Class_Date, Status, Recorded_At
        FROM ATTENDANCE
        WHERE CRN = ? AND Student_ID = ?
        ORDER BY Class_Date DESC
    ");

    $result = [];
    foreach ($sections as $sec) {
        $crn = (int)$sec['CRN'];
        $attStmt->bind_param("ii", $crn, $student_id);
        $attStmt->execute();
        $attRes = $attStmt->get_result();

        $records = [];
        $present = 0;
        $absent = 0;
        while ($attRow = $attRes->fetch_assoc()) {
            $records[] = [
                'Class_Date'  => $attRow['Class_Date'],
                'Status'      => $attRow['Status'],
                'Recorded_At' => $attRow['Recorded_At'],
            ];
            if ($attRow['Status'] === 'Present') {
                $present++;
            } else {
                $absent++;
            }
        }

        $total = $present + $absent;
        $pct = $total > 0 ? round(($present / $total) * 100, 1) : 0.0;

        $result[] = [
            'CRN'            => $crn,
            'Course_ID'      => $sec['Course_ID'],
            'Course_Name'    => $sec['Course_Name'],
            'Section_Number' => (int)$sec['Section_Number'],
            'Instructor_Name'=> $sec['Instructor_Name'],
            'total_sessions' => $total,
            'present_count'  => $present,
            'absent_count'   => $absent,
            'attendance_pct' => $pct,
            'records'        => $records,
        ];
    }
    $attStmt->close();

    return $result;
}

/**
 * Prerequisite Gating & Auto-Drop Handler
 * Uses local $inTransaction boolean guard for safe rollback.
 */
function checkAndEnforceFailedPrerequisites(mysqli $conn, int $student_id): array {
    $student_id = (int)$student_id;
    $notices = [];

    $stuStmt = $conn->prepare("SELECT Student_Type FROM STUDENT WHERE Student_ID = ?");
    $stuStmt->bind_param("i", $student_id);
    $stuStmt->execute();
    $stuRow = $stuStmt->get_result()->fetch_assoc();
    $stuStmt->close();
    $minPassingPoint = ($stuRow && strcasecmp($stuRow['Student_Type'], 'Graduate') === 0) ? 3.0 : 2.0;

    $enrolledStmt = $conn->prepare("
        SELECT e.CRN, cs.Course_ID, c.Course_Name, cs.Semester_ID
        FROM ENROLLMENT e
        JOIN COURSE_SECTIONS cs ON e.CRN = cs.CRN
        JOIN COURSES c ON cs.Course_ID = c.Course_ID
        WHERE e.Student_ID = ? AND e.Grade IS NULL
    ");
    $enrolledStmt->bind_param("i", $student_id);
    $enrolledStmt->execute();
    $enrolledSections = $enrolledStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $enrolledStmt->close();

    if (empty($enrolledSections)) {
        return [];
    }

    foreach ($enrolledSections as $sec) {
        $crn = (int)$sec['CRN'];
        $course_id = $sec['Course_ID'];

        $prereqStmt = $conn->prepare("
            SELECT Prerequisite_Course_ID
            FROM COURSE_PREREQUISITE
            WHERE Course_ID = ?
        ");
        $prereqStmt->bind_param("s", $course_id);
        $prereqStmt->execute();
        $prereqs = $prereqStmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $prereqStmt->close();

        if (empty($prereqs)) {
            continue;
        }

        foreach ($prereqs as $pr) {
            $prereq_id = $pr['Prerequisite_Course_ID'];

            $histStmt = $conn->prepare("
                SELECT Grade FROM (
                    SELECT Grade FROM STUDENT_HISTORY WHERE Student_ID = ? AND Course_ID = ?
                    UNION ALL
                    SELECT Grade FROM ENROLLMENT e JOIN COURSE_SECTIONS cs ON e.CRN = cs.CRN 
                    WHERE e.Student_ID = ? AND cs.Course_ID = ? AND e.Grade IS NOT NULL
                ) t
            ");
            $histStmt->bind_param("isis", $student_id, $prereq_id, $student_id, $prereq_id);
            $histStmt->execute();
            $gradesRes = $histStmt->get_result();
            $grades = [];
            while ($gRow = $gradesRes->fetch_assoc()) {
                $grades[] = $gRow['Grade'];
            }
            $histStmt->close();

            $hasPassed = false;
            $hasFailed = false;
            $lastFailedGrade = null;

            foreach ($grades as $g) {
                $pt = getGradePoint($g);
                if (strtoupper(trim((string)$g)) === 'P' || ($pt !== null && $pt >= $minPassingPoint)) {
                    $hasPassed = true;
                    break;
                } else {
                    $hasFailed = true;
                    $lastFailedGrade = $g;
                }
            }

            $ipStmt = $conn->prepare("
                SELECT 1
                FROM ENROLLMENT e
                JOIN COURSE_SECTIONS cs ON e.CRN = cs.CRN
                WHERE e.Student_ID = ? AND cs.Course_ID = ? AND e.CRN != ? AND e.Grade IS NULL
                LIMIT 1
            ");
            $ipStmt->bind_param("isi", $student_id, $prereq_id, $crn);
            $ipStmt->execute();
            $isInProgress = $ipStmt->get_result()->fetch_row();
            $ipStmt->close();

            if (!$hasPassed && !$isInProgress) {
                $inTransaction = false;
                try {
                    $conn->begin_transaction();
                    $inTransaction = true;

                    $lock = $conn->prepare("SELECT Available_seats FROM COURSE_SECTIONS WHERE CRN = ? FOR UPDATE");
                    $lock->bind_param("i", $crn);
                    $lock->execute();
                    $lock->close();

                    $del = $conn->prepare("DELETE FROM ENROLLMENT WHERE CRN = ? AND Student_ID = ?");
                    $del->bind_param("ii", $crn, $student_id);
                    $del->execute();
                    $affected = $del->affected_rows;
                    $del->close();

                    if ($affected === 1) {
                        $inc = $conn->prepare("UPDATE COURSE_SECTIONS SET Available_seats = Available_seats + 1 WHERE CRN = ?");
                        $inc->bind_param("i", $crn);
                        $inc->execute();
                        $inc->close();

                        $conn->commit();
                        $inTransaction = false;

                        if ($hasFailed) {
                            $notices[] = "You were automatically dropped from {$course_id} - {$sec['Course_Name']} (CRN: {$crn}) because prerequisite {$prereq_id} was not passed (Grade: {$lastFailedGrade}).";
                        } else {
                            $notices[] = "You were automatically dropped from {$course_id} - {$sec['Course_Name']} (CRN: {$crn}) because required prerequisite {$prereq_id} is no longer completed or in progress.";
                        }
                    } else {
                        if ($inTransaction) {
                            $conn->rollback();
                            $inTransaction = false;
                        }
                    }
                } catch (Exception $e) {
                    if ($inTransaction) {
                        $conn->rollback();
                        $inTransaction = false;
                    }
                }
                break;
            }
        }
    }

    return $notices;
}
