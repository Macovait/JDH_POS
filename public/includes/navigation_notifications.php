<?php
/**
 * Navigation Notifications System
 * Shows badges and alerts on navigation items
 */

require_once __DIR__ . '/../../src/paths.php';
safe_require('auth.php', 'src', true);
safe_require('db.php', 'src', true);
safe_require('functions.php', 'src', true);

// Get notification counts for navigation badges
function get_navigation_notifications($tenant_id, $branch_id) {
    $pdo = get_db_connection();
    $notifications = [];

    try {
        // Low stock alerts
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as count
            FROM inventory i
            JOIN products p ON i.product_id = p.id
            WHERE p.tenant_id = ? AND i.branch_id = ? AND i.stock <= p.reorder_level AND i.stock > 0
        ");
        $stmt->execute([$tenant_id, $branch_id]);
        $notifications['low_stock'] = (int) $stmt->fetchColumn();

        // Out of stock items
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as count
            FROM inventory i
            JOIN products p ON i.product_id = p.id
            WHERE p.tenant_id = ? AND i.branch_id = ? AND i.stock <= 0
        ");
        $stmt->execute([$tenant_id, $branch_id]);
        $notifications['out_of_stock'] = (int) $stmt->fetchColumn();

        // Pending purchase orders
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as count
            FROM purchase_orders
            WHERE tenant_id = ? AND status = 'pending'
        ");
        $stmt->execute([$tenant_id]);
        $notifications['pending_orders'] = (int) $stmt->fetchColumn();

        // Pending quotations
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as count
            FROM quotations
            WHERE tenant_id = ? AND status = 'pending'
        ");
        $stmt->execute([$tenant_id]);
        $notifications['pending_quotations'] = (int) $stmt->fetchColumn();

        // Failed payment attempts
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as count
            FROM payments
            WHERE tenant_id = ? AND payment_status = 'failed'
            AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
        ");
        $stmt->execute([$tenant_id]);
        $notifications['failed_payments'] = (int) $stmt->fetchColumn();

        // Active marketing campaigns
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as count
            FROM marketing_campaigns
            WHERE tenant_id = ? AND status = 'active'
        ");
        $stmt->execute([$tenant_id]);
        $notifications['active_campaigns'] = (int) $stmt->fetchColumn();

        // Pending customer approvals
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as count
            FROM customers
            WHERE tenant_id = ? AND status = 'pending'
        ");
        $stmt->execute([$tenant_id]);
        $notifications['pending_customers'] = (int) $stmt->fetchColumn();

    } catch (Exception $e) {
        // Return empty notifications on error
        return array_fill_keys(['low_stock', 'out_of_stock', 'pending_orders', 'pending_quotations', 'failed_payments', 'active_campaigns', 'pending_customers'], 0);
    }

    return $notifications;
}

// Render notification badge
function render_notification_badge($count, $type = 'default') {
    if ($count <= 0) return '';

    $colors = [
        'default' => 'bg-red-500',
        'warning' => 'bg-amber-500',
        'success' => 'bg-green-500',
        'info' => 'bg-blue-500'
    ];

    $color_class = $colors[$type] ?? $colors['default'];

    // Format large numbers
    $display_count = $count > 99 ? '99+' : $count;

    return "<span class=\"nav-badge {$color_class}\">{$display_count}</span>";
}

// Get notification data for current user
$notifications = [];
if (isset($_SESSION['tenant_id']) && isset($_SESSION['branch_id'])) {
    $notifications = get_navigation_notifications($_SESSION['tenant_id'], $_SESSION['branch_id']);
}
?>