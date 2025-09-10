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

// Show All PHP Errors for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

try {
    // Initialize statistics
    $stats = [
        'total_rooms' => 0,
        'total_volume' => 0.00
    ];
    
    // Debug logging
    error_log("DEBUG: Starting room statistics query");
    
    // Query for total rooms
    $stats['total_rooms'] = intval(DB::queryFirstField(
        "SELECT COUNT(*) FROM room_locations"
    ));
    
    // Query for total volume
    $total_volume = DB::queryFirstField(
        "SELECT IFNULL(SUM(room_volume), 0) FROM room_locations"
    );
    $stats['total_volume'] = number_format(floatval($total_volume), 2);
    
    error_log("DEBUG: Room stats = " . json_encode($stats));
    
    // Return JSON response
    echo json_encode($stats);
    
} catch (Exception $e) {
    error_log("Room statistics error: " . $e->getMessage());
    error_log("Room statistics stack trace: " . $e->getTraceAsString());
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}

?>