<?php
/**
 * Product Attributes Module
 * Laravel-inspired structure with Tailwind CSS
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

function log_activity(int $user_id, string $action, array $data, int $tenant_id): void {
    $pdo = get_db_connection();
    try {
        $stmt = $pdo->prepare("INSERT INTO activity_logs (user_id, action, description, tenant_id, created_at) VALUES (?, ?, ?, ?, NOW())");
        $stmt->execute([$user_id, $action, json_encode($data), $tenant_id]);
    } catch (Exception $e) {
        error_log("Failed to log activity: " . $e->getMessage());
    }
}

// Permissions
$can_view = check_permission('attributes.view') || check_permission('products.manage') || is_super_admin();
$can_create = check_permission('attributes.create') || check_permission('products.manage') || is_super_admin();
$can_edit = check_permission('attributes.edit') || check_permission('products.manage') || is_super_admin();
$can_delete = check_permission('attributes.delete') || check_permission('products.manage') || is_super_admin();

if (!$can_view) {
    enforce_permission('attributes.view');
}

// ============================================
// AJAX Router
// ============================================
$action = $_GET['action'] ?? ($_POST['action'] ?? '');
$is_ajax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
        || (!empty($_GET['ajax']) && $_GET['ajax'] === '1')
        || (!empty($_POST['ajax']) && $_POST['ajax'] === '1');
if ($action && $is_ajax) {
    header('Content-Type: application/json');
    $response = ['success' => false, 'error' => 'Unknown action'];

    switch ($action) {
        case 'list':
            $response = ajax_list($pdo, $tenant_id);
            break;
        case 'get':
            $response = ajax_get($pdo, $tenant_id);
            break;
        case 'save':
            if (!$can_create && !$can_edit) {
                $response = ['success' => false, 'error' => 'Permission denied'];
            } else {
                if (!verify_csrf_token($_POST['csrf_token'] ?? '')) { $response = ['success' => false, 'error' => 'Invalid CSRF token']; break; }
                $response = ajax_save($pdo, $tenant_id, $user_id);
            }
            break;
        case 'delete':
            if (!$can_delete) {
                $response = ['success' => false, 'error' => 'Permission denied'];
            } else {
                if (!verify_csrf_token($_POST['csrf_token'] ?? '')) { $response = ['success' => false, 'error' => 'Invalid CSRF token']; break; }
                $response = ajax_delete($pdo, $tenant_id, $user_id);
            }
            break;
        case 'toggle_status':
            if (!$can_edit) {
                $response = ['success' => false, 'error' => 'Permission denied'];
            } else {
                if (!verify_csrf_token($_POST['csrf_token'] ?? '')) { $response = ['success' => false, 'error' => 'Invalid CSRF token']; break; }
                $response = ajax_toggle_status($pdo, $tenant_id, $user_id);
            }
            break;
        case 'reorder':
            if (!$can_edit) {
                $response = ['success' => false, 'error' => 'Permission denied'];
            } else {
                if (!verify_csrf_token($_POST['csrf_token'] ?? '')) { $response = ['success' => false, 'error' => 'Invalid CSRF token']; break; }
                $response = ajax_reorder($pdo, $tenant_id, $user_id);
            }
            break;
        case 'check_name':
            $response = ajax_check_name($pdo, $tenant_id);
            break;
        case 'check_code':
            $response = ajax_check_code($pdo, $tenant_id);
            break;
        case 'export':
            $format = $_GET['format'] ?? 'csv';
            $stmt = $pdo->prepare("SELECT a.name, a.code, a.type, ag.name as group_name, a.unit, a.status, a.is_variant_forming, a.is_filterable, a.is_required FROM attributes a LEFT JOIN attribute_groups ag ON ag.id = a.group_id WHERE a.tenant_id = :tenant_id AND a.deleted_at IS NULL ORDER BY a.sort_order, a.name");
            $stmt->execute([':tenant_id' => $tenant_id]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="attributes_' . date('Y-m-d') . '.csv"');
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Name', 'Code', 'Type', 'Group', 'Unit', 'Status', 'Variant Forming', 'Filterable', 'Required']);
            foreach ($rows as $row) {
                fputcsv($out, [
                    $row['name'], $row['code'], $row['type'], $row['group_name'], $row['unit'],
                    $row['status'] ? 'Active' : 'Inactive',
                    $row['is_variant_forming'] ? 'Yes' : 'No',
                    $row['is_filterable'] ? 'Yes' : 'No',
                    $row['is_required'] ? 'Yes' : 'No'
                ]);
            }
            fclose($out);
            exit;
        case 'save_group':
            if (!$can_create && !$can_edit) {
                $response = ['success' => false, 'error' => 'Permission denied'];
            } else {
                if (!verify_csrf_token($_POST['csrf_token'] ?? '')) { $response = ['success' => false, 'error' => 'Invalid CSRF token']; break; }
                $response = ajax_save_group($pdo, $tenant_id, $user_id);
            }
            break;
        case 'delete_group':
            if (!$can_delete) {
                $response = ['success' => false, 'error' => 'Permission denied'];
            } else {
                if (!verify_csrf_token($_POST['csrf_token'] ?? '')) { $response = ['success' => false, 'error' => 'Invalid CSRF token']; break; }
                $response = ajax_delete_group($pdo, $tenant_id, $user_id);
            }
            break;
        case 'stats':
            $response = ajax_stats($pdo, $tenant_id);
            break;
        case 'bulk_status':
            if (!$can_edit) {
                $response = ['success' => false, 'error' => 'Permission denied'];
            } else {
                if (!verify_csrf_token($_POST['csrf_token'] ?? '')) { $response = ['success' => false, 'error' => 'Invalid CSRF token']; break; }
                $response = ajax_bulk_status($pdo, $tenant_id, $user_id);
            }
            break;
        case 'bulk_delete':
            if (!$can_delete) {
                $response = ['success' => false, 'error' => 'Permission denied'];
            } else {
                if (!verify_csrf_token($_POST['csrf_token'] ?? '')) { $response = ['success' => false, 'error' => 'Invalid CSRF token']; break; }
                $response = ajax_bulk_delete($pdo, $tenant_id, $user_id);
            }
            break;
    }

    echo json_encode($response);
    exit;
}

// ============================================
// AJAX Handlers
// ============================================

function ajax_list(PDO $pdo, int $tenant_id): array {
    $search = trim($_GET['search'] ?? '');
    $group_id = $_GET['group_id'] ?? '';
    $type = $_GET['type'] ?? '';
    $status = $_GET['status'] ?? '';
    $page = max(1, intval($_GET['page'] ?? 1));
    $per_page = max(1, min(100, intval($_GET['per_page'] ?? 20)));
    $offset = ($page - 1) * $per_page;

    $sort = $_GET['sort'] ?? 'sort_order_asc';
    $allowed_sort_cols = ['sort_order' => 'a.sort_order', 'name' => 'a.name', 'code' => 'a.code', 'type' => 'a.type', 'group_name' => 'ag.name', 'product_count' => 'product_count', 'status' => 'a.status', 'updated' => 'a.updated_at', 'id' => 'a.id'];
    $sort_parts = explode('_', $sort, 2);
    $sort_col = $allowed_sort_cols[$sort_parts[0] ?? 'sort_order'] ?? 'a.sort_order';
    $sort_dir = (isset($sort_parts[1]) && strtolower($sort_parts[1]) === 'desc') ? 'DESC' : 'ASC';
    $order_by = "ORDER BY $sort_col $sort_dir, a.id ASC";

    $where = 'WHERE a.tenant_id = :tenant_id AND a.deleted_at IS NULL';
    $params = [':tenant_id' => $tenant_id];

    if ($search !== '') {
        $where .= ' AND (a.name LIKE :search OR a.code LIKE :search)';
        $params[':search'] = '%' . $search . '%';
    }
    if ($group_id !== '' && is_numeric($group_id)) {
        $where .= ' AND a.group_id = :group_id';
        $params[':group_id'] = (int) $group_id;
    }
    if ($type !== '') {
        $where .= ' AND a.type = :type';
        $params[':type'] = $type;
    }
    if ($status !== '') {
        $where .= ' AND a.status = :status';
        $params[':status'] = (int) $status;
    }

    try {
        $count_sql = "SELECT COUNT(*) FROM attributes a $where";
        $count_stmt = $pdo->prepare($count_sql);
        $count_stmt->execute($params);
        $total = (int) $count_stmt->fetchColumn();

        $sql = "SELECT a.*, ag.name as group_name,
                (SELECT COUNT(*) FROM attribute_values av WHERE av.attribute_id = a.id AND av.tenant_id = :t1) as values_count,
                (SELECT COUNT(DISTINCT pav.product_id) FROM product_attribute_values pav WHERE pav.attribute_id = a.id AND pav.tenant_id = :t2) as product_count
                FROM attributes a
                LEFT JOIN attribute_groups ag ON ag.id = a.group_id AND ag.tenant_id = :t3
                $where
                $order_by
                LIMIT :limit OFFSET :offset";

        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':t1', $tenant_id, PDO::PARAM_INT);
        $stmt->bindValue(':t2', $tenant_id, PDO::PARAM_INT);
        $stmt->bindValue(':t3', $tenant_id, PDO::PARAM_INT);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $stmt->bindValue(':limit', $per_page, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $ids = array_column($rows, 'id');
        if (!empty($ids)) {
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $vstmt = $pdo->prepare("SELECT attribute_id, value, label FROM attribute_values WHERE attribute_id IN ($placeholders) AND tenant_id = ? ORDER BY sort_order, id");
            $vparams = array_merge($ids, [$tenant_id]);
            $vstmt->execute($vparams);
            $all_values = $vstmt->fetchAll(PDO::FETCH_ASSOC);
            $grouped = [];
            foreach ($all_values as $v) {
                $grouped[$v['attribute_id']][] = $v['label'] ?: $v['value'];
            }
            foreach ($rows as &$row) {
                $vals = $grouped[$row['id']] ?? [];
                $row['values_preview'] = implode(', ', array_slice($vals, 0, 5));
                $row['values_count'] = count($vals);
            }
            unset($row);
        }

        return ['success' => true, 'data' => ['rows' => $rows, 'total' => $total, 'page' => $page, 'per_page' => $per_page, 'total_pages' => (int) ceil($total / $per_page)]];
    } catch (PDOException $e) {
        error_log("ajax_list error: " . $e->getMessage());
        return ['success' => false, 'error' => 'Database error'];
    }
}

function ajax_get(PDO $pdo, int $tenant_id): array {
    $id = intval($_GET['id'] ?? 0);
    if (!$id) return ['success' => false, 'error' => 'Missing ID'];

    try {
        $stmt = $pdo->prepare("SELECT a.*, ag.name as group_name FROM attributes a LEFT JOIN attribute_groups ag ON ag.id = a.group_id WHERE a.id = :id AND a.tenant_id = :tenant_id AND a.deleted_at IS NULL");
        $stmt->execute([':id' => $id, ':tenant_id' => $tenant_id]);
        $attr = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$attr) return ['success' => false, 'error' => 'Not found'];

        $vstmt = $pdo->prepare("SELECT * FROM attribute_values WHERE attribute_id = :id AND tenant_id = :tenant_id ORDER BY sort_order, id");
        $vstmt->execute([':id' => $id, ':tenant_id' => $tenant_id]);
        $values = $vstmt->fetchAll(PDO::FETCH_ASSOC);
        $attr['values'] = $values;
        $attr['values_count'] = count($values);
        $attr['values_preview'] = implode(', ', array_slice(array_map(fn($v) => $v['label'] ?: $v['value'], $values), 0, 5));

        $pcstmt = $pdo->prepare("SELECT COUNT(DISTINCT product_id) FROM product_attribute_values WHERE attribute_id = :id AND tenant_id = :tenant_id");
        $pcstmt->execute([':id' => $id, ':tenant_id' => $tenant_id]);
        $attr['product_count'] = (int) $pcstmt->fetchColumn();

        return ['success' => true, 'data' => $attr];
    } catch (PDOException $e) {
        error_log("ajax_get error: " . $e->getMessage());
        return ['success' => false, 'error' => 'Database error'];
    }
}

function ajax_save(PDO $pdo, int $tenant_id, int $user_id): array {
    $id = intval($_POST['id'] ?? 0);
    $name = trim($_POST['name'] ?? '');
    $code = trim($_POST['code'] ?? '');
    $type = $_POST['type'] ?? 'text';
    $unit = trim($_POST['unit'] ?? '');
    $group_id = $_POST['group_id'] !== '' ? (int) $_POST['group_id'] : null;
    $is_required = isset($_POST['is_required']) ? 1 : 0;
    $is_filterable = isset($_POST['is_filterable']) ? 1 : 0;
    $is_variant_forming = isset($_POST['is_variant_forming']) ? 1 : 0;
    $business_type_id = $_POST['business_type_id'] !== '' ? (int) $_POST['business_type_id'] : null;
    $sort_order = intval($_POST['sort_order'] ?? 0);
    $status = isset($_POST['status']) ? 1 : 0;

    if ($name === '' || $code === '') {
        return ['success' => false, 'error' => 'Name and code are required'];
    }

    $allowed_types = ['text', 'textarea', 'dropdown', 'multiselect', 'number', 'color', 'file', 'date', 'boolean'];
    if (!in_array($type, $allowed_types, true)) {
        return ['success' => false, 'error' => 'Invalid attribute type'];
    }

    try {
        $check = $pdo->prepare("SELECT id FROM attributes WHERE tenant_id = :tenant_id AND name = :name AND deleted_at IS NULL" . ($id ? " AND id != :id" : ""));
        $cp = [':tenant_id' => $tenant_id, ':name' => $name];
        if ($id) $cp[':id'] = $id;
        $check->execute($cp);
        if ($check->fetch()) {
            return ['success' => false, 'error' => 'An attribute with this name already exists'];
        }

        $check2 = $pdo->prepare("SELECT id FROM attributes WHERE tenant_id = :tenant_id AND code = :code AND deleted_at IS NULL" . ($id ? " AND id != :id" : ""));
        $cp2 = [':tenant_id' => $tenant_id, ':code' => $code];
        if ($id) $cp2[':id'] = $id;
        $check2->execute($cp2);
        if ($check2->fetch()) {
            return ['success' => false, 'error' => 'An attribute with this code already exists'];
        }

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
            ':business_type_id' => $business_type_id,
            ':sort_order' => $sort_order,
            ':status' => $status,
            ':updated_by' => $user_id,
        ];

        if ($id) {
            $stmt = $pdo->prepare("UPDATE attributes SET name = :name, code = :code, type = :type, unit = :unit, group_id = :group_id, is_required = :is_required, is_filterable = :is_filterable, is_variant_forming = :is_variant_forming, business_type_id = :business_type_id, sort_order = :sort_order, status = :status, updated_by = :updated_by, updated_at = NOW() WHERE id = :id AND tenant_id = :tenant_id");
            $data[':id'] = $id;
            $stmt->execute($data);
            $attr_id = $id;
            log_activity($user_id, 'attribute_update', ['attribute_id' => $id, 'name' => $name], $tenant_id);
        } else {
            $data[':created_by'] = $user_id;
            $stmt = $pdo->prepare("INSERT INTO attributes (tenant_id, name, code, type, unit, group_id, is_required, is_filterable, is_variant_forming, business_type_id, sort_order, status, created_by, updated_by, created_at, updated_at) VALUES (:tenant_id, :name, :code, :type, :unit, :group_id, :is_required, :is_filterable, :is_variant_forming, :business_type_id, :sort_order, :status, :created_by, :updated_by, NOW(), NOW())");
            $stmt->execute($data);
            $attr_id = (int) $pdo->lastInsertId();
            log_activity($user_id, 'attribute_create', ['attribute_id' => $attr_id, 'name' => $name], $tenant_id);
        }

        $raw_values = $_POST['values'] ?? '[]';
        $values = [];
        if (is_string($raw_values)) {
            $decoded = json_decode($raw_values, true);
            if (is_array($decoded)) $values = $decoded;
        } elseif (is_array($raw_values)) {
            $values = $raw_values;
        }
        $pdo->prepare("DELETE FROM attribute_values WHERE attribute_id = :aid AND tenant_id = :tenant_id")->execute([':aid' => $attr_id, ':tenant_id' => $tenant_id]);

        $vstmt = $pdo->prepare("INSERT INTO attribute_values (attribute_id, tenant_id, value, label, color_hex, sort_order) VALUES (:attribute_id, :tenant_id, :value, :label, :color_hex, :sort_order)");
        foreach ($values as $i => $v) {
            if (empty($v['value'])) continue;
            $vstmt->execute([
                ':attribute_id' => $attr_id,
                ':tenant_id' => $tenant_id,
                ':value' => trim($v['value']),
                ':label' => trim($v['label'] ?? ''),
                ':color_hex' => trim($v['color_hex'] ?? ''),
                ':sort_order' => $i,
            ]);
        }

        return ['success' => true, 'data' => ['id' => $attr_id]];
    } catch (PDOException $e) {
        error_log("ajax_save error: " . $e->getMessage());
        return ['success' => false, 'error' => 'Database error: ' . $e->getMessage()];
    }
}

function ajax_delete(PDO $pdo, int $tenant_id, int $user_id): array {
    $id = intval($_POST['id'] ?? 0);
    if (!$id) return ['success' => false, 'error' => 'Missing ID'];

    try {
        $stmt = $pdo->prepare("UPDATE attributes SET deleted_at = NOW(), deleted_by = :deleted_by WHERE id = :id AND tenant_id = :tenant_id AND deleted_at IS NULL");
        $stmt->execute([':id' => $id, ':tenant_id' => $tenant_id, ':deleted_by' => $user_id]);
        log_activity($user_id, 'attribute_delete', ['attribute_id' => $id], $tenant_id);
        return ['success' => true, 'data' => ['id' => $id]];
    } catch (PDOException $e) {
        error_log("ajax_delete error: " . $e->getMessage());
        return ['success' => false, 'error' => 'Database error'];
    }
}

function ajax_bulk_status(PDO $pdo, int $tenant_id, int $user_id): array {
    $ids = isset($_POST['ids']) ? json_decode($_POST['ids'], true) : [];
    $status = intval($_POST['status'] ?? 0);
    if (empty($ids) || !is_array($ids)) return ['success' => false, 'error' => 'No items selected'];

    $cleanIds = array_filter(array_map('intval', $ids));
    if (empty($cleanIds)) return ['success' => false, 'error' => 'Invalid IDs'];

    try {
        $in = implode(',', array_fill(0, count($cleanIds), '?'));
        $stmt = $pdo->prepare("UPDATE attributes SET status = ?, updated_by = ?, updated_at = NOW() WHERE id IN ($in) AND tenant_id = ? AND deleted_at IS NULL");
        $params = array_merge([$status, $user_id], $cleanIds, [$tenant_id]);
        $stmt->execute($params);
        $updated = $stmt->rowCount();
        log_activity($user_id, 'attribute_bulk_status', ['ids' => $cleanIds, 'status' => $status], $tenant_id);
        return ['success' => true, 'data' => ['updated_count' => $updated]];
    } catch (PDOException $e) {
        error_log("ajax_bulk_status error: " . $e->getMessage());
        return ['success' => false, 'error' => 'Database error'];
    }
}

function ajax_bulk_delete(PDO $pdo, int $tenant_id, int $user_id): array {
    $ids = isset($_POST['ids']) ? json_decode($_POST['ids'], true) : [];
    if (empty($ids) || !is_array($ids)) return ['success' => false, 'error' => 'No items selected'];

    $cleanIds = array_filter(array_map('intval', $ids));
    if (empty($cleanIds)) return ['success' => false, 'error' => 'Invalid IDs'];

    try {
        $in = implode(',', array_fill(0, count($cleanIds), '?'));
        $stmt = $pdo->prepare("UPDATE attributes SET deleted_at = NOW(), deleted_by = ? WHERE id IN ($in) AND tenant_id = ? AND deleted_at IS NULL");
        $params = array_merge([$user_id], $cleanIds, [$tenant_id]);
        $stmt->execute($params);
        $deleted = $stmt->rowCount();
        log_activity($user_id, 'attribute_bulk_delete', ['ids' => $cleanIds], $tenant_id);
        return ['success' => true, 'data' => ['deleted_count' => $deleted]];
    } catch (PDOException $e) {
        error_log("ajax_bulk_delete error: " . $e->getMessage());
        return ['success' => false, 'error' => 'Database error'];
    }
}

function ajax_toggle_status(PDO $pdo, int $tenant_id, int $user_id): array {
    $id = intval($_POST['id'] ?? 0);
    $status = intval($_POST['status'] ?? 0);
    if (!$id) return ['success' => false, 'error' => 'Missing ID'];

    try {
        $stmt = $pdo->prepare("UPDATE attributes SET status = :status, updated_by = :updated_by, updated_at = NOW() WHERE id = :id AND tenant_id = :tenant_id");
        $stmt->execute([':status' => $status, ':updated_by' => $user_id, ':id' => $id, ':tenant_id' => $tenant_id]);
        return ['success' => true, 'data' => ['id' => $id, 'status' => $status]];
    } catch (PDOException $e) {
        error_log("ajax_toggle_status error: " . $e->getMessage());
        return ['success' => false, 'error' => 'Database error'];
    }
}

function ajax_reorder(PDO $pdo, int $tenant_id, int $user_id): array {
    $orders = isset($_POST['orders']) && is_array($_POST['orders']) ? $_POST['orders'] : [];
    if (empty($orders)) return ['success' => true];

    try {
        $stmt = $pdo->prepare("UPDATE attributes SET sort_order = :sort_order WHERE id = :id AND tenant_id = :tenant_id");
        foreach ($orders as $item) {
            $stmt->execute([':sort_order' => (int) $item['sort_order'], ':id' => (int) $item['id'], ':tenant_id' => $tenant_id]);
        }
        return ['success' => true];
    } catch (PDOException $e) {
        error_log("ajax_reorder error: " . $e->getMessage());
        return ['success' => false, 'error' => 'Database error'];
    }
}

function ajax_check_name(PDO $pdo, int $tenant_id): array {
    $name = trim($_GET['name'] ?? '');
    $id = intval($_GET['id'] ?? 0);
    if (!$name) return ['success' => true, 'data' => ['available' => false]];

    $stmt = $pdo->prepare("SELECT id FROM attributes WHERE tenant_id = :tenant_id AND name = :name AND deleted_at IS NULL" . ($id ? " AND id != :id" : ""));
    $p = [':tenant_id' => $tenant_id, ':name' => $name];
    if ($id) $p[':id'] = $id;
    $stmt->execute($p);
    return ['success' => true, 'data' => ['available' => !$stmt->fetch()]];
}

function ajax_check_code(PDO $pdo, int $tenant_id): array {
    $code = trim($_GET['code'] ?? '');
    $id = intval($_GET['id'] ?? 0);
    if (!$code) return ['success' => true, 'data' => ['available' => false]];

    $stmt = $pdo->prepare("SELECT id FROM attributes WHERE tenant_id = :tenant_id AND code = :code AND deleted_at IS NULL" . ($id ? " AND id != :id" : ""));
    $p = [':tenant_id' => $tenant_id, ':code' => $code];
    if ($id) $p[':id'] = $id;
    $stmt->execute($p);
    return ['success' => true, 'data' => ['available' => !$stmt->fetch()]];
}

function ajax_save_group(PDO $pdo, int $tenant_id, int $user_id): array {
    $id = intval($_POST['id'] ?? 0);
    $name = trim($_POST['name'] ?? '');
    $business_type_id = $_POST['business_type_id'] !== '' ? (int) $_POST['business_type_id'] : null;
    $sort_order = intval($_POST['sort_order'] ?? 0);

    if ($name === '') return ['success' => false, 'error' => 'Group name is required'];

    try {
        if ($id) {
            $stmt = $pdo->prepare("UPDATE attribute_groups SET name = :name, business_type_id = :business_type_id, sort_order = :sort_order WHERE id = :id AND tenant_id = :tenant_id");
            $stmt->execute([':name' => $name, ':business_type_id' => $business_type_id, ':sort_order' => $sort_order, ':id' => $id, ':tenant_id' => $tenant_id]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO attribute_groups (tenant_id, name, business_type_id, sort_order) VALUES (:tenant_id, :name, :business_type_id, :sort_order)");
            $stmt->execute([':tenant_id' => $tenant_id, ':name' => $name, ':business_type_id' => $business_type_id, ':sort_order' => $sort_order]);
            $id = (int) $pdo->lastInsertId();
        }
        return ['success' => true, 'data' => ['id' => $id, 'name' => $name]];
    } catch (PDOException $e) {
        error_log("ajax_save_group error: " . $e->getMessage());
        return ['success' => false, 'error' => 'Database error'];
    }
}

function ajax_delete_group(PDO $pdo, int $tenant_id, int $user_id): array {
    $id = intval($_POST['id'] ?? 0);
    if (!$id) return ['success' => false, 'error' => 'Missing ID'];

    try {
        $check = $pdo->prepare("SELECT COUNT(*) FROM attributes WHERE group_id = :id AND tenant_id = :tenant_id AND deleted_at IS NULL");
        $check->execute([':id' => $id, ':tenant_id' => $tenant_id]);
        if ((int) $check->fetchColumn() > 0) {
            return ['success' => false, 'error' => 'Cannot delete group with assigned attributes'];
        }
        $pdo->prepare("DELETE FROM attribute_groups WHERE id = :id AND tenant_id = :tenant_id")->execute([':id' => $id, ':tenant_id' => $tenant_id]);
        return ['success' => true];
    } catch (PDOException $e) {
        error_log("ajax_delete_group error: " . $e->getMessage());
        return ['success' => false, 'error' => 'Database error'];
    }
}

function ajax_stats(PDO $pdo, int $tenant_id): array {
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) as total, SUM(CASE WHEN status = 1 THEN 1 ELSE 0 END) as active, SUM(CASE WHEN is_variant_forming = 1 THEN 1 ELSE 0 END) as variant FROM attributes WHERE tenant_id = :t AND deleted_at IS NULL");
        $stmt->execute([':t' => $tenant_id]);
        $stats = $stmt->fetch(PDO::FETCH_ASSOC);

        $tstmt = $pdo->prepare("SELECT COUNT(*) FROM attribute_values WHERE tenant_id = :t");
        $tstmt->execute([':t' => $tenant_id]);
        $stats['terms'] = (int) $tstmt->fetchColumn();

        return ['success' => true, 'data' => $stats ?: ['total' => 0, 'active' => 0, 'variant' => 0, 'terms' => 0]];
    } catch (PDOException $e) {
        error_log("ajax_stats error: " . $e->getMessage());
        return ['success' => false, 'error' => 'Database error'];
    }
}

// ============================================
// Page Data Loading
// ============================================
$groups = [];
$business_types = [];
try {
    $gstmt = $pdo->prepare("SELECT * FROM attribute_groups WHERE tenant_id = :tenant_id AND deleted_at IS NULL ORDER BY sort_order, name");
    $gstmt->execute([':tenant_id' => $tenant_id]);
    $groups = $gstmt->fetchAll(PDO::FETCH_ASSOC);

    $bstmt = $pdo->prepare("SELECT id, name FROM business_types WHERE active = 1 ORDER BY name");
    $bstmt->execute();
    $business_types = $bstmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Attributes page load error: " . $e->getMessage());
}

$csrf_token = generate_csrf_token();
$page_title = 'Product Attributes | ' . ($branch_name ?? 'Jakababa POS');

// Load initial rows for server-side rendering fallback
$initial_rows = [];
try {
    $istmt = $pdo->prepare("SELECT a.*, ag.name as group_name,
        (SELECT COUNT(*) FROM attribute_values av WHERE av.attribute_id = a.id AND av.tenant_id = :t1) as values_count,
        (SELECT COUNT(DISTINCT pav.product_id) FROM product_attribute_values pav WHERE pav.attribute_id = a.id AND pav.tenant_id = :t2) as product_count
        FROM attributes a
        LEFT JOIN attribute_groups ag ON ag.id = a.group_id AND ag.tenant_id = :t3
        WHERE a.tenant_id = :tenant_id AND a.deleted_at IS NULL
        ORDER BY a.sort_order ASC, a.name ASC
        LIMIT 20 OFFSET 0");
    $istmt->bindValue(':t1', $tenant_id, PDO::PARAM_INT);
    $istmt->bindValue(':t2', $tenant_id, PDO::PARAM_INT);
    $istmt->bindValue(':t3', $tenant_id, PDO::PARAM_INT);
    $istmt->bindValue(':tenant_id', $tenant_id, PDO::PARAM_INT);
    $istmt->execute();
    $initial_rows = $istmt->fetchAll(PDO::FETCH_ASSOC);

    $iids = array_column($initial_rows, 'id');
    if (!empty($iids)) {
        $placeholders = implode(',', array_fill(0, count($iids), '?'));
        $ivstmt = $pdo->prepare("SELECT attribute_id, value, label FROM attribute_values WHERE attribute_id IN ($placeholders) AND tenant_id = ? ORDER BY sort_order, id");
        $ivstmt->execute(array_merge($iids, [$tenant_id]));
        $iall_values = $ivstmt->fetchAll(PDO::FETCH_ASSOC);
        $igrouped = [];
        foreach ($iall_values as $v) {
            $igrouped[$v['attribute_id']][] = $v['label'] ?: $v['value'];
        }
        foreach ($initial_rows as &$row) {
            $vals = $igrouped[$row['id']] ?? [];
            $row['values_preview'] = implode(', ', array_slice($vals, 0, 5));
            $row['values_count'] = count($vals);
        }
        unset($row);
    }
} catch (PDOException $e) {
    error_log("Initial rows load error: " . $e->getMessage());
}

function get_type_icon(string $type): string {
    $icons = ['text' => 'fa-font', 'textarea' => 'fa-align-left', 'dropdown' => 'fa-list', 'multiselect' => 'fa-check-square', 'number' => 'fa-hashtag', 'color' => 'fa-palette', 'file' => 'fa-file', 'date' => 'fa-calendar', 'boolean' => 'fa-toggle-on', 'select' => 'fa-list'];
    return $icons[$type] ?? 'fa-circle';
}
function get_type_label(string $type): string {
    $labels = ['text' => 'Text', 'textarea' => 'Textarea', 'dropdown' => 'Dropdown', 'multiselect' => 'Multi-Select', 'number' => 'Number', 'color' => 'Color', 'file' => 'File', 'date' => 'Date', 'boolean' => 'Yes/No', 'select' => 'Select'];
    return $labels[$type] ?? ucfirst($type);
}
function get_scope_label(array $attr): string {
    $parts = [];
    if (!empty($attr['is_filterable'])) $parts[] = 'Public';
    if (!empty($attr['is_variant_forming'])) $parts[] = 'Variant';
    return $parts ? ' (' . implode(', ', $parts) . ')' : ' (Private)';
}
function get_order_by_label(array $attr): string {
    return 'Custom ordering';
}

ob_start();
?>
<style>
#attr-pagination { display: flex; align-items: center; justify-content: space-between; gap: 0.5rem; margin-top: 1rem; flex-wrap: wrap; }
#attr-pagination .page-buttons { display: flex; align-items: center; gap: 0.25rem; }
#attr-pagination button { padding: 0.5rem 0.875rem; background: rgba(31,41,55,0.86); border: 1px solid rgba(148,163,184,0.16); border-radius: 0.5rem; color: #94A3B8; font-size: 0.875rem; font-weight: 600; transition: all 0.18s ease; cursor: pointer; min-height: 38px; display: inline-flex; align-items: center; }
#attr-pagination button:hover:not(:disabled) { border-color: rgba(245,158,11,0.22); color: #F8FAFC; background: rgba(30,41,59,0.96); }
#attr-pagination button:disabled { opacity: 0.4; cursor: not-allowed; }
#attr-pagination button.active { background: linear-gradient(135deg,#FBBF24 0%,#F59E0B 100%); color: #111827; border-color: rgba(245,158,11,0.18); }
.attr-value-row { display: flex; align-items: center; gap: 0.5rem; padding: 0.5rem; border-bottom: 1px solid rgba(55,65,81,0.5); }
.attr-value-row:last-child { border-bottom: none; }
.attr-value-drag { color: #6B7280; cursor: grab; padding: 0.25rem; }
#attr-values-manager { max-height: 300px; overflow-y: auto; }
.row-actions { position: relative; }
.row-actions-menu { position: absolute; right: 0; top: 100%; margin-top: 0.25rem; background: #1e293b; border: 1px solid #334155; border-radius: 0.5rem; padding: 0.25rem; min-width: 140px; z-index: 40; box-shadow: 0 10px 40px rgba(0,0,0,0.4); }
.row-actions-menu button, .row-actions-menu a { display: flex; align-items: center; gap: 0.5rem; width: 100%; padding: 0.5rem 0.75rem; color: #cbd5e1; font-size: 0.875rem; border-radius: 0.25rem; text-align: left; }
.row-actions-menu button:hover, .row-actions-menu a:hover { background: #334155; color: #fbbf24; }
.col-vis-dropdown { position: relative; }
.col-vis-menu { position: absolute; right: 0; top: 100%; margin-top: 0.5rem; background: #1e293b; border: 1px solid #334155; border-radius: 0.5rem; padding: 0.5rem; min-width: 180px; z-index: 50; box-shadow: 0 10px 40px rgba(0,0,0,0.4); }
.col-vis-menu label { display: flex; align-items: center; gap: 0.5rem; padding: 0.375rem 0.5rem; color: #cbd5e1; font-size: 0.875rem; cursor: pointer; border-radius: 0.25rem; }
.col-vis-menu label:hover { background: #334155; }
.wc-row-actions { display: flex; align-items: center; gap: 0.25rem; margin-top: 0.25rem; font-size: 0.75rem; color: #94a3b8; white-space: nowrap; }
.wc-row-actions a, .wc-row-actions button { color: #60a5fa; background: none; border: none; padding: 0; cursor: pointer; font-size: inherit; text-decoration: none; white-space: nowrap; }
.wc-row-actions a:hover, .wc-row-actions button:hover { color: #fbbf24; text-decoration: underline; }
.wc-row-actions .sep { color: #6b7280; }
.terms-preview { color: #cbd5e1; font-size: 0.8125rem; line-height: 1.4; }
.terms-preview .na { color: #6b7280; font-style: italic; }
.configure-terms { display: inline-block; margin-top: 0.375rem; color: #60a5fa; font-size: 0.75rem; text-decoration: none; white-space: nowrap; }
.configure-terms:hover { color: #fbbf24; text-decoration: underline; }
.configure-terms i { margin-right: 0.25rem; font-size: 0.625rem; }
.order-by-label { color: #94a3b8; font-size: 0.8125rem; white-space: nowrap; }
.type-badge { display: inline-flex; align-items: center; gap: 0.375rem; padding: 0.25rem 0.5rem; border-radius: 0.375rem; font-size: 0.75rem; background: rgba(51,65,85,0.5); color: #cbd5e1; white-space: nowrap; }
.variant-icon { display: inline-flex; align-items: center; justify-content: center; width: 1.5rem; height: 1.5rem; border-radius: 0.375rem; background: rgba(139,92,246,0.15); color: #a78bfa; font-size: 0.625rem; }
.value-count { display: inline-flex; align-items: center; justify-content: center; min-width: 1.5rem; height: 1.5rem; border-radius: 0.375rem; background: rgba(59,130,246,0.15); color: #60a5fa; font-size: 0.75rem; font-weight: 600; padding: 0 0.375rem; }
.status-badge { display: inline-flex; align-items: center; gap: 0.375rem; padding: 0.25rem 0.625rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 600; }
.status-badge.active { background: rgba(16,185,129,0.15); color: #34d399; border: 1px solid rgba(16,185,129,0.3); }
.status-badge.inactive { background: rgba(107,114,128,0.15); color: #9ca3af; border: 1px solid rgba(107,114,128,0.3); }
.kb-shortcut { display: inline-flex; align-items: center; gap: 0.25rem; padding: 0.125rem 0.375rem; background: #334155; border-radius: 0.25rem; font-size: 0.75rem; color: #94a3b8; font-family: monospace; }
.saved-filter-chip { display: inline-flex; align-items: center; gap: 0.375rem; padding: 0.375rem 0.75rem; background: rgba(251,191,36,0.1); border: 1px solid rgba(251,191,36,0.3); border-radius: 9999px; font-size: 0.75rem; color: #fbbf24; cursor: pointer; transition: all 0.2s; }
.saved-filter-chip:hover { background: rgba(251,191,36,0.2); }
@media (max-width: 768px) { #attr-type-grid { grid-template-columns: repeat(2,1fr); } #attr-slide-over { max-width: 100%; } }
</style>

<div class="fade-in" id="attributes-content">
    <!-- Toast -->
    <div id="attr-toast" class="fixed bottom-4 right-4 z-50 hidden px-4 py-3 rounded-lg text-sm font-medium shadow-2xl border-l-4">
        <span id="attr-toast-msg"></span>
    </div>

    <!-- Toolbar -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-5">
        <div>
            <p class="text-xs font-medium text-amber-400 uppercase tracking-wide mb-0.5">Products</p>
            <h1 class="text-lg font-bold text-white">Attributes</h1>
            <p class="text-sm text-slate-500 mt-0.5">Managing attributes in <span class="text-amber-400"><?php echo htmlspecialchars($branch_name); ?></span></p>
        </div>
        <div class="flex flex-wrap gap-2">
            <?php if ($can_create): ?>
            <a href="attribute_form.php" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-amber-500 text-black text-sm font-semibold hover:bg-amber-600 transition-colors">
                <i class="fas fa-plus text-xs"></i><span class="hidden sm:inline">New Attribute</span>
            </a>
            <button type="button" onclick="openGroupModal()" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-slate-700 border border-slate-600 text-slate-300 text-sm font-medium hover:bg-slate-600 transition-colors">
                <i class="fas fa-folder-plus text-xs"></i><span class="hidden sm:inline">New Group</span>
            </button>
            <?php endif; ?>
            <button type="button" onclick="exportAttributes()" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-slate-700 border border-slate-600 text-slate-300 text-sm font-medium hover:bg-slate-600 transition-colors">
                <i class="fas fa-download text-xs"></i><span class="hidden sm:inline">Export</span>
            </button>
            <button type="button" onclick="toggleShortcutsHelp()" title="Keyboard Shortcuts" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-slate-700 border border-slate-600 text-slate-300 text-sm hover:bg-slate-600 transition-colors">
                <i class="fas fa-keyboard text-xs"></i>
            </button>
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-3 mb-5">
        <?php foreach ([['stat-total','tags','blue-400','bg-blue-500/10','Total','text-white'],['stat-active','check-circle','emerald-400','bg-emerald-500/10','Active','text-emerald-400'],['stat-inactive','pause-circle','slate-400','bg-slate-700/60','Inactive','text-slate-400'],['stat-variant','code-branch','purple-400','bg-purple-500/10','Variant','text-purple-400'],['stat-terms','list','amber-400','bg-amber-500/10','Terms','text-amber-400']] as [$sid,$icon,$color,$bg,$label,$textColor]): ?>
        <div class="bg-slate-800/50 border border-slate-700/60 rounded-xl p-3 flex items-center gap-2.5">
            <div class="w-8 h-8 rounded-lg <?php echo $bg; ?> flex items-center justify-center shrink-0"><i class="fas fa-<?php echo $icon; ?> text-<?php echo $color; ?> text-xs"></i></div>
            <div><div class="text-[10px] text-slate-500 uppercase tracking-wide"><?php echo $label; ?></div><div class="text-xl font-bold <?php echo $textColor; ?>" id="<?php echo $sid; ?>">-</div></div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Status Tabs -->
    <div class="flex items-center gap-1 mb-4 border-b border-slate-700/60 pb-2 overflow-x-auto" id="status-tabs">
        <button data-status="" class="px-3 py-1.5 text-sm rounded-lg whitespace-nowrap bg-amber-500 text-slate-900 font-semibold" onclick="setStatusFilter('')">All <span id="tab-count-all">-</span></button>
        <button data-status="1" class="px-3 py-1.5 text-sm rounded-lg whitespace-nowrap text-slate-400 hover:text-white hover:bg-slate-700/60 transition-colors" onclick="setStatusFilter('1')">Active <span id="tab-count-active" class="text-emerald-400">-</span></button>
        <button data-status="0" class="px-3 py-1.5 text-sm rounded-lg whitespace-nowrap text-slate-400 hover:text-white hover:bg-slate-700/60 transition-colors" onclick="setStatusFilter('0')">Inactive <span id="tab-count-inactive" class="text-slate-500">-</span></button>
        <div class="ml-auto flex items-center gap-2">
            <select id="attr-sort" class="bg-slate-800 border border-slate-700 rounded-lg px-2 py-1.5 text-slate-300 text-sm focus:outline-none focus:ring-1 focus:ring-amber-500" onchange="loadAttributes()">
                <option value="sort_order_asc">Sort Order</option>
                <option value="name_asc">Name A-Z</option>
                <option value="name_desc">Name Z-A</option>
                <option value="product_count_desc">Most Products</option>
                <option value="product_count_asc">Least Products</option>
                <option value="updated_desc">Recently Updated</option>
                <option value="id_desc">Newest First</option>
                <option value="id_asc">Oldest First</option>
            </select>
        </div>
    </div>

    <!-- Filters -->
    <?php $sel = 'bg-slate-800 border border-slate-700 rounded-lg px-2 py-2 text-slate-300 text-sm focus:outline-none focus:ring-1 focus:ring-amber-500'; ?>
    <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-3 mb-4">
        <div class="flex flex-col lg:flex-row gap-3">
            <div class="flex-1 relative">
                <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-slate-500 text-xs"></i>
                <input type="text" id="attr-search" autocomplete="off" placeholder="Search by name or code..."
                       class="w-full bg-slate-900 border border-slate-700 rounded-lg pl-8 pr-3 py-2 text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-1 focus:ring-amber-500">
            </div>
            <div class="flex flex-wrap gap-2">
                <select id="attr-filter-type" class="<?php echo $sel; ?>" onchange="loadAttributes()">
                    <option value="">All Types</option>
                    <option value="text">Text</option><option value="textarea">Textarea</option>
                    <option value="dropdown">Dropdown</option><option value="multiselect">Multi-Select</option>
                    <option value="number">Number</option><option value="color">Color</option>
                    <option value="file">File</option><option value="date">Date</option><option value="boolean">Yes/No</option>
                </select>
                <select id="attr-filter-group" class="<?php echo $sel; ?>" onchange="loadAttributes()">
                    <option value="">All Groups</option>
                    <?php foreach ($groups as $g): ?>
                    <option value="<?php echo $g['id']; ?>"><?php echo htmlspecialchars($g['name']); ?></option>
                    <?php endforeach; ?>
                </select>
                <select id="attr-per-page" class="<?php echo $sel; ?>" onchange="changePerPage()">
                    <option value="10">10 / page</option><option value="20" selected>20 / page</option>
                    <option value="50">50 / page</option><option value="100">100 / page</option>
                </select>
                <button type="button" onclick="loadAttributes()" class="inline-flex items-center px-3 py-2 rounded-lg bg-amber-500/15 border border-amber-500/40 text-amber-400 text-sm hover:bg-amber-500/25 transition-colors"><i class="fas fa-filter text-xs"></i></button>
                <button type="button" onclick="clearFilters()" title="Clear Filters" class="inline-flex items-center px-3 py-2 rounded-lg bg-slate-700 border border-slate-600 text-slate-300 text-sm hover:bg-slate-600 transition-colors"><i class="fas fa-xmark text-xs"></i></button>
                <button type="button" onclick="saveCurrentFilter()" title="Save Filter" class="inline-flex items-center px-3 py-2 rounded-lg bg-slate-700 border border-slate-600 text-slate-300 text-sm hover:bg-slate-600 transition-colors"><i class="fas fa-bookmark text-xs"></i></button>
            </div>
        </div>
        <div id="saved-filters-bar" class="flex flex-wrap gap-2 mt-3 hidden"></div>
    </div>

    <!-- Bulk Actions Bar -->
    <div id="bulkActionsBar" class="hidden bg-slate-800/40 border border-slate-700/60 rounded-xl px-3 py-2.5 mb-4">
        <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3">
            <div class="flex items-center gap-3">
                <span class="text-sm text-slate-400"><span id="selectedCount" class="text-white font-semibold">0</span> selected</span>
                <button onclick="selectAllOnPage()" class="text-xs text-amber-400 hover:text-amber-300 transition">Select All</button>
                <button onclick="clearSelection()" class="text-xs text-slate-500 hover:text-slate-300 transition">Clear</button>
            </div>
            <div class="flex items-center gap-2">
                <button onclick="bulkStatusUpdate(1)" class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 text-xs font-semibold hover:bg-emerald-500/20 transition-colors"><i class="fas fa-check"></i>Activate</button>
                <button onclick="bulkStatusUpdate(0)" class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg bg-slate-700 border border-slate-600 text-slate-300 text-xs font-medium hover:bg-slate-600 transition-colors"><i class="fas fa-ban"></i>Deactivate</button>
                <?php if ($can_delete): ?>
                <button onclick="openBulkDeleteModal()" class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg bg-red-500/10 border border-red-500/30 text-red-400 text-xs font-semibold hover:bg-red-500/20 transition-colors"><i class="fas fa-trash"></i>Delete</button>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Two Column Layout -->
    <div class="flex flex-col lg:flex-row gap-5">
        <!-- Main: Table -->
        <div class="flex-1 min-w-0">
            <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm" id="attr-table">
                        <thead class="bg-slate-900/50 border-b border-slate-700/60">
                            <tr>
                                <th class="w-10 px-3 py-3">
                                    <input type="checkbox" id="selectAllCheckbox" class="w-4 h-4 rounded accent-amber-500 cursor-pointer" onchange="toggleSelectAll(this)">
                                </th>
                                <th class="px-3 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider cursor-pointer hover:text-amber-400" data-col="name" onclick="sortTable('name')">Name <i class="fas fa-sort text-[10px] opacity-40"></i></th>
                                <th class="px-3 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider cursor-pointer hover:text-amber-400" data-col="code" onclick="sortTable('code')">Slug <i class="fas fa-sort text-[10px] opacity-40"></i></th>
                                <th class="px-3 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider cursor-pointer hover:text-amber-400" data-col="type" onclick="sortTable('type')">Type <i class="fas fa-sort text-[10px] opacity-40"></i></th>
                                <th class="px-3 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider cursor-pointer hover:text-amber-400" data-col="group" onclick="sortTable('group')">Group <i class="fas fa-sort text-[10px] opacity-40"></i></th>
                                <th class="px-3 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider" data-col="order_by">Order by</th>
                                <th class="px-3 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider" data-col="values">Terms</th>
                                <th class="px-3 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider" data-col="products">Products</th>
                                <th class="px-3 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider cursor-pointer hover:text-amber-400" data-col="status" onclick="sortTable('status')">Status <i class="fas fa-sort text-[10px] opacity-40"></i></th>
                                <th class="px-3 py-3 w-10 text-right">
                                    <div class="col-vis-dropdown">
                                        <button type="button" onclick="toggleColVis()" class="text-slate-500 hover:text-amber-400 transition-colors"><i class="fas fa-columns text-xs"></i></button>
                                        <div id="colVisMenu" class="col-vis-menu hidden"></div>
                                    </div>
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-700/40" id="attr-tbody">
                            <?php if (empty($initial_rows)): ?>
                            <tr>
                                <td colspan="10" class="px-4 py-14 text-center">
                                    <i class="fas fa-inbox text-3xl text-slate-700 mb-3 block"></i>
                                    <p class="text-slate-400 font-medium">No attributes found</p>
                                    <p class="text-slate-500 text-xs mt-1">Try adjusting your filters or create a new attribute.</p>
                                </td>
                            </tr>
                            <?php else: foreach ($initial_rows as $attr): ?>
                            <tr class="hover:bg-slate-700/20 transition-colors cursor-pointer" data-id="<?php echo (int)$attr['id']; ?>" onclick="showAttributeDetail(<?php echo (int)$attr['id']; ?>)">
                                <td><input type="checkbox" class="attr-checkbox w-4 h-4 rounded accent-amber-500 cursor-pointer" value="<?php echo (int)$attr['id']; ?>" onchange="toggleAttributeSelection(this)"></td>
                                <td>
                                    <div class="flex flex-col">
                                        <div class="flex items-center gap-2">
                                            <span class="font-medium text-white"><?php echo htmlspecialchars($attr['name']); ?></span>
                                            <?php if ($attr['is_variant_forming']): ?><span class="variant-icon" title="Variant Forming"><i class="fas fa-code-branch"></i></span><?php endif; ?>
                                        </div>
                                        <div class="wc-row-actions">
                                            <a href="attribute_form.php?id=<?php echo (int)$attr['id']; ?>">Edit</a>
                                            <span class="sep">|</span>
                                            <a href="attribute_terms.php?attribute_id=<?php echo (int)$attr['id']; ?>" onclick="event.stopPropagation()">Configure terms</a>
                                            <span class="sep">|</span>
                                            <button type="button" onclick="event.stopPropagation(); deleteAttribute(<?php echo (int)$attr['id']; ?>, <?php echo json_encode($attr['name']); ?>)">Delete</button>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-3 py-2.5"><code class="font-mono text-xs text-slate-500"><?php echo htmlspecialchars($attr['code']); ?></code></td>
                                <td class="px-3 py-2.5"><span class="type-badge"><i class="fas <?php echo get_type_icon($attr['type']); ?>"></i> <?php echo get_type_label($attr['type']) . get_scope_label($attr); ?></span></td>
                                <td class="px-3 py-2.5 text-slate-300"><?php echo $attr['group_name'] ? htmlspecialchars($attr['group_name']) : '<span class="text-slate-500">—</span>'; ?></td>
                                <td class="px-3 py-2.5 order-by-label"><?php echo get_order_by_label($attr); ?></td>
                                <td class="px-3 py-2.5">
                                    <div class="terms-preview">
                                        <?php if (!empty($attr['values_preview'])): ?>
                                            <?php echo htmlspecialchars($attr['values_preview']); ?>
                                        <?php else: ?>
                                            <span class="na">—</span>
                                        <?php endif; ?>
                                        <br><a href="attribute_terms.php?attribute_id=<?php echo (int)$attr['id']; ?>" class="configure-terms" onclick="event.stopPropagation()"><i class="fas fa-cog"></i> Configure terms</a>
                                    </div>
                                </td>
                                <td class="px-3 py-2.5 text-sm <?php echo ($attr['product_count'] ?? 0) > 0 ? 'text-slate-200' : 'text-slate-500'; ?>"><?php echo (int)($attr['product_count'] ?? 0); ?></td>
                                <td class="px-3 py-2.5"><button onclick="toggleStatus(<?php echo (int)$attr['id']; ?>, <?php echo (int)$attr['status']; ?>)" class="status-badge <?php echo $attr['status'] ? 'active' : 'inactive'; ?>"><i class="fas <?php echo $attr['status'] ? 'fa-check-circle' : 'fa-ban'; ?>"></i> <?php echo $attr['status'] ? 'Active' : 'Inactive'; ?></button></td>
                                <td class="px-3 py-2.5 text-right"><div class="row-actions"><button onclick="event.stopPropagation(); toggleRowActions(this, <?php echo (int)$attr['id']; ?>, <?php echo json_encode($attr['name']); ?>)" class="text-slate-500 hover:text-amber-400 p-1 transition-colors"><i class="fas fa-ellipsis-v text-xs"></i></button></div></td>
                            </tr>
                            <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Pagination -->
            <div id="attr-pagination">
                <span class="text-sm text-slate-500" id="pagination-info"></span>
                <div class="page-buttons" id="pagination-buttons"></div>
            </div>
        </div>

        <!-- Sidebar -->
        <div class="w-full lg:w-72 flex-shrink-0 flex flex-col gap-4">
            <!-- Groups -->
            <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-4">
                <div class="flex items-center justify-between mb-3">
                    <h3 class="text-sm font-semibold text-white flex items-center gap-2"><i class="fas fa-folder text-amber-400"></i> Groups</h3>
                    <?php if ($can_create): ?>
                    <button onclick="openGroupModal()" class="text-xs text-amber-400 hover:text-amber-300 transition"><i class="fas fa-plus"></i></button>
                    <?php endif; ?>
                </div>
                <div class="space-y-1" id="attr-group-list">
                    <?php foreach ($groups as $g): ?>
                    <div class="flex items-center justify-between p-2 rounded-lg hover:bg-slate-700/40 cursor-pointer group transition-colors" onclick="filterByGroup(<?php echo $g['id']; ?>)">
                        <span class="text-sm text-slate-300 truncate"><?php echo htmlspecialchars($g['name']); ?></span>
                        <div class="flex gap-1 opacity-0 group-hover:opacity-100 transition">
                            <button onclick="event.stopPropagation(); editGroup(<?php echo $g['id']; ?>, '<?php echo htmlspecialchars($g['name'], ENT_QUOTES); ?>')" class="w-6 h-6 flex items-center justify-center text-slate-500 hover:text-amber-400 transition-colors"><i class="fas fa-edit text-xs"></i></button>
                            <button onclick="event.stopPropagation(); deleteGroup(<?php echo $g['id']; ?>)" class="w-6 h-6 flex items-center justify-center text-slate-500 hover:text-red-400 transition-colors"><i class="fas fa-trash text-xs"></i></button>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    <?php if (empty($groups)): ?>
                    <p class="text-center text-slate-500 text-sm py-4">No groups yet</p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Presets -->
            <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-4">
                <h3 class="text-sm font-semibold text-white mb-3 flex items-center gap-2"><i class="fas fa-magic text-amber-400"></i> Smart Presets</h3>
                <div class="flex flex-wrap gap-2">
                    <?php foreach ([['pharmacy','pills','emerald-400'],['fashion','tshirt','purple-400'],['restaurant','utensils','amber-400'],['electronics','microchip','blue-400'],['butchery','drumstick-bite','red-400']] as [$preset,$icon,$color]): ?>
                    <button onclick="applyPreset('<?php echo $preset; ?>')" class="inline-flex items-center gap-1.5 px-2.5 py-1.5 bg-slate-900/50 border border-slate-700/40 rounded-lg text-slate-400 text-xs hover:border-amber-500/50 hover:text-amber-400 transition-colors">
                        <i class="fas fa-<?php echo $icon; ?> text-<?php echo $color; ?> text-[10px]"></i> <?php echo ucfirst($preset); ?>
                    </button>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Recently Viewed -->
            <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-4">
                <h3 class="text-sm font-semibold text-white mb-3 flex items-center gap-2"><i class="fas fa-clock text-amber-400"></i> Recent</h3>
                <div id="recent-attributes" class="space-y-1">
                    <p class="text-center text-slate-500 text-sm py-2">No recent attributes</p>
                </div>
            </div>

            <!-- Detail Panel -->
            <div id="attr-detail-panel" class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-4 hidden">
                <h3 class="text-sm font-semibold text-white mb-3 flex items-center gap-2"><i class="fas fa-info-circle text-amber-400"></i> Details</h3>
                <div id="attr-detail-content"></div>
            </div>

            <!-- Keyboard Shortcuts -->
            <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-4">
                <h3 class="text-sm font-semibold text-white mb-3 flex items-center gap-2"><i class="fas fa-keyboard text-amber-400"></i> Shortcuts</h3>
                <div class="space-y-2 text-xs text-slate-400">
                    <div class="flex justify-between"><span>New Attribute</span> <div class="flex gap-1"><span class="kb-shortcut">Ctrl</span><span class="kb-shortcut">N</span></div></div>
                    <div class="flex justify-between"><span>Search</span> <span class="kb-shortcut">/</span></div>
                    <div class="flex justify-between"><span>Export</span> <div class="flex gap-1"><span class="kb-shortcut">Ctrl</span><span class="kb-shortcut">E</span></div></div>
                    <div class="flex justify-between"><span>Clear Filters</span> <span class="kb-shortcut">Esc</span></div>
                </div>
            </div>
        </div>
    </div>

<!-- Delete Modal -->
<div id="deleteModal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/60 ">
    <div class="bg-slate-800 border border-slate-700 rounded-xl p-6 w-full max-w-sm mx-4 shadow-2xl">
        <div class="flex items-center gap-3 mb-4">
            <div class="w-10 h-10 rounded-full bg-red-500/10 border border-red-500/30 flex items-center justify-center flex-shrink-0"><i class="fas fa-trash text-red-400 text-sm"></i></div>
            <h3 class="text-base font-semibold text-white">Delete Attribute</h3>
        </div>
        <p class="text-slate-400 text-sm mb-2">Are you sure you want to delete <strong id="deleteAttrName" class="text-amber-400">this attribute</strong>?</p>
        <p class="text-xs text-slate-500 mb-5">This action cannot be undone. Associated product data will remain but the attribute definition will be removed.</p>
        <div class="flex gap-2">
            <button onclick="closeDeleteModal()" class="flex-1 inline-flex items-center justify-center px-3 py-2 rounded-lg bg-slate-700 border border-slate-600 text-slate-300 text-sm font-medium hover:bg-slate-600 transition-colors">Cancel</button>
            <button onclick="confirmDelete()" class="flex-1 inline-flex items-center justify-center px-3 py-2 rounded-lg bg-red-500/10 border border-red-500/30 text-red-400 text-sm font-medium hover:bg-red-500/20 transition-colors">Delete</button>
        </div>
    </div>
</div>

<!-- Bulk Delete Modal -->
<div id="bulkDeleteModal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/60 ">
    <div class="bg-slate-800 border border-slate-700 rounded-xl p-6 w-full max-w-sm mx-4 shadow-2xl">
        <div class="flex items-center gap-3 mb-4">
            <div class="w-10 h-10 rounded-full bg-red-500/10 border border-red-500/30 flex items-center justify-center flex-shrink-0"><i class="fas fa-trash text-red-400 text-sm"></i></div>
            <h3 class="text-base font-semibold text-white">Delete Selected Attributes</h3>
        </div>
        <p class="text-slate-400 text-sm mb-2">Delete <span id="bulkDeleteCount" class="text-amber-400 font-bold">0</span> selected attributes?</p>
        <p class="text-xs text-slate-500 mb-5">This action cannot be undone.</p>
        <div class="flex gap-2">
            <button onclick="closeBulkDeleteModal()" class="flex-1 inline-flex items-center justify-center px-3 py-2 rounded-lg bg-slate-700 border border-slate-600 text-slate-300 text-sm font-medium hover:bg-slate-600 transition-colors">Cancel</button>
            <button onclick="confirmBulkDelete()" class="flex-1 inline-flex items-center justify-center px-3 py-2 rounded-lg bg-red-500/10 border border-red-500/30 text-red-400 text-sm font-medium hover:bg-red-500/20 transition-colors">Delete All</button>
        </div>
    </div>
</div>

<!-- Shortcuts Help Modal -->
<div id="shortcutsModal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/60 ">
    <div class="bg-slate-800 border border-slate-700 rounded-xl p-6 w-full max-w-md mx-4 shadow-2xl">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-base font-semibold text-white">Keyboard Shortcuts</h3>
            <button onclick="toggleShortcutsHelp()" class="text-slate-400 hover:text-white transition-colors"><i class="fas fa-times"></i></button>
        </div>
        <div class="space-y-1 text-sm">
            <?php foreach ([['New Attribute','Ctrl N'],['Focus Search','/'],['Export CSV','Ctrl E'],['Clear Filters','Esc'],['Select All','Ctrl A'],['Close Modal','Esc']] as [$action,$keys]): ?>
            <div class="flex justify-between items-center py-2 border-b border-slate-700/40 last:border-0">
                <span class="text-slate-300"><?php echo $action; ?></span>
                <div class="flex gap-1"><?php foreach (explode(' ',$keys) as $k): ?><span class="kb-shortcut"><?php echo $k; ?></span><?php endforeach; ?></div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- Slide Over -->
<div id="attr-slide-overlay" class="fixed inset-0 bg-black/70  z-[1000] hidden" onclick="closeSlideOver()"></div>
<div id="attr-slide-over" class="fixed top-0 right-0 w-full max-w-lg h-full bg-slate-900 border-l border-slate-700/60 shadow-2xl z-[1001] transform translate-x-full transition-transform duration-300 flex flex-col">
    <div class="flex justify-between items-center px-5 py-4 border-b border-slate-700/60">
        <h2 id="slide-title" class="text-base font-semibold text-white">Create New Attribute</h2>
        <button onclick="closeSlideOver()" class="text-slate-400 hover:text-white transition-colors"><i class="fas fa-times"></i></button>
    </div>
    <div class="flex-1 overflow-y-auto p-5">
        <?php $sinp = 'w-full bg-slate-800 border border-slate-700 rounded-lg px-3 py-2 text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-1 focus:ring-amber-500 transition-colors'; ?>
        <form id="attr-form" onsubmit="return false">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
            <input type="hidden" name="id" id="attr-form-id" value="">

            <div class="mb-4">
                <label for="attr-name" class="block text-xs font-medium text-slate-400 uppercase tracking-wide mb-1">Attribute Name <span class="text-red-400">*</span></label>
                <input type="text" name="name" id="attr-name" autocomplete="off" class="<?php echo $sinp; ?>" placeholder="e.g. Size, Color, Material" onblur="checkName()" required>
                <span id="name-error" class="text-xs text-red-400 mt-1 block"></span>
            </div>

            <div class="mb-4">
                <label for="attr-code" class="block text-xs font-medium text-slate-400 uppercase tracking-wide mb-1">Code <span class="text-red-400">*</span></label>
                <input type="text" name="code" id="attr-code" autocomplete="off" class="<?php echo $sinp; ?>" placeholder="e.g. size, color" onblur="checkCode()" required>
                <span id="code-error" class="text-xs text-red-400 mt-1 block"></span>
            </div>

            <div class="mb-4">
                <span class="block text-xs font-medium text-slate-400 uppercase tracking-wide mb-2">Type</span>
                <div class="grid grid-cols-3 gap-2" id="attr-type-grid">
                    <?php $types = [
                        ['text', 'fa-font', 'Text'],
                        ['textarea', 'fa-align-left', 'Textarea'],
                        ['dropdown', 'fa-list', 'Dropdown'],
                        ['multiselect', 'fa-check-square', 'Multi'],
                        ['number', 'fa-hashtag', 'Number'],
                        ['color', 'fa-palette', 'Color'],
                        ['file', 'fa-file', 'File'],
                        ['date', 'fa-calendar', 'Date'],
                        ['boolean', 'fa-toggle-on', 'Yes/No']
                    ]; ?>
                    <?php foreach ($types as $t): ?>
                    <label class="flex flex-col items-center gap-1 p-3 bg-slate-800 border border-slate-700 rounded-lg cursor-pointer hover:border-amber-500/50 transition-colors has-[:checked]:border-amber-500 has-[:checked]:bg-amber-500/10">
                        <input type="radio" name="type" value="<?php echo $t[0]; ?>" class="hidden" <?php echo $t[0] === 'text' ? 'checked' : ''; ?>>
                        <i class="fas <?php echo $t[1]; ?> text-amber-400"></i>
                        <span class="text-xs text-slate-400"><?php echo $t[2]; ?></span>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <div id="fg-unit" class="mb-4 hidden">
                <label for="attr-unit" class="block text-xs font-medium text-slate-400 uppercase tracking-wide mb-1">Unit</label>
                <input type="text" name="unit" id="attr-unit" autocomplete="off" class="<?php echo $sinp; ?>" placeholder="e.g. kg, cm, pcs">
            </div>

            <div class="mb-4">
                <label for="attr-group" class="block text-xs font-medium text-slate-400 uppercase tracking-wide mb-1">Group</label>
                <select name="group_id" id="attr-group" class="<?php echo $sinp; ?>">
                    <option value="">-- None --</option>
                    <?php foreach ($groups as $g): ?>
                    <option value="<?php echo $g['id']; ?>"><?php echo htmlspecialchars($g['name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="mb-4">
                <label for="attr-bt" class="block text-xs font-medium text-slate-400 uppercase tracking-wide mb-1">Business Type</label>
                <select name="business_type_id" id="attr-bt" class="<?php echo $sinp; ?>">
                    <option value="">-- All --</option>
                    <?php foreach ($business_types as $bt): ?>
                    <option value="<?php echo $bt['id']; ?>"><?php echo htmlspecialchars($bt['name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="flex gap-4 mb-4">
                <div class="flex-1">
                    <label for="attr-form-sort" class="block text-xs font-medium text-slate-400 uppercase tracking-wide mb-1">Sort Order</label>
                    <input type="number" name="sort_order" id="attr-form-sort" value="0" min="0" autocomplete="off" class="<?php echo $sinp; ?>">
                </div>
                <div class="flex-1 flex items-end pb-2">
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" name="status" id="attr-status" value="1" checked class="w-3.5 h-3.5 rounded accent-amber-500">
                        <span class="text-sm text-slate-300">Active</span>
                    </label>
                </div>
            </div>

            <div class="flex flex-wrap gap-4 mb-4">
                <label class="flex items-center gap-2 cursor-pointer"><input type="checkbox" name="is_required" id="attr-required" value="1" class="w-3.5 h-3.5 rounded accent-amber-500"><span class="text-sm text-slate-300">Required</span></label>
                <label class="flex items-center gap-2 cursor-pointer"><input type="checkbox" name="is_filterable" id="attr-filterable" value="1" class="w-3.5 h-3.5 rounded accent-amber-500"><span class="text-sm text-slate-300">Filterable</span></label>
                <label class="flex items-center gap-2 cursor-pointer"><input type="checkbox" name="is_variant_forming" id="attr-variant" value="1" class="w-3.5 h-3.5 rounded accent-amber-500"><span class="text-sm text-slate-300">Variant Forming</span></label>
            </div>

            <div id="fg-values" class="mb-4 hidden">
                <span class="block text-xs font-medium text-slate-400 uppercase tracking-wide mb-2">Values</span>
                <div id="attr-values-manager" class="bg-slate-800/50 rounded-lg mb-2"></div>
                <button type="button" onclick="addValueRow()" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-slate-700 border border-slate-600 text-slate-300 text-xs font-medium hover:bg-slate-600 transition-colors">
                    <i class="fas fa-plus text-[10px]"></i> Add Value
                </button>
            </div>

            <div class="flex gap-2 pt-4 border-t border-slate-700/60">
                <button type="button" onclick="closeSlideOver()" class="flex-1 inline-flex items-center justify-center px-3 py-2 rounded-lg bg-slate-700 border border-slate-600 text-slate-300 text-sm font-medium hover:bg-slate-600 transition-colors">Cancel</button>
                <button type="button" onclick="saveAndAnother()" class="flex-1 inline-flex items-center justify-center px-3 py-2 rounded-lg bg-slate-700 border border-slate-600 text-slate-300 text-sm font-medium hover:bg-slate-600 transition-colors">Save &amp; Add Another</button>
                <button type="button" onclick="saveAttribute()" class="flex-1 inline-flex items-center justify-center px-3 py-2 rounded-lg bg-amber-500 text-black text-sm font-semibold hover:bg-amber-600 transition-colors">Save Attribute</button>
            </div>
        </form>
    </div>
</div>

<!-- Group Modal -->
<div id="attr-group-modal" class="hidden fixed inset-0 bg-black/70  z-[1002] flex items-center justify-center">
    <div class="bg-slate-800 border border-slate-700 rounded-xl w-full max-w-md mx-4 p-6 shadow-2xl">
        <h3 id="group-modal-title" class="text-base font-semibold text-white mb-4">Create New Group</h3>
        <form id="group-form" onsubmit="return false" class="space-y-4">
            <input type="hidden" name="id" id="group-form-id" value="">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
            <div>
                <label for="group-name" class="block text-xs font-medium text-slate-400 uppercase tracking-wide mb-1">Group Name</label>
                <input type="text" name="name" id="group-name" autocomplete="off" class="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-1 focus:ring-amber-500" required>
            </div>
            <div>
                <label for="group-bt" class="block text-xs font-medium text-slate-400 uppercase tracking-wide mb-1">Business Type</label>
                <select name="business_type_id" id="group-bt" class="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-white text-sm focus:outline-none focus:ring-1 focus:ring-amber-500">
                    <option value="">-- All --</option>
                    <?php foreach ($business_types as $bt): ?>
                    <option value="<?php echo $bt['id']; ?>"><?php echo htmlspecialchars($bt['name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="group-sort" class="block text-xs font-medium text-slate-400 uppercase tracking-wide mb-1">Sort Order</label>
                <input type="number" name="sort_order" id="group-sort" value="0" min="0" autocomplete="off" class="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-white text-sm focus:outline-none focus:ring-1 focus:ring-amber-500">
            </div>
            <div class="flex gap-2 pt-2">
                <button type="button" onclick="closeGroupModal()" class="flex-1 inline-flex items-center justify-center px-3 py-2 rounded-lg bg-slate-700 border border-slate-600 text-slate-300 text-sm font-medium hover:bg-slate-600 transition-colors">Cancel</button>
                <button type="button" onclick="saveGroup()" class="flex-1 inline-flex items-center justify-center px-3 py-2 rounded-lg bg-amber-500 text-black text-sm font-semibold hover:bg-amber-600 transition-colors">Save Group</button>
            </div>
        </form>
    </div>
</div>

<script>
window.ATTR_CONFIG = {
    tenant_id: <?php echo (int) $tenant_id; ?>,
    csrf_token: '<?php echo htmlspecialchars($csrf_token); ?>',
    ajax_url: 'attributes.php',
    base_url: '<?php echo base_url(); ?>',
    can_edit: <?php echo $can_edit ? 'true' : 'false'; ?>,
    can_delete: <?php echo $can_delete ? 'true' : 'false'; ?>,
    can_create: <?php echo $can_create ? 'true' : 'false'; ?>
};
</script>
<script src="<?php echo asset_url('js/attributes.js'); ?>?v=3"></script>

<?php
$page_content = ob_get_clean();
require_once __DIR__ . '/../layouts/app.php';
