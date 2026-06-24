<?php
require_once __DIR__ . '/../../src/paths.php';
safe_require('functions.php', 'src', true);

$pdo = get_db_connection();

echo "Migration 026: Add position and used_for_variations to product_attributes\n";

try {
    $cols = [
        'position' => "ALTER TABLE product_attributes ADD COLUMN position INT DEFAULT 0 AFTER visible",
        'used_for_variations' => "ALTER TABLE product_attributes ADD COLUMN used_for_variations TINYINT(1) DEFAULT 0 AFTER position"
    ];
    foreach ($cols as $colName => $sql) {
        $check = $pdo->query("SHOW COLUMNS FROM product_attributes WHERE Field = '{$colName}'")->fetch(PDO::FETCH_ASSOC);
        if (!$check) {
            $pdo->exec($sql);
            echo "   ✓ Added column: {$colName}\n";
        } else {
            echo "   - Column {$colName} already exists, skipping\n";
        }
    }
    echo "Migration 026 completed.\n";
} catch (PDOException $e) {
    error_log("Migration 026 failed: " . $e->getMessage());
    echo "   ✗ Failed: " . $e->getMessage() . "\n";
}
