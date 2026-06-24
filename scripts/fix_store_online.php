<?php
require_once __DIR__ . '/../src/paths.php';
load_core_files();
$pdo = get_db_connection();

$settings = [
    'online_store_enabled' => '1',
    'store_name'           => 'JAKPOS Store',
    'currency'             => 'KES',
    'primary_color'        => '#f68b1e',
];

foreach ($settings as $key => $value) {
    $pdo->prepare(
        "INSERT INTO storefront_settings (tenant_id, setting_key, setting_value)
         VALUES (1, ?, ?)
         ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)"
    )->execute([$key, $value]);
    echo "Set {$key} = {$value}\n";
}
echo "Done.\n";
