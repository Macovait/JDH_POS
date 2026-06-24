<?php
/**
 * Save a filter configuration for the sales/returns search
 */
require_once __DIR__ . '/../../src/paths.php';
safe_require('auth.php', 'src', true);
safe_require('db.php', 'src', true);

require_login();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'POST required']);
    exit;
}

try {
    $pdo = get_db_connection();
    $user_id = get_current_user_id();
    $tenant_id = get_current_tenant_id();
    $name = trim($_POST['name'] ?? '');

    if (empty($name)) {
        echo json_encode(['success' => false, 'error' => 'Filter name required']);
        exit;
    }

    $config = [
        'search' => trim($_POST['search'] ?? ''),
        'date_from' => $_POST['date_from'] ?? '',
        'date_to' => $_POST['date_to'] ?? '',
        'status' => $_POST['status'] ?? '',
        'risk' => $_POST['risk'] ?? '',
        'amount' => $_POST['amount'] ?? '',
        'customer_type' => $_POST['customer_type'] ?? ''
    ];

    // Check if table exists
    $table_exists = false;
    try {
        $stmt = $pdo->query("SHOW TABLES LIKE 'saved_filters'");
        $table_exists = $stmt && $stmt->fetch();
    } catch (Exception $e) {}

    if (!$table_exists) {
        // Create table if missing
        $pdo->exec("CREATE TABLE saved_filters (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            tenant_id INT NOT NULL,
            filter_name VARCHAR(100) NOT NULL,
            is_default BOOLEAN DEFAULT FALSE,
            config JSON NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_user_tenant (user_id, tenant_id)
        )");
    }

    // Check if this is an update
    $id = (int)($_POST['id'] ?? 0);
    if ($id > 0) {
        $stmt = $pdo->prepare("UPDATE saved_filters SET filter_name = ?, config = ? WHERE id = ? AND user_id = ? AND tenant_id = ?");
        $stmt->execute([$name, json_encode($config), $id, $user_id, $tenant_id]);
    } else {
        $stmt = $pdo->prepare("INSERT INTO saved_filters (user_id, tenant_id, filter_name, config) VALUES (?, ?, ?, ?)");
        $stmt->execute([$user_id, $tenant_id, $name, json_encode($config)]);
        $id = $pdo->lastInsertId();
    }

    echo json_encode(['success' => true, 'id' => $id, 'name' => $name]);

} catch (Exception $e) {
    error_log("save_filter error: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Failed to save filter']);
}