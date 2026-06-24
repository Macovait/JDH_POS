-- ========================================================
-- Data Migration: online_orders
-- Database: jdh_pos
-- Row Count: 8
-- Generated: 2026-06-24 05:23:37
-- ========================================================

SET FOREIGN_KEY_CHECKS=0;

TRUNCATE TABLE `online_orders`;

INSERT INTO `online_orders` (`id`, `tenant_id`, `uuid`, `order_number`, `customer_name`, `customer_phone`, `customer_email`, `subtotal`, `tax_amount`, `shipping_amount`, `discount`, `customer_id`, `delivery_address`, `shipping_address`, `billing_address`, `notes`, `payment_method`, `payment_status`, `coupon_code`, `total`, `status`, `items_json`, `paid_amount`, `placed_at`, `shipped_at`, `delivered_at`, `created_at`, `updated_at`) VALUES
('1', '1', 'demo-track-001', 'ORD-20240624-001', 'John Doe', '0712345678', 'john@example.com', '0.00', '0.0000', '250.0000', '0.00', NULL, '123 Kimathi Street, Nairobi', NULL, NULL, 'Please call before delivery.', 'mpesa', 'paid', NULL, '3750.00', 'shipped', '[{\"product_id\":2,\"name\":\"Baby Blanket\",\"price\":1500,\"quantity\":1},{\"product_id\":8,\"name\":\"Cement\",\"price\":1200,\"quantity\":1}]', '0.00', '2026-06-22 05:58:18', '2026-06-23 05:58:18', NULL, '2026-05-17 05:30:32', '2026-06-24 05:58:18'),
('2', '1', 'f877bb96e103ba6df6d1918e', 'ORD-0CF59FB9', 'Wycliffe Bunde', '0759714022', 'wickymacochiz80@gmail.com', '700.00', '0.0000', '0.0000', '0.00', NULL, '40111', NULL, NULL, '', 'cash', 'pending', NULL, '700.00', 'delivered', NULL, '0.00', NULL, NULL, NULL, '2026-06-22 14:30:17', '2026-06-22 14:34:13'),
('3', '1', '94da2d455ac4656c12fa246a', 'ORD-F1195588', 'Wycliffe Bunde', '0759714022', 'wickymacochiz80@gmail.com', '1500.00', '0.0000', '0.0000', '0.00', NULL, '40111', NULL, NULL, '', 'mpesa', 'pending', NULL, '1500.00', 'delivered', NULL, '0.00', NULL, NULL, NULL, '2026-06-22 15:12:23', '2026-06-23 00:59:31'),
('4', '1', 'd19fadced8bb3553732723e0', 'ORD-C395EBFA', 'Wycliffe Bunde', '0759714022', 'wickymacochiz80@gmail.com', '1500.00', '0.0000', '0.0000', '0.00', NULL, '40111', NULL, NULL, '', 'card', 'pending', NULL, '1500.00', 'pending', NULL, '0.00', NULL, NULL, NULL, '2026-06-23 02:13:14', '2026-06-23 02:13:14'),
('5', '1', '483a08d9cea83234f47946f8', 'ORD-B95AD6E6', 'Wycliffe Bunde', '0759714022', 'wickymacochiz80@gmail.com', '1500.00', '0.0000', '0.0000', '0.00', NULL, '40111', NULL, NULL, '', 'mpesa', 'pending', NULL, '1500.00', 'processing', NULL, '0.00', NULL, NULL, NULL, '2026-06-23 12:26:50', '2026-06-23 18:48:44'),
('6', '1', 'eb4d295bfd9374f493ee16b6', 'ORD-CD794247', 'Wycliffe Bunde', '0759714022', 'wickymacochiz80@gmail.com', '2300.00', '0.0000', '0.0000', '0.00', NULL, '40111', NULL, NULL, '', 'mpesa', 'pending', NULL, '2300.00', 'processing', NULL, '0.00', NULL, NULL, NULL, '2026-06-24 03:52:05', '2026-06-24 03:57:02'),
('7', '1', 'aa7de98ec50d4228b6acfec0', 'ORD-CCF99CA8', 'Wycliffe Bunde', '0759714022', 'wickymacochiz80@gmail.com', '1500.00', '0.0000', '0.0000', '0.00', NULL, '40111', NULL, NULL, '', 'cash', 'pending', NULL, '1500.00', 'processing', NULL, '0.00', NULL, NULL, NULL, '2026-06-24 04:01:00', '2026-06-24 06:20:31'),
('8', '1', '4a8b89ae425223cd8ae0336a', 'ORD-65A17F43', 'Wycliffe Bunde', '0759714022', 'wickymacochiz80@gmail.com', '3900.00', '0.0000', '0.0000', '0.00', NULL, '40111', NULL, NULL, '', 'mpesa', 'pending', NULL, '3900.00', 'pending', NULL, '0.00', NULL, NULL, NULL, '2026-06-24 09:30:22', '2026-06-24 09:30:23');

SET FOREIGN_KEY_CHECKS=1;
