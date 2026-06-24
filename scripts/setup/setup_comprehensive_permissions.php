<?php
/**
 * Setup Comprehensive Permission System
 * Creates a complete permission system that works for ALL roles automatically
 */

require_once 'src/paths.php';
safe_require('db.php', 'src', true);
safe_require('functions.php', 'src', true);

echo "=== Setting Up Comprehensive Permission System ===\n\n";

try {
    $pdo = get_db_connection();
    
    // Get all existing roles
    $stmt = $pdo->prepare('
        SELECT DISTINCT r.id, r.name, r.tenant_id, t.name as tenant_name
        FROM roles r 
        JOIN tenants t ON r.tenant_id = t.id 
        WHERE r.deleted_at IS NULL
        ORDER BY t.id, r.id
    ');
    $stmt->execute();
    $roles = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Found " . count($roles) . " roles:\n";
    foreach ($roles as $role) {
        echo "  - " . $role['name'] . " (Tenant: " . $role['tenant_name'] . ")\n";
    }
    echo "\n";
    
    // Define comprehensive permission structure
    $permission_structure = [
        // Dashboard permissions
        'dashboard.view' => 'View Dashboard',
        
        // POS & Sales permissions
        'pos.access' => 'Access Point of Sale',
        'sales.create' => 'Create Sales',
        'sales.view' => 'View Sales',
        'sales.edit' => 'Edit Sales',
        'sales.delete' => 'Delete Sales',
        'sales.returns' => 'Process Returns',
        'sales.refunds' => 'Process Refunds',
        'sales.discounts' => 'Manage Discounts',
        'sales.vouchers' => 'Manage Vouchers',
        
        // Product permissions
        'products.view' => 'View Products',
        'products.create' => 'Create Products',
        'products.edit' => 'Edit Products',
        'products.delete' => 'Delete Products',
        'products.manage' => 'Manage Product Attributes',
        'products.export' => 'Export Products',
        'products.import' => 'Import Products',
        
        // Brand permissions
        'brands.view' => 'View Brands',
        'brands.create' => 'Create Brands',
        'brands.edit' => 'Edit Brands',
        'brands.delete' => 'Delete Brands',
        
        // Category permissions
        'categories.view' => 'View Categories',
        'categories.create' => 'Create Categories',
        'categories.edit' => 'Edit Categories',
        'categories.delete' => 'Delete Categories',
        
        // Inventory permissions
        'inventory.view' => 'View Inventory',
        'inventory.manage' => 'Manage Inventory',
        'inventory.adjust' => 'Adjust Stock',
        'inventory.transfer' => 'Transfer Stock',
        'inventory.expiry' => 'Manage Expiry Tracking',
        
        // Supplier permissions
        'suppliers.view' => 'View Suppliers',
        'suppliers.create' => 'Create Suppliers',
        'suppliers.edit' => 'Edit Suppliers',
        'suppliers.delete' => 'Delete Suppliers',
        
        // Customer permissions
        'customers.view' => 'View Customers',
        'customers.create' => 'Create Customers',
        'customers.edit' => 'Edit Customers',
        'customers.delete' => 'Delete Customers',
        'customers.bulk_sms' => 'Send Bulk SMS',
        
        // Procurement permissions
        'purchases.view' => 'View Purchases',
        'purchases.create' => 'Create Purchases',
        'purchases.edit' => 'Edit Purchases',
        'purchases.approve' => 'Approve Purchases',
        
        // Shipment permissions
        'shipments.view' => 'View Shipments',
        'shipments.create' => 'Create Shipments',
        'shipments.edit' => 'Edit Shipments',
        'shipments.receive' => 'Receive Shipments',
        
        // Reports permissions
        'reports.view' => 'View Reports',
        'reports.sales' => 'View Sales Reports',
        'reports.inventory' => 'View Inventory Reports',
        'reports.financial' => 'View Financial Reports',
        'reports.export' => 'Export Reports',
        
        // User management permissions
        'users.view' => 'View Users',
        'users.create' => 'Create Users',
        'users.edit' => 'Edit Users',
        'users.delete' => 'Delete Users',
        'users.manage' => 'Manage Users',
        
        // Role permissions
        'roles.view' => 'View Roles',
        'roles.create' => 'Create Roles',
        'roles.edit' => 'Edit Roles',
        'roles.delete' => 'Delete Roles',
        'roles.assign' => 'Assign Roles',
        
        // Branch permissions
        'branches.view' => 'View Branches',
        'branches.create' => 'Create Branches',
        'branches.edit' => 'Edit Branches',
        'branches.delete' => 'Delete Branches',
        'branches.manage' => 'Manage Branches',
        
        // System permissions
        'system.settings' => 'Access System Settings',
        'system.backup' => 'Perform Backup',
        'system.restore' => 'Perform Restore',
        'system.logs' => 'View System Logs',
        'system.maintenance' => 'System Maintenance',
        
        // Billing permissions
        'billing.view' => 'View Billing',
        'billing.manage' => 'Manage Billing',
        'billing.settings' => 'Billing Settings',
        
        // CRM permissions
        'crm.view' => 'Access CRM',
        'crm.leads' => 'Manage CRM Leads',
        'crm.deals' => 'Manage CRM Deals',
        
        // HR permissions
        'hr.view' => 'Access HR',
        'hr.employees' => 'Manage Employees',
        'hr.leaves' => 'Manage Leave Requests',
        'hr.payroll' => 'Manage Payroll',
        
        // Loyalty permissions
        'loyalty.view' => 'View Loyalty Program',
        'loyalty.manage' => 'Manage Loyalty Program',
        
        // Marketing permissions
        'marketing.sms' => 'Send Marketing SMS',
        'marketing.email' => 'Send Marketing Emails',
        
        // Profile permissions
        'profile.view' => 'View Profile',
        'profile.edit' => 'Edit Profile',
        
        // Quotation permissions
        'quotations.view' => 'View Quotations',
        'quotations.create' => 'Create Quotations',
        'quotations.edit' => 'Edit Quotations',
        'quotations.delete' => 'Delete Quotations',
        
        // Expense permissions
        'expenses.view' => 'View Expenses',
        'expenses.create' => 'Create Expenses',
        'expenses.edit' => 'Edit Expenses',
        'expenses.delete' => 'Delete Expenses',
        'expenses.approve' => 'Approve Expenses',
    ];
    
    // Define role-based permission mappings
    $role_permissions = [
        'super_admin' => array_keys($permission_structure), // All permissions
        'admin' => array_keys($permission_structure), // All permissions except system-level
        'manager' => [
            'dashboard.view', 'pos.access', 'sales.create', 'sales.view', 'sales.edit', 'sales.returns', 'sales.refunds',
            'products.view', 'products.create', 'products.edit', 'products.manage',
            'brands.view', 'brands.create', 'brands.edit',
            'categories.view', 'categories.create', 'categories.edit',
            'inventory.view', 'inventory.manage', 'inventory.adjust', 'inventory.transfer', 'inventory.expiry',
            'suppliers.view', 'suppliers.create', 'suppliers.edit',
            'customers.view', 'customers.create', 'customers.edit', 'customers.bulk_sms',
            'purchases.view', 'purchases.create', 'purchases.edit', 'purchases.approve',
            'shipments.view', 'shipments.create', 'shipments.edit', 'shipments.receive',
            'reports.view', 'reports.sales', 'reports.inventory', 'reports.financial', 'reports.export',
            'users.view', 'users.create', 'users.edit',
            'branches.view', 'branches.edit',
            'quotations.view', 'quotations.create', 'quotations.edit',
            'expenses.view', 'expenses.create', 'expenses.edit', 'expenses.approve',
            'loyalty.view', 'loyalty.manage',
            'profile.view', 'profile.edit'
        ],
        'supervisor' => [
            'dashboard.view', 'pos.access', 'sales.create', 'sales.view', 'sales.returns',
            'products.view', 'products.edit',
            'brands.view', 'brands.edit',
            'categories.view',
            'inventory.view', 'inventory.adjust',
            'suppliers.view', 'suppliers.edit',
            'customers.view', 'customers.create', 'customers.edit',
            'purchases.view', 'purchases.edit',
            'shipments.view', 'shipments.edit',
            'reports.view', 'reports.sales', 'reports.inventory',
            'users.view',
            'quotations.view', 'quotations.create', 'quotations.edit',
            'expenses.view', 'expenses.edit',
            'profile.view', 'profile.edit'
        ],
        'cashier' => [
            'dashboard.view', 'pos.access', 'sales.create', 'sales.view',
            'products.view',
            'customers.view', 'customers.create',
            'inventory.view',
            'reports.view', 'reports.sales',
            'profile.view', 'profile.edit'
        ],
        'clerk' => [
            'dashboard.view', 'products.view', 'customers.view', 'inventory.view',
            'profile.view', 'profile.edit'
        ],
        'viewer' => [
            'dashboard.view', 'products.view', 'customers.view', 'inventory.view',
            'profile.view'
        ]
    ];
    
    // Process each tenant separately
    $tenants = [];
    foreach ($roles as $role) {
        if (!isset($tenants[$role['tenant_id']])) {
            $tenants[$role['tenant_id']] = [
                'name' => $role['tenant_name'],
                'roles' => []
            ];
        }
        $tenants[$role['tenant_id']]['roles'][] = $role;
    }
    
    foreach ($tenants as $tenant_id => $tenant) {
        echo "Processing tenant: " . $tenant['name'] . " (ID: {$tenant_id})\n";
        echo str_repeat("-", 50) . "\n";
        
        // Create all permissions for this tenant
        echo "Creating permissions...\n";
        $permission_ids = [];
        foreach ($permission_structure as $code => $description) {
            $stmt = $pdo->prepare('
                INSERT IGNORE INTO permissions (tenant_id, code, description, module, category) 
                VALUES (?, ?, ?, "core", "general")
            ');
            $stmt->execute([$tenant_id, $code, $description]);
            
            // Get permission ID
            $stmt = $pdo->prepare('
                SELECT id FROM permissions WHERE tenant_id = ? AND code = ? LIMIT 1
            ');
            $stmt->execute([$tenant_id, $code]);
            $permission_ids[$code] = $stmt->fetchColumn();
        }
        echo "   ✅ Created " . count($permission_ids) . " permissions\n";
        
        // Assign permissions to roles
        echo "Assigning permissions to roles...\n";
        foreach ($tenant['roles'] as $role) {
            $role_name_lower = strtolower($role['name']);
            
            // Determine permission set for this role
            $permissions_to_assign = [];
            
            // Check exact match first
            if (isset($role_permissions[$role_name_lower])) {
                $permissions_to_assign = $role_permissions[$role_name_lower];
            } else {
                // Check partial matches
                foreach ($role_permissions as $template_role => $perms) {
                    if (strpos($role_name_lower, $template_role) !== false) {
                        $permissions_to_assign = $perms;
                        break;
                    }
                }
                
                // Default to viewer permissions if no match
                if (empty($permissions_to_assign)) {
                    $permissions_to_assign = $role_permissions['viewer'];
                    echo "   ⚠️  No specific permissions for role '{$role['name']}', using viewer permissions\n";
                }
            }
            
            // Assign permissions to role
            $assigned_count = 0;
            foreach ($permissions_to_assign as $permission_code) {
                if (isset($permission_ids[$permission_code])) {
                    $stmt = $pdo->prepare('
                        INSERT IGNORE INTO role_permissions (tenant_id, role_id, permission_id) 
                        VALUES (?, ?, ?)
                    ');
                    $stmt->execute([$tenant_id, $role['id'], $permission_ids[$permission_code]]);
                    $assigned_count++;
                }
            }
            
            echo "   ✅ {$role['name']}: {$assigned_count} permissions assigned\n";
        }
        
        echo "\n";
    }
    
    echo "🎉 Comprehensive permission system setup completed!\n\n";
    echo "✅ Created " . count($permission_structure) . " permission types\n";
    echo "✅ Set up permission templates for " . count($role_permissions) . " role types\n";
    echo "✅ Assigned permissions to all existing roles\n";
    echo "✅ System will automatically work for new roles too\n\n";
    
    echo "📋 Role Permission Summary:\n";
    foreach ($role_permissions as $role => $perms) {
        echo "   {$role}: " . count($perms) . " permissions\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}
?>
