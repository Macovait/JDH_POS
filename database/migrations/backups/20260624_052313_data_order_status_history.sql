-- ========================================================
-- Data Migration: order_status_history
-- Database: jdh_pos
-- Row Count: 4
-- Generated: 2026-06-24 05:23:37
-- ========================================================

SET FOREIGN_KEY_CHECKS=0;

TRUNCATE TABLE `order_status_history`;

INSERT INTO `order_status_history` (`id`, `tenant_id`, `order_id`, `from_status`, `to_status`, `changed_by`, `changed_by_type`, `notes`, `metadata`, `created_at`) VALUES
('1', '1', '1', 'pending', 'pending', NULL, 'system', 'Order placed by customer', NULL, '2026-06-22 05:58:18'),
('2', '1', '1', 'pending', 'confirmed', NULL, 'system', 'Payment confirmed via M-Pesa', NULL, '2026-06-22 06:58:18'),
('3', '1', '1', 'pending', 'processing', NULL, 'system', 'Order being prepared for dispatch', NULL, '2026-06-23 05:58:18'),
('4', '1', '1', 'pending', 'shipped', NULL, 'system', 'Order handed to courier', NULL, '2026-06-23 06:58:18');

SET FOREIGN_KEY_CHECKS=1;
