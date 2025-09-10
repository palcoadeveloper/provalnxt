-- =====================================================
-- Filter Master Table Creation
-- This script creates the filters master table for 
-- centralized filter management in ProVal HVAC system
-- =====================================================

-- Create filters master table
CREATE TABLE IF NOT EXISTS filters (
    filter_id INT PRIMARY KEY AUTO_INCREMENT,
    filter_code VARCHAR(100) NOT NULL,
    filter_name VARCHAR(255),
    filter_size ENUM('Standard', 'Large', 'Small', 'Custom') DEFAULT 'Standard',
    filter_type ENUM('HEPA','ULPA','Pre-Filter','Carbon','Vent','Membrane','Other') NOT NULL,
    manufacturer VARCHAR(100),
    specifications TEXT,
    installation_date DATE NOT NULL,
    planned_due_date DATE,
    actual_replacement_date DATE,
    status ENUM('Active', 'Inactive') DEFAULT 'Active',
    creation_datetime TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_modification_datetime TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_by INT,
    UNIQUE KEY unique_filter_code (filter_code),
    KEY idx_filter_type (filter_type),
    KEY idx_status (status),
    KEY idx_installation_date (installation_date),
    KEY idx_planned_due_date (planned_due_date)
);

-- Add foreign key constraint for created_by if users table exists
-- ALTER TABLE filters ADD CONSTRAINT fk_filters_created_by 
-- FOREIGN KEY (created_by) REFERENCES users(user_id) ON DELETE SET NULL;

-- Insert some sample data for testing (optional)
INSERT IGNORE INTO filters (filter_code, filter_name, filter_size, filter_type, manufacturer, specifications, installation_date, planned_due_date, created_by) VALUES
('HEPA-AHU01-001', 'AHU-01 HEPA Filter Primary', 'Standard', 'HEPA', 'Camfil', '99.97% efficiency at 0.3 microns', '2024-01-15', '2025-01-15', 1),
('HEPA-AHU01-002', 'AHU-01 HEPA Filter Secondary', 'Standard', 'HEPA', 'Camfil', '99.97% efficiency at 0.3 microns', '2024-01-15', '2025-01-15', 1),
('PRE-AHU01-001', 'AHU-01 Pre-Filter', 'Large', 'Pre-Filter', 'Donaldson', 'G4 Grade Pre-Filter', '2024-01-15', '2024-07-15', 1);

-- =====================================================
-- Add filter_id column to existing tables
-- =====================================================

-- Add filter_id to erf_mappings table
ALTER TABLE erf_mappings ADD COLUMN filter_id INT NULL AFTER filter_name;
ALTER TABLE erf_mappings ADD KEY idx_filter_id (filter_id);
-- ALTER TABLE erf_mappings ADD CONSTRAINT fk_erf_mappings_filter_id 
-- FOREIGN KEY (filter_id) REFERENCES filters(filter_id) ON DELETE SET NULL;

-- Add filter_id to test_specific_data table  
ALTER TABLE test_specific_data ADD COLUMN filter_id INT NULL AFTER section_type;
ALTER TABLE test_specific_data ADD KEY idx_filter_id (filter_id);
-- ALTER TABLE test_specific_data ADD CONSTRAINT fk_test_specific_data_filter_id 
-- FOREIGN KEY (filter_id) REFERENCES filters(filter_id) ON DELETE SET NULL;

-- =====================================================
-- Migration Notes:
-- =====================================================
-- 1. Uncomment foreign key constraints after ensuring referential integrity
-- 2. Populate filter_id in erf_mappings based on existing filter_name data
-- 3. Update application code to use new filter master relationships
-- 4. Test thoroughly before removing old filter_name columns