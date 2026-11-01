-- ============================================================================
-- LUCID UNIVERSITY: STUDENT HOLDS SEED DATA
-- Run AFTER UnderGradUserGen.sql and GradUserGen.sql.
-- Randomly assigns holds to a realistic subset of the student body.
-- ============================================================================

START TRANSACTION;

-- Clear existing holds if rerunning
DELETE FROM STUDENT_HOLDS;

-- 1. Financial Holds (Hold_ID 1) - ~5% of all students (Assigned early August 2026)
INSERT IGNORE INTO STUDENT_HOLDS (Hold_ID, Student_ID, Hold_Date)
SELECT 1, Student_ID, DATE_ADD('2026-08-01', INTERVAL FLOOR(RAND() * 30) DAY)
FROM STUDENT
WHERE RAND() <= 0.05;

-- 2. Academic Holds (Hold_ID 2) - ~3% of all students (Assigned mid-August 2026)
INSERT IGNORE INTO STUDENT_HOLDS (Hold_ID, Student_ID, Hold_Date)
SELECT 2, Student_ID, DATE_ADD('2026-08-15', INTERVAL FLOOR(RAND() * 15) DAY)
FROM STUDENT
WHERE RAND() <= 0.03;

-- 3. Health Holds (Hold_ID 3) - ~2% of all students (e.g., missing immunization records)
INSERT IGNORE INTO STUDENT_HOLDS (Hold_ID, Student_ID, Hold_Date)
SELECT 3, Student_ID, '2026-08-20'
FROM STUDENT
WHERE RAND() <= 0.02;

-- 4. Disciplinary Holds (Hold_ID 4) - ~1% of all students (Assigned dynamically into the semester)
INSERT IGNORE INTO STUDENT_HOLDS (Hold_ID, Student_ID, Hold_Date)
SELECT 4, Student_ID, DATE_ADD('2026-09-01', INTERVAL FLOOR(RAND() * 60) DAY)
FROM STUDENT
WHERE RAND() <= 0.01;

COMMIT;