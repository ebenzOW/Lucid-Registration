<?php
/**
 * Lucid University - Administrator & User Audit Logging Helpers
 */

/**
 * Record a USER table or user-account-level mutation in USER_UPDATE_LOG.
 */
function logUserUpdate(mysqli $conn, int $admin_id, int $target_user_id, string $changed_attribute): void
{
    $attrClean = substr(trim($changed_attribute), 0, 50);
    $stmt = $conn->prepare("
        INSERT INTO USER_UPDATE_LOG (Admin_ID, User_ID, Changed_Attribute, Date_Of_Change)
        VALUES (?, ?, ?, CURRENT_TIMESTAMP)
    ");
    $stmt->bind_param("iis", $admin_id, $target_user_id, $attrClean);
    if (!$stmt->execute()) {
        $err = $stmt->error;
        $stmt->close();
        throw new Exception("Failed to write USER_UPDATE_LOG entry: " . $err);
    }
    $stmt->close();
}

/**
 * Record any administrator write action in ADMIN_ACTIVITY_LOG.
 */
function logAdminActivity(
    mysqli $conn,
    int $admin_id,
    string $target_entity,
    string|int $target_id,
    string $action_type,
    string $details
): void {
    $entityClean  = substr(trim($target_entity), 0, 40);
    $targetClean  = substr(trim((string)$target_id), 0, 50);
    $actionClean  = substr(trim($action_type), 0, 40);
    $detailsClean = substr(trim($details), 0, 255);

    $stmt = $conn->prepare("
        INSERT INTO ADMIN_ACTIVITY_LOG (Admin_ID, Target_Entity, Target_ID, Action_Type, Details, Created_At)
        VALUES (?, ?, ?, ?, ?, CURRENT_TIMESTAMP)
    ");
    $stmt->bind_param("issss", $admin_id, $entityClean, $targetClean, $actionClean, $detailsClean);
    if (!$stmt->execute()) {
        $err = $stmt->error;
        $stmt->close();
        throw new Exception("Failed to write ADMIN_ACTIVITY_LOG entry: " . $err);
    }
    $stmt->close();
}
