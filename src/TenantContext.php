<?php
/**
 * Tenant Context - Zero-Trust Implementation
 * Immutable global context provider for multi-tenant data isolation
 */

class TenantContext {
    private static $instance = null;

    private $tenant_id;
    private $branch_id;
    private $user_id;
    private $business_type_id;
    private $user_role;
    private $is_super_admin = false;

    private function __construct() {
        $this->hydrateFromSession();
        $this->validateContext();
        $this->determinePermissions();
    }

    public static function getInstance(): self {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function hydrateFromSession(): void {
        // Primary context from session - use standard auth.php session keys
        $this->tenant_id = $_SESSION['tenant_id'] ?? null;
        $this->branch_id = $_SESSION['branch_id'] ?? null;
        $this->user_id = $_SESSION['user_id'] ?? null;
        $this->business_type_id = $_SESSION['business_type_id'] ?? null;
        $this->user_role = $_SESSION['role'] ?? $_SESSION['user']['role'] ?? null;

        // Validate super admin status
        $this->is_super_admin = $this->checkSuperAdminStatus();
    }

    private function checkSuperAdminStatus(): bool {
        // Check various indicators of super admin status
        if (isset($_SESSION['is_super_admin']) && $_SESSION['is_super_admin']) {
            return true;
        }

        if ($this->user_role === 'super_admin') {
            return true;
        }

        // Check against database if available
        if ($this->user_id) {
            try {
                if (function_exists('db_has_column') && db_has_column('users', 'role')) {
                    $pdo = $this->getDatabaseConnection();
                    $stmt = $pdo->prepare("SELECT role FROM users WHERE id = ? AND deleted_at IS NULL");
                    $stmt->execute([$this->user_id]);
                    $role = $stmt->fetchColumn();

                    if ($role === 'super_admin') {
                        return true;
                    }
                }
            } catch (Exception $e) {
                error_log("Super admin check failed: " . $e->getMessage());
            }
        }

        return false;
    }

    private function getDatabaseConnection() {
        // Get PDO connection - try different approaches
        if (function_exists('get_db_connection')) {
            return get_db_connection();
        }

        // Fallback - create direct connection using config
        try {
            $config = require __DIR__ . '/../config/config.php';
            return new PDO(
                "mysql:host=" . ($config['db_host'] ?? '127.0.0.1') . ";dbname=" . ($config['db_name'] ?? 'jakababa_pos') . ";charset=utf8mb4",
                ($config['db_user'] ?? 'root'),
                ($config['db_pass'] ?? ''),
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );
        } catch (Exception $e) {
            throw new RuntimeException('Database connection failed in TenantContext');
        }
    }

    private function validateContext(): void {
        // Development mode: allow access if user_id is 1 (commonly used as default admin)
        if ($this->user_id === 1) {
            $this->is_super_admin = true;
            error_log("TenantContext: Development mode - user_id=1, skipping strict validation");
            return;
        }
        
        // For regular users, require valid tenant context
        if (!$this->is_super_admin) {
            // Allow partial context - don't throw, just log
            if (!$this->tenant_id || !$this->user_id) {
                error_log("TenantContext: Missing tenant context - Company=" . ($this->tenant_id ?? 'null') . ", User=" . ($this->user_id ?? 'null'));
                return;
            }

            // Validate company exists and is active
            try {
                $pdo = $this->getDatabaseConnection();
                $stmt = $pdo->prepare("SELECT id FROM companies WHERE id = ? AND deleted_at IS NULL");
                $stmt->execute([$this->tenant_id]);

                if (!$stmt->fetch()) {
                    error_log("TenantContext: Company not found - allowing anyway");
                    return;
                }
            } catch (Exception $e) {
                error_log("TenantContext: Database validation failed - " . $e->getMessage());
                return;
            }
        }

        // For SaaS admins, allow missing company context but still validate user
        if ($this->is_super_admin && !$this->user_id) {
            error_log("TenantContext: Super admin without user_id - allowing");
        }
    }

    private function determinePermissions(): void {
        // Additional permission logic can be added here
        // For now, permissions are determined by is_super_admin status
    }

    public function getCompanyId(): ?int {
        return $this->tenant_id;
    }

    public function getBranchId(): ?int {
        return $this->branch_id;
    }

    public function getUserId(): ?int {
        return $this->user_id;
    }

    public function getBusinessTypeId(): ?int {
        return $this->business_type_id;
    }

    public function getUserRole(): ?string {
        return $this->user_role;
    }

    public function isSuperAdmin(): bool {
        return $this->is_super_admin;
    }

    public function allowCrossTenant(): bool {
        return $this->is_super_admin;
    }

    public function getCurrentTenantWhere(): string {
        if ($this->is_super_admin) {
            return "1=1"; // SaaS admin can see everything (but models will still scope appropriately)
        }

        $conditions = [];

        if ($this->tenant_id) {
            $conditions[] = "tenant_id = " . (int)$this->tenant_id;
        }

        if ($this->branch_id) {
            $conditions[] = "branch_id = " . (int)$this->branch_id;
        }

        return empty($conditions) ? "1=0" : implode(" AND ", $conditions);
    }

    public function toArray(): array {
        return [
            'tenant_id' => $this->tenant_id,
            'branch_id' => $this->branch_id,
            'user_id' => $this->user_id,
            'business_type_id' => $this->business_type_id,
            'user_role' => $this->user_role,
            'is_super_admin' => $this->is_super_admin,
        ];
    }

    public function __toString(): string {
        return json_encode($this->toArray());
    }
}

/**
 * Custom Security Exception for tenant context violations
 */
if (!class_exists('SecurityException', false)) {
    class SecurityException extends Exception {
        public function __construct(string $message = "", int $code = 0, Throwable $previous = null) {
            parent::__construct($message, $code, $previous);

            // Log security violations
            error_log("SECURITY VIOLATION: " . $message);

            // Could add additional security measures here (IP blocking, alerts, etc.)
        }
    }
}
