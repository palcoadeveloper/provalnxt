-- Database update script for test finalisation details table
-- Created: $(date '+%Y-%m-%d %H:%M:%S')

-- Create table for test finalisation tracking
CREATE TABLE IF NOT EXISTS `tbl_test_finalisation_details` (
    `test_id` INT AUTO_INCREMENT PRIMARY KEY,
    `test_wf_id` VARCHAR(45) NOT NULL,
    `test_finalised_on` DATETIME DEFAULT NOW(),
    `test_finalised_by` INT,
    `test_witnessed_on` DATETIME DEFAULT NOW(),
    `witness` INT,
    `witness_action` ENUM('approve', 'reject') DEFAULT NULL,
    `creation_datetime` DATETIME DEFAULT NOW(),
    
    -- Add indexes for better performance
    INDEX `idx_test_wf_id` (`test_wf_id`),
    INDEX `idx_test_finalised_by` (`test_finalised_by`),
    INDEX `idx_witness` (`witness`),
    INDEX `idx_creation_datetime` (`creation_datetime`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add foreign key constraints if user tables exist
-- Uncomment the following lines if you have a users table with user_id as primary key
-- ALTER TABLE `tbl_test_finalisation_details` 
-- ADD CONSTRAINT `fk_test_finalised_by` FOREIGN KEY (`test_finalised_by`) REFERENCES `users`(`user_id`) ON DELETE SET NULL ON UPDATE CASCADE;

-- ALTER TABLE `tbl_test_finalisation_details` 
-- ADD CONSTRAINT `fk_witness` FOREIGN KEY (`witness`) REFERENCES `users`(`user_id`) ON DELETE SET NULL ON UPDATE CASCADE;

-- Add comment to the table
ALTER TABLE `tbl_test_finalisation_details` COMMENT = 'Table to track test finalisation and witness approval/rejection details';