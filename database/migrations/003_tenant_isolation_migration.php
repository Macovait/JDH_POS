<?php
/**
 * Phase 1: Complete Tenant Isolation Migration
 * Adds tenant_id to all existing tables and migrates data for full multi-tenant support
 */

require_once __DIR__ . '/../../src/db.php';

class TenantIsolationMigration
{
    private PDO $db;

    public function __construct(PDO $db = null)
    {
        $this->db = $db ?? get_db_connection();
    }

    public function up(): bool
    {
        try {
            $this->db->beginTransaction();

            echo "Starting complete tenant isolation migration...\n";

            // Step 1: Ensure tenants table exists with proper structure
            $this->createTenantsTable();

            // Step 2: Add tenant_id to all business tables
            $this->addTenantIdToTables();

            // Step 3: Migrate existing companies to tenants
            $this->migrateCompaniesToTenants();

            // Step 4: Assign tenant_id to existing data
            $this->assignTenantIdsToData();

            // Step 5: Make tenant_id NOT NULL where appropriate
            $this->makeTenantIdRequired();

            // Step 6: Create tenant-aware indexes
            $this->createTenantIndexes();

            // Step 7: Create default tenant if none exists
            $this->createDefaultTenant();

            $this->db->commit();

            echo "Tenant isolation migration completed successfully!\n";
            return true;

        } catch (PDOException $e) {
            $this->db->rollBack();
            error_log("Tenant Isolation Migration Error: " . $e->getMessage());
            echo "Migration failed: " . $e->getMessage() . "\n";
            return false;
        }
    }

    private function createTenantsTable(): void
    {
        // Use the same structure as in 002_saas_tables.php
        $sql = "CREATE TABLE IF NOT EXISTS pos_tenants (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            uuid VARCHAR(36) UNIQUE NOT NULL,
            subdomain VARCHAR(63) UNIQUE,
            domain VARCHAR(255) UNIQUE,
            name VARCHAR(255) NOT NULL,
            slug VARCHAR(100) UNIQUE,
            status ENUM('active', 'suspended', 'cancelled', 'trial') DEFAULT 'trial',
            plan_id INT UNSIGNED DEFAULT 1,
            parent_tenant_id INT UNSIGNED,
            settings JSON,
            branding JSON,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_subdomain (subdomain),
            INDEX idx_uuid (uuid),
            INDEX idx_status (status),
            INDEX idx_slug (slug)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        $this->db->exec($sql);
        echo "Ensured pos_tenants table exists\n";
    }

    private function addTenantIdToTables(): void
    {
        $tables = [
            'users', 'branches', 'categories', 'products', 'product_variants',
            'product_images', 'inventory', 'customers', 'suppliers',
            'purchase_orders', 'purchase_order_items', 'sales', 'sale_items',
            'payments', 'discounts', 'vouchers', 'activity_logs', 'notifications',
            'expenses'
        ];

        foreach ($tables as $table) {
            try {
                // Check if tenant_id column exists
                $result = $this->db->query("SHOW COLUMNS FROM `$table` LIKE 'tenant_id'");
                if ($result->rowCount() == 0) {
                    // Add tenant_id column
                    $this->db->exec("ALTER TABLE `$table` ADD COLUMN tenant_id INT UNSIGNED NULL");
                    echo "Added tenant_id to $table\n";
                } else {
                    echo "tenant_id already exists in $table\n";
                }

                // Add index if it doesn't exist
                try {
                    $this->db->exec("ALTER TABLE `$table` ADD INDEX idx_tenant (tenant_id)");
                    echo "Added tenant index to $table\n";
                } catch (PDOException $e) {
                    // Index might already exist
                    echo "Index may already exist for $table\n";
                }

            } catch (PDOException $e) {
                echo "Error with table $table: " . $e->getMessage() . "\n";
            }
        }
    }

    private function migrateCompaniesToTenants(): void
    {
        // Check if companies table exists and has data
        try {
            $result = $this->db->query("SELECT COUNT(*) as count FROM companies");
            $count = $result->fetch(PDO::FETCH_ASSOC)['count'];

            if ($count > 0) {
                echo "Found $count companies to migrate\n";

                // Migrate companies to tenants
                $stmt = $this->db->prepare("
                    INSERT INTO pos_tenants (
                        uuid, name, slug, status, plan_id, settings, created_at, updated_at
                    )
                    SELECT
                        UUID() as uuid,
                        name,
                        LOWER(REPLACE(REPLACE(REPLACE(name, ' ', '_'), '-', '_'), '.', '')) as slug,
                        CASE
                            WHEN status = 'active' THEN 'active'
                            WHEN status = 'suspended' THEN 'suspended'
                            ELSE 'trial'
                        END as status,
                        COALESCE(current_plan_id, 1) as plan_id,
                        JSON_OBJECT(
                            'email', email,
                            'phone', phone,
                            'address', address,
                            'timezone', timezone,
                            'currency', currency,
                            'tax_rate', tax_rate,
                            'max_users', max_users,
                            'max_branches', max_branches,
                            'max_products', max_products
                        ) as settings,
                        created_at,
                        updated_at
                    FROM companies
                    WHERE deleted_at IS NULL
                ");

                $stmt->execute();
                $migrated = $stmt->rowCount();
                echo "Migrated $migrated companies to tenants\n";

                // Create mapping for updating foreign keys
                $this->createCompanyToTenantMapping();

            } else {
                echo "No companies found to migrate\n";
            }

        } catch (PDOException $e) {
            echo "Error migrating companies: " . $e->getMessage() . "\n";
        }
    }

    private function createCompanyToTenantMapping(): void
    {
        // Create a temporary mapping table
        $this->db->exec("
            CREATE TEMPORARY TABLE company_tenant_map (
                tenant_id INT PRIMARY KEY,
                tenant_id INT
            )
        ");

        $this->db->exec("
            INSERT INTO company_tenant_map (tenant_id, tenant_id)
            SELECT c.id, t.id
            FROM companies c
            JOIN pos_tenants t ON LOWER(REPLACE(REPLACE(REPLACE(c.name, ' ', '_'), '-', '_'), '.', '')) = t.slug
        ");

        echo "Created company to tenant mapping\n";
    }

    private function assignTenantIdsToData(): void
    {
        $tableMappings = [
            'users' => 'tenant_id',
            'branches' => 'tenant_id',
            'categories' => null, // Categories might be global or per-company
            'products' => null,   // Products might be global or per-company
            'product_variants' => null,
            'product_images' => null,
            'inventory' => null,
            'customers' => null,
            'suppliers' => null,
            'purchase_orders' => 'branch_id', // Through branch
            'purchase_order_items' => null,
            'sales' => 'branch_id', // Through branch
            'sale_items' => null,
            'payments' => null,
            'activity_logs' => 'user_id', // Through user
            'notifications' => 'user_id', // Through user
        ];

        // For tables with direct tenant_id
        $directTables = ['users', 'branches'];
        foreach ($directTables as $table) {
            try {
                $this->db->exec("
                    UPDATE `$table` t
                    JOIN company_tenant_map ctm ON t.tenant_id = ctm.tenant_id
                    SET t.tenant_id = ctm.tenant_id
                    WHERE t.tenant_id IS NULL
                ");
                echo "Assigned tenant_id to $table\n";
            } catch (PDOException $e) {
                echo "Error assigning tenant_id to $table: " . $e->getMessage() . "\n";
            }
        }

        // For sales through branch
        try {
            $this->db->exec("
                UPDATE sales s
                JOIN branches b ON s.branch_id = b.id
                JOIN company_tenant_map ctm ON b.tenant_id = ctm.tenant_id
                SET s.tenant_id = ctm.tenant_id
                WHERE s.tenant_id IS NULL
            ");
            echo "Assigned tenant_id to sales through branches\n";
        } catch (PDOException $e) {
            echo "Error assigning tenant_id to sales: " . $e->getMessage() . "\n";
        }

        // For activity_logs through user
        try {
            $this->db->exec("
                UPDATE activity_logs al
                JOIN users u ON al.user_id = u.id
                SET al.tenant_id = u.tenant_id
                WHERE al.tenant_id IS NULL AND u.tenant_id IS NOT NULL
            ");
            echo "Assigned tenant_id to activity_logs through users\n";
        } catch (PDOException $e) {
            echo "Error assigning tenant_id to activity_logs: " . $e->getMessage() . "\n";
        }

        // For global tables, assign to default tenant
        $globalTables = ['categories', 'products', 'product_variants', 'product_images', 'inventory', 'customers', 'suppliers'];
        $defaultTenantId = $this->getDefaultTenantId();

        if ($defaultTenantId) {
            foreach ($globalTables as $table) {
                try {
                    $this->db->exec("UPDATE `$table` SET tenant_id = $defaultTenantId WHERE tenant_id IS NULL");
                    echo "Assigned default tenant_id to $table\n";
                } catch (PDOException $e) {
                    echo "Error assigning default tenant_id to $table: " . $e->getMessage() . "\n";
                }
            }
        }
    }

    private function makeTenantIdRequired(): void
    {
        $requiredTables = [
            'users', 'branches', 'sales', 'activity_logs', 'notifications'
        ];

        foreach ($requiredTables as $table) {
            try {
                $this->db->exec("ALTER TABLE `$table` MODIFY COLUMN tenant_id INT UNSIGNED NOT NULL");
                echo "Made tenant_id required for $table\n";
            } catch (PDOException $e) {
                echo "Error making tenant_id required for $table: " . $e->getMessage() . "\n";
            }
        }
    }

    private function createTenantIndexes(): void
    {
        $indexes = [
            "CREATE INDEX IF NOT EXISTS idx_users_tenant_active ON users(tenant_id, status)",
            "CREATE INDEX IF NOT EXISTS idx_branches_tenant ON branches(tenant_id)",
            "CREATE INDEX IF NOT EXISTS idx_products_tenant_active ON products(tenant_id, active)",
            "CREATE INDEX IF NOT EXISTS idx_sales_tenant_created ON sales(tenant_id, created_at)",
            "CREATE INDEX IF NOT EXISTS idx_inventory_tenant ON inventory(tenant_id, product_id, branch_id)",
            "CREATE INDEX IF NOT EXISTS idx_activity_logs_tenant_created ON activity_logs(tenant_id, created_at)",
        ];

        foreach ($indexes as $index) {
            try {
                $this->db->exec($index);
            } catch (PDOException $e) {
                // Index might already exist
            }
        }
        echo "Created tenant-aware indexes\n";
    }

    private function createDefaultTenant(): void
    {
        // Check if any tenants exist
        $result = $this->db->query("SELECT COUNT(*) as count FROM pos_tenants");
        $count = $result->fetch(PDO::FETCH_ASSOC)['count'];

        if ($count == 0) {
            // Create a default tenant
            $uuid = $this->generateUUID();
            $stmt = $this->db->prepare("
                INSERT INTO pos_tenants (uuid, name, slug, status, plan_id, settings)
                VALUES (?, 'Default Tenant', 'default', 'active', 1, '{}')
            ");
            $stmt->execute([$uuid]);
            echo "Created default tenant\n";
        }
    }

    private function getDefaultTenantId(): ?int
    {
        $stmt = $this->db->query("SELECT id FROM pos_tenants WHERE slug = 'default' LIMIT 1");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ? (int)$result['id'] : null;
    }

    private function generateUUID(): string
    {
        return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
    }
}

// Run migration
if (php_sapi_name() === 'cli' && realpath($argv[0] ?? '') === realpath(__FILE__)) {
    $db = get_db_connection();
    $migration = new TenantIsolationMigration($db);
    $result = $migration->up();
    exit($result ? 0 : 1);
}