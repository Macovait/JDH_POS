/* ============================================================
 * POS Barcode Scanner — keyboard-wedge handler
 *
 * Detects rapid keystrokes ending in Enter (typical USB scanner
 * behaviour) anywhere on the page and routes them to the POS
 * "add by barcode" handler. Works regardless of focus, so cashiers
 * can scan without clicking the search box first.
 *
 * Usage: just include this script after pos.php main JS.
 * It auto-installs and calls `addProductByBarcode(code)` if defined,
 * otherwise falls back to dispatching a custom 'pos:barcode' event.
 * ============================================================ */
(function () {
    'use strict';

    var BUFFER       = '';
    var LAST_KEY_AT  = 0;
    var TIMER        = null;

    // Tuning
    var MIN_LENGTH       = 4;     // shortest accepted barcode
    var INTER_KEY_MS     = 50;    // max ms between keys to be considered "scan"
    var SCAN_TIMEOUT_MS  = 250;   // reset buffer if no key within this window
    var TRIGGER_KEY      = 'Enter';

    // Visual feedback element (created on demand)
    var pulseEl = null;

    function showPulse(code) {
        if (!pulseEl) {
            pulseEl = document.createElement('div');
            pulseEl.style.cssText = [
                'position:fixed', 'top:20px', 'left:50%',
                'transform:translateX(-50%)',
                'background:linear-gradient(135deg,#FBBF24,#F59E0B)',
                'color:#0B1024', 'padding:10px 18px',
                'border-radius:999px', 'font-weight:700',
                'font-size:0.85rem',
                'box-shadow:0 14px 36px rgba(251,191,36,0.45)',
                'z-index:9999', 'pointer-events:none',
                'transition:opacity 0.3s ease, transform 0.3s ease',
                'font-family:Inter, sans-serif'
            ].join(';');
            document.body.appendChild(pulseEl);
        }
        pulseEl.textContent = '⚡ Scanned: ' + code;
        pulseEl.style.opacity = '1';
        pulseEl.style.transform = 'translateX(-50%) translateY(0)';
        clearTimeout(pulseEl._t);
        pulseEl._t = setTimeout(function () {
            pulseEl.style.opacity = '0';
            pulseEl.style.transform = 'translateX(-50%) translateY(-10px)';
        }, 1400);
    }

    function dispatch(code) {
        if (!code || code.length < MIN_LENGTH) return;

        // Preferred handler — defined by pos.php
        if (typeof window.addProductByBarcode === 'function') {
            try { window.addProductByBarcode(code); } catch (e) { console.error(e); }
        } else if (typeof window.searchProducts === 'function') {
            // Fallback — push into search
            var box = document.getElementById('searchBox');
            if (box) { box.value = code; window.searchProducts(code); }
        }

        // Always emit event so other modules can listen
        document.dispatchEvent(new CustomEvent('pos:barcode', { detail: { code: code } }));
        showPulse(code);
    }

    function resetBuffer() {
        BUFFER = '';
        LAST_KEY_AT = 0;
    }

    document.addEventListener('keydown', function (e) {
        // Ignore when user is typing in inputs unless it's the search box
        // (search box should still accept scans naturally)
        var inField = e.target && e.target.matches('input, textarea, select, [contenteditable]');
        var isSearch = e.target && e.target.id === 'searchBox';

        // Allow scans in search box; ignore in other inputs
        if (inField && !isSearch) {
            resetBuffer();
            return;
        }

        var now  = Date.now();
        var gap  = LAST_KEY_AT ? (now - LAST_KEY_AT) : 0;

        // If trigger key, flush buffer
        if (e.key === TRIGGER_KEY) {
            if (BUFFER.length >= MIN_LENGTH) {
                e.preventDefault();
                var code = BUFFER;
                resetBuffer();
                dispatch(code);
                return;
            }
            // Short buffer — let Enter behave normally
            resetBuffer();
            return;
        }

        // Single printable character only
        if (e.key.length !== 1) return;

        // If gap too long, restart buffer (likely manual typing)
        if (LAST_KEY_AT && gap > SCAN_TIMEOUT_MS) {
            BUFFER = '';
        }

        BUFFER += e.key;
        LAST_KEY_AT = now;

        // Safety reset
        if (TIMER) clearTimeout(TIMER);
        TIMER = setTimeout(resetBuffer, SCAN_TIMEOUT_MS * 2);
    });

    // Expose tuning + manual trigger for debugging
    window.POSBarcode = {
        configure: function (opts) {
            if (!opts) return;
            if (typeof opts.minLength === 'number')    MIN_LENGTH      = opts.minLength;
            if (typeof opts.interKeyMs === 'number')   INTER_KEY_MS    = opts.interKeyMs;
            if (typeof opts.scanTimeoutMs === 'number') SCAN_TIMEOUT_MS = opts.scanTimeoutMs;
            if (typeof opts.triggerKey === 'string')   TRIGGER_KEY     = opts.triggerKey;
        },
        simulate: dispatch
    };

    console.info('[POS] Barcode scanner ready. Min length:', MIN_LENGTH, '| Scan window:', SCAN_TIMEOUT_MS, 'ms');
})();
