-- Simple Database Updates for User Checker Approval Workflow
-- Created: September 30, 2025

-- Step 1: Create user workflow log table
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

-- Step 2: Update existing Active vendor records to have proper submitted_by tracking
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
WHERE user_status = 'Active' AND user_type = 'vendor' AND submitted_by IS NULL;

-- Step 3: Insert initial workflow log entries for existing vendor users
INSERT IGNORE INTO user_workflow_log (user_id, action_type, performed_by, action_date, remarks)
SELECT
    u.user_id,
    'Created' as action_type,
    COALESCE(u.submitted_by, 1) as performed_by,
    COALESCE(u.user_created_datetime, NOW()) as action_date,
    'Historical record - migrated during user workflow implementation' as remarks
FROM users u
WHERE u.user_status = 'Active' AND u.user_type = 'vendor';

-- Verification
SELECT 'User workflow database setup completed successfully' as status;