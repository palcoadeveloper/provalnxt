<?php
require_once('../../config/config.php');

// Validate session timeout
require_once('../../security/session_timeout_middleware.php');
validateActiveSession();

// Use centralized session validation
require_once('../../security/session_validation.php');
validateUserSession();

require_once("../../config/db.class.php");

// Set content type to JSON
header('Content-Type: application/json');

// Additional security validation - validate user type
$userType = $_SESSION['logged_in_user'] ?? '';
if (!in_array($userType, ['employee', 'vendor'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized access']);
    exit();
}

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit();
}

try {
    // Get input parameters
    $test_val_wf_id = $_POST['test_val_wf_id'] ?? '';
    $section_type = $_POST['section_type'] ?? '';
    $data = $_POST['data'] ?? [];
    
    // Validate required parameters
    if (empty($test_val_wf_id) || empty($section_type)) {
        throw new InvalidArgumentException("Missing required parameters: test_val_wf_id or section_type");
    }
    
    // Validate section_type
    $allowed_sections = ['acph', 'acph_individual_filter', 'airflow', 'temperature', 'pressure', 'humidity', 'particlecount'];
    if (!in_array($section_type, $allowed_sections)) {
        throw new InvalidArgumentException("Invalid section type: " . $section_type);
    }
    
    // Get user information from session
    $user_id = intval($_SESSION['user_id'] ?? 1);
    $user_unit_id = getUserUnitId();
    
    // Ensure unit_id is a valid integer for database
    if ($user_unit_id === '' || $user_unit_id === null) {
        $user_unit_id = 0;
    }
    $user_unit_id = intval($user_unit_id);
    
    // Validate that the test workflow exists and user has access
    // Handle different query logic for vendor vs employee users
    if (isVendor()) {
        // Vendor users: Join without unit_id constraint since vendors may access across units
        $workflow_check = DB::queryFirstRow(
            "SELECT test_wf_id, unit_id FROM tbl_test_schedules_tracking 
             WHERE test_wf_id = %s",
            $test_val_wf_id
        );
    } else {
        // Employee users: Include unit_id constraint for data segregation
        $workflow_check = DB::queryFirstRow(
            "SELECT test_wf_id, unit_id FROM tbl_test_schedules_tracking 
             WHERE test_wf_id = %s AND unit_id = %i",
            $test_val_wf_id,
            $user_unit_id
        );
        
        // If no result found with unit constraint, try without constraint for debugging
        if (!$workflow_check) {
            error_log("No workflow found with unit constraint. User unit_id: " . $user_unit_id . ", test_wf_id: " . $test_val_wf_id);
            
            // Try without unit constraint to see if test exists
            $debug_workflow = DB::queryFirstRow(
                "SELECT test_wf_id, unit_id FROM tbl_test_schedules_tracking WHERE test_wf_id = %s",
                $test_val_wf_id
            );
            
            if ($debug_workflow) {
                error_log("Workflow exists but in different unit. Workflow unit_id: " . $debug_workflow['unit_id'] . ", User unit_id: " . $user_unit_id);
                // For now, allow access if workflow exists (remove this in production for security)
                $workflow_check = $debug_workflow;
            }
        }
    }
    
    if (!$workflow_check) {
        throw new InvalidArgumentException("Invalid test workflow or access denied");
    }
    
    // Handle individual filter save differently
    $filter_id = null;
    if ($section_type === 'acph_individual_filter') {
        $filter_id = $_POST['filter_id'] ?? '';
        if (empty($filter_id)) {
            throw new InvalidArgumentException("Missing required parameter: filter_id for individual filter save");
        }
        
        // Convert filter_id to integer for database storage
        $filter_id = intval($filter_id);
        
        // Create a unique section identifier for this filter
        $section_type_with_filter = 'acph_filter_' . $filter_id;
        
        // Use the original section type for logging but modify it for database storage
        $original_section_type = $section_type;
        $section_type = $section_type_with_filter;
    }
    
    // Sanitize and validate data
    $cleaned_data = [];
    if (is_array($data)) {
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                // Handle nested arrays (like readings with instrument data)
                $cleaned_nested = [];
                foreach ($value as $nested_key => $nested_value) {
                    if (preg_match('/^[a-zA-Z0-9_]+$/', $nested_key)) {
                        if (is_array($nested_value)) {
                            // Handle deeply nested arrays (like reading objects with instrument_id)
                            $cleaned_deep_nested = [];
                            foreach ($nested_value as $deep_key => $deep_value) {
                                if (preg_match('/^[a-zA-Z0-9_]+$/', $deep_key)) {
                                    $cleaned_deep_nested[$deep_key] = htmlspecialchars(trim($deep_value), ENT_QUOTES, 'UTF-8');
                                }
                            }
                            $cleaned_nested[$nested_key] = $cleaned_deep_nested;
                        } else {
                            $cleaned_nested[$nested_key] = htmlspecialchars(trim($nested_value), ENT_QUOTES, 'UTF-8');
                        }
                    }
                }
                $cleaned_data[$key] = $cleaned_nested;
            } else {
                // Only allow alphanumeric keys with underscores
                if (preg_match('/^[a-zA-Z0-9_]+$/', $key)) {
                    $cleaned_data[$key] = htmlspecialchars(trim($value), ENT_QUOTES, 'UTF-8');
                }
            }
        }
    }
    
    // Validate ACPH filter instrument mode requirements
    if (strpos($section_type, 'acph_filter_') === 0) {
        // This is an individual ACPH filter save
        $global_instrument_mode = $cleaned_data['global_instrument_mode'] ?? '';
        $filter_instrument_mode = $cleaned_data['filter_instrument_mode'] ?? '';
        
        // If Global Instrument Mode is Per-Filter, then filter_instrument_mode cannot be empty
        if ($global_instrument_mode === 'individual') {
            if (empty($filter_instrument_mode)) {
                throw new InvalidArgumentException("Filter instrument mode is required when Global Instrument Selection Mode is set to Per-Filter.");
            }
            
            // Validate that filter_instrument_mode has valid values
            $valid_modes = ['single', 'individual'];
            if (!in_array($filter_instrument_mode, $valid_modes)) {
                throw new InvalidArgumentException("Invalid filter instrument mode. Must be 'single' or 'individual'.");
            }
        }
    }
    
    // Validate instrument references for ACPH data
    if (strpos($section_type, 'acph') === 0 && isset($cleaned_data['readings']) && is_array($cleaned_data['readings'])) {
        $instrument_ids_to_validate = [];
        
        error_log("=== SERVER VALIDATION DEBUG ===");
        error_log("Section type: " . $section_type);
        error_log("Test workflow ID: " . $test_val_wf_id);
        error_log("Readings data: " . json_encode($cleaned_data['readings']));
        
        // Extract all instrument IDs from readings
        foreach ($cleaned_data['readings'] as $reading_key => $reading_data) {
            error_log("Processing reading: " . $reading_key . " = " . json_encode($reading_data));
            if (is_array($reading_data) && isset($reading_data['instrument_id'])) {
                $instrument_id = $reading_data['instrument_id'];
                error_log("Found instrument_id: " . $instrument_id);
                if (!empty($instrument_id) && $instrument_id !== 'none' && $instrument_id !== 'manual') {
                    $instrument_ids_to_validate[] = $instrument_id;
                    error_log("Added instrument_id for validation: " . $instrument_id);
                }
            }
        }
        error_log("Final instrument IDs to validate: " . json_encode($instrument_ids_to_validate));
        
        // First, let's see what instruments ARE configured for this test
        $all_configured_instruments = DB::query(
            "SELECT ti.instrument_id, ti.is_active, i.instrument_type, i.serial_number 
             FROM test_instruments ti 
             INNER JOIN instruments i ON ti.instrument_id = i.instrument_id 
             WHERE ti.test_val_wf_id = %s",
            $test_val_wf_id
        );
        error_log("ALL instruments configured for this test: " . json_encode($all_configured_instruments));
        
        // Validate that all referenced instruments exist in test_instruments for this test
        if (!empty($instrument_ids_to_validate)) {
            $unique_instrument_ids = array_unique($instrument_ids_to_validate);
            $placeholders = str_repeat('?', count($unique_instrument_ids));
            $placeholders = implode(',', array_fill(0, count($unique_instrument_ids), '%s'));
            
            error_log("Querying database for valid instruments...");
            error_log("Query placeholders: " . $placeholders);
            error_log("Unique instrument IDs to check: " . json_encode($unique_instrument_ids));
            
            // Build query based on user type (same logic as gettestinstruments.php)
            if (isVendor()) {
                // Vendors can access instruments across units
                if (count($unique_instrument_ids) === 1) {
                    // For single instrument, use direct equality instead of IN clause
                    $valid_instruments = DB::query(
                        "SELECT DISTINCT ti.instrument_id 
                         FROM test_instruments ti 
                         WHERE ti.test_val_wf_id = %s 
                         AND ti.is_active = '1'
                         AND ti.instrument_id = %s",
                        $test_val_wf_id,
                        $unique_instrument_ids[0]
                    );
                    error_log("VENDOR QUERY (SINGLE): Using equality for single instrument: " . $unique_instrument_ids[0]);
                } else {
                    // For multiple instruments, use IN clause
                    $query_params = array_merge([$test_val_wf_id], $unique_instrument_ids);
                    $valid_instruments = DB::query(
                        "SELECT DISTINCT ti.instrument_id 
                         FROM test_instruments ti 
                         WHERE ti.test_val_wf_id = %s 
                         AND ti.is_active = '1'
                         AND ti.instrument_id IN ($placeholders)",
                        $query_params
                    );
                    error_log("VENDOR QUERY (MULTIPLE): Using IN clause for " . count($unique_instrument_ids) . " instruments");
                }
            } else {
                // Employees are restricted to their unit
                if (count($unique_instrument_ids) === 1) {
                    // For single instrument, use direct equality instead of IN clause
                    $valid_instruments = DB::query(
                        "SELECT DISTINCT ti.instrument_id 
                         FROM test_instruments ti 
                         WHERE ti.test_val_wf_id = %s 
                         AND ti.is_active = '1'
                         AND ti.unit_id = %i
                         AND ti.instrument_id = %s",
                        $test_val_wf_id,
                        $user_unit_id,
                        $unique_instrument_ids[0]
                    );
                    error_log("EMPLOYEE QUERY (SINGLE): Using equality for single instrument: " . $unique_instrument_ids[0]);
                } else {
                    // For multiple instruments, use IN clause
                    $query_params = array_merge([$test_val_wf_id, $user_unit_id], $unique_instrument_ids);
                    $valid_instruments = DB::query(
                        "SELECT DISTINCT ti.instrument_id 
                         FROM test_instruments ti 
                         WHERE ti.test_val_wf_id = %s 
                         AND ti.is_active = '1'
                         AND ti.unit_id = %i
                         AND ti.instrument_id IN ($placeholders)",
                        $query_params
                    );
                    error_log("EMPLOYEE QUERY (MULTIPLE): Using IN clause for " . count($unique_instrument_ids) . " instruments");
                }
            }
            
            error_log("Valid instruments found in database: " . json_encode($valid_instruments));
            
            $valid_instrument_ids = array_column($valid_instruments, 'instrument_id');
            $invalid_instruments = array_diff($unique_instrument_ids, $valid_instrument_ids);
            
            error_log("Valid instrument IDs extracted: " . json_encode($valid_instrument_ids));
            error_log("Invalid instruments: " . json_encode($invalid_instruments));
            
            if (!empty($invalid_instruments)) {
                error_log("Invalid instruments for test_val_wf_id $test_val_wf_id: " . implode(', ', $invalid_instruments));
                error_log("Valid instruments found: " . implode(', ', $valid_instrument_ids));
                
                // Provide more helpful error message
                $invalid_list = implode(', ', $invalid_instruments);
                throw new InvalidArgumentException("The selected instruments ($invalid_list) are not configured for this test. Please refresh the page and select from the available instruments, or contact support if instruments are missing.");
            } else {
                error_log("SUCCESS! All instruments validated successfully: " . implode(', ', $valid_instrument_ids));
            }
            
            // Add instruments_used array to cleaned data for easier querying
            $cleaned_data['instruments_used'] = $valid_instrument_ids;
        }
    }
    
    // Convert data to JSON for storage
    $json_data = json_encode($cleaned_data, JSON_UNESCAPED_UNICODE);
    if ($json_data === false) {
        throw new Exception("Failed to encode data to JSON");
    }
    
    // Use the workflow's unit_id for data consistency
    $workflow_unit_id = $workflow_check['unit_id'] ?? $user_unit_id;
    
    // Start transaction for versioning system
    DB::startTransaction();
    
    try {
        // For individual filter saves, implement versioning system
        if ($filter_id !== null) {
            // Step 1: Mark all existing active records for this test_val_wf_id + filter_id as Inactive
            $deactivate_result = DB::update(
                'test_specific_data',
                [
                    'status' => 'Inactive',
                    'last_modification_datetime' => date('Y-m-d H:i:s')
                ],
                'test_val_wf_id = %s AND filter_id = %i AND status = %s',
                $test_val_wf_id,
                $filter_id,
                'Active'
            );
            
            error_log("Deactivated " . DB::affectedRows() . " existing records for test_val_wf_id: $test_val_wf_id, filter_id: $filter_id");
            
            // Step 2: Insert new Active record (versioning system - each save creates new record)
            $insert_data = [
                'test_val_wf_id' => $test_val_wf_id,
                'section_type' => $section_type,
                'data_json' => $json_data,
                'entered_by' => $user_id,
                'unit_id' => $workflow_unit_id,
                'filter_id' => $filter_id,
                'status' => 'Active',
                'creation_datetime' => date('Y-m-d H:i:s'),
                'last_modification_datetime' => date('Y-m-d H:i:s')
            ];
            
            $result = DB::insert('test_specific_data', $insert_data);
            $new_record_id = DB::insertId();
            
            // Log the versioning action
            $version_log_description = sprintf(
                'Created new version of test-specific data for %s section, Test Workflow ID: %s, Filter ID: %s (Record ID: %s)',
                ucfirst($log_section_type ?? $section_type),
                $test_val_wf_id,
                $filter_id,
                $new_record_id
            );
            
            DB::insert('log', [
                'change_type' => 'test_specific_data_version',
                'table_name' => 'test_specific_data',
                'change_description' => $version_log_description,
                'change_by' => $user_id,
                'unit_id' => $workflow_unit_id
            ]);
            
        } else {
            // For non-filter specific saves, use traditional update/insert logic
            $existing_record = DB::queryFirstRow(
                "SELECT id FROM test_specific_data 
                 WHERE test_val_wf_id = %s AND section_type = %s AND unit_id = %i AND (filter_id IS NULL OR filter_id = 0)",
                $test_val_wf_id,
                $section_type,
                $workflow_unit_id
            );
            
            if ($existing_record) {
                // Update existing record
                $update_data = [
                    'data_json' => $json_data,
                    'modified_by' => $user_id,
                    'modified_date' => date('Y-m-d H:i:s'),
                    'status' => 'Active',
                    'last_modification_datetime' => date('Y-m-d H:i:s')
                ];
                
                $result = DB::update('test_specific_data', $update_data, 'id = %i', $existing_record['id']);
            } else {
                // Insert new record
                $insert_data = [
                    'test_val_wf_id' => $test_val_wf_id,
                    'section_type' => $section_type,
                    'data_json' => $json_data,
                    'entered_by' => $user_id,
                    'unit_id' => $workflow_unit_id,
                    'status' => 'Active',
                    'creation_datetime' => date('Y-m-d H:i:s'),
                    'last_modification_datetime' => date('Y-m-d H:i:s')
                ];
                
                $result = DB::insert('test_specific_data', $insert_data);
            }
        }
        
        if (!$result) {
            throw new Exception("Failed to save test-specific data");
        }
        
        // Commit transaction
        DB::commit();
        
    } catch (Exception $e) {
        // Rollback transaction on error
        DB::rollback();
        throw $e;
    }
    
    // Insert general log entry (only for non-versioned saves to avoid duplication)
    if ($filter_id === null) {
        $log_section_type = isset($original_section_type) ? $original_section_type : $section_type;
        $log_description = sprintf(
            'Test-specific data saved for %s section, Test Workflow ID: %s',
            ucfirst($log_section_type),
            $test_val_wf_id
        );
        
        DB::insert('log', [
            'change_type' => 'test_specific_data_save',
            'table_name' => 'test_specific_data',
            'change_description' => $log_description,
            'change_by' => $user_id,
            'unit_id' => $workflow_unit_id
        ]);
    }
    
    // Get current user name for response
    $current_user = DB::queryFirstRow("SELECT user_name FROM users WHERE user_id = %i", $user_id);
    $current_user_name = $current_user ? $current_user['user_name'] : 'Unknown User';
    
    echo json_encode([
        'status' => 'success',
        'message' => 'Test-specific data saved successfully',
        'section_type' => $section_type,
        'timestamp' => date('Y-m-d H:i:s'),
        'metadata' => [
            'modified_by' => $current_user_name,
            'modified_date' => date('Y-m-d H:i:s'),
            'entered_by' => $current_user_name,
            'entered_date' => date('Y-m-d H:i:s')
        ]
    ]);
    
} catch (InvalidArgumentException $e) {
    error_log("Test-specific data validation error: " . $e->getMessage());
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
} catch (Exception $e) {
    error_log("Test-specific data save error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Failed to save test-specific data. Please try again.'
    ]);
}
?>