<?php
/**
 * Create Return - ADVANCED PURE TAILWIND EDITION
 * Process new return with AI risk assessment, real-time validation
 * URL: http://localhost/JDH_POS/public/pos/create_sell_return.php
 * @version 6.0 - Fully Advanced
 */

require_once __DIR__ . '/../../src/paths.php';
safe_require('auth.php', 'src', true);
require_login();
enforce_permission('sales.returns');

safe_require('db.php', 'src', true);

$page_title = 'Create Return';
ob_start();

$tenant_id = get_current_tenant_id();
$branch_id = get_current_branch_id();
$user_id = get_current_user_id();

$error = '';
$sale = null;
$return_items = [];
$pdo = null;

// Get user role for approval limits
$user_role = 'cashier';
try {
    $pdo_temp = get_db_connection();
    $stmt = $pdo_temp->prepare("SELECT r.name FROM users u LEFT JOIN roles r ON u.role_id = r.id WHERE u.id = ?");
    $stmt->execute([$user_id]);
    $role_data = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($role_data) $user_role = strtolower($role_data['name']);
} catch (Exception $e) {}

$user_approval_limit = 5000;
if ($user_role === 'manager') $user_approval_limit = 50000;
if ($user_role === 'admin' || is_super_admin()) $user_approval_limit = 999999;

// Get sale ID from query string
$sale_id = isset($_GET['sale_id']) ? (int)$_GET['sale_id'] : 0;

// Calculate AI risk score for this sale (if any)
$risk_score = 0;
$risk_level = 'low';
$risk_factors = [];
$customer_history = null;

if ($sale_id) {
    try {
        $pdo = get_db_connection();
        
        // Get sale info
        $stmt = $pdo->prepare("
            SELECT s.*, c.name as customer_name, c.phone as customer_phone, c.loyalty_points, c.email,
                   b.name as branch_name, u.name as cashier_name
            FROM sales s 
            LEFT JOIN customers c ON s.customer_id = c.id 
            LEFT JOIN branches b ON s.branch_id = b.id
            LEFT JOIN users u ON s.user_id = u.id
            WHERE s.id = ? AND s.tenant_id = ? AND s.branch_id = ?
        ");
        $stmt->execute([$sale_id, $tenant_id, $branch_id]);
        $sale = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$sale) {
            $error = "Sale not found";
        } else {
            // Check if already returned
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM returns WHERE sale_id = ? AND tenant_id = ? AND status != 'rejected' AND status != 'voided'");
            $stmt->execute([$sale_id, $tenant_id]);
            if ($stmt->fetchColumn() > 0) {
                $error = "This sale already has a return processed";
            }
            
            // Calculate AI Risk Score
            // Factor 1: Sale amount risk (0-30 points)
            if ($sale['total'] > 50000) {
                $risk_score += 30;
                $risk_factors[] = 'Very high value transaction';
            } elseif ($sale['total'] > 20000) {
                $risk_score += 25;
                $risk_factors[] = 'High value transaction';
            } elseif ($sale['total'] > 10000) {
                $risk_score += 20;
                $risk_factors[] = 'Medium-high value transaction';
            } elseif ($sale['total'] > 5000) {
                $risk_score += 10;
                $risk_factors[] = 'Medium value transaction';
            }
            
            // Factor 2: Customer return history (0-40 points)
            if ($sale['customer_id']) {
                $stmt = $pdo->prepare("
                    SELECT COUNT(*) as return_count, SUM(amount) as return_total, 
                           AVG(amount) as avg_return, MAX(created_at) as last_return
                    FROM returns 
                    WHERE customer_id = ? AND tenant_id = ? AND status = 'completed'
                ");
                $stmt->execute([$sale['customer_id']]);
                $customer_history = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($customer_history && $customer_history['return_count'] > 0) {
                    $points = min(40, $customer_history['return_count'] * 8);
                    $risk_score += $points;
                    $risk_factors[] = "Customer has {$customer_history['return_count']} previous return(s)";
                    
                    // Check recent returns (last 30 days)
                    if ($customer_history['last_return'] && strtotime($customer_history['last_return']) > strtotime('-30 days')) {
                        $risk_score += 15;
                        $risk_factors[] = 'Recent return within 30 days';
                    }
                }
            }
            
            // Factor 3: Item count risk (0-15 points)
            $stmt = $pdo->prepare("SELECT COUNT(*) as item_count, SUM(quantity) as total_qty FROM sale_items WHERE sale_id = ? AND tenant_id = ?");
            $stmt->execute([$sale_id, $tenant_id]);
            $item_stats = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($item_stats && $item_stats['total_qty'] > 10) {
                $risk_score += 15;
                $risk_factors[] = "High quantity order ({$item_stats['total_qty']} items)";
            } elseif ($item_stats && $item_stats['total_qty'] > 5) {
                $risk_score += 8;
                $risk_factors[] = "Multi-item order ({$item_stats['total_qty']} items)";
            }
            
            // Factor 4: Days since purchase (0-15 points)
            $days_since_purchase = (time() - strtotime($sale['created_at'])) / (60 * 60 * 24);
            if ($days_since_purchase <= 1) {
                $risk_score += 0;
            } elseif ($days_since_purchase <= 7) {
                $risk_score += 5;
                $risk_factors[] = "Return within 7 days";
            } elseif ($days_since_purchase <= 30) {
                $risk_score += 10;
                $risk_factors[] = "Return within 30 days";
            } elseif ($days_since_purchase > 30) {
                $risk_score += 15;
                $risk_factors[] = "Return after 30+ days";
            }
            
            // Determine risk level
            if ($risk_score >= 70) $risk_level = 'critical';
            elseif ($risk_score >= 50) $risk_level = 'high';
            elseif ($risk_score >= 25) $risk_level = 'medium';
            else $risk_level = 'low';
        }
        
        // Fetch sale items
        if ($sale) {
            $stmt = $pdo->prepare("
                SELECT si.*, p.name as product_name, p.sku, 
                       COALESCE(i.stock, 0) as current_stock,
                       (SELECT COALESCE(SUM(ri.quantity), 0) FROM return_items ri 
                        JOIN returns r ON ri.return_id = r.id 
                        WHERE ri.sale_item_id = si.id AND r.status NOT IN ('rejected', 'voided')) as already_returned
                FROM sale_items si
                JOIN products p ON si.product_id = p.id
                LEFT JOIN inventory i ON i.product_id = p.id AND i.branch_id = ? AND i.tenant_id = p.tenant_id
                WHERE si.sale_id = ? AND si.tenant_id = ?
            ");
            $stmt->execute([$branch_id, $sale_id, $tenant_id]);
            $return_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Calculate available quantity for each item
            foreach ($return_items as &$item) {
                $item['available_qty'] = max(0, $item['quantity'] - ($item['already_returned'] ?? 0));
                $item['is_available'] = $item['available_qty'] > 0;
            }
        }
    } catch (PDOException $e) {
        $error = "Database error: " . $e->getMessage();
    }
}

// Process return submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = "Invalid security token. Please try again.";
    } else {
        $sale_id = (int)$_POST['sale_id'];
        $return_reason = trim($_POST['return_reason'] ?? '');
        $return_type = $_POST['return_type'] ?? 'sales';
        $notes = trim($_POST['notes'] ?? '');
        $items = $_POST['items'] ?? [];
        
        if (!$sale_id || empty($items)) {
            $error = "Please select at least one item to return";
        } elseif (empty($return_reason)) {
            $error = "Please provide a return reason";
        } else {
            try {
                $pdo->beginTransaction();
                
                $total_amount = 0;
                $valid_items = [];
                
                foreach ($items as $item_id => $item_data) {
                    if (isset($item_data['quantity']) && $item_data['quantity'] > 0) {
                        $stmt = $pdo->prepare("
                            SELECT si.product_id, si.price, si.quantity, 
                                   COALESCE(SUM(ri.quantity), 0) as returned_qty
                            FROM sale_items si
                            LEFT JOIN return_items ri ON ri.sale_item_id = si.id
                            LEFT JOIN returns r ON ri.return_id = r.id AND r.status NOT IN ('rejected', 'voided')
                            WHERE si.id = ? AND si.sale_id = ?
                            GROUP BY si.id
                        ");
                        $stmt->execute([$item_id, $sale_id]);
                        $item = $stmt->fetch(PDO::FETCH_ASSOC);
                        
                        $available_qty = $item['quantity'] - $item['returned_qty'];
                        
                        if ($item && $item_data['quantity'] <= $available_qty) {
                            $refund_amount = $item['price'] * $item_data['quantity'];
                            $total_amount += $refund_amount;
                            $valid_items[$item_id] = [
                                'product_id' => $item['product_id'],
                                'quantity' => $item_data['quantity'],
                                'price' => $item['price'],
                                'subtotal' => $refund_amount
                            ];
                        }
                    }
                }
                
                if (empty($valid_items)) {
                    throw new Exception("No valid items selected for return");
                }
                
                // Generate return number
                $return_number = 'R' . date('Ymd') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
                
                // Insert return
                $stmt = $pdo->prepare("
                    INSERT INTO returns (return_number, return_type, sale_id, customer_id, branch_id, 
                    processed_by, reason, amount, status, notes, tenant_id, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?, ?, NOW())
                ");
                
                $customer_id = $sale['customer_id'] ?? null;
                $stmt->execute([
                    $return_number, $return_type, $sale_id, $customer_id, $branch_id,
                    $user_id, $return_reason, $total_amount, $notes, $tenant_id
                ]);
                
                $return_id = $pdo->lastInsertId();
                
                // Insert return items
                foreach ($valid_items as $item_id => $item) {
                    $stmt = $pdo->prepare("
                        INSERT INTO return_items (return_id, product_id, sale_item_id, quantity, unit_price, subtotal, tenant_id)
                        VALUES (?, ?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([
                        $return_id, $item['product_id'], $item_id, $item['quantity'],
                        $item['price'], $item['subtotal'], $tenant_id
                    ]);
                }
                
                // Log activity
                $stmt = $pdo->prepare("
                    INSERT INTO activity_logs (branch_id, user_id, action, description, meta, tenant_id, created_at)
                    VALUES (?, ?, 'return_created', 'Created return', ?, ?, NOW())
                ");
                $meta = json_encode([
                    'return_id' => $return_id, 
                    'return_number' => $return_number, 
                    'amount' => $total_amount,
                    'risk_level' => $risk_level,
                    'risk_score' => $risk_score
                ]);
                $stmt->execute([$branch_id, $user_id, $meta, $tenant_id]);
                
                $pdo->commit();
                
                $_SESSION['success_message'] = "Return #{$return_number} created successfully";
                header("Location: view_sell_return.php?id={$return_id}");
                exit;
                
            } catch (Exception $e) {
                $pdo->rollBack();
                $error = "Failed to process return: " . $e->getMessage();
            }
        }
    }
}

$csrf_token = generate_csrf_token();

$currency_symbol = 'KSh';
try {
    $pdo = get_db_connection();
    $stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = 'currency' AND tenant_id = ?");
    $stmt->execute([(int)$tenant_id]);
    $curr = $stmt->fetchColumn();
    if ($curr) $currency_symbol = $curr;
} catch (Exception $e) {}
?>

<style>
    @keyframes shake {
        0%, 100% { transform: translateX(0); }
        25% { transform: translateX(-5px); }
        75% { transform: translateX(5px); }
    }
    @keyframes pulse-anim {
        0%, 100% { opacity: 1; }
        50% { opacity: 0.5; }
    }
    .animate-shake { animation: shake 0.4s ease-in-out; }
    .animate-pulse-slow { animation: pulse-anim 2s ease-in-out infinite; }
    .risk-low { background: rgba(16,185,129,0.12); color: #10b981; border-color: rgba(16,185,129,0.3); }
    .risk-medium { background: rgba(245,158,11,0.12); color: #f59e0b; border-color: rgba(245,158,11,0.3); }
    .risk-high { background: rgba(239,68,68,0.12); color: #ef4444; border-color: rgba(239,68,68,0.3); }
    .risk-critical { background: rgba(139,0,0,0.25); color: #ff6b6b; border-color: rgba(255,0,0,0.3); animation: pulse-anim 2s ease-in-out infinite; }
    .quantity-input:focus { transform: scale(1.02); border-color: #fbbf24; }
</style>

<div class="space-y-4">
    
    <!-- Toast Container -->
    <div id="toast-container" class="fixed bottom-5 right-5 z-50 space-y-2"></div>
    
    <!-- Flash Message -->
    <?php if (isset($_SESSION['success_message'])): ?>
    <div class="p-3 bg-emerald-500/10 border border-emerald-500/30 rounded-lg text-emerald-400 text-xs flex justify-between items-center animate-slide-in">
        <span><i class="fas fa-check-circle mr-2"></i> <?php echo htmlspecialchars($_SESSION['success_message']); unset($_SESSION['success_message']); ?></span>
        <button onclick="this.parentElement.remove()" class="text-slate-500 hover:text-slate-300">&times;</button>
    </div>
    <?php endif; ?>
    
    <!-- Header -->
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3">
        <div>
            <div class="flex items-center gap-2 text-xs text-amber-400 mb-0.5">
                <i class="fas fa-undo-alt text-xs"></i>
                <span class="font-semibold tracking-wider uppercase">Returns Management</span>
            </div>
            <h1 class="text-xl font-bold text-white">Create Return</h1>
            <p class="text-sm text-slate-500 mt-0.5">Process customer return with risk assessment</p>
        </div>
        <div class="flex gap-2">
            <a href="select_sale_for_return.php" 
               class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-blue-500/10 border border-blue-500/30 rounded-lg text-blue-400 text-sm font-medium hover:bg-blue-500/20 transition-colors">
                <i class="fas fa-store text-xs"></i> Select Sale
            </a>
            <a href="list_sell_return.php" 
               class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-slate-800 border border-slate-700 rounded-lg text-slate-400 text-sm font-medium hover:bg-slate-700 hover:text-white transition-colors">
                <i class="fas fa-list text-xs"></i> All Returns
            </a>
        </div>
    </div>

    <!-- Error Alert -->
    <?php if ($error): ?>
    <div class="p-3 bg-red-500/10 border border-red-500/30 rounded-xl text-red-400 text-sm flex justify-between items-center animate-shake">
        <span><i class="fas fa-exclamation-circle mr-2"></i> <?php echo htmlspecialchars($error); ?></span>
        <button onclick="this.parentElement.remove()" class="text-slate-500 hover:text-slate-300 ml-3">&times;</button>
    </div>
    <?php endif; ?>

    <?php if (!$sale_id || !$sale): ?>
        <!-- Sale Search Form -->
        <div class="bg-slate-800/60 border border-slate-700/60 rounded-xl overflow-hidden">
            <div class="px-4 py-2.5 border-b border-slate-700/50 bg-slate-800/40 flex justify-between items-center">
                <h3 class="text-xs font-semibold text-amber-400 flex items-center gap-2">
                    <i class="fas fa-search"></i> Find Sale for Return
                </h3>
                <a href="select_sale_for_return.php" class="text-[10px] text-blue-400 hover:text-blue-300">Browse All →</a>
            </div>
            <div class="p-4 space-y-3">
                <div>
                    <label for="sale_search" class="block text-xs font-medium text-slate-400 mb-1">Invoice Number / Barcode</label>
                    <div class="flex gap-2">
                        <input type="text" id="sale_search" placeholder="e.g., INV-202606-0001" 
                               class="flex-1 px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm placeholder-slate-600 focus:outline-none focus:ring-1 focus:ring-amber-500/60 transition-colors">
                        <button onclick="searchSale()" class="px-3 py-2 bg-amber-500/15 border border-amber-500/30 rounded-lg text-amber-400 text-sm font-medium hover:bg-amber-500/25 transition-colors">
                            <i class="fas fa-search mr-1"></i> Search
                        </button>
                    </div>
                </div>
                <div id="sale-result" class="hidden"></div>
                <div id="recent-sales" class="hidden">
                    <div class="border-t border-slate-700/50 pt-2">
                        <p class="text-xs text-slate-500 mb-1.5">Recent sales:</p>
                        <div id="recent-sales-list" class="space-y-1"></div>
                    </div>
                </div>
            </div>
        </div>
        
        <script>
        const CURRENCY_SEARCH = '<?php echo $currency_symbol; ?>';
        const BRANCH_ID = <?php echo $branch_id; ?>;
        
        function showToastMsg(message, type) {
            const container = document.getElementById('toast-container');
            const colors = { warning: 'bg-amber-500/90', error: 'bg-red-500/90', success: 'bg-emerald-500/90' };
            const icons = { warning: 'fa-exclamation-triangle', error: 'fa-times-circle', success: 'fa-check-circle' };
            const toast = document.createElement('div');
            toast.className = `${colors[type]} text-white px-3 py-1.5 rounded-lg shadow-lg text-xs flex items-center gap-2 animate-slide-in mb-1`;
            toast.innerHTML = `<i class="fas ${icons[type]}"></i> ${message}`;
            container.appendChild(toast);
            setTimeout(() => toast.remove(), 3000);
        }
        
        document.getElementById('sale_search')?.addEventListener('keypress', function(e) { if (e.key === 'Enter') searchSale(); });
        
        function loadRecentSales() {
            fetch(`../ajax/recent_sales.php?branch_id=${BRANCH_ID}&limit=5`)
                .then(res => res.json())
                .then(data => {
                    if (data.success && data.sales && data.sales.length) {
                        const recentDiv = document.getElementById('recent-sales');
                        const recentList = document.getElementById('recent-sales-list');
                        recentList.innerHTML = '';
                        data.sales.forEach(sale => {
                            recentList.innerHTML += `
                                <div class="bg-slate-800/50 border border-slate-700/40 rounded-lg px-3 py-2 flex justify-between items-center">
                                    <span class="font-mono text-xs text-amber-400">${sale.invoice_number}</span>
                                    <span class="text-xs text-slate-500">${sale.customer_name || 'Walk-in'}</span>
                                    <a href="create_sell_return.php?sale_id=${sale.id}" class="text-xs text-amber-400 hover:underline font-medium">Select →</a>
                                </div>
                            `;
                        });
                        recentDiv.classList.remove('hidden');
                    }
                }).catch(err => console.error(err));
        }
        
        function searchSale() {
            const term = document.getElementById('sale_search').value.trim();
            if (!term) { showToastMsg('Enter invoice number', 'warning'); return; }
            const resultDiv = document.getElementById('sale-result');
            resultDiv.classList.remove('hidden');
            resultDiv.innerHTML = '<div class="mt-2 p-3 bg-slate-800/60 border border-slate-700/40 rounded-xl text-center text-sm text-slate-400"><i class="fas fa-spinner fa-spin mr-1"></i> Searching...</div>';
            fetch(`../ajax/search_sale.php?invoice=${encodeURIComponent(term)}&branch_id=${BRANCH_ID}`)
                .then(res => res.json())
                .then(data => {
                    if (data.success && data.sale) {
                        resultDiv.innerHTML = `
                            <div class="mt-2 bg-emerald-500/10 border border-emerald-500/30 rounded-xl p-3">
                                <div class="flex items-center gap-2 text-emerald-400 text-sm mb-2 font-medium"><i class="fas fa-check-circle"></i> Sale Found</div>
                                <div class="grid grid-cols-2 gap-2 text-xs">
                                    <div><span class="text-slate-500">Invoice:</span><br><span class="text-white font-mono">${escapeHtml(data.sale.invoice_number)}</span></div>
                                    <div><span class="text-slate-500">Customer:</span><br><span class="text-white">${escapeHtml(data.sale.customer_name || 'Walk-in')}</span></div>
                                    <div><span class="text-slate-500">Date:</span><br><span class="text-white">${data.sale.date || 'N/A'}</span></div>
                                    <div><span class="text-slate-500">Total:</span><br><span class="text-amber-400 font-bold">${CURRENCY_SEARCH} ${data.sale.total}</span></div>
                                </div>
                                <a href="create_sell_return.php?sale_id=${data.sale.id}" class="inline-block mt-2 text-sm text-amber-400 hover:underline font-medium">Process Return →</a>
                            </div>
                        `;
                    } else {
                        resultDiv.innerHTML = `<div class="mt-2 p-3 bg-red-500/10 border border-red-500/30 rounded-xl text-red-400 text-sm">${data.error || 'Sale not found'}</div>`;
                    }
                }).catch(err => { resultDiv.innerHTML = '<div class="mt-2 p-3 bg-red-500/10 border border-red-500/30 rounded-xl text-red-400 text-sm">Network error</div>'; });
        }
        
        function escapeHtml(str) { if (!str) return ''; return String(str).replace(/[&<>]/g, m => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;' }[m])); }
        
        loadRecentSales();
        </script>
        
    <?php else: ?>
        
        <!-- Risk Assessment Banner -->
        <div class="bg-slate-800/60 border border-slate-700/60 rounded-xl p-3">
            <div class="flex items-center justify-between flex-wrap gap-2">
                <div class="flex items-center gap-2.5">
                    <div class="w-9 h-9 rounded-lg bg-amber-500/10 flex items-center justify-center shrink-0">
                        <i class="fas fa-robot text-amber-400"></i>
                    </div>
                    <div>
                        <p class="text-xs text-slate-500">Risk Assessment</p>
                        <div class="flex items-center gap-1.5">
                            <span class="text-xs text-white">Risk:</span>
                            <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded-full text-[10px] font-medium risk-<?php echo $risk_level; ?>">
                                <i class="fas fa-<?php echo $risk_level === 'critical' ? 'skull-crossbones' : ($risk_level === 'high' ? 'exclamation-triangle' : ($risk_level === 'medium' ? 'chart-line' : 'leaf')); ?>"></i>
                                <?php echo ucfirst($risk_level); ?> (<?php echo min(100, $risk_score); ?>%)
                            </span>
                        </div>
                    </div>
                </div>
                <?php if (!empty($risk_factors)): ?>
                <div class="text-xs text-slate-500 max-w-md">
                    <i class="fas fa-info-circle mr-1 text-slate-600"></i> <?php echo implode(' &bull; ', array_slice($risk_factors, 0, 2)); ?>
                </div>
                <?php endif; ?>
            </div>
            <?php if ($risk_level === 'critical'): ?>
            <div class="mt-2 pt-2 border-t border-red-500/30 text-xs text-red-400 flex items-center gap-1.5">
                <i class="fas fa-gavel"></i> Requires manager approval due to high risk.
            </div>
            <?php endif; ?>
        </div>
        
        <!-- Sale Information Card -->
        <div class="bg-slate-800/60 border border-slate-700/60 rounded-xl overflow-hidden">
            <div class="px-4 py-2.5 border-b border-slate-700/50 bg-slate-800/40 flex items-center gap-2">
                <i class="fas fa-receipt text-amber-400 text-xs"></i>
                <h3 class="text-xs font-semibold text-amber-400 uppercase tracking-wider">Sale Information</h3>
                <span class="ml-auto text-xs text-slate-500">ID: #<?php echo $sale_id; ?></span>
            </div>
            <div class="p-4">
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                    <div>
                        <p class="text-xs text-slate-500 uppercase tracking-wider">Invoice</p>
                        <p class="text-xs font-mono text-amber-400 mt-0.5"><?php echo htmlspecialchars($sale['invoice_number']); ?></p>
                    </div>
                    <div>
                        <p class="text-xs text-slate-500 uppercase tracking-wider">Customer</p>
                        <p class="text-xs text-slate-200 mt-0.5"><?php echo htmlspecialchars($sale['customer_name'] ?? 'Walk-in Customer'); ?></p>
                        <?php if ($sale['customer_phone']): ?>
                        <p class="text-xs text-slate-500"><i class="fas fa-phone text-[10px] mr-1"></i><?php echo htmlspecialchars($sale['customer_phone']); ?></p>
                        <?php endif; ?>
                    </div>
                    <div>
                        <p class="text-xs text-slate-500 uppercase tracking-wider">Date</p>
                        <p class="text-xs text-slate-200 mt-0.5"><?php echo date('d M Y', strtotime($sale['created_at'])); ?></p>
                        <p class="text-xs text-slate-500"><?php echo date('H:i', strtotime($sale['created_at'])); ?></p>
                    </div>
                    <div>
                        <p class="text-xs text-slate-500 uppercase tracking-wider">Total</p>
                        <p class="text-sm font-bold text-emerald-400 mt-0.5"><?php echo $currency_symbol . ' ' . number_format($sale['total'], 2); ?></p>
                    </div>
                </div>
                <?php if ($customer_history && $customer_history['return_count'] > 0): ?>
                <div class="mt-3 pt-2 border-t border-slate-700/50 text-xs text-slate-500">
                    <i class="fas fa-history mr-1"></i> History: <?php echo $customer_history['return_count']; ?> return(s), <?php echo $currency_symbol . ' ' . number_format($customer_history['return_total'], 0); ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Return Form -->
        <form method="POST" class="bg-slate-800/60 border border-slate-700/60 rounded-xl overflow-hidden" id="return-form">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
            <input type="hidden" name="sale_id" value="<?php echo $sale_id; ?>">
            
            <div class="px-4 py-2.5 border-b border-slate-700/50 bg-slate-800/40 flex justify-between items-center">
                <h3 class="text-xs font-semibold text-amber-400 uppercase tracking-wider flex items-center gap-2">
                    <i class="fas fa-boxes"></i> Select Items to Return
                </h3>
                <span class="text-xs text-slate-500"><?php echo count($return_items); ?> item(s)</span>
            </div>
            
            <div class="p-0 overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-slate-900/50">
                        <tr>
                            <th class="text-left px-3 py-2.5 text-xs font-medium text-slate-500 uppercase tracking-wider w-10">Sel</th>
                            <th class="text-left px-3 py-2.5 text-xs font-medium text-slate-500 uppercase tracking-wider">Product</th>
                            <th class="text-right px-3 py-2.5 text-xs font-medium text-slate-500 uppercase tracking-wider">Price</th>
                            <th class="text-center px-3 py-2.5 text-xs font-medium text-slate-500 uppercase tracking-wider">Avail</th>
                            <th class="text-center px-3 py-2.5 text-xs font-medium text-slate-500 uppercase tracking-wider">Qty</th>
                            <th class="text-right px-3 py-2.5 text-xs font-medium text-slate-500 uppercase tracking-wider">Subtotal</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-700/40">
                        <?php foreach ($return_items as $item): ?>
                        <?php $is_available = $item['available_qty'] > 0; ?>
                        <tr class="hover:bg-slate-700/20 transition-colors <?php echo !$is_available ? 'opacity-40' : ''; ?>">
                            <td class="px-3 py-2.5">
                                <?php if ($is_available): ?>
                                <input type="checkbox" class="item-checkbox w-4 h-4 rounded border-slate-600 accent-amber-400"
                                       data-price="<?php echo $item['price']; ?>"
                                       data-max="<?php echo $item['available_qty']; ?>"
                                       data-item-id="<?php echo $item['id']; ?>"
                                       onchange="toggleItem(this, <?php echo $item['id']; ?>)">
                                <?php else: ?>
                                <span class="text-slate-600 text-xs">sold</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-3 py-2.5">
                                <div class="text-xs font-medium text-slate-200"><?php echo htmlspecialchars($item['product_name']); ?></div>
                                <div class="text-xs text-slate-500 font-mono">SKU: <?php echo htmlspecialchars($item['sku'] ?? 'N/A'); ?></div>
                            </td>
                            <td class="px-3 py-2.5 text-right text-xs text-slate-300"><?php echo $currency_symbol . ' ' . number_format($item['price'], 2); ?></td>
                            <td class="px-3 py-2.5 text-center">
                                <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium <?php echo $item['available_qty'] <= 0 ? 'bg-red-500/15 text-red-400' : 'bg-emerald-500/15 text-emerald-400'; ?>">
                                    <?php echo $item['available_qty']; ?>
                                </span>
                            </td>
                            <td class="px-3 py-2.5 text-center">
                                <?php if ($is_available): ?>
                                <input type="number" name="items[<?php echo $item['id']; ?>][quantity]" 
                                       class="item-quantity hidden w-16 px-2 py-1 bg-slate-900 border border-slate-700 rounded-lg text-white text-xs text-center focus:outline-none focus:ring-1 focus:ring-amber-500/60 quantity-input transition-all"
                                       data-id="<?php echo $item['id']; ?>"
                                       min="1" max="<?php echo $item['available_qty']; ?>" value="1"
                                       onchange="updateSubtotal(this, <?php echo $item['price']; ?>, <?php echo $item['id']; ?>)">
                                <?php else: ?>
                                <span class="text-xs text-slate-600">—</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-3 py-2.5 text-right">
                                <span class="subtotal-<?php echo $item['id']; ?> text-xs font-medium text-slate-500"><?php echo $currency_symbol; ?> 0.00</span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Return Details -->
            <div class="border-t border-slate-700/50 p-4 space-y-3">
                <h3 class="text-xs font-semibold text-amber-400 uppercase tracking-wider flex items-center gap-2">
                    <i class="fas fa-info-circle"></i> Return Details
                </h3>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-medium text-slate-400 mb-1">Return Reason *</label>
                        <select name="return_reason" required class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm focus:outline-none focus:ring-1 focus:ring-amber-500/60 transition-colors">
                            <option value="">Select reason...</option>
                            <option value="wrong_item">Wrong item sent</option>
                            <option value="damaged">Damaged/Defective product</option>
                            <option value="customer_changed_mind">Customer changed mind</option>
                            <option value="expired">Product expired</option>
                            <option value="quality_issue">Quality issue</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    
                    <div>
                        <label class="block text-xs font-medium text-slate-400 mb-1">Return Type</label>
                        <select name="return_type" class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm focus:outline-none focus:ring-1 focus:ring-amber-500/60 transition-colors">
                            <option value="sales">Sales Return</option>
                            <option value="purchase">Purchase Return</option>
                        </select>
                    </div>
                </div>
                
                <div>
                    <label class="block text-xs font-medium text-slate-400 mb-1">Notes</label>
                    <textarea name="notes" rows="2" class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm focus:outline-none focus:ring-1 focus:ring-amber-500/60 placeholder-slate-600 resize-none transition-colors" 
                              placeholder="Additional information..."></textarea>
                </div>
                
                <!-- Total Refund -->
                <div class="bg-slate-900/60 rounded-xl p-3 border border-slate-700/50">
                    <div class="flex justify-between items-center">
                        <div>
                            <span class="text-xs text-slate-500 uppercase tracking-wider">Total Refund</span>
                            <p class="text-xs text-slate-600 mt-0.5">Amount to be refunded</p>
                        </div>
                        <span id="total-amount" class="text-xl font-bold text-amber-400"><?php echo $currency_symbol; ?> 0.00</span>
                    </div>
                </div>
            </div>
            
            <!-- Actions -->
            <div class="border-t border-slate-700/50 px-4 py-3 flex gap-2">
                <button type="submit" id="submit-btn" class="flex-1 inline-flex items-center justify-center gap-1.5 px-4 py-2 bg-amber-500/15 border border-amber-500/30 rounded-lg text-amber-400 text-sm font-medium hover:bg-amber-500/25 transition-colors">
                    <i class="fas fa-check text-xs"></i> Process Return
                </button>
                <a href="list_sell_return.php" class="flex-1 inline-flex items-center justify-center gap-1.5 px-4 py-2 bg-slate-700 border border-slate-600 rounded-lg text-slate-300 text-sm font-medium hover:bg-slate-600 text-center transition-colors">
                    <i class="fas fa-times text-xs"></i> Cancel
                </a>
            </div>
        </form>
    <?php endif; ?>
</div>

<script>
const CURRENCY = '<?php echo $currency_symbol; ?>';
const RISK_LEVEL = '<?php echo $risk_level; ?>';
const USER_APPROVAL_LIMIT = <?php echo $user_approval_limit; ?>;

function showToastMsg(message, type) {
    const container = document.getElementById('toast-container');
    const colors = { warning: 'bg-amber-500/90', error: 'bg-red-500/90', success: 'bg-emerald-500/90' };
    const icons = { warning: 'fa-exclamation-triangle', error: 'fa-times-circle', success: 'fa-check-circle' };
    const toast = document.createElement('div');
    toast.className = `${colors[type]} text-white px-3 py-1.5 rounded-lg shadow-lg text-xs flex items-center gap-2 animate-slide-in mb-1`;
    toast.innerHTML = `<i class="fas ${icons[type]}"></i> ${message}`;
    container.appendChild(toast);
    setTimeout(() => toast.remove(), 3000);
}

function toggleItem(checkbox, itemId) {
    const qtyInput = document.querySelector(`.item-quantity[data-id="${itemId}"]`);
    if (checkbox.checked) {
        qtyInput.classList.remove('hidden');
        qtyInput.classList.add('inline-block');
        updateSubtotal(qtyInput, parseFloat(checkbox.dataset.price), itemId);
    } else {
        qtyInput.classList.add('hidden');
        qtyInput.classList.remove('inline-block');
        qtyInput.value = '1';
        document.querySelector(`.subtotal-${itemId}`).textContent = `${CURRENCY} 0.00`;
        updateTotal();
    }
}

function updateSubtotal(input, price, itemId) {
    let qty = parseInt(input.value) || 0;
    const max = parseInt(input.max) || 0;
    if (qty > max) {
        input.classList.add('animate-shake');
        setTimeout(() => input.classList.remove('animate-shake'), 400);
        input.value = max;
        qty = max;
        showToastMsg(`Maximum quantity is ${max}`, 'warning');
    }
    if (qty < 1) { input.value = 1; qty = 1; }
    const subtotal = qty * price;
    document.querySelector(`.subtotal-${itemId}`).textContent = `${CURRENCY} ${subtotal.toFixed(2)}`;
    updateTotal();
}

function updateTotal() {
    let total = 0;
    let selected = 0;
    document.querySelectorAll('.item-checkbox:checked').forEach(cb => {
        const row = cb.closest('tr');
        const qtyInput = row.querySelector('.item-quantity');
        if (qtyInput && qtyInput.value) {
            total += parseFloat(cb.dataset.price) * parseInt(qtyInput.value);
            selected++;
        }
    });
    const totalEl = document.getElementById('total-amount');
    totalEl.innerHTML = `${CURRENCY} ${total.toFixed(2)}`;
    if (total > 10000) {
        totalEl.classList.add('text-red-400', 'animate-pulse-slow');
        totalEl.classList.remove('text-amber-400');
    } else {
        totalEl.classList.remove('text-red-400', 'animate-pulse-slow');
        totalEl.classList.add('text-amber-400');
    }
    const submitBtn = document.getElementById('submit-btn');
    if (RISK_LEVEL === 'critical' && selected > 0 && total > 0) {
        submitBtn.classList.add('animate-pulse-slow', 'bg-red-500/20', 'border-red-500/50');
        submitBtn.classList.remove('bg-amber-500/15', 'border-amber-500/30');
    } else {
        submitBtn.classList.remove('animate-pulse-slow', 'bg-red-500/20', 'border-red-500/50');
        submitBtn.classList.add('bg-amber-500/15', 'border-amber-500/30');
    }
}

document.getElementById('return-form')?.addEventListener('submit', function(e) {
    if (document.querySelectorAll('.item-checkbox:checked').length === 0) {
        e.preventDefault();
        showToastMsg('Select at least one item', 'warning');
        return false;
    }
    const reason = document.querySelector('select[name="return_reason"]').value;
    if (!reason) {
        e.preventDefault();
        showToastMsg('Select a return reason', 'warning');
        return false;
    }
    const total = parseFloat(document.getElementById('total-amount').innerHTML.replace(/[^0-9.-]/g, '')) || 0;
    if (total > USER_APPROVAL_LIMIT && !confirm(`⚠️ Total (${CURRENCY} ${total.toFixed(2)}) exceeds your approval limit (${CURRENCY} ${USER_APPROVAL_LIMIT.toFixed(2)}). Continue?`)) {
        e.preventDefault();
        return false;
    }
    if (RISK_LEVEL === 'critical' && !confirm(`⚠️ HIGH RISK RETURN\n\nRisk: ${RISK_LEVEL.toUpperCase()}\nTotal: ${CURRENCY} ${total.toFixed(2)}\n\nContinue?`)) {
        e.preventDefault();
        return false;
    }
});

setTimeout(() => { const flash = document.querySelector('.fixed.top-20.right-4'); if (flash) flash.style.display = 'none'; }, 4000);
</script>

<?php
$page_content = ob_get_clean();
require_once __DIR__ . '/../../layouts/app.php';
?>