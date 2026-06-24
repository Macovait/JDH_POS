<?php
/**
 * Footer - SaaS POS System
 * 
 * Footer component for Jakababa POS dashboard.
 * Multi-tenant with company isolation and subscription awareness.
 * 
 * @package JakababaPOS
 */

// Ensure session is started with correct name
if (session_status() === PHP_SESSION_NONE) {
    session_name('jakababa_saas_sid');
    session_start();
}

// Load paths for base_url() if not already loaded
if (!function_exists('base_url')) {
    require_once __DIR__ . '/../../src/paths.php';
}

// Get session data
$tenant_id = $_SESSION['tenant_id'] ?? 0;
$company_name = $_SESSION['tenant_name'] ?? 'Jakababa POS';
$current_year = date('Y');

// Get app version from config
$app_version = '2.0.0';
if (file_exists(__DIR__ . '/../../config/config.php')) {
    $config = require __DIR__ . '/../../config/config.php';
    $app_version = $config['app_version'] ?? '2.0.0';
}
?>

<!-- Footer -->
<footer class="mt-auto border-t border-slate-700 bg-slate-800/50">
    <div class="px-4 md:px-6 py-6">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-3">
            <!-- Company Info -->
            <div class="md:col-span-2">
                <div class="flex items-center gap-3 mb-3">
                    <div class="w-10 h-10 bg-amber-500 rounded-xl flex items-center justify-center">
                        <i class="fas fa-cubes text-slate-900 text-xl"></i>
                    </div>
                    <div>
                        <h3 class="text-lg font-bold text-white font-poppins">
                            <?php echo htmlspecialchars($company_name); ?></h3>
                        <p class="text-xs text-gray-400">SaaS POS System</p>
                    </div>
                </div>
                <p class="text-gray-400 text-sm mb-4 max-w-md">
                    Complete Point of Sale solution for modern businesses. Manage sales, inventory, customers, and more
                    with ease.
                </p>
                <div class="flex gap-2">
                    <span class="text-xs bg-amber-500/10 text-amber-400 px-2 py-1 rounded-full">SaaS Ready</span>
                    <span class="text-xs bg-emerald-500/10 text-emerald-400 px-2 py-1 rounded-full">PCI Compliant</span>
                    <span class="text-xs bg-blue-500/10 text-blue-400 px-2 py-1 rounded-full">Cloud Based</span>
                </div>
            </div>

            <!-- Quick Links -->
            <div>
                <h4 class="font-semibold text-white mb-3 flex items-center gap-2">
                    <i class="fas fa-link text-amber-400"></i>
                    Quick Links
                </h4>
                <ul class="space-y-2 text-sm">
                    <li>
                        <a href="<?php echo base_url('dashboard/home.php');?>"
                            class="text-gray-400 hover:text-amber-400 transition-colors flex items-center gap-2">
                            <i class="fas fa-home w-4"></i>
                            Dashboard
                        </a>
                    </li>
                    <li>
                        <a href="<?php echo base_url('pos/pos-enterprise.php');?>"
                            class="text-gray-400 hover:text-amber-400 transition-colors flex items-center gap-2">
                            <i class="fas fa-cash-register w-4"></i>
                            Point of Sale
                        </a>
                    </li>
                    <li>
                        <a href="<?php echo base_url('inventory/inventory.php');?>"
                            class="text-gray-400 hover:text-amber-400 transition-colors flex items-center gap-2">
                            <i class="fas fa-boxes w-4"></i>
                            Inventory
                        </a>
                    </li>
                    <li>
                        <a href="<?php echo base_url('reports/reports.php');?>"
                            class="text-gray-400 hover:text-amber-400 transition-colors flex items-center gap-2">
                            <i class="fas fa-chart-line w-4"></i>
                            Reports
                        </a>
                    </li>
                </ul>
            </div>

            <!-- Support -->
            <div>
                <h4 class="font-semibold text-white mb-3 flex items-center gap-2">
                    <i class="fas fa-headset text-amber-400"></i>
                    Support
                </h4>
                <ul class="space-y-2 text-sm">
                    <li>
                        <a href="<?php echo base_url('dashboard/settings.php');?>"
                            class="text-gray-400 hover:text-amber-400 transition-colors flex items-center gap-2">
                            <i class="fas fa-cog w-4"></i>
                            Settings
                        </a>
                    </li>
                    <li>
                        <a href="<?php echo base_url('dashboard/profile.php');?>"
                            class="text-gray-400 hover:text-amber-400 transition-colors flex items-center gap-2">
                            <i class="fas fa-user w-4"></i>
                            Profile
                        </a>
                    </li>
                    <li>
                        <a href="<?php echo base_url('dashboard/notifications.php');?>"
                            class="text-gray-400 hover:text-amber-400 transition-colors flex items-center gap-2">
                            <i class="fas fa-bell w-4"></i>
                            Notifications
                        </a>
                    </li>
                    <li>
                        <a href="<?php echo base_url('auth/logout.php');?>"
                            class="text-gray-400 hover:text-red-400 transition-colors flex items-center gap-2">
                            <i class="fas fa-sign-out-alt w-4"></i>
                            Logout
                        </a>
                    </li>
                </ul>
            </div>
        </div>

        <!-- Bottom Bar -->
        <div class="mt-6 pt-6 border-t border-slate-700">
            <div class="flex flex-col md:flex-row justify-between items-center gap-4">
                <p class="text-gray-400 text-sm">
                    <i class="far fa-copyright mr-1"></i> <?php echo $current_year; ?>
                    <?php echo htmlspecialchars($company_name); ?>. All rights reserved.
                </p>
                <div class="flex items-center gap-4">
                    <span class="text-gray-400 text-sm">Version <?php echo htmlspecialchars($app_version); ?></span>
                    <div class="flex gap-2">
                        <span class="w-2 h-2 bg-emerald-500 rounded-full animate-pulse"></span>
                        <span class="text-emerald-400 text-sm">System Online</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Scroll to Top Button -->
    <button onclick="scrollToTop()" id="scrollTopBtn"
        class="fixed bottom-6 right-6 w-12 h-12 bg-amber-500 rounded-full flex items-center justify-center shadow-lg opacity-0 invisible transition-all duration-300 hover:bg-amber-600 hover:scale-110 z-50">
        <i class="fas fa-arrow-up text-slate-900"></i>
    </button>
</footer>

<!-- Footer Styles -->
<style>
    footer {
        background: rgba(31, 41, 55, 0.5);
        backdrop-filter: blur(10px);
        -webkit-backdrop-filter: blur(10px);
    }

    #scrollTopBtn {
        box-shadow: 0 4px 15px rgba(251, 191, 36, 0.3);
    }

    #scrollTopBtn:hover {
        box-shadow: 0 6px 20px rgba(251, 191, 36, 0.4);
    }

    #scrollTopBtn.visible {
        opacity: 1;
        visibility: visible;
    }

    @keyframes pulse {

        0%,
        100% {
            opacity: 1;
        }

        50% {
            opacity: 0.5;
        }
    }

    .animate-pulse {
        animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
    }
</style>

<!-- Footer JavaScript -->
<script>
    // Scroll to top functionality
    function scrollToTop() {
        window.scrollTo({
            top: 0,
            behavior: 'smooth'
        });
    }

    // Show/hide scroll to top button
    window.addEventListener('scroll', function () {
        const btn = document.getElementById('scrollTopBtn');
        if (window.scrollY > 300) {
            btn.classList.add('visible');
        } else {
            btn.classList.remove('visible');
        }
    });

    // Check system status
    function checkSystemStatus() {
        // This would typically make an AJAX call to check system status
        // For now, we'll just show it as online
        const statusDot = document.querySelector('.animate-pulse');
        if (statusDot) {
            statusDot.classList.add('bg-emerald-500');
        }
    }

    // Run on load
    document.addEventListener('DOMContentLoaded', function () {
        checkSystemStatus();
    });
</script>

</body>
</html>