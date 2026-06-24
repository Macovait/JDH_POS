<?php
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

// Get current business type for multi-tenant isolation
$business_type = get_current_business_type($tenant_id);
$business_type_id = get_current_business_type_id($tenant_id);
$user_role = $_SESSION['role'] ?? $_SESSION['user']['role'] ?? 'User';
$branch_id = get_current_branch_id();
$branch_name = get_current_branch_name();

// Check if tags tables exist, create if not
$tables_exist = false;
try {
    $check = $pdo->query(
        "SELECT 1 FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'product_tags' LIMIT 1"
    )->fetch();
    $tables_exist = !empty($check);
} catch (Exception $e) {
    $tables_exist = false;
}

if (!$tables_exist) {
    // Create product_tags table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `product_tags` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `tenant_id` bigint(20) UNSIGNED NOT NULL,
            `name` varchar(100) NOT NULL,
            `slug` varchar(100) NOT NULL,
            `color` varchar(7) DEFAULT '#3B82F6',
            `icon` varchar(50) DEFAULT 'fa-tag',
            `description` text DEFAULT NULL,
            `business_type` varchar(50) DEFAULT NULL,
            `business_type_id` int(11) DEFAULT NULL,
            `is_active` tinyint(1) DEFAULT 1,
            `sort_order` int(11) DEFAULT 0,
            `created_by` int(11) DEFAULT NULL,
            `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
            `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
            PRIMARY KEY (`id`),
            UNIQUE KEY `uk_tenant_slug` (`tenant_id`, `slug`),
            KEY `idx_tenant_active` (`tenant_id`, `is_active`),
            KEY `idx_business_type` (`business_type`),
            KEY `idx_business_type_id` (`business_type_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    // Create product_tag_relations table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `product_tag_relations` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `tenant_id` bigint(20) UNSIGNED NOT NULL,
            `product_id` int(11) NOT NULL,
            `tag_id` int(11) NOT NULL,
            `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
            PRIMARY KEY (`id`),
            UNIQUE KEY `uk_product_tag` (`tenant_id`, `product_id`, `tag_id`),
            KEY `idx_product` (`product_id`),
            KEY `idx_tag` (`tag_id`),
            CONSTRAINT `fk_pt_relations_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
            CONSTRAINT `fk_pt_relations_tag` FOREIGN KEY (`tag_id`) REFERENCES `product_tags` (`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
}

$csrf_token = generate_csrf_token();

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_action'])) {
    header('Content-Type: application/json');
    $response = ['success' => false, 'message' => ''];

    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $response['message'] = 'Invalid security token. Please refresh and try again.';
        echo json_encode($response);
        exit;
    }

    try {
        $action = $_POST['ajax_action'];

        // Create new tag
        if ($action === 'create_tag') {
            $name = trim($_POST['name'] ?? '');
            $color = trim($_POST['color'] ?? '#3B82F6');
            $icon = trim($_POST['icon'] ?? 'fa-tag');
            $description = trim($_POST['description'] ?? '');
            
            if (empty($name)) {
                throw new Exception('Tag name is required');
            }
            
            $slug = strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', $name), '-'));
            
            // Check if tag already exists
            $check = $pdo->prepare("SELECT id FROM product_tags WHERE tenant_id = ? AND slug = ?");
            $check->execute([$tenant_id, $slug]);
            if ($check->fetch()) {
                throw new Exception('A tag with this name already exists');
            }
            
            $stmt = $pdo->prepare("
                INSERT INTO product_tags (tenant_id, name, slug, color, icon, description, business_type, business_type_id, created_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $tenant_id, $name, $slug, $color, $icon, $description,
                $business_type, $business_type_id, get_current_user_id()
            ]);
            
            $response = [
                'success' => true,
                'message' => 'Tag created successfully',
                'tag' => [
                    'id' => $pdo->lastInsertId(),
                    'name' => $name,
                    'slug' => $slug,
                    'color' => $color,
                    'icon' => $icon
                ]
            ];
        }
        
        // Update tag
        elseif ($action === 'update_tag') {
            $tag_id = (int) $_POST['tag_id'];
            $name = trim($_POST['name'] ?? '');
            $color = trim($_POST['color'] ?? '#3B82F6');
            $icon = trim($_POST['icon'] ?? 'fa-tag');
            $description = trim($_POST['description'] ?? '');
            $is_active = isset($_POST['is_active']) ? (int) $_POST['is_active'] : 1;
            
            if (empty($name)) {
                throw new Exception('Tag name is required');
            }
            
            $slug = strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', $name), '-'));
            
            // Check if slug exists for another tag
            $check = $pdo->prepare("SELECT id FROM product_tags WHERE tenant_id = ? AND slug = ? AND id != ?");
            $check->execute([$tenant_id, $slug, $tag_id]);
            if ($check->fetch()) {
                throw new Exception('Another tag with this name already exists');
            }
            
            $stmt = $pdo->prepare("
                UPDATE product_tags 
                SET name = ?, slug = ?, color = ?, icon = ?, description = ?, is_active = ?, updated_at = NOW()
                WHERE id = ? AND tenant_id = ?
            ");
            $stmt->execute([$name, $slug, $color, $icon, $description, $is_active, $tag_id, $tenant_id]);
            
            $response = ['success' => true, 'message' => 'Tag updated successfully'];
        }
        
        // Delete tag
        elseif ($action === 'delete_tag') {
            $tag_id = (int) $_POST['tag_id'];
            
            // Check if tag is in use
            $check = $pdo->prepare("SELECT COUNT(*) FROM product_tag_relations WHERE tag_id = ? AND tenant_id = ?");
            $check->execute([$tag_id, $tenant_id]);
            $usage_count = $check->fetchColumn();
            
            if ($usage_count > 0) {
                throw new Exception("Cannot delete tag: it is used on {$usage_count} product(s). Remove the tag from products first.");
            }
            
            $stmt = $pdo->prepare("DELETE FROM product_tags WHERE id = ? AND tenant_id = ?");
            $stmt->execute([$tag_id, $tenant_id]);
            
            $response = ['success' => true, 'message' => 'Tag deleted successfully'];
        }
        
        // Assign tags to product
        elseif ($action === 'assign_tags') {
            $product_id = (int) $_POST['product_id'];
            $tag_ids = isset($_POST['tag_ids']) ? array_map('intval', (array)$_POST['tag_ids']) : [];
            
            // Verify product belongs to tenant
            $check = $pdo->prepare("SELECT id FROM products WHERE id = ? AND tenant_id = ?");
            $check->execute([$product_id, $tenant_id]);
            if (!$check->fetch()) {
                throw new Exception('Product not found or access denied');
            }
            
            // Remove existing relations
            $pdo->prepare("DELETE FROM product_tag_relations WHERE product_id = ? AND tenant_id = ?")->execute([$product_id, $tenant_id]);
            
            // Add new relations
            if (!empty($tag_ids)) {
                $stmt = $pdo->prepare("INSERT INTO product_tag_relations (tenant_id, product_id, tag_id) VALUES (?, ?, ?)");
                foreach ($tag_ids as $tag_id) {
                    $stmt->execute([$tenant_id, $product_id, $tag_id]);
                }
            }
            
            $response = ['success' => true, 'message' => 'Tags updated successfully'];
        }
        
        // Get product tags
        elseif ($action === 'get_product_tags') {
            $product_id = (int) $_POST['product_id'];
            
            $stmt = $pdo->prepare("
                SELECT t.id, t.name, t.color, t.icon
                FROM product_tags t
                INNER JOIN product_tag_relations r ON r.tag_id = t.id
                WHERE r.product_id = ? AND r.tenant_id = ? AND t.is_active = 1
                ORDER BY t.name
            ");
            $stmt->execute([$product_id, $tenant_id]);
            $tags = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $response = ['success' => true, 'tags' => $tags];
        }
        
        // Bulk assign tags
        elseif ($action === 'bulk_assign_tags') {
            $product_ids = isset($_POST['product_ids']) ? array_map('intval', (array)$_POST['product_ids']) : [];
            $tag_ids = isset($_POST['tag_ids']) ? array_map('intval', (array)$_POST['tag_ids']) : [];
            $action_type = $_POST['bulk_action'] ?? 'add'; // add, remove, set
            
            if (empty($product_ids)) {
                throw new Exception('No products selected');
            }
            
            $placeholders = implode(',', array_fill(0, count($product_ids), '?'));
            
            if ($action_type === 'add') {
                // Add tags to selected products
                foreach ($product_ids as $product_id) {
                    foreach ($tag_ids as $tag_id) {
                        $check = $pdo->prepare("SELECT 1 FROM product_tag_relations WHERE product_id = ? AND tag_id = ? AND tenant_id = ?");
                        $check->execute([$product_id, $tag_id, $tenant_id]);
                        if (!$check->fetch()) {
                            $pdo->prepare("INSERT INTO product_tag_relations (tenant_id, product_id, tag_id) VALUES (?, ?, ?)")
                                ->execute([$tenant_id, $product_id, $tag_id]);
                        }
                    }
                }
                $response = ['success' => true, 'message' => 'Tags added to ' . count($product_ids) . ' products'];
            } 
            elseif ($action_type === 'remove') {
                // Remove tags from selected products
                $placeholders_tags = implode(',', array_fill(0, count($tag_ids), '?'));
                $stmt = $pdo->prepare("
                    DELETE FROM product_tag_relations 
                    WHERE product_id IN ($placeholders) 
                    AND tag_id IN ($placeholders_tags)
                    AND tenant_id = ?
                ");
                $params = array_merge($product_ids, $tag_ids, [$tenant_id]);
                $stmt->execute($params);
                $response = ['success' => true, 'message' => 'Tags removed from ' . count($product_ids) . ' products'];
            }
            elseif ($action_type === 'set') {
                // Replace all tags for selected products
                foreach ($product_ids as $product_id) {
                    $pdo->prepare("DELETE FROM product_tag_relations WHERE product_id = ? AND tenant_id = ?")->execute([$product_id, $tenant_id]);
                    foreach ($tag_ids as $tag_id) {
                        $pdo->prepare("INSERT INTO product_tag_relations (tenant_id, product_id, tag_id) VALUES (?, ?, ?)")
                            ->execute([$tenant_id, $product_id, $tag_id]);
                    }
                }
                $response = ['success' => true, 'message' => 'Tags set for ' . count($product_ids) . ' products'];
            }
            else {
                throw new Exception('Invalid bulk action');
            }
        }
        
        // Search tags (autocomplete)
        elseif ($action === 'search_tags') {
            $search = trim($_POST['search'] ?? '');
            if (strlen($search) < 1) {
                $response = ['success' => true, 'tags' => []];
            } else {
                $stmt = $pdo->prepare("
                    SELECT id, name, color, icon 
                    FROM product_tags 
                    WHERE tenant_id = ? 
                    AND is_active = 1 
                    AND (name LIKE ? OR slug LIKE ?)
                    ORDER BY name LIMIT 20
                ");
                $search_param = "%$search%";
                $stmt->execute([$tenant_id, $search_param, $search_param]);
                $response = ['success' => true, 'tags' => $stmt->fetchAll()];
            }
        }
        
        // Get tag usage stats
        elseif ($action === 'get_stats') {
            $stmt = $pdo->prepare("
                SELECT
                    COUNT(*) as total_tags,
                    SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active_tags,
                    (SELECT COUNT(DISTINCT product_id) FROM product_tag_relations WHERE tenant_id = ?) as tagged_products,
                    (SELECT COUNT(*) FROM product_tag_relations WHERE tenant_id = ?) as total_assignments
                FROM product_tags
                WHERE tenant_id = ?
            ");
            $stmt->execute([$tenant_id, $tenant_id, $tenant_id]);
            $stats = $stmt->fetch(PDO::FETCH_ASSOC);
            $response = ['success' => true, 'stats' => $stats];
        }

        // Quick view: products with this tag
        elseif ($action === 'quick_view') {
            $tag_id = (int) ($_POST['tag_id'] ?? 0);
            if (!$tag_id) throw new Exception('Tag ID required');
            $tagStmt = $pdo->prepare("SELECT id, name, color, icon, description FROM product_tags WHERE id = ? AND tenant_id = ? LIMIT 1");
            $tagStmt->execute([$tag_id, $tenant_id]);
            $tag = $tagStmt->fetch();
            if (!$tag) throw new Exception('Tag not found');
            $prodStmt = $pdo->prepare("
                SELECT p.id, p.name, p.sku, p.price, p.image
                FROM products p
                JOIN product_tag_relations r ON p.id = r.product_id
                WHERE r.tag_id = ? AND r.tenant_id = ? AND p.deleted_at IS NULL
                ORDER BY p.name LIMIT 50
            ");
            $prodStmt->execute([$tag_id, $tenant_id]);
            $products = $prodStmt->fetchAll();
            $response = ['success' => true, 'tag' => $tag, 'products' => $products];
        }

        // Duplicate tag
        elseif ($action === 'duplicate') {
            $tag_id = (int) ($_POST['tag_id'] ?? 0);
            if (!$tag_id) throw new Exception('Tag ID required');
            $stmt = $pdo->prepare("SELECT * FROM product_tags WHERE id = ? AND tenant_id = ? LIMIT 1");
            $stmt->execute([$tag_id, $tenant_id]);
            $tag = $stmt->fetch();
            if (!$tag) throw new Exception('Tag not found');
            $tag['name'] = $tag['name'] . ' (Copy)';
            $base = strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', $tag['name']), '-'));
            $slug = $base;
            $i = 1;
            $checkStmt = $pdo->prepare("SELECT 1 FROM product_tags WHERE tenant_id = ? AND slug = ? LIMIT 1");
            $checkStmt->execute([$tenant_id, $slug]);
            while ($checkStmt->fetch()) {
                $slug = $base . '-' . $i;
                $i++;
                $checkStmt->execute([$tenant_id, $slug]);
            }
            $tag['slug'] = $slug;
            unset($tag['id'], $tag['created_at'], $tag['updated_at']);
            $cols = implode(', ', array_map(fn($c) => "`$c`", array_keys($tag)));
            $vals = array_values($tag);
            $ph = implode(', ', array_fill(0, count($vals), '?'));
            $pdo->prepare("INSERT INTO product_tags ($cols, created_at, updated_at) VALUES ($ph, NOW(), NOW())")->execute($vals);
            $response = ['success' => true, 'message' => 'Tag duplicated', 'new_id' => $pdo->lastInsertId()];
        }

    } catch (Exception $e) {
        $response['message'] = $e->getMessage();
    }
    
    echo json_encode($response);
    exit;
}

// Get all tags for the tenant
$tags = [];
$stmt = $pdo->prepare("
    SELECT t.*, COUNT(r.product_id) as usage_count
    FROM product_tags t
    LEFT JOIN product_tag_relations r ON r.tag_id = t.id AND r.tenant_id = t.tenant_id
    WHERE t.tenant_id = ?
    GROUP BY t.id
    ORDER BY t.sort_order ASC, t.name ASC
");
$stmt->execute([$tenant_id]);
$tags = $stmt->fetchAll();

// Get products for tag assignment modal (limited to 100 for performance)
$products = [];
$stmt = $pdo->prepare("
    SELECT id, name, sku 
    FROM products 
    WHERE tenant_id = ? AND active = 1 AND deleted_at IS NULL
    ORDER BY name LIMIT 100
");
$stmt->execute([$tenant_id]);
$products = $stmt->fetchAll();

// Predefined color options
$color_options = [
    '#3B82F6' => 'Blue',
    '#10B981' => 'Green',
    '#F59E0B' => 'Amber',
    '#EF4444' => 'Red',
    '#8B5CF6' => 'Purple',
    '#EC4899' => 'Pink',
    '#06B6D4' => 'Cyan',
    '#F97316' => 'Orange',
    '#6B7280' => 'Gray',
    '#FBBF24' => 'Yellow'
];

// Predefined icon options
$icon_options = [
    'fa-tag' => 'Tag',
    'fa-star' => 'Star',
    'fa-fire' => 'Fire',
    'fa-snowflake' => 'Snowflake',
    'fa-gift' => 'Gift',
    'fa-bolt' => 'Bolt',
    'fa-heart' => 'Heart',
    'fa-clock' => 'Clock',
    'fa-percent' => 'Percent',
    'fa-trophy' => 'Trophy',
    'fa-leaf' => 'Leaf',
    'fa-crown' => 'Crown',
    'fa-rocket' => 'Rocket',
    'fa-moon' => 'Moon',
    'fa-sun' => 'Sun'
];

$page_title = 'Product Tags | ' . ($branch_name ?? 'Jakababa POS');
ob_start();
?>

<div id="tags-content">

<div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-5">
    <div>
        <p class="text-xs font-medium text-amber-400 uppercase tracking-wide mb-0.5">Organization</p>
        <h1 class="text-lg font-bold text-white">Product Tags</h1>
        <p class="text-sm text-slate-500 mt-0.5">Manage tags to organize and filter products</p>
    </div>
    <div class="flex flex-wrap gap-2">
        <a href="tag_analytics.php" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-slate-700 border border-slate-600 text-slate-300 text-sm font-medium hover:bg-slate-600 transition-colors">
            <i class="fas fa-chart-bar text-xs"></i> Analytics
        </a>
        <button onclick="exportTags()" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-slate-700 border border-slate-600 text-slate-300 text-sm font-medium hover:bg-slate-600 transition-colors">
            <i class="fas fa-download text-xs"></i> Export
        </button>
        <button onclick="openBulkAssignModal()" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-slate-700 border border-slate-600 text-slate-300 text-sm font-medium hover:bg-slate-600 transition-colors">
            <i class="fas fa-tags text-xs"></i> Bulk Assign
        </button>
        <button onclick="openTagModal()" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-amber-500 text-black text-sm font-semibold hover:bg-amber-600 transition-colors">
            <i class="fas fa-plus text-xs"></i> Create Tag
        </button>
    </div>
</div>

<!-- Stats Cards -->
<?php
$total_tags = count($tags);
$active_tags = count(array_filter($tags, fn($t) => $t['is_active'] == 1));
$tagged_products = array_sum(array_column($tags, 'usage_count'));
$total_assignments = $tagged_products;
?>
<div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-5">
    <?php foreach ([['tags','amber-400','bg-amber-500/10','Total Tags',$total_tags,'text-white'],['check-circle','emerald-400','bg-emerald-500/10','Active Tags',$active_tags,'text-emerald-400'],['box','blue-400','bg-blue-500/10','Tagged Products',$tagged_products,'text-blue-400'],['link','purple-400','bg-purple-500/10','Total Assignments',$total_assignments,'text-purple-400']] as [$icon,$color,$bg,$label,$val,$textColor]): ?>
    <div class="bg-slate-800/50 border border-slate-700/60 rounded-xl p-3 flex items-center gap-2.5">
        <div class="w-8 h-8 rounded-lg <?php echo $bg; ?> flex items-center justify-center shrink-0"><i class="fas fa-<?php echo $icon; ?> text-<?php echo $color; ?> text-xs"></i></div>
        <div><div class="text-xl font-bold <?php echo $textColor; ?>"><?php echo $val; ?></div><div class="text-[10px] text-slate-500 uppercase tracking-wide"><?php echo $label; ?></div></div>
    </div>
    <?php endforeach; ?>
</div>

<!-- Tags Grid -->
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 mb-5">
    <?php foreach ($tags as $tag): 
        $usage_percent = $tagged_products > 0 ? min(100, round(($tag['usage_count'] / max(1, $tagged_products)) * 100)) : 0;
    ?>
    <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-4 hover:border-amber-500/40 transition-colors" data-tag-id="<?php echo $tag['id']; ?>"
         data-name="<?php echo htmlspecialchars($tag['name']); ?>"
         data-slug="<?php echo htmlspecialchars($tag['slug']); ?>"
         data-color="<?php echo htmlspecialchars($tag['color']); ?>"
         data-icon="<?php echo htmlspecialchars($tag['icon']); ?>"
         data-description="<?php echo htmlspecialchars($tag['description'] ?? ''); ?>"
         data-active="<?php echo $tag['is_active']; ?>">
        <div class="flex items-start justify-between mb-3">
            <div class="flex items-center gap-2 flex-wrap">
                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium" style="background: <?php echo $tag['color']; ?>20; color: <?php echo $tag['color']; ?>;">
                    <i class="fas <?php echo htmlspecialchars($tag['icon']); ?> text-[10px]"></i>
                    <?php echo htmlspecialchars($tag['name']); ?>
                </span>
                <?php if (!$tag['is_active']): ?>
                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-medium bg-amber-500/15 text-amber-400 border border-amber-500/30"><i class="fas fa-ban"></i> Inactive</span>
                <?php endif; ?>
            </div>
            <div class="flex items-center gap-1">
                <button onclick="openTagQuickView(<?php echo $tag['id']; ?>)" title="Quick View" class="w-7 h-7 inline-flex items-center justify-center rounded-lg bg-slate-700/60 text-slate-400 hover:text-white hover:bg-slate-700 transition-colors">
                    <i class="fas fa-eye text-[10px]"></i>
                </button>
                <button onclick="editTag(<?php echo $tag['id']; ?>)" title="Edit Tag" class="w-7 h-7 inline-flex items-center justify-center rounded-lg bg-slate-700/60 text-slate-400 hover:text-amber-400 hover:bg-slate-700 transition-colors">
                    <i class="fas fa-pen text-[10px]"></i>
                </button>
                <button onclick="duplicateTag(<?php echo $tag['id']; ?>)" title="Duplicate" class="w-7 h-7 inline-flex items-center justify-center rounded-lg bg-slate-700/60 text-slate-400 hover:text-amber-400 hover:bg-amber-500/10 transition-colors">
                    <i class="fas fa-clone text-[10px]"></i>
                </button>
                <?php if ($tag['usage_count'] == 0): ?>
                <button onclick="deleteTag(<?php echo $tag['id']; ?>, '<?php echo htmlspecialchars($tag['name']); ?>')" title="Delete Tag" class="w-7 h-7 inline-flex items-center justify-center rounded-lg bg-slate-700/60 text-slate-400 hover:text-red-400 hover:bg-red-500/10 transition-colors">
                    <i class="fas fa-trash text-[10px]"></i>
                </button>
                <?php endif; ?>
            </div>
        </div>
        <?php if (!empty($tag['description'])): ?>
        <p class="text-xs text-slate-500 mb-3"><?php echo htmlspecialchars($tag['description']); ?></p>
        <?php endif; ?>
        <div class="flex items-center justify-between text-xs">
            <span class="text-slate-500"><i class="fas fa-box"></i> <?php echo $tag['usage_count']; ?> product<?php echo $tag['usage_count'] != 1 ? 's' : ''; ?></span>
            <button onclick="assignTagToProducts(<?php echo $tag['id']; ?>, '<?php echo htmlspecialchars($tag['name']); ?>')" class="text-amber-400 hover:text-amber-300 transition-colors text-xs">
                <i class="fas fa-plus"></i> Assign
            </button>
        </div>
        <?php if ($usage_percent > 0): ?>
        <div class="mt-2 bg-slate-700/60 rounded h-1 overflow-hidden">
            <div class="bg-amber-500 h-full rounded" style="width: <?php echo $usage_percent; ?>%"></div>
        </div>
        <?php endif; ?>
    </div>
    <?php endforeach; ?>

    <?php if (empty($tags)): ?>
    <div class="col-span-full py-14 text-center">
        <i class="fas fa-tags text-4xl text-slate-700 mb-3 block"></i>
        <p class="text-slate-400 font-medium mb-1">No tags yet</p>
        <p class="text-slate-500 text-sm mb-4">Create tags to organize your products</p>
        <button onclick="openTagModal()" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-amber-500/15 border border-amber-500/40 text-amber-400 text-sm font-semibold hover:bg-amber-500/25 transition-colors">
            <i class="fas fa-plus text-xs"></i> Create First Tag
        </button>
    </div>
    <?php endif; ?>
</div>

<!-- Tag Cloud Section -->
<div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-4">
    <h3 class="text-sm font-semibold text-white mb-3"><i class="fas fa-cloud text-amber-400 mr-2"></i> Tag Cloud</h3>
    <div class="flex flex-wrap gap-2 bg-slate-900/40 rounded-lg p-3" id="tagCloud">
        <?php foreach ($tags as $tag): 
            $size = 12 + min(20, floor($tag['usage_count'] / max(1, $tagged_products) * 20));
        ?>
        <span class="px-2.5 py-1 rounded-full border border-slate-700 bg-slate-800/60 text-slate-400 cursor-pointer hover:bg-amber-500 hover:text-slate-900 hover:border-amber-500 transition-colors"
              style="font-size: <?php echo $size; ?>px"
              onclick="filterByTag(<?php echo $tag['id']; ?>, '<?php echo htmlspecialchars($tag['name']); ?>')">
            <i class="fas <?php echo htmlspecialchars($tag['icon']); ?>"></i>
            <?php echo htmlspecialchars($tag['name']); ?>
            <span class="text-xs opacity-75 ml-1">(<?php echo $tag['usage_count']; ?>)</span>
        </span>
        <?php endforeach; ?>
    </div>
</div>

<!-- ============================================ -->
<!-- MODALS -->
<!-- ============================================ -->

<?php
$minp = 'w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-1 focus:ring-amber-500 transition-colors';
$mlbl = 'block text-xs font-medium text-slate-400 uppercase tracking-wide mb-1';
?>

<!-- Create/Edit Tag Modal -->
<div id="tagModal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/60 ">
    <div class="bg-slate-800 border border-slate-700 rounded-xl p-6 w-full max-w-lg mx-4 shadow-2xl max-h-[90vh] overflow-y-auto">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-base font-semibold text-white" id="tagModalTitle">Create New Tag</h3>
            <button onclick="closeTagModal()" class="text-slate-400 hover:text-white transition-colors">&times;</button>
        </div>
        <form id="tagForm" onsubmit="saveTag(event)" class="space-y-4">
            <input type="hidden" id="tagId" name="tag_id" value="">
            <div>
                <label class="<?php echo $mlbl; ?>">Tag Name *</label>
                <input type="text" id="tagName" name="name" class="<?php echo $minp; ?>" required placeholder="e.g., Best Seller">
            </div>
            <div>
                <label class="<?php echo $mlbl; ?>">Color</label>
                <div class="flex flex-wrap gap-2">
                    <?php foreach ($color_options as $color => $name): ?>
                    <button type="button" onclick="selectColor('<?php echo $color; ?>')" 
                            class="w-8 h-8 rounded-full border-2 border-transparent hover:border-white transition-colors"
                            style="background: <?php echo $color; ?>" data-color="<?php echo $color; ?>"></button>
                    <?php endforeach; ?>
                </div>
                <input type="hidden" id="tagColor" name="color" value="#3B82F6">
            </div>
            <div>
                <label class="<?php echo $mlbl; ?>">Icon</label>
                <div class="grid grid-cols-5 gap-2 mb-2" id="iconGrid">
                    <?php foreach ($icon_options as $icon => $label): ?>
                    <button type="button" onclick="selectIcon('<?php echo $icon; ?>')" 
                            class="p-2 rounded-lg border border-slate-600 bg-slate-900/50 hover:border-amber-500/50 transition-colors text-center text-slate-300"
                            data-icon="<?php echo $icon; ?>">
                        <i class="fas <?php echo $icon; ?> text-amber-400"></i>
                        <span class="text-xs block mt-1 text-slate-500"><?php echo $label; ?></span>
                    </button>
                    <?php endforeach; ?>
                </div>
                <input type="hidden" id="tagIcon" name="icon" value="fa-tag">
            </div>
            <div>
                <label class="<?php echo $mlbl; ?>">Description</label>
                <textarea id="tagDescription" name="description" class="<?php echo $minp; ?> resize-none" rows="2" placeholder="Optional description"></textarea>
            </div>
            <div>
                <label class="flex items-center gap-2 cursor-pointer">
                    <input type="checkbox" id="tagActive" name="is_active" class="w-3.5 h-3.5 rounded accent-amber-500" checked>
                    <span class="text-sm text-slate-400">Active (visible in POS and online store)</span>
                </label>
            </div>
            <div class="flex gap-2 pt-2">
                <button type="submit" class="flex-1 inline-flex items-center justify-center gap-2 px-3 py-2 rounded-lg bg-amber-500 text-black text-sm font-semibold hover:bg-amber-600 transition-colors">
                    <i class="fas fa-save text-xs"></i> Save Tag
                </button>
                <button type="button" onclick="closeTagModal()" class="flex-1 inline-flex items-center justify-center px-3 py-2 rounded-lg bg-slate-700 border border-slate-600 text-slate-300 text-sm font-medium hover:bg-slate-600 transition-colors">Cancel</button>
            </div>
        </form>
    </div>
</div>

<!-- Assign Tags to Products Modal -->
<div id="assignModal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/60 ">
    <div class="bg-slate-800 border border-slate-700 rounded-xl p-6 w-full max-w-xl mx-4 shadow-2xl max-h-[90vh] overflow-y-auto">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-base font-semibold text-white"><i class="fas fa-tag text-amber-400 mr-2"></i>Assign Tags to Products</h3>
            <button onclick="closeAssignModal()" class="text-slate-400 hover:text-white transition-colors">&times;</button>
        </div>
        <div id="assignModalBody" class="space-y-4">
            <div>
                <label class="<?php echo $mlbl; ?>">Select Products</label>
                <select id="assignProducts" class="<?php echo $minp; ?>" multiple size="8">
                    <?php foreach ($products as $product): ?>
                    <option value="<?php echo $product['id']; ?>"><?php echo htmlspecialchars($product['name']); ?> (<?php echo htmlspecialchars($product['sku'] ?: 'No SKU'); ?>)</option>
                    <?php endforeach; ?>
                </select>
                <p class="text-xs text-slate-500 mt-1">Hold Ctrl/Cmd to select multiple products</p>
            </div>
            <div>
                <label class="<?php echo $mlbl; ?>">Select Tags</label>
                <select id="assignTags" class="<?php echo $minp; ?>" multiple size="6">
                    <?php foreach ($tags as $tag): ?>
                    <option value="<?php echo $tag['id']; ?>"><?php echo htmlspecialchars($tag['name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="<?php echo $mlbl; ?>">Action</label>
                <select id="assignAction" class="<?php echo $minp; ?>">
                    <option value="add">Add tags to selected products</option>
                    <option value="remove">Remove tags from selected products</option>
                    <option value="set">Replace all tags (only these tags)</option>
                </select>
            </div>
            <div class="flex gap-2 pt-2">
                <button onclick="confirmAssignTags()" class="flex-1 inline-flex items-center justify-center gap-2 px-3 py-2 rounded-lg bg-amber-500/15 border border-amber-500/40 text-amber-400 text-sm font-semibold hover:bg-amber-500/25 transition-colors">
                    <i class="fas fa-check text-xs"></i> Apply
                </button>
                <button onclick="closeAssignModal()" class="flex-1 inline-flex items-center justify-center px-3 py-2 rounded-lg bg-slate-700 border border-slate-600 text-slate-300 text-sm font-medium hover:bg-slate-600 transition-colors">Cancel</button>
            </div>
        </div>
    </div>
</div>

<!-- Single Tag Assignment Modal -->
<div id="singleAssignModal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/60 ">
    <div class="bg-slate-800 border border-slate-700 rounded-xl p-6 w-full max-w-lg mx-4 shadow-2xl">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-base font-semibold text-white" id="singleAssignTitle">Assign Tag to Products</h3>
            <button onclick="closeSingleAssignModal()" class="text-slate-400 hover:text-white transition-colors">&times;</button>
        </div>
        <div id="singleAssignBody" class="space-y-4">
            <p class="text-sm text-slate-400">Tag: <strong id="singleAssignTagName" class="text-amber-400"></strong></p>
            <div>
                <label class="<?php echo $mlbl; ?>">Select Products</label>
                <input type="text" id="singleAssignSearch" placeholder="Search products..." class="<?php echo $minp; ?> mb-2" onkeyup="filterProductList()">
                <div id="singleAssignProductList" class="border border-slate-700 rounded-lg max-h-64 overflow-y-auto divide-y divide-slate-700/40">
                    <?php foreach ($products as $product): ?>
                    <label class="flex items-center gap-2 px-3 py-2 hover:bg-slate-700/40 cursor-pointer product-item transition-colors" data-name="<?php echo strtolower(htmlspecialchars($product['name'])); ?>">
                        <input type="checkbox" value="<?php echo $product['id']; ?>" class="single-product-checkbox w-3.5 h-3.5 rounded accent-amber-500">
                        <span class="text-sm text-slate-200"><?php echo htmlspecialchars($product['name']); ?></span>
                        <span class="text-xs text-slate-500 ml-auto"><?php echo htmlspecialchars($product['sku'] ?: ''); ?></span>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="flex gap-2">
                <button onclick="confirmSingleAssign()" class="flex-1 inline-flex items-center justify-center gap-2 px-3 py-2 rounded-lg bg-amber-500/15 border border-amber-500/40 text-amber-400 text-sm font-semibold hover:bg-amber-500/25 transition-colors">
                    <i class="fas fa-tag text-xs"></i> Assign Tag
                </button>
                <button onclick="closeSingleAssignModal()" class="flex-1 inline-flex items-center justify-center px-3 py-2 rounded-lg bg-slate-700 border border-slate-600 text-slate-300 text-sm font-medium hover:bg-slate-600 transition-colors">Cancel</button>
            </div>
        </div>
    </div>
</div>

<!-- Quick View Modal -->
<div id="quickViewModal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/60 ">
    <div class="bg-slate-800 border border-slate-700 rounded-xl p-5 w-full max-w-md mx-4 shadow-xl max-h-[85vh] overflow-y-auto">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-sm font-semibold text-white">Tag Details</h3>
            <button onclick="closeQuickViewModal()" class="w-6 h-6 flex items-center justify-center rounded-lg bg-slate-700 text-slate-400 hover:text-white transition-colors"><i class="fas fa-times text-xs"></i></button>
        </div>
        <div class="space-y-4">
            <div class="flex items-center gap-2">
                <span id="qvTagBadge" class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium"></span>
                <span id="qvTagStatus" class="text-xs"></span>
            </div>
            <p id="qvTagDesc" class="text-xs text-slate-400 hidden"></p>
            <div>
                <div class="text-xs text-slate-500 mb-2">Products with this tag</div>
                <div id="qvProducts" class="space-y-1.5 max-h-64 overflow-y-auto"></div>
            </div>
        </div>
        <div class="flex gap-2 mt-4 pt-3 border-t border-slate-700/60">
            <a id="qvProductsLink" href="#" class="flex-1 inline-flex items-center justify-center gap-1.5 px-3 py-1.5 rounded-lg bg-amber-500/15 border border-amber-500/30 text-amber-400 text-xs font-medium hover:bg-amber-500/25 transition-colors"><i class="fas fa-box text-xs"></i> View Products</a>
            <button onclick="closeQuickViewModal()" class="flex-1 px-3 py-1.5 rounded-lg bg-slate-700 border border-slate-600 text-slate-300 text-xs font-medium hover:bg-slate-600 transition-colors">Close</button>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div id="deleteModal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/60 ">
    <div class="bg-slate-800 border border-slate-700 rounded-xl p-6 w-full max-w-sm mx-4 shadow-2xl">
        <div class="flex items-center gap-3 mb-4">
            <div class="w-10 h-10 rounded-full bg-red-500/10 border border-red-500/30 flex items-center justify-center flex-shrink-0"><i class="fas fa-trash text-red-400 text-sm"></i></div>
            <h3 class="text-base font-semibold text-white">Delete Tag</h3>
        </div>
        <p class="text-slate-400 text-sm mb-1">Are you sure you want to delete</p>
        <p id="deleteTagName" class="font-semibold text-amber-400 mb-4"></p>
        <div class="flex gap-2">
            <button onclick="confirmDeleteTag()" class="flex-1 inline-flex items-center justify-center gap-2 px-3 py-2 rounded-lg bg-red-500/10 border border-red-500/30 text-red-400 text-sm font-medium hover:bg-red-500/20 transition-colors">
                <i class="fas fa-trash text-xs"></i> Delete
            </button>
            <button onclick="closeDeleteModal()" class="flex-1 inline-flex items-center justify-center px-3 py-2 rounded-lg bg-slate-700 border border-slate-600 text-slate-300 text-sm font-medium hover:bg-slate-600 transition-colors">Cancel</button>
        </div>
    </div>
</div>

<!-- Toast Container -->
<div id="toastContainer" class="fixed bottom-4 right-4 z-[2000] flex flex-col gap-2"></div>

<script>
const CSRF_TOKEN = '<?php echo htmlspecialchars($csrf_token); ?>';
let currentDeleteTagId = null;
let currentSingleAssignTagId = null;

// Toast notifications
function showToast(message, type = 'info') {
    const container = document.getElementById('toastContainer');
    const toast = document.createElement('div');
    const colors = {success:'bg-emerald-800/95 border-l-4 border-emerald-400 text-white',error:'bg-red-800/95 border-l-4 border-red-400 text-white',warning:'bg-amber-800/95 border-l-4 border-amber-400 text-white',info:'bg-slate-800 border-l-4 border-blue-400 text-slate-200'};
    toast.className = `${colors[type]||colors.info} px-4 py-3 rounded-lg text-sm font-medium shadow-2xl`;
    toast.innerHTML = `<i class="fas ${type==='success'?'fa-check-circle':type==='error'?'fa-exclamation-circle':'fa-info-circle'} mr-2"></i>${message}`;
    container.appendChild(toast);
    setTimeout(() => { toast.style.opacity='0'; toast.style.transition='opacity 0.3s'; setTimeout(()=>toast.remove(),300); }, 3000);
}

// Tag CRUD operations
function openTagModal(tagId = null) {
    if (tagId) {
        document.getElementById('tagModalTitle').textContent = 'Edit Tag';
        const card = document.querySelector(`[data-tag-id="${tagId}"]`);
        if (card) {
            document.getElementById('tagId').value = tagId;
            document.getElementById('tagName').value = card.dataset.name;
            document.getElementById('tagColor').value = card.dataset.color;
            document.getElementById('tagIcon').value = card.dataset.icon;
            document.getElementById('tagDescription').value = card.dataset.description || '';
            document.getElementById('tagActive').checked = card.dataset.active == '1';
            document.querySelectorAll('[data-color]').forEach(btn => {
                btn.classList.toggle('border-white', btn.dataset.color === card.dataset.color);
            });
            document.querySelectorAll('[data-icon]').forEach(btn => {
                btn.classList.toggle('border-amber-400', btn.dataset.icon === card.dataset.icon);
            });
        }
    } else {
        document.getElementById('tagModalTitle').textContent = 'Create New Tag';
        document.getElementById('tagForm').reset();
        document.getElementById('tagId').value = '';
        document.getElementById('tagColor').value = '#3B82F6';
        document.getElementById('tagIcon').value = 'fa-tag';
        document.getElementById('tagActive').checked = true;
        // Reset highlights
        document.querySelectorAll('[data-color]').forEach(btn => btn.classList.remove('border-white'));
        document.querySelector('[data-color="#3B82F6"]')?.classList.add('border-white');
        document.querySelectorAll('[data-icon]').forEach(btn => btn.classList.remove('border-amber-400'));
        document.querySelector('[data-icon="fa-tag"]')?.classList.add('border-amber-400');
    }
    document.getElementById('tagModal').classList.remove('hidden');
}

function closeTagModal() {
    document.getElementById('tagModal').classList.add('hidden');
}

function selectColor(color) {
    document.getElementById('tagColor').value = color;
    document.querySelectorAll('[data-color]').forEach(btn => {
        btn.classList.toggle('border-white', btn.dataset.color === color);
    });
}

function selectIcon(icon) {
    document.getElementById('tagIcon').value = icon;
    document.querySelectorAll('[data-icon]').forEach(btn => {
        btn.classList.toggle('border-amber-400', btn.dataset.icon === icon);
    });
}

function saveTag(event) {
    event.preventDefault();
    const tagId = document.getElementById('tagId').value;
    const formData = new FormData();
    formData.append('csrf_token', CSRF_TOKEN);
    
    if (tagId) {
        formData.append('ajax_action', 'update_tag');
        formData.append('tag_id', tagId);
    } else {
        formData.append('ajax_action', 'create_tag');
    }
    
    formData.append('name', document.getElementById('tagName').value);
    formData.append('color', document.getElementById('tagColor').value);
    formData.append('icon', document.getElementById('tagIcon').value);
    formData.append('description', document.getElementById('tagDescription').value);
    formData.append('is_active', document.getElementById('tagActive').checked ? 1 : 0);
    
    fetch('product-tags.php', { method: 'POST', body: formData })
        .then(r => r.text())
        .then(text => {
            let data; try { data = JSON.parse(text.replace(/^\uFEFF/, '')); } catch(e) { showToast('Server error', 'error'); return; }
            if (data.success) {
                showToast(data.message, 'success');
                closeTagModal();
                setTimeout(() => window.location.reload(), 800);
            } else {
                showToast(data.message || 'Error saving tag', 'error');
            }
        })
        .catch(() => showToast('Network error', 'error'));
}

function editTag(tagId) {
    openTagModal(tagId);
}

function deleteTag(tagId, tagName) {
    currentDeleteTagId = tagId;
    document.getElementById('deleteTagName').textContent = tagName;
    document.getElementById('deleteModal').classList.remove('hidden');
}

function confirmDeleteTag() {
    if (!currentDeleteTagId) return;
    const formData = new FormData();
    formData.append('ajax_action', 'delete_tag');
    formData.append('tag_id', currentDeleteTagId);
    formData.append('csrf_token', CSRF_TOKEN);
    
    fetch('product-tags.php', { method: 'POST', body: formData })
        .then(r => r.text())
        .then(text => {
            let data; try { data = JSON.parse(text.replace(/^\uFEFF/, '')); } catch(e) { closeDeleteModal(); showToast('Server error', 'error'); return; }
            closeDeleteModal();
            if (data.success) {
                showToast(data.message, 'success');
                setTimeout(() => window.location.reload(), 800);
            } else {
                showToast(data.message || 'Error deleting tag', 'error');
            }
        })
        .catch(() => {
            closeDeleteModal();
            showToast('Network error', 'error');
        });
}

function closeDeleteModal() {
    document.getElementById('deleteModal').classList.add('hidden');
    currentDeleteTagId = null;
}

// Tag assignment functions
function assignTagToProducts(tagId, tagName) {
    currentSingleAssignTagId = tagId;
    document.getElementById('singleAssignTagName').textContent = tagName;
    document.getElementById('singleAssignModal').classList.remove('hidden');
    // Reset checkboxes
    document.querySelectorAll('.single-product-checkbox').forEach(cb => cb.checked = false);
    document.getElementById('singleAssignSearch').value = '';
    filterProductList();
}

function filterProductList() {
    const search = document.getElementById('singleAssignSearch').value.toLowerCase();
    document.querySelectorAll('.product-item').forEach(item => {
        const name = item.dataset.name || '';
        item.style.display = name.includes(search) ? 'flex' : 'none';
    });
}

function confirmSingleAssign() {
    if (!currentSingleAssignTagId) return;
    const selectedProducts = Array.from(document.querySelectorAll('.single-product-checkbox:checked')).map(cb => cb.value);
    if (selectedProducts.length === 0) {
        showToast('Please select at least one product', 'warning');
        return;
    }
    
    const formData = new FormData();
    formData.append('ajax_action', 'bulk_assign_tags');
    formData.append('csrf_token', CSRF_TOKEN);
    formData.append('bulk_action', 'add');
    selectedProducts.forEach(id => formData.append('product_ids[]', id));
    formData.append('tag_ids[]', currentSingleAssignTagId);
    
    fetch('product-tags.php', { method: 'POST', body: formData })
        .then(r => r.text())
        .then(text => {
            let data; try { data = JSON.parse(text.replace(/^\uFEFF/, '')); } catch(e) { showToast('Server error', 'error'); return; }
            if (data.success) {
                showToast(data.message, 'success');
                closeSingleAssignModal();
                setTimeout(() => window.location.reload(), 800);
            } else {
                showToast(data.message || 'Error assigning tags', 'error');
            }
        })
        .catch(() => showToast('Network error', 'error'));
}

function closeSingleAssignModal() {
    document.getElementById('singleAssignModal').classList.add('hidden');
    currentSingleAssignTagId = null;
}

function openBulkAssignModal() {
    document.getElementById('assignModal').classList.remove('hidden');
}

function closeAssignModal() {
    document.getElementById('assignModal').classList.add('hidden');
}

function confirmAssignTags() {
    const products = Array.from(document.getElementById('assignProducts').selectedOptions).map(opt => opt.value);
    const tags = Array.from(document.getElementById('assignTags').selectedOptions).map(opt => opt.value);
    const action = document.getElementById('assignAction').value;
    
    if (products.length === 0) {
        showToast('Please select at least one product', 'warning');
        return;
    }
    if (tags.length === 0) {
        showToast('Please select at least one tag', 'warning');
        return;
    }
    
    const formData = new FormData();
    formData.append('ajax_action', 'bulk_assign_tags');
    formData.append('csrf_token', CSRF_TOKEN);
    formData.append('bulk_action', action);
    products.forEach(id => formData.append('product_ids[]', id));
    tags.forEach(id => formData.append('tag_ids[]', id));
    
    fetch('product-tags.php', { method: 'POST', body: formData })
        .then(r => r.text())
        .then(text => {
            let data; try { data = JSON.parse(text.replace(/^\uFEFF/, '')); } catch(e) { showToast('Server error', 'error'); return; }
            if (data.success) {
                showToast(data.message, 'success');
                closeAssignModal();
                setTimeout(() => window.location.reload(), 800);
            } else {
                showToast(data.message || 'Error updating tags', 'error');
            }
        })
        .catch(() => showToast('Network error', 'error'));
}

function openTagQuickView(tagId) {
    const formData = new FormData();
    formData.append('ajax_action', 'quick_view');
    formData.append('tag_id', tagId);
    formData.append('csrf_token', CSRF_TOKEN);
    fetch('product-tags.php', { method: 'POST', body: formData })
        .then(r => r.text())
        .then(text => {
            let data; try { data = JSON.parse(text.replace(/^\uFEFF/, '')); } catch(e) { showToast('Server error', 'error'); return; }
            if (!data.success) { showToast(data.message || 'Error', 'error'); return; }
            const t = data.tag;
            const badge = document.getElementById('qvTagBadge');
            badge.style.background = t.color + '20';
            badge.style.color = t.color;
            badge.innerHTML = '<i class="fas ' + t.icon + ' text-[10px]"></i> ' + t.name;
            const status = document.getElementById('qvTagStatus');
            status.textContent = t.is_active ? 'Active' : 'Inactive';
            status.className = 'text-xs font-medium ' + (t.is_active ? 'text-emerald-400' : 'text-slate-400');
            const desc = document.getElementById('qvTagDesc');
            if (t.description) { desc.textContent = t.description; desc.classList.remove('hidden'); } else { desc.classList.add('hidden'); }
            const prodDiv = document.getElementById('qvProducts');
            prodDiv.innerHTML = '';
            if (data.products.length === 0) {
                prodDiv.innerHTML = '<div class="text-xs text-slate-500 text-center py-2">No products with this tag</div>';
            } else {
                data.products.forEach(p => {
                    const img = p.image ? '<img src="<?php echo base_url(''); ?>' + p.image.replace(/^\//, '').replace(/^public\//, '') + '" class="w-8 h-8 rounded-lg object-cover">' : '<div class="w-8 h-8 rounded-lg bg-slate-700 flex items-center justify-center text-slate-500"><i class="fas fa-cube text-xs"></i></div>';
                    prodDiv.innerHTML += '<div class="flex items-center gap-2 bg-slate-900/40 rounded-lg p-1.5 border border-slate-700/30"><div class="shrink-0">' + img + '</div><div class="min-w-0"><div class="text-xs text-white truncate">' + p.name + '</div><div class="text-xs text-slate-500">' + (p.sku || 'No SKU') + ' &middot; KSh ' + parseFloat(p.price).toFixed(2) + '</div></div></div>';
                });
            }
            document.getElementById('qvProductsLink').href = 'products.php?tag_id=' + t.id + '&tag_name=' + encodeURIComponent(t.name);
            document.getElementById('quickViewModal').classList.remove('hidden');
        })
        .catch(() => showToast('Network error', 'error'));
}

function closeQuickViewModal() {
    document.getElementById('quickViewModal').classList.add('hidden');
}

function duplicateTag(tagId) {
    const formData = new FormData();
    formData.append('ajax_action', 'duplicate');
    formData.append('tag_id', tagId);
    formData.append('csrf_token', CSRF_TOKEN);
    fetch('product-tags.php', { method: 'POST', body: formData })
        .then(r => r.text())
        .then(text => {
            let data; try { data = JSON.parse(text.replace(/^\uFEFF/, '')); } catch(e) { showToast('Server error', 'error'); return; }
            if (data.success) { showToast(data.message, 'success'); setTimeout(() => window.location.reload(), 800); }
            else showToast(data.message || 'Error duplicating tag', 'error');
        })
        .catch(() => showToast('Network error', 'error'));
}

function filterByTag(tagId, tagName) {
    window.location.href = `products.php?tag_id=${tagId}&tag_name=${encodeURIComponent(tagName)}`;
}

function exportTags() {
    window.location.href = 'export_tags.php?format=csv';
}

// Close modals on escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        ['tagModal','assignModal','singleAssignModal','deleteModal','quickViewModal'].forEach(id => {
            document.getElementById(id)?.classList.add('hidden');
        });
    }
});

// Close modal when clicking overlay background
['tagModal','assignModal','singleAssignModal','deleteModal','quickViewModal'].forEach(id => {
    document.getElementById(id)?.addEventListener('click', function(e) {
        if (e.target === this) this.classList.add('hidden');
    });
});

// Initialize icon and color selection highlights
document.addEventListener('DOMContentLoaded', function() {
    // Set default active states
    const defaultColor = document.querySelector('[data-color="#3B82F6"]');
    if (defaultColor) defaultColor.classList.add('border-white');
    const defaultIcon = document.querySelector('[data-icon="fa-tag"]');
    if (defaultIcon) defaultIcon.classList.add('border-amber-400');
    
    // Store product names for filtering
    document.querySelectorAll('.product-item').forEach(item => {
        const nameSpan = item.querySelector('span:first-of-type');
        if (nameSpan) item.dataset.name = nameSpan.textContent.toLowerCase();
    });
});
</script>

<?php
$page_content = ob_get_clean();
require_once __DIR__ . '/../layouts/app.php';
?>