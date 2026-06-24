<?php
/**
 * Admin Layout - app.php
 * Main layout wrapper for all admin pages
 * 
 * Usage:
 *   $current_page = 'dashboard';
 *   $page_title = 'Admin Dashboard';
 *   ob_start();
 *   // ... page content ...
 *   $content = ob_get_clean();
 *   require_once __DIR__ . '/layouts/app.php';
 */

require_once __DIR__ . '/../bootstrap.php';

// Prevent direct access
if (!isset($content)) {
    die('This file should not be accessed directly.');
}

$current_page = $current_page ?? 'dashboard';
$page_title = $page_title ?? 'Admin Panel';
?>
<!DOCTYPE html>
<html lang="en" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?> | JDH POS Admin</title>
    
    <!-- Tailwind CSS (Production Build) -->
    <link rel="stylesheet" href="<?php echo base_url('assets/css/app.css'); ?>">
    <link rel="stylesheet" href="<?php echo base_url('assets/css/laravel-crud.css'); ?>">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.5.1/css/all.min.css">
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        body {
            font-family: 'Inter', system-ui, sans-serif;
            background: #0b1120;
            min-height: 100vh;
        }

        /* Sidebar is fixed, main content needs margin */
        .main-content {
            margin-left: 16rem;
            min-height: 100vh;
        }

        /* Scrollbar styling */
        ::-webkit-scrollbar {
            width: 4px;
            height: 4px;
        }
        ::-webkit-scrollbar-track {
            background: transparent;
        }
        ::-webkit-scrollbar-thumb {
            background: rgba(251, 191, 36, 0.25);
            border-radius: 99px;
        }
        ::-webkit-scrollbar-thumb:hover {
            background: rgba(251, 191, 36, 0.45);
        }
    </style>
    
    <?php if (isset($extra_css)) echo $extra_css; ?>
</head>
<body class="antialiased text-slate-300">
    <!-- Sidebar -->
    <?php require_once __DIR__ . '/../components/sidebar.php'; ?>
    
    <!-- Main Content Area -->
    <div class="main-content">
        <!-- Header -->
        <?php require_once __DIR__ . '/../components/header.php'; ?>
        
        <!-- Page Content -->
        <main class="p-6">
            <?php echo $content; ?>
        </main>
    </div>
    
    <?php if (isset($extra_js)) echo $extra_js; ?>
</body>
</html>
