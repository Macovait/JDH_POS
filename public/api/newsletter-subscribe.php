<?php
/**
 * Newsletter Subscription API
 * Stores subscriber emails for marketing updates.
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../src/paths.php';
require_once __DIR__ . '/../../src/db.php';

header('Content-Type: application/json');

$email = trim($_POST['email'] ?? '');

if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Please enter a valid email address.']);
    exit;
}

try {
    $pdo = get_db_connection();
    $prefix = defined('DB_TABLE_PREFIX') ? DB_TABLE_PREFIX : 'pos_';

    // Ensure table exists (lazy migration)
    $pdo->exec("CREATE TABLE IF NOT EXISTS {$prefix}newsletter_subscribers (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        email VARCHAR(255) NOT NULL,
        source VARCHAR(100) DEFAULT 'landing_page',
        subscribed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        is_active TINYINT DEFAULT 1,
        UNIQUE KEY unique_email (email)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $stmt = $pdo->prepare("INSERT INTO {$prefix}newsletter_subscribers (email, source) VALUES (?, 'landing_page') ON DUPLICATE KEY UPDATE is_active = 1");
    $stmt->execute([$email]);

    echo json_encode(['success' => true, 'message' => 'Thank you! You have been subscribed successfully.']);
} catch (Exception $e) {
    error_log('Newsletter subscribe error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Something went wrong. Please try again later.']);
}
