<?php
/**
 * Master Landing Layout — Laravel-style @yield in plain PHP
 * Usage:
 *   require_once 'layouts/sections.php';
 *   start_section('content'); ... end_section();
 *   include 'layouts/landing.php';
 */

require_once __DIR__ . '/sections.php';

$layoutAppName = $layoutAppName ?? (getenv('APP_NAME') ?: 'Jakababa POS');
$layoutBrand   = $layoutBrand   ?? '#f68b1e';
?>
<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php yield_section('title', htmlspecialchars($layoutAppName)); ?></title>
    <meta name="description" content="<?php yield_section('meta_description', 'All-in-one POS, inventory, e-commerce, and analytics for modern retailers.'); ?>">
    <link rel="stylesheet" href="../assets/css/app.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.5.1/css/all.min.css">
    <style>
        [x-cloak]{display:none!important;}
        :root { --brand-color: <?= $layoutBrand ?>; }
        .bg-brand { background-color: var(--brand-color) !important; }
        .text-brand { color: var(--brand-color) !important; }
        .border-brand { border-color: var(--brand-color) !important; }
        .hover\:text-brand:hover { color: var(--brand-color) !important; }
        .hover\:bg-brand:hover { background-color: var(--brand-color) !important; }
    </style>
    <?php yield_section('head_extra'); ?>
</head>
<body class="bg-white text-gray-900 antialiased font-sans" x-data="landingApp()">

<!-- NAVIGATION -->
<nav class="sticky top-0 z-50 bg-white/80  border-b">
    <div class="max-w-7xl mx-auto px-4">
        <div class="flex items-center justify-between h-16">
            <!-- Logo -->
            <a href="./" class="flex items-center gap-2 shrink-0">
                <div class="w-9 h-9 bg-brand rounded-lg flex items-center justify-center text-white font-bold">J</div>
                <span class="text-lg font-bold text-dark-800"><?= htmlspecialchars($layoutAppName) ?></span>
            </a>

            <!-- Desktop Nav -->
            <div class="hidden lg:flex items-center gap-8 text-sm font-medium text-gray-600">
                <!-- Products Dropdown -->
                <div class="relative" @mouseenter="productsOpen = true" @mouseleave="productsOpen = false">
                    <button class="flex items-center gap-1 hover:text-brand transition py-5">
                        Products
                        <svg class="w-3 h-3 transition-transform" :class="productsOpen ? 'rotate-180' : ''" fill="none" viewBox="0 0 14 8"><path d="M1 1l6 6 6-6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    </button>
                    <div x-show="productsOpen" x-cloak x-transition class="absolute top-full left-1/2 -translate-x-1/2 w-[520px] bg-white rounded-xl shadow-xl border border-gray-100 p-4 grid grid-cols-2 gap-2">
                        <template x-for="p in products" :key="p.id">
                            <a :href="p.url" class="flex items-center gap-3 p-3 rounded-lg hover:bg-gray-50 transition group"
                               @mouseenter="activePreview = p.image">
                                <div class="w-10 h-10 rounded-lg bg-brand/10 flex items-center justify-center text-brand group-hover:bg-brand group-hover:text-white transition">
                                    <i :class="p.icon"></i>
                                </div>
                                <div>
                                    <div class="font-semibold text-sm text-dark-800" x-text="p.name"></div>
                                </div>
                            </a>
                        </template>
                        <div class="col-span-2 mt-2 p-3 bg-gray-50 rounded-lg flex items-center justify-center h-32">
                            <img :src="activePreview || products[0].image" class="max-h-full rounded object-contain" alt="Product preview">
                        </div>
                    </div>
                </div>
                <a href="pricing.php" class="hover:text-brand transition">Pricing</a>
                <a href="contact.php" class="hover:text-brand transition">About Us</a>
                <a href="store/index.php" class="hover:text-brand transition">Demo Store</a>
            </div>

            <!-- Desktop Actions -->
            <div class="hidden lg:flex items-center gap-3">
                <a href="auth/login.php" class="text-sm font-medium text-gray-600 hover:text-brand transition">Sign In</a>
                <a href="auth/tenant-signup.php" class="bg-brand hover:bg-brand-dark text-white text-sm font-semibold px-5 py-2 rounded-lg transition">Get Started</a>
            </div>

            <!-- Mobile Toggle -->
            <button @click="mobileOpen = !mobileOpen" class="lg:hidden p-2 text-gray-600">
                <i class="fas fa-bars text-xl" x-show="!mobileOpen"></i>
                <i class="fas fa-times text-xl" x-show="mobileOpen" x-cloak></i>
            </button>
        </div>
    </div>

    <!-- Mobile Menu -->
    <div x-show="mobileOpen" x-cloak x-transition class="lg:hidden border-t bg-white">
        <div class="max-w-7xl mx-auto px-4 py-4 space-y-3">
            <a href="pricing.php" @click="mobileOpen=false" class="block text-sm font-medium text-gray-600 py-2">Pricing</a>
            <a href="contact.php" @click="mobileOpen=false" class="block text-sm font-medium text-gray-600 py-2">About Us</a>
            <a href="store/index.php" @click="mobileOpen=false" class="block text-sm font-medium text-gray-600 py-2">Demo Store</a>
            <div class="pt-3 border-t flex flex-col gap-2">
                <a href="auth/login.php" class="text-center text-sm font-medium text-gray-600 py-2">Sign In</a>
                <a href="auth/tenant-signup.php" class="block text-center bg-brand text-white text-sm font-semibold px-5 py-2.5 rounded-lg">Get Started</a>
            </div>
        </div>
    </div>
</nav>

<!-- PAGE CONTENT -->
<main>
    <?php yield_section('content'); ?>
</main>

<!-- FOOTER -->
<footer class="bg-dark-900 text-white border-t border-dark-700">
    <div class="max-w-7xl mx-auto px-4 py-12 grid grid-cols-1 md:grid-cols-4 gap-8">
        <div>
            <div class="flex items-center gap-2 mb-3">
                <div class="w-8 h-8 bg-brand rounded-lg flex items-center justify-center text-white font-bold text-sm">J</div>
                <span class="font-bold"><?= htmlspecialchars($layoutAppName) ?></span>
            </div>
            <p class="text-sm text-gray-400">Smart retail technology for modern businesses.</p>
        </div>
        <div>
            <h4 class="font-bold mb-3">Product</h4>
            <ul class="space-y-2 text-sm text-gray-400">
                <li><a href="#features" class="hover:text-brand transition">Features</a></li>
                <li><a href="pricing.php" class="hover:text-brand transition">Pricing</a></li>
                <li><a href="store/index.php" class="hover:text-brand transition">Demo Store</a></li>
            </ul>
        </div>
        <div>
            <h4 class="font-bold mb-3">Account</h4>
            <ul class="space-y-2 text-sm text-gray-400">
                <li><a href="auth/login.php" class="hover:text-brand transition">Log In</a></li>
                <li><a href="auth/tenant-signup.php" class="hover:text-brand transition">Sign Up</a></li>
            </ul>
        </div>
        <div>
            <h4 class="font-bold mb-3">Support</h4>
            <ul class="space-y-2 text-sm text-gray-400">
                <li><a href="contact.php" class="hover:text-brand transition">Contact Us</a></li>
                <li><a href="landing/feature-roadmap.html" class="hover:text-brand transition">Roadmap</a></li>
            </ul>
        </div>
    </div>
    <div class="border-t border-dark-700 py-4 text-center text-sm text-gray-500">
        &copy; <?= date('Y') ?> <?= htmlspecialchars($layoutAppName) ?>. All rights reserved.
    </div>
</footer>

<!-- Floating Buttons -->
<div class="fixed bottom-6 right-6 z-50 flex flex-col gap-3 items-end">
    <div x-show="callOpen" x-cloak x-transition class="bg-dark-800 text-white rounded-lg px-4 py-2 shadow-lg flex items-center gap-2 mb-1">
        <span class="text-sm font-medium">+254 709 000 116</span>
        <button onclick="navigator.clipboard.writeText('+254709000116')" class="text-gray-400 hover:text-white"><i class="fas fa-copy text-xs"></i></button>
    </div>
    <button @click="callOpen = !callOpen" class="w-12 h-12 bg-green-600 hover:bg-green-700 rounded-full flex items-center justify-center text-white shadow-lg transition" title="Call Us">
        <i class="fas fa-phone"></i>
    </button>
    <a href="https://wa.me/254709000116" target="_blank" class="w-12 h-12 bg-green-500 hover:bg-green-600 rounded-full flex items-center justify-center text-white shadow-lg transition" title="Chat on WhatsApp">
        <i class="fab fa-whatsapp text-xl"></i>
    </a>
</div>

<script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
<script>
function landingApp() {
    return {
        mobileOpen: false,
        productsOpen: false,
        callOpen: false,
        activePreview: '',
        products: [
            { id:'pos', name:'Point of Sale', icon:'fas fa-cash-register', url:'#products', image:'assets/img/pos-preview.png' },
            { id:'erp', name:'ERP System', icon:'fas fa-boxes', url:'#products', image:'assets/img/erp-preview.png' },
            { id:'store', name:'Online Store', icon:'fas fa-shopping-bag', url:'store/index.php', image:'assets/img/store-preview.png' },
            { id:'ai', name:'AI Assistant', icon:'fas fa-robot', url:'#ai', image:'assets/img/ai-preview.png' },
        ]
    }
}
</script>
<?php yield_section('scripts'); ?>
</body>
</html>
