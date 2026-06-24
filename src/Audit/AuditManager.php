<?php
/**
 * Audit Trail Manager
 * Full audit trail with export capabilities
 */

namespace Jakababa\Audit;

class AuditManager
{
    private PDO $pdo;
    private int $tenantId;
    private int $branchId;

    public function __construct(PDO $pdo, int $tenantId, int $branchId)
    {
        $this->pdo = $pdo;
        $this->tenantId = $tenantId;
        $this->branchId = $branchId;
    }

    /**
     * Log an audit event
     */
    public function log(string $action, string $description, array $meta = []): void
    {
        $meta['branch_id'] = $this->branchId;
        $meta['tenant_id'] = $this->tenantId;

        $stmt = $this->pdo->prepare("
            INSERT INTO audit_logs 
            (action, description, meta, branch_id, tenant_id, ip_address, user_agent, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([
            $action,
            $description,
            json_encode($meta),
            $this->branchId,
            $this->tenantId,
            $_SERVER['REMOTE_ADDR'] ?? '',
            substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255)
        ]);
    }

    /**
     * Get audit logs with filters
     */
    public function getLogs(array $filters = []): array
    {
        $where = ["tenant_id = ?", "branch_id = ?"];
        $params = [$this->tenantId, $this->branchId];

        if (!empty($filters['action'])) {
            $where[] = "action = ?";
            $params[] = $filters['action'];
        }

        if (!empty($filters['date_from'])) {
            $where[] = "DATE(created_at) >= ?";
            $params[] = $filters['date_from'];
        }

        if (!empty($filters['date_to'])) {
            $where[] = "DATE(created_at) <= ?";
            $params[] = $filters['date_to'];
        }

        if (!empty($filters['search'])) {
            $where[] = "(description LIKE ? OR meta LIKE ?)";
            $searchTerm = '%' . $filters['search'] . '%';
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }

        $sql = "SELECT * FROM audit_logs WHERE " . implode(" AND ", $where);
        $sql .= " ORDER BY created_at DESC LIMIT 500";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Export audit logs to CSV
     */
    public function exportToCsv(array $filters = []): string
    {
        $logs = $this->getLogs($filters);

        $output = fopen('php://temp', 'r+');
        fputcsv($output, ['ID', 'Action', 'Description', 'Meta', 'Branch ID', 'Created At']);

        foreach ($logs as $log) {
            fputcsv($output, [
                $log['id'],
                $log['action'],
                $log['description'],
                $log['meta'],
                $log['branch_id'],
                $log['created_at']
            ]);
        }

        rewind($output);
        return stream_get_contents($output);
    }

    /**
     * Export audit logs to JSON
     */
    public function exportToJson(array $filters = []): string
    {
        $logs = $this->getLogs($filters);
        
        foreach ($logs as &$log) {
            $log['meta'] = json_decode($log['meta'] ?? '{}', true);
        }

        return json_encode($logs, JSON_PRETTY_PRINT);
    }

    /**
     * Get audit statistics
     */
    public function getStatistics(int $days = 30): array
    {
        $stmt = $this->pdo->prepare("
            SELECT action, COUNT(*) as count
            FROM audit_logs
            WHERE tenant_id = ? AND branch_id = ? 
            AND created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
            GROUP BY action
            ORDER BY count DESC
        ");
        $stmt->execute([$this->tenantId, $this->branchId, $days]);
        
        $stats = [];
        while ($row = $stmt->fetch()) {
            $stats[$row['action']] = (int)$row['count'];
        }

        return $stats;
    }

    /**
     * Get user activity summary
     */
    public function getUserActivity(int $userId, int $days = 30): array
    {
        $stmt = $this->pdo->prepare("
            SELECT action, COUNT(*) as count, MAX(created_at) as last_activity
            FROM audit_logs
            WHERE tenant_id = ? AND user_id = ?
            AND created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
            GROUP BY action
            ORDER BY count DESC
        ");
        $stmt->execute([$this->tenantId, $userId, $days]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}