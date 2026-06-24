<?php
/**
 * Roles Model - Zero-Trust Implementation
 * Handles role management with controlled cross-tenant access
 */

require_once dirname(__DIR__) . '/src/TenantContext.php';
require_once dirname(__DIR__) . '/src/BaseModel.php';

class RolesModel extends BaseModel {
    protected $table = 'roles';

    public function getRoles(array $filters = []): array {
        // SaaS admin can see all roles, regular tenants see only their assigned roles
        if ($this->context->allowCrossTenant()) {
            $stmt = $this->pdo->prepare("SELECT * FROM roles WHERE deleted_at IS NULL ORDER BY name");
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            // Regular tenants see roles available to their company
            $stmt = $this->pdo->prepare("
                SELECT r.* FROM roles r
                WHERE r.deleted_at IS NULL AND r.active = 1
                AND (r.tenant_id = ? OR r.tenant_id IS NULL OR r.is_system = 1)
                ORDER BY r.name
            ");
            $stmt->execute([$this->context->getCompanyId()]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    }

    public function createRole(array $roleData): int {
        // Validate permissions structure
        $this->validatePermissions($roleData['permissions'] ?? []);

        // SaaS admin can create global roles, regular tenants can create company-specific roles
        if (!$this->context->allowCrossTenant()) {
            // Regular tenants can only create roles for their company
            $roleData['tenant_id'] = $this->context->getCompanyId();
        }

        // Set audit trail
        $roleData['created_by'] = $this->context->getUserId();

        return $this->insert($roleData);
    }

    public function updateRole(int $roleId, array $roleData): void {
        // Verify role exists and check permissions
        $currentRole = $this->find($roleId);
        if (!$currentRole) {
            throw new Exception('Role not found');
        }

        // SaaS admin can update any role, regular tenants only their company roles
        if (!$this->context->allowCrossTenant()) {
            if ($currentRole['tenant_id'] != $this->context->getCompanyId() && !$currentRole['is_system']) {
                throw new Exception('Cannot modify roles from other companies');
            }
        }

        // Prevent modification of system roles by non-SaaS admins
        if ($currentRole['is_system'] && !$this->context->allowCrossTenant()) {
            throw new Exception('System roles can only be modified by SaaS administrators');
        }

        // Validate permissions structure
        $this->validatePermissions($roleData['permissions'] ?? []);

        // Set audit trail
        $roleData['updated_by'] = $this->context->getUserId();

        $stmt = $this->pdo->prepare("UPDATE roles SET " . implode('=?, ', array_keys($roleData)) . "=?, updated_at = NOW() WHERE id = ?");
        $params = array_values($roleData);
        $params[] = $roleId;
        $this->applyTenantScope($stmt, $params);
        $stmt->execute($params);
    }

    public function deleteRole(int $roleId): void {
        // Verify role exists and check permissions
        $currentRole = $this->find($roleId);
        if (!$currentRole) {
            throw new Exception('Role not found');
        }

        // Prevent deletion of system roles
        if ($currentRole['is_system']) {
            throw new Exception('System roles cannot be deleted');
        }

        // SaaS admin can delete any role, regular tenants only their company roles
        if (!$this->context->allowCrossTenant()) {
            if ($currentRole['tenant_id'] != $this->context->getCompanyId()) {
                throw new Exception('Cannot delete roles from other companies');
            }
        }

        // Soft delete
        $stmt = $this->pdo->prepare("UPDATE roles SET deleted_at = ?, updated_by = ? WHERE id = ?");
        $params = [date('Y-m-d H:i:s'), $this->context->getUserId(), $roleId];
        $this->applyTenantScope($stmt, $params);
        $stmt->execute($params);
    }

    public function checkSlugExists(string $slug, int $excludeRoleId = null): bool {
        $sql = "SELECT COUNT(*) FROM roles WHERE slug = ? AND deleted_at IS NULL";
        $params = [$slug];

        if ($excludeRoleId) {
            $sql .= " AND id != ?";
            $params[] = $excludeRoleId;
        }

        $stmt = $this->pdo->prepare($sql);
        $this->applyTenantScope($stmt, $params);
        $stmt->execute($params);

        return $stmt->fetchColumn() > 0;
    }

    public function getPermissionModules(): array {
        // Permission modules configuration - same for all tenants
        return [
            'companies' => ['label' => 'Companies', 'icon' => 'fa-building', 'actions' => ['view', 'create', 'edit', 'delete']],
            'users'     => ['label' => 'Users',     'icon' => 'fa-users',   'actions' => ['view', 'create', 'edit', 'delete']],
            'products'  => ['label' => 'Products',  'icon' => 'fa-box',     'actions' => ['view', 'create', 'edit', 'delete']],
            'reports'   => ['label' => 'Reports',   'icon' => 'fa-chart-bar','actions' => ['view', 'export']],
            'sales'     => ['label' => 'Sales',     'icon' => 'fa-cart-shopping','actions' => ['view', 'create', 'refund']],
            'inventory' => ['label' => 'Inventory', 'icon' => 'fa-warehouse','actions' => ['view', 'edit']],
            'settings'  => ['label' => 'Settings',  'icon' => 'fa-gear',    'actions' => ['view', 'edit']],
        ];
    }

    private function validatePermissions(array $permissions): void {
        $validModules = array_keys($this->getPermissionModules());

        foreach ($permissions as $module => $actions) {
            if (!in_array($module, $validModules)) {
                throw new Exception("Invalid permission module: {$module}");
            }

            if (!is_array($actions)) {
                throw new Exception("Invalid actions format for module: {$module}");
            }

            $validActions = $this->getPermissionModules()[$module]['actions'];
            foreach ($actions as $action) {
                if (!in_array($action, $validActions)) {
                    throw new Exception("Invalid action '{$action}' for module '{$module}'");
                }
            }
        }
    }

    public function assignRoleToUser(int $userId, int $roleId): void {
        // Verify user and role exist and are accessible
        $user = $this->findUser($userId);
        $role = $this->find($roleId);

        if (!$user) {
            throw new Exception('User not found');
        }
        if (!$role) {
            throw new Exception('Role not found');
        }

        // SaaS admin can assign any role to any user
        // Regular tenants can only assign roles within their company
        if (!$this->context->allowCrossTenant()) {
            if ($user['tenant_id'] != $this->context->getCompanyId()) {
                throw new Exception('Cannot assign roles to users from other companies');
            }
            if ($role['tenant_id'] && $role['tenant_id'] != $this->context->getCompanyId()) {
                throw new Exception('Cannot assign roles from other companies');
            }
        }

        // Update user role assignment
        $stmt = $this->pdo->prepare("UPDATE users SET role_id = ?, updated_by = ? WHERE id = ?");
        $stmt->execute([$roleId, $this->context->getUserId(), $userId]);
    }

    private function findUser(int $userId): ?array {
        // Users model query - tenant scoped
        $stmt = $this->pdo->prepare("SELECT * FROM users WHERE id = ? AND deleted_at IS NULL");
        $params = [$userId];
        $this->applyTenantScope($stmt, $params);
        $stmt->execute($params);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}