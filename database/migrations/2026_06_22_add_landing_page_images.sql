-- Add 'image' to input_type enum and create landing page image settings
-- Date: 2026-06-22

-- Step 1: Alter enum to include 'image'
ALTER TABLE system_settings 
MODIFY COLUMN input_type ENUM('text','textarea','password','number','email','url','checkbox','select','color','image') NOT NULL DEFAULT 'text';

-- Step 2: Add landing page image/content settings group
INSERT IGNORE INTO system_settings (setting_key, setting_value, setting_label, setting_description, setting_group, input_type, sort_order) VALUES
('landing_hero_image', '', 'Hero Image', 'Main hero section background or illustration image (recommended: 1200x800px)', 'landing', 'image', 1),
('landing_hero_image_alt', 'POS Dashboard Preview', 'Hero Image Alt Text', 'Alt text for accessibility', 'landing', 'text', 2),
('landing_logo', '', 'Site Logo', 'Upload your site logo (recommended: 200x60px, PNG with transparency)', 'landing', 'image', 3),
('landing_logo_dark', '', 'Logo (Dark Background)', 'Logo variant for dark backgrounds (footer, etc.)', 'landing', 'image', 4),
('landing_favicon', '', 'Favicon', 'Browser tab icon (recommended: 32x32px or 64x64px, ICO/PNG)', 'landing', 'image', 5),
('landing_product_image_1', '', 'Product Screenshot 1', 'Screenshot for the Products section (e.g. POS interface)', 'landing', 'image', 10),
('landing_product_image_2', '', 'Product Screenshot 2', 'Screenshot for the Features section (e.g. inventory)', 'landing', 'image', 11),
('landing_product_image_3', '', 'Product Screenshot 3', 'Screenshot for additional showcase area', 'landing', 'image', 12),
('landing_trust_badge_1', '', 'Trust Badge 1', 'Certification/trust badge image (e.g. ODPC registration)', 'landing', 'image', 20),
('landing_trust_badge_2', '', 'Trust Badge 2', 'Additional trust badge or partner logo', 'landing', 'image', 21),
('landing_og_image', '', 'Social Share Image', 'Image displayed when your site is shared on social media (recommended: 1200x630px)', 'landing', 'image', 30);
