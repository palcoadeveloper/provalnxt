<?php
/**
 * Scheduled job to clean up expired OTP sessions
 * This script should be run periodically via cron job
 * 
 * Recommended cron schedule: */5 * * * * (every 5 minutes)
 */

// Include configuration and security
require_once __DIR__ . '/../core/config/config.php';
require_once __DIR__ . '/../core/security/two_factor_auth.php';
require_once __DIR__ . '/../core/security/rate_limiting_utils.php';

// Only allow execution from command line or specific IPs
if (php_sapi_name() !== 'cli') {
    // If not CLI, check if it's from a whitelisted IP (localhost, internal network, etc.)
    $allowedIPs = ['127.0.0.1', '::1', 'localhost'];
    $clientIP = $_SERVER['REMOTE_ADDR'] ?? '';
    
    if (!in_array($clientIP, $allowedIPs)) {
        http_response_code(403);
        die('Access denied. This script can only be executed from command line or authorized IPs.');
    }
}

// Set execution time limit
set_time_limit(300); // 5 minutes max

// Log start of cleanup
error_log("Starting OTP session cleanup job at " . date('Y-m-d H:i:s'));

$startTime = microtime(true);
$cleanupResults = [
    'otp_sessions_cleaned' => 0,
    'rate_limit_data_cleaned' => 0,
    'errors' => []
];

try {
    // Clean up expired OTP sessions
    $beforeCount = DB::queryFirstField("SELECT COUNT(*) FROM user_otp_sessions WHERE expires_at < NOW() OR is_used = 'Yes'");
    
    TwoFactorAuth::cleanupExpiredSessions();
    
    $afterCount = DB::queryFirstField("SELECT COUNT(*) FROM user_otp_sessions WHERE expires_at < NOW() OR is_used = 'Yes'");
    $cleanupResults['otp_sessions_cleaned'] = max(0, $beforeCount - $afterCount);
    
    error_log("Cleaned up " . $cleanupResults['otp_sessions_cleaned'] . " expired OTP sessions");
    
} catch (Exception $e) {
    $cleanupResults['errors'][] = 'OTP session cleanup failed: ' . $e->getMessage();
    error_log("Error during OTP session cleanup: " . $e->getMessage());
}

try {
    // Clean up expired rate limiting data
    $beforeRLCount = DB::queryFirstField("
        SELECT COUNT(*) FROM rate_limits 
        WHERE created_at < DATE_SUB(NOW(), INTERVAL 1 DAY)
    ");
    
    RateLimiter::cleanExpiredData();
    
    $afterRLCount = DB::queryFirstField("
        SELECT COUNT(*) FROM rate_limits 
        WHERE created_at < DATE_SUB(NOW(), INTERVAL 1 DAY)  
    ");
    
    $cleanupResults['rate_limit_data_cleaned'] = max(0, $beforeRLCount - $afterRLCount);
    
    error_log("Cleaned up " . $cleanupResults['rate_limit_data_cleaned'] . " expired rate limit entries");
    
} catch (Exception $e) {
    $cleanupResults['errors'][] = 'Rate limit cleanup failed: ' . $e->getMessage();
    error_log("Error during rate limit cleanup: " . $e->getMessage());
}

// Additional cleanup: Remove OTP sessions older than 24 hours regardless of status
try {
    $oldSessionsQuery = "DELETE FROM user_otp_sessions WHERE created_at < DATE_SUB(NOW(), INTERVAL 24 HOUR)";
    $oldSessionsCount = DB::affectedRows($oldSessionsQuery);
    DB::query($oldSessionsQuery);
    
    if ($oldSessionsCount > 0) {
        error_log("Removed {$oldSessionsCount} OTP sessions older than 24 hours");
    }
    
} catch (Exception $e) {
    $cleanupResults['errors'][] = 'Old OTP session cleanup failed: ' . $e->getMessage();
    error_log("Error during old OTP session cleanup: " . $e->getMessage());
}

$executionTime = round((microtime(true) - $startTime) * 1000, 2);

// Log completion
$logMessage = sprintf(
    "OTP cleanup job completed in %sms. OTP sessions: %d, Rate limits: %d, Errors: %d",
    $executionTime,
    $cleanupResults['otp_sessions_cleaned'],
    $cleanupResults['rate_limit_data_cleaned'],
    count($cleanupResults['errors'])
);

error_log($logMessage);

// If running from web (authorized IP), return JSON response
if (php_sapi_name() !== 'cli') {
    header('Content-Type: application/json');
    echo json_encode([
        'status' => 'completed',
        'execution_time_ms' => $executionTime,
        'results' => $cleanupResults,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
} else {
    // CLI output
    echo "OTP Session Cleanup Job Results:\n";
    echo "================================\n";
    echo "Execution Time: {$executionTime}ms\n";
    echo "OTP Sessions Cleaned: " . $cleanupResults['otp_sessions_cleaned'] . "\n";
    echo "Rate Limit Entries Cleaned: " . $cleanupResults['rate_limit_data_cleaned'] . "\n";
    echo "Errors: " . count($cleanupResults['errors']) . "\n";
    
    if (!empty($cleanupResults['errors'])) {
        echo "\nErrors encountered:\n";
        foreach ($cleanupResults['errors'] as $error) {
            echo "- $error\n";
        }
    }
    
    echo "\nCompleted at: " . date('Y-m-d H:i:s') . "\n";
}
?>