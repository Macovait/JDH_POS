-- Seed landing page / branding settings into system_settings
-- Date: 2026-06-22
-- These are platform-wide (tenant_id = NULL) settings managed from admin/settings.php

-- General / Branding
INSERT IGNORE INTO system_settings (setting_key, setting_value, setting_label, setting_description, setting_group, input_type, sort_order) VALUES
('site_name', 'JAKPOS', 'Site Name', 'The name of your platform displayed on the landing page and browser title', 'general', 'text', 1),
('site_tagline', 'All-in-One POS System for African Businesses', 'Tagline', 'Short tagline shown in the hero section and meta title', 'general', 'text', 2),
('site_logo_url', '', 'Logo URL', 'URL to your logo image (leave empty for default icon). Upload to /uploads/branding/', 'general', 'url', 3),
('site_favicon_url', '', 'Favicon URL', 'URL to your favicon image', 'general', 'url', 4),
('site_url', '', 'Site URL', 'The public URL of your site (e.g. https://jakpos.com)', 'general', 'url', 5),
('contact_email', '', 'Contact Email', 'Public contact email address', 'general', 'email', 6),
('contact_phone', '', 'Contact Phone', 'Public contact phone number', 'general', 'text', 7);

-- Landing Page Content
INSERT IGNORE INTO system_settings (setting_key, setting_value, setting_label, setting_description, setting_group, input_type, sort_order) VALUES
('hero_title', 'The All-in-One AI System for African <span class="text-primary">Retail</span> and <span class="text-primary">Wholesale</span> Success', 'Hero Title', 'Main heading on the landing page hero section (HTML allowed)', 'general', 'textarea', 10),
('hero_subtitle', 'Manage your sales, inventory, and payments in one place. JAKPOS keeps your business running smoothly, efficiently, and intelligently—anywhere, online or offline.', 'Hero Subtitle', 'Paragraph text below the hero heading', 'general', 'textarea', 11),
('hero_cta_text', 'Get a Free Trial', 'Hero CTA Button Text', 'Text for the main call-to-action button', 'general', 'text', 12),
('hero_cta_url', 'auth/register.php', 'Hero CTA URL', 'Link for the main CTA button', 'general', 'url', 13),
('stats_businesses', '2,000+', 'Stats: Businesses', 'Number shown in hero stats bar', 'general', 'text', 14),
('stats_uptime', '99.9%', 'Stats: Uptime', 'Uptime percentage shown in hero stats bar', 'general', 'text', 15),
('stats_support', '24/7', 'Stats: Support', 'Support availability shown in hero stats bar', 'general', 'text', 16),
('show_pricing_from_db', '1', 'Dynamic Pricing', 'Pull pricing cards from database plans (1=yes, 0=use static)', 'general', 'checkbox', 20);

-- SEO
INSERT IGNORE INTO system_settings (setting_key, setting_value, setting_label, setting_description, setting_group, input_type, sort_order) VALUES
('seo_meta_description', 'Modern point-of-sale system for African businesses. Manage inventory, sales, customers, and analytics all in one place.', 'Meta Description', 'SEO meta description for the landing page', 'seo', 'textarea', 1),
('seo_og_image', '', 'OG Image URL', 'Social sharing image URL', 'seo', 'url', 2),
('seo_keywords', 'POS, point of sale, inventory management, African business, M-Pesa, retail', 'Meta Keywords', 'Comma-separated keywords', 'seo', 'text', 3);
