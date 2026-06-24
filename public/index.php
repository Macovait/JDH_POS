<?php
// Landing Page - Dynamic Content from Database

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../src/paths.php';
require_once __DIR__ . '/../src/db.php';
require_once __DIR__ . '/../src/site_settings.php';
require_once __DIR__ . '/../src/LandingBlocks.php';
safe_require('auth.php', 'src', true);
start_session_secure();

// Redirect authenticated users to dashboard
if (!empty($_SESSION['user_id']) || !empty($_SESSION['tenant_id'])) {
    header('Location: ' . base_url('dashboard/home.php'));
    exit;
}

// Load site settings from DB (managed in admin/settings.php)
$settings = get_site_settings();
$appName = $settings['site_name'] ?? (getenv('APP_NAME') ?: 'JAKPOS');
$tagline = $settings['site_tagline'] ?? 'All-in-One POS System for African Businesses';
$metaDesc = $settings['seo_meta_description'] ?? 'Modern point-of-sale system for African businesses.';
$heroTitle = $settings['hero_title'] ?? 'The All-in-One AI System for African <span class="hero-title-highlight">Retail</span> and <span class="hero-title-highlight">Wholesale</span> Success';
$heroSubtitle = $settings['hero_subtitle'] ?? 'Manage your sales, inventory, and payments in one place. Keep your business running smoothly, efficiently, and intelligently — anywhere, online or offline.';
$heroCtaText = $settings['hero_cta_text'] ?? 'Get a Free Trial';
$heroCtaUrl = !empty($settings['hero_cta_url']) ? base_url($settings['hero_cta_url']) : base_url('auth/register.php');
$statsBiz = $settings['stats_businesses'] ?? '2,000+';
$statsUptime = $settings['stats_uptime'] ?? '99.9%';
$statsSupport = $settings['stats_support'] ?? '24/7';
$logoUrl = $settings['landing_logo'] ?? ($settings['site_logo_url'] ?? '');
$logoDarkUrl = $settings['landing_logo_dark'] ?? $logoUrl;
$faviconUrl = $settings['landing_favicon'] ?? ($settings['site_favicon_url'] ?? '');
$heroImage = $settings['landing_hero_image'] ?? '';
$heroImageAlt = $settings['landing_hero_image_alt'] ?? 'Multi-Device POS System';
$ogImage = $settings['landing_og_image'] ?? ($settings['seo_og_image'] ?? '');
$showDbPricing = ($settings['show_pricing_from_db'] ?? '1') === '1';
$whatsappNumber = $settings['whatsapp_number'] ?? '+254709000116';
$phoneNumber = $settings['phone_number'] ?? '+254 709000116';

// Load plans from DB for pricing section
$dbPlans = $showDbPricing ? get_public_plans() : [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title><?= htmlspecialchars($appName) ?> | <?= htmlspecialchars($tagline) ?></title>
    <meta name="description" content="<?= htmlspecialchars($metaDesc) ?>">
    <?php if ($faviconUrl): ?>
    <link rel="icon" href="<?= htmlspecialchars($faviconUrl) ?>" type="image/x-icon">
    <?php endif; ?>
    <?php if ($ogImage): ?>
    <meta property="og:image" content="<?= htmlspecialchars($ogImage) ?>">
    <meta property="og:title" content="<?= htmlspecialchars($appName) ?> | <?= htmlspecialchars($tagline) ?>">
    <meta property="og:description" content="<?= htmlspecialchars($metaDesc) ?>">
    <?php endif; ?>
    
    <!-- Preconnect for performance -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="preconnect" href="https://cdnjs.cloudflare.com">

    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,100;14..32,300;14..32,400;14..32,500;14..32,600;14..32,700;14..32,800;14..32,900&display=swap" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

    <!-- JSON-LD Structured Data -->
    <script type="application/ld+json">
    {
        "@context": "https://schema.org",
        "@type": "Organization",
        "name": "<?= htmlspecialchars($appName) ?>",
        "description": "<?= htmlspecialchars($metaDesc) ?>",
        "url": "<?= htmlspecialchars((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost')) ?>",
        <?php if ($logoUrl): ?>"logo": "<?= htmlspecialchars($logoUrl) ?>",<?php endif; ?>
        "contactPoint": {
            "@type": "ContactPoint",
            "telephone": "<?= htmlspecialchars($phoneNumber) ?>",
            "contactType": "sales"
        }
    }
    </script>
    <script type="application/ld+json">
    {
        "@context": "https://schema.org",
        "@type": "SoftwareApplication",
        "name": "<?= htmlspecialchars($appName) ?>",
        "applicationCategory": "BusinessApplication",
        "operatingSystem": "Web",
        "description": "<?= htmlspecialchars($metaDesc) ?>",
        "offers": {
            "@type": "Offer",
            "price": "0",
            "priceCurrency": "KES",
            "description": "Free trial available"
        }
    }
    </script>
    
    <style>
        *, *::before, *::after { margin: 0; padding: 0; box-sizing: border-box; }
        :root {
            --color-primary: #f59e0b;
            --color-primary-dark: #d97706;
            --color-primary-light: #fef3c7;
            --color-text: #101828;
            --color-text-secondary: #475467;
            --color-bg: #ffffff;
            --color-bg-light: #f9fafb;
            --color-border: #e5e7eb;
            --nav-height: 72px;
            --font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
        }
        html { scroll-behavior: smooth; }
        body { font-family: var(--font-family); color: var(--color-text); background: var(--color-bg); overflow-x: hidden; line-height: 1.6; }

        /* Navigation */
        .navigation-section { position: fixed; top: 0; left: 0; right: 0; z-index: 1000; background: rgba(255,255,255,0.95); backdrop-filter: blur(12px); -webkit-backdrop-filter: blur(12px); border-bottom: 1px solid var(--color-border); transition: all 0.3s ease; }
        .navigation-container { max-width: 1280px; margin: 0 auto; padding: 0 24px; display: flex; align-items: center; justify-content: space-between; height: var(--nav-height); }
        .navigation-brand { cursor: pointer; display: flex; align-items: center; gap: 10px; }
        .navigation-logo { height: 36px; width: auto; }
        .navigation-brand-text { font-size: 1.25rem; font-weight: 700; color: var(--color-text); }
        .navigation-menu { display: flex; align-items: center; gap: 32px; }
        .navigation-link { font-size: 0.9rem; font-weight: 500; color: var(--color-text-secondary); text-decoration: none; transition: color 0.2s; display: flex; align-items: center; gap: 4px; }
        .navigation-link:hover { color: var(--color-primary-dark); }
        .navigation-dropdown { position: relative; }
        .navigation-dropdown-arrow { transition: transform 0.3s; }
        .navigation-dropdown:hover .navigation-dropdown-arrow { transform: rotate(180deg); }
        .navigation-dropdown-menu { position: absolute; top: 100%; left: 50%; transform: translateX(-50%) translateY(10px); background: white; border-radius: 16px; box-shadow: 0 20px 60px rgba(0,0,0,0.12); border: 1px solid var(--color-border); opacity: 0; visibility: hidden; transition: all 0.3s ease; padding: 20px; min-width: 280px; }
        .navigation-dropdown:hover .navigation-dropdown-menu { opacity: 1; visibility: visible; transform: translateX(-50%) translateY(4px); }
        .navigation-dropdown-item { display: flex; align-items: center; gap: 12px; padding: 12px 16px; border-radius: 10px; text-decoration: none; color: var(--color-text); transition: background 0.2s; }
        .navigation-dropdown-item:hover { background: var(--color-bg-light); }
        .dropdown-item-icon { width: 40px; height: 40px; border-radius: 10px; background: var(--color-primary-light); display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
        .dropdown-item-icon i { font-size: 1rem; color: var(--color-primary-dark); }
        .dropdown-item-title { font-weight: 600; font-size: 0.9rem; }
        .dropdown-item-desc { font-size: 0.8rem; color: var(--color-text-secondary); margin-top: 2px; }
        .navigation-actions { display: flex; align-items: center; gap: 12px; }
        .navigation-btn-secondary { padding: 8px 18px; border-radius: 8px; font-size: 0.875rem; font-weight: 500; color: var(--color-text); background: transparent; border: 1px solid var(--color-border); cursor: pointer; transition: all 0.2s; text-decoration: none; }
        .navigation-btn-secondary:hover { border-color: var(--color-primary); color: var(--color-primary-dark); }
        .navigation-btn-primary { padding: 8px 20px; border-radius: 8px; font-size: 0.875rem; font-weight: 600; color: white; background: var(--color-primary); border: none; cursor: pointer; transition: all 0.2s; text-decoration: none; display: inline-flex; align-items: center; }
        .navigation-btn-primary:hover { background: var(--color-primary-dark); transform: translateY(-1px); box-shadow: 0 4px 12px rgba(245,158,11,0.3); }
        .navigation-mobile-toggle { display: none; background: none; border: none; cursor: pointer; padding: 8px; }
        
        /* Mobile Menu */
        .navigation-mobile-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 998; opacity: 0; visibility: hidden; transition: all 0.3s; }
        .navigation-mobile-overlay.active { opacity: 1; visibility: visible; }
        .navigation-mobile-menu { position: fixed; top: var(--nav-height); right: -100%; width: 85%; max-width: 380px; height: calc(100vh - var(--nav-height)); background: white; z-index: 999; transition: right 0.3s ease; overflow-y: auto; padding: 24px; box-shadow: -4px 0 20px rgba(0,0,0,0.1); }
        .navigation-mobile-menu.active { right: 0; }
        .navigation-mobile-links { display: flex; flex-direction: column; gap: 4px; }
        .navigation-mobile-link { display: flex; align-items: center; justify-content: space-between; padding: 14px 16px; font-size: 1rem; font-weight: 500; color: var(--color-text); text-decoration: none; border-radius: 10px; transition: background 0.2s; }
        .navigation-mobile-link:hover { background: var(--color-bg-light); }
        .navigation-mobile-dropdown-menu { display: none; padding-left: 20px; }
        .navigation-mobile-dropdown.open .navigation-mobile-dropdown-menu { display: block; }
        .navigation-mobile-dropdown.open .navigation-mobile-dropdown-arrow { transform: rotate(180deg); }
        .navigation-mobile-dropdown-item { display: block; padding: 10px 16px; font-size: 0.9rem; color: var(--color-text-secondary); text-decoration: none; border-radius: 8px; transition: background 0.2s; }
        .navigation-mobile-dropdown-item:hover { background: var(--color-bg-light); color: var(--color-primary-dark); }
        .navigation-mobile-actions { margin-top: 24px; padding-top: 24px; border-top: 1px solid var(--color-border); display: flex; flex-direction: column; gap: 10px; }
        .navigation-mobile-actions .navigation-btn-primary,
        .navigation-mobile-actions .navigation-btn-secondary { width: 100%; text-align: center; padding: 12px; justify-content: center; }

        /* Hero Section */
        .hero-section { padding-top: calc(var(--nav-height) + 60px); padding-bottom: 80px; background: linear-gradient(135deg, #fffbf0 0%, #ffffff 40%, #f0f9ff 100%); position: relative; overflow: hidden; }
        .hero-section::before { content: ''; position: absolute; top: -50%; right: -20%; width: 800px; height: 800px; border-radius: 50%; background: radial-gradient(circle, rgba(245,158,11,0.06) 0%, transparent 70%); }
        .hero-container { max-width: 1280px; margin: 0 auto; padding: 0 24px; display: grid; grid-template-columns: 1fr 1fr; gap: 60px; align-items: center; }
        .hero-content { position: relative; z-index: 2; }
        .hero-title { font-size: 3.2rem; font-weight: 800; line-height: 1.15; color: var(--color-text); margin-bottom: 20px; letter-spacing: -0.02em; }
        .hero-title-highlight { color: var(--color-primary); position: relative; }
        .hero-subtitle { font-size: 1.1rem; color: var(--color-text-secondary); line-height: 1.7; margin-bottom: 32px; max-width: 520px; }
        .hero-buttons { display: flex; gap: 14px; flex-wrap: wrap; }
        .hero-btn-primary { padding: 14px 32px; border-radius: 10px; font-size: 1rem; font-weight: 600; color: white; background: var(--color-primary); border: none; cursor: pointer; transition: all 0.3s; text-decoration: none; display: inline-flex; align-items: center; gap: 8px; box-shadow: 0 4px 16px rgba(245,158,11,0.3); }
        .hero-btn-primary:hover { background: var(--color-primary-dark); transform: translateY(-2px); box-shadow: 0 8px 24px rgba(245,158,11,0.4); }
        .hero-images { position: relative; z-index: 2; }
        .hero-combined-image { width: 100%; height: auto; border-radius: 16px; box-shadow: 0 30px 60px rgba(0,0,0,0.12); }

        /* Products Section */
        .products-section { padding: 100px 0; background: var(--color-bg); }
        .products-container { max-width: 1280px; margin: 0 auto; padding: 0 24px; }
        .products-main-title { text-align: center; font-size: 2.2rem; font-weight: 800; color: var(--color-text); margin-bottom: 48px; }
        .products-tabs { display: flex; justify-content: center; gap: 8px; margin-bottom: 48px; background: var(--color-bg-light); padding: 6px; border-radius: 12px; border: 1px solid var(--color-border); width: fit-content; margin-left: auto; margin-right: auto; }
        .products-tab-item { padding: 10px 24px; border-radius: 8px; font-size: 0.9rem; font-weight: 500; color: var(--color-text-secondary); background: transparent; border: none; cursor: pointer; transition: all 0.3s; }
        .products-tab-item.active { background: white; color: var(--color-text); font-weight: 600; box-shadow: 0 2px 8px rgba(0,0,0,0.08); }
        .products-tab-content { display: none; grid-template-columns: 1fr 1fr; gap: 48px; align-items: center; }
        .products-tab-content.active { display: grid; }
        .products-content-title { font-size: 1.6rem; font-weight: 700; color: var(--color-text); margin-bottom: 16px; }
        .products-content-description { font-size: 1rem; color: var(--color-text-secondary); line-height: 1.7; margin-bottom: 24px; }
        .products-content-image { width: 100%; border-radius: 16px; box-shadow: 0 20px 50px rgba(0,0,0,0.1); border: 1px solid var(--color-border); }
        .btn-outline { padding: 12px 24px; border-radius: 8px; font-size: 0.9rem; font-weight: 600; color: var(--color-primary-dark); background: transparent; border: 2px solid var(--color-primary); cursor: pointer; transition: all 0.3s; text-decoration: none; display: inline-block; }
        .btn-outline:hover { background: var(--color-primary); color: white; }
        .products-preview-link { display: block; text-decoration: none; }
        .products-preview-link:hover .products-preview-card { transform: translateY(-4px); border-color: var(--color-primary); box-shadow: 0 20px 50px rgba(0,0,0,0.2); }
        .products-preview-link:hover .products-preview-card i { opacity: 1; }

        /* AI Section */
        .ai-section { padding: 100px 0; background: linear-gradient(180deg, #f8fafc 0%, #ffffff 100%); }
        .ai-container { max-width: 1280px; margin: 0 auto; padding: 0 24px; }
        .ai-header { text-align: center; margin-bottom: 60px; }
        .ai-header h2 { font-size: 2.2rem; font-weight: 800; color: var(--color-text); margin-bottom: 16px; }
        .ai-header p { font-size: 1.05rem; color: var(--color-text-secondary); max-width: 640px; margin: 0 auto; line-height: 1.7; }
        .ai-carousel { display: grid; grid-template-columns: 1fr 1fr; gap: 48px; align-items: center; }
        .ai-slide { display: none; }
        .ai-slide.active { display: grid; grid-template-columns: 1fr 1fr; gap: 48px; align-items: center; }
        .ai-slide-content h3 { font-size: 1.5rem; font-weight: 700; color: var(--color-text); margin-bottom: 16px; }
        .ai-slide-content p { font-size: 1rem; color: var(--color-text-secondary); line-height: 1.7; }
        .ai-slide-image img { width: 100%; border-radius: 16px; box-shadow: 0 20px 50px rgba(0,0,0,0.1); }
        .ai-carousel-indicators { display: flex; justify-content: center; gap: 10px; margin-top: 40px; }
        .ai-indicator { width: 10px; height: 10px; border-radius: 50%; border: none; background: var(--color-border); cursor: pointer; transition: all 0.3s; }
        .ai-indicator.active { background: var(--color-primary); width: 32px; border-radius: 5px; }

        /* Pricing Section */
        .pricing-section { padding: 100px 0; background: var(--color-bg-light); }
        .pricing-container { max-width: 1100px; margin: 0 auto; padding: 0 24px; }
        .pricing-header { text-align: center; margin-bottom: 60px; }
        .pricing-header h2 { font-size: 2.2rem; font-weight: 800; color: var(--color-text); margin-bottom: 12px; }
        .pricing-header p { font-size: 1.05rem; color: var(--color-text-secondary); }
        .pricing-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 24px; }
        .pricing-card { background: white; border-radius: 16px; padding: 36px 28px; border: 1px solid var(--color-border); transition: all 0.3s; position: relative; }
        .pricing-card:hover { box-shadow: 0 20px 50px rgba(0,0,0,0.08); transform: translateY(-4px); }
        .pricing-card.featured { border: 2px solid var(--color-primary); box-shadow: 0 8px 30px rgba(245,158,11,0.12); }
        .pricing-card-badge { position: absolute; top: -12px; left: 50%; transform: translateX(-50%); background: linear-gradient(135deg, var(--color-primary), var(--color-primary-dark)); color: white; font-size: 0.75rem; font-weight: 600; padding: 4px 16px; border-radius: 20px; }
        .pricing-card-name { font-size: 1.2rem; font-weight: 700; color: var(--color-text); margin-bottom: 4px; }
        .pricing-card-desc { font-size: 0.85rem; color: var(--color-text-secondary); margin-bottom: 20px; }
        .pricing-card-price { font-size: 2.5rem; font-weight: 800; color: var(--color-text); margin-bottom: 4px; }
        .pricing-card-price span { font-size: 1rem; font-weight: 400; color: var(--color-text-secondary); }
        .pricing-card-billing { font-size: 0.8rem; color: var(--color-text-secondary); margin-bottom: 24px; }
        .pricing-features { list-style: none; margin-bottom: 28px; }
        .pricing-features li { display: flex; align-items: center; gap: 10px; padding: 8px 0; font-size: 0.9rem; color: var(--color-text-secondary); }
        .pricing-features li i { color: #22c55e; font-size: 0.85rem; }
        .pricing-cta { display: block; width: 100%; padding: 12px; border-radius: 10px; font-size: 0.9rem; font-weight: 600; text-align: center; text-decoration: none; transition: all 0.3s; cursor: pointer; }
        .pricing-cta-primary { background: var(--color-primary); color: white; border: none; }
        .pricing-cta-primary:hover { background: var(--color-primary-dark); }
        .pricing-cta-secondary { background: var(--color-text); color: white; border: none; }
        .pricing-cta-secondary:hover { background: #1f2937; }

        /* FAQ Section */
        .faq-section { padding: 100px 0; background: var(--color-bg); }
        .faq-container { max-width: 720px; margin: 0 auto; padding: 0 24px; }
        .faq-header { text-align: center; margin-bottom: 48px; }
        .faq-header h2 { font-size: 2rem; font-weight: 800; color: var(--color-text); margin-bottom: 12px; }
        .faq-item { border: 1px solid var(--color-border); border-radius: 12px; margin-bottom: 12px; overflow: hidden; background: white; }
        .faq-item summary { padding: 18px 24px; font-weight: 600; font-size: 0.95rem; cursor: pointer; display: flex; align-items: center; justify-content: space-between; color: var(--color-text); }
        .faq-item summary::-webkit-details-marker { display: none; }
        .faq-item summary i { transition: transform 0.3s; color: var(--color-text-secondary); }
        .faq-item[open] summary i { transform: rotate(180deg); }
        .faq-item .faq-answer { padding: 0 24px 18px; font-size: 0.9rem; color: var(--color-text-secondary); line-height: 1.7; }

        /* CTA Section */
        .cta-section { padding: 100px 0; background: linear-gradient(135deg, var(--color-primary) 0%, var(--color-primary-dark) 100%); position: relative; overflow: hidden; }
        .cta-section::before { content: ''; position: absolute; top: -50%; right: -20%; width: 600px; height: 600px; border-radius: 50%; background: rgba(255,255,255,0.08); }
        .cta-container { max-width: 720px; margin: 0 auto; padding: 0 24px; text-align: center; position: relative; z-index: 2; }
        .cta-container h2 { font-size: 2.2rem; font-weight: 800; color: white; margin-bottom: 16px; }
        .cta-container p { font-size: 1.05rem; color: rgba(255,255,255,0.85); margin-bottom: 36px; line-height: 1.7; }
        .cta-btn { padding: 14px 36px; border-radius: 10px; font-size: 1rem; font-weight: 600; color: var(--color-primary-dark); background: white; border: none; cursor: pointer; transition: all 0.3s; text-decoration: none; display: inline-flex; align-items: center; gap: 8px; box-shadow: 0 4px 16px rgba(0,0,0,0.1); }
        .cta-btn:hover { transform: translateY(-2px); box-shadow: 0 8px 24px rgba(0,0,0,0.15); }

        /* Footer */
        .footer-section { padding: 60px 0 32px; background: #0f172a; color: white; }
        .footer-container { max-width: 1280px; margin: 0 auto; padding: 0 24px; }
        .footer-grid { display: grid; grid-template-columns: 2fr 1fr 1fr 1fr; gap: 48px; margin-bottom: 48px; }
        .footer-brand-text { font-size: 0.9rem; color: #94a3b8; line-height: 1.7; margin-top: 16px; }
        .footer-heading { font-size: 0.85rem; font-weight: 600; color: #e2e8f0; margin-bottom: 16px; text-transform: uppercase; letter-spacing: 0.05em; }
        .footer-links { list-style: none; }
        .footer-links li { margin-bottom: 10px; }
        .footer-links a { font-size: 0.9rem; color: #94a3b8; text-decoration: none; transition: color 0.2s; }
        .footer-links a:hover { color: var(--color-primary); }
        .footer-bottom { border-top: 1px solid #1e293b; padding-top: 24px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px; }
        .footer-bottom p { font-size: 0.8rem; color: #64748b; }
        .footer-social { display: flex; gap: 10px; }
        .footer-social a { width: 36px; height: 36px; border-radius: 8px; background: rgba(255,255,255,0.05); display: flex; align-items: center; justify-content: center; color: #94a3b8; text-decoration: none; transition: all 0.2s; }
        .footer-social a:hover { background: var(--color-primary); color: white; }

        /* Trust Badges Strip */
        .trust-strip { padding: 48px 0; background: var(--color-bg-light); border-top: 1px solid var(--color-border); border-bottom: 1px solid var(--color-border); }
        .trust-strip-container { max-width: 1280px; margin: 0 auto; padding: 0 24px; }
        .trust-strip-label { text-align: center; font-size: 0.75rem; font-weight: 600; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.1em; margin-bottom: 28px; }
        .trust-strip-logos { display: flex; justify-content: center; align-items: center; flex-wrap: wrap; gap: 40px; opacity: 0.6; }
        .trust-strip-item { display: flex; align-items: center; gap: 8px; font-size: 0.95rem; font-weight: 600; color: #64748b; transition: opacity 0.3s; }
        .trust-strip-item:hover { opacity: 1; }
        .trust-strip-item i { font-size: 1.5rem; }

        /* How it Works */
        .how-section { padding: 100px 0; background: var(--color-bg); }
        .how-container { max-width: 1000px; margin: 0 auto; padding: 0 24px; }
        .how-header { text-align: center; margin-bottom: 60px; }
        .how-header h2 { font-size: 2.2rem; font-weight: 800; color: var(--color-text); margin-bottom: 12px; }
        .how-header p { font-size: 1.05rem; color: var(--color-text-secondary); }
        .how-steps { display: grid; grid-template-columns: repeat(3, 1fr); gap: 32px; position: relative; }
        .how-steps::before { content: ''; position: absolute; top: 40px; left: 16%; right: 16%; height: 2px; background: linear-gradient(90deg, var(--color-primary-light), var(--color-primary), var(--color-primary-light)); z-index: 0; }
        .how-step { text-align: center; position: relative; z-index: 1; }
        .how-step-number { width: 80px; height: 80px; border-radius: 50%; background: linear-gradient(135deg, var(--color-primary), var(--color-primary-dark)); color: white; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; font-weight: 800; margin: 0 auto 20px; box-shadow: 0 8px 24px rgba(245,158,11,0.25); }
        .how-step h3 { font-size: 1.1rem; font-weight: 700; color: var(--color-text); margin-bottom: 8px; }
        .how-step p { font-size: 0.9rem; color: var(--color-text-secondary); line-height: 1.6; }

        /* Testimonials */
        .testimonials-section { padding: 100px 0; background: linear-gradient(180deg, #f8fafc 0%, var(--color-bg) 100%); }
        .testimonials-container { max-width: 1280px; margin: 0 auto; padding: 0 24px; }
        .testimonials-header { text-align: center; margin-bottom: 60px; }
        .testimonials-header h2 { font-size: 2.2rem; font-weight: 800; color: var(--color-text); margin-bottom: 12px; }
        .testimonials-header p { font-size: 1.05rem; color: var(--color-text-secondary); }
        .testimonials-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 24px; }
        .testimonial-card { background: white; border-radius: 16px; padding: 32px; border: 1px solid var(--color-border); transition: all 0.3s; }
        .testimonial-card:hover { box-shadow: 0 16px 40px rgba(0,0,0,0.06); transform: translateY(-4px); }
        .testimonial-stars { color: var(--color-primary); font-size: 0.85rem; margin-bottom: 16px; letter-spacing: 2px; }
        .testimonial-text { font-size: 0.95rem; color: var(--color-text-secondary); line-height: 1.7; margin-bottom: 24px; font-style: italic; }
        .testimonial-author { display: flex; align-items: center; gap: 12px; }
        .testimonial-avatar { width: 44px; height: 44px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 1rem; color: white; flex-shrink: 0; }
        .testimonial-name { font-weight: 600; font-size: 0.9rem; color: var(--color-text); }
        .testimonial-role { font-size: 0.8rem; color: var(--color-text-secondary); }

        /* Newsletter */
        .newsletter-section { padding: 48px 0; background: var(--color-bg-light); border-top: 1px solid var(--color-border); }
        .newsletter-container { max-width: 600px; margin: 0 auto; padding: 0 24px; text-align: center; }
        .newsletter-container h3 { font-size: 1.3rem; font-weight: 700; color: var(--color-text); margin-bottom: 8px; }
        .newsletter-container p { font-size: 0.9rem; color: var(--color-text-secondary); margin-bottom: 20px; }
        .newsletter-form { display: flex; gap: 8px; max-width: 440px; margin: 0 auto; }
        .newsletter-input { flex: 1; padding: 12px 16px; border-radius: 10px; border: 1px solid var(--color-border); font-size: 0.9rem; font-family: var(--font-family); outline: none; transition: border-color 0.2s; }
        .newsletter-input:focus { border-color: var(--color-primary); }
        .newsletter-btn { padding: 12px 24px; border-radius: 10px; background: var(--color-primary); color: white; font-size: 0.9rem; font-weight: 600; border: none; cursor: pointer; transition: all 0.3s; font-family: var(--font-family); white-space: nowrap; }
        .newsletter-btn:hover { background: var(--color-primary-dark); }
        .newsletter-msg { margin-top: 12px; font-size: 0.8rem; display: none; }

        /* Cookie Consent */
        .cookie-banner { position: fixed; bottom: 0; left: 0; right: 0; z-index: 950; background: #1e293b; color: white; padding: 16px 24px; display: none; align-items: center; justify-content: center; gap: 20px; flex-wrap: wrap; box-shadow: 0 -4px 20px rgba(0,0,0,0.15); }
        .cookie-banner.show { display: flex; }
        .cookie-banner p { font-size: 0.85rem; color: #cbd5e1; max-width: 600px; line-height: 1.5; }
        .cookie-banner p a { color: var(--color-primary); text-decoration: underline; }
        .cookie-banner-actions { display: flex; gap: 8px; flex-shrink: 0; }
        .cookie-btn { padding: 8px 20px; border-radius: 8px; font-size: 0.8rem; font-weight: 600; border: none; cursor: pointer; transition: all 0.2s; font-family: var(--font-family); }
        .cookie-btn-accept { background: var(--color-primary); color: white; }
        .cookie-btn-accept:hover { background: var(--color-primary-dark); }
        .cookie-btn-decline { background: transparent; color: #94a3b8; border: 1px solid #475569; }
        .cookie-btn-decline:hover { border-color: #94a3b8; color: white; }

        /* Live Chat Widget */
        .live-chat-btn { position: fixed; bottom: 90px; right: 24px; z-index: 900; width: 52px; height: 52px; border-radius: 50%; background: var(--color-text); color: white; display: flex; align-items: center; justify-content: center; text-decoration: none; transition: all 0.3s; box-shadow: 0 4px 16px rgba(0,0,0,0.2); cursor: pointer; border: none; }
        .live-chat-btn:hover { transform: scale(1.12); box-shadow: 0 6px 24px rgba(0,0,0,0.3); }
        .live-chat-btn i { font-size: 1.3rem; }
        .live-chat-tooltip { position: absolute; right: 60px; background: var(--color-text); color: white; padding: 6px 12px; border-radius: 6px; font-size: 0.75rem; font-weight: 500; white-space: nowrap; opacity: 0; visibility: hidden; transition: all 0.3s; pointer-events: none; }
        .live-chat-btn:hover .live-chat-tooltip { opacity: 1; visibility: visible; right: 64px; }

        /* Floating Action Buttons */
        .floating-buttons { position: fixed; bottom: 24px; right: 24px; z-index: 900; display: flex; flex-direction: column; gap: 12px; }
        .float-btn { width: 52px; height: 52px; border-radius: 50%; display: flex; align-items: center; justify-content: center; text-decoration: none; transition: all 0.3s; }
        .float-btn:hover { transform: scale(1.12); }
        .float-btn-whatsapp { background: #25D366; color: white; box-shadow: 0 4px 16px rgba(37,211,102,0.4); }
        .float-btn-whatsapp:hover { box-shadow: 0 6px 24px rgba(37,211,102,0.55); }
        .float-btn-whatsapp i { font-size: 1.4rem; }
        .float-btn-call { background: white; color: #22c55e; border: 1px solid var(--color-border); box-shadow: 0 4px 16px rgba(0,0,0,0.1); }
        .float-btn-call:hover { box-shadow: 0 6px 24px rgba(0,0,0,0.15); }
        .float-btn-call i { font-size: 1.2rem; }

        /* Responsive */
        @media (max-width: 1024px) {
            .hero-container { grid-template-columns: 1fr; gap: 40px; }
            .hero-title { font-size: 2.5rem; }
            .hero-images { order: -1; max-width: 500px; margin: 0 auto; }
            .products-tab-content.active { grid-template-columns: 1fr; }
            .ai-slide.active { grid-template-columns: 1fr; }
            .footer-grid { grid-template-columns: 1fr 1fr; }
            .testimonials-grid { grid-template-columns: 1fr 1fr; }
            .how-steps::before { display: none; }
        }
        @media (max-width: 768px) {
            .navigation-menu { display: none; }
            .navigation-mobile-toggle { display: block; }
            .navigation-actions .navigation-btn-secondary,
            .navigation-actions .navigation-btn-primary { display: none; }
            .navigation-actions { gap: 8px; }
            .hero-section { padding-top: calc(var(--nav-height) + 32px); padding-bottom: 48px; }
            .hero-title { font-size: 2rem; }
            .hero-subtitle { font-size: 1rem; }
            .products-main-title { font-size: 1.6rem; }
            .products-tabs { flex-wrap: wrap; }
            .ai-header h2 { font-size: 1.6rem; }
            .pricing-grid { grid-template-columns: 1fr; }
            .footer-grid { grid-template-columns: 1fr; gap: 32px; }
            .floating-buttons { bottom: 16px; right: 16px; }
            .float-btn { width: 48px; height: 48px; }
            .cta-container h2 { font-size: 1.6rem; }
            .testimonials-grid { grid-template-columns: 1fr; }
            .how-steps { grid-template-columns: 1fr; gap: 40px; }
            .newsletter-form { flex-direction: column; }
            .live-chat-btn { bottom: 80px; right: 16px; width: 48px; height: 48px; }
            #about > div > div { grid-template-columns: 1fr !important; }
        }

        /* Animations */
        @keyframes fadeInUp { from { opacity: 0; transform: translateY(30px); } to { opacity: 1; transform: translateY(0); } }
        .fade-in-up { opacity: 0; transform: translateY(30px); transition: opacity 0.6s ease, transform 0.6s ease; }
        .fade-in-up.visible { opacity: 1; transform: translateY(0); }
    </style>
</head>
<body>

    <!-- Navigation -->
    <nav class="navigation-section" role="navigation" aria-label="Main navigation">
        <div class="navigation-container">
            <div class="navigation-brand" onclick="window.location.href='./';" role="link" aria-label="<?= htmlspecialchars($appName) ?> Home">
                <?php if ($logoUrl): ?>
                    <img src="<?= htmlspecialchars($logoUrl) ?>" alt="<?= htmlspecialchars($appName) ?> Logo" class="navigation-logo">
                <?php else: ?>
                    <i class="fas fa-chart-line" style="font-size:1.5rem; color:var(--color-primary);" aria-hidden="true"></i>
                <?php endif; ?>
                <span class="navigation-brand-text"><?= htmlspecialchars($appName) ?></span>
            </div>

            <div class="navigation-menu">
                <div class="navigation-dropdown">
                    <a href="javascript:void(0)" class="navigation-link navigation-dropdown-toggle">
                        Products
                        <svg class="navigation-dropdown-arrow" width="12" height="8" viewBox="0 0 14 8" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M1 1L7 7L13 1" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"></path>
                        </svg>
                    </a>
                    <div class="navigation-dropdown-menu">
                        <a href="#products" class="navigation-dropdown-item">
                            <div class="dropdown-item-icon"><i class="fas fa-cash-register"></i></div>
                            <div class="dropdown-item-text">
                                <div class="dropdown-item-title">Point of Sale</div>
                                <div class="dropdown-item-desc">Fast checkout, offline capable</div>
                            </div>
                        </a>
                        <a href="#products" class="navigation-dropdown-item">
                            <div class="dropdown-item-icon"><i class="fas fa-boxes-stacked"></i></div>
                            <div class="dropdown-item-text">
                                <div class="dropdown-item-title">Inventory & ERP</div>
                                <div class="dropdown-item-desc">Complete business management</div>
                            </div>
                        </a>
                        <a href="#products" class="navigation-dropdown-item">
                            <div class="dropdown-item-icon"><i class="fas fa-store"></i></div>
                            <div class="dropdown-item-text">
                                <div class="dropdown-item-title">E-Commerce</div>
                                <div class="dropdown-item-desc">Sell online, sync everywhere</div>
                            </div>
                        </a>
                        <a href="#ai-section" class="navigation-dropdown-item">
                            <div class="dropdown-item-icon"><i class="fas fa-robot"></i></div>
                            <div class="dropdown-item-text">
                                <div class="dropdown-item-title">AI Assistant</div>
                                <div class="dropdown-item-desc">Smart ordering & insights</div>
                            </div>
                        </a>
                    </div>
                </div>
                <a href="#pricing" class="navigation-link">Pricing</a>
                <a href="#about" class="navigation-link">About Us</a>
                <a href="#faq" class="navigation-link">FAQ</a>
            </div>

            <div class="navigation-actions">
                <a href="auth/login.php" class="navigation-btn-secondary">Sign In</a>
                <a href="auth/register.php" class="navigation-btn-primary">Get Started</a>
                <button class="navigation-mobile-toggle" onclick="toggleMobileMenu()" aria-label="Toggle mobile menu">
                    <svg class="hamburger-icon" width="24" height="18" viewBox="0 0 30 22.5" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <rect width="30" height="2.5" rx="1" fill="#343330"></rect>
                        <rect y="10" width="30" height="2.5" rx="1" fill="#343330"></rect>
                        <rect y="20" width="30" height="2.5" rx="1" fill="#343330"></rect>
                    </svg>
                </button>
            </div>
        </div>
    </nav>

    <!-- Mobile Menu Overlay -->
    <div class="navigation-mobile-overlay" id="mobileOverlay" onclick="closeMobileMenu()"></div>

    <!-- Mobile Menu -->
    <div class="navigation-mobile-menu" id="mobileMenu">
        <div class="navigation-mobile-links">
            <div class="navigation-mobile-dropdown">
                <a href="javascript:void(0)" class="navigation-mobile-link" onclick="toggleMobileDropdown(this)">
                    Products
                    <svg class="navigation-mobile-dropdown-arrow" width="12" height="12" viewBox="0 0 12 12" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M3 4.5L6 7.5L9 4.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"></path>
                    </svg>
                </a>
                <div class="navigation-mobile-dropdown-menu">
                    <a href="#products" class="navigation-mobile-dropdown-item" onclick="closeMobileMenu()">Point of Sale</a>
                    <a href="#products" class="navigation-mobile-dropdown-item" onclick="closeMobileMenu()">Inventory & ERP</a>
                    <a href="#products" class="navigation-mobile-dropdown-item" onclick="closeMobileMenu()">E-Commerce</a>
                    <a href="#ai-section" class="navigation-mobile-dropdown-item" onclick="closeMobileMenu()">AI Assistant</a>
                </div>
            </div>
            <a href="#pricing" class="navigation-mobile-link" onclick="closeMobileMenu()">Pricing</a>
            <a href="#about" class="navigation-mobile-link" onclick="closeMobileMenu()">About Us</a>
            <a href="#faq" class="navigation-mobile-link" onclick="closeMobileMenu()">FAQ</a>
        </div>
        <div class="navigation-mobile-actions">
            <a href="auth/register.php" class="navigation-btn-primary">Get Started</a>
            <a href="auth/login.php" class="navigation-btn-secondary">Sign In</a>
        </div>
    </div>

    <!-- Hero Section -->
    <section class="hero-section" id="home">
        <div class="hero-container">
            <div class="hero-content">
                <div class="hero-text">
                    <h1 class="hero-title"><?= $heroTitle ?></h1>
                    <p class="hero-subtitle"><?= htmlspecialchars($heroSubtitle) ?></p>
                </div>
                <div class="hero-buttons">
                    <a href="<?= htmlspecialchars($heroCtaUrl) ?>" class="hero-btn-primary">
                        <?= htmlspecialchars($heroCtaText) ?> <i class="fas fa-arrow-right"></i>
                    </a>
                </div>
            </div>
            <div class="hero-images">
                <?php if ($heroImage): ?>
                    <img src="<?= htmlspecialchars($heroImage) ?>" alt="<?= htmlspecialchars($heroImageAlt) ?>" class="hero-combined-image" loading="lazy" decoding="async">
                <?php else: ?>
                    <!-- Default Dashboard Mockup -->
                    <div style="background: white; border-radius: 16px; box-shadow: 0 30px 60px rgba(0,0,0,0.12); overflow: hidden; border: 1px solid var(--color-border);">
                        <div style="background: #f9fafb; padding: 12px 16px; display: flex; align-items: center; gap: 8px; border-bottom: 1px solid var(--color-border);">
                            <div style="display:flex; gap:6px;">
                                <div style="width:10px;height:10px;border-radius:50%;background:#ef4444;"></div>
                                <div style="width:10px;height:10px;border-radius:50%;background:#eab308;"></div>
                                <div style="width:10px;height:10px;border-radius:50%;background:#22c55e;"></div>
                            </div>
                            <div style="flex:1;text-align:center;">
                                <span style="font-size:0.75rem;color:#94a3b8;background:white;padding:4px 12px;border-radius:6px;border:1px solid var(--color-border);"><?= htmlspecialchars($appName) ?> Dashboard</span>
                            </div>
                        </div>
                        <div style="padding:24px;background:linear-gradient(135deg,#0f172a,#1e293b);">
                            <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:12px;margin-bottom:20px;">
                                <div style="background:rgba(255,255,255,0.08);border-radius:12px;padding:16px;text-align:center;">
                                    <div style="font-size:1.4rem;font-weight:700;color:#4ade80;">KES 24.5K</div>
                                    <div style="font-size:0.65rem;color:#94a3b8;margin-top:4px;text-transform:uppercase;letter-spacing:0.05em;">Today's Sales</div>
                                </div>
                                <div style="background:rgba(255,255,255,0.08);border-radius:12px;padding:16px;text-align:center;">
                                    <div style="font-size:1.4rem;font-weight:700;color:#60a5fa;">89</div>
                                    <div style="font-size:0.65rem;color:#94a3b8;margin-top:4px;text-transform:uppercase;letter-spacing:0.05em;">Orders</div>
                                </div>
                                <div style="background:rgba(255,255,255,0.08);border-radius:12px;padding:16px;text-align:center;">
                                    <div style="font-size:1.4rem;font-weight:700;color:#c084fc;">12</div>
                                    <div style="font-size:0.65rem;color:#94a3b8;margin-top:4px;text-transform:uppercase;letter-spacing:0.05em;">New Customers</div>
                                </div>
                            </div>
                            <div style="background:rgba(255,255,255,0.05);border-radius:12px;padding:16px;">
                                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;">
                                    <span style="font-size:0.8rem;color:#94a3b8;">Revenue This Week</span>
                                    <span style="font-size:0.8rem;color:#4ade80;font-weight:600;">+18.2%</span>
                                </div>
                                <div style="display:flex;align-items:flex-end;gap:6px;height:60px;">
                                    <div style="flex:1;background:rgba(245,158,11,0.3);border-radius:4px 4px 0 0;height:40%;"></div>
                                    <div style="flex:1;background:rgba(245,158,11,0.4);border-radius:4px 4px 0 0;height:55%;"></div>
                                    <div style="flex:1;background:rgba(245,158,11,0.5);border-radius:4px 4px 0 0;height:45%;"></div>
                                    <div style="flex:1;background:rgba(245,158,11,0.6);border-radius:4px 4px 0 0;height:70%;"></div>
                                    <div style="flex:1;background:rgba(245,158,11,0.7);border-radius:4px 4px 0 0;height:65%;"></div>
                                    <div style="flex:1;background:rgba(245,158,11,0.8);border-radius:4px 4px 0 0;height:85%;"></div>
                                    <div style="flex:1;background:var(--color-primary);border-radius:4px 4px 0 0;height:100%;"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <!-- Trust Badges Strip -->
    <?php $trustBlocks = get_landing_blocks('trust_strip'); ?>
    <?php if (!empty($trustBlocks)): ?>
    <section class="trust-strip">
        <div class="trust-strip-container">
            <p class="trust-strip-label">Trusted integrations & compliance</p>
            <div class="trust-strip-logos">
                <?php foreach ($trustBlocks as $t): ?>
                <div class="trust-strip-item">
                    <?php if ($t['icon_class']): ?><i class="<?= htmlspecialchars($t['icon_class']) ?>"></i><?php endif; ?>
                    <?= htmlspecialchars($t['title'] ?: ucwords(str_replace('_', ' ', $t['block_key']))) ?>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <!-- How It Works -->
    <?php $howBlocks = get_landing_blocks('how_it_works'); ?>
    <?php if (!empty($howBlocks)): ?>
    <section class="how-section" id="how-it-works">
        <div class="how-container">
            <div class="how-header">
                <h2>Get Started in 3 Simple Steps</h2>
                <p>From sign-up to your first sale in minutes — not days.</p>
            </div>
            <div class="how-steps">
                <?php $stepNum = 1; foreach ($howBlocks as $h): ?>
                <div class="how-step fade-in-up">
                    <div class="how-step-number"><?= $stepNum++ ?></div>
                    <h3><?= htmlspecialchars($h['title']) ?></h3>
                    <p><?= htmlspecialchars($h['content']) ?></p>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <!-- Products Section -->
    <?php $productBlocks = get_landing_blocks('products'); ?>
    <?php if (!empty($productBlocks)): ?>
    <section class="products-section" id="products">
        <div class="products-container">
            <h2 class="products-main-title">All Business Operations, Simplified</h2>

            <div class="products-tabs">
                <?php $firstProd = true; foreach ($productBlocks as $p): ?>
                <button class="products-tab-item <?= $firstProd ? 'active' : '' ?>" data-tab="<?= htmlspecialchars($p['block_key']) ?>"><?= htmlspecialchars($p['subtitle'] ?: $p['title']) ?></button>
                <?php $firstProd = false; endforeach; ?>
            </div>

            <div class="products-content">
                <?php $firstProd = true; foreach ($productBlocks as $p):
                    $pIcon = $p['icon_class'] ?: 'fas fa-cube';
                    $pColor = ['fas fa-cash-register' => 'var(--color-primary)', 'fas fa-boxes-stacked' => '#60a5fa', 'fas fa-store' => '#a78bfa'][$pIcon] ?? 'var(--color-primary)';
                ?>
                <div class="products-tab-content <?= $firstProd ? 'active' : '' ?>" id="<?= htmlspecialchars($p['block_key']) ?>-content">
                    <div class="products-content-text">
                        <h3 class="products-content-title"><?= htmlspecialchars($p['title']) ?></h3>
                        <p class="products-content-description"><?= htmlspecialchars($p['content']) ?></p>
                        <a href="auth/register.php" class="btn-outline">Get Started for Free</a>
                    </div>
                    <a href="auth/register.php" class="products-preview-link" style="text-decoration:none;display:block;">
                        <div class="products-preview-card" style="background:linear-gradient(135deg,#0f172a,#1e293b);border-radius:16px;padding:40px;text-align:center;border:1px solid var(--color-border);transition:transform 0.3s,border-color 0.3s;cursor:pointer;">
                            <i class="<?= htmlspecialchars($pIcon) ?>" style="font-size:4rem;color:<?= htmlspecialchars($pColor) ?>;opacity:0.8;"></i>
                            <p style="color:#94a3b8;margin-top:16px;font-size:0.9rem;"><?= htmlspecialchars($p['title']) ?> Preview</p>
                            <p style="color:var(--color-primary);margin-top:12px;font-size:0.85rem;font-weight:600;"><i class="fas fa-arrow-right" style="margin-right:6px;"></i>Click to explore</p>
                        </div>
                    </a>
                </div>
                <?php $firstProd = false; endforeach; ?>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <!-- AI Section -->
    <?php $aiBlocks = get_landing_blocks('ai_slides'); ?>
    <?php if (!empty($aiBlocks)): ?>
    <section class="ai-section" id="ai-section">
        <div class="ai-container">
            <div class="ai-header">
                <h2>Your AI Business Partner</h2>
                <p>Powering your growth with AI Smart Ordering to automate manual tasks, and delivering smarter business decisions through real-time, data-driven insights.</p>
            </div>
            <div id="ai-carousel">
                <?php $firstAi = true; $aiIdx = 0; foreach ($aiBlocks as $a):
                    $aIcon = $a['icon_class'] ?: 'fas fa-robot';
                    $aColor = ['fas fa-wand-magic-sparkles' => 'var(--color-primary)', 'fas fa-brain' => '#7c3aed', 'fas fa-chart-line' => '#16a34a'][$aIcon] ?? 'var(--color-primary)';
                    $aBg = ['fas fa-wand-magic-sparkles' => 'linear-gradient(135deg,#fef3c7,#ffffff)', 'fas fa-brain' => 'linear-gradient(135deg,#ede9fe,#ffffff)', 'fas fa-chart-line' => 'linear-gradient(135deg,#dcfce7,#ffffff)'][$aIcon] ?? 'linear-gradient(135deg,#f8fafc,#ffffff)';
                ?>
                <div class="ai-slide <?= $firstAi ? 'active' : '' ?>" data-slide="<?= $aiIdx ?>">
                    <div class="ai-slide-content">
                        <h3><?= htmlspecialchars($a['title']) ?></h3>
                        <p><?= htmlspecialchars($a['content']) ?></p>
                    </div>
                    <div class="ai-slide-image">
                        <div style="background:<?= htmlspecialchars($aBg) ?>;border-radius:16px;padding:48px;text-align:center;border:1px solid var(--color-border);">
                            <i class="<?= htmlspecialchars($aIcon) ?>" style="font-size:3.5rem;color:<?= htmlspecialchars($aColor) ?>;"></i>
                            <p style="color:var(--color-text-secondary);margin-top:16px;font-weight:500;"><?= htmlspecialchars($a['title']) ?></p>
                        </div>
                    </div>
                </div>
                <?php $firstAi = false; $aiIdx++; endforeach; ?>
            </div>
            <!-- Carousel indicators -->
            <div class="ai-carousel-indicators">
                <?php $aiIdx = 0; foreach ($aiBlocks as $a): ?>
                <button class="ai-indicator <?= $aiIdx === 0 ? 'active' : '' ?>" data-slide="<?= $aiIdx++ ?>" aria-label="<?= htmlspecialchars($a['title']) ?>"></button>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <!-- Pricing Section -->
    <section class="pricing-section" id="pricing">
        <div class="pricing-container">
            <div class="pricing-header">
                <h2>Simple, Transparent Pricing</h2>
                <p>Choose the plan that fits your business. Upgrade or downgrade anytime.</p>
            </div>

            <?php if (!empty($dbPlans)): ?>
            <div class="pricing-grid">
                <?php foreach ($dbPlans as $plan):
                    $isFeatured = !empty($plan['is_featured']);
                    $cycleLbl = ['monthly' => '/mo', 'quarterly' => '/qtr', 'annual' => '/yr'];
                    $cycleTag = $cycleLbl[$plan['billing_cycle'] ?? 'monthly'] ?? '/mo';
                    $billingNote = ucfirst($plan['billing_cycle'] ?? 'monthly') . ' billing';
                ?>
                <div class="pricing-card <?= $isFeatured ? 'featured' : '' ?>">
                    <?php if ($isFeatured): ?>
                        <div class="pricing-card-badge">Most Popular</div>
                    <?php endif; ?>
                    <div class="pricing-card-name"><?= htmlspecialchars($plan['name']) ?></div>
                    <?php if (!empty($plan['description'])): ?>
                        <div class="pricing-card-desc"><?= htmlspecialchars($plan['description']) ?></div>
                    <?php endif; ?>
                    <div class="pricing-card-price"><?= htmlspecialchars($plan['currency'] ?? 'KES') ?> <?= number_format($plan['price'], 0) ?><span><?= $cycleTag ?></span></div>
                    <div class="pricing-card-billing"><?= $billingNote ?></div>
                    <ul class="pricing-features">
                        <li><i class="fas fa-check-circle"></i> <?= (int)$plan['max_users'] ?> users</li>
                        <li><i class="fas fa-check-circle"></i> <?= (int)$plan['max_branches'] ?> branches</li>
                        <li><i class="fas fa-check-circle"></i> <?= number_format((int)$plan['max_products']) ?> products</li>
                        <li><i class="fas fa-check-circle"></i> <?= number_format((int)$plan['max_storage_mb']) ?> MB storage</li>
                        <?php if ((int)$plan['trial_days'] > 0): ?>
                        <li><i class="fas fa-gift" style="color:var(--color-primary)"></i> <?= (int)$plan['trial_days'] ?>-day free trial</li>
                        <?php endif; ?>
                    </ul>
                    <a href="auth/register.php?plan=<?= htmlspecialchars($plan['slug']) ?>" class="pricing-cta <?= $isFeatured ? 'pricing-cta-primary' : 'pricing-cta-secondary' ?>">Get Started</a>
                </div>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <div class="pricing-grid">
                <div class="pricing-card">
                    <div class="pricing-card-name">Starter</div>
                    <div class="pricing-card-desc">For small businesses</div>
                    <div class="pricing-card-price">Free</div>
                    <div class="pricing-card-billing">14-day trial included</div>
                    <ul class="pricing-features">
                        <li><i class="fas fa-check-circle"></i> Up to 3 users</li>
                        <li><i class="fas fa-check-circle"></i> 1 branch</li>
                        <li><i class="fas fa-check-circle"></i> 500 products</li>
                        <li><i class="fas fa-check-circle"></i> Basic reports</li>
                    </ul>
                    <a href="auth/register.php" class="pricing-cta pricing-cta-secondary">Start Free</a>
                </div>
                <div class="pricing-card featured">
                    <div class="pricing-card-badge">Most Popular</div>
                    <div class="pricing-card-name">Business</div>
                    <div class="pricing-card-desc">Growing businesses</div>
                    <div class="pricing-card-price">KES 5,000<span>/mo</span></div>
                    <div class="pricing-card-billing">Billed monthly</div>
                    <ul class="pricing-features">
                        <li><i class="fas fa-check-circle"></i> Up to 10 users</li>
                        <li><i class="fas fa-check-circle"></i> 3 branches</li>
                        <li><i class="fas fa-check-circle"></i> Unlimited products</li>
                        <li><i class="fas fa-check-circle"></i> Advanced reports</li>
                        <li><i class="fas fa-check-circle"></i> M-Pesa integration</li>
                    </ul>
                    <a href="auth/register.php" class="pricing-cta pricing-cta-primary">Get Started</a>
                </div>
                <div class="pricing-card">
                    <div class="pricing-card-name">Enterprise</div>
                    <div class="pricing-card-desc">Large operations</div>
                    <div class="pricing-card-price">Custom</div>
                    <div class="pricing-card-billing">Tailored to your needs</div>
                    <ul class="pricing-features">
                        <li><i class="fas fa-check-circle"></i> Unlimited users</li>
                        <li><i class="fas fa-check-circle"></i> Unlimited branches</li>
                        <li><i class="fas fa-check-circle"></i> Dedicated support</li>
                        <li><i class="fas fa-check-circle"></i> Custom integrations</li>
                    </ul>
                    <a href="#" class="pricing-cta pricing-cta-secondary">Contact Sales</a>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- Testimonials Section -->
    <?php $testimonialBlocks = get_landing_blocks('testimonials'); ?>
    <?php if (!empty($testimonialBlocks)): ?>
    <section class="testimonials-section" id="testimonials">
        <div class="testimonials-container">
            <div class="testimonials-header">
                <h2>Loved by Businesses Across Africa</h2>
                <p>See what our customers have to say about <?= htmlspecialchars($appName) ?></p>
            </div>
            <div class="testimonials-grid">
                <?php
                $tGradients = [
                    'linear-gradient(135deg,#f59e0b,#d97706)',
                    'linear-gradient(135deg,#3b82f6,#2563eb)',
                    'linear-gradient(135deg,#22c55e,#16a34a)',
                    'linear-gradient(135deg,#a855f7,#7c3aed)',
                    'linear-gradient(135deg,#ef4444,#dc2626)',
                    'linear-gradient(135deg,#06b6d4,#0891b2)',
                ];
                $tIdx = 0;
                foreach ($testimonialBlocks as $t):
                    $tParts = explode(',', $t['subtitle'] ?? '', 2);
                    $tName = trim($tParts[0] ?? 'Customer');
                    $tRole = trim($tParts[1] ?? 'Business Owner');
                    $tInitials = implode('', array_map(fn($w) => strtoupper($w[0] ?? ''), array_slice(explode(' ', $tName), 0, 2)));
                    $tGrad = $tGradients[$tIdx % count($tGradients)];
                ?>
                <div class="testimonial-card fade-in-up">
                    <div class="testimonial-stars">
                        <i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i>
                    </div>
                    <p class="testimonial-text">"<?= htmlspecialchars($t['content']) ?>"</p>
                    <div class="testimonial-author">
                        <div class="testimonial-avatar" style="background:<?= htmlspecialchars($tGrad) ?>;"><?= htmlspecialchars($tInitials) ?></div>
                        <div>
                            <div class="testimonial-name"><?= htmlspecialchars($tName) ?></div>
                            <div class="testimonial-role"><?= htmlspecialchars($tRole) ?></div>
                        </div>
                    </div>
                </div>
                <?php $tIdx++; endforeach; ?>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <!-- Features Section -->
    <?php $featureBlocks = get_landing_blocks('features'); ?>
    <?php if (!empty($featureBlocks)): ?>
    <section id="features" style="padding:100px 0;background:var(--color-bg-light);">
        <div style="max-width:1280px;margin:0 auto;padding:0 24px;">
            <div style="text-align:center;margin-bottom:60px;">
                <h2 style="font-size:2.2rem;font-weight:800;color:var(--color-text);margin-bottom:12px;">Foundational Features for Core Operations</h2>
                <p style="font-size:1.05rem;color:var(--color-text-secondary);max-width:640px;margin:0 auto;">Our feature-rich system offers everything you need to manage your business efficiently.</p>
            </div>
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(300px,1fr));gap:24px;">
                <?php foreach ($featureBlocks as $fb):
                    $fbIcon = $fb['icon_class'] ?: 'fas fa-check-circle';
                ?>
                <div style="background:white;border-radius:16px;padding:32px;border:1px solid var(--color-border);transition:all 0.3s;" class="fade-in-up">
                    <div style="width:48px;height:48px;border-radius:12px;background:var(--color-primary-light);display:flex;align-items:center;justify-content:center;margin-bottom:20px;">
                        <i class="<?= htmlspecialchars($fbIcon) ?>" style="font-size:1.25rem;color:var(--color-primary-dark);"></i>
                    </div>
                    <h3 style="font-size:1.1rem;font-weight:700;color:var(--color-text);margin-bottom:8px;"><?= htmlspecialchars($fb['title']) ?></h3>
                    <p style="font-size:0.95rem;color:var(--color-text-secondary);line-height:1.6;"><?= htmlspecialchars($fb['content']) ?></p>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <!-- About / Trust Section -->
    <?php $aboutBlocks = get_landing_blocks('about'); ?>
    <?php $aboutBlock = $aboutBlocks[0] ?? null; ?>
    <section id="about" style="padding:100px 0;background:white;">
        <div style="max-width:1280px;margin:0 auto;padding:0 24px;">
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:64px;align-items:center;">
                <div>
                    <p style="font-size:0.75rem;font-weight:700;color:var(--color-primary-dark);text-transform:uppercase;letter-spacing:0.1em;margin-bottom:12px;">Why Choose Us</p>
                    <h2 style="font-size:2.2rem;font-weight:800;color:var(--color-text);margin-bottom:20px;"><?= htmlspecialchars($aboutBlock['title'] ?? "Your Business Data is Secure") ?></h2>
                    <p style="font-size:1rem;color:var(--color-text-secondary);line-height:1.7;margin-bottom:32px;"><?= htmlspecialchars($aboutBlock['content'] ?? "We are registered with Kenya's Office of the Data Protection Commissioner (ODPC) as both a Data Controller and Data Processor, ensuring full compliance and protection.") ?></p>
                    <div style="display:flex;flex-direction:column;gap:20px;">
                        <div style="display:flex;align-items:flex-start;gap:16px;">
                            <div style="width:44px;height:44px;background:#dcfce7;border-radius:12px;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                                <i class="fas fa-shield-alt" style="color:#16a34a;"></i>
                            </div>
                            <div>
                                <h4 style="font-weight:600;color:var(--color-text);margin-bottom:4px;">Enterprise-Grade Security</h4>
                                <p style="font-size:0.9rem;color:var(--color-text-secondary);">End-to-end encryption, secure backups, and role-based access control.</p>
                            </div>
                        </div>
                        <div style="display:flex;align-items:flex-start;gap:16px;">
                            <div style="width:44px;height:44px;background:#dbeafe;border-radius:12px;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                                <i class="fas fa-wifi" style="color:#2563eb;"></i>
                            </div>
                            <div>
                                <h4 style="font-weight:600;color:var(--color-text);margin-bottom:4px;">Works Offline</h4>
                                <p style="font-size:0.9rem;color:var(--color-text-secondary);">Keep selling even without internet. Data syncs automatically when back online.</p>
                            </div>
                        </div>
                        <div style="display:flex;align-items:flex-start;gap:16px;">
                            <div style="width:44px;height:44px;background:#f3e8ff;border-radius:12px;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                                <i class="fas fa-headset" style="color:#7c3aed;"></i>
                            </div>
                            <div>
                                <h4 style="font-weight:600;color:var(--color-text);margin-bottom:4px;"><?= htmlspecialchars($statsSupport) ?> Local Support</h4>
                                <p style="font-size:0.9rem;color:var(--color-text-secondary);">Dedicated support team that understands African business needs.</p>
                            </div>
                        </div>
                    </div>
                </div>
                <?php
                    // Parse numeric value and suffix for counter animation
                    $bizNum = (float) str_replace(',', '', preg_replace('/[^0-9.]/', '', $statsBiz));
                    $bizSuffix = trim(preg_replace('/[0-9.,]/', '', $statsBiz));
                    $uptimeNum = (float) preg_replace('/[^0-9.]/', '', $statsUptime);
                    $uptimeSuffix = trim(preg_replace('/[0-9.,]/', '', $statsUptime));
                ?>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;" class="stats-counter-group">
                    <div style="background:linear-gradient(135deg,#fef9ee,#ffffff);border-radius:16px;padding:28px;text-align:center;border:1px solid #fde68a;">
                        <div style="font-size:2rem;font-weight:800;color:var(--color-primary-dark);margin-bottom:8px;" data-count="<?= $bizNum ?>" data-suffix="<?= htmlspecialchars($bizSuffix) ?>">0</div>
                        <p style="font-size:0.85rem;color:var(--color-text-secondary);font-weight:500;">Active Businesses</p>
                    </div>
                    <div style="background:linear-gradient(135deg,#f0fdf4,#ffffff);border-radius:16px;padding:28px;text-align:center;border:1px solid #bbf7d0;">
                        <div style="font-size:2rem;font-weight:800;color:#16a34a;margin-bottom:8px;" data-count="<?= $uptimeNum ?>" data-suffix="<?= htmlspecialchars($uptimeSuffix) ?>">0</div>
                        <p style="font-size:0.85rem;color:var(--color-text-secondary);font-weight:500;">Uptime Guaranteed</p>
                    </div>
                    <div style="background:linear-gradient(135deg,#eff6ff,#ffffff);border-radius:16px;padding:28px;text-align:center;border:1px solid #bfdbfe;">
                        <i class="fas fa-certificate" style="font-size:2rem;color:#2563eb;margin-bottom:8px;display:block;"></i>
                        <p style="font-size:0.85rem;color:var(--color-text-secondary);font-weight:500;">ODPC Registered</p>
                    </div>
                    <div style="background:linear-gradient(135deg,#faf5ff,#ffffff);border-radius:16px;padding:28px;text-align:center;border:1px solid #e9d5ff;">
                        <i class="fas fa-lock" style="font-size:2rem;color:#7c3aed;margin-bottom:8px;display:block;"></i>
                        <p style="font-size:0.85rem;color:var(--color-text-secondary);font-weight:500;">256-bit Encryption</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- FAQ Section -->
    <?php $faqBlocks = get_landing_blocks('faq'); ?>
    <?php if (!empty($faqBlocks)): ?>
    <section class="faq-section" id="faq">
        <div class="faq-container">
            <div class="faq-header">
                <h2>Frequently Asked Questions</h2>
                <p style="color:var(--color-text-secondary);">Everything you need to know about <?= htmlspecialchars($appName) ?></p>
            </div>
            <?php $firstFaq = true; foreach ($faqBlocks as $f): ?>
            <details class="faq-item" <?= $firstFaq ? 'open' : '' ?>>
                <summary><?= htmlspecialchars($f['title'] ?: $f['block_key']) ?> <i class="fas fa-chevron-down"></i></summary>
                <p class="faq-answer"><?= nl2br(htmlspecialchars($f['content'])) ?></p>
            </details>
            <?php $firstFaq = false; endforeach; ?>
        </div>
    </section>
    <?php endif; ?>

    <!-- CTA Section -->
    <?php $ctaBlocks = get_landing_blocks('cta'); ?>
    <?php $ctaBlock = $ctaBlocks[0] ?? null; ?>
    <section class="cta-section">
        <div class="cta-container">
            <h2><?= htmlspecialchars($ctaBlock['title'] ?? 'Ready to Transform Your Business?') ?></h2>
            <p><?= htmlspecialchars($ctaBlock['content'] ?? ("Join " . $statsBiz . " businesses already using " . $appName . " to streamline operations and boost growth.")) ?></p>
            <a href="auth/register.php" class="cta-btn"><?= htmlspecialchars($ctaBlock['subtitle'] ?? 'Start Free Trial') ?> <i class="fas fa-rocket"></i></a>
        </div>
    </section>

    <!-- Newsletter Section -->
    <section class="newsletter-section">
        <div class="newsletter-container">
            <h3>Stay Updated</h3>
            <p>Get product updates, tips, and business insights delivered to your inbox.</p>
            <form class="newsletter-form" id="newsletterForm" onsubmit="return handleNewsletter(event)">
                <input type="email" class="newsletter-input" placeholder="Enter your email address" required aria-label="Email address">
                <button type="submit" class="newsletter-btn">Subscribe</button>
            </form>
            <p class="newsletter-msg" id="newsletterMsg"></p>
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer-section">
        <div class="footer-container">
            <div class="footer-grid">
                <div>
                    <div style="display:flex;align-items:center;gap:10px;">
                        <?php if ($logoDarkUrl): ?>
                            <img src="<?= htmlspecialchars($logoDarkUrl) ?>" alt="<?= htmlspecialchars($appName) ?>" style="height:36px;width:auto;">
                        <?php elseif ($logoUrl): ?>
                            <img src="<?= htmlspecialchars($logoUrl) ?>" alt="<?= htmlspecialchars($appName) ?>" style="height:36px;width:auto;filter:brightness(2);">
                        <?php else: ?>
                            <i class="fas fa-chart-line" style="font-size:1.5rem;color:var(--color-primary);"></i>
                        <?php endif; ?>
                        <span style="font-size:1.25rem;font-weight:700;color:white;"><?= htmlspecialchars($appName) ?></span>
                    </div>
                    <p class="footer-brand-text">The complete business management platform built for African entrepreneurs. Manage sales, inventory, and operations — all in one place.</p>
                </div>
                <div>
                    <h4 class="footer-heading">Product</h4>
                    <ul class="footer-links">
                        <li><a href="#products">Point of Sale</a></li>
                        <li><a href="#products">Inventory & ERP</a></li>
                        <li><a href="#products">E-Commerce</a></li>
                        <li><a href="#ai-section">AI Assistant</a></li>
                        <li><a href="#pricing">Pricing</a></li>
                    </ul>
                </div>
                <div>
                    <h4 class="footer-heading">Company</h4>
                    <ul class="footer-links">
                        <li><a href="#about">About Us</a></li>
                        <li><a href="#faq">FAQ</a></li>
                        <li><a href="#">Privacy Policy</a></li>
                        <li><a href="#">Terms of Service</a></li>
                    </ul>
                </div>
                <div>
                    <h4 class="footer-heading">Contact</h4>
                    <ul class="footer-links">
                        <li><a href="tel:<?= htmlspecialchars(str_replace(' ', '', $phoneNumber)) ?>"><i class="fas fa-phone" style="margin-right:6px;font-size:0.8rem;"></i><?= htmlspecialchars($phoneNumber) ?></a></li>
                        <li><a href="https://wa.me/<?= htmlspecialchars(str_replace(['+', ' '], '', $whatsappNumber)) ?>" target="_blank"><i class="fab fa-whatsapp" style="margin-right:6px;font-size:0.9rem;"></i>WhatsApp</a></li>
                    </ul>
                    <div class="footer-social" style="margin-top:16px;">
                        <a href="#"><i class="fab fa-facebook-f"></i></a>
                        <a href="#"><i class="fab fa-twitter"></i></a>
                        <a href="#"><i class="fab fa-linkedin-in"></i></a>
                        <a href="#"><i class="fab fa-instagram"></i></a>
                    </div>
                </div>
            </div>
            <div class="footer-bottom">
                <p>&copy; <?= date('Y') ?> <?= htmlspecialchars($appName) ?>. All rights reserved.</p>
                <div style="display:flex;align-items:center;gap:16px;font-size:0.8rem;color:#64748b;">
                    <span><i class="fas fa-shield-alt" style="color:#22c55e;margin-right:4px;"></i>ODPC Compliant</span>
                    <span><i class="fas fa-lock" style="color:#60a5fa;margin-right:4px;"></i>SSL Secured</span>
                </div>
            </div>
        </div>
    </footer>

    <!-- Floating Action Buttons -->
    <div class="floating-buttons">
        <a href="tel:<?= htmlspecialchars(str_replace(' ', '', $phoneNumber)) ?>" class="float-btn float-btn-call" aria-label="Call Us">
            <i class="fas fa-phone"></i>
        </a>
        <a href="https://wa.me/<?= htmlspecialchars(str_replace(['+', ' '], '', $whatsappNumber)) ?>" target="_blank" class="float-btn float-btn-whatsapp" aria-label="Chat on WhatsApp">
            <i class="fab fa-whatsapp"></i>
        </a>
    </div>

    <!-- Live Chat Widget -->
    <button class="live-chat-btn" id="liveChatBtn" aria-label="Live Chat">
        <span class="live-chat-tooltip">Chat with us</span>
        <i class="fas fa-comment-dots"></i>
    </button>

    <!-- Cookie Consent Banner -->
    <div class="cookie-banner" id="cookieBanner">
        <p>We use cookies to enhance your experience. By continuing to browse, you agree to our <a href="#">Privacy Policy</a>.</p>
        <div class="cookie-banner-actions">
            <button class="cookie-btn cookie-btn-accept" onclick="acceptCookies()">Accept</button>
            <button class="cookie-btn cookie-btn-decline" onclick="declineCookies()">Decline</button>
        </div>
    </div>

    <script>
        // Mobile Menu
        function toggleMobileMenu() {
            document.getElementById('mobileMenu').classList.toggle('active');
            document.getElementById('mobileOverlay').classList.toggle('active');
            document.body.style.overflow = document.getElementById('mobileMenu').classList.contains('active') ? 'hidden' : '';
        }
        function closeMobileMenu() {
            document.getElementById('mobileMenu').classList.remove('active');
            document.getElementById('mobileOverlay').classList.remove('active');
            document.body.style.overflow = '';
        }
        function toggleMobileDropdown(el) {
            el.closest('.navigation-mobile-dropdown').classList.toggle('open');
        }

        // Smooth scroll for anchor links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function(e) {
                const href = this.getAttribute('href');
                if (href === '#') return;
                e.preventDefault();
                const target = document.querySelector(href);
                if (target) {
                    target.scrollIntoView({ behavior: 'smooth', block: 'start' });
                    closeMobileMenu();
                }
            });
        });

        // Products Tabs
        document.querySelectorAll('.products-tab-item').forEach(tab => {
            tab.addEventListener('click', function() {
                document.querySelectorAll('.products-tab-item').forEach(t => t.classList.remove('active'));
                document.querySelectorAll('.products-tab-content').forEach(c => c.classList.remove('active'));
                this.classList.add('active');
                document.getElementById(this.dataset.tab + '-content').classList.add('active');
            });
        });

        // AI Carousel
        let currentAiSlide = 0;
        const aiSlides = document.querySelectorAll('.ai-slide');
        const aiIndicators = document.querySelectorAll('.ai-indicator');

        function showAiSlide(index) {
            aiSlides.forEach(s => s.classList.remove('active'));
            aiIndicators.forEach(i => i.classList.remove('active'));
            aiSlides[index].classList.add('active');
            aiIndicators[index].classList.add('active');
            currentAiSlide = index;
        }

        aiIndicators.forEach(indicator => {
            indicator.addEventListener('click', function() {
                showAiSlide(parseInt(this.dataset.slide));
            });
        });

        // Auto-rotate AI carousel
        setInterval(() => {
            showAiSlide((currentAiSlide + 1) % aiSlides.length);
        }, 5000);

        // Scroll animations
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('visible');
                }
            });
        }, { threshold: 0.1, rootMargin: '0px 0px -50px 0px' });

        document.querySelectorAll('.fade-in-up').forEach(el => observer.observe(el));

        // Nav shadow on scroll
        window.addEventListener('scroll', () => {
            const nav = document.querySelector('.navigation-section');
            if (window.scrollY > 10) {
                nav.style.boxShadow = '0 2px 20px rgba(0,0,0,0.08)';
            } else {
                nav.style.boxShadow = 'none';
            }
        });

        // Stats Counter Animation
        function animateCounter(el, target, suffix) {
            const isDecimal = target % 1 !== 0;
            const duration = 2000;
            const start = performance.now();
            function update(now) {
                const elapsed = now - start;
                const progress = Math.min(elapsed / duration, 1);
                const eased = 1 - Math.pow(1 - progress, 3);
                const current = isDecimal ? (target * eased).toFixed(1) : Math.floor(target * eased);
                el.textContent = (typeof current === 'string' ? current : current.toLocaleString()) + suffix;
                if (progress < 1) requestAnimationFrame(update);
            }
            requestAnimationFrame(update);
        }
        const statsObserver = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting && !entry.target.dataset.counted) {
                    entry.target.dataset.counted = 'true';
                    entry.target.querySelectorAll('[data-count]').forEach(el => {
                        const target = parseFloat(el.dataset.count);
                        const suffix = el.dataset.suffix || '';
                        animateCounter(el, target, suffix);
                    });
                }
            });
        }, { threshold: 0.3 });
        document.querySelectorAll('.stats-counter-group').forEach(el => statsObserver.observe(el));

        // Newsletter Handler
        function handleNewsletter(e) {
            e.preventDefault();
            const form = document.getElementById('newsletterForm');
            const msg = document.getElementById('newsletterMsg');
            const email = form.querySelector('input[type="email"]').value;
            const btn = form.querySelector('button[type="submit"]');
            const originalText = btn.textContent;
            btn.textContent = 'Subscribing...';
            btn.disabled = true;

            fetch('api/newsletter-subscribe.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'email=' + encodeURIComponent(email)
            })
            .then(r => r.json())
            .then(data => {
                msg.style.display = 'block';
                msg.style.color = data.success ? '#22c55e' : '#ef4444';
                msg.textContent = data.message;
                if (data.success) form.reset();
            })
            .catch(() => {
                msg.style.display = 'block';
                msg.style.color = '#ef4444';
                msg.textContent = 'Something went wrong. Please try again.';
            })
            .finally(() => {
                btn.textContent = originalText;
                btn.disabled = false;
                setTimeout(() => { msg.style.display = 'none'; }, 5000);
            });
            return false;
        }

        // Cookie Consent
        function showCookieBanner() {
            if (!localStorage.getItem('cookie_consent')) {
                setTimeout(() => {
                    document.getElementById('cookieBanner').classList.add('show');
                }, 2000);
            }
        }
        function acceptCookies() {
            localStorage.setItem('cookie_consent', 'accepted');
            document.getElementById('cookieBanner').classList.remove('show');
        }
        function declineCookies() {
            localStorage.setItem('cookie_consent', 'declined');
            document.getElementById('cookieBanner').classList.remove('show');
        }
        showCookieBanner();

        // Live Chat Widget
        document.getElementById('liveChatBtn').addEventListener('click', function() {
            const wa = 'https://wa.me/<?= htmlspecialchars(str_replace(['+', ' '], '', $whatsappNumber)) ?>?text=' + encodeURIComponent('Hi, I have a question about <?= htmlspecialchars($appName) ?>');
            window.open(wa, '_blank');
        });
    </script>
</body>
</html>