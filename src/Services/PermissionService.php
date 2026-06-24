<?php
/**
 * Centralized Permissions Engine
 * Manages roles, permissions, and access control in a database-driven manner
 */

declare(strict_types=1);

namespace JDH_POS\Services;

use PDO;
use PDOException;
use Exception;

class PermissionService
{
    private PDO $db;
    private array $permissionsCache = [];
    private array $rolesCache = [];

    // Predefined permissions structure
    private const PERMISSIONS = [
        'users' => [
            'view' => 'View users',
            'create' => 'Create users',
            'edit' => 'Edit users',
            'delete' => 'Delete users',
            'manage' => 'Manage users',
            'manage_roles' => 'Manage user roles',
            'roles' => 'Assign roles'
        ],
        'roles' => [
            'view' => 'View roles',
            'create' => 'Create roles',
            'edit' => 'Edit roles',
            'delete' => 'Delete roles',
            'manage' => 'Manage roles'
        ],
        'products' => [
            'view' => 'View products',
            'create' => 'Create products',
            'edit' => 'Edit products',
            'delete' => 'Delete products',
            'import' => 'Import products',
            'export' => 'Export products',
            'manage' => 'Manage products',
            'manage_categories' => 'Manage categories'
        ],
        'categories' => [
            'view' => 'View categories',
            'create' => 'Create categories',
            'edit' => 'Edit categories',
            'delete' => 'Delete categories',
            'manage' => 'Manage categories'
        ],
        'inventory' => [
            'view' => 'View inventory',
            'adjust' => 'Adjust stock levels',
            'transfers' => 'Manage stock transfers',
            'reports' => 'View inventory reports',
            'manage' => 'Manage inventory'
        ],
        'sales' => [
            'view' => 'View sales',
            'create' => 'Create sales',
            'edit' => 'Edit sales',
            'refund' => 'Process refunds',
            'returns' => 'Process returns',
            'discounts' => 'Apply discounts',
            'manage' => 'Manage sales'
        ],
        'pos' => [
            'view' => 'View POS',
            'access' => 'Access POS',
            'sell' => 'Process sales',
            'hold' => 'Hold sales',
            'order' => 'Manage orders',
            'refund' => 'POS refunds',
            'discount' => 'Apply POS discounts',
            'void' => 'Void transactions'
        ],
        'customers' => [
            'view' => 'View customers',
            'create' => 'Create customers',
            'edit' => 'Edit customers',
            'delete' => 'Delete customers',
            'loyalty' => 'Manage loyalty program',
            'manage' => 'Manage customers'
        ],
        'reports' => [
            'view' => 'View reports',
            'export' => 'Export reports',
            'sales' => 'View sales reports',
            'financial' => 'View financial reports',
            'inventory' => 'View inventory reports',
            'profit' => 'View profit reports',
            'advanced' => 'Access advanced analytics'
        ],
        'settings' => [
            'view' => 'View settings',
            'edit' => 'Edit settings',
            'system' => 'System administration'
        ],
        'branches' => [
            'view' => 'View branches',
            'create' => 'Create branches',
            'edit' => 'Edit branches',
            'delete' => 'Delete branches',
            'manage' => 'Manage branches'
        ],
        'expenses' => [
            'view' => 'View expenses',
            'create' => 'Create expenses',
            'edit' => 'Edit expenses',
            'manage' => 'Manage expenses',
            'categories' => 'Manage expense categories'
        ],
        'purchases' => [
            'view' => 'View purchases',
            'create' => 'Create purchases',
            'edit' => 'Edit purchases',
            'approve' => 'Approve purchases',
            'manage' => 'Manage purchases'
        ],
        'quotations' => [
            'view' => 'View quotations',
            'create' => 'Create quotations',
            'edit' => 'Edit quotations',
            'manage' => 'Manage quotations'
        ],
        'dashboard' => [
            'view' => 'View dashboard'
        ],
        'audit' => [
            'view' => 'View audit logs'
        ]
    ];

    public function __construct(PDO $db)
    {
        $this->db = $db;
        $this->initializePermissions();
    }

    /**
     * Initialize default permissions in database
     */
    private function initializePermissions(): void
    {
        try {
            foreach (self::PERMISSIONS as $module => $actions) {
                foreach ($actions as $action => $description) {
                    $code = "{$module}.{$action}";

                    // Check if permission exists
                    $stmt = $this->db->prepare("SELECT id FROM permissions WHERE code = ?");
                    $stmt->execute([$code]);

                    if (!$stmt->fetch()) {
                        // Create permission
                        $stmt = $this->db->prepare("
                            INSERT INTO permissions (code, description, created_at)
                            VALUES (?, ?, NOW())
                        ");
                        $stmt->execute([$code, $description]);
                    }
                }
            }
        } catch (PDOException $e) {
            error_log("PermissionService::initializePermissions error: " . $e->getMessage());
        }
    }

    /**
     * Get all permissions
     */
    public function getAllPermissions(): array
    {
        if (empty($this->permissionsCache)) {
            try {
                $stmt = $this->db->query("SELECT * FROM permissions ORDER BY code");
                $this->permissionsCache = $stmt->fetchAll(PDO::FETCH_ASSOC);
            } catch (PDOException $e) {
                error_log("PermissionService::getAllPermissions error: " . $e->getMessage());
                return [];
            }
        }

        return $this->permissionsCache;
    }

    /**
     * Get permissions grouped by module
     */
    public function getPermissionsByModule(): array
    {
        $permissions = $this->getAllPermissions();
        $grouped = [];

        foreach ($permissions as $permission) {
            $parts = explode('.', $permission['code'], 2);
            if (count($parts) === 2) {
                $grouped[$parts[0]][] = $permission;
            }
        }

        return $grouped;
    }

    /**
     * Create or update role
     */
    public function saveRole(array $data): bool
    {
        try {
            $this->db->beginTransaction();

            if (!empty($data['id'])) {
                // Update existing role
                $stmt = $this->db->prepare("
                    UPDATE roles
                    SET name = ?, updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([$data['name'], $data['id']]);
                $roleId = $data['id'];
            } else {
                // Create new role
                $stmt = $this->db->prepare("
                    INSERT INTO roles (name, created_at)
                    VALUES (?, NOW())
                ");
                $stmt->execute([$data['name']]);
                $roleId = $this->db->lastInsertId();
            }

            // Update role permissions
            if (isset($data['permissions'])) {
                // Clear existing permissions
                $stmt = $this->db->prepare("DELETE FROM role_permissions WHERE role_id = ?");
                $stmt->execute([$roleId]);

                // Add new permissions
                if (!empty($data['permissions'])) {
                    $stmt = $this->db->prepare("
                        INSERT INTO role_permissions (role_id, permission_id)
                        VALUES (?, ?)
                    ");

                    foreach ($data['permissions'] as $permissionId) {
                        $stmt->execute([$roleId, $permissionId]);
                    }
                }
            }

            $this->db->commit();

            // Clear cache
            $this->rolesCache = [];

            return true;

        } catch (PDOException $e) {
            $this->db->rollBack();
            error_log("PermissionService::saveRole error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get all roles with their permissions
     */
    public function getAllRoles(): array
    {
        if (empty($this->rolesCache)) {
            try {
                $stmt = $this->db->query("
                    SELECT r.*, GROUP_CONCAT(p.code) as permissions
                    FROM roles r
                    LEFT JOIN role_permissions rp ON r.id = rp.role_id
                    LEFT JOIN permissions p ON rp.permission_id = p.id
                    GROUP BY r.id
                    ORDER BY r.name
                ");
                $roles = $stmt->fetchAll(PDO::FETCH_ASSOC);

                foreach ($roles as &$role) {
                    $role['permissions'] = $role['permissions'] ? explode(',', $role['permissions']) : [];
                }

                $this->rolesCache = $roles;

            } catch (PDOException $e) {
                error_log("PermissionService::getAllRoles error: " . $e->getMessage());
                return [];
            }
        }

        return $this->rolesCache;
    }

    /**
     * Get role by ID with permissions
     */
    public function getRole(int $roleId): ?array
    {
        try {
            $stmt = $this->db->prepare("
                SELECT r.*, GROUP_CONCAT(p.id) as permission_ids, GROUP_CONCAT(p.code) as permission_codes
                FROM roles r
                LEFT JOIN role_permissions rp ON r.id = rp.role_id
                LEFT JOIN permissions p ON rp.permission_id = p.id
                WHERE r.id = ?
                GROUP BY r.id
            ");
            $stmt->execute([$roleId]);
            $role = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($role) {
                $role['permission_ids'] = $role['permission_ids'] ? explode(',', $role['permission_ids']) : [];
                $role['permission_codes'] = $role['permission_codes'] ? explode(',', $role['permission_codes']) : [];
            }

            return $role;

        } catch (PDOException $e) {
            error_log("PermissionService::getRole error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Delete role
     */
    public function deleteRole(int $roleId): bool
    {
        try {
            // Check if role is in use
            $stmt = $this->db->prepare("SELECT COUNT(*) FROM users WHERE role_id = ?");
            $stmt->execute([$roleId]);
            if ($stmt->fetchColumn() > 0) {
                throw new Exception("Cannot delete role that is assigned to users");
            }

            $this->db->beginTransaction();

            // Delete role permissions
            $stmt = $this->db->prepare("DELETE FROM role_permissions WHERE role_id = ?");
            $stmt->execute([$roleId]);

            // Delete role
            $stmt = $this->db->prepare("DELETE FROM roles WHERE id = ?");
            $stmt->execute([$roleId]);

            $this->db->commit();

            // Clear cache
            $this->rolesCache = [];

            return true;

        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("PermissionService::deleteRole error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Assign role to user
     */
    public function assignRoleToUser(int $userId, int $roleId): bool
    {
        try {
            // Verify role exists
            $stmt = $this->db->prepare("SELECT id FROM roles WHERE id = ?");
            $stmt->execute([$roleId]);
            if (!$stmt->fetch()) {
                return false;
            }

            $stmt = $this->db->prepare("UPDATE users SET role_id = ? WHERE id = ?");
            $stmt->execute([$roleId, $userId]);

            return true;

        } catch (PDOException $e) {
            error_log("PermissionService::assignRoleToUser error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Check if user has permission
     */
    public function userHasPermission(int $userId, string $module, string $action): bool
    {
        try {
            $stmt = $this->db->prepare("
                SELECT COUNT(*) as count
                FROM users u
                JOIN role_permissions rp ON u.role_id = rp.role_id
                JOIN permissions p ON rp.permission_id = p.id
                WHERE u.id = ? AND p.code = ? AND u.status = 1
            ");
            $stmt->execute([$userId, "{$module}.{$action}"]);

            return $stmt->fetchColumn() > 0;

        } catch (PDOException $e) {
            error_log("PermissionService::userHasPermission error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get user permissions
     */
    public function getUserPermissions(int $userId): array
    {
        try {
            $stmt = $this->db->prepare("
                SELECT p.code
                FROM users u
                JOIN role_permissions rp ON u.role_id = rp.role_id
                JOIN permissions p ON rp.permission_id = p.id
                WHERE u.id = ? AND u.status = 1
            ");
            $stmt->execute([$userId]);
            $permissions = $stmt->fetchAll(PDO::FETCH_COLUMN);

            // Group by module
            $grouped = [];
            foreach ($permissions as $permission) {
                $parts = explode('.', $permission, 2);
                if (count($parts) === 2) {
                    $grouped[$parts[0]][] = $parts[1];
                }
            }

            return $grouped;

        } catch (PDOException $e) {
            error_log("PermissionService::getUserPermissions error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Create default roles
     */
    public function createDefaultRoles(): void
    {
        $defaultRoles = [
            [
                'name' => 'Administrator',
                'permissions' => [
                    'users.view', 'users.create', 'users.edit', 'users.delete', 'users.manage_roles',
                    'products.*', 'inventory.*', 'sales.*', 'customers.*', 'reports.*',
                    'settings.*', 'branches.*'
                ]
            ],
            [
                'name' => 'Manager',
                'permissions' => [
                    'users.view', 'users.create', 'users.edit',
                    'products.view', 'products.create', 'products.edit', 'products.manage_categories',
                    'inventory.view', 'inventory.adjust', 'inventory.transfers', 'inventory.reports',
                    'sales.view', 'sales.create', 'sales.edit', 'sales.refund', 'sales.discounts',
                    'customers.*', 'reports.view', 'reports.export',
                    'settings.view', 'branches.view'
                ]
            ],
            [
                'name' => 'Cashier',
                'permissions' => [
                    'products.view', 'inventory.view',
                    'sales.view', 'sales.create', 'customers.view', 'customers.create'
                ]
            ]
        ];

        foreach ($defaultRoles as $roleData) {
            $this->createRoleWithPermissions($roleData);
        }
    }

    /**
     * Create role with permissions
     */
    private function createRoleWithPermissions(array $roleData): void
    {
        try {
            // Check if role exists
            $stmt = $this->db->prepare("SELECT id FROM roles WHERE name = ?");
            $stmt->execute([$roleData['name']]);

            if ($stmt->fetch()) {
                return; // Role already exists
            }

            $this->db->beginTransaction();

            // Create role
            $stmt = $this->db->prepare("INSERT INTO roles (name, created_at) VALUES (?, NOW())");
            $stmt->execute([$roleData['name']]);
            $roleId = $this->db->lastInsertId();

            // Add permissions
            $stmt = $this->db->prepare("
                INSERT INTO role_permissions (role_id, permission_id)
                SELECT ?, id FROM permissions WHERE code = ?
            ");

            foreach ($roleData['permissions'] as $permission) {
                if (str_ends_with($permission, '.*')) {
                    // Wildcard permission - add all permissions for module
                    $module = str_replace('.*', '', $permission);
                    $stmt = $this->db->prepare("
                        INSERT INTO role_permissions (role_id, permission_id)
                        SELECT ?, id FROM permissions WHERE code LIKE ?
                    ");
                    $stmt->execute([$roleId, $module . '.%']);
                } else {
                    $stmt->execute([$roleId, $permission]);
                }
            }

            $this->db->commit();

        } catch (PDOException $e) {
            $this->db->rollBack();
            error_log("PermissionService::createRoleWithPermissions error: " . $e->getMessage());
        }
    }
}