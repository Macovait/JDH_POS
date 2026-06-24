-- ========================================================
-- Data Migration: notification_jobs
-- Database: jdh_pos
-- Row Count: 7
-- Generated: 2026-06-24 05:23:36
-- ========================================================

SET FOREIGN_KEY_CHECKS=0;

TRUNCATE TABLE `notification_jobs`;

INSERT INTO `notification_jobs` (`id`, `tenant_id`, `type`, `payload`, `status`, `created_at`) VALUES
('1', '1', 'order_placed', '{\"order_id\":2,\"order_number\":\"ORD-0CF59FB9\",\"customer\":\"Wycliffe Bunde\",\"phone\":\"0759714022\",\"total\":700}', 'pending', '2026-06-22 14:30:17'),
('2', '1', 'order_placed', '{\"order_id\":3,\"order_number\":\"ORD-F1195588\",\"customer\":\"Wycliffe Bunde\",\"phone\":\"0759714022\",\"total\":1500}', 'pending', '2026-06-22 15:12:23'),
('3', '1', 'order_placed', '{\"order_id\":4,\"order_number\":\"ORD-C395EBFA\",\"customer\":\"Wycliffe Bunde\",\"phone\":\"0759714022\",\"total\":1500}', 'pending', '2026-06-23 02:13:14'),
('4', '1', 'order_placed', '{\"order_id\":5,\"order_number\":\"ORD-B95AD6E6\",\"customer\":\"Wycliffe Bunde\",\"phone\":\"0759714022\",\"total\":1500}', 'pending', '2026-06-23 12:26:50'),
('5', '1', 'order_placed', '{\"order_id\":6,\"order_number\":\"ORD-CD794247\",\"customer\":\"Wycliffe Bunde\",\"phone\":\"0759714022\",\"total\":2300}', 'pending', '2026-06-24 03:52:05'),
('6', '1', 'order_placed', '{\"order_id\":7,\"order_number\":\"ORD-CCF99CA8\",\"customer\":\"Wycliffe Bunde\",\"phone\":\"0759714022\",\"total\":1500}', 'pending', '2026-06-24 04:01:00'),
('7', '1', 'order_placed', '{\"order_id\":8,\"order_number\":\"ORD-65A17F43\",\"customer\":\"Wycliffe Bunde\",\"phone\":\"0759714022\",\"total\":3900}', 'pending', '2026-06-24 09:30:23');

SET FOREIGN_KEY_CHECKS=1;
