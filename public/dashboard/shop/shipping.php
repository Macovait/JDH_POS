<?php
/**
 * Store Admin — Shipping
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
$currency = $settings['currency'] ?? 'KES';

// Load currency symbol
try {
    $row = $pdo->prepare("SELECT setting_value FROM settings WHERE tenant_id = ? AND setting_key = 'currency' LIMIT 1");
    $row->execute([$tenant_id]);
    $currency = $row->fetchColumn() ?: 'KES';
} catch (Exception $e) {}

$blocks_count = 0;
try {
    $st = $pdo->prepare("SELECT COUNT(*) FROM storefront_blocks WHERE tenant_id = ? AND is_active = 1");
    $st->execute([$tenant_id]);
    $blocks_count = (int)$st->fetchColumn();
} catch (Exception $e) {}

$msg = '';
$msg_type = 'success';

// ── Save Shipping Settings ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['section']) && $_POST['section'] === 'shipping') {
    $fields = [
        'free_shipping_threshold', 
        'flat_rate_amount', 
        'enable_local_pickup',
        'shipping_note',
        'estimated_delivery_days',
        'free_shipping_label',
        'flat_rate_label',
        'enable_free_shipping'
    ];
    
    try {
        $pdo->beginTransaction();
        foreach ($fields as $k) {
            $val = $_POST[$k] ?? '';
            // Handle checkbox
            if (in_array($k, ['enable_local_pickup', 'enable_free_shipping'])) {
                $val = isset($_POST[$k]) ? '1' : '0';
            }
            $stmt = $pdo->prepare(
                "INSERT INTO storefront_settings (tenant_id, setting_key, setting_value) 
                 VALUES (?, ?, ?) 
                 ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)"
            );
            $stmt->execute([$tenant_id, $k, $val]);
        }
        $pdo->commit();
        $msg = 'Shipping settings saved successfully!';
        $msg_type = 'success';
    } catch (Exception $e) {
        $pdo->rollBack();
        $msg = 'Failed to save settings: ' . $e->getMessage();
        $msg_type = 'error';
    }
}

// ── Load Storefront Settings ──
$sf = [];
try {
    $stmt = $pdo->prepare("SELECT setting_key, setting_value FROM storefront_settings WHERE tenant_id = ?");
    $stmt->execute([$tenant_id]);
    while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $sf[$r['setting_key']] = $r['setting_value'];
    }
} catch (Exception $e) {}

$free_threshold = $sf['free_shipping_threshold'] ?? '5000';
$flat_rate = $sf['flat_rate_amount'] ?? '300';
$local_pickup = ($sf['enable_local_pickup'] ?? '0') === '1';
$free_shipping_enabled = ($sf['enable_free_shipping'] ?? '1') === '1';
$shipping_note = $sf['shipping_note'] ?? 'Standard delivery within 2-3 business days.';
$estimated_delivery_days = $sf['estimated_delivery_days'] ?? '2-3';
$free_shipping_label = $sf['free_shipping_label'] ?? 'Free Shipping';
$flat_rate_label = $sf['flat_rate_label'] ?? 'Standard Shipping';

// ── Statistics ──
$shipment_stats = [
    'total' => 0,
    'pending' => 0,
    'in_transit' => 0,
    'delivered' => 0,
    'cancelled' => 0
];

try {
    $stmt = $pdo->prepare(
        "SELECT status, COUNT(*) as count, COALESCE(SUM(shipping_cost), 0) as total_shipping 
         FROM shipments 
         WHERE tenant_id = ? 
         GROUP BY status"
    );
    $stmt->execute([$tenant_id]);
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $shipment_stats[$row['status']] = (int)$row['count'];
        $shipment_stats['total'] += (int)$row['count'];
    }
} catch (Exception $e) {}

// ── Recent Shipments ──
$shipments = [];
try {
    $stmt = $pdo->prepare(
        "SELECT s.id, s.tracking_number, s.status, s.carrier, s.estimated_delivery, s.created_at, 
                s.shipping_cost, s.tracking_url,
                o.order_number, o.customer_name 
         FROM shipments s 
         JOIN online_orders o ON o.id = s.order_id 
         WHERE s.tenant_id = ? 
         ORDER BY s.created_at DESC 
         LIMIT 10"
    );
    $stmt->execute([$tenant_id]);
    $shipments = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

// ── Stats ──
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
$page_title = 'Shipping';
$page_icon = 'fa-truck';
ob_start();
?>

<div class="space-y-6">

    <!-- Header -->
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
        <div>
            <h1 class="text-2xl font-bold text-white flex items-center gap-3">
                <i class="fas fa-truck text-amber-400 text-xl"></i>
                Shipping
            </h1>
            <p class="text-sm text-slate-500 mt-1 flex items-center gap-3">
                <span>Delivery options & shipment management</span>
                <?php if ($shipment_stats['total'] > 0): ?>
                <span class="text-[10px] bg-slate-700/50 px-2 py-0.5 rounded-full text-slate-400">
                    <?= number_format($shipment_stats['total']) ?> total shipments
                </span>
                <?php endif; ?>
            </p>
        </div>
        <div class="flex items-center gap-3">
            <a href="<?= base_url('shipments/shipments.php') ?>" class="inline-flex items-center gap-2 px-4 py-2 rounded-xl text-white text-xs font-bold transition-all duration-200 hover:opacity-90 shadow-lg" style="background: <?= $brand_color ?>">
                <i class="fas fa-truck"></i> Shipment Center
            </a>
            <a href="<?= htmlspecialchars($storeUrl) ?>" target="_blank" class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-slate-800/80 border border-slate-700/60 text-slate-400 text-xs font-medium hover:bg-slate-700/80 hover:text-white hover:border-slate-600 transition-all duration-200">
                <i class="fas fa-eye text-[10px]"></i> View Store
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

        <!-- LEFT: Settings & Shipments -->
        <div class="lg:col-span-2 space-y-6">

            <!-- Shipping Settings -->
            <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-6">
                <h3 class="font-bold text-white text-base mb-4 flex items-center gap-2">
                    <i class="fas fa-sliders-h text-amber-400 text-sm"></i>
                    Shipping Settings
                </h3>
                
                <form method="post" class="space-y-4">
                    <input type="hidden" name="section" value="shipping">
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <!-- Free Shipping Threshold -->
                        <div>
                            <label class="block text-xs font-medium text-slate-400 mb-1">
                                Free Shipping Threshold (<?= $currency ?>)
                            </label>
                            <div class="relative">
                                <span class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-500 text-xs"><?= $currency ?></span>
                                <input type="number" name="free_shipping_threshold" 
                                       value="<?= htmlspecialchars($free_threshold) ?>" 
                                       min="0" step="100"
                                       class="w-full pl-8 pr-3 py-2.5 text-sm bg-slate-900/50 border border-slate-700/60 rounded-lg text-slate-200 focus:outline-none focus:border-amber-500/50 focus:ring-1 focus:ring-amber-500/20 transition-all">
                            </div>
                            <p class="text-[10px] text-slate-500 mt-1">Orders above this amount get free shipping.</p>
                        </div>
                        
                        <!-- Flat Rate Amount -->
                        <div>
                            <label class="block text-xs font-medium text-slate-400 mb-1">
                                Flat Rate Amount (<?= $currency ?>)
                            </label>
                            <div class="relative">
                                <span class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-500 text-xs"><?= $currency ?></span>
                                <input type="number" name="flat_rate_amount" 
                                       value="<?= htmlspecialchars($flat_rate) ?>" 
                                       min="0" step="50"
                                       class="w-full pl-8 pr-3 py-2.5 text-sm bg-slate-900/50 border border-slate-700/60 rounded-lg text-slate-200 focus:outline-none focus:border-amber-500/50 focus:ring-1 focus:ring-amber-500/20 transition-all">
                            </div>
                        </div>
                    </div>

                    <!-- Labels -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-medium text-slate-400 mb-1">Free Shipping Label</label>
                            <input type="text" name="free_shipping_label" 
                                   value="<?= htmlspecialchars($free_shipping_label) ?>" 
                                   placeholder="Free Shipping"
                                   class="w-full px-3 py-2.5 text-sm bg-slate-900/50 border border-slate-700/60 rounded-lg text-slate-200 focus:outline-none focus:border-amber-500/50 focus:ring-1 focus:ring-amber-500/20 transition-all">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-400 mb-1">Flat Rate Label</label>
                            <input type="text" name="flat_rate_label" 
                                   value="<?= htmlspecialchars($flat_rate_label) ?>" 
                                   placeholder="Standard Shipping"
                                   class="w-full px-3 py-2.5 text-sm bg-slate-900/50 border border-slate-700/60 rounded-lg text-slate-200 focus:outline-none focus:border-amber-500/50 focus:ring-1 focus:ring-amber-500/20 transition-all">
                        </div>
                    </div>

                    <!-- Estimated Delivery -->
                    <div>
                        <label class="block text-xs font-medium text-slate-400 mb-1">Estimated Delivery Days</label>
                        <input type="text" name="estimated_delivery_days" 
                               value="<?= htmlspecialchars($estimated_delivery_days) ?>" 
                               placeholder="2-3 business days"
                               class="w-full px-3 py-2.5 text-sm bg-slate-900/50 border border-slate-700/60 rounded-lg text-slate-200 focus:outline-none focus:border-amber-500/50 focus:ring-1 focus:ring-amber-500/20 transition-all">
                    </div>

                    <!-- Checkboxes -->
                    <div class="flex flex-wrap gap-4">
                        <label class="flex items-center gap-2 cursor-pointer group">
                            <input type="checkbox" name="enable_free_shipping" value="1" 
                                   <?= $free_shipping_enabled ? 'checked' : '' ?> 
                                   class="rounded border-slate-600 bg-slate-800 text-amber-500 focus:ring-amber-500/20">
                            <span class="text-xs font-medium text-slate-300 group-hover:text-white transition-colors">Enable Free Shipping</span>
                        </label>
                        <label class="flex items-center gap-2 cursor-pointer group">
                            <input type="checkbox" name="enable_local_pickup" value="1" 
                                   <?= $local_pickup ? 'checked' : '' ?> 
                                   class="rounded border-slate-600 bg-slate-800 text-amber-500 focus:ring-amber-500/20">
                            <span class="text-xs font-medium text-slate-300 group-hover:text-white transition-colors">Enable Local Pickup</span>
                        </label>
                    </div>

                    <!-- Shipping Note -->
                    <div>
                        <label class="block text-xs font-medium text-slate-400 mb-1">Shipping Note (shown at checkout)</label>
                        <textarea name="shipping_note" rows="2" 
                                  class="w-full px-3 py-2.5 text-sm bg-slate-900/50 border border-slate-700/60 rounded-lg text-slate-200 focus:outline-none focus:border-amber-500/50 focus:ring-1 focus:ring-amber-500/20 transition-all resize-y"
                                  placeholder="Delivery information for customers..."><?= htmlspecialchars($shipping_note) ?></textarea>
                    </div>

                    <div class="pt-2">
                        <button type="submit" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl text-white text-sm font-bold transition-all duration-200 hover:opacity-90 shadow-lg" style="background: <?= $brand_color ?>">
                            <i class="fas fa-save text-xs"></i> Save Settings
                        </button>
                    </div>
                </form>
            </div>

            <!-- Recent Shipments -->
            <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl overflow-hidden">
                <div class="px-6 py-4 border-b border-slate-700/60 flex items-center justify-between">
                    <h3 class="font-bold text-white flex items-center gap-2">
                        <i class="fas fa-history text-amber-400 text-sm"></i>
                        Recent Shipments
                    </h3>
                    <a href="<?= base_url('shipments/shipments.php') ?>" class="text-[11px] text-amber-400 font-medium hover:text-amber-300 transition-all inline-flex items-center gap-1">
                        View All <i class="fas fa-arrow-right text-[9px]"></i>
                    </a>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-slate-900/50 text-slate-500 text-xs">
                            <tr>
                                <th class="px-4 py-3.5 text-left font-semibold">Order / Tracking</th>
                                <th class="px-4 py-3.5 text-left font-semibold">Customer</th>
                                <th class="px-4 py-3.5 text-left font-semibold">Carrier</th>
                                <th class="px-4 py-3.5 text-center font-semibold">Status</th>
                                <th class="px-4 py-3.5 text-left font-semibold">Est. Delivery</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-700/40">
                            <?php if (empty($shipments)): ?>
                            <tr>
                                <td colspan="5" class="px-4 py-16 text-center text-slate-500">
                                    <div class="w-16 h-16 rounded-full bg-slate-800/80 flex items-center justify-center mx-auto mb-3">
                                        <i class="fas fa-truck text-2xl text-slate-600"></i>
                                    </div>
                                    <p class="font-medium text-slate-400">No shipments yet</p>
                                    <p class="text-xs mt-1">Shipments will appear once orders are processed</p>
                                </td>
                            </tr>
                            <?php else: ?>
                            <?php foreach ($shipments as $s):
                                $statusClasses = [
                                    'pending' => 'bg-amber-500/15 text-amber-400',
                                    'in_transit' => 'bg-blue-500/15 text-blue-400',
                                    'delivered' => 'bg-emerald-500/15 text-emerald-400',
                                    'cancelled' => 'bg-red-500/15 text-red-400'
                                ];
                                $bc = $statusClasses[$s['status']] ?? 'bg-slate-500/15 text-slate-400';
                                $statusLabel = $s['status'] ? ucwords(str_replace('_', ' ', $s['status'])) : 'Pending';
                            ?>
                            <tr class="hover:bg-slate-700/20 transition-colors">
                                <td class="px-4 py-3">
                                    <div class="font-mono text-xs font-bold text-amber-400"><?= htmlspecialchars($s['order_number'] ?: '#'.$s['id']) ?></div>
                                    <div class="text-[10px] text-slate-500">
                                        <?php if ($s['tracking_number']): ?>
                                        <a href="<?= htmlspecialchars($s['tracking_url'] ?: '#') ?>" target="_blank" class="hover:text-amber-400 transition-colors">
                                            <?= htmlspecialchars($s['tracking_number']) ?>
                                            <i class="fas fa-external-link-alt text-[8px] ml-0.5"></i>
                                        </a>
                                        <?php else: ?>
                                        No tracking
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-xs text-slate-300"><?= htmlspecialchars($s['customer_name'] ?: '-') ?></td>
                                <td class="px-4 py-3 text-xs text-slate-300"><?= htmlspecialchars($s['carrier'] ?: '-') ?></td>
                                <td class="px-4 py-3 text-center">
                                    <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-medium <?= $bc ?>">
                                        <span class="w-1 h-1 rounded-full <?= str_replace('text-', 'bg-', $bc) ?>"></span>
                                        <?= $statusLabel ?>
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-slate-500 text-[10px]">
                                    <?= $s['estimated_delivery'] ? date('M d, Y', strtotime($s['estimated_delivery'])) : '-' ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- RIGHT: Quick Links & Info -->
        <div class="space-y-6">

            <!-- Quick Links -->
            <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-6">
                <h3 class="font-bold text-white text-base mb-4 flex items-center gap-2">
                    <i class="fas fa-link text-amber-400 text-sm"></i>
                    Quick Links
                </h3>
                <div class="space-y-1.5">
                    <a href="<?= base_url('shipments/create_shipment.php') ?>" class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-xs font-medium text-slate-400 hover:bg-slate-700/50 hover:text-white transition-all no-underline group">
                        <span class="w-7 h-7 rounded-lg bg-amber-500/10 flex items-center justify-center flex-shrink-0">
                            <i class="fas fa-plus text-amber-400 text-[10px]"></i>
                        </span>
                        <span>Create Shipment</span>
                    </a>
                    <a href="<?= base_url('shipments/track_shipment.php') ?>" class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-xs font-medium text-slate-400 hover:bg-slate-700/50 hover:text-white transition-all no-underline group">
                        <span class="w-7 h-7 rounded-lg bg-amber-500/10 flex items-center justify-center flex-shrink-0">
                            <i class="fas fa-search-location text-amber-400 text-[10px]"></i>
                        </span>
                        <span>Track Shipment</span>
                    </a>
                    <a href="<?= base_url('shipments/shipments.php') ?>" class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-xs font-medium text-slate-400 hover:bg-slate-700/50 hover:text-white transition-all no-underline group">
                        <span class="w-7 h-7 rounded-lg bg-amber-500/10 flex items-center justify-center flex-shrink-0">
                            <i class="fas fa-list text-amber-400 text-[10px]"></i>
                        </span>
                        <span>All Shipments</span>
                    </a>
                    <a href="<?= base_url('shipments/bulk_shipment.php') ?>" class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-xs font-medium text-slate-400 hover:bg-slate-700/50 hover:text-white transition-all no-underline group">
                        <span class="w-7 h-7 rounded-lg bg-amber-500/10 flex items-center justify-center flex-shrink-0">
                            <i class="fas fa-cubes text-amber-400 text-[10px]"></i>
                        </span>
                        <span>Bulk Shipment</span>
                    </a>
                </div>
            </div>

            <!-- Shipment Stats -->
            <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-6">
                <h3 class="font-bold text-white text-base mb-4 flex items-center gap-2">
                    <i class="fas fa-chart-pie text-amber-400 text-sm"></i>
                    Shipment Overview
                </h3>
                <div class="space-y-2.5">
                    <div class="flex items-center justify-between">
                        <span class="text-xs text-slate-400">Total Shipments</span>
                        <span class="text-sm font-bold text-white"><?= number_format($shipment_stats['total']) ?></span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-xs text-slate-400 flex items-center gap-1.5">
                            <span class="w-1.5 h-1.5 rounded-full bg-amber-400"></span>
                            Pending
                        </span>
                        <span class="text-sm font-bold text-amber-400"><?= number_format($shipment_stats['pending']) ?></span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-xs text-slate-400 flex items-center gap-1.5">
                            <span class="w-1.5 h-1.5 rounded-full bg-blue-400"></span>
                            In Transit
                        </span>
                        <span class="text-sm font-bold text-blue-400"><?= number_format($shipment_stats['in_transit']) ?></span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-xs text-slate-400 flex items-center gap-1.5">
                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-400"></span>
                            Delivered
                        </span>
                        <span class="text-sm font-bold text-emerald-400"><?= number_format($shipment_stats['delivered']) ?></span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-xs text-slate-400 flex items-center gap-1.5">
                            <span class="w-1.5 h-1.5 rounded-full bg-red-400"></span>
                            Cancelled
                        </span>
                        <span class="text-sm font-bold text-red-400"><?= number_format($shipment_stats['cancelled']) ?></span>
                    </div>
                </div>
            </div>

            <!-- Tips -->
            <div class="bg-slate-900/50 border border-slate-700/60 rounded-xl p-5">
                <h3 class="text-xs font-bold text-slate-500 uppercase tracking-wider mb-2 flex items-center gap-2">
                    <i class="fas fa-lightbulb text-amber-400"></i> Shipping Tips
                </h3>
                <ul class="text-[11px] text-slate-500 space-y-1.5 leading-relaxed">
                    <li>· <span class="text-slate-400">Set realistic thresholds</span> to increase average order value</li>
                    <li>· <span class="text-slate-400">Offer free shipping</span> on orders above <?= $currency ?> <?= number_format((float)$free_threshold, 0) ?></li>
                    <li>· <span class="text-slate-400">Provide tracking numbers</span> to improve customer satisfaction</li>
                    <li>· <span class="text-slate-400">Multiple carrier options</span> give customers flexibility</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<?php
$page_content = ob_get_clean();
require_once __DIR__ . '/../../layouts/app.php';
?>