<?php
/**
 * EmailReminderLogger - Comprehensive logging system for EmailReminder framework
 * 
 * This class handles all logging operations for the email reminder system,
 * including job execution logs, error tracking, and performance monitoring.
 * 
 * @author ProVal System
 * @version 1.0
 */

require_once(__DIR__ . '/config.php');
require_once(__DIR__ . '/db.class.php');

class EmailReminderLogger {
    
    private $logLevel;
    private $enableFileLogging;
    private $logFilePath;
    
    const LOG_LEVEL_ERROR = 1;
    const LOG_LEVEL_WARNING = 2;
    const LOG_LEVEL_INFO = 3;
    const LOG_LEVEL_DEBUG = 4;
    
    public function __construct($logLevel = self::LOG_LEVEL_INFO, $enableFileLogging = true) {
        $this->logLevel = $logLevel;
        $this->enableFileLogging = $enableFileLogging;
        
        if ($this->enableFileLogging) {
            $this->initializeFileLogging();
        }
        
        date_default_timezone_set("Asia/Kolkata");
    }
    
    /**
     * Initialize file logging
     */
    private function initializeFileLogging() {
        $logDir = __DIR__ . '/../logs/email_reminder';
        
        if (!is_dir($logDir)) {
            if (!mkdir($logDir, 0755, true)) {
                error_log("EmailReminderLogger: Failed to create log directory: $logDir");
                $this->enableFileLogging = false;
                return;
            }
        }
        
        $this->logFilePath = $logDir . '/email_reminder_' . date('Y-m-d') . '.log';
    }
    
    /**
     * Log job execution start
     * 
     * @param string $jobName Name of the job
     * @param array $additionalData Additional data to log
     * @return int Job execution ID
     */
    public function logJobStart($jobName, $additionalData = []) {
        try {
            $jobExecutionId = DB::insert('tbl_email_reminder_job_logs', [
                'job_name' => $jobName,
                'execution_start_time' => date('Y-m-d H:i:s'),
                'status' => 'running',
                'emails_sent' => 0,
                'emails_failed' => 0,
                'execution_time_seconds' => 0,
                'additional_data' => json_encode($additionalData)
            ]);
            
            $this->logInfo($jobName, "Job execution started with ID: $jobExecutionId");
            
            return $jobExecutionId;
            
        } catch (Exception $e) {
            $this->logError($jobName, "Failed to log job start: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Log job execution end
     * 
     * @param int $jobExecutionId Job execution ID
     * @param string $status Final status (completed, failed, skipped)
     * @param string $message Final message
     * @param int $emailsSent Number of emails sent
     * @param int $emailsFailed Number of emails failed
     */
    public function logJobEnd($jobExecutionId, $status, $message = '', $emailsSent = 0, $emailsFailed = 0) {
        if (!$jobExecutionId) {
            return;
        }
        
        try {
            // Get start time to calculate execution time
            $jobLog = DB::queryFirstRow(
                "SELECT execution_start_time FROM tbl_email_reminder_job_logs WHERE job_execution_id = %i", 
                $jobExecutionId
            );
            
            $executionTime = 0;
            if ($jobLog) {
                $startTime = strtotime($jobLog['execution_start_time']);
                $executionTime = time() - $startTime;
            }
            
            DB::update('tbl_email_reminder_job_logs', [
                'execution_end_time' => date('Y-m-d H:i:s'),
                'status' => $status,
                'final_message' => $message,
                'emails_sent' => $emailsSent,
                'emails_failed' => $emailsFailed,
                'execution_time_seconds' => $executionTime
            ], 'job_execution_id = %i', $jobExecutionId);
            
            $this->logInfo('JobLogger', "Job $jobExecutionId ended with status: $status, Emails sent: $emailsSent, Failed: $emailsFailed, Time: {$executionTime}s");
            
        } catch (Exception $e) {
            $this->logError('JobLogger', "Failed to log job end: " . $e->getMessage());
        }
    }
    
    /**
     * Log error message
     */
    public function logError($source, $message, $additionalData = []) {
        if ($this->logLevel >= self::LOG_LEVEL_ERROR) {
            $this->writeLog('ERROR', $source, $message, $additionalData);
        }
    }
    
    /**
     * Log warning message
     */
    public function logWarning($source, $message, $additionalData = []) {
        if ($this->logLevel >= self::LOG_LEVEL_WARNING) {
            $this->writeLog('WARNING', $source, $message, $additionalData);
        }
    }
    
    /**
     * Log info message
     */
    public function logInfo($source, $message, $additionalData = []) {
        if ($this->logLevel >= self::LOG_LEVEL_INFO) {
            $this->writeLog('INFO', $source, $message, $additionalData);
        }
    }
    
    /**
     * Log debug message
     */
    public function logDebug($source, $message, $additionalData = []) {
        if ($this->logLevel >= self::LOG_LEVEL_DEBUG) {
            $this->writeLog('DEBUG', $source, $message, $additionalData);
        }
    }
    
    /**
     * Write log entry
     */
    private function writeLog($level, $source, $message, $additionalData = []) {
        $timestamp = date('Y-m-d H:i:s');
        $logEntry = "[$timestamp] [$level] [$source] $message";
        
        if (!empty($additionalData)) {
            $logEntry .= " | Data: " . json_encode($additionalData);
        }
        
        // Write to file if enabled
        if ($this->enableFileLogging && $this->logFilePath) {
            file_put_contents($this->logFilePath, $logEntry . PHP_EOL, FILE_APPEND | LOCK_EX);
        }
        
        // Write to error log for ERROR level
        if ($level === 'ERROR') {
            error_log("EmailReminder $level: [$source] $message");
        }
        
        // Write to database for important logs
        if (in_array($level, ['ERROR', 'WARNING'])) {
            $this->writeLogToDatabase($level, $source, $message, $additionalData);
        }
    }
    
    /**
     * Write log to database
     */
    private function writeLogToDatabase($level, $source, $message, $additionalData = []) {
        try {
            DB::insert('tbl_email_reminder_system_logs', [
                'log_level' => $level,
                'log_source' => $source,
                'log_message' => $message,
                'log_data' => json_encode($additionalData),
                'log_datetime' => date('Y-m-d H:i:s')
            ]);
        } catch (Exception $e) {
            // Don't create infinite loop if database logging fails
            error_log("EmailReminderLogger: Failed to write to database: " . $e->getMessage());
        }
    }
    
    /**
     * Get job statistics
     */
    public function getJobStats($jobName = null, $days = 30) {
        try {
            $whereClause = "execution_start_time >= DATE_SUB(NOW(), INTERVAL %i DAY)";
            $params = [$days];
            
            if ($jobName) {
                $whereClause .= " AND job_name = %s";
                $params[] = $jobName;
            }
            
            return DB::queryFirstRow(
                "SELECT 
                    COUNT(*) as total_executions,
                    COUNT(CASE WHEN status = 'completed' THEN 1 END) as successful_executions,
                    COUNT(CASE WHEN status = 'failed' THEN 1 END) as failed_executions,
                    COUNT(CASE WHEN status = 'skipped' THEN 1 END) as skipped_executions,
                    SUM(emails_sent) as total_emails_sent,
                    SUM(emails_failed) as total_emails_failed,
                    AVG(execution_time_seconds) as avg_execution_time,
                    MAX(execution_time_seconds) as max_execution_time,
                    MIN(execution_time_seconds) as min_execution_time
                 FROM tbl_email_reminder_job_logs 
                 WHERE $whereClause",
                ...$params
            );
        } catch (Exception $e) {
            $this->logError('JobLogger', "Failed to get job stats: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Get recent job executions
     */
    public function getRecentJobExecutions($limit = 50, $jobName = null) {
        try {
            $whereClause = "1=1";
            $params = [];
            
            if ($jobName) {
                $whereClause .= " AND job_name = %s";
                $params[] = $jobName;
            }
            
            $params[] = $limit;
            
            return DB::query(
                "SELECT 
                    job_execution_id,
                    job_name,
                    execution_start_time,
                    execution_end_time,
                    status,
                    final_message,
                    emails_sent,
                    emails_failed,
                    execution_time_seconds
                 FROM tbl_email_reminder_job_logs 
                 WHERE $whereClause 
                 ORDER BY execution_start_time DESC 
                 LIMIT %i",
                ...$params
            );
        } catch (Exception $e) {
            $this->logError('JobLogger', "Failed to get recent executions: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get system logs
     */
    public function getSystemLogs($level = null, $source = null, $limit = 100) {
        try {
            $whereClause = "1=1";
            $params = [];
            
            if ($level) {
                $whereClause .= " AND log_level = %s";
                $params[] = $level;
            }
            
            if ($source) {
                $whereClause .= " AND log_source = %s";
                $params[] = $source;
            }
            
            $params[] = $limit;
            
            return DB::query(
                "SELECT 
                    log_id,
                    log_level,
                    log_source,
                    log_message,
                    log_data,
                    log_datetime
                 FROM tbl_email_reminder_system_logs 
                 WHERE $whereClause 
                 ORDER BY log_datetime DESC 
                 LIMIT %i",
                ...$params
            );
        } catch (Exception $e) {
            error_log("EmailReminderLogger: Failed to get system logs: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Clean old logs based on retention policy
     */
    public function cleanOldLogs() {
        try {
            $jobLogRetentionDays = EMAIL_REMINDER_JOB_LOG_RETENTION_DAYS;
            $emailLogRetentionDays = EMAIL_REMINDER_EMAIL_LOG_RETENTION_DAYS;
            
            // Clean old job logs
            $deletedJobLogs = DB::delete('tbl_email_reminder_job_logs', 
                'execution_start_time < DATE_SUB(NOW(), INTERVAL %i DAY)', 
                $jobLogRetentionDays);
            
            // Clean old email logs
            $deletedEmailLogs = DB::delete('tbl_email_reminder_logs', 
                'sent_datetime < DATE_SUB(NOW(), INTERVAL %i DAY)', 
                $emailLogRetentionDays);
            
            // Clean old system logs (keep for same period as job logs)
            $deletedSystemLogs = DB::delete('tbl_email_reminder_system_logs', 
                'log_datetime < DATE_SUB(NOW(), INTERVAL %i DAY)', 
                $jobLogRetentionDays);
            
            $this->logInfo('LogCleaner', 
                "Cleaned old logs: Job logs: $deletedJobLogs, Email logs: $deletedEmailLogs, System logs: $deletedSystemLogs");
            
            return [
                'job_logs_deleted' => $deletedJobLogs,
                'email_logs_deleted' => $deletedEmailLogs,
                'system_logs_deleted' => $deletedSystemLogs
            ];
            
        } catch (Exception $e) {
            $this->logError('LogCleaner', "Failed to clean old logs: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Get job execution health status
     */
    public function getJobHealthStatus($hours = 24) {
        try {
            $healthData = DB::queryFirstRow(
                "SELECT 
                    COUNT(*) as total_jobs,
                    COUNT(CASE WHEN status = 'failed' THEN 1 END) as failed_jobs,
                    COUNT(CASE WHEN status = 'completed' THEN 1 END) as successful_jobs,
                    COUNT(CASE WHEN execution_time_seconds > %i THEN 1 END) as slow_jobs
                 FROM tbl_email_reminder_job_logs 
                 WHERE execution_start_time >= DATE_SUB(NOW(), INTERVAL %i HOUR)",
                EMAIL_REMINDER_JOB_MAX_EXECUTION_TIME,
                $hours
            );
            
            $healthStatus = 'healthy';
            $issues = [];
            
            if ($healthData['failed_jobs'] > 0) {
                $healthStatus = 'warning';
                $issues[] = "Failed jobs: {$healthData['failed_jobs']}";
            }
            
            if ($healthData['slow_jobs'] > 0) {
                $healthStatus = 'warning';
                $issues[] = "Slow jobs: {$healthData['slow_jobs']}";
            }
            
            if ($healthData['failed_jobs'] > ($healthData['total_jobs'] * 0.5)) {
                $healthStatus = 'critical';
            }
            
            return [
                'status' => $healthStatus,
                'issues' => $issues,
                'data' => $healthData
            ];
            
        } catch (Exception $e) {
            $this->logError('HealthChecker', "Failed to get health status: " . $e->getMessage());
            return [
                'status' => 'unknown',
                'issues' => ['Failed to check health status'],
                'data' => []
            ];
        }
    }
    
    /**
     * Export logs to file
     */
    public function exportLogs($startDate, $endDate, $format = 'csv') {
        try {
            $logs = DB::query(
                "SELECT 
                    j.job_name,
                    j.execution_start_time,
                    j.execution_end_time,
                    j.status,
                    j.emails_sent,
                    j.emails_failed,
                    j.execution_time_seconds,
                    j.final_message
                 FROM tbl_email_reminder_job_logs j
                 WHERE j.execution_start_time >= %s AND j.execution_start_time <= %s
                 ORDER BY j.execution_start_time DESC",
                $startDate,
                $endDate
            );
            
            $exportDir = __DIR__ . '/../exports';
            if (!is_dir($exportDir)) {
                mkdir($exportDir, 0755, true);
            }
            
            $filename = $exportDir . '/email_reminder_logs_' . date('Y-m-d_H-i-s') . '.' . $format;
            
            if ($format === 'csv') {
                $this->exportToCSV($logs, $filename);
            } else {
                $this->exportToJSON($logs, $filename);
            }
            
            $this->logInfo('LogExporter', "Exported " . count($logs) . " log entries to: $filename");
            
            return $filename;
            
        } catch (Exception $e) {
            $this->logError('LogExporter', "Failed to export logs: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Export to CSV format
     */
    private function exportToCSV($logs, $filename) {
        $fp = fopen($filename, 'w');
        
        // Write headers
        fputcsv($fp, [
            'Job Name',
            'Start Time',
            'End Time',
            'Status',
            'Emails Sent',
            'Emails Failed',
            'Execution Time (seconds)',
            'Message'
        ]);
        
        // Write data
        foreach ($logs as $log) {
            fputcsv($fp, [
                $log['job_name'],
                $log['execution_start_time'],
                $log['execution_end_time'],
                $log['status'],
                $log['emails_sent'],
                $log['emails_failed'],
                $log['execution_time_seconds'],
                $log['final_message']
            ]);
        }
        
        fclose($fp);
    }
    
    /**
     * Export to JSON format
     */
    private function exportToJSON($logs, $filename) {
        file_put_contents($filename, json_encode($logs, JSON_PRETTY_PRINT));
    }
}
?>