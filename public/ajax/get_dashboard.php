<?php
declare(strict_types=1);

/**
 * Get Dashboard Stats - AJAX Endpoint
 *
 * GET /ajax/get_dashboard.php
 *
 * Returns POS dashboard statistics scoped by tenant_id + branch_id.
 * Adapts output based on business_type.
 */

header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');

require_once __DIR__ . '/../../src/paths.php';
safe_require('db.php', 'src', true);
safe_require('auth.php', 'src', true);
safe_require('functions.php', 'src', true);

require_login();

require_once __DIR__ . '/../../src/pos_backend.php';

try {
    $ctx = pos_require_session();
    $pdo = get_db_connection();

    $stats = pos_get_dashboard_stats(
        $pdo,
        $ctx['tenant_id'],
        $ctx['branch_id'],
        $ctx['business_type']
    );

    $bt_config = get_business_type_config($ctx['business_type']) ?? [];
    $stats['bt_config'] = [
        'type'           => $ctx['business_type'],
        'name'           => $bt_config['name'] ?? 'Retail',
        'icon'           => $bt_config['icon'] ?? 'fa-store',
        'sale_label'     => $bt_config['sale_label'] ?? 'Sale',
        'sales_label'    => $bt_config['sales_label'] ?? 'Sales',
        'customer_label' => $bt_config['customer_label'] ?? 'Customer',
        'order_labels'   => $bt_config['order_labels'] ?? [],
        'features'       => $bt_config['features'] ?? [],
    ];

    pos_json_success($stats);

} catch (\PDOException $e) {
    error_log("get_dashboard error: " . $e->getMessage());
    pos_json_error('Database error loading dashboard', 500);
} catch (\Exception $e) {
    pos_json_error($e->getMessage(), 400);
}
