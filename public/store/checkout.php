<?php
/**
 * Checkout Page — Modern Tailwind + Alpine.js
 * Multi-step checkout: Cart Review → Shipping → Payment → Confirmation
 */
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);
ini_set('display_errors', 0);

$root_path = dirname(dirname(__DIR__));
require_once $root_path . '/src/paths.php';
safe_require('db.php', 'src', true);
safe_require('functions.php', 'src', true);

$pdo = get_db_connection();
$current_branch_id = 0;
if (function_exists('get_current_branch_id')) {
    $current_branch_id = get_current_branch_id();
}

$tenant_id = isset($_GET['tenant']) ? (int) $_GET['tenant'] : 0;
if (!$tenant_id) {
    try {
        $row = $pdo->query("SELECT id FROM pos_tenants WHERE status IN ('active','trial') ORDER BY id ASC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $tenant_id = (int) $row['id'];
        } else {
            $row = $pdo->query("SELECT id FROM tenants WHERE active = 1 ORDER BY id ASC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
            if ($row) $tenant_id = (int) $row['id'];
        }
    } catch (Exception $e) {}
}
if (!$tenant_id) {
    http_response_code(404);
    echo '<h1>Store Not Found</h1>';
    exit;
}

$settings = [];
$stmt = $pdo->prepare("SELECT setting_key, setting_value FROM storefront_settings WHERE tenant_id = ?");
$stmt->execute([$tenant_id]);
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) $settings[$row['setting_key']] = $row['setting_value'];

$store_name = $settings['site_title'] ?? 'Jakababa';
$currency = $settings['currency'] ?? 'KES';
$primary_color = $settings['primary_color'] ?? '#f68b1e';
$whatsapp = $settings['whatsapp_number'] ?? '';
?>
<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout — <?php echo htmlspecialchars($store_name); ?></title>
    <link rel="stylesheet" href="<?php echo asset_url('css/app.css'); ?>">
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        brand: { DEFAULT: '<?php echo $primary_color; ?>', dark: '#e07d16' },
                        dark: { 900: '#0f0f1a', 800: '#1a1a2e', 700: '#252542', 600: '#323255' },
                        surface: '#ffffff',
                        muted: '#757575'
                    },
                    fontFamily: { sans: ['Inter', 'system-ui', 'sans-serif'] }
                }
            }
        }
    </script>
    <style>[x-cloak] { display: none !important; }</style>
</head>
<body class="bg-gray-50 text-gray-900 font-sans antialiased" x-data="checkoutApp()" x-init="initCheckout()">

<!-- TOP BAR -->
<div class="bg-dark-900 text-white text-xs py-2">
    <div class="max-w-7xl mx-auto px-4 flex justify-between items-center">
        <span>Secure Checkout</span>
        <div class="flex gap-4">
            <?php if ($whatsapp): ?>
            <a href="https://wa.me/<?php echo preg_replace('/[^0-9]/', '', $whatsapp); ?>" class="hover:text-brand transition"><i class="fab fa-whatsapp"></i> Help</a>
            <?php endif; ?>
            <a href="index.php?tenant=<?php echo $tenant_id; ?>" class="hover:text-brand transition">Continue Shopping</a>
        </div>
    </div>
</div>

<!-- HEADER -->
<header class="bg-white border-b sticky top-0 z-50 shadow-sm">
    <div class="max-w-7xl mx-auto px-4 py-3 flex items-center gap-4">
        <a href="index.php?tenant=<?php echo $tenant_id; ?>" class="flex items-center gap-2 shrink-0">
            <div class="w-10 h-10 bg-brand rounded-lg flex items-center justify-center text-white font-bold text-lg">J</div>
            <span class="text-xl font-bold text-dark-800 hidden sm:block"><?php echo htmlspecialchars($store_name); ?></span>
        </a>
        <div class="flex-1"></div>
        <div class="flex items-center gap-2 text-sm text-gray-500">
            <span class="flex items-center gap-1"><i class="fas fa-lock text-green-500"></i> Secure</span>
        </div>
    </div>
</header>

<!-- PROGRESS STEPS -->
<div class="bg-white border-b">
    <div class="max-w-3xl mx-auto px-4 py-4">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-2" :class="step >= 1 ? 'text-brand' : 'text-gray-400'">
                <div class="w-8 h-8 rounded-full flex items-center justify-center text-sm font-bold border-2" :class="step >= 1 ? 'bg-brand text-white border-brand' : 'border-gray-300'">1</div>
                <span class="hidden sm:inline font-medium">Cart</span>
            </div>
            <div class="flex-1 h-0.5 mx-3 bg-gray-200"><div class="h-full bg-brand transition-all" :style="`width: ${step >= 2 ? '100%' : '0%'}`"></div></div>
            <div class="flex items-center gap-2" :class="step >= 2 ? 'text-brand' : 'text-gray-400'">
                <div class="w-8 h-8 rounded-full flex items-center justify-center text-sm font-bold border-2" :class="step >= 2 ? 'bg-brand text-white border-brand' : 'border-gray-300'">2</div>
                <span class="hidden sm:inline font-medium">Shipping</span>
            </div>
            <div class="flex-1 h-0.5 mx-3 bg-gray-200"><div class="h-full bg-brand transition-all" :style="`width: ${step >= 3 ? '100%' : '0%'}`"></div></div>
            <div class="flex items-center gap-2" :class="step >= 3 ? 'text-brand' : 'text-gray-400'">
                <div class="w-8 h-8 rounded-full flex items-center justify-center text-sm font-bold border-2" :class="step >= 3 ? 'bg-brand text-white border-brand' : 'border-gray-300'">3</div>
                <span class="hidden sm:inline font-medium">Payment</span>
            </div>
        </div>
    </div>
</div>

<main class="max-w-7xl mx-auto px-4 py-8">
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">

        <!-- LEFT: Forms -->
        <div class="lg:col-span-2 space-y-4">

            <!-- STEP 1: CART REVIEW -->
            <div x-show="step === 1" x-transition>
                <h2 class="text-xl font-bold text-dark-800 mb-4 flex items-center gap-2"><i class="fas fa-shopping-cart text-brand"></i> Review Your Cart</h2>
                <div class="bg-white rounded-xl border border-gray-100 overflow-hidden">
                    <template x-if="cartItems.length === 0">
                        <div class="p-8 text-center">
                            <i class="fas fa-shopping-basket text-4xl text-gray-300 mb-3"></i>
                            <p class="text-gray-500">Your cart is empty</p>
                            <a href="index.php?tenant=<?php echo $tenant_id; ?>" class="mt-4 inline-block text-brand font-semibold text-sm hover:underline">Start Shopping</a>
                        </div>
                    </template>
                    <template x-for="item in cartItems" :key="item.id">
                        <div class="flex gap-4 p-4 border-b border-gray-100 last:border-0">
                            <img :src="item.image_url || '/assets/no-image.png'" class="w-20 h-20 object-contain bg-gray-50 rounded-lg" @error="$event.target.src='/assets/no-image.png'">
                            <div class="flex-1 min-w-0">
                                <div class="text-sm font-medium text-gray-900 truncate" x-text="item.product_name"></div>
                                <div class="text-brand font-bold text-sm" x-text="'<?php echo $currency; ?> ' + parseFloat(item.unit_price).toFixed(2)"></div>
                                <div class="flex items-center gap-2 mt-2">
                                    <button @click="updateQty(item.product_id, item.quantity - 1)" class="w-7 h-7 rounded bg-gray-100 flex items-center justify-center text-gray-600 hover:bg-gray-200 transition"><i class="fas fa-minus text-[10px]"></i></button>
                                    <span class="text-sm font-medium w-6 text-center" x-text="item.quantity"></span>
                                    <button @click="updateQty(item.product_id, item.quantity + 1)" class="w-7 h-7 rounded bg-gray-100 flex items-center justify-center text-gray-600 hover:bg-gray-200 transition"><i class="fas fa-plus text-[10px]"></i></button>
                                    <button @click="removeItem(item.product_id)" class="ml-auto text-red-400 hover:text-red-600 transition"><i class="fas fa-trash-alt text-sm"></i></button>
                                </div>
                            </div>
                            <div class="text-right">
                                <div class="font-bold text-gray-900" x-text="'<?php echo $currency; ?> ' + (parseFloat(item.unit_price) * item.quantity).toFixed(2)"></div>
                            </div>
                        </div>
                    </template>
                </div>
                <div class="flex justify-between mt-4">
                    <a href="index.php?tenant=<?php echo $tenant_id; ?>" class="text-gray-500 hover:text-gray-700 text-sm font-medium"><i class="fas fa-arrow-left mr-1"></i> Continue Shopping</a>
                    <button x-show="cartItems.length > 0" @click="step = 2" class="bg-brand hover:bg-brand-dark text-white px-6 py-2.5 rounded-lg font-semibold text-sm transition">Continue to Shipping <i class="fas fa-arrow-right ml-1"></i></button>
                </div>
            </div>

            <!-- STEP 2: SHIPPING -->
            <div x-show="step === 2" x-cloak x-transition>
                <h2 class="text-xl font-bold text-dark-800 mb-4 flex items-center gap-2"><i class="fas fa-truck text-brand"></i> Shipping Details</h2>
                <div class="bg-white rounded-xl border border-gray-100 p-6 space-y-4">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Full Name <span class="text-red-500">*</span></label>
                            <input x-model="form.customer_name" type="text" class="w-full bg-gray-50 border border-gray-200 rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-brand/50 focus:bg-white transition" placeholder="John Doe">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Phone / WhatsApp <span class="text-red-500">*</span></label>
                            <input x-model="form.customer_phone" type="tel" class="w-full bg-gray-50 border border-gray-200 rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-brand/50 focus:bg-white transition" placeholder="+254700000000">
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Email <span class="text-red-500">*</span></label>
                        <input x-model="form.customer_email" type="email" class="w-full bg-gray-50 border border-gray-200 rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-brand/50 focus:bg-white transition" placeholder="john@example.com">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Delivery Address <span class="text-red-500">*</span></label>
                        <textarea x-model="form.shipping_address" rows="3" class="w-full bg-gray-50 border border-gray-200 rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-brand/50 focus:bg-white transition" placeholder="Street, Building, City, Country"></textarea>
                    </div>

                    <!-- Shipping Zone -->
                    <div x-show="shippingZones.length > 0">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Delivery Zone</label>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <template x-for="zone in shippingZones" :key="zone.id">
                                <label class="flex items-start gap-3 p-4 border rounded-lg cursor-pointer transition" :class="form.shipping_zone_id == zone.id ? 'border-brand bg-brand/5' : 'border-gray-200 hover:border-gray-300'">
                                    <input type="radio" x-model="form.shipping_zone_id" :value="zone.id" class="mt-1 text-brand focus:ring-brand">
                                    <div>
                                        <div class="text-sm font-medium text-gray-900" x-text="zone.name"></div>
                                        <div class="text-xs text-gray-500" x-text="zone.description ?? ''"></div>
                                    </div>
                                </label>
                            </template>
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Order Notes (optional)</label>
                        <textarea x-model="form.notes" rows="2" class="w-full bg-gray-50 border border-gray-200 rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-brand/50 focus:bg-white transition" placeholder="Any special instructions..."></textarea>
                    </div>
                </div>
                <div class="flex justify-between mt-4">
                    <button @click="step = 1" class="text-gray-500 hover:text-gray-700 text-sm font-medium"><i class="fas fa-arrow-left mr-1"></i> Back to Cart</button>
                    <button @click="goToPayment()" class="bg-brand hover:bg-brand-dark text-white px-6 py-2.5 rounded-lg font-semibold text-sm transition">Continue to Payment <i class="fas fa-arrow-right ml-1"></i></button>
                </div>
            </div>

            <!-- STEP 3: PAYMENT -->
            <div x-show="step === 3" x-cloak x-transition>
                <h2 class="text-xl font-bold text-dark-800 mb-4 flex items-center gap-2"><i class="fas fa-credit-card text-brand"></i> Payment</h2>
                <div class="bg-white rounded-xl border border-gray-100 p-6 space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Payment Method</label>
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                            <label class="flex items-center gap-3 p-4 border rounded-lg cursor-pointer transition" :class="form.payment_method === 'cod' ? 'border-brand bg-brand/5' : 'border-gray-200 hover:border-gray-300'">
                                <input type="radio" x-model="form.payment_method" value="cod" class="text-brand focus:ring-brand">
                                <div class="text-sm font-medium text-gray-900">Cash on Delivery</div>
                            </label>
                            <label class="flex items-center gap-3 p-4 border rounded-lg cursor-pointer transition" :class="form.payment_method === 'mpesa' ? 'border-brand bg-brand/5' : 'border-gray-200 hover:border-gray-300'">
                                <input type="radio" x-model="form.payment_method" value="mpesa" class="text-brand focus:ring-brand">
                                <div class="text-sm font-medium text-gray-900">M-Pesa</div>
                            </label>
                            <label x-show="hasStripe" class="flex items-center gap-3 p-4 border rounded-lg cursor-pointer transition" :class="form.payment_method === 'stripe' ? 'border-brand bg-brand/5' : 'border-gray-200 hover:border-gray-300'">
                                <input type="radio" x-model="form.payment_method" value="stripe" class="text-brand focus:ring-brand">
                                <div class="text-sm font-medium text-gray-900">Card</div>
                            </label>
                        </div>
                    </div>

                    <!-- M-Pesa Phone -->
                    <div x-show="form.payment_method === 'mpesa'" x-transition>
                        <label class="block text-sm font-medium text-gray-700 mb-1">M-Pesa Phone Number</label>
                        <input x-model="form.mpesa_phone" type="tel" class="w-full bg-gray-50 border border-gray-200 rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-brand/50 focus:bg-white transition" placeholder="254700000000">
                        <p class="text-xs text-gray-500 mt-1">You will receive an STK push to complete payment.</p>
                    </div>
                </div>
                <div class="flex justify-between mt-4">
                    <button @click="step = 2" class="text-gray-500 hover:text-gray-700 text-sm font-medium"><i class="fas fa-arrow-left mr-1"></i> Back to Shipping</button>
                    <button @click="placeOrder()" :disabled="placing" class="bg-brand hover:bg-brand-dark disabled:opacity-50 text-white px-6 py-2.5 rounded-lg font-semibold text-sm transition flex items-center gap-2">
                        <i x-show="placing" class="fas fa-spinner fa-spin"></i>
                        <span x-text="placing ? 'Placing Order...' : 'Place Order'"></span>
                    </button>
                </div>
            </div>

            <!-- STEP 4: SUCCESS -->
            <div x-show="step === 4" x-cloak x-transition>
                <div class="bg-white rounded-xl border border-gray-100 p-8 text-center">
                    <div class="w-16 h-16 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-4">
                        <i class="fas fa-check text-2xl text-green-600"></i>
                    </div>
                    <h2 class="text-2xl font-bold text-dark-800 mb-2">Order Placed!</h2>
                    <p class="text-gray-500 mb-6">Thank you for your order. We will contact you shortly.</p>
                    <div class="bg-gray-50 rounded-lg p-4 max-w-sm mx-auto mb-6">
                        <div class="text-sm text-gray-500">Order Number</div>
                        <div class="text-xl font-bold text-dark-800" x-text="orderNumber"></div>
                    </div>
                    <?php if ($whatsapp): ?>
                    <a :href="'https://wa.me/<?php echo preg_replace('/[^0-9]/', '', $whatsapp); ?>?text=Hi, I just placed order ' + orderNumber" target="_blank" class="inline-flex items-center gap-2 bg-green-500 hover:bg-green-600 text-white px-6 py-3 rounded-lg font-semibold text-sm transition mb-4">
                        <i class="fab fa-whatsapp"></i> Chat on WhatsApp
                    </a>
                    <?php endif; ?>
                    <div>
                        <a href="index.php?tenant=<?php echo $tenant_id; ?>" class="text-brand font-semibold text-sm hover:underline">Continue Shopping</a>
                    </div>
                </div>
            </div>

        </div>

        <!-- RIGHT: Order Summary -->
        <div class="lg:col-span-1">
            <div class="bg-white rounded-xl border border-gray-100 p-6 sticky top-24">
                <h3 class="font-bold text-dark-800 mb-4">Order Summary</h3>
                <div class="space-y-3 mb-4 max-h-64 overflow-y-auto">
                    <template x-for="item in cartItems" :key="item.id">
                        <div class="flex gap-3">
                            <img :src="item.image_url || '/assets/no-image.png'" class="w-12 h-12 object-contain bg-gray-50 rounded" @error="$event.target.src='/assets/no-image.png'">
                            <div class="flex-1 min-w-0">
                                <div class="text-xs font-medium text-gray-900 truncate" x-text="item.product_name"></div>
                                <div class="text-xs text-gray-500" x-text="'<?php echo $currency; ?> ' + parseFloat(item.unit_price).toFixed(2) + ' x ' + item.quantity"></div>
                            </div>
                            <div class="text-xs font-bold text-gray-900" x-text="'<?php echo $currency; ?> ' + (parseFloat(item.unit_price) * item.quantity).toFixed(2)"></div>
                        </div>
                    </template>
                </div>
                <div class="border-t pt-4 space-y-2 text-sm">
                    <div class="flex justify-between"><span class="text-gray-500">Subtotal</span><span class="font-medium" x-text="'<?php echo $currency; ?> ' + cartSubtotal.toFixed(2)"></span></div>
                    <div class="flex justify-between"><span class="text-gray-500">Shipping</span><span class="font-medium" x-text="shippingCost > 0 ? '<?php echo $currency; ?> ' + shippingCost.toFixed(2) : 'Free'"></span></div>
                    <div class="flex justify-between"><span class="text-gray-500">Discount</span><span class="font-medium text-green-600" x-text="'-' + '<?php echo $currency; ?> ' + discount.toFixed(2)"></span></div>
                    <div class="flex justify-between text-lg font-bold border-t pt-2 mt-2"><span>Total</span><span x-text="'<?php echo $currency; ?> ' + total.toFixed(2)"></span></div>
                </div>
            </div>
        </div>

    </div>
</main>

<!-- FOOTER -->
<footer class="bg-dark-800 text-white mt-16">
    <div class="max-w-7xl mx-auto px-4 py-8 text-center text-sm text-gray-500">
        &copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars($store_name); ?>. All rights reserved.
    </div>
</footer>

<script>
function checkoutApp() {
    return {
        step: 1,
        tenantId: <?php echo $tenant_id; ?>,
        cartItems: [],
        cartSubtotal: 0,
        discount: 0,
        shippingCost: 0,
        total: 0,
        shippingZones: [],
        hasStripe: false,
        placing: false,
        orderNumber: '',
        form: {
            customer_name: '',
            customer_phone: '',
            customer_email: '',
            shipping_address: '',
            shipping_zone_id: '',
            payment_method: 'cod',
            mpesa_phone: '',
            notes: ''
        },

        async initCheckout() {
            await this.loadCart();
            await this.loadSummary();
        },

        async loadCart() {
            try {
                const res = await fetch(`api/v1/cart.php?tenant=${this.tenantId}`);
                const data = await res.json();
                if (data.success && data.cart) {
                    this.cartItems = data.cart.items || [];
                    this.cartSubtotal = parseFloat(data.cart.subtotal || 0);
                    this.discount = parseFloat(data.cart.coupon_discount || 0);
                    this.calculateTotal();
                }
            } catch (e) { console.error('Cart load error', e); }
        },

        async loadSummary() {
            try {
                const res = await fetch(`api/v1/checkout.php?tenant=${this.tenantId}`);
                const data = await res.json();
                if (data.success) {
                    this.shippingZones = data.shipping_zones || [];
                    if (this.shippingZones.length > 0) {
                        this.form.shipping_zone_id = this.shippingZones[0].id;
                    }
                }
            } catch (e) { console.error('Summary error', e); }
        },

        calculateTotal() {
            this.total = Math.max(0, this.cartSubtotal - this.discount + this.shippingCost);
        },

        async updateQty(productId, qty) {
            if (qty < 1) { this.removeItem(productId); return; }
            try {
                const res = await fetch(`api/v1/cart.php?tenant=${this.tenantId}`, {
                    method: 'PATCH',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ product_id: productId, quantity: qty })
                });
                const data = await res.json();
                if (data.success) { await this.loadCart(); this.calculateTotal(); }
            } catch (e) {}
        },

        async removeItem(productId) {
            try {
                const res = await fetch(`api/v1/cart.php?tenant=${this.tenantId}`, {
                    method: 'DELETE',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ product_id: productId })
                });
                const data = await res.json();
                if (data.success) { await this.loadCart(); this.calculateTotal(); }
            } catch (e) {}
        },

        goToPayment() {
            if (!this.form.customer_name || !this.form.customer_phone || !this.form.customer_email || !this.form.shipping_address) {
                alert('Please fill in all required fields');
                return;
            }
            this.step = 3;
        },

        async placeOrder() {
            this.placing = true;
            try {
                const payload = { ...this.form, action: 'place-order' };
                const res = await fetch(`api/v1/checkout.php?tenant=${this.tenantId}`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                });
                const data = await res.json();
                if (data.success) {
                    this.orderNumber = data.order_number;
                    this.step = 4;
                } else {
                    alert(data.error || 'Failed to place order');
                }
            } catch (e) {
                alert('Network error. Please try again.');
            } finally {
                this.placing = false;
            }
        }
    }
}
</script>

</body>
</html>
