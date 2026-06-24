<?php
/**
 * Database Schema Verification Script
 * Compares actual database tables with codebase expectations
 */

$pdo = new PDO('mysql:host=localhost;dbname=jdh_pos;charset=utf8mb4', 'root', '');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Get all tables from database
$stmt = $pdo->query("SHOW TABLES");
$db_tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
sort($db_tables);

// Tables referenced in codebase (from migration files, models, and PHP files)
$expected_tables = [
    // Core tables
    'abandoned_carts',
    'accounting_periods',
    'activity_logs',
    'admin_notifications',
    'admin_roles',
    'admins',
    'api_keys',
    'api_rate_limit_violations',
    'api_request_logs',
    'api_tokens',
    'api_usage_stats',
    'appointments',
    'attribute_groups',
    'attribute_values',
    'attributes',
    'audit_logs',
    'background_jobs',
    'backups',
    'branches',
    'campaign_messages',
    'cash_drawers',
    'cash_movements',
    'categories',
    'companies',
    'company_verticals',
    'conflict_log',
    'credits',
    'currencies',
    'custom_reports',
    'customer_groups',
    'customer_labels',
    'customer_loyalty',
    'customers',
    'delivery_areas',
    'delivery_routes',
    'discounts',
    'driver_locations',
    'email_templates',
    'exchange_rates',
    'expense_categories',
    'expenses',
    'failed_jobs',
    'features',
    'gift_cards',
    'inventory_adjustments',
    'inventory_counts',
    'invoice_items',
    'invoices',
    'job_queue',
    'kitchen_stations',
    'label_templates',
    'loyalty_rules',
    'loyalty_transactions',
    'migrations',
    'notification_queue',
    'notifications',
    'offline_customers',
    'offline_products',
    'offline_settings',
    'offline_transactions',
    'payment_gateways',
    'payment_methods',
    'payment_transactions',
    'plan_features',
    'plans',
    'pos_kot_jobs',
    'pos_settings',
    'pos_subscriptions',
    'pos_tab_items',
    'pos_tabs',
    'product_attributes',
    'product_images',
    'product_labels',
    'product_serials',
    'product_suppliers',
    'product_sync_log',
    'products',
    'purchase_items',
    'purchase_returns',
    'purchases',
    'rate_limit_blocks',
    'reminders',
    'reservations',
    'restaurant_tables',
    'return_items',
    'returns',
    'roles',
    'sale_items',
    'sales',
    'sessions',
    'settings',
    'shifts',
    'shop_orders',
    'shop_products',
    'shop_settings',
    'stock_alerts',
    'subscription_features',
    'subscription_invoices',
    'subscriptions',
    'supplier_products',
    'suppliers',
    'support_access_logs',
    'support_access_sessions',
    'sync_queue',
    'tax_rates',
    'tenant_configs',
    'tenant_settings',
    'tenants',
    'terminal_devices',
    'time_clock',
    'unit_conversions',
    'units',
    'user_notes',
    'user_permissions',
    'users',
    'webhook_logs',
    'webhook_retries',
    'marketing_campaigns',
];

sort($expected_tables);

echo "=== DATABASE SCHEMA ANALYSIS ===\n\n";

echo "=== Tables in Database (" . count($db_tables) . ") ===\n";
foreach ($db_tables as $table) {
    $expected = in_array($table, $expected_tables) ? '✓' : '?';
    echo "  [$expected] $table\n";
}

echo "\n=== Missing Tables (Expected but not in DB) ===\n";
$missing = array_diff($expected_tables, $db_tables);
if (empty($missing)) {
    echo "  All expected tables exist!\n";
} else {
    foreach ($missing as $table) {
        echo "  ✗ MISSING: $table\n";
    }
}

echo "\n=== Unexpected Tables (in DB but not in expected list) ===\n";
$unexpected = array_diff($db_tables, $expected_tables);
if (empty($unexpected)) {
    echo "  No unexpected tables found.\n";
} else {
    foreach ($unexpected as $table) {
        echo "  ? $table\n";
    }
}

echo "\n=== Checking for Foreign Key Constraints ===\n";
try {
    $stmt = $pdo->query("
        SELECT
            TABLE_NAME,
            COLUMN_NAME,
            CONSTRAINT_NAME,
            REFERENCED_TABLE_NAME,
            REFERENCED_COLUMN_NAME
        FROM
            INFORMATION_SCHEMA.KEY_COLUMN_USAGE
        WHERE
            REFERENCED_TABLE_NAME IS NOT NULL
            AND TABLE_SCHEMA = DATABASE()
    ");
    $fks = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "  Found " . count($fks) . " foreign key constraints\n";
} catch (Exception $e) {
    echo "  Error checking FKs: " . $e->getMessage() . "\n";
}

echo "\n=== Analysis Complete ===\n";
