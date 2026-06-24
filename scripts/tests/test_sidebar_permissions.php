<?php
/**
 * Test Sidebar Permission Filtering
 * Tests if the sidebar properly filters items based on cashier permissions
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

echo "=== Testing Sidebar Permission Filtering ===\n\n";

try {
    $pdo = get_db_connection();
    
    // Get cashier user and setup session
    $stmt = $pdo->prepare('
        SELECT u.*, t.name as tenant_name, t.subdomain, t.id as tenant_id
        FROM users u 
        JOIN tenants t ON u.tenant_id = t.id 
        WHERE u.username = "Wycliffe" AND u.deleted_at IS NULL AND u.status = 1
        LIMIT 1
    ');
    $stmt->execute();
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        echo "❌ Cashier user not found\n";
        exit;
    }
    
    // Setup session
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['user_name'] = $user['name'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['user_email'] = $user['email'];
    $_SESSION['tenant_id'] = $user['tenant_id'];
    $_SESSION['tenant_name'] = $user['tenant_name'];
    $_SESSION['tenant_slug'] = $user['subdomain'];
    $_SESSION['tenant_code'] = $user['subdomain'];
    $_SESSION['branch_id'] = $user['branch_id'] ?? 1;
    $_SESSION['role'] = 'cashier';
    $_SESSION['user_role'] = 'cashier';
    $_SESSION['authenticated_at'] = time();
    $_SESSION['is_super_admin'] = false;
    
    // Load permissions
    $stmt = $pdo->prepare('
        SELECT DISTINCT p.code
        FROM permissions p
        JOIN role_permissions rp ON rp.permission_id = p.id AND rp.tenant_id = p.tenant_id
        JOIN user_roles ur ON ur.role_id = rp.role_id AND ur.tenant_id = rp.tenant_id
        WHERE ur.user_id = ? AND p.tenant_id = ? AND p.deleted_at IS NULL
        ORDER BY p.code
    ');
    $stmt->execute([$user['id'], $user['tenant_id']]);
    $permissions = $stmt->fetchAll(PDO::FETCH_COLUMN);
    $_SESSION['permissions'] = $permissions;
    
    echo "✅ Cashier session initialized\n";
    echo "   Permissions loaded: " . count($permissions) . "\n\n";
    
    // Initialize security system
    SecurityBootstrap::initialize();
    
    // Define sidebar sections (same as in app.php)
    $sidebarSections = [
        [
            'label' => 'Overview',
            'items' => [
                ['icon' => 'fa-chart-pie', 'label' => 'Dashboard', 'permission' => 'dashboard.view', 'url' => 'dashboard/home.php'],
            ]
        ],
        ['label' => 'Sales', 'items' => [
            ['icon' => 'fa-cash-register', 'label' => 'Point of Sale', 'permission' => 'pos.access', 'url' => 'pos/pos.php'],
            ['icon' => 'fa-receipt', 'label' => 'All Sales', 'permission' => 'sales.view', 'url' => 'pos/all_sales.php'],
            ['icon' => 'fa-undo-alt', 'label' => 'Returns', 'permission' => 'returns.manage', 'url' => 'pos/select_sale_for_return.php'],
        ]],
        ['label' => 'Inventory', 'items' => [
            ['icon' => 'fa-boxes-stacked', 'label' => 'Products', 'permission' => 'products.view', 'url' => 'products/products.php'],
            ['icon' => 'fa-plus', 'label' => 'Add Product', 'permission' => 'products.create', 'url' => 'products/product_form.php'],
            ['icon' => 'fa-tags', 'label' => 'Brands', 'permission' => 'brands.view', 'url' => 'products/brands.php'],
            ['icon' => 'fa-warehouse', 'label' => 'Stock', 'permission' => 'inventory.view', 'url' => 'inventory/inventory.php'],
        ]],
        ['label' => 'People', 'items' => [
            ['icon' => 'fa-users', 'label' => 'All Customers', 'permission' => 'customers.view', 'url' => 'customers/customers.php'],
            ['icon' => 'fa-user-plus', 'label' => 'Add Customer', 'permission' => 'customers.create', 'url' => 'customers/add_customer.php'],
        ]],
        ['label' => 'Finance', 'items' => [
            ['icon' => 'fa-chart-line', 'label' => 'Reports', 'permission' => 'reports.view', 'url' => 'reports/reports.php'],
        ]],
        ['label' => 'System', 'items' => [
            ['icon' => 'fa-store', 'label' => 'Branches', 'permission' => 'branches.view', 'url' => 'branches/branches.php'],
            ['icon' => 'fa-user-gear', 'label' => 'Users', 'permission' => 'users.view', 'url' => 'users/users.php'],
            ['icon' => 'fa-user-shield', 'label' => 'Roles & Permissions', 'permission' => 'users.manage', 'url' => 'users/roles/roles.php'],
            ['icon' => 'fa-gear', 'label' => 'Settings', 'permission' => 'system.settings', 'url' => 'dashboard/settings.php'],
        ]],
    ];
    
    // Filter sidebar by permissions
    function filter_sidebar_by_permissions($sections){
        $filtered=[];
        foreach($sections as $section){
            $allowed=[];
            foreach($section["items"] as $item){
                $perm=$item["permission"]??null;
                if(!$perm||has_permission($perm)){
                    $allowed[]=$item;
                }
            }
            if(!empty($allowed)){
                $filtered[]=["label"=>$section["label"],"items"=>$allowed];
            }
        }
        return $filtered;
    }
    
    $filteredSections = filter_sidebar_by_permissions($sidebarSections);
    
    echo "=== SIDEBAR FILTERING RESULTS ===\n\n";
    
    echo "Original sections: " . count($sidebarSections) . "\n";
    echo "Filtered sections: " . count($filteredSections) . "\n\n";
    
    foreach ($filteredSections as $section) {
        echo "📂 " . $section['label'] . " (" . count($section['items']) . " items)\n";
        foreach ($section['items'] as $item) {
            $permission = $item['permission'] ?? 'none';
            echo "   ✅ " . $item['label'] . " (permission: {$permission})\n";
        }
        echo "\n";
    }
    
    echo "=== BLOCKED ITEMS ===\n\n";
    
    $blocked_count = 0;
    foreach ($sidebarSections as $section) {
        foreach ($section['items'] as $item) {
            $perm = $item['permission'] ?? null;
            if ($perm && !has_permission($perm)) {
                echo "   ❌ " . $item['label'] . " (permission: {$perm})\n";
                $blocked_count++;
            }
        }
    }
    
    echo "\n📊 Summary:\n";
    echo "   Total items: " . array_sum(array_map(fn($s) => count($s['items']), $sidebarSections)) . "\n";
    echo "   Allowed items: " . array_sum(array_map(fn($s) => count($s['items']), $filteredSections)) . "\n";
    echo "   Blocked items: {$blocked_count}\n";
    
    if ($blocked_count > 0) {
        echo "\n🎉 SUCCESS: Sidebar permission filtering is working!\n";
        echo "   Cashier can only see features they have permission to access.\n";
    } else {
        echo "\n⚠️  WARNING: All items are allowed. Check permission assignments.\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}
?>
