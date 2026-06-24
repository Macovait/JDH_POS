<?php
require_once __DIR__ . '/../src/paths.php';
safe_require('db.php', 'src', true);
$pdo = get_db_connection();

try {
    $pdo->exec("ALTER TABLE pos_subscriptions ADD COLUMN stripe_subscription_id VARCHAR(100) NULL AFTER updated_at");
    echo "Added stripe_subscription_id\n";
} catch (PDOException $e) {
    echo "Error adding stripe_subscription_id: " . $e->getMessage() . "\n";
}

try {
    $pdo->exec("ALTER TABLE pos_subscriptions ADD COLUMN stripe_payment_method_id VARCHAR(100) NULL AFTER stripe_subscription_id");
    echo "Added stripe_payment_method_id\n";
} catch (PDOException $e) {
    echo "Error adding stripe_payment_method_id: " . $e->getMessage() . "\n";
}
