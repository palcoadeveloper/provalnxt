-- Quick fix: Drop the problematic constraint immediately
-- Run this SQL command in your database to fix the instrument removal error

ALTER TABLE `test_instruments` DROP INDEX `idx_test_instrument_unique`;

-- Verify it was dropped (optional check)
SHOW INDEX FROM test_instruments WHERE Key_name = 'idx_test_instrument_unique';