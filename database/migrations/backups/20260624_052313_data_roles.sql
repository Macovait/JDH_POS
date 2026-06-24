-- ========================================================
-- Data Migration: roles
-- Database: jdh_pos
-- Row Count: 17
-- Generated: 2026-06-24 05:23:41
-- ========================================================

SET FOREIGN_KEY_CHECKS=0;

TRUNCATE TABLE `roles`;

INSERT INTO `roles` (`id`, `name`, `description`, `tenant_id`, `is_system`, `deleted_at`) VALUES
('1', 'Administrator', 'Full tenant access', '1', '1', NULL),
('2', 'Manager', 'Manage operations', '1', '1', NULL),
('3', 'Cashier', 'Process sales', '1', '1', NULL),
('4', 'Inventory', 'Manage inventory', '1', '1', NULL),
('5', 'Super Admin', 'Full platform access - manage all tenants, system settings', '1', '1', NULL),
('6', 'Developer', 'System maintenance, debugging, code access', '1', '1', NULL),
('7', 'Support', 'Platform support, view all tenants, resolve tickets', '1', '1', NULL),
('8', 'Owner', 'Full company access, billing, subscription management', '1', '0', NULL),
('9', 'Accountant', 'Financial operations, reports, expenses, billing', '1', '0', NULL),
('10', 'Inventory Manager', 'Full inventory control, stock management, transfers', '1', '0', NULL),
('11', 'HR Manager', 'Employee management, leave requests, payroll', '1', '0', NULL),
('12', 'Senior Cashier', 'All POS operations, basic manager duties, refunds', '1', '0', NULL),
('13', 'Inventory Clerk', 'Basic inventory tasks, stock counts, receiving', '1', '0', NULL),
('14', 'Customer Service', 'Customer management, returns, support tickets', '1', '0', NULL),
('15', 'Kitchen Staff', 'Kitchen display, order tickets, food preparation', '1', '0', NULL),
('16', 'Delivery Rider', 'Delivery management, tracking, order pickup', '1', '0', NULL),
('17', 'Viewer', 'Read-only access - view reports and data', '1', '0', NULL);

SET FOREIGN_KEY_CHECKS=1;
