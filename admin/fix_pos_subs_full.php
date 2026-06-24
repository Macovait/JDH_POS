<?php
require_once __DIR__ . '/bootstrap.php';
$pdo = admin_db();

// Fix the invalid default on current_period_end first (blocks ALTER in strict mode)
try {
    $pdo->exec("ALTER TABLE pos_subscriptions MODIFY current_period_end TIMESTAMP NULL DEFAULT NULL");
    echo "Fixed current_period_end default\n";
} catch (PDOException $e) {
    echo "current_period_end fix skipped: " . $e->getMessage() . "\n";
}

// Now add billing_cycle
try {
    $pdo->exec("ALTER TABLE pos_subscriptions ADD COLUMN billing_cycle ENUM('monthly','yearly') DEFAULT 'monthly' AFTER status");
    echo "Added billing_cycle to pos_subscriptions\n";
} catch (PDOException $e) {
    echo "billing_cycle: " . $e->getMessage() . "\n";
}
