<?php
// Session Validation Endpoint
// AJAX endpoint for client-side session validation and heartbeat

// Include session configuration and middleware
require_once '../config/config.php';
require_once '../security/session_init.php';
require_once '../security/session_timeout_middleware.php';

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Method not allowed']);
    exit();
}

// Set JSON content type
header('Content-Type: application/json');

try {
    // Check if session exists
    if (!isset($_SESSION['employee_id']) && !isset($_SESSION['vendor_id'])) {
        echo json_encode([
            'status' => 'invalid',
            'active' => false,
            'message' => 'No active session',
            'redirect' => BASE_URL . 'login.php?msg=no_session'
        ]);
        exit();
    }
    
    // Get current session status
    $sessionStatus = getSessionStatus();
    
    // Check if session has expired
    if ($sessionStatus['remaining_time'] <= 0) {
        // Log the timeout
        logSessionTimeout();
        
        // Destroy the session
        destroySession();
        
        echo json_encode([
            'status' => 'expired',
            'active' => false,
            'message' => 'Session has expired',
            'redirect' => BASE_URL . 'login.php?msg=session_timeout'
        ]);
        exit();
    }
    
    // Don't update activity timestamp for heartbeat - only validate
    // Activity should only be updated on actual user interaction
    
    // Return session status
    echo json_encode([
        'status' => 'valid',
        'active' => true,
        'remaining_time' => $sessionStatus['remaining_time'],
        'show_warning' => $sessionStatus['show_warning'],
        'warning_threshold' => SESSION_WARNING_TIME,
        'timeout_threshold' => SESSION_TIMEOUT,
        'user_info' => [
            'id' => $sessionStatus['user_id'],
            'type' => $sessionStatus['user_type']
        ],
        'server_time' => time()
    ]);
    
} catch (Exception $e) {
    // Log error
    error_log("Session validation error: " . $e->getMessage());
    
    // Return error response
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'active' => false,
        'message' => 'Internal server error',
        'redirect' => BASE_URL . 'login.php?msg=system_error'
    ]);
}