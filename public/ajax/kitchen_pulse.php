<?php
/**
 * Kitchen Pulse — lightweight delta-poll endpoint.
 * Returns counts and the IDs of pending KOTs newer than ?since (ISO timestamp or seconds-since-epoch).
 * Used by the KDS to play an audio alert when new orders arrive.
 *
 * GET params:
 *   since  - optional. ISO datetime ("2026-05-10 16:30:00") or unix seconds. Defaults to 60s ago.
 */

header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');

require_once __DIR__ . '/../../src/paths.php';
load_core_files();

if (session_status() === PHP_SESSION_NONE) session_start();

if (empty($_SESSION['user_id']) || empty($_SESSION['tenant_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

// Release session lock — high-frequency polling endpoint must not hold locks
if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}

try {
    $pdo = get_db_connection();
    $tenant_id = get_current_tenant_id();
    $branch_id = get_current_branch_id();

    $since_raw = $_GET['since'] ?? '';
    if ($since_raw === '') {
        $since_dt = date('Y-m-d H:i:s', time() - 60);
    } elseif (ctype_digit((string)$since_raw)) {
        $since_dt = date('Y-m-d H:i:s', (int)$since_raw);
    } else {
        $ts = strtotime($since_raw);
        $since_dt = $ts ? date('Y-m-d H:i:s', $ts) : date('Y-m-d H:i:s', time() - 60);
    }

    $has_tenant = false;
    try { $pdo->query("SELECT tenant_id FROM kitchen_orders LIMIT 1"); $has_tenant = true; } catch (Exception $e) {}

    // Counts (today, by status)
    $counts = ['pending' => 0, 'preparing' => 0, 'ready' => 0];
    $sql = "SELECT status, COUNT(*) c FROM kitchen_orders WHERE branch_id = ?";
    $params = [$branch_id];
    if ($has_tenant) { $sql .= " AND (tenant_id = ? OR tenant_id IS NULL)"; $params[] = $tenant_id; }
    $sql .= " AND status IN ('pending','preparing','ready') GROUP BY status";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $counts[$r['status']] = (int)$r['c'];
    }

    // New orders since cutoff
    $sql = "SELECT id, sale_id, table_number, status, created_at
            FROM kitchen_orders
            WHERE branch_id = ? AND created_at > ?";
    $params = [$branch_id, $since_dt];
    if ($has_tenant) { $sql .= " AND (tenant_id = ? OR tenant_id IS NULL)"; $params[] = $tenant_id; }
    $sql .= " ORDER BY created_at DESC LIMIT 20";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $new_orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success'    => true,
        'counts'     => $counts,
        'new_orders' => $new_orders,
        'new_count'  => count($new_orders),
        'server_time'=> date('Y-m-d H:i:s')
    ]);
} catch (Exception $e) {
    error_log("kitchen_pulse: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
