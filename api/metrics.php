<?php
/**
 * Prometheus-Compatible Metrics Endpoint
 *
 * Exposes system metrics in Prometheus text format for external monitoring
 * (Grafana, Datadog, etc). Protect this endpoint in production via IP allowlist
 * or METRICS_TOKEN env var.
 *
 * Usage: GET /api/metrics.php
 *        GET /api/metrics.php?token=YOUR_METRICS_TOKEN
 */

require_once __DIR__ . '/../src/bootstrap.php';

// Auth: require token if configured
$metricsToken = getenv('METRICS_TOKEN') ?: ($_ENV['METRICS_TOKEN'] ?? '');
if (!empty($metricsToken)) {
    $providedToken = $_GET['token'] ?? $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    $providedToken = str_replace('Bearer ', '', $providedToken);
    if (!hash_equals($metricsToken, $providedToken)) {
        http_response_code(403);
        exit('Forbidden');
    }
}

header('Content-Type: text/plain; version=0.0.4; charset=utf-8');

$lines = [];

function metric(string $name, string $type, string $help, $value, array $labels = []): string
{
    $labelStr = '';
    if (!empty($labels)) {
        $parts = [];
        foreach ($labels as $k => $v) {
            $parts[] = $k . '="' . addslashes((string) $v) . '"';
        }
        $labelStr = '{' . implode(',', $parts) . '}';
    }
    return "# HELP {$name} {$help}\n# TYPE {$name} {$type}\n{$name}{$labelStr} {$value}\n";
}

try {
    $pdo = get_db_connection();

    // Active tenants
    $tenantCount = (int) $pdo->query("SELECT COUNT(*) FROM tenants WHERE status = 'active'")->fetchColumn();
    $lines[] = metric('jdh_tenants_active', 'gauge', 'Number of active tenants', $tenantCount);

    // Sales today (all tenants)
    $salesToday = (int) $pdo->query("SELECT COUNT(*) FROM sales WHERE DATE(created_at) = CURDATE() AND status != 'voided'")->fetchColumn();
    $lines[] = metric('jdh_sales_today', 'gauge', 'Total sales today across all tenants', $salesToday);

    // Revenue today
    $revenueToday = (float) $pdo->query("SELECT COALESCE(SUM(total), 0) FROM sales WHERE DATE(created_at) = CURDATE() AND status != 'voided'")->fetchColumn();
    $lines[] = metric('jdh_revenue_today_kes', 'gauge', 'Total revenue today in KES', round($revenueToday, 2));

    // Job queue
    foreach (['pending', 'processing', 'failed', 'completed'] as $status) {
        $count = (int) $pdo->query("SELECT COUNT(*) FROM job_queue WHERE status = '{$status}'")->fetchColumn();
        $lines[] = metric('jdh_job_queue', 'gauge', 'Job queue count by status', $count, ['status' => $status]);
    }

    // eTIMS retry queue
    foreach (['pending', 'completed', 'failed'] as $status) {
        try {
            $count = (int) $pdo->query("SELECT COUNT(*) FROM etims_retry_queue WHERE status = '{$status}'")->fetchColumn();
            $lines[] = metric('jdh_etims_retry_queue', 'gauge', 'eTIMS retry queue count by status', $count, ['status' => $status]);
        } catch (\Exception $e) {
            // Table may not exist yet
        }
    }

    // Webhook delivery backlog
    $webhookPending = (int) $pdo->query("SELECT COUNT(*) FROM webhook_deliveries WHERE status = 'pending' AND scheduled_at <= NOW()")->fetchColumn();
    $lines[] = metric('jdh_webhook_pending', 'gauge', 'Pending webhook deliveries', $webhookPending);

    // Database connections
    $activeConns = (int) $pdo->query("SELECT COUNT(*) FROM information_schema.processlist WHERE command != 'Sleep'")->fetchColumn();
    $maxConns = (int) $pdo->query("SELECT @@max_connections")->fetchColumn();
    $lines[] = metric('jdh_db_active_connections', 'gauge', 'Active database connections', $activeConns);
    $lines[] = metric('jdh_db_max_connections', 'gauge', 'Max database connections', $maxConns);

    // Products and users (totals)
    $totalProducts = (int) $pdo->query("SELECT COUNT(*) FROM products WHERE deleted_at IS NULL")->fetchColumn();
    $totalUsers = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE deleted_at IS NULL")->fetchColumn();
    $lines[] = metric('jdh_products_total', 'gauge', 'Total products across all tenants', $totalProducts);
    $lines[] = metric('jdh_users_total', 'gauge', 'Total users across all tenants', $totalUsers);

    // Disk free space
    $diskFreeGB = round(disk_free_space(__DIR__) / 1024 / 1024 / 1024, 2);
    $lines[] = metric('jdh_disk_free_gb', 'gauge', 'Free disk space in GB', $diskFreeGB);

    // PHP memory
    $lines[] = metric('jdh_php_memory_usage_bytes', 'gauge', 'Current PHP memory usage', memory_get_usage(true));

} catch (\Exception $e) {
    $lines[] = metric('jdh_health_error', 'gauge', 'Health check error', 1);
    error_log("Metrics endpoint error: " . $e->getMessage());
}

echo implode("\n", $lines);
