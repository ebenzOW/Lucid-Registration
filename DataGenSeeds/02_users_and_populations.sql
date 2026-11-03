-- ============================================================================
-- LUCID UNIVERSITY: 02_users_and_populations.sql
-- Faculty, Undergraduates, Graduates, Advisors & Student Holds
-- Order:
--   1. Faculty Population & Department Assignment (SeedFaculty)
--   2. Undergraduate Population & Initial Advising (SeedUndergraduates)
--   3. Graduate Population, Programs, Theses & Advising (SeedGraduates)
--   4. Student Registration Holds (STUDENT_HOLDS)
-- ============================================================================

DELETE FROM `USER` WHERE User_Type = 'Faculty';

DELIMITER $$

DROP PROCEDURE IF EXISTS SeedFaculty$$

CREATE PROCEDURE SeedFaculty()
BEGIN
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
    DECLARE v_office_id VARCHAR(15);
    DECLARE v_faculty_type VARCHAR(20);
    DECLARE v_rank VARCHAR(50);
    DECLARE v_specialty VARCHAR(100);

    -- Standard bcrypt hash for the test password: 'password'
    DECLARE v_password_hash VARCHAR(255) DEFAULT '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi';

    START TRANSACTION;

    WHILE v_i <= 400 DO

        -- 1. Determine User_ID, Primary Department (1..12), Faculty_Type, and Rank
        IF v_i <= 12 THEN
            -- First 12 are the exact Department Chairs from the DEPARTMENT seed file
            SET v_dept_id = v_i;
            SET v_user_id = ELT(v_i,
                987221, 973244, 928991, 753298, 513849, 948579,
                957236, 467291, 987190, 992819, 299182, 178221
            );
            SET v_faculty_type = 'Full-Time';
            SET v_rank = 'Full Professor';
        ELSE
            -- Cycle evenly across Departments 1..12
            SET v_dept_id = 1 + ((v_i - 1) MOD 12);

            -- Generate unique random 6-to-7 digit User_ID not colliding with existing users or Chair_IDs
            REPEAT
                SET v_user_id = FLOOR(100000 + (RAND() * 9899999));
            UNTIL NOT EXISTS (SELECT 1 FROM `USER` WHERE User_ID = v_user_id)
              AND v_user_id NOT IN (987221, 973244, 928991, 753298, 513849, 948579, 957236, 467291, 987190, 992819, 299182, 178221)
            END REPEAT;

            -- 1..300 = Full-Time (300 total) | 301..400 = Part-Time (100 total)
            IF v_i <= 300 THEN
                SET v_faculty_type = 'Full-Time';
                SET v_rank = ELT(FLOOR(1 + RAND() * 3), 'Assistant Professor', 'Associate Professor', 'Full Professor');
            ELSE
                SET v_faculty_type = 'Part-Time';
                SET v_rank = 'Instructor';
            END IF;
        END IF;

        -- 2. Demographic names
        IF RAND() < 0.5 THEN
            SET v_gender = 'Female';
            SET v_fname = ELT(FLOOR(1 + RAND() * 30),
                'Eleanor','Margaret','Catherine','Victoria','Elizabeth','Caroline','Rebecca','Miriam','Anastasia','Valerie',
                'Diana','Rachel','Evelyn','Hannah','Claire','Beatrice','Sylvia','Nora','Audrey','Lydia',
                'Patricia','Linda','Barbara','Susan','Jessica','Sarah','Karen','Nancy','Lisa','Helen');
        ELSE
            SET v_gender = 'Male';
            SET v_fname = ELT(FLOOR(1 + RAND() * 30),
                'Arthur','Raymond','Lawrence','Gregory','Douglas','Walter','Harold','Henry','Charles','Thomas',
                'Christopher','Daniel','Matthew','Anthony','Mark','Donald','Steven','Paul','Andrew','Joshua',
                'Kenneth','Kevin','Brian','George','Timothy','Ronald','Edward','Jason','Jeffrey','Richard');
        END IF;

        SET v_lname = ELT(FLOOR(1 + RAND() * 40),
            'Smith','Johnson','Williams','Brown','Jones','Garcia','Miller','Davis','Rodriguez','Martinez',
            'Hernandez','Lopez','Gonzalez','Wilson','Anderson','Thomas','Taylor','Moore','Jackson','Martin',
            'Lee','Perez','Thompson','White','Harris','Sanchez','Clark','Ramirez','Lewis','Robinson',
            'Walker','Young','Allen','King','Wright','Scott','Torres','Nguyen','Hill','Flores');

        SET v_mname = ELT(FLOOR(1 + RAND() * 16),
            'A','B','C','D','E','F','G','H','J','K','L','M','N','R','S','T');

        -- 3. Realistic Faculty DOB (Ages ~30 to 68 in 2026 -> Born 1958 to 1996)
        IF v_rank = 'Full Professor' THEN
            SET v_dob = DATE_ADD(MAKEDATE(1958 + FLOOR(RAND() * 20), 1), INTERVAL FLOOR(RAND() * 364) DAY);
        ELSE
            SET v_dob = DATE_ADD(MAKEDATE(1975 + FLOOR(RAND() * 21), 1), INTERVAL FLOOR(RAND() * 364) DAY);
        END IF;

        -- 4. Synchronized 40-Location Index (City, State, Zip, Area Code)
        SET v_loc_idx = FLOOR(1 + RAND() * 40);

        SET v_city = ELT(v_loc_idx,
            'Old Westbury','Merrick','Hicksville','Garden City','Mineola','Syosset',
            'Levittown','Massapequa','Roslyn','Freeport','Valley Stream','Great Neck',
            'Huntington','Babylon','Islip','Smithtown','Patchogue','Stony Brook','Melville','Riverhead',
            'Flushing','Astoria','Jamaica','Bayside','Brooklyn','New York','Bronx','Staten Island',
            'White Plains','Yonkers','Albany','Syracuse',
            'Hoboken','Jersey City','Stamford','New Haven','Philadelphia','Boston','Miami','Austin'
        );

        SET v_state = ELT(v_loc_idx,
            'NY','NY','NY','NY','NY','NY','NY','NY','NY','NY','NY','NY',
            'NY','NY','NY','NY','NY','NY','NY','NY',
            'NY','NY','NY','NY','NY','NY','NY','NY',
            'NY','NY','NY','NY',
            'NJ','NJ','CT','CT','PA','MA','FL','TX'
        );

        SET v_zip = ELT(v_loc_idx,
            '11568','11566','11801','11530','11501','11791',
            '11756','11758','11576','11520','11580','11021',
            '11743','11702','11751','11787','11772','11790','11747','11901',
            '11354','11102','11432','11361','11201','10001','10451','10301',
            '10601','10701','12207','13202',
            '07030','07302','06901','06510','19104','02115','33101','78701'
        );

        IF RAND() < 0.80 THEN
            SET v_area_code = ELT(v_loc_idx,
                '516','516','516','516','516','516','516','516','516','516','516','363',
                '631','631','631','631','631','631','934','631',
                '718','347','718','929','718','212','718','347',
                '914','914','518','315',
                '201','973','203','475','215','617','305','512'
            );
        ELSE
            SET v_area_code = ELT(FLOOR(1 + RAND() * 8),
                '917','646','347','929','934','845','607','716');
        END IF;

        SET v_phone = CONCAT(
            v_area_code, '-',
            LPAD(FLOOR(200 + RAND() * 799), 3, '0'), '-',
            LPAD(FLOOR(1000 + RAND() * 8999), 4, '0')
        );

        SET v_street_no = CAST(FLOOR(10 + RAND() * 1989) AS CHAR);
        SET v_street_name = ELT(FLOOR(1 + RAND() * 24),
            'Old Country Rd','Jericho Tpke','Hempstead Tpke','Sunrise Hwy','Merrick Ave','Glen Cove Rd',
            'Northern Blvd','Stewart Ave','Wantagh Ave','Bellmore Ave','Park Ave','Maple St',
            'Oak St','Cedar Ln','Washington Ave','Main St','Broadway','Queens Blvd',
            'Atlantic Ave','Grand Concourse','Central Ave','Highland Ave','Forest Ave','Hillside Ave');

        -- 5. Email: LastName + FirstInitial + @lucid.edu
        SET v_base_email = CONCAT(v_lname, LOWER(LEFT(v_fname, 1)));
        SET v_email = CONCAT(v_base_email, '@lucid.edu');
        SET v_email_suffix = 2;

        WHILE EXISTS (SELECT 1 FROM LOGIN WHERE User_Email = v_email) DO
            SET v_email = CONCAT(v_base_email, v_email_suffix, '@lucid.edu');
            SET v_email_suffix = v_email_suffix + 1;
        END WHILE;

        -- 6. Pick an Office_ID
        -- Random faculty office that still has a free desk (department offices and admin offices excluded)
        SET v_office_id = (
            SELECT o.Office_ID
            FROM OFFICE o
            JOIN ROOM r ON r.Room_ID = o.Office_ID
            WHERE r.Building_ID IN ('FAC', 'ACAD', 'LIBN')
              AND o.Office_ID NOT IN (SELECT Office_ID FROM DEPARTMENT WHERE Office_ID IS NOT NULL)
              AND (SELECT COUNT(*) FROM FACULTY f WHERE f.Office_ID = o.Office_ID) < o.Office_Desks
            ORDER BY RAND()
            LIMIT 1
        );

        -- 7. Department-Aligned Specialty (Matches Dept 1..12)
        SET v_specialty = ELT(v_dept_id,
            -- 1: Computer Science
            ELT(1 + (v_i MOD 4), 'Distributed Systems & Cloud Computing', 'Artificial Intelligence & Machine Learning', 'Cybersecurity & Applied Cryptography', 'Database Systems & Query Optimization'),
            -- 2: Physics
            ELT(1 + (v_i MOD 4), 'Condensed Matter & Solid State Physics', 'Quantum Mechanics & Field Theory', 'Optics & Laser Spectroscopy', 'Statistical & Thermal Physics'),
            -- 3: English
            ELT(1 + (v_i MOD 4), 'Early Modern British Literature', 'Twentieth-Century American Poetics', 'Postcolonial & Transnational Studies', 'Rhetoric, Composition & Literary Theory'),
            -- 4: Psychology
            ELT(1 + (v_i MOD 4), 'Cognitive Neuroscience & Memory', 'Clinical & Abnormal Psychology', 'Developmental & Child Psychology', 'Behavioral & Social Psychology'),
            -- 5: Mathematics
            ELT(1 + (v_i MOD 4), 'Partial Differential Equations', 'Abstract Algebra & Number Theory', 'Stochastic Processes & Probability', 'Topology & Differential Geometry'),
            -- 6: History
            ELT(1 + (v_i MOD 4), 'Early Atlantic & Colonial History', 'Twentieth-Century U.S. Urban History', 'Early Modern European Statecraft', 'Cold War Diplomacy & Decolonization'),
            -- 7: Economics
            ELT(1 + (v_i MOD 4), 'Macroeconomic Monetary Policy', 'Econometrics & Applied Statistics', 'Industrial Organization & Microeconomic Theory', 'International Trade & Development'),
            -- 8: Philosophy
            ELT(1 + (v_i MOD 4), 'Epistemology & Philosophy of Science', 'Normative Ethics & Political Philosophy', 'Modal Logic & Philosophy of Language', 'Metaphysics & Philosophy of Mind'),
            -- 9: Engineering
            ELT(1 + (v_i MOD 4), 'Robotics & Nonlinear Control Systems', 'Computational Fluid Dynamics', 'Solid Mechanics & Materials Engineering', 'Power Electronics & Smart Grids'),
            -- 10: Biology
            ELT(1 + (v_i MOD 4), 'Molecular Genetics & Genomics', 'Cellular Signaling & Immunology', 'Microbiology & Infectious Disease', 'Evolutionary Ecology & Conservation'),
            -- 11: Art
            ELT(1 + (v_i MOD 4), 'Contemporary Studio Art & Painting', 'Sculpture & Three-Dimensional Design', 'Digital Media & Visual Culture', 'Renaissance to Modern Art History'),
            -- 12: Astronomy
            ELT(1 + (v_i MOD 4), 'Exoplanetary Atmospheres & Astrobiology', 'Galactic Dynamics & Cosmology', 'Stellar Astrophysics & Nucleosynthesis', 'Observational & Radio Astronomy')
        );

        -- ================================================================
        -- INSERT CHAIN (1 -> 6)
        -- ================================================================
        -- 1. USER
        INSERT INTO `USER` (
            User_ID, User_Type, User_FName, User_LName, User_MName,
            User_Gender, User_DOB, User_PhoneNo, Street_No, Street_Name,
            City, State, Country, Zip_Code
        ) VALUES (
            v_user_id, 'Faculty', v_fname, v_lname, v_mname,
            v_gender, v_dob, v_phone, v_street_no, v_street_name,
            v_city, v_state, 'USA', v_zip
        );

        -- 2. LOGIN
        INSERT INTO LOGIN (
            User_ID, User_Email, User_Password, No_Login_Attempts, IsLock
        ) VALUES (
            v_user_id, v_email, v_password_hash, 0, FALSE
        );

        -- 3. FACULTY
        INSERT INTO FACULTY (
            Faculty_ID, Office_ID, Faculty_Type, Specialty, `Rank`
        ) VALUES (
            v_user_id, v_office_id, v_faculty_type, v_specialty, v_rank
        );

       -- 4. Insert into FULL_TIME_FACULTY (first 300) or PART_TIME_FACULTY (last 100)
        IF v_i <= 300 THEN
            INSERT INTO FULL_TIME_FACULTY (Faculty_ID, No_Classes)
            VALUES (v_user_id, 0);
        ELSE
            INSERT INTO PART_TIME_FACULTY (Faculty_ID)
            VALUES (v_user_id);
        END IF;

        -- 5. DEPARTMENT_ROSTER: Every faculty gets 1 row in their home department at 100% to start with, can be edited later
        INSERT INTO DEPARTMENT_ROSTER (Department_ID, Faculty_ID, Date_of_Assignment, Percent_Time)
        VALUES (
            v_dept_id, 
            v_user_id, 
            CONCAT(2008 + (v_i % 18), '-08-25'), -- randomized date of assignment
            100.00
        );

        -- 6. DEPARTMENT_MANAGERS: First 12 are Chairs, next 12 are Department Secretaries
        IF v_i <= 12 THEN
            UPDATE DEPARTMENT SET Chair_ID = v_user_id WHERE Department_ID = v_dept_id;

            INSERT INTO DEPARTMENT_MANAGERS (Department_ID, Faculty_ID, Role)
            VALUES (v_dept_id, v_user_id, 'Chair');
        ELSEIF v_i <= 24 THEN
            INSERT INTO DEPARTMENT_MANAGERS (Department_ID, Faculty_ID, Role)
            VALUES (v_dept_id, v_user_id, 'Secretary');
        END IF;

        SET v_i = v_i + 1;
    END WHILE;

    COMMIT;
END$$

DELIMITER ;

-- Execute the seeder and clean up
CALL SeedFaculty();
DROP PROCEDURE IF EXISTS SeedFaculty;





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

-- ============================================================================
-- LUCID UNIVERSITY: GRADUATE USER GENERATOR (INTEGRATED MAJORS & THESES)
-- Run AFTER schema.sql, FacultyDepartmentDataGen.sql, and Majorsandminors.sql.
-- Generates 300 Graduate Students (50 PhD, 250 MS) and automatically populates:
--   - USER, LOGIN, STUDENT, GRADUATE_STUDENT, FULL/PART_TIME_GRADUATE
--   - STUDENT_MAJORS (MS: 102..124, PhD: 125..136 matching home department)
--   - ADVISOR_STUDENT (assigned to a full-time faculty member in home dept)
-- ============================================================================

DELIMITER $$

DROP PROCEDURE IF EXISTS SeedGraduates$$

CREATE PROCEDURE SeedGraduates()
BEGIN
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
    DECLARE v_student_year INT DEFAULT 5;
    DECLARE v_credits INT;
    DECLARE v_grad_type VARCHAR(20);
    DECLARE v_grad_program VARCHAR(100);
    DECLARE v_thesis VARCHAR(255);
    DECLARE v_grad_major INT;

    DECLARE v_password_hash VARCHAR(255) DEFAULT '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi';

    START TRANSACTION;

    WHILE v_i <= 300 DO

        -- 1. Quota Breakdown: 20 FT PhD, 30 PT PhD, 180 FT MS, 70 PT MS
        IF v_i <= 20 THEN
            SET v_grad_program = 'PHD';
            SET v_grad_type    = 'Full-Time';
        ELSEIF v_i <= 50 THEN
            SET v_grad_program = 'PHD';
            SET v_grad_type    = 'Part-Time';
        ELSEIF v_i <= 230 THEN
            SET v_grad_program = 'MS';
            SET v_grad_type    = 'Full-Time';
        ELSE
            SET v_grad_program = 'MS';
            SET v_grad_type    = 'Part-Time';
        END IF;

        -- 2. Department Assignment (Distributed across Depts 1..12)
        SET v_dept_id = 1 + ((v_i - 1) MOD 12);

        -- 3. Unique User_ID
        REPEAT
            SET v_user_id = FLOOR(100000 + (RAND() * 9899999));
        UNTIL NOT EXISTS (SELECT 1 FROM `USER` WHERE User_ID = v_user_id)
        END REPEAT;

        -- 4. Demographics
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

        IF v_grad_program = 'PHD' THEN
            SET v_dob = DATE_ADD(MAKEDATE(1994 + FLOOR(RAND() * 8), 1), INTERVAL FLOOR(RAND() * 364) DAY);
        ELSE
            SET v_dob = DATE_ADD(MAKEDATE(1998 + FLOOR(RAND() * 6), 1), INTERVAL FLOOR(RAND() * 364) DAY);
        END IF;

        -- 5. Address & Phone
        SET v_loc_idx = FLOOR(1 + RAND() * 40);
        SET v_city = ELT(v_loc_idx,
            'Old Westbury','Merrick','Hicksville','Garden City','Mineola','Syosset',
            'Levittown','Massapequa','Roslyn','Freeport','Valley Stream','Great Neck',
            'Huntington','Babylon','Islip','Smithtown','Patchogue','Stony Brook','Melville','Riverhead',
            'Flushing','Astoria','Jamaica','Bayside','Brooklyn','New York','Bronx','Staten Island',
            'White Plains','Yonkers','Albany','Syracuse',
            'Hoboken','Jersey City','Stamford','New Haven','Philadelphia','Boston','Miami','Austin');

        SET v_state = ELT(v_loc_idx,
            'NY','NY','NY','NY','NY','NY','NY','NY','NY','NY','NY','NY',
            'NY','NY','NY','NY','NY','NY','NY','NY',
            'NY','NY','NY','NY','NY','NY','NY','NY',
            'NY','NY','NY','NY',
            'NJ','NJ','CT','CT','PA','MA','FL','TX');

        SET v_zip = ELT(v_loc_idx,
            '11568','11566','11801','11530','11501','11791',
            '11756','11758','11576','11520','11580','11021',
            '11743','11702','11751','11787','11772','11790','11747','11901',
            '11354','11102','11432','11361','11201','10001','10451','10301',
            '10601','10701','12207','13202',
            '07030','07302','06901','06510','19104','02115','33101','78701');

        IF RAND() < 0.80 THEN
            SET v_area_code = ELT(v_loc_idx,
                '516','516','516','516','516','516','516','516','516','516','516','363',
                '631','631','631','631','631','631','934','631',
                '718','347','718','929','718','212','718','347',
                '914','914','518','315',
                '201','973','203','475','215','617','305','512');
        ELSE
            SET v_area_code = ELT(FLOOR(1 + RAND() * 8), '917','646','347','929','934','845','607','716');
        END IF;

        SET v_phone = CONCAT(v_area_code, '-', LPAD(FLOOR(200 + RAND() * 799), 3, '0'), '-', LPAD(FLOOR(1000 + RAND() * 8999), 4, '0'));
        SET v_street_no = CAST(FLOOR(10 + RAND() * 1989) AS CHAR);
        SET v_street_name = ELT(FLOOR(1 + RAND() * 24),
            'Old Country Rd','Jericho Tpke','Hempstead Tpke','Sunrise Hwy','Merrick Ave','Glen Cove Rd',
            'Northern Blvd','Stewart Ave','Wantagh Ave','Bellmore Ave','Park Ave','Maple St',
            'Oak St','Cedar Ln','Washington Ave','Main St','Broadway','Queens Blvd',
            'Atlantic Ave','Grand Concourse','Central Ave','Highland Ave','Forest Ave','Hillside Ave');

        -- 6. Email
        SET v_base_email = CONCAT(v_lname, LOWER(LEFT(v_fname, 1)));
        SET v_email = CONCAT(v_base_email, '@lucid.edu');
        SET v_email_suffix = 2;
        WHILE EXISTS (SELECT 1 FROM LOGIN WHERE User_Email = v_email) DO
            SET v_email = CONCAT(v_base_email, v_email_suffix, '@lucid.edu');
            SET v_email_suffix = v_email_suffix + 1;
        END WHILE;

        -- 7. Thesis & Credits (PhD gets unique dept-matched dissertation, MS has NULL)
        IF v_grad_program = 'PHD' THEN
            SET v_credits = (4 + FLOOR(RAND() * 11)) * 4; -- PhD: 16 - 56 credits (4 to 14 courses)
            SET v_thesis = CONCAT(
                ELT(v_dept_id,
                    CONCAT('Computer Science Dissertation: ', ELT(1 + (v_i MOD 5),
                        'Fault-Tolerant Consensus in Distributed Cloud Systems',
                        'Transformer Architectures for Low-Resource Neural Machine Translation',
                        'Zero-Knowledge Cryptographic Proofs in Decentralized Networks',
                        'Query Optimization in High-Throughput Relational Database Engines',
                        'Adversarial Robustness in Autonomous Computer Vision Models')),
                    CONCAT('Physics Dissertation: ', ELT(1 + (v_i MOD 5),
                        'Quantum Decoherence and Entanglement in Topological Insulators',
                        'Non-Equilibrium Statistical Mechanics of Superconducting Qubits',
                        'Relativistic Magnetohydrodynamics in Binary Neutron Star Mergers',
                        'Ultrafast Laser Spectroscopy of Two-Dimensional Semiconductors',
                        'Dark Matter Halo Kinematics in Modified Gravity Frameworks')),
                    CONCAT('English Literature Dissertation: ', ELT(1 + (v_i MOD 5),
                        'Postcolonial Identity and Transnational Poetics in 20th-Century Fiction',
                        'Print Culture and Political Rhetoric in Early Modern British Drama',
                        'Ecocriticism and Landscape Memory in American Modernist Poetry',
                        'Archival Narratives of the Harlem Renaissance and Atlantic Modernity',
                        'Gothic Domesticity and Gender Performance in Victorian Serial Novels')),
                    CONCAT('Psychology Dissertation: ', ELT(1 + (v_i MOD 5),
                        'Prefrontal Executive Control and Working Memory Load in Young Adults',
                        'Longitudinal Attachment Trajectories and Adolescent Emotion Regulation',
                        'Neurobiological Correlates of Fear Extinction in Trauma Recovery',
                        'Implicit Social Cognition and Decision-Making Under Uncertainty',
                        'Circadian Disruption and Synaptic Plasticity in Sleep Deprivation')),
                    CONCAT('Mathematics Dissertation: ', ELT(1 + (v_i MOD 5),
                        'Spectral Theory of Non-Self-Adjoint Elliptic Differential Operators',
                        'Stochastic Partial Differential Equations in Rough Path Spaces',
                        'Cohomological Invariants of Algebraic Varieties Over Finite Fields',
                        'Ergodic Properties of Hyperbolic Dynamical Systems on Manifolds',
                        'High-Dimensional Ricci Flow and Geometric Analysis of Solitons')),
                    CONCAT('History Dissertation: ', ELT(1 + (v_i MOD 5),
                        'Maritime Trade Networks and Imperial Governance in the Early Atlantic World',
                        'Urban Labor Movements and Civil Rights Activism in Postwar America',
                        'Diplomatic Decolonization and Non-Aligned Statecraft in the Cold War',
                        'Agrarian Transformation and State Formation in Early Modern Europe',
                        'Public Memory and Constitutional Reconstruction in 19th-Century Republics')),
                    CONCAT('Economics Dissertation: ', ELT(1 + (v_i MOD 5),
                        'Macroeconomic Monetary Transmission Under Zero-Lower-Bound Constraints',
                        'Causal Inference and Instrumental Variables in Labor Market Panel Data',
                        'Asymmetric Information and Dynamic Pricing in Digital Platform Markets',
                        'Sovereign Debt Restructuring and Capital Flows in Emerging Economies',
                        'Behavioral Risk Preferences and Household Portfolio Allocation')),
                    CONCAT('Philosophy Dissertation: ', ELT(1 + (v_i MOD 5),
                        'Epistemic Contextualism and the Normativity of Rational Belief',
                        'Modal Metaphysics and Grounding in Contemporary Analytic Ontology',
                        'Moral Responsibility and Compatibilism in Deterministic Agency',
                        'Phenomenological Intentionality and Embodied Consciousness',
                        'Formal Semantics of Counterfactual Conditionals in Modal Logic')),
                    CONCAT('Engineering Dissertation: ', ELT(1 + (v_i MOD 5),
                        'Nonlinear Model Predictive Control for Autonomous Aerial Robotics',
                        'Computational Fluid-Structure Interaction in Composite Turbine Blades',
                        'Multiscale Finite Element Modeling of Additive Manufactured Alloys',
                        'Reinforcement Learning for Real-Time Microgrid Energy Dispatch',
                        'Nanoscale Thermal Transport in Wide-Bandgap Power Electronics')),
                    CONCAT('Biology Dissertation: ', ELT(1 + (v_i MOD 5),
                        'CRISPR-Cas9 Epigenetic Editing of Gene Regulatory Networks',
                        'Single-Cell Transcriptomics of Tumor Microenvironment Plasticity',
                        'Host-Microbiome Metabolic Signaling in Innate Immune Responses',
                        'Phylogenomic Divergence and Adaptive Radiation in Marine Ecosystems',
                        'Synaptic Vesicle Trafficking in Neurodegenerative Disease Models')),
                    CONCAT('Fine Arts Dissertation: ', ELT(1 + (v_i MOD 5),
                        'Interactive Generative Media and Spatial Installation in Post-Digital Art',
                        'Materiality, Pigment Conservation, and Curatorial Ethics in Modernism',
                        'Algorithmic Aesthetics and Spectatorship in Contemporary New Media',
                        'Public Sculpture, Urban Memory, and Site-Specific Intervention',
                        'Photographic Archives and Documentary Visual Culture in the 21st Century')),
                    CONCAT('Astronomy Dissertation: ', ELT(1 + (v_i MOD 5),
                        'Atmospheric Spectroscopy of Terrestrial Exoplanets Around M-Dwarf Stars',
                        'Supermassive Black Hole Feedback and Quasar Host Galaxy Co-Evolution',
                        'Circumstellar Disk Dust Coagulation and Protoplanetary Core Accretion',
                        'Gravitational Lensing Reconstruction of High-Redshift Galaxy Clusters',
                        'Magnetar Flare Energetics and Fast Radio Burst Emission Mechanisms'))
                ),
                ' [ID #', v_user_id, ']'
            );
        ELSE
            SET v_credits = FLOOR(RAND() * 5) * 4; -- MS: 0 - 16 credits (0 to 4 courses; does not surpass 18-20 credit cap)
            SET v_thesis = NULL;
        END IF;

        -- ================================================================
        -- INSERT CHAIN
        -- ================================================================
        INSERT INTO `USER` (User_ID, User_Type, User_FName, User_LName, User_MName, User_Gender, User_DOB, User_PhoneNo, Street_No, Street_Name, City, State, Country, Zip_Code)
        VALUES (v_user_id, 'Student', v_fname, v_lname, v_mname, v_gender, v_dob, v_phone, v_street_no, v_street_name, v_city, v_state, 'USA', v_zip);

        INSERT INTO LOGIN (User_ID, User_Email, User_Password, No_Login_Attempts, IsLock)
        VALUES (v_user_id, v_email, v_password_hash, 0, FALSE);

        INSERT INTO STUDENT (Student_ID, Student_Year, Student_Type, Dept_ID, Credits_Earned)
        VALUES (v_user_id, v_student_year, 'Graduate', v_dept_id, v_credits);

        INSERT INTO GRADUATE_STUDENT (Student_ID, Grad_Type, Grad_Program, Thesis)
        VALUES (v_user_id, v_grad_type, v_grad_program, v_thesis);

        IF v_grad_type = 'Full-Time' THEN
            INSERT INTO FULL_TIME_GRADUATE (Student_ID) VALUES (v_user_id);
        ELSE
            INSERT INTO PART_TIME_GRADUATE (Student_ID) VALUES (v_user_id);
        END IF;

        -- ----------------------------------------------------------------
        -- INTEGRATED GRADUATE MAJOR
        -- ----------------------------------------------------------------
        IF v_grad_program = 'MS' THEN
            -- MS: Even Major_ID (102..124)
            SET v_grad_major = 100 + (v_dept_id * 2);
        ELSE
            -- PhD: Ph.D. Major_ID (125..136)
            SET v_grad_major = 124 + v_dept_id;
        END IF;

        INSERT INTO STUDENT_MAJORS (Major_ID, Student_ID) VALUES (v_grad_major, v_user_id);

        -- ----------------------------------------------------------------
        -- INTEGRATED ADVISOR
        -- ----------------------------------------------------------------
        INSERT INTO ADVISOR_STUDENT (Faculty_ID, Student_ID, Date_Assigned)
        SELECT dr.Faculty_ID, v_user_id, '2026-08-25'
        FROM DEPARTMENT_ROSTER dr
        JOIN FULL_TIME_FACULTY ft ON dr.Faculty_ID = ft.Faculty_ID
        WHERE dr.Department_ID = v_dept_id
          AND (SELECT COUNT(*) FROM ADVISOR_STUDENT a WHERE a.Faculty_ID = dr.Faculty_ID) < 15
        ORDER BY RAND()
        LIMIT 1;

        SET v_i = v_i + 1;
    END WHILE;

    COMMIT;
END$$

DELIMITER ;

CALL SeedGraduates();
DROP PROCEDURE IF EXISTS SeedGraduates;

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