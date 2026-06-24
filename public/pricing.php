<?php

// Branch filter for multi-tenant isolation
$current_branch_id = get_current_branch_id();
// Jakababa POS - Pricing Page
require_once __DIR__ . '/../config/config.php';

// Load brand colors from config (with defaults)
$brandPrimary = getenv('BRAND_PRIMARY') ?: '#1E3A8A';
$brandPrimaryDark = getenv('BRAND_PRIMARY_DARK') ?: '#0F2B5E';
$brandPrimaryLight = getenv('BRAND_PRIMARY_LIGHT') ?: '#3B82F6';
$brandAccent = getenv('BRAND_ACCENT') ?: '#FBBF24';
$brandAccentDark = getenv('BRAND_ACCENT_DARK') ?: '#F59E0B';
$appName = getenv('APP_NAME') ?: 'Jakababa POS';
$appUrl = getenv('APP_URL') ?: 'http://localhost/JDH_POS/public/';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pricing - <?= htmlspecialchars($appName) ?></title>
    <link rel="stylesheet" href="assets/css/app.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.5.1/css/all.min.css">
    <style>
        :root {
            --brand-primary:
                <?= $brandPrimary ?>
            ;
            --brand-primary-dark:
                <?= $brandPrimaryDark ?>
            ;
            --brand-primary-light:
                <?= $brandPrimaryLight ?>
            ;
            --brand-accent:
                <?= $brandAccent ?>
            ;
            --brand-accent-dark:
                <?= $brandAccentDark ?>
            ;
            --brand-dark: #101828;
        }

        body {
            background: #f7f9fb;
            color: #111827;
        }

        .nav-link:hover {
            color: var(--brand-primary);
        }

        .brand-btn {
            background: var(--brand-primary);
            color: white;
        }

        .brand-btn:hover {
            background: var(--brand-primary-dark);
        }

        .outline-btn {
            border: 1.5px solid var(--brand-primary);
            color: var(--brand-primary);
        }

        .outline-btn:hover {
            background: var(--brand-primary);
            color: white;
        }

        .accent-btn {
            background: var(--brand-accent);
            color: #111827;
        }

        .accent-btn:hover {
            background: var(--brand-accent-dark);
        }

        /* Glassmorphism */
        .glass {
            background: rgba(255, 255, 255, 0.25);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.18);
        }

        .bg-slate-800/40 border border-slate-700/60 rounded-xl {
            background: rgba(255, 255, 255, 0.3);
            backdrop-filter: blur(15px);
            -webkit-backdrop-filter: blur(15px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            box-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.15);
        }

        .pricing-card {
            transition: transform 0.3s, box-shadow 0.3s;
        }

        .pricing-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.15);
        }

        .popular-badge {
            background: linear-gradient(135deg, var(--brand-accent), var(--brand-accent-dark));
            color: #111827;
        }

        /* Feature filtering styles */
        .feature-row {
            cursor: pointer;
            transition: background-color 0.2s;
        }

        .feature-row:hover {
            background-color: rgba(59, 130, 246, 0.1);
        }

        .feature-row.highlighted {
            background-color: rgba(59, 130, 246, 0.2);
        }

        .feature-row.highlighted td:first-child {
            font-weight: 600;
            color: var(--brand-primary);
        }

        .plan-column.highlighted {
            background-color: rgba(16, 185, 129, 0.1);
        }

        .filter-badge {
            display: inline-block;
            background: var(--brand-primary);
            color: white;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 11px;
            margin-left: 8px;
            cursor: pointer;
        }

        .filter-badge:hover {
            background: var(--brand-primary-dark);
        }

        .clear-filters {
            background: #ef4444;
            color: white;
            padding: 4px 12px;
            border-radius: 8px;
            font-size: 12px;
            cursor: pointer;
            border: none;
        }

        .clear-filters:hover {
            background: #dc2626;
        }
    </style>
</head>

<body>
    <!-- Navigation -->
    <header class="sticky top-0 glass z-40">
        <div class="max-w-6xl mx-auto px-4 py-4 flex items-center justify-between">
            <div class="flex items-center space-x-3">
                <a href="index.php" class="flex items-center space-x-3">
                    <img src="<?= htmlspecialchars($appUrl) ?>assets/img/logo.png" class="h-10" alt="<?= htmlspecialchars($appName) ?>">
                    <span class="font-bold text-xl"
                        style="color:var(--brand-primary)"><?= htmlspecialchars($appName) ?></span>
                </a>
            </div>
            <nav class="hidden md:flex items-center space-x-6 font-semibold text-gray-700">
                <a class="nav-link" href="index.php">Home</a>
                <a class="nav-link" href="index.php#modules">Modules</a>
                <a class="nav-link" href="index.php#features">Features</a>
                <a class="nav-link" href="pricing.php" style="color:var(--brand-primary)">Pricing</a>
                <a class="nav-link" href="index.php#faq">FAQs</a>
            </nav>
            <div class="hidden md:flex items-center space-x-3">
                <button class="outline-btn px-4 py-2 rounded-lg text-sm font-semibold"
                    onclick="window.location.href='auth/login.php'">Sign In</button>
                <button class="brand-btn px-4 py-2 rounded-lg text-sm font-semibold"
                    onclick="window.location.href='auth/register.php'">Get Started</button>
            </div>
            <button class="md:hidden" onclick="document.getElementById('mobileMenu').classList.toggle('hidden')">
                <svg width="28" height="22" fill="none" stroke="#111" stroke-width="2">
                    <path d="M1 1h26M1 11h26M1 21h26" />
                </svg>
            </button>
        </div>
        <div id="mobileMenu" class="md:hidden hidden border-t glass">
            <div class="px-4 py-3 space-y-2 font-semibold text-gray-700">
                <a class="block" href="index.php">Home</a>
                <a class="block" href="index.php#modules">Modules</a>
                <a class="block" href="index.php#features">Features</a>
                <a class="block" href="pricing.php" style="color:var(--brand-primary)">Pricing</a>
                <a class="block" href="index.php#faq">FAQs</a>
                <div class="pt-2 flex space-x-2">
                    <button class="outline-btn flex-1 px-4 py-2 rounded-lg text-sm font-semibold"
                        onclick="window.location.href='auth/login.php'">Sign In</button>
                    <button class="brand-btn flex-1 px-4 py-2 rounded-lg text-sm font-semibold"
                        onclick="window.location.href='auth/register.php'">Get Started</button>
                </div>
            </div>
        </div>
    </header>

    <!-- Hero Section -->
    <section class="bg-gradient-to-br from-blue-600 via-blue-700 to-indigo-800 text-white py-20">
        <div class="max-w-6xl mx-auto px-4 text-center">
            <h1 class="text-4xl md:text-5xl font-bold mb-6">Simple, Transparent Pricing</h1>
            <p class="text-xl text-blue-100 max-w-2xl mx-auto">Choose the perfect plan for your business. All plans
                include a free trial with no credit card required.</p>
        </div>
    </section>

    <!-- Pricing Cards -->
    <section class="max-w-6xl mx-auto px-4 py-16 -mt-10">
        <div class="grid md:grid-cols-2 lg:grid-cols-4 gap-3">
            <?php

// Branch filter for multi-tenant isolation
$current_branch_id = get_current_branch_id();
            $plans = [
                [
                    'name' => 'Free',
                    'price' => 'KES 0',
                    'period' => '/month',
                    'desc' => 'Perfect for getting started',
                    'users' => '2 users',
                    'branches' => '1 branch',
                    'products' => '50 products',
                    'features' => [
                        'Point of Sale',
                        'Basic Inventory',
                        'Customer Management',
                        'Basic Reports',
                        'Email Support'
                    ],
                    'highlighted' => false,
                    'cta' => 'Start Free'
                ],
                [
                    'name' => 'Starter',
                    'price' => 'KES 999',
                    'period' => '/month',
                    'desc' => 'For small businesses',
                    'users' => '5 users',
                    'branches' => '2 branches',
                    'products' => '200 products',
                    'features' => [
                        'All Free features',
                        'Advanced Inventory',
                        'Purchase Management',
                        'Sales Reports',
                        'Multi-branch Support',
                        'Priority Email Support'
                    ],
                    'highlighted' => false,
                    'cta' => 'Get Started'
                ],
                [
                    'name' => 'Professional',
                    'price' => 'KES 2,499',
                    'period' => '/month',
                    'desc' => 'For growing businesses',
                    'users' => '15 users',
                    'branches' => '5 branches',
                    'products' => '1,000 products',
                    'features' => [
                        'All Starter features',
                        'API Access',
                        'Advanced Reports',
                        'Customer Loyalty',
                        'Discounts & Vouchers',
                        'Priority Support',
                        'Custom Integrations'
                    ],
                    'highlighted' => true,
                    'cta' => 'Get Started'
                ],
                [
                    'name' => 'Enterprise',
                    'price' => 'KES 4,999',
                    'period' => '/month',
                    'desc' => 'For large organizations',
                    'users' => 'Unlimited users',
                    'branches' => 'Unlimited branches',
                    'products' => 'Unlimited products',
                    'features' => [
                        'All Professional features',
                        'Dedicated Account Manager',
                        'Custom Development',
                        'SLA Guarantee',
                        'On-site Training',
                        '24/7 Phone Support',
                        'White-label Options'
                    ],
                    'highlighted' => false,
                    'cta' => 'Contact Sales'
                ],
            ];
            foreach ($plans as $plan): ?>
                <div
                    class="pricing-card bg-slate-800/40 border border-slate-700/60 rounded-xl rounded-xl p-6 <?php echo !empty($plan['highlighted']) ? 'ring-2 ring-blue-500' : ''; ?>">
                    <?php if (!empty($plan['highlighted'])): ?>
                        <div class="popular-badge text-xs font-bold px-3 py-1 rounded-full inline-block mb-4">Most Popular</div>
                    <?php endif; ?>
                    <h3 class="text-2xl font-bold text-gray-900 mb-2"><?= $plan['name'] ?></h3>
                    <div class="mb-4">
                        <span class="text-4xl font-bold" style="color:var(--brand-primary)"><?= $plan['price'] ?></span>
                        <span class="text-gray-500"><?= $plan['period'] ?></span>
                    </div>
                    <p class="text-gray-600 mb-6"><?= $plan['desc'] ?></p>

                    <div class="space-y-3 mb-6">
                        <div class="flex items-center space-x-2 text-sm">
                            <i class="fas fa-users text-gray-400"></i>
                            <span><?= $plan['users'] ?></span>
                        </div>
                        <div class="flex items-center space-x-2 text-sm">
                            <i class="fas fa-building text-gray-400"></i>
                            <span><?= $plan['branches'] ?></span>
                        </div>
                        <div class="flex items-center space-x-2 text-sm">
                            <i class="fas fa-box text-gray-400"></i>
                            <span><?= $plan['products'] ?></span>
                        </div>
                    </div>

                    <ul class="space-y-3 mb-8">
                        <?php foreach ($plan['features'] as $feature): ?>
                            <li class="flex items-start space-x-2 text-sm">
                                <svg class="w-5 h-5 text-green-500 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7">
                                    </path>
                                </svg>
                                <span><?= $feature ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>

                    <?php if ($plan['name'] === 'Enterprise'): ?>
                        <button class="outline-btn w-full py-3 rounded-lg font-semibold"
                            onclick="window.location.href='auth/register_company.php'">
                            <?= $plan['cta'] ?>
                        </button>
                    <?php else: ?>
                        <button class="brand-btn w-full py-3 rounded-lg font-semibold"
                            onclick="window.location.href='auth/register.php'">
                            <?= $plan['cta'] ?>
                        </button>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </section>

    <!-- Features Comparison -->
    <section class="max-w-6xl mx-auto px-4 py-16">
        <div class="text-center mb-10">
            <h2 class="text-3xl font-bold mb-4">Compare Plans</h2>
            <p class="text-gray-600">Click on any feature to highlight which plans include it</p>
            <div id="activeFilters" class="mt-4 hidden">
                <span class="text-sm text-gray-600">Active filters:</span>
                <div id="filterTags" class="inline-flex flex-wrap gap-2 ml-2"></div>
                <button class="clear-filters ml-4" onclick="clearAllFilters()">Clear All</button>
            </div>
        </div>
        <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl rounded-xl overflow-hidden">
            <table class="w-full" id="comparisonTable">
                <thead>
                    <tr class="border-b border-gray-200">
                        <th class="text-left p-4 font-semibold">Feature</th>
                        <th class="text-center p-4 font-semibold plan-col" data-plan="free">Free</th>
                        <th class="text-center p-4 font-semibold plan-col" data-plan="starter">Starter</th>
                        <th class="text-center p-4 font-semibold plan-col" data-plan="professional">Professional</th>
                        <th class="text-center p-4 font-semibold plan-col" data-plan="enterprise">Enterprise</th>
                    </tr>
                </thead>
                <tbody>
                    <?php

// Branch filter for multi-tenant isolation
$current_branch_id = get_current_branch_id();
                    $comparisons = [
                        ['Point of Sale', true, true, true, true],
                        ['Inventory Management', 'Basic', 'Advanced', 'Advanced', 'Advanced'],
                        ['Multi-branch Support', false, true, true, true],
                        ['Purchase Management', false, true, true, true],
                        ['Customer Loyalty', false, false, true, true],
                        ['API Access', false, false, true, true],
                        ['Advanced Reports', false, false, true, true],
                        ['Discounts & Vouchers', false, false, true, true],
                        ['Priority Support', false, false, true, true],
                        ['Custom Integrations', false, false, false, true],
                        ['Dedicated Account Manager', false, false, false, true],
                        ['SLA Guarantee', false, false, false, true],
                    ];
                    foreach ($comparisons as $row): ?>
                        <tr class="border-b border-gray-100 feature-row" data-feature="<?= htmlspecialchars($row[0]) ?>">
                            <td class="p-4 font-medium">
                                <?= $row[0] ?>
                                <span class="filter-badge hidden"
                                    onclick="removeFilter('<?= htmlspecialchars($row[0]) ?>')">×</span>
                            </td>
                            <td class="text-center p-4 plan-cell" data-plan="free">
                                <?php if ($row[1] === true): ?>
                                    <i class="fas fa-check text-green-500"></i>
                                <?php elseif ($row[1] === false): ?>
                                    <i class="fas fa-times text-gray-300"></i>
                                <?php else: ?>
                                    <span class="text-sm text-gray-600"><?= $row[1] ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center p-4 plan-cell" data-plan="starter">
                                <?php if ($row[2] === true): ?>
                                    <i class="fas fa-check text-green-500"></i>
                                <?php elseif ($row[2] === false): ?>
                                    <i class="fas fa-times text-gray-300"></i>
                                <?php else: ?>
                                    <span class="text-sm text-gray-600"><?= $row[2] ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center p-4 plan-cell" data-plan="professional">
                                <?php if ($row[3] === true): ?>
                                    <i class="fas fa-check text-green-500"></i>
                                <?php elseif ($row[3] === false): ?>
                                    <i class="fas fa-times text-gray-300"></i>
                                <?php else: ?>
                                    <span class="text-sm text-gray-600"><?= $row[3] ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center p-4 plan-cell" data-plan="enterprise">
                                <?php if ($row[4] === true): ?>
                                    <i class="fas fa-check text-green-500"></i>
                                <?php elseif ($row[4] === false): ?>
                                    <i class="fas fa-times text-gray-300"></i>
                                <?php else: ?>
                                    <span class="text-sm text-gray-600"><?= $row[4] ?></span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>

    <!-- FAQ -->
    <section class="max-w-4xl mx-auto px-4 py-16">
        <div class="text-center mb-10">
            <h2 class="text-3xl font-bold mb-4">Frequently Asked Questions</h2>
        </div>
        <div class="space-y-4">
            <?php

// Branch filter for multi-tenant isolation
$current_branch_id = get_current_branch_id();
            $faqs = [
                ['q' => 'Can I change plans later?', 'a' => 'Yes, you can upgrade or downgrade your plan at any time. Changes take effect immediately, and we\'ll prorate your billing.'],
                ['q' => 'Is there a free trial?', 'a' => 'Yes! All paid plans include a 14-day free trial. No credit card required to start.'],
                ['q' => 'What payment methods do you accept?', 'a' => 'We accept M-Pesa, credit/debit cards, and bank transfers. All payments are processed securely.'],
                ['q' => 'Can I cancel anytime?', 'a' => 'Absolutely. You can cancel your subscription at any time. You\'ll continue to have access until the end of your billing period.'],
                ['q' => 'Do you offer discounts for annual billing?', 'a' => 'Yes, annual billing saves you 20% compared to monthly billing.'],
            ];
            foreach ($faqs as $i => $faq): ?>
                <details class="bg-slate-800/40 border border-slate-700/60 rounded-xl rounded-xl p-4" <?= $i === 0 ? 'open' : ''; ?>>
                    <summary class="font-semibold cursor-pointer"><?= $faq['q'] ?></summary>
                    <p class="mt-2 text-gray-600"><?= $faq['a'] ?></p>
                </details>
            <?php endforeach; ?>
        </div>
    </section>

    <!-- CTA -->
    <section class="bg-gradient-to-r from-blue-600 to-indigo-700 text-white py-16">
        <div class="max-w-4xl mx-auto px-4 text-center">
            <h2 class="text-3xl font-bold mb-4">Ready to get started?</h2>
            <p class="text-blue-100 mb-8">Join thousands of businesses using <?= htmlspecialchars($appName) ?> to grow
                their operations.</p>
            <div class="flex flex-col sm:flex-row gap-4 justify-center">
                <button class="bg-white text-blue-600 px-8 py-3 rounded-lg font-semibold hover:bg-gray-100 transition"
                    onclick="window.location.href='auth/register.php'">
                    Start Free Trial
                </button>
                <button
                    class="border-2 border-white text-white px-8 py-3 rounded-lg font-semibold hover:bg-white/10 transition"
                    onclick="window.location.href='auth/login.php'">
                    Sign In
                </button>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="bg-white border-t py-10">
        <div class="max-w-6xl mx-auto px-4">
            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                <div class="flex items-center space-x-3">
                    <img src="<?= htmlspecialchars($appUrl) ?>assets/img/logo.png" class="h-10"
                        alt="<?= htmlspecialchars($appName) ?>">
                    <span class="font-bold text-lg"
                        style="color:var(--brand-primary)"><?= htmlspecialchars($appName) ?></span>
                </div>
                <div class="flex space-x-4 text-sm text-gray-700">
                    <a href="index.php" class="hover:text-blue-700">Home</a>
                    <a href="pricing.php" class="hover:text-blue-700">Pricing</a>
                    <a href="index.php#faq" class="hover:text-blue-700">FAQ</a>
                </div>
            </div>
            <div class="border-t mt-6 pt-6">
                <p class="text-sm text-gray-500 text-center">&copy; <?= date('Y') ?> <?= htmlspecialchars($appName) ?>.
                    All Rights Reserved.</p>
            </div>
        </div>
    </footer>

    <script>
        // Feature filtering functionality
        let activeFilters = new Set();

        // Add click handlers to feature rows
        document.querySelectorAll('.feature-row').forEach(row => {
            row.addEventListener('click', function (e) {
                // Don't trigger if clicking on the filter badge
                if (e.target.closest('.filter-badge')) {
                    e.stopPropagation();
                    return;
                }

                const feature = this.dataset.feature;
                toggleFilter(feature);
            });
        });

        function toggleFilter(feature) {
            if (activeFilters.has(feature)) {
                activeFilters.delete(feature);
            } else {
                activeFilters.add(feature);
            }
            updateFilters();
        }

        function removeFilter(feature) {
            activeFilters.delete(feature);
            updateFilters();
        }

        function clearAllFilters() {
            activeFilters.clear();
            updateFilters();
        }

        function updateFilters() {
            // Update filter tags display
            const filterTags = document.getElementById('filterTags');
            const activeFiltersDiv = document.getElementById('activeFilters');

            if (activeFilters.size > 0) {
                activeFiltersDiv.classList.remove('hidden');
                filterTags.innerHTML = '';
                activeFilters.forEach(feature => {
                    const tag = document.createElement('span');
                    tag.className = 'inline-flex items-center bg-blue-100 text-blue-800 text-xs px-2 py-1 rounded-full';
                    tag.innerHTML = `${feature} <button onclick="removeFilter('${feature}')" class="ml-1 text-blue-600 hover:text-blue-800">×</button>`;
                    filterTags.appendChild(tag);
                });
            } else {
                activeFiltersDiv.classList.add('hidden');
            }

            // Highlight feature rows
            document.querySelectorAll('.feature-row').forEach(row => {
                const feature = row.dataset.feature;
                const badge = row.querySelector('.filter-badge');

                if (activeFilters.has(feature)) {
                    row.classList.add('highlighted');
                    badge.classList.remove('hidden');
                } else {
                    row.classList.remove('highlighted');
                    badge.classList.add('hidden');
                }
            });

            // Highlight plan columns that have all selected features
            const planColumns = ['free', 'starter', 'professional', 'enterprise'];
            planColumns.forEach(plan => {
                const planCol = document.querySelector(`.plan-col[data-plan="${plan}"]`);
                const planCells = document.querySelectorAll(`.plan-cell[data-plan="${plan}"]`);

                if (activeFilters.size === 0) {
                    planCol.classList.remove('highlighted');
                    return;
                }

                let hasAllFeatures = true;
                activeFilters.forEach(feature => {
                    const featureRow = document.querySelector(`.feature-row[data-feature="${feature}"]`);
                    const cell = featureRow.querySelector(`.plan-cell[data-plan="${plan}"]`);
                    const hasCheck = cell.querySelector('.fa-check') !== null;
                    const cellText = cell.querySelector('span');
                    const hasFullFeature = hasCheck || (cellText && cellText.textContent.trim() !== 'Basic');

                    if (!hasFullFeature) {
                        hasAllFeatures = false;
                    }
                });

                if (hasAllFeatures) {
                    planCol.classList.add('highlighted');
                } else {
                    planCol.classList.remove('highlighted');
                }
            });
        }

        // Initialize with no filters
        updateFilters();
    </script>
</body>

</html>