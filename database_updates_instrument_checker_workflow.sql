-- Database Schema Updates for Instrument Checker Approval Workflow
-- ProVal HVAC System - Vendor Instrument Management Enhancement
-- Created: September 30, 2025

-- Step 1: Add 'Pending' status to instrument_status ENUM
ALTER TABLE instruments
MODIFY COLUMN instrument_status ENUM('Active', 'Inactive', 'Pending') NOT NULL DEFAULT 'Pending';

-- Step 2: Add checker approval tracking columns
ALTER TABLE instruments
ADD COLUMN submitted_by INT NULL COMMENT 'User ID who submitted/modified the record',
ADD COLUMN checker_id INT NULL COMMENT 'User ID who performed checker approval/rejection',
ADD COLUMN checker_action ENUM('Approved', 'Rejected') NULL COMMENT 'Checker decision',
ADD COLUMN checker_date DATETIME NULL COMMENT 'Date and time of checker action',
ADD COLUMN checker_remarks TEXT NULL COMMENT 'Checker comments/remarks',
ADD COLUMN original_data JSON NULL COMMENT 'Original data before modification for audit trail';

-- Step 3: Add foreign key constraints for data integrity
ALTER TABLE instruments
ADD CONSTRAINT fk_instruments_submitted_by
    FOREIGN KEY (submitted_by) REFERENCES users(user_id) ON DELETE SET NULL,
ADD CONSTRAINT fk_instruments_checker_id
    FOREIGN KEY (checker_id) REFERENCES users(user_id) ON DELETE SET NULL;

-- Step 4: Create index for performance optimization on checker workflow queries
CREATE INDEX idx_instruments_workflow
ON instruments(instrument_status, submitted_by, vendor_id);

CREATE INDEX idx_instruments_checker
ON instruments(checker_id, checker_date);

-- Step 5: Update existing Active records to have proper submitted_by tracking
-- This sets submitted_by to the first admin user found, or NULL if none exist
UPDATE instruments
SET submitted_by = (
    SELECT u.user_id
    FROM users u
    WHERE (u.is_admin = 'Yes' OR u.is_super_admin = 'Yes')
    AND u.user_status = 'Active'
    LIMIT 1
)
WHERE instrument_status = 'Active' AND submitted_by IS NULL;

-- Step 6: Create audit log table for instrument workflow tracking
CREATE TABLE IF NOT EXISTS instrument_workflow_log (
    log_id INT AUTO_INCREMENT PRIMARY KEY,
    instrument_id VARCHAR(255) NOT NULL,
    action_type ENUM('Created', 'Modified', 'Approved', 'Rejected', 'Resubmitted') NOT NULL,
    performed_by INT NOT NULL,
    action_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    old_data JSON NULL,
    new_data JSON NULL,
    remarks TEXT NULL,
    ip_address VARCHAR(45) NULL,
    user_agent TEXT NULL,
    INDEX idx_instrument_workflow_log_instrument (instrument_id),
    INDEX idx_instrument_workflow_log_date (action_date),
    INDEX idx_instrument_workflow_log_user (performed_by),
    FOREIGN KEY (performed_by) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Audit log for instrument workflow actions';

-- Step 7: Insert initial workflow log entries for existing instruments
INSERT INTO instrument_workflow_log (instrument_id, action_type, performed_by, action_date, remarks)
SELECT
    i.instrument_id,
    'Created' as action_type,
    COALESCE(i.submitted_by, 1) as performed_by,
    COALESCE(i.created_date, NOW()) as action_date,
    'Historical record - migrated during workflow implementation' as remarks
FROM instruments i
WHERE i.instrument_status = 'Active';

-- Verification queries (to be run after applying changes)
-- SELECT COUNT(*) as total_instruments, instrument_status, COUNT(*) as status_count
-- FROM instruments GROUP BY instrument_status;

-- SELECT COUNT(*) as pending_instruments
-- FROM instruments WHERE instrument_status = 'Pending';

-- SHOW COLUMNS FROM instruments LIKE '%checker%' OR LIKE '%submitted%';