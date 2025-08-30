<?php
/**
 * Background Email Sender for OTP Emails
 * Handles asynchronous email sending to improve user experience
 * 
 * Usage: php background_email_sender.php email@domain.com "User Name" "123456" 5 "EMP001" 1
 */

// Prevent header output when run from CLI
if (php_sapi_name() === 'cli') {
    // Set flag to prevent header generation in web-oriented includes
    define('CLI_MODE', true);
}

// Security: Only allow execution from command line
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    die('Access denied: This script can only be run from command line');
}

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/BasicOTPEmailService.php';
require_once __DIR__ . '/../security/rate_limiting_utils.php';

// Validate arguments
if ($argc !== 7) {
    error_log("[BACKGROUND EMAIL] Invalid arguments. Usage: php background_email_sender.php email name otpCode validityMinutes employeeId unitId");
    exit(1);
}

// Parse command line arguments
$recipientEmail = $argv[1];
$recipientName = $argv[2];
$otpCode = $argv[3];
$validityMinutes = (int)$argv[4];
$employeeId = $argv[5];
$unitId = (int)$argv[6];

// Validate input parameters
if (!filter_var($recipientEmail, FILTER_VALIDATE_EMAIL)) {
    error_log("[BACKGROUND EMAIL] Invalid email address: $recipientEmail");
    exit(1);
}

if (empty($otpCode) || strlen($otpCode) < 4 || strlen($otpCode) > 8) {
    error_log("[BACKGROUND EMAIL] Invalid OTP code length");
    exit(1);
}

if ($validityMinutes < 1 || $validityMinutes > 15) {
    error_log("[BACKGROUND EMAIL] Invalid validity minutes: $validityMinutes");
    exit(1);
}

// Set execution timeout
set_time_limit(EMAIL_BACKGROUND_TIMEOUT);

// Start background email sending process
error_log("[BACKGROUND EMAIL] Starting background email send for user: $employeeId");

try {
    // Check rate limiting for this specific email/user
    $rateLimitKey = 'otp_email_' . $recipientEmail;
    $rateLimitResult = RateLimiter::checkRateLimit($rateLimitKey, 5, 300); // 5 emails per 5 minutes
    
    if (!$rateLimitResult['allowed']) {
        error_log("[BACKGROUND EMAIL] Rate limit exceeded for: $recipientEmail");
        exit(2); // Exit code 2 indicates rate limiting
    }
    
    // Additional global rate limiting check
    $globalRateLimitKey = 'global_otp_emails';
    $globalRateLimitResult = RateLimiter::checkRateLimit($globalRateLimitKey, EMAIL_GLOBAL_RATE_LIMIT_PER_MINUTE, 60);
    
    if (!$globalRateLimitResult['allowed']) {
        error_log("[BACKGROUND EMAIL] Global rate limit exceeded");
        // Wait a bit and try again for global limits
        sleep(2);
        $globalRateLimitResult = RateLimiter::checkRateLimit($globalRateLimitKey, EMAIL_GLOBAL_RATE_LIMIT_PER_MINUTE, 60);
        if (!$globalRateLimitResult['allowed']) {
            error_log("[BACKGROUND EMAIL] Global rate limit still exceeded after retry");
            exit(3); // Exit code 3 indicates global rate limiting
        }
    }
    
    // Create optimized email service instance
    $emailService = new BasicOTPEmailService();
    
    // Send the email
    $result = $emailService->sendOTP(
        $recipientEmail,
        $recipientName,
        $otpCode,
        $validityMinutes,
        $employeeId,
        $unitId
    );
    
    if ($result['success']) {
        error_log("[BACKGROUND EMAIL] Successfully sent OTP email to: $recipientEmail for user: $employeeId");
        
        // Log security event if function exists
        if (function_exists('logSecurityEvent')) {
            logSecurityEvent($employeeId, 'otp_email_sent_async', 
                "OTP email sent successfully via background process to: $recipientEmail", $unitId);
        }
        
        exit(0); // Success
    } else {
        error_log("[BACKGROUND EMAIL] Failed to send OTP email: " . ($result['error'] ?? 'Unknown error'));
        exit(4); // Exit code 4 indicates email sending failure
    }
    
} catch (Exception $e) {
    error_log("[BACKGROUND EMAIL] Exception occurred: " . $e->getMessage());
    error_log("[BACKGROUND EMAIL] Stack trace: " . $e->getTraceAsString());
    exit(5); // Exit code 5 indicates exception
}
?>