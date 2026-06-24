<?php
/**
 * Search and list returns with filters and pagination
 */

require_once __DIR__ . '/../../src/paths.php';
safe_require('auth.php', 'src', true);
safe_require('db.php', 'src', true);
safe_require('functions.php', 'src', true);

if (!is_super_admin() && !check_permission('sales.returns')) {
    echo json_encode(['success' => false, 'error' => 'Permission denied']);
    exit;
}

header('Content-Type: application/json');

try {
    $pdo = get_db_connection();
    $tenant_id = get_current_tenant_id();
    $requested_branch_id = isset($_GET['branch_id']) ? (int)$_GET['branch_id'] : 0;

    // Validate branch_id belongs to tenant (prevents cross-tenant data leakage)
    $branch_id = resolve_branch_id($requested_branch_id, $tenant_id);
    if ($branch_id <= 0 && $requested_branch_id > 0) {
        echo json_encode(['success' => false, 'error' => 'Access denied to this branch']);
        exit;
    }

    $page = max(1, (int)($_GET['page'] ?? 1));
    $limit = min(50, max(1, (int)($_GET['limit'] ?? 15)));
    $offset = ($page - 1) * $limit;
    $status = trim($_GET['status'] ?? '');
    $search = trim($_GET['search'] ?? '');
    $date_from = $_GET['date_from'] ?? '';
    $date_to = $_GET['date_to'] ?? '';

    $where = ["r.tenant_id = ? AND r.branch_id = ?"];
    $params = [$tenant_id, $branch_id];

    if ($status !== '') {
        $where[] = "r.status = ?";
        $params[] = $status;
    }

    if ($search !== '') {
        $where[] = "(r.return_number LIKE ? OR s.invoice_number LIKE ? OR c.name LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }

    if ($date_from !== '') {
        $where[] = "DATE(r.created_at) >= ?";
        $params[] = $date_from;
    }
    if ($date_to !== '') {
        $where[] = "DATE(r.created_at) <= ?";
        $params[] = $date_to;
    }

    $whereSql = implode(' AND ', $where);

    // Count total
    $countSql = "SELECT COUNT(*) FROM returns r
        LEFT JOIN sales s ON r.sale_id = s.id
        LEFT JOIN customers c ON r.customer_id = c.id
        WHERE $whereSql";
    $stmt = $pdo->prepare($countSql);
    $stmt->execute($params);
    $total = (int)$stmt->fetchColumn();

    // Fetch returns
    $sql = "SELECT r.id, r.return_number, r.status, r.amount, r.reason, r.created_at,
                   s.invoice_number, c.name as customer_name
            FROM returns r
            LEFT JOIN sales s ON r.sale_id = s.id
            LEFT JOIN customers c ON r.customer_id = c.id
            WHERE $whereSql
            ORDER BY r.created_at DESC
            LIMIT $limit OFFSET $offset";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $returns = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Stats
    $statsSql = "SELECT status, COUNT(*) as cnt FROM returns WHERE tenant_id = ? AND branch_id = ? GROUP BY status";
    $stmt = $pdo->prepare($statsSql);
    $stmt->execute([$tenant_id, $branch_id]);
    $statsRows = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    $stats = [
        'total' => $total,
        'completed' => $statsRows['completed'] ?? 0,
        'pending' => $statsRows['pending'] ?? 0,
        'rejected' => $statsRows['rejected'] ?? 0
    ];

    echo json_encode([
        'success' => true,
        'returns' => array_map(function($r) {
            return [
                'id' => (int)$r['id'],
                'return_number' => $r['return_number'],
                'status' => $r['status'],
                'amount' => (float)$r['amount'],
                'reason' => $r['reason'] ?? '',
                'created_at' => $r['created_at'],
                'invoice_number' => $r['invoice_number'] ?: 'N/A',
                'customer_name' => $r['customer_name'] ?: 'Walk-in'
            ];
        }, $returns),
        'total' => $total,
        'page' => $page,
        'total_pages' => max(1, (int)ceil($total / $limit)),
        'stats' => $stats
    ]);

} catch (Exception $e) {
    error_log("search_returns.php error: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Failed to load returns']);
}
?>
