<?php
require_once __DIR__ . '/../src/db.php';

$pdo = get_db_connection();
$pdo->exec('SET FOREIGN_KEY_CHECKS = 0');

$sql = file_get_contents(__DIR__ . '/../database/migrations/remaining_tables.sql');
$statements = array_filter(array_map('trim', preg_split('/;\s*/', $sql)));

$created = 0;
$errors = [];

foreach ($statements as $stmt) {
    if (empty($stmt) || preg_match('/^--|^#|^SET|DELIMITER/i', $stmt)) {
        continue;
    }
    
    $table_name = null;
    if (preg_match('/CREATE TABLE IF NOT EXISTS\s+`?(\w+)`?/i', $stmt, $matches)) {
        $table_name = $matches[1];
    }
    
    try {
        $pdo->exec($stmt);
        $created++;
        if ($table_name) {
            echo "[CREATED] $table_name\n";
        }
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'already exists') === false) {
            $errors[] = $e->getMessage();
            if ($table_name) {
                echo "[ERROR] $table_name: " . substr($e->getMessage(), 0, 60) . "\n";
            }
        } else {
            echo "[EXISTS]  $table_name\n";
        }
    }
}

$pdo->exec('SET FOREIGN_KEY_CHECKS = 1');

echo "\nDone! Processed $created statements.\n";
echo "Errors: " . count($errors) . "\n";
