<?php
/**
 * Get Plan Usage Statistics
 * Returns current usage vs limits for dashboard widget
 */

require_once __DIR__ . '/../../src/paths.php';
safe_require('auth.php', 'src', true);
require_login();

header('Content-Type: application/json');

try {
    $pdo = get_db_connection();
    $tenant_id = get_current_tenant_id();
    
    // Load plan enforcement
    require_once __DIR__ . '/../../src/Security/PlanEnforcement.php';
    
    // Get usage stats
    $usage = PlanEnforcement::getUsageStats($pdo, $tenant_id);
    $plan = PlanEnforcement::getCurrentPlan();
    $details = PlanEnforcement::getPlanDetails();
    
    echo json_encode([
        'success' => true,
        'plan' => $plan,
        'plan_name' => $details['name'],
        'usage' => $usage,
        'limits' => [
            'users' => $details['max_users'],
            'products' => $details['max_products'],
            'branches' => $details['max_branches'],
            'storage' => $details['storage_gb'],
        ],
        'features' => PlanEnforcement::getFeatureStatus()
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
