DELIMITER $$

DROP PROCEDURE IF EXISTS SeedPrerequisites$$

CREATE PROCEDURE SeedPrerequisites()
BEGIN
    DECLARE v_dept INT DEFAULT 1;
    DECLARE v_prefix VARCHAR(5);

    START TRANSACTION;
    DELETE FROM COURSE_PREREQUISITE;

    WHILE v_dept <= 12 DO
        SET v_prefix = ELT(v_dept, 'CS', 'PX', 'EN', 'PS', 'MA', 'HI', 'EC', 'PH', 'EG', 'BO', 'AT', 'AS');

        -- 1. 100-level courses have NO prerequisites among each other.
        -- Freshmen can take xx100, xx120, xx101, xx110, xx130, xx150, xx160 in any order.

        -- 2. TIER 2: ALL 200-level courses require BOTH xx100 and xx120
        INSERT INTO COURSE_PREREQUISITE (Prerequisite_Course_ID, Course_ID)
        SELECT CONCAT(v_prefix, '100'), Course_ID
        FROM COURSES 
        WHERE Department_ID = v_dept 
          AND Course_Type = 'Undergraduate' 
          AND Course_ID REGEXP CONCAT('^', v_prefix, '2[0-9]{2}$');

        INSERT INTO COURSE_PREREQUISITE (Prerequisite_Course_ID, Course_ID)
        SELECT CONCAT(v_prefix, '120'), Course_ID
        FROM COURSES 
        WHERE Department_ID = v_dept 
          AND Course_Type = 'Undergraduate' 
          AND Course_ID REGEXP CONCAT('^', v_prefix, '2[0-9]{2}$');

        -- 3. TIER 3: ALL 300-level courses require BOTH xx200 and xx220
        INSERT INTO COURSE_PREREQUISITE (Prerequisite_Course_ID, Course_ID)
        SELECT CONCAT(v_prefix, '200'), Course_ID
        FROM COURSES 
        WHERE Department_ID = v_dept 
          AND Course_Type = 'Undergraduate' 
          AND Course_ID REGEXP CONCAT('^', v_prefix, '3[0-9]{2}$');

        INSERT INTO COURSE_PREREQUISITE (Prerequisite_Course_ID, Course_ID)
        SELECT CONCAT(v_prefix, '220'), Course_ID
        FROM COURSES 
        WHERE Department_ID = v_dept 
          AND Course_Type = 'Undergraduate' 
          AND Course_ID REGEXP CONCAT('^', v_prefix, '3[0-9]{2}$');

        -- 4. TIER 4: 400-level electives require xx300
        INSERT INTO COURSE_PREREQUISITE (Prerequisite_Course_ID, Course_ID)
        SELECT CONCAT(v_prefix, '300'), Course_ID
        FROM COURSES 
        WHERE Department_ID = v_dept 
          AND Course_Type = 'Undergraduate' 
          AND Course_ID REGEXP CONCAT('^', v_prefix, '4[0-8][0-9]$');

        -- 5. SENIOR CAPSTONE (xx490): Requires xx200, xx300, and xx320
        INSERT INTO COURSE_PREREQUISITE (Prerequisite_Course_ID, Course_ID) VALUES
        (CONCAT(v_prefix, '200'), CONCAT(v_prefix, '490')),
        (CONCAT(v_prefix, '300'), CONCAT(v_prefix, '490')),
        (CONCAT(v_prefix, '320'), CONCAT(v_prefix, '490'));

        -- 6. GRADUATE: xx500 gates intermediate grad courses; xx500+xx600 gates xx690 seminar
        INSERT INTO COURSE_PREREQUISITE (Prerequisite_Course_ID, Course_ID) VALUES 
        (CONCAT(v_prefix, '500'), CONCAT(v_prefix, '510')),
        (CONCAT(v_prefix, '500'), CONCAT(v_prefix, '520')),
        (CONCAT(v_prefix, '500'), CONCAT(v_prefix, '600')),
        (CONCAT(v_prefix, '500'), CONCAT(v_prefix, '690')),
        (CONCAT(v_prefix, '600'), CONCAT(v_prefix, '690'));

        SET v_dept = v_dept + 1;
    END WHILE;

    COMMIT;
END$$

DELIMITER ;

CALL SeedPrerequisites();
DROP PROCEDURE IF EXISTS SeedPrerequisites;