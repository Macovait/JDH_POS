<?php
/**
 * CRM Dashboard - Customer Relationship Management
 * Overview of leads, deals, and customer interactions.
 * Pure Tailwind CSS
 */

$page_title = 'CRM Dashboard';
ob_start();

$pathsFile = __DIR__ . '/../../src/paths.php';
if (!file_exists($pathsFile)) {
    $pathsFile = dirname(__DIR__, 3) . '/src/paths.php';
}
require_once $pathsFile;

safe_require('auth.php', 'src', true);
require_login();
safe_require('db.php', 'src', true);
safe_require('functions.php', 'src', true);

$pdo = get_db_connection();
$user_id = get_current_user_id();
$tenant_id = get_current_tenant_id();
$branch_id = get_current_branch_id();

// Initialize stats
$stats = [
    'total_leads' => 0,
    'new_leads' => 0,
    'contacted_leads' => 0,
    'qualified_leads' => 0,
    'converted_leads' => 0,
    'total_deals' => 0,
    'open_deals' => 0,
    'negotiating_deals' => 0,
    'won_deals' => 0,
    'lost_deals' => 0,
    'total_pipeline_value' => 0
];

$recent_leads = [];
$recent_deals = [];

try {
    // Lead stats
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM crm_leads WHERE tenant_id = ?");
    $stmt->execute([$tenant_id]);
    $stats['total_leads'] = (int) $stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM crm_leads WHERE tenant_id = ? AND status = 'new'");
    $stmt->execute([$tenant_id]);
    $stats['new_leads'] = (int) $stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM crm_leads WHERE tenant_id = ? AND status = 'contacted'");
    $stmt->execute([$tenant_id]);
    $stats['contacted_leads'] = (int) $stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM crm_leads WHERE tenant_id = ? AND status = 'qualified'");
    $stmt->execute([$tenant_id]);
    $stats['qualified_leads'] = (int) $stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM crm_leads WHERE tenant_id = ? AND status = 'converted'");
    $stmt->execute([$tenant_id]);
    $stats['converted_leads'] = (int) $stmt->fetchColumn();

    // Deal stats
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM crm_deals WHERE tenant_id = ?");
    $stmt->execute([$tenant_id]);
    $stats['total_deals'] = (int) $stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM crm_deals WHERE tenant_id = ? AND status = 'open'");
    $stmt->execute([$tenant_id]);
    $stats['open_deals'] = (int) $stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM crm_deals WHERE tenant_id = ? AND status = 'negotiating'");
    $stmt->execute([$tenant_id]);
    $stats['negotiating_deals'] = (int) $stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM crm_deals WHERE tenant_id = ? AND status = 'won'");
    $stmt->execute([$tenant_id]);
    $stats['won_deals'] = (int) $stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM crm_deals WHERE tenant_id = ? AND status = 'lost'");
    $stmt->execute([$tenant_id]);
    $stats['lost_deals'] = (int) $stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COALESCE(SUM(value), 0) FROM crm_deals WHERE tenant_id = ? AND status IN ('open', 'negotiating')");
    $stmt->execute([$tenant_id]);
    $stats['total_pipeline_value'] = (float) $stmt->fetchColumn();

    // Recent leads
    $stmt = $pdo->prepare("
        SELECT * FROM crm_leads 
        WHERE tenant_id = ? 
        ORDER BY created_at DESC 
        LIMIT 10
    ");
    $stmt->execute([$tenant_id]);
    $recent_leads = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Recent deals
    $stmt = $pdo->prepare("
        SELECT d.*, l.name as lead_name 
        FROM crm_deals d
        LEFT JOIN crm_leads l ON d.lead_id = l.id
        WHERE d.tenant_id = ?
        ORDER BY d.updated_at DESC
        LIMIT 10
    ");
    $stmt->execute([$tenant_id]);
    $recent_deals = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log("Error fetching CRM stats: " . $e->getMessage());
}

$currency = function_exists('get_settings') ? get_settings('currency', 'KES', $tenant_id) : 'KES';
?>

<!-- Page header -->
<div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-5">
    <div>
        <h1 class="text-lg font-bold text-white flex items-center gap-2">
            <i class="fas fa-handshake text-amber-400"></i> CRM Dashboard
        </h1>
        <p class="text-sm text-slate-500 mt-0.5">
            Manage leads, deals, and customer relationships
        </p>
    </div>
    <div class="flex items-center gap-2 shrink-0">
        <a href="leads.php?action=add"
           class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-blue-500/15 border border-blue-500/30 text-blue-400 text-sm font-medium hover:bg-blue-500/20 transition-colors">
            <i class="fas fa-user-plus text-xs"></i> Add Lead
        </a>
        <a href="deals.php?action=add"
           class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-emerald-500/15 border border-emerald-500/30 text-emerald-400 text-sm font-medium hover:bg-emerald-500/20 transition-colors">
            <i class="fas fa-dollar-sign text-xs"></i> New Deal
        </a>
    </div>
</div>

<!-- Stat Cards -->
<div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-2 mb-5">
    <?php
    $stat_cards = [
        ['label' => 'Total Leads', 'value' => $stats['total_leads'], 'sub' => $stats['new_leads'] . ' new', 'icon' => 'fa-users', 'color' => 'text-blue-400', 'bg' => 'bg-blue-500/10'],
        ['label' => 'Total Deals', 'value' => $stats['total_deals'], 'sub' => $stats['won_deals'] . ' won, ' . $stats['lost_deals'] . ' lost', 'icon' => 'fa-briefcase', 'color' => 'text-amber-400', 'bg' => 'bg-amber-500/10'],
        ['label' => 'Pipeline Value', 'value' => $currency . ' ' . number_format($stats['total_pipeline_value'], 0), 'sub' => 'Active deals', 'icon' => 'fa-chart-line', 'color' => 'text-emerald-400', 'bg' => 'bg-emerald-500/10'],
        ['label' => 'Conversion Rate', 'value' => $stats['total_leads'] > 0 ? round(($stats['converted_leads'] / $stats['total_leads']) * 100) : 0 . '%', 'sub' => $stats['converted_leads'] . ' converted', 'icon' => 'fa-percentage', 'color' => 'text-purple-400', 'bg' => 'bg-purple-500/10'],
    ];
    foreach ($stat_cards as $card):
    ?>
    <div class="bg-slate-800/50 border border-slate-700/60 rounded-xl p-3 flex items-center gap-2.5">
        <div class="w-8 h-8 rounded-lg <?php echo $card['bg']; ?> flex items-center justify-center shrink-0">
            <i class="fas <?php echo $card['icon']; ?> <?php echo $card['color']; ?> text-xs"></i>
        </div>
        <div class="min-w-0">
            <div class="text-sm font-bold <?php echo $card['color']; ?> truncate">
                <?php echo $card['value']; ?>
            </div>
            <div class="text-xs text-slate-500 leading-none mt-0.5"><?php echo $card['label']; ?></div>
            <div class="text-[10px] text-slate-600 leading-none mt-0.5"><?php echo $card['sub']; ?></div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- Lead Status Breakdown -->
<div class="grid grid-cols-2 sm:grid-cols-4 gap-2 mb-5">
    <?php
    $status_cards = [
        ['label' => 'New', 'count' => $stats['new_leads'], 'color' => 'text-blue-400', 'bg' => 'bg-blue-500/10'],
        ['label' => 'Contacted', 'count' => $stats['contacted_leads'], 'color' => 'text-amber-400', 'bg' => 'bg-amber-500/10'],
        ['label' => 'Qualified', 'count' => $stats['qualified_leads'], 'color' => 'text-emerald-400', 'bg' => 'bg-emerald-500/10'],
        ['label' => 'Converted', 'count' => $stats['converted_leads'], 'color' => 'text-purple-400', 'bg' => 'bg-purple-500/10'],
    ];
    foreach ($status_cards as $card):
    ?>
    <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-2 text-center">
        <div class="text-lg font-bold <?php echo $card['color']; ?>"><?php echo $card['count']; ?></div>
        <div class="text-xs text-slate-500"><?php echo $card['label']; ?></div>
    </div>
    <?php endforeach; ?>
</div>

<!-- Deal Status Breakdown -->
<div class="grid grid-cols-2 sm:grid-cols-4 gap-2 mb-5">
    <?php
    $deal_cards = [
        ['label' => 'Open', 'count' => $stats['open_deals'], 'color' => 'text-blue-400', 'bg' => 'bg-blue-500/10'],
        ['label' => 'Negotiating', 'count' => $stats['negotiating_deals'], 'color' => 'text-amber-400', 'bg' => 'bg-amber-500/10'],
        ['label' => 'Won', 'count' => $stats['won_deals'], 'color' => 'text-emerald-400', 'bg' => 'bg-emerald-500/10'],
        ['label' => 'Lost', 'count' => $stats['lost_deals'], 'color' => 'text-red-400', 'bg' => 'bg-red-500/10'],
    ];
    foreach ($deal_cards as $card):
    ?>
    <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-2 text-center">
        <div class="text-lg font-bold <?php echo $card['color']; ?>"><?php echo $card['count']; ?></div>
        <div class="text-xs text-slate-500"><?php echo $card['label']; ?></div>
    </div>
    <?php endforeach; ?>
</div>

<!-- Recent Leads & Deals -->
<div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
    
    <!-- Recent Leads -->
    <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl overflow-hidden">
        <div class="flex items-center justify-between px-4 py-3 border-b border-slate-700/60">
            <h3 class="text-sm font-semibold text-white flex items-center gap-2">
                <i class="fas fa-user-clock text-amber-400 text-xs"></i> Recent Leads
            </h3>
            <a href="leads.php" class="text-xs text-amber-400 hover:text-amber-300 transition-colors">View All</a>
        </div>
        
        <?php if (empty($recent_leads)): ?>
            <div class="text-center py-12">
                <i class="fas fa-users text-4xl text-slate-700 block mb-3"></i>
                <p class="text-slate-500 text-sm">No leads yet</p>
                <a href="leads.php?action=add" class="mt-2 inline-flex items-center gap-1 text-xs text-amber-400 hover:text-amber-300 transition-colors">
                    <i class="fas fa-plus text-xs"></i> Add your first lead
                </a>
            </div>
        <?php else: ?>
            <div class="divide-y divide-slate-700/40">
                <?php foreach ($recent_leads as $lead):
                    $status = $lead['status'] ?? 'new';
                    $status_colors = [
                        'new' => 'bg-blue-500/15 text-blue-400',
                        'contacted' => 'bg-amber-500/15 text-amber-400',
                        'qualified' => 'bg-emerald-500/15 text-emerald-400',
                        'converted' => 'bg-purple-500/15 text-purple-400',
                        'lost' => 'bg-red-500/15 text-red-400',
                    ];
                    $status_class = $status_colors[$status] ?? 'bg-slate-500/15 text-slate-400';
                ?>
                <div class="flex items-center justify-between p-3 hover:bg-slate-700/30 transition-colors">
                    <div class="flex items-center gap-3">
                        <div class="w-8 h-8 rounded-lg bg-amber-500/10 flex items-center justify-center">
                            <span class="text-amber-400 text-xs font-semibold"><?php echo strtoupper(substr($lead['name'], 0, 1)); ?></span>
                        </div>
                        <div>
                            <p class="text-sm text-white font-medium"><?php echo htmlspecialchars($lead['name']); ?></p>
                            <p class="text-xs text-slate-500"><?php echo htmlspecialchars($lead['email'] ?? $lead['phone'] ?? 'No contact'); ?></p>
                        </div>
                    </div>
                    <div class="text-right">
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium <?php echo $status_class; ?>">
                            <?php echo ucfirst($status); ?>
                        </span>
                        <p class="text-[10px] text-slate-600 mt-1"><?php echo date('d M', strtotime($lead['created_at'])); ?></p>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Recent Deals -->
    <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl overflow-hidden">
        <div class="flex items-center justify-between px-4 py-3 border-b border-slate-700/60">
            <h3 class="text-sm font-semibold text-white flex items-center gap-2">
                <i class="fas fa-hand-holding-dollar text-emerald-400 text-xs"></i> Recent Deals
            </h3>
            <a href="deals.php" class="text-xs text-emerald-400 hover:text-emerald-300 transition-colors">View All</a>
        </div>
        
        <?php if (empty($recent_deals)): ?>
            <div class="text-center py-12">
                <i class="fas fa-briefcase text-4xl text-slate-700 block mb-3"></i>
                <p class="text-slate-500 text-sm">No deals yet</p>
                <a href="deals.php?action=add" class="mt-2 inline-flex items-center gap-1 text-xs text-emerald-400 hover:text-emerald-300 transition-colors">
                    <i class="fas fa-plus text-xs"></i> Create your first deal
                </a>
            </div>
        <?php else: ?>
            <div class="divide-y divide-slate-700/40">
                <?php foreach ($recent_deals as $deal):
                    $status = $deal['status'] ?? 'open';
                    $status_colors = [
                        'open' => 'bg-blue-500/15 text-blue-400',
                        'negotiating' => 'bg-amber-500/15 text-amber-400',
                        'won' => 'bg-emerald-500/15 text-emerald-400',
                        'lost' => 'bg-red-500/15 text-red-400',
                    ];
                    $status_class = $status_colors[$status] ?? 'bg-slate-500/15 text-slate-400';
                ?>
                <div class="flex items-center justify-between p-3 hover:bg-slate-700/30 transition-colors">
                    <div class="flex items-center gap-3">
                        <div class="w-8 h-8 rounded-lg bg-emerald-500/10 flex items-center justify-center">
                            <i class="fas fa-handshake text-emerald-400 text-xs"></i>
                        </div>
                        <div>
                            <p class="text-sm text-white font-medium"><?php echo htmlspecialchars($deal['title']); ?></p>
                            <p class="text-xs text-slate-500"><?php echo htmlspecialchars($deal['lead_name'] ?? 'No lead'); ?></p>
                        </div>
                    </div>
                    <div class="text-right">
                        <p class="text-sm font-semibold text-amber-400"><?php echo $currency . ' ' . number_format((float)$deal['value'], 0); ?></p>
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium <?php echo $status_class; ?>">
                            <?php echo ucfirst($status); ?>
                        </span>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php
$page_content = ob_get_clean();
require_once __DIR__ . '/../layouts/app.php';
?>