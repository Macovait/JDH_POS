<?php
/**
 * Analytics Service
 * Calculates SaaS metrics: MRR, ARR, Churn, LTV, ARPU, NRR, CAC
 */

namespace Jakababa\Services;

use PDO;

class AnalyticsService
{
    private PDO $pdo;
    
    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }
    
    /**
     * Get all SaaS metrics
     */
    public function getSaaSMetrics(string $startDate, string $endDate): array
    {
        return [
            'mrr' => $this->calculateMRR(),
            'arr' => $this->calculateARR(),
            'arpu' => $this->calculateARPU(),
            'ltv' => $this->calculateLTV(),
            'nrr' => $this->calculateNRR($startDate, $endDate),
            'churn_rate' => $this->calculateChurnRate($startDate, $endDate),
            'total_customers' => $this->getTotalCustomers(),
            'active_customers' => $this->getActiveCustomers(),
            'new_customers' => $this->getNewCustomers($startDate, $endDate),
            'churned_customers' => $this->getChurnedCustomers($startDate, $endDate),
            'mrr_growth' => $this->calculateMRRGrowth($startDate, $endDate),
        ];
    }
    
    /**
     * Calculate MRR (Monthly Recurring Revenue)
     */
    public function calculateMRR(): float
    {
        $stmt = $this->pdo->query("
            SELECT 
                SUM(CASE 
                    WHEN billing_cycle = 'monthly' THEN amount 
                    WHEN billing_cycle = 'yearly' THEN amount / 12 
                    ELSE amount 
                END) as mrr
            FROM subscriptions 
            WHERE status IN ('active', 'trialing', 'past_due')
        ");
        
        return (float)($stmt->fetchColumn() ?? 0);
    }
    
    /**
     * Calculate ARR (Annual Recurring Revenue)
     */
    public function calculateARR(): float
    {
        return $this->calculateMRR() * 12;
    }
    
    /**
     * Calculate ARPU (Average Revenue Per User)
     */
    public function calculateARPU(): float
    {
        $mrr = $this->calculateMRR();
        $activeCustomers = $this->getActiveCustomers();
        
        return $activeCustomers > 0 ? $mrr / $activeCustomers : 0;
    }
    
    /**
     * Calculate LTV (Customer Lifetime Value)
     * Formula: ARPU / Churn Rate (monthly)
     */
    public function calculateLTV(): float
    {
        $arpu = $this->calculateARPU();
        $monthlyChurnRate = $this->calculateMonthlyChurnRate();
        
        if ($monthlyChurnRate <= 0) {
            // If no churn, estimate based on average customer age
            $avgCustomerAge = $this->getAverageCustomerAgeMonths();
            return $arpu * max($avgCustomerAge, 24); // Minimum 24 months assumption
        }
        
        return $arpu / $monthlyChurnRate;
    }
    
    /**
     * Calculate NRR (Net Revenue Retention)
     * Formula: (Starting MRR + Expansion - Contraction - Churn) / Starting MRR
     */
    public function calculateNRR(string $startDate, string $endDate): float
    {
        // Get MRR at start of period
        $startMRR = $this->getMRRAtDate($startDate);
        
        if ($startMRR <= 0) {
            return 100.0;
        }
        
        // Get expansion (upgrades)
        $expansion = $this->getExpansionRevenue($startDate, $endDate);
        
        // Get contraction (downgrades)
        $contraction = $this->getContractionRevenue($startDate, $endDate);
        
        // Get churned MRR
        $churnedMRR = $this->getChurnedMRR($startDate, $endDate);
        
        $nrr = (($startMRR + $expansion - $contraction - $churnedMRR) / $startMRR) * 100;
        
        return max(0, $nrr);
    }
    
    /**
     * Calculate churn rate for period
     */
    public function calculateChurnRate(string $startDate, string $endDate): float
    {
        $startCustomers = $this->getCustomerCountAtDate($startDate);
        $churned = $this->getChurnedCustomers($startDate, $endDate);
        
        if ($startCustomers <= 0) {
            return 0;
        }
        
        return ($churned / $startCustomers) * 100;
    }
    
    /**
     * Calculate monthly churn rate
     */
    public function calculateMonthlyChurnRate(): float
    {
        // Get last 3 months average
        $stmt = $this->pdo->query("
            SELECT 
                DATE_FORMAT(cancelled_at, '%Y-%m') as month,
                COUNT(*) as churned
            FROM subscriptions
            WHERE status = 'cancelled'
            AND cancelled_at >= DATE_SUB(NOW(), INTERVAL 3 MONTH)
            GROUP BY DATE_FORMAT(cancelled_at, '%Y-%m')
        ");
        
        $churnData = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($churnData)) {
            return 0.02; // Default 2% monthly churn
        }
        
        $totalChurned = array_sum(array_column($churnData, 'churned'));
        $avgCustomers = $this->getAverageCustomerCount(3);
        
        return $avgCustomers > 0 ? $totalChurned / $avgCustomers : 0.02;
    }
    
    /**
     * Get revenue metrics for charts
     */
    public function getRevenueMetrics(string $startDate, string $endDate): array
    {
        $labels = [];
        $mrrData = [];
        $newRevenue = [];
        
        $current = new \DateTime($startDate);
        $end = new \DateTime($endDate);
        
        while ($current <= $end) {
            $dateStr = $current->format('Y-m-d');
            $labels[] = $current->format('M j');
            
            $mrrData[] = $this->getMRRAtDate($dateStr);
            $newRevenue[] = $this->getNewRevenueForDate($dateStr);
            
            $current->modify('+1 day');
        }
        
        return [
            'labels' => $labels,
            'mrr_data' => $mrrData,
            'new_revenue' => $newRevenue,
        ];
    }
    
    /**
     * Get growth metrics
     */
    public function getGrowthMetrics(string $startDate, string $endDate): array
    {
        $labels = [];
        $newCustomers = [];
        $churned = [];
        
        $current = new \DateTime($startDate);
        $end = new \DateTime($endDate);
        
        while ($current <= $end) {
            $dateStr = $current->format('Y-m-d');
            $labels[] = $current->format('M j');
            
            $newCustomers[] = $this->getNewCustomersForDate($dateStr);
            $churned[] = $this->getChurnedForDate($dateStr);
            
            $current->modify('+1 day');
        }
        
        return [
            'labels' => $labels,
            'new_customers' => $newCustomers,
            'churned' => $churned,
        ];
    }
    
    /**
     * Get churn metrics
     */
    public function getChurnMetrics(string $startDate, string $endDate): array
    {
        $labels = [];
        $rates = [];
        
        // Weekly churn rate
        $current = new \DateTime($startDate);
        $end = new \DateTime($endDate);
        
        while ($current <= $end) {
            $weekEnd = clone $current;
            $weekEnd->modify('+6 days');
            
            if ($weekEnd > $end) {
                $weekEnd = $end;
            }
            
            $labels[] = $current->format('M j');
            $rates[] = $this->calculateChurnRate(
                $current->format('Y-m-d'),
                $weekEnd->format('Y-m-d')
            );
            
            $current->modify('+7 days');
        }
        
        // Recent churn details
        $stmt = $this->pdo->prepare("
            SELECT 
                c.id,
                c.name,
                s.plan_id,
                s.cancelled_at,
                TIMESTAMPDIFF(MONTH, s.created_at, s.cancelled_at) as tenure_months
            FROM companies c
            JOIN subscriptions s ON c.id = s.tenant_id
            WHERE s.status = 'cancelled'
            AND s.cancelled_at BETWEEN ? AND ?
            ORDER BY s.cancelled_at DESC
            LIMIT 10
        ");
        $stmt->execute([$startDate, $endDate]);
        $recent = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return [
            'labels' => $labels,
            'rates' => $rates,
            'recent' => $recent,
        ];
    }
    
    /**
     * Get customer metrics
     */
    public function getCustomerMetrics(string $startDate, string $endDate): array
    {
        // Top customers by MRR
        $stmt = $this->pdo->query("
            SELECT 
                c.id,
                c.name,
                c.email,
                s.plan_id,
                s.status,
                CASE 
                    WHEN s.billing_cycle = 'yearly' THEN s.amount / 12
                    ELSE s.amount
                END as monthly_amount
            FROM companies c
            JOIN subscriptions s ON c.id = s.tenant_id
            WHERE s.status IN ('active', 'trialing', 'past_due')
            ORDER BY monthly_amount DESC
            LIMIT 10
        ");
        $topByMRR = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Customers by plan
        $stmt = $this->pdo->query("
            SELECT 
                plan_id,
                COUNT(*) as count,
                SUM(CASE 
                    WHEN billing_cycle = 'yearly' THEN amount / 12
                    ELSE amount
                END) as mrr
            FROM subscriptions
            WHERE status IN ('active', 'trialing', 'past_due')
            GROUP BY plan_id
            ORDER BY mrr DESC
        ");
        $byPlan = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Cohort analysis (simplified)
        $cohorts = $this->getCohortAnalysis();
        
        return [
            'top_by_mrr' => $topByMRR,
            'by_plan' => $byPlan,
            'cohorts' => $cohorts,
        ];
    }
    
    /**
     * Calculate MRR growth percentage
     */
    public function calculateMRRGrowth(string $startDate, string $endDate): float
    {
        $startMRR = $this->getMRRAtDate($startDate);
        $endMRR = $this->getMRRAtDate($endDate);
        
        if ($startMRR <= 0) {
            return $endMRR > 0 ? 100 : 0;
        }
        
        return (($endMRR - $startMRR) / $startMRR) * 100;
    }
    
    // Helper methods
    
    private function getTotalCustomers(): int
    {
        $stmt = $this->pdo->query("SELECT COUNT(*) FROM subscriptions");
        return (int)$stmt->fetchColumn();
    }
    
    private function getActiveCustomers(): int
    {
        $stmt = $this->pdo->query("
            SELECT COUNT(*) FROM subscriptions 
            WHERE status IN ('active', 'trialing', 'past_due')
        ");
        return (int)$stmt->fetchColumn();
    }
    
    private function getNewCustomers(string $startDate, string $endDate): int
    {
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) FROM subscriptions 
            WHERE created_at BETWEEN ? AND ?
        ");
        $stmt->execute([$startDate, $endDate]);
        return (int)$stmt->fetchColumn();
    }
    
    private function getChurnedCustomers(string $startDate, string $endDate): int
    {
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) FROM subscriptions 
            WHERE status = 'cancelled'
            AND cancelled_at BETWEEN ? AND ?
        ");
        $stmt->execute([$startDate, $endDate]);
        return (int)$stmt->fetchColumn();
    }
    
    private function getCustomerCountAtDate(string $date): int
    {
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) FROM subscriptions 
            WHERE created_at <= ?
            AND (cancelled_at IS NULL OR cancelled_at > ?)
        ");
        $stmt->execute([$date, $date]);
        return (int)$stmt->fetchColumn();
    }
    
    private function getAverageCustomerCount(int $months): float
    {
        $stmt = $this->pdo->prepare("
            SELECT AVG(monthly_count) FROM (
                SELECT 
                    DATE_FORMAT(created_at, '%Y-%m') as month,
                    COUNT(*) as monthly_count
                FROM subscriptions
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? MONTH)
                GROUP BY DATE_FORMAT(created_at, '%Y-%m')
            ) as monthly_stats
        ");
        $stmt->execute([$months]);
        return (float)($stmt->fetchColumn() ?? 0);
    }
    
    private function getAverageCustomerAgeMonths(): float
    {
        $stmt = $this->pdo->query("
            SELECT AVG(TIMESTAMPDIFF(MONTH, created_at, COALESCE(cancelled_at, NOW())))
            FROM subscriptions
            WHERE created_at IS NOT NULL
        ");
        return (float)($stmt->fetchColumn() ?? 12);
    }
    
    private function getMRRAtDate(string $date): float
    {
        $stmt = $this->pdo->prepare("
            SELECT 
                SUM(CASE 
                    WHEN billing_cycle = 'monthly' THEN amount 
                    WHEN billing_cycle = 'yearly' THEN amount / 12 
                    ELSE amount 
                END)
            FROM subscriptions 
            WHERE created_at <= ?
            AND (cancelled_at IS NULL OR cancelled_at > ?)
            AND status IN ('active', 'trialing', 'past_due', 'active')
        ");
        $stmt->execute([$date, $date]);
        return (float)($stmt->fetchColumn() ?? 0);
    }
    
    private function getNewRevenueForDate(string $date): float
    {
        $stmt = $this->pdo->prepare("
            SELECT 
                SUM(CASE 
                    WHEN billing_cycle = 'monthly' THEN amount 
                    WHEN billing_cycle = 'yearly' THEN amount / 12 
                    ELSE amount 
                END)
            FROM subscriptions 
            WHERE DATE(created_at) = ?
        ");
        $stmt->execute([$date]);
        return (float)($stmt->fetchColumn() ?? 0);
    }
    
    private function getNewCustomersForDate(string $date): int
    {
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) FROM subscriptions WHERE DATE(created_at) = ?
        ");
        $stmt->execute([$date]);
        return (int)$stmt->fetchColumn();
    }
    
    private function getChurnedForDate(string $date): int
    {
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) FROM subscriptions 
            WHERE status = 'cancelled' AND DATE(cancelled_at) = ?
        ");
        $stmt->execute([$date]);
        return (int)$stmt->fetchColumn();
    }
    
    private function getExpansionRevenue(string $startDate, string $endDate): float
    {
        // Track upgrades - simplified
        return 0; // Would require tracking subscription changes
    }
    
    private function getContractionRevenue(string $startDate, string $endDate): float
    {
        // Track downgrades - simplified
        return 0; // Would require tracking subscription changes
    }
    
    private function getChurnedMRR(string $startDate, string $endDate): float
    {
        $stmt = $this->pdo->prepare("
            SELECT 
                SUM(CASE 
                    WHEN billing_cycle = 'monthly' THEN amount 
                    WHEN billing_cycle = 'yearly' THEN amount / 12 
                    ELSE amount 
                END)
            FROM subscriptions 
            WHERE status = 'cancelled'
            AND cancelled_at BETWEEN ? AND ?
        ");
        $stmt->execute([$startDate, $endDate]);
        return (float)($stmt->fetchColumn() ?? 0);
    }
    
    private function getCohortAnalysis(): array
    {
        // Simplified cohort analysis
        $cohorts = [];
        
        $stmt = $this->pdo->query("
            SELECT 
                DATE_FORMAT(created_at, '%Y-%m') as cohort_month,
                COUNT(*) as total_customers
            FROM subscriptions
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
            GROUP BY DATE_FORMAT(created_at, '%Y-%m')
            ORDER BY cohort_month DESC
        ");
        
        $months = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($months as $month) {
            $retention = [];
            
            // Calculate retention for each month after signup
            for ($i = 0; $i <= 12; $i++) {
                $retention[] = $this->calculateCohortRetention(
                    $month['cohort_month'],
                    $i
                );
            }
            
            $cohorts[] = [
                'month' => date('M Y', strtotime($month['cohort_month'] . '-01')),
                'count' => $month['total_customers'],
                'retention' => $retention,
            ];
        }
        
        return $cohorts;
    }
    
    private function calculateCohortRetention(string $cohortMonth, int $monthsAfter): int
    {
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) 
            FROM subscriptions
            WHERE DATE_FORMAT(created_at, '%Y-%m') = ?
            AND (cancelled_at IS NULL OR TIMESTAMPDIFF(MONTH, created_at, cancelled_at) > ?)
        ");
        $stmt->execute([$cohortMonth, $monthsAfter]);
        $retained = (int)$stmt->fetchColumn();
        
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) 
            FROM subscriptions
            WHERE DATE_FORMAT(created_at, '%Y-%m') = ?
        ");
        $stmt->execute([$cohortMonth]);
        $total = (int)$stmt->fetchColumn();
        
        return $total > 0 ? round(($retained / $total) * 100) : 0;
    }
}
