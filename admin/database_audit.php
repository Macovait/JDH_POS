<?php
/**
 * Database Audit Script
 * Checks existing tables and compares with expected schema
 */

require_once __DIR__ . '/bootstrap.php';

// Get database connection
$pdo = get_db_connection();

// Get all expected tables from migration files
$expected_tables = [
    // Core tables (should already exist)
    'users', 'roles', 'permissions', 'role_permissions', 'companies', 'branches',
    'products', 'categories', 'customers', 'sales', 'sale_items', 'inventory',
    'suppliers', 'purchases', 'invoices', 'subscriptions', 'tax_rates',
    
    // Cash Management
    'cash_drawers', 'cash_movements',
    
    // Inventory Management
    'inventory_adjustments', 'inventory_counts', 'stock_alerts', 'units', 'unit_conversions',
    
    // Purchasing Module
    'purchase_items', 'purchase_returns', 'product_suppliers', 'supplier_products',
    
    // Customer Management
    'customer_loyalty', 'loyalty_rules', 'customer_labels', 'loyalty_transactions',
    
    // Invoice & Billing
    'invoice_items', 'subscription_features', 'subscription_invoices', 'gift_cards',
    
    // E-Commerce / Shop
    'shop_settings', 'shop_products', 'shop_orders',
    
    // Delivery & Logistics
    'delivery_areas', 'delivery_routes', 'driver_locations',
    
    // Product Management
    'product_labels', 'product_price_history', 'product_sync_log',
    
    // POS & Terminal
    'terminal_devices', 'pos_settings', 'pos_tabs', 'pos_tab_items', 'pos_kot_jobs',
    
    // Restaurant
    'restaurant_tables',
    
    // HR & Scheduling
    'time_clock', 'shifts',
    
    // Reservations
    'reservations', 'reminders',
    
    // System & Utility
    'migrations', 'failed_jobs', 'webhook_retries', 'user_permissions',
    'job_queue', 'sync_queue', 'tenant_configs',
    
    // Support & Admin
    'support_access_logs', 'support_access_sessions',
    
    // Marketing
    'marketing_campaigns', 'campaign_messages',
    
    // Payments
    'payment_gateways', 'payment_transactions', 'currencies', 'exchange_rates',
    
    // Label Printing
    'label_templates',
    
    // Security & Rate Limiting
    'rate_limit_blocks',
    
    // Offline Sync
    'offline_transactions', 'offline_products', 'offline_customers', 'offline_settings',
    'conflict_log',
    
    // Reports
    'custom_reports',
    
    // Users
    'user_notes', 'user_password_history', 'user_sessions',
    
    // Company/Business
    'company_verticals', 'company_permissions', 'business_types',
    
    // Permissions
    'permission_groups',
    
    // Branch
    'branch_hours',
];

// Get actual tables from database
$stmt = $pdo->query("SHOW TABLES");
$actual_tables = $stmt->fetchAll(PDO::FETCH_COLUMN);

// Find missing tables
$missing_tables = array_diff($expected_tables, $actual_tables);

// Find extra tables (in db but not in expected list)
$extra_tables = array_diff($actual_tables, $expected_tables);

$page_title = 'Database Audit';
$current_page = 'database_audit';
ob_start();

$categories = [
    'cash_drawers' => 'Cash Management', 'cash_movements' => 'Cash Management',
    'inventory_adjustments' => 'Inventory', 'inventory_counts' => 'Inventory',
    'stock_alerts' => 'Inventory', 'units' => 'Inventory', 'unit_conversions' => 'Inventory',
    'purchase_items' => 'Purchasing', 'purchase_returns' => 'Purchasing',
    'product_suppliers' => 'Purchasing', 'supplier_products' => 'Purchasing',
    'customer_loyalty' => 'Customers', 'loyalty_rules' => 'Customers',
    'customer_labels' => 'Customers', 'loyalty_transactions' => 'Customers',
    'invoice_items' => 'Billing', 'subscription_features' => 'Billing',
    'subscription_invoices' => 'Billing', 'gift_cards' => 'Billing',
    'shop_settings' => 'E-Commerce', 'shop_products' => 'E-Commerce', 'shop_orders' => 'E-Commerce',
    'delivery_areas' => 'Logistics', 'delivery_routes' => 'Logistics', 'driver_locations' => 'Logistics',
    'product_labels' => 'Products', 'product_price_history' => 'Products', 'product_sync_log' => 'Products',
    'terminal_devices' => 'POS', 'pos_settings' => 'POS', 'pos_tabs' => 'POS',
    'pos_tab_items' => 'POS', 'pos_kot_jobs' => 'POS', 'restaurant_tables' => 'POS',
    'time_clock' => 'HR', 'shifts' => 'HR',
    'reservations' => 'Reservations', 'reminders' => 'Reservations',
    'migrations' => 'System', 'failed_jobs' => 'System', 'webhook_retries' => 'System',
    'user_permissions' => 'System', 'job_queue' => 'System', 'sync_queue' => 'System',
    'tenant_configs' => 'System', 'support_access_logs' => 'Support',
    'support_access_sessions' => 'Support', 'marketing_campaigns' => 'Marketing',
    'campaign_messages' => 'Marketing', 'payment_gateways' => 'Payments',
    'payment_transactions' => 'Payments', 'currencies' => 'Payments', 'exchange_rates' => 'Payments',
    'label_templates' => 'Labels', 'rate_limit_blocks' => 'Security',
    'offline_transactions' => 'Offline', 'offline_products' => 'Offline',
    'offline_customers' => 'Offline', 'offline_settings' => 'Offline',
    'conflict_log' => 'Offline', 'custom_reports' => 'Reports',
    'user_notes' => 'Users', 'user_password_history' => 'Users', 'user_sessions' => 'Users',
    'company_verticals' => 'Company', 'company_permissions' => 'Company', 'business_types' => 'Company',
    'permission_groups' => 'Permissions', 'branch_hours' => 'Branch',
];
?>

<!-- Page Header -->
<div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-5">
    <div>
        <h1 class="text-lg font-bold text-white flex items-center gap-2">
            <i class="fas fa-database text-amber-400"></i> Database Audit Report
        </h1>
        <p class="text-sm text-slate-500 mt-0.5">Database: <span class="text-slate-300">jdh_pos</span> | Generated: <?= date('Y-m-d H:i:s') ?></p>
    </div>
    <div class="flex items-center gap-2 shrink-0">
        <?php if (count($missing_tables) > 0): ?>
        <a href="run_migrations.php" onclick="return confirm('This will create all missing tables. Continue?')" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-amber-500/10 border border-amber-500/30 text-amber-400 text-sm font-medium hover:bg-amber-500/20 transition-colors">
            <i class="fas fa-play text-xs"></i> Run Migrations
        </a>
        <?php endif; ?>
    </div>
</div>

<!-- Summary Cards -->
<div class="grid grid-cols-3 gap-2 mb-5">
    <div class="bg-slate-800/50 border border-slate-700/60 rounded-xl p-3 flex items-center gap-2.5">
        <div class="w-8 h-8 rounded-lg bg-emerald-500/10 flex items-center justify-center shrink-0">
            <i class="fas fa-check text-emerald-400 text-xs"></i>
        </div>
        <div class="min-w-0">
            <div class="text-sm font-bold text-emerald-400"><?= count($actual_tables) ?></div>
            <div class="text-xs text-slate-500 leading-none mt-0.5">Existing Tables</div>
        </div>
    </div>
    <div class="bg-slate-800/50 border border-slate-700/60 rounded-xl p-3 flex items-center gap-2.5">
        <div class="w-8 h-8 rounded-lg bg-<?= count($missing_tables) === 0 ? 'emerald' : 'red' ?>-500/10 flex items-center justify-center shrink-0">
            <i class="fas fa-<?= count($missing_tables) === 0 ? 'check' : 'times' ?> text-<?= count($missing_tables) === 0 ? 'emerald' : 'red' ?>-400 text-xs"></i>
        </div>
        <div class="min-w-0">
            <div class="text-sm font-bold text-<?= count($missing_tables) === 0 ? 'emerald' : 'red' ?>-400"><?= count($missing_tables) ?></div>
            <div class="text-xs text-slate-500 leading-none mt-0.5">Missing Tables</div>
        </div>
    </div>
    <div class="bg-slate-800/50 border border-slate-700/60 rounded-xl p-3 flex items-center gap-2.5">
        <div class="w-8 h-8 rounded-lg bg-<?= count($extra_tables) === 0 ? 'emerald' : 'amber' ?>-500/10 flex items-center justify-center shrink-0">
            <i class="fas fa-<?= count($extra_tables) === 0 ? 'check' : 'exclamation' ?> text-<?= count($extra_tables) === 0 ? 'emerald' : 'amber' ?>-400 text-xs"></i>
        </div>
        <div class="min-w-0">
            <div class="text-sm font-bold text-<?= count($extra_tables) === 0 ? 'emerald' : 'amber' ?>-400"><?= count($extra_tables) ?></div>
            <div class="text-xs text-slate-500 leading-none mt-0.5">Extra Tables</div>
        </div>
    </div>
</div>

<!-- Missing Tables -->
<?php if (count($missing_tables) > 0): ?>
<div class="bg-slate-800/40 border border-slate-700/60 rounded-xl overflow-hidden mb-5">
    <div class="px-3 py-2.5 border-b border-slate-700/60 bg-slate-800/60">
        <h2 class="text-sm font-semibold text-white">Missing Tables (<?= count($missing_tables) ?>)</h2>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead>
                <tr class="border-b border-slate-700/60 bg-slate-800/60">
                    <th class="px-3 py-2.5 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">#</th>
                    <th class="px-3 py-2.5 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Table Name</th>
                    <th class="px-3 py-2.5 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Category</th>
                    <th class="px-3 py-2.5 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Status</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-700/40">
                <?php $i = 1; foreach ($missing_tables as $table): $category = $categories[$table] ?? 'Other'; ?>
                <tr class="hover:bg-slate-700/30 transition-colors">
                    <td class="px-3 py-2.5 text-sm text-slate-400"><?= $i++ ?></td>
                    <td class="px-3 py-2.5 text-sm font-mono text-amber-400"><?= htmlspecialchars($table) ?></td>
                    <td class="px-3 py-2.5 text-sm text-slate-400"><?= htmlspecialchars($category) ?></td>
                    <td class="px-3 py-2.5"><span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-red-500/15 text-red-400 ring-1 ring-red-500/30">MISSING</span></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php else: ?>
<div class="mb-5 flex items-center gap-2 px-3 py-2 rounded-lg bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 text-sm">
    <i class="fas fa-check-circle"></i> All expected tables exist!
</div>
<?php endif; ?>

<!-- Extra Tables -->
<?php if (count($extra_tables) > 0): ?>
<div class="bg-slate-800/40 border border-slate-700/60 rounded-xl overflow-hidden mb-5">
    <div class="px-3 py-2.5 border-b border-slate-700/60 bg-slate-800/60">
        <h2 class="text-sm font-semibold text-white">Extra Tables (<?= count($extra_tables) ?>)</h2>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead>
                <tr class="border-b border-slate-700/60 bg-slate-800/60">
                    <th class="px-3 py-2.5 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">#</th>
                    <th class="px-3 py-2.5 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Table Name</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-700/40">
                <?php $i = 1; foreach ($extra_tables as $table): ?>
                <tr class="hover:bg-slate-700/30 transition-colors">
                    <td class="px-3 py-2.5 text-sm text-slate-400"><?= $i++ ?></td>
                    <td class="px-3 py-2.5 text-sm font-mono text-slate-300"><?= htmlspecialchars($table) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<!-- All Tables Status -->
<div class="bg-slate-800/40 border border-slate-700/60 rounded-xl overflow-hidden">
    <div class="px-3 py-2.5 border-b border-slate-700/60 bg-slate-800/60">
        <h2 class="text-sm font-semibold text-white">All Tables Status</h2>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead>
                <tr class="border-b border-slate-700/60 bg-slate-800/60">
                    <th class="px-3 py-2.5 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">#</th>
                    <th class="px-3 py-2.5 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Table Name</th>
                    <th class="px-3 py-2.5 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Status</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-700/40">
                <?php 
                $i = 1;
                sort($expected_tables);
                foreach ($expected_tables as $table): 
                    $exists = in_array($table, $actual_tables);
                ?>
                <tr class="hover:bg-slate-700/30 transition-colors">
                    <td class="px-3 py-2.5 text-sm text-slate-400"><?= $i++ ?></td>
                    <td class="px-3 py-2.5 text-sm font-mono text-slate-300"><?= htmlspecialchars($table) ?></td>
                    <td class="px-3 py-2.5">
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium <?= $exists ? 'bg-emerald-500/15 text-emerald-400 ring-1 ring-emerald-500/30' : 'bg-red-500/15 text-red-400 ring-1 ring-red-500/30' ?>">
                            <?= $exists ? 'EXISTS' : 'MISSING' ?>
                        </span>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php
$page_content = ob_get_clean();
require_once __DIR__ . '/layouts/super_admin.php';
?>
