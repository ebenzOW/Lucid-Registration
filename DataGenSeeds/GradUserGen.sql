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