-- ProVal HVAC - Test-Specific Data Storage
-- This script creates the test_specific_data table for storing custom test data
-- when Paper on Glass is enabled

-- Create test_specific_data table for storing test-specific section data
CREATE TABLE IF NOT EXISTS `test_specific_data` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `test_val_wf_id` varchar(50) NOT NULL COMMENT 'Test workflow ID from tbl_test_schedules_tracking',
  `section_type` varchar(50) NOT NULL COMMENT 'Type of test section (airflow, temperature, pressure, etc.)',
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
  KEY `idx_entered_date` (`entered_date`),
  CONSTRAINT `fk_test_specific_data_entered_by` FOREIGN KEY (`entered_by`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_test_specific_data_modified_by` FOREIGN KEY (`modified_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL,
  CONSTRAINT `fk_test_specific_data_unit` FOREIGN KEY (`unit_id`) REFERENCES `units` (`unit_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Storage for test-specific data entry sections';

-- Create indexes for better performance
CREATE INDEX `idx_test_unit_section` ON `test_specific_data` (`test_val_wf_id`, `unit_id`, `section_type`);
CREATE INDEX `idx_section_modified` ON `test_specific_data` (`section_type`, `modified_date` DESC);

-- Insert sample data for testing (optional - remove in production)
-- INSERT INTO `test_specific_data` (`test_val_wf_id`, `section_type`, `data_json`, `entered_by`, `unit_id`) VALUES
-- ('TEST001', 'airflow', '{"room_pressure": "15.5", "air_velocity": "0.45", "flow_pattern": "laminar"}', 1, 1),
-- ('TEST001', 'temperature', '{"target_temperature": "22.0", "tolerance_range": "2.0", "temp_point_1": "21.8"}', 1, 1);

-- Create view for easy data retrieval with user information
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
    u2.user_id as modified_by_id,
    -- Extract some common JSON fields for easier querying
    JSON_UNQUOTE(JSON_EXTRACT(tsd.data_json, '$.target_temperature')) as target_temperature,
    JSON_UNQUOTE(JSON_EXTRACT(tsd.data_json, '$.room_pressure')) as room_pressure,
    JSON_UNQUOTE(JSON_EXTRACT(tsd.data_json, '$.air_velocity')) as air_velocity
FROM test_specific_data tsd
LEFT JOIN users u1 ON tsd.entered_by = u1.user_id
LEFT JOIN users u2 ON tsd.modified_by = u2.user_id;

-- Create function to validate section types
DELIMITER $$
CREATE OR REPLACE FUNCTION validate_section_type(section_type VARCHAR(50)) 
RETURNS BOOLEAN
READS SQL DATA
DETERMINISTIC
BEGIN
    RETURN section_type IN ('airflow', 'temperature', 'pressure', 'humidity', 'particlecount', 'lighting', 'sound', 'vibration');
END$$
DELIMITER ;

-- Add check constraint for section types (MySQL 8.0+)
-- ALTER TABLE test_specific_data 
-- ADD CONSTRAINT chk_section_type 
-- CHECK (section_type IN ('airflow', 'temperature', 'pressure', 'humidity', 'particlecount', 'lighting', 'sound', 'vibration'));

-- Create trigger to validate JSON structure (optional)
DELIMITER $$
CREATE OR REPLACE TRIGGER validate_test_specific_json
BEFORE INSERT ON test_specific_data
FOR EACH ROW
BEGIN
    -- Validate that data_json is valid JSON
    IF NOT JSON_VALID(NEW.data_json) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Invalid JSON format in data_json field';
    END IF;
    
    -- Set modified_by to entered_by for new records
    IF NEW.modified_by IS NULL THEN
        SET NEW.modified_by = NEW.entered_by;
    END IF;
END$$

CREATE OR REPLACE TRIGGER validate_test_specific_json_update
BEFORE UPDATE ON test_specific_data
FOR EACH ROW
BEGIN
    -- Validate that data_json is valid JSON
    IF NOT JSON_VALID(NEW.data_json) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Invalid JSON format in data_json field';
    END IF;
    
    -- Ensure modified_by is set for updates
    IF NEW.modified_by IS NULL THEN
        SET NEW.modified_by = OLD.entered_by;
    END IF;
END$$
DELIMITER ;

-- Create stored procedure for data cleanup (optional)
DELIMITER $$
CREATE OR REPLACE PROCEDURE CleanupTestSpecificData(IN days_old INT DEFAULT 365)
BEGIN
    DECLARE done INT DEFAULT FALSE;
    DECLARE cleanup_count INT DEFAULT 0;
    
    -- Delete test-specific data for test workflows that no longer exist
    DELETE tsd FROM test_specific_data tsd
    LEFT JOIN tbl_test_schedules_tracking tst ON tsd.test_val_wf_id = tst.test_wf_id
    WHERE tst.test_wf_id IS NULL;
    
    GET DIAGNOSTICS cleanup_count = ROW_COUNT;
    
    SELECT CONCAT('Cleaned up ', cleanup_count, ' orphaned test-specific data records') AS result;
END$$
DELIMITER ;

-- Grant permissions (adjust as needed for your environment)
-- GRANT SELECT, INSERT, UPDATE ON test_specific_data TO 'proval_user'@'%';
-- GRANT SELECT ON v_test_specific_data_with_users TO 'proval_user'@'%';

-- Add notes about usage
SELECT 'Test-specific data table created successfully' as status,
       'Use section_type to categorize different test types' as note1,
       'JSON storage allows flexible field structures per test' as note2,
       'Unique constraint prevents duplicate sections per test workflow' as note3;