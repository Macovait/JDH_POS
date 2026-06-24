<?php
/**
 * Users Model - Zero-Trust Implementation
 * Handles user management with controlled cross-tenant access
 */

require_once dirname(__DIR__) . '/src/TenantContext.php';
require_once dirname(__DIR__) . '/src/BaseModel.php';

class UsersModel extends BaseModel {
    protected $table = 'users';

    public function getUsers(array $filters = [], int $limit = 20, int $offset = 0): array {
        // SaaS admin can see all users, regular tenants see only their own
        $sql = "SELECT u.*, t.name AS company_name
                FROM users u
                LEFT JOIN pos_tenants t ON u.tenant_id = t.id
                WHERE u.deleted_at IS NULL";

        $params = [];

        // Apply filters
        if (!empty($filters['search'])) {
            $sql .= " AND (u.name LIKE ? OR u.email LIKE ?)";
            $params[] = '%' . $filters['search'] . '%';
            $params[] = '%' . $filters['search'] . '%';
        }

        if (!empty($filters['tenant_id'])) {
            $sql .= " AND u.tenant_id = ?";
            $params[] = $filters['tenant_id'];
        }

        if (!empty($filters['status'])) {
            $sql .= " AND u.status = ?";
            $params[] = $filters['status'];
        }

        // Apply tenant scoping
        $stmt = $this->pdo->prepare($sql);
        $this->applyTenantScope($stmt, $params);

        $sql .= " ORDER BY u.created_at DESC LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;

        $stmt = $this->pdo->prepare($sql);
        $this->applyTenantScope($stmt, $params);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getTotalUsers(array $filters = []): int {
        $sql = "SELECT COUNT(*) FROM users u WHERE u.deleted_at IS NULL";
        $params = [];

        // Apply filters
        if (!empty($filters['search'])) {
            $sql .= " AND (u.name LIKE ? OR u.email LIKE ?)";
            $params[] = '%' . $filters['search'] . '%';
            $params[] = '%' . $filters['search'] . '%';
        }

        if (!empty($filters['tenant_id'])) {
            $sql .= " AND u.tenant_id = ?";
            $params[] = $filters['tenant_id'];
        }

        if (!empty($filters['status'])) {
            $sql .= " AND u.status = ?";
            $params[] = $filters['status'];
        }

        $stmt = $this->pdo->prepare($sql);
        $this->applyTenantScope($stmt, $params);
        $stmt->execute($params);

        return (int) $stmt->fetchColumn();
    }

    public function getTenants(): array {
        // SaaS admin can see all tenants, regular tenants see only their own
        if ($this->context->allowCrossTenant()) {
            $stmt = $this->pdo->prepare("SELECT id, name FROM pos_tenants ORDER BY name");
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $stmt = $this->pdo->prepare("SELECT id, name FROM pos_tenants WHERE id = ? ORDER BY name");
            $stmt->execute([$this->context->getTenantId()]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    }

    public function createUser(array $userData): int {
        // Validate tenant assignment for SaaS admin
        if (isset($userData['tenant_id']) && $userData['tenant_id'] > 0) {
            if (!$this->context->allowCrossTenant()) {
                // Regular tenants can only assign users to their own tenant
                $userData['tenant_id'] = $this->context->getTenantId();
            } else {
                // SaaS admin - validate tenant exists
                $stmt = $this->pdo->prepare("SELECT id FROM pos_tenants WHERE id = ?");
                $stmt->execute([$userData['tenant_id']]);
                if (!$stmt->fetch()) {
                    throw new Exception('Invalid tenant assignment');
                }
            }
        }

        // Hash password
        $userData['password'] = password_hash($userData['password'], PASSWORD_DEFAULT);

        // Set created_by for audit trail
        $userData['created_by'] = $this->context->getUserId();

        return $this->insert($userData);
    }

    public function updateUser(int $userId, array $userData): void {
        // Verify user exists and get current data
        $currentUser = $this->find($userId);
        if (!$currentUser) {
            throw new Exception('User not found');
        }

        // SaaS admin can update any user, regular tenants only their tenant users
        if (!$this->context->allowCrossTenant()) {
            if ($currentUser['tenant_id'] != $this->context->getTenantId()) {
                throw new Exception('Cannot modify users from other tenants');
            }
        }

        // Validate tenant assignment
        if (isset($userData['tenant_id']) && $userData['tenant_id'] > 0) {
            if (!$this->context->allowCrossTenant()) {
                // Regular tenants can only assign to their own tenant
                $userData['tenant_id'] = $this->context->getTenantId();
            } else {
                // SaaS admin - validate tenant exists
                $stmt = $this->pdo->prepare("SELECT id FROM pos_tenants WHERE id = ?");
                $stmt->execute([$userData['tenant_id']]);
                if (!$stmt->fetch()) {
                    throw new Exception('Invalid tenant assignment');
                }
            }
        }

        // Hash password if provided
        if (!empty($userData['password'])) {
            $userData['password'] = password_hash($userData['password'], PASSWORD_DEFAULT);
        }

        // Set updated_by for audit trail
        $userData['updated_by'] = $this->context->getUserId();

        $stmt = $this->pdo->prepare("UPDATE users SET " . implode('=?, ', array_keys($userData)) . "=?, updated_at = NOW() WHERE id = ?");
        $params = array_values($userData);
        $params[] = $userId;
        $this->applyTenantScope($stmt, $params);
        $stmt->execute($params);
    }

    public function deleteUser(int $userId): void {
        // Verify user exists and get current data
        $currentUser = $this->find($userId);
        if (!$currentUser) {
            throw new Exception('User not found');
        }

        // SaaS admin can delete any user, regular tenants only their tenant users
        if (!$this->context->allowCrossTenant()) {
            if ($currentUser['tenant_id'] != $this->context->getTenantId()) {
                throw new Exception('Cannot delete users from other tenants');
            }
        }

        // Soft delete
        $stmt = $this->pdo->prepare("UPDATE users SET deleted_at = ?, status = 'inactive', updated_by = ? WHERE id = ?");
        $params = [date('Y-m-d H:i:s'), $this->context->getUserId(), $userId];
        $this->applyTenantScope($stmt, $params);
        $stmt->execute($params);
    }

    public function checkEmailExists(string $email, int $excludeUserId = null): bool {
        $sql = "SELECT COUNT(*) FROM users WHERE email = ? AND deleted_at IS NULL";
        $params = [$email];

        if ($excludeUserId) {
            $sql .= " AND id != ?";
            $params[] = $excludeUserId;
        }

        $stmt = $this->pdo->prepare($sql);
        $this->applyTenantScope($stmt, $params);
        $stmt->execute($params);

        return $stmt->fetchColumn() > 0;
    }
}