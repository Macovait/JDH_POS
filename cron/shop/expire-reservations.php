<?php
/**
 * Cron: Release expired inventory reservations
 * Run every 5 minutes via crontab
 *   5 * * * * /usr/bin/php /var/www/JDH_POS/cron/shop/expire-reservations.php
 */

require_once dirname(dirname(__DIR__)) . '/src/paths.php';
safe_require('db.php', 'src', true);

$pdo = get_db_connection();

// Release expired reservations
$stmt = $pdo->query("
    SELECT tenant_id, COUNT(*) as count
    FROM inventory_reservations
    WHERE expires_at <= NOW()
    GROUP BY tenant_id
");
$released = $stmt->fetchAll(PDO::FETCH_ASSOC);

$total = 0;
foreach ($released as $r) {
    $total += (int) $r['count'];
}

if ($total > 0) {
    $pdo->query("DELETE FROM inventory_reservations WHERE expires_at <= NOW()");

    // Mark abandoned carts
    $pdo->query("
        UPDATE carts c
        LEFT JOIN cart_items ci ON ci.cart_id = c.id
        SET c.abandoned_notified_at = COALESCE(c.abandoned_notified_at, NOW())
        WHERE ci.id IS NULL AND c.converted_to_order_id IS NULL
          AND c.last_activity < DATE_SUB(NOW(), INTERVAL 30 MINUTE)
          AND c.abandoned_notified_at IS NULL
    ");

    error_log("[Shop Cron] Released {$total} expired reservations at " . date('Y-m-d H:i:s'));
}

echo "Released {$total} expired reservations.\n";
