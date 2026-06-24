<?php
/**
 * Audit Trail System
 * Logs all changes to critical data for accountability and compliance
 */

namespace JDH\POS\Audit;

class AuditLogger
{
    private \PDO $pdo;
    private int $userId;
    private ?int $tenantId;
    private string $ipAddress;
    private string $userAgent;
    
    /**
     * Actions to audit
     */
    const ACTION_CREATE = 'create';
    const ACTION_UPDATE = 'update';
    const ACTION_DELETE = 'delete';
    const ACTION_VIEW = 'view';
    const ACTION_LOGIN = 'login';
    const ACTION_LOGOUT = 'logout';
    const ACTION_EXPORT = 'export';
    const ACTION_IMPORT = 'import';
    const ACTION_PRINT = 'print';
    
    public function __construct(\PDO $pdo, int $userId = 0, ?int $tenantId = null)
    {
        $this->pdo = $pdo;
        $this->userId = $userId;
        $this->tenantId = $tenantId;
        $this->ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $this->userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
        
        $this->ensureTableExists();
    }
    
    /**
     * Log a single action
     */
    public function log(
        string $action,
        string $entityType,
        ?int $entityId = null,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?string $description = null
    ): void {
        try {
            // Don't log if no changes
            if ($action === self::ACTION_UPDATE && $oldValues === $newValues) {
                return;
            }
            
            $stmt = $this->pdo->prepare("
                INSERT INTO audit_logs 
                (user_id, tenant_id, action, entity_type, entity_id, old_values, new_values, 
                 description, ip_address, user_agent, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            
            $stmt->execute([
                $this->userId ?: null,
                $this->tenantId,
                $action,
                $entityType,
                $entityId,
                $oldValues ? json_encode($oldValues) : null,
                $newValues ? json_encode($newValues) : null,
                $description,
                $this->ipAddress,
                substr($this->userAgent, 0, 255)
            ]);
            
        } catch (\Exception $e) {
            // Silently fail - don't break the app for audit logging
            error_log("Audit log failed: " . $e->getMessage());
        }
    }
    
    /**
     * Log creation of a record
     */
    public function logCreate(string $entityType, int $entityId, array $newValues, ?string $description = null): void
    {
        $this->log(self::ACTION_CREATE, $entityType, $entityId, null, $newValues, $description);
    }
    
    /**
     * Log update of a record
     */
    public function logUpdate(string $entityType, int $entityId, array $oldValues, array $newValues, ?string $description = null): void
    {
        // Only log changed fields
        $changes = [];
        foreach ($newValues as $key => $value) {
            if (!isset($oldValues[$key]) || $oldValues[$key] !== $value) {
                $changes[$key] = $value;
            }
        }
        
        if (!empty($changes)) {
            $this->log(self::ACTION_UPDATE, $entityType, $entityId, $oldValues, $changes, $description);
        }
    }
    
    /**
     * Log deletion of a record
     */
    public function logDelete(string $entityType, int $entityId, array $oldValues, ?string $description = null): void
    {
        $this->log(self::ACTION_DELETE, $entityType, $entityId, $oldValues, null, $description);
    }
    
    /**
     * Log view action
     */
    public function logView(string $entityType, ?int $entityId = null, ?string $description = null): void
    {
        $this->log(self::ACTION_VIEW, $entityType, $entityId, null, null, $description);
    }
    
    /**
     * Log authentication actions
     */
    public function logLogin(bool $success, ?string $username = null, ?string $failureReason = null): void
    {
        $action = $success ? self::ACTION_LOGIN : 'login_failed';
        $description = $success ? "User logged in" : "Login failed: {$failureReason}";
        
        $this->log($action, 'user', $this->userId ?: null, null, [
            'username' => $username,
            'success' => $success
        ], $description);
    }
    
    public function logLogout(): void
    {
        $this->log(self::ACTION_LOGOUT, 'user', $this->userId, null, null, 'User logged out');
    }
    
    /**
     * Query audit logs
     */
    public function query(array $filters = [], int $limit = 100, int $offset = 0): array
    {
        $where = ['1=1'];
        $params = [];
        
        if (isset($filters['user_id'])) {
            $where[] = 'user_id = ?';
            $params[] = $filters['user_id'];
        }
        
        if (isset($filters['tenant_id'])) {
            $where[] = 'tenant_id = ?';
            $params[] = $filters['tenant_id'];
        }
        
        if (isset($filters['action'])) {
            $where[] = 'action = ?';
            $params[] = $filters['action'];
        }
        
        if (isset($filters['entity_type'])) {
            $where[] = 'entity_type = ?';
            $params[] = $filters['entity_type'];
        }
        
        if (isset($filters['entity_id'])) {
            $where[] = 'entity_id = ?';
            $params[] = $filters['entity_id'];
        }
        
        if (isset($filters['date_from'])) {
            $where[] = 'created_at >= ?';
            $params[] = $filters['date_from'];
        }
        
        if (isset($filters['date_to'])) {
            $where[] = 'created_at <= ?';
            $params[] = $filters['date_to'] . ' 23:59:59';
        }
        
        if (isset($filters['search'])) {
            $where[] = '(description LIKE ? OR entity_type LIKE ?)';
            $search = '%' . $filters['search'] . '%';
            $params[] = $search;
            $params[] = $search;
        }
        
        $whereClause = implode(' AND ', $where);
        
        // Get total count
        $countStmt = $this->pdo->prepare("SELECT COUNT(*) FROM audit_logs WHERE {$whereClause}");
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();
        
        // Get records
        $sql = "
            SELECT * FROM audit_logs 
            WHERE {$whereClause} 
            ORDER BY created_at DESC 
            LIMIT ? OFFSET ?
        ";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(array_merge($params, [$limit, $offset]));
        $records = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        
        // Decode JSON values
        foreach ($records as &$record) {
            $record['old_values'] = $record['old_values'] ? json_decode($record['old_values'], true) : null;
            $record['new_values'] = $record['new_values'] ? json_decode($record['new_values'], true) : null;
        }
        
        return [
            'records' => $records,
            'total' => $total,
            'limit' => $limit,
            'offset' => $offset
        ];
    }
    
    /**
     * Get audit trail for a specific entity
     */
    public function getEntityHistory(string $entityType, int $entityId): array
    {
        return $this->query([
            'entity_type' => $entityType,
            'entity_id' => $entityId
        ], 1000, 0);
    }
    
    /**
     * Get recent activity for a user
     */
    public function getUserActivity(int $userId, int $limit = 50): array
    {
        return $this->query(['user_id' => $userId], $limit, 0);
    }
    
    /**
     * Clean old audit logs (GDPR compliance)
     */
    public function cleanup(int $daysToKeep = 365): int
    {
        $cutoff = date('Y-m-d', strtotime("-{$daysToKeep} days"));
        
        $stmt = $this->pdo->prepare("
            DELETE FROM audit_logs 
            WHERE created_at < ?
        ");
        $stmt->execute([$cutoff]);
        
        return $stmt->rowCount();
    }
    
    /**
     * Ensure audit_logs table exists
     */
    private function ensureTableExists(): void
    {
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS audit_logs (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NULL,
                tenant_id INT NULL,
                action VARCHAR(50) NOT NULL,
                entity_type VARCHAR(100) NOT NULL,
                entity_id INT NULL,
                old_values JSON NULL,
                new_values JSON NULL,
                description TEXT NULL,
                ip_address VARCHAR(45) NULL,
                user_agent VARCHAR(255) NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_user_id (user_id),
                INDEX idx_tenant_id (tenant_id),
                INDEX idx_action (action),
                INDEX idx_entity (entity_type, entity_id),
                INDEX idx_created (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }
}

/**
 * Global audit helper functions
 */

if (!function_exists('audit_log')) {
    /**
     * Create audit log entry
     * 
     * @param \PDO $pdo Database connection
     * @param string $action Action type
     * @param string $entityType Entity being modified
     * @param int|null $entityId Entity ID
     * @param array|null $oldValues Previous values
     * @param array|null $newValues New values
     * @param string|null $description Optional description
     */
    function audit_log(
        \PDO $pdo,
        string $action,
        string $entityType,
        ?int $entityId = null,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?string $description = null
    ): void {
        $userId = $_SESSION['user_id'] ?? 0;
        $tenantId = $_SESSION['tenant_id'] ?? null;
        
        $logger = new AuditLogger($pdo, $userId, $tenantId);
        $logger->log($action, $entityType, $entityId, $oldValues, $newValues, $description);
    }
}

if (!function_exists('audit_login')) {
    /**
     * Log login attempt
     */
    function audit_login(\PDO $pdo, bool $success, ?string $username = null, ?string $failureReason = null): void
    {
        $userId = $_SESSION['user_id'] ?? 0;
        $tenantId = $_SESSION['tenant_id'] ?? null;
        
        $logger = new AuditLogger($pdo, $userId, $tenantId);
        $logger->logLogin($success, $username, $failureReason);
    }
}
