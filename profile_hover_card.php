<?php
/**
 * Lucid University Course Registration System
 * Reusable User Profile Hover Card Component (Zero Custom JavaScript)
 *
 * Renders the logged-in user's name in headers and navbars with a pure CSS hover card
 * displaying comprehensive profile details for Students, Faculty, and Administrators.
 */

if (!function_exists('getUserProfileData')) {
    /**
     * Fetch complete user demographic and role-specific data.
     */
    function getUserProfileData($conn, $user_id, $user_type = null) {
        // 1. Fetch base user and login information
        $stmt = $conn->prepare("
            SELECT u.User_ID, u.User_Type, u.User_FName, u.User_MName, u.User_LName,
                   u.User_Gender, u.User_DOB, u.User_PhoneNo,
                   u.Street_No, u.Street_Name, u.City, u.State, u.Country, u.Zip_Code,
                   l.User_Email
            FROM `USER` u
            LEFT JOIN LOGIN l ON u.User_ID = l.User_ID
            WHERE u.User_ID = ?
        ");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$user) {
            return null;
        }

        $user_type = $user_type ?: $user['User_Type'];
        $fullName = trim($user['User_FName'] . ' ' . (!empty($user['User_MName']) ? $user['User_MName'] . ' ' : '') . $user['User_LName']);
        $user['full_name'] = $fullName;

        // 2. Fetch role-specific details
        if ($user_type === 'Student') {
            $sStmt = $conn->prepare("
                SELECT s.Student_Year, s.Student_Type, s.Credits_Earned,
                       d.Department_Name,
                       COALESCE(ug.Undergrad_Type, g.Grad_Type, 'Full-Time') AS Attendance_Type
                FROM STUDENT s
                LEFT JOIN DEPARTMENT d ON s.Dept_ID = d.Department_ID
                LEFT JOIN UNDERGRADUATE_STUDENT ug ON s.Student_ID = ug.Student_ID
                LEFT JOIN GRADUATE_STUDENT g ON s.Student_ID = g.Student_ID
                WHERE s.Student_ID = ?
            ");
            $sStmt->bind_param("i", $user_id);
            $sStmt->execute();
            $sData = $sStmt->get_result()->fetch_assoc();
            $sStmt->close();

            if ($sData) {
                $user = array_merge($user, $sData);
            }

            // Map class year
            $yearMap = [
                1 => 'Freshman',
                2 => 'Sophomore',
                3 => 'Junior',
                4 => 'Senior',
                5 => 'Graduate'
            ];
            $yearVal = (int)($user['Student_Year'] ?? 1);
            if (strcasecmp($user['Student_Type'] ?? '', 'Graduate') === 0) {
                $user['class_standing'] = 'Graduate';
            } else {
                $user['class_standing'] = $yearMap[$yearVal] ?? 'Undergraduate';
            }

            // Fetch Majors
            $mStmt = $conn->prepare("
                SELECT m.Major_Name
                FROM STUDENT_MAJORS sm
                JOIN MAJORS m ON sm.Major_ID = m.Major_ID
                WHERE sm.Student_ID = ?
                ORDER BY sm.Major_ID ASC
            ");
            $mStmt->bind_param("i", $user_id);
            $mStmt->execute();
            $mRes = $mStmt->get_result();
            $majors = [];
            while ($mRow = $mRes->fetch_assoc()) {
                $majors[] = $mRow['Major_Name'];
            }
            $mStmt->close();
            $user['majors'] = $majors;

            // Fetch Minor
            $minStmt = $conn->prepare("
                SELECT m.Minor_Name
                FROM STUDENT_MINORS sm
                JOIN MINORS m ON sm.Minor_ID = m.Minor_ID
                WHERE sm.Student_ID = ?
                ORDER BY sm.Minor_ID ASC
            ");
            $minStmt->bind_param("i", $user_id);
            $minStmt->execute();
            $minRes = $minStmt->get_result();
            $minors = [];
            while ($minRow = $minRes->fetch_assoc()) {
                $minors[] = $minRow['Minor_Name'];
            }
            $minStmt->close();
            $user['minor'] = $minors[0] ?? null;

        } elseif ($user_type === 'Faculty') {
            $fStmt = $conn->prepare("
                SELECT f.Rank, f.Faculty_Type, f.Specialty, f.Office_ID, d.Department_Name
                FROM FACULTY f
                LEFT JOIN DEPARTMENT_ROSTER dr ON f.Faculty_ID = dr.Faculty_ID
                LEFT JOIN DEPARTMENT d ON dr.Department_ID = d.Department_ID
                WHERE f.Faculty_ID = ?
                LIMIT 1
            ");
            $fStmt->bind_param("i", $user_id);
            $fStmt->execute();
            $fData = $fStmt->get_result()->fetch_assoc();
            $fStmt->close();

            if ($fData) {
                $user = array_merge($user, $fData);
            }

        } elseif ($user_type === 'Admin') {
            $aStmt = $conn->prepare("
                SELECT Security_Level
                FROM ADMIN
                WHERE Admin_ID = ?
            ");
            $aStmt->bind_param("i", $user_id);
            $aStmt->execute();
            $aData = $aStmt->get_result()->fetch_assoc();
            $aStmt->close();

            if ($aData) {
                $user = array_merge($user, $aData);
            }
        }

        return $user;
    }
}

if (!function_exists('getUserNavbarProfileHtml')) {
    /**
     * Render modular profile hover card markup with pure CSS interactivity.
     */
    function getUserNavbarProfileHtml($conn, $user_id, $user_type = null, $textClass = 'text-white') {
        $u = getUserProfileData($conn, $user_id, $user_type);
        if (!$u) {
            return htmlspecialchars((string)$user_id, ENT_QUOTES, 'UTF-8');
        }

        $fullName    = $u['full_name'];
        $role        = $u['User_Type'];
        $email       = $u['User_Email'] ?? 'N/A';
        $phone       = $u['User_PhoneNo'] ?? 'N/A';
        $address     = trim(($u['Street_No'] ?? '') . ' ' . ($u['Street_Name'] ?? ''));
        $cityStateZip = trim(($u['City'] ?? '') . ($u['City'] && $u['State'] ? ', ' : '') . ($u['State'] ?? '') . ' ' . ($u['Zip_Code'] ?? ''));

        // Role Badge Styling
        $roleBadgeClass = match($role) {
            'Admin'   => 'bg-danger',
            'Faculty' => 'bg-warning text-dark',
            default   => 'bg-primary',
        };

        $roleLabel = ($role === 'Admin') ? 'Administrator' : $role;

        // Build Role-Specific Meta HTML
        $roleDetailsHtml = '';
        if ($role === 'Student') {
            $classStanding = htmlspecialchars($u['class_standing'] ?? 'Undergraduate', ENT_QUOTES, 'UTF-8');
            $attendance    = htmlspecialchars($u['Attendance_Type'] ?? 'Full-Time', ENT_QUOTES, 'UTF-8');
            $dept          = htmlspecialchars($u['Department_Name'] ?? 'General Academics', ENT_QUOTES, 'UTF-8');
            $majorsStr     = !empty($u['majors']) ? htmlspecialchars(implode(', ', $u['majors']), ENT_QUOTES, 'UTF-8') : 'Undeclared';
            $minorStr      = !empty($u['minor']) ? htmlspecialchars($u['minor'], ENT_QUOTES, 'UTF-8') : 'None';

            $roleDetailsHtml = "
                <div class='mb-1'><strong>Standing:</strong> {$classStanding} ({$attendance})</div>
                <div class='mb-1'><strong>Department:</strong> {$dept}</div>
                <div class='mb-1'><strong>Major:</strong> {$majorsStr}</div>
                <div class='mb-1'><strong>Minor:</strong> {$minorStr}</div>
            ";
        } elseif ($role === 'Faculty') {
            $rank       = htmlspecialchars($u['Rank'] ?? 'Instructor', ENT_QUOTES, 'UTF-8');
            $facType    = htmlspecialchars($u['Faculty_Type'] ?? 'Full-Time', ENT_QUOTES, 'UTF-8');
            $specialty  = htmlspecialchars($u['Specialty'] ?? 'General', ENT_QUOTES, 'UTF-8');
            $office     = htmlspecialchars($u['Office_ID'] ?? 'TBA', ENT_QUOTES, 'UTF-8');
            $dept       = htmlspecialchars($u['Department_Name'] ?? 'General', ENT_QUOTES, 'UTF-8');

            $roleDetailsHtml = "
                <div class='mb-1'><strong>Rank &amp; Type:</strong> {$rank} ({$facType})</div>
                <div class='mb-1'><strong>Department:</strong> {$dept}</div>
                <div class='mb-1'><strong>Specialty:</strong> {$specialty}</div>
                <div class='mb-1'><strong>Office:</strong> {$office}</div>
            ";
        } elseif ($role === 'Admin') {
            $secLevel = (int)($u['Security_Level'] ?? 1);
            $roleDetailsHtml = "
                <div class='mb-1'><strong>Security Level:</strong> Level {$secLevel}</div>
                <div class='mb-1'><strong>Role Scope:</strong> System Administration &amp; Scheduling</div>
            ";
        }

        // Return container with scoped CSS and accessible dropdown card
        ob_start();
        ?>
        <div class="user-profile-hover-container d-inline-block position-relative">
            <span class="user-profile-hover-trigger <?= htmlspecialchars($textClass, ENT_QUOTES, 'UTF-8') ?> fw-semibold" tabindex="0">
                <?= htmlspecialchars($fullName, ENT_QUOTES, 'UTF-8') ?>
                <span class="opacity-75 small">(ID: <?= (int)$user_id ?>)</span>
            </span>

            <!-- Hover Card Popover -->
            <div class="user-profile-card-dropdown shadow-lg p-3 bg-white border rounded text-dark text-start" role="tooltip">
                <div class="d-flex align-items-center gap-2 pb-2 mb-2 border-bottom">
                    <div class="rounded-circle bg-secondary text-white d-flex align-items-center justify-content-center fw-bold" style="width: 38px; height: 38px; font-size: 1rem;">
                        <?= htmlspecialchars(strtoupper(substr($u['User_FName'], 0, 1) . substr($u['User_LName'], 0, 1)), ENT_QUOTES, 'UTF-8') ?>
                    </div>
                    <div class="overflow-hidden">
                        <div class="fw-bold text-truncate" style="font-size: 0.95rem;"><?= htmlspecialchars($fullName, ENT_QUOTES, 'UTF-8') ?></div>
                        <span class="badge <?= $roleBadgeClass ?> py-1 px-2" style="font-size: 0.72rem;"><?= htmlspecialchars($roleLabel, ENT_QUOTES, 'UTF-8') ?></span>
                    </div>
                </div>

                <div class="small" style="font-size: 0.82rem; line-height: 1.45;">
                    <div class="mb-1 text-muted"><strong>User ID:</strong> <?= (int)$user_id ?></div>
                    <div class="mb-1"><strong>Email:</strong> <span class="text-break"><?= htmlspecialchars($email, ENT_QUOTES, 'UTF-8') ?></span></div>
                    <?php if ($phone && $phone !== 'N/A'): ?>
                        <div class="mb-1"><strong>Phone:</strong> <?= htmlspecialchars($phone, ENT_QUOTES, 'UTF-8') ?></div>
                    <?php endif; ?>
                    <?php if ($address || $cityStateZip): ?>
                        <div class="mb-2 text-muted">
                            <strong>Address:</strong><br>
                            <?= htmlspecialchars($address, ENT_QUOTES, 'UTF-8') ?><?= $address && $cityStateZip ? '<br>' : '' ?>
                            <?= htmlspecialchars($cityStateZip, ENT_QUOTES, 'UTF-8') ?>
                        </div>
                    <?php endif; ?>

                    <hr class="my-2 opacity-25">

                    <?= $roleDetailsHtml ?>
                </div>
            </div>
        </div>
        <style>
            <?= getProfileHoverCardStyles() ?>
        </style>
        <?php
        return ob_get_clean();
    }
}

if (!function_exists('getProfileHoverCardStyles')) {
    /**
     * Return CSS rules for user profile hover card component.
     */
    function getProfileHoverCardStyles() {
        return "
            .user-profile-hover-container {
                position: relative;
            }
            .user-profile-hover-trigger {
                cursor: pointer;
                text-decoration: underline dotted;
                text-underline-offset: 3px;
                transition: color 0.15s ease-in-out;
            }
            .user-profile-card-dropdown {
                display: none;
                position: absolute;
                right: 0;
                top: calc(100% + 6px);
                z-index: 1090;
                min-width: 290px;
                max-width: 340px;
                cursor: default;
            }
            .user-profile-hover-container:hover .user-profile-card-dropdown,
            .user-profile-hover-container:focus-within .user-profile-card-dropdown {
                display: block;
                animation: popoverFadeIn 0.15s ease-in-out;
            }
            @keyframes popoverFadeIn {
                from { opacity: 0; transform: translateY(-4px); }
                to { opacity: 1; transform: translateY(0); }
            }
        ";
    }
}

if (!function_exists('renderUserNavbarProfile')) {
    /**
     * Echo modular profile hover card markup directly.
     */
    function renderUserNavbarProfile($conn, $user_id, $user_type = null, $textClass = 'text-white') {
        echo getUserNavbarProfileHtml($conn, $user_id, $user_type, $textClass);
    }
}
