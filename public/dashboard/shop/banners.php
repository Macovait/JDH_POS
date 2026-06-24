<?php
/**
 * Store Admin — Banner Management
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
$msg = '';
$msg_type = 'success';

// ── CRUD Operations ──
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Delete banner
    if (isset($_POST['delete_banner'])) {
        try {
            $stmt = $pdo->prepare("DELETE FROM storefront_banners WHERE id = ? AND tenant_id = ?");
            $stmt->execute([(int)$_POST['banner_id'], $tenant_id]);
            $msg = 'Banner deleted successfully.';
            $msg_type = 'success';
        } catch (Exception $e) {
            $msg = 'Delete failed: ' . $e->getMessage();
            $msg_type = 'error';
        }
    }

    // Toggle active
    if (isset($_POST['toggle_banner'])) {
        try {
            $stmt = $pdo->prepare(
                "UPDATE storefront_banners 
                 SET is_active = NOT is_active 
                 WHERE id = ? AND tenant_id = ?"
            );
            $stmt->execute([(int)$_POST['banner_id'], $tenant_id]);
            $msg = 'Banner toggled successfully.';
            $msg_type = 'success';
        } catch (Exception $e) {
            $msg = 'Toggle failed: ' . $e->getMessage();
            $msg_type = 'error';
        }
    }

    // Save/Update banner
    if (isset($_POST['save_banner'])) {
        $bid = (int)($_POST['banner_id'] ?? 0);
        $data = [
            'title' => trim($_POST['title'] ?? ''),
            'subtitle' => trim($_POST['subtitle'] ?? ''),
            'image_url' => trim($_POST['image_url'] ?? ''),
            'link_url' => trim($_POST['link_url'] ?? ''),
            'link_text' => trim($_POST['link_text'] ?? 'Shop Now'),
            'position' => $_POST['position'] ?? 'hero',
            'display_order' => (int)($_POST['display_order'] ?? 0),
            'is_active' => isset($_POST['is_active']) ? 1 : 0,
        ];

        if (empty($data['title']) || empty($data['image_url'])) {
            $msg = 'Title and image URL are required.';
            $msg_type = 'error';
        } else {
            try {
                if ($bid) {
                    // Update
                    $stmt = $pdo->prepare(
                        "UPDATE storefront_banners 
                         SET title = ?, subtitle = ?, image_url = ?, link_url = ?, 
                             link_text = ?, position = ?, display_order = ?, is_active = ?,
                             updated_at = NOW() 
                         WHERE id = ? AND tenant_id = ?"
                    );
                    $stmt->execute(array_merge(array_values($data), [$bid, $tenant_id]));
                    $msg = 'Banner updated successfully!';
                } else {
                    // Insert
                    $stmt = $pdo->prepare(
                        "INSERT INTO storefront_banners 
                         (tenant_id, title, subtitle, image_url, link_url, link_text, position, display_order, is_active) 
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
                    );
                    $stmt->execute(array_merge([$tenant_id], array_values($data)));
                    $msg = 'Banner added successfully!';
                }
                $msg_type = 'success';
            } catch (Exception $e) {
                $msg = 'Error: ' . $e->getMessage();
                $msg_type = 'error';
            }
        }

        // Redirect to avoid resubmission
        header('Location: banners.php?msg=' . urlencode($msg) . '&type=' . $msg_type);
        exit;
    }

    // Reorder banners
    if (isset($_POST['reorder_banners']) && isset($_POST['order'])) {
        try {
            $pdo->beginTransaction();
            foreach ($_POST['order'] as $id => $order) {
                $stmt = $pdo->prepare(
                    "UPDATE storefront_banners 
                     SET display_order = ? 
                     WHERE id = ? AND tenant_id = ?"
                );
                $stmt->execute([(int)$order, (int)$id, $tenant_id]);
            }
            $pdo->commit();
            $msg = 'Banner order updated successfully.';
            $msg_type = 'success';
        } catch (Exception $e) {
            $pdo->rollBack();
            $msg = 'Reorder failed: ' . $e->getMessage();
            $msg_type = 'error';
        }
    }

    // Duplicate banner
    if (isset($_POST['duplicate_banner'])) {
        try {
            $stmt = $pdo->prepare("SELECT * FROM storefront_banners WHERE id = ? AND tenant_id = ?");
            $stmt->execute([(int)$_POST['banner_id'], $tenant_id]);
            $original = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($original) {
                unset($original['id']);
                $original['title'] = $original['title'] . ' (Copy)';
                $original['display_order'] = (int)$original['display_order'] + 1;

                $stmt = $pdo->prepare(
                    "INSERT INTO storefront_banners 
                     (tenant_id, title, subtitle, image_url, link_url, link_text, position, display_order, is_active) 
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
                );
                $stmt->execute([
                    $tenant_id, $original['title'], $original['subtitle'],
                    $original['image_url'], $original['link_url'], $original['link_text'],
                    $original['position'], $original['display_order'], $original['is_active']
                ]);
                $msg = 'Banner duplicated successfully.';
                $msg_type = 'success';
            }
        } catch (Exception $e) {
            $msg = 'Duplicate failed: ' . $e->getMessage();
            $msg_type = 'error';
        }
    }

    // Redirect if not AJAX
    if ($msg_type === 'success' && empty($_POST['ajax'])) {
        header('Location: banners.php?msg=' . urlencode($msg) . '&type=' . $msg_type);
        exit;
    }
}

// ── Load banners ──
$banners = [];
try {
    $stmt = $pdo->prepare(
        "SELECT * FROM storefront_banners 
         WHERE tenant_id = ? 
         ORDER BY FIELD(position, 'hero', 'featured', 'promo', 'sidebar'), display_order ASC"
    );
    $stmt->execute([$tenant_id]);
    $banners = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // Silence
}

// Edit mode
$editBanner = null;
if (isset($_GET['edit'])) {
    foreach ($banners as $b) {
        if ($b['id'] == $_GET['edit']) {
            $editBanner = $b;
            break;
        }
    }
}

// Load storefront settings for sidebar
$settings = [];
try {
    $stmt = $pdo->prepare("SELECT setting_key, setting_value FROM storefront_settings WHERE tenant_id = ?");
    $stmt->execute([$tenant_id]);
    while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $settings[$r['setting_key']] = $r['setting_value'];
    }
} catch (Exception $e) {}

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
$page_title = 'Banners';

// Handle messages from redirects
if (isset($_GET['msg'])) {
    $msg = $_GET['msg'];
    $msg_type = $_GET['type'] ?? 'success';
}

ob_start();
?>

<div class="space-y-6">

    <!-- Header -->
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
        <div>
            <h1 class="text-2xl font-bold text-white flex items-center gap-3">
                <i class="fas fa-images text-amber-400 text-xl"></i>
                Banners &amp; Sliders
            </h1>
            <p class="text-sm text-slate-500 mt-1">Manage homepage hero banners and promotional images</p>
        </div>
        <div class="flex items-center gap-3">
            <a href="blocks.php" class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-slate-800/80 border border-slate-700/60 text-slate-400 text-xs font-medium hover:bg-slate-700/80 hover:text-white hover:border-slate-600 transition-all duration-200">
                <i class="fas fa-cubes text-[10px]"></i> Blocks
            </a>
            <a href="customize.php" class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-slate-800/80 border border-slate-700/60 text-slate-400 text-xs font-medium hover:bg-slate-700/80 hover:text-white hover:border-slate-600 transition-all duration-200">
                <i class="fas fa-paint-brush text-[10px]"></i> Customize
            </a>
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

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        <!-- LEFT: Banner List -->
        <div class="lg:col-span-2 space-y-6">
            <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl overflow-hidden">
                <div class="px-6 py-4 border-b border-slate-700/60 flex items-center justify-between">
                    <h2 class="text-base font-bold text-white flex items-center gap-2">
                        <i class="fas fa-list text-amber-400 text-sm"></i>
                        All Banners
                    </h2>
                    <span class="text-xs text-slate-500 bg-slate-800/60 px-2.5 py-1 rounded-full border border-slate-700/40">
                        <?= count($banners) ?> total
                    </span>
                </div>

                <?php if (empty($banners)): ?>
                <div class="text-center py-16 text-slate-500">
                    <div class="w-16 h-16 rounded-full bg-slate-800/80 flex items-center justify-center mx-auto mb-4">
                        <i class="fas fa-image text-2xl text-slate-600"></i>
                    </div>
                    <p class="font-medium text-slate-400">No banners yet</p>
                    <p class="text-xs mt-1">Add banners to display on your store homepage</p>
                    <a href="?new=1" class="inline-flex items-center gap-2 mt-4 px-4 py-2 rounded-xl text-white text-xs font-bold transition-all hover:opacity-90 shadow-lg" style="background: <?= $brand_color ?>">
                        <i class="fas fa-plus"></i> Add First Banner
                    </a>
                </div>
                <?php else: ?>
                <div class="divide-y divide-slate-700/40">
                    <?php
                    $positionLabels = [
                        'hero' => 'Hero (Main Slider)',
                        'featured' => 'Featured Section',
                        'promo' => 'Promo Strip',
                        'sidebar' => 'Sidebar'
                    ];
                    foreach ($banners as $b):
                        $isActive = (int)$b['is_active'];
                    ?>
                    <div class="block-item flex items-center gap-4 px-4 py-3 hover:bg-slate-700/20 transition-colors group">
                        <!-- Image thumbnail -->
                        <div class="w-20 h-14 bg-slate-700/30 rounded-lg overflow-hidden flex-shrink-0 border border-slate-700/40">
                            <?php if ($b['image_url']): ?>
                            <img src="<?= htmlspecialchars($b['image_url']) ?>" class="w-full h-full object-cover" alt="<?= htmlspecialchars($b['title']) ?>" onerror="this.parentElement.innerHTML='<i class=\'fas fa-image text-slate-500 text-xl flex items-center justify-center w-full h-full\'></i>'">
                            <?php else: ?>
                            <div class="w-full h-full flex items-center justify-center">
                                <i class="fas fa-image text-slate-500 text-xl"></i>
                            </div>
                            <?php endif; ?>
                        </div>

                        <!-- Info -->
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2 flex-wrap">
                                <span class="text-[10px] font-medium px-2 py-0.5 rounded-full bg-slate-700/50 text-slate-400">
                                    <?= $positionLabels[$b['position']] ?? ucfirst($b['position']) ?>
                                </span>
                                <span class="text-[10px] font-medium px-2 py-0.5 rounded-full <?= $isActive ? 'bg-emerald-500/15 text-emerald-400' : 'bg-slate-500/15 text-slate-400' ?>">
                                    <i class="fas <?= $isActive ? 'fa-circle' : 'fa-circle' ?> text-[6px] mr-1 <?= $isActive ? 'text-emerald-400' : 'text-slate-500' ?>"></i>
                                    <?= $isActive ? 'Active' : 'Hidden' ?>
                                </span>
                            </div>
                            <p class="text-sm font-medium text-white truncate"><?= htmlspecialchars($b['title'] ?: '(No Title)') ?></p>
                            <?php if ($b['subtitle']): ?>
                            <p class="text-xs text-slate-500 truncate"><?= htmlspecialchars($b['subtitle']) ?></p>
                            <?php endif; ?>
                            <p class="text-[10px] text-slate-600 mt-0.5">Order: <?= (int)$b['display_order'] ?></p>
                        </div>

                        <!-- Actions -->
                        <div class="flex items-center gap-1.5 shrink-0">
                            <!-- Toggle -->
                            <form method="POST" class="inline">
                                <input type="hidden" name="banner_id" value="<?= $b['id'] ?>">
                                <input type="hidden" name="toggle_banner" value="1">
                                <button type="submit"
                                    class="w-8 h-8 rounded-lg bg-slate-800/60 border border-slate-700/40 flex items-center justify-center text-slate-400 hover:bg-slate-700/60 hover:text-white transition-all"
                                    title="Toggle visibility">
                                    <i class="fas <?= $isActive ? 'fa-eye' : 'fa-eye-slash' ?> text-[10px]"></i>
                                </button>
                            </form>

                            <!-- Duplicate -->
                            <form method="POST" class="inline">
                                <input type="hidden" name="banner_id" value="<?= $b['id'] ?>">
                                <input type="hidden" name="duplicate_banner" value="1">
                                <button type="submit"
                                    class="w-8 h-8 rounded-lg bg-slate-800/60 border border-slate-700/40 flex items-center justify-center text-slate-400 hover:bg-slate-700/60 hover:text-white transition-all"
                                    title="Duplicate">
                                    <i class="fas fa-copy text-[10px]"></i>
                                </button>
                            </form>

                            <!-- Edit -->
                            <a href="?edit=<?= $b['id'] ?>"
                                class="w-8 h-8 rounded-lg bg-slate-800/60 border border-slate-700/40 flex items-center justify-center text-slate-400 hover:bg-blue-500/20 hover:text-blue-400 hover:border-blue-500/30 transition-all"
                                title="Edit">
                                <i class="fas fa-pen text-[10px]"></i>
                            </a>

                            <!-- Delete -->
                            <form method="POST" class="inline" onsubmit="return confirm('Delete this banner? This action cannot be undone.');">
                                <input type="hidden" name="banner_id" value="<?= $b['id'] ?>">
                                <input type="hidden" name="delete_banner" value="1">
                                <button type="submit"
                                    class="w-8 h-8 rounded-lg bg-slate-800/60 border border-slate-700/40 flex items-center justify-center text-slate-400 hover:bg-red-500/20 hover:text-red-400 hover:border-red-500/30 transition-all"
                                    title="Delete">
                                    <i class="fas fa-trash text-[10px]"></i>
                                </button>
                            </form>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <!-- Reorder Form -->
                <div class="px-6 py-4 border-t border-slate-700/60 bg-slate-900/30">
                    <form method="POST" class="space-y-3">
                        <input type="hidden" name="reorder_banners" value="1">
                        <div class="flex flex-wrap items-center gap-3">
                            <span class="text-xs font-medium text-slate-500">Reorder:</span>
                            <div class="flex flex-wrap gap-3">
                                <?php foreach ($banners as $b): ?>
                                <div class="flex items-center gap-1.5 bg-slate-800/60 rounded-lg px-2 py-1 border border-slate-700/40">
                                    <span class="text-[10px] text-slate-500 truncate max-w-[80px]"><?= htmlspecialchars($b['title'] ?: 'Banner ' . $b['id']) ?></span>
                                    <input type="number" name="order[<?= $b['id'] ?>]"
                                        value="<?= (int)$b['display_order'] ?>"
                                        min="0"
                                        class="w-12 text-center text-xs bg-slate-900/50 border border-slate-600 rounded text-slate-300 focus:outline-none focus:border-amber-500 focus:ring-1 focus:ring-amber-500 py-0.5">
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <button type="submit"
                            class="inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-lg bg-slate-700/60 text-slate-300 text-xs font-medium hover:bg-slate-600/60 hover:text-white transition-all border border-slate-600/40">
                            <i class="fas fa-save text-[10px]"></i> Save Order
                        </button>
                    </form>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- RIGHT: Form -->
        <div class="space-y-6">

            <!-- Add/Edit Form -->
            <?php if (isset($_GET['new']) || $editBanner): ?>
            <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-6">
                <h2 class="text-base font-bold text-white mb-4 flex items-center gap-2">
                    <i class="fas <?= $editBanner ? 'fa-pen' : 'fa-plus' ?> text-amber-400 text-sm"></i>
                    <?= $editBanner ? 'Edit Banner' : 'Add New Banner' ?>
                </h2>

                <form method="POST" id="bannerForm" class="space-y-4">
                    <input type="hidden" name="save_banner" value="1">
                    <?php if ($editBanner): ?>
                    <input type="hidden" name="banner_id" value="<?= $editBanner['id'] ?>">
                    <?php endif; ?>

                    <!-- Title -->
                    <div>
                        <label class="block text-xs font-medium text-slate-400 mb-1">Title <span class="text-red-400">*</span></label>
                        <input type="text" name="title"
                            value="<?= htmlspecialchars($editBanner['title'] ?? '') ?>"
                            required
                            placeholder="e.g. Big Sale This Weekend"
                            class="w-full bg-slate-900/50 text-slate-200 border border-slate-700/60 rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-amber-500/50 focus:ring-1 focus:ring-amber-500/20 transition-all">
                    </div>

                    <!-- Subtitle -->
                    <div>
                        <label class="block text-xs font-medium text-slate-400 mb-1">Subtitle</label>
                        <input type="text" name="subtitle"
                            value="<?= htmlspecialchars($editBanner['subtitle'] ?? '') ?>"
                            placeholder="e.g. Up to 50% off on selected items"
                            class="w-full bg-slate-900/50 text-slate-200 border border-slate-700/60 rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-amber-500/50 focus:ring-1 focus:ring-amber-500/20 transition-all">
                    </div>

                    <!-- Image URL -->
                    <div>
                        <label class="block text-xs font-medium text-slate-400 mb-1">Image URL <span class="text-red-400">*</span></label>
                        <input type="url" name="image_url" id="imgUrl"
                            value="<?= htmlspecialchars($editBanner['image_url'] ?? '') ?>"
                            required
                            placeholder="https://example.com/banner.jpg"
                            class="w-full bg-slate-900/50 text-slate-200 border border-slate-700/60 rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-amber-500/50 focus:ring-1 focus:ring-amber-500/20 transition-all"
                            oninput="document.getElementById('imgPreview').src=this.value||''; document.getElementById('imgPreview').classList.toggle('hidden', !this.value)">
                        <div class="mt-2">
                            <img id="imgPreview"
                                src="<?= htmlspecialchars($editBanner['image_url'] ?? '') ?>"
                                class="h-24 rounded-lg object-cover w-full border border-slate-700/40 <?= ($editBanner['image_url'] ?? '') ? '' : 'hidden' ?>"
                                onerror="this.classList.add('hidden')"
                                onload="this.classList.remove('hidden')"
                                alt="Banner preview">
                        </div>
                    </div>

                    <!-- Link URL & Button Text -->
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-medium text-slate-400 mb-1">Link URL</label>
                            <input type="url" name="link_url"
                                value="<?= htmlspecialchars($editBanner['link_url'] ?? '') ?>"
                                placeholder="https://..."
                                class="w-full bg-slate-900/50 text-slate-200 border border-slate-700/60 rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-amber-500/50 focus:ring-1 focus:ring-amber-500/20 transition-all">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-400 mb-1">Button Text</label>
                            <input type="text" name="link_text"
                                value="<?= htmlspecialchars($editBanner['link_text'] ?? 'Shop Now') ?>"
                                placeholder="Shop Now"
                                class="w-full bg-slate-900/50 text-slate-200 border border-slate-700/60 rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-amber-500/50 focus:ring-1 focus:ring-amber-500/20 transition-all">
                        </div>
                    </div>

                    <!-- Position & Order -->
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-medium text-slate-400 mb-1">Position</label>
                            <select name="position"
                                class="w-full bg-slate-900/50 text-slate-200 border border-slate-700/60 rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-amber-500/50 focus:ring-1 focus:ring-amber-500/20 transition-all">
                                <?php foreach (['hero' => 'Hero (Main Slider)', 'featured' => 'Featured Section', 'promo' => 'Promo Strip', 'sidebar' => 'Sidebar'] as $pv => $pl): ?>
                                <option value="<?= $pv ?>" <?= ($editBanner['position'] ?? 'hero') === $pv ? 'selected' : '' ?>><?= $pl ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-400 mb-1">Display Order</label>
                            <input type="number" name="display_order"
                                value="<?= (int)($editBanner['display_order'] ?? 0) ?>"
                                min="0"
                                class="w-full bg-slate-900/50 text-slate-200 border border-slate-700/60 rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-amber-500/50 focus:ring-1 focus:ring-amber-500/20 transition-all">
                        </div>
                    </div>

                    <!-- Active -->
                    <label class="flex items-center gap-3 cursor-pointer group">
                        <input type="checkbox" name="is_active" value="1"
                            <?= !isset($editBanner) || ($editBanner['is_active'] ?? 1) ? 'checked' : '' ?>
                            class="w-4 h-4 rounded border-slate-600 bg-slate-800 text-amber-500 focus:ring-amber-500/20 focus:ring-offset-0">
                        <span class="text-sm text-slate-300 group-hover:text-white transition-colors">Active (visible on storefront)</span>
                    </label>

                    <!-- Submit -->
                    <div class="flex gap-2">
                        <button type="submit"
                            class="flex-1 py-2.5 rounded-xl text-white font-bold text-sm transition-all duration-200 hover:opacity-90 hover:shadow-lg"
                            style="background: <?= $brand_color ?>">
                            <i class="fas <?= $editBanner ? 'fa-save' : 'fa-plus' ?> mr-2"></i>
                            <?= $editBanner ? 'Update Banner' : 'Add Banner' ?>
                        </button>
                        <a href="banners.php"
                            class="px-4 py-2.5 rounded-xl bg-slate-700/50 text-slate-300 font-bold text-sm hover:bg-slate-700 hover:text-white transition-all">
                            Cancel
                        </a>
                    </div>
                </form>
            </div>
            <?php else: ?>
            <!-- Quick Add Button -->
            <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-6 text-center">
                <div class="w-14 h-14 rounded-full bg-amber-500/10 flex items-center justify-center mx-auto mb-3 border border-amber-500/20">
                    <i class="fas fa-plus text-amber-400 text-lg"></i>
                </div>
                <h3 class="text-sm font-semibold text-white mb-1">Add New Banner</h3>
                <p class="text-xs text-slate-500 mb-4">Create a new promotional banner for your store</p>
                <a href="?new=1" class="inline-flex items-center gap-2 px-4 py-2 rounded-xl text-white text-xs font-bold transition-all hover:opacity-90 shadow-lg" style="background: <?= $brand_color ?>">
                    <i class="fas fa-plus"></i> Create Banner
                </a>
            </div>
            <?php endif; ?>

            <!-- Quick Tips -->
            <div class="bg-slate-900/50 border border-slate-700/60 rounded-xl p-4">
                <h3 class="text-xs font-bold text-slate-500 uppercase tracking-wider mb-2 flex items-center gap-2">
                    <i class="fas fa-lightbulb text-amber-400"></i> Banner Tips
                </h3>
                <ul class="text-[11px] text-slate-500 space-y-1.5 leading-relaxed">
                    <li>· <span class="text-slate-400">Recommended size:</span> 1200×400px for hero banners</li>
                    <li>· <span class="text-slate-400">Hero banners</span> appear in the main slider</li>
                    <li>· <span class="text-slate-400">Featured</span> banners show below hero section</li>
                    <li>· <span class="text-slate-400">Promo strips</span> are compact promotional bars</li>
                    <li>· <span class="text-slate-400">Lower order numbers</span> appear first</li>
                    <li>· <span class="text-slate-400">Use high-quality images</span> for better conversion</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<script>
// Image preview update
document.addEventListener('DOMContentLoaded', function() {
    const imgUrl = document.getElementById('imgUrl');
    const imgPreview = document.getElementById('imgPreview');

    if (imgUrl && imgPreview) {
        imgUrl.addEventListener('input', function() {
            const url = this.value.trim();
            if (url) {
                imgPreview.src = url;
                imgPreview.classList.remove('hidden');
                imgPreview.onerror = function() {
                    this.classList.add('hidden');
                };
            } else {
                imgPreview.classList.add('hidden');
            }
        });
    }

    // Template button click feedback
    document.querySelectorAll('.template-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            document.querySelectorAll('.template-btn').forEach(b => {
                b.classList.remove('border-amber-500/30', 'bg-slate-800/60');
            });
            this.classList.add('border-amber-500/30', 'bg-slate-800/60');
        });
    });
});
</script>

<?php
$page_content = ob_get_clean();
require_once __DIR__ . '/../../layouts/app.php';
?>