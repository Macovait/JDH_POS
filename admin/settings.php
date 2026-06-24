<?php
/**
 * Global System Settings - SaaS Admin
 *
 * Manage platform-wide settings grouped by category.
 * Super Admin only.
 */

require_once __DIR__ . '/bootstrap.php';

admin_require_super_admin();

$pdo = admin_require_db(['admins']);

$message = '';
$message_type = '';

// Generate CSRF nonce
$settings_nonce = $_SESSION['settings_nonce'] ?? bin2hex(random_bytes(32));
$_SESSION['settings_nonce'] = $settings_nonce;

// Handle POST: update settings
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF nonce
    if (!isset($_POST['_nonce']) || $_POST['_nonce'] !== $settings_nonce) {
        $message = 'Invalid security token. Please refresh and try again.';
        $message_type = 'error';
    } else {
        $updated = 0;
        $upload_dir = realpath(__DIR__ . '/../public/uploads/branding') ?: __DIR__ . '/../public/uploads/branding';
        if (!is_dir($upload_dir)) {
            @mkdir($upload_dir, 0755, true);
        }

        try {
            $settings = db_fetch_all("SELECT setting_key, input_type FROM system_settings");

            foreach ($settings as $setting) {
                $key = $setting['setting_key'];
                $input_type = $setting['input_type'] ?? 'text';

                // Handle image uploads
                if ($input_type === 'image') {
                    if (isset($_FILES[$key]) && $_FILES[$key]['error'] === UPLOAD_ERR_OK) {
                        $file = $_FILES[$key];
                        $allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml', 'image/x-icon', 'image/vnd.microsoft.icon'];
                        $finfo = finfo_open(FILEINFO_MIME_TYPE);
                        $mime = finfo_file($finfo, $file['tmp_name']);
                        finfo_close($finfo);

                        if (!in_array($mime, $allowed)) {
                            continue; // Skip invalid file types silently
                        }

                        // Generate safe filename
                        $ext = pathinfo($file['name'], PATHINFO_EXTENSION) ?: 'png';
                        $filename = $key . '_' . time() . '.' . strtolower($ext);
                        $dest = $upload_dir . DIRECTORY_SEPARATOR . $filename;

                        if (move_uploaded_file($file['tmp_name'], $dest)) {
                            // Delete old file if exists
                            $old_value = db_fetch_one("SELECT setting_value FROM system_settings WHERE setting_key = ?", [$key]);
                            if ($old_value && !empty($old_value['setting_value'])) {
                                $old_path = realpath(__DIR__ . '/../public/' . $old_value['setting_value']);
                                if ($old_path && file_exists($old_path) && strpos($old_path, 'uploads') !== false) {
                                    @unlink($old_path);
                                }
                            }

                            $value = 'uploads/branding/' . $filename;
                            db_query("UPDATE system_settings SET setting_value = ? WHERE setting_key = ?", [$value, $key]);
                            $updated++;
                        }
                    }
                    // Check if user wants to remove the image
                    if (isset($_POST[$key . '_remove']) && $_POST[$key . '_remove'] === '1') {
                        $old_value = db_fetch_one("SELECT setting_value FROM system_settings WHERE setting_key = ?", [$key]);
                        if ($old_value && !empty($old_value['setting_value'])) {
                            $old_path = realpath(__DIR__ . '/../public/' . $old_value['setting_value']);
                            if ($old_path && file_exists($old_path) && strpos($old_path, 'uploads') !== false) {
                                @unlink($old_path);
                            }
                        }
                        db_query("UPDATE system_settings SET setting_value = '' WHERE setting_key = ?", [$key]);
                        $updated++;
                    }
                    continue;
                }

                if ($input_type === 'checkbox') {
                    $value = isset($_POST[$key]) ? '1' : '0';
                } else {
                    if (!isset($_POST[$key])) {
                        continue;
                    }
                    $value = $_POST[$key];
                }

                // For password fields, only update if a new value is provided
                if ($input_type === 'password' && $value === '') {
                    continue;
                }

                db_query("UPDATE system_settings SET setting_value = ? WHERE setting_key = ?", [$value, $key]);
                $updated++;
            }

            if ($updated > 0) {
                $message = "Settings updated successfully ({$updated} fields).";
                $message_type = 'success';
            } else {
                $message = 'No settings were changed.';
                $message_type = 'success';
            }
            // Regenerate nonce after successful update
            $_SESSION['settings_nonce'] = bin2hex(random_bytes(32));
        } catch (\Exception $e) {
            error_log("Settings update error: " . $e->getMessage());
            $message = 'Failed to update settings: ' . $e->getMessage();
            $message_type = 'error';
        }
    }

    // Redirect to avoid form re-submission on refresh, keep active tab
    if ($message_type === 'success') {
        $tab = $_POST['__tab'] ?? 'general';
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        header('Location: settings.php?saved=1&tab=' . urlencode($tab));
        exit;
    }
}

// Detect saved toast
$show_toast = isset($_GET['saved']) && $_GET['saved'] === '1';
$active_tab = $_GET['tab'] ?? 'general';

// Load all settings grouped
$all_settings = db_fetch_all("SELECT * FROM system_settings ORDER BY setting_group, id");

$grouped = [];
foreach ($all_settings as $s) {
    $grouped[$s['setting_group']][] = $s;
}

$tab_labels = [
    'general'       => ['label' => 'General',       'icon' => 'fa-globe',         'color' => 'green'],
    'landing'       => ['label' => 'Landing Page', 'icon' => 'fa-image',         'color' => 'cyan'],
    'seo'           => ['label' => 'SEO & Social', 'icon' => 'fa-share-nodes', 'color' => 'blue'],
    'ecommerce'    => ['label' => 'E-commerce',  'icon' => 'fa-cart-shopping', 'color' => 'purple'],
    'payment'      => ['label' => 'Payment',      'icon' => 'fa-credit-card', 'color' => 'yellow'],
    'security'     => ['label' => 'Security',     'icon' => 'fa-shield-halved', 'color' => 'red'],
    'performance'  => ['label' => 'Performance',  'icon' => 'fa-bolt',        'color' => 'orange'],
    'notifications'=> ['label' => 'Notifications', 'icon' => 'fa-bell',       'color' => 'pink'],
];

$current_page = 'settings';
$page_title = 'Settings';
?>
<!DOCTYPE html>
<html lang="en" class="dark">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings | JDH POS Admin</title>
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

        .content-wrapper {
            margin-left: 260px;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        @media (max-width: 767px) {
            .content-wrapper {
                margin-left: 0;
            }
        }

        .main-content {
            flex: 1;
            padding: 24px;
        }

        .glass-panel {
            background: rgba(17, 24, 39, 0.6);
            backdrop-filter: blur(24px);
            -webkit-backdrop-filter: blur(24px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 1rem;
        }

        /* Toast */
        .toast {
            position: fixed;
            top: 24px;
            right: 24px;
            z-index: 9999;
            padding: 14px 24px;
            border-radius: 0.75rem;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 14px;
            font-weight: 500;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.4);
            animation: toastIn 0.4s ease, toastOut 0.4s ease 3.6s forwards;
            pointer-events: none;
        }

        .toast-success {
            background: rgba(34, 197, 94, 0.15);
            border: 1px solid rgba(34, 197, 94, 0.3);
            color: #4ade80;
        }

        .toast-error {
            background: rgba(239, 68, 68, 0.15);
            border: 1px solid rgba(239, 68, 68, 0.3);
            color: #f87171;
        }

        @keyframes toastIn {
            from { transform: translateX(100%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }

        @keyframes toastOut {
            from { transform: translateX(0); opacity: 1; }
            to { transform: translateX(100%); opacity: 0; }
        }

        /* Toggle switch */
        .toggle-switch {
            position: relative;
            width: 44px;
            height: 24px;
            flex-shrink: 0;
        }

        .toggle-switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }

        .toggle-slider {
            position: absolute;
            cursor: pointer;
            top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 24px;
            transition: 0.3s;
        }

        .toggle-slider:before {
            content: "";
            position: absolute;
            height: 18px;
            width: 18px;
            left: 3px;
            bottom: 3px;
            background: #fff;
            border-radius: 50%;
            transition: 0.3s;
        }

        .toggle-switch input:checked + .toggle-slider {
            background: #22c55e;
        }

        .toggle-switch input:checked + .toggle-slider:before {
            transform: translateX(20px);
        }

        /* Tab styles */
        .tab-btn {
            padding: 10px 20px;
            border-radius: 0.75rem;
            font-size: 13px;
            font-weight: 500;
            color: rgba(255, 255, 255, 0.5);
            background: transparent;
            border: 1px solid transparent;
            cursor: pointer;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            white-space: nowrap;
        }

        .tab-btn:hover {
            color: rgba(255, 255, 255, 0.8);
            background: rgba(255, 255, 255, 0.05);
        }

        .tab-btn.active {
            color: #22c55e;
            background: rgba(34, 197, 94, 0.1);
            border-color: rgba(34, 197, 94, 0.3);
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }

        /* Form inputs */
        .form-input {
            width: 100%;
            padding: 10px 14px;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 0.5rem;
            color: #fff;
            font-size: 14px;
            outline: none;
            transition: border-color 0.2s, box-shadow 0.2s;
        }

        .form-input:focus {
            border-color: rgba(34, 197, 94, 0.5);
            box-shadow: 0 0 0 3px rgba(34, 197, 94, 0.1);
        }

        .form-input::placeholder {
            color: rgba(255, 255, 255, 0.3);
        }

        .form-select {
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' fill='rgba(255,255,255,0.4)' viewBox='0 0 16 16'%3E%3Cpath d='M8 11L3 6h10z'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 12px center;
            padding-right: 36px;
        }

        .save-btn {
            background: linear-gradient(135deg, #22c55e, #16a34a);
            color: #fff;
            font-weight: 600;
            padding: 12px 32px;
            border-radius: 0.75rem;
            border: none;
            cursor: pointer;
            font-size: 14px;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .save-btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 8px 24px rgba(34, 197, 94, 0.3);
        }

        .form-textarea {
            width: 100%;
            min-height: 100px;
            padding: 12px 14px;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 0.5rem;
            color: #fff;
            font-size: 14px;
            outline: none;
            transition: border-color 0.2s, box-shadow 0.2s;
            resize: vertical;
            font-family: inherit;
        }

        .form-textarea:focus {
            border-color: rgba(34, 197, 94, 0.5);
            box-shadow: 0 0 0 3px rgba(34, 197, 94, 0.1);
        }

        .form-textarea::placeholder {
            color: rgba(255, 255, 255, 0.3);
        }

        /* Section cards */
        .section-card {
            background: rgba(255, 255, 255, 0.02);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 0.75rem;
            padding: 20px;
            margin-bottom: 20px;
            transition: all 0.3s ease;
        }

        .section-card:hover {
            border-color: rgba(255, 255, 255, 0.15);
            background: rgba(255, 255, 255, 0.03);
        }

        .section-card h3 {
            font-size: 14px;
            font-weight: 600;
            color: #fff;
            margin-bottom: 4px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .section-card p {
            font-size: 12px;
            color: rgba(255, 255, 255, 0.5);
            margin-bottom: 16px;
        }

        /* Preview panel */
        .preview-panel {
            background: linear-gradient(135deg, #1e293b, #0f172a);
            border: 1px solid rgba(59, 130, 246, 0.2);
            border-radius: 0.75rem;
            padding: 16px;
            margin-bottom: 16px;
        }

        .preview-panel h4 {
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: rgba(255, 255, 255, 0.4);
            margin-bottom: 12px;
        }

        .preview-title {
            font-size: 14px;
            color: #60a5fa;
            font-weight: 500;
            margin-bottom: 4px;
        }

        .preview-url {
            font-size: 12px;
            color: #4ade80;
            margin-bottom: 8px;
        }

        .preview-desc {
            font-size: 13px;
            color: rgba(255, 255, 255, 0.7);
            line-height: 1.5;
        }

        /* Enhanced tabs */
        .tab-container {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            padding: 4px;
            background: rgba(0, 0, 0, 0.2);
            border-radius: 12px;
            margin-bottom: 24px;
        }

        .tab-btn {
            padding: 12px 20px;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 500;
            color: rgba(255, 255, 255, 0.6);
            background: transparent;
            border: none;
            cursor: pointer;
            transition: all 0.25s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            white-space: nowrap;
        }

        .tab-btn:hover {
            color: rgba(255, 255, 255, 0.9);
            background: rgba(255, 255, 255, 0.08);
        }

        .tab-btn.active {
            background: rgba(255, 255, 255, 0.1);
            color: #fff;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
        }

        .tab-btn.active.green { color: #4ade80; background: rgba(34, 197, 94, 0.15); }
        .tab-btn.active.blue { color: #60a5fa; background: rgba(59, 130, 246, 0.15); }
        .tab-btn.active.purple { color: #c084fc; background: rgba(168, 85, 247, 0.15); }
        .tab-btn.active.yellow { color: #fbbf24; background: rgba(251, 191, 36, 0.15); }
        .tab-btn.active.red { color: #f87171; background: rgba(239, 68, 68, 0.15); }
        .tab-btn.active.orange { color: #fb923c; background: rgba(251, 146, 60, 0.15); }
        .tab-btn.active.pink { color: #f472b6; background: rgba(244, 114, 182, 0.15); }

        .tab-icon {
            width: 32px;
            height: 32px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
        }

        .tab-icon.green { background: rgba(34, 197, 94, 0.15); color: #4ade80; }
        .tab-icon.blue { background: rgba(59, 130, 246, 0.15); color: #60a5fa; }
        .tab-icon.purple { background: rgba(168, 85, 247, 0.15); color: #c084fc; }
        .tab-icon.yellow { background: rgba(251, 191, 36, 0.15); color: #fbbf24; }
        .tab-icon.red { background: rgba(239, 68, 68, 0.15); color: #f87171; }
        .tab-icon.orange { background: rgba(251, 146, 60, 0.15); color: #fb923c; }
        .tab-icon.pink { background: rgba(244, 114, 182, 0.15); color: #f472b6; }

        /* Group badge */
        .group-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
    </style>
<?php
ob_start();
?>
    <?php if ($show_toast): ?>
    <div class="toast toast-success" id="toast">
        <i class="fa-solid fa-check-circle"></i>
        <span>Settings saved successfully.</span>
    </div>
    <script>
        setTimeout(function() {
            var t = document.getElementById('toast');
            if (t) t.remove();
        }, 4000);
    </script>
    <?php endif; ?>

    <div>
            <!-- Page header -->
            <div class="mb-6">
                <p class="text-slate-400 text-sm">Super Admin / Global Settings</p>
            </div>

            <?php if ($message && !$show_toast): ?>
            <div class="mb-6 p-4 rounded-xl <?= $message_type === 'success' ? 'bg-green-500/10 border border-green-500/20 text-green-400' : 'bg-red-500/10 border-red-500/20 text-red-400' ?>">
                <i class="fa-solid <?= $message_type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle' ?> mr-2"></i>
                <?= htmlspecialchars($message) ?>
            </div>
            <?php endif; ?>

            <!-- Tab navigation -->
            <div class="tab-container">
                <?php foreach ($tab_labels as $slug => $tab): 
                    $color = $tab['color'] ?? 'green';
                ?>
                    <button class="tab-btn <?= ($active_tab === $slug) ? 'active ' . $color : '' ?>"
                            data-tab="<?= $slug ?>"
                            onclick="switchTab('<?= $slug ?>')">
                        <span class="tab-icon <?= $color ?>"><i class="fa-solid <?= $tab['icon'] ?>"></i></span>
                        <?= $tab['label'] ?>
                    </button>
                <?php endforeach; ?>
            </div>

            <!-- Tab content -->
            <?php foreach ($tab_labels as $group => $tab): 
                $color = $tab['color'] ?? 'green';
            ?>
            <div class="tab-content <?= ($active_tab === $group) ? 'active' : '' ?>" id="tab-<?= $group ?>">
                <div class="glass-panel p-6 md:p-8">
                    <div class="mb-8">
                        <div class="flex items-center gap-3">
                            <span class="tab-icon <?= $color ?>"><i class="fa-solid <?= $tab['icon'] ?>"></i></span>
                            <div>
                                <h2 class="text-xl font-semibold text-white"><?= $tab['label'] ?> Settings</h2>
                                <p class="text-slate-400 text-sm mt-1">Configure <?= strtolower($tab['label']) ?> options for the platform.</p>
                            </div>
                        </div>
                    </div>

                    <?php if (!empty($grouped[$group])): ?>
                    <form method="POST" class="settings-form" data-tab="<?= $group ?>" enctype="multipart/form-data">
                        <input type="hidden" name="__tab" value="<?= $group ?>">
                        <input type="hidden" name="_nonce" value="<?= $settings_nonce ?>">

                        <!-- SEO Preview Panel (shown for seo tab) -->
                        <?php if ($group === 'seo'): ?>
                        <div class="preview-panel" id="seo-preview">
                            <h4><i class="fa-solid fa-magnifying-glass mr-2"></i>Search Preview</h4>
                            <p class="preview-title" id="preview-title"><?= htmlspecialchars($grouped['general'][0]['setting_value'] ?? 'Your Site Title') ?></p>
                            <p class="preview-url" id="preview-url">https://yoursite.com</p>
                            <p class="preview-desc" id="preview-desc">Your meta description will appear here. This is how your site will appear in search results.</p>
                        </div>
                        <?php endif; ?>

                        <!-- Currency Preview Panel (shown for ecommerce tab) -->
                        <?php if ($group === 'ecommerce'): ?>
                        <div class="preview-panel" id="currency-preview">
                            <h4><i class="fa-solid fa-dollar-sign mr-2"></i>Price Preview</h4>
                            <p class="preview-title" id="preview-price">1,234.56</p>
                            <p class="preview-desc text-xs text-slate-500">Sample price with your currency settings</p>
                        </div>
                        <?php endif; ?>

                        <div class="space-y-2">
                            <?php foreach ($grouped[$group] as $setting):
                                $key       = $setting['setting_key'];
                                $label     = $setting['setting_label'] ?? $key;
                                $desc      = $setting['setting_description'] ?? '';
                                $value     = $setting['setting_value'] ?? '';
                                $inputType = $setting['input_type'] ?? 'text';
                                $options   = json_decode($setting['setting_options'] ?? '{}', true);
                            ?>
                            <div class="section-card">
                                <h3><i class="fa-solid fa-cog"></i> <?= htmlspecialchars($label) ?></h3>
                                <?php if ($desc): ?>
                                    <p><?= htmlspecialchars($desc) ?></p>
                                <?php endif; ?>

                                <div class="mt-4">
                                    <?php if ($inputType === 'checkbox'): ?>
                                        <label class="toggle-switch">
                                            <input type="checkbox"
                                                   id="field-<?= htmlspecialchars($key) ?>"
                                                   name="<?= htmlspecialchars($key) ?>"
                                                   <?= $value === '1' || $value === 'true' ? 'checked' : '' ?>>
                                            <span class="toggle-slider"></span>
                                        </label>

                                    <?php elseif ($inputType === 'select'): ?>
                                        <select id="field-<?= htmlspecialchars($key) ?>"
                                                name="<?= htmlspecialchars($key) ?>"
                                                class="form-input form-select">
                                            <?php if (is_array($options)): ?>
                                                <?php foreach ($options as $optValue => $optLabel): ?>
                                                    <option value="<?= htmlspecialchars($optValue) ?>"
                                                        <?= ($value == $optValue) ? 'selected' : '' ?>>
                                                        <?= htmlspecialchars($optLabel) ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </select>

                                    <?php elseif ($inputType === 'password'): ?>
                                        <input type="password"
                                               id="field-<?= htmlspecialchars($key) ?>"
                                               name="<?= htmlspecialchars($key) ?>"
                                               value=""
                                               placeholder="Leave blank to keep current"
                                               class="form-input"
                                               autocomplete="new-password">

                                    <?php elseif ($inputType === 'number'): ?>
                                        <input type="number"
                                               id="field-<?= htmlspecialchars($key) ?>"
                                               name="<?= htmlspecialchars($key) ?>"
                                               value="<?= htmlspecialchars($value) ?>"
                                               class="form-input">

                                    <?php elseif ($inputType === 'email'): ?>
                                        <input type="email"
                                               id="field-<?= htmlspecialchars($key) ?>"
                                               name="<?= htmlspecialchars($key) ?>"
                                               value="<?= htmlspecialchars($value) ?>"
                                               class="form-input">

                                    <?php elseif ($inputType === 'textarea'): ?>
                                        <textarea id="field-<?= htmlspecialchars($key) ?>"
                                                  name="<?= htmlspecialchars($key) ?>"
                                                  class="form-textarea"
                                                  placeholder="<?= htmlspecialchars($desc) ?>"><?= htmlspecialchars($value) ?></textarea>

                                    <?php elseif ($inputType === 'image'): ?>
                                        <div class="space-y-3">
                                            <?php if ($value): ?>
                                            <div class="relative inline-block group">
                                                <img src="<?= htmlspecialchars('../public/' . $value) ?>"
                                                     alt="<?= htmlspecialchars($label) ?>"
                                                     class="h-20 max-w-[200px] object-contain rounded-lg border border-slate-700 bg-slate-800 p-2">
                                                <label class="absolute -top-2 -right-2 w-6 h-6 bg-red-500 hover:bg-red-400 rounded-full flex items-center justify-center cursor-pointer opacity-0 group-hover:opacity-100 transition-opacity" title="Remove image">
                                                    <input type="hidden" name="<?= htmlspecialchars($key) ?>_remove" value="0">
                                                    <input type="checkbox" class="hidden" onchange="this.previousElementSibling.value = this.checked ? '1' : '0'; this.closest('.group').querySelector('img').style.opacity = this.checked ? '0.3' : '1';">
                                                    <i class="fa-solid fa-xmark text-white text-xs"></i>
                                                </label>
                                            </div>
                                            <?php else: ?>
                                            <div class="h-20 w-32 rounded-lg border-2 border-dashed border-slate-700 bg-slate-800/50 flex items-center justify-center">
                                                <i class="fa-solid fa-image text-slate-600 text-xl"></i>
                                            </div>
                                            <?php endif; ?>
                                            <div>
                                                <label for="field-<?= htmlspecialchars($key) ?>" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-slate-800 hover:bg-slate-700 border border-slate-600 text-slate-300 text-sm cursor-pointer transition">
                                                    <i class="fa-solid fa-cloud-arrow-up"></i>
                                                    <?= $value ? 'Replace Image' : 'Upload Image' ?>
                                                </label>
                                                <input type="file"
                                                       id="field-<?= htmlspecialchars($key) ?>"
                                                       name="<?= htmlspecialchars($key) ?>"
                                                       accept="image/*"
                                                       class="hidden"
                                                       onchange="this.closest('.space-y-3').querySelector('.file-name').textContent = this.files[0]?.name || ''">
                                                <span class="file-name text-xs text-slate-500 ml-2"></span>
                                            </div>
                                        </div>

                                    <?php else: ?>
                                        <input type="text"
                                               id="field-<?= htmlspecialchars($key) ?>"
                                               name="<?= htmlspecialchars($key) ?>"
                                               value="<?= htmlspecialchars($value) ?>"
                                               class="form-input">
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>

                        <div class="mt-8 flex justify-end items-center gap-4">
                            <span class="text-xs text-slate-500"><i class="fa-solid fa-lock mr-1"></i>Secured with CSRF token</span>
                            <button type="submit" class="save-btn">
                                <i class="fa-solid fa-floppy-disk"></i>
                                Save <?= $tab['label'] ?> Settings
                            </button>
                        </div>
                    </form>
                    <?php else: ?>
                        <div class="text-center py-16">
                            <div class="w-20 h-20 mx-auto mb-4 rounded-full bg-slate-800/50 flex items-center justify-center">
                                <i class="fa-solid fa-gear text-3xl text-slate-600"></i>
                            </div>
                            <h3 class="text-lg font-medium text-slate-300 mb-2">No Settings Configured</h3>
                            <p class="text-slate-500">No <?= strtolower($tab['label']) ?> settings available yet.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <?php require_once __DIR__ . '/components/footer.php'; ?>
    </div>

    <script>
        function switchTab(tabId) {
            document.querySelectorAll('.tab-content').forEach(function(el) {
                el.classList.remove('active');
            });
            document.querySelectorAll('.tab-btn').forEach(function(el) {
                el.classList.remove('active');
            });
            document.getElementById('tab-' + tabId).classList.add('active');
            document.querySelector('[data-tab="' + tabId + '"]').classList.add('active');
            
            // Update URL without refresh
            var url = new URL(window.location);
            url.searchParams.set('tab', tabId);
            window.history.pushState({}, '', url);
        }

        // SEO Live Preview
        document.addEventListener('DOMContentLoaded', function() {
            var siteNameField = document.getElementById('field-site_name');
            var metaDescField = document.getElementById('field-seo_meta_description');
            var siteUrlField = document.getElementById('field-site_url');
            
            if (siteNameField) {
                siteNameField.addEventListener('input', function(e) {
                    var titleEl = document.getElementById('preview-title');
                    if (titleEl) titleEl.textContent = e.target.value || 'Your Site Title';
                });
            }
            
            if (metaDescField) {
                metaDescField.addEventListener('input', function(e) {
                    var descEl = document.getElementById('preview-desc');
                    if (descEl) descEl.textContent = e.target.value.substring(0, 160) || 'Your meta description will appear here.';
                });
            }

            if (siteUrlField) {
                siteUrlField.addEventListener('input', function(e) {
                    var urlEl = document.getElementById('preview-url');
                    if (urlEl) urlEl.textContent = e.target.value || 'https://yoursite.com';
                });
            }

            // Currency Preview
            var currencySymbol = document.getElementById('field-currency_symbol');
            var decimalPlaces = document.getElementById('field-decimal_places');
            var decimalSep = document.getElementById('field-decimal_separator');
            var thousandSep = document.getElementById('field-thousand_separator');

            function updateCurrencyPreview() {
                var priceEl = document.getElementById('preview-price');
                if (!priceEl) return;
                
                var sample = 1234.56;
                var symbol = currencySymbol ? currencySymbol.value : 'KES';
                var decimals = decimalPlaces ? parseInt(decimalPlaces.value) : 2;
                var dsep = decimalSep ? decimalSep.value : '.';
                var tsep = thousandSep ? thousandSep.value : ',';
                
                var formatted = sample.toFixed(decimals).replace('.', dsep).replace(/\B(?=(\d{3})+(?!\d))/g, tsep);
                priceEl.textContent = symbol + formatted;
            }

            if (currencySymbol) currencySymbol.addEventListener('input', updateCurrencyPreview);
            if (decimalPlaces) decimalPlaces.addEventListener('change', updateCurrencyPreview);
            if (decimalSep) decimalSep.addEventListener('change', updateCurrencyPreview);
            if (thousandSep) thousandSep.addEventListener('change', updateCurrencyPreview);

            // Auto-resize textareas
            document.querySelectorAll('.form-textarea').forEach(function(textarea) {
                textarea.style.height = 'auto';
                textarea.style.height = textarea.scrollHeight + 'px';
                textarea.addEventListener('input', function() {
                    this.style.height = 'auto';
                    this.style.height = this.scrollHeight + 'px';
                });
            });
        });
    </script>

<?php
$page_content = ob_get_clean();
require_once __DIR__ . '/layouts/super_admin.php';
?>
