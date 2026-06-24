<?php
/**
 * Custom Report Query Endpoint
 * Executes real SQL based on user-selected report type, grouping, sort and limit.
 */

require_once __DIR__ . '/../../src/auth.php';
require_once __DIR__ . '/../../src/functions.php';
require_once __DIR__ . '/../../src/db.php';

header('Content-Type: application/json');

require_login();

if (!check_permission('reports.view') && !check_permission('sales.view') && !is_super_admin()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$tenant_id   = (int) ($_SESSION['tenant_id'] ?? 0);
$report_type = $_GET['report_type'] ?? 'sales';
$group_by    = $_GET['group_by']    ?? 'day';
$sort_by     = $_GET['sort_by']     ?? 'date';
$limit       = min((int) ($_GET['limit'] ?? 25), 500);
$from        = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['from'] ?? '') ? $_GET['from'] : date('Y-m-01');
$to          = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['to']   ?? '') ? $_GET['to']   : date('Y-m-d');
$requested_branch_id = isset($_GET['branch_id']) ? (int) $_GET['branch_id'] : 0;
$currency    = $_GET['currency'] ?? $_SESSION['tenant_currency'] ?? 'USD';

$pdo = get_db_connection();

// Validate branch_id belongs to tenant (prevents cross-tenant data leakage)
$branch_id = resolve_branch_id($requested_branch_id, $tenant_id);
if ($branch_id <= 0 && $requested_branch_id > 0) {
    $branch_id = 0; // Treat as no branch filter for unauthorized access
}

$params = [];

// ── Date grouping expression ──────────────────────────────────────────────────
$group_expr = match ($group_by) {
    'week'  => "DATE_FORMAT(DATE_SUB(date_col, INTERVAL WEEKDAY(date_col) DAY), '%Y-%m-%d')",
    'month' => "DATE_FORMAT(date_col, '%Y-%m')",
    'year'  => "YEAR(date_col)",
    default => "DATE(date_col)",   // day
};

try {
    switch ($report_type) {

        // ── SALES ─────────────────────────────────────────────────────────────
        case 'sales':
            $date_col = 's.created_at';
            $grp = str_replace('date_col', $date_col, $group_expr);

            $sql = "SELECT
                        {$grp}                                     AS period,
                        COUNT(s.id)                                AS transactions,
                        COALESCE(SUM(s.total), 0)                 AS revenue,
                        COALESCE(AVG(s.total), 0)                 AS avg_sale,
                        COALESCE(SUM(s.discount_amount), 0)       AS total_discount,
                        COALESCE(SUM(s.tax_amount), 0)            AS total_tax,
                        SUM(CASE WHEN s.payment_method='cash'  THEN s.total ELSE 0 END) AS cash,
                        SUM(CASE WHEN s.payment_method='card'  THEN s.total ELSE 0 END) AS card,
                        SUM(CASE WHEN s.payment_method='mpesa' THEN s.total ELSE 0 END) AS mpesa
                    FROM sales s
                    WHERE s.tenant_id = ?
                      AND DATE(s.created_at) BETWEEN ? AND ?
                      AND s.voided = 0
                      AND s.status NOT IN ('cancelled','refunded')";
            $params = [$tenant_id, $from, $to];

            if ($branch_id > 0) { $sql .= " AND s.branch_id = ?"; $params[] = $branch_id; }
            $sql .= " GROUP BY {$grp}";
            $sql .= match ($sort_by) {
                'revenue'      => " ORDER BY revenue DESC",
                'transactions' => " ORDER BY transactions DESC",
                default        => " ORDER BY period ASC",
            };
            $sql .= " LIMIT {$limit}";

            $headers = ['Period', 'Transactions', 'Revenue', 'Avg Sale', 'Discount', 'Tax', 'Cash', 'Card', 'M-Pesa'];
            $keys    = ['period','transactions','revenue','avg_sale','total_discount','total_tax','cash','card','mpesa'];
            $money   = ['revenue','avg_sale','total_discount','total_tax','cash','card','mpesa'];
            break;

        // ── PRODUCTS ─────────────────────────────────────────────────────────
        case 'products':
            $sql = "SELECT
                        si.product_name                            AS product,
                        si.product_sku                            AS sku,
                        COALESCE(SUM(si.quantity), 0)             AS qty_sold,
                        COALESCE(SUM(si.subtotal), 0)             AS revenue,
                        COALESCE(AVG(si.price), 0)                AS avg_price,
                        COUNT(DISTINCT si.sale_id)                AS in_orders
                    FROM sale_items si
                    JOIN sales s ON s.id = si.sale_id
                    WHERE s.tenant_id = ?
                      AND DATE(s.created_at) BETWEEN ? AND ?
                      AND s.voided = 0
                      AND s.status NOT IN ('cancelled','refunded')
                      AND si.deleted_at IS NULL";
            $params = [$tenant_id, $from, $to];

            if ($branch_id > 0) { $sql .= " AND s.branch_id = ?"; $params[] = $branch_id; }
            $sql .= " GROUP BY si.product_id, si.product_name, si.product_sku";
            $sql .= match ($sort_by) {
                'revenue'      => " ORDER BY revenue DESC",
                'transactions' => " ORDER BY in_orders DESC",
                default        => " ORDER BY qty_sold DESC",
            };
            $sql .= " LIMIT {$limit}";

            $headers = ['Product', 'SKU', 'Qty Sold', 'Revenue', 'Avg Price', 'Orders'];
            $keys    = ['product','sku','qty_sold','revenue','avg_price','in_orders'];
            $money   = ['revenue','avg_price'];
            break;

        // ── CUSTOMERS ────────────────────────────────────────────────────────
        case 'customers':
            $sql = "SELECT
                        COALESCE(c.name, 'Walk-in')               AS customer,
                        COALESCE(c.phone, '—')                    AS phone,
                        COUNT(s.id)                               AS visits,
                        COALESCE(SUM(s.total), 0)                 AS total_spent,
                        COALESCE(AVG(s.total), 0)                 AS avg_spend,
                        MAX(DATE(s.created_at))                   AS last_visit
                    FROM sales s
                    LEFT JOIN customers c ON c.id = s.customer_id
                    WHERE s.tenant_id = ?
                      AND DATE(s.created_at) BETWEEN ? AND ?
                      AND s.voided = 0
                      AND s.status NOT IN ('cancelled','refunded')";
            $params = [$tenant_id, $from, $to];

            if ($branch_id > 0) { $sql .= " AND s.branch_id = ?"; $params[] = $branch_id; }
            $sql .= " GROUP BY s.customer_id, c.name, c.phone";
            $sql .= match ($sort_by) {
                'revenue'      => " ORDER BY total_spent DESC",
                'transactions' => " ORDER BY visits DESC",
                default        => " ORDER BY last_visit DESC",
            };
            $sql .= " LIMIT {$limit}";

            $headers = ['Customer', 'Phone', 'Visits', 'Total Spent', 'Avg Spend', 'Last Visit'];
            $keys    = ['customer','phone','visits','total_spent','avg_spend','last_visit'];
            $money   = ['total_spent','avg_spend'];
            break;

        // ── INVENTORY ────────────────────────────────────────────────────────
        case 'inventory':
            $sql = "SELECT
                        p.name                                    AS product,
                        p.sku,
                        COALESCE(p.stock_status, 'unknown')       AS stock_status,
                        COALESCE(p.reorder_level, 0)              AS reorder_level,
                        COALESCE(p.cost_price, 0)                 AS cost_price,
                        COALESCE(p.selling_price, p.price, 0)     AS selling_price,
                        COALESCE(sm.total_in,  0)                 AS total_in,
                        COALESCE(sm.total_out, 0)                 AS total_out
                    FROM products p
                    LEFT JOIN (
                        SELECT product_id,
                               SUM(CASE WHEN quantity_change > 0 THEN  quantity_change ELSE 0 END) AS total_in,
                               SUM(CASE WHEN quantity_change < 0 THEN -quantity_change ELSE 0 END) AS total_out
                        FROM stock_movements
                        WHERE tenant_id = ?
                          AND DATE(created_at) BETWEEN ? AND ?
                        GROUP BY product_id
                    ) sm ON sm.product_id = p.id
                    WHERE p.tenant_id = ?
                      AND p.deleted_at IS NULL
                      AND p.active = 1";
            $params = [$tenant_id, $from, $to, $tenant_id];

            if ($branch_id > 0) { $sql .= " AND p.branch_id = ?"; $params[] = $branch_id; }
            $sql .= match ($sort_by) {
                'revenue'      => " ORDER BY selling_price DESC",
                'transactions' => " ORDER BY total_out DESC",
                default        => " ORDER BY p.name ASC",
            };
            $sql .= " LIMIT {$limit}";

            $headers = ['Product', 'SKU', 'Status', 'Reorder Lvl', 'Cost', 'Price', 'Stock In', 'Stock Out'];
            $keys    = ['product','sku','stock_status','reorder_level','cost_price','selling_price','total_in','total_out'];
            $money   = ['cost_price','selling_price'];
            break;

        default:
            echo json_encode(['success' => false, 'error' => 'Unknown report type']);
            exit;
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Format money columns
    $formatted = [];
    foreach ($rows as $row) {
        $frow = [];
        foreach ($keys as $k) {
            $val = $row[$k] ?? '';
            if (in_array($k, $money)) {
                $frow[$k] = $currency . ' ' . number_format((float) $val, 2);
            } elseif (is_numeric($val) && !in_array($k, ['reorder_level','qty_sold','visits','in_orders','total_in','total_out'])) {
                $frow[$k] = $val;
            } else {
                $frow[$k] = $val;
            }
        }
        $formatted[] = $frow;
    }

    echo json_encode([
        'success'  => true,
        'headers'  => $headers,
        'keys'     => $keys,
        'rows'     => $formatted,
        'count'    => count($formatted),
        'from'     => $from,
        'to'       => $to,
        'currency' => $currency,
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    error_log('Custom report query error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Database error occurred. Please contact support.']);
}
exit;
