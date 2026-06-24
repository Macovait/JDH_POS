<?php
/**
 * Owner Panel Sidebar Component
 * 
 * Clean, organized navigation for the SaaS Owner Panel
 */
require_once __DIR__ . '/../bootstrap.php';

$current_page = $current_page ?? 'dashboard';
$admin_role = admin_current_role();
$admin_name = admin_current_name();

// Simple flat menu structure - easier to scan
$menu_items = [
    ['id' => 'dashboard', 'label' => 'Dashboard', 'icon' => 'fa-tachometer-alt', 'url' => 'dashboard.php'],
    ['id' => 'companies', 'label' => 'Companies', 'icon' => 'fa-building', 'url' => 'companies.php'],
    ['id' => 'onboarding', 'label' => 'Onboarding', 'icon' => 'fa-user-check', 'url' => 'onboarding.php'],
    ['id' => 'subscriptions', 'label' => 'Subscriptions', 'icon' => 'fa-credit-card', 'url' => 'subscriptions.php'],
    ['id' => 'subscription_history', 'label' => 'Sub History', 'icon' => 'fa-history', 'url' => 'subscription_history.php'],
    ['id' => 'dunning', 'label' => 'Dunning', 'icon' => 'fa-sync-alt', 'url' => 'dunning.php'],
    ['id' => 'plans', 'label' => 'Pricing Plans', 'icon' => 'fa-layer-group', 'url' => 'plans.php'],
    ['id' => 'features', 'label' => 'Features & Entitlements', 'icon' => 'fa-flag', 'url' => 'features.php'],
    ['id' => 'usage', 'label' => 'Usage Metering', 'icon' => 'fa-tachometer-alt', 'url' => 'usage.php'],
    ['id' => 'revenue', 'label' => 'Revenue & Payments', 'icon' => 'fa-coins', 'url' => 'revenue.php'],
    ['id' => 'credits', 'label' => 'Credits', 'icon' => 'fa-wallet', 'url' => 'credits.php'],
    ['id' => 'invoice_generator', 'label' => 'Invoice Generator', 'icon' => 'fa-file-invoice-dollar', 'url' => 'invoice_generator.php'],
    ['id' => 'analytics', 'label' => 'Analytics', 'icon' => 'fa-chart-line', 'url' => 'analytics.php'],
    ['id' => 'support_access', 'label' => 'Support Access', 'icon' => 'fa-headset', 'url' => 'support-access.php'],
    ['id' => 'support_tickets', 'label' => 'Support Tickets', 'icon' => 'fa-ticket-alt', 'url' => 'support_tickets.php'],
    ['id' => 'support_entry', 'label' => 'Enter Support Session', 'icon' => 'fa-sign-in-alt', 'url' => 'support-entry.php'],
    ['id' => 'audit_logs', 'label' => 'Audit Logs', 'icon' => 'fa-shield-alt', 'url' => 'audit_logs.php'],
    ['id' => 'security', 'label' => 'Security & Threats', 'icon' => 'fa-lock', 'url' => 'security.php'],
    ['id' => 'monitoring', 'label' => 'System Monitoring', 'icon' => 'fa-heartbeat', 'url' => 'monitoring.php'],
    ['id' => 'webhook_retries', 'label' => 'Webhook Retries', 'icon' => 'fa-plug', 'url' => 'webhook_retries.php'],
    ['id' => 'api_portal', 'label' => 'Developer Portal', 'icon' => 'fa-code', 'url' => 'api_portal.php'],
    ['id' => 'platform_health', 'label' => 'Platform Health', 'icon' => 'fa-stethoscope', 'url' => 'platform_health.php'],
    ['id' => 'email_templates', 'label' => 'Email Templates', 'icon' => 'fa-envelope', 'url' => 'email_templates.php'],
    ['id' => 'plugins', 'label' => 'Plugins', 'icon' => 'fa-puzzle-piece', 'url' => 'plugins.php'],
    ['id' => 'roles', 'label' => 'Admin Roles', 'icon' => 'fa-user-shield', 'url' => 'roles.php'],
    ['id' => 'notifications', 'label' => 'Notifications', 'icon' => 'fa-bell', 'url' => 'notifications.php'],
    ['id' => 'tenant_branding', 'label' => 'Tenant Branding', 'icon' => 'fa-paint-brush', 'url' => 'tenant_branding.php'],
    ['id' => 'bulk_operations', 'label' => 'Bulk Operations', 'icon' => 'fa-bolt', 'url' => 'bulk_operations.php'],
    ['id' => 'landing_editor', 'label' => 'Landing Editor', 'icon' => 'fa-file-code', 'url' => 'landing_editor.php'],
    ['id' => 'settings', 'label' => 'Settings', 'icon' => 'fa-cog', 'url' => 'settings.php'],
];
?>
<aside class="fixed left-0 top-0 h-full w-64 bg-slate-900 border-r border-slate-700/60 z-50 overflow-y-auto">
    <div class="flex items-center gap-3 px-6 py-4 border-b border-slate-700/60">
        <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-amber-500 to-orange-600 flex items-center justify-center">
            <i class="fas fa-crown text-white text-sm"></i>
        </div>
        <div>
            <h1 class="text-white font-bold text-lg">JDH POS</h1>
            <p class="text-slate-500 text-xs"><?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $admin_role))); ?></p>
        </div>
    </div>

    <nav class="p-4 space-y-1">
        <?php foreach ($menu_items as $item):
            $is_active = $current_page === $item['id'];
            $base_classes = 'flex items-center gap-3 px-4 py-2.5 rounded-lg text-sm font-medium transition';
            $active_classes = $is_active
                ? 'bg-amber-500/10 text-amber-400 border border-amber-500/20'
                : 'text-slate-400 hover:text-white hover:bg-slate-700/30';
        ?>
        <a href="<?php echo admin_url($item['url']); ?>" class="<?php echo $base_classes . ' ' . $active_classes; ?>">
            <i class="fas <?php echo $item['icon']; ?> w-5 text-center"></i>
            <?php echo $item['label']; ?>
        </a>
        <?php endforeach; ?>
    </nav>

    <div class="absolute bottom-0 left-0 right-0 p-4 border-t border-slate-700/60">
        <a href="<?php echo admin_url('logout.php'); ?>" class="flex items-center gap-3 px-4 py-2.5 rounded-lg text-sm font-medium text-red-400 hover:bg-red-500/10 transition">
            <i class="fas fa-sign-out-alt w-5"></i>
            Logout
        </a>
    </div>
</aside>
