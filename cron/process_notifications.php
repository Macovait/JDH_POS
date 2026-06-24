<?php
/**
 * Notification Job Processor
 * Runs via cron every minute:  * * * * * php /path/to/cron/process_notifications.php
 *
 * Reads pending rows from notification_jobs and dispatches:
 *  - WhatsApp via Africa's Talking or Twilio
 *  - SMS via Africa's Talking
 *
 * Env vars needed:
 *  AT_API_KEY, AT_USERNAME          — Africa's Talking
 *  TWILIO_SID, TWILIO_TOKEN, TWILIO_WHATSAPP_FROM  — Twilio WhatsApp
 *  WHATSAPP_DRIVER = africastalking|twilio|log
 *  SMS_DRIVER      = africastalking|log
 */

require_once dirname(__DIR__) . '/src/paths.php';
load_core_files();

$pdo = get_db_connection();

// Fetch up to 20 pending jobs
$stmt = $pdo->prepare(
    "SELECT * FROM notification_jobs
     WHERE status = 'pending' AND attempts < 3
     ORDER BY created_at ASC
     LIMIT 20"
);
$stmt->execute();
$jobs = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($jobs)) {
    echo "[" . date('Y-m-d H:i:s') . "] No pending jobs.\n";
    exit;
}

foreach ($jobs as $job) {
    $id      = (int) $job['id'];
    $type    = $job['type'];
    $payload = json_decode($job['payload'], true) ?? [];

    // Mark as processing
    $pdo->prepare("UPDATE notification_jobs SET status='processing', attempts=attempts+1 WHERE id=?")->execute([$id]);

    try {
        $sent = processJob($pdo, $type, $payload);
        $pdo->prepare(
            "UPDATE notification_jobs SET status=?, processed_at=NOW() WHERE id=?"
        )->execute([$sent ? 'sent' : 'failed', $id]);
        echo "[" . date('Y-m-d H:i:s') . "] Job #{$id} ({$type}): " . ($sent ? 'SENT' : 'FAILED') . "\n";
    } catch (Throwable $e) {
        $pdo->prepare(
            "UPDATE notification_jobs SET status='failed', error=?, processed_at=NOW() WHERE id=?"
        )->execute([$e->getMessage(), $id]);
        echo "[" . date('Y-m-d H:i:s') . "] Job #{$id} ERROR: " . $e->getMessage() . "\n";
    }
}

// ─── Dispatch functions ───────────────────────────────────────────────────────

function processJob(PDO $pdo, string $type, array $payload): bool
{
    return match ($type) {
        'order_placed'   => sendOrderPlaced($pdo, $payload),
        'order_confirmed'=> sendOrderConfirmed($pdo, $payload),
        'order_shipped'  => sendOrderShipped($pdo, $payload),
        'order_delivered'=> sendOrderDelivered($pdo, $payload),
        default          => false,
    };
}

function sendOrderPlaced(PDO $pdo, array $p): bool
{
    $msg = "✅ Hi {$p['customer']}! Your order #{$p['order_number']} of KES " .
           number_format($p['total'], 2) . " has been received. " .
           "We'll confirm shortly. Track: " . getenv('APP_URL') . "/store/track.php?order={$p['order_id']}";

    // Send to customer
    $customerSent = sendSms($p['phone'], $msg);

    // Notify merchant via WhatsApp if configured
    $tenantPhone = getTenantWhatsApp($pdo, $p['order_id'] ?? 0);
    if ($tenantPhone) {
        $merchantMsg = "🛒 New Order #{$p['order_number']} from {$p['customer']}. Total: KES " .
                       number_format($p['total'], 2);
        sendWhatsApp($tenantPhone, $merchantMsg);
    }

    return $customerSent;
}

function sendOrderConfirmed(PDO $pdo, array $p): bool
{
    $msg = "🎉 Hi {$p['customer']}! Your order #{$p['order_number']} is confirmed and being prepared.";
    return sendSms($p['phone'], $msg);
}

function sendOrderShipped(PDO $pdo, array $p): bool
{
    $msg = "🚚 Hi {$p['customer']}! Your order #{$p['order_number']} is out for delivery. " .
           "Expected: {$p['eta']}. Track: " . getenv('APP_URL') . "/store/track.php?order={$p['uuid']}";
    return sendWhatsApp($p['phone'], $msg) ?: sendSms($p['phone'], $msg);
}

function sendOrderDelivered(PDO $pdo, array $p): bool
{
    $msg = "📦 Hi {$p['customer']}! Your order #{$p['order_number']} has been delivered. " .
           "Thank you for shopping with us! 🙏";
    return sendSms($p['phone'], $msg);
}

function getTenantWhatsApp(PDO $pdo, int $orderId): ?string
{
    if (!$orderId) return null;
    $row = $pdo->prepare(
        "SELECT ss.setting_value FROM online_orders o
         JOIN storefront_settings ss ON ss.tenant_id = o.tenant_id AND ss.setting_key = 'whatsapp_number'
         WHERE o.id = ? LIMIT 1"
    );
    $row->execute([$orderId]);
    $val = $row->fetchColumn();
    return $val ?: null;
}

// ─── Transport layer ──────────────────────────────────────────────────────────

function sendSms(string $phone, string $message): bool
{
    $driver = getenv('SMS_DRIVER') ?: 'log';

    if ($driver === 'africastalking') {
        return sendViaTalking($phone, $message, 'sms');
    }

    // Log driver (dev/test)
    error_log("[SMS] To: {$phone} | {$message}");
    return true;
}

function sendWhatsApp(string $phone, string $message): bool
{
    $driver = getenv('WHATSAPP_DRIVER') ?: 'log';

    if ($driver === 'africastalking') {
        return sendViaTalking($phone, $message, 'whatsapp');
    }

    if ($driver === 'twilio') {
        return sendViaTwilio($phone, $message);
    }

    // Log driver
    error_log("[WhatsApp] To: {$phone} | {$message}");
    return true;
}

function sendViaTalking(string $phone, string $message, string $channel): bool
{
    $apiKey   = getenv('AT_API_KEY');
    $username = getenv('AT_USERNAME') ?: 'sandbox';

    if (!$apiKey) {
        error_log("Africa's Talking API key not configured");
        return false;
    }

    $phone = normalisePhone($phone);
    $endpoint = $channel === 'whatsapp'
        ? 'https://voice.africastalking.com/whatsapp/message'
        : 'https://api.africastalking.com/version1/messaging';

    $data = $channel === 'whatsapp'
        ? ['username' => $username, 'to' => $phone, 'message' => $message]
        : ['username' => $username, 'to' => $phone, 'message' => $message];

    $ch = curl_init($endpoint);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query($data),
        CURLOPT_HTTPHEADER     => [
            'apiKey: ' . $apiKey,
            'Accept: application/json',
            'Content-Type: application/x-www-form-urlencoded',
        ],
        CURLOPT_TIMEOUT        => 10,
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    error_log("[AT {$channel}] HTTP {$httpCode} | " . $response);
    return $httpCode >= 200 && $httpCode < 300;
}

function sendViaTwilio(string $phone, string $message): bool
{
    $sid   = getenv('TWILIO_SID');
    $token = getenv('TWILIO_TOKEN');
    $from  = getenv('TWILIO_WHATSAPP_FROM') ?: 'whatsapp:+14155238886';

    if (!$sid || !$token) {
        error_log('Twilio credentials not configured');
        return false;
    }

    $phone = 'whatsapp:' . normalisePhone($phone);
    $url   = "https://api.twilio.com/2010-04-01/Accounts/{$sid}/Messages.json";

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_USERPWD        => "{$sid}:{$token}",
        CURLOPT_POSTFIELDS     => http_build_query(['From' => $from, 'To' => $phone, 'Body' => $message]),
        CURLOPT_TIMEOUT        => 10,
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    error_log("[Twilio WhatsApp] HTTP {$httpCode} | " . $response);
    return $httpCode >= 200 && $httpCode < 300;
}

function normalisePhone(string $phone): string
{
    $phone = preg_replace('/\D/', '', $phone);
    // Kenya: 07xxxxxxxx → +2547xxxxxxxx
    if (strlen($phone) === 10 && str_starts_with($phone, '0')) {
        $phone = '254' . substr($phone, 1);
    }
    if (!str_starts_with($phone, '+')) {
        $phone = '+' . $phone;
    }
    return $phone;
}
