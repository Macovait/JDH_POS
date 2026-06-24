<?php
require_once __DIR__ . '/../src/paths.php';
safe_require('db.php', 'src', true);
$pdo = get_db_connection();

$cols = $pdo->query("SHOW COLUMNS FROM pos_subscriptions")->fetchAll(PDO::FETCH_COLUMN);
echo "pos_subscriptions columns:\n";
foreach ($cols as $c) echo "  $c\n";
echo "Has stripe_subscription_id: " . (in_array('stripe_subscription_id', $cols) ? 'YES' : 'NO') . "\n";
echo "Has stripe_payment_method_id: " . (in_array('stripe_payment_method_id', $cols) ? 'YES' : 'NO') . "\n";
