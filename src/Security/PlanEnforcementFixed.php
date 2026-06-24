<?php
/**
 * Subscription Plan Enforcement
 * Restricts features based on tenant's plan tier
 * 
 * @package Jakababa\Security
 * @version 1.0.0
 */

class PlanEnforcement {
    
    /**
     * Plan limits configuration
     */
    private static array $planLimits = [];
    private static bool $limitsLoaded = false;
    
    /**
     * Initialize plan limits from config or use defaults
     */
    private static function loadPlanLimits(): void {
        if (self::$limitsLoaded) return;
        
        $configLimits = [];
        if (class_exists('SecurityConfig')) {
            SecurityConfig::load();
            $configLimits = SecurityConfig::getPlanConfig();
        }
        
        self::$planLimits = [
            'free' => [
                'max_users' => $configLimits['free']['max_users'] ?? 1,
                'max_products' => $configLimits['free']['max_products'] ?? 100,
                'max_branches' => $configLimits['free']['max_branches'] ?? 1,
                'storage_gb' => $configLimits['free']['storage_gb'] ?? 1,
                'features' => [
                    'pos.basic' => true,
                    'pos.offline' => false,
                    'inventory.basic' => true,
                    'inventory.advanced' => false,
                    'reports.basic' => true,
                    'reports.advanced' => false,
                    'customers.basic' => true,
                    'customers.loyalty' => false,
                    'users.management' => false,
                    'api.access' => false,
                    'integrations' => false,
                    'multi_branch' => false,
                    'custom_receipts' => false,
                    'bulk_operations' => false,
                    'data_export' => false,
                    'analytics' => false,
                    'sms_notifications' => false,
                    'email_notifications' => false,
                    'priority_support' => false,
                ],
                'support_level' => 'email',
            ],
            'starter' => [
                'max_users' => $configLimits['starter']['max_users'] ?? 3,
                'max_products' => $configLimits['starter']['max_products'] ?? 1000,
                'max_branches' => $configLimits['starter']['max_branches'] ?? 1,
                'storage_gb' => $configLimits['starter']['storage_gb'] ?? 5,
                'features' => [
                    'pos.basic' => true,
                    'pos.offline' => true,
                    'inventory.basic' => true,
                    'inventory.advanced' => true,
                    'reports.basic' => true,
                    'reports.advanced' => false,
                    'customers.basic' => true,
                    'customers.loyalty' => true,
                    'users.management' => true,
                    'api.access' => false,
                    'integrations' => false,
                    'multi_branch' => false,
                    'custom_receipts' => true,
                    'bulk_operations' => true,
                    'data_export' => true,
                    'analytics' => false,
                    'sms_notifications' => true,
                    'email_notifications' => true,
                    'priority_support' => false,
                ],
                'support_level' => 'priority_email',
            ],
            'professional' => [
                'max_users' => PHP_INT_MAX,
                'max_products' => PHP_INT_MAX,
                'max_branches' => PHP_INT_MAX,
                'storage_gb' => $configLimits['professional']['storage_gb'] ?? 50,
                'features' => [
                    'pos.basic' => true,
                    'pos.offline' => true,
                    'inventory.basic' => true,
                    'inventory.advanced' => true,
                    'reports.basic' => true,
                    'reports.advanced' => true,
                    'customers.basic' => true,
                    'customers.loyalty' => true,
                    'users.management' => true,
                    'api.access' => true,
                    'integrations' => true,
                    'multi_branch' => true,
                    'custom_receipts' => true,
                    'bulk_operations' => true,
                    'data_export' => true,
                    'analytics' => true,
                    'sms_notifications' => true,
                    'email_notifications' => true,
                    'priority_support' => true,
                ],
                'support_level' => '24_7',
            ],
        ];
        
        self::$limitsLoaded = true;
    }
    
    /**
     * Check if current tenant has access to a feature
     */
    public static function hasFeature(string $feature): bool {
        self::loadPlanLimits();
        $plan = self::getCurrentPlan();
        return self::$planLimits[$plan]['features'][$feature] ?? false;
    }
    
    /**
     * Check if limit is reached
     */
    public static function checkLimit(string $limitType, int $currentCount): bool {
        self::loadPlanLimits();
        $plan = self::getCurrentPlan();
        $limit = self::$planLimits[$plan][$limitType] ?? 0;
        return $currentCount < $limit;
    }
    
    /**
     * Get remaining quota
     */
    public static function getRemainingQuota(string $limitType, int $currentCount): int {
        self::loadPlanLimits();
        $plan = self::getCurrentPlan();
        $limit = self::$planLimits[$plan][$limitType] ?? 0;
        return max(0, $limit - $currentCount);
    }
    
    /**
     * Get current tenant plan
     */
    public static function getCurrentPlan(): string {
        return $_SESSION['tenant_plan'] ?? 'free';
    }
    
    /**
     * Enforce feature access
     */
    public static function enforceFeature(string $feature, string $redirectUrl = null): void {
        if (!self::hasFeature($feature)) {
            $errorMessage = "This feature requires a higher plan. Please upgrade to access it.";
            
            if ($redirectUrl) {
                $_SESSION['upgrade_error'] = $errorMessage;
                $_SESSION['required_feature'] = $feature;
                redirect($redirectUrl);
            }
            
            http_response_code(403);
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'error' => 'plan_required',
                'message' => $errorMessage,
                'feature' => $feature,
                'current_plan' => self::getCurrentPlan(),
                'upgrade_url' => base_url('dashboard/billing.php')
            ]);
            exit;
        }
    }
    
    /**
     * Enforce limit
     */
    public static function enforceLimit(string $limitType, int $currentCount, string $itemName = 'item'): void {
        if (!self::checkLimit($limitType, $currentCount)) {
            $plan = self::getCurrentPlan();
            $limit = self::$planLimits[$plan][$limitType] ?? 0;
            
            $errorMessage = "You've reached the {$limit} {$itemName} limit for your {$plan} plan. Please upgrade to add more.";
            
            http_response_code(403);
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'error' => 'limit_reached',
                'message' => $errorMessage,
                'limit' => $limit,
                'current' => $currentCount,
                'current_plan' => $plan,
                'upgrade_url' => base_url('dashboard/billing.php')
            ]);
            exit;
        }
    }
    
    /**
     * Get plan details
     */
    public static function getPlanDetails(string $plan = null): array {
        self::loadPlanLimits();
        $plan = $plan ?? self::getCurrentPlan();
        $details = self::$planLimits[$plan] ?? self::$planLimits['free'];
        
        return [
            'name' => ucfirst($plan),
            'max_users' => $details['max_users'] === PHP_INT_MAX ? 'Unlimited' : $details['max_users'],
            'max_products' => $details['max_products'] === PHP_INT_MAX ? 'Unlimited' : $details['max_products'],
            'max_branches' => $details['max_branches'] === PHP_INT_MAX ? 'Unlimited' : $details['max_branches'],
            'storage_gb' => $details['storage_gb'],
            'support_level' => $details['support_level'],
            'features' => $details['features'],
        ];
    }
    
    /**
     * Get all available plans
     */
    public static function getAvailablePlans(): array {
        self::loadPlanLimits();
        $plans = [];
        foreach (self::$planLimits as $plan => $details) {
            $plans[$plan] = self::getPlanDetails($plan);
        }
        return $plans;
    }
    
    /**
     * Get feature status
     */
    public static function getFeatureStatus(): array {
        self::loadPlanLimits();
        $plan = self::getCurrentPlan();
        $features = [];
        $allFeatures = array_keys(self::$planLimits['professional']['features']);
        
        foreach ($allFeatures as $feature) {
            $features[$feature] = [
                'available' => self::$planLimits[$plan]['features'][$feature] ?? false,
                'plan' => self::getMinimumPlanForFeature($feature),
            ];
        }
        return $features;
    }
    
    /**
     * Get minimum plan for feature
     */
    private static function getMinimumPlanForFeature(string $feature): string {
        self::loadPlanLimits();
        $planOrder = ['free', 'starter', 'professional'];
        foreach ($planOrder as $plan) {
            if (self::$planLimits[$plan]['features'][$feature] ?? false) {
                return $plan;
            }
        }
        return 'professional';
    }
    
    /**
     * Check if plan is active
     */
    public static function isPlanActive(PDO $pdo, int $tenantId): bool {
        try {
            $stmt = $pdo->prepare("SELECT plan, plan_expires_at FROM tenants WHERE id = ? LIMIT 1");
            $stmt->execute([$tenantId]);
            $tenant = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$tenant) return false;
            if ($tenant['plan'] === 'free') return true;
            
            if ($tenant['plan_expires_at']) {
                return strtotime($tenant['plan_expires_at']) > time();
            }
            return true;
        } catch (Exception $e) {
            error_log("Plan check failed: " . $e->getMessage());
            return true;
        }
    }
    
    /**
     * Load tenant plan into session
     */
    public static function loadTenantPlan(PDO $pdo, int $tenantId): void {
        try {
            $stmt = $pdo->prepare("SELECT plan, plan_status FROM tenants WHERE id = ? LIMIT 1");
            $stmt->execute([$tenantId]);
            $tenant = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($tenant) {
                $_SESSION['tenant_plan'] = $tenant['plan'] ?? 'free';
                $_SESSION['tenant_plan_status'] = $tenant['plan_status'] ?? 'active';
            }
        } catch (Exception $e) {
            $_SESSION['tenant_plan'] = 'free';
            $_SESSION['tenant_plan_status'] = 'active';
        }
    }
    
    /**
     * Get usage statistics
     */
    public static function getUsageStats(PDO $pdo, int $tenantId): array {
        $stats = ['users' => 0, 'products' => 0, 'branches' => 0];
        
        try {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE tenant_id = ? AND deleted_at IS NULL");
            $stmt->execute([$tenantId]);
            $stats['users'] = (int) $stmt->fetchColumn();
            
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM products WHERE tenant_id = ? AND deleted_at IS NULL");
            $stmt->execute([$tenantId]);
            $stats['products'] = (int) $stmt->fetchColumn();
            
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM branches WHERE tenant_id = ? AND deleted_at IS NULL");
            $stmt->execute([$tenantId]);
            $stats['branches'] = (int) $stmt->fetchColumn();
            
            $plan = self::getCurrentPlan();
            $limits = self::$planLimits[$plan];
            
            foreach (['users', 'products', 'branches'] as $type) {
                $limitKey = 'max_' . $type;
                $limit = $limits[$limitKey] ?? 0;
                $current = $stats[$type];
                
                $stats[$type . '_limit'] = $limit === PHP_INT_MAX ? 'Unlimited' : $limit;
                $stats[$type . '_remaining'] = $limit === PHP_INT_MAX ? 'Unlimited' : max(0, $limit - $current);
                $stats[$type . '_percentage'] = $limit === PHP_INT_MAX ? 0 : round(($current / $limit) * 100, 1);
            }
        } catch (Exception $e) {
            error_log("Usage stats failed: " . $e->getMessage());
        }
        
        return $stats;
    }
}
?>
