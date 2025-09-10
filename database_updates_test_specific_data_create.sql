-- Create test_specific_data table for ACPH and other test-specific data storage
-- Run this script to create the required table structure

USE provalnxt_demo;

-- Create the test_specific_data table
CREATE TABLE IF NOT EXISTS `test_specific_data` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `test_val_wf_id` varchar(50) NOT NULL COMMENT 'Test workflow ID from tbl_test_schedules_tracking',
  `section_type` varchar(50) NOT NULL COMMENT 'Type of test section (acph, airflow, temperature, etc.)',
  `data_json` JSON NOT NULL COMMENT 'JSON storage for test-specific field data',
  `entered_by` int(11) NOT NULL COMMENT 'User who first entered the data',
  `entered_date` timestamp DEFAULT CURRENT_TIMESTAMP COMMENT 'When data was first entered',
  `modified_by` int(11) DEFAULT NULL COMMENT 'User who last modified the data',
  `modified_date` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'When data was last modified',
  `unit_id` int(11) NOT NULL COMMENT 'Unit ID for data segregation',
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_test_section_unique` (`test_val_wf_id`, `section_type`),
  KEY `idx_test_val_wf_id` (`test_val_wf_id`),
  KEY `idx_section_type` (`section_type`),
  KEY `idx_entered_by` (`entered_by`),
  KEY `idx_modified_by` (`modified_by`),
  KEY `idx_unit_id` (`unit_id`),
  CONSTRAINT `fk_test_specific_data_entered_by` FOREIGN KEY (`entered_by`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_test_specific_data_modified_by` FOREIGN KEY (`modified_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL,
  CONSTRAINT `fk_test_specific_data_unit` FOREIGN KEY (`unit_id`) REFERENCES `units` (`unit_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Storage for test-specific data sections in JSON format';

-- Create a view for easy data retrieval with user information
CREATE OR REPLACE VIEW `v_test_specific_data_with_users` AS
SELECT 
    tsd.id,
    tsd.test_val_wf_id,
    tsd.section_type,
    tsd.data_json,
    tsd.entered_date,
    tsd.modified_date,
    tsd.unit_id,
    u1.user_name as entered_by_name,
    u1.user_id as entered_by_id,
    u2.user_name as modified_by_name,
    u2.user_id as modified_by_id
FROM test_specific_data tsd
LEFT JOIN users u1 ON tsd.entered_by = u1.user_id
LEFT JOIN users u2 ON tsd.modified_by = u2.user_id;

-- Insert a sample log entry to track this schema creation
INSERT INTO log (
    change_type, 
    table_name, 
    change_description, 
    change_by, 
    unit_id
) VALUES (
    'schema_update', 
    'test_specific_data', 
    'Created test_specific_data table and view for ACPH test-specific data storage', 
    1, 
    1
);

SELECT 'test_specific_data table and view created successfully!' as Status;