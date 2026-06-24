<?php
$files = [
    "c:/xampp/htdocs/JDH_POS/public/billing/mpesa_settings.php",
    "c:/xampp/htdocs/JDH_POS/public/billing/payment_history.php",
    "c:/xampp/htdocs/JDH_POS/public/billing/sms_settings.php",
    "c:/xampp/htdocs/JDH_POS/public/branches/branches.php",
    "c:/xampp/htdocs/JDH_POS/public/categories/add_category.php",
    "c:/xampp/htdocs/JDH_POS/public/categories/category_hierarchy.php",
    "c:/xampp/htdocs/JDH_POS/public/categories/edit_category.php",
    "c:/xampp/htdocs/JDH_POS/public/categories/list_categories.php",
    "c:/xampp/htdocs/JDH_POS/public/crm/deals.php",
    "c:/xampp/htdocs/JDH_POS/public/crm/index.php",
    "c:/xampp/htdocs/JDH_POS/public/crm/leads.php",
    "c:/xampp/htdocs/JDH_POS/public/expenses/add_expense.php",
    "c:/xampp/htdocs/JDH_POS/public/expenses/expense_categories.php",
    "c:/xampp/htdocs/JDH_POS/public/expenses/expenses.php",
    "c:/xampp/htdocs/JDH_POS/public/hr/employees.php",
    "c:/xampp/htdocs/JDH_POS/public/hr/index.php",
    "c:/xampp/htdocs/JDH_POS/public/hr/leave_requests.php",
    "c:/xampp/htdocs/JDH_POS/public/hr/hr/employees.php",
    "c:/xampp/htdocs/JDH_POS/public/inventory/automated_inventory.php",
    "c:/xampp/htdocs/JDH_POS/public/inventory/inventory.php",
    "c:/xampp/htdocs/JDH_POS/public/inventory/inventory_report.php",
    "c:/xampp/htdocs/JDH_POS/public/inventory/item_expiry.php",
    "c:/xampp/htdocs/JDH_POS/public/inventory/suppliers.php",
    "c:/xampp/htdocs/JDH_POS/public/inventory/variants.php",
    "c:/xampp/htdocs/JDH_POS/public/inventory/products/import_products.php",
    "c:/xampp/htdocs/JDH_POS/public/inventory/products/products.php",
    "c:/xampp/htdocs/JDH_POS/public/inventory/products/product_form.php",
    "c:/xampp/htdocs/JDH_POS/public/inventory/tax/tax_rates.php",
    "c:/xampp/htdocs/JDH_POS/public/marketing/campaigns.php",
    "c:/xampp/htdocs/JDH_POS/public/marketing/campaign_builder.php",
    "c:/xampp/htdocs/JDH_POS/public/payments/stripe_gateway.php",
    "c:/xampp/htdocs/JDH_POS/public/products/attributes.php",
    "c:/xampp/htdocs/JDH_POS/public/purchases/list_draft.php",
    "c:/xampp/htdocs/JDH_POS/public/quotations/add_quotation.php",
    "c:/xampp/htdocs/JDH_POS/public/quotations/list_quotation.php",
    "c:/xampp/htdocs/JDH_POS/public/reports/profit_loss.php",
    "c:/xampp/htdocs/JDH_POS/public/reports/sales_report.php",
];

$converted = [];
$skipped = [];

foreach ($files as $file) {
    if (!file_exists($file)) {
        $skipped[] = basename($file) . " (not found)";
        continue;
    }
    $content = file_get_contents($file);
    if (strpos($content, 'ob_get_clean()') !== false) {
        $skipped[] = basename($file) . " (already converted)";
        continue;
    }

    // Determine relative depth from public/
    $relative = substr($file, strlen('c:/xampp/htdocs/JDH_POS/public/'));
    $depth = substr_count($relative, '/');
    $pp = str_repeat('../', $depth);

    $appPath = $pp . "layouts/app.php";
    $closePath = $pp . "layouts/app_close.php";

    // Try various patterns for app.php include
    $patterns = [
        "include __DIR__ . '/" . $appPath . "';",
        "include_once __DIR__ . '/" . $appPath . "';",
        "require __DIR__ . '/" . $appPath . "';",
        "require_once __DIR__ . '/" . $appPath . "';",
        "include '/" . $appPath . "';",
        "include_once '/" . $appPath . "';",
        "include '" . $appPath . "';",
        "include_once '" . $appPath . "';",
    ];

    $replaced = false;
    foreach ($patterns as $pat) {
        if (strpos($content, $pat) !== false) {
            $content = str_replace($pat, "ob_start();", $content);
            $replaced = true;
            break;
        }
    }

    if (!$replaced) {
        $skipped[] = basename($file) . " (app pattern not found)";
        continue;
    }

    // Try various patterns for app_close.php include
    $closePatterns = [
        "<?php include_once __DIR__ . '/" . $closePath . "'; ?>",
        "<?php include __DIR__ . '/" . $closePath . "'; ?>",
        "<?php require_once __DIR__ . '/" . $closePath . "'; ?>",
        "<?php require __DIR__ . '/" . $closePath . "'; ?>",
        "include_once __DIR__ . '/" . $closePath . "';",
        "include __DIR__ . '/" . $closePath . "';",
        "require_once __DIR__ . '/" . $closePath . "';",
        "require __DIR__ . '/" . $closePath . "';",
    ];

    $closeReplacement = '<?php' . "\n" .
        '$page_content = ob_get_clean();' . "\n" .
        "require_once __DIR__ . '/" . $closePath . "';" . "\n" .
        "require_once __DIR__ . '/" . $appPath . "';" . "\n" .
        '?>';

    $closeReplaced = false;
    foreach ($closePatterns as $pat) {
        if (strpos($content, $pat) !== false) {
            $content = str_replace($pat, $closeReplacement, $content);
            $closeReplaced = true;
            break;
        }
    }

    if (!$closeReplaced) {
        $skipped[] = basename($file) . " (close pattern not found)";
        continue;
    }

    file_put_contents($file, $content);
    $converted[] = basename($file);
}

echo "Converted: " . count($converted) . "\n";
foreach ($converted as $f) echo "  - $f\n";
echo "\nSkipped: " . count($skipped) . "\n";
foreach ($skipped as $f) echo "  - $f\n";
