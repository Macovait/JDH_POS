<?php
/**
 * Audit Trail Manager - Comprehensive Audit System
 * Manages audit logs, security monitoring, and compliance reporting
 * 
 * @package Jakababa\Security
 * @version 1.0.0
 */

class AuditTrailManager {
    private $db;
    private $audit_logger;
    private $data_scope;
    
    public function __construct() {
        $this->db = $this->getDbConnection();
        $this->audit_logger = AuditLogger::getInstance();
        $this->data_scope = DataAccessScope::getInstance();
    }
    
    /**
     * Create comprehensive audit trail system
     */
    public function initializeAuditSystem(): void {
        $this->createAuditTables();
        $this->createAuditTriggers();
        $this->setupAuditRetention();
        $this->initializeAuditMonitoring();
    }
    
    /**
     * Create audit tables
     */
    private function createAuditTables(): void {
        try {
            // Main audit log table
            $this->db->exec("
                CREATE TABLE IF NOT EXISTS audit_logs (
                    id BIGINT AUTO_INCREMENT PRIMARY KEY,
                    tenant_id INT NOT NULL,
                    user_id INT,
                    session_id VARCHAR(255),
                    category VARCHAR(50) NOT NULL,
                    action VARCHAR(100) NOT NULL,
                    resource VARCHAR(255) NOT NULL,
                    resource_id INT,
                    context JSON,
                    ip_address VARCHAR(45),
                    user_agent TEXT,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_tenant_user (tenant_id, user_id),
                    INDEX idx_category (category),
                    INDEX idx_created (created_at),
                    INDEX idx_resource (resource, resource_id),
                    INDEX idx_action (action)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
            
            // Security events table
            $this->db->exec("
                CREATE TABLE IF NOT EXISTS security_events (
                    id BIGINT AUTO_INCREMENT PRIMARY KEY,
                    tenant_id INT NOT NULL,
                    user_id INT,
                    event_type VARCHAR(50) NOT NULL,
                    severity ENUM('low', 'medium', 'high', 'critical') NOT NULL,
                    description TEXT NOT NULL,
                    context JSON,
                    ip_address VARCHAR(45),
                    user_agent TEXT,
                    resolved BOOLEAN DEFAULT FALSE,
                    resolved_by INT,
                    resolved_at TIMESTAMP NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_tenant_severity (tenant_id, severity),
                    INDEX idx_event_type (event_type),
                    INDEX idx_resolved (resolved),
                    INDEX idx_created (created_at)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
            
            // Data access log table
            $this->db->exec("
                CREATE TABLE IF NOT EXISTS data_access_logs (
                    id BIGINT AUTO_INCREMENT PRIMARY KEY,
                    tenant_id INT NOT NULL,
                    user_id INT,
                    table_name VARCHAR(100) NOT NULL,
                    operation ENUM('SELECT', 'INSERT', 'UPDATE', 'DELETE') NOT NULL,
                    record_id INT,
                    query_hash VARCHAR(64),
                    execution_time DECIMAL(10,4),
                    rows_affected INT,
                    context JSON,
                    ip_address VARCHAR(45),
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_tenant_table (tenant_id, table_name),
                    INDEX idx_operation (operation),
                    INDEX idx_user_operation (user_id, operation),
                    INDEX idx_created (created_at)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
            
            // Permission check log table
            $this->db->exec("
                CREATE TABLE IF NOT EXISTS permission_checks (
                    id BIGINT AUTO_INCREMENT PRIMARY KEY,
                    tenant_id INT NOT NULL,
                    user_id INT,
                    permission VARCHAR(255) NOT NULL,
                    granted BOOLEAN NOT NULL,
                    resource VARCHAR(255),
                    context JSON,
                    ip_address VARCHAR(45),
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_tenant_permission (tenant_id, permission),
                    INDEX idx_user_granted (user_id, granted),
                    INDEX idx_created (created_at)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
            
        } catch (Exception $e) {
            error_log("Failed to create audit tables: " . $e->getMessage());
        }
    }
    
    /**
     * Create audit triggers for automatic logging
     */
    private function createAuditTriggers(): void {
        try {
            // Sales table trigger
            $this->db->exec("
                CREATE TRIGGER IF NOT EXISTS sales_audit_insert
                AFTER INSERT ON sales
                FOR EACH ROW
                BEGIN
                    INSERT INTO data_access_logs (tenant_id, user_id, table_name, operation, record_id, context)
                    VALUES (NEW.tenant_id, NEW.created_by, 'sales', 'INSERT', NEW.id, 
                           JSON_OBJECT('total', NEW.total, 'customer_id', NEW.customer_id, 'status', NEW.status));
                END
            ");
            
            $this->db->exec("
                CREATE TRIGGER IF NOT EXISTS sales_audit_update
                AFTER UPDATE ON sales
                FOR EACH ROW
                BEGIN
                    INSERT INTO data_access_logs (tenant_id, user_id, table_name, operation, record_id, context)
                    VALUES (NEW.tenant_id, NEW.updated_by, 'sales', 'UPDATE', NEW.id,
                           JSON_OBJECT('old_status', OLD.status, 'new_status', NEW.status, 'old_total', OLD.total, 'new_total', NEW.total));
                END
            ");
            
            // Users table trigger
            $this->db->exec("
                CREATE TRIGGER IF NOT EXISTS users_audit_insert
                AFTER INSERT ON users
                FOR EACH ROW
                BEGIN
                    INSERT INTO data_access_logs (tenant_id, user_id, table_name, operation, record_id, context)
                    VALUES (NEW.tenant_id, NEW.id, 'users', 'INSERT', NEW.id,
                           JSON_OBJECT('username', NEW.username, 'role', NEW.role, 'email', NEW.email));
                END
            ");
            
            $this->db->exec("
                CREATE TRIGGER IF NOT EXISTS users_audit_update
                AFTER UPDATE ON users
                FOR EACH ROW
                BEGIN
                    INSERT INTO data_access_logs (tenant_id, user_id, table_name, operation, record_id, context)
                    VALUES (NEW.tenant_id, NEW.id, 'users', 'UPDATE', NEW.id,
                           JSON_OBJECT('old_status', OLD.status, 'new_status', NEW.status, 'old_role', OLD.role, 'new_role', NEW.role));
                END
            ");
            
            // Products table trigger
            $this->db->exec("
                CREATE TRIGGER IF NOT EXISTS products_audit_insert
                AFTER INSERT ON products
                FOR EACH ROW
                BEGIN
                    INSERT INTO data_access_logs (tenant_id, user_id, table_name, operation, record_id, context)
                    VALUES (NEW.tenant_id, NEW.created_by, 'products', 'INSERT', NEW.id,
                           JSON_OBJECT('name', NEW.name, 'price', NEW.price, 'category_id', NEW.category_id));
                END
            ");
            
            $this->db->exec("
                CREATE TRIGGER IF NOT EXISTS products_audit_update
                AFTER UPDATE ON products
                FOR EACH ROW
                BEGIN
                    INSERT INTO data_access_logs (tenant_id, user_id, table_name, operation, record_id, context)
                    VALUES (NEW.tenant_id, NEW.updated_by, 'products', 'UPDATE', NEW.id,
                           JSON_OBJECT('old_price', OLD.price, 'new_price', NEW.price, 'old_status', OLD.status, 'new_status', NEW.status));
                END
            ");
            
        } catch (Exception $e) {
            error_log("Failed to create audit triggers: " . $e->getMessage());
        }
    }
    
    /**
     * Setup audit retention policy
     */
    private function setupAuditRetention(): void {
        // This would typically be run as a scheduled job
        // For now, we'll create the stored procedure
        try {
            $this->db->exec("
                CREATE PROCEDURE IF NOT EXISTS CleanOldAuditLogs()
                BEGIN
                    -- Clean audit logs older than 1 year
                    DELETE FROM audit_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL 1 YEAR);
                    
                    -- Clean security events older than 2 years (keep critical events)
                    DELETE FROM security_events 
                    WHERE created_at < DATE_SUB(NOW(), INTERVAL 2 YEAR) 
                    AND severity IN ('low', 'medium');
                    
                    -- Clean data access logs older than 6 months
                    DELETE FROM data_access_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL 6 MONTH);
                    
                    -- Clean permission checks older than 3 months
                    DELETE FROM permission_checks WHERE created_at < DATE_SUB(NOW(), INTERVAL 3 MONTH);
                END
            ");
        } catch (Exception $e) {
            error_log("Failed to setup audit retention: " . $e->getMessage());
        }
    }
    
    /**
     * Initialize audit monitoring
     */
    private function initializeAuditMonitoring(): void {
        // Set up monitoring for suspicious activities
        $this->setupSuspiciousActivityMonitoring();
        $this->setupDataAccessMonitoring();
        $this->setupSecurityEventMonitoring();
    }
    
    /**
     * Monitor suspicious activities
     */
    private function setupSuspiciousActivityMonitoring(): void {
        // This would typically run periodically
        // For now, we'll create the monitoring function
    }
    
    /**
     * Monitor data access patterns
     */
    private function setupDataAccessMonitoring(): void {
        // Monitor for unusual data access patterns
    }
    
    /**
     * Monitor security events
     */
    private function setupSecurityEventMonitoring(): void {
        // Monitor for security violations and threats
    }
    
    /**
     * Log data access with detailed context
     */
    public function logDataAccess(string $table, string $operation, array $context = []): void {
        try {
            $scope = $this->data_scope->getUserDataScope();
            
            $stmt = $this->db->prepare("
                INSERT INTO data_access_logs (
                    tenant_id, user_id, table_name, operation, record_id, 
                    query_hash, execution_time, rows_affected, context, ip_address
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $scope['tenant_id'],
                $scope['user_id'],
                $table,
                strtoupper($operation),
                $context['record_id'] ?? null,
                $context['query_hash'] ?? null,
                $context['execution_time'] ?? null,
                $context['rows_affected'] ?? null,
                json_encode($context),
                $_SERVER['REMOTE_ADDR'] ?? ''
            ]);
            
        } catch (Exception $e) {
            error_log("Failed to log data access: " . $e->getMessage());
        }
    }
    
    /**
     * Log security event
     */
    public function logSecurityEvent(string $event_type, string $severity, string $description, array $context = []): void {
        try {
            $scope = $this->data_scope->getUserDataScope();
            
            $stmt = $this->db->prepare("
                INSERT INTO security_events (
                    tenant_id, user_id, event_type, severity, description, 
                    context, ip_address, user_agent
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $scope['tenant_id'],
                $scope['user_id'],
                $event_type,
                $severity,
                $description,
                json_encode($context),
                $_SERVER['REMOTE_ADDR'] ?? '',
                $_SERVER['HTTP_USER_AGENT'] ?? ''
            ]);
            
            // Send immediate alert for critical events
            if ($severity === 'critical') {
                $this->sendSecurityAlert($event_type, $description, $context);
            }
            
        } catch (Exception $e) {
            error_log("Failed to log security event: " . $e->getMessage());
        }
    }
    
    /**
     * Log permission check
     */
    public function logPermissionCheck(string $permission, bool $granted, string $resource = null): void {
        try {
            $scope = $this->data_scope->getUserDataScope();
            
            $stmt = $this->db->prepare("
                INSERT INTO permission_checks (
                    tenant_id, user_id, permission, granted, resource, context, ip_address
                ) VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $scope['tenant_id'],
                $scope['user_id'],
                $permission,
                $granted,
                $resource,
                json_encode([
                    'uri' => $_SERVER['REQUEST_URI'] ?? '',
                    'method' => $_SERVER['REQUEST_METHOD'] ?? '',
                    'timestamp' => time()
                ]),
                $_SERVER['REMOTE_ADDR'] ?? ''
            ]);
            
        } catch (Exception $e) {
            error_log("Failed to log permission check: " . $e->getMessage());
        }
    }
    
    /**
     * Generate compliance report
     */
    public function generateComplianceReport(string $start_date, string $end_date): array {
        try {
            $scope = $this->data_scope->getUserDataScope();
            
            $report = [
                'period' => ['start' => $start_date, 'end' => $end_date],
                'tenant_id' => $scope['tenant_id'],
                'summary' => [],
                'security_events' => [],
                'data_access' => [],
                'permission_checks' => []
            ];
            
            // Get summary statistics
            $stmt = $this->db->prepare("
                SELECT 
                    COUNT(*) as total_audit_logs,
                    COUNT(DISTINCT user_id) as active_users,
                    COUNT(DISTINCT DATE(created_at)) as active_days
                FROM audit_logs 
                WHERE tenant_id = ? AND created_at BETWEEN ? AND ?
            ");
            $stmt->execute([$scope['tenant_id'], $start_date, $end_date]);
            $report['summary'] = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Get security events
            $stmt = $this->db->prepare("
                SELECT * FROM security_events 
                WHERE tenant_id = ? AND created_at BETWEEN ? AND ?
                ORDER BY created_at DESC
            ");
            $stmt->execute([$scope['tenant_id'], $start_date, $end_date]);
            $report['security_events'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Get data access summary
            $stmt = $this->db->prepare("
                SELECT 
                    table_name,
                    operation,
                    COUNT(*) as access_count,
                    COUNT(DISTINCT user_id) as unique_users
                FROM data_access_logs 
                WHERE tenant_id = ? AND created_at BETWEEN ? AND ?
                GROUP BY table_name, operation
                ORDER BY access_count DESC
            ");
            $stmt->execute([$scope['tenant_id'], $start_date, $end_date]);
            $report['data_access'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Get permission denied events
            $stmt = $this->db->prepare("
                SELECT permission, COUNT(*) as denial_count
                FROM permission_checks 
                WHERE tenant_id = ? AND granted = FALSE AND created_at BETWEEN ? AND ?
                GROUP BY permission
                ORDER BY denial_count DESC
            ");
            $stmt->execute([$scope['tenant_id'], $start_date, $end_date]);
            $report['permission_checks'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            return $report;
            
        } catch (Exception $e) {
            error_log("Failed to generate compliance report: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Send security alert
     */
    private function sendSecurityAlert(string $event_type, string $description, array $context): void {
        $message = "SECURITY ALERT: {$event_type} - {$description}";
        error_log($message);
        
        // Could integrate with email/SMS services here
        if (function_exists('send_admin_alert')) {
            send_admin_alert($message, $context);
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
     * Clean old audit logs
     */
    public function cleanOldLogs(): void {
        try {
            $this->db->exec("CALL CleanOldAuditLogs()");
        } catch (Exception $e) {
            error_log("Failed to clean old logs: " . $e->getMessage());
        }
    }
    
    /**
     * Get security dashboard data
     */
    public function getSecurityDashboard(): array {
        try {
            $scope = $this->data_scope->getUserDataScope();
            
            $dashboard = [
                'security_events' => [],
                'recent_activity' => [],
                'top_accessed_tables' => [],
                'permission_denials' => []
            ];
            
            // Get recent security events
            $stmt = $this->db->prepare("
                SELECT * FROM security_events 
                WHERE tenant_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                ORDER BY created_at DESC
                LIMIT 10
            ");
            $stmt->execute([$scope['tenant_id']]);
            $dashboard['security_events'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Get recent activity
            $stmt = $this->db->prepare("
                SELECT * FROM audit_logs 
                WHERE tenant_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
                ORDER BY created_at DESC
                LIMIT 20
            ");
            $stmt->execute([$scope['tenant_id']]);
            $dashboard['recent_activity'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Get top accessed tables
            $stmt = $this->db->prepare("
                SELECT table_name, COUNT(*) as access_count
                FROM data_access_logs 
                WHERE tenant_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                GROUP BY table_name
                ORDER BY access_count DESC
                LIMIT 10
            ");
            $stmt->execute([$scope['tenant_id']]);
            $dashboard['top_accessed_tables'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Get permission denials
            $stmt = $this->db->prepare("
                SELECT permission, COUNT(*) as denial_count
                FROM permission_checks 
                WHERE tenant_id = ? AND granted = FALSE AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                GROUP BY permission
                ORDER BY denial_count DESC
                LIMIT 10
            ");
            $stmt->execute([$scope['tenant_id']]);
            $dashboard['permission_denials'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            return $dashboard;
            
        } catch (Exception $e) {
            error_log("Failed to get security dashboard: " . $e->getMessage());
            return [];
        }
    }
}
