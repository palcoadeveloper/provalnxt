<?php
// Do NOT call validateActiveSession() here as this endpoint is used during session destruction
// and would create a validation loop that prevents login

require_once('../config/config.php');
include_once '../config/db.class.php';

// Check if this is a valid session timeout request
if ($_POST['action'] === 'session_timeout') {
    
    // Get user information from session if available
    $user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
    $username = isset($_SESSION['user_name']) ? $_SESSION['user_name'] : 'Unknown User';
    $unit_id = isset($_SESSION['unit_id']) ? $_SESSION['unit_id'] : null;
    
    // Only log if we have a valid user session
    if ($user_id) {
        try {
            // Insert log entry for session timeout
            DB::insert('log', [
                'change_type' => 'security_error',
                'table_name' => '',
                'change_description' => 'User ' . $username . ' automatically logged out due to inactivity.',
                'change_by' => 0,
                'unit_id' => $unit_id
            ]);
            
            // Send success response
            http_response_code(200);
            echo 'Session timeout logged successfully';
            
        } catch (Exception $e) {
            // Log error but don't block the logout process
            error_log('Session timeout logging failed: ' . $e->getMessage());
            http_response_code(500);
            echo 'Logging failed';
        }
    } else {
        // No valid session to log
        http_response_code(400);
        echo 'No valid session';
    }
    
} else {
    // Invalid request
    http_response_code(400);
    echo 'Invalid request';
}
?> 