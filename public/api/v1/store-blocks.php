<?php
/**
 * Storefront Blocks API — Typed Block Renderer Data
 * Returns structured block data with JSON props for the Next.js storefront.
 */

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../../src/Security/CorsHandler.php';
\Jakababa\Security\apply_cors_headers();

if (false) { // Preflight handled by CorsHandler
    http_response_code(204);
    exit;
}

require_once __DIR__ . '/../../../src/paths.php';
load_core_files();

$pdo = get_db_connection();

function jsonResponse(array $data, int $code = 200): void {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

function jsonError(string $message, int $code = 400): void {
    http_response_code($code);
    echo json_encode(['success' => false, 'error' => $message], JSON_UNESCAPED_UNICODE);
    exit;
}

function resolveTenantId(PDO $pdo): int {
    if (!empty($_GET['tenant'])) {
        return (int) $_GET['tenant'];
    }
    if (!empty($_SERVER['HTTP_X_TENANT_ID'])) {
        return (int) $_SERVER['HTTP_X_TENANT_ID'];
    }
    $host = $_SERVER['HTTP_HOST'] ?? '';
    if ($host) {
        $row = db_fetch_one(
            "SELECT tenant_id FROM pos_domains WHERE domain = ? AND active = 1 LIMIT 1",
            [$host]
        );
        if ($row) return (int) $row['tenant_id'];
    }
    return 0;
}

// Resolve tenant
$tenantId = resolveTenantId($pdo);
if ($tenantId <= 0) {
    jsonError('Tenant required. Pass ?tenant=ID or X-Tenant-ID header.', 400);
}

// Verify tenant exists and is active
$active = db_fetch_value(
    "SELECT id FROM pos_tenants WHERE id = ? AND status IN ('active','trial') LIMIT 1",
    [$tenantId]
);
if (!$active) {
    jsonError('Store not found or inactive.', 404);
}

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

// ============================================================
// GET /store-blocks  — fetch all active blocks for a tenant
// ============================================================
if ($method === 'GET' && !$action) {
    $blocks = db_fetch_all(
        "SELECT id, tenant_id, name, type, props, content, bg_color, text_color, padding, section_class, display_order, is_active
         FROM storefront_blocks
         WHERE tenant_id = ? AND is_active = 1
         ORDER BY display_order ASC, id ASC",
        [$tenantId]
    );

    $result = [];
    foreach ($blocks as $b) {
        $props = [];
        if (!empty($b['props'])) {
            $decoded = json_decode($b['props'], true);
            if (is_array($decoded)) {
                $props = $decoded;
            }
        }

        $blockType = trim($b['type'] ?? '');
        $fallbackContent = '';

        // Backward compat: raw HTML blocks created via blocks.php
        if (empty($blockType) && !empty($b['content'])) {
            $blockType = 'raw-html';
            $fallbackContent = $b['content'];
        } elseif (!empty($b['content']) && empty($props)) {
            // If typed block has content but no props, use content as fallback
            $fallbackContent = $b['content'];
        }

        $result[] = [
            'id'            => (int) $b['id'],
            'name'          => $b['name'],
            'type'          => $blockType ?: 'text-section',
            'props'         => $props ?: (object) [],
            'bg_color'      => $b['bg_color'] ?? '#ffffff',
            'text_color'    => $b['text_color'] ?? '#1f2937',
            'padding'       => $b['padding'] ?? 'py-8',
            'section_class' => $b['section_class'] ?? '',
            'display_order' => (int) $b['display_order'],
            'is_active'     => (bool) $b['is_active'],
            '_fallback_html'=> $fallbackContent,
        ];
    }

    jsonResponse(['success' => true, 'data' => $result]);
}

// ============================================================
// Admin actions (POST) — requires session auth
// ============================================================
if ($method === 'POST') {
    // Require session auth for mutations
    if (empty($_SESSION['user_id']) || empty($_SESSION['tenant_id']) || $_SESSION['tenant_id'] != $tenantId) {
        jsonError('Authentication required.', 401);
    }

    $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
    $action = $input['action'] ?? '';

    switch ($action) {
        case 'create':
            $type = trim($input['type'] ?? 'text-section');
            $name = trim($input['name'] ?? 'Untitled Block');
            $props = isset($input['props']) ? json_encode($input['props']) : '{}';
            $order = (int) ($input['display_order'] ?? 0);
            $active = isset($input['is_active']) ? (int) $input['is_active'] : 1;

            $id = db_insert('storefront_blocks', [
                'tenant_id'     => $tenantId,
                'name'          => $name,
                'type'          => $type,
                'props'         => $props,
                'bg_color'      => $input['bg_color'] ?? '#ffffff',
                'text_color'    => $input['text_color'] ?? '#1f2937',
                'padding'       => $input['padding'] ?? 'py-8',
                'section_class' => $input['section_class'] ?? '',
                'display_order' => $order,
                'is_active'     => $active,
            ]);
            jsonResponse(['success' => true, 'data' => ['id' => $id, 'message' => 'Block created']], 201);

        case 'update':
            $blockId = (int) ($input['id'] ?? 0);
            if (!$blockId) jsonError('Block ID required.', 422);

            $update = [];
            if (isset($input['name'])) $update['name'] = $input['name'];
            if (isset($input['type'])) $update['type'] = $input['type'];
            if (isset($input['props'])) $update['props'] = json_encode($input['props']);
            if (isset($input['bg_color'])) $update['bg_color'] = $input['bg_color'];
            if (isset($input['text_color'])) $update['text_color'] = $input['text_color'];
            if (isset($input['padding'])) $update['padding'] = $input['padding'];
            if (isset($input['section_class'])) $update['section_class'] = $input['section_class'];
            if (isset($input['display_order'])) $update['display_order'] = (int) $input['display_order'];
            if (isset($input['is_active'])) $update['is_active'] = (int) $input['is_active'];

            if (empty($update)) jsonError('No fields to update.', 422);

            $affected = db_update(
                'storefront_blocks',
                $update,
                'id = ? AND tenant_id = ?',
                [$blockId, $tenantId]
            );
            if ($affected === 0) jsonError('Block not found.', 404);

            jsonResponse(['success' => true, 'data' => ['id' => $blockId, 'message' => 'Block updated']]);

        case 'delete':
            $blockId = (int) ($input['id'] ?? 0);
            if (!$blockId) jsonError('Block ID required.', 422);

            $affected = db_delete(
                'storefront_blocks',
                'id = ? AND tenant_id = ?',
                [$blockId, $tenantId]
            );
            if ($affected === 0) jsonError('Block not found.', 404);

            jsonResponse(['success' => true, 'data' => ['message' => 'Block deleted']]);

        case 'reorder':
            $orders = $input['orders'] ?? [];
            if (!is_array($orders) || empty($orders)) {
                jsonError('Orders array required.', 422);
            }

            $pdo = get_db_connection();
            $stmt = $pdo->prepare("UPDATE storefront_blocks SET display_order = ? WHERE id = ? AND tenant_id = ?");
            foreach ($orders as $item) {
                $stmt->execute([(int) $item['order'], (int) $item['id'], $tenantId]);
            }
            jsonResponse(['success' => true, 'data' => ['message' => 'Order updated']]);

        case 'toggle':
            $blockId = (int) ($input['id'] ?? 0);
            if (!$blockId) jsonError('Block ID required.', 422);

            $current = db_fetch_one(
                "SELECT is_active FROM storefront_blocks WHERE id = ? AND tenant_id = ?",
                [$blockId, $tenantId]
            );
            if (!$current) jsonError('Block not found.', 404);

            $newState = $current['is_active'] ? 0 : 1;
            db_update('storefront_blocks', ['is_active' => $newState], 'id = ?', [$blockId]);
            jsonResponse(['success' => true, 'data' => ['id' => $blockId, 'is_active' => (bool) $newState]]);

        default:
            jsonError('Unknown action: ' . $action, 400);
    }
}

jsonError('Method not allowed.', 405);
