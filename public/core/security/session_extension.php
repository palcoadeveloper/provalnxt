<?php
// Session Extension Endpoint
// Allows client-side JavaScript to extend server session during active use

// Session is already started by config.php via session_init.php
require_once '../config/config.php';
require_once '../security/session_timeout_middleware.php';

// Validate that user has an active session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['employee_id']) && !isset($_SESSION['vendor_id'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'No active session']);
    exit();
}

// Process session extension request
if ($_POST['action'] === 'extend_session') {
    $operation = $_POST['operation'] ?? 'unknown';
    
    // Update server-side session activity
    updateSessionActivity();
    
    // Log the extension
    $userId = $_SESSION['employee_id'] ?? $_SESSION['vendor_id'] ?? 'unknown';
    error_log("Client-initiated session extension for operation '{$operation}' by user: {$userId} at " . date('Y-m-d H:i:s'));
    
    // Return success response
    echo json_encode([
        'status' => 'success',
        'message' => 'Session extended',
        'operation' => $operation,
        'timestamp' => date('Y-m-d H:i:s'),
        'remaining_time' => getRemainingSessionTime()
    ]);
} else {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
}
?>