-- Create units table if it doesn't exist
-- This should be run before the database_updates_2fa.sql script

CREATE TABLE IF NOT EXISTS units (
    unit_id INT PRIMARY KEY,
    unit_name VARCHAR(100) NOT NULL UNIQUE,
    unit_status ENUM('Active', 'Inactive') NOT NULL DEFAULT 'Active',
    primary_test_id INT NULL,
    secondary_test_id INT NULL,
    unit_creation_datetime TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    unit_last_modification_datetime TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_unit_name (unit_name),
    INDEX idx_unit_status (unit_status),
    INDEX idx_primary_test (primary_test_id),
    INDEX idx_secondary_test (secondary_test_id),
    FOREIGN KEY (primary_test_id) REFERENCES tests(test_id) ON DELETE SET NULL,
    FOREIGN KEY (secondary_test_id) REFERENCES tests(test_id) ON DELETE SET NULL
) ENGINE=InnoDB COMMENT='Units master table for ProVal HVAC system';

-- Insert sample data if table is empty
INSERT IGNORE INTO units (unit_id, unit_name, unit_status) VALUES
(1, 'Unit A', 'Active'),
(2, 'Unit B', 'Active'),
(3, 'Unit C', 'Inactive');