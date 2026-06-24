<?php
/**
 * Chart of Accounts Service
 * Enterprise-grade financial accounting following JDH_POS patterns
 * 
 * This service follows the same architectural patterns as the barcode label module:
 * - Service-oriented architecture with single responsibility
 * - Dependency injection
 * - Settings management integration
 * - Rate limiting for bulk operations
 * - Comprehensive error handling
 * - Security-first approach
 * - Transaction safety
 */

namespace JDH\POS\Accounting;

use JDH\POS\Src\SettingsManager;
use JDH\POS\Src\Barcode\RateLimiter;
use JDH\POS\Src\Barcode\BarcodeErrorHandler;
use PDO;
use Exception;

class ChartOfAccountsService
{
    private PDO $pdo;
    private SettingsManager $settingsManager;
    private RateLimiter $rateLimiter;
    private BarcodeErrorHandler $errorHandler;
    private int $tenantId;
    private int $userId;

    public function __construct(
        PDO $pdo,
        SettingsManager $settingsManager,
        RateLimiter $rateLimiter,
        BarcodeErrorHandler $errorHandler,
        int $tenantId,
        int $userId = 0
    ) {
        $this->pdo = $pdo;
        $this->settingsManager = $settingsManager;
        $this->rateLimiter = $rateLimiter;
        $this->errorHandler = $errorHandler;
        $this->tenantId = $tenantId;
        $this->userId = $userId;
        
        // Ensure chart of accounts table exists for this tenant
        $this->ensureChartOfAccountsTable();
    }

    /**
     * Get chart of accounts hierarchy
     * 
     * @return array Multi-dimensional array representing account hierarchy
     */
    public function getChartOfAccounts(): array
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT id, account_code, account_name, account_type, 
                       parent_id, is_active, description, 
                       normal_balance, tax_related, report_section
                FROM chart_of_accounts 
                WHERE tenant_id = ? 
                ORDER BY account_code
            ");
            $stmt->execute([$this->tenantId]);
            
            $flatAccounts = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Build hierarchy
            return $this->buildAccountHierarchy($flatAccounts);
        } catch (Exception $e) {
            $this->errorHandler->logError("Failed to get chart of accounts: " . $e->getMessage());
            throw new Exception('Unable to retrieve chart of accounts');
        }
    }

    /**
     * Create a new account
     * 
     * @param array $accountData Account data (code, name, type, etc.)
     * @return int New account ID
     */
    public function createAccount(array $accountData): int
    {
        // Validate input
        $validation = $this->validateAccountData($accountData);
        if (!$validation['valid']) {
            throw new Exception('Invalid account data: ' . $validation['error']);
        }
        
        // Check rate limiting for bulk operations (if creating multiple accounts)
        $rlCheck = $this->rateLimiter->checkChartOfAccountsOperation('create');
        if (!$rlCheck['allowed']) {
            throw new Exception('Rate limit exceeded for account creation');
        }
        
        try {
            // Start transaction
            $this->pdo->beginTransaction();
            
            // Check for duplicate account code
            $checkStmt = $this->pdo->prepare("
                SELECT id FROM chart_of_accounts 
                WHERE tenant_id = ? AND account_code = ?
            ");
            $checkStmt->execute([$this->tenantId, $accountData['account_code']]);
            
            if ($checkStmt->fetch()) {
                throw new Exception('Account code already exists');
            }
            
            // Insert account
            $insertStmt = $this->pdo->prepare("
                INSERT INTO chart_of_accounts 
                (tenant_id, account_code, account_name, account_type, parent_id, 
                 description, normal_balance, tax_related, report_section, is_active, created_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $insertStmt->execute([
                $this->tenantId,
                $accountData['account_code'],
                $accountData['account_name'],
                $accountData['account_type'],
                $accountData['parent_id'] ?? null,
                $accountData['description'] ?? null,
                $accountData['normal_balance'] ?? 'debit',
                $accountData['tax_related'] ?? false,
                $accountData['report_section'] ?? null,
                $accountData['is_active'] ?? true,
                $this->userId
            ]);
            
            $accountId = (int)$this->pdo->lastInsertId();
            
            // Commit transaction
            $this->pdo->commit();
            
            // Log activity
            $this->logActivity('account_created', [
                'account_id' => $accountId,
                'account_code' => $accountData['account_code'],
                'account_name' => $accountData['account_name']
            ]);
            
            return $accountId;
        } catch (Exception $e) {
            // Rollback transaction on error
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            
            $this->errorHandler->logError("Failed to create account: " . $e->getMessage());
            throw new Exception('Unable to create account');
        }
    }

    /**
     * Update an existing account
     * 
     * @param int $accountId Account ID
     * @param array $accountData Updated account data
     * @return bool Success status
     */
    public function updateAccount(int $accountId, array $accountData): bool
    {
        // Validate input
        $validation = $this->validateAccountData($accountData, true); // Allow partial updates
        if (!$validation['valid']) {
            throw new Exception('Invalid account data: ' . $validation['error']);
        }
        
        // Check rate limiting
        $rlCheck = $this->rateLimiter->checkChartOfAccountsOperation('update');
        if (!$rlCheck['allowed']) {
            throw new Exception('Rate limit exceeded for account updates');
        }
        
        try {
            // Start transaction
            $this->pdo->beginTransaction();
            
            // Verify account exists and belongs to tenant
            $checkStmt = $this->pdo->prepare("
                SELECT id FROM chart_of_accounts 
                WHERE id = ? AND tenant_id = ?
            ");
            $checkStmt->execute([$accountId, $this->tenantId]);
            
            if (!$checkStmt->fetch()) {
                throw new Exception('Account not found or access denied');
            }
            
            // Check for duplicate account code (excluding current account)
            if (!empty($accountData['account_code'])) {
                $duplicateCheck = $this->pdo->prepare("
                    SELECT id FROM chart_of_accounts 
                    WHERE tenant_id = ? AND account_code = ? AND id != ?
                ");
                $duplicateCheck->execute([
                    $this->tenantId, 
                    $accountData['account_code'], 
                    $accountId
                ]);
                
                if ($duplicateCheck->fetch()) {
                    throw new Exception('Account code already exists');
                }
            }
            
            // Build update query dynamically
            $updateFields = [];
            $params = [];
            
            $fields = ['account_code', 'account_name', 'account_type', 'parent_id', 
                      'description', 'normal_balance', 'tax_related', 'report_section', 'is_active'];
            
            foreach ($fields as $field) {
                if (array_key_exists($field, $accountData)) {
                    $updateFields[] = "$field = ?";
                    $params[] = $accountData[$field];
                }
            }
            
            if (empty($updateFields)) {
                throw new Exception('No valid fields to update');
            }
            
            $params[] = $accountId;
            $params[] = $this->tenantId;
            
            $updateStmt = $this->pdo->prepare("
                UPDATE chart_of_accounts 
                SET " . implode(', ', $updateFields) . ", updated_by = ?, updated_at = NOW()
                WHERE id = ? AND tenant_id = ?
            ");
            
            $updateStmt->execute(array_merge($params, [$this->userId]));
            
            // Commit transaction
            $this->pdo->commit();
            
            // Log activity
            $this->logActivity('account_updated', [
                'account_id' => $accountId,
                'updated_fields' => array_keys($accountData)
            ]);
            
            return true;
        } catch (Exception $e) {
            // Rollback transaction on error
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            
            $this->errorHandler->logError("Failed to update account: " . $e->getMessage());
            throw new Exception('Unable to update account');
        }
    }

    /**
     * Delete an account (soft delete)
     * 
     * @param int $accountId Account ID
     * @return bool Success status
     */
    public function deleteAccount(int $accountId): bool
    {
        // Check if account has transactions (prevent deletion if used)
        $usageCheck = $this->checkAccountUsage($accountId);
        if ($usageCheck['has_transactions']) {
            throw new Exception('Cannot delete account with existing transactions');
        }
        
        // Check rate limiting
        $rlCheck = $this->rateLimiter->checkChartOfAccountsOperation('delete');
        if (!$rlCheck['allowed']) {
            throw new Exception('Rate limit exceeded for account deletion');
        }
        
        try {
            // Start transaction
            $this->pdo->beginTransaction();
            
            // Verify account exists and belongs to tenant
            $checkStmt = $this->pdo->prepare("
                SELECT id FROM chart_of_accounts 
                WHERE id = ? AND tenant_id = ?
            ");
            $checkStmt->execute([$accountId, $this->tenantId]);
            
            if (!$checkStmt->fetch()) {
                throw new Exception('Account not found or access denied');
            }
            
            // Soft delete (set is_active = 0)
            $deleteStmt = $this->pdo->prepare("
                UPDATE chart_of_accounts 
                SET is_active = 0, updated_by = ?, updated_at = NOW()
                WHERE id = ? AND tenant_id = ?
            ");
            
            $deleteStmt->execute([$this->userId, $accountId, $this->tenantId]);
            
            // Commit transaction
            $this->pdo->commit();
            
            // Log activity
            $this->logActivity('account_deleted', [
                'account_id' => $accountId
            ]);
            
            return true;
        } catch (Exception $e) {
            // Rollback transaction on error
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            
            $this->errorHandler->logError("Failed to delete account: " . $e->getMessage());
            throw new Exception('Unable to delete account');
        }
    }

    /**
     * Get account balance (for reporting)
     * 
     * @param int $accountId Account ID
     * @param string $dateFrom Optional start date (YYYY-MM-DD)
     * @param string $dateTo Optional end date (YYYY-MM-DD)
     * @return float Account balance
     */
    public function getAccountBalance(int $accountId, string $dateFrom = null, string $dateTo = null): float
    {
        try {
            // Verify account exists and belongs to tenant
            $checkStmt = $this->pdo->prepare("
                SELECT id FROM chart_of_accounts 
                WHERE id = ? AND tenant_id = ?
            ");
            $checkStmt->execute([$accountId, $this->tenantId]);
            
            if (!$checkStmt->fetch()) {
                throw new Exception('Account not found or access denied');
            }
            
            // Build query for balance calculation
            $query = "
                SELECT COALESCE(SUM(
                    CASE 
                        WHEN normal_balance = 'debit' THEN debit_amount - credit_amount
                        ELSE credit_amount - debit_amount
                    END
                ), 0) as balance
                FROM journal_entries je
                JOIN journal_entry_lines jel ON je.id = jel.journal_entry_id
                WHERE jel.account_id = ? 
                AND je.tenant_id = ?
            ";
            
            $params = [$accountId, $this->tenantId];
            
            if ($dateFrom) {
                $query .= " AND je.entry_date >= ?";
                $params[] = $dateFrom;
            }
            
            if ($dateTo) {
                $query .= " AND je.entry_date <= ?";
                $params[] = $dateTo;
            }
            
            $stmt = $this->pdo->prepare($query);
            $stmt->execute($params);
            
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return (float)($result['balance'] ?? 0);
        } catch (Exception $e) {
            $this->errorHandler->logError("Failed to get account balance: " . $e->getMessage());
            return 0.0;
        }
    }

    /**
     * Get trial balance
     * 
     * @param string $dateAsOf Optional date (YYYY-MM-DD)
     * @return array Trial balance data
     */
    public function getTrialBalance(string $dateAsOf = null): array
    {
        try {
            $accounts = $this->getChartOfAccounts();
            $trialBalance = [];
            
            foreach ($accounts as $account) {
                $balance = $this->getAccountBalance($account['id'], null, $dateAsOf);
                
                // Only include accounts with non-zero balance or that are active
                if ($balance != 0.0 || $account['is_active']) {
                    $trialBalance[] = [
                        'account_id' => $account['id'],
                        'account_code' => $account['account_code'],
                        'account_name' => $account['account_name'],
                        'account_type' => $account['account_type'],
                        'debit' => $account['normal_balance'] === 'debit' && $balance > 0 ? abs($balance) : 0,
                        'credit' => $account['normal_balance'] === 'credit' && $balance > 0 ? abs($balance) : 0,
                        'balance' => $balance
                    ];
                }
            }
            
            return $trialBalance;
        } catch (Exception $e) {
            $this->errorHandler->logError("Failed to get trial balance: " . $e->getMessage());
            throw new Exception('Unable to generate trial balance');
        }
    }

    // ============================================================================
    // PRIVATE HELPER METHODS
    // ============================================================================

    /**
     * Ensure chart of accounts table exists (create if needed)
     */
    private function ensureChartOfAccountsTable(): void
    {
        try {
            $this->pdo->exec("
                CREATE TABLE IF NOT EXISTS chart_of_accounts (
                    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    tenant_id BIGINT UNSIGNED NOT NULL,
                    account_code VARCHAR(20) NOT NULL,
                    account_name VARCHAR(200) NOT NULL,
                    account_type ENUM('asset', 'liability', 'equity', 'revenue', 'expense') NOT NULL,
                    parent_id BIGINT UNSIGNED NULL,
                    description TEXT NULL,
                    normal_balance ENUM('debit', 'credit') DEFAULT 'debit',
                    tax_related BOOLEAN DEFAULT FALSE,
                    report_section VARCHAR(50) NULL,
                    is_active BOOLEAN DEFAULT TRUE,
                    created_by BIGINT UNSIGNED DEFAULT 0,
                    updated_by BIGINT UNSIGNED DEFAULT 0,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
                    deleted_at TIMESTAMP NULL,
                    UNIQUE KEY uk_tenant_account (tenant_id, account_code),
                    INDEX idx_tenant_id (tenant_id),
                    INDEX idx_parent_id (parent_id),
                    INDEX idx_account_type (account_type),
                    INDEX idx_is_active (is_active),
                    FOREIGN KEY (parent_id) REFERENCES chart_of_accounts(id) ON DELETE SET NULL
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
        } catch (Exception $e) {
            $this->errorHandler->logError("Failed to ensure chart of accounts table exists: " . $e->getMessage());
            // Don't throw - table might be created elsewhere or migration will handle it
        }
    }

    /**
     * Validate account data
     * 
     * @param array $accountData Account data to validate
     * @param bool $allowPartial Allow partial updates (for PATCH operations)
     * @return array Validation result
     */
    private function validateAccountData(array $accountData, bool $allowPartial = false): array
    {
        $errors = [];
        
        // Required fields for creation
        if (!$allowPartial) {
            $requiredFields = ['account_code', 'account_name', 'account_type'];
            foreach ($requiredFields as $field) {
                if (empty($accountData[$field])) {
                    $errors[] = "$field is required";
                }
            }
        }
        
        // Validate account code format
        if (!empty($accountData['account_code'])) {
            if (!preg_match('/^[a-zA-Z0-9\-]+$/', $accountData['account_code'])) {
                $errors[] = 'Account code can only contain letters, numbers, and hyphens';
            }
            
            if (strlen($accountData['account_code']) > 20) {
                $errors[] = 'Account code cannot exceed 20 characters';
            }
        }
        
        // Validate account name
        if (!empty($accountData['account_name'])) {
            if (strlen($accountData['account_name']) > 200) {
                $errors[] = 'Account name cannot exceed 200 characters';
            }
        }
        
        // Validate account type
        $validTypes = ['asset', 'liability', 'equity', 'revenue', 'expense'];
        if (!empty($accountData['account_type']) && 
            !in_array($accountData['account_type'], $validTypes)) {
            $errors[] = 'Invalid account type';
        }
        
        // Validate normal balance
        $validBalances = ['debit', 'credit'];
        if (!empty($accountData['normal_balance']) && 
            !in_array($accountData['normal_balance'], $validBalances)) {
            $errors[] = 'Invalid normal balance';
        }
        
        // Validate parent_id if provided
        if (!empty($accountData['parent_id'])) {
            $parentId = (int)$accountData['parent_id'];
            if ($parentId <= 0) {
                $errors[] = 'Invalid parent account ID';
            } else {
                // Check if parent exists and belongs to same tenant
                $parentCheck = $this->pdo->prepare("
                    SELECT id FROM chart_of_accounts 
                    WHERE id = ? AND tenant_id = ? AND is_active = 1
                ");
                $parentCheck->execute([$parentId, $this->tenantId]);
                
                if (!$parentCheck->fetch()) {
                    $errors[] = 'Parent account not found or inactive';
                }
                
                // Prevent circular references (simplified check)
                if ($parentId == $accountData['id'] ?? 0) {
                    $errors[] = 'Account cannot be its own parent';
                }
            }
        }
        
        // Validate report section
        if (!empty($accountData['report_section'])) {
            $validSections = ['balance_sheet', 'income_statement', 'cash_flow', 'notes'];
            if (!in_array($accountData['report_section'], $validSections)) {
                $errors[] = 'Invalid report section';
            }
        }
        
        return [
            'valid' => empty($errors),
            'error' => implode('; ', $errors)
        ];
    }

    /**
     * Check if account has transactions (usage)
     * 
     * @param int $accountId Account ID
     * @return array Usage information
     */
    private function checkAccountUsage(int $accountId): array
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT COUNT(*) as transaction_count 
                FROM journal_entry_lines jel
                JOIN journal_entries je ON jel.journal_entry_id = je.id
                WHERE jel.account_id = ? 
                AND je.tenant_id = ?
            ");
            $stmt->execute([$accountId, $this->tenantId]);
            
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $transactionCount = (int)($result['transaction_count'] ?? 0);
            
            return [
                'has_transactions' => $transactionCount > 0,
                'transaction_count' => $transactionCount
            ];
        } catch (Exception $e) {
            $this->errorHandler->logError("Failed to check account usage: " . $e->getMessage());
            return [
                'has_transactions' => true, // Assume usage to be safe
                'transaction_count' => 0
            ];
        }
    }

    /**
     * Build account hierarchy from flat list
     * 
     * @param array $flatAccounts Flat list of accounts
     * @return array Hierarchical account structure
     */
    private function buildAccountHierarchy(array $flatAccounts): array
    {
        $tree = [];
        $children = [];
        
        // First, index all accounts by ID
        foreach ($flatAccounts as &$account) {
            $account['children'] = [];
            $children[$account['id']] = &$account;
        }
        
        // Then, build the tree
        foreach ($flatAccounts as $account) {
            if ($account['parent_id'] === null) {
                // Root level account
                $tree[] = &$children[$account['id']];
            } else if (isset($children[$account['parent_id']])) {
                // Child account
                $children[$account['parent_id']]['children'][] = &$children[$account['id']];
            }
            // Orphaned accounts (parent doesn't exist) are ignored
        }
        
        return $tree;
    }

    /**
     * Log activity for audit trail
     * 
     * @param string $action Action performed
     * @param array $details Additional details
     */
    private function logActivity(string $action, array $details = []): void
    {
        try {
            // This would integrate with an activity logging system
            // For now, we'll just log to the application log
            $logMessage = sprintf(
                '[ChartOfAccounts] User %d: %s - %s',
                $this->userId,
                $action,
                json_encode($details)
            );
            
            error_log($logMessage);
        } catch (Exception $e) {
            // Don't let logging errors break the main functionality
            error_log('Failed to log activity: ' . $e->getMessage());
        }
    }
}