// ============================================
// HELPER FUNCTIONS
// ============================================
function formatCurrency(amount) {
    return `${CONFIG.currency} ${Number(amount || 0).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2})}`;
}

function escapeHtml(str) {
    return String(str || '').replace(/[&<>]/g, function(m) {
        if (m === '&') return '&amp;';
        if (m === '<') return '&lt;';
        if (m === '>') return '&gt;';
        return m;
    });
}

function escapeJsString(value) {
    return String(value || '').replace(/\\/g, '\\\\').replace(/'/g, "\\'").replace(/"/g, '\\"');
}

function showToast(message, type = 'info') {
    const container = document.getElementById('toastContainer');
    if (!container) return;
    
    const bgColor = type === 'success' ? 'bg-emerald-500' : type === 'error' ? 'bg-red-500' : type === 'warning' ? 'bg-amber-500' : 'bg-blue-500';
    const toast = document.createElement('div');
    toast.className = `${bgColor} text-white px-4 py-3 rounded-lg shadow-lg animate-slide-in text-sm max-w-xs`;
    toast.textContent = message;
    container.appendChild(toast);
    
    setTimeout(() => {
        toast.style.opacity = '0';
        toast.style.transition = 'opacity 0.3s';
        setTimeout(() => toast.remove(), 300);
    }, 3000);
}

function getPricingBreakdown() {
    const subtotal = window.cart.reduce((sum, item) => sum + (item.price * item.qty), 0);
    let discount = 0;
    
    if (appliedDiscount) {
        if (appliedDiscount.type === 'percent') {
            discount = subtotal * (appliedDiscount.value / 100);
        } else {
            discount = appliedDiscount.value;
        }
        discount = Math.min(discount, subtotal);
    }
    
    const taxableAmount = Math.max(0, subtotal - discount);
    const tax = taxableAmount * (CONFIG.taxRate / 100);
    const total = taxableAmount + tax;
    
    return { subtotal, discount, tax, total, itemCount: window.cart.reduce((sum, item) => sum + item.qty, 0) };
}

// Expose globally for plugin compatibility
window.getCartTotal = function() {
    return getPricingBreakdown().total;
};

// ============================================
// MODAL FUNCTIONS
// ============================================
function showModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.remove('hidden');
        modal.classList.add('flex');
    }
}

function closeModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.add('hidden');
        modal.classList.remove('flex');
    }
}

function closeAllModals() {
    document.querySelectorAll('.fixed.inset-0.bg-black\\/70').forEach(modal => {
        modal.classList.add('hidden');
        modal.classList.remove('flex');
    });
}

// ============================================
// CART FUNCTIONS
// ============================================
function updateCartDisplay() {
    _saveState();
    const container = document.getElementById('cartItems');
    if (!container) return;

    if (window.cart.length === 0) {
        container.innerHTML = '<div class="flex flex-col items-center justify-center h-full py-8 gap-2"><div class="w-14 h-14 rounded-full bg-slate-800 flex items-center justify-center"><i class="fas fa-receipt text-slate-600 text-xl"></i></div><p class="text-slate-500 text-xs text-center">No items yet<br><span class="text-slate-600">Tap a product to add</span></p></div>';
        document.getElementById('payBtn').disabled = true;
        updateTotals();
        updatePosCartFabBadge();
        document.dispatchEvent(new CustomEvent('cart:updated', { detail: { cart: window.cart } }));
        return;
    }
    
    container.innerHTML = window.cart.map((item, index) => `
        <div class="cart-item group flex flex-col px-1 py-1 rounded-lg hover:bg-slate-800/60 transition-colors">
            <div class="flex items-center gap-2">
                <div class="flex flex-col items-center gap-0.5 shrink-0">
                    <button onclick="updateQty(${index}, 1)" class="w-5 h-5 rounded bg-slate-800 text-slate-400 text-[10px] hover:bg-amber-500/20 hover:text-amber-400 transition-colors leading-none">+</button>
                    <button onclick="showQtyPopup(${index})" class="text-white text-xs font-bold font-mono min-w-[20px] text-center hover:text-amber-400 transition-colors" title="Tap to set qty">${item.qty}</button>
                    <button onclick="updateQty(${index}, -1)" class="w-5 h-5 rounded bg-slate-800 text-slate-400 text-[10px] hover:bg-red-500/20 hover:text-red-400 transition-colors leading-none">&minus;</button>
                </div>
                <div class="flex-1 min-w-0">
                    <div class="text-slate-200 text-xs font-medium truncate leading-tight">${escapeHtml(item.name)}</div>
                    <div class="text-slate-500 text-[10px] font-mono">${formatCurrency(item.price)} each${item.note ? ' <span class="text-amber-400/70 italic">' + escapeHtml(item.note) + '</span>' : ''}</div>
                </div>
                <div class="text-right shrink-0">
                    <div class="text-white text-xs font-bold font-mono">${formatCurrency(item.price * item.qty)}</div>
                    <div class="flex gap-1 justify-end mt-0.5 opacity-0 group-hover:opacity-100 transition-opacity">
                        <button onclick="toggleItemNote(${index})" class="text-slate-600 hover:text-blue-400 text-[9px] transition-colors" title="Add note"><i class="fas fa-comment-dots"></i></button>
                        <button onclick="showPriceOverrideModal(${index})" class="text-slate-600 hover:text-amber-400 text-[9px] transition-colors" title="Override Price"><i class="fas fa-tag"></i></button>
                        <button onclick="removeFromCart(${index})" class="text-slate-600 hover:text-red-400 text-[9px] transition-colors" title="Remove"><i class="fas fa-times"></i></button>
                    </div>
                </div>
            </div>
            <div id="noteRow_${index}" class="${item.note ? '' : 'hidden'} mt-0.5 pl-7">
                <input type="text" value="${escapeHtml(item.note || '')}" placeholder="Item note..." maxlength="80"
                    class="w-full px-1.5 py-0.5 bg-slate-900 border border-slate-700/60 rounded text-[10px] text-slate-300 placeholder-slate-600 focus:outline-none focus:border-amber-500/50"
                    oninput="saveItemNote(${index}, this.value)" onblur="saveItemNote(${index}, this.value)">
            </div>
        </div>
    `).join('<div class="receipt-dashes my-0.5 opacity-40"></div>');
    
    document.getElementById('payBtn').disabled = false;
    updateTotals();
    updatePosCartFabBadge();
    loadAIRecommendations();
    document.dispatchEvent(new CustomEvent('cart:updated', { detail: { cart: window.cart } }));
}

function updateTotals() {
    const { subtotal, discount, tax, total, itemCount } = getPricingBreakdown();
    
    const subtotalEl = document.getElementById('cartSubtotalDisplay');
    const discountDisplayRow = document.getElementById('discountDisplayRow');
    const discountEl = document.getElementById('cartDiscountDisplay');
    const taxEl = document.getElementById('cartTaxDisplay');
    const totalEl = document.getElementById('paymentTotal');
    const itemCountEl = document.getElementById('cartItemCount');
    const orderNumEl = document.getElementById('orderNumberDisplay');
    
    if (subtotalEl) subtotalEl.textContent = formatCurrency(subtotal);
    
    if (discount > 0 && discountDisplayRow && discountEl) {
        discountDisplayRow.classList.remove('hidden');
        discountDisplayRow.classList.add('flex');
        discountEl.textContent = '-' + formatCurrency(discount);
    } else if (discountDisplayRow) {
        discountDisplayRow.classList.add('hidden');
        discountDisplayRow.classList.remove('flex');
    }
    
    if (taxEl) taxEl.textContent = formatCurrency(tax);
    if (totalEl) totalEl.textContent = formatCurrency(total);
    if (itemCountEl) itemCountEl.textContent = itemCount + (itemCount === 1 ? ' item' : ' items');
    if (orderNumEl) orderNumEl.textContent = window.cart.length > 0 ? '#' + String(Date.now()).slice(-4) : '#—';
    
    updatePaymentModalTotals();
    const changeAmountEl = document.getElementById('changeAmount');
    if (changeAmountEl && !document.getElementById('paymentModal')?.classList.contains('hidden')) {
        calculateChange();
    }
    
    return total;
}

function updateQty(index, delta) {
    const item = window.cart[index];
    if (!item) return;
    
    const newQty = item.qty + delta;
    if (newQty < 1) {
        window.cart.splice(index, 1);
    } else if (newQty <= item.stock) {
        item.qty = newQty;
    } else {
        showToast('No more stock available', 'error');
        return;
    }
    cart = window.cart;
    updateCartDisplay();
}

function removeFromCart(index) {
    window.cart.splice(index, 1);
    cart = window.cart;
    updateCartDisplay();
}

function clearCart() {
    if (window.cart.length > 0 && !confirm('Clear entire cart?')) return;
    window.cart = [];
    cart = window.cart;
    appliedDiscount = null;
    appliedVoucher = null;
    window.selectedCustomer = null;
    selectedCustomer = null;
    _clearState();
    const _ci = document.getElementById('couponCodeInput');
    const _cm = document.getElementById('couponMsg');
    const _ca = document.getElementById('couponApplyBtn');
    const _cc = document.getElementById('couponClearBtn');
    if (_ci) { _ci.value = ''; _ci.readOnly = false; }
    if (_cm) { _cm.textContent = ''; _cm.classList.add('hidden'); }
    if (_ca) { _ca.disabled = false; _ca.textContent = 'Apply'; _ca.classList.remove('hidden'); }
    if (_cc) { _cc.classList.add('hidden'); }
    updateCartDisplay();
    showToast('Cart cleared', 'info');
}

function addToCart(element) {
    const id = parseInt(element.dataset.productId);
    const name = element.dataset.productName;
    const price = parseFloat(element.dataset.productPrice);
    const stock = (element.dataset.productStock != null && element.dataset.productStock !== '') ? parseInt(element.dataset.productStock, 10) : 999;
    const image = element.dataset.productImage || '';
    
    if (stock <= 0) {
        showToast('This product is out of stock', 'error');
        return;
    }
    
    if (element.classList) {
        element.classList.add('processing');
        setTimeout(() => element.classList.remove('processing'), 400);
    }
    
    const existing = window.cart.find(item => item.id === id);
    if (existing) {
        if (existing.qty >= stock) {
            showToast('No more stock available', 'error');
            return;
        }
        existing.qty++;
    } else {
        window.cart.push({ id, name, price, qty: 1, stock, image });
    }
    
    cart = window.cart;
    updateCartDisplay();
    showToast(`${name} added to cart`, 'success');
    document.dispatchEvent(new CustomEvent('cart:updated', { detail: { cart: window.cart } }));
}

// ============================================
// PAYMENT FUNCTIONS
// ============================================
function showPaymentModal() {
    closePosCart();
    if (window.cart.length === 0) {
        showToast('Cart is empty', 'warning');
        return;
    }
    
    const { subtotal, discount, tax, total, itemCount } = getPricingBreakdown();
    
    document.getElementById('paymentTotal').textContent = formatCurrency(total);
    document.getElementById('modalItemCount').textContent = itemCount;
    document.getElementById('modalSubtotalDisplay').textContent = formatCurrency(subtotal);
    document.getElementById('modalTaxDisplay').textContent = formatCurrency(tax);
    
    const discountDisplayRow = document.getElementById('modalDiscountRow');
    const cartDiscountDisplay = document.getElementById('modalDiscountDisplay');
    if (discount > 0 && cartDiscountDisplay && discountDisplayRow) {
        cartDiscountDisplay.textContent = '-' + formatCurrency(discount);
        discountDisplayRow.classList.remove('hidden');
        discountDisplayRow.classList.add('flex');
    } else if (discountDisplayRow) {
        discountDisplayRow.classList.add('hidden');
        discountDisplayRow.classList.remove('flex');
    }
    
    document.getElementById('amountReceived').value = total.toFixed(2);
    document.getElementById('changeAmount').textContent = formatCurrency(0);
    
    selectedPaymentMethod = 'cash';
    document.querySelectorAll('.payment-method-btn').forEach(btn => {
        btn.classList.toggle('ring-2', btn.dataset.method === 'cash');
        btn.classList.toggle('ring-amber-500', btn.dataset.method === 'cash');
    });
    
    document.getElementById('splitPaymentPanel').style.display = 'none';
    document.getElementById('splitPaymentsList').innerHTML = `
        <div class="space-y-2 p-2 bg-slate-800 rounded">
            <select name="split_method_0" class="split-method w-full px-2 py-1 bg-slate-900 border border-slate-600 rounded text-sm text-white">
                ${CONFIG.paymentMethods.map(pm => `<option value="${pm.id}">${pm.label}</option>`).join('')}
            </select>
            <input type="number" name="split_amount_0" class="split-amount w-full px-2 py-1 bg-slate-900 border border-slate-600 rounded text-sm text-white" placeholder="Amount" onchange="calculateSplitTotal()">
        </div>
    `;
    document.getElementById('splitTotal').textContent = formatCurrency(0);
    
    document.getElementById('processPaymentBtn').disabled = false;
    document.getElementById('processPaymentBtn').innerHTML = '<i class="fas fa-credit-card mr-1"></i> Complete Sale';
    
    _loyaltyRedeemDiscount = 0;
    _loyaltyPointsToRedeem = 0;
    document.getElementById('loyaltyRedeemInput').value = '';
    document.getElementById('loyaltyDiscountMsg').classList.add('hidden');
    showLoyaltyRowIfCustomer();

    showModal('paymentModal');
    setTimeout(() => {
        document.getElementById('amountReceived').focus();
        document.getElementById('amountReceived').select();
    }, 100);
}

function updatePaymentModalTotals() {
    const paymentModal = document.getElementById('paymentModal');
    if (!paymentModal || paymentModal.classList.contains('hidden')) return;
    
    const { subtotal, discount, tax, total, itemCount } = getPricingBreakdown();
    
    document.getElementById('paymentTotal').textContent = formatCurrency(total);
    document.getElementById('modalItemCount').textContent = itemCount;
    document.getElementById('modalSubtotalDisplay').textContent = formatCurrency(subtotal);
    document.getElementById('modalTaxDisplay').textContent = formatCurrency(tax);
    
    const discountDisplayRow = document.getElementById('modalDiscountRow');
    const cartDiscountDisplay = document.getElementById('modalDiscountDisplay');
    if (discount > 0 && cartDiscountDisplay && discountDisplayRow) {
        cartDiscountDisplay.textContent = '-' + formatCurrency(discount);
        discountDisplayRow.classList.remove('hidden');
        discountDisplayRow.classList.add('flex');
    } else if (discountDisplayRow) {
        discountDisplayRow.classList.add('hidden');
        discountDisplayRow.classList.remove('flex');
    }
    
    calculateChange();
}

function selectPaymentMethod(method) {
    selectedPaymentMethod = method;
    document.querySelectorAll('.payment-method-btn').forEach(btn => {
        btn.classList.toggle('ring-2', btn.dataset.method === method);
        btn.classList.toggle('ring-amber-500', btn.dataset.method === method);
    });
    
    document.getElementById('splitPaymentPanel').style.display = method === 'split' ? 'block' : 'none';
    
    if (method !== 'split') {
        splitPayments = [];
        const total = updateTotals();
        document.getElementById('amountReceived').value = total.toFixed(2);
    }
    calculateChange();
}

function addSplitPayment() {
    const list = document.getElementById('splitPaymentsList');
    const idx = list.children.length;
    const div = document.createElement('div');
    div.className = 'space-y-2 p-2 bg-slate-800 rounded';
    div.innerHTML = `
        <select name="split_method_${idx}" class="split-method w-full px-2 py-1 bg-slate-900 border border-slate-600 rounded text-sm text-white">
            ${CONFIG.paymentMethods.map(pm => `<option value="${pm.id}">${pm.label}</option>`).join('')}
        </select>
        <input type="number" name="split_amount_${idx}" class="split-amount w-full px-2 py-1 bg-slate-900 border border-slate-600 rounded text-sm text-white" placeholder="Amount" onchange="calculateSplitTotal()">
    `;
    list.appendChild(div);
}

function getSplitPayments() {
    const payments = [];
    const methods = document.querySelectorAll('#splitPaymentsList .split-method');
    const amounts = document.querySelectorAll('#splitPaymentsList .split-amount');
    for (let i = 0; i < amounts.length; i++) {
        const amount = parseFloat(amounts[i].value) || 0;
        if (amount > 0) {
            payments.push({ method: methods[i] ? methods[i].value : 'cash', amount });
        }
    }
    return payments;
}

function calculateSplitTotal() {
    const payments = getSplitPayments();
    const total = payments.reduce((sum, p) => sum + p.amount, 0);
    document.getElementById('splitTotal').textContent = formatCurrency(total);
    calculateChange();
}

function calculateChange() {
    const { total } = getPricingBreakdown();
    const amount = parseFloat(document.getElementById('amountReceived')?.value || 0);
    let change = 0;
    
    if (selectedPaymentMethod === 'split') {
        const splitTotal = getSplitPayments().reduce((sum, p) => sum + p.amount, 0);
        change = Math.max(0, splitTotal - total);
    } else {
        change = Math.max(0, amount - total);
    }
    
    document.getElementById('changeAmount').textContent = formatCurrency(change);
    document.getElementById('changeIndicator').innerHTML = amount >= total || getSplitPayments().reduce((s, p) => s + p.amount, 0) >= total 
        ? '<i class="fas fa-check-circle text-emerald-400 ml-2"></i>' 
        : '<i class="fas fa-exclamation-triangle text-amber-400 ml-2"></i>';
}

function processPayment() {
    if (!CONFIG.shiftId) {
        showToast('Please open a shift first', 'error');
        showModal('shiftModal');
        return;
    }
    
    if (isProcessingPayment) {
        showToast('Payment already in progress', 'warning');
        return;
    }
    
    const { subtotal, discount, tax, total } = getPricingBreakdown();
    
    if (selectedPaymentMethod === 'split') {
        splitPayments = getSplitPayments();
        if (splitPayments.length === 0) {
            showToast('Add at least one payment method', 'error');
            return;
        }
        const splitTotal = splitPayments.reduce((sum, p) => sum + p.amount, 0);
        if (splitTotal < total) {
            showToast('Split payments total is less than total due', 'error');
            return;
        }
    }
    
    const amount = parseFloat(document.getElementById('amountReceived')?.value || 0);
    if (selectedPaymentMethod !== 'split' && amount < total) {
        showToast('Insufficient amount received', 'error');
        return;
    }
    
    isProcessingPayment = true;
    const processBtn = document.getElementById('processPaymentBtn');
    const originalHtml = processBtn.innerHTML;
    processBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
    processBtn.disabled = true;
    
    const soldItems = [...window.cart];
    const normalizedPaymentMethod = selectedPaymentMethod === 'bank' ? 'bank_transfer' : selectedPaymentMethod;
    const saleItems = window.cart.map(item => ({ product_id: item.id, quantity: item.qty, price: item.price }));
    
    const _amtReceived = selectedPaymentMethod === 'split'
        ? splitPayments.reduce((s, p) => s + p.amount, 0)
        : parseFloat(document.getElementById('amountReceived')?.value || total);

    const saleData = {
        branch_id: CONFIG.branchId,
        customer_id: window.selectedCustomer?.id || '',
        customer_name: window.selectedCustomer?.name || 'Walk-in Customer',
        payment_method: normalizedPaymentMethod,
        voucher_code: document.getElementById('voucherInput')?.value.trim() || '',
        discount_id: appliedDiscount?.id || '',
        discount_value: Number(appliedDiscount?.value || 0),
        discount_type: appliedDiscount?.type || 'fixed',
        subtotal,
        discount,
        tax,
        total,
        amount_received: _amtReceived,
        items: saleItems,
        items_json: JSON.stringify(saleItems),
        order_type: CONFIG.orderType,
        csrf_token: CONFIG.csrfToken,
        loyalty_points_redeemed: _loyaltyPointsToRedeem,
        loyalty_discount: _loyaltyRedeemDiscount,
        kitchen_notes: document.getElementById('kitchenNotes')?.value || '',
        table_number: document.getElementById('tableNumber')?.value || '',
        prescription_enabled: document.getElementById('prescriptionToggle')?.checked ? 1 : 0,
        prescription_ref: document.getElementById('prescriptionRef')?.value || '',
        prescription_doctor: document.getElementById('prescriptionDoctor')?.value || '',
        prescription_notes: document.getElementById('prescriptionNotes')?.value || '',
        room_number: document.getElementById('roomNumber')?.value || '',
        guest_name: document.getElementById('guestName')?.value || '',
        serial_number: document.getElementById('serialNumber')?.value || '',
        warranty_months: document.getElementById('warrantyPeriod')?.value || '',
        age_verified: document.getElementById('ageVerifyToggle')?.checked ? 1 : 0,
        age_verify_id: document.getElementById('ageVerifyId')?.value || '',
        staff_id: document.getElementById('staffSelect')?.value || '',
        batch_number: document.getElementById('batchNumber')?.value || '',
        weight: parseFloat(document.getElementById('weightInput')?.value || 0),
        weight_unit: document.getElementById('weightUnit')?.value || 'kg',
        bt_id: CONFIG.businessTypeId,
        business_type: CONFIG.businessType
    };
    
    if (selectedPaymentMethod === 'split') {
        saleData.split_payments = JSON.stringify(splitPayments);
    }
    
    fetch('../ajax/process_sale.php', {
        method: 'POST',
        headers: { 
            'Content-Type': 'application/json',
            'X-CSRF-Token': CONFIG.csrfToken
        },
        body: JSON.stringify(saleData)
    })
    .then(async r => {
        const text = await r.text();
        let data = null;
        try {
            data = text ? JSON.parse(text) : null;
        } catch (e) {
            throw new Error('Server returned non-JSON response: ' + text.slice(0, 300));
        }
        if (!data && !r.ok) {
            throw new Error('Request failed with HTTP ' + r.status);
        }
        return data;
    })
    .then(data => {
        if (!data.success) {
            throw new Error(data.error || 'Sale failed');
        }
        
        showToast(`Sale completed! Invoice: ${data.invoice_number || '#' + data.sale_id}`, 'success');
        if (data.csrf_token) CONFIG.csrfToken = data.csrf_token;

        trackBehavior([...window.cart]);
        updateGridStockAfterSale([...window.cart]);
        awardLoyaltyPoints([...window.cart], total);
        _loyaltyRedeemDiscount = 0;
        _loyaltyPointsToRedeem = 0;
        refreshTodayStats();
        clearCart();
        closeModal('paymentModal');
        showConfetti();
        
        const amountReceived = _amtReceived;
        const change = Math.max(0, amountReceived - total);

        if (data.sale_id) {
            _lastSaleId = data.sale_id;
            showReceipt(data.sale_id);
        } else if (data.receipt) {
            data.receipt.amount_received = amountReceived;
            data.receipt.change = change;
            showReceiptWithData(data.receipt);
        } else {
            showReceiptWithItems(soldItems, normalizedPaymentMethod, data.invoice_number, amountReceived, change);
        }
    })
    .catch(err => {
        const message = err.message || 'Network error';
        showToast(message, 'error');
    })
    .finally(() => {
        isProcessingPayment = false;
        processBtn.innerHTML = originalHtml;
        processBtn.disabled = false;
    });
}

// ============================================
// SHIFT FUNCTIONS
// ============================================
function openShift() {
    const openingCash = parseFloat(document.getElementById('openingCash')?.value || 0);
    fetch('../ajax/shift.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `action=open&opening_cash=${openingCash}&csrf_token=${CONFIG.csrfToken}`
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            showToast('Shift opened successfully', 'success');
            location.reload();
        } else {
            showToast(data.error || 'Failed to open shift', 'error');
        }
    })
    .catch(() => showToast('Network error', 'error'));
}

function closeShift() {
    const closingCash = parseFloat(document.getElementById('closingCash')?.value || 0);
    const notes = document.getElementById('closingNotes')?.value || '';
    fetch('../ajax/shift.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `action=close&closing_cash=${closingCash}&notes=${encodeURIComponent(notes)}&csrf_token=${CONFIG.csrfToken}`
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            showToast('Shift closed successfully', 'success');
            location.reload();
        } else {
            showToast(data.error || 'Failed to close shift', 'error');
        }
    })
    .catch(() => showToast('Network error', 'error'));
}

function calcCashVariance() {
    const closing = parseFloat(document.getElementById('closingCash')?.value || 0);
    const expected = parseFloat(document.getElementById('shiftExpectedCash')?.textContent?.replace(/,/g, '') || 0);
    const diff = closing - expected;
    const row = document.getElementById('cashVarianceRow');
    const amt = document.getElementById('cashVarianceAmt');
    if (!row || !amt) return;
    row.classList.remove('hidden');
    row.classList.add('flex');
    if (Math.abs(diff) < 0.01) {
        row.className = row.className.replace(/bg-\S+/g, '') + ' bg-emerald-500/10';
        amt.className = 'font-semibold text-emerald-400';
        amt.textContent = 'Balanced';
    } else if (diff > 0) {
        row.className = row.className.replace(/bg-\S+/g, '') + ' bg-blue-500/10';
        amt.className = 'font-semibold text-blue-400';
        amt.textContent = '+' + CONFIG.currency + ' ' + diff.toFixed(2) + ' (over)';
    } else {
        row.className = row.className.replace(/bg-\S+/g, '') + ' bg-red-500/10';
        amt.className = 'font-semibold text-red-400';
        amt.textContent = CONFIG.currency + ' ' + diff.toFixed(2) + ' (short)';
    }
}

function refreshShiftSummary() {
    fetch(`../ajax/get_register_summary.php?csrf_token=${CONFIG.csrfToken}`)
        .then(r => r.json())
        .then(data => {
            if (!data.success) return;
            const s = data.summary;
            const setEl = (id, v) => { const el = document.getElementById(id); if (el) el.textContent = v; };
            setEl('shiftTotalSales', parseFloat(s.total_sales || 0).toFixed(2));
            setEl('shiftSaleCount', s.sale_count || 0);
            setEl('shiftCashSales', parseFloat(s.cash || 0).toFixed(2));
            const opening = parseFloat(s.opening_cash || 0);
            const cash = parseFloat(s.cash || 0);
            setEl('shiftExpectedCash', (opening + cash).toFixed(2));
            const breakdown = document.getElementById('shiftPaymentBreakdown');
            if (breakdown && s.by_method) {
                const rows = Object.entries(s.by_method).map(([m, v]) =>
                    `<div class="flex justify-between text-xs"><span class="text-slate-400 capitalize">${m}</span><span class="text-white">${CONFIG.currency} ${parseFloat(v).toFixed(2)}</span></div>`
                ).join('');
                breakdown.innerHTML = '<div class="text-slate-500 text-[10px] uppercase tracking-wider">By Payment Method</div>' + rows;
                breakdown.classList.remove('hidden');
            }
        })
        .catch(() => {});
}

// ============================================
// SEARCH & FILTER FUNCTIONS
// ============================================
function searchProducts(query) {
    currentSearchQuery = String(query || '');
    applyProductFilters();
}

function filterCategory(categoryId, btn) {
    currentCategory = categoryId;
    currentTag = null;
    document.querySelectorAll('.category-btn').forEach(b => b.classList.remove('active'));
    if (btn) btn.classList.add('active');
    applyProductFilters();
}

function filterTag(tagId, btn) {
    currentTag = tagId;
    document.querySelectorAll('.tag-filter-btn').forEach(b => {
        if (!b.dataset.originalBg) {
            b.dataset.originalBg = b.style.background;
            b.dataset.originalColor = b.style.color;
        }
        const isActive = b === btn;
        if (isActive) {
            b.style.background = b.dataset.originalColor;
            b.style.color = '#111827';
            b.style.borderColor = b.dataset.originalColor;
        } else {
            b.style.background = b.dataset.originalBg;
            b.style.color = b.dataset.originalColor;
            b.style.borderColor = (b.dataset.originalColor || '') + '40';
        }
    });
    applyProductFilters();
}

function setOrderType(type, btn) {
    CONFIG.orderType = type;
    document.querySelectorAll('.order-type-btn').forEach(b => {
        b.classList.remove('ring-1', 'ring-current', 'opacity-100');
        b.classList.add('opacity-60');
    });
    if (btn) {
        btn.classList.add('ring-1', 'ring-current', 'opacity-100');
        btn.classList.remove('opacity-60');
    }
    showToast(`Order type: ${type}`, 'info');
}

function applyProductFilters() {
    const query = currentSearchQuery.trim().toLowerCase();
    const filtered = allProducts.filter(p => {
        const matchesCategory = currentCategory === 'all' || p.category_id == currentCategory;
        const matchesTag = !currentTag || (p.tag_ids || []).includes(currentTag);
        const matchesQuery = !query || p.name.toLowerCase().includes(query) || (p.sku && p.sku.toLowerCase().includes(query));
        return matchesCategory && matchesTag && matchesQuery;
    });
    renderProducts(filtered);
}

function renderProducts(products) {
    const grid = document.getElementById('productsGrid');
    if (!grid) return;

    if (products.length === 0) {
        grid.innerHTML = '<div class="col-span-full text-center py-12 text-slate-500">No products found</div>';
        return;
    }
    
    grid.innerHTML = products.map(p => {
        const stockQty = (p.stock != null && p.stock !== '') ? parseInt(p.stock, 10) : 999;
        const isLowStock = stockQty > 0 && stockQty <= 5;
        const hasImage = p.image && String(p.image).trim() !== '';
        let imgRel = String(p.image || '').replace(/^\/+/, '');
        if (imgRel.indexOf('public/') === 0) imgRel = imgRel.substring(7);
        const imgUrl = hasImage ? CONFIG.baseUrl + '/' + imgRel : '';
        const productName = escapeHtml(p.name) || '<span class="text-slate-500">Unnamed</span>';
        const isOOS = stockQty <= 0;
        const borderCls = isOOS ? 'border-red-500/60' : 'border-slate-700/80';
        const priceCls = isOOS ? 'text-red-400/70' : 'text-amber-400';
        const nameCls = isOOS ? 'text-slate-500' : 'text-white';
        const priceText = isOOS ? 'Out of Stock' : (CONFIG.currency + ' ' + Number(p.price).toLocaleString());
        const iconHtml = hasImage
            ? `<img src="${imgUrl}" class="absolute inset-0 w-full h-full object-cover opacity-80 transition-opacity duration-100" alt="">
               <div class="absolute inset-0 bg-gradient-to-t from-slate-900 to-transparent"></div>`
            : `<div class="w-6 h-6 rounded-lg bg-amber-500/10 flex items-center justify-center">
                 <i class="fas fa-box text-amber-400/60 text-xs"></i>
               </div>`;
        const overlayHtml = isOOS
            ? `<span class="absolute top-1 right-1 w-2 h-2 rounded-full bg-red-500" title="Out of stock"></span>
                <div class="absolute inset-0 flex items-center justify-center">
                    <span class="bg-red-500/80 text-white text-[8px] font-bold px-1.5 py-0.5 rounded-full uppercase tracking-wide shadow">Out of Stock</span>
                </div>`
            : isLowStock ? `<span class="absolute top-1 right-1 w-1.5 h-1.5 rounded-full bg-amber-400 s-dot-amber" title="Only ${stockQty} left"></span>` : '';
        return `
            <div class="product-card aspect-square bg-slate-800 ${borderCls} active:scale-95 active:border-amber-400 hover:border-slate-600 rounded-lg relative select-none overflow-hidden flex flex-col transition-all duration-100 group ${isOOS ? 'oos-card' : ''}"
                 data-product-id="${p.id}" data-product-name="${escapeJsString(p.name)}" data-product-price="${p.price}" data-product-stock="${stockQty}" data-product-image="${imgUrl}"
                 ${isOOS ? '' : 'onclick="addToCart(this)"'} role="${isOOS ? 'img' : 'button'}" tabindex="${isOOS ? -1 : 0}" aria-label="${isOOS ? 'Out of stock: ' + p.name : 'Add ' + p.name + ' to cart'}">
                <div class="card-img-area flex-1 relative flex items-center justify-center overflow-hidden">
                    ${iconHtml}${overlayHtml}
                </div>
                <div class="card-info-bar px-1 pt-0.5 pb-0.5 bg-slate-900/95 border-t border-slate-700/40 flex-shrink-0">
                    <div class="text-[9px] font-semibold truncate leading-tight ${nameCls}">${productName}</div>
                    <div class="${priceCls} font-bold text-[9px] leading-tight">${priceText}</div>
                </div>
            </div>
        `;
    }).join('');
}

// ============================================
// CUSTOMER FUNCTIONS
// ============================================
function searchCustomer() {
    const phone = document.getElementById('customerPhone')?.value.trim();
    if (!phone) {
        showToast('Enter a phone number', 'warning');
        return;
    }
    
    fetch(`../ajax/get_customer.php?phone=${encodeURIComponent(phone)}`)
        .then(r => r.json())
        .then(data => {
            const info = document.getElementById('customerInfo');
            if (data.success && data.customer) {
                window.selectedCustomer = data.customer;
                selectedCustomer = window.selectedCustomer;
                _saveState();
                document.getElementById('customerName').textContent = data.customer.name || '';
                document.getElementById('customerPoints').textContent = data.customer.loyalty_points || 0;
                document.getElementById('customerBalance').textContent = data.customer.credit_limit ? formatCurrency(data.customer.credit_limit) : 'N/A';
                document.getElementById('customerTotalSpent').textContent = data.customer.total_spent ? formatCurrency(data.customer.total_spent) : 'N/A';
                info.style.display = 'block';
                showToast(`Customer found: ${data.customer.name}`, 'success');
                document.dispatchEvent(new CustomEvent('customer:selected', { detail: { customer: data.customer } }));
            } else {
                window.selectedCustomer = null;
                selectedCustomer = null;
                info.style.display = 'none';
                showToast(data.message || 'Customer not found', 'warning');
            }
        })
        .catch(() => showToast('Error searching customer', 'error'));
}

function saveCustomer() {
    const phone = document.getElementById('customerPhone')?.value.trim();
    if (!phone) {
        showToast('Enter a phone number first', 'error');
        return;
    }
    if (!window.selectedCustomer) {
        window.selectedCustomer = { id: null, name: 'Walk-in Customer', phone: phone, loyalty_points: 0 };
        selectedCustomer = window.selectedCustomer;
    }
    _saveState();
    showToast(`Customer set: ${window.selectedCustomer.name || phone}`, 'success');
    closeModal('customerModal');
}

// ============================================
// RECEIPT FUNCTIONS
// ============================================
function showReceipt(saleId) {
    const frame = document.getElementById('receiptFrame');
    if (frame) frame.src = `receipts/receipt.php?id=${encodeURIComponent(saleId)}`;
    showModal('receiptModal');
}

function showReceiptWithData(receipt) {
    const frame = document.getElementById('receiptFrame');
    if (frame && receipt) {
        const html = buildReceiptHtml(receipt);
        frame.srcdoc = html;
    }
    showModal('receiptModal');
}

function showReceiptWithItems(items, paymentMethod, invoiceNumber, amountReceived, change) {
    const subtotal = items.reduce((sum, item) => sum + (item.price * item.qty), 0);
    const tax = subtotal * (CONFIG.taxRate / 100);
    const total = subtotal + tax;
    const receipt = {
        invoice_number: invoiceNumber || 'Receipt',
        date: new Date().toLocaleString(),
        items: items.map(item => ({ name: item.name, quantity: item.qty, price: item.price, subtotal: item.price * item.qty, note: item.note || '' })),
        subtotal: subtotal,
        discount: 0,
        tax: tax,
        total: total,
        payment_method: paymentMethod,
        split_payments: [],
        amount_received: amountReceived != null ? amountReceived : total,
        change: change != null ? change : 0
    };
    showReceiptWithData(receipt);
}

function buildReceiptHtml(receipt) {
    const itemsHtml = (receipt.items || []).map(item => `
        <div style="display:flex;justify-content:space-between;margin-bottom:2px;font-size:10pt;">
            <span style="width:60%;word-break:break-word;">${escapeHtml(item.name)} <span style="color:#555;">x${item.quantity}</span></span>
            <span style="width:38%;text-align:right;">${formatCurrency(item.subtotal)}</span>
        </div>
        ${item.note ? `<div style="font-size:8.5pt;color:#777;padding-left:4mm;margin-bottom:2px;">↳ ${escapeHtml(item.note)}</div>` : ''}
    `).join('');

    const logoHtml = CONFIG.logoUrl
        ? `<img src="${CONFIG.logoUrl}" style="max-height:18mm;max-width:60mm;object-fit:contain;display:block;margin:0 auto 2mm;" alt="Logo">`
        : '';
    const splitHtml = (receipt.split_payments || []).length > 1
        ? (receipt.split_payments.map(sp => `<div style="display:flex;justify-content:space-between;font-size:9pt;"><span>${escapeHtml(sp.method||'').toUpperCase()}</span><span>${formatCurrency(sp.amount)}</span></div>`).join(''))
        : `<div style="font-weight:bold;text-align:center;letter-spacing:1px;">PAID: ${escapeHtml((receipt.payment_method||'cash').toUpperCase())}</div>`;

    return `<!DOCTYPE html>
<html><head><meta charset="UTF-8"><title>Receipt</title>
<style>
    @page { size: 80mm auto; margin: 0; }
    * { box-sizing: border-box; }
    body { font-family: 'Courier New', monospace; background: white; margin: 0; padding: 0; width: 80mm; }
    .receipt { width: 80mm; padding: 4mm 5mm 8mm; font-size: 10pt; line-height: 1.4; }
    .center { text-align: center; }
    .bold { font-weight: bold; }
    .dash { border-top: 1px dashed #000; margin: 2mm 0; }
    .row { display: flex; justify-content: space-between; margin-bottom: 1px; font-size: 9.5pt; }
    .total-row { display: flex; justify-content: space-between; font-weight: bold; font-size: 12pt; margin-top: 1mm; }
    .store-name { font-size: 14pt; font-weight: bold; letter-spacing: 0.5px; }
    .sub-info { font-size: 8.5pt; color: #444; }
    @media print {
        body { width: 80mm; }
        .no-print { display: none !important; }
    }
</style>
</head>
<body onload="window.print()">
<div class="receipt">
    <div class="center" style="margin-bottom:2mm;">
        ${logoHtml}
        <div class="store-name">${escapeHtml(CONFIG.companyName)}</div>
        <div class="sub-info">${escapeHtml(CONFIG.branchName)}</div>
        ${CONFIG.branchAddress ? `<div class="sub-info">${escapeHtml(CONFIG.branchAddress)}</div>` : ''}
        ${CONFIG.branchPhone ? `<div class="sub-info">Tel: ${escapeHtml(CONFIG.branchPhone)}</div>` : ''}
        ${CONFIG.vatNumber ? `<div class="sub-info">VAT/TIN: ${escapeHtml(CONFIG.vatNumber)}</div>` : ''}
    </div>
    <div class="dash"></div>
    <div class="row"><span>Invoice:</span><span>${escapeHtml(receipt.invoice_number || '-')}</span></div>
    <div class="row"><span>Date:</span><span>${escapeHtml(receipt.date || new Date().toLocaleString())}</span></div>
    ${receipt.customer_name && receipt.customer_name !== 'Walk-in Customer' ? `<div class="row"><span>Customer:</span><span>${escapeHtml(receipt.customer_name)}</span></div>` : ''}
    <div class="dash"></div>
    ${itemsHtml || '<div class="center sub-info">No items</div>'}
    <div class="dash"></div>
    <div class="row"><span>Subtotal</span><span>${formatCurrency(receipt.subtotal)}</span></div>
    ${receipt.discount > 0 ? `<div class="row"><span>Discount</span><span style="color:#059669;">-${formatCurrency(receipt.discount)}</span></div>` : ''}
    <div class="row"><span>Tax (${CONFIG.taxRate}%)</span><span>${formatCurrency(receipt.tax)}</span></div>
    <div class="dash"></div>
    <div class="total-row"><span>TOTAL</span><span>${formatCurrency(receipt.total)}</span></div>
    <div class="dash"></div>
    ${splitHtml}
    ${(receipt.amount_received != null && receipt.amount_received > 0 && (receipt.split_payments||[]).length <= 1) ? `
    <div class="row"><span>Cash Received</span><span>${formatCurrency(receipt.amount_received)}</span></div>
    <div class="row bold" style="font-size:11pt;"><span>Change</span><span style="color:${(receipt.change||0) > 0 ? '#059669' : '#111'};">${formatCurrency(receipt.change || 0)}</span></div>` : ''}
    <div class="dash"></div>
    <div class="center" style="margin-top:2mm;font-size:9pt;">${escapeHtml(CONFIG.receiptFooter)}</div>
    <div class="center sub-info" style="margin-top:1mm;">Served by: ${escapeHtml(receipt.cashier || '')}</div>
</div>
</body>
    </html>`;
}

function printReceipt() {
    const frame = document.getElementById('receiptFrame');
    if (frame && frame.contentWindow) {
        frame.contentWindow.focus();
        frame.contentWindow.print();
    }
}

// ============================================
// VOUCHER FUNCTIONS
// ============================================
function applyVoucher() {
    const code = document.getElementById('voucherInput')?.value.trim();
    if (!code) {
        showToast('Enter a voucher code', 'warning');
        return;
    }
    
    fetch(`../ajax/validate_voucher.php?code=${encodeURIComponent(code)}&branch_id=${CONFIG.branchId}`)
        .then(r => r.json())
        .then(data => {
            if (data.valid) {
                appliedVoucher = data.voucher;
                appliedDiscount = { type: data.voucher.type, value: data.voucher.value, id: data.voucher.id };
                _saveState();
                updateCartDisplay();
                showToast(`Voucher applied: ${data.voucher.description || code}`, 'success');
            } else {
                showToast(data.message || 'Invalid voucher', 'error');
            }
        })
        .catch(() => showToast('Error validating voucher', 'error'));
}

function applyCouponCode() {
    const input = document.getElementById('couponCodeInput');
    const msgEl = document.getElementById('couponMsg');
    const applyBtn = document.getElementById('couponApplyBtn');
    const clearBtn = document.getElementById('couponClearBtn');
    const code = input?.value?.trim();

    if (!code) return;

    const { subtotal } = getPricingBreakdown();

    applyBtn.disabled = true;
    applyBtn.textContent = '...';
    msgEl.className = 'mt-1 text-[10px]';
    msgEl.textContent = 'Checking…';
    msgEl.classList.remove('hidden');

    fetch('../ajax/apply_coupon.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CONFIG.csrfToken },
        body: JSON.stringify({
            code,
            subtotal,
            customer_id: window.selectedCustomer?.id || '',
            csrf_token: CONFIG.csrfToken
        })
    })
    .then(r => r.json())
    .then(data => {
        if (data.valid) {
            appliedDiscount = {
                id: data.discount.id,
                name: data.discount.name,
                code: data.discount.code,
                type: data.discount.type,
                value: data.discount.value,
                amount: data.discount.amount
            };
            _saveState();
            updateCartDisplay();
            updateTotals();
            msgEl.className = 'mt-1 text-[10px] text-emerald-400';
            msgEl.textContent = `✓ ${data.discount.name} applied (–${formatCurrency(data.discount.amount)})`;
            clearBtn.classList.remove('hidden');
            applyBtn.classList.add('hidden');
            input.readOnly = true;
        } else {
            msgEl.className = 'mt-1 text-[10px] text-red-400';
            msgEl.textContent = data.error || 'Invalid coupon';
            applyBtn.disabled = false;
            applyBtn.textContent = 'Apply';
        }
    })
    .catch(() => {
        msgEl.className = 'mt-1 text-[10px] text-red-400';
        msgEl.textContent = 'Error checking coupon. Try again.';
        applyBtn.disabled = false;
        applyBtn.textContent = 'Apply';
    });
}

function clearCoupon() {
    appliedDiscount = null;
    _saveState();
    const input = document.getElementById('couponCodeInput');
    const msgEl = document.getElementById('couponMsg');
    const applyBtn = document.getElementById('couponApplyBtn');
    const clearBtn = document.getElementById('couponClearBtn');
    if (input) { input.value = ''; input.readOnly = false; }
    if (msgEl) { msgEl.textContent = ''; msgEl.classList.add('hidden'); }
    if (applyBtn) { applyBtn.disabled = false; applyBtn.textContent = 'Apply'; applyBtn.classList.remove('hidden'); }
    if (clearBtn) { clearBtn.classList.add('hidden'); }
    updateCartDisplay();
    updateTotals();
}

// ============================================
// HELD SALES FUNCTIONS
// ============================================
function holdSale() {
    if (window.cart.length === 0) {
        showToast('Cart is empty', 'warning');
        return;
    }
    
    const saleData = {
        items: window.cart,
        total: updateTotals(),
        customer_name: window.selectedCustomer?.name || 'Walk-in',
        table: document.getElementById('tableNumber')?.value || ''
    };
    
    fetch('../ajax/hold_sale.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `action=save&data=${encodeURIComponent(JSON.stringify(saleData))}&branch_id=${CONFIG.branchId}&bt_id=${CONFIG.businessTypeId}&csrf_token=${CONFIG.csrfToken}`
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            clearCart();
            updateHeldBadge(+1);
            showToast('Order held successfully', 'success');
            closeModal('heldModal');
        } else {
            showToast(data.error || 'Failed to hold order', 'error');
        }
    })
    .catch(() => showToast('Error holding order', 'error'));
}

function loadHeldSales() {
    const listEl = document.getElementById('heldList');
    if (!listEl) return;
    listEl.innerHTML = '<div class="text-center py-8 text-slate-500"><i class="fas fa-spinner fa-spin mr-2"></i>Loading...</div>';
    
    fetch(`../ajax/hold_sale.php?action=list&bt_id=${CONFIG.businessTypeId}&csrf_token=${CONFIG.csrfToken}`)
        .then(r => r.json())
        .then(data => {
            if (data.success && data.sales && data.sales.length) {
                listEl.innerHTML = data.sales.map(sale => {
                    const preview = (sale.items_preview || []).map(i => escapeHtml(i.name)).join(', ');
                    return `
                    <div class="bg-slate-700/60 border border-slate-600/50 rounded-xl p-3 mb-2">
                        <div class="flex justify-between items-start mb-2">
                            <div>
                                <div class="text-white font-semibold text-sm">${escapeHtml(sale.customer_name || 'Walk-in')}</div>
                                <div class="text-slate-400 text-xs mt-0.5"><i class="fas fa-clock mr-1"></i>${escapeHtml(sale.formatted_date || '')} ${escapeHtml(sale.formatted_time || '')}</div>
                            </div>
                            <div class="text-amber-400 font-bold text-sm">${formatCurrency(sale.total || 0)}</div>
                        </div>
                        <div class="text-slate-400 text-xs mb-3 truncate"><i class="fas fa-box mr-1"></i>${sale.item_count || 0} item${sale.item_count != 1 ? 's' : ''}${preview ? ' &mdash; ' + preview : ''}</div>
                        <div class="flex gap-2">
                            <button onclick="restoreHeldSale(${sale.id})"
                                class="flex-1 flex items-center justify-center gap-1.5 py-1.5 bg-amber-500 hover:bg-amber-400 active:scale-95 text-slate-900 font-semibold text-xs rounded-lg transition-all">
                                <i class="fas fa-play"></i> Restore to Cart
                            </button>
                            <button onclick="deleteHeldSale(${sale.id}, this)"
                                class="w-8 flex items-center justify-center py-1.5 bg-slate-600 hover:bg-red-500/80 active:scale-95 text-slate-300 hover:text-white text-xs rounded-lg transition-all" title="Delete">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </div>`;
                }).join('');
            } else {
                listEl.innerHTML = '<div class="text-center py-10 text-slate-500"><i class="fas fa-inbox text-3xl mb-2 block"></i>No held orders</div>';
            }
        })
        .catch(() => {
            listEl.innerHTML = '<div class="text-center py-8 text-red-400"><i class="fas fa-exclamation-triangle"></i><p class="mt-2">Error loading held orders</p></div>';
        });
}

function restoreHeldSale(id) {
    fetch(`../ajax/hold_sale.php?action=restore&id=${id}&csrf_token=${CONFIG.csrfToken}`)
        .then(r => r.json())
        .then(data => {
            if (data.success && data.sale_data) {
                const saleData = typeof data.sale_data === 'string' ? JSON.parse(data.sale_data) : data.sale_data;
                const sale = typeof saleData === 'string' ? JSON.parse(saleData) : saleData;
                if (sale && sale.items && sale.items.length) {
                    cart = sale.items;
                    updateCartDisplay();
                    closeModal('heldModal');
                    updateHeldBadge(-1);
                    showToast('Order restored to cart', 'success');
                } else {
                    showToast('Held order had no items', 'error');
                }
            } else {
                showToast(data.message || 'Failed to restore order', 'error');
            }
        })
        .catch(() => showToast('Error restoring order', 'error'));
}

function deleteHeldSale(id, btn) {
    if (!confirm('Delete this held order?')) return;
    if (btn) btn.disabled = true;
    fetch(`../ajax/hold_sale.php`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `action=delete&id=${id}&csrf_token=${CONFIG.csrfToken}`
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            if (btn) btn.closest('.bg-slate-700\\/60').remove();
            updateHeldBadge(-1);
            const listEl = document.getElementById('heldList');
            if (listEl && !listEl.querySelector('.bg-slate-700\\/60')) {
                listEl.innerHTML = '<div class="text-center py-10 text-slate-500"><i class="fas fa-inbox text-3xl mb-2 block"></i>No held orders</div>';
            }
            showToast('Held order deleted', 'success');
        } else {
            if (btn) btn.disabled = false;
            showToast(data.message || 'Failed to delete', 'error');
        }
    })
    .catch(() => { if (btn) btn.disabled = false; showToast('Error deleting order', 'error'); });
}

function updateHeldBadge(delta) {
    ['heldCountBadge', 'mobileHeldBadge'].forEach(id => {
        const badge = document.getElementById(id);
        if (!badge) return;
        const current = parseInt(badge.textContent) || 0;
        const next = Math.max(0, current + delta);
        badge.textContent = next;
        badge.classList.toggle('hidden', next <= 0);
    });
}

// ============================================
// REPORTS FUNCTIONS
// ============================================
function loadReports() {
    const btParam = CONFIG.businessTypeId > 0 ? `&bt_id=${CONFIG.businessTypeId}` : '';
    fetch(`../ajax/get_today_sales.php?branch_id=${CONFIG.branchId}${btParam}`)
        .then(r => r.json())
        .then(data => {
            document.getElementById('reportsContent').innerHTML = `
                <div class="grid grid-cols-2 gap-3 mb-4">
                    <div class="bg-slate-700 rounded-lg p-3 text-center">
                        <div class="text-amber-400 text-xl font-bold">${formatCurrency(data.total || 0)}</div>
                        <div class="text-slate-400 text-xs">Total Sales</div>
                    </div>
                    <div class="bg-slate-700 rounded-lg p-3 text-center">
                        <div class="text-emerald-400 text-xl font-bold">${data.count || 0}</div>
                        <div class="text-slate-400 text-xs">Transactions</div>
                    </div>
                </div>
                <div class="space-y-2">
                    <div class="flex justify-between text-sm"><span class="text-slate-400">Cash</span><span class="text-white">${formatCurrency(data.cash_total || 0)}</span></div>
                    <div class="flex justify-between text-sm"><span class="text-slate-400">Card</span><span class="text-white">${formatCurrency(data.card_total || 0)}</span></div>
                    <div class="flex justify-between text-sm"><span class="text-slate-400">M-Pesa</span><span class="text-white">${formatCurrency(data.mpesa_total || 0)}</span></div>
                </div>
                <div class="mt-4 pt-3 border-t border-slate-700">
                    <div class="flex justify-between text-sm"><span class="text-slate-400">Today's Profit</span><span class="text-white">${formatCurrency(data.profit || 0)}</span></div>
                    <div class="flex justify-between text-sm"><span class="text-slate-400">Margin</span><span class="text-emerald-400">${data.margin || 0}%</span></div>
                </div>
            `;
        })
        .catch(() => {
            document.getElementById('reportsContent').innerHTML = '<div class="text-center py-8 text-red-400"><i class="fas fa-exclamation-triangle"></i><p class="mt-2">Error loading reports</p></div>';
        });
}

function refreshTodayStats() {
    const btParam = CONFIG.businessTypeId > 0 ? `&bt_id=${CONFIG.businessTypeId}` : '';
    fetch(`../ajax/get_today_sales.php?branch_id=${CONFIG.branchId}${btParam}`)
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                const totalEl = document.getElementById('todaySalesValue');
                const countEl = document.getElementById('todaySalesCount');
                if (totalEl) totalEl.textContent = formatCurrency(data.total || 0);
                if (countEl) countEl.innerHTML = `<i class="fas fa-receipt"></i> ${data.sale_count || 0}`;
            }
        })
        .catch(() => {});
}

// ============================================
// CALCULATOR FUNCTIONS
// ============================================
function updateCalcDisplay() {
    const display = document.getElementById('calcDisplay');
    if (display) display.textContent = calcCurrent;
}

function calcInput(num) {
    if (calcCurrent === '0' && num !== '.') calcCurrent = num;
    else calcCurrent += num;
    updateCalcDisplay();
}

function calcSetOperator(op) {
    if (calcPrev !== null) calcCalculate();
    calcPrev = parseFloat(calcCurrent);
    calcOperator = op;
    calcCurrent = '0';
}

function calcCalculate() {
    if (calcPrev === null || calcOperator === null) return;
    const current = parseFloat(calcCurrent);
    let result;
    switch (calcOperator) {
        case '+': result = calcPrev + current; break;
        case '-': result = calcPrev - current; break;
        case '*': result = calcPrev * current; break;
        case '/': result = calcPrev / current; break;
        case '%': result = calcPrev % current; break;
        default: return;
    }
    calcCurrent = result.toString();
    calcPrev = null;
    calcOperator = null;
    updateCalcDisplay();
}

function calcClear() {
    calcCurrent = '0';
    calcPrev = null;
    calcOperator = null;
    updateCalcDisplay();
}

function copyCalcResult() {
    const result = document.getElementById('calcDisplay')?.textContent || '0';
    navigator.clipboard.writeText(result).then(() => showToast('Copied: ' + result, 'success'));
}

// ============================================
// AI RECOMMENDATIONS
// ============================================
function loadAIRecommendations() {
    clearTimeout(_aiDebounceTimer);
    _aiDebounceTimer = setTimeout(_fetchAIRecommendations, 800);
}

function _fetchAIRecommendations() {
    const strip = document.getElementById('aiSuggestionsStrip');
    if (window.cart.length === 0) {
        if (strip) strip.classList.add('hidden');
        return;
    }
    const cartItems = window.cart.map(i => ({ id: i.id }));
    const cartProductIds = window.cart.map(i => i.id);
    fetch('../ajax/ai_recommendations.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ cart_items: cartItems, limit: 5 })
    })
    .then(r => r.json())
    .then(data => {
        if (!strip) return;
        if (data.success && data.recommendations && data.recommendations.length > 0) {
            const filtered = data.recommendations.filter(p => !cartProductIds.includes(p.id));
            if (!filtered.length) { strip.classList.add('hidden'); return; }
            const reasonEl = document.getElementById('aiStripReason');
            const productsEl = document.getElementById('aiStripProducts');
            if (reasonEl) reasonEl.textContent = data.reason || 'Suggestions';
            if (productsEl) {
                productsEl.innerHTML = filtered.slice(0, 4).map(p => `
                    <button onclick="addRecommendedProduct(${p.id},'${escapeJsString(p.name)}',${p.price},${p.stock||999})"
                        class="flex-shrink-0 flex flex-col items-center gap-0.5 p-1.5 bg-slate-800 hover:bg-amber-500/20 active:scale-95 border border-slate-700/60 hover:border-amber-500/40 rounded-lg transition-all w-14 text-center">
                        <i class="fas fa-plus-circle text-amber-400 text-xs"></i>
                        <span class="text-white text-[9px] leading-tight line-clamp-2">${escapeHtml(p.name)}</span>
                        <span class="text-amber-400 text-[9px] font-bold">${formatCurrency(p.price)}</span>
                    </button>
                `).join('');
            }
            strip.classList.remove('hidden');
        } else {
            strip.classList.add('hidden');
        }
    })
    .catch(() => { if (strip) strip.classList.add('hidden'); });
}

function addRecommendedProduct(id, name, price, stock) {
    addToCart({ dataset: { productId: id, productName: name, productPrice: price, productStock: stock } });
}

// ============================================
// LOYALTY POINTS
// ============================================
const POINTS_PER_CURRENCY = 1;
const CURRENCY_PER_POINT = 0.01;

function showLoyaltyRowIfCustomer() {
    const row = document.getElementById('loyaltyRedeemRow');
    if (!row) return;
    if (window.selectedCustomer && window.selectedCustomer.loyalty_points > 0) {
        const pts = parseInt(window.selectedCustomer.loyalty_points) || 0;
        const val = (pts * CURRENCY_PER_POINT).toFixed(2);
        document.getElementById('loyaltyPointsDisplay').textContent = pts;
        document.getElementById('loyaltyValueDisplay').textContent = val;
        document.getElementById('loyaltyRedeemInput').max = pts;
        row.classList.remove('hidden');
    } else {
        row.classList.add('hidden');
        _loyaltyRedeemDiscount = 0;
        _loyaltyPointsToRedeem = 0;
    }
}

function applyLoyaltyRedeem() {
    const maxPts = parseInt(window.selectedCustomer?.loyalty_points || 0);
    const inputEl = document.getElementById('loyaltyRedeemInput');
    const msgEl = document.getElementById('loyaltyDiscountMsg');
    let pts = parseInt(inputEl?.value || 0);
    if (pts < 0) pts = 0;
    if (pts > maxPts) { pts = maxPts; if (inputEl) inputEl.value = maxPts; }
    _loyaltyPointsToRedeem = pts;
    _loyaltyRedeemDiscount = pts * CURRENCY_PER_POINT;
    if (msgEl) {
        if (pts > 0) {
            msgEl.textContent = `${pts} pts = ${CONFIG.currency} ${_loyaltyRedeemDiscount.toFixed(2)} discount`;
            msgEl.classList.remove('hidden');
        } else {
            msgEl.classList.add('hidden');
        }
    }
    updatePaymentModalTotals();
}

function awardLoyaltyPoints(soldCart, totalPaid) {
    if (!window.selectedCustomer || !window.selectedCustomer.id) return;
    const points = Math.floor(totalPaid * POINTS_PER_CURRENCY);
    if (points <= 0) return;
    fetch('../ajax/get_customer_loyalty.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `action=award&customer_id=${window.selectedCustomer.id}&points=${points}&csrf_token=${CONFIG.csrfToken}`
    }).catch(() => {});
}

// ============================================
// STOCK HELPERS
// ============================================
function updateGridStockAfterSale(soldItems) {
    soldItems.forEach(item => {
        const p = allProducts.find(x => x.id === item.id);
        if (p) p.stock = Math.max(0, p.stock - item.qty);
        const card = document.querySelector(`.product-card[data-product-id="${item.id}"]`);
        if (card) {
            const newStock = Math.max(0, parseInt(card.dataset.productStock ?? 999, 10) - item.qty);
            card.dataset.productStock = newStock;
            const imgArea = card.querySelector('.card-img-area');
            const badge = imgArea?.querySelector('.absolute');
            if (newStock <= 0) {
                card.classList.add('oos-card');
                if (badge) badge.remove();
                const overlayHtml = `<span class="absolute top-1 right-1 w-2 h-2 rounded-full bg-red-500" title="Out of stock"></span>
                    <div class="absolute inset-0 flex items-center justify-center">
                        <span class="bg-red-500/80 text-white text-[8px] font-bold px-1.5 py-0.5 rounded-full uppercase tracking-wide shadow">Out of Stock</span>
                    </div>`;
                imgArea.insertAdjacentHTML('beforeend', overlayHtml);
            } else if (newStock <= 5) {
                card.classList.remove('oos-card');
                if (badge) badge.className = 'absolute top-1 right-1 w-1.5 h-1.5 rounded-full bg-amber-400 s-dot-amber';
            } else {
                card.classList.remove('oos-card');
                if (badge) badge.remove();
            }
        }
    });
}

// ============================================
// QTY POPUP
// ============================================
function showQtyPopup(index) {
    const item = window.cart[index];
    if (!item) return;
    const current = item.qty;
    const val = prompt(`Qty for "${item.name}":`, current);
    if (val === null) return;
    const n = parseInt(val);
    if (!n || n < 1) { showToast('Invalid quantity', 'error'); return; }
    if (n > item.stock) { showToast('Exceeds stock (' + item.stock + ')', 'error'); return; }
    item.qty = n;
    updateCartDisplay();
}

// ============================================
// ITEM NOTES
// ============================================
function toggleItemNote(index) {
    const row = document.getElementById('noteRow_' + index);
    if (!row) return;
    row.classList.toggle('hidden');
    if (!row.classList.contains('hidden')) row.querySelector('input')?.focus();
}

function saveItemNote(index, val) {
    if (window.cart[index]) { window.cart[index].note = val; _saveState(); }
}

// ============================================
// LAST SALE
// ============================================
function viewLastSale() {
    if (!_lastSaleId) { showToast('No sale yet this session', 'info'); return; }
    showReceipt(_lastSaleId);
}

function trackBehavior(completedCart) {
    if (!completedCart || completedCart.length === 0) return;
    const productIds = completedCart.map(i => i.id);
    const total = completedCart.reduce((s, i) => s + (i.price * i.qty), 0);
    fetch('../ajax/ai_track_behavior.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ product_ids: productIds, total_amount: total })
    }).catch(() => {});
}

// ============================================
// PRICE OVERRIDE
// ============================================
let currentOverrideIndex = null;

function showPriceOverrideModal(index) {
    const item = window.cart[index];
    if (!item) return;
    currentOverrideIndex = index;
    document.getElementById('overrideProductName').textContent = item.name;
    document.getElementById('overrideCurrentPrice').textContent = formatCurrency(item.price);
    document.getElementById('overridePriceInput').value = '';
    document.getElementById('adminPinInput').value = '';
    showModal('priceOverrideModal');
}

function confirmPriceOverride() {
    const newPrice = parseFloat(document.getElementById('overridePriceInput')?.value);
    const pin = document.getElementById('adminPinInput')?.value;
    
    if (isNaN(newPrice) || newPrice <= 0) {
        showToast('Enter a valid price', 'error');
        return;
    }
    if (!pin) {
        showToast('Admin PIN required', 'error');
        return;
    }
    
    fetch('../ajax/override_price.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `action=override&price=${newPrice}&pin=${encodeURIComponent(pin)}&csrf_token=${CONFIG.csrfToken}`
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            if (currentOverrideIndex !== null && window.cart[currentOverrideIndex]) {
                window.cart[currentOverrideIndex].price = newPrice;
                updateCartDisplay();
                showToast('Price updated successfully', 'success');
                closeModal('priceOverrideModal');
            }
        } else {
            showToast(data.error || 'Override failed', 'error');
        }
    })
    .catch(() => showToast('Error processing override', 'error'));
}

// ============================================
// BUSINESS TYPE FEATURES
// ============================================
function togglePrescription(enabled) {
    const fields = document.getElementById('prescriptionFields');
    if (fields) fields.style.display = enabled ? 'block' : 'none';
}

function toggleAgeVerify(enabled) {
    const fields = document.getElementById('ageVerifyFields');
    if (fields) fields.style.display = enabled ? 'block' : 'none';
    const knob = document.getElementById('ageVerifyKnob');
    if (knob && knob.parentElement) {
        knob.style.transform = enabled ? 'translateX(20px)' : 'translateX(0)';
        knob.parentElement.style.background = enabled ? '#10B981' : '#374151';
    }
}

function applyTableNumber() {
    const tableNum = document.getElementById('tableNumber')?.value;
    if (tableNum) showToast(`Table ${tableNum} selected`, 'success');
}

function applyWeight() {
    const weight = parseFloat(document.getElementById('weightInput')?.value || 0);
    if (weight <= 0) {
        showToast('Enter a valid weight', 'warning');
        return;
    }
    const unit = document.getElementById('weightUnit')?.value || 'kg';
    if (window.cart.length > 0) {
        const lastItem = window.cart[window.cart.length - 1];
        showToast(`Weight ${weight}${unit} applied to ${lastItem.name}`, 'success');
    } else {
        showToast(`Weight ${weight}${unit} set`, 'success');
    }
}

function setWholesaleTier(tier) {
    const tierDef = (CONFIG.wholesaleTiers || []).find(t => t.value === tier);
    const tierLabel = tierDef
        ? (tierDef.discount > 0 ? `${tierDef.label} (${tierDef.discount}%)` : tierDef.label)
        : tier;
    const badge = document.getElementById('tierBadge');
    if (badge) {
        badge.textContent = tierLabel;
        badge.style.display = tier && tier !== 'retail' ? 'inline-block' : 'none';
    }
    cart.forEach(item => {
        item.wholesaleTier = tier;
        item.wholesaleDiscount = tierDef ? (tierDef.discount / 100) : 0;
    });
    updateCartDisplay();
    showToast(`Pricing tier: ${tierLabel}`, 'info');
}

function setAppointment(appointmentId) {
    if (appointmentId === 'new') {
        showToast('Opening appointment scheduler...', 'info');
        return;
    }
    if (appointmentId) {
        const select = document.getElementById('appointmentSelect');
        const option = select?.options[select.selectedIndex];
        if (option) {
            showToast(`Appointment: ${option.dataset.customer || 'Customer'}`, 'success');
        }
    }
}

// ============================================
// UI HELPERS
// ============================================
function goHome() {
    if (window.cart.length > 0 && !confirm('You have items in cart. Leave anyway?')) return;
    window.location.href = CONFIG.baseUrl + '/dashboard/home.php';
}

function togglePosCart() {
    const cartPanel = document.querySelector('.cart-panel');
    const backdrop = document.getElementById('cartBackdrop');
    if (!cartPanel) return;
    const isOpen = cartPanel.classList.toggle('open');
    if (backdrop) backdrop.classList.toggle('open', isOpen);
    document.body.style.overflow = isOpen ? 'hidden' : '';
}

function closePosCart() {
    const cartPanel = document.querySelector('.cart-panel');
    const backdrop = document.getElementById('cartBackdrop');
    if (cartPanel) cartPanel.classList.remove('open');
    if (backdrop) backdrop.classList.remove('open');
    document.body.style.overflow = '';
}

function updatePosCartFabBadge() {
    const count = window.cart.reduce((sum, item) => sum + item.qty, 0);
    const badge = document.getElementById('posCartFabBadge');
    if (badge) {
        badge.textContent = count;
        badge.classList.toggle('hidden', count <= 0);
    }
}

function showConfetti() {
    const colors = ['#fbbf24', '#10b981', '#3b82f6', '#8b5cf6', '#ef4444'];
    for (let i = 0; i < 80; i++) {
        const conf = document.createElement('div');
        conf.className = 'confetti-piece';
        conf.style.cssText = `left: ${Math.random() * 100}%; background: ${colors[Math.floor(Math.random() * colors.length)]}; width: ${6 + Math.random() * 6}px; height: ${6 + Math.random() * 6}px; top: -10px; animation-delay: ${Math.random() * 0.5}s;`;
        document.body.appendChild(conf);
        setTimeout(() => conf.remove(), 2000);
    }
}

function openCalculator() {
    calcClear();
    showModal('calculatorModal');
}

// ============================================
// QUICK USER SWITCH
// ============================================
function openQuickSwitch() {
    document.getElementById('quickSwitchUser').value = '';
    document.getElementById('quickSwitchPin').value = '';
    showModal('quickSwitchModal');
}

// ============================================
// HELP OVERLAY
// ============================================
function openPosHelp() {
    showModal('posHelpOverlay');
}

function closePosHelp() {
    closeModal('posHelpOverlay');
}

// ============================================
// REFRESH PRODUCTS
// ============================================
function refreshProducts() {
    const refreshBtn = document.getElementById('refreshBtn');
    const refreshIcon = document.getElementById('refreshIcon');
    const loading = document.getElementById('productsLoading');
    
    refreshBtn.disabled = true;
    refreshIcon.classList.add('fa-spin');
    loading.classList.remove('hidden');
    
    fetch('../ajax/clear_product_cache.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `csrf_token=${CONFIG.csrfToken}`
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            showToast('Products refreshed', 'success');
            setTimeout(() => window.location.reload(), 500);
        } else {
            throw new Error(data.error || 'Failed to refresh');
        }
    })
    .catch(e => {
        showToast('Failed to refresh products', 'error');
    })
    .finally(() => {
        refreshBtn.disabled = false;
        refreshIcon.classList.remove('fa-spin');
        loading.classList.add('hidden');
    });
}

// ============================================
// LOAD PRODUCTS LAZY
// ============================================
function loadProductsLazy() {
    const grid = document.getElementById('productsGrid');
    const loading = document.getElementById('productsLoading');
    const errorDiv = document.getElementById('productsError');
    const emptyDiv = document.getElementById('productsEmpty');

    if (loading) loading.classList.remove('hidden');
    if (errorDiv) errorDiv.classList.add('hidden');

    fetch('../ajax/get_products.php?page=1&limit=100', {
        method: 'GET',
        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(response => {
        if (!response.ok) throw new Error(`HTTP ${response.status}`);
        return response.json();
    })
    .then(data => {
        if (data.success) {
            allProducts = data.products || [];
            renderProducts(allProducts);
            if (loading) loading.classList.add('hidden');
        } else {
            throw new Error(data.error || 'Unknown error');
        }
    })
    .catch(e => {
        console.error('Failed to load products:', e);
        if (loading) loading.classList.add('hidden');
        if (errorDiv) {
            errorDiv.classList.remove('hidden');
            errorDiv.innerHTML = `
                <i class="fas fa-exclamation-triangle text-red-400 text-2xl mb-2"></i>
                <p class="text-red-400 text-sm">Failed to load products: ${e.message}</p>
                <button onclick="refreshProducts()" class="mt-2 px-4 py-2 bg-blue-500/20 text-blue-400 rounded-lg text-xs hover:bg-blue-500/30">
                    <i class="fas fa-sync-alt mr-1"></i>Try Again
                </button>
            `;
        }
        if (emptyDiv) emptyDiv.classList.remove('hidden');
    });
}

// ============================================
// EVENT LISTENERS & INITIALIZATION
// ============================================
document.addEventListener('DOMContentLoaded', () => {
    _loadState();
    if (window.cart.length > 0) updateCartDisplay();
    if (allProducts.length > 0) {
        applyProductFilters();
    } else {
        loadProductsLazy();
    }
    
    const searchBox = document.getElementById('searchBox');
    if (searchBox) searchBox.addEventListener('input', (e) => searchProducts(e.target.value));
    
    const amountReceived = document.getElementById('amountReceived');
    if (amountReceived) {
        amountReceived.addEventListener('input', function() { calculateChange(); });
    }
    
    const branchSelect = document.getElementById('branchSelect');
    if (branchSelect) {
        branchSelect.addEventListener('change', (e) => {
            const branchId = e.target.value;
            if (branchId != CONFIG.branchId && confirm('Switch branch? Cart will be cleared.')) {
                const btParam = CONFIG.businessTypeId > 0 ? `&bt_id=${CONFIG.businessTypeId}` : '';
                window.location.href = `?branch=${branchId}&branch_token=${CONFIG.csrfToken}${btParam}`;
            } else if (branchId != CONFIG.branchId) {
                e.target.value = CONFIG.branchId;
            }
        });
    }
    
    updatePosCartFabBadge();
});

// Keyboard shortcuts
document.addEventListener('keydown', (e) => {
    if (e.target.matches('input, textarea, select')) return;
    
    if (e.key === 'F1') { e.preventDefault(); document.getElementById('searchBox')?.focus(); }
    else if (e.key === 'F2') { e.preventDefault(); if (window.cart.length > 0) showPaymentModal(); }
    else if (e.key === 'F4') { e.preventDefault(); holdSale(); }
    else if (e.key === 'F6') { e.preventDefault(); showModal('customerModal'); }
    else if (e.key === 'F8') { e.preventDefault(); clearCart(); }
    else if (e.altKey && e.key === 'c') { e.preventDefault(); openCalculator(); }
    else if (e.altKey && e.key === 'h') { e.preventDefault(); goHome(); }
    else if (e.key === 'Escape') { closeAllModals(); }
    else if (e.key === '?' || (e.shiftKey && e.key === '/')) { e.preventDefault(); openPosHelp(); }
    else if ((e.key === 'c' || e.key === 'C') && window.innerWidth <= 768) { e.preventDefault(); togglePosCart(); }
});

// Calculator event delegation
document.addEventListener('click', (e) => {
    const btn = e.target.closest('.calc-btn');
    if (!btn) return;
    if (btn.dataset.action === 'clear') calcClear();
    else if (btn.dataset.action === 'number') calcInput(btn.dataset.num);
    else if (btn.dataset.action === 'operator') calcSetOperator(btn.dataset.op);
    else if (btn.dataset.action === 'equals') calcCalculate();
});

// Keypad for payment modal
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.keypad-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            const value = btn.dataset.value;
            const input = document.getElementById('amountReceived');
            if (!input) return;
            if (value === 'C') input.value = '';
            else if (value === '.') { if (!input.value.includes('.')) input.value += '.'; }
            else input.value += value;
            calculateChange();
        });
    });
});

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.quick-cash-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            const amount = btn.dataset.amount;
            const input = document.getElementById('amountReceived');
            if (!input) return;
            if (amount === '0') input.value = updateTotals().toFixed(2);
            else input.value = amount;
            calculateChange();
        });
    });
});

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.override-keypad-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            const value = btn.dataset.value;
            const input = document.getElementById('overridePriceInput');
            if (!input) return;
            if (value === 'C') input.value = '';
            else if (value === '.') { if (!input.value.includes('.')) input.value += '.'; }
            else input.value += value;
        });
    });
});

// Keypad for quick switch PIN
document.addEventListener('DOMContentLoaded', function() {
    const pinInput = document.getElementById('quickSwitchPin');
    const userInput = document.getElementById('quickSwitchUser');
    document.querySelectorAll('#quickSwitchModal .keypad-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const key = this.dataset.key;
            if (key === 'clear') {
                pinInput.value = '';
            } else if (key === 'enter') {
                performQuickSwitch();
            } else if (pinInput.value.length < 6) {
                pinInput.value += key;
            }
        });
    });
    if (userInput) {
        userInput.addEventListener('keydown', function(e) {
            if (e.key === 'Enter') performQuickSwitch();
        });
    }
});

async function performQuickSwitch() {
    const userId = document.getElementById('quickSwitchUser').value;
    const pin = document.getElementById('quickSwitchPin').value;
    if (!userId || !pin) { showToast('Enter user ID and PIN', 'error'); return; }
    try {
        const res = await fetch('../ajax/quick_switch_user.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `user_id=${encodeURIComponent(userId)}&pin=${encodeURIComponent(pin)}&csrf_token=${CONFIG.csrfToken}`
        });
        const data = await res.json();
        if (data.success) {
            cart = []; localStorage.removeItem(_lsKey('cart')); updateCartDisplay();
            window.location.reload();
        } else {
            showToast(data.error || 'Switch failed', 'error');
        }
    } catch (e) {
        showToast('Error switching user', 'error');
    }
}

// Live clock
(function startClock() {
    const clockEl = document.getElementById('liveClock');
    const dateEl = document.getElementById('liveDate');
    const days = ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'];
    const months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
    function tick() {
        const now = new Date();
        const h = String(now.getHours()).padStart(2,'0');
        const m = String(now.getMinutes()).padStart(2,'0');
        const s = String(now.getSeconds()).padStart(2,'0');
        if (clockEl) clockEl.textContent = h + ':' + m + ':' + s;
        if (dateEl) dateEl.textContent = days[now.getDay()] + ' ' + now.getDate() + ' ' + months[now.getMonth()];
    }
    tick();
    setInterval(tick, 1000);
})();

// Real-time stock sync (SSE)
if (window.EventSource) {
    (function startStockSync() {
        let sse = null;
        let lastEventId = 0;
        let reconnectDelay = 5000;
        let retryCount = 0;
        const maxRetries = 3;
        
        function connect() {
            if (retryCount >= maxRetries) return;
            const url = `../api/stock-events.php?last_id=${lastEventId}`;
            sse = new EventSource(url);
            
            sse.addEventListener('stock_change', (e) => {
                const ev = JSON.parse(e.data);
                lastEventId = parseInt(e.lastEventId, 10) || lastEventId;
                const p = allProducts.find(x => x.id === ev.product_id);
                if (p) p.stock = ev.new_qty;
                window.cart.forEach(item => { if (item.id === ev.product_id) item.stock = ev.new_qty; });
                const card = document.querySelector(`.product-card[data-product-id="${ev.product_id}"]`);
                if (card) {
                    card.setAttribute('data-product-stock', ev.new_qty);
                    const imgArea = card.querySelector('.card-img-area');
                    const existingBadge = imgArea?.querySelector('.absolute');
                    const existingOverlay = imgArea?.querySelector('.inset-0.flex.items-center');
                    if (ev.new_qty <= 0) {
                        card.classList.add('oos-card');
                        if (existingBadge) existingBadge.remove();
                        if (existingOverlay) existingOverlay.remove();
                        imgArea.insertAdjacentHTML('beforeend', `<span class="absolute top-1 right-1 w-2 h-2 rounded-full bg-red-500" title="Out of stock"></span>
                            <div class="absolute inset-0 flex items-center justify-center">
<span class="bg-red-500/80 text-white text-[8px] font-bold px-1.5 py-0.5 rounded-full uppercase tracking-wide shadow">Out of Stock</span>
                            </div>`);
                    } else {
                        card.classList.remove('oos-card');
                        if (existingBadge) {
                            existingBadge.className = `absolute top-1 right-1 w-1.5 h-1.5 rounded-full ${ev.new_qty <= 5 ? 'bg-amber-400 s-dot-amber' : ''}`;
                            if (ev.new_qty > 5) existingBadge.remove();
                        }
                        if (existingOverlay) existingOverlay.remove();
                    }
                }
                const cartItem = window.cart.find(c => c.id === ev.product_id);
                if (cartItem && ev.new_qty <= 0) showToast(`⚠️ ${p ? p.name : 'Item'} is out of stock!`, 'warning');
            });
            
            sse.onerror = () => {
                retryCount++;
                if (retryCount >= maxRetries) return;
                sse.close();
                setTimeout(connect, reconnectDelay);
                reconnectDelay = Math.min(reconnectDelay * 2, 60000);
            };
        }
        connect();
    })();
}
</script>

<!-- Advanced POS Modules (async to not block render) -->
<script src="<?= base_url("assets/js/pos-smart-checkout.js") ?>" async></script>
<script src="<?= base_url("assets/js/pos-offline-advanced.js") ?>" async></script>
<script src="<?= base_url("assets/js/pos-advanced-loyalty.js") ?>" async></script>
