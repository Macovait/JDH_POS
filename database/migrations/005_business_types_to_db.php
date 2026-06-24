<?php
/**
 * Migration 005 - Move hardcoded business types to database
 * Creates business_types table and migrates configuration from pos.php
 * 
 * Run: php scripts/migrate.php --migration=005_business_types_to_db
 */

require_once __DIR__ . '/../../src/db.php';

class BusinessTypesMigration
{
    private PDO $db;
    private string $prefix;

    public function __construct(PDO $db = null)
    {
        $this->db = $db ?? get_db_connection();
        $this->prefix = 'pos_';
    }

    public function up(): bool
    {
        try {
            $this->db->beginTransaction();

            $this->createBusinessTypesTable();
            $this->createOrderTypesTable();
            $this->createBusinessFeaturesTable();
            $this->seedBusinessTypes();

            $this->db->commit();
            
            echo "Business types migration completed successfully!\n";
            return true;

        } catch (PDOException $e) {
            $this->db->rollBack();
            error_log("Business Types Migration Error: " . $e->getMessage());
            echo "Migration failed: " . $e->getMessage() . "\n";
            return false;
        }
    }

    private function createBusinessTypesTable(): void
    {
        $sql = "CREATE TABLE IF NOT EXISTS {$this->prefix}business_types (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            slug VARCHAR(50) UNIQUE NOT NULL,
            name VARCHAR(100) NOT NULL,
            icon VARCHAR(50) DEFAULT 'fa-store',
            color VARCHAR(7) DEFAULT '#3B82F6',
            description TEXT,
            is_active BOOLEAN DEFAULT TRUE,
            sort_order INT DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_slug (slug),
            INDEX idx_active (is_active),
            INDEX idx_sort (sort_order)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $this->db->exec($sql);
        echo "  ✓ Created business_types table\n";
    }

    private function createOrderTypesTable(): void
    {
        $sql = "CREATE TABLE IF NOT EXISTS {$this->prefix}order_types (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            business_type_id INT UNSIGNED NOT NULL,
            slug VARCHAR(50) NOT NULL,
            label VARCHAR(100) NOT NULL,
            icon VARCHAR(50) DEFAULT 'fa-circle',
            is_active BOOLEAN DEFAULT TRUE,
            sort_order INT DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uk_business_order (business_type_id, slug),
            FOREIGN KEY (business_type_id) REFERENCES {$this->prefix}business_types(id) ON DELETE CASCADE,
            INDEX idx_active (is_active)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $this->db->exec($sql);
        echo "  ✓ Created order_types table\n";
    }

    private function createBusinessFeaturesTable(): void
    {
        $sql = "CREATE TABLE IF NOT EXISTS {$this->prefix}business_features (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            business_type_id INT UNSIGNED NOT NULL,
            feature_key VARCHAR(50) NOT NULL,
            is_enabled BOOLEAN DEFAULT TRUE,
            config JSON,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uk_business_feature (business_type_id, feature_key),
            FOREIGN KEY (business_type_id) REFERENCES {$this->prefix}business_types(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $this->db->exec($sql);
        echo "  ✓ Created business_features table\n";
    }

    private function seedBusinessTypes(): void
    {
        // Check if already seeded
        $stmt = $this->db->query("SELECT COUNT(*) FROM {$this->prefix}business_types");
        if ($stmt->fetchColumn() > 0) {
            echo "  ℹ Business types already seeded\n";
            return;
        }

        // Hardcoded business types (moved from pos.php)
        $businessTypes = [
            ['retail', 'Retail Store', 'fa-store', '#3B82F6', 'General retail store'],
            ['supermarket', 'Supermarket', 'fa-shopping-cart', '#10B981', 'Large retail store with multiple categories'],
            ['restaurant', 'Restaurant / Cafe', 'fa-utensils', '#F59E0B', 'Food service establishment'],
            ['pharmacy', 'Pharmacy', 'fa-pills', '#EF4444', 'Pharmaceutical and medical supplies'],
            ['salon', 'Salon / Beauty', 'fa-cut', '#EC4899', 'Beauty and personal care services'],
            ['electronics', 'Electronics', 'fa-laptop', '#8B5CF6', 'Electronic devices and accessories'],
            ['hardware', 'Hardware Store', 'fa-hammer', '#F97316', 'Construction and home improvement supplies'],
            ['butchery', 'Butchery', 'fa-drumstick-bite', '#DC2626', 'Fresh meat and poultry'],
            ['bakery', 'Bakery', 'fa-bread-slice', '#D97706', 'Fresh baked goods'],
            ['hotel', 'Hotel / Lodging', 'fa-hotel', '#6366F1', 'Hospitality and accommodation'],
            ['wholesale', 'Wholesale', 'fa-boxes', '#06B6D4', 'Bulk sales and distribution'],
            ['service', 'Service Business', 'fa-concierge-bell', '#14B8A6', 'Professional services'],
            ['grocery', 'Grocery Store', 'fa-apple-alt', '#22C55E', 'Food and household items'],
        ];

        $orderTypes = [
            'retail' => [['walkin', 'Walk-in', 'fa-user']],
            'supermarket' => [['walkin', 'Walk-in', 'fa-user'], ['online', 'Online', 'fa-globe']],
            'restaurant' => [['dinein', 'Dine-in', 'fa-chair'], ['takeaway', 'Takeaway', 'fa-bag'], ['delivery', 'Delivery', 'fa-truck']],
            'pharmacy' => [['walkin', 'Walk-in', 'fa-user'], ['prescription', 'Prescription', 'fa-file-medical']],
            'salon' => [['appointment', 'Appointment', 'fa-calendar'], ['walkin', 'Walk-in', 'fa-user']],
            'electronics' => [['retail', 'Retail', 'fa-store'], ['installment', 'Installment', 'fa-calendar-check']],
            'hardware' => [['retail', 'Retail', 'fa-store'], ['wholesale', 'Wholesale', 'fa-boxes']],
            'butchery' => [['retail', 'Retail', 'fa-store'], ['wholesale', 'Wholesale', 'fa-boxes']],
            'bakery' => [['dinein', 'Dine-in', 'fa-chair'], ['takeaway', 'Takeaway', 'fa-bag'], ['delivery', 'Delivery', 'fa-truck']],
            'hotel' => [['room', 'Room Service', 'fa-bed'], ['dinein', 'Restaurant', 'fa-utensils']],
            'wholesale' => [['wholesale', 'Wholesale', 'fa-boxes'], ['online', 'Online', 'fa-globe']],
            'service' => [['service', 'Service', 'fa-hand-sparkles'], ['appointment', 'Appointment', 'fa-calendar']],
            'grocery' => [['walkin', 'Walk-in', 'fa-user'], ['online', 'Online', 'fa-globe'], ['delivery', 'Delivery', 'fa-truck']],
        ];

        $features = [
            'retail' => ['barcode', 'stock'],
            'supermarket' => ['barcode', 'stock', 'weight'],
            'restaurant' => ['kitchen_notes', 'table'],
            'pharmacy' => ['prescription', 'stock'],
            'salon' => ['appointment'],
            'electronics' => ['serial', 'warranty'],
            'hardware' => ['barcode', 'stock', 'weight'],
            'butchery' => ['weight', 'stock'],
            'bakery' => ['expiry', 'stock'],
            'hotel' => ['room_charge'],
            'wholesale' => ['bulk', 'discount'],
            'service' => ['appointment'],
            'grocery' => ['barcode', 'stock', 'weight'],
        ];

        $stmt = $this->db->prepare("INSERT INTO {$this->prefix}business_types (slug, name, icon, color, description, sort_order) VALUES (?, ?, ?, ?, ?, ?)");
        $orderStmt = $this->db->prepare("INSERT INTO {$this->prefix}order_types (business_type_id, slug, label, icon, sort_order) VALUES (?, ?, ?, ?, ?)");
        $featureStmt = $this->db->prepare("INSERT INTO {$this->prefix}business_features (business_type_id, feature_key) VALUES (?, ?)");

        foreach ($businessTypes as $index => $type) {
            $stmt->execute([$type[0], $type[1], $type[2], $type[3], $type[4], $index]);
            $businessTypeId = $this->db->lastInsertId();

            // Insert order types
            if (isset($orderTypes[$type[0]])) {
                foreach ($orderTypes[$type[0]] as $orderIndex => $order) {
                    $orderStmt->execute([$businessTypeId, $order[0], $order[1], $order[2], $orderIndex]);
                }
            }

            // Insert features
            if (isset($features[$type[0]])) {
                foreach ($features[$type[0]] as $feature) {
                    $featureStmt->execute([$businessTypeId, $feature]);
                }
            }
        }

        echo "  ✓ Seeded " . count($businessTypes) . " business types\n";
    }
}

// Run migration if called directly
if (php_sapi_name() === 'cli' && basename($argv[0]) === basename(__FILE__)) {
    $migration = new BusinessTypesMigration();
    exit($migration->up() ? 0 : 1);
}
