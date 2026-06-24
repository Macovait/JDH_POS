<?php
/**
 * Notifications / Alerts - SaaS Admin
 * Manage system and admin notifications
 */

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/NotificationsModel.php';

admin_require_super_admin();

$context = TenantContext::getInstance();
$notificationsModel = new NotificationsModel();

$message = '';
$message_type = '';

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    try {
        switch ($action) {
            case 'mark_read':
                $notif_id = (int)($_POST['notification_id'] ?? 0);
                if ($notif_id) {
                    $notificationsModel->markAsRead($notif_id);
                }
                $message = 'Notification marked as read.';
                $message_type = 'success';
                break;
            case 'mark_all_read':
                $notificationsModel->markAllAsRead();
                $message = 'All notifications marked as read.';
                $message_type = 'success';
                break;
            case 'delete':
                $notif_id = (int)($_POST['notification_id'] ?? 0);
                if ($notif_id) {
                    $notificationsModel->deleteNotification($notif_id);
                }
                $message = 'Notification deleted.';
                $message_type = 'success';
                break;
            case 'create':
                $type = trim($_POST['type'] ?? 'info');
                $title = trim($_POST['title'] ?? '');
                $msg = trim($_POST['message'] ?? '');
                $link = trim($_POST['link'] ?? '');
                if (empty($title) || empty($msg)) {
                    throw new Exception('Title and message are required.');
                }
                $allowed_types = ['info', 'warning', 'success', 'error'];
                if (!in_array($type, $allowed_types)) { $type = 'info'; }
                $notificationsModel->createNotification([
                    'type' => $type,
                    'title' => $title,
                    'message' => $msg,
                    'link' => $link ?: null,
                    'is_read' => 0,
                    'created_at' => date('Y-m-d H:i:s')
                ]);
                $message = 'Notification created successfully.';
                $message_type = 'success';
                break;
        }
    } catch (Exception $e) {
        $message = $e->getMessage();
        $message_type = 'error';
    }
}

// Auto-generate system notifications
try {
    $notificationsModel->generateSystemNotifications();
} catch (Exception $e) {
    error_log("Notification auto-generation error: " . $e->getMessage());
}

// Fetch notifications
$filter = $_GET['filter'] ?? 'all';
$notifications = $notificationsModel->getNotifications(['filter' => $filter]);
$counts = $notificationsModel->getNotificationCounts();
$unread_count = $counts['unread'];

// Helper: time ago
if (!function_exists('time_ago')) {
    function time_ago(?string $datetime): string {
        if (!$datetime) return 'Unknown';
        $now = new DateTime();
        $ago = new DateTime($datetime);
        $diff = $now->diff($ago);
        if ($diff->y > 0) return $diff->y . ' year' . ($diff->y > 1 ? 's' : '') . ' ago';
        if ($diff->m > 0) return $diff->m . ' month' . ($diff->m > 1 ? 's' : '') . ' ago';
        if ($diff->d > 0) return $diff->d . ' day' . ($diff->d > 1 ? 's' : '') . ' ago';
        if ($diff->h > 0) return $diff->h . ' hour' . ($diff->h > 1 ? 's' : '') . ' ago';
        if ($diff->i > 0) return $diff->i . ' minute' . ($diff->i > 1 ? 's' : '') . ' ago';
        return 'Just now';
    }
}

$type_icons = [
    'warning' => 'fa-triangle-exclamation',
    'info' => 'fa-circle-info',
    'success' => 'fa-circle-check',
    'error' => 'fa-circle-xmark',
];

$type_colors = [
    'warning' => 'text-amber-400',
    'info' => 'text-blue-400',
    'success' => 'text-emerald-400',
    'error' => 'text-red-400',
];

$type_borders = [
    'warning' => 'border-l-amber-400',
    'info' => 'border-l-blue-400',
    'success' => 'border-l-emerald-400',
    'error' => 'border-l-red-400',
];

$current_page = 'notifications';
$page_title = 'Notifications';
$unread_count_header = $unread_count;
ob_start();
?>

<!-- Flash message -->
<?php if ($message): ?>
    <div class="mb-6 p-4 rounded-xl <?= $message_type === 'success' ? 'bg-green-500/10 border border-green-500/20 text-green-400' : 'bg-red-500/10 border-red-500/20 text-red-400' ?>">
        <i class="fa-solid <?= $message_type === 'success' ? 'fa-circle-check' : 'fa-circle-xmark' ?> mr-2"></i>
        <?= htmlspecialchars($message) ?>
    </div>
<?php endif; ?>

<!-- Top bar: stats + actions -->
<div class="flex flex-wrap items-center justify-between gap-4 mb-6">
    <div class="flex items-center gap-4">
        <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl px-4 py-3 flex items-center gap-3">
            <div class="w-9 h-9 rounded-lg bg-amber-500/15 flex items-center justify-center">
                <i class="fa-solid fa-bell text-amber-400"></i>
            </div>
            <div>
                <p class="text-xs text-slate-400">Unread</p>
                <p class="text-lg font-bold text-white"><?= $unread_count ?></p>
            </div>
        </div>
    </div>
    <div class="flex items-center gap-3">
        <?php if ($unread_count > 0): ?>
            <form method="POST" class="inline">
                <input type="hidden" name="action" value="mark_all_read">
                <button type="submit"
                    class="px-4 py-2 rounded-lg bg-slate-800/50 border border-slate-700/60 text-sm text-slate-300 hover:bg-slate-700/50 hover:text-white transition flex items-center gap-2">
                    <i class="fa-solid fa-check-double"></i>
                    Mark All Read
                </button>
            </form>
        <?php endif; ?>
        <button onclick="openModal('createModal')"
            class="px-4 py-2 rounded-lg text-sm font-semibold flex items-center gap-2 transition"
            style="background: #22c55e; color: #111;">
            <i class="fa-solid fa-plus"></i>
            Create Notification
        </button>
    </div>
</div>

<!-- Filter tabs -->
<div class="flex flex-wrap gap-2 mb-6">
    <a href="?filter=all"
        class="tab-btn px-4 py-2 rounded-lg text-sm border border-slate-700/60 text-slate-300 <?= $filter === 'all' ? 'active' : '' ?>">
        <i class="fa-solid fa-list mr-1.5"></i> All
        <span class="ml-1.5 text-xs opacity-60">(<?= $counts['all'] ?>)</span>
    </a>
    <a href="?filter=unread"
        class="tab-btn px-4 py-2 rounded-lg text-sm border border-slate-700/60 text-slate-300 <?= $filter === 'unread' ? 'active' : '' ?>">
        <i class="fa-solid fa-envelope mr-1.5"></i> Unread
        <span class="ml-1.5 text-xs opacity-60">(<?= $counts['unread'] ?>)</span>
    </a>
    <a href="?filter=subscription"
        class="tab-btn px-4 py-2 rounded-lg text-sm border border-slate-700/60 text-slate-300 <?= $filter === 'subscription' ? 'active' : '' ?>">
        <i class="fa-solid fa-credit-card mr-1.5"></i> Subscription
        <span class="ml-1.5 text-xs opacity-60">(<?= $counts['subscription'] ?>)</span>
    </a>
    <a href="?filter=system"
        class="tab-btn px-4 py-2 rounded-lg text-sm border border-slate-700/60 text-slate-300 <?= $filter === 'system' ? 'active' : '' ?>">
        <i class="fa-solid fa-gear mr-1.5"></i> System
        <span class="ml-1.5 text-xs opacity-60">(<?= $counts['system'] ?>)</span>
    </a>
</div>

<!-- Notifications list -->
<div class="bg-slate-800/40 border border-slate-700/60 rounded-xl-lg overflow-hidden">
    <?php if (empty($notifications)): ?>
        <!-- Empty state -->
        <div class="flex flex-col items-center justify-center py-20 px-6">
            <div class="w-20 h-20 rounded-full bg-slate-800/50 flex items-center justify-center mb-5">
                <i class="fa-solid fa-bell-slash text-3xl text-slate-500"></i>
            </div>
            <h3 class="text-lg font-semibold text-slate-300 mb-1">No notifications</h3>
            <p class="text-sm text-slate-500 text-center max-w-sm">
                <?php if ($filter !== 'all'): ?>
                    No <?= htmlspecialchars($filter) ?> notifications found. Try a different filter.
                <?php else: ?>
                    You're all caught up! There are no notifications to display.
                <?php endif; ?>
            </p>
        </div>
    <?php else: ?>
        <div class="divide-y divide-white/5">
            <?php foreach ($notifications as $notif): ?>
                <?php
                    $is_unread = !((int)($notif['is_read'] ?? 0));
                    $icon = $type_icons[$notif['type']] ?? 'fa-circle-info';
                    $color = $type_colors[$notif['type']] ?? 'text-blue-400';
                    $border = $type_borders[$notif['type']] ?? 'border-l-blue-400';
                ?>
                <div class="notif-item flex items-start gap-4 px-6 py-4 <?= $is_unread ? 'border-l-4 ' . $border : 'border-l-transparent' ?>">
                    <!-- Icon -->
                    <div class="mt-0.5 flex-shrink-0">
                        <i class="fa-solid <?= $icon ?> text-lg $color"></i>
                    </div>

                    <!-- Content -->
                    <div class="flex-1 min-w-0">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <h4 class="text-sm font-semibold <?= $is_unread ? 'text-white' : 'text-slate-300' ?>">
                                    <?= htmlspecialchars($notif['title'] ?? '') ?>
                                    <?php if ($is_unread): ?>
                                        <span class="inline-block w-2 h-2 rounded-full bg-green-400 ml-1.5 align-middle"></span>
                                    <?php endif; ?>
                                </h4>
                                <p class="text-sm text-slate-400 mt-0.5 leading-relaxed">
                                    <?= htmlspecialchars($notif['message'] ?? '') ?>
                                </p>
                                <div class="flex items-center gap-4 mt-2">
                                    <span class="text-xs text-slate-500">
                                        <i class="fa-regular fa-clock mr-1"></i><?= time_ago($notif['created_at'] ?? null) ?>
                                    </span>
                                    <?php if (!empty($notif['link'])): ?>
                                        <a href="<?= htmlspecialchars($notif['link']) ?>"
                                            class="text-xs text-green-400 hover:text-green-300 hover:underline">
                                            <i class="fa-solid fa-arrow-up-right-from-square mr-1"></i>View details
                                        </a>
                                    <?php endif; ?>
                                    <span class="text-xs text-slate-600 capitalize">
                                        <?= htmlspecialchars($notif['type'] ?? 'info') ?>
                                    </span>
                                </div>
                            </div>

                            <!-- Actions -->
                            <div class="flex items-center gap-1 flex-shrink-0">
                                <?php if ($is_unread): ?>
                                    <form method="POST" class="inline">
                                        <input type="hidden" name="action" value="mark_read">
                                        <input type="hidden" name="notification_id" value="<?= (int)$notif['id'] ?>">
                                        <button type="submit"
                                            class="action-btn w-8 h-8 rounded-lg flex items-center justify-center text-slate-400 hover:text-green-400"
                                            title="Mark as read">
                                            <i class="fa-solid fa-check text-xs"></i>
                                        </button>
                                    </form>
                                <?php endif; ?>
                                <form method="POST" class="inline"
                                    onsubmit="return confirm('Delete this notification?')">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="notification_id" value="<?= (int)$notif['id'] ?>">
                                    <button type="submit"
                                        class="action-btn w-8 h-8 rounded-lg flex items-center justify-center text-slate-400 hover:text-red-400"
                                        title="Delete">
                                        <i class="fa-solid fa-trash text-xs"></i>
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<!-- Create Notification Modal -->
<div id="createModal"
    class="fixed inset-0 bg-black/50 backdrop-blur-sm hidden items-center justify-center z-50">
    <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-8 w-full max-w-lg mx-4">
        <div class="flex items-center justify-between mb-6">
            <h2 class="text-lg font-bold text-white">Create Notification</h2>
            <button onclick="closeModal('createModal')" class="text-slate-400 hover:text-white transition">
                <i class="fa-solid fa-times text-lg"></i>
            </button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="create">

            <div class="space-y-4">
                <div>
                    <label class="block text-slate-500 text-xs mb-1.5">Type</label>
                    <select name="type"
                        class="w-full px-4 py-2.5 bg-slate-800/50 border border-slate-700/60 rounded-lg text-white text-sm focus:outline-none focus:border-green-500/50 transition">
                        <option value="info">Info</option>
                        <option value="warning">Warning</option>
                        <option value="success">Success</option>
                        <option value="error">Error</option>
                    </select>
                </div>
                <div>
                    <label class="block text-slate-500 text-xs mb-1.5">Title *</label>
                    <input type="text" name="title" required
                        class="w-full px-4 py-2.5 bg-slate-800/50 border border-slate-700/60 rounded-lg text-white text-sm placeholder-gray-500 focus:outline-none focus:border-green-500/50 transition"
                        placeholder="Notification title">
                </div>
                <div>
                    <label class="block text-slate-500 text-xs mb-1.5">Message *</label>
                    <textarea name="message" rows="3" required
                        class="w-full px-4 py-2.5 bg-slate-800/50 border border-slate-700/60 rounded-lg text-white text-sm placeholder-gray-500 focus:outline-none focus:border-green-500/50 transition resize-none"
                        placeholder="Notification message..."></textarea>
                </div>
                <div>
                    <label class="block text-slate-500 text-xs mb-1.5">Link (optional)</label>
                    <input type="text" name="link"
                        class="w-full px-4 py-2.5 bg-slate-800/50 border border-slate-700/60 rounded-lg text-white text-sm placeholder-gray-500 focus:outline-none focus:border-green-500/50 transition"
                        placeholder="https:// or relative path">
                </div>
            </div>

            <div class="flex justify-end gap-3 mt-6">
                <button type="button" onclick="closeModal('createModal')"
                    class="px-5 py-2.5 rounded-lg bg-slate-800/50 border border-slate-700/60 text-sm text-slate-300 hover:bg-slate-700/50 transition">
                    Cancel
                </button>
                <button type="submit"
                    class="px-5 py-2.5 rounded-lg text-sm font-semibold transition"
                    style="background: #22c55e; color: #111;">
                    <i class="fa-solid fa-paper-plane mr-1.5"></i>Create
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    function openModal(id) {
        const el = document.getElementById(id);
        el.classList.remove('hidden');
        el.classList.add('flex');
    }

    function closeModal(id) {
        const el = document.getElementById(id);
        el.classList.add('hidden');
        el.classList.remove('flex');
    }

    // Close modal on backdrop click
    document.querySelectorAll('[id$="Modal"]').forEach(function (modal) {
        modal.addEventListener('click', function (e) {
            if (e.target === this) {
                closeModal(this.id);
            }
        });
    });

    // Auto-dismiss flash messages
    setTimeout(function () {
        const flash = document.querySelector('.mb-6.p-4.rounded-xl');
        if (flash) {
            flash.style.transition = 'opacity 0.3s';
            flash.style.opacity = '0';
            setTimeout(function () { flash.remove(); }, 300);
        }
    }, 5000);
</script>

<style>
    .bg-slate-800/40 border border-slate-700/60 rounded-xl {
        background: rgba(17, 24, 39, 0.6);
        backdrop-filter: blur(24px);
        -webkit-backdrop-filter: blur(24px);
        border: 1px solid rgba(255, 255, 255, 0.1);
        border-radius: 1rem;
    }
    .bg-slate-800/40 border border-slate-700/60 rounded-xl-lg {
        background: rgba(17, 24, 39, 0.6);
        backdrop-filter: blur(24px);
        -webkit-backdrop-filter: blur(24px);
        border: 1px solid rgba(255, 255, 255, 0.1);
        border-radius: 1rem;
    }
    .notif-item {
        transition: background 0.15s ease;
    }
    .notif-item:hover {
        background: rgba(255, 255, 255, 0.03);
    }
    .tab-btn {
        transition: all 0.2s ease;
    }
    .tab-btn:hover {
        background: rgba(255, 255, 255, 0.08);
    }
    .tab-btn.active {
        background: rgba(34, 197, 94, 0.15);
        color: #22c55e;
        border-color: rgba(34, 197, 94, 0.4);
    }
    .action-btn {
        transition: all 0.15s ease;
    }
    .action-btn:hover {
        background: rgba(255, 255, 255, 0.12);
    }
</style>

<?php
$page_content = ob_get_clean();
require_once __DIR__ . '/layouts/super_admin.php';
?>
