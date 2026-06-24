-- ========================================================
-- Data Migration: pos_newsletter_subscribers
-- Database: jdh_pos
-- Row Count: 1
-- Generated: 2026-06-24 05:23:37
-- ========================================================

SET FOREIGN_KEY_CHECKS=0;

TRUNCATE TABLE `pos_newsletter_subscribers`;

INSERT INTO `pos_newsletter_subscribers` (`id`, `email`, `source`, `subscribed_at`, `is_active`) VALUES
('1', 'shacazbabyandmother@gmail.com', 'landing_page', '2026-06-22 15:03:37', '1');

SET FOREIGN_KEY_CHECKS=1;
