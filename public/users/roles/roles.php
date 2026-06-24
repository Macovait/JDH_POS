<?php
/**
 * Role Management - Complete Feature Set
 * Slim & Compact Design - All role management features included
 * Fixed: Proper label associations, accessibility improvements
 * Updated: All 16 roles with proper permissions mapping
 */

$page_title = 'Role Management';

$pathsFile = __DIR__ . '/../../src/paths.php';
if (!file_exists($pathsFile)) {
    $pathsFile = dirname(__DIR__, 3) . '/src/paths.php';
}
require_once $pathsFile;

safe_require('auth.php', 'src', true);
safe_require('db.php', 'src', true);
safe_require('functions.php', 'src', true);
require_login();

// Branch filter for multi-tenant isolation
$current_branch_id = get_current_branch_id();
$pdo = get_db_connection();

$user_id   = function_exists('get_current_user_id')   ? get_current_user_id()   : (int)($_SESSION['user_id'] ?? $_SESSION['user']['id'] ?? 0);
$user_name = function_exists('get_current_user_name') ? htmlspecialchars(get_current_user_name()) : htmlspecialchars($_SESSION['username'] ?? $_SESSION['user']['name'] ?? 'User');
$user_role = $_SESSION['role'] ?? $_SESSION['role_name'] ?? $_SESSION['user']['role'] ?? '';
$branch_id = function_exists('get_current_branch_id') ? (int)get_current_branch_id() : (int)($_SESSION['branch_id'] ?? $_SESSION['user']['branch_id'] ?? 1);
$tenant_id = function_exists('get_current_tenant_id') ? (int)get_current_tenant_id() : (int)($_SESSION['tenant_id'] ?? 0);
$is_super_admin = is_super_admin();

if (!is_super_admin() && !check_permission('roles.manage')) {
    enforce_permission('roles.manage');
}

$csrf_token = generate_csrf_token();

// ============================================================================
// COMPLETE ROLE DEFINITIONS (All 16 Roles)
// ============================================================================
$role_definitions = [
    // System-Level Roles (Platform)
    'super_admin' => [
        'name' => 'Super Admin',
        'description' => 'Full platform access - manage all tenants, system settings',
        'level' => 10,
        'is_system' => 1,
        'permissions' => 'all',
        'color' => 'red'
    ],
    'developer' => [
        'name' => 'Developer',
        'description' => 'System maintenance, debugging, code access',
        'level' => 9,
        'is_system' => 1,
        'permissions' => 'all',
        'color' => 'purple'
    ],
    'support' => [
        'name' => 'Support',
        'description' => 'Platform support, view all tenants, resolve tickets',
        'level' => 8,
        'is_system' => 1,
        'permissions' => 'support',
        'color' => 'blue'
    ],
    
    // Tenant-Level Roles (Company)
    'owner' => [
        'name' => 'Owner',
        'description' => 'Full company access, billing, subscription management',
        'level' => 10,
        'is_system' => 0,
        'permissions' => 'all',
        'color' => 'amber'
    ],
    'administrator' => [
        'name' => 'Administrator',
        'description' => 'Full company management - all modules',
        'level' => 9,
        'is_system' => 0,
        'permissions' => 'all',
        'color' => 'purple'
    ],
    'manager' => [
        'name' => 'Manager',
        'description' => 'Operational management - staff, sales, inventory',
        'level' => 8,
        'is_system' => 0,
        'permissions' => 'manager',
        'color' => 'blue'
    ],
    'accountant' => [
        'name' => 'Accountant',
        'description' => 'Financial operations, reports, expenses, billing',
        'level' => 7,
        'is_system' => 0,
        'permissions' => 'accountant',
        'color' => 'teal'
    ],
    'inventory_manager' => [
        'name' => 'Inventory Manager',
        'description' => 'Full inventory control, stock management, transfers',
        'level' => 6,
        'is_system' => 0,
        'permissions' => 'inventory_manager',
        'color' => 'green'
    ],
    'hr_manager' => [
        'name' => 'HR Manager',
        'description' => 'Employee management, leave requests, payroll',
        'level' => 6,
        'is_system' => 0,
        'permissions' => 'hr_manager',
        'color' => 'pink'
    ],
    'senior_cashier' => [
        'name' => 'Senior Cashier',
        'description' => 'All POS operations, basic manager duties, refunds',
        'level' => 5,
        'is_system' => 0,
        'permissions' => 'senior_cashier',
        'color' => 'emerald'
    ],
    'cashier' => [
        'name' => 'Cashier',
        'description' => 'POS operations - sales processing only',
        'level' => 4,
        'is_system' => 0,
        'permissions' => 'cashier',
        'color' => 'emerald'
    ],
    'inventory_clerk' => [
        'name' => 'Inventory Clerk',
        'description' => 'Basic inventory tasks, stock counts, receiving',
        'level' => 4,
        'is_system' => 0,
        'permissions' => 'inventory_clerk',
        'color' => 'lime'
    ],
    'customer_service' => [
        'name' => 'Customer Service',
        'description' => 'Customer management, returns, support tickets',
        'level' => 3,
        'is_system' => 0,
        'permissions' => 'customer_service',
        'color' => 'pink'
    ],
    'kitchen_staff' => [
        'name' => 'Kitchen Staff',
        'description' => 'Kitchen display, order tickets, food preparation',
        'level' => 2,
        'is_system' => 0,
        'permissions' => 'kitchen_staff',
        'color' => 'orange'
    ],
    'delivery_rider' => [
        'name' => 'Delivery Rider',
        'description' => 'Delivery management, tracking, order pickup',
        'level' => 2,
        'is_system' => 0,
        'permissions' => 'delivery_rider',
        'color' => 'yellow'
    ],
    'viewer' => [
        'name' => 'Viewer',
        'description' => 'Read-only access - view reports and data',
        'level' => 1,
        'is_system' => 0,
        'permissions' => 'viewer',
        'color' => 'gray'
    ]
];

// ============================================================================
// PERMISSION TEMPLATES (Pre-configured permission sets)
// ============================================================================
$permission_templates = [
    'full_access' => [
        'name' => 'Full Access',
        'description' => 'All permissions enabled',
        'icon' => 'fa-crown',
        'color' => 'amber',
        'permissions' => 'all'
    ],
    'super_admin' => [
        'name' => 'Super Admin',
        'description' => 'Platform-wide access',
        'icon' => 'fa-crown',
        'color' => 'red',
        'permissions' => 'all'
    ],
    'owner' => [
        'name' => 'Owner',
        'description' => 'Company owner access',
        'icon' => 'fa-crown',
        'color' => 'amber',
        'permissions' => 'all'
    ],
    'administrator' => [
        'name' => 'Administrator',
        'description' => 'Full company management',
        'icon' => 'fa-user-shield',
        'color' => 'purple',
        'permissions' => 'all'
    ],
    'manager' => [
        'name' => 'Manager',
        'description' => 'Operational management',
        'icon' => 'fa-user-tie',
        'color' => 'blue',
        'permissions' => [
            'pos.view', 'pos.sell', 'pos.refund', 'pos.discount', 'pos.hold', 'pos.void',
            'sales.view', 'sales.manage', 'sales.returns',
            'products.view', 'products.create', 'products.edit',
            'inventory.view', 'inventory.adjust', 'inventory.transfer',
            'customers.view', 'customers.create', 'customers.edit',
            'users.view', 'users.create', 'users.edit',
            'reports.view', 'reports.sales', 'reports.inventory',
            'expenses.view', 'expenses.create', 'expenses.approve',
            'branches.view'
        ]
    ],
    'accountant' => [
        'name' => 'Accountant',
        'description' => 'Financial operations',
        'icon' => 'fa-calculator',
        'color' => 'teal',
        'permissions' => [
            'sales.view', 'sales.export',
            'reports.view', 'reports.sales', 'reports.financial', 'reports.profit', 'reports.export',
            'expenses.view', 'expenses.create', 'expenses.edit', 'expenses.categories',
            'customers.view', 'customers.credit'
        ]
    ],
    'inventory_manager' => [
        'name' => 'Inventory Manager',
        'description' => 'Full inventory control',
        'icon' => 'fa-boxes',
        'color' => 'green',
        'permissions' => [
            'products.view', 'products.create', 'products.edit', 'products.delete', 'products.import', 'products.export', 'products.categories',
            'inventory.view', 'inventory.adjust', 'inventory.transfer', 'inventory.count', 'inventory.reorder', 'inventory.alerts',
            'purchases.view', 'purchases.create', 'purchases.approve',
            'suppliers.view', 'suppliers.create', 'suppliers.edit',
            'reports.inventory', 'reports.export'
        ]
    ],
    'hr_manager' => [
        'name' => 'HR Manager',
        'description' => 'Employee management',
        'icon' => 'fa-users-cog',
        'color' => 'pink',
        'permissions' => [
            'users.view', 'users.create', 'users.edit', 'users.delete', 'users.roles', 'users.attendance',
            'hr.view', 'hr.employees', 'hr.leaves', 'hr.payroll',
            'reports.view'
        ]
    ],
    'senior_cashier' => [
        'name' => 'Senior Cashier',
        'description' => 'All POS operations + basic management',
        'icon' => 'fa-cash-register',
        'color' => 'emerald',
        'permissions' => [
            'pos.view', 'pos.sell', 'pos.refund', 'pos.discount', 'pos.hold', 'pos.order', 'pos.split',
            'sales.view', 'sales.returns',
            'customers.view', 'customers.create', 'customers.edit', 'customers.loyalty',
            'inventory.view',
            'reports.sales'
        ]
    ],
    'cashier' => [
        'name' => 'Cashier',
        'description' => 'POS sales only - no admin access',
        'icon' => 'fa-cash-register',
        'color' => 'emerald',
        'permissions' => [
            'pos.view', 'pos.sell', 'pos.hold', 'pos.order',
            'customers.view', 'customers.create',
            'sales.view'
        ]
    ],
    'inventory_clerk' => [
        'name' => 'Inventory Clerk',
        'description' => 'Basic inventory tasks',
        'icon' => 'fa-clipboard-list',
        'color' => 'lime',
        'permissions' => [
            'products.view',
            'inventory.view', 'inventory.count',
            'suppliers.view',
            'purchases.view'
        ]
    ],
    'customer_service' => [
        'name' => 'Customer Service',
        'description' => 'Customer management & returns',
        'icon' => 'fa-headset',
        'color' => 'pink',
        'permissions' => [
            'customers.view', 'customers.create', 'customers.edit',
            'sales.view', 'sales.returns',
            'pos.view',
            'reports.view'
        ]
    ],
    'kitchen_staff' => [
        'name' => 'Kitchen Staff',
        'description' => 'Kitchen operations only',
        'icon' => 'fa-fire',
        'color' => 'orange',
        'permissions' => [
            'pos.kitchen', 'pos.view',
            'kitchen.view', 'kitchen.ready'
        ]
    ],
    'delivery_rider' => [
        'name' => 'Delivery Rider',
        'description' => 'Delivery tracking only',
        'icon' => 'fa-motorcycle',
        'color' => 'yellow',
        'permissions' => [
            'delivery.view', 'delivery.update', 'delivery.track',
            'orders.view', 'orders.dispatch'
        ]
    ],
    'viewer' => [
        'name' => 'Viewer',
        'description' => 'Read-only access',
        'icon' => 'fa-eye',
        'color' => 'gray',
        'permissions' => [
            'reports.view', 'reports.sales',
            'sales.view',
            'products.view',
            'customers.view',
            'inventory.view'
        ]
    ],
    'waiter' => [
        'name' => 'Waiter',
        'description' => 'Restaurant orders & tables',
        'icon' => 'fa-utensils',
        'color' => 'orange',
        'permissions' => [
            'pos.view', 'pos.sell', 'pos.order', 'pos.table', 'pos.kitchen',
            'customers.view',
            'sales.view'
        ]
    ],
    'branch_manager' => [
        'name' => 'Branch Manager',
        'description' => 'Branch operations management',
        'icon' => 'fa-building',
        'color' => 'indigo',
        'permissions' => [
            'pos.view', 'pos.sell', 'pos.refund', 'pos.discount', 'pos.hold',
            'sales.view', 'sales.manage', 'sales.returns',
            'products.view', 'products.create', 'products.edit',
            'inventory.view', 'inventory.adjust', 'inventory.transfer',
            'customers.view', 'customers.create', 'customers.edit',
            'staff.view', 'staff.manage',
            'reports.view', 'reports.sales', 'reports.inventory',
            'branches.view', 'branches.manage'
        ]
    ],
    'auditor_read_only' => [
        'name' => 'Auditor (Read-Only)',
        'description' => 'Complete read-only access for auditing',
        'icon' => 'fa-eye',
        'color' => 'gray',
        'permissions' => [
            'sales.view', 'sales.export',
            'products.view',
            'inventory.view',
            'customers.view',
            'reports.view', 'reports.sales', 'reports.inventory', 'reports.financial', 'reports.profit',
            'expenses.view',
            'users.view',
            'audit.view'
        ]
    ]
];

// ============================================================================
// POST HANDLING (from standalone action pages)
// ============================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        die('CSRF token validation failed');
    }
    
    $action = $_POST['action'] ?? '';
    $msg = '';
    
    try {
        $pdo->beginTransaction();
        
        switch ($action) {
            case 'save_role':
                $id = (int)($_POST['id'] ?? 0);
                $name = trim($_POST['name'] ?? '');
                $desc = trim($_POST['description'] ?? '');
                if ($id > 0) {
                    $stmt = $pdo->prepare('UPDATE roles SET name = ?, description = ? WHERE id = ? AND (tenant_id = ? OR tenant_id IS NULL) AND deleted_at IS NULL');
                    $stmt->execute([$name, $desc, $id, $tenant_id]);
                    $msg = 'Role updated successfully';
                } else {
                    $stmt = $pdo->prepare('INSERT INTO roles (name, description, tenant_id, is_system, deleted_at) VALUES (?, ?, ?, 0, NULL)');
                    $stmt->execute([$name, $desc, $tenant_id]);
                    $msg = 'Role created successfully';
                }
                break;
                
            case 'delete':
                $role_id = (int)($_POST['role_id'] ?? 0);
                $stmt = $pdo->prepare('UPDATE roles SET deleted_at = NOW() WHERE id = ? AND (tenant_id = ? OR tenant_id IS NULL) AND deleted_at IS NULL');
                $stmt->execute([$role_id, $tenant_id]);
                $msg = 'Role deleted successfully';
                break;
                
            case 'duplicate':
                $source_id = (int)($_POST['id'] ?? 0);
                $new_name = trim($_POST['new_name'] ?? '');
                $stmt = $pdo->prepare('SELECT * FROM roles WHERE id = ? AND (tenant_id = ? OR tenant_id IS NULL) AND deleted_at IS NULL');
                $stmt->execute([$source_id, $tenant_id]);
                $src = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($src) {
                    $pdo->prepare('INSERT INTO roles (name, description, tenant_id, is_system, deleted_at) VALUES (?, ?, ?, 0, NULL)')->execute([$new_name, $src['description'], $tenant_id]);
                    $new_id = $pdo->lastInsertId();
                    $stmt2 = $pdo->prepare('INSERT INTO role_permissions (role_id, permission_id, tenant_id) SELECT ?, permission_id, ? FROM role_permissions WHERE role_id = ? AND (tenant_id = ? OR tenant_id IS NULL)');
                    $stmt2->execute([$new_id, $tenant_id, $source_id, $tenant_id]);
                    $msg = 'Role duplicated successfully';
                }
                break;
                
            case 'update':
                $role_id = (int)($_POST['id'] ?? 0);
                $perms = $_POST['perms'] ?? [];
                
                $role_check = $pdo->prepare('SELECT id FROM roles WHERE id = ? AND (tenant_id = ? OR tenant_id IS NULL) AND deleted_at IS NULL');
                $role_check->execute([$role_id, $tenant_id]);
                if (!$role_check->fetch()) {
                    throw new Exception('Access denied: Cannot modify permissions for this role');
                }
                
                $pdo->prepare('DELETE FROM role_permissions WHERE role_id = ? AND (tenant_id = ? OR tenant_id IS NULL)')->execute([$role_id, $tenant_id]);
                foreach ($perms as $pid) {
                    $pdo->prepare('INSERT INTO role_permissions (role_id, permission_id, tenant_id) VALUES (?, ?, ?)')->execute([$role_id, $pid, $tenant_id]);
                }
                $msg = 'Permissions saved successfully';
                break;
                
            case 'seed_roles':
                // Seed all 16 roles with proper permissions
                $seeded = 0;
                foreach ($role_definitions as $key => $def) {
                    // Check if role exists
                    $stmt = $pdo->prepare('SELECT id FROM roles WHERE name = ? AND (tenant_id = ? OR tenant_id IS NULL) AND deleted_at IS NULL');
                    $stmt->execute([$def['name'], $tenant_id]);
                    $existing = $stmt->fetch();
                    
                    if (!$existing) {
                        $stmt = $pdo->prepare('INSERT INTO roles (name, description, tenant_id, is_system, deleted_at) VALUES (?, ?, ?, ?, NULL)');
                        $stmt->execute([$def['name'], $def['description'], $tenant_id, $def['is_system']]);
                        $role_id = $pdo->lastInsertId();
                        
                        // Assign permissions based on template
                        $template = $permission_templates[$key] ?? $permission_templates['viewer'];
                        if ($template['permissions'] === 'all') {
                            // All permissions
                            $stmt = $pdo->prepare('INSERT INTO role_permissions (role_id, permission_id, tenant_id) SELECT ?, id, ? FROM permissions WHERE (tenant_id = ? OR tenant_id IS NULL) AND deleted_at IS NULL');
                            $stmt->execute([$role_id, $tenant_id, $tenant_id]);
                        } else {
                            // Specific permissions
                            $perm_list = is_array($template['permissions']) ? $template['permissions'] : [];
                            foreach ($perm_list as $perm_code) {
                                $stmt = $pdo->prepare('SELECT id FROM permissions WHERE code = ? AND (tenant_id = ? OR tenant_id IS NULL)');
                                $stmt->execute([$perm_code, $tenant_id]);
                                $perm = $stmt->fetch();
                                if ($perm) {
                                    $stmt = $pdo->prepare('INSERT INTO role_permissions (role_id, permission_id, tenant_id) VALUES (?, ?, ?)');
                                    $stmt->execute([$role_id, $perm['id'], $tenant_id]);
                                }
                            }
                        }
                        $seeded++;
                    }
                }
                $msg = "$seeded roles seeded successfully";
                break;
        }
        
        $pdo->commit();
    } catch (Exception $e) {
        $pdo->rollBack();
        $msg = $e->getMessage();
    }
    
    if ($msg) {
        $is_json = strpos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false
                || (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest');
        if ($is_json) {
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'message' => $msg]);
            exit;
        }
        header('Location: roles.php?success=' . urlencode($msg));
        exit;
    }
}

// ============================================================================
// FETCH DATA
// ============================================================================
$roles = [];
try {
    $tables = [];
    $tbl_stmts = $pdo->query("SHOW TABLES");
    while ($row = $tbl_stmts->fetch(PDO::FETCH_NUM)) {
        $tables[] = $row[0];
    }
    
    $has_user_roles_table = in_array('user_roles', $tables);
    $has_role_permissions = in_array('role_permissions', $tables);
    $has_permissions = in_array('permissions', $tables);
    $has_users_role_id = false;
    
    if (in_array('users', $tables)) {
        try {
            $col_stmt = $pdo->query("DESCRIBE users");
            $user_cols = $col_stmt->fetchAll(PDO::FETCH_COLUMN);
            $has_users_role_id = in_array('role_id', $user_cols);
        } catch (PDOException $e) {}
    }

    $user_roles_has_data = false;
    if ($has_user_roles_table) {
        try {
            $count = $pdo->prepare("SELECT COUNT(*) FROM user_roles WHERE (tenant_id = ? OR tenant_id IS NULL)");
            $count->execute([$tenant_id]);
            $user_roles_has_data = ($count->fetchColumn() > 0);
        } catch (PDOException $e) {}
    }

    if ($has_user_roles_table && $user_roles_has_data) {
        $user_join = "LEFT JOIN user_roles ur ON r.id = ur.role_id";
        $user_count_field = ", COUNT(DISTINCT ur.user_id) as user_count";
    } elseif ($has_users_role_id) {
        $user_join = "LEFT JOIN users u ON r.id = u.role_id";
        $user_count_field = ", COUNT(DISTINCT u.id) as user_count";
    } else {
        $user_join = "";
        $user_count_field = ", 0 as user_count";
    }

    $perm_join = $has_role_permissions ? "LEFT JOIN role_permissions rp ON r.id = rp.role_id" : "";
    $perm_field = $has_permissions && $has_role_permissions ? ", GROUP_CONCAT(DISTINCT p.code) as permission_codes, GROUP_CONCAT(DISTINCT p.id) as permission_ids" : "";
    $perm_join2 = ($has_permissions && $has_role_permissions) ? "LEFT JOIN permissions p ON rp.permission_id = p.id" : "";

    $role_params = [$tenant_id];
    $role_where = "WHERE (r.tenant_id = ? OR r.tenant_id IS NULL) AND r.deleted_at IS NULL";
    
    $sql = "
        SELECT r.*
        $perm_field
        $user_count_field
        " . ($has_role_permissions ? ", COUNT(DISTINCT rp.permission_id) as permission_count" : ", 0 as permission_count") . "
        FROM roles r
        $user_join
        $perm_join
        $perm_join2
        $role_where
        GROUP BY r.id
        ORDER BY 
            CASE 
                WHEN r.name = 'Super Admin' THEN 1
                WHEN r.name = 'Owner' THEN 2
                WHEN r.name = 'Administrator' THEN 3
                WHEN r.name = 'Manager' THEN 4
                ELSE 5
            END,
            r.name
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($role_params);
    $roles = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Error fetching roles: " . $e->getMessage());
    $roles = [];
}

$permissions = [];
$grouped_permissions = [];
try {
    $tenant_id_val = function_exists('get_current_tenant_id') ? (int) get_current_tenant_id() : 0;

    $perm_params = [];
    if ($tenant_id_val > 0) {
        $perm_where = 'WHERE (tenant_id = ? OR tenant_id IS NULL) AND (deleted_at IS NULL)';
        $perm_params = [$tenant_id_val];
    } else {
        $perm_where = 'WHERE deleted_at IS NULL';
    }

    $perm_sql = "
        SELECT * FROM permissions
        $perm_where
        ORDER BY 
            CASE 
                WHEN code LIKE 'pos.%'       THEN 1
                WHEN code LIKE 'sales.%'     THEN 2
                WHEN code LIKE 'products.%'  THEN 3
                WHEN code LIKE 'inventory.%' THEN 4
                WHEN code LIKE 'customers.%' THEN 5
                WHEN code LIKE 'users.%'     THEN 6
                WHEN code LIKE 'reports.%'   THEN 7
                WHEN code LIKE 'expenses.%'  THEN 8
                ELSE 9
            END,
            code
    ";
    $stmt = $pdo->prepare($perm_sql);
    $stmt->execute($perm_params);
    $permissions = $stmt->fetchAll();

    foreach ($permissions as $perm) {
        $category = explode('.', $perm['code'])[0] ?? 'other';
        if (!isset($grouped_permissions[$category])) {
            $grouped_permissions[$category] = [];
        }
        $grouped_permissions[$category][] = $perm;
    }
} catch (PDOException $e) {
    error_log("Error fetching permissions: " . $e->getMessage());
    $permissions = [];
    $grouped_permissions = [];
}

// Statistics
$stats = ['total_roles' => 0, 'total_permissions' => 0];
try {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM roles WHERE (tenant_id = ? OR tenant_id IS NULL) AND deleted_at IS NULL");
    $stmt->execute([$tenant_id]);
    $stats['total_roles'] = (int) $stmt->fetchColumn();
    $stats['total_permissions'] = count($permissions);
} catch (PDOException $e) {}

$protected_roles = ['Super Admin', 'Owner', 'Administrator'];
$restricted_roles = ['Cashier', 'Waiter', 'Kitchen Staff', 'Delivery Rider', 'Viewer'];

function getCatIcon($category) {
    $icons = [
        'pos' => 'fa-cash-register', 'sales' => 'fa-chart-line', 'products' => 'fa-boxes',
        'inventory' => 'fa-warehouse', 'customers' => 'fa-users', 'users' => 'fa-user-shield',
        'reports' => 'fa-file-alt', 'expenses' => 'fa-wallet', 'other' => 'fa-key',
        'hr' => 'fa-users-cog', 'delivery' => 'fa-truck', 'kitchen' => 'fa-fire',
        'accounting' => 'fa-calculator', 'branch' => 'fa-building'
    ];
    return $icons[strtolower($category)] ?? 'fa-key';
}

ob_start();
?>

<!-- Page header -->
<div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-5">
    <div>
        <h1 class="text-lg font-bold text-white flex items-center gap-2">
            <i class="fas fa-user-shield text-amber-400"></i> Role Management
        </h1>
        <p class="text-sm text-slate-500 mt-0.5">
            <?php echo count($roles); ?> role<?php echo count($roles) !== 1 ? 's' : ''; ?> configured
        </p>
    </div>
    <div class="flex items-center gap-2 shrink-0 flex-wrap">
        <button onclick="seedAllRoles()"
           class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-green-500/10 border border-green-500/30 text-green-400 text-sm font-medium hover:bg-green-500/20 transition-colors">
            <i class="fas fa-database text-xs"></i> Seed All Roles
        </button>
        <button onclick="exportRoles()"
           class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-slate-800 border border-slate-700 text-slate-400 text-sm font-medium hover:bg-slate-700 hover:text-white transition-colors">
            <i class="fas fa-download text-xs"></i> Export CSV
        </button>
        <?php if ($is_super_admin || $user_role === 'Admin'): ?>
        <button onclick="openCreateModal()"
           class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-amber-500/10 border border-amber-500/30 text-amber-400 text-sm font-medium hover:bg-amber-500/20 transition-colors">
            <i class="fas fa-plus text-xs"></i> New Role
        </button>
        <?php endif; ?>
    </div>
</div>

<!-- Flash messages -->
<?php if (!empty($_GET['success'])): ?>
<div class="mb-4 flex items-center gap-2 px-3 py-2 rounded-lg bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 text-sm">
    <i class="fas fa-check-circle"></i>
    <?php echo htmlspecialchars(urldecode($_GET['success'])); ?>
</div>
<?php endif; ?>
<?php if (!empty($_GET['error'])): ?>
<div class="mb-4 flex items-center gap-2 px-3 py-2 rounded-lg bg-red-500/10 border border-red-500/30 text-red-400 text-sm">
    <i class="fas fa-exclamation-circle"></i>
    <?php echo htmlspecialchars(urldecode($_GET['error'])); ?>
</div>
<?php endif; ?>

<!-- JS message container -->
<div id="messageContainer"></div>

<!-- Stats Cards -->
<div class="grid grid-cols-2 sm:grid-cols-4 gap-2 mb-5">
    <?php
    $stat_cards = [
        ['label' => 'Total Roles',   'value' => $stats['total_roles'],          'icon' => 'fa-user-tag',    'color' => 'text-amber-400',   'bg' => 'bg-amber-500/10'],
        ['label' => 'Permissions',   'value' => $stats['total_permissions'],     'icon' => 'fa-key',         'color' => 'text-emerald-400', 'bg' => 'bg-emerald-500/10'],
        ['label' => 'Active Roles',  'value' => count($roles),                   'icon' => 'fa-shield-alt',  'color' => 'text-purple-400',  'bg' => 'bg-purple-500/10'],
        ['label' => 'Categories',    'value' => count($grouped_permissions),     'icon' => 'fa-layer-group', 'color' => 'text-blue-400',    'bg' => 'bg-blue-500/10'],
    ];
    foreach ($stat_cards as $card):
    ?>
    <div class="bg-slate-800/50 border border-slate-700/60 rounded-xl p-3 flex items-center gap-2.5">
        <div class="w-8 h-8 rounded-lg <?php echo $card['bg']; ?> flex items-center justify-center shrink-0">
            <i class="fas <?php echo $card['icon']; ?> <?php echo $card['color']; ?> text-xs"></i>
        </div>
        <div class="min-w-0">
            <div class="text-sm font-bold <?php echo $card['color']; ?>"><?php echo $card['value']; ?></div>
            <div class="text-xs text-slate-500 leading-none mt-0.5"><?php echo $card['label']; ?></div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- Roles Table Section -->
<div class="bg-slate-800/40 border border-slate-700/60 rounded-xl">
    <div class="px-3 py-2.5 border-b border-slate-700/60 flex flex-col sm:flex-row sm:items-center justify-between gap-2">
        <div class="flex items-center gap-2">
            <h2 class="text-sm font-semibold text-white">System Roles</h2>
            <span class="text-xs text-slate-500">(<?php echo count($roles); ?>)</span>
            <?php if (count($roles) < 16): ?>
            <span class="text-xs px-2 py-0.5 rounded-full bg-amber-500/20 text-amber-400">
                <i class="fas fa-info-circle mr-1"></i><?php echo 16 - count($roles); ?> missing
            </span>
            <?php endif; ?>
        </div>
        <div class="flex gap-2">
            <div class="relative">
                <i class="fas fa-search absolute left-2.5 top-1/2 -translate-y-1/2 text-slate-500 text-xs"></i>
                <input type="text" id="roleSearch" placeholder="Search roles…"
                       class="pl-7 pr-2 py-1.5 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-1 focus:ring-amber-500 w-36">
                <label for="roleSearch" class="sr-only">Search roles</label>
            </div>
        </div>
    </div>

    <div class="overflow-x-auto">
            <table class="w-full text-sm" id="rolesTable">
                <thead>
                    <tr class="text-xs text-slate-400 uppercase border-b border-slate-700/60 bg-slate-900/30">
                        <th class="text-left px-4 py-2.5 font-medium">Role Name</th>
                        <th class="text-left px-4 py-2.5 font-medium">Description</th>
                        <th class="text-center px-4 py-2.5 font-medium">Users</th>
                        <th class="text-center px-4 py-2.5 font-medium">Permissions</th>
                        <th class="text-center px-4 py-2.5 font-medium">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-700/50">
                    <?php if (empty($roles)): ?>
                        <tr>
                            <td colspan="5" class="text-center py-10 text-slate-500">
                                <i class="fas fa-shield-alt text-3xl mb-2 block text-slate-600"></i>
                                No roles found. Click "New Role" to create your first role.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($roles as $role):
                            $is_protected = in_array($role['name'], $protected_roles);
                            $is_restricted = isset($restricted_roles[$role['name']]) || strtolower($role['name']) === 'cashier';
                            $role_color = $role['name'] == 'Super Admin' ? 'red' : 
                                        ($role['name'] == 'Owner' ? 'amber' : 
                                        ($role['name'] == 'Administrator' ? 'purple' : 
                                        ($role['name'] == 'Manager' ? 'blue' : 
                                        ($is_restricted ? 'emerald' : 'slate'))));
                            $permission_list = !empty($role['permission_codes']) ? explode(',', $role['permission_codes']) : [];
                            $is_overprivileged = $is_restricted && (int)$role['permission_count'] > 10;
                        ?>
                            <tr class="hover:bg-slate-700/20 transition-colors role-row" data-role-name="<?php echo strtolower(htmlspecialchars($role['name'])); ?>">
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-2.5">
                                        <div class="w-7 h-7 rounded-lg bg-<?php echo $role_color; ?>-500/15 flex items-center justify-center shrink-0">
                                            <i class="fas <?php echo $role['name'] == 'Super Admin' ? 'fa-crown' : ($role['name'] == 'Owner' ? 'fa-crown' : 'fa-user-tag'); ?> text-<?php echo $role_color; ?>-400 text-xs"></i>
                                        </div>
                                        <div>
                                            <span class="font-medium text-white text-sm"><?php echo htmlspecialchars($role['name']); ?></span>
                                            <?php if ($is_protected): ?>
                                                <span class="ml-1.5 inline-flex px-1.5 py-0.5 rounded-full text-xs font-medium bg-purple-500/15 text-purple-400 ring-1 ring-purple-500/30">Protected</span>
                                            <?php endif; ?>
                                            <?php if ($is_overprivileged): ?>
                                                <span class="ml-1.5 inline-flex px-1.5 py-0.5 rounded-full text-xs font-medium bg-red-500/15 text-red-400 ring-1 ring-red-500/30" title="This role has too many permissions!">Overprivileged</span>
                                            <?php elseif ($is_restricted): ?>
                                                <span class="ml-1.5 inline-flex px-1.5 py-0.5 rounded-full text-xs font-medium bg-emerald-500/15 text-emerald-400 ring-1 ring-emerald-500/30">Restricted</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-sm text-slate-400"><?php echo htmlspecialchars($role['description'] ?: '—'); ?></td>
                                <td class="px-4 py-3 text-center">
                                    <span class="inline-flex items-center justify-center px-2 py-0.5 rounded-full bg-slate-700/60 text-slate-300 text-xs font-medium">
                                        <?php echo (int)$role['user_count']; ?>
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <span class="inline-flex items-center justify-center px-2 py-0.5 rounded-full bg-slate-700/60 text-slate-300 text-xs font-medium">
                                        <?php echo (int)$role['permission_count']; ?>
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <div class="flex justify-center items-center gap-1.5 flex-wrap">
                                        <button onclick="editRole(<?php echo $role['id']; ?>)"
                                                class="inline-flex items-center px-2 py-1 rounded-md bg-blue-500/10 text-blue-400 text-xs hover:bg-blue-500/20 transition-colors" title="Edit Role">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button onclick="duplicateRole(<?php echo $role['id']; ?>, '<?php echo htmlspecialchars(addslashes($role['name'])); ?>')"
                                                class="inline-flex items-center px-2 py-1 rounded-md bg-emerald-500/10 text-emerald-400 text-xs hover:bg-emerald-500/20 transition-colors" title="Duplicate Role">
                                            <i class="fas fa-copy"></i>
                                        </button>
                                        <button onclick="manageRolePermissions(<?php echo $role['id']; ?>)"
                                                class="inline-flex items-center px-2 py-1 rounded-md bg-purple-500/10 text-purple-400 text-xs hover:bg-purple-500/20 transition-colors" title="Manage Permissions">
                                            <i class="fas fa-key"></i>
                                        </button>
                                        <?php if ($is_overprivileged): ?>
                                        <button onclick="quickFixRole(<?php echo $role['id']; ?>, '<?php echo strtolower($role['name']) === 'cashier' ? 'cashier' : 'pos_only'; ?>')"
                                                class="inline-flex items-center px-2 py-1 rounded-md bg-amber-500/10 text-amber-400 text-xs hover:bg-amber-500/20 transition-colors" title="Apply Correct Permissions">
                                            <i class="fas fa-magic"></i> Fix
                                        </button>
                                        <?php endif; ?>
                                        <?php if ($is_super_admin && !$is_protected && $role['name'] !== 'Super Admin'): ?>
                                        <button onclick="deleteRole(<?php echo $role['id']; ?>, '<?php echo htmlspecialchars(addslashes($role['name'])); ?>', <?php echo (int)$role['user_count']; ?>)"
                                                class="inline-flex items-center px-2 py-1 rounded-md bg-red-500/10 text-red-400 text-xs hover:bg-red-500/20 transition-colors" title="Delete Role">
                                            <i class="fas fa-trash-alt"></i>
                                        </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
    </div>
</div>

<!-- ============================================================ -->
<!-- MODALS (Create, Edit, Duplicate, Delete, Permissions) -->
<!-- ============================================================ -->

<!-- Create / Edit Role Modal -->
<div id="roleModal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/60  p-4">
    <div class="bg-slate-900 border border-slate-700/60 rounded-xl shadow-2xl w-full max-w-md">
        <div class="flex items-center justify-between px-5 py-3.5 border-b border-slate-700/60">
            <h3 id="roleModalTitle" class="text-sm font-semibold text-white flex items-center gap-2">
                <i class="fas fa-plus text-amber-400 text-xs"></i> Create Role
            </h3>
            <button onclick="closeRoleModal()" class="text-slate-400 hover:text-white transition-colors"><i class="fas fa-times"></i></button>
        </div>
        <form id="roleForm" class="p-5 space-y-4">
            <input type="hidden" id="roleAction" value="create">
            <input type="hidden" id="roleId" value="0">
            <div>
                <label for="roleName" class="block text-xs font-medium text-slate-400 mb-1.5">Role Name <span class="text-red-400">*</span></label>
                <input type="text" id="roleName" required placeholder="e.g. Store Manager"
                       class="w-full px-3 py-2 bg-slate-800 border border-slate-700 rounded-lg text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-1 focus:ring-amber-500">
            </div>
            <div>
                <label for="roleDescription" class="block text-xs font-medium text-slate-400 mb-1.5">Description</label>
                <textarea id="roleDescription" rows="2" placeholder="Optional description…"
                          class="w-full px-3 py-2 bg-slate-800 border border-slate-700 rounded-lg text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-1 focus:ring-amber-500 resize-none"></textarea>
            </div>
            <div class="flex justify-end gap-2 pt-1">
                <button type="button" onclick="closeRoleModal()"
                        class="px-4 py-2 rounded-lg bg-slate-800 border border-slate-700 text-slate-400 text-sm hover:bg-slate-700 hover:text-white transition-colors">Cancel</button>
                <button type="submit"
                        class="px-4 py-2 rounded-lg bg-amber-500/15 border border-amber-500/30 text-amber-400 text-sm font-medium hover:bg-amber-500/25 transition-colors">Save Role</button>
            </div>
        </form>
    </div>
</div>

<!-- Duplicate Role Modal -->
<div id="duplicateRoleModal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/60  p-4">
    <div class="bg-slate-900 border border-slate-700/60 rounded-xl shadow-2xl w-full max-w-sm">
        <div class="flex items-center justify-between px-5 py-3.5 border-b border-slate-700/60">
            <h3 class="text-sm font-semibold text-white flex items-center gap-2">
                <i class="fas fa-copy text-emerald-400 text-xs"></i> Duplicate Role
            </h3>
            <button onclick="closeDuplicateRoleModal()" class="text-slate-400 hover:text-white transition-colors"><i class="fas fa-times"></i></button>
        </div>
        <form id="duplicateForm" class="p-5 space-y-4">
            <input type="hidden" id="duplicateRoleId" value="">
            <p class="text-sm text-slate-400">Duplicating role: <strong id="duplicateRoleName" class="text-white"></strong></p>
            <div>
                <label for="newRoleName" class="block text-xs font-medium text-slate-400 mb-1.5">New Role Name <span class="text-red-400">*</span></label>
                <input type="text" id="newRoleName" required placeholder="Name for the new role"
                       class="w-full px-3 py-2 bg-slate-800 border border-slate-700 rounded-lg text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-1 focus:ring-amber-500">
            </div>
            <div class="flex justify-end gap-2 pt-1">
                <button type="button" onclick="closeDuplicateRoleModal()"
                        class="px-4 py-2 rounded-lg bg-slate-800 border border-slate-700 text-slate-400 text-sm hover:bg-slate-700 hover:text-white transition-colors">Cancel</button>
                <button type="submit"
                        class="px-4 py-2 rounded-lg bg-emerald-500/15 border border-emerald-500/30 text-emerald-400 text-sm font-medium hover:bg-emerald-500/25 transition-colors">Duplicate</button>
            </div>
        </form>
    </div>
</div>

<!-- Delete Role Modal -->
<div id="deleteRoleModal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/60  p-4">
    <div class="bg-slate-900 border border-slate-700/60 rounded-xl shadow-2xl w-full max-w-sm">
        <div class="flex items-center justify-between px-5 py-3.5 border-b border-slate-700/60">
            <h3 class="text-sm font-semibold text-white flex items-center gap-2">
                <i class="fas fa-trash-alt text-red-400 text-xs"></i> Delete Role
            </h3>
            <button onclick="closeDeleteRoleModal()" class="text-slate-400 hover:text-white transition-colors"><i class="fas fa-times"></i></button>
        </div>
        <form id="deleteForm" class="p-5 space-y-4">
            <input type="hidden" id="deleteRoleId" value="">
            <p class="text-sm text-slate-300">Delete role <strong id="deleteRoleName" class="text-white"></strong>?
                (<span id="deleteUserCount" class="text-amber-400 font-medium">0</span> users assigned)
            </p>
            <div id="deleteRoleWarning" class="hidden flex items-start gap-2 px-3 py-2 rounded-lg bg-red-500/10 border border-red-500/30 text-red-400 text-xs">
                <i class="fas fa-exclamation-triangle mt-0.5 shrink-0"></i>
                Cannot delete: this role still has users assigned to it.
            </div>
            <div class="flex justify-end gap-2 pt-1">
                <button type="button" onclick="closeDeleteRoleModal()"
                        class="px-4 py-2 rounded-lg bg-slate-800 border border-slate-700 text-slate-400 text-sm hover:bg-slate-700 hover:text-white transition-colors">Cancel</button>
                <button type="submit" id="deleteRoleButton"
                        class="px-4 py-2 rounded-lg bg-red-500/15 border border-red-500/30 text-red-400 text-sm font-medium hover:bg-red-500/25 transition-colors">Delete</button>
            </div>
        </form>
    </div>
</div>

<!-- Manage Permissions Modal -->
<div id="permissionsModal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/60  p-4">
    <div class="bg-slate-900 border border-slate-700/60 rounded-xl shadow-2xl w-full max-w-2xl max-h-[90vh] flex flex-col">
        <div class="flex items-center justify-between px-5 py-3.5 border-b border-slate-700/60 shrink-0">
            <h3 class="text-sm font-semibold text-white flex items-center gap-2">
                <i class="fas fa-key text-purple-400 text-xs"></i> Manage Permissions
            </h3>
            <button onclick="closePermissionsModal()" class="text-slate-400 hover:text-white transition-colors"><i class="fas fa-times"></i></button>
        </div>
        <form id="permissionsForm" class="flex flex-col flex-1 min-h-0">
            <input type="hidden" id="permRoleId" value="">

            <!-- Template selector + bulk actions -->
            <div class="px-5 py-3 border-b border-slate-700/60 flex flex-wrap gap-2 items-center shrink-0">
                <span class="text-xs text-slate-500 mr-1">Apply template:</span>
                <?php foreach ($permission_templates as $key => $tmpl): ?>
                <button type="button" onclick="applyTemplateToPermissions('<?php echo $key; ?>')"
                        class="px-2 py-1 rounded-md bg-slate-800 border border-slate-700 text-slate-300 text-xs hover:bg-slate-700 hover:text-white transition-colors">
                    <i class="fas <?php echo $tmpl['icon']; ?> mr-1 text-<?php echo $tmpl['color']; ?>-400"></i><?php echo $tmpl['name']; ?>
                </button>
                <?php endforeach; ?>
                <div class="ml-auto flex gap-2">
                    <button type="button" onclick="selectAllPermissions()" class="px-2.5 py-1 rounded-md bg-amber-500/10 border border-amber-500/30 text-amber-400 text-xs hover:bg-amber-500/20 transition-colors">All</button>
                    <button type="button" onclick="deselectAllPermissions()" class="px-2.5 py-1 rounded-md bg-slate-800 border border-slate-700 text-slate-400 text-xs hover:bg-slate-700 transition-colors">None</button>
                </div>
            </div>

            <!-- Permissions grid -->
            <div class="flex-1 overflow-y-auto px-5 py-4 space-y-4 custom-scroll">
                <?php foreach ($grouped_permissions as $category => $perms): ?>
                <div data-category="<?php echo htmlspecialchars($category); ?>">
                    <div class="flex items-center gap-2 mb-2">
                        <label class="flex items-center gap-1.5 cursor-pointer select-none">
                            <input type="checkbox" class="category-select-all rounded border-slate-600 bg-slate-800 text-amber-500 focus:ring-amber-500" data-category="<?php echo htmlspecialchars($category); ?>">
                            <i class="fas <?php echo getCatIcon($category); ?> text-amber-400 text-xs"></i>
                            <span class="text-xs font-semibold text-slate-300 uppercase tracking-wide"><?php echo ucfirst($category); ?></span>
                        </label>
                        <span class="text-xs text-slate-600">(<?php echo count($perms); ?>)</span>
                    </div>
                    <div class="grid grid-cols-2 sm:grid-cols-3 gap-1.5 pl-2">
                        <?php foreach ($perms as $perm): ?>
                        <label class="flex items-center gap-1.5 px-2 py-1.5 rounded-md bg-slate-800/60 border border-slate-700/60 hover:bg-slate-800 cursor-pointer select-none text-xs text-slate-300 hover:text-white transition-colors">
                            <input type="checkbox" class="permission-checkbox rounded border-slate-600 bg-slate-800 text-amber-500 focus:ring-amber-500 shrink-0" value="<?php echo $perm['id']; ?>">
                            <span class="truncate"><?php echo htmlspecialchars($perm['code']); ?></span>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php if (empty($grouped_permissions)): ?>
                <p class="text-center text-slate-500 text-sm py-8">No permissions found for this branch.</p>
                <?php endif; ?>
            </div>

            <div class="px-5 py-3.5 border-t border-slate-700/60 flex justify-end gap-2 shrink-0">
                <button type="button" onclick="closePermissionsModal()"
                        class="px-4 py-2 rounded-lg bg-slate-800 border border-slate-700 text-slate-400 text-sm hover:bg-slate-700 hover:text-white transition-colors">Cancel</button>
                <button type="submit"
                        class="px-4 py-2 rounded-lg bg-purple-500/15 border border-purple-500/30 text-purple-400 text-sm font-medium hover:bg-purple-500/25 transition-colors">Save Permissions</button>
            </div>
        </form>
    </div>
</div>

<style>
.custom-scroll::-webkit-scrollbar { width: 4px; }
.custom-scroll::-webkit-scrollbar-track { background: rgba(255, 255, 255, 0.05); border-radius: 10px; }
.custom-scroll::-webkit-scrollbar-thumb { background: #f59e0b; border-radius: 10px; }
</style>

<script>
const API_BASE = window.location.pathname.replace(/\/[^/]*$/, '/');
let currentRolePermissions = [];

function showMessage(message, type = 'success') {
    const container = document.getElementById('messageContainer');
    const cls = type === 'success'
        ? 'bg-emerald-500/10 border border-emerald-500/30 text-emerald-400'
        : 'bg-red-500/10 border border-red-500/30 text-red-400';
    const icon = type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle';
    const msgDiv = document.createElement('div');
    msgDiv.className = `mb-4 flex items-center gap-2 px-3 py-2 rounded-lg ${cls} text-sm`;
    msgDiv.innerHTML = `<i class="fas ${icon}"></i><span class="flex-1">${escapeHtml(message)}</span><button onclick="this.parentElement.remove()" class="text-current opacity-60 hover:opacity-100 ml-auto">&times;</button>`;
    container.appendChild(msgDiv);
    setTimeout(() => msgDiv?.remove(), 4000);
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

async function apiCall(action, data) {
    try {
        const formData = new FormData();
        formData.append('action', action);
        formData.append('csrf_token', '<?php echo htmlspecialchars($csrf_token); ?>');
        Object.keys(data).forEach(key => {
            if (Array.isArray(data[key])) {
                data[key].forEach(val => formData.append(`${key}[]`, val));
            } else {
                formData.append(key, data[key]);
            }
        });
        const response = await fetch(`${API_BASE}roles.php`, {
            method: 'POST',
            headers: { 'Accept': 'application/json' },
            body: formData
        });
        const result = await response.json();
        if (result.success) {
            showMessage(result.message, 'success');
            setTimeout(() => window.location.reload(), 1000);
        } else {
            showMessage(result.message || 'An error occurred', 'error');
        }
        return result;
    } catch (error) {
        showMessage('Network error: ' + error.message, 'error');
        return { success: false, message: error.message };
    }
}

// Seed All Roles
function seedAllRoles() {
    if (!confirm('This will create all 16 default roles with proper permissions. Continue?')) return;
    apiCall('seed_roles', {});
}

// Role CRUD Functions
function openCreateModal() {
    document.getElementById('roleModalTitle').innerHTML = '<i class="fas fa-plus text-xs mr-1"></i> Create Role';
    document.getElementById('roleAction').value = 'create';
    document.getElementById('roleId').value = '0';
    document.getElementById('roleName').value = '';
    document.getElementById('roleDescription').value = '';
    document.getElementById('roleModal').classList.remove('hidden');
}

function editRole(roleId) {
    fetch(`${API_BASE}get_role.php?id=${roleId}`)
        .then(res => res.json())
        .then(role => {
            if (role) {
                document.getElementById('roleModalTitle').innerHTML = '<i class="fas fa-edit text-xs mr-1"></i> Edit Role';
                document.getElementById('roleAction').value = 'update';
                document.getElementById('roleId').value = role.id;
                document.getElementById('roleName').value = role.name || '';
                document.getElementById('roleDescription').value = role.description || '';
                document.getElementById('roleModal').classList.remove('hidden');
            }
        })
        .catch(err => showMessage('Failed to load role data', 'error'));
}

function closeRoleModal() { document.getElementById('roleModal').classList.add('hidden'); }

function duplicateRole(id, name) {
    document.getElementById('duplicateRoleId').value = id;
    document.getElementById('duplicateRoleName').textContent = name;
    document.getElementById('duplicateRoleModal').classList.remove('hidden');
}

function closeDuplicateRoleModal() { document.getElementById('duplicateRoleModal').classList.add('hidden'); }

function deleteRole(id, name, userCount) {
    document.getElementById('deleteRoleId').value = id;
    document.getElementById('deleteRoleName').textContent = name;
    document.getElementById('deleteUserCount').textContent = userCount;
    const warning = document.getElementById('deleteRoleWarning');
    const btn = document.getElementById('deleteRoleButton');
    if (userCount > 0) {
        warning.classList.remove('hidden');
        btn.disabled = true;
        btn.classList.add('opacity-50', 'cursor-not-allowed');
    } else {
        warning.classList.add('hidden');
        btn.disabled = false;
        btn.classList.remove('opacity-50', 'cursor-not-allowed');
    }
    document.getElementById('deleteRoleModal').classList.remove('hidden');
}

function closeDeleteRoleModal() { document.getElementById('deleteRoleModal').classList.add('hidden'); }

// Permissions Management
function manageRolePermissions(roleId) {
    document.getElementById('permRoleId').value = roleId;
    fetch(`${API_BASE}get_role.php?id=${roleId}`)
        .then(res => res.json())
        .then(role => {
            currentRolePermissions = role.permission_ids ? String(role.permission_ids).split(',').map(id => id.trim()) : [];
            document.querySelectorAll('#permissionsModal .permission-checkbox').forEach(cb => {
                cb.checked = currentRolePermissions.includes(cb.value);
            });
            updateCategorySelectAll();
        })
        .catch(err => showMessage('Failed to load permissions', 'error'));
    document.getElementById('permissionsModal').classList.remove('hidden');
}

function closePermissionsModal() { document.getElementById('permissionsModal').classList.add('hidden'); }

function selectAllPermissions() {
    document.querySelectorAll('#permissionsModal .permission-checkbox').forEach(cb => cb.checked = true);
    updateCategorySelectAll();
}

function deselectAllPermissions() {
    document.querySelectorAll('#permissionsModal .permission-checkbox').forEach(cb => cb.checked = false);
    updateCategorySelectAll();
}

function updateCategorySelectAll() {
    document.querySelectorAll('#permissionsModal [data-category]').forEach(categoryDiv => {
        const checkboxes = categoryDiv.querySelectorAll('.permission-checkbox');
        const checkedBoxes = categoryDiv.querySelectorAll('.permission-checkbox:checked');
        const selectAllCb = document.querySelector(`#permissionsModal .category-select-all[data-category="${categoryDiv.dataset.category}"]`);
        if (selectAllCb) {
            selectAllCb.checked = checkboxes.length > 0 && checkedBoxes.length === checkboxes.length;
        }
    });
}

function applyTemplateToPermissions(template) {
    document.querySelectorAll('#permissionsModal .permission-checkbox').forEach(cb => cb.checked = false);
    
    const templateConfig = {
        'full_access': { all: true },
        'super_admin': { all: true },
        'owner': { all: true },
        'administrator': { all: true },
        'manager': ['pos.view', 'pos.sell', 'pos.refund', 'pos.discount', 'pos.hold', 'pos.void', 'sales.view', 'sales.manage', 'sales.returns', 'products.view', 'products.create', 'products.edit', 'inventory.view', 'inventory.adjust', 'inventory.transfer', 'customers.view', 'customers.create', 'customers.edit', 'users.view', 'users.create', 'users.edit', 'reports.view', 'reports.sales', 'reports.inventory', 'expenses.view', 'expenses.create', 'expenses.approve', 'branches.view'],
        'accountant': ['sales.view', 'sales.export', 'reports.view', 'reports.sales', 'reports.financial', 'reports.profit', 'reports.export', 'expenses.view', 'expenses.create', 'expenses.edit', 'expenses.categories', 'customers.view', 'customers.credit'],
        'inventory_manager': ['products.view', 'products.create', 'products.edit', 'products.delete', 'products.import', 'products.export', 'products.categories', 'inventory.view', 'inventory.adjust', 'inventory.transfer', 'inventory.count', 'inventory.reorder', 'inventory.alerts', 'purchases.view', 'purchases.create', 'purchases.approve', 'suppliers.view', 'suppliers.create', 'suppliers.edit', 'reports.inventory', 'reports.export'],
        'hr_manager': ['users.view', 'users.create', 'users.edit', 'users.delete', 'users.roles', 'users.attendance', 'hr.view', 'hr.employees', 'hr.leaves', 'hr.payroll', 'reports.view'],
        'senior_cashier': ['pos.view', 'pos.sell', 'pos.refund', 'pos.discount', 'pos.hold', 'pos.order', 'pos.split', 'sales.view', 'sales.returns', 'customers.view', 'customers.create', 'customers.edit', 'customers.loyalty', 'inventory.view', 'reports.sales'],
        'cashier': ['pos.view', 'pos.sell', 'pos.hold', 'pos.order', 'customers.view', 'customers.create', 'sales.view'],
        'inventory_clerk': ['products.view', 'inventory.view', 'inventory.count', 'suppliers.view', 'purchases.view'],
        'customer_service': ['customers.view', 'customers.create', 'customers.edit', 'sales.view', 'sales.returns', 'pos.view', 'reports.view'],
        'kitchen_staff': ['pos.kitchen', 'pos.view', 'kitchen.view', 'kitchen.ready'],
        'delivery_rider': ['delivery.view', 'delivery.update', 'delivery.track', 'orders.view', 'orders.dispatch'],
        'viewer': ['reports.view', 'reports.sales', 'sales.view', 'products.view', 'customers.view', 'inventory.view'],
        'waiter': ['pos.view', 'pos.sell', 'pos.order', 'pos.table', 'pos.kitchen', 'customers.view', 'sales.view'],
        'branch_manager': ['pos.view', 'pos.sell', 'pos.refund', 'pos.discount', 'pos.hold', 'sales.view', 'sales.manage', 'sales.returns', 'products.view', 'products.create', 'products.edit', 'inventory.view', 'inventory.adjust', 'inventory.transfer', 'customers.view', 'customers.create', 'customers.edit', 'staff.view', 'staff.manage', 'reports.view', 'reports.sales', 'reports.inventory', 'branches.view', 'branches.manage'],
        'auditor_read_only': ['sales.view', 'sales.export', 'products.view', 'inventory.view', 'customers.view', 'reports.view', 'reports.sales', 'reports.inventory', 'reports.financial', 'reports.profit', 'expenses.view', 'users.view', 'audit.view']
    };

    const config = templateConfig[template] || [];
    
    if (config === 'all' || config.all === true) {
        document.querySelectorAll('#permissionsModal .permission-checkbox').forEach(cb => cb.checked = true);
    } else if (Array.isArray(config)) {
        document.querySelectorAll('#permissionsModal .permission-checkbox').forEach(cb => {
            const text = cb.closest('label')?.innerText || '';
            cb.checked = config.some(perm => text.includes(perm));
        });
    }
    
    updateCategorySelectAll();
    showMessage('Template applied to permission selection', 'success');
}

// Quick Fix - Apply correct permissions to overprivileged roles
async function quickFixRole(roleId, template) {
    if (!confirm(`Apply ${template} template to fix this role's permissions?`)) return;
    
    // Open permissions modal and apply template
    manageRolePermissions(roleId);
    
    // Wait for modal to open then apply template
    setTimeout(() => {
        applyTemplateToPermissions(template);
        
        // Auto-save the permissions
        const selectedPerms = Array.from(document.querySelectorAll('#permissionsModal .permission-checkbox:checked')).map(cb => cb.value);
        apiCall('update', {
            id: document.getElementById('permRoleId').value,
            perms: selectedPerms
        }).then(() => {
            showMessage(`Role fixed with ${template} permissions`, 'success');
            closePermissionsModal();
            setTimeout(() => window.location.reload(), 1000);
        });
    }, 300);
}

// Form Submissions
document.getElementById('roleForm')?.addEventListener('submit', async (e) => {
    e.preventDefault();
    const isEdit = document.getElementById('roleAction').value === 'update';
    const data = {
        name: document.getElementById('roleName').value,
        description: document.getElementById('roleDescription').value,
        id: isEdit ? document.getElementById('roleId').value : '0'
    };
    await apiCall('save_role', data);
});

document.getElementById('duplicateForm')?.addEventListener('submit', async (e) => {
    e.preventDefault();
    await apiCall('duplicate', {
        id: document.getElementById('duplicateRoleId').value,
        new_name: document.getElementById('newRoleName').value
    });
});

document.getElementById('deleteForm')?.addEventListener('submit', async (e) => {
    e.preventDefault();
    await apiCall('delete', { role_id: document.getElementById('deleteRoleId').value });
});

document.getElementById('permissionsForm')?.addEventListener('submit', async (e) => {
    e.preventDefault();
    const selectedPerms = Array.from(document.querySelectorAll('#permissionsModal .permission-checkbox:checked')).map(cb => cb.value);
    await apiCall('update', {
        id: document.getElementById('permRoleId').value,
        perms: selectedPerms
    });
});

// Search
document.getElementById('roleSearch')?.addEventListener('input', e => {
    const term = e.target.value.toLowerCase();
    document.querySelectorAll('.role-row').forEach(row => {
        row.style.display = row.dataset.roleName?.includes(term) ? '' : 'none';
    });
});

// Category select all
document.querySelectorAll('.category-select-all').forEach(cb => {
    cb.addEventListener('change', function() {
        const container = document.querySelector(`[data-category="${this.dataset.category}"]`);
        if (container) {
            container.querySelectorAll('.permission-checkbox').forEach(permCb => permCb.checked = this.checked);
        }
    });
});

// Export
function exportRoles() {
    const rows = document.querySelectorAll('#rolesTable tbody tr');
    let csv = 'Role Name,Description,Users,Permissions\n';
    rows.forEach(row => {
        if (row.style.display !== 'none') {
            const cells = row.querySelectorAll('td');
            const name = cells[0]?.innerText.trim().replace(/,/g, ' ') || '';
            const desc = cells[1]?.innerText.trim().replace(/,/g, ' ') || '';
            const users = cells[2]?.innerText.trim() || '0';
            const perms = cells[3]?.innerText.trim() || '0';
            csv += `"${name}","${desc}",${users},${perms}\n`;
        }
    });
    const blob = new Blob([csv], { type: 'text/csv' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `roles_export_${new Date().toISOString().slice(0,19)}.csv`;
    a.click();
    URL.revokeObjectURL(url);
}

// Close modals on backdrop click
document.getElementById('roleModal')?.addEventListener('click', e => { if (e.target === e.currentTarget) closeRoleModal(); });
document.getElementById('duplicateRoleModal')?.addEventListener('click', e => { if (e.target === e.currentTarget) closeDuplicateRoleModal(); });
document.getElementById('deleteRoleModal')?.addEventListener('click', e => { if (e.target === e.currentTarget) closeDeleteRoleModal(); });
document.getElementById('permissionsModal')?.addEventListener('click', e => { if (e.target === e.currentTarget) closePermissionsModal(); });
</script>

<?php
$page_content = ob_get_clean();
require_once __DIR__ . '/../../layouts/app.php';
?>