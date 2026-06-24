<?php

// Branch filter for multi-tenant isolation
$current_branch_id = get_current_branch_id();
/**
 * Online Store - Shopping Cart
 */
require_once __DIR__ . '/../../src/bootstrap.php';
$tenantSlug = $_GET['tenant'] ?? '';
$pdo = get_db_connection();
$tenant = null;
if ($tenantSlug) {
    $stmt = $pdo->prepare("SELECT * FROM pos_tenants WHERE slug = ? AND status = 'active' LIMIT 1");
    $stmt->execute([$tenantSlug]);
    $tenant = $stmt->fetch(PDO::FETCH_ASSOC);
}
if (!$tenant) { http_response_code(404); echo "<h1>Store not found</h1>"; exit; }
?><!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Cart - <?= htmlspecialchars($tenant['name']) ?></title>
<link rel="stylesheet" href="/assets/css/store.css">
<script src="/assets/js/store.js" defer></script>
</head>
<body class="store-body">
<header class="store-header"><div class="store-brand"><a href="index.php?tenant=<?= urlencode($tenantSlug) ?>"><h1><?= htmlspecialchars($tenant['name']) ?></h1></a></div></header>
<main class="store-cart-page">
    <h2>Shopping Cart</h2>
    <div id="cartItems"></div>
    <div class="cart-summary">
        <p><strong>Total:</strong> <span id="cartTotal">0.00</span> <?= htmlspecialchars($tenant['currency'] ?? 'KES') ?></p>
        <span class="checkout-btn opacity-50 cursor-not-allowed" title="Checkout coming soon">Proceed to Checkout</span>
    </div>
</main>
<script>const TENANT_SLUG = <?= json_encode($tenantSlug) ?>;</script>
</body></html>
