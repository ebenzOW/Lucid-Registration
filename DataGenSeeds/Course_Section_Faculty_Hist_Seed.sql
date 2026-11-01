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

