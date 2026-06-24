<?php
/**
 * Jumia-Style Online Storefront  Jakababa Smart Store
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

// Resolve tenant using pos_tenants first, then legacy tenants
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
    echo '<h1>Store Not Found</h1><p>No active store is available.</p>';
    exit;
}

// Load tenant settings (try storefront_settings first, then fallback to settings)
$settings = [];
try {
    $stmt = $pdo->prepare("SELECT setting_key, setting_value FROM storefront_settings WHERE tenant_id = ?");
    $stmt->execute([$tenant_id]);
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) $settings[$row['setting_key']] = $row['setting_value'];
} catch (Exception $e) {}
if (empty($settings)) {
    try {
        $stmt = $pdo->prepare("SELECT setting_key, setting_value FROM settings WHERE tenant_id = ?");
        $stmt->execute([$tenant_id]);
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) $settings[$row['setting_key']] = $row['setting_value'];
    } catch (Exception $e) {}
}

$store_name = $settings['site_title'] ?? $settings['company_name'] ?? 'Jakababa';
$currency = $settings['currency'] ?? 'KES';
$primary_color = $settings['primary_color'] ?? '#f68b1e';
$whatsapp = $settings['whatsapp_number'] ?? '';
$online_store_enabled = ($settings['online_store_enabled'] ?? '1') === '1';

// Fetch categories
$categories = [];
try {
    $sql = "SELECT id, name, icon, image FROM categories WHERE tenant_id = ? AND status = 'active' AND (deleted_at IS NULL OR deleted_at = '0000-00-00 00:00:00')";
    if ($current_branch_id > 0) $sql .= " AND branch_id = $current_branch_id";
    $sql .= " ORDER BY sort_order ASC, name ASC LIMIT 12";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$tenant_id]);
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

// Fetch flash deals (biggest discounts)
$flashDeals = [];
try {
    $stmt = $pdo->prepare("
        SELECT p.id, p.name, p.slug, p.price, p.selling_price, p.image, p.rating, p.review_count,
               COALESCE(SUM(i.stock), 0) as stock
        FROM products p
        LEFT JOIN inventory i ON i.product_id = p.id AND i.tenant_id = p.tenant_id
        WHERE p.tenant_id = ? AND p.active = 1
          AND p.selling_price > 0 AND p.selling_price < p.price
          AND (p.deleted_at IS NULL OR p.deleted_at = '0000-00-00 00:00:00')
        GROUP BY p.id
        ORDER BY (p.price - p.selling_price) / p.price DESC
        LIMIT 8
    ");
    $stmt->execute([$tenant_id]);
    $flashDeals = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

// Fetch top deals (any product with a discount)
$topDeals = [];
try {
    $stmt = $pdo->prepare("
        SELECT p.id, p.name, p.slug, p.price, p.selling_price, p.image, p.rating, p.review_count,
               COALESCE(SUM(i.stock), 0) as stock
        FROM products p
        LEFT JOIN inventory i ON i.product_id = p.id AND i.tenant_id = p.tenant_id
        WHERE p.tenant_id = ? AND p.active = 1
          AND (p.deleted_at IS NULL OR p.deleted_at = '0000-00-00 00:00:00')
        GROUP BY p.id
        ORDER BY (p.price - COALESCE(p.selling_price,0)) DESC
        LIMIT 12
    ");
    $stmt->execute([$tenant_id]);
    $topDeals = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

// Fetch new arrivals
$newArrivals = [];
try {
    $stmt = $pdo->prepare("
        SELECT p.id, p.name, p.slug, p.price, p.selling_price, p.image, p.rating, p.review_count,
               COALESCE(SUM(i.stock), 0) as stock
        FROM products p
        LEFT JOIN inventory i ON i.product_id = p.id AND i.tenant_id = p.tenant_id
        WHERE p.tenant_id = ? AND p.active = 1
          AND (p.deleted_at IS NULL OR p.deleted_at = '0000-00-00 00:00:00')
        GROUP BY p.id
        ORDER BY p.created_at DESC
        LIMIT 12
    ");
    $stmt->execute([$tenant_id]);
    $newArrivals = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

// Fetch banners
$banners = [];
try {
    $stmt = $pdo->prepare("
        SELECT * FROM storefront_banners
        WHERE tenant_id = ? AND is_active = 1 AND position = 'hero'
          AND (start_date IS NULL OR start_date <= CURDATE())
          AND (end_date IS NULL OR end_date >= CURDATE())
        ORDER BY display_order ASC
        LIMIT 5
    ");
    $stmt->execute([$tenant_id]);
    $banners = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

function formatPrice($price, $currency) {
    return $currency . ' ' . number_format((float)$price, 0);
}
function calcDiscount($price, $salePrice) {
    if (!$salePrice || $salePrice <= 0 || $salePrice >= $price) return 0;
    return round((1 - $salePrice / $price) * 100);
}
function getCategoryIcon($cat) {
    if (!empty($cat['icon'])) return 'fas ' . htmlspecialchars($cat['icon']);
    $icons = ['fa-mobile-alt','fa-laptop','fa-tshirt','fa-shoe-prints','fa-couch','fa-utensils','fa-book','fa-car','fa-heart','fa-star','fa-gem','fa-camera'];
    return 'fas ' . $icons[$cat['id'] % count($icons)];
}
function renderStars($rating) {
    $rating = (float) ($rating ?? 0);
    $full = floor($rating);
    $half = ($rating - $full) >= 0.5 ? 1 : 0;
    $empty = 5 - $full - $half;
    $html = '';
    for ($i = 0; $i < $full; $i++) $html .= '<i class="fas fa-star text-yellow-400 text-[10px]"></i>';
    if ($half) $html .= '<i class="fas fa-star-half-alt text-yellow-400 text-[10px]"></i>';
    for ($i = 0; $i < $empty; $i++) $html .= '<i class="far fa-star text-gray-300 text-[10px]"></i>';
    return $html;
}
function getFirstName($name) {
    return strtok(htmlspecialchars($name), ' ');
}
?>
<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($store_name); ?>  Online Shopping</title>
    <meta name="description" content="Shop the best deals at <?php echo htmlspecialchars($store_name); ?>. Fast delivery, great prices.">
    <link rel="stylesheet" href="<?php echo asset_url('css/app.css'); ?>">
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        brand: { DEFAULT: '<?php echo $primary_color; ?>', dark: '#d46a0d' },
                        jumia: { orange: '#f68b1e', red: '#e62e04', dark: '#282828', gray: '#f1f1f2' }
                    },
                    fontFamily: { sans: ['Inter', 'system-ui', 'sans-serif'] }
                }
            }
        }
    </script>
    <style>
        [x-cloak] { display: none !important; }
        .scrollbar-hide::-webkit-scrollbar { display: none; }
        .scrollbar-hide { -ms-overflow-style: none; scrollbar-width: none; }
        .line-clamp-2 { display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
        @keyframes pulse-sale { 0%,100%{transform:scale(1)} 50%{transform:scale(1.05)} }
        .sale-pulse { animation: pulse-sale 2s infinite; }
        .countdown-box { background: #fff; color: #e62e04; font-weight: 700; padding: 4px 8px; border-radius: 4px; font-size: 14px; }
    </style>
</head>
<body class="bg-jumia-gray text-gray-800 font-sans antialiased" x-data="shopApp()" x-init="initCart()">

<!-- PROMO BAR -->
<div class="bg-jumia-dark text-white text-xs py-1.5">
    <div class="max-w-7xl mx-auto px-4 flex justify-between items-center">
        <span><i class="fas fa-truck-fast mr-1 text-jumia-orange"></i> <strong>FREE DELIVERY</strong> on your first order! Use code <strong class="text-jumia-orange">WELCOME</strong></span>
        <div class="hidden sm:flex gap-4">
            <a href="track.php?tenant=<?php echo $tenant_id; ?>" class="hover:text-jumia-orange transition">Track Order</a>
            <a href="account/orders.php?tenant=<?php echo $tenant_id; ?>" class="hover:text-jumia-orange transition">My Orders</a>
            <?php if ($whatsapp): ?>
            <a href="https://wa.me/<?php echo preg_replace('/[^0-9]/', '', $whatsapp); ?>" class="hover:text-green-400 transition"><i class="fab fa-whatsapp"></i> Help</a>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- MAIN HEADER -->
<header class="bg-white shadow-sm sticky top-0 z-50">
    <div class="max-w-7xl mx-auto px-4 py-3">
        <div class="flex items-center gap-4">
            <!-- Logo -->
            <a href="index.php?tenant=<?php echo $tenant_id; ?>" class="flex items-center gap-2 shrink-0">
                <div class="w-10 h-10 bg-jumia-orange rounded-lg flex items-center justify-center text-white font-extrabold text-xl tracking-tight">J</div>
                <div class="hidden sm:block">
                    <div class="text-xl font-extrabold text-jumia-dark leading-none tracking-tight"><?php echo getFirstName($store_name); ?></div>
                    <div class="text-[10px] text-jumia-orange font-bold tracking-widest uppercase">Marketplace</div>
                </div>
            </a>

            <!-- Search -->
            <div class="flex-1 max-w-2xl relative" x-data="{ open: false, results: [], query: '' }" @click.away="open = false">
                <form @submit.prevent="window.location.href='search.php?tenant=<?php echo $tenant_id; ?>&q='+encodeURIComponent(query)" class="flex shadow-sm rounded-lg overflow-hidden border border-gray-200 focus-within:border-jumia-orange focus-within:ring-2 focus-within:ring-jumia-orange/20 transition">
                    <input x-model="query" @input.debounce.300ms="if(query.length>2) fetch('api/v1/products.php?tenant=<?php echo $tenant_id; ?>&q='+query).then(r=>r.json()).then(d=>{results=d.data||[];open=true})" type="text" placeholder="Search products, brands and categories..." class="flex-1 bg-white px-4 py-2.5 text-sm focus:outline-none">
                    <button type="submit" class="bg-jumia-orange hover:bg-brand-dark text-white px-6 font-bold text-sm transition flex items-center gap-2">
                        <i class="fas fa-search"></i><span class="hidden sm:inline">Search</span>
                    </button>
                </form>
                <!-- Search Dropdown -->
                <div x-show="open && results.length" x-transition class="absolute top-full left-0 right-0 bg-white border border-gray-200 rounded-lg shadow-xl mt-1 overflow-hidden z-50">
                    <template x-for="p in results.slice(0,6)" :key="p.id">
                        <a :href="'product.php?tenant=<?php echo $tenant_id; ?>&id='+p.id" class="flex items-center gap-3 px-4 py-3 hover:bg-gray-50 border-b border-gray-100 last:border-0">
                            <img :src="p.image || '/assets/no-image.png'" class="w-10 h-10 object-contain bg-white rounded border" @error="$event.target.src='/assets/no-image.png'">
                            <div class="flex-1 min-w-0">
                                <div class="text-sm font-medium text-gray-900 truncate" x-text="p.name"></div>
                                <div class="text-xs text-jumia-orange font-bold" x-text="'<?php echo $currency; ?> ' + parseFloat(p.sale_price || p.price).toFixed(0)"></div>
                            </div>
                        </a>
                    </template>
                    <a :href="'search.php?tenant=<?php echo $tenant_id; ?>&q='+encodeURIComponent(query)" class="block text-center text-sm text-jumia-orange font-bold py-2 hover:bg-gray-50 border-t">See all results</a>
                </div>
            </div>

            <!-- Actions -->
            <div class="flex items-center gap-1 sm:gap-3 shrink-0">
                <a href="account/orders.php?tenant=<?php echo $tenant_id; ?>" class="flex flex-col items-center p-2 text-gray-600 hover:text-jumia-orange transition">
                    <i class="far fa-user text-lg"></i><span class="text-[10px] hidden sm:block">Account</span>
                </a>
                <a href="account/wishlist.php?tenant=<?php echo $tenant_id; ?>" class="flex flex-col items-center p-2 text-gray-600 hover:text-jumia-orange transition">
                    <i class="far fa-heart text-lg"></i><span class="text-[10px] hidden sm:block">Saved</span>
                </a>
                <button @click="cartOpen = true" class="flex flex-col items-center p-2 text-gray-600 hover:text-jumia-orange transition relative">
                    <i class="fas fa-shopping-cart text-lg"></i>
                    <span class="text-[10px] hidden sm:block">Cart</span>
                    <span x-show="cartCount > 0" x-text="cartCount" class="absolute top-0 right-0 bg-jumia-red text-white text-[9px] font-bold w-4 h-4 rounded-full flex items-center justify-center"></span>
                </button>
            </div>
        </div>
    </div>
</header>

<!-- CATEGORY NAV -->
<nav class="bg-white border-b shadow-sm">
    <div class="max-w-7xl mx-auto px-4">
        <div class="flex gap-0.5 py-2 overflow-x-auto scrollbar-hide">
            <a href="index.php?tenant=<?php echo $tenant_id; ?>" class="px-3 py-1.5 rounded-md text-sm font-semibold bg-jumia-orange text-white whitespace-nowrap">All</a>
            <?php foreach ($categories as $cat): ?>
            <a href="category.php?tenant=<?php echo $tenant_id; ?>&cat=<?php echo $cat['id']; ?>" class="px-3 py-1.5 rounded-md text-sm font-medium text-gray-700 hover:bg-gray-100 whitespace-nowrap transition"><?php echo htmlspecialchars($cat['name']); ?></a>
            <?php endforeach; ?>
        </div>
    </div>
</nav>

<!-- HERO BANNER -->
<?php if (!empty($banners)): ?>
<div class="max-w-7xl mx-auto px-4 mt-4" x-data="{ current: 0 }" x-init="setInterval(() => current = (current + 1) % <?php echo count($banners); ?>, 5000)">
    <div class="relative overflow-hidden rounded-xl aspect-[16/7] md:aspect-[4/1] shadow-lg">
        <?php foreach ($banners as $i => $banner): ?>
        <div class="absolute inset-0 transition-opacity duration-700" :class="current === <?php echo $i; ?> ? 'opacity-100 z-10' : 'opacity-0 z-0'">
            <img src="<?php echo htmlspecialchars($banner['image_url']); ?>" alt="<?php echo htmlspecialchars($banner['title']); ?>" class="w-full h-full object-cover" loading="<?php echo $i === 0 ? 'eager' : 'lazy'; ?>">
            <div class="absolute inset-0 bg-gradient-to-r from-black/70 via-black/30 to-transparent flex items-center px-8 md:px-16">
                <div class="text-white max-w-xl">
                    <?php if ($banner['title']): ?><h2 class="text-2xl md:text-5xl font-extrabold mb-3 drop-shadow-lg"><?php echo htmlspecialchars($banner['title']); ?></h2><?php endif; ?>
                    <?php if ($banner['subtitle']): ?><p class="text-sm md:text-lg text-gray-100 mb-5 drop-shadow"><?php echo htmlspecialchars($banner['subtitle']); ?></p><?php endif; ?>
                    <?php if ($banner['link_url']): ?><a href="<?php echo htmlspecialchars($banner['link_url']); ?>" class="inline-block bg-jumia-orange hover:bg-brand-dark text-white px-8 py-3 rounded-lg font-bold text-sm transition shadow-lg shadow-orange-500/30"><?php echo htmlspecialchars($banner['link_text'] ?? 'Shop Now'); ?> <i class="fas fa-arrow-right ml-1"></i></a><?php endif; ?>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <div class="flex justify-center gap-2 mt-3">
        <?php foreach ($banners as $i => $b): ?>
        <button @click="current = <?php echo $i; ?>" :class="current === <?php echo $i; ?> ? 'bg-jumia-orange w-8' : 'bg-gray-300'" class="h-2 rounded-full transition-all duration-300"></button>
        <?php endforeach; ?>
    </div>
</div>
<?php else: ?>
<div class="max-w-7xl mx-auto px-4 mt-4">
    <div class="relative overflow-hidden rounded-xl bg-gradient-to-r from-jumia-dark to-gray-800 aspect-[16/7] md:aspect-[4/1] flex items-center px-8 md:px-16 shadow-lg">
        <div class="text-white max-w-xl">
            <h2 class="text-3xl md:text-5xl font-extrabold mb-3 drop-shadow-lg">Black Friday Deals</h2>
            <p class="text-base md:text-lg text-gray-200 mb-5">Up to 70% off on top products. Limited stock available!</p>
            <a href="#flash-deals" class="inline-block bg-jumia-orange hover:bg-brand-dark text-white px-8 py-3 rounded-lg font-bold text-sm transition shadow-lg">Shop Deals Now <i class="fas fa-arrow-right ml-1"></i></a>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- CATEGORY GRID -->
<div class="max-w-7xl mx-auto px-4 mt-6">
    <div class="grid grid-cols-4 sm:grid-cols-6 md:grid-cols-8 lg:grid-cols-10 gap-2">
        <?php foreach ($categories as $cat): ?>
        <a href="category.php?tenant=<?php echo $tenant_id; ?>&cat=<?php echo $cat['id']; ?>" class="group flex flex-col items-center gap-1.5 p-2 bg-white rounded-lg border border-gray-100 hover:border-jumia-orange/40 hover:shadow-md hover:-translate-y-0.5 transition-all duration-200">
            <div class="w-10 h-10 sm:w-12 sm:h-12 bg-jumia-orange/10 rounded-full flex items-center justify-center text-jumia-orange group-hover:bg-jumia-orange group-hover:text-white transition">
                <i class="<?php echo getCategoryIcon($cat); ?> text-sm sm:text-base"></i>
            </div>
            <span class="text-[10px] sm:text-xs font-medium text-gray-700 text-center leading-tight line-clamp-2"><?php echo htmlspecialchars($cat['name']); ?></span>
        </a>
        <?php endforeach; ?>
    </div>
</div>

<!-- FLASH SALE -->
<?php if (!empty($flashDeals)): ?>
<div class="max-w-7xl mx-auto px-4 mt-6" id="flash-deals">
    <div class="bg-jumia-red rounded-xl p-4 sm:p-5 shadow-lg">
        <div class="flex items-center justify-between mb-4">
            <div class="flex items-center gap-3">
                <i class="fas fa-bolt text-white text-2xl sale-pulse"></i>
                <div>
                    <h3 class="text-white font-extrabold text-lg tracking-tight">FLASH SALE</h3>
                    <p class="text-white/80 text-xs font-medium">Top discounts ending soon!</p>
                </div>
            </div>
            <div class="flex items-center gap-2" x-data="countdown()" x-init="start()">
                <span class="text-white/80 text-xs hidden sm:inline font-medium">Ends in:</span>
                <span class="countdown-box" x-text="hours">00</span><span class="text-white font-bold">:</span>
                <span class="countdown-box" x-text="minutes">00</span><span class="text-white font-bold">:</span>
                <span class="countdown-box" x-text="seconds">00</span>
            </div>
        </div>
        <div class="flex gap-3 overflow-x-auto pb-2 scrollbar-hide snap-x snap-mandatory">
            <?php foreach ($flashDeals as $p):
                $discount = calcDiscount($p['price'], $p['selling_price']);
                $price = (float) $p['price'];
                $salePrice = !empty($p['selling_price']) && $p['selling_price'] > 0 ? (float) $p['selling_price'] : $price;
                $stock = (int) $p['stock'];
            ?>
            <div class="snap-start shrink-0 w-[150px] sm:w-[180px] bg-white rounded-lg overflow-hidden shadow hover:shadow-xl transition-all duration-300 group">
                <a href="product.php?tenant=<?php echo $tenant_id; ?>&id=<?php echo $p['id']; ?>" class="block relative">
                    <div class="aspect-square bg-gray-50 flex items-center justify-center p-3">
                        <?php if (!empty($p['image'])): ?>
                        <img src="<?php echo htmlspecialchars($p['image']); ?>" alt="<?php echo htmlspecialchars($p['name']); ?>" class="max-w-full max-h-full object-contain group-hover:scale-105 transition" loading="lazy">
                        <?php else: ?>
                        <i class="fas fa-image text-gray-300 text-2xl"></i>
                        <?php endif; ?>
                    </div>
                    <?php if ($discount > 0): ?><span class="absolute top-2 left-2 bg-jumia-red text-white text-[10px] font-extrabold px-2 py-0.5 rounded">-<?php echo $discount; ?>%</span><?php endif; ?>
                    <?php if ($stock <= 0): ?><span class="absolute inset-0 bg-white/70 flex items-center justify-center text-xs font-bold text-gray-500">SOLD OUT</span><?php endif; ?>
                </a>
                <div class="p-2.5">
                    <a href="product.php?tenant=<?php echo $tenant_id; ?>&id=<?php echo $p['id']; ?>" class="block text-xs font-semibold text-gray-900 line-clamp-2 hover:text-jumia-orange transition mb-1 min-h-[2rem]"><?php echo htmlspecialchars($p['name']); ?></a>
                    <div class="flex items-baseline gap-1.5">
                        <span class="text-jumia-red font-extrabold text-sm"><?php echo formatPrice($salePrice, $currency); ?></span>
                        <?php if ($discount > 0): ?><span class="text-[10px] text-gray-400 line-through"><?php echo formatPrice($price, $currency); ?></span><?php endif; ?>
                    </div>
                    <?php if ($stock > 0 && $stock <= 5): ?><span class="text-[10px] text-jumia-red font-bold">Only <?php echo $stock; ?> left</span><?php endif; ?>
                    <div class="flex items-center gap-0.5 mt-1"><?php echo renderStars($p['rating']); ?><span class="text-[10px] text-gray-400">(<?php echo (int)($p['review_count'] ?? 0); ?>)</span></div>
                    <button <?php echo $stock > 0 ? '' : 'disabled'; ?> @click="<?php echo $stock > 0 ? "addToCart({$p['id']}, '".addslashes($p['name'])."', {$salePrice})" : ''; ?>" class="w-full mt-2 <?php echo $stock > 0 ? 'bg-jumia-orange hover:bg-brand-dark text-white' : 'bg-gray-100 text-gray-400 cursor-not-allowed'; ?> text-xs font-bold py-1.5 rounded transition">
                        <i class="fas fa-plus text-[10px]"></i> <?php echo $stock > 0 ? 'ADD TO CART' : 'SOLD OUT'; ?>
                    </button>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- TOP DEALS -->
<?php if (!empty($topDeals)): ?>
<div class="max-w-7xl mx-auto px-4 mt-8">
    <div class="flex items-center justify-between mb-4">
        <h3 class="text-lg font-extrabold text-gray-800 flex items-center gap-2"><i class="fas fa-fire text-jumia-orange"></i> Top Deals For You</h3>
        <a href="search.php?tenant=<?php echo $tenant_id; ?>" class="text-sm text-jumia-orange font-bold hover:underline">SEE ALL <i class="fas fa-chevron-right text-xs"></i></a>
    </div>
    <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6 gap-3">
        <?php foreach ($topDeals as $p):
            $discount = calcDiscount($p['price'], $p['selling_price']);
            $price = (float) $p['price'];
            $salePrice = !empty($p['selling_price']) && $p['selling_price'] > 0 ? (float) $p['selling_price'] : $price;
            $stock = (int) $p['stock'];
        ?>
        <div class="bg-white rounded-lg border border-gray-100 overflow-hidden hover:shadow-lg hover:border-jumia-orange/20 transition-all duration-300 group relative">
            <a href="product.php?tenant=<?php echo $tenant_id; ?>&id=<?php echo $p['id']; ?>" class="block relative">
                <div class="aspect-square bg-gray-50 flex items-center justify-center p-3">
                    <?php if (!empty($p['image'])): ?>
                    <img src="<?php echo htmlspecialchars($p['image']); ?>" alt="<?php echo htmlspecialchars($p['name']); ?>" class="max-w-full max-h-full object-contain group-hover:scale-105 transition" loading="lazy">
                    <?php else: ?>
                    <i class="fas fa-image text-gray-300 text-3xl"></i>
                    <?php endif; ?>
                </div>
                <?php if ($discount > 0): ?><span class="absolute top-2 left-2 bg-jumia-red text-white text-[10px] font-extrabold px-1.5 py-0.5 rounded">-<?php echo $discount; ?>%</span><?php endif; ?>
                <?php if ($stock <= 0): ?><span class="absolute inset-0 bg-white/70 flex items-center justify-center text-xs font-bold text-gray-500">SOLD OUT</span><?php endif; ?>
            </a>
            <div class="p-2.5">
                <a href="product.php?tenant=<?php echo $tenant_id; ?>&id=<?php echo $p['id']; ?>" class="block text-xs font-semibold text-gray-800 line-clamp-2 hover:text-jumia-orange transition mb-1 min-h-[2rem]"><?php echo htmlspecialchars($p['name']); ?></a>
                <div class="flex items-baseline gap-1.5">
                    <span class="text-jumia-red font-extrabold text-sm"><?php echo formatPrice($salePrice, $currency); ?></span>
                    <?php if ($discount > 0): ?><span class="text-[10px] text-gray-400 line-through"><?php echo formatPrice($price, $currency); ?></span><?php endif; ?>
                </div>
                <div class="flex items-center gap-0.5 mt-1"><?php echo renderStars($p['rating']); ?><span class="text-[10px] text-gray-400">(<?php echo (int)($p['review_count'] ?? 0); ?>)</span></div>
                <div class="flex items-center gap-1 mt-1.5">
                    <span class="bg-green-50 text-green-700 text-[9px] font-bold px-1.5 py-0.5 rounded flex items-center gap-0.5"><i class="fas fa-bolt text-[8px]"></i> EXPRESS</span>
                    <?php if ($stock > 0 && $stock <= 5): ?><span class="text-[9px] text-jumia-red font-bold"><?php echo $stock; ?> left</span><?php endif; ?>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<!-- NEW ARRIVALS -->
<?php if (!empty($newArrivals)): ?>
<div class="max-w-7xl mx-auto px-4 mt-8">
    <div class="flex items-center justify-between mb-4">
        <h3 class="text-lg font-extrabold text-gray-800 flex items-center gap-2"><span class="bg-jumia-orange text-white text-[10px] font-extrabold px-2 py-1 rounded tracking-wider">NEW</span> New Arrivals</h3>
        <a href="search.php?tenant=<?php echo $tenant_id; ?>" class="text-sm text-jumia-orange font-bold hover:underline">SEE ALL <i class="fas fa-chevron-right text-xs"></i></a>
    </div>
    <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6 gap-3">
        <?php foreach ($newArrivals as $p):
            $discount = calcDiscount($p['price'], $p['selling_price']);
            $price = (float) $p['price'];
            $salePrice = !empty($p['selling_price']) && $p['selling_price'] > 0 ? (float) $p['selling_price'] : $price;
            $stock = (int) $p['stock'];
        ?>
        <div class="bg-white rounded-lg border border-gray-100 overflow-hidden hover:shadow-lg hover:border-jumia-orange/20 transition-all duration-300 group relative">
            <a href="product.php?tenant=<?php echo $tenant_id; ?>&id=<?php echo $p['id']; ?>" class="block relative">
                <div class="aspect-square bg-gray-50 flex items-center justify-center p-3">
                    <?php if (!empty($p['image'])): ?>
                    <img src="<?php echo htmlspecialchars($p['image']); ?>" alt="<?php echo htmlspecialchars($p['name']); ?>" class="max-w-full max-h-full object-contain group-hover:scale-105 transition" loading="lazy">
                    <?php else: ?>
                    <i class="fas fa-image text-gray-300 text-3xl"></i>
                    <?php endif; ?>
                </div>
                <?php if ($discount > 0): ?><span class="absolute top-2 left-2 bg-jumia-red text-white text-[10px] font-extrabold px-1.5 py-0.5 rounded">-<?php echo $discount; ?>%</span><?php endif; ?>
                <?php if ($stock <= 0): ?><span class="absolute inset-0 bg-white/70 flex items-center justify-center text-xs font-bold text-gray-500">SOLD OUT</span><?php endif; ?>
            </a>
            <div class="p-2.5">
                <a href="product.php?tenant=<?php echo $tenant_id; ?>&id=<?php echo $p['id']; ?>" class="block text-xs font-semibold text-gray-800 line-clamp-2 hover:text-jumia-orange transition mb-1 min-h-[2rem]"><?php echo htmlspecialchars($p['name']); ?></a>
                <div class="flex items-baseline gap-1.5">
                    <span class="text-jumia-red font-extrabold text-sm"><?php echo formatPrice($salePrice, $currency); ?></span>
                    <?php if ($discount > 0): ?><span class="text-[10px] text-gray-400 line-through"><?php echo formatPrice($price, $currency); ?></span><?php endif; ?>
                </div>
                <div class="flex items-center gap-0.5 mt-1"><?php echo renderStars($p['rating']); ?><span class="text-[10px] text-gray-400">(<?php echo (int)($p['review_count'] ?? 0); ?>)</span></div>
                <div class="flex items-center gap-1 mt-1.5">
                    <span class="bg-blue-50 text-blue-700 text-[9px] font-bold px-1.5 py-0.5 rounded">NEW</span>
                    <?php if ($stock > 0 && $stock <= 5): ?><span class="text-[9px] text-jumia-red font-bold"><?php echo $stock; ?> left</span><?php endif; ?>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<!-- TRUST BADGES -->
<div class="max-w-7xl mx-auto px-4 mt-10">
    <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
        <div class="bg-white rounded-lg p-4 flex items-center gap-3 border border-gray-100">
            <div class="w-10 h-10 bg-jumia-orange/10 rounded-full flex items-center justify-center text-jumia-orange"><i class="fas fa-truck-fast text-lg"></i></div>
            <div><div class="text-sm font-bold text-gray-800">Free Delivery</div><div class="text-[10px] text-gray-500">On eligible orders</div></div>
        </div>
        <div class="bg-white rounded-lg p-4 flex items-center gap-3 border border-gray-100">
            <div class="w-10 h-10 bg-green-50 rounded-full flex items-center justify-center text-green-600"><i class="fas fa-rotate-left text-lg"></i></div>
            <div><div class="text-sm font-bold text-gray-800">Easy Returns</div><div class="text-[10px] text-gray-500">7-day return policy</div></div>
        </div>
        <div class="bg-white rounded-lg p-4 flex items-center gap-3 border border-gray-100">
            <div class="w-10 h-10 bg-blue-50 rounded-full flex items-center justify-center text-blue-600"><i class="fas fa-shield-halved text-lg"></i></div>
            <div><div class="text-sm font-bold text-gray-800">Secure Payment</div><div class="text-[10px] text-gray-500">100% secure checkout</div></div>
        </div>
        <div class="bg-white rounded-lg p-4 flex items-center gap-3 border border-gray-100">
            <div class="w-10 h-10 bg-purple-50 rounded-full flex items-center justify-center text-purple-600"><i class="fas fa-tag text-lg"></i></div>
            <div><div class="text-sm font-bold text-gray-800">Best Prices</div><div class="text-[10px] text-gray-500">Guaranteed savings</div></div>
        </div>
    </div>
</div>

<!-- NEWSLETTER -->
<div class="max-w-7xl mx-auto px-4 mt-10">
    <div class="bg-jumia-dark rounded-xl p-6 md:p-8 text-center">
        <h3 class="text-white font-extrabold text-xl mb-2">Subscribe to Our Newsletter</h3>
        <p class="text-gray-400 text-sm mb-5">Get the latest deals and updates delivered to your inbox.</p>
        <form class="flex max-w-md mx-auto gap-2" onsubmit="event.preventDefault(); alert('Thank you for subscribing!');">
            <input type="email" placeholder="Enter your email" class="flex-1 bg-white/10 border border-white/20 rounded-lg px-4 py-2.5 text-sm text-white placeholder-gray-400 focus:outline-none focus:border-jumia-orange transition">
            <button type="submit" class="bg-jumia-orange hover:bg-brand-dark text-white font-bold px-6 py-2.5 rounded-lg text-sm transition">Subscribe</button>
        </form>
    </div>
</div>

<!-- CART DRAWER -->
<div x-cloak x-show="cartOpen" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" x-transition:leave="transition ease-in duration-200" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0" class="fixed inset-0 bg-black/50 z-[100]" @click="cartOpen = false"></div>
<div x-cloak x-show="cartOpen" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="translate-x-full" x-transition:enter-end="translate-x-0" x-transition:leave="transition ease-in duration-200" x-transition:leave-start="translate-x-0" x-transition:leave-end="translate-x-full" class="fixed right-0 top-0 h-full w-full max-w-md bg-white shadow-2xl z-[101] flex flex-col">
    <div class="flex items-center justify-between p-4 border-b">
        <h3 class="font-bold text-lg flex items-center gap-2"><i class="fas fa-shopping-cart text-jumia-orange"></i> Your Cart (<span x-text="cartCount"></span>)</h3>
        <button @click="cartOpen = false" class="w-8 h-8 flex items-center justify-center rounded-full hover:bg-gray-100 transition"><i class="fas fa-times text-gray-500"></i></button>
    </div>
    <div class="flex-1 overflow-y-auto p-4 space-y-4">
        <template x-if="cartItems.length === 0">
            <div class="text-center py-12">
                <i class="fas fa-shopping-basket text-4xl text-gray-300 mb-3"></i>
                <p class="text-gray-500">Your cart is empty</p>
                <button @click="cartOpen = false" class="mt-4 text-jumia-orange font-bold text-sm hover:underline">Start Shopping</button>
            </div>
        </template>
        <template x-for="item in cartItems" :key="item.id">
            <div class="flex gap-3 p-3 bg-gray-50 rounded-lg">
                <img :src="item.image_url || '/assets/no-image.png'" class="w-16 h-16 object-contain bg-white rounded border" @error="$event.target.src='/assets/no-image.png'">
                <div class="flex-1 min-w-0">
                    <div class="text-sm font-medium text-gray-900 truncate" x-text="item.product_name"></div>
                    <div class="text-jumia-orange font-bold text-sm" x-text="'<?php echo $currency; ?> ' + parseFloat(item.unit_price).toFixed(0)"></div>
                    <div class="flex items-center gap-2 mt-1">
                        <button @click="updateQty(item.product_id, item.quantity - 1)" class="w-6 h-6 rounded bg-white border border-gray-200 flex items-center justify-center text-gray-500 hover:border-jumia-orange transition"><i class="fas fa-minus text-[10px]"></i></button>
                        <span class="text-sm font-medium w-6 text-center" x-text="item.quantity"></span>
                        <button @click="updateQty(item.product_id, item.quantity + 1)" class="w-6 h-6 rounded bg-white border border-gray-200 flex items-center justify-center text-gray-500 hover:border-jumia-orange transition"><i class="fas fa-plus text-[10px]"></i></button>
                        <button @click="removeItem(item.product_id)" class="ml-auto text-red-400 hover:text-red-600 transition"><i class="fas fa-trash-alt text-sm"></i></button>
                    </div>
                </div>
            </div>
        </template>
    </div>
    <div class="border-t p-4 space-y-3 bg-gray-50">
        <div class="flex justify-between text-sm"><span class="text-gray-500">Subtotal</span><span class="font-semibold" x-text="'<?php echo $currency; ?> ' + cartTotal.toFixed(0)"></span></div>
        <button x-show="cartItems.length > 0" @click="window.location.href='checkout.php?tenant=<?php echo $tenant_id; ?>'" class="w-full bg-jumia-orange hover:bg-brand-dark text-white font-bold py-3 rounded-xl transition shadow-lg shadow-orange-500/20">Proceed to Checkout</button>
    </div>
</div>

<!-- MOBILE BOTTOM NAV -->
<nav class="md:hidden fixed bottom-0 left-0 right-0 bg-white border-t z-50 flex justify-around py-2 shadow-lg">
    <a href="index.php?tenant=<?php echo $tenant_id; ?>" class="flex flex-col items-center gap-0.5 text-jumia-orange"><i class="fas fa-home text-lg"></i><span class="text-[10px] font-medium">Home</span></a>
    <a href="search.php?tenant=<?php echo $tenant_id; ?>" class="flex flex-col items-center gap-0.5 text-gray-400"><i class="fas fa-search text-lg"></i><span class="text-[10px] font-medium">Search</span></a>
    <button @click="cartOpen = true" class="flex flex-col items-center gap-0.5 text-gray-400 relative">
        <i class="fas fa-shopping-cart text-lg"></i>
        <span x-show="cartCount > 0" x-text="cartCount" class="absolute -top-1 right-0 bg-jumia-red text-white text-[8px] w-4 h-4 rounded-full flex items-center justify-center font-bold"></span>
        <span class="text-[10px] font-medium">Cart</span>
    </button>
    <a href="account/orders.php?tenant=<?php echo $tenant_id; ?>" class="flex flex-col items-center gap-0.5 text-gray-400"><i class="fas fa-user text-lg"></i><span class="text-[10px] font-medium">Account</span></a>
</nav>

<!-- FOOTER -->
<footer class="bg-jumia-dark text-white mt-12 pb-24 md:pb-0">
    <div class="max-w-7xl mx-auto px-4 py-10 grid grid-cols-2 md:grid-cols-4 lg:grid-cols-5 gap-8">
        <div class="col-span-2 md:col-span-1">
            <div class="flex items-center gap-2 mb-3">
                <div class="w-8 h-8 bg-jumia-orange rounded flex items-center justify-center text-white font-extrabold text-sm">J</div>
                <div class="font-extrabold text-lg tracking-tight"><?php echo getFirstName($store_name); ?></div>
            </div>
            <p class="text-xs text-gray-400 leading-relaxed">Your trusted online shopping destination. Quality products, fast delivery, great prices.</p>
        </div>
        <div>
            <h4 class="font-bold text-sm mb-3 text-gray-200">Quick Links</h4>
            <ul class="space-y-2 text-xs text-gray-400">
                <li><a href="index.php?tenant=<?php echo $tenant_id; ?>" class="hover:text-jumia-orange transition">Home</a></li>
                <li><a href="search.php?tenant=<?php echo $tenant_id; ?>" class="hover:text-jumia-orange transition">All Products</a></li>
                <li><a href="track.php?tenant=<?php echo $tenant_id; ?>" class="hover:text-jumia-orange transition">Track Order</a></li>
                <li><a href="account/orders.php?tenant=<?php echo $tenant_id; ?>" class="hover:text-jumia-orange transition">My Orders</a></li>
            </ul>
        </div>
        <div>
            <h4 class="font-bold text-sm mb-3 text-gray-200">Customer Service</h4>
            <ul class="space-y-2 text-xs text-gray-400">
                <li><a href="#" class="hover:text-jumia-orange transition">Help Center</a></li>
                <li><a href="#" class="hover:text-jumia-orange transition">Returns Policy</a></li>
                <li><a href="#" class="hover:text-jumia-orange transition">Shipping Info</a></li>
                <li><a href="#" class="hover:text-jumia-orange transition">Terms & Conditions</a></li>
            </ul>
        </div>
        <div>
            <h4 class="font-bold text-sm mb-3 text-gray-200">About Us</h4>
            <ul class="space-y-2 text-xs text-gray-400">
                <li><a href="#" class="hover:text-jumia-orange transition">About <?php echo getFirstName($store_name); ?></a></li>
                <li><a href="#" class="hover:text-jumia-orange transition">Careers</a></li>
                <li><a href="#" class="hover:text-jumia-orange transition">Sell on <?php echo getFirstName($store_name); ?></a></li>
                <li><a href="#" class="hover:text-jumia-orange transition">Privacy Policy</a></li>
            </ul>
        </div>
        <div>
            <h4 class="font-bold text-sm mb-3 text-gray-200">Contact</h4>
            <?php if ($whatsapp): ?>
            <a href="https://wa.me/<?php echo preg_replace('/[^0-9]/', '', $whatsapp); ?>" class="inline-flex items-center gap-2 bg-green-600 hover:bg-green-700 text-white px-3 py-2 rounded-lg text-xs font-bold transition mb-2"><i class="fab fa-whatsapp"></i> WhatsApp Us</a>
            <?php endif; ?>
            <p class="text-xs text-gray-500 mt-2"><?php echo htmlspecialchars($whatsapp); ?></p>
        </div>
    </div>
    <div class="border-t border-white/10 py-4 text-center text-xs text-gray-500">
        &copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars($store_name); ?>. All rights reserved. Powered by Jakababa.
    </div>
</footer>

<script>
function shopApp() {
    return {
        cartOpen: false,
        cartItems: [],
        cartCount: 0,
        cartTotal: 0,
        tenantId: <?php echo $tenant_id; ?>,
        cartKey: 'jumia_cart_<?php echo $tenant_id; ?>',

        initCart() {
            this.loadCart();
        },

        loadCart() {
            try {
                const raw = localStorage.getItem(this.cartKey);
                const cart = raw ? JSON.parse(raw) : { items: [] };
                this.cartItems = cart.items || [];
                this.recalc();
            } catch (e) { console.error('Cart load error', e); }
        },

        saveCart() {
            localStorage.setItem(this.cartKey, JSON.stringify({ items: this.cartItems }));
            this.recalc();
        },

        recalc() {
            this.cartCount = this.cartItems.reduce((sum, i) => sum + parseInt(i.quantity), 0);
            this.cartTotal = this.cartItems.reduce((sum, i) => sum + (parseFloat(i.unit_price) * parseInt(i.quantity)), 0);
        },

        addToCart(productId, name, price) {
            const existing = this.cartItems.find(i => i.product_id == productId);
            if (existing) {
                existing.quantity++;
            } else {
                this.cartItems.push({ product_id: productId, product_name: name, unit_price: price, quantity: 1, image_url: '' });
            }
            this.saveCart();
            this.cartOpen = true;
        },

        updateQty(productId, qty) {
            if (qty < 1) { this.removeItem(productId); return; }
            const item = this.cartItems.find(i => i.product_id == productId);
            if (item) { item.quantity = qty; this.saveCart(); }
        },

        removeItem(productId) {
            this.cartItems = this.cartItems.filter(i => i.product_id != productId);
            this.saveCart();
        }
    }
}

function countdown() {
    return {
        hours: '00', minutes: '00', seconds: '00',
        start() {
            const end = new Date();
            end.setHours(23, 59, 59);
            const tick = () => {
                const diff = end - new Date();
                if (diff <= 0) { this.hours = '00'; this.minutes = '00'; this.seconds = '00'; return; }
                this.hours = String(Math.floor(diff / 3600000)).padStart(2, '0');
                this.minutes = String(Math.floor((diff % 3600000) / 60000)).padStart(2, '0');
                this.seconds = String(Math.floor((diff % 60000) / 1000)).padStart(2, '0');
            };
            tick();
            setInterval(tick, 1000);
        }
    }
}
</script>

</body>
</html>
