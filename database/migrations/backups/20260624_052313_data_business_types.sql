-- ========================================================
-- Data Migration: business_types
-- Database: jdh_pos
-- Row Count: 12
-- Generated: 2026-06-24 05:23:30
-- ========================================================

SET FOREIGN_KEY_CHECKS=0;

TRUNCATE TABLE `business_types`;

INSERT INTO `business_types` (`id`, `code`, `name`, `description`, `icon`, `default_receipt_template`, `order_types`, `product_fields`, `features`, `active`, `is_active`, `sort_order`, `created_at`, `updated_at`) VALUES
('1', 'retail', 'Retail / General Store', 'General retail and point of sale', 'fa-store', 'default', '[\"walk-in\",\"online\",\"delivery\"]', NULL, '{\"inventory\":true,\"barcode\":true}', '1', '1', '0', '2026-04-30 22:05:32', '2026-04-30 22:05:32'),
('2', 'supermarket', 'Supermarket / Grocery', 'Supermarket and grocery stores', 'fa-shopping-cart', 'default', '[\"walk-in\",\"online\",\"delivery\"]', NULL, '{\"inventory\":true,\"barcode\":true}', '1', '1', '0', '2026-04-30 22:05:32', '2026-04-30 22:05:32'),
('3', 'restaurant', 'Restaurant / Cafe', 'Restaurants, cafes and food service', 'fa-utensils', 'default', '[\"walk-in\",\"online\",\"delivery\"]', NULL, '{\"inventory\":true,\"barcode\":true}', '1', '1', '0', '2026-04-30 22:05:32', '2026-04-30 22:05:32'),
('4', 'pharmacy', 'Pharmacy / Drugstore', 'Pharmacies and drugstores', 'fa-pills', 'default', '[\"walk-in\",\"online\",\"delivery\"]', NULL, '{\"inventory\":true,\"barcode\":true}', '1', '1', '0', '2026-04-30 22:05:32', '2026-04-30 22:05:32'),
('5', 'fashion', 'Fashion / Clothing', 'Fashion and clothing stores', 'fa-tshirt', 'default', '[\"walk-in\",\"online\",\"delivery\"]', NULL, '{\"inventory\":true,\"barcode\":true}', '1', '1', '0', '2026-04-30 22:05:32', '2026-04-30 22:05:32'),
('6', 'electronics', 'Electronics', 'Electronics and tech stores', 'fa-laptop', 'default', '[\"walk-in\",\"online\",\"delivery\"]', NULL, '{\"inventory\":true,\"barcode\":true}', '1', '1', '0', '2026-04-30 22:05:32', '2026-04-30 22:05:32'),
('7', 'hardware', 'Hardware / Building', 'Hardware and building materials', 'fa-wrench', 'default', '[\"walk-in\",\"online\",\"delivery\"]', NULL, '{\"inventory\":true,\"barcode\":true}', '1', '1', '0', '2026-04-30 22:05:32', '2026-04-30 22:05:32'),
('8', 'salon', 'Salon / Beauty', 'Beauty salons and spa', 'fa-cut', 'default', '[\"walk-in\",\"online\",\"delivery\"]', NULL, '{\"inventory\":true,\"barcode\":true}', '1', '1', '0', '2026-04-30 22:05:32', '2026-04-30 22:05:32'),
('9', 'wholesale', 'Wholesale', 'Wholesale and distribution', 'fa-boxes', 'default', '[\"walk-in\",\"online\",\"delivery\"]', NULL, '{\"inventory\":true,\"barcode\":true}', '0', '1', '0', '2026-04-30 22:05:32', '2026-05-16 16:18:36'),
('10', 'other', 'Other', 'Other business types', 'fa-ellipsis-h', 'default', '[\"walk-in\",\"online\",\"delivery\"]', NULL, '{\"inventory\":true,\"barcode\":true}', '1', '1', '0', '2026-04-30 22:05:32', '2026-04-30 22:05:32'),
('11', 'liquor_store', 'Liquor Store', NULL, 'fa-wine-bottle', 'default', NULL, NULL, NULL, '1', '1', '0', '2026-05-16 16:18:36', '2026-05-16 16:18:36'),
('12', 'stationery', 'Stationery / Office', NULL, 'fa-pencil-alt', 'default', NULL, NULL, NULL, '1', '1', '0', '2026-05-16 16:18:36', '2026-05-16 16:18:36');

SET FOREIGN_KEY_CHECKS=1;
