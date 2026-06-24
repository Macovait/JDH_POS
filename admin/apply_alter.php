<?php
require_once __DIR__ . '/bootstrap.php';
header('Content-Type: text/plain');

$pdo = admin_db();
if (!$pdo) { echo "No DB connection.\n"; exit(1); }

// Check if subscriptions table exists
$hasTable = $pdo->query("SHOW TABLES LIKE 'subscriptions'")->fetchColumn();
if (!$hasTable) {
    echo "Table 'subscriptions' does not exist.\n";
    exit(1);
}

// Check if billing_cycle already exists
$cols = $pdo->query("SHOW COLUMNS FROM subscriptions")->fetchAll(PDO::FETCH_COLUMN);
if (in_array('billing_cycle', $cols, true)) {
    echo "Column 'billing_cycle' already exists in 'subscriptions'.\n";
    exit(0);
}

// Apply the ALTER
$sql = "ALTER TABLE subscriptions ADD COLUMN billing_cycle ENUM('daily','weekly','monthly','quarterly','yearly','lifetime') DEFAULT 'monthly'";
$pdo->exec($sql);

echo "Column 'billing_cycle' added to 'subscriptions'.\n";
