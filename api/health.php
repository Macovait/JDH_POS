<?php
/**
 * Health Check Endpoint
 * Returns system status for load balancers and monitoring.
 *
 * Usage: GET /api/health.php
 *        GET /api/health.php?full=1  (detailed diagnostics)
 */

require_once __DIR__ . '/../src/bootstrap.php';

header('Content-Type: application/json');
$full = isset($_GET['full']);

$checks = [];
$healthy = true;

// Database
$checks['database'] = ['status' => 'unknown'];
try {
    $pdo = get_db_connection();
    $pdo->query('SELECT 1');
    $checks['database'] = ['status' => 'ok', 'latency_ms' => null];
} catch (Exception $e) {
    $checks['database'] = ['status' => 'error', 'message' => $e->getMessage()];
    $healthy = false;
}

// Redis
$checks['redis'] = ['status' => 'unknown'];
try {
    if (extension_loaded('redis')) {
        $redis = new Redis();
        if ($redis->connect(getenv('REDIS_HOST') ?: '127.0.0.1', (int)(getenv('REDIS_PORT') ?: 6379))) {
            $redis->ping();
            $checks['redis'] = ['status' => 'ok'];
            $redis->close();
        } else {
            $checks['redis'] = ['status' => 'warning', 'message' => 'Connection failed'];
        }
    } else {
        $checks['redis'] = ['status' => 'disabled'];
    }
} catch (Exception $e) {
    $checks['redis'] = ['status' => 'error', 'message' => $e->getMessage()];
    $healthy = false;
}

// Queue worker (check if jobs are piling up)
$checks['queue'] = ['status' => 'unknown'];
try {
    $pdo = get_db_connection();
    $pending = (int) $pdo->query("SELECT COUNT(*) FROM job_queue WHERE status = 'pending'")->fetchColumn();
    $failed = (int) $pdo->query("SELECT COUNT(*) FROM job_queue WHERE status = 'failed'")->fetchColumn();
    $checks['queue'] = ['status' => $pending > 1000 ? 'warning' : 'ok', 'pending' => $pending, 'failed' => $failed];
    if ($pending > 5000) $healthy = false;
} catch (Exception $e) {
    $checks['queue'] = ['status' => 'error', 'message' => $e->getMessage()];
}

// Disk space
$checks['disk'] = ['status' => 'ok', 'free_gb' => round(disk_free_space(__DIR__) / 1024 / 1024 / 1024, 2)];
if ($checks['disk']['free_gb'] < 1) {
    $checks['disk']['status'] = 'warning';
    $healthy = false;
}

// Webhook delivery backlog
$checks['webhooks'] = ['status' => 'ok'];
try {
    $pendingWebhooks = (int) $pdo->query("SELECT COUNT(*) FROM webhook_deliveries WHERE status = 'pending' AND scheduled_at <= NOW()")->fetchColumn();
    $checks['webhooks']['pending'] = $pendingWebhooks;
    if ($pendingWebhooks > 500) $checks['webhooks']['status'] = 'warning';
} catch (Exception $e) {
    $checks['webhooks'] = ['status' => 'error'];
}

// eTIMS retry queue
$checks['etims_retry'] = ['status' => 'ok'];
try {
    $pdo = get_db_connection();
    $etimsPending = (int) $pdo->query("SELECT COUNT(*) FROM etims_retry_queue WHERE status = 'pending'")->fetchColumn();
    $etimsFailed = (int) $pdo->query("SELECT COUNT(*) FROM etims_retry_queue WHERE status = 'failed'")->fetchColumn();
    $checks['etims_retry'] = ['status' => $etimsPending > 100 ? 'warning' : 'ok', 'pending' => $etimsPending, 'failed' => $etimsFailed];
} catch (\Exception $e) {
    $checks['etims_retry'] = ['status' => 'n/a'];
}

$response = [
    'status' => $healthy ? 'healthy' : 'degraded',
    'timestamp' => date('c'),
    'version' => '2.0.0',
];

if ($full) {
    $response['checks'] = $checks;
    $response['php'] = ['version' => PHP_VERSION, 'memory_limit' => ini_get('memory_limit'), 'max_execution_time' => ini_get('max_execution_time')];
    $response['mysql'] = $pdo ? $pdo->query("SELECT VERSION() as v, @@max_connections as max_conn, (SELECT COUNT(*) FROM information_schema.processlist WHERE command != 'Sleep') as active_conn")->fetch(PDO::FETCH_ASSOC) : null;
}

http_response_code($healthy ? 200 : 503);
echo json_encode($response, JSON_PRETTY_PRINT);
