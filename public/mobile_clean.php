<?php
/**
 * Clean Mobile POS - No service workers, clean JavaScript
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

require_login();

$pdo = get_db_connection();
$tenant_id = get_current_tenant_id();

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
    <title>Clean Mobile POS - <?php echo htmlspecialchars($settings['company_name'] ?? 'POS System'); ?></title>

    <!-- Simple, reliable CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.5.1/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; }
        body { background: #0f172a; color: #e2e8f0; height: 100vh; overflow: hidden; }
        .container { display: flex; flex-direction: column; height: 100vh; }
        .header { background: #1e293b; padding: 15px; border-bottom: 1px solid #334155; text-align: center; }
        .header h1 { font-size: 1.5rem; font-weight: 600; }
        .header .user { font-size: 0.9rem; color: #94a3b8; margin-top: 5px; }
        .content { flex: 1; padding: 20px; overflow-y: auto; }
        .status { padding: 15px; margin: 10px 0; border-radius: 8px; }
        .success { background: rgba(76, 175, 80, 0.2); border: 1px solid #4CAF50; color: #81C784; }
        .error { background: rgba(244, 67, 54, 0.2); border: 1px solid #f44336; color: #ef5350; }
        .loading { background: rgba(255, 193, 7, 0.2); border: 1px solid #FFC107; color: #FFD54F; }
        .grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: 15px; margin: 20px 0; }
        .card { background: #1e293b; border: 1px solid #334155; border-radius: 12px; padding: 20px; text-align: center; }
        .card:hover { border-color: #3b82f6; }
        .icon { font-size: 2rem; margin-bottom: 10px; }
        .title { font-size: 1.1rem; font-weight: 600; margin-bottom: 5px; }
        .desc { font-size: 0.9rem; color: #94a3b8; }
        .btn { background: #3b82f6; color: white; border: none; padding: 12px 24px; border-radius: 8px; cursor: pointer; font-size: 1rem; margin: 10px; }
        .btn:hover { background: #2563eb; }
        .hidden { display: none; }
        @media (max-width: 768px) { .grid { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📱 Clean Mobile POS</h1>
            <div class="user"><?php echo htmlspecialchars(get_current_user_name()); ?></div>
        </div>

        <div class="content">
            <div id="status" class="status loading">Initializing clean mobile POS...</div>

            <div class="grid">
                <div class="card" onclick="testAPI()">
                    <div class="icon">🔗</div>
                    <div class="title">Test API</div>
                    <div class="desc">Check API connectivity</div>
                </div>

                <div class="card" onclick="loadProducts()">
                    <div class="icon">📦</div>
                    <div class="title">Load Products</div>
                    <div class="desc">Fetch product catalog</div>
                </div>

                <div class="card" onclick="showCart()">
                    <div class="icon">🛒</div>
                    <div class="title">Cart</div>
                    <div class="desc">View current cart</div>
                </div>

                <div class="card" onclick="processTestSale()">
                    <div class="icon">💰</div>
                    <div class="title">Test Sale</div>
                    <div class="desc">Process a test transaction</div>
                </div>
            </div>

            <div id="results" class="hidden"></div>

            <div id="cart" class="hidden">
                <h2>Shopping Cart</h2>
                <div id="cart-items"></div>
                <div id="cart-total"></div>
            </div>

            <div id="products" class="hidden">
                <h2>Products</h2>
                <div id="product-list"></div>
            </div>
        </div>
    </div>

    <script>
        // Clean JavaScript - no async/await issues
        const apiKey = '<?php echo $api_key; ?>';
        const baseUrl = '/JDH_POS/public/api/mobile';
        let cart = [];

        function updateStatus(message, type = 'info') {
            const status = document.getElementById('status');
            status.className = 'status ' + (type === 'success' ? 'success' : type === 'error' ? 'error' : 'loading');
            status.textContent = message;
        }

        function showResult(message, type = 'info') {
            const results = document.getElementById('results');
            results.classList.remove('hidden');
            results.innerHTML = `<div class="status ${type === 'success' ? 'success' : type === 'error' ? 'error' : 'loading'}">${message}</div>`;
        }

        function testAPI() {
            updateStatus('Testing API connection...', 'loading');

            fetch(baseUrl + '/pos/products?limit=1', {
                headers: { 'X-API-Key': apiKey }
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('HTTP ' + response.status);
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    updateStatus('✅ API connection successful!', 'success');
                    showResult('API is working correctly. Ready for mobile POS operations.', 'success');
                } else {
                    updateStatus('❌ API error', 'error');
                    showResult('API error: ' + data.message, 'error');
                }
            })
            .catch(error => {
                updateStatus('❌ Connection failed', 'error');
                showResult('Failed to connect to API: ' + error.message, 'error');
            });
        }

        function loadProducts() {
            updateStatus('Loading products...', 'loading');

            fetch(baseUrl + '/pos/products?limit=10', {
                headers: { 'X-API-Key': apiKey }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    updateStatus('✅ Products loaded!', 'success');

                    const productsDiv = document.getElementById('products');
                    const productList = document.getElementById('product-list');

                    productList.innerHTML = data.data.products.map(product =>
                        `<div class="card" style="margin: 10px 0;" onclick="addToCart('${product.id}', '${product.name.replace(/'/g, "\\'")}', ${product.price})">
                            <div class="title">${product.name}</div>
                            <div class="desc">$${product.price}</div>
                        </div>`
                    ).join('');

                    productsDiv.classList.remove('hidden');
                    document.getElementById('results').classList.add('hidden');

                } else {
                    updateStatus('❌ Failed to load products', 'error');
                    showResult('Failed to load products: ' + data.message, 'error');
                }
            })
            .catch(error => {
                updateStatus('❌ Error loading products', 'error');
                showResult('Error: ' + error.message, 'error');
            });
        }

        function addToCart(id, name, price) {
            const existing = cart.find(item => item.id === id);
            if (existing) {
                existing.quantity += 1;
            } else {
                cart.push({ id, name, price, quantity: 1 });
            }

            updateStatus(`✅ Added ${name} to cart`, 'success');
            showCart();
        }

        function showCart() {
            const cartDiv = document.getElementById('cart');
            const cartItems = document.getElementById('cart-items');
            const cartTotal = document.getElementById('cart-total');

            if (cart.length === 0) {
                cartItems.innerHTML = '<p>Cart is empty</p>';
                cartTotal.innerHTML = '';
            } else {
                cartItems.innerHTML = cart.map(item =>
                    `<div style="display: flex; justify-content: space-between; padding: 10px; border-bottom: 1px solid #334155;">
                        <span>${item.name} x${item.quantity}</span>
                        <span>$${(item.price * item.quantity).toFixed(2)}</span>
                    </div>`
                ).join('');

                const total = cart.reduce((sum, item) => sum + (item.price * item.quantity), 0);
                cartTotal.innerHTML = `<div style="padding: 15px; font-weight: bold; font-size: 1.2rem;">Total: $${total.toFixed(2)}</div>`;
            }

            cartDiv.classList.remove('hidden');
            document.getElementById('results').classList.add('hidden');
        }

        function processTestSale() {
            if (cart.length === 0) {
                showResult('Cart is empty. Add some products first.', 'error');
                return;
            }

            updateStatus('Processing sale...', 'loading');

            const saleData = {
                branch_id: 1,
                user_id: 1,
                items: cart.map(item => ({
                    product_id: item.id,
                    name: item.name,
                    quantity: item.quantity,
                    price: item.price,
                    cost_price: item.price * 0.6
                })),
                total: cart.reduce((sum, item) => sum + (item.price * item.quantity), 0),
                discount: 0,
                payment_method: 'cash',
                device_id: 'clean-mobile-pos'
            };

            fetch(baseUrl + '/pos/create_sale', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-API-Key': apiKey
                },
                body: JSON.stringify(saleData)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    updateStatus('✅ Sale completed successfully!', 'success');
                    showResult(`Sale completed! ID: ${data.data.sale_id}, Receipt: ${data.data.receipt_number}`, 'success');
                    cart = []; // Clear cart
                    showCart();
                } else {
                    updateStatus('❌ Sale failed', 'error');
                    showResult('Sale failed: ' + data.message, 'error');
                }
            })
            .catch(error => {
                updateStatus('❌ Sale error', 'error');
                showResult('Sale processing error: ' + error.message, 'error');
            });
        }

        // Initialize
        updateStatus('Clean Mobile POS ready!', 'success');
    </script>
</body>
</html>