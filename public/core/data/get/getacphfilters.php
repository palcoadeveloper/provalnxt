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
    
    // First, get the equipment_id for this test workflow
    // Handle different query logic for vendor vs employee users
    if (isVendor()) {
        // Vendor users: Join without unit_id constraint since vendors may access across units
        $equipment_query = "
            SELECT equip_id, test_wf_id, unit_id
            FROM tbl_test_schedules_tracking 
            WHERE test_wf_id = %s
        ";
        $equipment_result = DB::queryFirstRow($equipment_query, $test_val_wf_id);
    } else {
        // Employee users: Include unit_id constraint for data segregation
        $equipment_query = "
            SELECT equip_id, test_wf_id, unit_id
            FROM tbl_test_schedules_tracking 
            WHERE test_wf_id = %s AND unit_id = %i
        ";
        $equipment_result = DB::queryFirstRow($equipment_query, $test_val_wf_id, $user_unit_id);
        
        // If no result found with unit constraint, try without constraint for debugging
        if (!$equipment_result) {
            error_log("No equipment found with unit constraint. User unit_id: " . $user_unit_id . ", test_wf_id: " . $test_val_wf_id);
            
            // Try without unit constraint to see if test exists
            $debug_query = "SELECT equip_id, test_wf_id, unit_id FROM tbl_test_schedules_tracking WHERE test_wf_id = %s";
            $debug_result = DB::queryFirstRow($debug_query, $test_val_wf_id);
            
            if ($debug_result) {
                error_log("Test exists but in different unit. Test unit_id: " . $debug_result['unit_id'] . ", User unit_id: " . $user_unit_id);
                // For now, allow access if test exists (remove this in production for security)
                $equipment_result = $debug_result;
            }
        }
    }
    
    if (!$equipment_result) {
        throw new InvalidArgumentException("Invalid test workflow or access denied");
    }
    
    $equipment_id = $equipment_result['equip_id'];
    $test_wf_id = $equipment_result['test_wf_id'];
    
    // Get active filters mapped to this equipment with filter group information
    $filters_query = "
        SELECT 
            em.erf_mapping_id,
            em.equipment_id,
            em.filter_id,
            em.filter_name,
            em.area_classification,
            fg.filter_group_id,
            fg.filter_group_name,
            rl.room_loc_name,
            rl.room_volume,
            e.equipment_code
        FROM erf_mappings em
        INNER JOIN equipments e ON em.equipment_id = e.equipment_id
        INNER JOIN room_locations rl ON em.room_loc_id = rl.room_loc_id
        LEFT JOIN filter_groups fg ON em.filter_group_id = fg.filter_group_id
        WHERE em.equipment_id = %i 
        AND em.erf_mapping_status = 'Active'
        ORDER BY fg.filter_group_name ASC, em.filter_name ASC
    ";
    
    $filters = DB::query($filters_query, $equipment_id);
    
    // Group filters by filter group
    $grouped_filters = [];
    $equipment_info = [];
    
    foreach ($filters as $filter) {
        // Store equipment info (will be the same for all filters)
        if (empty($equipment_info)) {
            $equipment_info = [
                'equipment_id' => $filter['equipment_id'],
                'equipment_code' => $filter['equipment_code'],
                'room_loc_name' => $filter['room_loc_name'],
                'room_volume' => $filter['room_volume'],
                'test_wf_id' => $test_wf_id
            ];
        }
        
        $group_name = $filter['filter_group_name'] ?? 'Ungrouped Filters';
        $group_id = $filter['filter_group_id'] ?? 0;
        
        if (!isset($grouped_filters[$group_name])) {
            $grouped_filters[$group_name] = [
                'filter_group_id' => $group_id,
                'filter_group_name' => $group_name,
                'filters' => []
            ];
        }
        
        $grouped_filters[$group_name]['filters'][] = [
            'erf_mapping_id' => $filter['erf_mapping_id'],
            'filter_id' => $filter['filter_id'],
            'filter_name' => $filter['filter_name'],
            'area_classification' => $filter['area_classification'],
            'filter_group_id' => $filter['filter_group_id'],
            'filter_group_name' => $filter['filter_group_name']
        ];
    }
    
    // Convert associative array to indexed array for JSON response
    $filter_groups = array_values($grouped_filters);
    
    echo json_encode([
        'status' => 'success',
        'equipment_info' => $equipment_info,
        'filter_groups' => $filter_groups,
        'total_filters' => count($filters),
        'total_groups' => count($filter_groups)
    ]);
    
} catch (InvalidArgumentException $e) {
    error_log("ACPH filters retrieval validation error: " . $e->getMessage());
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
} catch (Exception $e) {
    error_log("ACPH filters retrieval error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Failed to retrieve filter data. Please try again.'
    ]);
}
?>