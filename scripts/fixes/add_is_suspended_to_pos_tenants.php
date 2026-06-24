<?php
/**
 * Fix: Add missing is_suspended column to pos_tenants
 */
require_once __DIR__ . '/../../src/paths.php';
require_once __DIR__ . '/../../src/db.php';

try {
    $pdo = get_db_connection();

    // Check if column exists
    $cols = $pdo->query("SHOW COLUMNS FROM pos_tenants LIKE 'is_suspended'")->fetchAll(PDO::FETCH_ASSOC);
    if (count($cols) === 0) {
        $pdo->exec("ALTER TABLE pos_tenants ADD COLUMN is_suspended TINYINT(1) NOT NULL DEFAULT 0 AFTER status");
        echo "Added is_suspended column to pos_tenants.\n";
    } else {
        echo "is_suspended column already exists in pos_tenants.\n";
    }

    // Check if pos_tenants has updated_at
    $cols = $pdo->query("SHOW COLUMNS FROM pos_tenants LIKE 'updated_at'")->fetchAll(PDO::FETCH_ASSOC);
    if (count($cols) === 0) {
        $pdo->exec("ALTER TABLE pos_tenants ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP");
        echo "Added updated_at column to pos_tenants.\n";
    }

    // Sync existing suspended tenants
    $pdo->exec("UPDATE pos_tenants SET is_suspended = 1 WHERE status = 'suspended'");
    $count = $pdo->query("SELECT ROW_COUNT()")->fetchColumn();
    echo "Synced {$count} existing suspended tenants.\n";

    echo "Done.\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
