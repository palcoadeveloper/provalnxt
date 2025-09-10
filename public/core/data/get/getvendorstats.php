<?php 
session_start();

// Load configuration first
require_once(__DIR__ . '/../../config/config.php');

// Validate session timeout
require_once(__DIR__ . '/../../security/session_timeout_middleware.php');

// Include XSS protection middleware to get safe_get function
require_once(__DIR__ . '/../../security/xss_integration_middleware.php');

// Only validate session if we're in a web request
if (!empty($_SERVER['REQUEST_METHOD'])) {
    validateActiveSession();
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
        'active_vendors' => 0,
        'inactive_vendors' => 0
    ];
    
    // Debug logging
    error_log("DEBUG: Starting vendor statistics query");
    
    // Query for active vendors
    $stats['active_vendors'] = intval(DB::queryFirstField(
        "SELECT COUNT(*) FROM vendors WHERE vendor_status = 'Active'"
    ));
    
    // Query for inactive vendors
    $stats['inactive_vendors'] = intval(DB::queryFirstField(
        "SELECT COUNT(*) FROM vendors WHERE vendor_status != 'Active' OR vendor_status IS NULL"
    ));
    
    error_log("DEBUG: Vendor stats = " . json_encode($stats));
    
    // Return JSON response
    echo json_encode($stats);
    
} catch (Exception $e) {
    error_log("Vendor statistics error: " . $e->getMessage());
    error_log("Vendor statistics stack trace: " . $e->getTraceAsString());
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}

?>