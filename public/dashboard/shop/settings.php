<?php
/**
 * Store Admin — Store Settings (Shopify-style)
 * @version 3.0
 */
require_once __DIR__ . '/../../../src/paths.php';
safe_require('auth.php', 'src', true);
safe_require('db.php', 'src', true);
safe_require('functions.php', 'src', true);
require_login();

$pdo = get_db_connection();
$tenant_id = (int)($_SESSION['tenant_id'] ?? get_current_tenant_id() ?? 0);

if (!$tenant_id) {
    echo '<div class="flex items-center justify-center h-screen text-slate-400">Access Denied — no tenant context</div>';
    exit;
}

// Load store settings
$settings = [];
$stmt = $pdo->prepare("SELECT setting_key, setting_value FROM storefront_settings WHERE tenant_id = ?");
$stmt->execute([$tenant_id]);
while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $settings[$r['setting_key']] = $r['setting_value'];
}

$brand_color = $settings['primary_color'] ?? '#f68b1e';
$store_name = $settings['store_name'] ?? $_SESSION['company_name'] ?? 'My Store';
$currency = $settings['currency'] ?? 'KES';

$msg = '';
$msg_type = 'success';

// ── Helper to upsert settings ──
function sfSave(PDO $pdo, int $tid, string $key, string $val): void {
    $stmt = $pdo->prepare(
        "INSERT INTO storefront_settings (tenant_id, setting_key, setting_value) 
         VALUES (?, ?, ?) 
         ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = NOW()"
    );
    $stmt->execute([$tid, $key, $val]);
}

// ── Handle POST ──
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $section = $_POST['section'] ?? '';
    try {
        $pdo->beginTransaction();
        
        if ($section === 'general') {
            $fields = ['site_title', 'site_description', 'currency', 'whatsapp_number', 
                       'min_order_amount', 'free_shipping_threshold', 'store_name', 
                       'store_email', 'store_phone', 'store_address'];
            foreach ($fields as $k) {
                sfSave($pdo, $tenant_id, $k, trim($_POST[$k] ?? ''));
            }
            $store_name = trim($_POST['store_name'] ?? $store_name);
            
        } elseif ($section === 'design') {
            $fields = ['primary_color', 'secondary_color', 'logo_url', 'favicon_url', 
                       'header_bg_color', 'header_text_color', 'button_style'];
            foreach ($fields as $k) {
                sfSave($pdo, $tenant_id, $k, trim($_POST[$k] ?? ''));
            }
            
        } elseif ($section === 'seo') {
            $fields = ['meta_title', 'meta_description', 'og_image', 'og_title', 
                       'og_description', 'twitter_card', 'keywords'];
            foreach ($fields as $k) {
                sfSave($pdo, $tenant_id, $k, trim($_POST[$k] ?? ''));
            }
            
        } elseif ($section === 'payment') {
            $fields = ['mpesa_enabled', 'cod_enabled', 'stripe_enabled', 
                       'stripe_publishable_key', 'stripe_secret_key', 
                       'mpesa_paybill', 'mpesa_account', 'mpesa_consumer_key', 
                       'mpesa_consumer_secret', 'paypal_enabled', 'paypal_client_id', 
                       'paypal_secret', 'bank_transfer_enabled', 'bank_details'];
            foreach ($fields as $k) {
                $val = $_POST[$k] ?? '0';
                if (in_array($k, ['mpesa_enabled', 'cod_enabled', 'stripe_enabled', 
                                  'paypal_enabled', 'bank_transfer_enabled'])) {
                    $val = isset($_POST[$k]) ? '1' : '0';
                }
                sfSave($pdo, $tenant_id, $k, trim($val));
            }
            
        } elseif ($section === 'social') {
            $fields = ['facebook_url', 'instagram_url', 'twitter_url', 'tiktok_url', 
                       'youtube_url', 'linkedin_url', 'pinterest_url', 'whatsapp_number'];
            foreach ($fields as $k) {
                sfSave($pdo, $tenant_id, $k, trim($_POST[$k] ?? ''));
            }
            
        } elseif ($section === 'email') {
            $fields = ['email_from_name', 'email_from_address', 'email_order_confirmation', 
                       'email_order_shipped', 'email_order_delivered', 'email_footer_text'];
            foreach ($fields as $k) {
                sfSave($pdo, $tenant_id, $k, trim($_POST[$k] ?? ''));
            }
            
        } elseif ($section === 'advanced') {
            $fields = ['enable_guest_checkout', 'enable_wishlist', 'enable_reviews', 
                       'enable_compare', 'enable_currency_switcher', 'enable_language_switcher',
                       'default_language', 'default_country', 'enable_cookies', 
                       'enable_analytics', 'analytics_code', 'enable_terms', 
                       'terms_page_url', 'privacy_page_url', 'return_policy_url'];
            foreach ($fields as $k) {
                $val = $_POST[$k] ?? '0';
                if (in_array($k, ['enable_guest_checkout', 'enable_wishlist', 'enable_reviews', 
                                  'enable_compare', 'enable_currency_switcher', 'enable_language_switcher',
                                  'enable_cookies', 'enable_analytics', 'enable_terms'])) {
                    $val = isset($_POST[$k]) ? '1' : '0';
                }
                sfSave($pdo, $tenant_id, $k, trim($val));
            }
        }
        
        $pdo->commit();
        
        // Reload settings
        $stmt = $pdo->prepare("SELECT setting_key, setting_value FROM storefront_settings WHERE tenant_id = ?");
        $stmt->execute([$tenant_id]);
        $settings = [];
        while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $settings[$r['setting_key']] = $r['setting_value'];
        }
        
        $msg = 'Settings saved successfully!';
        $msg_type = 'success';
    } catch (Exception $e) {
        $pdo->rollBack();
        $msg = 'Error: ' . $e->getMessage();
        $msg_type = 'error';
    }
}

// ── Helpers ──
$s = function(string $k, string $def = '') use ($settings): string {
    return htmlspecialchars($settings[$k] ?? $def);
};
$checked = function(string $k, string $def = '0') use ($settings): string {
    return ($settings[$k] ?? $def) === '1' ? 'checked' : '';
};
$selected = function(string $k, string $val) use ($settings): string {
    return ($settings[$k] ?? '') === $val ? 'selected' : '';
};

// ── Stats ──
$blocks_count = 0;
try {
    $st = $pdo->prepare("SELECT COUNT(*) FROM storefront_blocks WHERE tenant_id = ? AND is_active = 1");
    $st->execute([$tenant_id]);
    $blocks_count = (int)$st->fetchColumn();
} catch (Exception $e) {}

$stats = ['pending_orders' => 0, 'reviews_pending' => 0];
try {
    $r = $pdo->prepare("SELECT COUNT(*) FROM online_orders WHERE tenant_id = ? AND status = 'pending'");
    $r->execute([$tenant_id]);
    $stats['pending_orders'] = (int)$r->fetchColumn();
    $r = $pdo->prepare("SELECT COUNT(*) FROM product_reviews WHERE tenant_id = ? AND status = 'pending'");
    $r->execute([$tenant_id]);
    $stats['reviews_pending'] = (int)$r->fetchColumn();
} catch (Exception $e) {}

$storeUrl = storefront_url($tenant_id);
$page_title = 'Store Settings';
$active_tab = $_GET['tab'] ?? 'general';
$page_icon = 'fa-sliders-h';

ob_start();
?>

<div class="space-y-6">

    <!-- Header -->
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
        <div>
            <h1 class="text-2xl font-bold text-white flex items-center gap-3">
                <i class="fas fa-sliders-h text-amber-400 text-xl"></i>
                Store Settings
            </h1>
            <p class="text-sm text-slate-500 mt-1">Customize your online store appearance and configuration</p>
        </div>
        <div class="flex items-center gap-3">
            <a href="<?= htmlspecialchars($storeUrl) ?>" target="_blank" class="inline-flex items-center gap-2 px-4 py-2 rounded-xl text-white text-xs font-bold transition-all duration-200 hover:opacity-90 shadow-lg" style="background: <?= $brand_color ?>">
                <i class="fas fa-eye"></i> Preview Store
            </a>
        </div>
    </div>

    <!-- Messages -->
    <?php if ($msg): ?>
    <div class="px-4 py-3 rounded-xl text-sm font-medium flex items-center gap-2 <?= $msg_type === 'success' ? 'bg-emerald-500/10 border border-emerald-500/30 text-emerald-400' : 'bg-red-500/10 border border-red-500/30 text-red-400' ?>">
        <i class="fas <?= $msg_type === 'success' ? 'fa-check-circle' : 'fa-times-circle' ?>"></i>
        <?= htmlspecialchars($msg) ?>
    </div>
    <?php endif; ?>

    <!-- Tabs & Content -->
    <div class="flex flex-col md:flex-row gap-6">
        
        <!-- Sidebar Tabs -->
        <div class="md:w-52 flex-shrink-0">
            <nav class="space-y-1 bg-slate-800/40 border border-slate-700/60 rounded-xl p-1.5">
                <?php 
                $tabs = [
                    'general' => ['fa-store', 'General'],
                    'design' => ['fa-palette', 'Design'],
                    'seo' => ['fa-magnifying-glass', 'SEO'],
                    'payment' => ['fa-credit-card', 'Payments'],
                    'social' => ['fa-share-nodes', 'Social'],
                    'email' => ['fa-envelope', 'Email'],
                    'advanced' => ['fa-cogs', 'Advanced']
                ];
                foreach ($tabs as $tv => [$ico, $tl]): 
                    $active = $active_tab === $tv;
                ?>
                <a href="?tab=<?= $tv ?>" 
                   class="flex items-center gap-2.5 px-3 py-2.5 rounded-lg text-sm font-medium transition-all duration-200 <?= $active ? 'bg-amber-500/10 border border-amber-500/30 text-amber-400 shadow-sm' : 'border border-transparent text-slate-400 hover:bg-slate-700/30 hover:text-white' ?>">
                    <i class="fas <?= $ico ?> w-4 text-center <?= $active ? 'text-amber-400' : 'text-slate-500' ?>"></i> 
                    <?= $tl ?>
                    <?php if ($active): ?>
                    <span class="ml-auto w-1.5 h-1.5 rounded-full bg-amber-400 animate-pulse"></span>
                    <?php endif; ?>
                </a>
                <?php endforeach; ?>
            </nav>
        </div>

        <!-- Content Area -->
        <div class="flex-1">

            <!-- ============================================================ -->
            <!-- GENERAL TAB -->
            <!-- ============================================================ -->
            <?php if ($active_tab === 'general'): ?>
            <form method="POST">
                <input type="hidden" name="section" value="general">
                <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-6 space-y-5">
                    <h2 class="font-bold text-white text-base flex items-center gap-2">
                        <i class="fas fa-store text-amber-400 text-sm"></i>
                        General Settings
                    </h2>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-medium text-slate-400 mb-1">Store Name</label>
                            <input type="text" name="store_name" value="<?= $s('store_name', $store_name) ?>" 
                                   class="w-full px-3 py-2.5 text-sm bg-slate-900/50 border border-slate-700/60 rounded-lg text-slate-200 focus:outline-none focus:border-amber-500/50 focus:ring-1 focus:ring-amber-500/20 transition-all" 
                                   placeholder="My Store">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-400 mb-1">Store Email</label>
                            <input type="email" name="store_email" value="<?= $s('store_email') ?>" 
                                   class="w-full px-3 py-2.5 text-sm bg-slate-900/50 border border-slate-700/60 rounded-lg text-slate-200 focus:outline-none focus:border-amber-500/50 focus:ring-1 focus:ring-amber-500/20 transition-all" 
                                   placeholder="contact@store.com">
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-slate-400 mb-1">Store Description</label>
                        <textarea name="site_description" rows="3" 
                                  class="w-full px-3 py-2.5 text-sm bg-slate-900/50 border border-slate-700/60 rounded-lg text-slate-200 focus:outline-none focus:border-amber-500/50 focus:ring-1 focus:ring-amber-500/20 transition-all resize-y" 
                                  placeholder="Describe your store"><?= $s('site_description') ?></textarea>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-medium text-slate-400 mb-1">Currency</label>
                            <select name="currency" class="w-full px-3 py-2.5 text-sm bg-slate-900/50 border border-slate-700/60 rounded-lg text-slate-200 focus:outline-none focus:border-amber-500/50 focus:ring-1 focus:ring-amber-500/20 transition-all">
                                <?php foreach (['KES' => 'KES — Kenyan Shilling', 'USD' => 'USD — US Dollar', 'EUR' => 'EUR — Euro', 'GBP' => 'GBP — British Pound', 'TZS' => 'TZS — Tanzanian Shilling', 'UGX' => 'UGX — Ugandan Shilling', 'ZAR' => 'ZAR — South African Rand'] as $cv => $cl): ?>
                                <option value="<?= $cv ?>" <?= ($settings['currency'] ?? 'KES') === $cv ? 'selected' : '' ?>><?= $cl ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-400 mb-1">WhatsApp Number</label>
                            <input type="text" name="whatsapp_number" value="<?= $s('whatsapp_number') ?>" 
                                   placeholder="+254700000000"
                                   class="w-full px-3 py-2.5 text-sm bg-slate-900/50 border border-slate-700/60 rounded-lg text-slate-200 focus:outline-none focus:border-amber-500/50 focus:ring-1 focus:ring-amber-500/20 transition-all">
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-medium text-slate-400 mb-1">Minimum Order Amount (<?= $s('currency', 'KES') ?>)</label>
                            <input type="number" name="min_order_amount" value="<?= $s('min_order_amount', '0') ?>" 
                                   min="0" step="50"
                                   class="w-full px-3 py-2.5 text-sm bg-slate-900/50 border border-slate-700/60 rounded-lg text-slate-200 focus:outline-none focus:border-amber-500/50 focus:ring-1 focus:ring-amber-500/20 transition-all">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-400 mb-1">Free Shipping Above (<?= $s('currency', 'KES') ?>)</label>
                            <input type="number" name="free_shipping_threshold" value="<?= $s('free_shipping_threshold', '0') ?>" 
                                   min="0" step="100"
                                   class="w-full px-3 py-2.5 text-sm bg-slate-900/50 border border-slate-700/60 rounded-lg text-slate-200 focus:outline-none focus:border-amber-500/50 focus:ring-1 focus:ring-amber-500/20 transition-all">
                            <p class="text-[10px] text-slate-500 mt-1">Set to 0 to disable free shipping</p>
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-slate-400 mb-1">Store Address</label>
                        <textarea name="store_address" rows="2" 
                                  class="w-full px-3 py-2.5 text-sm bg-slate-900/50 border border-slate-700/60 rounded-lg text-slate-200 focus:outline-none focus:border-amber-500/50 focus:ring-1 focus:ring-amber-500/20 transition-all resize-y" 
                                  placeholder="123 Main St, City, Country"><?= $s('store_address') ?></textarea>
                    </div>

                    <button type="submit" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl text-white text-sm font-bold transition-all duration-200 hover:opacity-90 shadow-lg" style="background: <?= $brand_color ?>">
                        <i class="fas fa-save text-xs"></i> Save General Settings
                    </button>
                </div>
            </form>

            <!-- ============================================================ -->
            <!-- DESIGN TAB -->
            <!-- ============================================================ -->
            <?php elseif ($active_tab === 'design'): ?>
            <form method="POST">
                <input type="hidden" name="section" value="design">
                <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-6 space-y-5">
                    <h2 class="font-bold text-white text-base flex items-center gap-2">
                        <i class="fas fa-palette text-amber-400 text-sm"></i>
                        Design &amp; Branding
                    </h2>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-medium text-slate-400 mb-1">Primary Color</label>
                            <div class="flex items-center gap-2">
                                <input type="color" name="primary_color" value="<?= $s('primary_color', '#f68b1e') ?>" 
                                       class="w-12 h-10 border border-slate-700/60 rounded-lg cursor-pointer bg-slate-900/50"
                                       oninput="document.getElementById('primaryHex').value=this.value">
                                <input type="text" id="primaryHex" value="<?= $s('primary_color', '#f68b1e') ?>" 
                                       class="flex-1 px-3 py-2.5 text-sm bg-slate-900/50 border border-slate-700/60 rounded-lg text-slate-200 focus:outline-none focus:border-amber-500/50 focus:ring-1 focus:ring-amber-500/20 transition-all font-mono">
                            </div>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-400 mb-1">Secondary Color</label>
                            <div class="flex items-center gap-2">
                                <input type="color" name="secondary_color" value="<?= $s('secondary_color', '#1a1a2e') ?>" 
                                       class="w-12 h-10 border border-slate-700/60 rounded-lg cursor-pointer bg-slate-900/50"
                                       oninput="document.getElementById('secondaryHex').value=this.value">
                                <input type="text" id="secondaryHex" value="<?= $s('secondary_color', '#1a1a2e') ?>" 
                                       class="flex-1 px-3 py-2.5 text-sm bg-slate-900/50 border border-slate-700/60 rounded-lg text-slate-200 focus:outline-none focus:border-amber-500/50 focus:ring-1 focus:ring-amber-500/20 transition-all font-mono">
                            </div>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-medium text-slate-400 mb-1">Header Background Color</label>
                            <div class="flex items-center gap-2">
                                <input type="color" name="header_bg_color" value="<?= $s('header_bg_color', '#ffffff') ?>" 
                                       class="w-12 h-10 border border-slate-700/60 rounded-lg cursor-pointer bg-slate-900/50"
                                       oninput="document.getElementById('headerBgHex').value=this.value">
                                <input type="text" id="headerBgHex" value="<?= $s('header_bg_color', '#ffffff') ?>" 
                                       class="flex-1 px-3 py-2.5 text-sm bg-slate-900/50 border border-slate-700/60 rounded-lg text-slate-200 focus:outline-none focus:border-amber-500/50 focus:ring-1 focus:ring-amber-500/20 transition-all font-mono">
                            </div>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-400 mb-1">Button Style</label>
                            <select name="button_style" class="w-full px-3 py-2.5 text-sm bg-slate-900/50 border border-slate-700/60 rounded-lg text-slate-200 focus:outline-none focus:border-amber-500/50 focus:ring-1 focus:ring-amber-500/20 transition-all">
                                <option value="rounded" <?= $selected('button_style', 'rounded') ?>>Rounded</option>
                                <option value="sharp" <?= $selected('button_style', 'sharp') ?>>Sharp / Square</option>
                                <option value="pill" <?= $selected('button_style', 'pill') ?>>Pill / Capsule</option>
                            </select>
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-slate-400 mb-1">Logo URL</label>
                        <input type="url" name="logo_url" value="<?= $s('logo_url') ?>" 
                               placeholder="https://example.com/logo.png"
                               class="w-full px-3 py-2.5 text-sm bg-slate-900/50 border border-slate-700/60 rounded-lg text-slate-200 focus:outline-none focus:border-amber-500/50 focus:ring-1 focus:ring-amber-500/20 transition-all">
                        <?php if ($settings['logo_url'] ?? ''): ?>
                        <div class="mt-2 p-2 bg-slate-900/50 rounded-lg border border-slate-700/40 inline-block">
                            <img src="<?= $s('logo_url') ?>" class="h-12 object-contain" onerror="this.parentElement.innerHTML='<span class=\'text-xs text-slate-500\'>Invalid image URL</span>'">
                        </div>
                        <?php endif; ?>
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-slate-400 mb-1">Favicon URL</label>
                        <input type="url" name="favicon_url" value="<?= $s('favicon_url') ?>" 
                               placeholder="https://example.com/favicon.ico"
                               class="w-full px-3 py-2.5 text-sm bg-slate-900/50 border border-slate-700/60 rounded-lg text-slate-200 focus:outline-none focus:border-amber-500/50 focus:ring-1 focus:ring-amber-500/20 transition-all">
                    </div>

                    <button type="submit" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl text-white text-sm font-bold transition-all duration-200 hover:opacity-90 shadow-lg" style="background: <?= $brand_color ?>">
                        <i class="fas fa-save text-xs"></i> Save Design Settings
                    </button>
                </div>
            </form>

            <!-- ============================================================ -->
            <!-- SEO TAB -->
            <!-- ============================================================ -->
            <?php elseif ($active_tab === 'seo'): ?>
            <form method="POST">
                <input type="hidden" name="section" value="seo">
                <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-6 space-y-5">
                    <h2 class="font-bold text-white text-base flex items-center gap-2">
                        <i class="fas fa-magnifying-glass text-amber-400 text-sm"></i>
                        SEO Settings
                    </h2>

                    <div>
                        <label class="block text-xs font-medium text-slate-400 mb-1">Meta Title <span class="text-slate-500 font-normal">(max 60 chars)</span></label>
                        <input type="text" name="meta_title" value="<?= $s('meta_title') ?>" 
                               maxlength="60"
                               class="w-full px-3 py-2.5 text-sm bg-slate-900/50 border border-slate-700/60 rounded-lg text-slate-200 focus:outline-none focus:border-amber-500/50 focus:ring-1 focus:ring-amber-500/20 transition-all" 
                               placeholder="My Store - Best Products Online">
                        <div class="text-[10px] text-slate-500 mt-1"><span id="metaTitleCount"><?= strlen($s('meta_title')) ?></span>/60 characters</div>
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-slate-400 mb-1">Meta Description <span class="text-slate-500 font-normal">(max 160 chars)</span></label>
                        <textarea name="meta_description" rows="3" maxlength="160"
                                  class="w-full px-3 py-2.5 text-sm bg-slate-900/50 border border-slate-700/60 rounded-lg text-slate-200 focus:outline-none focus:border-amber-500/50 focus:ring-1 focus:ring-amber-500/20 transition-all resize-y" 
                                  placeholder="Discover amazing products at great prices"><?= $s('meta_description') ?></textarea>
                        <div class="text-[10px] text-slate-500 mt-1"><span id="metaDescCount"><?= strlen($s('meta_description')) ?></span>/160 characters</div>
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-slate-400 mb-1">Keywords (comma separated)</label>
                        <input type="text" name="keywords" value="<?= $s('keywords') ?>" 
                               placeholder="online store, best deals, shopping"
                               class="w-full px-3 py-2.5 text-sm bg-slate-900/50 border border-slate-700/60 rounded-lg text-slate-200 focus:outline-none focus:border-amber-500/50 focus:ring-1 focus:ring-amber-500/20 transition-all">
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-medium text-slate-400 mb-1">OG Title</label>
                            <input type="text" name="og_title" value="<?= $s('og_title') ?>" 
                                   placeholder="Social share title"
                                   class="w-full px-3 py-2.5 text-sm bg-slate-900/50 border border-slate-700/60 rounded-lg text-slate-200 focus:outline-none focus:border-amber-500/50 focus:ring-1 focus:ring-amber-500/20 transition-all">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-400 mb-1">OG Description</label>
                            <input type="text" name="og_description" value="<?= $s('og_description') ?>" 
                                   placeholder="Social share description"
                                   class="w-full px-3 py-2.5 text-sm bg-slate-900/50 border border-slate-700/60 rounded-lg text-slate-200 focus:outline-none focus:border-amber-500/50 focus:ring-1 focus:ring-amber-500/20 transition-all">
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-slate-400 mb-1">OG Image URL</label>
                        <input type="url" name="og_image" value="<?= $s('og_image') ?>" 
                               placeholder="https://example.com/share-image.jpg"
                               class="w-full px-3 py-2.5 text-sm bg-slate-900/50 border border-slate-700/60 rounded-lg text-slate-200 focus:outline-none focus:border-amber-500/50 focus:ring-1 focus:ring-amber-500/20 transition-all">
                        <?php if ($settings['og_image'] ?? ''): ?>
                        <div class="mt-2">
                            <img src="<?= $s('og_image') ?>" class="h-16 rounded-lg border border-slate-700/40 object-cover" onerror="this.remove()">
                        </div>
                        <?php endif; ?>
                    </div>

                    <button type="submit" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl text-white text-sm font-bold transition-all duration-200 hover:opacity-90 shadow-lg" style="background: <?= $brand_color ?>">
                        <i class="fas fa-save text-xs"></i> Save SEO Settings
                    </button>
                </div>
            </form>

            <script>
            document.addEventListener('DOMContentLoaded', function() {
                const metaTitle = document.querySelector('input[name="meta_title"]');
                const metaDesc = document.querySelector('textarea[name="meta_description"]');
                if (metaTitle) {
                    metaTitle.addEventListener('input', function() {
                        document.getElementById('metaTitleCount').textContent = this.value.length;
                    });
                }
                if (metaDesc) {
                    metaDesc.addEventListener('input', function() {
                        document.getElementById('metaDescCount').textContent = this.value.length;
                    });
                }
            });
            </script>

            <!-- ============================================================ -->
            <!-- PAYMENT TAB -->
            <!-- ============================================================ -->
            <?php elseif ($active_tab === 'payment'): ?>
            <form method="POST">
                <input type="hidden" name="section" value="payment">
                <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-6 space-y-5">
                    <h2 class="font-bold text-white text-base flex items-center gap-2">
                        <i class="fas fa-credit-card text-amber-400 text-sm"></i>
                        Payment Methods
                    </h2>

                    <!-- M-Pesa -->
                    <div class="flex items-center justify-between p-4 rounded-xl border border-slate-700/60 bg-slate-900/50">
                        <div class="flex items-center gap-3">
                            <i class="fas fa-mobile-alt text-green-400 text-xl w-6 text-center"></i>
                            <div><p class="font-bold text-white text-sm">M-Pesa STK Push</p></div>
                        </div>
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input type="hidden" name="mpesa_enabled" value="0">
                            <input type="checkbox" name="mpesa_enabled" value="1" <?= $checked('mpesa_enabled') ?> class="sr-only peer">
                            <div class="w-11 h-6 bg-slate-700 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-amber-500"></div>
                        </label>
                    </div>

                    <!-- M-Pesa Config -->
                    <div class="border-l-2 border-amber-500/30 pl-4 ml-2 space-y-3">
                        <p class="text-xs font-bold text-slate-500 uppercase">M-Pesa Configuration</p>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-medium text-slate-400 mb-1">Paybill / Till Number</label>
                                <input type="text" name="mpesa_paybill" value="<?= $s('mpesa_paybill') ?>" 
                                       placeholder="e.g. 400001"
                                       class="w-full px-3 py-2 text-sm bg-slate-900/50 border border-slate-700/60 rounded-lg text-slate-200 focus:outline-none focus:border-amber-500/50 focus:ring-1 focus:ring-amber-500/20 transition-all">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-slate-400 mb-1">Account Number</label>
                                <input type="text" name="mpesa_account" value="<?= $s('mpesa_account') ?>" 
                                       placeholder="e.g. Account"
                                       class="w-full px-3 py-2 text-sm bg-slate-900/50 border border-slate-700/60 rounded-lg text-slate-200 focus:outline-none focus:border-amber-500/50 focus:ring-1 focus:ring-amber-500/20 transition-all">
                            </div>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-medium text-slate-400 mb-1">Consumer Key</label>
                                <input type="text" name="mpesa_consumer_key" value="<?= $s('mpesa_consumer_key') ?>" 
                                       placeholder="Consumer Key"
                                       class="w-full px-3 py-2 text-sm bg-slate-900/50 border border-slate-700/60 rounded-lg text-slate-200 focus:outline-none focus:border-amber-500/50 focus:ring-1 focus:ring-amber-500/20 transition-all font-mono">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-slate-400 mb-1">Consumer Secret</label>
                                <input type="password" name="mpesa_consumer_secret" value="<?= $s('mpesa_consumer_secret') ?>" 
                                       placeholder="Consumer Secret"
                                       class="w-full px-3 py-2 text-sm bg-slate-900/50 border border-slate-700/60 rounded-lg text-slate-200 focus:outline-none focus:border-amber-500/50 focus:ring-1 focus:ring-amber-500/20 transition-all font-mono">
                            </div>
                        </div>
                    </div>

                    <!-- Cash on Delivery -->
                    <div class="flex items-center justify-between p-4 rounded-xl border border-slate-700/60 bg-slate-900/50">
                        <div class="flex items-center gap-3">
                            <i class="fas fa-money-bill-wave text-green-400 text-xl w-6 text-center"></i>
                            <div><p class="font-bold text-white text-sm">Cash on Delivery</p></div>
                        </div>
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input type="hidden" name="cod_enabled" value="0">
                            <input type="checkbox" name="cod_enabled" value="1" <?= $checked('cod_enabled') ?> class="sr-only peer">
                            <div class="w-11 h-6 bg-slate-700 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-amber-500"></div>
                        </label>
                    </div>

                    <!-- Stripe -->
                    <div class="flex items-center justify-between p-4 rounded-xl border border-slate-700/60 bg-slate-900/50">
                        <div class="flex items-center gap-3">
                            <i class="fab fa-stripe text-purple-400 text-xl w-6 text-center"></i>
                            <div><p class="font-bold text-white text-sm">Stripe (Card Payments)</p></div>
                        </div>
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input type="hidden" name="stripe_enabled" value="0">
                            <input type="checkbox" name="stripe_enabled" value="1" <?= $checked('stripe_enabled') ?> class="sr-only peer">
                            <div class="w-11 h-6 bg-slate-700 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-amber-500"></div>
                        </label>
                    </div>

                    <!-- Stripe Config -->
                    <div class="border-l-2 border-purple-500/30 pl-4 ml-2 space-y-3">
                        <p class="text-xs font-bold text-slate-500 uppercase">Stripe Configuration</p>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-medium text-slate-400 mb-1">Publishable Key</label>
                                <input type="text" name="stripe_publishable_key" value="<?= $s('stripe_publishable_key') ?>" 
                                       placeholder="pk_live_..."
                                       class="w-full px-3 py-2 text-sm bg-slate-900/50 border border-slate-700/60 rounded-lg text-slate-200 focus:outline-none focus:border-amber-500/50 focus:ring-1 focus:ring-amber-500/20 transition-all font-mono">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-slate-400 mb-1">Secret Key</label>
                                <input type="password" name="stripe_secret_key" value="<?= $s('stripe_secret_key') ?>" 
                                       placeholder="sk_live_..."
                                       class="w-full px-3 py-2 text-sm bg-slate-900/50 border border-slate-700/60 rounded-lg text-slate-200 focus:outline-none focus:border-amber-500/50 focus:ring-1 focus:ring-amber-500/20 transition-all font-mono">
                            </div>
                        </div>
                    </div>

                    <!-- PayPal -->
                    <div class="flex items-center justify-between p-4 rounded-xl border border-slate-700/60 bg-slate-900/50">
                        <div class="flex items-center gap-3">
                            <i class="fab fa-paypal text-blue-400 text-xl w-6 text-center"></i>
                            <div><p class="font-bold text-white text-sm">PayPal</p></div>
                        </div>
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input type="hidden" name="paypal_enabled" value="0">
                            <input type="checkbox" name="paypal_enabled" value="1" <?= $checked('paypal_enabled') ?> class="sr-only peer">
                            <div class="w-11 h-6 bg-slate-700 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-amber-500"></div>
                        </label>
                    </div>

                    <!-- PayPal Config -->
                    <div class="border-l-2 border-blue-500/30 pl-4 ml-2 space-y-3">
                        <p class="text-xs font-bold text-slate-500 uppercase">PayPal Configuration</p>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-medium text-slate-400 mb-1">Client ID</label>
                                <input type="text" name="paypal_client_id" value="<?= $s('paypal_client_id') ?>" 
                                       placeholder="Client ID"
                                       class="w-full px-3 py-2 text-sm bg-slate-900/50 border border-slate-700/60 rounded-lg text-slate-200 focus:outline-none focus:border-amber-500/50 focus:ring-1 focus:ring-amber-500/20 transition-all font-mono">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-slate-400 mb-1">Secret</label>
                                <input type="password" name="paypal_secret" value="<?= $s('paypal_secret') ?>" 
                                       placeholder="Secret"
                                       class="w-full px-3 py-2 text-sm bg-slate-900/50 border border-slate-700/60 rounded-lg text-slate-200 focus:outline-none focus:border-amber-500/50 focus:ring-1 focus:ring-amber-500/20 transition-all font-mono">
                            </div>
                        </div>
                    </div>

                    <!-- Bank Transfer -->
                    <div class="flex items-center justify-between p-4 rounded-xl border border-slate-700/60 bg-slate-900/50">
                        <div class="flex items-center gap-3">
                            <i class="fas fa-university text-yellow-400 text-xl w-6 text-center"></i>
                            <div><p class="font-bold text-white text-sm">Bank Transfer</p></div>
                        </div>
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input type="hidden" name="bank_transfer_enabled" value="0">
                            <input type="checkbox" name="bank_transfer_enabled" value="1" <?= $checked('bank_transfer_enabled') ?> class="sr-only peer">
                            <div class="w-11 h-6 bg-slate-700 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-amber-500"></div>
                        </label>
                    </div>

                    <!-- Bank Details -->
                    <div class="border-l-2 border-yellow-500/30 pl-4 ml-2">
                        <label class="block text-xs font-medium text-slate-400 mb-1">Bank Details (shown at checkout)</label>
                        <textarea name="bank_details" rows="3" 
                                  class="w-full px-3 py-2.5 text-sm bg-slate-900/50 border border-slate-700/60 rounded-lg text-slate-200 focus:outline-none focus:border-amber-500/50 focus:ring-1 focus:ring-amber-500/20 transition-all resize-y" 
                                  placeholder="Bank: Equity Bank&#10;Account Name: My Store Ltd&#10;Account Number: 1234567890"><?= $s('bank_details') ?></textarea>
                    </div>

                    <button type="submit" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl text-white text-sm font-bold transition-all duration-200 hover:opacity-90 shadow-lg" style="background: <?= $brand_color ?>">
                        <i class="fas fa-save text-xs"></i> Save Payment Settings
                    </button>
                </div>
            </form>

            <!-- ============================================================ -->
            <!-- SOCIAL TAB -->
            <!-- ============================================================ -->
            <?php elseif ($active_tab === 'social'): ?>
            <form method="POST">
                <input type="hidden" name="section" value="social">
                <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-6 space-y-5">
                    <h2 class="font-bold text-white text-base flex items-center gap-2">
                        <i class="fas fa-share-nodes text-amber-400 text-sm"></i>
                        Social Media Links
                    </h2>

                    <?php 
                    $socialFields = [
                        ['facebook_url', 'fab fa-facebook', 'text-blue-600', 'Facebook', 'https://facebook.com/yourpage'],
                        ['instagram_url', 'fab fa-instagram', 'text-pink-500', 'Instagram', 'https://instagram.com/yourpage'],
                        ['twitter_url', 'fab fa-x-twitter', 'text-white', 'Twitter / X', 'https://twitter.com/yourpage'],
                        ['tiktok_url', 'fab fa-tiktok', 'text-white', 'TikTok', 'https://tiktok.com/@yourpage'],
                        ['youtube_url', 'fab fa-youtube', 'text-red-600', 'YouTube', 'https://youtube.com/@yourpage'],
                        ['linkedin_url', 'fab fa-linkedin', 'text-blue-700', 'LinkedIn', 'https://linkedin.com/company/yourpage'],
                        ['pinterest_url', 'fab fa-pinterest', 'text-red-500', 'Pinterest', 'https://pinterest.com/yourpage']
                    ];
                    foreach ($socialFields as [$field, $icon, $color, $label, $placeholder]): ?>
                    <div class="flex items-center gap-3">
                        <i class="<?= $icon ?> <?= $color ?> text-xl w-6 text-center"></i>
                        <input type="url" name="<?= $field ?>" value="<?= $s($field) ?>" 
                               placeholder="<?= $placeholder ?>"
                               class="flex-1 px-3 py-2.5 text-sm bg-slate-900/50 border border-slate-700/60 rounded-lg text-slate-200 focus:outline-none focus:border-amber-500/50 focus:ring-1 focus:ring-amber-500/20 transition-all">
                    </div>
                    <?php endforeach; ?>

                    <button type="submit" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl text-white text-sm font-bold transition-all duration-200 hover:opacity-90 shadow-lg" style="background: <?= $brand_color ?>">
                        <i class="fas fa-save text-xs"></i> Save Social Links
                    </button>
                </div>
            </form>

            <!-- ============================================================ -->
            <!-- EMAIL TAB -->
            <!-- ============================================================ -->
            <?php elseif ($active_tab === 'email'): ?>
            <form method="POST">
                <input type="hidden" name="section" value="email">
                <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-6 space-y-5">
                    <h2 class="font-bold text-white text-base flex items-center gap-2">
                        <i class="fas fa-envelope text-amber-400 text-sm"></i>
                        Email Settings
                    </h2>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-medium text-slate-400 mb-1">From Name</label>
                            <input type="text" name="email_from_name" value="<?= $s('email_from_name', $store_name) ?>" 
                                   class="w-full px-3 py-2.5 text-sm bg-slate-900/50 border border-slate-700/60 rounded-lg text-slate-200 focus:outline-none focus:border-amber-500/50 focus:ring-1 focus:ring-amber-500/20 transition-all">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-400 mb-1">From Address</label>
                            <input type="email" name="email_from_address" value="<?= $s('email_from_address') ?>" 
                                   class="w-full px-3 py-2.5 text-sm bg-slate-900/50 border border-slate-700/60 rounded-lg text-slate-200 focus:outline-none focus:border-amber-500/50 focus:ring-1 focus:ring-amber-500/20 transition-all">
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-slate-400 mb-1">Order Confirmation Email</label>
                        <textarea name="email_order_confirmation" rows="3" 
                                  class="w-full px-3 py-2.5 text-sm bg-slate-900/50 border border-slate-700/60 rounded-lg text-slate-200 focus:outline-none focus:border-amber-500/50 focus:ring-1 focus:ring-amber-500/20 transition-all resize-y" 
                                  placeholder="Thank you for your order!"><?= $s('email_order_confirmation', "Thank you for your order!\n\nYour order #{{order_number}} has been confirmed.\n\nWe'll notify you when it ships.") ?></textarea>
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-slate-400 mb-1">Order Shipped Email</label>
                        <textarea name="email_order_shipped" rows="3" 
                                  class="w-full px-3 py-2.5 text-sm bg-slate-900/50 border border-slate-700/60 rounded-lg text-slate-200 focus:outline-none focus:border-amber-500/50 focus:ring-1 focus:ring-amber-500/20 transition-all resize-y" 
                                  placeholder="Your order has been shipped!"><?= $s('email_order_shipped', "Great news! Your order #{{order_number}} has been shipped.\n\nTracking: {{tracking_number}}") ?></textarea>
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-slate-400 mb-1">Order Delivered Email</label>
                        <textarea name="email_order_delivered" rows="3" 
                                  class="w-full px-3 py-2.5 text-sm bg-slate-900/50 border border-slate-700/60 rounded-lg text-slate-200 focus:outline-none focus:border-amber-500/50 focus:ring-1 focus:ring-amber-500/20 transition-all resize-y" 
                                  placeholder="Your order has been delivered!"><?= $s('email_order_delivered', "Your order #{{order_number}} has been delivered.\n\nThank you for shopping with us!") ?></textarea>
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-slate-400 mb-1">Email Footer Text</label>
                        <textarea name="email_footer_text" rows="2" 
                                  class="w-full px-3 py-2.5 text-sm bg-slate-900/50 border border-slate-700/60 rounded-lg text-slate-200 focus:outline-none focus:border-amber-500/50 focus:ring-1 focus:ring-amber-500/20 transition-all resize-y" 
                                  placeholder="Thank you for shopping with us!"><?= $s('email_footer_text', "Thank you for shopping with {{store_name}}!") ?></textarea>
                    </div>

                    <button type="submit" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl text-white text-sm font-bold transition-all duration-200 hover:opacity-90 shadow-lg" style="background: <?= $brand_color ?>">
                        <i class="fas fa-save text-xs"></i> Save Email Settings
                    </button>
                </div>
            </form>

            <!-- ============================================================ -->
            <!-- ADVANCED TAB -->
            <!-- ============================================================ -->
            <?php elseif ($active_tab === 'advanced'): ?>
            <form method="POST">
                <input type="hidden" name="section" value="advanced">
                <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-6 space-y-5">
                    <h2 class="font-bold text-white text-base flex items-center gap-2">
                        <i class="fas fa-cogs text-amber-400 text-sm"></i>
                        Advanced Settings
                    </h2>

                    <div class="space-y-3">
                        <label class="flex items-center gap-3 cursor-pointer group">
                            <input type="hidden" name="enable_guest_checkout" value="0">
                            <input type="checkbox" name="enable_guest_checkout" value="1" <?= $checked('enable_guest_checkout', '1') ?> 
                                   class="rounded border-slate-600 bg-slate-800 text-amber-500 focus:ring-amber-500/20">
                            <span class="text-xs text-slate-300 group-hover:text-white transition-colors">Enable Guest Checkout</span>
                        </label>
                        <label class="flex items-center gap-3 cursor-pointer group">
                            <input type="hidden" name="enable_wishlist" value="0">
                            <input type="checkbox" name="enable_wishlist" value="1" <?= $checked('enable_wishlist', '1') ?> 
                                   class="rounded border-slate-600 bg-slate-800 text-amber-500 focus:ring-amber-500/20">
                            <span class="text-xs text-slate-300 group-hover:text-white transition-colors">Enable Wishlist</span>
                        </label>
                        <label class="flex items-center gap-3 cursor-pointer group">
                            <input type="hidden" name="enable_reviews" value="0">
                            <input type="checkbox" name="enable_reviews" value="1" <?= $checked('enable_reviews', '1') ?> 
                                   class="rounded border-slate-600 bg-slate-800 text-amber-500 focus:ring-amber-500/20">
                            <span class="text-xs text-slate-300 group-hover:text-white transition-colors">Enable Product Reviews</span>
                        </label>
                        <label class="flex items-center gap-3 cursor-pointer group">
                            <input type="hidden" name="enable_compare" value="0">
                            <input type="checkbox" name="enable_compare" value="1" <?= $checked('enable_compare', '1') ?> 
                                   class="rounded border-slate-600 bg-slate-800 text-amber-500 focus:ring-amber-500/20">
                            <span class="text-xs text-slate-300 group-hover:text-white transition-colors">Enable Product Compare</span>
                        </label>
                        <label class="flex items-center gap-3 cursor-pointer group">
                            <input type="hidden" name="enable_cookies" value="0">
                            <input type="checkbox" name="enable_cookies" value="1" <?= $checked('enable_cookies', '1') ?> 
                                   class="rounded border-slate-600 bg-slate-800 text-amber-500 focus:ring-amber-500/20">
                            <span class="text-xs text-slate-300 group-hover:text-white transition-colors">Enable Cookie Consent</span>
                        </label>
                        <label class="flex items-center gap-3 cursor-pointer group">
                            <input type="hidden" name="enable_analytics" value="0">
                            <input type="checkbox" name="enable_analytics" value="1" <?= $checked('enable_analytics', '1') ?> 
                                   class="rounded border-slate-600 bg-slate-800 text-amber-500 focus:ring-amber-500/20">
                            <span class="text-xs text-slate-300 group-hover:text-white transition-colors">Enable Analytics</span>
                        </label>
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-slate-400 mb-1">Analytics Code (Google Analytics)</label>
                        <textarea name="analytics_code" rows="3" 
                                  class="w-full px-3 py-2.5 text-sm bg-slate-900/50 border border-slate-700/60 rounded-lg text-slate-200 focus:outline-none focus:border-amber-500/50 focus:ring-1 focus:ring-amber-500/20 transition-all font-mono" 
                                  placeholder="<!-- Google Analytics -->"><?= $s('analytics_code') ?></textarea>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-xs font-medium text-slate-400 mb-1">Default Language</label>
                            <select name="default_language" class="w-full px-3 py-2.5 text-sm bg-slate-900/50 border border-slate-700/60 rounded-lg text-slate-200 focus:outline-none focus:border-amber-500/50 focus:ring-1 focus:ring-amber-500/20 transition-all">
                                <option value="en" <?= $selected('default_language', 'en') ?>>English</option>
                                <option value="sw" <?= $selected('default_language', 'sw') ?>>Swahili</option>
                                <option value="fr" <?= $selected('default_language', 'fr') ?>>French</option>
                                <option value="es" <?= $selected('default_language', 'es') ?>>Spanish</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-400 mb-1">Default Country</label>
                            <select name="default_country" class="w-full px-3 py-2.5 text-sm bg-slate-900/50 border border-slate-700/60 rounded-lg text-slate-200 focus:outline-none focus:border-amber-500/50 focus:ring-1 focus:ring-amber-500/20 transition-all">
                                <option value="KE" <?= $selected('default_country', 'KE') ?>>Kenya</option>
                                <option value="TZ" <?= $selected('default_country', 'TZ') ?>>Tanzania</option>
                                <option value="UG" <?= $selected('default_country', 'UG') ?>>Uganda</option>
                                <option value="ZA" <?= $selected('default_country', 'ZA') ?>>South Africa</option>
                                <option value="NG" <?= $selected('default_country', 'NG') ?>>Nigeria</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-400 mb-1">Enable Currency Switcher</label>
                            <select name="enable_currency_switcher" class="w-full px-3 py-2.5 text-sm bg-slate-900/50 border border-slate-700/60 rounded-lg text-slate-200 focus:outline-none focus:border-amber-500/50 focus:ring-1 focus:ring-amber-500/20 transition-all">
                                <option value="1" <?= $selected('enable_currency_switcher', '1') ?>>Yes</option>
                                <option value="0" <?= $selected('enable_currency_switcher', '0') ?>>No</option>
                            </select>
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-slate-400 mb-1">Enable Language Switcher</label>
                        <select name="enable_language_switcher" class="w-full px-3 py-2.5 text-sm bg-slate-900/50 border border-slate-700/60 rounded-lg text-slate-200 focus:outline-none focus:border-amber-500/50 focus:ring-1 focus:ring-amber-500/20 transition-all">
                            <option value="1" <?= $selected('enable_language_switcher', '1') ?>>Yes</option>
                            <option value="0" <?= $selected('enable_language_switcher', '0') ?>>No</option>
                        </select>
                    </div>

                    <div class="border-t border-slate-700/60 pt-4">
                        <p class="text-xs font-bold text-slate-500 uppercase mb-3">Legal Pages</p>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div>
                                <label class="block text-xs font-medium text-slate-400 mb-1">Terms &amp; Conditions URL</label>
                                <input type="url" name="terms_page_url" value="<?= $s('terms_page_url') ?>" 
                                       placeholder="https://example.com/terms"
                                       class="w-full px-3 py-2 text-sm bg-slate-900/50 border border-slate-700/60 rounded-lg text-slate-200 focus:outline-none focus:border-amber-500/50 focus:ring-1 focus:ring-amber-500/20 transition-all">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-slate-400 mb-1">Privacy Policy URL</label>
                                <input type="url" name="privacy_page_url" value="<?= $s('privacy_page_url') ?>" 
                                       placeholder="https://example.com/privacy"
                                       class="w-full px-3 py-2 text-sm bg-slate-900/50 border border-slate-700/60 rounded-lg text-slate-200 focus:outline-none focus:border-amber-500/50 focus:ring-1 focus:ring-amber-500/20 transition-all">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-slate-400 mb-1">Return Policy URL</label>
                                <input type="url" name="return_policy_url" value="<?= $s('return_policy_url') ?>" 
                                       placeholder="https://example.com/returns"
                                       class="w-full px-3 py-2 text-sm bg-slate-900/50 border border-slate-700/60 rounded-lg text-slate-200 focus:outline-none focus:border-amber-500/50 focus:ring-1 focus:ring-amber-500/20 transition-all">
                            </div>
                        </div>
                    </div>

                    <button type="submit" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl text-white text-sm font-bold transition-all duration-200 hover:opacity-90 shadow-lg" style="background: <?= $brand_color ?>">
                        <i class="fas fa-save text-xs"></i> Save Advanced Settings
                    </button>
                </div>
            </form>
            <?php endif; ?>

        </div>
    </div>
</div>

<?php
$page_content = ob_get_clean();
require_once __DIR__ . '/../../layouts/app.php';
?>