<?php
/**
 * Queue Worker - Production-Ready Background Job Processor
 *
 * Run via CLI or Supervisor:
 *   php scripts/worker.php --max-jobs=100 --sleep=2
 *
 * Supervisor config (/etc/supervisor/conf.d/jdh-worker.conf):
 *   [program:jdh-worker]
 *   command=php /var/www/JDH_POS/scripts/worker.php --max-jobs=500 --sleep=1
 *   autostart=true
 *   autorestart=true
 *   user=www-data
 *   numprocs=4
 *   redirect_stderr=true
 *   stdout_logfile=/var/log/jdh-worker.log
 */

require_once __DIR__ . '/../src/bootstrap.php';

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    die('CLI only');
}

// Parse CLI args
$options = getopt('', ['max-jobs:', 'sleep:', 'memory-limit:', 'timeout:', 'tenant:']);
$maxJobs = (int) ($options['max-jobs'] ?? 50);
$sleepSeconds = (int) ($options['sleep'] ?? 2);
$memoryLimitMB = (int) ($options['memory-limit'] ?? 256);
$jobTimeout = (int) ($options['timeout'] ?? 30);
$targetTenant = isset($options['tenant']) ? (int) $options['tenant'] : null;

$startTime = time();
$processed = 0;
$failed = 0;

set_time_limit(0);
ini_set('memory_limit', $memoryLimitMB . 'M');

$pdo = get_db_connection();
$queue = new \JDH\POS\Jobs\JobQueue($pdo);

// Signal handling for graceful shutdown
$shouldStop = false;
if (function_exists('pcntl_signal')) {
    pcntl_signal(SIGTERM, function () use (&$shouldStop) { $shouldStop = true; });
    pcntl_signal(SIGINT, function () use (&$shouldStop) { $shouldStop = true; });
}

echo "[Worker] Started " . date('Y-m-d H:i:s') . " | max_jobs={$maxJobs} sleep={$sleepSeconds}s memory={$memoryLimitMB}MB\n";

// ---- JOB PROCESSOR REGISTRY ----
$processors = [
    'send_email' => function (array $payload, PDO $pdo) {
        $template = $payload['template'] ?? 'default';
        $to = $payload['to'] ?? '';
        $vars = $payload['vars'] ?? [];
        if (!$to) throw new Exception('Missing recipient email');
        // Use existing email service if available
        $subject = $vars['subject'] ?? 'Notification from JDH POS';
        $body = $vars['body'] ?? '';
        $headers = "From: " . (getenv('EMAIL_FROM') ?: 'noreply@jakababa.local') . "\r\n";
        $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
        if (!mail($to, $subject, $body, $headers)) {
            throw new Exception('mail() returned false');
        }
        return ['sent' => true, 'to' => $to];
    },

    'stripe_webhook_retry' => function (array $payload, PDO $pdo) {
        $eventId = $payload['stripe_event_id'] ?? '';
        if (!$eventId) throw new Exception('Missing stripe_event_id');
        $stmt = $pdo->prepare("SELECT * FROM processed_stripe_events WHERE stripe_event_id = ?");
        $stmt->execute([$eventId]);
        $event = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$event) throw new Exception('Stripe event not found: ' . $eventId);
        // Re-process via webhook handler logic
        require_once __DIR__ . '/../src/Webhooks/StripeWebhookProcessor.php';
        $processor = new \JDH\POS\Webhooks\StripeWebhookProcessor($pdo);
        $result = $processor->processEvent(json_decode($event['payload'], true));
        return ['retried' => true, 'event' => $eventId, 'result' => $result];
    },

    'webhook_deliver' => function (array $payload, PDO $pdo) {
        $deliveryId = $payload['delivery_id'] ?? 0;
        if (!$deliveryId) throw new Exception('Missing delivery_id');
        require_once __DIR__ . '/../src/Webhooks/WebhookDelivery.php';
        $delivery = new \JDH\POS\Webhooks\WebhookDelivery($pdo);
        return $delivery->attemptDelivery((int) $deliveryId);
    },

    'generate_invoice' => function (array $payload, PDO $pdo) {
        $invoiceId = $payload['invoice_id'] ?? 0;
        if (!$invoiceId) throw new Exception('Missing invoice_id');
        $stmt = $pdo->prepare("UPDATE pos_invoices SET status='generated', updated_at=NOW() WHERE id=? AND status='pending'");
        $stmt->execute([$invoiceId]);
        return ['generated' => $stmt->rowCount() > 0, 'invoice_id' => $invoiceId];
    },

    'dunning_email' => function (array $payload, PDO $pdo) {
        $tenantId = $payload['tenant_id'] ?? 0;
        $day = $payload['day'] ?? 1;
        if (!$tenantId) throw new Exception('Missing tenant_id');
        $stmt = $pdo->prepare("SELECT email, name FROM pos_tenants WHERE id = ?");
        $stmt->execute([$tenantId]);
        $tenant = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$tenant) throw new Exception('Tenant not found');
        $subject = "Payment Reminder - Day {$day}";
        $body = "<p>Hi {$tenant['name']},</p><p>This is a friendly reminder that your subscription payment is overdue. Day {$day}.</p>";
        $headers = "From: " . (getenv('EMAIL_FROM') ?: 'noreply@jakababa.local') . "\r\nContent-Type: text/html; charset=UTF-8\r\n";
        mail($tenant['email'], $subject, $body, $headers);
        return ['sent' => true, 'tenant_id' => $tenantId, 'day' => $day];
    },

    'gdpr_export' => function (array $payload, PDO $pdo) {
        $tenantId = $payload['tenant_id'] ?? 0;
        $type = $payload['type'] ?? 'tenant'; // tenant or customer
        $recordId = $payload['record_id'] ?? 0;
        if (!$tenantId) throw new Exception('Missing tenant_id');
        require_once __DIR__ . '/../src/GDPR/DataExporter.php';
        $exporter = new \JDH\POS\GDPR\DataExporter($pdo);
        $file = $exporter->export($type, $tenantId, $recordId);
        return ['file' => $file, 'tenant_id' => $tenantId];
    },

    'usage_aggregate' => function (array $payload, PDO $pdo) {
        $tenantId = $payload['tenant_id'] ?? null;
        $date = $payload['date'] ?? date('Y-m-d');
        $stmt = $pdo->prepare("
            INSERT INTO usage_metering_aggregates (tenant_id, metering_type_id, date, total_value, created_at)
            SELECT tenant_id, metering_type_id, DATE(created_at) as dt, SUM(value), NOW()
            FROM usage_metering_events
            WHERE DATE(created_at) = ? " . ($tenantId ? "AND tenant_id = ?" : "") . "
            GROUP BY tenant_id, metering_type_id, DATE(created_at)
            ON DUPLICATE KEY UPDATE total_value = VALUES(total_value), updated_at = NOW()
        ");
        $params = [$date];
        if ($tenantId) $params[] = $tenantId;
        $stmt->execute($params);
        return ['aggregated' => $stmt->rowCount(), 'date' => $date];
    },

    'inventory_sync' => function (array $payload, PDO $pdo) {
        $tenantId = $payload['tenant_id'] ?? 0;
        if (!$tenantId) throw new Exception('Missing tenant_id');
        // Sync online store inventory with POS stock
        $stmt = $pdo->prepare("
            UPDATE online_store_products osp
            JOIN products p ON osp.product_id = p.id AND osp.tenant_id = p.tenant_id
            SET osp.stock_quantity = p.stock_quantity, osp.updated_at = NOW()
            WHERE osp.tenant_id = ?
        ");
        $stmt->execute([$tenantId]);
        return ['synced' => $stmt->rowCount(), 'tenant_id' => $tenantId];
    },
];

// ---- MAIN LOOP ----
while ($processed < $maxJobs && !$shouldStop) {
    if (function_exists('pcntl_signal_dispatch')) {
        pcntl_signal_dispatch();
    }

    $job = $queue->pop();

    if (!$job) {
        if ($processed > 0) {
            echo "[Worker] No more jobs. Processed {$processed} jobs.\n";
        }
        sleep($sleepSeconds);
        continue;
    }

    // Tenant-scoped worker filter
    if ($targetTenant && ($job['tenant_id'] ?? null) !== $targetTenant) {
        $queue->complete($job['id'], ['skipped' => true, 'reason' => 'tenant_filter']);
        continue;
    }

    $tenantLabel = $job['tenant_id'] ?? 'null';
    echo "[Job #{$job['id']}] type={$job['type']} tenant={$tenantLabel}\n";

    try {
        $handler = $processors[$job['type']] ?? null;
        if (!$handler) {
            throw new Exception("Unknown job type: {$job['type']}");
        }

        // Timeout wrapper
        if (function_exists('pcntl_alarm')) {
            pcntl_alarm($jobTimeout);
        }

        $result = $handler($job['payload'], $pdo);
        $queue->complete($job['id'], $result);
        $processed++;

        if (function_exists('pcntl_alarm')) {
            pcntl_alarm(0);
        }

        echo "  ✓ Completed\n";

    } catch (Exception $e) {
        $failed++;
        error_log("[Worker] Job #{$job['id']} failed: " . $e->getMessage());
        echo "  ✗ Failed: " . $e->getMessage() . "\n";

        $shouldRetry = !in_array($job['type'], ['gdpr_export']); // GDPR exports can retry
        $queue->fail($job['id'], $e->getMessage(), $shouldRetry);
    }

    // Memory check
    $usageMB = memory_get_usage(true) / 1024 / 1024;
    if ($usageMB > $memoryLimitMB * 0.9) {
        echo "[Worker] Memory limit approaching ({$usageMB}MB). Exiting gracefully.\n";
        break;
    }
}

$duration = time() - $startTime;
echo "[Worker] Finished | processed={$processed} failed={$failed} duration={$duration}s\n";
exit($failed > 0 ? 1 : 0);
