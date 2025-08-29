<?php
/**
 * EmailReminder System Health Check
 * 
 * Utility script to check the health of the EmailReminder framework.
 * Performs various system checks and returns status information.
 * 
 * @author ProVal System
 * @version 1.0
 */

require_once('../config/config.php');

// Start the session for access control
session_start();

// Check admin permissions if called via web
if (!isset($_SESSION['user_name']) || !($_SESSION['is_admin'] === 'Yes' || $_SESSION['is_super_admin'] === 'Yes')) {
    if (!defined('HEALTH_CHECK_CLI')) {
        header('HTTP/1.0 403 Forbidden');
        exit(json_encode(['status' => 'error', 'message' => 'Access denied']));
    }
}

include_once '../config/db.class.php';
require_once 'EmailReminderLogger.php';

// Initialize logger
$logger = new EmailReminderLogger();

/**
 * Perform comprehensive health checks
 */
function performHealthCheck() {
    global $logger;
    
    $checks = [];
    $overallStatus = 'healthy';
    $issues = [];
    
    try {
        // 1. Database connectivity check
        $checks['database'] = checkDatabaseConnectivity();
        if (!$checks['database']['status']) {
            $overallStatus = 'unhealthy';
            $issues[] = 'Database connectivity issues';
        }
        
        // 2. Required tables check
        $checks['tables'] = checkRequiredTables();
        if (!$checks['tables']['status']) {
            $overallStatus = 'unhealthy';
            $issues[] = 'Missing required database tables';
        }
        
        // 3. EmailReminder core files check
        $checks['core_files'] = checkCoreFiles();
        if (!$checks['core_files']['status']) {
            $overallStatus = 'warning';
            $issues[] = 'Missing core EmailReminder files';
        }
        
        // 4. Job execution status check
        $checks['recent_jobs'] = checkRecentJobExecution();
        if (!$checks['recent_jobs']['status']) {
            $overallStatus = ($overallStatus === 'healthy') ? 'warning' : $overallStatus;
            $issues[] = 'Issues with recent job executions';
        }
        
        // 5. Email configuration check
        $checks['email_config'] = checkEmailConfiguration();
        if (!$checks['email_config']['status']) {
            $overallStatus = ($overallStatus === 'healthy') ? 'warning' : $overallStatus;
            $issues[] = 'Email configuration issues';
        }
        
        // 6. SMTP connectivity check
        $checks['smtp'] = checkSMTPConnectivity();
        if (!$checks['smtp']['status']) {
            $overallStatus = ($overallStatus === 'healthy') ? 'warning' : $overallStatus;
            $issues[] = 'SMTP connectivity issues';
        }
        
        // 7. Disk space check
        $checks['disk_space'] = checkDiskSpace();
        if (!$checks['disk_space']['status']) {
            $overallStatus = ($overallStatus === 'healthy') ? 'warning' : $overallStatus;
            $issues[] = 'Low disk space';
        }
        
        // 8. Recent errors check
        $checks['recent_errors'] = checkRecentErrors();
        if (!$checks['recent_errors']['status']) {
            $overallStatus = ($overallStatus === 'healthy') ? 'warning' : $overallStatus;
            $issues[] = 'Recent system errors detected';
        }
        
        // 9. Performance metrics check
        $checks['performance'] = checkPerformanceMetrics();
        if (!$checks['performance']['status']) {
            $overallStatus = ($overallStatus === 'healthy') ? 'warning' : $overallStatus;
            $issues[] = 'Performance issues detected';
        }
        
    } catch (Exception $e) {
        $logger->logError('HealthCheck', 'Health check failed: ' . $e->getMessage());
        $overallStatus = 'error';
        $issues[] = 'Health check execution failed: ' . $e->getMessage();
    }
    
    // Generate summary message
    $message = generateHealthSummary($overallStatus, $issues, $checks);
    
    // Log health check result
    $logger->logInfo('HealthCheck', "System health check completed: $overallStatus");
    
    return [
        'status' => $overallStatus,
        'message' => $message,
        'checks' => $checks,
        'issues' => $issues,
        'timestamp' => date('Y-m-d H:i:s')
    ];
}

/**
 * Check database connectivity
 */
function checkDatabaseConnectivity() {
    try {
        $result = DB::queryFirstField("SELECT 1");
        return [
            'status' => true,
            'message' => 'Database connection successful',
            'details' => 'Connected to ' . DB_NAME
        ];
    } catch (Exception $e) {
        return [
            'status' => false,
            'message' => 'Database connection failed',
            'details' => $e->getMessage()
        ];
    }
}

/**
 * Check required EmailReminder tables
 */
function checkRequiredTables() {
    $requiredTables = [
        'tbl_email_reminder_job_logs',
        'tbl_email_reminder_logs',
        'tbl_email_reminder_recipients',
        'tbl_email_reminder_system_logs',
        'tbl_email_configuration'
    ];
    
    $missingTables = [];
    
    foreach ($requiredTables as $table) {
        try {
            $exists = DB::queryFirstField(
                "SELECT COUNT(*) FROM information_schema.tables 
                 WHERE table_name = %s AND table_schema = DATABASE()",
                $table
            );
            
            if (!$exists) {
                $missingTables[] = $table;
            }
        } catch (Exception $e) {
            $missingTables[] = $table . ' (check failed)';
        }
    }
    
    if (empty($missingTables)) {
        return [
            'status' => true,
            'message' => 'All required tables exist',
            'details' => count($requiredTables) . ' tables verified'
        ];
    } else {
        return [
            'status' => false,
            'message' => 'Missing required tables',
            'details' => 'Missing: ' . implode(', ', $missingTables)
        ];
    }
}

/**
 * Check core EmailReminder files
 */
function checkCoreFiles() {
    $baseDir = dirname(__DIR__);
    $requiredFiles = [
        'core/EmailReminderService.php',
        'core/EmailReminderBaseJob.php',
        'core/EmailReminderLogger.php',
        'scheduled_jobs/EmailReminderJobRunner.php'
    ];
    
    $missingFiles = [];
    
    foreach ($requiredFiles as $file) {
        if (!file_exists($baseDir . '/' . $file)) {
            $missingFiles[] = $file;
        }
    }
    
    // Check job files
    $jobFiles = [
        'scheduled_jobs/jobs/EmailReminderValidationNotStarted10Days.php',
        'scheduled_jobs/jobs/EmailReminderValidationNotStarted30Days.php',
        'scheduled_jobs/jobs/EmailReminderValidationInProgress30Days.php',
        'scheduled_jobs/jobs/EmailReminderValidationInProgress35Days.php',
        'scheduled_jobs/jobs/EmailReminderValidationInProgress38Days.php'
    ];
    
    foreach ($jobFiles as $file) {
        if (!file_exists($baseDir . '/' . $file)) {
            $missingFiles[] = $file;
        }
    }
    
    if (empty($missingFiles)) {
        return [
            'status' => true,
            'message' => 'All core files present',
            'details' => count($requiredFiles) + count($jobFiles) . ' files verified'
        ];
    } else {
        return [
            'status' => false,
            'message' => 'Missing core files',
            'details' => 'Missing: ' . implode(', ', $missingFiles)
        ];
    }
}

/**
 * Check recent job execution status
 */
function checkRecentJobExecution() {
    try {
        $recentJobs = DB::queryFirstRow(
            "SELECT 
                COUNT(*) as total_jobs,
                COUNT(CASE WHEN status = 'failed' THEN 1 END) as failed_jobs,
                COUNT(CASE WHEN status = 'completed' THEN 1 END) as successful_jobs,
                MAX(execution_start_time) as last_execution
             FROM tbl_email_reminder_job_logs 
             WHERE execution_start_time >= DATE_SUB(NOW(), INTERVAL 24 HOUR)"
        );
        
        if ($recentJobs['total_jobs'] == 0) {
            return [
                'status' => false,
                'message' => 'No recent job executions',
                'details' => 'No jobs executed in the last 24 hours'
            ];
        }
        
        $failureRate = $recentJobs['failed_jobs'] / $recentJobs['total_jobs'];
        
        if ($failureRate > 0.2) { // More than 20% failure rate
            return [
                'status' => false,
                'message' => 'High job failure rate',
                'details' => "Failed: {$recentJobs['failed_jobs']}/{$recentJobs['total_jobs']} jobs"
            ];
        }
        
        return [
            'status' => true,
            'message' => 'Recent job executions healthy',
            'details' => "Successful: {$recentJobs['successful_jobs']}/{$recentJobs['total_jobs']} jobs"
        ];
        
    } catch (Exception $e) {
        return [
            'status' => false,
            'message' => 'Unable to check job status',
            'details' => $e->getMessage()
        ];
    }
}

/**
 * Check email configuration
 */
function checkEmailConfiguration() {
    try {
        $configStats = DB::queryFirstRow(
            "SELECT 
                COUNT(*) as total_configs,
                COUNT(CASE WHEN email_enabled = 1 THEN 1 END) as enabled_configs
             FROM tbl_email_configuration"
        );
        
        if ($configStats['total_configs'] == 0) {
            return [
                'status' => false,
                'message' => 'No email configurations found',
                'details' => 'System needs email configuration setup'
            ];
        }
        
        if ($configStats['enabled_configs'] == 0) {
            return [
                'status' => false,
                'message' => 'No enabled email configurations',
                'details' => 'All email configurations are disabled'
            ];
        }
        
        return [
            'status' => true,
            'message' => 'Email configurations present',
            'details' => "Enabled: {$configStats['enabled_configs']}/{$configStats['total_configs']} configurations"
        ];
        
    } catch (Exception $e) {
        return [
            'status' => false,
            'message' => 'Unable to check email configuration',
            'details' => $e->getMessage()
        ];
    }
}

/**
 * Check SMTP connectivity
 */
function checkSMTPConnectivity() {
    try {
        // Test SMTP connection using the configured settings
        $smtp_host = defined('EMAIL_REMINDER_SMTP_HOST') ? EMAIL_REMINDER_SMTP_HOST : SMTP_HOST;
        $smtp_port = defined('EMAIL_REMINDER_SMTP_PORT') ? EMAIL_REMINDER_SMTP_PORT : SMTP_PORT;
        
        if (empty($smtp_host)) {
            return [
                'status' => false,
                'message' => 'SMTP host not configured',
                'details' => 'EMAIL_REMINDER_SMTP_HOST or SMTP_HOST not set'
            ];
        }
        
        // Try to connect to SMTP server
        $connection = @fsockopen($smtp_host, $smtp_port, $errno, $errstr, 10);
        
        if (!$connection) {
            return [
                'status' => false,
                'message' => 'SMTP connection failed',
                'details' => "Cannot connect to $smtp_host:$smtp_port - $errstr"
            ];
        }
        
        fclose($connection);
        
        return [
            'status' => true,
            'message' => 'SMTP connection successful',
            'details' => "Connected to $smtp_host:$smtp_port"
        ];
        
    } catch (Exception $e) {
        return [
            'status' => false,
            'message' => 'SMTP check failed',
            'details' => $e->getMessage()
        ];
    }
}

/**
 * Check disk space
 */
function checkDiskSpace() {
    try {
        $freeBytes = disk_free_space(__DIR__);
        $totalBytes = disk_total_space(__DIR__);
        
        if ($freeBytes === false || $totalBytes === false) {
            return [
                'status' => false,
                'message' => 'Unable to check disk space',
                'details' => 'Disk space information unavailable'
            ];
        }
        
        $freePercent = ($freeBytes / $totalBytes) * 100;
        $freeGB = round($freeBytes / (1024 * 1024 * 1024), 2);
        
        if ($freePercent < 10) { // Less than 10% free
            return [
                'status' => false,
                'message' => 'Low disk space',
                'details' => "Only {$freeGB}GB ({$freePercent}%) free"
            ];
        }
        
        return [
            'status' => true,
            'message' => 'Sufficient disk space',
            'details' => "{$freeGB}GB ({$freePercent}%) free"
        ];
        
    } catch (Exception $e) {
        return [
            'status' => false,
            'message' => 'Disk space check failed',
            'details' => $e->getMessage()
        ];
    }
}

/**
 * Check for recent errors
 */
function checkRecentErrors() {
    try {
        $errorStats = DB::queryFirstRow(
            "SELECT 
                COUNT(*) as total_errors,
                COUNT(CASE WHEN log_level = 'ERROR' THEN 1 END) as critical_errors,
                COUNT(CASE WHEN log_level = 'WARNING' THEN 1 END) as warnings
             FROM tbl_email_reminder_system_logs 
             WHERE log_datetime >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
               AND log_level IN ('ERROR', 'WARNING')"
        );
        
        if ($errorStats['critical_errors'] > 10) {
            return [
                'status' => false,
                'message' => 'High error rate',
                'details' => "{$errorStats['critical_errors']} errors in last 24 hours"
            ];
        }
        
        if ($errorStats['total_errors'] > 0) {
            return [
                'status' => true,
                'message' => 'Some recent errors',
                'details' => "{$errorStats['critical_errors']} errors, {$errorStats['warnings']} warnings"
            ];
        }
        
        return [
            'status' => true,
            'message' => 'No recent errors',
            'details' => 'Clean error log for last 24 hours'
        ];
        
    } catch (Exception $e) {
        return [
            'status' => false,
            'message' => 'Unable to check error logs',
            'details' => $e->getMessage()
        ];
    }
}

/**
 * Check performance metrics
 */
function checkPerformanceMetrics() {
    try {
        $perfStats = DB::queryFirstRow(
            "SELECT 
                AVG(execution_time_seconds) as avg_execution_time,
                MAX(execution_time_seconds) as max_execution_time,
                COUNT(CASE WHEN execution_time_seconds > 300 THEN 1 END) as slow_jobs
             FROM tbl_email_reminder_job_logs 
             WHERE execution_start_time >= DATE_SUB(NOW(), INTERVAL 7 DAY)
               AND status = 'completed'"
        );
        
        if ($perfStats['avg_execution_time'] > 180) { // Average over 3 minutes
            return [
                'status' => false,
                'message' => 'Poor performance',
                'details' => "Average execution time: " . round($perfStats['avg_execution_time'], 1) . " seconds"
            ];
        }
        
        if ($perfStats['slow_jobs'] > 5) {
            return [
                'status' => false,
                'message' => 'Many slow jobs',
                'details' => "{$perfStats['slow_jobs']} jobs took over 5 minutes"
            ];
        }
        
        return [
            'status' => true,
            'message' => 'Good performance',
            'details' => "Average execution time: " . round($perfStats['avg_execution_time'], 1) . " seconds"
        ];
        
    } catch (Exception $e) {
        return [
            'status' => false,
            'message' => 'Unable to check performance',
            'details' => $e->getMessage()
        ];
    }
}

/**
 * Generate health summary message
 */
function generateHealthSummary($overallStatus, $issues, $checks) {
    $passedChecks = count(array_filter($checks, function($check) {
        return $check['status'] === true;
    }));
    $totalChecks = count($checks);
    
    $html = "<h5>System Health: " . strtoupper($overallStatus) . "</h5>";
    $html .= "<p><strong>Overall Status:</strong> {$passedChecks}/{$totalChecks} checks passed</p>";
    
    if (!empty($issues)) {
        $html .= "<p><strong>Issues Found:</strong></p><ul>";
        foreach ($issues as $issue) {
            $html .= "<li>" . htmlspecialchars($issue) . "</li>";
        }
        $html .= "</ul>";
    }
    
    $html .= "<h6>Detailed Results:</h6>";
    $html .= "<div class='table-responsive'>";
    $html .= "<table class='table table-sm'>";
    $html .= "<thead><tr><th>Check</th><th>Status</th><th>Details</th></tr></thead>";
    $html .= "<tbody>";
    
    foreach ($checks as $checkName => $result) {
        $statusBadge = $result['status'] ? 
            "<span class='badge badge-success'>PASS</span>" : 
            "<span class='badge badge-danger'>FAIL</span>";
        
        $html .= "<tr>";
        $html .= "<td>" . ucfirst(str_replace('_', ' ', $checkName)) . "</td>";
        $html .= "<td>{$statusBadge}</td>";
        $html .= "<td><small>" . htmlspecialchars($result['details'] ?? $result['message']) . "</small></td>";
        $html .= "</tr>";
    }
    
    $html .= "</tbody></table></div>";
    
    return $html;
}

// Execute health check and return results
if (!defined('HEALTH_CHECK_INCLUDED')) {
    $healthResult = performHealthCheck();
    
    // Return JSON response
    header('Content-Type: application/json');
    echo json_encode($healthResult);
}
?>