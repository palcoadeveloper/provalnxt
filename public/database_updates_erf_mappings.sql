-- ERF (Equipment Room Filter) Mapping Master Database Schema
-- This table manages the mapping between Equipment, Room, and Filter
-- Combination of Equipment + Room + Filter must be unique

-- Drop table if exists (for development/testing purposes)
-- DROP TABLE IF EXISTS erf_mappings;

-- Create the erf_mappings table
CREATE TABLE IF NOT EXISTS `erf_mappings` (
  `erf_mapping_id` INT AUTO_INCREMENT PRIMARY KEY COMMENT 'Unique identifier for ERF mapping',
  `equipment_id` INT NOT NULL COMMENT 'Foreign key to equipments table',
  `room_loc_id` INT NOT NULL COMMENT 'Foreign key to room_locations table',
  `area_classification`  VARCHAR(200) NOT NULL DEFAULT 'N/A' COMMENT 'Area classification of the equipment',
  `filter_name` VARCHAR(200) NULL DEFAULT NULL COMMENT 'Filter name/description (optional)',
  `erf_mapping_status` ENUM('Active', 'Inactive') NOT NULL DEFAULT 'Active' COMMENT 'Status of the mapping',
  `creation_datetime` DATETIME NOT NULL DEFAULT NOW() COMMENT 'Record creation timestamp',
  `last_modification_datetime` DATETIME NOT NULL DEFAULT NOW() ON UPDATE NOW() COMMENT 'Last modification timestamp',
  
  -- Indexes for better performance
  INDEX `idx_equipment_id` (`equipment_id`),
  INDEX `idx_room_loc_id` (`room_loc_id`),
  INDEX `idx_status` (`erf_mapping_status`),
  INDEX `idx_creation_date` (`creation_datetime`),
  
  -- Unique constraint for Equipment + Room + Filter combination (filter can be NULL)
  UNIQUE KEY `uk_equipment_room_filter` (`equipment_id`, `room_loc_id`, `filter_name`),
  
  -- Foreign key constraints (if tables exist)
  FOREIGN KEY (`equipment_id`) REFERENCES `equipments`(`equipment_id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  FOREIGN KEY (`room_loc_id`) REFERENCES `room_locations`(`room_loc_id`) ON DELETE RESTRICT ON UPDATE CASCADE
  
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='ERF Mapping Master - Equipment Room Filter relationships';

-- Insert some sample data for testing
INSERT INTO `erf_mappings` (`equipment_id`, `room_loc_id`, `filter_name`, `erf_mapping_status`,`area_classification`) VALUES
-- Assuming equipment_id 1-5 exist and room_loc_id 1-5 exist
(1, 1, 'AHU-01/THF/0.3mu/01/A', 'Active',"ISO 5/Grade 'B'"),
(1, 1, 'AHU-01/THF/0.3mu/02/A', 'Active',"ISO 5/Grade 'B'"),
-- Example with no filter (NULL)
(2, 1, NULL, 'Active',"ISO 7/Grade 'C'");

-- Verify the data
-- SELECT 
--     em.erf_mapping_id,
--     e.equipment_code,
--     rl.room_loc_name,
--     em.filter_name,
--     em.erf_mapping_status,
--     em.creation_datetime
-- FROM erf_mappings em
-- INNER JOIN equipments e ON em.equipment_id = e.equipment_id  
-- INNER JOIN room_locations rl ON em.room_loc_id = rl.room_loc_id
-- ORDER BY em.creation_datetime DESC;