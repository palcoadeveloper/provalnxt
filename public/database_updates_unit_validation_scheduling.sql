-- Database update for unit validation scheduling logic
-- Add new column to units table

ALTER TABLE units 
ADD COLUMN validation_scheduling_logic ENUM('dynamic', 'fixed') NOT NULL DEFAULT 'dynamic' 
COMMENT 'Validation scheduling logic: dynamic adjusts automatically, fixed remains constant';

-- Update existing records to set default value
UPDATE units 
SET validation_scheduling_logic = 'dynamic' 
WHERE validation_scheduling_logic IS NULL;

-- Add index for better performance
CREATE INDEX idx_units_validation_scheduling_logic ON units(validation_scheduling_logic);

-- Add comment to describe the new field
ALTER TABLE units 
MODIFY COLUMN validation_scheduling_logic ENUM('dynamic', 'fixed') NOT NULL DEFAULT 'dynamic' 
COMMENT 'Validation scheduling logic: dynamic dates adjust automatically based on last validation, fixed dates remain constant from initial setup';

COMMIT;