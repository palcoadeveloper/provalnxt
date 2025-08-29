<?php
// Session Timeout Middleware
// Provides server-side session validation and management

// Include configuration
// Note: config.php is loaded by the main application

// Note: Constants (SESSION_TIMEOUT, SESSION_WARNING_TIME, BASE_URL) are defined in config.php

/**
 * Get session timeout value with fallback
 */
function getSessionTimeout() {
    return defined('SESSION_TIMEOUT') ? SESSION_TIMEOUT : 300; // 5 minutes default
}

/**
 * Get session warning time with fallback
 */
function getSessionWarningTime() {
    return defined('SESSION_WARNING_TIME') ? SESSION_WARNING_TIME : 180; // 3 minutes default
}

/**
 * Get base URL with fallback
 */
function getBaseUrl() {
    return defined('BASE_URL') ? BASE_URL : '/';
}

/**
 * Validates if the current session is active and not expired
 * Redirects to login if session is invalid or expired
 * Updates last activity timestamp for valid sessions
 */
function validateActiveSession() {
    // PURE CLIENT-SIDE MODE: Server-side session timeout validation DISABLED
    // Session timeout is now handled entirely on the client-side via JavaScript
    
    // Skip validation for non-session pages
    $currentPage = basename($_SERVER['PHP_SELF']);
    $excludedPages = ['login.php', 'logout.php', 'checklogin.php'];
    
    if (in_array($currentPage, $excludedPages)) {
        // Clear session destruction markers on login page to allow fresh login
        if ($currentPage === 'login.php' && isset($_SESSION['session_destroyed'])) {
            unset($_SESSION['session_destroyed']);
            unset($_SESSION['destruction_time']);
            unset($_SESSION['destruction_reason']);
        }
        return;
    }
    
    // Check if session exists
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    // Check if user is logged in (basic session validation only)
    if (!isset($_SESSION['employee_id']) && !isset($_SESSION['vendor_id'])) {
        redirectToLogin('no_session');
        return;
    }
    
    // Check for destroyed session marker (prevents back button access)
    if (isset($_SESSION['session_destroyed'])) {
        redirectToLogin('session_destroyed');
        return;
    }
    
    // SMART SERVER-SIDE TIMEOUT: Only timeout truly inactive users
    // Check session timeout only when no recent activity detected
    if (isset($_SESSION['last_activity'])) {
        $inactiveTime = time() - $_SESSION['last_activity'];
        
        // Only timeout if user has been completely inactive for the full timeout period
        if ($inactiveTime > getSessionTimeout()) {
            // Additional check: ensure no active transactions in progress
            if (!isset($_SESSION['transaction_in_progress'])) {
                // Session expired due to complete inactivity - compliance lockout
                logSessionTimeout();
                destroySession();
                redirectToLogin('session_timeout_compliance');
                return;
            } else {
                // Transaction in progress - extend session to prevent data loss
                $_SESSION['last_activity'] = time();
                error_log("Session timeout prevented due to active transaction for user: " . ($_SESSION['employee_id'] ?? $_SESSION['vendor_id'] ?? 'unknown'));
            }
        }
    }
    
    // Initialize last_activity if not set (for first-time sessions)
    if (!isset($_SESSION['last_activity'])) {
        $_SESSION['last_activity'] = time();
    }
    
    // Note: Activity timestamp is updated by explicit user interactions and transactions
    // Page loads alone do not extend session - only real user activity does
}

/**
 * Updates the last activity timestamp
 * Used for explicit user interactions and transactions
 */
function updateSessionActivity() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $_SESSION['last_activity'] = time();
    
    // Log activity update for debugging
    error_log("Session activity updated for user: " . ($_SESSION['employee_id'] ?? $_SESSION['vendor_id'] ?? 'unknown') . " at " . date('Y-m-d H:i:s'));
}

/**
 * Extends session during critical transactions
 * Call this during form submissions, file uploads, and AJAX operations
 */
function extendSessionForTransaction($operation = 'transaction') {
    updateSessionActivity();
    
    // Add extra buffer for long operations
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    // Mark as transaction in progress (optional debugging)
    $_SESSION['transaction_in_progress'] = [
        'operation' => $operation,
        'started_at' => time(),
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
    ];
    
    error_log("Session extended for {$operation} by user: " . ($_SESSION['employee_id'] ?? $_SESSION['vendor_id'] ?? 'unknown'));
}

/**
 * Marks transaction as completed
 */
function completeTransaction() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    // Update activity timestamp
    updateSessionActivity();
    
    // Clear transaction marker
    unset($_SESSION['transaction_in_progress']);
}

/**
 * Gets remaining session time in seconds
 * @return int Remaining time in seconds, 0 if expired
 */
function getRemainingSessionTime() {
    if (!isset($_SESSION['last_activity'])) {
        return 0;
    }
    
    $inactiveTime = time() - $_SESSION['last_activity'];
    $remaining = getSessionTimeout() - $inactiveTime;
    
    return max(0, $remaining);
}

/**
 * Checks if session should show warning
 * @return bool True if warning should be shown
 */
function shouldShowSessionWarning() {
    if (!isset($_SESSION['last_activity'])) {
        return false;
    }
    
    $inactiveTime = time() - $_SESSION['last_activity'];
    return $inactiveTime >= getSessionWarningTime();
}

/**
 * Destroys the current session completely
 */
function destroySession() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    // Clear session variables
    $_SESSION = array();
    
    // Clear session cookie
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    
    // Destroy session
    session_destroy();
}

/**
 * Logs session timeout event
 */
function logSessionTimeout() {
    if (isset($_SESSION['employee_id']) || isset($_SESSION['vendor_id'])) {
        $userId = $_SESSION['employee_id'] ?? $_SESSION['vendor_id'] ?? 'unknown';
        $userType = isset($_SESSION['employee_id']) ? 'employee' : 'vendor';
        
        error_log("Session timeout for {$userType}: {$userId} at " . date('Y-m-d H:i:s'));
        
        // Log to session timeout file if it exists
        if (file_exists('log_session_timeout.php')) {
            // Use the existing logging mechanism
            if (function_exists('curl_init')) {
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, getBaseUrl() . 'core/debug/log_session_timeout.php');
                curl_setopt($ch, CURLOPT_POST, 1);
                curl_setopt($ch, CURLOPT_POSTFIELDS, 'action=session_timeout');
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_TIMEOUT, 2);
                curl_exec($ch);
                curl_close($ch);
            }
        }
    }
}

/**
 * Redirects to login page with appropriate message
 * @param string $reason Reason for redirect
 */
function redirectToLogin($reason = 'session_expired') {
    // For AJAX requests, return JSON response
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
        strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
        
        header('Content-Type: application/json');
        echo json_encode([
            'status' => 'session_expired',
            'message' => 'Your session has expired. Please log in again.',
            'redirect' => getBaseUrl() . 'login.php?msg=' . $reason
        ]);
        exit();
    }
    
    // For regular requests, redirect to login
    header('Location: ' . getBaseUrl() . 'login.php?msg=' . $reason);
    exit();
}

/**
 * Returns session status information as JSON
 * Used for AJAX session validation
 */
function getSessionStatus() {
    $status = [
        'active' => false,
        'remaining_time' => 0,
        'show_warning' => false,
        'user_id' => null,
        'user_type' => null
    ];
    
    if (isset($_SESSION['employee_id']) || isset($_SESSION['vendor_id'])) {
        $status['active'] = true;
        $status['remaining_time'] = getRemainingSessionTime();
        $status['show_warning'] = shouldShowSessionWarning();
        $status['user_id'] = $_SESSION['employee_id'] ?? $_SESSION['vendor_id'];
        $status['user_type'] = isset($_SESSION['employee_id']) ? 'employee' : 'vendor';
    }
    
    return $status;
}