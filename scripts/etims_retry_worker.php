<?php
/**
 * KRA eTIMS Retry Queue Worker
 *
 * Processes failed eTIMS invoice submissions with exponential backoff.
 * Run via cron every minute: * * * * * php scripts/etims_retry_worker.php
 *
 * @package Jakababa
 */

declare(strict_types=1);

// CLI only
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit('CLI only');
}

require_once __DIR__ . '/../src/db.php';
require_once __DIR__ . '/../src/Services/Integration/KRA/EtimsService.php';

use Jakababa\Services\Integration\KRA\EtimsService;

$limit = (int)($argv[1] ?? 50);

echo "[eTIMS Retry] Starting at " . date('Y-m-d H:i:s') . "\n";

// Find tenants with pending retries
$stmt = $pdo->prepare("
    SELECT DISTINCT tenant_id 
    FROM etims_retry_queue 
    WHERE status = 'pending' AND next_retry_at <= NOW()
    LIMIT 100
");
$stmt->execute();
$tenants = $stmt->fetchAll(PDO::FETCH_COLUMN);

if (empty($tenants)) {
    echo "[eTIMS Retry] No pending items. Done.\n";
    exit(0);
}

$totalProcessed = 0;
$totalSucceeded = 0;
$totalFailed = 0;

foreach ($tenants as $tenantId) {
    $service = new EtimsService($pdo, (int)$tenantId);
    if (!$service->isEnabled()) {
        continue;
    }

    $result = $service->processRetryQueue($limit);
    $totalProcessed += $result['processed'];
    $totalSucceeded += $result['succeeded'];
    $totalFailed += $result['failed'];

    echo "[eTIMS Retry] Tenant {$tenantId}: {$result['processed']} processed, {$result['succeeded']} succeeded, {$result['failed']} failed\n";
}

echo "[eTIMS Retry] Done. Total: {$totalProcessed} processed, {$totalSucceeded} succeeded, {$totalFailed} failed\n";
