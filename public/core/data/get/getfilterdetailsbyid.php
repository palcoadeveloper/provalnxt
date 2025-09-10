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
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Only allow GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

try {
    // Get input parameters and sanitize
    $filter_id = $_GET['filter_id'] ?? '';
    
    // Validate required parameter
    if (empty($filter_id) || !is_numeric($filter_id)) {
        echo json_encode(['success' => false, 'message' => 'Invalid filter ID']);
        exit();
    }
    
    // Get user information from session
    $user_unit_id = getUserUnitId();
    
    // Ensure unit_id is a valid integer for database
    if ($user_unit_id === '' || $user_unit_id === null) {
        $user_unit_id = 0;
    }
    $user_unit_id = intval($user_unit_id);
    $filter_id = intval($filter_id);
    
    // Build the query with proper security filtering
    $query = "
        SELECT 
            f.filter_id,
            f.filter_code,
            f.filter_name,
            f.filter_type_id,
            fg.filter_group_name,
            fg.filter_group_id
        FROM filters f
        LEFT JOIN filter_groups fg ON f.filter_type_id = fg.filter_group_id
        WHERE f.filter_id = %i AND f.status = 'Active'
    ";
    
    $query_params = [$filter_id];
    
    // Apply unit filtering based on user permissions
    if (!isVendor() && $_SESSION['is_super_admin'] !== "Yes") {
        // Regular employee users: restrict to their unit only
        $query .= " AND f.unit_id = %i";
        $query_params[] = $user_unit_id;
    }
    
    // Execute query
    $filter_details = DB::queryFirstRow($query, ...$query_params);
    
    if (!$filter_details) {
        echo json_encode(['success' => false, 'message' => 'Filter not found or access denied']);
        exit();
    }
    
    // Return filter details
    echo json_encode([
        'success' => true,
        'filter_id' => $filter_details['filter_id'],
        'filter_code' => $filter_details['filter_code'],
        'filter_name' => $filter_details['filter_name'] ?? '',
        'filter_group_id' => $filter_details['filter_group_id'],
        'filter_group_name' => $filter_details['filter_group_name']
    ]);
    
} catch (Exception $e) {
    error_log("Filter details fetch error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Failed to retrieve filter details. Please try again.']);
}
?>