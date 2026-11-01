-- 1. INSERT FALL 2026 & SPRING 2027 SEMESTERS
INSERT INTO SEMESTER (Semester_ID, Semester_Name, Semester_Year, Start_Date, End_Date) VALUES
(202208, 'Fall',   2022, '2022-08-29', '2022-12-19'),
(202301, 'Spring', 2023, '2023-01-23', '2023-05-18'),
(202308, 'Fall',   2023, '2023-08-28', '2023-12-18'),
(202401, 'Spring', 2024, '2024-01-22', '2024-05-16'),
(202408, 'Fall',   2024, '2024-08-26', '2024-12-16'),
(202501, 'Spring', 2025, '2025-01-21', '2025-05-15'),
(202508, 'Fall',   2025, '2025-08-25', '2025-12-15'),
(202601, 'Spring', 2026, '2026-01-20', '2026-05-14'),
(202608, 'Fall',   2026, '2026-08-31', '2026-12-21'),
(202701, 'Spring', 2027, '2027-01-25', '2027-05-20');

-- 2. INSERT INFORMATION_RELATIONS FOR SEMESTERS
INSERT INTO INFORMATION_RELATION (Semester_ID, Last_Day_Of_Registration, Last_Day_Of_Dropping_Course, Date_Of_Midterm_Grade_Recording, Date_Of_Final_Grades_Recording) VALUES 
(202608, '2026-09-01', '2026-09-15', '2026-10-15', '2026-12-22'),
(202701, '2027-01-25', '2027-02-08', '2027-03-15', '2027-05-27');