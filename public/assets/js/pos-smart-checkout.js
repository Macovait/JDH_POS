/**
 * Advanced Smart Checkout Module
 * 
 * Features:
 * - AI-powered product recommendations based on customer history
 * - Smart upselling and cross-selling
 * - Customer insights panel
 * - Dynamic pricing suggestions
 * - Cart abandonment prevention
 * - Quick-add bundles
 * 
 * @version 2.0 - Advanced Smart Checkout
 */

(function () {
    'use strict';

    // Smart Checkout Configuration
    const SMART_CHECKOUT_CONFIG = {
        minCartValueForUpsell: 50,
        maxRecommendations: 6,
        bundleDiscountThreshold: 100,
        aiEnabled: true,
        customerInsightCacheTime: 300000, // 5 minutes
    };

    // Customer purchase history cache
    let customerInsightsCache = new Map();
    let currentRecommendations = [];
    let smartBundles = [];

    // ============================================
    // INITIALIZATION
    // ============================================
    document.addEventListener('DOMContentLoaded', function () {
        if (typeof CONFIG === 'undefined') {
            console.warn('Smart Checkout: CONFIG not available');
            return;
        }
        initSmartCheckout();
    });

    function initSmartCheckout() {
        // Safety check - ensure window.cart exists
        if (typeof window.cart === 'undefined') {
            console.warn('[Smart Checkout] window.cart not available - waiting...');
            // Retry after short delay
            setTimeout(initSmartCheckout, 500);
            return;
        }
        
        try {
            initSmartRecommendations();
            initCustomerInsightsPanel();
            initCartAbandonmentPrevention();
            initQuickBundles();
            initDynamicPricing();
            console.info('[Smart Checkout] Advanced features initialized');
        } catch (err) {
            console.error('[Smart Checkout] Initialization error:', err);
        }
    }

    // ============================================
    // AI-POWERED RECOMMENDATIONS
    // ============================================
    function initSmartRecommendations() {
        // Add recommendations panel to POS
        addRecommendationsPanel();
        
        // Listen for cart changes
        document.addEventListener('cart:updated', onCartUpdated);
        document.addEventListener('customer:selected', onCustomerSelected);
    }

    function addRecommendationsPanel() {
        const sidebar = document.querySelector('.cart-section');
        if (!sidebar) return;

        const panel = document.createElement('div');
        panel.id = 'smartRecommendationsPanel';
        panel.className = 'hidden mt-4 p-3 bg-gradient-to-br from-slate-800/80 to-slate-900/80 border border-amber-500/30 rounded-xl';
        panel.innerHTML = `
            <div class="flex items-center justify-between mb-3">
                <h4 class="text-amber-400 text-xs font-semibold uppercase tracking-wider">
                    <i class="fas fa-magic mr-1"></i>Smart Suggestions
                </h4>
                <span id="recLoading" class="text-[10px] text-slate-500 hidden">
                    <i class="fas fa-spinner fa-spin mr-1"></i>Analyzing...
                </span>
            </div>
            <div id="recommendationsList" class="grid grid-cols-2 gap-2">
                <!-- Dynamic recommendations -->
            </div>
            <div id="bundleSuggestion" class="hidden mt-3 p-2 bg-amber-500/10 border border-amber-500/20 rounded-lg">
                <div class="flex items-center gap-2">
                    <i class="fas fa-box-open text-amber-400"></i>
                    <div class="flex-1">
                        <p class="text-white text-xs font-medium" id="bundleTitle">Bundle Offer</p>
                        <p class="text-amber-400 text-[10px]" id="bundleDiscount">Save 15%</p>
                    </div>
                    <button onclick="SmartCheckout.addBundle()" class="px-2 py-1 bg-amber-500 text-slate-900 rounded text-xs font-semibold hover:bg-amber-400">
                        Add
                    </button>
                </div>
            </div>
        `;

        // Insert before cart items
        const cartContainer = sidebar.querySelector('#cartItems');
        if (cartContainer) {
            cartContainer.parentNode.insertBefore(panel, cartContainer);
        }
    }

    async function onCartUpdated(e) {
        const cart = e.detail?.cart || window.cart || [];
        const cartValue = cart.reduce((sum, item) => sum + (item.price * (item.quantity || item.qty || 1)), 0);

        // Show/hide recommendations based on cart value
        const panel = document.getElementById('smartRecommendationsPanel');
        if (!panel) return;

        if (cart.length > 0) {
            panel.classList.remove('hidden');
            await generateRecommendations(cart);
            
            // Check for bundle opportunities
            if (cartValue >= SMART_CHECKOUT_CONFIG.bundleDiscountThreshold) {
                suggestBundle(cart);
            }
        } else {
            panel.classList.add('hidden');
        }
    }

    async function generateRecommendations(cart) {
        const loading = document.getElementById('recLoading');
        const list = document.getElementById('recommendationsList');
        
        if (loading) loading.classList.remove('hidden');

        // Get product IDs in cart
        const cartProductIds = cart.map(item => item.id);
        
        // Fetch AI recommendations
        try {
            const response = await fetch(CONFIG.ajaxUrl + 'get_smart_recommendations.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    cart_products: cartProductIds,
                    customer_id: selectedCustomer?.id || null,
                    branch_id: CONFIG.branchId,
                    company_id: CONFIG.companyId,
                    limit: SMART_CHECKOUT_CONFIG.maxRecommendations
                })
            });

            const data = await response.json();
            
            if (data.success && data.recommendations) {
                currentRecommendations = data.recommendations;
                renderRecommendations(data.recommendations);
            }
        } catch (err) {
            console.error('[Smart Checkout] Failed to load recommendations:', err);
        } finally {
            if (loading) loading.classList.add('hidden');
        }
    }

    function renderRecommendations(recommendations) {
        const list = document.getElementById('recommendationsList');
        if (!list) return;

        list.innerHTML = recommendations.map(rec => `
            <div class="p-2 bg-slate-800/50 border border-slate-700 rounded-lg hover:border-amber-500/50 transition-all cursor-pointer group"
                 onclick="SmartCheckout.addRecommendedProduct(${rec.id}, '${rec.name.replace(/'/g, "\\'")}', ${rec.price}, ${rec.stock})">
                <div class="flex items-center gap-2">
                    ${rec.image ? `<img src="${rec.image}" class="w-8 h-8 rounded object-cover">` : 
                      `<div class="w-8 h-8 rounded bg-slate-700 flex items-center justify-center"><i class="fas fa-box text-slate-500 text-xs"></i></div>`}
                    <div class="flex-1 min-w-0">
                        <p class="text-white text-xs truncate group-hover:text-amber-400 transition-colors">${rec.name}</p>
                        <p class="text-emerald-400 text-[10px] font-mono">${CONFIG.currency} ${rec.price.toFixed(2)}</p>
                    </div>
                </div>
                ${rec.reason ? `<p class="text-[9px] text-slate-500 mt-1"><i class="fas fa-lightbulb mr-1 text-amber-500"></i>${rec.reason}</p>` : ''}
            </div>
        `).join('');
    }

    // ============================================
    // CUSTOMER INSIGHTS PANEL
    // ============================================
    function initCustomerInsightsPanel() {
        // Add insights to customer modal
        const customerModal = document.getElementById('customerModal');
        if (!customerModal) return;

        const insightsDiv = document.createElement('div');
        insightsDiv.id = 'customerInsights';
        insightsDiv.className = 'hidden mt-4 p-3 bg-slate-800/50 border border-slate-700 rounded-lg';
        insightsDiv.innerHTML = `
            <h4 class="text-amber-400 text-xs font-semibold mb-3">
                <i class="fas fa-chart-line mr-1"></i>Customer Insights
            </h4>
            <div class="grid grid-cols-2 gap-3 mb-3">
                <div class="p-2 bg-slate-900/50 rounded">
                    <p class="text-slate-500 text-[10px] uppercase">Avg. Order</p>
                    <p class="text-white text-sm font-mono" id="insightAvgOrder">-</p>
                </div>
                <div class="p-2 bg-slate-900/50 rounded">
                    <p class="text-slate-500 text-[10px] uppercase">Visit Frequency</p>
                    <p class="text-white text-sm font-mono" id="insightFrequency">-</p>
                </div>
                <div class="p-2 bg-slate-900/50 rounded">
                    <p class="text-slate-500 text-[10px] uppercase">Favorite Category</p>
                    <p class="text-white text-sm" id="insightCategory">-</p>
                </div>
                <div class="p-2 bg-slate-900/50 rounded">
                    <p class="text-slate-500 text-[10px] uppercase">Last Visit</p>
                    <p class="text-white text-sm" id="insightLastVisit">-</p>
                </div>
            </div>
            <div class="border-t border-slate-700 pt-2">
                <p class="text-slate-500 text-[10px] mb-2">Recommended for this customer:</p>
                <div id="customerPersonalRecs" class="flex flex-wrap gap-1">
                    <!-- Personal recommendations -->
                </div>
            </div>
        `;

        const infoSection = customerModal.querySelector('#customerInfo');
        if (infoSection) {
            infoSection.parentNode.insertBefore(insightsDiv, infoSection.nextSibling);
        }
    }

    async function onCustomerSelected(e) {
        const customer = e.detail?.customer;
        if (!customer || !customer.id) return;

        // Load customer insights
        await loadCustomerInsights(customer.id);
    }

    async function loadCustomerInsights(customerId) {
        const cacheKey = `insights_${customerId}`;
        const cached = customerInsightsCache.get(cacheKey);
        
        if (cached && (Date.now() - cached.timestamp) < SMART_CHECKOUT_CONFIG.customerInsightCacheTime) {
            renderCustomerInsights(cached.data);
            return;
        }

        try {
            const response = await fetch(CONFIG.ajaxUrl + 'get_customer_insights.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    customer_id: customerId,
                    company_id: CONFIG.companyId,
                    branch_id: CONFIG.branchId
                })
            });

            const data = await response.json();
            
            if (data.success) {
                customerInsightsCache.set(cacheKey, {
                    data: data.insights,
                    timestamp: Date.now()
                });
                renderCustomerInsights(data.insights);
            }
        } catch (err) {
            console.error('[Smart Checkout] Failed to load insights:', err);
        }
    }

    function renderCustomerInsights(insights) {
        const panel = document.getElementById('customerInsights');
        if (!panel) return;

        panel.classList.remove('hidden');

        document.getElementById('insightAvgOrder').textContent = 
            insights.avg_order_value ? `${CONFIG.currency} ${insights.avg_order_value.toFixed(2)}` : '-';
        document.getElementById('insightFrequency').textContent = 
            insights.visit_frequency || '-';
        document.getElementById('insightCategory').textContent = 
            insights.favorite_category || '-';
        document.getElementById('insightLastVisit').textContent = 
            insights.last_visit ? formatRelativeTime(insights.last_visit) : '-';

        // Personal recommendations
        const recsContainer = document.getElementById('customerPersonalRecs');
        if (recsContainer && insights.recommended_products) {
            recsContainer.innerHTML = insights.recommended_products.map(p => `
                <button onclick="SmartCheckout.addRecommendedProduct(${p.id}, '${p.name.replace(/'/g, "\\'")}', ${p.price}, ${p.stock})"
                        class="px-2 py-1 bg-slate-700 hover:bg-amber-500/20 text-white hover:text-amber-400 text-[10px] rounded transition-colors">
                    ${p.name}
                </button>
            `).join('');
        }
    }

    // ============================================
    // QUICK BUNDLES
    // ============================================
    function initQuickBundles() {
        // Load available bundles for this company
        loadSmartBundles();
    }

    async function loadSmartBundles() {
        try {
            const response = await fetch(CONFIG.ajaxUrl + 'get_product_bundles.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    company_id: CONFIG.companyId,
                    branch_id: CONFIG.branchId
                })
            });

            const data = await response.json();
            if (data.success && data.bundles) {
                smartBundles = data.bundles;
            }
        } catch (err) {
            console.error('[Smart Checkout] Failed to load bundles:', err);
        }
    }

    function suggestBundle(cart) {
        const bundleDiv = document.getElementById('bundleSuggestion');
        const title = document.getElementById('bundleTitle');
        const discount = document.getElementById('bundleDiscount');
        
        if (!bundleDiv || smartBundles.length === 0) return;

        // Find matching bundle based on cart contents
        const cartIds = cart.map(i => i.id);
        const matchingBundle = smartBundles.find(b => {
            const hasTrigger = b.trigger_products.some(tp => cartIds.includes(tp));
            return hasTrigger;
        });

        if (matchingBundle) {
            bundleDiv.dataset.bundleId = matchingBundle.id;
            title.textContent = matchingBundle.name;
            discount.textContent = `Save ${matchingBundle.discount_percent}%`;
            bundleDiv.classList.remove('hidden');
        }
    }

    // ============================================
    // CART ABANDONMENT PREVENTION
    // ============================================
    function initCartAbandonmentPrevention() {
        let lastActivity = Date.now();
        let reminderShown = false;

        // Track activity
        document.addEventListener('click', () => lastActivity = Date.now());
        document.addEventListener('keypress', () => lastActivity = Date.now());

        // Check for abandoned cart every 30 seconds
        setInterval(() => {
            const cart = window.cart || [];
            const inactive = Date.now() - lastActivity;
            
            if (cart.length > 0 && inactive > 120000 && !reminderShown) {
                showCartReminder(cart);
                reminderShown = true;
            }
        }, 30000);
    }

    function showCartReminder(cart) {
        const itemCount = cart.reduce((sum, item) => sum + item.quantity, 0);
        const total = cart.reduce((sum, item) => sum + (item.price * item.quantity), 0);

        if (typeof showToast === 'function') {
            showToast(
                `<div class="flex items-center gap-2">
                    <i class="fas fa-shopping-cart text-amber-400"></i>
                    <div>
                        <p class="font-semibold">You have ${itemCount} items in cart</p>
                        <p class="text-xs">Total: ${CONFIG.currency} ${total.toFixed(2)}</p>
                    </div>
                </div>`,
                'info',
                10000
            );
        }
    }

    // ============================================
    // DYNAMIC PRICING
    // ============================================
    function initDynamicPricing() {
        // Apply dynamic pricing rules based on:
        // - Time of day (happy hours)
        // - Cart value (volume discounts)
        // - Customer tier
        // - Stock levels (clearance)
    }

    // ============================================
    // PUBLIC API
    // ============================================
    window.SmartCheckout = {
        addRecommendedProduct: function(id, name, price, stock) {
            if (typeof window.addToCart === 'function') {
                window.addToCart(id, name, price, stock);
                
                // Track that recommendation was clicked
                trackRecommendationClick(id);
            }
        },

        addBundle: function() {
            const bundleDiv = document.getElementById('bundleSuggestion');
            const bundleId = bundleDiv?.dataset.bundleId;
            
            if (!bundleId) return;

            const bundle = smartBundles.find(b => b.id == bundleId);
            if (!bundle) return;

            // Add all bundle products to cart
            bundle.products.forEach(p => {
                if (typeof window.addToCart === 'function') {
                    window.addToCart(p.id, p.name, p.price * (1 - bundle.discount_percent/100), p.stock);
                }
            });

            showToast(`Bundle "${bundle.name}" added with ${bundle.discount_percent}% discount!`, 'success');
            bundleDiv.classList.add('hidden');
        },

        refreshRecommendations: function() {
            const cart = window.cart || [];
            if (cart.length > 0) {
                generateRecommendations(cart);
            }
        },

        getCustomerInsights: function(customerId) {
            return customerInsightsCache.get(`insights_${customerId}`)?.data || null;
        }
    };

    // ============================================
    // HELPERS
    // ============================================
    function trackRecommendationClick(productId) {
        fetch(CONFIG.ajaxUrl + 'track_recommendation.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                product_id: productId,
                customer_id: selectedCustomer?.id,
                company_id: CONFIG.companyId,
                timestamp: new Date().toISOString()
            })
        }).catch(() => {});
    }

    function formatRelativeTime(dateString) {
        const date = new Date(dateString);
        const now = new Date();
        const diff = Math.floor((now - date) / 1000);

        if (diff < 60) return 'Just now';
        if (diff < 3600) return `${Math.floor(diff/60)} min ago`;
        if (diff < 86400) return `${Math.floor(diff/3600)} hours ago`;
        if (diff < 604800) return `${Math.floor(diff/86400)} days ago`;
        return date.toLocaleDateString();
    }

    // Expose config for debugging
    window.SmartCheckoutConfig = SMART_CHECKOUT_CONFIG;

})();
