<?php
/**
 * Advanced Barcode Label API
 * 
 * Endpoints for batch operations, template management, and export
 * 
 * @package Jakababa
 * @subpackage Labels
 * @version 1.0
 */

require_once __DIR__ . '/../../src/paths.php';
safe_require('auth.php', 'src', true);

// Branch filter for multi-tenant isolation
$current_branch_id = get_current_branch_id();
safe_require('db.php', 'src', true);
safe_require('functions.php', 'src', true);
require_login();

if (!check_permission('products.view') && !is_super_admin()) {
    http_response_code(403);
    echo json_encode(['error' => 'Permission denied']);
    exit;
}

$pdo = get_db_connection();
$tenant_id = get_current_tenant_id();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

try {
    $data = json_decode(file_get_contents('php://input'), true);

    // CSRF verification
    $csrf_token = $_SESSION['csrf_token'] ?? '';
    if (!isset($data['csrf_token']) || $data['csrf_token'] !== $csrf_token) {
        http_response_code(403);
        echo json_encode(['error' => 'Invalid CSRF token']);
        exit;
    }
    $action = $data['action'] ?? '';

    switch ($action) {
        case 'get_categories':
            // Get all product categories for filter
            $stmt = $pdo->prepare("
                SELECT id, name, COUNT(p.id) as product_count
                FROM categories c
                LEFT JOIN products p ON p.category_id = c.id AND p.tenant_id = c.tenant_id
                WHERE c.tenant_id = ? AND c.deleted_at IS NULL
                GROUP BY c.id, c.name
                ORDER BY c.name
            ");
            $stmt->execute([$tenant_id]);
            echo json_encode([
                'success' => true,
                'categories' => $stmt->fetchAll(PDO::FETCH_ASSOC)
            ]);
            break;

        case 'get_label_templates':
            // Get saved label templates
            $stmt = $pdo->prepare("
                SELECT id, name, label_width, label_height, 
                       show_name, show_price, show_sku, show_category, 
                       data
                FROM label_templates
                WHERE tenant_id = ? AND deleted_at IS NULL
                ORDER BY name
            ");
            $stmt->execute([$tenant_id]);
            echo json_encode([
                'success' => true,
                'templates' => $stmt->fetchAll(PDO::FETCH_ASSOC)
            ]);
            break;

        case 'save_template':
            // Save a label template
            $name = $data['name'] ?? '';
            $config = $data['config'] ?? [];

            if (!$name) {
                throw new Exception('Template name is required');
            }

            $stmt = $pdo->prepare("
                INSERT INTO label_templates 
                (tenant_id, name, label_width, label_height, show_name, show_price, show_sku, show_category, data, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");

            $stmt->execute([
                $tenant_id,
                $name,
                $config['labelWidth'] ?? 40,
                $config['labelHeight'] ?? 20,
                $config['showProductName'] ? 1 : 0,
                $config['showPrice'] ? 1 : 0,
                $config['showSKU'] ? 1 : 0,
                $config['showCategory'] ? 1 : 0,
                json_encode($config)
            ]);

            echo json_encode([
                'success' => true,
                'message' => 'Template saved successfully',
                'template_id' => $pdo->lastInsertId()
            ]);
            break;

        case 'delete_template':
            // Delete a label template
            $template_id = (int)($data['template_id'] ?? 0);

            if (!$template_id) {
                throw new Exception('Template ID is required');
            }

            // SECURITY: Verify template belongs to current company before deleting
            $verify_stmt = $pdo->prepare("SELECT id FROM label_templates WHERE id = ? AND tenant_id = ?");
            $verify_stmt->execute([$template_id, $tenant_id]);
            
            if (!$verify_stmt->fetch()) {
                throw new Exception('Template not found or access denied');
            }

            $stmt = $pdo->prepare("
                UPDATE label_templates 
                SET deleted_at = NOW() 
                WHERE id = ? AND tenant_id = ?
            ");
            $stmt->execute([$template_id, $tenant_id]);

            echo json_encode([
                'success' => true,
                'message' => 'Template deleted successfully'
            ]);
            break;

        case 'generate_bulk_html':
            // Generate HTML for bulk label printing
            $product_ids = $data['products'] ?? [];
            $quantities = $data['quantities'] ?? [];
            $config = $data['config'] ?? [];

            if (empty($product_ids)) {
                throw new Exception('No products selected');
            }

            // Validate all product IDs are integers (prevent SQL injection)
            $product_ids = array_map('intval', $product_ids);
            
            // SECURITY: Verify all products belong to current company
            $count = count($product_ids);
            $placeholders = implode(',', array_fill(0, $count, '?'));
            
            $verify_stmt = $pdo->prepare("SELECT COUNT(*) FROM products WHERE id IN ($placeholders) AND tenant_id = ? AND deleted_at IS NULL");
            $verify_params = array_merge($product_ids, [$tenant_id]);
            $verify_stmt->execute($verify_params);
            
            if ($verify_stmt->fetchColumn() !== $count) {
                throw new Exception('Access denied: Some products do not belong to your tenant');
            }

            // Fetch products - security verified above
            $stmt = $pdo->prepare("
                SELECT p.id, p.name, p.sku, p.price, c.name as category_name
                FROM products p
                LEFT JOIN categories c ON p.category_id = c.id
                WHERE p.id IN ($placeholders) AND p.tenant_id = ? AND p.active = 1 AND p.deleted_at IS NULL
            ");
            $stmt->execute(array_merge($product_ids, [$tenant_id]));
            $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode([
                'success' => true,
                'products' => $products,
                'config' => $config
            ]);
            break;

        case 'get_barcode_formats':
            // Return supported barcode formats
            echo json_encode([
                'success' => true,
                'formats' => [
                    ['code' => 'CODE128', 'name' => 'Code 128', 'recommended' => true],
                    ['code' => 'CODE39', 'name' => 'Code 39'],
                    ['code' => 'EAN13', 'name' => 'EAN-13'],
                    ['code' => 'EAN8', 'name' => 'EAN-8'],
                    ['code' => 'UPCA', 'name' => 'UPC-A'],
                    ['code' => 'ITF14', 'name' => 'ITF-14']
                ]
            ]);
            break;

        case 'get_printer_profiles':
            // Return printer profiles and settings
            echo json_encode([
                'success' => true,
                'profiles' => [
                    [
                        'id' => 'thermal_80mm',
                        'name' => 'Thermal Printer (80mm)',
                        'width' => 80,
                        'cols' => 1,
                        'recommended' => true
                    ],
                    [
                        'id' => 'thermal_thermal_100mm',
                        'name' => 'Thermal Printer (100mm)',
                        'width' => 100,
                        'cols' => 1
                    ],
                    [
                        'id' => 'a4_labels',
                        'name' => 'A4 Sheet Labels (2x5)',
                        'width' => 210,
                        'cols' => 2
                    ],
                    [
                        'id' => 'a4_labels_3x8',
                        'name' => 'A4 Sheet Labels (3x8)',
                        'width' => 210,
                        'cols' => 3
                    ]
                ]
            ]);
            break;

        case 'batch_update_prices':
            // Batch update product prices for labels
            $updates = $data['updates'] ?? [];

            if (empty($updates)) {
                throw new Exception('No price updates provided');
            }

            // SECURITY: Verify all products belong to current company before updating
            $product_ids = array_map('intval', array_column($updates, 'product_id'));
            $count = count($product_ids);
            $placeholders = implode(',', array_fill(0, $count, '?'));
            
            $verify_stmt = $pdo->prepare("SELECT COUNT(*) FROM products WHERE id IN ($placeholders) AND tenant_id = ? AND deleted_at IS NULL");
            $verify_params = array_merge($product_ids, [$tenant_id]);
            $verify_stmt->execute($verify_params);
            
            if ($verify_stmt->fetchColumn() !== $count) {
                throw new Exception('Access denied: Some products do not belong to your tenant');
            }

            $stmt = $pdo->prepare("UPDATE products SET price = ?, updated_at = NOW() WHERE id = ? AND tenant_id = ? AND deleted_at IS NULL");

            foreach ($updates as $update) {
                $stmt->execute([
                    $update['price'],
                    $update['product_id'],
                    $tenant_id
                ]);
            }

            echo json_encode([
                'success' => true,
                'message' => count($updates) . ' prices updated'
            ]);
            break;

        default:
            throw new Exception('Unknown action: ' . $action);
    }

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
