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

// Only allow GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit();
}

try {
    // Get input parameters
    $test_val_wf_id = $_GET['test_val_wf_id'] ?? '';
    $section_type = $_GET['section_type'] ?? '';
    
    // Validate required parameters
    if (empty($test_val_wf_id)) {
        throw new InvalidArgumentException("Missing required parameter: test_val_wf_id");
    }
    
    // Get user information from session
    $user_unit_id = getUserUnitId();
    
    // Ensure unit_id is a valid integer for database
    if ($user_unit_id === '' || $user_unit_id === null) {
        $user_unit_id = 0;
    }
    $user_unit_id = intval($user_unit_id);
    
    // Build query based on whether section_type is specified
    if (!empty($section_type)) {
        // Validate section_type - allow individual filter sections
        $allowed_sections = ['acph', 'airflow', 'temperature', 'pressure', 'humidity', 'particlecount'];
        $is_individual_filter = strpos($section_type, 'acph_filter_') === 0;
        
        if (!in_array($section_type, $allowed_sections) && !$is_individual_filter) {
            throw new InvalidArgumentException("Invalid section type: " . $section_type);
        }
        
        // Handle different query logic for vendor vs employee users
        if (isVendor()) {
            // Vendor users: Join without unit_id constraint since vendors may access across units
            $query = "
                SELECT tsd.section_type, tsd.data_json, tsd.entered_date, tsd.modified_date, 
                       tsd.filter_id, tsd.creation_datetime, tsd.last_modification_datetime,
                       u1.user_name as entered_by_name, u2.user_name as modified_by_name
                FROM test_specific_data tsd
                LEFT JOIN users u1 ON tsd.entered_by = u1.user_id
                LEFT JOIN users u2 ON tsd.modified_by = u2.user_id
                WHERE tsd.test_val_wf_id = %s 
                AND tsd.section_type = %s
                AND tsd.status = 'Active'
            ";
            $data = DB::queryFirstRow($query, $test_val_wf_id, $section_type);
        } else {
            // Engineering and QA users can access data across units for review
            if ($_SESSION['department_id'] == 1 || $_SESSION['department_id'] == 8) {
                $query = "
                    SELECT tsd.section_type, tsd.data_json, tsd.entered_date, tsd.modified_date,
                           tsd.filter_id, tsd.creation_datetime, tsd.last_modification_datetime,
                           u1.user_name as entered_by_name, u2.user_name as modified_by_name
                    FROM test_specific_data tsd
                    LEFT JOIN users u1 ON tsd.entered_by = u1.user_id
                    LEFT JOIN users u2 ON tsd.modified_by = u2.user_id
                    WHERE tsd.test_val_wf_id = %s 
                    AND tsd.section_type = %s
                    AND tsd.status = 'Active'
                ";
                $data = DB::queryFirstRow($query, $test_val_wf_id, $section_type);
            } else {
                // Other employees: Include unit_id constraint for data segregation
                $query = "
                    SELECT tsd.section_type, tsd.data_json, tsd.entered_date, tsd.modified_date,
                           tsd.filter_id, tsd.creation_datetime, tsd.last_modification_datetime,
                           u1.user_name as entered_by_name, u2.user_name as modified_by_name
                    FROM test_specific_data tsd
                    LEFT JOIN users u1 ON tsd.entered_by = u1.user_id
                    LEFT JOIN users u2 ON tsd.modified_by = u2.user_id
                    WHERE tsd.test_val_wf_id = %s 
                    AND tsd.section_type = %s
                    AND tsd.unit_id = %i
                    AND tsd.status = 'Active'
                ";
                $data = DB::queryFirstRow($query, $test_val_wf_id, $section_type, $user_unit_id);
            }
            
            // If no result found with unit constraint, try without constraint for debugging
            if (!$data) {
                error_log("No active data found with unit constraint. User unit_id: " . $user_unit_id . ", test_wf_id: " . $test_val_wf_id . ", section: " . $section_type);
                
                // Try without unit constraint to see if data exists
                $debug_query = "
                    SELECT tsd.section_type, tsd.data_json, tsd.entered_date, tsd.modified_date, tsd.unit_id,
                           tsd.filter_id, tsd.creation_datetime, tsd.last_modification_datetime, tsd.status,
                           u1.user_name as entered_by_name, u2.user_name as modified_by_name
                    FROM test_specific_data tsd
                    LEFT JOIN users u1 ON tsd.entered_by = u1.user_id
                    LEFT JOIN users u2 ON tsd.modified_by = u2.user_id
                    WHERE tsd.test_val_wf_id = %s 
                    AND tsd.section_type = %s
                    AND tsd.status = 'Active'
                ";
                $debug_data = DB::queryFirstRow($debug_query, $test_val_wf_id, $section_type);
                
                if ($debug_data) {
                    error_log("Active data exists but in different unit. Data unit_id: " . $debug_data['unit_id'] . ", User unit_id: " . $user_unit_id);
                    // For now, allow access if data exists (remove this in production for security)
                    $data = $debug_data;
                }
            }
        }
        
    } else {
        // Get all sections for this test workflow
        // Handle different query logic for vendor vs employee users
        if (isVendor()) {
            // Vendor users: Join without unit_id constraint since vendors may access across units
            $query = "
                SELECT tsd.section_type, tsd.data_json, tsd.entered_date, tsd.modified_date,
                       tsd.filter_id, tsd.creation_datetime, tsd.last_modification_datetime,
                       u1.user_name as entered_by_name, u2.user_name as modified_by_name
                FROM test_specific_data tsd
                LEFT JOIN users u1 ON tsd.entered_by = u1.user_id
                LEFT JOIN users u2 ON tsd.modified_by = u2.user_id
                WHERE tsd.test_val_wf_id = %s 
                AND tsd.status = 'Active'
                ORDER BY tsd.section_type
            ";
            $data = DB::query($query, $test_val_wf_id);
        } else {
            // Employee users: Include unit_id constraint for data segregation
            $query = "
                SELECT tsd.section_type, tsd.data_json, tsd.entered_date, tsd.modified_date,
                       tsd.filter_id, tsd.creation_datetime, tsd.last_modification_datetime,
                       u1.user_name as entered_by_name, u2.user_name as modified_by_name
                FROM test_specific_data tsd
                LEFT JOIN users u1 ON tsd.entered_by = u1.user_id
                LEFT JOIN users u2 ON tsd.modified_by = u2.user_id
                WHERE tsd.test_val_wf_id = %s 
                AND tsd.unit_id = %i
                AND tsd.status = 'Active'
                ORDER BY tsd.section_type
            ";
            $data = DB::query($query, $test_val_wf_id, $user_unit_id);
        }
    }
    
    if (!empty($section_type)) {
        // Single section response
        if ($data) {
            $decoded_data = json_decode($data['data_json'], true);
            if ($decoded_data === null && json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception("Failed to decode JSON data");
            }
            
            // Enrich instrument data for ACPH sections
            if (strpos($section_type, 'acph') === 0 && isset($decoded_data['readings']) && is_array($decoded_data['readings'])) {
                $instrument_ids = [];
                
                // Collect all instrument IDs from readings
                foreach ($decoded_data['readings'] as $reading_data) {
                    if (is_array($reading_data) && isset($reading_data['instrument_id'])) {
                        $instrument_ids[] = $reading_data['instrument_id'];
                    }
                }
                
                // Get instrument details if we have IDs
                if (!empty($instrument_ids)) {
                    $unique_ids = array_unique(array_filter($instrument_ids));
                    if (!empty($unique_ids)) {
                        $placeholders = implode(',', array_fill(0, count($unique_ids), '%s'));
                        $instruments = DB::query(
                            "SELECT ti.instrument_id, i.instrument_type, i.serial_number,
                                    CASE 
                                        WHEN i.calibration_due_on < NOW() THEN 'Expired'
                                        WHEN i.calibration_due_on < DATE_ADD(NOW(), INTERVAL 30 DAY) THEN 'Due Soon' 
                                        ELSE 'Valid'
                                    END as calibration_status
                             FROM test_instruments ti
                             INNER JOIN instruments i ON ti.instrument_id = i.instrument_id
                             WHERE ti.test_val_wf_id = %s 
                             AND ti.instrument_id IN ($placeholders)
                             AND ti.is_active = 1",
                            array_merge([$test_val_wf_id], $unique_ids)
                        );
                        
                        // Create instrument lookup for frontend
                        $instrument_details = [];
                        foreach ($instruments as $instrument) {
                            $instrument_details[$instrument['instrument_id']] = [
                                'type' => $instrument['instrument_type'],
                                'serial' => $instrument['serial_number'],
                                'status' => $instrument['calibration_status']
                            ];
                        }
                        
                        $decoded_data['instrument_details'] = $instrument_details;
                    }
                }
            }
            
            echo json_encode([
                'status' => 'success',
                'section_type' => $data['section_type'],
                'data' => $decoded_data,
                'metadata' => [
                    'entered_date' => $data['entered_date'],
                    'modified_date' => $data['modified_date'],
                    'entered_by' => $data['entered_by_name'],
                    'modified_by' => $data['modified_by_name'],
                    'filter_id' => $data['filter_id'],
                    'creation_datetime' => $data['creation_datetime'],
                    'last_modification_datetime' => $data['last_modification_datetime']
                ]
            ]);
        } else {
            echo json_encode([
                'status' => 'success',
                'section_type' => $section_type,
                'data' => [],
                'message' => 'No data found for this section'
            ]);
        }
    } else {
        // Multiple sections response
        $response_data = [];
        if ($data) {
            foreach ($data as $row) {
                $decoded_data = json_decode($row['data_json'], true);
                if ($decoded_data === null && json_last_error() !== JSON_ERROR_NONE) {
                    error_log("Failed to decode JSON for section: " . $row['section_type']);
                    $decoded_data = [];
                }
                
                $response_data[$row['section_type']] = [
                    'data' => $decoded_data,
                    'metadata' => [
                        'entered_date' => $row['entered_date'],
                        'modified_date' => $row['modified_date'],
                        'entered_by' => $row['entered_by_name'],
                        'modified_by' => $row['modified_by_name'],
                        'filter_id' => $row['filter_id'],
                        'creation_datetime' => $row['creation_datetime'],
                        'last_modification_datetime' => $row['last_modification_datetime']
                    ]
                ];
            }
        }
        
        echo json_encode([
            'status' => 'success',
            'sections' => $response_data,
            'count' => count($response_data)
        ]);
    }
    
} catch (InvalidArgumentException $e) {
    error_log("Test-specific data retrieval validation error: " . $e->getMessage());
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
} catch (Exception $e) {
    error_log("Test-specific data retrieval error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Failed to retrieve test-specific data. Please try again.'
    ]);
}
?>