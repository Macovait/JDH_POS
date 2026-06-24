<?php
/**
 * Cron: Send email warnings for trials expiring soon
 *
 * Run daily via cron:
 *   php /path/to/cron/trial-warning.php
 * Or via HTTP with secret key:
 *   https://yoursite.com/cron/trial-warning.php?key=YOUR_SECRET
 */

$isCli = php_sapi_name() === 'cli';

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

try {
    $pdo = get_db_connection();
    $warnings = 0;

    // Find subscriptions with trial ending in exactly 3 days (and not already warned)
    $stmt = $pdo->query("
        SELECT s.id, s.tenant_id, s.trial_ends_at, s.status,
               t.name as tenant_name, t.email as tenant_email, t.slug as tenant_slug
        FROM pos_subscriptions s
        JOIN pos_tenants t ON s.tenant_id = t.id
        WHERE s.status = 'trialing'
          AND s.trial_ends_at BETWEEN DATE_ADD(NOW(), INTERVAL 2 DAY 23 HOUR)
                                  AND DATE_ADD(NOW(), INTERVAL 3 DAY 1 HOUR)
          AND (s.trial_warned_at IS NULL OR s.trial_warned_at < DATE_SUB(NOW(), INTERVAL 1 DAY))
    ");

    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $sub) {
        $tenantName = $sub['tenant_name'] ?? 'User';
        $tenantEmail = $sub['tenant_email'] ?? '';
        $trialEnds = $sub['trial_ends_at'] ? date('M d, Y', strtotime($sub['trial_ends_at'])) : 'soon';

        if ($tenantEmail && function_exists('send_email')) {
            $subject = "Your JDH POS trial expires in 3 days — " . $tenantName;
            $body = <<<HTML
<p>Hi {$tenantName},</p>
<p>Your free trial for <strong>JDH POS</strong> expires on <strong>{$trialEnds}</strong>.</p>
<p>To keep your account active and avoid any service interruption, please upgrade to a paid plan before your trial ends.</p>
<p><a href="https://www.kilimax.com/auth/login.php" style="background:#f59e0b;color:#fff;padding:10px 20px;text-decoration:none;border-radius:6px;">Upgrade Now</a></p>
<p>If you have any questions, reply to this email.</p>
<p>— The JDH POS Team</p>
HTML;
            send_email($tenantEmail, $subject, $body);
        }

        // Mark as warned
        $pdo->prepare("UPDATE pos_subscriptions SET trial_warned_at = NOW() WHERE id = ?")
            ->execute([$sub['id']]);

        $warnings++;
    }

    $result = [
        'success' => true,
        'warnings_sent' => $warnings,
        'message' => $warnings > 0 ? "{$warnings} trial warning(s) sent." : "No upcoming trial expirations found.",
        'timestamp' => date('Y-m-d H:i:s'),
    ];

    if ($isCli) {
        echo $result['message'] . PHP_EOL;
    } else {
        echo json_encode($result);
    }
} catch (Exception $e) {
    $msg = 'Trial warning cron failed: ' . $e->getMessage();
    error_log($msg);
    if ($isCli) {
        echo $msg . PHP_EOL;
        exit(1);
    } else {
        http_response_code(500);
        echo json_encode(['error' => $msg]);
    }
}
