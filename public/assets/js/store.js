/**
 * Online Store JavaScript
 * Cart, checkout, and offline caching.
 */

const CART_KEY = 'jdh_cart_' + (typeof TENANT_SLUG !== 'undefined' ? TENANT_SLUG : 'global');

function getCart() {
    try { return JSON.parse(localStorage.getItem(CART_KEY)) || {}; } catch (e) { return {}; }
}

function saveCart(cart) {
    localStorage.setItem(CART_KEY, JSON.stringify(cart));
    updateCartUI();
}

function addToCart(id, name, price, maxQty) {
    const cart = getCart();
    const qty = (cart[id] ? cart[id].qty : 0) + 1;
    if (qty > maxQty) { alert('Only ' + maxQty + ' available in stock.'); return; }
    cart[id] = { id, name, price: parseFloat(price), qty };
    saveCart(cart);
    alert(name + ' added to cart.');
}

function removeFromCart(id) {
    const cart = getCart();
    delete cart[id];
    saveCart(cart);
    renderCartPage();
}

function updateQty(id, delta) {
    const cart = getCart();
    if (!cart[id]) return;
    cart[id].qty += delta;
    if (cart[id].qty < 1) delete cart[id];
    saveCart(cart);
    renderCartPage();
}

function updateCartUI() {
    const cart = getCart();
    let count = 0;
    Object.values(cart).forEach(item => count += item.qty);
    const el = document.getElementById('cartCount');
    if (el) el.textContent = count;
}

function renderCartPage() {
    const cart = getCart();
    const container = document.getElementById('cartItems');
    if (!container) return;
    if (!Object.keys(cart).length) {
        container.innerHTML = '<p>Your cart is empty. <a href="index.php?tenant=' + encodeURIComponent(TENANT_SLUG) + '">Continue shopping</a></p>';
        document.getElementById('cartTotal').textContent = '0.00';
        return;
    }
    let html = '<table class="cart-table"><thead><tr><th>Product</th><th>Price</th><th>Qty</th><th>Total</th><th></th></tr></thead><tbody>';
    let total = 0;
    Object.values(cart).forEach(item => {
        const itemTotal = item.price * item.qty;
        total += itemTotal;
        html += '<tr><td>' + escapeHtml(item.name) + '</td><td>' + item.price.toFixed(2) + '</td>' +
            '<td><button onclick="updateQty(' + item.id + ', -1)">-</button> ' + item.qty + ' <button onclick="updateQty(' + item.id + ', 1)">+</button></td>' +
            '<td>' + itemTotal.toFixed(2) + '</td><td><button onclick="removeFromCart(' + item.id + ')">Remove</button></td></tr>';
    });
    html += '</tbody></table>';
    container.innerHTML = html;
    document.getElementById('cartTotal').textContent = total.toFixed(2);
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

document.addEventListener('DOMContentLoaded', function () {
    updateCartUI();
    renderCartPage();
});

// Service Worker registration for offline caching (check file existence first)
if ('serviceWorker' in navigator) {
    (async function () {
        const swPath = '/assets/js/store-sw.js';
        try {
            const resp = await fetch(swPath, { method: 'HEAD' });
            if (resp && resp.ok) {
                navigator.serviceWorker.register(swPath).catch(function (err) {
                    console.log('SW registration failed', err);
                });
            } else {
                console.info('Store service worker not found; skipping registration:', swPath);
            }
        } catch (e) {
            console.info('Could not verify store service worker; skipping registration.', e);
        }
    })();
}
