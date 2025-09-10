<?php
// Quick constraint fix - run this once to solve the problem
require_once('./core/config/config.php');
require_once('./core/config/db.class.php');

header('Content-Type: application/json');

try {
    // Drop the problematic constraint
    DB::query("ALTER TABLE `test_instruments` DROP INDEX `idx_test_instrument_unique`");
    
    echo json_encode([
        'status' => 'success',
        'message' => 'Constraint dropped successfully! Instrument removal should now work.'
    ]);
    
} catch (Exception $e) {
    if (strpos($e->getMessage(), "check that column/key exists") !== false) {
        // Constraint doesn't exist (already dropped)
        echo json_encode([
            'status' => 'success', 
            'message' => 'Constraint already removed - no action needed.'
        ]);
    } else {
        echo json_encode([
            'status' => 'error',
            'message' => 'Error: ' . $e->getMessage()
        ]);
    }
}
?>