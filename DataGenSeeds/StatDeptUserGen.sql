-- ============================================================================
-- Lucid University: Generic Statistical Department (Stat-Dept) User Seed
-- Idempotent, MariaDB 10.2+ and MySQL 8.0+ compatible
--
-- Test Accounts (Plaintext Password: password):
--   1. patelr@lucid.edu   (User_ID / Stat_ID: 9100001) - Rachel M. Patel
--   2. chenm@lucid.edu    (User_ID / Stat_ID: 9100002) - Marcus T. Chen
--   3. brooksj@lucid.edu  (User_ID / Stat_ID: 9100003) - Jordan A. Brooks
-- ============================================================================

START TRANSACTION;

INSERT IGNORE INTO `USER` (
    User_ID,
    User_Type,
    User_FName,
    User_MName,
    User_LName,
    User_Gender,
    User_DOB,
    User_PhoneNo,
    Street_No,
    Street_Name,
    City,
    State,
    Country,
    Zip_Code
) VALUES
    (9100001, 'Stat-Dept', 'Rachel', 'M.', 'Patel',  'Female',     '1986-04-14', '516-555-0401', '100', 'Campus Plaza', 'Old Westbury', 'NY', 'USA', '11568'),
    (9100002, 'Stat-Dept', 'Marcus', 'T.', 'Chen',   'Male',       '1983-09-28', '516-555-0402', '215', 'Jericho Tpke', 'Mineola',      'NY', 'USA', '11501'),
    (9100003, 'Stat-Dept', 'Jordan', 'A.', 'Brooks', 'Non-Binary', '1990-02-19', '516-555-0403', '340', 'Merrick Ave',  'Merrick',      'NY', 'USA', '11566');

INSERT IGNORE INTO LOGIN (
    User_ID,
    User_Email,
    User_Password
) VALUES
    (9100001, 'patelr@lucid.edu',  '$2y$10$syLuuUfhTYX2U4PAENJIZeMoYTRpPoHkdpKWcTyHAfPWp2YD.Or2u'),
    (9100002, 'chenm@lucid.edu',   '$2y$10$syLuuUfhTYX2U4PAENJIZeMoYTRpPoHkdpKWcTyHAfPWp2YD.Or2u'),
    (9100003, 'brooksj@lucid.edu', '$2y$10$syLuuUfhTYX2U4PAENJIZeMoYTRpPoHkdpKWcTyHAfPWp2YD.Or2u');

INSERT IGNORE INTO STAT_DEPT (Stat_ID) VALUES
    (9100001),
    (9100002),
    (9100003);

COMMIT;
