-- ========================================================
-- Data Migration: register_sessions
-- Database: jdh_pos
-- Row Count: 4
-- Generated: 2026-06-24 05:23:41
-- ========================================================

SET FOREIGN_KEY_CHECKS=0;

TRUNCATE TABLE `register_sessions`;

INSERT INTO `register_sessions` (`id`, `branch_id`, `user_id`, `opening_cash`, `closing_cash`, `expected_cash`, `cash_difference`, `total_sales`, `total_cash_sales`, `total_card_sales`, `total_other_sales`, `sale_count`, `notes`, `status`, `opened_at`, `closed_at`, `tenant_id`) VALUES
('1', '1', '1', '200.00', NULL, NULL, NULL, '0.00', '0.00', '0.00', '0.00', '0', NULL, 'open', '2026-05-01 17:37:55', NULL, '1'),
('2', '1', '2', '200.00', '200.00', '200.00', '0.00', '0.00', '0.00', '0.00', '0.00', '0', '', 'closed', '2026-05-02 17:31:09', '2026-05-02 19:28:47', '1'),
('3', '1', '2', '200.00', '500.00', '200.00', '300.00', '0.00', '0.00', '0.00', '0.00', '0', '', 'closed', '2026-05-11 12:23:35', '2026-06-06 22:07:10', '1'),
('4', '1', '2', '200.00', NULL, NULL, NULL, '0.00', '0.00', '0.00', '0.00', '0', NULL, 'open', '2026-06-06 22:07:55', NULL, '1');

SET FOREIGN_KEY_CHECKS=1;
