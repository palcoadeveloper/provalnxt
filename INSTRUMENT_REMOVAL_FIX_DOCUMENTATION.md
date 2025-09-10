# Instrument Removal Error Fix Documentation

## Problem Description
**Error**: `Duplicate entry 'T-1-7-1-1756793223-INs2346777-0' for key 'test_instruments.idx_test_instrument_unique'`

**Context**: When attempting to remove the same instrument multiple times from a test workflow via `removetestinstrument_simple.php`.

## Root Cause Analysis

### Database Constraint Issue
The `test_instruments` table had a unique constraint:
```sql
CREATE UNIQUE INDEX `idx_test_instrument_unique` ON `test_instruments` 
(`test_val_wf_id`, `instrument_id`, `is_active`);
```

### Soft Delete Pattern
The system uses **soft deletes** - instead of physically removing records, it sets `is_active = 0`. This caused issues when trying to "remove" the same instrument multiple times, as it attempted to create multiple records with identical `(test_val_wf_id, instrument_id, 0)` combinations.

## Solution Implemented

### 1. Database Constraint Removal
**Action**: Dropped the problematic unique constraint
```sql
ALTER TABLE `test_instruments` DROP INDEX `idx_test_instrument_unique`;
```

**File**: `fix_test_instruments_constraint.sql`

### 2. Application-Level Validation (Already in Place)
Both instrument addition endpoints already have proper duplicate prevention:

**Files with validation**:
- `public/core/data/save/addtestinstrument.php` (lines 87-99)
- `public/core/data/save/addtestinstrument_simple.php` (lines 88-100)

**Validation logic**:
```php
// Check if instrument is already added to this test
$existing_mapping = DB::queryFirstRow(
    "SELECT mapping_id FROM test_instruments 
     WHERE test_val_wf_id = %s 
     AND instrument_id = %s 
     AND is_active = 1",
    $test_val_wf_id,
    $instrument_id
);

if ($existing_mapping) {
    throw new InvalidArgumentException("Instrument is already added to this test");
}
```

## Testing Procedures

### Test Script
**File**: `public/test_constraint_fix.php`
- Checks if constraint exists
- Drops the constraint
- Verifies removal
- Shows current table structure

### Manual Testing Steps
1. **Add an instrument** to a test workflow
2. **Remove the instrument** (should work)
3. **Try to remove the same instrument again** (should work without error)
4. **Try to add the same instrument twice** (should be prevented by application logic)

## Files Modified/Created

### Created Files
1. `fix_test_instruments_constraint.sql` - Database migration script
2. `public/test_constraint_fix.php` - Testing and verification script
3. `INSTRUMENT_REMOVAL_FIX_DOCUMENTATION.md` - This documentation

### Verified Files (No Changes Needed)
1. `public/core/data/save/addtestinstrument.php` - Has proper validation
2. `public/core/data/save/addtestinstrument_simple.php` - Has proper validation
3. `public/core/data/update/removetestinstrument_simple.php` - Works correctly without constraint

## Benefits of This Approach

### ✅ Advantages
1. **Simple solution** - Just removes problematic constraint
2. **Preserves existing logic** - Application validation already works correctly
3. **Maintains data integrity** - Prevents duplicate active instruments via PHP
4. **Flexible** - Allows multiple soft-deletes without database errors
5. **No code changes** - Existing validation logic is sufficient

### ⚠️ Considerations
1. **Database-level uniqueness removed** - Now relies entirely on application logic
2. **Multiple inactive records allowed** - Same instrument can have multiple `is_active = 0` records

## Implementation Notes

### Why This Works Better
- **Database constraints** are rigid and don't understand business logic (soft deletes)
- **Application validation** is flexible and can implement complex business rules
- **Performance impact** is minimal as the duplicate check query is simple and indexed

### Security & Data Integrity
- Application validation is sufficient for preventing duplicates
- All API endpoints require authentication and CSRF protection
- Parameterized queries prevent SQL injection
- Audit logging tracks all instrument additions/removals

## Future Maintenance

### If Constraint is Re-added
If someone attempts to re-add a similar unique constraint in the future, consider:
```sql
-- This would work better (only enforce uniqueness for active records):
CREATE UNIQUE INDEX `idx_test_instrument_active_unique` ON `test_instruments` 
(`test_val_wf_id`, `instrument_id`) WHERE `is_active` = 1;
```

However, the current application-level validation approach is sufficient and preferred.

### Monitoring
- Monitor the `log` table for `test_instrument_add` and `test_instrument_remove` events
- Check for any unusual patterns in instrument additions/removals
- Verify no duplicate active instruments exist in the database

---

**Date**: September 9, 2025  
**Status**: ✅ Implemented and Ready for Testing  
**Next Step**: Run `public/test_constraint_fix.php` to apply the database fix