-- ========================================================
-- Data Migration: customers
-- Database: jdh_pos
-- Row Count: 1
-- Generated: 2026-06-24 05:23:33
-- ========================================================

SET FOREIGN_KEY_CHECKS=0;

TRUNCATE TABLE `customers`;

INSERT INTO `customers` (`id`, `name`, `phone`, `email`, `address`, `city`, `postal_code`, `tax_id`, `loyalty_points`, `active`, `created_at`, `updated_at`, `status`, `deleted_at`, `deleted_by`, `credit_limit`, `notes`, `created_by`, `branch_id`, `loyalty_tier`, `group_id`, `total_spent`, `last_purchase`, `points_expired`, `tenant_id`, `current_balance`, `total_credit_used`, `total_payments`, `last_payment_date`, `payment_terms_days`, `credit_status`, `credit_notes`, `requires_approval`, `tenant_name`) VALUES
('1', 'Customer_1', 'REDACTED_1', 'customer_1@secured.local', 'REDACTED', 'REDACTED', '40111', '', '200', '1', '2026-05-18 14:35:21', '2026-05-18 11:35:42', '1', NULL, NULL, '0.00', '', '2', '1', 'bronze', NULL, '0.00', NULL, '0', '1', '0.00', '0.00', '0.00', NULL, '30', 'active', NULL, '0', NULL);

SET FOREIGN_KEY_CHECKS=1;
