<?php
require_once __DIR__ . '/bootstrap.php';
header('Content-Type: text/plain');

$pdo = admin_db();
if (!$pdo) { echo "No DB\n"; exit; }

$hasTable = $pdo->query("SHOW TABLES LIKE 'pos_subscriptions'")->fetchColumn();
if (!$hasTable) { echo "pos_subscriptions does not exist\n"; exit; }

$cols = $pdo->query("SHOW COLUMNS FROM pos_subscriptions")->fetchAll(PDO::FETCH_COLUMN);
echo "Current columns: " . implode(', ', $cols) . "\n";

$missing = [];
if (!in_array('billing_cycle', $cols, true)) $missing[] = "ADD billing_cycle ENUM('monthly','yearly') DEFAULT 'monthly' AFTER status";
if (!in_array('amount', $cols, true)) $missing[] = "ADD amount DECIMAL(12,2) DEFAULT 0.00 AFTER billing_cycle";
if (!in_array('currency', $cols, true)) $missing[] = "ADD currency VARCHAR(10) DEFAULT 'KES' AFTER amount";
if (!in_array('trial_ends_at', $cols, true)) $missing[] = "ADD trial_ends_at DATETIME NULL AFTER currency";
if (!in_array('cancel_reason', $cols, true)) $missing[] = "ADD cancel_reason TEXT NULL AFTER cancelled_at";
if (!in_array('payment_method', $cols, true)) $missing[] = "ADD payment_method VARCHAR(50) NULL AFTER cancel_reason";
if (!in_array('payment_reference', $cols, true)) $missing[] = "ADD payment_reference VARCHAR(200) NULL AFTER payment_method";

if (empty($missing)) {
    echo "All required columns already present.\n";
    exit;
}

echo "\nAdding missing columns:\n";
foreach ($missing as $alter) {
    echo "  {$alter}\n";
    $sql = "ALTER TABLE pos_subscriptions {$alter}";
    try {
        $pdo->exec($sql);
    } catch (PDOException $e) {
        echo "    ERROR: " . $e->getMessage() . "\n";
    }
}

$cols = $pdo->query("SHOW COLUMNS FROM pos_subscriptions")->fetchAll(PDO::FETCH_COLUMN);
echo "\nUpdated columns: " . implode(', ', $cols) . "\n";
echo "Done.\n";
