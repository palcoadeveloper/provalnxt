<?php
/**
 * Cancel 2FA Session Handler
 * Properly cleans up session data when user abandons 2FA process
 */

// Include configuration and security
require_once 'core/config/config.php';
require_once 'core/security/session_init.php';
require_once 'core/security/auth_utils.php';
require_once 'core/security/two_factor_auth.php';

// Only allow POST requests for security
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('HTTP/1.1 405 Method Not Allowed');
    header('Location: ' . BASE_URL . 'login.php');
    exit();
}

// Validate CSRF token
if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
    // CSRF validation failed
    session_destroy();
    header('Location: ' . BASE_URL . 'login.php?msg=security_error');
    exit();
}

$ipAddress = getClientIP();
$cancellationSuccessful = false;

// Check if there's a pending 2FA session to cancel
if (isset($_SESSION['pending_2fa']) && isset($_SESSION['otp_session_token'])) {
    $pendingUser = $_SESSION['pending_2fa'];
    $otpSessionToken = $_SESSION['otp_session_token'];
    
    // Cancel the OTP session in the database
    $cancellationSuccessful = TwoFactorAuth::cancelOTPSession($otpSessionToken, $ipAddress);
    
    // Log the cancellation attempt
    error_log("[2FA CANCEL] User {$pendingUser['employee_id']} cancelled 2FA session from IP: $ipAddress");
    
    // Log security event if function exists
    if (function_exists('logSecurityEvent')) {
        logSecurityEvent($pendingUser['employee_id'], '2fa_session_cancelled', 
            'User voluntarily cancelled 2FA authentication process', 
            $pendingUser['unit_id'], $ipAddress);
    }
}

// Always clean up session data regardless of database operation result
unset($_SESSION['pending_2fa']);
unset($_SESSION['otp_session_token']);

// Regenerate session ID for security
if (session_status() === PHP_SESSION_ACTIVE) {
    session_regenerate_id(true);
}

// Redirect to login with appropriate message
$message = $cancellationSuccessful ? 'session_cancelled' : 'session_cancelled_error';
header('Location: ' . BASE_URL . 'login.php?msg=' . $message);
exit();
?>