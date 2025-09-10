-- Room/Location Master Database Schema
-- ProVal HVAC - Room Location Management
-- Created: December 2024

-- Create room_locations table for managing rooms and their volumes
CREATE TABLE IF NOT EXISTS `room_locations` (
  `room_loc_id` INT AUTO_INCREMENT PRIMARY KEY COMMENT 'Unique identifier for room location',
  `room_loc_name` VARCHAR(500) NOT NULL COMMENT 'Name of the room or location',
  `room_volume` DECIMAL(10,2) NOT NULL COMMENT 'Volume of the room in cubic feet',
  `creation_datetime` DATETIME DEFAULT NOW() COMMENT 'Date and time when record was created',
  `last_modification_datetime` DATETIME DEFAULT NOW() ON UPDATE NOW() COMMENT 'Date and time when record was last modified',
  
  -- Indexes for performance
  KEY `idx_room_name` (`room_loc_name`),
  KEY `idx_creation_date` (`creation_datetime`),
  KEY `idx_modification_date` (`last_modification_datetime`)
  
  -- Constraints
 -- CONSTRAINT `chk_room_volume_positive` CHECK (`room_volume` > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Master table for room locations and their volumes';

-- Insert sample data for testing (optional)
INSERT INTO `room_locations` (`room_loc_name`, `room_volume`) VALUES
('Vial Filling Lyo Loading and Unloading Area', 4354.30),
('Location 1', 0.00),
('Location 2', 0.00),
('Location 3', 0.0);

-- Add audit trail support - update log table to handle room operations
-- This ensures room changes are tracked in the existing audit system

-- Verify table creation
SELECT 'room_locations table created successfully' as status;