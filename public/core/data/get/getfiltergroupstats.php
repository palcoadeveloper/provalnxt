<?php 
session_start();

// Load configuration first
require_once(__DIR__ . '/../../config/config.php');

// Validate session timeout
require_once(__DIR__ . '/../../security/session_timeout_middleware.php');

// Include XSS protection middleware to get safe_get function
require_once(__DIR__ . '/../../security/xss_integration_middleware.php');

// Check if user is logged in before session validation
if (!isset($_SESSION['logged_in_user']) || !isset($_SESSION['user_name'])) {
    error_log("DEBUG: Session check failed - user not logged in");
    http_response_code(401);
    echo json_encode(['error' => 'Authentication required']);
    exit();
}

// Only validate session timeout if we're in a web request
if (!empty($_SERVER['REQUEST_METHOD'])) {
    try {
        validateActiveSession();
    } catch (Exception $e) {
        error_log("DEBUG: Session timeout validation failed: " . $e->getMessage());
        http_response_code(401);
        echo json_encode(['error' => 'Session expired']);
        exit();
    }
}

require_once __DIR__ . '/../../config/db.class.php';
date_default_timezone_set("Asia/Kolkata");

//Show All PHP Errors for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

try {
    // Initialize statistics
    $stats = [
        'active_filtergroups' => 0,
        'inactive_filtergroups' => 0
    ];
    
    // Debug logging
    error_log("DEBUG: Starting filter group statistics query");
    
    // Query for active filter groups
    $stats['active_filtergroups'] = intval(DB::queryFirstField(
        "SELECT COUNT(*) FROM filter_groups WHERE status = 'Active'"
    ));
    
    // Query for inactive filter groups
    $stats['inactive_filtergroups'] = intval(DB::queryFirstField(
        "SELECT COUNT(*) FROM filter_groups WHERE status = 'Inactive'"
    ));
    
    error_log("DEBUG: Filter group stats = " . json_encode($stats));
    
    // Return JSON response
    echo json_encode($stats);
    
} catch (Exception $e) {
    error_log("Filter group statistics error: " . $e->getMessage());
    error_log("Filter group statistics stack trace: " . $e->getTraceAsString());
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}

?>