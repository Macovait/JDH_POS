-- ========================================================
-- Data Migration: pos_landing_blocks
-- Database: jdh_pos
-- Row Count: 13
-- Generated: 2026-06-24 05:23:37
-- ========================================================

SET FOREIGN_KEY_CHECKS=0;

TRUNCATE TABLE `pos_landing_blocks`;

INSERT INTO `pos_landing_blocks` (`id`, `section`, `block_key`, `title`, `subtitle`, `content`, `image_url`, `icon_class`, `sort_order`, `is_active`, `created_at`, `updated_at`) VALUES
('1', 'products', 'pos', 'Point of Sale', 'POS', 'Fast checkout with offline capability, barcode scanning, split payments, and multi-currency support. Works seamlessly even without internet and syncs when back online.', 'assets/img/pos-preview.png', 'fas fa-cash-register', '1', '1', '2026-06-22 16:25:56', '2026-06-22 16:25:56'),
('2', 'products', 'erp', 'Inventory & ERP', 'ERP', 'Complete inventory management with stock tracking, purchase orders, supplier management, and multi-branch support. Never run out of stock again.', 'assets/img/erp-preview.png', 'fas fa-boxes-stacked', '2', '1', '2026-06-22 16:25:56', '2026-06-22 16:25:56'),
('3', 'products', 'ecommerce', 'Online Store', 'Store', 'Launch your online storefront with product catalogs, customer accounts, secure checkout, and order tracking. Syncs with your POS inventory in real-time.', 'assets/img/store-preview.png', 'fas fa-store', '3', '1', '2026-06-22 16:25:56', '2026-06-22 16:25:56'),
('4', 'products', 'ai', 'AI Assistant', 'AI', 'Smart ordering suggestions, predictive analytics, customer insights, and automated reports. Let AI handle the heavy lifting while you focus on growth.', 'assets/img/ai-preview.png', 'fas fa-robot', '4', '1', '2026-06-22 16:25:56', '2026-06-22 16:25:56'),
('5', 'how_it_works', 'signup', 'Create Account', NULL, 'Sign up in under 2 minutes with your business name and email. No credit card required to start your free trial.', NULL, 'fas fa-user-plus', '1', '1', '2026-06-22 16:25:56', '2026-06-22 16:25:56'),
('6', 'how_it_works', 'setup', 'Setup Your Store', NULL, 'Add your products, set up branches, and configure your settings. Import existing data via CSV with one click.', NULL, 'fas fa-cogs', '2', '1', '2026-06-22 16:25:56', '2026-06-22 16:25:56'),
('7', 'how_it_works', 'sell', 'Start Selling', NULL, 'Process sales, manage inventory, and serve customers online or in-store. Track everything from one dashboard.', NULL, 'fas fa-chart-line', '3', '1', '2026-06-22 16:25:56', '2026-06-22 16:25:56'),
('8', 'trust_strip', 'secure', 'Bank-Level Security', NULL, NULL, NULL, 'fas fa-shield-alt', '1', '1', '2026-06-22 16:25:56', '2026-06-22 16:25:56'),
('9', 'trust_strip', 'offline', 'Offline Capable', NULL, NULL, NULL, 'fas fa-wifi', '2', '1', '2026-06-22 16:25:56', '2026-06-22 16:25:56'),
('10', 'trust_strip', 'support', '24/7 Support', NULL, NULL, NULL, 'fas fa-headset', '3', '1', '2026-06-22 16:25:56', '2026-06-22 16:25:56'),
('11', 'ai_slides', 'smart_ordering', 'Smart Ordering', NULL, 'AI analyzes your sales patterns and automatically suggests optimal reorder quantities. Never overstock or understock again.', NULL, 'fas fa-wand-magic-sparkles', '1', '1', '2026-06-22 16:25:56', '2026-06-22 16:25:56'),
('12', 'ai_slides', 'insights', 'Customer Insights', NULL, 'Understand your best customers, peak hours, and top products. Make data-driven decisions with AI-powered analytics.', NULL, 'fas fa-brain', '2', '1', '2026-06-22 16:25:56', '2026-06-22 16:25:56'),
('13', 'ai_slides', 'forecasting', 'Sales Forecasting', NULL, 'Predict future sales trends based on historical data, seasonality, and market patterns. Plan ahead with confidence.', NULL, 'fas fa-chart-line', '3', '1', '2026-06-22 16:25:56', '2026-06-22 16:25:56');

SET FOREIGN_KEY_CHECKS=1;
