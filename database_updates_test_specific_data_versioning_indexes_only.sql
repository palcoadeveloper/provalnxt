-- Database updates for test_specific_data table versioning system (indexes and views only)
-- The columns already exist, so we just need to add indexes and views

-- Check if indexes exist before creating them
-- Add indexes for better performance on versioning queries (if they don't exist)
CREATE INDEX IF NOT EXISTS idx_test_specific_data_status ON test_specific_data (status);
CREATE INDEX IF NOT EXISTS idx_test_specific_data_filter_id ON test_specific_data (filter_id);
CREATE INDEX IF NOT EXISTS idx_test_specific_data_creation_datetime ON test_specific_data (creation_datetime);
CREATE INDEX IF NOT EXISTS idx_test_specific_data_modification_datetime ON test_specific_data (last_modification_datetime);

-- Add compound index for efficient versioning queries
CREATE INDEX IF NOT EXISTS idx_test_specific_data_versioning 
ON test_specific_data (test_val_wf_id, filter_id, status, creation_datetime DESC);

-- Create unique constraint for active records (only one active record per test_val_wf_id + filter_id)
-- First check if the index exists
SELECT COUNT(*) as index_exists FROM information_schema.statistics 
WHERE table_schema = DATABASE() 
AND table_name = 'test_specific_data' 
AND index_name = 'idx_test_specific_data_active_unique';

-- Only create if it doesn't exist (we'll handle this in a separate statement)

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