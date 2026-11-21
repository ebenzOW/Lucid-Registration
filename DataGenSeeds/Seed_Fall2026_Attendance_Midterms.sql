-- ============================================================================
-- Lucid University: Fall 2026 Attendance & Midterm Grades Seeder
-- Idempotent, cross-compatible with MariaDB 10.2+ and MySQL 8.0+
-- ============================================================================

-- 1. Seed Fall 2026 (202608) Midterm Grades
-- Populate Midterm_Grade for Undergraduates (mostly A/B/C with ~10% C-/D/F)
UPDATE ENROLLMENT e
JOIN COURSE_SECTIONS cs ON e.CRN = cs.CRN
JOIN STUDENT s ON e.Student_ID = s.Student_ID
SET e.Midterm_Grade = CASE
    WHEN (MOD(e.Student_ID * 17 + e.CRN * 31, 100)) < 15 THEN 'A'
    WHEN (MOD(e.Student_ID * 17 + e.CRN * 31, 100)) < 30 THEN 'A-'
    WHEN (MOD(e.Student_ID * 17 + e.CRN * 31, 100)) < 45 THEN 'B+'
    WHEN (MOD(e.Student_ID * 17 + e.CRN * 31, 100)) < 60 THEN 'B'
    WHEN (MOD(e.Student_ID * 17 + e.CRN * 31, 100)) < 72 THEN 'B-'
    WHEN (MOD(e.Student_ID * 17 + e.CRN * 31, 100)) < 81 THEN 'C+'
    WHEN (MOD(e.Student_ID * 17 + e.CRN * 31, 100)) < 90 THEN 'C'
    WHEN (MOD(e.Student_ID * 17 + e.CRN * 31, 100)) < 93 THEN 'C-'
    WHEN (MOD(e.Student_ID * 17 + e.CRN * 31, 100)) < 96 THEN 'D+'
    WHEN (MOD(e.Student_ID * 17 + e.CRN * 31, 100)) < 98 THEN 'D'
    ELSE 'F'
END,
e.Final_Grade = NULL,
e.Grade = NULL
WHERE cs.Semester_ID = 202608
  AND s.Student_Type = 'Undergraduate'
  AND e.Midterm_Grade IS NULL;

-- Populate Midterm_Grade for Graduates (mostly A/B with ~8% B-/C+)
UPDATE ENROLLMENT e
JOIN COURSE_SECTIONS cs ON e.CRN = cs.CRN
JOIN STUDENT s ON e.Student_ID = s.Student_ID
SET e.Midterm_Grade = CASE
    WHEN (MOD(e.Student_ID * 17 + e.CRN * 31, 100)) < 28 THEN 'A'
    WHEN (MOD(e.Student_ID * 17 + e.CRN * 31, 100)) < 52 THEN 'A-'
    WHEN (MOD(e.Student_ID * 17 + e.CRN * 31, 100)) < 72 THEN 'B+'
    WHEN (MOD(e.Student_ID * 17 + e.CRN * 31, 100)) < 92 THEN 'B'
    WHEN (MOD(e.Student_ID * 17 + e.CRN * 31, 100)) < 97 THEN 'B-'
    ELSE 'C+'
END,
e.Final_Grade = NULL,
e.Grade = NULL
WHERE cs.Semester_ID = 202608
  AND s.Student_Type = 'Graduate'
  AND e.Midterm_Grade IS NULL;

-- 2. Seed Fall 2026 (202608) Attendance Records
-- Generates all meeting dates from SEMESTER.Start_Date through yesterday (excluding today)
INSERT IGNORE INTO ATTENDANCE (CRN, Student_ID, Class_Date, Status, Recorded_At)
WITH RECURSIVE calendar AS (
    SELECT CAST('2026-08-31' AS DATE) AS dt
    UNION ALL
    SELECT DATE_ADD(dt, INTERVAL 1 DAY)
    FROM calendar
    WHERE dt < LEAST('2026-12-21', DATE_SUB(CURRENT_DATE(), INTERVAL 1 DAY))
)
SELECT 
    e.CRN, 
    e.Student_ID, 
    c.dt AS Class_Date,
    CASE 
        WHEN MOD(e.Student_ID * 13 + e.CRN * 7 + DATEDIFF(c.dt, '2026-08-31'), 10) = 0 THEN 'Absent' 
        ELSE 'Present' 
    END AS Status,
    TIMESTAMP(c.dt, p.Start_Time) AS Recorded_At
FROM ENROLLMENT e
JOIN COURSE_SECTIONS cs ON e.CRN = cs.CRN
JOIN SEMESTER s ON cs.Semester_ID = s.Semester_ID
JOIN TIME_SLOT ts ON cs.Time_Slot_ID = ts.Time_Slot_ID
JOIN TIME_SLOT_DAY tsd ON ts.Time_Slot_ID = tsd.Time_Slot_ID
JOIN DAY d ON tsd.Day_ID = d.Day_ID
JOIN PERIOD p ON ts.Period_ID = p.Period_ID
JOIN calendar c ON DAYNAME(c.dt) = d.Weekday
WHERE cs.Semester_ID = 202608;
