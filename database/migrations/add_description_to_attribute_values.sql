-- Add description column to attribute_values for term descriptions
ALTER TABLE `attribute_values` ADD COLUMN IF NOT EXISTS `description` TEXT DEFAULT NULL AFTER `label`;
