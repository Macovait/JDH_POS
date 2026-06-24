<?php
/**
 * Advanced Loyalty Management API
 * 
 * Provides comprehensive loyalty data including:
 * - Tier information and progress
 * - Points balance and history
 * - Referral tracking
 * - Personalized rewards
 */

require_once __DIR__ . '/../../src/paths.php';
safe_require('auth.php', 'src', true);
safe_require('db.php', 'src', true);

header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);
$customer_id = intval($input['customer_id'] ?? 0);
$company_id = intval($input['company_id'] ?? 0);

if (!$customer_id || !$company_id) {
    echo json_encode(['success' => false, 'error' => 'Customer ID and Company ID required']);
    exit;
}

try {
    $pdo = get_db_connection();

    // Get customer loyalty overview
    $sql = "
        SELECT 
            c.id as customer_id,
            c.name,
            c.email,
            c.phone,
            COALESCE(lp.available_points, 0) as available_points,
            COALESCE(lp.lifetime_points, 0) as lifetime_points,
            COALESCE(lp.tier, 'bronze') as tier,
            COALESCE(lp.total_redeemed, 0) as total_redeemed,
            COALESCE(lp.total_savings, 0) as total_savings,
            lp.created_at,
            lp.updated_at,
            (SELECT COUNT(*) FROM loyalty_referrals WHERE referrer_id = c.id AND status = 'completed') as referral_count,
            (SELECT SUM(points_earned) FROM loyalty_referrals WHERE referrer_id = c.id AND status = 'completed') as referral_points
        FROM customers c
        LEFT JOIN loyalty_program lp ON c.id = lp.customer_id AND lp.company_id = c.company_id
        WHERE c.id = ? AND c.company_id = ?
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$customer_id, $company_id]);
    $loyalty = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$loyalty) {
        // Create new loyalty record if doesn't exist
        $sql = "
            INSERT INTO loyalty_program (customer_id, company_id, available_points, lifetime_points, tier, created_at)
            VALUES (?, ?, 0, 0, 'bronze', NOW())
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$customer_id, $company_id]);
        
        $loyalty = [
            'customer_id' => $customer_id,
            'available_points' => 0,
            'lifetime_points' => 0,
            'tier' => 'bronze',
            'total_redeemed' => 0,
            'total_savings' => 0,
            'referral_count' => 0,
            'referral_points' => 0
        ];
    }

    // Get recent points history
    $sql = "
        SELECT 
            id,
            points,
            type,
            description,
            sale_id,
            multiplier_applied,
            created_at
        FROM loyalty_transactions
        WHERE customer_id = ? AND company_id = ?
        ORDER BY created_at DESC
        LIMIT 10
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$customer_id, $company_id]);
    $history = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get available rewards
    $sql = "
        SELECT 
            id,
            name,
            description,
            points_cost,
            discount_value,
            discount_type,
            expiry_days
        FROM loyalty_rewards
        WHERE company_id = ?
        AND (min_tier IS NULL OR min_tier = ? OR FIELD(?, 'bronze', 'silver', 'gold', 'platinum') >= FIELD(min_tier, 'bronze', 'silver', 'gold', 'platinum'))
        AND is_active = 1
        AND (expiry_date IS NULL OR expiry_date > NOW())
        ORDER BY points_cost ASC
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$company_id, $loyalty['tier'], $loyalty['tier']]);
    $rewards = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get personalized offers based on purchase history
    $sql = "
        SELECT 
            o.id,
            o.name,
            o.description,
            o.discount_percent,
            o.points_bonus,
            o.valid_until
        FROM loyalty_personalized_offers o
        WHERE o.customer_id = ?
        AND o.company_id = ?
        AND o.status = 'active'
        AND o.valid_until > NOW()
        ORDER BY o.created_at DESC
        LIMIT 5
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$customer_id, $company_id]);
    $offers = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Calculate next tier progress
    $tiers = [
        'bronze' => ['min_points' => 0, 'next' => 'silver'],
        'silver' => ['min_points' => 500, 'next' => 'gold'],
        'gold' => ['min_points' => 2000, 'next' => 'platinum'],
        'platinum' => ['min_points' => 5000, 'next' => null]
    ];
    
    $current_tier = $loyalty['tier'];
    $next_tier = $tiers[$current_tier]['next'] ?? null;
    $next_tier_points = $next_tier ? $tiers[$next_tier]['min_points'] : null;
    $points_to_next = $next_tier_points ? ($next_tier_points - $loyalty['lifetime_points']) : 0;

    echo json_encode([
        'success' => true,
        'loyalty' => $loyalty,
        'history' => $history,
        'rewards' => $rewards,
        'personalized_offers' => $offers,
        'tier_progress' => [
            'current_tier' => $current_tier,
            'next_tier' => $next_tier,
            'lifetime_points' => $loyalty['lifetime_points'],
            'points_to_next' => max(0, $points_to_next),
            'progress_percent' => $next_tier ? min(100, ($loyalty['lifetime_points'] / $next_tier_points) * 100) : 100
        ]
    ]);

} catch (Exception $e) {
    error_log('Advanced loyalty error: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'Failed to load loyalty data'
    ]);
}
