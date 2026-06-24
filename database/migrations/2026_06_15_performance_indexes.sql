-- Performance Indexes for JDH_POS
-- Date: 2026-06-15
-- Description: Critical indexes for frequently queried tables

SET FOREIGN_KEY_CHECKS = 0;
SET NAMES utf8mb4;

-- Sales table indexes (most critical for POS performance)
ALTER TABLE `sales` ADD INDEX IF NOT EXISTS `idx_sales_tenant_branch_date` (`tenant_id`, `branch_id`, `created_at`);
ALTER TABLE `sales` ADD INDEX IF NOT EXISTS `idx_sales_customer` (`customer_id`);
ALTER TABLE `sales` ADD INDEX IF NOT EXISTS `idx_sales_status` (`status`);
ALTER TABLE `sales` ADD INDEX IF NOT EXISTS `idx_sales_user` (`user_id`);
ALTER TABLE `sales` ADD INDEX IF NOT EXISTS `idx_sales_invoice` (`invoice_number`);

-- Sale items indexes
ALTER TABLE `sale_items` ADD INDEX IF NOT EXISTS `idx_sale_items_sale` (`sale_id`);
ALTER TABLE `sale_items` ADD INDEX IF NOT EXISTS `idx_sale_items_product` (`product_id`);
ALTER TABLE `sale_items` ADD INDEX IF NOT EXISTS `idx_sale_items_tenant` (`tenant_id`);

-- Inventory indexes
ALTER TABLE `inventory` ADD INDEX IF NOT EXISTS `idx_inventory_product_branch` (`product_id`, `branch_id`);
ALTER TABLE `inventory` ADD INDEX IF NOT EXISTS `idx_inventory_tenant_stock` (`tenant_id`, `stock`);
ALTER TABLE `inventory` ADD INDEX IF NOT EXISTS `idx_inventory_reorder` (`reorder_level`, `stock`);

-- Products indexes
ALTER TABLE `products` ADD INDEX IF NOT EXISTS `idx_products_tenant_active` (`tenant_id`, `is_active`);
ALTER TABLE `products` ADD INDEX IF NOT EXISTS `idx_products_sku` (`sku`);
ALTER TABLE `products` ADD INDEX IF NOT EXISTS `idx_products_barcode` (`barcode`);
ALTER TABLE `products` ADD INDEX IF NOT EXISTS `idx_products_category` (`category_id`);
ALTER TABLE `products` ADD INDEX IF NOT EXISTS `idx_products_name` (`name`);

-- Customers indexes
ALTER TABLE `customers` ADD INDEX IF NOT EXISTS `idx_customers_tenant` (`tenant_id`);
ALTER TABLE `customers` ADD INDEX IF NOT EXISTS `idx_customers_phone` (`phone`);
ALTER TABLE `customers` ADD INDEX IF NOT EXISTS `idx_customers_email` (`email`);

-- Users indexes
ALTER TABLE `users` ADD INDEX IF NOT EXISTS `idx_users_tenant_branch` (`tenant_id`, `branch_id`);
ALTER TABLE `users` ADD INDEX IF NOT EXISTS `idx_users_role` (`role_id`);
ALTER TABLE `users` ADD INDEX IF NOT EXISTS `idx_users_active` (`is_active`);

-- Purchase orders indexes
ALTER TABLE `purchase_orders` ADD INDEX IF NOT EXISTS `idx_po_tenant_branch` (`tenant_id`, `branch_id`);
ALTER TABLE `purchase_orders` ADD INDEX IF NOT EXISTS `idx_po_supplier` (`supplier_id`);
ALTER TABLE `purchase_orders` ADD INDEX IF NOT EXISTS `idx_po_status` (`status`);

-- Activity logs indexes
ALTER TABLE `activity_logs` ADD INDEX IF NOT EXISTS `idx_logs_tenant_user` (`tenant_id`, `user_id`);
ALTER TABLE `activity_logs` ADD INDEX IF NOT EXISTS `idx_logs_created` (`created_at`);
ALTER TABLE `activity_logs` ADD INDEX IF NOT EXISTS `idx_logs_action` (`action`);

-- Stock movements indexes
ALTER TABLE `stock_movements` ADD INDEX IF NOT EXISTS `idx_movements_product` (`product_id`);
ALTER TABLE `stock_movements` ADD INDEX IF NOT EXISTS `idx_movements_branch` (`branch_id`);
ALTER TABLE `stock_movements` ADD INDEX IF NOT EXISTS `idx_movements_created` (`created_at`);

-- Returns indexes
ALTER TABLE `returns` ADD INDEX IF NOT EXISTS `idx_returns_sale` (`sale_id`);
ALTER TABLE `returns` ADD INDEX IF NOT EXISTS `idx_returns_tenant_branch` (`tenant_id`, `branch_id`);
ALTER TABLE `returns` ADD INDEX IF NOT EXISTS `idx_returns_created` (`created_at`);

-- Notifications indexes
ALTER TABLE `notifications` ADD INDEX IF NOT EXISTS `idx_notifications_user` (`user_id`);
ALTER TABLE `notifications` ADD INDEX IF NOT EXISTS `idx_notifications_unread` (`user_id`, `is_read`);
ALTER TABLE `notifications` ADD INDEX IF NOT EXISTS `idx_notifications_created` (`created_at`);

-- Online orders indexes
ALTER TABLE `online_orders` ADD INDEX IF NOT EXISTS `idx_online_orders_customer` (`customer_id`);
ALTER TABLE `online_orders` ADD INDEX IF NOT EXISTS `idx_online_orders_status` (`status`);
ALTER TABLE `online_orders` ADD INDEX IF NOT EXISTS `idx_online_orders_created` (`created_at`);

-- Carts indexes
ALTER TABLE `carts` ADD INDEX IF NOT EXISTS `idx_carts_customer` (`customer_id`);
ALTER TABLE `carts` ADD INDEX IF NOT EXISTS `idx_carts_session` (`session_id`);

-- Coupons indexes
ALTER TABLE `coupons` ADD INDEX IF NOT EXISTS `idx_coupons_code` (`code`);
ALTER TABLE `coupons` ADD INDEX IF NOT EXISTS `idx_coupons_active` (`is_active`, `valid_until`);

-- Loyalty transactions indexes
ALTER TABLE `loyalty_transactions` ADD INDEX IF NOT EXISTS `idx_loyalty_customer` (`customer_id`);
ALTER TABLE `loyalty_transactions` ADD INDEX IF NOT EXISTS `idx_loyalty_created` (`created_at`);

-- Time clock indexes
ALTER TABLE `time_clock` ADD INDEX IF NOT EXISTS `idx_timeclock_user` (`user_id`);
ALTER TABLE `time_clock` ADD INDEX IF NOT EXISTS `idx_timeclock_date` (`clock_in`);

-- Settings indexes
ALTER TABLE `settings` ADD INDEX IF NOT EXISTS `idx_settings_key` (`setting_key`);
ALTER TABLE `settings` ADD INDEX IF NOT EXISTS `idx_settings_tenant` (`tenant_id`);

SET FOREIGN_KEY_CHECKS = 1;
