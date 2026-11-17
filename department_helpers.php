<?php
/**
 * Read-only public department catalog helpers.
 * Uses the DEPARTMENT, MAJORS, and MINORS tables in schema(1).sql.
 */

function escapeHtml(mixed $value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function getAllDepartments(mysqli $conn): array
{
    $sql = 'SELECT Department_ID, Department_Name
            FROM DEPARTMENT
            ORDER BY Department_Name ASC';

    return $conn->query($sql)->fetch_all(MYSQLI_ASSOC);
}

function getDepartmentById(mysqli $conn, int $departmentId): ?array
{
    $stmt = $conn->prepare(
        'SELECT Department_ID, Department_Name, Department_Email, Department_Phone
         FROM DEPARTMENT
         WHERE Department_ID = ?'
    );
    $stmt->bind_param('i', $departmentId);
    $stmt->execute();
    $department = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return $department ?: null;
}

function getMajorsByDepartment(mysqli $conn, int $departmentId): array
{
    $stmt = $conn->prepare(
        'SELECT Major_ID, Major_Name, No_Credits_Required
         FROM MAJORS
         WHERE Department_ID = ?
         ORDER BY Major_Name ASC'
    );
    $stmt->bind_param('i', $departmentId);
    $stmt->execute();
    $majors = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    return $majors;
}

function getMinorsByDepartment(mysqli $conn, int $departmentId): array
{
    $stmt = $conn->prepare(
        'SELECT Minor_ID, Minor_Name, No_Credits_Required
         FROM MINORS
         WHERE Department_ID = ?
         ORDER BY Minor_Name ASC'
    );
    $stmt->bind_param('i', $departmentId);
    $stmt->execute();
    $minors = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    return $minors;
}

/**
 * Courses offered by this department. These are NOT necessarily approved
 * electives for a particular major or minor.
 */
function getCoursesByDepartment(mysqli $conn, int $departmentId): array
{
    $stmt = $conn->prepare(
        'SELECT Course_ID, Course_Name, Course_Description, Course_Credits, Course_Type
         FROM COURSES
         WHERE Department_ID = ?
         ORDER BY Course_ID ASC'
    );
    $stmt->bind_param('i', $departmentId);
    $stmt->execute();
    $courses = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    return $courses;
}

/**
 * Map each major in a department to its explicitly required courses.
 * Courses from OTHER departments are included when they are a requirement.
 * Returns [Major_ID => [course rows...]].
 */
function getMajorRequiredCoursesByDepartment(mysqli $conn, int $departmentId): array
{
    $stmt = $conn->prepare(
        'SELECT mr.Major_ID, mr.Min_Grade,
                c.Course_ID, c.Course_Name, c.Course_Description,
                c.Course_Credits, c.Course_Type, d.Department_Name AS Course_Department_Name
         FROM MAJORS AS m
         JOIN MAJOR_REQUIREMENTS AS mr ON mr.Major_ID = m.Major_ID
         JOIN COURSES AS c ON c.Course_ID = mr.Course_ID
         JOIN DEPARTMENT AS d ON d.Department_ID = c.Department_ID
         WHERE m.Department_ID = ?
         ORDER BY mr.Major_ID ASC, c.Course_ID ASC'
    );
    $stmt->bind_param('i', $departmentId);
    $stmt->execute();
    $coursesByMajor = [];
    foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $course) {
        $coursesByMajor[(int) $course['Major_ID']][] = $course;
    }
    $stmt->close();

    return $coursesByMajor;
}

/**
 * Map each minor in a department to its explicitly required courses.
 * Returns [Minor_ID => [course rows...]].
 */
function getMinorRequiredCoursesByDepartment(mysqli $conn, int $departmentId): array
{
    $stmt = $conn->prepare(
        'SELECT mr.Minor_ID, mr.Min_Grade,
                c.Course_ID, c.Course_Name, c.Course_Description,
                c.Course_Credits, c.Course_Type, d.Department_Name AS Course_Department_Name
         FROM MINORS AS m
         JOIN MINOR_REQUIREMENTS AS mr ON mr.Minor_ID = m.Minor_ID
         JOIN COURSES AS c ON c.Course_ID = mr.Course_ID
         JOIN DEPARTMENT AS d ON d.Department_ID = c.Department_ID
         WHERE m.Department_ID = ?
         ORDER BY mr.Minor_ID ASC, c.Course_ID ASC'
    );
    $stmt->bind_param('i', $departmentId);
    $stmt->execute();
    $coursesByMinor = [];
    foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $course) {
        $coursesByMinor[(int) $course['Minor_ID']][] = $course;
    }
    $stmt->close();

    return $coursesByMinor;
}
