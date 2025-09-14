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
        'active_instruments' => 0,
        'expired_instruments' => 0,
        'due_soon_instruments' => 0
    ];
    
    // Debug logging
    error_log("DEBUG: Starting instrument statistics query");
    
    // Query for active instruments
    $stats['active_instruments'] = intval(DB::queryFirstField(
        "SELECT COUNT(*) FROM instruments WHERE instrument_status = 'Active'"
    ));
    
    // Query for expired instruments (calibration due date passed) - all instruments regardless of status
    $stats['expired_instruments'] = intval(DB::queryFirstField(
        "SELECT COUNT(*) FROM instruments WHERE calibration_due_on < CURDATE()"
    ));
    
    // Query for instruments due for calibration soon (within 30 days)
    $stats['due_soon_instruments'] = intval(DB::queryFirstField(
        "SELECT COUNT(*) FROM instruments 
         WHERE calibration_due_on BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY) 
         AND instrument_status = 'Active'"
    ));
    
    error_log("DEBUG: Instrument stats = " . json_encode($stats));
    
    // Return JSON response
    echo json_encode($stats);
    
} catch (Exception $e) {
    error_log("Instrument statistics error: " . $e->getMessage());
    error_log("Instrument statistics stack trace: " . $e->getTraceAsString());
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}

?>