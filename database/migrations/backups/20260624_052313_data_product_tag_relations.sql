-- ========================================================
-- Data Migration: product_tag_relations
-- Database: jdh_pos
-- Row Count: 4
-- Generated: 2026-06-24 05:23:39
-- ========================================================

SET FOREIGN_KEY_CHECKS=0;

TRUNCATE TABLE `product_tag_relations`;

INSERT INTO `product_tag_relations` (`id`, `tenant_id`, `product_id`, `tag_id`, `created_at`) VALUES
('1', '1', '2', '1', '2026-05-18 17:06:14'),
('2', '1', '3', '1', '2026-05-18 17:06:14'),
('3', '1', '1', '1', '2026-05-18 17:06:14'),
('4', '1', '4', '1', '2026-06-09 12:02:23');

SET FOREIGN_KEY_CHECKS=1;
