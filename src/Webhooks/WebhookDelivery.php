<?php
/**
 * Webhook Delivery System
 * Handles outgoing webhooks to tenant-configured endpoints with retry logic.
 */

namespace JDH\POS\Webhooks;

use PDO;
use Exception;

class WebhookDelivery
{
    private PDO $pdo;
    private array $retrySchedule = [0, 300, 1800, 7200, 86400]; // immediate, 5m, 30m, 2h, 24h

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->ensureTablesExist();
    }

    /**
     * Register a webhook endpoint for a tenant.
     */
    public function registerEndpoint(int $tenantId, string $url, string $secret, array $events = []): int
    {
        $eventsJson = json_encode($events);
        $stmt = $this->pdo->prepare("
            INSERT INTO webhook_endpoints (tenant_id, url, secret, events, status, created_at)
            VALUES (?, ?, ?, ?, 'active', NOW())
            ON DUPLICATE KEY UPDATE url = VALUES(url), secret = VALUES(secret), events = VALUES(events), updated_at = NOW()
        ");
        $stmt->execute([$tenantId, $url, $secret, $eventsJson]);
        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Dispatch an event to all matching endpoints.
     */
    public function dispatch(string $eventType, array $payload, ?int $tenantId = null): array
    {
        $where = "status = 'active' AND (events = '[]' OR events LIKE ?)";
        $params = ["%\"{$eventType}\"%"];

        if ($tenantId) {
            $where .= " AND tenant_id = ?";
            $params[] = $tenantId;
        }

        $stmt = $this->pdo->prepare("SELECT * FROM webhook_endpoints WHERE {$where}");
        $stmt->execute($params);
        $endpoints = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $results = [];
        foreach ($endpoints as $ep) {
            $result = $this->queueDelivery((int) $ep['id'], $eventType, $payload);
            $results[] = $result;
        }

        return $results;
    }

    /**
     * Queue a single delivery attempt.
     */
    public function queueDelivery(int $endpointId, string $eventType, array $payload): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO webhook_deliveries (endpoint_id, event_type, payload, status, attempt_count, scheduled_at, created_at)
            VALUES (?, ?, ?, 'pending', 0, NOW(), NOW())
        ");
        $stmt->execute([$endpointId, $eventType, json_encode($payload)]);
        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Attempt to deliver a queued webhook.
     */
    public function attemptDelivery(int $deliveryId): array
    {
        $stmt = $this->pdo->prepare("SELECT d.*, e.url, e.secret, e.tenant_id FROM webhook_deliveries d JOIN webhook_endpoints e ON d.endpoint_id = e.id WHERE d.id = ?");
        $stmt->execute([$deliveryId]);
        $delivery = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$delivery) {
            throw new Exception("Delivery not found: {$deliveryId}");
        }

        $payload = json_decode($delivery['payload'], true);
        $signature = $this->signPayload($delivery['payload'], $delivery['secret']);

        $ch = curl_init($delivery['url']);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $delivery['payload'],
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'X-Webhook-Signature: ' . $signature,
                'X-Webhook-Event: ' . $delivery['event_type'],
                'X-Webhook-ID: ' . $delivery['id'],
                'X-Webhook-Attempt: ' . ($delivery['attempt_count'] + 1),
                'User-Agent: JDH-POS-Webhook/1.0',
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        $totalTime = curl_getinfo($ch, CURLINFO_TOTAL_TIME);
        curl_close($ch);

        $success = ($httpCode >= 200 && $httpCode < 300) && empty($error);

        if ($success) {
            $this->pdo->prepare("
                UPDATE webhook_deliveries
                SET status = 'delivered', http_status = ?, response_body = ?, delivered_at = NOW(), attempt_count = attempt_count + 1, last_attempt_at = NOW(), error = NULL
                WHERE id = ?
            ")->execute([$httpCode, $response, $deliveryId]);

            return ['delivered' => true, 'http_code' => $httpCode, 'time_ms' => round($totalTime * 1000)];
        }

        // Schedule retry
        $attempts = (int) $delivery['attempt_count'] + 1;
        $delay = $this->retrySchedule[$attempts] ?? 86400;
        $scheduledAt = date('Y-m-d H:i:s', time() + $delay);
        $status = ($attempts >= count($this->retrySchedule)) ? 'failed' : 'pending';

        $this->pdo->prepare("
            UPDATE webhook_deliveries
            SET status = ?, http_status = ?, response_body = ?, attempt_count = ?, scheduled_at = ?, last_attempt_at = NOW(), error = ?
            WHERE id = ?
        ")->execute([$status, $httpCode, $response, $attempts, $scheduledAt, $error ?: "HTTP {$httpCode}", $deliveryId]);

        // If still pending, queue a retry job
        if ($status === 'pending') {
            $queue = new \JDH\POS\Jobs\JobQueue($this->pdo);
            $queue->push('webhook_deliver', ['delivery_id' => $deliveryId], $delay, (int) $delivery['tenant_id']);
        }

        return ['delivered' => false, 'http_code' => $httpCode, 'error' => $error ?: "HTTP {$httpCode}", 'retry_at' => $scheduledAt];
    }

    /**
     * Sign payload with HMAC-SHA256.
     */
    public function signPayload(string $payload, string $secret): string
    {
        return hash_hmac('sha256', $payload, $secret);
    }

    /**
     * Verify a received signature.
     */
    public function verifySignature(string $payload, string $secret, string $signature): bool
    {
        $expected = $this->signPayload($payload, $secret);
        return hash_equals($expected, $signature);
    }

    private function ensureTablesExist(): void
    {
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS webhook_endpoints (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                tenant_id INT UNSIGNED NOT NULL,
                url VARCHAR(500) NOT NULL,
                secret VARCHAR(255) NOT NULL,
                events JSON DEFAULT '[]',
                status ENUM('active','paused','disabled') NOT NULL DEFAULT 'active',
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_tenant (tenant_id),
                INDEX idx_status (status)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS webhook_deliveries (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                endpoint_id INT UNSIGNED NOT NULL,
                event_type VARCHAR(100) NOT NULL,
                payload JSON NOT NULL,
                status ENUM('pending','delivered','failed') NOT NULL DEFAULT 'pending',
                http_status INT UNSIGNED,
                response_body TEXT,
                attempt_count INT UNSIGNED NOT NULL DEFAULT 0,
                scheduled_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                delivered_at DATETIME,
                last_attempt_at DATETIME,
                error TEXT,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_status_scheduled (status, scheduled_at),
                INDEX idx_endpoint (endpoint_id),
                INDEX idx_event (event_type)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
    }
}
