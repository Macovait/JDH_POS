/**
 * Advanced Loyalty Management Module
 * 
 * Features:
 * - Tiered loyalty levels (Bronze, Silver, Gold, Platinum)
 * - Personalized rewards based on customer preferences
 * - Referral tracking and bonuses
 * - Points multipliers for special events
 * - Birthday bonuses
 * - VIP early access
 * - Loyalty analytics dashboard
 * 
 * @version 2.0 - Advanced Loyalty
 */

(function () {
    'use strict';

    const LOYALTY_CONFIG = {
        tiers: {
            bronze: { name: 'Bronze', minPoints: 0, multiplier: 1, discount: 0, color: '#CD7F32' },
            silver: { name: 'Silver', minPoints: 500, multiplier: 1.25, discount: 5, color: '#C0C0C0' },
            gold: { name: 'Gold', minPoints: 2000, multiplier: 1.5, discount: 10, color: '#FFD700' },
            platinum: { name: 'Platinum', minPoints: 5000, multiplier: 2, discount: 15, color: '#E5E4E2' }
        },
        pointsPerCurrency: 1,
        currencyPerPoint: 0.01,
        referralBonus: 100,
        birthdayBonus: 200,
        vipEarlyAccess: true,
        specialEventMultipliers: {
            'double_points_day': 2,
            'weekend_special': 1.5,
            'holiday_bonus': 3
        }
    };

    let currentCustomerLoyalty = null;
    let activeMultiplier = 1;

    // ============================================
    // INITIALIZATION
    // ============================================
    document.addEventListener('DOMContentLoaded', function () {
        if (typeof CONFIG === 'undefined') {
            console.warn('Advanced Loyalty: CONFIG not available');
            return;
        }
        initAdvancedLoyalty();
    });

    function initAdvancedLoyalty() {
        // Safety check
        if (typeof window.selectedCustomer === 'undefined') {
            console.warn('[Advanced Loyalty] window.selectedCustomer not available - waiting...');
            setTimeout(initAdvancedLoyalty, 500);
            return;
        }
        
        try {
            initLoyaltyPanel();
            initReferralSystem();
            initTierProgressUI();
            initPersonalizedRewards();
            
            // Listen for customer selection
            document.addEventListener('customer:selected', onCustomerSelected);
            document.addEventListener('sale:completed', onSaleCompleted);
            
            // Check for special events
            checkSpecialEvents();
            
            console.info('[Advanced Loyalty] Initialized');
        } catch (err) {
            console.error('[Advanced Loyalty] Initialization error:', err);
        }
    }

    // ============================================
    // LOYALTY TIER SYSTEM
    // ============================================
    function calculateTier(totalPoints) {
        const tiers = Object.entries(LOYALTY_CONFIG.tiers).reverse();
        for (const [tierKey, tierData] of tiers) {
            if (totalPoints >= tierData.minPoints) {
                return { key: tierKey, ...tierData };
            }
        }
        return { key: 'bronze', ...LOYALTY_CONFIG.tiers.bronze };
    }

    function getNextTier(currentTier) {
        const tierOrder = ['bronze', 'silver', 'gold', 'platinum'];
        const currentIndex = tierOrder.indexOf(currentTier);
        const nextTierKey = tierOrder[currentIndex + 1];
        
        if (nextTierKey) {
            return { key: nextTierKey, ...LOYALTY_CONFIG.tiers[nextTierKey] };
        }
        return null;
    }

    function calculateTierProgress(totalPoints, currentTier) {
        const nextTier = getNextTier(currentTier);
        if (!nextTier) return { percent: 100, pointsNeeded: 0, nextTier: null };
        
        const pointsInCurrentTier = totalPoints - LOYALTY_CONFIG.tiers[currentTier].minPoints;
        const pointsNeededForNext = nextTier.minPoints - LOYALTY_CONFIG.tiers[currentTier].minPoints;
        const percent = Math.min(100, (pointsInCurrentTier / pointsNeededForNext) * 100);
        const pointsNeeded = nextTier.minPoints - totalPoints;
        
        return { percent, pointsNeeded, nextTier };
    }

    // ============================================
    // LOYALTY PANEL UI
    // ============================================
    function initLoyaltyPanel() {
        const sidebar = document.querySelector('.cart-section');
        if (!sidebar) return;

        // Enhanced loyalty panel
        const panel = document.createElement('div');
        panel.id = 'advancedLoyaltyPanel';
        panel.className = 'hidden mt-4 p-4 bg-gradient-to-br from-slate-800/90 to-slate-900/90 border border-amber-500/30 rounded-xl shadow-lg';
        panel.innerHTML = `
            <div class="flex items-center justify-between mb-3">
                <div class="flex items-center gap-2">
                    <div id="tierBadge" class="w-8 h-8 rounded-full flex items-center justify-center">
                        <i class="fas fa-crown text-sm"></i>
                    </div>
                    <div>
                        <h4 class="text-white text-sm font-semibold" id="tierName">-</h4>
                        <p class="text-[10px] text-slate-400" id="tierMultiplier">-</p>
                    </div>
                </div>
                <button onclick="AdvancedLoyalty.showLoyaltyDetails()" class="text-amber-400 hover:text-amber-300 text-xs">
                    <i class="fas fa-chart-bar"></i>
                </button>
            </div>
            
            <!-- Progress to next tier -->
            <div id="tierProgressSection" class="mb-3">
                <div class="flex justify-between text-[10px] text-slate-400 mb-1">
                    <span>Progress to <span id="nextTierName" class="text-amber-400">-</span></span>
                    <span id="tierProgressText">-</span>
                </div>
                <div class="h-2 bg-slate-700 rounded-full overflow-hidden">
                    <div id="tierProgressBar" class="h-full bg-gradient-to-r from-amber-500 to-amber-300 transition-all duration-500" style="width: 0%"></div>
                </div>
                <p class="text-[10px] text-slate-500 mt-1" id="pointsToNextTier">-</p>
            </div>
            
            <!-- Points balance -->
            <div class="grid grid-cols-2 gap-2 mb-3">
                <div class="p-2 bg-slate-900/50 rounded-lg text-center">
                    <p class="text-amber-400 text-lg font-bold font-mono" id="availablePoints">0</p>
                    <p class="text-[10px] text-slate-500">Available Points</p>
                </div>
                <div class="p-2 bg-slate-900/50 rounded-lg text-center">
                    <p class="text-emerald-400 text-lg font-bold font-mono" id="lifetimePoints">0</p>
                    <p class="text-[10px] text-slate-500">Lifetime Points</p>
                </div>
            </div>
            
            <!-- Tier benefits -->
            <div id="tierBenefits" class="space-y-1 text-[10px]">
                <!-- Populated dynamically -->
            </div>
            
            <!-- Redeem button -->
            <button onclick="AdvancedLoyalty.showRedeemModal()" 
                    id="redeemPointsBtn"
                    class="w-full mt-3 py-2 bg-amber-500 text-slate-900 rounded-lg font-semibold text-sm hover:bg-amber-400 transition-colors disabled:opacity-50 disabled:cursor-not-allowed">
                <i class="fas fa-gift mr-1"></i>Redeem Points
            </button>
        `;

        // Insert in sidebar
        const cartContainer = sidebar.querySelector('#cartItems');
        if (cartContainer) {
            cartContainer.parentNode.insertBefore(panel, cartContainer);
        }

        // Add loyalty details modal
        addLoyaltyDetailsModal();
        addRedeemModal();
    }

    function addLoyaltyDetailsModal() {
        const modal = document.createElement('div');
        modal.id = 'loyaltyDetailsModal';
        modal.className = 'fixed inset-0 z-[2000] hidden items-center justify-center';
        modal.innerHTML = `
            <div class="absolute inset-0 bg-black/70" onclick="AdvancedLoyalty.hideLoyaltyDetails()"></div>
            <div class="relative bg-slate-900 rounded-2xl w-full max-w-2xl mx-4 border border-slate-700 shadow-2xl max-h-[90vh] overflow-y-auto">
                <div class="sticky top-0 bg-slate-900 z-10 flex justify-between items-center p-4 border-b border-slate-700">
                    <h3 class="text-white font-semibold text-lg"><i class="fas fa-crown mr-2 text-amber-400"></i>Loyalty Dashboard</h3>
                    <button onclick="AdvancedLoyalty.hideLoyaltyDetails()" class="text-slate-400 hover:text-white text-2xl">&times;</button>
                </div>
                
                <div class="p-4 space-y-4">
                    <!-- Tier comparison -->
                    <div class="grid grid-cols-4 gap-2" id="tierComparison">
                        <!-- Populated dynamically -->
                    </div>
                    
                    <!-- Stats -->
                    <div class="grid grid-cols-3 gap-3">
                        <div class="p-3 bg-slate-800 rounded-lg text-center">
                            <p class="text-2xl font-bold text-emerald-400" id="detailTotalRedeemed">0</p>
                            <p class="text-xs text-slate-500">Points Redeemed</p>
                        </div>
                        <div class="p-3 bg-slate-800 rounded-lg text-center">
                            <p class="text-2xl font-bold text-amber-400" id="detailSavings">0</p>
                            <p class="text-xs text-slate-500">Total Savings</p>
                        </div>
                        <div class="p-3 bg-slate-800 rounded-lg text-center">
                            <p class="text-2xl font-bold text-blue-400" id="detailMemberSince">-</p>
                            <p class="text-xs text-slate-500">Member Since</p>
                        </div>
                    </div>
                    
                    <!-- Transaction history -->
                    <div>
                        <h4 class="text-white font-medium mb-2 text-sm">Points History</h4>
                        <div id="pointsHistoryList" class="space-y-2 max-h-48 overflow-y-auto">
                            <!-- Populated dynamically -->
                        </div>
                    </div>
                    
                    <!-- Referral section -->
                    <div id="referralSection" class="p-3 bg-gradient-to-r from-amber-500/10 to-transparent border border-amber-500/20 rounded-lg">
                        <h4 class="text-amber-400 font-medium text-sm mb-2"><i class="fas fa-user-plus mr-1"></i>Refer & Earn</h4>
                        <p class="text-slate-400 text-xs mb-2">Share your code and earn ${LOYALTY_CONFIG.referralBonus} points per referral</p>
                        <div class="flex gap-2">
                            <input type="text" id="referralCode" readonly 
                                   class="flex-1 px-3 py-2 bg-slate-800 border border-slate-600 rounded text-white text-sm font-mono">
                            <button onclick="AdvancedLoyalty.copyReferralCode()" class="px-3 py-2 bg-slate-700 text-white rounded hover:bg-slate-600">
                                <i class="fas fa-copy"></i>
                            </button>
                        </div>
                        <p class="text-[10px] text-slate-500 mt-2" id="referralStats">0 referrals • 0 points earned</p>
                    </div>
                </div>
            </div>
        `;
        document.body.appendChild(modal);
    }

    function addRedeemModal() {
        const modal = document.createElement('div');
        modal.id = 'redeemPointsModal';
        modal.className = 'fixed inset-0 z-[2000] hidden items-center justify-center';
        modal.innerHTML = `
            <div class="absolute inset-0 bg-black/70" onclick="AdvancedLoyalty.hideRedeemModal()"></div>
            <div class="relative bg-slate-900 rounded-2xl w-full max-w-md mx-4 border border-slate-700 shadow-2xl">
                <div class="flex justify-between items-center p-4 border-b border-slate-700">
                    <h3 class="text-white font-semibold"><i class="fas fa-gift mr-2 text-amber-400"></i>Redeem Points</h3>
                    <button onclick="AdvancedLoyalty.hideRedeemModal()" class="text-slate-400 hover:text-white text-2xl">&times;</button>
                </div>
                
                <div class="p-4">
                    <div class="text-center mb-4">
                        <p class="text-slate-400 text-sm">Available Balance</p>
                        <p class="text-3xl font-bold text-amber-400 font-mono" id="redeemAvailablePoints">0</p>
                        <p class="text-emerald-400 text-sm">= ${CONFIG.currency} <span id="redeemValue">0.00</span></p>
                    </div>
                    
                    <div class="space-y-2 mb-4">
                        <p class="text-xs text-slate-500 uppercase tracking-wider">Quick Redeem</p>
                        <div class="grid grid-cols-3 gap-2">
                            <button onclick="AdvancedLoyalty.redeemPoints(100)" class="py-2 bg-slate-800 border border-slate-700 rounded text-white text-sm hover:border-amber-500">
                                100 pts
                            </button>
                            <button onclick="AdvancedLoyalty.redeemPoints(500)" class="py-2 bg-slate-800 border border-slate-700 rounded text-white text-sm hover:border-amber-500">
                                500 pts
                            </button>
                            <button onclick="AdvancedLoyalty.redeemPoints(1000)" class="py-2 bg-slate-800 border border-slate-700 rounded text-white text-sm hover:border-amber-500">
                                1000 pts
                            </button>
                        </div>
                    </div>
                    
                    <div class="space-y-2">
                        <p class="text-xs text-slate-500 uppercase tracking-wider">Custom Amount</p>
                        <div class="flex gap-2">
                            <input type="number" id="customRedeemAmount" min="1" step="1" 
                                   class="flex-1 px-3 py-2 bg-slate-800 border border-slate-600 rounded text-white text-sm"
                                   placeholder="Enter points">
                            <button onclick="AdvancedLoyalty.redeemCustomPoints()" 
                                    class="px-4 py-2 bg-amber-500 text-slate-900 rounded font-semibold hover:bg-amber-400">
                                Redeem
                            </button>
                        </div>
                    </div>
                    
                    <div class="mt-4 p-3 bg-blue-500/10 border border-blue-500/20 rounded">
                        <p class="text-blue-400 text-xs"><i class="fas fa-info-circle mr-1"></i>Points will be applied to the current sale</p>
                    </div>
                </div>
            </div>
        `;
        document.body.appendChild(modal);
    }

    // ============================================
    // CUSTOMER LOYALTY HANDLING
    // ============================================
    async function onCustomerSelected(e) {
        const customer = e?.detail?.customer || window.selectedCustomer;
        if (!customer || !customer.id) {
            hideLoyaltyPanel();
            return;
        }

        await loadCustomerLoyalty(customer.id);
    }

    async function loadCustomerLoyalty(customerId) {
        try {
            const response = await fetch(CONFIG.ajaxUrl + 'get_advanced_loyalty.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    customer_id: customerId,
                    company_id: CONFIG.companyId
                })
            });

            const data = await response.json();
            
            if (data.success) {
                currentCustomerLoyalty = data.loyalty;
                renderLoyaltyPanel(data.loyalty);
            }
        } catch (err) {
            console.error('[Advanced Loyalty] Failed to load:', err);
        }
    }

    function renderLoyaltyPanel(loyalty) {
        const panel = document.getElementById('advancedLoyaltyPanel');
        if (!panel) return;

        panel.classList.remove('hidden');

        const tier = calculateTier(loyalty.lifetime_points);
        const progress = calculateTierProgress(loyalty.lifetime_points, tier.key);

        // Tier badge
        const badge = document.getElementById('tierBadge');
        badge.style.backgroundColor = tier.color + '30';
        badge.style.color = tier.color;
        
        document.getElementById('tierName').textContent = tier.name + ' Member';
        document.getElementById('tierMultiplier').textContent = 
            `${tier.multiplier}x points on every purchase`;

        // Progress
        if (progress.nextTier) {
            document.getElementById('tierProgressSection').classList.remove('hidden');
            document.getElementById('nextTierName').textContent = progress.nextTier.name;
            document.getElementById('tierProgressText').textContent = progress.percent.toFixed(0) + '%';
            document.getElementById('tierProgressBar').style.width = progress.percent + '%';
            document.getElementById('pointsToNextTier').textContent = 
                `${progress.pointsNeeded} points to ${progress.nextTier.name}`;
        } else {
            document.getElementById('tierProgressSection').classList.add('hidden');
        }

        // Points
        document.getElementById('availablePoints').textContent = loyalty.available_points.toLocaleString();
        document.getElementById('lifetimePoints').textContent = loyalty.lifetime_points.toLocaleString();

        // Benefits
        const benefitsDiv = document.getElementById('tierBenefits');
        const benefits = [
            `${tier.multiplier}x points multiplier`,
            tier.discount > 0 ? `${tier.discount}% tier discount` : 'Earn points faster',
            'Exclusive member offers',
            tier.key === 'platinum' ? 'VIP customer support' : 'Priority support'
        ];
        benefitsDiv.innerHTML = benefits.map(b => `
            <div class="flex items-center gap-1 text-slate-400">
                <i class="fas fa-check text-emerald-500 text-[8px]"></i>
                <span>${b}</span>
            </div>
        `).join('');

        // Redeem button state
        const redeemBtn = document.getElementById('redeemPointsBtn');
        redeemBtn.disabled = loyalty.available_points <= 0;
        
        // Update global multiplier
        activeMultiplier = tier.multiplier;
    }

    function hideLoyaltyPanel() {
        const panel = document.getElementById('advancedLoyaltyPanel');
        if (panel) panel.classList.add('hidden');
        currentCustomerLoyalty = null;
        activeMultiplier = 1;
    }

    // ============================================
    // POINTS CALCULATION & AWARDING
    // ============================================
    function calculatePointsForSale(subtotal) {
        const basePoints = Math.floor(subtotal * LOYALTY_CONFIG.pointsPerCurrency);
        const tierMultiplier = currentCustomerLoyalty ? 
            LOYALTY_CONFIG.tiers[currentCustomerLoyalty.tier]?.multiplier || 1 : 1;
        
        // Apply special event multiplier
        const totalMultiplier = tierMultiplier * activeMultiplier;
        
        return Math.floor(basePoints * totalMultiplier);
    }

    async function awardPoints(saleId, points, reason = 'purchase') {
        if (!currentCustomerLoyalty || points <= 0) return;

        try {
            await fetch(CONFIG.ajaxUrl + 'award_loyalty_points.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    customer_id: currentCustomerLoyalty.customer_id,
                    sale_id: saleId,
                    points: points,
                    reason: reason,
                    multiplier: activeMultiplier,
                    company_id: CONFIG.companyId
                })
            });

            // Refresh loyalty data
            await loadCustomerLoyalty(currentCustomerLoyalty.customer_id);
            
            showPointsAwardedToast(points, reason);
        } catch (err) {
            console.error('[Advanced Loyalty] Failed to award points:', err);
        }
    }

    function onSaleCompleted(e) {
        const sale = e.detail;
        if (!currentCustomerLoyalty || !sale) return;

        // Calculate points
        const points = calculatePointsForSale(sale.subtotal);
        
        // Award points
        awardPoints(sale.id, points, 'purchase');
    }

    // ============================================
    // REFERRAL SYSTEM
    // ============================================
    function initReferralSystem() {
        // Referral tracking is handled server-side
    }

    async function generateReferralCode() {
        if (!currentCustomerLoyalty) return null;

        try {
            const response = await fetch(CONFIG.ajaxUrl + 'get_referral_code.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    customer_id: currentCustomerLoyalty.customer_id,
                    company_id: CONFIG.companyId
                })
            });

            const data = await response.json();
            return data.success ? data.referral_code : null;
        } catch (err) {
            console.error('[Advanced Loyalty] Failed to get referral code:', err);
            return null;
        }
    }

    // ============================================
    // SPECIAL EVENTS
    // ============================================
    function checkSpecialEvents() {
        // Check for double points days, holidays, etc.
        const today = new Date();
        const dayOfWeek = today.getDay();
        
        // Weekend bonus
        if (dayOfWeek === 0 || dayOfWeek === 6) {
            activeMultiplier = LOYALTY_CONFIG.specialEventMultipliers.weekend_special;
            showEventBanner('Weekend Special: 1.5x Points!');
        }
        
        // Check for configured special events from server
        fetch(CONFIG.ajaxUrl + 'get_active_loyalty_events.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ company_id: CONFIG.companyId })
        })
        .then(r => r.json())
        .then(data => {
            if (data.success && data.events) {
                data.events.forEach(event => {
                    if (event.multiplier > activeMultiplier) {
                        activeMultiplier = event.multiplier;
                        showEventBanner(event.name + ': ' + event.multiplier + 'x Points!');
                    }
                });
            }
        })
        .catch(() => {});
    }

    function showEventBanner(message) {
        const banner = document.createElement('div');
        banner.className = 'fixed top-16 left-1/2 transform -translate-x-1/2 z-[1500] px-4 py-2 bg-gradient-to-r from-amber-500 to-orange-500 text-slate-900 rounded-full shadow-lg animate-bounce';
        banner.innerHTML = `<i class="fas fa-star mr-2"></i>${message}`;
        document.body.appendChild(banner);
        
        setTimeout(() => banner.remove(), 5000);
    }

    // ============================================
    // UI INTERACTIONS
    // ============================================
    function showLoyaltyDetails() {
        const modal = document.getElementById('loyaltyDetailsModal');
        if (!modal || !currentCustomerLoyalty) return;

        modal.classList.remove('hidden');
        modal.classList.add('flex');
        
        renderTierComparison();
        renderPointsHistory();
        loadReferralInfo();
    }

    function hideLoyaltyDetails() {
        const modal = document.getElementById('loyaltyDetailsModal');
        if (modal) {
            modal.classList.add('hidden');
            modal.classList.remove('flex');
        }
    }

    function renderTierComparison() {
        const container = document.getElementById('tierComparison');
        if (!container) return;

        const currentTier = currentCustomerLoyalty?.tier || 'bronze';

        container.innerHTML = Object.entries(LOYALTY_CONFIG.tiers).map(([key, tier]) => {
            const isCurrent = key === currentTier;
            return `
                <div class="p-3 rounded-lg border-2 ${isCurrent ? 'border-amber-500 bg-amber-500/10' : 'border-slate-700 bg-slate-800'}">
                    <div class="w-8 h-8 rounded-full mx-auto mb-2 flex items-center justify-center" style="background-color: ${tier.color}30; color: ${tier.color}">
                        <i class="fas fa-crown text-sm"></i>
                    </div>
                    <p class="text-white text-xs font-semibold text-center">${tier.name}</p>
                    <p class="text-[10px] text-slate-400 text-center">${tier.minPoints}+ pts</p>
                    <p class="text-[10px] text-amber-400 text-center mt-1">${tier.multiplier}x</p>
                    ${tier.discount > 0 ? `<p class="text-[10px] text-emerald-400 text-center">${tier.discount}% off</p>` : ''}
                </div>
            `;
        }).join('');
    }

    async function renderPointsHistory() {
        if (!currentCustomerLoyalty) return;

        try {
            const response = await fetch(CONFIG.ajaxUrl + 'get_points_history.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    customer_id: currentCustomerLoyalty.customer_id,
                    company_id: CONFIG.companyId,
                    limit: 20
                })
            });

            const data = await response.json();
            const container = document.getElementById('pointsHistoryList');
            
            if (container && data.success) {
                container.innerHTML = data.history.map(h => `
                    <div class="flex justify-between items-center p-2 bg-slate-800 rounded">
                        <div>
                            <p class="text-white text-xs">${h.description}</p>
                            <p class="text-[10px] text-slate-500">${new Date(h.created_at).toLocaleDateString()}</p>
                        </div>
                        <span class="text-sm font-mono ${h.points > 0 ? 'text-emerald-400' : 'text-red-400'}">
                            ${h.points > 0 ? '+' : ''}${h.points}
                        </span>
                    </div>
                `).join('');
            }

            // Update stats
            document.getElementById('detailTotalRedeemed').textContent = 
                currentCustomerLoyalty.total_redeemed?.toLocaleString() || '0';
            document.getElementById('detailSavings').textContent = 
                CONFIG.currency + ' ' + (currentCustomerLoyalty.total_savings || 0).toFixed(2);
            document.getElementById('detailMemberSince').textContent = 
                new Date(currentCustomerLoyalty.created_at).toLocaleDateString();
        } catch (err) {
            console.error('[Advanced Loyalty] Failed to load history:', err);
        }
    }

    async function loadReferralInfo() {
        const code = await generateReferralCode();
        if (code) {
            document.getElementById('referralCode').value = code;
        }
    }

    function showRedeemModal() {
        if (!currentCustomerLoyalty) return;

        const modal = document.getElementById('redeemPointsModal');
        if (!modal) return;

        document.getElementById('redeemAvailablePoints').textContent = 
            currentCustomerLoyalty.available_points.toLocaleString();
        document.getElementById('redeemValue').textContent = 
            (currentCustomerLoyalty.available_points * LOYALTY_CONFIG.currencyPerPoint).toFixed(2);

        modal.classList.remove('hidden');
        modal.classList.add('flex');
    }

    function hideRedeemModal() {
        const modal = document.getElementById('redeemPointsModal');
        if (modal) {
            modal.classList.add('hidden');
            modal.classList.remove('flex');
        }
    }

    async function redeemPoints(points) {
        if (!currentCustomerLoyalty || points > currentCustomerLoyalty.available_points) {
            showToast('Insufficient points', 'error');
            return;
        }

        // Emit event for POS to apply discount
        const event = new CustomEvent('loyalty:redeem', {
            detail: {
                points: points,
                value: points * LOYALTY_CONFIG.currencyPerPoint,
                customerId: currentCustomerLoyalty.customer_id
            }
        });
        document.dispatchEvent(event);

        hideRedeemModal();
        showToast(`${points} points redeemed!`, 'success');
    }

    function redeemCustomPoints() {
        const input = document.getElementById('customRedeemAmount');
        const points = parseInt(input?.value) || 0;
        if (points > 0) {
            redeemPoints(points);
        }
    }

    async function copyReferralCode() {
        const code = document.getElementById('referralCode')?.value;
        if (code) {
            await navigator.clipboard.writeText(code);
            showToast('Referral code copied!', 'success');
        }
    }

    function showToast(message, type = 'info') {
        if (typeof window.showToast === 'function') {
            window.showToast(message, type);
        }
    }

    function initTierProgressUI() {
        // Already handled in renderLoyaltyPanel
    }

    function initPersonalizedRewards() {
        // Personalized rewards based on purchase history
    }

    // ============================================
    // PUBLIC API
    // ============================================
    window.AdvancedLoyalty = {
        showLoyaltyDetails,
        hideLoyaltyDetails,
        showRedeemModal,
        hideRedeemModal,
        redeemPoints,
        redeemCustomPoints,
        copyReferralCode,
        calculatePointsForSale,
        getCurrentTier: () => currentCustomerLoyalty ? calculateTier(currentCustomerLoyalty.lifetime_points) : null,
        getMultiplier: () => activeMultiplier,
        refresh: () => {
            if (currentCustomerLoyalty) {
                loadCustomerLoyalty(currentCustomerLoyalty.customer_id);
            }
        },
        forceSync: async () => {
            // Refresh loyalty data from server
            if (currentCustomerLoyalty) {
                await loadCustomerLoyalty(currentCustomerLoyalty.customer_id);
            }
        }
    };

    // Expose config
    window.LOYALTY_CONFIG = LOYALTY_CONFIG;

})();
