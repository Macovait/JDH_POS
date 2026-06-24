<?php
/**
 * Check if specific tables referenced in code exist in database
 */

require_once __DIR__ . '/../src/db.php';

$pdo = get_db_connection();

// Tables commonly referenced in JDH POS code based on grep results
$code_tables = [
    // Core tables
    'users', 'roles', 'permissions', 'role_permissions', 'companies', 'branches',
    'products', 'categories', 'customers', 'sales', 'sale_items', 'inventory',
    'suppliers', 'purchases', 'invoices', 'subscriptions', 'tax_rates',
    'settings', 'activity_logs', 'notifications', 'returns', 'return_items',
    
    // Recently added tables
    'cash_drawers', 'cash_movements', 'inventory_adjustments', 'inventory_counts',
    'stock_alerts', 'units', 'unit_conversions', 'purchase_items', 'purchase_returns',
    'product_suppliers', 'supplier_products', 'customer_loyalty', 'loyalty_rules',
    'customer_labels', 'loyalty_transactions', 'invoice_items', 'subscription_features',
    'subscription_invoices', 'gift_cards', 'shop_settings', 'shop_products',
    'shop_orders', 'delivery_areas', 'delivery_routes', 'driver_locations',
    'product_labels', 'product_price_history', 'product_sync_log', 'terminal_devices',
    'pos_settings', 'pos_tabs', 'pos_tab_items', 'pos_kot_jobs', 'restaurant_tables',
    'time_clock', 'shifts', 'reservations', 'reminders', 'migrations', 'failed_jobs',
    'webhook_retries', 'user_permissions', 'job_queue', 'sync_queue', 'tenant_configs',
    'support_access_logs', 'support_access_sessions', 'marketing_campaigns',
    'campaign_messages', 'payment_gateways', 'payment_transactions', 'currencies',
    'exchange_rates', 'label_templates', 'rate_limit_blocks', 'offline_transactions',
    'offline_products', 'offline_customers', 'offline_settings', 'conflict_log',
    'custom_reports', 'user_notes', 'user_password_history', 'user_sessions',
    'company_verticals', 'company_permissions', 'business_types', 'permission_groups',
    'branch_hours',
    
    // Additional tables that may be referenced
    'discounts', 'promotions', 'price_rules', 'tax_classes', 'attributes',
    'attribute_values', 'product_attributes', 'stock_transfers', 'stock_adjustments',
    'payment_methods', 'expense_categories', 'expenses', 'budgets',
    'accounting_periods', 'journal_entries', 'ledger_accounts',
    'loyalty_tiers', 'customer_groups', 'wishlists', 'reviews',
    'shipping_methods', 'shipping_zones', 'shipping_rates',
    'email_templates', 'sms_templates', 'notification_templates',
    'print_templates', 'barcode_settings', 'receipt_templates',
    'audit_logs', 'system_logs', 'error_logs', 'api_logs',
    'tenant_settings', 'company_settings', 'branch_settings',
    'user_preferences', 'dashboard_widgets', 'reports', 'report_schedules',
    'backup_logs', 'import_logs', 'export_logs',
    'vendor_products', 'vendor_orders', 'consignments',
    'work_orders', 'maintenance_logs', 'asset_register',
    'employee_shifts', 'timesheets', 'payroll', 'commissions',
    'bookings', 'appointments', 'schedules', 'calendars',
    'tickets', 'support_tickets', 'ticket_replies', 'kb_articles',
    'kpi_metrics', 'goals', 'targets', 'performance_reviews',
    'webhooks', 'webhook_deliveries', 'integrations', 'api_keys',
    'data_sync', 'sync_logs', 'replication_status',
];

$stmt = $pdo->query("SHOW TABLES");
$db_tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
$db_tables_lower = array_map('strtolower', $db_tables);

$matched = [];
$missing = [];

foreach ($code_tables as $table) {
    if (in_array(strtolower($table), $db_tables_lower)) {
        $matched[] = $table;
    } else {
        $missing[] = $table;
    }
}

echo "=== CODE-DATABASE MATCH REPORT ===\n\n";
echo "Tables expected by code:  " . count($code_tables) . "\n";
echo "Tables found in DB:       " . count($db_tables) . "\n";
echo "Tables MATCHED:           " . count($matched) . "\n";
echo "Tables MISSING from DB:   " . count($missing) . "\n\n";

if (count($missing) > 0) {
    echo "MISSING TABLES (may cause errors if referenced):\n";
    foreach (array_slice($missing, 0, 50) as $i => $t) {
        echo ($i + 1) . ". $t\n";
    }
    if (count($missing) > 50) {
        echo "... and " . (count($missing) - 50) . " more\n";
    }
    echo "\n";
}

// Check for extra tables in DB not in our expected list
$extra_in_db = [];
foreach ($db_tables as $db_table) {
    if (!in_array(strtolower($db_table), array_map('strtolower', $code_tables))) {
        $extra_in_db[] = $db_table;
    }
}

echo "EXTRA TABLES IN DB (not in expected list): " . count($extra_in_db) . "\n";
foreach (array_slice($extra_in_db, 0, 30) as $i => $t) {
    echo "  - $t\n";
}
if (count($extra_in_db) > 30) {
    echo "  ... and " . (count($extra_in_db) - 30) . " more\n";
}

echo "\n=== STATUS: " . (count($missing) === 0 ? "ALL EXPECTED TABLES EXIST ✓" : "SOME TABLES MISSING ✗") . " ===\n";
