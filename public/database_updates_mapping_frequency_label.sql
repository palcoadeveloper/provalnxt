-- Database updates for Mapping Frequency Label
-- Execute this script to add frequency_label column to equipment_test_vendor_mapping table

-- Add frequency_label column to equipment_test_vendor_mapping table if not exists
SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
     WHERE TABLE_SCHEMA = DATABASE() 
     AND TABLE_NAME = 'equipment_test_vendor_mapping' 
     AND COLUMN_NAME = 'frequency_label') = 0,
    'ALTER TABLE equipment_test_vendor_mapping ADD COLUMN frequency_label varchar(3) NOT NULL DEFAULT "ALL" AFTER test_type',
    'SELECT "Column frequency_label already exists"'
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add index for better performance on frequency_label queries
SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS 
     WHERE TABLE_SCHEMA = DATABASE() 
     AND TABLE_NAME = 'equipment_test_vendor_mapping' 
     AND INDEX_NAME = 'idx_frequency_label') = 0,
    'CREATE INDEX idx_frequency_label ON equipment_test_vendor_mapping (frequency_label)',
    'SELECT "Index idx_frequency_label already exists"'
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;