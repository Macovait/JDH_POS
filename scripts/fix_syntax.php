<?php
$fixes = [
    'billing/sms_settings.php' => ["<?php <?php\n\$page_content", "<?php\n\$page_content"],
    'inventory/automated_inventory.php' => ["}\n\n<?php\n\$page_content", "}\n\n\$page_content"],
    'marketing/campaigns.php' => ["}\n\n<?php\n\$page_content", "}\n\n\$page_content"],
    'marketing/campaign_builder.php' => ["}\n\n<?php\n\$page_content", "}\n\n\$page_content"],
    'payments/stripe_gateway.php' => ["}\n\n<?php\n\$page_content", "}\n\n\$page_content"],
];

foreach ($fixes as $file => $patterns) {
    $path = "c:/xampp/htdocs/JDH_POS/public/$file";
    if (!file_exists($path)) continue;
    $content = file_get_contents($path);
    $content = str_replace($patterns[0], $patterns[1], $content);
    file_put_contents($path, $content);
    echo "Fixed: $file\n";
}
