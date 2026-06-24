<?php
require_once __DIR__ . '/bootstrap.php';
$pdo = admin_db();
$cols = $pdo->query("SHOW COLUMNS FROM pos_subscriptions")->fetchAll(PDO::FETCH_COLUMN);
if (!in_array('billing_cycle', $cols)) {
    $pdo->exec("ALTER TABLE pos_subscriptions ADD COLUMN billing_cycle ENUM('monthly','yearly') DEFAULT 'monthly' AFTER status");
    echo "Added billing_cycle to pos_subscriptions.";
} else { echo "Already exists."; }
