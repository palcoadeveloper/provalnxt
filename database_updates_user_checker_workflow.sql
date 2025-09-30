-- Database Schema Updates for User Checker Approval Workflow
-- ProVal HVAC System - Engineering User Vendor Employee Management Enhancement
-- Created: September 30, 2025

-- Step 1: Add 'Pending' status to user_status ENUM
ALTER TABLE users
MODIFY COLUMN user_status ENUM('Active', 'Inactive', 'Pending') NOT NULL DEFAULT 'Pending';

-- Step 2: Add checker approval tracking columns
ALTER TABLE users
ADD COLUMN submitted_by INT NULL COMMENT 'User ID who submitted/modified the record',
ADD COLUMN checker_id INT NULL COMMENT 'User ID who performed checker approval/rejection',
ADD COLUMN checker_action ENUM('Approved', 'Rejected') NULL COMMENT 'Checker decision',
ADD COLUMN checker_date DATETIME NULL COMMENT 'Date and time of checker action',
ADD COLUMN checker_remarks TEXT NULL COMMENT 'Checker comments/remarks',
ADD COLUMN original_data JSON NULL COMMENT 'Original data before modification for audit trail';

-- Step 3: Add foreign key constraints for data integrity
ALTER TABLE users
ADD CONSTRAINT fk_users_submitted_by
    FOREIGN KEY (submitted_by) REFERENCES users(user_id) ON DELETE SET NULL,
ADD CONSTRAINT fk_users_checker_id
    FOREIGN KEY (checker_id) REFERENCES users(user_id) ON DELETE SET NULL;

-- Step 4: Create index for performance optimization on checker workflow queries
CREATE INDEX idx_users_workflow
ON users(user_status, submitted_by, unit_id, user_type);

CREATE INDEX idx_users_checker
ON users(checker_id, checker_date);

-- Step 5: Update existing Active records to have proper submitted_by tracking
-- This sets submitted_by to the first admin user found, or NULL if none exist
UPDATE users
SET submitted_by = (
    SELECT admin_user_id
    FROM (
        SELECT u.user_id as admin_user_id
        FROM users u
        WHERE (u.is_admin = 'Yes' OR u.is_super_admin = 'Yes')
        AND u.user_status = 'Active'
        LIMIT 1
    ) as admin_users
)
WHERE user_status = 'Active' AND submitted_by IS NULL;

-- Step 6: Create audit log table for user workflow tracking
CREATE TABLE IF NOT EXISTS user_workflow_log (
    log_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL COMMENT 'User ID being managed',
    action_type ENUM('Created', 'Modified', 'Approved', 'Rejected', 'Resubmitted') NOT NULL,
    performed_by INT NOT NULL,
    action_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    old_data JSON NULL,
    new_data JSON NULL,
    remarks TEXT NULL,
    ip_address VARCHAR(45) NULL,
    user_agent TEXT NULL,
    INDEX idx_user_workflow_log_user (user_id),
    INDEX idx_user_workflow_log_date (action_date),
    INDEX idx_user_workflow_log_performer (performed_by),
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (performed_by) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Audit log for user workflow actions';

-- Step 7: Insert initial workflow log entries for existing users
INSERT INTO user_workflow_log (user_id, action_type, performed_by, action_date, remarks)
SELECT
    u.user_id,
    'Created' as action_type,
    COALESCE(u.submitted_by, 1) as performed_by,
    COALESCE(u.user_created_datetime, NOW()) as action_date,
    'Historical record - migrated during user workflow implementation' as remarks
FROM users u
WHERE u.user_status = 'Active' AND u.user_type = 'vendor';

-- Verification queries (to be run after applying changes)
-- SELECT COUNT(*) as total_users, user_status, COUNT(*) as status_count
-- FROM users GROUP BY user_status;

-- SELECT COUNT(*) as pending_users
-- FROM users WHERE user_status = 'Pending';

-- SHOW COLUMNS FROM users LIKE '%checker%' OR LIKE '%submitted%';