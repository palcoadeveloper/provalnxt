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
    $unit_id = $_GET['unit_id'] ?? '';
    
    // Validate unit_id if provided
    if (!empty($unit_id) && !is_numeric($unit_id)) {
        throw new InvalidArgumentException("Invalid unit ID");
    }
    
    // Get user information from session
    $user_unit_id = getUserUnitId();
    
    // Ensure unit_id is a valid integer for database
    if ($user_unit_id === '' || $user_unit_id === null) {
        $user_unit_id = 0;
    }
    $user_unit_id = intval($user_unit_id);
    
    // Determine which unit to query
    $query_unit_id = !empty($unit_id) ? intval($unit_id) : $user_unit_id;
    
    // Handle different query logic for vendor vs employee users
    $unit_filter = '';
    $query_params = [];
    
    if (!isVendor()) {
        // Employee users: Include unit_id constraint for data segregation
        if ($_SESSION['is_super_admin'] !== "Yes") {
            $unit_filter = " AND f.unit_id = %i";
            $query_params[] = $user_unit_id;
        } else if (!empty($query_unit_id)) {
            $unit_filter = " AND f.unit_id = %i";
            $query_params[] = $query_unit_id;
        }
    } else {
        // Vendor users: May access across units, but still filter by unit if specified
        if (!empty($query_unit_id)) {
            $unit_filter = " AND f.unit_id = %i";
            $query_params[] = $query_unit_id;
        }
    }
    
    // Count active filters
    $active_filters_query = "SELECT COUNT(*) FROM filters f WHERE f.status = 'Active'" . $unit_filter;
    $active_filters = DB::queryFirstField($active_filters_query, ...$query_params);
    
    // Count inactive filters
    $inactive_filters_query = "SELECT COUNT(*) FROM filters f WHERE f.status = 'Inactive'" . $unit_filter;
    $inactive_filters = DB::queryFirstField($inactive_filters_query, ...$query_params);
    
    // Count filters due for replacement (planned_due_date <= today + 30 days)
    $due_replacement_query = "SELECT COUNT(*) FROM filters f WHERE f.status = 'Active' AND f.planned_due_date IS NOT NULL AND f.planned_due_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)" . $unit_filter;
    $due_replacement = DB::queryFirstField($due_replacement_query, ...$query_params);
    
    // Return statistics as JSON
    echo json_encode([
        'active_filters' => intval($active_filters ?: 0),
        'inactive_filters' => intval($inactive_filters ?: 0),
        'due_replacement' => intval($due_replacement ?: 0),
        'total_filters' => intval(($active_filters ?: 0) + ($inactive_filters ?: 0)),
        'unit_id' => $query_unit_id
    ]);
    
} catch (InvalidArgumentException $e) {
    error_log("Filter statistics validation error: " . $e->getMessage());
    http_response_code(400);
    echo json_encode([
        'error' => $e->getMessage()
    ]);
} catch (Exception $e) {
    error_log("Filter statistics error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'error' => 'Failed to retrieve filter statistics. Please try again.'
    ]);
}
?>