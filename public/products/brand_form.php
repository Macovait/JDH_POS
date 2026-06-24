<?php
/**
 * Brand Form page for JDH POS
 */

require_once __DIR__ . '/../../src/paths.php';
safe_require('auth.php', 'src', true);
safe_require('db.php', 'src', true);
safe_require('functions.php', 'src', true);
require_once __DIR__ . '/../../src/Security/SecurityBootstrap.php';

// Initialize comprehensive security system
SecurityBootstrap::initialize();

function brand_form_table_has_col(PDO $pdo, string $table, string $column): bool {
    try {
        $stmt = $pdo->prepare("SHOW COLUMNS FROM `$table` LIKE ?");
        $stmt->execute([$column]);
        return $stmt->rowCount() > 0;
    } catch (Exception $e) {
        return false;
    }
}

require_login();

// Enhanced permission check with audit logging
if (!has_permission('products.manage')) {
    enforce_permission('products.manage');
}

// Apply tenant isolation validation
validate_tenant_isolation();

$pdo = get_db_connection();
$tenant_id = get_current_tenant_id();
if (!$tenant_id) {
    http_response_code(403);
    exit('Company context missing. Please log in again.');
}

$user_id = get_current_user_id();
$user_name = htmlspecialchars(get_current_user_name());
$branch_id = get_current_branch_id();
$branch_name = get_current_branch_name();

$id = intval($_GET['id'] ?? 0);
$name = ''; $description = ''; $image = null; $active = 1; $error = ''; $success = '';

if ($id) {
    try {
        // Use secure query builder with automatic tenant scoping
        $brand = secure_db_find('brands', $id);
        if ($brand) {
            $name = $brand['name'];
            $description = $brand['description'] ?? '';
            $image = $brand['image'];
            $active = $brand['active'];
            
            // Log data access
            log_security_event('brand_view', [
                'brand_id' => $id,
                'brand_name' => $name
            ]);
        } else {
            $error = 'Brand not found';
        }
    } catch (Exception $e) { 
        error_log("Error loading brand: " . $e->getMessage()); 
        $error = 'Failed to load brand'; 
        
        // Log security event for failed access
        log_security_event('brand_access_failed', [
            'brand_id' => $id,
            'error' => $e->getMessage()
        ]);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $active = isset($_POST['active']) ? 1 : 0;

    $errors = [];

    if (empty($name)) $errors[] = 'Brand name is required';

    if (!empty($name)) {
        // Use secure database functions with automatic tenant scoping
        $existing_brand = secure_db_fetch_all('brands', ['name' => $name, 'deleted_at' => null]);
        
        // Filter out current brand if editing
        if ($id) {
            $existing_brand = array_filter($existing_brand, fn($b) => $b['id'] != $id);
        }
        
        if (!empty($existing_brand)) {
            $errors[] = 'Brand name already exists. Please use a unique name.';
        }
    }

    $imagePath = $image;
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $max_size = 2 * 1024 * 1024;
        $file_info = finfo_open(FILEINFO_MIME_TYPE);
        $mime_type = finfo_file($file_info, $_FILES['image']['tmp_name']);
        finfo_close($file_info);
        if (!in_array($mime_type, $allowed_types)) $errors[] = 'Invalid image type. Allowed: JPG, PNG, GIF, WEBP';
        elseif ($_FILES['image']['size'] > $max_size) $errors[] = 'Image size must be less than 2MB';
        else {
            // Use tenant-isolated upload directory
            $middleware = new TenantIsolationMiddleware();
            $upload_dir = $middleware->applyFileUploadIsolation(PUBLIC_PATH . '/uploads/brand_images');
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
            $extension = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
            $filename = uniqid('brand_') . '_' . time() . '.' . $extension;
            $destination = $upload_dir . $filename;
            if (move_uploaded_file($_FILES['image']['tmp_name'], $destination)) {
                if ($image && file_exists(PUBLIC_PATH . $image)) unlink(PUBLIC_PATH . $image);
                $imagePath = '/uploads/brand_images/tenant_' . get_current_tenant_id() . '/' . $filename;
            } else { $errors[] = 'Failed to upload image'; }
        }
    } elseif (isset($_POST['remove_image']) && $_POST['remove_image'] === '1') {
        if ($image && file_exists(PUBLIC_PATH . $image)) unlink(PUBLIC_PATH . $image);
        $imagePath = null;
    }

    if (empty($errors)) {
        try {
            // Validate CSRF token
            if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
                $errors[] = 'Security token validation failed';
                log_security_event('csrf_validation_failed', [
                    'form' => 'brand_form',
                    'action' => $id ? 'update' : 'create'
                ]);
            }

            if (empty($errors)) {
                if ($id) {
                    // Update existing brand using secure operations
                    $brand_data = [
                        'name' => $name,
                        'description' => $description,
                        'image' => $imagePath,
                        'active' => $active,
                        'updated_at' => date('Y-m-d H:i:s')
                    ];
                    
                    $affected_rows = secure_db_update('brands', $brand_data, ['id' => $id]);
                    
                    if ($affected_rows > 0) {
                        log_security_event('brand_update', [
                            'brand_id' => $id,
                            'brand_name' => $name,
                            'old_data' => $brand ?? [],
                            'new_data' => $brand_data
                        ]);
                        $redirect_msg = 'Brand updated successfully';
                    } else {
                        $errors[] = 'No changes made to brand';
                    }
                } else {
                    // Create new brand using secure operations
                    $brand_data = [
                        'name' => $name,
                        'description' => $description,
                        'image' => $imagePath,
                        'active' => $active,
                        'created_at' => date('Y-m-d H:i:s'),
                        'updated_at' => date('Y-m-d H:i:s')
                    ];
                    
                    $new_id = secure_db_insert('brands', $brand_data);
                    
                    if ($new_id > 0) {
                        log_security_event('brand_create', [
                            'brand_id' => $new_id,
                            'brand_name' => $name,
                            'brand_data' => $brand_data
                        ]);
                        $redirect_msg = 'Brand created successfully';
                    } else {
                        $errors[] = 'Failed to create brand';
                    }
                }
            }

            if (empty($errors)) {
                header('Location: brands.php?branch_id=' . $branch_id . '&success=' . urlencode($redirect_msg));
                exit;
            }
        } catch (Exception $e) {
            error_log("Error saving brand: " . $e->getMessage());
            $errors[] = 'Database error: Failed to save brand - ' . $e->getMessage();
            
            // Log security event for database error
            log_security_event('brand_save_error', [
                'brand_id' => $id,
                'error' => $e->getMessage(),
                'action' => $id ? 'update' : 'create'
            ]);
        }
    }
}

$page_title = ($id ? 'Edit' : 'Add') . ' Brand';
$csrf_token = generate_csrf_token();
ob_start();
?>

<div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-5">
    <div>
        <a href="brands.php?branch_id=<?php echo $branch_id; ?>" class="inline-flex items-center gap-1.5 text-sm text-slate-500 hover:text-amber-400 transition-colors mb-2">
            <i class="fas fa-arrow-left text-xs"></i> Back to Brands
        </a>
        <h1 class="text-lg font-bold text-white flex items-center gap-2">
            <i class="fas fa-<?php echo $id ? 'pen' : 'plus-circle'; ?> text-amber-400"></i>
            <?php echo $id ? 'Edit Brand' : 'Add New Brand'; ?>
        </h1>
        <p class="text-sm text-slate-500 mt-0.5"><?php echo $id ? 'Update brand information' : 'Create a new brand in your catalog'; ?></p>
    </div>
</div>

<?php if (!empty($errors)): ?>
    <div class="mb-6 bg-red-500/10 border border-red-500 rounded-xl p-4">
        <div class="flex items-start gap-3 text-red-400">
            <i class="fas fa-exclamation-triangle flex-shrink-0 mt-0.5"></i>
            <div class="text-white">
                <h3 class="font-semibold mb-2">Please fix the following errors:</h3>
                <ul class="list-disc list-inside text-sm space-y-1">
                    <?php foreach ($errors as $err): ?>
                        <li><?php echo htmlspecialchars($err); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
    </div>
<?php endif; ?>

<div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-5">
    <form method="post" enctype="multipart/form-data" class="space-y-5">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
        <div>
            <label for="name" class="block text-xs font-medium text-slate-400 uppercase tracking-wide mb-1">
                <i class="fas fa-tag text-amber-500 mr-1"></i>Brand Name <span class="text-red-400">*</span>
            </label>
            <input type="text" id="name" name="name" required value="<?php echo htmlspecialchars($name); ?>" placeholder="Enter brand name"
                   class="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-1 focus:ring-amber-500 transition-colors">
        </div>
        <div>
            <label for="description" class="block text-xs font-medium text-slate-400 uppercase tracking-wide mb-1">
                <i class="fas fa-align-left text-amber-500 mr-1"></i>Description
            </label>
            <textarea id="description" name="description" rows="3" placeholder="Enter brand description (optional)"
                      class="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-1 focus:ring-amber-500 transition-colors resize-none"><?php echo htmlspecialchars($description); ?></textarea>
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-400 uppercase tracking-wide mb-1">
                <i class="fas fa-image text-amber-500 mr-1"></i>Brand Image
            </label>
            <div class="flex flex-col md:flex-row gap-4">
                <div class="flex-1">
                    <input type="file" id="image" name="image" accept="image/jpeg,image/png,image/gif,image/webp"
                           class="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-slate-400 text-sm file:mr-3 file:py-1 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-amber-500 file:text-slate-900 hover:file:bg-amber-400 transition-all focus:outline-none">
                    <p class="text-xs text-slate-600 mt-1.5">JPG, PNG, GIF, WEBP &middot; max 2MB</p>
                </div>
                <?php if ($image): ?>
                <div class="relative">
                    <img src="<?php echo htmlspecialchars(base_url($image)); ?>" alt="Brand preview"
                         class="w-28 h-28 object-cover rounded-xl border border-slate-700/60">
                    <button type="button" onclick="removeImage()"
                            class="absolute -top-1.5 -right-1.5 w-5 h-5 flex items-center justify-center rounded-full bg-red-500 text-white text-xs hover:bg-red-600 transition-colors">
                        <i class="fas fa-times"></i>
                    </button>
                    <input type="hidden" name="remove_image" id="remove_image" value="0">
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php if ($id): ?>
        <div class="flex items-center gap-3 px-4 py-3 bg-slate-900/50 border border-slate-700/40 rounded-xl">
            <i class="fas fa-power-off text-amber-500 text-xs"></i>
            <label class="relative inline-flex items-center cursor-pointer">
                <input type="checkbox" name="active" value="1" class="sr-only peer" <?php echo $active ? 'checked' : ''; ?>>
                <div class="w-9 h-5 bg-slate-600 rounded-full peer peer-checked:bg-emerald-500 transition-colors"></div>
                <div class="absolute left-0.5 top-0.5 w-4 h-4 bg-white rounded-full transition-transform peer-checked:translate-x-4"></div>
            </label>
            <span class="text-sm text-slate-300">Brand Active</span>
            <span class="text-xs text-slate-500 ml-auto">Inactive brands won't appear in POS</span>
        </div>
        <?php endif; ?>
        <div class="flex gap-3 pt-4 border-t border-slate-700/60">
            <button type="submit" class="flex-1 inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-lg bg-amber-500/15 border border-amber-500/40 text-amber-400 text-sm font-semibold hover:bg-amber-500/25 transition-colors">
                <i class="fas fa-<?php echo $id ? 'save' : 'plus'; ?> text-xs"></i><?php echo $id ? 'Update Brand' : 'Create Brand'; ?>
            </button>
            <a href="brands.php?branch_id=<?php echo $branch_id; ?>" class="flex-1 inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-lg bg-slate-700 border border-slate-600 text-slate-300 text-sm font-medium hover:bg-slate-600 transition-colors">
                Cancel
            </a>
        </div>
    </form>
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

    document.addEventListener('keydown', function (e) {
        if ((e.ctrlKey || e.metaKey) && e.key === 's') { e.preventDefault(); document.querySelector('form').submit(); }
        if (e.key === 'Escape') { e.preventDefault(); window.location.href = 'brands.php?branch_id=<?php echo $branch_id; ?>'; }
    });
</script>

<?php
$page_content = ob_get_clean();
require_once __DIR__ . '/../layouts/app.php';