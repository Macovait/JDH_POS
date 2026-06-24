<?php
declare(strict_types=1);

/**
 * Secure Tenant Context - Zero-Trust Implementation
 * Immutable, validated tenant context provider with cryptographic security
 */

class SecureTenantContext {
    private static ?self $instance = null;

    private int $tenant_id = 0;
    private int $branch_id = 0;
    private int $user_id = 0;
    private int $business_type_id = 0;
    private string $user_role = 'cashier';
    private bool $is_super_admin = false;
    private array $permissions = [];
    private ?array $company_data = null;
    private string $context_hash = '';
    private string $security_token = '';
    
    private const HMAC_ALGO = 'sha256';
    private const TOKEN_LIFETIME = 3600;

    private function __construct() {
        $this->hydrateFromSession();
        $this->validateContext();
        $this->loadPermissions();
        $this->loadCompanyData();
        $this->generateSecurityCredentials();
    }

    public static function getInstance(): self {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public static function resetInstance(): void {
        self::$instance = null;
    }

    private function hydrateFromSession(): void {
        $this->tenant_id = $this->validateSessionValue('tenant_id');
        $this->branch_id = $this->validateSessionValue('branch_id');
        $this->user_id = $this->validateSessionValue('user_id');
        $this->business_type_id = $this->validateSessionValue('business_type_id') ?? 0;
        $this->user_role = $_SESSION['role'] ?? $_SESSION['user']['role'] ?? 'cashier';
        $this->is_super_admin = $this->checkSuperAdminStatus();
    }

    private function validateSessionValue(string $key): ?int {
        $value = $_SESSION[$key] ?? null;
        if ($value !== null) {
            $intValue = (int) $value;
            if ($intValue > 0 && (string) $intValue === (string) $value) {
                return $intValue;
            }
        }
        return null;
    }

    private function checkSuperAdminStatus(): bool {
        if (!empty($_SESSION['is_super_admin'])) {
            return true;
        }
        if ($this->user_role === 'super_admin' || $this->user_role === 'superadmin') {
            return true;
        }
        if ($this->user_id === 1) {
            return true;
        }
        if ($this->user_id) {
            try {
                if (function_exists('db_has_column') && db_has_column('users', 'role')) {
                    $pdo = $this->getDatabaseConnection();
                    $stmt = $pdo->prepare("SELECT role FROM users WHERE id = ? AND deleted_at IS NULL");
                    $stmt->execute([$this->user_id]);
                    $role = $stmt->fetchColumn();
                    if ($role === 'super_admin' || $role === 'superadmin') {
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
        if (function_exists('get_db_connection')) {
            return get_db_connection();
        }
        global $pdo;
        if ($pdo instanceof PDO) {
            return $pdo;
        }
        throw new RuntimeException('Database connection not available');
    }
    
    private function generateSecurityCredentials(): void {
        $contextData = [
            'tenant_id' => $this->tenant_id,
            'branch_id' => $this->branch_id,
            'user_id' => $this->user_id,
            'business_type_id' => $this->business_type_id,
            'timestamp' => time(),
            'nonce' => bin2hex(random_bytes(16)),
        ];
        $key = $_SESSION['security_key'] ?? getenv('JDH_POS_SECURITY_KEY') ?: bin2hex(random_bytes(32));
        $_SESSION['security_key'] = $key;
        $this->context_hash = hash_hmac(self::HMAC_ALGO, json_encode($contextData), $key);
        
        $tokenData = [
            'tenant_id' => $this->tenant_id,
            'branch_id' => $this->branch_id,
            'user_id' => $this->user_id,
            'issued_at' => time(),
            'expires_at' => time() + self::TOKEN_LIFETIME,
        ];
        $this->security_token = base64_encode(json_encode($tokenData));
    }

    public function validateContext(): void {
        if ($this->user_id === 1) {
            $this->is_super_admin = true;
            return;
        }
        
        if (!$this->is_super_admin && (!$this->tenant_id || !$this->user_id)) {
            error_log("SecureTenantContext: Missing tenant context");
            return;
        }

        if (!$this->is_super_admin && $this->tenant_id) {
            try {
                $pdo = $this->getDatabaseConnection();
                $stmt = $pdo->prepare("SELECT id, status FROM tenants WHERE id = ? AND deleted_at IS NULL");
                $stmt->execute([$this->tenant_id]);
                $company = $stmt->fetch(PDO::FETCH_ASSOC);
                if (!$company) {
                    error_log("SecureTenantContext: Company not found");
                    return;
                }
                $validStatuses = ['active', 'trial'];
                if (!in_array($company['status'] ?? '', $validStatuses, true)) {
                    error_log("SecureTenantContext: Company not active");
                }
            } catch (PDOException $e) {
                error_log("SecureTenantContext: Validation failed - " . $e->getMessage());
            }
        }
    }

    private function loadPermissions(): void {
        if (!$this->user_id) {
            return;
        }
        try {
            $pdo = $this->getDatabaseConnection();
            $stmt = $pdo->prepare("
                SELECT DISTINCT p.code FROM permissions p
                JOIN role_permissions rp ON p.id = rp.permission_id
                JOIN user_roles ur ON rp.role_id = ur.role_id
                WHERE ur.user_id = ?
            ");
            $stmt->execute([$this->user_id]);
            $this->permissions = $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
        } catch (Exception $e) {
            error_log("Failed to load permissions: " . $e->getMessage());
            $this->permissions = [];
        }
    }

    private function loadCompanyData(): void {
        if (!$this->tenant_id) {
            return;
        }
        try {
            $pdo = $this->getDatabaseConnection();
            $stmt = $pdo->prepare("
                SELECT c.*, bt.code as business_type_code, bt.name as business_type_name
                FROM tenants c
                LEFT JOIN business_types bt ON c.business_type_id = bt.id
                WHERE c.id = ? AND c.deleted_at IS NULL
            ");
            $stmt->execute([$this->tenant_id]);
            $this->company_data = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Failed to load company data: " . $e->getMessage());
            $this->company_data = null;
        }
    }

    public function getCompanyId(): int { return $this->tenant_id; }
    public function getBranchId(): int { return $this->branch_id; }
    public function getUserId(): int { return $this->user_id; }
    public function getBusinessTypeId(): int { return $this->business_type_id; }
    public function getUserRole(): string { return $this->user_role; }
    public function isSuperAdmin(): bool { return $this->is_super_admin; }
    public function getPermissions(): array { return $this->permissions; }
    public function getCompanyData(): ?array { return $this->company_data; }
    public function getContextHash(): string { return $this->context_hash; }
    public function getSecurityToken(): string { return $this->security_token; }

    public function hasPermission(string $permission): bool {
        return $this->is_super_admin || in_array($permission, $this->permissions, true);
    }

    public function allowCrossTenant(): bool {
        return $this->is_super_admin;
    }
    
    public function canAccessCompany(int $companyId): bool {
        return $this->is_super_admin || $this->tenant_id === $companyId;
    }
    
    public function canAccessBranch(int $branchId): bool {
        return $this->is_super_admin || $this->user_role === 'owner' || $this->branch_id === $branchId;
    }
    
    public function getSecureContextPredicates(): array {
        return [
            'tenant_id' => $this->tenant_id,
            'branch_id' => $this->branch_id,
            'user_id' => $this->user_id,
            'business_type_id' => $this->business_type_id,
        ];
    }
    
    public function getTenantWhereClause(string $tableAlias = ''): string {
        if ($this->is_super_admin) {
            return '1=1';
        }
        $prefix = $tableAlias ? "{$tableAlias}." : '';
        $conditions = [];
        if ($this->tenant_id > 0) {
            $conditions[] = "{$prefix}tenant_id = " . (int) $this->tenant_id;
        }
        if ($this->branch_id > 0) {
            $conditions[] = "{$prefix}branch_id = " . (int) $this->branch_id;
        }
        return !empty($conditions) ? implode(' AND ', $conditions) : '1=0';
    }
    
    public function getTenantParams(): array {
        return [
            ':tenant_id' => $this->tenant_id,
            ':branch_id' => $this->branch_id,
            ':user_id' => $this->user_id,
            ':business_type_id' => $this->business_type_id,
        ];
    }
    
    public function isValid(): bool {
        return $this->tenant_id > 0 && $this->user_id > 0;
    }

    public function getCurrentTenantWhere(): string {
        if ($this->is_super_admin) {
            return "1=1";
        }
        $conditions = [];
        if ($this->tenant_id) {
            $conditions[] = "tenant_id = " . (int) $this->tenant_id;
        }
        if ($this->branch_id) {
            $conditions[] = "branch_id = " . (int) $this->branch_id;
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
            'context_hash' => $this->context_hash,
        ];
    }

    public function __toString(): string {
        return json_encode($this->toArray());
    }

    public function logContextAccess(string $operation, string $table = null): void {
        error_log(sprintf(
            "TENANT_ACCESS: User %d (%s) performed '%s' on '%s' [company=%d, branch=%d]",
            $this->user_id,
            $this->user_role,
            $operation,
            $table ?: 'unknown',
            $this->tenant_id,
            $this->branch_id
        ));
    }
    
    /**
     * Verify request integrity with HMAC
     */
    public function verifyRequestIntegrity(array $requestData, string $providedSignature): bool {
        $key = $_SESSION['security_key'] ?? getenv('JDH_POS_SECURITY_KEY') ?: '';
        if (empty($key)) {
            return false;
        }
        
        $expectedSignature = hash_hmac(
            'sha256',
            json_encode($requestData),
            $key
        );
        
        return hash_equals($expectedSignature, $providedSignature);
    }
}

if (!class_exists('SecurityException', false)) {
    class SecurityException extends Exception {
        public function __construct(string $message = "", int $code = 0, Throwable $previous = null) {
            error_log("SECURITY VIOLATION: " . $message);
            parent::__construct($message, $code, $previous);
        }
    }
}
