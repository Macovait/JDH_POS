<?php
/**
 * Store Admin — Content Blocks (Page Builder)
 */
require_once __DIR__ . '/../../../src/paths.php';
safe_require('auth.php', 'src', true);
safe_require('db.php', 'src', true);
safe_require('functions.php', 'src', true);
require_login();

$pdo = get_db_connection();
$tenant_id = (int)($_SESSION['tenant_id'] ?? get_current_tenant_id() ?? 0);
if (!$tenant_id) { 
    echo '<div class="flex items-center justify-center h-screen font-sans text-slate-400">Access Denied — no tenant context</div>'; 
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
$layout_mode            = $settings['layout_mode']            ?? 'full-width';
$layout_container_width = $settings['layout_container_width'] ?? '1400';
$layout_sidebar         = $settings['layout_sidebar']         ?? 'none';
$layout_grid_gap        = $settings['layout_grid_gap']        ?? 'medium';
$layout_content_padding = $settings['layout_content_padding'] ?? 'normal';
$msg = '';
$msg_type = 'success';
?>
<style>:root{--brand-color:<?= $brand_color ?>;}</style>
<?php

/**
 * Sanitize merchant-edited HTML blocks.
 * Removes dangerous tags and event handlers while preserving layout markup.
 */
function sanitizeBlockHtml(string $html): string {
    // Strip dangerous tags entirely
    $dangerousTags = ['script', 'iframe', 'object', 'embed', 'form', 'input', 'textarea', 'button', 'select'];
    foreach ($dangerousTags as $tag) {
        $html = preg_replace("/<{$tag}[^>]*>.*?<\/{$tag}>/si", '', $html);
        $html = preg_replace("/<{$tag}[^>]*\/>/si", '', $html);
    }

    // Strip javascript: URLs and dangerous attributes
    $html = preg_replace('/\s*on\w+\s*=\s*"[^"]*"/i', '', $html);
    $html = preg_replace('/\s*on\w+\s*=\s*\'[^\']*\'/i', '', $html);
    $html = preg_replace('/\s*on\w+\s*=\s*[^\s>]+/i', '', $html);
    $html = preg_replace('/javascript\s*:/i', '', $html);

    // Strip style tags that could hide malicious content
    $html = preg_replace('/<style[^>]*>.*?<\/style>/si', '', $html);

    return trim($html);
}

// ── CRUD Operations ──
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['save_block'])) {
        $name = trim($_POST['name'] ?? '');
        $mode = $_POST['block_mode'] ?? 'html';
        $blockType = ($mode === 'typed') ? trim($_POST['block_type'] ?? 'text-section') : 'raw-html';
        $props = '{}';
        if ($mode === 'typed') {
            $rawProps = trim($_POST['props'] ?? '{}');
            $decoded = json_decode($rawProps, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $props = json_encode($decoded, JSON_UNESCAPED_UNICODE);
            } else {
                $msg = 'Invalid JSON in Props field.';
                $msg_type = 'error';
            }
        }
        $content = ($mode === 'html') ? sanitizeBlockHtml($_POST['content'] ?? '') : '';
        $data = [
            'name'          => $name,
            'content'       => $content,
            'bg_color'      => $_POST['bg_color'] ?? '#ffffff',
            'text_color'    => $_POST['text_color'] ?? '#1f2937',
            'padding'       => $_POST['padding'] ?? 'py-8',
            'display_order' => (int)($_POST['display_order'] ?? 0),
            'is_active'     => isset($_POST['is_active']) ? 1 : 0,
        ];
        if (empty($name)) {
            $msg = 'Block name is required.';
            $msg_type = 'error';
        } elseif ($msg_type !== 'error') {
            try {
                $hasTypeCol = false;
                try { $pdo->query("SELECT type FROM storefront_blocks LIMIT 0"); $hasTypeCol = true; } catch (Exception $e) {}
                if (!empty($_POST['block_id'])) {
                    // Update
                    if ($hasTypeCol) {
                        $stmt = $pdo->prepare("UPDATE storefront_blocks SET name=?, type=?, props=?, content=?, bg_color=?, text_color=?, padding=?, display_order=?, is_active=? WHERE id=? AND tenant_id=?");
                        $stmt->execute([$data['name'], $blockType, $props, $data['content'], $data['bg_color'], $data['text_color'], $data['padding'], $data['display_order'], $data['is_active'], (int)$_POST['block_id'], $tenant_id]);
                    } else {
                        $stmt = $pdo->prepare("UPDATE storefront_blocks SET name=?, content=?, bg_color=?, text_color=?, padding=?, display_order=?, is_active=? WHERE id=? AND tenant_id=?");
                        $stmt->execute([$data['name'], $data['content'], $data['bg_color'], $data['text_color'], $data['padding'], $data['display_order'], $data['is_active'], (int)$_POST['block_id'], $tenant_id]);
                    }
                    header('Location: blocks.php?msg=' . urlencode('Block updated.'));
                    exit;
                } else {
                    // Insert
                    if ($hasTypeCol) {
                        $stmt = $pdo->prepare("INSERT INTO storefront_blocks (tenant_id, name, type, props, content, bg_color, text_color, padding, display_order, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                        $stmt->execute([$tenant_id, $data['name'], $blockType, $props, $data['content'], $data['bg_color'], $data['text_color'], $data['padding'], $data['display_order'], $data['is_active']]);
                    } else {
                        $stmt = $pdo->prepare("INSERT INTO storefront_blocks (tenant_id, name, content, bg_color, text_color, padding, display_order, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                        $stmt->execute([$tenant_id, $data['name'], $data['content'], $data['bg_color'], $data['text_color'], $data['padding'], $data['display_order'], $data['is_active']]);
                    }
                    header('Location: blocks.php?msg=' . urlencode('Block created.'));
                    exit;
                }
            } catch (Exception $e) {
                $msg = 'Database error: ' . $e->getMessage();
                $msg_type = 'error';
            }
        }
    } elseif (isset($_POST['delete_block'])) {
        try {
            $stmt = $pdo->prepare("DELETE FROM storefront_blocks WHERE id = ? AND tenant_id = ?");
            $stmt->execute([(int)$_POST['block_id'], $tenant_id]);
            header('Location: blocks.php?msg=' . urlencode('Block deleted.'));
            exit;
        } catch (Exception $e) {
            $msg = 'Delete failed: ' . $e->getMessage();
            $msg_type = 'error';
        }
    }
}

// ── Load blocks ──
$blocks = [];
try {
    $stmt = $pdo->prepare("SELECT * FROM storefront_blocks WHERE tenant_id = ? ORDER BY display_order ASC, id ASC");
    $stmt->execute([$tenant_id]);
    $blocks = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

$blocks_count = count($blocks);

// Stats for sidebar
$stats = ['pending_orders' => 0, 'reviews_pending' => 0];
try {
    $r = $pdo->prepare("SELECT COUNT(*) FROM online_orders WHERE tenant_id = ? AND status='pending'"); 
    $r->execute([$tenant_id]); 
    $stats['pending_orders'] = (int)$r->fetchColumn();
    $r = $pdo->prepare("SELECT COUNT(*) FROM product_reviews WHERE tenant_id = ? AND status='pending'"); 
    $r->execute([$tenant_id]); 
    $stats['reviews_pending'] = (int)$r->fetchColumn();
} catch (Exception $e) {}

$status = [
    'online' => ($settings['online_store_enabled'] ?? '0') === '1',
    'pending_orders' => $stats['pending_orders'],
    'reviews_pending' => $stats['reviews_pending']
];

$storeUrl = storefront_url($tenant_id);
$page_title = 'Content Blocks';

// Edit mode
$editBlock = null;
if (isset($_GET['edit'])) {
    foreach ($blocks as $b) { if ($b['id'] == $_GET['edit']) { $editBlock = $b; break; } }
}

if (isset($_GET['msg'])) { $msg = $_GET['msg']; $msg_type = $_GET['type'] ?? 'success'; }
ob_start();
?>

<div class="space-y-5">
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
        <div>
            <h1 class="text-2xl font-bold text-white">Content Blocks</h1>
            <p class="text-sm text-slate-500 mt-1">Page Builder — custom sections for your storefront</p>
        </div>
        <div class="flex items-center gap-3">
            <a href="customize.php" class="flex items-center gap-1.5 text-slate-400 hover:text-white text-xs bg-slate-800/40 px-3 py-1.5 rounded-lg border border-slate-700/60 transition hover:bg-slate-700/50">
                <i class="fas fa-paint-brush"></i> Customize
            </a>
            <a href="<?= htmlspecialchars($storeUrl) ?>" target="_blank" class="flex items-center gap-2 text-white text-xs font-bold px-4 py-2 rounded-lg transition hover:opacity-90" style="background:<?= $brand_color ?>">
                <i class="fas fa-eye"></i> Preview Store
            </a>
        </div>
    </div>

    <?php if ($msg): ?>
    <div class="px-4 py-3 rounded-lg text-sm font-bold <?= $msg_type === 'success' ? 'bg-emerald-500/15 border border-emerald-500/30 text-emerald-400' : 'bg-red-500/15 border border-red-500/30 text-red-400' ?>">
        <i class="fas <?= $msg_type === 'success' ? 'fa-check-circle' : 'fa-times-circle' ?> mr-2"></i><?= htmlspecialchars($msg) ?>
    </div>
    <?php endif; ?>

    <div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

            <!-- LEFT: Block List -->
            <div class="lg:col-span-2">
                <div class="bg-slate-800/40 rounded-2xl border border-slate-700/60 p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h2 class="text-base font-bold text-white">Your Blocks</h2>
                        <span class="text-xs text-slate-400"><?= count($blocks) ?> total</span>
                    </div>

                    <?php if (empty($blocks)): ?>
                    <div class="text-center py-12 text-slate-400">
                        <i class="fas fa-cubes text-4xl text-slate-700 mb-3 block"></i>
                        <p class="font-medium">No blocks yet</p>
                        <p class="text-xs mt-1">Use the form to create your first custom section</p>
                    </div>
                    <?php else: ?>
                    <div class="space-y-2">
                    <?php foreach ($blocks as $b):
                        $isActive = (int)$b['is_active'];
                    ?>
                    <div class="block-item flex items-center gap-3 bg-slate-900/50 rounded-xl px-4 py-3 border border-transparent hover:border-amber-500/30 transition">
                        <div class="text-slate-400 text-xs cursor-move"><i class="fas fa-grip-vertical"></i></div>
                        <div class="w-6 h-6 rounded-full border border-slate-700 shrink-0" style="background:<?= htmlspecialchars($b['bg_color']) ?>;"></div>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-semibold text-slate-300 truncate"><?= htmlspecialchars($b['name']) ?></p>
                            <p class="text-[11px] text-slate-400">
                                <span class="inline-block px-1.5 py-0.5 rounded bg-slate-700/50 text-slate-300 mr-1"><?= htmlspecialchars($b['type'] ?? 'raw-html') ?></span>
                                Order <?= (int)$b['display_order'] ?> · <?= $isActive ? 'Active' : 'Hidden' ?>
                            </p>
                        </div>
                        <div class="flex items-center gap-2 shrink-0">
                            <!-- Toggle -->
                            <form method="POST" class="inline">
                                <input type="hidden" name="block_id" value="<?= $b['id'] ?>">
                                <input type="hidden" name="toggle_block" value="1">
                                <button type="submit" 
                                        class="text-[10px] font-bold px-2 py-1 rounded-lg transition <?= $isActive ? 'bg-emerald-500/15 text-emerald-400' : 'bg-slate-700/50 text-slate-400' ?>"
                                        title="Toggle visibility">
                                    <i class="fas <?= $isActive ? 'fa-eye' : 'fa-eye-slash' ?>"></i>
                                </button>
                            </form>
                            <!-- Edit -->
                            <a href="?edit=<?= $b['id'] ?>" 
                               class="text-[10px] font-bold px-2 py-1 rounded-lg bg-blue-500/15 text-blue-400 hover:bg-blue-500/25 transition"
                               title="Edit">
                                <i class="fas fa-pen"></i>
                            </a>
                            <!-- Delete -->
                            <form method="POST" class="inline" onsubmit="return confirm('Delete this block?');">
                                <input type="hidden" name="block_id" value="<?= $b['id'] ?>">
                                <input type="hidden" name="delete_block" value="1">
                                <button type="submit" 
                                        class="text-[10px] font-bold px-2 py-1 rounded-lg bg-red-500/15 text-red-400 hover:bg-red-500/25 transition"
                                        title="Delete">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </form>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    </div>
                    <!-- Reorder -->
                    <form method="POST" class="mt-4 pt-4 border-t border-slate-700/60">
                        <input type="hidden" name="reorder_blocks" value="1">
                        <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-2 mb-3">
                            <?php foreach ($blocks as $b): ?>
                            <div class="flex items-center gap-2">
                                <span class="text-xs text-slate-500 truncate max-w-[100px]"><?= htmlspecialchars($b['name']) ?></span>
                                <input type="number" name="order[<?= $b['id'] ?>]" 
                                       value="<?= (int)$b['display_order'] ?>" 
                                       class="w-14 text-center text-xs border border-slate-700 rounded-lg py-1 focus:outline-none focus:ring-2 focus:ring-orange-300"
                                       title="Display order">
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <button type="submit" 
                                class="bg-slate-700 text-white text-xs font-bold px-4 py-2 rounded-lg hover:bg-slate-600 transition">
                            <i class="fas fa-save mr-1"></i> Save Order
                        </button>
                    </form>
                    <?php endif; ?>
                </div>
            </div>

            <!-- RIGHT: Form -->
            <div>
                <!-- Templates -->
                <div class="bg-slate-800/40 rounded-2xl border border-slate-700/60 p-5 mb-4">
                    <h3 class="text-xs font-bold text-slate-500 uppercase tracking-wide mb-3">Start from a Template</h3>
                    <div class="grid grid-cols-2 gap-2">
                        <button type="button" onclick="insertTemplate('grid3')" 
                                class="template-btn bg-slate-900/50 rounded-lg p-3 text-left">
                            <i class="fas fa-th text-amber-400 text-sm mb-1 block"></i>
                            <span class="text-xs font-semibold text-slate-300 block">Product Grid (3)</span>
                            <span class="text-[10px] text-slate-400">Image cards with prices</span>
                        </button>
                        <button type="button" onclick="insertTemplate('trust')" 
                                class="template-btn bg-slate-900/50 rounded-lg p-3 text-left">
                            <i class="fas fa-shield-alt text-amber-400 text-sm mb-1 block"></i>
                            <span class="text-xs font-semibold text-slate-300 block">Trust Badges</span>
                            <span class="text-[10px] text-slate-400">Delivery, payment, returns</span>
                        </button>
                        <button type="button" onclick="insertTemplate('promo')" 
                                class="template-btn bg-slate-900/50 rounded-lg p-3 text-left">
                            <i class="fas fa-bullhorn text-amber-400 text-sm mb-1 block"></i>
                            <span class="text-xs font-semibold text-slate-300 block">Promo Banner</span>
                            <span class="text-[10px] text-slate-400">Gradient sale callout</span>
                        </button>
                        <button type="button" onclick="insertTemplate('shipping')" 
                                class="template-btn bg-slate-900/50 rounded-lg p-3 text-left">
                            <i class="fas fa-truck text-amber-400 text-sm mb-1 block"></i>
                            <span class="text-xs font-semibold text-slate-300 block">Shipping Info</span>
                            <span class="text-[10px] text-slate-400">Delivery zones & times</span>
                        </button>
                        <button type="button" onclick="insertTemplate('text')" 
                                class="template-btn bg-slate-900/50 rounded-lg p-3 text-left">
                            <i class="fas fa-align-center text-amber-400 text-sm mb-1 block"></i>
                            <span class="text-xs font-semibold text-slate-300 block">Text Section</span>
                            <span class="text-[10px] text-slate-400">Centered heading + paragraph</span>
                        </button>
                        <button type="button" onclick="insertTemplate('twoCol')" 
                                class="template-btn bg-slate-900/50 rounded-lg p-3 text-left">
                            <i class="fas fa-columns text-amber-400 text-sm mb-1 block"></i>
                            <span class="text-xs font-semibold text-slate-300 block">Image + Text</span>
                            <span class="text-[10px] text-slate-400">Two column feature layout</span>
                        </button>
                    </div>
                    <h3 class="text-xs font-bold text-slate-500 uppercase tracking-wide mt-4 mb-3">Typed Blocks</h3>
                    <div class="grid grid-cols-2 gap-2">
                        <button type="button" onclick="setTypedTemplate('slider')" class="template-btn bg-slate-900/50 rounded-lg p-3 text-left">
                            <i class="fas fa-images text-amber-400 text-sm mb-1 block"></i>
                            <span class="text-xs font-semibold text-slate-300 block">Slider</span>
                            <span class="text-[10px] text-slate-400">Image carousel</span>
                        </button>
                        <button type="button" onclick="setTypedTemplate('accordion')" class="template-btn bg-slate-900/50 rounded-lg p-3 text-left">
                            <i class="fas fa-list text-amber-400 text-sm mb-1 block"></i>
                            <span class="text-xs font-semibold text-slate-300 block">Accordion</span>
                            <span class="text-[10px] text-slate-400">FAQ / expandable</span>
                        </button>
                        <button type="button" onclick="setTypedTemplate('countdown')" class="template-btn bg-slate-900/50 rounded-lg p-3 text-left">
                            <i class="fas fa-clock text-amber-400 text-sm mb-1 block"></i>
                            <span class="text-xs font-semibold text-slate-300 block">Countdown</span>
                            <span class="text-[10px] text-slate-400">Sale timer</span>
                        </button>
                        <button type="button" onclick="setTypedTemplate('gallery')" class="template-btn bg-slate-900/50 rounded-lg p-3 text-left">
                            <i class="fas fa-th-large text-amber-400 text-sm mb-1 block"></i>
                            <span class="text-xs font-semibold text-slate-300 block">Gallery</span>
                            <span class="text-[10px] text-slate-400">Image grid + lightbox</span>
                        </button>
                        <button type="button" onclick="setTypedTemplate('icon-box')" class="template-btn bg-slate-900/50 rounded-lg p-3 text-left">
                            <i class="fas fa-icons text-amber-400 text-sm mb-1 block"></i>
                            <span class="text-xs font-semibold text-slate-300 block">Icon Box</span>
                            <span class="text-[10px] text-slate-400">Feature highlights</span>
                        </button>
                        <button type="button" onclick="setTypedTemplate('map')" class="template-btn bg-slate-900/50 rounded-lg p-3 text-left">
                            <i class="fas fa-map-marker-alt text-amber-400 text-sm mb-1 block"></i>
                            <span class="text-xs font-semibold text-slate-300 block">Map</span>
                            <span class="text-[10px] text-slate-400">Store location</span>
                        </button>
                        <button type="button" onclick="setTypedTemplate('video-button')" class="template-btn bg-slate-900/50 rounded-lg p-3 text-left">
                            <i class="fas fa-play-circle text-amber-400 text-sm mb-1 block"></i>
                            <span class="text-xs font-semibold text-slate-300 block">Video</span>
                            <span class="text-[10px] text-slate-400">YouTube / Vimeo lightbox</span>
                        </button>
                        <button type="button" onclick="setTypedTemplate('button-block')" class="template-btn bg-slate-900/50 rounded-lg p-3 text-left">
                            <i class="fas fa-hand-pointer text-amber-400 text-sm mb-1 block"></i>
                            <span class="text-xs font-semibold text-slate-300 block">Button</span>
                            <span class="text-[10px] text-slate-400">CTA button</span>
                        </button>
                        <button type="button" onclick="setTypedTemplate('message-box')" class="template-btn bg-slate-900/50 rounded-lg p-3 text-left">
                            <i class="fas fa-info-circle text-amber-400 text-sm mb-1 block"></i>
                            <span class="text-xs font-semibold text-slate-300 block">Message Box</span>
                            <span class="text-[10px] text-slate-400">Alert / notice</span>
                        </button>
                        <button type="button" onclick="setTypedTemplate('divider')" class="template-btn bg-slate-900/50 rounded-lg p-3 text-left">
                            <i class="fas fa-minus text-amber-400 text-sm mb-1 block"></i>
                            <span class="text-xs font-semibold text-slate-300 block">Divider</span>
                            <span class="text-[10px] text-slate-400">Section separator</span>
                        </button>
                    </div>
                </div>

                <!-- Add/Edit Form -->
                <div class="bg-slate-800/40 rounded-2xl border border-slate-700/60 p-6">
                    <h2 class="text-base font-bold text-white mb-4"><?= $editBlock ? 'Edit Block' : 'Add Block' ?></h2>
                    <form method="POST" id="blockForm">
                        <input type="hidden" name="save_block" value="1">
                        <?php if ($editBlock): ?><input type="hidden" name="block_id" value="<?= $editBlock['id'] ?>"><?php endif; ?>

                        <div class="space-y-4">
                            <div>
                                <label class="block text-xs font-semibold text-slate-400 mb-1">Block Name</label>
                                <input type="text" name="name" 
                                       value="<?= htmlspecialchars($editBlock['name'] ?? '') ?>" 
                                       required placeholder="e.g. Shipping Info"
                                       class="w-full bg-slate-900 text-slate-100 border border-slate-700 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-orange-300">
                            </div>

                            <div>
                                <label class="block text-xs font-semibold text-slate-400 mb-1">Block Mode</label>
                                <div class="flex bg-slate-900 rounded-lg border border-slate-700 p-1">
                                    <label class="flex-1 text-center cursor-pointer">
                                        <input type="radio" name="block_mode" value="html" class="sr-only peer" <?= ($editBlock['type'] ?? 'raw-html') === 'raw-html' ? 'checked' : '' ?> onchange="toggleMode(this.value)">
                                        <span class="block text-xs font-semibold py-2 rounded-md text-slate-400 peer-checked:bg-slate-700 peer-checked:text-white transition">HTML Content</span>
                                    </label>
                                    <label class="flex-1 text-center cursor-pointer">
                                        <input type="radio" name="block_mode" value="typed" class="sr-only peer" <?= ($editBlock['type'] ?? 'raw-html') !== 'raw-html' ? 'checked' : '' ?> onchange="toggleMode(this.value)">
                                        <span class="block text-xs font-semibold py-2 rounded-md text-slate-400 peer-checked:bg-slate-700 peer-checked:text-white transition">Typed Block</span>
                                    </label>
                                </div>
                            </div>

                            <div id="typedFields">
                                <div>
                                    <label class="block text-xs font-semibold text-slate-400 mb-1">Block Type</label>
                                    <select name="block_type" id="blockType"
                                            class="w-full bg-slate-900 text-slate-100 border border-slate-700 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-orange-300">
                                        <?php
                                        $blockTypes = [
                                            'text-section' => 'Text Section',
                                            'hero-banner' => 'Hero Banner',
                                            'product-grid' => 'Product Grid',
                                            'categories-grid' => 'Categories Grid',
                                            'promo-banner' => 'Promo Banner',
                                            'flash-sale' => 'Flash Sale',
                                            'image-text' => 'Image + Text',
                                            'testimonials' => 'Testimonials',
                                            'brand-showcase' => 'Brand Showcase',
                                            'newsletter' => 'Newsletter',
                                            'trust-bar' => 'Trust Bar',
                                            'featured-product' => 'Featured Product',
                                            'blog-posts' => 'Blog Posts',
                                            'payment-icons' => 'Payment Icons',
                                            'social-links' => 'Social Links',
                                            'footer-links' => 'Footer Links',
                                            'slider' => 'Slider / Carousel',
                                            'accordion' => 'Accordion / FAQ',
                                            'countdown' => 'Countdown Timer',
                                            'gallery' => 'Image Gallery',
                                            'icon-box' => 'Icon Box',
                                            'map' => 'Map',
                                            'video-button' => 'Video Button',
                                            'button-block' => 'Button / CTA',
                                            'message-box' => 'Message Box',
                                            'divider' => 'Divider',
                                        ];
                                        $currentType = $editBlock['type'] ?? 'text-section';
                                        foreach ($blockTypes as $val => $label):
                                        ?>
                                        <option value="<?= $val ?>" <?= $currentType === $val ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-slate-400 mb-1">Props (JSON)</label>
                                    <textarea name="props" id="blockProps" rows="10"
                                              placeholder='{"title":"Hello","items":[]}'
                                              class="w-full bg-slate-900 text-slate-100 border border-slate-700 rounded-lg px-3 py-2 text-sm font-mono focus:outline-none focus:ring-2 focus:ring-orange-300"><?= htmlspecialchars($editBlock['props'] ?? '{}') ?></textarea>
                                    <p class="text-[10px] text-slate-500 mt-1">Use the template buttons above to auto-fill JSON.</p>
                                </div>
                            </div>

                            <div id="htmlFields">
                                <div>
                                    <div class="flex items-center justify-between mb-1">
                                        <label class="block text-xs font-semibold text-slate-400">HTML Content</label>
                                        <span class="text-[10px] text-slate-400">Updates preview as you type</span>
                                    </div>
                                    <textarea name="content" id="blockContent" rows="8"
                                              placeholder="<div class='grid grid-cols-3 gap-4'>..."
                                              class="w-full bg-slate-900 text-slate-100 border border-slate-700 rounded-lg px-3 py-2 text-sm font-mono focus:outline-none focus:ring-2 focus:ring-orange-300"><?= htmlspecialchars($editBlock['content'] ?? '') ?></textarea>
                                </div>

                                <!-- Live Preview -->
                                <div>
                                    <div class="flex items-center justify-between mb-2">
                                        <label class="block text-xs font-semibold text-slate-400">Live Preview</label>
                                        <span class="text-[10px] text-slate-400 bg-slate-800/40 px-2 py-0.5 rounded border border-slate-700">
                                            <?= ucwords(str_replace('-', ' ', htmlspecialchars($layout_mode))) ?> · <?= (int)$layout_container_width ?>px
                                        </span>
                                    </div>
                                    <div class="preview-frame p-4 bg-slate-900/50">
                                        <?php if ($layout_sidebar !== 'none'): ?>
                                        <div class="flex gap-4">
                                            <?php if ($layout_sidebar === 'left'): ?>
                                            <div class="preview-sidebar w-48 shrink-0 hidden lg:flex">Sidebar</div>
                                            <?php endif; ?>
                                            <div id="livePreview" class="preview-layout-wrapper <?= htmlspecialchars($layout_mode) ?>" style="max-width:<?= (int)$layout_container_width ?>px; padding:<?= $layout_content_padding==='compact'?'0.5rem':($layout_content_padding==='spacious'?'2rem':'1rem') ?>"></div>
                                            <?php if ($layout_sidebar === 'right'): ?>
                                            <div class="preview-sidebar w-48 shrink-0 hidden lg:flex">Sidebar</div>
                                            <?php endif; ?>
                                        </div>
                                        <?php else: ?>
                                        <div id="livePreview" class="preview-layout-wrapper <?= htmlspecialchars($layout_mode) ?>" style="max-width:<?= (int)$layout_container_width ?>px; padding:<?= $layout_content_padding==='compact'?'0.5rem':($layout_content_padding==='spacious'?'2rem':'1rem') ?>"></div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>

                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label class="block text-xs font-semibold text-slate-400 mb-1">Background Color</label>
                                    <div class="flex items-center gap-2">
                                        <input type="color" name="bg_color" 
                                               value="<?= htmlspecialchars($editBlock['bg_color'] ?? '#ffffff') ?>"
                                               class="w-10 h-10 rounded-lg border border-slate-700 cursor-pointer p-0.5"
                                               oninput="this.nextElementSibling.value=this.value">
                                        <input type="text" 
                                               value="<?= htmlspecialchars($editBlock['bg_color'] ?? '#ffffff') ?>"
                                               class="flex-1 bg-slate-900 text-slate-100 border border-slate-700 rounded-lg px-2 py-1.5 text-xs font-mono" 
                                               readonly>
                                    </div>
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-slate-400 mb-1">Text Color</label>
                                    <div class="flex items-center gap-2">
                                        <input type="color" name="text_color" 
                                               value="<?= htmlspecialchars($editBlock['text_color'] ?? '#1f2937') ?>"
                                               class="w-10 h-10 rounded-lg border border-slate-700 cursor-pointer p-0.5"
                                               oninput="this.nextElementSibling.value=this.value">
                                        <input type="text" 
                                               value="<?= htmlspecialchars($editBlock['text_color'] ?? '#1f2937') ?>"
                                               class="flex-1 bg-slate-900 text-slate-100 border border-slate-700 rounded-lg px-2 py-1.5 text-xs font-mono" 
                                               readonly>
                                    </div>
                                </div>
                            </div>

                            <div>
                                <label class="block text-xs font-semibold text-slate-400 mb-1">Padding</label>
                                <select name="padding" 
                                        class="w-full bg-slate-900 text-slate-100 border border-slate-700 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-orange-300">
                                    <?php foreach (['py-4','py-6','py-8','py-10','py-12'] as $p): ?>
                                    <option value="<?= $p ?>" <?= ($editBlock['padding'] ?? 'py-8') === $p ? 'selected' : '' ?>><?= $p ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div>
                                <label class="block text-xs font-semibold text-slate-400 mb-1">Display Order</label>
                                <input type="number" name="display_order" 
                                       value="<?= (int)($editBlock['display_order'] ?? 0) ?>" 
                                       min="0"
                                       class="w-full bg-slate-900 text-slate-100 border border-slate-700 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-orange-300">
                            </div>

                            <label class="flex items-center gap-3 cursor-pointer">
                                <input type="checkbox" name="is_active" value="1"
                                       <?= !isset($editBlock) || ($editBlock['is_active'] ?? 1) ? 'checked' : '' ?>
                                       class="w-4 h-4 text-orange-500 rounded border-slate-600 focus:ring-orange-300">
                                <span class="text-sm text-slate-300">Active (visible on store)</span>
                            </label>

                            <button type="submit" 
                                    class="w-full py-3 rounded-xl text-white font-bold text-sm transition hover:opacity-90"
                                    style="background:<?= $brand_color ?>">
                                <?= $editBlock ? 'Update Block' : 'Create Block' ?>
                            </button>

                            <?php if ($editBlock): ?>
                            <a href="blocks.php" class="block text-center text-xs text-slate-400 hover:text-slate-400 mt-2">Cancel editing</a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>

                <!-- Quick Tips -->
                <div class="bg-slate-900/50 rounded-2xl border border-slate-700/60 p-4 mt-4">
                    <h3 class="text-xs font-bold text-slate-500 uppercase tracking-wide mb-2">Quick Tips</h3>
                    <ul class="text-[11px] text-slate-500 space-y-1.5">
                        <li>· HTML mode: raw HTML / Tailwind markup</li>
                        <li>· Typed Block mode: choose a block type and edit JSON props</li>
                        <li>· Click a template button to auto-fill starting HTML or JSON</li>
                        <li>· Use <code class="bg-slate-800/40 px-1.5 py-0.5 rounded border border-slate-700 text-xs">var(--brand-color)</code> for the store brand color</li>
                        <li>· Lower order numbers appear first on the storefront</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function toggleMode(mode) {
  const htmlFields = document.getElementById('htmlFields');
  const typedFields = document.getElementById('typedFields');
  const content = document.getElementById('blockContent');
  const props = document.getElementById('blockProps');
  if (!htmlFields || !typedFields) return;
  if (mode === 'typed') {
    htmlFields.classList.add('hidden');
    typedFields.classList.remove('hidden');
    if (content) content.removeAttribute('required');
  } else {
    htmlFields.classList.remove('hidden');
    typedFields.classList.add('hidden');
    if (content) content.setAttribute('required', 'required');
  }
}

document.addEventListener('DOMContentLoaded', function() {
  const checked = document.querySelector('input[name="block_mode"]:checked');
  toggleMode(checked ? checked.value : 'html');
});

const typedTemplates = {
  'text-section': `{"heading": "About Our Store", "body": "We bring you the best products at unbeatable prices.", "align": "center", "heading_size": "lg"}`,
  'hero-banner': `{"slides": [{"image": "/uploads/products/placeholder.jpg", "title": "Big Sale", "subtitle": "Up to 50% off", "cta_text": "Shop Now", "cta_link": "/products"}], "autoplay": true, "interval": 5000}`,
  'product-grid': `{"title": "Featured Products", "sort": "featured", "limit": 8, "columns": 4}`,
  'categories-grid': `{"title": "Shop by Category", "limit": 6, "layout": "grid"}`,
  'promo-banner': `{"image": "/uploads/products/placeholder.jpg", "title": "Summer Sale", "subtitle": "Limited time offer", "cta_text": "Shop Now", "cta_link": "/products", "height": "md"}`,
  'flash-sale': `{"title": "Flash Sale", "end_time": "2026-12-31T23:59:59", "limit": 6, "show_timer": true}`,
  'image-text': `{"image": "/uploads/products/placeholder.jpg", "heading": "Why Shop With Us", "body": "Quality products, fast delivery, and excellent support.", "image_position": "left"}`,
  'testimonials': `{"title": "What Customers Say", "testimonials": [{"name": "Jane D.", "rating": 5, "text": "Great service and fast delivery!"}], "layout": "grid"}`,
  'brand-showcase': `{"title": "Our Brands", "brands": [{"name": "Brand 1", "logo": "/uploads/brands/brand1.jpg", "link": "/products"}], "layout": "grid"}`,
  'newsletter': `{"title": "Join Our Newsletter", "description": "Subscribe for exclusive deals and updates.", "button_text": "Subscribe"}`,
  'trust-bar': `{"items": [{"icon": "truck", "title": "Fast Delivery", "description": "1-3 business days"}, {"icon": "shield", "title": "Secure Payment", "description": "M-Pesa & cards"}, {"icon": "refresh", "title": "Easy Returns", "description": "7-day return policy"}]}`,
  'featured-product': `{"product_id": 1, "badge": "Best Seller", "show_description": true, "show_reviews": true}`,
  'blog-posts': `{"title": "Latest News", "limit": 3, "columns": 3, "show_excerpt": true, "show_read_more": true}`,
  'payment-icons': `{"title": "We Accept", "icons": [{"name": "M-Pesa", "src": "/uploads/payments/mpesa.png", "alt": "M-Pesa"}]}`,
  'social-links': `{"title": "Follow Us", "links": [{"platform": "facebook", "url": "https://facebook.com", "label": "Facebook"}], "style": "icon-only", "align": "center"}`,
  'footer-links': `{"columns": [{"title": "Shop", "links": [{"label": "Products", "url": "/products"}]}], "bottom_text": "© 2026 My Store", "show_payment_icons": true, "show_social_links": true}`,
  'slider': `{"items": [{"image": "/uploads/products/placeholder.jpg", "title": "Slide 1", "subtitle": "Subtitle", "link": "/products"}], "autoplay": true, "show_dots": true, "slides_per_view": 1}`,
  'accordion': `{"title": "Frequently Asked Questions", "items": [{"question": "How do I order?", "answer": "Add products to cart and checkout."}], "allow_multiple": false, "style": "default"}`,
  'countdown': `{"target": "2026-12-31T23:59:59", "title": "Sale Ends In", "subtitle": "Hurry! Offer ends soon", "link": "/products"}`,
  'gallery': `{"images": [{"src": "/uploads/products/placeholder.jpg", "alt": "Gallery image", "caption": "Caption"}], "columns": 3, "gap": "md", "lightbox": true, "aspect": "square"}`,
  'icon-box': `{"items": [{"icon": "truck", "title": "Fast Delivery", "description": "1-3 business days"}], "layout": "grid", "columns": 3, "icon_style": "filled", "align": "center"}`,
  'map': `{"address": "Nairobi, Kenya", "lat": -1.2921, "lng": 36.8219, "height": 360, "zoom": 15, "marker_title": "Our Store"}`,
  'video-button': `{"video_url": "https://www.youtube.com/watch?v=dQw4w9WgXcQ", "title": "Watch Our Story", "button_text": "Play Video", "button_size": "md"}`,
  'button-block': `{"text": "Shop Now", "link": "/products", "style": "primary", "size": "md", "align": "center"}`,
  'message-box': `{"title": "Heads Up", "message": "Free shipping on orders over KES 5,000", "type": "info", "dismissible": true}`,
  'divider': `{"style": "solid", "width": "full", "align": "center", "spacing": "md"}`
};

function setTypedTemplate(type) {
  const props = document.getElementById('blockProps');
  const typeSel = document.getElementById('blockType');
  if (typeSel) typeSel.value = type;
  if (props && typedTemplates[type]) {
    props.value = typedTemplates[type];
  }
}

const templates = {
  grid3: `<div class="max-w-7xl mx-auto px-4 py-8">
  <h2 class="text-2xl font-bold text-center mb-6" style="color:var(--brand-color)">Featured Products</h2>
  <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-6">
    <div class="rounded-xl border border-slate-200 overflow-hidden shadow-sm">
      <img src="/uploads/products/placeholder.jpg" class="w-full h-48 object-cover" alt="">
      <div class="p-4">
        <h3 class="font-semibold text-slate-800">Product Name</h3>
        <p class="text-sm text-slate-500 mt-1">Short description</p>
        <div class="mt-3 font-bold" style="color:var(--brand-color)">KES 1,299</div>
      </div>
    </div>
    <!-- duplicate block for more products -->
  </div>
</div>`,
  trust: `<div class="max-w-7xl mx-auto px-4 py-6">
  <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 text-center">
    <div class="p-4 rounded-xl bg-slate-50 border border-slate-100">
      <i class="fas fa-truck text-2xl mb-2" style="color:var(--brand-color)"></i>
      <h4 class="font-semibold text-sm text-slate-700">Fast Delivery</h4>
      <p class="text-xs text-slate-500 mt-1">1-3 business days</p>
    </div>
    <div class="p-4 rounded-xl bg-slate-50 border border-slate-100">
      <i class="fas fa-shield-alt text-2xl mb-2" style="color:var(--brand-color)"></i>
      <h4 class="font-semibold text-sm text-slate-700">Secure Payment</h4>
      <p class="text-xs text-slate-500 mt-1">M-Pesa & cards</p>
    </div>
    <div class="p-4 rounded-xl bg-slate-50 border border-slate-100">
      <i class="fas fa-undo text-2xl mb-2" style="color:var(--brand-color)"></i>
      <h4 class="font-semibold text-sm text-slate-700">Easy Returns</h4>
      <p class="text-xs text-slate-500 mt-1">7-day return policy</p>
    </div>
  </div>
</div>`,
  promo: `<div class="max-w-7xl mx-auto px-4 py-6">
  <div class="rounded-2xl p-8 text-center text-white" style="background: linear-gradient(135deg, var(--brand-color), #c2410c)">
    <h2 class="text-3xl font-bold mb-2">Summer Sale</h2>
    <p class="text-lg opacity-90 mb-4">Up to 50% off selected items</p>
    <a href="/store/products?tenant=<?= $tenant_id ?>" class="inline-block px-6 py-2 rounded-full bg-white font-bold" style="color:var(--brand-color)">Shop Now</a>
  </div>
</div>`,
  shipping: `<div class="max-w-7xl mx-auto px-4 py-8">
  <h2 class="text-xl font-bold text-slate-800 mb-4">Shipping Information</h2>
  <div class="space-y-3 text-sm text-slate-600">
    <p><strong>Nairobi:</strong> Same-day delivery for orders placed before 2 PM.</p>
    <p><strong>Mombasa / Kisumu / Nakuru:</strong> 1-2 business days.</p>
    <p><strong>Rest of Kenya:</strong> 2-4 business days via Speedaf or G4S.</p>
    <p><strong>Free shipping</strong> on orders over KES 5,000.</p>
  </div>
</div>`,
  text: `<div class="max-w-3xl mx-auto px-4 py-10 text-center">
  <h2 class="text-3xl font-bold text-slate-800 mb-4">About Our Store</h2>
  <p class="text-slate-600 leading-relaxed">
    We are passionate about bringing you the best products at unbeatable prices.
    Every item is carefully selected to ensure quality and value for our customers.
  </p>
</div>`,
  twoCol: `<div class="max-w-7xl mx-auto px-4 py-10">
  <div class="grid grid-cols-1 md:grid-cols-2 gap-8 items-center">
    <div>
      <img src="/uploads/products/placeholder.jpg" class="w-full rounded-2xl shadow" alt="Feature">
    </div>
    <div>
      <h2 class="text-2xl font-bold text-slate-800 mb-3">Why Shop With Us?</h2>
      <p class="text-slate-600 leading-relaxed mb-4">
        Discover a curated selection of premium products backed by fast delivery and friendly support.
      </p>
      <a href="/store/products?tenant=<?= $tenant_id ?>" class="inline-block px-5 py-2 rounded-lg text-white font-semibold" style="background:var(--brand-color)">Browse Products</a>
    </div>
  </div>
</div>`
};
function insertTemplate(key) {
  const ta = document.getElementById('blockContent');
  if (!ta || !templates[key]) return;
  ta.value = templates[key];
  ta.focus();
  updatePreview();
}
/* Live preview */
function updatePreview() {
  const ta = document.getElementById('blockContent');
  const el = document.getElementById('livePreview');
  if (!ta || !el) return;
  el.innerHTML = ta.value;
}
document.addEventListener('DOMContentLoaded', function() {
  const ta = document.getElementById('blockContent');
  if (ta) { ta.addEventListener('input', updatePreview); updatePreview(); }
});
</script>

<?php
$page_content = ob_get_clean();
require_once __DIR__ . '/../../layouts/app.php';
?>