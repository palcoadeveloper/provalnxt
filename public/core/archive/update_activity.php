<?php
// Update Session Activity Endpoint
// For explicit user activity updates (like "Continue Session" button)

// Include session configuration and middleware
require_once '../../config/config.php';
require_once '../../security/session_init.php';
require_once '../../security/session_timeout_middleware.php';

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
            'message' => 'No active session'
        ]);
        exit();
    }
    
    // Update the last activity timestamp
    updateSessionActivity();
    
    // Return success response
    echo json_encode([
        'status' => 'updated',
        'message' => 'Activity timestamp updated',
        'timestamp' => time()
    ]);
    
} catch (Exception $e) {
    // Log error
    error_log("Activity update error: " . $e->getMessage());
    
    // Return error response
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Internal server error'
    ]);
}