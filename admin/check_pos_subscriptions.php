<?php
require_once __DIR__ . '/bootstrap.php';
$pdo = admin_db();
if (!$pdo) { echo "No DB\n"; exit; }
$hasTable = $pdo->query("SHOW TABLES LIKE 'pos_subscriptions'")->fetchColumn();
if (!$hasTable) { echo "pos_subscriptions does not exist\n"; exit; }
$cols = $pdo->query("SHOW COLUMNS FROM pos_subscriptions")->fetchAll(PDO::FETCH_COLUMN);
echo in_array('billing_cycle', $cols) ? "HAS billing_cycle\n" : "MISSING billing_cycle\n";
echo "Columns: " . implode(', ', $cols) . "\n";
