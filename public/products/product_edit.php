<?php
/**
 * Product Inline Edit Page
 * Dedicated edit page extracted from product_view.php
 */

require_once __DIR__ . '/../../src/paths.php';
safe_require('auth.php', 'src', true);
safe_require('db.php', 'src', true);
safe_require('functions.php', 'src', true);

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

$branch_id = get_current_branch_id();
$branch_name = get_current_branch_name() ?: 'Default Branch';

$product_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if (!$product_id) {
    header('Location: products.php');
    exit;
}

// Load product
$product = null;
$categories = [];
$brands = [];
$errors = [];
$success = '';

try {
    $stmt = $pdo->prepare("
        SELECT p.*, c.name AS category_name, b.name AS brand_name
        FROM products p
        LEFT JOIN categories c ON p.category_id = c.id
        LEFT JOIN brands b ON p.brand_id = b.id
        WHERE p.id = :id AND p.tenant_id = :tenant_id AND p.deleted_at IS NULL
    ");
    $stmt->execute([':id' => $product_id, ':tenant_id' => $tenant_id]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$product) {
        header('Location: products.php');
        exit;
    }

    // Load categories
    $catStmt = $pdo->prepare("
        SELECT id, name FROM categories
        WHERE tenant_id = :tenant_id AND (status = 'active' OR status IS NULL)
        ORDER BY name
    ");
    $catStmt->execute([':tenant_id' => $tenant_id]);
    $categories = $catStmt->fetchAll(PDO::FETCH_ASSOC);

    // Load brands
    $brandStmt = $pdo->prepare("
        SELECT id, name FROM brands
        WHERE tenant_id = :tenant_id AND active = 1
        ORDER BY name
    ");
    $brandStmt->execute([':tenant_id' => $tenant_id]);
    $brands = $brandStmt->fetchAll(PDO::FETCH_ASSOC);

    // Load current branch inventory
    $inventory = null;
    try {
        $invStmt = $pdo->prepare("
            SELECT stock, reorder_level, minimum_stock, maximum_stock
            FROM inventory
            WHERE product_id = :pid AND tenant_id = :tenant_id AND branch_id = :branch_id
            LIMIT 1
        ");
        $invStmt->execute([':pid' => $product_id, ':tenant_id' => $tenant_id, ':branch_id' => $branch_id]);
        $inventory = $invStmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) { /* silent */ }

    // Load product attributes
    $productAttributes = [];
    try {
        $attrStmt = $pdo->prepare("
            SELECT id, attribute_name, attribute_value, visible, used_for_variations
            FROM product_attributes
            WHERE product_id = :pid AND tenant_id = :tenant_id
            ORDER BY position, id
        ");
        $attrStmt->execute([':pid' => $product_id, ':tenant_id' => $tenant_id]);
        $productAttributes = $attrStmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) { /* silent */ }
} catch (PDOException $e) {
    error_log('Product edit load error: ' . $e->getMessage());
    $errors[] = 'Database error: ' . $e->getMessage();
}

// Handle POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $product) {
    $data = [
        'name'        => trim($_POST['name'] ?? ''),
        'sku'         => trim($_POST['sku'] ?? ''),
        'barcode'     => trim($_POST['barcode'] ?? ''),
        'price'       => floatval($_POST['price'] ?? 0),
        'cost_price'  => floatval($_POST['cost_price'] ?? 0),
        'category_id' => !empty($_POST['category_id']) ? (int) $_POST['category_id'] : null,
        'brand_id'    => !empty($_POST['brand_id']) ? (int) $_POST['brand_id'] : null,
        'description' => trim($_POST['description'] ?? ''),
        'active'      => isset($_POST['active']) ? 1 : 0,
        'unit'        => trim($_POST['unit'] ?? 'pcs'),
        'tax_rate'    => floatval($_POST['tax_rate'] ?? 0),
        'reorder_level' => intval($_POST['reorder_level'] ?? 5),
    ];

    // Validate
    if (empty($data['name'])) {
        $errors[] = 'Product name is required.';
    }
    if ($data['price'] < 0) {
        $errors[] = 'Price cannot be negative.';
    }
    if ($data['cost_price'] < 0) {
        $errors[] = 'Cost price cannot be negative.';
    }

    // Check SKU uniqueness (exclude current product)
    if (empty($errors) && !empty($data['sku'])) {
        try {
            $check = $pdo->prepare("
                SELECT id FROM products
                WHERE sku = :sku AND tenant_id = :tenant_id AND id != :id AND deleted_at IS NULL
                LIMIT 1
            ");
            $check->execute([':sku' => $data['sku'], ':tenant_id' => $tenant_id, ':id' => $product_id]);
            if ($check->fetch()) {
                $errors[] = 'SKU is already used by another product.';
            }
        } catch (PDOException $e) {
            error_log('SKU check error: ' . $e->getMessage());
        }
    }

    // Handle image upload
    $uploadedImagePath = null;
    $removeImage = isset($_POST['remove_image']) && $_POST['remove_image'] === '1';

    if (isset($_FILES['image']) && $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
        $uploadErrors = [
            UPLOAD_ERR_INI_SIZE => 'File exceeds upload_max_filesize',
            UPLOAD_ERR_FORM_SIZE => 'File exceeds MAX_FILE_SIZE',
            UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
            UPLOAD_ERR_NO_FILE => 'No file was uploaded',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
            UPLOAD_ERR_EXTENSION => 'Upload stopped by extension',
        ];
        $errCode = $_FILES['image']['error'];
        $errMsg = $uploadErrors[$errCode] ?? "Unknown upload error ({$errCode})";
        $errors[] = "Upload error: {$errMsg}";
    }

    if ($removeImage && !empty($product['image'])) {
        // Delete existing image file
        $oldPath = ltrim($product['image'], '/');
        if (strpos($oldPath, 'public/') === 0) {
            $oldPath = substr($oldPath, 7); // strip 'public/'
        }
        $fullOld = PUBLIC_PATH . '/' . $oldPath;
        $fullOld = str_replace('/', DIRECTORY_SEPARATOR, $fullOld);
        if (file_exists($fullOld)) {
            @unlink($fullOld);
        }
        $data['image'] = null;
    }

    // Preserve existing image if no action taken
    if (empty($_FILES['image']['tmp_name']) && !$removeImage) {
        $data['image'] = $product['image'] ?? null;
    }

    if (!empty($_FILES['image']['tmp_name']) && empty($errors) && !$removeImage) {
        $allowed = ['image/jpeg','image/png','image/webp','image/gif'];
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $_FILES['image']['tmp_name']);
        finfo_close($finfo);
        if (!in_array($mime, $allowed)) {
            $errors[] = 'Invalid image format. Use JPG, PNG, WebP, or GIF.';
        } elseif ($_FILES['image']['size'] > 2 * 1024 * 1024) {
            $errors[] = 'Image too large. Max 2MB.';
        } else {
            $ext = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
            $filename = 'prod_' . time() . '_' . uniqid() . '.' . $ext;
            $uploadDir = PUBLIC_PATH . '/uploads/product_images';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
            $dest = $uploadDir . '/' . $filename;
            if (move_uploaded_file($_FILES['image']['tmp_name'], $dest)) {
                // Delete old image if present
                if (!empty($product['image'])) {
                    $oldPath = ltrim($product['image'], '/');
                    if (strpos($oldPath, 'public/') === 0) {
                        $oldPath = substr($oldPath, 7);
                    }
                    $fullOld = PUBLIC_PATH . '/' . $oldPath;
                    $fullOld = str_replace('/', DIRECTORY_SEPARATOR, $fullOld);
                    if (file_exists($fullOld)) {
                        @unlink($fullOld);
                    }
                }
                $uploadedImagePath = 'uploads/product_images/' . $filename;
                $data['image'] = $uploadedImagePath;
            } else {
                $errors[] = 'Image upload failed.';
            }
        }
    }

    // Update
    if (empty($errors)) {
        try {
            $sql = "UPDATE products SET
                        `name`        = :name,
                        `sku`         = :sku,
                        `barcode`     = :barcode,
                        `price`       = :price,
                        `cost_price`  = :cost_price,
                        `category_id` = :category_id,
                        `brand_id`    = :brand_id,
                        `description` = :description,
                        `active`      = :active,
                        `unit`        = :unit,
                        `tax_rate`    = :tax_rate,
                        `reorder_level` = :reorder_level,
                        `image`       = :image,
                        `updated_at`  = NOW()
                    WHERE id = :id AND tenant_id = :tenant_id AND deleted_at IS NULL";
            $up = $pdo->prepare($sql);
            $up->execute([
                ':name'         => $data['name'],
                ':sku'          => $data['sku'],
                ':barcode'      => $data['barcode'],
                ':price'        => $data['price'],
                ':cost_price'   => $data['cost_price'],
                ':category_id'  => $data['category_id'],
                ':brand_id'     => $data['brand_id'],
                ':description'  => $data['description'],
                ':active'       => $data['active'],
                ':unit'         => $data['unit'],
                ':tax_rate'     => $data['tax_rate'],
                ':reorder_level'=> $data['reorder_level'],
                ':image'        => $data['image'] ?? null,
                ':id'           => $product_id,
                ':tenant_id'    => $tenant_id,
            ]);

            // Update inventory for current branch
            $newStock = isset($_POST['stock']) ? (int) $_POST['stock'] : null;
            if ($newStock !== null) {
                $invData = [
                    ':pid' => $product_id,
                    ':tenant_id' => $tenant_id,
                    ':branch_id' => $branch_id,
                    ':stock' => max(0, $newStock),
                    ':reorder_level' => max(0, (int) ($_POST['inv_reorder_level'] ?? 5)),
                    ':min_stock' => max(0, (int) ($_POST['minimum_stock'] ?? 0)),
                    ':max_stock' => max(0, (int) ($_POST['maximum_stock'] ?? 0)),
                ];
                $invCheck = $pdo->prepare("
                    SELECT 1 FROM inventory
                    WHERE product_id = :pid AND tenant_id = :tenant_id AND branch_id = :branch_id
                    LIMIT 1
                ");
                $invCheck->execute([':pid' => $product_id, ':tenant_id' => $tenant_id, ':branch_id' => $branch_id]);
                if ($invCheck->fetch()) {
                    $invUp = $pdo->prepare("
                        UPDATE inventory
                        SET stock = :stock, reorder_level = :reorder_level,
                            minimum_stock = :min_stock, maximum_stock = :max_stock,
                            updated_at = NOW()
                        WHERE product_id = :pid AND tenant_id = :tenant_id AND branch_id = :branch_id
                    ");
                    $invUp->execute($invData);
                } else {
                    $invIns = $pdo->prepare("
                        INSERT INTO inventory
                        (product_id, tenant_id, branch_id, stock, reorder_level, minimum_stock, maximum_stock, created_at, updated_at)
                        VALUES (:pid, :tenant_id, :branch_id, :stock, :reorder_level, :min_stock, :max_stock, NOW(), NOW())
                    ");
                    $invIns->execute($invData);
                }
            }

            // Update attributes
            $attrNames = (array) ($_POST['attr_names'] ?? []);
            $attrValues = (array) ($_POST['attr_values'] ?? []);
            $attrVisible = (array) ($_POST['attr_visible'] ?? []);
            $attrVariations = (array) ($_POST['attr_variations'] ?? []);

            // Delete existing attributes
            $delAttr = $pdo->prepare("
                DELETE FROM product_attributes
                WHERE product_id = :pid AND tenant_id = :tenant_id
            ");
            $delAttr->execute([':pid' => $product_id, ':tenant_id' => $tenant_id]);

            // Insert new attributes
            $insAttr = $pdo->prepare("
                INSERT INTO product_attributes
                (tenant_id, product_id, attribute_name, attribute_value, visible, position, used_for_variations)
                VALUES (:tenant_id, :pid, :name, :value, :visible, :position, :variations)
            ");
            foreach ($attrNames as $i => $name) {
                $name = trim($name);
                $value = trim($attrValues[$i] ?? '');
                if ($name === '') continue;
                $insAttr->execute([
                    ':tenant_id' => $tenant_id,
                    ':pid' => $product_id,
                    ':name' => $name,
                    ':value' => $value,
                    ':visible' => isset($attrVisible[$i]) ? 1 : 0,
                    ':position' => (int) $i,
                    ':variations' => isset($attrVariations[$i]) ? 1 : 0,
                ]);
            }

            // Reload product
            $stmt->execute([':id' => $product_id, ':tenant_id' => $tenant_id]);
            $product = $stmt->fetch(PDO::FETCH_ASSOC);

            // Reload inventory
            $invStmt->execute([':pid' => $product_id, ':tenant_id' => $tenant_id, ':branch_id' => $branch_id]);
            $inventory = $invStmt->fetch(PDO::FETCH_ASSOC);

            // Reload attributes
            $attrStmt->execute([':pid' => $product_id, ':tenant_id' => $tenant_id]);
            $productAttributes = $attrStmt->fetchAll(PDO::FETCH_ASSOC);

            $success = 'Product updated successfully.';
        } catch (PDOException $e) {
            error_log('Product update error: ' . $e->getMessage());
            $errors[] = 'Update failed: ' . $e->getMessage();
        }
    }
}

$page_title = $product ? 'Edit: ' . htmlspecialchars($product['name']) : 'Edit Product';
$inp = 'w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-1 focus:ring-amber-500 transition-colors';
$lbl = 'block text-xs font-medium text-slate-400 uppercase tracking-wide mb-1';
ob_start();
?>

<!-- Page header -->
<div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-5">
    <div>
        <a href="product_view.php?id=<?php echo $product_id; ?>"
           class="inline-flex items-center gap-1.5 text-sm text-slate-500 hover:text-amber-400 transition-colors mb-2">
            <i class="fas fa-arrow-left text-xs"></i> Back to View
        </a>
        <h1 class="text-lg font-bold text-white flex items-center gap-2">
            <i class="fas fa-pen text-amber-400"></i>
            <?php echo $product ? 'Edit: ' . htmlspecialchars($product['name']) : 'Edit Product'; ?>
        </h1>
        <p class="text-sm text-slate-500 mt-0.5">
            <span class="text-amber-400"><?php echo htmlspecialchars($branch_name); ?></span>
        </p>
    </div>
    <div class="shrink-0">
        <a href="products.php"
           class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-slate-700 border border-slate-600 text-slate-300 text-sm font-medium hover:bg-slate-600 transition-colors">
            <i class="fas fa-list text-xs"></i> All Products
        </a>
    </div>
</div>

<?php if (!empty($errors)): ?>
<div class="mb-4 flex items-start gap-2 px-3 py-3 rounded-lg bg-red-500/10 border border-red-500/30 text-red-400 text-sm">
    <i class="fas fa-exclamation-triangle mt-0.5 shrink-0"></i>
    <div><?php foreach ($errors as $err): ?><p><?php echo htmlspecialchars($err); ?></p><?php endforeach; ?></div>
</div>
<?php endif; ?>

<?php if ($success): ?>
<div class="mb-4 flex items-center gap-2 px-3 py-2 rounded-lg bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 text-sm" id="successBanner">
    <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?>
</div>
<?php endif; ?>

<?php if ($product): ?>
<form method="POST" action="" enctype="multipart/form-data" class="space-y-4">
    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? ''); ?>">

    <!-- Basic Information -->
    <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl overflow-hidden">
        <div class="flex items-center gap-2 px-4 py-3 border-b border-slate-700/60 bg-slate-800/60">
            <i class="fas fa-box text-amber-400 text-xs"></i>
            <span class="text-sm font-semibold text-white">Basic Information</span>
        </div>
        <div class="p-4 grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div class="sm:col-span-2">
                <label class="<?php echo $lbl; ?>" for="prod_name">Product Name <span class="text-red-400">*</span></label>
                <input type="text" id="prod_name" name="name" autocomplete="off" required
                       class="<?php echo $inp; ?>"
                       value="<?php echo htmlspecialchars($product['name'] ?? ''); ?>">
            </div>
            <div>
                <label class="<?php echo $lbl; ?>" for="prod_sku">SKU</label>
                <input type="text" id="prod_sku" name="sku" autocomplete="off"
                       class="<?php echo $inp; ?> font-mono"
                       value="<?php echo htmlspecialchars($product['sku'] ?? ''); ?>">
            </div>
            <div>
                <label class="<?php echo $lbl; ?>" for="prod_barcode">Barcode</label>
                <input type="text" id="prod_barcode" name="barcode" autocomplete="off"
                       class="<?php echo $inp; ?> font-mono"
                       value="<?php echo htmlspecialchars($product['barcode'] ?? ''); ?>">
            </div>
            <div>
                <label class="<?php echo $lbl; ?>" for="prod_category">Category</label>
                <select id="prod_category" name="category_id" class="<?php echo $inp; ?>">
                    <option value="">— None —</option>
                    <?php foreach ($categories as $cat): ?>
                    <option value="<?php echo (int)$cat['id']; ?>" <?php echo ($product['category_id'] ?? null) == $cat['id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($cat['name']); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="<?php echo $lbl; ?>" for="prod_brand">Brand</label>
                <select id="prod_brand" name="brand_id" class="<?php echo $inp; ?>">
                    <option value="">— None —</option>
                    <?php foreach ($brands as $b): ?>
                    <option value="<?php echo (int)$b['id']; ?>" <?php echo ($product['brand_id'] ?? null) == $b['id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($b['name']); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="<?php echo $lbl; ?>" for="prod_unit">Unit</label>
                <input type="text" id="prod_unit" name="unit" autocomplete="off"
                       class="<?php echo $inp; ?>"
                       value="<?php echo htmlspecialchars($product['unit'] ?? 'pcs'); ?>">
            </div>
            <div class="flex items-center gap-3 pt-5">
                <label class="inline-flex items-center gap-2 cursor-pointer select-none">
                    <input type="checkbox" id="prod_active" name="active" value="1"
                           class="w-4 h-4 rounded accent-amber-500 cursor-pointer"
                           <?php echo ($product['active'] ?? 0) ? 'checked' : ''; ?>>
                    <span class="text-sm text-slate-300">Active</span>
                </label>
            </div>
        </div>
    </div>

    <!-- Pricing -->
    <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl overflow-hidden">
        <div class="flex items-center gap-2 px-4 py-3 border-b border-slate-700/60 bg-slate-800/60">
            <i class="fas fa-tag text-amber-400 text-xs"></i>
            <span class="text-sm font-semibold text-white">Pricing</span>
        </div>
        <div class="p-4 grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div>
                <label class="<?php echo $lbl; ?>" for="prod_price">Selling Price <span class="text-red-400">*</span></label>
                <input type="number" id="prod_price" name="price" step="0.01" min="0" required
                       class="<?php echo $inp; ?>"
                       value="<?php echo number_format((float)($product['price'] ?? 0), 2, '.', ''); ?>">
            </div>
            <div>
                <label class="<?php echo $lbl; ?>" for="prod_cost">Cost Price</label>
                <input type="number" id="prod_cost" name="cost_price" step="0.01" min="0"
                       class="<?php echo $inp; ?>"
                       value="<?php echo number_format((float)($product['cost_price'] ?? 0), 2, '.', ''); ?>">
            </div>
            <div>
                <label class="<?php echo $lbl; ?>" for="prod_tax">Tax Rate (%)</label>
                <input type="number" id="prod_tax" name="tax_rate" step="0.01" min="0"
                       class="<?php echo $inp; ?>"
                       value="<?php echo number_format((float)($product['tax_rate'] ?? 0), 2, '.', ''); ?>">
            </div>
        </div>
    </div>

    <!-- Branch Stock -->
    <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl overflow-hidden">
        <div class="flex items-center gap-2 px-4 py-3 border-b border-slate-700/60 bg-slate-800/60">
            <i class="fas fa-warehouse text-amber-400 text-xs"></i>
            <span class="text-sm font-semibold text-white">Stock</span>
            <span class="ml-auto text-xs text-slate-500"><?php echo htmlspecialchars($branch_name); ?></span>
        </div>
        <div class="p-4 grid grid-cols-2 sm:grid-cols-4 gap-4">
            <div>
                <label class="<?php echo $lbl; ?>" for="prod_stock">Current Stock</label>
                <input type="number" id="prod_stock" name="stock" min="0"
                       class="<?php echo $inp; ?>"
                       value="<?php echo (int)($inventory['stock'] ?? 0); ?>">
            </div>
            <div>
                <label class="<?php echo $lbl; ?>" for="prod_reorder">Reorder Level</label>
                <input type="number" id="prod_reorder" name="reorder_level" min="0"
                       class="<?php echo $inp; ?>"
                       value="<?php echo (int)($product['reorder_level'] ?? 5); ?>">
            </div>
            <div>
                <label class="<?php echo $lbl; ?>" for="prod_inv_reorder">Inv. Reorder</label>
                <input type="number" id="prod_inv_reorder" name="inv_reorder_level" min="0"
                       class="<?php echo $inp; ?>"
                       value="<?php echo (int)($inventory['reorder_level'] ?? 5); ?>">
            </div>
            <div>
                <label class="<?php echo $lbl; ?>" for="prod_min_stock">Min Stock</label>
                <input type="number" id="prod_min_stock" name="minimum_stock" min="0"
                       class="<?php echo $inp; ?>"
                       value="<?php echo (int)($inventory['minimum_stock'] ?? 0); ?>">
            </div>
        </div>
    </div>

    <!-- Product Image -->
    <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl overflow-hidden">
        <div class="flex items-center gap-2 px-4 py-3 border-b border-slate-700/60 bg-slate-800/60">
            <i class="fas fa-image text-amber-400 text-xs"></i>
            <span class="text-sm font-semibold text-white">Product Image</span>
        </div>
        <div class="p-4">
            <?php
            $imgUrl = '';
            if (!empty($product['image'])) {
                $imgPath = ltrim($product['image'], '/');
                if (strpos($imgPath, 'public/') === 0) $imgPath = substr($imgPath, 7);
                $fullPath = str_replace('/', DIRECTORY_SEPARATOR, PUBLIC_PATH . '/' . $imgPath);
                if (file_exists($fullPath)) {
                    $imgUrl = base_url($imgPath) . '?v=' . (@filemtime($fullPath) ?: time());
                }
            }
            ?>
            <div id="imageUploadArea"
                 class="border-2 border-dashed border-slate-700 rounded-xl p-6 text-center cursor-pointer hover:border-slate-600 transition-colors bg-slate-900/40"
                 onclick="document.getElementById('imageInput').click()">
                <?php if ($imgUrl): ?>
                <img src="<?php echo htmlspecialchars($imgUrl); ?>" alt="Product"
                     id="imagePreview"
                     class="w-40 h-40 object-cover rounded-xl border border-slate-700/60 mx-auto mb-3">
                <p class="text-xs text-slate-500 mb-2">Click to change image</p>
                <button type="button"
                        class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg bg-red-500/10 border border-red-500/30 text-red-400 text-xs font-medium hover:bg-red-500/20 transition-colors"
                        onclick="event.stopPropagation(); removeImage()">
                    <i class="fas fa-trash text-xs"></i> Remove
                </button>
                <?php else: ?>
                <i class="fas fa-cloud-upload-alt text-3xl text-slate-600 mb-3 block"></i>
                <p class="text-sm text-slate-400">Click to upload image</p>
                <p class="text-xs text-slate-600 mt-1">JPG, PNG, GIF, WEBP · max 2 MB</p>
                <?php endif; ?>
            </div>
            <input type="file" id="imageInput" name="image" accept="image/*" class="hidden" onchange="previewImage(this)">
            <input type="hidden" name="remove_image" id="remove_image" value="0">
        </div>
    </div>

    <!-- Attributes -->
    <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl overflow-hidden">
        <div class="flex items-center gap-2 px-4 py-3 border-b border-slate-700/60 bg-slate-800/60">
            <i class="fas fa-sliders-h text-amber-400 text-xs"></i>
            <span class="text-sm font-semibold text-white">Attributes</span>
        </div>
        <div class="p-4">
            <div id="attributes-container" class="space-y-2">
                <?php foreach ($productAttributes as $i => $attr): ?>
                <div class="attr-row grid grid-cols-1 sm:grid-cols-2 gap-2 p-3 bg-slate-900/50 rounded-lg border border-slate-700/40">
                    <div>
                        <label class="<?php echo $lbl; ?>">Name</label>
                        <input type="text" name="attr_names[]" class="<?php echo $inp; ?>"
                               value="<?php echo htmlspecialchars($attr['attribute_name']); ?>" placeholder="e.g. Color">
                    </div>
                    <div>
                        <label class="<?php echo $lbl; ?>">Value(s)</label>
                        <input type="text" name="attr_values[]" class="<?php echo $inp; ?>"
                               value="<?php echo htmlspecialchars($attr['attribute_value']); ?>" placeholder="e.g. Red, Blue">
                    </div>
                    <div class="sm:col-span-2 flex flex-wrap items-center gap-4 pt-1">
                        <label class="inline-flex items-center gap-2 cursor-pointer text-sm text-slate-300">
                            <input type="checkbox" name="attr_visible[<?php echo $i; ?>]" value="1"
                                   class="w-3.5 h-3.5 rounded accent-amber-500"
                                   <?php echo $attr['visible'] ? 'checked' : ''; ?>>
                            Visible
                        </label>
                        <label class="inline-flex items-center gap-2 cursor-pointer text-sm text-slate-300">
                            <input type="checkbox" name="attr_variations[<?php echo $i; ?>]" value="1"
                                   class="w-3.5 h-3.5 rounded accent-amber-500"
                                   <?php echo $attr['used_for_variations'] ? 'checked' : ''; ?>>
                            Used for Variations
                        </label>
                        <button type="button" onclick="this.closest('.attr-row').remove()"
                                class="ml-auto inline-flex items-center gap-1 px-2.5 py-1 rounded-lg bg-red-500/10 border border-red-500/30 text-red-400 text-xs hover:bg-red-500/20 transition-colors">
                            <i class="fas fa-trash text-xs"></i> Remove
                        </button>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <button type="button" onclick="addAttributeRow()"
                    class="mt-3 inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-slate-700 border border-slate-600 text-amber-400 text-sm font-medium hover:bg-slate-600 transition-colors">
                <i class="fas fa-plus text-xs"></i> Add Attribute
            </button>
        </div>
    </div>

    <!-- Description -->
    <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl overflow-hidden">
        <div class="flex items-center gap-2 px-4 py-3 border-b border-slate-700/60 bg-slate-800/60">
            <i class="fas fa-align-left text-amber-400 text-xs"></i>
            <span class="text-sm font-semibold text-white">Description</span>
        </div>
        <div class="p-4">
            <textarea id="prod_desc" name="description" rows="4"
                      class="<?php echo $inp; ?> resize-y"
                      placeholder="Optional product description…"><?php echo htmlspecialchars($product['description'] ?? ''); ?></textarea>
        </div>
    </div>

    <!-- Form actions -->
    <div class="flex items-center gap-3 pb-2">
        <button type="submit"
                class="inline-flex items-center gap-1.5 px-4 py-2 rounded-lg bg-amber-500/10 border border-amber-500/30 text-amber-400 text-sm font-medium hover:bg-amber-500/20 transition-colors">
            <i class="fas fa-save text-xs"></i> Save Changes
        </button>
        <a href="product_view.php?id=<?php echo $product_id; ?>"
           class="inline-flex items-center gap-1.5 px-4 py-2 rounded-lg bg-slate-700 border border-slate-600 text-slate-300 text-sm font-medium hover:bg-slate-600 transition-colors">
            Cancel
        </a>
    </div>
</form>
<?php endif; ?>

<script>
    const INP_CLS = '<?php echo addslashes($inp); ?>';
    const LBL_CLS = '<?php echo addslashes($lbl); ?>';

    function previewImage(input) {
        if (input.files && input.files[0]) {
            const reader = new FileReader();
            reader.onload = function(e) {
                const area = document.getElementById('imageUploadArea');
                area.innerHTML = `
                    <img src="${e.target.result}" alt="Preview"
                         class="w-40 h-40 object-cover rounded-xl border border-slate-700/60 mx-auto mb-3">
                    <p class="text-xs text-slate-500 mb-2">Click to change image</p>
                    <button type="button"
                            class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg bg-red-500/10 border border-red-500/30 text-red-400 text-xs font-medium hover:bg-red-500/20 transition-colors"
                            onclick="event.stopPropagation(); removeImage()">
                        <i class="fas fa-trash text-xs"></i> Remove
                    </button>
                `;
                document.getElementById('remove_image').value = '0';
            };
            reader.readAsDataURL(input.files[0]);
        }
    }

    function removeImage() {
        document.getElementById('remove_image').value = '1';
        const area = document.getElementById('imageUploadArea');
        area.innerHTML = `
            <i class="fas fa-cloud-upload-alt text-3xl text-slate-600 mb-3 block"></i>
            <p class="text-sm text-slate-400">Click to upload image</p>
            <p class="text-xs text-slate-600 mt-1">JPG, PNG, GIF, WEBP · max 2 MB</p>
        `;
    }

    let attrIdx = <?php echo count($productAttributes); ?>;
    function addAttributeRow() {
        const container = document.getElementById('attributes-container');
        const div = document.createElement('div');
        div.className = 'attr-row grid grid-cols-1 sm:grid-cols-2 gap-2 p-3 bg-slate-900/50 rounded-lg border border-slate-700/40';
        div.innerHTML = `
            <div>
                <label class="${LBL_CLS}">Name</label>
                <input type="text" name="attr_names[]" class="${INP_CLS}" placeholder="e.g. Color">
            </div>
            <div>
                <label class="${LBL_CLS}">Value(s)</label>
                <input type="text" name="attr_values[]" class="${INP_CLS}" placeholder="e.g. Red, Blue">
            </div>
            <div class="sm:col-span-2 flex flex-wrap items-center gap-4 pt-1">
                <label class="inline-flex items-center gap-2 cursor-pointer text-sm text-slate-300">
                    <input type="checkbox" name="attr_visible[${attrIdx}]" value="1" class="w-3.5 h-3.5 rounded accent-amber-500" checked> Visible
                </label>
                <label class="inline-flex items-center gap-2 cursor-pointer text-sm text-slate-300">
                    <input type="checkbox" name="attr_variations[${attrIdx}]" value="1" class="w-3.5 h-3.5 rounded accent-amber-500"> Used for Variations
                </label>
                <button type="button" onclick="this.closest('.attr-row').remove()"
                        class="ml-auto inline-flex items-center gap-1 px-2.5 py-1 rounded-lg bg-red-500/10 border border-red-500/30 text-red-400 text-xs hover:bg-red-500/20 transition-colors">
                    <i class="fas fa-trash text-xs"></i> Remove
                </button>
            </div>
        `;
        container.appendChild(div);
        attrIdx++;
    }

    document.addEventListener('DOMContentLoaded', function() {
        const banner = document.getElementById('successBanner');
        if (banner) setTimeout(() => { banner.style.transition = 'opacity 0.5s'; banner.style.opacity = '0'; setTimeout(() => banner.remove(), 500); }, 4000);
    });

    document.addEventListener('keydown', function(e) {
        if (e.target.matches('input, textarea, select')) return;
        if (e.key === 'Escape') window.location.href = 'product_view.php?id=<?php echo $product_id; ?>';
        if (e.ctrlKey && e.key === 's') { e.preventDefault(); document.querySelector('form')?.submit(); }
    });
</script>

<?php
$page_content = ob_get_clean();
require_once __DIR__ . '/../layouts/app.php';
