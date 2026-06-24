-- ========================================================
-- Data Migration: security_audit_log
-- Database: jdh_pos
-- Row Count: 1
-- Generated: 2026-06-24 05:23:41
-- ========================================================

SET FOREIGN_KEY_CHECKS=0;

TRUNCATE TABLE `security_audit_log`;

INSERT INTO `security_audit_log` (`id`, `event_type`, `description`, `performed_by`, `ip_address`, `created_at`) VALUES
('1', 'security_hardening', 'Complete security patch applied - Default accounts locked, weak passwords disabled, rate limiting enabled', 'system', '127.0.0.1', '2026-06-11 11:33:44');

SET FOREIGN_KEY_CHECKS=1;
