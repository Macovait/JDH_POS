<?php
/**
 * Kitchen Display System (KDS)
 * Restaurant / Cafe / Bakery vertical
 * Shows live KOTs (Kitchen Order Tickets) with status flow:
 *   pending -> preparing -> ready -> served (or cancelled)
 */

$page_title = 'Kitchen Display';
ob_start();

require_once __DIR__ . '/../../src/auth.php';
require_login();

$tenant_id = get_current_tenant_id();
$branch_id = get_current_branch_id();
$user_id   = get_current_user_id();

$pdo = get_db_connection();

// Gate: KDS is only for restaurant / cafe / bakery verticals
$kds_allowed = ['restaurant', 'cafe', 'bakery'];
$current_bt  = function_exists('get_current_business_type')
    ? get_current_business_type($tenant_id)
    : ($_SESSION['business_type'] ?? 'retail');

if (!in_array($current_bt, $kds_allowed, true)) {
    http_response_code(403);
    ?><!DOCTYPE html>
    <html lang="en">
    <head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>KDS Not Available</title>
    <style>body{font-family:sans-serif;display:flex;align-items:center;justify-content:center;height:100vh;margin:0;background:#0f172a;color:#fff;text-align:center;}
    .box{max-width:400px;padding:2rem;}
    h1{color:#f59e0b;margin-bottom:.5rem;}p{color:#94a3b8;}a{color:#38bdf8;}</style></head>
    <body><div class="box"><h1>Kitchen Display Not Available</h1>
    <p>This feature is only available for <strong>Restaurant, Cafe, and Bakery</strong> business types.</p>
    <p>Current type: <code><?php echo htmlspecialchars($current_bt); ?></code></p>
    <p><a href="pos.php">Back to POS</a></p></div></body></html>
    <?php exit;
}

// Ensure tables exist (no-op if already there)
try {
    $pdo->query("SELECT 1 FROM kitchen_orders LIMIT 1");
} catch (Exception $e) {
    $pdo->exec("CREATE TABLE IF NOT EXISTS `kitchen_orders` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `tenant_id` bigint(20) unsigned NULL,
      `branch_id` int(11) NOT NULL,
      `sale_id` int(11) NOT NULL,
      `table_number` varchar(20) DEFAULT NULL,
      `order_type` varchar(30) NOT NULL DEFAULT 'walkin',
      `status` enum('pending','preparing','ready','served','cancelled') NOT NULL DEFAULT 'pending',
      `priority` int(11) NOT NULL DEFAULT 0,
      `notes` text DEFAULT NULL,
      `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
      `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
      PRIMARY KEY (`id`),
      KEY `idx_ko_branch` (`branch_id`),
      KEY `idx_ko_sale` (`sale_id`),
      KEY `idx_ko_status` (`status`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
    $pdo->exec("CREATE TABLE IF NOT EXISTS `kitchen_order_items` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `tenant_id` bigint(20) unsigned NULL,
      `kitchen_order_id` int(11) NOT NULL,
      `sale_item_id` int(11) NOT NULL,
      `product_name` varchar(150) NOT NULL,
      `quantity` int(11) NOT NULL DEFAULT 1,
      `modifiers` text DEFAULT NULL,
      `notes` text DEFAULT NULL,
      `status` enum('pending','preparing','ready','served','cancelled') NOT NULL DEFAULT 'pending',
      `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
      PRIMARY KEY (`id`),
      KEY `idx_koi_order` (`kitchen_order_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
}

$has_tenant_col = false;
try {
    $pdo->query("SELECT tenant_id FROM kitchen_orders LIMIT 1");
    $has_tenant_col = true;
} catch (Exception $e) {}

$status_filter = $_GET['status'] ?? 'active';
$status_clause = '';
$params = [];

$where = " WHERE ko.branch_id = ? ";
$params[] = $branch_id;
if ($has_tenant_col) {
    $where .= " AND (ko.tenant_id = ? OR ko.tenant_id IS NULL) ";
    $params[] = $tenant_id;
}

if ($status_filter === 'active') {
    $where .= " AND ko.status IN ('pending','preparing','ready') ";
} elseif (in_array($status_filter, ['pending', 'preparing', 'ready', 'served', 'cancelled'], true)) {
    $where .= " AND ko.status = ? ";
    $params[] = $status_filter;
}

// Counts
$stats = ['pending' => 0, 'preparing' => 0, 'ready' => 0, 'served_today' => 0];
try {
    $sql = "SELECT status, COUNT(*) c FROM kitchen_orders ko WHERE ko.branch_id = ?";
    $cp = [$branch_id];
    if ($has_tenant_col) { $sql .= " AND (ko.tenant_id = ? OR ko.tenant_id IS NULL)"; $cp[] = $tenant_id; }
    $sql .= " AND DATE(ko.created_at) = CURDATE() GROUP BY status";
    $stmt = $pdo->prepare($sql); $stmt->execute($cp);
    while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
        if (isset($stats[$r['status']])) $stats[$r['status']] = (int)$r['c'];
        if ($r['status'] === 'served') $stats['served_today'] = (int)$r['c'];
    }
} catch (Exception $e) {}

// Fetch orders
$orders = [];
try {
    $sql = "SELECT ko.id, ko.sale_id, ko.table_number, ko.order_type, ko.status,
                   ko.priority, ko.notes, ko.created_at, ko.updated_at,
                   s.invoice_number,
                   TIMESTAMPDIFF(MINUTE, ko.created_at, NOW()) AS minutes_old
            FROM kitchen_orders ko
            LEFT JOIN sales s ON s.id = ko.sale_id
            $where
            ORDER BY
              CASE ko.status
                WHEN 'pending' THEN 1
                WHEN 'preparing' THEN 2
                WHEN 'ready' THEN 3
                WHEN 'served' THEN 4
                ELSE 5 END,
              ko.priority DESC, ko.created_at ASC
            LIMIT 80";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if ($orders) {
        $ids = array_map(fn($o) => (int)$o['id'], $orders);
        $ph = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $pdo->prepare("SELECT kitchen_order_id, product_name, quantity, notes, status
                               FROM kitchen_order_items WHERE kitchen_order_id IN ($ph) ORDER BY id ASC");
        $stmt->execute($ids);
        $items_by_order = [];
        while ($it = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $items_by_order[(int)$it['kitchen_order_id']][] = $it;
        }
        foreach ($orders as &$o) {
            $o['items'] = $items_by_order[(int)$o['id']] ?? [];
        }
        unset($o);
    }
} catch (Exception $e) {
    $orders = [];
    $error = $e->getMessage();
}

$status_colors = [
    'pending'    => ['bg' => 'bg-amber-500/10',  'border' => 'border-amber-500/40',  'text' => 'text-amber-400',  'label' => 'Pending'],
    'preparing'  => ['bg' => 'bg-blue-500/10',   'border' => 'border-blue-500/40',   'text' => 'text-blue-400',   'label' => 'Preparing'],
    'ready'      => ['bg' => 'bg-emerald-500/10','border' => 'border-emerald-500/40','text' => 'text-emerald-400','label' => 'Ready'],
    'served'     => ['bg' => 'bg-gray-500/10',   'border' => 'border-gray-500/40',   'text' => 'text-gray-400',   'label' => 'Served'],
    'cancelled'  => ['bg' => 'bg-red-500/10',    'border' => 'border-red-500/40',    'text' => 'text-red-400',    'label' => 'Cancelled'],
];
?>

<div class="fade-in" id="kitchen-content">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-5">
        <div>
            <div class="text-xs text-amber-400/70 uppercase tracking-wider font-semibold mb-0.5">KDS</div>
            <h1 class="text-lg font-bold text-white"><i class="fas fa-utensils text-[#FBBF24]"></i> Kitchen Display</h1>
            <p class="text-sm text-slate-500 mt-0.5 mt-1">Live order tickets — auto-refreshes every 15s</p>
        </div>
        <div class="flex items-center gap-2 shrink-0">
            <label class="flex items-center gap-2 text-sm text-gray-300">
                <input type="checkbox" id="autoRefreshToggle" checked class="rounded bg-gray-700 border-gray-600">
                Auto refresh
            </label>
            <button onclick="window.location.reload()" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-slate-800 border border-slate-700 text-slate-300 text-sm font-medium hover:bg-slate-700 transition-colors"><i class="fas fa-sync"></i> Refresh</button>
        </div>
    </div>

    <!-- Stats -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-5">
        <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl overflow-hidden">
            <div class="text-xs text-amber-400/70 uppercase tracking-wider font-semibold mb-0.5 text-[#9CA3AF]">Pending</div>
            <div class="text-lg font-bold text-white text-2xl text-[#FBBF24]"><?php echo $stats['pending']; ?></div>
        </div>
        <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl overflow-hidden">
            <div class="text-xs text-amber-400/70 uppercase tracking-wider font-semibold mb-0.5 text-[#9CA3AF]">Preparing</div>
            <div class="text-lg font-bold text-white text-2xl text-[#3B82F6]"><?php echo $stats['preparing']; ?></div>
        </div>
        <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl overflow-hidden">
            <div class="text-xs text-amber-400/70 uppercase tracking-wider font-semibold mb-0.5 text-[#9CA3AF]">Ready</div>
            <div class="text-lg font-bold text-white text-2xl text-[#10B981]"><?php echo $stats['ready']; ?></div>
        </div>
        <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl overflow-hidden">
            <div class="text-xs text-amber-400/70 uppercase tracking-wider font-semibold mb-0.5 text-[#9CA3AF]">Served</div>
            <div class="text-lg font-bold text-white text-2xl text-[#D1D5DB]"><?php echo $stats['served_today']; ?></div>
        </div>
    </div>

    <!-- Filters -->
    <div class="flex flex-wrap gap-1 mb-4">
        <?php
        $tabs = [
            'active'    => 'Active',
            'pending'   => 'Pending',
            'preparing' => 'Preparing',
            'ready'     => 'Ready',
            'served'    => 'Served',
            'cancelled' => 'Cancelled',
        ];
        foreach ($tabs as $key => $label):
            $active = $status_filter === $key; ?>
            <a href="?status=<?php echo $key; ?>"
               class="px-3 py-1.5 rounded-md text-sm transition-colors <?php echo $active ? 'bg-yellow-400 text-gray-900 font-medium' : 'bg-gray-700 hover:bg-gray-600 text-gray-300'; ?>">
                <?php echo $label; ?>
            </a>
        <?php endforeach; ?>
    </div>

    <?php if (empty($orders)): ?>
        <div class="bg-gray-800 border border-gray-700 rounded-lg p-10 text-center text-gray-400">
            <i class="fas fa-clipboard-list text-4xl text-gray-600 mb-3"></i>
            <p>No kitchen orders<?php echo $status_filter !== 'active' ? ' for this status' : ''; ?>.</p>
        </div>
    <?php else: ?>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4" id="kotGrid">
            <?php foreach ($orders as $o): $sc = $status_colors[$o['status']] ?? $status_colors['pending']; ?>
            <div class="rounded-lg border <?php echo $sc['border']; ?> <?php echo $sc['bg']; ?> p-4 flex flex-col" data-id="<?php echo (int)$o['id']; ?>">
                <div class="flex items-center justify-between mb-2">
                    <div>
                        <div class="text-xs text-gray-400">#<?php echo (int)$o['id']; ?> · <?php echo htmlspecialchars($o['invoice_number'] ?? 'Sale '.$o['sale_id']); ?></div>
                        <div class="text-base font-semibold text-white">
                            <?php if (!empty($o['table_number'])): ?>
                                <i class="fas fa-chair text-gray-400"></i> Table <?php echo htmlspecialchars($o['table_number']); ?>
                            <?php else: ?>
                                <i class="fas fa-bag-shopping text-gray-400"></i> <?php echo htmlspecialchars(ucfirst($o['order_type'] ?: 'Order')); ?>
                            <?php endif; ?>
                        </div>
                    </div>
                    <span class="text-xs px-2 py-0.5 rounded <?php echo $sc['text']; ?> bg-gray-900/40 border <?php echo $sc['border']; ?>">
                        <?php echo $sc['label']; ?>
                    </span>
                </div>

                <div class="text-xs text-gray-400 mb-2">
                    <i class="far fa-clock"></i>
                    <?php $m = (int)$o['minutes_old']; ?>
                    <?php if ($m < 1): ?>just now<?php elseif ($m < 60): ?><?php echo $m; ?> min ago<?php else: ?><?php echo intdiv($m, 60); ?>h <?php echo $m % 60; ?>m ago<?php endif; ?>
                    <?php if ($m >= 15 && in_array($o['status'], ['pending','preparing'])): ?>
                        <span class="ml-2 text-red-400 font-medium"><i class="fas fa-exclamation-triangle"></i> Overdue</span>
                    <?php endif; ?>
                </div>

                <div class="flex-1 space-y-1.5 mb-3">
                    <?php foreach (($o['items'] ?? []) as $it): ?>
                    <div class="flex justify-between text-sm bg-gray-900/40 rounded px-2 py-1">
                        <span class="text-gray-200"><?php echo htmlspecialchars($it['product_name']); ?></span>
                        <span class="text-gray-400 font-mono">×<?php echo (int)$it['quantity']; ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>

                <?php if (!empty($o['notes'])): ?>
                <div class="text-xs text-amber-300 bg-amber-500/10 border border-amber-500/30 rounded px-2 py-1 mb-3">
                    <i class="fas fa-sticky-note"></i> <?php echo htmlspecialchars($o['notes']); ?>
                </div>
                <?php endif; ?>

                <div class="flex gap-2">
                    <?php if ($o['status'] === 'pending'): ?>
                        <button onclick="updateKOT(<?php echo (int)$o['id']; ?>, 'preparing')" class="flex-1 px-3 py-1.5 bg-blue-500 hover:bg-blue-600 text-white rounded text-sm font-medium">Start</button>
                    <?php elseif ($o['status'] === 'preparing'): ?>
                        <button onclick="updateKOT(<?php echo (int)$o['id']; ?>, 'ready')" class="flex-1 px-3 py-1.5 bg-emerald-500 hover:bg-emerald-600 text-white rounded text-sm font-medium">Mark Ready</button>
                    <?php elseif ($o['status'] === 'ready'): ?>
                        <button onclick="updateKOT(<?php echo (int)$o['id']; ?>, 'served')" class="flex-1 px-3 py-1.5 bg-gray-600 hover:bg-gray-700 text-white rounded text-sm font-medium">Mark Served</button>
                    <?php endif; ?>
                    <?php if (in_array($o['status'], ['pending','preparing','ready'])): ?>
                        <button onclick="updateKOT(<?php echo (int)$o['id']; ?>, 'cancelled')" class="px-3 py-1.5 bg-red-500/20 hover:bg-red-500/30 text-red-400 rounded text-sm">Cancel</button>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<script>
const CSRF = '<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? ''); ?>';
const PAGE_LOADED_AT = '<?php echo date('Y-m-d H:i:s'); ?>';

function updateKOT(id, status) {
    fetch('../ajax/update_kitchen_order.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF },
        body: JSON.stringify({ id, status, csrf_token: CSRF })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) { window.location.reload(); }
        else { alert(data.error || 'Update failed'); }
    })
    .catch(err => alert('Network error: ' + err.message));
}

// ===== Live pulse: poll every 5s for new orders, audible alert + auto-refresh =====
let _audioCtx = null;
function _ensureAudio() {
    if (!_audioCtx) {
        try { _audioCtx = new (window.AudioContext || window.webkitAudioContext)(); }
        catch (e) { _audioCtx = null; }
    }
    return _audioCtx;
}

function playKitchenChime() {
    const ctx = _ensureAudio();
    if (!ctx) return;
    // Two-tone chime
    [880, 1320].forEach((freq, i) => {
        const osc = ctx.createOscillator();
        const gain = ctx.createGain();
        osc.type = 'sine';
        osc.frequency.value = freq;
        gain.gain.setValueAtTime(0.0001, ctx.currentTime + i * 0.18);
        gain.gain.exponentialRampToValueAtTime(0.25, ctx.currentTime + i * 0.18 + 0.02);
        gain.gain.exponentialRampToValueAtTime(0.0001, ctx.currentTime + i * 0.18 + 0.25);
        osc.connect(gain).connect(ctx.destination);
        osc.start(ctx.currentTime + i * 0.18);
        osc.stop(ctx.currentTime + i * 0.18 + 0.3);
    });
}

let _lastSeen = PAGE_LOADED_AT;
let _seenIds = new Set();
let _pendingNew = 0;

function _flashTitle() {
    const orig = document.title.replace(/^\(\d+\)\s*/, '');
    if (_pendingNew > 0) {
        document.title = '(' + _pendingNew + ') ' + orig;
    } else {
        document.title = orig;
    }
}

function pollKitchenPulse() {
    if (!document.getElementById('autoRefreshToggle')?.checked) return;
    fetch('../ajax/kitchen_pulse.php?since=' + encodeURIComponent(_lastSeen))
        .then(r => r.json())
        .then(data => {
            if (!data || !data.success) return;
            if (data.server_time) _lastSeen = data.server_time;

            const newOnes = (data.new_orders || []).filter(o => !_seenIds.has(String(o.id)));
            newOnes.forEach(o => _seenIds.add(String(o.id)));

            if (newOnes.length > 0) {
                _pendingNew += newOnes.length;
                _flashTitle();
                playKitchenChime();
                // Toast
                const t = document.createElement('div');
                t.className = 'fixed bottom-6 right-6 z-50 bg-amber-500 text-gray-900 px-4 py-3 rounded-lg shadow-lg font-medium';
                t.innerHTML = '<i class="fas fa-bell mr-2"></i>' + newOnes.length + ' new kitchen order' + (newOnes.length > 1 ? 's' : '');
                t.style.cursor = 'pointer';
                t.onclick = () => window.location.reload();
                document.body.appendChild(t);
                setTimeout(() => t.remove(), 6000);
            }
        })
        .catch(() => {});
}

// Reset notification counter when user focuses tab
document.addEventListener('visibilitychange', () => {
    if (!document.hidden) { _pendingNew = 0; _flashTitle(); }
});

// Pulse every 5s
setInterval(pollKitchenPulse, 5000);

// Full reload every 60s as a safety net
setInterval(() => {
    if (document.getElementById('autoRefreshToggle')?.checked) {
        window.location.reload();
    }
}, 60000);
</script>

<?php
$page_content = ob_get_clean();
require_once __DIR__ . '/../layouts/app.php';
require_once __DIR__ . '/../layouts/app_close.php';
