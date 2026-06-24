<?php
/**
 * Search & Category Results — Modern Tailwind + Alpine.js
 * Handles both text search and category browsing
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

$search_q = isset($_GET['q']) ? trim($_GET['q']) : '';
$cat_filter = isset($_GET['cat']) ? (int) $_GET['cat'] : 0;
$deals_only = isset($_GET['deals']) ? 1 : 0;

// Fetch categories for sidebar
$categories = [];
try {
    $stmt = $pdo->prepare("SELECT id, name, icon, (SELECT COUNT(*) FROM products WHERE category_id = c.id AND tenant_id = c.tenant_id AND active = 1) as product_count FROM categories c WHERE tenant_id = ? AND status = 'active' AND branch_id = $current_branch_id ORDER BY sort_order ASC, name ASC LIMIT 20");
    $stmt->execute([$tenant_id]);
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

$active_cat_name = 'All Products';
if ($cat_filter > 0) {
    foreach ($categories as $c) {
        if ((int)$c['id'] === $cat_filter) { $active_cat_name = $c['name']; break; }
    }
} elseif ($deals_only) {
    $active_cat_name = 'Flash Deals';
} elseif ($search_q !== '') {
    $active_cat_name = 'Search: ' . htmlspecialchars($search_q);
}

function formatPrice($price, $currency) {
    return $currency . ' ' . number_format((float)$price, 2);
}
function calcDiscount($price, $salePrice) {
    if (!$salePrice || $salePrice >= $price) return 0;
    return round((1 - $salePrice / $price) * 100);
}
function getCategoryIcon($cat) {
    if (!empty($cat['icon'])) return 'fas ' . htmlspecialchars($cat['icon']);
    $icons = ['fa-tshirt','fa-baby','fa-mobile-alt','fa-laptop','fa-couch','fa-utensils','fa-book','fa-car','fa-heart','fa-star','fa-gem','fa-camera'];
    return 'fas ' . $icons[$cat['id'] % count($icons)];
}
?>
<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($active_cat_name); ?> — <?php echo htmlspecialchars($store_name); ?></title>
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
<body class="bg-gray-50 text-gray-900 font-sans antialiased" x-data="searchApp()" x-init="initSearch()">

<!-- TOP BAR -->
<div class="bg-dark-900 text-white text-xs py-2">
    <div class="max-w-7xl mx-auto px-4 flex justify-between items-center">
        <span>Welcome to <?php echo htmlspecialchars($store_name); ?></span>
        <div class="flex gap-4">
            <?php if ($whatsapp): ?>
            <a href="https://wa.me/<?php echo preg_replace('/[^0-9]/', '', $whatsapp); ?>" class="hover:text-brand transition"><i class="fab fa-whatsapp"></i> Help</a>
            <?php endif; ?>
            <a href="track.php?tenant=<?php echo $tenant_id; ?>" class="hover:text-brand transition">Track Order</a>
            <a href="account/orders.php?tenant=<?php echo $tenant_id; ?>" class="hover:text-brand transition">My Orders</a>
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
        <div class="flex-1 max-w-2xl relative" x-data="{ open: false, results: [], query: '<?php echo addslashes($search_q); ?>' }" @click.away="open = false">
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
                <a :href="'search.php?tenant=<?php echo $tenant_id; ?>&q='+encodeURIComponent(query)" class="block text-center text-sm text-brand font-medium py-2 hover:bg-gray-50 border-t">See all results</a>
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

<!-- CATEGORY NAV -->
<nav class="bg-white border-b overflow-x-auto scrollbar-hide">
    <div class="max-w-7xl mx-auto px-4 flex gap-1 py-2">
        <a href="search.php?tenant=<?php echo $tenant_id; ?>" class="px-4 py-2 rounded-full text-sm font-medium <?php echo !$cat_filter && !$deals_only ? 'bg-brand text-white' : 'text-gray-700 hover:bg-gray-100'; ?> whitespace-nowrap transition">All</a>
        <?php foreach ($categories as $cat): ?>
        <a href="category.php?tenant=<?php echo $tenant_id; ?>&cat=<?php echo $cat['id']; ?>" class="px-4 py-2 rounded-full text-sm font-medium <?php echo $cat_filter == $cat['id'] ? 'bg-brand text-white' : 'text-gray-700 hover:bg-gray-100'; ?> whitespace-nowrap transition"><?php echo htmlspecialchars($cat['name']); ?></a>
        <?php endforeach; ?>
    </div>
</nav>

<main class="max-w-7xl mx-auto px-4 py-8">
    <div class="grid grid-cols-1 lg:grid-cols-4 gap-8">

        <!-- SIDEBAR FILTERS -->
        <aside class="hidden lg:block">
            <div class="bg-white rounded-xl border border-gray-100 p-5 sticky top-28">
                <h3 class="font-bold text-dark-800 mb-4">Categories</h3>
                <ul class="space-y-2 text-sm">
                    <li><a href="search.php?tenant=<?php echo $tenant_id; ?>" class="flex items-center justify-between py-1 <?php echo !$cat_filter && !$deals_only ? 'text-brand font-semibold' : 'text-gray-600 hover:text-brand'; ?> transition"><span>All Products</span></a></li>
                    <?php foreach ($categories as $cat): ?>
                    <li><a href="category.php?tenant=<?php echo $tenant_id; ?>&cat=<?php echo $cat['id']; ?>" class="flex items-center justify-between py-1 <?php echo $cat_filter == $cat['id'] ? 'text-brand font-semibold' : 'text-gray-600 hover:text-brand'; ?> transition"><span><?php echo htmlspecialchars($cat['name']); ?></span><span class="text-xs text-gray-400 bg-gray-100 px-2 py-0.5 rounded-full"><?php echo (int)$cat['product_count']; ?></span></a></li>
                    <?php endforeach; ?>
                </ul>

                <h3 class="font-bold text-dark-800 mt-6 mb-4">Filters</h3>
                <div class="space-y-4">
                    <div>
                        <label class="text-sm font-medium text-gray-700 mb-1 block">Sort By</label>
                        <select x-model="sort" @change="loadProducts()" class="w-full bg-gray-50 border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand/50">
                            <option value="newest">Newest</option>
                            <option value="price_asc">Price: Low to High</option>
                            <option value="price_desc">Price: High to Low</option>
                            <option value="name_asc">Name: A-Z</option>
                        </select>
                    </div>
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" x-model="inStock" @change="loadProducts()" class="rounded text-brand focus:ring-brand">
                        <span class="text-sm text-gray-700">In Stock Only</span>
                    </label>
                </div>
            </div>
        </aside>

        <!-- PRODUCTS GRID -->
        <div class="lg:col-span-3">
            <div class="flex items-center justify-between mb-6">
                <h1 class="text-xl font-bold text-dark-800"><?php echo htmlspecialchars($active_cat_name); ?></h1>
                <span class="text-sm text-gray-500" x-text="meta.total + ' results'"></span>
            </div>

            <!-- Loading State -->
            <div x-show="loading" class="flex justify-center py-12">
                <i class="fas fa-spinner fa-spin text-2xl text-brand"></i>
            </div>

            <!-- Empty State -->
            <div x-show="!loading && products.length === 0" x-cloak class="text-center py-16 bg-white rounded-xl border border-gray-100">
                <i class="fas fa-box-open text-5xl text-gray-300 mb-4"></i>
                <h3 class="text-lg font-bold text-dark-800 mb-2">No products found</h3>
                <p class="text-gray-500 text-sm mb-6">Try adjusting your filters or search term.</p>
                <a href="search.php?tenant=<?php echo $tenant_id; ?>" class="inline-block bg-brand hover:bg-brand-dark text-white px-6 py-2.5 rounded-lg font-semibold text-sm transition">View All Products</a>
            </div>

            <!-- Grid -->
            <div x-show="!loading && products.length > 0" x-cloak class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-3 lg:grid-cols-4 gap-4">
                <template x-for="p in products" :key="p.id">
                    <div class="bg-white rounded-xl border border-gray-100 overflow-hidden hover:shadow-lg hover:border-brand/20 transition-all duration-300 group">
                        <a :href="'product.php?tenant=<?php echo $tenant_id; ?>&id=' + p.id" class="block relative">
                            <div class="aspect-square bg-gray-50 flex items-center justify-center p-4">
                                <img :src="p.image || '/assets/no-image.png'" :alt="p.name" class="max-w-full max-h-full object-contain group-hover:scale-105 transition" loading="lazy" @error="$event.target.src='/assets/no-image.png'">
                            </div>
                            <span x-show="p.discount_pct > 0" class="absolute top-2 right-2 bg-red-500 text-white text-[10px] font-bold px-2 py-1 rounded" x-text="'-' + p.discount_pct + '%'"></span>
                            <span x-show="p.is_out_of_stock" class="absolute top-2 left-2 bg-gray-800/80 text-white text-[10px] font-bold px-2 py-1 rounded">Out of Stock</span>
                        </a>
                        <div class="p-3">
                            <a :href="'product.php?tenant=<?php echo $tenant_id; ?>&id=' + p.id" class="block text-sm font-medium text-gray-900 line-clamp-2 hover:text-brand transition mb-1 min-h-[2.5rem]" x-text="p.name"></a>
                            <div class="flex items-baseline gap-2">
                                <span class="text-brand font-bold" x-text="'<?php echo $currency; ?> ' + parseFloat(p.sale_price).toFixed(2)"></span>
                                <span x-show="p.discount_pct > 0" class="text-xs text-gray-400 line-through" x-text="'<?php echo $currency; ?> ' + parseFloat(p.price).toFixed(2)"></span>
                            </div>
                            <span x-show="p.is_low_stock" class="text-[10px] text-red-500 font-medium">Only <span x-text="p.stock_quantity"></span> left</span>
                            <button :disabled="p.is_out_of_stock" @click="p.is_out_of_stock ? null : addToCart(p.id, p.name, p.sale_price)" class="w-full mt-2 text-sm font-semibold py-2 rounded-lg transition flex items-center justify-center gap-2" :class="p.is_out_of_stock ? 'bg-gray-200 text-gray-400 cursor-not-allowed' : 'bg-brand hover:bg-brand-dark text-white'">
                                <i class="fas fa-plus text-xs"></i> <span x-text="p.is_out_of_stock ? 'Out of Stock' : 'Add to Cart'"></span>
                            </button>
                        </div>
                    </div>
                </template>
            </div>

            <!-- Pagination -->
            <div x-show="!loading && meta.total_pages > 1" x-cloak class="flex justify-center mt-8 gap-2">
                <button @click="if(page>1){page--;loadProducts();}" :disabled="page <= 1" class="px-3 py-2 rounded-lg border text-sm font-medium transition disabled:opacity-40" :class="page > 1 ? 'hover:bg-gray-50' : ''">Prev</button>
                <template x-for="p in Array.from({length: meta.total_pages}, (_,i) => i+1)" :key="p">
                    <button @click="page=p;loadProducts();" class="w-9 h-9 rounded-lg text-sm font-medium transition" :class="page === p ? 'bg-brand text-white' : 'border hover:bg-gray-50'" x-text="p"></button>
                </template>
                <button @click="if(page < meta.total_pages){page++;loadProducts();}" :disabled="page >= meta.total_pages" class="px-3 py-2 rounded-lg border text-sm font-medium transition disabled:opacity-40" :class="page < meta.total_pages ? 'hover:bg-gray-50' : ''">Next</button>
            </div>
        </div>
    </div>
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
        <div>
            <h4 class="font-bold text-lg mb-3"><?php echo htmlspecialchars($store_name); ?></h4>
            <p class="text-sm text-gray-400">Your trusted online shopping destination.</p>
        </div>
        <div>
            <h4 class="font-bold mb-3">Quick Links</h4>
            <ul class="space-y-2 text-sm text-gray-400">
                <li><a href="index.php?tenant=<?php echo $tenant_id; ?>" class="hover:text-brand transition">Home</a></li>
                <li><a href="search.php?tenant=<?php echo $tenant_id; ?>" class="hover:text-brand transition">All Products</a></li>
                <li><a href="track.php?tenant=<?php echo $tenant_id; ?>" class="hover:text-brand transition">Track Order</a></li>
            </ul>
        </div>
        <div>
            <h4 class="font-bold mb-3">Customer Service</h4>
            <ul class="space-y-2 text-sm text-gray-400">
                <li><a href="#" class="hover:text-brand transition">Help Center</a></li>
                <li><a href="#" class="hover:text-brand transition">Returns Policy</a></li>
            </ul>
        </div>
        <div>
            <h4 class="font-bold mb-3">Contact Us</h4>
            <?php if ($whatsapp): ?>
            <a href="https://wa.me/<?php echo preg_replace('/[^0-9]/', '', $whatsapp); ?>" class="inline-flex items-center gap-2 bg-green-500 hover:bg-green-600 text-white px-4 py-2 rounded-lg text-sm font-medium transition"><i class="fab fa-whatsapp"></i> WhatsApp</a>
            <?php endif; ?>
        </div>
    </div>
    <div class="border-t border-dark-600 py-4 text-center text-sm text-gray-500">&copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars($store_name); ?>. Powered by Jakababa Smart System.</div>
</footer>

<script>
function searchApp() {
    return {
        cartOpen: false,
        cartItems: [],
        cartCount: 0,
        cartTotal: 0,
        tenantId: <?php echo $tenant_id; ?>,
        products: [],
        loading: true,
        page: 1,
        sort: 'newest',
        inStock: false,
        meta: { total: 0, total_pages: 0 },

        initSearch() {
            this.loadCart();
            this.loadProducts();
        },

        async loadProducts() {
            this.loading = true;
            try {
                const params = new URLSearchParams({ tenant: this.tenantId, page: this.page, per_page: 24, sort: this.sort });
                <?php if ($cat_filter > 0): ?>params.append('category', '<?php echo $cat_filter; ?>');<?php endif; ?>
                <?php if ($search_q !== ''): ?>params.append('q', <?php echo json_encode($search_q); ?>);<?php endif; ?>
                <?php if ($deals_only): ?>params.append('min_discount', '1');<?php endif; ?>
                if (this.inStock) params.append('in_stock', '1');

                const res = await fetch('api/v1/products.php?' + params.toString());
                const data = await res.json();
                if (data.success) {
                    this.products = data.data || [];
                    this.meta = data.meta || { total: 0, total_pages: 0 };
                }
            } catch (e) { console.error(e); }
            this.loading = false;
        },

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
                    body: JSON.stringify({ action: 'add', product_id: productId, quantity: 1 })
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
