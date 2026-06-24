<?php
$files = [
    'c:/xampp/htdocs/JDH_POS/public/billing/sms_settings.php',
    'c:/xampp/htdocs/JDH_POS/public/inventory/automated_inventory.php',
    'c:/xampp/htdocs/JDH_POS/public/marketing/campaigns.php',
    'c:/xampp/htdocs/JDH_POS/public/marketing/campaign_builder.php',
    'c:/xampp/htdocs/JDH_POS/public/payments/stripe_gateway.php',
];
foreach ($files as $f) {
    $out = shell_exec("php -l \"$f\" 2>&1");
    echo basename($f) . ": " . (strpos($out, 'No syntax errors') !== false ? 'OK' : 'FAIL') . "\n";
}
