-- Fix activity_logs tenant_id to allow NULL so INSERTs that omit tenant_id don't fail FK constraint
-- This fixes Integrity constraint violation 1452 on fk_activity_logs_tenant

ALTER TABLE `activity_logs`
    MODIFY COLUMN `tenant_id` bigint(20) unsigned DEFAULT NULL;

-- If the above still fails due to existing bad data, also temporarily disable FK check:
-- SET FOREIGN_KEY_CHECKS=0;
-- UPDATE activity_logs SET tenant_id = NULL WHERE tenant_id = 0;
-- SET FOREIGN_KEY_CHECKS=1;
