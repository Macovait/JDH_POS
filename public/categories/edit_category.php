<?php
/**
 * Edit Category Page for Jakababa POS
 * Edit existing product categories with AJAX support
 */

error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

require_once __DIR__ . '/../../src/auth.php';
require_login();
require_once __DIR__ . '/../../src/db.php';
require_once __DIR__ . '/../../src/functions.php';
require_once __DIR__ . '/../../src/logger.php';

// Helper function to check if a column exists
if (!function_exists('db_has_column')) {
    function db_has_column($table, $column) {
        try {
            $pdo = get_db_connection();
            $stmt = $pdo->prepare("SHOW COLUMNS FROM `$table` LIKE ?");
            $stmt->execute([$column]);
            return $stmt->fetch() !== false;
        } catch (Exception $e) {
            return false;
        }
    }
}

// Get current user info
$user_id = get_current_user_id();
$user_name = get_current_user_name();
$tenant_id = get_current_tenant_id();
$current_branch_id = get_current_branch_id();
$current_branch_name = get_current_branch_name();

// Get category ID from URL
$category_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if (!$category_id) {
    header('Location: list_categories.php?error=' . urlencode('No category ID provided'));
    exit;
}

// Initialize variables
$category = null;
$parent_categories = [];
$error = '';
$success = '';

try {
    $pdo = get_db_connection();

    // Check if deleted_at column exists
    $has_deleted_at = db_has_column('categories', 'deleted_at');

    // Build WHERE clause conditionally
    $where_extra = 'c.id = ?';
    $params = [$category_id];

    if ($current_branch_id > 0) {
        $where_extra .= ' AND (c.branch_id = ? OR c.branch_id IS NULL)';
        $params[] = $current_branch_id;
    }

    if ($has_deleted_at) {
        $where_extra .= ' AND (c.deleted_at IS NULL OR c.deleted_at = "0000-00-00 00:00:00")';
    }

    // Fetch category details
    $sql = "
        SELECT c.*, 
               p.name as parent_name,
               u.name as created_by_name,
               u2.name as updated_by_name
        FROM categories c
        LEFT JOIN categories p ON c.parent_id = p.id
        LEFT JOIN users u ON c.created_by = u.id
        LEFT JOIN users u2 ON c.updated_by = u2.id
        WHERE " . $where_extra . "
        LIMIT 1
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $category = $stmt->fetch();

    if (!$category) {
        header('Location: list_categories.php?error=' . urlencode('Category not found'));
        exit;
    }

    // Fetch parent categories for dropdown
    $parent_where = 'id != ? AND status = "active"';
    $parent_params = [$category_id];

    if ($current_branch_id > 0) {
        $parent_where .= ' AND (branch_id = ? OR branch_id IS NULL)';
        $parent_params[] = $current_branch_id;
    }

    if ($has_deleted_at) {
        $parent_where .= ' AND (deleted_at IS NULL OR deleted_at = "0000-00-00 00:00:00")';
    }

    $stmt = $pdo->prepare("SELECT id, name FROM categories WHERE " . $parent_where . " ORDER BY name");
    $stmt->execute($parent_params);
    $parent_categories = $stmt->fetchAll();

} catch (PDOException $e) {
    error_log("Error fetching category: " . $e->getMessage());
    $error = "Failed to load category: " . $e->getMessage();
}

// Handle regular form submission (non-AJAX)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo = get_db_connection();
        $pdo->beginTransaction();

        $id = (int) ($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $parent_id = !empty($_POST['parent_id']) ? (int) $_POST['parent_id'] : null;
        $description = trim($_POST['description'] ?? '');
        $color = trim($_POST['color'] ?? '#3B82F6');
        $icon = trim($_POST['icon'] ?? 'tag');
        $status = $_POST['status'] ?? 'active';
        $meta_title = trim($_POST['meta_title'] ?? '');
        $meta_description = trim($_POST['meta_description'] ?? '');
        $sort_order = (int) ($_POST['sort_order'] ?? 0);

        if (empty($name)) {
            throw new Exception('Category name is required');
        }

        $image_path = $category['image'] ?? null;

        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = $_SERVER['DOCUMENT_ROOT'] . '/JDH_POS/public/uploads/categories/';

            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }

            $file_extension = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
            $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'];

            if (in_array($file_extension, $allowed_extensions) && $_FILES['image']['size'] <= 2 * 1024 * 1024) {
                $filename = 'category_' . $id . '_' . time() . '.' . $file_extension;
                $target_path = $upload_dir . $filename;
                $web_path = '/JDH_POS/public/uploads/categories/' . $filename;

                if (move_uploaded_file($_FILES['image']['tmp_name'], $target_path)) {
                    // Delete old image if exists
                    if (!empty($category['image']) && file_exists($_SERVER['DOCUMENT_ROOT'] . $category['image'])) {
                        unlink($_SERVER['DOCUMENT_ROOT'] . $category['image']);
                    }
                    $image_path = $web_path;
                }
            }
        } elseif (isset($_POST['remove_image']) && $_POST['remove_image'] == '1') {
            // Remove image
            if (!empty($category['image']) && file_exists($_SERVER['DOCUMENT_ROOT'] . $category['image'])) {
                unlink($_SERVER['DOCUMENT_ROOT'] . $category['image']);
            }
            $image_path = null;
        }

        $stmt = $pdo->prepare("
            UPDATE categories 
            SET name = ?, parent_id = ?, description = ?, color = ?, icon = ?, 
                image = ?, meta_title = ?, meta_description = ?, sort_order = ?, 
                status = ?, updated_by = ?, updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([
            $name, $parent_id, $description, $color, $icon,
            $image_path, $meta_title, $meta_description, $sort_order,
            $status, $user_id, $id
        ]);

        if (function_exists('log_activity')) {
            log_activity($user_id, 'category_updated', [
                'category_id' => $id, 'category_name' => $name
            ], get_current_tenant_id());
        }

        $pdo->commit();

        header('Location: list_categories.php?message=' . urlencode('Category updated successfully'));
        exit;

    } catch (Exception $e) {
        if (isset($pdo) && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $error = $e->getMessage();
    }
}

// Render HTML page
$page_title = 'Edit Category';
ob_start();
?>

<style>
    .bg-slate-800/40 border border-slate-700/60 rounded-xl {
        background: rgba(31, 41, 55, 0.7);
        backdrop-filter: blur(10px);
        border: 1px solid #374151;
        border-radius: 1rem;
    }

    .form-input {
        background: #111827;
        border: 1px solid #374151;
        border-radius: 0.75rem;
        padding: 0.75rem 1rem;
        color: white;
        width: 100%;
    }

    .form-input:focus {
        border-color: #FBBF24;
        outline: none;
        box-shadow: 0 0 0 3px rgba(251, 191, 36, 0.2);
    }

    select.form-input option {
        background: #1F2937;
        color: white;
    }

    .image-preview {
        width: 100%;
        height: 200px;
        border-radius: 0.75rem;
        border: 2px dashed #374151;
        display: flex;
        align-items: center;
        justify-content: center;
        background: #111827;
        position: relative;
    }

    .image-preview.has-image {
        border: 2px solid #FBBF24;
    }

    .image-preview img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .image-preview .placeholder {
        text-align: center;
        color: #9CA3AF;
    }

    .image-remove-btn {
        position: absolute;
        top: 0.5rem;
        right: 0.5rem;
        background: rgba(239, 68, 68, 0.9);
        color: white;
        width: 32px;
        height: 32px;
        border-radius: 50%;
        border: none;
        cursor: pointer;
    }

    .status-badge {
        display: inline-flex;
        align-items: center;
        padding: 0.25rem 0.75rem;
        border-radius: 9999px;
        font-size: 0.7rem;
    }

    .status-active {
        background: rgba(16, 185, 129, 0.2);
        color: #10B981;
    }

    .status-inactive {
        background: rgba(239, 68, 68, 0.2);
        color: #EF4444;
    }

    .toast-container {
        position: fixed;
        bottom: 1rem;
        right: 1rem;
        z-index: 9999;
    }

    .toast {
        background: rgba(31, 41, 55, 0.95);
        border-radius: 0.75rem;
        padding: 0.75rem 1.5rem;
        color: white;
        margin-bottom: 0.5rem;
        animation: slideIn 0.3s ease;
    }

    .toast.success { border-left: 4px solid #10B981; }
    .toast.error { border-left: 4px solid #EF4444; }

    @keyframes slideIn {
        from { transform: translateX(100%); opacity: 0; }
        to { transform: translateX(0); opacity: 1; }
    }
</style>

<div class="fade-in">

<!-- Main Content -->
<div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-6">

    <?php if ($error): ?>
        <div class="mb-6 bg-red-500/10 border border-red-500 rounded-xl p-4">
            <div class="flex items-center gap-3 text-red-400">
                <i class="fas fa-exclamation-circle"></i>
                <span><?php echo htmlspecialchars($error); ?></span>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($category): ?>
        <!-- Category Info Card -->
        <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-6 mb-6">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-lg font-semibold flex items-center gap-2">
                    <i class="fas fa-info-circle text-yellow-500"></i>
                    Category Information
                </h2>
                <div class="flex items-center gap-2 text-sm text-gray-400">
                    <span>Created: <?php echo date('M j, Y', strtotime($category['created_at'])); ?></span>
                </div>
            </div>
            <div class="grid grid-cols-2 gap-4 text-sm">
                <div>
                    <span class="text-gray-400">Category ID:</span>
                    <span class="ml-2 text-white">#<?php echo $category['id']; ?></span>
                </div>
                <div>
                    <span class="text-gray-400">Status:</span>
                    <span class="ml-2">
                        <span class="status-badge <?php echo ($category['status'] ?? 'active') == 'active' ? 'status-active' : 'status-inactive'; ?>">
                            <?php echo ucfirst($category['status'] ?? 'Active'); ?>
                        </span>
                    </span>
                </div>
                <?php if (!empty($category['parent_name'])): ?>
                <div>
                    <span class="text-gray-400">Parent:</span>
                    <span class="ml-2 text-white"><?php echo htmlspecialchars($category['parent_name']); ?></span>
                </div>
                <?php endif; ?>
                <?php if (!empty($category['created_by_name'])): ?>
                <div>
                    <span class="text-gray-400">Created By:</span>
                    <span class="ml-2 text-white"><?php echo htmlspecialchars($category['created_by_name']); ?></span>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Edit Form -->
        <form id="editCategoryForm" class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-6 space-y-4" enctype="multipart/form-data">
            <input type="hidden" name="id" value="<?php echo $category['id']; ?>">
            <input type="hidden" name="remove_image" id="remove_image" value="0">

            <!-- Basic Information -->
            <div class="space-y-4">
                <h3 class="font-medium flex items-center gap-2 border-b border-gray-700 pb-2">
                    <i class="fas fa-info-circle text-yellow-500"></i>
                    Basic Information
                </h3>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label for="categoryName" class="block text-sm text-gray-400 mb-2">Category Name *</label>
                        <input type="text" name="name" id="categoryName" required
                            value="<?php echo htmlspecialchars($category['name']); ?>"
                            class="form-input"
                            placeholder="e.g., Electronics, Clothing"
                            autocomplete="off">
                    </div>

                    <div>
                        <label for="parentCategory" class="block text-sm text-gray-400 mb-2">Parent Category</label>
                        <select name="parent_id" id="parentCategory" class="form-input" autocomplete="off">
                            <option value="">-- None (Top Level) --</option>
                            <?php foreach ($parent_categories as $parent): ?>
                                <option value="<?php echo $parent['id']; ?>" 
                                    <?php echo ($category['parent_id'] ?? null) == $parent['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($parent['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div>
                    <label for="categoryDescription" class="block text-sm text-gray-400 mb-2">Description</label>
                    <textarea name="description" id="categoryDescription" rows="3"
                        class="form-input"
                        placeholder="Describe this category..."
                        autocomplete="off"><?php echo htmlspecialchars($category['description'] ?? ''); ?></textarea>
                </div>
            </div>

            <!-- Appearance -->
            <div class="space-y-4">
                <h3 class="font-medium flex items-center gap-2 border-b border-gray-700 pb-2">
                    <i class="fas fa-palette text-yellow-500"></i>
                    Appearance
                </h3>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label for="categoryColor" class="block text-sm text-gray-400 mb-2">Color</label>
                        <div class="flex gap-2">
                            <input type="color" name="color" id="categoryColor"
                                value="<?php echo $category['color'] ?? '#3B82F6'; ?>"
                                class="h-11 w-11 rounded-lg border border-gray-700 bg-transparent cursor-pointer"
                                autocomplete="off">
                            <label for="colorHex" class="sr-only">Color Hex Value</label>
                            <input type="text" name="color_hex" id="colorHex"
                                value="<?php echo $category['color'] ?? '#3B82F6'; ?>"
                                class="form-input flex-1"
                                placeholder="#3B82F6"
                                autocomplete="off">
                        </div>
                    </div>

                    <div>
                        <label for="categoryIcon" class="block text-sm text-gray-400 mb-2">Icon</label>
                        <select name="icon" id="categoryIcon" class="form-input" autocomplete="off">
                            <option value="tag" <?php echo ($category['icon'] ?? 'tag') == 'tag' ? 'selected' : ''; ?>>Tag</option>
                            <option value="folder" <?php echo ($category['icon'] ?? '') == 'folder' ? 'selected' : ''; ?>>Folder</option>
                            <option value="star" <?php echo ($category['icon'] ?? '') == 'star' ? 'selected' : ''; ?>>Star</option>
                            <option value="heart" <?php echo ($category['icon'] ?? '') == 'heart' ? 'selected' : ''; ?>>Heart</option>
                            <option value="box" <?php echo ($category['icon'] ?? '') == 'box' ? 'selected' : ''; ?>>Box</option>
                            <option value="fire" <?php echo ($category['icon'] ?? '') == 'fire' ? 'selected' : ''; ?>>Fire</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Image Upload -->
            <div class="space-y-4">
                <h3 class="font-medium flex items-center gap-2 border-b border-gray-700 pb-2">
                    <i class="fas fa-image text-yellow-500"></i>
                    <label for="categoryImage" class="cursor-pointer">Category Image</label>
                </h3>

                <div id="imagePreviewContainer" class="image-preview <?php echo !empty($category['image']) ? 'has-image' : ''; ?>" onclick="document.getElementById('categoryImage').click()">
                    <?php if (!empty($category['image'])): ?>
                        <img src="<?php echo htmlspecialchars($category['image']); ?>" alt="Category image" id="previewImage">
                        <button type="button" onclick="removeImage()" class="image-remove-btn" title="Remove">
                            <i class="fas fa-times"></i>
                        </button>
                    <?php else: ?>
                        <div class="placeholder" id="previewPlaceholder">
                            <i class="fas fa-cloud-upload-alt text-3xl mb-2"></i>
                            <p class="text-sm">Click to upload image</p>
                            <p class="text-xs mt-1">JPG, PNG, GIF up to 2MB</p>
                        </div>
                    <?php endif; ?>
                </div>

                <input type="file" name="image" id="categoryImage" accept="image/*" class="hidden" autocomplete="off">
                <div class="flex items-center gap-3">
                    <button type="button" onclick="document.getElementById('categoryImage').click()"
                        class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg text-sm">
                        <i class="fas fa-upload"></i> Choose Image
                    </button>
                    <span id="fileName" class="text-sm text-gray-400">No file chosen</span>
                </div>
            </div>

            <!-- SEO & Settings -->
            <div class="space-y-4">
                <h3 class="font-medium flex items-center gap-2 border-b border-gray-700 pb-2">
                    <i class="fas fa-cog text-yellow-500"></i>
                    SEO & Settings
                </h3>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label for="sortOrder" class="block text-sm text-gray-400 mb-2">Sort Order</label>
                        <input type="number" name="sort_order" id="sortOrder"
                            value="<?php echo (int) ($category['sort_order'] ?? 0); ?>"
                            min="0" class="form-input" autocomplete="off">
                    </div>

                    <div>
                        <span class="block text-sm text-gray-400 mb-2">Status</span>
                        <div class="flex items-center gap-4" role="radiogroup" aria-label="Status">
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="radio" name="status" id="statusActive" value="active"
                                    <?php echo ($category['status'] ?? 'active') == 'active' ? 'checked' : ''; ?>
                                    class="accent-yellow-500" autocomplete="off">
                                <span class="text-sm">Active</span>
                            </label>
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="radio" name="status" id="statusInactive" value="inactive"
                                    <?php echo ($category['status'] ?? '') == 'inactive' ? 'checked' : ''; ?>
                                    class="accent-yellow-500" autocomplete="off">
                                <span class="text-sm">Inactive</span>
                            </label>
                        </div>
                    </div>
                </div>

                <div>
                    <label for="metaTitle" class="block text-sm text-gray-400 mb-2">Meta Title (SEO)</label>
                    <input type="text" name="meta_title" id="metaTitle"
                        value="<?php echo htmlspecialchars($category['meta_title'] ?? ''); ?>"
                        class="form-input"
                        placeholder="SEO title (optional)"
                        autocomplete="off">
                </div>

                <div>
                    <label for="metaDescription" class="block text-sm text-gray-400 mb-2">Meta Description (SEO)</label>
                    <textarea name="meta_description" id="metaDescription" rows="2"
                        class="form-input"
                        placeholder="SEO description (optional)"
                        autocomplete="off"><?php echo htmlspecialchars($category['meta_description'] ?? ''); ?></textarea>
                </div>
            </div>

            <!-- Form Actions -->
            <div class="flex items-center justify-end gap-3 pt-4 border-t border-gray-700">
                <a href="list_categories.php"
                    class="px-6 py-3 bg-gray-800 border border-gray-700 text-white rounded-xl hover:border-yellow-500 transition">
                    Cancel
                </a>
                <button type="submit" id="submitBtn"
                    class="px-6 py-3 bg-yellow-500 text-gray-900 rounded-xl font-semibold hover:bg-yellow-400 transition">
                    <i class="fas fa-save"></i> Update Category
                </button>
            </div>
        </form>
    <?php endif; ?>
</div>

<!-- Toast Container -->
<div id="toastContainer" class="toast-container"></div>

<script>
const form = document.getElementById('editCategoryForm');
const submitBtn = document.getElementById('submitBtn');
const colorInput = document.getElementById('categoryColor');
const colorHex = document.getElementById('colorHex');
const imageInput = document.getElementById('categoryImage');
const fileName = document.getElementById('fileName');
const previewContainer = document.getElementById('imagePreviewContainer');
const removeImageInput = document.getElementById('remove_image');

colorInput.addEventListener('input', () => { colorHex.value = colorInput.value; });
colorHex.addEventListener('input', () => { if (/^#[0-9A-F]{6}$/i.test(colorHex.value)) colorInput.value = colorHex.value; });

imageInput.addEventListener('change', function(e) {
    if (this.files && this.files[0]) {
        const file = this.files[0];
        if (file.size > 2 * 1024 * 1024) {
            showToast('File size must be less than 2MB', 'error');
            this.value = '';
            return;
        }
        fileName.textContent = file.name;
        
        const reader = new FileReader();
        reader.onload = function(e) {
            previewContainer.innerHTML = `<img src="${e.target.result}" alt="Preview"><button type="button" onclick="removeImage()" class="image-remove-btn"><i class="fas fa-times"></i></button>`;
            previewContainer.classList.add('has-image');
            removeImageInput.value = '0';
        };
        reader.readAsDataURL(file);
    }
});

function removeImage() {
    if (confirm('Remove this image?')) {
        imageInput.value = '';
        fileName.textContent = 'No file chosen';
        previewContainer.innerHTML = `<div class="placeholder" id="previewPlaceholder"><i class="fas fa-cloud-upload-alt text-3xl mb-2"></i><p class="text-sm">Click to upload image</p><p class="text-xs mt-1">JPG, PNG, GIF up to 2MB</p></div>`;
        previewContainer.classList.remove('has-image');
        removeImageInput.value = '1';
    }
}

form.addEventListener('submit', function(e) {
    e.preventDefault();
    const name = document.getElementById('categoryName').value.trim();
    if (!name) {
        showToast('Category name is required', 'error');
        return;
    }

    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Updating...';
    submitBtn.disabled = true;

    const formData = new FormData(form);

    fetch('edit_category_ajax.php', {
        method: 'POST',
        body: formData
    })
    .then(async res => {
        const text = await res.text();
        try {
            return JSON.parse(text);
        } catch (e) {
            console.error('Invalid JSON response:', text);
            throw new Error('Server returned invalid response. Check console for details.');
        }
    })
    .then(data => {
        if (data.success) {
            showToast(data.message, 'success');
            setTimeout(() => { window.location.href = data.redirect; }, 1500);
        } else {
            showToast(data.message || 'Update failed', 'error');
            submitBtn.innerHTML = '<i class="fas fa-save"></i> Update Category';
            submitBtn.disabled = false;
        }
    })
    .catch(err => {
        console.error('Fetch error:', err);
        showToast('Error: ' + err.message, 'error');
        submitBtn.innerHTML = '<i class="fas fa-save"></i> Update Category';
        submitBtn.disabled = false;
    });
});

function showToast(message, type = 'success') {
    const container = document.getElementById('toastContainer');
    const toast = document.createElement('div');
    toast.className = `toast ${type}`;
    toast.innerHTML = `<i class="fas ${type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'}"></i><span>${message}</span>`;
    container.appendChild(toast);
    setTimeout(() => { toast.style.opacity = '0'; setTimeout(() => toast.remove(), 300); }, 3000);
}

document.addEventListener('keydown', function(e) {
    if (e.ctrlKey && e.key === 's') {
        e.preventDefault();
        form.dispatchEvent(new Event('submit'));
    }
    if (e.key === 'Escape') {
        window.location.href = 'list_categories.php';
    }
});
</script>

</div>

<?php
$page_content = ob_get_clean();
require_once __DIR__ . '/../layouts/app_close.php';
require_once __DIR__ . '/../layouts/app.php';
?>