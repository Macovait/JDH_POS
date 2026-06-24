<?php
/**
 * Storefront Customizer - Pure Tailwind CSS Version
 * @version 3.0
 */

require_once __DIR__ . '/../../../src/paths.php';
safe_require('auth.php', 'src', true);
safe_require('db.php', 'src', true);
safe_require('functions.php', 'src', true);
require_login();

$pdo = get_db_connection();
$tenant_id = (int) ($_SESSION['tenant_id'] ?? get_current_tenant_id() ?? 0);

if (!$tenant_id) {
    echo '<div class="p-8 text-center text-red-400">Access Denied</div>';
    exit;
}

$msg = '';

// Handle POST submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fields = [
        'store_name', 'site_title', 'site_description', 'primary_color', 'secondary_color',
        'currency', 'whatsapp_number', 'facebook_url', 'instagram_url', 'tiktok_url',
        'meta_title', 'meta_description', 'free_shipping_threshold', 'min_order_amount',
        'show_reviews', 'show_stock_count', 'online_store_enabled',
        'announcement_bar_text', 'announcement_bar_enabled', 'announcement_bar_color',
        'show_deal_spotlight', 'show_newsletter', 'show_recently_viewed',
        'deal_of_the_day_product_id', 'homepage_sections',
        'font_family', 'heading_font_size', 'body_font_size',
        'button_style', 'button_padding', 'card_style',
        'store_notice_message', 'store_notice_type', 'store_notice_dismissible',
        'store_notice_link_text', 'store_notice_link_url',
        'featured_product_id', 'featured_product_badge', 'featured_product_show_description',
        'featured_product_show_reviews', 'featured_product_image_position',
        'blog_posts_title', 'blog_posts_limit', 'blog_posts_columns',
        'blog_posts_show_date', 'blog_posts_show_excerpt', 'blog_posts_show_read_more',
        'payment_icons_title', 'payment_icons_layout',
        'payment_icons_visa', 'payment_icons_mastercard', 'payment_icons_amex',
        'payment_icons_paypal', 'payment_icons_mpesa',
        'social_links_title', 'social_links_style', 'social_links_align',
        'social_links_facebook', 'social_links_instagram', 'social_links_twitter',
        'social_links_tiktok', 'social_links_youtube', 'social_links_whatsapp',
        'footer_bottom_text', 'footer_show_payment_icons', 'footer_show_social_links',
        'layout_mode', 'layout_container_width', 'layout_sidebar',
        'layout_grid_gap', 'layout_content_padding',
        'header_style', 'header_sticky', 'header_top_bar', 'header_top_bar_text',
        'header_top_bar_bg', 'header_top_bar_text_color',
        'header_show_cart', 'header_show_account', 'header_show_search',
        'header_show_wishlist', 'header_transparent_home',
        'header_bg_color', 'header_text_color',
        'header_mobile_style', 'header_dropdown_style'
    ];

    foreach ($fields as $k) {
        $v = $_POST[$k] ?? '';
        $stmt = $pdo->prepare(
            "INSERT INTO storefront_settings (tenant_id, setting_key, setting_value) 
             VALUES (?, ?, ?) 
             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)"
        );
        $stmt->execute([$tenant_id, $k, $v]);
    }

    // Handle logo upload
    if (!empty($_FILES['logo_file']['tmp_name'])) {
        $ext = strtolower(pathinfo($_FILES['logo_file']['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'svg', 'webp'];
        if (in_array($ext, $allowed)) {
            $dir = PUBLIC_PATH . '/uploads/store/';
            if (!is_dir($dir)) mkdir($dir, 0755, true);
            $filename = 'logo_' . $tenant_id . '.' . $ext;
            move_uploaded_file($_FILES['logo_file']['tmp_name'], $dir . $filename);
            $url = '/uploads/store/' . $filename;
            $stmt = $pdo->prepare(
                "INSERT INTO storefront_settings (tenant_id, setting_key, setting_value) 
                 VALUES (?, 'logo_url', ?) 
                 ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)"
            );
            $stmt->execute([$tenant_id, $url]);
        }
    }

    // Handle favicon upload
    if (!empty($_FILES['favicon_file']['tmp_name'])) {
        $ext = strtolower(pathinfo($_FILES['favicon_file']['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'svg', 'webp', 'ico'];
        if (in_array($ext, $allowed)) {
            $dir = PUBLIC_PATH . '/uploads/store/';
            if (!is_dir($dir)) mkdir($dir, 0755, true);
            $filename = 'favicon_' . $tenant_id . '.' . $ext;
            move_uploaded_file($_FILES['favicon_file']['tmp_name'], $dir . $filename);
            $url = '/uploads/store/' . $filename;
            $stmt = $pdo->prepare(
                "INSERT INTO storefront_settings (tenant_id, setting_key, setting_value) 
                 VALUES (?, 'favicon_url', ?) 
                 ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)"
            );
            $stmt->execute([$tenant_id, $url]);
        }
    }

    $msg = 'Settings saved successfully!';
}

// Load settings
$stmt = $pdo->prepare("SELECT setting_key, setting_value FROM storefront_settings WHERE tenant_id = ?");
$stmt->execute([$tenant_id]);
$settings = [];
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $settings[$row['setting_key']] = $row['setting_value'];
}

// Default values
$defaults = [
    'store_name' => 'My Store',
    'site_title' => '',
    'site_description' => '',
    'primary_color' => '#f68b1e',
    'secondary_color' => '#1a1a2e',
    'currency' => 'KES',
    'whatsapp_number' => '',
    'facebook_url' => '',
    'instagram_url' => '',
    'tiktok_url' => '',
    'meta_title' => '',
    'meta_description' => '',
    'logo_url' => '',
    'favicon_url' => '',
    'free_shipping_threshold' => '0',
    'min_order_amount' => '0',
    'show_reviews' => '1',
    'show_stock_count' => '0',
    'online_store_enabled' => '1',
    'announcement_bar_text' => '',
    'announcement_bar_enabled' => '0',
    'announcement_bar_color' => '#f68b1e',
    'show_deal_spotlight' => '1',
    'show_newsletter' => '1',
    'show_recently_viewed' => '1',
    'deal_of_the_day_product_id' => '',
    'homepage_sections' => 'flash_sale,top_selling,new_arrivals,categories',
    'font_family' => 'system',
    'heading_font_size' => 'medium',
    'body_font_size' => 'medium',
    'button_style' => 'rounded',
    'button_padding' => 'normal',
    'card_style' => 'elevated',
    'store_notice_message' => 'Welcome to our store!',
    'store_notice_type' => 'info',
    'store_notice_dismissible' => '1',
    'store_notice_link_text' => '',
    'store_notice_link_url' => '',
    'featured_product_id' => '',
    'featured_product_badge' => 'Featured',
    'featured_product_show_description' => '1',
    'featured_product_show_reviews' => '1',
    'featured_product_image_position' => 'left',
    'blog_posts_title' => 'Latest Posts',
    'blog_posts_limit' => '3',
    'blog_posts_columns' => '3',
    'blog_posts_show_date' => '1',
    'blog_posts_show_excerpt' => '1',
    'blog_posts_show_read_more' => '1',
    'payment_icons_title' => 'We Accept',
    'payment_icons_layout' => 'row',
    'payment_icons_visa' => '1',
    'payment_icons_mastercard' => '1',
    'payment_icons_amex' => '1',
    'payment_icons_paypal' => '1',
    'payment_icons_mpesa' => '1',
    'social_links_title' => 'Follow Us',
    'social_links_style' => 'icon-with-label',
    'social_links_align' => 'left',
    'social_links_facebook' => '1',
    'social_links_instagram' => '1',
    'social_links_twitter' => '1',
    'social_links_tiktok' => '1',
    'social_links_youtube' => '0',
    'social_links_whatsapp' => '0',
    'footer_bottom_text' => '',
    'footer_show_payment_icons' => '1',
    'footer_show_social_links' => '1',
    'layout_mode' => 'full-width',
    'layout_container_width' => '1400',
    'layout_sidebar' => 'none',
    'layout_grid_gap' => 'medium',
    'layout_content_padding' => 'normal',
    'header_style' => 'standard',
    'header_sticky' => '1',
    'header_top_bar' => '0',
    'header_top_bar_text' => '',
    'header_top_bar_bg' => '#1a1a2e',
    'header_top_bar_text_color' => 'light',
    'header_show_cart' => '1',
    'header_show_account' => '1',
    'header_show_search' => '1',
    'header_show_wishlist' => '0',
    'header_transparent_home' => '0',
    'header_bg_color' => '#ffffff',
    'header_text_color' => 'dark',
    'header_mobile_style' => 'overlay',
    'header_dropdown_style' => 'simple'
];

$settings = array_merge($defaults, $settings);

// Helper functions
function val($key) {
    global $settings;
    return htmlspecialchars($settings[$key] ?? '');
}

function chk($key) {
    global $settings;
    return ($settings[$key] ?? '0') === '1' ? 'checked' : '';
}

function sel($key, $value) {
    global $settings;
    return ($settings[$key] ?? '') === $value ? 'selected' : '';
}

// Get products list
$products = [];
try {
    $stmt = $pdo->prepare(
        "SELECT id, name, price, selling_price 
         FROM products 
         WHERE tenant_id = ? AND active = 1 
         AND (deleted_at IS NULL OR deleted_at = '0000-00-00 00:00:00') 
         ORDER BY name LIMIT 200"
    );
    $stmt->execute([$tenant_id]);
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // Silence
}

// Get blocks count
$blocks_count = 0;
try {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM storefront_blocks WHERE tenant_id = ? AND is_active = 1");
    $stmt->execute([$tenant_id]);
    $blocks_count = (int) $stmt->fetchColumn();
} catch (Exception $e) {
    // Silence
}

$store_url = storefront_url($tenant_id);
$brand_color = $settings['primary_color'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customize — <?= val('store_name') ?></title>
    
    <!-- Tailwind CSS CDN -->
    <link rel="stylesheet" href="<?php echo asset_url('css/app.css'); ?>">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    
    <style>
        /* Minimal custom CSS - only for complex animations and WordPress-like accordion behavior */
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(8px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .spinner {
            display: none;
            width: 16px;
            height: 16px;
            border: 2px solid #334155;
            border-top-color: #f59e0b;
            border-radius: 50%;
            animation: spin .8s linear infinite;
        }
        
        .spinner.show {
            display: inline-block;
        }
        
        /* Accordion toggle */
        .accordion-section-content {
            display: none;
        }
        
        .accordion-section.open .accordion-section-content {
            display: block;
        }
        
        .accordion-section-title::after {
            content: '\f054';
            font-family: 'Font Awesome 6 Free';
            font-weight: 900;
            font-size: 10px;
            color: #64748b;
            margin-left: auto;
            transition: transform .15s;
        }
        
        .accordion-section.open .accordion-section-title::after {
            transform: rotate(90deg);
        }
        
        /* Customizer panels */
        .customize-pane-child {
            display: none;
            position: absolute;
            top: 45px;
            left: 0;
            right: 0;
            bottom: 48px;
            background: #0f172a;
            z-index: 20;
            overflow-y: auto;
        }
        
        .customize-pane-child.active {
            display: block;
        }
        
        /* Toggle switches */
        .customize-toggle .track {
            width: 36px;
            height: 18px;
            background: #475569;
            border-radius: 9px;
            position: relative;
            transition: .15s;
        }
        
        .customize-toggle .thumb {
            width: 14px;
            height: 14px;
            background: #1e293b;
            border-radius: 50%;
            position: absolute;
            top: 2px;
            left: 2px;
            transition: .15s;
            box-shadow: 0 1px 3px rgba(0, 0, 0, .2);
        }
        
        .customize-toggle input:checked+.track {
            background: #f59e0b;
        }
        
        .customize-toggle input:checked+.track .thumb {
            left: 20px;
        }
        
        /* Drag and drop */
        .reorder-list li.dragging {
            opacity: .5;
        }
        
        .builder-zone.drag-over {
            border-color: #f59e0b !important;
            background: rgba(30, 41, 59, 0.4) !important;
        }
        
        /* Header Builder */
        .header-builder-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 300px;
            right: 0;
            bottom: 0;
            background: #0f172a;
            z-index: 200;
            flex-direction: column;
        }
        
        .wp-full-overlay.collapsed .header-builder-overlay {
            left: 0;
        }
        
        .header-builder-overlay.active {
            display: flex;
        }
        
        /* Preview frame */
        .device-frame.desktop {
            width: 100%;
            height: 100%;
            border: 0;
            border-radius: 0;
            box-shadow: none;
        }
        
        .device-frame.tablet {
            width: 768px;
            height: 90%;
            border-width: 16px;
            border-radius: 16px;
        }
        
        .device-frame.mobile {
            width: 375px;
            height: 85%;
            border-width: 14px;
            border-radius: 20px;
        }
        
        .device-frame iframe {
            width: 100%;
            height: 100%;
            border: 0;
            display: block;
        }
        
        /* Sidebar collapse */
        .wp-full-overlay.collapsed .wp-full-overlay-sidebar {
            width: 0;
            min-width: 0;
            overflow: hidden;
            border-right: 0;
        }
        
        .wp-full-overlay.collapsed .wp-full-overlay-main {
            padding: 0;
        }
        
        .wp-full-overlay.collapsed #showControlsFab {
            display: flex !important;
        }
        
        #showControlsFab {
            display: none;
        }
        
        /* Scrollbar */
        .custom-scroll::-webkit-scrollbar {
            width: 5px;
        }
        
        .custom-scroll::-webkit-scrollbar-thumb {
            background: #475569;
            border-radius: 3px;
        }
        
        .custom-scroll::-webkit-scrollbar-track {
            background: transparent;
        }
        
        /* Preset thumbnails */
        .preset-thumb.active {
            border-color: #f59e0b !important;
            box-shadow: 0 0 0 1px #f59e0b;
        }
        
        /* Color picker sync */
        .color-picker-row input[type="color"] {
            width: 32px;
            height: 32px;
            padding: 0;
            border: 1px solid #475569;
            border-radius: 3px;
            cursor: pointer;
        }
        
        /* Logo preview */
        .logo-preview img {
            max-width: 100%;
            max-height: 100%;
            object-fit: contain;
        }
        
        /* Builder components */
        .builder-component {
            cursor: grab;
            user-select: none;
        }
        
        .builder-component .remove {
            cursor: pointer;
        }
        
        .builder-component .remove:hover {
            color: #ef4444;
        }
        
        .builder-palette-item {
            cursor: grab;
            user-select: none;
        }
        
        /* CSP Fallback */
        #cspFallback {
            display: none;
        }
        
        #cspFallback.show {
            display: flex !important;
        }
    </style>
</head>
<body>

    <?php if ($msg): ?>
    <div class="fixed top-12 left-1/2 -translate-x-1/2 bg-slate-800 border-l-4 border-emerald-500 px-4 py-2.5 shadow-lg rounded-r z-50 text-sm flex items-center gap-2 animate-[fadeInUp_0.3s_ease-out]">
        <i class="fas fa-check-circle text-emerald-500"></i>
        <span class="text-slate-200"><?= htmlspecialchars($msg) ?></span>
    </div>
    <?php endif; ?>

    <div class="wp-full-overlay fixed inset-0 flex flex-row bg-slate-900">
        
        <!-- Sidebar -->
        <form id="customize-controls" class="wp-full-overlay-sidebar relative w-[300px] min-w-[300px] bg-slate-900 border-r border-slate-700 flex flex-col z-10 transition-all duration-200">
            
            <!-- Header -->
            <div class="h-11 bg-slate-800 border-b border-slate-700 flex items-center justify-between px-4 flex-shrink-0">
                <div class="flex items-center gap-2.5">
                    <div class="customize-save-button-wrapper flex items-center">
                        <input type="submit" name="save" id="save" value="Publish" 
                               class="px-3.5 py-1.5 bg-amber-500 text-white rounded-l text-xs font-semibold hover:bg-amber-600 transition-colors cursor-pointer border-0">
                        <button type="button" id="publishSettingsBtn" onclick="togglePublishSettings()" 
                                class="w-[30px] h-[30px] bg-amber-500 text-white rounded-r text-xs flex items-center justify-center border-l border-white/20 hover:bg-amber-600 transition-colors">
                            <i class="fas fa-cog"></i>
                        </button>
                    </div>
                    <span class="spinner" id="topSpinner"></span>
                    <button type="button" class="flex bg-slate-800 rounded border border-slate-600 p-0.5 gap-0.5 ml-3" id="previewToggleBtn" onclick="togglePreviewMode()">
                        <span class="controls active px-2.5 py-1 text-xs rounded text-slate-300 bg-slate-700 shadow-sm">Customize</span>
                        <span class="preview px-2.5 py-1 text-xs rounded text-slate-500">Preview</span>
                    </button>
                </div>
                <div class="flex items-center gap-2.5">
                    <span id="saveStatus" class="text-[11px] text-slate-500 opacity-60 transition-opacity">Auto-saved</span>
                    <a href="index.php" class="flex items-center gap-1.5 text-slate-300 text-sm font-semibold hover:text-white transition-colors">
                        <i class="fas fa-arrow-left text-xs"></i>
                        <span class="sr-only">Close</span>
                    </a>
                </div>
            </div>

            <!-- Sidebar Content -->
            <div class="flex-1 overflow-hidden flex flex-col">
                <div class="flex-1 overflow-y-auto custom-scroll" id="customize-theme-controls">
                    
                    <!-- Info Section -->
                    <div class="border-b border-slate-700 bg-slate-800">
                        <div class="flex items-center px-4 py-3 text-sm font-semibold text-slate-100">
                            <h2>You are customizing <strong class="text-amber-400"><?= val('store_name') ?></strong></h2>
                        </div>
                        <div class="px-4 pb-3 text-xs text-slate-400 leading-relaxed">
                            <p>The Customizer allows you to preview changes to your site before publishing them.</p>
                        </div>
                    </div>

                    <!-- Accordion Sections -->
                    <ul class="list-none" id="accordion">

                        <!-- Site Identity -->
                        <li class="accordion-section border-b border-slate-700 bg-slate-800 open">
                            <div class="accordion-section-title flex items-center px-4 py-2.5 text-sm font-semibold text-slate-100 cursor-pointer border-l-4 border-transparent hover:bg-slate-700/50 transition-colors" onclick="toggleSection(this)">
                                <i class="fas fa-id-card w-5 text-slate-500"></i> Site Identity
                            </div>
                            <div class="accordion-section-content px-4 pb-4 bg-slate-800">
                                <!-- Store Name -->
                                <div class="mb-3">
                                    <label class="block text-xs font-medium text-slate-300 mb-1" for="store_name">Store Name</label>
                                    <input type="text" name="store_name" id="store_name" value="<?= val('store_name') ?>" 
                                           class="w-full px-2 py-1.5 bg-slate-700 border border-slate-600 rounded text-xs text-slate-100 focus:outline-none focus:border-amber-500 focus:ring-1 focus:ring-amber-500" data-autosave>
                                </div>
                                <!-- Site Title -->
                                <div class="mb-3">
                                    <label class="block text-xs font-medium text-slate-300 mb-1" for="site_title">Site Title</label>
                                    <input type="text" name="site_title" id="site_title" value="<?= val('site_title') ?>" 
                                           placeholder="Browser tab title"
                                           class="w-full px-2 py-1.5 bg-slate-700 border border-slate-600 rounded text-xs text-slate-100 focus:outline-none focus:border-amber-500 focus:ring-1 focus:ring-amber-500" data-autosave>
                                </div>
                                <!-- Tagline -->
                                <div class="mb-3">
                                    <label class="block text-xs font-medium text-slate-300 mb-1" for="site_description">Tagline</label>
                                    <textarea name="site_description" id="site_description" rows="2" placeholder="Short store description"
                                              class="w-full px-2 py-1.5 bg-slate-700 border border-slate-600 rounded text-xs text-slate-100 focus:outline-none focus:border-amber-500 focus:ring-1 focus:ring-amber-500 resize-y" data-autosave><?= val('site_description') ?></textarea>
                                </div>
                                <!-- Currency -->
                                <div class="mb-3">
                                    <label class="block text-xs font-medium text-slate-300 mb-1" for="currency">Currency</label>
                                    <select name="currency" id="currency" class="w-full px-2 py-1.5 bg-slate-700 border border-slate-600 rounded text-xs text-slate-100 focus:outline-none focus:border-amber-500 focus:ring-1 focus:ring-amber-500" data-autosave>
                                        <?php foreach(['KES','USD','EUR','GBP','TZS','UGX','ZAR'] as $c): ?>
                                        <option value="<?= $c ?>" <?= $settings['currency'] === $c ? 'selected' : '' ?>><?= $c ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <!-- Logo -->
                                <div class="mb-3">
                                    <label class="block text-xs font-medium text-slate-300 mb-1">Logo</label>
                                    <div class="flex items-center gap-2.5">
                                        <div class="logo-preview w-[50px] h-[50px] border border-dashed border-slate-600 rounded bg-slate-700 flex items-center justify-center overflow-hidden" id="logoPreview">
                                            <?php 
                                            $logoPath = $settings['logo_url'] ? PUBLIC_PATH . '/' . ltrim($settings['logo_url'], '/') : '';
                                            if ($logoPath && file_exists($logoPath)): ?>
                                            <img src="<?= htmlspecialchars(base_url(ltrim($settings['logo_url'], '/'))) ?>" alt="Logo">
                                            <?php else: ?>
                                            <span class="text-2xl">🏪</span>
                                            <?php endif; ?>
                                        </div>
                                        <input type="file" name="logo_file" id="logo_file" accept="image/*" 
                                               class="text-xs text-slate-400 file:mr-2 file:py-1 file:px-3 file:rounded file:border-0 file:text-xs file:font-semibold file:bg-slate-600 file:text-slate-200 hover:file:bg-slate-500 cursor-pointer"
                                               onchange="previewLogo(this)">
                                    </div>
                                </div>
                                <!-- Favicon -->
                                <div class="mb-3">
                                    <label class="block text-xs font-medium text-slate-300 mb-1">Favicon</label>
                                    <div class="flex items-center gap-2.5">
                                        <div class="logo-preview w-[50px] h-[50px] border border-dashed border-slate-600 rounded bg-slate-700 flex items-center justify-center overflow-hidden" id="faviconPreview">
                                            <?php 
                                            $favPath = $settings['favicon_url'] ? PUBLIC_PATH . '/' . ltrim($settings['favicon_url'], '/') : '';
                                            if ($favPath && file_exists($favPath)): ?>
                                            <img src="<?= htmlspecialchars(base_url(ltrim($settings['favicon_url'], '/'))) ?>" alt="Favicon">
                                            <?php else: ?>
                                            <span class="text-xl">🌐</span>
                                            <?php endif; ?>
                                        </div>
                                        <input type="file" name="favicon_file" id="favicon_file" accept="image/*,.ico" 
                                               class="text-xs text-slate-400 file:mr-2 file:py-1 file:px-3 file:rounded file:border-0 file:text-xs file:font-semibold file:bg-slate-600 file:text-slate-200 hover:file:bg-slate-500 cursor-pointer"
                                               onchange="previewFavicon(this)">
                                    </div>
                                </div>
                            </div>
                        </li>

                        <!-- Colors -->
                        <li class="accordion-section border-b border-slate-700 bg-slate-800">
                            <div class="accordion-section-title flex items-center px-4 py-2.5 text-sm font-semibold text-slate-100 cursor-pointer border-l-4 border-transparent hover:bg-slate-700/50 transition-colors" onclick="toggleSection(this)">
                                <i class="fas fa-palette w-5 text-slate-500"></i> Colors
                            </div>
                            <div class="accordion-section-content px-4 pb-4 bg-slate-800">
                                <!-- Primary Color -->
                                <div class="mb-3">
                                    <label class="block text-xs font-medium text-slate-300 mb-1">Primary Color</label>
                                    <div class="color-picker-row flex items-center gap-2">
                                        <input type="color" name="primary_color" id="primaryColor" value="<?= val('primary_color') ?>" 
                                               class="w-8 h-8 p-0 border border-slate-600 rounded cursor-pointer" oninput="syncHex(this)" data-autosave>
                                        <input type="text" id="primaryColorHex" value="<?= val('primary_color') ?>" 
                                               class="flex-1 px-2 py-1.5 bg-slate-700 border border-slate-600 rounded text-xs font-mono text-slate-100 focus:outline-none focus:border-amber-500 focus:ring-1 focus:ring-amber-500"
                                               onchange="syncColorFromHex(this)" data-autosave>
                                    </div>
                                </div>
                                <!-- Secondary Color -->
                                <div class="mb-3">
                                    <label class="block text-xs font-medium text-slate-300 mb-1">Secondary Color</label>
                                    <div class="color-picker-row flex items-center gap-2">
                                        <input type="color" name="secondary_color" id="secondaryColor" value="<?= val('secondary_color') ?>" 
                                               class="w-8 h-8 p-0 border border-slate-600 rounded cursor-pointer" oninput="syncHex(this)" data-autosave>
                                        <input type="text" id="secondaryColorHex" value="<?= val('secondary_color') ?>" 
                                               class="flex-1 px-2 py-1.5 bg-slate-700 border border-slate-600 rounded text-xs font-mono text-slate-100 focus:outline-none focus:border-amber-500 focus:ring-1 focus:ring-amber-500"
                                               onchange="syncColorFromHex(this)" data-autosave>
                                    </div>
                                </div>
                                <!-- Presets -->
                                <div>
                                    <span class="block text-xs font-medium text-slate-300 mb-1.5">Quick Presets</span>
                                    <div class="flex flex-wrap gap-1.5">
                                        <?php 
                                        $presets = [
                                            ['Jumia', '#f68b1e', '#1a1a2e'],
                                            ['Minimal', '#ffffff', '#111111'],
                                            ['Modern', '#0ea5e9', '#0f172a'],
                                            ['Bold', '#e11d48', '#881337'],
                                            ['Elegant', '#10b981', '#064e3b'],
                                            ['Red', '#e02020', '#1a1a2e'],
                                            ['Green', '#2ecc71', '#1a472a'],
                                            ['Blue', '#2563eb', '#1e3a5f'],
                                            ['Purple', '#7c3aed', '#2e1065']
                                        ];
                                        foreach ($presets as [$name, $p, $sec]): ?>
                                        <button type="button" class="flex items-center gap-1 px-2 py-1 border border-slate-600 rounded text-xs bg-slate-700 text-slate-300 hover:border-amber-500 hover:text-white transition-colors" onclick="applyPreset('<?= $p ?>','<?= $sec ?>')">
                                            <span class="w-3.5 h-3.5 rounded-full border border-white/10" style="background:<?= $p ?>"></span>
                                            <?= $name ?>
                                        </button>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                        </li>

                        <!-- Typography -->
                        <li class="accordion-section border-b border-slate-700 bg-slate-800">
                            <div class="accordion-section-title flex items-center px-4 py-2.5 text-sm font-semibold text-slate-100 cursor-pointer border-l-4 border-transparent hover:bg-slate-700/50 transition-colors" onclick="toggleSection(this)">
                                <i class="fas fa-font w-5 text-slate-500"></i> Typography
                            </div>
                            <div class="accordion-section-content px-4 pb-4 bg-slate-800">
                                <div class="mb-3">
                                    <label class="block text-xs font-medium text-slate-300 mb-1" for="font_family">Font Family</label>
                                    <select name="font_family" id="font_family" class="w-full px-2 py-1.5 bg-slate-700 border border-slate-600 rounded text-xs text-slate-100 focus:outline-none focus:border-amber-500 focus:ring-1 focus:ring-amber-500" data-autosave>
                                        <?php foreach(['system'=>'System Default','Inter'=>'Inter','Poppins'=>'Poppins','Roboto'=>'Roboto','Open Sans'=>'Open Sans','Playfair Display'=>'Playfair Display'] as $k=>$lbl): ?>
                                        <option value="<?= $k ?>" <?= sel('font_family', $k) ?>><?= $lbl ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label class="block text-xs font-medium text-slate-300 mb-1" for="heading_font_size">Heading Size</label>
                                    <select name="heading_font_size" id="heading_font_size" class="w-full px-2 py-1.5 bg-slate-700 border border-slate-600 rounded text-xs text-slate-100 focus:outline-none focus:border-amber-500 focus:ring-1 focus:ring-amber-500" data-autosave>
                                        <?php foreach(['small'=>'Small','medium'=>'Medium','large'=>'Large','x-large'=>'Extra Large'] as $k=>$lbl): ?>
                                        <option value="<?= $k ?>" <?= sel('heading_font_size', $k) ?>><?= $lbl ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label class="block text-xs font-medium text-slate-300 mb-1" for="body_font_size">Body Size</label>
                                    <select name="body_font_size" id="body_font_size" class="w-full px-2 py-1.5 bg-slate-700 border border-slate-600 rounded text-xs text-slate-100 focus:outline-none focus:border-amber-500 focus:ring-1 focus:ring-amber-500" data-autosave>
                                        <?php foreach(['small'=>'Small','medium'=>'Medium','large'=>'Large'] as $k=>$lbl): ?>
                                        <option value="<?= $k ?>" <?= sel('body_font_size', $k) ?>><?= $lbl ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </li>

                        <!-- Design -->
                        <li class="accordion-section border-b border-slate-700 bg-slate-800">
                            <div class="accordion-section-title flex items-center px-4 py-2.5 text-sm font-semibold text-slate-100 cursor-pointer border-l-4 border-transparent hover:bg-slate-700/50 transition-colors" onclick="toggleSection(this)">
                                <i class="fas fa-shapes w-5 text-slate-500"></i> Design
                            </div>
                            <div class="accordion-section-content px-4 pb-4 bg-slate-800">
                                <div class="mb-3">
                                    <label class="block text-xs font-medium text-slate-300 mb-1" for="button_style">Button Style</label>
                                    <select name="button_style" id="button_style" class="w-full px-2 py-1.5 bg-slate-700 border border-slate-600 rounded text-xs text-slate-100 focus:outline-none focus:border-amber-500 focus:ring-1 focus:ring-amber-500" data-autosave>
                                        <?php foreach(['rounded'=>'Rounded','sharp'=>'Sharp / Square','pill'=>'Pill / Capsule'] as $k=>$lbl): ?>
                                        <option value="<?= $k ?>" <?= sel('button_style', $k) ?>><?= $lbl ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label class="block text-xs font-medium text-slate-300 mb-1" for="button_padding">Button Padding</label>
                                    <select name="button_padding" id="button_padding" class="w-full px-2 py-1.5 bg-slate-700 border border-slate-600 rounded text-xs text-slate-100 focus:outline-none focus:border-amber-500 focus:ring-1 focus:ring-amber-500" data-autosave>
                                        <?php foreach(['compact'=>'Compact','normal'=>'Normal','spacious'=>'Spacious'] as $k=>$lbl): ?>
                                        <option value="<?= $k ?>" <?= sel('button_padding', $k) ?>><?= $lbl ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label class="block text-xs font-medium text-slate-300 mb-1" for="card_style">Card Style</label>
                                    <select name="card_style" id="card_style" class="w-full px-2 py-1.5 bg-slate-700 border border-slate-600 rounded text-xs text-slate-100 focus:outline-none focus:border-amber-500 focus:ring-1 focus:ring-amber-500" data-autosave>
                                        <?php foreach(['flat'=>'Flat','elevated'=>'Elevated / Shadow','bordered'=>'Bordered'] as $k=>$lbl): ?>
                                        <option value="<?= $k ?>" <?= sel('card_style', $k) ?>><?= $lbl ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </li>

                        <!-- Layout -->
                        <li class="accordion-section border-b border-slate-700 bg-slate-800">
                            <div class="accordion-section-title flex items-center px-4 py-2.5 text-sm font-semibold text-slate-100 cursor-pointer border-l-4 border-transparent hover:bg-slate-700/50 transition-colors" onclick="toggleSection(this)">
                                <i class="fas fa-th-large w-5 text-slate-500"></i> Layout
                            </div>
                            <div class="accordion-section-content px-4 pb-4 bg-slate-800">
                                <div class="mb-3">
                                    <label class="block text-xs font-medium text-slate-300 mb-1" for="layout_mode">Site Layout</label>
                                    <select name="layout_mode" id="layout_mode" class="w-full px-2 py-1.5 bg-slate-700 border border-slate-600 rounded text-xs text-slate-100 focus:outline-none focus:border-amber-500 focus:ring-1 focus:ring-amber-500" data-autosave>
                                        <?php foreach(['full-width'=>'Full Width','boxed'=>'Boxed','framed'=>'Framed'] as $k=>$lbl): ?>
                                        <option value="<?= $k ?>" <?= sel('layout_mode', $k) ?>><?= $lbl ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label class="block text-xs font-medium text-slate-300 mb-1" for="layout_container_width">Container Max Width (px)</label>
                                    <select name="layout_container_width" id="layout_container_width" class="w-full px-2 py-1.5 bg-slate-700 border border-slate-600 rounded text-xs text-slate-100 focus:outline-none focus:border-amber-500 focus:ring-1 focus:ring-amber-500" data-autosave>
                                        <?php foreach(['1200'=>'1200px','1400'=>'1400px','1600'=>'1600px'] as $k=>$lbl): ?>
                                        <option value="<?= $k ?>" <?= sel('layout_container_width', $k) ?>><?= $lbl ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label class="block text-xs font-medium text-slate-300 mb-1" for="layout_sidebar">Default Sidebar</label>
                                    <select name="layout_sidebar" id="layout_sidebar" class="w-full px-2 py-1.5 bg-slate-700 border border-slate-600 rounded text-xs text-slate-100 focus:outline-none focus:border-amber-500 focus:ring-1 focus:ring-amber-500" data-autosave>
                                        <?php foreach(['none'=>'No Sidebar','left'=>'Left Sidebar','right'=>'Right Sidebar'] as $k=>$lbl): ?>
                                        <option value="<?= $k ?>" <?= sel('layout_sidebar', $k) ?>><?= $lbl ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label class="block text-xs font-medium text-slate-300 mb-1" for="layout_grid_gap">Grid Gap</label>
                                    <select name="layout_grid_gap" id="layout_grid_gap" class="w-full px-2 py-1.5 bg-slate-700 border border-slate-600 rounded text-xs text-slate-100 focus:outline-none focus:border-amber-500 focus:ring-1 focus:ring-amber-500" data-autosave>
                                        <?php foreach(['small'=>'Small','medium'=>'Medium','large'=>'Large'] as $k=>$lbl): ?>
                                        <option value="<?= $k ?>" <?= sel('layout_grid_gap', $k) ?>><?= $lbl ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label class="block text-xs font-medium text-slate-300 mb-1" for="layout_content_padding">Content Padding</label>
                                    <select name="layout_content_padding" id="layout_content_padding" class="w-full px-2 py-1.5 bg-slate-700 border border-slate-600 rounded text-xs text-slate-100 focus:outline-none focus:border-amber-500 focus:ring-1 focus:ring-amber-500" data-autosave>
                                        <?php foreach(['compact'=>'Compact','normal'=>'Normal','spacious'=>'Spacious'] as $k=>$lbl): ?>
                                        <option value="<?= $k ?>" <?= sel('layout_content_padding', $k) ?>><?= $lbl ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </li>

                        <!-- Header -->
                        <li class="accordion-section border-b border-slate-700 bg-slate-800">
                            <div class="accordion-section-title flex items-center px-4 py-2.5 text-sm font-semibold text-slate-100 cursor-pointer border-l-4 border-transparent hover:bg-slate-700/50 transition-colors" onclick="openHeaderPanel()">
                                <i class="fas fa-heading w-5 text-slate-500"></i> Header
                            </div>
                        </li>

                        <!-- Announcement Bar -->
                        <li class="accordion-section border-b border-slate-700 bg-slate-800">
                            <div class="accordion-section-title flex items-center px-4 py-2.5 text-sm font-semibold text-slate-100 cursor-pointer border-l-4 border-transparent hover:bg-slate-700/50 transition-colors" onclick="toggleSection(this)">
                                <i class="fas fa-bullhorn w-5 text-slate-500"></i> Announcement Bar
                            </div>
                            <div class="accordion-section-content px-4 pb-4 bg-slate-800">
                                <div class="mb-3">
                                    <label class="block text-xs font-medium text-slate-300 mb-1" for="announcement_bar_text">Text</label>
                                    <input type="text" name="announcement_bar_text" id="announcement_bar_text" value="<?= val('announcement_bar_text') ?>" 
                                           placeholder="Free delivery on orders over KES 5,000"
                                           class="w-full px-2 py-1.5 bg-slate-700 border border-slate-600 rounded text-xs text-slate-100 focus:outline-none focus:border-amber-500 focus:ring-1 focus:ring-amber-500" data-autosave>
                                </div>
                                <div class="mb-3">
                                    <label class="block text-xs font-medium text-slate-300 mb-1">Color</label>
                                    <div class="color-picker-row flex items-center gap-2">
                                        <input type="color" name="announcement_bar_color" id="announcementBarColor" value="<?= val('announcement_bar_color') ?>" 
                                               class="w-8 h-8 p-0 border border-slate-600 rounded cursor-pointer" oninput="syncHex(this)" data-autosave>
                                        <input type="text" id="announcementBarColorHex" value="<?= val('announcement_bar_color') ?>" 
                                               class="flex-1 px-2 py-1.5 bg-slate-700 border border-slate-600 rounded text-xs font-mono text-slate-100 focus:outline-none focus:border-amber-500 focus:ring-1 focus:ring-amber-500"
                                               onchange="syncColorFromHex(this)" data-autosave>
                                    </div>
                                </div>
                                <div>
                                    <label class="customize-toggle flex items-center justify-between cursor-pointer">
                                        <span class="text-xs font-medium text-slate-300">Enable</span>
                                        <span>
                                            <input type="checkbox" name="announcement_bar_enabled" value="1" <?= chk('announcement_bar_enabled') ?> data-autosave>
                                            <span class="track block w-9 h-4.5 bg-slate-600 rounded-full relative transition-colors">
                                                <span class="thumb block w-3.5 h-3.5 bg-slate-800 rounded-full absolute top-0.5 left-0.5 transition-all shadow-sm"></span>
                                            </span>
                                        </span>
                                    </label>
                                </div>
                            </div>
                        </li>

                        <!-- Homepage -->
                        <li class="accordion-section border-b border-slate-700 bg-slate-800">
                            <div class="accordion-section-title flex items-center px-4 py-2.5 text-sm font-semibold text-slate-100 cursor-pointer border-l-4 border-transparent hover:bg-slate-700/50 transition-colors" onclick="toggleSection(this)">
                                <i class="fas fa-home w-5 text-slate-500"></i> Homepage
                            </div>
                            <div class="accordion-section-content px-4 pb-4 bg-slate-800">
                                <div class="mb-3">
                                    <label class="block text-xs font-medium text-slate-300 mb-1">Section Order (drag to reorder)</label>
                                    <input type="hidden" name="homepage_sections" id="homepageSections" value="<?= val('homepage_sections') ?>" data-autosave>
                                    <ul class="reorder-list list-none" id="reorderList">
                                        <?php 
                                        $labels = [
                                            'flash_sale' => ['⚡', 'Flash Sale'],
                                            'top_selling' => ['🔥', 'Top Selling'],
                                            'new_arrivals' => ['✨', 'New Arrivals'],
                                            'categories' => ['🗂️', 'Category Grid'],
                                            'featured' => ['⭐', 'Featured'],
                                            'brands' => ['🏷️', 'Brand Showcase']
                                        ];
                                        $order = array_filter(explode(',', $settings['homepage_sections']));
                                        if (empty($order)) $order = array_keys($labels);
                                        foreach ($order as $key):
                                            if (!isset($labels[$key])) continue;
                                            [$em, $lbl] = $labels[$key];
                                        ?>
                                        <li data-key="<?= $key ?>" draggable="true" 
                                            class="flex items-center gap-2 px-3 py-1.5 bg-slate-700 border border-slate-600 rounded mb-1 cursor-grab text-xs text-slate-200">
                                            <span class="drag-handle text-slate-500"><i class="fas fa-grip-vertical"></i></span>
                                            <span class="text-base"><?= $em ?></span>
                                            <span><?= $lbl ?></span>
                                        </li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                                <div class="mb-3">
                                    <label class="block text-xs font-medium text-slate-300 mb-1" for="deal_of_the_day_product_id">Deal of the Day</label>
                                    <select name="deal_of_the_day_product_id" id="deal_of_the_day_product_id" class="w-full px-2 py-1.5 bg-slate-700 border border-slate-600 rounded text-xs text-slate-100 focus:outline-none focus:border-amber-500 focus:ring-1 focus:ring-amber-500" data-autosave>
                                        <option value="">— Auto-select —</option>
                                        <?php foreach ($products as $prod): ?>
                                        <option value="<?= $prod['id'] ?>" <?= $settings['deal_of_the_day_product_id'] == $prod['id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($prod['name']) ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label class="customize-toggle flex items-center justify-between cursor-pointer">
                                        <span class="text-xs font-medium text-slate-300">Deal Spotlight</span>
                                        <span>
                                            <input type="checkbox" name="show_deal_spotlight" value="1" <?= chk('show_deal_spotlight') ?> data-autosave>
                                            <span class="track block w-9 h-4.5 bg-slate-600 rounded-full relative transition-colors">
                                                <span class="thumb block w-3.5 h-3.5 bg-slate-800 rounded-full absolute top-0.5 left-0.5 transition-all shadow-sm"></span>
                                            </span>
                                        </span>
                                    </label>
                                </div>
                                <div class="mb-3">
                                    <label class="customize-toggle flex items-center justify-between cursor-pointer">
                                        <span class="text-xs font-medium text-slate-300">Newsletter</span>
                                        <span>
                                            <input type="checkbox" name="show_newsletter" value="1" <?= chk('show_newsletter') ?> data-autosave>
                                            <span class="track block w-9 h-4.5 bg-slate-600 rounded-full relative transition-colors">
                                                <span class="thumb block w-3.5 h-3.5 bg-slate-800 rounded-full absolute top-0.5 left-0.5 transition-all shadow-sm"></span>
                                            </span>
                                        </span>
                                    </label>
                                </div>
                                <div>
                                    <label class="customize-toggle flex items-center justify-between cursor-pointer">
                                        <span class="text-xs font-medium text-slate-300">Recently Viewed</span>
                                        <span>
                                            <input type="checkbox" name="show_recently_viewed" value="1" <?= chk('show_recently_viewed') ?> data-autosave>
                                            <span class="track block w-9 h-4.5 bg-slate-600 rounded-full relative transition-colors">
                                                <span class="thumb block w-3.5 h-3.5 bg-slate-800 rounded-full absolute top-0.5 left-0.5 transition-all shadow-sm"></span>
                                            </span>
                                        </span>
                                    </label>
                                </div>
                            </div>
                        </li>

                        <!-- Footer -->
                        <li class="accordion-section border-b border-slate-700 bg-slate-800">
                            <div class="accordion-section-title flex items-center px-4 py-2.5 text-sm font-semibold text-slate-100 cursor-pointer border-l-4 border-transparent hover:bg-slate-700/50 transition-colors" onclick="toggleSection(this)">
                                <i class="fas fa-shoe-prints w-5 text-slate-500"></i> Footer
                            </div>
                            <div class="accordion-section-content px-4 pb-4 bg-slate-800">
                                <div class="mb-3">
                                    <label class="block text-xs font-medium text-slate-300 mb-1" for="facebook_url">Facebook URL</label>
                                    <input type="url" name="facebook_url" id="facebook_url" value="<?= val('facebook_url') ?>" 
                                           placeholder="https://facebook.com/..."
                                           class="w-full px-2 py-1.5 bg-slate-700 border border-slate-600 rounded text-xs text-slate-100 focus:outline-none focus:border-amber-500 focus:ring-1 focus:ring-amber-500" data-autosave>
                                </div>
                                <div class="mb-3">
                                    <label class="block text-xs font-medium text-slate-300 mb-1" for="instagram_url">Instagram URL</label>
                                    <input type="url" name="instagram_url" id="instagram_url" value="<?= val('instagram_url') ?>" 
                                           placeholder="https://instagram.com/..."
                                           class="w-full px-2 py-1.5 bg-slate-700 border border-slate-600 rounded text-xs text-slate-100 focus:outline-none focus:border-amber-500 focus:ring-1 focus:ring-amber-500" data-autosave>
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-slate-300 mb-1" for="tiktok_url">TikTok URL</label>
                                    <input type="url" name="tiktok_url" id="tiktok_url" value="<?= val('tiktok_url') ?>" 
                                           placeholder="https://tiktok.com/@..."
                                           class="w-full px-2 py-1.5 bg-slate-700 border border-slate-600 rounded text-xs text-slate-100 focus:outline-none focus:border-amber-500 focus:ring-1 focus:ring-amber-500" data-autosave>
                                </div>
                            </div>
                        </li>

                        <!-- SEO -->
                        <li class="accordion-section border-b border-slate-700 bg-slate-800">
                            <div class="accordion-section-title flex items-center px-4 py-2.5 text-sm font-semibold text-slate-100 cursor-pointer border-l-4 border-transparent hover:bg-slate-700/50 transition-colors" onclick="toggleSection(this)">
                                <i class="fas fa-search w-5 text-slate-500"></i> SEO
                            </div>
                            <div class="accordion-section-content px-4 pb-4 bg-slate-800">
                                <div class="mb-3">
                                    <label class="block text-xs font-medium text-slate-300 mb-1" for="meta_title">Meta Title</label>
                                    <input type="text" name="meta_title" id="meta_title" value="<?= val('meta_title') ?>" 
                                           placeholder="Page title for search engines"
                                           class="w-full px-2 py-1.5 bg-slate-700 border border-slate-600 rounded text-xs text-slate-100 focus:outline-none focus:border-amber-500 focus:ring-1 focus:ring-amber-500" data-autosave>
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-slate-300 mb-1" for="meta_description">Meta Description</label>
                                    <textarea name="meta_description" id="meta_description" rows="3" placeholder="Brief description for search results"
                                              class="w-full px-2 py-1.5 bg-slate-700 border border-slate-600 rounded text-xs text-slate-100 focus:outline-none focus:border-amber-500 focus:ring-1 focus:ring-amber-500 resize-y" data-autosave><?= val('meta_description') ?></textarea>
                                </div>
                            </div>
                        </li>

                        <!-- Store Settings -->
                        <li class="accordion-section border-b border-slate-700 bg-slate-800">
                            <div class="accordion-section-title flex items-center px-4 py-2.5 text-sm font-semibold text-slate-100 cursor-pointer border-l-4 border-transparent hover:bg-slate-700/50 transition-colors" onclick="toggleSection(this)">
                                <i class="fas fa-store w-5 text-slate-500"></i> Store Settings
                            </div>
                            <div class="accordion-section-content px-4 pb-4 bg-slate-800">
                                <div class="mb-3">
                                    <label class="customize-toggle flex items-center justify-between cursor-pointer">
                                        <span class="text-xs font-medium text-slate-300">Online Store</span>
                                        <span>
                                            <input type="checkbox" name="online_store_enabled" value="1" <?= chk('online_store_enabled') ?> data-autosave>
                                            <span class="track block w-9 h-4.5 bg-slate-600 rounded-full relative transition-colors">
                                                <span class="thumb block w-3.5 h-3.5 bg-slate-800 rounded-full absolute top-0.5 left-0.5 transition-all shadow-sm"></span>
                                            </span>
                                        </span>
                                    </label>
                                </div>
                                <div class="mb-3">
                                    <label class="customize-toggle flex items-center justify-between cursor-pointer">
                                        <span class="text-xs font-medium text-slate-300">Show Reviews</span>
                                        <span>
                                            <input type="checkbox" name="show_reviews" value="1" <?= chk('show_reviews') ?> data-autosave>
                                            <span class="track block w-9 h-4.5 bg-slate-600 rounded-full relative transition-colors">
                                                <span class="thumb block w-3.5 h-3.5 bg-slate-800 rounded-full absolute top-0.5 left-0.5 transition-all shadow-sm"></span>
                                            </span>
                                        </span>
                                    </label>
                                </div>
                                <div class="mb-3">
                                    <label class="customize-toggle flex items-center justify-between cursor-pointer">
                                        <span class="text-xs font-medium text-slate-300">Stock Count</span>
                                        <span>
                                            <input type="checkbox" name="show_stock_count" value="1" <?= chk('show_stock_count') ?> data-autosave>
                                            <span class="track block w-9 h-4.5 bg-slate-600 rounded-full relative transition-colors">
                                                <span class="thumb block w-3.5 h-3.5 bg-slate-800 rounded-full absolute top-0.5 left-0.5 transition-all shadow-sm"></span>
                                            </span>
                                        </span>
                                    </label>
                                </div>
                                <div class="mb-3">
                                    <label class="block text-xs font-medium text-slate-300 mb-1" for="free_shipping_threshold">Free Shipping Threshold (<?= val('currency') ?>)</label>
                                    <input type="number" name="free_shipping_threshold" id="free_shipping_threshold" value="<?= val('free_shipping_threshold') ?>" 
                                           min="0" step="100"
                                           class="w-full px-2 py-1.5 bg-slate-700 border border-slate-600 rounded text-xs text-slate-100 focus:outline-none focus:border-amber-500 focus:ring-1 focus:ring-amber-500" data-autosave>
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-slate-300 mb-1" for="min_order_amount">Minimum Order (<?= val('currency') ?>)</label>
                                    <input type="number" name="min_order_amount" id="min_order_amount" value="<?= val('min_order_amount') ?>" 
                                           min="0" step="50"
                                           class="w-full px-2 py-1.5 bg-slate-700 border border-slate-600 rounded text-xs text-slate-100 focus:outline-none focus:border-amber-500 focus:ring-1 focus:ring-amber-500" data-autosave>
                                </div>
                            </div>
                        </li>

                        <!-- Store Notice -->
                        <li class="accordion-section border-b border-slate-700 bg-slate-800">
                            <div class="accordion-section-title flex items-center px-4 py-2.5 text-sm font-semibold text-slate-100 cursor-pointer border-l-4 border-transparent hover:bg-slate-700/50 transition-colors" onclick="toggleSection(this)">
                                <i class="fas fa-bullhorn w-5 text-slate-500"></i> Store Notice
                            </div>
                            <div class="accordion-section-content px-4 pb-4 bg-slate-800">
                                <div class="mb-3">
                                    <label class="block text-xs font-medium text-slate-300 mb-1" for="store_notice_message">Message</label>
                                    <textarea name="store_notice_message" id="store_notice_message" rows="2" placeholder="Welcome to our store!"
                                              class="w-full px-2 py-1.5 bg-slate-700 border border-slate-600 rounded text-xs text-slate-100 focus:outline-none focus:border-amber-500 focus:ring-1 focus:ring-amber-500 resize-y" data-autosave><?= val('store_notice_message') ?></textarea>
                                </div>
                                <div class="mb-3">
                                    <label class="block text-xs font-medium text-slate-300 mb-1" for="store_notice_type">Type</label>
                                    <select name="store_notice_type" id="store_notice_type" class="w-full px-2 py-1.5 bg-slate-700 border border-slate-600 rounded text-xs text-slate-100 focus:outline-none focus:border-amber-500 focus:ring-1 focus:ring-amber-500" data-autosave>
                                        <?php foreach(['info'=>'Info','success'=>'Success','warning'=>'Warning','error'=>'Error'] as $k=>$lbl): ?>
                                        <option value="<?= $k ?>" <?= sel('store_notice_type', $k) ?>><?= $lbl ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label class="customize-toggle flex items-center justify-between cursor-pointer">
                                        <span class="text-xs font-medium text-slate-300">Dismissible</span>
                                        <span>
                                            <input type="checkbox" name="store_notice_dismissible" value="1" <?= chk('store_notice_dismissible') ?> data-autosave>
                                            <span class="track block w-9 h-4.5 bg-slate-600 rounded-full relative transition-colors">
                                                <span class="thumb block w-3.5 h-3.5 bg-slate-800 rounded-full absolute top-0.5 left-0.5 transition-all shadow-sm"></span>
                                            </span>
                                        </span>
                                    </label>
                                </div>
                                <div class="mb-3">
                                    <label class="block text-xs font-medium text-slate-300 mb-1" for="store_notice_link_text">Link Text</label>
                                    <input type="text" name="store_notice_link_text" id="store_notice_link_text" value="<?= val('store_notice_link_text') ?>" 
                                           placeholder="Learn more"
                                           class="w-full px-2 py-1.5 bg-slate-700 border border-slate-600 rounded text-xs text-slate-100 focus:outline-none focus:border-amber-500 focus:ring-1 focus:ring-amber-500" data-autosave>
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-slate-300 mb-1" for="store_notice_link_url">Link URL</label>
                                    <input type="url" name="store_notice_link_url" id="store_notice_link_url" value="<?= val('store_notice_link_url') ?>" 
                                           placeholder="https://..."
                                           class="w-full px-2 py-1.5 bg-slate-700 border border-slate-600 rounded text-xs text-slate-100 focus:outline-none focus:border-amber-500 focus:ring-1 focus:ring-amber-500" data-autosave>
                                </div>
                            </div>
                        </li>

                        <!-- Featured Product -->
                        <li class="accordion-section border-b border-slate-700 bg-slate-800">
                            <div class="accordion-section-title flex items-center px-4 py-2.5 text-sm font-semibold text-slate-100 cursor-pointer border-l-4 border-transparent hover:bg-slate-700/50 transition-colors" onclick="toggleSection(this)">
                                <i class="fas fa-star w-5 text-slate-500"></i> Featured Product
                            </div>
                            <div class="accordion-section-content px-4 pb-4 bg-slate-800">
                                <div class="mb-3">
                                    <label class="block text-xs font-medium text-slate-300 mb-1" for="featured_product_id">Select Product</label>
                                    <select name="featured_product_id" id="featured_product_id" class="w-full px-2 py-1.5 bg-slate-700 border border-slate-600 rounded text-xs text-slate-100 focus:outline-none focus:border-amber-500 focus:ring-1 focus:ring-amber-500" data-autosave>
                                        <option value="">— None —</option>
                                        <?php foreach ($products as $prod): ?>
                                        <option value="<?= $prod['id'] ?>" <?= $settings['featured_product_id'] == $prod['id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($prod['name']) ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label class="block text-xs font-medium text-slate-300 mb-1" for="featured_product_badge">Badge Text</label>
                                    <input type="text" name="featured_product_badge" id="featured_product_badge" value="<?= val('featured_product_badge') ?>" 
                                           placeholder="Featured"
                                           class="w-full px-2 py-1.5 bg-slate-700 border border-slate-600 rounded text-xs text-slate-100 focus:outline-none focus:border-amber-500 focus:ring-1 focus:ring-amber-500" data-autosave>
                                </div>
                                <div class="mb-3">
                                    <label class="customize-toggle flex items-center justify-between cursor-pointer">
                                        <span class="text-xs font-medium text-slate-300">Show Description</span>
                                        <span>
                                            <input type="checkbox" name="featured_product_show_description" value="1" <?= chk('featured_product_show_description') ?> data-autosave>
                                            <span class="track block w-9 h-4.5 bg-slate-600 rounded-full relative transition-colors">
                                                <span class="thumb block w-3.5 h-3.5 bg-slate-800 rounded-full absolute top-0.5 left-0.5 transition-all shadow-sm"></span>
                                            </span>
                                        </span>
                                    </label>
                                </div>
                                <div class="mb-3">
                                    <label class="customize-toggle flex items-center justify-between cursor-pointer">
                                        <span class="text-xs font-medium text-slate-300">Show Reviews</span>
                                        <span>
                                            <input type="checkbox" name="featured_product_show_reviews" value="1" <?= chk('featured_product_show_reviews') ?> data-autosave>
                                            <span class="track block w-9 h-4.5 bg-slate-600 rounded-full relative transition-colors">
                                                <span class="thumb block w-3.5 h-3.5 bg-slate-800 rounded-full absolute top-0.5 left-0.5 transition-all shadow-sm"></span>
                                            </span>
                                        </span>
                                    </label>
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-slate-300 mb-1" for="featured_product_image_position">Image Position</label>
                                    <select name="featured_product_image_position" id="featured_product_image_position" class="w-full px-2 py-1.5 bg-slate-700 border border-slate-600 rounded text-xs text-slate-100 focus:outline-none focus:border-amber-500 focus:ring-1 focus:ring-amber-500" data-autosave>
                                        <?php foreach(['left'=>'Left','right'=>'Right'] as $k=>$lbl): ?>
                                        <option value="<?= $k ?>" <?= sel('featured_product_image_position', $k) ?>><?= $lbl ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </li>

                        <!-- Blog Posts -->
                        <li class="accordion-section border-b border-slate-700 bg-slate-800">
                            <div class="accordion-section-title flex items-center px-4 py-2.5 text-sm font-semibold text-slate-100 cursor-pointer border-l-4 border-transparent hover:bg-slate-700/50 transition-colors" onclick="toggleSection(this)">
                                <i class="fas fa-newspaper w-5 text-slate-500"></i> Blog Posts
                            </div>
                            <div class="accordion-section-content px-4 pb-4 bg-slate-800">
                                <div class="mb-3">
                                    <label class="block text-xs font-medium text-slate-300 mb-1" for="blog_posts_title">Section Title</label>
                                    <input type="text" name="blog_posts_title" id="blog_posts_title" value="<?= val('blog_posts_title') ?>" 
                                           placeholder="Latest Posts"
                                           class="w-full px-2 py-1.5 bg-slate-700 border border-slate-600 rounded text-xs text-slate-100 focus:outline-none focus:border-amber-500 focus:ring-1 focus:ring-amber-500" data-autosave>
                                </div>
                                <div class="mb-3">
                                    <label class="block text-xs font-medium text-slate-300 mb-1" for="blog_posts_limit">Posts to Show</label>
                                    <input type="number" name="blog_posts_limit" id="blog_posts_limit" value="<?= val('blog_posts_limit') ?>" 
                                           min="1" max="12"
                                           class="w-full px-2 py-1.5 bg-slate-700 border border-slate-600 rounded text-xs text-slate-100 focus:outline-none focus:border-amber-500 focus:ring-1 focus:ring-amber-500" data-autosave>
                                </div>
                                <div class="mb-3">
                                    <label class="block text-xs font-medium text-slate-300 mb-1" for="blog_posts_columns">Columns</label>
                                    <select name="blog_posts_columns" id="blog_posts_columns" class="w-full px-2 py-1.5 bg-slate-700 border border-slate-600 rounded text-xs text-slate-100 focus:outline-none focus:border-amber-500 focus:ring-1 focus:ring-amber-500" data-autosave>
                                        <?php foreach(['2'=>'2 Columns','3'=>'3 Columns','4'=>'4 Columns'] as $k=>$lbl): ?>
                                        <option value="<?= $k ?>" <?= sel('blog_posts_columns', $k) ?>><?= $lbl ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label class="customize-toggle flex items-center justify-between cursor-pointer">
                                        <span class="text-xs font-medium text-slate-300">Show Date</span>
                                        <span>
                                            <input type="checkbox" name="blog_posts_show_date" value="1" <?= chk('blog_posts_show_date') ?> data-autosave>
                                            <span class="track block w-9 h-4.5 bg-slate-600 rounded-full relative transition-colors">
                                                <span class="thumb block w-3.5 h-3.5 bg-slate-800 rounded-full absolute top-0.5 left-0.5 transition-all shadow-sm"></span>
                                            </span>
                                        </span>
                                    </label>
                                </div>
                                <div class="mb-3">
                                    <label class="customize-toggle flex items-center justify-between cursor-pointer">
                                        <span class="text-xs font-medium text-slate-300">Show Excerpt</span>
                                        <span>
                                            <input type="checkbox" name="blog_posts_show_excerpt" value="1" <?= chk('blog_posts_show_excerpt') ?> data-autosave>
                                            <span class="track block w-9 h-4.5 bg-slate-600 rounded-full relative transition-colors">
                                                <span class="thumb block w-3.5 h-3.5 bg-slate-800 rounded-full absolute top-0.5 left-0.5 transition-all shadow-sm"></span>
                                            </span>
                                        </span>
                                    </label>
                                </div>
                                <div>
                                    <label class="customize-toggle flex items-center justify-between cursor-pointer">
                                        <span class="text-xs font-medium text-slate-300">Show Read More</span>
                                        <span>
                                            <input type="checkbox" name="blog_posts_show_read_more" value="1" <?= chk('blog_posts_show_read_more') ?> data-autosave>
                                            <span class="track block w-9 h-4.5 bg-slate-600 rounded-full relative transition-colors">
                                                <span class="thumb block w-3.5 h-3.5 bg-slate-800 rounded-full absolute top-0.5 left-0.5 transition-all shadow-sm"></span>
                                            </span>
                                        </span>
                                    </label>
                                </div>
                            </div>
                        </li>

                        <!-- Payment Icons -->
                        <li class="accordion-section border-b border-slate-700 bg-slate-800">
                            <div class="accordion-section-title flex items-center px-4 py-2.5 text-sm font-semibold text-slate-100 cursor-pointer border-l-4 border-transparent hover:bg-slate-700/50 transition-colors" onclick="toggleSection(this)">
                                <i class="fas fa-credit-card w-5 text-slate-500"></i> Payment Icons
                            </div>
                            <div class="accordion-section-content px-4 pb-4 bg-slate-800">
                                <div class="mb-3">
                                    <label class="block text-xs font-medium text-slate-300 mb-1" for="payment_icons_title">Title</label>
                                    <input type="text" name="payment_icons_title" id="payment_icons_title" value="<?= val('payment_icons_title') ?>" 
                                           placeholder="We Accept"
                                           class="w-full px-2 py-1.5 bg-slate-700 border border-slate-600 rounded text-xs text-slate-100 focus:outline-none focus:border-amber-500 focus:ring-1 focus:ring-amber-500" data-autosave>
                                </div>
                                <div class="mb-3">
                                    <label class="block text-xs font-medium text-slate-300 mb-1" for="payment_icons_layout">Layout</label>
                                    <select name="payment_icons_layout" id="payment_icons_layout" class="w-full px-2 py-1.5 bg-slate-700 border border-slate-600 rounded text-xs text-slate-100 focus:outline-none focus:border-amber-500 focus:ring-1 focus:ring-amber-500" data-autosave>
                                        <?php foreach(['row'=>'Horizontal Row','grid'=>'Grid'] as $k=>$lbl): ?>
                                        <option value="<?= $k ?>" <?= sel('payment_icons_layout', $k) ?>><?= $lbl ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="space-y-2">
                                    <span class="block text-xs font-medium text-slate-300">Show Icons</span>
                                    <?php 
                                    $payment_icons = [
                                        'payment_icons_visa' => 'Visa',
                                        'payment_icons_mastercard' => 'Mastercard',
                                        'payment_icons_amex' => 'American Express',
                                        'payment_icons_paypal' => 'PayPal',
                                        'payment_icons_mpesa' => 'M-Pesa'
                                    ];
                                    foreach ($payment_icons as $key => $label): ?>
                                    <label class="customize-toggle flex items-center justify-between cursor-pointer">
                                        <span class="text-xs text-slate-300"><?= $label ?></span>
                                        <span>
                                            <input type="checkbox" name="<?= $key ?>" value="1" <?= chk($key) ?> data-autosave>
                                            <span class="track block w-9 h-4.5 bg-slate-600 rounded-full relative transition-colors">
                                                <span class="thumb block w-3.5 h-3.5 bg-slate-800 rounded-full absolute top-0.5 left-0.5 transition-all shadow-sm"></span>
                                            </span>
                                        </span>
                                    </label>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </li>

                        <!-- Social Links -->
                        <li class="accordion-section border-b border-slate-700 bg-slate-800">
                            <div class="accordion-section-title flex items-center px-4 py-2.5 text-sm font-semibold text-slate-100 cursor-pointer border-l-4 border-transparent hover:bg-slate-700/50 transition-colors" onclick="toggleSection(this)">
                                <i class="fas fa-share-alt w-5 text-slate-500"></i> Social Links
                            </div>
                            <div class="accordion-section-content px-4 pb-4 bg-slate-800">
                                <div class="mb-3">
                                    <label class="block text-xs font-medium text-slate-300 mb-1" for="social_links_title">Title</label>
                                    <input type="text" name="social_links_title" id="social_links_title" value="<?= val('social_links_title') ?>" 
                                           placeholder="Follow Us"
                                           class="w-full px-2 py-1.5 bg-slate-700 border border-slate-600 rounded text-xs text-slate-100 focus:outline-none focus:border-amber-500 focus:ring-1 focus:ring-amber-500" data-autosave>
                                </div>
                                <div class="mb-3">
                                    <label class="block text-xs font-medium text-slate-300 mb-1" for="social_links_style">Style</label>
                                    <select name="social_links_style" id="social_links_style" class="w-full px-2 py-1.5 bg-slate-700 border border-slate-600 rounded text-xs text-slate-100 focus:outline-none focus:border-amber-500 focus:ring-1 focus:ring-amber-500" data-autosave>
                                        <?php foreach(['icon-only'=>'Icon Only','icon-with-label'=>'Icon with Label','pill'=>'Pill'] as $k=>$lbl): ?>
                                        <option value="<?= $k ?>" <?= sel('social_links_style', $k) ?>><?= $lbl ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label class="block text-xs font-medium text-slate-300 mb-1" for="social_links_align">Alignment</label>
                                    <select name="social_links_align" id="social_links_align" class="w-full px-2 py-1.5 bg-slate-700 border border-slate-600 rounded text-xs text-slate-100 focus:outline-none focus:border-amber-500 focus:ring-1 focus:ring-amber-500" data-autosave>
                                        <?php foreach(['left'=>'Left','center'=>'Center','right'=>'Right'] as $k=>$lbl): ?>
                                        <option value="<?= $k ?>" <?= sel('social_links_align', $k) ?>><?= $lbl ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="space-y-2">
                                    <span class="block text-xs font-medium text-slate-300">Show Platforms</span>
                                    <?php 
                                    $social_platforms = [
                                        'social_links_facebook' => 'Facebook',
                                        'social_links_instagram' => 'Instagram',
                                        'social_links_twitter' => 'Twitter / X',
                                        'social_links_tiktok' => 'TikTok',
                                        'social_links_youtube' => 'YouTube',
                                        'social_links_whatsapp' => 'WhatsApp'
                                    ];
                                    foreach ($social_platforms as $key => $label): ?>
                                    <label class="customize-toggle flex items-center justify-between cursor-pointer">
                                        <span class="text-xs text-slate-300"><?= $label ?></span>
                                        <span>
                                            <input type="checkbox" name="<?= $key ?>" value="1" <?= chk($key) ?> data-autosave>
                                            <span class="track block w-9 h-4.5 bg-slate-600 rounded-full relative transition-colors">
                                                <span class="thumb block w-3.5 h-3.5 bg-slate-800 rounded-full absolute top-0.5 left-0.5 transition-all shadow-sm"></span>
                                            </span>
                                        </span>
                                    </label>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </li>

                        <!-- Footer Links -->
                        <li class="accordion-section border-b border-slate-700 bg-slate-800">
                            <div class="accordion-section-title flex items-center px-4 py-2.5 text-sm font-semibold text-slate-100 cursor-pointer border-l-4 border-transparent hover:bg-slate-700/50 transition-colors" onclick="toggleSection(this)">
                                <i class="fas fa-list w-5 text-slate-500"></i> Footer Links
                            </div>
                            <div class="accordion-section-content px-4 pb-4 bg-slate-800">
                                <div class="mb-3">
                                    <label class="block text-xs font-medium text-slate-300 mb-1" for="footer_bottom_text">Bottom Text</label>
                                    <textarea name="footer_bottom_text" id="footer_bottom_text" rows="2" placeholder="© 2026 My Store. All rights reserved."
                                              class="w-full px-2 py-1.5 bg-slate-700 border border-slate-600 rounded text-xs text-slate-100 focus:outline-none focus:border-amber-500 focus:ring-1 focus:ring-amber-500 resize-y" data-autosave><?= val('footer_bottom_text') ?></textarea>
                                </div>
                                <div class="mb-3">
                                    <label class="customize-toggle flex items-center justify-between cursor-pointer">
                                        <span class="text-xs font-medium text-slate-300">Show Payment Icons</span>
                                        <span>
                                            <input type="checkbox" name="footer_show_payment_icons" value="1" <?= chk('footer_show_payment_icons') ?> data-autosave>
                                            <span class="track block w-9 h-4.5 bg-slate-600 rounded-full relative transition-colors">
                                                <span class="thumb block w-3.5 h-3.5 bg-slate-800 rounded-full absolute top-0.5 left-0.5 transition-all shadow-sm"></span>
                                            </span>
                                        </span>
                                    </label>
                                </div>
                                <div>
                                    <label class="customize-toggle flex items-center justify-between cursor-pointer">
                                        <span class="text-xs font-medium text-slate-300">Show Social Links</span>
                                        <span>
                                            <input type="checkbox" name="footer_show_social_links" value="1" <?= chk('footer_show_social_links') ?> data-autosave>
                                            <span class="track block w-9 h-4.5 bg-slate-600 rounded-full relative transition-colors">
                                                <span class="thumb block w-3.5 h-3.5 bg-slate-800 rounded-full absolute top-0.5 left-0.5 transition-all shadow-sm"></span>
                                            </span>
                                        </span>
                                    </label>
                                </div>
                            </div>
                        </li>

                        <!-- Page Builder -->
                        <li class="accordion-section border-b border-slate-700 bg-slate-800">
                            <div class="accordion-section-title flex items-center px-4 py-2.5 text-sm font-semibold text-slate-100 cursor-pointer border-l-4 border-transparent hover:bg-slate-700/50 transition-colors" onclick="toggleSection(this)">
                                <i class="fas fa-cubes w-5 text-slate-500"></i> Page Builder
                            </div>
                            <div class="accordion-section-content px-4 pb-4 bg-slate-800">
                                <p class="text-xs text-slate-400 mb-2.5">Manage custom content blocks for your storefront.</p>
                                <a href="blocks.php" class="inline-block px-3 py-1.5 bg-amber-500 text-white rounded text-xs font-semibold hover:bg-amber-600 transition-colors">
                                    Manage Blocks <?= $blocks_count ? "($blocks_count active)" : '' ?> →
                                </a>
                            </div>
                        </li>

                    </ul>

                    <!-- Header Sub-Panel -->
                    <div class="customize-pane-child accordion-sub-container" id="sub-accordion-panel-header">
                        <ul class="list-none">
                            <!-- Panel Header -->
                            <li class="panel-meta border-b border-slate-700 bg-slate-800">
                                <div class="flex items-center px-4 py-3">
                                    <button class="customize-panel-back w-6 h-6 border-0 bg-transparent text-slate-300 cursor-pointer flex items-center justify-center rounded hover:bg-slate-700 hover:text-white transition-colors mr-1.5" type="button" onclick="closeHeaderPanel()">
                                        <i class="fas fa-arrow-left text-xs"></i>
                                    </button>
                                    <span class="text-sm font-semibold text-slate-100">You are customizing <strong class="text-amber-400">Header</strong></span>
                                </div>
                                <div class="px-4 pb-3 text-xs text-slate-400">Change Theme Header Options here.</div>
                            </li>

                            <!-- Header Presets -->
                            <li class="accordion-section border-b border-slate-700 bg-slate-800">
                                <div class="accordion-section-title flex items-center px-4 py-2 text-sm font-semibold text-slate-100 cursor-pointer border-l-3 border-transparent hover:bg-slate-700/50 transition-colors" onclick="toggleNestedSection(this)">
                                    Presets
                                </div>
                                <div class="accordion-section-content px-4 pb-4 bg-slate-800">
                                    <p class="text-[11px] text-slate-400 mb-2">You can quick change between header presets here.</p>
                                    <div class="preset-thumbs grid grid-cols-2 gap-2">
                                        <?php
                                        $headerPresets = [
                                            ['Default','standard','1','0','#ffffff','dark','0','','#1a1a2e','light','1','1','1','0','overlay','simple','light','light','dark'],
                                            ['Default Center','centered','1','0','#ffffff','dark','0','','#1a1a2e','light','1','1','1','0','overlay','simple','light','dark','light'],
                                            ['Default Dark','standard','1','0','#1a1a2e','light','0','','#1a1a2e','light','1','1','1','0','overlay','simple','dark','dark','light'],
                                            ['Wide Nav','modern','1','0','#ffffff','dark','1','Free shipping on orders over KES 5,000','#f68b1e','light','1','1','1','0','slideout','mega','light','light','dark'],
                                            ['Wide Nav Dark','modern','1','0','#1a1a2e','light','1','Free shipping on orders over KES 5,000','#f68b1e','light','1','1','1','0','slideout','mega','dark','dark','light'],
                                            ['Simple','minimal','0','0','#ffffff','dark','0','','#1a1a2e','light','1','0','1','0','dropdown','simple','light','light','dark'],
                                            ['Simple Signup','minimal','0','0','#ffffff','dark','1','Sign up and get 10% off your first order','#f68b1e','light','0','1','1','0','dropdown','simple','light','light','dark'],
                                            ['Simple Right','minimal','0','0','#ffffff','dark','0','','#1a1a2e','light','0','1','1','1','dropdown','simple','light','light','dark'],
                                            ['Cart Top','standard','1','0','#f68b1e','light','1','Free delivery on orders over KES 5,000','#1a1a2e','light','1','1','0','0','overlay','boxed','accent','dark','light']
                                        ];
                                        $presetMinis = [
                                            '<div class="bar light flex items-center gap-0.5 h-3 px-1 rounded"><span class="dot w-1 h-1 rounded-sm bg-slate-500"></span><span class="spacer flex-1"></span><span class="line short w-3 h-0.5 rounded-sm bg-slate-300"></span><span class="dot w-1 h-1 rounded-sm bg-slate-500"></span><span class="dot w-1 h-1 rounded-sm bg-slate-500"></span></div><div class="bar light flex items-center gap-0.5 h-3 px-1 rounded"><span class="line mid w-5 h-0.5 rounded-sm bg-slate-300"></span><span class="spacer flex-1"></span><span class="line short w-3 h-0.5 rounded-sm bg-slate-300"></span></div>',
                                            '<div class="bar light flex items-center gap-0.5 h-3 px-1 rounded"><span class="spacer flex-1"></span><span class="dot w-1 h-1 rounded-sm bg-slate-500"></span><span class="spacer flex-1"></span></div><div class="bar light flex items-center gap-0.5 h-3 px-1 rounded"><span class="spacer flex-1"></span><span class="line mid w-5 h-0.5 rounded-sm bg-slate-300"></span><span class="spacer flex-1"></span></div>',
                                            '<div class="bar dark flex items-center gap-0.5 h-3 px-1 rounded bg-slate-800"><span class="dot w-1 h-1 rounded-sm bg-slate-300"></span><span class="spacer flex-1"></span><span class="line short w-3 h-0.5 rounded-sm bg-slate-500"></span><span class="dot w-1 h-1 rounded-sm bg-slate-300"></span><span class="dot w-1 h-1 rounded-sm bg-slate-300"></span></div><div class="bar dark flex items-center gap-0.5 h-3 px-1 rounded bg-slate-800"><span class="line mid w-5 h-0.5 rounded-sm bg-slate-500"></span><span class="spacer flex-1"></span><span class="line short w-3 h-0.5 rounded-sm bg-slate-500"></span></div>',
                                            '<div class="bar light flex items-center gap-0.5 h-3 px-1 rounded"><span class="dot w-1 h-1 rounded-sm bg-slate-500"></span><span class="line short w-3 h-0.5 rounded-sm bg-slate-300"></span><span class="spacer flex-1"></span><span class="dot w-1 h-1 rounded-sm bg-slate-500"></span><span class="dot w-1 h-1 rounded-sm bg-slate-500"></span></div><div class="bar light flex items-center gap-0.5 h-3 px-1 rounded"><span class="line long flex-1 h-0.5 rounded-sm bg-slate-300"></span><span class="spacer flex-1"></span><span class="line short w-3 h-0.5 rounded-sm bg-slate-300"></span></div>',
                                            '<div class="bar dark flex items-center gap-0.5 h-3 px-1 rounded bg-slate-800"><span class="dot w-1 h-1 rounded-sm bg-slate-300"></span><span class="line short w-3 h-0.5 rounded-sm bg-slate-500"></span><span class="spacer flex-1"></span><span class="dot w-1 h-1 rounded-sm bg-slate-300"></span><span class="dot w-1 h-1 rounded-sm bg-slate-300"></span></div><div class="bar dark flex items-center gap-0.5 h-3 px-1 rounded bg-slate-800"><span class="line long flex-1 h-0.5 rounded-sm bg-slate-500"></span><span class="spacer flex-1"></span><span class="line short w-3 h-0.5 rounded-sm bg-slate-500"></span></div>',
                                            '<div class="bar light flex items-center gap-0.5 h-3 px-1 rounded"><span class="dot w-1 h-1 rounded-sm bg-slate-500"></span><span class="spacer flex-1"></span><span class="line short w-3 h-0.5 rounded-sm bg-slate-300"></span></div>',
                                            '<div class="bar accent flex items-center gap-0.5 h-3 px-1 rounded bg-amber-500"><span class="spacer flex-1"></span><span class="line mid w-5 h-0.5 rounded-sm bg-white/60"></span><span class="spacer flex-1"></span></div><div class="bar light flex items-center gap-0.5 h-3 px-1 rounded"><span class="dot w-1 h-1 rounded-sm bg-slate-500"></span><span class="spacer flex-1"></span><span class="line short w-3 h-0.5 rounded-sm bg-slate-300"></span></div>',
                                            '<div class="bar light flex items-center gap-0.5 h-3 px-1 rounded"><span class="dot w-1 h-1 rounded-sm bg-slate-500"></span><span class="spacer flex-1"></span><span class="line short w-3 h-0.5 rounded-sm bg-slate-300"></span><span class="dot w-1 h-1 rounded-sm bg-slate-500"></span><span class="dot w-1 h-1 rounded-sm bg-slate-500"></span></div>',
                                            '<div class="bar accent flex items-center gap-0.5 h-3 px-1 rounded bg-amber-500"><span class="spacer flex-1"></span><span class="line mid w-5 h-0.5 rounded-sm bg-white/60"></span><span class="spacer flex-1"></span></div><div class="bar light flex items-center gap-0.5 h-3 px-1 rounded"><span class="dot w-1 h-1 rounded-sm bg-slate-500"></span><span class="line short w-3 h-0.5 rounded-sm bg-slate-300"></span><span class="spacer flex-1"></span><span class="dot w-1 h-1 rounded-sm bg-slate-500"></span></div>'
                                        ];
                                        foreach ($headerPresets as $i => $hp):
                                        ?>
                                        <div class="preset-thumb p-2 border border-slate-600 rounded bg-slate-800 cursor-pointer hover:border-amber-500 transition-colors" onclick="applyHeaderPreset('<?= implode("','", $hp) ?>')">
                                            <div class="preset-mini flex flex-col gap-0.5 h-10 overflow-hidden"><?= $presetMinis[$i] ?? '' ?></div>
                                            <div class="preset-thumb-label text-[10px] text-slate-400 text-center mt-0.5"><?= $hp[0] ?></div>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                    <div class="mt-2.5">
                                        <button type="button" class="px-3 py-1.5 bg-slate-700 border border-slate-600 rounded text-xs text-slate-300 hover:bg-slate-600 hover:text-white transition-colors cursor-pointer" onclick="openHeaderBuilder()">
                                            <i class="fas fa-tools mr-1"></i> Open Header Builder
                                        </button>
                                    </div>
                                </div>
                            </li>

                            <!-- Header Elements -->
                            <li class="accordion-section border-b border-slate-700 bg-slate-800">
                                <div class="accordion-section-title flex items-center px-4 py-2 text-sm font-semibold text-slate-100 cursor-pointer border-l-3 border-transparent hover:bg-slate-700/50 transition-colors" onclick="toggleNestedSection(this)">
                                    Elements
                                </div>
                                <div class="accordion-section-content px-4 pb-4 bg-slate-800">
                                    <div class="mb-3">
                                        <label class="block text-xs font-medium text-slate-300 mb-1" for="headerStyle">Header Style</label>
                                        <select name="header_style" id="headerStyle" class="w-full px-2 py-1.5 bg-slate-700 border border-slate-600 rounded text-xs text-slate-100 focus:outline-none focus:border-amber-500 focus:ring-1 focus:ring-amber-500" data-autosave>
                                            <?php foreach(['standard'=>'Standard','minimal'=>'Minimal','centered'=>'Centered Logo','modern'=>'Modern'] as $k=>$lbl): ?>
                                            <option value="<?= $k ?>" <?= sel('header_style', $k) ?>><?= $lbl ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="mb-3">
                                        <label class="customize-toggle flex items-center justify-between cursor-pointer">
                                            <span class="text-xs font-medium text-slate-300">Transparent on Homepage</span>
                                            <span>
                                                <input type="checkbox" name="header_transparent_home" id="headerTransparentHome" value="1" <?= chk('header_transparent_home') ?> data-autosave>
                                                <span class="track block w-9 h-4.5 bg-slate-600 rounded-full relative transition-colors">
                                                    <span class="thumb block w-3.5 h-3.5 bg-slate-800 rounded-full absolute top-0.5 left-0.5 transition-all shadow-sm"></span>
                                                </span>
                                            </span>
                                        </label>
                                    </div>
                                    <div class="mb-3">
                                        <label class="block text-xs font-medium text-slate-300 mb-1">Header Background</label>
                                        <div class="color-picker-row flex items-center gap-2">
                                            <input type="color" name="header_bg_color" id="headerBgColor" value="<?= val('header_bg_color') ?>" 
                                                   class="w-8 h-8 p-0 border border-slate-600 rounded cursor-pointer" oninput="syncHex(this)" data-autosave>
                                            <input type="text" id="headerBgColorHex" value="<?= val('header_bg_color') ?>" 
                                                   class="flex-1 px-2 py-1.5 bg-slate-700 border border-slate-600 rounded text-xs font-mono text-slate-100 focus:outline-none focus:border-amber-500 focus:ring-1 focus:ring-amber-500"
                                                   onchange="syncColorFromHex(this)" data-autosave>
                                        </div>
                                    </div>
                                    <div>
                                        <label class="block text-xs font-medium text-slate-300 mb-1" for="headerTextColor">Header Text Color</label>
                                        <select name="header_text_color" id="headerTextColor" class="w-full px-2 py-1.5 bg-slate-700 border border-slate-600 rounded text-xs text-slate-100 focus:outline-none focus:border-amber-500 focus:ring-1 focus:ring-amber-500" data-autosave>
                                            <?php foreach(['dark'=>'Dark','light'=>'Light'] as $k=>$lbl): ?>
                                            <option value="<?= $k ?>" <?= sel('header_text_color', $k) ?>><?= $lbl ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                            </li>

                            <!-- Top Bar -->
                            <li class="accordion-section border-b border-slate-700 bg-slate-800">
                                <div class="accordion-section-title flex items-center px-4 py-2 text-sm font-semibold text-slate-100 cursor-pointer border-l-3 border-transparent hover:bg-slate-700/50 transition-colors" onclick="toggleNestedSection(this)">
                                    Top Bar
                                </div>
                                <div class="accordion-section-content px-4 pb-4 bg-slate-800">
                                    <div class="mb-3">
                                        <label class="customize-toggle flex items-center justify-between cursor-pointer">
                                            <span class="text-xs font-medium text-slate-300">Show Top Bar</span>
                                            <span>
                                                <input type="checkbox" name="header_top_bar" id="headerTopBar" value="1" <?= chk('header_top_bar') ?> data-autosave>
                                                <span class="track block w-9 h-4.5 bg-slate-600 rounded-full relative transition-colors">
                                                    <span class="thumb block w-3.5 h-3.5 bg-slate-800 rounded-full absolute top-0.5 left-0.5 transition-all shadow-sm"></span>
                                                </span>
                                            </span>
                                        </label>
                                    </div>
                                    <div class="mb-3">
                                        <label class="block text-xs font-medium text-slate-300 mb-1" for="headerTopBarText">Top Bar Text</label>
                                        <input type="text" name="header_top_bar_text" id="headerTopBarText" value="<?= val('header_top_bar_text') ?>" 
                                               placeholder="Free shipping on orders over KES 5,000"
                                               class="w-full px-2 py-1.5 bg-slate-700 border border-slate-600 rounded text-xs text-slate-100 focus:outline-none focus:border-amber-500 focus:ring-1 focus:ring-amber-500" data-autosave>
                                    </div>
                                    <div class="mb-3">
                                        <label class="block text-xs font-medium text-slate-300 mb-1">Top Bar Background</label>
                                        <div class="color-picker-row flex items-center gap-2">
                                            <input type="color" name="header_top_bar_bg" id="headerTopBarBg" value="<?= val('header_top_bar_bg') ?>" 
                                                   class="w-8 h-8 p-0 border border-slate-600 rounded cursor-pointer" oninput="syncHex(this)" data-autosave>
                                            <input type="text" id="headerTopBarBgHex" value="<?= val('header_top_bar_bg') ?>" 
                                                   class="flex-1 px-2 py-1.5 bg-slate-700 border border-slate-600 rounded text-xs font-mono text-slate-100 focus:outline-none focus:border-amber-500 focus:ring-1 focus:ring-amber-500"
                                                   onchange="syncColorFromHex(this)" data-autosave>
                                        </div>
                                    </div>
                                    <div>
                                        <label class="block text-xs font-medium text-slate-300 mb-1" for="header_top_bar_text_color">Text Color</label>
                                        <select name="header_top_bar_text_color" id="header_top_bar_text_color" class="w-full px-2 py-1.5 bg-slate-700 border border-slate-600 rounded text-xs text-slate-100 focus:outline-none focus:border-amber-500 focus:ring-1 focus:ring-amber-500" data-autosave>
                                            <?php foreach(['light'=>'Light','dark'=>'Dark'] as $k=>$lbl): ?>
                                            <option value="<?= $k ?>" <?= sel('header_top_bar_text_color', $k) ?>><?= $lbl ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                            </li>

                            <!-- Mobile Menu -->
                            <li class="accordion-section border-b border-slate-700 bg-slate-800">
                                <div class="accordion-section-title flex items-center px-4 py-2 text-sm font-semibold text-slate-100 cursor-pointer border-l-3 border-transparent hover:bg-slate-700/50 transition-colors" onclick="toggleNestedSection(this)">
                                    Mobile Menu
                                </div>
                                <div class="accordion-section-content px-4 pb-4 bg-slate-800">
                                    <div>
                                        <label class="block text-xs font-medium text-slate-300 mb-1" for="headerMobileStyle">Mobile Menu Style</label>
                                        <select name="header_mobile_style" id="headerMobileStyle" class="w-full px-2 py-1.5 bg-slate-700 border border-slate-600 rounded text-xs text-slate-100 focus:outline-none focus:border-amber-500 focus:ring-1 focus:ring-amber-500" data-autosave>
                                            <?php foreach(['overlay'=>'Fullscreen Overlay','slideout'=>'Side Slideout','dropdown'=>'Simple Dropdown'] as $k=>$lbl): ?>
                                            <option value="<?= $k ?>" <?= sel('header_mobile_style', $k) ?>><?= $lbl ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                            </li>

                            <!-- Sticky Header -->
                            <li class="accordion-section border-b border-slate-700 bg-slate-800">
                                <div class="accordion-section-title flex items-center px-4 py-2 text-sm font-semibold text-slate-100 cursor-pointer border-l-3 border-transparent hover:bg-slate-700/50 transition-colors" onclick="toggleNestedSection(this)">
                                    Sticky Header
                                </div>
                                <div class="accordion-section-content px-4 pb-4 bg-slate-800">
                                    <div>
                                        <label class="customize-toggle flex items-center justify-between cursor-pointer">
                                            <span class="text-xs font-medium text-slate-300">Sticky Header</span>
                                            <span>
                                                <input type="checkbox" name="header_sticky" id="headerSticky" value="1" <?= chk('header_sticky') ?> data-autosave>
                                                <span class="track block w-9 h-4.5 bg-slate-600 rounded-full relative transition-colors">
                                                    <span class="thumb block w-3.5 h-3.5 bg-slate-800 rounded-full absolute top-0.5 left-0.5 transition-all shadow-sm"></span>
                                                </span>
                                            </span>
                                        </label>
                                    </div>
                                </div>
                            </li>

                            <!-- Dropdown Style -->
                            <li class="accordion-section border-b border-slate-700 bg-slate-800">
                                <div class="accordion-section-title flex items-center px-4 py-2 text-sm font-semibold text-slate-100 cursor-pointer border-l-3 border-transparent hover:bg-slate-700/50 transition-colors" onclick="toggleNestedSection(this)">
                                    Dropdown Style
                                </div>
                                <div class="accordion-section-content px-4 pb-4 bg-slate-800">
                                    <div>
                                        <label class="block text-xs font-medium text-slate-300 mb-1" for="headerDropdownStyle">Dropdown Style</label>
                                        <select name="header_dropdown_style" id="headerDropdownStyle" class="w-full px-2 py-1.5 bg-slate-700 border border-slate-600 rounded text-xs text-slate-100 focus:outline-none focus:border-amber-500 focus:ring-1 focus:ring-amber-500" data-autosave>
                                            <?php foreach(['simple'=>'Simple','boxed'=>'Boxed','mega'=>'Mega Menu'] as $k=>$lbl): ?>
                                            <option value="<?= $k ?>" <?= sel('header_dropdown_style', $k) ?>><?= $lbl ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                            </li>

                            <!-- Header Buttons (Show/Hide) -->
                            <li class="accordion-section border-b border-slate-700 bg-slate-800">
                                <div class="accordion-section-title flex items-center px-4 py-2 text-sm font-semibold text-slate-100 cursor-pointer border-l-3 border-transparent hover:bg-slate-700/50 transition-colors" onclick="toggleNestedSection(this)">
                                    Header Buttons
                                </div>
                                <div class="accordion-section-content px-4 pb-4 bg-slate-800">
                                    <div class="mb-3">
                                        <label class="customize-toggle flex items-center justify-between cursor-pointer">
                                            <span class="text-xs font-medium text-slate-300">Show Account Icon</span>
                                            <span>
                                                <input type="checkbox" name="header_show_account" id="headerShowAccount" value="1" <?= chk('header_show_account') ?> data-autosave>
                                                <span class="track block w-9 h-4.5 bg-slate-600 rounded-full relative transition-colors">
                                                    <span class="thumb block w-3.5 h-3.5 bg-slate-800 rounded-full absolute top-0.5 left-0.5 transition-all shadow-sm"></span>
                                                </span>
                                            </span>
                                        </label>
                                    </div>
                                    <div class="mb-3">
                                        <label class="customize-toggle flex items-center justify-between cursor-pointer">
                                            <span class="text-xs font-medium text-slate-300">Show Cart Icon</span>
                                            <span>
                                                <input type="checkbox" name="header_show_cart" id="headerShowCart" value="1" <?= chk('header_show_cart') ?> data-autosave>
                                                <span class="track block w-9 h-4.5 bg-slate-600 rounded-full relative transition-colors">
                                                    <span class="thumb block w-3.5 h-3.5 bg-slate-800 rounded-full absolute top-0.5 left-0.5 transition-all shadow-sm"></span>
                                                </span>
                                            </span>
                                        </label>
                                    </div>
                                    <div class="mb-3">
                                        <label class="customize-toggle flex items-center justify-between cursor-pointer">
                                            <span class="text-xs font-medium text-slate-300">Show Search Icon</span>
                                            <span>
                                                <input type="checkbox" name="header_show_search" id="headerShowSearch" value="1" <?= chk('header_show_search') ?> data-autosave>
                                                <span class="track block w-9 h-4.5 bg-slate-600 rounded-full relative transition-colors">
                                                    <span class="thumb block w-3.5 h-3.5 bg-slate-800 rounded-full absolute top-0.5 left-0.5 transition-all shadow-sm"></span>
                                                </span>
                                            </span>
                                        </label>
                                    </div>
                                    <div>
                                        <label class="customize-toggle flex items-center justify-between cursor-pointer">
                                            <span class="text-xs font-medium text-slate-300">Show Wishlist Icon</span>
                                            <span>
                                                <input type="checkbox" name="header_show_wishlist" id="headerShowWishlist" value="1" <?= chk('header_show_wishlist') ?> data-autosave>
                                                <span class="track block w-9 h-4.5 bg-slate-600 rounded-full relative transition-colors">
                                                    <span class="thumb block w-3.5 h-3.5 bg-slate-800 rounded-full absolute top-0.5 left-0.5 transition-all shadow-sm"></span>
                                                </span>
                                            </span>
                                        </label>
                                    </div>
                                </div>
                            </li>
                        </ul>
                    </div>

                    <!-- Publish Settings Panel -->
                    <ul class="customize-pane-child" id="sub-accordion-section-publish_settings">
                        <li class="customize-section-description-container section-meta border-b border-slate-700 bg-slate-800">
                            <div class="flex items-center px-4 py-3">
                                <button class="customize-section-back w-6 h-6 border-0 bg-transparent text-slate-300 cursor-pointer flex items-center justify-center rounded hover:bg-slate-700 hover:text-white transition-colors mr-1.5" type="button" onclick="closePublishSettings()">
                                    <i class="fas fa-arrow-left text-xs"></i>
                                </button>
                                <div>
                                    <span class="text-[11px] text-slate-400 block">Updating</span>
                                    <h3 class="text-sm font-semibold text-slate-100">Publish Settings</h3>
                                </div>
                            </div>
                        </li>
                        <li class="px-4 py-3 border-b border-slate-700 bg-slate-800">
                            <span class="block text-xs font-medium text-slate-300 mb-2">Action</span>
                            <div class="space-y-1.5">
                                <label class="flex items-center gap-2 text-xs text-slate-200">
                                    <input type="radio" name="publish_action" value="publish" checked onchange="toggleSchedule(this)"> Publish
                                </label>
                                <label class="flex items-center gap-2 text-xs text-slate-200">
                                    <input type="radio" name="publish_action" value="draft" onchange="toggleSchedule(this)"> Save Draft
                                </label>
                                <label class="flex items-center gap-2 text-xs text-slate-200">
                                    <input type="radio" name="publish_action" value="future" onchange="toggleSchedule(this)"> Schedule
                                </label>
                            </div>
                        </li>
                        <li class="px-4 py-3 border-b border-slate-700 bg-slate-800" id="scheduleFields" style="display:none;">
                            <p class="text-[11px] text-slate-400 mb-2">Schedule your customization changes to publish at a future date.</p>
                            <div class="space-y-2">
                                <div class="flex items-center gap-2 flex-wrap">
                                    <select id="schedMonth" class="px-2 py-1 bg-slate-700 border border-slate-600 rounded text-xs text-slate-100">
                                        <?php for ($m = 1; $m <= 12; $m++): ?>
                                        <option value="<?= $m ?>" <?= $m == date('n') ? 'selected' : '' ?>><?= $m ?>-<?= date('M', mktime(0,0,0,$m,1)) ?></option>
                                        <?php endfor; ?>
                                    </select>
                                    <input id="schedDay" type="number" size="2" class="w-12 px-1 py-1 bg-slate-700 border border-slate-600 rounded text-xs text-slate-100" min="1" max="31" value="<?= date('j') ?>">
                                    <input id="schedYear" type="number" size="4" class="w-16 px-1 py-1 bg-slate-700 border border-slate-600 rounded text-xs text-slate-100" min="2026" max="9999" value="<?= date('Y') ?>">
                                    <span class="text-slate-500 text-xs">@</span>
                                    <input id="schedHour" type="number" size="2" class="w-12 px-1 py-1 bg-slate-700 border border-slate-600 rounded text-xs text-slate-100" min="1" max="12" value="<?= date('g') ?>">
                                    <span class="text-slate-500 text-xs">:</span>
                                    <input id="schedMinute" type="number" size="2" class="w-12 px-1 py-1 bg-slate-700 border border-slate-600 rounded text-xs text-slate-100" min="0" max="59" value="<?= date('i') ?>">
                                    <select id="schedMeridian" class="px-2 py-1 bg-slate-700 border border-slate-600 rounded text-xs text-slate-100">
                                        <option value="am">AM</option>
                                        <option value="pm">PM</option>
                                    </select>
                                </div>
                                <p class="text-[10px] text-slate-500">Your timezone is set to UTC+0.</p>
                            </div>
                        </li>
                        <li class="px-4 py-3 border-b border-slate-700 bg-slate-800">
                            <button type="button" class="text-xs text-red-400 hover:text-red-300 transition-colors" onclick="if(confirm('Discard all unsaved changes?'))location.reload()">
                                Discard changes
                            </button>
                        </li>
                        <li class="px-4 py-3 bg-slate-800">
                            <span class="block text-xs font-medium text-slate-300 mb-1">Share Preview Link</span>
                            <p class="text-[11px] text-slate-400 mb-2">See how changes would look live on your website.</p>
                            <div class="flex items-center gap-2 bg-slate-700 border border-slate-600 rounded p-1.5">
                                <input id="previewLinkInput" readonly class="flex-1 bg-transparent border-0 text-xs font-mono text-slate-300 outline-none" value="<?= htmlspecialchars($store_url . '?preview=1') ?>">
                                <button type="button" class="px-2.5 py-1 bg-slate-600 border border-slate-500 rounded text-xs text-slate-200 hover:bg-slate-500 hover:text-white transition-colors" onclick="copyPreviewLink()">Copy</button>
                            </div>
                        </li>
                    </ul>

                </div>
            </div>

            <!-- Footer -->
            <div class="h-12 border-t border-slate-700 bg-slate-800 flex items-center justify-between px-4 flex-shrink-0">
                <button type="button" class="collapse-sidebar flex items-center gap-1.5 bg-transparent border-0 text-slate-300 text-xs cursor-pointer hover:text-white transition-colors" onclick="toggleSidebar()">
                    <span class="collapse-sidebar-arrow inline-block w-0 h-0 border-t-[4px] border-t-transparent border-b-[4px] border-b-transparent border-r-[6px] border-r-current transition-transform"></span>
                    <span class="collapse-sidebar-label">Hide Controls</span>
                </button>
                <div class="devices-wrapper flex items-center gap-0.5">
                    <div class="devices flex gap-0.5">
                        <button type="button" class="preview-desktop active w-8 h-7 border-0 bg-transparent rounded text-slate-500 text-sm cursor-pointer flex items-center justify-center hover:text-slate-300 transition-colors" data-device="desktop" onclick="setDevice(this)" title="Desktop">
                            <i class="fas fa-desktop"></i>
                        </button>
                        <button type="button" class="preview-tablet w-8 h-7 border-0 bg-transparent rounded text-slate-500 text-sm cursor-pointer flex items-center justify-center hover:text-slate-300 transition-colors" data-device="tablet" onclick="setDevice(this)" title="Tablet">
                            <i class="fas fa-tablet-alt"></i>
                        </button>
                        <button type="button" class="preview-mobile w-8 h-7 border-0 bg-transparent rounded text-slate-500 text-sm cursor-pointer flex items-center justify-center hover:text-slate-300 transition-colors" data-device="mobile" onclick="setDevice(this)" title="Mobile">
                            <i class="fas fa-mobile-alt"></i>
                        </button>
                    </div>
                </div>
            </div>

        </form>

        <!-- Preview Area -->
        <div class="wp-full-overlay-main flex-1 flex items-center justify-center bg-slate-800 p-4 relative transition-all">
            <div class="device-frame desktop bg-slate-800 border border-slate-700 rounded-none shadow-none w-full h-full" id="deviceFrame">
                <iframe id="previewFrame" src="<?= htmlspecialchars($store_url) ?>" class="w-full h-full border-0 block" onload="window._previewLoaded=true;document.getElementById('cspFallback').classList.remove('show');flushPreviewQueue();"></iframe>
            </div>
            <div id="cspFallback" class="absolute inset-0 flex-col items-center justify-center text-center p-8 bg-slate-800 z-20 hidden">
                <div class="text-5xl mb-4">🖥️</div>
                <h3 class="text-lg text-slate-100 mb-2">Preview Blocked</h3>
                <p class="text-sm text-slate-400 max-w-sm mb-4">Your server blocks framing external pages. Open the storefront directly.</p>
                <div class="flex gap-2.5">
                    <a href="<?= htmlspecialchars($store_url) ?>" target="_blank" class="inline-flex items-center gap-1.5 px-5 py-2.5 bg-amber-500 text-white rounded text-sm font-semibold hover:bg-amber-600 transition-colors no-underline">
                        <i class="fas fa-external-link-alt"></i> Open Storefront
                    </a>
                </div>
            </div>
        </div>

    </div>

    <!-- Show Controls Button -->
    <button type="button" id="showControlsFab" class="fixed left-0 top-1/2 -translate-y-1/2 bg-slate-800 text-slate-300 border border-slate-600 border-l-0 rounded-r px-2 py-2.5 text-xs font-medium cursor-pointer z-50 flex-col items-center gap-1 shadow-lg hover:bg-slate-700 hover:text-amber-400 transition-all hidden" onclick="toggleSidebar()">
        <i class="fas fa-chevron-right"></i>
        <span class="text-[10px] tracking-widest [writing-mode:vertical-rl]">CONTROLS</span>
    </button>

    <!-- Header Builder Overlay -->
    <div id="headerBuilderOverlay" class="header-builder-overlay">
        <div class="builder-header h-11 border-b border-slate-700 bg-slate-800 flex items-center justify-between px-4 flex-shrink-0">
            <h3 class="text-sm font-semibold text-slate-100 flex items-center gap-2"><i class="fas fa-tools"></i> Header Builder</h3>
            <div class="flex items-center gap-3">
                <div class="builder-device-toggle flex gap-0.5 bg-slate-700 rounded p-0.5 border border-slate-600">
                    <button type="button" class="active px-2.5 py-1 border-0 bg-transparent rounded text-xs text-slate-300 hover:text-white transition-colors" data-device="desktop" onclick="setBuilderDevice(this)">Desktop</button>
                    <button type="button" class="px-2.5 py-1 border-0 bg-transparent rounded text-xs text-slate-500 hover:text-white transition-colors" data-device="mobile" onclick="setBuilderDevice(this)">Mobile / Tablet</button>
                </div>
                <div class="builder-actions flex items-center gap-2">
                    <button type="button" class="px-2.5 py-1 border border-slate-600 rounded text-xs text-slate-300 bg-slate-700 hover:bg-slate-600 hover:text-white transition-colors cursor-pointer flex items-center gap-1" onclick="openHeaderBuilderPresets()"><i class="fas fa-th"></i> Presets</button>
                    <button type="button" class="px-2.5 py-1 border border-slate-600 rounded text-xs text-slate-300 bg-slate-700 hover:bg-slate-600 hover:text-white transition-colors cursor-pointer flex items-center gap-1" onclick="showBuilderTutorial()"><i class="fas fa-question-circle"></i> Tutorial</button>
                    <button type="button" class="px-2.5 py-1 border border-slate-600 rounded text-xs text-slate-300 bg-slate-700 hover:bg-slate-600 hover:text-white transition-colors cursor-pointer flex items-center gap-1" onclick="clearBuilder()"><i class="fas fa-eraser"></i> Clear All</button>
                    <button type="button" class="primary px-2.5 py-1 bg-amber-500 text-white rounded text-xs font-semibold hover:bg-amber-600 transition-colors cursor-pointer flex items-center gap-1" onclick="closeHeaderBuilder()"><i class="fas fa-times"></i> Close</button>
                </div>
            </div>
        </div>
        <div class="builder-body flex-1 flex flex-col items-center justify-start p-5 overflow-y-auto gap-4">
            <div class="builder-canvas w-full max-w-[1100px] flex flex-col gap-2">
                <!-- Top Bar Zone -->
                <div class="builder-zone bg-slate-800 border-2 border-dashed border-slate-600 rounded min-h-[60px] p-2 flex flex-wrap items-center gap-1.5 transition-colors" id="builderZoneTop" ondrop="builderDrop(event,'top')" ondragover="builderDragOver(event)" ondragleave="builderDragLeave(event)">
                    <span class="builder-zone-label w-full text-[10px] uppercase tracking-wider text-slate-500 mb-0.5">Top Bar</span>
                </div>
                <!-- Header Main Zone -->
                <div class="builder-zone bg-slate-800 border-2 border-dashed border-slate-600 rounded min-h-[60px] p-2 flex flex-wrap items-center gap-1.5 transition-colors" id="builderZoneMain" ondrop="builderDrop(event,'main')" ondragover="builderDragOver(event)" ondragleave="builderDragLeave(event)">
                    <span class="builder-zone-label w-full text-[10px] uppercase tracking-wider text-slate-500 mb-0.5">Header Main</span>
                    <div class="builder-component flex items-center gap-1 px-2 py-1 border border-slate-600 rounded bg-slate-700 text-xs text-slate-300 cursor-grab select-none" draggable="true" ondragstart="builderDragStart(event,'logo')" data-type="logo"><i class="fas fa-image"></i> Logo <span class="remove ml-1 cursor-pointer text-slate-500 hover:text-red-400 text-[9px]">&times;</span></div>
                    <div class="builder-component flex items-center gap-1 px-2 py-1 border border-slate-600 rounded bg-slate-700 text-xs text-slate-300 cursor-grab select-none" draggable="true" ondragstart="builderDragStart(event,'search')" data-type="search"><i class="fas fa-search"></i> Search Form <span class="remove ml-1 cursor-pointer text-slate-500 hover:text-red-400 text-[9px]">&times;</span></div>
                    <div class="builder-component flex items-center gap-1 px-2 py-1 border border-slate-600 rounded bg-slate-700 text-xs text-slate-300 cursor-grab select-none" draggable="true" ondragstart="builderDragStart(event,'account')" data-type="account"><i class="fas fa-user"></i> Account <span class="remove ml-1 cursor-pointer text-slate-500 hover:text-red-400 text-[9px]">&times;</span></div>
                    <div class="builder-component flex items-center gap-1 px-2 py-1 border border-slate-600 rounded bg-slate-700 text-xs text-slate-300 cursor-grab select-none" draggable="true" ondragstart="builderDragStart(event,'cart')" data-type="cart"><i class="fas fa-shopping-cart"></i> Cart <span class="remove ml-1 cursor-pointer text-slate-500 hover:text-red-400 text-[9px]">&times;</span></div>
                </div>
                <!-- Header Bottom Zone -->
                <div class="builder-zone bg-slate-800 border-2 border-dashed border-slate-600 rounded min-h-[60px] p-2 flex flex-wrap items-center gap-1.5 transition-colors" id="builderZoneBottom" ondrop="builderDrop(event,'bottom')" ondragover="builderDragOver(event)" ondragleave="builderDragLeave(event)">
                    <span class="builder-zone-label w-full text-[10px] uppercase tracking-wider text-slate-500 mb-0.5">Header Bottom</span>
                    <div class="builder-component flex items-center gap-1 px-2 py-1 border border-slate-600 rounded bg-slate-700 text-xs text-slate-300 cursor-grab select-none" draggable="true" ondragstart="builderDragStart(event,'menu')" data-type="menu"><i class="fas fa-bars"></i> Main Menu <span class="remove ml-1 cursor-pointer text-slate-500 hover:text-red-400 text-[9px]">&times;</span></div>
                </div>
            </div>
            <div class="builder-palette w-full max-w-[1100px] border-t border-slate-700 pt-3 mt-auto">
                <div class="builder-palette-title text-[11px] text-slate-400 uppercase tracking-wider mb-2">Components</div>
                <div class="builder-palette-items flex flex-wrap gap-1.5">
                    <?php 
                    $paletteItems = [
                        ['nav-icon', 'fa-bars', 'Nav Icon'],
                        ['secondary-menu', 'fa-list', 'Secondary Menu'],
                        ['vertical-menu', 'fa-columns', 'Vertical Menu'],
                        ['search-icon', 'fa-search', 'Search Icon'],
                        ['button1', 'fa-square', 'Button 1'],
                        ['button2', 'fa-square', 'Button 2'],
                        ['checkout', 'fa-credit-card', 'Checkout Button'],
                        ['newsletter', 'fa-envelope', 'Newsletter'],
                        ['languages', 'fa-globe', 'Languages'],
                        ['contact', 'fa-phone', 'Contact'],
                        ['social', 'fa-share-alt', 'Social Icons'],
                        ['block1', 'fa-cube', 'Block 1'],
                        ['block2', 'fa-cube', 'Block 2'],
                        ['block3', 'fa-cube', 'Block 3'],
                        ['block4', 'fa-cube', 'Block 4'],
                        ['html1', 'fa-code', 'HTML 1'],
                        ['html2', 'fa-code', 'HTML 2'],
                        ['html3', 'fa-code', 'HTML 3'],
                        ['html4', 'fa-code', 'HTML 4'],
                        ['html5', 'fa-code', 'HTML 5']
                    ];
                    foreach ($paletteItems as [$type, $icon, $label]): ?>
                    <div class="builder-palette-item flex items-center gap-1 px-2.5 py-1 border border-slate-600 rounded bg-slate-700 text-xs text-slate-300 cursor-grab select-none hover:border-amber-500 hover:text-white transition-colors" draggable="true" ondragstart="builderDragStart(event,'<?= $type ?>')"><i class="fas <?= $icon ?> text-slate-500"></i> <?= $label ?></div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <script>
        /* ===== Core Functions ===== */
        
        // Toggle accordion section
        function toggleSection(el) {
            const section = el.closest('.accordion-section');
            const isOpen = section.classList.contains('open');
            document.querySelectorAll('.accordion-section').forEach(s => s.classList.remove('open'));
            if (!isOpen) section.classList.add('open');
        }

        function toggleNestedSection(el) {
            const section = el.closest('.accordion-section');
            const parent = section.parentElement.closest('.accordion-section-content, .accordion-sub-container');
            const isOpen = section.classList.contains('open');
            if (parent) {
                parent.querySelectorAll(':scope > .accordion-section').forEach(s => s.classList.remove('open'));
            }
            if (!isOpen) section.classList.add('open');
        }

        // Color sync
        function syncHex(input) {
            const hex = input.nextElementSibling;
            if (hex) hex.value = input.value;
            triggerAutoSave(input);
        }

        function syncColorFromHex(input) {
            const color = input.previousElementSibling;
            if (color && input.value.match(/^#[0-9A-Fa-f]{6}$/)) color.value = input.value;
            triggerAutoSave(input);
        }

        // Apply color preset
        function applyPreset(primary, secondary) {
            document.getElementById('primaryColor').value = primary;
            document.getElementById('primaryColorHex').value = primary;
            document.getElementById('secondaryColor').value = secondary;
            document.getElementById('secondaryColorHex').value = secondary;
            triggerAutoSave(document.getElementById('primaryColor'));
        }

        // Apply header preset
        function applyHeaderPreset(name, style, sticky, transparent, bgColor, textColor, topBar, topBarText, topBarBg, topBarTextColor, showCart, showAccount, showSearch, showWishlist, mobileStyle, dropdownStyle) {
            const setVal = (id, v) => { const el = document.getElementById(id); if (el) el.value = v; };
            const setChk = (id, v) => { const el = document.getElementById(id); if (el) { el.checked = v === '1'; triggerAutoSave(el); } };
            setVal('headerStyle', style);
            setChk('headerSticky', sticky);
            setChk('headerTransparentHome', transparent);
            document.getElementById('headerBgColor').value = bgColor;
            document.getElementById('headerBgColorHex').value = bgColor;
            setVal('headerTextColor', textColor);
            setChk('headerTopBar', topBar);
            setVal('headerTopBarText', topBarText);
            document.getElementById('headerTopBarBg').value = topBarBg;
            document.getElementById('headerTopBarBgHex').value = topBarBg;
            setChk('headerShowCart', showCart);
            setChk('headerShowAccount', showAccount);
            setChk('headerShowSearch', showSearch);
            setChk('headerShowWishlist', showWishlist);
            setVal('headerMobileStyle', mobileStyle);
            setVal('headerDropdownStyle', dropdownStyle);
            triggerAutoSave(document.getElementById('headerStyle'));
            document.querySelectorAll('.preset-thumb').forEach(t => t.classList.remove('active'));
        }

        // Sidebar toggle
        function toggleSidebar() {
            const overlay = document.querySelector('.wp-full-overlay');
            const label = document.querySelector('.collapse-sidebar-label');
            overlay.classList.toggle('collapsed');
            label.textContent = overlay.classList.contains('collapsed') ? 'Show Controls' : 'Hide Controls';
        }

        // Preview mode toggle
        function togglePreviewMode() {
            const controls = document.querySelector('.customize-controls-preview-toggle .controls');
            const isControls = controls.classList.contains('active');
            switchPane(isControls ? 'preview' : 'controls');
        }

        function switchPane(mode) {
            const sidebar = document.querySelector('.wp-full-overlay-sidebar');
            const controls = document.querySelector('.customize-controls-preview-toggle .controls');
            const preview = document.querySelector('.customize-controls-preview-toggle .preview');
            if (mode === 'preview') {
                sidebar.classList.add('collapsed');
                document.getElementById('showControlsFab').style.display = 'flex';
                if (controls) controls.classList.remove('active');
                if (preview) preview.classList.add('active');
            } else {
                sidebar.classList.remove('collapsed');
                document.getElementById('showControlsFab').style.display = 'none';
                if (controls) controls.classList.add('active');
                if (preview) preview.classList.remove('active');
            }
        }

        // Header panel
        function openHeaderPanel() {
            document.getElementById('sub-accordion-panel-header').classList.add('active');
            document.getElementById('accordion').style.display = 'none';
        }

        function closeHeaderPanel() {
            document.getElementById('sub-accordion-panel-header').classList.remove('active');
            document.getElementById('accordion').style.display = '';
        }

        // Publish settings
        function togglePublishSettings() {
            const panel = document.getElementById('sub-accordion-section-publish_settings');
            const btn = document.getElementById('publishSettingsBtn');
            if (panel) {
                panel.classList.toggle('active');
                btn.setAttribute('aria-expanded', panel.classList.contains('active') ? 'true' : 'false');
            }
        }

        function closePublishSettings() {
            const panel = document.getElementById('sub-accordion-section-publish_settings');
            const btn = document.getElementById('publishSettingsBtn');
            if (panel) {
                panel.classList.remove('active');
                btn.setAttribute('aria-expanded', 'false');
            }
        }

        // Device toggle
        function setDevice(btn) {
            document.querySelectorAll('.devices button').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            document.getElementById('deviceFrame').className = 'device-frame ' + btn.dataset.device;
        }

        // Schedule toggle
        function toggleSchedule(el) {
            document.getElementById('scheduleFields').style.display = (el.value === 'future') ? 'block' : 'none';
        }

        // Copy preview link
        function copyPreviewLink() {
            const input = document.getElementById('previewLinkInput');
            if (!input) return;
            input.select();
            input.setSelectionRange(0, 99999);
            try {
                navigator.clipboard.writeText(input.value);
                flashStatus('Link copied');
            } catch (e) {
                flashStatus('Copy failed', '#ef4444');
            }
        }

        // Logo/Favicon preview
        function previewLogo(input) {
            const preview = document.getElementById('logoPreview');
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = e => preview.innerHTML = '<img src="' + e.target.result + '" alt="Logo">';
                reader.readAsDataURL(input.files[0]);
            }
        }

        function previewFavicon(input) {
            const preview = document.getElementById('faviconPreview');
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = e => preview.innerHTML = '<img src="' + e.target.result + '" alt="Favicon">';
                reader.readAsDataURL(input.files[0]);
            }
        }

        // Reorder list
        (function() {
            const list = document.getElementById('reorderList');
            const input = document.getElementById('homepageSections');
            if (!list || !input) return;
            let dragged = null;

            function updateOrder() {
                input.value = [...list.querySelectorAll('li')].map(el => el.dataset.key).join(',');
                triggerAutoSave(input);
            }

            list.querySelectorAll('li').forEach(item => {
                item.addEventListener('dragstart', e => {
                    dragged = item;
                    item.classList.add('dragging');
                    e.dataTransfer.effectAllowed = 'move';
                });
                item.addEventListener('dragend', () => {
                    item.classList.remove('dragging');
                    dragged = null;
                    updateOrder();
                });
                item.addEventListener('dragover', e => {
                    e.preventDefault();
                    if (!dragged || dragged === item) return;
                    const rect = item.getBoundingClientRect();
                    const mid = rect.top + rect.height / 2;
                    if (e.clientY < mid) {
                        list.insertBefore(dragged, item);
                    } else {
                        list.insertBefore(dragged, item.nextSibling);
                    }
                });
            });
        })();

        // Header Builder
        function openHeaderBuilder() {
            document.getElementById('headerBuilderOverlay').classList.add('active');
        }

        function closeHeaderBuilder() {
            document.getElementById('headerBuilderOverlay').classList.remove('active');
        }

        function setBuilderDevice(btn) {
            document.querySelectorAll('.builder-device-toggle button').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            const canvas = document.querySelector('.builder-canvas');
            canvas.style.maxWidth = btn.dataset.device === 'mobile' ? '768px' : '1100px';
        }

        function openHeaderBuilderPresets() { alert('Presets panel coming soon'); }

        function showBuilderTutorial() {
            alert('Drag components from the palette into the zones.\n\n• Click × to remove a component\n• Use Desktop/Mobile toggle to preview');
        }

        function clearBuilder() {
            document.querySelectorAll('.builder-zone .builder-component').forEach(c => c.remove());
        }

        let draggedType = null;

        function builderDragStart(e, type) {
            draggedType = type;
            e.dataTransfer.effectAllowed = 'move';
            e.dataTransfer.setData('text/plain', type);
        }

        function builderDragOver(e) {
            e.preventDefault();
            e.dataTransfer.dropEffect = 'move';
            e.currentTarget.classList.add('drag-over');
        }

        function builderDragLeave(e) {
            e.currentTarget.classList.remove('drag-over');
        }

        function builderDrop(e, zone) {
            e.preventDefault();
            e.currentTarget.classList.remove('drag-over');
            const type = e.dataTransfer.getData('text/plain') || draggedType;
            if (!type) return;

            const icons = {
                logo: 'fa-image', search: 'fa-search', account: 'fa-user', cart: 'fa-shopping-cart',
                menu: 'fa-bars', 'nav-icon': 'fa-bars', 'secondary-menu': 'fa-list',
                'vertical-menu': 'fa-columns', 'search-icon': 'fa-search',
                button1: 'fa-square', button2: 'fa-square', checkout: 'fa-credit-card',
                newsletter: 'fa-envelope', languages: 'fa-globe', contact: 'fa-phone',
                social: 'fa-share-alt', block1: 'fa-cube', block2: 'fa-cube',
                block3: 'fa-cube', block4: 'fa-cube',
                html1: 'fa-code', html2: 'fa-code', html3: 'fa-code', html4: 'fa-code', html5: 'fa-code'
            };
            const labels = {
                logo: 'Logo', search: 'Search Form', account: 'Account', cart: 'Cart',
                menu: 'Main Menu', 'nav-icon': 'Nav Icon', 'secondary-menu': 'Secondary Menu',
                'vertical-menu': 'Vertical Menu', 'search-icon': 'Search Icon',
                button1: 'Button 1', button2: 'Button 2', checkout: 'Checkout Button',
                newsletter: 'Newsletter', languages: 'Languages', contact: 'Contact',
                social: 'Social Icons', block1: 'Block 1', block2: 'Block 2',
                block3: 'Block 3', block4: 'Block 4',
                html1: 'HTML 1', html2: 'HTML 2', html3: 'HTML 3', html4: 'HTML 4', html5: 'HTML 5'
            };

            const wrapper = document.createElement('div');
            wrapper.className = 'builder-component flex items-center gap-1 px-2 py-1 border border-slate-600 rounded bg-slate-700 text-xs text-slate-300 cursor-grab select-none';
            wrapper.draggable = true;
            wrapper.dataset.type = type;
            wrapper.innerHTML = '<i class="fas ' + (icons[type] || 'fa-cube') + '"></i> ' + (labels[type] || type) + ' <span class="remove ml-1 cursor-pointer text-slate-500 hover:text-red-400 text-[9px]" onclick="removeComponent(this)">&times;</span>';
            wrapper.addEventListener('dragstart', ev => builderDragStart(ev, type));
            e.currentTarget.appendChild(wrapper);
        }

        function removeComponent(el) {
            el.parentElement.remove();
        }

        // Auto-save system
        let saveTimer = null;
        let lastFlash = null;

        function flashStatus(text, color = '#00a32a') {
            const el = document.getElementById('saveStatus');
            if (!el) return;
            el.textContent = text;
            el.style.color = color;
            el.style.opacity = '1';
            clearTimeout(lastFlash);
            lastFlash = setTimeout(() => { el.style.opacity = '0.6'; }, 2500);
        }

        function triggerAutoSave(el) {
            if (!el) return;
            clearTimeout(saveTimer);
            saveTimer = setTimeout(autoSave, 800);
        }

        function autoSave() {
            const data = {};
            document.querySelectorAll('[data-autosave]').forEach(el => {
                if (!el.name) return;
                if (el.type === 'checkbox') {
                    data[el.name] = el.checked ? '1' : '0';
                } else if (el.tagName === 'SELECT') {
                    data[el.name] = el.value;
                } else {
                    data[el.name] = el.value;
                }
            });

            const orderInput = document.getElementById('homepageSections');
            if (orderInput) data['homepage_sections'] = orderInput.value;

            const formData = new FormData();
            formData.append('action', 'save');
            formData.append('data', JSON.stringify(data));

            fetch('customize_api.php', { method: 'POST', body: formData, credentials: 'same-origin' })
                .then(async response => {
                    const text = await response.text();
                    try {
                        const json = JSON.parse(text);
                        if (json.success) {
                            flashStatus('Saved');
                            postPreview(data);
                        } else {
                            flashStatus('Save failed: ' + (json.error || ''), '#ef4444');
                        }
                    } catch (e) {
                        flashStatus('Save failed (bad response)', '#ef4444');
                    }
                })
                .catch(() => flashStatus('Offline', '#ef4444'));
        }

        // Auto-save listeners
        document.querySelectorAll('[data-autosave]').forEach(el => {
            const event = el.type === 'checkbox' ? 'change' : 'input';
            el.addEventListener(event, () => triggerAutoSave(el));
        });

        // PostMessage preview
        let previewMsgQueue = [];

        function postPreview(data) {
            const frame = document.getElementById('previewFrame');
            if (!frame || !frame.contentWindow || !window._storefrontReady) {
                previewMsgQueue.push(data);
                return;
            }
            try {
                frame.contentWindow.postMessage({ type: 'STOREFRONT_SETTINGS', settings: data, ts: Date.now() }, '*');
            } catch (e) { console.error('[Customizer] postMessage failed:', e); }
        }

        function flushPreviewQueue() {
            if (!previewMsgQueue.length || !window._storefrontReady) return;
            const frame = document.getElementById('previewFrame');
            if (!frame || !frame.contentWindow) return;
            while (previewMsgQueue.length) {
                const data = previewMsgQueue.shift();
                try {
                    frame.contentWindow.postMessage({ type: 'STOREFRONT_SETTINGS', settings: data, ts: Date.now() }, '*');
                } catch (e) {}
            }
        }

        window.addEventListener('message', (e) => {
            if (e.data && e.data.type === 'STOREFRONT_READY') {
                window._storefrontReady = true;
                flushPreviewQueue();
            }
        });

        // CSP fallback
        setTimeout(() => {
            if (window._previewLoaded) return;
            document.getElementById('cspFallback').classList.add('show');
        }, 3500);

        // Auto-hide notice
        setTimeout(() => {
            const notice = document.querySelector('.customize-notice');
            if (notice) notice.classList.remove('show');
        }, 4000);

        // Re-enable publish button
        window.addEventListener('pageshow', () => {
            const btn = document.getElementById('save');
            if (btn) { btn.value = 'Publish'; btn.disabled = false; }
        });

        // Device toggle listeners
        document.querySelectorAll('.devices button').forEach(btn => {
            btn.addEventListener('click', () => setDevice(btn));
        });

        // Iframe load
        document.getElementById('previewFrame').addEventListener('load', function() {
            window._previewLoaded = true;
            document.getElementById('cspFallback').classList.remove('show');
            flushPreviewQueue();
        });
    </script>

</body>
</html>