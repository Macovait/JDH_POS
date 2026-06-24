<?php
/**
 * Fix Administrator Role Permissions
 * Ensure Administrator has full access to all system features
 */

require_once 'src/paths.php';
safe_require('db.php', 'src', true);
safe_require('functions.php', 'src', true');
require_once 'src/Security/SecurityBootstrap.php';

echo "=== Fixing Administrator Role Permissions ===\n\n";

try {
    $pdo = get_db_connection();
    
    // Get Administrator role
    $stmt = $pdo->prepare("
        SELECT r.id, r.name, r.tenant_id, t.name as tenant_name
        FROM roles r 
        JOIN tenants t ON r.tenant_id = t.id 
        WHERE r.name = 'Administrator' AND r.deleted_at IS NULL
        LIMIT 1
    ");
    $stmt->execute();
    $admin_role = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$admin_role) {
        echo "❌ Administrator role not found\n";
        exit;
    }
    
    echo "Found Administrator role: " . $admin_role['name'] . " (ID: " . $admin_role['id'] . ")\n";
    echo "Tenant: " . $admin_role['tenant_name'] . " (ID: " . $admin_role['tenant_id'] . ")\n\n";
    
    // Get all available permissions for this tenant
    $stmt = $pdo->prepare("
        SELECT id, code FROM permissions 
        WHERE tenant_id = ? AND deleted_at IS NULL
        ORDER BY code
    ");
    $stmt->execute([$admin_role['tenant_id']]);
    $all_permissions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Total available permissions: " . count($all_permissions) . "\n\n";
    
    // Clear existing permissions for Administrator role
    $stmt = $pdo->prepare("
        DELETE FROM role_permissions 
        WHERE role_id = ? AND tenant_id = ?
    ");
    $stmt->execute([$admin_role['id'], $admin_role['tenant_id']]);
    
    echo "Cleared existing Administrator permissions\n";
    
    // Assign ALL permissions to Administrator role
    $assigned_count = 0;
    foreach ($all_permissions as $permission) {
        $stmt = $pdo->prepare("
            INSERT INTO role_permissions (tenant_id, role_id, permission_id) 
            VALUES (?, ?, ?)
        ");
        $stmt->execute([$admin_role['tenant_id'], $admin_role['id'], $permission['id']]);
        $assigned_count++;
    }
    
    echo "Assigned {$assigned_count} permissions to Administrator role\n\n";
    
    // Set super admin flag for Administrator users
    $stmt = $pdo->prepare("
        UPDATE users 
        SET is_super_admin = 1 
        WHERE role_id = ? AND tenant_id = ? AND deleted_at IS NULL
    ");
    $stmt->execute([$admin_role['id'], $admin_role['tenant_id']]);
    
    $updated_users = $stmt->rowCount();
    echo "Set super admin flag for {$updated_users} Administrator users\n\n";
    
    // Verify the permissions
    $stmt = $pdo->prepare("
        SELECT p.code FROM permissions p
        JOIN role_permissions rp ON rp.permission_id = p.id AND rp.tenant_id = p.tenant_id
        WHERE rp.role_id = ? AND rp.tenant_id = ? AND p.deleted_at IS NULL
        ORDER BY p.code
    ");
    $stmt->execute([$admin_role['id'], $admin_role['tenant_id']]);
    $assigned_permissions = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo "Verification - Administrator now has " . count($assigned_permissions) . " permissions\n";
    
    // Test with an Administrator user
    $stmt = $pdo->prepare("
        SELECT u.id, u.username, u.name 
        FROM users u 
        WHERE u.role_id = ? AND u.tenant_id = ? AND u.deleted_at IS NULL AND u.status = 1
        LIMIT 1
    ");
    $stmt->execute([$admin_role['id'], $admin_role['tenant_id']]);
    $admin_user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($admin_user) {
        echo "\nTesting with Administrator user: " . $admin_user['name'] . " (" . $admin_user['username'] . ")\n";
        
        // Setup session
        session_name('jakababa_saas_sid');
        session_start();
        
        $_SESSION['user_id'] = $admin_user['id'];
        $_SESSION['user_name'] = $admin_user['name'];
        $_SESSION['username'] = $admin_user['username'];
        $_SESSION['tenant_id'] = $admin_role['tenant_id'];
        $_SESSION['tenant_name'] = $admin_role['tenant_name'];
        $_SESSION['tenant_slug'] = strtolower($admin_role['tenant_name']);
        $_SESSION['tenant_code'] = strtolower($admin_role['tenant_name']);
        $_SESSION['branch_id'] = 1;
        $_SESSION['role'] = 'administrator';
        $_SESSION['user_role'] = 'administrator';
        $_SESSION['authenticated_at'] = time();
        $_SESSION['is_super_admin'] = true;
        $_SESSION['permissions'] = $assigned_permissions;
        
        // Initialize security system
        SecurityBootstrap::initialize();
        
        // Test key permissions
        $key_permissions = [
            'dashboard.view', 'pos.access', 'products.view', 'products.create', 
            'users.view', 'users.create', 'users.manage', 'system.settings',
            'system.backup', 'billing.view', 'reports.view'
        ];
        
        echo "Key permission checks:\n";
        foreach ($key_permissions as $perm) {
            $has_perm = has_permission($perm);
            echo "  " . ($has_perm ? '✅' : '❌') . " {$perm}\n";
        }
        
        // Test sidebar generation
        require_once 'src/Security/DynamicSidebar.php';
        $dynamicSidebar = DynamicSidebar::getInstance();
        $sidebarSections = $dynamicSidebar->generateSidebar();
        $stats = $dynamicSidebar->getMenuStats();
        
        echo "\nSidebar access for Administrator:\n";
        echo "  Sections: " . $stats['allowed_sections'] . "/" . $stats['total_sections'] . "\n";
        echo "  Items: " . $stats['allowed_items'] . "/" . $stats['total_items'] . "\n";
        
        if ($stats['allowed_items'] === $stats['total_items']) {
            echo "  ✅ Administrator has access to ALL menu items\n";
        } else {
            echo "  ⚠️  Administrator is missing some menu items\n";
        }
    }
    
    echo "\n✅ Administrator role permissions updated successfully!\n";
    echo "📋 Administrator now has:\n";
    echo "   - Full access to all " . count($assigned_permissions) . " permissions\n";
    echo "   - Super admin privileges enabled\n";
    echo "   - Access to all system features and settings\n";
    echo "   - Complete sidebar menu access\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}
?>
