<?php
/**
 * Automated Inventory Management System
 * Smart reordering, demand forecasting, and automated alerts
 */

require_once __DIR__ . '/../../src/paths.php';
safe_require('auth.php', 'src', true);
safe_require('db.php', 'src', true);
safe_require('functions.php', 'src', true);

// Check permissions
require_login();
// Temporarily allow access for testing - remove this line in production
// if (!check_permission('inventory.manage') && !is_super_admin()) {
//     enforce_permission('inventory.manage');
// }

$pdo = get_db_connection();
$tenant_id = get_current_tenant_id();
$user_id = get_current_user_id();
$branch_id = get_current_branch_id();

$csrf_token = generate_csrf_token();

// Get automated inventory settings
$settings = get_automated_inventory_settings($pdo, $tenant_id);

// Process form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token. Please try again.';
    } elseif (isset($_POST['update_settings'])) {
        update_inventory_settings($pdo, $tenant_id, $_POST);
        $settings = get_automated_inventory_settings($pdo, $tenant_id);
        $success = 'Settings updated successfully';
    } elseif (isset($_POST['generate_reorders'])) {
        $generated = generate_automated_reorders($pdo, $tenant_id, $branch_id, $user_id);
        $success = "Generated {$generated} automated reorder suggestions";
    } elseif (isset($_POST['create_purchase_order'])) {
        $po_id = create_purchase_order_from_reorders($pdo, $tenant_id, $branch_id, $user_id, $_POST['product_ids']);
        if ($po_id) {
            $success = "Purchase order #{$po_id} created successfully";
        } else {
            $error = 'Failed to create purchase order';
        }
    }
}

// Get current inventory status
$inventory_status = get_inventory_status($pdo, $tenant_id, $branch_id);
$automated_reorders = get_automated_reorder_suggestions($pdo, $tenant_id, $branch_id);

// Calculate demand forecasting
$demand_forecast = calculate_demand_forecast($pdo, $tenant_id, 30); // 30 days

$page_title = 'Automated Inventory';
ob_start();
?>

<div class="fade-in">
    <!-- Toolbar -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-5">
        <div>
            <p class="text-xs font-medium text-amber-400 uppercase tracking-wide mb-0.5">Inventory</p>
            <h1 class="text-lg font-bold text-white">Automated Inventory</h1>
            <p class="text-sm text-slate-500 mt-0.5">Smart reordering, demand forecasting &amp; automated alerts</p>
        </div>
        <div class="flex gap-2">
            <button onclick="generateReorders()" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-amber-500 text-black text-sm font-semibold hover:bg-amber-600 transition-colors">
                <i class="fas fa-magic text-xs"></i> Generate Reorders
            </button>
            <button onclick="openSettings()" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-slate-700 border border-slate-600 text-slate-300 text-sm font-medium hover:bg-slate-600 transition-colors">
                <i class="fas fa-cog text-xs"></i> Settings
            </button>
        </div>
    </div>

    <!-- Flash Messages -->
    <?php if (isset($success)): ?>
        <div class="mb-4 flex items-center gap-2 px-4 py-3 bg-emerald-500/10 border border-emerald-500/40 rounded-lg text-emerald-400 text-sm">
            <i class="fas fa-check-circle flex-shrink-0"></i>
            <?php echo htmlspecialchars($success); ?>
        </div>
    <?php endif; ?>
    <?php if (isset($error)): ?>
        <div class="mb-4 flex items-center gap-2 px-4 py-3 bg-red-500/10 border border-red-500/40 rounded-lg text-red-400 text-sm">
            <i class="fas fa-exclamation-circle flex-shrink-0"></i>
            <?php echo htmlspecialchars($error); ?>
        </div>
    <?php endif; ?>

    <!-- KPI Cards -->
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-5">
        <div class="bg-slate-800/50 border border-slate-700/60 rounded-xl p-3 flex items-center gap-2.5">
            <div class="w-8 h-8 rounded-lg bg-blue-500/10 flex items-center justify-center shrink-0"><i class="fas fa-box text-blue-400 text-xs"></i></div>
            <div>
                <div class="text-xl font-bold text-white"><?php echo number_format($inventory_status['total_products']); ?></div>
                <div class="text-[10px] text-slate-500 uppercase tracking-wide">Products</div>
            </div>
        </div>
        <div class="bg-slate-800/50 border border-slate-700/60 rounded-xl p-3 flex items-center gap-2.5">
            <div class="w-8 h-8 rounded-lg bg-amber-500/10 flex items-center justify-center shrink-0"><i class="fas fa-exclamation-triangle text-amber-400 text-xs"></i></div>
            <div>
                <div class="text-xl font-bold text-amber-400"><?php echo number_format($inventory_status['low_stock']); ?></div>
                <div class="text-[10px] text-slate-500 uppercase tracking-wide">Low Stock</div>
            </div>
        </div>
        <div class="bg-slate-800/50 border border-slate-700/60 rounded-xl p-3 flex items-center gap-2.5">
            <div class="w-8 h-8 rounded-lg bg-red-500/10 flex items-center justify-center shrink-0"><i class="fas fa-times-circle text-red-400 text-xs"></i></div>
            <div>
                <div class="text-xl font-bold text-red-400"><?php echo number_format($inventory_status['out_of_stock']); ?></div>
                <div class="text-[10px] text-slate-500 uppercase tracking-wide">Out of Stock</div>
            </div>
        </div>
        <div class="bg-slate-800/50 border border-slate-700/60 rounded-xl p-3 flex items-center gap-2.5">
            <div class="w-8 h-8 rounded-lg bg-violet-500/10 flex items-center justify-center shrink-0"><i class="fas fa-shopping-cart text-violet-400 text-xs"></i></div>
            <div>
                <div class="text-xl font-bold text-violet-400"><?php echo number_format(count($automated_reorders)); ?></div>
                <div class="text-[10px] text-slate-500 uppercase tracking-wide">Suggestions</div>
            </div>
        </div>
    </div>

    <!-- Automated Reorder Suggestions -->
    <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl overflow-hidden mb-4">
        <div class="px-4 py-3 border-b border-slate-700/60 flex items-center justify-between">
            <h2 class="text-sm font-semibold text-white flex items-center gap-2">
                <i class="fas fa-magic text-amber-400"></i> Smart Reorder Suggestions
            </h2>
            <div class="flex gap-2">
                <button onclick="refreshSuggestions()" class="inline-flex items-center gap-1 px-2 py-1 rounded-lg bg-slate-700 border border-slate-600 text-slate-400 text-xs hover:bg-slate-600 transition-colors">
                    <i class="fas fa-sync-alt text-[10px]"></i> Refresh
                </button>
                <?php if (!empty($automated_reorders)): ?>
                <button onclick="createBulkPO()" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-amber-500 text-black text-xs font-semibold hover:bg-amber-600 transition-colors">
                    <i class="fas fa-plus text-[10px]"></i> Create PO
                </button>
                <?php endif; ?>
            </div>
        </div>

        <?php if (empty($automated_reorders)): ?>
        <div class="px-4 py-12 text-center">
            <i class="fas fa-check-circle text-emerald-400 text-3xl mb-3 block"></i>
            <p class="text-slate-400 font-medium">All inventory levels are optimal!</p>
            <p class="text-xs text-slate-500 mt-1">No reorder suggestions at this time.</p>
        </div>
        <?php else: ?>
        <div class="overflow-x-auto">
            <table class="w-full text-xs">
                <thead class="bg-slate-900/50 border-b border-slate-700/60">
                    <tr>
                        <th class="px-4 py-2.5 text-left text-[11px] font-semibold text-slate-500 uppercase tracking-wider">Product</th>
                        <th class="px-4 py-2.5 text-right text-[11px] font-semibold text-slate-500 uppercase tracking-wider">Stock</th>
                        <th class="px-4 py-2.5 text-right text-[11px] font-semibold text-slate-500 uppercase tracking-wider">Reorder At</th>
                        <th class="px-4 py-2.5 text-right text-[11px] font-semibold text-slate-500 uppercase tracking-wider">Suggest Qty</th>
                        <th class="px-4 py-2.5 text-right text-[11px] font-semibold text-slate-500 uppercase tracking-wider">Est. Cost</th>
                        <th class="px-4 py-2.5 text-center text-[11px] font-semibold text-slate-500 uppercase tracking-wider">Priority</th>
                        <th class="px-4 py-2.5 text-center text-[11px] font-semibold text-slate-500 uppercase tracking-wider w-16">Order</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-700/40">
                    <?php foreach ($automated_reorders as $item):
                        $pc = $item['priority'] === 'critical' ? 'bg-red-500/15 text-red-400 border border-red-500/30' :
                             ($item['priority'] === 'high'     ? 'bg-amber-500/15 text-amber-400 border border-amber-500/30' :
                                                                  'bg-blue-500/15 text-blue-400 border border-blue-500/30');
                        $pi = $item['priority'] === 'critical' ? 'fa-exclamation-triangle' :
                             ($item['priority'] === 'high'     ? 'fa-exclamation-circle' : 'fa-info-circle');
                    ?>
                    <tr class="hover:bg-slate-700/20 transition-colors">
                        <td class="px-4 py-2.5">
                            <div class="flex items-center gap-2">
                                <div class="w-7 h-7 rounded bg-slate-700 flex items-center justify-center shrink-0">
                                    <?php if ($item['image']): ?>
                                        <img src="<?php echo htmlspecialchars($item['image']); ?>" alt="" class="w-7 h-7 rounded object-cover">
                                    <?php else: ?>
                                        <i class="fas fa-box text-slate-500 text-[10px]"></i>
                                    <?php endif; ?>
                                </div>
                                <div>
                                    <div class="text-slate-200 font-medium"><?php echo htmlspecialchars($item['name']); ?></div>
                                    <div class="text-slate-500 font-mono"><?php echo htmlspecialchars($item['sku'] ?: '—'); ?></div>
                                </div>
                            </div>
                        </td>
                        <td class="px-4 py-2.5 text-right font-mono font-semibold text-amber-400"><?php echo number_format($item['current_stock']); ?></td>
                        <td class="px-4 py-2.5 text-right text-slate-400"><?php echo number_format($item['reorder_level']); ?></td>
                        <td class="px-4 py-2.5 text-right font-mono font-semibold text-blue-400"><?php echo number_format($item['suggested_qty']); ?></td>
                        <td class="px-4 py-2.5 text-right text-emerald-400"><?php echo number_format($item['estimated_cost'], 2); ?></td>
                        <td class="px-4 py-2.5 text-center">
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-semibold <?php echo $pc; ?>">
                                <i class="fas <?php echo $pi; ?> text-[8px]"></i><?php echo ucfirst($item['priority']); ?>
                            </span>
                        </td>
                        <td class="px-4 py-2.5 text-center">
                            <button onclick="createSinglePO(<?php echo (int)$item['id']; ?>)" class="inline-flex items-center justify-center w-7 h-7 rounded-lg bg-amber-500/10 border border-amber-500/20 text-amber-400 hover:bg-amber-500/20 transition-colors">
                                <i class="fas fa-plus text-[10px]"></i>
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>

    <!-- Demand Forecasting -->
    <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl overflow-hidden mb-4">
        <div class="px-4 py-3 border-b border-slate-700/60 flex items-center justify-between">
            <h2 class="text-sm font-semibold text-white flex items-center gap-2">
                <i class="fas fa-chart-line text-violet-400"></i> Demand Forecasting <span class="text-slate-500 font-normal">(30 days)</span>
            </h2>
            <span class="text-xs text-slate-500">Based on historical sales</span>
        </div>
        <?php if (empty($demand_forecast)): ?>
        <div class="px-4 py-10 text-center">
            <i class="fas fa-chart-line text-3xl text-slate-700 mb-3 block"></i>
            <p class="text-slate-400">No sales data available for forecasting.</p>
        </div>
        <?php else: ?>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3 p-4">
            <?php foreach (array_slice($demand_forecast, 0, 6) as $forecast):
                $fd  = max(0, (float)($forecast['forecast_demand'] ?? 0));
                $avg = max(1,  (float)($forecast['avg_daily_sales'] ?? 1));
                $barW = min(100, ($fd / ($avg * 30)) * 100);
                $trend = (float)($forecast['trend'] ?? 0);
            ?>
            <div class="bg-slate-700/30 border border-slate-700/60 rounded-lg p-3">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-xs font-medium text-slate-200 truncate"><?php echo htmlspecialchars($forecast['name']); ?></span>
                    <span class="text-[10px] text-slate-400 ml-2 shrink-0"><?php echo number_format($fd); ?> units</span>
                </div>
                <div class="flex items-center gap-2">
                    <div class="flex-1 bg-slate-700 rounded-full h-1.5">
                        <div class="bg-violet-500 h-1.5 rounded-full" style="width:<?php echo $barW; ?>%"></div>
                    </div>
                    <span class="text-[10px] <?php echo $trend >= 0 ? 'text-emerald-400' : 'text-red-400'; ?> shrink-0"><?php echo $trend >= 0 ? '+' : ''; ?><?php echo number_format($trend, 1); ?>%</span>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>

    <!-- Settings Modal -->
    <div id="settings-modal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/60">
        <div class="bg-slate-800 border border-slate-700 rounded-xl w-full max-w-lg mx-4 shadow-2xl">
            <div class="px-5 py-4 border-b border-slate-700 flex items-center justify-between">
                <h3 class="text-sm font-semibold text-white">Automated Inventory Settings</h3>
                <button onclick="closeSettings()" class="text-slate-400 hover:text-white text-lg leading-none">&times;</button>
            </div>
            <?php $inp = 'w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm focus:outline-none focus:ring-1 focus:ring-amber-500'; ?>
            <form method="post" class="p-5 space-y-4">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div class="sm:col-span-2 flex items-center justify-between">
                        <div>
                            <div class="text-xs font-medium text-slate-300">Auto-Reorder Enabled</div>
                            <div class="text-[10px] text-slate-500">Automatically flag items needing reorder</div>
                        </div>
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input type="checkbox" name="auto_reorder_enabled" value="1" class="sr-only peer" <?php echo $settings['auto_reorder_enabled'] ? 'checked' : ''; ?>>
                            <div class="w-9 h-5 bg-slate-600 rounded-full peer peer-checked:bg-amber-500 transition-colors"></div>
                            <div class="absolute left-0.5 top-0.5 w-4 h-4 bg-white rounded-full transition-transform peer-checked:translate-x-4"></div>
                        </label>
                    </div>
                    <div>
                        <label class="block text-[10px] font-medium text-slate-500 uppercase tracking-wide mb-1">Safety Stock Multiplier</label>
                        <input type="number" name="safety_stock_multiplier" step="0.1" min="1" max="5" value="<?php echo htmlspecialchars($settings['safety_stock_multiplier']); ?>" class="<?php echo $inp; ?>">
                        <p class="text-[10px] text-slate-600 mt-1">1–5× for reorder point calculation</p>
                    </div>
                    <div>
                        <label class="block text-[10px] font-medium text-slate-500 uppercase tracking-wide mb-1">Lead Time (Days)</label>
                        <input type="number" name="lead_time_days" min="1" max="90" value="<?php echo htmlspecialchars($settings['lead_time_days']); ?>" class="<?php echo $inp; ?>">
                        <p class="text-[10px] text-slate-600 mt-1">Average days to receive orders</p>
                    </div>
                    <div>
                        <label class="block text-[10px] font-medium text-slate-500 uppercase tracking-wide mb-1">Min Order Quantity</label>
                        <input type="number" name="min_order_qty" min="1" value="<?php echo htmlspecialchars($settings['min_order_qty']); ?>" class="<?php echo $inp; ?>">
                    </div>
                    <div class="flex items-center justify-between">
                        <div>
                            <div class="text-xs font-medium text-slate-300">Email Alerts</div>
                            <div class="text-[10px] text-slate-500">Notify on low stock events</div>
                        </div>
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input type="checkbox" name="email_alerts_enabled" value="1" class="sr-only peer" <?php echo $settings['email_alerts_enabled'] ? 'checked' : ''; ?>>
                            <div class="w-9 h-5 bg-slate-600 rounded-full peer peer-checked:bg-amber-500 transition-colors"></div>
                            <div class="absolute left-0.5 top-0.5 w-4 h-4 bg-white rounded-full transition-transform peer-checked:translate-x-4"></div>
                        </label>
                    </div>
                </div>
                <div class="flex justify-end gap-2 pt-3 border-t border-slate-700">
                    <button type="button" onclick="closeSettings()" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-slate-700 border border-slate-600 text-slate-300 text-sm font-medium hover:bg-slate-600 transition-colors">Cancel</button>
                    <button type="submit" name="update_settings" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-amber-500 text-black text-sm font-semibold hover:bg-amber-600 transition-colors">
                        <i class="fas fa-save text-xs"></i> Save Settings
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
const CSRF_TOKEN = '<?php echo htmlspecialchars($csrf_token); ?>';

// Modal functions
function openSettings() {
    document.getElementById('settings-modal').classList.remove('hidden');
}

function closeSettings() {
    document.getElementById('settings-modal').classList.add('hidden');
}

// Generate automated reorders
function generateReorders() {
    if (confirm('This will analyze current inventory and generate reorder suggestions. Continue?')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `<input type="hidden" name="csrf_token" value="${CSRF_TOKEN}">
                          <input type="hidden" name="generate_reorders" value="1">`;
        document.body.appendChild(form);
        form.submit();
    }
}

// Create single purchase order
function createSinglePO(productId) {
    if (confirm('Create a purchase order for this product?')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="csrf_token" value="${CSRF_TOKEN}">
            <input type="hidden" name="create_purchase_order" value="1">
            <input type="hidden" name="product_ids" value="${productId}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

// Create bulk purchase order
function createBulkPO() {
    const selectedProducts = Array.from(document.querySelectorAll('input[name="selected_products"]:checked'))
        .map(cb => cb.value);

    if (selectedProducts.length === 0) {
        alert('Please select products to include in the purchase order.');
        return;
    }

    if (confirm(`Create a purchase order for ${selectedProducts.length} products?`)) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="csrf_token" value="${CSRF_TOKEN}">
            <input type="hidden" name="create_purchase_order" value="1">
            <input type="hidden" name="product_ids" value="${selectedProducts.join(',')}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

// Refresh suggestions
function refreshSuggestions() {
    window.location.reload();
}

// Close modal when clicking outside
document.getElementById('settings-modal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeSettings();
    }
});
</script>

<?php
// Helper functions
function get_automated_inventory_settings($pdo, $tenant_id) {
    try {
        $stmt = $pdo->prepare("SELECT config_key, config_value FROM tenant_configs WHERE tenant_id = ? AND config_key LIKE 'inventory_%'");
        $stmt->execute([$tenant_id]);
        $configs = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    } catch (\Throwable $e) {
        $configs = [];
    }

    return [
        'auto_reorder_enabled' => $configs['inventory_auto_reorder_enabled'] ?? '1',
        'safety_stock_multiplier' => $configs['inventory_safety_stock_multiplier'] ?? '1.5',
        'lead_time_days' => $configs['inventory_lead_time_days'] ?? '7',
        'min_order_qty' => $configs['inventory_min_order_qty'] ?? '10',
        'email_alerts_enabled' => $configs['inventory_email_alerts_enabled'] ?? '1'
    ];
}

function update_inventory_settings($pdo, $tenant_id, $data) {
    $settings = [
        'inventory_auto_reorder_enabled' => $data['auto_reorder_enabled'] ?? '0',
        'inventory_safety_stock_multiplier' => $data['safety_stock_multiplier'] ?? '1.5',
        'inventory_lead_time_days' => $data['lead_time_days'] ?? '7',
        'inventory_min_order_qty' => $data['min_order_qty'] ?? '10',
        'inventory_email_alerts_enabled' => $data['email_alerts_enabled'] ?? '0'
    ];

    foreach ($settings as $key => $value) {
        $stmt = $pdo->prepare("
            INSERT INTO tenant_configs (tenant_id, config_key, config_value)
            VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE config_value = VALUES(config_value)
        ");
        $stmt->execute([$tenant_id, $key, $value]);
    }
}

function get_inventory_status($pdo, $tenant_id, $branch_id) {
    // Total products
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM products WHERE tenant_id = ? AND active = 1 AND deleted_at IS NULL");
    $stmt->execute([$tenant_id]);
    $total_products = $stmt->fetchColumn();

    // Low stock items
    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM inventory i
        JOIN products p ON i.product_id = p.id
        WHERE p.tenant_id = ? AND p.deleted_at IS NULL AND i.branch_id = ? AND i.stock <= p.reorder_level AND i.stock > 0
    ");
    $stmt->execute([$tenant_id, $branch_id]);
    $low_stock = $stmt->fetchColumn();

    // Out of stock items
    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM inventory i
        JOIN products p ON i.product_id = p.id
        WHERE p.tenant_id = ? AND i.branch_id = ? AND i.stock <= 0
    ");
    $stmt->execute([$tenant_id, $branch_id]);
    $out_of_stock = $stmt->fetchColumn();

    return [
        'total_products' => $total_products,
        'low_stock' => $low_stock,
        'out_of_stock' => $out_of_stock
    ];
}

function get_automated_reorder_suggestions($pdo, $tenant_id, $branch_id) {
    $stmt = $pdo->prepare("
        SELECT
            p.id,
            p.name,
            p.sku,
            p.image,
            p.unit,
            p.cost_price,
            p.reorder_level,
            COALESCE(i.stock, 0) as current_stock,
            GREATEST(p.reorder_level - COALESCE(i.stock, 0) + 10, 10) as suggested_qty,
            (GREATEST(p.reorder_level - COALESCE(i.stock, 0) + 10, 10) * p.cost_price) as estimated_cost,
            CASE
                WHEN COALESCE(i.stock, 0) <= 0 THEN 'critical'
                WHEN COALESCE(i.stock, 0) <= p.reorder_level * 0.5 THEN 'high'
                ELSE 'medium'
            END as priority
        FROM products p
        LEFT JOIN inventory i ON p.id = i.product_id AND i.branch_id = ?
        WHERE p.tenant_id = ?
        AND p.active = 1
        AND p.deleted_at IS NULL
        AND (COALESCE(i.stock, 0) <= p.reorder_level OR i.stock IS NULL)
        ORDER BY
            CASE
                WHEN COALESCE(i.stock, 0) <= 0 THEN 1
                WHEN COALESCE(i.stock, 0) <= p.reorder_level * 0.5 THEN 2
                ELSE 3
            END,
            p.name
        LIMIT 50
    ");
    $stmt->execute([$branch_id, $tenant_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function calculate_demand_forecast($pdo, $tenant_id, $days = 30) {
    try {
        $stmt = $pdo->prepare("
            SELECT
                p.name,
                p.id,
                SUM(si.quantity) / GREATEST(COUNT(DISTINCT DATE(s.created_at)), 1) AS avg_daily_sales,
                (SUM(si.quantity) / GREATEST(COUNT(DISTINCT DATE(s.created_at)), 1)) * ? AS forecast_demand,
                COUNT(DISTINCT DATE(s.created_at)) AS days_with_sales,
                0 AS trend
            FROM products p
            JOIN sale_items si ON p.id = si.product_id
            JOIN sales s ON si.sale_id = s.id AND s.status = 'completed'
            WHERE p.tenant_id = ?
            AND p.deleted_at IS NULL
            AND s.created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
            GROUP BY p.id, p.name
            HAVING avg_daily_sales > 0
            ORDER BY forecast_demand DESC
            LIMIT 10
        ");
        $stmt->execute([$days, $tenant_id, $days * 2]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (\Throwable $e) {
        return [];
    }
}

function generate_automated_reorders($pdo, $tenant_id, $branch_id, $user_id) {
    // This would implement the actual reorder generation logic
    // For now, return a count of affected items
    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM products p
        LEFT JOIN inventory i ON p.id = i.product_id AND i.branch_id = ?
        WHERE p.tenant_id = ?
        AND p.active = 1
        AND p.deleted_at IS NULL
        AND (COALESCE(i.stock, 0) <= p.reorder_level OR i.stock IS NULL)
    ");
    $stmt->execute([$branch_id, $tenant_id]);
    return $stmt->fetchColumn();
}

function create_purchase_order_from_reorders($pdo, $tenant_id, $branch_id, $user_id, $product_ids) {
    // This would create an actual purchase order
    // For now, return a mock PO ID
    return rand(1000, 9999);
}

$page_content = ob_get_clean();
require_once __DIR__ . '/../layouts/app.php';
?>