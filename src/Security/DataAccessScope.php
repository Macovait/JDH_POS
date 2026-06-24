<?php
/**
 * Data Access Scope - Row-Level Security Implementation
 * Ensures users can only access data they're authorized to see
 * 
 * @package Jakababa\Security
 * @version 1.0.0
 */

class DataAccessScope {
    private static $instance = null;
    private $tenant_id;
    private $branch_id;
    private $user_id;
    private $user_role;
    private $is_super_admin;
    private $permissions = [];
    
    private function __construct() {
        $this->initializeContext();
    }
    
    public static function getInstance(): self {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function initializeContext(): void {
        // Get context from session
        $this->tenant_id = $_SESSION['tenant_id'] ?? null;
        $this->branch_id = $_SESSION['branch_id'] ?? null;
        $this->user_id = $_SESSION['user_id'] ?? null;
        $this->user_role = $_SESSION['role'] ?? null;
        $this->is_super_admin = $_SESSION['is_super_admin'] ?? false;
        $this->permissions = $_SESSION['permissions'] ?? [];
        
        // Validate context
        if (!$this->tenant_id && !$this->is_super_admin) {
            throw new SecurityException('Invalid tenant context');
        }
    }
    
    /**
     * Get tenant WHERE clause for queries
     */
    public function getTenantWhere(string $table_alias = ''): string {
        if ($this->is_super_admin) {
            return '1=1'; // Super admin can see all
        }
        
        $alias = $table_alias ? $table_alias . '.' : '';
        $conditions = [];
        
        // Always scope by tenant
        if ($this->tenant_id) {
            $conditions[] = "{$alias}tenant_id = " . (int)$this->tenant_id;
        }
        
        // Scope by branch for certain tables
        if ($this->branch_id && $this->shouldScopeByBranch($table_alias)) {
            $conditions[] = "{$alias}branch_id = " . (int)$this->branch_id;
        }
        
        return empty($conditions) ? '1=0' : implode(' AND ', $conditions);
    }
    
    /**
     * Get tenant filter parameters for prepared statements
     */
    public function getTenantParams(string $table_alias = ''): array {
        if ($this->is_super_admin) {
            return [];
        }
        
        $params = [];
        
        if ($this->tenant_id) {
            $params[] = $this->tenant_id;
        }
        
        if ($this->branch_id && $this->shouldScopeByBranch($table_alias)) {
            $params[] = $this->branch_id;
        }
        
        return $params;
    }
    
    /**
     * Check if table should be scoped by branch
     */
    private function shouldScopeByBranch(string $table): bool {
        $branch_scoped_tables = [
            'sales', 'products', 'inventory', 'purchases', 
            'expenses', 'customers', 'suppliers'
        ];
        
        return in_array($table, $branch_scoped_tables);
    }
    
    /**
     * Apply tenant scoping to a query
     */
    public function applyTenantScoping(string $query, array $params = []): array {
        if ($this->is_super_admin) {
            return [$query, $params];
        }
        
        // Find tables in FROM clause
        preg_match_all('/FROM\s+(\w+)/i', $query, $matches);
        $tables = $matches[1] ?? [];
        
        // Find tables in JOIN clauses
        preg_match_all('/JOIN\s+(\w+)/i', $query, $matches);
        $tables = array_merge($tables, $matches[1] ?? []);
        
        // Apply scoping for each table
        foreach (array_unique($tables) as $table) {
            $where = $this->getTenantWhere($table);
            $table_params = $this->getTenantParams($table);
            
            // Insert WHERE clause if not exists
            if (stripos($query, 'WHERE') === false) {
                $query = preg_replace("/FROM\s+{$table}/i", "FROM {$table} WHERE {$where}", $query);
            } else {
                $query = preg_replace("/FROM\s+{$table}(\s+WHERE\s+)?/i", "FROM {$table} WHERE {$where} AND ", $query);
            }
            
            $params = array_merge($params, $table_params);
        }
        
        return [$query, $params];
    }
    
    /**
     * Check if user can access specific record
     */
    public function canAccessRecord(string $table, int $record_id): bool {
        if ($this->is_super_admin) {
            return true;
        }
        
        try {
            $pdo = $this->getDbConnection();
            $where = $this->getTenantWhere($table);
            $params = $this->getTenantParams($table);
            $params[] = $record_id;
            
            $stmt = $pdo->prepare("SELECT id FROM {$table} WHERE {$where} AND id = ? LIMIT 1");
            $stmt->execute($params);
            
            return $stmt->fetch() !== false;
        } catch (Exception $e) {
            error_log("Record access check failed: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get user-specific data scope
     */
    public function getUserDataScope(): array {
        return [
            'tenant_id' => $this->tenant_id,
            'branch_id' => $this->branch_id,
            'user_id' => $this->user_id,
            'role' => $this->user_role,
            'is_super_admin' => $this->is_super_admin,
            'permissions' => $this->permissions
        ];
    }
    
    /**
     * Check if user has specific permission
     */
    public function hasPermission(string $permission): bool {
        if ($this->is_super_admin) {
            return true;
        }
        
        return in_array($permission, $this->permissions);
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
}

/**
 * Security Exception for data access violations
 */
class SecurityException extends Exception {
    public function __construct(string $message = "", int $code = 0, Throwable $previous = null) {
        parent::__construct($message, $code, $previous);
        
        // Log security violations
        error_log("DATA ACCESS SECURITY VIOLATION: " . $message);
    }
}
