<?php
// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Start error logging
error_log("Login attempt started");

// Include configuration first
require_once '../config/config.php';

// Include session initialization
require_once '../security/session_init.php';

// Include security middleware
require_once '../security/security_middleware.php';

// Include database connection
require_once __DIR__ . '/../config/db.class.php';

// Include authentication utilities
require_once '../security/auth_utils.php';

// Include rate limiting utilities
require_once '../security/rate_limiting_utils.php';

// Only enable detailed errors in development
if (ENVIRONMENT === 'dev') {
    ini_set('display_errors', 1);
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', 0);
    error_reporting(0);
}

try {
    // Main login handling
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        error_log("POST request received");
        error_log("POST data: " . print_r($_POST, true));
        error_log("Session data: " . print_r($_SESSION, true));

        // Check if HTTPS is required and request is not secure
        if (FORCE_HTTPS && !isSecureRequest()) {
            // For API/AJAX requests, return JSON error
            if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
                strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
                header('Content-Type: application/json');
                echo json_encode([
                    'status' => 'error',
                    'message' => 'security_error',
                    'type' => 'https_required',
                    'redirect' => getCurrentUrl()
                ]);
                exit();
            }
            
            // For regular requests, redirect to HTTPS required page
            redirectToHttps();
        }

        // Check rate limiting for login attempts before processing
        $rateLimitResult = RateLimiter::checkRateLimit('login_attempts');
        if (!$rateLimitResult['allowed']) {
            error_log("Login attempt rate limited");
            
            // Log the rate limiting event
            if (class_exists('SecurityUtils')) {
                SecurityUtils::logSecurityEvent('login_rate_limited', 'Login attempt blocked by rate limiting', [
                    'lockout_expires' => $rateLimitResult['lockout_expires'],
                    'remaining_time' => $rateLimitResult['lockout_expires'] - time()
                ]);
            }
            
            header("Location:".BASE_URL ."login.php?msg=rate_limited&retry_after=" . 
                   urlencode(ceil(($rateLimitResult['lockout_expires'] - time()) / 60)));
            exit();
        }

        // CSRF validation - don't regenerate token to avoid issues with multiple submissions
        if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'], false)) {
            // Record failed attempt for rate limiting
            RateLimiter::recordFailure('login_attempts', null, 'csrf_failure');
            
            $ip = logSecurityEvent($username ?? 'unknown', 'csrf_failure');
            header("Location:".BASE_URL ."login.php?msg=security_error&type=csrf_failure&ip=" . urlencode($ip));
            exit();
        }
        
        error_log("CSRF validation passed, continuing with login process");
        
        // Get the inputs
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $userType = htmlspecialchars(trim($_POST['optionUserType'] ?? ''), ENT_QUOTES, 'UTF-8');
        
        // Validate input format
        if (!preg_match('/^[a-zA-Z0-9_.]{1,20}$/', $username)) {
            header('Location: '.BASE_URL .'login.php?msg=invalid_input');
            exit();
        }
        
        error_log("Processing login for user: " . $username . ", type: " . $userType);
        
        // Initialize security response
        $securityIssue = false;
        $securityType = '';
        
        // Check for SQL injection
        if (detectSQLInjection($username) || detectSQLInjection($password)) {
            error_log("SQL injection attempt detected");
            $securityIssue = true;
            $securityType = 'sql_injection_attempt';
        }
        
        // Handle security issues
        if ($securityIssue) {
            // Record failed attempt for rate limiting
            RateLimiter::recordFailure('login_attempts', null, $securityType);
            
            $ip = logSecurityEvent($username, $securityType);
           header("Location:" .BASE_URL . "login.php?msg=security_error&type={$securityType}&ip=" . urlencode($ip));
            exit();
        }
        
        // Validate inputs are not empty
        if (empty($username) || empty($password)) {
            error_log("Empty username or password");
            
            // Record failed attempt for rate limiting
            RateLimiter::recordFailure('login_attempts', null, 'empty_fields');
            
            header('Location: '.BASE_URL .'login.php?msg=empty_fields');
            exit();
        }
        
        // Make sure $_SESSION['failed_attempts'] is always an array
        if (!isset($_SESSION['failed_attempts']) || !is_array($_SESSION['failed_attempts'])) {
            $_SESSION['failed_attempts'] = [];
        }
        if (!isset($_SESSION['failed_attempts'][$username])) {
            $_SESSION['failed_attempts'][$username] = 0;
        }

        error_log("Verifying user credentials");
        $user = verifyUserCredentials($username, $password, $userType);
        
        // Verify credentials
        if ($user) {
            error_log("Login successful for user: " . $username);
            
            // Record successful login for rate limiting (clears previous failures)
            RateLimiter::recordSuccess('login_attempts');
            
            // Authentication successful
            handleSuccessfulLogin($user, $userType);
        } else {
            error_log("Login failed for user: " . $username);
            
            // Record failed attempt for rate limiting
            RateLimiter::recordFailure('login_attempts', null, 'invalid_credentials');
            
            $unit_id = isset($user['unit_id']) && $user['unit_id'] !== null ? $user['unit_id'] : 0;
            handleAccountLocking($username, $unit_id);
            
            $attemptsLeft = MAX_LOGIN_ATTEMPTS - $_SESSION['failed_attempts'][$username];
            header('Location: '.BASE_URL .'login.php?msg=invalid_login&attempts_left=' . urlencode($attemptsLeft));
            exit();
        }
    }
} catch (Exception $e) {
    error_log("Error in checklogin.php: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    
    // Redirect to login page with error message
    header('Location: '.BASE_URL .'login.php?msg=system_error');
    exit();
}
?>