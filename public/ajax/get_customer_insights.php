<?php
/**
 * Customer Insights API
 * 
 * Provides AI-powered customer insights for smart checkout:
 * - Average order value
 * - Purchase frequency
 * - Favorite categories
 * - Product recommendations
 */

require_once __DIR__ . '/../../src/paths.php';
safe_require('auth.php', 'src', true);
safe_require('functions.php', 'src', true);
safe_require('db.php', 'src', true);

require_login();

header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);
$customer_id = intval($input['customer_id'] ?? 0);
$tenant_id = intval($input['tenant_id'] ?? 0);
$branch_id = intval($input['branch_id'] ?? 0);

$session_tenant_id = get_current_tenant_id();

if (!$customer_id || !$tenant_id) {
    echo json_encode(['success' => false, 'error' => 'Customer ID and tenant ID required']);
    exit;
}

// Validate the requested tenant against the authenticated tenant.
if ($tenant_id !== $session_tenant_id) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Unauthorized tenant context']);
    exit;
}

// Validate branch belongs to tenant
if ($branch_id) {
    $validated_branch = resolve_branch_id($branch_id, $session_tenant_id);
    if ($validated_branch <= 0) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Invalid branch']);
        exit;
    }
    $branch_id = $validated_branch;
}

try {
    $pdo = get_db_connection();

    // Establish ownership before reading any customer-related data.
    $owner = $pdo->prepare('SELECT id FROM customers WHERE id = ? AND tenant_id = ? AND deleted_at IS NULL LIMIT 1');
    $owner->execute([$customer_id, $session_tenant_id]);
    if (!$owner->fetchColumn()) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Customer not found']);
        exit;
    }

    // Get purchase statistics
    $sql = "
        SELECT 
            COUNT(*) as total_orders,
            AVG(total) as avg_order_value,
            SUM(total) as total_spent,
            MIN(created_at) as first_purchase,
            MAX(created_at) as last_purchase,
            COUNT(DISTINCT DATE(created_at)) as unique_visit_days
        FROM sales
        WHERE customer_id = ? AND tenant_id = ? AND status = 'completed'
    ";
    
    if ($branch_id) {
        $sql .= " AND branch_id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$customer_id, $session_tenant_id, $branch_id]);
    } else {
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$customer_id, $session_tenant_id]);
    }
    
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);

    // Calculate visit frequency
    $visit_frequency = 'New Customer';
    if ($stats['unique_visit_days'] > 0) {
        $days_since_first = max(1, (strtotime('now') - strtotime($stats['first_purchase'])) / 86400);
        $visits_per_month = ($stats['unique_visit_days'] / $days_since_first) * 30;
        
        if ($visits_per_month >= 4) {
            $visit_frequency = 'Frequent (Weekly)';
        } elseif ($visits_per_month >= 2) {
            $visit_frequency = 'Regular (Bi-weekly)';
        } elseif ($visits_per_month >= 1) {
            $visit_frequency = 'Occasional (Monthly)';
        } else {
            $visit_frequency = 'Rare';
        }
    }

    // Get favorite categories
    $sql = "
        SELECT 
            c.name as category_name,
            COUNT(*) as purchase_count,
            SUM(si.quantity * si.price) as total_spent
        FROM sales s
        JOIN sale_items si ON s.id = si.sale_id AND si.tenant_id = s.tenant_id
        JOIN products p ON si.product_id = p.id AND p.tenant_id = s.tenant_id
        JOIN categories c ON p.category_id = c.id AND c.tenant_id = s.tenant_id
        WHERE s.customer_id = ? AND s.tenant_id = ? AND s.status = 'completed'
        GROUP BY c.id
        ORDER BY purchase_count DESC
        LIMIT 3
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$customer_id, $session_tenant_id]);
    $favorite_categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $favorite_category = $favorite_categories[0]['category_name'] ?? 'Not enough data';

    // Get frequently purchased products for recommendations
    $sql = "
        SELECT 
            p.id,
            p.name,
            p.selling_price as price,
            p.image,
            COUNT(*) as purchase_count,
            'Your favorite' as reason
        FROM sales s
        JOIN sale_items si ON s.id = si.sale_id AND si.tenant_id = s.tenant_id
        JOIN products p ON si.product_id = p.id AND p.tenant_id = s.tenant_id
        WHERE s.customer_id = ? AND s.tenant_id = ?
        AND s.created_at > DATE_SUB(NOW(), INTERVAL 90 DAY)
        AND p.deleted_at IS NULL
        AND p.active = 1
        GROUP BY p.id
        ORDER BY purchase_count DESC, MAX(s.created_at) DESC
        LIMIT 5
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$customer_id, $session_tenant_id]);
    $recommended_products = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // If no purchase history, get popular products from favorite category
    if (empty($recommended_products) && $favorite_categories) {
        $category_id = $favorite_categories[0]['category_id'] ?? null;
        if ($category_id) {
            $sql = "
                SELECT 
                    p.id,
                    p.name,
                    p.selling_price as price,
                    p.image,
                    'Popular in ' + c.name as reason
                FROM products p
                JOIN categories c ON p.category_id = c.id AND c.tenant_id = p.tenant_id
                WHERE p.category_id = ?
                AND p.tenant_id = ?
                AND p.deleted_at IS NULL
                AND p.active = 1
                ORDER BY p.popularity_score DESC
                LIMIT 5
            ";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$category_id, $session_tenant_id]);
            $recommended_products = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    }

    // Get recent purchase pattern (time of day)
    $sql = "
        SELECT 
            HOUR(created_at) as hour,
            COUNT(*) as order_count
        FROM sales
        WHERE customer_id = ? AND tenant_id = ?
        AND created_at > DATE_SUB(NOW(), INTERVAL 30 DAY)
        GROUP BY HOUR(created_at)
        ORDER BY order_count DESC
        LIMIT 1
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$customer_id, $session_tenant_id]);
    $peak_hour = $stmt->fetch(PDO::FETCH_ASSOC);

    $preferred_time = null;
    if ($peak_hour) {
        $hour = $peak_hour['hour'];
        if ($hour >= 5 && $hour < 12) {
            $preferred_time = 'Morning shopper';
        } elseif ($hour >= 12 && $hour < 17) {
            $preferred_time = 'Afternoon shopper';
        } elseif ($hour >= 17 && $hour < 22) {
            $preferred_time = 'Evening shopper';
        } else {
            $preferred_time = 'Night shopper';
        }
    }

    // Check for upcoming birthday (if customer has DOB)
    $sql = "SELECT date_of_birth FROM customers WHERE id = ? AND tenant_id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$customer_id, $session_tenant_id]);
    $dob = $stmt->fetch(PDO::FETCH_ASSOC);

    $birthday_info = null;
    if ($dob && $dob['date_of_birth']) {
        $birthday = new DateTime($dob['date_of_birth']);
        $today = new DateTime();
        $next_birthday = new DateTime($today->format('Y') . '-' . $birthday->format('m-d'));
        
        if ($next_birthday < $today) {
            $next_birthday->modify('+1 year');
        }
        
        $days_until = $today->diff($next_birthday)->days;
        
        if ($days_until <= 7) {
            $birthday_info = [
                'is_upcoming' => true,
                'days_until' => $days_until,
                'message' => $days_until === 0 ? 'Birthday today!' : "Birthday in {$days_until} days!"
            ];
        }
    }

    echo json_encode([
        'success' => true,
        'insights' => [
            'avg_order_value' => round(floatval($stats['avg_order_value'] ?? 0), 2),
            'total_orders' => intval($stats['total_orders'] ?? 0),
            'total_spent' => round(floatval($stats['total_spent'] ?? 0), 2),
            'visit_frequency' => $visit_frequency,
            'favorite_category' => $favorite_category,
            'favorite_categories' => $favorite_categories,
            'last_visit' => $stats['last_purchase'],
            'first_purchase' => $stats['first_purchase'],
            'preferred_time' => $preferred_time,
            'birthday_info' => $birthday_info,
            'recommended_products' => $recommended_products
        ]
    ]);

} catch (Exception $e) {
    error_log('Customer insights error: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'Failed to load customer insights'
    ]);
}
