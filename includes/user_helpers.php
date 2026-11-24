<?php
/**
 * Lucid University - User Creation & Account Management Helpers
 */

function generateUserID(mysqli $conn): int
{
    do {
        $userID = random_int(100000, 9999999);

        $stmt = $conn->prepare(
            "SELECT User_ID FROM `USER` WHERE User_ID = ?"
        );

        $stmt->bind_param("i", $userID);
        $stmt->execute();

        $result = $stmt->get_result();
        $exists = $result->num_rows > 0;

        $stmt->close();

    } while ($exists);

    return $userID;
}

function createUser(mysqli $conn, int $userID, array $data): void
{
    $sql = "
        INSERT INTO `USER`
        (
            User_ID,
            User_Type,
            User_FName,
            User_LName,
            User_MName,
            User_Gender,
            User_DOB,
            User_PhoneNo,
            Street_No,
            Street_Name,
            City,
            State,
            Country,
            Zip_Code
        )
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ";

    $stmt = $conn->prepare($sql);

    $middleName = !empty($data["middle_name"]) ? $data["middle_name"] : null;
    $gender     = !empty($data["gender"]) ? $data["gender"] : null;
    $dob        = !empty($data["dob"]) ? $data["dob"] : null;
    $phone      = !empty($data["phone"]) ? $data["phone"] : null;
    $streetNo   = !empty($data["street_no"]) ? $data["street_no"] : null;
    $streetName = !empty($data["street_name"]) ? $data["street_name"] : null;
    $city       = !empty($data["city"]) ? $data["city"] : null;
    $state      = !empty($data["state"]) ? $data["state"] : null;
    $country    = !empty($data["country"]) ? $data["country"] : null;
    $zipCode    = !empty($data["zip_code"]) ? $data["zip_code"] : null;

    $stmt->bind_param(
        "isssssssssssss",
        $userID,
        $data["user_type"],
        $data["first_name"],
        $data["last_name"],
        $middleName,
        $gender,
        $dob,
        $phone,
        $streetNo,
        $streetName,
        $city,
        $state,
        $country,
        $zipCode
    );

    $stmt->execute();
    $stmt->close();
}

function createLogin(mysqli $conn, int $userID, array $data): void
{
    $password = password_hash(
        $data["password"],
        PASSWORD_DEFAULT
    );

    $sql = "
        INSERT INTO LOGIN
        (
            User_ID,
            User_Email,
            User_Password
        )
        VALUES (?, ?, ?)
    ";

    $stmt = $conn->prepare($sql);

    $stmt->bind_param(
        "iss",
        $userID,
        $data["email"],
        $password
    );

    $stmt->execute();
    $stmt->close();
}

function createAdmin(mysqli $conn, int $userID, array $data): void
{
    if (empty($data["security_level"])) {
        throw new Exception(
            "Security level is required for an Administrator."
        );
    }

    $stmt = $conn->prepare(
        "INSERT INTO ADMIN (Admin_ID, Security_Level)
         VALUES (?, ?)"
    );

    $stmt->bind_param(
        "ii",
        $userID,
        $data["security_level"]
    );

    $stmt->execute();
    $stmt->close();
}

function createStudent(mysqli $conn, int $userID, array $data): void
{
    if (
        empty($data["student_year"]) ||
        empty($data["student_level"])
    ) {
        throw new Exception(
            "Student year and student type are required."
        );
    }

    $studentType = $data["student_level"];

    if (!in_array($studentType, ["Undergraduate", "Graduate"], true)) {
        throw new Exception("Invalid student type.");
    }

    $departmentID = !empty($data["department_id"])
        ? $data["department_id"]
        : null;

    $stmt = $conn->prepare(
        "INSERT INTO STUDENT
        (
            Student_ID,
            Student_Year,
            Student_Type,
            Dept_ID
        )
        VALUES (?, ?, ?, ?)"
    );

    $stmt->bind_param(
        "iisi",
        $userID,
        $data["student_year"],
        $studentType,
        $departmentID
    );

    $stmt->execute();
    $stmt->close();

    if ($studentType === "Undergraduate") {

        $undergradType = str_replace('_', '-', $data["undergrad_type"] ?? "");
        if (empty($undergradType)) {
            throw new Exception(
                "Undergraduate type is required."
            );
        }

        if (!in_array(
            $undergradType,
            ["Full-Time", "Part-Time"],
            true
        )) {
            throw new Exception(
                "Invalid undergraduate type."
            );
        }

        $stmt = $conn->prepare(
            "INSERT INTO UNDERGRADUATE_STUDENT
            (Student_ID, Undergrad_Type)
            VALUES (?, ?)"
        );

        $stmt->bind_param(
            "is",
            $userID,
            $undergradType
        );

        $stmt->execute();
        $stmt->close();

        if ($undergradType === "Full-Time") {
            $table = "FULL_TIME_UNDERGRADUATE";
        } else {
            $table = "PART_TIME_UNDERGRADUATE";
        }

    } else {

        $gradType = str_replace('_', '-', $data["grad_type"] ?? "");
        if (
            empty($gradType) ||
            empty($data["grad_program"])
        ) {
            throw new Exception(
                "Graduate type and graduate program are required."
            );
        }

        if (!in_array(
            $gradType,
            ["Full-Time", "Part-Time"],
            true
        )) {
            throw new Exception(
                "Invalid graduate type."
            );
        }

        $thesis = !empty($data["thesis"])
            ? $data["thesis"]
            : null;

        $stmt = $conn->prepare(
            "INSERT INTO GRADUATE_STUDENT
            (
                Student_ID,
                Grad_Type,
                Grad_Program,
                Thesis
            )
            VALUES (?, ?, ?, ?)"
        );

        $stmt->bind_param(
            "isss",
            $userID,
            $gradType,
            $data["grad_program"],
            $thesis
        );

        $stmt->execute();
        $stmt->close();

        if ($gradType === "Full-Time") {
            $table = "FULL_TIME_GRADUATE";
        } else {
            $table = "PART_TIME_GRADUATE";
        }
    }

    $allowedTables = [
        "FULL_TIME_UNDERGRADUATE",
        "PART_TIME_UNDERGRADUATE",
        "FULL_TIME_GRADUATE",
        "PART_TIME_GRADUATE"
    ];

    if (!in_array($table, $allowedTables, true)) {
        throw new Exception(
            "Invalid structural subtype configuration."
        );
    }

    $stmt = $conn->prepare(
        "INSERT INTO $table (Student_ID) VALUES (?)"
    );

    $stmt->bind_param("i", $userID);
    $stmt->execute();
    $stmt->close();

    // Automatically assign default department major in STUDENT_MAJORS and optional eligible advisor
    if ($departmentID) {
        if ($studentType === "Undergraduate") {
            $majorId = 100 + ($departmentID * 2) - 1;
        } else {
            $gradProg = strtoupper(trim((string)($data["grad_program"] ?? '')));
            if (str_contains($gradProg, 'PHD')) {
                $majorId = 124 + $departmentID;
            } else {
                $majorId = 100 + ($departmentID * 2);
            }
        }

        $mStmt = $conn->prepare("INSERT INTO STUDENT_MAJORS (Major_ID, Student_ID) VALUES (?, ?)");
        $mStmt->bind_param("ii", $majorId, $userID);
        $mStmt->execute();
        $mStmt->close();

        if (!empty($data["advisor_id"])) {
            $advisorId = (int)$data["advisor_id"];
            $advCheckStmt = $conn->prepare("
                SELECT 1
                FROM FACULTY f
                JOIN FULL_TIME_FACULTY ftf ON f.Faculty_ID = ftf.Faculty_ID
                JOIN DEPARTMENT_ROSTER dr ON f.Faculty_ID = dr.Faculty_ID
                WHERE f.Faculty_ID = ? AND dr.Department_ID = ?
                  AND (SELECT COUNT(*) FROM ADVISOR_STUDENT a WHERE a.Faculty_ID = f.Faculty_ID) < 15
                LIMIT 1
            ");
            $advCheckStmt->bind_param("ii", $advisorId, $departmentID);
            $advCheckStmt->execute();
            $isEligible = $advCheckStmt->get_result()->fetch_row();
            $advCheckStmt->close();

            if (!$isEligible) {
                throw new Exception("Selected advisor is not eligible (must be a full-time faculty member in the student's department with fewer than 15 advisees).");
            }

            $advAssignStmt = $conn->prepare("
                INSERT INTO ADVISOR_STUDENT (Faculty_ID, Student_ID, Date_Assigned)
                VALUES (?, ?, CURRENT_DATE())
            ");
            $advAssignStmt->bind_param("ii", $advisorId, $userID);
            $advAssignStmt->execute();
            $advAssignStmt->close();
        }
    }
}

function createFaculty(mysqli $conn, int $userID, array $data): void
{
    $facultyStatus = str_replace('_', '-', $data["faculty_status"] ?? "");
    if (empty($facultyStatus)) {
        throw new Exception(
            "Faculty status is required."
        );
    }

    if (!in_array(
        $facultyStatus,
        ["Full-Time", "Part-Time"],
        true
    )) {
        throw new Exception(
            "Invalid faculty status."
        );
    }

    $officeID  = !empty($data["office_id"]) ? $data["office_id"] : null;
    $specialty = !empty($data["specialty"]) ? $data["specialty"] : null;
    $rank      = !empty($data["rank"]) ? $data["rank"] : null;

    $stmt = $conn->prepare(
        "INSERT INTO FACULTY
        (
            Faculty_ID,
            Office_ID,
            Faculty_Type,
            Specialty,
            Rank
        )
        VALUES (?, ?, ?, ?, ?)"
    );

    $stmt->bind_param(
        "issss",
        $userID,
        $officeID,
        $facultyStatus,
        $specialty,
        $rank
    );

    $stmt->execute();
    $stmt->close();

    if ($facultyStatus === "Full-Time") {

        $noClasses = isset($data["no_classes"])
            ? (int) $data["no_classes"]
            : 0;

        $stmt = $conn->prepare(
            "INSERT INTO FULL_TIME_FACULTY
            (Faculty_ID, No_Classes)
            VALUES (?, ?)"
        );

        $stmt->bind_param(
            "ii",
            $userID,
            $noClasses
        );

    } else {

        $stmt = $conn->prepare(
            "INSERT INTO PART_TIME_FACULTY
            (Faculty_ID)
            VALUES (?)"
        );

        $stmt->bind_param("i", $userID);
    }

    $stmt->execute();
    $stmt->close();

    $facultyDeptId = !empty($data["faculty_department_id"]) ? (int)$data["faculty_department_id"] : (!empty($data["department_id"]) ? (int)$data["department_id"] : null);
    if (!$facultyDeptId) {
        throw new Exception("Faculty department assignment is required.");
    }

    $deptStmt = $conn->prepare("
        INSERT INTO DEPARTMENT_ROSTER (Department_ID, Faculty_ID, Date_of_Assignment, Percent_Time)
        VALUES (?, ?, CURRENT_DATE(), 100.00)
    ");
    $deptStmt->bind_param("ii", $facultyDeptId, $userID);
    $deptStmt->execute();
    $deptStmt->close();
}

function createStatDept(mysqli $conn, int $userID, array $data = []): void
{
    $colCheck = $conn->query("SHOW COLUMNS FROM STAT_DEPT LIKE 'Employment_Status'");
    if ($colCheck && $colCheck->num_rows > 0) {
        $stmt = $conn->prepare("INSERT INTO STAT_DEPT (Stat_ID, Employment_Status) VALUES (?, 'Full-Time')");
        $stmt->bind_param("i", $userID);
    } else {
        $stmt = $conn->prepare("INSERT INTO STAT_DEPT (Stat_ID) VALUES (?)");
        $stmt->bind_param("i", $userID);
    }
    $stmt->execute();
    $stmt->close();
}

/**
 * Fetch a user's core USER + LOGIN account record by User_ID.
 */
function getUserAccountById(mysqli $conn, int $user_id): ?array
{
    $stmt = $conn->prepare("
        SELECT u.User_ID, u.User_Type, u.User_FName, u.User_MName, u.User_LName,
               u.User_Gender, u.User_DOB, u.User_PhoneNo,
               u.Street_No, u.Street_Name, u.City, u.State, u.Country, u.Zip_Code,
               l.User_Email
        FROM `USER` u
        LEFT JOIN LOGIN l ON u.User_ID = l.User_ID
        WHERE u.User_ID = ?
        LIMIT 1
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return $user ?: null;
}

/**
 * Fetch an Administrator's Security_Level from ADMIN.
 */
function getAdminSecurityLevel(mysqli $conn, int $admin_id): ?int
{
    $stmt = $conn->prepare("SELECT Security_Level FROM ADMIN WHERE Admin_ID = ? LIMIT 1");
    $stmt->bind_param("i", $admin_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return $row ? (int)$row['Security_Level'] : null;
}
