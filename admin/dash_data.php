<?php
require_once __DIR__ . '/bootstrap.php';
$pdo = admin_db();

header('Content-Type: text/plain');

// Check what tables actually have data
$tables = ['pos_tenants','tenants','pos_subscriptions','subscriptions','pos_invoices','invoices','users','branches','sales'];
foreach ($tables as $t) {
    $has = $pdo->query("SHOW TABLES LIKE '{$t}'")->fetchColumn();
    if ($has) {
        $cnt = $pdo->query("SELECT COUNT(*) FROM {$t}")->fetchColumn();
        echo "{$t}: {$cnt} rows\n";
    } else {
        echo "{$t}: MISSING\n";
    }
}

echo "\n--- pos_subscriptions statuses ---\n";
$rows = $pdo->query("SELECT status, COUNT(*) as c FROM pos_subscriptions GROUP BY status")->fetchAll(PDO::FETCH_ASSOC);
foreach ($rows as $r) echo "  {$r['status']}: {$r['c']}\n";

echo "\n--- pos_invoices statuses ---\n";
$rows = $pdo->query("SELECT status, COUNT(*) as c FROM pos_invoices GROUP BY status")->fetchAll(PDO::FETCH_ASSOC);
foreach ($rows as $r) echo "  {$r['status']}: {$r['c']}\n";
