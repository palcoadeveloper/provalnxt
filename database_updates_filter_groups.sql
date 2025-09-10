-- Filter Groups Master Database Schema
-- This table manages filter group categories for ERF mappings
-- Filter groups provide categorization for filters used in Equipment Room Filter mappings

-- Drop table if exists (for development/testing purposes)  
-- DROP TABLE IF EXISTS filter_groups;

-- Create the filter_groups table
CREATE TABLE IF NOT EXISTS `filter_groups` (
  `filter_group_id` INT AUTO_INCREMENT PRIMARY KEY COMMENT 'Unique identifier for filter group',
  `filter_group_name` VARCHAR(200) NOT NULL COMMENT 'Name/description of the filter group',
  `status` ENUM('Active', 'Inactive') NOT NULL DEFAULT 'Active' COMMENT 'Status of the filter group',
  `creation_datetime` DATETIME NOT NULL DEFAULT NOW() COMMENT 'Record creation timestamp',
  `last_modification_datetime` DATETIME NOT NULL DEFAULT NOW() ON UPDATE NOW() COMMENT 'Last modification timestamp',
  
  -- Indexes for better performance
  INDEX `idx_status` (`status`),
  INDEX `idx_creation_date` (`creation_datetime`),
  
  -- Unique constraint to prevent duplicate filter group names
  UNIQUE KEY `uk_filter_group_name` (`filter_group_name`)
  
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Filter Groups Master - Categories for filter classification';

-- Insert sample filter group data for testing
INSERT INTO `filter_groups` (`filter_group_name`, `status`) VALUES
('HEPA Filters', 'Active'),
('Pre-Filters', 'Active'), 
('Terminal Filters', 'Active'),
('Carbon Filters', 'Active'),
('Final Filters', 'Active'),
('Intermediate Filters', 'Active'),
('Bag Filters', 'Active'),
('Panel Filters', 'Inactive');

-- Add filter_group_id column to existing erf_mappings table
-- This creates the relationship between ERF mappings and filter groups
ALTER TABLE `erf_mappings` 
ADD COLUMN `filter_group_id` INT NULL DEFAULT NULL COMMENT 'Foreign key to filter_groups table (optional)' AFTER `filter_name`;

-- Add index for the new column
ALTER TABLE `erf_mappings` 
ADD INDEX `idx_filter_group_id` (`filter_group_id`);

-- Add foreign key constraint
ALTER TABLE `erf_mappings` 
ADD CONSTRAINT `fk_erf_mappings_filter_group` 
    FOREIGN KEY (`filter_group_id`) REFERENCES `filter_groups`(`filter_group_id`) 
    ON DELETE SET NULL ON UPDATE CASCADE;

-- Verify the schema changes
-- SELECT COLUMN_NAME, DATA_TYPE, IS_NULLABLE, COLUMN_DEFAULT 
-- FROM INFORMATION_SCHEMA.COLUMNS 
-- WHERE TABLE_NAME = 'erf_mappings' AND TABLE_SCHEMA = DATABASE()
-- ORDER BY ORDINAL_POSITION;

-- Sample query to test the relationship
-- SELECT 
--     em.erf_mapping_id,
--     e.equipment_code,
--     rl.room_loc_name,
--     em.filter_name,
--     fg.filter_group_name,
--     em.area_classification,
--     em.erf_mapping_status,
--     em.creation_datetime
-- FROM erf_mappings em
-- INNER JOIN equipments e ON em.equipment_id = e.equipment_id  
-- INNER JOIN room_locations rl ON em.room_loc_id = rl.room_loc_id
-- LEFT JOIN filter_groups fg ON em.filter_group_id = fg.filter_group_id
-- ORDER BY em.creation_datetime DESC;

-- Update some existing ERF mappings with sample filter group associations
-- (This is optional and only for testing - adjust IDs as needed)
-- UPDATE erf_mappings SET filter_group_id = 1 WHERE filter_name LIKE '%HEPA%' OR filter_name LIKE '%THF%';
-- UPDATE erf_mappings SET filter_group_id = 2 WHERE filter_name LIKE '%Pre%' OR filter_name LIKE '%PRE%';