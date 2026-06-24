<?php
/**
 * Add Category Page for Jakababa POS
 * Add new product categories
 */

// Include required files FIRST (before any output)
require_once __DIR__ . '/../../src/auth.php';
require_login();
require_once __DIR__ . '/../../src/db.php';
require_once __DIR__ . '/../../src/functions.php';

// Production-ready: debug logging disabled
function debug_log($message, $data = null) {
    // No-op in production
    return;
}

/**
 * Safe escape function to handle null values
 * 
 * @param mixed $value The value to escape
 * @return string Escaped string or empty string if null
 */
function safe_escape($value) {
    if ($value === null) {
        return '';
    }
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

// This file handles both adding new category and editing existing ones
// Check if we're editing an existing category (id provided) or adding new (no id)
$category_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
debug_log("Category ID from URL", ['id' => $category_id]);

$page_title = ($category_id > 0 ? 'Edit' : 'Add') . ' Category | Jakababa POS';
$user_id = (int) ($_SESSION['user_id'] ?? 0);
$user_name = safe_escape($_SESSION['user_name'] ?? 'User');
$user_role = safe_escape($_SESSION['role'] ?? '');
$current_branch_id = get_current_branch_id();
$current_branch_name = safe_escape(get_current_branch_name());

debug_log("User info", ['user_id' => $user_id, 'branch_id' => $current_branch_id, 'branch_name' => $current_branch_name]);

// Get currency symbol from settings
$currency_symbol = 'KSh'; // Default
try {
    $pdo = get_db_connection();
    if ($pdo) {
        $stmt = $pdo->query("SELECT setting_value FROM settings WHERE setting_key = 'currency'");
        $currency = $stmt->fetchColumn();
        if ($currency) {
            $currency_symbol = $currency;
        }
        debug_log("Currency fetched", ['currency' => $currency_symbol]);
    }
} catch (Exception $e) {
    debug_log("Error fetching currency: " . $e->getMessage());
}

// Initialize variables
$category = null;
$parent_categories = [];
$error = '';
$success = '';

try {
    // Get database connection
    debug_log("Attempting database connection");
    $pdo = get_db_connection();
    if (!$pdo) {
        throw new Exception('Database connection failed');
    }
    debug_log("Database connection successful");

    // Test database query
    $test = $pdo->query("SELECT 1")->fetch();
    debug_log("Database test query", ['result' => $test]);

    // Fetch category details when editing an existing category
    if ($category_id > 0) {
        debug_log("Fetching category details for ID: " . $category_id);
        $stmt = $pdo->prepare("
            SELECT c.*, 
                   p.name as parent_name,
                   p.color as parent_color,
                   p.icon as parent_icon,
                   u.name as created_by_name,
                   u2.name as updated_by_name,
                   (SELECT COUNT(*) FROM products WHERE category_id = c.id AND deleted_at IS NULL) as product_count,
                   (SELECT COUNT(*) FROM categories WHERE parent_id = c.id AND deleted_at IS NULL) as subcategory_count
            FROM categories c
            LEFT JOIN categories p ON c.parent_id = p.id
            LEFT JOIN users u ON c.created_by = u.id
            LEFT JOIN users u2 ON c.updated_by = u2.id
            WHERE c.id = ? AND (c.branch_id = ? OR c.branch_id IS NULL) AND c.deleted_at IS NULL
        ");
        
        debug_log("Category query prepared", ['sql' => $stmt->queryString]);
        
        $stmt->execute([$category_id, $current_branch_id]);
        $category = $stmt->fetch();
        
        debug_log("Category fetch result", ['found' => !empty($category), 'data' => $category]);

        if (!$category) {
            debug_log("Category not found, redirecting");
            header('Location: list_categories.php?error=' . urlencode('Category not found'));
            exit;
        }
    } else {
        debug_log("Adding new category - no ID provided");
    }

    // Fetch parent categories for dropdown (excluding current category and its children)
    debug_log("Fetching parent categories");
    $stmt = $pdo->prepare("
        SELECT id, name, color, icon 
        FROM categories 
        WHERE id != ? 
        AND (branch_id = ? OR branch_id IS NULL) 
        AND deleted_at IS NULL 
        AND status = 'active'
        AND (parent_id IS NULL OR parent_id != ?)
        ORDER BY name
    ");
    $stmt->execute([$category_id, $current_branch_id, $category_id]);
    $parent_categories = $stmt->fetchAll();
    
    debug_log("Parent categories fetched", ['count' => count($parent_categories)]);

    // Fetch available icons from database or use default list
    $icons = [];
    try {
        $stmt = $pdo->query("SELECT DISTINCT icon FROM categories WHERE icon IS NOT NULL AND icon != '' GROUP BY icon ORDER BY icon");
        $icons = $stmt->fetchAll(PDO::FETCH_COLUMN);
        debug_log("Icons fetched from database", ['count' => count($icons), 'icons' => $icons]);
    } catch (Exception $e) {
        debug_log("Error fetching icons from database: " . $e->getMessage());
        // If query fails, use default icon list
        $icons = ['tag', 'folder', 'star', 'heart', 'bookmark', 'flag', 'bolt', 'fire', 'gift', 'crown'];
    }

    // If no icons found in database, use default list
    if (empty($icons)) {
        $icons = ['tag', 'folder', 'star', 'heart', 'bookmark', 'flag', 'bolt', 'fire', 'gift', 'crown'];
        debug_log("Using default icons", ['icons' => $icons]);
    }

} catch (PDOException $e) {
    debug_log("PDOException: " . $e->getMessage());
    debug_log("Stack trace: " . $e->getTraceAsString());
    $error = "Failed to load category: " . $e->getMessage();
} catch (Exception $e) {
    debug_log("General exception: " . $e->getMessage());
    debug_log("Stack trace: " . $e->getTraceAsString());
    $error = $e->getMessage();
}

// Handle AJAX requests for form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
    debug_log("Processing AJAX request");
    header('Content-Type: application/json');
    $response = ['success' => false, 'message' => ''];

    try {
        // Get database connection
        debug_log("AJAX: Getting database connection");
        $pdo = get_db_connection();
        if (!$pdo) {
            throw new Exception('Database connection failed');
        }
        
        $pdo->beginTransaction();
        debug_log("AJAX: Transaction started");

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

        debug_log("AJAX: Form data", [
            'id' => $id,
            'name' => $name,
            'parent_id' => $parent_id,
            'color' => $color,
            'icon' => $icon,
            'status' => $status,
            'sort_order' => $sort_order
        ]);

        // Validation
        if (empty($name)) {
            throw new Exception('Category name is required');
        }

        // Check if category name already exists (excluding current)
        debug_log("AJAX: Checking for duplicate name");
        $stmt = $pdo->prepare("SELECT id FROM categories WHERE name = ? AND id != ? AND (branch_id = ? OR branch_id IS NULL) AND deleted_at IS NULL");
        $stmt->execute([$name, $id, $current_branch_id]);
        if ($stmt->fetch()) {
            throw new Exception('A category with this name already exists');
        }
        debug_log("AJAX: Name is unique");

        // Check if trying to set parent to self or child
        if ($parent_id == $id) {
            throw new Exception('A category cannot be its own parent');
        }

        // Check for circular reference (if parent is a child of current category)
        if ($parent_id) {
            debug_log("AJAX: Checking for circular reference");
            $check_circular = $pdo->prepare("
                WITH RECURSIVE category_path AS (
                    SELECT id, parent_id FROM categories WHERE id = ?
                    UNION ALL
                    SELECT c.id, c.parent_id FROM categories c
                    INNER JOIN category_path cp ON c.id = cp.parent_id
                )
                SELECT id FROM category_path WHERE id = ?
            ");
            $check_circular->execute([$parent_id, $id]);
            if ($check_circular->fetch()) {
                throw new Exception('Cannot set parent category that would create a circular reference');
            }
            debug_log("AJAX: No circular reference detected");
        }

        // Handle image upload
        $image_path = $category['image'] ?? null; // Keep existing image by default when editing
        debug_log("AJAX: Current image path", ['image_path' => $image_path]);
        
        // Check if image was uploaded
        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            debug_log("AJAX: Image upload detected", $_FILES['image']);
            
            // Create upload directory if it doesn't exist
            $upload_dir = $_SERVER['DOCUMENT_ROOT'] . '/JDH_POS/public/uploads/categories/';
            debug_log("AJAX: Upload directory", ['path' => $upload_dir]);
            
            // Create directory with proper permissions
            if (!file_exists($upload_dir)) {
                debug_log("AJAX: Creating upload directory");
                if (!mkdir($upload_dir, 0777, true)) {
                    throw new Exception('Failed to create upload directory. Please check permissions.');
                }
                debug_log("AJAX: Upload directory created");
            }

            // Check if directory is writable
            if (!is_writable($upload_dir)) {
                throw new Exception('Upload directory is not writable. Please check permissions.');
            }
            debug_log("AJAX: Upload directory is writable");

            $file_extension = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
            $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'];

            debug_log("AJAX: File extension", ['extension' => $file_extension]);

            if (!in_array($file_extension, $allowed_extensions)) {
                throw new Exception('Invalid file type. Only JPG, JPEG, PNG, GIF, WEBP, and SVG files are allowed.');
            }

            // Check file size (2MB max)
            if ($_FILES['image']['size'] > 2 * 1024 * 1024) {
                throw new Exception('File size must be less than 2MB');
            }
            debug_log("AJAX: File size check passed", ['size' => $_FILES['image']['size']]);

            // Generate unique filename
            $filename = 'category_' . $id . '_' . time() . '.' . $file_extension;
            $target_path = $upload_dir . $filename;
            $web_path = '/JDH_POS/public/uploads/categories/' . $filename;

            debug_log("AJAX: Target path", ['target' => $target_path, 'web_path' => $web_path]);

            // Upload file
            if (move_uploaded_file($_FILES['image']['tmp_name'], $target_path)) {
                debug_log("AJAX: File uploaded successfully");
                
                // Delete old image if exists
                if ($category['image']) {
                    $old_file = $_SERVER['DOCUMENT_ROOT'] . $category['image'];
                    debug_log("AJAX: Checking old image", ['old_file' => $old_file]);
                    if (file_exists($old_file)) {
                        unlink($old_file);
                        debug_log("AJAX: Old image deleted");
                    }
                }
                $image_path = $web_path;
                debug_log("AJAX: New image path set", ['image_path' => $image_path]);
            } else {
                throw new Exception('Failed to upload image. Please check file permissions.');
            }
        } 
        // Check if image should be removed
        elseif (isset($_POST['remove_image']) && $_POST['remove_image'] == '1') {
            debug_log("AJAX: Image removal requested");
            // Remove image
            if ($category['image']) {
                $old_file = $_SERVER['DOCUMENT_ROOT'] . $category['image'];
                debug_log("AJAX: Removing image", ['old_file' => $old_file]);
                if (file_exists($old_file)) {
                    unlink($old_file);
                    debug_log("AJAX: Image file deleted");
                }
            }
            $image_path = null;
            debug_log("AJAX: Image path set to null");
        }

        // Insert or update category
        debug_log("AJAX: Processing category data", ['id' => $id]);
        
        if ($id > 0) {
            // Update existing category
            debug_log("AJAX: Updating category in database");
            $stmt = $pdo->prepare("
                UPDATE categories 
                SET name = ?, parent_id = ?, description = ?, color = ?, icon = ?, 
                    image = ?, meta_title = ?, meta_description = ?, sort_order = ?, 
                    status = ?, updated_by = ?, updated_at = NOW()
                WHERE id = ?
            ");
            
            $params = [
                $name, $parent_id, $description, $color, $icon,
                $image_path, $meta_title, $meta_description, $sort_order,
                $status, $user_id, $id
            ];
            
            debug_log("AJAX: Update params", $params);
            
            $result = $stmt->execute($params);
            debug_log("AJAX: Update result", ['success' => $result]);

            // Log activity
            log_activity($user_id, 'category_updated', [
                'category_id' => $id, 'category_name' => $name,
                'changes' => [
                    'name' => $name,
                    'status' => $status
                ]
            ], get_current_tenant_id());
            debug_log("AJAX: Activity logged");

            $pdo->commit();
            debug_log("AJAX: Transaction committed");

            $response = [
                'success' => true,
                'message' => 'Category updated successfully',
                'redirect' => 'list_categories.php?message=' . urlencode('Category updated successfully')
            ];
        } else {
            // Insert new category
            debug_log("AJAX: Creating new category");
            $stmt = $pdo->prepare("
                INSERT INTO categories (name, parent_id, description, color, icon, image, meta_title, meta_description, sort_order, status, branch_id, created_by, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            
            $params = [
                $name, $parent_id, $description, $color, $icon,
                $image_path, $meta_title, $meta_description, $sort_order,
                $status, $current_branch_id, $user_id
            ];
            
            debug_log("AJAX: Insert params", $params);
            
            $stmt->execute($params);
            $new_id = $pdo->lastInsertId();
            debug_log("AJAX: New category created with ID: " . $new_id);

            // Log activity
            log_activity($user_id, 'category_created', [
                'category_id' => $new_id, 'category_name' => $name
            ], get_current_tenant_id());
            debug_log("AJAX: Activity logged");

            $pdo->commit();
            debug_log("AJAX: Transaction committed");

            $response = [
                'success' => true,
                'message' => 'Category created successfully',
                'redirect' => 'list_categories.php?message=' . urlencode('Category created successfully')
            ];
        }

    } catch (Exception $e) {
        debug_log("AJAX: Exception caught: " . $e->getMessage());
        debug_log("AJAX: Stack trace: " . $e->getTraceAsString());
        
        if (isset($pdo) && $pdo->inTransaction()) {
            $pdo->rollBack();
            debug_log("AJAX: Transaction rolled back");
        }
        $response = ['success' => false, 'message' => $e->getMessage()];
    }

    debug_log("AJAX: Sending response", $response);
    echo json_encode($response);
    exit;
}

// Handle regular form submission (fallback)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
    debug_log("Processing regular form submission");
    
    // Similar validation as AJAX but with redirect
    try {
        $pdo = get_db_connection();
        if (!$pdo) {
            throw new Exception('Database connection failed');
        }
        
        $pdo->beginTransaction();
        debug_log("Regular: Transaction started");

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

        debug_log("Regular: Form data", [
            'id' => $id,
            'name' => $name,
            'parent_id' => $parent_id,
            'color' => $color,
            'icon' => $icon,
            'status' => $status
        ]);

        if (empty($name)) {
            throw new Exception('Category name is required');
        }

        // Check if category name already exists (excluding current)
        $stmt = $pdo->prepare("SELECT id FROM categories WHERE name = ? AND id != ? AND (branch_id = ? OR branch_id IS NULL) AND deleted_at IS NULL");
        $stmt->execute([$name, $id, $current_branch_id]);
        if ($stmt->fetch()) {
            throw new Exception('A category with this name already exists');
        }

        // Handle image upload
        $image_path = $category['image'] ?? null;
        
        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            debug_log("Regular: Image upload detected", $_FILES['image']);
            
            // Create upload directory if it doesn't exist
            $upload_dir = $_SERVER['DOCUMENT_ROOT'] . '/JDH_POS/public/uploads/categories/';

            if (!file_exists($upload_dir)) {
                if (!mkdir($upload_dir, 0777, true)) {
                    throw new Exception('Failed to create upload directory');
                }
            }

            if (!is_writable($upload_dir)) {
                throw new Exception('Upload directory is not writable');
            }

            $file_extension = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
            $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'];

            if (!in_array($file_extension, $allowed_extensions)) {
                throw new Exception('Invalid file type. Only JPG, JPEG, PNG, GIF, WEBP, and SVG files are allowed.');
            }

            if ($_FILES['image']['size'] > 2 * 1024 * 1024) {
                throw new Exception('File size must be less than 2MB');
            }

            $filename = 'category_' . $id . '_' . time() . '.' . $file_extension;
            $target_path = $upload_dir . $filename;
            $web_path = '/JDH_POS/public/uploads/categories/' . $filename;

            if (move_uploaded_file($_FILES['image']['tmp_name'], $target_path)) {
                debug_log("Regular: File uploaded successfully");
                if ($category['image']) {
                    $old_file = $_SERVER['DOCUMENT_ROOT'] . $category['image'];
                    if (file_exists($old_file)) {
                        unlink($old_file);
                        debug_log("Regular: Old image deleted");
                    }
                }
                $image_path = $web_path;
            } else {
                throw new Exception('Failed to upload image');
            }
        } elseif (isset($_POST['remove_image']) && $_POST['remove_image'] == '1') {
            debug_log("Regular: Image removal requested");
            if ($category['image']) {
                $old_file = $_SERVER['DOCUMENT_ROOT'] . $category['image'];
                if (file_exists($old_file)) {
                    unlink($old_file);
                    debug_log("Regular: Image file deleted");
                }
            }
            $image_path = null;
        }

        // Insert or update category
        if ($id > 0) {
            // Update existing category
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
            
            debug_log("Regular: Category updated");

            log_activity($user_id, 'category_updated', [
                'category_id' => $id, 'category_name' => $name
            ], get_current_tenant_id());

            $pdo->commit();
            debug_log("Regular: Transaction committed");

            header('Location: list_categories.php?message=' . urlencode('Category updated successfully'));
        } else {
            // Insert new category
            $stmt = $pdo->prepare("
                INSERT INTO categories (name, parent_id, description, color, icon, image, meta_title, meta_description, sort_order, status, branch_id, created_by, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([
                $name, $parent_id, $description, $color, $icon,
                $image_path, $meta_title, $meta_description, $sort_order,
                $status, $current_branch_id, $user_id
            ]);
            
            $new_id = $pdo->lastInsertId();
            debug_log("Regular: New category created with ID: " . $new_id);

            log_activity($user_id, 'category_created', [
                'category_id' => $new_id, 'category_name' => $name
            ], get_current_tenant_id());

            $pdo->commit();
            debug_log("Regular: Transaction committed");

            header('Location: list_categories.php?message=' . urlencode('Category created successfully'));
        }
        exit;

    } catch (Exception $e) {
        debug_log("Regular: Exception caught: " . $e->getMessage());
        
        if (isset($pdo) && $pdo->inTransaction()) {
            $pdo->rollBack();
            debug_log("Regular: Transaction rolled back");
        }
        $error = $e->getMessage();
    }
}

// Helper function to display image with square aspect ratio
function displaySquareImage($image_path, $name)
{
    $safe_name = safe_escape($name ?? 'Category');
    
    if (!empty($image_path)) {
        // Check if file exists using absolute path
        $full_path = $_SERVER['DOCUMENT_ROOT'] . $image_path;
        if (file_exists($full_path)) {
            return '<img src="' . safe_escape($image_path) . '?v=' . filemtime($full_path) . '" 
                         alt="' . $safe_name . '"
                         class="w-full h-full object-cover rounded-lg"
                         onerror="this.onerror=null; console.error(\'Image failed to load: ' . safe_escape($image_path) . '\'); this.parentElement.innerHTML=\'<div class=\\\'w-full h-full rounded-lg border border-slate-700 flex items-center justify-center bg-gradient-to-br from-primary-dark to-primary\\\'><i class=\\\'fas fa-tag text-4xl text-amber-400/50\\\'></i></div>\';">';
        } else {
            debug_log("Image file not found", ['path' => $full_path]);
        }
    }

    // Return colored placeholder with icon
    return '<div class="w-full h-full rounded-lg border border-slate-700 flex items-center justify-center bg-gradient-to-br from-primary-dark to-primary">
        <i class="fas fa-tag text-4xl text-amber-400/50"></i>
    </div>';
}

// Add debug panel to HTML
$show_debug = isset($_GET['debug']) || isset($_SESSION['debug_mode']);
if ($show_debug) {
    debug_log("Debug mode enabled");
}

$page_title = ($category_id > 0 ? 'Edit' : 'Add') . ' Category';

// ============================================================
// DEBUG CHECKPOINT 10: Before layout
// ============================================================
file_put_contents(__DIR__ . '/../../storage/logs/debug_checkpoint.log', 
    "CHECKPOINT 10: " . date('Y-m-d H:i:s') . " - About to include layout, page_title=$page_title\n", 
    FILE_APPEND);

$layout_file = __DIR__ . '/../layouts/app.php';
if (!file_exists($layout_file)) {
    die("FATAL: layout app.php not found at: $layout_file");
}
ob_start();
?>

<!-- Main Content -->
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-6" style="background:#222;padding:20px;margin:10px;border-radius:8px;">
        <h2 style="color:#fff;margin-bottom:20px;"><?php echo $page_title; ?></h2>

        <?php if ($category_id == 0): ?>
            <!-- Add New Category Form -->
            <form id="addCategoryForm" class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-6 space-y-4 " enctype="multipart/form-data">
                <input type="hidden" name="id" value="0">

                <!-- Basic Information -->
                <div class="space-y-4">
                    <h3 class="font-medium flex items-center gap-2 border-b border-[#374151] pb-2">
                        <i class="fas fa-info-circle text-[#FBBF24]"></i>
                        Basic Information
                    </h3>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm text-[#9CA3AF] mb-2">
                                <i class="fas fa-tag mr-1"></i>Category Name *
                            </label>
                            <input type="text" name="name" id="categoryName" required
                                class="form-input"
                                placeholder="e.g., Electronics, Clothing, etc.">
                        </div>

                        <div>
                            <label class="block text-sm text-[#9CA3AF] mb-2">
                                <i class="fas fa-sitemap mr-1"></i>Parent Category
                            </label>
                            <select name="parent_id" id="parentCategory" class="form-input">
                                <option value="">-- None (Top Level) --</option>
                                <?php foreach ($parent_categories as $parent): ?>
                                    <option value="<?php echo (int)($parent['id'] ?? 0); ?>">
                                        <?php echo safe_escape($parent['name'] ?? ''); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <p class="text-xs text-[#6B7280] mt-1">Leave empty for top-level category</p>
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm text-[#9CA3AF] mb-2">
                            <i class="fas fa-align-left mr-1"></i>Description
                        </label>
                        <textarea name="description" id="categoryDescription" rows="3"
                            class="form-input"
                            placeholder="Describe this category..."></textarea>
                    </div>
                </div>

                <!-- Appearance -->
                <div class="space-y-4">
                    <h3 class="font-medium flex items-center gap-2 border-b border-[#374151] pb-2">
                        <i class="fas fa-palette text-[#FBBF24]"></i>
                        Appearance
                    </h3>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm text-[#9CA3AF] mb-2">
                                <i class="fas fa-paint-brush mr-1"></i>Color
                            </label>
                            <div class="flex gap-2">
                                <input type="color" name="color" id="categoryColor"
                                    value="#3B82F6"
                                    class="h-11 w-11 rounded-lg border border-[#374151] bg-transparent cursor-pointer">
                                <input type="text" name="color_hex" id="colorHex"
                                    value="#3B82F6"
                                    class="form-input flex-1"
                                    placeholder="#3B82F6"
                                    pattern="^#[0-9A-Fa-f]{6}$">
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm text-[#9CA3AF] mb-2">
                                <i class="fas fa-icons mr-1"></i>Icon
                            </label>
                            <div class="flex gap-2">
                                <select name="icon" id="categoryIcon" class="form-input flex-1">
                                    <?php foreach ($icons as $icon_option): ?>
                                        <option value="<?php echo safe_escape($icon_option); ?>"
                                            <?php echo $icon_option == 'tag' ? 'selected' : ''; ?>>
                                            <i class="fas fa-<?php echo safe_escape($icon_option); ?>"></i>
                                            <?php echo ucfirst(safe_escape($icon_option)); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="w-11 h-11 rounded-lg border border-[#374151] flex items-center justify-center"
                                     id="iconPreview" style="background-color: #3B82F620;">
                                    <i class="fas fa-tag text-xs"
                                       id="iconPreviewIcon"
                                       style="color: #3B82F6;"></i>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm text-[#9CA3AF] mb-2">
                            <i class="fas fa-image mr-1"></i>Category Image
                        </label>

                        <!-- Square Image Preview Container -->
                        <div class="image-preview-container">
                            <div id="imagePreviewContainer" class="image-preview">
                                <div class="placeholder" id="previewPlaceholder">
                                    <i class="fas fa-cloud-upload-alt"></i>
                                    <p class="text-sm">Click to upload</p>
                                    <p class="text-xs mt-1">Square image (1:1)</p>
                                </div>
                            </div>
                        </div>

                        <!-- File Input -->
                        <input type="file" name="image" id="categoryImage" accept="image/*" class="hidden">

                        <div class="flex items-center justify-center gap-3 mt-3">
                            <button type="button" onclick="document.getElementById('categoryImage').click()"
                                class="px-4 py-2 bg-[#3B82F6] hover:bg-[#2563EB] text-white rounded-lg transition text-sm flex items-center gap-2">
                                <i class="fas fa-upload"></i>
                                Choose Image
                            </button>
                            <span id="fileName" class="text-sm text-[#9CA3AF]">No file chosen</span>
                        </div>
                        <p class="text-xs text-[#6B7280] text-center mt-1">Maximum file size: 2MB. Square images work best (1:1 ratio)</p>
                    </div>
                </div>

                <!-- SEO & Settings -->
                <div class="space-y-4">
                    <h3 class="font-medium flex items-center gap-2 border-b border-[#374151] pb-2">
                        <i class="fas fa-cog text-[#FBBF24]"></i>
                        SEO & Settings
                    </h3>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm text-[#9CA3AF] mb-2">
                                <i class="fas fa-heading mr-1"></i>Meta Title
                            </label>
                            <input type="text" name="meta_title" id="metaTitle"
                                class="form-input"
                                placeholder="SEO title (optional)">
                        </div>

                        <div>
                            <label class="block text-sm text-[#9CA3AF] mb-2">
                                <i class="fas fa-sort-amount-down mr-1"></i>Sort Order
                            </label>
                            <input type="number" name="sort_order" id="sortOrder"
                                value="0"
                                min="0" step="1"
                                class="form-input"
                                placeholder="0">
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm text-[#9CA3AF] mb-2">
                            <i class="fas fa-align-left mr-1"></i>Meta Description
                        </label>
                        <textarea name="meta_description" id="metaDescription" rows="2"
                            class="form-input"
                            placeholder="SEO description (optional)"></textarea>
                    </div>

                    <div>
                        <label class="block text-sm text-[#9CA3AF] mb-2">
                            <i class="fas fa-circle mr-1"></i>Status
                        </label>
                        <div class="flex items-center gap-4">
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="radio" name="status" value="active" checked
                                    class="w-4 h-4 accent-[#FBBF24]">
                                <span class="text-sm">Active</span>
                            </label>
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="radio" name="status" value="inactive"
                                    class="w-4 h-4 accent-[#FBBF24]">
                                <span class="text-sm">Inactive</span>
                            </label>
                        </div>
                    </div>
                </div>

                <!-- Form Actions -->
                <div class="flex items-center justify-end gap-3 pt-4 border-t border-[#374151]">
                    <a href="list_categories.php"
                        class="px-6 py-3 bg-[#1F2937] border border-[#374151] text-white rounded-xl font-semibold hover:border-[#FBBF24] transition flex items-center gap-2">
                        <i class="fas fa-times"></i>
                        Cancel
                    </a>
                    <button type="submit" id="submitBtn"
                        class="px-6 py-3 bg-[#FBBF24] text-[#1E3A8A] rounded-xl font-semibold hover:bg-[#F59E0B] transition flex items-center gap-2">
                        <i class="fas fa-plus"></i>
                        <span>Create Category</span>
                    </button>
                </div>
            </form>
        <?php elseif ($category): ?>
            <!-- Category Info Card - Sourcing all data from database -->
            <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-6 mb-6 ">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-lg font-semibold flex items-center gap-2">
                        <i class="fas fa-info-circle text-[#FBBF24]"></i>
                        Category Information
                    </h2>
                    <div class="flex items-center gap-2 text-sm text-[#9CA3AF]">
                        <span><i class="fas fa-clock mr-1"></i>Created: <?php echo date('M j, Y H:i', strtotime($category['created_at'] ?? 'now')); ?></span>
                        <?php if (!empty($category['updated_at'])): ?>
                            <span>• <i class="fas fa-pen mr-1"></i>Updated: <?php echo date('M j, Y H:i', strtotime($category['updated_at'])); ?></span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
                    <div class="bg-[#1F2937] p-3 rounded-lg">
                        <span class="text-[#9CA3AF] block text-xs">Category ID</span>
                        <span class="text-white font-semibold">#<?php echo (int)($category['id'] ?? 0); ?></span>
                    </div>
                    <div class="bg-[#1F2937] p-3 rounded-lg">
                        <span class="text-[#9CA3AF] block text-xs">Status</span>
                        <span class="status-badge <?php echo ($category['status'] ?? 'active') == 'active' ? 'status-active' : 'status-inactive'; ?> mt-1">
                            <i class="fas fa-<?php echo ($category['status'] ?? 'active') == 'active' ? 'check-circle' : 'ban'; ?> mr-1"></i>
                            <?php echo ucfirst($category['status'] ?? 'Active'); ?>
                        </span>
                    </div>
                    <div class="bg-[#1F2937] p-3 rounded-lg">
                        <span class="text-[#9CA3AF] block text-xs">Products</span>
                        <span class="text-white font-semibold"><?php echo (int)($category['product_count'] ?? 0); ?></span>
                    </div>
                    <div class="bg-[#1F2937] p-3 rounded-lg">
                        <span class="text-[#9CA3AF] block text-xs">Subcategories</span>
                        <span class="text-white font-semibold"><?php echo (int)($category['subcategory_count'] ?? 0); ?></span>
                    </div>
                </div>
                <?php if (!empty($category['parent_name'])): ?>
                <div class="mt-3 bg-[#1F2937] p-3 rounded-lg">
                    <span class="text-[#9CA3AF] text-xs">Parent Category</span>
                    <div class="flex items-center gap-2 mt-1">
                        <div class="w-6 h-6 rounded flex items-center justify-center" style="background-color: <?php echo safe_escape($category['parent_color'] ?? '#3B82F6'); ?>20;">
                            <i class="fas fa-<?php echo safe_escape($category['parent_icon'] ?? 'folder'); ?> text-xs" style="color: <?php echo safe_escape($category['parent_color'] ?? '#3B82F6'); ?>;"></i>
                        </div>
                        <span class="text-white"><?php echo safe_escape($category['parent_name']); ?></span>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <!-- Edit Form -->
            <form id="editCategoryForm" class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-6 space-y-4 " enctype="multipart/form-data">
                <input type="hidden" name="id" value="<?php echo (int)($category['id'] ?? 0); ?>">
                <input type="hidden" name="remove_image" id="remove_image" value="0">

                <!-- Basic Information -->
                <div class="space-y-4">
                    <h3 class="font-medium flex items-center gap-2 border-b border-[#374151] pb-2">
                        <i class="fas fa-info-circle text-[#FBBF24]"></i>
                        Basic Information
                    </h3>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm text-[#9CA3AF] mb-2">
                                <i class="fas fa-tag mr-1"></i>Category Name *
                            </label>
                            <input type="text" name="name" id="categoryName" required
                                value="<?php echo safe_escape($category['name'] ?? ''); ?>"
                                class="form-input"
                                placeholder="e.g., Electronics, Clothing, etc.">
                        </div>

                        <div>
                            <label class="block text-sm text-[#9CA3AF] mb-2">
                                <i class="fas fa-sitemap mr-1"></i>Parent Category
                            </label>
                            <select name="parent_id" id="parentCategory" class="form-input">
                                <option value="">-- None (Top Level) --</option>
                                <?php foreach ($parent_categories as $parent): ?>
                                    <option value="<?php echo (int)($parent['id'] ?? 0); ?>" 
                                        <?php echo ($category['parent_id'] ?? '') == ($parent['id'] ?? '') ? 'selected' : ''; ?>>
                                        <?php echo safe_escape($parent['name'] ?? ''); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <p class="text-xs text-[#6B7280] mt-1">Leave empty for top-level category</p>
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm text-[#9CA3AF] mb-2">
                            <i class="fas fa-align-left mr-1"></i>Description
                        </label>
                        <textarea name="description" id="categoryDescription" rows="3"
                            class="form-input"
                            placeholder="Describe this category..."><?php echo safe_escape($category['description'] ?? ''); ?></textarea>
                    </div>
                </div>

                <!-- Appearance - Sourcing icons from database -->
                <div class="space-y-4">
                    <h3 class="font-medium flex items-center gap-2 border-b border-[#374151] pb-2">
                        <i class="fas fa-palette text-[#FBBF24]"></i>
                        Appearance
                    </h3>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm text-[#9CA3AF] mb-2">
                                <i class="fas fa-paint-brush mr-1"></i>Color
                            </label>
                            <div class="flex gap-2">
                                <input type="color" name="color" id="categoryColor"
                                    value="<?php echo safe_escape($category['color'] ?? '#3B82F6'); ?>"
                                    class="h-11 w-11 rounded-lg border border-[#374151] bg-transparent cursor-pointer">
                                <input type="text" name="color_hex" id="colorHex"
                                    value="<?php echo safe_escape($category['color'] ?? '#3B82F6'); ?>"
                                    class="form-input flex-1"
                                    placeholder="#3B82F6"
                                    pattern="^#[0-9A-Fa-f]{6}$">
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm text-[#9CA3AF] mb-2">
                                <i class="fas fa-icons mr-1"></i>Icon
                            </label>
                            <div class="flex gap-2">
                                <select name="icon" id="categoryIcon" class="form-input flex-1">
                                    <?php foreach ($icons as $icon_option): ?>
                                        <option value="<?php echo safe_escape($icon_option); ?>" 
                                            <?php echo ($category['icon'] ?? 'tag') == $icon_option ? 'selected' : ''; ?>>
                                            <i class="fas fa-<?php echo safe_escape($icon_option); ?>"></i> 
                                            <?php echo ucfirst(safe_escape($icon_option)); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="w-11 h-11 rounded-lg border border-[#374151] flex items-center justify-center"
                                     id="iconPreview" style="background-color: <?php echo safe_escape($category['color'] ?? '#3B82F6'); ?>20;">
                                    <i class="fas fa-<?php echo safe_escape($category['icon'] ?? 'tag'); ?>" 
                                       id="iconPreviewIcon"
                                       style="color: <?php echo safe_escape($category['color'] ?? '#3B82F6'); ?>;"></i>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm text-[#9CA3AF] mb-2">
                            <i class="fas fa-image mr-1"></i>Category Image
                        </label>
                        
                        <!-- Square Image Preview Container -->
                        <div class="image-preview-container">
                            <div id="imagePreviewContainer" class="image-preview <?php echo !empty($category['image']) ? 'has-image' : ''; ?>">
                                <?php if (!empty($category['image'])): ?>
                                    <?php echo displaySquareImage($category['image'], $category['name'] ?? 'Category'); ?>
                                    <button type="button" onclick="removeImage()" class="image-remove-btn" title="Remove image">
                                        <i class="fas fa-times"></i>
                                    </button>
                                <?php else: ?>
                                    <div class="placeholder" id="previewPlaceholder">
                                        <i class="fas fa-cloud-upload-alt"></i>
                                        <p class="text-sm">Click to upload</p>
                                        <p class="text-xs mt-1">Square image (1:1)</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- File Input -->
                        <input type="file" name="image" id="categoryImage" accept="image/*" class="hidden">
                        
                        <div class="flex items-center justify-center gap-3 mt-3">
                            <button type="button" onclick="document.getElementById('categoryImage').click()"
                                class="px-4 py-2 bg-[#3B82F6] hover:bg-[#2563EB] text-white rounded-lg transition text-sm flex items-center gap-2">
                                <i class="fas fa-upload"></i>
                                Choose Image
                            </button>
                            <span id="fileName" class="text-sm text-[#9CA3AF]">No file chosen</span>
                        </div>
                        <p class="text-xs text-[#6B7280] text-center mt-1">Maximum file size: 2MB. Square images work best (1:1 ratio)</p>
                    </div>
                </div>

                <!-- SEO & Settings -->
                <div class="space-y-4">
                    <h3 class="font-medium flex items-center gap-2 border-b border-[#374151] pb-2">
                        <i class="fas fa-cog text-[#FBBF24]"></i>
                        SEO & Settings
                    </h3>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm text-[#9CA3AF] mb-2">
                                <i class="fas fa-heading mr-1"></i>Meta Title
                            </label>
                            <input type="text" name="meta_title" id="metaTitle"
                                value="<?php echo safe_escape($category['meta_title'] ?? ''); ?>"
                                class="form-input"
                                placeholder="SEO title (optional)">
                        </div>

                        <div>
                            <label class="block text-sm text-[#9CA3AF] mb-2">
                                <i class="fas fa-sort-amount-down mr-1"></i>Sort Order
                            </label>
                            <input type="number" name="sort_order" id="sortOrder"
                                value="<?php echo (int) ($category['sort_order'] ?? 0); ?>"
                                min="0" step="1"
                                class="form-input"
                                placeholder="0">
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm text-[#9CA3AF] mb-2">
                            <i class="fas fa-align-left mr-1"></i>Meta Description
                        </label>
                        <textarea name="meta_description" id="metaDescription" rows="2"
                            class="form-input"
                            placeholder="SEO description (optional)"><?php echo safe_escape($category['meta_description'] ?? ''); ?></textarea>
                    </div>

                    <div>
                        <label class="block text-sm text-[#9CA3AF] mb-2">
                            <i class="fas fa-circle mr-1"></i>Status
                        </label>
                        <div class="flex items-center gap-4">
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="radio" name="status" value="active" 
                                    <?php echo ($category['status'] ?? 'active') == 'active' ? 'checked' : ''; ?>
                                    class="w-4 h-4 accent-[#FBBF24]">
                                <span class="text-sm">Active</span>
                            </label>
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="radio" name="status" value="inactive"
                                    <?php echo ($category['status'] ?? '') == 'inactive' ? 'checked' : ''; ?>
                                    class="w-4 h-4 accent-[#FBBF24]">
                                <span class="text-sm">Inactive</span>
                            </label>
                        </div>
                    </div>
                </div>

                <!-- Form Actions -->
                <div class="flex items-center justify-end gap-3 pt-4 border-t border-[#374151]">
                    <a href="list_categories.php"
                        class="px-6 py-3 bg-[#1F2937] border border-[#374151] text-white rounded-xl font-semibold hover:border-[#FBBF24] transition flex items-center gap-2">
                        <i class="fas fa-times"></i>
                        Cancel
                    </a>
                    <button type="submit" id="submitBtn"
                        class="px-6 py-3 bg-[#FBBF24] text-[#1E3A8A] rounded-xl font-semibold hover:bg-[#F59E0B] transition flex items-center gap-2">
                        <i class="fas fa-save"></i>
                        <span>Update Category</span>
                    </button>
                </div>
            </form>
        <?php endif; ?>
    </div>

    <!-- Toast Container -->
    <div id="toastContainer" class="toast-container"></div>

    <style>
        .gradient-text {
            background: linear-gradient(135deg, #FBBF24 0%, #F59E0B 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .form-input {
            background: #111827;
            border: 1px solid #374151;
            border-radius: 0.75rem;
            padding: 0.75rem 1rem;
            color: white;
            width: 100%;
            transition: all 0.2s;
        }

        .form-input:focus {
            border-color: #FBBF24;
            box-shadow: 0 0 0 3px rgba(251, 191, 36, 0.2);
            outline: none;
        }

        .form-input::placeholder {
            color: #6B7280;
        }

        select.form-input option {
            background: #1F2937;
            color: white;
        }

        .image-preview-container {
            position: relative;
            width: 200px;
            height: 200px;
            margin: 0 auto;
        }

        .image-preview {
            width: 100%;
            height: 100%;
            border: 2px dashed #374151;
            border-radius: 0.75rem;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            transition: all 0.3s ease;
        }

        .image-preview.has-image {
            border-style: solid;
            border-color: #FBBF24;
        }

        .image-preview .placeholder {
            text-align: center;
            color: #6B7280;
        }

        .image-preview .placeholder i {
            font-size: 3rem;
            margin-bottom: 0.5rem;
            display: block;
        }

        .image-preview img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 0.5rem;
        }

        .image-remove-btn {
            position: absolute;
            top: 0.5rem;
            right: 0.5rem;
            background: rgba(239, 68, 68, 0.9);
            color: white;
            border: none;
            border-radius: 50%;
            width: 2rem;
            height: 2rem;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.2s;
        }

        .image-remove-btn:hover {
            background: #EF4444;
            transform: scale(1.1);
        }

        .toast-container {
            position: fixed;
            top: 1rem;
            right: 1rem;
            z-index: 9999;
        }

        .toast {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.875rem 1.25rem;
            border-radius: 0.75rem;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.3);
            animation: slideInRight 0.3s ease-out;
            min-width: 280px;
            max-width: 400px;
            margin-bottom: 0.5rem;
        }

        .toast.success {
            background: linear-gradient(135deg, rgba(16, 185, 129, 0.15), rgba(16, 185, 129, 0.05));
            border: 1px solid rgba(16, 185, 129, 0.3);
            color: #10B981;
        }

        .toast.error {
            background: linear-gradient(135deg, rgba(239, 68, 68, 0.15), rgba(239, 68, 68, 0.05));
            border: 1px solid rgba(239, 68, 68, 0.3);
            color: #EF4444;
        }

        .toast.info {
            background: linear-gradient(135deg, rgba(59, 130, 246, 0.15), rgba(59, 130, 246, 0.05));
            border: 1px solid rgba(59, 130, 246, 0.3);
            color: #60a5fa;
        }

        .spinner {
            border: 2px solid rgba(255, 255, 255, 0.3);
            border-radius: 50%;
            border-top: 2px solid #fff;
            width: 1rem;
            height: 1rem;
            animation: spin 1s linear infinite;
        }

        @keyframes slideInRight {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        . {
            animation: fadeIn 0.5s ease-out;
        }

        . {
            animation: slideUp 0.3s ease-out;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
            padding: 0.25rem 0.5rem;
            border-radius: 0.375rem;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .status-active {
            background: rgba(16, 185, 129, 0.1);
            color: #10B981;
            border: 1px solid rgba(16, 185, 129, 0.2);
        }

        .status-inactive {
            background: rgba(239, 68, 68, 0.1);
            color: #EF4444;
            border: 1px solid rgba(239, 68, 68, 0.2);
        }
    </style>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // DOM Elements - support both add and edit forms
            const addForm = document.getElementById('addCategoryForm');
            const editForm = document.getElementById('editCategoryForm');
            const form = addForm || editForm;
            if (!form) return;

            const submitBtn = document.getElementById('submitBtn');
            const colorInput = document.getElementById('categoryColor');
            const colorHex = document.getElementById('colorHex');
            const iconSelect = document.getElementById('categoryIcon');
            const iconPreview = document.getElementById('iconPreviewIcon');
            const iconPreviewContainer = document.getElementById('iconPreview');
            const imageInput = document.getElementById('categoryImage');
            const fileName = document.getElementById('fileName');
            const previewContainer = document.getElementById('imagePreviewContainer');
            const removeImageInput = document.getElementById('remove_image');

            function updateIconColor() {
                if (colorInput && iconPreview) {
                    const color = colorInput.value;
                    iconPreview.style.color = color;
                    if (iconPreviewContainer) {
                        iconPreviewContainer.style.backgroundColor = color + '20';
                    }
                }
            }

            // Color picker sync
            if (colorInput && colorHex) {
                colorInput.addEventListener('input', function() {
                    colorHex.value = this.value;
                    updateIconColor();
                });

                colorHex.addEventListener('input', function() {
                    if (/^#[0-9A-F]{6}$/i.test(this.value)) {
                        colorInput.value = this.value;
                        updateIconColor();
                    }
                });
            }

            // Icon preview
            if (iconSelect && iconPreview) {
                iconSelect.addEventListener('change', function() {
                    const icon = this.value;
                    iconPreview.className = 'fas fa-' + icon;
                    updateIconColor();
                });
            }

            // Image upload preview
            if (imageInput && previewContainer) {
                imageInput.addEventListener('change', function(e) {
                    if (this.files && this.files[0]) {
                        const file = this.files[0];
                        
                        if (file.size > 2 * 1024 * 1024) {
                            showToast('File size must be less than 2MB', 'error');
                            this.value = '';
                            return;
                        }
                        
                        const validTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml'];
                        if (!validTypes.includes(file.type)) {
                            showToast('Invalid file type. Only JPG, PNG, GIF, WEBP, and SVG files are allowed.', 'error');
                            this.value = '';
                            return;
                        }
                        
                        if (fileName) fileName.textContent = file.name;

                        const reader = new FileReader();
                        reader.onload = function(e) {
                            previewContainer.innerHTML = '';
                            
                            const img = document.createElement('img');
                            img.src = e.target.result;
                            img.alt = 'Preview';
                            img.className = 'w-full h-full object-cover';
                            
                            const removeBtn = document.createElement('button');
                            removeBtn.type = 'button';
                            removeBtn.className = 'image-remove-btn';
                            removeBtn.innerHTML = '<i class="fas fa-times"></i>';
                            removeBtn.onclick = removeImage;
                            
                            previewContainer.appendChild(img);
                            previewContainer.appendChild(removeBtn);
                            previewContainer.classList.add('has-image');
                            
                            if (removeImageInput) removeImageInput.value = '0';
                        };
                        reader.readAsDataURL(file);
                    }
                });
            }

            // Remove image
            function removeImage() {
                if (confirm('Are you sure you want to remove this image?')) {
                    if (imageInput) imageInput.value = '';
                    if (fileName) fileName.textContent = 'No file chosen';
                    
                    if (previewContainer) {
                        previewContainer.innerHTML = '<div class="placeholder"><i class="fas fa-cloud-upload-alt text-3xl mb-2"></i><p class="text-sm">Click to upload</p><p class="text-xs mt-1">Square image (1:1)</p></div>';
                        previewContainer.classList.remove('has-image');
                    }
                    
                    if (removeImageInput) removeImageInput.value = '1';
                }
            }

            // Form submission with AJAX
            form.addEventListener('submit', function(e) {
                e.preventDefault();

                const nameField = document.getElementById('categoryName');
                const name = nameField ? nameField.value.trim() : '';
                if (!name) {
                    showToast('Category name is required', 'error');
                    if (nameField) nameField.focus();
                    return;
                }

                if (!submitBtn) return;
                const originalText = submitBtn.innerHTML;
                submitBtn.innerHTML = '<span class="spinner mr-2"></span> Saving...';
                submitBtn.disabled = true;

                const formData = new FormData(form);

                fetch('add_category.php', {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showToast(data.message, 'success');
                        setTimeout(function() {
                            window.location.href = data.redirect;
                        }, 1500);
                    } else {
                        showToast(data.message, 'error');
                        submitBtn.innerHTML = originalText;
                        submitBtn.disabled = false;
                    }
                })
                .catch(function(error) {
                    console.error('Error:', error);
                    showToast('An error occurred while saving the category', 'error');
                    submitBtn.innerHTML = originalText;
                    submitBtn.disabled = false;
                });
            });

            // Toast notification
            function showToast(message, type) {
                type = type || 'success';
                const container = document.getElementById('toastContainer');
                if (!container) return;
                
                const toast = document.createElement('div');
                
                var icons = {
                    success: 'fa-check-circle',
                    error: 'fa-exclamation-circle',
                    warning: 'fa-exclamation-triangle',
                    info: 'fa-info-circle'
                };
                
                toast.className = 'toast ' + type;
                toast.innerHTML = '<i class="fas ' + icons[type] + '"></i><span>' + message + '</span>';
                
                container.appendChild(toast);
                
                setTimeout(function() {
                    toast.style.opacity = '0';
                    toast.style.transition = 'opacity 0.3s ease';
                    setTimeout(function() { toast.remove(); }, 300);
                }, 3000);
            }

            // Connection status
            function updateOnlineStatus() {
                const statusEl = document.getElementById('connection-status');
                if (statusEl) {
                    if (navigator.onLine) {
                        statusEl.innerHTML = '<div class="w-2 h-2 bg-[#10B981] rounded-full animate-pulse"></div><span class="text-[#10B981]"><i class="fas fa-wifi mr-1"></i>Online</span>';
                    } else {
                        statusEl.innerHTML = '<div class="w-2 h-2 bg-[#EF4444] rounded-full animate-pulse"></div><span class="text-[#EF4444]"><i class="fas fa-wifi-slash mr-1"></i>Offline</span>';
                    }
                }
            }

            if (window.addEventListener) {
                window.addEventListener('online', updateOnlineStatus);
                window.addEventListener('offline', updateOnlineStatus);
            }
            updateOnlineStatus();

            // Keyboard shortcuts
            document.addEventListener('keydown', function(e) {
                if (e.target.matches('input, textarea, select')) {
                    return;
                }

                if (e.ctrlKey && e.key === 's') {
                    e.preventDefault();
                    form.dispatchEvent(new Event('submit'));
                }

                if (e.key === 'Escape') {
                    if (confirm('Are you sure you want to leave? Any unsaved changes will be lost.')) {
                        window.location.href = 'list_categories.php';
                    }
                }
            });

            // Initialize icon and color
            updateIconColor();
        });
    </script>
</div>

<?php
$page_content = ob_get_clean();
require_once __DIR__ . '/../layouts/app_close.php';
require_once __DIR__ . '/../layouts/app.php';
?>