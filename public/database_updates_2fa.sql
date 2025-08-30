-- Two-Factor Authentication Database Schema Updates
-- ProVal HVAC System
-- Execute these SQL statements in your MySQL/MariaDB database

-- Add 2FA configuration columns to units table
ALTER TABLE units 
ADD COLUMN two_factor_enabled ENUM('Yes', 'No') NOT NULL DEFAULT 'No' COMMENT 'Enable/disable 2FA for this unit',
ADD COLUMN otp_validity_minutes INT NOT NULL DEFAULT 5 COMMENT 'OTP validity period in minutes (1-15)',
ADD COLUMN otp_digits INT NOT NULL DEFAULT 6 COMMENT 'Number of digits in OTP (4-8)',
ADD COLUMN otp_resend_delay_seconds INT NOT NULL DEFAULT 60 COMMENT 'Delay between OTP resend requests';

-- Create user_otp_sessions table for tracking OTP sessions
CREATE TABLE user_otp_sessions (
    otp_session_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    unit_id INT NOT NULL,
    employee_id VARCHAR(50) NOT NULL,
    otp_code VARCHAR(10) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NOT NULL,
    is_used ENUM('Yes', 'No') NOT NULL DEFAULT 'No',
    attempts_count INT NOT NULL DEFAULT 0,
    ip_address VARCHAR(45) NOT NULL,
    user_agent TEXT,
    session_token VARCHAR(128) NOT NULL,
    INDEX idx_user_id (user_id),
    INDEX idx_employee_id (employee_id),
    INDEX idx_expires_at (expires_at),
    INDEX idx_session_token (session_token),
    INDEX idx_ip_address (ip_address),
    CONSTRAINT fk_otp_sessions_unit_id FOREIGN KEY (unit_id) REFERENCES units(unit_id) ON DELETE CASCADE
) ENGINE=InnoDB COMMENT='Stores OTP sessions for two-factor authentication';

-- Create indexes for efficient cleanup of expired sessions
CREATE INDEX idx_cleanup ON user_otp_sessions (expires_at, is_used);

-- Add constraints to ensure valid configuration values
ALTER TABLE units 
ADD CONSTRAINT chk_otp_validity CHECK (otp_validity_minutes BETWEEN 1 AND 15),
ADD CONSTRAINT chk_otp_digits CHECK (otp_digits BETWEEN 4 AND 8),
ADD CONSTRAINT chk_otp_resend_delay CHECK (otp_resend_delay_seconds BETWEEN 30 AND 300);