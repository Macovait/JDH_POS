<?php
/**
 * Unified Cron Job Runner
 * Executes all scheduled background tasks with locking and health tracking.
 *
 * Setup:
 *   crontab -e
 *   * * * * * php /var/www/JDH_POS/scripts/cron-runner.php >> /var/log/jdh-cron.log 2>&1
 *
 * Windows Task Scheduler:
 *   php c:\xampp\htdocs\JDH_POS\scripts\cron-runner.php
 */

require_once __DIR__ . '/../src/bootstrap.php';

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    die('CLI only');
}

$startTime = microtime(true);
$lockFile = __DIR__ . '/../storage/locks/cron.lock';
$lockDir = dirname($lockFile);
if (!is_dir($lockDir)) mkdir($lockDir, 0755, true);

// ---- LOCKING ----
$fp = fopen($lockFile, 'c');
if (!flock($fp, LOCK_EX | LOCK_NB)) {
    echo "[Cron] Another instance is running. Exiting.\n";
    exit(0);
}

$pdo = get_db_connection();
$queue = new \JDH\POS\Jobs\JobQueue($pdo);
$results = [];

// ---- JOB DEFINITIONS ----
$jobs = [
    'every_minute' => [
        'interval' => 60,
        'tasks' => [
            'process_webhook_deliveries' => function ($pdo, $queue) {
                $stmt = $pdo->query("
                    SELECT id FROM webhook_deliveries
                    WHERE status = 'pending' AND scheduled_at <= NOW()
                    ORDER BY created_at ASC
                    LIMIT 20
                ");
                $ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
                foreach ($ids as $id) {
                    $queue->push('webhook_deliver', ['delivery_id' => (int) $id], 0);
                }
                return ['queued' => count($ids)];
            },
            'queue_stripe_retries' => function ($pdo, $queue) {
                $stmt = $pdo->query("
                    SELECT stripe_event_id FROM processed_stripe_events
                    WHERE event_type LIKE 'invoice.payment_failed' AND processed_at < DATE_SUB(NOW(), INTERVAL 1 DAY)
                    AND result NOT LIKE '%dunning_triggered%'
                    LIMIT 5
                ");
                $ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
                foreach ($ids as $eventId) {
                    $queue->push('stripe_webhook_retry', ['stripe_event_id' => $eventId]);
                }
                return ['retries_queued' => count($ids)];
            },
        ],
    ],

    'every_5_minutes' => [
        'interval' => 300,
        'tasks' => [
            'sync_online_inventory' => function ($pdo, $queue) {
                $stmt = $pdo->query("SELECT DISTINCT tenant_id FROM products WHERE updated_at > DATE_SUB(NOW(), INTERVAL 5 MINUTE)");
                $tenantIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
                foreach ($tenantIds as $tenantId) {
                    $queue->push('inventory_sync', ['tenant_id' => (int) $tenantId], 0, (int) $tenantId);
                }
                return ['tenants_synced' => count($tenantIds)];
            },
        ],
    ],

    'hourly' => [
        'interval' => 3600,
        'tasks' => [
            'aggregate_usage' => function ($pdo, $queue) {
                $yesterday = date('Y-m-d', strtotime('-1 day'));
                $queue->push('usage_aggregate', ['date' => $yesterday]);
                return ['date' => $yesterday];
            },
            'cleanup_jobs' => function ($pdo, $queue) {
                $queue->cleanup(7);
                return ['cleanup' => 'job_queue older than 7 days'];
            },
            'cleanup_sessions' => function ($pdo, $queue) {
                $cutoff = date('Y-m-d H:i:s', time() - 86400 * 2);
                $stmt = $pdo->prepare("DELETE FROM sessions WHERE last_activity < ?");
                $stmt->execute([$cutoff]);
                return ['deleted_sessions' => $stmt->rowCount()];
            },
        ],
    ],

    'daily' => [
        'interval' => 86400,
        'tasks' => [
            'generate_invoices' => function ($pdo, $queue) {
                $stmt = $pdo->query("
                    SELECT s.id, s.tenant_id
                    FROM pos_subscriptions s
                    JOIN pos_tenants t ON s.tenant_id = t.id
                    WHERE s.status = 'active'
                    AND (s.next_billing_date <= CURDATE() OR s.next_billing_date IS NULL)
                ");
                $subs = $stmt->fetchAll(PDO::FETCH_ASSOC);
                foreach ($subs as $sub) {
                    $queue->push('generate_invoice', ['subscription_id' => $sub['id'], 'tenant_id' => $sub['tenant_id']], 0, (int) $sub['tenant_id']);
                }
                return ['invoices_queued' => count($subs)];
            },
            'dunning_campaign' => function ($pdo, $queue) {
                $stmt = $pdo->query("
                    SELECT tenant_id, DATEDIFF(NOW(), s.updated_at) as days_overdue
                    FROM pos_subscriptions s
                    WHERE s.status = 'past_due'
                ");
                $subs = $stmt->fetchAll(PDO::FETCH_ASSOC);
                foreach ($subs as $sub) {
                    $day = (int) $sub['days_overdue'];
                    if (in_array($day, [1, 3, 7, 14])) {
                        $queue->push('dunning_email', ['tenant_id' => $sub['tenant_id'], 'day' => $day], 0, (int) $sub['tenant_id']);
                    }
                }
                return ['dunning_queued' => count($subs)];
            },
            'backup_database' => function ($pdo, $queue) {
                $backupFile = __DIR__ . '/../backups/auto_' . date('Ymd_His') . '.sql.gz';
                $cmd = sprintf(
                    'mysqldump -h%s -u%s -p%s %s --single-transaction --quick 2>/dev/null | gzip > %s',
                    escapeshellarg(getenv('DB_HOST') ?: 'localhost'),
                    escapeshellarg(getenv('DB_USER') ?: 'root'),
                    escapeshellarg(getenv('DB_PASS') ?: ''),
                    escapeshellarg(getenv('DB_NAME') ?: 'jdh_pos'),
                    escapeshellarg($backupFile)
                );
                exec($cmd, $output, $returnCode);
                return ['backup' => $returnCode === 0 ? $backupFile : 'failed', 'code' => $returnCode];
            },
            'update_trial_status' => function ($pdo, $queue) {
                $stmt = $pdo->query("
                    UPDATE pos_subscriptions
                    SET status = 'expired'
                    WHERE status = 'trialing' AND trial_ends_at < CURDATE()
                ");
                return ['trials_expired' => $stmt->rowCount()];
            },
        ],
    ],
];

// ---- EXECUTION ----
echo "[Cron] Started " . date('Y-m-d H:i:s') . "\n";

foreach ($jobs as $group => $config) {
    $lastRunFile = $lockDir . '/cron_' . $group . '.last';
    $lastRun = file_exists($lastRunFile) ? (int) file_get_contents($lastRunFile) : 0;

    if ((time() - $lastRun) < $config['interval']) {
        continue;
    }

    echo "[Cron] Running {$group} tasks...\n";
    file_put_contents($lastRunFile, (string) time(), LOCK_EX);

    foreach ($config['tasks'] as $name => $task) {
        try {
            $result = $task($pdo, $queue);
            $results[$group][$name] = $result;
            echo "  ✓ {$name}: " . json_encode($result) . "\n";
        } catch (Exception $e) {
            $results[$group][$name] = ['error' => $e->getMessage()];
            error_log("[Cron] {$name} failed: " . $e->getMessage());
            echo "  ✗ {$name}: " . $e->getMessage() . "\n";
        }
    }
}

// ---- HEALTH LOG ----
$duration = round(microtime(true) - $startTime, 3);
$health = [
    'timestamp' => date('c'),
    'duration_seconds' => $duration,
    'results' => $results,
];
$healthFile = __DIR__ . '/../storage/logs/cron-health.json';
file_put_contents($healthFile, json_encode($health, JSON_PRETTY_PRINT) . "\n", FILE_APPEND | LOCK_EX);

flock($fp, LOCK_UN);
fclose($fp);

echo "[Cron] Finished in {$duration}s\n";
exit(0);
