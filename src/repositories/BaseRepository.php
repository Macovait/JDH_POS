<?php
declare(strict_types=1);

/**
 * BaseRepository - Multi-Tenant Data Access Layer
 * 
 * Enforces strict data isolation at query level:
 * - Company isolation (tenant_id)
 * - Branch isolation (branch_id)
 * - Business type filtering (business_type_id)
 * - Role-based access control
 * 
 * @package Jakababa
 * @subpackage Repository
 */

abstract class BaseRepository
{
    protected PDO $pdo;
    protected ?int $companyId;
    protected ?int $branchId;
    protected ?int $userId;
    protected ?int $businessTypeId;
    protected string $userRole;
    protected bool $isSuperAdmin;
    protected bool $isCompanyAdmin;
    protected bool $isBranchManager;
    protected bool $isCashier;
    
    // Table name must be defined in child classes
    protected string $tableName = '';
    protected string $primaryKey = 'id';

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->initFromSession();
    }

    /**
     * Initialize user context from session
     */
    protected function initFromSession(): void
    {
        $this->companyId = isset($_SESSION['tenant_id']) ? (int) $_SESSION['tenant_id'] : null;
        $this->branchId = isset($_SESSION['branch_id']) ? (int) $_SESSION['branch_id'] : null;
        $this->userId = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;
        $this->businessTypeId = isset($_SESSION['business_type_id']) ? (int) $_SESSION['business_type_id'] : null;
        
        $this->userRole = $_SESSION['role'] ?? 'cashier';
        
        // Role-based access flags
        $this->isSuperAdmin = $this->userRole === 'superadmin' || $this->userRole === 'admin' && !empty($_SESSION['is_super_admin']);
        $this->isCompanyAdmin = in_array($this->userRole, ['owner', 'admin', 'superadmin']);
        $this->isBranchManager = $this->userRole === 'manager' || $this->userRole === 'branch_manager';
        $this->isCashier = $this->userRole === 'cashier';
    }

    /**
     * Check if user has access to company
     */
    protected function canAccessCompany(int $companyId): bool
    {
        if ($this->isSuperAdmin) {
            return true;
        }
        return $this->companyId === $companyId;
    }

    /**
     * Check if user has access to branch
     */
    protected function canAccessBranch(int $branchId): bool
    {
        if ($this->isSuperAdmin || $this->isCompanyAdmin) {
            return true;
        }
        return $this->branchId === $branchId;
    }

    /**
     * Add company filter to WHERE clause
     */
    protected function addCompanyFilter(string $sql, string $alias = ''): string
    {
        if ($this->isSuperAdmin) {
            return $sql;
        }
        
        $prefix = $alias ? "{$alias}." : "";
        $companyColumn = "{$prefix}tenant_id";
        
        // Check if WHERE already exists
        if (stripos($sql, 'WHERE') === false) {
            return "{$sql} WHERE {$companyColumn} = :tenant_id";
        }
        
        return "{$sql} AND {$companyColumn} = :tenant_id";
    }

    /**
     * Add branch filter to WHERE clause
     */
    protected function addBranchFilter(string $sql, string $alias = ''): string
    {
        // Branch managers and cashiers are restricted to their branch
        if ($this->isSuperAdmin || $this->isCompanyAdmin) {
            return $sql;
        }
        
        if ($this->branchId) {
            $prefix = $alias ? "{$alias}." : "";
            $branchColumn = "{$prefix}branch_id";
            
            if (stripos($sql, 'WHERE') === false) {
                return "{$sql} WHERE {$branchColumn} = :branch_id";
            }
            
            return "{$sql} AND {$branchColumn} = :branch_id";
        }
        
        return $sql;
    }

    /**
     * Add business type filter to WHERE clause
     */
    protected function addBusinessTypeFilter(string $sql, string $alias = ''): string
    {
        if (!$this->businessTypeId) {
            return $sql;
        }
        
        $prefix = $alias ? "{$alias}." : "";
        $btColumn = "{$prefix}business_type_id";
        
        if (stripos($sql, 'WHERE') === false) {
            return "{$sql} WHERE {$btColumn} = :business_type_id";
        }
        
        return "{$sql} AND {$btColumn} = :business_type_id";
    }

    /**
     * Add user company isolation to INSERT
     */
    protected function addCompanyToData(array $data): array
    {
        if (!$this->isSuperAdmin && $this->companyId && !isset($data['tenant_id'])) {
            $data['tenant_id'] = $this->companyId;
        }
        return $data;
    }

    /**
     * Add branch to data for INSERT/UPDATE
     */
    protected function addBranchToData(array $data): array
    {
        if (!$this->isSuperAdmin && !$this->isCompanyAdmin && $this->branchId && !isset($data['branch_id'])) {
            $data['branch_id'] = $this->branchId;
        }
        return $data;
    }

    /**
     * Execute query with automatic filter injection
     * CRITICAL: Must handle duplicate parameters BEFORE executing
     * This method guarantees isolation while fixing PDO parameter issues
     */
    protected function executeWithFilters(string $sql, array $params = []): PDOStatement
    {
        // Step 1: Parse the SQL to find all named placeholders that appear multiple times
        // Match :param_name patterns
        preg_match_all('/:([a-zA-Z_][a-zA-Z0-9_]*)/', $sql, $matches);
        $placeholderCounts = array_count_values($matches[1]);
        
        // Step 2: For any placeholder used more than once, rename duplicates in SQL
        $newParams = $params;
        foreach ($placeholderCounts as $name => $count) {
            if ($count > 1 && isset($params[':' . $name])) {
                // Replace duplicate placeholders with unique names
                $param = ':' . $name;
                $pos = strpos($sql, $param);
                $offset = 0;
                
                for ($i = 1; $i < $count; $i++) {
                    $newName = ':' . $name . '_dup' . $i;
                    // Find next occurrence after previous
                    $nextPos = strpos($sql, $param, $pos + strlen($param));
                    if ($nextPos !== false) {
                        $sql = substr_replace($sql, $newName, $nextPos, strlen($param));
                        $newParams[$newName] = $params[':' . $name];
                        $pos = $nextPos;
                    }
                }
            }
        }
        
        // Step 3: Add filter parameters AFTER handling duplicates
        // These filter names should be unique to avoid conflicts
        $filterParams = [];
        
        // Add company filter - use unique name
        if (!$this->isSuperAdmin && $this->companyId) {
            // Use a unique key to avoid conflicts with existing params
            if (!isset($newParams[':filter_tenant_id'])) {
                $filterParams[':filter_tenant_id'] = $this->companyId;
                // Update the WHERE clause to use the filter parameter name
                $sql = preg_replace('/:tenant_id\b/', ':filter_tenant_id', $sql, 1);
            }
        }
        
        // Add branch filter - use unique name  
        if (!$this->isSuperAdmin && !$this->isCompanyAdmin && $this->branchId) {
            if (!isset($newParams[':filter_branch_id'])) {
                $filterParams[':filter_branch_id'] = $this->branchId;
                $sql = preg_replace('/:branch_id\b/', ':filter_branch_id', $sql, 1);
            }
        }
        
        // Add business type filter
        if ($this->businessTypeId) {
            if (!isset($newParams[':filter_business_type_id'])) {
                $filterParams[':filter_business_type_id'] = $this->businessTypeId;
                $sql = preg_replace('/:business_type_id\b/', ':filter_business_type_id', $sql, 1);
            }
        }
        
        // Step 4: Merge all params
        $allParams = array_merge($newParams, $filterParams);
        
        // Debug: log the query for debugging issues
        // error_log("SQL: " . substr($sql, 0, 200));
        // error_log("Params: " . json_encode(array_keys($allParams)));
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($allParams);
        
        return $stmt;
    }

    /**
     * Find all records with optional filters
     */
    public function findAll(array $filters = [], int $limit = 100, int $offset = 0): array
    {
        $sql = "SELECT * FROM {$this->tableName}";
        $params = [];
        
        // Add WHERE clause for filters
        if (!empty($filters)) {
            $conditions = [];
            foreach ($filters as $key => $value) {
                $conditions[] = "{$key} = :{$key}";
                $params[":{$key}"] = $value;
            }
            $sql .= " WHERE " . implode(' AND ', $conditions);
        }
        
        $sql = $this->addCompanyFilter($sql);
        $sql = $this->addBranchFilter($sql);
        $sql = $this->addBusinessTypeFilter($sql);
        
        $sql .= " ORDER BY {$this->primaryKey} DESC LIMIT :limit OFFSET :offset";
        $params[':limit'] = $limit;
        $params[':offset'] = $offset;
        
        $stmt = $this->executeWithFilters($sql, $params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Find single record by ID
     */
    public function findById(int $id): ?array
    {
        $sql = "SELECT * FROM {$this->tableName} WHERE {$this->primaryKey} = :id";
        $sql = $this->addCompanyFilter($sql);
        $sql = $this->addBranchFilter($sql);
        
        $stmt = $this->executeWithFilters($sql, [':id' => $id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $result ?: null;
    }

    /**
     * Count records with filters
     */
    public function count(array $filters = []): int
    {
        $sql = "SELECT COUNT(*) as cnt FROM {$this->tableName}";
        $params = [];
        
        if (!empty($filters)) {
            $conditions = [];
            foreach ($filters as $key => $value) {
                $conditions[] = "{$key} = :{$key}";
                $params[":{$key}"] = $value;
            }
            $sql .= " WHERE " . implode(' AND ', $conditions);
        }
        
        $sql = $this->addCompanyFilter($sql);
        $sql = $this->addBranchFilter($sql);
        
        $stmt = $this->executeWithFilters($sql, $params);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return (int) ($result['cnt'] ?? 0);
    }

    /**
     * Insert new record
     */
    public function create(array $data): int
    {
        // Add company and branch to data
        $data = $this->addCompanyToData($data);
        $data = $this->addBranchToData($data);
        
        $columns = array_keys($data);
        $placeholders = array_map(fn($col) => ":{$col}", $columns);
        
        $sql = "INSERT INTO {$this->tableName} (" . implode(', ', $columns) . ") 
                VALUES (" . implode(', ', $placeholders) . ")";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($data);
        
        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Update record by ID
     */
    public function update(int $id, array $data): bool
    {
        // Ensure company isolation on update
        if (!$this->isSuperAdmin && !$this->isCompanyAdmin) {
            // Check ownership
            $existing = $this->findById($id);
            if (!$existing) {
                return false;
            }
        }
        
        $sets = [];
        foreach (array_keys($data) as $key) {
            $sets[] = "{$key} = :{$key}";
        }
        
        $sql = "UPDATE {$this->tableName} SET " . implode(', ', $sets) . " 
                WHERE {$this->primaryKey} = :id";
        
        // Add company filter to prevent cross-company updates
        $sql = $this->addCompanyFilter($sql);
        
        $data[':id'] = $id;
        
        // Add tenant_id to params
        if (!$this->isSuperAdmin && $this->companyId) {
            $data[':tenant_id'] = $this->companyId;
        }
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($data);
        
        return $stmt->rowCount() > 0;
    }

    /**
     * Delete record (soft delete)
     */
    public function delete(int $id): bool
    {
        $sql = "UPDATE {$this->tableName} SET deleted_at = NOW() 
                WHERE {$this->primaryKey} = :id";
        
        // Add company filter
        $sql = $this->addCompanyFilter($sql);
        $sql = $this->addBranchFilter($sql);
        
        $params = [':id' => $id];
        if (!$this->isSuperAdmin && $this->companyId) {
            $params[':tenant_id'] = $this->companyId;
        }
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->rowCount() > 0;
    }

    /**
     * Hard delete (use with caution)
     */
    public function hardDelete(int $id): bool
    {
        $sql = "DELETE FROM {$this->tableName} WHERE {$this->primaryKey} = :id";
        $sql = $this->addCompanyFilter($sql);
        
        $params = [':id' => $id];
        if (!$this->isSuperAdmin && $this->companyId) {
            $params[':tenant_id'] = $this->companyId;
        }
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->rowCount() > 0;
    }

    /**
     * Begin transaction
     */
    protected function beginTransaction(): void
    {
        $this->pdo->beginTransaction();
    }

    /**
     * Commit transaction
     */
    protected function commit(): void
    {
        $this->pdo->commit();
    }

    /**
     * Rollback transaction
     */
    protected function rollback(): void
    {
        $this->pdo->rollBack();
    }
}
