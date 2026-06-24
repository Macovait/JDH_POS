<?php
/**
 * Database Connection and Query Helpers
 * 
 * Provides PDO connection and helper functions for database operations.
 * 
 * @package JDH_POS
 * @version 2.1
 */

/**
 * Get a PDO database connection (singleton pattern).
 * 
 * @return PDO
 * @throws PDOException
 */
function get_db_connection() {
    static $pdo = null;
    
    if ($pdo !== null) {
        return $pdo;
    }
    
    $host = getenv('DB_HOST') ?: 'localhost';
    $dbname = getenv('DB_NAME') ?: 'jdh_pos';
    $username = getenv('DB_USER') ?: 'root';
    $password = getenv('DB_PASS') ?: '';
    $port = getenv('DB_PORT') ?: 3306;
    $charset = getenv('DB_CHARSET') ?: 'utf8mb4';
    
    try {
        $dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset={$charset}";
        
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_TIMEOUT => 30,
            PDO::ATTR_PERSISTENT => true,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES {$charset}, time_zone = '+03:00'"
        ];
        
        $pdo = new PDO($dsn, $username, $password, $options);
        
        // Set session timezone to East Africa Time (or whatever is configured)
        $timezone = getenv('DB_TIMEZONE') ?: '+03:00';
        $pdo->exec("SET time_zone = '{$timezone}'");
        
        return $pdo;
    } catch (PDOException $e) {
        error_log("Database connection failed: " . $e->getMessage());
        throw $e;
    }
}

/**
 * Safely close the database connection.
 * 
 * @return void
 */
function db_close_connection() {
    static $pdo = null;
    $pdo = null;
}

/**
 * Check if a column exists in a table.
 * 
 * @param string $table
 * @param string $column
 * @return bool
 */
function db_has_column($table, $column) {
    static $cache = [];
    $key = $table . '.' . $column;
    if (isset($cache[$key])) {
        return $cache[$key];
    }
    try {
        $pdo = get_db_connection();
        $table = sanitize_table_name($table);
        $column_quoted = $pdo->quote($column);
        $stmt = $pdo->query("SHOW COLUMNS FROM `{$table}` LIKE " . $column_quoted);
        $result = $stmt->fetch() !== false;
        $cache[$key] = $result;
        return $result;
    } catch (Exception $e) {
        error_log("db_has_column error: " . $e->getMessage());
        return false;
    }
}

/**
 * Sanitize table name to prevent SQL injection.
 * 
 * @param string $table Table name
 * @return string Sanitized table name
 */
function sanitize_table_name($table) {
    // Allow only alphanumeric, underscore, and dash
    return preg_replace('/[^a-zA-Z0-9_-]/', '', $table);
}

/**
 * Get all column information for a table.
 * 
 * @param string $table Table name
 * @param PDO|null $pdo Optional PDO connection
 * @return array Array of column information (Field, Type, Null, Key, Default, Extra)
 */
function db_get_columns($table, $pdo = null) {
    try {
        if ($pdo === null) {
            $pdo = get_db_connection();
        }
        
        $table = sanitize_table_name($table);
        
        // Try information_schema first (more reliable)
        $stmt = $pdo->prepare("
            SELECT 
                COLUMN_NAME as Field,
                COLUMN_TYPE as Type,
                IS_NULLABLE as `Null`,
                COLUMN_KEY as `Key`,
                COLUMN_DEFAULT as `Default`,
                EXTRA as Extra,
                DATA_TYPE as DataType
            FROM information_schema.COLUMNS 
            WHERE TABLE_SCHEMA = DATABASE() 
                AND TABLE_NAME = ?
            ORDER BY ORDINAL_POSITION
        ");
        $stmt->execute([$table]);
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // If information_schema fails or returns empty, try DESCRIBE as fallback
        if (empty($columns)) {
            $stmt = $pdo->prepare("DESCRIBE `{$table}`");
            $stmt->execute();
            $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
        
        return $columns;
    } catch (Exception $e) {
        error_log("db_get_columns error for table {$table}: " . $e->getMessage());
        return [];
    }
}

/**
 * Fetch all rows from a query.
 * 
 * @param string $query SQL query with placeholders
 * @param array $params Parameters for placeholders
 * @return array Array of rows (associative), empty array on error
 */
function db_fetch_all($query, $params = []) {
    try {
        [$query, $params] = apply_auto_scoping($query, $params);
        $pdo = get_db_connection();
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        return $stmt->fetchAll();
    } catch (Exception $e) {
        error_log("db_fetch_all error: " . $e->getMessage() . " | Query: " . substr($query, 0, 200));
        return [];
    }
}

/**
 * Fetch a single row from a query.
 *
 * @param string $query SQL query with placeholders
 * @param array $params Parameters for placeholders
 * @return array|false Associative array on success, false on error
 */
function db_fetch_one($query, $params = []) {
    try {
        [$query, $params] = apply_auto_scoping($query, $params);
        $pdo = get_db_connection();
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        return $stmt->fetch();
    } catch (Exception $e) {
        error_log("db_fetch_one error: " . $e->getMessage() . " | Query: " . substr($query, 0, 200));
        return false;
    }
}

/**
 * Fetch a single value from a query (first column of first row).
 *
 * @param string $query SQL query with placeholders
 * @param array $params Parameters for placeholders
 * @return mixed|false Value on success, false on error
 */
function db_fetch_value($query, $params = []) {
    try {
        [$query, $params] = apply_auto_scoping($query, $params);
        $pdo = get_db_connection();
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $result = $stmt->fetchColumn();
        return $result !== false ? $result : false;
    } catch (Exception $e) {
        error_log("db_fetch_value error: " . $e->getMessage() . " | Query: " . substr($query, 0, 200));
        return false;
    }
}

/**
 * Execute a query (INSERT, UPDATE, DELETE, etc.).
 *
 * @param string $query SQL query with placeholders
 * @param array $params Parameters for placeholders
 * @return int|false Number of affected rows on success, false on error
 */
function db_query($query, $params = []) {
    try {
        [$query, $params] = apply_auto_scoping($query, $params);
        $pdo = get_db_connection();
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        return $stmt->rowCount();
    } catch (Exception $e) {
        error_log("db_query error: " . $e->getMessage() . " | Query: " . substr($query, 0, 200));
        return false;
    }
}

/**
 * Apply automatic tenant and branch scoping to queries.
 * Uses DataAccessScope if available, otherwise falls back to session context.
 *
 * @param string $query SQL query
 * @param array $params Parameters for placeholders
 * @return array [string $query, array $params]
 */
function apply_auto_scoping(string $query, array $params = []): array {
    // Skip non-SELECT queries for safety (INSERT/UPDATE/DELETE should be explicit)
    $isSelect = preg_match('/^\s*SELECT\s/i', $query);
    if (!$isSelect) {
        return [$query, $params];
    }

    // Skip if already has explicit tenant_id or branch_id WHERE filters
    // Only check for actual comparison operators to avoid false positives in SELECT lists
    if (preg_match('/tenant_id\s*(=|IS|IN|<>|!=)/i', $query) || preg_match('/branch_id\s*(=|IS|IN|<>|!=)/i', $query)) {
        return [$query, $params];
    }

    // Skip system/auth tables that are not tenant-scoped
    $system_tables = ['permissions', 'roles', 'role_permissions', 'user_roles', 'users', 'tenants',
                      'settings', 'migrations', 'failed_jobs', 'plans', 'subscriptions', 'branches'];
    if (preg_match('/FROM\s+(\w+)/i', $query, $tm) && in_array(strtolower($tm[1]), $system_tables)) {
        return [$query, $params];
    }

    // Try to use DataAccessScope if available
    if (class_exists('DataAccessScope')) {
        try {
            $scope = DataAccessScope::getInstance();
            return $scope->applyTenantScoping($query, $params);
        } catch (Exception $e) {
            // Fall through to manual scoping
        }
    }

    // Manual fallback: inject tenant_id and branch_id from session
    $tenant_id = $_SESSION['tenant_id'] ?? null;
    $is_super_admin = $_SESSION['is_super_admin'] ?? false;
    $user_role = $_SESSION['role'] ?? null;

    // Use dashboard-selected branch if available (only for admins/managers who can switch)
    // Otherwise fall back to the user's assigned branch
    $branch_id = null;
    $can_switch_branch = ($is_super_admin || in_array($user_role ?? '', ['admin', 'manager', 'company_admin']));
    if ($can_switch_branch && isset($_SESSION['dashboard_branch_id']) && $_SESSION['dashboard_branch_id'] !== '') {
        $selected = (int) $_SESSION['dashboard_branch_id'];
        if ($selected > 0) {
            $branch_id = $selected;
        }
    }
    if (!$branch_id) {
        $branch_id = $_SESSION['branch_id'] ?? null;
    }

    if (!$tenant_id) {
        return [$query, $params];
    }

    // Super admins bypass tenant filter but still respect explicit branch selection
    if ($is_super_admin && !$branch_id) {
        return [$query, $params];
    }

    // Extract primary table from FROM clause only (ignore JOINs)
    if (!preg_match('/FROM\s+(\w+)(?:\s+AS\s+(\w+)|\s+(\w+))?(?:\s+WHERE|\s+JOIN|\s+GROUP|\s+ORDER|\s+LIMIT|\s*$)/i', $query, $fromMatch)) {
        return [$query, $params];
    }

    $table = $fromMatch[1];
    $alias = !empty($fromMatch[2]) ? $fromMatch[2] : (!empty($fromMatch[3]) ? $fromMatch[3] : '');
    $aliasPrefix = $alias ? $alias . '.' : '';

    // Tables that should be scoped by branch
    $branch_scoped_tables = ['sales', 'products', 'inventory', 'purchases', 'expenses', 'customers', 'suppliers', 'returns', 'quotations'];

    $conditions = [];
    $newParams = [];

    // Add tenant filter (skip for super admins who can see all tenants)
    if (!$is_super_admin) {
        $conditions[] = "{$aliasPrefix}tenant_id = ?";
        $newParams[] = $tenant_id;
    }

    // Add branch filter if applicable
    if ($branch_id && in_array(strtolower($table), $branch_scoped_tables)) {
        $conditions[] = "{$aliasPrefix}branch_id = ?";
        $newParams[] = $branch_id;
    }

    if (empty($conditions)) {
        return [$query, $params];
    }

    // Inject WHERE clause
    $whereClause = implode(' AND ', $conditions);
    if (stripos($query, 'WHERE') === false) {
        // No WHERE clause - find position after FROM + table + optional alias and inject WHERE
        $fromPos = stripos($query, 'FROM');
        if ($fromPos !== false) {
            // Find end of FROM clause (table + optional alias)
            $afterFrom = substr($query, $fromPos + 4); // skip "FROM"
            $pattern = '/^\s+' . preg_quote($table, '/') . '(?:\s+AS\s+' . preg_quote($alias, '/') . '|\s+' . preg_quote($alias, '/') . ')?/i';
            if (preg_match($pattern, $afterFrom, $m)) {
                // Calculate insert position manually
                $matchLen = strlen($m[0]);
                $insertPos = $fromPos + 4 + $matchLen;
                $query = substr($query, 0, $insertPos) . ' WHERE ' . $whereClause . substr($query, $insertPos);
            }
        }
    } else {
        // Has WHERE - prepend conditions
        $query = preg_replace('/\bWHERE\b/i', 'WHERE ' . $whereClause . ' AND ', $query, 1);
    }

    return [$query, array_merge($newParams, $params)];
}

/**
 * Insert a row into a table.
 * 
 * @param string $table Table name
 * @param array $data Associative array of column => value
 * @return int|false Last insert ID on success, false on error
 */
function db_insert($table, $data) {
    if (empty($data)) {
        error_log("db_insert: No data provided for table {$table}");
        return false;
    }
    
    $table = sanitize_table_name($table);
    $columns = implode(', ', array_map(function($col) {
        return '`' . str_replace('`', '``', $col) . '`';
    }, array_keys($data)));
    
    $placeholders = implode(', ', array_fill(0, count($data), '?'));
    $values = array_values($data);
    
    try {
        $pdo = get_db_connection();
        $stmt = $pdo->prepare("INSERT INTO `{$table}` ({$columns}) VALUES ({$placeholders})");
        $stmt->execute($values);
        return (int) $pdo->lastInsertId();
    } catch (Exception $e) {
        error_log("db_insert error: " . $e->getMessage() . " | Table: {$table}");
        return false;
    }
}

/**
 * Insert multiple rows into a table (bulk insert).
 * 
 * @param string $table Table name
 * @param array $data Array of associative arrays (each row)
 * @return int|false Number of inserted rows on success, false on error
 */
function db_insert_bulk($table, $data) {
    if (empty($data)) {
        return false;
    }
    
    $table = sanitize_table_name($table);
    $columns = array_keys($data[0]);
    $columnList = implode(', ', array_map(function($col) {
        return '`' . str_replace('`', '``', $col) . '`';
    }, $columns));
    
    $placeholders = [];
    $values = [];
    
    foreach ($data as $row) {
        $placeholders[] = '(' . implode(', ', array_fill(0, count($row), '?')) . ')';
        foreach (array_values($row) as $value) {
            $values[] = $value;
        }
    }
    
    $placeholderString = implode(', ', $placeholders);
    
    try {
        $pdo = get_db_connection();
        $stmt = $pdo->prepare("INSERT INTO `{$table}` ({$columnList}) VALUES {$placeholderString}");
        $stmt->execute($values);
        return $stmt->rowCount();
    } catch (Exception $e) {
        error_log("db_insert_bulk error: " . $e->getMessage() . " | Table: {$table}");
        return false;
    }
}

/**
 * Update rows in a table.
 * 
 * @param string $table Table name
 * @param array $data Associative array of column => value
 * @param string $where WHERE clause (without 'WHERE')
 * @param array $whereParams Parameters for WHERE clause
 * @return int|false Number of affected rows on success, false on error
 */
function db_update($table, $data, $where, $whereParams = []) {
    if (empty($data)) {
        return false;
    }
    
    $table = sanitize_table_name($table);
    $setParts = [];
    $params = [];
    
    foreach ($data as $column => $value) {
        $setParts[] = "`" . str_replace('`', '``', $column) . "` = ?";
        $params[] = $value;
    }
    
    // Add WHERE parameters
    foreach ($whereParams as $param) {
        $params[] = $param;
    }
    
    $setClause = implode(', ', $setParts);
    
    try {
        $pdo = get_db_connection();
        $stmt = $pdo->prepare("UPDATE `{$table}` SET {$setClause} WHERE {$where}");
        $stmt->execute($params);
        return $stmt->rowCount();
    } catch (Exception $e) {
        error_log("db_update error: " . $e->getMessage() . " | Table: {$table} | WHERE: {$where}");
        return false;
    }
}

/**
 * Delete rows from a table.
 * 
 * @param string $table Table name
 * @param string $where WHERE clause (without 'WHERE')
 * @param array $params Parameters for WHERE clause
 * @return int|false Number of affected rows on success, false on error
 */
function db_delete($table, $where, $params = []) {
    $table = sanitize_table_name($table);
    
    try {
        $pdo = get_db_connection();
        $stmt = $pdo->prepare("DELETE FROM `{$table}` WHERE {$where}");
        $stmt->execute($params);
        return $stmt->rowCount();
    } catch (Exception $e) {
        error_log("db_delete error: " . $e->getMessage() . " | Table: {$table}");
        return false;
    }
}

/**
 * Get the scope column for multi-tenancy for a given table.
 * Returns the column name that indicates tenant scope (usually 'tenant_id'),
 * or null if the table does not have a tenant scope column.
 * 
 * @param string $table Table name
 * @return string|null Scope column name or null
 */
function db_get_scope_column($table) {
    try {
        $table = sanitize_table_name($table);
        
        // Check for tenant_id column (the standard scope in this SaaS version)
        if (db_has_column($table, 'tenant_id')) {
            return 'tenant_id';
        }
        
        // Optionally check for other scope columns
        if (db_has_column($table, 'company_id')) {
            return 'company_id';
        }
        
        return null;
    } catch (Exception $e) {
        error_log("db_get_scope_column error: " . $e->getMessage());
        return null;
    }
}

/**
 * Check if a table exists in the database.
 * 
 * @param string $table_name Table name
 * @return bool
 */
function db_table_exists($table_name) {
    static $cache = [];
    if (isset($cache[$table_name])) {
        return $cache[$table_name];
    }
    try {
        $pdo = get_db_connection();
        $table_name = sanitize_table_name($table_name);
        $stmt = $pdo->query("SHOW TABLES LIKE '" . $pdo->quote($table_name) . "'");
        $result = $stmt->fetchColumn() !== false;
        $cache[$table_name] = $result;
        return $result;
    } catch (Exception $e) {
        error_log("db_table_exists error: " . $e->getMessage());
        return false;
    }
}

/**
 * Begin a database transaction.
 * 
 * @return bool True on success, false on error
 */
function db_begin_transaction() {
    try {
        $pdo = get_db_connection();
        return $pdo->beginTransaction();
    } catch (Exception $e) {
        error_log("db_begin_transaction error: " . $e->getMessage());
        return false;
    }
}

/**
 * Commit a database transaction.
 * 
 * @return bool True on success, false on error
 */
function db_commit() {
    try {
        $pdo = get_db_connection();
        return $pdo->commit();
    } catch (Exception $e) {
        error_log("db_commit error: " . $e->getMessage());
        return false;
    }
}

/**
 * Rollback a database transaction.
 * 
 * @return bool True on success, false on error
 */
function db_rollback() {
    try {
        $pdo = get_db_connection();
        return $pdo->rollBack();
    } catch (Exception $e) {
        error_log("db_rollback error: " . $e->getMessage());
        return false;
    }
}

/**
 * Escape a string for safe use in SQL (use prepared statements instead when possible).
 * This function is deprecated - use prepared statements instead.
 * 
 * @param string $string String to escape
 * @return string Escaped string
 * @deprecated Use prepared statements instead
 */
function db_escape($string) {
    $pdo = get_db_connection();
    return substr($pdo->quote($string), 1, -1);
}

/**
 * Get the last insert ID from the last INSERT query.
 * 
 * @param string|null $name Name of the sequence object (optional)
 * @return string|false Last insert ID
 */
function db_last_insert_id($name = null) {
    try {
        $pdo = get_db_connection();
        return $pdo->lastInsertId($name);
    } catch (Exception $e) {
        error_log("db_last_insert_id error: " . $e->getMessage());
        return false;
    }
}

/**
 * Check if a table has a specific index.
 * 
 * @param string $table Table name
 * @param string $index_name Index name
 * @return bool
 */
function db_has_index($table, $index_name) {
    try {
        $pdo = get_db_connection();
        $table = sanitize_table_name($table);
        $stmt = $pdo->prepare("SHOW INDEX FROM `{$table}` WHERE Key_name = ?");
        $stmt->execute([$index_name]);
        return $stmt->fetch() !== false;
    } catch (Exception $e) {
        error_log("db_has_index error: " . $e->getMessage());
        return false;
    }
}

/**
 * Get the total number of rows in a table (with optional WHERE clause).
 * 
 * @param string $table Table name
 * @param string $where WHERE clause (optional)
 * @param array $params Parameters for WHERE clause
 * @return int Number of rows
 */
function db_count_rows($table, $where = '', $params = []) {
    $table = sanitize_table_name($table);
    $query = "SELECT COUNT(*) FROM `{$table}`";
    
    if (!empty($where)) {
        $query .= " WHERE {$where}";
    }
    
    return (int) db_fetch_value($query, $params);
}

/**
 * Execute a prepared statement with automatic tenant filtering.
 * 
 * @param string $query SQL query with placeholders
 * @param array $params Parameters for placeholders
 * @param int|null $tenant_id Tenant ID (uses current if null)
 * @return array|false Query results
 */
function db_query_tenant($query, $params = [], $tenant_id = null) {
    if ($tenant_id === null && function_exists('get_current_tenant_id')) {
        $tenant_id = get_current_tenant_id();
    }
    
    if ($tenant_id) {
        // Add tenant_id to params if it's not already there and the query needs it
        if (strpos($query, 'tenant_id = ?') !== false || strpos($query, 'tenant_id=?') !== false) {
            $params[] = $tenant_id;
        }
    }
    
    return db_fetch_all($query, $params);
}

/**
 * Get a paginated result set.
 * 
 * @param string $base_query Base SQL query (without LIMIT)
 * @param int $page Page number (1-indexed)
 * @param int $per_page Items per page
 * @param array $params Query parameters
 * @return array Array with 'data', 'total', 'last_page', 'current_page'
 */
function db_paginate($base_query, $page = 1, $per_page = 20, $params = []) {
    $page = max(1, (int) $page);
    $per_page = max(1, (int) $per_page);
    $offset = ($page - 1) * $per_page;
    
    // Get total count
    $count_query = preg_replace('/SELECT.*?FROM/i', 'SELECT COUNT(*) as total FROM', $base_query, 1);
    $count_query = preg_replace('/\s+ORDER\s+BY\s+.*$/i', '', $count_query);
    $total = (int) db_fetch_value($count_query, $params);
    
    // Get paginated data
    $data_query = $base_query . " LIMIT {$offset}, {$per_page}";
    $data = db_fetch_all($data_query, $params);
    
    return [
        'data' => $data,
        'total' => $total,
        'per_page' => $per_page,
        'current_page' => $page,
        'last_page' => ceil($total / $per_page),
        'from' => $offset + 1,
        'to' => min($offset + $per_page, $total)
    ];
}

/**
 * Build a WHERE clause from an array of conditions.
 * 
 * @param array $conditions Associative array of column => value
 * @param string $operator Operator (AND, OR)
 * @return array [where_clause, params]
 */
function db_build_where($conditions, $operator = 'AND') {
    if (empty($conditions)) {
        return ['1=1', []];
    }
    
    $clauses = [];
    $params = [];
    
    foreach ($conditions as $column => $value) {
        if ($value === null) {
            $clauses[] = "`{$column}` IS NULL";
        } elseif (is_array($value)) {
            $placeholders = implode(', ', array_fill(0, count($value), '?'));
            $clauses[] = "`{$column}` IN ({$placeholders})";
            $params = array_merge($params, $value);
        } else {
            $clauses[] = "`{$column}` = ?";
            $params[] = $value;
        }
    }
    
    return [implode(" {$operator} ", $clauses), $params];
}