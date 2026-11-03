
DELETE FROM `USER` WHERE User_Type = 'Student';

DELIMITER $$

DROP PROCEDURE IF EXISTS SeedUndergraduates$$

CREATE PROCEDURE SeedUndergraduates()
BEGIN
    DECLARE v_year INT DEFAULT 1;
    DECLARE v_i INT DEFAULT 1;
    DECLARE v_user_id INT;
    DECLARE v_loc_idx INT;
    DECLARE v_fname VARCHAR(50);
    DECLARE v_lname VARCHAR(50);
    DECLARE v_mname VARCHAR(5);
    DECLARE v_gender VARCHAR(15);
    DECLARE v_dob DATE;
    DECLARE v_area_code CHAR(3);
    DECLARE v_phone VARCHAR(15);
    DECLARE v_street_no VARCHAR(10);
    DECLARE v_street_name VARCHAR(50);
    DECLARE v_city VARCHAR(50);
    DECLARE v_state VARCHAR(20);
    DECLARE v_zip VARCHAR(15);
    DECLARE v_base_email VARCHAR(100);
    DECLARE v_email VARCHAR(100);
    DECLARE v_email_suffix INT;
    DECLARE v_dept_id INT;
    DECLARE v_credits INT;
    DECLARE v_undergrad_type VARCHAR(20);
    DECLARE v_primary_major INT;
    DECLARE v_secondary_major INT;
    DECLARE v_minor_id INT;
    
    -- Standard bcrypt hash for the test password: 'password'
    DECLARE v_password_hash VARCHAR(255) DEFAULT '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi';

    START TRANSACTION;

    -- Loop through Student_Year 1 (Freshman) to 4 (Senior)
    WHILE v_year <= 4 DO
        SET v_i = 1;

        -- 300 students per class standing (1..200 = Full-Time, 201..300 = Part-Time)
        WHILE v_i <= 300 DO

            -- 1. Generate a unique random 6-to-7 digit User_ID (100000 - 9999999)
            REPEAT
                SET v_user_id = FLOOR(100000 + (RAND() * 9899999));
            UNTIL NOT EXISTS (SELECT 1 FROM `USER` WHERE User_ID = v_user_id)
              AND v_user_id NOT IN (987221, 973244, 928991, 753298, 513849, 948579, 957236, 467291, 987190, 992819, 299182, 178221)
            END REPEAT;

            -- 2. Pick realistic demographic names (30 Female, 30 Male, 40 Last Names)
            IF RAND() < 0.5 THEN
                SET v_gender = 'Female';
                SET v_fname = ELT(FLOOR(1 + RAND() * 30),
                    'Emma','Olivia','Ava','Sophia','Isabella','Mia','Charlotte','Amelia','Harper','Evelyn',
                    'Abigail','Emily','Elizabeth','Sofia','Avery','Ella','Scarlett','Grace','Chloe','Victoria',
                    'Riley','Aria','Lily','Natalie','Zoe','Hannah','Layla','Leah','Nora','Maya');
            ELSE
                SET v_gender = 'Male';
                SET v_fname = ELT(FLOOR(1 + RAND() * 30),
                    'Liam','Noah','Oliver','Elijah','James','William','Benjamin','Lucas','Henry','Theodore',
                    'Jack','Levi','Alexander','Jackson','Mateo','Daniel','Michael','Mason','Sebastian','Ethan',
                    'Logan','Owen','Samuel','Jacob','Asher','Aiden','John','Joseph','Wyatt','David');
            END IF;

            SET v_lname = ELT(FLOOR(1 + RAND() * 40),
                'Smith','Johnson','Williams','Brown','Jones','Garcia','Miller','Davis','Rodriguez','Martinez',
                'Hernandez','Lopez','Gonzalez','Wilson','Anderson','Thomas','Taylor','Moore','Jackson','Martin',
                'Lee','Perez','Thompson','White','Harris','Sanchez','Clark','Ramirez','Lewis','Robinson',
                'Walker','Young','Allen','King','Wright','Scott','Torres','Nguyen','Hill','Flores');

            SET v_mname = ELT(FLOOR(1 + RAND() * 16),
                'A','B','C','D','E','F','G','H','J','K','L','M','N','R','S','T');

            -- 3. Realistic DOB based on Student_Year (relative to 2026)
            SET v_dob = DATE_ADD(MAKEDATE(2009 - v_year, 1), INTERVAL FLOOR(RAND() * 364) DAY);

            -- 4. Pick ONE shared location index (1 to 40) so City, State, Zip, and Area Code match
            SET v_loc_idx = FLOOR(1 + RAND() * 40);

            SET v_city = ELT(v_loc_idx,
                -- 1-12: Nassau County, NY
                'Old Westbury','Merrick','Hicksville','Garden City','Mineola','Syosset',
                'Levittown','Massapequa','Roslyn','Freeport','Valley Stream','Great Neck',
                -- 13-20: Suffolk County, NY
                'Huntington','Babylon','Islip','Smithtown','Patchogue','Stony Brook','Melville','Riverhead',
                -- 21-28: NYC Boroughs (Queens, Brooklyn, Manhattan, Bronx, Staten Island)
                'Flushing','Astoria','Jamaica','Bayside','Brooklyn','New York','Bronx','Staten Island',
                -- 29-32: Westchester & Upstate NY
                'White Plains','Yonkers','Albany','Syracuse',
                -- 33-40: Tri-State & Out-of-State (NJ, CT, PA, MA, FL, TX, CA, IL)
                'Hoboken','Jersey City','Stamford','New Haven','Philadelphia','Boston','Miami','Austin'
            );

            SET v_state = ELT(v_loc_idx,
                -- 1-32: NY
                'NY','NY','NY','NY','NY','NY','NY','NY','NY','NY','NY','NY',
                'NY','NY','NY','NY','NY','NY','NY','NY',
                'NY','NY','NY','NY','NY','NY','NY','NY',
                'NY','NY','NY','NY',
                -- 33-40: NJ, NJ, CT, CT, PA, MA, FL, TX
                'NJ','NJ','CT','CT','PA','MA','FL','TX'
            );

            SET v_zip = ELT(v_loc_idx,
                -- 1-12: Nassau County exact zips
                '11568','11566','11801','11530','11501','11791',
                '11756','11758','11576','11520','11580','11021',
                -- 13-20: Suffolk County exact zips
                '11743','11702','11751','11787','11772','11790','11747','11901',
                -- 21-28: NYC exact zips
                '11354','11102','11432','11361','11201','10001','10451','10301',
                -- 29-32: Westchester & Upstate NY exact zips
                '10601','10701','12207','13202',
                -- 33-40: Out-of-state exact zips (preserving leading zeros for NJ/CT/MA)
                '07030','07302','06901','06510','19104','02115','33101','78701'
            );

            -- 5. Assign matching regional area code (with 20% chance of mobile overlay code)
            IF RAND() < 0.80 THEN
                SET v_area_code = ELT(v_loc_idx,
                    -- 1-12: Nassau (516 / 363)
                    '516','516','516','516','516','516','516','516','516','516','516','363',
                    -- 13-20: Suffolk (631 / 934)
                    '631','631','631','631','631','631','934','631',
                    -- 21-28: NYC (718, 347, 929, 212, 646)
                    '718','347','718','929','718','212','718','347',
                    -- 29-32: Westchester & Upstate (914, 845, 518, 315)
                    '914','914','518','315',
                    -- 33-40: NJ, CT, PA, MA, FL, TX
                    '201','973','203','475','215','617','305','512'
                );
            ELSE
                -- Common mobile/cell overlay area codes across the student body
                SET v_area_code = ELT(FLOOR(1 + RAND() * 8),
                    '917','646','347','929','934','845','607','716');
            END IF;

            SET v_phone = CONCAT(
                v_area_code, '-',
                LPAD(FLOOR(200 + RAND() * 799), 3, '0'), '-',
                LPAD(FLOOR(1000 + RAND() * 8999), 4, '0')
            );

            -- 6. Street Number & Expanded Street Names (24 realistic streets)
            SET v_street_no = CAST(FLOOR(10 + RAND() * 1989) AS CHAR);
            SET v_street_name = ELT(FLOOR(1 + RAND() * 24),
                'Old Country Rd','Jericho Tpke','Hempstead Tpke','Sunrise Hwy','Merrick Ave','Glen Cove Rd',
                'Northern Blvd','Stewart Ave','Wantagh Ave','Bellmore Ave','Park Ave','Maple St',
                'Oak St','Cedar Ln','Washington Ave','Main St','Broadway','Queens Blvd',
                'Atlantic Ave','Grand Concourse','Central Ave','Highland Ave','Forest Ave','Hillside Ave');

            -- 7. Build Email: LastName + FirstInitial + @lucid.edu (with collision suffix)
            SET v_base_email = CONCAT(v_lname, LOWER(LEFT(v_fname, 1)));
            SET v_email = CONCAT(v_base_email, '@lucid.edu');
            SET v_email_suffix = 2;

            WHILE EXISTS (SELECT 1 FROM LOGIN WHERE User_Email = v_email) DO
                SET v_email = CONCAT(v_base_email, v_email_suffix, '@lucid.edu');
                SET v_email_suffix = v_email_suffix + 1;
            END WHILE;

            -- 8. Pick a valid Department_ID from your populated DEPARTMENT table
            SELECT Department_ID INTO v_dept_id
            FROM DEPARTMENT
            ORDER BY RAND()
            LIMIT 1;

            -- 9. Realistic Credits_Earned matching class standing
            -- Freshmen: 0 - 28 credits (0 to 7 courses)
            IF v_year = 1 THEN
                SET v_credits = FLOOR(RAND() * 8) * 4;
            -- Sophomores: 32 - 56 credits (8 to 14 courses)
            ELSEIF v_year = 2 THEN
                SET v_credits = (8 + FLOOR(RAND() * 7)) * 4;
            -- Juniors: 60 - 88 credits (15 to 22 courses)
            ELSEIF v_year = 3 THEN
                SET v_credits = (15 + FLOOR(RAND() * 8)) * 4;
            -- Seniors: 92 - 116 credits (23 to 29 courses)
            ELSE
                SET v_credits = (23 + FLOOR(RAND() * 7)) * 4;
            END IF;

            -- 10. First 200 per year = Full-Time, Next 100 per year = Part-Time
            IF v_i <= 200 THEN
                SET v_undergrad_type = 'Full-Time';
            ELSE
                SET v_undergrad_type = 'Part-Time';
            END IF;

            -- ================================================================
            -- INSERT CHAIN (1 -> 5)
            -- ================================================================
            INSERT INTO `USER` (
                User_ID, User_Type, User_FName, User_LName, User_MName,
                User_Gender, User_DOB, User_PhoneNo, Street_No, Street_Name,
                City, State, Country, Zip_Code
            ) VALUES (
                v_user_id, 'Student', v_fname, v_lname, v_mname,
                v_gender, v_dob, v_phone, v_street_no, v_street_name,
                v_city, v_state, 'USA', v_zip
            );

            INSERT INTO LOGIN (
                User_ID, User_Email, User_Password, No_Login_Attempts, IsLock
            ) VALUES (
                v_user_id, v_email, v_password_hash, 0, FALSE
            );

            INSERT INTO STUDENT (
                Student_ID, Student_Year, Student_Type, Dept_ID, Credits_Earned
            ) VALUES (
                v_user_id, v_year, 'Undergraduate', v_dept_id, v_credits
            );

            INSERT INTO UNDERGRADUATE_STUDENT (
                Student_ID, Undergrad_Type
            ) VALUES (
                v_user_id, v_undergrad_type
            );

            IF v_undergrad_type = 'Full-Time' THEN
                INSERT INTO FULL_TIME_UNDERGRADUATE (Student_ID) VALUES (v_user_id);
            ELSE
                INSERT INTO PART_TIME_UNDERGRADUATE (Student_ID) VALUES (v_user_id);
            END IF;

            -- ----------------------------------------------------------------
            -- INTEGRATED MAJORS & MINORS
            -- ----------------------------------------------------------------
            -- Primary Major: Odd Major_ID matching Dept_ID (101, 103..123)
            SET v_primary_major = 100 + (v_dept_id * 2) - 1;
            INSERT INTO STUDENT_MAJORS (Major_ID, Student_ID) VALUES (v_primary_major, v_user_id);

            -- Degree Options (15% Double Major, 25% Major + Minor, 60% Single Major)
            IF (v_i MOD 20) IN (1, 2, 3) THEN
                -- Double Major: Second major from a different department, 0 Minors
                SET v_secondary_major = 100 + (((v_dept_id MOD 12) + 1) * 2) - 1;
                INSERT INTO STUDENT_MAJORS (Major_ID, Student_ID) VALUES (v_secondary_major, v_user_id);
            ELSEIF (v_i MOD 4) = 0 THEN
                -- Single Major + 1 Minor: Minor from a different department
                SET v_minor_id = (v_dept_id MOD 12) + 1;
                INSERT INTO STUDENT_MINORS (Minor_ID, Student_ID) VALUES (v_minor_id, v_user_id);
            END IF;

             -- 6. ADVISOR_STUDENT: Assign an advisor(faculty in same department) immediately upon student creation
            -- and guarantees no Full-Time professor ever exceeds the 15-advisee cap.
            INSERT INTO ADVISOR_STUDENT (Faculty_ID, Student_ID, Date_Assigned)
            SELECT dr.Faculty_ID, v_user_id, '2026-08-25'
            FROM DEPARTMENT_ROSTER dr
            JOIN FULL_TIME_FACULTY ft ON dr.Faculty_ID = ft.Faculty_ID
            WHERE dr.Department_ID = v_dept_id
            AND (
                SELECT COUNT(*) 
                FROM ADVISOR_STUDENT a 
                WHERE a.Faculty_ID = dr.Faculty_ID
            ) < 15
            ORDER BY RAND()
            LIMIT 1;

            SET v_i = v_i + 1;
        END WHILE;

        SET v_year = v_year + 1;
    END WHILE;

    COMMIT;
END$$

DELIMITER ;

-- Run the seeder and clean up
CALL SeedUndergraduates();
DROP PROCEDURE IF EXISTS SeedUndergraduates;