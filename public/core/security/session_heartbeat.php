<?php
// Session Heartbeat Endpoint
// Handles AJAX requests to extend active user sessions

// Load configuration and security
require_once('../config/config.php');
require_once('session_timeout_middleware.php');

// Set JSON header
header('Content-Type: application/json');

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
    exit();
}

// Check if session exists
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

try {
    // Validate that user is logged in
    if (!isset($_SESSION['employee_id']) && !isset($_SESSION['vendor_id'])) {
        echo json_encode([
            'status' => 'expired',
            'message' => 'Session has expired'
        ]);
        exit();
    }

    // Update session activity FIRST (this is an active request)
    $_SESSION['last_activity'] = time();

    // Log activity for debugging (only in development)
    if (defined('ENVIRONMENT') && ENVIRONMENT === 'dev') {
        $userType = isset($_SESSION['employee_id']) && !empty($_SESSION['employee_id']) ? 'employee' : 'vendor';
        $userId = $_SESSION['employee_id'] ?? $_SESSION['vendor_id'] ?? 'unknown';
        error_log("Session heartbeat: {$userType}:{$userId} activity updated via AJAX");
    }

    // Check for action parameter
    $action = $_POST['action'] ?? '';

    if ($action === 'heartbeat') {
        // Update session activity timestamp
        updateSessionActivity();

        // Get session information for response
        $userType = isset($_SESSION['employee_id']) && !empty($_SESSION['employee_id']) ? 'employee' : 'vendor';
        $userId = $_SESSION['employee_id'] ?? $_SESSION['vendor_id'] ?? 'unknown';
        $remainingTime = getRemainingSessionTime();

        // Check for multi-tab coordination info
        $tabId = $_POST['tab_id'] ?? null;
        $isMaster = $_POST['is_master'] ?? false;

        // Log heartbeat in development mode
        if (defined('ENVIRONMENT') && ENVIRONMENT === 'dev') {
            $tabInfo = $tabId ? " (Tab: {$tabId}, Master: " . ($isMaster ? 'yes' : 'no') . ")" : '';
            error_log("Session heartbeat received for {$userType}: {$userId}, remaining time: {$remainingTime}s{$tabInfo}");
        }

        echo json_encode([
            'status' => 'success',
            'message' => 'Session extended',
            'remaining_time' => $remainingTime,
            'user_type' => $userType,
            'warning_threshold' => getSessionWarningTime(),
            'multi_tab_enabled' => true
        ]);
        
    } elseif ($action === 'status') {
        // Return current session status
        $sessionStatus = getSessionStatus();
        echo json_encode([
            'status' => 'success',
            'session' => $sessionStatus
        ]);
        
    } else {
        echo json_encode([
            'status' => 'error',
            'message' => 'Invalid action'
        ]);
    }

} catch (Exception $e) {
    // Log error for debugging
    error_log("Session heartbeat error: " . $e->getMessage());
    
    echo json_encode([
        'status' => 'error',
        'message' => 'Internal server error'
    ]);
}
?>