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
    
    // Get ACPH specific data from test_specific_data
    // Handle different query logic for vendor vs employee users
    if (isVendor()) {
        $query = "
            SELECT tsd.section_type, tsd.data_json, tsd.entered_date, tsd.modified_date,
                   u1.user_name as entered_by_name, u2.user_name as modified_by_name
            FROM test_specific_data tsd
            LEFT JOIN users u1 ON tsd.entered_by = u1.user_id
            LEFT JOIN users u2 ON tsd.modified_by = u2.user_id
            WHERE tsd.test_val_wf_id = %s 
            AND tsd.section_type = 'acph'
        ";
        $data = DB::queryFirstRow($query, $test_val_wf_id);
    } else {
        $query = "
            SELECT tsd.section_type, tsd.data_json, tsd.entered_date, tsd.modified_date,
                   u1.user_name as entered_by_name, u2.user_name as modified_by_name
            FROM test_specific_data tsd
            LEFT JOIN users u1 ON tsd.entered_by = u1.user_id
            LEFT JOIN users u2 ON tsd.modified_by = u2.user_id
            WHERE tsd.test_val_wf_id = %s 
            AND tsd.section_type = 'acph'
            AND tsd.unit_id = %i
        ";
        $data = DB::queryFirstRow($query, $test_val_wf_id, $user_unit_id);
    }
    
    // Get filter structure for this test
    $filters_response = [];
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, BASE_URL . 'core/data/get/getacphfilters.php?test_val_wf_id=' . urlencode($test_val_wf_id));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Cookie: ' . $_SERVER['HTTP_COOKIE'] ?? ''
    ]);
    $filters_json = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code === 200 && $filters_json) {
        $filters_response = json_decode($filters_json, true);
    }
    
    if ($data) {
        $decoded_data = json_decode($data['data_json'], true);
        if ($decoded_data === null && json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception("Failed to decode JSON data");
        }
        
        // Merge existing data with current filter structure
        $response_data = [
            'status' => 'success',
            'section_type' => 'acph',
            'data' => $decoded_data,
            'filter_structure' => $filters_response['filter_groups'] ?? [],
            'equipment_info' => $filters_response['equipment_info'] ?? [],
            'metadata' => [
                'entered_date' => $data['entered_date'],
                'modified_date' => $data['modified_date'],
                'entered_by' => $data['entered_by_name'],
                'modified_by' => $data['modified_by_name']
            ]
        ];
    } else {
        // No existing data, return empty structure with filter information
        $response_data = [
            'status' => 'success',
            'section_type' => 'acph',
            'data' => [
                'room_volume' => '',
                'filters' => [],
                'filter_group_totals' => [],
                'grand_total_supply_cfm' => '',
                'calculated_acph' => ''
            ],
            'filter_structure' => $filters_response['filter_groups'] ?? [],
            'equipment_info' => $filters_response['equipment_info'] ?? [],
            'message' => 'No existing ACPH data found'
        ];
    }
    
    echo json_encode($response_data);
    
} catch (InvalidArgumentException $e) {
    error_log("ACPH data retrieval validation error: " . $e->getMessage());
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
} catch (Exception $e) {
    error_log("ACPH data retrieval error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Failed to retrieve ACPH data. Please try again.'
    ]);
}
?>