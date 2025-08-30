<?php
/**
 * Login Flow Performance Test
 * Simulates the exact scenario that occurs during login to verify
 * that async email sending is working correctly for web requests
 */

// Security check - allow CLI access for testing
$allowedIPs = ['127.0.0.1', '::1'];
$clientIP = $_SERVER['REMOTE_ADDR'] ?? '';
$isLocalhost = in_array($clientIP, $allowedIPs);
$isCLI = php_sapi_name() === 'cli';

if (!$isLocalhost && !$isCLI) {
    http_response_code(403);
    die('Access denied - Localhost or CLI access only');
}

// Simulate web environment for testing (override CLI detection temporarily)
if ($isCLI) {
    // Set web-like environment variables for testing
    $_SERVER['HTTP_HOST'] = 'localhost';
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
}

require_once 'core/config/config.php';
require_once 'core/email/SmartOTPEmailSender.php';

header('Content-Type: application/json');

echo "=== Login Flow Performance Test ===\n";
echo "Timestamp: " . date('Y-m-d H:i:s') . "\n\n";

$results = [];
$startTime = microtime(true);

try {
    // Test 1: Check current system conditions
    echo "1. System Conditions Check:\n";
    $smartSender = new SmartOTPEmailSender();
    $healthCheck = $smartSender->healthCheck();
    
    echo "   - Async Available: " . ($healthCheck['async_available'] ? 'YES' : 'NO') . "\n";
    echo "   - Should Use Sync: " . ($healthCheck['should_use_sync'] ? 'YES' : 'NO') . "\n";
    echo "   - System Load: " . ($healthCheck['system_load'] ? 'HIGH' : 'NORMAL') . "\n";
    
    // Get system load for display
    if (function_exists('sys_getloadavg')) {
        $load = sys_getloadavg();
        echo "   - Load Average: " . round($load[0], 2) . " (threshold: 4.0 for normal, 8.0 for login)\n";
    }
    echo "\n";
    
    // Test 2: Simulate Login Flow Email Sending (with timing)
    echo "2. Login Flow Email Simulation:\n";
    $testEmail = 'login-test-' . time() . '@example.com';
    
    $loginStartTime = microtime(true);
    
    // This simulates the exact call from checklogin.php with isLoginFlow=true
    $result = $smartSender->sendOTP(
        $testEmail,
        'Login Test User',
        '123456',
        5,
        'LOGIN001',
        1,
        true // isLoginFlow = true (this is the key difference)
    );
    
    $loginEndTime = microtime(true);
    $loginDuration = ($loginEndTime - $loginStartTime) * 1000; // Convert to milliseconds
    
    echo "   - Test Email: $testEmail\n";
    echo "   - Duration: " . round($loginDuration, 2) . " ms\n";
    echo "   - Success: " . ($result['success'] ? 'YES' : 'NO') . "\n";
    echo "   - Async Used: " . (isset($result['async']) && $result['async'] ? 'YES' : 'NO') . "\n";
    echo "   - Result: " . json_encode($result) . "\n";
    echo "\n";
    
    // Test 3: Compare with Resend Flow (non-login)
    echo "3. Resend Flow Email Simulation (for comparison):\n";
    $resendTestEmail = 'resend-test-' . time() . '@example.com';
    
    $resendStartTime = microtime(true);
    
    // This simulates resend with isLoginFlow=false (default)
    $resendResult = $smartSender->sendOTP(
        $resendTestEmail,
        'Resend Test User',
        '654321',
        5,
        'RESEND001',
        1,
        false // isLoginFlow = false (normal resend)
    );
    
    $resendEndTime = microtime(true);
    $resendDuration = ($resendEndTime - $resendStartTime) * 1000;
    
    echo "   - Test Email: $resendTestEmail\n";
    echo "   - Duration: " . round($resendDuration, 2) . " ms\n";
    echo "   - Success: " . ($resendResult['success'] ? 'YES' : 'NO') . "\n";
    echo "   - Async Used: " . (isset($resendResult['async']) && $resendResult['async'] ? 'YES' : 'NO') . "\n";
    echo "   - Result: " . json_encode($resendResult) . "\n";
    echo "\n";
    
    // Test 4: Performance Summary
    echo "4. Performance Summary:\n";
    echo "   - Login Flow Duration: " . round($loginDuration, 2) . " ms\n";
    echo "   - Resend Flow Duration: " . round($resendDuration, 2) . " ms\n";
    echo "   - Performance Target: < 500 ms for login flow\n";
    echo "   - Login Performance: " . ($loginDuration < 500 ? 'PASS' : 'NEEDS IMPROVEMENT') . "\n";
    echo "\n";
    
    // Test 5: Configuration Recommendations
    echo "5. Configuration Status:\n";
    echo "   - EMAIL_ASYNC_ENABLED: " . (EMAIL_ASYNC_ENABLED ? 'ENABLED' : 'DISABLED') . "\n";
    echo "   - FORCE_SYNC_EMAIL: " . (defined('FORCE_SYNC_EMAIL') && FORCE_SYNC_EMAIL ? 'ENABLED (affects all)' : 'DISABLED') . "\n";
    echo "   - FORCE_SYNC_LOGIN_EMAIL: " . (defined('FORCE_SYNC_LOGIN_EMAIL') && FORCE_SYNC_LOGIN_EMAIL ? 'ENABLED (affects login)' : 'DISABLED') . "\n";
    echo "\n";
    
    $totalTime = (microtime(true) - $startTime) * 1000;
    echo "=== TEST COMPLETED ===\n";
    echo "Total test duration: " . round($totalTime, 2) . " ms\n";
    echo "Status: " . ($result['success'] && $loginDuration < 500 ? 'PASS' : 'REVIEW NEEDED') . "\n";
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
}
?>