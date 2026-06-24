<?php
/**
 * Prescriptions Log
 * Pharmacy vertical — lists prescription-tagged sales with filtering and detail view.
 */

$page_title = 'Prescriptions';
ob_start();

require_once __DIR__ . '/../../src/auth.php';
require_login();

$tenant_id = get_current_tenant_id();
$branch_id = get_current_branch_id();
$pdo = get_db_connection();

// Auto-create table if missing (no-op when present)
$pdo->exec("CREATE TABLE IF NOT EXISTS `prescriptions` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `tenant_id` BIGINT(20) UNSIGNED NULL,
    `branch_id` INT(11) NOT NULL,
    `sale_id` INT(11) NOT NULL,
    `customer_id` INT(11) NULL,
    `prescription_ref` VARCHAR(100) NULL,
    `doctor_name` VARCHAR(150) NULL,
    `notes` TEXT NULL,
    `status` ENUM('pending','dispensed','cancelled') NOT NULL DEFAULT 'dispensed',
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_rx_tenant` (`tenant_id`),
    KEY `idx_rx_branch` (`branch_id`),
    KEY `idx_rx_sale` (`sale_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

$search = trim($_GET['s'] ?? '');
$status_filter = $_GET['status'] ?? '';
$from = $_GET['from'] ?? date('Y-m-d', strtotime('-30 days'));
$to   = $_GET['to']   ?? date('Y-m-d');

$where = " WHERE p.branch_id = ? AND (p.tenant_id = ? OR p.tenant_id IS NULL) ";
$params = [$branch_id, $tenant_id];

if ($search !== '') {
    $where .= " AND (p.prescription_ref LIKE ? OR p.doctor_name LIKE ? OR s.invoice_number LIKE ? OR c.name LIKE ?) ";
    $like = '%' . $search . '%';
    array_push($params, $like, $like, $like, $like);
}
if (in_array($status_filter, ['pending', 'dispensed', 'cancelled'], true)) {
    $where .= " AND p.status = ? "; $params[] = $status_filter;
}
$where .= " AND DATE(p.created_at) BETWEEN ? AND ? ";
$params[] = $from; $params[] = $to;

$rows = [];
$stats = ['total' => 0, 'pending' => 0, 'dispensed' => 0, 'cancelled' => 0];

try {
    $stmt = $pdo->prepare("SELECT p.*, s.invoice_number, s.total AS sale_total, c.name AS customer_name
                           FROM prescriptions p
                           LEFT JOIN sales s ON s.id = p.sale_id
                           LEFT JOIN customers c ON c.id = p.customer_id
                           $where
                           ORDER BY p.created_at DESC LIMIT 300");
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $sql = "SELECT status, COUNT(*) c FROM prescriptions p WHERE p.branch_id = ? AND (p.tenant_id = ? OR p.tenant_id IS NULL)
            AND DATE(p.created_at) BETWEEN ? AND ? GROUP BY status";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$branch_id, $tenant_id, $from, $to]);
    while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $stats['total'] += (int)$r['c'];
        if (isset($stats[$r['status']])) $stats[$r['status']] = (int)$r['c'];
    }
} catch (Exception $e) {
    $error = $e->getMessage();
}

$currency = function_exists('get_currency_symbol') ? get_currency_symbol() : 'KES';

$status_badges = [
    'pending'   => 'bg-amber-500/10 text-amber-400 border-amber-500/30',
    'dispensed' => 'bg-emerald-500/10 text-emerald-400 border-emerald-500/30',
    'cancelled' => 'bg-red-500/10 text-red-400 border-red-500/30',
];
?>

<div class="fade-in" id="prescriptions-content">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-5">
        <div>
            <div class="text-xs text-amber-400/70 uppercase tracking-wider font-semibold mb-0.5">Pharmacy</div>
            <h1 class="text-lg font-bold text-white"><i class="fas fa-file-medical text-[#EF4444]"></i> Prescriptions</h1>
            <p class="text-sm text-slate-500 mt-0.5 mt-1">Log of dispensed and pending prescription sales</p>
        </div>
        <div class="flex items-center gap-2 shrink-0">
            <a href="pos.php" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-slate-800 border border-slate-700 text-slate-300 text-sm font-medium hover:bg-slate-700 transition-colors"><i class="fas fa-plus"></i> New Prescription Sale</a>
        </div>
    </div>

    <!-- Stats -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-5">
        <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl overflow-hidden">
            <div class="text-xs text-amber-400/70 uppercase tracking-wider font-semibold mb-0.5 text-[#9CA3AF]">Total</div>
            <div class="text-lg font-bold text-white text-2xl text-[#FBBF24]"><?php echo $stats['total']; ?></div>
        </div>
        <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl overflow-hidden">
            <div class="text-xs text-amber-400/70 uppercase tracking-wider font-semibold mb-0.5 text-[#9CA3AF]">Dispensed</div>
            <div class="text-lg font-bold text-white text-2xl text-[#10B981]"><?php echo $stats['dispensed']; ?></div>
        </div>
        <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl overflow-hidden">
            <div class="text-xs text-amber-400/70 uppercase tracking-wider font-semibold mb-0.5 text-[#9CA3AF]">Pending</div>
            <div class="text-lg font-bold text-white text-2xl text-[#FBBF24]"><?php echo $stats['pending']; ?></div>
        </div>
        <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl overflow-hidden">
            <div class="text-xs text-amber-400/70 uppercase tracking-wider font-semibold mb-0.5 text-[#9CA3AF]">Cancelled</div>
            <div class="text-lg font-bold text-white text-2xl text-[#EF4444]"><?php echo $stats['cancelled']; ?></div>
        </div>
    </div>

    <!-- Filters -->
    <form method="get" class="flex flex-wrap gap-2 mb-4 items-center">
        <input type="search" name="s" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search ref, doctor, invoice, customer..."
               class="px-3 py-2 bg-gray-800 border border-gray-600 rounded-lg text-white placeholder-gray-400 focus:outline-none focus:border-yellow-400 w-64">
        <select name="status" class="px-3 py-2 bg-gray-800 border border-gray-600 rounded-lg text-white">
            <option value="">All statuses</option>
            <?php foreach (['pending','dispensed','cancelled'] as $s): ?>
                <option value="<?php echo $s; ?>" <?php echo $status_filter === $s ? 'selected' : ''; ?>><?php echo ucfirst($s); ?></option>
            <?php endforeach; ?>
        </select>
        <input type="date" name="from" value="<?php echo htmlspecialchars($from); ?>" class="px-3 py-2 bg-gray-800 border border-gray-600 rounded-lg text-white">
        <input type="date" name="to"   value="<?php echo htmlspecialchars($to); ?>"   class="px-3 py-2 bg-gray-800 border border-gray-600 rounded-lg text-white">
        <button type="submit" class="px-4 py-2 bg-gray-700 hover:bg-gray-600 text-white rounded-lg">
            <i class="fas fa-filter"></i> Filter
        </button>
        <a href="prescriptions.php" class="px-3 py-2 text-gray-400 hover:text-white text-sm">Reset</a>
    </form>

    <?php if (empty($rows)): ?>
        <div class="bg-gray-800 border border-gray-700 rounded-lg p-10 text-center text-gray-400">
            <i class="fas fa-file-medical text-4xl text-gray-600 mb-3"></i>
            <p>No prescriptions in this date range.</p>
        </div>
    <?php else: ?>
    <div class="bg-gray-800 border border-gray-700 rounded-lg overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-gray-900/50 text-gray-400 text-xs uppercase">
                <tr>
                    <th class="px-3 py-2 text-left">Date</th>
                    <th class="px-3 py-2 text-left">Ref #</th>
                    <th class="px-3 py-2 text-left">Invoice</th>
                    <th class="px-3 py-2 text-left">Customer</th>
                    <th class="px-3 py-2 text-left">Doctor</th>
                    <th class="px-3 py-2 text-right">Total</th>
                    <th class="px-3 py-2 text-center">Status</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-700">
                <?php foreach ($rows as $r): $sb = $status_badges[$r['status']] ?? ''; ?>
                <tr class="hover:bg-gray-700/30">
                    <td class="px-3 py-2 text-gray-300"><?php echo date('M d, H:i', strtotime($r['created_at'])); ?></td>
                    <td class="px-3 py-2 font-mono text-yellow-300"><?php echo htmlspecialchars($r['prescription_ref'] ?: '—'); ?></td>
                    <td class="px-3 py-2">
                        <?php if (!empty($r['invoice_number'])): ?>
                            <a href="all_sales.php?s=<?php echo urlencode($r['invoice_number']); ?>" class="text-blue-400 hover:underline"><?php echo htmlspecialchars($r['invoice_number']); ?></a>
                        <?php else: ?>
                            <span class="text-gray-500">Sale #<?php echo (int)$r['sale_id']; ?></span>
                        <?php endif; ?>
                    </td>
                    <td class="px-3 py-2 text-gray-200"><?php echo htmlspecialchars($r['customer_name'] ?: 'Walk-in'); ?></td>
                    <td class="px-3 py-2 text-gray-300"><?php echo htmlspecialchars($r['doctor_name'] ?: '—'); ?></td>
                    <td class="px-3 py-2 text-right text-gray-100 font-medium">
                        <?php echo $currency . ' ' . number_format((float)($r['sale_total'] ?? 0), 2); ?>
                    </td>
                    <td class="px-3 py-2 text-center">
                        <span class="inline-block px-2 py-0.5 rounded text-xs border <?php echo $sb; ?>">
                            <?php echo ucfirst($r['status']); ?>
                        </span>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<?php
$page_content = ob_get_clean();
require_once __DIR__ . '/../layouts/app.php';
require_once __DIR__ . '/../layouts/app_close.php';
