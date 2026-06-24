-- ========================================================
-- Data Migration: storefront_themes
-- Database: jdh_pos
-- Row Count: 1
-- Generated: 2026-06-24 05:23:42
-- ========================================================

SET FOREIGN_KEY_CHECKS=0;

TRUNCATE TABLE `storefront_themes`;

INSERT INTO `storefront_themes` (`id`, `tenant_id`, `theme_name`, `primary_color`, `secondary_color`, `background_color`, `text_color`, `font_family`, `custom_css`, `custom_js`, `header_html`, `footer_html`, `is_active`, `created_at`, `updated_at`) VALUES
('1', '1', 'default', '#f68b1e', '#1a1a2e', '#ffffff', '#282828', 'Inter', NULL, NULL, NULL, NULL, '1', '2026-05-17 09:47:32', '2026-05-17 09:47:32');

SET FOREIGN_KEY_CHECKS=1;
