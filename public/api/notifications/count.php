<?php
/**
 * Notification Count API
 * Returns unread notification count for the authenticated user
 * Consumed by header.js polling
 */

require_once __DIR__ . '/../../../src/paths.php';
safe_require('auth.php', 'src', true);

// Branch filter for multi-tenant isolation
$current_branch_id = get_current_branch_id();
safe_require('db.php', 'src', true);
require_login();

header('Content-Type: application/json');

$user_id = (int) ($_SESSION['user_id'] ?? 0);
$tenant_id = get_current_tenant_id();

try {
    $pdo = get_db_connection();

    $sql = "SELECT COUNT(*) FROM notifications WHERE user_id = ? AND read_at IS NULL";
    $params = [$user_id];

    if ($tenant_id) {
        $sql .= " AND tenant_id = ?";
        $params[] = $tenant_id;
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $count = (int) $stmt->fetchColumn();

    echo json_encode([
        'success'    => true,
        'count'      => $count,
        'badge_text' => $count > 99 ? '99+' : (string) $count,
        'has_unread' => $count > 0
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error'   => 'Database error'
    ]);
}
