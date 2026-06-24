<?php
/**
 * Database Schema Checker for jdh_pos
 * Compares live DB schema against POS file expectations
 */

require_once __DIR__ . '/../src/Config.php';

try {
    $pdo = \JDH\POS\Config::getPDO();
} catch (Exception $e) {
    die("DB Connection failed: " . $e->getMessage());
}

// Get all tables
$tables = $pdo->query("SHOW TABLES FROM jdh_pos")->fetchAll(PDO::FETCH_COLUMN);

$schema = [];
foreach ($tables as $table) {
    $columns = $pdo->query("DESCRIBE `{$table}`")->fetchAll(PDO::FETCH_ASSOC);
    $schema[$table] = array_column($columns, 'Field');
}

// Tables expected by POS files based on codebase audit
$expectedTables = [
    // Core transaction tables
    'sales', 'sale_items', 'sale_payments',
    'sell_returns', 'sell_return_items',
    
    // Product & inventory
    'products', 'product_variations', 'product_attributes',
    'categories', 'brands', 'units',
    'inventory', 'stock_adjustments', 'stock_transfers',
    
    // Customer & supplier
    'customers', 'customer_groups', 'suppliers',
    
    // Business structure
    'companies', 'branches', 'business_types',
    
    // Settings & configuration
    'settings', 'taxes', 'payment_methods', 'currencies', 'exchange_rates',
    
    // Users & roles
    'users', 'roles', 'role_user', 'permissions', 'model_has_permissions', 'model_has_roles',
    
    // POS features
    'shifts', 'cash_drawer', 'saved_carts', 'pos_tabs', 'pos_tab_items', 'pos_kot_jobs',
    'restaurant_tables', 'reservations', 'kitchen_orders', 'kitchen_notes',
    
    // Loyalty & marketing
    'loyalty_transactions', 'marketing_campaigns', 'campaign_messages',
    
    // Payments & transactions
    'payment_transactions', 'payment_gateways', 'mpesa_transactions',
    
    // Offline & sync
    'offline_transactions', 'offline_products', 'offline_customers', 'offline_settings',
    'sync_queue', 'job_queue',
    
    // Logging & audit
    'activity_logs', 'audit_logs', 'user_logs',
    
    // Support & system
    'support_access_logs', 'support_access_sessions',
    'tenant_configs', 'tenant_settings', 'tenant_features',
    
    // Reports
    'custom_reports',
    
    // Other
    'expenses', 'expense_categories', 'purchases', 'purchase_items',
    'prescriptions', 'sms_templates', 'email_templates',
    'driver_locations', 'user_notes', 'company_verticals',
    'label_templates', 'rate_limit_blocks',
    'conflict_log', 'product_sync_log',
    'online_orders', 'cart_items',
    'subscriptions', 'subscription_items', 'invoices',
    'plans', 'plan_features', 'feature_flags',
    'api_keys', 'webhook_endpoints', 'webhook_deliveries',
];

header('Content-Type: text/plain');
echo "===============================================\n";
echo "JDH_POS Database Schema Check Report\n";
echo "Database: jdh_pos\n";
echo "Total Tables Found: " . count($tables) . "\n";
echo "===============================================\n\n";

echo "--- EXPECTED TABLES STATUS ---\n";
$missing = [];
$found = [];
foreach ($expectedTables as $table) {
    if (in_array($table, $tables)) {
        $found[] = $table;
        echo "[OK]   {$table}\n";
    } else {
        $missing[] = $table;
        echo "[MISS] {$table}\n";
    }
}

echo "\n--- UNEXPECTED TABLES (not in expected list) ---\n";
$unexpected = array_diff($tables, $expectedTables);
if (empty($unexpected)) {
    echo "None - all tables accounted for\n";
} else {
    foreach ($unexpected as $table) {
        echo "[EXTRA] {$table}\n";
    }
}

echo "\n--- MISSING TABLES SUMMARY ---\n";
echo "Missing count: " . count($missing) . "\n";
if (!empty($missing)) {
    foreach ($missing as $table) {
        echo "  - {$table}\n";
    }
}

echo "\n--- ALL TABLES WITH COLUMN COUNTS ---\n";
foreach ($schema as $table => $columns) {
    echo str_pad($table, 40) . " (" . count($columns) . " cols)\n";
}

echo "\n--- CRITICAL TABLE COLUMNS ---\n";
$criticalTables = ['sales', 'sale_items', 'products', 'customers', 'branches', 'users'];
foreach ($criticalTables as $table) {
    if (isset($schema[$table])) {
        echo "\n[{$table}] columns:\n";
        echo "  " . implode(", ", $schema[$table]) . "\n";
    }
}

echo "\n===============================================\n";
echo "End of Report\n";
echo "===============================================\n";
