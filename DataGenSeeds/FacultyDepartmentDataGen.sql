
-- Run order: schema.sql, Building_Room_Data.sql, then this file.
-- Departments are inserted first with their department office (LIBN-301 .. LIBN-312) but without
-- Chair_ID; the chairs are assigned during the faculty generation below.
INSERT INTO DEPARTMENT (Department_ID, Department_Name, Department_Email, Department_Phone, Office_ID)
VALUES
(1, 'Computer Science', 'CompSciDept@Lucid.edu', '9417441085', 'LIBN-301'),
(2, 'Physics', 'PhysicsDept@Lucid.edu', '3809975286', 'LIBN-302'),
(3, 'English', 'EnglishDept@Lucid.edu', '3252938628', 'LIBN-303'),
(4, 'Psychology', 'PsychologyDept@Lucid.edu', '8187848925', 'LIBN-304'),
(5, 'Mathematics', 'MathDept@Lucid.edu', '9193065764', 'LIBN-305'),
(6, 'History', 'HistoryDept@Lucid.edu', '7473813355', 'LIBN-306'),
(7, 'Economics', 'EconDept@Lucid.edu', '6825504432', 'LIBN-307'),
(8, 'Philosophy', 'PhilosophyDept@Lucid.edu', '2344844302', 'LIBN-308'),
(9, 'Engineering', 'EngineeringDept@Lucid.edu', '4402601702', 'LIBN-309'),
(10, 'Biology', 'BiologyDept@Lucid.edu', '9093810048', 'LIBN-310'),
(11, 'Art', 'ArtDept@Lucid.edu', '6789047659', 'LIBN-311'),
(12, 'Astronomy', 'AstronomyDept@Lucid.edu', '4694102072', 'LIBN-312');

DELETE FROM USER WHERE User_Type = 'Faculty';

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
    DECLARE v_dept_2 INT;
    DECLARE v_dept_3 INT;
    DECLARE v_office_id VARCHAR(15);
    DECLARE v_faculty_type VARCHAR(20);
    DECLARE v_rank VARCHAR(50);
    DECLARE v_specialty VARCHAR(100);
    DECLARE v_assign_date DATE;
    DECLARE v_roll DECIMAL(5,4);

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
            UNTIL NOT EXISTS (SELECT 1 FROM USER WHERE User_ID = v_user_id)
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
        INSERT INTO USER (
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


