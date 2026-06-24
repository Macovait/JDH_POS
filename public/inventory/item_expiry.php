<?php
/**
 * Item Expiry Tracking - Products Expiry Management
 * Laravel-style layout with Tailwind CSS
 */

require_once __DIR__ . '/../../src/paths.php';
safe_require('auth.php', 'src', true);
safe_require('db.php', 'src', true);
safe_require('functions.php', 'src', true);
require_login();

$pdo = get_db_connection();
$tenant_id = get_current_tenant_id();
$branch_id = get_current_branch_id();

ensureExpiryTables($pdo);

$alert_days = 30;
$filter  = $_GET['filter']  ?? 'all';
$search  = trim($_GET['search'] ?? '');

// Handle GET messages
$message = $_GET['success'] ?? ($_GET['error'] ?? '');
$message_type = !empty($_GET['success']) ? 'success' : (!empty($_GET['error']) ? 'error' : '');

$stats = [
    'total_tracked' => 0,
    'expired' => 0,
    'expiring_soon' => 0,
    'ok' => 0
];

$expiry_items = [];

try {
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM product_expiry
        WHERE tenant_id = ? AND (branch_id = ? OR branch_id IS NULL)
    ");
    $stmt->execute([$tenant_id, $branch_id]);
    $stats['total_tracked'] = $stmt->fetchColumn();

    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM product_expiry
        WHERE tenant_id = ? AND (branch_id = ? OR branch_id IS NULL) AND expiry_date < CURDATE()
    ");
    $stmt->execute([$tenant_id, $branch_id]);
    $stats['expired'] = $stmt->fetchColumn();

    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM product_expiry
        WHERE tenant_id = ? AND (branch_id = ? OR branch_id IS NULL)
        AND expiry_date >= CURDATE() AND expiry_date <= DATE_ADD(CURDATE(), INTERVAL ? DAY)
    ");
    $stmt->execute([$tenant_id, $branch_id, $alert_days]);
    $stats['expiring_soon'] = $stmt->fetchColumn();

    $stats['ok'] = $stats['total_tracked'] - $stats['expired'] - $stats['expiring_soon'];

    $sql = "
        SELECT pe.*, p.name as product_name, p.sku, p.barcode
        FROM product_expiry pe
        LEFT JOIN products p ON pe.product_id = p.id
        WHERE pe.tenant_id = ? AND (pe.branch_id = ? OR pe.branch_id IS NULL)
    ";
    $params = [$tenant_id, $branch_id];

    if ($filter === 'expired') {
        $sql .= " AND pe.expiry_date < CURDATE()";
    } elseif ($filter === 'expiring') {
        $sql .= " AND pe.expiry_date >= CURDATE() AND pe.expiry_date <= DATE_ADD(CURDATE(), INTERVAL ? DAY)";
        $params[] = $alert_days;
    } elseif ($filter === 'ok') {
        $sql .= " AND pe.expiry_date > DATE_ADD(CURDATE(), INTERVAL ? DAY)";
        $params[] = $alert_days;
    } elseif ($filter === 'batch') {
        $sql .= " AND pe.batch_number IS NOT NULL";
    }

    if ($search !== '') {
        $sql .= " AND (p.name LIKE ? OR p.sku LIKE ? OR pe.batch_number LIKE ?)";
        $params[] = '%' . $search . '%';
        $params[] = '%' . $search . '%';
        $params[] = '%' . $search . '%';
    }

    $sql .= " ORDER BY pe.expiry_date ASC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $expiry_items = $stmt->fetchAll();

} catch (PDOException $e) {
    error_log("Error fetching expiry items: " . $e->getMessage());
}

$page_title = 'Item Expiry Tracking';
ob_start();
?>

<div class="fade-in">
<!-- Toolbar -->
<div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-5">
    <div>
        <p class="text-xs font-medium text-amber-400 uppercase tracking-wide mb-0.5">Inventory</p>
        <h1 class="text-lg font-bold text-white">Item Expiry Tracking</h1>
        <p class="text-sm text-slate-500 mt-0.5">
            <?php echo number_format(count($expiry_items)); ?> entr<?php echo count($expiry_items) !== 1 ? 'ies' : 'y'; ?> found
        </p>
    </div>
    <div class="flex items-center gap-2 shrink-0">
        <a href="expiry_form.php"
           class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-amber-500 text-black text-sm font-semibold hover:bg-amber-600 transition-colors">
            <i class="fas fa-plus text-xs"></i> Add Expiry Entry
        </a>
    </div>
</div>

<?php if ($message): ?>
<div class="mb-4 flex items-center gap-2 px-3 py-2 rounded-lg <?php echo $message_type === 'success' ? 'bg-emerald-500/10 border border-emerald-500/30 text-emerald-400' : 'bg-red-500/10 border border-red-500/30 text-red-400'; ?> text-sm toast-slide">
    <i class="fas <?php echo $message_type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'; ?>"></i>
    <?php echo htmlspecialchars($message); ?>
</div>
<?php endif; ?>

<!-- Summary cards -->
<div class="grid grid-cols-2 sm:grid-cols-4 gap-2 mb-5">
    <?php
    $stat_cards = [
        ['label' => 'Total Tracked',  'value' => $stats['total_tracked'],  'icon' => 'fa-boxes',              'color' => 'text-blue-400',    'bg' => 'bg-blue-500/10'],
        ['label' => 'Expired',        'value' => $stats['expired'],        'icon' => 'fa-calendar-times',     'color' => 'text-red-400',     'bg' => 'bg-red-500/10'],
        ['label' => 'Expiring Soon',  'value' => $stats['expiring_soon'],  'icon' => 'fa-exclamation-triangle','color' => 'text-amber-400',   'bg' => 'bg-amber-500/10'],
        ['label' => 'OK',             'value' => $stats['ok'],             'icon' => 'fa-check-circle',       'color' => 'text-emerald-400', 'bg' => 'bg-emerald-500/10'],
    ];
    foreach ($stat_cards as $card):
    ?>
    <div class="bg-slate-800/50 border border-slate-700/60 rounded-xl p-3 flex items-center gap-2.5">
        <div class="w-8 h-8 rounded-lg <?php echo $card['bg']; ?> flex items-center justify-center shrink-0">
            <i class="fas <?php echo $card['icon']; ?> <?php echo $card['color']; ?> text-xs"></i>
        </div>
        <div class="min-w-0">
            <div class="text-sm font-bold <?php echo $card['color']; ?>"><?php echo number_format($card['value']); ?></div>
            <div class="text-xs text-slate-500 leading-none mt-0.5"><?php echo $card['label']; ?></div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- Filter bar -->
<div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-3 mb-4">
    <form method="GET" class="flex flex-col sm:flex-row gap-2">
        <div class="flex-1">
            <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>"
                   placeholder="Search product, SKU or batch..."
                   class="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-1 focus:ring-amber-500">
        </div>
        <div class="flex flex-wrap gap-1.5">
            <?php
            $pills = [
                'all'      => ['label' => 'All',          'count' => $stats['total_tracked'], 'on' => 'bg-amber-500 text-black font-semibold',                  'off' => 'bg-slate-700 border border-slate-600 text-slate-300 hover:bg-slate-600'],
                'expired'  => ['label' => 'Expired',      'count' => $stats['expired'],       'on' => 'bg-red-500 text-white font-semibold',                    'off' => 'bg-slate-700 border border-slate-600 text-slate-300 hover:bg-slate-600'],
                'expiring' => ['label' => 'Expiring Soon','count' => $stats['expiring_soon'], 'on' => 'bg-amber-500 text-black font-semibold',                  'off' => 'bg-slate-700 border border-slate-600 text-slate-300 hover:bg-slate-600'],
                'ok'       => ['label' => 'OK',           'count' => $stats['ok'],            'on' => 'bg-emerald-500 text-black font-semibold',                'off' => 'bg-slate-700 border border-slate-600 text-slate-300 hover:bg-slate-600'],
                'batch'    => ['label' => 'By Batch',     'count' => null,                    'on' => 'bg-blue-500 text-white font-semibold',                   'off' => 'bg-slate-700 border border-slate-600 text-slate-300 hover:bg-slate-600'],
            ];
            foreach ($pills as $val => $pill):
                $active = $filter === $val;
            ?>
            <button type="submit" name="filter" value="<?php echo $val; ?>"
               class="inline-flex items-center gap-1 px-3 py-2 rounded-lg text-xs transition-colors <?php echo $active ? $pill['on'] : $pill['off']; ?>">
                <?php echo $pill['label']; ?>
                <?php if ($pill['count'] !== null): ?>
                <span class="opacity-70">(<?php echo (int)$pill['count']; ?>)</span>
                <?php endif; ?>
            </button>
            <?php endforeach; ?>
            <?php if ($search): ?>
            <a href="?filter=<?php echo htmlspecialchars($filter); ?>" class="inline-flex items-center justify-center px-3 py-2 rounded-lg bg-slate-700 border border-slate-600 text-slate-400 text-xs hover:bg-slate-600 transition-colors">
                <i class="fas fa-times text-[10px]"></i>
            </a>
            <?php endif; ?>
        </div>
    </form>
</div>

<!-- Expiry table -->
<div class="bg-slate-800/40 border border-slate-700/60 rounded-xl overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full min-w-[700px]">
            <thead>
                <tr class="border-b border-slate-700/60 bg-slate-800/60">
                    <th class="px-3 py-2.5 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Product</th>
                    <th class="px-3 py-2.5 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">SKU / Barcode</th>
                    <th class="px-3 py-2.5 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Batch</th>
                    <th class="px-3 py-2.5 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Qty</th>
                    <th class="px-3 py-2.5 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Expiry Date</th>
                    <th class="px-3 py-2.5 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Days Left</th>
                    <th class="px-3 py-2.5 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Status</th>
                    <th class="px-3 py-2.5 text-center text-xs font-semibold text-slate-500 uppercase tracking-wider">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-700/40">
            <?php if (empty($expiry_items)): ?>
                <tr>
                    <td colspan="8" class="px-4 py-14 text-center">
                        <i class="fas fa-calendar-times text-4xl text-slate-700 block mb-3"></i>
                        <p class="text-slate-500 text-sm">No expiry items found</p>
                        <?php if ($filter !== 'all'): ?>
                        <a href="?filter=all" class="mt-2 inline-flex items-center gap-1 text-xs text-amber-400 hover:text-amber-300 transition-colors">
                            <i class="fas fa-times text-xs"></i> Clear filter
                        </a>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($expiry_items as $item):
                    $expiry    = strtotime($item['expiry_date']);
                    $today     = time();
                    $days_left = ceil(($expiry - $today) / (60 * 60 * 24));
                    if ($days_left < 0)       $status = 'expired';
                    elseif ($days_left <= 30) $status = 'expiring';
                    else                      $status = 'ok';
                    $status_tw = [
                        'expired'  => 'bg-red-500/15 text-red-400 ring-1 ring-red-500/30',
                        'expiring' => 'bg-amber-500/15 text-amber-400 ring-1 ring-amber-500/30',
                        'ok'       => 'bg-emerald-500/15 text-emerald-400 ring-1 ring-emerald-500/30',
                    ];
                    $status_labels = ['expired' => 'Expired', 'expiring' => 'Expiring', 'ok' => 'OK'];
                ?>
                <tr class="hover:bg-slate-700/30 transition-colors group">
                    <td class="px-3 py-2.5">
                        <span class="text-sm font-medium text-white"><?php echo htmlspecialchars($item['product_name'] ?? 'Unknown Product'); ?></span>
                    </td>
                    <td class="px-3 py-2.5">
                        <div class="text-sm text-slate-300"><?php echo htmlspecialchars($item['sku'] ?? '—'); ?></div>
                        <?php if (!empty($item['barcode'])): ?>
                        <div class="text-xs text-slate-500"><?php echo htmlspecialchars($item['barcode']); ?></div>
                        <?php endif; ?>
                    </td>
                    <td class="px-3 py-2.5 text-sm text-slate-400"><?php echo htmlspecialchars($item['batch_number'] ?? '—'); ?></td>
                    <td class="px-3 py-2.5 text-sm text-white"><?php echo number_format($item['quantity']); ?></td>
                    <td class="px-3 py-2.5">
                        <div class="text-sm text-slate-300"><?php echo date('d M Y', strtotime($item['expiry_date'])); ?></div>
                    </td>
                    <td class="px-3 py-2.5 text-sm">
                        <?php if ($status === 'expired'): ?>
                            <span class="text-red-400 font-semibold">Expired</span>
                        <?php elseif ($days_left == 0): ?>
                            <span class="text-red-400 font-semibold">Today</span>
                        <?php else: ?>
                            <span class="text-slate-300"><?php echo $days_left; ?> days</span>
                        <?php endif; ?>
                    </td>
                    <td class="px-3 py-2.5">
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium <?php echo $status_tw[$status]; ?>">
                            <?php echo $status_labels[$status]; ?>
                        </span>
                    </td>
                    <td class="px-3 py-2.5">
                        <div class="flex items-center justify-center gap-2">
                            <a href="expiry_form.php?id=<?php echo $item['id']; ?>"
                               class="w-6 h-6 flex items-center justify-center rounded-lg bg-slate-700/60 text-amber-400 hover:bg-amber-500/20 hover:text-amber-300 transition-colors" title="Edit">
                                <i class="fas fa-edit text-xs"></i>
                            </a>
                            <form method="POST" action="save_expiry.php" class="inline" onsubmit="return confirm('Delete this expiry entry?');">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?php echo $item['id']; ?>">
                                <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                                <button type="submit"
                                        class="w-6 h-6 flex items-center justify-center rounded-lg bg-slate-700/60 text-slate-400 hover:bg-red-500/20 hover:text-red-400 transition-colors" title="Delete">
                                    <i class="fas fa-trash text-xs"></i>
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const msg = document.querySelector('.toast-slide');
        if (msg) {
            setTimeout(() => {
                msg.style.transition = 'opacity 0.5s ease';
                msg.style.opacity = '0';
                setTimeout(() => msg.remove(), 500);
            }, 5000);
        }
    });
    document.addEventListener('keydown', function (e) {
        if (e.target.matches('input, textarea, select')) return;
        if (e.altKey && e.key === 'a') { e.preventDefault(); window.location.href = 'expiry_form.php'; }
    });
</script>
</div><!-- /.fade-in -->

<?php
$page_content = ob_get_clean();
require_once __DIR__ . '/../layouts/app.php';

function ensureExpiryTables($pdo) {
    try {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS product_expiry (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                tenant_id INT UNSIGNED NOT NULL,
                branch_id INT UNSIGNED DEFAULT 1,
                product_id INT UNSIGNED NOT NULL,
                batch_number VARCHAR(100),
                quantity INT DEFAULT 1,
                expiry_date DATE NOT NULL,
                notes TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NULL,
                INDEX idx_company (tenant_id),
                INDEX idx_product (product_id),
                INDEX idx_expiry (expiry_date),
                INDEX idx_batch (batch_number)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    } catch (PDOException $e) {
        error_log("Expiry table error: " . $e->getMessage());
    }
}
?>