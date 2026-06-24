<?php
/**
 * Cron: Expire expired trials and suspend tenants
 * 
 * Run daily via cron:
 *   php /path/to/cron/expire-trials.php
 * Or via HTTP with secret key:
 *   https://yoursite.com/cron/expire-trials.php?key=YOUR_SECRET
 */

// Determine if running from CLI or HTTP
$isCli = php_sapi_name() === 'cli';

// CLI: allow direct execution
// HTTP: require secret key
if (!$isCli) {
    $configFile = dirname(__DIR__) . '/config/app.php';
    $secret = 'changeme';
    if (file_exists($configFile)) {
        $cfg = require $configFile;
        $secret = $cfg['cron_secret'] ?? ($_ENV['CRON_SECRET'] ?? 'changeme');
    }
    if (empty($_GET['key']) || $_GET['key'] !== $secret) {
        http_response_code(403);
        echo json_encode(['error' => 'Forbidden']);
        exit;
    }
    header('Content-Type: application/json');
}

require_once dirname(__DIR__) . '/src/paths.php';
require_once dirname(__DIR__) . '/src/db.php';
require_once dirname(__DIR__) . '/src/SubscriptionManager.php';

try {
    $pdo = get_db_connection();
    $mgr = new SubscriptionManager($pdo);
    $expired = $mgr->expireAllExpiredTrials();

    $result = [
        'success' => true,
        'expired_count' => $expired,
        'message' => $expired > 0 ? "{$expired} trial(s) expired and suspended." : "No expired trials found.",
        'timestamp' => date('Y-m-d H:i:s'),
    ];

    if ($isCli) {
        echo $result['message'] . PHP_EOL;
    } else {
        echo json_encode($result);
    }
} catch (Exception $e) {
    $msg = 'Trial expiration cron failed: ' . $e->getMessage();
    error_log($msg);
    if ($isCli) {
        echo $msg . PHP_EOL;
        exit(1);
    } else {
        http_response_code(500);
        echo json_encode(['error' => $msg]);
    }
}
