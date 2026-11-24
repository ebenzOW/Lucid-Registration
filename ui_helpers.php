<?php
/**
 * Lucid University - UI & Presentation Helpers
 */

/**
 * HTML Output Escaping
 */
function escapeHtml(mixed $value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

/**
 * Format day and start/end period times cleanly (e.g. "M/W 08:00 AM - 09:40 AM").
 */
if (!function_exists('formatSchedule')) {
    function formatSchedule($day, $start, $end): string {
        if (empty($start) || empty($end)) {
            return htmlspecialchars($day ?? 'TBA', ENT_QUOTES, 'UTF-8');
        }
        $st = DateTime::createFromFormat('H:i:s', $start);
        $et = DateTime::createFromFormat('H:i:s', $end);
        $formattedTime = ($st && $et) ? ($st->format('g:i A') . ' - ' . $et->format('g:i A')) : ($start . ' - ' . $end);
        return htmlspecialchars(($day ? $day . ' ' : '') . $formattedTime, ENT_QUOTES, 'UTF-8');
    }
}

/**
 * Formats Street_No, Street_Name, City, State, Zip_Code, Country cleanly using commas and spaces only.
 * e.g. "123 University Ave, Old Westbury, NY 11568, United States".
 */
function formatAddress(array $u): string {
    $streetParts = [];
    if (!empty($u['Street_No'])) {
        $streetParts[] = trim($u['Street_No']);
    }
    if (!empty($u['Street_Name'])) {
        $streetParts[] = trim($u['Street_Name']);
    }
    $street = implode(' ', $streetParts);

    $city = trim($u['City'] ?? '');
    $state = trim($u['State'] ?? '');
    $zip = trim($u['Zip_Code'] ?? '');
    $country = trim($u['Country'] ?? '');

    $cityStateZip = $city;
    if ($state !== '') {
        $cityStateZip .= ($cityStateZip !== '' ? ', ' : '') . $state;
    }
    if ($zip !== '') {
        $cityStateZip .= ($cityStateZip !== '' ? ' ' : '') . $zip;
    }

    $addressParts = [];
    if ($street !== '') {
        $addressParts[] = $street;
    }
    if ($cityStateZip !== '') {
        $addressParts[] = $cityStateZip;
    }
    if ($country !== '') {
        $addressParts[] = $country;
    }

    return implode(', ', $addressParts);
}

/**
 * Trims and strips any stray trailing student ID pattern defensively.
 */
function cleanThesisTitle(?string $thesis): string {
    if ($thesis === null || $thesis === '') {
        return '';
    }
    $clean = trim($thesis);
    $clean = preg_replace('/\s*[-–:|#\[]*\s*(?:ID\s*)?\(?#?\d{5,7}\)?\]?\s*$/i', '', $clean);
    return trim($clean);
}

/**
 * Renders the logged-in user's name in headers and navbars with a clean hyperlink.
 */
function getUserNavbarProfileHtml(mysqli $conn, int $user_id, ?string $user_type = null, string $link_classes = 'text-white'): string {
    $user_id = (int)$user_id;
    $stmt = $conn->prepare("SELECT User_FName, User_MName, User_LName FROM `USER` WHERE User_ID = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $u = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $fullName = trim(($u['User_FName'] ?? '') . ' ' . (!empty($u['User_MName']) ? $u['User_MName'] . ' ' : '') . ($u['User_LName'] ?? ''));
    if (empty($fullName)) {
        $fullName = "User #" . $user_id;
    }

    $prefix = file_exists('user_profile.php') ? '' : '../';
    $url = $prefix . "user_profile.php?user_id=" . $user_id;
    return '<a href="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '" class="fw-semibold text-decoration-underline ' . htmlspecialchars($link_classes, ENT_QUOTES, 'UTF-8') . '" title="View Full Profile">' . htmlspecialchars($fullName, ENT_QUOTES, 'UTF-8') . '</a>';
}

/**
 * Helper to render clickable link to faculty profile.
 */
function renderFacultyLink($faculty_id, $instructor_name, $root_prefix = '../'): string {
    if (empty($faculty_id) || empty($instructor_name) || strcasecmp(trim($instructor_name), 'Staff') === 0) {
        return htmlspecialchars($instructor_name ?: 'Staff', ENT_QUOTES, 'UTF-8');
    }
    $fid = (int)$faculty_id;
    $name = htmlspecialchars($instructor_name, ENT_QUOTES, 'UTF-8');
    $prefix = htmlspecialchars($root_prefix, ENT_QUOTES, 'UTF-8');
    return '<a href="' . $prefix . 'user_profile.php?user_id=' . $fid . '" class="text-decoration-none fw-semibold text-primary">' . $name . '</a>';
}

/**
 * Render unified Bootstrap 5.3 navigation bar for Administrator pages.
 */
function renderAdminNavbar(mysqli $conn, int $admin_id, string $activeFile = ''): string {
    $navItems = [
        'dashboard.php'              => 'Dashboard',
        'master_schedule.php'        => 'Master Schedule',
        'create_user.php'            => 'Create User',
        'view_users.php'             => 'View Users',
        'create_course_section.php'  => 'Create Course Section',
        'manage_courses.php'         => 'Manage Courses',
        'manage_holds.php'           => 'Manage Holds',
        'semesters.php'              => 'Semesters',
        'majors_minors.php'          => 'Majors & Minors',
        'manage_advisors.php'        => 'Advisors',
        'manage_facilities.php'      => 'Facilities',
        'manage_change_requests.php' => 'Change Requests',
    ];

    $profileHtml = getUserNavbarProfileHtml($conn, $admin_id, 'Admin');

    $linksHtml = '';
    foreach ($navItems as $file => $label) {
        $isActive = ($activeFile === $file);
        $activeClass = $isActive ? ' active' : '';
        $ariaCurrent = $isActive ? ' aria-current="page"' : '';
        $escapedLabel = htmlspecialchars($label, ENT_QUOTES, 'UTF-8');
        $linksHtml .= '<li class="nav-item">'
            . '<a class="nav-link' . $activeClass . '"' . $ariaCurrent . ' href="' . $file . '">' . $escapedLabel . '</a>'
            . '</li>' . "\n";
    }

    return <<<HTML
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark mb-4 shadow-sm">
        <div class="container-fluid px-lg-5">
            <a class="navbar-brand fw-bold" href="dashboard.php">Lucid University <span class="badge bg-danger ms-1">Admin</span></a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#adminNav" aria-controls="adminNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="adminNav">
                <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                    {$linksHtml}
                </ul>
                <div class="d-flex align-items-center gap-3">
                    {$profileHtml}
                    <form action="../logout.php" method="POST" class="m-0">
                        <button type="submit" class="btn btn-outline-light btn-sm">Log Out</button>
                    </form>
                </div>
            </div>
        </div>
    </nav>
HTML;
}

/**
 * Render unified Bootstrap 5.3 navigation bar for Student pages.
 */
function renderStudentNavbar(mysqli $conn, int $student_id, string $activeFile = ''): string {
    $navItems = [
        'dashboard.php'       => 'Dashboard',
        'schedule.php'        => 'My Schedule',
        'master_schedule.php' => 'Master Schedule',
        'register.php'        => 'Register / Add-Drop',
        'transcript.php'      => 'Transcript',
        'degree_audit.php'    => 'Degree Audit',
        'holds.php'           => 'Holds',
        'majors_minors.php'   => 'Majors & Minors',
        'profile_requests.php' => 'Profile Requests',
    ];

    $profileHtml = getUserNavbarProfileHtml($conn, $student_id, 'Student');

    $linksHtml = '';
    foreach ($navItems as $file => $label) {
        $isActive = ($activeFile === $file);
        $activeClass = $isActive ? ' active' : '';
        $ariaCurrent = $isActive ? ' aria-current="page"' : '';
        $escapedLabel = htmlspecialchars($label, ENT_QUOTES, 'UTF-8');
        $linksHtml .= '<li class="nav-item">'
            . '<a class="nav-link' . $activeClass . '"' . $ariaCurrent . ' href="' . $file . '">' . $escapedLabel . '</a>'
            . '</li>' . "\n";
    }

    return <<<HTML
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark mb-4 shadow-sm">
        <div class="container">
            <a class="navbar-brand fw-bold" href="dashboard.php">Lucid University</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#studentNav" aria-controls="studentNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="studentNav">
                <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                    {$linksHtml}
                </ul>
                <div class="d-flex align-items-center gap-3">
                    <span class="navbar-text text-white small d-none d-lg-inline">
                        Welcome, {$profileHtml}
                    </span>
                    <form action="../logout.php" method="POST" class="m-0">
                        <button type="submit" class="btn btn-outline-light btn-sm">Log Out</button>
                    </form>
                </div>
            </div>
        </div>
    </nav>
HTML;
}

/**
 * Render unified Bootstrap 5.3 navigation bar for Faculty pages.
 */
function renderFacultyNavbar(mysqli $conn, int $faculty_id, string $activePage = 'dashboard.php'): string {
    $isDashboard = ($activePage === 'dashboard.php');
    $isProfile = ($activePage === 'user_profile.php');

    $dashActiveClass = $isDashboard ? ' active' : '';
    $dashAria = $isDashboard ? ' aria-current="page"' : '';

    $profActiveClass = $isProfile ? ' active' : '';
    $profAria = $isProfile ? ' aria-current="page"' : '';

    $profileHtml = getUserNavbarProfileHtml($conn, $faculty_id, 'Faculty');
    $escapedFacultyId = (int)$faculty_id;

    return <<<HTML
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark mb-4 shadow-sm">
        <div class="container">
            <a class="navbar-brand fw-bold" href="dashboard.php">
                Lucid University <span class="badge bg-warning text-dark ms-1">Faculty</span>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#facultyNav" aria-controls="facultyNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="facultyNav">
                <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                    <li class="nav-item">
                        <a class="nav-link{$dashActiveClass}"{$dashAria} href="dashboard.php">Dashboard &amp; Current Sections</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link{$profActiveClass}"{$profAria} href="../user_profile.php?user_id={$escapedFacultyId}">My Profile</a>
                    </li>
                </ul>
                <div class="d-flex align-items-center gap-3">
                    {$profileHtml}
                    <form action="../logout.php" method="POST" class="m-0">
                        <button type="submit" class="btn btn-outline-light btn-sm">Log Out</button>
                    </form>
                </div>
            </div>
        </div>
    </nav>
HTML;
}

/**
 * Backwards-compatibility stub
 */
function getProfileHoverCardStyles(): string {
    return '';
}
