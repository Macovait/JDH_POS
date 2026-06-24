<?php
require_once __DIR__ . '/bootstrap.php';
$pdo = admin_db();

$posCount = (int) $pdo->query("SELECT COUNT(*) FROM pos_tenants")->fetchColumn();
if ($posCount > 0) {
    echo "pos_tenants already has {$posCount} rows. Nothing to do.\n";
    exit;
}

$tenants = $pdo->query("SELECT * FROM tenants LIMIT 100")->fetchAll(PDO::FETCH_ASSOC);
if (empty($tenants)) {
    echo "No tenants found in 'tenants' table either.\n";
    exit;
}

$inserted = 0;
foreach ($tenants as $t) {
    $pdo->prepare("INSERT INTO pos_tenants
        (uuid, subdomain, domain, name, slug, status, plan_id, parent_tenant_id, settings, branding, created_at, updated_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ")->execute([
        $t['uuid'] ?? bin2hex(random_bytes(16)),
        $t['subdomain'] ?? null,
        $t['domain'] ?? null,
        $t['name'] ?? 'Tenant',
        $t['slug'] ?? ($t['subdomain'] ?? null),
        $t['status'] ?? 'trial',
        $t['plan_id'] ?? 1,
        $t['parent_tenant_id'] ?? null,
        $t['settings'] ?? null,
        $t['branding'] ?? null,
        $t['created_at'] ?? date('Y-m-d H:i:s'),
        $t['updated_at'] ?? date('Y-m-d H:i:s'),
    ]);
    $inserted++;
}

echo "Migrated {$inserted} tenant(s) into pos_tenants.\n";
