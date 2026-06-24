<?php
require_once 'src/paths.php';
safe_require('db.php', 'src', true);
safe_require('functions.php', 'src', true');

echo "=== Fixing Administrator Permissions ===\n";

try {
    $pdo = get_db_connection();
    
    // Get Administrator role
    $stmt = $pdo->prepare("SELECT id, tenant_id FROM roles WHERE name = 'Administrator' AND deleted_at IS NULL LIMIT 1");
    $stmt->execute();
    $admin_role = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$admin_role) {
        echo "Administrator role not found\n";
        exit;
    }
    
    echo "Found Administrator role ID: " . $admin_role['id'] . "\n";
    
    // Get all permissions
    $stmt = $pdo->prepare("SELECT id FROM permissions WHERE tenant_id = ? AND deleted_at IS NULL");
    $stmt->execute([$admin_role['tenant_id']]);
    $permissions = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo "Found " . count($permissions) . " permissions\n";
    
    // Clear existing permissions
    $stmt = $pdo->prepare("DELETE FROM role_permissions WHERE role_id = ? AND tenant_id = ?");
    $stmt->execute([$admin_role['id'], $admin_role['tenant_id']]);
    
    // Assign all permissions
    $count = 0;
    foreach ($permissions as $perm_id) {
        $stmt = $pdo->prepare("INSERT INTO role_permissions (tenant_id, role_id, permission_id) VALUES (?, ?, ?)");
        $stmt->execute([$admin_role['tenant_id'], $admin_role['id'], $perm_id]);
        $count++;
    }
    
    echo "Assigned {$count} permissions to Administrator\n";
    
    // Set super admin flag
    $stmt = $pdo->prepare("UPDATE users SET is_super_admin = 1 WHERE role_id = ? AND tenant_id = ?");
    $stmt->execute([$admin_role['id'], $admin_role['tenant_id']]);
    
    $users = $stmt->rowCount();
    echo "Set super admin flag for {$users} users\n";
    
    echo "\nAdministrator now has FULL ACCESS!\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
