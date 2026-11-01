-- ============================================================================

DELIMITER $$

DROP PROCEDURE IF EXISTS sh_plan_add$$
CREATE PROCEDURE sh_plan_add(IN p_sid INT, IN p_start INT, IN p_cnt INT,
                             IN p_level VARCHAR(20), IN p_max INT, IN p_init INT)
BEGIN
    DECLARE v_i INT DEFAULT 0;
    WHILE v_i < p_cnt DO
        INSERT INTO tmp_plan (Student_ID, Sem_Idx, Semester_ID, Level, Max_Load, Target_Load)
        VALUES (p_sid, p_start + v_i,
                ELT(p_start + v_i, 202208, 202301, 202308, 202401, 202408, 202501, 202508, 202601),
                p_level, p_max, p_init);
        SET v_i = v_i + 1;
    END WHILE;
END$$

-- Raises random semesters (that still have room) by one course until the level's total hits p_total
DROP PROCEDURE IF EXISTS sh_plan_fill$$
CREATE PROCEDURE sh_plan_fill(IN p_sid INT, IN p_level VARCHAR(20), IN p_total INT)
BEGIN
    DECLARE v_sum INT;
    DECLARE v_guard INT DEFAULT 0;
    SELECT COALESCE(SUM(Target_Load), 0) INTO v_sum
    FROM tmp_plan WHERE Student_ID = p_sid AND Level = p_level;

    WHILE v_sum < p_total AND v_guard < 100 DO
        UPDATE tmp_plan SET Target_Load = Target_Load + 1
        WHERE Student_ID = p_sid AND Level = p_level AND Target_Load < Max_Load
        ORDER BY RAND() LIMIT 1;
        IF ROW_COUNT() = 0 THEN
            SET v_guard = 100;
        ELSE
            SET v_sum = v_sum + 1;
        END IF;
        SET v_guard = v_guard + 1;
    END WHILE;
END$$

DROP PROCEDURE IF EXISTS SeedStudentHistory$$
CREATE PROCEDURE SeedStudentHistory()
BEGIN
    -- plan building
    DECLARE v_prev INT DEFAULT 0;
    DECLARE v_sid INT;
    DECLARE v_level VARCHAR(20);
    DECLARE v_mode VARCHAR(2);
    DECLARE v_prog VARCHAR(10);
    DECLARE v_n INT;
    DECLARE v_cont INT;
    DECLARE v_lo INT;
    DECLARE v_hi INT;
    DECLARE v_s INT;
    DECLARE v_f INT;
    DECLARE v_fmin INT;
    DECLARE v_fmax INT;
    DECLARE v_p INT;
    DECLARE v_plo INT;
    DECLARE v_phi INT;
    DECLARE v_g INT;
    DECLARE v_nu INT;
    DECLARE v_u INT;
    -- enrollment loop
    DECLARE v_sem_idx INT;
    DECLARE v_semester_id INT;
    DECLARE v_ord INT;
    DECLARE v_next INT;
    DECLARE v_target INT;
    DECLARE v_dept INT;
    DECLARE v_ability DECIMAL(4,3);
    DECLARE v_got INT;
    DECLARE v_short INT;
    DECLARE v_crn INT;
    DECLARE v_course VARCHAR(15);
    DECLARE v_pri INT;
    DECLARE v_pref_other INT;
    DECLARE v_salt VARCHAR(40);
    DECLARE v_x DECIMAL(6,4);
    DECLARE v_floor DECIMAL(4,2);
    DECLARE v_grade VARCHAR(2);

    IF (SELECT COUNT(*) FROM COURSE_SECTIONS WHERE Semester_ID < 202608) = 0 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'No past COURSE_SECTIONS found. Run the section seeder first.';
    END IF;

    -- ------------------------------------------------------------------------
    -- Work tables
    -- ------------------------------------------------------------------------
    DROP TEMPORARY TABLE IF EXISTS tmp_stu;
    DROP TEMPORARY TABLE IF EXISTS tmp_plan;
    DROP TEMPORARY TABLE IF EXISTS tmp_req;
    DROP TEMPORARY TABLE IF EXISTS tmp_course;
    DROP TEMPORARY TABLE IF EXISTS tmp_order;

    CREATE TEMPORARY TABLE tmp_stu (
        Student_ID INT PRIMARY KEY,
        Level      VARCHAR(20),
        Mode       VARCHAR(2),
        Dept_ID    INT,
        Program    VARCHAR(10),
        N_Target   INT,
        Continuing TINYINT DEFAULT 0,
        Ability    DECIMAL(4,3)
    );

    CREATE TEMPORARY TABLE tmp_plan (
        Student_ID  INT,
        Sem_Idx     INT,
        Semester_ID INT,
        Level       VARCHAR(20),
        Max_Load    INT,
        Target_Load INT,
        PRIMARY KEY (Student_ID, Sem_Idx)
    );

    CREATE TEMPORARY TABLE tmp_req (
        Student_ID INT,
        Course_ID  VARCHAR(15),
        Pri        TINYINT,
        PRIMARY KEY (Student_ID, Course_ID)
    );

    CREATE TEMPORARY TABLE tmp_course (
        Course_ID     VARCHAR(15) PRIMARY KEY,
        Course_Type   VARCHAR(20),
        Department_ID INT,
        Band          INT
    );

    INSERT INTO tmp_course (Course_ID, Course_Type, Department_ID, Band)
    SELECT Course_ID, Course_Type, Department_ID,
           FLOOR(CAST(RIGHT(Course_ID, 3) AS UNSIGNED) / 100)
    FROM COURSES;

   

    -- Students (full-time / part-time comes from the subtype tables, so it works for any spelling)
    INSERT INTO tmp_stu (Student_ID, Level, Mode, Dept_ID, Program, N_Target, Continuing, Ability)
    SELECT s.Student_ID, s.Student_Type,
           CASE WHEN ftu.Student_ID IS NOT NULL OR ftg.Student_ID IS NOT NULL THEN 'FT' ELSE 'PT' END,
           s.Dept_ID,
           CASE WHEN s.Student_Type = 'Undergraduate' THEN 'BS'
                WHEN UPPER(g.Grad_Program) = 'PHD' THEN 'PHD'
                ELSE 'MS' END,
           COALESCE(s.Credits_Earned, 0) DIV 4,
           0,
           ROUND(RAND(), 3)
    FROM STUDENT s
    LEFT JOIN FULL_TIME_UNDERGRADUATE ftu ON ftu.Student_ID = s.Student_ID
    LEFT JOIN FULL_TIME_GRADUATE ftg      ON ftg.Student_ID = s.Student_ID
    LEFT JOIN GRADUATE_STUDENT g          ON g.Student_ID  = s.Student_ID;

    -- ~35% of M.S. students continued from a Lucid bachelor's degree
    UPDATE tmp_stu SET Continuing = 1 WHERE Program = 'MS' AND RAND() < 0.35;

    -- Required / minor courses per student (lowest priority number wins)
    INSERT IGNORE INTO tmp_req (Student_ID, Course_ID, Pri)
    SELECT sm.Student_ID, mr.Course_ID, 0
    FROM STUDENT_MAJORS sm JOIN MAJOR_REQUIREMENTS mr ON mr.Major_ID = sm.Major_ID;

    -- continuing grads also follow the B.S./B.A. requirements of their home department
    INSERT IGNORE INTO tmp_req (Student_ID, Course_ID, Pri)
    SELECT st.Student_ID, mr.Course_ID, 0
    FROM tmp_stu st
    JOIN MAJORS m ON m.Department_ID = st.Dept_ID AND m.Major_Name LIKE '%(B.%'
    JOIN MAJOR_REQUIREMENTS mr ON mr.Major_ID = m.Major_ID
    WHERE st.Continuing = 1;

    INSERT IGNORE INTO tmp_req (Student_ID, Course_ID, Pri)
    SELECT sm.Student_ID, mr.Course_ID, 1
    FROM STUDENT_MINORS sm JOIN MINOR_REQUIREMENTS mr ON mr.Minor_ID = sm.Minor_ID;

    -- ------------------------------------------------------------------------
    -- PHASE 1: semester plan for every student (Sem_Idx 1 = Fall 2022 .. 8 = Spring 2026)
    -- ------------------------------------------------------------------------
    plan_loop: LOOP
        SET v_sid = NULL;
        SELECT MIN(Student_ID) INTO v_sid FROM tmp_stu WHERE Student_ID > v_prev;
        IF v_sid IS NULL THEN LEAVE plan_loop; END IF;
        SET v_prev = v_sid;

        SELECT Level, Mode, Program, N_Target, Continuing
        INTO v_level, v_mode, v_prog, v_n, v_cont
        FROM tmp_stu WHERE Student_ID = v_sid;

        IF v_level = 'Undergraduate' THEN

            IF v_n > 0 THEN
                IF v_mode = 'FT' THEN
                    -- full-time: 3-4 courses a term, never more than 8 terms
                    SET v_n  = LEAST(v_n, 32);
                    SET v_lo = CEIL(v_n / 4);
                    SET v_hi = LEAST(8, GREATEST(v_lo, FLOOR(v_n / 3)));
                    SET v_s  = LEAST(v_n, v_lo + FLOOR(RAND() * (v_hi - v_lo + 1)));
                    CALL sh_plan_add(v_sid, 9 - v_s, v_s, 'Undergraduate', 4, LEAST(3, v_n DIV v_s));
                    CALL sh_plan_fill(v_sid, 'Undergraduate', v_n);
                ELSE
                    -- part-time now: possibly full-time in the earliest f terms
                    SET v_n = LEAST(v_n, 30);
                    IF v_n > 16 THEN
                        SET v_fmin = GREATEST(0, CEIL(v_n / 2 - 8));
                        SET v_fmax = LEAST(7, (v_n - 1) DIV 4);
                        SET v_f = v_fmin + FLOOR(RAND() * (GREATEST(v_fmax, v_fmin) - v_fmin + 1));
                    ELSEIF v_n >= 8 AND RAND() < 0.35 THEN
                        SET v_f = 1 + FLOOR(RAND() * LEAST(2, (v_n - 1) DIV 4));
                    ELSE
                        SET v_f = 0;
                    END IF;
                    SET v_plo = CEIL(GREATEST(v_n - 4 * v_f, 1) / 2);
                    SET v_phi = LEAST(8 - v_f, v_n - 3 * v_f);
                    SET v_p   = LEAST(GREATEST(v_phi, v_plo), v_plo + IF(RAND() < 0.3, 1, 0));
                    SET v_s   = v_f + v_p;
                    CALL sh_plan_add(v_sid, 9 - v_s, v_f, 'Undergraduate', 4, IF(3 * v_f + v_p <= v_n, 3, 1));
                    CALL sh_plan_add(v_sid, 9 - v_s + v_f, v_p, 'Undergraduate', 2, 1);
                    CALL sh_plan_fill(v_sid, 'Undergraduate', v_n);
                END IF;
            END IF;

        ELSE
            -- Graduate student: v_n = graduate courses completed so far
            SET v_g = 0;
            IF v_n > 0 THEN
                IF v_mode = 'FT' THEN
                    SET v_lo = CEIL(v_n / 3);
                    SET v_hi = LEAST(8, GREATEST(v_lo, CEIL(v_n / 2)));
                    SET v_g  = LEAST(v_n, v_lo + FLOOR(RAND() * (v_hi - v_lo + 1)));
                    CALL sh_plan_add(v_sid, 9 - v_g, v_g, 'Graduate', 3, LEAST(2, v_n DIV v_g));
                ELSE
                    SET v_lo = CEIL(v_n / 2);
                    SET v_hi = LEAST(8, v_n);
                    SET v_g  = LEAST(v_hi, v_lo + IF(RAND() < 0.3, 1, 0));
                    CALL sh_plan_add(v_sid, 9 - v_g, v_g, 'Graduate', 2, 1);
                END IF;
                CALL sh_plan_fill(v_sid, 'Graduate', v_n);
            END IF;

            IF v_cont = 1 THEN
                -- bachelor's finished at Lucid before the graduate terms start (always full-time)
                SET v_nu = LEAST(4 * (8 - v_g), 20 + FLOOR(RAND() * 9));
                SET v_lo = CEIL(v_nu / 4);
                SET v_hi = GREATEST(v_lo, LEAST(8 - v_g, v_nu DIV 3));
                SET v_u  = v_lo + FLOOR(RAND() * (v_hi - v_lo + 1));
                CALL sh_plan_add(v_sid, 9 - v_g - v_u, v_u, 'Undergraduate', 4, LEAST(3, v_nu DIV v_u));
                CALL sh_plan_fill(v_sid, 'Undergraduate', v_nu);
            END IF;
        END IF;
    END LOOP plan_loop;

    -- ------------------------------------------------------------------------
    -- PHASE 2: walk the terms in order and enroll students in real sections
    -- ------------------------------------------------------------------------
    DELETE FROM STUDENT_HISTORY;
    SET @sh_shortfall = 0;
    SET v_sem_idx = 1;

    WHILE v_sem_idx <= 8 DO
        SET v_semester_id = ELT(v_sem_idx, 202208, 202301, 202308, 202401, 202408, 202501, 202508, 202601);

        DROP TEMPORARY TABLE IF EXISTS tmp_order;
        CREATE TEMPORARY TABLE tmp_order (Ord INT AUTO_INCREMENT PRIMARY KEY, Student_ID INT);
        -- most constrained students first (fewest finished courses = fewest options), random within ties
        INSERT INTO tmp_order (Student_ID)
        SELECT p.Student_ID FROM tmp_plan p
        WHERE p.Sem_Idx = v_sem_idx
        ORDER BY (SELECT COUNT(*) FROM STUDENT_HISTORY h WHERE h.Student_ID = p.Student_ID), RAND();

        SET v_ord = 0;
        student_loop: LOOP
            SET v_next = NULL;
            SELECT MIN(Ord) INTO v_next FROM tmp_order WHERE Ord > v_ord;
            IF v_next IS NULL THEN LEAVE student_loop; END IF;
            SET v_ord = v_next;

            SELECT Student_ID INTO v_sid FROM tmp_order WHERE Ord = v_ord;

            SELECT p.Level, p.Target_Load, s.Dept_ID, s.Ability
            INTO v_level, v_target, v_dept, v_ability
            FROM tmp_plan p JOIN tmp_stu s ON s.Student_ID = p.Student_ID
            WHERE p.Student_ID = v_sid AND p.Sem_Idx = v_sem_idx;

            SET v_got = 0;
            SET v_short = 0;

            pick_loop: WHILE v_got < v_target DO
                SET v_crn = NULL;
                SET v_course = NULL;
                SET v_pri = NULL;
                SET v_salt = CAST(RAND() AS CHAR);

                SELECT cs.CRN, cs.Course_ID, rq.Pri
                INTO v_crn, v_course, v_pri
                FROM COURSE_SECTIONS cs
                JOIN tmp_course tc ON tc.Course_ID = cs.Course_ID
                LEFT JOIN tmp_req rq ON rq.Student_ID = v_sid AND rq.Course_ID = cs.Course_ID
                WHERE cs.Semester_ID = v_semester_id
                  AND COALESCE(cs.`Status`, 'Active') = 'Active'
                  AND tc.Course_Type = v_level
                  -- STRICT PROGRAM GATE: Must be a required major/minor course OR from own department
                  AND (rq.Student_ID IS NOT NULL OR tc.Department_ID = v_dept)
                  -- seat available
                  AND (SELECT COUNT(*) FROM STUDENT_HISTORY h WHERE h.CRN = cs.CRN) < cs.Available_seats
                  -- never repeat a course
                  AND NOT EXISTS (SELECT 1 FROM STUDENT_HISTORY h
                                  WHERE h.Student_ID = v_sid AND h.Course_ID = cs.Course_ID)
                  -- no time-slot conflict with this term's other courses
                  AND NOT EXISTS (SELECT 1 FROM STUDENT_HISTORY h
                                  JOIN COURSE_SECTIONS c2 ON c2.CRN = h.CRN
                                  WHERE h.Student_ID = v_sid AND h.Semester_ID = v_semester_id
                                    AND c2.Time_Slot_ID = cs.Time_Slot_ID)
                  -- every prerequisite finished in a STRICTLY EARLIER semester
                  AND NOT EXISTS (SELECT 1 FROM COURSE_PREREQUISITE cp
                                  WHERE cp.Course_ID = cs.Course_ID
                                    AND NOT EXISTS (SELECT 1 FROM STUDENT_HISTORY h
                                                    WHERE h.Student_ID = v_sid
                                                      AND h.Course_ID = cp.Prerequisite_Course_ID
                                                      AND h.Semester_ID < v_semester_id))
                ORDER BY
                    CASE
                        WHEN rq.Pri IS NOT NULL THEN rq.Pri
                        ELSE 2
                    END,
                    tc.Band,
                    MD5(CONCAT(cs.Course_ID, v_salt)),
                    RAND()
                LIMIT 1;

                IF v_crn IS NULL THEN
                    SET v_short = v_target - v_got;
                    LEAVE pick_loop;
                END IF;

                -- grade: student ability shifts every roll; grads stay at B or better in required courses
                SET v_x = LEAST(0.9999, GREATEST(0, RAND() + (v_ability - 0.5) * 0.30));
                IF v_level = 'Graduate' THEN
                    SET v_floor = IF(v_pri = 0, 0.36, 0.22);
                    SET v_x = v_floor + (1 - v_floor) * v_x;
                END IF;
                SET v_grade = CASE
                    WHEN v_x < 0.10 THEN 'C'
                    WHEN v_x < 0.22 THEN 'C+'
                    WHEN v_x < 0.36 THEN 'B-'
                    WHEN v_x < 0.52 THEN 'B'
                    WHEN v_x < 0.68 THEN 'B+'
                    WHEN v_x < 0.84 THEN 'A-'
                    ELSE 'A' END;

                INSERT INTO STUDENT_HISTORY (Student_ID, CRN, Course_ID, Semester_ID, Grade)
                VALUES (v_sid, v_crn, v_course, v_semester_id, v_grade);

                SET v_got = v_got + 1;
            END WHILE pick_loop;

            -- couldn't fill the load: try to make it up next term (same level, if it has room)
            IF v_short > 0 THEN
                SET @sh_shortfall = @sh_shortfall + v_short;
                UPDATE tmp_plan
                SET Target_Load = LEAST(Max_Load, Target_Load + v_short)
                WHERE Student_ID = v_sid AND Sem_Idx = v_sem_idx + 1 AND Level = v_level;
            END IF;
        END LOOP student_loop;

        SET v_sem_idx = v_sem_idx + 1;
    END WHILE;

    -- ------------------------------------------------------------------------
    -- PHASE 3: make STUDENT.Credits_Earned agree with what is really in the history
    -- (credits at the student's current level only: a continuing grad's bachelor's
    --  courses do not count toward the graduate credits)
    -- ------------------------------------------------------------------------
    UPDATE STUDENT s
    LEFT JOIN (
        SELECT h.Student_ID, SUM(c.Course_Credits) AS cr, c.Course_Type
        FROM STUDENT_HISTORY h JOIN COURSES c ON c.Course_ID = h.Course_ID
        GROUP BY h.Student_ID, c.Course_Type
    ) x ON x.Student_ID = s.Student_ID AND x.Course_Type = s.Student_Type
    SET s.Credits_Earned = COALESCE(x.cr, 0);

   

    DROP TEMPORARY TABLE IF EXISTS tmp_order;
    DROP TEMPORARY TABLE IF EXISTS tmp_course;
    DROP TEMPORARY TABLE IF EXISTS tmp_req;
    DROP TEMPORARY TABLE IF EXISTS tmp_plan;
    DROP TEMPORARY TABLE IF EXISTS tmp_stu;
END$$

DELIMITER ;

CALL SeedStudentHistory();
DROP PROCEDURE IF EXISTS SeedStudentHistory;
DROP PROCEDURE IF EXISTS sh_plan_fill;
DROP PROCEDURE IF EXISTS sh_plan_add;

-- Courses that could not be placed (no seat / no eligible section). Should be small.
SELECT @sh_shortfall AS Courses_Not_Placed;
