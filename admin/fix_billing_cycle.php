<?php
require_once __DIR__ . '/bootstrap.php';
header('Content-Type: text/plain');

$pdo = admin_db();
if (!$pdo) { echo "No DB connection.\n"; exit; }

// Check if subscription_plans exists and is missing billing_cycle
$tables = $pdo->query("SHOW TABLES LIKE 'subscription_plans'")->fetchAll(PDO::FETCH_COLUMN);
if (empty($tables)) {
    echo "Table 'subscription_plans' does not exist.\n";
    exit;
}

$cols = $pdo->query("SHOW COLUMNS FROM subscription_plans")->fetchAll(PDO::FETCH_COLUMN);
if (in_array('billing_cycle', $cols, true)) {
    echo "Column 'billing_cycle' already exists in subscription_plans.\n";
    exit;
}

echo "Adding 'billing_cycle' column to subscription_plans...\n";
$pdo->exec("ALTER TABLE subscription_plans ADD COLUMN billing_cycle ENUM('monthly','yearly') NOT NULL DEFAULT 'monthly' AFTER slug");
echo "Done. Column added successfully.\n";

// Also check if there's data trying to insert into this table that needs the column
$cols = $pdo->query("SHOW COLUMNS FROM subscription_plans")->fetchAll(PDO::FETCH_COLUMN);
echo "Current columns: " . implode(', ', $cols) . "\n";
