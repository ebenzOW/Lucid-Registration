DELIMITER $$

DROP PROCEDURE IF EXISTS SeedActiveFall2026Enrollments$$
CREATE PROCEDURE SeedActiveFall2026Enrollments()
BEGIN
    DECLARE v_done INT DEFAULT FALSE;
    DECLARE v_sid INT;
    DECLARE v_stype VARCHAR(20);
    DECLARE v_mode VARCHAR(20);
    DECLARE v_dept INT;
    DECLARE v_target_courses INT;
    DECLARE v_enrolled INT;
    DECLARE v_crn INT;

    DECLARE cur_students CURSOR FOR 
        SELECT s.Student_ID, s.Student_Type, 
               IF(ftu.Student_ID IS NOT NULL OR ftg.Student_ID IS NOT NULL, 'FT', 'PT') AS Mode,
               s.Dept_ID
        FROM STUDENT s
        LEFT JOIN FULL_TIME_UNDERGRADUATE ftu ON s.Student_ID = ftu.Student_ID
        LEFT JOIN FULL_TIME_GRADUATE ftg ON s.Student_ID = ftg.Student_ID;
        
    DECLARE CONTINUE HANDLER FOR NOT FOUND SET v_done = TRUE;

    START TRANSACTION;
    DELETE FROM ENROLLMENT WHERE CRN IN (SELECT CRN FROM COURSE_SECTIONS WHERE Semester_ID = 202608);
    -- Why: Reset seat counts cleanly on re-runs so Fall 2026 course sections restore full 10-seat capacity.
    UPDATE COURSE_SECTIONS SET Available_seats = 10 WHERE Semester_ID = 202608;

    OPEN cur_students;
    student_loop: LOOP
        -- Why: Reset cursor termination flag before fetching the next student to avoid premature loop termination.
        SET v_done = FALSE;
        FETCH cur_students INTO v_sid, v_stype, v_mode, v_dept;
        IF v_done THEN LEAVE student_loop; END IF;

        -- Undergrad FT: 4 courses (16 cr), Grad FT: 3 courses (12 cr), PT: 2 courses (8 cr)
        SET v_target_courses = IF(v_stype = 'Graduate', IF(v_mode = 'FT', 3, 2), IF(v_mode = 'FT', 4, 2));
        SET v_enrolled = 0;

        pick_loop: WHILE v_enrolled < v_target_courses DO
            SET v_crn = NULL;

            SELECT cs.CRN INTO v_crn
            FROM COURSE_SECTIONS cs
            JOIN COURSES c ON cs.Course_ID = c.Course_ID
            WHERE cs.Semester_ID = 202608
              AND c.Course_Type = v_stype
              -- Enforce major/minor/department rule (including cross-department major and minor requirements)
              AND (
                  c.Department_ID IN (SELECT m.Department_ID FROM STUDENT_MAJORS sm JOIN MAJORS m ON sm.Major_ID = m.Major_ID WHERE sm.Student_ID = v_sid)
                  OR c.Department_ID IN (SELECT mn.Department_ID FROM STUDENT_MINORS smn JOIN MINORS mn ON smn.Minor_ID = mn.Minor_ID WHERE smn.Student_ID = v_sid)
                  OR c.Course_ID IN (SELECT mr.Course_ID FROM STUDENT_MAJORS sm JOIN MAJOR_REQUIREMENTS mr ON sm.Major_ID = mr.Major_ID WHERE sm.Student_ID = v_sid)
                  OR c.Course_ID IN (SELECT mnr.Course_ID FROM STUDENT_MINORS smn JOIN MINOR_REQUIREMENTS mnr ON smn.Minor_ID = mnr.Minor_ID WHERE smn.Student_ID = v_sid)
              )
              -- Strict seat cap check
              AND cs.Available_seats > 0
              -- Not already registered for this course in Fall 2026
              AND NOT EXISTS (
                  SELECT 1 FROM ENROLLMENT e2 JOIN COURSE_SECTIONS cs2 ON e2.CRN = cs2.CRN 
                  WHERE e2.Student_ID = v_sid AND cs2.Course_ID = cs.Course_ID
              )
              -- Not already passed in past history
              AND NOT EXISTS (
                  SELECT 1 FROM STUDENT_HISTORY h WHERE h.Student_ID = v_sid AND h.Course_ID = cs.Course_ID
              )
              -- Every prerequisite finished in past history
              AND NOT EXISTS (
                  SELECT 1 FROM COURSE_PREREQUISITE cp
                  WHERE cp.Course_ID = cs.Course_ID
                    AND NOT EXISTS (
                        SELECT 1 FROM STUDENT_HISTORY h
                        WHERE h.Student_ID = v_sid
                          AND h.Course_ID = cp.Prerequisite_Course_ID
                    )
              )
              -- No time-slot conflict with already chosen Fall 2026 classes
              AND NOT EXISTS (
                  SELECT 1 FROM ENROLLMENT e3 JOIN COURSE_SECTIONS cs3 ON e3.CRN = cs3.CRN 
                  WHERE e3.Student_ID = v_sid AND cs3.Time_Slot_ID = cs.Time_Slot_ID
              )
            ORDER BY RAND()
            LIMIT 1;

            -- Why: Reset v_done to FALSE because a NOT FOUND condition in SELECT INTO sets v_done=TRUE, which would otherwise abort student_loop.
            SET v_done = FALSE;

            IF v_crn IS NULL THEN
                LEAVE pick_loop;
            END IF;

            INSERT INTO ENROLLMENT (CRN, Student_ID, Grade) VALUES (v_crn, v_sid, NULL);
            UPDATE COURSE_SECTIONS SET Available_seats = Available_seats - 1 WHERE CRN = v_crn;

            SET v_enrolled = v_enrolled + 1;
        END WHILE pick_loop;

    END LOOP student_loop;
    CLOSE cur_students;

    COMMIT;
END$$

DELIMITER ;

CALL SeedActiveFall2026Enrollments();
DROP PROCEDURE IF EXISTS SeedActiveFall2026Enrollments;