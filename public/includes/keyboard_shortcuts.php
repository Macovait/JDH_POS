<?php
/**
 * Keyboard Shortcuts Guide
 * Modal showing available keyboard shortcuts
 */
?>

<!-- Keyboard Shortcuts Modal -->
<div id="shortcuts-modal" class="fixed inset-0 bg-black/50 hidden z-50">
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="bg-gray-800 border border-gray-700 rounded-xl max-w-2xl w-full max-h-[90vh] overflow-y-auto">
            <div class="p-6 border-b border-gray-700">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <i class="fas fa-keyboard text-amber-400"></i>
                        <h3 class="text-lg font-semibold text-white">Keyboard Shortcuts</h3>
                    </div>
                    <button onclick="closeShortcutsModal()" class="text-gray-400 hover:text-white">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            </div>

            <div class="p-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                    <!-- General Shortcuts -->
                    <div>
                        <h4 class="text-white font-medium mb-3 flex items-center gap-2">
                            <i class="fas fa-globe text-blue-400"></i>
                            General
                        </h4>
                        <div class="space-y-2">
                            <div class="flex items-center justify-between py-2 border-b border-gray-700">
                                <span class="text-gray-300">Global Search</span>
                                <kbd class="px-2 py-1 bg-gray-700 text-gray-300 rounded text-xs">Ctrl + K</kbd>
                            </div>
                            <div class="flex items-center justify-between py-2 border-b border-gray-700">
                                <span class="text-gray-300">Open POS</span>
                                <kbd class="px-2 py-1 bg-gray-700 text-gray-300 rounded text-xs">Ctrl + P</kbd>
                            </div>
                            <div class="flex items-center justify-between py-2 border-b border-gray-700">
                                <span class="text-gray-300">Dashboard</span>
                                <kbd class="px-2 py-1 bg-gray-700 text-gray-300 rounded text-xs">Ctrl + D</kbd>
                            </div>
                            <div class="flex items-center justify-between py-2 border-b border-gray-700">
                                <span class="text-gray-300">Notifications</span>
                                <kbd class="px-2 py-1 bg-gray-700 text-gray-300 rounded text-xs">Ctrl + N</kbd>
                            </div>
                        </div>
                    </div>

                    <!-- Navigation Shortcuts -->
                    <div>
                        <h4 class="text-white font-medium mb-3 flex items-center gap-2">
                            <i class="fas fa-route text-green-400"></i>
                            Navigation
                        </h4>
                        <div class="space-y-2">
                            <div class="flex items-center justify-between py-2 border-b border-gray-700">
                                <span class="text-gray-300">Inventory</span>
                                <kbd class="px-2 py-1 bg-gray-700 text-gray-300 rounded text-xs">Ctrl + I</kbd>
                            </div>
                            <div class="flex items-center justify-between py-2 border-b border-gray-700">
                                <span class="text-gray-300">Products</span>
                                <kbd class="px-2 py-1 bg-gray-700 text-gray-300 rounded text-xs">Ctrl + R</kbd>
                            </div>
                            <div class="flex items-center justify-between py-2 border-b border-gray-700">
                                <span class="text-gray-300">Customers</span>
                                <kbd class="px-2 py-1 bg-gray-700 text-gray-300 rounded text-xs">Ctrl + U</kbd>
                            </div>
                            <div class="flex items-center justify-between py-2 border-b border-gray-700">
                                <span class="text-gray-300">Reports</span>
                                <kbd class="px-2 py-1 bg-gray-700 text-gray-300 rounded text-xs">Ctrl + T</kbd>
                            </div>
                        </div>
                    </div>

                    <!-- Advanced Features -->
                    <div>
                        <h4 class="text-white font-medium mb-3 flex items-center gap-2">
                            <i class="fas fa-rocket text-purple-400"></i>
                            Advanced Features
                        </h4>
                        <div class="space-y-2">
                            <div class="flex items-center justify-between py-2 border-b border-gray-700">
                                <span class="text-gray-300">AI Recommendations</span>
                                <kbd class="px-2 py-1 bg-gray-700 text-gray-300 rounded text-xs">Ctrl + A</kbd>
                            </div>
                            <div class="flex items-center justify-between py-2 border-b border-gray-700">
                                <span class="text-gray-300">Smart Inventory</span>
                                <kbd class="px-2 py-1 bg-gray-700 text-gray-300 rounded text-xs">Ctrl + S</kbd>
                            </div>
                            <div class="flex items-center justify-between py-2 border-b border-gray-700">
                                <span class="text-gray-300">Marketing Campaigns</span>
                                <kbd class="px-2 py-1 bg-gray-700 text-gray-300 rounded text-xs">Ctrl + M</kbd>
                            </div>
                            <div class="flex items-center justify-between py-2 border-b border-gray-700">
                                <span class="text-gray-300">Custom Reports</span>
                                <kbd class="px-2 py-1 bg-gray-700 text-gray-300 rounded text-xs">Ctrl + C</kbd>
                            </div>
                        </div>
                    </div>

                    <!-- POS Shortcuts -->
                    <div>
                        <h4 class="text-white font-medium mb-3 flex items-center gap-2">
                            <i class="fas fa-cash-register text-amber-400"></i>
                            POS Actions
                        </h4>
                        <div class="space-y-2">
                            <div class="flex items-center justify-between py-2 border-b border-gray-700">
                                <span class="text-gray-300">New Sale</span>
                                <kbd class="px-2 py-1 bg-gray-700 text-gray-300 rounded text-xs">F1</kbd>
                            </div>
                            <div class="flex items-center justify-between py-2 border-b border-gray-700">
                                <span class="text-gray-300">Search Product</span>
                                <kbd class="px-2 py-1 bg-gray-700 text-gray-300 rounded text-xs">F2</kbd>
                            </div>
                            <div class="flex items-center justify-between py-2 border-b border-gray-700">
                                <span class="text-gray-300">Payment</span>
                                <kbd class="px-2 py-1 bg-gray-700 text-gray-300 rounded text-xs">F3</kbd>
                            </div>
                            <div class="flex items-center justify-between py-2 border-b border-gray-700">
                                <span class="text-gray-300">Clear Cart</span>
                                <kbd class="px-2 py-1 bg-gray-700 text-gray-300 rounded text-xs">F4</kbd>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mt-6 pt-4 border-t border-gray-700">
                    <div class="flex items-center justify-between">
                        <div class="text-sm text-gray-400">
                            <i class="fas fa-lightbulb mr-1"></i>
                            Pro tip: Press <kbd class="px-1 py-0.5 bg-gray-700 text-gray-300 rounded text-xs">?</kbd> anywhere to open this guide
                        </div>
                        <button onclick="closeShortcutsModal()" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-amber-500 text-black text-sm font-semibold hover:bg-amber-600 transition-colors">
                            Got it!
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Global keyboard shortcuts
document.addEventListener('keydown', function(e) {
    // Ignore if typing in input fields
    if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA' || e.target.contentEditable === 'true') {
        return;
    }

    // Ctrl key combinations
    if (e.ctrlKey || e.metaKey) {
        switch (e.key.toLowerCase()) {
            case 'k':
                e.preventDefault();
                document.getElementById('global-search')?.focus();
                break;
            case 'p':
                e.preventDefault();
                window.location.href = '<?php echo base_url('pos/pos.php'); ?>';
                break;
            case 'd':
                e.preventDefault();
                window.location.href = '<?php echo base_url('dashboard/home.php'); ?>';
                break;
            case 'n':
                e.preventDefault();
                // Trigger notifications dropdown
                document.querySelector('[onclick="toggleNotifications()"]')?.click();
                break;
            case 'i':
                e.preventDefault();
                window.location.href = '<?php echo base_url('inventory/inventory.php'); ?>';
                break;
            case 'r':
                e.preventDefault();
                window.location.href = '<?php echo base_url('products/products.php'); ?>';
                break;
            case 'u':
                e.preventDefault();
                window.location.href = '<?php echo base_url('customers/customers.php'); ?>';
                break;
            case 't':
                e.preventDefault();
                window.location.href = '<?php echo base_url('reports/reports.php'); ?>';
                break;
            case 'a':
                e.preventDefault();
                window.location.href = '<?php echo base_url('ai/recommendations.php'); ?>';
                break;
            case 's':
                e.preventDefault();
                window.location.href = '<?php echo base_url('inventory/automated_inventory.php'); ?>';
                break;
            case 'm':
                e.preventDefault();
                window.location.href = '<?php echo base_url('marketing/campaigns.php'); ?>';
                break;
            case 'c':
                e.preventDefault();
                window.location.href = '<?php echo base_url('reports/custom_builder.php'); ?>';
                break;
        }
    }

    // Single key shortcuts
    if (!e.ctrlKey && !e.metaKey && !e.altKey && !e.shiftKey) {
        switch (e.key) {
            case '?':
                e.preventDefault();
                openShortcutsModal();
                break;
        }
    }
});

// Shortcuts modal functions
function openShortcutsModal() {
    document.getElementById('shortcuts-modal').classList.remove('hidden');
}

function closeShortcutsModal() {
    document.getElementById('shortcuts-modal').classList.add('hidden');
}

// Add shortcuts button to header if it doesn't exist
document.addEventListener('DOMContentLoaded', function() {
    // Add keyboard shortcuts help button to header
    const headerRight = document.querySelector('.header-right');
    if (headerRight && !document.querySelector('.shortcuts-trigger')) {
        const shortcutsBtn = document.createElement('button');
        shortcutsBtn.className = 'header-action';
        shortcutsBtn.onclick = openShortcutsModal;
        shortcutsBtn.title = 'Keyboard Shortcuts (Press ?)';
        shortcutsBtn.innerHTML = '<i class="fas fa-keyboard"></i>';
        headerRight.insertBefore(shortcutsBtn, headerRight.firstChild);
    }
});

// Close modal when clicking outside
document.getElementById('shortcuts-modal')?.addEventListener('click', function(e) {
    if (e.target === this) closeShortcutsModal();
});
</script>