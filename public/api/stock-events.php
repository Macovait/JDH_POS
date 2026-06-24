<?php
/**
 * Stock Events SSE Endpoint
 * Streams real-time stock changes to connected POS clients.
 */

require_once __DIR__ . '/../../src/paths.php';
safe_require('auth.php', 'src', true);
safe_require('db.php', 'src', true);
safe_require('functions.php', 'src', true);

// Capture session tenant BEFORE releasing lock so URL params cannot be spoofed
$session_tenant_id = (int) (get_current_tenant_id() ?? ($_SESSION['tenant_id'] ?? 0));
$requested_tenant_id = (int) ($_GET['tenant_id'] ?? 0);
$requested_branch_id = (int) ($_GET['branch_id'] ?? 1);

if (!$session_tenant_id) {
    header('Content-Type: text/event-stream');
    echo "data: " . json_encode(['type' => 'error', 'message' => 'Unauthorized']) . "\n\n";
    exit;
}

if ($requested_tenant_id && $requested_tenant_id !== $session_tenant_id) {
    header('Content-Type: text/event-stream');
    echo "data: " . json_encode(['type' => 'error', 'message' => 'Tenant mismatch']) . "\n\n";
    exit;
}

$tenant_id = $requested_tenant_id ?: $session_tenant_id;
$branch_id = $requested_branch_id;

// Release session lock immediately — SSE holds connections open for minutes.
// Without this, all other PHP requests from the same user will block.
if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}

// SSE can run for minutes; disable PHP execution time limit
set_time_limit(0);
ignore_user_abort(true);

header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('Connection: keep-alive');
header('X-Accel-Buffering: no');

if (function_exists('apache_setenv')) {
    apache_setenv('no-gzip', '1');
}

$pdo = get_db_connection();
$last_id    = (int) ($_SERVER['HTTP_LAST_EVENT_ID'] ?? $_GET['last_id'] ?? 0);

// Helper to send SSE event
function sendEvent($id, $data) {
    echo "id: {$id}\n";
    echo "data: " . json_encode($data) . "\n\n";
    if (ob_get_level()) ob_flush();
    flush();
}

// Send initial connection event
sendEvent(0, ['type' => 'connected', 'tenant_id' => $tenant_id, 'branch_id' => $branch_id]);

$max_loops = 90; // ~3 minutes at 2s sleep
for ($i = 0; $i < $max_loops; $i++) {
    $stmt = $pdo->prepare("SELECT id, product_id, old_qty, new_qty, event_type, created_at
        FROM stock_events
        WHERE tenant_id = ? AND branch_id = ? AND id > ?
        ORDER BY id ASC LIMIT 50");
    $stmt->execute([$tenant_id, $branch_id, $last_id]);
    $events = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($events as $ev) {
        sendEvent((int)$ev['id'], [
            'type'      => 'stock_change',
            'product_id'=> (int)$ev['product_id'],
            'old_qty'   => (int)$ev['old_qty'],
            'new_qty'   => (int)$ev['new_qty'],
            'event_type'=> $ev['event_type'],
            'timestamp' => $ev['created_at']
        ]);
        $last_id = (int)$ev['id'];
    }

    sleep(2);
}

// Client should reconnect automatically when connection closes
