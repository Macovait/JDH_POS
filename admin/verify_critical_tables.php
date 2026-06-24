<?php
/**
 * Verify critical tables match between code and database
 */

require_once __DIR__ . '/../src/db.php';
$pdo = get_db_connection();

// Critical tables actually used in JDH POS (based on code grep results)
$critical_tables = [
    // Core
    'users', 'roles', 'permissions', 'role_permissions', 'user_roles',
    'companies', 'branches', 'company_subscriptions', 'subscription_plans',
    
    // Products
    'products', 'categories', 'brands', 'product_categories', 'product_tags',
    'attributes', 'attribute_values', 'product_attributes', 'attribute_groups',
    
    // Inventory
    'inventory', 'stock_alerts', 'stock_movements', 'stock_transfers', 'stock_batches',
    'inventory_adjustments', 'inventory_counts', 'units', 'unit_conversions',
    
    // Customers
    'customers', 'customer_groups', 'customer_loyalty', 'loyalty_rules',
    'customer_labels', 'loyalty_transactions', 'loyalty_tiers',
    
    // Sales
    'sales', 'sale_items', 'sale_payments', 'returns', 'return_items',
    
    // Purchases
    'purchase_orders', 'purchase_order_items', 'purchase_items', 'purchase_returns',
    'suppliers', 'product_suppliers', 'supplier_products',
    
    // POS
    'pos_settings', 'pos_tabs', 'pos_tab_items', 'pos_kot_jobs', 'terminal_devices',
    'restaurant_tables', 'pos_order_types', 'shifts', 'cash_drawers', 'cash_movements',
    
    // Shop
    'shop_settings', 'shop_products', 'shop_orders', 'online_orders', 'online_order_items',
    'delivery_areas', 'delivery_routes', 'driver_locations',
    
    // Billing
    'invoices', 'invoice_items', 'subscription_features', 'subscription_invoices',
    'gift_cards', 'credits', 'credit_notes', 'payment_gateways', 'payment_transactions',
    
    // Accounting
    'chart_of_accounts', 'accounting_periods', 'journal_entries', 'bank_reconciliations',
    'tax_rates', 'expenses', 'expense_categories',
    
    // System
    'settings', 'activity_logs', 'notifications', 'migrations', 'failed_jobs',
    'job_queue', 'sync_queue', 'webhook_retries', 'api_request_logs', 'api_tokens',
    
    // Users
    'user_permissions', 'user_notes', 'user_password_history', 'user_sessions',
    'time_clock', 
    
    // Support
    'support_access_logs', 'support_access_sessions', 'tenant_configs',
    
    // Marketing
    'marketing_campaigns', 'campaign_messages', 'coupons', 'coupon_usage',
    
    // Misc
    'currencies', 'exchange_rates', 'label_templates', 'rate_limit_blocks',
    'offline_transactions', 'offline_products', 'offline_customers', 'offline_settings',
    'conflict_log', 'custom_reports', 'restaurant_tables', 'reservations', 'reminders',
    'business_types', 'company_verticals', 'company_permissions', 'permission_groups',
    'branch_hours',
];

$stmt = $pdo->query("SHOW TABLES");
$db_tables = array_map('strtolower', $stmt->fetchAll(PDO::FETCH_COLUMN));

$matched = [];
$missing = [];

foreach ($critical_tables as $table) {
    if (in_array(strtolower($table), $db_tables)) {
        $matched[] = $table;
    } else {
        $missing[] = $table;
    }
}

echo "=== CRITICAL TABLES VERIFICATION ===\n\n";
echo "Total critical tables checked: " . count($critical_tables) . "\n";
echo "Tables FOUND in database:      " . count($matched) . "\n";
echo "Tables MISSING from database:  " . count($missing) . "\n\n";

if (count($missing) > 0) {
    echo "MISSING CRITICAL TABLES:\n";
    foreach ($missing as $i => $t) {
        echo ($i + 1) . ". $t\n";
    }
    echo "\n";
} else {
    echo "✓ ALL CRITICAL TABLES EXIST!\n\n";
}

// Count total tables
echo "=== DATABASE OVERVIEW ===\n";
echo "Total tables in database: " . count($db_tables) . "\n";

// Group by category
$categories = [
    'Core' => ['users', 'roles', 'permissions', 'companies', 'branches'],
    'Products' => ['products', 'categories', 'inventory'],
    'Sales/POS' => ['sales', 'sale_items', 'pos_settings', 'pos_tabs', 'cash_drawers'],
    'Purchasing' => ['purchase_orders', 'suppliers', 'purchase_items'],
    'Customers' => ['customers', 'customer_loyalty', 'loyalty_rules'],
    'Shop' => ['shop_settings', 'shop_orders', 'online_orders'],
    'Billing' => ['invoices', 'payment_gateways', 'payment_transactions'],
    'System' => ['settings', 'activity_logs', 'notifications', 'migrations'],
];

echo "\n=== TABLES BY CATEGORY ===\n";
foreach ($categories as $cat => $tables) {
    $found = 0;
    foreach ($tables as $t) {
        if (in_array(strtolower($t), $db_tables)) $found++;
    }
    echo "$cat: $found/" . count($tables) . " ✓\n";
}

echo "\n=== OVERALL STATUS: " . (count($missing) === 0 ? "✓ COMPLETE - ALL CRITICAL TABLES EXIST" : "⚠ MISSING " . count($missing) . " CRITICAL TABLES") . " ===\n";
