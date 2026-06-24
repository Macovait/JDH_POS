<?php
require_once __DIR__ . '/bootstrap.php';
echo "Bootstrap OK\n";
echo "Session admin_id: " . ($_SESSION['admin_id'] ?? 'NOT SET') . "\n";
echo "Session admin_role: " . ($_SESSION['admin_role'] ?? 'NOT SET') . "\n";
echo "Session is_super_admin: " . ($_SESSION['is_super_admin'] ?? 'NOT SET') . "\n";

$pdo = admin_db();
echo "DB: " . ($pdo ? 'CONNECTED' : 'FAILED - ' . admin_db_error()) . "\n";

if ($pdo) {
    foreach(['admins','pos_tenants','pos_subscriptions','pos_invoices','users','branches','sales'] as $t) {
        echo "$t: " . (admin_table_exists($t) ? 'EXISTS' : 'MISSING') . "\n";
    }
}
