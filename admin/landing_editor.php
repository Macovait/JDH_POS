<?php
/**
 * Landing Page Editor - SaaS Admin
 *
 * Edit all public landing page sections without touching code.
 * Super Admin only.
 */

require_once __DIR__ . '/bootstrap.php';
admin_require_super_admin();

$pdo = admin_require_db();

$prefix = 'pos_';

$message = '';
$message_type = '';

$sections_config = [
    'trust_strip'   => ['label' => 'Trust Badges',    'icon' => 'fa-shield-alt',      'desc' => 'Compliance & integration logos shown below the hero.'],
    'how_it_works'  => ['label' => 'How It Works',    'icon' => 'fa-list-ol',         'desc' => '3-step onboarding flow.'],
    'products'      => ['label' => 'Products',          'icon' => 'fa-box-open',        'desc' => 'POS / ERP / E-Commerce product tabs.'],
    'ai_slides'     => ['label' => 'AI Section',       'icon' => 'fa-robot',           'desc' => 'AI feature carousel slides.'],
    'testimonials'  => ['label' => 'Testimonials',      'icon' => 'fa-comment-dots',    'desc' => 'Customer success stories.'],
    'faq'           => ['label' => 'FAQ',              'icon' => 'fa-circle-question', 'desc' => 'Frequently asked questions.'],
    'features'      => ['label' => 'Features Grid',     'icon' => 'fa-grid-2',          'desc' => 'Feature cards (reports, inventory, invoices, etc.).'],
    'cta'           => ['label' => 'CTA Section',       'icon' => 'fa-bullhorn',        'desc' => 'Bottom call-to-action banner.'],
    'about'         => ['label' => 'About Section',     'icon' => 'fa-circle-info',     'desc' => 'About / mission section content.'],
];

// Generate CSRF nonce
$editor_nonce = $_SESSION['landing_editor_nonce'] ?? bin2hex(random_bytes(32));
$_SESSION['landing_editor_nonce'] = $editor_nonce;

// Handle POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['_nonce']) || $_POST['_nonce'] !== $editor_nonce) {
        $message = 'Invalid security token. Please refresh and try again.';
        $message_type = 'error';
    } else {
        $action = $_POST['action'] ?? '';
        $section = $_POST['section'] ?? '';

        try {
            switch ($action) {
                case 'update_block':
                    $block_id = (int) ($_POST['block_id'] ?? 0);
                    $title = trim($_POST['title'] ?? '');
                    $subtitle = trim($_POST['subtitle'] ?? '');
                    $content = trim($_POST['content'] ?? '');
                    $image_url = trim($_POST['image_url'] ?? '');
                    $icon_class = trim($_POST['icon_class'] ?? '');
                    $sort_order = (int) ($_POST['sort_order'] ?? 0);
                    $is_active = isset($_POST['is_active']) ? 1 : 0;

                    if ($block_id > 0) {
                        $stmt = $pdo->prepare("UPDATE {$prefix}landing_blocks SET title = ?, subtitle = ?, content = ?, image_url = ?, icon_class = ?, sort_order = ?, is_active = ? WHERE id = ?");
                        $stmt->execute([$title, $subtitle, $content, $image_url, $icon_class, $sort_order, $is_active, $block_id]);
                        $message = 'Block updated successfully.';
                        $message_type = 'success';
                    }
                    break;

                case 'add_block':
                    $block_key = preg_replace('/[^a-z0-9_]/', '_', strtolower(trim($_POST['block_key'] ?? '')));
                    if (empty($block_key)) {
                        $block_key = 'block_' . time();
                    }
                    $title = trim($_POST['title'] ?? '');
                    $subtitle = trim($_POST['subtitle'] ?? '');
                    $content = trim($_POST['content'] ?? '');
                    $image_url = trim($_POST['image_url'] ?? '');
                    $icon_class = trim($_POST['icon_class'] ?? '');
                    $sort_order = (int) ($_POST['sort_order'] ?? 0);

                    $stmt = $pdo->prepare("INSERT INTO {$prefix}landing_blocks (section, block_key, title, subtitle, content, image_url, icon_class, sort_order, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1)");
                    $stmt->execute([$section, $block_key, $title, $subtitle, $content, $image_url, $icon_class, $sort_order]);
                    $message = 'New block added successfully.';
                    $message_type = 'success';
                    break;

                case 'delete_block':
                    $block_id = (int) ($_POST['block_id'] ?? 0);
                    if ($block_id > 0) {
                        $stmt = $pdo->prepare("DELETE FROM {$prefix}landing_blocks WHERE id = ?");
                        $stmt->execute([$block_id]);
                        $message = 'Block deleted.';
                        $message_type = 'success';
                    }
                    break;

                case 'toggle_active':
                    $block_id = (int) ($_POST['block_id'] ?? 0);
                    if ($block_id > 0) {
                        $stmt = $pdo->prepare("UPDATE {$prefix}landing_blocks SET is_active = NOT is_active WHERE id = ?");
                        $stmt->execute([$block_id]);
                        $message = 'Block visibility toggled.';
                        $message_type = 'success';
                    }
                    break;
            }

            // Regenerate nonce
            $_SESSION['landing_editor_nonce'] = bin2hex(random_bytes(32));

            // Redirect to avoid re-submission
            $tab = $section ?: 'trust_strip';
            while (ob_get_level() > 0) { ob_end_clean(); }
            header('Location: landing_editor.php?saved=1&tab=' . urlencode($tab));
            exit;

        } catch (Exception $e) {
            error_log("Landing editor error: " . $e->getMessage());
            $message = 'Error: ' . $e->getMessage();
            $message_type = 'error';
        }
    }
}

$show_toast = isset($_GET['saved']) && $_GET['saved'] === '1';
$active_tab = $_GET['tab'] ?? 'trust_strip';
if (!isset($sections_config[$active_tab])) {
    $active_tab = array_key_first($sections_config);
}

// Load blocks for all sections
$all_blocks = [];
foreach (array_keys($sections_config) as $sec) {
    $stmt = $pdo->prepare("SELECT * FROM {$prefix}landing_blocks WHERE section = ? ORDER BY sort_order ASC, id ASC");
    $stmt->execute([$sec]);
    $all_blocks[$sec] = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$current_page = 'landing_editor';
$page_title = 'Landing Page Editor';
?>
<!DOCTYPE html>
<html lang="en" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Landing Page Editor | JDH POS Admin</title>
    <link rel="stylesheet" href="<?php echo asset_url('css/app.css'); ?>">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            background: radial-gradient(circle at 20% 20%, rgba(34, 197, 94, 0.12), transparent 30%),
                        radial-gradient(circle at 80% 0%, rgba(59, 130, 246, 0.12), transparent 32%),
                        #0b1021;
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
            color: #e5e7eb;
        }
        .content-wrapper { margin-left: 260px; min-height: 100vh; display: flex; flex-direction: column; }
        @media (max-width: 767px) { .content-wrapper { margin-left: 0; } }
        .main-content { flex: 1; padding: 24px; }
        .glass-panel {
            background: rgba(17, 24, 39, 0.6);
            backdrop-filter: blur(24px);
            -webkit-backdrop-filter: blur(24px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 1rem;
        }
        .toast {
            position: fixed; top: 24px; right: 24px; z-index: 9999;
            padding: 14px 24px; border-radius: 0.75rem;
            display: flex; align-items: center; gap: 10px;
            font-size: 14px; font-weight: 500;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.4);
            animation: toastIn 0.4s ease, toastOut 0.4s ease 3.6s forwards;
            pointer-events: none;
        }
        .toast-success { background: rgba(34, 197, 94, 0.15); border: 1px solid rgba(34, 197, 94, 0.3); color: #4ade80; }
        .toast-error { background: rgba(239, 68, 68, 0.15); border: 1px solid rgba(239, 68, 68, 0.3); color: #f87171; }
        @keyframes toastIn { from { transform: translateX(100%); opacity: 0; } to { transform: translateX(0); opacity: 1; } }
        @keyframes toastOut { from { transform: translateX(0); opacity: 1; } to { transform: translateX(100%); opacity: 0; } }

        .tab-container {
            display: flex; flex-wrap: wrap; gap: 8px; padding: 4px;
            background: rgba(0, 0, 0, 0.2); border-radius: 12px; margin-bottom: 24px;
        }
        .tab-btn {
            padding: 10px 16px; border-radius: 8px; font-size: 13px; font-weight: 500;
            color: rgba(255, 255, 255, 0.6); background: transparent; border: none;
            cursor: pointer; transition: all 0.25s ease;
            display: inline-flex; align-items: center; gap: 8px; white-space: nowrap;
        }
        .tab-btn:hover { color: rgba(255, 255, 255, 0.9); background: rgba(255, 255, 255, 0.08); }
        .tab-btn.active { background: rgba(255, 255, 255, 0.1); color: #fff; box-shadow: 0 2px 8px rgba(0,0,0,0.2); }
        .tab-btn.active.amber { color: #fbbf24; background: rgba(251, 191, 36, 0.15); }

        .form-input {
            width: 100%; padding: 10px 14px;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1); border-radius: 0.5rem;
            color: #fff; font-size: 14px; outline: none;
            transition: border-color 0.2s, box-shadow 0.2s;
        }
        .form-input:focus { border-color: rgba(245, 158, 11, 0.5); box-shadow: 0 0 0 3px rgba(245, 158, 11, 0.1); }
        .form-input::placeholder { color: rgba(255, 255, 255, 0.3); }
        .form-textarea {
            width: 100%; min-height: 80px; padding: 12px 14px;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1); border-radius: 0.5rem;
            color: #fff; font-size: 14px; outline: none; resize: vertical; font-family: inherit;
            transition: border-color 0.2s, box-shadow 0.2s;
        }
        .form-textarea:focus { border-color: rgba(245, 158, 11, 0.5); box-shadow: 0 0 0 3px rgba(245, 158, 11, 0.1); }

        .block-card {
            background: rgba(255, 255, 255, 0.02);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 0.75rem; padding: 20px;
            transition: all 0.3s ease;
        }
        .block-card:hover { border-color: rgba(255, 255, 255, 0.15); background: rgba(255, 255, 255, 0.03); }
        .block-card.inactive { opacity: 0.5; }

        .save-btn {
            background: linear-gradient(135deg, #f59e0b, #d97706);
            color: #fff; font-weight: 600; padding: 10px 24px;
            border-radius: 0.75rem; border: none; cursor: pointer; font-size: 14px;
            transition: all 0.2s ease; display: inline-flex; align-items: center; gap: 8px;
        }
        .save-btn:hover { transform: translateY(-1px); box-shadow: 0 8px 24px rgba(245, 158, 11, 0.3); }

        .btn-secondary {
            background: rgba(255,255,255,0.05); color: #cbd5e1;
            padding: 10px 20px; border-radius: 0.75rem; border: 1px solid rgba(255,255,255,0.1);
            cursor: pointer; font-size: 14px; transition: all 0.2s;
            display: inline-flex; align-items: center; gap: 8px;
        }
        .btn-secondary:hover { background: rgba(255,255,255,0.1); }

        .btn-danger {
            background: rgba(239, 68, 68, 0.1); color: #f87171;
            padding: 8px 16px; border-radius: 0.5rem; border: 1px solid rgba(239, 68, 68, 0.2);
            cursor: pointer; font-size: 13px; transition: all 0.2s;
            display: inline-flex; align-items: center; gap: 6px;
        }
        .btn-danger:hover { background: rgba(239, 68, 68, 0.2); }

        .btn-ghost {
            background: transparent; color: #94a3b8;
            padding: 8px 16px; border-radius: 0.5rem; border: 1px solid transparent;
            cursor: pointer; font-size: 13px; transition: all 0.2s;
            display: inline-flex; align-items: center; gap: 6px;
        }
        .btn-ghost:hover { color: #fff; background: rgba(255,255,255,0.05); }

        .toggle-switch { position: relative; width: 44px; height: 24px; flex-shrink: 0; }
        .toggle-switch input { opacity: 0; width: 0; height: 0; }
        .toggle-slider { position: absolute; cursor: pointer; top:0;left:0;right:0;bottom:0;
            background: rgba(255,255,255,0.1); border-radius: 24px; transition: 0.3s; }
        .toggle-slider:before { content: ""; position: absolute; height: 18px; width: 18px;
            left: 3px; bottom: 3px; background: #fff; border-radius: 50%; transition: 0.3s; }
        .toggle-switch input:checked + .toggle-slider { background: #22c55e; }
        .toggle-switch input:checked + .toggle-slider:before { transform: translateX(20px); }

        .preview-link {
            color: #60a5fa; text-decoration: none; font-size: 13px;
            display: inline-flex; align-items: center; gap: 6px;
        }
        .preview-link:hover { text-decoration: underline; }
    </style>
</head>
<body>
    <?php require_once __DIR__ . '/components/sidebar.php'; ?>

    <div class="content-wrapper">
        <main class="main-content">
            <?php if ($show_toast): ?>
            <div class="toast toast-success" id="toast">
                <i class="fa-solid fa-check-circle"></i>
                <span>Changes saved successfully.</span>
            </div>
            <script>
                setTimeout(() => { const t = document.getElementById('toast'); if(t) t.remove(); }, 4000);
            </script>
            <?php endif; ?>

            <?php if ($message && !$show_toast): ?>
            <div class="mb-6 p-4 rounded-xl <?= $message_type === 'success' ? 'bg-green-500/10 border border-green-500/20 text-green-400' : 'bg-red-500/10 border-red-500/20 text-red-400' ?>">
                <i class="fa-solid <?= $message_type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle' ?> mr-2"></i>
                <?= htmlspecialchars($message) ?>
            </div>
            <?php endif; ?>

            <!-- Header -->
            <div class="mb-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <p class="text-slate-400 text-sm">Super Admin / Landing Page</p>
                    <h1 class="text-2xl font-bold text-white mt-1">Landing Page Editor</h1>
                    <p class="text-slate-400 text-sm mt-1">Edit all public-facing sections without touching code.</p>
                </div>
                <a href="<?= base_url('index.php') ?>" target="_blank" class="preview-link">
                    <i class="fa-solid fa-external-link-alt"></i> Preview Landing Page
                </a>
            </div>

            <!-- Section Tabs -->
            <div class="tab-container">
                <?php foreach ($sections_config as $slug => $cfg): ?>
                <button class="tab-btn <?= ($active_tab === $slug) ? 'active amber' : '' ?>"
                        onclick="switchTab('<?= $slug ?>')">
                    <i class="fas <?= $cfg['icon'] ?>"></i>
                    <?= $cfg['label'] ?>
                    <span class="ml-1 text-xs opacity-60">(<?= count($all_blocks[$slug] ?? []) ?>)</span>
                </button>
                <?php endforeach; ?>
            </div>

            <!-- Tab Content -->
            <?php foreach ($sections_config as $section => $cfg): ?>
            <div class="tab-content <?= ($active_tab === $section) ? 'active' : '' ?>" id="tab-<?= $section ?>" style="<?= ($active_tab === $section) ? '' : 'display:none;' ?>">
                <div class="glass-panel p-6 md:p-8">
                    <div class="mb-6">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-lg bg-amber-500/15 flex items-center justify-center">
                                <i class="fas <?= $cfg['icon'] ?> text-amber-400"></i>
                            </div>
                            <div>
                                <h2 class="text-lg font-semibold text-white"><?= $cfg['label'] ?></h2>
                                <p class="text-slate-400 text-sm"><?= $cfg['desc'] ?></p>
                            </div>
                        </div>
                    </div>

                    <!-- Existing Blocks -->
                    <div class="space-y-4 mb-8">
                        <?php foreach ($all_blocks[$section] ?? [] as $block): ?>
                        <div class="block-card <?= $block['is_active'] ? '' : 'inactive' ?>" id="block-<?= $block['id'] ?>">
                            <form method="POST" class="space-y-4">
                                <input type="hidden" name="_nonce" value="<?= $editor_nonce ?>">
                                <input type="hidden" name="action" value="update_block">
                                <input type="hidden" name="section" value="<?= htmlspecialchars($section) ?>">
                                <input type="hidden" name="block_id" value="<?= (int)$block['id'] ?>">

                                <div class="flex items-center justify-between">
                                    <div class="flex items-center gap-3">
                                        <span class="text-xs font-mono text-slate-500 bg-slate-800 px-2 py-1 rounded"><?= htmlspecialchars($block['block_key']) ?></span>
                                        <?php if ($block['icon_class']): ?>
                                        <i class="<?= htmlspecialchars($block['icon_class']) ?> text-amber-400 text-sm"></i>
                                        <?php endif; ?>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <form method="POST" class="inline">
                                            <input type="hidden" name="_nonce" value="<?= $editor_nonce ?>">
                                            <input type="hidden" name="action" value="toggle_active">
                                            <input type="hidden" name="section" value="<?= htmlspecialchars($section) ?>">
                                            <input type="hidden" name="block_id" value="<?= (int)$block['id'] ?>">
                                            <button type="submit" class="btn-ghost" title="Toggle visibility">
                                                <i class="fa-solid fa-eye<?= $block['is_active'] ? '' : '-slash' ?>"></i>
                                            </button>
                                        </form>
                                        <form method="POST" class="inline" onsubmit="return confirm('Delete this block?')">
                                            <input type="hidden" name="_nonce" value="<?= $editor_nonce ?>">
                                            <input type="hidden" name="action" value="delete_block">
                                            <input type="hidden" name="section" value="<?= htmlspecialchars($section) ?>">
                                            <input type="hidden" name="block_id" value="<?= (int)$block['id'] ?>">
                                            <button type="submit" class="btn-danger" title="Delete">
                                                <i class="fa-solid fa-trash"></i>
                                            </button>
                                        </form>
                                    </div>
                                </div>

                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-xs text-slate-400 mb-1.5 font-medium">Title</label>
                                        <input type="text" name="title" value="<?= htmlspecialchars($block['title'] ?? '') ?>" class="form-input" placeholder="Block title">
                                    </div>
                                    <div>
                                        <label class="block text-xs text-slate-400 mb-1.5 font-medium">Subtitle</label>
                                        <input type="text" name="subtitle" value="<?= htmlspecialchars($block['subtitle'] ?? '') ?>" class="form-input" placeholder="Short subtitle">
                                    </div>
                                </div>

                                <div>
                                    <label class="block text-xs text-slate-400 mb-1.5 font-medium">Content</label>
                                    <textarea name="content" class="form-textarea" placeholder="Main content (description, quote, answer, etc.)"><?= htmlspecialchars($block['content'] ?? '') ?></textarea>
                                </div>

                                <div>
                                    <label class="block text-xs text-slate-400 mb-1.5 font-medium">Image URL</label>
                                    <div class="flex gap-2">
                                        <input type="text" name="image_url" value="<?= htmlspecialchars($block['image_url'] ?? '') ?>" class="form-input flex-1" placeholder="uploads/landing/image.png or https://...">
                                        <?php if (!empty($block['image_url'])): ?>
                                        <a href="<?= htmlspecialchars($block['image_url']) ?>" target="_blank" class="btn-secondary flex-shrink-0" title="Preview image">
                                            <i class="fa-solid fa-image"></i>
                                        </a>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                    <div>
                                        <label class="block text-xs text-slate-400 mb-1.5 font-medium">Icon Class <span class="opacity-50">(Font Awesome)</span></label>
                                        <input type="text" name="icon_class" value="<?= htmlspecialchars($block['icon_class'] ?? '') ?>" class="form-input" placeholder="fas fa-icon">
                                    </div>
                                    <div>
                                        <label class="block text-xs text-slate-400 mb-1.5 font-medium">Sort Order</label>
                                        <input type="number" name="sort_order" value="<?= (int)($block['sort_order'] ?? 0) ?>" class="form-input" min="0">
                                    </div>
                                    <div class="flex items-end pb-2">
                                        <label class="flex items-center gap-3 cursor-pointer">
                                            <span class="toggle-switch">
                                                <input type="checkbox" name="is_active" <?= $block['is_active'] ? 'checked' : '' ?>>
                                                <span class="toggle-slider"></span>
                                            </span>
                                            <span class="text-sm text-slate-300">Active</span>
                                        </label>
                                    </div>
                                </div>

                                <div class="flex justify-end">
                                    <button type="submit" class="save-btn">
                                        <i class="fa-solid fa-floppy-disk"></i> Save Changes
                                    </button>
                                </div>
                            </form>
                        </div>
                        <?php endforeach; ?>

                        <?php if (empty($all_blocks[$section])): ?>
                        <div class="text-center py-12 rounded-xl border border-dashed border-slate-700">
                            <i class="fa-solid fa-layer-group text-3xl text-slate-600 mb-3"></i>
                            <p class="text-slate-400">No blocks in this section yet.</p>
                        </div>
                        <?php endif; ?>
                    </div>

                    <!-- Add New Block -->
                    <div class="border-t border-slate-700/50 pt-6">
                        <h3 class="text-sm font-semibold text-white mb-4 flex items-center gap-2">
                            <i class="fa-solid fa-plus-circle text-amber-400"></i> Add New Block
                        </h3>
                        <form method="POST" class="block-card">
                            <input type="hidden" name="_nonce" value="<?= $editor_nonce ?>">
                            <input type="hidden" name="action" value="add_block">
                            <input type="hidden" name="section" value="<?= htmlspecialchars($section) ?>">

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                                <div>
                                    <label class="block text-xs text-slate-400 mb-1.5 font-medium">Block Key <span class="opacity-50">(unique ID)</span></label>
                                    <input type="text" name="block_key" class="form-input" placeholder="e.g. new_feature_1" required>
                                </div>
                                <div>
                                    <label class="block text-xs text-slate-400 mb-1.5 font-medium">Sort Order</label>
                                    <input type="number" name="sort_order" value="<?= count($all_blocks[$section] ?? []) + 1 ?>" class="form-input" min="0">
                                </div>
                            </div>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                                <div>
                                    <label class="block text-xs text-slate-400 mb-1.5 font-medium">Title</label>
                                    <input type="text" name="title" class="form-input" placeholder="Block title">
                                </div>
                                <div>
                                    <label class="block text-xs text-slate-400 mb-1.5 font-medium">Subtitle</label>
                                    <input type="text" name="subtitle" class="form-input" placeholder="Short subtitle">
                                </div>
                            </div>
                            <div class="mb-4">
                                <label class="block text-xs text-slate-400 mb-1.5 font-medium">Content</label>
                                <textarea name="content" class="form-textarea" placeholder="Main content (description, quote, answer, etc.)"></textarea>
                            </div>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                                <div>
                                    <label class="block text-xs text-slate-400 mb-1.5 font-medium">Image URL</label>
                                    <input type="text" name="image_url" class="form-input" placeholder="uploads/landing/image.png or https://...">
                                </div>
                                <div>
                                    <label class="block text-xs text-slate-400 mb-1.5 font-medium">Icon Class <span class="opacity-50">(Font Awesome)</span></label>
                                    <input type="text" name="icon_class" class="form-input" placeholder="fas fa-icon">
                                </div>
                            </div>
                            <div class="flex justify-end">
                                <button type="submit" class="save-btn">
                                    <i class="fa-solid fa-plus"></i> Add Block
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </main>
    </div>

    <script>
    function switchTab(tab) {
        document.querySelectorAll('.tab-content').forEach(el => el.style.display = 'none');
        document.querySelectorAll('.tab-btn').forEach(el => {
            el.classList.remove('active', 'amber');
        });
        const content = document.getElementById('tab-' + tab);
        if (content) content.style.display = 'block';
        event.currentTarget.classList.add('active', 'amber');
        // Update URL without reload
        const url = new URL(window.location);
        url.searchParams.set('tab', tab);
        window.history.replaceState({}, '', url);
    }
    </script>
</body>
</html>
