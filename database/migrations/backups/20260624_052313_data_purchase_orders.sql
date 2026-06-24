-- ========================================================
-- Data Migration: purchase_orders
-- Database: jdh_pos
-- Row Count: 2
-- Generated: 2026-06-24 05:23:40
-- ========================================================

SET FOREIGN_KEY_CHECKS=0;

TRUNCATE TABLE `purchase_orders`;

INSERT INTO `purchase_orders` (`id`, `supplier_id`, `branch_id`, `total`, `status`, `expected_date`, `notes`, `due_amount`, `paid_amount`, `manager`, `created_by`, `created_at`, `updated_at`, `tenant_id`) VALUES
('1', '3', '1', '15000.00', 'received', '2026-06-09', 'It is emergency', '0.00', '0.00', NULL, '0', '2026-06-09 15:40:39', '2026-06-09 17:05:16', '1'),
('2', '3', '1', '17000.00', 'received', '2026-06-10', 'Waiting', '0.00', '0.00', NULL, '0', '2026-06-09 17:25:10', '2026-06-09 17:26:44', '1');

SET FOREIGN_KEY_CHECKS=1;
