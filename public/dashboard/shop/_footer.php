<?php
/**
 * Store Admin — Shared Footer Partial
 * @version 3.0
 * 
 * Closes the main content wrapper, body and html tags.
 * Includes footer content, stats, and closing scripts.
 * 
 * Optional variables:
 *   - $show_footer_stats (bool) - Show footer stats
 *   - $hide_footer (bool) - Hide footer entirely
 *   - $footer_links (array) - Custom footer links
 *   - $app_version (string) - Application version
 *   - $store_name (string) - Store name
 *   - $tenant_id (int) - Tenant ID
 */
?>

<?php if (empty($hide_footer)): ?>
<!-- Footer -->
<footer class="border-t border-slate-700/40 px-4 md:px-6 py-3 flex flex-col sm:flex-row items-center justify-between gap-2 text-[11px] text-slate-500 flex-shrink-0 bg-slate-900/30">
    <div class="flex flex-wrap items-center gap-3 md:gap-4 justify-center sm:justify-start">
        <!-- Copyright -->
        <span>&copy; <?= date('Y') ?> <?= htmlspecialchars($store_name ?? 'Store') ?></span>
        
        <span class="text-slate-700 hidden xs:inline">|</span>
        
        <!-- Version -->
        <?php if (!empty($app_version)): ?>
        <span class="flex items-center gap-1.5">
            <i class="fas fa-code-branch text-[9px] text-slate-600"></i>
            v<?= htmlspecialchars($app_version) ?>
        </span>
        <span class="text-slate-700 hidden xs:inline">|</span>
        <?php endif; ?>
        
        <!-- Tenant ID (if available) -->
        <?php if (!empty($tenant_id)): ?>
        <span class="text-[10px] text-slate-600">
            Tenant: #<?= (int)$tenant_id ?>
        </span>
        <span class="text-slate-700 hidden xs:inline">|</span>
        <?php endif; ?>
        
        <!-- PHP Version -->
        <?php if (!empty($show_php_version)): ?>
        <span class="flex items-center gap-1.5 text-[10px] text-slate-600">
            <i class="fab fa-php text-[10px]"></i>
            PHP <?= phpversion() ?>
        </span>
        <span class="text-slate-700 hidden xs:inline">|</span>
        <?php endif; ?>
        
        <!-- Memory Usage -->
        <?php if (!empty($show_memory_usage) && function_exists('memory_get_usage')): ?>
        <span class="flex items-center gap-1.5 text-[10px] text-slate-600">
            <i class="fas fa-microchip text-[9px]"></i>
            <?= round(memory_get_usage() / 1024 / 1024, 1) ?> MB
        </span>
        <?php endif; ?>
    </div>

    <div class="flex flex-wrap items-center gap-3 justify-center sm:justify-end">
        <!-- System Status -->
        <span class="flex items-center gap-1.5">
            <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse"></span>
            <span class="text-[10px] text-slate-500">System Online</span>
        </span>
        
        <span class="text-slate-700 hidden xs:inline">|</span>
        
        <!-- Execution Time -->
        <?php if (!empty($show_execution_time) && isset($_SERVER['REQUEST_TIME_FLOAT'])): ?>
        <span class="text-[10px] text-slate-600">
            <i class="fas fa-clock text-[9px]"></i>
            <?= round((microtime(true) - $_SERVER['REQUEST_TIME_FLOAT']) * 1000, 1) ?> ms
        </span>
        <span class="text-slate-700 hidden xs:inline">|</span>
        <?php endif; ?>
        
        <!-- Custom Footer Links -->
        <?php if (!empty($footer_links) && is_array($footer_links)): ?>
            <?php foreach ($footer_links as $index => $link): ?>
                <?php if ($index > 0): ?>
                <span class="text-slate-700 hidden xs:inline">|</span>
                <?php endif; ?>
                <a href="<?= htmlspecialchars($link['url'] ?? '#') ?>" 
                   target="<?= htmlspecialchars($link['target'] ?? '_blank') ?>"
                   class="hover:text-slate-300 transition-colors text-[10px] <?= $link['class'] ?? '' ?>">
                    <?php if (!empty($link['icon'])): ?>
                    <i class="fas <?= htmlspecialchars($link['icon']) ?> text-[9px]"></i>
                    <?php endif; ?>
                    <?= htmlspecialchars($link['label'] ?? '') ?>
                </a>
            <?php endforeach; ?>
        <?php else: ?>
            <!-- Default Links -->
            <a href="/admin/docs.php" target="_blank" class="hover:text-slate-300 transition-colors text-[10px]">
                <i class="fas fa-book text-[9px]"></i> Documentation
            </a>
            <span class="text-slate-700 hidden xs:inline">|</span>
            <a href="/admin/support.php" target="_blank" class="hover:text-slate-300 transition-colors text-[10px]">
                <i class="fas fa-life-ring text-[9px]"></i> Support
            </a>
        <?php endif; ?>
    </div>
</footer>
<?php endif; ?>

<!-- Toast Container (if not already in layout) -->
<?php if (empty($hide_toast_container)): ?>
<div class="toast-container" id="toastContainer"></div>
<?php endif; ?>

<!-- Closing Scripts -->
<script>
// Auto-dismiss flash messages
document.addEventListener('DOMContentLoaded', function() {
    // Toast system
    window.showToast = function(message, type = 'success', duration = 4000) {
        const container = document.getElementById('toastContainer') || document.body;
        if (!container) return;
        
        const toast = document.createElement('div');
        toast.className = `toast toast-${type}`;
        
        const icons = {
            success: 'fa-check-circle text-emerald-400',
            error: 'fa-times-circle text-red-400',
            warning: 'fa-exclamation-triangle text-amber-400',
            info: 'fa-info-circle text-blue-400'
        };
        
        toast.innerHTML = `
            <i class="fas ${icons[type] || icons.info} text-lg"></i>
            <span class="text-sm text-slate-200 flex-1">${message}</span>
            <button onclick="this.parentElement.remove()" class="text-slate-500 hover:text-slate-300 transition-colors">
                <i class="fas fa-times text-xs"></i>
            </button>
        `;
        
        container.appendChild(toast);
        
        setTimeout(() => {
            toast.style.animation = 'slideOutRight 0.3s ease-in forwards';
            setTimeout(() => toast.remove(), 300);
        }, duration);
    };
    
    // Flash message helper
    window.flash = function(message, type = 'success') {
        showToast(message, type);
    };
    
    // Auto-dismiss flash messages
    document.querySelectorAll('.animate-fade-in, .flash-message').forEach(el => {
        setTimeout(() => {
            el.style.transition = 'opacity 0.5s';
            el.style.opacity = '0';
            setTimeout(() => el.remove(), 500);
        }, 5000);
    });
});

// Copy to clipboard helper
window.copyToClipboard = function(text, successMessage = 'Copied to clipboard!') {
    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(text).then(() => {
            window.flash?.(successMessage, 'success') || alert(successMessage);
        }).catch(() => {
            fallbackCopy(text, successMessage);
        });
    } else {
        fallbackCopy(text, successMessage);
    }
};

function fallbackCopy(text, successMessage) {
    const textarea = document.createElement('textarea');
    textarea.value = text;
    textarea.style.position = 'fixed';
    textarea.style.opacity = '0';
    textarea.style.left = '-9999px';
    document.body.appendChild(textarea);
    textarea.select();
    try {
        document.execCommand('copy');
        window.flash?.(successMessage, 'success') || alert(successMessage);
    } catch (err) {
        window.flash?.('Failed to copy', 'error') || alert('Failed to copy');
    }
    document.body.removeChild(textarea);
}

// Keyboard shortcuts
document.addEventListener('keydown', function(e) {
    // Ctrl+S to save
    if ((e.ctrlKey || e.metaKey) && e.key === 's') {
        const saveBtn = document.querySelector('[data-save], .save-btn, button[type="submit"]');
        if (saveBtn && !saveBtn.disabled) {
            e.preventDefault();
            saveBtn.click();
        }
    }
    
    // Escape to close modals
    if (e.key === 'Escape') {
        document.querySelectorAll('.modal, .modal-overlay, .dropdown-open').forEach(el => {
            el.classList.remove('active', 'show', 'modal-open');
        });
    }
});

// Console branding
console.log('%c Store Admin v<?= htmlspecialchars($app_version ?? '1.0') ?> ', 'background: #f68b1e; color: white; font-size: 14px; padding: 8px 12px; border-radius: 4px; font-weight: bold;');
console.log('%c 🚀 Ready ', 'background: #0b1120; color: #94a3b8; font-size: 12px; padding: 4px 8px;');
</script>

<!-- Styles for toast (if not in layout) -->
<?php if (empty($hide_toast_styles)): ?>
<style>
.toast-container {
    position: fixed;
    top: 1rem;
    right: 1rem;
    z-index: 9999;
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
    max-width: 400px;
    width: 100%;
    pointer-events: none;
}

.toast {
    pointer-events: auto;
    padding: 0.75rem 1rem;
    border-radius: 0.75rem;
    background: #1e293b;
    border: 1px solid #334155;
    box-shadow: 0 10px 40px rgba(0, 0, 0, 0.4);
    display: flex;
    align-items: center;
    gap: 0.75rem;
    animation: slideInRight 0.3s ease-out;
    backdrop-filter: blur(12px);
}

.toast-success { border-left: 4px solid #10b981; }
.toast-error { border-left: 4px solid #ef4444; }
.toast-warning { border-left: 4px solid #f59e0b; }
.toast-info { border-left: 4px solid #3b82f6; }

@keyframes slideInRight {
    from { transform: translateX(100%); opacity: 0; }
    to { transform: translateX(0); opacity: 1; }
}

@keyframes slideOutRight {
    from { transform: translateX(0); opacity: 1; }
    to { transform: translateX(100%); opacity: 0; }
}
</style>
<?php endif; ?>

</body>
</html>