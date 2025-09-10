-- ProVal HVAC - Test Instruments Mapping Table
-- This script creates the test_instruments table for mapping instruments to tests

-- Create test_instruments table for mapping instruments to tests
CREATE TABLE IF NOT EXISTS `test_instruments` (
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
  KEY `idx_unit_id` (`unit_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Create composite unique index to prevent duplicate instrument mappings for the same test
CREATE UNIQUE INDEX `idx_test_instrument_unique` ON `test_instruments` (`test_val_wf_id`, `instrument_id`, `is_active`);

-- Insert sample data for testing (optional)
-- INSERT INTO `test_instruments` (`test_val_wf_id`, `instrument_id`, `added_by`, `unit_id`) VALUES
-- ('TEST001', 1, 1, 1),
-- ('TEST001', 2, 1, 1);