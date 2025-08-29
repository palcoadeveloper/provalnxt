<?php
/**
 * Validation Auto-Scheduler
 * Handles automatic creation of subsequent validations based on actual completion dates
 * This implements validation-specific business rules that differ from routine test auto-scheduling
 */

// DB class is already loaded by the calling script (updatewfstage.php)
// No need to include it again to avoid path issues when called from different contexts

/**
 * Auto-schedule subsequent validations when a validation test is completed
 * 
 * @param string $val_wf_id The validation workflow ID
 * @param int $test_id The completed test ID
 * @param int $test_sch_id The test schedule ID
 * @param string $actual_completion_date The actual completion date (YYYY-MM-DD format)
 * @param int $user_id The user ID making the change
 * @param int $unit_id The unit ID
 * @return bool True if auto-scheduling was successful, false otherwise
 */
function autoScheduleSubsequentValidations($val_wf_id, $test_id, $test_sch_id, $actual_completion_date, $user_id, $unit_id) {
    try {
        // Validate input parameters
        if (empty($val_wf_id)) {
            error_log("Validation auto-scheduling failed: Missing validation workflow ID");
            return false;
        }
        
        if (empty($test_id)) {
            error_log("Validation auto-scheduling failed: Missing test ID for " . $val_wf_id);
            return false;
        }
        
        if (empty($actual_completion_date)) {
            error_log("Validation auto-scheduling failed: Missing actual completion date for " . $val_wf_id);
            return false;
        }
        
        // Validate date format
        $test_date = DateTime::createFromFormat('Y-m-d', $actual_completion_date);
        if (!$test_date || $test_date->format('Y-m-d') !== $actual_completion_date) {
            error_log("Validation auto-scheduling failed: Invalid date format '" . $actual_completion_date . "' for " . $val_wf_id);
            return false;
        }
        
        // Check if auto-scheduling is enabled
        $is_enabled = DB::queryFirstField("SELECT fn_is_auto_schedule_enabled('validation')");
        if (!$is_enabled) {
            error_log("Auto-scheduling is disabled for validations");
            return false;
        }
        
        // Get validation context information
        error_log("Validation auto-scheduling step 1: Getting context for validation: " . $val_wf_id);
        $context = getValidationContext($val_wf_id, $test_sch_id);
        
        if (!$context) {
            error_log("Could not find validation context for: " . $val_wf_id);
            return false;
        }
        
        error_log("Validation auto-scheduling step 2: Found context - equip_id=" . $context['equip_id'] . ", frequency=" . $context['frequency_code'] . ", validation_current_year=" . $context['validation_current_year']);
        
        // Check if frequency supports auto-scheduling (only Annual 'Y')
        if ($context['frequency_code'] !== 'Y') {
            error_log("Validation auto-scheduling skipped: Frequency '" . $context['frequency_code'] . "' not supported (only 'Y' supported)");
            
            // Log the skip reason
            DB::insert('auto_schedule_log', [
                'trigger_type' => 'validation_frequency_check',
                'original_id' => $val_wf_id,
                'frequency' => $context['frequency_code'],
                'status' => 'no_action_needed',
                'error_details' => 'Frequency ' . $context['frequency_code'] . ' not supported for auto-scheduling',
                'business_rule_applied' => 'frequency_validation_check',
                'trigger_timestamp' => DB::sqleval('NOW()')
            ]);
            
            return true; // Not an error, just not eligible
        }
        
        // Determine target test based on business rules
        error_log("Validation auto-scheduling step 3: Determining target test");
        $target_test_id = determineTargetTest(
            $context['equip_id'], 
            $val_wf_id, 
            $context['primary_test_id'], 
            $context['secondary_test_id'], 
            $test_id
        );
        
        if ($target_test_id === null || $target_test_id != $test_id) {
            error_log("Validation auto-scheduling skipped: Completed test " . $test_id . " is not the target test for auto-scheduling (target: " . $target_test_id . ")");
            
            // Log the skip reason
            DB::insert('auto_schedule_log', [
                'trigger_type' => 'validation_test_selection',
                'original_id' => $val_wf_id,
                'test_id' => $test_id,
                'status' => 'no_action_needed',
                'error_details' => 'Completed test is not the target test for auto-scheduling',
                'business_rule_applied' => 'test_selection_logic',
                'trigger_timestamp' => DB::sqleval('NOW()')
            ]);
            
            return true; // Not an error, just not the right test
        }
        
        // Apply year-based business rule
        $execution_year = date('Y', strtotime($actual_completion_date));
        error_log("Validation auto-scheduling step 4: Checking year rule - execution_year=" . $execution_year . ", validation_current_year=" . $context['validation_current_year']);
        
        if (!validateYearBasedRule($execution_year, $context['validation_current_year'], $context['frequency_code'])) {
            error_log("Validation auto-scheduling skipped: Year rule not met - execution_year=" . $execution_year . ", validation_current_year=" . $context['validation_current_year']);
            
            // Log the skip reason
            DB::insert('auto_schedule_log', [
                'trigger_type' => 'validation_year_check',
                'original_id' => $val_wf_id,
                'equipment_id' => $context['equip_id'],
                'test_id' => $test_id,
                'original_execution_date' => $actual_completion_date,
                'frequency' => $context['frequency_code'],
                'status' => 'no_action_needed',
                'error_details' => 'Execution year: ' . $execution_year . ', Validation current year: ' . $context['validation_current_year'],
                'business_rule_applied' => 'year_comparison_logic',
                'trigger_timestamp' => DB::sqleval('NOW()')
            ]);
            
            return true; // Not an error, just not eligible based on year rule
        }
        
        // Create new validation
        error_log("Validation auto-scheduling step 5: Creating new validation");
        $creation_result = createNewValidation(
            $val_wf_id,
            $context['unit_id'],
            $context['equip_id'],
            $actual_completion_date,
            $context['frequency_code'],
            $user_id,
            $unit_id
        );
        
        if (!$creation_result) {
            error_log("Failed to create new validation for " . $val_wf_id);
            return false;
        }
        
        // Mark the validation as auto-schedule processed
        DB::query("
            UPDATE tbl_test_schedules_tracking 
            SET auto_schedule_processed = 'Y',
                auto_schedule_trigger_date = NOW()
            WHERE val_wf_id = %s AND test_sch_id = %i
        ", $val_wf_id, $test_sch_id);
        
        // Log the overall success
        DB::insert('auto_schedule_log', [
            'trigger_type' => 'validation_auto_schedule_complete',
            'original_id' => $val_wf_id,
            'new_id' => $creation_result,
            'equipment_id' => $context['equip_id'],
            'test_id' => $test_id,
            'original_execution_date' => $actual_completion_date,
            'action_taken' => 'Created new validation study',
            'status' => 'success',
            'business_rule_applied' => 'previous_year_annual_validation',
            'trigger_timestamp' => DB::sqleval('NOW()')
        ]);
        
        error_log("Validation auto-scheduling completed successfully: created " . $creation_result);
        return true;
        
    } catch (Exception $e) {
        error_log("Error in validation auto-scheduling: " . $e->getMessage());
        
        // Log the error
        DB::insert('auto_schedule_log', [
            'trigger_type' => 'validation_auto_schedule_error',
            'original_id' => $val_wf_id,
            'status' => 'error',
            'error_details' => 'Auto-scheduling failed: ' . substr($e->getMessage(), 0, 200),
            'business_rule_applied' => 'error_handling',
            'trigger_timestamp' => DB::sqleval('NOW()')
        ]);
        
        return false;
    }
}

/**
 * Get validation context information from the database
 * 
 * @param string $val_wf_id The validation workflow ID
 * @param int $test_sch_id The test schedule ID
 * @return array|false Validation context data or false if not found
 */
function getValidationContext($val_wf_id, $test_sch_id) {
    try {
        // Query validation context using a view or join
        $context = DB::queryFirstRow("
            SELECT 
                vs.unit_id,
                vs.equip_id,
                e.validation_frequency as frequency_code,
                YEAR(vs.val_wf_planned_start_date) as validation_current_year,
                u.primary_test_id,
                u.secondary_test_id,
                tst.test_conducted_date
            FROM tbl_val_schedules vs
            JOIN equipments e ON vs.equip_id = e.equipment_id  
            JOIN units u ON vs.unit_id = u.unit_id
            JOIN tbl_test_schedules_tracking tst ON vs.val_wf_id = tst.val_wf_id
            WHERE vs.val_wf_id = %s
              AND tst.test_sch_id = %i
        ", $val_wf_id, $test_sch_id);
        
        return $context;
        
    } catch (Exception $e) {
        error_log("Error getting validation context: " . $e->getMessage());
        return false;
    }
}

/**
 * Determine target test based on validation business rules
 * 
 * @param int $equipment_id Equipment ID
 * @param string $val_wf_id Validation workflow ID
 * @param int $primary_test_id Primary test ID
 * @param int $secondary_test_id Secondary test ID
 * @param int $completed_test_id The completed test ID
 * @return int|null Target test ID or null if none determined
 */
function determineTargetTest($equipment_id, $val_wf_id, $primary_test_id, $secondary_test_id, $completed_test_id) {
    try {
        // Check if primary test is in this validation study
        $primary_in_study = DB::queryFirstField("
            SELECT COUNT(*) > 0
            FROM tbl_test_schedules_tracking tst
            WHERE tst.val_wf_id = %s 
              AND tst.test_id = %i
        ", $val_wf_id, $primary_test_id);
        
        // Check if secondary test is in this validation study
        $secondary_in_study = DB::queryFirstField("
            SELECT COUNT(*) > 0
            FROM tbl_test_schedules_tracking tst
            WHERE tst.val_wf_id = %s 
              AND tst.test_id = %i
        ", $val_wf_id, $secondary_test_id);
        
        // Apply business rules for test selection
        if ($primary_in_study && $secondary_in_study) {
            // Both tests in study - use primary test
            return $primary_test_id;
        } elseif ($primary_in_study && !$secondary_in_study) {
            // Only primary test in study
            return $primary_test_id;
        } elseif (!$primary_in_study && $secondary_in_study) {
            // Only secondary test in study
            return $secondary_test_id;
        } else {
            // Neither test in study
            return null;
        }
        
    } catch (Exception $e) {
        error_log("Error determining target test: " . $e->getMessage());
        return null;
    }
}

/**
 * Validate year-based business rule for validation auto-scheduling
 * 
 * @param int $execution_year Year when test was executed
 * @param int $validation_current_year Current validation year
 * @param string $frequency Frequency code
 * @return bool True if rule passes, false otherwise
 */
function validateYearBasedRule($execution_year, $validation_current_year, $frequency) {
    // Only schedule if execution year = (validation_current_year - 1) AND frequency = 'Y'
    return ($execution_year == ($validation_current_year - 1)) && ($frequency == 'Y');
}

/**
 * Create a new validation with 'A' suffix
 * 
 * @param string $original_val_wf_id Original validation workflow ID
 * @param int $unit_id Unit ID
 * @param int $equip_id Equipment ID
 * @param string $actual_completion_date Actual completion date
 * @param string $frequency_code Frequency code
 * @param int $user_id User ID creating the validation
 * @param int $session_unit_id Session unit ID
 * @return string|false New validation workflow ID or false on failure
 */
function createNewValidation($original_val_wf_id, $unit_id, $equip_id, $actual_completion_date, $frequency_code, $user_id, $session_unit_id) {
    try {
        // Calculate next validation date: test_conducted_date + 1 YEAR - 1 DAY
        $next_date = new DateTime($actual_completion_date);
        $next_date->add(new DateInterval('P1Y'));
        $next_date->sub(new DateInterval('P1D'));
        $next_validation_date = $next_date->format('Y-m-d');
        
        // Generate new validation workflow ID with 'A' suffix
        $new_val_wf_id = $original_val_wf_id . '-A';
        
        // Check for ID collision and increment suffix if needed
        while (DB::queryFirstField("SELECT COUNT(*) FROM tbl_val_schedules WHERE val_wf_id = %s", $new_val_wf_id) > 0) {
            $new_val_wf_id = $new_val_wf_id . '-A';
            error_log("Validation ID conflict resolved. New ID: " . $new_val_wf_id);
        }
        
        // Create new validation record
        DB::insert('tbl_val_schedules', [
            'unit_id' => $unit_id,
            'equip_id' => $equip_id,
            'val_wf_id' => $new_val_wf_id,
            'val_wf_planned_start_date' => $next_validation_date,
            'val_wf_status' => 'Active',
            'frequency_code' => $frequency_code,
            'parent_val_wf_id' => $original_val_wf_id,
            'auto_created' => 'Y',
            'actual_execution_date' => $actual_completion_date,
            'created_date_time' => DB::sqleval('NOW()'),
            'last_modified_date_time' => DB::sqleval('NOW()')
        ]);
        
        // Log the creation
        DB::insert('log', [
            'change_type' => 'validation_auto_create',
            'table_name' => 'tbl_val_schedules',
            'change_description' => sprintf(
                'Auto-created new validation %s for equipment %d, planned for %s.',
                $new_val_wf_id,
                $equip_id,
                date('d-M-Y', strtotime($next_validation_date))
            ),
            'change_by' => $user_id,
            'unit_id' => $session_unit_id
        ]);
        
        error_log("Auto-created new validation: " . $new_val_wf_id . " for " . $next_validation_date);
        return $new_val_wf_id;
        
    } catch (Exception $e) {
        error_log("Error creating new validation: " . $e->getMessage());
        
        // Log the error
        DB::insert('log', [
            'change_type' => 'validation_auto_create_error',
            'table_name' => 'tbl_val_schedules',
            'change_description' => 'Failed to auto-create new validation for equipment ' . $equip_id . ': ' . substr($e->getMessage(), 0, 200),
            'change_by' => $user_id,
            'unit_id' => $session_unit_id
        ]);
        
        return false;
    }
}

/**
 * Get the actual completion date from test schedules tracking
 * 
 * @param string $val_wf_id The validation workflow ID
 * @param string $provided_date Optional completion date if already known
 * @return string|false The completion date in Y-m-d format, or false if not found
 */
function getActualCompletionDate($val_wf_id, $provided_date = null) {
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
        ORDER BY test_conducted_date DESC
        LIMIT 1
    ", $val_wf_id);
}

?>