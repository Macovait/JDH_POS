<?php
/**
 * Run POS Performance Indexes SQL
 */

require_once __DIR__ . '/../src/paths.php';
safe_require('db.php', 'src', true);

try {
    $pdo = get_db_connection();

    if (!$pdo) {
        throw new Exception('Database connection failed');
    }

    echo "Adding POS performance indexes...\n";

    $sql = file_get_contents(__DIR__ . '/../sql/pos_performance_indexes.sql');

    // Split into individual statements
    $statements = array_filter(array_map('trim', explode(';', $sql)));

    foreach ($statements as $statement) {
        if (!empty($statement)) {
            echo "Executing: " . substr($statement, 0, 50) . "...\n";
            $pdo->exec($statement);
        }
    }

    echo "POS performance indexes added successfully!\n";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
?>