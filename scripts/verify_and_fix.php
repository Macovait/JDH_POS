<?php
$converted = [
    'billing/mpesa_settings.php','billing/payment_history.php','billing/sms_settings.php',
    'branches/branches.php','categories/add_category.php','categories/category_hierarchy.php',
    'categories/edit_category.php','categories/list_categories.php','crm/deals.php',
    'crm/index.php','crm/leads.php','expenses/add_expense.php','expenses/expense_categories.php',
    'expenses/expenses.php','hr/employees.php','hr/index.php','hr/leave_requests.php',
    'hr/hr/employees.php','inventory/automated_inventory.php','inventory/inventory.php',
    'inventory/inventory_report.php','inventory/item_expiry.php','inventory/suppliers.php',
    'inventory/variants.php','inventory/products/import_products.php','inventory/products/products.php',
    'inventory/products/product_form.php','inventory/tax/tax_rates.php','marketing/campaigns.php',
    'marketing/campaign_builder.php','payments/stripe_gateway.php','products/attributes.php',
    'purchases/list_draft.php','quotations/add_quotation.php','quotations/list_quotation.php',
    'reports/profit_loss.php','reports/sales_report.php'
];

$errors = 0;
foreach ($converted as $f) {
    $full = "c:/xampp/htdocs/JDH_POS/public/$f";
    $out = shell_exec("php -l \"$full\" 2>&1");
    if (strpos($out, 'No syntax errors') === false) {
        echo "ERROR: $f\n$out\n---\n";
        $errors++;
    }
}
echo "\nTotal errors: $errors\n";
