<?php
/**
 * Routine Test Auto-Scheduler
 * Handles automatic rescheduling of subsequent routine tests based on actual completion dates
 */

require_once '../config/db.class.php';

/**
 * Auto-schedule subsequent routine tests when a routine test is completed
 * 
 * @param string $completed_routine_wf_id The workflow ID of the completed routine test
 * @param string $actual_completion_date The actual completion date (YYYY-MM-DD format)
 * @param int $user_id The user ID making the change
 * @param int $unit_id The unit ID
 * @return bool True if auto-scheduling was successful, false otherwise
 */
function autoScheduleSubsequentRoutineTests($completed_routine_wf_id, $actual_completion_date, $user_id, $unit_id) {
    try {
        // Validate input parameters
        if (empty($completed_routine_wf_id)) {
            error_log("Auto-scheduling failed: Missing routine workflow ID");
            return false;
        }
        
        if (empty($actual_completion_date)) {
            error_log("Auto-scheduling failed: Missing actual completion date for " . $completed_routine_wf_id);
            return false;
        }
        
        // Validate date format
        $test_date = DateTime::createFromFormat('Y-m-d', $actual_completion_date);
        if (!$test_date || $test_date->format('Y-m-d') !== $actual_completion_date) {
            error_log("Auto-scheduling failed: Invalid date format '" . $actual_completion_date . "' for " . $completed_routine_wf_id);
            return false;
        }
        
        // Check if auto-scheduling is enabled
        $is_enabled = DB::queryFirstField("SELECT fn_is_auto_schedule_enabled('routine')");
        if (!$is_enabled) {
            error_log("Auto-scheduling is disabled for routine tests");
            return false;
        }
        
        // Get the details of the completed routine test
        error_log("Auto-scheduling step 1: Getting details for routine test: " . $completed_routine_wf_id);
        $completed_test = DB::queryFirstRow("
            SELECT 
                equip_id,
                test_id,
                routine_test_wf_planned_start_date,
                routine_test_wf_id,
                routine_test_req_id
            FROM tbl_routine_test_schedules 
            WHERE routine_test_wf_id = %s
        ", $completed_routine_wf_id);
        
        if (!$completed_test) {
            error_log("Could not find completed routine test: " . $completed_routine_wf_id);
            return false;
        }
        error_log("Auto-scheduling step 2: Found test - equip_id=" . $completed_test['equip_id'] . ", test_id=" . $completed_test['test_id'] . ", planned_start_date='" . $completed_test['routine_test_wf_planned_start_date'] . "'");
        
        // Get the frequency for this equipment/test combination
        error_log("Auto-scheduling step 3: Getting frequency for equipment " . $completed_test['equip_id'] . ", test " . $completed_test['test_id']);
        $frequency = DB::queryFirstField("
            SELECT test_frequency 
            FROM tbl_routine_tests_requests 
            WHERE equipment_id = %i AND test_id = %i
        ", $completed_test['equip_id'], $completed_test['test_id']);
        
        if (!$frequency) {
            error_log("Could not find frequency for equipment " . $completed_test['equip_id'] . ", test " . $completed_test['test_id']);
            return false;
        }
        error_log("Auto-scheduling step 4: Found frequency=" . $frequency);
        
        // Calculate frequency in months
        $frequency_months = getFrequencyInMonths($frequency);
        if ($frequency_months === false) {
            error_log("Invalid frequency: " . $frequency);
            return false;
        }
        
        // Find the immediate next routine test for the same equipment/test (cascading approach)
        error_log("Auto-scheduling step 5: Finding immediate next test with params - equip_id=" . $completed_test['equip_id'] . ", test_id=" . $completed_test['test_id'] . ", planned_start_date='" . $completed_test['routine_test_wf_planned_start_date'] . "', routine_wf_id=" . $completed_routine_wf_id);
        $next_test = DB::queryFirstRow("
            SELECT 
                routine_test_wf_id,
                routine_test_wf_planned_start_date
            FROM tbl_routine_test_schedules 
            WHERE equip_id = %i 
              AND test_id = %i 
              AND routine_test_wf_planned_start_date > %s
              AND routine_test_wf_status = 'Active'
              AND routine_test_wf_id != %s
            ORDER BY routine_test_wf_planned_start_date ASC
            LIMIT 1
        ", $completed_test['equip_id'], $completed_test['test_id'], $completed_test['routine_test_wf_planned_start_date'], $completed_routine_wf_id);
        
        // Calculate the new base date from actual completion (needed for both update and auto-creation logic)
        error_log("Auto-scheduling step 6: Creating base date from completion date: " . $actual_completion_date);
        $base_date = new DateTime($actual_completion_date);
        error_log("Auto-scheduling step 7: Base date created: " . $base_date->format('Y-m-d'));
        
        if (!$next_test) {
            error_log("No immediate next routine test found - checking if new test should be created");
            
            // Calculate what the next test date would be
            $next_due_date = clone $base_date;
            $next_due_date->add(new DateInterval('P' . $frequency_months . 'M'));
            $next_due_date->sub(new DateInterval('P1D')); // Subtract 1 day for planned date
            
            // Get the year of the last existing test
            $last_existing_test_year = date('Y', strtotime($completed_test['routine_test_wf_planned_start_date']));
            
            // Get the year of the calculated next date
            $next_due_year = $next_due_date->format('Y');
            
            error_log("Auto-creation check: Last test year={$last_existing_test_year}, Next due year={$next_due_year}, Next due date=" . $next_due_date->format('Y-m-d'));
            
            if ($last_existing_test_year == $next_due_year) {
                // Same year → Create new routine test
                error_log("Same year detected - creating new routine test for " . $next_due_date->format('Y-m-d'));
                
                $creation_result = createNewRoutineTest(
                    $completed_test['equip_id'],
                    $completed_test['test_id'], 
                    $next_due_date->format('Y-m-d'),
                    $user_id,
                    $unit_id,
                    $frequency,
                    $completed_test['routine_test_req_id']
                );
                
                if ($creation_result) {
                    error_log("Successfully auto-created new routine test for " . $next_due_date->format('Y-m-d'));
                    
                    // Log the overall auto-creation action
                /*    DB::insert('log', [
                        'change_type' => 'routine_auto_schedule_create',
                        'table_name' => 'tbl_routine_test_schedules',
                        'change_description' => sprintf(
                            'Auto-scheduling created new routine test for equipment %d, test %d. Next test planned for %s (same year continuation from %s)',
                            $completed_test['equip_id'],
                            $completed_test['test_id'],
                            $next_due_date->format('d-M-Y'),
                            date('d-M-Y', strtotime($actual_completion_date))
                        ),
                        'change_by' => $user_id,
                        'unit_id' => $unit_id
                    ]); */
                } else {
                    error_log("Failed to auto-create new routine test for " . $next_due_date->format('Y-m-d'));
                }
                
                return $creation_result;
            } else {
                // Different year → Do not create
                error_log("Next routine test date " . $next_due_date->format('Y-m-d') . " falls in different year ({$next_due_year} vs {$last_existing_test_year}). Auto-creation skipped per year boundary rule.");
                
                // Log that auto-creation was skipped due to year boundary
                DB::insert('log', [
                    'change_type' => 'routine_auto_schedule_year_boundary',
                    'table_name' => 'tbl_routine_test_schedules',
                    'change_description' => sprintf(
                        'Auto-scheduling skipped for equipment %d, test %d. Next test would be %s (year %s) but last test was in %s. Year boundary rule applied.',
                        $completed_test['equip_id'],
                        $completed_test['test_id'],
                        $next_due_date->format('d-M-Y'),
                        $next_due_year,
                        $last_existing_test_year
                    ),
                    'change_by' => $user_id,
                    'unit_id' => $unit_id
                ]);
                
                return true; // Not an error, just nothing to do due to year boundary
            }
        }
        
        // Calculate new planned date for immediate next test: completion date + frequency - 1 day
        $new_date = clone $base_date;
        $new_date->add(new DateInterval('P' . $frequency_months . 'M'));
        $new_date->sub(new DateInterval('P1D')); // Subtract 1 day for planned date
        $new_planned_date = $new_date->format('Y-m-d');
        
        // Debug logging
        error_log("Auto-scheduling debug: base_date=" . $base_date->format('Y-m-d') . 
                 ", frequency_months=" . $frequency_months . 
                 ", new_planned_date=" . $new_planned_date);
        $original_date = $next_test['routine_test_wf_planned_start_date'];
        
        $updated_count = 0;
        
        // Only update if the date actually changes
        if ($new_planned_date !== $original_date) {
            error_log("Auto-scheduling step 8: Updating immediate next test " . $next_test['routine_test_wf_id'] . " from '" . $original_date . "' to '" . $new_planned_date . "'");
            // Update the routine test schedule
            DB::query("
                UPDATE tbl_routine_test_schedules 
                SET routine_test_wf_planned_start_date = %s,
                    last_modified_date_time = NOW()
                WHERE routine_test_wf_id = %s
            ", $new_planned_date, $next_test['routine_test_wf_id']);
            
            $updated_count = 1;
            
            // Log the change
            DB::insert('log', [
                'change_type' => 'routine_auto_reschedule',
                'table_name' => 'tbl_routine_test_schedules',
                'change_description' => sprintf(
                    'Auto-rescheduled immediate next routine test %s from %s to %s due to completion of %s on %s (cascading approach)',
                    $next_test['routine_test_wf_id'],
                    date('d-M-Y', strtotime($original_date)),
                    $new_date->format('d-M-Y'),
                    $completed_routine_wf_id,
                    date('d-M-Y', strtotime($actual_completion_date))
                ),
                'change_by' => $user_id,
                'unit_id' => $unit_id
            ]);
        } else {
            error_log("Auto-scheduling: No date change needed for immediate next test " . $next_test['routine_test_wf_id'] . " (already scheduled for " . $new_planned_date . ")");
        }
        
        // Mark the completed test as auto-schedule processed
        DB::query("
            UPDATE tbl_test_schedules_tracking 
            SET auto_schedule_processed = 'Y',
                auto_schedule_trigger_date = NOW()
            WHERE val_wf_id = %s
        ", $completed_routine_wf_id);
        
        // Log the overall auto-scheduling action
        DB::insert('log', [
            'change_type' => 'routine_auto_schedule_complete',
            'table_name' => 'tbl_routine_test_schedules',
            'change_description' => sprintf(
                'Auto-scheduling completed for routine test %s. Updated %d immediate next test based on completion date %s (cascading approach)',
                $completed_routine_wf_id,
                $updated_count,
                date('d-M-Y', strtotime($actual_completion_date))
            ),
            'change_by' => $user_id,
            'unit_id' => $unit_id
        ]);
        
        error_log("Auto-scheduling completed: updated $updated_count immediate next routine test (cascading approach)");
        return true;
        
    } catch (Exception $e) {
        error_log("Error in auto-scheduling routine tests: " . $e->getMessage());
        
        // Log the error
        DB::insert('log', [
            'change_type' => 'routine_auto_schedule_error',
            'table_name' => 'tbl_routine_test_schedules',
            'change_description' => 'Auto-scheduling failed for routine test ' . $completed_routine_wf_id . ': ' . substr($e->getMessage(), 0, 200),
            'change_by' => $user_id,
            'unit_id' => $unit_id
        ]);
        
        return false;
    }
}

/**
 * Convert frequency code to months
 * 
 * @param string $frequency The frequency code (Q, H, Y, 2Y, etc.)
 * @return int|false The number of months, or false if invalid
 */
function getFrequencyInMonths($frequency) {
    switch (strtoupper($frequency)) {
        case 'Q': // Quarterly
            return 3;
        case 'H': // Half-yearly  
            return 6;
        case 'Y': // Yearly
            return 12;
        case '2Y': // Every 2 years
            return 24;
        case '3Y': // Every 3 years
            return 36;
        default:
            return false;
    }
}

/**
 * Get the actual completion date from test schedules tracking
 * 
 * @param string $routine_wf_id The routine workflow ID
 * @param string $provided_date Optional completion date if already known
 * @return string|false The completion date in Y-m-d format, or false if not found
 */
function getActualCompletionDate($routine_wf_id, $provided_date = null) {
    // If date is provided directly, validate and return it
    if (!empty($provided_date)) {
        // Validate date format
        $date = DateTime::createFromFormat('Y-m-d', $provided_date);
        if ($date && $date->format('Y-m-d') === $provided_date) {
            return $provided_date;
        }
        // Try other common date formats
        $date = DateTime::createFromFormat('d-m-Y', $provided_date);
        if ($date) {
            return $date->format('Y-m-d');
        }
    }
    
    // Fallback to database query
    return DB::queryFirstField("
        SELECT DATE(test_conducted_date) 
        FROM tbl_test_schedules_tracking 
        WHERE val_wf_id = %s AND test_conducted_date IS NOT NULL
    ", $routine_wf_id);
}

/**
 * Create a new routine test when none exist for continuing the schedule
 * 
 * @param int $equip_id Equipment ID
 * @param int $test_id Test ID  
 * @param string $planned_date Planned start date in Y-m-d format
 * @param int $user_id User ID creating the test
 * @param int $unit_id Unit ID
 * @param string $frequency_code Frequency code (Q, H, Y, 2Y, etc.)
 * @param int $routine_test_req_id Routine test request ID (same as previous test)
 * @return bool True if creation was successful, false otherwise
 */
function createNewRoutineTest($equip_id, $test_id, $planned_date, $user_id, $unit_id, $frequency_code, $routine_test_req_id) {
    try {
        // Generate new routine test workflow ID with dynamic frequency code
        $timestamp = time();
        $new_routine_wf_id = "R-{$equip_id}-{$unit_id}-{$test_id}-{$timestamp}-{$frequency_code}";
        
        // Check for ID conflicts and regenerate if needed
        $conflict_check = DB::queryFirstField("
            SELECT COUNT(*) FROM tbl_routine_test_schedules 
            WHERE routine_test_wf_id = %s
        ", $new_routine_wf_id);
        
        if ($conflict_check > 0) {
            // Add random suffix to avoid conflicts
            $new_routine_wf_id .= "-" . substr(md5(microtime()), 0, 8);
            error_log("Routine test ID conflict resolved. New ID: " . $new_routine_wf_id);
        }
        
        // Insert new routine test record
        DB::insert('tbl_routine_test_schedules', [
            'routine_test_wf_id' => $new_routine_wf_id,
            'equip_id' => $equip_id,
            'test_id' => $test_id,
            'routine_test_wf_planned_start_date' => $planned_date,
            'routine_test_wf_status' => 'Active',
            'unit_id' => $unit_id,
            'routine_test_req_id' => $routine_test_req_id, // Link to same request as previous test
            'auto_created' => 'Y', // Mark as auto-created
            'test_origin' => 'system_auto_created', // Mark origin
            'is_adhoc' => 'N' // Not an adhoc test
        ]);
        
        // Log the creation
        DB::insert('log', [
            'change_type' => 'routine_auto_create',
            'table_name' => 'tbl_routine_test_schedules',
            'change_description' => sprintf(
                'Auto-created new routine test %s for equipment %d, test %d, planned for %s (same year continuation)',
                $new_routine_wf_id,
                $equip_id,
                $test_id,
                date('d-M-Y', strtotime($planned_date))
            ),
            'change_by' => $user_id,
            'unit_id' => $unit_id
        ]);
        
        error_log("Auto-created new routine test: " . $new_routine_wf_id . " for " . $planned_date);
        return true;
        
    } catch (Exception $e) {
        error_log("Error creating new routine test: " . $e->getMessage());
        
        // Log the error
        DB::insert('log', [
            'change_type' => 'routine_auto_create_error',
            'table_name' => 'tbl_routine_test_schedules',
            'change_description' => 'Failed to auto-create new routine test for equipment ' . $equip_id . ', test ' . $test_id . ': ' . substr($e->getMessage(), 0, 200),
            'change_by' => $user_id,
            'unit_id' => $unit_id
        ]);
        
        return false;
    }
}

?>