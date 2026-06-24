<?php
/**
 * Secure Query Builder - Automatic Tenant Scoping for Database Queries
 * Prevents data leakage by automatically adding tenant/branch filters
 * 
 * @package Jakababa\Security
 * @version 1.0.0
 */

class SecureQueryBuilder {
    private $db;
    private $data_scope;
    private $audit_logger;
    
    public function __construct() {
        $this->db = $this->getDbConnection();
        $this->data_scope = DataAccessScope::getInstance();
        $this->audit_logger = AuditLogger::getInstance();
    }
    
    /**
     * Execute a secure SELECT query with automatic tenant scoping
     */
    public function select(string $table, array $columns = ['*'], array $where = [], array $joins = [], string $order_by = '', int $limit = 0, int $offset = 0): array {
        // Build base query
        $query = "SELECT " . implode(', ', $columns) . " FROM {$table}";
        $params = [];
        
        // Add JOINs
        foreach ($joins as $join) {
            $query .= " " . $join;
        }
        
        // Apply tenant scoping
        [$scoped_query, $scoped_params] = $this->data_scope->applyTenantScoping($query, $params);
        
        // Add WHERE conditions
        if (!empty($where)) {
            $where_clause = $this->buildWhereClause($where);
            if (stripos($scoped_query, 'WHERE') === false) {
                $scoped_query .= " WHERE " . $where_clause['sql'];
            } else {
                $scoped_query .= " AND " . $where_clause['sql'];
            }
            $scoped_params = array_merge($scoped_params, $where_clause['params']);
        }
        
        // Add ORDER BY
        if ($order_by) {
            $scoped_query .= " ORDER BY " . $order_by;
        }
        
        // Add LIMIT and OFFSET
        if ($limit > 0) {
            $scoped_query .= " LIMIT ?";
            $scoped_params[] = $limit;
            
            if ($offset > 0) {
                $scoped_query .= " OFFSET ?";
                $scoped_params[] = $offset;
            }
        }
        
        // Log data access
        $this->audit_logger->logDataAccess('select', $table, [
            'query' => $scoped_query,
            'params' => $scoped_params
        ]);
        
        // Execute query
        try {
            $stmt = $this->db->prepare($scoped_query);
            $stmt->execute($scoped_params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Secure SELECT failed: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Execute a secure INSERT query
     */
    public function insert(string $table, array $data): int {
        // Add tenant context to data
        $data = $this->addTenantContext($data);
        
        // Validate tenant scoping
        if (!$this->validateDataAccess($table, $data)) {
            throw new SecurityException("Unauthorized data access attempt");
        }
        
        $columns = array_keys($data);
        $placeholders = str_repeat('?,', count($columns) - 1) . '?';
        
        $query = "INSERT INTO {$table} (" . implode(', ', $columns) . ") VALUES ({$placeholders})";
        $params = array_values($data);
        
        // Log data modification
        $this->audit_logger->logDataModification('insert', $table, [], $data);
        
        try {
            $stmt = $this->db->prepare($query);
            $stmt->execute($params);
            return (int) $this->db->lastInsertId();
        } catch (Exception $e) {
            error_log("Secure INSERT failed: " . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * Execute a secure UPDATE query
     */
    public function update(string $table, array $data, array $where): int {
        // Add tenant context to WHERE clause
        $where = $this->addTenantContextToWhere($where);
        
        // Validate tenant scoping
        if (!$this->validateDataAccess($table, $where)) {
            throw new SecurityException("Unauthorized data access attempt");
        }
        
        // Get old data for audit
        $old_data = $this->getOldData($table, $where);
        
        $set_clause = $this->buildSetClause($data);
        $where_clause = $this->buildWhereClause($where);
        
        $query = "UPDATE {$table} SET {$set_clause['sql']} WHERE {$where_clause['sql']}";
        $params = array_merge($set_clause['params'], $where_clause['params']);
        
        // Log data modification
        $this->audit_logger->logDataModification('update', $table, $old_data, $data);
        
        try {
            $stmt = $this->db->prepare($query);
            $stmt->execute($params);
            return $stmt->rowCount();
        } catch (Exception $e) {
            error_log("Secure UPDATE failed: " . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * Execute a secure DELETE query
     */
    public function delete(string $table, array $where): int {
        // Add tenant context to WHERE clause
        $where = $this->addTenantContextToWhere($where);
        
        // Validate tenant scoping
        if (!$this->validateDataAccess($table, $where)) {
            throw new SecurityException("Unauthorized data access attempt");
        }
        
        // Get old data for audit
        $old_data = $this->getOldData($table, $where);
        
        $where_clause = $this->buildWhereClause($where);
        $query = "DELETE FROM {$table} WHERE {$where_clause['sql']}";
        $params = $where_clause['params'];
        
        // Log data modification
        $this->audit_logger->logDataModification('delete', $table, $old_data, []);
        
        try {
            $stmt = $this->db->prepare($query);
            $stmt->execute($params);
            return $stmt->rowCount();
        } catch (Exception $e) {
            error_log("Secure DELETE failed: " . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * Add tenant context to data
     */
    private function addTenantContext(array $data): array {
        $scope = $this->data_scope->getUserDataScope();
        
        if (!$scope['is_super_admin']) {
            $data['tenant_id'] = $scope['tenant_id'];
            
            if ($this->shouldAddBranchContext()) {
                $data['branch_id'] = $scope['branch_id'];
            }
        }
        
        return $data;
    }
    
    /**
     * Add tenant context to WHERE clause
     */
    private function addTenantContextToWhere(array $where): array {
        $scope = $this->data_scope->getUserDataScope();
        
        if (!$scope['is_super_admin']) {
            $where['tenant_id'] = $scope['tenant_id'];
            
            if ($this->shouldAddBranchContext()) {
                $where['branch_id'] = $scope['branch_id'];
            }
        }
        
        return $where;
    }
    
    /**
     * Check if branch context should be added
     */
    private function shouldAddBranchContext(): bool {
        // Add branch context for tables that are branch-scoped
        $caller = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3)[2]['function'] ?? '';
        $branch_scoped_operations = ['select', 'insert', 'update', 'delete'];
        
        return in_array($caller, $branch_scoped_operations);
    }
    
    /**
     * Validate data access
     */
    private function validateDataAccess(string $table, array $data): bool {
        // For super admin, allow all
        $scope = $this->data_scope->getUserDataScope();
        if ($scope['is_super_admin']) {
            return true;
        }
        
        // Check tenant_id matches
        if (isset($data['tenant_id']) && $data['tenant_id'] != $scope['tenant_id']) {
            $this->audit_logger->logSecurityViolation('Tenant ID mismatch', [
                'table' => $table,
                'data_tenant_id' => $data['tenant_id'],
                'user_tenant_id' => $scope['tenant_id']
            ]);
            return false;
        }
        
        // Check branch_id if present
        if (isset($data['branch_id']) && $data['branch_id'] != $scope['branch_id']) {
            $this->audit_logger->logSecurityViolation('Branch ID mismatch', [
                'table' => $table,
                'data_branch_id' => $data['branch_id'],
                'user_branch_id' => $scope['branch_id']
            ]);
            return false;
        }
        
        return true;
    }
    
    /**
     * Build WHERE clause
     */
    private function buildWhereClause(array $where): array {
        $conditions = [];
        $params = [];
        
        foreach ($where as $column => $value) {
            if (is_array($value)) {
                $placeholders = str_repeat('?,', count($value) - 1) . '?';
                $conditions[] = "{$column} IN ({$placeholders})";
                $params = array_merge($params, $value);
            } else {
                $conditions[] = "{$column} = ?";
                $params[] = $value;
            }
        }
        
        return [
            'sql' => implode(' AND ', $conditions),
            'params' => $params
        ];
    }
    
    /**
     * Build SET clause
     */
    private function buildSetClause(array $data): array {
        $assignments = [];
        $params = [];
        
        foreach ($data as $column => $value) {
            $assignments[] = "{$column} = ?";
            $params[] = $value;
        }
        
        return [
            'sql' => implode(', ', $assignments),
            'params' => $params
        ];
    }
    
    /**
     * Get old data for audit
     */
    private function getOldData(string $table, array $where): array {
        try {
            $where_clause = $this->buildWhereClause($where);
            $query = "SELECT * FROM {$table} WHERE {$where_clause['sql']} LIMIT 1";
            
            $stmt = $this->db->prepare($query);
            $stmt->execute($where_clause['params']);
            return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        } catch (Exception $e) {
            error_log("Failed to get old data: " . $e->getMessage());
            return [];
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
}
