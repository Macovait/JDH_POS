-- ========================================================
-- Data Migration: quotations
-- Database: jdh_pos
-- Row Count: 1
-- Generated: 2026-06-24 05:23:40
-- ========================================================

SET FOREIGN_KEY_CHECKS=0;

TRUNCATE TABLE `quotations`;

INSERT INTO `quotations` (`id`, `tenant_id`, `quotation_number`, `customer_id`, `branch_id`, `business_type_id`, `created_by`, `total`, `status`, `valid_until`, `notes`, `terms`, `created_at`, `updated_at`) VALUES
('2', '1', 'Q20260608-1216', '1', '1', '2', '2', '1500.00', 'sent', '2026-06-15', '', 'Payment due within 30 days. Prices valid for 7 days.', '2026-06-08 18:46:27', '2026-06-08 18:46:27');

SET FOREIGN_KEY_CHECKS=1;
