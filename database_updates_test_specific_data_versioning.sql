-- Database updates for test_specific_data table versioning system
-- This script adds versioning capabilities to track filter data changes over time

-- Add versioning columns to test_specific_data table
ALTER TABLE test_specific_data 
ADD COLUMN status ENUM('Active', 'Inactive') NOT NULL DEFAULT 'Active',
ADD COLUMN creation_datetime DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
ADD COLUMN last_modification_datetime DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;

-- Update existing records to have Active status
UPDATE test_specific_data 
SET status = 'Active', 
    creation_datetime = COALESCE(entered_date, NOW()),
    last_modification_datetime = COALESCE(modified_date, entered_date, NOW())
WHERE status IS NULL;

-- Create unique key constraint over test_val_wf_id and filter_id for active records
-- Note: Since we want versioning, we can't have a simple unique constraint
-- MySQL doesn't support partial indexes like PostgreSQL, so we'll use a different approach
-- We'll create a unique index that includes status to ensure only one active record per filter
CREATE UNIQUE INDEX idx_test_specific_data_active_unique 
ON test_specific_data (test_val_wf_id, filter_id, status);

-- Add indexes for better performance on versioning queries
CREATE INDEX idx_test_specific_data_status ON test_specific_data (status);
CREATE INDEX idx_test_specific_data_filter_id ON test_specific_data (filter_id);
CREATE INDEX idx_test_specific_data_creation_datetime ON test_specific_data (creation_datetime);
CREATE INDEX idx_test_specific_data_modification_datetime ON test_specific_data (last_modification_datetime);

-- Add compound index for efficient versioning queries
CREATE INDEX idx_test_specific_data_versioning 
ON test_specific_data (test_val_wf_id, filter_id, status, creation_datetime DESC);

-- Create a view to easily get the latest active version of each filter's data
CREATE VIEW vw_test_specific_data_active AS
SELECT 
    id,
    test_val_wf_id,
    section_type,
    filter_id,
    data_json,
    entered_by,
    entered_date,
    modified_by,
    modified_date,
    unit_id,
    status,
    creation_datetime,
    last_modification_datetime
FROM test_specific_data 
WHERE status = 'Active';

-- Create a view to get version history for audit purposes
CREATE VIEW vw_test_specific_data_history AS
SELECT 
    id,
    test_val_wf_id,
    section_type,
    filter_id,
    data_json,
    entered_by,
    entered_date,
    modified_by,
    modified_date,
    unit_id,
    status,
    creation_datetime,
    last_modification_datetime,
    ROW_NUMBER() OVER (PARTITION BY test_val_wf_id, filter_id ORDER BY creation_datetime DESC) as version_number
FROM test_specific_data 
WHERE filter_id IS NOT NULL
ORDER BY test_val_wf_id, filter_id, creation_datetime DESC;

-- Update any existing log entries to reflect the versioning system
INSERT INTO log (change_type, table_name, change_description, change_by, unit_id)
VALUES ('schema_update', 'test_specific_data', 'Added versioning system with status, creation_datetime, and last_modification_datetime columns', 1, 0);