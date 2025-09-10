-- ProVal HVAC - Test Instruments Schema Corrections
-- This script fixes any schema inconsistencies found during security review

-- Ensure test_instruments table has correct data types and constraints
-- The instrument_id should be int(11) to match the instruments table primary key

-- Drop the table if it exists with wrong schema and recreate
-- Note: This will lose existing data, so backup first in production
DROP TABLE IF EXISTS `test_instruments`;

-- Recreate with correct schema
CREATE TABLE `test_instruments` (
  `mapping_id` int(11) NOT NULL AUTO_INCREMENT,
  `test_val_wf_id` varchar(50) NOT NULL,
  `instrument_id` int(11) NOT NULL,
  `added_by` int(11) NOT NULL,
  `added_date` timestamp DEFAULT CURRENT_TIMESTAMP,
  `is_active` tinyint(1) DEFAULT 1,
  `unit_id` int(11) NOT NULL,
  PRIMARY KEY (`mapping_id`),
  KEY `idx_test_val_wf_id` (`test_val_wf_id`),
  KEY `idx_instrument_id` (`instrument_id`),
  KEY `idx_added_by` (`added_by`),
  KEY `idx_is_active` (`is_active`),
  KEY `idx_unit_id` (`unit_id`),
  -- Add foreign key constraints for data integrity
  CONSTRAINT `fk_test_instruments_instrument` FOREIGN KEY (`instrument_id`) REFERENCES `instruments` (`instrument_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_test_instruments_user` FOREIGN KEY (`added_by`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_test_instruments_unit` FOREIGN KEY (`unit_id`) REFERENCES `units` (`unit_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Create optimized composite indexes for performance
CREATE INDEX `idx_test_active_date` ON `test_instruments` (`test_val_wf_id`, `is_active`, `added_date` DESC);
CREATE INDEX `idx_instrument_active` ON `test_instruments` (`instrument_id`, `is_active`);
CREATE UNIQUE INDEX `idx_test_instrument_unique` ON `test_instruments` (`test_val_wf_id`, `instrument_id`, `is_active`);

-- Note: This schema is now consistent with the instruments table structure
-- All APIs should work correctly with int(11) instrument_id values