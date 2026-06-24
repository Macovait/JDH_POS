<?php
/**
 * Edit Category AJAX Handler
 * Separate file for AJAX requests to avoid any HTML output
 */

// Error handling
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

require_once __DIR__ . '/../../src/auth.php';
require_once __DIR__ . '/../../src/db.php';
require_once __DIR__ . '/../../src/functions.php';
require_once __DIR__ . '/../../src/logger.php';

header('Content-Type: application/json');

$response = ['success' => false, 'message' => ''];

try {
    $user_id = get_current_user_id();
    $tenant_id = get_current_tenant_id();
    $current_branch_id = get_current_branch_id();

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

    // Validation
    if (empty($name)) {
        throw new Exception('Category name is required');
    }

    if (empty($id)) {
        throw new Exception('Category ID is required');
    }

    // Check if category name already exists
    $check = $pdo->prepare("SELECT id FROM categories WHERE name = ? AND id != ? AND tenant_id = ? AND deleted_at IS NULL");
    $check->execute([$name, $id, $tenant_id]);
    if ($check->fetch()) {
        throw new Exception('Category name already exists');
    }

    // Handle image upload
    $image_path = null;
    $stmt_img = $pdo->prepare("SELECT image FROM categories WHERE id = ?");
    $stmt_img->execute([$id]);
    $existing = $stmt_img->fetch();
    $image_path = $existing['image'] ?? null;

    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = $_SERVER['DOCUMENT_ROOT'] . '/JDH_POS/public/uploads/categories/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        $file_name = uniqid() . '_' . basename($_FILES['image']['name']);
        $target_file = $upload_dir . $file_name;
        if (move_uploaded_file($_FILES['image']['tmp_name'], $target_file)) {
            if ($image_path && file_exists($_SERVER['DOCUMENT_ROOT'] . $image_path)) {
                unlink($_SERVER['DOCUMENT_ROOT'] . $image_path);
            }
            $image_path = '/JDH_POS/public/uploads/categories/' . $file_name;
        }
    } elseif (isset($_POST['remove_image']) && $_POST['remove_image'] == '1') {
        if ($image_path && file_exists($_SERVER['DOCUMENT_ROOT'] . $image_path)) {
            unlink($_SERVER['DOCUMENT_ROOT'] . $image_path);
        }
        $image_path = null;
    }

    // Update category
    $stmt = $pdo->prepare("
        UPDATE categories
        SET name = ?, parent_id = ?, description = ?, color = ?, icon = ?,
            image = ?, meta_title = ?, meta_description = ?, sort_order = ?,
            status = ?, updated_by = ?, updated_at = NOW()
        WHERE id = ? AND tenant_id = ?
    ");
    $stmt->execute([
        $name, $parent_id, $description, $color, $icon,
        $image_path, $meta_title, $meta_description, $sort_order,
        $status, $user_id, $id, $tenant_id
    ]);

    if (function_exists('log_activity')) {
        log_activity($user_id, 'category_updated', [
            'category_id' => $id, 'category_name' => $name
        ], $tenant_id);
    }

    $pdo->commit();

    $response = [
        'success' => true,
        'message' => 'Category updated successfully',
        'redirect' => 'list_categories.php?message=' . urlencode('Category updated successfully')
    ];

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Category update error: ' . $e->getMessage());
    $response = ['success' => false, 'message' => $e->getMessage()];
}

echo json_encode($response);
