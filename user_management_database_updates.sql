-- User Management Database Updates
-- Add Pending status to user_status ENUM
-- Created: September 30, 2025

-- Step 1: Add 'Pending' to user_status ENUM
ALTER TABLE users MODIFY COLUMN user_status ENUM('Active', 'Inactive', 'Pending') NOT NULL DEFAULT 'Active';

-- Step 2: Verify the change
SELECT COLUMN_TYPE
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
AND TABLE_NAME = 'users'
AND COLUMN_NAME = 'user_status';

-- Step 3: Display current user status distribution
SELECT user_status, COUNT(*) as count
FROM users
GROUP BY user_status
ORDER BY user_status;

SELECT 'User status ENUM updated successfully - Pending status added' as status;