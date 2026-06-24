<?php
/**
 * Categories API Endpoint
 * Returns category data from database based on business_type
 * Fallback to demo data if API fails
 * 
 * @package Jakababa POS API
 */

// Configuration
define('API_LOAD_CORE', true);
$root_path = dirname(dirname(__DIR__));
$paths_file = $root_path . '/src/paths.php';

if (!file_exists($paths_file)) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'System configuration not found']);
    exit;
}

require_once $paths_file;

// Start session BEFORE loading core files (needed for AJAX compatibility)
if (session_status() === PHP_SESSION_NONE) {
    session_name('jakababa_saas_sid');
    
    // Calculate proper cookie path based on script location
    $script_name = $_SERVER['SCRIPT_NAME'] ?? '';
    $cookie_path = '/';
    if (strpos($script_name, '/JDH_POS/') === 0) {
        $cookie_path = '/JDH_POS/';
    }
    
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => $cookie_path,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    session_start();
}

load_core_files();

// Get parameters
$current_user_id = (int) (get_current_user_id() ?? 0);
$current_tenant_id = (int) (get_current_tenant_id() ?? 0);
$current_branch_id = (int) (get_current_branch_id() ?? 0);
$current_business_type = (string) (get_current_business_type($current_tenant_id) ?? ($_SESSION['business_type'] ?? 'retail'));
$current_business_type_id = (int) (get_current_business_type_id($current_tenant_id) ?? 0);

$requested_user_id = isset($_GET['user_id']) ? (int) $_GET['user_id'] : $current_user_id;
$requested_tenant_id = isset($_GET['tenant_id']) ? (int) $_GET['tenant_id'] : $current_tenant_id;
$requested_branch_id = isset($_GET['branch_id']) ? (int) $_GET['branch_id'] : $current_branch_id;
$requested_business_type = trim((string) ($_GET['business_type'] ?? $current_business_type));
$requested_business_type_id = isset($_GET['business_type_id']) ? (int) $_GET['business_type_id'] : $current_business_type_id;

if ($current_user_id <= 0 || $current_tenant_id <= 0) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Session expired. Please log in again.']);
    exit;
}

if ($requested_tenant_id > 0 && $requested_tenant_id !== $current_tenant_id) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Company context mismatch.']);
    exit;
}

if ($requested_user_id > 0 && $requested_user_id !== $current_user_id) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'User context mismatch.']);
    exit;
}

if ($current_business_type !== '' && $requested_business_type !== '' && $requested_business_type !== $current_business_type) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Business type mismatch.']);
    exit;
}

if ($current_business_type_id > 0 && $requested_business_type_id > 0 && $requested_business_type_id !== $current_business_type_id) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Business type mismatch.']);
    exit;
}

$tenant_id = $current_tenant_id;
$branch_id = $requested_branch_id > 0 ? $requested_branch_id : $current_branch_id;
$business_type = $current_business_type !== '' ? $current_business_type : $requested_business_type;
$business_type_id = $current_business_type_id > 0 ? $current_business_type_id : $requested_business_type_id;

// Set JSON header
header('Content-Type: application/json');

// Try database first, fallback to demo data on error
try {
    $pdo = get_db_connection();
    
    if (!$pdo) {
        throw new Exception("Database connection failed");
    }
    
    // Build query with proper column check
    $columnsCheck = $pdo->query("SHOW COLUMNS FROM categories LIKE 'tenant_id'");
    $has_company = $columnsCheck->rowCount() > 0;
    
    $columnsCheck = $pdo->query("SHOW COLUMNS FROM categories LIKE 'business_type'");
    $has_business_type = $columnsCheck->rowCount() > 0;

    $columnsCheck = $pdo->query("SHOW COLUMNS FROM categories LIKE 'business_type_id'");
    $has_business_type_id = $columnsCheck->rowCount() > 0;

    $columnsCheck = $pdo->query("SHOW COLUMNS FROM categories LIKE 'branch_id'");
    $has_branch = $columnsCheck->rowCount() > 0;

    $columnsCheck = $pdo->query("SHOW COLUMNS FROM categories LIKE 'status'");
    $has_status = $columnsCheck->rowCount() > 0;

    $columnsCheck = $pdo->query("SHOW COLUMNS FROM categories LIKE 'deleted_at'");
    $has_deleted_at = $columnsCheck->rowCount() > 0;

    $can_view_other_branches = (function_exists('is_super_admin') && is_super_admin())
        || (function_exists('check_permission') && (check_permission('branches.view') || check_permission('branches.manage')));

    if (!$can_view_other_branches && $current_branch_id > 0 && $branch_id > 0 && $branch_id !== $current_branch_id) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'You are not allowed to view categories for that branch.']);
        exit;
    }
    
    // Build WHERE clause
    $where = "WHERE 1=1";
    $params = [];
    
    if ($has_company && $tenant_id > 0) {
        $where .= " AND (c.tenant_id = ? OR c.tenant_id IS NULL)";
        $params[] = $tenant_id;
    }

    if ($has_branch && $branch_id > 0) {
        $where .= " AND (c.branch_id = ? OR c.branch_id IS NULL)";
        $params[] = $branch_id;
    }
    
    if ($has_business_type_id && $business_type_id > 0 && $has_business_type && !empty($business_type)) {
        $where .= " AND (c.business_type_id = ? OR (c.business_type_id IS NULL AND c.business_type = ?))";
        $params[] = $business_type_id;
        $params[] = $business_type;
    } elseif ($has_business_type_id && $business_type_id > 0) {
        $where .= " AND c.business_type_id = ?";
        $params[] = $business_type_id;
    } elseif ($has_business_type && !empty($business_type)) {
        $where .= " AND c.business_type = ?";
        $params[] = $business_type;
    }

    if ($has_status) {
        $where .= " AND (c.status = 'active' OR c.status IS NULL OR c.status = '')";
    }

    if ($has_deleted_at) {
        $where .= " AND (c.deleted_at IS NULL OR c.deleted_at = '0000-00-00 00:00:00')";
    }
    
    // Categories query
    $sql = "SELECT c.id, c.name, c.icon, c.color, c.business_type, c.tenant_id
            FROM categories c
            {$where}
            ORDER BY c.name ASC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Return successful response
    echo json_encode([
        'success' => true,
        'data' => $categories,
        'business_type' => $business_type,
        'tenant_id' => $tenant_id,
        'branch_id' => $branch_id,
        'user_id' => $current_user_id,
        'source' => 'database'
    ]);
    
} catch (Exception $e) {
    // Fallback to demo data on error
    $demoCategories = getDemoCategories($business_type);
    echo json_encode([
        'success' => true,
        'data' => $demoCategories['categories'],
        'business_type' => $business_type,
        'tenant_id' => $tenant_id,
        'branch_id' => $branch_id,
        'user_id' => $current_user_id,
        'source' => 'demo'
    ]);
}

/**
 * Get demo categories fallback data
 */
function getDemoCategories($business_type) {
    $demoCategories = [
        'retail' => [
            ['id' => 1, 'name' => 'Beverages', 'icon' => 'fa-glass-water', 'color' => '#3B82F6'],
            ['id' => 2, 'name' => 'Bakery', 'icon' => 'fa-bread-slice', 'color' => '#F59E0B'],
            ['id' => 3, 'name' => 'Snacks', 'icon' => 'fa-cookie', 'color' => '#10B981'],
            ['id' => 4, 'name' => 'Household', 'icon' => 'fa-house', 'color' => '#8B5CF6'],
            ['id' => 5, 'name' => 'Groceries', 'icon' => 'fa-basket-shopping', 'color' => '#EF4444'],
        ],
        'restaurant' => [
            ['id' => 11, 'name' => 'Meals', 'icon' => 'fa-utensils', 'color' => '#F59E0B'],
            ['id' => 12, 'name' => 'Proteins', 'icon' => 'fa-drumstick-bite', 'color' => '#EF4444'],
            ['id' => 13, 'name' => 'Vegetarian', 'icon' => 'fa-leaf', 'color' => '#10B981'],
            ['id' => 14, 'name' => 'Drinks', 'icon' => 'fa-mug-hot', 'color' => '#3B82F6'],
        ],
        'supermarket' => [
            ['id' => 21, 'name' => 'Staples', 'icon' => 'fa-bowl-rice', 'color' => '#F59E0B'],
            ['id' => 22, 'name' => 'Beverages', 'icon' => 'fa-bottle-water', 'color' => '#10B981'],
            ['id' => 23, 'name' => 'Household', 'icon' => 'fa-house-chimney', 'color' => '#8B5CF6'],
            ['id' => 24, 'name' => 'Personal Care', 'icon' => 'fa-user', 'color' => '#EC4899'],
        ],
        'hardware' => [
            ['id' => 31, 'name' => 'Tools', 'icon' => 'fa-wrench', 'color' => '#6B7280'],
            ['id' => 32, 'name' => 'Paints', 'icon' => 'fa-paint-roller', 'color' => '#EF4444'],
            ['id' => 33, 'name' => 'Fasteners', 'icon' => 'fa-screwdriver', 'color' => '#8B5CF6'],
            ['id' => 34, 'name' => 'Electrical', 'icon' => 'fa-bolt', 'color' => '#FBBF24'],
        ],
        'salon' => [
            ['id' => 41, 'name' => 'Services', 'icon' => 'fa-scissors', 'color' => '#EC4899'],
            ['id' => 42, 'name' => 'Products', 'icon' => 'fa-bottle-empty', 'color' => '#10B981'],
        ],
        'electronics' => [
            ['id' => 51, 'name' => 'Cables', 'icon' => 'fa-plug', 'color' => '#3B82F6'],
            ['id' => 52, 'name' => 'Chargers', 'icon' => 'fa-charging-station', 'color' => '#10B981'],
            ['id' => 53, 'name' => 'Audio', 'icon' => 'fa-headphones', 'color' => '#8B5CF6'],
        ]
    ];
    
    $categories = $demoCategories[$business_type] ?? $demoCategories['retail'];
    
    return ['categories' => $categories];
}
