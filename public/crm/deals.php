<?php
/**
 * CRM Deals Management
 * Pure Tailwind CSS
 */

$page_title = 'CRM Deals';
ob_start();

$pathsFile = __DIR__ . '/../../src/paths.php';
if (!file_exists($pathsFile)) {
    $pathsFile = dirname(__DIR__, 3) . '/src/paths.php';
}
require_once $pathsFile;

safe_require('auth.php', 'src', true);
safe_require('db.php', 'src', true);
safe_require('functions.php', 'src', true);
require_login();

$pdo = get_db_connection();
$user_id = get_current_user_id();
$tenant_id = get_current_tenant_id();
$success_message = '';
$error_message = '';

// Ensure tables exist
ensureCrmTables($pdo, $tenant_id);

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $csrf_token = $_POST['csrf_token'] ?? '';

    if (!verify_csrf_token($csrf_token)) {
        $error_message = 'Security validation failed. Please refresh the page.';
    } else {
        if ($action === 'create') {
            $title = trim($_POST['title'] ?? '');
            $lead_id = !empty($_POST['lead_id']) ? intval($_POST['lead_id']) : null;
            $value = floatval($_POST['value'] ?? 0);
            $status = $_POST['status'] ?? 'open';
            $expected_close_date = $_POST['expected_close_date'] ?? null;
            $notes = trim($_POST['notes'] ?? '');

            if (!in_array($status, ['open', 'negotiating', 'won', 'lost'], true)) {
                $status = 'open';
            }

            if (empty($title)) {
                $error_message = 'Deal title is required';
            } else {
                try {
                    $stmt = $pdo->prepare('
                        INSERT INTO crm_deals (tenant_id, lead_id, title, value, status, expected_close_date, notes, created_by, created_at)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
                    ');
                    $stmt->execute([$tenant_id, $lead_id, $title, $value, $status, $expected_close_date, $notes, $user_id]);
                    $success_message = 'Deal created successfully';
                    
                } catch (PDOException $e) {
                    error_log("Error creating deal: " . $e->getMessage());
                    $error_message = 'Failed to create deal';
                }
            }
        } elseif ($action === 'update') {
            $id = intval($_POST['id'] ?? 0);
            $title = trim($_POST['title'] ?? '');
            $lead_id = !empty($_POST['lead_id']) ? intval($_POST['lead_id']) : null;
            $value = floatval($_POST['value'] ?? 0);
            $status = $_POST['status'] ?? 'open';
            $expected_close_date = $_POST['expected_close_date'] ?? null;
            $notes = trim($_POST['notes'] ?? '');

            if ($id > 0 && !empty($title)) {
                try {
                    $stmt = $pdo->prepare('
                        UPDATE crm_deals SET title = ?, lead_id = ?, value = ?, status = ?, expected_close_date = ?, notes = ?, updated_at = NOW()
                        WHERE id = ? AND tenant_id = ?
                    ');
                    $stmt->execute([$title, $lead_id, $value, $status, $expected_close_date, $notes, $id, $tenant_id]);
                    $success_message = 'Deal updated successfully';
                    
                } catch (PDOException $e) {
                    error_log("Error updating deal: " . $e->getMessage());
                    $error_message = 'Failed to update deal';
                }
            }
        } elseif ($action === 'delete') {
            $id = intval($_POST['id'] ?? 0);
            if ($id > 0) {
                try {
                    $stmt = $pdo->prepare('DELETE FROM crm_deals WHERE id = ? AND tenant_id = ?');
                    $stmt->execute([$id, $tenant_id]);
                    $success_message = 'Deal deleted successfully';
                    
                } catch (PDOException $e) {
                    error_log("Error deleting deal: " . $e->getMessage());
                    $error_message = 'Failed to delete deal';
                }
            }
        }
    }
}

$csrf_token = generate_csrf_token();

// Fetch deals with lead names
$deals = [];
$leads = [];
try {
    $stmt = $pdo->prepare('
        SELECT d.*, l.name as lead_name 
        FROM crm_deals d
        LEFT JOIN crm_leads l ON d.lead_id = l.id
        WHERE d.tenant_id = ? 
        ORDER BY d.created_at DESC
        LIMIT 100
    ');
    $stmt->execute([$tenant_id]);
    $deals = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch leads for dropdown (only active ones)
    $stmt = $pdo->prepare('
        SELECT id, name FROM crm_leads 
        WHERE tenant_id = ? AND status != "converted" AND status != "lost"
        ORDER BY name
    ');
    $stmt->execute([$tenant_id]);
    $leads = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error fetching deals: " . $e->getMessage());
}

// Calculate stats
$stats = ['total' => 0, 'open' => 0, 'negotiating' => 0, 'won' => 0, 'lost' => 0, 'value' => 0];
foreach ($deals as $deal) {
    $stats['total']++;
    $stats[$deal['status']]++;
    if (in_array($deal['status'], ['open', 'negotiating'])) {
        $stats['value'] += $deal['value'];
    }
}

// Status badge colors
$status_colors = [
    'open' => 'bg-blue-500/15 text-blue-400',
    'negotiating' => 'bg-amber-500/15 text-amber-400',
    'won' => 'bg-emerald-500/15 text-emerald-400',
    'lost' => 'bg-red-500/15 text-red-400',
];

$currency = function_exists('get_settings') ? get_settings('currency', 'KES', $tenant_id) : 'KES';
?>

<!-- Page header -->
<div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-5">
    <div>
        <h1 class="text-lg font-bold text-white flex items-center gap-2">
            <i class="fas fa-hand-holding-dollar text-emerald-400"></i> CRM Deals
        </h1>
        <p class="text-sm text-slate-500 mt-0.5">
            Track your sales deals and opportunities
        </p>
    </div>
    <div class="flex items-center gap-2 shrink-0">
        <button onclick="openDealModal()" 
                class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-emerald-500/15 border border-emerald-500/30 text-emerald-400 text-sm font-medium hover:bg-emerald-500/20 transition-colors">
            <i class="fas fa-plus text-xs"></i> New Deal
        </button>
    </div>
</div>

<!-- Stat Cards -->
<div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-2 mb-5">
    <?php
    $stat_cards = [
        ['label' => 'Total', 'value' => $stats['total'], 'color' => 'text-white', 'bg' => 'bg-slate-500/10'],
        ['label' => 'Open', 'value' => $stats['open'], 'color' => 'text-blue-400', 'bg' => 'bg-blue-500/10'],
        ['label' => 'Negotiating', 'value' => $stats['negotiating'], 'color' => 'text-amber-400', 'bg' => 'bg-amber-500/10'],
        ['label' => 'Won', 'value' => $stats['won'], 'color' => 'text-emerald-400', 'bg' => 'bg-emerald-500/10'],
        ['label' => 'Pipeline Value', 'value' => $currency . ' ' . number_format($stats['value'], 0), 'color' => 'text-white', 'bg' => 'bg-slate-500/10'],
    ];
    foreach ($stat_cards as $card):
    ?>
    <div class="bg-slate-800/50 border border-slate-700/60 rounded-xl p-3 text-center">
        <div class="text-lg font-bold <?php echo $card['color']; ?>"><?php echo $card['value']; ?></div>
        <div class="text-xs text-slate-500"><?php echo $card['label']; ?></div>
    </div>
    <?php endforeach; ?>
</div>

<!-- Success/Error Messages -->
<?php if ($success_message): ?>
<div class="mb-4 flex items-center gap-2 px-3 py-2 rounded-lg bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 text-sm">
    <i class="fas fa-check-circle"></i>
    <?php echo htmlspecialchars($success_message); ?>
</div>
<?php endif; ?>

<?php if ($error_message): ?>
<div class="mb-4 flex items-center gap-2 px-3 py-2 rounded-lg bg-red-500/10 border border-red-500/30 text-red-400 text-sm">
    <i class="fas fa-exclamation-circle"></i>
    <?php echo htmlspecialchars($error_message); ?>
</div>
<?php endif; ?>

<!-- Deals Table -->
<div class="bg-slate-800/40 border border-slate-700/60 rounded-xl overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full min-w-[860px]">
            <thead>
                <tr class="border-b border-slate-700/60 bg-slate-800/60">
                    <th class="px-3 py-2.5 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Deal</th>
                    <th class="px-3 py-2.5 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Lead</th>
                    <th class="px-3 py-2.5 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Value</th>
                    <th class="px-3 py-2.5 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Status</th>
                    <th class="px-3 py-2.5 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Expected Close</th>
                    <th class="px-3 py-2.5 text-center text-xs font-semibold text-slate-500 uppercase tracking-wider">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-700/40">
            <?php if (empty($deals)): ?>
                <tr>
                    <td colspan="6" class="px-4 py-14 text-center">
                        <i class="fas fa-briefcase text-4xl text-slate-700 block mb-3"></i>
                        <p class="text-slate-500 text-sm">No deals found</p>
                        <button onclick="openDealModal()" class="mt-2 inline-flex items-center gap-1 text-xs text-emerald-400 hover:text-emerald-300 transition-colors">
                            <i class="fas fa-plus text-xs"></i> Create your first deal
                        </button>
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($deals as $deal): 
                    $status = $deal['status'] ?? 'open';
                    $status_class = $status_colors[$status] ?? 'bg-slate-500/15 text-slate-400';
                ?>
                <tr class="hover:bg-slate-700/30 transition-colors group">
                    <td class="px-3 py-2.5">
                        <div class="flex items-center gap-3">
                            <div class="w-8 h-8 rounded-lg bg-emerald-500/10 flex items-center justify-center">
                                <i class="fas fa-handshake text-emerald-400 text-xs"></i>
                            </div>
                            <span class="text-sm text-white font-medium"><?php echo htmlspecialchars($deal['title']); ?></span>
                        </div>
                        <?php if (!empty($deal['notes'])): ?>
                        <div class="text-xs text-slate-500 mt-1 truncate max-w-[200px]"><?php echo htmlspecialchars(substr($deal['notes'], 0, 50)); ?></div>
                        <?php endif; ?>
                    </td>
                    <td class="px-3 py-2.5 text-sm text-slate-400">
                        <?php echo htmlspecialchars($deal['lead_name'] ?? '—'); ?>
                    </td>
                    <td class="px-3 py-2.5">
                        <span class="text-sm font-semibold text-amber-400"><?php echo $currency . ' ' . number_format((float)$deal['value'], 0); ?></span>
                    </td>
                    <td class="px-3 py-2.5">
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium <?php echo $status_class; ?>">
                            <?php echo ucfirst($status); ?>
                        </span>
                    </td>
                    <td class="px-3 py-2.5 text-sm text-slate-400">
                        <?php echo $deal['expected_close_date'] ? date('d M Y', strtotime($deal['expected_close_date'])) : '—'; ?>
                    </td>
                    <td class="px-3 py-2.5">
                        <div class="flex items-center justify-center gap-2">
                            <button onclick='editDeal(<?php echo json_encode($deal); ?>)' 
                                    class="w-6 h-6 flex items-center justify-center rounded-lg bg-slate-700/60 text-emerald-400 hover:bg-emerald-500/20 hover:text-emerald-300 transition-colors" title="Edit">
                                <i class="fas fa-edit text-xs"></i>
                            </button>
                            <button onclick="deleteDeal(<?php echo $deal['id']; ?>, '<?php echo htmlspecialchars(addslashes($deal['title'])); ?>')" 
                                    class="w-6 h-6 flex items-center justify-center rounded-lg bg-slate-700/60 text-red-400 hover:bg-red-500/20 hover:text-red-300 transition-colors" title="Delete">
                                <i class="fas fa-trash text-xs"></i>
                            </button>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Deal Modal -->
<div id="dealModal" class="fixed inset-0 bg-black/70 hidden z-50 items-center justify-center">
    <div class="bg-slate-800 rounded-xl border border-slate-700/60 p-6 max-w-lg w-full mx-4 shadow-xl">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-lg font-semibold text-white" id="dealModalTitle">New Deal</h3>
            <button onclick="closeDealModal()" class="text-slate-400 hover:text-white transition-colors">
                <i class="fas fa-times"></i>
            </button>
        </div>
        
        <form method="POST" class="space-y-4">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
            <input type="hidden" name="action" id="dealAction" value="create">
            <input type="hidden" name="id" id="dealId" value="0">
            
            <div>
                <label class="block text-xs font-semibold text-slate-400 mb-1">Deal Title *</label>
                <input type="text" name="title" id="dealTitle" required 
                       class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-1 focus:ring-emerald-500">
            </div>
            
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-semibold text-slate-400 mb-1">Related Lead</label>
                    <select name="lead_id" id="dealLead" 
                            class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm focus:outline-none focus:ring-1 focus:ring-emerald-500">
                        <option value="">No lead</option>
                        <?php foreach ($leads as $lead): ?>
                            <option value="<?php echo $lead['id']; ?>"><?php echo htmlspecialchars($lead['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-400 mb-1">Value</label>
                    <input type="number" name="value" id="dealValue" step="0.01" min="0" 
                           class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-1 focus:ring-emerald-500">
                </div>
            </div>
            
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-semibold text-slate-400 mb-1">Status</label>
                    <select name="status" id="dealStatus" 
                            class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm focus:outline-none focus:ring-1 focus:ring-emerald-500">
                        <option value="open">Open</option>
                        <option value="negotiating">Negotiating</option>
                        <option value="won">Won</option>
                        <option value="lost">Lost</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-400 mb-1">Expected Close</label>
                    <input type="date" name="expected_close_date" id="dealCloseDate" 
                           class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm focus:outline-none focus:ring-1 focus:ring-emerald-500">
                </div>
            </div>
            
            <div>
                <label class="block text-xs font-semibold text-slate-400 mb-1">Notes</label>
                <textarea name="notes" id="dealNotes" rows="3" 
                          class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-1 focus:ring-emerald-500"></textarea>
            </div>
            
            <div class="flex gap-3 pt-4">
                <button type="submit" class="flex-1 px-4 py-2 bg-emerald-500 hover:bg-emerald-600 text-white font-semibold rounded-lg transition-colors">
                    Save Deal
                </button>
                <button type="button" onclick="closeDealModal()" class="flex-1 px-4 py-2 bg-slate-700 hover:bg-slate-600 text-white font-medium rounded-lg transition-colors">
                    Cancel
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function openDealModal() {
    document.getElementById('dealModal').classList.remove('hidden');
    document.getElementById('dealModal').style.display = 'flex';
}

function closeDealModal() {
    document.getElementById('dealModal').classList.add('hidden');
    document.getElementById('dealModal').style.display = 'none';
    document.getElementById('dealModalTitle').innerText = 'New Deal';
    document.getElementById('dealAction').value = 'create';
    document.getElementById('dealId').value = '0';
    document.getElementById('dealTitle').value = '';
    document.getElementById('dealLead').value = '';
    document.getElementById('dealValue').value = '';
    document.getElementById('dealStatus').value = 'open';
    document.getElementById('dealCloseDate').value = '';
    document.getElementById('dealNotes').value = '';
}

function editDeal(deal) {
    document.getElementById('dealModalTitle').innerText = 'Edit Deal';
    document.getElementById('dealAction').value = 'update';
    document.getElementById('dealId').value = deal.id;
    document.getElementById('dealTitle').value = deal.title || '';
    document.getElementById('dealLead').value = deal.lead_id || '';
    document.getElementById('dealValue').value = deal.value || '';
    document.getElementById('dealStatus').value = deal.status || 'open';
    document.getElementById('dealCloseDate').value = deal.expected_close_date || '';
    document.getElementById('dealNotes').value = deal.notes || '';
    openDealModal();
}

function deleteDeal(id, title) {
    if (confirm('Delete deal: ' + title + '?')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = '<input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">' +
                        '<input type="hidden" name="action" value="delete">' +
                        '<input type="hidden" name="id" value="' + id + '">';
        document.body.appendChild(form);
        form.submit();
    }
}

// Close modal on escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeDealModal();
    }
});

// Close modal on outside click
document.getElementById('dealModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeDealModal();
    }
});
</script>

<?php
$page_content = ob_get_clean();
require_once __DIR__ . '/../layouts/app.php';
?>

<?php
/**
 * Ensure CRM tables exist
 */
function ensureCrmTables($pdo, $tenant_id) {
    try {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS crm_leads (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                tenant_id BIGINT UNSIGNED NOT NULL,
                tenant_name VARCHAR(255) NULL,
                name VARCHAR(255) NOT NULL,
                email VARCHAR(255),
                phone VARCHAR(50),
                source VARCHAR(50) DEFAULT 'website',
                status ENUM('new', 'contacted', 'qualified', 'converted', 'lost') DEFAULT 'new',
                notes TEXT,
                created_by INT UNSIGNED,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NULL,
                INDEX idx_tenant (tenant_id),
                INDEX idx_status (status)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS crm_deals (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                tenant_id BIGINT UNSIGNED NOT NULL,
                lead_id INT UNSIGNED,
                title VARCHAR(255) NOT NULL,
                value DECIMAL(15,2) DEFAULT 0,
                status ENUM('open', 'negotiating', 'won', 'lost') DEFAULT 'open',
                expected_close_date DATE,
                notes TEXT,
                created_by INT UNSIGNED,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NULL,
                INDEX idx_tenant (tenant_id),
                INDEX idx_status (status)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    } catch (PDOException $e) {
        error_log("CRM tables error: " . $e->getMessage());
    }
}
?>