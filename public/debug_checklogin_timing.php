<?php
/**
 * Debug Timing for checklogin.php
 * This script simulates the exact flow of checklogin.php with timing measurements
 */

// Start total timing
$totalStart = microtime(true);

echo "=== CheckLogin.php Performance Debug ===\n";
echo "Timestamp: " . date('Y-m-d H:i:s') . "\n\n";

// Simulate the exact includes from checklogin.php
echo "1. Loading Dependencies:\n";

$step1Start = microtime(true);
require_once 'core/config/config.php';
$step1End = microtime(true);
echo "   - config.php: " . round(($step1End - $step1Start) * 1000, 2) . " ms\n";

$step2Start = microtime(true);
require_once 'core/security/session_init.php';
$step2End = microtime(true);
echo "   - session_init.php: " . round(($step2End - $step2Start) * 1000, 2) . " ms\n";

$step3Start = microtime(true);
require_once 'core/security/security_middleware.php';
$step3End = microtime(true);
echo "   - security_middleware.php: " . round(($step3End - $step3Start) * 1000, 2) . " ms\n";

$step4Start = microtime(true);
require_once 'core/config/db.class.php';
$step4End = microtime(true);
echo "   - db.class.php: " . round(($step4End - $step4Start) * 1000, 2) . " ms\n";

$step5Start = microtime(true);
require_once 'core/security/auth_utils.php';
require_once 'core/security/rate_limiting_utils.php';
require_once 'core/security/two_factor_auth.php';
require_once 'core/email/BasicOTPEmailService.php';
$step5End = microtime(true);
echo "   - auth & security utilities: " . round(($step5End - $step5Start) * 1000, 2) . " ms\n";

$loadingTime = ($step5End - $step1Start) * 1000;
echo "   - Total Loading Time: " . round($loadingTime, 2) . " ms\n\n";

// Simulate POST request processing
echo "2. Simulating Login Process:\n";

// Simulate rate limiting check
$rateLimitStart = microtime(true);
require_once 'core/security/rate_limiting_utils.php';
// $rateLimitResult = RateLimiter::checkRateLimit('login_attempts');
$rateLimitEnd = microtime(true);
echo "   - Rate limit check: " . round(($rateLimitEnd - $rateLimitStart) * 1000, 2) . " ms\n";

// Simulate user credential verification
$authStart = microtime(true);
// Simulate getting user details (this might be slow)
try {
    // Test database connection first
    $dbTestStart = microtime(true);
    $testQuery = DB::queryFirstRow("SELECT 1 as test");
    $dbTestEnd = microtime(true);
    echo "   - Database connectivity test: " . round(($dbTestEnd - $dbTestStart) * 1000, 2) . " ms\n";
    
    // Simulate the actual user lookup query
    $userLookupStart = microtime(true);
    $testUser = DB::queryFirstRow(
        "SELECT user_id, user_domain_id, user_name,user_email, u1.unit_id, unit_name, unit_site, 
                department_id, is_qa_head, is_unit_head, is_admin, is_super_admin, 
                is_account_locked, is_dept_head, user_status, user_password, employee_id
         FROM users u1 LEFT JOIN units u2 ON u1.unit_id = u2.unit_id
         WHERE user_domain_id = %s and user_type='employee' LIMIT 1", 
        'testuser'
    );
    $userLookupEnd = microtime(true);
    echo "   - User lookup query: " . round(($userLookupEnd - $userLookupStart) * 1000, 2) . " ms\n";
    
} catch (Exception $e) {
    echo "   - Database error: " . $e->getMessage() . "\n";
}
$authEnd = microtime(true);
echo "   - Total auth simulation: " . round(($authEnd - $authStart) * 1000, 2) . " ms\n";

// Simulate 2FA process
echo "\n3. Simulating 2FA Process:\n";

$twoFAStart = microtime(true);

// Simulate checking 2FA config
$configStart = microtime(true);
try {
    // This simulates TwoFactorAuth::getUnitTwoFactorConfig()
    $twoFAConfig = DB::queryFirstRow("SELECT two_factor_enabled FROM units WHERE unit_id = %i LIMIT 1", 1);
    $configEnd = microtime(true);
    echo "   - 2FA config check: " . round(($configEnd - $configStart) * 1000, 2) . " ms\n";
} catch (Exception $e) {
    echo "   - 2FA config error: " . $e->getMessage() . "\n";
    $configEnd = microtime(true);
}

// Simulate OTP session creation
$otpSessionStart = microtime(true);
try {
    // This simulates the OTP session creation queries
    $sessionQuery = "INSERT INTO user_otp_sessions (user_id, unit_id, employee_id, session_token, otp_code, expires_at, ip_address, user_agent) VALUES (%i, %i, %s, %s, %s, DATE_ADD(NOW(), INTERVAL 5 MINUTE), %s, %s)";
    // We won't actually insert, just measure query prep time
} catch (Exception $e) {
    echo "   - OTP session error: " . $e->getMessage() . "\n";
}
$otpSessionEnd = microtime(true);
echo "   - OTP session simulation: " . round(($otpSessionEnd - $otpSessionStart) * 1000, 2) . " ms\n";

// Simulate email sending
$emailStart = microtime(true);
require_once 'core/email/SmartOTPEmailSender.php';
$smartEmailSender = new SmartOTPEmailSender();

// Test what mode it would choose
$healthCheck = $smartEmailSender->healthCheck();
echo "   - Email sender health check: " . round((microtime(true) - $emailStart) * 1000, 2) . " ms\n";

$emailEnd = microtime(true);
$twoFAEnd = microtime(true);
echo "   - Total 2FA simulation: " . round(($twoFAEnd - $twoFAStart) * 1000, 2) . " ms\n";

$totalEnd = microtime(true);
$totalTime = ($totalEnd - $totalStart) * 1000;

echo "\n=== TIMING SUMMARY ===\n";
echo "Total execution time: " . round($totalTime, 2) . " ms\n";
echo "Loading dependencies: " . round($loadingTime, 2) . " ms (" . round(($loadingTime / $totalTime) * 100, 1) . "%)\n";
echo "Authentication: " . round(($authEnd - $authStart) * 1000, 2) . " ms (" . round((($authEnd - $authStart) * 1000 / $totalTime) * 100, 1) . "%)\n";
echo "2FA processing: " . round(($twoFAEnd - $twoFAStart) * 1000, 2) . " ms (" . round((($twoFAEnd - $twoFAStart) * 1000 / $totalTime) * 100, 1) . "%)\n";

echo "\n=== ANALYSIS ===\n";
if ($totalTime > 2000) {
    echo "⚠️  SLOW PERFORMANCE DETECTED (> 2000ms)\n";
    if ($loadingTime > 1000) {
        echo "❌ ISSUE: Dependency loading is slow\n";
    }
    if (($authEnd - $authStart) * 1000 > 1000) {
        echo "❌ ISSUE: Authentication/Database queries are slow\n";
    }
    if (($twoFAEnd - $twoFAStart) * 1000 > 1000) {
        echo "❌ ISSUE: 2FA processing is slow\n";
    }
} else {
    echo "✅ Performance appears normal in simulation\n";
    echo "💡 The delay might be specific to actual login processing or network conditions\n";
}

echo "\n=== ENVIRONMENT INFO ===\n";
echo "Environment: " . ENVIRONMENT . "\n";
echo "PHP SAPI: " . php_sapi_name() . "\n";
if (function_exists('sys_getloadavg')) {
    $load = sys_getloadavg();
    echo "System load: " . round($load[0], 2) . "\n";
}
echo "Memory usage: " . round(memory_get_usage(true) / 1024 / 1024, 2) . " MB\n";
?>