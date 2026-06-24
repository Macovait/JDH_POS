<?php
/**
 * Vendor PO Automation Service
 * 
 * Automates vendor management and purchase order generation.
 * Includes auto-reorder points, lead time optimization, and vendor scoring.
 * 
 * @package JDH_POS\Operations
 * @version 1.0.0
 */

namespace JDH_POS\Operations;

use PDO;
use Exception;

class VendorPOService
{
    private PDO $db;
    
    public function __construct(PDO $db)
    {
        $this->db = $db;
    }
    
    // ============================================
    // AUTO PURCHASE ORDER GENERATION
    // ============================================
    
    public function generateAutoPOs(int $companyId, int $branchId = null): array
    {
        $result = [
            'tenant_id' => $companyId,
            'branch_id' => $branchId,
            'purchase_orders' => [],
            'generated_at' => date('Y-m-d H:i:s')
        ];
        
        // Get products below reorder point
        $productsToReorder = $this->getProductsToReorder($companyId, $branchId);
        
        if (empty($productsToReorder)) {
            return $result;
        }
        
        $defaultVendorId = $this->getDefaultSupplierId($companyId);
        if (!$defaultVendorId) {
            return $result;
        }
        
        if ($branchId === null) {
            $branchId = $this->getDefaultBranchId($companyId);
            if (!$branchId) {
                return $result;
            }
        }
        
        // Group by vendor
        $byVendor = [];
        foreach ($productsToReorder as $product) {
            $vendorId = (int) ($product['vendor_id'] ?: $defaultVendorId);
            
            if (!isset($byVendor[$vendorId])) {
                $byVendor[$vendorId] = [
                    'vendor' => [
                        'id' => $vendorId,
                        'name' => $product['vendor_name'],
                        'lead_time_days' => $product['lead_time_days'] ?? 7
                    ],
                    'items' => []
                ];
            }
            
            $byVendor[$vendorId]['items'][] = [
                'product_id' => $product['id'],
                'product_name' => $product['name'],
                'current_stock' => $product['stock'],
                'reorder_point' => $product['reorder_point'],
                'suggested_qty' => $product['suggested_qty'],
                'unit_cost' => $product['unit_cost'],
                'total_cost' => $product['suggested_qty'] * $product['unit_cost']
            ];
        }
        
        // Generate PO for each vendor
        foreach ($byVendor as $vendorId => $vendorData) {
            $po = $this->createPO($companyId, $vendorBranchId = $branchId, $vendorData);
            $result['purchase_orders'][] = $po;
        }
        
        return $result;
    }
    
    private function getDefaultSupplierId(int $companyId): ?int
    {
        $stmt = $this->db->prepare("
            SELECT id FROM suppliers
            WHERE tenant_id = ? AND deleted_at IS NULL
            ORDER BY id
            LIMIT 1
        ");
        $stmt->execute([$companyId]);
        $supplier = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $supplier ? (int) $supplier['id'] : null;
    }
    
    private function getDefaultBranchId(int $companyId): ?int
    {
        $stmt = $this->db->prepare("
            SELECT id FROM branches
            WHERE tenant_id = ? AND active = 1 AND deleted_at IS NULL
            ORDER BY id
            LIMIT 1
        ");
        $stmt->execute([$companyId]);
        $branch = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $branch ? (int) $branch['id'] : null;
    }
    
    private function getProductsToReorder(int $companyId, ?int $branchId): array
    {
        $params = [$companyId];
        $inventoryJoin = "LEFT JOIN inventory i ON i.product_id = p.id AND i.tenant_id = p.tenant_id";
        $inventoryGroup = "COALESCE(SUM(i.stock), 0)";
        
        if ($branchId !== null) {
            $params[] = $branchId;
            $inventoryJoin .= " AND i.branch_id = ?";
        }
        
        $sql = "
            SELECT 
                p.id,
                p.name,
                NULL AS vendor_id,
                'Default Supplier' AS vendor_name,
                7 AS lead_time_days,
                {$inventoryGroup} AS stock,
                p.reorder_level AS reorder_point,
                p.cost_price AS unit_cost,
                GREATEST(COALESCE(p.reorder_level, 0) - {$inventoryGroup}, 0) AS suggested_qty,
                COALESCE(p.reorder_level, 0) AS max_stock
            FROM products p
            {$inventoryJoin}
            WHERE p.tenant_id = ?
            AND p.deleted_at IS NULL
            AND p.active = 1
            GROUP BY p.id
            HAVING stock <= COALESCE(p.reorder_level, p.low_stock_amount, 0)
            ORDER BY stock ASC
        ";
        
        $params[] = $companyId;
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    private function createPO(int $companyId, ?int $branchId, array $vendorData): array
    {
        // Calculate totals
        $totalItems = count($vendorData['items']);
        $totalCost = array_sum(array_column($vendorData['items'], 'total_cost'));
        
        // Expected delivery date
        $leadTime = $vendorData['vendor']['lead_time_days'];
        $expectedDate = date('Y-m-d', strtotime("+{$leadTime} days"));
        
        // Generate PO reference
        $poNumber = 'PO-' . date('Ymd') . '-' . strtoupper(substr($vendorData['vendor']['name'], 0, 3));
        
        $po = [
            'po_number' => $poNumber,
            'supplier_id' => $vendorData['vendor']['id'],
            'vendor_name' => $vendorData['vendor']['name'],
            'branch_id' => $branchId,
            'status' => 'draft',
            'expected_date' => $expectedDate,
            'total_items' => $totalItems,
            'total_cost' => round($totalCost, 2),
            'items' => $vendorData['items'],
            'created_at' => date('Y-m-d H:i:s')
        ];
        
        // Save to database
        $this->savePO($companyId, $po);
        
        return $po;
    }
    
    private function savePO(int $companyId, array $po): void
    {
        try {
            $this->db->beginTransaction();
            
            // Insert PO header
            $stmt = $this->db->prepare("
                INSERT INTO purchase_orders (
                    tenant_id, branch_id, supplier_id, po_number,
                    status, expected_date, total
                ) VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $companyId,
                $po['branch_id'],
                $po['supplier_id'],
                $po['po_number'],
                $po['status'],
                $po['expected_date'],
                $po['total_cost']
            ]);
            
            $poId = $this->db->lastInsertId();
            
            // Insert PO items
            $itemStmt = $this->db->prepare("
                INSERT INTO purchase_order_items (
                    tenant_id, purchase_order_id, product_id, quantity, cost_price
                ) VALUES (?, ?, ?, ?, ?)
            ");
            
            foreach ($po['items'] as $item) {
                $itemStmt->execute([
                    $companyId,
                    $poId,
                    $item['product_id'],
                    $item['suggested_qty'],
                    $item['unit_cost']
                ]);
            }
            
            $this->db->commit();
            
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }
    
    // ============================================
    // VENDOR MANAGEMENT
    // ============================================
    
    public function getVendorScore(int $vendorId): array
    {
        $score = [
            'vendor_id' => $vendorId,
            'overall_score' => 0,
            'fulfillment_rate' => 0,
            'lead_time_score' => 0,
            'quality_score' => 0,
            'calculated_at' => date('Y-m-d H:i:s')
        ];
        
        $stmt = $this->db->prepare("
            SELECT 
                COUNT(*) as total_orders,
                SUM(CASE WHEN status = 'received' THEN 1 ELSE 0 END) as delivered
            FROM purchase_orders 
            WHERE supplier_id = ? AND tenant_id = ?
        ");
        $stmt->execute([$vendorId, $this->companyId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result && $result['total_orders'] > 0) {
            $score['fulfillment_rate'] = round(
                ($result['delivered'] / $result['total_orders']) * 100, 1
            );
        }
        
        $stmt = $this->db->prepare("
            SELECT 
                AVG(DATEDIFF(expected_date, created_at)) as avg_actual
            FROM purchase_orders 
            WHERE supplier_id = ? AND tenant_id = ? AND status = 'received'
        ");
        $stmt->execute([$vendorId, $this->companyId]);
        $ltResult = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($ltResult && $ltResult['avg_actual']) {
            $score['lead_time_score'] = round(max(0, 100 - abs((float) $ltResult['avg_actual'] - 7) * 10), 1);
        }
        
        $score['quality_score'] = 0;
        $score['overall_score'] = round(
            ($score['fulfillment_rate'] * 0.6) +
            ($score['lead_time_score'] * 0.4),
            1
        );
        
        return $score;
    }
    
    public function rankVendors(int $companyId): array
    {
        $stmt = $this->db->prepare("
            SELECT id, name FROM suppliers
            WHERE tenant_id = ? AND deleted_at IS NULL
            ORDER BY name
        ");
        
        $stmt->execute([$companyId]);
        $vendors = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $ranked = [];
        
        foreach ($vendors as $vendor) {
            $ranked[$vendor['id']] = [
                'name' => $vendor['name'],
                'score' => $this->getVendorScore($vendor['id'])
            ];
        }
        
        // Sort by overall score descending
        uasort($ranked, fn($a, $b) => 
            $b['score']['overall_score'] <=> $a['score']['overall_score']
        );
        
        return $ranked;
    }
    
    // ============================================
    // DYNAMIC REORDER OPTIMIZATION
    // ============================================
    
    public function suggestOptimalReorderPoint(int $productId): array
    {
        // Analyze sales velocity to recommend optimal reorder point
        
        $stmt = $this->db->prepare("
            SELECT 
                AVG(daily_sales) as avg_daily,
                MAX(daily_sales) as max_daily,
                STDDEV(daily_sales) as std_daily
            FROM (
                SELECT DATE(created_at) as date, SUM(quantity) as daily_sales
                FROM sale_items si
                JOIN sales s ON si.sale_id = s.id
                WHERE si.product_id = ? AND s.status = 'completed'
                AND s.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                GROUP BY DATE(created_at)
            ) daily
        ");
        
        $stmt->execute([$productId]);
        $stats = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$stats || !$stats['avg_daily']) {
            return ['suggestion' => 'insufficient_data'];
        }
        
        $leadTime = 7; // Default lead time - could be vendor-specific
        $safetyStock = ($stats['std_daily'] ?? 0) * 2; // 2 standard deviations
        
        $suggestedReorderPoint = ceil(
            ($stats['avg_daily'] * $leadTime) + $safetyStock
        );
        
        $suggestedMaxStock = ceil(
            ($stats['avg_daily'] * ($leadTime * 2)) + ($safetyStock * 2)
        );
        
        return [
            'product_id' => $productId,
            'avg_daily_sales' => round($stats['avg_daily'], 2),
            'max_daily_sales' => (int) $stats['max_daily'],
            'suggested_reorder_point' => $suggestedReorderPoint,
            'suggested_max_stock' => $suggestedMaxStock,
            'safety_stock' => ceil($safetyStock),
            'calculated_at' => date('Y-m-d H:i:s')
        ];
    }
}

// ============================================
// RECONCILIATION SERVICE
// ============================================

class ReconciliationService
{
    private PDO $db;
    
    public function __construct(PDO $db)
    {
        $this->db = $db;
    }
    
    /**
     * Reconcile payments between gateway and bank statements
     */
    public function reconcilePayments(int $companyId, string $date): array
    {
        $result = [
            'tenant_id' => $companyId,
            'date' => $date,
            'matched' => [],
            'unmatched_gateway' => [],
            'unmatched_bank' => [],
            'variance' => 0,
            'generated_at' => date('Y-m-d H:i:s')
        ];
        
        // Get gateway transactions
        $gatewayTxns = $this->getGatewayTransactions($companyId, $date);
        
        // Get bank statement transactions (simulated - real implementation would parse bank CSV)
        $bankTxns = $this->getBankTransactions($companyId, $date);
        
        // Match by amount (and optionally by phone/reference)
        foreach ($gatewayTxns as $gtxn) {
            $matched = false;
            
            foreach ($bankTxns as $btxnKey => $btxn) {
                if ($btxn['amount'] == $gtxn['amount']) {
                    // Match found
                    $result['matched'][] = [
                        'gateway' => $gtxn,
                        'bank' => $btxn,
                        'match_type' => 'exact_amount'
                    ];
                    unset($bankTxns[$btxnKey]);
                    $matched = true;
                    break;
                }
            }
            
            if (!$matched) {
                $result['unmatched_gateway'][] = $gtxn;
            }
        }
        
        // Remaining bank transactions are unmatched
        $result['unmatched_bank'] = array_values($bankTxns);
        
        // Calculate variance
        $matchedAmount = array_sum(array_column($result['matched'], fn($m) => $m['gateway']['amount']));
        $gatewayAmount = array_sum(array_column($result['unmatched_gateway'], 'amount'));
        $bankAmount = array_sum(array_column($result['unmatched_bank'], fn($b) => $b['amount']));
        
        $result['variance'] = round($matchedAmount + $bankAmount - $gatewayAmount, 2);
        
        return $result;
    }
    
    private function getGatewayTransactions(int $companyId, string $date): array
    {
        $stmt = $this->db->prepare("
            SELECT id, amount, phone, status, payment_reference, created_at
            FROM payments
            WHERE tenant_id = ? AND DATE(created_at) = ? AND status = 'completed'
            ORDER BY created_at
        ");
        
        $stmt->execute([$companyId, $date]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    private function getBankTransactions(int $companyId, string $date): array
    {
        // This would be replaced with actual bank statement parsing
        // For now, return empty
        return [];
    }
}

// ============================================
// HELPER FUNCTIONS
// ============================================

function generate_vendor_pos(int $companyId, int $branchId = null): array
{
    static $service = null;
    
    if ($service === null) {
        $service = new VendorPOService(get_db_connection());
    }
    
    return $service->generateAutoPOs($companyId, $branchId);
}

function reconcile_payments(int $companyId, string $date): array
{
    static $service = null;
    
    if ($service === null) {
        $service = new ReconciliationService(get_db_connection());
    }
    
    return $service->reconcilePayments($companyId, $date);
}