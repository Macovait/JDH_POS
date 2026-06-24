<?php
/**
 * Seed pos_landing_blocks table with product data for landing page
 */
require_once __DIR__ . '/../../src/paths.php';
require_once __DIR__ . '/../../src/db.php';

try {
    $pdo = get_db_connection();

    // Create table if not exists
    $pdo->exec("CREATE TABLE IF NOT EXISTS pos_landing_blocks (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        section VARCHAR(50) NOT NULL COMMENT 'products,ai_slides,how_it_works,trust_strip,faq,cta,features,about',
        block_key VARCHAR(100) NOT NULL,
        title VARCHAR(255) DEFAULT NULL,
        subtitle VARCHAR(500) DEFAULT NULL,
        content TEXT DEFAULT NULL,
        image_url VARCHAR(500) DEFAULT NULL,
        icon_class VARCHAR(100) DEFAULT NULL,
        sort_order INT DEFAULT 0,
        is_active TINYINT DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY unique_block (section, block_key),
        INDEX idx_section_sort (section, sort_order),
        INDEX idx_active (is_active)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // Check if data already exists
    $exists = $pdo->query("SELECT COUNT(*) FROM pos_landing_blocks WHERE section = 'products'")->fetchColumn();
    if ($exists > 0) {
        echo "Landing blocks already seeded.\n";
        exit(0);
    }

    $blocks = [
        // Products Section
        ['section' => 'products', 'block_key' => 'pos', 'title' => 'Point of Sale', 'subtitle' => 'POS', 'content' => 'Fast checkout with offline capability, barcode scanning, split payments, and multi-currency support. Works seamlessly even without internet and syncs when back online.', 'icon_class' => 'fas fa-cash-register', 'image_url' => 'assets/img/pos-preview.png', 'sort_order' => 1, 'is_active' => 1],
        ['section' => 'products', 'block_key' => 'erp', 'title' => 'Inventory & ERP', 'subtitle' => 'ERP', 'content' => 'Complete inventory management with stock tracking, purchase orders, supplier management, and multi-branch support. Never run out of stock again.', 'icon_class' => 'fas fa-boxes-stacked', 'image_url' => 'assets/img/erp-preview.png', 'sort_order' => 2, 'is_active' => 1],
        ['section' => 'products', 'block_key' => 'ecommerce', 'title' => 'Online Store', 'subtitle' => 'Store', 'content' => 'Launch your online storefront with product catalogs, customer accounts, secure checkout, and order tracking. Syncs with your POS inventory in real-time.', 'icon_class' => 'fas fa-store', 'image_url' => 'assets/img/store-preview.png', 'sort_order' => 3, 'is_active' => 1],
        ['section' => 'products', 'block_key' => 'ai', 'title' => 'AI Assistant', 'subtitle' => 'AI', 'content' => 'Smart ordering suggestions, predictive analytics, customer insights, and automated reports. Let AI handle the heavy lifting while you focus on growth.', 'icon_class' => 'fas fa-robot', 'image_url' => 'assets/img/ai-preview.png', 'sort_order' => 4, 'is_active' => 1],

        // How It Works
        ['section' => 'how_it_works', 'block_key' => 'signup', 'title' => 'Create Account', 'subtitle' => null, 'content' => 'Sign up in under 2 minutes with your business name and email. No credit card required to start your free trial.', 'icon_class' => 'fas fa-user-plus', 'image_url' => null, 'sort_order' => 1, 'is_active' => 1],
        ['section' => 'how_it_works', 'block_key' => 'setup', 'title' => 'Setup Your Store', 'subtitle' => null, 'content' => 'Add your products, set up branches, and configure your settings. Import existing data via CSV with one click.', 'icon_class' => 'fas fa-cogs', 'image_url' => null, 'sort_order' => 2, 'is_active' => 1],
        ['section' => 'how_it_works', 'block_key' => 'sell', 'title' => 'Start Selling', 'subtitle' => null, 'content' => 'Process sales, manage inventory, and serve customers online or in-store. Track everything from one dashboard.', 'icon_class' => 'fas fa-chart-line', 'image_url' => null, 'sort_order' => 3, 'is_active' => 1],

        // Trust Strip
        ['section' => 'trust_strip', 'block_key' => 'secure', 'title' => 'Bank-Level Security', 'subtitle' => null, 'content' => null, 'icon_class' => 'fas fa-shield-alt', 'image_url' => null, 'sort_order' => 1, 'is_active' => 1],
        ['section' => 'trust_strip', 'block_key' => 'offline', 'title' => 'Offline Capable', 'subtitle' => null, 'content' => null, 'icon_class' => 'fas fa-wifi', 'image_url' => null, 'sort_order' => 2, 'is_active' => 1],
        ['section' => 'trust_strip', 'block_key' => 'support', 'title' => '24/7 Support', 'subtitle' => null, 'content' => null, 'icon_class' => 'fas fa-headset', 'image_url' => null, 'sort_order' => 3, 'is_active' => 1],

        // AI Slides
        ['section' => 'ai_slides', 'block_key' => 'smart_ordering', 'title' => 'Smart Ordering', 'subtitle' => null, 'content' => 'AI analyzes your sales patterns and automatically suggests optimal reorder quantities. Never overstock or understock again.', 'icon_class' => 'fas fa-wand-magic-sparkles', 'image_url' => null, 'sort_order' => 1, 'is_active' => 1],
        ['section' => 'ai_slides', 'block_key' => 'insights', 'title' => 'Customer Insights', 'subtitle' => null, 'content' => 'Understand your best customers, peak hours, and top products. Make data-driven decisions with AI-powered analytics.', 'icon_class' => 'fas fa-brain', 'image_url' => null, 'sort_order' => 2, 'is_active' => 1],
        ['section' => 'ai_slides', 'block_key' => 'forecasting', 'title' => 'Sales Forecasting', 'subtitle' => null, 'content' => 'Predict future sales trends based on historical data, seasonality, and market patterns. Plan ahead with confidence.', 'icon_class' => 'fas fa-chart-line', 'image_url' => null, 'sort_order' => 3, 'is_active' => 1],
    ];

    $stmt = $pdo->prepare("INSERT INTO pos_landing_blocks (section, block_key, title, subtitle, content, image_url, icon_class, sort_order, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    foreach ($blocks as $block) {
        $stmt->execute([
            $block['section'], $block['block_key'], $block['title'], $block['subtitle'],
            $block['content'], $block['image_url'], $block['icon_class'], $block['sort_order'], $block['is_active']
        ]);
    }

    echo "Seeded " . count($blocks) . " landing blocks successfully.\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
