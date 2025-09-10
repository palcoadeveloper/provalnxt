-- Database Update: Add filter_id column to test_specific_data table
-- Date: 2025-09-08
-- Description: Add filter_id as INT column to establish relationship with filters table

-- Add filter_id column to test_specific_data table
ALTER TABLE test_specific_data 
ADD COLUMN filter_id INT NULL;

-- Add index for better performance on filter_id lookups
CREATE INDEX idx_test_specific_data_filter_id ON test_specific_data(filter_id);

-- Add foreign key constraint to reference filters table (optional - uncomment if needed)
-- ALTER TABLE test_specific_data 
-- ADD CONSTRAINT fk_test_specific_data_filter_id 
-- FOREIGN KEY (filter_id) REFERENCES filters(filter_id) 
-- ON UPDATE CASCADE ON DELETE SET NULL;

-- Add comment to document the column purpose
ALTER TABLE test_specific_data MODIFY COLUMN filter_id INT NULL COMMENT 'References filter_id from filters table';