<?php
/**
 * Role-Based Access Control Implementation
 * Comprehensive permission management for POS system
 * 
 * @package Jakababa\Security
 * @version 1.0.0
 */

class RoleBasedAccess {
    private static $instance = null;
    private $permissions = [];
    private $role_hierarchy = [
        'super_admin' => 100,
        'developer' => 95,
        'support' => 90,
        'owner' => 100,
        'administrator' => 90,
        'admin' => 90,
        'manager' => 80,
        'branch_manager' => 80,
        'accountant' => 70,
        'inventory_manager' => 70,
        'hr_manager' => 70,
        'supervisor' => 70,
        'senior_cashier' => 65,
        'cashier' => 60,
        'inventory_clerk' => 55,
        'clerk' => 50,
        'customer_service' => 50,
        'waiter' => 45,
        'kitchen_staff' => 40,
        'delivery_rider' => 40,
        'viewer' => 30,
        'auditor_read_only' => 30
    ];
    
    private function __construct() {
        $this->loadPermissions();
    }
    
    public static function getInstance(): self {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Load user permissions from session
     */
    private function loadPermissions(): void {
        $this->permissions = $_SESSION['permissions'] ?? [];
        
        // Add role-based permissions
        $role = $_SESSION['role'] ?? '';
        if ($role) {
            $this->permissions = array_merge($this->permissions, $this->getRolePermissions($role));
        }
    }
    
    /**
     * Get permissions for a specific role
     */
    private function getRolePermissions(string $role): array {
        $role = strtolower(trim($role));
        $role_permissions = [
            'super_admin' => [
                'system.admin', 'system.config', 'system.logs',
                'tenants.*', 'users.*', 'roles.*', 'permissions.*',
                'billing.*', 'reports.*', 'audit.*'
            ],
            'developer' => [
                'system.admin', 'system.config', 'system.logs',
                'tenants.*', 'users.*', 'roles.*', 'permissions.*',
                'billing.*', 'reports.*', 'audit.*'
            ],
            'support' => [
                'tenants.view', 'users.view', 'reports.*', 'audit.*'
            ],
            'owner' => [
                'users.*', 'roles.*', 'permissions.*',
                'products.*', 'inventory.*', 'sales.*', 'customers.*',
                'purchases.*', 'expenses.*', 'branches.*', 'reports.*',
                'settings.*', 'billing.*'
            ],
            'administrator' => [
                'users.view', 'users.create', 'users.edit', 'users.delete',
                'roles.view', 'roles.create', 'roles.edit', 'roles.manage',
                'settings.view', 'settings.edit',
                'reports.*', 'audit.*',
                'products.*', 'inventory.*', 'sales.*', 'customers.*',
                'purchases.*', 'expenses.*', 'branches.*'
            ],
            'admin' => [
                'users.view', 'users.create', 'users.edit', 'users.delete',
                'roles.view', 'roles.create', 'roles.edit', 'roles.manage',
                'settings.view', 'settings.edit',
                'reports.*', 'audit.*',
                'products.*', 'inventory.*', 'sales.*', 'customers.*',
                'purchases.*', 'expenses.*', 'branches.*'
            ],
            'manager' => [
                'pos.view', 'pos.sell', 'pos.refund', 'pos.discount', 'pos.hold', 'pos.void',
                'sales.view', 'sales.manage', 'sales.returns',
                'products.view', 'products.create', 'products.edit',
                'inventory.view', 'inventory.adjust', 'inventory.transfer',
                'customers.view', 'customers.create', 'customers.edit',
                'users.view', 'users.create', 'users.edit',
                'reports.view', 'reports.sales', 'reports.inventory',
                'expenses.view', 'expenses.create', 'expenses.approve',
                'branches.view'
            ],
            'branch_manager' => [
                'pos.view', 'pos.sell', 'pos.refund', 'pos.discount', 'pos.hold',
                'sales.view', 'sales.manage', 'sales.returns',
                'products.view', 'products.create', 'products.edit',
                'inventory.view', 'inventory.adjust', 'inventory.transfer',
                'customers.view', 'customers.create', 'customers.edit',
                'staff.view', 'staff.manage',
                'reports.view', 'reports.sales', 'reports.inventory',
                'branches.view', 'branches.manage'
            ],
            'accountant' => [
                'sales.view', 'sales.export',
                'reports.view', 'reports.sales', 'reports.financial', 'reports.profit', 'reports.export',
                'expenses.view', 'expenses.create', 'expenses.edit', 'expenses.categories',
                'customers.view', 'customers.credit'
            ],
            'inventory_manager' => [
                'products.view', 'products.create', 'products.edit', 'products.delete', 'products.import', 'products.export', 'products.categories',
                'inventory.view', 'inventory.adjust', 'inventory.transfer', 'inventory.count', 'inventory.reorder', 'inventory.alerts',
                'purchases.view', 'purchases.create', 'purchases.approve',
                'suppliers.view', 'suppliers.create', 'suppliers.edit',
                'reports.inventory', 'reports.export'
            ],
            'hr_manager' => [
                'users.view', 'users.create', 'users.edit', 'users.delete', 'users.roles', 'users.attendance',
                'hr.view', 'hr.employees', 'hr.leaves', 'hr.payroll',
                'reports.view'
            ],
            'supervisor' => [
                'products.view', 'products.edit',
                'inventory.*', 'sales.*', 'customers.*',
                'purchases.view', 'expenses.view', 'reports.view',
                'branches.view'
            ],
            'senior_cashier' => [
                'pos.view', 'pos.sell', 'pos.refund', 'pos.discount', 'pos.hold', 'pos.order', 'pos.split',
                'sales.view', 'sales.returns',
                'customers.view', 'customers.create', 'customers.edit', 'customers.loyalty',
                'inventory.view',
                'reports.sales'
            ],
            'cashier' => [
                'pos.view', 'pos.sell', 'pos.hold', 'pos.order',
                'customers.view', 'customers.create',
                'sales.view'
            ],
            'inventory_clerk' => [
                'products.view',
                'inventory.view', 'inventory.count',
                'suppliers.view',
                'purchases.view'
            ],
            'clerk' => [
                'products.view', 'sales.view', 'customers.view',
                'inventory.view'
            ],
            'customer_service' => [
                'customers.view', 'customers.create', 'customers.edit',
                'sales.view', 'sales.returns',
                'pos.view',
                'reports.view'
            ],
            'waiter' => [
                'pos.view', 'pos.sell', 'pos.order', 'pos.table', 'pos.kitchen',
                'customers.view',
                'sales.view'
            ],
            'kitchen_staff' => [
                'pos.kitchen', 'pos.view',
                'kitchen.view', 'kitchen.ready'
            ],
            'delivery_rider' => [
                'delivery.view', 'delivery.update', 'delivery.track',
                'orders.view', 'orders.dispatch'
            ],
            'viewer' => [
                'reports.view', 'reports.sales',
                'sales.view',
                'products.view',
                'customers.view',
                'inventory.view'
            ],
            'auditor_read_only' => [
                'sales.view', 'sales.export',
                'products.view',
                'inventory.view',
                'customers.view',
                'reports.view', 'reports.sales', 'reports.inventory', 'reports.financial', 'reports.profit',
                'expenses.view',
                'users.view',
                'audit.view'
            ]
        ];
        
        return $role_permissions[$role] ?? [];
    }
    
    /**
     * Check if user has specific permission
     */
    public function hasPermission(string $permission): bool {
        // Super admin has all permissions
        if ($_SESSION['is_super_admin'] ?? false) {
            return true;
        }
        
        // Check exact permission
        if (in_array($permission, $this->permissions)) {
            return true;
        }
        
        // Check wildcard permissions
        foreach ($this->permissions as $perm) {
            if ($this->matchesWildcard($permission, $perm)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Check if permission matches wildcard pattern
     */
    private function matchesWildcard(string $permission, string $pattern): bool {
        if (strpos($pattern, '*') === false) {
            return false;
        }

        $regex = '/^' . str_replace('\\*', '.*', preg_quote($pattern, '/')) . '$/';
        return preg_match($regex, $permission) === 1;
    }
    
    /**
     * Check if user has any of the specified permissions
     */
    public function hasAnyPermission(array $permissions): bool {
        foreach ($permissions as $permission) {
            if ($this->hasPermission($permission)) {
                return true;
            }
        }
        return false;
    }
    
    /**
     * Check if user has all specified permissions
     */
    public function hasAllPermissions(array $permissions): bool {
        foreach ($permissions as $permission) {
            if (!$this->hasPermission($permission)) {
                return false;
            }
        }
        return true;
    }
    
    /**
     * Check if user can access specific module
     */
    public function canAccessModule(string $module): bool {
        $module_permissions = [
            'pos' => ['pos.access', 'sales.create'],
            'products' => ['products.view'],
            'inventory' => ['inventory.view'],
            'customers' => ['customers.view'],
            'reports' => ['reports.view'],
            'users' => ['users.view'],
            'settings' => ['settings.view'],
            'purchases' => ['purchases.view'],
            'expenses' => ['expenses.view'],
            'branches' => ['branches.view']
        ];
        
        $required_permissions = $module_permissions[$module] ?? [];
        return $this->hasAnyPermission($required_permissions);
    }
    
    /**
     * Get user's role level
     */
    public function getRoleLevel(): int {
        $role = $_SESSION['role'] ?? '';
        return $this->role_hierarchy[$role] ?? 0;
    }
    
    /**
     * Check if user can edit another user
     */
    public function canEditUser(int $target_user_id): bool {
        // Can't edit super admin
        if ($this->isUserSuperAdmin($target_user_id)) {
            return false;
        }
        
        // Super admin can edit anyone
        if ($_SESSION['is_super_admin'] ?? false) {
            return true;
        }
        
        // Can edit users with lower role level
        $target_role = $this->getUserRole($target_user_id);
        $target_level = $this->role_hierarchy[$target_role] ?? 0;
        
        return $this->getRoleLevel() > $target_level;
    }
    
    /**
     * Check if user is super admin
     */
    private function isUserSuperAdmin(int $user_id): bool {
        try {
            $pdo = $this->getDbConnection();
            $stmt = $pdo->prepare("SELECT role FROM users WHERE id = ? LIMIT 1");
            $stmt->execute([$user_id]);
            $role = $stmt->fetchColumn();
            
            return in_array($role, ['super_admin', 'Super Admin']);
        } catch (Exception $e) {
            error_log("Failed to check user role: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get user's role
     */
    private function getUserRole(int $user_id): string {
        try {
            $pdo = $this->getDbConnection();
            $stmt = $pdo->prepare("SELECT role FROM users WHERE id = ? LIMIT 1");
            $stmt->execute([$user_id]);
            return $stmt->fetchColumn() ?: '';
        } catch (Exception $e) {
            error_log("Failed to get user role: " . $e->getMessage());
            return '';
        }
    }
    
    /**
     * Filter data based on user permissions
     */
    public function filterData(array $data, string $permission): array {
        if (!$this->hasPermission($permission)) {
            return [];
        }
        
        // Additional filtering logic can be added here
        return $data;
    }
    
    /**
     * Get database connection
     */
    private function getDbConnection() {
        if (function_exists('get_db_connection')) {
            return get_db_connection();
        }
        
        throw new RuntimeException('Database connection not available');
    }
    
    /**
     * Reset singleton (for testing)
     */
    public static function reset(): void {
        self::$instance = null;
    }
    
    /**
     * Get all user permissions
     */
    public function getAllPermissions(): array {
        return $this->permissions;
    }
    
    /**
     * Check if user can perform action on resource
     */
    public function canPerformAction(string $action, string $resource): bool {
        $permission = $resource . '.' . $action;
        return $this->hasPermission($permission);
    }
}
