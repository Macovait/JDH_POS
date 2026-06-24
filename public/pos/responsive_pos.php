<?php
/**
 * Responsive POS Interface
 * Mobile-first design with fluid layouts
 */

session_name('jakababa_saas_sid');
session_start();

require_once __DIR__ . '/../../src/paths.php';
safe_require('auth.php', 'src', true);
safe_require('db.php', 'src', true);
safe_require('functions.php', 'src', true);

require_login();

$pdo = get_db_connection();
$tenant_id = get_current_tenant_id();
$branch_id = get_current_branch_id();
$user_id = get_current_user_id();

if (!$tenant_id) {
    header('Location: /JDH_POS/public/auth/login.php?error=tenant_required');
    exit;
}

$business_type = get_current_business_type($tenant_id);

$stmt = $pdo->prepare("SELECT name FROM branches WHERE id = ? AND tenant_id = ?");
$stmt->execute([$branch_id, $tenant_id]);
$branch = $stmt->fetch();
$branch_name = $branch['name'] ?? 'Main Branch';

$user_name = get_current_user_name();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="theme-color" content="#0F172A">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <title>POS | <?php echo htmlspecialchars($branch_name); ?></title>
    
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.5.1/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --primary-dark: #0F172A;
            --primary-light: #1E293B;
            --accent: #FBBF24;
            --accent-dark: #F59E0B;
            --text-primary: #F8FAFC;
            --text-secondary: #94A3B8;
            --text-muted: #64748B;
            --success: #22C55E;
            --warning: #FBBF24;
            --danger: #EF4444;
            --border: #334155;
            --border-light: #475569;
            
            --font-base: 1rem;
            --font-sm: 0.875rem;
            --font-xs: 0.75rem;
            
            --space-xs: 0.25rem;
            --space-sm: 0.5rem;
            --space-md: 1rem;
            --space-lg: 1.5rem;
            --space-xl: 2rem;
            
            --radius-sm: 0.375rem;
            --radius-md: 0.5rem;
            --radius-lg: 0.75rem;
            --radius-xl: 1rem;
            
            --shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.3);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.4);
            
            --touch-target-min: 2.75rem;
        }

        *, *::before, *::after {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        html {
            font-size: 16px;
            -webkit-text-size-adjust: 100%;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: var(--primary-dark);
            color: var(--text-primary);
            min-height: 100vh;
            min-height: 100dvh;
            overflow-x: hidden;
            line-height: 1.5;
        }

        .app-container {
            display: flex;
            flex-direction: column;
            min-height: 100vh;
            min-height: 100dvh;
            max-width: 100vw;
            overflow: hidden;
        }

        /* Header */
        .pos-header {
            background: var(--primary-light);
            border-bottom: 1px solid var(--border);
            padding: var(--space-sm) var(--space-md);
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: var(--space-md);
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .pos-header-brand {
            display: flex;
            align-items: center;
            gap: var(--space-sm);
        }

        .pos-logo {
            width: 2.5rem;
            height: 2.5rem;
            background: var(--accent);
            border-radius: var(--radius-lg);
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .pos-logo i {
            font-size: 1.25rem;
            color: var(--primary-dark);
        }

        .pos-branch-info {
            min-width: 0;
        }

        .pos-branch-name {
            font-weight: 600;
            font-size: var(--font-sm);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .pos-user-info {
            font-size: var(--font-xs);
            color: var(--text-secondary);
        }

        .pos-header-actions {
            display: flex;
            align-items: center;
            gap: var(--space-sm);
        }

        .pos-date-display {
            font-size: var(--font-xs);
            color: var(--text-secondary);
            text-align: right;
        }

        /* Header Buttons */
        .header-btn {
            width: 2.75rem;
            height: 2.75rem;
            border-radius: var(--radius-md);
            background: var(--primary-dark);
            border: 1px solid var(--border);
            color: var(--text-secondary);
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.2s ease;
            font-size: 1rem;
            -webkit-tap-highlight-color: transparent;
        }

        .header-btn:active {
            background: var(--border);
            transform: scale(0.95);
        }

        .header-btn-badge {
            position: relative;
        }

        .header-btn-badge::after {
            content: '';
            position: absolute;
            top: 0.25rem;
            right: 0.25rem;
            width: 0.5rem;
            height: 0.5rem;
            background: var(--danger);
            border-radius: 50%;
        }

        /* Main Content Area */
        .pos-main {
            flex: 1;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }

        /* Search Bar */
        .pos-search {
            padding: var(--space-md);
            background: var(--primary-dark);
            border-bottom: 1px solid var(--border);
        }

        .search-input-wrapper {
            position: relative;
            display: flex;
            align-items: center;
        }

        .search-icon {
            position: absolute;
            left: var(--space-md);
            color: var(--text-muted);
            pointer-events: none;
        }

        .search-input {
            width: 100%;
            padding: var(--space-md) var(--space-md) var(--space-md) 2.75rem;
            background: var(--primary-light);
            border: 1px solid var(--border);
            border-radius: var(--radius-lg);
            color: var(--text-primary);
            font-size: var(--font-base);
            outline: none;
            transition: border-color 0.2s;
            -webkit-appearance: none;
            appearance: none;
        }

        .search-input::placeholder {
            color: var(--text-muted);
        }

        .search-input:focus {
            border-color: var(--accent);
        }

        /* Product Grid */
        .product-section {
            flex: 1;
            overflow-y: auto;
            overflow-x: hidden;
            padding: var(--space-md);
        }

        .product-section::-webkit-scrollbar {
            width: 0.375rem;
        }

        .product-section::-webkit-scrollbar-track {
            background: var(--primary-dark);
        }

        .product-section::-webkit-scrollbar-thumb {
            background: var(--border);
            border-radius: 0.25rem;
        }

        /* Category Tabs */
        .category-tabs {
            display: flex;
            gap: var(--space-sm);
            padding-bottom: var(--space-md);
            overflow-x: auto;
            scrollbar-width: none;
            -ms-overflow-style: none;
        }

        .category-tabs::-webkit-scrollbar {
            display: none;
        }

        .category-tab {
            padding: var(--space-sm) var(--space-md);
            background: var(--primary-light);
            border: 1px solid var(--border);
            border-radius: var(--radius-xl);
            color: var(--text-secondary);
            font-size: var(--font-sm);
            white-space: nowrap;
            cursor: pointer;
            transition: all 0.2s;
            -webkit-tap-highlight-color: transparent;
        }

        .category-tab:active {
            transform: scale(0.95);
        }

        .category-tab.active {
            background: var(--accent);
            border-color: var(--accent);
            color: var(--primary-dark);
            font-weight: 600;
        }

        /* Products Grid - Mobile First */
        .products-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: var(--space-md);
        }

        .product-card {
            background: var(--primary-light);
            border: 1px solid var(--border);
            border-radius: var(--radius-lg);
            padding: var(--space-md);
            cursor: pointer;
            transition: all 0.2s;
            -webkit-tap-highlight-color: transparent;
            display: flex;
            flex-direction: column;
            min-height: 0;
        }

        @media (hover: hover) {
            .product-card:hover {
                border-color: var(--accent);
                transform: translateY(-0.125rem);
            }
        }

        .product-card:active {
            transform: scale(0.98);
            background: var(--border);
        }

        .product-image {
            width: 100%;
            aspect-ratio: 1;
            background: var(--primary-dark);
            border-radius: var(--radius-md);
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: var(--space-sm);
            overflow: hidden;
        }

        .product-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .product-image-placeholder {
            font-size: 2rem;
            color: var(--text-muted);
        }

        .product-name {
            font-weight: 500;
            font-size: var(--font-sm);
            color: var(--text-primary);
            margin-bottom: var(--space-xs);
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
            line-height: 1.3;
        }

        .product-sku {
            font-size: var(--font-xs);
            color: var(--text-muted);
            margin-bottom: var(--space-xs);
        }

        .product-price {
            font-weight: 700;
            font-size: var(--font-base);
            color: var(--accent);
            margin-top: auto;
        }

        .product-price .currency {
            font-size: var(--font-xs);
            font-weight: 400;
            color: var(--text-secondary);
        }

        .product-stock {
            font-size: var(--font-xs);
            color: var(--text-muted);
            margin-top: var(--space-xs);
        }

        .product-stock.low {
            color: var(--warning);
        }

        .product-stock.out {
            color: var(--danger);
        }

        .product-tag-dots {
            display: flex;
            align-items: center;
            gap: 3px;
            margin: 2px 0;
            justify-content: center;
            min-height: 8px;
        }
        .tag-dot {
            width: 6px;
            height: 6px;
            border-radius: 50%;
            display: inline-block;
            border: 1px solid rgba(255,255,255,0.2);
        }
        .tag-dot-more {
            font-size: 0.55rem;
            color: var(--text-muted);
            line-height: 1;
        }

        /* Cart Panel */
        .cart-panel {
            background: var(--primary-light);
            border-top: 1px solid var(--border);
            display: flex;
            flex-direction: column;
            max-height: 40vh;
            transition: max-height 0.3s ease;
        }

        .cart-panel.collapsed {
            max-height: 4rem;
            overflow: hidden;
        }

        .cart-header {
            padding: var(--space-md);
            display: flex;
            align-items: center;
            justify-content: space-between;
            cursor: pointer;
            flex-shrink: 0;
        }

        .cart-header-left {
            display: flex;
            align-items: center;
            gap: var(--space-sm);
        }

        .cart-count {
            background: var(--accent);
            color: var(--primary-dark);
            font-weight: 700;
            font-size: var(--font-xs);
            min-width: 1.5rem;
            height: 1.5rem;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .cart-items {
            flex: 1;
            overflow-y: auto;
            padding: 0 var(--space-md) var(--space-md);
        }

        .cart-item {
            display: flex;
            align-items: center;
            gap: var(--space-md);
            padding: var(--space-sm) 0;
            border-bottom: 1px solid var(--border);
        }

        .cart-item:last-child {
            border-bottom: none;
        }

        .cart-item-details {
            flex: 1;
            min-width: 0;
        }

        .cart-item-name {
            font-weight: 500;
            font-size: var(--font-sm);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .cart-item-price {
            font-size: var(--font-xs);
            color: var(--text-secondary);
        }

        .cart-item-qty {
            display: flex;
            align-items: center;
            gap: var(--space-xs);
        }

        .qty-btn {
            width: 2rem;
            height: 2rem;
            border-radius: var(--radius-sm);
            background: var(--primary-dark);
            border: 1px solid var(--border);
            color: var(--text-primary);
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            font-size: var(--font-base);
            -webkit-tap-highlight-color: transparent;
        }

        .qty-btn:active {
            background: var(--border);
        }

        .cart-item-total {
            font-weight: 600;
            font-size: var(--font-sm);
            color: var(--accent);
            min-width: 4rem;
            text-align: right;
        }

        /* Cart Footer */
        .cart-footer {
            padding: var(--space-md);
            border-top: 1px solid var(--border);
            background: var(--primary-dark);
        }

        .cart-summary {
            margin-bottom: var(--space-md);
        }

        .cart-summary-row {
            display: flex;
            justify-content: space-between;
            padding: var(--space-xs) 0;
            font-size: var(--font-sm);
        }

        .cart-summary-label {
            color: var(--text-secondary);
        }

        .cart-summary-value {
            font-weight: 600;
        }

        .cart-summary-total {
            font-size: var(--font-lg);
            color: var(--text-primary);
            border-top: 1px solid var(--border);
            padding-top: var(--space-sm);
            margin-top: var(--space-sm);
        }

        .cart-summary-total .cart-summary-value {
            color: var(--accent);
            font-size: var(--font-xl);
        }

        /* Buttons */
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: var(--space-sm);
            padding: var(--space-md) var(--space-lg);
            border-radius: var(--radius-lg);
            font-weight: 600;
            font-size: var(--font-base);
            cursor: pointer;
            transition: all 0.2s;
            border: none;
            outline: none;
            -webkit-tap-highlight-color: transparent;
            min-height: var(--touch-target-min);
        }

        .btn:active {
            transform: scale(0.98);
        }

        .btn-primary {
            background: var(--accent);
            color: var(--primary-dark);
        }

        .btn-primary:active {
            background: var(--accent-dark);
        }

        .btn-secondary {
            background: var(--primary-light);
            border: 1px solid var(--border);
            color: var(--text-primary);
        }

        .btn-danger {
            background: var(--danger);
            color: white;
        }

        .btn-full {
            width: 100%;
        }

        .btn-lg {
            padding: var(--space-lg) var(--space-xl);
            font-size: var(--font-lg);
        }

        .cart-actions {
            display: flex;
            gap: var(--space-md);
        }

        /* Quick Amount Grid */
        .quick-amounts {
            padding: var(--space-md);
            border-top: 1px solid var(--border);
            background: var(--primary-dark);
        }

        .quick-amounts-label {
            font-size: var(--font-xs);
            color: var(--text-secondary);
            margin-bottom: var(--space-sm);
        }

        .quick-amounts-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: var(--space-sm);
        }

        .quick-amount-btn {
            padding: var(--space-sm) var(--space-xs);
            background: var(--primary-light);
            border: 1px solid var(--border);
            border-radius: var(--radius-md);
            color: var(--text-primary);
            font-size: var(--font-sm);
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            -webkit-tap-highlight-color: transparent;
        }

        .quick-amount-btn:active {
            background: var(--border);
            transform: scale(0.95);
        }

        /* Empty State */
        .empty-state {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: var(--space-xl);
            text-align: center;
            color: var(--text-muted);
        }

        .empty-state i {
            font-size: 3rem;
            margin-bottom: var(--space-md);
            opacity: 0.5;
        }

        .empty-state p {
            font-size: var(--font-sm);
        }

        /* Loading State */
        .loading-skeleton {
            background: linear-gradient(90deg, 
                var(--primary-light) 25%, 
                var(--border) 50%, 
                var(--primary-light) 75%
            );
            background-size: 200% 100%;
            animation: shimmer 1.5s infinite;
            border-radius: var(--radius-md);
        }

        @keyframes shimmer {
            0% { background-position: 200% 0; }
            100% { background-position: -200% 0; }
        }

        .skeleton-product {
            aspect-ratio: 1;
            border-radius: var(--radius-lg);
        }

        /* Toast Notifications */
        .toast-container {
            position: fixed;
            bottom: var(--space-lg);
            left: 50%;
            transform: translateX(-50%);
            z-index: 1000;
            display: flex;
            flex-direction: column;
            gap: var(--space-sm);
            width: calc(100% - var(--space-xl));
            max-width: 28rem;
        }

        .toast {
            background: var(--primary-light);
            border: 1px solid var(--border);
            border-radius: var(--radius-lg);
            padding: var(--space-md);
            display: flex;
            align-items: center;
            gap: var(--space-md);
            box-shadow: var(--shadow-lg);
            animation: slideUp 0.3s ease;
        }

        .toast.success {
            border-color: var(--success);
        }

        .toast.error {
            border-color: var(--danger);
        }

        .toast i {
            font-size: 1.25rem;
        }

        .toast.success i {
            color: var(--success);
        }

        .toast.error i {
            color: var(--danger);
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(100%);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Modal */
        .modal-overlay {
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.75);
            display: flex;
            align-items: flex-end;
            justify-content: center;
            z-index: 1000;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s;
        }

        .modal-overlay.active {
            opacity: 1;
            visibility: visible;
        }

        .modal-content {
            background: var(--primary-light);
            border-radius: var(--radius-xl) var(--radius-xl) 0 0;
            width: 100%;
            max-width: 100%;
            max-height: 85vh;
            overflow-y: auto;
            transform: translateY(100%);
            transition: transform 0.3s ease;
        }

        .modal-overlay.active .modal-content {
            transform: translateY(0);
        }

        .modal-header {
            padding: var(--space-md);
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: sticky;
            top: 0;
            background: var(--primary-light);
        }

        .modal-title {
            font-weight: 600;
            font-size: var(--font-lg);
        }

        .modal-close {
            width: 2.5rem;
            height: 2.5rem;
            border-radius: var(--radius-md);
            background: var(--primary-dark);
            border: 1px solid var(--border);
            color: var(--text-secondary);
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            -webkit-tap-highlight-color: transparent;
        }

        .modal-body {
            padding: var(--space-md);
        }

        .modal-footer {
            padding: var(--space-md);
            border-top: 1px solid var(--border);
            display: flex;
            gap: var(--space-md);
            position: sticky;
            bottom: 0;
            background: var(--primary-light);
        }

        /* Receipt Modal */
        .receipt-content {
            font-family: 'Courier New', monospace;
            font-size: var(--font-sm);
            line-height: 1.6;
        }

        .receipt-header {
            text-align: center;
            padding-bottom: var(--space-md);
            border-bottom: 1px dashed var(--border);
            margin-bottom: var(--space-md);
        }

        .receipt-logo {
            font-size: 1.5rem;
            color: var(--accent);
            margin-bottom: var(--space-sm);
        }

        .receipt-items {
            padding: var(--space-md) 0;
            border-bottom: 1px dashed var(--border);
        }

        .receipt-item {
            display: flex;
            justify-content: space-between;
            padding: var(--space-xs) 0;
        }

        .receipt-totals {
            padding-top: var(--space-md);
        }

        .receipt-total-row {
            display: flex;
            justify-content: space-between;
            padding: var(--space-xs) 0;
            font-weight: 600;
        }

        /* Media Queries - Tablet and Up */
        @media (min-width: 48rem) {
            .products-grid {
                grid-template-columns: repeat(3, 1fr);
            }
            
            .product-section {
                padding: var(--space-lg);
            }
            
            .pos-search {
                padding: var(--space-lg);
            }
            
            .pos-header {
                padding: var(--space-md) var(--space-lg);
            }
        }

        /* Media Queries - Desktop */
        @media (min-width: 64rem) {
            :root {
                --font-base: 1rem;
            }

            .pos-layout {
                display: grid;
                grid-template-columns: 1fr 22rem;
                height: 100vh;
                height: 100dvh;
            }

            .pos-layout .product-section {
                height: calc(100vh - var(--header-height));
                overflow-y: auto;
            }

            .products-grid {
                grid-template-columns: repeat(4, 1fr);
            }

            .product-card {
                padding: var(--space-lg);
            }

            .cart-panel {
                border-top: none;
                border-left: 1px solid var(--border);
                max-height: none;
                height: 100%;
            }

            .cart-panel.collapsed {
                max-height: none;
            }

            .cart-header {
                cursor: default;
            }

            .cart-items {
                flex: 1;
                overflow-y: auto;
            }
            
            .quick-amounts {
                position: sticky;
                bottom: 0;
            }
        }

        /* Large Desktop */
        @media (min-width: 80rem) {
            .products-grid {
                grid-template-columns: repeat(5, 1fr);
            }
        }

        /* Extra Large Desktop */
        @media (min-width: 100rem) {
            .products-grid {
                grid-template-columns: repeat(6, 1fr);
            }
        }

        /* Landscape orientation optimization */
        @media (orientation: landscape) and (max-height: 30rem) {
            .pos-header {
                padding: var(--space-xs) var(--space-md);
            }
            
            .product-image {
                aspect-ratio: 1.5;
            }
            
            .category-tabs {
                padding-bottom: var(--space-sm);
            }
            
            .category-tab {
                padding: var(--space-xs) var(--space-sm);
                font-size: var(--font-xs);
            }
        }

        /* High contrast mode support */
        @media (prefers-contrast: high) {
            .product-card {
                border-width: 2px;
            }
            
            .btn {
                border-width: 2px;
            }
        }

        /* Reduced motion support */
        @media (prefers-reduced-motion: reduce) {
            *, *::before, *::after {
                animation-duration: 0.01ms !important;
                animation-iteration-count: 1 !important;
                transition-duration: 0.01ms !important;
            }
        }

        /* Print styles */
        @media print {
            body {
                background: white;
                color: black;
            }

            .pos-header, 
            .pos-search, 
            .cart-panel,
            .btn,
            .header-btn {
                display: none;
            }

            .product-card {
                border: 1px solid #ccc;
                page-break-inside: avoid;
            }
        }
    </style>
</head>
<body>
    <div class="app-container">
        <!-- Header -->
        <header class="pos-header">
            <div class="pos-header-brand">
                <div class="pos-logo">
                    <i class="fas fa-cash-register"></i>
                </div>
                <div class="pos-branch-info">
                    <div class="pos-branch-name"><?php echo htmlspecialchars($branch_name); ?></div>
                    <div class="pos-user-info"><?php echo htmlspecialchars($user_name); ?></div>
                </div>
            </div>
            <div class="pos-header-actions">
                <div class="pos-date-display">
                    <div id="current-time">--:--</div>
                    <div id="current-date"><?php echo date('M d, Y'); ?></div>
                </div>
                <button class="header-btn" onclick="toggleCart()" aria-label="Toggle cart">
                    <i class="fas fa-shopping-cart"></i>
                    <span class="cart-count" id="cart-count" style="display: none;">0</span>
                </button>
                <button class="header-btn" onclick="openModal('settings-modal')" aria-label="Settings">
                    <i class="fas fa-cog"></i>
                </button>
            </div>
        </header>

        <!-- Main Content -->
        <main class="pos-main">
            <!-- Search -->
            <div class="pos-search">
                <div class="search-input-wrapper">
                    <i class="fas fa-search search-icon"></i>
                    <input type="text" 
                           class="search-input" 
                           id="product-search"
                           placeholder="Search products by name, SKU, or barcode..."
                           autocomplete="off"
                           aria-label="Search products">
                </div>
            </div>

            <!-- Product Section -->
            <div class="product-section">
                <!-- Category Tabs -->
                <div class="category-tabs" id="category-tabs" role="tablist">
                    <button class="category-tab active" data-category="all" role="tab" aria-selected="true">All</button>
                    <button class="category-tab" data-category="food" role="tab" aria-selected="false">Food</button>
                    <button class="category-tab" data-category="drinks" role="tab" aria-selected="false">Drinks</button>
                    <button class="category-tab" data-category="snacks" role="tab" aria-selected="false">Snacks</button>
                </div>

                <!-- Products Grid -->
                <div class="products-grid" id="products-grid" role="list">
                    <div class="empty-state" id="empty-state">
                        <i class="fas fa-shopping-basket"></i>
                        <p>No products found</p>
                    </div>
                </div>
            </div>
        </main>

        <!-- Cart Panel -->
        <aside class="cart-panel" id="cart-panel">
            <div class="cart-header" onclick="toggleCart()" role="button" tabindex="0" aria-expanded="true">
                <div class="cart-header-left">
                    <i class="fas fa-shopping-cart"></i>
                    <span>Current Sale</span>
                    <span class="cart-count" id="cart-count-header">0</span>
                </div>
                <div class="cart-header-right">
                    <i class="fas fa-chevron-up" id="cart-toggle-icon"></i>
                </div>
            </div>

            <div class="cart-items" id="cart-items">
                <div class="empty-state">
                    <i class="fas fa-cart-plus"></i>
                    <p>Add products to start a sale</p>
                </div>
            </div>

            <div class="quick-amounts" id="quick-amounts">
                <div class="quick-amounts-label">Quick Amount</div>
                <div class="quick-amounts-grid">
                    <button class="quick-amount-btn" data-amount="100">100</button>
                    <button class="quick-amount-btn" data-amount="200">200</button>
                    <button class="quick-amount-btn" data-amount="500">500</button>
                    <button class="quick-amount-btn" data-amount="1000">1000</button>
                </div>
            </div>

            <div class="cart-footer">
                <div class="cart-summary">
                    <div class="cart-summary-row">
                        <span class="cart-summary-label">Subtotal</span>
                        <span class="cart-summary-value" id="subtotal">KSh 0</span>
                    </div>
                    <div class="cart-summary-row">
                        <span class="cart-summary-label">Tax (16%)</span>
                        <span class="cart-summary-value" id="tax">KSh 0</span>
                    </div>
                    <div class="cart-summary-row cart-summary-total">
                        <span class="cart-summary-label">Total</span>
                        <span class="cart-summary-value" id="total">KSh 0</span>
                    </div>
                </div>

                <div class="cart-actions">
                    <button class="btn btn-secondary btn-lg" onclick="holdSale()" id="hold-btn">
                        <i class="fas fa-pause"></i> Hold
                    </button>
                    <button class="btn btn-primary btn-lg btn-full" onclick="processSale()" id="checkout-btn">
                        <i class="fas fa-credit-card"></i> Checkout
                    </button>
                </div>
            </div>
        </aside>
    </div>

    <!-- Receipt Modal -->
    <div class="modal-overlay" id="receipt-modal" onclick="closeModalOnOverlay(event)">
        <div class="modal-content" role="dialog" aria-label="Receipt">
            <div class="modal-header">
                <h2 class="modal-title" id="receipt-title">Receipt</h2>
                <button class="modal-close" onclick="closeModal('receipt-modal')" aria-label="Close">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <div class="receipt-content" id="receipt-content">
                    <!-- Receipt content loaded here -->
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="printReceipt()">
                    <i class="fas fa-print"></i> Print
                </button>
                <button class="btn btn-primary" onclick="newSale()">
                    <i class="fas fa-plus"></i> New Sale
                </button>
            </div>
        </div>
    </div>

    <!-- Settings Modal -->
    <div class="modal-overlay" id="settings-modal" onclick="closeModalOnOverlay(event)">
        <div class="modal-content" role="dialog" aria-label="Settings">
            <div class="modal-header">
                <h2 class="modal-title" id="settings-title">Settings</h2>
                <button class="modal-close" onclick="closeModal('settings-modal')" aria-label="Close">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <div style="padding: var(--space-md);">
                    <p style="color: var(--text-secondary); font-size: var(--font-sm);">
                        Settings panel - Configure your POS preferences here
                    </p>
                </div>
            </div>
        </div>
    </div>

    <!-- Toast Container -->
    <div class="toast-container" id="toast-container"></div>

    <script>
        const IMAGE_BASE_URL = '<?php echo function_exists('base_url') ? rtrim(base_url(''), '/') : ''; ?>';

        // State
        const state = {
            products: [],
            cart: [],
            cartCollapsed: false,
            selectedCategory: 'all',
            searchQuery: ''
        };

        // Initialize
        document.addEventListener('DOMContentLoaded', () => {
            initTime();
            loadProducts();
            initEventListeners();
        });

        function initTime() {
            const now = new Date();
            const timeEl = document.getElementById('current-time');
            const dateEl = document.getElementById('current-date');

            function updateTime() {
                const now = new Date();
                if (timeEl) timeEl.textContent = now.toLocaleTimeString('en-US', { 
                    hour: '2-digit', 
                    minute: '2-digit',
                    hour12: false 
                });
            }

            updateTime();
            setInterval(updateTime, 1000);

            if (dateEl) {
                dateEl.textContent = now.toLocaleDateString('en-US', { 
                    month: 'short', 
                    day: 'numeric', 
                    year: 'numeric' 
                });
            }
        }

        function initEventListeners() {
            // Search input
            const searchInput = document.getElementById('product-search');
            if (searchInput) {
                searchInput.addEventListener('input', debounce((e) => {
                    state.searchQuery = e.target.value.toLowerCase();
                    filterProducts();
                }, 300));
            }

            // Category tabs
            document.querySelectorAll('.category-tab').forEach(tab => {
                tab.addEventListener('click', () => {
                    document.querySelectorAll('.category-tab').forEach(t => {
                        t.classList.remove('active');
                        t.setAttribute('aria-selected', 'false');
                    });
                    tab.classList.add('active');
                    tab.setAttribute('aria-selected', 'true');
                    state.selectedCategory = tab.dataset.category;
                    filterProducts();
                });
            });

            // Quick amount buttons
            document.querySelectorAll('.quick-amount-btn').forEach(btn => {
                btn.addEventListener('click', () => {
                    const amount = parseInt(btn.dataset.amount);
                    addQuickAmount(amount);
                });
            });

            // Keyboard shortcuts
            document.addEventListener('keydown', (e) => {
                // F1 or Ctrl+F - Focus search
                if (e.key === 'F1' || (e.ctrlKey && e.key === 'f')) {
                    e.preventDefault();
                    searchInput?.focus();
                }
                // Escape - Close modals
                if (e.key === 'Escape') {
                    closeAllModals();
                }
                // F2 - Toggle cart
                if (e.key === 'F2') {
                    e.preventDefault();
                    toggleCart();
                }
                // F3 - Checkout
                if (e.key === 'F3') {
                    e.preventDefault();
                    processSale();
                }
            });

            // Touch gesture for cart
            let touchStartY = 0;
            const cartPanel = document.getElementById('cart-panel');
            if (cartPanel) {
                cartPanel.addEventListener('touchstart', (e) => {
                    touchStartY = e.touches[0].clientY;
                }, { passive: true });
            }
        }

        function loadProducts() {
            const grid = document.getElementById('products-grid');
            if (!grid) return;

            // Show loading skeletons
            grid.innerHTML = Array(12).fill('<div class="skeleton-product loading-skeleton"></div>').join('');

            // Fetch products via API
            fetch(`/public/pos/fetch_products.php?branch_id=${<?php echo $branch_id; ?>}&limit=50`)
                .then(res => res.json())
                .then(data => {
                    state.products = data.products || [];
                    renderProducts();
                })
                .catch(err => {
                    console.error('Failed to load products:', err);
                    showToast('Failed to load products', 'error');
                });
        }

        function renderProducts() {
            const grid = document.getElementById('products-grid');
            const emptyState = document.getElementById('empty-state');
            if (!grid) return;

            const filtered = getFilteredProducts();

            if (filtered.length === 0) {
                if (emptyState) emptyState.style.display = 'flex';
                grid.innerHTML = '';
                if (emptyState) grid.appendChild(emptyState);
                return;
            }

            if (emptyState) emptyState.style.display = 'none';

            grid.innerHTML = filtered.map(product => {
                const tagDots = (product.tags && product.tags.length)
                    ? `<div class="product-tag-dots">${product.tags.slice(0, 3).map(t => `<span class="tag-dot" style="background:${t.color}" title="${t.name}"></span>`).join('')}${product.tags.length > 3 ? '<span class="tag-dot-more">+</span>' : ''}</div>`
                    : '';
                return `
                <article class="product-card"
                         onclick="addToCart(${product.id})"
                         onkeydown="if(event.key==='Enter')addToCart(${product.id})"
                         tabindex="0"
                         role="button"
                         aria-label="Add ${product.name || 'product'} to cart">
                    <div class="product-image">
                        ${product.image
                            ? `<img src="${IMAGE_BASE_URL + '/' + String(product.image).replace(/^\/+/, '')}" alt="${product.name || 'Product'}" loading="lazy" onerror="this.style.display='none';this.parentNode.querySelector('.product-image-placeholder').style.display='flex'">`
                            : ''}
                        <i class="fas fa-box product-image-placeholder" style="${product.image ? 'display:none' : ''}"></i>
                    </div>
                    <div class="product-name">${product.name || '<span style="color:var(--text-muted)">Unnamed</span>'}</div>
                    <div class="product-sku">${product.sku || ''}</div>
                    ${tagDots}
                    <div class="product-price">
                        <span class="currency">KSh</span>${parseFloat(product.price || 0).toLocaleString()}
                    </div>
                    ${product.stock !== undefined ? `
                        <div class="product-stock ${product.stock < 5 ? 'low' : ''} ${product.stock === 0 ? 'out' : ''}">
                            ${product.stock === 0 ? 'Out of stock' : `Stock: ${product.stock}`}
                        </div>
                    ` : ''}
                </article>
            `}).join('');
        }

        function getFilteredProducts() {
            return state.products.filter(product => {
                const matchesCategory = state.selectedCategory === 'all' || 
                    product.category?.toLowerCase() === state.selectedCategory;
                const matchesSearch = !state.searchQuery || 
                    product.name?.toLowerCase().includes(state.searchQuery) ||
                    product.sku?.toLowerCase().includes(state.searchQuery) ||
                    product.barcode?.toLowerCase().includes(state.searchQuery);
                return matchesCategory && matchesSearch;
            });
        }

        function filterProducts() {
            renderProducts();
        }

        function addToCart(productId) {
            const product = state.products.find(p => p.id === productId);
            if (!product) return;

            const existingItem = state.cart.find(item => item.id === productId);

            if (existingItem) {
                // Check stock
                const maxQty = product.stock || 999;
                if (existingItem.quantity < maxQty) {
                    existingItem.quantity++;
                } else {
                    showToast('Maximum stock reached', 'error');
                    return;
                }
            } else {
                state.cart.push({
                    id: product.id,
                    name: product.name,
                    price: product.price,
                    quantity: 1
                });
            }

            renderCart();
            showToast(`Added ${product.name}`, 'success');
            hapticFeedback();
        }

        function addQuickAmount(amount) {
            // For quick amount, just set the tendered amount
            document.getElementById('tendered-amount')?.setAttribute('value', amount);
            showToast(`Quick amount: KSh ${amount}`, 'success');
            hapticFeedback();
        }

        function renderCart() {
            const cartItemsEl = document.getElementById('cart-items');
            const cartCountEl = document.getElementById('cart-count');
            const cartCountHeaderEl = document.getElementById('cart-count-header');
            const subtotalEl = document.getElementById('subtotal');
            const taxEl = document.getElementById('tax');
            const totalEl = document.getElementById('total');

            const itemCount = state.cart.reduce((sum, item) => sum + item.quantity, 0);

            // Update counts
            if (cartCountEl) cartCountEl.textContent = itemCount;
            if (cartCountHeaderEl) cartCountHeaderEl.textContent = itemCount;
            if (cartCountEl && itemCount > 0) cartCountEl.style.display = 'flex';
            else if (cartCountEl) cartCountEl.style.display = 'none';

            // Calculate totals
            const subtotal = state.cart.reduce((sum, item) => sum + (item.price * item.quantity), 0);
            const tax = subtotal * 0.16;
            const total = subtotal + tax;

            // Update totals
            if (subtotalEl) subtotalEl.textContent = `KSh ${subtotal.toLocaleString()}`;
            if (taxEl) taxEl.textContent = `KSh ${tax.toLocaleString()}`;
            if (totalEl) totalEl.textContent = `KSh ${total.toLocaleString()}`;

            // Render items
            if (!cartItemsEl) return;

            if (state.cart.length === 0) {
                cartItemsEl.innerHTML = `
                    <div class="empty-state">
                        <i class="fas fa-cart-plus"></i>
                        <p>Add products to start a sale</p>
                    </div>
                `;
                return;
            }

            cartItemsEl.innerHTML = state.cart.map(item => `
                <div class="cart-item">
                    <div class="cart-item-details">
                        <div class="cart-item-name">${item.name}</div>
                        <div class="cart-item-price">KSh ${parseFloat(item.price).toLocaleString()}</div>
                    </div>
                    <div class="cart-item-qty">
                        <button class="qty-btn" onclick="updateQuantity(${item.id}, -1)" aria-label="Decrease quantity">-</button>
                        <span>${item.quantity}</span>
                        <button class="qty-btn" onclick="updateQuantity(${item.id}, 1)" aria-label="Increase quantity">+</button>
                    </div>
                    <div class="cart-item-total">
                        KSh ${(item.price * item.quantity).toLocaleString()}
                    </div>
                </div>
            `).join('');
        }

        function updateQuantity(productId, change) {
            const item = state.cart.find(i => i.id === productId);
            if (!item) return;

            item.quantity += change;

            if (item.quantity <= 0) {
                state.cart = state.cart.filter(i => i.id !== productId);
            }

            renderCart();
        }

        function toggleCart() {
            const panel = document.getElementById('cart-panel');
            const icon = document.getElementById('cart-toggle-icon');
            
            if (!panel) return;

            state.cartCollapsed = !state.cartCollapsed;
            panel.classList.toggle('collapsed', state.cartCollapsed);

            if (icon) {
                icon.className = state.cartCollapsed ? 'fas fa-chevron-down' : 'fas fa-chevron-up';
            }
        }

        function processSale() {
            if (state.cart.length === 0) {
                showToast('Cart is empty', 'error');
                return;
            }

            const total = state.cart.reduce((sum, item) => sum + (item.price * item.quantity), 0);

            // Open payment modal
            openPaymentModal(total);
        }

        function openPaymentModal(total) {
            // For now, just show confirmation
            if (confirm(`Confirm sale of KSh ${total.toLocaleString()}?`)) {
                completeSale();
            }
        }

        function completeSale() {
            // Submit sale to API
            const saleData = {
                tenant_id: <?php echo $tenant_id; ?>,
                branch_id: <?php echo $branch_id; ?>,
                user_id: <?php echo $user_id; ?>,
                items: state.cart,
                business_type: '<?php echo $business_type; ?>'
            };

            fetch('/public/pos/process_sale.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(saleData)
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    showReceipt(data.receipt);
                    state.cart = [];
                    renderCart();
                    showToast('Sale completed!', 'success');
                } else {
                    showToast(data.message || 'Sale failed', 'error');
                }
            })
            .catch(err => {
                showToast('Failed to process sale', 'error');
            });
        }

        function showReceipt(receiptData) {
            const modal = document.getElementById('receipt-modal');
            const content = document.getElementById('receipt-content');
            
            if (content) {
                content.innerHTML = receiptData || '<p>Receipt data unavailable</p>';
            }

            openModal('receipt-modal');
        }

        function newSale() {
            closeModal('receipt-modal');
            state.cart = [];
            renderCart();
            loadProducts();
        }

        function holdSale() {
            showToast('Sale held', 'success');
        }

        function printReceipt() {
            window.print();
        }

        // Modal functions
        function openModal(modalId) {
            const modal = document.getElementById(modalId);
            if (modal) modal.classList.add('active');
        }

        function closeModal(modalId) {
            const modal = document.getElementById(modalId);
            if (modal) modal.classList.remove('active');
        }

        function closeModalOnOverlay(e) {
            if (e.target.classList.contains('modal-overlay')) {
                e.target.classList.remove('active');
            }
        }

        function closeAllModals() {
            document.querySelectorAll('.modal-overlay.active').forEach(modal => {
                modal.classList.remove('active');
            });
        }

        // Toast notification
        function showToast(message, type = 'info') {
            const container = document.getElementById('toast-container');
            if (!container) return;

            const toast = document.createElement('div');
            toast.className = `toast ${type}`;
            toast.innerHTML = `
                <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'error' ? '-times-circle' : 'info-circle'}"></i>
                <span>${message}</span>
            `;

            container.appendChild(toast);

            setTimeout(() => {
                toast.remove();
            }, 3000);
        }

        // Haptic feedback for mobile
        function hapticFeedback() {
            if (navigator.vibrate) {
                navigator.vibrate(50);
            }
        }

        // Utility functions
        function debounce(fn, delay) {
            let timeout;
            return function(...args) {
                clearTimeout(timeout);
                timeout = setTimeout(() => fn.apply(this, args), delay);
            };
        }
    </script>
</body>
</html>