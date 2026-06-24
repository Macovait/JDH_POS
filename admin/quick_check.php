<?php
require_once __DIR__ . '/../src/db.php';

$pdo = get_db_connection();

$expected = [
    'cash_drawers', 'cash_movements', 'inventory_adjustments', 'inventory_counts', 'stock_alerts', 
    'units', 'unit_conversions', 'purchase_items', 'purchase_returns', 'product_suppliers', 
    'supplier_products', 'customer_loyalty', 'loyalty_rules', 'customer_labels', 'loyalty_transactions',
    'invoice_items', 'subscription_features', 'subscription_invoices', 'gift_cards',
    'shop_settings', 'shop_products', 'shop_orders', 'delivery_areas', 'delivery_routes', 
    'driver_locations', 'product_labels', 'product_price_history', 'product_sync_log',
    'terminal_devices', 'pos_settings', 'pos_tabs', 'pos_tab_items', 'pos_kot_jobs', 
    'restaurant_tables', 'time_clock', 'shifts', 'reservations', 'reminders',
    'migrations', 'failed_jobs', 'webhook_retries', 'user_permissions', 'job_queue',
    'sync_queue', 'tenant_configs', 'support_access_logs', 'support_access_sessions',
    'marketing_campaigns', 'campaign_messages', 'payment_gateways', 'payment_transactions',
    'currencies', 'exchange_rates', 'label_templates', 'rate_limit_blocks',
    'offline_transactions', 'offline_products', 'offline_customers', 'offline_settings',
    'conflict_log', 'custom_reports', 'user_notes', 'user_password_history', 'user_sessions',
    'company_verticals', 'company_permissions', 'business_types', 'permission_groups', 'branch_hours'
];

$stmt = $pdo->query("SHOW TABLES");
$existing = $stmt->fetchAll(PDO::FETCH_COLUMN);

$missing = array_diff($expected, $existing);
$found = array_intersect($expected, $existing);

echo "=== JDH POS DATABASE CHECK ===\n\n";
echo "Total expected: " . count($expected) . "\n";
echo "Tables found:   " . count($found) . "\n";
echo "Still missing:  " . count($missing) . "\n\n";

if (count($missing) > 0) {
    echo "MISSING TABLES:\n";
    foreach ($missing as $i => $t) {
        echo ($i + 1) . ". $t\n";
    }
    echo "\n";
}

if (count($found) > 0) {
    echo "TABLES CONFIRMED (showing first 20):\n";
    foreach (array_slice($found, 0, 20) as $i => $t) {
        echo ($i + 1) . ". $t\n";
    }
    if (count($found) > 20) {
        echo "... and " . (count($found) - 20) . " more\n";
    }
}

echo "\n=== CHECK COMPLETE ===\n";
