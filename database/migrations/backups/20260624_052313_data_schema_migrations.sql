-- ========================================================
-- Data Migration: schema_migrations
-- Database: jdh_pos
-- Row Count: 1
-- Generated: 2026-06-24 05:23:41
-- ========================================================

SET FOREIGN_KEY_CHECKS=0;

TRUNCATE TABLE `schema_migrations`;

INSERT INTO `schema_migrations` (`id`, `version`, `name`, `applied_at`, `checksum`, `execution_time_ms`, `status`, `error_message`) VALUES
('1', '005_pos_tabs_and_kot', '005_pos_tabs_and_kot', '2026-06-17 17:00:10', '15cc28e10f47f376843dcb9ea4971f8287b7171677342ddd3045fd57ec370193', '198', 'success', NULL);

SET FOREIGN_KEY_CHECKS=1;
