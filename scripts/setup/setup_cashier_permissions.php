<?php
/**
 * Setup Cashier Permissions
 * Creates and assigns appropriate permissions for the cashier role
 */

require_once 'src/paths.php';
safe_require('db.php', 'src', true);
safe_require('functions.php', 'src', true);
require_once 'src/Security/SecurityBootstrap.php';

echo "=== Setting Up Cashier Permissions ===\n\n";

// Initialize security system
SecurityBootstrap::initialize();

try {
    $pdo = get_db_connection();
    
    // Get cashier role and tenant
    $stmt = $pdo->prepare('
        SELECT u.id, u.tenant_id, r.id as role_id, r.name as role_name
        FROM users u 
        JOIN roles r ON u.role_id = r.id AND u.tenant_id = r.tenant_id
        WHERE u.username = "Wycliffe" AND u.deleted_at IS NULL AND u.status = 1
        LIMIT 1
    ');
    $stmt->execute();
    $cashier_data = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$cashier_data) {
        echo "❌ Cashier user not found\n";
        exit;
    }
    
    echo "✅ Found cashier role: " . $cashier_data['role_name'] . " (ID: " . $cashier_data['role_id'] . ")\n";
    echo "   Tenant ID: " . $cashier_data['tenant_id'] . "\n\n";
    
    // Define cashier permissions
    $cashier_permissions = [
        // Core permissions
        'dashboard.view' => 'View Dashboard',
        'pos.access' => 'Access Point of Sale',
        'sales.create' => 'Create Sales',
        'sales.view' => 'View Sales',
        
        // Product permissions (view only)
        'products.view' => 'View Products',
        
        // Customer permissions
        'customers.view' => 'View Customers',
        'customers.create' => 'Create Customers',
        
        // Basic inventory permissions
        'inventory.view' => 'View Inventory',
        
        // Reports (basic)
        'reports.view' => 'View Reports',
        
        // Basic permissions
        'profile.view' => 'View Profile',
        'profile.edit' => 'Edit Profile'
    ];
    
    echo "Creating cashier permissions...\n";
    
    // Create permissions
    foreach ($cashier_permissions as $code => $description) {
        $stmt = $pdo->prepare('
            INSERT IGNORE INTO permissions (tenant_id, code, description, module, category) 
            VALUES (?, ?, ?, "core", "general")
        ');
        $stmt->execute([$cashier_data['tenant_id'], $code, $description]);
        
        // Get permission ID
        $stmt = $pdo->prepare('
            SELECT id FROM permissions WHERE tenant_id = ? AND code = ? LIMIT 1
        ');
        $stmt->execute([$cashier_data['tenant_id'], $code]);
        $permission_id = $stmt->fetchColumn();
        
        if ($permission_id) {
            // Assign permission to cashier role
            $stmt = $pdo->prepare('
                INSERT IGNORE INTO role_permissions (tenant_id, role_id, permission_id) 
                VALUES (?, ?, ?)
            ');
            $stmt->execute([$cashier_data['tenant_id'], $cashier_data['role_id'], $permission_id]);
            
            echo "   ✅ {$code}\n";
        } else {
            echo "   ❌ Failed to create {$code}\n";
        }
    }
    
    // Create user role assignment if not exists
    $stmt = $pdo->prepare('
        INSERT IGNORE INTO user_roles (tenant_id, user_id, role_id) 
        VALUES (?, ?, ?)
    ');
    $stmt->execute([$cashier_data['tenant_id'], $cashier_data['id'], $cashier_data['role_id']]);
    
    echo "\n✅ Cashier permissions setup completed!\n";
    echo "\nCashier can now access:\n";
    echo "- Dashboard\n";
    echo "- Point of Sale\n";
    echo "- Sales Management\n";
    echo "- Product View (read-only)\n";
    echo "- Customer Management\n";
    echo "- Basic Inventory View\n";
    echo "- Basic Reports\n";
    echo "- Profile Management\n";
    echo "\n❌ Cashier CANNOT access:\n";
    echo "- User Management\n";
    echo "- Role & Permissions\n";
    echo "- System Settings\n";
    echo "- Backup & Restore\n";
    echo "- Advanced Inventory Management\n";
    echo "- Procurement\n";
    echo "- Billing Settings\n";
    echo "- HR Management\n";
    echo "- CRM Features\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}
?>
