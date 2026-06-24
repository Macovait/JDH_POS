<?php
/**
 * Database Migration Runner
 * Safely applies database migrations with error handling
 */

require_once __DIR__ . '/../src/paths.php';
require_once __DIR__ . '/../src/core/paths.php';
safe_require('db.php', 'src/core', true);

header('Content-Type: text/plain');

echo "=== Database Migration Runner ===\n\n";

$pdo = get_db_connection();
if (!$pdo) {
    echo "ERROR: Could not connect to database\n";
    exit(1);
}

$migrations = [
    [
        'name' => 'Add fk_companies_business_type',
        'sql' => "ALTER TABLE companies 
                  ADD CONSTRAINT fk_companies_business_type 
                  FOREIGN KEY (business_type_id) REFERENCES business_types(id) ON DELETE SET NULL",
        'check' => "SELECT CONSTRAINT_NAME 
                    FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS 
                    WHERE TABLE_NAME = 'companies' 
                    AND CONSTRAINT_NAME = 'fk_companies_business_type'"
    ],
    [
        'name' => 'Add fk_companies_default_plan',
        'sql' => "ALTER TABLE companies 
                  ADD CONSTRAINT fk_companies_default_plan 
                  FOREIGN KEY (default_plan_id) REFERENCES subscription_plans(id) ON DELETE SET NULL",
        'check' => "SELECT CONSTRAINT_NAME 
                    FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS 
                    WHERE TABLE_NAME = 'companies' 
                    AND CONSTRAINT_NAME = 'fk_companies_default_plan'"
    ],
    [
        'name' => 'Add idx_companies_status_deleted',
        'sql' => "CREATE INDEX idx_companies_status_deleted ON companies(status, deleted_at)",
        'check' => "SELECT INDEX_NAME 
                    FROM INFORMATION_SCHEMA.STATISTICS 
                    WHERE TABLE_NAME = 'companies' 
                    AND INDEX_NAME = 'idx_companies_status_deleted'"
    ],
    [
        'name' => 'Add idx_companies_business_type',
        'sql' => "CREATE INDEX idx_companies_business_type ON companies(business_type, status)",
        'check' => "SELECT INDEX_NAME 
                    FROM INFORMATION_SCHEMA.STATISTICS 
                    WHERE TABLE_NAME = 'companies' 
                    AND INDEX_NAME = 'idx_companies_business_type'"
    ],
    [
        'name' => 'Add branches.manager_id column',
        'sql' => "ALTER TABLE branches ADD COLUMN manager_id INT(11) NULL AFTER manager",
        'check' => "SELECT COLUMN_NAME 
                    FROM INFORMATION_SCHEMA.COLUMNS 
                    WHERE TABLE_NAME = 'branches' 
                    AND COLUMN_NAME = 'manager_id'"
    ],
    [
        'name' => 'Add fk_branches_manager',
        'sql' => "ALTER TABLE branches 
                  ADD CONSTRAINT fk_branches_manager 
                  FOREIGN KEY (manager_id) REFERENCES users(id) ON DELETE SET NULL",
        'check' => "SELECT CONSTRAINT_NAME 
                    FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS 
                    WHERE TABLE_NAME = 'branches' 
                    AND CONSTRAINT_NAME = 'fk_branches_manager'"
    ],
    [
        'name' => 'Add fk_branches_tenant',
        'sql' => "ALTER TABLE branches 
                  ADD CONSTRAINT fk_branches_company 
                  FOREIGN KEY (tenant_id) REFERENCES companies(id) ON DELETE CASCADE",
        'check' => "SELECT CONSTRAINT_NAME 
                    FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS 
                    WHERE TABLE_NAME = 'branches' 
                    AND CONSTRAINT_NAME = 'fk_branches_tenant'"
    ],
    [
        'name' => 'Create branch_hours table',
        'sql' => "CREATE TABLE IF NOT EXISTS `branch_hours` (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `branch_id` INT(11) NOT NULL,
            `day_of_week` TINYINT(1) NOT NULL COMMENT '0=Sunday, 1=Monday, etc.',
            `opening_time` TIME NOT NULL,
            `closing_time` TIME NOT NULL,
            `is_closed` TINYINT(1) NOT NULL DEFAULT 0,
            `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP(),
            `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uk_branch_day` (`branch_id`, `day_of_week`),
            FOREIGN KEY (`branch_id`) REFERENCES `branches`(`id`) ON DELETE CASCADE
          ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        'check' => "SELECT TABLE_NAME 
                    FROM INFORMATION_SCHEMA.TABLES 
                    WHERE TABLE_NAME = 'branch_hours' 
                    AND TABLE_SCHEMA = DATABASE()"
    ],
    [
        'name' => 'Add branches.manager_id column',
        'sql' => "ALTER TABLE branches ADD COLUMN manager_id INT(11) NULL AFTER manager",
        'check' => "SELECT COLUMN_NAME 
                    FROM INFORMATION_SCHEMA.COLUMNS 
                    WHERE TABLE_NAME = 'branches' 
                    AND COLUMN_NAME = 'manager_id'"
    ],
    [
        'name' => 'Standardize branches.created_at',
        'sql' => "ALTER TABLE branches MODIFY created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP",
        'check' => "SELECT COLUMN_NAME 
                    FROM INFORMATION_SCHEMA.COLUMNS 
                    WHERE TABLE_NAME = 'branches' 
                    AND COLUMN_NAME = 'created_at' 
                    AND COLUMN_DEFAULT = 'CURRENT_TIMESTAMP' 
                    AND IS_NULLABLE = 'NO'"
    ],
    [
        'name' => 'Standardize branches.updated_at',
        'sql' => "ALTER TABLE branches MODIFY updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP",
        'check' => "SELECT COLUMN_NAME 
                    FROM INFORMATION_SCHEMA.COLUMNS 
                    WHERE TABLE_NAME = 'branches' 
                    AND COLUMN_NAME = 'updated_at' 
                    AND EXTRA LIKE '%ON UPDATE CURRENT_TIMESTAMP%'"
    ],
    [
        'name' => 'Add idx_branches_company_active',
        'sql' => "CREATE INDEX idx_branches_company_active ON branches(tenant_id, is_active, deleted_at)",
        'check' => "SELECT INDEX_NAME 
                    FROM INFORMATION_SCHEMA.STATISTICS 
                    WHERE TABLE_NAME = 'branches' 
                    AND INDEX_NAME = 'idx_branches_company_active'"
    ],
    [
        'name' => 'Add fk_users_role',
        'sql' => "ALTER TABLE users ADD CONSTRAINT fk_users_role 
                  FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE SET NULL",
        'check' => "SELECT CONSTRAINT_NAME 
                    FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS 
                    WHERE TABLE_NAME = 'users' 
                    AND CONSTRAINT_NAME = 'fk_users_role'"
    ],
    [
        'name' => 'Add fk_users_branch',
        'sql' => "ALTER TABLE users ADD CONSTRAINT fk_users_branch 
                  FOREIGN KEY (branch_id) REFERENCES branches(id) ON DELETE SET NULL",
        'check' => "SELECT CONSTRAINT_NAME 
                    FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS 
                    WHERE TABLE_NAME = 'users' 
                    AND CONSTRAINT_NAME = 'fk_users_branch'"
    ],
    [
        'name' => 'Create user_password_history table',
        'sql' => "CREATE TABLE IF NOT EXISTS `user_password_history` (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `user_id` INT(11) NOT NULL,
            `password_hash` VARCHAR(255) NOT NULL,
            `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `idx_user_password_history` (`user_id`),
            FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
          ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        'check' => "SELECT TABLE_NAME 
                    FROM INFORMATION_SCHEMA.TABLES 
                    WHERE TABLE_NAME = 'user_password_history' 
                    AND TABLE_SCHEMA = DATABASE()"
    ],
    [
        'name' => 'Create user_sessions table',
        'sql' => "CREATE TABLE IF NOT EXISTS `user_sessions` (
            `id` VARCHAR(128) NOT NULL,
            `user_id` INT(11) NOT NULL,
            `ip_address` VARCHAR(45) NOT NULL,
            `user_agent` TEXT,
            `payload` TEXT NOT NULL,
            `last_activity` INT(11) NOT NULL,
            `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `idx_user_sessions_user_id` (`user_id`),
            KEY `idx_user_sessions_last_activity` (`last_activity`),
            FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
          ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        'check' => "SELECT TABLE_NAME 
                    FROM INFORMATION_SCHEMA.TABLES 
                    WHERE TABLE_NAME = 'user_sessions' 
                    AND TABLE_SCHEMA = DATABASE()"
    ],
    [
        'name' => 'Add users.user_status column',
        'sql' => "ALTER TABLE users ADD COLUMN user_status 
                  ENUM('active','inactive','locked','suspended') NOT NULL DEFAULT 'active' AFTER status",
        'check' => "SELECT COLUMN_NAME 
                    FROM INFORMATION_SCHEMA.COLUMNS 
                    WHERE TABLE_NAME = 'users' 
                    AND COLUMN_NAME = 'user_status'"
    ],
    [
        'name' => 'Add users.total_login_attempts column',
        'sql' => "ALTER TABLE users ADD COLUMN total_login_attempts 
                  INT(11) NOT NULL DEFAULT 0 AFTER failed_login_attempts",
        'check' => "SELECT COLUMN_NAME 
                    FROM INFORMATION_SCHEMA.COLUMNS 
                    WHERE TABLE_NAME = 'users' 
                    AND COLUMN_NAME = 'total_login_attempts'"
    ],
    [
        'name' => 'Add roles.inherits_from column',
        'sql' => "ALTER TABLE roles ADD COLUMN inherits_from INT(11) NULL AFTER is_system",
        'check' => "SELECT COLUMN_NAME 
                    FROM INFORMATION_SCHEMA.COLUMNS 
                    WHERE TABLE_NAME = 'roles' 
                    AND COLUMN_NAME = 'inherits_from'"
    ],
    [
        'name' => 'Add roles.sort_order column',
        'sql' => "ALTER TABLE roles ADD COLUMN sort_order INT(11) NOT NULL DEFAULT 0 AFTER inherits_from",
        'check' => "SELECT COLUMN_NAME 
                    FROM INFORMATION_SCHEMA.COLUMNS 
                    WHERE TABLE_NAME = 'roles' 
                    AND COLUMN_NAME = 'sort_order'"
    ],
    [
        'name' => 'Add fk_roles_inherits',
        'sql' => "ALTER TABLE roles ADD CONSTRAINT fk_roles_inherits 
                  FOREIGN KEY (inherits_from) REFERENCES roles(id) ON DELETE SET NULL",
        'check' => "SELECT CONSTRAINT_NAME 
                    FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS 
                    WHERE TABLE_NAME = 'roles' 
                    AND CONSTRAINT_NAME = 'fk_roles_inherits'"
    ],
    [
        'name' => 'Create permission_groups table',
        'sql' => "CREATE TABLE IF NOT EXISTS `permission_groups` (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `name` VARCHAR(100) NOT NULL,
            `description` VARCHAR(255) DEFAULT NULL,
            `sort_order` INT(11) NOT NULL DEFAULT 0,
            `icon` VARCHAR(50) DEFAULT NULL,
            `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uk_permission_groups_name` (`name`)
          ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        'check' => "SELECT TABLE_NAME 
                    FROM INFORMATION_SCHEMA.TABLES 
                    WHERE TABLE_NAME = 'permission_groups' 
                    AND TABLE_SCHEMA = DATABASE()"
    ],
    [
        'name' => 'Add permissions.group_id column',
        'sql' => "ALTER TABLE permissions ADD COLUMN group_id INT(11) NULL AFTER category",
        'check' => "SELECT COLUMN_NAME 
                    FROM INFORMATION_SCHEMA.COLUMNS 
                    WHERE TABLE_NAME = 'permissions' 
                    AND COLUMN_NAME = 'group_id'"
    ],
    [
        'name' => 'Add fk_permissions_group',
        'sql' => "ALTER TABLE permissions ADD CONSTRAINT fk_permissions_group 
                  FOREIGN KEY (group_id) REFERENCES permission_groups(id) ON DELETE SET NULL",
        'check' => "SELECT CONSTRAINT_NAME 
                    FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS 
                    WHERE TABLE_NAME = 'permissions' 
                    AND CONSTRAINT_NAME = 'fk_permissions_group'"
    ],
    [
        'name' => 'Create company_permissions table',
        'sql' => "CREATE TABLE IF NOT EXISTS `company_permissions` (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `tenant_id` INT(11) NOT NULL,
            `permission_id` INT(11) NOT NULL,
            `is_allowed` TINYINT(1) NOT NULL DEFAULT 1,
            `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uk_company_permission` (`tenant_id`, `permission_id`),
            FOREIGN KEY (`tenant_id`) REFERENCES `companies`(`id`) ON DELETE CASCADE,
            FOREIGN KEY (`permission_id`) REFERENCES `permissions`(`id`) ON DELETE CASCADE
          ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        'check' => "SELECT TABLE_NAME 
                    FROM INFORMATION_SCHEMA.TABLES 
                    WHERE TABLE_NAME = 'company_permissions' 
                    AND TABLE_SCHEMA = DATABASE()"
    ],
    [
        'name' => 'Add idx_roles_deleted',
        'sql' => "CREATE INDEX idx_roles_deleted ON roles(deleted_at)",
        'check' => "SELECT INDEX_NAME 
                    FROM INFORMATION_SCHEMA.STATISTICS 
                    WHERE TABLE_NAME = 'roles' 
                    AND INDEX_NAME = 'idx_roles_deleted'"
    ],
    [
        'name' => 'Add idx_roles_system',
        'sql' => "CREATE INDEX idx_roles_system ON roles(is_system)",
        'check' => "SELECT INDEX_NAME 
                    FROM INFORMATION_SCHEMA.STATISTICS 
                    WHERE TABLE_NAME = 'roles' 
                    AND INDEX_NAME = 'idx_roles_system'"
    ],
    [
        'name' => 'Add idx_permissions_module',
        'sql' => "CREATE INDEX idx_permissions_module ON permissions(module)",
        'check' => "SELECT INDEX_NAME 
                    FROM INFORMATION_SCHEMA.STATISTICS 
                    WHERE TABLE_NAME = 'permissions' 
                    AND INDEX_NAME = 'idx_permissions_module'"
    ],
    [
        'name' => 'Add idx_permissions_category',
        'sql' => "CREATE INDEX idx_permissions_category ON permissions(category)",
        'check' => "SELECT INDEX_NAME 
                    FROM INFORMATION_SCHEMA.STATISTICS 
                    WHERE TABLE_NAME = 'permissions' 
                    AND INDEX_NAME = 'idx_permissions_category'"
    ],
    [
        'name' => 'Add idx_permissions_deleted',
        'sql' => "CREATE INDEX idx_permissions_deleted ON permissions(deleted_at)",
        'check' => "SELECT INDEX_NAME 
                    FROM INFORMATION_SCHEMA.STATISTICS 
                    WHERE TABLE_NAME = 'permissions' 
                    AND INDEX_NAME = 'idx_permissions_deleted'"
    ],
    [
        'name' => 'Add idx_role_permissions_role',
        'sql' => "CREATE INDEX idx_role_permissions_role ON role_permissions(role_id)",
        'check' => "SELECT INDEX_NAME 
                    FROM INFORMATION_SCHEMA.STATISTICS 
                    WHERE TABLE_NAME = 'role_permissions' 
                    AND INDEX_NAME = 'idx_role_permissions_role'"
    ],
    [
        'name' => 'Add idx_role_permissions_permission',
        'sql' => "CREATE INDEX idx_role_permissions_permission ON role_permissions(permission_id)",
        'check' => "SELECT INDEX_NAME 
                    FROM INFORMATION_SCHEMA.STATISTICS 
                    WHERE TABLE_NAME = 'role_permissions' 
                    AND INDEX_NAME = 'idx_role_permissions_permission'"
    ],
    [
        'name' => 'Create get_role_permissions function',
        'sql' => "CREATE FUNCTION get_role_permissions(p_role_id INT) RETURNS TEXT DETERMINISTIC
                  BEGIN
                    DECLARE v_result TEXT DEFAULT '';
                    DECLARE v_current_id INT;
                    SET v_current_id = p_role_id;
                    WHILE v_current_id IS NOT NULL DO
                      SET v_result = CONCAT(v_result, (SELECT GROUP_CONCAT(permission_id) FROM role_permissions WHERE role_id = v_current_id), ',');
                      SELECT inherits_from INTO v_current_id FROM roles WHERE id = v_current_id;
                    END WHILE;
                    RETURN v_result;
                  END",
        'check' => "SELECT ROUTINE_NAME 
                    FROM INFORMATION_SCHEMA.ROUTINES 
                    WHERE ROUTINE_NAME = 'get_role_permissions' 
                    AND ROUTINE_SCHEMA = DATABASE()"
    ],
    [
        'name' => 'Add idx_business_types_active',
        'sql' => "CREATE INDEX idx_business_types_active ON business_types(is_active, sort_order)",
        'check' => "SELECT INDEX_NAME 
                    FROM INFORMATION_SCHEMA.STATISTICS 
                    WHERE TABLE_NAME = 'business_types' 
                    AND INDEX_NAME = 'idx_business_types_active'"
    ],
    [
        'name' => 'Add idx_business_types_code',
        'sql' => "CREATE INDEX idx_business_types_code ON business_types(code)",
        'check' => "SELECT INDEX_NAME 
                    FROM INFORMATION_SCHEMA.STATISTICS 
                    WHERE TABLE_NAME = 'business_types' 
                    AND INDEX_NAME = 'idx_business_types_code'"
    ],
    [
        'name' => 'Add idx_settings_company_category',
        'sql' => "CREATE INDEX idx_settings_company_category ON settings(tenant_id, category)",
        'check' => "SELECT INDEX_NAME 
                    FROM INFORMATION_SCHEMA.STATISTICS 
                    WHERE TABLE_NAME = 'settings' 
                    AND INDEX_NAME = 'idx_settings_company_category'"
    ],
    [
        'name' => 'Add idx_settings_key_lookup',
        'sql' => "CREATE INDEX idx_settings_key_lookup ON settings(tenant_id, setting_key)",
        'check' => "SELECT INDEX_NAME 
                    FROM INFORMATION_SCHEMA.STATISTICS 
                    WHERE TABLE_NAME = 'settings' 
                    AND INDEX_NAME = 'idx_settings_key_lookup'"
    ],
    [
        'name' => 'Add fk_products_tenant',
        'sql' => "ALTER TABLE products ADD CONSTRAINT fk_products_company 
                  FOREIGN KEY (tenant_id) REFERENCES companies(id) ON DELETE CASCADE",
        'check' => "SELECT CONSTRAINT_NAME 
                    FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS 
                    WHERE TABLE_NAME = 'products' 
                    AND CONSTRAINT_NAME = 'fk_products_tenant'"
    ],
    [
        'name' => 'Add fk_products_category',
        'sql' => "ALTER TABLE products ADD CONSTRAINT fk_products_category 
                  FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL",
        'check' => "SELECT CONSTRAINT_NAME 
                    FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS 
                    WHERE TABLE_NAME = 'products' 
                    AND CONSTRAINT_NAME = 'fk_products_category'"
    ],
    [
        'name' => 'Add fk_products_tax_rate',
        'sql' => "ALTER TABLE products ADD CONSTRAINT fk_products_tax_rate 
                  FOREIGN KEY (tax_rate_id) REFERENCES tax_rates(id) ON DELETE SET NULL",
        'check' => "SELECT CONSTRAINT_NAME 
                    FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS 
                    WHERE TABLE_NAME = 'products' 
                    AND CONSTRAINT_NAME = 'fk_products_tax_rate'"
    ],
    [
        'name' => 'Add fk_products_created_by',
        'sql' => "ALTER TABLE products ADD CONSTRAINT fk_products_created_by 
                  FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL",
        'check' => "SELECT CONSTRAINT_NAME 
                    FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS 
                    WHERE TABLE_NAME = 'products' 
                    AND CONSTRAINT_NAME = 'fk_products_created_by'"
    ],
    [
        'name' => 'Add fk_products_updated_by',
        'sql' => "ALTER TABLE products ADD CONSTRAINT fk_products_updated_by 
                  FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL",
        'check' => "SELECT CONSTRAINT_NAME 
                    FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS 
                    WHERE TABLE_NAME = 'products' 
                    AND CONSTRAINT_NAME = 'fk_products_updated_by'"
    ],
    [
        'name' => 'Add fk_products_deleted_by',
        'sql' => "ALTER TABLE products ADD CONSTRAINT fk_products_deleted_by 
                  FOREIGN KEY (deleted_by) REFERENCES users(id) ON DELETE SET NULL",
        'check' => "SELECT CONSTRAINT_NAME 
                    FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS 
                    WHERE TABLE_NAME = 'products' 
                    AND CONSTRAINT_NAME = 'fk_products_deleted_by'"
    ],
    [
        'name' => 'Add fk_products_branch',
        'sql' => "ALTER TABLE products ADD CONSTRAINT fk_products_branch 
                  FOREIGN KEY (branch_id) REFERENCES branches(id) ON DELETE SET NULL",
        'check' => "SELECT CONSTRAINT_NAME 
                    FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS 
                    WHERE TABLE_NAME = 'products' 
                    AND CONSTRAINT_NAME = 'fk_products_branch'"
    ],
    [
        'name' => 'Create product_price_history table',
        'sql' => "CREATE TABLE IF NOT EXISTS `product_price_history` (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `product_id` INT(11) NOT NULL,
            `old_price` DECIMAL(10,2) NOT NULL,
            `new_price` DECIMAL(10,2) NOT NULL,
            `old_cost` DECIMAL(10,2) DEFAULT NULL,
            `new_cost` DECIMAL(10,2) DEFAULT NULL,
            `changed_by` INT(11) NOT NULL,
            `change_reason` VARCHAR(255) DEFAULT NULL,
            `changed_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `idx_price_history_product` (`product_id`),
            KEY `idx_price_history_changed_at` (`changed_at`),
            FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE CASCADE,
            FOREIGN KEY (`changed_by`) REFERENCES `users`(`id`) ON DELETE RESTRICT
          ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        'check' => "SELECT TABLE_NAME 
                    FROM INFORMATION_SCHEMA.TABLES 
                    WHERE TABLE_NAME = 'product_price_history' 
                    AND TABLE_SCHEMA = DATABASE()"
    ]
];

$applied = 0;
$skipped = 0;
$errors = [];

foreach ($migrations as $migration) {
    echo "Processing: {$migration['name']}...\n";
    
    // Check if already exists
    try {
        $stmt = $pdo->query($migration['check']);
        if ($stmt && $stmt->rowCount() > 0) {
            echo "  -> Already exists, skipping\n";
            $skipped++;
            continue;
        }
    } catch (PDOException $e) {
        // Check failed, continue to try applying
    }
    
    // Apply migration
    try {
        $pdo->exec($migration['sql']);
        echo "  -> SUCCESS\n";
        $applied++;
    } catch (PDOException $e) {
        $error = $e->getMessage();
        echo "  -> ERROR: $error\n";
        $errors[] = ['name' => $migration['name'], 'error' => $error];
    }
}

echo "\n=== Migration Summary ===\n";
echo "Applied: $applied\n";
echo "Skipped: $skipped\n";
echo "Errors: " . count($errors) . "\n";

if (!empty($errors)) {
    echo "\nFailed migrations:\n";
    foreach ($errors as $err) {
        echo "  - {$err['name']}: {$err['error']}\n";
    }
    exit(1);
}

echo "\nAll migrations completed successfully!\n";
exit(0);
