<?php
require_once __DIR__ . '/../../src/paths.php';
safe_require('auth.php', 'src', true);
safe_require('db.php', 'src', true);
safe_require('functions.php', 'src', true);
require_login();

$tenant_id = get_current_tenant_id();
if (!$tenant_id) { http_response_code(403); exit('Unauthorized'); }

$pdo = get_db_connection();

$format = $_GET['format'] ?? 'csv';
$stmt = $pdo->prepare("
    SELECT t.*, COUNT(r.product_id) as usage_count
    FROM product_tags t
    LEFT JOIN product_tag_relations r ON r.tag_id = t.id AND r.tenant_id = t.tenant_id
    WHERE t.tenant_id = ?
    GROUP BY t.id
    ORDER BY t.name
");
$stmt->execute([$tenant_id]);
$tags = $stmt->fetchAll(PDO::FETCH_ASSOC);

if ($format === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="product_tags.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['ID','Name','Slug','Color','Icon','Description','Active','Usage Count']);
    foreach ($tags as $t) {
        fputcsv($out, [$t['id'],$t['name'],$t['slug'],$t['color'],$t['icon'],$t['description'],$t['is_active']?'Yes':'No',$t['usage_count']]);
    }
    fclose($out);
    exit;
}

header('Content-Type: application/json');
echo json_encode(['success' => true, 'tags' => $tags]);
