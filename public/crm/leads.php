<?php
/**
 * CRM Leads Management
 * Pure Tailwind CSS
 */

$page_title = 'CRM Leads';
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
            $name = trim($_POST['name'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $phone = trim($_POST['phone'] ?? '');
            $source = $_POST['source'] ?? 'website';
            $status = $_POST['status'] ?? 'new';
            $notes = trim($_POST['notes'] ?? '');
            $tenant_name = $_SESSION['tenant_name'] ?? '';

            if (!in_array($source, ['website', 'referral', 'social', 'advertisement', 'other'], true)) {
                $source = 'website';
            }
            if (!in_array($status, ['new', 'contacted', 'qualified', 'converted', 'lost'], true)) {
                $status = 'new';
            }

            if (empty($name)) {
                $error_message = 'Lead name is required';
            } else {
                try {
                    $stmt = $pdo->prepare('
                        INSERT INTO crm_leads (tenant_id, tenant_name, name, email, phone, source, status, notes, created_by, created_at)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                    ');
                    $stmt->execute([$tenant_id, $tenant_name, $name, $email, $phone, $source, $status, $notes, $user_id]);
                    $success_message = 'Lead created successfully';
                    
                } catch (PDOException $e) {
                    error_log("Error creating lead: " . $e->getMessage());
                    $error_message = 'Failed to create lead';
                }
            }
        } elseif ($action === 'update') {
            $id = intval($_POST['id'] ?? 0);
            $name = trim($_POST['name'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $phone = trim($_POST['phone'] ?? '');
            $source = $_POST['source'] ?? 'website';
            $status = $_POST['status'] ?? 'new';
            $notes = trim($_POST['notes'] ?? '');

            if (!in_array($source, ['website', 'referral', 'social', 'advertisement', 'other'], true)) {
                $source = 'website';
            }
            if (!in_array($status, ['new', 'contacted', 'qualified', 'converted', 'lost'], true)) {
                $status = 'new';
            }

            if ($id > 0 && !empty($name)) {
                try {
                    $stmt = $pdo->prepare('
                        UPDATE crm_leads SET name = ?, email = ?, phone = ?, source = ?, status = ?, notes = ?, updated_at = NOW()
                        WHERE id = ? AND tenant_id = ?
                    ');
                    $stmt->execute([$name, $email, $phone, $source, $status, $notes, $id, $tenant_id]);
                    $success_message = 'Lead updated successfully';
                    
                } catch (PDOException $e) {
                    error_log("Error updating lead: " . $e->getMessage());
                    $error_message = 'Failed to update lead';
                }
            }
        } elseif ($action === 'delete') {
            $id = intval($_POST['id'] ?? 0);
            if ($id > 0) {
                try {
                    $stmt = $pdo->prepare('DELETE FROM crm_leads WHERE id = ? AND tenant_id = ?');
                    $stmt->execute([$id, $tenant_id]);
                    $success_message = 'Lead deleted successfully';
                    
                } catch (PDOException $e) {
                    error_log("Error deleting lead: " . $e->getMessage());
                    $error_message = 'Failed to delete lead';
                }
            }
        }
    }
}

$csrf_token = generate_csrf_token();

// Fetch leads
$leads = [];
try {
    $stmt = $pdo->prepare('
        SELECT * FROM crm_leads 
        WHERE tenant_id = ? 
        ORDER BY created_at DESC 
        LIMIT 100
    ');
    $stmt->execute([$tenant_id]);
    $leads = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error fetching leads: " . $e->getMessage());
}

// Status badge colors
$status_colors = [
    'new' => 'bg-blue-500/15 text-blue-400',
    'contacted' => 'bg-amber-500/15 text-amber-400',
    'qualified' => 'bg-emerald-500/15 text-emerald-400',
    'converted' => 'bg-purple-500/15 text-purple-400',
    'lost' => 'bg-red-500/15 text-red-400',
];

$source_labels = [
    'website' => 'Website',
    'referral' => 'Referral',
    'social' => 'Social Media',
    'advertisement' => 'Advertisement',
    'other' => 'Other',
];
?>

<!-- Page header -->
<div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-5">
    <div>
        <h1 class="text-lg font-bold text-white flex items-center gap-2">
            <i class="fas fa-user-friends text-amber-400"></i> CRM Leads
        </h1>
        <p class="text-sm text-slate-500 mt-0.5">
            Manage your sales leads and prospects
        </p>
    </div>
    <div class="flex items-center gap-2 shrink-0">
        <button onclick="openLeadModal()" 
                class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-amber-500/15 border border-amber-500/30 text-amber-400 text-sm font-medium hover:bg-amber-500/20 transition-colors">
            <i class="fas fa-plus text-xs"></i> Add Lead
        </button>
    </div>
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

<!-- Leads Table -->
<div class="bg-slate-800/40 border border-slate-700/60 rounded-xl overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full min-w-[860px]">
            <thead>
                <tr class="border-b border-slate-700/60 bg-slate-800/60">
                    <th class="px-3 py-2.5 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Lead</th>
                    <th class="px-3 py-2.5 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Contact</th>
                    <th class="px-3 py-2.5 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Source</th>
                    <th class="px-3 py-2.5 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Status</th>
                    <th class="px-3 py-2.5 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Created</th>
                    <th class="px-3 py-2.5 text-center text-xs font-semibold text-slate-500 uppercase tracking-wider">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-700/40">
            <?php if (empty($leads)): ?>
                <tr>
                    <td colspan="6" class="px-4 py-14 text-center">
                        <i class="fas fa-user-friends text-4xl text-slate-700 block mb-3"></i>
                        <p class="text-slate-500 text-sm">No leads found</p>
                        <button onclick="openLeadModal()" class="mt-2 inline-flex items-center gap-1 text-xs text-amber-400 hover:text-amber-300 transition-colors">
                            <i class="fas fa-plus text-xs"></i> Add your first lead
                        </button>
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($leads as $lead): 
                    $status = $lead['status'] ?? 'new';
                    $status_class = $status_colors[$status] ?? 'bg-slate-500/15 text-slate-400';
                ?>
                <tr class="hover:bg-slate-700/30 transition-colors group">
                    <td class="px-3 py-2.5">
                        <div class="flex items-center gap-3">
                            <div class="w-8 h-8 rounded-lg bg-amber-500/10 flex items-center justify-center">
                                <span class="text-amber-400 text-xs font-semibold"><?php echo strtoupper(substr($lead['name'], 0, 1)); ?></span>
                            </div>
                            <span class="text-sm text-white font-medium"><?php echo htmlspecialchars($lead['name']); ?></span>
                        </div>
                    </td>
                    <td class="px-3 py-2.5">
                        <?php if (!empty($lead['email'])): ?>
                        <div class="text-sm text-slate-300"><?php echo htmlspecialchars($lead['email']); ?></div>
                        <?php endif; ?>
                        <?php if (!empty($lead['phone'])): ?>
                        <div class="text-xs text-slate-500"><?php echo htmlspecialchars($lead['phone']); ?></div>
                        <?php endif; ?>
                        <?php if (empty($lead['email']) && empty($lead['phone'])): ?>
                        <div class="text-sm text-slate-500">—</div>
                        <?php endif; ?>
                    </td>
                    <td class="px-3 py-2.5">
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-slate-700/50 text-slate-300">
                            <?php echo $source_labels[$lead['source']] ?? ucfirst($lead['source']); ?>
                        </span>
                    </td>
                    <td class="px-3 py-2.5">
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium <?php echo $status_class; ?>">
                            <?php echo ucfirst($status); ?>
                        </span>
                    </td>
                    <td class="px-3 py-2.5">
                        <div class="text-sm text-slate-400"><?php echo date('d M Y', strtotime($lead['created_at'])); ?></div>
                        <div class="text-xs text-slate-600"><?php echo date('H:i', strtotime($lead['created_at'])); ?></div>
                    </td>
                    <td class="px-3 py-2.5">
                        <div class="flex items-center justify-center gap-2">
                            <button onclick='editLead(<?php echo json_encode($lead); ?>)' 
                                    class="w-6 h-6 flex items-center justify-center rounded-lg bg-slate-700/60 text-amber-400 hover:bg-amber-500/20 hover:text-amber-300 transition-colors" title="Edit">
                                <i class="fas fa-edit text-xs"></i>
                            </button>
                            <button onclick="deleteLead(<?php echo $lead['id']; ?>, '<?php echo htmlspecialchars(addslashes($lead['name'])); ?>')" 
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

<!-- Lead Modal -->
<div id="leadModal" class="fixed inset-0 bg-black/70 hidden z-50 items-center justify-center">
    <div class="bg-slate-800 rounded-xl border border-slate-700/60 p-6 max-w-lg w-full mx-4 shadow-xl">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-lg font-semibold text-white" id="leadModalTitle">Add Lead</h3>
            <button onclick="closeLeadModal()" class="text-slate-400 hover:text-white transition-colors">
                <i class="fas fa-times"></i>
            </button>
        </div>
        
        <form method="POST" class="space-y-4">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
            <input type="hidden" name="action" id="leadAction" value="create">
            <input type="hidden" name="id" id="leadId" value="0">
            
            <div>
                <label class="block text-xs font-semibold text-slate-400 mb-1">Name *</label>
                <input type="text" name="name" id="leadName" required 
                       class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-1 focus:ring-amber-500">
            </div>
            
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-semibold text-slate-400 mb-1">Email</label>
                    <input type="email" name="email" id="leadEmail" 
                           class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-1 focus:ring-amber-500">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-400 mb-1">Phone</label>
                    <input type="tel" name="phone" id="leadPhone" 
                           class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-1 focus:ring-amber-500">
                </div>
            </div>
            
            <div>
                <label class="block text-xs font-semibold text-slate-400 mb-1">Source</label>
                <select name="source" id="leadSource" 
                        class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm focus:outline-none focus:ring-1 focus:ring-amber-500">
                    <option value="website">Website</option>
                    <option value="referral">Referral</option>
                    <option value="social">Social Media</option>
                    <option value="advertisement">Advertisement</option>
                    <option value="other">Other</option>
                </select>
            </div>
            
            <div>
                <label class="block text-xs font-semibold text-slate-400 mb-1">Status</label>
                <select name="status" id="leadStatus" 
                        class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm focus:outline-none focus:ring-1 focus:ring-amber-500">
                    <option value="new">New</option>
                    <option value="contacted">Contacted</option>
                    <option value="qualified">Qualified</option>
                    <option value="converted">Converted</option>
                    <option value="lost">Lost</option>
                </select>
            </div>
            
            <div>
                <label class="block text-xs font-semibold text-slate-400 mb-1">Notes</label>
                <textarea name="notes" id="leadNotes" rows="3" 
                          class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-1 focus:ring-amber-500"></textarea>
            </div>
            
            <div class="flex gap-3 pt-4">
                <button type="submit" class="flex-1 px-4 py-2 bg-amber-500 hover:bg-amber-600 text-white font-semibold rounded-lg transition-colors">
                    Save Lead
                </button>
                <button type="button" onclick="closeLeadModal()" class="flex-1 px-4 py-2 bg-slate-700 hover:bg-slate-600 text-white font-medium rounded-lg transition-colors">
                    Cancel
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function openLeadModal() {
    document.getElementById('leadModal').classList.remove('hidden');
    document.getElementById('leadModal').style.display = 'flex';
}

function closeLeadModal() {
    document.getElementById('leadModal').classList.add('hidden');
    document.getElementById('leadModal').style.display = 'none';
    document.getElementById('leadModalTitle').innerText = 'Add Lead';
    document.getElementById('leadAction').value = 'create';
    document.getElementById('leadId').value = '0';
    document.getElementById('leadName').value = '';
    document.getElementById('leadEmail').value = '';
    document.getElementById('leadPhone').value = '';
    document.getElementById('leadSource').value = 'website';
    document.getElementById('leadStatus').value = 'new';
    document.getElementById('leadNotes').value = '';
}

function editLead(lead) {
    document.getElementById('leadModalTitle').innerText = 'Edit Lead';
    document.getElementById('leadAction').value = 'update';
    document.getElementById('leadId').value = lead.id;
    document.getElementById('leadName').value = lead.name || '';
    document.getElementById('leadEmail').value = lead.email || '';
    document.getElementById('leadPhone').value = lead.phone || '';
    document.getElementById('leadSource').value = lead.source || 'website';
    document.getElementById('leadStatus').value = lead.status || 'new';
    document.getElementById('leadNotes').value = lead.notes || '';
    openLeadModal();
}

function deleteLead(id, name) {
    if (confirm('Delete lead: ' + name + '?')) {
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
        closeLeadModal();
    }
});

// Close modal on outside click
document.getElementById('leadModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeLeadModal();
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