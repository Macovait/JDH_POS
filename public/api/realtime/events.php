<?php
/**
 * Server-Sent Events (SSE) Endpoint
 * Real-time updates for POS - sales, notifications, sync
 */

require_once __DIR__ . '/../../../src/paths.php';

// Disable output buffering
@ini_set('output_buffering', 'off');
@ini_set('zlib.output_compression', false);
@ini_set('implicit_flush', true);

// Headers for SSE
header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('Connection: keep-alive');
header('X-Accel-Buffering: no'); // Disable Nginx buffering

// Check authentication
safe_require('auth.php', 'src', true);

// Branch filter for multi-tenant isolation
$current_branch_id = get_current_branch_id();
if (!is_logged_in()) {
    echo "event: error\n";
    echo "data: " . json_encode(['error' => 'Unauthorized']) . "\n\n";
    exit;
}

$tenantId = $_SESSION['tenant_id'] ?? 0;
$userId = $_SESSION['user_id'] ?? 0;
$lastEventId = isset($_SERVER['HTTP_LAST_EVENT_ID']) ? (int) $_SERVER['HTTP_LAST_EVENT_ID'] : 0;

// Get database connection
safe_require('db.php', 'src', true);

try {
    $pdo = get_db_connection();
    
    // Send initial connection message
    echo "event: connected\n";
    echo "data: " . json_encode([
        'tenant_id' => $tenantId,
        'user_id' => $userId,
        'time' => date('Y-m-d H:i:s')
    ]) . "\n\n";
    
    flush();
    
    // Keep connection alive and send updates
    $counter = 0;
    $maxIterations = 60; // Run for ~1 minute (60 * 1 second sleep)
    
    while ($counter < $maxIterations) {
        // Check for new sales
        $newSales = checkNewSales($pdo, $tenantId, $lastEventId);
        if (!empty($newSales)) {
            foreach ($newSales as $sale) {
                echo "event: sale\n";
                echo "id: {$sale['id']}\n";
                echo "data: " . json_encode($sale) . "\n\n";
                $lastEventId = max($lastEventId, $sale['id']);
            }
        }

        // Check for inventory alerts (every 5 seconds)
        if ($counter % 5 === 0) {
            $inventoryAlerts = checkInventoryAlerts($pdo, $tenantId);
            if (!empty($inventoryAlerts)) {
                echo "event: inventory_alert\n";
                echo "data: " . json_encode($inventoryAlerts) . "\n\n";
            }
        }

        // Check for performance metrics (every 10 seconds)
        if ($counter % 10 === 0) {
            $metrics = getRealtimeMetrics($pdo, $tenantId);
            echo "event: metrics\n";
            echo "data: " . json_encode($metrics) . "\n\n";
        }

        // Check for notifications
        $notifications = checkNotifications($pdo, $userId);
        if (!empty($notifications)) {
            foreach ($notifications as $notification) {
                echo "event: notification\n";
                echo "data: " . json_encode($notification) . "\n\n";
            }
        }

        // Check for system status
        $systemStatus = getSystemStatus($pdo);
        if ($counter % 10 === 0) { // Every 10 seconds
            echo "event: ping\n";
            echo "data: " . json_encode(['time' => time(), 'status' => $systemStatus]) . "\n\n";
        }

        flush();

        // Sleep for 1 second
        sleep(1);
        $counter++;
    }
    
} catch (\Exception $e) {
    echo "event: error\n";
    echo "data: " . json_encode(['error' => $e->getMessage()]) . "\n\n";
}

// Clean end
echo "event: close\n";
echo "data: " . json_encode(['message' => 'Connection closing']) . "\n\n";

/**
 * Check for new sales since last event
 */
function checkNewSales(\PDO $pdo, int $tenantId, int $lastId): array
{
    $stmt = $pdo->prepare("
        SELECT s.id, s.receipt_number, s.final_amount, s.created_at, u.name as cashier
        FROM sales s
        LEFT JOIN users u ON s.user_id = u.id
        WHERE s.tenant_id = ?
        AND s.id > ?
        AND s.created_at >= DATE_SUB(NOW(), INTERVAL 5 MINUTE)
        ORDER BY s.id DESC
        LIMIT 10
    ");
    $stmt->execute([$tenantId, $lastId]);
    return $stmt->fetchAll(\PDO::FETCH_ASSOC);
}

/**
 * Check for new notifications
 */
function checkNotifications(\PDO $pdo, int $userId): array
{
    // Simple query - would need notifications table
    return [];
}

/**
 * Check for inventory alerts (low stock, out of stock)
 */
function checkInventoryAlerts(\PDO $pdo, int $tenantId): array
{
    $alerts = [];

    // Low stock alerts (below reorder level)
    $stmt = $pdo->prepare("
        SELECT p.id, p.name, i.stock, p.reorder_level, b.name as branch_name
        FROM inventory i
        JOIN products p ON i.product_id = p.id
        JOIN branches b ON i.branch_id = b.id
        WHERE p.tenant_id = ?
        AND i.stock <= p.reorder_level
        AND i.stock > 0
        AND p.active = 1
        ORDER BY (p.reorder_level - i.stock) DESC
        LIMIT 5
    ");
    $stmt->execute([$tenantId]);
    $lowStock = $stmt->fetchAll(\PDO::FETCH_ASSOC);

    if (!empty($lowStock)) {
        $alerts['low_stock'] = $lowStock;
    }

    // Out of stock alerts
    $stmt = $pdo->prepare("
        SELECT p.id, p.name, b.name as branch_name
        FROM inventory i
        JOIN products p ON i.product_id = p.id
        JOIN branches b ON i.branch_id = b.id
        WHERE p.tenant_id = ?
        AND i.stock <= 0
        AND p.active = 1
        ORDER BY p.name
        LIMIT 5
    ");
    $stmt->execute([$tenantId]);
    $outOfStock = $stmt->fetchAll(\PDO::FETCH_ASSOC);

    if (!empty($outOfStock)) {
        $alerts['out_of_stock'] = $outOfStock;
    }

    return $alerts;
}

/**
 * Get real-time performance metrics
 */
function getRealtimeMetrics(\PDO $pdo, int $tenantId): array
{
    $metrics = [];

    // Today's sales metrics
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
    $todayMetrics = $stmt->fetch(\PDO::FETCH_ASSOC);

    $metrics['today'] = $todayMetrics ?: [
        'sales_count' => 0,
        'total_revenue' => 0,
        'avg_sale' => 0,
        'unique_customers' => 0
    ];

    // Current inventory value
    $stmt = $pdo->prepare("
        SELECT
            COALESCE(SUM(i.stock * p.cost_price), 0) as inventory_value,
            COALESCE(SUM(i.stock), 0) as total_stock
        FROM inventory i
        JOIN products p ON i.product_id = p.id
        WHERE p.tenant_id = ?
        AND p.active = 1
    ");
    $stmt->execute([$tenantId]);
    $inventoryMetrics = $stmt->fetch(\PDO::FETCH_ASSOC);

    $metrics['inventory'] = $inventoryMetrics ?: [
        'inventory_value' => 0,
        'total_stock' => 0
    ];

    // Active registers/sessions
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as active_sessions
        FROM register_sessions
        WHERE tenant_id = ?
        AND status = 'open'
    ");
    $stmt->execute([$tenantId]);
    $sessionMetrics = $stmt->fetch(\PDO::FETCH_ASSOC);

    $metrics['sessions'] = $sessionMetrics ?: ['active_sessions' => 0];

    // Recent activity (last 5 minutes)
    $stmt = $pdo->prepare("
        SELECT
            'sale' as type,
            id,
            final_amount as amount,
            created_at
        FROM sales
        WHERE tenant_id = ?
        AND created_at >= DATE_SUB(NOW(), INTERVAL 5 MINUTE)
        AND status = 'completed'
        UNION ALL
        SELECT
            'return' as type,
            id,
            -total_amount as amount,
            created_at
        FROM returns
        WHERE tenant_id = ?
        AND created_at >= DATE_SUB(NOW(), INTERVAL 5 MINUTE)
        ORDER BY created_at DESC
        LIMIT 10
    ");
    $stmt->execute([$tenantId, $tenantId]);
    $recentActivity = $stmt->fetchAll(\PDO::FETCH_ASSOC);

    $metrics['recent_activity'] = $recentActivity;

    return $metrics;
}

/**
 * Get system status
 */
function getSystemStatus(\PDO $pdo): array
{
    try {
        $pdo->query('SELECT 1');
        return ['database' => 'connected', 'status' => 'healthy'];
    } catch (\Exception $e) {
        return ['database' => 'error', 'status' => 'degraded', 'error' => $e->getMessage()];
    }
}
