<?php
/**
 * Seed Default Users Script
 * 
 * Creates:
 * - Super Admin for tenant/billing management
 * - Default Tenant with admin user for testing
 * 
 * Usage: php scripts/seed_default_users.php
 */

define('DB_HOST', 'localhost');
define('DB_NAME', 'jdh_pos');
define('DB_USER', 'root');
define('DB_PASS', '');

echo "=== JDH POS Default Users Setup ===\n\n";

try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME,
        DB_USER,
        DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    
    echo "✓ Connected to database\n\n";
    
    // ==========================================
    // 1. CREATE SUPER ADMIN
    // ==========================================
    echo "--- Creating Super Admin ---\n";
    
    // Check if admin_roles table has data
    $stmt = $pdo->query("SELECT COUNT(*) FROM admin_roles");
    $adminRolesCount = $stmt->fetchColumn();
    
    if ($adminRolesCount == 0) {
        // Create admin roles
        $pdo->exec("INSERT INTO admin_roles (name, slug, description, permissions, is_system, is_owner_role) VALUES
            ('Super Admin', 'super_admin', 'Full system access', '{\"*\": true}', 1, 1),
            ('Admin', 'admin', 'Administrative access', '{\"tenants\": [\"view\", \"manage\"], \"billing\": [\"view\", \"manage\"]}', 1, 0),
            ('Support', 'support', 'Support staff access', '{\"tenants\": [\"view\"], \"support\": [\"access\"]}', 1, 0)
        ");
        echo "  ✓ Created admin roles\n";
    }
    
    // Check if super admin exists
    $stmt = $pdo->prepare("SELECT id FROM admins WHERE username = ?");
    $stmt->execute(['superadmin']);
    $superAdminExists = $stmt->fetch();
    
    if (!$superAdminExists) {
        // Create super admin user (password: admin123)
        $passwordHash = password_hash('admin123', PASSWORD_DEFAULT);
        
        $stmt = $pdo->prepare("INSERT INTO admins (username, email, password_hash, name, role, status, created_at) 
            VALUES (?, ?, ?, ?, 'super_admin', 'active', NOW())");
        $stmt->execute([
            'superadmin',
            'admin@jakababa.com',
            $passwordHash,
            'System Administrator'
        ]);
        
        echo "  ✓ Created Super Admin user\n";
        echo "     Username: superadmin\n";
        echo "     Password: admin123\n";
        echo "     Email: admin@jakababa.com\n";
        echo "     Role: Super Admin\n";
    } else {
        echo "  ℹ Super Admin already exists\n";
    }
    
    // ==========================================
    // 2. CREATE DEFAULT TENANT
    // ==========================================
    echo "\n--- Creating Default Tenant ---\n";
    
    // Check if default tenant exists
    $stmt = $pdo->prepare("SELECT id FROM tenants WHERE subdomain = ?");
    $stmt->execute(['demo']);
    $tenantExists = $stmt->fetch();
    
    if (!$tenantExists) {
        // Create default tenant
        $stmt = $pdo->prepare("INSERT INTO tenants 
            (uuid, subdomain, name, email, business_type, status, is_active, is_verified, currency, timezone, tax_rate, created_at) 
            VALUES (UUID(), ?, ?, ?, 'retail', 'active', 1, 1, 'KES', 'Africa/Nairobi', 16.00, NOW())");
        $stmt->execute([
            'demo',
            'Demo Store',
            'demo@jakababa.com'
        ]);
        
        $tenantId = $pdo->lastInsertId();
        echo "  ✓ Created Tenant: Demo Store\n";
        echo "     Subdomain: demo\n";
        echo "     Tenant ID: {$tenantId}\n";
        
        // Create tenant subscription
        $stmt = $pdo->prepare("INSERT INTO tenant_subscriptions 
            (tenant_id, plan_id, status, billing_cycle, amount, currency, current_period_start, current_period_end)
            VALUES (?, 4, 'active', 'monthly', 4999.00, 'KES', NOW(), DATE_ADD(NOW(), INTERVAL 1 MONTH))");
        $stmt->execute([$tenantId]);
        echo "  ✓ Created subscription (Enterprise plan)\n";
        
    } else {
        $tenantId = $tenantExists['id'];
        echo "  ℹ Tenant 'demo' already exists (ID: {$tenantId})\n";
    }
    
    // ==========================================
    // 3. CREATE TENANT ADMIN USER
    // ==========================================
    echo "\n--- Creating Tenant Admin User ---\n";
    
    // Check if roles exist for tenant
    $stmt = $pdo->prepare("SELECT id FROM roles WHERE name = ? AND tenant_id IS NULL");
    $stmt->execute(['Administrator']);
    $roleExists = $stmt->fetch();
    
    if (!$roleExists) {
        // Create system roles
        $pdo->exec("INSERT INTO roles (name, description, is_system) VALUES
            ('Administrator', 'Full tenant access', 1),
            ('Manager', 'Manage operations', 1),
            ('Cashier', 'Process sales', 1),
            ('Inventory', 'Manage inventory', 1)
        ");
        echo "  ✓ Created system roles\n";
    }
    
    // Get Administrator role ID
    $stmt = $pdo->query("SELECT id FROM roles WHERE name = 'Administrator' LIMIT 1");
    $adminRoleId = $stmt->fetchColumn();
    
    // Check if tenant admin exists
    $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? AND tenant_id = ?");
    $stmt->execute(['admin', $tenantId]);
    $tenantAdminExists = $stmt->fetch();
    
    if (!$tenantAdminExists) {
        // Create tenant admin user (password: admin123)
        $passwordHash = password_hash('admin123', PASSWORD_DEFAULT);
        
        $stmt = $pdo->prepare("INSERT INTO users 
            (tenant_id, name, email, username, role_id, password_hash, status, branch_id, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, 1, 1, NOW())");
        $stmt->execute([
            $tenantId,
            'Store Admin',
            'admin@demostore.com',
            'admin',
            $adminRoleId,
            $passwordHash
        ]);
        
        $userId = $pdo->lastInsertId();
        echo "  ✓ Created Tenant Admin user\n";
        echo "     Username: admin\n";
        echo "     Password: admin123\n";
        echo "     Email: admin@demostore.com\n";
        echo "     Role: Administrator\n";
        echo "     Branch: 1 (Main Branch)\n";
        
    } else {
        echo "  ℹ Tenant admin already exists\n";
    }
    
    // ==========================================
    // 4. CREATE DEFAULT BRANCH
    // ==========================================
    echo "\n--- Creating Default Branch ---\n";
    
    $stmt = $pdo->prepare("SELECT id FROM branches WHERE tenant_id = ? AND code = ?");
    $stmt->execute([$tenantId, 'MAIN']);
    $branchExists = $stmt->fetch();
    
    if (!$branchExists) {
        $stmt = $pdo->prepare("INSERT INTO branches 
            (tenant_id, name, code, address, phone, active, created_at) 
            VALUES (?, 'Main Branch', 'MAIN', '123 Main Street', '+254700000000', 1, NOW())");
        $stmt->execute([$tenantId]);
        
        $branchId = $pdo->lastInsertId();
        echo "  ✓ Created Main Branch (ID: {$branchId})\n";
    } else {
        echo "  ℹ Main branch already exists\n";
    }
    
    // ==========================================
    // SUMMARY
    // ==========================================
    echo "\n========================================\n";
    echo "    LOGIN CREDENTIALS\n";
    echo "========================================\n\n";
    
    echo "🔴 SUPER ADMIN (System Management)\n";
    echo "   URL: http://localhost/JDH_POS/admin/login.php\n";
    echo "   Username: superadmin\n";
    echo "   Password: admin123\n";
    echo "   Access: Manage all tenants, billing, plans\n\n";
    
    echo "🟢 TENANT ADMIN (POS System)\n";
    echo "   URL: http://localhost/JDH_POS/public/auth/login.php\n";
    echo "   Tenant Code: demo\n";
    echo "   Username: admin\n";
    echo "   Password: admin123\n";
    echo "   Access: POS, inventory, sales, reports\n\n";
    
    echo "⚠️  IMPORTANT: Change these passwords in production!\n";
    
} catch (PDOException $e) {
    die("\nERROR: " . $e->getMessage() . "\n");
}
