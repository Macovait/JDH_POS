<?php
require_once __DIR__ . '/bootstrap.php';
$pdo = admin_db();
$cols = $pdo->query("SHOW COLUMNS FROM pos_subscriptions")->fetchAll(PDO::FETCH_COLUMN);
$missing = [];
if (!in_array('amount', $cols)) $missing[] = "ADD amount DECIMAL(12,2) DEFAULT 0.00 AFTER billing_cycle";
if (!in_array('currency', $cols)) $missing[] = "ADD currency VARCHAR(10) DEFAULT 'KES' AFTER amount";
if (!in_array('trial_ends_at', $cols)) $missing[] = "ADD trial_ends_at DATETIME NULL AFTER currency";
if (!in_array('cancel_reason', $cols)) $missing[] = "ADD cancel_reason TEXT NULL AFTER cancelled_at";
if (!in_array('payment_method', $cols)) $missing[] = "ADD payment_method VARCHAR(50) NULL AFTER cancel_reason";
if (!in_array('payment_reference', $cols)) $missing[] = "ADD payment_reference VARCHAR(200) NULL AFTER payment_method";
foreach ($missing as $alter) {
    try { $pdo->exec("ALTER TABLE pos_subscriptions {$alter}"); echo "OK: {$alter}\n"; }
    catch (PDOException $e) { echo "ERR: {$e->getMessage()}\n"; }
}
if (!$missing) echo "All columns present.\n";
