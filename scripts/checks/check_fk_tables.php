<?php
/**
 * Check Foreign Key Table References
 */

$pdo = new PDO('mysql:host=localhost;dbname=jdh_pos;charset=utf8mb4', 'root', '');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$tables_to_check = [
    'cash_drawers', 'products', 'suppliers', 'units', 'purchases', 'customers',
    'invoices', 'subscriptions', 'users', 'shops', 'company', 'companies'
];

echo "=== Checking Table Existence and ID Types ===\n\n";

foreach ($tables_to_check as $table) {
    try {
        $stmt = $pdo->query("SHOW TABLES LIKE '$table'");
        $exists = $stmt->fetchColumn();

        if ($exists) {
            echo "[$table] EXISTS\n";

            // Check ID column type
            $stmt = $pdo->query("SHOW COLUMNS FROM `$table` WHERE Field = 'id'");
            $col = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($col) {
                echo "  -> id column: {$col['Type']}\n";
            }

            // Check other relevant columns
            $stmt = $pdo->query("SHOW COLUMNS FROM `$table`");
            $cols = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $col_names = array_column($cols, 'Field');
            echo "  -> Columns: " . implode(', ', array_slice($col_names, 0, 5)) . "...\n";
        } else {
            echo "[$table] DOES NOT EXIST\n";
        }
        echo "\n";
    } catch (Exception $e) {
        echo "[$table] ERROR: {$e->getMessage()}\n\n";
    }
}
