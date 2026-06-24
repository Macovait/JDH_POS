<?php
declare(strict_types=1);

/**
 * Logger functions for Jakababa POS (SaaS Version)
 * Logs user activities to database with multi-tenant support
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

// Load required dependencies (always from shared src folder)
require_once dirname(__DIR__, 2) . '/src/paths.php';
safe_require('db.php', 'src', true);

/**
 * Get user activity logs with filters (SaaS-aware)
 * 
 * @param array $filters Optional filters (user_id, action, date_from, date_to, limit, offset, tenant_id, branch_id, reviewed)
 * @return array Array of activity logs with parsed meta
 */
function get_activity_logs(array $filters = []): array
{
    try {
        $pdo = get_db_connection();

        $sql = "
            SELECT 
                al.*,
                u.name as user_name,
                u.username as user_username,
                ru.name as reviewer_name
            FROM activity_logs al
            LEFT JOIN users u ON al.user_id = u.id
            LEFT JOIN users ru ON al.reviewed_by = ru.id
            WHERE 1=1
        ";

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

        // Action filter
        if (!empty($filters['action'])) {
            $sql .= " AND al.action LIKE ?";
            $params[] = '%' . $filters['action'] . '%';
        }

        // Date range filters
        if (!empty($filters['date_from'])) {
            $sql .= " AND DATE(al.created_at) >= ?";
            $params[] = $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $sql .= " AND DATE(al.created_at) <= ?";
            $params[] = $filters['date_to'];
        }

        // Reviewed filter
        if (isset($filters['reviewed'])) {
            $sql .= " AND al.reviewed = ?";
            $params[] = $filters['reviewed'] ? 1 : 0;
        }

        $sql .= " ORDER BY al.id DESC";

        // Pagination
        if (!empty($filters['limit'])) {
            $sql .= " LIMIT ?";
            $params[] = (int) $filters['limit'];
        }
        if (!empty($filters['offset'])) {
            $sql .= " OFFSET ?";
            $params[] = (int) $filters['offset'];
        }

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $logs = $stmt->fetchAll();

        // Parse meta JSON for each log
        foreach ($logs as &$log) {
            if (!empty($log['meta'])) {
                $decoded = json_decode($log['meta'], true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $log['meta_parsed'] = $decoded;
                } else {
                    $log['meta_parsed'] = $log['meta'];
                }
            } else {
                $log['meta_parsed'] = null;
            }
        }

        return $logs;

    } catch (Exception $e) {
        error_log("Error fetching activity logs: " . $e->getMessage());
        return [];
    }
}

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

        if (isset($filters['reviewed'])) {
            $sql .= " AND al.reviewed = ?";
            $params[] = $filters['reviewed'] ? 1 : 0;
        }

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();

    } catch (Exception $e) {
        error_log("Error counting activity logs: " . $e->getMessage());
        return 0;
    }
}

/**
 * Mark a log as reviewed
 * 
 * @param int $log_id The ID of the log to mark
 * @param int $reviewed_by The ID of the user marking it as reviewed
 * @return bool True on success, false on failure
 */
function mark_log_reviewed(int $log_id, int $reviewed_by): bool
{
    try {
        db_query("
            UPDATE activity_logs 
            SET reviewed = 1, reviewed_by = ?, reviewed_at = NOW() 
            WHERE id = ?
        ", [$reviewed_by, $log_id]);

        return true;

    } catch (Exception $e) {
        error_log("Error marking log reviewed: " . $e->getMessage());
        return false;
    }
}

/**
 * Add notes to a log
 * 
 * @param int $log_id The ID of the log
 * @param string $notes The notes to add
 * @return bool True on success, false on failure
 */
function add_log_notes(int $log_id, string $notes): bool
{
    try {
        db_query("UPDATE activity_logs SET notes = ? WHERE id = ?", [$notes, $log_id]);
        return true;

    } catch (Exception $e) {
        error_log("Error adding log notes: " . $e->getMessage());
        return false;
    }
}

/**
 * Get statistics about activity logs
 * 
 * @param string $date_from Start date (YYYY-MM-DD)
 * @param string $date_to End date (YYYY-MM-DD)
 * @param int|null $tenant_id Tenant ID for multi-tenant
 * @return array Statistics
 */
function get_activity_stats(string $date_from, string $date_to, ?int $tenant_id = null): array
{
    try {
        $pdo = get_db_connection();

        $sql = "
            SELECT 
                COUNT(*) as total_logs,
                COUNT(DISTINCT DATE(created_at)) as active_days,
                COUNT(DISTINCT action) as unique_actions,
                COUNT(DISTINCT user_id) as active_users,
                SUM(CASE WHEN reviewed = 0 THEN 1 ELSE 0 END) as unreviewed_count
            FROM activity_logs
            WHERE DATE(created_at) BETWEEN ? AND ?
        ";
        $params = [$date_from, $date_to];

        if ($tenant_id) {
            $sql .= " AND tenant_id = ?";
            $params[] = $tenant_id;
        } elseif (function_exists('get_current_tenant_id')) {
            $currentCompany = get_current_tenant_id();
            if ($currentCompany) {
                $sql .= " AND tenant_id = ?";
                $params[] = $currentCompany;
            }
        }

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $stats = $stmt->fetch();

        return $stats ?: [
            'total_logs' => 0,
            'active_days' => 0,
            'unique_actions' => 0,
            'active_users' => 0,
            'unreviewed_count' => 0
        ];

    } catch (Exception $e) {
        error_log("Error getting activity stats: " . $e->getMessage());
        return [
            'total_logs' => 0,
            'active_days' => 0,
            'unique_actions' => 0,
            'active_users' => 0,
            'unreviewed_count' => 0
        ];
    }
}

/**
 * Get activity timeline for chart
 * 
 * @param string $date_from Start date (YYYY-MM-DD)
 * @param string $date_to End date (YYYY-MM-DD)
 * @param int|null $tenant_id Tenant ID for multi-tenant
 * @return array Timeline data
 */
function get_activity_timeline(string $date_from, string $date_to, ?int $tenant_id = null): array
{
    try {
        $pdo = get_db_connection();

        $sql = "
            SELECT 
                DATE(created_at) as date,
                COUNT(*) as count
            FROM activity_logs
            WHERE DATE(created_at) BETWEEN ? AND ?
        ";
        $params = [$date_from, $date_to];

        if ($tenant_id) {
            $sql .= " AND tenant_id = ?";
            $params[] = $tenant_id;
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
        return $stmt->fetchAll();

    } catch (Exception $e) {
        error_log("Error getting activity timeline: " . $e->getMessage());
        return [];
    }
}

/**
 * Delete a log (permanent)
 * 
 * @param int $log_id The ID of the log to delete
 * @return bool True on success, false on failure
 */
function delete_log(int $log_id): bool
{
    try {
        db_query("DELETE FROM activity_logs WHERE id = ?", [$log_id]);
        return true;

    } catch (Exception $e) {
        error_log("Error deleting log: " . $e->getMessage());
        return false;
    }
}

/**
 * Archive old logs (move to archive table)
 * 
 * @param int $days Older than this many days
 * @param int|null $tenant_id Tenant ID for multi-tenant
 * @return int Number of logs archived
 */
function archive_old_logs(int $days = 30, ?int $tenant_id = null): int
{
    try {
        $pdo = get_db_connection();

        // Create archive table if it doesn't exist
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS activity_logs_archive LIKE activity_logs
        ");

        $pdo->beginTransaction();

        $where = "created_at < DATE_SUB(NOW(), INTERVAL ? DAY)";
        $params = [$days];

        if ($tenant_id) {
            $where .= " AND tenant_id = ?";
            $params[] = $tenant_id;
        } elseif (function_exists('get_current_tenant_id')) {
            $currentCompany = get_current_tenant_id();
            if ($currentCompany) {
                $where .= " AND tenant_id = ?";
                $params[] = $currentCompany;
            }
        }

        // Copy to archive
        $stmt = $pdo->prepare("
            INSERT INTO activity_logs_archive 
            SELECT * FROM activity_logs 
            WHERE $where
        ");
        $stmt->execute($params);
        $archived = $stmt->rowCount();

        // Delete from main table
        $stmt = $pdo->prepare("
            DELETE FROM activity_logs 
            WHERE $where
        ");
        $stmt->execute($params);

        $pdo->commit();

        return $archived;

    } catch (Exception $e) {
        if (isset($pdo) && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("Error archiving logs: " . $e->getMessage());
        return 0;
    }
}
