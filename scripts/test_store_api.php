<?php
require_once __DIR__ . '/../src/paths.php';
load_core_files();
$pdo = get_db_connection();

// Check pos_tenants columns
$cols = $pdo->query("SHOW COLUMNS FROM pos_tenants")->fetchAll(PDO::FETCH_COLUMN);
echo "pos_tenants columns: " . implode(', ', $cols) . "\n\n";

// Check storefront_settings for tenant 1
$rows = $pdo->query("SELECT setting_key, setting_value FROM storefront_settings WHERE tenant_id = 1")->fetchAll(PDO::FETCH_ASSOC);
echo "storefront_settings for tenant 1:\n";
foreach ($rows as $r) {
    echo "  {$r['setting_key']} = {$r['setting_value']}\n";
}

// Test the API URL directly
$url = 'http://localhost/JDH_POS/public/api/v1/store/tenant?tenant=1';
$ctx = stream_context_create(['http' => ['timeout' => 5]]);
$resp = @file_get_contents($url, false, $ctx);
echo "\nAPI /store/tenant?tenant=1 response:\n";
echo ($resp ?: '[NO RESPONSE - check Apache is running]') . "\n";
