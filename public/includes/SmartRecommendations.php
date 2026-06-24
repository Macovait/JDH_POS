<?php

// Branch filter for multi-tenant isolation
$current_branch_id = get_current_branch_id();
// ============================================================================
// SMART RECOMMENDATION ENGINE - Zero-Trust Implementation
// File: includes/SmartRecommendations.php
// ============================================================================

require_once __DIR__ . '/CartModel.php';

class SmartRecommendations {
    private $cartModel;
    private $cache_ttl = 300; // 5 minutes cache

    public function __construct() {
        $this->cartModel = new CartModel();
    }
    
    /**
     * Get real-time recommendations based on current context
     */
    public function getContextualRecommendations($current_cart = [], $customer_id = null) {
        $context = $this->analyzeCurrentContext();
        $recommendations = [];
        
        // 1. Time-based recommendations (happy hour, slow hours)
        if ($time_recos = $this->getTimeBasedRecommendations($context['hour'])) {
            $recommendations['time_based'] = $time_recos;
        }
        
        // 2. Stock-based recommendations (clear overstock, promote slow movers)
        if ($stock_recos = $this->getStockBasedRecommendations()) {
            $recommendations['stock_based'] = $stock_recos;
        }
        
        // 3. Cart-based recommendations (frequently bought together)
        if (!empty($current_cart) && $cart_recos = $this->getCartBasedRecommendations($current_cart)) {
            $recommendations['cart_based'] = $cart_recos;
        }
        
        // 4. Customer-based recommendations (personalized)
        if ($customer_id && $personal_recos = $this->getPersonalizedRecommendations($customer_id)) {
            $recommendations['personalized'] = $personal_recos;
        }
        
        // 5. Trending products (sales velocity)
        if ($trending = $this->getTrendingRecommendations()) {
            $recommendations['trending'] = $trending;
        }
        
        // 6. Bundle recommendations (profit-maximizing bundles)
        if ($bundles = $this->getBundleRecommendations($current_cart)) {
            $recommendations['bundles'] = $bundles;
        }
        
        return $recommendations;
    }
    
    /**
     * Analyze current context (time, traffic, stock levels)
     */
    private function analyzeCurrentContext() {
        $hour = (int)date('H');
        $day = (int)date('N');
        $is_weekend = ($day >= 6);

        // Determine traffic level based on sales velocity - using CartModel for scoping
        $stmt = $this->cartModel->pdo->prepare("
            SELECT COUNT(*) as transaction_count,
                   AVG(TIMESTAMPDIFF(MINUTE, created_at, NOW())) as avg_time
            FROM sales
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
            AND status = 'completed'
        ");
        $params = [];
        $this->cartModel->applyTenantScope($stmt, $params);
        $stmt->execute($params);
        $traffic = $stmt->fetch(PDO::FETCH_ASSOC);

        $traffic_level = 'normal';
        if ($traffic['transaction_count'] > 50) {
            $traffic_level = 'busy';
        } elseif ($traffic['transaction_count'] < 10) {
            $traffic_level = 'slow';
        }

        // Check stock health - using CartModel for scoping
        $stmt = $this->cartModel->pdo->prepare("
            SELECT COUNT(*) as low_stock_count
            FROM inventory i
            JOIN products p ON i.product_id = p.id
            WHERE i.stock <= p.reorder_level AND i.stock > 0
        ");
        $params = [];
        $this->cartModel->applyTenantScope($stmt, $params);
        $stmt->execute($params);
        $low_stock = $stmt->fetchColumn();

        return [
            'hour' => $hour,
            'day' => $day,
            'is_weekend' => $is_weekend,
            'traffic_level' => $traffic_level,
            'low_stock_count' => $low_stock,
            'is_busy_hour' => ($hour >= 12 && $hour <= 14) || ($hour >= 18 && $hour <= 20),
            'is_slow_hour' => ($hour >= 22 || $hour <= 8)
        ];
    }
    
    /**
     * Time-based recommendations (happy hour deals)
     */
    private function getTimeBasedRecommendations($hour) {
        // Check for happy hour (4PM - 7PM)
        if ($hour >= 16 && $hour <= 19) {
            $stmt = $this->cartModel->pdo->prepare("
                SELECT p.id, p.name, p.price,
                       (p.price * 0.85) as discounted_price,
                       'Happy Hour - 15% OFF' as reason
                FROM products p
                JOIN inventory i ON p.id = i.product_id
                WHERE i.stock > 0
                AND p.price > 100
                AND p.deleted_at IS NULL
                ORDER BY p.price DESC
                LIMIT 5
            ");
            $params = [];
            $this->cartModel->applyTenantScope($stmt, $params);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        // Breakfast hour (7AM - 10AM)
        if ($hour >= 7 && $hour <= 10) {
            $stmt = $this->cartModel->pdo->prepare("
                SELECT p.id, p.name, p.price, p.price as discounted_price,
                       'Breakfast Special' as reason
                FROM products p
                JOIN categories c ON p.category_id = c.id
                WHERE c.name IN ('Breakfast', 'Coffee', 'Beverages', 'Pastries')
                AND p.active = 1 AND p.deleted_at IS NULL
                LIMIT 5
            ");
            $params = [];
            $this->cartModel->applyTenantScope($stmt, $params);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        return [];
    }
    
    /**
     * Stock-based recommendations (clear overstock, promote expiring items)
     */
    private function getStockBasedRecommendations() {
        // Overstock items (more than 3x reorder level)
        $stmt = $this->cartModel->pdo->prepare("
            SELECT p.id, p.name, p.price,
                   (p.price * 0.8) as discounted_price,
                   CONCAT('Overstock Sale - ', i.stock, ' in stock') as reason,
                   'overstock' as type
            FROM inventory i
            JOIN products p ON i.product_id = p.id
            WHERE i.stock > (p.reorder_level * 3)
            AND i.stock > 0
            AND p.deleted_at IS NULL
            ORDER BY i.stock DESC
            LIMIT 5
        ");
        $params = [];
        $this->cartModel->applyTenantScope($stmt, $params);
        $stmt->execute($params);
        $overstock = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Expiring soon items (within 30 days)
        $stmt = $this->cartModel->pdo->prepare("
            SELECT p.id, p.name, p.price,
                   (p.price * 0.7) as discounted_price,
                   CONCAT('Expiring Soon - ', DATEDIFF(i.expiry_date, CURDATE()), ' days left') as reason,
                   'expiring' as type
            FROM inventory i
            JOIN products p ON i.product_id = p.id
            WHERE i.expiry_date IS NOT NULL
            AND i.expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)
            AND i.stock > 0
            AND p.deleted_at IS NULL
            ORDER BY i.expiry_date ASC
            LIMIT 5
        ");
        $params = [];
        $this->cartModel->applyTenantScope($stmt, $params);
        $stmt->execute($params);
        $expiring = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return array_merge($overstock, $expiring);
    }
    
    /**
     * Cart-based recommendations (frequently bought together)
     */
    private function getCartBasedRecommendations($cart) {
        if (empty($cart)) return [];

        $product_ids = array_column($cart, 'product_id');
        return $this->cartModel->getFrequentItems($product_ids);
    }
    
    /**
     * Personalized recommendations based on customer purchase history
     */
    private function getPersonalizedRecommendations($customer_id) {
        // Get customer's purchase history using CartModel
        $history = $this->cartModel->getCustomerHistory($customer_id);

        if (!$history || empty($history['categories'])) return [];

        // Recommend products from same categories they've bought before
        $categoryIds = explode(',', $history['categories']);
        $productIds = explode(',', $history['products']);

        $placeholders = str_repeat('?,', count($categoryIds) - 1) . '?';
        $productPlaceholders = str_repeat('?,', count($productIds) - 1) . '?';

        $stmt = $this->cartModel->pdo->prepare("
            SELECT p.id, p.name, p.price,
                   'Based on your purchase history' as reason
            FROM products p
            JOIN inventory i ON p.id = i.product_id
            WHERE p.category_id IN ($placeholders)
            AND p.id NOT IN ($productPlaceholders)
            AND i.stock > 0
            AND p.deleted_at IS NULL
            ORDER BY RAND()
            LIMIT 6
        ");
        $params = array_merge($categoryIds, $productIds);
        $this->cartModel->applyTenantScope($stmt, $params);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Trending products based on sales velocity
     */
    private function getTrendingRecommendations() {
        $stmt = $this->cartModel->pdo->prepare("
            SELECT p.id, p.name, p.price,
                   sv.velocity_score,
                   sv.today_sales,
                   CONCAT('Trending - ', sv.today_sales, ' sold today') as reason
            FROM sales_velocity sv
            JOIN products p ON sv.product_id = p.id
            JOIN inventory i ON p.id = i.product_id
            WHERE i.stock > 0
            ORDER BY sv.velocity_score DESC, sv.today_sales DESC
            LIMIT 8
        ");
        $params = [];
        $this->cartModel->applyTenantScope($stmt, $params);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Bundle recommendations (profit-maximizing combinations)
     */
    private function getBundleRecommendations($cart) {
        $cart_total = array_sum(array_column($cart, 'price') ?: [0]);

        // Find bundles that complement current cart
        $stmt = $this->cartModel->pdo->prepare("
            SELECT b.id, b.name, b.description,
                   b.total_price as bundle_price,
                   (b.total_price - b.discounted_price) as savings,
                   b.products_json,
                   'Bundle Deal' as reason
            FROM bundles b
            WHERE b.is_active = 1
            AND b.min_purchase <= ?
            ORDER BY b.discounted_price ASC
            LIMIT 3
        ");
        $params = [$cart_total];
        $this->cartModel->applyTenantScope($stmt, $params);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Update product affinity scores based on completed sales
     */
    public function updateAffinityScores($sale_id) {
        // Get all products in this sale - tenant scope applied via sale_items join
        $stmt = $this->cartModel->pdo->prepare("
            SELECT DISTINCT si.product_id FROM sale_items si
            JOIN sales s ON si.sale_id = s.id
            WHERE si.sale_id = ?
        ");
        $params = [$sale_id];
        $this->cartModel->applyTenantScope($stmt, $params);
        $stmt->execute($params);
        $products = $stmt->fetchAll(PDO::FETCH_COLUMN);

        if (count($products) < 2) return;

        // Update affinity for all product pairs
        for ($i = 0; $i < count($products); $i++) {
            for ($j = $i + 1; $j < count($products); $j++) {
                $this->updatePairAffinity($products[$i], $products[$j]);
                $this->updatePairAffinity($products[$j], $products[$i]);
            }
        }
    }

    private function updatePairAffinity($product_id, $related_id) {
        $stmt = $this->cartModel->pdo->prepare("
            INSERT INTO product_affinity (tenant_id, product_id, related_product_id, times_bought_together, affinity_score)
            VALUES (?, ?, ?, 1, 0.5)
            ON DUPLICATE KEY UPDATE
                times_bought_together = times_bought_together + 1,
                affinity_score = LEAST(1, affinity_score + 0.05)
        ");
        $stmt->execute([$this->cartModel->context->getCompanyId(), $product_id, $related_id]);
    }
    
    /**
     * Calculate and update sales velocity for trending products
     */
    public function updateSalesVelocity() {
        // Update daily sales velocity for all products - tenant scoping applied
        $stmt = $this->cartModel->pdo->prepare("
            INSERT INTO sales_velocity (tenant_id, branch_id, product_id, daily_rate, today_sales, velocity_score)
            SELECT
                s.tenant_id,
                s.branch_id,
                si.product_id,
                COUNT(si.id) / 30 as daily_rate,
                SUM(CASE WHEN DATE(s.created_at) = CURDATE() THEN si.quantity ELSE 0 END) as today_sales,
                (COUNT(si.id) / 30) * (SUM(si.quantity) / 100) as velocity_score
            FROM sales s
            JOIN sale_items si ON s.id = si.sale_id
            WHERE s.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            AND s.status = 'completed'
            GROUP BY s.tenant_id, s.branch_id, si.product_id
            ON DUPLICATE KEY UPDATE
                daily_rate = VALUES(daily_rate),
                today_sales = VALUES(today_sales),
                velocity_score = VALUES(velocity_score),
                calculated_at = NOW()
        ");
        $params = [];
        $this->cartModel->applyTenantScope($stmt, $params);
        $stmt->execute($params);
    }
    
    /**
     * Get real-time context-aware UI configuration
     */
    public function getUIConfiguration() {
        $context = $this->analyzeCurrentContext();
        $config = [
            'layout' => 'default',
            'show_sidebar' => true,
            'show_recommendations' => true,
            'product_card_size' => 'normal',
            'animation_speed' => 'normal'
        ];
        
        // Busy hours: compact layout, faster animations, fewer distractions
        if ($context['traffic_level'] === 'busy') {
            $config['layout'] = 'compact';
            $config['product_card_size'] = 'small';
            $config['animation_speed'] = 'fast';
            $config['show_recommendations'] = false;
            $config['show_sidebar'] = false;
        }
        
        // Slow hours: show more recommendations, larger cards, educational mode
        if ($context['traffic_level'] === 'slow') {
            $config['layout'] = 'expanded';
            $config['product_card_size'] = 'large';
            $config['show_recommendations'] = true;
            $config['show_sidebar'] = true;
            $config['show_tips'] = true;
        }
        
        // Low stock alert: highlight low stock items
        if ($context['low_stock_count'] > 5) {
            $config['show_stock_alerts'] = true;
            $config['low_stock_threshold'] = 10;
        }
        
        return $config;
    }
}
?>