<?php
/**
 * Module Access Control Utility
 * Provides consistent permission-based module filtering across all pages
 * 
 * @package Jakababa\Security
 * @version 1.0.0
 */

class ModuleAccess {
    
    /**
     * Define module permissions for each section
     */
    private static array $modulePermissions = [
        // Reports modules
        'reports' => [
            'sales' => 'sales.view',
            'profit_loss' => 'reports.advanced',
            'inventory' => 'inventory.view',
            'customers' => 'customers.view',
            'tax' => 'reports.advanced',
            'custom' => 'reports.advanced',
        ],
        
        // Dashboard modules/widgets
        'dashboard' => [
            'sales_overview' => 'sales.view',
            'inventory_status' => 'inventory.view',
            'customer_summary' => 'customers.view',
            'staff_activity' => 'users.view',
            'financial_metrics' => 'reports.advanced',
            'system_health' => 'system.settings',
        ],
        
        // POS quick actions
        'pos' => [
            'new_sale' => 'sales.create',
            'view_sales' => 'sales.view',
            'returns' => 'sales.returns',
            'discounts' => 'sales.discounts',
        ],
        
        // Product management actions
        'products' => [
            'view' => 'products.view',
            'create' => 'products.create',
            'edit' => 'products.edit',
            'import' => 'products.create',
            'export' => 'products.view',
            'barcodes' => 'products.view',
            'brands' => 'brands.view',
            'categories' => 'categories.view',
            'attributes' => 'products.manage',
        ],
        
        // Customer management actions
        'customers' => [
            'view' => 'customers.view',
            'create' => 'customers.create',
            'edit' => 'customers.edit',
            'loyalty' => 'loyalty.view',
            'bulk_sms' => 'customers.bulk_sms',
        ],
        
        // Inventory actions
        'inventory' => [
            'view' => 'inventory.view',
            'adjust' => 'inventory.adjust',
            'transfer' => 'inventory.transfer',
            'expiry' => 'inventory.expiry',
            'suppliers' => 'suppliers.view',
            'purchase_orders' => 'purchases.view',
        ],
        
        // User management actions
        'users' => [
            'view' => 'users.view',
            'create' => 'users.create',
            'roles' => 'roles.view',
            'permissions' => 'roles.manage',
        ],
        
        // System settings
        'system' => [
            'settings' => 'system.settings',
            'logs' => 'system.logs',
            'backup' => 'system.backup',
            'security' => 'system.settings',
        ],
    ];
    
    /**
     * Filter modules by permission
     */
    public static function filterModules(string $section, array $modules): array {
        $permissions = self::$modulePermissions[$section] ?? [];
        $filtered = [];
        
        foreach ($modules as $key => $module) {
            $requiredPermission = $permissions[$key] ?? $module['permission'] ?? null;
            
            if (!$requiredPermission || has_permission($requiredPermission) || is_super_admin()) {
                $filtered[$key] = $module;
            }
        }
        
        return $filtered;
    }
    
    /**
     * Check if user has access to a specific module
     */
    public static function canAccess(string $section, string $module): bool {
        $permissions = self::$modulePermissions[$section] ?? [];
        $requiredPermission = $permissions[$module] ?? null;
        
        if (!$requiredPermission) return true;
        return has_permission($requiredPermission) || is_super_admin();
    }
    
    /**
     * Enforce module access - redirects if not allowed
     */
    public static function enforce(string $section, string $module, string $redirect = 'dashboard/home.php'): void {
        if (!self::canAccess($section, $module)) {
            $_SESSION['error_message'] = 'You do not have permission to access this module.';
            redirect(base_url($redirect . '?error=unauthorized'));
        }
    }
    
    /**
     * Get filtered tabs with permission metadata
     */
    public static function getTabs(string $section, array $tabs): array {
        $permissions = self::$modulePermissions[$section] ?? [];
        $filtered = [];
        
        foreach ($tabs as $key => $tab) {
            $requiredPermission = $permissions[$key] ?? $tab['permission'] ?? null;
            
            if (!$requiredPermission || has_permission($requiredPermission) || is_super_admin()) {
                $tab['permission'] = $requiredPermission; // Store for reference
                $tab['has_access'] = true;
                $filtered[$key] = $tab;
            }
        }
        
        return $filtered;
    }
    
    /**
     * Get first accessible module as default
     */
    public static function getDefaultModule(string $section, string $requested = null): string {
        $permissions = self::$modulePermissions[$section] ?? [];
        
        // Check if requested module is accessible
        if ($requested && isset($permissions[$requested])) {
            if (has_permission($permissions[$requested]) || is_super_admin()) {
                return $requested;
            }
        }
        
        // Find first accessible module
        foreach ($permissions as $module => $permission) {
            if (has_permission($permission) || is_super_admin()) {
                return $module;
            }
        }
        
        return '';
    }
    
    /**
     * Get allowed modules as JavaScript array for client-side checking
     */
    public static function getAllowedModulesJs(string $section): string {
        $permissions = self::$modulePermissions[$section] ?? [];
        $allowed = [];
        
        foreach ($permissions as $module => $permission) {
            if (has_permission($permission) || is_super_admin()) {
                $allowed[] = $module;
            }
        }
        
        return json_encode($allowed);
    }
}
?>
