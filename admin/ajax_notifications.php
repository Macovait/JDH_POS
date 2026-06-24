<?php
/**
 * Notifications API - Super Admin
 * Fetch and manage system notifications
 */

require_once __DIR__ . '/bootstrap.php';

header('Content-Type: application/json');

// Verify super admin
if (!admin_is_authenticated() || !is_super_admin()) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$pdo = admin_db();
if (!$pdo) {
    echo json_encode(['notifications' => []]);
    exit;
}

// Handle mark all as read
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['action']) && $_GET['action'] === 'mark_all_read') {
    try {
        // Check if admin_notifications table exists
        $stmt = $pdo->query("SHOW TABLES LIKE 'admin_notifications'");
        if ($stmt->rowCount() === 0) {
            echo json_encode(['success' => true]);
            exit;
        }
        
        $pdo->prepare("UPDATE admin_notifications SET is_read = 1, read_at = NOW() WHERE is_read = 0")->execute();
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        error_log('Mark all read error: ' . $e->getMessage());
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// Fetch notifications
try {
    $notifications = [];
    
    // Check if admin_notifications table exists
    $stmt = $pdo->query("SHOW TABLES LIKE 'admin_notifications'");
    if ($stmt->rowCount() > 0) {
        $limit = (int) ($_GET['limit'] ?? 10);
        
        $stmt = $pdo->prepare("
            SELECT id, type, title, message, is_read, created_at, link
            FROM admin_notifications
            ORDER BY created_at DESC
            LIMIT ?
        ");
        $stmt->execute([$limit]);
        $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // If no notifications table, generate from recent activity
    if (empty($notifications)) {
        // Check recent tenant signups
        if (admin_table_exists('pos_tenants')) {
            $stmt = $pdo->query("
                SELECT id, name, status, created_at
                FROM pos_tenants
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
                ORDER BY created_at DESC
                LIMIT 5
            ");
            $newTenants = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($newTenants as $tenant) {
                $notifications[] = [
                    'id' => 'tenant_' . $tenant['id'],
                    'type' => 'success',
                    'title' => 'New Tenant Signup',
                    'message' => $tenant['name'] . ' just signed up',
                    'is_read' => 0,
                    'created_at' => $tenant['created_at'],
                    'link' => 'tenant_detail.php?id=' . $tenant['id']
                ];
            }
        }
        
        // Check recent support tickets
        if (admin_table_exists('support_tickets')) {
            $stmt = $pdo->query("
                SELECT id, subject, priority, created_at
                FROM support_tickets
                WHERE status = 'open' AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
                ORDER BY created_at DESC
                LIMIT 5
            ");
            $newTickets = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($newTickets as $ticket) {
                $notifications[] = [
                    'id' => 'ticket_' . $ticket['id'],
                    'type' => $ticket['priority'] === 'urgent' ? 'error' : 'warning',
                    'title' => 'New Support Ticket',
                    'message' => $ticket['subject'],
                    'is_read' => 0,
                    'created_at' => $ticket['created_at'],
                    'link' => 'support_tickets.php?id=' . $ticket['id']
                ];
            }
        }
    }
    
    echo json_encode(['notifications' => $notifications]);
    
} catch (Exception $e) {
    error_log('Notifications fetch error: ' . $e->getMessage());
    echo json_encode(['notifications' => []]);
}
