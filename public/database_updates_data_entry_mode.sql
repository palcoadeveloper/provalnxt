-- ProVal HVAC - Add Data Entry Mode Tracking
-- This script adds a column to track the selected data entry mode for each test

-- Add data_entry_mode column to tbl_test_schedules_tracking
ALTER TABLE `tbl_test_schedules_tracking` 
ADD COLUMN `data_entry_mode` ENUM('online', 'offline') NULL DEFAULT NULL 
COMMENT 'Track whether test data entry is done online or offline (paper-first)';

-- Add index for better query performance
ALTER TABLE `tbl_test_schedules_tracking` 
ADD INDEX `idx_data_entry_mode` (`data_entry_mode`);

-- Update: Change default value from 'offline' to NULL
-- This allows users to make their initial selection
ALTER TABLE `tbl_test_schedules_tracking` 
MODIFY COLUMN `data_entry_mode` ENUM('online', 'offline') NULL DEFAULT NULL;

-- Optional: Check the table structure after modification
-- DESCRIBE tbl_test_schedules_tracking;

-- Note: Default value is NULL - users must explicitly select their mode
-- Once offline mode is selected, it cannot be changed (enforced by application logic)