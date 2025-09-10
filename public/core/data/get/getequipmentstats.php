<?php 
session_start();

// Load configuration first
require_once(__DIR__ . '/../../config/config.php');

// Validate session timeout
require_once(__DIR__ . '/../../security/session_timeout_middleware.php');
validateActiveSession();

require_once __DIR__ . '/../../config/db.class.php';
date_default_timezone_set("Asia/Kolkata");

//Show All PHP Errors for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Get input values
$unit_id = isset($_GET['unit_id']) ? $_GET['unit_id'] : '';

try {
    // Initialize statistics
    $stats = [
        'active_equipments' => 0,
        'inactive_equipments' => 0,
        'no_etv_mapping' => 0
    ];
    
    // Build query conditions based on unit selection and user permissions
    $unit_condition = "";
    $unit_params = [];
    
    if (!empty($unit_id) && is_numeric($unit_id)) {
        $unit_condition = "AND unit_id = %i";
        $unit_params[] = intval($unit_id);
    } elseif ($_SESSION['is_super_admin'] !== "Yes") {
        // Non-super admin users can only see their unit's data
        $unit_condition = "AND unit_id = %i";
        $unit_params[] = intval($_SESSION['unit_id']);
    } else {
        // Super admin without specific unit selection - use the first available unit or their assigned unit
        $unit_condition = "AND unit_id = %i";
        $unit_params[] = isset($_SESSION['unit_id']) ? intval($_SESSION['unit_id']) : 1;
    }
    
    // Helper function to execute query with or without parameters
    function executeCountQuery($query, $params = []) {
        if (!empty($params)) {
            return DB::queryFirstField($query, $params[0]);
        } else {
            return DB::queryFirstField($query);
        }
    }
    
    // Query for active equipments
    $active_equipments_query = "SELECT COUNT(*) FROM equipments WHERE equipment_status = 'Active'" . $unit_condition;
    $stats['active_equipments'] = executeCountQuery($active_equipments_query, $unit_params);
    
    // Query for inactive equipments
    $inactive_equipments_query = "SELECT COUNT(*) FROM equipments WHERE equipment_status != 'Active'" . $unit_condition;
    $stats['inactive_equipments'] = executeCountQuery($inactive_equipments_query, $unit_params);
    
    // Query for equipments without ETV mapping
    // Find equipments that don't have any Active mapping in equipment_test_vendor_mapping table
    $no_etv_mapping_query = "
        SELECT COUNT(*) 
        FROM equipments e 
        WHERE e.equipment_id NOT IN (
            SELECT DISTINCT equipment_id 
            FROM equipment_test_vendor_mapping 
            WHERE mapping_status = 'Active' 
            AND equipment_id IS NOT NULL
        )" . str_replace('unit_id', 'e.unit_id', $unit_condition);
    
    $stats['no_etv_mapping'] = executeCountQuery($no_etv_mapping_query, $unit_params);
    
    // Ensure all values are integers
    $stats['active_equipments'] = intval($stats['active_equipments']);
    $stats['inactive_equipments'] = intval($stats['inactive_equipments']);
    $stats['no_etv_mapping'] = intval($stats['no_etv_mapping']);
    
    // Return JSON response
    echo json_encode($stats);
    
} catch (Exception $e) {
    error_log("Equipment statistics error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Database error occurred while fetching equipment statistics.']);
}

?>