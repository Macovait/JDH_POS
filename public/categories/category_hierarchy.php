<?php
/**
 * Category Hierarchy Page for Jakababa POS
 * Visual tree view of all categories and subcategories
 */

require_once __DIR__ . '/../../src/auth.php';
require_login();
require_once __DIR__ . '/../../src/db.php';

$page_title = 'Category Hierarchy';
ob_start();
?>

<style>
    .category-tree {
        position: relative;
    }

    .category-tree ul {
        margin-left: 2rem;
        padding-left: 1rem;
        position: relative;
    }

    .category-tree li {
        list-style: none;
        margin: 0.75rem 0;
        position: relative;
        animation: slideUp 0.3s ease-out;
    }

    .tree-connector {
        position: absolute;
        left: -1rem;
        top: 0;
        bottom: 0;
        width: 1rem;
    }

    .tree-connector::before {
        content: '';
        position: absolute;
        left: 0;
        top: 1.5rem;
        width: 1rem;
        height: 1px;
        background: #374151;
    }

    .tree-connector::after {
        content: '';
        position: absolute;
        left: 0;
        top: 0;
        bottom: 1.5rem;
        width: 1px;
        background: #374151;
    }

    li:last-child .tree-connector::after {
        display: none;
    }

    .tree-node {
        transition: all 0.3s ease;
        border: 1px solid transparent;
    }

    .tree-node:hover {
        transform: translateX(4px);
        border-color: #FBBF24;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
    }

    .level-indicator {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 24px;
        height: 24px;
        border-radius: 50%;
        font-size: 0.75rem;
        font-weight: 600;
    }

    .bg-slate-800/50 border border-slate-700/60 rounded-xl p-3 {
        transition: all 0.3s ease;
    }

    .bg-slate-800/50 border border-slate-700/60 rounded-xl p-3:hover {
        transform: translateY(-2px);
        box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.3);
    }

    .gradient-text {
        background: linear-gradient(135deg, #FBBF24 0%, #F59E0B 100%);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
    }

    .inline-flex items-center justify-center {
        display: inline-flex;
        align-items: center;
        justify-content: center;
    }

    .action-btn {
        transition: all 0.2s ease;
    }

    .action-btn:hover {
        transform: scale(1.1);
    }

    .depth-bar {
        height: 4px;
        background: linear-gradient(90deg, #FBBF24, #F59E0B);
        border-radius: 2px;
        transition: width 0.3s ease;
    }

    .orphaned {
        border-left: 3px solid #EF4444;
    }
</style>

<div class="fade-in">
<?php
$user_id = (int) ($_SESSION['user_id'] ?? 0);
$user_name = htmlspecialchars($_SESSION['user_name'] ?? 'User');
$user_role = $_SESSION['role'] ?? '';
$user_branch = (int) ($_SESSION['branch_id'] ?? 0);

function buildCategoryTree($categories, $parentId = null, $level = 0)
{
    $tree = [];
    foreach ($categories as $category) {
        if ($category['parent_id'] == $parentId) {
            $category['level'] = $level;
            $children = buildCategoryTree($categories, $category['id'], $level + 1);
            if ($children) {
                $category['children'] = $children;
            }
            $tree[] = $category;
        }
    }
    return $tree;
}

function calculateTreeStats($tree, &$stats)
{
    foreach ($tree as $category) {
        $stats['total']++;
        $stats['total_products'] += $category['product_count'];

        if (isset($category['children']) && !empty($category['children'])) {
            $stats['has_children']++;
            calculateTreeStats($category['children'], $stats);
        } else {
            $stats['leaf_nodes']++;
        }
    }
}

// Fetch all categories
$categories = [];
$categoryTree = [];
$stats = ['total' => 0, 'has_children' => 0, 'leaf_nodes' => 0, 'total_products' => 0, 'max_depth' => 0];
$max_depth = 0;

try {
    $pdo = get_db_connection();
    $stmt = $pdo->prepare("
        SELECT c.*, 
               p.name as parent_name,
               u.name as created_by_name,
               (SELECT COUNT(*) FROM products WHERE category_id = c.id AND deleted_at IS NULL) as product_count 
        FROM categories c 
        LEFT JOIN categories p ON c.parent_id = p.id
        LEFT JOIN users u ON c.created_by = u.id
        WHERE c.branch_id = ? OR c.branch_id IS NULL 
        ORDER BY 
            CASE 
                WHEN c.parent_id IS NULL THEN 0 
                ELSE 1 
            END,
            c.name
    ");
    $stmt->execute([$user_branch]);
    $categories = $stmt->fetchAll();

    $categoryTree = buildCategoryTree($categories);
    calculateTreeStats($categoryTree, $stats);

    // Calculate max depth
    function getMaxDepth($tree, $currentDepth = 1)
    {
        $max = $currentDepth;
        foreach ($tree as $node) {
            if (isset($node['children']) && !empty($node['children'])) {
                $depth = getMaxDepth($node['children'], $currentDepth + 1);
                if ($depth > $max) {
                    $max = $depth;
                }
            }
        }
        return $max;
    }
    $stats['max_depth'] = getMaxDepth($categoryTree);

} catch (PDOException $e) {
    error_log("Error fetching categories: " . $e->getMessage());
    $error = "Failed to load categories.";
}

// Get orphaned categories (if any)
$orphaned = [];
foreach ($categories as $cat) {
    if (!empty($cat['parent_id'])) {
        $parent_exists = false;
        foreach ($categories as $p) {
            if ($p['id'] == $cat['parent_id']) {
                $parent_exists = true;
                break;
            }
        }
        if (!$parent_exists) {
            $orphaned[] = $cat;
        }
    }
}
?>

<div class="fade-in">

    <!-- Main Content -->
    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-6">

        <!-- Page Header -->
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6 ">
            <div>
                <h1 class="text-2xl md:text-3xl font-bold flex items-center gap-2">
                    <span class="gradient-text">Category Hierarchy</span>
                </h1>
                <p class="text-gray-400 text-sm mt-1">Visual representation of your category structure</p>
            </div>
            <div class="flex gap-3">
                <a href="add_category.php"
                    class="bg-amber-500 hover:bg-amber-600 text-black px-4 py-2 rounded-xl transition flex items-center gap-2 text-sm font-medium">
                    <span class="inline-flex items-center justify-center w-5 h-5" data-icon="plus" data-type="outline"></span>
                    New Category
                </a>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4 mb-6 ">
            <div class="bg-slate-800/50 border border-slate-700/60 rounded-xl p-3 bg-gradient-to-br from-blue-600 to-blue-800 rounded-xl p-4 shadow-lg">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-blue-200 text-xs">Total Categories</p>
                        <p class="text-2xl font-bold text-white"><?php echo $stats['total']; ?></p>
                    </div>
                    <div class="bg-white/20 p-2 rounded-full">
                        <span class="inline-flex items-center justify-center w-6 h-6 text-white" data-icon="rectangle-stack"
                            data-type="outline"></span>
                    </div>
                </div>
            </div>

            <div class="bg-slate-800/50 border border-slate-700/60 rounded-xl p-3 bg-gradient-to-br from-purple-600 to-purple-800 rounded-xl p-4 shadow-lg">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-purple-200 text-xs">Parent Categories</p>
                        <p class="text-2xl font-bold text-white"><?php echo $stats['has_children']; ?></p>
                    </div>
                    <div class="bg-white/20 p-2 rounded-full">
                        <span class="inline-flex items-center justify-center w-6 h-6 text-white" data-icon="folder" data-type="outline"></span>
                    </div>
                </div>
            </div>

            <div class="bg-slate-800/50 border border-slate-700/60 rounded-xl p-3 bg-gradient-to-br from-accent to-orange-600 rounded-xl p-4 shadow-lg">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-orange-200 text-xs">Leaf Nodes</p>
                        <p class="text-2xl font-bold text-white"><?php echo $stats['leaf_nodes']; ?></p>
                    </div>
                    <div class="bg-white/20 p-2 rounded-full">
                        <span class="inline-flex items-center justify-center w-6 h-6 text-white" data-icon="document" data-type="outline"></span>
                    </div>
                </div>
            </div>

            <div class="bg-slate-800/50 border border-slate-700/60 rounded-xl p-3 bg-gradient-to-br from-green-600 to-green-800 rounded-xl p-4 shadow-lg">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-green-200 text-xs">Total Products</p>
                        <p class="text-2xl font-bold text-white"><?php echo $stats['total_products']; ?></p>
                    </div>
                    <div class="bg-white/20 p-2 rounded-full">
                        <span class="inline-flex items-center justify-center w-6 h-6 text-white" data-icon="cube" data-type="outline"></span>
                    </div>
                </div>
            </div>

            <div class="bg-slate-800/50 border border-slate-700/60 rounded-xl p-3 bg-gradient-to-br from-info to-info-dark rounded-xl p-4 shadow-lg">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-blue-200 text-xs">Max Depth</p>
                        <p class="text-2xl font-bold text-white"><?php echo $stats['max_depth']; ?> levels</p>
                    </div>
                    <div class="bg-white/20 p-2 rounded-full">
                        <span class="inline-flex items-center justify-center w-6 h-6 text-white" data-icon="arrows-up-down"
                            data-type="outline"></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Depth Visualization -->
        <div class="bg-slate-800/50 rounded-xl p-4 mb-6 border border-slate-700">
            <div class="flex items-center justify-between mb-2">
                <span class="text-sm text-gray-400">Category Depth Distribution</span>
                <span class="text-xs text-amber-400"><?php echo $stats['max_depth']; ?> levels max</span>
            </div>
            <div class="flex gap-1 h-2">
                <?php for ($i = 1; $i <= $stats['max_depth']; $i++):
                    $percentage = min(100, ($i / $stats['max_depth']) * 100);
                    ?>
                                <div class="depth-bar" style="width: <?php echo 100 / $stats['max_depth']; ?>%"></div>
                <?php endfor; ?>
            </div>
        </div>

        <!-- Main Tree View -->
        <div class="bg-slate-800/90  rounded-xl p-6 border border-slate-700 ">
            <?php if (empty($categoryTree)): ?>
                            <div class="text-center py-12">
                                <span class="inline-flex items-center justify-center w-20 h-20 mx-auto text-gray-600 mb-4" data-icon="sitemap" data-type="outline"></span>
                                <h3 class="text-2xl font-semibold text-gray-400 mb-2">No Categories Found</h3>
                                <p class="text-gray-500 mb-6">Create your first category to see the hierarchy.</p>
                                <a href="add_category.php" 
                                   class="inline-block bg-amber-500 hover:bg-amber-600 text-black px-6 py-3 rounded-lg transition font-medium">
                                    Create Category
                                </a>
                            </div>
            <?php else: ?>
                            <!-- Tree Legend -->
                            <div class="flex flex-wrap gap-4 mb-6 pb-4 border-b border-slate-700">
                                <div class="flex items-center gap-2">
                                    <div class="w-3 h-3 bg-amber-500 rounded-full"></div>
                                    <span class="text-xs text-gray-400">Parent Category</span>
                                </div>
                                <div class="flex items-center gap-2">
                                    <div class="w-3 h-3 bg-blue-500 rounded-full"></div>
                                    <span class="text-xs text-gray-400">Subcategory</span>
                                </div>
                                <div class="flex items-center gap-2">
                                    <div class="w-3 h-3 bg-emerald-500 rounded-full"></div>
                                    <span class="text-xs text-gray-400">Has Products</span>
                                </div>
                                <div class="flex items-center gap-2">
                                    <span class="inline-flex items-center justify-center w-4 h-4 text-blue-400" data-icon="pencil" data-type="outline"></span>
                                    <span class="text-xs text-gray-400">Edit</span>
                                </div>
                                <div class="flex items-center gap-2">
                                    <span class="inline-flex items-center justify-center w-4 h-4 text-amber-400" data-icon="plus-circle" data-type="outline"></span>
                                    <span class="text-xs text-gray-400">Add Subcategory</span>
                                </div>
                            </div>

                            <!-- Tree Container -->
                            <div class="category-tree overflow-x-auto">
                                <ul class="space-y-3 min-w-[600px]">
                                    <?php
                                    $color_map = ['#FBBF24', '#3B82F6', '#10B981', '#8B5CF6', '#EC4899', '#F97316', '#14B8A6'];
                                    $color_index = 0;
                                    ?>
                                    <?php foreach ($categoryTree as $category): ?>
                                                    <li>
                                                        <div class="tree-connector"></div>
                                                        <div class="tree-node relative bg-primary-dark/50 rounded-xl p-4 border border-slate-700">
                                                            <!-- Node Content -->
                                                            <div class="flex items-start gap-4">
                                                                <!-- Level Indicator -->
                                                                <div class="level-indicator" style="background-color: <?php echo $color_map[$color_index % count($color_map)]; ?>20; color: <?php echo $color_map[$color_index % count($color_map)]; ?>;">
                                                                    L<?php echo $category['level'] + 1; ?>
                                                                </div>

                                                                <!-- Icon and Name -->
                                                                <div class="flex-1">
                                                                    <div class="flex items-center gap-3">
                                                                        <div class="p-2 rounded-lg" style="background-color: <?php echo $category['color']; ?>20;">
                                                                            <span class="inline-flex items-center justify-center w-6 h-6" style="color: <?php echo $category['color']; ?>;"
                                                                                  data-icon="<?php echo $category['icon'] ?? 'tag'; ?>" data-type="outline"></span>
                                                                        </div>
                                                                        <div>
                                                                            <h3 class="font-semibold text-lg flex items-center gap-2">
                                                                                <?php echo htmlspecialchars($category['name']); ?>
                                                                                <?php if ($category['product_count'] > 0): ?>
                                                                                                <span class="bg-emerald-500/20 text-emerald-400 text-xs px-2 py-0.5 rounded-full">
                                                                                                    <?php echo $category['product_count']; ?> products
                                                                                                </span>
                                                                                <?php endif; ?>
                                                                            </h3>
                                                                            <?php if (!empty($category['description'])): ?>
                                                                                            <p class="text-xs text-gray-400 mt-1"><?php echo htmlspecialchars($category['description']); ?></p>
                                                                            <?php endif; ?>
                                                                        </div>
                                                                    </div>
                                                                </div>

                                                                <!-- Actions -->
                                                                <div class="flex items-center gap-2">
                                                                    <a href="add_category.php?parent=<?php echo $category['id']; ?>" 
                                                                       class="action-btn p-2 hover:bg-amber-500/20 rounded-lg transition" title="Add Subcategory">
                                                                        <span class="inline-flex items-center justify-center w-5 h-5 text-amber-400" data-icon="plus-circle" data-type="outline"></span>
                                                                    </a>
                                                                    <a href="edit_category.php?id=<?php echo $category['id']; ?>" 
                                                                       class="action-btn p-2 hover:bg-blue-500/20 rounded-lg transition" title="Edit Category">
                                                                        <span class="inline-flex items-center justify-center w-5 h-5 text-blue-400" data-icon="pencil" data-type="outline"></span>
                                                                    </a>
                                                                    <a href="../products/products.php?category=<?php echo $category['id']; ?>" 
                                                                       class="action-btn p-2 hover:bg-emerald-500/20 rounded-lg transition" title="View Products">
                                                                        <span class="inline-flex items-center justify-center w-5 h-5 text-emerald-400" data-icon="cube" data-type="outline"></span>
                                                                    </a>
                                                                </div>
                                                            </div>

                                                            <!-- Metadata -->
                                                            <div class="mt-3 flex items-center gap-4 text-xs text-gray-500">
                                                                <span>ID: #<?php echo $category['id']; ?></span>
                                                                <?php if (!empty($category['created_by_name'])): ?>
                                                                                <span>Created by: <?php echo htmlspecialchars($category['created_by_name']); ?></span>
                                                                <?php endif; ?>
                                                                <span>Updated: <?php echo date('d M Y', strtotime($category['updated_at'] ?? 'now')); ?></span>
                                                            </div>
                                                        </div>

                                                        <!-- Children -->
                                                        <?php if (!empty($category['children'])): ?>
                                                                        <ul class="mt-3 space-y-3">
                                                                            <?php foreach ($category['children'] as $child): ?>
                                                                                            <li>
                                                                                                <div class="tree-connector"></div>
                                                                                                <div class="tree-node bg-primary-dark/30 rounded-xl p-3 border border-slate-700 ml-4">
                                                                                                    <div class="flex items-start gap-3">
                                                                                                        <!-- Level Indicator -->
                                                                                                        <div class="level-indicator w-6 h-6 text-xs" style="background-color: <?php echo $color_map[($category['level'] + 1) % count($color_map)]; ?>20; color: <?php echo $color_map[($category['level'] + 1) % count($color_map)]; ?>;">
                                                                                                            L<?php echo $child['level'] + 1; ?>
                                                                                                        </div>

                                                                                                        <div class="p-2 rounded-lg" style="background-color: <?php echo $child['color']; ?>20;">
                                                                                                            <span class="inline-flex items-center justify-center w-5 h-5" style="color: <?php echo $child['color']; ?>;"
                                                                                                                  data-icon="<?php echo $child['icon'] ?? 'tag'; ?>" data-type="outline"></span>
                                                                                                        </div>
                                                        
                                                                                                        <div class="flex-1">
                                                                                                            <div class="flex items-center gap-2">
                                                                                                                <h4 class="font-semibold"><?php echo htmlspecialchars($child['name']); ?></h4>
                                                                                                                <?php if ($child['product_count'] > 0): ?>
                                                                                                                                <span class="bg-emerald-500/20 text-emerald-400 text-xs px-2 py-0.5 rounded-full">
                                                                                                                                    <?php echo $child['product_count']; ?>
                                                                                                                                </span>
                                                                                                                <?php endif; ?>
                                                                                                            </div>
                                                                                                        </div>

                                                                                                        <div class="flex items-center gap-1">
                                                                                                            <a href="edit_category.php?id=<?php echo $child['id']; ?>" 
                                                                                                               class="action-btn p-1.5 hover:bg-blue-500/20 rounded-lg transition" title="Edit">
                                                                                                                <span class="inline-flex items-center justify-center w-4 h-4 text-blue-400" data-icon="pencil" data-type="outline"></span>
                                                                                                            </a>
                                                                                                            <a href="../products/products.php?category=<?php echo $child['id']; ?>" 
                                                                                                               class="action-btn p-1.5 hover:bg-emerald-500/20 rounded-lg transition" title="View Products">
                                                                                                                <span class="inline-flex items-center justify-center w-4 h-4 text-emerald-400" data-icon="cube" data-type="outline"></span>
                                                                                                            </a>
                                                                                                        </div>
                                                                                                    </div>
                                                                                                </div>
                                                                                            </li>
                                                                            <?php endforeach; ?>
                                                                        </ul>
                                                        <?php endif; ?>
                                                    </li>
                                                <?php
                                                $color_index++;
                                    endforeach;
                                    ?>
                                </ul>
                            </div>
            <?php endif; ?>
        </div>

        <!-- Orphaned Categories Alert -->
        <?php if (!empty($orphaned)): ?>
                        <div class="mt-6 bg-red-500/10 border border-red-500 rounded-xl p-4">
                            <div class="flex items-center gap-3 mb-3">
                                <span class="inline-flex items-center justify-center w-6 h-6 text-red-400" data-icon="exclamation-triangle" data-type="outline"></span>
                                <h3 class="font-semibold text-red-400">Orphaned Categories Found</h3>
                            </div>
                            <p class="text-sm text-gray-300 mb-3">These categories have missing parents and need attention:</p>
                            <div class="space-y-2">
                                <?php foreach ($orphaned as $orphan): ?>
                                                <div class="orphaned bg-primary-dark/30 rounded-lg p-3 flex items-center justify-between">
                                                    <div class="flex items-center gap-3">
                                                        <div class="p-2 rounded-lg" style="background-color: <?php echo $orphan['color']; ?>20;">
                                                            <span class="inline-flex items-center justify-center w-5 h-5" style="color: <?php echo $orphan['color']; ?>;"
                                                                  data-icon="<?php echo $orphan['icon'] ?? 'tag'; ?>" data-type="outline"></span>
                                                        </div>
                                                        <div>
                                                            <p class="font-medium"><?php echo htmlspecialchars($orphan['name']); ?></p>
                                                            <p class="text-xs text-gray-400">Parent ID: <?php echo $orphan['parent_id']; ?> (missing)</p>
                                                        </div>
                                                    </div>
                                                    <a href="edit_category.php?id=<?php echo $orphan['id']; ?>" 
                                                       class="bg-red-500/20 hover:bg-red-500/30 text-red-400 px-3 py-1 rounded-lg text-sm transition">
                                                        Fix Parent
                                                    </a>
                                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
        <?php endif; ?>
    </div>

    <script>
        // Initialize tooltips
        document.querySelectorAll('[title]').forEach(el => {
            el.addEventListener('mouseenter', function(e) {
                const tooltip = document.createElement('div');
                tooltip.className = 'absolute bg-slate-800 text-white text-xs px-2 py-1 rounded border border-slate-700 z-50';
                tooltip.textContent = this.title;
                this.appendChild(tooltip);

                const rect = this.getBoundingClientRect();
                tooltip.style.top = '-30px';
                tooltip.style.left = '50%';
                tooltip.style.transform = 'translateX(-50%)';

                this.addEventListener('mouseleave', () => tooltip.remove());
            });
        });

        // Expand/collapse functionality
        function toggleChildren(element) {
            const children = element.nextElementSibling;
            if (children && children.tagName === 'UL') {
                children.classList.toggle('hidden');
                const icon = element.querySelector('.toggle-icon');
                if (icon) {
                    icon.classList.toggle('rotate-90');
                }
            }
        }

        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            if (e.target.matches('input, textarea, select')) {
                return;
            }

            // Ctrl + N - New category
            if (e.ctrlKey && e.key === 'n') {
                e.preventDefault();
                window.location.href = 'add_category.php';
            }

            // Ctrl + B - Back to list
            if (e.ctrlKey && e.key === 'b') {
                e.preventDefault();
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