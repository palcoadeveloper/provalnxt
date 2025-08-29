<?php

// Environment configuration
if (!defined('ENVIRONMENT')) {
    define('ENVIRONMENT', 'dev'); // Change to 'prod' in production
}

// Database configuration
if (!defined('DB_HOST')) {
    define('DB_HOST', 'localhost');
}
if (!defined('DB_NAME')) {
    define('DB_NAME', 'proval4_demo');
}
if (!defined('DB_USER')) {
    define('DB_USER', 'root');
}
if (!defined('DB_PASS')) {
    define('DB_PASS', '');
}
if (!defined('DB_PORT')) {
    define('DB_PORT', 3306);
}

// Security configuration
if (!defined('FORCE_HTTPS')) {
    define('FORCE_HTTPS', false); // set to true to force HTTPS, false to allow HTTP
}
if (!defined('HTTPS_PORT')) {
    define('HTTPS_PORT', 443); // Default HTTPS port
}

// Debugging settings
if (ENVIRONMENT === 'dev') {
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', 0);
    ini_set('display_startup_errors', 0);
    error_reporting(0);
}

// LDAP configuration
if (!defined('LDAP_URL')) {
    define('LDAP_URL', ''); // Set the LDAP URL here -- ldap://INCPLGOAADC1.cipla.com
}

// Base URL configuration
if (!defined('BASE_URL')) {
    if (FORCE_HTTPS) {
        $protocol = 'https://';
    } else {
        // Detect current protocol
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
    }
    define('BASE_URL', $protocol.'localhost/proval4/public/');
}

// Other configurations
date_default_timezone_set("Asia/Kolkata");

// Define constants
if (!defined('MAX_LOGIN_ATTEMPTS')) {
    define('MAX_LOGIN_ATTEMPTS', 3);
}

// Rate limiting configuration
if (!defined('RATE_LIMITING_ENABLED')) {
    define('RATE_LIMITING_ENABLED', false); // Enable/disable rate limiting globally
}

// Per-IP rate limiting (individual user quotas)
if (!defined('RATE_LIMIT_LOGIN_MAX')) {
    define('RATE_LIMIT_LOGIN_MAX', 5); // Maximum login attempts per IP
}
if (!defined('RATE_LIMIT_LOGIN_WINDOW')) {
    define('RATE_LIMIT_LOGIN_WINDOW', 300); // Time window in seconds (5 minutes)
}
if (!defined('RATE_LIMIT_LOGIN_LOCKOUT')) {
    define('RATE_LIMIT_LOGIN_LOCKOUT', 1800); // Lockout duration in seconds (30 minutes)
}

// System-wide rate limiting (global quotas for DDoS protection)
if (!defined('RATE_LIMIT_LOGIN_SYSTEM_MAX')) {
    define('RATE_LIMIT_LOGIN_SYSTEM_MAX', 1000); // Maximum login attempts system-wide
}
if (!defined('RATE_LIMIT_LOGIN_SYSTEM_WINDOW')) {
    define('RATE_LIMIT_LOGIN_SYSTEM_WINDOW', 300); // Time window in seconds (5 minutes)
}
if (!defined('RATE_LIMIT_LOGIN_SYSTEM_LOCKOUT')) {
    define('RATE_LIMIT_LOGIN_SYSTEM_LOCKOUT', 600); // System lockout duration (10 minutes)
}

// Per-IP password reset rate limiting
if (!defined('RATE_LIMIT_PASSWORD_RESET_MAX')) {
    define('RATE_LIMIT_PASSWORD_RESET_MAX', 3); // Maximum password reset attempts per IP
}
if (!defined('RATE_LIMIT_PASSWORD_RESET_WINDOW')) {
    define('RATE_LIMIT_PASSWORD_RESET_WINDOW', 900); // Time window in seconds (15 minutes)
}
if (!defined('RATE_LIMIT_PASSWORD_RESET_LOCKOUT')) {
    define('RATE_LIMIT_PASSWORD_RESET_LOCKOUT', 3600); // Lockout duration in seconds (1 hour)
}

// System-wide password reset rate limiting
if (!defined('RATE_LIMIT_PASSWORD_RESET_SYSTEM_MAX')) {
    define('RATE_LIMIT_PASSWORD_RESET_SYSTEM_MAX', 100); // Maximum password resets system-wide
}
if (!defined('RATE_LIMIT_PASSWORD_RESET_SYSTEM_WINDOW')) {
    define('RATE_LIMIT_PASSWORD_RESET_SYSTEM_WINDOW', 900); // Time window in seconds (15 minutes)
}
if (!defined('RATE_LIMIT_PASSWORD_RESET_SYSTEM_LOCKOUT')) {
    define('RATE_LIMIT_PASSWORD_RESET_SYSTEM_LOCKOUT', 1800); // System lockout duration (30 minutes)
}

// Per-IP API request rate limiting
if (!defined('RATE_LIMIT_API_MAX')) {
    define('RATE_LIMIT_API_MAX', 100); // Maximum API requests per IP
}
if (!defined('RATE_LIMIT_API_WINDOW')) {
    define('RATE_LIMIT_API_WINDOW', 60); // Time window in seconds (1 minute)
}
if (!defined('RATE_LIMIT_API_LOCKOUT')) {
    define('RATE_LIMIT_API_LOCKOUT', 300); // Lockout duration in seconds (5 minutes)
}

// System-wide API request rate limiting
if (!defined('RATE_LIMIT_API_SYSTEM_MAX')) {
    define('RATE_LIMIT_API_SYSTEM_MAX', 10000); // Maximum API requests system-wide
}
if (!defined('RATE_LIMIT_API_SYSTEM_WINDOW')) {
    define('RATE_LIMIT_API_SYSTEM_WINDOW', 60); // Time window in seconds (1 minute)
}
if (!defined('RATE_LIMIT_API_SYSTEM_LOCKOUT')) {
    define('RATE_LIMIT_API_SYSTEM_LOCKOUT', 300); // System lockout duration (5 minutes)
}

// Per-IP file upload rate limiting
if (!defined('RATE_LIMIT_FILE_UPLOAD_MAX')) {
    define('RATE_LIMIT_FILE_UPLOAD_MAX', 50); // Maximum file uploads per IP
}
if (!defined('RATE_LIMIT_FILE_UPLOAD_WINDOW')) {
    define('RATE_LIMIT_FILE_UPLOAD_WINDOW', 3600); // Time window in seconds (1 hour)
}
if (!defined('RATE_LIMIT_FILE_UPLOAD_LOCKOUT')) {
    define('RATE_LIMIT_FILE_UPLOAD_LOCKOUT', 1800); // Lockout duration in seconds (30 minutes)
}

// System-wide file upload rate limiting
if (!defined('RATE_LIMIT_FILE_UPLOAD_SYSTEM_MAX')) {
    define('RATE_LIMIT_FILE_UPLOAD_SYSTEM_MAX', 500); // Maximum file uploads system-wide
}
if (!defined('RATE_LIMIT_FILE_UPLOAD_SYSTEM_WINDOW')) {
    define('RATE_LIMIT_FILE_UPLOAD_SYSTEM_WINDOW', 3600); // Time window in seconds (1 hour)
}
if (!defined('RATE_LIMIT_FILE_UPLOAD_SYSTEM_LOCKOUT')) {
    define('RATE_LIMIT_FILE_UPLOAD_SYSTEM_LOCKOUT', 1800); // System lockout duration (30 minutes)
}

// Per-IP form submission rate limiting
if (!defined('RATE_LIMIT_FORM_SUBMISSION_MAX')) {
    define('RATE_LIMIT_FORM_SUBMISSION_MAX', 20); // Maximum form submissions per IP
}
if (!defined('RATE_LIMIT_FORM_SUBMISSION_WINDOW')) {
    define('RATE_LIMIT_FORM_SUBMISSION_WINDOW', 300); // Time window in seconds (5 minutes)
}
if (!defined('RATE_LIMIT_FORM_SUBMISSION_LOCKOUT')) {
    define('RATE_LIMIT_FORM_SUBMISSION_LOCKOUT', 600); // Lockout duration in seconds (10 minutes)
}

// System-wide form submission rate limiting
if (!defined('RATE_LIMIT_FORM_SUBMISSION_SYSTEM_MAX')) {
    define('RATE_LIMIT_FORM_SUBMISSION_SYSTEM_MAX', 2000); // Maximum form submissions system-wide
}
if (!defined('RATE_LIMIT_FORM_SUBMISSION_SYSTEM_WINDOW')) {
    define('RATE_LIMIT_FORM_SUBMISSION_SYSTEM_WINDOW', 300); // Time window in seconds (5 minutes)
}
if (!defined('RATE_LIMIT_FORM_SUBMISSION_SYSTEM_LOCKOUT')) {
    define('RATE_LIMIT_FORM_SUBMISSION_SYSTEM_LOCKOUT', 600); // System lockout duration (10 minutes)
}

// File Upload Configuration
if (!defined('FILE_UPLOAD_MAX_SIZE_MB')) {
    define('FILE_UPLOAD_MAX_SIZE_MB', 4); // 10MB per file
}
if (!defined('FILE_UPLOAD_MAX_SIZE_BYTES')) {
    define('FILE_UPLOAD_MAX_SIZE_BYTES', FILE_UPLOAD_MAX_SIZE_MB * 1024 * 1024);
}
if (!defined('FILE_UPLOAD_ALLOWED_EXTENSIONS')) {
    define('FILE_UPLOAD_ALLOWED_EXTENSIONS', 'pdf'); // Comma-separated: pdf,jpg,png
}
if (!defined('FILE_UPLOAD_ALLOWED_MIME_TYPES')) {
    define('FILE_UPLOAD_ALLOWED_MIME_TYPES', 'application/pdf'); // Comma-separated MIME types
}

// Security headers configuration
if (!defined('ENABLE_SECURITY_HEADERS')) {
    define('ENABLE_SECURITY_HEADERS', true);
}
if (!defined('HSTS_MAX_AGE')) {
    define('HSTS_MAX_AGE', 31536000); // 1 year in seconds
}
if (!defined('HSTS_INCLUDE_SUBDOMAINS')) {
    define('HSTS_INCLUDE_SUBDOMAINS', true);
}
if (!defined('HSTS_PRELOAD')) {
    define('HSTS_PRELOAD', false);
}


// EmailReminder System Configuration
// SMTP settings for automated email reminders
/*
if (!defined('EMAIL_REMINDER_SMTP_HOST')) {
    define('EMAIL_REMINDER_SMTP_HOST', 'smtp.office365.com');
}
if (!defined('EMAIL_REMINDER_SMTP_PORT')) {
    define('EMAIL_REMINDER_SMTP_PORT', 587);
}
if (!defined('EMAIL_REMINDER_SMTP_USERNAME')) {
    define('EMAIL_REMINDER_SMTP_USERNAME', 'systemalert@cipla.com');
}
if (!defined('EMAIL_REMINDER_SMTP_PASSWORD')) {
    define('EMAIL_REMINDER_SMTP_PASSWORD', 'Cipla@321'); // Consider moving to environment variable
}
if (!defined('EMAIL_REMINDER_SMTP_FROM_EMAIL')) {
    define('EMAIL_REMINDER_SMTP_FROM_EMAIL', 'systemalert@cipla.com');
}
if (!defined('EMAIL_REMINDER_SMTP_FROM_NAME')) {
    define('EMAIL_REMINDER_SMTP_FROM_NAME', 'ProVal Communication');
}
if (!defined('EMAIL_REMINDER_SMTP_SECURE')) {
    define('EMAIL_REMINDER_SMTP_SECURE', 'tls'); // 'tls', 'ssl', or 'none'
}
if (!defined('EMAIL_REMINDER_SMTP_AUTH_ENABLED')) {
    define('EMAIL_REMINDER_SMTP_AUTH_ENABLED', true);
}
*/



if (!defined('EMAIL_REMINDER_SMTP_HOST')) {
    define('EMAIL_REMINDER_SMTP_HOST', 'smtp.hostinger.com');
}
if (!defined('EMAIL_REMINDER_SMTP_PORT')) {
    define('EMAIL_REMINDER_SMTP_PORT', 465);
}
if (!defined('EMAIL_REMINDER_SMTP_USERNAME')) {
    define('EMAIL_REMINDER_SMTP_USERNAME', 'postman@palcoa.com');
}
if (!defined('EMAIL_REMINDER_SMTP_PASSWORD')) {
    define('EMAIL_REMINDER_SMTP_PASSWORD', 'Mandar@131620'); // Consider moving to environment variable
}
if (!defined('EMAIL_REMINDER_SMTP_FROM_EMAIL')) {
    define('EMAIL_REMINDER_SMTP_FROM_EMAIL', 'postman@palcoa.com');
}
if (!defined('EMAIL_REMINDER_SMTP_FROM_NAME')) {
    define('EMAIL_REMINDER_SMTP_FROM_NAME', 'ProVal Communication');
}
if (!defined('EMAIL_REMINDER_SMTP_SECURE')) {
    define('EMAIL_REMINDER_SMTP_SECURE', 'ssl'); // 'tls', 'ssl', or 'none'
}
if (!defined('EMAIL_REMINDER_SMTP_AUTH_ENABLED')) {
    define('EMAIL_REMINDER_SMTP_AUTH_ENABLED', true);
}



// Email debug settings (environment-specific)
if (!defined('EMAIL_REMINDER_SMTP_DEBUG_LEVEL')) {
    if (ENVIRONMENT === 'dev') {
        define('EMAIL_REMINDER_SMTP_DEBUG_LEVEL', 2); // Verbose debug in development
    } else {
        define('EMAIL_REMINDER_SMTP_DEBUG_LEVEL', 0); // No debug in production
    }
}

// Email retry and rate limiting
if (!defined('EMAIL_REMINDER_MAX_RETRIES')) {
    define('EMAIL_REMINDER_MAX_RETRIES', 3);
}
if (!defined('EMAIL_REMINDER_RETRY_DELAY')) {
    define('EMAIL_REMINDER_RETRY_DELAY', 300); // 5 minutes between retries (in seconds)
}
if (!defined('EMAIL_REMINDER_RATE_LIMIT_PER_HOUR')) {
    define('EMAIL_REMINDER_RATE_LIMIT_PER_HOUR', 100); // Max 100 emails per hour
}

// Job execution settings
if (!defined('EMAIL_REMINDER_JOB_MAX_EXECUTION_TIME')) {
    define('EMAIL_REMINDER_JOB_MAX_EXECUTION_TIME', 300); // 5 minutes max per job (in seconds)
}
if (!defined('EMAIL_REMINDER_JOB_LOG_RETENTION_DAYS')) {
    define('EMAIL_REMINDER_JOB_LOG_RETENTION_DAYS', 90); // Keep logs for 90 days
}
if (!defined('EMAIL_REMINDER_EMAIL_LOG_RETENTION_DAYS')) {
    define('EMAIL_REMINDER_EMAIL_LOG_RETENTION_DAYS', 365); // Keep email logs for 1 year
}

// EmailReminder job configuration
if (!defined('EMAIL_REMINDER_JOBS_ENABLED')) {
    define('EMAIL_REMINDER_JOBS_ENABLED', true); // Master switch for all email reminder jobs
}
if (!defined('EMAIL_REMINDER_VALIDATE_EMAIL_ADDRESSES')) {
    define('EMAIL_REMINDER_VALIDATE_EMAIL_ADDRESSES', true); // Validate email addresses before sending
}



// Session timeout configuration (in seconds)
if (!defined('SESSION_TIMEOUT')) {
    define('SESSION_TIMEOUT', 300); // 5 minutes - Compliance requirement for user lockout after inactivity
}
if (!defined('SESSION_WARNING_TIME')) {
    define('SESSION_WARNING_TIME', 180); // 3 minutes - Show warning after 3 minutes of inactivity
}

// Validate session timeout configuration
if (SESSION_WARNING_TIME >= SESSION_TIMEOUT) {
    throw new Exception('SESSION_WARNING_TIME (' . SESSION_WARNING_TIME . 's) must be less than SESSION_TIMEOUT (' . SESSION_TIMEOUT . 's)');
}

if (SESSION_TIMEOUT < 120) {
    throw new Exception('SESSION_TIMEOUT must be at least 2 minutes (120 seconds) for security compliance');
}

if (SESSION_WARNING_TIME < 60) {
    throw new Exception('SESSION_WARNING_TIME must be at least 1 minute (60 seconds) for usability');
}

// Calculate remaining time for validation
$remaining_time = SESSION_TIMEOUT - SESSION_WARNING_TIME;
if ($remaining_time < 30) {
    throw new Exception('There must be at least 30 seconds between SESSION_WARNING_TIME and SESSION_TIMEOUT for user response time');
}

if (!defined('SHOW_SESSION_DEBUG_TIMERS')) {
    define('SHOW_SESSION_DEBUG_TIMERS', true); // Set to false to hide debug timers in navbar
}

// Helper function for redirects using BASE_URL
if (!function_exists('redirect')) {
    function redirect($path = '', $query = '') {
        $url = rtrim(BASE_URL, '/') . '/' . ltrim($path, '/');
        if (!empty($query)) {
            $url .= (strpos($url, '?') === false ? '?' : '&') . ltrim($query, '?&');
        }
        
        header('Location: ' . $url);
        exit();
    }
}

// ==============================================
// SECURITY BOOTSTRAP - Add to end of config.php
// ==============================================

// Prevent multiple security initializations
if (!defined('PROVAL_SECURITY_LOADED')) {
    define('PROVAL_SECURITY_LOADED', true);

    // Define security file path helper
    if (!function_exists('security_file_path')) {
        function security_file_path($filename) {
            $base_dir = dirname(__FILE__);
            return $base_dir . '/' . $filename;
        }
    }

    // TIER 1: Core Dependencies (Load First)
    require_once security_file_path('../validation/input_validation_utils.php');
    
    // TIER 2: Session Management
    if (!headers_sent() && session_status() === PHP_SESSION_NONE) {
        require_once security_file_path('../security/session_init.php');
    }
    
    // TIER 3: Core Security Components
    require_once security_file_path('../security/rate_limiting_utils.php');
    require_once security_file_path('../security/auth_utils.php');
    require_once security_file_path('../security/xss_prevention_utils.php');
    
    // TIER 4: Security Middleware (Load After Core)
    require_once security_file_path('../security/security_middleware.php');
    require_once security_file_path('../security/xss_integration_middleware.php');
    
    // TIER 5: Session Timeout (Load After Session Init)
    require_once security_file_path('../security/session_timeout_middleware.php');
    
    // TIER 6: Optional Components (Load Last) - TEMPORARILY DISABLED FOR TESTING
    /*if (file_exists(security_file_path('../security/secure_file_upload_utils.php'))) {
        require_once security_file_path('../security/secure_file_upload_utils.php');
    }*/
    if (file_exists(security_file_path('../security/secure_query_wrapper.php'))) {
        require_once security_file_path('../security/secure_query_wrapper.php');
    }

    // Auto-initialize XSS protection for web requests
    if (isset($_SERVER['REQUEST_METHOD']) && class_exists('XSSIntegrationMiddleware')) {
        XSSIntegrationMiddleware::initialize();
    }

    // Initialize centralized security manager
    require_once(__DIR__ . '/../security/security_manager.php');
    
    // Validation workflow configuration
    if (!defined('VALIDATION_DEVIATION_THRESHOLD_DAYS')) {
        define('VALIDATION_DEVIATION_THRESHOLD_DAYS', 1);
    }
    
    // Security initialization complete flag
    if (!defined('PROVAL_SECURITY_INITIALIZED')) {
        define('PROVAL_SECURITY_INITIALIZED', true);
    }
}

?>