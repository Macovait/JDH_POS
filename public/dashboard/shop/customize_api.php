<?php
/**
 * Storefront Customizer API
 * Handles save, get, preview, and upload operations
 * @version 3.0
 */

ob_start();
require_once __DIR__ . '/../../../src/paths.php';
safe_require('auth.php', 'src', true);
safe_require('db.php', 'src', true);
safe_require('functions.php', 'src', true);

header('Content-Type: application/json; charset=utf-8');

// API endpoints must return JSON, never redirect
start_session_secure();
$user_id = get_current_user_id();
$tenant_id = get_current_tenant_id();

if (!$user_id || !$tenant_id) {
    http_response_code(403);
    ob_end_clean();
    echo json_encode(['success' => false, 'error' => 'Session expired. Please sign in again.']);
    exit;
}

$_SESSION['tenant_id'] = $tenant_id;

if (!validate_current_tenant()) {
    http_response_code(403);
    ob_end_clean();
    echo json_encode(['success' => false, 'error' => 'Tenant inactive.']);
    exit;
}

if (!validate_current_subscription()) {
    http_response_code(403);
    ob_end_clean();
    echo json_encode(['success' => false, 'error' => 'Subscription expired.']);
    exit;
}

ensure_current_branch();

$pdo = get_db_connection();
if (!$pdo) {
    ob_end_clean();
    echo json_encode(['success' => false, 'error' => 'Database connection failed']);
    exit;
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';

/* ================================================================
   HELPERS
   ================================================================ */

/**
 * Sanitize color hex value
 */
function sanitize_color(string $c): string {
    $c = trim($c);
    if (preg_match('/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/', $c)) {
        return strtolower($c);
    }
    return '#f68b1e';
}

/**
 * Sanitize URL
 */
function sanitize_url(string $u): string {
    $u = trim($u);
    if ($u === '') return '';
    if (!preg_match('~^https?://~i', $u)) $u = 'https://' . $u;
    if (filter_var($u, FILTER_VALIDATE_URL)) return $u;
    return '';
}

/**
 * Sanitize number
 */
function sanitize_number($v, float $min = 0, float $max = 999999): string {
    $n = is_numeric($v) ? (float)$v : 0;
    $n = max($min, min($max, $n));
    return (string)$n;
}

/**
 * Sanitize text
 */
function sanitize_text(string $t, int $max = 500): string {
    return mb_substr(trim(strip_tags($t)), 0, $max);
}

/**
 * Sanitize select value
 */
function sanitize_select(string $v, array $allowed, string $default): string {
    return in_array($v, $allowed, true) ? $v : $default;
}

/**
 * Sanitize boolean/checkbox
 */
function sanitize_bool($v): string {
    return ($v === '1' || $v === true || $v === 'true' || $v === 1) ? '1' : '0';
}

/* ================================================================
   SAVE (auto-save or batch publish)
   ================================================================ */

if ($action === 'save') {
    $raw = $_POST['data'] ?? '';
    $payload = is_string($raw) ? (json_decode($raw, true) ?: []) : (is_array($raw) ? $raw : []);
    
    if (empty($payload)) {
        ob_end_clean();
        echo json_encode([
            'success' => false,
            'error' => 'No data received',
            'received_type' => gettype($raw),
            'received_preview' => is_string($raw) ? substr($raw, 0, 200) : null
        ]);
        exit;
    }

    $errors = [];
    $saved = 0;

    // Define validators per key
    $validators = [
        // Site Identity
        'store_name' => fn($v) => sanitize_text($v, 120),
        'site_title' => fn($v) => sanitize_text($v, 120),
        'site_description' => fn($v) => sanitize_text($v, 300),
        'primary_color' => fn($v) => sanitize_color($v),
        'secondary_color' => fn($v) => sanitize_color($v),
        'currency' => fn($v) => sanitize_select($v, ['KES', 'USD', 'EUR', 'GBP', 'TZS', 'UGX', 'ZAR'], 'KES'),
        'whatsapp_number' => fn($v) => preg_replace('/[^0-9+\s-]/', '', sanitize_text($v, 20)),
        'facebook_url' => fn($v) => sanitize_url($v),
        'instagram_url' => fn($v) => sanitize_url($v),
        'tiktok_url' => fn($v) => sanitize_url($v),
        'meta_title' => fn($v) => sanitize_text($v, 120),
        'meta_description' => fn($v) => sanitize_text($v, 300),
        'logo_url' => fn($v) => sanitize_url($v),
        'favicon_url' => fn($v) => sanitize_url($v),
        
        // Store Settings
        'free_shipping_threshold' => fn($v) => sanitize_number($v, 0, 999999),
        'min_order_amount' => fn($v) => sanitize_number($v, 0, 999999),
        'show_reviews' => fn($v) => sanitize_bool($v),
        'show_stock_count' => fn($v) => sanitize_bool($v),
        'online_store_enabled' => fn($v) => sanitize_bool($v),
        
        // Announcement Bar
        'announcement_bar_text' => fn($v) => sanitize_text($v, 200),
        'announcement_bar_enabled' => fn($v) => sanitize_bool($v),
        'announcement_bar_color' => fn($v) => sanitize_color($v),
        
        // Homepage
        'show_deal_spotlight' => fn($v) => sanitize_bool($v),
        'show_newsletter' => fn($v) => sanitize_bool($v),
        'show_recently_viewed' => fn($v) => sanitize_bool($v),
        'deal_of_the_day_product_id' => fn($v) => sanitize_number($v, 0, 999999999),
        'homepage_sections' => fn($v) => sanitize_text($v, 200),
        
        // Typography
        'font_family' => fn($v) => sanitize_select($v, ['system', 'Inter', 'Poppins', 'Roboto', 'Open Sans', 'Playfair Display'], 'system'),
        'heading_font_size' => fn($v) => sanitize_select($v, ['small', 'medium', 'large', 'x-large'], 'medium'),
        'body_font_size' => fn($v) => sanitize_select($v, ['small', 'medium', 'large'], 'medium'),
        
        // Design
        'button_style' => fn($v) => sanitize_select($v, ['rounded', 'sharp', 'pill'], 'rounded'),
        'button_padding' => fn($v) => sanitize_select($v, ['compact', 'normal', 'spacious'], 'normal'),
        'card_style' => fn($v) => sanitize_select($v, ['flat', 'elevated', 'bordered'], 'elevated'),
        
        // Store Notice
        'store_notice_message' => fn($v) => sanitize_text($v, 300),
        'store_notice_type' => fn($v) => sanitize_select($v, ['info', 'success', 'warning', 'error'], 'info'),
        'store_notice_dismissible' => fn($v) => sanitize_bool($v),
        'store_notice_link_text' => fn($v) => sanitize_text($v, 100),
        'store_notice_link_url' => fn($v) => sanitize_url($v),
        
        // Featured Product
        'featured_product_id' => fn($v) => sanitize_number($v, 0, 999999999),
        'featured_product_badge' => fn($v) => sanitize_text($v, 50),
        'featured_product_show_description' => fn($v) => sanitize_bool($v),
        'featured_product_show_reviews' => fn($v) => sanitize_bool($v),
        'featured_product_image_position' => fn($v) => sanitize_select($v, ['left', 'right'], 'left'),
        
        // Blog Posts
        'blog_posts_title' => fn($v) => sanitize_text($v, 120),
        'blog_posts_limit' => fn($v) => sanitize_number($v, 1, 12),
        'blog_posts_columns' => fn($v) => sanitize_select($v, ['2', '3', '4'], '3'),
        'blog_posts_show_date' => fn($v) => sanitize_bool($v),
        'blog_posts_show_excerpt' => fn($v) => sanitize_bool($v),
        'blog_posts_show_read_more' => fn($v) => sanitize_bool($v),
        
        // Payment Icons
        'payment_icons_title' => fn($v) => sanitize_text($v, 100),
        'payment_icons_layout' => fn($v) => sanitize_select($v, ['row', 'grid'], 'row'),
        'payment_icons_visa' => fn($v) => sanitize_bool($v),
        'payment_icons_mastercard' => fn($v) => sanitize_bool($v),
        'payment_icons_amex' => fn($v) => sanitize_bool($v),
        'payment_icons_paypal' => fn($v) => sanitize_bool($v),
        'payment_icons_mpesa' => fn($v) => sanitize_bool($v),
        
        // Social Links
        'social_links_title' => fn($v) => sanitize_text($v, 100),
        'social_links_style' => fn($v) => sanitize_select($v, ['icon-only', 'icon-with-label', 'pill'], 'icon-with-label'),
        'social_links_align' => fn($v) => sanitize_select($v, ['left', 'center', 'right'], 'left'),
        'social_links_facebook' => fn($v) => sanitize_bool($v),
        'social_links_instagram' => fn($v) => sanitize_bool($v),
        'social_links_twitter' => fn($v) => sanitize_bool($v),
        'social_links_tiktok' => fn($v) => sanitize_bool($v),
        'social_links_youtube' => fn($v) => sanitize_bool($v),
        'social_links_whatsapp' => fn($v) => sanitize_bool($v),
        
        // Footer Links
        'footer_bottom_text' => fn($v) => sanitize_text($v, 300),
        'footer_show_payment_icons' => fn($v) => sanitize_bool($v),
        'footer_show_social_links' => fn($v) => sanitize_bool($v),
        
        // Layout
        'layout_mode' => fn($v) => sanitize_select($v, ['full-width', 'boxed', 'framed'], 'full-width'),
        'layout_container_width' => fn($v) => sanitize_select($v, ['1200', '1400', '1600'], '1400'),
        'layout_sidebar' => fn($v) => sanitize_select($v, ['none', 'left', 'right'], 'none'),
        'layout_grid_gap' => fn($v) => sanitize_select($v, ['small', 'medium', 'large'], 'medium'),
        'layout_content_padding' => fn($v) => sanitize_select($v, ['compact', 'normal', 'spacious'], 'normal'),
        
        // Header
        'header_style' => fn($v) => sanitize_select($v, ['standard', 'minimal', 'centered', 'modern'], 'standard'),
        'header_sticky' => fn($v) => sanitize_bool($v),
        'header_top_bar' => fn($v) => sanitize_bool($v),
        'header_top_bar_text' => fn($v) => sanitize_text($v, 200),
        'header_top_bar_bg' => fn($v) => sanitize_color($v),
        'header_top_bar_text_color' => fn($v) => sanitize_select($v, ['light', 'dark'], 'light'),
        'header_show_cart' => fn($v) => sanitize_bool($v),
        'header_show_account' => fn($v) => sanitize_bool($v),
        'header_show_search' => fn($v) => sanitize_bool($v),
        'header_show_wishlist' => fn($v) => sanitize_bool($v),
        'header_transparent_home' => fn($v) => sanitize_bool($v),
        'header_bg_color' => fn($v) => sanitize_color($v),
        'header_text_color' => fn($v) => sanitize_select($v, ['dark', 'light'], 'dark'),
        'header_mobile_style' => fn($v) => sanitize_select($v, ['overlay', 'slideout', 'dropdown'], 'overlay'),
        'header_dropdown_style' => fn($v) => sanitize_select($v, ['simple', 'boxed', 'mega'], 'simple'),
    ];

    $stmt = $pdo->prepare(
        "INSERT INTO storefront_settings (tenant_id, setting_key, setting_value) 
         VALUES (?, ?, ?) 
         ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)"
    );

    try {
        foreach ($payload as $key => $raw) {
            if (!isset($validators[$key])) {
                $errors[] = "Unknown field: $key";
                continue;
            }
            
            $clean = $validators[$key]($raw);
            $stmt->execute([$tenant_id, $key, $clean]);
            $saved++;
        }
    } catch (PDOException $e) {
        ob_end_clean();
        echo json_encode([
            'success' => false,
            'error' => 'Database error: ' . $e->getMessage(),
            'code' => $e->getCode()
        ]);
        exit;
    }

    ob_end_clean();
    echo json_encode([
        'success' => empty($errors),
        'saved' => $saved,
        'errors' => $errors ?: null,
        'ts' => date('c')
    ]);
    exit;
}

/* ================================================================
   GET (return all settings as JSON)
   ================================================================ */

if ($action === 'get') {
    $rows = $pdo->prepare("SELECT setting_key, setting_value FROM storefront_settings WHERE tenant_id = ?");
    $rows->execute([$tenant_id]);
    $settings = [];
    while ($r = $rows->fetch(PDO::FETCH_ASSOC)) {
        $settings[$r['setting_key']] = $r['setting_value'];
    }
    
    ob_end_clean();
    echo json_encode([
        'success' => true,
        'settings' => $settings,
        'tenant_id' => $tenant_id
    ]);
    exit;
}

/* ================================================================
   PREVIEW (public-safe subset for storefront iframe via postMessage)
   ================================================================ */

if ($action === 'preview') {
    $allowed = [
        'store_name', 'site_title', 'site_description', 'primary_color', 'secondary_color',
        'currency', 'announcement_bar_text', 'announcement_bar_enabled', 'announcement_bar_color',
        'font_family', 'heading_font_size', 'body_font_size',
        'button_style', 'button_padding', 'card_style',
        'show_reviews', 'show_stock_count', 'show_deal_spotlight', 'show_newsletter', 'show_recently_viewed',
        'store_notice_message', 'store_notice_type', 'store_notice_dismissible', 'store_notice_link_text', 'store_notice_link_url',
        'featured_product_id', 'featured_product_badge', 'featured_product_show_description', 'featured_product_show_reviews', 'featured_product_image_position',
        'blog_posts_title', 'blog_posts_limit', 'blog_posts_columns', 'blog_posts_show_date', 'blog_posts_show_excerpt', 'blog_posts_show_read_more',
        'payment_icons_title', 'payment_icons_layout', 'payment_icons_visa', 'payment_icons_mastercard', 'payment_icons_amex', 'payment_icons_paypal', 'payment_icons_mpesa',
        'social_links_title', 'social_links_style', 'social_links_align', 'social_links_facebook', 'social_links_instagram', 'social_links_twitter', 'social_links_tiktok', 'social_links_youtube', 'social_links_whatsapp',
        'footer_bottom_text', 'footer_show_payment_icons', 'footer_show_social_links',
        'layout_mode', 'layout_container_width', 'layout_sidebar', 'layout_grid_gap', 'layout_content_padding',
        'header_style', 'header_sticky', 'header_top_bar', 'header_top_bar_text', 'header_top_bar_bg', 'header_top_bar_text_color',
        'header_show_cart', 'header_show_account', 'header_show_search', 'header_show_wishlist',
        'header_transparent_home', 'header_bg_color', 'header_text_color', 'header_mobile_style', 'header_dropdown_style'
    ];
    
    $rows = $pdo->prepare(
        "SELECT setting_key, setting_value FROM storefront_settings 
         WHERE tenant_id = ? AND setting_key IN ('" . implode("','", $allowed) . "')"
    );
    $rows->execute([$tenant_id]);
    $out = [];
    while ($r = $rows->fetch(PDO::FETCH_ASSOC)) {
        $out[$r['setting_key']] = $r['setting_value'];
    }
    
    // Fill defaults
    $defaults = [
        'primary_color' => '#f68b1e',
        'secondary_color' => '#1a1a2e',
        'currency' => 'KES',
        'font_family' => 'system',
        'heading_font_size' => 'medium',
        'body_font_size' => 'medium',
        'button_style' => 'rounded',
        'button_padding' => 'normal',
        'card_style' => 'elevated',
        'show_reviews' => '1',
        'show_stock_count' => '0',
        'show_deal_spotlight' => '1',
        'show_newsletter' => '1',
        'show_recently_viewed' => '1',
        'store_notice_type' => 'info',
        'store_notice_dismissible' => '1',
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
    
    foreach ($defaults as $k => $v) {
        if (!isset($out[$k])) $out[$k] = $v;
    }
    
    ob_end_clean();
    echo json_encode([
        'success' => true,
        'settings' => $out,
        'tenant_id' => $tenant_id,
        '_ts' => time()
    ]);
    exit;
}

/* ================================================================
   UPLOAD LOGO / FAVICON
   ================================================================ */

if ($action === 'upload_logo' || $action === 'upload_favicon') {
    $key = $action === 'upload_logo' ? 'logo_file' : 'favicon_file';
    $setting_key = $action === 'upload_logo' ? 'logo_url' : 'favicon_url';
    $prefix = $action === 'upload_logo' ? 'logo_' : 'favicon_';

    if (empty($_FILES[$key]['tmp_name'])) {
        ob_end_clean();
        echo json_encode(['success' => false, 'error' => 'No file uploaded']);
        exit;
    }

    $file = $_FILES[$key];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowed = ['jpg', 'jpeg', 'png', 'gif', 'svg', 'webp'];
    if ($action === 'upload_favicon') $allowed[] = 'ico';
    
    if (!in_array($ext, $allowed)) {
        ob_end_clean();
        echo json_encode([
            'success' => false,
            'error' => 'Invalid file type. Allowed: ' . implode(', ', $allowed)
        ]);
        exit;
    }

    // Check file size (max 2MB)
    if ($file['size'] > 2 * 1024 * 1024) {
        ob_end_clean();
        echo json_encode(['success' => false, 'error' => 'File too large. Max 2MB allowed.']);
        exit;
    }

    $dir = PUBLIC_PATH . '/uploads/store/';
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    
    $fname = $prefix . $tenant_id . '_' . time() . '.' . $ext;
    $dest = $dir . $fname;
    
    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        ob_end_clean();
        echo json_encode(['success' => false, 'error' => 'Failed to move uploaded file']);
        exit;
    }

    $url = '/uploads/store/' . $fname;
    $stmt = $pdo->prepare(
        "INSERT INTO storefront_settings (tenant_id, setting_key, setting_value) 
         VALUES (?, ?, ?) 
         ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)"
    );
    $stmt->execute([$tenant_id, $setting_key, $url]);

    ob_end_clean();
    echo json_encode([
        'success' => true,
        'url' => $url,
        'full_url' => base_url(ltrim($url, '/'))
    ]);
    exit;
}

/* ================================================================
   RESET (reset all settings to defaults)
   ================================================================ */

if ($action === 'reset') {
    // Check for confirmation
    if (empty($_POST['confirm']) || $_POST['confirm'] !== 'yes') {
        ob_end_clean();
        echo json_encode(['success' => false, 'error' => 'Confirmation required. Pass confirm=yes']);
        exit;
    }

    try {
        $stmt = $pdo->prepare("DELETE FROM storefront_settings WHERE tenant_id = ?");
        $stmt->execute([$tenant_id]);
        
        ob_end_clean();
        echo json_encode([
            'success' => true,
            'message' => 'All settings reset to defaults'
        ]);
    } catch (PDOException $e) {
        ob_end_clean();
        echo json_encode([
            'success' => false,
            'error' => 'Failed to reset settings: ' . $e->getMessage()
        ]);
    }
    exit;
}

/* ================================================================
   EXPORT (export all settings as JSON)
   ================================================================ */

if ($action === 'export') {
    $rows = $pdo->prepare("SELECT setting_key, setting_value FROM storefront_settings WHERE tenant_id = ?");
    $rows->execute([$tenant_id]);
    $settings = [];
    while ($r = $rows->fetch(PDO::FETCH_ASSOC)) {
        $settings[$r['setting_key']] = $r['setting_value'];
    }
    
    ob_end_clean();
    header('Content-Disposition: attachment; filename="storefront-settings-' . date('Y-m-d') . '.json"');
    echo json_encode([
        'exported_at' => date('c'),
        'tenant_id' => $tenant_id,
        'settings' => $settings
    ], JSON_PRETTY_PRINT);
    exit;
}

/* ================================================================
   IMPORT (import settings from JSON)
   ================================================================ */

if ($action === 'import') {
    if (empty($_POST['settings']) && empty($_FILES['import_file']['tmp_name'])) {
        ob_end_clean();
        echo json_encode(['success' => false, 'error' => 'No settings data provided']);
        exit;
    }

    $data = null;
    
    // Check if file was uploaded
    if (!empty($_FILES['import_file']['tmp_name'])) {
        $json = file_get_contents($_FILES['import_file']['tmp_name']);
        $data = json_decode($json, true);
        if ($data && isset($data['settings'])) {
            $data = $data['settings'];
        }
    } elseif (!empty($_POST['settings'])) {
        $data = json_decode($_POST['settings'], true);
    }

    if (!$data || !is_array($data)) {
        ob_end_clean();
        echo json_encode(['success' => false, 'error' => 'Invalid JSON data']);
        exit;
    }

    $saved = 0;
    $errors = [];
    
    // Get existing settings to know which ones exist
    $existing = $pdo->prepare("SELECT setting_key FROM storefront_settings WHERE tenant_id = ?");
    $existing->execute([$tenant_id]);
    $existingKeys = $existing->fetchAll(PDO::FETCH_COLUMN);

    $stmt = $pdo->prepare(
        "INSERT INTO storefront_settings (tenant_id, setting_key, setting_value) 
         VALUES (?, ?, ?) 
         ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)"
    );

    foreach ($data as $key => $value) {
        if (!is_string($key) || strlen($key) > 100) continue;
        try {
            $stmt->execute([$tenant_id, $key, $value]);
            $saved++;
        } catch (PDOException $e) {
            $errors[] = "Failed to import '$key': " . $e->getMessage();
        }
    }

    ob_end_clean();
    echo json_encode([
        'success' => empty($errors),
        'saved' => $saved,
        'errors' => $errors ?: null,
        'message' => "Imported $saved settings successfully"
    ]);
    exit;
}

/* ================================================================
   DEFAULT / UNKNOWN
   ================================================================ */

ob_end_clean();
echo json_encode([
    'success' => false,
    'error' => 'Unknown action',
    'available_actions' => ['save', 'get', 'preview', 'upload_logo', 'upload_favicon', 'reset', 'export', 'import']
]);
exit;