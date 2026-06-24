<?php
/**
 * Breadcrumb Navigation Component
 * Shows current page location and navigation path
 */

function generate_breadcrumbs() {
    $current_url = $_SERVER['REQUEST_URI'];
    $path_parts = explode('/', trim($current_url, '/'));
    $breadcrumbs = [];

    // Base breadcrumb
    $breadcrumbs[] = [
        'name' => 'Dashboard',
        'url' => base_url('dashboard/home.php'),
        'icon' => 'fas fa-tachometer-alt'
    ];

    // Build breadcrumbs based on URL path
    $current_path = '';
    foreach ($path_parts as $index => $part) {
        if (empty($part) || $part === 'JDH_POS' || $part === 'public') continue;

        $current_path .= '/' . $part;
        $name = format_breadcrumb_name($part, $current_path);
        $icon = get_breadcrumb_icon($part);

        // Don't add current page as clickable link
        $is_last = ($index === count($path_parts) - 1);

        $breadcrumbs[] = [
            'name' => $name,
            'url' => $is_last ? null : base_url($current_path),
            'icon' => $icon,
            'active' => $is_last
        ];
    }

    return $breadcrumbs;
}

function format_breadcrumb_name($part, $full_path) {
    // Convert URL parts to readable names
    $name_map = [
        'dashboard' => 'Dashboard',
        'home.php' => 'Overview',
        'inventory' => 'Inventory',
        'products' => 'Products',
        'automated_inventory.php' => 'Smart Inventory',
        'product_form.php' => 'Add Product',
        'customers' => 'Customers',
        'sales' => 'Sales',
        'reports' => 'Reports',
        'custom_builder.php' => 'Custom Reports',
        'users' => 'Users',
        'advanced_permissions.php' => 'Permissions',
        'marketing' => 'Marketing',
        'campaigns.php' => 'Campaigns',
        'campaign_builder.php' => 'Campaign Builder',
        'payments' => 'Payments',
        'stripe_gateway.php' => 'Stripe Gateway',
        'ai' => 'AI & Analytics',
        'recommendations.php' => 'AI Recommendations'
    ];

    // Check query parameters for specific items
    if (isset($_GET['id']) && is_numeric($_GET['id'])) {
        if (strpos($full_path, 'products') !== false) {
            return 'Edit Product #' . $_GET['id'];
        } elseif (strpos($full_path, 'customers') !== false) {
            return 'Customer #' . $_GET['id'];
        } elseif (strpos($full_path, 'campaigns') !== false) {
            return 'Edit Campaign #' . $_GET['id'];
        }
    }

    return $name_map[$part] ?? ucwords(str_replace(['_', '.php'], [' ', ''], $part));
}

function get_breadcrumb_icon($part) {
    $icon_map = [
        'dashboard' => 'fas fa-tachometer-alt',
        'inventory' => 'fas fa-boxes',
        'products' => 'fas fa-box',
        'customers' => 'fas fa-users',
        'sales' => 'fas fa-shopping-cart',
        'reports' => 'fas fa-chart-line',
        'users' => 'fas fa-user-cog',
        'marketing' => 'fas fa-bullhorn',
        'payments' => 'fas fa-credit-card',
        'ai' => 'fas fa-brain'
    ];

    return $icon_map[$part] ?? 'fas fa-circle';
}

function render_breadcrumbs() {
    $breadcrumbs = generate_breadcrumbs();

    if (count($breadcrumbs) <= 1) return '';

    $html = '<nav class="breadcrumb-nav" aria-label="Breadcrumb"><ol class="breadcrumb-list">';

    foreach ($breadcrumbs as $index => $crumb) {
        $is_last = ($index === count($breadcrumbs) - 1);

        $html .= '<li class="breadcrumb-item' . ($is_last ? ' active' : '') . '">';

        if (!$is_last && $crumb['url']) {
            $html .= '<a href="' . htmlspecialchars($crumb['url']) . '" class="breadcrumb-link">';
        }

        if ($crumb['icon']) {
            $html .= '<i class="' . $crumb['icon'] . ' breadcrumb-icon"></i>';
        }

        $html .= '<span class="breadcrumb-text">' . htmlspecialchars($crumb['name']) . '</span>';

        if (!$is_last && $crumb['url']) {
            $html .= '</a>';
        }

        if (!$is_last) {
            $html .= '<i class="fas fa-chevron-right breadcrumb-separator"></i>';
        }

        $html .= '</li>';
    }

    $html .= '</ol></nav>';

    return $html;
}
?>