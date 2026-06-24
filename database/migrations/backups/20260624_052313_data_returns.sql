-- ========================================================
-- Data Migration: returns
-- Database: jdh_pos
-- Row Count: 23
-- Generated: 2026-06-24 05:23:41
-- ========================================================

SET FOREIGN_KEY_CHECKS=0;

TRUNCATE TABLE `returns`;

INSERT INTO `returns` (`id`, `return_number`, `return_type`, `sale_id`, `customer_id`, `branch_id`, `processed_by`, `approved_by`, `approved_at`, `reason`, `refund_method`, `amount`, `status`, `notes`, `created_at`, `updated_at`, `tenant_id`, `approval_level`, `approved_by_manager_id`, `approved_at_manager`, `requires_approval`, `high_value_flag`) VALUES
('1', 'R20260510-1296', 'sales', '6', NULL, '1', '0', NULL, NULL, 'wrong_item', 'cash', '1500.00', 'completed', '', '2026-05-10 15:29:03', '2026-05-10 15:29:03', '1', 'level1', NULL, NULL, '0', '0'),
('3', 'R20260511-668', 'sales', '5', NULL, '1', '2', NULL, NULL, 'Wrong Item', 'cash', '1500.00', 'pending', '', '2026-05-11 06:36:51', '2026-06-12 15:20:03', '1', 'level1', NULL, NULL, '0', '0'),
('4', 'R20260511-542', 'sales', '4', NULL, '1', '2', NULL, NULL, 'Wrong Item', 'cash', '1500.00', 'pending', '', '2026-05-11 06:38:22', '2026-06-12 15:20:03', '1', 'level1', NULL, NULL, '0', '0'),
('5', 'R20260511-1701', 'sales', '4', NULL, '1', '0', NULL, NULL, 'wrong_item', 'cash', '1500.00', 'completed', '', '2026-05-11 07:24:36', '2026-05-11 07:24:36', '1', 'level1', NULL, NULL, '0', '0'),
('6', 'R20260511-600', 'sales', '3', NULL, '1', '2', '2', NULL, 'Wrong Item', 'cash', '1500.00', 'completed', '', '2026-05-11 08:06:08', '2026-06-12 15:20:03', '1', 'level1', NULL, NULL, '0', '0'),
('7', 'R20260514-024', 'sales', '29', NULL, '1', '2', NULL, NULL, 'Wrong Item', 'cash', '1500.00', 'pending', '', '2026-05-14 11:28:23', '2026-06-12 15:20:03', '1', 'level1', NULL, NULL, '0', '0'),
('8', 'R20260514-598', 'sales', '22', NULL, '1', '2', NULL, NULL, 'Customer Changed Mind', 'cash', '1500.00', 'pending', '', '2026-05-14 11:31:26', '2026-06-12 15:20:03', '1', 'level1', NULL, NULL, '0', '0'),
('9', 'R20260514-574', 'sales', '28', NULL, '1', '2', NULL, NULL, 'Wrong Item', 'cash', '1500.00', 'pending', '', '2026-05-14 14:29:11', '2026-06-12 15:20:03', '1', 'level1', NULL, NULL, '0', '0'),
('10', 'R20260514-637', 'sales', '27', NULL, '1', '2', NULL, NULL, 'Damaged/Defective', 'cash', '1500.00', 'pending', '', '2026-05-14 14:32:00', '2026-06-12 15:20:03', '1', 'level1', NULL, NULL, '0', '0'),
('11', 'R20260514-706', 'sales', '23', NULL, '1', '2', NULL, NULL, 'Wrong Item', 'cash', '1500.00', 'pending', '', '2026-05-14 20:28:55', '2026-06-12 15:20:03', '1', 'level1', NULL, NULL, '0', '0'),
('12', 'R20260514-554', 'sales', '26', NULL, '1', '2', '2', NULL, 'Damaged/Defective', 'cash', '1500.00', 'completed', '', '2026-05-14 20:30:56', '2026-06-12 15:20:03', '1', 'level1', NULL, NULL, '0', '0'),
('16', 'R20260517-532', 'sales', '33', NULL, '1', '2', NULL, NULL, 'Customer Changed Mind', 'cash', '1200.00', 'pending', '', '2026-05-17 08:15:35', '2026-06-12 15:20:03', '1', 'level1', NULL, NULL, '0', '0'),
('17', 'R20260517-458', 'sales', '34', NULL, '1', '2', NULL, NULL, 'Wrong Item', 'cash', '1500.00', 'pending', '', '2026-05-17 08:57:27', '2026-06-12 15:20:03', '1', 'level1', NULL, NULL, '0', '0'),
('28', 'R20260517-900', 'sales', '32', NULL, '1', '2', '2', NULL, 'Wrong Item', 'cash', '1500.00', 'completed', '', '2026-05-17 19:25:53', '2026-06-01 16:43:49', '1', 'level1', NULL, NULL, '0', '0'),
('31', 'R20260603-B332', 'sales', '52', NULL, '1', '2', NULL, NULL, 'wrong_item', 'cash', '1500.00', 'completed', '', '2026-06-03 16:09:25', '2026-06-03 16:09:41', '1', 'level1', NULL, NULL, '0', '0'),
('32', 'R20260603-A6EF', 'sales', '53', NULL, '1', '2', NULL, NULL, 'wrong_item', 'cash', '1500.00', 'pending', '', '2026-06-03 22:49:53', '2026-06-03 22:49:53', '1', 'level1', NULL, NULL, '0', '0'),
('33', 'R20260603-4772', 'sales', '53', NULL, '1', '2', NULL, NULL, 'wrong_item', 'cash', '0.00', 'rejected', '\n[2026-06-03 23:48:01] Status changed from pending to rejected by user 2: ', '2026-06-03 23:05:25', '2026-06-03 23:48:01', '1', 'level1', NULL, NULL, '0', '0'),
('34', 'R20260604-DACB', 'sales', '60', NULL, '1', '2', NULL, NULL, 'customer_changed_mind', 'cash', '2700.00', 'pending', '\n[2026-06-04 23:50:11] Status changed from pending to pending by user 2: ', '2026-06-04 21:51:20', '2026-06-04 23:50:11', '1', 'level1', NULL, NULL, '0', '0'),
('35', 'R20260607-CA78', 'sales', '70', NULL, '1', '2', NULL, NULL, 'wrong_item', 'cash', '700.00', 'pending', '', '2026-06-07 15:56:53', '2026-06-07 15:56:53', '1', 'level1', NULL, NULL, '0', '0'),
('36', 'R20260607-EF9B', 'sales', '70', NULL, '1', '2', NULL, NULL, 'wrong_item', 'cash', '700.00', 'pending', '', '2026-06-07 17:20:47', '2026-06-07 17:20:47', '1', 'level1', NULL, NULL, '0', '0'),
('37', 'R20260607-7DAE', 'sales', '70', NULL, '1', '2', NULL, NULL, 'quality_issue', 'cash', '0.00', 'pending', '', '2026-06-07 17:38:58', '2026-06-07 17:38:58', '1', 'level1', NULL, NULL, '0', '0'),
('38', 'R20260607-1169', 'sales', '70', NULL, '1', '0', NULL, NULL, 'wrong_item', 'cash', '1400.00', 'completed', '', '2026-06-07 20:29:55', '2026-06-07 20:29:55', '1', 'level1', NULL, NULL, '0', '0'),
('39', 'R20260607-3892', 'sales', '69', NULL, '1', '0', NULL, NULL, 'wrong_item', 'card', '700.00', 'completed', '', '2026-06-07 21:07:53', '2026-06-07 21:07:53', '1', 'level1', NULL, NULL, '0', '0');

SET FOREIGN_KEY_CHECKS=1;
