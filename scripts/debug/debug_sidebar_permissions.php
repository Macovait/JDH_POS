<?php
/**
 * Debug Sidebar Permission Issues
 */

// Start session manually
if (session_status() === PHP_SESSION_NONE) {
    session_name('jakababa_saas_sid');
    session_start();
}

require_once 'src/paths.php';
safe_require('db.php', 'src', true);
safe_require('functions.php', 'src', true);
require_once 'src/Security/SecurityBootstrap.php';
require_once 'src/Security/DynamicSidebarFixed.php';

echo "=== Debugging Sidebar Permissions ===\n\n";

try {
    $pdo = get_db_connection();
    
    // Test cashier role specifically
    $stmt = $pdo->prepare("
        SELECT r.id, r.name, r.tenant_id
        FROM roles r 
        WHERE r.name = 'Cashier' AND r.deleted_at IS NULL
        LIMIT 1
    ");
    $stmt->execute();
    $role = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$role) {
        echo "❌ Cashier role not found\n";
        exit;
    }
    
    echo "Testing Cashier Role (ID: {$role['id']})\n";
    echo str_repeat("-", 40) . "\n";
    
    // Setup session
    $_SESSION['user_id'] = 1;
    $_SESSION['user_name'] = 'Wycliffe';
    $_SESSION['username'] = 'wycliffe';
    $_SESSION['user_email'] = 'user_1@secured.local';
    $_SESSION['tenant_id'] = $role['tenant_id'];
    $_SESSION['tenant_name'] = 'JAKPOS';
    $_SESSION['tenant_slug'] = 'demo';
    $_SESSION['tenant_code'] = 'demo';
    $_SESSION['branch_id'] = 1;
    $_SESSION['role'] = 'cashier';
    $_SESSION['user_role'] = 'cashier';
    $_SESSION['authenticated_at'] = time();
    $_SESSION['is_super_admin'] = false;
    
    // Load permissions
    $stmt = $pdo->prepare("
        SELECT DISTINCT p.code
        FROM permissions p
        JOIN role_permissions rp ON rp.permission_id = p.id AND rp.tenant_id = p.tenant_id
        WHERE rp.role_id = ? AND p.tenant_id = ? AND p.deleted_at IS NULL
        ORDER BY p.code
    ");
    $stmt->execute([$role['id'], $role['tenant_id']]);
    $permissions = $stmt->fetchAll(PDO::FETCH_COLUMN);
    $_SESSION['permissions'] = $permissions;
    
    echo "Permissions loaded: " . count($permissions) . "\n";
    echo "Permissions: " . implode(', ', $permissions) . "\n\n";
    
    // Initialize security system
    SecurityBootstrap::initialize();
    
    // Test has_permission function directly
    echo "Testing has_permission function:\n";
    $test_perms = ['dashboard.view', 'pos.access', 'products.view', 'users.view', 'system.settings'];
    foreach ($test_perms as $perm) {
        $has_perm = has_permission($perm);
        echo "  {$perm}: " . ($has_perm ? '✅' : '❌') . "\n";
    }
    echo "\n";
    
    // Test dynamic sidebar
    echo "Testing Dynamic Sidebar:\n";
    $dynamicSidebar = DynamicSidebar::getInstance();
    $sidebarSections = $dynamicSidebar->generateSidebar();
    
    echo "Generated sections: " . count($sidebarSections) . "\n";
    foreach ($sidebarSections as $section) {
        echo "  Section: " . $section['label'] . " (" . count($section['items']) . " items)\n";
        foreach ($section['items'] as $item) {
            $permission = $item['permission'] ?? 'none';
            echo "    - " . $item['label'] . " [{$permission}]\n";
        }
    }
    
    // Get menu stats
    $stats = $dynamicSidebar->getMenuStats();
    echo "\nMenu Stats:\n";
    echo "  Total sections: " . $stats['total_sections'] . "\n";
    echo "  Allowed sections: " . $stats['allowed_sections'] . "\n";
    echo "  Total items: " . $stats['total_items'] . "\n";
    echo "  Allowed items: " . $stats['allowed_items'] . "\n";
    echo "  Blocked items: " . $stats['blocked_items'] . "\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}
?>
