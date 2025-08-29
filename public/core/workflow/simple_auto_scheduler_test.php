<?php
/**
 * Simple test of auto-scheduler functions without session dependencies
 */

// Minimal database connection
require_once '../config/db.class.php';

echo "=== Testing Auto-Scheduler Functions ===\n\n";

// Test 1: Check if auto-scheduling is enabled
try {
    $is_enabled = DB::queryFirstField("SELECT fn_is_auto_schedule_enabled('routine')");
    echo "1. Auto-scheduling enabled: " . ($is_enabled ? "YES" : "NO") . "\n";
} catch (Exception $e) {
    echo "1. Error checking auto-schedule status: " . $e->getMessage() . "\n";
}

// Test 2: Check frequency for the test equipment/test combination
try {
    $frequency = DB::queryFirstField("
        SELECT test_frequency 
        FROM tbl_routine_tests_requests 
        WHERE equipment_id = 1 AND test_id = 6
    ");
    echo "2. Frequency for equipment 1, test 6: " . ($frequency ?: "NOT FOUND") . "\n";
} catch (Exception $e) {
    echo "2. Error getting frequency: " . $e->getMessage() . "\n";
}

// Test 3: Get completion data for the specific tests
try {
    $completions = DB::query("
        SELECT 
            test_wf_id,
            val_wf_id,
            DATE_FORMAT(test_conducted_date, '%d-%b-%Y') as completion_date,
            auto_schedule_processed
        FROM tbl_test_schedules_tracking 
        WHERE test_wf_id IN ('T-1-7-6-1754804872', 'T-1-7-6-1754805803')
           OR val_wf_id IN ('R-1-7-6-1740076200-Q', 'R-1-7-6-1755541800-Q')
        ORDER BY test_conducted_date
    ");
    
    echo "3. Completion data:\n";
    foreach ($completions as $completion) {
        echo "   - " . $completion['test_wf_id'] . " / " . $completion['val_wf_id'] . 
             " completed " . $completion['completion_date'] . 
             " (auto-processed: " . $completion['auto_schedule_processed'] . ")\n";
    }
} catch (Exception $e) {
    echo "3. Error getting completion data: " . $e->getMessage() . "\n";
}

// Test 4: Get current routine test schedule
try {
    $routine_tests = DB::query("
        SELECT 
            routine_test_wf_id,
            DATE_FORMAT(routine_test_wf_planned_start_date, '%d-%b-%Y') as planned_date
        FROM tbl_routine_test_schedules 
        WHERE equip_id = 1 AND test_id = 6 
        ORDER BY routine_test_wf_planned_start_date ASC
    ");
    
    echo "4. Current routine test schedule:\n";
    foreach ($routine_tests as $test) {
        echo "   - " . $test['routine_test_wf_id'] . ": " . $test['planned_date'] . "\n";
    }
} catch (Exception $e) {
    echo "4. Error getting routine test schedule: " . $e->getMessage() . "\n";
}

// Test 5: Test frequency conversion function
require_once '../workflow/routine_auto_scheduler.php';

echo "5. Frequency conversion test:\n";
$test_frequencies = ['Q', 'H', 'Y', '2Y', 'INVALID'];
foreach ($test_frequencies as $freq) {
    $months = getFrequencyInMonths($freq);
    echo "   - $freq -> " . ($months !== false ? "$months months" : "INVALID") . "\n";
}

echo "\n=== Test Complete ===\n";

?>