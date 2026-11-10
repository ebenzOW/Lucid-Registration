<?php
/**
 * Lucid University Course Registration System
 * User Profile Link Component (Direct Link Architecture)
 *
 * Renders the logged-in user's name in headers and navbars with a clean hyperlink
 * pointing to the standalone user profile page (user_profile.php).
 */

function getUserNavbarProfileHtml($conn, $user_id, $user_type = null, $link_classes = 'text-white') {
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

function getProfileHoverCardStyles() {
    return ''; // No-op to preserve backwards compatibility across existing view includes
}

