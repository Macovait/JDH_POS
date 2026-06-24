/**
 * POS Calculator Module
 * Enhanced payment panel with numeric keypad, calculator modal, and manual price override
 */

(function() {
    'use strict';

    // Module state
    var POSCalc = {
        soundEnabled: true,
        keypadSound: null,
        calculatorOpen: false,
        priceOverrideModalOpen: false
    };

    // Initialize sound feedback
    function initSound() {
        POSCalc.soundEnabled = localStorage.getItem('pos_calc_sound') !== 'false';
    }

    // Play key press sound
    function playKeySound() {
        if (!POSCalc.soundEnabled) return;
        
        try {
            var ctx = new (window.AudioContext || window.webkitAudioContext)();
            var osc = ctx.createOscillator();
            var gain = ctx.createGain();
            
            osc.connect(gain);
            gain.connect(ctx.destination);
            
            osc.frequency.value = 800;
            osc.type = 'sine';
            gain.gain.setValueAtTime(0.1, ctx.currentTime);
            gain.gain.exponentialRampToValueAtTime(0.01, ctx.currentTime + 0.05);
            
            osc.start(ctx.currentTime);
            osc.stop(ctx.currentTime + 0.05);
        } catch (e) {
            // Sound not supported
        }
    }

    // Secure number parsing
    function parseNumber(value) {
        var num = parseFloat(String(value).replace(/[^\d.-]/g, ''));
        return isNaN(num) ? 0 : Math.round(num * 100) / 100;
    }

    // Format currency
    function formatCurrency(amount) {
        var currency = 'KES';
        if (typeof CONFIG !== 'undefined' && CONFIG.currency) {
            currency = CONFIG.currency;
        }
        return currency + ' ' + Number(amount || 0).toFixed(2);
    }

    // ============ PAYMENT PANEL KEYPAD ============

    // Global keypad functions
    window.keypadInput = function(value) {
        playKeySound();
        
        var input = document.getElementById('amountReceived');
        if (!input) return;

        var currentValue = input.value.replace(/[^\d.]/g, '');
        
        if (value === 'C') {
            currentValue = '';
        } else if (value === '.') {
            if (!currentValue.includes('.')) {
                currentValue = currentValue ? currentValue + '.' : '0.';
            }
        } else {
            if (currentValue === '0') {
                currentValue = value;
            } else {
                currentValue += value;
            }
        }
        
        input.value = currentValue;
        calculateChangeEnhanced();
        
        input.focus();
    };

    window.setCashReceived = function(amount) {
        playKeySound();
        
        var input = document.getElementById('amountReceived');
        if (!input) return;
        
        var total = 0;
        if (typeof getPricingBreakdown === 'function') {
            total = getPricingBreakdown().total;
        } else if (window.getPricingBreakdown) {
            total = window.getPricingBreakdown().total;
        }
        
        if (amount === 0) {
            input.value = total.toFixed(2);
        } else {
            input.value = amount.toFixed(2);
        }
        
        calculateChangeEnhanced();
        input.focus();
    };

    // ============ CALCULATOR MODAL ============

    // Calculator state
    var Calculator = {
        display: '0',
        firstOperand: null,
        operator: null,
        waitingForSecondOperand: false
    };

    window.calcInput = function(value) {
        playKeySound();
        
        if (Calculator.waitingForSecondOperand) {
            Calculator.display = value;
            Calculator.waitingForSecondOperand = false;
        } else {
            Calculator.display = Calculator.display === '0' ? value : Calculator.display + value;
        }
        
        updateCalcDisplay();
    };

    window.calcOperator = function(op) {
        playKeySound();
        
        var inputValue = parseFloat(Calculator.display);
        
        if (Calculator.firstOperand === null) {
            Calculator.firstOperand = inputValue;
        } else if (Calculator.operator) {
            var result = calculate(Calculator.firstOperand, inputValue, Calculator.operator);
            Calculator.display = String(result);
            Calculator.firstOperand = result;
            updateCalcDisplay();
        }
        
        Calculator.operator = op;
        Calculator.waitingForSecondOperand = true;
    };

    window.calcEquals = function() {
        playKeySound();
        
        if (Calculator.operator && Calculator.firstOperand !== null) {
            var inputValue = parseFloat(Calculator.display);
            var result = calculate(Calculator.firstOperand, inputValue, Calculator.operator);
            
            Calculator.display = String(result);
            Calculator.firstOperand = null;
            Calculator.operator = null;
            Calculator.waitingForSecondOperand = false;
            
            updateCalcDisplay();
        }
    };

    window.calcClear = function() {
        playKeySound();
        
        Calculator.display = '0';
        Calculator.firstOperand = null;
        Calculator.operator = null;
        Calculator.waitingForSecondOperand = false;
        
        updateCalcDisplay();
    };

    function calculate(a, b, op) {
        a = parseFloat(a);
        b = parseFloat(b);
        
        switch (op) {
            case '+': return Math.round((a + b) * 100) / 100;
            case '-': return Math.round((a - b) * 100) / 100;
            case '*': return Math.round((a * b) * 100) / 100;
            case '/': return b !== 0 ? Math.round((a / b) * 100) / 100 : 0;
            case '%': return Math.round((a * (b / 100)) * 100) / 100;
            default: return b;
        }
    }

    function updateCalcDisplay() {
        var display = document.getElementById('calcDisplay');
        if (display) {
            display.textContent = Calculator.display;
        }
    }

    window.calcCopyResult = function() {
        var result = Calculator.display;
        navigator.clipboard.writeText(result).then(function() {
            showToast('Copied: ' + result, 'success');
        }).catch(function() {
            showToast('Could not copy', 'error');
        });
    };

    window.openCalculator = function() {
        // Reset calculator state
        Calculator.display = '0';
        Calculator.firstOperand = null;
        Calculator.operator = null;
        Calculator.waitingForSecondOperand = false;
        updateCalcDisplay();
        
        document.getElementById('calculatorModal').classList.remove('hidden');
        POSCalc.calculatorOpen = true;
    };

    window.closeCalculator = function() {
        document.getElementById('calculatorModal').classList.add('hidden');
        POSCalc.calculatorOpen = false;
    };

    // ============ MANUAL PRICE OVERRIDE ============

    // Price override state
    var overrideItemIndex = null;

    window.openPriceOverride = function(itemIndex) {
        overrideItemIndex = itemIndex;
        var item = window.cart[itemIndex];
        
        if (!item) return;
        
        document.getElementById('overrideProductName').textContent = item.name;
        document.getElementById('overrideCurrentPrice').textContent = formatCurrency(item.price);
        document.getElementById('overridePriceInput').value = '';
        document.getElementById('adminPinInput').value = '';
        
        document.getElementById('priceOverrideModal').classList.remove('hidden');
        document.getElementById('adminPinInput').focus();
        POSCalc.priceOverrideModalOpen = true;
    };

    window.closePriceOverride = function() {
        document.getElementById('priceOverrideModal').classList.add('hidden');
        POSCalc.priceOverrideModalOpen = false;
        overrideItemIndex = null;
    };

    window.overrideKeypadInput = function(value) {
        playKeySound();
        
        var input = document.getElementById('overridePriceInput');
        if (!input) return;

        var currentValue = input.value.replace(/[^\d.]/g, '');
        
        if (value === 'C') {
            currentValue = '';
        } else if (value === '.') {
            if (!currentValue.includes('.')) {
                currentValue = currentValue ? currentValue + '.' : '0.';
            }
        } else {
            if (currentValue === '0') {
                currentValue = value;
            } else {
                currentValue += value;
            }
        }
        
        input.value = currentValue;
    };

    window.confirmPriceOverride = function() {
        var newPrice = parseNumber(document.getElementById('overridePriceInput').value);
        var adminPin = document.getElementById('adminPinInput').value;
        
        if (newPrice <= 0) {
            showToast('Please enter a valid price', 'error');
            return;
        }
        
        if (!adminPin || adminPin.length < 4) {
            showToast('Please enter admin PIN', 'error');
            return;
        }
        
        // Log the override attempt
        logPriceOverride(overrideItemIndex, newPrice, adminPin);
        
        // Apply the override
        if (overrideItemIndex !== null && window.cart[overrideItemIndex]) {
            var oldPrice = window.cart[overrideItemIndex].price;
            window.cart[overrideItemIndex].price = newPrice;
            
            showToast('Price updated: ' + formatCurrency(oldPrice) + ' → ' + formatCurrency(newPrice), 'success');
            
            // Call the main updateCartDisplay function
            if (typeof updateCartDisplay === 'function') {
                updateCartDisplay();
            }
        }
        
        closePriceOverride();
    };

    function logPriceOverride(itemIndex, newPrice, adminPin) {
        var item = window.cart[itemIndex];
        if (!item) return;
        
        var logEntry = {
            timestamp: new Date().toISOString(),
            product_id: item.id,
            product_name: item.name,
            old_price: item.price,
            new_price: newPrice,
            user_id: (typeof CONFIG !== 'undefined' && CONFIG.userId) ? CONFIG.userId : null,
            cart_index: itemIndex,
            admin_pin: adminPin
        };
        
        // Store in session storage for audit
        try {
            var overrideLogs = JSON.parse(sessionStorage.getItem('priceOverrideLogs') || '[]');
            overrideLogs.push(logEntry);
            sessionStorage.setItem('priceOverrideLogs', JSON.stringify(overrideLogs));
        } catch (e) {}
        
        // Send to server for permanent logging
        if (typeof fetch !== 'undefined') {
            fetch('../ajax/log_price_override.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(logEntry)
            }).catch(function() {});
        }
    }

    // ============ ENHANCED CHANGE CALCULATION ============

    window.calculateChangeEnhanced = function() {
        var total = 0;
        if (typeof getPricingBreakdown === 'function') {
            total = getPricingBreakdown().total;
        } else if (window.getPricingBreakdown) {
            total = window.getPricingBreakdown().total;
        }
        
        var amountInput = document.getElementById('amountReceived');
        var amount = amountInput ? parseNumber(amountInput.value) : 0;
        var change = amount - total;
        
        var changeEl = document.getElementById('changeAmount');
        var indicatorEl = document.getElementById('changeIndicator');
        
        if (changeEl) {
            changeEl.textContent = formatCurrency(Math.abs(change));
            
            if (amount > 0) {
                if (change >= 0) {
                    changeEl.style.color = 'var(--success)';
                    if (indicatorEl) {
                        indicatorEl.innerHTML = '<i class="fas fa-check-circle"></i>';
                        indicatorEl.className = 'payment-change-indicator sufficient';
                    }
                } else {
                    changeEl.style.color = 'var(--danger)';
                    if (indicatorEl) {
                        indicatorEl.innerHTML = '<i class="fas fa-exclamation-circle"></i>';
                        indicatorEl.className = 'payment-change-indicator insufficient';
                    }
                }
            } else {
                changeEl.style.color = 'var(--text-muted)';
                if (indicatorEl) {
                    indicatorEl.innerHTML = '';
                    indicatorEl.className = 'payment-change-indicator';
                }
            }
        }
        
        // Also update the main calculation
        if (typeof calculateChange === 'function') {
            calculateChange();
        }
        
        return change;
    };

    // ============ INITIALIZATION ============

    function init() {
        initSound();
        
        console.log('POS Calculator Module initialized');
    }

    // Auto-init when DOM ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

})();