<?php
require_once 'src/paths.php';
safe_require('db.php', 'src', true);
safe_require('functions.php', 'src', true);

try {
    $pdo = get_db_connection();
    
    // Check for existing users and their roles
    $stmt = $pdo->prepare('
        SELECT u.id, u.username, u.email, r.name as role_name, t.name as tenant_name, t.subdomain
        FROM users u 
        LEFT JOIN roles r ON u.role_id = r.id AND u.tenant_id = r.tenant_id
        LEFT JOIN tenants t ON u.tenant_id = t.id 
        WHERE u.deleted_at IS NULL AND u.status = 1
        ORDER BY t.id, u.id
        LIMIT 10
    ');
    $stmt->execute();
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Existing Users:\n";
    echo "ID\tUsername\t\tRole\t\tTenant\n";
    echo "-\t--------\t\t----\t\t------\n";
    foreach ($users as $user) {
        $role = $user['role_name'] ?? 'Unknown';
        echo $user['id'] . "\t" . substr($user['username'], 0, 15) . "\t\t" . substr($role, 0, 15) . "\t" . ($user['tenant_name'] ?? 'No Tenant') . "\n";
    }
    
    // Check if we have a cashier user
    $cashier_found = false;
    foreach ($users as $user) {
        $role = strtolower($user['role_name'] ?? '');
        if (strpos($role, 'cashier') !== false) {
            echo "\nFound cashier user: " . $user['username'] . " (ID: " . $user['id'] . ")\n";
            $cashier_found = true;
            break;
        }
    }
    
    if (!$cashier_found) {
        echo "\nNo cashier user found. Creating test cashier user...\n";
        
        // Get first tenant
        $stmt = $pdo->prepare('SELECT id, subdomain FROM tenants WHERE deleted_at IS NULL AND status = "active" LIMIT 1');
        $stmt->execute();
        $tenant = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($tenant) {
            // Create cashier role if not exists
            $stmt = $pdo->prepare('
                INSERT IGNORE INTO roles (tenant_id, name, description, created_at, updated_at) 
                VALUES (?, "cashier", "Cashier Role", NOW(), NOW())
            ');
            $stmt->execute([$tenant['id']]);
            $role_id = $pdo->lastInsertId();
            
            if (!$role_id) {
                $stmt = $pdo->prepare('SELECT id FROM roles WHERE tenant_id = ? AND name = "cashier" LIMIT 1');
                $stmt->execute([$tenant['id']]);
                $role_id = $stmt->fetchColumn();
            }
            
            // Create cashier user
            $password_hash = password_hash('cashier123', PASSWORD_DEFAULT);
            $stmt = $pdo->prepare('
                INSERT INTO users (tenant_id, role_id, username, email, password_hash, name, status, created_at, updated_at) 
                VALUES (?, ?, "cashier", "cashier@test.com", ?, "Test Cashier", 1, NOW(), NOW())
            ');
            $stmt->execute([$tenant['id'], $role_id, $password_hash]);
            
            echo "Created test cashier user:\n";
            echo "Username: cashier\n";
            echo "Password: cashier123\n";
            echo "Tenant: " . $tenant['subdomain'] . "\n";
        }
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
