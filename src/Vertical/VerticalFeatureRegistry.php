<?php
/**
 * Vertical Feature Framework
 * 
 * Enables industry-specific modules for Fine Dining, Pharmacy, and High Retail.
 * Provides deep vertical customization without code bloat.
 * 
 * @package JDH_POS\Vertical
 * @version 1.0.0
 */

namespace JDH_POS\Vertical;

use PDO;
use Exception;

class VerticalFeatureRegistry
{
    private static ?VerticalFeatureRegistry $instance = null;
    private PDO $db;
    private array $enabledFeatures = [];
    
    const VERTICALS = [
        'fine_dining' => [
            'name' => 'Fine Dining',
            'features' => [
                'table_management',
                'course_timing',
                'split_by_item',
                'staff_sections',
                'table_reservation',
                'waiting_list',
                'restaurant_receipt'
            ]
        ],
        'pharmacy' => [
            'name' => 'Pharmacy/Healthcare',
            'features' => [
                'rxndrug_check',
                'prescription_log',
                'batch_expiry',
                'narco_compliance',
                'drug_interactions',
                'temp_storage_log',
                'controlled_substances'
            ]
        ],
        'high_retail' => [
            'name' => 'High-End Retail',
            'features' => [
                'consignment',
                'commission_tiers',
                'rfid_inventory',
                'layaway',
                'special_order',
                'gift_registry',
                'appointment_booking'
            ]
        ]
    ];
    
    private function __construct(PDO $db)
    {
        $this->db = $db;
    }
    
    private function hasTable(string $table): bool
    {
        $stmt = $this->db->prepare('SHOW TABLES LIKE ?');
        $stmt->execute([$table]);
        return (bool) $stmt->fetchColumn();
    }
    
    private function hasColumn(string $table, string $column): bool
    {
        $stmt = $this->db->prepare("SHOW COLUMNS FROM {$table} LIKE ?");
        $stmt->execute([$column]);
        return (bool) $stmt->fetchColumn();
    }
    
    public static function getInstance(PDO $db = null): VerticalFeatureRegistry
    {
        if (self::$instance === null) {
            if ($db === null) {
                $db = get_db_connection();
            }
            self::$instance = new self($db);
        }
        
        return self::$instance;
    }
    
    public function getEnabledFeatures(int $companyId): array
    {
        if (!$this->hasTable('company_verticals')) {
            return [];
        }
        
        if (isset($this->enabledFeatures[$companyId])) {
            return $this->enabledFeatures[$companyId];
        }
        
        $stmt = $this->db->prepare("
            SELECT vertical_type, features_json
            FROM company_verticals
            WHERE tenant_id = ? AND active = 1
        ");
        
        $stmt->execute([$companyId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $this->enabledFeatures[$companyId] = $result ? json_decode($result['features_json'], true) : [];
        
        return $this->enabledFeatures[$companyId];
    }
    
    public function hasFeature(int $companyId, string $feature): bool
    {
        $features = $this->getEnabledFeatures($companyId);
        return in_array($feature, $features);
    }
    
    public function enableVertical(int $companyId, string $vertical): bool
    {
        if (!isset(self::VERTICALS[$vertical]) || !$this->hasTable('company_verticals')) {
            return false;
        }
        
        $features = self::VERTICALS[$vertical]['features'];
        
        try {
            $stmt = $this->db->prepare("
                INSERT INTO company_verticals (tenant_id, vertical_type, features_json, active)
                VALUES (?, ?, ?, 1)
                ON DUPLICATE KEY UPDATE 
                    vertical_type = VALUES(vertical_type),
                    features_json = VALUES(features_json),
                    active = 1
            ");
            
            $stmt->execute([$companyId, $vertical, json_encode($features)]);
            
            // Clear cache
            unset($this->enabledFeatures[$companyId]);
            
            return true;
            
        } catch (Exception $e) {
            return false;
        }
    }
    
    // ============================================
    // FINE DINING MODULES
    // ============================================
    
    public function getTableLayout(int $companyId): array
    {
        if (!$this->hasTable('restaurant_tables')) {
            return [];
        }
        
        $stmt = $this->db->prepare("
            SELECT * FROM restaurant_tables
            WHERE tenant_id = ? AND deleted_at IS NULL
            ORDER BY floor, table_number
        ");
        
        $stmt->execute([$companyId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function getTableStatus(int $tableId): array
    {
        if (!$this->hasTable('restaurant_tables')) {
            return [];
        }
        
        $stmt = $this->db->prepare("
            SELECT rt.*, 
                (SELECT COUNT(*) FROM orders o 
                 WHERE o.table_id = rt.id AND o.status IN ('open', 'in_progress')) as active_orders
            FROM restaurant_tables rt
            WHERE rt.id = ?
        ");
        
        $stmt->execute([$tableId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    public function manageCourseTiming(string $orderId, int $courseNumber): bool
    {
        if (!$this->hasTable('order_courses')) {
            return false;
        }
        
        $stmt = $this->db->prepare("
            INSERT INTO order_courses (order_id, course_number, status)
            VALUES (?, ?, 'active')
            ON DUPLICATE KEY UPDATE status = 'active', course_number = ?
        ");
        
        return $stmt->execute([$orderId, $courseNumber, $courseNumber]);
    }
    
    // ============================================
    // PHARMACY MODULES  
    // ============================================
    
    public function checkRXNCompliance(int $productId, array $customerHistory): bool
    {
        if (!$this->hasFeature($_SESSION['tenant_id'] ?? 0, 'rxndrug_check')) {
            return true; // Not pharmacy mode
        }
        
        if (!$this->hasColumn('products', 'is_controlled_drug')) {
            return true;
        }
        
        // Check if product is controlled substance
        $stmt = $this->db->prepare("
            SELECT is_controlled_drug FROM products WHERE id = ? AND deleted_at IS NULL
        ");
        
        $stmt->execute([$productId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$result || !$result['is_controlled_drug']) {
            return true; // Not controlled
        }
        
        // Validate prescription exists
        $hasPrescription = $this->hasValidPrescription($customerHistory['customer_id'], $productId);
        
        if (!$hasPrescription) {
            throw new Exception('Valid prescription required for this medication');
        }
        
        return true;
    }
    
    private function hasValidPrescription(int $customerId, int $productId): bool
    {
        if (!$this->hasTable('prescriptions')) {
            return false;
        }
        
        $stmt = $this->db->prepare("
            SELECT 1 FROM prescriptions
            WHERE customer_id = ?
            AND status = 'dispensed'
            LIMIT 1
        ");
        
        $stmt->execute([$customerId]);
        return (bool) $stmt->fetchColumn();
    }
    
    public function logBatchExpiry(int $batchId, array $details): bool
    {
        if (!$this->hasTable('batch_expiry_log')) {
            return false;
        }
        
        $stmt = $this->db->prepare("
            INSERT INTO batch_expiry_log (batch_id, product_id, expiry_date, quantity, logged_at)
            VALUES (?, ?, ?, ?, NOW())
        ");
        
        return $stmt->execute([
            $batchId,
            $details['product_id'],
            $details['expiry_date'],
            $details['quantity']
        ]);
    }
    
    // ============================================
    // HIGH RETAIL MODULES
    // ============================================
    
    public function calculateConsignmentCommission(float $saleAmount, int $productId, int $salespersonId): array
    {
        if (!$this->hasTable('consignment_rules')) {
            return ['commission' => 0, 'tier' => 'standard'];
        }
        
        $stmt = $this->db->prepare("
            SELECT cr.*, sp.commission_rate
            FROM consignment_rules cr
            JOIN sales_persons sp ON cr.tier_id = sp.tier_id
            WHERE cr.product_id = ?
            ORDER BY cr.min_price DESC
            LIMIT 1
        ");
        
        $stmt->execute([$productId]);
        $rule = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$rule) {
            return ['commission' => 0, 'tier' => 'standard'];
        }
        
        // Commission tiers
        $tiers = [
            'standard' => 0.05,
            'silver' => 0.08,
            'gold' => 0.10,
            'platinum' => 0.15
        ];
        
        $rate = $tiers[$rule['tier']] ?? 0.05;
        $commission = $saleAmount * $rate;
        
        return [
            'commission' => round($commission, 2),
            'rate' => $rate,
            'tier' => $rule['tier'],
            'consignment_rule_id' => $rule['id']
        ];
    }
    
    public function processLayaway(float $initialPayment, int $productId, int $customerId): int
    {
        if (!$this->hasTable('layaway_accounts')) {
            return 0;
        }
        
        $stmt = $this->db->prepare("
            INSERT INTO layaway_accounts (customer_id, product_id, total_price, paid_amount, status, created_at)
            VALUES (?, ?, (SELECT price FROM products WHERE id = ? AND deleted_at IS NULL), ?, 'active', NOW())
        ");
        
        $stmt->execute([$customerId, $productId, $productId, $initialPayment]);
        
        return (int) $this->db->lastInsertId();
    }
    
    // ============================================
    // API INTERFACE
    // ============================================
    
    public static function checkFeature(string $feature): bool
    {
        $instance = self::getInstance();
        $companyId = $_SESSION['tenant_id'] ?? 0;
        
        return $instance->checkFeature($companyId, $feature);
    }
}

// ============================================
// VERTICAL-SPECIFIC SERVICES
// ============================================

class FineDiningService
{
    private PDO $db;
    
    public function __construct(PDO $db)
    {
        $this->db = $db;
    }
    
    private function hasTable(string $table): bool
    {
        $stmt = $this->db->prepare('SHOW TABLES LIKE ?');
        $stmt->execute([$table]);
        return (bool) $stmt->fetchColumn();
    }
    
    public function splitBillByItem(int $orderId, int $splitCount): array
    {
        $items = $this->getOrderItems($orderId);
        
        $splitItems = array_chunk($items, ceil(count($items) / $splitCount));
        
        $splits = [];
        
        foreach ($splitItems as $index => $splitGroup) {
            $total = array_sum(array_column($splitGroup, 'total'));
            
            $splits[$index] = [
                'split_number' => $index + 1,
                'items' => $splitGroup,
                'subtotal' => round($total, 2),
                'tax' => round($total * 0.16, 2), // 16% VAT
                'total' => round($total * 1.16, 2)
            ];
        }
        
        return $splits;
    }
    
    private function getOrderItems(int $orderId): array
    {
        if (!$this->hasTable('order_items')) {
            return [];
        }
        
        $stmt = $this->db->prepare("
            SELECT * FROM order_items WHERE order_id = ?
        ");
        
        $stmt->execute([$orderId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

class PharmacyService
{
    private PDO $db;
    
    public function __construct(PDO $db)
    {
        $this->db = $db;
    }
    
    public function checkDrugInteractions(array $prescribedProducts): array
    {
        // Simplified interaction check - real implementation would use drug database
        $warnings = [];
        
        // Known problematic combinations (simplified example)
        $knownInteractions = [
            'antibiotic' => ['alcohol'],
            'sedative' => ['opioid']
        ];
        
        $categories = array_column($prescribedProducts, 'category');
        
        foreach ($categories as $cat1) {
            foreach ($categories as $cat2) {
                if ($cat1 !== $cat2 && isset($knownInteractions[$cat1]) && in_array($cat2, $knownInteractions[$cat1])) {
                    $warnings[] = "Warning: Potential interaction between {$cat1} and {$cat2}";
                }
            }
        }
        
        return $warnings;
    }
}

class HighRetailService
{
    private PDO $db;
    
    public function __construct(PDO $db)
    {
        $this->db = $db;
    }
    
    private function hasTable(string $table): bool
    {
        $stmt = $this->db->prepare('SHOW TABLES LIKE ?');
        $stmt->execute([$table]);
        return (bool) $stmt->fetchColumn();
    }
    
    public function processSpecialOrder(array $orderDetails): int
    {
        if (!$this->hasTable('special_orders')) {
            return 0;
        }
        
        $stmt = $this->db->prepare("
            INSERT INTO special_orders (
                customer_id, product_id, deposit_amount, 
                total_price, status, expected_delivery
            ) VALUES (?, ?, ?, ?, 'pending', DATE_ADD(NOW(), INTERVAL 14 DAY))
        ");
        
        $stmt->execute([
            $orderDetails['customer_id'],
            $orderDetails['product_id'],
            $orderDetails['deposit_amount'],
            $orderDetails['total_price']
        ]);
        
        return (int) $this->db->lastInsertId();
    }
    
    public function manageGiftRegistry(int $customerId, array $items): bool
    {
        if (!$this->hasTable('gift_registry')) {
            return false;
        }
        
        $stmt = $this->db->prepare("
            INSERT INTO gift_registry (customer_id, product_id, quantity_fulfilled)
            VALUES (?, ?, 0)
            ON DUPLICATE KEY UPDATE quantity_fulfilled = quantity_fulfilled + 1
        ");
        
        foreach ($items as $item) {
            $stmt->execute([$customerId, $item['product_id']]);
        }
        
        return true;
    }
}

// ============================================
// HELPER FUNCTIONS
// ============================================

function has_vertical_feature(string $feature): bool
{
    return VerticalFeatureRegistry::checkFeature($feature);
}

function vertical_check_rxndrug(int $productId, array $history): bool
{
    $registry = VerticalFeatureRegistry::getInstance();
    return $registry->checkRXNCompliance($productId, $history);
}