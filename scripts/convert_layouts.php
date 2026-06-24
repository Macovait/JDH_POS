<?php
/**
 * Batch convert old layout includes to ob_start() / ob_get_clean() pattern
 */

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
        $skipped[] = "$file (not found)";
        continue;
    }

    $content = file_get_contents($file);

    // Skip if already converted
    if (strpos($content, 'ob_get_clean()') !== false) {
        $skipped[] = "$file (already has ob_get_clean)";
        continue;
    }

    // Determine path depth from public/
    $relative = substr($file, strlen('c:/xampp/htdocs/JDH_POS/public/'));
    $depth = substr_count($relative, '/');
    $pathPrefix = str_repeat('../', $depth);

    // Replace layout/app.php include with ob_start();
    // Match patterns like: include __DIR__ . '/../layouts/app.php'; or include_once __DIR__ . '/../../layouts/app.php';
    $appPattern = '/^(\s*)(include|require)(_once)?\s+(__DIR__\s*\.\s*)?\'' . preg_quote($pathPrefix, '/') . 'layouts\/app\.php\'\s*;\s*\n/m';
    $content = preg_replace($appPattern, "${1}ob_start();\n", $content);

    // Check if replacement happened
    if (strpos($content, 'ob_start()') === false) {
        // Try without __DIR__ prefix
        $appPattern2 = '/^(\s*)(include|require)(_once)?\s+\'' . preg_quote($pathPrefix, '/') . 'layouts\/app\.php\'\s*;\s*\n/m';
        $content = preg_replace($appPattern2, "${1}ob_start();\n", $content);
    }

    // Replace layout/app_close.php include with ob_get_clean + layout includes
    $closePattern = '/^(\s*)(include|require)(_once)?\s+(__DIR__\s*\.\s*)?\'' . preg_quote($pathPrefix, '/') . 'layouts\/app_close\.php\'\s*;\s*\n/m';
    $replacement = "${1}\$page_content = ob_get_clean();\n${1}require_once __DIR__ . '/" . $pathPrefix . "layouts/app.php';\n${1}require_once __DIR__ . '/" . $pathPrefix . "layouts/app_close.php';\n";
    $content = preg_replace($closePattern, $replacement, $content);

    if (strpos($content, 'ob_start()') !== false && strpos($content, 'ob_get_clean()') !== false) {
        file_put_contents($file, $content);
        $converted[] = $file;
    } else {
        $skipped[] = "$file (pattern not matched)";
    }
}

echo "=== Converted (" . count($converted) . ") ===\n";
foreach ($converted as $f) echo basename($f) . "\n";

echo "\n=== Skipped (" . count($skipped) . ") ===\n";
foreach ($skipped as $f) echo basename($f) . "\n";
