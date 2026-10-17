<?php

function generateUserID($conn)
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


function createUser($conn, $userID, $data)
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


function createLogin($conn, $userID, $data)
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


function createAdmin($conn, $userID, $data)
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


function createStudent($conn, $userID, $data)
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

        if (empty($data["undergrad_type"])) {
            throw new Exception(
                "Undergraduate type is required."
            );
        }

        if (!in_array(
            $data["undergrad_type"],
            ["Full_Time", "Part_Time"],
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
            $data["undergrad_type"]
        );

        $stmt->execute();
        $stmt->close();

        if ($data["undergrad_type"] === "Full_Time") {
            $table = "FULL_TIME_UNDERGRADUATE";
        } else {
            $table = "PART_TIME_UNDERGRADUATE";
        }

    } else {

        if (
            empty($data["grad_type"]) ||
            empty($data["grad_program"])
        ) {
            throw new Exception(
                "Graduate type and graduate program are required."
            );
        }

        if (!in_array(
            $data["grad_type"],
            ["Full_Time", "Part_Time"],
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
            $data["grad_type"],
            $data["grad_program"],
            $thesis
        );

        $stmt->execute();
        $stmt->close();

        if ($data["grad_type"] === "Full_Time") {
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
}


function createFaculty($conn, $userID, $data)
{
    if (empty($data["faculty_status"])) {
        throw new Exception(
            "Faculty status is required."
        );
    }

    if (!in_array(
    $data["faculty_status"],
    ["Full_Time", "Part_Time"],
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
        $data["faculty_status"],
        $specialty,
        $rank
    );

    $stmt->execute();
    $stmt->close();

    if ($data["faculty_status"] === "Full_Time") {

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
}

?>