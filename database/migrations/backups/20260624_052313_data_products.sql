-- ========================================================
-- Data Migration: products
-- Database: jdh_pos
-- Row Count: 8
-- Generated: 2026-06-24 05:23:39
-- ========================================================

SET FOREIGN_KEY_CHECKS=0;

TRUNCATE TABLE `products`;

INSERT INTO `products` (`id`, `business_type`, `business_type_id`, `category_id`, `brand_id`, `name`, `sku`, `global_unique_id`, `barcode`, `price`, `sale_price`, `sale_price_dates_from`, `sale_price_dates_to`, `selling_price`, `cost_price`, `active`, `image`, `description`, `external_url`, `button_text`, `created_by`, `updated_by`, `created_at`, `updated_at`, `status`, `tax_rate_id`, `branch_id`, `deleted_at`, `deleted_by`, `unit`, `tax_rate`, `reorder_level`, `stock_status`, `manage_stock`, `backorders`, `low_stock_amount`, `sold_individually`, `weight`, `length`, `width`, `height`, `shipping_class`, `purchase_note`, `menu_order`, `enable_reviews`, `product_type`, `virtual`, `downloadable`, `tenant_id`) VALUES
('1', NULL, '2', '1', NULL, 'Heavy Rompers', 'HEAVYROM-590', NULL, '', '1500.00', NULL, NULL, NULL, NULL, '700.00', '1', 'uploads/product_images/prod_1782128909_6a39210d57896.png', 'Very Soft', NULL, NULL, NULL, NULL, '2026-05-01 13:38:35', '2026-06-22 14:48:29', '1', NULL, NULL, NULL, NULL, 'pcs', '0.00', '0', 'instock', '0', 'no', NULL, '0', NULL, NULL, NULL, NULL, NULL, NULL, '0', '1', 'simple', '0', '0', '1'),
('2', NULL, '2', '1', NULL, 'Baby Blanket', 'BABYBLAN-300', NULL, '', '1500.00', NULL, NULL, NULL, NULL, '699.98', '1', 'uploads/product_images/prod_1779471103_6a1092ff1677d.jpg', '', NULL, NULL, NULL, NULL, '2026-05-12 23:14:16', '2026-05-22 17:31:43', '1', NULL, NULL, NULL, NULL, 'pcs', '0.00', '0', 'instock', '0', 'no', NULL, '0', NULL, NULL, NULL, NULL, NULL, NULL, '0', '1', 'simple', '0', '0', '1'),
('3', NULL, '2', '1', NULL, 'Baby Shawl', 'BABYSHAW-213', NULL, '', '1600.00', NULL, NULL, NULL, NULL, '749.98', '1', 'uploads/product_images/prod_1782128882_6a3920f23858f.jpg', '', NULL, NULL, NULL, NULL, '2026-05-12 23:16:38', '2026-06-22 14:48:02', '1', NULL, NULL, NULL, NULL, 'pcs', '0.00', '0', 'instock', '0', 'no', NULL, '0', NULL, NULL, NULL, NULL, NULL, NULL, '0', '1', 'simple', '0', '0', '1'),
('4', NULL, '2', '1', NULL, 'Cussion packed', 'CUSSIONP-746', NULL, '', '2300.00', NULL, NULL, NULL, NULL, '1200.00', '1', 'uploads/product_images/prod_1782128786_6a3920921ee68.png', '', NULL, NULL, NULL, NULL, '2026-05-12 23:17:47', '2026-06-22 14:46:26', '1', NULL, NULL, NULL, NULL, 'pcs', '0.00', '0', 'instock', '0', 'no', NULL, '0', NULL, NULL, NULL, NULL, NULL, NULL, '0', '1', 'simple', '0', '0', '1'),
('5', NULL, '2', '1', NULL, 'Mackintosh', 'MACKINTO-270', NULL, '', '700.00', NULL, NULL, NULL, NULL, '300.00', '1', 'uploads/product_images/prod_1780311487_6a1d65bf4f8a7.jpg', '', NULL, NULL, NULL, NULL, '2026-05-12 23:18:49', '2026-06-01 13:58:07', '1', NULL, NULL, NULL, NULL, 'pcs', '0.00', '0', 'instock', '0', 'no', NULL, '0', NULL, NULL, NULL, NULL, NULL, NULL, '0', '1', 'simple', '0', '0', '1'),
('6', NULL, '2', '1', NULL, 'Flannel Sheets', 'FLANNELS-286', NULL, '', '1500.00', NULL, NULL, NULL, NULL, '560.00', '1', 'uploads/product_images/prod_1780311343_6a1d652f98549.png', '', NULL, NULL, NULL, NULL, '2026-05-12 23:21:23', '2026-06-01 13:55:43', '1', NULL, NULL, NULL, NULL, 'pcs', '0.00', '0', 'instock', '0', 'no', NULL, '0', NULL, NULL, NULL, NULL, NULL, NULL, '0', '1', 'simple', '0', '0', '1'),
('7', NULL, '2', '1', NULL, 'Mittens', 'MITTENS-227', NULL, '', '100.00', NULL, NULL, NULL, NULL, '65.00', '1', 'uploads/product_images/prod_1779470176_6a108f606abf1.jpg', '', NULL, NULL, NULL, NULL, '2026-05-12 23:21:55', '2026-05-22 17:16:16', '1', NULL, NULL, NULL, NULL, 'pcs', '0.00', '5', 'instock', '0', 'no', NULL, '0', NULL, NULL, NULL, NULL, NULL, NULL, '0', '1', 'simple', '0', '0', '1'),
('8', NULL, '7', NULL, NULL, 'Cement', 'CEMENT-667', NULL, '', '1200.00', NULL, NULL, NULL, NULL, '650.00', '1', NULL, '', NULL, NULL, NULL, NULL, '2026-05-13 11:22:24', NULL, '1', NULL, NULL, NULL, NULL, 'pcs', '0.00', '0', 'instock', '0', 'no', NULL, '0', NULL, NULL, NULL, NULL, NULL, NULL, '0', '1', 'simple', '0', '0', '1');

SET FOREIGN_KEY_CHECKS=1;
