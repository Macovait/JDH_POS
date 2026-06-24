<?php
/**
 * Load a saved filter configuration
 */
require_once __DIR__ . '/../../src/paths.php';
safe_require('auth.php', 'src', true);
safe_require('db.php', 'src', true);

require_login();

header('Content-Type: application/json');

try {
    $pdo = get_db_connection();
    $user_id = get_current_user_id();
    $tenant_id = get_current_tenant_id();
    $id = (int)($_GET['id'] ?? 0);

    if ($id <= 0) {
        echo json_encode(['success' => false, 'error' => 'Filter ID required']);
        exit;
    }

    $stmt = $pdo->prepare("SELECT id, filter_name, config FROM saved_filters WHERE id = ? AND user_id = ? AND tenant_id = ? LIMIT 1");
    $stmt->execute([$id, $user_id, $tenant_id]);
    $filter = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$filter) {
        echo json_encode(['success' => false, 'error' => 'Filter not found']);
        exit;
    }

    $config = json_decode($filter['config'], true);
    if (!is_array($config)) {
        $config = [];
    }

    echo json_encode([
        'success' => true,
        'id' => (int)$filter['id'],
        'name' => $filter['filter_name'],
        'config' => $config
    ]);

} catch (Exception $e) {
    error_log("load_filter error: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Failed to load filter']);
}