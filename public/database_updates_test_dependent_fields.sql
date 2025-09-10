-- Database updates for Test Dependent Fields
-- Execute this script to add dependent_tests and paper_on_glass_enabled columns

-- Add dependent_tests column to tests table if not exists
SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
     WHERE TABLE_SCHEMA = DATABASE() 
     AND TABLE_NAME = 'tests' 
     AND COLUMN_NAME = 'dependent_tests') = 0,
    'ALTER TABLE tests ADD COLUMN dependent_tests TEXT DEFAULT NULL AFTER test_status',
    'SELECT "Column dependent_tests already exists"'
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add paper_on_glass_enabled column to tests table if not exists
SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
     WHERE TABLE_SCHEMA = DATABASE() 
     AND TABLE_NAME = 'tests' 
     AND COLUMN_NAME = 'paper_on_glass_enabled') = 0,
    'ALTER TABLE tests ADD COLUMN paper_on_glass_enabled ENUM("Yes","No") DEFAULT "No" AFTER dependent_tests',
    'SELECT "Column paper_on_glass_enabled already exists"'
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add index for better performance on dependent_tests queries
SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS 
     WHERE TABLE_SCHEMA = DATABASE() 
     AND TABLE_NAME = 'tests' 
     AND INDEX_NAME = 'idx_dependent_tests') = 0,
    'CREATE INDEX idx_dependent_tests ON tests (dependent_tests(100))',
    'SELECT "Index idx_dependent_tests already exists"'
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add index for paper_on_glass_enabled
SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS 
     WHERE TABLE_SCHEMA = DATABASE() 
     AND TABLE_NAME = 'tests' 
     AND INDEX_NAME = 'idx_paper_on_glass') = 0,
    'CREATE INDEX idx_paper_on_glass ON tests (paper_on_glass_enabled)',
    'SELECT "Index idx_paper_on_glass already exists"'
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;