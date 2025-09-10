-- Drop Constraint Migration - idx_test_section_filter_active_unique
-- This script removes the constraint that prevents Engineering reject functionality
-- from updating Active records to Inactive status when duplicate Inactive records exist

-- Step 1: Check if the constraint exists before attempting to drop it
SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS 
     WHERE table_schema = DATABASE() 
     AND table_name = 'test_specific_data' 
     AND index_name = 'idx_test_section_filter_active_unique') > 0,
    'DROP INDEX idx_test_section_filter_active_unique ON test_specific_data',
    'SELECT "Constraint idx_test_section_filter_active_unique does not exist" as message'));

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Step 2: Log this schema change for audit purposes
INSERT INTO log (change_type, table_name, change_description, change_by, unit_id)
VALUES (
    'schema_fix', 
    'test_specific_data', 
    'Dropped constraint idx_test_section_filter_active_unique to allow Engineering reject functionality to update Active records to Inactive status while preserving all data versions', 
    1, 
    0
);

-- Step 3: Verification - Check that the constraint has been removed
SELECT 
    'Constraint removal completed' as status,
    'Engineering reject can now update Active to Inactive without constraint violations' as description,
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS 
     WHERE table_schema = DATABASE() 
     AND table_name = 'test_specific_data' 
     AND index_name = 'idx_test_section_filter_active_unique') as constraint_exists_count;

-- Step 4: Show current constraints on the table for reference
SELECT 
    INDEX_NAME as constraint_name,
    COLUMN_NAME as column_name,
    NON_UNIQUE as is_unique_constraint
FROM INFORMATION_SCHEMA.STATISTICS 
WHERE table_schema = DATABASE() 
  AND table_name = 'test_specific_data'
  AND INDEX_NAME != 'PRIMARY'
ORDER BY INDEX_NAME, SEQ_IN_INDEX;

-- Step 5: Verify data integrity after constraint removal
-- Show current record counts by status
SELECT 
    test_val_wf_id,
    section_type,
    filter_id,
    status,
    COUNT(*) as record_count
FROM test_specific_data
WHERE test_val_wf_id LIKE 'T-%'
GROUP BY test_val_wf_id, section_type, filter_id, status
ORDER BY test_val_wf_id, section_type, filter_id, status;