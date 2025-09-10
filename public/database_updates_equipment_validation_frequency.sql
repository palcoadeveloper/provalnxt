-- Database updates for equipment validation frequency fields
-- Add new columns to equipments table

ALTER TABLE equipments 
ADD COLUMN validation_frequencies VARCHAR(20) NOT NULL DEFAULT 'Y' COMMENT 'Comma-separated list of validation frequencies for combined type',
ADD COLUMN starting_frequency ENUM('6M','Y','2Y') NOT NULL DEFAULT 'Y' COMMENT 'Starting frequency for single type validation',
ADD COLUMN first_validation_date DATE DEFAULT NULL COMMENT 'Date of first validation for the equipment';

-- Update existing records to set proper default values
UPDATE equipments 
SET starting_frequency = validation_frequency 
WHERE validation_frequency IN ('6M', 'Y', '2Y');

-- For records with frequencies not in the new enum, set to 'Y' (Yearly)
UPDATE equipments 
SET starting_frequency = 'Y' 
WHERE validation_frequency NOT IN ('6M', 'Y', '2Y') OR validation_frequency IS NULL;

-- Clear validation_frequencies for existing single-frequency records
UPDATE equipments 
SET validation_frequencies = '' 
WHERE validation_frequencies = 'Y';

-- Add indexes for better performance
CREATE INDEX idx_equipments_first_validation_date ON equipments(first_validation_date);
CREATE INDEX idx_equipments_starting_frequency ON equipments(starting_frequency);
CREATE INDEX idx_equipments_validation_frequencies ON equipments(validation_frequencies);

-- Add comments to existing columns for clarity
ALTER TABLE equipments 
MODIFY COLUMN validation_frequency VARCHAR(10) COMMENT 'Legacy validation frequency field (kept for backwards compatibility)';

COMMIT;