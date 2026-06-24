<?php
/**
 * Product Detail Page  Modern Tailwind + Alpine.js
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
$product_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if (!$tenant_id || !$product_id) {
    http_response_code(404);
    echo '<h1>Product Not Found</h1>';
    exit;
}

$settings = [];
try {
    $stmt = $pdo->prepare("SELECT setting_key, setting_value FROM storefront_settings WHERE tenant_id = ?");
    $stmt->execute([$tenant_id]);
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) $settings[$row['setting_key']] = $row['setting_value'];
} catch (Exception $e) {}

$store_name = $settings['site_title'] ?? 'Jakababa';
$currency = $settings['currency'] ?? 'KES';
$primary_color = $settings['primary_color'] ?? '#f68b1e';
$whatsapp = $settings['whatsapp_number'] ?? '';

$product = null;
try {
    $stmt = $pdo->prepare("
        SELECT p.*,
               (SELECT COALESCE(SUM(stock), 0) FROM inventory WHERE product_id = p.id AND tenant_id = p.tenant_id) as stock_quantity
        FROM products p
        WHERE p.id = ? AND p.tenant_id = ? AND p.active = 1 AND (p.deleted_at IS NULL OR p.deleted_at = '0000-00-00 00:00:00')
        LIMIT 1
    ");
    $stmt->execute([$product_id, $tenant_id]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

if (!$product) {
    http_response_code(404);
    echo '<h1>Product Not Found</h1><p>This product is no longer available.</p>';
    exit;
}

$price = (float) $product['price'];
$salePrice = (!empty($product['selling_price']) && (float) $product['selling_price'] > 0 && (float) $product['selling_price'] < $price) ? (float) $product['selling_price'] : $price;
$discount = $salePrice < $price ? round((1 - $salePrice / $price) * 100) : 0;
$is_out = (int) ($product['stock_quantity'] ?? 0) <= 0;
$is_low = (int) ($product['stock_quantity'] ?? 0) <= 5 && !$is_out;

// Related products
$related = [];
try {
    $stmt = $pdo->prepare("
        SELECT p.id, p.name, p.price, p.selling_price, p.image,
               (SELECT COALESCE(SUM(stock), 0) FROM inventory WHERE product_id = p.id AND tenant_id = p.tenant_id) as stock_quantity
        FROM products p
        WHERE p.category_id = ? AND p.id != ? AND p.tenant_id = ? AND p.active = 1 AND (p.deleted_at IS NULL OR p.deleted_at = '0000-00-00 00:00:00')
        ORDER BY RAND()
        LIMIT 4
    ");
    $stmt->execute([$product['category_id'] ?? 0, $product_id, $tenant_id]);
    $related = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

function fmtPrice($p, $c) { return $c . ' ' . number_format((float)$p, 2); }
?>
<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($product['name']); ?>  <?php echo htmlspecialchars($store_name); ?></title>
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
                        dark: { 900: '#0f0f1a', 800: '#1a1a2e', 700: '#252542', 600: '#323255' }
                    },
                    fontFamily: { sans: ['Inter', 'system-ui', 'sans-serif'] }
                }
            }
        }
    </script>
    <style>[x-cloak] { display: none !important; }</style>
</head>
<body class="bg-gray-50 text-gray-900 font-sans antialiased" x-data="productApp()" x-init="initCart()">

<!-- TOP BAR -->
<div class="bg-dark-900 text-white text-xs py-2">
    <div class="max-w-7xl mx-auto px-4 flex justify-between items-center">
        <span>Welcome to <?php echo htmlspecialchars($store_name); ?></span>
        <div class="flex gap-4">
            <?php if ($whatsapp): ?><a href="https://wa.me/<?php echo preg_replace('/[^0-9]/', '', $whatsapp); ?>" class="hover:text-brand transition"><i class="fab fa-whatsapp"></i> Help</a><?php endif; ?>
            <a href="track.php?tenant=<?php echo $tenant_id; ?>" class="hover:text-brand transition">Track Order</a>
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
        <div class="flex-1 max-w-2xl relative" x-data="{ open: false, results: [], query: '' }" @click.away="open = false">
            <form @submit.prevent="window.location.href='search.php?tenant=<?php echo $tenant_id; ?>&q='+encodeURIComponent(query)" class="flex">
                <input x-model="query" @input.debounce.300ms="if(query.length>2) fetch('api/v1/products.php?tenant=<?php echo $tenant_id; ?>&q='+query).then(r=>r.json()).then(d=>{results=d.data||[];open=true})" type="text" placeholder="Search products..." class="flex-1 bg-gray-100 border border-gray-200 rounded-l-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-brand/50 focus:bg-white transition">
                <button type="submit" class="bg-brand hover:bg-brand-dark text-white px-6 rounded-r-lg font-semibold text-sm transition"><i class="fas fa-search"></i></button>
            </form>
            <div x-show="open && results.length" x-transition class="absolute top-full left-0 right-0 bg-white border border-gray-200 rounded-lg shadow-xl mt-1 overflow-hidden z-50">
                <template x-for="p in results.slice(0,6)" :key="p.id">
                    <a :href="'product.php?tenant=<?php echo $tenant_id; ?>&id='+p.id" class="flex items-center gap-3 px-4 py-3 hover:bg-gray-50 border-b border-gray-100 last:border-0">
                        <img :src="p.image || '/assets/no-image.png'" class="w-10 h-10 object-contain bg-gray-100 rounded" @error="$event.target.src='/assets/no-image.png'">
                        <div class="flex-1 min-w-0">
                            <div class="text-sm font-medium text-gray-900 truncate" x-text="p.name"></div>
                            <div class="text-xs text-brand font-semibold" x-text="'<?php echo $currency; ?> ' + parseFloat(p.sale_price || p.price).toFixed(2)"></div>
                        </div>
                    </a>
                </template>
            </div>
        </div>
        <div class="flex items-center gap-3 shrink-0">
            <button @click="cartOpen = true" class="relative p-2 text-gray-600 hover:text-brand transition">
                <i class="fas fa-shopping-cart text-lg"></i>
                <span x-show="cartCount > 0" x-text="cartCount" class="absolute -top-1 -right-1 bg-red-600 text-white text-[10px] font-bold w-5 h-5 rounded-full flex items-center justify-center"></span>
            </button>
        </div>
    </div>
</header>

<main class="max-w-7xl mx-auto px-4 py-8">
    <!-- Breadcrumb -->
    <nav class="flex items-center gap-2 text-sm text-gray-500 mb-6">
        <a href="index.php?tenant=<?php echo $tenant_id; ?>" class="hover:text-brand transition">Home</a>
        <i class="fas fa-chevron-right text-[10px]"></i>
        <span class="text-gray-900 font-medium"><?php echo htmlspecialchars($product['name']); ?></span>
    </nav>

    <!-- Product Detail -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-8 mb-12">
        <!-- Image -->
        <div class="bg-white rounded-xl border border-gray-100 p-6 flex items-center justify-center aspect-square">
            <?php

// Branch filter for multi-tenant isolation
$current_branch_id = get_current_branch_id();
            $imgUrl = '';
            if (!empty($product['image'])) {
                $rel = ltrim($product['image'], '/');
                if (strpos($rel, 'public/') === 0) {
                    $rel = substr($rel, 7);
                }
                $full = PUBLIC_PATH . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel);
                if (file_exists($full)) {
                    $imgUrl = base_url($rel) . '?v=' . (@filemtime($full) ?: time());
                }
            }
            ?>
            <?php if ($imgUrl): ?>
            <img src="<?php echo htmlspecialchars($imgUrl); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>" class="max-w-full max-h-full object-contain" onerror="this.style.display='none'; this.parentNode.innerHTML='<i class=\'fas fa-image text-6xl text-gray-300\'></i>';">
            <?php else: ?><i class="fas fa-image text-6xl text-gray-300"></i><?php endif; ?>
        </div>

        <!-- Info -->
        <div class="space-y-5">
            <div>
                <h1 class="text-2xl md:text-3xl font-bold text-dark-800 mb-2"><?php echo htmlspecialchars($product['name']); ?></h1>
                <div class="flex items-baseline gap-3">
                    <span class="text-3xl font-bold text-brand"><?php echo fmtPrice($salePrice, $currency); ?></span>
                    <?php if ($discount > 0): ?><span class="text-lg text-gray-400 line-through"><?php echo fmtPrice($price, $currency); ?></span><span class="bg-red-100 text-red-600 text-xs font-bold px-2 py-1 rounded">-<?php echo $discount; ?>%</span><?php endif; ?>
                </div>
            </div>

            <?php if ($is_low): ?><div class="inline-flex items-center gap-1 text-amber-600 bg-amber-50 px-3 py-1.5 rounded-lg text-sm font-medium"><i class="fas fa-exclamation-circle"></i> Low Stock: <?php echo (int) $product['stock_quantity']; ?> left</div><?php endif; ?>
            <?php if ($is_out): ?><div class="inline-flex items-center gap-1 text-red-600 bg-red-50 px-3 py-1.5 rounded-lg text-sm font-medium"><i class="fas fa-times-circle"></i> Out of Stock</div><?php endif; ?>

            <p class="text-gray-600 leading-relaxed"><?php echo nl2br(htmlspecialchars($product['description'] ?? '')); ?></p>

            <!-- Quantity + Add to Cart -->
            <div class="flex items-center gap-4">
                <div class="flex items-center border border-gray-200 rounded-lg overflow-hidden">
                    <button @click="if(qty>1) qty--" class="w-10 h-10 flex items-center justify-center text-gray-500 hover:bg-gray-50 transition"><i class="fas fa-minus text-xs"></i></button>
                    <span x-text="qty" class="w-10 text-center text-sm font-semibold"></span>
                    <button @click="if(qty < <?php echo (int) ($product['stock_quantity'] ?? 99); ?>) qty++" class="w-10 h-10 flex items-center justify-center text-gray-500 hover:bg-gray-50 transition"><i class="fas fa-plus text-xs"></i></button>
                </div>
                <button <?php echo $is_out ? 'disabled' : ''; ?> @click="<?php echo $is_out ? '' : 'addToCart('.$product_id.', \''.addslashes($product['name']).'\', '.$salePrice.')'; ?>" class="flex-1 <?php echo $is_out ? 'bg-gray-200 text-gray-400 cursor-not-allowed' : 'bg-brand hover:bg-brand-dark text-white'; ?> font-bold py-3 rounded-xl transition flex items-center justify-center gap-2">
                    <i class="fas fa-shopping-cart"></i> <?php echo $is_out ? 'Out of Stock' : 'Add to Cart'; ?>
                </button>
            </div>

            <!-- Trust badges -->
            <div class="grid grid-cols-3 gap-3 pt-4 border-t border-gray-100">
                <div class="text-center"><i class="fas fa-check-circle text-green-500 text-xl mb-1"></i><div class="text-xs text-gray-600 font-medium">In Stock</div></div>
                <div class="text-center"><i class="fas fa-truck text-blue-500 text-xl mb-1"></i><div class="text-xs text-gray-600 font-medium">Fast Delivery</div></div>
                <div class="text-center"><i class="fas fa-undo text-amber-500 text-xl mb-1"></i><div class="text-xs text-gray-600 font-medium">Easy Returns</div></div>
            </div>
        </div>
    </div>

    <!-- Related Products -->
    <?php if (!empty($related)): ?>
    <h2 class="text-xl font-bold text-dark-800 mb-4 flex items-center gap-2"><i class="fas fa-heart text-brand"></i> You May Also Like</h2>
    <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-4">
        <?php foreach ($related as $rp):
            $rpPrice = (float) $rp['price'];
            $rpSale = (!empty($rp['selling_price']) && (float) $rp['selling_price'] > 0 && (float) $rp['selling_price'] < $rpPrice) ? (float) $rp['selling_price'] : $rpPrice;
            $rpDisc = $rpSale < $rpPrice ? round((1 - $rpSale / $rpPrice) * 100) : 0;
            $rpOut = (int) ($rp['stock_quantity'] ?? 0) <= 0;
        ?>
        <div class="bg-white rounded-xl border border-gray-100 overflow-hidden hover:shadow-lg hover:border-brand/20 transition-all duration-300 group">
            <a href="product.php?tenant=<?php echo $tenant_id; ?>&id=<?php echo (int) $rp['id']; ?>" class="block relative">
                <div class="aspect-square bg-gray-50 flex items-center justify-center p-4">
                    <?php if (!empty($rp['image'])): ?><img src="<?php echo htmlspecialchars($rp['image']); ?>" alt="<?php echo htmlspecialchars($rp['name']); ?>" class="max-w-full max-h-full object-contain group-hover:scale-105 transition" loading="lazy"><?php else: ?><i class="fas fa-image text-gray-300 text-4xl"></i><?php endif; ?>
                </div>
                <?php if ($rpDisc > 0): ?><span class="absolute top-2 right-2 bg-red-500 text-white text-[10px] font-bold px-2 py-1 rounded">-<?php echo $rpDisc; ?>%</span><?php endif; ?>
                <?php if ($rpOut): ?><span class="absolute top-2 left-2 bg-gray-800/80 text-white text-[10px] font-bold px-2 py-1 rounded">Out of Stock</span><?php endif; ?>
            </a>
            <div class="p-3">
                <a href="product.php?tenant=<?php echo $tenant_id; ?>&id=<?php echo (int) $rp['id']; ?>" class="block text-sm font-medium text-gray-900 line-clamp-2 hover:text-brand transition mb-1 min-h-[2.5rem]"><?php echo htmlspecialchars($rp['name']); ?></a>
                <div class="flex items-baseline gap-2">
                    <span class="text-brand font-bold"><?php echo fmtPrice($rpSale, $currency); ?></span>
                    <?php if ($rpDisc > 0): ?><span class="text-xs text-gray-400 line-through"><?php echo fmtPrice($rpPrice, $currency); ?></span><?php endif; ?>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</main>

<!-- CART DRAWER -->
<div x-cloak x-show="cartOpen" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" x-transition:leave="transition ease-in duration-200" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0" class="fixed inset-0 bg-black/50 z-[100]" @click="cartOpen = false"></div>
<div x-cloak x-show="cartOpen" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="translate-x-full" x-transition:enter-end="translate-x-0" x-transition:leave="transition ease-in duration-200" x-transition:leave-start="translate-x-0" x-transition:leave-end="translate-x-full" class="fixed right-0 top-0 h-full w-full max-w-md bg-white shadow-2xl z-[101] flex flex-col">
    <div class="flex items-center justify-between p-4 border-b">
        <h3 class="font-bold text-lg flex items-center gap-2"><i class="fas fa-shopping-cart text-brand"></i> Your Cart (<span x-text="cartCount"></span>)</h3>
        <button @click="cartOpen = false" class="w-8 h-8 flex items-center justify-center rounded-full hover:bg-gray-100 transition"><i class="fas fa-times text-gray-500"></i></button>
    </div>
    <div class="flex-1 overflow-y-auto p-4 space-y-4">
        <template x-if="cartItems.length === 0">
            <div class="text-center py-12"><i class="fas fa-shopping-basket text-4xl text-gray-300 mb-3"></i><p class="text-gray-500">Your cart is empty</p></div>
        </template>
        <template x-for="item in cartItems" :key="item.id">
            <div class="flex gap-3 p-3 bg-gray-50 rounded-lg">
                <img :src="item.image_url || '/assets/no-image.png'" class="w-16 h-16 object-contain bg-white rounded" @error="$event.target.src='/assets/no-image.png'">
                <div class="flex-1 min-w-0">
                    <div class="text-sm font-medium text-gray-900 truncate" x-text="item.product_name"></div>
                    <div class="text-brand font-bold text-sm" x-text="'<?php echo $currency; ?> ' + parseFloat(item.unit_price).toFixed(2)"></div>
                    <div class="flex items-center gap-2 mt-1">
                        <button @click="updateQty(item.product_id, item.quantity - 1)" class="w-6 h-6 rounded bg-white border border-gray-200 flex items-center justify-center text-gray-500 hover:border-brand transition"><i class="fas fa-minus text-[10px]"></i></button>
                        <span class="text-sm font-medium w-6 text-center" x-text="item.quantity"></span>
                        <button @click="updateQty(item.product_id, item.quantity + 1)" class="w-6 h-6 rounded bg-white border border-gray-200 flex items-center justify-center text-gray-500 hover:border-brand transition"><i class="fas fa-plus text-[10px]"></i></button>
                        <button @click="removeItem(item.product_id)" class="ml-auto text-red-400 hover:text-red-600 transition"><i class="fas fa-trash-alt text-sm"></i></button>
                    </div>
                </div>
            </div>
        </template>
    </div>
    <div class="border-t p-4 space-y-3 bg-gray-50">
        <div class="flex justify-between text-sm"><span class="text-gray-500">Subtotal</span><span class="font-semibold" x-text="'<?php echo $currency; ?> ' + cartTotal.toFixed(2)"></span></div>
        <button x-show="cartItems.length > 0" @click="window.location.href='checkout.php?tenant=<?php echo $tenant_id; ?>'" class="w-full bg-brand hover:bg-brand-dark text-white font-bold py-3 rounded-xl transition shadow-lg shadow-brand/20">Proceed to Checkout</button>
    </div>
</div>

<!-- FOOTER -->
<footer class="bg-dark-800 text-white mt-16 pb-20 md:pb-0">
    <div class="max-w-7xl mx-auto px-4 py-12 grid grid-cols-1 md:grid-cols-4 gap-8">
        <div><h4 class="font-bold text-lg mb-3"><?php echo htmlspecialchars($store_name); ?></h4><p class="text-sm text-gray-400">Your trusted online shopping destination.</p></div>
        <div><h4 class="font-bold mb-3">Quick Links</h4><ul class="space-y-2 text-sm text-gray-400"><li><a href="index.php?tenant=<?php echo $tenant_id; ?>" class="hover:text-brand transition">Home</a></li><li><a href="search.php?tenant=<?php echo $tenant_id; ?>" class="hover:text-brand transition">All Products</a></li></ul></div>
        <div><h4 class="font-bold mb-3">Customer Service</h4><ul class="space-y-2 text-sm text-gray-400"><li><a href="#" class="hover:text-brand transition">Help Center</a></li><li><a href="#" class="hover:text-brand transition">Returns Policy</a></li></ul></div>
        <div><h4 class="font-bold mb-3">Contact Us</h4><?php if ($whatsapp): ?><a href="https://wa.me/<?php echo preg_replace('/[^0-9]/', '', $whatsapp); ?>" class="inline-flex items-center gap-2 bg-green-500 hover:bg-green-600 text-white px-4 py-2 rounded-lg text-sm font-medium transition"><i class="fab fa-whatsapp"></i> WhatsApp</a><?php endif; ?></div>
    </div>
    <div class="border-t border-dark-600 py-4 text-center text-sm text-gray-500">&copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars($store_name); ?>. Powered by Jakababa Smart System.</div>
</footer>

<script>
function productApp() {
    return {
        cartOpen: false,
        cartItems: [],
        cartCount: 0,
        cartTotal: 0,
        tenantId: <?php echo $tenant_id; ?>,
        qty: 1,
        initCart() { this.loadCart(); },
        async loadCart() {
            try {
                const res = await fetch(`api/v1/cart.php?tenant=${this.tenantId}`);
                const data = await res.json();
                if (data.success && data.cart) {
                    this.cartItems = data.cart.items || [];
                    this.cartCount = this.cartItems.reduce((sum, i) => sum + parseInt(i.quantity), 0);
                    this.cartTotal = parseFloat(data.cart.subtotal || 0);
                }
            } catch (e) {}
        },
        async addToCart(productId, name, price) {
            try {
                const res = await fetch(`api/v1/cart.php?tenant=${this.tenantId}`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'add', product_id: productId, quantity: this.qty })
                });
                const data = await res.json();
                if (data.success) { this.loadCart(); this.cartOpen = true; }
                else alert(data.error || 'Could not add to cart');
            } catch (e) { alert('Network error'); }
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
                if (data.success) this.loadCart();
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
                if (data.success) this.loadCart();
            } catch (e) {}
        }
    }
}
</script>
</body>
</html>
