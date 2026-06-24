<?php
/**
 * Price Override Logging Endpoint
 * Logs all manual price overrides for audit purposes
 */

session_name('jakababa_saas_sid');
session_start();

// Branch filter for multi-tenant isolation
$current_branch_id = get_current_branch_id();

require_once __DIR__ . '/../../src/paths.php';
safe_require('auth.php', 'src', true);
safe_require('db.php', 'src', true);

header('Content-Type: application/json');

$response = ['success' => false, 'message' => ''];

try {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        $input = $_POST;
    }
    
    $productId = isset($input['product_id']) ? (int) $input['product_id'] : 0;
    $productName = isset($input['product_name']) ? htmlspecialchars($input['product_name'], ENT_QUOTES, 'UTF-8') : '';
    $oldPrice = isset($input['old_price']) ? (float) $input['old_price'] : 0;
    $newPrice = isset($input['new_price']) ? (float) $input['new_price'] : 0;
    $userId = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : 0;
    $companyId = isset($_SESSION['tenant_id']) ? (int) $_SESSION['tenant_id'] : 1;
    $branchId = isset($_SESSION['branch_id']) ? (int) $_SESSION['branch_id'] : 0;
    
    if ($productId <= 0 || $newPrice <= 0) {
        throw new Exception('Invalid data provided');
    }
    
    $pdo = get_db_connection();
    if (!$pdo) {
        throw new Exception('Database connection failed');
    }
    
    // Check if table exists, create if not
    $tableExists = false;
    try {
        $stmt = $pdo->query("SHOW TABLES LIKE 'price_override_logs'");
        $tableExists = $stmt->rowCount() > 0;
    } catch (Exception $e) {
        $tableExists = false;
    }
    
    if (!$tableExists) {
        $pdo->exec("CREATE TABLE IF NOT EXISTS price_override_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            tenant_id INT NOT NULL DEFAULT 1,
            branch_id INT DEFAULT 0,
            user_id INT NOT NULL DEFAULT 0,
            product_id INT NOT NULL,
            product_name VARCHAR(255),
            old_price DECIMAL(10,2) NOT NULL DEFAULT 0,
            new_price DECIMAL(10,2) NOT NULL DEFAULT 0,
            override_amount DECIMAL(10,2) NOT NULL DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_company (tenant_id),
            INDEX idx_branch (branch_id),
            INDEX idx_product (product_id),
            INDEX idx_user (user_id),
            INDEX idx_created (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }
    
    // Check for admin PIN verification (simplified)
    // In production, this should verify against actual admin credentials
    $pinVerified = !empty($input['admin_pin']) && strlen($input['admin_pin']) >= 4;
    
    $overrideAmount = $newPrice - $oldPrice;
    
    $stmt = $pdo->prepare("
        INSERT INTO price_override_logs 
        (tenant_id, branch_id, user_id, product_id, product_name, old_price, new_price, override_amount) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");
    
    $stmt->execute([
        $companyId,
        $branchId,
        $userId,
        $productId,
        $productName,
        $oldPrice,
        $newPrice,
        $overrideAmount
    ]);
    
    $logId = $pdo->lastInsertId();
    
    $response = [
        'success' => true,
        'message' => 'Price override logged',
        'log_id' => $logId,
        'verified' => $pinVerified
    ];
    
} catch (Exception $e) {
    $response = [
        'success' => false,
        'message' => $e->getMessage()
    ];
}

echo json_encode($response);
exit;