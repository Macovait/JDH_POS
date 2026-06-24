/**
 * Example Plugin JS
 * Hooks into the POS frontend via window.JDH_POS_Hooks
 */
(function () {
    'use strict';

    console.log('[ExamplePlugin] Loaded');

    // Listen for cart updates if the global event bus exists
    if (window.JDH_POS && window.JDH_POS.on) {
        window.JDH_POS.on('cart:updated', (cart) => {
            console.log('[ExamplePlugin] Cart updated:', cart.items.length, 'items');
        });
    }
})();
