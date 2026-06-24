<?php
/**
 * Header Service — cached header data fetching (procedural, matches codebase style)
 * Reduces DB queries by caching header-specific data in session.
 */

if (!function_exists('header_get_notif_count')) {
    function header_get_notif_count(PDO $db, int $userId, int $cacheTtl = 300): int
    {
        $cacheKey = 'header_notif_count_' . $userId;
        $expiresKey = $cacheKey . '_expires';

        if (!empty($_SESSION[$cacheKey]) && !empty($_SESSION[$expiresKey]) && $_SESSION[$expiresKey] > time()) {
            return (int) $_SESSION[$cacheKey];
        }

        $count = 0;
        try {
            $stmt = $db->prepare(
                "SELECT COUNT(*) FROM notifications WHERE user_id = ? AND read_at IS NULL AND deleted_at IS NULL"
            );
            $stmt->execute([$userId]);
            $count = (int) $stmt->fetchColumn();
        } catch (Exception $e) {
            $count = (int) ($_SESSION[$cacheKey] ?? 0);
        }

        $_SESSION[$cacheKey] = $count;
        $_SESSION[$expiresKey] = time() + $cacheTtl;
        return $count;
    }
}

if (!function_exists('header_clear_notif_cache')) {
    function header_clear_notif_cache(int $userId): void
    {
        $keys = [
            'header_notif_count_' . $userId,
            'header_notif_count_' . $userId . '_expires',
            'header_notif_recent_' . $userId,
            'header_notif_recent_' . $userId . '_expires',
        ];
        foreach ($keys as $key) {
            unset($_SESSION[$key]);
        }
    }
}
