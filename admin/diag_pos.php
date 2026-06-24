<?php
require_once __DIR__ . '/bootstrap.php';
$pdo = admin_db();
$cols = $pdo->query("SHOW COLUMNS FROM pos_subscriptions")->fetchAll(PDO::FETCH_ASSOC);
$names = array_column($cols, 'Field');
echo "pos_subscriptions columns:\n";
foreach ($names as $n) echo "  {$n}\n";
if (in_array('billing_cycle', $names)) { echo "billing_cycle EXISTS\n"; exit; }
try {
    $pdo->exec("ALTER TABLE pos_subscriptions ADD COLUMN billing_cycle ENUM('monthly','yearly') DEFAULT 'monthly' AFTER status");
    echo "billing_cycle ADDED\n";
} catch (PDOException $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
