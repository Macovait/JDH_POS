<?php
/**
 * Landing Blocks Helper
 *
 * Loads editable landing page content from pos_landing_blocks table.
 * Used by the public landing page to render sections dynamically.
 */

/**
 * Get all active blocks for a given section, ordered by sort_order.
 *
 * @param string $section Section name (e.g., 'trust_strip', 'testimonials', 'faq')
 * @return array
 */
function get_landing_blocks(string $section): array
{
    static $cache = [];
    if (isset($cache[$section])) {
        return $cache[$section];
    }

    try {
        $pdo = get_db_connection();
        $prefix = defined('DB_TABLE_PREFIX') ? DB_TABLE_PREFIX : 'pos_';
        $stmt = $pdo->prepare("SELECT * FROM {$prefix}landing_blocks WHERE section = ? AND is_active = 1 ORDER BY sort_order ASC, id ASC");
        $stmt->execute([$section]);
        $cache[$section] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("get_landing_blocks error: " . $e->getMessage());
        $cache[$section] = [];
    }

    return $cache[$section];
}

/**
 * Get a single landing block by section and key.
 *
 * @param string $section
 * @param string $blockKey
 * @return array|null
 */
function get_landing_block(string $section, string $blockKey): ?array
{
    $blocks = get_landing_blocks($section);
    foreach ($blocks as $block) {
        if ($block['block_key'] === $blockKey) {
            return $block;
        }
    }
    return null;
}

/**
 * Get all available landing sections (distinct section names).
 *
 * @return array
 */
function get_landing_sections(): array
{
    try {
        $pdo = get_db_connection();
        $prefix = defined('DB_TABLE_PREFIX') ? DB_TABLE_PREFIX : 'pos_';
        $stmt = $pdo->query("SELECT DISTINCT section FROM {$prefix}landing_blocks ORDER BY section");
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    } catch (Exception $e) {
        error_log("get_landing_sections error: " . $e->getMessage());
        return [];
    }
}
