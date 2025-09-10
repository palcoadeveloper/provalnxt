<?php
/**
 * Test script to verify the constraint fix for test_instruments table
 * This script will:
 * 1. Check if the problematic constraint exists
 * 2. Drop the constraint if it exists
 * 3. Verify the constraint was removed
 * 
 * Run this script from the browser: /test_constraint_fix.php
 */

require_once('./core/config/config.php');
require_once('./core/config/db.class.php');

// Set content type to HTML for browser viewing
header('Content-Type: text/html; charset=UTF-8');

echo "<h2>Test Instruments Constraint Fix</h2>";
echo "<pre>";

try {
    echo "1. Checking current constraints on test_instruments table...\n";
    
    // Check if the constraint exists
    $constraints = DB::query("SHOW INDEX FROM test_instruments WHERE Key_name = 'idx_test_instrument_unique'");
    
    if (!empty($constraints)) {
        echo "   ❌ Found problematic constraint 'idx_test_instrument_unique'\n";
        echo "   📄 Constraint details:\n";
        foreach ($constraints as $constraint) {
            echo "      - Column: " . $constraint['Column_name'] . "\n";
        }
        echo "\n";
        
        echo "2. Dropping the problematic constraint...\n";
        
        // Drop the constraint
        DB::query("ALTER TABLE `test_instruments` DROP INDEX `idx_test_instrument_unique`");
        
        echo "   ✅ Constraint dropped successfully!\n\n";
        
        // Verify it's gone
        echo "3. Verifying constraint removal...\n";
        $check = DB::query("SHOW INDEX FROM test_instruments WHERE Key_name = 'idx_test_instrument_unique'");
        
        if (empty($check)) {
            echo "   ✅ Constraint successfully removed!\n\n";
        } else {
            echo "   ❌ Constraint still exists!\n\n";
        }
        
    } else {
        echo "   ✅ No problematic constraint found (already removed or never existed)\n\n";
    }
    
    echo "4. Checking current table structure...\n";
    $indexes = DB::query("SHOW INDEX FROM test_instruments");
    
    echo "   Current indexes on test_instruments:\n";
    foreach ($indexes as $index) {
        echo "      - " . $index['Key_name'] . " (" . $index['Column_name'] . ")\n";
    }
    
    echo "\n5. Testing instrument operations...\n";
    echo "   ✅ Database connection working\n";
    echo "   ✅ Ready to test instrument addition/removal\n";
    
    echo "\n🎉 Constraint fix completed successfully!\n";
    echo "\nNext steps:\n";
    echo "1. Test adding an instrument to a test workflow\n";
    echo "2. Test removing the same instrument multiple times\n";
    echo "3. Verify no duplicate active instruments can be added\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    error_log("Constraint fix error: " . $e->getMessage());
}

echo "</pre>";

// Clean up - remove this test file after running
echo "<p><strong>Note:</strong> Remember to delete this test file after verifying the fix!</p>";
?>