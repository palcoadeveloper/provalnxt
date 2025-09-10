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
        'active_employees' => 0,
        'inactive_employees' => 0,
        'active_vendor_employees' => 0,
        'inactive_vendor_employees' => 0
    ];
    
    // Build query conditions based on unit selection and user permissions
    $unit_condition = "";
    $unit_params = [];
    
    if (!empty($unit_id) && $unit_id === 'all' && $_SESSION['is_super_admin'] === "Yes") {
        // Super admin selected 'All Units' - no unit condition needed
        $unit_condition = "";
    } elseif (!empty($unit_id) && is_numeric($unit_id)) {
        $unit_condition = "AND unit_id = %i";
        $unit_params[] = intval($unit_id);
    } elseif ($_SESSION['is_super_admin'] !== "Yes") {
        // Non-super admin users can only see their unit's data
        $unit_condition = "AND unit_id = %i";
        $unit_params[] = intval($_SESSION['unit_id']);
    }
    
    // Helper function to execute query with or without parameters
    function executeCountQuery($query, $params = []) {
        if (!empty($params)) {
            return DB::queryFirstField($query, $params[0]);
        } else {
            return DB::queryFirstField($query);
        }
    }
    
    // Query for active employees
    $active_employees_query = "SELECT COUNT(*) FROM users WHERE user_status = 'Active' AND user_type = 'employee'" . $unit_condition;
    $stats['active_employees'] = executeCountQuery($active_employees_query, $unit_params);
    
    // Query for inactive employees
    $inactive_employees_query = "SELECT COUNT(*) FROM users WHERE user_status = 'Inactive' AND user_type = 'employee'" . $unit_condition;
    $stats['inactive_employees'] = executeCountQuery($inactive_employees_query, $unit_params);
    
    // Query for active vendor employees
    $active_vendor_employees_query = "SELECT COUNT(*) FROM users WHERE user_status = 'Active' AND user_type = 'vendor'" . $unit_condition;
    $stats['active_vendor_employees'] = executeCountQuery($active_vendor_employees_query, $unit_params);
    
    // Query for inactive vendor employees
    $inactive_vendor_employees_query = "SELECT COUNT(*) FROM users WHERE user_status = 'Inactive' AND user_type = 'vendor'" . $unit_condition;
    $stats['inactive_vendor_employees'] = executeCountQuery($inactive_vendor_employees_query, $unit_params);
    
    // Ensure all values are integers
    $stats['active_employees'] = intval($stats['active_employees']);
    $stats['inactive_employees'] = intval($stats['inactive_employees']);
    $stats['active_vendor_employees'] = intval($stats['active_vendor_employees']);
    $stats['inactive_vendor_employees'] = intval($stats['inactive_vendor_employees']);
    
    // Return JSON response
    echo json_encode($stats);
    
} catch (Exception $e) {
    error_log("User statistics error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Database error occurred while fetching statistics.']);
}

?>