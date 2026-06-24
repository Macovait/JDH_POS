<?php
/**
 * Audit Logger - Comprehensive Security and Access Logging
 * Tracks all data access and modifications for compliance
 * 
 * @package Jakababa\Security
 * @version 1.0.0
 */

class AuditLogger {
    private static $instance = null;
    private $db;
    private $tenant_id;
    private $user_id;
    private $session_id;
    
    private function __construct() {
        $this->db = $this->getDbConnection();
        $this->initializeContext();
    }
    
    public static function getInstance(): self {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function initializeContext(): void {
        $this->tenant_id = $_SESSION['tenant_id'] ?? null;
        $this->user_id = $_SESSION['user_id'] ?? null;
        $this->session_id = session_id();
    }
    
    /**
     * Log data access
     */
    public function logDataAccess(string $action, string $resource, array $context = []): void {
        $this->log('data_access', $action, $resource, $context);
    }
    
    /**
     * Log data modification
     */
    public function logDataModification(string $action, string $resource, array $old_data = [], array $new_data = []): void {
        $context = [
            'old_data' => $old_data,
            'new_data' => $new_data
        ];
        $this->log('data_modification', $action, $resource, $context);
    }
    
    /**
     * Log authentication event
     */
    public function logAuth(string $action, string $resource, array $context = []): void {
        $this->log('authentication', $action, $resource, $context);
    }
    
    /**
     * Log permission check
     */
    public function logPermissionCheck(string $permission, bool $granted, array $context = []): void {
        $log_context = array_merge($context, [
            'permission' => $permission,
            'granted' => $granted
        ]);
        $this->log('permission_check', 'check', 'permission', $log_context);
    }
    
    /**
     * Log security violation
     */
    public function logSecurityViolation(string $violation, array $context = []): void {
        $log_context = array_merge($context, [
            'severity' => 'high',
            'type' => 'security_violation'
        ]);
        $this->log('security', $violation, 'system', $log_context);
        
        // Also send immediate alert for critical violations
        $this->sendSecurityAlert($violation, $log_context);
    }
    
    /**
     * General logging method
     */
    private function log(string $category, string $action, string $resource, array $context = []): void {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO audit_logs (
                    tenant_id, user_id, session_id, category, action, 
                    resource, context, ip_address, user_agent, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            
            $stmt->execute([
                $this->tenant_id,
                $this->user_id,
                $this->session_id,
                $category,
                $action,
                $resource,
                json_encode($context),
                $_SERVER['REMOTE_ADDR'] ?? '',
                $_SERVER['HTTP_USER_AGENT'] ?? ''
            ]);
            
        } catch (Exception $e) {
            error_log("Audit logging failed: " . $e->getMessage());
        }
    }
    
    /**
     * Send security alert for critical violations
     */
    private function sendSecurityAlert(string $violation, array $context): void {
        // Log to error log for immediate attention
        $message = "SECURITY ALERT: {$violation} | User: {$this->user_id} | Tenant: {$this->tenant_id} | IP: " . ($_SERVER['REMOTE_ADDR'] ?? '');
        error_log($message);
        
        // Could add email/SMS alerts here for critical violations
        if (function_exists('send_security_alert')) {
            send_security_alert($violation, $context);
        }
    }
    
    /**
     * Get audit logs for a tenant
     */
    public function getAuditLogs(array $filters = [], int $limit = 100, int $offset = 0): array {
        try {
            $where_conditions = ["tenant_id = ?"];
            $params = [$this->tenant_id];
            
            // Apply filters
            if (!empty($filters['category'])) {
                $where_conditions[] = "category = ?";
                $params[] = $filters['category'];
            }
            
            if (!empty($filters['user_id'])) {
                $where_conditions[] = "user_id = ?";
                $params[] = $filters['user_id'];
            }
            
            if (!empty($filters['date_from'])) {
                $where_conditions[] = "created_at >= ?";
                $params[] = $filters['date_from'];
            }
            
            if (!empty($filters['date_to'])) {
                $where_conditions[] = "created_at <= ?";
                $params[] = $filters['date_to'];
            }
            
            $where_clause = implode(' AND ', $where_conditions);
            
            $stmt = $this->db->prepare("
                SELECT * FROM audit_logs 
                WHERE {$where_clause}
                ORDER BY created_at DESC
                LIMIT ? OFFSET ?
            ");
            
            $params[] = $limit;
            $params[] = $offset;
            $stmt->execute($params);
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (Exception $e) {
            error_log("Failed to get audit logs: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get security violations
     */
    public function getSecurityViolations(int $days = 30): array {
        try {
            $stmt = $this->db->prepare("
                SELECT * FROM audit_logs 
                WHERE tenant_id = ? AND category = 'security'
                AND created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
                ORDER BY created_at DESC
            ");
            
            $stmt->execute([$this->tenant_id, $days]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (Exception $e) {
            error_log("Failed to get security violations: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get user activity summary
     */
    public function getUserActivitySummary(int $days = 7): array {
        try {
            $stmt = $this->db->prepare("
                SELECT 
                    user_id,
                    COUNT(*) as total_actions,
                    COUNT(DISTINCT DATE(created_at)) as active_days,
                    MAX(created_at) as last_activity
                FROM audit_logs 
                WHERE tenant_id = ? 
                AND created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
                GROUP BY user_id
                ORDER BY total_actions DESC
            ");
            
            $stmt->execute([$this->tenant_id, $days]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (Exception $e) {
            error_log("Failed to get user activity summary: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Create audit log table if not exists
     */
    public function createAuditTable(): void {
        try {
            $sql = "
                CREATE TABLE IF NOT EXISTS audit_logs (
                    id BIGINT AUTO_INCREMENT PRIMARY KEY,
                    tenant_id INT NOT NULL,
                    user_id INT,
                    session_id VARCHAR(255),
                    category VARCHAR(50) NOT NULL,
                    action VARCHAR(100) NOT NULL,
                    resource VARCHAR(255) NOT NULL,
                    context JSON,
                    ip_address VARCHAR(45),
                    user_agent TEXT,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_tenant_user (tenant_id, user_id),
                    INDEX idx_category (category),
                    INDEX idx_created (created_at),
                    INDEX idx_resource (resource)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ";
            
            $this->db->exec($sql);
            
        } catch (Exception $e) {
            error_log("Failed to create audit table: " . $e->getMessage());
        }
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
     * Clean old audit logs
     */
    public function cleanOldLogs(int $days_to_keep = 90): void {
        try {
            $stmt = $this->db->prepare("
                DELETE FROM audit_logs 
                WHERE tenant_id = ? 
                AND created_at < DATE_SUB(NOW(), INTERVAL ? DAY)
            ");
            
            $stmt->execute([$this->tenant_id, $days_to_keep]);
            
        } catch (Exception $e) {
            error_log("Failed to clean old audit logs: " . $e->getMessage());
        }
    }
}
