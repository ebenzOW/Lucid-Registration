-- ============================================================================
-- LUCID UNIVERSITY: 03_schedules_and_enrollments.sql
-- Master Schedule Sections, Faculty History, Student History & Active Fall 2026 Enrollments
-- Order:
--   1. Course Sections & Faculty History Generation (SeedCourseSections)
--   2. Student Historical Course Enrollments & Transcripts (SeedStudentHistory)
--   3. Active Fall 2026 Live Enrollments (SeedActiveFall2026Enrollments)
-- ============================================================================

-- ============================================================================
-- LUCID UNIVERSITY: MASTER SCHEDULE GENERATOR (COURSE_SECTIONS) - LOOP VERSION
-- Generates Fall 2026 (202608) and Spring 2027 (202701) course sections. as well as past semesters, 8 in total
--
-- How it works (same style as the faculty / student seeders):
--   1. Build a list of the sections we want to offer (staging table).
--   2. Go through that list ONE SECTION AT A TIME. For each section:
--        a) pick a random faculty member from that course's department who still
--           has room (part-time: max 1 section, full-time: max 2 sections)
--        b) pick a random room + time slot where the room is free AND the
--           faculty member is free
--        c) INSERT the section
--
-- Rules enforced:
--   - Faculty only teach courses from a department they belong to (DEPARTMENT_ROSTER)
--   - Part-time faculty teach exactly 1 section per semester
--   - Full-time faculty teach at most 2 sections per semester
--   - A faculty member is never in two sections at the same time slot
--   - A room is never used by two sections at the same time slot
--   - Lab courses go in LAB rooms (in a building that fits the department),
--     everything else goes in LECTURE rooms
--   - x670 / x690 PhD courses are Friday only
-- ============================================================================

DELIMITER $$

DROP PROCEDURE IF EXISTS SeedCourseSections$$

CREATE PROCEDURE SeedCourseSections()
BEGIN
    DECLARE v_sem_idx          INT DEFAULT 1;
    DECLARE v_semester_id      INT;
    DECLARE v_crn_base         INT;
    DECLARE v_crn              INT;
    DECLARE v_dept             INT;
    DECLARE v_prefix           VARCHAR(5);
    DECLARE v_is_fall          BOOLEAN;

    DECLARE v_sched_id         INT;
    DECLARE v_sec_dept         INT;
    DECLARE v_course_id        VARCHAR(15);
    DECLARE v_section_no       INT;
    DECLARE v_requires_lab     BOOLEAN;
    DECLARE v_is_friday        BOOLEAN;

    DECLARE v_faculty_id       INT;
    DECLARE v_room_type        VARCHAR(20);
    DECLARE v_lab_building     VARCHAR(10);
    DECLARE v_lecture_building VARCHAR(10);
    DECLARE v_room_id          VARCHAR(15);
    DECLARE v_slot_id          INT;

    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        RESIGNAL;
    END;

    START TRANSACTION;

    -- Wipe existing generated sections to rebuild cleanly
    DELETE FROM COURSE_SECTIONS;

    -- Iterate over all 10 semesters (8 past terms + Fall 2026 + Spring 2027)
    WHILE v_sem_idx <= 10 DO

        SET v_semester_id = ELT(v_sem_idx, 
            202208, 202301, 202308, 202401, 202408, 202501, 202508, 202601, 202608, 202701
        );
        -- Odd index = Fall (August), Even index = Spring (January)
        SET v_is_fall  = (v_sem_idx % 2 = 1);
        SET v_crn_base = ELT(v_sem_idx, 
            222001, 231001, 232001, 241001, 242001, 251001, 252001, 261001, 262001, 271001
        );
        SET v_crn = v_crn_base;

        -- --------------------------------------------------------------------
        -- STEP A: Build Staging List for This Semester
        -- --------------------------------------------------------------------
        DROP TEMPORARY TABLE IF EXISTS tmp_sections;
        CREATE TEMPORARY TABLE tmp_sections (
            sched_id       INT AUTO_INCREMENT PRIMARY KEY,
            Department_ID  INT,
            Course_ID      VARCHAR(15),
            Section_Number INT,
            Requires_Lab   BOOLEAN DEFAULT FALSE,
            Is_Friday      BOOLEAN DEFAULT FALSE
        );

        SET v_dept = 1;
        WHILE v_dept <= 12 DO
            SET v_prefix = ELT(v_dept, 'CS','PX','EN','PS','MA','HI','EC','PH','EG','BO','AT','AS');

            -- 1. Intro Undergrad (x100, x101, x110): 6 sections each (was 3).
            --    With 10 seats per section, 3 sections left only ~30 intro seats per department per
            --    semester, which is far fewer than the new students who need them (students can
            --    only take 100-level courses until their prerequisites are done).
            INSERT INTO tmp_sections (Department_ID, Course_ID, Section_Number, Requires_Lab)
            VALUES
            (v_dept, CONCAT(v_prefix,'100'), 1, v_dept IN (2,9,10,11)),
            (v_dept, CONCAT(v_prefix,'100'), 2, v_dept IN (2,9,10,11)),
            (v_dept, CONCAT(v_prefix,'100'), 3, v_dept IN (2,9,10,11)),
            (v_dept, CONCAT(v_prefix,'101'), 1, FALSE),
            (v_dept, CONCAT(v_prefix,'101'), 2, FALSE),
            (v_dept, CONCAT(v_prefix,'101'), 3, FALSE),
            (v_dept, CONCAT(v_prefix,'110'), 1, FALSE),
            (v_dept, CONCAT(v_prefix,'110'), 2, FALSE),
            (v_dept, CONCAT(v_prefix,'110'), 3, FALSE);
         

            -- 2. Core Undergrad (x120, x150, x200, x210, x220, x250): 3 sections each (was 2)
            INSERT INTO tmp_sections (Department_ID, Course_ID, Section_Number, Requires_Lab)
            VALUES
            (v_dept, CONCAT(v_prefix,'120'), 1, FALSE),
            (v_dept, CONCAT(v_prefix,'120'), 2, FALSE),
            (v_dept, CONCAT(v_prefix,'120'), 3, FALSE),
            (v_dept, CONCAT(v_prefix,'150'), 1, v_dept IN (1,2,9)),
            (v_dept, CONCAT(v_prefix,'150'), 2, v_dept IN (1,2,9)),
            (v_dept, CONCAT(v_prefix,'200'), 1, v_dept IN (10,11)),
            (v_dept, CONCAT(v_prefix,'200'), 2, v_dept IN (10,11)),
            (v_dept, CONCAT(v_prefix,'200'), 3, v_dept IN (10,11)),
            (v_dept, CONCAT(v_prefix,'210'), 1, FALSE),
            (v_dept, CONCAT(v_prefix,'210'), 2, FALSE),
            (v_dept, CONCAT(v_prefix,'220'), 1, FALSE),
            (v_dept, CONCAT(v_prefix,'220'), 2, FALSE),
            (v_dept, CONCAT(v_prefix,'220'), 3, FALSE),
            (v_dept, CONCAT(v_prefix,'250'), 1, FALSE),
            (v_dept, CONCAT(v_prefix,'250'), 2, FALSE);

            -- 3. Undergrad Electives: Strict Seasonal Rotation
            IF v_is_fall THEN
                INSERT INTO tmp_sections (Department_ID, Course_ID, Section_Number, Requires_Lab)
                VALUES
                (v_dept, CONCAT(v_prefix,'130'), 1, FALSE),
                (v_dept, CONCAT(v_prefix,'160'), 1, v_dept IN (1,2,9,10)),
                (v_dept, CONCAT(v_prefix,'180'), 1, FALSE),
                (v_dept, CONCAT(v_prefix,'230'), 1, FALSE),
                (v_dept, CONCAT(v_prefix,'260'), 1, FALSE),
                -- 300-level: 3 sections each (30 seats for 25 juniors)
                (v_dept, CONCAT(v_prefix,'300'), 1, FALSE),
                (v_dept, CONCAT(v_prefix,'300'), 2, FALSE),
                (v_dept, CONCAT(v_prefix,'300'), 3, FALSE),
                (v_dept, CONCAT(v_prefix,'310'), 1, FALSE),
                (v_dept, CONCAT(v_prefix,'310'), 2, FALSE),
                (v_dept, CONCAT(v_prefix,'310'), 3, FALSE),
                (v_dept, CONCAT(v_prefix,'320'), 1, FALSE),
                (v_dept, CONCAT(v_prefix,'320'), 2, FALSE),
                -- 400-level: 3 sections each (30 seats for 25 seniors)
                (v_dept, CONCAT(v_prefix,'400'), 1, FALSE),
                (v_dept, CONCAT(v_prefix,'400'), 2, FALSE),
                (v_dept, CONCAT(v_prefix,'400'), 3, FALSE),
                (v_dept, CONCAT(v_prefix,'410'), 1, FALSE),
                (v_dept, CONCAT(v_prefix,'410'), 2, FALSE),
                (v_dept, CONCAT(v_prefix,'410'), 3, FALSE),
                (v_dept, CONCAT(v_prefix,'430'), 1, FALSE),
                (v_dept, CONCAT(v_prefix,'430'), 2, FALSE),
                (v_dept, CONCAT(v_prefix,'430'), 3, FALSE);  -- 20 seats total for x430
            ELSE
                INSERT INTO tmp_sections (Department_ID, Course_ID, Section_Number, Requires_Lab)
                VALUES
                (v_dept, CONCAT(v_prefix,'170'), 1, v_dept IN (1,2,9,10)),
                (v_dept, CONCAT(v_prefix,'190'), 1, FALSE),
                (v_dept, CONCAT(v_prefix,'240'), 1, FALSE),
                (v_dept, CONCAT(v_prefix,'290'), 1, FALSE),
                (v_dept, CONCAT(v_prefix,'330'), 1, FALSE),
                (v_dept, CONCAT(v_prefix,'340'), 1, FALSE),
                (v_dept, CONCAT(v_prefix,'340'), 2, FALSE),
                (v_dept, CONCAT(v_prefix,'360'), 1, FALSE),
                (v_dept, CONCAT(v_prefix,'370'), 1, FALSE),
                (v_dept, CONCAT(v_prefix,'420'), 1, FALSE),
                (v_dept, CONCAT(v_prefix,'420'), 2, FALSE),
                (v_dept, CONCAT(v_prefix,'420'), 3, FALSE),   -- Raised to 3 sections (30 seats)
                (v_dept, CONCAT(v_prefix,'440'), 1, FALSE),   -- Added x440 (10 seats)
                (v_dept, CONCAT(v_prefix,'440'), 2, FALSE),   -- 20 seats total for x440
                (v_dept, CONCAT(v_prefix,'490'), 1, FALSE),   -- Capstone (30 seats)
                (v_dept, CONCAT(v_prefix,'490'), 2, FALSE),
                (v_dept, CONCAT(v_prefix,'490'), 3, FALSE);
            END IF;

            -- 4. Graduate Core (x500, x510, x520, x550): Exactly 1 section each
            INSERT INTO tmp_sections (Department_ID, Course_ID, Section_Number, Requires_Lab)
            VALUES
            (v_dept, CONCAT(v_prefix,'500'), 1, FALSE),
            (v_dept, CONCAT(v_prefix,'510'), 1, FALSE),
            (v_dept, CONCAT(v_prefix,'520'), 1, FALSE),
            (v_dept, CONCAT(v_prefix,'550'), 1, FALSE);

            -- 5. Graduate Electives: Strict Seasonal Rotation
            IF v_is_fall THEN
                INSERT INTO tmp_sections (Department_ID, Course_ID, Section_Number, Requires_Lab)
                VALUES
                (v_dept, CONCAT(v_prefix,'530'), 1, FALSE),
                (v_dept, CONCAT(v_prefix,'560'), 1, FALSE),
                (v_dept, CONCAT(v_prefix,'580'), 1, FALSE),
                (v_dept, CONCAT(v_prefix,'600'), 1, FALSE),
                (v_dept, CONCAT(v_prefix,'620'), 1, FALSE),
                (v_dept, CONCAT(v_prefix,'640'), 1, FALSE);
            ELSE
                INSERT INTO tmp_sections (Department_ID, Course_ID, Section_Number, Requires_Lab)
                VALUES
                (v_dept, CONCAT(v_prefix,'540'), 1, FALSE),
                (v_dept, CONCAT(v_prefix,'570'), 1, FALSE),
                (v_dept, CONCAT(v_prefix,'590'), 1, FALSE),
                (v_dept, CONCAT(v_prefix,'610'), 1, FALSE),
                (v_dept, CONCAT(v_prefix,'630'), 1, FALSE),
                (v_dept, CONCAT(v_prefix,'660'), 1, FALSE);
            END IF;

            -- 6. PhD Courses: Friday only
            INSERT INTO tmp_sections (Department_ID, Course_ID, Section_Number, Requires_Lab, Is_Friday)
            VALUES
            (v_dept, CONCAT(v_prefix,'670'), 1, FALSE, TRUE),
            (v_dept, CONCAT(v_prefix,'690'), 1, FALSE, TRUE);

            SET v_dept = v_dept + 1;
        END WHILE;

        -- Prune any placeholder course numbers not in COURSES
        DELETE FROM tmp_sections WHERE Course_ID NOT IN (SELECT Course_ID FROM COURSES);

        -- --------------------------------------------------------------------
        -- STEP B: Assign Faculty, Room, and Time Slot per Section
        -- --------------------------------------------------------------------
        SET v_sched_id = 0;

        section_loop: LOOP
            SELECT MIN(sched_id) INTO v_sched_id 
            FROM tmp_sections 
            WHERE sched_id > v_sched_id;

            IF v_sched_id IS NULL THEN 
                LEAVE section_loop; 
            END IF;

            SELECT Department_ID, Course_ID, Section_Number, Requires_Lab, Is_Friday
            INTO v_sec_dept, v_course_id, v_section_no, v_requires_lab, v_is_friday
            FROM tmp_sections 
            WHERE sched_id = v_sched_id;

            -- 1. Faculty selection (within department and teaching limits)
            SET v_faculty_id = NULL;
            SELECT dr.Faculty_ID INTO v_faculty_id
            FROM DEPARTMENT_ROSTER dr
            LEFT JOIN PART_TIME_FACULTY pt ON pt.Faculty_ID = dr.Faculty_ID
            LEFT JOIN (
                SELECT Faculty_ID, COUNT(*) AS cnt
                FROM COURSE_SECTIONS
                WHERE Semester_ID = v_semester_id
                GROUP BY Faculty_ID
            ) c ON c.Faculty_ID = dr.Faculty_ID
            WHERE dr.Department_ID = v_sec_dept
              AND IFNULL(c.cnt, 0) < IF(pt.Faculty_ID IS NULL, 2, 1)
            ORDER BY IFNULL(c.cnt, 0), (pt.Faculty_ID IS NULL), RAND()
            LIMIT 1;

            IF v_faculty_id IS NULL THEN
                SIGNAL SQLSTATE '45000'
                    SET MESSAGE_TEXT = 'Faculty teaching limit reached for department';
            END IF;

            -- 2. Building selection based on lab or lecture
            SET v_room_type = IF(v_requires_lab, 'Lab', 'Lecture');

            SET v_lab_building = CASE v_sec_dept
                WHEN 1  THEN 'ENG'
                WHEN 2  THEN 'SCI'
                WHEN 9  THEN 'ENG'
                WHEN 10 THEN 'SCI'
                WHEN 11 THEN 'ARTS'
                ELSE NULL
            END;

            SET v_lecture_building = CASE v_sec_dept
                WHEN 1  THEN 'ENG'
                WHEN 2  THEN 'SCI'
                WHEN 3  THEN 'HUM'
                WHEN 4  THEN 'ACAD'
                WHEN 5  THEN 'ACAD'
                WHEN 6  THEN 'HUM'
                WHEN 7  THEN 'HUM'
                WHEN 8  THEN 'HUM'
                WHEN 9  THEN 'ENG'
                WHEN 10 THEN 'SCI'
                WHEN 11 THEN 'ARTS'
                WHEN 12 THEN 'SCI'
                ELSE 'ACAD'
            END;

            -- 3. Room & Time Slot Conflict Check
            SET v_room_id = NULL;
            SET v_slot_id = NULL;

            SELECT r.Room_ID, ts.Time_Slot_ID INTO v_room_id, v_slot_id
            FROM ROOM r
            CROSS JOIN TIME_SLOT ts
            WHERE r.Room_Type = v_room_type
              AND (
                    (v_room_type = 'Lab' AND r.Building_ID = v_lab_building)
                OR  (v_room_type = 'Lecture' AND r.Building_ID = v_lecture_building)
              )
              AND (
                    (v_is_friday = 1 AND ts.Time_Slot_ID IN (10, 20))
                 OR (v_is_friday = 0 AND ts.Time_Slot_ID BETWEEN 101 AND 116)
              )
              AND NOT EXISTS (
                  SELECT 1 FROM COURSE_SECTIONS cs
                  WHERE cs.Semester_ID  = v_semester_id
                    AND cs.Room_ID      = r.Room_ID
                    AND cs.Time_Slot_ID = ts.Time_Slot_ID
              )
              AND NOT EXISTS (
                  SELECT 1 FROM COURSE_SECTIONS cs
                  WHERE cs.Semester_ID  = v_semester_id
                    AND cs.Faculty_ID   = v_faculty_id
                    AND cs.Time_Slot_ID = ts.Time_Slot_ID
              )
            ORDER BY RAND()
            LIMIT 1;

            -- Fallback to any general room if department building is congested
            IF v_room_id IS NULL THEN
                SELECT r.Room_ID, ts.Time_Slot_ID INTO v_room_id, v_slot_id
                FROM ROOM r
                CROSS JOIN TIME_SLOT ts
                WHERE r.Room_Type = v_room_type
                  AND (
                        (v_is_friday = 1 AND ts.Time_Slot_ID IN (10, 20))
                     OR (v_is_friday = 0 AND ts.Time_Slot_ID BETWEEN 101 AND 116)
                  )
                  AND NOT EXISTS (
                      SELECT 1 FROM COURSE_SECTIONS cs
                      WHERE cs.Semester_ID  = v_semester_id
                        AND cs.Room_ID      = r.Room_ID
                        AND cs.Time_Slot_ID = ts.Time_Slot_ID
                  )
                  AND NOT EXISTS (
                      SELECT 1 FROM COURSE_SECTIONS cs
                      WHERE cs.Semester_ID  = v_semester_id
                        AND cs.Faculty_ID   = v_faculty_id
                        AND cs.Time_Slot_ID = ts.Time_Slot_ID
                  )
                ORDER BY RAND()
                LIMIT 1;
            END IF;

            IF v_room_id IS NULL THEN
                SIGNAL SQLSTATE '45000'
                    SET MESSAGE_TEXT = 'No free room and time slot left for section';
            END IF;

            -- 4. Insert section
            INSERT INTO COURSE_SECTIONS (
                CRN, Course_ID, Section_Number, Faculty_ID,
                Time_Slot_ID, Room_ID, Semester_ID, Available_seats
            ) VALUES (
                v_crn, v_course_id, v_section_no, v_faculty_id,
                v_slot_id, v_room_id, v_semester_id, 10
            );

            SET v_crn = v_crn + 1;

        END LOOP section_loop;

        DROP TEMPORARY TABLE IF EXISTS tmp_sections;
        SET v_sem_idx = v_sem_idx + 1;
    END WHILE;

    -- Automatically populate FACULTY_HISTORY for all past terms
    DELETE FROM FACULTY_HISTORY;

    INSERT INTO FACULTY_HISTORY (Faculty_ID, CRN, Course_ID, Semester_ID)
    SELECT Faculty_ID, CRN, Course_ID, Semester_ID
    FROM COURSE_SECTIONS
    WHERE Semester_ID < 202608;

    COMMIT;
END$$

DELIMITER ;

CALL SeedCourseSections();
DROP PROCEDURE IF EXISTS SeedCourseSections;

-- ============================================================================
-- SANITY CHECKS (run these after; the first number should match what you expect,
-- every other query should return ZERO rows)
-- ============================================================================

-- Sections per semester
-- SELECT Semester_ID, COUNT(*) FROM COURSE_SECTIONS GROUP BY Semester_ID;

-- Professor double-booked at the same time
-- SELECT Faculty_ID, Semester_ID, Time_Slot_ID, COUNT(*)
-- FROM COURSE_SECTIONS GROUP BY Faculty_ID, Semester_ID, Time_Slot_ID HAVING COUNT(*) > 1;

-- Room double-booked at the same time
-- SELECT Room_ID, Semester_ID, Time_Slot_ID, COUNT(*)
-- FROM COURSE_SECTIONS GROUP BY Room_ID, Semester_ID, Time_Slot_ID HAVING COUNT(*) > 1;

-- Part-time faculty who do not have exactly 1 section in a semester
-- SELECT s.Semester_ID, pt.Faculty_ID, COUNT(cs.CRN) AS n
-- FROM PART_TIME_FACULTY pt
-- CROSS JOIN SEMESTER s
-- LEFT JOIN COURSE_SECTIONS cs ON cs.Faculty_ID = pt.Faculty_ID AND cs.Semester_ID = s.Semester_ID
-- GROUP BY s.Semester_ID, pt.Faculty_ID HAVING n <> 1;

-- Full-time faculty with more than 2 sections in a semester
-- SELECT Faculty_ID, Semester_ID, COUNT(*) FROM COURSE_SECTIONS
-- WHERE Faculty_ID IN (SELECT Faculty_ID FROM FULL_TIME_FACULTY)
-- GROUP BY Faculty_ID, Semester_ID HAVING COUNT(*) > 2;

-- Faculty teaching a course outside their department
-- SELECT cs.CRN, cs.Course_ID, cs.Faculty_ID
-- FROM COURSE_SECTIONS cs
-- JOIN COURSES c ON c.Course_ID = cs.Course_ID
-- LEFT JOIN DEPARTMENT_ROSTER dr ON dr.Faculty_ID = cs.Faculty_ID AND dr.Department_ID = c.Department_ID
-- WHERE dr.Faculty_ID IS NULL;



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
    INSERT INTO tmp_stu (Student_ID, Level, Mode, Dept_ID, Program, N_Target, Ability)
    SELECT s.Student_ID, s.Student_Type,
           CASE WHEN ftu.Student_ID IS NOT NULL OR ftg.Student_ID IS NOT NULL THEN 'FT' ELSE 'PT' END,
           s.Dept_ID,
           CASE WHEN s.Student_Type = 'Undergraduate' THEN 'BS'
                WHEN UPPER(g.Grad_Program) = 'PHD' THEN 'PHD'
                ELSE 'MS' END,
           COALESCE(s.Credits_Earned, 0) DIV 4,
           ROUND(RAND(), 3)
    FROM STUDENT s
    LEFT JOIN FULL_TIME_UNDERGRADUATE ftu ON ftu.Student_ID = s.Student_ID
    LEFT JOIN FULL_TIME_GRADUATE ftg      ON ftg.Student_ID = s.Student_ID
    LEFT JOIN GRADUATE_STUDENT g          ON g.Student_ID  = s.Student_ID;


    -- Required / minor courses per student (lowest priority number wins)
    INSERT IGNORE INTO tmp_req (Student_ID, Course_ID, Pri)
    SELECT sm.Student_ID, mr.Course_ID, 0
    FROM STUDENT_MAJORS sm JOIN MAJOR_REQUIREMENTS mr ON mr.Major_ID = sm.Major_ID;

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

        SELECT Level, Mode, Program, N_Target
        INTO v_level, v_mode, v_prog, v_n
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
                  AND (SELECT COUNT(*) FROM STUDENT_HISTORY h WHERE h.CRN = cs.CRN) < 25
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