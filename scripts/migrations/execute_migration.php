<?php
/**
 * Migration Execution Script
 * Executes the SQL migration file
 */

$migrationFile = __DIR__ . '/../../database/migrations/2026_06_15_add_core_missing_tables.sql';

if (!file_exists($migrationFile)) {
    die("Migration file not found: $migrationFile\n");
}

echo "=== Executing Migration ===\n";
echo "File: $migrationFile\n\n";

try {
    $pdo = new PDO('mysql:host=localhost;dbname=jdh_pos;charset=utf8mb4', 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);

    // Read and split SQL file into individual statements
    $sql = file_get_contents($migrationFile);

    // Split by semicolons but preserve those within quotes
    $statements = array_filter(array_map('trim', preg_split('/;[\s]*$/m', $sql)));

    $executed = 0;
    $errors = [];

    foreach ($statements as $statement) {
        if (empty($statement) || strpos($statement, '--') === 0) {
            continue;
        }

        try {
            $pdo->exec($statement);
            $executed++;
            echo ".";
        } catch (PDOException $e) {
            echo "\n[ERROR] " . $e->getMessage() . "\n";
            $errors[] = $e->getMessage();
        }
    }

    echo "\n\n=== Migration Complete ===\n";
    echo "Statements executed: $executed\n";
    if (count($errors) > 0) {
        echo "Errors encountered: " . count($errors) . "\n";
        foreach ($errors as $error) {
            echo "  - $error\n";
        }
    }

} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage() . "\n");
}
