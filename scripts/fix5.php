<?php
$files = [
    'billing/sms_settings.php',
    'inventory/automated_inventory.php',
    'marketing/campaigns.php',
    'marketing/campaign_builder.php',
    'payments/stripe_gateway.php'
];

foreach ($files as $f) {
    $p = "c:/xampp/htdocs/JDH_POS/public/$f";
    $c = file_get_contents($p);
    $c = str_replace("<?php <?php\n\$page_content", "<?php\n\$page_content", $c);
    $c = str_replace("}\n\n<?php\n\$page_content", "}\n\n\$page_content", $c);
    file_put_contents($p, $c);
    echo "Fixed $f\n";
}
