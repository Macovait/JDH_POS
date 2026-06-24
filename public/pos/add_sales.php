<?php
/**
 * Advanced Add Sales Page for Jakababa POS
 * Complete sales transaction interface with advanced features
 * Pure Tailwind CSS Design
 */

// Bootstrap paths and core dependencies
$pathsFile = __DIR__ . '/../../src/paths.php';
if (!file_exists($pathsFile)) {
    $pathsFile = dirname(__DIR__, 3) . '/src/paths.php';
}
require_once $pathsFile;

safe_require('auth.php', 'src', true);
safe_require('db.php', 'src', true);
safe_require('functions.php', 'src', true);
require_login();

// Start session for flash messages
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Business type detection
$tenant_id = get_current_tenant_id();
$business_type = get_current_business_type($tenant_id);
$bt_config = get_business_type_config($business_type) ?? [];
$bt_sale_label = $bt_config['sale_label'] ?? 'Sale';
$bt_sales_label = $bt_config['sales_label'] ?? 'Sales';
$bt_customer_label = $bt_config['customer_label'] ?? 'Customer';
$bt_order_types = $bt_config['order_types'] ?? ['walkin'];
$bt_order_labels = $bt_config['order_labels'] ?? [];
$bt_default_order_type = $bt_order_types[0] ?? 'walkin';
$bt_icon = $bt_config['icon'] ?? 'fa-store';
$bt_name = $bt_config['name'] ?? 'General Retail';

$page_title = 'Add New ' . $bt_sale_label;
$user_id = (int) ($_SESSION['user']['id'] ?? 0);
$user_name = htmlspecialchars($_SESSION['user']['name'] ?? 'Cashier');
$user_role = $_SESSION['user']['role'] ?? '';
$user_branch = (int) ($_SESSION['user']['branch_id'] ?? 0);
$tenant_id = get_current_tenant_id();
if (!$tenant_id) {
    http_response_code(403);
    exit('Company context missing. Please log in again.');
}

$csrf_token = generate_csrf_token();

// Check for flash messages
$flash_message = $_SESSION['flash_message'] ?? '';
$flash_type = $_SESSION['flash_type'] ?? '';
unset($_SESSION['flash_message'], $_SESSION['flash_type']);

// Release session lock
if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}

// Initialize counters
$product_count = 0;
$customer_count = 0;
$discount_count = 0;
$voucher_count = 0;

// Fetch products with advanced details
$products = [];
try {
    $pdo = get_db_connection();

    // Check if products table exists and has data
    $check_products = $pdo->prepare("SELECT COUNT(*) as count FROM products WHERE tenant_id = ?");
    $check_products->execute([$tenant_id]);
    $product_count = $check_products->fetch()['count'] ?? 0;

    if ($product_count > 0) {
        // Check if inventory table exists
        $inventory_exists = false;
        try {
            $pdo->query("SELECT 1 FROM inventory LIMIT 1");
            $inventory_exists = true;
        } catch (PDOException $e) {
            // Inventory table might not exist
        }

        // Check if categories table exists
        $categories_exists = false;
        try {
            $pdo->query("SELECT 1 FROM categories LIMIT 1");
            $categories_exists = true;
        } catch (PDOException $e) {
            // Categories table might not exist
        }

        // Build query based on available tables
        $product_query = "
            SELECT 
                p.*, 
                COALESCE(p.selling_price, p.price, 0) as selling_price,
                p.status as product_status,
                p.id as product_id
        ";

        if ($inventory_exists) {
            $product_query .= ", COALESCE(i.stock, 0) as stock";
        } else {
            $product_query .= ", 0 as stock";
        }

        if ($categories_exists) {
            $product_query .= ", c.name as category_name, c.color as category_color";
        } else {
            $product_query .= ", NULL as category_name, NULL as category_color";
        }

        $product_query .= " FROM products p ";

        if ($inventory_exists) {
            $product_query .= " LEFT JOIN inventory i ON p.id = i.product_id AND i.branch_id = :branch_id ";
        }

        if ($categories_exists) {
            $product_query .= " LEFT JOIN categories c ON p.category_id = c.id ";
        }

        $product_query .= " WHERE (p.status = 'active' OR p.status IS NULL OR p.status = 1) AND p.tenant_id = :tenant_id AND p.deleted_at IS NULL ORDER BY p.name";

        $stmt = $pdo->prepare($product_query);
        $stmt->execute([':branch_id' => $user_branch, ':tenant_id' => $tenant_id]);
        $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Update product count with actual fetched products
        $product_count = count($products);
    }
} catch (PDOException $e) {
    error_log("Error fetching products: " . $e->getMessage());
    $products = [];
}

// Fetch customers with balances
$customers = [];
try {
    $check_customers = $pdo->prepare("SELECT COUNT(*) as count FROM customers WHERE tenant_id = ?");
    $check_customers->execute([$tenant_id]);
    $customer_count = $check_customers->fetch()['count'] ?? 0;

    if ($customer_count > 0) {
        // Check which columns exist
        $columns = $pdo->query("SHOW COLUMNS FROM customers")->fetchAll(PDO::FETCH_COLUMN);

        $has_credit_limit = in_array('credit_limit', $columns);
        $has_current_balance = in_array('current_balance', $columns);
        $has_status = in_array('status', $columns);

        $customer_query = "
            SELECT
                c.*,
                COALESCE(SUM(s.total), 0) as lifetime_value,
                MAX(s.created_at) as last_purchase
            FROM customers c
            LEFT JOIN sales s ON c.id = s.customer_id
            WHERE c.tenant_id = :tenant_id
        ";

        if ($has_status) {
            $customer_query .= " AND (c.status = 1 OR c.status IS NULL)";
        }

        $customer_query .= " GROUP BY c.id ORDER BY c.name";

        $stmt = $pdo->prepare($customer_query);
        $stmt->execute([':tenant_id' => $tenant_id]);
        $customers = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $customer_count = count($customers);
    }
} catch (PDOException $e) {
    error_log("Error fetching customers: " . $e->getMessage());
    $customers = [];
}

// Get active discounts
$discounts = [];
try {
    $check_discounts = $pdo->prepare("SELECT COUNT(*) as count FROM discounts WHERE tenant_id = ?");
    $check_discounts->execute([$tenant_id]);
    $discount_count = $check_discounts->fetch()['count'] ?? 0;

    if ($discount_count > 0) {
        $stmt = $pdo->prepare("
            SELECT * FROM discounts
            WHERE tenant_id = ? AND active = 1
            AND (valid_from IS NULL OR valid_from <= CURDATE())
            AND (valid_until IS NULL OR valid_until >= CURDATE())
            ORDER BY priority DESC, name
        ");
        $stmt->execute([$tenant_id]);
        $discounts = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $discount_count = count($discounts);
    }
} catch (PDOException $e) {
    error_log("Error fetching discounts: " . $e->getMessage());
    $discounts = [];
}

// Get active vouchers
$vouchers = [];
try {
    $check_vouchers = $pdo->prepare("SELECT COUNT(*) as count FROM vouchers WHERE tenant_id = ?");
    $check_vouchers->execute([$tenant_id]);
    $voucher_count = $check_vouchers->fetch()['count'] ?? 0;

    if ($voucher_count > 0) {
        $stmt = $pdo->prepare("
            SELECT * FROM vouchers
            WHERE tenant_id = ? AND active = 1
            AND (expires_at IS NULL OR expires_at >= CURDATE())
            ORDER BY code
        ");
        $stmt->execute([$tenant_id]);
        $vouchers = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $voucher_count = count($vouchers);
    }
} catch (PDOException $e) {
    error_log("Error fetching vouchers: " . $e->getMessage());
    $vouchers = [];
}

// Get company settings
$tax_rate = 16;
$currency_symbol = 'KSh';
$mpesa_enabled = false;
try {
    $settings_exists = false;
    try {
        $pdo->query("SELECT 1 FROM settings LIMIT 1");
        $settings_exists = true;
    } catch (PDOException $e) {
        // Settings table might not exist
    }

    if ($settings_exists) {
        $stmt = $pdo->prepare("SELECT setting_key, setting_value FROM settings WHERE tenant_id = ? AND setting_key IN ('tax_rate', 'currency', 'mpesa_enabled')");
        $stmt->execute([$tenant_id]);
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            if ($row['setting_key'] === 'tax_rate')
                $tax_rate = (float) $row['setting_value'];
            if ($row['setting_key'] === 'currency')
                $currency_symbol = $row['setting_value'];
            if ($row['setting_key'] === 'mpesa_enabled')
                $mpesa_enabled = $row['setting_value'] === '1';
        }
    }
} catch (PDOException $e) {
    error_log("Error fetching settings: " . $e->getMessage());
}

// Sample data fallbacks
if (empty($products)) {
    $products = [
        ['id' => 1, 'name' => 'Sample Product 1', 'selling_price' => 1000, 'stock' => 50],
        ['id' => 2, 'name' => 'Sample Product 2', 'selling_price' => 2500, 'stock' => 30],
        ['id' => 3, 'name' => 'Sample Product 3', 'selling_price' => 5000, 'stock' => 20],
    ];
    $product_count = count($products);
}

if (empty($customers)) {
    $customers = [
        ['id' => 1, 'name' => 'Walk-in Customer', 'phone' => '', 'current_balance' => 0, 'credit_limit' => 0, 'lifetime_value' => 0],
        ['id' => 2, 'name' => 'John Doe', 'phone' => '0712345678', 'current_balance' => 5000, 'credit_limit' => 50000, 'lifetime_value' => 15000],
    ];
    $customer_count = count($customers);
}

if (empty($discounts)) {
    $discounts = [
        ['id' => 1, 'name' => 'Staff Discount', 'type' => 'percent', 'value' => 10, 'min_purchase' => 0],
        ['id' => 2, 'name' => 'Bulk Purchase', 'type' => 'fixed', 'value' => 500, 'min_purchase' => 10000],
    ];
    $discount_count = count($discounts);
}

if (empty($vouchers)) {
    $vouchers = [
        ['id' => 1, 'code' => 'SAVE10', 'type' => 'percent', 'value' => 10],
        ['id' => 2, 'code' => 'WELCOME20', 'type' => 'fixed', 'value' => 200],
    ];
    $voucher_count = count($vouchers);
}
ob_start();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?> - Jakababa POS</title>
    
    <link rel="stylesheet" href="<?php echo asset_url('css/app.css'); ?>">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        accent: '#fbbf24',
                        'bg-dark': '#0f172a',
                        'border': '#374151',
                    },
                    animation: {
                        'fade-in': 'fadeIn 0.3s ease-out',
                        'slide-in': 'slideIn 0.3s ease-out',
                        'spin-slow': 'spin 1s linear infinite',
                    }
                }
            }
        }
    </script>
    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    
    <style>
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        @keyframes slideIn {
            from { transform: translateX(100%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        
        . { animation: fadeIn 0.3s ease-out; }
        .animate-slide-in { animation: slideIn 0.3s ease-out; }
        .animate-spin { animation: spin 0.8s linear infinite; }
        
        .product-row {
            transition: all 0.2s ease;
            background: rgba(31, 41, 55, 0.3);
            border: 1px solid transparent;
            border-radius: 0.75rem;
        }
        .product-row:hover {
            background: rgba(31, 41, 55, 0.6);
            border-color: rgba(251, 191, 36, 0.3);
        }
        .product-row-remove { opacity: 0; transition: opacity 0.2s; }
        .product-row:hover .product-row-remove { opacity: 1; }
        
        .form-input {
            background: #111827;
            border: 1px solid #374151;
            border-radius: 0.75rem;
            padding: 0.5rem 0.75rem;
            color: white;
            width: 100%;
            font-size: 0.875rem;
            transition: all 0.2s;
        }
        .form-input:focus {
            outline: none;
            border-color: #fbbf24;
            box-shadow: 0 0 0 3px rgba(251, 191, 36, 0.2);
        }
        .form-input::placeholder { color: #6B7280; }
        
        .quick-amount {
            background: #111827;
            border: 1px solid #374151;
            border-radius: 0.5rem;
            padding: 0.5rem;
            color: #9CA3AF;
            cursor: pointer;
            text-align: center;
            font-size: 0.75rem;
            transition: all 0.2s;
        }
        .quick-amount:hover {
            background: #fbbf24;
            color: #1e3a8a;
            border-color: #fbbf24;
        }
        
        .stock-indicator {
            display: inline-block;
            width: 8px;
            height: 8px;
            border-radius: 50%;
            margin-right: 6px;
        }
        .stock-high { background: #10b981; }
        .stock-medium { background: #fbbf24; }
        .stock-low { background: #ef4444; }
        
        ::-webkit-scrollbar { width: 6px; height: 6px; }
        ::-webkit-scrollbar-track { background: rgba(255,255,255,0.05); border-radius: 10px; }
        ::-webkit-scrollbar-thumb { background: rgba(251,191,36,0.3); border-radius: 10px; }
        ::-webkit-scrollbar-thumb:hover { background: rgba(251,191,36,0.5); }
    </style>
</head>
<body class="bg-gray-900 font-['Inter'] antialiased">

<div class="max-w-7xl mx-auto px-4 py-4 ">
    
    <!-- Header -->
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-5 gap-3">
        <div>
            <div class="flex items-center gap-2 text-sm text-amber-400 mb-1">
                <i class="fas fa-cash-register text-xs"></i>
                <span class="font-semibold tracking-wider">NEW TRANSACTION</span>
            </div>
            <h1 class="text-2xl font-bold text-white">Add New <?php echo htmlspecialchars($bt_sale_label); ?></h1>
            <p class="text-xs text-gray-500 mt-0.5">Create a new sales transaction</p>
        </div>
        <div class="flex gap-2">
            <a href="all_sales.php" class="inline-flex items-center px-3 py-1.5 bg-gray-800 border border-gray-700 rounded-lg text-xs font-medium text-gray-400 hover:bg-gray-700 transition-all">
                <i class="fas fa-arrow-left mr-1 text-xs"></i> Back to Sales
            </a>
        </div>
    </div>

    <!-- Flash Message -->
    <?php if ($flash_message): ?>
        <div class="mb-4 p-3 rounded-lg <?php echo $flash_type === 'success' ? 'bg-emerald-500/10 border border-emerald-500/30 text-emerald-400' : 'bg-red-500/10 border border-red-500/30 text-red-400'; ?> text-sm animate-slide-in">
            <i class="fas fa-<?php echo $flash_type === 'success' ? 'check-circle' : 'exclamation-circle'; ?> mr-2"></i>
            <?php echo htmlspecialchars($flash_message); ?>
        </div>
    <?php endif; ?>

    <!-- Business Type Display Banner -->
    <div class="mb-4 bg-gradient-to-r from-gray-800 to-gray-900 rounded-xl p-3 border border-gray-700 flex items-center justify-between">
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 bg-amber-500/10 rounded-lg flex items-center justify-center">
                <i class="fas <?php echo htmlspecialchars($bt_icon); ?> text-amber-400 text-lg"></i>
            </div>
            <div>
                <p class="text-[10px] text-gray-500 uppercase tracking-wider">Business Type</p>
                <p class="text-sm font-semibold text-white"><?php echo htmlspecialchars($bt_name); ?></p>
            </div>
        </div>
        <div class="text-right text-xs text-gray-500">
            <p>User: <strong class="text-white"><?php echo htmlspecialchars($user_name); ?></strong></p>
        </div>
    </div>

    <!-- M-Pesa Configuration Banner -->
    <?php if (!$mpesa_enabled): ?>
    <div class="mb-4 bg-red-500/10 border border-red-500/30 rounded-xl p-3">
        <div class="flex items-start gap-2">
            <i class="fas fa-info-circle text-red-400 text-sm mt-0.5"></i>
            <div class="flex-1">
                <h3 class="font-semibold text-red-400 text-sm">M-Pesa Payment Not Configured</h3>
                <p class="text-xs text-gray-400 mt-0.5">M-Pesa STK Push is not enabled. To accept M-Pesa payments, configure your Daraja API credentials.</p>
                <a href="../../billing/mpesa_settings.php" class="inline-block mt-2 text-xs text-emerald-400 hover:text-emerald-300">Configure Now →</a>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Quick Stats -->
    <div class="grid grid-cols-4 gap-2 mb-4">
        <div class="bg-gray-800/40 border border-gray-700 rounded-lg p-2">
            <div class="flex items-center gap-2">
                <div class="w-7 h-7 rounded-lg bg-amber-500/10 flex items-center justify-center">
                    <i class="fas fa-cubes text-amber-400 text-xs"></i>
                </div>
                <div>
                    <p class="text-[10px] text-gray-500">Products</p>
                    <p class="text-sm font-semibold text-white"><?php echo $product_count; ?></p>
                </div>
            </div>
        </div>
        <div class="bg-gray-800/40 border border-gray-700 rounded-lg p-2">
            <div class="flex items-center gap-2">
                <div class="w-7 h-7 rounded-lg bg-emerald-500/10 flex items-center justify-center">
                    <i class="fas fa-users text-emerald-400 text-xs"></i>
                </div>
                <div>
                    <p class="text-[10px] text-gray-500">Customers</p>
                    <p class="text-sm font-semibold text-white"><?php echo $customer_count; ?></p>
                </div>
            </div>
        </div>
        <div class="bg-gray-800/40 border border-gray-700 rounded-lg p-2">
            <div class="flex items-center gap-2">
                <div class="w-7 h-7 rounded-lg bg-amber-500/10 flex items-center justify-center">
                    <i class="fas fa-tags text-amber-400 text-xs"></i>
                </div>
                <div>
                    <p class="text-[10px] text-gray-500">Discounts</p>
                    <p class="text-sm font-semibold text-white"><?php echo $discount_count; ?></p>
                </div>
            </div>
        </div>
        <div class="bg-gray-800/40 border border-gray-700 rounded-lg p-2">
            <div class="flex items-center gap-2">
                <div class="w-7 h-7 rounded-lg bg-amber-500/10 flex items-center justify-center">
                    <i class="fas fa-percent text-amber-400 text-xs"></i>
                </div>
                <div>
                    <p class="text-[10px] text-gray-500">Tax Rate</p>
                    <p class="text-sm font-semibold text-white"><?php echo $tax_rate; ?>%</p>
                </div>
            </div>
        </div>
    </div>

    <?php if ($product_count == 0): ?>
    <div class="mb-4 bg-amber-500/10 border border-amber-500/30 rounded-xl p-3">
        <div class="flex items-center gap-2 text-amber-400 text-sm">
            <i class="fas fa-info-circle"></i>
            <p>No products found. Please add products first.</p>
        </div>
    </div>
    <?php endif; ?>

    <!-- Main Form -->
    <form id="salesForm" method="POST" action="process_sales.php" class="space-y-4">
        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">

        <!-- Customer & Payment Section -->
        <div class="bg-gray-800/40 border border-gray-700 rounded-xl p-4">
            <h2 class="text-sm font-semibold text-white mb-3 flex items-center gap-2">
                <i class="fas fa-user text-amber-400 text-xs"></i>
                Customer & Payment Details
            </h2>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                <div>
                    <label class="block text-xs text-gray-500 mb-1"><?php echo htmlspecialchars($bt_customer_label); ?></label>
                    <select name="customer_id" id="customerSelect" class="form-input text-sm">
                        <option value="">Walk-in Customer</option>
                        <?php foreach ($customers as $customer): ?>
                            <option value="<?php echo $customer['id']; ?>"
                                data-balance="<?php echo $customer['current_balance'] ?? 0; ?>"
                                data-limit="<?php echo $customer['credit_limit'] ?? 0; ?>"
                                data-ltv="<?php echo $customer['lifetime_value'] ?? 0; ?>">
                                <?php echo htmlspecialchars($customer['name']); ?>
                                <?php if (!empty($customer['phone'])): ?> - <?php echo $customer['phone']; ?><?php endif; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div id="customerInfo" class="hidden mt-2 p-2 bg-gray-900 rounded-lg text-xs space-y-1">
                        <div class="flex justify-between"><span class="text-gray-500">Credit Balance:</span><span id="creditBalance" class="text-amber-400 font-mono">KSh 0</span></div>
                        <div class="flex justify-between"><span class="text-gray-500">Available Credit:</span><span id="availableCredit" class="text-emerald-400 font-mono">KSh 0</span></div>
                        <div class="flex justify-between"><span class="text-gray-500">Lifetime Value:</span><span id="lifetimeValue" class="text-amber-400 font-mono">KSh 0</span></div>
                    </div>
                </div>

                <div>
                    <label class="block text-xs text-gray-500 mb-1">Payment Method</label>
                    <select name="payment_method" id="paymentMethod" class="form-input text-sm">
                        <option value="cash">Cash</option>
                        <option value="card">Credit/Debit Card</option>
                        <?php if ($mpesa_enabled): ?>
                            <option value="mpesa">M-Pesa (STK Push)</option>
                        <?php else: ?>
                            <option value="mpesa" disabled>M-Pesa (Disabled)</option>
                        <?php endif; ?>
                        <option value="bank_transfer">Bank Transfer</option>
                        <option value="credit">Credit Account</option>
                        <option value="split">Split Payment</option>
                    </select>
                </div>

                <div>
                    <label class="block text-xs text-gray-500 mb-1">Order Type</label>
                    <select name="order_type" id="orderType" class="form-input text-sm">
                        <?php foreach ($bt_order_types as $ot): 
                            $ot_label = $bt_order_labels[$ot]['label'] ?? ucfirst(str_replace('-', ' ', $ot));
                        ?>
                            <option value="<?php echo htmlspecialchars($ot); ?>"><?php echo htmlspecialchars($ot_label); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </div>

        <!-- Discounts & Vouchers Section -->
        <div class="bg-gray-800/40 border border-gray-700 rounded-xl p-4">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Apply Discount</label>
                    <select name="discount_id" id="discountSelect" class="form-input text-sm">
                        <option value="">No Discount</option>
                        <?php foreach ($discounts as $discount): ?>
                            <option value="<?php echo $discount['id']; ?>" data-type="<?php echo $discount['type']; ?>"
                                data-value="<?php echo $discount['value']; ?>" data-min="<?php echo $discount['min_purchase'] ?? 0; ?>">
                                <?php echo htmlspecialchars($discount['name']); ?>
                                (<?php echo $discount['type'] == 'percent' ? $discount['value'] . '%' : 'KSh ' . number_format($discount['value'], 0); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="block text-xs text-gray-500 mb-1">Voucher Code</label>
                    <div class="flex gap-2">
                        <input type="text" name="voucher_code" id="voucherCode" class="form-input flex-1 text-sm" placeholder="Enter code">
                        <button type="button" onclick="validateVoucher()" class="px-3 py-1.5 bg-amber-500/15 border border-amber-500/30 rounded-lg text-amber-400 text-xs hover:bg-amber-500/25 transition-all whitespace-nowrap">Apply</button>
                    </div>
                    <div id="voucherMessage" class="text-xs mt-1"></div>
                </div>
            </div>
        </div>

        <!-- Products Section -->
        <div class="bg-gray-800/40 border border-gray-700 rounded-xl p-4">
            <div class="flex justify-between items-center mb-3">
                <h2 class="text-sm font-semibold text-white flex items-center gap-2">
                    <i class="fas fa-shopping-cart text-amber-400 text-xs"></i>
                    Products
                </h2>
                <button type="button" onclick="addProductRow()" class="px-3 py-1.5 bg-amber-500/15 border border-amber-500/30 rounded-lg text-amber-400 text-xs hover:bg-amber-500/25 transition-all">
                    <i class="fas fa-plus mr-1"></i> Add Product
                </button>
            </div>

            <div id="productsContainer" class="space-y-2 max-h-[400px] overflow-y-auto pr-1">
                <!-- Product rows will be added here -->
            </div>

            <div id="emptyState" class="text-center py-8">
                <i class="fas fa-shopping-cart text-3xl text-gray-600 mb-2 block"></i>
                <p class="text-gray-500 text-sm">No products added</p>
                <p class="text-xs text-gray-600 mt-1">Click "Add Product" to start</p>
            </div>

            <!-- Quick Products Grid -->
            <?php if (!empty($products)): ?>
            <div class="mt-4 pt-3 border-t border-gray-700">
                <p class="text-xs text-gray-500 mb-2">Quick Add Products</p>
                <div class="grid grid-cols-3 sm:grid-cols-6 gap-1.5">
                    <?php foreach (array_slice($products, 0, 6) as $product): ?>
                        <div onclick="quickAddProduct(<?php echo $product['id']; ?>, '<?php echo htmlspecialchars(addslashes($product['name'])); ?>', <?php echo $product['selling_price'] ?? 0; ?>, <?php echo $product['stock'] ?? 0; ?>)"
                            class="bg-gray-900 p-2 rounded-lg border border-gray-700 hover:border-amber-500 cursor-pointer transition text-center">
                            <div class="text-[11px] text-amber-400 font-bold">KSh <?php echo number_format($product['selling_price'] ?? 0, 0); ?></div>
                            <div class="text-xs text-white truncate"><?php echo htmlspecialchars($product['name']); ?></div>
                            <div class="text-[10px] text-gray-500 mt-0.5">
                                <span class="stock-indicator <?php echo ($product['stock'] ?? 0) > 20 ? 'stock-high' : (($product['stock'] ?? 0) > 5 ? 'stock-medium' : 'stock-low'); ?>"></span>
                                Stock: <?php echo $product['stock'] ?? 0; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Summary Section -->
        <div class="bg-gray-800/40 border border-gray-700 rounded-xl p-4">
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-3">
                <div class="space-y-1">
                    <div class="flex items-center gap-3 text-sm">
                        <span class="text-gray-500">Subtotal:</span>
                        <span id="subtotal" class="text-white font-mono font-semibold">KSh 0</span>
                    </div>
                    <div class="flex items-center gap-3 text-sm">
                        <span class="text-gray-500">Discount:</span>
                        <span id="discountAmount" class="text-emerald-400 font-mono">-KSh 0</span>
                    </div>
                    <div class="flex items-center gap-3 text-sm">
                        <span class="text-gray-500">Tax (<?php echo $tax_rate; ?>%):</span>
                        <span id="taxAmount" class="text-white font-mono">KSh 0</span>
                    </div>
                </div>
                <div class="text-right">
                    <span class="text-gray-500 text-xs">Total Amount</span>
                    <div class="text-2xl font-bold text-amber-400" id="totalAmount">KSh 0</div>
                </div>
            </div>

            <!-- Quick Amount Buttons -->
            <div class="grid grid-cols-4 sm:grid-cols-6 gap-1.5 mt-3 pt-3 border-t border-gray-700">
                <div class="quick-amount text-xs" onclick="addPayment(100)">+100</div>
                <div class="quick-amount text-xs" onclick="addPayment(500)">+500</div>
                <div class="quick-amount text-xs" onclick="addPayment(1000)">+1K</div>
                <div class="quick-amount text-xs" onclick="addPayment(5000)">+5K</div>
                <div class="quick-amount text-xs" onclick="addPayment(10000)">+10K</div>
                <div class="quick-amount text-xs" onclick="addPayment(20000)">+20K</div>
            </div>
        </div>

        <!-- Notes Section -->
        <div class="bg-gray-800/40 border border-gray-700 rounded-xl p-4">
            <label class="block text-xs text-gray-500 mb-1">Sales Notes</label>
            <textarea name="notes" rows="2" class="form-input text-sm" placeholder="Add any notes about this transaction..."></textarea>
        </div>

        <!-- Action Buttons -->
        <div class="flex flex-col sm:flex-row justify-end gap-2">
            <button type="submit" name="action" value="save" class="px-4 py-2 bg-emerald-500/15 border border-emerald-500/30 rounded-lg text-emerald-400 text-sm font-medium hover:bg-emerald-500/25 transition-all">
                <i class="fas fa-check-circle mr-1"></i> Complete Sale
            </button>
            <button type="submit" name="action" value="draft" class="px-4 py-2 bg-amber-500/15 border border-amber-500/30 rounded-lg text-amber-400 text-sm font-medium hover:bg-amber-500/25 transition-all">
                <i class="fas fa-save mr-1"></i> Save as Draft
            </button>
            <a href="index.php" class="px-4 py-2 bg-red-500/15 border border-red-500/30 rounded-lg text-red-400 text-sm font-medium hover:bg-red-500/25 transition-all text-center">
                <i class="fas fa-times mr-1"></i> Cancel
            </a>
        </div>
    </form>

    <!-- M-Pesa Payment Modal -->
    <div id="mpesaPaymentModal" class="fixed inset-0 bg-black/70  hidden items-center justify-center z-[10000]">
        <div class="bg-gray-800 rounded-xl max-w-md w-full mx-4 shadow-2xl border border-gray-700">
            <div class="bg-gradient-to-r from-emerald-600 to-emerald-700 px-5 py-3 rounded-t-xl">
                <h2 class="text-lg font-bold text-white flex items-center gap-2">
                    <i class="fas fa-mobile-alt"></i>
                    M-Pesa Payment
                </h2>
            </div>

            <div class="p-5 space-y-4">
                <div id="mpesaPhoneStep" class="space-y-3">
                    <div>
                        <label class="block text-sm text-gray-400 mb-1">Customer Phone Number</label>
                        <div class="flex gap-2">
                            <input type="tel" id="mpesaPhone" placeholder="0712345678" class="flex-1 bg-gray-900 border border-gray-700 rounded-lg px-3 py-2 text-white text-sm focus:outline-none focus:border-emerald-500">
                            <button type="button" onclick="validateMpesaPhone()" class="px-4 py-2 bg-emerald-500 text-white rounded-lg text-sm hover:bg-emerald-600 transition">Pay</button>
                        </div>
                        <p class="text-xs text-gray-500 mt-1">Format: 0712345678 or 254712345678</p>
                    </div>
                    <div class="bg-gray-700/30 rounded-lg p-3">
                        <p class="text-gray-400 text-sm">Amount to Pay</p>
                        <p class="text-2xl font-bold text-amber-400"><span id="mpesaAmount">KSh 0</span></p>
                    </div>
                </div>

                <div id="mpesaProcessingStep" class="hidden space-y-3 text-center py-4">
                    <div class="animate-spin mx-auto"><i class="fas fa-spinner text-4xl text-emerald-500"></i></div>
                    <p class="text-gray-300 font-semibold">Initiating Payment...</p>
                    <p class="text-gray-400 text-sm">Sending STK push to your phone</p>
                </div>

                <div id="mpesaConfirmationStep" class="hidden space-y-3 text-center py-4">
                    <div class="text-5xl text-emerald-500"><i class="fas fa-mobile-alt"></i></div>
                    <p class="text-gray-300 font-semibold">Check Your Phone</p>
                    <p class="text-gray-400 text-sm" id="mpesaConfirmPhone"></p>
                    <p class="text-gray-400 text-sm">Enter your M-Pesa PIN to complete payment</p>
                    <div class="bg-gray-700/30 rounded-lg p-2">
                        <p class="text-xs text-gray-500 mb-1">Payment Reference</p>
                        <p class="text-sm font-mono text-amber-400" id="mpesaCheckoutId">-</p>
                    </div>
                    <p class="text-xs text-gray-500">Timeout: <span id="mpesaTimeout">30</span> seconds</p>
                </div>

                <div id="mpesaSuccessStep" class="hidden space-y-3 text-center py-4">
                    <div class="text-5xl text-emerald-500"><i class="fas fa-check-circle"></i></div>
                    <p class="text-gray-300 font-semibold text-lg">Payment Confirmed!</p>
                    <p class="text-gray-400 text-sm">Your M-Pesa payment has been received</p>
                    <div class="bg-emerald-500/10 border border-emerald-500/30 rounded-lg p-2">
                        <p class="text-xs text-gray-500 mb-1">M-Pesa Receipt</p>
                        <p class="text-sm font-mono text-emerald-400" id="mpesaReceipt">-</p>
                    </div>
                </div>

                <div id="mpesaErrorStep" class="hidden space-y-3 text-center py-4">
                    <div class="text-5xl text-red-500"><i class="fas fa-times-circle"></i></div>
                    <p class="text-gray-300 font-semibold text-lg">Payment Failed</p>
                    <p class="text-gray-400 text-sm" id="mpesaErrorMessage">Unable to process payment</p>
                </div>
            </div>

            <div class="bg-gray-900 px-5 py-3 rounded-b-xl flex gap-2">
                <button type="button" id="mpesaCancelBtn" onclick="closeMpesaModal()" class="flex-1 px-4 py-2 border border-gray-700 text-gray-300 rounded-lg text-sm hover:bg-gray-800 transition">Cancel</button>
                <button type="button" id="mpesaRetryBtn" onclick="resetMpesaFlow()" class="hidden flex-1 px-4 py-2 bg-emerald-500 text-white rounded-lg text-sm hover:bg-emerald-600 transition">Try Again</button>
                <button type="button" id="mpesaCompleteBtn" onclick="completeMpesaSale()" class="hidden flex-1 px-4 py-2 bg-emerald-500 text-white rounded-lg text-sm hover:bg-emerald-600 transition">Complete Sale</button>
            </div>
        </div>
    </div>
</div>

<script>
// ============================================
// GLOBAL VARIABLES
// ============================================
let products = <?php echo json_encode($products); ?>;
let discounts = <?php echo json_encode($discounts); ?>;
let vouchers = <?php echo json_encode($vouchers); ?>;
let rowCount = 0;
let taxRate = <?php echo $tax_rate; ?>;
let currencySymbol = '<?php echo $currency_symbol; ?>';
let appliedVoucher = null;
let appliedDiscount = null;

// M-Pesa payment data
let mpesaPaymentData = {
    phone: '',
    amount: 0,
    checkout_request_id: null,
    polling_timeout: null,
    timeout_countdown: null
};
let salesFormData = null;

// ============================================
// INITIALIZATION
// ============================================
document.addEventListener('DOMContentLoaded', function () {
    addProductRow();
    
    const customerSelect = document.getElementById('customerSelect');
    if (customerSelect) customerSelect.addEventListener('change', updateCustomerInfo);
    
    const discountSelect = document.getElementById('discountSelect');
    if (discountSelect) discountSelect.addEventListener('change', applyDiscount);
});

// ============================================
// TOAST NOTIFICATION
// ============================================
function showToast(message, type = 'info', duration = 3000) {
    const container = document.getElementById('toastContainer') || createToastContainer();
    const icons = { success: 'check-circle', error: 'exclamation-circle', warning: 'exclamation-triangle', info: 'info-circle' };
    const colors = { success: 'bg-emerald-500', error: 'bg-red-500', warning: 'bg-amber-500', info: 'bg-blue-500' };
    
    const toast = document.createElement('div');
    toast.className = `${colors[type]} text-white px-4 py-2 rounded-lg shadow-lg text-sm mb-2 animate-slide-in`;
    toast.innerHTML = `<i class="fas fa-${icons[type]} mr-2"></i> ${message}`;
    container.appendChild(toast);
    
    setTimeout(() => { toast.style.opacity = '0'; setTimeout(() => toast.remove(), 300); }, duration);
}

function createToastContainer() {
    const container = document.createElement('div');
    container.id = 'toastContainer';
    container.className = 'fixed top-5 right-5 z-[9999] space-y-2';
    document.body.appendChild(container);
    return container;
}

// ============================================
// PRODUCT ROW MANAGEMENT
// ============================================
function addProductRow() {
    const container = document.getElementById('productsContainer');
    const emptyState = document.getElementById('emptyState');
    if (emptyState) emptyState.style.display = 'none';

    const rowId = rowCount;
    const row = document.createElement('div');
    row.className = 'product-row p-2 fade-in';
    row.id = `row-${rowId}`;

    let productOptions = '<option value="">Select Product</option>';
    if (products && products.length > 0) {
        products.forEach(p => {
            productOptions += `<option value="${p.id}" data-price="${p.selling_price || p.price || 0}" data-stock="${p.stock || 0}" data-name="${p.name}">${p.name} - Stock: ${p.stock || 0}</option>`;
        });
    }

    row.innerHTML = `
        <div class="grid grid-cols-12 gap-2 items-center">
            <div class="col-span-5"><select onchange="updateProduct(${rowId})" id="product-${rowId}" class="form-input text-sm">${productOptions}</select></div>
            <div class="col-span-2"><input type="number" id="qty-${rowId}" onchange="updateQuantity(${rowId})" value="1" min="1" class="form-input text-sm"></div>
            <div class="col-span-2"><input type="number" id="price-${rowId}" onchange="updatePrice(${rowId})" step="0.01" class="form-input text-sm" placeholder="Price"></div>
            <div class="col-span-2 text-right font-mono text-amber-400 text-sm" id="total-${rowId}">KSh 0</div>
            <div class="col-span-1 text-right"><button type="button" onclick="removeRow(${rowId})" class="product-row-remove text-red-400 hover:text-red-300"><i class="fas fa-trash"></i></button></div>
        </div>
    `;
    container.appendChild(row);
    rowCount++;
}

function updateProduct(rowId) {
    const select = document.getElementById(`product-${rowId}`);
    const priceInput = document.getElementById(`price-${rowId}`);
    const qtyInput = document.getElementById(`qty-${rowId}`);
    if (!select || !select.selectedIndex) return;
    
    const selected = select.options[select.selectedIndex];
    if (selected && selected.value) {
        const price = selected.dataset.price;
        const stock = parseInt(selected.dataset.stock);
        priceInput.value = price;
        if (qtyInput && parseInt(qtyInput.value) > stock) {
            qtyInput.value = stock;
            showToast(`Maximum stock available: ${stock}`, 'warning');
        }
        calculateRow(rowId);
    }
}

function updateQuantity(rowId) {
    const qtyInput = document.getElementById(`qty-${rowId}`);
    const select = document.getElementById(`product-${rowId}`);
    if (!select || !select.selectedIndex) return;
    
    const selected = select.options[select.selectedIndex];
    if (selected && selected.value) {
        const stock = parseInt(selected.dataset.stock);
        if (parseInt(qtyInput.value) > stock) {
            qtyInput.value = stock;
            showToast(`Maximum stock available: ${stock}`, 'warning');
        }
        calculateRow(rowId);
    }
}

function updatePrice(rowId) { calculateRow(rowId); }

function calculateRow(rowId) {
    const qty = parseFloat(document.getElementById(`qty-${rowId}`).value) || 0;
    const price = parseFloat(document.getElementById(`price-${rowId}`).value) || 0;
    const total = qty * price;
    document.getElementById(`total-${rowId}`).innerHTML = `${currencySymbol} ${total.toLocaleString()}`;
    calculateSummary();
}

function removeRow(rowId) {
    const row = document.getElementById(`row-${rowId}`);
    if (row) { row.remove(); calculateSummary(); updateEmptyState(); }
}

function updateEmptyState() {
    const container = document.getElementById('productsContainer');
    const emptyState = document.getElementById('emptyState');
    if (emptyState) emptyState.style.display = container.children.length === 0 ? 'block' : 'none';
}

// ============================================
// SUMMARY CALCULATION
// ============================================
function calculateSummary() {
    let subtotal = 0;
    for (let i = 0; i < rowCount; i++) {
        const rowTotal = document.getElementById(`total-${i}`);
        if (rowTotal) subtotal += parseFloat(rowTotal.innerHTML.replace(/[^0-9.-]+/g, '')) || 0;
    }

    let discount = 0;
    if (appliedDiscount) discount = appliedDiscount.type === 'percent' ? subtotal * (appliedDiscount.value / 100) : appliedDiscount.value;
    if (appliedVoucher) discount += appliedVoucher.type === 'percent' ? subtotal * (appliedVoucher.value / 100) : appliedVoucher.value;
    discount = Math.min(discount, subtotal);

    const afterDiscount = subtotal - discount;
    const tax = afterDiscount * (taxRate / 100);
    const total = afterDiscount + tax;

    document.getElementById('subtotal').innerHTML = `${currencySymbol} ${Math.round(subtotal).toLocaleString()}`;
    document.getElementById('discountAmount').innerHTML = `-${currencySymbol} ${Math.round(discount).toLocaleString()}`;
    document.getElementById('taxAmount').innerHTML = `${currencySymbol} ${Math.round(tax).toLocaleString()}`;
    document.getElementById('totalAmount').innerHTML = `${currencySymbol} ${Math.round(total).toLocaleString()}`;
}

// ============================================
// DISCOUNT & VOUCHER FUNCTIONS
// ============================================
function applyDiscount() {
    const select = document.getElementById('discountSelect');
    const selected = select.options[select.selectedIndex];
    
    if (selected && selected.value) {
        appliedDiscount = { id: selected.value, type: selected.dataset.type, value: parseFloat(selected.dataset.value), min: parseFloat(selected.dataset.min) || 0 };
        const subtotal = parseFloat(document.getElementById('subtotal').innerHTML.replace(/[^0-9.-]+/g, '')) || 0;
        if (subtotal < appliedDiscount.min) {
            showToast(`Minimum purchase of ${currencySymbol} ${appliedDiscount.min.toLocaleString()} required`, 'warning');
            appliedDiscount = null;
            select.value = '';
        }
    } else {
        appliedDiscount = null;
    }
    calculateSummary();
}

function validateVoucher() {
    const code = document.getElementById('voucherCode').value.toUpperCase();
    const message = document.getElementById('voucherMessage');
    const voucher = vouchers.find(v => v.code && v.code.toUpperCase() === code);

    if (voucher) {
        appliedVoucher = { code: voucher.code, type: voucher.type, value: parseFloat(voucher.value) };
        message.innerHTML = '<span class="text-emerald-400"><i class="fas fa-check-circle"></i> Voucher applied!</span>';
        showToast('Voucher applied successfully!', 'success');
    } else {
        appliedVoucher = null;
        message.innerHTML = '<span class="text-red-400"><i class="fas fa-exclamation-circle"></i> Invalid voucher code</span>';
        showToast('Invalid voucher code', 'error');
    }
    calculateSummary();
}

// ============================================
// CUSTOMER FUNCTIONS
// ============================================
function updateCustomerInfo() {
    const select = document.getElementById('customerSelect');
    const selected = select.options[select.selectedIndex];
    const infoDiv = document.getElementById('customerInfo');

    if (selected && selected.value) {
        const balance = parseFloat(selected.dataset.balance) || 0;
        const limit = parseFloat(selected.dataset.limit) || 0;
        const ltv = parseFloat(selected.dataset.ltv) || 0;
        const available = limit - balance;

        document.getElementById('creditBalance').innerHTML = `${currencySymbol} ${balance.toLocaleString()}`;
        document.getElementById('availableCredit').innerHTML = `${currencySymbol} ${available.toLocaleString()}`;
        document.getElementById('lifetimeValue').innerHTML = `${currencySymbol} ${ltv.toLocaleString()}`;
        infoDiv.classList.remove('hidden');
    } else {
        infoDiv.classList.add('hidden');
    }
}

// ============================================
// QUICK FUNCTIONS
// ============================================
function quickAddProduct(id, name, price, stock) {
    addProductRow();
    const lastRowId = rowCount - 1;
    const select = document.getElementById(`product-${lastRowId}`);
    if (select) {
        for (let i = 0; i < select.options.length; i++) {
            if (select.options[i].value == id) { select.selectedIndex = i; break; }
        }
        updateProduct(lastRowId);
        showToast(`${name} added to cart`, 'success');
    }
}

function addPayment(amount) {
    const totalElement = document.getElementById('totalAmount');
    const currentTotal = parseFloat(totalElement.innerHTML.replace(/[^0-9.-]+/g, '')) || 0;
    if (currentTotal > 0) showToast(`Quick payment of ${currencySymbol} ${amount.toLocaleString()} will be processed`, 'info');
    else showToast('No items in cart', 'warning');
}

// ============================================
// FORM SUBMIT HANDLER
// ============================================
document.getElementById('salesForm')?.addEventListener('submit', function (e) {
    let hasProducts = false;
    const productsData = [];

    for (let i = 0; i < rowCount; i++) {
        const select = document.getElementById(`product-${i}`);
        const qty = document.getElementById(`qty-${i}`);
        const price = document.getElementById(`price-${i}`);
        if (select && select.value && qty && parseFloat(qty.value) > 0 && price && parseFloat(price.value) > 0) {
            hasProducts = true;
            productsData.push({ id: select.value, quantity: qty.value, price: price.value });
        }
    }

    if (!hasProducts) {
        e.preventDefault();
        showToast('Please add at least one product to the sale', 'warning');
        return;
    }

    const paymentMethod = document.getElementById('paymentMethod')?.value;
    if (paymentMethod === 'mpesa') {
        e.preventDefault();
        const totalText = document.getElementById('totalAmount')?.innerHTML;
        const totalAmount = parseFloat(totalText?.replace(/[^0-9.-]+/g, '')) || 0;
        
        if (totalAmount <= 0) { showToast('Cannot process payment without amount', 'error'); return; }

        salesFormData = {
            products: productsData,
            paymentMethod: paymentMethod,
            customerId: document.getElementById('customerSelect')?.value || '',
            orderType: document.getElementById('orderType')?.value || '',
            notes: document.getElementById('notes')?.value || '',
            discountId: document.getElementById('discountSelect')?.value || '',
            csrf_token: document.querySelector('input[name="csrf_token"]')?.value || '',
            action: e.submitter?.value || 'save'
        };

        mpesaPaymentData.amount = totalAmount;
        showMpesaModal();
        return;
    }

    // Add products as hidden inputs
    productsData.forEach((product, index) => {
        const input1 = document.createElement('input'); input1.type = 'hidden'; input1.name = `product_${index}`; input1.value = product.id;
        const input2 = document.createElement('input'); input2.type = 'hidden'; input2.name = `quantity_${index}`; input2.value = product.quantity;
        const input3 = document.createElement('input'); input3.type = 'hidden'; input3.name = `price_${index}`; input3.value = product.price;
        this.appendChild(input1); this.appendChild(input2); this.appendChild(input3);
    });
    const countInput = document.createElement('input'); countInput.type = 'hidden'; countInput.name = 'product_count'; countInput.value = productsData.length;
    this.appendChild(countInput);
});

// ============================================
// M-PESA FUNCTIONS
// ============================================
function showMpesaModal() {
    const modal = document.getElementById('mpesaPaymentModal');
    if (modal) {
        modal.classList.remove('hidden');
        document.getElementById('mpesaAmount').innerHTML = `KSh ${mpesaPaymentData.amount.toLocaleString('en-US', {maximumFractionDigits: 0})}`;
        document.getElementById('mpesaPhone').focus();
    }
}

function closeMpesaModal() { resetMpesaFlow(); document.getElementById('mpesaPaymentModal')?.classList.add('hidden'); }

function validateMpesaPhone() {
    let phone = document.getElementById('mpesaPhone').value.trim();
    if (!phone) { showToast('Please enter a phone number', 'warning'); document.getElementById('mpesaPhone').focus(); return; }
    
    phone = phone.replace(/[^0-9+]/g, '');
    if (phone.startsWith('+')) phone = phone.substring(1);
    if (phone.startsWith('0') && phone.length === 10) phone = '254' + phone.substring(1);
    else if (phone.length === 9 && phone.startsWith('7')) phone = '254' + phone;
    else if (!phone.startsWith('254') || phone.length !== 12) { showToast('Invalid phone number format', 'error'); return; }
    
    mpesaPaymentData.phone = phone;
    initiateMpesaPayment();
}

function initiateMpesaPayment() {
    document.getElementById('mpesaPhoneStep').classList.add('hidden');
    document.getElementById('mpesaProcessingStep').classList.remove('hidden');
    document.getElementById('mpesaCancelBtn').disabled = true;

    fetch('/public/billing/initiate_payment.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ phone: mpesaPaymentData.phone, amount: mpesaPaymentData.amount, sale_id: 0, csrf_token: document.querySelector('input[name="csrf_token"]')?.value || '' })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success && data.checkout_id) {
            mpesaPaymentData.checkout_request_id = data.checkout_id;
            document.getElementById('mpesaProcessingStep').classList.add('hidden');
            document.getElementById('mpesaConfirmationStep').classList.remove('hidden');
            document.getElementById('mpesaConfirmPhone').innerHTML = formatPhoneDisplay(mpesaPaymentData.phone);
            document.getElementById('mpesaCheckoutId').innerHTML = mpesaPaymentData.checkout_request_id;
            startPaymentPolling();
            startTimeoutCountdown();
        } else {
            showMpesaErrorStep(data.message || 'Failed to initiate payment');
        }
    })
    .catch(error => { console.error('M-Pesa error:', error); showMpesaErrorStep('Payment initiation failed'); });
}

function startPaymentPolling() {
    let attempts = 0;
    const poll = () => {
        attempts++;
        if (attempts > 60) { showMpesaErrorStep('Payment verification timeout'); return; }

        fetch('/public/billing/check_payment_status.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({ checkout_request_id: mpesaPaymentData.checkout_request_id, csrf_token: document.querySelector('input[name="csrf_token"]')?.value || '' })
        })
        .then(response => response.json())
        .then(data => {
            if (data.status === 'completed') {
                if (mpesaPaymentData.timeout_countdown) clearInterval(mpesaPaymentData.timeout_countdown);
                mpesaPaymentData.receipt = data.mpesa_receipt || '';
                document.getElementById('mpesaConfirmationStep').classList.add('hidden');
                document.getElementById('mpesaSuccessStep').classList.remove('hidden');
                document.getElementById('mpesaReceipt').innerHTML = mpesaPaymentData.receipt || 'N/A';
                document.getElementById('mpesaCancelBtn').classList.add('hidden');
                document.getElementById('mpesaCompleteBtn').classList.remove('hidden');
            } else if (data.status === 'failed') {
                showMpesaErrorStep(data.result_desc || 'Payment was declined');
            } else {
                mpesaPaymentData.polling_timeout = setTimeout(poll, 2000);
            }
        })
        .catch(() => { mpesaPaymentData.polling_timeout = setTimeout(poll, 2000); });
    };
    poll();
}

function startTimeoutCountdown() {
    let timeLeft = 30;
    document.getElementById('mpesaTimeout').innerHTML = timeLeft;
    mpesaPaymentData.timeout_countdown = setInterval(() => {
        timeLeft--;
        document.getElementById('mpesaTimeout').innerHTML = timeLeft;
        if (timeLeft <= 0) {
            clearInterval(mpesaPaymentData.timeout_countdown);
            if (mpesaPaymentData.polling_timeout) clearTimeout(mpesaPaymentData.polling_timeout);
            showMpesaErrorStep('Payment request timed out');
        }
    }, 1000);
}

function showMpesaErrorStep(message) {
    document.getElementById('mpesaPhoneStep').classList.add('hidden');
    document.getElementById('mpesaProcessingStep').classList.add('hidden');
    document.getElementById('mpesaConfirmationStep').classList.add('hidden');
    document.getElementById('mpesaSuccessStep').classList.add('hidden');
    document.getElementById('mpesaErrorStep').classList.remove('hidden');
    document.getElementById('mpesaErrorMessage').innerHTML = message;
    if (mpesaPaymentData.polling_timeout) clearTimeout(mpesaPaymentData.polling_timeout);
    if (mpesaPaymentData.timeout_countdown) clearInterval(mpesaPaymentData.timeout_countdown);
    document.getElementById('mpesaCancelBtn').classList.remove('hidden');
    document.getElementById('mpesaRetryBtn').classList.remove('hidden');
    document.getElementById('mpesaCancelBtn').disabled = false;
}

function resetMpesaFlow() {
    if (mpesaPaymentData.polling_timeout) clearTimeout(mpesaPaymentData.polling_timeout);
    if (mpesaPaymentData.timeout_countdown) clearInterval(mpesaPaymentData.timeout_countdown);
    
    document.getElementById('mpesaPhoneStep').classList.remove('hidden');
    document.getElementById('mpesaProcessingStep').classList.add('hidden');
    document.getElementById('mpesaConfirmationStep').classList.add('hidden');
    document.getElementById('mpesaSuccessStep').classList.add('hidden');
    document.getElementById('mpesaErrorStep').classList.add('hidden');
    
    document.getElementById('mpesaCancelBtn').classList.remove('hidden');
    document.getElementById('mpesaCancelBtn').disabled = false;
    document.getElementById('mpesaRetryBtn').classList.add('hidden');
    document.getElementById('mpesaCompleteBtn').classList.add('hidden');
    
    document.getElementById('mpesaPhone').value = '';
    mpesaPaymentData = { phone: '', amount: 0, checkout_request_id: null, polling_timeout: null, timeout_countdown: null };
    document.getElementById('mpesaPhone').focus();
}

function completeMpesaSale() {
    if (!salesFormData) { showToast('Error: Sale data missing', 'error'); closeMpesaModal(); return; }

    const form = document.getElementById('salesForm');
    salesFormData.products.forEach((product, index) => {
        const input1 = document.createElement('input'); input1.type = 'hidden'; input1.name = `product_${index}`; input1.value = product.id;
        const input2 = document.createElement('input'); input2.type = 'hidden'; input2.name = `quantity_${index}`; input2.value = product.quantity;
        const input3 = document.createElement('input'); input3.type = 'hidden'; input3.name = `price_${index}`; input3.value = product.price;
        form.appendChild(input1); form.appendChild(input2); form.appendChild(input3);
    });
    const countInput = document.createElement('input'); countInput.type = 'hidden'; countInput.name = 'product_count'; countInput.value = salesFormData.products.length;
    form.appendChild(countInput);
    
    const mpesaCheckoutInput = document.createElement('input'); mpesaCheckoutInput.type = 'hidden'; mpesaCheckoutInput.name = 'mpesa_checkout_request_id'; mpesaCheckoutInput.value = mpesaPaymentData.checkout_request_id;
    form.appendChild(mpesaCheckoutInput);
    
    closeMpesaModal();
    document.getElementById('salesForm').submit();
}

function formatPhoneDisplay(phone) {
    if (phone.startsWith('254')) {
        const local = '0' + phone.substring(3);
        return local.slice(0, 4) + ' ' + local.slice(4, 7) + ' ' + local.slice(7);
    }
    return phone;
}

// Keyboard Shortcuts
document.addEventListener('keydown', function (e) {
    if (e.target.matches('input, textarea, select')) return;
    if (e.ctrlKey && e.key === 'n') { e.preventDefault(); addProductRow(); }
    if (e.ctrlKey && e.key === 's') { e.preventDefault(); document.querySelector('button[name="action"][value="save"]')?.click(); }
    if (e.ctrlKey && e.key === 'd') { e.preventDefault(); document.querySelector('button[name="action"][value="draft"]')?.click(); }
});
</script>

</body>
</html>

<?php
$page_content = ob_get_clean();
require_once __DIR__ . '/../layouts/app.php';
require_once __DIR__ . '/../layouts/app_close.php';
?>