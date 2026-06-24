<?php
/**
 * Global Search API - Super Admin
 * Searches across tenants, users, subscriptions, tickets
 */

require_once __DIR__ . '/bootstrap.php';

header('Content-Type: application/json');

// Verify super admin
if (!admin_is_authenticated() || !is_super_admin()) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$query = $_GET['q'] ?? '';
if (empty($query) || strlen($query) < 2) {
    echo json_encode(['results' => []]);
    exit;
}

$pdo = admin_db();
if (!$pdo) {
    echo json_encode(['results' => []]);
    exit;
}

$searchTerm = '%' . $query . '%';
$results = [];
$limit = 10;

try {
    // 1. Search Tenants
    if (admin_table_exists('pos_tenants')) {
        $stmt = $pdo->prepare("
            SELECT id, name, slug, email, status, business_type
            FROM pos_tenants 
            WHERE name LIKE ? OR slug LIKE ? OR email LIKE ?
            ORDER BY created_at DESC
            LIMIT 5
        ");
        $stmt->execute([$searchTerm, $searchTerm, $searchTerm]);
        $tenants = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($tenants as $tenant) {
            $results[] = [
                'type' => 'tenant',
                'title' => $tenant['name'],
                'subtitle' => $tenant['email'] . ' • ' . ucfirst($tenant['status']),
                'url' => 'tenant_detail.php?id=' . $tenant['id'],
                'icon' => 'fa-building',
                'color' => 'blue'
            ];
        }
    }
    
    // 2. Search Subscriptions
    if (admin_table_exists('pos_subscriptions')) {
        $stmt = $pdo->prepare("
            SELECT s.id, s.status, s.plan_id, t.name as tenant_name, p.name as plan_name
            FROM pos_subscriptions s
            JOIN pos_tenants t ON s.tenant_id = t.id
            LEFT JOIN pos_plans p ON s.plan_id = p.id
            WHERE t.name LIKE ? OR p.name LIKE ?
            ORDER BY s.created_at DESC
            LIMIT 5
        ");
        $stmt->execute([$searchTerm, $searchTerm]);
        $subscriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($subscriptions as $sub) {
            $results[] = [
                'type' => 'subscription',
                'title' => $sub['tenant_name'],
                'subtitle' => ($sub['plan_name'] ?? 'Unknown Plan') . ' • ' . ucfirst($sub['status']),
                'url' => 'subscriptions.php?search=' . urlencode($sub['tenant_name']),
                'icon' => 'fa-credit-card',
                'color' => 'emerald'
            ];
        }
    }
    
    // 3. Search Support Tickets (if exists)
    if (admin_table_exists('support_tickets')) {
        $stmt = $pdo->prepare("
            SELECT id, subject, status, priority, ticket_number
            FROM support_tickets
            WHERE subject LIKE ? OR ticket_number LIKE ?
            ORDER BY created_at DESC
            LIMIT 5
        ");
        $stmt->execute([$searchTerm, $searchTerm]);
        $tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($tickets as $ticket) {
            $priorityColors = [
                'urgent' => 'red',
                'high' => 'orange',
                'medium' => 'amber',
                'low' => 'slate'
            ];
            
            $results[] = [
                'type' => 'ticket',
                'title' => '#' . $ticket['ticket_number'] . ' ' . $ticket['subject'],
                'subtitle' => ucfirst($ticket['status']) . ' • ' . ucfirst($ticket['priority']) . ' Priority',
                'url' => 'support_tickets.php?id=' . $ticket['id'],
                'icon' => 'fa-ticket-alt',
                'color' => $priorityColors[$ticket['priority']] ?? 'purple'
            ];
        }
    }
    
    // 4. Search Admin Users
    if (admin_table_exists('admins')) {
        $stmt = $pdo->prepare("
            SELECT id, name, email, username, role
            FROM admins
            WHERE name LIKE ? OR email LIKE ? OR username LIKE ?
            ORDER BY name
            LIMIT 5
        ");
        $stmt->execute([$searchTerm, $searchTerm, $searchTerm]);
        $admins = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($admins as $admin) {
            $results[] = [
                'type' => 'admin',
                'title' => $admin['name'],
                'subtitle' => $admin['email'] . ' • ' . ucfirst($admin['role']),
                'url' => 'roles.php',
                'icon' => 'fa-user-shield',
                'color' => 'amber'
            ];
        }
    }
    
    // 5. Search Plans
    if (admin_table_exists('pos_plans')) {
        $stmt = $pdo->prepare("
            SELECT id, name, price, billing_cycle
            FROM pos_plans
            WHERE name LIKE ? OR description LIKE ?
            ORDER BY price
            LIMIT 5
        ");
        $stmt->execute([$searchTerm, $searchTerm]);
        $plans = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($plans as $plan) {
            $results[] = [
                'type' => 'plan',
                'title' => $plan['name'],
                'subtitle' => '$' . $plan['price'] . '/' . $plan['billing_cycle'],
                'url' => 'plans.php',
                'icon' => 'fa-layer-group',
                'color' => 'cyan'
            ];
        }
    }
    
    // Sort results by relevance (simple: title starts with query first)
    usort($results, function($a, $b) use ($query) {
        $aStartsWith = stripos($a['title'], $query) === 0;
        $bStartsWith = stripos($b['title'], $query) === 0;
        
        if ($aStartsWith && !$bStartsWith) return -1;
        if (!$aStartsWith && $bStartsWith) return 1;
        return 0;
    });
    
    // Limit total results
    $results = array_slice($results, 0, $limit);
    
    echo json_encode(['results' => $results]);
    
} catch (Exception $e) {
    error_log('Global search error: ' . $e->getMessage());
    echo json_encode(['results' => []]);
}
