<?php
declare(strict_types=1);

/**
 * Server-Sent Events (SSE) Endpoint for Real-Time Multi-Register Sync
 * Streams events to connected POS clients for live cart/order updates.
 */

require_once __DIR__ . '/../../src/paths.php';
safe_require('db.php', 'src', true);

header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('Connection: keep-alive');
header('X-Accel-Buffering: no');

// Validate session with secure params
session_name('jakababa_saas_sid');
$cookieParams = [
    'lifetime' => 0,
    'path' => '/JDH_POS/',
    'httponly' => true,
    'samesite' => 'Lax'
];
if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
    $cookieParams['secure'] = true;
}
session_set_cookie_params($cookieParams);
session_start();

$tenantId = $_SESSION['tenant_id'] ?? null;
$branchId = $_SESSION['branch_id'] ?? null;
$userId = $_SESSION['user_id'] ?? null;

if (!$tenantId || !$branchId || !$userId) {
    echo "event: error\ndata: " . json_encode(['message' => 'Unauthorized']) . "\n\n";
    exit;
}

// Disable output buffering
while (ob_get_level() > 0) {
    ob_end_flush();
}
flush();

$pdo = get_db_connection();
$lastEventId = isset($_SERVER['HTTP_LAST_EVENT_ID']) ? (int) $_SERVER['HTTP_LAST_EVENT_ID'] : 0;
$heartbeatInterval = 15; // seconds
$lastHeartbeat = time();

while (true) {
    // Check for new events since last poll
    $stmt = $pdo->prepare("
        SELECT id, event_type, event_data, created_at
        FROM sync_events
        WHERE tenant_id = ? AND branch_id = ? AND id > ?
        ORDER BY id ASC
        LIMIT 50
    ");
    $stmt->execute([$tenantId, $branchId, $lastEventId]);
    $events = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($events as $event) {
        $payload = json_encode([
            'id' => (int) $event['id'],
            'type' => $event['event_type'],
            'data' => json_decode($event['event_data'], true),
            'timestamp' => $event['created_at']
        ]);

        echo "id: {$event['id']}\n";
        echo "event: {$event['event_type']}\n";
        echo "data: {$payload}\n\n";
        $lastEventId = (int) $event['id'];
    }

    // Send heartbeat to keep connection alive
    if (time() - $lastHeartbeat >= $heartbeatInterval) {
        echo ":heartbeat\n\n";
        $lastHeartbeat = time();
    }

    flush();

    // Sleep to prevent CPU spinning
    usleep(500000); // 500ms
}
