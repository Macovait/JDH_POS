<?php
/**
 * Apply Performance Indexes
 */

require_once __DIR__ . '/../src/db.php';

$pdo = get_db_connection();
$pdo->exec('SET FOREIGN_KEY_CHECKS = 0');

$sql = file_get_contents(__DIR__ . '/../database/migrations/2026_06_15_performance_indexes.sql');
$statements = array_filter(array_map('trim', preg_split('/;\s*/', $sql)));

$applied = 0;
$skipped = 0;
$errors = [];

foreach ($statements as $statement) {
    if (empty($statement) || preg_match('/^--|^#|^SET/i', $statement)) {
        continue;
    }
    
    try {
        $pdo->exec($statement);
        $applied++;
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate') !== false || strpos($e->getMessage(), '1061') !== false) {
            $skipped++;
        } else {
            $errors[] = $e->getMessage();
        }
    }
}

$pdo->exec('SET FOREIGN_KEY_CHECKS = 1');

echo "=== INDEXES APPLIED ===\n";
echo "Applied: $applied\n";
echo "Skipped (already exist): $skipped\n";
echo "Errors: " . count($errors) . "\n";

if (count($errors) > 0) {
    echo "\nErrors encountered:\n";
    foreach (array_slice($errors, 0, 10) as $err) {
        echo "  - " . substr($err, 0, 80) . "\n";
    }
}

echo "\n=== COMPLETE ===\n";
