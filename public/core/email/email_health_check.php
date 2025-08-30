<?php
/**
 * Email Service Health Check Endpoint
 * Provides monitoring information about email service performance
 * Access: /core/email/email_health_check.php
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/BasicOTPEmailService.php';

// Security: Only allow access from localhost or authenticated sessions
$allowedIPs = ['127.0.0.1', '::1'];
$clientIP = $_SERVER['REMOTE_ADDR'] ?? '';

$hasValidSession = isset($_SESSION['user_name']) && isset($_SESSION['user_role']);
$isLocalhost = in_array($clientIP, $allowedIPs);
$isAdmin = $hasValidSession && ($_SESSION['user_role'] === 'admin' || $_SESSION['user_role'] === 'superadmin');

if (!$isLocalhost && !$isAdmin) {
    http_response_code(403);
    die('Access denied');
}

header('Content-Type: application/json');

try {
    $emailService = new BasicOTPEmailService();
    
    // Get performance stats
    $performanceStats = $emailService->getPerformanceStats();
    
    // Get health status
    $healthStatus = $emailService->healthCheck();
    
    // Additional system information
    $systemInfo = [
        'php_version' => PHP_VERSION,
        'server_time' => date('Y-m-d H:i:s'),
        'server_timezone' => date_default_timezone_get(),
        'memory_usage' => memory_get_usage(true),
        'peak_memory_usage' => memory_get_peak_usage(true)
    ];
    
    $response = [
        'timestamp' => date('c'),
        'status' => $healthStatus['status'],
        'health' => $healthStatus,
        'performance' => $performanceStats,
        'system' => $systemInfo
    ];
    
    // Set appropriate HTTP status code
    http_response_code($healthStatus['status'] === 'healthy' ? 200 : 503);
    
    echo json_encode($response, JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'timestamp' => date('c'),
        'status' => 'error',
        'error' => $e->getMessage()
    ], JSON_PRETTY_PRINT);
}
?>