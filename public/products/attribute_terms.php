<?php
/**
 * Attribute Terms Management
 * Manage values/terms for a specific product attribute
 */

require_once __DIR__ . '/../../src/paths.php';
safe_require('auth.php', 'src', true);
safe_require('db.php', 'src', true);
safe_require('functions.php', 'src', true);

// Branch filter for multi-tenant isolation
$current_branch_id = get_current_branch_id();

require_login();

$pdo = get_db_connection();
$tenant_id = get_current_tenant_id();
if (!$tenant_id) {
    http_response_code(403);
    exit('Company context missing. Please log in again.');
}

$user_id = get_current_user_id() ?? 0;
$branch_name = get_current_branch_name();

$attribute_id = intval($_GET['attribute_id'] ?? 0);
if (!$attribute_id) {
    header('Location: attributes.php');
    exit;
}

// Load attribute info
$attr = null;
try {
    $stmt = $pdo->prepare("SELECT a.*, ag.name as group_name FROM attributes a LEFT JOIN attribute_groups ag ON ag.id = a.group_id WHERE a.id = :id AND a.tenant_id = :tenant_id AND a.deleted_at IS NULL");
    $stmt->execute([':id' => $attribute_id, ':tenant_id' => $tenant_id]);
    $attr = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Attribute load error: " . $e->getMessage());
}

if (!$attr) {
    header('Location: attributes.php');
    exit;
}

$csrf_token = generate_csrf_token();

// AJAX Router
$is_ajax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
        || (!empty($_GET['ajax']) && $_GET['ajax'] === '1')
        || (!empty($_POST['ajax']) && $_POST['ajax'] === '1');

$action = $_GET['action'] ?? $_POST['action'] ?? '';
$response = ['success' => false, 'error' => 'Unknown action'];

if ($action && $is_ajax) {
    header('Content-Type: application/json');
    switch ($action) {
        case 'list':
            $response = ajax_list_terms($pdo, $tenant_id, $attribute_id);
            break;
        case 'save':
            if (!verify_csrf_token($_POST['csrf_token'] ?? '')) { $response = ['success' => false, 'error' => 'Invalid CSRF token']; break; }
            $response = ajax_save_term($pdo, $tenant_id, $attribute_id);
            break;
        case 'delete':
            if (!verify_csrf_token($_POST['csrf_token'] ?? '')) { $response = ['success' => false, 'error' => 'Invalid CSRF token']; break; }
            $response = ajax_delete_term($pdo, $tenant_id, $attribute_id);
            break;
        case 'reorder':
            if (!verify_csrf_token($_POST['csrf_token'] ?? '')) { $response = ['success' => false, 'error' => 'Invalid CSRF token']; break; }
            $response = ajax_reorder_terms($pdo, $tenant_id, $attribute_id);
            break;
        case 'get':
            $response = ajax_get_term($pdo, $tenant_id, $attribute_id);
            break;
    }
    echo json_encode($response);
    exit;
}

function ajax_list_terms(PDO $pdo, int $tenant_id, int $attribute_id): array {
    try {
        $stmt = $pdo->prepare("SELECT * FROM attribute_values WHERE attribute_id = :attribute_id AND tenant_id = :tenant_id AND branch_id = $current_branch_id ORDER BY sort_order ASC, id ASC");
        $stmt->execute([':attribute_id' => $attribute_id, ':tenant_id' => $tenant_id]);
        $terms = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return ['success' => true, 'data' => ['terms' => $terms, 'count' => count($terms)]];
    } catch (PDOException $e) {
        error_log("ajax_list_terms error: " . $e->getMessage());
        return ['success' => false, 'error' => 'Database error'];
    }
}

function ajax_get_term(PDO $pdo, int $tenant_id, int $attribute_id): array {
    $id = intval($_GET['id'] ?? 0);
    if (!$id) return ['success' => false, 'error' => 'Missing ID'];
    try {
        $stmt = $pdo->prepare("SELECT * FROM attribute_values WHERE id = :id AND attribute_id = :attribute_id AND tenant_id = :tenant_id");
        $stmt->execute([':id' => $id, ':attribute_id' => $attribute_id, ':tenant_id' => $tenant_id]);
        $term = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$term) return ['success' => false, 'error' => 'Not found'];
        return ['success' => true, 'data' => $term];
    } catch (PDOException $e) {
        return ['success' => false, 'error' => 'Database error'];
    }
}

function hasDescriptionColumn(PDO $pdo): bool {
    static $cached = null;
    if ($cached !== null) return $cached;
    try {
        $stmt = $pdo->query("SHOW COLUMNS FROM attribute_values LIKE 'description'");
        $cached = (bool) $stmt->fetch();
        return $cached;
    } catch (PDOException $e) {
        $cached = false;
        return false;
    }
}

function ajax_save_term(PDO $pdo, int $tenant_id, int $attribute_id): array {
    $id = intval($_POST['id'] ?? 0);
    $name = trim($_POST['name'] ?? '');
    $slug = trim($_POST['slug'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $color_hex = trim($_POST['color_hex'] ?? '');

    if ($name === '') {
        return ['success' => false, 'error' => 'Name is required'];
    }
    if ($slug === '') {
        $slug = strtolower(preg_replace('/[^a-z0-9]+/i', '-', $name));
        $slug = trim($slug, '-');
    }

    $hasDesc = hasDescriptionColumn($pdo);

    try {
        // Check duplicate slug within this attribute
        $check = $pdo->prepare("SELECT id FROM attribute_values WHERE attribute_id = :attribute_id AND tenant_id = :tenant_id AND label = :slug" . ($id ? " AND id != :id" : ""));
        $cp = [':attribute_id' => $attribute_id, ':tenant_id' => $tenant_id, ':slug' => $slug];
        if ($id) $cp[':id'] = $id;
        $check->execute($cp);
        if ($check->fetch()) {
            return ['success' => false, 'error' => 'A term with this slug already exists for this attribute'];
        }

        if ($id) {
            if ($hasDesc) {
                $stmt = $pdo->prepare("UPDATE attribute_values SET value = :value, label = :label, description = :description, color_hex = :color_hex WHERE id = :id AND attribute_id = :attribute_id AND tenant_id = :tenant_id");
                $stmt->execute([
                    ':value' => $name, ':label' => $slug, ':description' => $description,
                    ':color_hex' => $color_hex, ':id' => $id,
                    ':attribute_id' => $attribute_id, ':tenant_id' => $tenant_id
                ]);
            } else {
                $stmt = $pdo->prepare("UPDATE attribute_values SET value = :value, label = :label, color_hex = :color_hex WHERE id = :id AND attribute_id = :attribute_id AND tenant_id = :tenant_id");
                $stmt->execute([
                    ':value' => $name, ':label' => $slug,
                    ':color_hex' => $color_hex, ':id' => $id,
                    ':attribute_id' => $attribute_id, ':tenant_id' => $tenant_id
                ]);
            }
            return ['success' => true, 'data' => ['id' => $id, 'message' => 'Term updated']];
        } else {
            // Get max sort_order
            $sortStmt = $pdo->prepare("SELECT COALESCE(MAX(sort_order), 0) + 1 FROM attribute_values WHERE attribute_id = :attribute_id AND tenant_id = :tenant_id");
            $sortStmt->execute([':attribute_id' => $attribute_id, ':tenant_id' => $tenant_id]);
            $nextSort = (int) $sortStmt->fetchColumn();

            if ($hasDesc) {
                $stmt = $pdo->prepare("INSERT INTO attribute_values (tenant_id, attribute_id, value, label, description, color_hex, sort_order, created_at) VALUES (:tenant_id, :attribute_id, :value, :label, :description, :color_hex, :sort_order, NOW())");
                $stmt->execute([
                    ':tenant_id' => $tenant_id, ':attribute_id' => $attribute_id,
                    ':value' => $name, ':label' => $slug, ':description' => $description,
                    ':color_hex' => $color_hex, ':sort_order' => $nextSort
                ]);
            } else {
                $stmt = $pdo->prepare("INSERT INTO attribute_values (tenant_id, attribute_id, value, label, color_hex, sort_order, created_at) VALUES (:tenant_id, :attribute_id, :value, :label, :color_hex, :sort_order, NOW())");
                $stmt->execute([
                    ':tenant_id' => $tenant_id, ':attribute_id' => $attribute_id,
                    ':value' => $name, ':label' => $slug,
                    ':color_hex' => $color_hex, ':sort_order' => $nextSort
                ]);
            }
            $newId = (int) $pdo->lastInsertId();
            return ['success' => true, 'data' => ['id' => $newId, 'message' => 'Term created']];
        }
    } catch (PDOException $e) {
        error_log("ajax_save_term error: " . $e->getMessage());
        return ['success' => false, 'error' => 'Database error'];
    }
}

function ajax_delete_term(PDO $pdo, int $tenant_id, int $attribute_id): array {
    $id = intval($_POST['id'] ?? 0);
    if (!$id) return ['success' => false, 'error' => 'Missing ID'];
    try {
        $stmt = $pdo->prepare("DELETE FROM attribute_values WHERE id = :id AND attribute_id = :attribute_id AND tenant_id = :tenant_id");
        $stmt->execute([':id' => $id, ':attribute_id' => $attribute_id, ':tenant_id' => $tenant_id]);
        return ['success' => true, 'data' => ['deleted' => $stmt->rowCount() > 0]];
    } catch (PDOException $e) {
        return ['success' => false, 'error' => 'Database error'];
    }
}

function ajax_reorder_terms(PDO $pdo, int $tenant_id, int $attribute_id): array {
    $orders = json_decode($_POST['orders'] ?? '[]', true);
    if (!is_array($orders) || empty($orders)) {
        return ['success' => false, 'error' => 'Invalid order data'];
    }
    try {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare("UPDATE attribute_values SET sort_order = :sort_order WHERE id = :id AND attribute_id = :attribute_id AND tenant_id = :tenant_id");
        foreach ($orders as $item) {
            $stmt->execute([
                ':sort_order' => (int)($item['sort_order'] ?? 0),
                ':id' => (int)($item['id'] ?? 0),
                ':attribute_id' => $attribute_id,
                ':tenant_id' => $tenant_id
            ]);
        }
        $pdo->commit();
        return ['success' => true, 'data' => ['message' => 'Order updated']];
    } catch (PDOException $e) {
        $pdo->rollBack();
        return ['success' => false, 'error' => 'Database error'];
    }
}

// Get initial terms for SSR
$initial_terms = [];
try {
    $tstmt = $pdo->prepare("SELECT * FROM attribute_values WHERE attribute_id = :attribute_id AND tenant_id = :tenant_id AND branch_id = $current_branch_id ORDER BY sort_order ASC, id ASC");
    $tstmt->execute([':attribute_id' => $attribute_id, ':tenant_id' => $tenant_id]);
    $initial_terms = $tstmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Initial terms load error: " . $e->getMessage());
}

$is_color = $attr['type'] === 'color';

ob_start();
?>
<?php
$inp_t = 'w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-1 focus:ring-amber-500 transition-colors';
$lbl_t = 'block text-xs font-medium text-slate-400 uppercase tracking-wide mb-1';
$hint_t = 'block text-xs text-slate-600 mt-1';
?>

<!-- Toast -->
<div id="term-toast" class="hidden fixed bottom-4 right-4 z-50 flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-medium shadow-2xl transition-all">
    <span id="term-toast-msg"></span>
</div>

<!-- Breadcrumb -->
<nav class="flex items-center gap-2 text-sm text-slate-500 mb-3">
    <a href="attributes.php" class="text-blue-400 hover:text-amber-400 transition-colors">Attributes</a>
    <span>/</span>
    <span class="text-slate-300"><?php echo htmlspecialchars($attr['name']); ?> Terms</span>
</nav>

<!-- Toolbar -->
<div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-5">
    <div>
        <p class="text-xs font-medium text-amber-400 uppercase tracking-wide mb-0.5">Attribute Terms</p>
        <h1 class="text-lg font-bold text-white flex items-center gap-2">
            <?php echo htmlspecialchars($attr['name']); ?>
            <span class="inline-flex items-center justify-center min-w-[1.5rem] h-6 rounded-md bg-blue-500/15 text-blue-400 text-xs font-bold px-1.5"><?php echo count($initial_terms); ?></span>
        </h1>
        <p class="text-sm text-slate-500 mt-0.5">Managing terms in <span class="text-amber-400"><?php echo htmlspecialchars($branch_name); ?></span></p>
    </div>
    <div class="flex flex-wrap gap-2">
        <a href="attributes.php" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-slate-700 border border-slate-600 text-slate-300 text-sm font-medium hover:bg-slate-600 transition-colors">
            <i class="fas fa-arrow-left text-xs"></i> Back to Attributes
        </a>
        <a href="attribute_form.php?id=<?php echo (int)$attr['id']; ?>" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-slate-700 border border-slate-600 text-slate-300 text-sm font-medium hover:bg-slate-600 transition-colors">
            <i class="fas fa-edit text-xs"></i> Edit Attribute
        </a>
    </div>
</div>

<!-- Notice -->
<div class="flex items-start gap-3 px-4 py-3 mb-5 rounded-xl bg-blue-500/5 border border-blue-500/20 text-slate-400 text-sm">
    <i class="fas fa-info-circle text-blue-400 mt-0.5"></i>
    <p><span class="text-amber-400 font-medium">Note:</span> Deleting a term removes it from all products and variations. Recreating a term will not automatically re-assign it.</p>
</div>

<!-- Two Column Layout -->
<div class="flex flex-col lg:flex-row gap-5">
    <!-- Main: Terms Table -->
    <div class="flex-1 min-w-0">
        <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl overflow-hidden">
            <table class="w-full text-sm" id="terms-table">
                <thead class="bg-slate-800/60 border-b border-slate-700/40">
                    <tr>
                        <th class="w-8 px-3 py-3"></th>
                        <th class="px-3 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Name</th>
                        <th class="px-3 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Slug</th>
                        <?php if ($is_color): ?><th class="px-3 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Color</th><?php endif; ?>
                        <th class="px-3 py-3 text-center text-xs font-semibold text-slate-500 uppercase tracking-wider w-20">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-700/40" id="terms-tbody">
                    <?php if (empty($initial_terms)): ?>
                    <tr>
                        <td colspan="<?php echo $is_color ? 5 : 4; ?>" class="px-4 py-14 text-center">
                            <i class="fas fa-list text-3xl text-slate-700 mb-3 block"></i>
                            <p class="text-slate-400 font-medium">No terms found</p>
                            <p class="text-slate-500 text-xs mt-1">Add your first term using the form on the right.</p>
                        </td>
                    </tr>
                    <?php else: foreach ($initial_terms as $term): ?>
                    <tr data-id="<?php echo (int)$term['id']; ?>" draggable="true" class="hover:bg-slate-700/20 transition-colors">
                        <td class="px-3 py-2.5"><i class="fas fa-grip-vertical text-slate-600 cursor-grab"></i></td>
                        <td class="px-3 py-2.5">
                            <div class="flex items-center gap-2">
                                <?php if ($is_color && $term['color_hex']): ?>
                                <span class="w-5 h-5 rounded border border-white/15 inline-block flex-shrink-0" style="background-color:<?php echo htmlspecialchars($term['color_hex']); ?>"></span>
                                <?php endif; ?>
                                <span class="font-medium text-slate-200"><?php echo htmlspecialchars($term['value']); ?></span>
                            </div>
                        </td>
                        <td class="px-3 py-2.5"><code class="font-mono text-xs text-slate-500"><?php echo htmlspecialchars($term['label'] ?: $term['value']); ?></code></td>
                        <?php if ($is_color): ?>
                        <td class="px-3 py-2.5"><code class="font-mono text-xs text-slate-500"><?php echo htmlspecialchars($term['color_hex'] ?: '—'); ?></code></td>
                        <?php endif; ?>
                        <td class="px-3 py-2.5">
                            <div class="flex items-center justify-center gap-1">
                                <button onclick="editTerm(<?php echo (int)$term['id']; ?>)" title="Edit" class="p-1.5 rounded-lg text-slate-400 hover:text-amber-400 hover:bg-slate-700/60 transition-colors"><i class="fas fa-pen text-xs"></i></button>
                                <button onclick="deleteTerm(<?php echo (int)$term['id']; ?>, <?php echo json_encode($term['value']); ?>)" title="Delete" class="p-1.5 rounded-lg text-slate-400 hover:text-red-400 hover:bg-red-500/10 transition-colors"><i class="fas fa-trash text-xs"></i></button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Sidebar: Add Term Form -->
    <div class="w-full lg:w-96 flex-shrink-0">
        <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-5" id="add-term-panel">
            <h3 class="text-sm font-semibold text-white mb-4" id="form-title">Add new <?php echo htmlspecialchars($attr['name']); ?></h3>
            <form id="term-form" onsubmit="return false" class="space-y-4">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                <input type="hidden" name="id" id="term-id" value="">
                <div>
                    <label for="term-name" class="<?php echo $lbl_t; ?>">Name <span class="text-red-400">*</span></label>
                    <input type="text" id="term-name" name="name" class="<?php echo $inp_t; ?>" placeholder="e.g. Black, Large, Cotton" autocomplete="off" required>
                    <span class="<?php echo $hint_t; ?>">The name is how it appears on your site.</span>
                </div>
                <div>
                    <label for="term-slug" class="<?php echo $lbl_t; ?>">Slug</label>
                    <input type="text" id="term-slug" name="slug" class="<?php echo $inp_t; ?>" placeholder="auto-generated from name" autocomplete="off">
                    <span class="<?php echo $hint_t; ?>">URL-friendly lowercase version. Auto-generated if left blank.</span>
                </div>
                <div>
                    <label for="term-description" class="<?php echo $lbl_t; ?>">Description</label>
                    <textarea id="term-description" name="description" rows="3" class="<?php echo $inp_t; ?> resize-none" placeholder="Optional description..."></textarea>
                </div>
                <?php if ($is_color): ?>
                <div>
                    <label for="term-color" class="<?php echo $lbl_t; ?>">Color</label>
                    <div class="flex items-center gap-3">
                        <input type="color" id="term-color" name="color_hex" value="#000000" class="w-12 h-9 border border-slate-700 rounded-lg bg-transparent cursor-pointer p-0.5">
                        <input type="text" id="term-color-hex" class="<?php echo $inp_t; ?>" placeholder="#000000" value="#000000" onchange="document.getElementById('term-color').value = this.value">
                    </div>
                    <span class="<?php echo $hint_t; ?>">Select or enter a hex color code.</span>
                </div>
                <?php endif; ?>
                <div class="flex gap-2 pt-2">
                    <button type="button" onclick="resetForm()" id="btn-cancel" style="display:none" class="flex-1 inline-flex items-center justify-center gap-1.5 px-3 py-2 rounded-lg bg-slate-700 border border-slate-600 text-slate-300 text-sm font-medium hover:bg-slate-600 transition-colors">Cancel</button>
                    <button type="button" onclick="saveTerm()" id="btn-save" class="flex-1 inline-flex items-center justify-center gap-1.5 px-3 py-2 rounded-lg bg-amber-500/15 border border-amber-500/40 text-amber-400 text-sm font-semibold hover:bg-amber-500/25 transition-colors">
                        <i class="fas fa-plus text-xs"></i> Add Term
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Modal -->
<div id="deleteModal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/60 ">
    <div class="bg-slate-800 border border-slate-700 rounded-xl p-6 w-full max-w-sm mx-4 shadow-2xl">
        <div class="flex items-center gap-3 mb-4">
            <div class="w-10 h-10 rounded-full bg-red-500/10 border border-red-500/30 flex items-center justify-center flex-shrink-0">
                <i class="fas fa-trash text-red-400 text-sm"></i>
            </div>
            <h3 class="text-base font-semibold text-white">Delete Term</h3>
        </div>
        <p class="text-slate-400 text-sm mb-2">Are you sure you want to delete <strong id="deleteTermName" class="text-amber-400">this term</strong>?</p>
        <p class="text-xs text-slate-500 mb-5">This will remove the term from all products and variations. This action cannot be undone.</p>
        <div class="flex gap-2">
            <button onclick="closeDeleteModal()" class="flex-1 inline-flex items-center justify-center px-3 py-2 rounded-lg bg-slate-700 border border-slate-600 text-slate-300 text-sm font-medium hover:bg-slate-600 transition-colors">Cancel</button>
            <button onclick="confirmDelete()" class="flex-1 inline-flex items-center justify-center px-3 py-2 rounded-lg bg-red-500/10 border border-red-500/30 text-red-400 text-sm font-medium hover:bg-red-500/20 transition-colors">Delete</button>
        </div>
    </div>
</div>

<script>
window.TERM_CONFIG = {
    attribute_id: <?php echo (int)$attribute_id; ?>,
    attr_name: <?php echo json_encode($attr['name']); ?>,
    csrf_token: '<?php echo htmlspecialchars($csrf_token); ?>',
    ajax_url: 'attribute_terms.php?attribute_id=<?php echo (int)$attribute_id; ?>',
    is_color: <?php echo $is_color ? 'true' : 'false'; ?>
};
</script>
<script src="<?php echo asset_url('js/attribute_terms.js'); ?>?v=2"></script>

<?php
$page_content = ob_get_clean();
require_once __DIR__ . '/../layouts/app.php';
