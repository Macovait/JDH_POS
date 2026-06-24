<?php
/**
 * Dynamic Role Manager
 * Automatically manages permissions for all roles including future ones
 * 
 * @package Jakababa\Security
 * @version 1.0.0
 */

class DynamicRoleManager {
    private static ?DynamicRoleManager $instance = null;
    private PDO $pdo;
    private array $permissionTemplates;
    
    private function __construct() {
        $this->pdo = get_db_connection();
        $this->initializePermissionTemplates();
    }
    
    public static function getInstance(): self {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Initialize permission templates for different role types
     */
    private function initializePermissionTemplates(): void {
        $this->permissionTemplates = [
            'super_admin' => [
                'pattern' => ['super_admin', 'administrator', 'admin'],
                'permissions' => 'all' // All permissions
            ],
            'developer' => [
                'pattern' => ['developer', 'dev'],
                'permissions' => 'all'
            ],
            'support' => [
                'pattern' => ['support', 'helpdesk', 'technical_support'],
                'permissions' => [
                    'tenants.view', 'users.view', 'reports.*', 'audit.*',
                    'profile.view', 'profile.edit'
                ]
            ],
            'owner' => [
                'pattern' => ['owner', 'founder', 'ceo'],
                'permissions' => 'all_except_system'
            ],
            'admin' => [
                'pattern' => ['admin', 'administrator'],
                'permissions' => 'all_except_system'
            ],
            'manager' => [
                'pattern' => ['manager', 'store_manager'],
                'permissions' => [
                    'dashboard.view', 'pos.view', 'pos.sell', 'pos.refund', 'pos.discount', 'pos.hold', 'pos.void',
                    'sales.view', 'sales.manage', 'sales.returns',
                    'products.view', 'products.create', 'products.edit',
                    'inventory.view', 'inventory.adjust', 'inventory.transfer',
                    'customers.view', 'customers.create', 'customers.edit',
                    'users.view', 'users.create', 'users.edit',
                    'reports.view', 'reports.sales', 'reports.inventory',
                    'expenses.view', 'expenses.create', 'expenses.approve',
                    'branches.view',
                    'profile.view', 'profile.edit'
                ]
            ],
            'branch_manager' => [
                'pattern' => ['branch_manager', 'branch manager'],
                'permissions' => [
                    'dashboard.view', 'pos.view', 'pos.sell', 'pos.refund', 'pos.discount', 'pos.hold',
                    'sales.view', 'sales.manage', 'sales.returns',
                    'products.view', 'products.create', 'products.edit',
                    'inventory.view', 'inventory.adjust', 'inventory.transfer',
                    'customers.view', 'customers.create', 'customers.edit',
                    'staff.view', 'staff.manage',
                    'reports.view', 'reports.sales', 'reports.inventory',
                    'branches.view', 'branches.manage',
                    'profile.view', 'profile.edit'
                ]
            ],
            'accountant' => [
                'pattern' => ['accountant', 'finance', 'bookkeeper'],
                'permissions' => [
                    'sales.view', 'sales.export',
                    'reports.view', 'reports.sales', 'reports.financial', 'reports.profit', 'reports.export',
                    'expenses.view', 'expenses.create', 'expenses.edit', 'expenses.categories',
                    'customers.view', 'customers.credit',
                    'profile.view', 'profile.edit'
                ]
            ],
            'inventory_manager' => [
                'pattern' => ['inventory_manager', 'inventory manager', 'warehouse_manager'],
                'permissions' => [
                    'products.view', 'products.create', 'products.edit', 'products.delete', 'products.import', 'products.export', 'products.categories',
                    'inventory.view', 'inventory.adjust', 'inventory.transfer', 'inventory.count', 'inventory.reorder', 'inventory.alerts',
                    'purchases.view', 'purchases.create', 'purchases.approve',
                    'suppliers.view', 'suppliers.create', 'suppliers.edit',
                    'reports.inventory', 'reports.export',
                    'profile.view', 'profile.edit'
                ]
            ],
            'hr_manager' => [
                'pattern' => ['hr_manager', 'hr manager', 'human_resources'],
                'permissions' => [
                    'users.view', 'users.create', 'users.edit', 'users.delete', 'users.roles', 'users.attendance',
                    'hr.view', 'hr.employees', 'hr.leaves', 'hr.payroll',
                    'reports.view',
                    'profile.view', 'profile.edit'
                ]
            ],
            'supervisor' => [
                'pattern' => ['supervisor', 'team_lead', 'shift_supervisor'],
                'permissions' => [
                    'dashboard.view', 'pos.view', 'pos.sell', 'sales.view', 'sales.returns',
                    'products.view', 'products.edit',
                    'inventory.view', 'inventory.adjust',
                    'customers.view', 'customers.create', 'customers.edit',
                    'reports.view', 'reports.sales', 'reports.inventory',
                    'users.view',
                    'profile.view', 'profile.edit'
                ]
            ],
            'senior_cashier' => [
                'pattern' => ['senior_cashier', 'senior cashier', 'lead_cashier'],
                'permissions' => [
                    'dashboard.view', 'pos.view', 'pos.sell', 'pos.refund', 'pos.discount', 'pos.hold', 'pos.order', 'pos.split',
                    'sales.view', 'sales.returns',
                    'customers.view', 'customers.create', 'customers.edit', 'customers.loyalty',
                    'inventory.view',
                    'reports.sales',
                    'profile.view', 'profile.edit'
                ]
            ],
            'cashier' => [
                'pattern' => ['cashier', 'sales', 'pos'],
                'permissions' => [
                    'dashboard.view', 'pos.view', 'pos.sell', 'pos.hold', 'pos.order',
                    'products.view',
                    'customers.view', 'customers.create',
                    'inventory.view',
                    'sales.view',
                    'profile.view', 'profile.edit'
                ]
            ],
            'inventory_clerk' => [
                'pattern' => ['inventory_clerk', 'inventory clerk', 'stock_clerk'],
                'permissions' => [
                    'dashboard.view', 'products.view',
                    'inventory.view', 'inventory.count',
                    'suppliers.view',
                    'purchases.view',
                    'profile.view', 'profile.edit'
                ]
            ],
            'customer_service' => [
                'pattern' => ['customer_service', 'customer service', 'support_agent'],
                'permissions' => [
                    'customers.view', 'customers.create', 'customers.edit',
                    'sales.view', 'sales.returns',
                    'pos.view',
                    'reports.view',
                    'profile.view', 'profile.edit'
                ]
            ],
            'waiter' => [
                'pattern' => ['waiter', 'server', 'restaurant_staff'],
                'permissions' => [
                    'pos.view', 'pos.sell', 'pos.order', 'pos.table', 'pos.kitchen',
                    'customers.view',
                    'sales.view',
                    'profile.view', 'profile.edit'
                ]
            ],
            'kitchen_staff' => [
                'pattern' => ['kitchen_staff', 'kitchen staff', 'cook', 'chef'],
                'permissions' => [
                    'pos.kitchen', 'pos.view',
                    'kitchen.view', 'kitchen.ready',
                    'profile.view', 'profile.edit'
                ]
            ],
            'delivery_rider' => [
                'pattern' => ['delivery_rider', 'delivery rider', 'delivery', 'rider'],
                'permissions' => [
                    'delivery.view', 'delivery.update', 'delivery.track',
                    'orders.view', 'orders.dispatch',
                    'profile.view', 'profile.edit'
                ]
            ],
            'auditor_read_only' => [
                'pattern' => ['auditor', 'read_only', 'read only'],
                'permissions' => [
                    'sales.view', 'sales.export',
                    'products.view',
                    'inventory.view',
                    'customers.view',
                    'reports.view', 'reports.sales', 'reports.inventory', 'reports.financial', 'reports.profit',
                    'expenses.view',
                    'users.view',
                    'audit.view',
                    'profile.view'
                ]
            ],
            'clerk' => [
                'pattern' => ['clerk', 'assistant', 'staff'],
                'permissions' => [
                    'dashboard.view', 'products.view', 'customers.view', 'inventory.view',
                    'profile.view', 'profile.edit'
                ]
            ],
            'viewer' => [
                'pattern' => ['viewer', 'read_only', 'guest'],
                'permissions' => [
                    'dashboard.view', 'products.view', 'customers.view', 'inventory.view',
                    'reports.view', 'reports.sales',
                    'profile.view'
                ]
            ]
        ];
    }
    
    /**
     * Automatically assign permissions to a role based on its name
     */
    public function autoAssignPermissions(int $roleId, string $roleName, int $tenantId): bool {
        try {
            // Get all available permissions for this tenant
            $stmt = $this->pdo->prepare('
                SELECT id, code FROM permissions 
                WHERE tenant_id = ? AND deleted_at IS NULL
            ');
            $stmt->execute([$tenantId]);
            $allPermissions = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
            
            // Determine which template to use
            $roleNameLower = strtolower(trim($roleName));
            $permissionsToAssign = $this->getPermissionsForRole($roleNameLower, array_keys($allPermissions));
            
            // Clear existing permissions for this role
            $stmt = $this->pdo->prepare('
                DELETE FROM role_permissions WHERE role_id = ? AND tenant_id = ?
            ');
            $stmt->execute([$roleId, $tenantId]);
            
            // Assign new permissions
            $assignedCount = 0;
            foreach ($permissionsToAssign as $permissionCode) {
                if (isset($allPermissions[$permissionCode])) {
                    $stmt = $this->pdo->prepare('
                        INSERT IGNORE INTO role_permissions (tenant_id, role_id, permission_id) 
                        VALUES (?, ?, ?)
                    ');
                    $stmt->execute([$tenantId, $roleId, $allPermissions[$permissionCode]]);
                    $assignedCount++;
                }
            }
            
            // Log the assignment
            error_log("Auto-assigned {$assignedCount} permissions to role '{$roleName}' (ID: {$roleId}) in tenant {$tenantId}");
            
            return $assignedCount > 0;
            
        } catch (Exception $e) {
            error_log("Error auto-assigning permissions: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get permissions for a role based on name patterns
     */
    private function getPermissionsForRole(string $roleName, array $availablePermissions): array {
        // Check for exact matches first
        foreach ($this->permissionTemplates as $templateName => $template) {
            if (in_array($roleName, $template['pattern'], true)) {
                if ($template['permissions'] === 'all') {
                    return $availablePermissions;
                } elseif ($template['permissions'] === 'all_except_system') {
                    return array_filter($availablePermissions, fn($perm) => 
                        !str_starts_with($perm, 'system.') && !str_starts_with($perm, 'billing.')
                    );
                } else {
                    return array_intersect($template['permissions'], $availablePermissions);
                }
            }
        }
        
        // Check for partial matches
        foreach ($this->permissionTemplates as $templateName => $template) {
            foreach ($template['pattern'] as $pattern) {
                if (strpos($roleName, $pattern) !== false) {
                    if ($template['permissions'] === 'all') {
                        return $availablePermissions;
                    } elseif ($template['permissions'] === 'all_except_system') {
                        return array_filter($availablePermissions, fn($perm) => 
                            !str_starts_with($perm, 'system.') && !str_starts_with($perm, 'billing.')
                        );
                    } else {
                        return array_intersect($template['permissions'], $availablePermissions);
                    }
                }
            }
        }
        
        // Default to viewer permissions
        return $this->permissionTemplates['viewer']['permissions'];
    }
    
    /**
     * Create a new role and automatically assign permissions
     */
    public function createRoleWithPermissions(string $roleName, int $tenantId, string $description = ''): int {
        try {
            $this->pdo->beginTransaction();
            
            // Create the role
            $stmt = $this->pdo->prepare('
                INSERT INTO roles (tenant_id, name, description, created_at, updated_at) 
                VALUES (?, ?, ?, NOW(), NOW())
            ');
            $stmt->execute([$tenantId, $roleName, $description]);
            $roleId = $this->pdo->lastInsertId();
            
            // Auto-assign permissions
            $this->autoAssignPermissions($roleId, $roleName, $tenantId);
            
            $this->pdo->commit();
            
            error_log("Created new role '{$roleName}' with auto-assigned permissions in tenant {$tenantId}");
            
            return $roleId;
            
        } catch (Exception $e) {
            $this->pdo->rollBack();
            error_log("Error creating role with permissions: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Update permissions for an existing role
     */
    public function updateRolePermissions(int $roleId, int $tenantId): bool {
        try {
            // Get role name
            $stmt = $this->pdo->prepare('
                SELECT name FROM roles WHERE id = ? AND tenant_id = ? AND deleted_at IS NULL LIMIT 1
            ');
            $stmt->execute([$roleId, $tenantId]);
            $roleName = $stmt->fetchColumn();
            
            if (!$roleName) {
                return false;
            }
            
            return $this->autoAssignPermissions($roleId, $roleName, $tenantId);
            
        } catch (Exception $e) {
            error_log("Error updating role permissions: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get all permissions for a role
     */
    public function getRolePermissions(int $roleId, int $tenantId): array {
        try {
            $stmt = $this->pdo->prepare('
                SELECT p.code FROM permissions p
                JOIN role_permissions rp ON rp.permission_id = p.id AND rp.tenant_id = p.tenant_id
                WHERE rp.role_id = ? AND rp.tenant_id = ? AND p.deleted_at IS NULL
                ORDER BY p.code
            ');
            $stmt->execute([$roleId, $tenantId]);
            return $stmt->fetchAll(PDO::FETCH_COLUMN);
            
        } catch (Exception $e) {
            error_log("Error getting role permissions: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Check if a role has a specific permission
     */
    public function roleHasPermission(int $roleId, int $tenantId, string $permission): bool {
        try {
            $stmt = $this->pdo->prepare('
                SELECT COUNT(*) FROM role_permissions rp
                JOIN permissions p ON rp.permission_id = p.id AND rp.tenant_id = p.tenant_id
                WHERE rp.role_id = ? AND rp.tenant_id = ? AND p.code = ? AND p.deleted_at IS NULL
            ');
            $stmt->execute([$roleId, $tenantId, $permission]);
            return (int) $stmt->fetchColumn() > 0;
            
        } catch (Exception $e) {
            error_log("Error checking role permission: " . $e->getMessage());
            return false;
        }
    }
}
?>
