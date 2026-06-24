<?php
/**
 * Inventory Tracking API Controller
 * RESTful API endpoints for inventory management
 * 
 * Follows the same patterns as barcode_labels.php and accounting controller:
 * - Security-first with CSRF protection
 * - Input validation and sanitization
 * - Proper error handling
 * - Rate limiting integration
 * - Service-oriented architecture
 */

require_once __DIR__ . '/../../src/paths.php';
safe_require('auth.php', 'src', true);
safe_require('db.php', 'src', true);
safe_require('functions.php', 'src', true);

// Import inventory services
require_once __DIR__ . '/../../src/Inventory/InventoryTrackingService.php';
require_once __DIR__ . '/../../src/SettingsManager.php';
require_once __DIR__ . '/../../src/Barcode/RateLimiter.php';
require_once __DIR__ . '/../../src/Barcode/BarcodeErrorHandler.php';

require_login();

// Check permissions for inventory features
if (!check_permission('inventory.view') && !is_super_admin()) {
    enforce_permission('inventory.view');
}

$pdo = get_db_connection();
$tenant_id = get_current_tenant_id();
$branch_id = get_current_branch_id();
$user_id = function_exists('get_current_user_id') ? get_current_user_id() : (int)($_SESSION['user']['id'] ?? 0);

if (!$tenant_id) {
    http_response_code(403);
    exit('Company context missing. Please log in again.');
}

// Initialize services with graceful degradation
$inventoryService = null;
$serviceError = null;
try {
    $settingsManager = new SettingsManager($pdo, $tenant_id);
    $rateLimiter = new \JDH\POS\Barcode\RateLimiter($pdo, $tenant_id, $user_id);
    $errorHandler = new \JDH\POS\Barcode\BarcodeErrorHandler(false);
    $inventoryService = new \JDH\POS\Inventory\InventoryTrackingService(
        $pdo,
        $settingsManager,
        $rateLimiter,
        $errorHandler,
        $tenant_id,
        $branch_id,
        $user_id
    );
} catch (\Throwable $e) {
    $serviceError = $e->getMessage();
    error_log('[inventory_tracking] Service init failed: ' . $e->getMessage());
}

$csrf_token = generate_csrf_token();

// ============================================================================
// API ENDPOINTS
// ============================================================================

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action'])) {
    header('Content-Type: application/json');
    
    if (!$inventoryService) {
        http_response_code(503);
        echo json_encode(['success' => false, 'error' => 'Inventory service unavailable']);
        exit;
    }
    
    try {
        switch ($_GET['action']) {
            case 'get_stock_levels':
                // Get current stock levels
                $productIds = isset($_GET['product_ids']) 
                    ? json_decode($_GET['product_ids'], true) 
                    : [];
                $locationId = isset($_GET['location_id']) 
                    ? (int)$_GET['location_id'] 
                    : null;
                
                // Validate product_ids is an array if provided
                if (!empty($productIds) && !is_array($productIds)) {
                    throw new Exception('product_ids must be an array');
                }
                
                $stockLevels = $inventoryService->getStockLevels($productIds, $locationId);
                echo json_encode(['success' => true, 'data' => $stockLevels]);
                break;
                
            case 'get_low_stock':
                // Get low stock items
                $locationId = isset($_GET['location_id']) 
                    ? (int)$_GET['location_id'] 
                    : null;
                
                $lowStock = $inventoryService->getLowStockItems($locationId);
                echo json_encode(['success' => true, 'data' => $lowStock]);
                break;
                
            default:
                throw new Exception('Invalid action');
        }
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// ============================================================================
// POST/PUT/PATCH/DELETE ENDPOINTS
// ============================================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');

    if (!$inventoryService) {
        http_response_code(503);
        echo json_encode(['success' => false, 'error' => 'Inventory service unavailable']);
        exit;
    }

    try {
        if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
            throw new Exception('Invalid security token');
        }
        
        switch ($_POST['action']) {
            case 'reserve_stock':
                // Reserve stock for sales order
                $itemsJson = $_POST['items'] ?? '[]';
                $items = json_decode($itemsJson, true);
                
                if (!is_array($items)) {
                    throw new Exception('Items must be a JSON object/array');
                }
                
                // Convert to product_id => quantity format
                $formattedItems = [];
                foreach ($items as $item) {
                    if (is_array($item) && isset($item['product_id'], $item['quantity'])) {
                        $formattedItems[(int)$item['product_id']] = (int)$item['quantity'];
                    } elseif (is_numeric($item) && isset($_POST['quantity'])) {
                        // Handle alternative format: items[]=product_id&quantity=X
                        // This is more complex - for simplicity, we'll expect the first format
                    }
                }
                
                if (empty($formattedItems)) {
                    throw new Exception('No valid items provided');
                }
                
                $referenceId = isset($_POST['reference_id']) ? (int)$_POST['reference_id'] : 0;
                $referenceType = $_POST['reference_type'] ?? 'sale';
                
                if ($referenceId <= 0) {
                    throw new Exception('Invalid reference ID');
                }
                
                $result = $inventoryService->reserveStock($formattedItems, $referenceId, $referenceType);
                echo json_encode(['success' => true, 'reserved' => $result]);
                break;
                
            case 'release_stock':
                // Release stock reservation
                $itemsJson = $_POST['items'] ?? '[]';
                $items = json_decode($itemsJson, true);
                
                if (!is_array($items)) {
                    throw new Exception('Items must be a JSON object/array');
                }
                
                $formattedItems = [];
                foreach ($items as $item) {
                    if (is_array($item) && isset($item['product_id'], $item['quantity'])) {
                        $formattedItems[(int)$item['product_id']] = (int)$item['quantity'];
                    }
                }
                
                if (empty($formattedItems)) {
                    throw new Exception('No valid items provided');
                }
                
                $referenceId = isset($_POST['reference_id']) ? (int)$_POST['reference_id'] : 0;
                $referenceType = $_POST['reference_type'] ?? 'sale';
                
                if ($referenceId <= 0) {
                    throw new Exception('Invalid reference ID');
                }
                
                $result = $inventoryService->releaseStock($formattedItems, $referenceId, $referenceType);
                echo json_encode(['success' => true, 'released' => $result]);
                break;
                
            case 'fulfill_stock':
                // Fulfill stock (convert allocation to actual deduction)
                $itemsJson = $_POST['items'] ?? '[]';
                $items = json_decode($itemsJson, true);
                
                if (!is_array($items)) {
                    throw new Exception('Items must be a JSON object/array');
                }
                
                $formattedItems = [];
                foreach ($items as $item) {
                    if (is_array($item) && isset($item['product_id'], $item['quantity'])) {
                        $formattedItems[(int)$item['product_id']] = (int)$item['quantity'];
                    }
                }
                
                if (empty($formattedItems)) {
                    throw new Exception('No valid items provided');
                }
                
                $referenceId = isset($_POST['reference_id']) ? (int)$_POST['reference_id'] : 0;
                $referenceType = $_POST['reference_type'] ?? 'sale';
                
                if ($referenceId <= 0) {
                    throw new Exception('Invalid reference ID');
                }
                
                $result = $inventoryService->fulfillStock($formattedItems, $referenceId, $referenceType);
                echo json_encode(['success' => true, 'fulfilled' => $result]);
                break;
                
            case 'adjust_inventory':
                // Adjust inventory (counts, losses, etc.)
                $productId = isset($_POST['product_id']) ? (int)$_POST['product_id'] : 0;
                $quantity = isset($_POST['quantity']) ? (int)$_POST['quantity'] : 0;
                $reason = $_POST['reason'] ?? '';
                $referenceType = $_POST['reference_type'] ?? 'adjustment';
                $referenceId = isset($_POST['reference_id']) ? (int)$_POST['reference_id'] : 0;
                
                if ($productId <= 0) {
                    throw new Exception('Invalid product ID');
                }
                
                if ($quantity == 0) {
                    throw new Exception('Quantity must not be zero');
                }
                
                if (empty($reason)) {
                    throw new Exception('Reason is required');
                }
                
                $result = $inventoryService->adjustInventory($productId, $quantity, $reason, $referenceType, $referenceId);
                echo json_encode(['success' => true, 'adjusted' => $result]);
                break;
                
            default:
                throw new Exception('Invalid action');
        }
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// ============================================================================
// HTML INTERFACE — server-side rendered
// ============================================================================

// Fetch data server-side now
$search      = trim($_GET['search'] ?? '');
$view        = $_GET['view'] ?? 'all'; // all | low
$stockItems  = [];
$lowItems    = [];
if ($inventoryService) {
    try { $stockItems = $inventoryService->getStockLevels(); } catch (\Throwable $e) { $serviceError = $e->getMessage(); }
    try { $lowItems   = $inventoryService->getLowStockItems(); } catch (\Throwable $e) {}
}

// Apply search filter client-side in PHP
if ($search !== '') {
    $s = strtolower($search);
    $stockItems = array_filter($stockItems, fn($r) =>
        str_contains(strtolower($r['name'] ?? ''), $s) ||
        str_contains(strtolower($r['sku'] ?? ''), $s)
    );
    $stockItems = array_values($stockItems);
}

$displayItems  = $view === 'low' ? $lowItems : $stockItems;
$totalUnits    = array_sum(array_column($stockItems, 'quantity_on_hand'));
$lowCount      = count($lowItems);

$page_title = 'Inventory Tracking';
ob_start();
?>
<div class="fade-in">

    <?php if ($serviceError): ?>
    <div class="mb-4 flex items-start gap-3 px-4 py-3 bg-amber-500/10 border border-amber-500/30 rounded-xl text-amber-400 text-sm">
        <i class="fas fa-exclamation-triangle mt-0.5 shrink-0"></i>
        <div><span class="font-semibold">Service degraded</span> — some features may be limited. <span class="text-slate-500 text-xs"><?php echo htmlspecialchars($serviceError); ?></span></div>
    </div>
    <?php endif; ?>

    <!-- Toolbar -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-5">
        <div>
            <p class="text-xs font-medium text-amber-400 uppercase tracking-wide mb-0.5">Inventory</p>
            <h1 class="text-lg font-bold text-white">Inventory Tracking</h1>
            <p class="text-sm text-slate-500 mt-0.5">Stock levels &amp; adjustments for <span class="text-amber-400"><?php echo htmlspecialchars(get_current_branch_name()); ?></span></p>
        </div>
        <div class="flex gap-2">
            <a href="inventory.php" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-slate-700 border border-slate-600 text-slate-300 text-xs font-medium hover:bg-slate-600 transition-colors">
                <i class="fas fa-warehouse text-xs"></i><span class="hidden sm:inline">Inventory</span>
            </a>
        </div>
    </div>

    <!-- KPI Cards -->
    <div class="grid grid-cols-3 gap-3 mb-5">
        <div class="bg-slate-800/50 border border-slate-700/60 rounded-xl p-3 flex items-center gap-2.5">
            <div class="w-8 h-8 rounded-lg bg-blue-500/10 flex items-center justify-center shrink-0"><i class="fas fa-boxes text-blue-400 text-xs"></i></div>
            <div>
                <div class="text-xl font-bold text-white"><?php echo count($stockItems); ?></div>
                <div class="text-[10px] text-slate-500 uppercase tracking-wide">Products</div>
            </div>
        </div>
        <div class="bg-slate-800/50 border border-slate-700/60 rounded-xl p-3 flex items-center gap-2.5">
            <div class="w-8 h-8 rounded-lg bg-emerald-500/10 flex items-center justify-center shrink-0"><i class="fas fa-cubes text-emerald-400 text-xs"></i></div>
            <div>
                <div class="text-xl font-bold text-white"><?php echo number_format($totalUnits); ?></div>
                <div class="text-[10px] text-slate-500 uppercase tracking-wide">Total Units</div>
            </div>
        </div>
        <div class="bg-slate-800/50 border border-slate-700/60 rounded-xl p-3 flex items-center gap-2.5">
            <div class="w-8 h-8 rounded-lg bg-amber-500/10 flex items-center justify-center shrink-0"><i class="fas fa-exclamation-triangle text-amber-400 text-xs"></i></div>
            <div>
                <div class="text-xl font-bold text-amber-400"><?php echo $lowCount; ?></div>
                <div class="text-[10px] text-slate-500 uppercase tracking-wide">Low Stock</div>
            </div>
        </div>
    </div>

    <!-- Filter + View Tabs -->
    <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-3 mb-4">
        <form method="GET" class="flex flex-col sm:flex-row gap-2">
            <div class="flex-1">
                <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search by product name or SKU..."
                    class="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-1 focus:ring-amber-500">
            </div>
            <div class="flex gap-2">
                <button type="submit" name="view" value="all" class="inline-flex items-center gap-1.5 px-3 py-2 rounded-lg <?php echo $view !== 'low' ? 'bg-amber-500 text-black font-semibold' : 'bg-slate-700 border border-slate-600 text-slate-300'; ?> text-sm transition-colors hover:bg-amber-600">
                    <i class="fas fa-list text-xs"></i> All Stock
                </button>
                <button type="submit" name="view" value="low" class="inline-flex items-center gap-1.5 px-3 py-2 rounded-lg <?php echo $view === 'low' ? 'bg-amber-500 text-black font-semibold' : 'bg-slate-700 border border-slate-600 text-slate-300'; ?> text-sm transition-colors hover:bg-amber-600">
                    <i class="fas fa-exclamation-triangle text-xs"></i> Low Stock
                    <?php if ($lowCount > 0): ?><span class="ml-1 px-1.5 py-0.5 rounded-full bg-red-500/20 text-red-400 text-[10px] font-semibold"><?php echo $lowCount; ?></span><?php endif; ?>
                </button>
                <?php if ($search): ?>
                <a href="inventory_tracking.php" class="inline-flex items-center justify-center px-3 py-2 rounded-lg bg-slate-700 border border-slate-600 text-slate-400 text-sm hover:bg-slate-600 transition-colors">
                    <i class="fas fa-times text-xs"></i>
                </a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <!-- Stock Table -->
    <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl overflow-hidden mb-5">
        <div class="px-4 py-3 border-b border-slate-700/40 flex items-center justify-between">
            <h2 class="text-sm font-semibold text-white flex items-center gap-2">
                <i class="fas fa-<?php echo $view === 'low' ? 'exclamation-triangle text-amber-400' : 'list text-amber-400'; ?>"></i>
                <?php echo $view === 'low' ? 'Low Stock Items' : 'All Stock Levels'; ?>
            </h2>
            <span class="text-xs text-slate-500"><?php echo count($displayItems); ?> items</span>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-xs">
                <thead class="bg-slate-900/50 border-b border-slate-700/60">
                    <tr>
                        <th class="px-3 py-2.5 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Product</th>
                        <th class="px-3 py-2.5 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">SKU</th>
                        <th class="px-3 py-2.5 text-right text-xs font-semibold text-slate-500 uppercase tracking-wider">On Hand</th>
                        <th class="px-3 py-2.5 text-right text-xs font-semibold text-slate-500 uppercase tracking-wider">Reorder At</th>
                        <th class="px-3 py-2.5 text-center text-xs font-semibold text-slate-500 uppercase tracking-wider">Status</th>
                        <?php if (check_permission('inventory.manage') || is_super_admin()): ?>
                        <th class="px-3 py-2.5 text-center text-xs font-semibold text-slate-500 uppercase tracking-wider w-16">Adjust</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-700/40">
                    <?php if (empty($displayItems)): ?>
                    <tr>
                        <td colspan="6" class="px-4 py-14 text-center">
                            <i class="fas fa-boxes text-3xl text-slate-700 mb-3 block"></i>
                            <p class="text-slate-400 font-medium"><?php echo $view === 'low' ? 'No low stock items' : 'No stock data found'; ?></p>
                            <?php if ($search): ?><p class="text-slate-500 text-xs mt-1">Try a different search term.</p><?php endif; ?>
                        </td>
                    </tr>
                    <?php else: foreach ($displayItems as $row):
                        $qty    = (int)($row['quantity_on_hand'] ?? 0);
                        $reord  = (int)($row['reorder_point'] ?? 0);
                        $isLow  = $reord > 0 && $qty <= $reord;
                        $isOut  = $qty <= 0;
                        if ($isOut) {
                            $badge = 'bg-red-500/15 text-red-400 border border-red-500/30';
                            $label = 'Out of Stock';
                        } elseif ($isLow) {
                            $badge = 'bg-amber-500/15 text-amber-400 border border-amber-500/30';
                            $label = 'Low';
                        } else {
                            $badge = 'bg-emerald-500/15 text-emerald-400 border border-emerald-500/30';
                            $label = 'OK';
                        }
                    ?>
                    <tr class="hover:bg-slate-700/20 transition-colors">
                        <td class="px-3 py-2.5 text-slate-200 font-medium"><?php echo htmlspecialchars($row['name'] ?? ''); ?></td>
                        <td class="px-3 py-2.5"><code class="font-mono text-slate-400"><?php echo htmlspecialchars($row['sku'] ?? '—'); ?></code></td>
                        <td class="px-3 py-2.5 text-right font-mono font-semibold <?php echo $isOut ? 'text-red-400' : ($isLow ? 'text-amber-400' : 'text-white'); ?>"><?php echo $qty; ?></td>
                        <td class="px-3 py-2.5 text-right text-slate-500"><?php echo $reord ?: '—'; ?></td>
                        <td class="px-3 py-2.5 text-center">
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold <?php echo $badge; ?>"><?php echo $label; ?></span>
                        </td>
                        <?php if (check_permission('inventory.manage') || is_super_admin()): ?>
                        <td class="px-3 py-2.5 text-center">
                            <button onclick="openAdjust(<?php echo (int)$row['product_id']; ?>, '<?php echo htmlspecialchars(addslashes($row['name'] ?? ''), ENT_QUOTES); ?>', <?php echo $qty; ?>)"
                                class="inline-flex items-center justify-center w-7 h-7 rounded-lg bg-amber-500/10 border border-amber-500/20 text-amber-400 hover:bg-amber-500/20 transition-colors" title="Adjust">
                                <i class="fas fa-pen text-[10px]"></i>
                            </button>
                        </td>
                        <?php endif; ?>
                    </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
        <?php if (!empty($displayItems)): ?>
        <div class="px-4 py-2.5 bg-slate-800/60 border-t border-slate-700/40 text-xs text-slate-500">
            Showing <span class="text-white font-semibold"><?php echo count($displayItems); ?></span> item<?php echo count($displayItems) !== 1 ? 's' : ''; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Adjust Stock Modal -->
<div id="adjustModal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/60">
    <div class="bg-slate-800 border border-slate-700 rounded-xl p-5 w-full max-w-sm mx-4 shadow-2xl">
        <div class="flex items-center gap-3 mb-4">
            <div class="w-9 h-9 rounded-full bg-amber-500/10 border border-amber-500/30 flex items-center justify-center shrink-0">
                <i class="fas fa-pen text-amber-400 text-sm"></i>
            </div>
            <div>
                <h3 class="text-base font-semibold text-white">Adjust Stock</h3>
                <p id="adj-product-name" class="text-xs text-slate-400"></p>
            </div>
        </div>
        <div id="adj-alert" class="hidden mb-3 px-3 py-2 rounded-lg text-xs"></div>
        <?php $inp2 = 'w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-white text-sm focus:outline-none focus:ring-1 focus:ring-amber-500'; ?>
        <div class="space-y-3">
            <div>
                <label class="block text-[10px] font-medium text-slate-500 uppercase tracking-wide mb-1">Current Stock</label>
                <input type="text" id="adj-current" class="<?php echo $inp2; ?>" readonly>
            </div>
            <div>
                <label class="block text-[10px] font-medium text-slate-500 uppercase tracking-wide mb-1">Quantity Change <span class="text-red-400">*</span></label>
                <input type="number" id="adj-quantity" class="<?php echo $inp2; ?>" placeholder="+10 to add, -5 to remove">
            </div>
            <div>
                <label class="block text-[10px] font-medium text-slate-500 uppercase tracking-wide mb-1">Reason <span class="text-red-400">*</span></label>
                <select id="adj-reason" class="<?php echo $inp2; ?>">
                    <option value="">Select Reason</option>
                    <option value="count">Physical Count</option>
                    <option value="loss">Loss / Shrinkage</option>
                    <option value="damage">Damaged Goods</option>
                    <option value="expired">Expired Products</option>
                    <option value="received">Received Purchase Order</option>
                    <option value="returned">Customer Return</option>
                    <option value="transfer_in">Transfer In</option>
                    <option value="transfer_out">Transfer Out</option>
                    <option value="other">Other</option>
                </select>
            </div>
        </div>
        <div class="flex gap-2 mt-4">
            <button onclick="closeAdjust()" class="flex-1 inline-flex items-center justify-center px-3 py-2 rounded-lg bg-slate-700 border border-slate-600 text-slate-300 text-sm font-medium hover:bg-slate-600 transition-colors">Cancel</button>
            <button onclick="saveAdjust()" id="adj-save-btn" class="flex-1 inline-flex items-center justify-center gap-1.5 px-3 py-2 rounded-lg bg-amber-500 text-black text-sm font-semibold hover:bg-amber-600 transition-colors">
                <i class="fas fa-save text-xs"></i> Save
            </button>
        </div>
    </div>
</div>

<!-- Toast -->
<div id="track-toast" class="fixed bottom-4 right-4 z-50 hidden px-4 py-3 rounded-lg text-sm font-medium shadow-2xl border-l-4"></div>

<script>
const CSRF_TOKEN = '<?php echo htmlspecialchars($csrf_token); ?>';
let adjProductId = 0;

function openAdjust(productId, name, currentStock) {
    adjProductId = productId;
    document.getElementById('adj-product-name').textContent = name;
    document.getElementById('adj-current').value = currentStock;
    document.getElementById('adj-quantity').value = '';
    document.getElementById('adj-reason').value = '';
    document.getElementById('adj-alert').classList.add('hidden');
    document.getElementById('adjustModal').classList.remove('hidden');
    setTimeout(() => document.getElementById('adj-quantity').focus(), 50);
}
function closeAdjust() {
    document.getElementById('adjustModal').classList.add('hidden');
}
document.getElementById('adjustModal').addEventListener('click', function(e) {
    if (e.target === this) closeAdjust();
});
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeAdjust();
});

function showToast(msg, type) {
    const t = document.getElementById('track-toast');
    const colors = { success: 'bg-emerald-900/90 text-emerald-300 border-emerald-500', error: 'bg-red-900/90 text-red-300 border-red-500' };
    t.className = 'fixed bottom-4 right-4 z-50 px-4 py-3 rounded-lg text-sm font-medium shadow-2xl border-l-4 ' + (colors[type] || colors.error);
    t.textContent = msg;
    t.classList.remove('hidden');
    setTimeout(() => t.classList.add('hidden'), 3500);
}

function saveAdjust() {
    const qty    = parseInt(document.getElementById('adj-quantity').value, 10);
    const reason = document.getElementById('adj-reason').value;
    const alert  = document.getElementById('adj-alert');

    if (!qty || qty === 0) { showAlert('Enter a non-zero quantity'); return; }
    if (!reason) { showAlert('Select a reason'); return; }

    function showAlert(msg) {
        alert.textContent = msg;
        alert.className = 'mb-3 px-3 py-2 rounded-lg text-xs bg-red-500/15 border border-red-500/30 text-red-400';
        alert.classList.remove('hidden');
    }

    const btn = document.getElementById('adj-save-btn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin text-xs"></i> Saving...';

    const fd = new FormData();
    fd.append('action', 'adjust_inventory');
    fd.append('csrf_token', CSRF_TOKEN);
    fd.append('product_id', adjProductId);
    fd.append('quantity', qty);
    fd.append('reason', reason);
    fd.append('reference_type', 'adjustment');
    fd.append('reference_id', '0');

    fetch('inventory_tracking.php', { method: 'POST', body: fd })
        .then(r => r.text())
        .then(text => {
            let data;
            try { data = JSON.parse(text.replace(/^\uFEFF/, '')); }
            catch(e) { showToast('Server error — check console', 'error'); console.error(text.substring(0, 500)); return; }
            if (data.success) {
                showToast('Stock adjusted successfully', 'success');
                closeAdjust();
                setTimeout(() => location.reload(), 800);
            } else {
                showToast(data.error || 'Adjustment failed', 'error');
            }
        })
        .catch(() => showToast('Network error', 'error'))
        .finally(() => {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-save text-xs"></i> Save';
        });
}
</script>

<?php
$page_content = ob_get_clean();
require_once __DIR__ . '/../layouts/app.php';
?>