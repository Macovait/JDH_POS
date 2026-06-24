/**
 * POS Helper Module for Jakababa POS
 * Complements the inline JS in pos.php with:
 * - Barcode scanning support
 * - Auto-refresh today's sales
 * - Keyboard shortcuts
 * - Offline cart persistence
 * - Business-type-aware utilities
 *
 * This file assumes CONFIG is defined by pos.php inline script.
 */

(function () {
    'use strict';

    // Wait for DOM and CONFIG
    if (typeof CONFIG === 'undefined') {
        console.warn('pos.js: CONFIG not found, deferring initialization');
        document.addEventListener('DOMContentLoaded', function () {
            if (typeof CONFIG === 'undefined') {
                console.error('pos.js: CONFIG still not available');
                return;
            }
            initPosHelpers();
        });
        return;
    }

    document.addEventListener('DOMContentLoaded', initPosHelpers);

    function initPosHelpers() {
        initBarcodeScanner();
        initAutoRefreshSales();
        initKeyboardShortcuts();
        initPosPageEvents();
        initPosImageFallbacks();
        initOfflineCartPersistence();
    }

    // -------------------------------------------------------
    // Barcode Scanner Support
    // -------------------------------------------------------
    function initBarcodeScanner() {
        let barcodeBuffer = '';
        let barcodeTimer = null;
        const BARCODE_TIMEOUT = 100; // ms between keystrokes for barcode scan

        document.addEventListener('keypress', function (e) {
            // Ignore if user is typing in an input/textarea
            const tag = (e.target.tagName || '').toLowerCase();
            if (tag === 'input' || tag === 'textarea' || tag === 'select') return;

            if (e.key === 'Enter') {
                if (barcodeBuffer.length >= 3) {
                    e.preventDefault();
                    lookupBarcode(barcodeBuffer.trim());
                }
                barcodeBuffer = '';
                clearTimeout(barcodeTimer);
                return;
            }

            barcodeBuffer += e.key;
            clearTimeout(barcodeTimer);
            barcodeTimer = setTimeout(function () {
                barcodeBuffer = '';
            }, BARCODE_TIMEOUT);
        });
    }

    function lookupBarcode(barcode) {
        if (!barcode || !CONFIG.ajaxUrl) return;

        var params = new URLSearchParams({
            barcode: barcode,
            branch_id: CONFIG.branchId,
            company_id: CONFIG.companyId,
            user_id: CONFIG.userId,
            business_type: CONFIG.businessType
        });

        fetch(CONFIG.ajaxUrl + 'get_product_by_barcode.php?' + params.toString())
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data.found && data.product) {
                    var p = data.product;
                    if (typeof window.addToCart === 'function') {
                        window.addToCart(p.id, p.name, p.price, p.stock);
                    }
                } else {
                    if (typeof window.showToast === 'function') {
                        window.showToast('Product not found for barcode: ' + barcode, 'error');
                    }
                }
            })
            .catch(function (err) {
                console.error('Barcode lookup error:', err);
            });
    }

    // -------------------------------------------------------
    // Auto-Refresh Today's Sales
    // -------------------------------------------------------
    function initAutoRefreshSales() {
        refreshTodaySales();
        setInterval(refreshTodaySales, 60000); // every 60 seconds
    }

    function refreshTodaySales() {
        if (!CONFIG.ajaxUrl) return;

        fetch(CONFIG.ajaxUrl + 'get_today_sales.php?branch_id=' + CONFIG.branchId)
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data.success) {
                    var el = document.getElementById('todaySalesValue');
                    if (el) {
                        el.textContent = CONFIG.currency + ' ' + parseFloat(data.total).toFixed(0);
                    }
                    var cnt = document.getElementById('todaySalesCount');
                    if (cnt && data.sale_count !== undefined) {
                        cnt.innerHTML = '<i class="fas fa-receipt"></i> ' + parseInt(data.sale_count);
                    }
                }
            })
            .catch(function () { /* silent fail for background refresh */ });
    }

    // -------------------------------------------------------
    // Keyboard Shortcuts
    // -------------------------------------------------------
    function initKeyboardShortcuts() {
        document.addEventListener('keydown', function (e) {
            // F2 - Focus search
            if (e.key === 'F2') {
                e.preventDefault();
                var searchInput = document.getElementById('search');
                if (searchInput) searchInput.focus();
            }

            // F4 - Hold sale
            if (e.key === 'F4') {
                e.preventDefault();
                if (typeof window.holdSale === 'function') window.holdSale();
            }

            // F5 - Recall sale
            if (e.key === 'F5') {
                e.preventDefault();
                if (typeof window.recallSale === 'function') window.recallSale();
            }

            // F8 - Process cash payment
            if (e.key === 'F8') {
                e.preventDefault();
                if (typeof window.processPayment === 'function') window.processPayment('cash');
            }

            // F9 - Process card payment
            if (e.key === 'F9') {
                e.preventDefault();
                if (typeof window.processPayment === 'function') window.processPayment('card');
            }

            // Ctrl+Enter - Quick checkout cash
            if (e.ctrlKey && e.key === 'Enter') {
                e.preventDefault();
                if (typeof window.processPayment === 'function') window.processPayment('cash');
            }
        });
    }

    function initPosPageEvents() {
        document.body.addEventListener('click', function (event) {
            var target = event.target.closest('[data-action]');
            if (!target) return;

            var action = target.dataset.action;
            switch (action) {
                case 'go-home':
                    event.preventDefault();
                    if (typeof window.goHome === 'function') window.goHome();
                    break;
                case 'show-shift-modal':
                    event.preventDefault();
                    if (typeof window.showShiftModal === 'function') window.showShiftModal();
                    break;
                case 'show-held-sales':
                    event.preventDefault();
                    if (typeof window.showHeldSales === 'function') window.showHeldSales();
                    break;
                case 'show-customer-modal':
                    event.preventDefault();
                    if (typeof window.showCustomerModal === 'function') window.showCustomerModal();
                    break;
                case 'open-calculator':
                    event.preventDefault();
                    if (typeof window.openCalculator === 'function') window.openCalculator();
                    break;
                case 'show-payment-modal':
                    event.preventDefault();
                    if (typeof window.showPaymentModal === 'function') window.showPaymentModal();
                    break;
                case 'show-reports-modal':
                    event.preventDefault();
                    if (typeof window.showReportsModal === 'function') window.showReportsModal();
                    break;
                case 'set-order-type':
                    event.preventDefault();
                    if (typeof window.setOrderType === 'function') {
                        window.setOrderType(target.dataset.orderType || target.dataset.type || 'walkin');
                    }
                    break;
                case 'apply-weight':
                    event.preventDefault();
                    if (typeof window.applyWeight === 'function') window.applyWeight();
                    break;
                case 'apply-serial-warranty':
                    event.preventDefault();
                    if (typeof window.applySerialWarranty === 'function') window.applySerialWarranty();
                    break;
                case 'close-shift-modal':
                    event.preventDefault();
                    if (typeof window.closeShiftModal === 'function') window.closeShiftModal();
                    break;
                case 'close-shift':
                    event.preventDefault();
                    if (typeof window.closeShift === 'function') window.closeShift();
                    break;
                case 'open-shift':
                    event.preventDefault();
                    if (typeof window.openShift === 'function') window.openShift();
                    break;
                case 'hold-sale':
                    event.preventDefault();
                    if (typeof window.holdSale === 'function') window.holdSale();
                    break;
                case 'apply-voucher':
                    event.preventDefault();
                    if (typeof window.applyVoucher === 'function') window.applyVoucher();
                    break;
                case 'filter-category':
                    event.preventDefault();
                    if (typeof window.filterCategory === 'function') {
                        window.filterCategory(target.dataset.category || 'all', event);
                    }
                    break;
                case 'filter-tag':
                    event.preventDefault();
                    if (typeof window.filterTag === 'function') {
                        window.filterTag(target.dataset.tagId || '', event);
                    }
                    break;
                case 'clear-cart':
                    event.preventDefault();
                    if (typeof window.clearCart === 'function') window.clearCart();
                    break;
                case 'close-payment-modal':
                    event.preventDefault();
                    if (typeof window.closePaymentModal === 'function') window.closePaymentModal();
                    break;
                case 'close-customer-modal':
                    event.preventDefault();
                    if (typeof window.closeCustomerModal === 'function') window.closeCustomerModal();
                    break;
                case 'close-held-modal':
                    event.preventDefault();
                    if (typeof window.closeHeldModal === 'function') window.closeHeldModal();
                    break;
                case 'close-reports-modal':
                    event.preventDefault();
                    if (typeof window.closeReportsModal === 'function') window.closeReportsModal();
                    break;
                case 'close-receipt-modal':
                    event.preventDefault();
                    if (typeof window.closeReceiptModal === 'function') window.closeReceiptModal();
                    break;
                case 'print-receipt':
                    event.preventDefault();
                    if (typeof window.printReceipt === 'function') window.printReceipt();
                    break;
                case 'process-payment':
                    event.preventDefault();
                    if (typeof window.processPayment === 'function') window.processPayment();
                    break;
                case 'add-split-payment':
                    event.preventDefault();
                    if (typeof window.addSplitPayment === 'function') window.addSplitPayment();
                    break;
                case 'set-cash-received':
                    event.preventDefault();
                    if (typeof window.setCashReceived === 'function') {
                        window.setCashReceived(Number(target.dataset.amount || 0));
                    }
                    break;
                case 'keypad-input':
                    event.preventDefault();
                    if (typeof window.keypadInput === 'function') {
                        window.keypadInput(target.dataset.value || '');
                    }
                    break;
                case 'close-calculator':
                    event.preventDefault();
                    if (typeof window.closeCalculator === 'function') window.closeCalculator();
                    break;
                case 'calc-clear':
                    event.preventDefault();
                    if (typeof window.calcClear === 'function') window.calcClear();
                    break;
                case 'calc-operator':
                    event.preventDefault();
                    if (typeof window.calcOperator === 'function') window.calcOperator(target.dataset.value || '');
                    break;
                case 'calc-input':
                    event.preventDefault();
                    if (typeof window.calcInput === 'function') window.calcInput(target.dataset.value || '');
                    break;
                case 'calc-equals':
                    event.preventDefault();
                    if (typeof window.calcEquals === 'function') window.calcEquals();
                    break;
                case 'calc-copy-result':
                    event.preventDefault();
                    if (typeof window.calcCopyResult === 'function') window.calcCopyResult();
                    break;
                case 'close-price-override':
                    event.preventDefault();
                    if (typeof window.closePriceOverride === 'function') window.closePriceOverride();
                    break;
                case 'confirm-price-override':
                    event.preventDefault();
                    if (typeof window.confirmPriceOverride === 'function') window.confirmPriceOverride();
                    break;
                case 'override-keypad-input':
                    event.preventDefault();
                    if (typeof window.overrideKeypadInput === 'function') window.overrideKeypadInput(target.dataset.value || '');
                    break;
                case 'close-pos-help':
                    event.preventDefault();
                    if (typeof window.closePosHelp === 'function') window.closePosHelp();
                    break;
                case 'toggle-pos-cart':
                    event.preventDefault();
                    if (typeof window.togglePosCart === 'function') window.togglePosCart();
                    break;
                case 'restore-held-sale':
                    event.preventDefault();
                    if (typeof window.restoreHeldSale === 'function') {
                        window.restoreHeldSale(Number(target.dataset.heldId || 0), target.dataset.sale || '');
                    }
                    break;
                case 'remove-split-payment':
                    event.preventDefault();
                    var parent = target.closest('.form-group');
                    if (parent) {
                        parent.remove();
                        if (typeof window.calculateSplitTotal === 'function') window.calculateSplitTotal();
                    }
                    break;
                case 'select-payment-method':
                    event.preventDefault();
                    if (typeof window.selectPaymentMethod === 'function') window.selectPaymentMethod(target.dataset.method);
                    break;
                case 'toggle-details':
                    event.preventDefault();
                    target.classList.toggle('expanded');
                    break;
                case 'product-add':
                    event.preventDefault();
                    if (typeof window.addToCart === 'function') {
                        var productId = Number(target.dataset.productId || 0);
                        var name = target.dataset.productName || '';
                        var price = Number(target.dataset.productPrice || 0);
                        var stock = Number(target.dataset.productStock || 0);
                        var image = target.dataset.productImage || '';
                        window.addToCart(productId, name, price, stock, image);
                    }
                    break;
                case 'cart-update-qty':
                    event.preventDefault();
                    if (typeof window.updateQty === 'function') {
                        var idx = Number(target.dataset.index || -1);
                        var delta = Number(target.dataset.delta || 0);
                        window.updateQty(idx, delta);
                    }
                    break;
                case 'cart-remove':
                    event.preventDefault();
                    if (typeof window.removeFromCart === 'function') {
                        var removeIdx = Number(target.dataset.index || -1);
                        window.removeFromCart(removeIdx);
                    }
                    break;
                case 'cart-override':
                    event.preventDefault();
                    if (typeof window.openPriceOverride === 'function') {
                        var overrideIdx = Number(target.dataset.index || -1);
                        window.openPriceOverride(overrideIdx);
                    }
                    break;
                default:
                    break;
            }
        });

        document.body.addEventListener('input', function (event) {
            if (event.target.matches('[data-action="split-amount-input"]')) {
                if (typeof window.calculateSplitTotal === 'function') window.calculateSplitTotal();
            }
            if (event.target.matches('[data-action="amount-received"]')) {
                if (typeof window.calculateChangeEnhanced === 'function') window.calculateChangeEnhanced();
            }
            if (event.target.matches('[data-action="search-customer"]')) {
                if (typeof window.searchCustomer === 'function') window.searchCustomer();
            }
        });

        var branchSelect = document.getElementById('branchSelect');
        if (branchSelect) {
            branchSelect.addEventListener('change', function () {
                if (typeof window.switchBranch === 'function') {
                    window.switchBranch(this.value);
                }
            });
        }

        var searchBox = document.getElementById('searchBox');
        if (searchBox) {
            searchBox.addEventListener('input', function () {
                if (typeof window.searchProducts === 'function') {
                    window.searchProducts(this.value);
                }
            });
        }

        var prescriptionToggle = document.getElementById('prescriptionToggle');
        if (prescriptionToggle) {
            prescriptionToggle.addEventListener('change', function () {
                if (typeof window.togglePrescription === 'function') {
                    window.togglePrescription(this.checked);
                }
            });
        }

        var wholesaleTier = document.getElementById('wholesaleTier');
        if (wholesaleTier) {
            wholesaleTier.addEventListener('change', function () {
                if (typeof window.setWholesaleTier === 'function') {
                    window.setWholesaleTier(this.value);
                }
            });
        }

        var tableSelect = document.getElementById('tableSelect');
        if (tableSelect) {
            tableSelect.addEventListener('change', function () {
                if (typeof window.setTable === 'function') {
                    window.setTable(this.value);
                }
            });
        }

        var appointmentSelect = document.getElementById('appointmentSelect');
        if (appointmentSelect) {
            appointmentSelect.addEventListener('change', function () {
                if (typeof window.setAppointment === 'function') {
                    window.setAppointment(this.value);
                }
            });
        }

        var staffSelect = document.getElementById('staffSelect');
        if (staffSelect) {
            staffSelect.addEventListener('change', function () {
                if (typeof window.setStaffMember === 'function') {
                    window.setStaffMember(this.value);
                }
            });
        }

        var serialNumber = document.getElementById('serialNumber');
        var warrantyPeriod = document.getElementById('warrantyPeriod');
        [serialNumber, warrantyPeriod].forEach(function (el) {
            if (el) {
                el.addEventListener('change', function () {
                    if (typeof window.setSerialWarranty === 'function') {
                        window.setSerialWarranty();
                    }
                });
            }
        });
    }

    function initPosImageFallbacks() {
        document.body.addEventListener('error', function (event) {
            var img = event.target;
            if (!(img instanceof HTMLImageElement)) return;
            if (img.dataset.posImageFallbackHandled) return;
            img.dataset.posImageFallbackHandled = '1';

            img.style.display = 'none';

            if (img.classList.contains('logo-img')) {
                var parent = img.parentNode;
                if (parent) {
                    parent.classList.remove('has-logo');
                    var initials = parent.querySelector('.brand-initials');
                    if (initials) initials.style.display = 'flex';
                }
                return;
            }

            if (img.classList.contains('product-img')) {
                var fallbackIcon = img.nextElementSibling;
                if (fallbackIcon && fallbackIcon.classList.contains('icon')) {
                    fallbackIcon.style.display = 'flex';
                }
                return;
            }

            if (img.classList.contains('cart-item-thumb')) {
                var placeholder = img.parentNode && img.parentNode.querySelector('.cart-item-thumb-placeholder');
                if (!placeholder && img.parentNode) {
                    placeholder = document.createElement('div');
                    placeholder.className = 'cart-item-thumb cart-item-thumb-placeholder';
                    placeholder.innerHTML = '<i class="fas fa-box"></i>';
                    img.parentNode.insertBefore(placeholder, img.nextSibling);
                }
                return;
            }
        }, true);
    }

    // -------------------------------------------------------
    // Offline Cart Persistence
    // -------------------------------------------------------
    function initOfflineCartPersistence() {
        // Save cart to localStorage periodically
        setInterval(function () {
            if (typeof window.cart !== 'undefined' && Array.isArray(window.cart)) {
                try {
                    localStorage.setItem('pos_cart_backup', JSON.stringify({
                        cart: window.cart,
                        customer: window.currentCustomer || {},
                        orderType: window.currentOrderType || 'walkin',
                        timestamp: new Date().toISOString()
                    }));
                } catch (e) { /* quota exceeded or private browsing */ }
            }
        }, 5000);
    }

})();
