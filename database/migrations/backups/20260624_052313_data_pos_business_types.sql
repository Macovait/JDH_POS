-- ========================================================
-- Data Migration: pos_business_types
-- Database: jdh_pos
-- Row Count: 15
-- Generated: 2026-06-24 05:23:37
-- ========================================================

SET FOREIGN_KEY_CHECKS=0;

TRUNCATE TABLE `pos_business_types`;

INSERT INTO `pos_business_types` (`id`, `slug`, `name`, `icon`, `color`, `description`, `is_active`, `sort_order`, `created_at`, `updated_at`) VALUES
('1', 'retail', 'Retail Store', 'fa-store', '#3B82F6', 'General retail store', '1', '0', '2026-05-02 18:14:36', '2026-05-02 18:14:36'),
('2', 'supermarket', 'Supermarket', 'fa-shopping-cart', '#10B981', 'Large retail store with multiple categories', '1', '1', '2026-05-02 18:14:36', '2026-05-02 18:14:36'),
('3', 'restaurant', 'Restaurant / Cafe', 'fa-utensils', '#F59E0B', 'Food service establishment', '1', '2', '2026-05-02 18:14:36', '2026-05-02 18:14:36'),
('4', 'pharmacy', 'Pharmacy', 'fa-pills', '#EF4444', 'Pharmaceutical and medical supplies', '1', '3', '2026-05-02 18:14:36', '2026-05-02 18:14:36'),
('5', 'salon', 'Salon / Beauty', 'fa-cut', '#EC4899', 'Beauty and personal care services', '1', '4', '2026-05-02 18:14:36', '2026-05-02 18:14:36'),
('6', 'electronics', 'Electronics', 'fa-laptop', '#8B5CF6', 'Electronic devices and accessories', '1', '5', '2026-05-02 18:14:36', '2026-05-02 18:14:36'),
('7', 'hardware', 'Hardware Store', 'fa-hammer', '#F97316', 'Construction and home improvement supplies', '1', '6', '2026-05-02 18:14:36', '2026-05-02 18:14:36'),
('8', 'butchery', 'Butchery', 'fa-drumstick-bite', '#DC2626', 'Fresh meat and poultry', '1', '7', '2026-05-02 18:14:36', '2026-05-02 18:14:36'),
('9', 'bakery', 'Bakery', 'fa-bread-slice', '#D97706', 'Fresh baked goods', '1', '8', '2026-05-02 18:14:36', '2026-05-02 18:14:36'),
('10', 'hotel', 'Hotel / Lodging', 'fa-hotel', '#6366F1', 'Hospitality and accommodation', '1', '9', '2026-05-02 18:14:36', '2026-05-02 18:14:36'),
('11', 'wholesale', 'Wholesale', 'fa-boxes', '#06B6D4', 'Bulk sales and distribution', '0', '10', '2026-05-02 18:14:36', '2026-05-16 14:07:58'),
('12', 'service', 'Service Business', 'fa-concierge-bell', '#14B8A6', 'Professional services', '0', '11', '2026-05-02 18:14:36', '2026-05-16 14:07:58'),
('13', 'grocery', 'Grocery Store', 'fa-apple-alt', '#22C55E', 'Food and household items', '0', '12', '2026-05-02 18:14:36', '2026-05-16 14:07:58'),
('14', 'liquor_store', 'Liquor Store', 'fa-wine-bottle', '#7C3AED', 'Alcohol and beverage retail', '1', '10', '2026-05-16 14:07:58', '2026-05-16 14:07:58'),
('15', 'stationery', 'Stationery / Office', 'fa-pencil-alt', '#0EA5E9', 'Office and school supplies', '1', '11', '2026-05-16 14:07:58', '2026-05-16 14:07:58');

SET FOREIGN_KEY_CHECKS=1;
