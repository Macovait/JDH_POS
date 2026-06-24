<?php
require_once __DIR__ . '/../../src/paths.php';
safe_require('functions.php', 'src', true);

$pdo = get_db_connection();

echo "Migration 025: Add visible column to product_attributes\n";

try {
    $check = $pdo->query("SHOW COLUMNS FROM product_attributes WHERE Field = 'visible'")->fetch(PDO::FETCH_ASSOC);
    if (!$check) {
        $pdo->exec("ALTER TABLE product_attributes ADD COLUMN visible TINYINT(1) DEFAULT 1 AFTER attribute_value");
        echo "   ✓ Added column: visible\n";
    } else {
        echo "   - Column visible already exists, skipping\n";
    }
    echo "Migration 025 completed.\n";
} catch (PDOException $e) {
    error_log("Migration 025 failed: " . $e->getMessage());
    echo "   ✗ Failed: " . $e->getMessage() . "\n";
}
