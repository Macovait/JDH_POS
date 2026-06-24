/**
 * Shared E-commerce Shop JavaScript
 */
const STORE_TENANT = (typeof SHOP_TENANT !== 'undefined') ? SHOP_TENANT : 0;
const CURRENCY = (typeof SHOP_CURRENCY !== 'undefined') ? SHOP_CURRENCY : 'KES';

let cart = [];
try { cart = JSON.parse(localStorage.getItem('shop_cart_' + STORE_TENANT) || '[]'); } catch(e) { cart = []; }

function saveCart() {
    localStorage.setItem('shop_cart_' + STORE_TENANT, JSON.stringify(cart));
    renderCart();
}

function addToCart(id, name, price) {
    const existing = cart.find(i => i.id === id);
    if (existing) { existing.qty++; } else { cart.push({ id, name, price, qty: 1 }); }
    saveCart();
}

function updateQty(id, delta) {
    const item = cart.find(i => i.id === id);
    if (!item) return;
    item.qty += delta;
    if (item.qty <= 0) cart = cart.filter(i => i.id !== id);
    saveCart();
}

function removeFromCart(id) { cart = cart.filter(i => i.id !== id); saveCart(); }
function clearCart() { cart = []; saveCart(); }

function renderCart() {
    const container = document.getElementById('cartItems');
    const badge = document.getElementById('cartBadge');
    if (!container) return;
    if (cart.length === 0) {
        container.innerHTML = '<div class="empty-cart">Your cart is empty</div>';
        if (badge) badge.style.display = 'none';
        const totalEl = document.getElementById('cartTotal');
        if (totalEl) totalEl.textContent = CURRENCY + ' 0.00';
        return;
    }
    let html = '', total = 0, count = 0;
    cart.forEach(item => {
        const sub = item.price * item.qty;
        total += sub;
        count += item.qty;
        html += '<div class="cart-item">' +
            '<div class="cart-item-name">' + item.name + '</div>' +
            '<div class="cart-item-qty">' +
                '<button onclick="updateQty(' + item.id + ', -1)">-</button>' +
                '<span>' + item.qty + '</span>' +
                '<button onclick="updateQty(' + item.id + ', 1)">+</button>' +
            '</div>' +
            '<div class="cart-item-price">' + CURRENCY + ' ' + sub.toFixed(2) + '</div>' +
            '<button onclick="removeFromCart(' + item.id + ')" style="background:none;border:none;color:#f87171;cursor:pointer;"><i class="fas fa-trash"></i></button>' +
        '</div>';
    });
    container.innerHTML = html;
    const totalEl = document.getElementById('cartTotal');
    if (totalEl) totalEl.textContent = CURRENCY + ' ' + total.toFixed(2);
    if (badge) { badge.textContent = count; badge.style.display = 'inline'; }
}

function toggleCart(forceOpen) {
    const overlay = document.getElementById('cartOverlay');
    const drawer = document.getElementById('cartDrawer');
    if (!overlay || !drawer) return;
    const isOpen = drawer.classList.contains('open');
    if (forceOpen || !isOpen) { overlay.classList.add('open'); drawer.classList.add('open'); document.body.style.overflow = 'hidden'; }
    else { overlay.classList.remove('open'); drawer.classList.remove('open'); document.body.style.overflow = ''; }
}

function openCheckout() {
    if (cart.length === 0) { alert('Your cart is empty.'); return; }
    const modal = document.getElementById('checkoutModal');
    if (!modal) return;
    modal.classList.add('open');
    const form = document.getElementById('checkoutForm');
    const success = document.getElementById('checkoutSuccess');
    if (form) form.style.display = 'block';
    if (success) success.style.display = 'none';
}

function closeCheckout() {
    const modal = document.getElementById('checkoutModal');
    if (modal) modal.classList.remove('open');
}

function submitOrder() {
    const name = document.getElementById('coName');
    const phone = document.getElementById('coPhone');
    const email = document.getElementById('coEmail');
    const address = document.getElementById('coAddress');
    const payment = document.getElementById('coPayment');
    if (!name || !phone) return;
    const n = name.value.trim();
    const p = phone.value.trim();
    if (!n || !p) { alert('Please fill in your name and phone number.'); return; }
    const total = cart.reduce((sum, i) => sum + (i.price * i.qty), 0);
    const payload = {
        tenant_id: STORE_TENANT,
        customer_name: n,
        customer_phone: p,
        customer_email: email ? email.value.trim() : '',
        delivery_address: address ? address.value.trim() : '',
        payment_method: payment ? payment.value : 'cash',
        items: cart.map(i => ({ product_id: i.id, name: i.name, price: i.price, quantity: i.qty })),
        total: total
    };
    fetch('../api/shop/order.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            const form = document.getElementById('checkoutForm');
            const success = document.getElementById('checkoutSuccess');
            if (form) form.style.display = 'none';
            if (success) success.style.display = 'block';
        } else {
            alert(data.error || 'Could not place order. Please try again.');
        }
    })
    .catch(err => { console.error(err); alert('Network error. Please try again.'); });
}

function filterProducts() {
    const cat = document.getElementById('categoryFilter');
    const term = document.getElementById('searchInput');
    const catVal = cat ? cat.value : '';
    const termVal = term ? term.value.toLowerCase() : '';
    document.querySelectorAll('.product-card').forEach(card => {
        const matchCat = !catVal || card.dataset.category === catVal;
        const matchTerm = !termVal || (card.dataset.name && card.dataset.name.includes(termVal));
        card.style.display = (matchCat && matchTerm) ? '' : 'none';
    });
}

// Init cart on page load
document.addEventListener('DOMContentLoaded', function() {
    renderCart();
});

// Close modals on Escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeCheckout();
        const drawer = document.getElementById('cartDrawer');
        if (drawer && drawer.classList.contains('open')) toggleCart();
    }
});
