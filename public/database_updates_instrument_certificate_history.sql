-- Database updates for Instrument Certificate History
-- Execute this script to add certificate history tracking

-- Create instrument_certificate_history table
CREATE TABLE IF NOT EXISTS `instrument_certificate_history` (
  `history_id` int(11) NOT NULL AUTO_INCREMENT,
  `instrument_id` varchar(100) NOT NULL,
  `certificate_file_path` varchar(500) NOT NULL,
  `calibrated_on` date NOT NULL,
  `calibration_due_on` date NOT NULL,
  `uploaded_by` int(11) NOT NULL,
  `uploaded_date` timestamp DEFAULT CURRENT_TIMESTAMP,
  `is_active` tinyint(1) DEFAULT 1,
  `file_size` bigint(20) DEFAULT NULL,
  `original_filename` varchar(255) DEFAULT NULL,
  `notes` text,
  PRIMARY KEY (`history_id`),
  KEY `idx_instrument_id` (`instrument_id`),
  KEY `idx_uploaded_by` (`uploaded_by`),
  KEY `idx_uploaded_date` (`uploaded_date`),
  KEY `idx_is_active` (`is_active`),
  CONSTRAINT `fk_certificate_history_user` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add index for better performance on queries (only if it doesn't exist)
SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS 
     WHERE TABLE_SCHEMA = DATABASE() 
     AND TABLE_NAME = 'instrument_certificate_history' 
     AND INDEX_NAME = 'idx_instrument_active') = 0,
    'CREATE INDEX idx_instrument_active ON instrument_certificate_history (instrument_id, is_active, uploaded_date DESC)',
    'SELECT "Index idx_instrument_active already exists"'
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add master_certificate_path to instruments table if not exists
-- Note: Using a different approach since IF NOT EXISTS is not supported for ADD COLUMN in all MySQL versions
SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
     WHERE TABLE_SCHEMA = DATABASE() 
     AND TABLE_NAME = 'instruments' 
     AND COLUMN_NAME = 'master_certificate_path') = 0,
    'ALTER TABLE instruments ADD COLUMN master_certificate_path varchar(500) DEFAULT NULL AFTER instrument_status',
    'SELECT "Column master_certificate_path already exists"'
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
     WHERE TABLE_SCHEMA = DATABASE() 
     AND TABLE_NAME = 'instruments' 
     AND COLUMN_NAME = 'approval_status') = 0,
    'ALTER TABLE instruments ADD COLUMN approval_status enum("PENDING","APPROVED","REJECTED") DEFAULT "APPROVED" AFTER master_certificate_path',
    'SELECT "Column approval_status already exists"'
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
     WHERE TABLE_SCHEMA = DATABASE() 
     AND TABLE_NAME = 'instruments' 
     AND COLUMN_NAME = 'pending_approval_id') = 0,
    'ALTER TABLE instruments ADD COLUMN pending_approval_id int(11) DEFAULT NULL AFTER approval_status',
    'SELECT "Column pending_approval_id already exists"'
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
     WHERE TABLE_SCHEMA = DATABASE() 
     AND TABLE_NAME = 'instruments' 
     AND COLUMN_NAME = 'reviewed_by') = 0,
    'ALTER TABLE instruments ADD COLUMN reviewed_by int(11) DEFAULT NULL AFTER pending_approval_id',
    'SELECT "Column reviewed_by already exists"'
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
     WHERE TABLE_SCHEMA = DATABASE() 
     AND TABLE_NAME = 'instruments' 
     AND COLUMN_NAME = 'reviewed_date') = 0,
    'ALTER TABLE instruments ADD COLUMN reviewed_date timestamp NULL DEFAULT NULL AFTER reviewed_by',
    'SELECT "Column reviewed_date already exists"'
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;