<?php
/**
 * Test script for routine auto-scheduler
 * This script tests the auto-scheduling logic without making actual changes
 */

require_once '../config/db.class.php';
require_once '../workflow/routine_auto_scheduler.php';

// Test with the specific cases mentioned
echo "=== Testing Auto-Scheduling Logic ===\n\n";

// Test Case 1: T-1-7-6-1754804872 completed on 16-Jan-2025
echo "Test Case 1: T-1-7-6-1754804872 completed on 16-Jan-2025\n";
echo "Expected: R-1-7-6-1747679400-Q should be updated from 19-May-2025 to ~16-Apr-2025\n";

// Get the completion details
$completion_details = DB::queryFirstRow("
    SELECT 
        val_wf_id,
        DATE_FORMAT(test_conducted_date, '%Y-%m-%d') as completion_date,
        DATE_FORMAT(test_conducted_date, '%d-%b-%Y') as formatted_date
    FROM tbl_test_schedules_tracking 
    WHERE test_wf_id = 'T-1-7-6-1754804872'
");

if ($completion_details) {
    echo "Completion found: " . $completion_details['val_wf_id'] . " on " . $completion_details['formatted_date'] . "\n";
    
    // Get current subsequent test dates
    $current_tests = DB::query("
        SELECT 
            routine_test_wf_id,
            DATE_FORMAT(routine_test_wf_planned_start_date, '%d-%b-%Y') as current_planned_date,
            routine_test_wf_planned_start_date
        FROM tbl_routine_test_schedules 
        WHERE equip_id = 1 AND test_id = 6 
          AND routine_test_wf_planned_start_date > '2025-02-20'
        ORDER BY routine_test_wf_planned_start_date ASC
    ");
    
    echo "Current subsequent tests:\n";
    foreach ($current_tests as $test) {
        echo "  - " . $test['routine_test_wf_id'] . ": " . $test['current_planned_date'] . "\n";
    }
    
    // Calculate what the new dates should be
    $base_date = new DateTime($completion_details['completion_date']);
    echo "\nCalculated new dates (based on completion " . $completion_details['formatted_date'] . "):\n";
    
    foreach ($current_tests as $index => $test) {
        $new_date = clone $base_date;
        $new_date->add(new DateInterval('P' . (($index + 1) * 3) . 'M')); // 3 months for quarterly
        echo "  - " . $test['routine_test_wf_id'] . ": " . $test['current_planned_date'] . " -> " . $new_date->format('d-M-Y') . "\n";
    }
}

echo "\n" . str_repeat("-", 60) . "\n\n";

// Test Case 2: R-1-7-6-1755541800-Q completed on 10-Aug-2025
echo "Test Case 2: R-1-7-6-1755541800-Q completed on 10-Aug-2025\n";
echo "Expected: R-1-7-6-1763404200-Q should be updated from 17-Nov-2025 to ~10-Nov-2025\n";

$completion_details2 = DB::queryFirstRow("
    SELECT 
        val_wf_id,
        DATE_FORMAT(test_conducted_date, '%Y-%m-%d') as completion_date,
        DATE_FORMAT(test_conducted_date, '%d-%b-%Y') as formatted_date
    FROM tbl_test_schedules_tracking 
    WHERE val_wf_id = 'R-1-7-6-1755541800-Q'
");

if ($completion_details2) {
    echo "Completion found: " . $completion_details2['val_wf_id'] . " on " . $completion_details2['formatted_date'] . "\n";
    
    // Get the next test
    $next_test = DB::queryFirstRow("
        SELECT 
            routine_test_wf_id,
            DATE_FORMAT(routine_test_wf_planned_start_date, '%d-%b-%Y') as current_planned_date,
            routine_test_wf_planned_start_date
        FROM tbl_routine_test_schedules 
        WHERE routine_test_wf_id = 'R-1-7-6-1763404200-Q'
    ");
    
    if ($next_test) {
        echo "Next test: " . $next_test['routine_test_wf_id'] . " currently scheduled for " . $next_test['current_planned_date'] . "\n";
        
        // Calculate new date
        $base_date2 = new DateTime($completion_details2['completion_date']);
        $new_date2 = clone $base_date2;
        $new_date2->add(new DateInterval('P3M')); // 3 months for quarterly
        echo "Should be rescheduled to: " . $new_date2->format('d-M-Y') . "\n";
    }
}

echo "\n=== Auto-Schedule Configuration Check ===\n";

$config_check = DB::queryFirstField("SELECT fn_is_auto_schedule_enabled('routine')");
echo "Auto-scheduling enabled: " . ($config_check ? "YES" : "NO") . "\n";

$frequency_check = DB::queryFirstField("
    SELECT test_frequency 
    FROM tbl_routine_tests_requests 
    WHERE equipment_id = 1 AND test_id = 6
");
echo "Test frequency for equipment 1, test 6: " . ($frequency_check ?: "NOT FOUND") . "\n";

echo "\n=== Test Complete ===\n";

?>