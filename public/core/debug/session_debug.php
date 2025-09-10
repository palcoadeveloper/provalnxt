<?php
// Session Debug Endpoint
// Provides real-time session information for debugging

// Only allow in development environment
require_once('../config/config.php');

if (ENVIRONMENT !== 'dev') {
    http_response_code(403);
    echo json_encode(['error' => 'Debug endpoint only available in development']);
    exit();
}

require_once('../security/session_timeout_middleware.php');

// Set JSON header
header('Content-Type: application/json');

// Check if session exists
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

try {
    // Gather session information
    $sessionInfo = [
        'session_id' => session_id(),
        'session_status' => session_status(),
        'current_time' => time(),
        'current_datetime' => date('Y-m-d H:i:s'),
        'configuration' => [
            'SESSION_TIMEOUT' => getSessionTimeout(),
            'SESSION_WARNING_TIME' => getSessionWarningTime(),
            'ENVIRONMENT' => ENVIRONMENT,
            'SESSION_DEBUG_ENABLED' => defined('SESSION_DEBUG_ENABLED') ? SESSION_DEBUG_ENABLED : false,
            'SESSION_TIMEOUT_LOGGING_ENABLED' => defined('SESSION_TIMEOUT_LOGGING_ENABLED') ? SESSION_TIMEOUT_LOGGING_ENABLED : false,
            'SESSION_ACTIVITY_LOGGING_ENABLED' => defined('SESSION_ACTIVITY_LOGGING_ENABLED') ? SESSION_ACTIVITY_LOGGING_ENABLED : false
        ]
    ];

    // Check if user is logged in
    if (isset($_SESSION['employee_id']) || isset($_SESSION['vendor_id'])) {
        $userType = isset($_SESSION['employee_id']) && !empty($_SESSION['employee_id']) ? 'employee' : 'vendor';
        $userId = $_SESSION['employee_id'] ?? $_SESSION['vendor_id'] ?? 'unknown';
        
        // Calculate session timings
        $loginTime = $_SESSION['login_time'] ?? null;
        $lastActivity = $_SESSION['last_activity'] ?? null;
        $currentTime = time();
        
        $sessionDuration = $loginTime ? ($currentTime - $loginTime) : null;
        $inactiveTime = $lastActivity ? ($currentTime - $lastActivity) : null;
        $remainingTime = getRemainingSessionTime();
        $shouldShowWarning = shouldShowSessionWarning();
        
        $sessionInfo['user'] = [
            'logged_in' => true,
            'user_type' => $userType,
            'user_id' => $userId,
            'user_name' => $_SESSION['user_name'] ?? 'Unknown',
            'vendor_name' => $_SESSION['vendor_name'] ?? null,
            'unit_id' => $_SESSION['unit_id'] ?? null,
            'department_id' => $_SESSION['department_id'] ?? null
        ];
        
        $sessionInfo['timing'] = [
            'login_time' => $loginTime,
            'login_datetime' => $loginTime ? date('Y-m-d H:i:s', $loginTime) : null,
            'last_activity' => $lastActivity,
            'last_activity_datetime' => $lastActivity ? date('Y-m-d H:i:s', $lastActivity) : null,
            'session_duration_seconds' => $sessionDuration,
            'inactive_time_seconds' => $inactiveTime,
            'remaining_time_seconds' => $remainingTime,
            'should_show_warning' => $shouldShowWarning,
            'will_timeout_at' => $lastActivity ? date('Y-m-d H:i:s', $lastActivity + getSessionTimeout()) : null
        ];
        
        // Calculate status
        if ($remainingTime <= 0) {
            $sessionInfo['status'] = 'expired';
        } elseif ($shouldShowWarning) {
            $sessionInfo['status'] = 'warning';
        } else {
            $sessionInfo['status'] = 'active';
        }
        
    } else {
        $sessionInfo['user'] = [
            'logged_in' => false
        ];
        $sessionInfo['status'] = 'not_logged_in';
    }
    
    // Add session data (filtered for security)
    $filteredSessionData = [];
    $allowedKeys = [
        'login_time', 'last_activity', 'logged_in_user', 'user_name', 
        'vendor_name', 'unit_id', 'department_id', 'is_admin', 'is_super_admin'
    ];
    
    foreach ($allowedKeys as $key) {
        if (isset($_SESSION[$key])) {
            $filteredSessionData[$key] = $_SESSION[$key];
        }
    }
    
    $sessionInfo['session_data'] = $filteredSessionData;
    
    echo json_encode($sessionInfo, JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    echo json_encode([
        'error' => 'Debug error: ' . $e->getMessage(),
        'session_status' => session_status(),
        'session_id' => session_id()
    ]);
}
?>