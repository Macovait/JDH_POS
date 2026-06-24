<?php
/**
 * Customer Lifetime Value (CLV) Prediction Service
 * 
 * Predicts customer lifetime value using BG/NBD and Gamma-Gamma models.
 * Enables targeted marketing and retention strategies.
 * 
 * @package JDH_POS\Intelligence
 * @version 1.0.0
 */

namespace JDH_POS\Intelligence;

use PDO;
use Exception;

class CLVPredictionService
{
    private PDO $db;
    
    // Model parameters (can be tuned per business)
    const TRANSACTION_RATE_PRIOR = 0.14;  // Daily transaction rate prior
    const DROPOUT_PRIOR = 0.13;            // Dropout probability prior  
    const MONETARY_GAMMA_PRIOR = 2;        // Monetary gamma prior
    const MONETARY_SAMPLE_PRIOR = 2;     // Monetary sample prior
    
    public function __construct(PDO $db)
    {
        $this->db = $db;
    }
    
    // ============================================
    // MAIN CLV PREDICTION
    // ============================================
    
    public function predictCLV(int $customerId): array
    {
        $result = [
            'customer_id' => $customerId,
            'clv' => 0,
            'predicted_months' => 0,
            'confidence' => 0,
            'segment' => 'new',
            'churn_risk' => 0,
            'factors' => [],
            'generated_at' => date('Y-m-d H:i:s')
        ];
        
        // Get transaction history
        $transactions = $this->getCustomerTransactions($customerId);
        
        if (count($transactions) < 3) {
            $result['segment'] = 'new';
            $result['confidence'] = 0.3;
            return $result;
        }
        
        // Calculate CLV using simplified BG/NBD-like model
        $clv = $this->calculateCLV($transactions);
        $result['clv'] = round($clv, 2);
        
        // Predict active months remaining
        $result['predicted_months'] = $this->predictActiveMonths($transactions);
        
        // Calculate confidence
        $result['confidence'] = $this->calculateConfidence($transactions);
        
        // Determine segment
        $result['segment'] = $this->determineSegment($result['clv'], $result['predicted_months']);
        
        // Calculate churn risk
        $result['churn_risk'] = $this->calculateChurnRisk($transactions);
        
        // Identify key factors
        $result['factors'] = $this->analyzeCLVFactors($transactions);
        
        return $result;
    }
    
    private function getCustomerTransactions(int $customerId): array
    {
        $stmt = $this->db->prepare("
            SELECT 
                s.id,
                s.total,
                s.created_at,
                TIMESTAMPDAY(s.created_at, MIN(s2.created_at)) as days_since_first
            FROM sales s
            LEFT JOIN sales s2 ON s2.customer_id = s.customer_id
            WHERE s.customer_id = ? AND s.status = 'completed'
            GROUP BY s.id
            ORDER BY s.created_at ASC
        ");
        
        $stmt->execute([$customerId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    private function calculateCLV(array $transactions): float
    {
        if (empty($transactions)) return 0;
        
        // Calculate key metrics
        $count = count($transactions);
        $totalRevenue = array_sum(array_column($transactions, 'total'));
        
        // Get customer age (days since first purchase)
        $firstPurchase = strtotime($transactions[0]['created_at']);
        $lastPurchase = strtotime($transactions[$count - 1]['created_at']);
        $customerAgeDays = ($lastPurchase - $firstPurchase) / 86400 ?: 1;
        
        // Transaction frequency
        $frequency = $count / max(1, $customerAgeDays / 30); // Monthly frequency
        
        // Average order value
        $aov = $totalRevenue / $count;
        
        // Predicted monthly spend
        $monthlySpend = $frequency * $aov;
        
        // Expected customer lifespan (months)
        $expectedLifespan = $this->predictActiveMonths($transactions);
        
        // CLV = Monthly spend × Expected lifespan
        $clv = $monthlySpend * $expectedLifespan;
        
        return $clv;
    }
    
    private function predictActiveMonths(array $transactions): int
    {
        if (count($transactions) < 2) return 3;
        
        // Calculate purchase intervals
        $intervals = [];
        for ($i = 1; $i < count($transactions); $i++) {
            $prev = strtotime($transactions[$i - 1]['created_at']);
            $curr = strtotime($transactions[$i]['created_at']);
            $intervals[] = ($curr - $prev) / 86400;
        }
        
        $avgInterval = array_sum($intervals) / count($intervals);
        
        // Calculate recency (days since last purchase)
        $lastPurchase = strtotime($transactions[count($transactions) - 1]['created_at']);
        $recency = (time() - $lastPurchase) / 86400;
        
        // Predict remaining active months using simplified model
        // If customer is inactive for >2x average interval, they're likely churning
        $inactivityThreshold = $avgInterval * 2;
        
        if ($recency > $inactivityThreshold) {
            return 0; // Already churned
        }
        
        // Estimate remaining lifespan based on purchase pattern
        // Younger customers stay longer
        $firstPurchase = strtotime($transactions[0]['created_at']);
        $ageDays = (time() - $firstPurchase) / 86400;
        
        if ($ageDays < 30) return 6;    // New customers - assume 6 months
        if ($ageDays < 90) return 12;   // Recent customers - 12 months
        if ($ageDays < 180) return 24; // Established - 24 months
        return 36;                      // Loyal customers - 36 months
    }
    
    private function calculateConfidence(array $transactions): float
    {
        $count = count($transactions);
        
        if ($count < 3) return 0.3;
        if ($count < 5) return 0.5;
        if ($count < 10) return 0.7;
        
        // Calculate purchase consistency
        $amounts = array_column($transactions, 'total');
        $mean = array_sum($amounts) / $count;
        $diffSquares = array_map(function($a) use ($mean) { return pow($a - $mean, 2); }, $amounts);
        $variance = array_sum($diffSquares) / $count;
        $cv = $variance > 0 ? sqrt($variance) / $mean : 1;
        
        // Lower CV = more confident
        $consistencyBonus = max(0, min(0.2, 0.2 - ($cv * 0.1)));
        
        return min(0.95, 0.75 + $consistencyBonus + ($count > 20 ? 0.1 : 0));
    }
    
    private function determineSegment(float $clv, int $months): string
    {
        if ($months == 0) return 'churned';
        if ($clv < 1000) return 'low_value';
        if ($clv < 10000) return 'medium';
        if ($clv < 50000) return 'high';
        return 'vip';
    }
    
    private function calculateChurnRisk(array $transactions): float
    {
        if (count($transactions) < 3) return 0.5;
        
        // Calculate days since last purchase
        $lastPurchase = strtotime($transactions[count($transactions) - 1]['created_at']);
        $recency = (time() - $lastPurchase) / 86400;
        
        // Calculate average interval
        $intervals = [];
        for ($i = 1; $i < count($transactions); $i++) {
            $prev = strtotime($transactions[$i - 1]['created_at']);
            $curr = strtotime($transactions[$i]['created_at']);
            $intervals[] = ($curr - $prev) / 86400;
        }
        
        $avgInterval = array_sum($intervals) / count($intervals);
        
        // Churn risk based on recency vs average
        if ($recency > $avgInterval * 3) return 0.9;
        if ($recency > $avgInterval * 2) return 0.7;
        if ($recency > $avgInterval * 1.5) return 0.4;
        
        return max(0.1, min(0.3, $recency / ($avgInterval * 2)));
    }
    
    private function analyzeCLVFactors(array $transactions): array
    {
        $count = count($transactions);
        $total = array_sum(array_column($transactions, 'total'));
        
        return [
            'purchase_count' => $count,
            'total_revenue' => round($total, 2),
            'average_order_value' => round($total / $count, 2),
            'first_purchase' => $transactions[0]['created_at'] ?? null,
            'recent_purchase' => $transactions[$count - 1]['created_at'] ?? null
        ];
    }
    
    // ============================================
    // BATCH CLV FOR ALL CUSTOMERS
    // ============================================
    
    public function calculateAllCustomerCLV(int $tenantId): array
    {
        $stmt = $this->db->prepare("
            SELECT c.id, c.name, c.phone
            FROM customers c
            WHERE c.tenant_id = ? AND c.deleted_at IS NULL
            ORDER BY c.name
        ");
        
        $stmt->execute([$tenantId]);
        $customers = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $results = [];
        
        foreach ($customers as $customer) {
            $results[$customer['id']] = [
                'name' => $customer['name'],
                'clv_data' => $this->predictCLV($customer['id'])
            ];
        }
        
        // Filter by segment
        $bySegment = [];
        foreach ($results as $id => $data) {
            $segment = $data['clv_data']['segment'];
            if (!isset($bySegment[$segment])) {
                $bySegment[$segment] = [];
            }
            $bySegment[$segment][$id] = $data;
        }
        
        return [
            'all_customers' => $results,
            'by_segment' => $bySegment,
            'generated_at' => date('Y-m-d H:i:s')
        ];
    }
}

// ============================================
// UNIFIED LOYALTY SERVICE
// ============================================

class UnifiedLoyaltyService
{
    private PDO $db;
    
    public function __construct(PDO $db)
    {
        $this->db = $db;
    }
    
    /**
     * Get unified customer profile across all channels
     */
    public function getUnifiedProfile(int $identifier): ?array
    {
        // Try to find by phone, email, or loyalty card
        $stmt = $this->db->prepare("
            SELECT * FROM customers 
            WHERE (phone = ? OR email = ? OR loyalty_card = ?)
            AND deleted_at IS NULL
            LIMIT 1
        ");
        
        $stmt->execute([$identifier, $identifier, $identifier]);
        $customer = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$customer) return null;
        
        // Get loyalty balance
        $points = $this->getLoyaltyPoints($customer['id']);
        
        // Get purchase history
        $purchases = $this->getPurchaseHistory($customer['id']);
        
        // Get preferences (clienteling notes)
        $preferences = $this->getPreferences($customer['id']);
        
        return array_merge($customer, [
            'loyalty_points' => $points,
            'purchase_count' => count($purchases),
            'total_spent' => array_sum(array_column($purchases, 'total')),
            'preferences' => $preferences,
            'tier' => $this->calculateTier($points)
        ]);
    }
    
    public function getLoyaltyPoints(int $customerId): int
    {
        $stmt = $this->db->prepare("
            SELECT loyalty_points FROM customers WHERE id = ?
        ");
        $stmt->execute([$customerId]);
        return (int) $stmt->fetchColumn();
    }
    
    public function awardPoints(int $customerId, int $points, string $reason): bool
    {
        try {
            $this->db->beginTransaction();
            
            // Update points
            $stmt = $this->db->prepare("
                UPDATE customers SET loyalty_points = loyalty_points + ? WHERE id = ?
            ");
            $stmt->execute([$points, $customerId]);
            
            // Log transaction
            $stmt = $this->db->prepare("
                INSERT INTO loyalty_transactions (customer_id, points, type, reason)
                VALUES (?, ?, 'earned', ?)
            ");
            $stmt->execute([$customerId, $points, $reason]);
            
            $this->db->commit();
            return true;
            
        } catch (Exception $e) {
            $this->db->rollBack();
            return false;
        }
    }
    
    private function getPurchaseHistory(int $customerId): array
    {
        $stmt = $this->db->prepare("
            SELECT total, created_at FROM sales 
            WHERE customer_id = ? AND status = 'completed'
            ORDER BY created_at DESC LIMIT 50
        ");
        $stmt->execute([$customerId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    private function getPreferences(int $customerId): array
    {
        $stmt = $this->db->prepare("
            SELECT note_type, note, created_by, created_at 
            FROM customer_notes 
            WHERE customer_id = ?
            ORDER BY created_at DESC LIMIT 10
        ");
        $stmt->execute([$customerId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    private function calculateTier(int $points): string
    {
        if ($points >= 50000) return 'platinum';
        if ($points >= 20000) return 'gold';
        if ($points >= 5000) return 'silver';
        return 'bronze';
    }
}

// ============================================
// HELPER FUNCTIONS
// ============================================

function predict_clv(int $customerId): array
{
    static $service = null;
    
    if ($service === null) {
        $service = new CLVPredictionService(get_db_connection());
    }
    
    return $service->predictCLV($customerId);
}

function get_unified_customer_profile(string $identifier): ?array
{
    static $service = null;
    
    if ($service === null) {
        $service = new UnifiedLoyaltyService(get_db_connection());
    }
    
    return $service->getUnifiedProfile($identifier);
}