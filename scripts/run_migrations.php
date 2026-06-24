<?php
/**
 * Simple Migration Runner - Execute SQL migrations directly
 */
require_once __DIR__ . '/../src/db.php';

$pdo = get_db_connection();

$migrations = [
    'audit_logs', 'returns', 'return_items', 'credit_sales', 'credit_payments',
    'discounts', 'exchange_rates', 'stock_transfers', 'inventory', 
    'receipt_emails', 'receipt_sms', 'credit_notes'
];

foreach ($migrations as $migration) {
    $file = __DIR__ . "/../database/migrations/{$migration}.sql";
    if (!file_exists($file)) {
        echo "✗ Missing: {$migration}.sql\n";
        continue;
    }
    
    $sql = file_get_contents($file);
    echo "Running: {$migration}... ";
    
    try {
        $pdo->exec($sql);
        echo "✓ Success\n";
    } catch (Exception $e) {
        echo "✗ Failed: " . $e->getMessage() . "\n";
    }
}

echo "\nDone!\n";