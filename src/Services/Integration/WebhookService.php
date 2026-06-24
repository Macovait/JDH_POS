<?php
/**
 * Webhook Service - Event-driven integrations
 * 
 * Manages webhook subscriptions and deliveries
 * for real-time third-party integrations
 * 
 * @package JakababaPOS
 * @version 2.0.0
 */

namespace JakababaPOS\Services\Integration;

class WebhookService
{
    private \PDO $db;
    private static ?WebhookService $instance = null;
    
    const TABLE_PREFIX = 'pos_';
    const MAX_RETRY_ATTEMPTS = 3;
    const RETRY_DELAY = 60; // seconds
    
    /**
     * Available events
     */
    const EVENTS = [
        // Sale events
        'sale.created',
        'sale.completed',
        'sale.refunded',
        'sale.cancelled',
        
        // Customer events
        'customer.created',
        'customer.updated',
        
        // Product events
        'product.created',
        'product.updated',
        'product.low_stock',
        
        // Subscription events
        'subscription.created',
        'subscription.upgraded',
        'subscription.downgraded',
        'subscription.cancelled',
        'subscription.expired',
        'subscription.payment_failed',
        
        // User events
        'user.created',
        'user.login',
        'user.login_failed',
        
        // Inventory events
        'inventory.transferred',
        'inventory.adjusted',
        
        // Payment events
        'payment.received',
        'payment.failed',
    ];
    
    /**
     * Get singleton instance
     */
    public static function getInstance(\PDO $db): self
    {
        if (self::$instance === null) {
            self::$instance = new self($db);
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    public function __construct(\PDO $db)
    {
        $this->db = $db;
    }
    
    /**
     * Create webhook subscription
     */
    public function create(
        int $tenantId,
        string $url,
        array $events,
        ?string $secret = null
    ): int {
        $secret = $secret ?? bin2hex(random_bytes(32));
        
        $stmt = $this->db->prepare("
            INSERT INTO " . self::TABLE_PREFIX . "webhooks
            (tenant_id, url, events, secret)
            VALUES (:tenant_id, :url, :events, :secret)
        ");
        
        $stmt->execute([
            ':tenant_id' => $tenantId,
            ':url' => $url,
            ':events' => json_encode($events),
            ':secret' => $secret
        ]);
        
        return (int) $this->db->lastInsertId();
    }
    
    /**
     * Update webhook
     */
    public function update(
        int $webhookId,
        ?string $url = null,
        ?array $events = null,
        ?bool $active = null
    ): bool {
        $updates = [];
        $params = [':id' => $webhookId];
        
        if ($url !== null) {
            $updates[] = 'url = :url';
            $params[':url'] = $url;
        }
        
        if ($events !== null) {
            $updates[] = 'events = :events';
            $params[':events'] = json_encode($events);
        }
        
        if ($active !== null) {
            $updates[] = 'is_active = :active';
            $params[':active'] = $active ? 1 : 0;
        }
        
        if (empty($updates)) {
            return false;
        }
        
        $sql = "UPDATE " . self::TABLE_PREFIX . "webhooks SET " . implode(', ', $updates) . " WHERE id = :id";
        
        return (bool) $this->db->prepare($sql)->execute($params);
    }
    
    /**
     * Delete webhook
     */
    public function delete(int $webhookId): bool
    {
        return (bool) $this->db->prepare("
            DELETE FROM " . self::TABLE_PREFIX . "webhooks WHERE id = :id
        ")->execute([':id' => $webhookId]);
    }
    
    /**
     * Get webhooks for tenant
     */
    public function getWebhooks(?int $tenantId = null): array
    {
        $tenantId = $tenantId ?? $_SESSION['tenant_id'] ?? null;
        
        if (!$tenantId) {
            return [];
        }
        
        return $this->db->query("
            SELECT * FROM " . self::TABLE_PREFIX . "webhooks
            WHERE tenant_id = $tenantId
            ORDER BY created_at DESC
        ")->fetchAll(\PDO::FETCH_ASSOC);
    }
    
    /**
     * Trigger event - queues webhook deliveries
     */
    public function trigger(
        string $event,
        array $payload,
        ?int $tenantId = null
    ): int {
        $tenantId = $tenantId ?? $_SESSION['tenant_id'] ?? null;
        
        if (!$tenantId || !in_array($event, self::EVENTS)) {
            return 0;
        }
        
        // Get active webhooks subscribed to this event
        $stmt = $this->db->prepare("
            SELECT * FROM " . self::TABLE_PREFIX . "webhooks
            WHERE tenant_id = :tenant_id AND is_active = 1
        ");
        
        $stmt->execute([':tenant_id' => $tenantId]);
        $webhooks = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        
        $delivered = 0;
        
        foreach ($webhooks as $webhook) {
            $events = json_decode($webhook['events'], true) ?? [];
            
            if (in_array($event, $events) || in_array('*', $events)) {
                $this->queueDelivery($webhook['id'], $event, $payload);
                $delivered++;
            }
        }
        
        return $delivered;
    }
    
    /**
     * Queue webhook delivery
     */
    private function queueDelivery(int $webhookId, string $event, array $payload): void
    {
        $this->db->prepare("
            INSERT INTO " . self::TABLE_PREFIX . "webhook_deliveries
            (webhook_id, event, payload, status)
            VALUES (:webhook_id, :event, :payload, 'pending')
        ")->execute([
            ':webhook_id' => $webhookId,
            ':event' => $event,
            ':payload' => json_encode($payload, JSON_UNESCAPED_UNICODE)
        ]);
    }
    
    /**
     * Process queued deliveries (call from cron job)
     */
    public function processDeliveries(?int $limit = 50): int
    {
        $stmt = $this->db->query("
            SELECT wd.*, w.url, w.secret, w.failure_count
            FROM " . self::TABLE_PREFIX . "webhook_deliveries wd
            INNER JOIN " . self::TABLE_PREFIX . "webhooks w ON wd.webhook_id = w.id
            WHERE wd.status = 'pending'
            AND wd.attempt < " . self::MAX_RETRY_ATTEMPTS . "
            ORDER BY wd.created_at ASC
            LIMIT $limit
        ");
        
        $deliveries = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        
        $processed = 0;
        
        foreach ($deliveries as $delivery) {
            $success = $this->sendWebhook($delivery);
            
            if ($success) {
                $this->markDelivered($delivery['id'], $delivery['response_status'] ?? 200);
            } else {
                $this->markFailed($delivery['id'], $delivery['failure_count'] + 1);
            }
            
            $processed++;
        }
        
        return $processed;
    }
    
    /**
     * Send webhook HTTP request
     */
    private function sendWebhook(array $delivery): bool
    {
        $url = $delivery['url'];
        $payload = json_decode($delivery['payload'], true);
        $secret = $delivery['secret'];
        
        // Generate signature
        $signature = $this->generateSignature($payload, $secret);
        
        $ch = curl_init($url);
        
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'X-Webhook-Signature: ' . $signature,
            'X-Webhook-Event: ' . $delivery['event'],
            'X-Webhook-Delivery-ID: ' . $delivery['id'],
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        
        $response = curl_exec($ch);
        $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        
        curl_close($ch);
        
        // Store response
        $this->db->prepare("
            UPDATE " . self::TABLE_PREFIX . "webhook_deliveries
            SET response_status = :status, response_body = :body
            WHERE id = :id
        ")->execute([
            ':status' => $statusCode,
            ':body' => $response ?: $error,
            ':id' => $delivery['id']
        ]);
        
        return $statusCode >= 200 && $statusCode < 300;
    }
    
    /**
     * Generate HMAC signature
     */
    private function generateSignature(array $payload, string $secret): string
    {
        $json = json_encode($payload);
        
        return 'sha256=' . hash_hmac('sha256', $json, $secret);
    }
    
    /**
     * Mark delivery as successful
     */
    private function markDelivered(int $deliveryId, int $statusCode): void
    {
        $this->db->prepare("
            UPDATE " . self::TABLE_PREFIX . "webhook_deliveries
            SET status = 'success', response_status = :status
            WHERE id = :id
        ")->execute([':status' => $statusCode, ':id' => $deliveryId]);
        
        // Update last triggered
        $this->db->prepare("
            UPDATE " . self::TABLE_PREFIX . "webhooks w
            INNER JOIN " . self::TABLE_PREFIX . "webhook_deliveries wd ON w.id = wd.webhook_id
            SET w.last_triggered_at = NOW(), w.failure_count = 0
            WHERE wd.id = :id
        ")->execute([':id' => $deliveryId]);
    }
    
    /**
     * Mark delivery as failed
     */
    private function markFailed(int $deliveryId, int $attempt): void
    {
        $this->db->prepare("
            UPDATE " . self::TABLE_PREFIX . "webhook_deliveries
            SET status = 'failed', attempt = :attempt
            WHERE id = :id
        ")->execute([':attempt' => $attempt, ':id' => $deliveryId]);
        
        // Increment failure count on webhook
        $this->db->prepare("
            UPDATE " . self::TABLE_PREFIX . "webhooks
            SET failure_count = failure_count + 1
            WHERE id = (
                SELECT webhook_id FROM " . self::TABLE_PREFIX . "webhook_deliveries WHERE id = :id
            )
        ")->execute([':id' => $deliveryId]);
    }
    
    /**
     * Test webhook
     */
    public function test(int $webhookId): array
    {
        $stmt = $this->db->prepare("
            SELECT * FROM " . self::TABLE_PREFIX . "webhooks WHERE id = :id
        ");
        
        $stmt->execute([':id' => $webhookId]);
        $webhook = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        if (!$webhook) {
            return ['success' => false, 'error' => 'Webhook not found'];
        }
        
        $payload = [
            'event' => 'test',
            'data' => ['message' => 'This is a test webhook'],
            'timestamp' => time()
        ];
        
        $delivery = [
            'id' => 0,
            'url' => $webhook['url'],
            'secret' => $webhook['secret'],
            'event' => 'test',
            'payload' => json_encode($payload),
            'response_status' => 0,
            'failure_count' => 0
        ];
        
        $success = $this->sendWebhook($delivery);
        
        return [
            'success' => $success,
            'url' => $webhook['url']
        ];
    }
}

/**
 * Helper functions
 */
if (!function_exists('triggerWebhook')) {
    function triggerWebhook(string $event, array $payload, ?int $tenantId = null): int
    {
        global $db;
        $service = WebhookService::getInstance($db);
        return $service->trigger($event, $payload, $tenantId);
    }
}