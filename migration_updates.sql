-- ============================================================================
-- Lucid University Migration Updates (Idempotent, MariaDB & MySQL 8 Compatible)
-- ============================================================================

-- 1. Safely add ENROLLMENT.Midterm_Grade
SET @col_exists = (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ENROLLMENT' AND COLUMN_NAME = 'Midterm_Grade'
);
SET @sql = IF(@col_exists = 0, 'ALTER TABLE ENROLLMENT ADD COLUMN Midterm_Grade VARCHAR(5) NULL DEFAULT NULL AFTER Student_ID', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 2. Safely add ENROLLMENT.Final_Grade
SET @col_exists = (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ENROLLMENT' AND COLUMN_NAME = 'Final_Grade'
);
SET @sql = IF(@col_exists = 0, 'ALTER TABLE ENROLLMENT ADD COLUMN Final_Grade VARCHAR(5) NULL DEFAULT NULL AFTER Midterm_Grade', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 3. Safely add MAJORS.Program_Level
SET @col_exists = (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'MAJORS' AND COLUMN_NAME = 'Program_Level'
);
SET @sql = IF(@col_exists = 0, "ALTER TABLE MAJORS ADD COLUMN Program_Level ENUM('Undergraduate', 'Graduate', 'Ph.D.') NOT NULL DEFAULT 'Undergraduate' AFTER Major_Name", 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 4. Safely add MINORS.Program_Level
SET @col_exists = (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'MINORS' AND COLUMN_NAME = 'Program_Level'
);
SET @sql = IF(@col_exists = 0, "ALTER TABLE MINORS ADD COLUMN Program_Level ENUM('Undergraduate', 'Graduate', 'Ph.D.') NOT NULL DEFAULT 'Undergraduate' AFTER Minor_Name", 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Backfill MAJORS program levels
UPDATE MAJORS SET Program_Level = 'Ph.D.'
WHERE Major_Name LIKE '%Ph.D.%' OR Major_Name LIKE '%Doctor%';

UPDATE MAJORS SET Program_Level = 'Graduate'
WHERE Program_Level = 'Undergraduate'
  AND (Major_Name LIKE '%M.S.%' OR Major_Name LIKE '%M.A.%' OR Major_Name LIKE '%M.F.A.%' OR Major_Name LIKE '%Master%');

-- 5. Ensure ATTENDANCE table exists
CREATE TABLE IF NOT EXISTS ATTENDANCE (
    CRN INT NOT NULL,
    Student_ID INT NOT NULL,
    Class_Date DATE NOT NULL,
    Status ENUM('Present', 'Absent') NOT NULL DEFAULT 'Absent',
    Recorded_At DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (CRN, Student_ID, Class_Date),
    FOREIGN KEY (CRN, Student_ID) REFERENCES ENROLLMENT(CRN, Student_ID) ON DELETE CASCADE
) ENGINE=InnoDB;

-- 6. Update Past Semester Section Statuses to 'Past'
UPDATE COURSE_SECTIONS cs
JOIN SEMESTER s ON cs.Semester_ID = s.Semester_ID
SET cs.Status = 'Past'
WHERE (s.End_Date < CURRENT_DATE() OR cs.Semester_ID < 202608)
  AND COALESCE(cs.Status, 'Active') = 'Active';

-- 7. Strip Attached Student IDs from Ph.D. / Graduate Thesis Titles
UPDATE GRADUATE_STUDENT
SET Thesis = TRIM(REGEXP_REPLACE(Thesis, '(\\s*[-–:|#\\[]*\\s*(?:ID\\s*)?\\(?#?[0-9]{5,7}\\)?\\]?)\\s*$', ''))
WHERE Thesis IS NOT NULL AND Thesis REGEXP '[0-9]{5,7}';

-- 8. Ensure PROFILE_CHANGE_REQUESTS table exists for student profile change requests
CREATE TABLE IF NOT EXISTS PROFILE_CHANGE_REQUESTS (
    Request_ID INT NOT NULL AUTO_INCREMENT,
    User_ID INT NOT NULL,
    Req_FName VARCHAR(50) NOT NULL,
    Req_MName VARCHAR(50),
    Req_LName VARCHAR(50) NOT NULL,
    Req_Gender VARCHAR(15),
    Req_DOB DATE,
    Req_PhoneNo VARCHAR(15),
    Req_Street_No VARCHAR(10),
    Req_Street_Name VARCHAR(50),
    Req_City VARCHAR(50),
    Req_State VARCHAR(20),
    Req_Country VARCHAR(50),
    Req_Zip_Code VARCHAR(15),
    Request_Reason VARCHAR(255),
    Request_Status VARCHAR(20) NOT NULL DEFAULT 'Pending',
    Requested_At DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    Reviewed_By INT,
    Reviewed_At DATETIME,
    Admin_Notes VARCHAR(255),
    PRIMARY KEY (Request_ID),
    FOREIGN KEY (User_ID) REFERENCES USER(User_ID) ON DELETE CASCADE,
    FOREIGN KEY (Reviewed_By) REFERENCES ADMIN(Admin_ID) ON DELETE SET NULL
) ENGINE=InnoDB;
