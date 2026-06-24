<?php
/**
 * Notifications functions for JDH POS
 */

if (!function_exists('get_unread_notifications')) {
    function get_unread_notifications($user_id = null)
    {
        global $pdo;

        if ($user_id === null && function_exists('get_current_user_id')) {
            $user_id = get_current_user_id();
        }

        if (!$user_id || !$pdo) {
            return 0;
        }

        try {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND read_at IS NULL");
            $stmt->execute([$user_id]);
            return (int) $stmt->fetchColumn();
        } catch (PDOException $e) {
            error_log("Error getting unread notifications: " . $e->getMessage());
            return 0;
        }
    }
}

if (!function_exists('get_notifications')) {
    function get_notifications($user_id = null, $limit = 10)
    {
        global $pdo;

        if ($user_id === null && function_exists('get_current_user_id')) {
            $user_id = get_current_user_id();
        }

        if (!$user_id || !$pdo) {
            return [];
        }

        try {
            $stmt = $pdo->prepare("
                SELECT * FROM notifications 
                WHERE user_id = ? 
                ORDER BY created_at DESC 
                LIMIT ?
            ");
            $stmt->execute([$user_id, $limit]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error getting notifications: " . $e->getMessage());
            return [];
        }
    }
}

if (!function_exists('mark_notification_read')) {
    function mark_notification_read($notification_id, $user_id = null)
    {
        global $pdo;

        if ($user_id === null && function_exists('get_current_user_id')) {
            $user_id = get_current_user_id();
        }

        if (!$user_id || !$pdo) {
            return false;
        }

        try {
            $stmt = $pdo->prepare("
                UPDATE notifications 
                SET read_at = NOW() 
                WHERE id = ? AND user_id = ?
            ");
            return $stmt->execute([$notification_id, $user_id]);
        } catch (PDOException $e) {
            error_log("Error marking notification read: " . $e->getMessage());
            return false;
        }
    }
}