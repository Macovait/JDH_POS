-- =====================================================
-- BLOCK-BASED CMS MIGRATION — Typed Blocks with JSON Props
-- =====================================================
-- Adds type + props columns to storefront_blocks for structured block rendering.
-- Old HTML content is preserved in the `content` column for backward compatibility.

ALTER TABLE storefront_blocks
    ADD COLUMN IF NOT EXISTS type VARCHAR(50) DEFAULT 'text-section' AFTER name,
    ADD COLUMN IF NOT EXISTS props JSON NULL AFTER type,
    ADD COLUMN IF NOT EXISTS section_class VARCHAR(100) DEFAULT 'py-8' AFTER padding,
    DROP INDEX IF EXISTS uk_tenant_order,
    ADD INDEX idx_tenant_type (tenant_id, type),
    ADD INDEX idx_tenant_active_order (tenant_id, is_active, display_order);

-- =====================================================
-- EXAMPLE: Migrate old HTML blocks to typed blocks
-- (Run manually after deployment if you want to convert existing blocks)
-- =====================================================
-- UPDATE storefront_blocks SET type = 'text-section' WHERE type IS NULL OR type = '';
