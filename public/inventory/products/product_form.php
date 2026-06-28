<?php
/**
 * Product Form page for Jakababa POS
 */

require_once __DIR__ . '/../../src/auth.php';
require_login();

if (!check_permission('products.manage') && !is_super_admin()) {
    enforce_permission('products.manage');
}

require_once __DIR__ . '/../../src/db.php';

$pdo = get_db_connection();

$user_id = (int) ($_SESSION['user']['id'] ?? 0);
$user_name = htmlspecialchars($_SESSION['user']['name'] ?? 'User');
$user_role = $_SESSION['user']['role'] ?? '';
$branch_id = get_current_branch_id();
$branch_name = get_current_branch_name();

$branches = [];
if (is_super_admin() || $user_role === 'Admin' || check_permission('branches.view')) {
    try { $stmt = $pdo->query("SELECT id, name FROM branches WHERE active = 1 ORDER BY name"); $branches = $stmt->fetchAll(); } catch (PDOException $e) { error_log("Error fetching branches: " . $e->getMessage()); }
}

$id = intval($_GET['id'] ?? 0);
$name = ''; $sku = ''; $price = 0; $cost_price = 0; $category_id = null; $image = null; $active = 1; $error = ''; $success = ''; $description = '';

if ($id) {
    try {
        $stmt = $pdo->prepare('SELECT * FROM products WHERE id = ? AND deleted_at IS NULL');
        $stmt->execute([$id]);
        $product = $stmt->fetch();
        if ($product) { $name = $product['name']; $sku = $product['sku']; $price = $product['price']; $cost_price = $product['cost_price']; $category_id = $product['category_id']; $image = $product['image']; $active = $product['active']; $description = $product['description'] ?? ''; }
        else { $error = 'Product not found'; }
    } catch (PDOException $e) { error_log("Error loading product: " . $e->getMessage()); $error = 'Failed to load product'; }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? ''); $sku = trim($_POST['sku'] ?? ''); $price = floatval($_POST['price'] ?? 0); $cost_price = floatval($_POST['cost_price'] ?? 0);
    $category_id = !empty($_POST['category_id']) ? intval($_POST['category_id']) : null; $active = isset($_POST['active']) ? 1 : 0; $description = trim($_POST['description'] ?? '');
    $errors = [];
    if (empty($name)) $errors[] = 'Product name is required';
    if ($price <= 0) $errors[] = 'Price must be greater than 0';
    if ($cost_price < 0) $errors[] = 'Cost price cannot be negative';

    if (!empty($sku)) {
        if ($id) { $stmt = $pdo->prepare('SELECT id FROM products WHERE sku = ? AND id != ? AND deleted_at IS NULL'); $stmt->execute([$sku, $id]); }
        else { $stmt = $pdo->prepare('SELECT id FROM products WHERE sku = ? AND deleted_at IS NULL'); $stmt->execute([$sku]); }
        if ($stmt->fetch()) $errors[] = 'SKU already exists. Please use a unique SKU.';
    }

    $imagePath = $image;
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp']; $max_size = 2 * 1024 * 1024;
        $file_info = finfo_open(FILEINFO_MIME_TYPE); $mime_type = finfo_file($file_info, $_FILES['image']['tmp_name']); finfo_close($file_info);
        if (!in_array($mime_type, $allowed_types)) $errors[] = 'Invalid image type. Allowed: JPG, PNG, GIF, WEBP';
        elseif ($_FILES['image']['size'] > $max_size) $errors[] = 'Image size must be less than 2MB';
        else {
            $upload_dir = __DIR__ . '/../uploads/product_images/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
            $extension = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
            $filename = uniqid('product_') . '_' . time() . '.' . $extension;
            $destination = $upload_dir . $filename;
            if (move_uploaded_file($_FILES['image']['tmp_name'], $destination)) {
                if ($image && file_exists(__DIR__ . '/..' . $image)) unlink(__DIR__ . '/..' . $image);
                $imagePath = '/uploads/product_images/' . $filename;
            } else { $errors[] = 'Failed to upload image'; }
        }
    } elseif (isset($_POST['remove_image']) && $_POST['remove_image'] === '1') {
        if ($image && file_exists(__DIR__ . '/..' . $image)) unlink(__DIR__ . '/..' . $image);
        $imagePath = null;
    }

    // Enforce product quota on creation
    if (empty($errors) && !$id) {
        require_once __DIR__ . '/../../src/Security/PlanEnforcement.php';
        PlanEnforcement::loadTenantPlan($pdo, $tenant_id);
        $countStmt = $pdo->prepare("SELECT COUNT(*) FROM products WHERE tenant_id = ? AND deleted_at IS NULL");
        $countStmt->execute([$tenant_id]);
        $productCount = (int) $countStmt->fetchColumn();
        if (!PlanEnforcement::checkLimit('max_products', $productCount)) {
            $errors[] = 'Product limit reached for your plan. Please upgrade to add more products.';
        }
    }

    if (empty($errors)) {
        try {
            $pdo->beginTransaction();
            if ($id) {
                $stmt = $pdo->prepare('UPDATE products SET name=?, sku=?, price=?, cost_price=?, category_id=?, image=?, active=?, description=?, updated_at=NOW() WHERE id=? AND tenant_id=?');
                $stmt->execute([$name, $sku, $price, $cost_price, $category_id, $imagePath, $active, $description, $id, $tenant_id]);
                log_activity($user_id, 'product_update', ['product_id' => $id, 'product_name' => $name, 'description' => "Updated product: $name"], get_current_tenant_id());
                $success = 'Product updated successfully';
            } else {
                $stmt = $pdo->prepare('INSERT INTO products (tenant_id, name, sku, price, cost_price, category_id, active, image, description, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())');
                $stmt->execute([$tenant_id, $name, $sku, $price, $cost_price, $category_id, $active, $imagePath, $description]);
                $id = $pdo->lastInsertId();
                $stmt = $pdo->prepare('INSERT INTO inventory (tenant_id, product_id, branch_id, stock, reorder_level, created_at, updated_at) SELECT ?, ?, id, 0, 5, NOW(), NOW() FROM branches WHERE active = 1 AND tenant_id = ?');
                $stmt->execute([$tenant_id, $id, $tenant_id]);
                log_activity($user_id, 'product_create', ['product_id' => $id, 'product_name' => $name, 'description' => "Created new product: $name"], get_current_tenant_id());
                $success = 'Product created successfully';
            }
            $pdo->commit();
        } catch (PDOException $e) { $pdo->rollBack(); error_log("Error saving product: " . $e->getMessage()); $errors[] = 'Database error: Failed to save product'; }
    }
}

$categories = [];
try { $stmt = $pdo->prepare('SELECT id, name FROM categories WHERE tenant_id = ? AND (status = "active" OR status IS NULL) ORDER BY name'); $stmt->execute([$tenant_id]); $categories = $stmt->fetchAll(); } catch (PDOException $e) { error_log("Error fetching categories: " . $e->getMessage()); }

$page_title = ($id ? 'Edit' : 'Add') . ' Product | Jakababa POS';
ob_start();
?>

<style>
    .form-input { transition: all 0.2s ease; }
    .form-input:focus { border-color: #FBBF24; box-shadow: 0 0 0 3px rgba(251, 191, 36, 0.2); }
    .image-preview { transition: all 0.3s ease; }
    .image-preview:hover { opacity: 0.9; }
    .gradient-text { background: linear-gradient(135deg, #FBBF24 0%, #F59E0B 100%); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text; }
</style>

<div class="fade-in">

    <!-- Header -->
    <div class="mb-6 ">
        <h1 class="text-3xl font-bold gradient-text"><?php echo $id ? 'Edit Product' : 'Add New Product'; ?></h1>
        <p class="text-gray-400 mt-1">
            <?php echo $id ? 'Update product information' : 'Create a new product in your catalog'; ?>
            <span class="text-amber-400">(for <?php echo htmlspecialchars($branch_name); ?>)</span>
        </p>
    </div>

    <!-- Error Messages -->
    <?php if (!empty($errors)): ?>
        <div class="mb-6 bg-red-500/10 border border-red-500 rounded-xl p-4 ">
            <div class="flex items-start gap-3"><i class="fas fa-exclamation-triangle text-red-400 mt-1"></i><div class="flex-1"><h3 class="text-red-400 font-semibold mb-2">Please fix the following errors:</h3><ul class="list-disc list-inside text-red-400/90 text-sm space-y-1"><?php foreach ($errors as $err): ?><li><?php echo htmlspecialchars($err); ?></li><?php endforeach; ?></ul></div></div>
        </div>
    <?php endif; ?>

    <!-- Success Message -->
    <?php if ($success): ?>
        <div class="mb-6 bg-emerald-500/10 border border-emerald-500 rounded-xl p-4 ">
            <div class="flex items-center gap-3 text-emerald-400"><i class="fas fa-check-circle"></i><span><?php echo htmlspecialchars($success); ?></span></div>
            <div class="mt-3 flex gap-2"><a href="product_form.php" class="text-amber-400 hover:underline text-sm"><i class="fas fa-plus mr-1"></i>Add another product</a><span class="text-gray-500">|</span><a href="products.php?branch_id=<?php echo $branch_id; ?>" class="text-amber-400 hover:underline text-sm"><i class="fas fa-arrow-left mr-1"></i>Back to products</a></div>
        </div>
    <?php endif; ?>

    <!-- Product Form -->
    <div class="bg-slate-800/40 rounded-xl border border-slate-700 p-6 shadow-lift ">
        <form method="post" enctype="multipart/form-data" class="space-y-4">\n<input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
            <div>
                <label for="name" class="text-sm font-medium text-gray-300 mb-2 flex items-center gap-2"><i class="fas fa-cube text-amber-400 w-4"></i>Product Name <span class="text-red-400">*</span></label>
                <input type="text" id="name" name="name" required value="<?php echo htmlspecialchars($name); ?>" placeholder="Enter product name" class="form-input w-full px-4 py-3 rounded-xl bg-slate-800 border border-slate-700 text-white placeholder-slate-500 focus:ring-1 focus:ring-amber-500 focus:border-amber-500 focus:ring-2 focus:ring-amber-500/20 outline-none transition-all">
            </div>

            <div>
                <label for="description" class="text-sm font-medium text-gray-300 mb-2 flex items-center gap-2"><i class="fas fa-align-left text-amber-400 w-4"></i>Description</label>
                <textarea id="description" name="description" rows="3" placeholder="Enter product description (optional)" class="form-input w-full px-4 py-3 rounded-xl bg-slate-800 border border-slate-700 text-white placeholder-slate-500 focus:ring-1 focus:ring-amber-500 focus:border-amber-500 focus:ring-2 focus:ring-amber-500/20 outline-none transition-all"><?php echo htmlspecialchars($description); ?></textarea>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label for="sku" class="text-sm font-medium text-gray-300 mb-2 flex items-center gap-2"><i class="fas fa-tag text-amber-400 w-4"></i>SKU</label>
                    <input type="text" id="sku" name="sku" value="<?php echo htmlspecialchars($sku); ?>" placeholder="e.g., PRD-001" class="form-input w-full px-4 py-3 rounded-xl bg-slate-800 border border-slate-700 text-white placeholder-slate-500 focus:ring-1 focus:ring-amber-500 focus:border-amber-500 focus:ring-2 focus:ring-amber-500/20 outline-none transition-all">
                    <p class="text-xs text-gray-500 mt-1">Leave empty for auto-generated SKU</p>
                </div>
                <div>
                    <label for="category_id" class="text-sm font-medium text-gray-300 mb-2 flex items-center gap-2"><i class="fas fa-folder text-amber-400 w-4"></i>Category</label>
                    <select id="category_id" name="category_id" class="w-full px-4 py-3 rounded-xl bg-slate-800 border border-slate-700 text-white focus:ring-1 focus:ring-amber-500 focus:border-amber-500 focus:ring-2 focus:ring-amber-500/20 outline-none transition-all">
                        <option value="">-- Select Category --</option>
                        <?php foreach ($categories as $cat): ?><option value="<?php echo $cat['id']; ?>" <?php echo $cat['id'] == $category_id ? 'selected' : ''; ?>><?php echo htmlspecialchars($cat['name']); ?></option><?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label for="price" class="text-sm font-medium text-gray-300 mb-2 flex items-center gap-2"><i class="fas fa-dollar-sign text-amber-400 w-4"></i>Selling Price <span class="text-red-400">*</span></label>
                    <div class="relative"><span class="absolute left-4 top-3 text-gray-500">KSh</span><input type="number" id="price" name="price" required step="0.01" min="0" value="<?php echo htmlspecialchars($price); ?>" placeholder="0.00" class="form-input w-full pl-12 pr-4 py-3 rounded-xl bg-slate-800 border border-slate-700 text-white placeholder-slate-500 focus:ring-1 focus:ring-amber-500 focus:border-amber-500 focus:ring-2 focus:ring-amber-500/20 outline-none transition-all"></div>
                </div>
                <div>
                    <label for="cost_price" class="text-sm font-medium text-gray-300 mb-2 flex items-center gap-2"><i class="fas fa-coins text-amber-400 w-4"></i>Cost Price</label>
                    <div class="relative"><span class="absolute left-4 top-3 text-gray-500">KSh</span><input type="number" id="cost_price" name="cost_price" step="0.01" min="0" value="<?php echo htmlspecialchars($cost_price); ?>" placeholder="0.00" class="form-input w-full pl-12 pr-4 py-3 rounded-xl bg-slate-800 border border-slate-700 text-white placeholder-slate-500 focus:ring-1 focus:ring-amber-500 focus:border-amber-500 focus:ring-2 focus:ring-amber-500/20 outline-none transition-all"></div>
                    <p class="text-xs text-gray-500 mt-1">Your purchase cost from supplier</p>
                </div>
            </div>

            <div>
                <label class="text-sm font-medium text-gray-300 mb-2 flex items-center gap-2"><i class="fas fa-image text-amber-400 w-4"></i>Product Image</label>
                <div class="flex flex-col md:flex-row gap-4">
                    <div class="flex-1">
                        <input type="file" id="image" name="image" accept="image/jpeg,image/png,image/gif,image/webp" class="w-full px-4 py-3 rounded-xl bg-slate-800 border border-slate-700 text-gray-400 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-amber-500 file:text-black hover:file:bg-amber-600 transition-all">
                        <p class="text-xs text-gray-500 mt-2"><span class="flex items-center gap-1"><i class="fas fa-info-circle"></i>Allowed: JPG, PNG, GIF, WEBP (Max: 2MB)</span></p>
                    </div>
                    <?php
                    $imgUrl = '';
                    if ($image) {
                        $rel = ltrim($image, '/');
                        if (strpos($rel, 'public/') === 0) {
                            $rel = substr($rel, 7);
                        }
                        $full = PUBLIC_PATH . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel);
                        if (file_exists($full)) {
                            $imgUrl = base_url($rel) . '?v=' . (@filemtime($full) ?: time());
                        }
                    }
                    ?>
                    <?php if ($imgUrl): ?>
                        <div class="relative image-preview">
                            <img src="<?php echo htmlspecialchars($imgUrl); ?>" alt="Product preview" class="w-32 h-32 object-cover rounded-xl border-2 border-amber-500/30">
                            <button type="button" onclick="removeImage()" class="absolute -top-2 -right-2 p-1 bg-red-500 rounded-full text-white hover:bg-red-600 transition-colors"><i class="fas fa-times"></i></button>
                            <input type="hidden" name="remove_image" id="remove_image" value="0">
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <?php if ($id): ?>
                <div class="flex items-center gap-3 p-4 bg-slate-800/50 rounded-xl border border-slate-700">
                    <i class="fas fa-power-off text-amber-400"></i>
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input type="checkbox" name="active" value="1" class="sr-only peer" <?php echo $active ? 'checked' : ''; ?>>
                        <div class="w-11 h-6 bg-gray-600 rounded-full peer peer-checked:bg-emerald-500 peer-focus:ring-2 peer-focus:ring-amber-500/20 transition-colors"></div>
                        <div class="absolute left-1 top-1 w-4 h-4 bg-white rounded-full transition-transform peer-checked:translate-x-5"></div>
                    </label>
                    <span class="text-sm text-gray-300">Product Active</span>
                    <span class="text-xs text-gray-500 ml-auto">Inactive products won't appear in POS</span>
                </div>
            <?php endif; ?>

            <div class="flex flex-col sm:flex-row gap-3 pt-4 border-t border-slate-700">
                <button type="submit" class="flex-1 px-6 py-3 bg-amber-500 text-black rounded-xl font-semibold hover:bg-amber-600 transition-all transform hover:scale-[1.02] focus:outline-none focus:ring-2 focus:ring-amber-500 focus:ring-offset-2 focus:ring-offset-primary flex items-center justify-center gap-2">
                    <i class="fas fa-<?php echo $id ? 'pen' : 'plus'; ?>"></i><?php echo $id ? 'Update Product' : 'Create Product'; ?>
                </button>
                <a href="products.php?branch_id=<?php echo $branch_id; ?>" class="flex-1 px-6 py-3 bg-slate-800 border border-slate-700 text-white rounded-xl font-semibold hover:border-amber-500 hover:text-amber-400 transition-all flex items-center justify-center gap-2">
                    <i class="fas fa-times"></i>Cancel
                </a>
            </div>
        </form>
    </div>

    <?php if (!$id): ?>
        <div class="mt-6 bg-amber-500/5 rounded-xl border border-amber-500/20 p-4 text-sm text-gray-400">
            <div class="flex items-start gap-3"><i class="fas fa-info-circle text-amber-400 mt-1"></i><div><p class="text-amber-400 font-semibold mb-1">About New Products</p><ul class="space-y-1 list-disc list-inside"><li>New products are created as <span class="text-emerald-400">Active</span> by default</li><li>Inventory will be initialized with <span class="text-red-400">0 stock</span> for <strong>ALL branches</strong></li><li>You can add stock in the Inventory section</li></ul></div></div>
        </div>
    <?php endif; ?>

    <?php if (!empty($branches) && (is_super_admin() || $user_role === 'Admin')): ?>
        <div class="mt-4 bg-slate-800/40 rounded-xl border border-slate-700 p-4 text-sm">
            <div class="flex items-start gap-3"><i class="fas fa-store text-amber-400"></i><div><p class="text-gray-300 font-semibold mb-1">Branch Information</p><p class="text-gray-400">This product will be available in all branches:</p><div class="flex flex-wrap gap-2 mt-2"><?php foreach ($branches as $branch): ?><span class="px-2 py-1 bg-primary-dark rounded-lg text-xs text-gray-300"><?php echo htmlspecialchars($branch['name']); ?></span><?php endforeach; ?></div></div></div>
        </div>
    <?php endif; ?>
</div>

<script>
    function removeImage() {
        document.getElementById('remove_image').value = '1';
        const imagePreview = document.querySelector('.image-preview');
        if (imagePreview) { imagePreview.style.opacity = '0.5'; imagePreview.style.pointerEvents = 'none'; }
    }

    document.getElementById('image')?.addEventListener('change', function (e) {
        const file = e.target.files[0];
        if (file && file.size > 2 * 1024 * 1024) { alert('Image size must be less than 2MB'); this.value = ''; }
    });

    document.getElementById('name')?.addEventListener('blur', function () {
        const skuField = document.getElementById('sku');
        if (!skuField.value && this.value) {
            const suggestion = this.value.toUpperCase().replace(/[^A-Z0-9]/g, '').substring(0, 8);
            if (suggestion) skuField.value = suggestion + '-' + Math.floor(Math.random() * 1000);
        }
    });

    document.addEventListener('keydown', function (e) {
        if ((e.ctrlKey || e.metaKey) && e.key === 's') { e.preventDefault(); document.querySelector('form').submit(); }
        if (e.key === 'Escape') { e.preventDefault(); window.location.href = 'products.php?branch_id=<?php echo $branch_id; ?>'; }
    });
</script>

<?php
$page_content = ob_get_clean();
require_once __DIR__ . '/../../layouts/app_close.php';
require_once __DIR__ . '/../../layouts/app.php';
?>
