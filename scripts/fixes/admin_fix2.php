<?php
require_once 'src/db.php';

try {
    $pdo = get_db_connection();
    
    // Get Administrator role info
    $stmt = $pdo->query("SELECT id, tenant_id FROM roles WHERE name = 'Administrator' AND deleted_at IS NULL LIMIT 1");
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($admin) {
        echo "Administrator role found: ID " . $admin['id'] . "\n";
        
        // Get existing permission IDs for Administrator
        $stmt = $pdo->prepare("SELECT permission_id FROM role_permissions WHERE role_id = ? AND tenant_id = ?");
        $stmt->execute([$admin['id'], $admin['tenant_id']]);
        $existing_perms = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        echo "Existing permissions: " . count($existing_perms) . "\n";
        
        // Get all permission IDs
        $stmt = $pdo->prepare("SELECT id FROM permissions WHERE tenant_id = ? AND deleted_at IS NULL");
        $stmt->execute([$admin['tenant_id']]);
        $all_perm_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        echo "Total available permissions: " . count($all_perm_ids) . "\n";
        
        // Find missing permissions
        $missing_perms = array_diff($all_perm_ids, $existing_perms);
        
        echo "Missing permissions to add: " . count($missing_perms) . "\n";
        
        // Insert missing permissions using INSERT IGNORE
        $insert = $pdo->prepare("INSERT IGNORE INTO role_permissions (tenant_id, role_id, permission_id) VALUES (?, ?, ?)");
        $count = 0;
        foreach ($missing_perms as $perm_id) {
            $insert->execute([$admin['tenant_id'], $admin['id'], $perm_id]);
            $count++;
        }
        
        echo "Added {$count} new permissions\n";
        
        // Set super admin flag
        $pdo->prepare("UPDATE users SET is_super_admin = 1 WHERE role_id = ? AND tenant_id = ?")->execute([$admin['id'], $admin['tenant_id']]);
        
        // Verify final count
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM role_permissions WHERE role_id = ? AND tenant_id = ?");
        $stmt->execute([$admin['id'], $admin['tenant_id']]);
        $final_count = $stmt->fetchColumn();
        
        echo "\nSUCCESS: Administrator now has " . $final_count . " permissions\n";
        echo "Super admin flag set for all Administrator users\n";
        
        if ($final_count == count($all_perm_ids)) {
            echo "Administrator has FULL ACCESS to all system features!\n";
        } else {
            echo "Note: Administrator has " . $final_count . " of " . count($all_perm_ids) . " possible permissions\n";
        }
    } else {
        echo "ERROR: Administrator role not found\n";
    }
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
?>
