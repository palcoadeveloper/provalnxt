<?php
require_once('../../config/config.php');

// Validate session timeout
require_once('../../security/session_timeout_middleware.php');
validateActiveSession();

// Use centralized session validation
require_once('../../security/session_validation.php');
validateUserSession();

require_once("../../config/db.class.php");

// Additional security validation - validate user type
$userType = $_SESSION['logged_in_user'] ?? '';
if (!in_array($userType, ['employee', 'vendor'])) {
    http_response_code(403);
    echo '<option value="">Unauthorized access</option>';
    exit();
}

// Only allow GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo '<option value="">Method not allowed</option>';
    exit();
}

try {
    // Get input parameters and sanitize
    $unit_id = $_GET['unit_id'] ?? '';
    $filter_type = $_GET['filter_type'] ?? '';
    $status_filter = $_GET['status_filter'] ?? 'Active'; // Default to Active
    
    // Get user information from session
    $user_unit_id = getUserUnitId();
    
    // Ensure unit_id is a valid integer for database
    if ($user_unit_id === '' || $user_unit_id === null) {
        $user_unit_id = 0;
    }
    $user_unit_id = intval($user_unit_id);
    
    // Build the base query
    $query = "
        SELECT 
            f.filter_id,
            f.filter_code,
            f.filter_name,
            fg.filter_group_name as filter_type,
            f.filter_size,
            f.status
        FROM filters f
        LEFT JOIN filter_groups fg ON f.filter_type_id = fg.filter_group_id
        WHERE 1=1
    ";
    
    $query_params = [];
    
    // Apply unit filtering based on user permissions
    if (!isVendor() && $_SESSION['is_super_admin'] !== "Yes") {
        // Regular employee users: restrict to their unit only
        $query .= " AND f.unit_id = %i";
        $query_params[] = $user_unit_id;
    } else if (!empty($unit_id) && is_numeric($unit_id)) {
        // Super admin or vendor: filter by selected unit if specified
        $query .= " AND f.unit_id = %i";
        $query_params[] = intval($unit_id);
    }
    
    // Apply additional filters
    if (!empty($filter_type) && $filter_type != 'Select') {
        $query .= " AND f.filter_type_id = %i";
        $query_params[] = intval($filter_type);
    }
    
    if (!empty($status_filter) && $status_filter != 'Select') {
        $query .= " AND f.status = %s";
        $query_params[] = $status_filter;
    }
    
    // Add ordering
    $query .= " ORDER BY f.filter_code ASC";
    
    // Execute query
    $results = DB::query($query, ...$query_params);
    
    $output = '';
    
    if (!empty($results)) {
        foreach ($results as $row) {
            $display_text = htmlspecialchars($row['filter_code'], ENT_QUOTES, 'UTF-8');
            if (!empty($row['filter_name'])) {
                $display_text .= ' - ' . htmlspecialchars($row['filter_name'], ENT_QUOTES, 'UTF-8');
            }
            $display_text .= ' (' . htmlspecialchars($row['filter_type'], ENT_QUOTES, 'UTF-8') . ')';
            
            $output .= '<option value="' . intval($row['filter_id']) . '">' . $display_text . '</option>';
        }
    }
    
    if (empty($output)) {
        $output = '<option value="">No filters found</option>';
    }
    
    echo $output;
    
} catch (Exception $e) {
    error_log("Filter dropdown error: " . $e->getMessage());
    echo '<option value="">Error loading filters</option>';
}
?>