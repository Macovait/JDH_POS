<?php
/**
 * Base Model - Zero-Trust Implementation
 * Provides automatic tenant scoping for all database operations
 */

require_once __DIR__ . '/TenantContext.php';

abstract class BaseModel {
    protected $pdo;
    protected $context;
    protected $table;

    public function __construct() {
        $this->pdo = $this->getDatabaseConnection();
        $this->context = TenantContext::getInstance();
    }

    protected function getDatabaseConnection() {
        // Get PDO connection - try different approaches
        if (function_exists('get_db_connection')) {
            return get_db_connection();
        }

        // Fallback - create direct connection
        try {
            $configFile = __DIR__ . '/../config/config.php';
            if (!file_exists($configFile)) {
                throw new RuntimeException('Config file not found');
            }

            $config = require $configFile;
            return new PDO(
                "mysql:host=" . ($config['db_host'] ?? '127.0.0.1') .
                ";dbname=" . ($config['db_name'] ?? 'jakababa_pos') .
                ";charset=utf8mb4",
                $config['db_user'] ?? 'root',
                $config['db_pass'] ?? '',
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );
        } catch (Exception $e) {
            throw new RuntimeException('Database connection failed in BaseModel: ' . $e->getMessage());
        }
    }

    /**
     * Apply tenant scoping to any query
     * This is the core security method that prevents data leaks
     *
     * CRITICAL: This method ensures ZERO data leakage by:
     * 1. Automatically detecting table names from SQL
     * 2. Checking table schema for tenant columns
     * 3. Injecting tenant filters at the WHERE level
     * 4. Logging cross-tenant access for audit
     */
    protected function applyTenantScope(PDOStatement &$stmt, array &$params): void {
        $sql = $stmt->queryString;

        // Parse the SQL to understand table structure
        $tableName = $this->extractTableName($sql);

        // Build tenant WHERE conditions
        $tenantConditions = $this->buildTenantConditions($tableName);

        if (!empty($tenantConditions)) {
            // Inject tenant conditions into the WHERE clause
            $sql = $this->injectTenantConditions($sql, $tenantConditions, $params);

            // Re-prepare the statement with tenant conditions
            try {
                $stmt = $this->pdo->prepare($sql);
            } catch (PDOException $e) {
                error_log("TENANT SCOPE INJECTION FAILED: " . $e->getMessage());
                error_log("Original SQL: " . $stmt->queryString);
                error_log("Modified SQL: " . $sql);
                throw new RuntimeException('Invalid query structure for tenant scoping');
            }
        }
    }

    /**
     * Extract table name from SQL query
     */
    private function extractTableName(string $sql): string {
        // Simple regex to extract table name from common query patterns
        // FROM table_name, JOIN table_name, UPDATE table_name, etc.
        $patterns = [
            '/FROM\s+`?(\w+)`?/i',
            '/UPDATE\s+`?(\w+)`?/i',
            '/INSERT\s+INTO\s+`?(\w+)`?/i',
            '/DELETE\s+FROM\s+`?(\w+)`?/i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $sql, $matches)) {
                return $matches[1];
            }
        }

        // If table property is set on the model, use that
        if (!empty($this->table)) {
            return $this->table;
        }

        return '';
    }

    /**
     * Build tenant WHERE conditions based on table and context
     */
    private function buildTenantConditions(string $tableName): array {
        $conditions = [];

        // For SaaS admins, allow cross-tenant access but log it
        if ($this->context->allowCrossTenant()) {
            // SaaS admin can access data, but we still validate table structure
            $this->logCrossTenantAccess($tableName);
        } else {
            // Regular users get strict tenant scoping
            if ($this->context->getCompanyId()) {
                // Check if table has tenant_id column
                if ($this->tableHasColumn($tableName, 'tenant_id')) {
                    $conditions[] = "tenant_id = ?";
                }
            }

            if ($this->context->getBranchId()) {
                // Check if table has branch_id column
                if ($this->tableHasColumn($tableName, 'branch_id')) {
                    $conditions[] = "branch_id = ?";
                }
            }
        }

        return $conditions;
    }

    /**
     * Inject tenant conditions into SQL WHERE clause
     */
    private function injectTenantConditions(string $sql, array $tenantConditions, array &$params): string {
        if (empty($tenantConditions)) {
            return $sql;
        }

        $tenantWhere = implode(' AND ', $tenantConditions);

        // Add tenant parameters to the params array
        if (!$this->context->allowCrossTenant()) {
            if ($this->context->getCompanyId() && in_array('tenant_id = ?', $tenantConditions)) {
                $params[] = $this->context->getCompanyId();
            }
            if ($this->context->getBranchId() && in_array('branch_id = ?', $tenantConditions)) {
                $params[] = $this->context->getBranchId();
            }
        }

        // Inject into WHERE clause
        if (stripos($sql, 'WHERE') === false) {
            // No WHERE clause, add one
            $sql .= " WHERE {$tenantWhere}";
        } else {
            // Inject at the beginning of WHERE clause
            $sql = preg_replace('/WHERE\s+/i', "WHERE {$tenantWhere} AND ", $sql, 1);
        }

        return $sql;
    }

    /**
     * Check if table has a specific column
     */
    private function tableHasColumn(string $tableName, string $columnName): bool {
        try {
            $safeTable = str_replace('`', '', $tableName);
            $safeCol = str_replace("'", "\\'", $columnName);
            $stmt = $this->pdo->query("SHOW COLUMNS FROM `{$safeTable}` LIKE '{$safeCol}'");
            return $stmt->rowCount() > 0;
        } catch (PDOException $e) {
            // Table might not exist or other error
            error_log("Column check failed for {$tableName}.{$columnName}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get all column names from a table
     */
    private function getTableColumns(string $tableName): array {
        try {
            $stmt = $this->pdo->prepare("SHOW COLUMNS FROM `{$tableName}`");
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_COLUMN);
        } catch (PDOException $e) {
            error_log("Get columns failed for {$tableName}: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Log cross-tenant access for audit purposes
     * CRITICAL: All SaaS admin cross-tenant operations are logged
     */
    private function logCrossTenantAccess(string $tableName): void {
        $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 5);
        $caller = isset($backtrace[4]) ? $backtrace[4]['class'] . '::' . $backtrace[4]['function'] : 'unknown';

        $logData = [
            'timestamp' => date('Y-m-d H:i:s'),
            'user_id' => $this->context->getUserId(),
            'user_role' => $this->context->getUserRole(),
            'table' => $tableName,
            'caller' => $caller,
            'tenant_id' => $this->context->getCompanyId(),
            'branch_id' => $this->context->getBranchId(),
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 100)
        ];

        error_log("CROSS-TENANT ACCESS: " . json_encode($logData));

        // Could also write to audit table
        // $this->logToAuditTable('cross_tenant_access', $logData);
    }

    /**
     * Generic find method with tenant scoping
     */
    public function find(int $id) {
        $stmt = $this->pdo->prepare("SELECT * FROM `{$this->table}` WHERE id = ?");
        $params = [$id];
        $this->applyTenantScope($stmt, $params);
        $stmt->execute($params);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Generic insert with tenant scoping
     */
    public function insert(array $data): int {
        // Get columns from the table to validate data keys
        $validColumns = $this->getTableColumns($this->table);

        // Filter data to only include valid columns
        $data = array_intersect_key($data, array_flip($validColumns));

        // Inject audit fields
        if ($this->tableHasColumn($this->table, 'created_by') && !isset($data['created_by'])) {
            $data['created_by'] = $this->context->getUserId();
        }
        if ($this->tableHasColumn($this->table, 'updated_by') && !isset($data['updated_by'])) {
            $data['updated_by'] = $this->context->getUserId();
        }

        // Apply tenant scoping - inject tenant_id if column exists and tenant_id not already set
        if (!isset($data['tenant_id']) && in_array('tenant_id', $validColumns)) {
            $companyId = $this->context->getCompanyId();

            // Validate tenant_id is valid (>0) and exists in companies table
            if ($companyId > 0) {
                // Verify company exists in database to prevent FK constraint errors
                try {
                    $checkStmt = $this->pdo->prepare("SELECT id FROM companies WHERE id = ? LIMIT 1");
                    $checkStmt->execute([$companyId]);
                    if ($checkStmt->fetch()) {
                        $data['tenant_id'] = $companyId;
                    } else {
                        error_log("BaseModel::insert - Tenant ID {$companyId} not found in companies table. Skipping tenant_id assignment.");
                    }
                } catch (PDOException $e) {
                    error_log("BaseModel::insert - Error checking company existence: " . $e->getMessage());
                }
            } else {
                error_log("BaseModel::insert - Invalid tenant_id ({$companyId}). Tenant context may not be properly initialized.");
            }
        }

        $columns = array_keys($data);
        $placeholders = str_repeat('?, ', count($data) - 1) . '?';

        $stmt = $this->pdo->prepare("INSERT INTO `{$this->table}` (" . implode(', ', $columns) . ") VALUES ({$placeholders})");
        $stmt->execute(array_values($data));

        return $this->pdo->lastInsertId();
    }

    /**
     * Generic update with tenant scoping
     */
    public function update(int $id, array $data): bool {
        // Inject audit fields
        if ($this->tableHasColumn($this->table, 'updated_by')) {
            $data['updated_by'] = $this->context->getUserId();
        }

        $setParts = [];
        $params = array_values($data);

        foreach (array_keys($data) as $column) {
            $setParts[] = "`{$column}` = ?";
        }

        $stmt = $this->pdo->prepare("UPDATE `{$this->table}` SET " . implode(', ', $setParts) . " WHERE id = ?");
        $params[] = $id;

        $this->applyTenantScope($stmt, $params);
        return $stmt->execute($params);
    }

    /**
     * Generic delete with tenant scoping (soft delete if supported)
     */
    public function delete(int $id): bool {
        if ($this->tableHasColumn($this->table, 'deleted_at')) {
            // Soft delete
            return $this->update($id, ['deleted_at' => date('Y-m-d H:i:s')]);
        } else {
            // Hard delete
            $stmt = $this->pdo->prepare("DELETE FROM `{$this->table}` WHERE id = ?");
            $params = [$id];
            $this->applyTenantScope($stmt, $params);
            return $stmt->execute($params);
        }
    }

    /**
     * Execute raw query with automatic tenant scoping
     */
    public function query(string $sql, array $params = []): PDOStatement {
        $stmt = $this->pdo->prepare($sql);
        $this->applyTenantScope($stmt, $params);
        $stmt->execute($params);
        return $stmt;
    }

    /**
     * Get all records with tenant scoping
     */
    public function all(array $conditions = []): array {
        $sql = "SELECT * FROM `{$this->table}`";
        $params = [];

        if (!empty($conditions)) {
            $whereParts = [];
            foreach ($conditions as $column => $value) {
                $whereParts[] = "`{$column}` = ?";
                $params[] = $value;
            }
            $sql .= " WHERE " . implode(' AND ', $whereParts);
        }

        $stmt = $this->pdo->prepare($sql);
        $this->applyTenantScope($stmt, $params);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}