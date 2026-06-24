<?php
declare(strict_types=1);
/**
 * Activity Logger for Jakababa POS (SaaS Version)
 *
 * Handles logging of user activities, system events, and audit trails.
 * Supports multi-tenant SaaS architecture with company isolation.
 *
 * @package Jakababa
 * @subpackage Logger
 * @version 2.0
 */

// Prevent multiple inclusions
if (defined('LOGGER_LOADED')) {
    return;
}
define('LOGGER_LOADED', true);

require_once __DIR__ . '/paths.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php'; // for get_current_* functions

// -----------------------------------------------------------------------------
// Core Logging Function
// -----------------------------------------------------------------------------

if (!function_exists('log_activity')) {
    /**
     * Log user activity to the database
     *
     * @param int $userId The ID of the user performing the action (0 for system)
     * @param string $action The action being performed (e.g., 'user.login', 'product.created')
     * @param array $meta Additional metadata about the action
     * @param int|null $companyId Tenant ID for multi-tenant isolation
     * @param int|null $branchId Branch ID for branch-specific logging
     * @return bool True if logged successfully, false otherwise
     */
    function log_activity(int $userId, string $action, array $meta = [], ?int $companyId = null,
        ?int $branchId = null): bool {
        try {
            $pdo = get_db_connection();

            // Fallback to current context if not provided
            if ($companyId === null && function_exists('get_current_tenant_id')) {
                $companyId = get_current_tenant_id();
            }
            if ($branchId === null && function_exists('get_current_branch_id')) {
                $branchId = get_current_branch_id();
            }

            // Add context to meta
            $meta['ip_address'] = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
            $meta['user_agent'] = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
            $meta['timestamp'] = date('Y-m-d H:i:s');
            $meta['request_uri'] = $_SERVER['REQUEST_URI'] ?? 'unknown';
            $meta['request_method'] = $_SERVER['REQUEST_METHOD'] ?? 'unknown';

            if ($companyId) {
                $meta['tenant_id'] = $companyId;
            }
            if ($branchId) {
                $meta['branch_id'] = $branchId;
            }

            $metaJson = json_encode($meta, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

            // Check table columns to build insert dynamically
            $columns = db_get_columns('activity_logs');
            $colNames = array_column($columns, 'Field');

            $insertFields = ['user_id', 'action', 'meta', 'created_at'];
            $insertValues = [$userId, $action, $metaJson, date('Y-m-d H:i:s')];

            if (in_array('tenant_id', $colNames)) {
                $insertFields[] = 'tenant_id';
                $insertValues[] = $companyId;
            }
            if (in_array('branch_id', $colNames)) {
                $insertFields[] = 'branch_id';
                $insertValues[] = $branchId;
            }

            $placeholders = implode(',', array_fill(0, count($insertFields), '?'));
            $sql = "INSERT INTO activity_logs (" . implode(',', $insertFields) . ") VALUES ($placeholders)";

            $stmt = $pdo->prepare($sql);
            $result = $stmt->execute($insertValues);

            // Log critical actions to error log as well
            $criticalActions = ['error', 'failed', 'security', 'permission', 'limit_reached'];
            if (in_array($action, $criticalActions) || strpos($action, 'error') !== false) {
                error_log("[POS Activity] " . ($companyId ? "[Company:$companyId] " : "") . "User $userId: $action - " . substr($metaJson, 0, 200));
            }

            return $result;
        } catch (Exception $e) {
            error_log("Failed to log activity: " . $e->getMessage());
            error_log("Activity details - User: $userId, Action: $action, Meta: " . json_encode($meta));
            return false;
        }
    }
}

// -----------------------------------------------------------------------------
// Specialized Logging Functions
// -----------------------------------------------------------------------------

if (!function_exists('log_error')) {
    /**
     * Log error events
     *
     * @param string $error Error message
     * @param array $context Additional context
     * @param int|null $userId Optional user ID
     * @param int|null $companyId Optional company ID
     * @return bool
     */
    function log_error(string $error, array $context = [], ?int $userId = null, ?int $companyId = null): bool
    {
        $meta = array_merge($context, ['error' => $error]);
        $userId = $userId ?? (function_exists('get_current_user_id') ? get_current_user_id() : 0);
        return log_activity($userId, 'system.error', $meta, $companyId, get_current_tenant_id());
    }
}

if (!function_exists('log_login_attempt')) {
    /**
     * Log login attempts (successful or failed)
     *
     * @param string $username Username attempted
     * @param bool $success Whether login was successful
     * @param int|null $userId User ID if successful
     * @param int|null $companyId Tenant ID
     * @return bool
     */
    function log_login_attempt(string $username, bool $success, ?int $userId = null, ?int $companyId = null): bool
    {
        $action = $success ? 'auth.login' : 'auth.login_failed';
        $meta = [
            'username' => $username,
            'success' => $success,
            'login_time' => date('Y-m-d H:i:s')
        ];
        return log_activity($userId ?? 0, $action, $meta, $companyId, get_current_tenant_id());
    }
}

if (!function_exists('log_database_change')) {
    /**
     * Log database changes (INSERT, UPDATE, DELETE)
     *
     * @param string $table Table name
     * @param string $operation Operation type (INSERT, UPDATE, DELETE)
     * @param int $recordId Record ID affected
     * @param array $oldData Old data (for UPDATE)
     * @param array $newData New data (for INSERT/UPDATE)
     * @param int $userId User ID
     * @param int|null $companyId Tenant ID
     * @return bool
     */
    function log_database_change(
        string $table,
        string $operation,
        int $recordId,
        array $oldData = [],
        array $newData = [],
        int $userId = 0,
        ?int $companyId = null
    ): bool {
        $meta = [
            'table' => $table,
            'operation' => $operation,
            'record_id' => $recordId
        ];

        // Remove sensitive fields from data
        $sensitive = ['password', 'password_hash', 'api_token', 'reset_token', 'two_factor_secret'];

        if (!empty($oldData)) {
            $filtered = array_diff_key($oldData, array_flip($sensitive));
            $meta['old_data'] = array_slice($filtered, 0, 15); // Limit size
        }

        if (!empty($newData)) {
            $filtered = array_diff_key($newData, array_flip($sensitive));
            $meta['new_data'] = array_slice($filtered, 0, 15);
        }

        return log_activity($userId, "database.$operation", $meta, $companyId, get_current_tenant_id());
    }
}

if (!function_exists('log_sale')) {
    /**
     * Log sale transactions
     *
     * @param int $saleId Sale ID
     * @param float $total Sale total
     * @param string $paymentMethod Payment method used
     * @param int $userId User ID
     * @param int|null $customerId Customer ID (optional)
     * @param int|null $branchId Branch ID (optional)
     * @param int|null $companyId Tenant ID (optional)
     * @param string|null $invoiceNumber Optional invoice number
     * @return bool
     */
    function log_sale(
        int $saleId,
        float $total,
        string $paymentMethod,
        int $userId,
        ?int $customerId = null,
        ?int $branchId = null,
        ?int $companyId = null,
        ?string $invoiceNumber = null
    ): bool {
        $meta = [
            'sale_id' => $saleId,
            'total' => $total,
            'payment_method' => $paymentMethod,
        ];
        if ($invoiceNumber) {
            $meta['invoice_number'] = $invoiceNumber;
        }
        if ($customerId) {
            $meta['customer_id'] = $customerId;
        }
        if ($branchId) {
            $meta['branch_id'] = $branchId;
        }
        return log_activity($userId, 'sale.completed', $meta, $companyId, $branchId, get_current_tenant_id());
    }
}

if (!function_exists('log_inventory_change')) {
    /**
     * Log inventory changes
     *
     * @param int $productId Product ID
     * @param int $branchId Branch ID
     * @param int $oldStock Old stock level
     * @param int $newStock New stock level
     * @param string $reason Reason for change
     * @param int $userId User ID
     * @param int|null $companyId Tenant ID
     * @return bool
     */
    function log_inventory_change(
        int $productId,
        int $branchId,
        int $oldStock,
        int $newStock,
        string $reason,
        int $userId,
        ?int $companyId = null
    ): bool {
        $change = $newStock - $oldStock;
        $meta = [
            'product_id' => $productId,
            'branch_id' => $branchId,
            'old_stock' => $oldStock,
            'new_stock' => $newStock,
            'change' => $change,
            'reason' => $reason,
            'change_absolute' => abs($change)
        ];
        $action = $change > 0 ? 'inventory.increased' : ($change < 0 ? 'inventory.decreased' : 'inventory.unchanged');
        return log_activity($userId, $action, $meta, $companyId, $branchId, get_current_tenant_id());
    }
}

// -----------------------------------------------------------------------------
// Retrieval Functions
// -----------------------------------------------------------------------------

if (!function_exists('get_activity_logs')) {
    /**
     * Retrieve activity logs with filtering
     *
     * @param array $filters Filter criteria (user_id, action, date_from, date_to, limit, offset, tenant_id, branch_id)
     * @return array Array of log entries with parsed meta
     */
    function get_activity_logs(array $filters = []): array
    {
        try {
            $pdo = get_db_connection();

            $sql = "SELECT al.*, u.name as user_name, u.username as user_username
                    FROM activity_logs al
                    LEFT JOIN users u ON al.user_id = u.id
                    WHERE 1=1";
            $params = [];

            // Company filter (multi-tenant)
            if (!empty($filters['tenant_id'])) {
                $sql .= " AND al.tenant_id = ?";
                $params[] = (int) $filters['tenant_id'];
            } elseif (function_exists('get_current_tenant_id')) {
                $companyId = get_current_tenant_id();
                if ($companyId) {
                    $sql .= " AND al.tenant_id = ?";
                    $params[] = $companyId;
                }
            }

            // Branch filter
            if (!empty($filters['branch_id'])) {
                $sql .= " AND al.branch_id = ?";
                $params[] = (int) $filters['branch_id'];
            }

            // User filter
            if (!empty($filters['user_id'])) {
                $sql .= " AND al.user_id = ?";
                $params[] = (int) $filters['user_id'];
            }

            // Action pattern
            if (!empty($filters['action'])) {
                $sql .= " AND al.action LIKE ?";
                $params[] = '%' . $filters['action'] . '%';
            }

            // Date range
            if (!empty($filters['date_from'])) {
                $sql .= " AND DATE(al.created_at) >= ?";
                $params[] = $filters['date_from'];
            }
            if (!empty($filters['date_to'])) {
                $sql .= " AND DATE(al.created_at) <= ?";
                $params[] = $filters['date_to'];
            }

            // Order and pagination
            $sql .= " ORDER BY al.created_at DESC, al.id DESC";
            $limit = $filters['limit'] ?? 100;
            $offset = $filters['offset'] ?? 0;
            $sql .= " LIMIT ? OFFSET ?";
            $params[] = (int) $limit;
            $params[] = (int) $offset;

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $logs = $stmt->fetchAll();

            // Parse meta JSON
            foreach ($logs as &$log) {
                if (!empty($log['meta'])) {
                    $log['meta_parsed'] = json_decode($log['meta'], true);
                    if (json_last_error() !== JSON_ERROR_NONE) {
                        $log['meta_parsed'] = ['raw' => $log['meta']];
                    }
                } else {
                    $log['meta_parsed'] = [];
                }
            }

            return $logs;
        } catch (Exception $e) {
            error_log("Failed to retrieve activity logs: " . $e->getMessage());
            return [];
        }
    }
}

if (!function_exists('get_activity_count')) {
    /**
     * Get total count of activity logs matching filters
     *
     * @param array $filters Same filters as get_activity_logs
     * @return int Total count
     */
    function get_activity_count(array $filters = []): int
    {
        try {
            $pdo = get_db_connection();

            $sql = "SELECT COUNT(*) FROM activity_logs al WHERE 1=1";
            $params = [];

            if (!empty($filters['tenant_id'])) {
                $sql .= " AND al.tenant_id = ?";
                $params[] = (int) $filters['tenant_id'];
            } elseif (function_exists('get_current_tenant_id')) {
                $companyId = get_current_tenant_id();
                if ($companyId) {
                    $sql .= " AND al.tenant_id = ?";
                    $params[] = $companyId;
                }
            }

            if (!empty($filters['branch_id'])) {
                $sql .= " AND al.branch_id = ?";
                $params[] = (int) $filters['branch_id'];
            }

            if (!empty($filters['user_id'])) {
                $sql .= " AND al.user_id = ?";
                $params[] = (int) $filters['user_id'];
            }

            if (!empty($filters['action'])) {
                $sql .= " AND al.action LIKE ?";
                $params[] = '%' . $filters['action'] . '%';
            }

            if (!empty($filters['date_from'])) {
                $sql .= " AND DATE(al.created_at) >= ?";
                $params[] = $filters['date_from'];
            }
            if (!empty($filters['date_to'])) {
                $sql .= " AND DATE(al.created_at) <= ?";
                $params[] = $filters['date_to'];
            }

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            return (int) $stmt->fetchColumn();
        } catch (Exception $e) {
            error_log("Failed to count activity logs: " . $e->getMessage());
            return 0;
        }
    }
}

if (!function_exists('clean_old_logs')) {
    /**
     * Clean up old activity logs (older than specified days)
     *
     * @param int $days Number of days to keep
     * @param int|null $companyId Tenant ID for multi-tenant cleanup
     * @param int|null $userId Optional user ID to limit cleanup
     * @return int Number of deleted logs
     */
    function clean_old_logs(int $days = 90, ?int $companyId = null, ?int $userId = null): int
    {
        try {
            $pdo = get_db_connection();

            $sql = "DELETE FROM activity_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)";
            $params = [$days];

            if ($companyId) {
                $sql .= " AND tenant_id = ?";
                $params[] = $companyId;
            }
            if ($userId) {
                $sql .= " AND user_id = ?";
                $params[] = $userId;
            }

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $deleted = $stmt->rowCount();

            // Log the cleanup
            $cleanupUserId = function_exists('get_current_user_id') ? get_current_user_id() : 0;
            log_activity($cleanupUserId, 'system.logs_cleaned', [
                'days_kept' => $days, 'deleted_count' => $deleted,
                'company_id_filter' => $companyId,
                'user_id_filter' => $userId
            ], $companyId, get_current_tenant_id());

            return $deleted;
        } catch (Exception $e) {
            error_log("Failed to clean old logs: " . $e->getMessage());
            return 0;
        }
    }
}

if (!function_exists('get_user_activity_summary')) {
    /**
     * Get activity summary for a user
     *
     * @param int $userId User ID
     * @param int $days Number of days to look back
     * @param int|null $companyId Tenant ID for multi-tenant
     * @return array Summary statistics
     */
    function get_user_activity_summary(int $userId, int $days = 30, ?int $companyId = null): array
    {
        try {
            $pdo = get_db_connection();

            $sql = "
                SELECT
                    COUNT(*) as total_actions,
                    COUNT(DISTINCT DATE(created_at)) as active_days,
                    COUNT(DISTINCT action) as unique_actions,
                    MAX(created_at) as last_activity,
                    MIN(created_at) as first_activity
                FROM activity_logs
                WHERE user_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
            ";
            $params = [$userId, $days];

            if ($companyId) {
                $sql .= " AND tenant_id = ?";
                $params[] = $companyId;
            } elseif (function_exists('get_current_tenant_id')) {
                $currentCompany = get_current_tenant_id();
                if ($currentCompany) {
                    $sql .= " AND tenant_id = ?";
                    $params[] = $currentCompany;
                }
            }

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $result = $stmt->fetch();

            if (!$result) {
                return [
                    'total_actions' => 0,
                    'active_days' => 0,
                    'unique_actions' => 0,
                    'last_activity' => null,
                    'first_activity' => null
                ];
            }

            // Get top actions
            $actionStmt = $pdo->prepare("
                SELECT action, COUNT(*) as count
                FROM activity_logs
                WHERE user_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
                GROUP BY action
                ORDER BY count DESC
                LIMIT 5
            ");
            $actionStmt->execute([$userId, $days]);
            $result['top_actions'] = $actionStmt->fetchAll();

            return $result;
        } catch (Exception $e) {
            error_log("Failed to get user activity summary: " . $e->getMessage());
            return [
                'total_actions' => 0,
                'active_days' => 0,
                'unique_actions' => 0,
                'last_activity' => null,
                'first_activity' => null,
                'top_actions' => []
            ];
        }
    }
}

if (!function_exists('get_popular_actions')) {
    /**
     * Get most common actions
     *
     * @param int $limit Number of actions to return
     * @param int $days Number of days to look back
     * @param int|null $companyId Tenant ID for multi-tenant
     * @return array List of actions with counts
     */
    function get_popular_actions(int $limit = 10, int $days = 30, ?int $companyId = null): array
    {
        try {
            $pdo = get_db_connection();

            $sql = "
                SELECT
                    action,
                    COUNT(*) as count,
                    COUNT(DISTINCT user_id) as unique_users
                FROM activity_logs
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
            ";
            $params = [$days];

            if ($companyId) {
                $sql .= " AND tenant_id = ?";
                $params[] = $companyId;
            } elseif (function_exists('get_current_tenant_id')) {
                $currentCompany = get_current_tenant_id();
                if ($currentCompany) {
                    $sql .= " AND tenant_id = ?";
                    $params[] = $currentCompany;
                }
            }

            $sql .= " GROUP BY action ORDER BY count DESC LIMIT ?";
            $params[] = $limit;

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll();
        } catch (Exception $e) {
            error_log("Failed to get popular actions: " . $e->getMessage());
            return [];
        }
    }
}

if (!function_exists('get_activity_timeline')) {
    /**
     * Get activity timeline for charts/dashboard
     *
     * @param int $days Number of days to include
     * @param int|null $companyId Tenant ID for multi-tenant
     * @return array Timeline data grouped by date
     */
    function get_activity_timeline(int $days = 7, ?int $companyId = null): array
    {
        try {
            $pdo = get_db_connection();

            $sql = "
                SELECT
                    DATE(created_at) as date,
                    COUNT(*) as total_actions,
                    COUNT(DISTINCT user_id) as unique_users,
                    COUNT(DISTINCT action) as unique_actions
                FROM activity_logs
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
            ";
            $params = [$days];

            if ($companyId) {
                $sql .= " AND tenant_id = ?";
                $params[] = $companyId;
            } elseif (function_exists('get_current_tenant_id')) {
                $currentCompany = get_current_tenant_id();
                if ($currentCompany) {
                    $sql .= " AND tenant_id = ?";
                    $params[] = $currentCompany;
                }
            }

            $sql .= " GROUP BY DATE(created_at) ORDER BY date ASC";

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $results = $stmt->fetchAll();

            // Fill missing dates
            $timeline = [];
            $startDate = new DateTime("-{$days} days");
            $endDate = new DateTime();
            $interval = new DateInterval('P1D');
            $dateRange = new DatePeriod($startDate, $interval, $endDate);

            foreach ($dateRange as $date) {
                $dateStr = $date->format('Y-m-d');
                $found = false;
                foreach ($results as $row) {
                    if ($row['date'] === $dateStr) {
                        $timeline[] = $row;
                        $found = true;
                        break;
                    }
                }
                if (!$found) {
                    $timeline[] = [
                        'date' => $dateStr,
                        'total_actions' => 0,
                        'unique_users' => 0,
                        'unique_actions' => 0
                    ];
                }
            }

            return $timeline;
        } catch (Exception $e) {
            error_log("Failed to get activity timeline: " . $e->getMessage());
            return [];
        }
    }
}