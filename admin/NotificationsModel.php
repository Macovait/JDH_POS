<?php
/**
 * Notifications Model - Zero-Trust Implementation
 * Handles notification management with controlled SaaS admin access
 */

require_once dirname(__DIR__) . '/src/TenantContext.php';
require_once dirname(__DIR__) . '/src/BaseModel.php';

class NotificationsModel extends BaseModel {
    protected $table = 'admin_notifications';

    public function getNotifications(array $filters = []): array {
        // SaaS admin only - can see all notifications
        if (!$this->context->allowCrossTenant()) {
            throw new Exception('Access denied: SaaS admin required for notifications');
        }

        $sql = "SELECT * FROM admin_notifications WHERE 1=1";
        $params = [];

        // Apply filters
        if (!empty($filters['filter'])) {
            switch ($filters['filter']) {
                case 'unread':
                    $sql .= " AND is_read = 0";
                    break;
                case 'subscription':
                    $sql .= " AND (title LIKE '%Subscrip%' OR title LIKE '%Trial%' OR title LIKE '%Plan%' OR title LIKE '%Billing%')";
                    break;
                case 'system':
                    $sql .= " AND (type = 'warning' OR type = 'error')";
                    break;
            }
        }

        $sql .= " ORDER BY created_at DESC LIMIT 50";

        $stmt = $this->pdo->prepare($sql);
        $this->applyTenantScope($stmt, $params);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getNotificationCounts(): array {
        // SaaS admin only
        if (!$this->context->allowCrossTenant()) {
            return ['all' => 0, 'unread' => 0, 'subscription' => 0, 'system' => 0];
        }

        $counts = ['all' => 0, 'unread' => 0, 'subscription' => 0, 'system' => 0];

        try {
            // All notifications
            $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM admin_notifications");
            $params = [];
            $this->applyTenantScope($stmt, $params);
            $stmt->execute($params);
            $counts['all'] = (int) $stmt->fetchColumn();

            // Unread notifications
            $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM admin_notifications WHERE is_read = 0");
            $params = [];
            $this->applyTenantScope($stmt, $params);
            $stmt->execute($params);
            $counts['unread'] = (int) $stmt->fetchColumn();

            // Subscription notifications
            $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM admin_notifications WHERE title LIKE '%Subscrip%' OR title LIKE '%Trial%' OR title LIKE '%Plan%' OR title LIKE '%Billing%'");
            $params = [];
            $this->applyTenantScope($stmt, $params);
            $stmt->execute($params);
            $counts['subscription'] = (int) $stmt->fetchColumn();

            // System notifications
            $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM admin_notifications WHERE type = 'warning' OR type = 'error'");
            $params = [];
            $this->applyTenantScope($stmt, $params);
            $stmt->execute($params);
            $counts['system'] = (int) $stmt->fetchColumn();

        } catch (Exception $e) {
            error_log("Notification count error: " . $e->getMessage());
        }

        return $counts;
    }

    public function createNotification(array $notificationData): int {
        // SaaS admin only
        if (!$this->context->allowCrossTenant()) {
            throw new Exception('Access denied: SaaS admin required');
        }

        // Validate notification data
        $this->validateNotificationData($notificationData);

        // Set audit trail
        $notificationData['created_by'] = $this->context->getUserId();

        return $this->insert($notificationData);
    }

    public function markAsRead(int $notificationId): void {
        // SaaS admin only
        if (!$this->context->allowCrossTenant()) {
            throw new Exception('Access denied: SaaS admin required');
        }

        // Verify notification exists
        $notification = $this->find($notificationId);
        if (!$notification) {
            throw new Exception('Notification not found');
        }

        $stmt = $this->pdo->prepare("UPDATE admin_notifications SET is_read = 1, read_at = NOW(), read_by = ? WHERE id = ?");
        $params = [$this->context->getUserId(), $notificationId];
        $this->applyTenantScope($stmt, $params);
        $stmt->execute($params);
    }

    public function markAllAsRead(): void {
        // SaaS admin only
        if (!$this->context->allowCrossTenant()) {
            throw new Exception('Access denied: SaaS admin required');
        }

        $stmt = $this->pdo->prepare("UPDATE admin_notifications SET is_read = 1, read_at = NOW(), read_by = ? WHERE is_read = 0");
        $params = [$this->context->getUserId()];
        $this->applyTenantScope($stmt, $params);
        $stmt->execute($params);
    }

    public function deleteNotification(int $notificationId): void {
        // SaaS admin only
        if (!$this->context->allowCrossTenant()) {
            throw new Exception('Access denied: SaaS admin required');
        }

        // Verify notification exists
        $notification = $this->find($notificationId);
        if (!$notification) {
            throw new Exception('Notification not found');
        }

        // Hard delete (admin_notifications has no deleted_at column)
        $stmt = $this->pdo->prepare("DELETE FROM admin_notifications WHERE id = ?");
        $params = [$notificationId];
        $this->applyTenantScope($stmt, $params);
        $stmt->execute($params);
    }

    // Auto-generate system notifications with controlled access
    public function generateSystemNotifications(): void {
        // SaaS admin only - only they can generate system-wide notifications
        if (!$this->context->allowCrossTenant()) {
            return;
        }

        try {
            $this->generateExpiredTrialsNotifications();
            $this->generateSuspendedCompaniesNotifications();
            $this->generateExpiringSubscriptionsNotifications();
        } catch (Exception $e) {
            error_log("System notification generation error: " . $e->getMessage());
        }
    }

    private function generateExpiredTrialsNotifications(): void {
        // Get expired trials - tenant-scoped query
        $stmt = $this->pdo->prepare("
            SELECT t.id, t.name
            FROM pos_tenants t
            JOIN pos_subscriptions s ON t.id = s.tenant_id
            WHERE s.trial_ends_at < NOW() AND s.status = 'trialing'
        ");
        $params = [];
        $this->applyTenantScope($stmt, $params);
        $stmt->execute($params);
        $expired_trials = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($expired_trials as $company) {
            // Check if notification already exists (within last 24 hours)
            $stmt = $this->pdo->prepare("
                SELECT id FROM admin_notifications
                WHERE type = 'warning' AND title = ? AND link LIKE ?
                AND created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)
            ");
            $params = ['Trial Expired: ' . $company['name'], '%tenant_id=' . $company['id'] . '%'];
            $this->applyTenantScope($stmt, $params);
            $stmt->execute($params);

            if (!$stmt->fetch()) {
                $this->createNotification([
                    'type' => 'warning',
                    'title' => 'Trial Expired: ' . $company['name'],
                    'message' => 'The trial period for "' . $company['name'] . '" has expired. Consider reaching out to convert them to a paid plan.',
                    'link' => 'companies.php?search=' . urlencode($company['name']),
                    'is_read' => 0,
                    'created_at' => date('Y-m-d H:i:s')
                ]);
            }
        }
    }

    private function generateSuspendedCompaniesNotifications(): void {
        // Get suspended companies - tenant-scoped query
        $stmt = $this->pdo->prepare("
            SELECT id, name FROM pos_tenants
            WHERE status = 'suspended'
        ");
        $params = [];
        $this->applyTenantScope($stmt, $params);
        $stmt->execute($params);
        $suspended = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($suspended as $company) {
            // Check if notification already exists (within last 24 hours)
            $stmt = $this->pdo->prepare("
                SELECT id FROM admin_notifications
                WHERE type = 'error' AND title = ?
                AND created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)
            ");
            $params = ['Company Suspended: ' . $company['name']];
            $this->applyTenantScope($stmt, $params);
            $stmt->execute($params);

            if (!$stmt->fetch()) {
                $this->createNotification([
                    'type' => 'error',
                    'title' => 'Company Suspended: ' . $company['name'],
                    'message' => 'Company "' . $company['name'] . '" is currently suspended. Review and take appropriate action.',
                    'link' => 'companies.php?search=' . urlencode($company['name']),
                    'is_read' => 0,
                    'created_at' => date('Y-m-d H:i:s')
                ]);
            }
        }
    }

    private function generateExpiringSubscriptionsNotifications(): void {
        // Get expiring subscriptions - tenant-scoped query
        $stmt = $this->pdo->prepare("
            SELECT s.id, t.name AS company_name, s.current_period_end
            FROM pos_subscriptions s
            JOIN pos_tenants t ON s.tenant_id = t.id
            WHERE s.status = 'active'
            AND s.current_period_end BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 7 DAY)
        ");
        $params = [];
        $this->applyTenantScope($stmt, $params);
        $stmt->execute($params);
        $expiring_subs = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($expiring_subs as $sub) {
            // Check if notification already exists (within last 24 hours)
            $stmt = $this->pdo->prepare("
                SELECT id FROM admin_notifications
                WHERE type = 'warning' AND title = ?
                AND created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)
            ");
            $params = ['Subscription Expiring: ' . $sub['company_name']];
            $this->applyTenantScope($stmt, $params);
            $stmt->execute($params);

            if (!$stmt->fetch()) {
                $this->createNotification([
                    'type' => 'warning',
                    'title' => 'Subscription Expiring: ' . $sub['company_name'],
                    'message' => 'The subscription for "' . $sub['company_name'] . '" expires on ' . date('M d, Y', strtotime($sub['current_period_end'])) . '. Renewal may be needed.',
                    'link' => 'subscriptions.php?search=' . urlencode($sub['company_name']),
                    'is_read' => 0,
                    'created_at' => date('Y-m-d H:i:s')
                ]);
            }
        }
    }

    private function validateNotificationData(array &$data): void {
        // Validate required fields
        if (empty($data['title'])) {
            throw new Exception('Notification title is required');
        }

        if (empty($data['message'])) {
            throw new Exception('Notification message is required');
        }

        // Validate type
        $allowedTypes = ['info', 'warning', 'success', 'error'];
        if (!in_array($data['type'] ?? 'info', $allowedTypes)) {
            $data['type'] = 'info';
        }

        // Set defaults
        $data['is_read'] = $data['is_read'] ?? 0;
        $data['created_at'] = $data['created_at'] ?? date('Y-m-d H:i:s');
    }
}