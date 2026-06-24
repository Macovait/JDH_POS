-- ========================================================
-- Data Migration: system_settings
-- Database: jdh_pos
-- Row Count: 29
-- Generated: 2026-06-24 05:23:43
-- ========================================================

SET FOREIGN_KEY_CHECKS=0;

TRUNCATE TABLE `system_settings`;

INSERT INTO `system_settings` (`id`, `setting_key`, `setting_value`, `setting_label`, `setting_description`, `setting_group`, `input_type`, `setting_options`, `is_required`, `placeholder`, `sort_order`, `created_at`, `updated_at`, `tenant_id`) VALUES
('1', 'site_name', 'Jakababa', 'Site Name', 'The name of your platform displayed on the landing page and browser title', 'general', 'text', NULL, '0', NULL, '1', '2026-06-22 03:41:29', '2026-06-22 06:06:00', '0'),
('2', 'site_tagline', 'All-in-One POS System for African Businesses', 'Tagline', 'Short tagline shown in the hero section and meta title', 'general', 'text', NULL, '0', NULL, '2', '2026-06-22 03:41:29', '2026-06-22 03:41:29', '0'),
('3', 'site_logo_url', '', 'Logo URL', 'URL to your logo image (leave empty for default icon). Upload to /uploads/branding/', 'general', 'url', NULL, '0', NULL, '3', '2026-06-22 03:41:29', '2026-06-22 03:41:29', '0'),
('4', 'site_favicon_url', '', 'Favicon URL', 'URL to your favicon image', 'general', 'url', NULL, '0', NULL, '4', '2026-06-22 03:41:29', '2026-06-22 03:41:29', '0'),
('5', 'site_url', '', 'Site URL', 'The public URL of your site (e.g. https://jakpos.com)', 'general', 'url', NULL, '0', NULL, '5', '2026-06-22 03:41:29', '2026-06-22 03:41:29', '0'),
('6', 'contact_email', '', 'Contact Email', 'Public contact email address', 'general', 'email', NULL, '0', NULL, '6', '2026-06-22 03:41:29', '2026-06-22 03:41:29', '0'),
('7', 'contact_phone', '', 'Contact Phone', 'Public contact phone number', 'general', 'text', NULL, '0', NULL, '7', '2026-06-22 03:41:29', '2026-06-22 03:41:29', '0'),
('8', 'hero_title', 'The All-in-One AI System for African <span class=\"text-primary\">Retail</span> and <span class=\"text-primary\">Wholesale</span> Success', 'Hero Title', 'Main heading on the landing page hero section (HTML allowed)', 'general', 'textarea', NULL, '0', NULL, '10', '2026-06-22 03:41:29', '2026-06-22 03:41:29', '0'),
('9', 'hero_subtitle', 'Manage your sales, inventory, and payments in one place. JAKPOS keeps your business running smoothly, efficiently, and intelligently—anywhere, online or offline.', 'Hero Subtitle', 'Paragraph text below the hero heading', 'general', 'textarea', NULL, '0', NULL, '11', '2026-06-22 03:41:29', '2026-06-22 03:41:29', '0'),
('10', 'hero_cta_text', 'Get a Free Trial', 'Hero CTA Button Text', 'Text for the main call-to-action button', 'general', 'text', NULL, '0', NULL, '12', '2026-06-22 03:41:29', '2026-06-22 03:41:29', '0'),
('11', 'hero_cta_url', 'auth/register.php', 'Hero CTA URL', 'Link for the main CTA button', 'general', 'url', NULL, '0', NULL, '13', '2026-06-22 03:41:29', '2026-06-22 04:25:19', '0'),
('12', 'stats_businesses', '2,000+', 'Stats: Businesses', 'Number shown in hero stats bar', 'general', 'text', NULL, '0', NULL, '14', '2026-06-22 03:41:29', '2026-06-22 03:41:29', '0'),
('13', 'stats_uptime', '99.9%', 'Stats: Uptime', 'Uptime percentage shown in hero stats bar', 'general', 'text', NULL, '0', NULL, '15', '2026-06-22 03:41:29', '2026-06-22 03:41:29', '0'),
('14', 'stats_support', '24/7', 'Stats: Support', 'Support availability shown in hero stats bar', 'general', 'text', NULL, '0', NULL, '16', '2026-06-22 03:41:29', '2026-06-22 03:41:29', '0'),
('15', 'show_pricing_from_db', '1', 'Dynamic Pricing', 'Pull pricing cards from database plans (1=yes, 0=use static)', 'general', 'checkbox', NULL, '0', NULL, '20', '2026-06-22 03:41:29', '2026-06-22 03:41:29', '0'),
('16', 'seo_meta_description', 'Modern point-of-sale system for African businesses. Manage inventory, sales, customers, and analytics all in one place.', 'Meta Description', 'SEO meta description for the landing page', 'seo', 'textarea', NULL, '0', NULL, '1', '2026-06-22 03:41:29', '2026-06-22 03:41:29', '0'),
('17', 'seo_og_image', '', 'OG Image URL', 'Social sharing image URL', 'seo', 'url', NULL, '0', NULL, '2', '2026-06-22 03:41:29', '2026-06-22 03:41:29', '0'),
('18', 'seo_keywords', 'POS, point of sale, inventory management, African business, M-Pesa, retail', 'Meta Keywords', 'Comma-separated keywords', 'seo', 'text', NULL, '0', NULL, '3', '2026-06-22 03:41:29', '2026-06-22 03:41:29', '0'),
('19', 'landing_hero_image', '', 'Hero Image', 'Main hero section background or illustration image (recommended: 1200x800px)', 'landing', 'image', NULL, '0', NULL, '1', '2026-06-22 05:43:14', '2026-06-22 05:43:14', '0'),
('20', 'landing_hero_image_alt', 'POS Dashboard Preview', 'Hero Image Alt Text', 'Alt text for accessibility', 'landing', 'text', NULL, '0', NULL, '2', '2026-06-22 05:43:14', '2026-06-22 05:43:14', '0'),
('21', 'landing_logo', '', 'Site Logo', 'Upload your site logo (recommended: 200x60px, PNG with transparency)', 'landing', 'image', NULL, '0', NULL, '3', '2026-06-22 05:43:14', '2026-06-22 05:43:14', '0'),
('22', 'landing_logo_dark', '', 'Logo (Dark Background)', 'Logo variant for dark backgrounds (footer, etc.)', 'landing', 'image', NULL, '0', NULL, '4', '2026-06-22 05:43:14', '2026-06-22 05:43:14', '0'),
('23', 'landing_favicon', '', 'Favicon', 'Browser tab icon (recommended: 32x32px or 64x64px, ICO/PNG)', 'landing', 'image', NULL, '0', NULL, '5', '2026-06-22 05:43:14', '2026-06-22 05:43:14', '0'),
('24', 'landing_product_image_1', '', 'Product Screenshot 1', 'Screenshot for the Products section (e.g. POS interface)', 'landing', 'image', NULL, '0', NULL, '10', '2026-06-22 05:43:14', '2026-06-22 05:43:14', '0'),
('25', 'landing_product_image_2', '', 'Product Screenshot 2', 'Screenshot for the Features section (e.g. inventory)', 'landing', 'image', NULL, '0', NULL, '11', '2026-06-22 05:43:14', '2026-06-22 05:43:14', '0'),
('26', 'landing_product_image_3', '', 'Product Screenshot 3', 'Screenshot for additional showcase area', 'landing', 'image', NULL, '0', NULL, '12', '2026-06-22 05:43:14', '2026-06-22 05:43:14', '0'),
('27', 'landing_trust_badge_1', '', 'Trust Badge 1', 'Certification/trust badge image (e.g. ODPC registration)', 'landing', 'image', NULL, '0', NULL, '20', '2026-06-22 05:43:14', '2026-06-22 05:43:14', '0'),
('28', 'landing_trust_badge_2', '', 'Trust Badge 2', 'Additional trust badge or partner logo', 'landing', 'image', NULL, '0', NULL, '21', '2026-06-22 05:43:14', '2026-06-22 05:43:14', '0'),
('29', 'landing_og_image', '', 'Social Share Image', 'Image displayed when your site is shared on social media (recommended: 1200x630px)', 'landing', 'image', NULL, '0', NULL, '30', '2026-06-22 05:43:14', '2026-06-22 05:43:14', '0');

SET FOREIGN_KEY_CHECKS=1;
