<?php
require_once 'src/db.php';

try {
    $pdo = get_db_connection();
    
    // Get Administrator role info
    $stmt = $pdo->query("SELECT id, tenant_id FROM roles WHERE name = 'Administrator' AND deleted_at IS NULL LIMIT 1");
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($admin) {
        echo "Administrator role found: ID " . $admin['id'] . "\n";
        
        // Delete existing permissions
        $pdo->prepare("DELETE FROM role_permissions WHERE role_id = ? AND tenant_id = ?")->execute([$admin['id'], $admin['tenant_id']]);
        
        // Get all permission IDs
        $stmt = $pdo->prepare("SELECT id FROM permissions WHERE tenant_id = ? AND deleted_at IS NULL");
        $stmt->execute([$admin['tenant_id']]);
        $perm_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        // Insert all permissions
        $insert = $pdo->prepare("INSERT INTO role_permissions (tenant_id, role_id, permission_id) VALUES (?, ?, ?)");
        foreach ($perm_ids as $perm_id) {
            $insert->execute([$admin['tenant_id'], $admin['id'], $perm_id]);
        }
        
        // Set super admin flag
        $pdo->prepare("UPDATE users SET is_super_admin = 1 WHERE role_id = ? AND tenant_id = ?")->execute([$admin['id'], $admin['tenant_id']]);
        
        echo "SUCCESS: Administrator now has " . count($perm_ids) . " permissions and super admin access\n";
    } else {
        echo "ERROR: Administrator role not found\n";
    }
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
?>
