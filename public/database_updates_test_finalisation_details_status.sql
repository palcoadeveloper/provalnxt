-- Database update script to add status column to test finalisation details table
-- Created: 2025-09-08

-- Add status column to existing table
ALTER TABLE `tbl_test_finalisation_details` 
ADD COLUMN `status` ENUM('Active', 'Inactive') DEFAULT 'Active' 
AFTER `witness_action`;

-- Add index for better performance on status queries
ALTER TABLE `tbl_test_finalisation_details` 
ADD INDEX `idx_status` (`status`);

-- Update table comment to reflect the new column
ALTER TABLE `tbl_test_finalisation_details` 
COMMENT = 'Table to track test finalisation and witness approval/rejection details with status tracking';