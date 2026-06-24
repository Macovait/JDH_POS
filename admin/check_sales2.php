<?php
require_once __DIR__ . '/bootstrap.php';
$pdo = admin_db();
$cols = $pdo->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'sales'")->fetchAll(PDO::FETCH_COLUMN);
echo implode(', ', $cols) . "\n";
echo "Has total_amount: " . (in_array('total_amount', $cols) ? 'YES' : 'NO') . "\n";
echo "Has amount: " . (in_array('amount', $cols) ? 'YES' : 'NO') . "\n";
echo "Has grand_total: " . (in_array('grand_total', $cols) ? 'YES' : 'NO') . "\n";
echo "Has total: " . (in_array('total', $cols) ? 'YES' : 'NO') . "\n";
