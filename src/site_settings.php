<?php
/**
 * Site Settings Helper
 * 
 * Loads platform-wide settings from system_settings table.
 * Used by the public landing page and other public-facing pages.
 * Settings are cached per-request to avoid multiple DB queries.
 */

/**
 * Get all site settings as key => value array.
 * Loads from system_settings where tenant_id IS NULL (platform-wide).
 *
 * @return array
 */
function get_site_settings(): array {
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }

    $cache = [];
    try {
        $pdo = get_db_connection();
        $stmt = $pdo->query("SELECT setting_key, setting_value FROM system_settings WHERE (tenant_id IS NULL OR tenant_id = 0) ORDER BY sort_order");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $cache[$row['setting_key']] = $row['setting_value'];
        }
    } catch (Exception $e) {
        error_log("get_site_settings error: " . $e->getMessage());
    }

    return $cache;
}

/**
 * Get a single site setting value.
 *
 * @param string $key Setting key
 * @param string $default Default value if not found
 * @return string
 */
function get_site_setting(string $key, string $default = ''): string {
    $settings = get_site_settings();
    return $settings[$key] ?? $default;
}

/**
 * Get active plans for public display (pricing page / landing page).
 * Returns only active plans ordered by sort_order and price.
 * Uses raw PDO to bypass tenant auto-scoping (plans are platform-wide).
 *
 * @return array
 */
function get_public_plans(): array {
    try {
        $pdo = get_db_connection();
        $stmt = $pdo->prepare("SELECT * FROM pos_plans WHERE is_active = 1 ORDER BY sort_order ASC, price ASC");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("get_public_plans error: " . $e->getMessage());
        return [];
    }
}
