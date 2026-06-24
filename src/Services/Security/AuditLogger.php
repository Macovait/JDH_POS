<?php
/**
 * Audit Logger - Comprehensive audit logging for SaaS
 * 
 * Tracks all data modifications and sensitive actions
 * for compliance and security monitoring
 * 
 * @package JakababaPOS
 * @version 2.0.0
 */

namespace JakababaPOS\Services\Security;

class AuditLogger
{
    private \PDO $db;
    private static ?AuditLogger $instance = null;
    
    const TABLE_PREFIX = 'pos_';
    
    /**
     * Tracked action categories
     */
    const ACTION_CREATE = 'create';
    const ACTION_READ = 'read';
    const ACTION_UPDATE = 'update';
    const ACTION_DELETE = 'delete';
    const ACTION_LOGIN = 'login';
    const ACTION_LOGOUT = 'logout';
    const ACTION_LOGIN_FAILED = 'login_failed';
    const ACTION_PASSWORD_CHANGE = 'password_change';
    const ACTION_2FA_ENABLE = '2fa_enable';
    const ACTION_2FA_DISABLE = '2fa_disable';
    const ACTION_API_KEY_CREATE = 'api_key_create';
    const ACTION_API_KEY_REVOKE = 'api_key_revoke';
    const ACTION_SUBSCRIPTION_UPGRADE = 'subscription_upgrade';
    const ACTION_SUBSCRIPTION_DOWNGRADE = 'subscription_downgrade';
    const ACTION_PAYMENT = 'payment';
    const ACTION_REFUND = 'refund';
    const ACTION_EXPORT = 'export';
    const ACTION_IMPORT = 'import';
    const ACTION_SETTINGS_CHANGE = 'settings_change';
    
    /**
     * Get singleton instance
     */
    public static function getInstance(\PDO $db): self
    {
        if (self::$instance === null) {
            self::$instance = new self($db);
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    public function __construct(\PDO $db)
    {
        $this->db = $db;
    }
    
    /**
     * Log an audit event
     */
    public function log(
        string $action,
        ?string $entityType = null,
        ?int $entityId = null,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?int $userId = null,
        ?string $description = null
    ): int {
        $tenantId = $this->getCurrentTenantId();
        $userId = $userId ?? $_SESSION['user_id'] ?? null;
        
        $oldJson = $oldValues ? json_encode($oldValues, JSON_UNESCAPED_UNICODE) : null;
        $newJson = $newValues ? json_encode($newValues, JSON_UNESCAPED_UNICODE) : null;
        
        $sql = "INSERT INTO " . self::TABLE_PREFIX . "audit_logs 
                (tenant_id, user_id, action, entity_type, entity_id, old_values, new_values, 
                 ip_address, user_agent, session_id, description) 
                VALUES (:tenant_id, :user_id, :action, :entity_type, :entity_id, :old_values, :new_values,
                        :ip_address, :user_agent, :session_id, :description)";
        
        $stmt = $this->db->prepare($sql);
        
        $stmt->execute([
            ':tenant_id' => $tenantId,
            ':user_id' => $userId,
            ':action' => $action,
            ':entity_type' => $entityType,
            ':entity_id' => $entityId,
            ':old_values' => $oldJson,
            ':new_values' => $newJson,
            ':ip_address' => $this->getClientIp(),
            ':user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
            ':session_id' => session_id(),
            ':description' => $description
        ]);
        
        return (int) $this->db->lastInsertId();
    }
    
    /**
     * Log data change with automatic diff
     */
    public function logChange(
        string $action,
        string $entityType,
        int $entityId,
        array $before,
        array $after,
        ?int $userId = null
    ): int {
        $changes = $this->computeDiff($before, $after);
        
        if (empty($changes)) {
            return 0;
        }
        
        $description = sprintf(
            '%s %s: %s',
            ucfirst($action),
            $entityType,
            implode(', ', array_keys($changes))
        );
        
        return $this->log(
            $action,
            $entityType,
            $entityId,
            $changes['before'] ?? null,
            $changes['after'] ?? null,
            $userId,
            $description
        );
    }
    
    /**
     * Compute diff between two arrays
     */
    private function computeDiff(array $before, array $after): array
    {
        $changes = [];
        
        foreach ($after as $key => $newValue) {
            $oldValue = $before[$key] ?? null;
            
            if ($oldValue !== $newValue) {
                $changes['before'][$key] = $oldValue;
                $changes['after'][$key] = $newValue;
            }
        }
        
        // Check for deleted keys
        foreach ($before as $key => $oldValue) {
            if (!array_key_exists($key, $after)) {
                $changes['before'][$key] = $oldValue;
            }
        }
        
        return $changes;
    }
    
    /**
     * Log authentication event
     */
    public function logAuth(
        string $action,
        int $userId,
        bool $success,
        ?string $reason = null
    ): int {
        $action = $action === self::ACTION_LOGIN && !$success 
            ? self::ACTION_LOGIN_FAILED 
            : $action;
        
        return $this->log(
            $action,
            'user',
            $userId,
            null,
            null,
            null,
            $reason ?? ($success ? 'Successful login' : 'Failed login attempt')
        );
    }
    
    /**
     * Log security event
     */
    public function logSecurity(
        string $event,
        array $context = []
    ): int {
        return $this->log(
            $event,
            'security',
            null,
            null,
            $context,
            null,
            "Security event: $event"
        );
    }
    
    /**
     * Log API request
     */
    public function logApi(
        string $endpoint,
        string $method,
        int $statusCode,
        ?int $userId = null
    ): void {
        $this->log(
            'api_request',
            'api',
            null,
            ['endpoint' => $endpoint, 'method' => $method],
            ['status' => $statusCode],
            $userId
        );
    }
    
    /**
     * Get audit trail for entity
     */
    public function getEntityHistory(
        string $entityType,
        int $entityId,
        ?int $limit = 50
    ): array {
        $sql = "SELECT * FROM " . self::TABLE_PREFIX . "audit_logs
                WHERE entity_type = :entity_type AND entity_id = :entity_id
                ORDER BY created_at DESC
                LIMIT :limit";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':entity_type' => $entityType,
            ':entity_id' => $entityId,
            ':limit' => $limit
        ]);
        
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    
    /**
     * Get user's activity
     */
    public function getUserActivity(
        int $userId,
        ?int $days = 30,
        ?int $limit = 100
    ): array {
        $sql = "SELECT * FROM " . self::TABLE_PREFIX . "audit_logs
                WHERE user_id = :user_id
                AND created_at > DATE_SUB(NOW(), INTERVAL :days DAY)
                ORDER BY created_at DESC
                LIMIT :limit";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':user_id' => $userId,
            ':days' => $days,
            ':limit' => $limit
        ]);
        
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    
    /**
     * Get tenant's recent activity
     */
    public function getRecentActivity(
        ?int $tenantId = null,
        ?int $limit = 100
    ): array {
        $tenantId = $tenantId ?? $this->getCurrentTenantId();
        
        $sql = "SELECT al.*, u.name as user_name, u.email as user_email
                FROM " . self::TABLE_PREFIX . "audit_logs al
                LEFT JOIN " . self::TABLE_PREFIX . "users u ON al.user_id = u.id
                WHERE al.tenant_id = :tenant_id
                ORDER BY al.created_at DESC
                LIMIT :limit";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':tenant_id' => $tenantId,
            ':limit' => $limit
        ]);
        
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    
    /**
     * Search audit logs
     */
    public function search(
        array $filters = [],
        ?int $page = 1,
        ?int $perPage = 50
    ): array {
        $where = ['1=1'];
        $params = [];
        
        if (!empty($filters['tenant_id'])) {
            $where[] = 'al.tenant_id = :tenant_id';
            $params[':tenant_id'] = $filters['tenant_id'];
        }
        
        if (!empty($filters['user_id'])) {
            $where[] = 'al.user_id = :user_id';
            $params[':user_id'] = $filters['user_id'];
        }
        
        if (!empty($filters['action'])) {
            $where[] = 'al.action = :action';
            $params[':action'] = $filters['action'];
        }
        
        if (!empty($filters['entity_type'])) {
            $where[] = 'al.entity_type = :entity_type';
            $params[':entity_type'] = $filters['entity_type'];
        }
        
        if (!empty($filters['from_date'])) {
            $where[] = 'al.created_at >= :from_date';
            $params[':from_date'] = $filters['from_date'];
        }
        
        if (!empty($filters['to_date'])) {
            $where[] = 'al.created_at <= :to_date';
            $params[':to_date'] = $filters['to_date'];
        }
        
        $offset = ($page - 1) * $perPage;
        
        $sql = "SELECT al.* FROM " . self::TABLE_PREFIX . "audit_logs al
                WHERE " . implode(' AND ', $where) . "
                ORDER BY al.created_at DESC
                LIMIT :limit OFFSET :offset";
        
        $params[':limit'] = $perPage;
        $params[':offset'] = $offset;
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    
    /**
     * Get current tenant ID
     */
    private function getCurrentTenantId(): int
    {
        return $_SESSION['tenant_id'] ?? $_SESSION['tenant_id'] ?? 0;
    }
    
    /**
     * Get client IP
     */
    private function getClientIp(): string
    {
        $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['HTTP_X_REAL_IP'] ?? null;
        
        if ($ip) {
            return explode(',', $ip)[0];
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }
}

/**
 * Helper functions
 */
if (!function_exists('auditLog')) {
    function auditLog(
        string $action,
        ?string $entityType = null,
        ?int $entityId = null,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?int $userId = null
    ): int {
        global $db;
        $logger = AuditLogger::getInstance($db);
        return $logger->log($action, $entityType, $entityId, $oldValues, $newValues, $userId);
    }
}

if (!function_exists('auditAuth')) {
    function auditAuth(string $action, int $userId, bool $success, ?string $reason = null): int
    {
        global $db;
        $logger = AuditLogger::getInstance($db);
        return $logger->logAuth($action, $userId, $success, $reason);
    }
}

if (!function_exists('auditSecurity')) {
    function auditSecurity(string $event, array $context = []): int
    {
        global $db;
        $logger = AuditLogger::getInstance($db);
        return $logger->logSecurity($event, $context);
    }
}