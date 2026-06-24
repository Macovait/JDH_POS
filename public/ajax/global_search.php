<?php
/**
 * Global Search Component
 * Search across all system features and data
 */

require_once __DIR__ . '/../../src/paths.php';
safe_require('auth.php', 'src', true);

// Branch filter for multi-tenant isolation
$current_branch_id = get_current_branch_id();
safe_require('db.php', 'src', true);
safe_require('functions.php', 'src', true);

// Check permissions
require_login();
$tenant_id = get_current_tenant_id();
$user_id = get_current_user_id();

$query = $_GET['q'] ?? '';
$results = [];

if (!empty($query) && strlen($query) >= 2) {
    $results = perform_global_search($pdo, $tenant_id, $query, $user_id);
}

header('Content-Type: application/json');
echo json_encode([
    'query' => $query,
    'results' => $results,
    'total' => count($results)
]);

function perform_global_search($pdo, $tenant_id, $query, $user_id) {
    $results = [];
    $branch_id = get_current_branch_id();

    // Search in products
    $stmt = $pdo->prepare("
        SELECT 'product' as type, id, name, sku,
               CONCAT('/products/products.php?edit=', id) as url,
               'fas fa-box' as icon, 'blue' as color
        FROM products
        WHERE tenant_id = ? AND active = 1 AND deleted_at IS NULL
        AND (name LIKE ? OR sku LIKE ? OR barcode LIKE ?)
        ORDER BY name
        LIMIT 5
    ");
    $stmt->execute([$tenant_id, "%$query%", "%$query%", "%$query%"]);
    $results = array_merge($results, $stmt->fetchAll(PDO::FETCH_ASSOC));

    // Search in customers
    $stmt = $pdo->prepare("
        SELECT 'customer' as type, id, name, email,
               CONCAT('/customers/customers.php?view=', id) as url,
               'fas fa-user' as icon, 'green' as color
        FROM customers
        WHERE tenant_id = ? AND active = 1
        AND (name LIKE ? OR email LIKE ? OR phone LIKE ?)
        ORDER BY name
        LIMIT 5
    ");
    $stmt->execute([$tenant_id, "%$query%", "%$query%", "%$query%"]);
    $results = array_merge($results, $stmt->fetchAll(PDO::FETCH_ASSOC));

    // Search in sales/receipts - scoped by branch for multi-tenant isolation
    $branch_filter = $branch_id > 0 ? " AND branch_id = ?" : "";
    $sale_id = is_numeric($query) ? $query : 0;
    $sale_sql = "
        SELECT 'sale' as type, id, receipt_number, final_amount,
               CONCAT('/pos/receipts/view_sale.php?id=', id) as url,
               'fas fa-receipt' as icon, 'purple' as color
        FROM sales
        WHERE tenant_id = ? $branch_filter
        AND (receipt_number LIKE ? OR id = ?)
        ORDER BY created_at DESC
        LIMIT 3
    ";
    $sale_params = [$tenant_id];
    if ($branch_id > 0) $sale_params[] = $branch_id;
    $sale_params[] = "%$query%";
    $sale_params[] = $sale_id;
    
    $stmt = $pdo->prepare($sale_sql);
    $stmt->execute($sale_params);
    $results = array_merge($results, $stmt->fetchAll(PDO::FETCH_ASSOC));

    // Search in navigation/features (not tenant-scoped)
    $features = [
        ['type' => 'feature', 'name' => 'Smart Inventory', 'url' => '/inventory/automated_inventory.php', 'icon' => 'fas fa-robot', 'color' => 'amber'],
        ['type' => 'feature', 'name' => 'AI Recommendations', 'url' => '/ai/recommendations.php', 'icon' => 'fas fa-brain', 'color' => 'green'],
        ['type' => 'feature', 'name' => 'Marketing Campaigns', 'url' => '/marketing/campaigns.php', 'icon' => 'fas fa-bullhorn', 'color' => 'purple'],
        ['type' => 'feature', 'name' => 'Stripe Payments', 'url' => '/payments/stripe_gateway.php', 'icon' => 'fab fa-stripe', 'color' => 'blue'],
        ['type' => 'feature', 'name' => 'Custom Reports', 'url' => '/reports/custom_builder.php', 'icon' => 'fas fa-chart-bar', 'color' => 'cyan'],
        ['type' => 'feature', 'name' => 'User Permissions', 'url' => '/users/advanced_permissions.php', 'icon' => 'fas fa-shield-alt', 'color' => 'red'],
    ];

    foreach ($features as $feature) {
        if (stripos($feature['name'], $query) !== false) {
            $results[] = $feature;
        }
    }

    return array_slice($results, 0, 10); // Limit to 10 results
}
?>