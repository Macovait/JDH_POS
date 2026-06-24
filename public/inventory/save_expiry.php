<?php
/**
 * Save Expiry Entry - Handle product expiry form submissions
 */

require_once __DIR__ . '/../../src/paths.php';
safe_require('auth.php', 'src', true);
safe_require('db.php', 'src', true);
safe_require('functions.php', 'src', true);
require_login();

$pdo = get_db_connection();
$user_id = get_current_user_id();
$tenant_id = get_current_tenant_id();
$branch_id = get_current_branch_id();

$action = $_POST['action'] ?? '';
$csrf_token = $_POST['csrf_token'] ?? '';

if (!verify_csrf_token($csrf_token)) {
    header('Location: item_expiry.php?error=Security validation failed');
    exit;
}

function ensureExpiryTables($pdo) {
    try {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS product_expiry (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                tenant_id INT UNSIGNED NOT NULL,
                branch_id INT UNSIGNED DEFAULT 1,
                product_id INT UNSIGNED NOT NULL,
                batch_number VARCHAR(100),
                quantity INT DEFAULT 1,
                expiry_date DATE NOT NULL,
                notes TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NULL,
                INDEX idx_company (tenant_id),
                INDEX idx_product (product_id),
                INDEX idx_expiry (expiry_date),
                INDEX idx_batch (batch_number)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    } catch (PDOException $e) {
        error_log("Expiry table error: " . $e->getMessage());
    }
}

ensureExpiryTables($pdo);

if ($action === 'create') {
    $product_id = intval($_POST['product_id'] ?? 0);
    $batch_number = trim($_POST['batch_number'] ?? '');
    $quantity = intval($_POST['quantity'] ?? 1);
    $expiry_date = $_POST['expiry_date'] ?? '';
    $notes = trim($_POST['notes'] ?? '');

    if ($product_id > 0 && !empty($expiry_date)) {
        try {
            $stmt = $pdo->prepare('
                INSERT INTO product_expiry (tenant_id, branch_id, product_id, batch_number, quantity, expiry_date, notes, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
            ');
            $stmt->execute([$tenant_id, $branch_id, $product_id, $batch_number, $quantity, $expiry_date, $notes]);
            
            header('Location: item_expiry.php?success=Expiry entry added successfully');
            exit;
        } catch (PDOException $e) {
            error_log("Error saving expiry: " . $e->getMessage());
            header('Location: item_expiry.php?error=Failed to save expiry entry');
            exit;
        }
    } else {
        header('Location: item_expiry.php?error=Invalid data');
        exit;
    }
} elseif ($action === 'delete') {
    $id = intval($_POST['id'] ?? 0);
    if ($id > 0) {
        try {
            $stmt = $pdo->prepare('DELETE FROM product_expiry WHERE id = ? AND tenant_id = ?');
            $stmt->execute([$id, $tenant_id]);
            header('Location: item_expiry.php?success=Entry deleted');
            exit;
        } catch (PDOException $e) {
            error_log("Error deleting expiry: " . $e->getMessage());
            header('Location: item_expiry.php?error=Failed to delete');
            exit;
        }
    }
}

header('Location: item_expiry.php');
exit;