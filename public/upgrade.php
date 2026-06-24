<?php
/**
 * Jakababa POS - Upgrade Plan Page
 * 
 * This page allows users to upgrade their subscription plan
 * when they try to access features not available in their current plan.
 * 
 * @package JakababaPOS
 * @author Senior SaaS Architect
 * @version 1.0.0
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../src/FeatureAccess.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['user_id']) || !isset($_SESSION['tenant_id'])) {
    header('Location: /auth/login.php');
    exit;
}

// Get database connection
$db = getDB();

// Initialize FeatureAccess
$featureAccess = new FeatureAccess($db);

// Get current plan
$currentPlan = $featureAccess->getCompanyPlan();
$companyFeatures = $featureAccess->getCompanyFeatures();

// Get all available plans
$allPlans = $featureAccess->getAllPlans();

// Get requested feature (if any)
$requestedFeature = isset($_GET['feature']) ? $_GET['feature'] : null;
$errorMessage = isset($_GET['error']) ? $_GET['error'] : null;

// Get feature details if a specific feature was requested
$featureDetails = null;
if ($requestedFeature) {
    $features = $featureAccess->getAllFeatures();
    foreach ($features as $feature) {
        if ($feature['feature_key'] === $requestedFeature) {
            $featureDetails = $feature;
            break;
        }
    }
}

// Get brand colors
$brandPrimary = getenv('BRAND_PRIMARY') ?: '#1E3A8A';
$brandPrimaryDark = getenv('BRAND_PRIMARY_DARK') ?: '#0F2B5E';
$brandPrimaryLight = getenv('BRAND_PRIMARY_LIGHT') ?: '#3B82F6';
$brandAccent = getenv('BRAND_ACCENT') ?: '#FBBF24';
$brandAccentDark = getenv('BRAND_ACCENT_DARK') ?: '#F59E0B';
$appName = getenv('APP_NAME') ?: 'Jakababa POS';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Upgrade Plan - <?= htmlspecialchars($appName) ?></title>
    <link rel="stylesheet" href="assets/css/app.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.5.1/css/all.min.css">
    <style>
        :root {
            --brand-primary:
                <?= $brandPrimary ?>
            ;
            --brand-primary-dark:
                <?= $brandPrimaryDark ?>
            ;
            --brand-primary-light:
                <?= $brandPrimaryLight ?>
            ;
            --brand-accent:
                <?= $brandAccent ?>
            ;
            --brand-accent-dark:
                <?= $brandAccentDark ?>
            ;
        }

        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
        }

        .bg-slate-800/40 border border-slate-700/60 rounded-xl {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
        }

        .plan-card {
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .plan-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 12px 40px rgba(0, 0, 0, 0.15);
        }

        .plan-card.current {
            border: 2px solid var(--brand-primary);
            background: linear-gradient(135deg, rgba(30, 58, 138, 0.05) 0%, rgba(59, 130, 246, 0.05) 100%);
        }

        .plan-card.recommended {
            border: 2px solid var(--brand-accent);
            transform: scale(1.05);
        }

        .plan-card.recommended:hover {
            transform: scale(1.05) translateY(-5px);
        }

        .feature-list li {
            padding: 0.5rem 0;
            border-bottom: 1px solid #e5e7eb;
        }

        .feature-list li:last-child {
            border-bottom: none;
        }

        .btn-upgrade {
            background: linear-gradient(135deg, var(--brand-primary) 0%, var(--brand-primary-dark) 100%);
            color: white;
            padding: 0.75rem 1.5rem;
            border-radius: 0.5rem;
            font-weight: 600;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
        }

        .btn-upgrade:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(30, 58, 138, 0.4);
        }

        .btn-current {
            background: #e5e7eb;
            color: #6b7280;
            cursor: default;
        }

        .error-message {
            background: #fef2f2;
            border: 1px solid #fecaca;
            color: #dc2626;
            padding: 1rem;
            border-radius: 0.5rem;
            margin-bottom: 1rem;
        }

        .feature-highlight {
            background: linear-gradient(135deg, rgba(251, 191, 36, 0.1) 0%, rgba(245, 158, 11, 0.1) 100%);
            border: 1px solid rgba(251, 191, 36, 0.3);
            border-radius: 0.5rem;
            padding: 1rem;
            margin-bottom: 1rem;
        }
    </style>
</head>

<body class="font-sans">
    <div class="container mx-auto px-4 py-8">
        <!-- Header -->
        <div class="text-center mb-8">
            <a href="index.php" class="inline-block mb-4">
                <img src="/images/logo.9c89fc39.png" alt="<?= htmlspecialchars($appName) ?>" class="h-12 mx-auto">
            </a>
            <h1 class="text-3xl font-bold text-white mb-2">Upgrade Your Plan</h1>
            <p class="text-white/80">Unlock more features to grow your business</p>
        </div>

        <!-- Error Message -->
        <?php if ($errorMessage): ?>
            <div class="max-w-4xl mx-auto mb-6">
                <div class="error-message">
                    <?php
                    switch ($errorMessage) {
                        case 'database':
                            echo 'Database connection error. Please try again later.';
                            break;
                        case 'subscription':
                            echo 'Your subscription is not active. Please upgrade to continue.';
                            break;
                        case 'trial_expired':
                            echo 'Your trial period has expired. Please upgrade to continue.';
                            break;
                        case 'user_limit':
                            echo 'You have reached the maximum number of users for your plan. Please upgrade to add more users.';
                            break;
                        case 'branch_limit':
                            echo 'You have reached the maximum number of branches for your plan. Please upgrade to add more branches.';
                            break;
                        case 'product_limit':
                            echo 'You have reached the maximum number of products for your plan. Please upgrade to add more products.';
                            break;
                        default:
                            echo 'An error occurred. Please upgrade your plan to continue.';
                    }
                    ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Feature Highlight -->
        <?php if ($featureDetails): ?>
            <div class="max-w-4xl mx-auto mb-6">
                <div class="feature-highlight">
                    <div class="flex items-center">
                        <div class="mr-4">
                            <i class="fas fa-lock text-2xl text-yellow-600"></i>
                        </div>
                        <div>
                            <h3 class="font-semibold text-gray-800"><?= htmlspecialchars($featureDetails['name']) ?></h3>
                            <p class="text-gray-600 text-sm"><?= htmlspecialchars($featureDetails['description']) ?></p>
                            <p class="text-yellow-700 text-sm mt-1">
                                <i class="fas fa-info-circle mr-1"></i>
                                This feature is not available in your current plan.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Current Plan Info -->
        <?php if ($currentPlan): ?>
            <div class="max-w-4xl mx-auto mb-6">
                <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl rounded-lg p-4">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-gray-600">Current Plan</p>
                            <p class="font-semibold text-gray-800"><?= htmlspecialchars($currentPlan['name']) ?></p>
                        </div>
                        <div class="text-right">
                            <p class="text-sm text-gray-600">Status</p>
                            <p
                                class="font-semibold <?= $currentPlan['subscription_status'] === 'trialing' ? 'text-yellow-600' : 'text-green-600' ?>">
                                <?= ucfirst($currentPlan['subscription_status']) ?>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Plans Grid -->
        <div class="max-w-6xl mx-auto">
            <div class="grid md:grid-cols-2 lg:grid-cols-4 gap-3">
                <?php foreach ($allPlans as $plan): ?>
                    <?php
                    $isCurrent = $currentPlan && $currentPlan['id'] === $plan['id'];
                    $isRecommended = $plan['is_popular'] == 1;
                    $planFeatures = $featureAccess->getPlanFeatures($plan['id']);
                    ?>
                    <div
                        class="plan-card bg-slate-800/40 border border-slate-700/60 rounded-xl rounded-lg p-6 <?= $isCurrent ? 'current' : '' ?> <?= $isRecommended ? 'recommended' : '' ?>">
                        <?php if ($isRecommended): ?>
                            <div class="text-center mb-4">
                                <span class="bg-yellow-400 text-yellow-900 text-xs font-bold px-3 py-1 rounded-full">
                                    RECOMMENDED
                                </span>
                            </div>
                        <?php endif; ?>

                        <?php if ($isCurrent): ?>
                            <div class="text-center mb-4">
                                <span class="bg-blue-100 text-blue-800 text-xs font-bold px-3 py-1 rounded-full">
                                    CURRENT PLAN
                                </span>
                            </div>
                        <?php endif; ?>

                        <div class="text-center mb-4">
                            <h3 class="text-xl font-bold text-gray-800"><?= htmlspecialchars($plan['name']) ?></h3>
                            <p class="text-gray-600 text-sm"><?= htmlspecialchars($plan['description']) ?></p>
                        </div>

                        <div class="text-center mb-6">
                            <div class="text-3xl font-bold text-gray-800">
                                KES <?= number_format($plan['price'], 2) ?>
                            </div>
                            <p class="text-gray-600 text-sm">/ <?= $plan['billing_cycle'] ?></p>
                        </div>

                        <div class="mb-6">
                            <h4 class="font-semibold text-gray-800 mb-3">Features:</h4>
                            <ul class="feature-list text-sm text-gray-600">
                                <?php
                                // Get first 8 features for display
                                $displayFeatures = array_slice($planFeatures, 0, 8);
                                foreach ($displayFeatures as $featureKey):
                                    // Get feature name from database
                                    $featureName = $featureKey;
                                    foreach ($featureAccess->getAllFeatures() as $feature) {
                                        if ($feature['feature_key'] === $featureKey) {
                                            $featureName = $feature['name'];
                                            break;
                                        }
                                    }
                                    ?>
                                    <li class="flex items-center">
                                        <i class="fas fa-check text-green-500 mr-2"></i>
                                        <?= htmlspecialchars($featureName) ?>
                                    </li>
                                <?php endforeach; ?>
                                <?php if (count($planFeatures) > 8): ?>
                                    <li class="text-gray-500 italic">
                                        + <?= count($planFeatures) - 8 ?> more features
                                    </li>
                                <?php endif; ?>
                            </ul>
                        </div>

                        <div class="text-center">
                            <?php if ($isCurrent): ?>
                                <button class="btn-upgrade btn-current w-full" disabled>
                                    <i class="fas fa-check mr-2"></i>
                                    Current Plan
                                </button>
                            <?php else: ?>
                                <button class="btn-upgrade w-full" onclick="upgradePlan(<?= $plan['id'] ?>)">
                                    <?php if ($currentPlan && $plan['price'] > $currentPlan['price']): ?>
                                        <i class="fas fa-arrow-up mr-2"></i>
                                        Upgrade
                                    <?php elseif ($currentPlan && $plan['price'] < $currentPlan['price']): ?>
                                        <i class="fas fa-arrow-down mr-2"></i>
                                        Downgrade
                                    <?php else: ?>
                                        <i class="fas fa-exchange-alt mr-2"></i>
                                        Switch Plan
                                    <?php endif; ?>
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Back Link -->
        <div class="text-center mt-8">
            <a href="dashboard/home.php" class="text-white/80 hover:text-white">
                <i class="fas fa-arrow-left mr-2"></i>
                Back to Dashboard
            </a>
        </div>
    </div>

    <script>
        function upgradePlan(planId) {
            if (confirm('Are you sure you want to upgrade your plan?')) {
                // Show loading state
                event.target.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Processing...';
                event.target.disabled = true;

                // Make AJAX request to upgrade plan
                fetch('/ajax/upgrade_plan.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        plan_id: planId
                    })
                })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            alert('Plan upgraded successfully!');
                            window.location.href = 'dashboard/home.php';
                        } else {
                            alert('Error: ' + data.message);
                            event.target.innerHTML = 'Upgrade';
                            event.target.disabled = false;
                        }
                    })
                    .catch(error => {
                        alert('An error occurred. Please try again.');
                        event.target.innerHTML = 'Upgrade';
                        event.target.disabled = false;
                    });
            }
        }
    </script>
</body>

</html>