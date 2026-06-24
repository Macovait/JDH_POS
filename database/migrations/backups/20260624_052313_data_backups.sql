-- ========================================================
-- Data Migration: backups
-- Database: jdh_pos
-- Row Count: 1
-- Generated: 2026-06-24 05:23:30
-- ========================================================

SET FOREIGN_KEY_CHECKS=0;

TRUNCATE TABLE `backups`;

INSERT INTO `backups` (`id`, `filename`, `size`, `status`, `created_at`) VALUES
('1', 'jdh_pos_2026-05-04_08-23-25.sql.gz', '24155', 'success', '2026-05-04 09:23:26');

SET FOREIGN_KEY_CHECKS=1;
