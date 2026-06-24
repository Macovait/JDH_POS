<?php
/**
 * Run storefront_blocks table migration
 * Visit: http://localhost/JDH_POS/scripts/run_migration_blocks.php
 */
require_once __DIR__ . '/../src/paths.php';
safe_require('db.php', 'src', true);

$pdo = get_db_connection();

try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS storefront_blocks (
        id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        tenant_id       BIGINT UNSIGNED NOT NULL,
        name            VARCHAR(200) NOT NULL COMMENT 'Admin label e.g. Shipping Info',
        content         TEXT NOT NULL COMMENT 'HTML / rich text content',
        bg_color        VARCHAR(20) DEFAULT '#ffffff',
        text_color      VARCHAR(20) DEFAULT '#1f2937',
        padding         VARCHAR(20) DEFAULT 'py-8',
        is_active       TINYINT(1) DEFAULT 1,
        display_order   INT DEFAULT 0,
        created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uk_tenant_order (tenant_id, display_order)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

    echo "<h2 style='font-family:sans-serif;color:green;'>Success!</h2>";
    echo "<p style='font-family:sans-serif;'>Table <code>storefront_blocks</code> created (or already exists).</p>";
    echo "<p style='font-family:sans-serif;'><a href='/JDH_POS/public/dashboard/shop/blocks.php'>Go to Content Blocks admin &rarr;</a></p>";
} catch (Exception $e) {
    echo "<h2 style='font-family:sans-serif;color:red;'>Error</h2>";
    echo "<pre style='font-family:sans-serif;'>" . htmlspecialchars($e->getMessage()) . "</pre>";
}
?>
