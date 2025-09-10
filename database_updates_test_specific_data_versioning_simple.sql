-- Database updates for test_specific_data table versioning system (simple version)
-- The columns already exist, so we just need to add indexes and views

-- Add indexes for better performance on versioning queries
-- These will fail if they already exist, but that's okay
CREATE INDEX idx_test_specific_data_status ON test_specific_data (status);
CREATE INDEX idx_test_specific_data_creation_datetime ON test_specific_data (creation_datetime);
CREATE INDEX idx_test_specific_data_modification_datetime ON test_specific_data (last_modification_datetime);

-- Add compound index for efficient versioning queries
CREATE INDEX idx_test_specific_data_versioning 
ON test_specific_data (test_val_wf_id, filter_id, status, creation_datetime DESC);

-- Create a view to easily get the latest active version of each filter's data
CREATE OR REPLACE VIEW vw_test_specific_data_active AS
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
CREATE OR REPLACE VIEW vw_test_specific_data_history AS
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
    ROW_NUMBER() OVER (PARTITION BY test_val_wf_id, COALESCE(filter_id, 0) ORDER BY creation_datetime DESC) as version_number
FROM test_specific_data 
ORDER BY test_val_wf_id, filter_id, creation_datetime DESC;

-- Update any existing log entries to reflect the versioning system
INSERT INTO log (change_type, table_name, change_description, change_by, unit_id)
VALUES ('schema_update', 'test_specific_data', 'Added versioning system indexes and views for test_specific_data table', 1, 0);