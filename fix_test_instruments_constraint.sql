-- Fix for test_instruments constraint issue
-- This script removes the problematic unique constraint that prevents multiple soft-deletes
-- 
-- Problem: The idx_test_instrument_unique constraint prevents soft-deletion (is_active = 0) 
-- of the same instrument multiple times, causing "Duplicate entry" errors.
--
-- Solution: Drop the constraint and rely on application-level validation in PHP
-- to prevent duplicate active instruments.
--
-- Date: September 9, 2025
-- Ticket: Instrument removal error - duplicate key constraint violation

-- Drop the problematic unique constraint
ALTER TABLE `test_instruments` DROP INDEX `idx_test_instrument_unique`;

-- Optional: Add a comment to document the change
ALTER TABLE `test_instruments` COMMENT = 'Updated to remove unique constraint - duplicate prevention handled at application level';

-- Verification query to ensure constraint is dropped
-- Run this to verify the constraint no longer exists:
-- SHOW INDEX FROM test_instruments WHERE Key_name = 'idx_test_instrument_unique';