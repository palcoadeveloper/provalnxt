<?php
/**
 * SMTP Connection Cleanup Job
 * Cleans up old SMTP connections and maintains connection health
 * Run this every 5-10 minutes via cron job
 */

require_once __DIR__ . '/../core/config/config.php';
require_once __DIR__ . '/../core/email/SMTPConnectionManager.php';

// Security: Only allow execution from command line or specific IPs
if (php_sapi_name() !== 'cli') {
    // Allow execution from localhost/specific IPs for web-based cron
    $allowedIPs = ['127.0.0.1', '::1', 'localhost'];
    $clientIP = $_SERVER['REMOTE_ADDR'] ?? '';
    
    if (!in_array($clientIP, $allowedIPs)) {
        http_response_code(403);
        die('Access denied');
    }
}

$startTime = time();
error_log("[SMTP CLEANUP] Starting SMTP connection cleanup job");

try {
    // Get connection stats before cleanup
    $connectionManager = SMTPConnectionManager::getInstance();
    $statsBefore = $connectionManager->getConnectionStats();
    
    error_log("[SMTP CLEANUP] Pre-cleanup stats: " . json_encode($statsBefore));
    
    // Perform cleanup
    SMTPConnectionManager::cleanup();
    
    // Get stats after cleanup
    $statsAfter = $connectionManager->getConnectionStats();
    
    error_log("[SMTP CLEANUP] Post-cleanup stats: " . json_encode($statsAfter));
    
    // Log performance metrics
    $executionTime = time() - $startTime;
    $logMessage = sprintf(
        "[SMTP CLEANUP] Completed in %d seconds. Connection was %s",
        $executionTime,
        $statsBefore['has_connection'] ? 'cleaned up' : 'already clean'
    );
    
    error_log($logMessage);
    
    // Output results for web-based monitoring
    if (php_sapi_name() !== 'cli') {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'execution_time' => $executionTime,
            'stats_before' => $statsBefore,
            'stats_after' => $statsAfter,
            'timestamp' => date('Y-m-d H:i:s')
        ]);
    }
    
} catch (Exception $e) {
    $error = "SMTP cleanup failed: " . $e->getMessage();
    error_log("[SMTP CLEANUP] ERROR: " . $error);
    
    if (php_sapi_name() !== 'cli') {
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'error' => $error,
            'timestamp' => date('Y-m-d H:i:s')
        ]);
    }
    
    exit(1);
}

exit(0);
?>