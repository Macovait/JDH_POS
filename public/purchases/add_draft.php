<?php
/**
 * Add Draft Sale - Create and save draft sales
 * Modern UI with full error handling and validation
 */

require_once __DIR__ . '/../../src/paths.php';
safe_require('auth.php', 'src', true);
safe_require('db.php', 'src', true);
safe_require('functions.php', 'src', true);
require_login();

// Check permission
if (!check_permission('sales.create')) {
    enforce_permission('sales.create');
}

$pdo = get_db_connection();

// Get current user info
$user_id = (int) ($_SESSION['user']['id'] ?? 0);
$user_name = htmlspecialchars($_SESSION['user']['name'] ?? 'User');
$branch_id = (int) ($_SESSION['user']['branch_id'] ?? 1);
$tenant_id = get_current_tenant_id() ?: 1;

// Initialize variables
$error = '';
$success = '';
$debug_info = [];

// Get categories for filter
$categories = [];
try {
    $stmt = $pdo->prepare("SELECT id, name, color FROM categories WHERE tenant_id = ? AND status = 'active' ORDER BY name");
    $stmt->execute([$tenant_id]);
    $categories = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Error fetching categories: " . $e->getMessage());
    $categories = [];
}

// Get customers for dropdown
$customers = [];
try {
    // Check if credit_limit column exists
    $check_column = $pdo->query("SHOW COLUMNS FROM customers LIKE 'credit_limit'");
    $has_credit_limit = $check_column->rowCount() > 0;

    if ($has_credit_limit) {
        $stmt = $pdo->prepare("SELECT id, name, phone, email, credit_limit FROM customers WHERE tenant_id = ? AND (status = 1 OR status IS NULL) ORDER BY name");
    } else {
        $stmt = $pdo->prepare("SELECT id, name, phone, email, 0 as credit_limit FROM customers WHERE tenant_id = ? AND (status = 1 OR status IS NULL) ORDER BY name");
    }
    $stmt->execute([$tenant_id]);
    $customers = $stmt->fetchAll();
    $debug_info['customers_found'] = count($customers);
} catch (PDOException $e) {
    error_log("Error fetching customers: " . $e->getMessage());
    $debug_info['customer_error'] = $e->getMessage();
    // Fallback query without credit_limit
    try {
        $stmt = $pdo->prepare("SELECT id, name, phone, email FROM customers WHERE tenant_id = ? AND (status = 1 OR status IS NULL) ORDER BY name");
        $stmt->execute([$tenant_id]);
        $customers = $stmt->fetchAll();
    } catch (PDOException $e2) {
        error_log("Fallback customer query failed: " . $e2->getMessage());
        $customers = [];
    }
}

// Get products
$products = [];
try {
    $stmt = $pdo->prepare("
        SELECT p.*, 
               COALESCE(i.stock, 0) as stock,
               c.name as category_name,
               c.color as category_color
        FROM products p 
        LEFT JOIN categories c ON p.category_id = c.id
        LEFT JOIN inventory i ON p.id = i.product_id AND i.branch_id = ? AND i.tenant_id = ?
        WHERE p.tenant_id = ? AND p.deleted_at IS NULL AND (p.active = 1 OR p.active IS NULL)
        ORDER BY p.name
    ");
    $stmt->execute([$branch_id, $tenant_id, $tenant_id]);
    $products = $stmt->fetchAll();
    $debug_info['products_found'] = count($products);
} catch (PDOException $e) {
    error_log("Error fetching products: " . $e->getMessage());
    $debug_info['product_error'] = $e->getMessage();

    // Fallback query without inventory join
    try {
        $stmt = $pdo->query("
            SELECT p.*, 0 as stock, c.name as category_name
            FROM products p 
            LEFT JOIN categories c ON p.category_id = c.id
            WHERE p.tenant_id = ? AND p.deleted_at IS NULL
            ORDER BY p.name
        ");
        $stmt->execute([$tenant_id]);
        $products = $stmt->fetchAll();
    } catch (PDOException $e2) {
        error_log("Fallback product query failed: " . $e2->getMessage());
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token. Please try again.';
    } else {
    try {
        $customer_id = !empty($_POST['customer_id']) ? (int) $_POST['customer_id'] : null;
        $notes = trim($_POST['notes'] ?? '');
        $items = isset($_POST['items']) ? json_decode($_POST['items'], true) : [];

        $debug_info['post_data'] = $_POST;
        $debug_info['items_decoded'] = $items;

        if (empty($items)) {
            throw new Exception('No items in draft sale');
        }

        // Calculate totals
        $subtotal = 0;
        foreach ($items as $item) {
            $subtotal += (float) ($item['price'] ?? 0) * (int) ($item['quantity'] ?? 1);
        }

        $discount = (float) ($_POST['discount'] ?? 0);
        $discount_type = $_POST['discount_type'] ?? 'fixed';

        if ($discount_type === 'percent') {
            $discount_amount = $subtotal * ($discount / 100);
        } else {
            $discount_amount = min($discount, $subtotal);
        }

        $tax_rate = (float) ($_POST['tax_rate'] ?? 0);
        $tax_exempt = isset($_POST['tax_exempt']) && $_POST['tax_exempt'] === 'on';

        $after_discount = $subtotal - $discount_amount;
        $tax_amount = $tax_exempt ? 0 : $after_discount * ($tax_rate / 100);
        $total = $after_discount + $tax_amount;

        // Begin transaction
        $pdo->beginTransaction();

        // Insert draft sale
        $stmt = $pdo->prepare("
            INSERT INTO sales (
                tenant_id, branch_id, user_id, customer_id, 
                subtotal, discount, discount_type, discount_amount,
                tax_rate, tax_exempt, tax_amount,
                total, notes, status, created_at
            ) VALUES (
                ?, ?, ?, ?,
                ?, ?, ?, ?,
                ?, ?, ?,
                ?, ?, 'draft', NOW()
            )
        ");

        $stmt->execute([
            $tenant_id,
            $branch_id,
            $user_id,
            $customer_id,
            $subtotal,
            $discount,
            $discount_type,
            $discount_amount,
            $tax_rate,
            $tax_exempt ? 1 : 0,
            $tax_amount,
            $total,
            $notes
        ]);

        $sale_id = $pdo->lastInsertId();

        // Insert sale items
        $stmt = $pdo->prepare("
            INSERT INTO sale_items (
                tenant_id, sale_id, product_id, quantity, price, subtotal
            ) VALUES (?, ?, ?, ?, ?, ?)
        ");

        foreach ($items as $item) {
            $item_subtotal = (float) ($item['price'] ?? 0) * (int) ($item['quantity'] ?? 1);
            $stmt->execute([
                $tenant_id,
                $sale_id,
                $item['id'] ?? null,
                $item['quantity'] ?? 1,
                $item['price'] ?? 0,
                $item_subtotal
            ]);
        }

        // Log activity
        $stmt = $pdo->prepare("
            INSERT INTO activity_logs (
                user_id, action, description, ip_address, tenant_id, created_at
            ) VALUES (
                ?, 'draft_created', ?, ?, ?, NOW()
            )
        ");
        $stmt->execute([
            $user_id,
            "Created draft sale #$sale_id with " . count($items) . " items",
            $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
            $tenant_id
        ]);

        $pdo->commit();

        $success = "Draft sale created successfully!";

        // Redirect to view draft
        header("Location: view_draft.php?id=" . $sale_id);
        exit;

    } catch (Exception $e) {
        if (isset($pdo) && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $error = "Error creating draft: " . $e->getMessage();
        $debug_info['error'] = $e->getMessage();
        $debug_info['trace'] = $e->getTraceAsString();
    }
    } // end CSRF else
}

$page_title = 'Add Draft Sale';
$csrf_token = generate_csrf_token();
$currency_symbol = get_tenant_currency();
ob_start();
?>

<style>
    .product-card {
        background: rgba(30, 41, 59, 0.5);
        border: 1px solid rgba(255, 255, 255, 0.05);
        border-radius: 0.75rem;
        padding: 0.75rem;
        cursor: pointer;
        transition: all 0.2s ease;
    }

    .product-card:hover {
        transform: translateY(-2px);
        border-color: #FBBF24;
        background: rgba(30, 41, 59, 0.8);
    }

    .cart-item {
        background: rgba(30, 41, 59, 0.5);
        border: 1px solid rgba(255, 255, 255, 0.05);
        border-radius: 0.75rem;
        padding: 0.75rem;
        margin-bottom: 0.5rem;
        transition: all 0.2s ease;
    }

    .cart-item:hover {
        background: rgba(30, 41, 59, 0.8);
        border-color: rgba(251, 191, 36, 0.3);
    }

    .qty-btn {
        width: 28px;
        height: 28px;
        display: flex;
        align-items: center;
        justify-content: center;
        background: rgba(0, 0, 0, 0.3);
        border-radius: 0.5rem;
        transition: all 0.2s ease;
    }

    .qty-btn:hover {
        background: #FBBF24;
        color: black;
    }

    .debug-panel {
        position: fixed;
        top: 1rem;
        right: 1rem;
        max-width: 400px;
        max-height: 80vh;
        overflow-y: auto;
        background: rgba(31, 41, 55, 0.95);
        backdrop-filter: blur(8px);
        border: 1px solid #FBBF24;
        border-radius: 1rem;
        padding: 1rem;
        font-size: 0.75rem;
        z-index: 9999;
        display: none;
        color: white;
    }

    .debug-panel.visible {
        display: block;
    }

    .debug-toggle {
        position: fixed;
        top: 1rem;
        right: 1rem;
        background: #FBBF24;
        color: #000;
        padding: 0.5rem 1rem;
        border-radius: 9999px;
        font-size: 0.75rem;
        font-weight: bold;
        cursor: pointer;
        z-index: 10000;
    }

    @keyframes slideUp {
        from {
            opacity: 0;
            transform: translateY(20px);
        }

        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
</style>

<div class="fade-in">

    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-6">
        <div>
            <div class="text-xs text-amber-400/70 uppercase tracking-wider font-semibold mb-0.5">Sales</div>
            <h1 class="text-lg font-bold text-white">Add Draft Sale</h1>
        </div>
        <div class="flex items-center gap-2 shrink-0">
            <a href="list_draft.php" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-slate-800 border border-slate-700 text-slate-300 text-sm font-medium hover:bg-slate-700 transition-colors"><i class="fas fa-list mr-1"></i>View All Drafts</a>
        </div>
    </div>

    <!-- Error/Success Messages -->
    <?php if ($error): ?>
        <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-3 mb-4 border-l-4 border-l-[#EF4444]">
            <div class="flex items-center gap-3 text-[#EF4444]">
                <i class="fas fa-exclamation-circle"></i>
                <span><?php echo htmlspecialchars($error); ?></span>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-3 mb-4 border-l-4 border-l-[#10B981]">
            <div class="flex items-center gap-3 text-[#10B981]">
                <i class="fas fa-check-circle"></i>
                <span><?php echo htmlspecialchars($success); ?></span>
            </div>
        </div>
    <?php endif; ?>

    <form method="POST" id="draftForm" class="grid lg:grid-cols-3 gap-3">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">

        <!-- Left Column - Product Selection -->
        <div class="lg:col-span-2 space-y-4">

            <!-- Search and Filters -->
            <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-3 mb-4">
                <div class="flex gap-3">
                    <div class="flex-1 relative">
                        <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-500"></i>
                        <input type="text" id="searchInput" placeholder="Search products..."
                            class="w-full pl-10 pr-4 py-2 bg-[#1F2937] border border-gray-700 rounded-lg text-white placeholder-slate-500 focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 outline-none transition-all">
                    </div>
                    <select id="categoryFilter"
                        class="px-4 py-2 bg-[#1F2937] border border-gray-700 rounded-lg text-white focus:ring-1 focus:ring-amber-500 focus:border-amber-500 outline-none">
                        <option value="all">All Categories</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?php echo $cat['id']; ?>"><?php echo htmlspecialchars($cat['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <!-- Products Grid -->
            <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-3 mb-4">
                <div class="flex justify-between items-center mb-3">
                    <h2 class="font-semibold flex items-center gap-2">
                        <i class="fas fa-cubes text-amber-400"></i>
                        Products
                    </h2>
                    <span class="text-sm text-gray-400" id="productCount"><?php echo count($products); ?>
                        items</span>
                </div>

                <?php if (empty($products)): ?>
                    <div class="text-center py-12 bg-[#1F2937]/50 rounded-lg">
                        <i class="fas fa-box-open text-4xl text-gray-600 mb-3"></i>
                        <p class="text-gray-400">No products found</p>
                    </div>
                <?php else: ?>
                    <div id="productGrid"
                        class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-3 max-h-[500px] overflow-y-auto pr-2">
                        <?php foreach ($products as $product):
                            $price = isset($product['selling_price']) && $product['selling_price'] > 0 ? $product['selling_price'] : ($product['price'] ?? 0);
                            ?>
                            <div class="product-card"
                                onclick="addToCart(<?php echo $product['id']; ?>, '<?php echo htmlspecialchars(addslashes($product['name'])); ?>', <?php echo (float) $price; ?>, <?php echo (int) ($product['stock'] ?? 0); ?>)"
                                data-category="<?php echo $product['category_id'] ?? ''; ?>"
                                data-name="<?php echo htmlspecialchars(strtolower($product['name'])); ?>">
                                <?php
                                $imgUrl = '';
                                if (!empty($product['image'])) {
                                    $rel = ltrim($product['image'], '/');
                                    if (strpos($rel, 'public/') === 0) {
                                        $rel = substr($rel, 7);
                                    }
                                    $full = PUBLIC_PATH . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel);
                                    if (file_exists($full)) {
                                        $imgUrl = base_url($rel) . '?v=' . (@filemtime($full) ?: time());
                                    }
                                }
                                ?>
                                <?php if ($imgUrl): ?>
                                    <img src="<?php echo htmlspecialchars($imgUrl); ?>" alt=""
                                        class="w-full h-20 object-cover rounded-lg mb-2">
                                <?php else: ?>
                                    <div
                                        class="w-full h-20 bg-gradient-to-br from-slate-700 to-slate-800 rounded-lg mb-2 flex items-center justify-center">
                                        <i class="fas fa-cube text-amber-400/50 text-2xl"></i>
                                    </div>
                                <?php endif; ?>
                                <div class="text-sm font-medium line-clamp-2 mb-1">
                                    <?php echo htmlspecialchars($product['name']); ?>
                                </div>
                                <div class="flex justify-between items-center">
                                    <span class="text-amber-400 font-bold"><?php echo $currency_symbol; ?> <?php echo number_format((float) $price, 0); ?></span>
                                    <span
                                        class="text-xs <?php echo ($product['stock'] ?? 0) < 5 ? 'text-red-400' : 'text-gray-400'; ?>">
                                        <i
                                            class="fas <?php echo ($product['stock'] ?? 0) < 5 ? 'fa-exclamation-triangle' : 'fa-cube'; ?>"></i>
                                        <?php echo (int) ($product['stock'] ?? 0); ?>
                                    </span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Right Column - Cart -->
        <div class="lg:col-span-1">
            <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-3 mb-4 sticky top-20">

                <!-- Cart Header -->
                <div class="flex justify-between items-center mb-4">
                    <h2 class="font-semibold flex items-center gap-2">
                        <i class="fas fa-shopping-cart text-amber-400"></i>
                        Cart
                        <span id="cartCount" class="text-xs bg-amber-500 text-black px-2 py-0.5 rounded-full">0</span>
                    </h2>
                    <button type="button" onclick="clearCart()"
                        class="text-sm text-gray-400 hover:text-red-500 transition-colors">
                        <i class="fas fa-trash"></i>
                        Clear
                    </button>
                </div>

                <!-- Customer Selection -->
                <div class="mb-4">
                    <label class="block text-sm text-gray-400 mb-1">Customer (Optional)</label>
                    <select name="customer_id"
                        class="w-full px-3 py-2 bg-[#1F2937] border border-gray-700 rounded-lg text-white focus:ring-1 focus:ring-amber-500 focus:border-amber-500 outline-none">
                        <option value="">Walk-in Customer</option>
                        <?php foreach ($customers as $customer): ?>
                            <option value="<?php echo $customer['id']; ?>">
                                <?php echo htmlspecialchars($customer['name']); ?>
                                <?php if (!empty($customer['phone'])): ?> -
                                    <?php echo htmlspecialchars($customer['phone']); ?>     <?php endif; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Cart Items -->
                <div id="cartItems" class="max-h-[300px] overflow-y-auto mb-4">
                    <div class="text-center text-gray-500 py-8">
                        <i class="fas fa-shopping-cart text-3xl mb-2 opacity-50"></i>
                        <p class="text-sm">Cart is empty</p>
                        <p class="text-xs">Click on products to add them</p>
                    </div>
                </div>

                <!-- Discount -->
                <div class="space-y-3 mb-4">
                    <div class="flex gap-2">
                        <select id="discountType" name="discount_type"
                            class="px-2 py-2 bg-[#1F2937] border border-gray-700 rounded-lg text-white text-sm focus:ring-1 focus:ring-amber-500 focus:border-amber-500 outline-none">
                            <option value="fixed"><?php echo htmlspecialchars($currency_symbol); ?></option>
                            <option value="percent">%</option>
                        </select>
                        <input type="number" id="discount" name="discount" value="0" min="0" step="0.01"
                            class="flex-1 px-3 py-2 bg-[#1F2937] border border-gray-700 rounded-lg text-white focus:ring-1 focus:ring-amber-500 focus:border-amber-500 outline-none"
                            oninput="calculateTotals()">
                    </div>

                    <div class="flex items-center gap-2">
                        <input type="checkbox" id="taxExempt" name="tax_exempt"
                            class="rounded border-gray-600 text-amber-400 focus:ring-amber-500"
                            onchange="calculateTotals()">
                        <label for="taxExempt" class="text-sm text-gray-300">Tax Exempt</label>
                    </div>

                    <input type="hidden" id="taxRate" name="tax_rate" value="16">
                </div>

                <!-- Summary -->
                <div class="space-y-2 pt-3 border-t border-gray-700">
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-400">Subtotal:</span>
                        <span id="subtotalDisplay" class="font-semibold"><?php echo $currency_symbol; ?> 0</span>
                    </div>
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-400">Discount:</span>
                        <span id="discountDisplay" class="text-red-400">-<?php echo $currency_symbol; ?> 0</span>
                    </div>
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-400">Tax (16%):</span>
                        <span id="taxDisplay" class="font-semibold"><?php echo $currency_symbol; ?> 0</span>
                    </div>
                    <div class="flex justify-between text-lg font-bold pt-2 border-t border-gray-700">
                        <span>Total:</span>
                        <span id="totalDisplay" class="text-amber-400"><?php echo $currency_symbol; ?> 0</span>
                    </div>
                </div>

                <!-- Notes -->
                <div class="mt-4">
                    <textarea name="notes" placeholder="Add notes (optional)..."
                        class="w-full px-3 py-2 bg-[#1F2937] border border-gray-700 rounded-lg text-white placeholder-slate-500 focus:ring-1 focus:ring-amber-500 focus:border-amber-500 outline-none text-sm"
                        rows="2"></textarea>
                </div>

                <!-- Hidden Input for Cart Items -->
                <input type="hidden" name="items" id="cartItemsInput" value="[]">

                <!-- Submit Button -->
                <button type="submit"
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-amber-500 text-black text-sm font-semibold hover:bg-amber-600 transition-colors w-full mt-4 disabled:opacity-50 disabled:cursor-not-allowed"
                    id="submitBtn" disabled>
                    <i class="fas fa-save mr-1"></i>Save Draft
                </button>
            </div>
        </div>
    </form>
</div>

<script>
    // Cart state
    let cart = [];
    let products = <?php echo json_encode($products); ?>;
    const CURRENCY_SYM = <?php echo json_encode($currency_symbol); ?>;

    // Filter and search functionality
    document.getElementById('searchInput').addEventListener('input', filterProducts);
    document.getElementById('categoryFilter').addEventListener('change', filterProducts);

    function filterProducts() {
        const searchTerm = document.getElementById('searchInput').value.toLowerCase();
        const category = document.getElementById('categoryFilter').value;

        document.querySelectorAll('.product-card').forEach(card => {
            const name = card.dataset.name || '';
            const cardCategory = card.dataset.category;

            const matchesSearch = name.includes(searchTerm);
            const matchesCategory = category === 'all' || cardCategory === category;

            card.style.display = matchesSearch && matchesCategory ? 'block' : 'none';
        });

        const visibleCount = document.querySelectorAll('.product-card[style="display: block"]').length;
        document.getElementById('productCount').textContent = visibleCount + ' items';
    }

    // Add to cart
    function addToCart(id, name, price, stock) {
        if (stock <= 0) {
            alert('Product out of stock');
            return;
        }

        const existing = cart.find(item => item.id === id);

        if (existing) {
            if (existing.quantity < stock) {
                existing.quantity++;
            } else {
                alert('Maximum stock reached');
                return;
            }
        } else {
            cart.push({
                id: id,
                name: name,
                price: price,
                quantity: 1,
                stock: stock
            });
        }

        updateCartDisplay();
    }

    // Update cart display
    function updateCartDisplay() {
        const cartContainer = document.getElementById('cartItems');
        const cartCount = document.getElementById('cartCount');

        if (cart.length === 0) {
            cartContainer.innerHTML = `
                <div class="text-center text-gray-500 py-8">
                    <i class="fas fa-shopping-cart text-3xl mb-2 opacity-50"></i>
                    <p class="text-sm">Cart is empty</p>
                    <p class="text-xs">Click on products to add them</p>
                </div>
            `;
            cartCount.textContent = '0';
            document.getElementById('submitBtn').disabled = true;
        } else {
            let html = '';
            cart.forEach((item, index) => {
                html += `
                    <div class="cart-item">
                        <div class="flex justify-between items-start mb-2">
                            <div class="flex-1">
                                <div class="font-medium text-sm">${item.name}</div>
                                <div class="text-xs text-gray-400">${CURRENCY_SYM} ${item.price.toLocaleString()} x ${item.quantity}</div>
                            </div>
                            <button type="button" onclick="removeFromCart(${index})" class="text-red-400 hover:text-red-300">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-1">
                                <button type="button" onclick="updateQuantity(${index}, -1)" class="qty-btn">
                                    <i class="fas fa-minus text-xs"></i>
                                </button>
                                <span class="text-sm font-medium w-8 text-center">${item.quantity}</span>
                                <button type="button" onclick="updateQuantity(${index}, 1)" class="qty-btn">
                                    <i class="fas fa-plus text-xs"></i>
                                </button>
                            </div>
                            <span class="font-semibold text-amber-400">${CURRENCY_SYM} ${(item.price * item.quantity).toLocaleString()}</span>
                        </div>
                    </div>
                `;
            });
            cartContainer.innerHTML = html;
            cartCount.textContent = cart.reduce((sum, item) => sum + item.quantity, 0);
            document.getElementById('submitBtn').disabled = false;
        }

        calculateTotals();
        updateCartItemsInput();
    }

    // Update quantity
    function updateQuantity(index, change) {
        const item = cart[index];
        const newQty = item.quantity + change;

        if (newQty <= 0) {
            removeFromCart(index);
        } else if (newQty <= item.stock) {
            item.quantity = newQty;
            updateCartDisplay();
        }
    }

    // Remove from cart
    function removeFromCart(index) {
        cart.splice(index, 1);
        updateCartDisplay();
    }

    // Clear cart
    function clearCart() {
        if (cart.length > 0 && confirm('Clear all items from cart?')) {
            cart = [];
            updateCartDisplay();
        }
    }

    // Calculate totals
    function calculateTotals() {
        const subtotal = cart.reduce((sum, item) => sum + (item.price * item.quantity), 0);

        const discount = parseFloat(document.getElementById('discount').value) || 0;
        const discountType = document.getElementById('discountType').value;
        const taxExempt = document.getElementById('taxExempt').checked;
        const taxRate = 16; // 16% VAT

        let discountAmount = 0;
        if (discountType === 'percent') {
            discountAmount = subtotal * (discount / 100);
        } else {
            discountAmount = Math.min(discount, subtotal);
        }

        const afterDiscount = subtotal - discountAmount;
        const taxAmount = taxExempt ? 0 : afterDiscount * (taxRate / 100);
        const total = afterDiscount + taxAmount;

        // Update displays with null checking
        document.getElementById('subtotalDisplay').textContent = `${CURRENCY_SYM} ${safeNumberFormat(subtotal)}`;
        document.getElementById('discountDisplay').textContent = `-${CURRENCY_SYM} ${safeNumberFormat(discountAmount)}`;
        document.getElementById('taxDisplay').textContent = `${CURRENCY_SYM} ${safeNumberFormat(taxAmount)}`;
        document.getElementById('totalDisplay').textContent = `${CURRENCY_SYM} ${safeNumberFormat(total)}`;
    }

    // Safe number formatting function
    function safeNumberFormat(value) {
        // Handle null, undefined, NaN
        if (value === null || value === undefined || isNaN(value)) {
            return '0';
        }
        return value.toLocaleString(undefined, {
            minimumFractionDigits: 0,
            maximumFractionDigits: 0
        });
    }

    // Update hidden input with cart data
    function updateCartItemsInput() {
        const cartItems = cart.map(item => ({
            id: item.id,
            name: item.name,
            price: item.price,
            quantity: item.quantity
        }));
        document.getElementById('cartItemsInput').value = JSON.stringify(cartItems);
    }

    // Debug toggle
    function toggleDebug() {
        document.getElementById('debugPanel').classList.toggle('visible');
    }

    // Initialize
    document.addEventListener('DOMContentLoaded', function () {
        calculateTotals();
    });
</script>

<?php
$page_content = ob_get_clean();
require_once __DIR__ . '/../layouts/app.php';
