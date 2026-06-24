<?php
/**
 * Migration 007 - Update business types to match config/app.php
 * Adds liquor_store and stationery, deactivates orphaned wholesale/service/grocery
 *
 * Run: php scripts/migrate.php --migration=007_update_business_types
 */

require_once __DIR__ . '/../../src/db.php';

class UpdateBusinessTypesMigration
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

            $this->addMissingBusinessTypes();
            $this->deactivateOrphanedTypes();
            $this->syncOrderTypesAndFeatures();
            $this->syncCanonicalBusinessTypes();

            $this->db->commit();

            echo "Business types updated successfully!\n";
            return true;

        } catch (PDOException $e) {
            $this->db->rollBack();
            error_log("Update Business Types Migration Error: " . $e->getMessage());
            echo "Migration failed: " . $e->getMessage() . "\n";
            return false;
        }
    }

    private function addMissingBusinessTypes(): void
    {
        $newTypes = [
            ['liquor_store', 'Liquor Store', 'fa-wine-bottle', '#7C3AED', 'Alcohol and beverage retail', 10],
            ['stationery', 'Stationery / Office', 'fa-pencil-alt', '#0EA5E9', 'Office and school supplies', 11],
        ];

        $stmt = $this->db->prepare("INSERT INTO {$this->prefix}business_types (slug, name, icon, color, description, sort_order) VALUES (?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE name = VALUES(name), icon = VALUES(icon), color = VALUES(color), description = VALUES(description), sort_order = VALUES(sort_order), is_active = 1");

        foreach ($newTypes as $type) {
            $stmt->execute($type);
            echo "  + Added/updated business type: {$type[0]}\n";
        }
    }

    private function deactivateOrphanedTypes(): void
    {
        $orphaned = ['wholesale', 'service', 'grocery'];
        $stmt = $this->db->prepare("UPDATE {$this->prefix}business_types SET is_active = 0 WHERE slug = ?");
        foreach ($orphaned as $slug) {
            $stmt->execute([$slug]);
            echo "  - Deactivated orphaned type: {$slug}\n";
        }
    }

    private function syncOrderTypesAndFeatures(): void
    {
        // Get IDs for new types
        $stmt = $this->db->prepare("SELECT id, slug FROM {$this->prefix}business_types WHERE slug IN ('liquor_store', 'stationery')");
        $stmt->execute();
        $typeIds = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $typeIds[$row['slug']] = (int)$row['id'];
        }

        if (empty($typeIds)) {
            echo "  ! No new business types found, skipping order types/features\n";
            return;
        }

        $orderTypes = [
            'liquor_store' => [
                ['walkin', 'Walk-in', 'fa-user', 0],
                ['wholesale', 'Wholesale', 'fa-boxes', 1],
                ['delivery', 'Delivery', 'fa-truck', 2],
            ],
            'stationery' => [
                ['walkin', 'Walk-in', 'fa-user', 0],
                ['online', 'Online', 'fa-globe', 1],
                ['wholesale', 'Wholesale', 'fa-boxes', 2],
                ['delivery', 'Delivery', 'fa-truck', 3],
            ],
        ];

        $features = [
            'liquor_store' => ['barcode_scanning', 'track_stock', 'age_verification', 'bulk_pricing'],
            'stationery' => ['barcode_scanning', 'track_stock', 'bulk_pricing'],
        ];

        // Insert order types (ignore duplicates)
        $orderStmt = $this->db->prepare("INSERT IGNORE INTO {$this->prefix}order_types (business_type_id, slug, label, icon, sort_order) VALUES (?, ?, ?, ?, ?)");
        foreach ($orderTypes as $slug => $orders) {
            if (!isset($typeIds[$slug])) continue;
            foreach ($orders as $order) {
                $orderStmt->execute(array_merge([$typeIds[$slug]], $order));
            }
        }

        // Insert features (ignore duplicates)
        $featureStmt = $this->db->prepare("INSERT IGNORE INTO {$this->prefix}business_features (business_type_id, feature_key) VALUES (?, ?)");
        foreach ($features as $slug => $featList) {
            if (!isset($typeIds[$slug])) continue;
            foreach ($featList as $feature) {
                $featureStmt->execute([$typeIds[$slug], $feature]);
            }
        }

        echo "  Synced order types and features for new business types\n";
    }

    private function syncCanonicalBusinessTypes(): void
    {
        // The business_types table (without pos_ prefix) is used by
        // get_current_business_type_id() and other legacy code paths.
        $canonicalTypes = [
            ['liquor_store', 'Liquor Store', 'fa-wine-bottle', 1],
            ['stationery', 'Stationery / Office', 'fa-pencil-alt', 1],
        ];

        // Check if canonical table exists
        $check = $this->db->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'business_types'")
            ->fetchColumn();
        if (!$check) {
            echo "  ! business_types table not found, skipping canonical sync\n";
            return;
        }

        $stmt = $this->db->prepare("INSERT INTO business_types (code, name, icon, active) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE name = VALUES(name), icon = VALUES(icon), active = VALUES(active)");
        foreach ($canonicalTypes as $type) {
            $stmt->execute($type);
            echo "  + Synced canonical business type: {$type[0]}\n";
        }

        // Deactivate orphaned canonical types
        $orphaned = ['wholesale', 'service', 'grocery'];
        $deactivateStmt = $this->db->prepare("UPDATE business_types SET active = 0 WHERE code = ?");
        foreach ($orphaned as $slug) {
            $deactivateStmt->execute([$slug]);
            echo "  - Deactivated canonical orphaned type: {$slug}\n";
        }
    }
}

// Run migration if called directly
if (php_sapi_name() === 'cli' && basename($argv[0]) === basename(__FILE__)) {
    $migration = new UpdateBusinessTypesMigration();
    exit($migration->up() ? 0 : 1);
}
