<?php
// Load configuration first
require_once(__DIR__ . '/../../config/config.php');

// Validate session timeout
require_once('../../security/session_timeout_middleware.php');
validateActiveSession();

require_once '../../config/db.class.php';
date_default_timezone_set("Asia/Kolkata");

// Get parameters
$unit_id = intval($_GET['unitid'] ?? 0);
$schedule_year = intval($_GET['schyear'] ?? 0);

// Validate parameters
if ($unit_id <= 0 || $schedule_year <= 0) {
    echo "Invalid parameters provided.";
    exit();
}

try {
    // Check 1: Verify if a schedule request already exists for this unit and year
    $existing_request = DB::queryFirstRow(
        "SELECT schedule_id, schedule_request_status FROM tbl_val_wf_schedule_requests 
         WHERE unit_id = %d AND schedule_year = %d", 
        $unit_id, $schedule_year
    );
    
    if ($existing_request) {
        echo "A schedule request for this unit and year already exists (Schedule ID: " . $existing_request['schedule_id'] . ").";
        exit();
    }
    
    // Check 2: For non-first-time generation, ensure previous year exists with status 3
    $previous_year = $schedule_year - 1;
    
    // Check if any schedule exists for this unit (to determine if this is first-time generation)
    $any_existing_schedule = DB::queryFirstRow(
        "SELECT schedule_id FROM tbl_val_wf_schedule_requests WHERE unit_id = %d LIMIT 1", 
        $unit_id
    );
    
    if ($any_existing_schedule) {
        // Not first-time generation, check if previous year exists with status 3
        $previous_year_schedule = DB::queryFirstRow(
            "SELECT schedule_id, schedule_request_status FROM tbl_val_wf_schedule_requests 
             WHERE unit_id = %d AND schedule_year = %d", 
            $unit_id, $previous_year
        );
        
        if (!$previous_year_schedule) {
            echo "Cannot generate schedule for year $schedule_year. No schedule exists for the previous year ($previous_year) for this unit.";
            exit();
        }
        
        if ($previous_year_schedule['schedule_request_status'] != '3') {
            echo "Cannot generate schedule for year $schedule_year. The schedule for the previous year ($previous_year) must be completed (status 3) before generating a new one. Current status: " . $previous_year_schedule['schedule_request_status'];
            exit();
        }
    }
    
    // All validations passed
    echo "valid";
    
} catch (Exception $e) {
    error_log("Database error in validateschedulerequest.php: " . $e->getMessage());
    echo "Database error occurred while validating schedule request.";
}
?>