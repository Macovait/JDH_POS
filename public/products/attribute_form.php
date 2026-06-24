<?php
/**
 * Attribute Form page for Jakababa POS - SaaS Version
 * Uses the new attributes module tables
 */

require_once __DIR__ . '/../../src/paths.php';
safe_require('auth.php', 'src', true);
safe_require('db.php', 'src', true);
safe_require('functions.php', 'src', true);

// Branch filter for multi-tenant isolation
$current_branch_id = get_current_branch_id();

require_login();

if (!check_permission('products.manage') && !is_super_admin()) {
    enforce_permission('products.manage');
}

$pdo = get_db_connection();
$tenant_id = get_current_tenant_id();
if (!$tenant_id) {
    http_response_code(403);
    exit('Company context missing. Please log in again.');
}

$user_id = get_current_user_id();
$branch_name = get_current_branch_name();

$csrf_token = generate_csrf_token();

$id = intval($_GET['id'] ?? 0);
$errors = [];
$success = '';
$existing_values = [];

// Defaults
$attr = [
    'name' => '',
    'code' => '',
    'type' => 'text',
    'group_id' => null,
    'unit' => '',
    'status' => 1,
    'sort_order' => 0,
    'is_required' => 0,
    'is_filterable' => 0,
    'is_variant_forming' => 0,
    'business_type_id' => null,
];

// Load groups and business types for dropdowns
$groups = [];
$business_types = [];
try {
    $gstmt = $pdo->prepare("SELECT id, name FROM attribute_groups WHERE tenant_id = :tenant_id AND deleted_at IS NULL AND branch_id = $current_branch_id ORDER BY sort_order, name");
    $gstmt->execute([':tenant_id' => $tenant_id]);
    $groups = $gstmt->fetchAll(PDO::FETCH_ASSOC);

    $bstmt = $pdo->prepare("SELECT id, name FROM business_types WHERE active = 1 AND branch_id = $current_branch_id ORDER BY name");
    $bstmt->execute();
    $business_types = $bstmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Groups/BT load error: " . $e->getMessage());
}

if ($id) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM attributes WHERE id = :id AND tenant_id = :tenant_id AND deleted_at IS NULL");
        $stmt->execute([':id' => $id, ':tenant_id' => $tenant_id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $attr = array_merge($attr, $row);
            // Load existing values for display
            $vstmt = $pdo->prepare("SELECT value, label, color_hex FROM attribute_values WHERE attribute_id = :id AND tenant_id = :tenant_id AND branch_id = $current_branch_id ORDER BY sort_order, id");
            $vstmt->execute([':id' => $id, ':tenant_id' => $tenant_id]);
            $existing_values = $vstmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $errors[] = 'Attribute not found';
        }
    } catch (PDOException $e) {
        error_log("Error loading attribute: " . $e->getMessage());
        $errors[] = 'Failed to load attribute';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($errors)) {
    try {
        csrf_verify();
    } catch (Exception $e) {
        $errors[] = 'Invalid or expired CSRF token. Please refresh the page and try again.';
    }

    $name = trim($_POST['name'] ?? '');
    $code = trim($_POST['code'] ?? '');
    $type = $_POST['type'] ?? 'text';
    $group_id = $_POST['group_id'] !== '' ? (int) $_POST['group_id'] : null;
    $unit = trim($_POST['unit'] ?? '');
    $status = isset($_POST['status']) ? 1 : 0;
    $sort_order = intval($_POST['sort_order'] ?? 0);
    $is_required = isset($_POST['is_required']) ? 1 : 0;
    $is_filterable = isset($_POST['is_filterable']) ? 1 : 0;
    $is_variant_forming = isset($_POST['is_variant_forming']) ? 1 : 0;
    $business_type_id = $_POST['business_type_id'] !== '' ? (int) $_POST['business_type_id'] : null;

    // Auto-generate code from name if empty
    if ($code === '' && $name !== '') {
        $code = preg_replace('/[^a-z0-9]/', '_', strtolower($name));
        $code = preg_replace('/_+/', '_', $code);
        $code = trim($code, '_');
    }

    if ($name === '' || $code === '') {
        $errors[] = 'Attribute name and code are required';
    }

    $allowed_types = ['text', 'textarea', 'dropdown', 'multiselect', 'number', 'color', 'file', 'date', 'boolean'];
    if (!in_array($type, $allowed_types, true)) {
        $errors[] = 'Invalid attribute type';
    }

    if (empty($errors)) {
        try {
            // Duplicate name check
            $checkName = $pdo->prepare("SELECT id FROM attributes WHERE tenant_id = :tenant_id AND name = :name AND deleted_at IS NULL" . ($id ? " AND id != :id" : ""));
            $cp = [':tenant_id' => $tenant_id, ':name' => $name];
            if ($id) $cp[':id'] = $id;
            $checkName->execute($cp);
            if ($checkName->fetch()) {
                $errors[] = 'An attribute with this name already exists. Please use a different name.';
            }

            // Duplicate code check
            if (empty($errors)) {
                $checkCode = $pdo->prepare("SELECT id FROM attributes WHERE tenant_id = :tenant_id AND code = :code AND deleted_at IS NULL" . ($id ? " AND id != :id" : ""));
                $cp2 = [':tenant_id' => $tenant_id, ':code' => $code];
                if ($id) $cp2[':id'] = $id;
                $checkCode->execute($cp2);
                if ($checkCode->fetch()) {
                    $errors[] = 'An attribute with this code already exists. Please use a different code.';
                }
            }

            if (empty($errors)) {
                $data = [
                    ':tenant_id' => $tenant_id,
                    ':name' => $name,
                    ':code' => $code,
                    ':type' => $type,
                    ':unit' => $unit ?: null,
                    ':group_id' => $group_id,
                    ':is_required' => $is_required,
                    ':is_filterable' => $is_filterable,
                    ':is_variant_forming' => $is_variant_forming,
                    ':sort_order' => $sort_order,
                    ':status' => $status,
                    ':updated_by' => $user_id,
                    ':business_type_id' => $business_type_id,
                ];

                if ($id) {
                    $stmt = $pdo->prepare("UPDATE attributes SET name = :name, code = :code, type = :type, unit = :unit, group_id = :group_id, is_required = :is_required, is_filterable = :is_filterable, is_variant_forming = :is_variant_forming, business_type_id = :business_type_id, sort_order = :sort_order, status = :status, updated_by = :updated_by, updated_at = NOW() WHERE id = :id AND tenant_id = :tenant_id");
                    $data[':id'] = $id;
                    $stmt->execute($data);
                    $success = 'Attribute updated successfully';
                    log_activity($user_id, 'attribute_update', ['attribute_id' => $id, 'name' => $name], $tenant_id);
                } else {
                    $data[':created_by'] = $user_id;
                    $stmt = $pdo->prepare("INSERT INTO attributes (tenant_id, name, code, type, unit, group_id, is_required, is_filterable, is_variant_forming, business_type_id, sort_order, status, created_by, updated_by, created_at, updated_at) VALUES (:tenant_id, :name, :code, :type, :unit, :group_id, :is_required, :is_filterable, :is_variant_forming, :business_type_id, :sort_order, :status, :created_by, :updated_by, NOW(), NOW())");
                    $stmt->execute($data);
                    $success = 'Attribute created successfully';
                    log_activity($user_id, 'attribute_create', ['name' => $name], $tenant_id);
                }

                if ($success) {
                    header('Location: attributes.php?success=' . urlencode($success));
                    exit;
                }
            }
        } catch (PDOException $e) {
            error_log("Error saving attribute: " . $e->getMessage());
            $errors[] = 'Database error: ' . $e->getMessage();
        }
    }

    // Repopulate form on error
    $attr = array_merge($attr, [
        'name' => $_POST['name'] ?? '',
        'code' => $_POST['code'] ?? '',
        'type' => $_POST['type'] ?? 'text',
        'group_id' => $_POST['group_id'] !== '' ? (int) $_POST['group_id'] : null,
        'unit' => $_POST['unit'] ?? '',
        'status' => isset($_POST['status']) ? 1 : 0,
        'sort_order' => intval($_POST['sort_order'] ?? 0),
        'is_required' => isset($_POST['is_required']) ? 1 : 0,
        'is_filterable' => isset($_POST['is_filterable']) ? 1 : 0,
        'is_variant_forming' => isset($_POST['is_variant_forming']) ? 1 : 0,
        'business_type_id' => $_POST['business_type_id'] !== '' ? (int) $_POST['business_type_id'] : null,
    ]);
}

$page_title = ($id ? 'Edit' : 'Add') . ' Attribute | ' . ($branch_name ?? 'Jakababa POS');
ob_start();
?>

<div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-5">
    <div>
        <a href="attributes.php" class="inline-flex items-center gap-1.5 text-sm text-slate-500 hover:text-amber-400 transition-colors mb-2">
            <i class="fas fa-arrow-left text-xs"></i> Back to Attributes
        </a>
        <h1 class="text-lg font-bold text-white flex items-center gap-2">
            <i class="fas fa-<?php echo $id ? 'pen' : 'plus-circle'; ?> text-amber-400"></i>
            <?php echo $id ? 'Edit Attribute' : 'Add New Attribute'; ?>
        </h1>
        <p class="text-sm text-slate-500 mt-0.5">
            <?php echo $id ? 'Update attribute information' : 'Create a new product attribute'; ?>
            &mdash; <span class="text-amber-400"><?php echo htmlspecialchars($branch_name); ?></span>
        </p>
    </div>
</div>

<?php if (!empty($errors)): ?>
<div class="flex items-start gap-3 px-4 py-3 mb-4 rounded-xl bg-red-500/10 border border-red-500/30 text-red-400 text-sm">
    <i class="fas fa-exclamation-triangle mt-0.5"></i>
    <ul class="list-disc list-inside space-y-0.5">
        <?php foreach ($errors as $err): ?>
        <li><?php echo htmlspecialchars($err); ?></li>
        <?php endforeach; ?>
    </ul>
</div>
<?php endif; ?>

<?php if ($id && in_array($attr['type'] ?? '', ['dropdown','multiselect','color'])): ?>
<div class="flex items-center justify-between px-4 py-3 mb-4 rounded-xl bg-blue-500/10 border border-blue-500/30">
    <div class="flex items-center gap-3">
        <i class="fas fa-cog text-blue-400 text-sm"></i>
        <div>
            <p class="text-white font-medium text-sm">This attribute uses predefined terms</p>
            <p class="text-slate-400 text-xs">Manage the available values (terms) for this attribute</p>
        </div>
    </div>
    <a href="attribute_terms.php?attribute_id=<?php echo (int)$attr['id']; ?>" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-slate-700 border border-slate-600 text-slate-300 text-sm font-medium hover:bg-slate-600 transition-colors">
        <i class="fas fa-list text-xs"></i> Configure Terms
    </a>
</div>
<?php endif; ?>

<!-- Attribute Form -->
<div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-5">
    <form method="post" class="space-y-5">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
        <?php $inp = 'w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-1 focus:ring-amber-500 transition-colors';
              $lbl = 'block text-xs font-medium text-slate-400 uppercase tracking-wide mb-1'; ?>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label for="name" class="<?php echo $lbl; ?>"><i class="fas fa-tag text-amber-500 mr-1"></i>Name <span class="text-red-400">*</span></label>
                <input type="text" id="name" name="name" required autocomplete="off" value="<?php echo htmlspecialchars($attr['name']); ?>" placeholder="e.g., Color, Size, Material" class="<?php echo $inp; ?>">
            </div>
            <div>
                <label for="code" class="<?php echo $lbl; ?>"><i class="fas fa-code text-amber-500 mr-1"></i>Code</label>
                <input type="text" id="code" name="code" autocomplete="off" value="<?php echo htmlspecialchars($attr['code']); ?>" placeholder="Auto-generated from name" class="<?php echo $inp; ?>">
                <p class="text-xs text-slate-600 mt-1">Leave blank to auto-generate</p>
            </div>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
                <label for="type" class="<?php echo $lbl; ?>"><i class="fas fa-list text-amber-500 mr-1"></i>Type</label>
                <select id="type" name="type" class="<?php echo $inp; ?>">
                    <?php $types = ['text'=>'Text','textarea'=>'Textarea','dropdown'=>'Dropdown','multiselect'=>'Multi-Select','number'=>'Number','color'=>'Color','file'=>'File','date'=>'Date','boolean'=>'Yes/No'];
                    foreach ($types as $k=>$v): ?>
                    <option value="<?php echo $k; ?>" <?php echo $attr['type']===$k?'selected':''; ?>><?php echo $v; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="group_id" class="<?php echo $lbl; ?>"><i class="fas fa-folder text-amber-500 mr-1"></i>Group</label>
                <select id="group_id" name="group_id" class="<?php echo $inp; ?>">
                    <option value="">-- None --</option>
                    <?php foreach ($groups as $g): ?>
                    <option value="<?php echo $g['id']; ?>" <?php echo ($attr['group_id']==$g['id'])?'selected':''; ?>><?php echo htmlspecialchars($g['name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="unit" class="<?php echo $lbl; ?>"><i class="fas fa-ruler text-amber-500 mr-1"></i>Unit</label>
                <input type="text" id="unit" name="unit" autocomplete="off" value="<?php echo htmlspecialchars($attr['unit'] ?? ''); ?>" placeholder="e.g. kg, cm, pcs" class="<?php echo $inp; ?>">
            </div>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label for="business_type_id" class="<?php echo $lbl; ?>"><i class="fas fa-briefcase text-amber-500 mr-1"></i>Business Type</label>
                <select id="business_type_id" name="business_type_id" class="<?php echo $inp; ?>">
                    <option value="">-- All --</option>
                    <?php foreach ($business_types as $bt): ?>
                    <option value="<?php echo $bt['id']; ?>" <?php echo ($attr['business_type_id']==$bt['id'])?'selected':''; ?>><?php echo htmlspecialchars($bt['name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="sort_order" class="<?php echo $lbl; ?>"><i class="fas fa-sort-numeric-down text-amber-500 mr-1"></i>Sort Order</label>
                <input type="number" id="sort_order" name="sort_order" min="0" autocomplete="off" value="<?php echo (int)($attr['sort_order'] ?? 0); ?>" class="<?php echo $inp; ?>">
            </div>
        </div>
        <div class="flex flex-wrap gap-4">
            <?php foreach (['status'=>['Active',(bool)($attr['status']??1)],'is_required'=>['Required',(bool)($attr['is_required']??0)],'is_filterable'=>['Filterable',(bool)($attr['is_filterable']??0)],'is_variant_forming'=>['Variant Forming',(bool)($attr['is_variant_forming']??0)]] as $fname=>[$flabel,$fchecked]): ?>
            <label class="flex items-center gap-2 cursor-pointer">
                <input type="checkbox" name="<?php echo $fname; ?>" id="<?php echo $fname; ?>" value="1" <?php echo $fchecked?'checked':''; ?> class="w-3.5 h-3.5 rounded accent-amber-500">
                <span class="text-sm text-slate-400"><?php echo $flabel; ?></span>
            </label>
            <?php endforeach; ?>
        </div>
        <div class="flex flex-col sm:flex-row gap-3 pt-4 border-t border-slate-700/60">
            <button type="submit" class="flex-1 inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-lg bg-amber-500/15 border border-amber-500/40 text-amber-400 text-sm font-semibold hover:bg-amber-500/25 transition-colors">
                <i class="fas fa-<?php echo $id ? 'save' : 'plus'; ?> text-xs"></i><?php echo $id ? 'Update Attribute' : 'Create Attribute'; ?>
            </button>
            <a href="attributes.php" class="flex-1 inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-lg bg-slate-700 border border-slate-600 text-slate-300 text-sm font-medium hover:bg-slate-600 transition-colors">
                <i class="fas fa-times text-xs"></i> Cancel
            </a>
        </div>
    </form>
</div>

<?php if ($id && !empty($existing_values)): ?>
<div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-5 mt-4">
    <h3 class="text-sm font-semibold text-white flex items-center gap-2 mb-3">
        <i class="fas fa-list text-blue-400"></i>Existing Terms
        <span class="text-xs text-slate-500 font-normal">(<?php echo count($existing_values); ?> total)</span>
    </h3>
    <div class="flex flex-wrap gap-2">
        <?php foreach ($existing_values as $v): ?>
        <span class="inline-flex items-center gap-2 px-2.5 py-1 rounded-lg text-xs bg-slate-900/50 border border-slate-700/40 text-slate-300">
            <?php if (!empty($v['color_hex'])): ?>
            <span class="w-3 h-3 rounded-full inline-block border border-slate-600" style="background-color:<?php echo htmlspecialchars($v['color_hex']); ?>"></span>
            <?php endif; ?>
            <?php echo htmlspecialchars($v['label'] ?: $v['value']); ?>
        </span>
        <?php endforeach; ?>
    </div>
    <p class="text-xs text-slate-600 mt-3">
        <i class="fas fa-info-circle mr-1"></i>Use <a href="attribute_terms.php?attribute_id=<?php echo (int)$attr['id']; ?>" class="text-blue-400 hover:text-amber-400 transition-colors">Configure Terms</a> to add, edit, or reorder values.
    </p>
</div>
<?php endif; ?>

<div class="bg-amber-500/5 border border-amber-500/20 rounded-xl p-4 mt-4 text-sm text-slate-400">
    <div class="flex items-start gap-3">
        <i class="fas fa-info-circle text-amber-500 mt-0.5"></i>
        <ul class="space-y-1 list-disc list-inside">
            <li>Attributes are custom fields that can be assigned to products</li>
            <li>Common attributes: Color, Size, Material, Weight, Brand</li>
            <li>The code is used internally; leave blank to auto-generate from the name</li>
            <li>Variant Forming attributes generate product variants (e.g., Size, Color)</li>
        </ul>
    </div>
</div>

<script>
    const nameInput = document.getElementById('name');
    const codeInput = document.getElementById('code');

    nameInput?.addEventListener('input', function() {
        if (!codeInput.value || codeInput.dataset.auto === 'true') {
            let code = this.value.toLowerCase().replace(/[^a-z0-9]/g, '_').replace(/_+/g, '_').replace(/^_|_$/g, '');
            codeInput.value = code;
            codeInput.dataset.auto = 'true';
        }
    });

    codeInput?.addEventListener('input', function() {
        codeInput.dataset.auto = this.value ? 'false' : 'true';
    });

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            window.location.href = 'attributes.php';
        }
    });
</script>

<?php
$page_content = ob_get_clean();
require_once __DIR__ . '/../layouts/app.php';