-- Database Update: Add filter_id column to erf_mappings table
-- Date: 2025-01-07
-- Description: Add filter_id as INT column to establish relationship with filters table

-- Add filter_id column to erf_mappings table
ALTER TABLE erf_mappings 
ADD COLUMN filter_id INT NULL;

-- Add index for better performance on filter_id lookups
CREATE INDEX idx_erf_mappings_filter_id ON erf_mappings(filter_id);

-- Add foreign key constraint to reference filters table (optional - uncomment if needed)
-- ALTER TABLE erf_mappings 
-- ADD CONSTRAINT fk_erf_mappings_filter_id 
-- FOREIGN KEY (filter_id) REFERENCES filters(filter_id) 
-- ON UPDATE CASCADE ON DELETE SET NULL;

-- Add comment to document the column purpose
ALTER TABLE erf_mappings MODIFY COLUMN filter_id INT NULL COMMENT 'References filter_id from filters table';