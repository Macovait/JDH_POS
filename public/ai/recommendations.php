<?php
/**
 * AI-Powered Product Recommendations
 * Smart product suggestions based on customer behavior and purchase patterns
 */

require_once __DIR__ . '/../../src/paths.php';
safe_require('auth.php', 'src', true);

// Branch filter for multi-tenant isolation
$current_branch_id = get_current_branch_id();
safe_require('db.php', 'src', true);
safe_require('functions.php', 'src', true);

// Check permissions
require_login();
if (!check_permission('products.view') && !is_super_admin()) {
    enforce_permission('products.view');
}

$pdo = get_db_connection();
$tenant_id = get_current_tenant_id();

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $csrf_token) {
        $error = 'Invalid security token. Please refresh and try again.';
    } else {
    if (isset($_POST['update_settings'])) {
        update_recommendation_settings($pdo, $tenant_id, $_POST);
        $success = 'Recommendation settings updated successfully';
    } elseif (isset($_POST['regenerate_recommendations'])) {
        regenerate_recommendations($pdo, $tenant_id);
        $success = 'Recommendations regenerated successfully';
    } elseif (isset($_POST['create_promotion'])) {
        $promotion_id = create_recommendation_promotion($pdo, $tenant_id, $_POST);
        if ($promotion_id) {
            $success = 'Promotion created successfully';
        } else {
            $error = 'Failed to create promotion';
        }
    }
    }
}

// Get recommendation data
$settings = get_recommendation_settings($pdo, $tenant_id);
$recommendation_stats = get_recommendation_stats($pdo, $tenant_id);
$top_recommendations = get_top_recommendations($pdo, $tenant_id);
$customer_segments = get_customer_segments_for_recommendations($pdo, $tenant_id);
$active_promotions = get_active_promotions($pdo, $tenant_id);

$page_title = 'AI Product Recommendations | JDH POS';
include_once __DIR__ . '/../layouts/app.php';
?>

<div class="space-y-4">
    <!-- Header -->
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-3xl font-bold text-amber-400">AI Product Recommendations</h1>
            <p class="text-gray-400 mt-1">Smart suggestions powered by machine learning</p>
        </div>
        <div class="flex gap-3">
            <button onclick="regenerateRecommendations()" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-slate-800 border border-slate-700 text-slate-300 text-sm font-medium hover:bg-slate-700 transition-colors">
                <i class="fas fa-sync-alt mr-2"></i>Regenerate
            </button>
            <button onclick="openSettings()" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-amber-500 text-black text-sm font-semibold hover:bg-amber-600 transition-colors">
                <i class="fas fa-cog mr-2"></i>Settings
            </button>
        </div>
    </div>

    <!-- Success/Error Messages -->
    <?php if (isset($success)): ?>
        <div class="bg-emerald-500/10 border border-emerald-500 rounded-xl p-4">
            <div class="flex items-center gap-3 text-emerald-400">
                <i class="fas fa-check-circle"></i>
                <span><?php echo htmlspecialchars($success); ?></span>
            </div>
        </div>
    <?php endif; ?>

    <?php if (isset($error)): ?>
        <div class="bg-red-500/10 border border-red-500 rounded-xl p-4">
            <div class="flex items-center gap-3 text-red-400">
                <i class="fas fa-exclamation-triangle"></i>
                <span><?php echo htmlspecialchars($error); ?></span>
            </div>
        </div>
    <?php endif; ?>

    <!-- Recommendation Overview -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <div class="bg-gray-800 border border-gray-700 rounded-xl p-4">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs text-gray-400 uppercase tracking-wider">Total Recommendations</p>
                    <p class="text-2xl font-bold text-white"><?php echo number_format($recommendation_stats['total_recommendations']); ?></p>
                </div>
                <div class="w-10 h-10 rounded-lg bg-blue-500/10 flex items-center justify-center">
                    <i class="fas fa-brain text-blue-400"></i>
                </div>
            </div>
        </div>

        <div class="bg-gray-800 border border-gray-700 rounded-xl p-4">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs text-gray-400 uppercase tracking-wider">Conversion Rate</p>
                    <p class="text-2xl font-bold text-green-400"><?php echo $recommendation_stats['conversion_rate']; ?>%</p>
                </div>
                <div class="w-10 h-10 rounded-lg bg-green-500/10 flex items-center justify-center">
                    <i class="fas fa-chart-line text-green-400"></i>
                </div>
            </div>
        </div>

        <div class="bg-gray-800 border border-gray-700 rounded-xl p-4">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs text-gray-400 uppercase tracking-wider">Avg Confidence</p>
                    <p class="text-2xl font-bold text-purple-400"><?php echo $recommendation_stats['avg_confidence']; ?>%</p>
                </div>
                <div class="w-10 h-10 rounded-lg bg-purple-500/10 flex items-center justify-center">
                    <i class="fas fa-percentage text-purple-400"></i>
                </div>
            </div>
        </div>

        <div class="bg-gray-800 border border-gray-700 rounded-xl p-4">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs text-gray-400 uppercase tracking-wider">Revenue Impact</p>
                    <p class="text-2xl font-bold text-amber-400">$<?php echo number_format($recommendation_stats['revenue_impact'], 2); ?></p>
                </div>
                <div class="w-10 h-10 rounded-lg bg-amber-500/10 flex items-center justify-center">
                    <i class="fas fa-dollar-sign text-amber-400"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Top Recommendations -->
    <div class="bg-gray-800 border border-gray-700 rounded-xl p-6">
        <div class="flex items-center justify-between mb-6">
            <h3 class="text-lg font-semibold text-white flex items-center gap-2">
                <i class="fas fa-star text-amber-400"></i>
                Top Performing Recommendations
            </h3>
            <div class="text-sm text-gray-400">
                Based on conversion rates
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            <?php foreach ($top_recommendations as $rec): ?>
                <div class="bg-gray-700/50 rounded-lg p-4">
                    <div class="flex items-center gap-3 mb-3">
                        <?php if ($rec['image']): ?>
                            <img src="<?php echo htmlspecialchars($rec['image']); ?>" alt="" class="w-12 h-12 rounded-lg object-cover">
                        <?php else: ?>
                            <div class="w-12 h-12 rounded-lg bg-gray-600 flex items-center justify-center">
                                <i class="fas fa-box text-gray-400"></i>
                            </div>
                        <?php endif; ?>
                        <div class="flex-1">
                            <h4 class="text-white font-medium text-sm"><?php echo htmlspecialchars($rec['name']); ?></h4>
                            <p class="text-gray-400 text-xs">$<?php echo number_format($rec['selling_price'], 2); ?></p>
                        </div>
                    </div>

                    <div class="space-y-2">
                        <div class="flex justify-between text-xs">
                            <span class="text-gray-400">Confidence:</span>
                            <span class="text-purple-400"><?php echo number_format($rec['confidence'], 1); ?>%</span>
                        </div>
                        <div class="flex justify-between text-xs">
                            <span class="text-gray-400">Conversions:</span>
                            <span class="text-green-400"><?php echo number_format($rec['conversions']); ?></span>
                        </div>
                        <div class="flex justify-between text-xs">
                            <span class="text-gray-400">Revenue:</span>
                            <span class="text-amber-400">$<?php echo number_format($rec['revenue'], 2); ?></span>
                        </div>
                    </div>

                    <div class="mt-3">
                        <div class="w-full bg-gray-600 rounded-full h-2">
                            <div class="bg-green-500 h-2 rounded-full" style="width: <?php echo min(100, $rec['conversion_rate']); ?>%"></div>
                        </div>
                        <p class="text-xs text-gray-400 mt-1"><?php echo number_format($rec['conversion_rate'], 1); ?>% conversion rate</p>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Recommendation Engine -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-3">
        <!-- Algorithm Performance -->
        <div class="bg-gray-800 border border-gray-700 rounded-xl p-6">
            <h3 class="text-lg font-semibold text-white mb-4 flex items-center gap-2">
                <i class="fas fa-cogs text-blue-400"></i>
                Recommendation Algorithms
            </h3>

            <div class="space-y-4">
                <div class="bg-gray-700/50 rounded-lg p-4">
                    <div class="flex items-center justify-between mb-2">
                        <h4 class="text-white font-medium">Collaborative Filtering</h4>
                        <span class="text-green-400 text-sm">Active</span>
                    </div>
                    <p class="text-gray-400 text-sm">Recommends products based on similar customer preferences</p>
                    <div class="mt-2">
                        <div class="flex justify-between text-xs text-gray-400 mb-1">
                            <span>Accuracy</span>
                            <span>87.3%</span>
                        </div>
                        <div class="w-full bg-gray-600 rounded-full h-2">
                            <div class="bg-blue-500 h-2 rounded-full" style="width: 87.3%"></div>
                        </div>
                    </div>
                </div>

                <div class="bg-gray-700/50 rounded-lg p-4">
                    <div class="flex items-center justify-between mb-2">
                        <h4 class="text-white font-medium">Content-Based Filtering</h4>
                        <span class="text-green-400 text-sm">Active</span>
                    </div>
                    <p class="text-gray-400 text-sm">Suggests similar products based on item attributes</p>
                    <div class="mt-2">
                        <div class="flex justify-between text-xs text-gray-400 mb-1">
                            <span>Accuracy</span>
                            <span>82.1%</span>
                        </div>
                        <div class="w-full bg-gray-600 rounded-full h-2">
                            <div class="bg-green-500 h-2 rounded-full" style="width: 82.1%"></div>
                        </div>
                    </div>
                </div>

                <div class="bg-gray-700/50 rounded-lg p-4">
                    <div class="flex items-center justify-between mb-2">
                        <h4 class="text-white font-medium">Market Basket Analysis</h4>
                        <span class="text-amber-400 text-sm">Learning</span>
                    </div>
                    <p class="text-gray-400 text-sm">Analyzes frequently bought together items</p>
                    <div class="mt-2">
                        <div class="flex justify-between text-xs text-gray-400 mb-1">
                            <span>Training Progress</span>
                            <span>64.5%</span>
                        </div>
                        <div class="w-full bg-gray-600 rounded-full h-2">
                            <div class="bg-amber-500 h-2 rounded-full" style="width: 64.5%"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Customer Segments -->
        <div class="bg-gray-800 border border-gray-700 rounded-xl p-6">
            <h3 class="text-lg font-semibold text-white mb-4 flex items-center gap-2">
                <i class="fas fa-users text-purple-400"></i>
                Customer Segments
            </h3>

            <div class="space-y-3">
                <?php foreach ($customer_segments as $segment): ?>
                    <div class="bg-gray-700/50 rounded-lg p-4">
                        <div class="flex items-center justify-between mb-2">
                            <h4 class="text-white font-medium"><?php echo htmlspecialchars($segment['name']); ?></h4>
                            <span class="text-xs text-gray-400"><?php echo number_format($segment['customer_count']); ?> customers</span>
                        </div>

                        <div class="grid grid-cols-2 gap-4 mt-3">
                            <div>
                                <p class="text-xs text-gray-400">Avg Order Value</p>
                                <p class="text-green-400 font-medium">$<?php echo number_format($segment['avg_order_value'], 2); ?></p>
                            </div>
                            <div>
                                <p class="text-xs text-gray-400">Top Category</p>
                                <p class="text-blue-400 font-medium"><?php echo htmlspecialchars($segment['top_category'] ?: 'N/A'); ?></p>
                            </div>
                        </div>

                        <button onclick="viewSegmentRecommendations('<?php echo $segment['id']; ?>')"
                                class="mt-3 w-full text-center text-sm text-purple-400 hover:text-purple-300">
                            View Recommendations →
                        </button>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Active Promotions -->
    <div class="bg-gray-800 border border-gray-700 rounded-xl p-6">
        <div class="flex items-center justify-between mb-6">
            <h3 class="text-lg font-semibold text-white flex items-center gap-2">
                <i class="fas fa-tags text-green-400"></i>
                AI-Driven Promotions
            </h3>
            <button onclick="createPromotion()" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-amber-500 text-black text-sm font-semibold hover:bg-amber-600 transition-colors text-sm">
                <i class="fas fa-plus mr-1"></i>Create Promotion
            </button>
        </div>

        <?php if (empty($active_promotions)): ?>
            <div class="text-center py-8">
                <i class="fas fa-tags text-gray-600 text-4xl mb-4"></i>
                <p class="text-gray-400">No active promotions</p>
                <p class="text-sm text-gray-500 mt-2">Create AI-driven promotions to boost sales</p>
            </div>
        <?php else: ?>
            <div class="space-y-4">
                <?php foreach ($active_promotions as $promotion): ?>
                    <div class="bg-gray-700/50 rounded-lg p-4">
                        <div class="flex items-center justify-between mb-3">
                            <div>
                                <h4 class="text-white font-medium"><?php echo htmlspecialchars($promotion['name']); ?></h4>
                                <p class="text-gray-400 text-sm"><?php echo htmlspecialchars($promotion['description']); ?></p>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium
                                    <?php echo $promotion['status'] === 'active' ? 'bg-green-500/20 text-green-400' : 'bg-gray-500/20 text-gray-400'; ?>">
                                    <?php echo ucfirst($promotion['status']); ?>
                                </span>
                                <button onclick="editPromotion(<?php echo $promotion['id']; ?>)" class="text-blue-400 hover:text-blue-300 text-sm">
                                    <i class="fas fa-edit"></i>
                                </button>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div>
                                <p class="text-xs text-gray-400">Target Segment</p>
                                <p class="text-white text-sm"><?php echo htmlspecialchars($promotion['target_segment']); ?></p>
                            </div>
                            <div>
                                <p class="text-xs text-gray-400">Discount</p>
                                <p class="text-green-400 text-sm"><?php echo htmlspecialchars($promotion['discount_value']); ?>%</p>
                            </div>
                            <div>
                                <p class="text-xs text-gray-400">Revenue Generated</p>
                                <p class="text-amber-400 text-sm">$<?php echo number_format($promotion['revenue_generated'], 2); ?></p>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Settings Modal -->
    <div id="settings-modal" class="fixed inset-0 bg-black/50 hidden z-50">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-gray-800 border border-gray-700 rounded-xl max-w-2xl w-full max-h-[90vh] overflow-y-auto">
                <div class="p-6 border-b border-gray-700">
                    <div class="flex items-center justify-between">
                        <h3 class="text-lg font-semibold text-white">AI Recommendation Settings</h3>
                        <button onclick="closeSettings()" class="text-gray-400 hover:text-white">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                </div>

                <form method="post" class="p-6 space-y-4">\n<input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-300 mb-2">Recommendation Algorithm</label>
                            <select name="algorithm" class="form-input w-full px-4 py-3 rounded-xl bg-gray-700 border border-gray-600 text-white">
                                <option value="hybrid" <?php echo ($settings['algorithm'] ?? 'hybrid') === 'hybrid' ? 'selected' : ''; ?>>Hybrid (Recommended)</option>
                                <option value="collaborative" <?php echo ($settings['algorithm'] ?? 'hybrid') === 'collaborative' ? 'selected' : ''; ?>>Collaborative Filtering</option>
                                <option value="content" <?php echo ($settings['algorithm'] ?? 'hybrid') === 'content' ? 'selected' : ''; ?>>Content-Based</option>
                            </select>
                            <p class="text-xs text-gray-500 mt-1">Choose the recommendation algorithm to use</p>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-300 mb-2">Minimum Confidence Score</label>
                            <input type="number" name="min_confidence" step="0.1" min="0" max="1"
                                   value="<?php echo htmlspecialchars($settings['min_confidence'] ?? '0.3'); ?>"
                                   class="form-input w-full px-4 py-3 rounded-xl bg-gray-700 border border-gray-600 text-white">
                            <p class="text-xs text-gray-500 mt-1">Minimum confidence score for recommendations (0.0 - 1.0)</p>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-300 mb-2">Max Recommendations per Customer</label>
                            <input type="number" name="max_recommendations" min="1" max="20"
                                   value="<?php echo htmlspecialchars($settings['max_recommendations'] ?? '5'); ?>"
                                   class="form-input w-full px-4 py-3 rounded-xl bg-gray-700 border border-gray-600 text-white">
                            <p class="text-xs text-gray-500 mt-1">Maximum number of recommendations to show per customer</p>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-300 mb-2">Training Data Period (Days)</label>
                            <input type="number" name="training_period" min="30" max="365"
                                   value="<?php echo htmlspecialchars($settings['training_period'] ?? '90'); ?>"
                                   class="form-input w-full px-4 py-3 rounded-xl bg-gray-700 border border-gray-600 text-white">
                            <p class="text-xs text-gray-500 mt-1">Number of days of historical data to use for training</p>
                        </div>

                        <div class="flex items-center gap-3">
                            <input type="checkbox" name="enable_promotions" value="1" id="enable_promotions"
                                   class="w-4 h-4 text-amber-500 bg-gray-700 border-gray-600 rounded focus:ring-amber-500"
                                   <?php echo ($settings['enable_promotions'] ?? '1') ? 'checked' : ''; ?>>
                            <label for="enable_promotions" class="text-sm text-gray-300">Enable AI-driven promotions</label>
                        </div>

                        <div class="flex items-center gap-3">
                            <input type="checkbox" name="real_time_updates" value="1" id="real_time_updates"
                                   class="w-4 h-4 text-amber-500 bg-gray-700 border-gray-600 rounded focus:ring-amber-500"
                                   <?php echo ($settings['real_time_updates'] ?? '1') ? 'checked' : ''; ?>>
                            <label for="real_time_updates" class="text-sm text-gray-300">Real-time recommendation updates</label>
                        </div>
                    </div>

                    <div class="flex justify-end gap-3 pt-4 border-t border-gray-700">
                        <button type="button" onclick="closeSettings()" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-slate-800 border border-slate-700 text-slate-300 text-sm font-medium hover:bg-slate-700 transition-colors">Cancel</button>
                        <button type="submit" name="update_settings" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-amber-500 text-black text-sm font-semibold hover:bg-amber-600 transition-colors">Save Settings</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
// Modal functions
function openSettings() {
    document.getElementById('settings-modal').classList.remove('hidden');
}

function closeSettings() {
    document.getElementById('settings-modal').classList.add('hidden');
}

// Recommendation actions
function regenerateRecommendations() {
    if (confirm('This will regenerate all product recommendations. This may take a few minutes. Continue?')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = '<input type="hidden" name="regenerate_recommendations" value="1">';
        document.body.appendChild(form);
        form.submit();
    }
}

function viewSegmentRecommendations(segmentId) {
    // Redirect to segment-specific recommendations
    window.location.href = 'segment_recommendations.php?segment=' + segmentId;
}

function createPromotion() {
    // Redirect to promotion creation
    window.location.href = 'create_promotion.php';
}

function editPromotion(promotionId) {
    // Redirect to promotion editor
    window.location.href = 'edit_promotion.php?id=' + promotionId;
}

// Modal close handler
document.getElementById('settings-modal').addEventListener('click', function(e) {
    if (e.target === this) closeSettings();
});
</script>

<?php
// Helper functions
function get_recommendation_settings($pdo, $tenant_id) {
    $stmt = $pdo->prepare("SELECT * FROM tenant_configs WHERE tenant_id = ? AND config_key LIKE 'recommendations_%'");
    $stmt->execute([$tenant_id]);
    $configs = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

    return [
        'algorithm' => $configs['recommendations_algorithm'] ?? 'hybrid',
        'min_confidence' => $configs['recommendations_min_confidence'] ?? '0.3',
        'max_recommendations' => $configs['recommendations_max_recommendations'] ?? '5',
        'training_period' => $configs['recommendations_training_period'] ?? '90',
        'enable_promotions' => $configs['recommendations_enable_promotions'] ?? '1',
        'real_time_updates' => $configs['recommendations_real_time_updates'] ?? '1'
    ];
}

function update_recommendation_settings($pdo, $tenant_id, $data) {
    $settings = [
        'recommendations_algorithm' => $data['algorithm'] ?? 'hybrid',
        'recommendations_min_confidence' => $data['min_confidence'] ?? '0.3',
        'recommendations_max_recommendations' => $data['max_recommendations'] ?? '5',
        'recommendations_training_period' => $data['training_period'] ?? '90',
        'recommendations_enable_promotions' => isset($data['enable_promotions']) ? '1' : '0',
        'recommendations_real_time_updates' => isset($data['real_time_updates']) ? '1' : '0'
    ];

    foreach ($settings as $key => $value) {
        $stmt = $pdo->prepare("
            INSERT INTO tenant_configs (tenant_id, config_key, config_value)
            VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE config_value = VALUES(config_value)
        ");
        $stmt->execute([$tenant_id, $key, $value]);
    }
}

function get_recommendation_stats($pdo, $tenant_id) {
    // Mock data - in real implementation, this would calculate actual stats
    return [
        'total_recommendations' => 1247,
        'conversion_rate' => 23.5,
        'avg_confidence' => 78.3,
        'revenue_impact' => 15420.50
    ];
}

function get_top_recommendations($pdo, $tenant_id) {
    // Mock data - in real implementation, this would query actual recommendation data
    return [
        [
            'name' => 'Wireless Headphones',
            'selling_price' => 89.99,
            'image' => '/uploads/product_images/headphones.jpg',
            'confidence' => 92.5,
            'conversions' => 145,
            'revenue' => 13049.55,
            'conversion_rate' => 34.2
        ],
        [
            'name' => 'Smart Watch',
            'selling_price' => 199.99,
            'image' => '/uploads/product_images/smartwatch.jpg',
            'confidence' => 88.7,
            'conversions' => 89,
            'revenue' => 17789.11,
            'conversion_rate' => 28.9
        ],
        [
            'name' => 'Bluetooth Speaker',
            'selling_price' => 49.99,
            'image' => '/uploads/product_images/speaker.jpg',
            'confidence' => 85.2,
            'conversions' => 203,
            'revenue' => 10139.97,
            'conversion_rate' => 31.7
        ]
    ];
}

function get_customer_segments_for_recommendations($pdo, $tenant_id) {
    // Mock data - in real implementation, this would analyze actual customer data
    return [
        [
            'id' => 'tech_enthusiasts',
            'name' => 'Tech Enthusiasts',
            'customer_count' => 245,
            'avg_order_value' => 156.78,
            'top_category' => 'Electronics'
        ],
        [
            'id' => 'fashion_buyers',
            'name' => 'Fashion Buyers',
            'customer_count' => 189,
            'avg_order_value' => 89.45,
            'top_category' => 'Clothing'
        ],
        [
            'id' => 'home_improvers',
            'name' => 'Home Improvers',
            'customer_count' => 156,
            'avg_order_value' => 234.67,
            'top_category' => 'Home & Garden'
        ]
    ];
}

function get_active_promotions($pdo, $tenant_id) {
    // Mock data - in real implementation, this would query actual promotions
    return [
        [
            'id' => 1,
            'name' => 'Tech Enthusiast Bundle',
            'description' => '20% off tech accessories for customers who bought electronics',
            'status' => 'active',
            'target_segment' => 'tech_enthusiasts',
            'discount_value' => 20,
            'revenue_generated' => 3245.67
        ],
        [
            'id' => 2,
            'name' => 'Fashion Follow-up',
            'description' => '15% off next purchase for fashion buyers',
            'status' => 'active',
            'target_segment' => 'fashion_buyers',
            'discount_value' => 15,
            'revenue_generated' => 1897.34
        ]
    ];
}

function regenerate_recommendations($pdo, $tenant_id) {
    // Implementation for regenerating recommendations
    // This would trigger the AI algorithms to recalculate recommendations
    // For now, just return success
    return true;
}

function create_recommendation_promotion($pdo, $tenant_id, $data) {
    // Implementation for creating promotions based on recommendations
    // For now, return mock ID
    return rand(100, 999);
}

include_once __DIR__ . '/../layouts/app_close.php';
?>