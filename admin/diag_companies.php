<?php
require_once __DIR__ . '/bootstrap.php';
$pdo = admin_db();

$tables = $pdo->query("SHOW TABLES LIKE '%tenant%'")->fetchAll(PDO::FETCH_COLUMN);
echo "Tenant-related tables:\n";
foreach ($tables as $t) {
    $count = $pdo->query("SELECT COUNT(*) FROM {$t}")->fetchColumn();
    echo "  {$t}: {$count} rows\n";
}

echo "\npos_tenants sample:\n";
$rows = $pdo->query("SELECT id, name, status, created_at FROM pos_tenants LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
foreach ($rows as $r) {
    echo "  id={$r['id']} name={$r['name']} status={$r['status']} created={$r['created_at']}\n";
}
if (empty($rows)) echo "  (empty)\n";
