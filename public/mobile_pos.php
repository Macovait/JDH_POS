<?php
/**
 * Mobile POS Web Interface
 * Browser-based mobile POS interface for testing
 */

require_once __DIR__ . '/../src/paths.php';
safe_require('auth.php', 'src', true);

// Branch filter for multi-tenant isolation
$current_branch_id = get_current_branch_id();
safe_require('db.php', 'src', true);
safe_require('functions.php', 'src', true);

session_start();

// Prevent iframe embedding
header('X-Frame-Options: DENY');
header('Content-Security-Policy: frame-ancestors \'none\'');

// Check if being loaded in an iframe
if (isset($_SERVER['HTTP_REFERER'])) {
    $referer_host = parse_url($_SERVER['HTTP_REFERER'], PHP_URL_HOST);
    $current_host = $_SERVER['HTTP_HOST'];

    // Allow same domain, block cross-domain iframes
    if ($referer_host && $referer_host !== $current_host) {
        http_response_code(403);
        die('This page cannot be embedded in iframes from external domains.');
    }
}

require_login();

$pdo = get_db_connection();
$tenant_id = get_current_tenant_id();
$user_id = get_current_user_id();

// Get API key
$stmt = $pdo->prepare("SELECT api_key FROM tenant_api_keys WHERE tenant_id = ? AND status = 'active' LIMIT 1");
$stmt->execute([$tenant_id]);
$api_key = $stmt->fetch()['api_key'] ?? '';

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="theme-color" content="#1e293b">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="Mobile POS">

    <title>Mobile POS - <?php echo htmlspecialchars($settings['company_name'] ?? 'POS System'); ?></title>

    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.5.1/css/all.min.css">

    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Inter', sans-serif;
        }

        :root {
            --primary: #3b82f6;
            --primary-dark: #1d4ed8;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
            --dark: #0f172a;
            --darker: #020617;
            --card: #1e293b;
            --text: #e2e8f0;
            --text-muted: #94a3b8;
            --border: #334155;
        }

        body {
            background: var(--dark);
            color: var(--text);
            height: 100vh;
            overflow: hidden;
        }

        .app {
            display: flex;
            flex-direction: column;
            height: 100vh;
        }

        .header {
            background: var(--card);
            padding: 15px;
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .header h1 {
            font-size: 1.2rem;
            font-weight: 600;
        }

        .header .user {
            font-size: 0.9rem;
            color: var(--text-muted);
        }

        .main {
            flex: 1;
            display: flex;
            overflow: hidden;
        }

        .sidebar {
            width: 80px;
            background: var(--card);
            border-right: 1px solid var(--border);
            padding: 10px 0;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 15px;
        }

        .nav-btn {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            border: none;
            background: transparent;
            color: var(--text-muted);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .nav-btn.active {
            background: var(--primary);
            color: white;
        }

        .nav-btn:hover {
            background: var(--border);
            color: var(--text);
        }

        .content {
            flex: 1;
            padding: 20px;
            overflow-y: auto;
            display: flex;
            flex-direction: column;
        }

        .screen {
            display: none;
            height: 100%;
        }

        .screen.active {
            display: flex;
            flex-direction: column;
        }

        .products-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }

        .product-card {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 15px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .product-card:hover {
            border-color: var(--primary);
            transform: translateY(-2px);
        }

        .product-card.selected {
            border-color: var(--primary);
            background: rgba(59, 130, 246, 0.1);
        }

        .product-image {
            width: 60px;
            height: 60px;
            background: var(--border);
            border-radius: 8px;
            margin: 0 auto 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: var(--text-muted);
        }

        .product-name {
            font-size: 0.9rem;
            font-weight: 500;
            margin-bottom: 5px;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .product-price {
            font-size: 1rem;
            font-weight: 600;
            color: var(--primary);
        }

        .product-tag-dots {
            display: flex;
            align-items: center;
            gap: 3px;
            margin: 2px 0;
            justify-content: center;
            min-height: 8px;
        }
        .tag-dot {
            width: 6px;
            height: 6px;
            border-radius: 50%;
            display: inline-block;
            border: 1px solid rgba(255,255,255,0.2);
        }
        .tag-dot-more {
            font-size: 0.55rem;
            color: var(--text-muted);
            line-height: 1;
        }

        .cart {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 20px;
            display: flex;
            flex-direction: column;
            height: 100%;
        }

        .cart-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .cart-title {
            font-size: 1.2rem;
            font-weight: 600;
        }

        .cart-clear {
            background: var(--danger);
            color: white;
            border: none;
            padding: 8px 12px;
            border-radius: 8px;
            font-size: 0.8rem;
            cursor: pointer;
        }

        .cart-items {
            flex: 1;
            overflow-y: auto;
            margin-bottom: 20px;
        }

        .cart-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px 0;
            border-bottom: 1px solid var(--border);
        }

        .cart-item:last-child {
            border-bottom: none;
        }

        .cart-item-info {
            flex: 1;
        }

        .cart-item-name {
            font-weight: 500;
            margin-bottom: 2px;
        }

        .cart-item-price {
            color: var(--text-muted);
            font-size: 0.9rem;
        }

        .cart-item-controls {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .qty-btn {
            width: 30px;
            height: 30px;
            border: 1px solid var(--border);
            background: var(--dark);
            color: var(--text);
            border-radius: 6px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .cart-total {
            border-top: 1px solid var(--border);
            padding-top: 20px;
        }

        .total-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
        }

        .total-row.final {
            font-size: 1.2rem;
            font-weight: 600;
            color: var(--primary);
        }

        .checkout-btn {
            width: 100%;
            background: var(--primary);
            color: white;
            border: none;
            padding: 15px;
            border-radius: 12px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            margin-top: 10px;
            transition: all 0.3s ease;
        }

        .checkout-btn:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
        }

        .payment-modal {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.8);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 1000;
        }

        .payment-content {
            background: var(--card);
            border-radius: 16px;
            padding: 30px;
            width: 90%;
            max-width: 400px;
        }

        .payment-methods {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin: 20px 0;
        }

        .payment-method {
            padding: 20px;
            border: 2px solid var(--border);
            border-radius: 12px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .payment-method:hover {
            border-color: var(--primary);
        }

        .payment-method.selected {
            border-color: var(--primary);
            background: rgba(59, 130, 246, 0.1);
        }

        .payment-method i {
            font-size: 2rem;
            margin-bottom: 10px;
            color: var(--text-muted);
        }

        .payment-method.selected i {
            color: var(--primary);
        }

        .hidden {
            display: none !important;
        }

        .toast {
            position: fixed;
            bottom: 20px;
            left: 50%;
            transform: translateX(-50%);
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 15px 20px;
            z-index: 1001;
            box-shadow: 0 10px 25px rgba(0,0,0,0.3);
        }

        .toast.success {
            border-color: var(--success);
        }

        .toast.error {
            border-color: var(--danger);
        }

        @media (max-width: 768px) {
            .sidebar {
                width: 60px;
            }

            .nav-btn {
                width: 45px;
                height: 45px;
                font-size: 1rem;
            }

            .products-grid {
                grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
                gap: 10px;
            }

            .content {
                padding: 15px;
            }
        }

        /* Loading spinner */
        .loading {
            display: flex;
            align-items: center;
            justify-content: center;
            height: 200px;
            color: var(--text-muted);
        }

        .spinner {
            border: 3px solid var(--border);
            border-top: 3px solid var(--primary);
            border-radius: 50%;
            width: 30px;
            height: 30px;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        /* Error states */
        .error-state {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            height: 200px;
            color: var(--text-muted);
        }

        .error-state i {
            font-size: 3rem;
            margin-bottom: 15px;
            color: var(--danger);
        }

        .retry-btn {
            background: var(--primary);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            cursor: pointer;
            margin-top: 10px;
        }
    </style>
</head>
<body>
    <div class="app">
        <div class="header">
            <h1>Mobile POS</h1>
            <div class="user"><?php echo htmlspecialchars(get_current_user_name()); ?></div>
        </div>

        <div class="main">
            <div class="sidebar">
                <button class="nav-btn active" data-screen="products">
                    <i class="fas fa-utensils"></i>
                </button>
                <button class="nav-btn" data-screen="cart">
                    <i class="fas fa-shopping-cart"></i>
                </button>
                <button class="nav-btn" data-screen="orders">
                    <i class="fas fa-list"></i>
                </button>
                <button class="nav-btn" data-screen="customers">
                    <i class="fas fa-users"></i>
                </button>
                <button class="nav-btn" data-screen="reports">
                    <i class="fas fa-chart-bar"></i>
                </button>
            </div>

            <div class="content">
                <!-- Products Screen -->
                <div class="screen active" id="products-screen">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                        <h2>Menu</h2>
                        <div>
                            <select id="category-filter" style="background: var(--card); border: 1px solid var(--border); color: var(--text); padding: 8px 12px; border-radius: 8px;">
                                <option value="">All Categories</option>
                            </select>
                        </div>
                    </div>
                    <div class="products-grid" id="products-grid">
                        <div class="loading">
                            <div class="spinner"></div>
                            <div style="margin-left: 15px;">Loading products...</div>
                        </div>
                    </div>
                </div>

                <!-- Cart Screen -->
                <div class="screen" id="cart-screen">
                    <div class="cart">
                        <div class="cart-header">
                            <h2 class="cart-title">Cart (<span id="cart-count">0</span>)</h2>
                            <button class="cart-clear" onclick="clearCart()">Clear</button>
                        </div>

                        <div class="cart-items" id="cart-items">
                            <div style="color: var(--text-muted); text-align: center; padding: 40px;">
                                Cart is empty
                            </div>
                        </div>

                        <div class="cart-total">
                            <div class="total-row">
                                <span>Subtotal:</span>
                                <span id="subtotal">$0.00</span>
                            </div>
                            <div class="total-row">
                                <span>Tax:</span>
                                <span id="tax">$0.00</span>
                            </div>
                            <div class="total-row final">
                                <span>Total:</span>
                                <span id="total">$0.00</span>
                            </div>

                            <button class="checkout-btn" onclick="showPaymentModal()">Checkout</button>
                        </div>
                    </div>
                </div>

                <!-- Orders Screen -->
                <div class="screen" id="orders-screen">
                    <h2>Recent Orders</h2>
                    <div id="orders-list" style="margin-top: 20px;">
                        <div class="loading">
                            <div class="spinner"></div>
                            <div style="margin-left: 15px;">Loading orders...</div>
                        </div>
                    </div>
                </div>

                <!-- Customers Screen -->
                <div class="screen" id="customers-screen">
                    <h2>Customers</h2>
                    <div id="customers-list" style="margin-top: 20px;">
                        <div class="loading">
                            <div class="spinner"></div>
                            <div style="margin-left: 15px;">Loading customers...</div>
                        </div>
                    </div>
                </div>

                <!-- Reports Screen -->
                <div class="screen" id="reports-screen">
                    <h2>Today's Summary</h2>
                    <div id="reports-content" style="margin-top: 20px;">
                        <div class="loading">
                            <div class="spinner"></div>
                            <div style="margin-left: 15px;">Loading reports...</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Payment Modal -->
    <div class="payment-modal hidden" id="payment-modal">
        <div class="payment-content">
            <h3 style="margin-bottom: 20px; text-align: center;">Select Payment Method</h3>

            <div class="payment-methods">
                <div class="payment-method selected" data-method="cash" onclick="selectPaymentMethod('cash')">
                    <i class="fas fa-money-bill-wave"></i>
                    <div>Cash</div>
                </div>
                <div class="payment-method" data-method="card" onclick="selectPaymentMethod('card')">
                    <i class="fas fa-credit-card"></i>
                    <div>Card</div>
                </div>
                <div class="payment-method" data-method="mpesa" onclick="selectPaymentMethod('mpesa')">
                    <i class="fas fa-mobile-alt"></i>
                    <div>M-Pesa</div>
                </div>
                <div class="payment-method" data-method="split" onclick="selectPaymentMethod('split')">
                    <i class="fas fa-split"></i>
                    <div>Split</div>
                </div>
            </div>

            <div style="display: flex; gap: 10px; margin-top: 20px;">
                <button class="checkout-btn" style="flex: 1;" onclick="processPayment()">Complete Payment</button>
                <button class="cart-clear" onclick="hidePaymentModal()">Cancel</button>
            </div>
        </div>
    </div>

    <script>
        // Force cache refresh
        console.log('Mobile POS v<?php echo time(); ?> loaded');
        // Global state
        let products = [];
        let categories = [];
        let cart = [];
        let jwtToken = null;
        const apiKey = '<?php echo $api_key; ?>';
        const baseUrl = '/JDH_POS/public/api/mobile';
        const IMAGE_BASE_URL = '<?php echo function_exists('base_url') ? rtrim(base_url(''), '/') : ''; ?>';

        // Initialize app
        document.addEventListener('DOMContentLoaded', function() {
            console.log('Mobile POS initializing...');
            console.log('Browser supports async/await:', typeof async !== 'undefined');
            setupNavigation();
            loadProducts(); // Load initial products
        });

        // Navigation
        function setupNavigation() {
            document.querySelectorAll('.nav-btn').forEach(btn => {
                btn.addEventListener('click', () => {
                    const screen = btn.dataset.screen;
                    switchScreen(screen);
                });
            });
        }

        function switchScreen(screenName) {
            document.querySelectorAll('.nav-btn').forEach(btn => btn.classList.remove('active'));
            document.querySelectorAll('.screen').forEach(screen => screen.classList.remove('active'));

            document.querySelector(`[data-screen="${screenName}"]`).classList.add('active');
            document.getElementById(`${screenName}-screen`).classList.add('active');

            // Load screen data
            switch(screenName) {
                case 'products':
                    if (products.length === 0) {
                        loadProducts().catch(error => {
                            console.error('Failed to load products:', error);
                            showErrorState('products-grid', 'Failed to load products. Please refresh the page.');
                        });
                    }
                    break;
                case 'cart':
                    updateCartDisplay();
                    break;
                case 'orders':
                    if (!document.getElementById('orders-list').querySelector('.error-state')) {
                        loadOrders().catch(error => {
                            console.error('Failed to load orders:', error);
                            showErrorState('orders-list', 'Failed to load orders. Please try again.');
                        });
                    }
                    break;
                case 'customers':
                    if (!document.getElementById('customers-list').querySelector('.error-state')) {
                        loadCustomers().catch(error => {
                            console.error('Failed to load customers:', error);
                            showErrorState('customers-list', 'Failed to load customers. Please try again.');
                        });
                    }
                    break;
                case 'reports':
                    if (!document.getElementById('reports-content').querySelector('.error-state')) {
                        loadReports().catch(error => {
                            console.error('Failed to load reports:', error);
                            showErrorState('reports-content', 'Failed to load reports. Please try again.');
                        });
                    }
                    break;
            }
        }

        // Web interface uses session authentication - no JWT required

        // API helper (web interface uses session auth, no JWT needed)
        function apiRequest(method, endpoint, data = null) {
            console.log('API Request:', method, endpoint);
            return new Promise((resolve, reject) => {
                const headers = {
                    'Content-Type': 'application/json',
                    'X-API-Key': apiKey
                };

                // Web interface doesn't need JWT - uses session authentication
                const config = {
                    method: method,
                    headers: headers
                };

                if (data && (method === 'POST' || method === 'PUT')) {
                    config.body = JSON.stringify(data);
                }

                console.log('Fetching:', baseUrl + endpoint);
                fetch(baseUrl + endpoint, config)
                    .then(response => {
                        console.log('Response status:', response.status);
                        return response.json();
                    })
                    .then(data => {
                        console.log('Response data:', data);
                        resolve(data);
                    })
                    .catch(error => {
                        console.error('API Error:', error);
                        reject(error);
                    });
            });
        }

        // Products
        function loadProducts() {
            const grid = document.getElementById('products-grid');
            grid.innerHTML = '<div class="loading"><div class="spinner"></div><div style="margin-left: 15px;">Loading products...</div></div>';

            apiRequest('GET', '/pos/products?branch_id=1')
                .then(response => {
                    if (response.success) {
                        products = response.data.products || [];
                        categories = response.data.categories || [];

                        updateCategoryFilter();
                        displayProducts();
                    } else {
                        throw new Error(response.message || 'Failed to load products');
                    }
                })
                .catch(error => {
                    console.error('Products error:', error);
                    showErrorState('products-grid', 'Failed to load products. Please check your connection.');
                });
        }

        function updateCategoryFilter() {
            const select = document.getElementById('category-filter');
            select.innerHTML = '<option value="">All Categories</option>';
            categories.forEach(cat => {
                select.innerHTML += `<option value="${cat.id}">${cat.name}</option>`;
            });

            select.addEventListener('change', () => {
                displayProducts(select.value);
            });
        }

        function displayProducts(categoryId = '') {
            const grid = document.getElementById('products-grid');
            let filteredProducts = products;

            if (categoryId) {
                filteredProducts = products.filter(p => p.category_id == categoryId);
            }

            if (filteredProducts.length === 0) {
                grid.innerHTML = '<div style="color: var(--text-muted); text-align: center; padding: 40px;">No products found in this category</div>';
                return;
            }

            grid.innerHTML = filteredProducts.map(product => {
                const tagDots = (product.tags && product.tags.length)
                    ? `<div class="product-tag-dots">${product.tags.slice(0, 3).map(t => `<span class="tag-dot" style="background:${t.color}" title="${t.name}"></span>`).join('')}${product.tags.length > 3 ? '<span class="tag-dot-more">+</span>' : ''}</div>`
                    : '';
                return `
                <div class="product-card" onclick="addToCart(${product.id})">
                    <div class="product-image">
                        ${product.image ? `<img src="${product.image}" style="width: 100%; height: 100%; object-fit: cover; border-radius: 6px;" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';" alt="${product.name}">` : ''}
                        <i class="fas fa-utensils" style="${product.image ? 'display: none;' : ''}"></i>
                    </div>
                    <div class="product-name">${product.name}</div>
                    ${tagDots}
                    <div class="product-price">$${parseFloat(product.price).toFixed(2)}</div>
                </div>
            `}).join('');
        }

        function showErrorState(containerId, message) {
            const container = document.getElementById(containerId);
            container.innerHTML = `
                <div class="error-state">
                    <i class="fas fa-exclamation-triangle"></i>
                    <div>${message}</div>
                    <button class="retry-btn" onclick="retryLoad('${containerId}')">Retry</button>
                </div>
            `;
        }

        function retryLoad(containerId) {
            switch(containerId) {
                case 'products-grid':
                    loadProducts().catch(error => {
                        console.error('Retry load products failed:', error);
                        showErrorState('products-grid', 'Failed to load products after retry.');
                    });
                    break;
                case 'orders-list':
                    loadOrders().catch(error => {
                        console.error('Retry load orders failed:', error);
                        showErrorState('orders-list', 'Failed to load orders after retry.');
                    });
                    break;
                case 'customers-list':
                    loadCustomers().catch(error => {
                        console.error('Retry load customers failed:', error);
                        showErrorState('customers-list', 'Failed to load customers after retry.');
                    });
                    break;
                case 'reports-content':
                    loadReports().catch(error => {
                        console.error('Retry load reports failed:', error);
                        showErrorState('reports-content', 'Failed to load reports after retry.');
                    });
                    break;
            }
        }

        // Cart management
        function addToCart(productId) {
            const product = products.find(p => p.id == productId);
            if (!product) return;

            const existing = cart.find(item => item.id === productId);
            if (existing) {
                existing.quantity += 1;
            } else {
                cart.push({
                    id: product.id,
                    name: product.name,
                    price: parseFloat(product.price),
                    quantity: 1
                });
            }

            updateCartDisplay();
            switchScreen('cart');
            showToast('Added to cart', 'success');
        }

        function updateCartDisplay() {
            const cartItems = document.getElementById('cart-items');
            const cartCount = document.getElementById('cart-count');
            const subtotal = document.getElementById('subtotal');
            const tax = document.getElementById('tax');
            const total = document.getElementById('total');

            cartCount.textContent = cart.length;

            if (cart.length === 0) {
                cartItems.innerHTML = '<div style="color: var(--text-muted); text-align: center; padding: 40px;">Cart is empty</div>';
                subtotal.textContent = '$0.00';
                tax.textContent = '$0.00';
                total.textContent = '$0.00';
                return;
            }

            cartItems.innerHTML = cart.map(item => `
                <div class="cart-item">
                    <div class="cart-item-info">
                        <div class="cart-item-name">${item.name}</div>
                        <div class="cart-item-price">$${item.price.toFixed(2)} each</div>
                    </div>
                    <div class="cart-item-controls">
                        <button class="qty-btn" onclick="updateQuantity(${item.id}, ${item.quantity - 1})">-</button>
                        <span style="min-width: 30px; text-align: center;">${item.quantity}</span>
                        <button class="qty-btn" onclick="updateQuantity(${item.id}, ${item.quantity + 1})">+</button>
                    </div>
                </div>
            `).join('');

            const cartSubtotal = cart.reduce((sum, item) => sum + (item.price * item.quantity), 0);
            const cartTax = cartSubtotal * 0.16; // 16% tax
            const cartTotal = cartSubtotal + cartTax;

            subtotal.textContent = '$' + cartSubtotal.toFixed(2);
            tax.textContent = '$' + cartTax.toFixed(2);
            total.textContent = '$' + cartTotal.toFixed(2);
        }

        function updateQuantity(productId, newQuantity) {
            if (newQuantity <= 0) {
                cart = cart.filter(item => item.id !== productId);
            } else {
                const item = cart.find(item => item.id === productId);
                if (item) {
                    item.quantity = newQuantity;
                }
            }
            updateCartDisplay();
        }

        function clearCart() {
            cart = [];
            updateCartDisplay();
            showToast('Cart cleared', 'success');
        }

        // Payment
        let selectedPaymentMethod = 'cash';

        function showPaymentModal() {
            if (cart.length === 0) {
                showToast('Cart is empty', 'error');
                return;
            }
            document.getElementById('payment-modal').classList.remove('hidden');
        }

        function hidePaymentModal() {
            document.getElementById('payment-modal').classList.add('hidden');
        }

        function selectPaymentMethod(method) {
            selectedPaymentMethod = method;
            document.querySelectorAll('.payment-method').forEach(el => {
                el.classList.remove('selected');
            });
            document.querySelector(`[data-method="${method}"]`).classList.add('selected');
        }

        function processPayment() {
            apiRequest('POST', '/pos/create_sale', {
                branch_id: 1,
                user_id: <?php echo $user_id; ?>,
                items: cart.map(item => ({
                    product_id: item.id,
                    name: item.name,
                    quantity: item.quantity,
                    price: item.price,
                    cost_price: item.price * 0.6 // Estimated cost
                })),
                total: parseFloat(document.getElementById('total').textContent.replace('$', '')),
                discount: 0,
                payment_method: selectedPaymentMethod,
                device_id: 'web-pos-' + Date.now()
            })
            .then(response => {
                if (response.success) {
                    showToast('Sale completed successfully!', 'success');
                    cart = [];
                    updateCartDisplay();
                    hidePaymentModal();
                    switchScreen('products');
                } else {
                    showToast('Sale failed: ' + (response.message || 'Unknown error'), 'error');
                }
            })
            .catch(error => {
                console.error('Payment error:', error);
                showToast('Payment error: ' + error.message, 'error');
            });
        }

        // Other screens
        function loadOrders() {
            const ordersList = document.getElementById('orders-list');
            ordersList.innerHTML = '<div class="loading"><div class="spinner"></div><div style="margin-left: 15px;">Loading orders...</div></div>';

            apiRequest('GET', '/pos/orders?limit=10')
                .then(response => {
                    if (response.success) {
                        const orders = response.data.orders || [];
                        if (orders.length === 0) {
                            ordersList.innerHTML = '<div style="color: var(--text-muted); text-align: center; padding: 40px;">No recent orders</div>';
                            return;
                        }

                        ordersList.innerHTML = orders.map(order => `
                            <div style="background: var(--card); border: 1px solid var(--border); border-radius: 8px; padding: 15px; margin-bottom: 10px;">
                                <div style="display: flex; justify-content: space-between; margin-bottom: 10px;">
                                    <strong>${order.order_number || 'Order #' + order.id}</strong>
                                    <span style="color: var(--text-muted);">${new Date(order.created_at).toLocaleDateString()}</span>
                                </div>
                                <div style="color: var(--text-muted);">Status: ${order.status}</div>
                                <div>Total: $${parseFloat(order.total).toFixed(2)}</div>
                            </div>
                        `).join('');
                    } else {
                        throw new Error(response.message || 'Failed to load orders');
                    }
                })
                .catch(error => {
                    console.error('Orders error:', error);
                    showErrorState('orders-list', 'Failed to load orders. Please check your connection.');
                });
        }

        function loadCustomers() {
            const customersList = document.getElementById('customers-list');
            customersList.innerHTML = '<div class="loading"><div class="spinner"></div><div style="margin-left: 15px;">Loading customers...</div></div>';

            apiRequest('GET', '/pos/customers?limit=20')
                .then(response => {
                    if (response.success) {
                        const customers = response.data.customers || [];
                        if (customers.length === 0) {
                            customersList.innerHTML = '<div style="color: var(--text-muted); text-align: center; padding: 40px;">No customers found</div>';
                            return;
                        }

                        customersList.innerHTML = customers.map(customer => `
                            <div style="background: var(--card); border: 1px solid var(--border); border-radius: 8px; padding: 15px; margin-bottom: 10px;">
                                <div style="display: flex; justify-content: space-between;">
                                    <strong>${customer.name}</strong>
                                    <span style="color: var(--text-muted);">Points: ${customer.loyalty_points || 0}</span>
                                </div>
                                <div style="color: var(--text-muted); margin-top: 5px;">${customer.phone || 'No phone'}</div>
                            </div>
                        `).join('');
                    } else {
                        throw new Error(response.message || 'Failed to load customers');
                    }
                })
                .catch(error => {
                    console.error('Customers error:', error);
                    showErrorState('customers-list', 'Failed to load customers. Please check your connection.');
                });
        }

        function loadReports() {
            const reportsContent = document.getElementById('reports-content');
            reportsContent.innerHTML = '<div class="loading"><div class="spinner"></div><div style="margin-left: 15px;">Loading reports...</div></div>';

            apiRequest('GET', '/manager/dashboard?branch_id=1')
                .then(response => {
                    if (response.success) {
                        const data = response.data;
                        reportsContent.innerHTML = `
                            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 15px; margin-bottom: 20px;">
                                <div style="background: var(--card); border: 1px solid var(--border); border-radius: 8px; padding: 15px; text-align: center;">
                                    <div style="font-size: 1.5rem; font-weight: 600; color: var(--primary);">${data.today_sales?.total_sales || 0}</div>
                                    <div style="color: var(--text-muted);">Today's Sales</div>
                                </div>
                                <div style="background: var(--card); border: 1px solid var(--border); border-radius: 8px; padding: 15px; text-align: center;">
                                    <div style="font-size: 1.5rem; font-weight: 600; color: var(--success);">$${parseFloat(data.today_sales?.total_revenue || 0).toFixed(2)}</div>
                                    <div style="color: var(--text-muted);">Today's Revenue</div>
                                </div>
                            </div>
                            <div style="color: var(--text-muted); text-align: center; margin-top: 20px;">
                                <small>Real-time business metrics</small>
                            </div>
                        `;
                    } else {
                        throw new Error(response.message || 'Failed to load reports');
                    }
                })
                .catch(error => {
                    console.error('Reports error:', error);
                    showErrorState('reports-content', 'Failed to load reports. Please check your connection.');
                });
        }

        // Toast notifications
        function showToast(message, type = 'info') {
            const toast = document.createElement('div');
            toast.className = `toast ${type}`;
            toast.innerHTML = `
                <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-circle' : 'info-circle'}"></i>
                ${message}
            `;
            document.body.appendChild(toast);

            setTimeout(() => {
                toast.remove();
            }, 3000);
        }

        // PWA features completely disabled
        // Remove any service worker registrations
    </script>
</body>
</html>