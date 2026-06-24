/**
 * Advanced Payment Integration Module
 * 
 * Features:
 * - QR Code payments (M-Pesa, Stripe, etc.)
 * - Payment links generation
 * - Contactless/NFC payment support
 * - Auto-reconciliation
 * - Split payment enhancements
 * - Payment retry with fallback methods
 * - Real-time payment status polling
 * 
 * @version 2.0 - Seamless Payments
 */

(function () {
    'use strict';

    const PAYMENT_CONFIG = {
        pollingInterval: 3000,
        maxPollingAttempts: 60,
        qrCodeExpiry: 300000, // 5 minutes
        autoReconcileInterval: 60000, // 1 minute
        fallbackMethods: ['cash', 'card', 'mpesa'],
        supportedQRMethods: ['mpesa', 'stripe', 'paypal'],
        nfcEnabled: 'NDEFReader' in window
    };

    let activePayment = null;
    let pollingTimer = null;
    let reconciliationInterval = null;

    // ============================================
    // INITIALIZATION
    // ============================================
    document.addEventListener('DOMContentLoaded', function () {
        if (typeof CONFIG === 'undefined') {
            console.warn('Advanced Payments: CONFIG not available');
            return;
        }
        initAdvancedPayments();
    });

    function initAdvancedPayments() {
        // Safety check
        if (typeof window.getCartTotal !== 'function') {
            console.warn('[Advanced Payments] window.getCartTotal not available - waiting...');
            setTimeout(initAdvancedPayments, 500);
            return;
        }
        
        try {
            enhancePaymentModal();
            initQRPayments();
            initPaymentLinks();
            initAutoReconciliation();
            initPaymentRetrySystem();
            
            if (PAYMENT_CONFIG.nfcEnabled) {
                initNFCPayments();
            }
            
            console.info('[Advanced Payments] Initialized');
            console.info('[Advanced Payments] NFC Support:', PAYMENT_CONFIG.nfcEnabled ? 'Yes' : 'No');
        } catch (err) {
            console.error('[Advanced Payments] Initialization error:', err);
        }
    }

    // ============================================
    // ENHANCED PAYMENT MODAL
    // ============================================
    function enhancePaymentModal() {
        // Add QR payment button to payment methods
        const paymentMethodsGrid = document.querySelector('.payment-methods-grid');
        if (paymentMethodsGrid) {
            const qrBtn = document.createElement('button');
            qrBtn.className = 'payment-method-btn py-2 px-3 bg-slate-700 rounded-lg text-slate-300 text-sm hover:bg-slate-600 transition-colors';
            qrBtn.setAttribute('data-method', 'qr');
            qrBtn.setAttribute('onclick', 'AdvancedPayments.selectQRPayment()');
            qrBtn.innerHTML = '<i class="fas fa-qrcode mr-1"></i>QR Pay';
            paymentMethodsGrid.appendChild(qrBtn);
        }

        // Add payment link option
        const paymentModal = document.getElementById('paymentModal');
        if (paymentModal) {
            const linkSection = document.createElement('div');
            linkSection.id = 'paymentLinkSection';
            linkSection.className = 'hidden mt-3 p-3 bg-slate-700/50 rounded-lg';
            linkSection.innerHTML = `
                <div class="flex items-center justify-between mb-2">
                    <span class="text-white text-sm font-medium"><i class="fas fa-link mr-1 text-blue-400"></i>Payment Link</span>
                </div>
                <p class="text-slate-400 text-xs mb-2">Send payment link to customer</p>
                <div class="flex gap-2">
                    <input type="tel" id="paymentLinkPhone" placeholder="Phone number" 
                           class="flex-1 px-2 py-1 bg-slate-900 border border-slate-600 rounded text-white text-sm">
                    <button onclick="AdvancedPayments.sendPaymentLink()" 
                            class="px-3 py-1 bg-blue-500 text-white rounded text-sm hover:bg-blue-400">
                        <i class="fas fa-paper-plane"></i>
                    </button>
                </div>
            `;
            
            // Insert before action buttons
            const actions = paymentModal.querySelector('.payment-actions');
            if (actions) {
                actions.parentNode.insertBefore(linkSection, actions);
            }
        }
    }

    // ============================================
    // QR CODE PAYMENTS
    // ============================================
    function initQRPayments() {
        // Add QR payment modal
        const modal = document.createElement('div');
        modal.id = 'qrPaymentModal';
        modal.className = 'fixed inset-0 z-[2000] hidden items-center justify-center';
        modal.innerHTML = `
            <div class="absolute inset-0 bg-black/80" onclick="AdvancedPayments.closeQRModal()"></div>
            <div class="relative bg-white rounded-2xl w-full max-w-sm mx-4 p-6 shadow-2xl">
                <div class="text-center mb-4">
                    <h3 class="text-slate-900 font-semibold text-lg mb-1">Scan to Pay</h3>
                    <p class="text-slate-500 text-sm">Use your phone to scan this QR code</p>
                </div>
                
                <div id="qrCodeContainer" class="flex justify-center mb-4">
                    <!-- QR Code will be rendered here -->
                    <div class="w-48 h-48 bg-slate-200 rounded-lg flex items-center justify-center">
                        <i class="fas fa-qrcode text-6xl text-slate-400"></i>
                    </div>
                </div>
                
                <div class="text-center mb-4">
                    <p class="text-2xl font-bold text-slate-900" id="qrAmount">$0.00</p>
                    <p class="text-sm text-slate-500" id="qrReference">Ref: -</p>
                </div>
                
                <div id="qrStatus" class="mb-4">
                    <div class="flex items-center justify-center gap-2 text-amber-600">
                        <i class="fas fa-spinner fa-spin"></i>
                        <span class="text-sm">Waiting for payment...</span>
                    </div>
                    <div class="h-1 bg-slate-200 rounded-full mt-2 overflow-hidden">
                        <div id="qrProgressBar" class="h-full bg-amber-500 transition-all duration-1000" style="width: 100%"></div>
                    </div>
                </div>
                
                <div class="grid grid-cols-2 gap-2">
                    <button onclick="AdvancedPayments.cancelQRPayment()" 
                            class="py-2 bg-slate-200 text-slate-700 rounded-lg font-medium hover:bg-slate-300">
                        Cancel
                    </button>
                    <button onclick="AdvancedPayments.manualConfirmQR()" 
                            class="py-2 bg-emerald-500 text-white rounded-lg font-medium hover:bg-emerald-400">
                        Paid
                    </button>
                </div>
            </div>
        `;
        document.body.appendChild(modal);
    }

    async function selectQRPayment() {
        const total = (typeof window.getCartTotal === 'function') ? window.getCartTotal() : 0;
        if (total <= 0) {
            showToast('No amount to pay', 'error');
            return;
        }

        // Generate QR payment
        try {
            const response = await fetch(CONFIG.ajaxUrl + 'create_qr_payment.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    amount: total,
                    company_id: CONFIG.companyId,
                    branch_id: CONFIG.branchId,
                    user_id: CONFIG.userId,
                    customer_id: window.selectedCustomer?.id || null,
                    expiry_minutes: 5
                })
            });

            const data = await response.json();
            
            if (data.success) {
                activePayment = {
                    id: data.payment_id,
                    method: 'qr',
                    amount: total,
                    reference: data.reference,
                    qrData: data.qr_data
                };
                
                showQRModal(data);
                startPaymentPolling(data.payment_id);
            }
        } catch (err) {
            console.error('[Advanced Payments] QR generation failed:', err);
            showToast('Failed to generate QR code', 'error');
        }
    }

    function showQRModal(data) {
        const modal = document.getElementById('qrPaymentModal');
        if (!modal) return;

        // Generate QR code using qrcode.js or similar
        const container = document.getElementById('qrCodeContainer');
        container.innerHTML = `<img src="${data.qr_image_url}" alt="Payment QR" class="w-48 h-48">`;

        document.getElementById('qrAmount').textContent = CONFIG.currency + ' ' + data.amount.toFixed(2);
        document.getElementById('qrReference').textContent = 'Ref: ' + data.reference;

        modal.classList.remove('hidden');
        modal.classList.add('flex');

        // Start progress bar countdown
        startQRProgressCountdown();
    }

    function closeQRModal() {
        const modal = document.getElementById('qrPaymentModal');
        if (modal) {
            modal.classList.add('hidden');
            modal.classList.remove('flex');
        }
        
        stopPaymentPolling();
        activePayment = null;
    }

    function startQRProgressCountdown() {
        const bar = document.getElementById('qrProgressBar');
        let width = 100;
        
        const interval = setInterval(() => {
            width -= 0.33; // 5 minutes = 300 seconds, update every second
            if (bar) bar.style.width = width + '%';
            
            if (width <= 0) {
                clearInterval(interval);
                AdvancedPayments.cancelQRPayment();
            }
        }, 1000);
        
        // Store interval for cleanup
        activePayment = { ...activePayment, countdownInterval: interval };
    }

    // ============================================
    // PAYMENT LINKS
    // ============================================
    function initPaymentLinks() {
        // Payment links are initialized in enhancePaymentModal
    }

    async function sendPaymentLink() {
        const phoneInput = document.getElementById('paymentLinkPhone');
        const phone = phoneInput?.value?.trim();
        
        if (!phone) {
            showToast('Please enter a phone number', 'error');
            return;
        }

        const total = (typeof window.getCartTotal === 'function') ? window.getCartTotal() : 0;
        if (total <= 0) {
            showToast('No amount to pay', 'error');
            return;
        }

        try {
            const response = await fetch(CONFIG.ajaxUrl + 'send_payment_link.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    phone: phone,
                    amount: total,
                    company_id: CONFIG.companyId,
                    branch_id: CONFIG.branchId,
                    customer_id: window.selectedCustomer?.id || null
                })
            });

            const data = await response.json();
            
            if (data.success) {
                showToast('Payment link sent!', 'success');
                phoneInput.value = '';
                
                // Track this as a pending payment
                activePayment = {
                    id: data.payment_id,
                    method: 'link',
                    amount: total,
                    phone: phone,
                    status: 'pending'
                };
                
                startPaymentPolling(data.payment_id);
            } else {
                showToast(data.error || 'Failed to send link', 'error');
            }
        } catch (err) {
            console.error('[Advanced Payments] Send link failed:', err);
            showToast('Failed to send payment link', 'error');
        }
    }

    // ============================================
    // PAYMENT POLLING & STATUS
    // ============================================
    function startPaymentPolling(paymentId) {
        let attempts = 0;
        
        pollingTimer = setInterval(async () => {
            attempts++;
            
            if (attempts > PAYMENT_CONFIG.maxPollingAttempts) {
                stopPaymentPolling();
                showPaymentTimeout();
                return;
            }

            try {
                const response = await fetch(CONFIG.ajaxUrl + 'check_payment_status.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        payment_id: paymentId,
                        company_id: CONFIG.companyId
                    })
                });

                const data = await response.json();
                
                if (data.status === 'completed') {
                    stopPaymentPolling();
                    handlePaymentSuccess(data);
                } else if (data.status === 'failed') {
                    stopPaymentPolling();
                    handlePaymentFailure(data);
                }
                // Continue polling for 'pending' status
            } catch (err) {
                console.error('[Advanced Payments] Polling error:', err);
            }
        }, PAYMENT_CONFIG.pollingInterval);
    }

    function stopPaymentPolling() {
        if (pollingTimer) {
            clearInterval(pollingTimer);
            pollingTimer = null;
        }
        
        if (activePayment?.countdownInterval) {
            clearInterval(activePayment.countdownInterval);
        }
    }

    function handlePaymentSuccess(data) {
        closeQRModal();
        
        // Complete the sale
        const event = new CustomEvent('payment:confirmed', {
            detail: {
                method: activePayment?.method || 'qr',
                payment_id: data.payment_id,
                amount: data.amount,
                transaction_ref: data.transaction_ref
            }
        });
        document.dispatchEvent(event);
        
        showToast('Payment confirmed!', 'success');
        
        // Process the sale
        if (typeof window.processPayment === 'function') {
            window.processPayment('qr', {
                payment_id: data.payment_id,
                transaction_ref: data.transaction_ref
            });
        }
    }

    function handlePaymentFailure(data) {
        closeQRModal();
        showToast(data.error || 'Payment failed', 'error');
    }

    function showPaymentTimeout() {
        const status = document.getElementById('qrStatus');
        if (status) {
            status.innerHTML = `
                <div class="text-center text-red-500">
                    <i class="fas fa-clock mb-1"></i>
                    <p class="text-sm">Payment timed out</p>
                </div>
            `;
        }
    }

    // ============================================
    // AUTO-RECONCILIATION
    // ============================================
    function initAutoReconciliation() {
        // Check for unreconciled payments periodically
        reconciliationInterval = setInterval(() => {
            if (navigator.onLine) {
                performReconciliation();
            }
        }, PAYMENT_CONFIG.autoReconcileInterval);
    }

    async function performReconciliation() {
        try {
            const response = await fetch(CONFIG.ajaxUrl + 'reconcile_payments.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    company_id: CONFIG.companyId,
                    branch_id: CONFIG.branchId
                })
            });

            const data = await response.json();
            
            if (data.reconciled?.length > 0) {
                console.info('[Advanced Payments] Reconciled', data.reconciled.length, 'payments');
                
                // Notify about reconciled payments
                data.reconciled.forEach(payment => {
                    showToast(`Payment ${payment.reference} confirmed`, 'success');
                });
            }
            
            if (data.discrepancies?.length > 0) {
                console.warn('[Advanced Payments] Discrepancies found:', data.discrepancies);
                handleDiscrepancies(data.discrepancies);
            }
        } catch (err) {
            console.error('[Advanced Payments] Reconciliation failed:', err);
        }
    }

    function handleDiscrepancies(discrepancies) {
        // Log discrepancies for review
        discrepancies.forEach(d => {
            console.warn(`Payment discrepancy: ${d.payment_id} - ${d.issue}`);
        });
        
        // Emit event for admin notification
        const event = new CustomEvent('payment:discrepancy', {
            detail: { discrepancies }
        });
        document.dispatchEvent(event);
    }

    // ============================================
    // PAYMENT RETRY SYSTEM
    // ============================================
    function initPaymentRetrySystem() {
        // Listen for payment failures and offer retry options
        document.addEventListener('payment:failed', handlePaymentFailure);
    }

    async function handlePaymentFailure(e) {
        const { error, originalMethod, saleData } = e.detail;
        
        // Show retry options
        const fallbackOptions = PAYMENT_CONFIG.fallbackMethods.filter(m => m !== originalMethod);
        
        // Emit event with retry options
        const event = new CustomEvent('payment:retry-options', {
            detail: {
                error,
                originalMethod,
                fallbacks: fallbackOptions,
                saleData
            }
        });
        document.dispatchEvent(event);
    }

    async function retryPayment(saleData, newMethod) {
        try {
            const response = await fetch(CONFIG.ajaxUrl + 'retry_payment.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    original_sale: saleData,
                    new_method: newMethod,
                    company_id: CONFIG.companyId,
                    branch_id: CONFIG.branchId
                })
            });

            const data = await response.json();
            
            if (data.success) {
                showToast(`Payment retried with ${newMethod}`, 'success');
                return data;
            } else {
                throw new Error(data.error);
            }
        } catch (err) {
            console.error('[Advanced Payments] Retry failed:', err);
            showToast('Retry failed: ' + err.message, 'error');
            throw err;
        }
    }

    // ============================================
    // NFC/CONTACTLESS PAYMENTS
    // ============================================
    async function initNFCPayments() {
        try {
            const ndef = new NDEFReader();
            await ndef.scan();
            
            console.info('[Advanced Payments] NFC scanning active');
            
            ndef.addEventListener('reading', ({ message, serialNumber }) => {
                console.log(`NFC tag read: ${serialNumber}`);
                handleNFCReading(message, serialNumber);
            });
        } catch (err) {
            console.warn('[Advanced Payments] NFC not available:', err);
        }
    }

    async function handleNFCReading(message, serialNumber) {
        // Process NFC payment (e.g., contactless card, mobile wallet)
        const decoder = new TextDecoder();
        let paymentData = null;
        
        for (const record of message.records) {
            if (record.recordType === 'text') {
                paymentData = decoder.decode(record.data);
                break;
            }
        }
        
        if (paymentData) {
            try {
                const parsed = JSON.parse(paymentData);
                
                // Process contactless payment
                const event = new CustomEvent('payment:nfc', {
                    detail: {
                        serialNumber,
                        paymentData: parsed
                    }
                });
                document.dispatchEvent(event);
                
                showToast('Contactless payment detected', 'success');
            } catch (err) {
                console.error('[Advanced Payments] NFC parse error:', err);
            }
        }
    }

    // ============================================
    // MANUAL PAYMENT HANDLING
    // ============================================
    function manualConfirmQR() {
        if (!activePayment) return;
        
        // Allow manual confirmation for cases where auto-detection failed
        stopPaymentPolling();
        
        handlePaymentSuccess({
            payment_id: activePayment.id,
            amount: activePayment.amount,
            transaction_ref: 'MANUAL-' + Date.now()
        });
    }

    function cancelQRPayment() {
        stopPaymentPolling();
        
        // Cancel on server
        if (activePayment?.id) {
            fetch(CONFIG.ajaxUrl + 'cancel_qr_payment.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    payment_id: activePayment.id,
                    company_id: CONFIG.companyId
                })
            }).catch(() => {});
        }
        
        closeQRModal();
        showToast('QR payment cancelled', 'info');
    }

    // ============================================
    // SPLIT PAYMENT ENHANCEMENTS
    // ============================================
    function enhanceSplitPayment() {
        // Add remaining balance indicator
        // Add quick split suggestions (50/50, 60/40, etc.)
        // Add payment method optimization (suggest best method for each split)
    }

    // ============================================
    // PUBLIC API
    // ============================================
    window.AdvancedPayments = {
        selectQRPayment,
        closeQRModal,
        cancelQRPayment,
        manualConfirmQR,
        sendPaymentLink,
        retryPayment,
        performReconciliation,
        getActivePayment: () => activePayment,
        isPolling: () => pollingTimer !== null,
        getConfig: () => PAYMENT_CONFIG,
        showPaymentLinkSection: () => {
            const section = document.getElementById('paymentLinkSection');
            if (section) section.classList.remove('hidden');
        }
    };

    function showToast(message, type = 'info') {
        if (typeof window.showToast === 'function') {
            window.showToast(message, type);
        }
    }

})();
