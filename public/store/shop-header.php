<?php
/**
 * Shared Shop Header
 */
?>
<header class="shop-header">
    <div class="shop-header-inner">
        <a href="./?tenant=<?php echo $tenant_id; ?>" class="logo"><i class="fas fa-store"></i> <?php echo htmlspecialchars($store_name); ?></a>
        <div class="header-search">
            <form method="GET" action="./">
                <input type="hidden" name="tenant" value="<?php echo $tenant_id; ?>">
                <input type="text" name="q" value="<?php echo htmlspecialchars($_GET['q'] ?? ''); ?>" placeholder="Search products, brands and categories" aria-label="Search">
                <button type="submit"><i class="fas fa-search"></i> Search</button>
            </form>
        </div>
        <nav class="shop-header-nav">
            <a href="./?tenant=<?php echo $tenant_id; ?>">Shop</a>
            <a href="orders.php?tenant=<?php echo $tenant_id; ?>">My Orders</a>
            <a href="track.php?tenant=<?php echo $tenant_id; ?>">Track Order</a>
        </nav>
        <button class="cart-btn" onclick="toggleCart()">
            <i class="fas fa-shopping-cart"></i>
            <span>Cart</span>
            <span id="cartBadge" style="display:none; background:#c62828; color:#fff; font-size:0.7rem; padding:0.1rem 0.45rem; border-radius:10px;">0</span>
        </button>
    </div>
</header>

<!-- Cart Drawer -->
<div class="cart-overlay" id="cartOverlay" onclick="toggleCart()"></div>
<div class="cart-drawer" id="cartDrawer">
    <div class="cart-header">
        <h3><i class="fas fa-shopping-cart"></i> Your Cart</h3>
        <button class="cart-close" onclick="toggleCart()">&times;</button>
    </div>
    <div class="cart-items" id="cartItems">
        <div class="empty-cart">Your cart is empty</div>
    </div>
    <div class="cart-footer">
        <div class="cart-total"><span>Total</span><span id="cartTotal"><?php echo $currency; ?> 0.00</span></div>
        <button class="checkout-btn" onclick="openCheckout()">Proceed to Checkout</button>
    </div>
</div>

<!-- Checkout Modal -->
<div class="modal-overlay" id="checkoutModal">
    <div class="modal">
        <h2><i class="fas fa-credit-card"></i> Checkout</h2>
        <div id="checkoutForm">
            <div class="form-group"><label>Full Name</label><input type="text" id="coName" placeholder="John Doe" required></div>
            <div class="form-group"><label>Phone / WhatsApp</label><input type="tel" id="coPhone" placeholder="+254700000000" required></div>
            <div class="form-group"><label>Email</label><input type="email" id="coEmail" placeholder="john@example.com"></div>
            <div class="form-group"><label>Delivery Address</label><textarea id="coAddress" rows="2" placeholder="Street, City, Country"></textarea></div>
            <div class="form-group"><label>Payment Method</label>
                <select id="coPayment">
                    <option value="cash">Cash on Delivery</option>
                    <option value="mpesa">M-Pesa</option>
                    <?php if (!empty($settings['stripe_secret_key'])): ?><option value="stripe">Card (Stripe)</option><?php endif; ?>
                    <?php if (!empty($settings['paypal_client_id'])): ?><option value="paypal">PayPal</option><?php endif; ?>
                </select>
            </div>
            <div class="modal-actions">
                <button class="btn-secondary" onclick="closeCheckout()">Cancel</button>
                <button class="btn-primary" onclick="submitOrder()">Place Order</button>
            </div>
        </div>
        <div id="checkoutSuccess" style="display:none; text-align:center; padding:1rem 0;">
            <i class="fas fa-check-circle" style="font-size:3rem; color:#34d399; margin-bottom:1rem;"></i>
            <h3 style="margin-bottom:0.5rem;">Order Placed!</h3>
            <p style="color:var(--j-muted); margin-bottom:1rem;">Thank you for your order. We will contact you shortly.</p>
            <?php if (!empty($whatsapp_number)): ?>
            <a href="https://wa.me/<?php echo preg_replace('/[^0-9]/', '', $whatsapp_number); ?>?text=<?php echo urlencode($whatsapp_message); ?>" class="whatsapp-link" target="_blank"><i class="fab fa-whatsapp"></i> Chat on WhatsApp</a>
            <?php endif; ?>
            <button class="btn-primary" style="margin-top:1rem; width:100%;" onclick="closeCheckout(); clearCart();">Continue Shopping</button>
        </div>
    </div>
</div>
