<?php
require_once(dirname(__FILE__) . '/../../config/config.php');

// Validate session timeout
require_once(dirname(__FILE__) . '/../../security/session_timeout_middleware.php');
validateActiveSession();

// Use centralized session validation
require_once(dirname(__FILE__) . '/../../security/session_validation.php');
validateUserSession();

require_once(dirname(__FILE__) . '/../../config/db.class.php');
require_once(dirname(__FILE__) . '/../../security/secure_query_wrapper.php');

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
    // Validate CSRF token
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (!$csrf_token || $csrf_token !== $_SESSION['csrf_token']) {
        throw new InvalidArgumentException("Invalid security token");
    }
    
    // Get input parameters
    $mapping_id = $_POST['mapping_id'] ?? '';
    $test_val_wf_id = $_POST['test_val_wf_id'] ?? '';
    
    // Validate required parameters
    if (empty($mapping_id) || empty($test_val_wf_id)) {
        throw new InvalidArgumentException("Missing required parameters: mapping_id or test_val_wf_id");
    }
    
    // Get user information from session
    $user_id = intval($_SESSION['user_id'] ?? 1);
    $user_unit_id = getUserUnitId();
    
    // Ensure unit_id is a valid integer for database
    if ($user_unit_id === '' || $user_unit_id === null) {
        $user_unit_id = 0;
    }
    $user_unit_id = intval($user_unit_id);
    
    // Verify the mapping exists and get instrument details for logging
    $mapping_check = DB::queryFirstRow(
        "SELECT ti.mapping_id, ti.instrument_id, ti.test_val_wf_id, i.instrument_type 
         FROM test_instruments ti 
         LEFT JOIN instruments i ON ti.instrument_id = i.instrument_id 
         WHERE ti.mapping_id = %i 
         AND ti.test_val_wf_id = %s 
         AND ti.is_active = 1",
        $mapping_id,
        $test_val_wf_id
    );
    
    if (!$mapping_check) {
        throw new InvalidArgumentException("Invalid mapping or instrument already removed");
    }
    
    // Check if instrument is being used in any active test data
    $instrument_id = $mapping_check['instrument_id'];
    $usage_check = DB::query(
        "SELECT tsd.section_type, tsd.data_json 
         FROM test_specific_data tsd 
         WHERE tsd.test_val_wf_id = %s 
         AND tsd.status = 'Active' 
         AND JSON_SEARCH(tsd.data_json, 'one', %s, NULL, '$.readings.*.instrument_id') IS NOT NULL",
        $test_val_wf_id,
        $instrument_id
    );
    
    if (!empty($usage_check)) {
        // Extract section names and create user-friendly filter names
        $sections_using_instrument = [];
        $section_display_names = [];
        
        foreach ($usage_check as $usage) {
            $section_type = $usage['section_type'];
            $sections_using_instrument[] = $section_type;
            
            // Convert section type to user-friendly name
            if (preg_match('/acph_filter_(\d+)/', $section_type, $matches)) {
                $filter_number = $matches[1];
                $section_display_names[] = "Filter $filter_number";
            } else {
                $section_display_names[] = ucwords(str_replace('_', ' ', $section_type));
            }
        }
        
        $usage_count = count($sections_using_instrument);
        $sections_list = implode(', ', array_unique($section_display_names));
        
        // Log the blocked removal attempt
        DB::insert('log', [
            'change_type' => 'test_instrument_remove_blocked',
            'table_name' => 'test_instruments',
            'change_description' => sprintf(
                'Blocked removal of instrument %s (%s) from test workflow %s - instrument is in use in %d sections: %s',
                $mapping_check['instrument_type'] ?? 'Unknown',
                $instrument_id,
                $test_val_wf_id,
                $usage_count,
                implode(', ', $sections_using_instrument)
            ),
            'change_by' => $user_id,
            'unit_id' => $user_unit_id
        ]);
        
        // Return detailed error response
        http_response_code(400);
        echo json_encode([
            'status' => 'error',
            'error_type' => 'instrument_in_use',
            'message' => "Cannot remove instrument $instrument_id. It is currently being used in $usage_count filter section(s). Please update the filter data to use different instruments before removing this instrument.",
            'sections_affected' => $sections_using_instrument,
            'usage_count' => $usage_count,
            'instrument_id' => $instrument_id
        ]);
        exit();
    }
    
    // Soft delete the mapping (set is_active to 0)
    $result = DB::update('test_instruments', [
        'is_active' => 0
    ], 'mapping_id = %i', $mapping_id);
    
    if (!$result) {
        throw new Exception("Failed to remove instrument from test");
    }
    
    // Insert log entry
    DB::insert('log', [
        'change_type' => 'test_instrument_remove',
        'table_name' => 'test_instruments',
        'change_description' => sprintf(
            'Removed instrument %s (%s) from test workflow %s',
            $mapping_check['instrument_type'] ?? 'Unknown',
            $mapping_check['instrument_id'],
            $test_val_wf_id
        ),
        'change_by' => $user_id,
        'unit_id' => $user_unit_id
    ]);
    
    // Generate new CSRF token
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    
    echo json_encode([
        'status' => 'success',
        'message' => 'Instrument removed successfully',
        'csrf_token' => $_SESSION['csrf_token']
    ]);
    
} catch (InvalidArgumentException $e) {
    error_log("Validation error: " . $e->getMessage());
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
} catch (Exception $e) {
    error_log("Database error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Failed to remove instrument. Please try again. Error: ' . $e->getMessage()
    ]);
}
?>