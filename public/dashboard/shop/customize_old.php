<?php
require_once __DIR__ . '/../../../src/paths.php';
safe_require('auth.php', 'src', true);
safe_require('db.php', 'src', true);
safe_require('functions.php', 'src', true);
require_login();

$pdo = get_db_connection();
$tenant_id = (int)($_SESSION['tenant_id'] ?? get_current_tenant_id() ?? 0);
if (!$tenant_id) { 
    echo '<div class="flex items-center justify-center h-screen font-sans text-gray-600">Access Denied — no tenant context</div>'; 
    exit; 
}

$store_name = $_SESSION['company_name'] ?? 'My Store';
$msg = '';
$err = '';

// ---------- Handle save ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fields = [
        'store_name', 'site_title', 'site_description',
        'primary_color', 'secondary_color',
        'currency', 'whatsapp_number',
        'facebook_url', 'instagram_url', 'tiktok_url',
        'meta_title', 'meta_description',
        'free_shipping_threshold', 'min_order_amount',
        'show_reviews', 'show_stock_count',
        'online_store_enabled',
        'announcement_bar_text', 'announcement_bar_enabled',
        'show_deal_spotlight', 'show_newsletter', 'show_recently_viewed',
        'deal_of_the_day_product_id',
        'homepage_sections',
    ];
    foreach ($fields as $key) {
        $val = $_POST[$key] ?? '';
        $pdo->prepare(
            "INSERT INTO storefront_settings (tenant_id, setting_key, setting_value)
             VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)"
        )->execute([$tenant_id, $key, $val]);
    }
    // Handle logo upload
    if (!empty($_FILES['logo_file']['tmp_name'])) {
        $ext  = strtolower(pathinfo($_FILES['logo_file']['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg','jpeg','png','gif','svg','webp'];
        if (in_array($ext, $allowed)) {
            $dir = ROOT_PATH . '/uploads/store/';
            if (!is_dir($dir)) mkdir($dir, 0755, true);
            $filename = 'logo_' . $tenant_id . '.' . $ext;
            move_uploaded_file($_FILES['logo_file']['tmp_name'], $dir . $filename);
            $logo_url = '/uploads/store/' . $filename;
            $pdo->prepare("INSERT INTO storefront_settings (tenant_id, setting_key, setting_value) VALUES (?, 'logo_url', ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)")->execute([$tenant_id, $logo_url]);
        }
    }
    $msg = 'Storefront settings saved!';
}

// ---------- Load current settings ----------
$rows = $pdo->prepare("SELECT setting_key, setting_value FROM storefront_settings WHERE tenant_id = ?");
$rows->execute([$tenant_id]);
$s = [];
while ($r = $rows->fetch(PDO::FETCH_ASSOC)) {
    $s[$r['setting_key']] = $r['setting_value'];
}
$s += [
    'store_name'               => 'My Store',
    'site_title'               => '',
    'site_description'         => '',
    'primary_color'            => '#f68b1e',
    'secondary_color'          => '#1a1a2e',
    'currency'                 => 'KES',
    'whatsapp_number'          => '',
    'facebook_url'             => '',
    'instagram_url'            => '',
    'tiktok_url'               => '',
    'meta_title'               => '',
    'meta_description'         => '',
    'logo_url'                 => '',
    'free_shipping_threshold'  => '0',
    'min_order_amount'         => '0',
    'show_reviews'             => '1',
    'show_stock_count'         => '0',
    'online_store_enabled'     => '1',
    'announcement_bar_text'    => '',
    'announcement_bar_enabled' => '0',
    'show_deal_spotlight'      => '1',
    'show_newsletter'          => '1',
    'show_recently_viewed'     => '1',
    'deal_of_the_day_product_id' => '',
    'homepage_sections'        => 'flash_sale,top_selling,new_arrivals,categories',
];

$brand_color = $s['primary_color'] ?? '#f68b1e';
$store_url = storefront_url($tenant_id);

// Stats for sidebar
$stats = ['pending_orders' => 0, 'reviews_pending' => 0];
try {
    $r = $pdo->prepare("SELECT COUNT(*) FROM online_orders WHERE tenant_id = ? AND status='pending'"); 
    $r->execute([$tenant_id]); 
    $stats['pending_orders'] = (int)$r->fetchColumn();
} catch (Exception $e) {}

$status = [
    'online' => ($s['online_store_enabled'] ?? '0') === '1',
    'pending_orders' => $stats['pending_orders'],
    'reviews_pending' => $stats['reviews_pending']
];

// Products for Deal of the Day picker
$productsList = [];
try {
    $r = $pdo->prepare("SELECT id, name, price, selling_price FROM products WHERE tenant_id = ? AND active = 1 AND (deleted_at IS NULL OR deleted_at = '0000-00-00 00:00:00') ORDER BY name LIMIT 200");
    $r->execute([$tenant_id]);
    $productsList = $r->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

// Count blocks for sidebar
$blocks_count = 0;
try {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM storefront_blocks WHERE tenant_id = ? AND is_active = 1");
    $stmt->execute([$tenant_id]);
    $blocks_count = (int)$stmt->fetchColumn();
} catch (Exception $e) {}

$settings = $s;
$storeUrl = $store_url;
$nav_page = 'customize';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customize Store — <?php echo htmlspecialchars($s['store_name']); ?></title>
    <link rel="stylesheet" href="<?php echo asset_url('css/app.css'); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        brand: {
                            DEFAULT: '<?= $brand_color ?>',
                            dark: '<?= $s['secondary_color'] ?? '#d97706' ?>'
                        }
                    }
                }
            }
        }
    </script>
    <style>
        body { font-family: 'Inter', sans-serif; }
        .section-item { transition: all 0.15s; cursor: grab; }
        .section-item:active { cursor: grabbing; }
        .section-item.dragging { opacity: 0.5; }
        .section-item.drag-over { border-color: <?= $brand_color ?>; background: #fef3c7; }
        .color-picker-sync { transition: all 0.2s; }
        .toggle-switch { transition: all 0.2s; }
    </style>
    <script>
        // ── Color picker sync ──
        function syncColorPicker(input) {
            const textInput = input.nextElementSibling;
            if (textInput && textInput.tagName === 'INPUT') {
                textInput.value = input.value;
            }
        }

        function applyPreset(primary, secondary) {
            const primaryInput = document.getElementById('primaryColor');
            const primaryText = document.getElementById('primaryColorHex');
            const secondaryInput = document.getElementById('secondaryColor');
            const secondaryText = document.getElementById('secondaryColorHex');
            
            if (primaryInput && primaryText) {
                primaryInput.value = primary;
                primaryText.value = primary;
            }
            if (secondaryInput && secondaryText) {
                secondaryInput.value = secondary;
                secondaryText.value = secondary;
            }
        }

        // ── Drag-to-reorder for sections ──
        document.addEventListener('DOMContentLoaded', function() {
            const list = document.getElementById('sectionsList');
            const input = document.getElementById('homepageSections');
            if (!list || !input) return;
            
            let dragging = null;
            let dragOverItem = null;

            function updateInput() {
                const keys = [...list.querySelectorAll('.section-item')].map(el => el.dataset.key);
                input.value = keys.join(',');
            }

            list.querySelectorAll('.section-item').forEach(item => {
                item.setAttribute('draggable', true);
                
                item.addEventListener('dragstart', function(e) {
                    dragging = this;
                    this.classList.add('dragging');
                    e.dataTransfer.effectAllowed = 'move';
                });
                
                item.addEventListener('dragend', function() {
                    this.classList.remove('dragging');
                    list.querySelectorAll('.section-item').forEach(el => el.classList.remove('drag-over'));
                    dragging = null;
                    updateInput();
                });
                
                item.addEventListener('dragover', function(e) {
                    e.preventDefault();
                    e.dataTransfer.dropEffect = 'move';
                    
                    if (!dragging || dragging === this) return;
                    
                    this.classList.add('drag-over');
                    const rect = this.getBoundingClientRect();
                    const mid = rect.top + rect.height / 2;
                    
                    if (e.clientY < mid) {
                        list.insertBefore(dragging, this);
                    } else {
                        list.insertBefore(dragging, this.nextSibling);
                    }
                });
                
                item.addEventListener('dragleave', function() {
                    this.classList.remove('drag-over');
                });
            });

            // Color picker sync
            document.querySelectorAll('input[type="color"]').forEach(input => {
                input.addEventListener('input', function() {
                    syncColorPicker(this);
                });
            });
        });
    </script>
</head>
<body class="bg-gray-50 h-screen flex overflow-hidden font-sans">

<?php 
// Pass variables to sidebar
$blocks_count = $blocks_count;
$settings = $s;
$status = $status;
$store_name = $s['store_name'];
$storeUrl = $store_url;
require __DIR__ . '/_sidebar.php'; 
?>

<div class="flex-1 flex flex-col overflow-hidden">
    <!-- Header -->
    <header class="bg-white border-b border-gray-200 px-6 py-3 flex items-center justify-between flex-shrink-0">
        <div>
            <h1 class="text-lg font-bold text-gray-800 flex items-center gap-2">
                Customize Store
                <?php if (!empty($s['store_name'])): ?>
                    <span class="text-xs font-normal text-gray-400">— <?= htmlspecialchars($s['store_name']) ?></span>
                <?php endif; ?>
            </h1>
            <p class="text-xs text-gray-400">Control your storefront appearance and settings</p>
        </div>
        <div class="flex items-center gap-3">
            <a href="blocks.php" 
               class="flex items-center gap-1.5 text-gray-600 hover:text-gray-800 text-xs bg-gray-50 px-3 py-1.5 rounded-lg border border-gray-200 transition hover:bg-gray-100">
                <i class="fas fa-cubes"></i> Page Builder
            </a>
            <a href="<?php echo htmlspecialchars($store_url); ?>" target="_blank"
               class="flex items-center gap-2 text-white text-xs font-bold px-4 py-2 rounded-lg transition hover:opacity-90"
               style="background:<?= $brand_color ?>">
                <i class="fas fa-eye"></i> Preview Store
            </a>
        </div>
    </header>

    <main class="flex-1 overflow-y-auto p-6">
    <div class="max-w-7xl mx-auto">

    <?php if ($msg): ?>
    <div class="mb-4 px-4 py-3 rounded-lg text-sm font-bold flex items-center gap-2 bg-green-50 border border-green-200 text-green-700">
        <i class="fas fa-check-circle"></i>
        <?= htmlspecialchars($msg) ?>
    </div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data" class="space-y-6">

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

            <!-- LEFT: Main settings -->
            <div class="lg:col-span-2 space-y-6">

                <!-- Store Identity -->
                <div class="bg-white rounded-2xl border border-gray-100 p-6">
                    <h2 class="text-base font-bold text-gray-800 mb-4 flex items-center gap-2">
                        <span class="text-xl">🏪</span> Store Identity
                    </h2>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-semibold text-gray-600 mb-1">Store Name</label>
                            <input type="text" name="store_name" 
                                   value="<?= htmlspecialchars($s['store_name']) ?>"
                                   class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-orange-300">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-600 mb-1">Site Title (browser tab)</label>
                            <input type="text" name="site_title" 
                                   value="<?= htmlspecialchars($s['site_title']) ?>"
                                   class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-orange-300">
                        </div>
                        <div class="sm:col-span-2">
                            <label class="block text-xs font-semibold text-gray-600 mb-1">Site Description</label>
                            <textarea name="site_description" rows="2"
                                      class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-orange-300"><?= htmlspecialchars($s['site_description']) ?></textarea>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-600 mb-1">Currency</label>
                            <select name="currency" 
                                    class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-orange-300">
                                <?php foreach (['KES','USD','EUR','GBP','TZS','UGX','ZAR'] as $c): ?>
                                <option value="<?= $c ?>" <?= $s['currency'] === $c ? 'selected' : '' ?>><?= $c ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-600 mb-1">WhatsApp Number</label>
                            <input type="text" name="whatsapp_number" 
                                   value="<?= htmlspecialchars($s['whatsapp_number']) ?>"
                                   placeholder="+254712345678"
                                   class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-orange-300">
                        </div>
                    </div>

                    <!-- Logo -->
                    <div class="mt-4">
                        <label class="block text-xs font-semibold text-gray-600 mb-2">Store Logo</label>
                        <div class="flex items-center gap-4">
                            <?php if ($s['logo_url']): ?>
                            <img src="<?= htmlspecialchars($s['logo_url']) ?>" alt="Logo"
                                 class="h-14 w-14 object-contain rounded-xl border border-gray-200 bg-gray-50 p-1">
                            <?php else: ?>
                            <div class="h-14 w-14 rounded-xl border-2 border-dashed border-gray-300 flex items-center justify-center text-2xl bg-gray-50">🏪</div>
                            <?php endif; ?>
                            <div class="flex-1">
                                <input type="file" name="logo_file" accept="image/*"
                                       class="text-sm text-gray-500 file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-orange-50 file:text-orange-600 hover:file:bg-orange-100">
                                <p class="text-[11px] text-gray-400 mt-1">PNG, JPG, SVG, WebP — max 2MB</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Brand Colors -->
                <div class="bg-white rounded-2xl border border-gray-100 p-6">
                    <h2 class="text-base font-bold text-gray-800 mb-4 flex items-center gap-2">
                        <span class="text-xl">🎨</span> Brand Colors
                    </h2>
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
                        <div>
                            <label class="block text-xs font-semibold text-gray-600 mb-2">Primary Color</label>
                            <div class="flex items-center gap-2">
                                <input type="color" name="primary_color" 
                                       id="primaryColor"
                                       value="<?= htmlspecialchars($s['primary_color']) ?>"
                                       class="w-10 h-10 rounded-lg border border-gray-200 cursor-pointer p-0.5"
                                       oninput="syncColorPicker(this)">
                                <input type="text" id="primaryColorHex" 
                                       value="<?= htmlspecialchars($s['primary_color']) ?>"
                                       class="flex-1 border border-gray-200 rounded-lg px-2 py-1.5 text-xs font-mono focus:outline-none focus:ring-2 focus:ring-orange-300"
                                       readonly>
                            </div>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-600 mb-2">Secondary Color</label>
                            <div class="flex items-center gap-2">
                                <input type="color" name="secondary_color" 
                                       id="secondaryColor"
                                       value="<?= htmlspecialchars($s['secondary_color']) ?>"
                                       class="w-10 h-10 rounded-lg border border-gray-200 cursor-pointer p-0.5"
                                       oninput="syncColorPicker(this)">
                                <input type="text" id="secondaryColorHex" 
                                       value="<?= htmlspecialchars($s['secondary_color']) ?>"
                                       class="flex-1 border border-gray-200 rounded-lg px-2 py-1.5 text-xs font-mono focus:outline-none focus:ring-2 focus:ring-orange-300"
                                       readonly>
                            </div>
                        </div>
                    </div>
                    <!-- Preset palettes -->
                    <div class="mt-4">
                        <p class="text-xs font-semibold text-gray-500 mb-2">Quick Presets</p>
                        <div class="flex flex-wrap gap-2">
                            <?php
                            $presets = [
                                ['Jumia Orange', '#f68b1e', '#1a1a2e'],
                                ['Kilimall Red', '#e02020', '#1a1a2e'],
                                ['Safaricom Green', '#2ecc71', '#1a472a'],
                                ['Sky Blue', '#2563eb', '#1e3a5f'],
                                ['Royal Purple', '#7c3aed', '#2e1065'],
                                ['Rose Pink', '#e91e8c', '#3d0038'],
                                ['Forest Green', '#16a34a', '#052e16'],
                                ['Midnight Dark', '#111827', '#374151'],
                            ];
                            foreach ($presets as [$name, $primary, $secondary]):
                            ?>
                            <button type="button"
                                    onclick="applyPreset('<?= $primary ?>','<?= $secondary ?>')"
                                    class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-gray-200 text-xs font-medium text-gray-600 hover:bg-gray-50 transition">
                                <span class="w-4 h-4 rounded-full border border-white shadow-sm" style="background:<?= $primary ?>"></span>
                                <?= $name ?>
                            </button>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <!-- Homepage Sections Order -->
                <div class="bg-white rounded-2xl border border-gray-100 p-6">
                    <h2 class="text-base font-bold text-gray-800 mb-1 flex items-center gap-2">
                        <span class="text-xl">📐</span> Homepage Sections
                    </h2>
                    <p class="text-xs text-gray-400 mb-4">Drag to reorder. Toggle to show/hide.</p>
                    <input type="hidden" name="homepage_sections" id="homepageSections" 
                           value="<?= htmlspecialchars($s['homepage_sections']) ?>">
                    <ul id="sectionsList" class="space-y-2">
                        <?php
                        $sectionLabels = [
                            'flash_sale'   => ['⚡', 'Flash Sale', 'Discounted products with countdown timer'],
                            'top_selling'  => ['🔥', 'Top Selling', 'Most popular products'],
                            'new_arrivals' => ['✨', 'New Arrivals', 'Recently added products'],
                            'categories'   => ['🗂️', 'Category Grid', 'Browse by category icons'],
                        ];
                        $order = array_filter(explode(',', $s['homepage_sections']));
                        if (empty($order)) $order = array_keys($sectionLabels);
                        foreach ($order as $key):
                            if (!isset($sectionLabels[$key])) continue;
                            [$emoji, $label, $desc] = $sectionLabels[$key];
                        ?>
                        <li class="section-item flex items-center gap-3 bg-gray-50 rounded-xl px-3 py-2.5 border border-transparent hover:border-orange-200"
                            data-key="<?= $key ?>">
                            <span class="text-gray-400"><i class="fas fa-grip-vertical"></i></span>
                            <span class="text-lg"><?= $emoji ?></span>
                            <div class="flex-1">
                                <p class="text-sm font-semibold text-gray-700"><?= $label ?></p>
                                <p class="text-[11px] text-gray-400"><?= $desc ?></p>
                            </div>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </div>

                <!-- Social & SEO -->
                <div class="bg-white rounded-2xl border border-gray-100 p-6">
                    <h2 class="text-base font-bold text-gray-800 mb-4 flex items-center gap-2">
                        <span class="text-xl">🔗</span> Social & SEO
                    </h2>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-semibold text-gray-600 mb-1">Facebook URL</label>
                            <input type="url" name="facebook_url" 
                                   value="<?= htmlspecialchars($s['facebook_url']) ?>"
                                   placeholder="https://facebook.com/yourpage"
                                   class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-orange-300">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-600 mb-1">Instagram URL</label>
                            <input type="url" name="instagram_url" 
                                   value="<?= htmlspecialchars($s['instagram_url']) ?>"
                                   placeholder="https://instagram.com/yourpage"
                                   class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-orange-300">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-600 mb-1">TikTok URL</label>
                            <input type="url" name="tiktok_url" 
                                   value="<?= htmlspecialchars($s['tiktok_url']) ?>"
                                   placeholder="https://tiktok.com/@yourpage"
                                   class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-orange-300">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-600 mb-1">Meta Title (SEO)</label>
                            <input type="text" name="meta_title" 
                                   value="<?= htmlspecialchars($s['meta_title']) ?>"
                                   class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-orange-300">
                        </div>
                        <div class="sm:col-span-2">
                            <label class="block text-xs font-semibold text-gray-600 mb-1">Meta Description (SEO)</label>
                            <textarea name="meta_description" rows="2"
                                      class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-orange-300"><?= htmlspecialchars($s['meta_description']) ?></textarea>
                        </div>
                    </div>
                </div>

            </div>

            <!-- RIGHT: Controls -->
            <div class="space-y-6">

                <!-- Save button -->
                <div class="bg-white rounded-2xl border border-gray-100 p-4">
                    <button type="submit"
                            class="w-full py-3 rounded-xl text-white font-bold text-sm transition hover:opacity-90"
                            style="background:<?= $brand_color ?>">
                        💾 Save Changes
                    </button>
                    <a href="<?= htmlspecialchars($store_url) ?>" target="_blank"
                       class="mt-2 w-full flex items-center justify-center py-2.5 rounded-xl border border-gray-200 text-sm font-medium text-gray-600 hover:bg-gray-50 transition">
                        👁️ Preview Storefront
                    </a>
                </div>

                <!-- Store Status -->
                <div class="bg-white rounded-2xl border border-gray-100 p-4">
                    <h3 class="text-sm font-bold text-gray-700 mb-3">Store Status</h3>
                    
                    <label class="flex items-center justify-between cursor-pointer">
                        <div>
                            <p class="text-sm font-medium text-gray-700">Online Store</p>
                            <p class="text-xs text-gray-400">Customers can browse & order</p>
                        </div>
                        <div class="relative inline-block w-11 h-6">
                            <input type="checkbox" name="online_store_enabled" value="1"
                                   <?= $s['online_store_enabled'] === '1' ? 'checked' : '' ?>
                                   class="sr-only peer">
                            <div class="w-11 h-6 bg-gray-200 peer-checked:bg-orange-500 rounded-full transition toggle-switch"></div>
                            <div class="absolute left-0.5 top-0.5 w-5 h-5 bg-white rounded-full shadow transition toggle-switch peer-checked:translate-x-5"></div>
                        </div>
                    </label>

                    <hr class="my-3 border-gray-100">

                    <label class="flex items-center justify-between cursor-pointer">
                        <div>
                            <p class="text-sm font-medium text-gray-700">Show Reviews</p>
                            <p class="text-xs text-gray-400">Display customer reviews</p>
                        </div>
                        <div class="relative inline-block w-11 h-6">
                            <input type="checkbox" name="show_reviews" value="1"
                                   <?= $s['show_reviews'] === '1' ? 'checked' : '' ?>
                                   class="sr-only peer">
                            <div class="w-11 h-6 bg-gray-200 peer-checked:bg-orange-500 rounded-full transition toggle-switch"></div>
                            <div class="absolute left-0.5 top-0.5 w-5 h-5 bg-white rounded-full shadow transition toggle-switch peer-checked:translate-x-5"></div>
                        </div>
                    </label>

                    <hr class="my-3 border-gray-100">

                    <label class="flex items-center justify-between cursor-pointer">
                        <div>
                            <p class="text-sm font-medium text-gray-700">Show Stock Count</p>
                            <p class="text-xs text-gray-400">"Only 3 left" urgency badge</p>
                        </div>
                        <div class="relative inline-block w-11 h-6">
                            <input type="checkbox" name="show_stock_count" value="1"
                                   <?= $s['show_stock_count'] === '1' ? 'checked' : '' ?>
                                   class="sr-only peer">
                            <div class="w-11 h-6 bg-gray-200 peer-checked:bg-orange-500 rounded-full transition toggle-switch"></div>
                            <div class="absolute left-0.5 top-0.5 w-5 h-5 bg-white rounded-full shadow transition toggle-switch peer-checked:translate-x-5"></div>
                        </div>
                    </label>

                    <hr class="my-3 border-gray-100">

                    <label class="flex items-center justify-between cursor-pointer">
                        <div>
                            <p class="text-sm font-medium text-gray-700">Deal Spotlight</p>
                            <p class="text-xs text-gray-400">Large deal-of-the-day card</p>
                        </div>
                        <div class="relative inline-block w-11 h-6">
                            <input type="checkbox" name="show_deal_spotlight" value="1"
                                   <?= $s['show_deal_spotlight'] === '1' ? 'checked' : '' ?>
                                   class="sr-only peer">
                            <div class="w-11 h-6 bg-gray-200 peer-checked:bg-orange-500 rounded-full transition toggle-switch"></div>
                            <div class="absolute left-0.5 top-0.5 w-5 h-5 bg-white rounded-full shadow transition toggle-switch peer-checked:translate-x-5"></div>
                        </div>
                    </label>

                    <hr class="my-3 border-gray-100">

                    <label class="flex items-center justify-between cursor-pointer">
                        <div>
                            <p class="text-sm font-medium text-gray-700">Newsletter Signup</p>
                            <p class="text-xs text-gray-400">Email capture section</p>
                        </div>
                        <div class="relative inline-block w-11 h-6">
                            <input type="checkbox" name="show_newsletter" value="1"
                                   <?= $s['show_newsletter'] === '1' ? 'checked' : '' ?>
                                   class="sr-only peer">
                            <div class="w-11 h-6 bg-gray-200 peer-checked:bg-orange-500 rounded-full transition toggle-switch"></div>
                            <div class="absolute left-0.5 top-0.5 w-5 h-5 bg-white rounded-full shadow transition toggle-switch peer-checked:translate-x-5"></div>
                        </div>
                    </label>

                    <hr class="my-3 border-gray-100">

                    <label class="flex items-center justify-between cursor-pointer">
                        <div>
                            <p class="text-sm font-medium text-gray-700">Recently Viewed</p>
                            <p class="text-xs text-gray-400">Per-user browsing history row</p>
                        </div>
                        <div class="relative inline-block w-11 h-6">
                            <input type="checkbox" name="show_recently_viewed" value="1"
                                   <?= $s['show_recently_viewed'] === '1' ? 'checked' : '' ?>
                                   class="sr-only peer">
                            <div class="w-11 h-6 bg-gray-200 peer-checked:bg-orange-500 rounded-full transition toggle-switch"></div>
                            <div class="absolute left-0.5 top-0.5 w-5 h-5 bg-white rounded-full shadow transition toggle-switch peer-checked:translate-x-5"></div>
                        </div>
                    </label>
                </div>

                <!-- Announcement Bar -->
                <div class="bg-white rounded-2xl border border-gray-100 p-4">
                    <h3 class="text-sm font-bold text-gray-700 mb-3">📢 Announcement Bar</h3>
                    <label class="flex items-center justify-between cursor-pointer mb-3">
                        <span class="text-sm text-gray-600">Enable bar</span>
                        <div class="relative inline-block w-11 h-6">
                            <input type="checkbox" name="announcement_bar_enabled" value="1"
                                   <?= $s['announcement_bar_enabled'] === '1' ? 'checked' : '' ?>
                                   class="sr-only peer">
                            <div class="w-11 h-6 bg-gray-200 peer-checked:bg-orange-500 rounded-full transition toggle-switch"></div>
                            <div class="absolute left-0.5 top-0.5 w-5 h-5 bg-white rounded-full shadow transition toggle-switch peer-checked:translate-x-5"></div>
                        </div>
                    </label>
                    <input type="text" name="announcement_bar_text"
                           value="<?= htmlspecialchars($s['announcement_bar_text']) ?>"
                           placeholder="🎉 Free delivery on orders over KES 2000!"
                           class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-orange-300">
                </div>

                <!-- Deal of the Day -->
                <div class="bg-white rounded-2xl border border-gray-100 p-4">
                    <h3 class="text-sm font-bold text-gray-700 mb-3">🔥 Deal of the Day</h3>
                    <label class="block text-xs font-semibold text-gray-600 mb-1">Pick a product to spotlight</label>
                    <select name="deal_of_the_day_product_id"
                            class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-orange-300">
                        <option value="">— None (auto-pick first discounted product) —</option>
                        <?php foreach ($productsList as $prod):
                            $prodPrice = (float)($prod['selling_price'] ?: $prod['price']);
                            $hasDiscount = !empty($prod['selling_price']) && (float)$prod['selling_price'] > 0 && (float)$prod['selling_price'] < (float)$prod['price'];
                        ?>
                        <option value="<?= $prod['id'] ?>" <?= $s['deal_of_the_day_product_id'] == $prod['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($prod['name']) ?> — <?= $s['currency'] ?> <?= number_format($prodPrice, 0) ?> <?= $hasDiscount ? '(ON SALE)' : '' ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <p class="text-[11px] text-gray-400 mt-1">Shows a large spotlight card with countdown timer on the home page.</p>
                </div>

                <!-- Shipping & Orders -->
                <div class="bg-white rounded-2xl border border-gray-100 p-4">
                    <h3 class="text-sm font-bold text-gray-700 mb-3">🚚 Shipping & Orders</h3>
                    <div class="space-y-3">
                        <div>
                            <label class="block text-xs font-semibold text-gray-600 mb-1">Free Shipping Threshold (KES)</label>
                            <input type="number" name="free_shipping_threshold"
                                   value="<?= htmlspecialchars($s['free_shipping_threshold']) ?>"
                                   min="0" step="100"
                                   class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-orange-300">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-600 mb-1">Minimum Order Amount (KES)</label>
                            <input type="number" name="min_order_amount"
                                   value="<?= htmlspecialchars($s['min_order_amount']) ?>"
                                   min="0" step="50"
                                   class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-orange-300">
                        </div>
                    </div>
                </div>

                <!-- Store URL -->
                <div class="bg-gray-50 rounded-2xl border border-gray-100 p-4">
                    <h3 class="text-xs font-bold text-gray-500 uppercase tracking-wide mb-2">Store URL</h3>
                    <div class="flex items-center gap-2">
                        <code class="text-xs text-gray-600 bg-white border border-gray-200 rounded-lg px-2 py-1.5 flex-1 truncate">
                            <?= htmlspecialchars($store_url) ?>
                        </code>
                        <button type="button" 
                                onclick="navigator.clipboard.writeText('<?= htmlspecialchars($store_url) ?>')"
                                class="px-3 py-1.5 text-xs rounded-lg bg-white border border-gray-200 hover:bg-gray-50 transition text-gray-600">
                            <i class="fas fa-copy"></i>
                        </button>
                    </div>
                </div>

                <!-- Quick Link to Page Builder -->
                <div class="bg-orange-50 rounded-2xl border border-orange-200 p-4">
                    <div class="flex items-start gap-3">
                        <div class="text-xl">📐</div>
                        <div class="flex-1">
                            <h3 class="text-sm font-bold text-gray-800">Page Builder</h3>
                            <p class="text-xs text-gray-600 mt-0.5">Create custom sections for your storefront</p>
                            <a href="blocks.php" 
                               class="inline-flex items-center gap-1.5 mt-2 text-xs font-bold px-3 py-1.5 rounded-lg transition"
                               style="background:<?= $brand_color ?>;color:white;">
                                Manage Blocks →
                            </a>
                            <?php if ($blocks_count > 0): ?>
                                <span class="ml-2 text-xs text-gray-500">(<?= $blocks_count ?> active)</span>
                            <?php else: ?>
                                <span class="ml-2 text-xs text-orange-500">(No blocks yet)</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </form>
    </div>
    </main>
</div>
</body>
</html>