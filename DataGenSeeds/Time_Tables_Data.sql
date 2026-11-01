
-- 1. Populate PERIOD
INSERT INTO PERIOD (Period_ID, Start_Time, End_Time) VALUES
(1, '08:00:00', '09:40:00'),
(2, '09:50:00', '11:30:00'),
(3, '11:40:00', '13:20:00'),
(4, '13:30:00', '15:10:00'),
(5, '15:20:00', '17:00:00'),
(6, '17:10:00', '18:50:00'),
(7, '19:00:00', '20:40:00'),
(8, '20:50:00', '22:30:00');

-- 2. Populate DAY
INSERT INTO DAY (Day_ID, Weekday) VALUES
(1, 'Monday'),
(2, 'Tuesday'),
(3, 'Wednesday'),
(4, 'Thursday'),
(5, 'Friday'),
(6, 'Saturday'),
(7, 'Sunday');

-- 3. Create individual weekday (Monday-Friday) time slots (IDs 1-40)
INSERT INTO TIME_SLOT (Time_Slot_ID, Time_Slot_Day, Period_ID)
SELECT
    (p.Period_ID - 1) * 5 + d.Day_ID,
    d.Weekday,
    p.Period_ID
FROM PERIOD p
CROSS JOIN DAY d
WHERE d.Day_ID <= 5
ORDER BY p.Period_ID, d.Day_ID;

-- 4. Link individual slots to their corresponding weekdays
INSERT INTO TIME_SLOT_DAY (Time_Slot_ID, Day_ID)
SELECT ts.Time_Slot_ID, d.Day_ID
FROM TIME_SLOT ts
JOIN DAY d ON ts.Time_Slot_Day = d.Weekday;

-- 5. Create Monday/Wednesday slots (IDs 101-108)
INSERT INTO TIME_SLOT (Time_Slot_ID, Time_Slot_Day, Period_ID)
SELECT p.Period_ID + 100, 'M/W', p.Period_ID
FROM PERIOD p;

-- 6. Create Tuesday/Thursday slots (IDs 109-116)
INSERT INTO TIME_SLOT (Time_Slot_ID, Time_Slot_Day, Period_ID)
SELECT p.Period_ID + 108, 'Tues/Thurs', p.Period_ID
FROM PERIOD p;

-- 7. Link M/W and Tues/Thurs slots to their weekdays
INSERT INTO TIME_SLOT_DAY (Time_Slot_ID, Day_ID)
SELECT ts.Time_Slot_ID, d.Day_ID
FROM TIME_SLOT ts
CROSS JOIN DAY d
WHERE ts.Time_Slot_ID BETWEEN 101 AND 116
  AND (
      (ts.Time_Slot_Day = 'M/W' AND d.Weekday IN ('Monday', 'Wednesday'))
      OR
      (ts.Time_Slot_Day = 'Tues/Thurs' AND d.Weekday IN ('Tuesday', 'Thursday'))
  );
