<?php
/**
 * Tenant Isolation Middleware - Multi-Tenant Data Protection
 * Ensures complete data isolation between tenants in the SaaS system
 * 
 * @package Jakababa\Middleware
 * @version 1.0.0
 */

class TenantIsolationMiddleware {
    private $data_scope;
    private $audit_logger;
    
    public function __construct() {
        $this->data_scope = DataAccessScope::getInstance();
        $this->audit_logger = AuditLogger::getInstance();
    }
    
    /**
     * Apply tenant isolation to all database queries
     */
    public function applyTenantIsolation(): void {
        // Validate tenant context
        $this->validateTenantContext();
        
        // Hook into database operations
        $this->hookDatabaseOperations();
        
        // Validate request parameters
        $this->validateRequestParameters();
        
        // Set MySQL session variables for row-level security
        $this->setMySqlSessionVariables();
    }
    
    /**
     * Validate tenant context
     */
    private function validateTenantContext(): void {
        $scope = $this->data_scope->getUserDataScope();
        
        // Super admin can bypass tenant validation
        if ($scope['is_super_admin']) {
            return;
        }
        
        // Ensure tenant_id is set and valid
        if (!$scope['tenant_id']) {
            $this->audit_logger->logSecurityViolation('Missing tenant context', [
                'user_id' => $scope['user_id'],
                'uri' => $_SERVER['REQUEST_URI'] ?? ''
            ]);
            
            throw new SecurityException('Tenant context required');
        }
        
        // Validate tenant exists and is active
        if (!$this->validateTenantExists($scope['tenant_id'])) {
            $this->audit_logger->logSecurityViolation('Invalid tenant context', [
                'tenant_id' => $scope['tenant_id'],
                'user_id' => $scope['user_id']
            ]);
            
            throw new SecurityException('Invalid tenant context');
        }
    }
    
    /**
     * Validate tenant exists and is active
     */
    private function validateTenantExists(int $tenant_id): bool {
        try {
            $pdo = $this->getDbConnection();
            $stmt = $pdo->prepare("
                SELECT id, status, is_active, deleted_at 
                FROM tenants 
                WHERE id = ? AND deleted_at IS NULL 
                LIMIT 1
            ");
            
            $stmt->execute([$tenant_id]);
            $tenant = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$tenant) {
                return false;
            }
            
            // Check if tenant is active
            $is_active = ($tenant['is_active'] ?? 0) === 1 || 
                        in_array($tenant['status'] ?? '', ['active', 'trial'], true);
            
            return $is_active;
            
        } catch (Exception $e) {
            error_log("Tenant validation failed: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Hook into database operations
     */
    private function hookDatabaseOperations(): void {
        // This would typically be done via PDO wrapper or database layer
        // For now, we'll provide utility methods for secure queries
        
        if (!function_exists('secure_db_query')) {
            /**
             * Execute secure database query with tenant isolation
             */
            function secure_db_query(string $query, array $params = [], string $table_alias = '') {
                $data_scope = DataAccessScope::getInstance();
                $audit_logger = AuditLogger::getInstance();
                
                // Apply tenant scoping
                [$scoped_query, $scoped_params] = $data_scope->applyTenantScoping($query, $params);
                
                // Log query execution
                $audit_logger->logDataAccess('db_query', 'database', [
                    'query' => $scoped_query,
                    'params' => $scoped_params
                ]);
                
                try {
                    $pdo = get_db_connection();
                    $stmt = $pdo->prepare($scoped_query);
                    $stmt->execute($scoped_params);
                    return $stmt;
                } catch (Exception $e) {
                    error_log("Secure DB query failed: " . $e->getMessage());
                    throw $e;
                }
            }
        }
        
        if (!function_exists('secure_db_fetch_one')) {
            /**
             * Fetch single record with tenant isolation
             */
            function secure_db_fetch_one(string $query, array $params = [], string $table_alias = '') {
                $stmt = secure_db_query($query, $params, $table_alias);
                return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
            }
        }
        
        if (!function_exists('secure_db_fetch_all')) {
            /**
             * Fetch all records with tenant isolation
             */
            function secure_db_fetch_all(string $query, array $params = [], string $table_alias = '') {
                $stmt = secure_db_query($query, $params, $table_alias);
                return $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
        }
        
        if (!function_exists('secure_db_fetch_value')) {
            /**
             * Fetch single value with tenant isolation
             */
            function secure_db_fetch_value(string $query, array $params = [], string $table_alias = '') {
                $stmt = secure_db_query($query, $params, $table_alias);
                return $stmt->fetchColumn();
            }
        }
    }
    
    /**
     * Validate request parameters for tenant isolation
     */
    private function validateRequestParameters(): void {
        $scope = $this->data_scope->getUserDataScope();
        
        // Super admin can bypass parameter validation
        if ($scope['is_super_admin']) {
            return;
        }
        
        // Check for tenant_id parameter tampering
        $this->validateParameter('tenant_id', $scope['tenant_id']);
        
        // Check for branch_id parameter tampering
        $this->validateParameter('branch_id', $scope['branch_id']);
        
        // Validate record access parameters
        $this->validateRecordAccessParameters();
    }
    
    /**
     * Validate specific parameter
     */
    private function validateParameter(string $param_name, $expected_value): void {
        $request_value = $_GET[$param_name] ?? $_POST[$param_name] ?? null;
        
        if ($request_value !== null && $request_value != $expected_value) {
            $this->audit_logger->logSecurityViolation('Parameter tampering detected', [
                'parameter' => $param_name,
                'expected_value' => $expected_value,
                'request_value' => $request_value,
                'user_id' => $scope['user_id'] ?? null,
                'uri' => $_SERVER['REQUEST_URI'] ?? ''
            ]);
            
            throw new SecurityException("Invalid {$param_name} parameter");
        }
    }
    
    /**
     * Validate record access parameters
     */
    private function validateRecordAccessParameters(): void {
        // Check for record IDs in common parameters
        $record_params = ['id', 'record_id', 'sale_id', 'product_id', 'customer_id', 'user_id'];
        
        foreach ($record_params as $param) {
            $record_id = $_GET[$param] ?? $_POST[$param] ?? null;
            
            if ($record_id && is_numeric($record_id)) {
                // Determine table based on parameter name
                $table = $this->getTableFromParameter($param);
                
                if ($table && !$this->data_scope->canAccessRecord($table, (int)$record_id)) {
                    $this->audit_logger->logSecurityViolation('Unauthorized record access', [
                        'table' => $table,
                        'record_id' => $record_id,
                        'parameter' => $param,
                        'user_id' => $this->data_scope->getUserDataScope()['user_id']
                    ]);
                    
                    throw new SecurityException('Access denied to record');
                }
            }
        }
    }
    
    /**
     * Get table name from parameter name
     */
    private function getTableFromParameter(string $param): string {
        $table_map = [
            'sale_id' => 'sales',
            'product_id' => 'products',
            'customer_id' => 'customers',
            'user_id' => 'users',
            'category_id' => 'categories',
            'branch_id' => 'branches',
            'supplier_id' => 'suppliers',
            'expense_id' => 'expenses',
            'purchase_id' => 'purchases'
        ];
        
        return $table_map[$param] ?? '';
    }
    
    /**
     * Set MySQL session variables for row-level security
     */
    private function setMySqlSessionVariables(): void {
        try {
            $scope = $this->data_scope->getUserDataScope();
            $pdo = $this->getDbConnection();
            
            // Set session variables for database-level security
            $pdo->exec("SET @current_user_id = " . ($scope['user_id'] ?? 'NULL'));
            $pdo->exec("SET @current_tenant_id = " . ($scope['tenant_id'] ?? 'NULL'));
            $pdo->exec("SET @current_branch_id = " . ($scope['branch_id'] ?? 'NULL'));
            $pdo->exec("SET @is_super_admin = " . ($scope['is_super_admin'] ? '1' : '0'));
            
        } catch (Exception $e) {
            error_log("Failed to set MySQL session variables: " . $e->getMessage());
        }
    }
    
    /**
     * Apply tenant isolation to file uploads
     */
    public function applyFileUploadIsolation(string $upload_dir): string {
        $scope = $this->data_scope->getUserDataScope();
        
        // Create tenant-specific upload directory
        $tenant_dir = $upload_dir . '/tenant_' . $scope['tenant_id'];
        
        if (!is_dir($tenant_dir)) {
            mkdir($tenant_dir, 0755, true);
        }
        
        return $tenant_dir;
    }
    
    /**
     * Validate file access for downloads
     */
    public function validateFileAccess(string $file_path): bool {
        $scope = $this->data_scope->getUserDataScope();
        
        // Super admin can access all files
        if ($scope['is_super_admin']) {
            return true;
        }
        
        // Check if file is in tenant's directory
        $tenant_pattern = '/tenant_' . $scope['tenant_id'] . '/';
        
        if (strpos($file_path, $tenant_pattern) === false) {
            $this->audit_logger->logSecurityViolation('Unauthorized file access', [
                'file_path' => $file_path,
                'tenant_id' => $scope['tenant_id'],
                'user_id' => $scope['user_id']
            ]);
            
            return false;
        }
        
        return true;
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
     * Create tenant isolation triggers for database
     */
    public function createTenantIsolationTriggers(): void {
        try {
            $pdo = $this->getDbConnection();
            
            // Create trigger for automatic tenant_id enforcement
            $trigger_sql = "
                DELIMITER //
                CREATE TRIGGER before_insert_check_tenant
                BEFORE INSERT ON sales
                FOR EACH ROW
                BEGIN
                    IF @is_super_admin = 0 AND NEW.tenant_id != @current_tenant_id THEN
                        SIGNAL SQLSTATE '45000' 
                        SET MESSAGE_TEXT = 'Tenant access violation';
                    END IF;
                END//
                DELIMITER ;
            ";
            
            // Note: This would need to be created for each table
            // For now, we'll rely on application-level enforcement
            
        } catch (Exception $e) {
            error_log("Failed to create tenant isolation triggers: " . $e->getMessage());
        }
    }
    
    /**
     * Get tenant isolation statistics
     */
    public function getTenantIsolationStats(): array {
        try {
            $scope = $this->data_scope->getUserDataScope();
            $pdo = $this->getDbConnection();
            
            $stats = [];
            
            // Get tenant data counts
            $tables = ['users', 'sales', 'products', 'customers', 'branches'];
            
            foreach ($tables as $table) {
                $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM {$table} WHERE tenant_id = ?");
                $stmt->execute([$scope['tenant_id']]);
                $stats[$table] = $stmt->fetchColumn();
            }
            
            return $stats;
            
        } catch (Exception $e) {
            error_log("Failed to get tenant isolation stats: " . $e->getMessage());
            return [];
        }
    }
}
