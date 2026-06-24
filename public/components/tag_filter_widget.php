<?php

// Branch filter for multi-tenant isolation
$current_branch_id = get_current_branch_id();
/**
 * Tag Filter Widget — Reusable component for dashboard and online store
 * Usage:
 *   include __DIR__ . '/../components/tag_filter_widget.php';
 *   renderTagFilter($tenant_id, $pdo, ['redirect_url' => 'store/index.php']);
 */
function renderTagFilter(int $tenant_id, PDO $pdo, array $options = []): void {
    $redirect_url = $options['redirect_url'] ?? 'products/products.php';
    $param_name = $options['param_name'] ?? 'tag_id';
    $style = $options['style'] ?? 'pills'; // pills or cloud

    $stmt = $pdo->prepare("
        SELECT t.id, t.name, t.color, t.icon, COUNT(r.product_id) as product_count
        FROM product_tags t
        LEFT JOIN product_tag_relations r ON r.tag_id = t.id AND r.tenant_id = t.tenant_id
        WHERE t.tenant_id = ? AND t.is_active = 1
        GROUP BY t.id
        HAVING product_count > 0
        ORDER BY product_count DESC, t.name
        LIMIT 20
    ");
    $stmt->execute([$tenant_id]);
    $tags = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (empty($tags)) return;

    $active_tag = (int) ($_GET[$param_name] ?? 0);
?>
<div class="tag-filter-widget mb-4">
    <?php if ($style === 'cloud'): ?>
    <div class="tag-cloud flex flex-wrap gap-2">
        <?php foreach ($tags as $tag): 
            $size = 12 + min(12, floor($tag['product_count'] / max(1, array_sum(array_column($tags, 'product_count'))) * 12));
            $isActive = $active_tag === (int) $tag['id'];
        ?>
        <a href="<?php echo htmlspecialchars($redirect_url); ?>?<?php echo http_build_query(array_merge($_GET, [$param_name => $isActive ? null : $tag['id'], 'tag_name' => $isActive ? null : $tag['name']])); ?>"
           class="tag-cloud-item inline-flex items-center gap-1 rounded-full px-3 py-1 transition"
           style="font-size: <?php echo $size; ?>px; background: <?php echo $isActive ? $tag['color'] : $tag['color'] . '15'; ?>; color: <?php echo $isActive ? '#111827' : $tag['color']; ?>; border: 1px solid <?php echo $tag['color']; ?><?php echo $isActive ? '' : '40'; ?>;">
            <i class="fas <?php echo htmlspecialchars($tag['icon'] ?: 'fa-tag'); ?>"></i>
            <?php echo htmlspecialchars($tag['name']); ?>
            <span class="text-xs opacity-70">(<?php echo $tag['product_count']; ?>)</span>
        </a>
        <?php endforeach; ?>
    </div>
    <?php else: ?>
    <div class="tag-pills flex flex-wrap gap-2">
        <?php foreach ($tags as $tag):
            $isActive = $active_tag === (int) $tag['id'];
        ?>
        <a href="<?php echo htmlspecialchars($redirect_url); ?>?<?php echo http_build_query(array_merge($_GET, [$param_name => $isActive ? null : $tag['id'], 'tag_name' => $isActive ? null : $tag['name']])); ?>"
           class="tag-pill inline-flex items-center gap-1 rounded-full px-3 py-1 text-xs font-medium transition"
           style="background: <?php echo $isActive ? $tag['color'] : $tag['color'] . '15'; ?>; color: <?php echo $isActive ? '#111827' : $tag['color']; ?>; border: 1px solid <?php echo $tag['color']; ?><?php echo $isActive ? '' : '40'; ?>;">
            <i class="fas <?php echo htmlspecialchars($tag['icon'] ?: 'fa-tag'); ?>"></i>
            <?php echo htmlspecialchars($tag['name']); ?>
        </a>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>
<?php }
