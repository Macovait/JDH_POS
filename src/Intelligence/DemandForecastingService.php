<?php
/**
 * AI Demand Forecasting Service
 * 
 * Predictive demand forecasting using time series analysis and ML enhancement.
 * Enables automated stock replenishment based on predicted demand patterns.
 * 
 * @package JDH_POS\Intelligence
 * @version 1.0.0
 */

namespace JDH_POS\Intelligence;

use PDO;
use Exception;
use Math_Stats;

class DemandForecastingService
{
    private PDO $db;
    private array $config;
    
    const DEFAULT_FORECAST_DAYS = 30;
    const SEASONALITY_WINDOW = 7;
    const MIN_HISTORY_DAYS = 14;
    
    public function __construct(PDO $db)
    {
        $this->db = $db;
        $this->loadConfig();
    }
    
    private function loadConfig(): void
    {
        $stmt = $this->db->prepare("
            SELECT config_key, config_value 
            FROM app_config 
            WHERE config_key LIKE 'forecast.%'
        ");
        $stmt->execute();
        
        $this->config = [
            'default_days' => 30,
            'min_confidence' => 0.6,
            'auto_reorder_threshold' => 0.7,
            'seasonality_enabled' => true
        ];
        
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $key = str_replace('forecast.', '', $row['config_key']);
            $this->config[$key] = $row['config_value'];
        }
    }
    
    // ============================================
    // MAIN FORECASTING METHOD
    // ============================================
    
    public function forecastDemand(
        int $productId,
        int $branchId = null,
        int $days = 30
    ): array {
        $result = [
            'product_id' => $productId,
            'branch_id' => $branchId,
            'forecast_days' => $days,
            'forecasts' => [],
            'confidence' => 0,
            'recommendation' => null,
            'generated_at' => date('Y-m-d H:i:s')
        ];
        
        // Get historical data
        $history = $this->getSalesHistory($productId, $branchId, 90);
        
        if (count($history) < self::MIN_HISTORY_DAYS) {
            $result['error'] = 'Insufficient historical data (minimum 14 days required)';
            return $result;
        }
        
        // Calculate forecasts using multiple methods
        $forecasts = [];
        
        // 1. Simple Moving Average baseline
        $forecasts['sma'] = $this->calculateSMA($history, $days);
        
        // 2. Exponential Smoothing
        $forecasts['exponential'] = $this->calculateExponentialSmoothing($history, $days);
        
        // 3. Trend-adjusted forecast
        $forecasts['trend'] = $this->calculateTrendAdjustment($history, $days);
        
        // 4. Seasonality-adjusted (if enabled)
        if ($this->config['seasonality_enabled']) {
            $forecasts['seasonal'] = $this->calculateSeasonal($history, $days);
        }
        
        // Ensemble: Weighted average of methods
        $result['forecasts'] = $this->ensembleForecast($forecasts);
        
        // Calculate confidence based on data quality and variance
        $result['confidence'] = $this->calculateConfidence($history);
        
        // Generate replenishment recommendation
        $result['recommendation'] = $this->generateRecommendation(
            $productId,
            $branchId,
            $result['forecasts'],
            $result['confidence']
        );
        
        return $result;
    }
    
    // ============================================
    // SALES HISTORY RETRIEVAL
    // ============================================
    
    private function getSalesHistory(
        int $productId,
        ?int $branchId,
        int $days
    ): array {
        $params = [$productId, date('Y-m-d', strtotime("-{$days} days"))];
        
        $sql = "
            SELECT 
                DATE(s.created_at) as sale_date,
                COALESCE(SUM(si.quantity), 0) as units_sold,
                COALESCE(SUM(si.quantity * si.price), 0) as revenue
            FROM sales s
            JOIN sale_items si ON s.id = si.sale_id
            WHERE si.product_id = ?
            AND s.status = 'completed'
            AND s.created_at >= ?
        ";
        
        if ($branchId !== null) {
            $sql .= " AND s.branch_id = ?";
            $params[] = $branchId;
        }
        
        $sql .= " GROUP BY DATE(s.created_at) ORDER BY sale_date ASC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // ============================================
    // FORECASTING ALGORITHMS
    // ============================================
    
    private function calculateSMA(array $history, int $days): array
    {
        // Simple Moving Average - last 7 days average
        $recentSales = array_slice($history, -7);
        $avgUnits = array_sum(array_column($recentSales, 'units_sold')) / count($recentSales);
        
        return array_fill(0, $days, [
            'date' => null,
            'predicted' => round($avgUnits),
            'method' => 'sma'
        ]);
    }
    
    private function calculateExponentialSmoothing(array $history, int $days): array
    {
        $alpha = 0.3; // Smoothing factor
        $forecast = [];
        
        // Calculate initial level from first 7 days
        $level = array_sum(array_slice(array_column($history, 'units_sold'), 0, 7)) / 7;
        
        // Apply exponential smoothing
        $values = array_column($history, 'units_sold');
        for ($i = 1; $i < count($values); $i++) {
            $level = $alpha * $values[$i] + (1 - $alpha) * $level;
        }
        
        // Generate forecasts
        for ($d = 0; $d < $days; $d++) {
            $forecast[] = [
                'date' => date('Y-m-d', strtotime("+{$d} days")),
                'predicted' => round($level),
                'method' => 'exponential'
            ];
        }
        
        return $forecast;
    }
    
    private function calculateTrendAdjustment(array $history, int $days): array
    {
        $values = array_column($history, 'units_sold');
        
        // Calculate linear regression for trend
        $n = count($values);
        $meanX = ($n - 1) / 2;
        $meanY = array_sum($values) / $n;
        
        $numerator = 0;
        $denominator = 0;
        
        for ($i = 0; $i < $n; $i++) {
            $numerator += ($i - $meanX) * ($values[$i] - $meanY);
            $denominator += pow($i - $meanX, 2);
        }
        
        $slope = $denominator != 0 ? $numerator / $denominator : 0;
        
        // Generate forecasts with trend
        $lastValue = end($values);
        $forecast = [];
        
        for ($d = 0; $d < $days; $d++) {
            $predicted = $lastValue + ($slope * ($d + 1));
            $predicted = max(0, round($predicted)); // Can't be negative
            
            $forecast[] = [
                'date' => date('Y-m-d', strtotime("+{$d} days")),
                'predicted' => $predicted,
                'method' => 'trend'
            ];
        }
        
        return $forecast;
    }
    
    private function calculateSeasonal(array $history, int $days): array
    {
        // Calculate 7-day seasonality indices
        $seasonalIndices = array_fill(0, 7, 1.0);
        $weeklySales = [];
        
        // Group by day of week
        foreach ($history as $day) {
            $dow = (int) date('w', strtotime($day['sale_date']));
            $weeklySales[$dow][] = $day['units_sold'];
        }
        
        // Calculate average for each day of week
        for ($dow = 0; $dow < 7; $dow++) {
            if (!empty($weeklySales[$dow])) {
                $globalAvg = array_sum(array_column($history, 'units_sold')) / count($history);
                $seasonalIndices[$dow] = array_sum($weeklySales[$dow]) / count($weeklySales[$dow]) / ($globalAvg ?: 1);
            }
        }
        
        // Apply seasonal adjustment
        $baseForecast = $this->calculateExponentialSmoothing($history, 1);
        $baseValue = $baseForecast[0]['predicted'];
        
        $forecast = [];
        for ($d = 0; $d < $days; $d++) {
            $dow = (int) date('w', strtotime("+{$d} days"));
            $predicted = round($baseValue * $seasonalIndices[$dow]);
            
            $forecast[] = [
                'date' => date('Y-m-d', strtotime("+{$d} days")),
                'predicted' => max(0, $predicted),
                'method' => 'seasonal',
                'seasonal_index' => $seasonalIndices[$dow]
            ];
        }
        
        return $forecast;
    }
    
    private function ensembleForecast(array $methods): array
    {
        // Weight different methods based on typical accuracy
        $weights = [
            'seasonal' => 0.35,
            'exponential' => 0.30,
            'trend' => 0.20,
            'sma' => 0.15
        ];
        
        $ensemble = [];
        
        foreach ($methods['sma'] as $i => $smaPoint) {
            $weightedSum = 0;
            $totalWeight = 0;
            
            foreach ($methods as $method => $forecast) {
                if (isset($forecast[$i]['predicted'])) {
                    $weight = $weights[$method] ?? 0.15;
                    $weightedSum += $forecast[$i]['predicted'] * $weight;
                    $totalWeight += $weight;
                }
            }
            
            $ensemble[] = [
                'date' => $smaPoint['date'] ?? date('Y-m-d', strtotime("+{$i} days")),
                'predicted' => round($weightedSum / ($totalWeight ?: 1)),
                'method' => 'ensemble'
            ];
        }
        
        return $ensemble;
    }
    
    // ============================================
    // CONFIDENCE & RECOMMENDATIONS
    // ============================================
    
    private function calculateConfidence(array $history): float
    {
        if (count($history) < 14) {
            return 0.3;
        }
        
        $values = array_column($history, 'units_sold');
        $mean = array_sum($values) / count($values);
        
        if ($mean == 0) {
            return 0.2;
        }
        
        // Calculate coefficient of variation (lower = more confident)
        $diffs = array_map(function($v) use ($mean) { return pow($v - $mean, 2); }, $values);
        $variance = array_sum($diffs) / count($values);
        $cv = sqrt($variance) / $mean;
        
        // Convert CV to confidence (0-1 scale)
        // Lower CV = Higher confidence
        $confidence = max(0.2, min(0.95, 1 - ($cv * 0.5)));
        
        // Boost confidence if more historical data
        if (count($history) > 60) {
            $confidence = min(0.95, $confidence + 0.1);
        }
        
        return round($confidence, 2);
    }
    
    private function generateRecommendation(
        int $productId,
        ?int $branchId,
        array $forecasts,
        float $confidence
    ): array {
        // Calculate average predicted daily sales
        $totalPredicted = array_sum(array_column($forecasts, 'predicted'));
        $avgDaily = $totalPredicted / count($forecasts);
        
        // Get current stock
        $stock = $this->getCurrentStock($productId, $branchId);
        
        // Days of stock remaining
        $daysRemaining = $avgDaily > 0 ? $stock / $avgDaily : PHP_INT_MAX;
        
        // Reorder point (trigger reorder when stock drops below this)
        $reorderPoint = ceil($avgDaily * 7); // 7 days worth
        
        $recommendation = [
            'current_stock' => $stock,
            'avg_daily_sales' => round($avgDaily, 1),
            'days_remaining' => round($daysRemaining, 1),
            'reorder_point' => $reorderPoint,
            'confidence' => $confidence,
            'action' => 'monitor',
            'suggested_quantity' => 0,
            'urgency' => 'low'
        ];
        
        // Determine action based on days remaining and confidence
        if ($daysRemaining < 7 && $confidence >= 0.6) {
            $recommendation['action'] = 'reorder';
            $recommendation['urgency'] = 'high';
            $recommendation['suggested_quantity'] = ceil($avgDaily * 30); // 30 days supply
        } elseif ($daysRemaining < 14 && $confidence >= 0.5) {
            $recommendation['action'] = 'review';
            $recommendation['urgency'] = 'medium';
            $recommendation['suggested_quantity'] = ceil($avgDaily * 21);
        }
        
        return $recommendation;
    }
    
    private function getCurrentStock(int $productId, ?int $branchId): int
    {
        if ($branchId !== null) {
            $sql = "
                SELECT COALESCE(SUM(i.stock), 0)
                FROM inventory i
                JOIN products p ON p.id = i.product_id AND p.tenant_id = i.tenant_id
                WHERE p.id = ? AND i.branch_id = ?
            ";
            $params = [$productId, $branchId];
        } else {
            $sql = "
                SELECT COALESCE(SUM(i.stock), 0)
                FROM inventory i
                JOIN products p ON p.id = i.product_id AND p.tenant_id = i.tenant_id
                WHERE p.id = ?
            ";
            $params = [$productId];
        }
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        
        return (int) $stmt->fetchColumn();
    }
    
    // ============================================
    // BATCH FORECASTING
    // ============================================
    
    public function forecastAllProducts(int $tenantId, int $days = 30): array
    {
        $stmt = $this->db->prepare("
            SELECT id, name FROM products 
            WHERE tenant_id = ? AND deleted_at IS NULL AND active = 1
            ORDER BY name
        ");
        
        $stmt->execute([$tenantId]);
        $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $results = [];
        
        foreach ($products as $product) {
            $results[$product['id']] = [
                'name' => $product['name'],
                'forecast' => $this->forecastDemand($product['id'], null, $days)
            ];
        }
        
        // Filter to products needing reorder
        $needsReorder = array_filter($results, fn($r) => 
            $r['forecast']['recommendation']['action'] ?? '' === 'reorder'
        );
        
        return [
            'all_products' => $results,
            'needs_reorder' => $needsReorder,
            'generated_at' => date('Y-m-d H:i:s')
        ];
    }
}

// ============================================
// LABORSCHEDULING SERVICE
// ============================================

class LaborSchedulingService
{
    private PDO $db;
    
    public function __construct(PDO $db)
    {
        $this->db = $db;
    }
    
    public function optimizeSchedule(
        int $branchId,
        array $salesForecast,
        int $targetHours
    ): array {
        // Calculate required labor hours based on forecast
        $totalProjectedSales = array_sum(array_column($salesForecast, 'predicted'));
        
        // Labor hours based on sales volume (industry standard: 1 hour per X transactions)
        $laborHoursPerSale = 0.1; // 6 minutes per transaction
        $requiredHours = $totalProjectedSales * $laborHoursPerSale;
        
        // Generate optimized schedule
        $schedule = [];
        
        // Distribute hours across week
        // Note: This is a simplified algorithm - real implementation would consider:
        // - Staff availability preferences
        // - Labor law constraints
        // - Overtime costs
        // - Skill mix requirements
        
        return [
            'branch_id' => $branchId,
            'target_hours' => $targetHours,
            'projected_hours' => round($requiredHours, 1),
            'variance' => round($requiredHours - $targetHours, 1),
            'daily_allocation' => $this->distributeHours($requiredHours, 7),
            'generated_at' => date('Y-m-d H:i:s')
        ];
    }
    
    private function distributeHours(float $totalHours, int $days): array
    {
        // Simple even distribution - real version would use sales patterns
        $dailyHours = $totalHours / $days;
        
        $distribution = [];
        for ($d = 0; $d < $days; $d++) {
            $distribution[] = [
                'date' => date('Y-m-d', strtotime("+{$d} days")),
                'hours' => round($dailyHours, 1)
            ];
        }
        
        return $distribution;
    }
}

// ============================================
// HELPER FUNCTIONS
// ============================================

function forecast_demand(int $productId, int $branchId = null, int $days = 30): array
{
    static $service = null;
    
    if ($service === null) {
        $service = new DemandForecastingService(get_db_connection());
    }
    
    return $service->forecastDemand($productId, $branchId, $days);
}

function forecast_all_products(int $tenantId, int $days = 30): array
{
    static $service = null;
    
    if ($service === null) {
        $service = new DemandForecastingService(get_db_connection());
    }
    
    return $service->forecastAllProducts($tenantId, $days);
}