<?php
/**
 * Multi-Tenant Authentication Service
 * Handles user authentication, session management, and tenant context for SaaS
 */

declare(strict_types=1);

namespace JDH_POS\Services;

use PDO;
use PDOException;
use Exception;

class AuthenticationService
{
    private PDO $db;
    private TenantContextService $tenantContext;

    public function __construct(PDO $db, TenantContextService $tenantContext)
    {
        $this->db = $db;
        $this->tenantContext = $tenantContext;
    }

    /**
     * Authenticate user with email/username and password
     */
    public function authenticate(string $identifier, string $password): ?array
    {
        try {
            // Find user by email or username
            $stmt = $this->db->prepare("
                SELECT u.id, u.name, u.email, u.username, u.password_hash, u.role_id, u.tenant_id, u.status,
                       u.branch_id, u.last_login, u.require_password_change,
                       r.name AS role_name
                FROM users u
                LEFT JOIN roles r ON u.role_id = r.id
                WHERE (u.email = ? OR u.username = ?) AND u.status = 1
                LIMIT 1
            ");
            $stmt->execute([$identifier, $identifier]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$user || !password_verify($password, $user['password_hash'])) {
                $this->logFailedLogin($identifier, 'invalid_credentials');
                return null;
            }

            // Check if account is locked
            if (!empty($user['locked_until']) && strtotime($user['locked_until']) > time()) {
                $this->logFailedLogin($identifier, 'account_locked');
                return null;
            }

            // Set tenant context
            if ($user['tenant_id']) {
                $this->tenantContext->setTenant($user['tenant_id']);
            }

            // Update last login
            $this->updateLastLogin($user['id']);

            // Get user permissions
            $user['permissions'] = $this->getUserPermissions($user['role_id']);

            // Check if password change is required
            if (!empty($user['require_password_change'])) {
                $user['force_password_change'] = true;
            }

            // Log successful login
            $this->logActivity($user['id'], 'login', 'User logged in successfully');

            return $user;

        } catch (PDOException $e) {
            error_log("AuthenticationService::authenticate error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Create user session
     */
    public function createSession(int $userId, array $userData): string
    {
        try {
            $sessionId = bin2hex(random_bytes(32));
            $expiresAt = date('Y-m-d H:i:s', strtotime('+8 hours'));

            $stmt = $this->db->prepare("
                INSERT INTO tenant_sessions
                (id, tenant_id, user_id, ip_address, user_agent, expires_at, created_at)
                VALUES (?, ?, ?, ?, ?, ?, NOW())
            ");

            $stmt->execute([
                $sessionId,
                $userData['tenant_id'] ?? null,
                $userId,
                $_SERVER['REMOTE_ADDR'] ?? null,
                $_SERVER['HTTP_USER_AGENT'] ?? null,
                $expiresAt
            ]);

            return $sessionId;

        } catch (PDOException $e) {
            error_log("AuthenticationService::createSession error: " . $e->getMessage());
            throw new Exception("Failed to create session");
        }
    }

    /**
     * Validate session
     */
    public function validateSession(string $sessionId): ?array
    {
        try {
            $stmt = $this->db->prepare("
                SELECT s.*, u.name, u.email, u.role_id, u.tenant_id, u.status, u.branch_id
                FROM tenant_sessions s
                JOIN users u ON s.user_id = u.id
                WHERE s.id = ? AND s.expires_at > NOW() AND u.status = 1
                LIMIT 1
            ");
            $stmt->execute([$sessionId]);
            $session = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($session) {
                // Set tenant context
                if ($session['tenant_id']) {
                    $this->tenantContext->setTenant($session['tenant_id']);
                }

                // Update session activity
                $this->updateSessionActivity($sessionId);

                // Get user permissions
                $session['permissions'] = $this->getUserPermissions($session['role_id']);

                return $session;
            }

            return null;

        } catch (PDOException $e) {
            error_log("AuthenticationService::validateSession error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Destroy session
     */
    public function destroySession(string $sessionId): bool
    {
        try {
            $stmt = $this->db->prepare("DELETE FROM tenant_sessions WHERE id = ?");
            $stmt->execute([$sessionId]);
            return true;
        } catch (PDOException $e) {
            error_log("AuthenticationService::destroySession error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Check if user has permission
     */
    public function hasPermission(int $userId, string $module, string $action): bool
    {
        $permissions = $this->getUserPermissionsForUser($userId);

        if (isset($permissions['all']) && $permissions['all'] === true) {
            return true;
        }

        if (isset($permissions[$module])) {
            return in_array($action, $permissions[$module]);
        }

        return false;
    }

    /**
     * Get user permissions by role
     */
    private function getUserPermissions(int $roleId): array
    {
        try {
            $stmt = $this->db->prepare("
                SELECT p.code
                FROM role_permissions rp
                JOIN permissions p ON rp.permission_id = p.id
                WHERE rp.role_id = ?
            ");
            $stmt->execute([$roleId]);
            $permissions = $stmt->fetchAll(PDO::FETCH_COLUMN);

            // Group permissions by module
            $grouped = [];
            foreach ($permissions as $permission) {
                // Parse permission code (format: module.action)
                $parts = explode('.', $permission, 2);
                if (count($parts) === 2) {
                    $grouped[$parts[0]][] = $parts[1];
                }
            }

            return $grouped;

        } catch (PDOException $e) {
            error_log("AuthenticationService::getUserPermissions error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get permissions for a specific user
     */
    private function getUserPermissionsForUser(int $userId): array
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

            // Group permissions
            $grouped = [];
            foreach ($permissions as $permission) {
                $parts = explode('.', $permission, 2);
                if (count($parts) === 2) {
                    $grouped[$parts[0]][] = $parts[1];
                }
            }

            return $grouped;

        } catch (PDOException $e) {
            error_log("AuthenticationService::getUserPermissionsForUser error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Update user last login
     */
    private function updateLastLogin(int $userId): void
    {
        try {
            $stmt = $this->db->prepare("
                UPDATE users
                SET last_login = NOW(), failed_login_attempts = 0, locked_until = NULL
                WHERE id = ?
            ");
            $stmt->execute([$userId]);
        } catch (PDOException $e) {
            error_log("AuthenticationService::updateLastLogin error: " . $e->getMessage());
        }
    }

    /**
     * Update session activity
     */
    private function updateSessionActivity(string $sessionId): void
    {
        try {
            $stmt = $this->db->prepare("
                UPDATE tenant_sessions
                SET last_activity_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$sessionId]);
        } catch (PDOException $e) {
            error_log("AuthenticationService::updateSessionActivity error: " . $e->getMessage());
        }
    }

    /**
     * Log failed login attempt
     */
    private function logFailedLogin(string $identifier, string $reason): void
    {
        try {
            // Find user to get ID
            $stmt = $this->db->prepare("SELECT id FROM users WHERE email = ? OR username = ? LIMIT 1");
            $stmt->execute([$identifier, $identifier]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user) {
                // Increment failed attempts
                $stmt = $this->db->prepare("
                    UPDATE users
                    SET failed_login_attempts = failed_login_attempts + 1,
                        locked_until = CASE
                            WHEN failed_login_attempts >= 5 THEN DATE_ADD(NOW(), INTERVAL 30 MINUTE)
                            ELSE NULL
                        END
                    WHERE id = ?
                ");
                $stmt->execute([$user['id']]);

                // Log the failed attempt
                $this->logActivity($user['id'], 'failed_login', "Failed login: $reason");
            }

        } catch (PDOException $e) {
            error_log("AuthenticationService::logFailedLogin error: " . $e->getMessage());
        }
    }

    /**
     * Log user activity
     */
    private function logActivity(int $userId, string $action, string $details = ''): void
    {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO activity_logs
                (user_id, tenant_id, action, meta, ip_address, created_at)
                VALUES (?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([
                $userId,
                $this->tenantContext->getTenantId(),
                $action,
                json_encode(['details' => $details]),
                $_SERVER['REMOTE_ADDR'] ?? null
            ]);
        } catch (PDOException $e) {
            error_log("AuthenticationService::logActivity error: " . $e->getMessage());
        }
    }

    /**
     * Change user password
     */
    public function changePassword(int $userId, string $currentPassword, string $newPassword): bool
    {
        try {
            // Verify current password
            $stmt = $this->db->prepare("SELECT password_hash FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$user || !password_verify($currentPassword, $user['password_hash'])) {
                return false;
            }

            // Update password
            $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
            $stmt = $this->db->prepare("
                UPDATE users
                SET password_hash = ?, require_password_change = 0, password_reset_token = NULL,
                    password_reset_expires = NULL, updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$newHash, $userId]);

            $this->logActivity($userId, 'password_changed', 'Password changed successfully');

            return true;

        } catch (PDOException $e) {
            error_log("AuthenticationService::changePassword error: " . $e->getMessage());
            return false;
        }
    }
}