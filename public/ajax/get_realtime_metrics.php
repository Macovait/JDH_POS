<?php
/**
 * AJAX Endpoint for Real-time Metrics
 * Returns current dashboard metrics for live updates
 */

// JSON header must be first
header('Content-Type: application/json');

try {
    require_once __DIR__ . '/../../src/paths.php';
    safe_require('auth.php', 'src', true);
    safe_require('db.php', 'src', true);

    // Return 401 JSON instead of redirect for AJAX requests
    if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized']);
        exit;
    }

    $pdo = get_db_connection();
    $tenantId = (int)($_SESSION['tenant_id'] ?? 0);

    // Default empty structures
    $todayMetrics    = ['sales_count' => 0, 'total_revenue' => 0, 'avg_sale' => 0, 'unique_customers' => 0];
    $inventoryMetrics = ['inventory_value' => 0, 'total_stock' => 0];
    $sessionMetrics  = ['active_sessions' => 0];
    $stockAlerts     = ['low_stock' => 0, 'out_of_stock' => 0];
    $recentActivity  = [];

    // Today's sales metrics
    try {
        $stmt = $pdo->prepare("
            SELECT
                COUNT(*) as sales_count,
                COALESCE(SUM(final_amount), 0) as total_revenue,
                COALESCE(AVG(final_amount), 0) as avg_sale,
                COUNT(DISTINCT customer_id) as unique_customers
            FROM sales
            WHERE tenant_id = ?
            AND DATE(created_at) = CURDATE()
            AND status = 'completed'
        ");
        $stmt->execute([$tenantId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) $todayMetrics = $row;
    } catch (Throwable $e) { error_log('realtime_metrics sales: ' . $e->getMessage()); }

    // Inventory value
    try {
        $stmt = $pdo->prepare("
            SELECT
                COALESCE(SUM(i.stock * p.cost_price), 0) as inventory_value,
                COALESCE(SUM(i.stock), 0) as total_stock
            FROM inventory i
            JOIN products p ON i.product_id = p.id
            WHERE p.tenant_id = ? AND p.active = 1
        ");
        $stmt->execute([$tenantId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) $inventoryMetrics = $row;
    } catch (Throwable $e) { error_log('realtime_metrics inventory: ' . $e->getMessage()); }

    // Active POS shifts (use shifts table as fallback if register_sessions missing)
    try {
        $tables = $pdo->query("SHOW TABLES LIKE 'register_sessions'")->fetchAll();
        if (!empty($tables)) {
            $stmt = $pdo->prepare("SELECT COUNT(*) as active_sessions FROM register_sessions WHERE tenant_id = ? AND status = 'open'");
        } else {
            $stmt = $pdo->prepare("SELECT COUNT(*) as active_sessions FROM shifts WHERE tenant_id = ? AND status = 'open'");
        }
        $stmt->execute([$tenantId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) $sessionMetrics = $row;
    } catch (Throwable $e) { error_log('realtime_metrics sessions: ' . $e->getMessage()); }

    // Recent sales activity (last 10 minutes)
    try {
        $stmt = $pdo->prepare("
            SELECT 'sale' as type, id, final_amount as amount, created_at
            FROM sales
            WHERE tenant_id = ?
            AND created_at >= DATE_SUB(NOW(), INTERVAL 10 MINUTE)
            AND status = 'completed'
            ORDER BY created_at DESC
            LIMIT 10
        ");
        $stmt->execute([$tenantId]);
        $recentActivity = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) { error_log('realtime_metrics activity: ' . $e->getMessage()); }

    // Stock alerts
    try {
        $stmt = $pdo->prepare("
            SELECT
                SUM(CASE WHEN i.stock <= p.reorder_level AND i.stock > 0 THEN 1 ELSE 0 END) as low_stock,
                SUM(CASE WHEN i.stock <= 0 THEN 1 ELSE 0 END) as out_of_stock
            FROM inventory i
            JOIN products p ON i.product_id = p.id
            WHERE p.tenant_id = ? AND p.active = 1
        ");
        $stmt->execute([$tenantId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) $stockAlerts = $row;
    } catch (Throwable $e) { error_log('realtime_metrics stock: ' . $e->getMessage()); }

    echo json_encode([
        'today'           => $todayMetrics,
        'inventory'       => $inventoryMetrics,
        'sessions'        => $sessionMetrics,
        'stock_alerts'    => $stockAlerts,
        'recent_activity' => $recentActivity,
        'timestamp'       => time()
    ]);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'error'   => 'Server error',
        'message' => $e->getMessage()
    ]);
}
?>