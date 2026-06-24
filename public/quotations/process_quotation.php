<?php
/**
 * Process Quotation - Save quotation to database
 * @file /JDH_POS/public/quotations/process_quotation.php
 */

// ============================================
// Fix path resolution - try multiple approaches
// ============================================

// Method 1: Try relative path from current directory
$pathsFile = __DIR__ . '/../../src/paths.php';
if (!file_exists($pathsFile)) {
    // Method 2: Try absolute path from document root
    $pathsFile = $_SERVER['DOCUMENT_ROOT'] . '/JDH_POS/src/paths.php';
}
if (!file_exists($pathsFile)) {
    // Method 3: Try parent directory traversal
    $pathsFile = dirname(__DIR__, 3) . '/src/paths.php';
}
if (!file_exists($pathsFile)) {
    die("Cannot find paths.php. Please check your installation.");
}

require_once $pathsFile;

// ============================================
// Load required dependencies
// ============================================
safe_require('auth.php', 'src', true);
safe_require('db.php', 'src', true);
safe_require('functions.php', 'src', true);

// ============================================
// Start session for flash messages
// ============================================
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ============================================
// Verify user is logged in
// ============================================
require_login();

// ============================================
// Check permission
// ============================================
if (!check_permission('quotations.create') && !is_super_admin()) {
    http_response_code(403);
    die('Access Denied: You do not have permission to create quotations.');
}

// ============================================
// Get tenant and user context
// ============================================
$tenant_id = get_current_tenant_id();
$branch_id = get_current_branch_id();
$user_id = get_current_user_id();

if (!$tenant_id) {
    http_response_code(403);
    die('Access Denied: Invalid tenant context.');
}

// ============================================
// CSRF Validation
// ============================================
if (!isset($_POST['csrf_token']) || !isset($_SESSION['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    $_SESSION['error'] = 'Invalid security token. Please try again.';
    header('Location: add_quotation.php');
    exit;
}

// ============================================
// Get form data
// ============================================
$quotation_number = trim($_POST['quotation_number'] ?? '');
$customer_id = !empty($_POST['customer_id']) ? (int)$_POST['customer_id'] : null;
$valid_until = $_POST['valid_until'] ?? date('Y-m-d', strtotime('+7 days'));
$notes = trim($_POST['notes'] ?? '');
$terms = trim($_POST['terms'] ?? '');
$action = $_POST['action'] ?? 'save';
$status = $action === 'draft' ? 'draft' : 'sent';
$business_type_id = !empty($_POST['business_type_id']) ? (int)$_POST['business_type_id'] : null;

// ============================================
// Get products from form
// ============================================
$products = $_POST['products'] ?? [];
$total = 0;
$items = [];

// Get PDO connection first
$pdo = get_db_connection();

foreach ($products as $product) {
    if (!empty($product['id']) && !empty($product['qty']) && $product['qty'] > 0 && isset($product['price'])) {
        $product_id = (int)$product['id'];
        $qty = (int)$product['qty'];
        $price = (float)$product['price'];
        $subtotal = $qty * $price;
        $total += $subtotal;
        
        // Get product name from database for the item
        $product_name = null;
        try {
            $stmt = $pdo->prepare("SELECT name FROM products WHERE id = ? AND tenant_id = ?");
            $stmt->execute([$product_id, $tenant_id]);
            $prod = $stmt->fetch();
            if ($prod) $product_name = $prod['name'];
        } catch (Exception $e) {
            // If we can't get product name, use a placeholder
            $product_name = "Product ID: $product_id";
        }
        
        $items[] = [
            'product_id' => $product_id,
            'product_name' => $product_name,
            'quantity' => $qty,
            'price' => $price,
            'subtotal' => $subtotal
        ];
    }
}

// ============================================
// Validate at least one item
// ============================================
if (empty($items)) {
    $_SESSION['error'] = 'Please add at least one product to the quotation.';
    header('Location: add_quotation.php');
    exit;
}

// ============================================
// Validate quotation number
// ============================================
if (empty($quotation_number)) {
    $quotation_number = 'Q' . date('Ymd') . '-' . str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
}

// ============================================
// Save to database
// ============================================
try {
    // Check if quotations table exists, create if not
    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    
    if (!in_array('quotations', $tables)) {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `quotations` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `quotation_number` varchar(50) NOT NULL,
                `customer_id` int(11) DEFAULT NULL,
                `tenant_id` bigint(20) UNSIGNED NOT NULL,
                `branch_id` int(11) NOT NULL,
                `business_type_id` int(11) DEFAULT NULL,
                `created_by` int(11) NOT NULL,
                `total` decimal(15,2) DEFAULT 0.00,
                `status` enum('draft','sent','accepted','rejected','expired') DEFAULT 'draft',
                `valid_until` date DEFAULT NULL,
                `notes` text DEFAULT NULL,
                `terms` text DEFAULT NULL,
                `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `quotation_number` (`quotation_number`),
                KEY `idx_tenant_branch` (`tenant_id`, `branch_id`),
                KEY `idx_status` (`status`),
                KEY `idx_customer` (`customer_id`),
                KEY `idx_created_at` (`created_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }
    
    // Add business_type_id column if missing (migration for existing tables)
    $cols = $pdo->query("SHOW COLUMNS FROM `quotations` LIKE 'business_type_id'")->fetchAll();
    if (empty($cols)) {
        $pdo->exec("ALTER TABLE `quotations` ADD COLUMN `business_type_id` int(11) DEFAULT NULL AFTER `branch_id`");
    }

    // Add terms column if missing (migration for existing tables)
    $cols = $pdo->query("SHOW COLUMNS FROM `quotations` LIKE 'terms'")->fetchAll();
    if (empty($cols)) {
        $pdo->exec("ALTER TABLE `quotations` ADD COLUMN `terms` text DEFAULT NULL AFTER `notes`");
    }

    if (!in_array('quotation_items', $tables)) {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `quotation_items` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `quotation_id` int(11) NOT NULL,
                `product_id` int(11) DEFAULT NULL,
                `product_name` varchar(255) DEFAULT NULL,
                `quantity` int(11) NOT NULL,
                `price` decimal(15,2) NOT NULL,
                `subtotal` decimal(15,2) NOT NULL,
                PRIMARY KEY (`id`),
                KEY `idx_quotation` (`quotation_id`),
                KEY `idx_product` (`product_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }
    
    $pdo->beginTransaction();
    
    // Insert quotation
    $stmt = $pdo->prepare("
        INSERT INTO quotations (
            quotation_number, customer_id, tenant_id, branch_id, business_type_id, 
            created_by, total, status, valid_until, notes, terms, created_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
    ");
    
    $stmt->execute([
        $quotation_number, 
        $customer_id, 
        $tenant_id, 
        $branch_id, 
        $business_type_id,
        $user_id, 
        $total, 
        $status, 
        $valid_until, 
        $notes, 
        $terms
    ]);
    
    $quotation_id = $pdo->lastInsertId();
    
    // Insert items
    $stmt = $pdo->prepare("
        INSERT INTO quotation_items (quotation_id, product_id, product_name, quantity, price, subtotal)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    
    foreach ($items as $item) {
        $stmt->execute([
            $quotation_id,
            $item['product_id'],
            $item['product_name'],
            $item['quantity'],
            $item['price'],
            $item['subtotal']
        ]);
    }
    
    // Log activity (optional, don't fail if table doesn't exist)
    try {
        // Check if activity_logs table exists
        if (in_array('activity_logs', $tables)) {
            $activityStmt = $pdo->prepare("
                INSERT INTO activity_logs (branch_id, user_id, action, description, meta, tenant_id, created_at)
                VALUES (?, ?, 'quotation_created', 'Created quotation', ?, ?, NOW())
            ");
            $meta = json_encode([
                'quotation_id' => $quotation_id,
                'quotation_number' => $quotation_number,
                'total' => $total,
                'status' => $status
            ]);
            $activityStmt->execute([$branch_id, $user_id, $meta, $tenant_id]);
        }
    } catch (Exception $e) {
        // Activity logging is optional, don't fail if it doesn't work
        error_log("Activity log failed: " . $e->getMessage());
    }
    
    $pdo->commit();
    
    $_SESSION['success_message'] = $status === 'draft' ? 'Quotation saved as draft successfully' : 'Quotation created successfully';
    header("Location: view_quotation.php?id=" . $quotation_id);
    exit;
    
} catch (PDOException $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Database error saving quotation: " . $e->getMessage());
    // TEMP DEBUG
    die('<pre style="background:#1e293b;color:#f87171;padding:1rem">PDOException: ' . htmlspecialchars($e->getMessage()) . "\n" . htmlspecialchars($e->getTraceAsString()) . '</pre>');
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Error saving quotation: " . $e->getMessage());
    // TEMP DEBUG
    die('<pre style="background:#1e293b;color:#f87171;padding:1rem">Exception: ' . htmlspecialchars($e->getMessage()) . "\n" . htmlspecialchars($e->getTraceAsString()) . '</pre>');
}
?>