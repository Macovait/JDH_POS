<?php
require_once __DIR__ . '/../../src/paths.php';
safe_require('auth.php', 'src', true);

// Branch filter for multi-tenant isolation
$current_branch_id = get_current_branch_id();
safe_require('db.php', 'src', true);
require_login();

header('Content-Type: application/json');

$pdo = get_db_connection();
$tenant_id = get_current_tenant_id();
$tag_id = (int) ($_GET['id'] ?? 0);

if (!$tenant_id || !$tag_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit;
}

$stmt = $pdo->prepare("SELECT id, name, slug, color, icon, description, is_active, sort_order FROM product_tags WHERE id = ? AND tenant_id = ? AND branch_id = $current_branch_id LIMIT 1");
$stmt->execute([$tag_id, $tenant_id]);
$tag = $stmt->fetch(PDO::FETCH_ASSOC);

if ($tag) {
    echo json_encode(['success' => true, 'tag' => $tag]);
} else {
    echo json_encode(['success' => false, 'message' => 'Tag not found']);
}
