<?php
/**
 * EmailReminderJobRunner - Main orchestrator for all email reminder jobs
 * 
 * This is the single entry point that Windows Task Scheduler executes.
 * It manages the execution of all email reminder jobs, handles dependencies,
 * monitors execution time, and provides comprehensive reporting.
 * 
 * Usage: php EmailReminderJobRunner.php [--test] [--job=JobName] [--unit=UnitId]
 * 
 * @author ProVal System
 * @version 1.0
 */

// Include core files
require_once(__DIR__ . '/../core/config/config.php');
require_once(__DIR__ . '/../core/config/db.class.php');
require_once(__DIR__ . '/../core/EmailReminderLogger.php');

// Include job classes
require_once(__DIR__ . '/jobs/EmailReminderValidationNotStarted10Days.php');
require_once(__DIR__ . '/jobs/EmailReminderValidationNotStarted30Days.php');
require_once(__DIR__ . '/jobs/EmailReminderValidationInProgress30Days.php');
require_once(__DIR__ . '/jobs/EmailReminderValidationInProgress35Days.php');
require_once(__DIR__ . '/jobs/EmailReminderValidationInProgress38Days.php');

class EmailReminderJobRunner {
    
    private $logger;
    private $startTime;
    private $jobDefinitions;
    private $isTestMode;
    private $specificJob;
    private $specificUnit;
    
    public function __construct() {
        $this->logger = new EmailReminderLogger();
        $this->startTime = microtime(true);
        $this->isTestMode = false;
        $this->specificJob = null;
        $this->specificUnit = null;
        
        // Parse command line arguments
        $this->parseArguments();
        
        // Define job configuration
        $this->defineJobs();
        
        // Set timezone
        date_default_timezone_set("Asia/Kolkata");
        
        $this->logger->logInfo('JobRunner', 'EmailReminder Job Runner initialized');
    }
    
    /**
     * Parse command line arguments
     */
    private function parseArguments() {
        global $argv;
        
        if (isset($argv)) {
            foreach ($argv as $arg) {
                if ($arg === '--test') {
                    $this->isTestMode = true;
                } elseif (strpos($arg, '--job=') === 0) {
                    $this->specificJob = substr($arg, 6);
                } elseif (strpos($arg, '--unit=') === 0) {
                    $this->specificUnit = intval(substr($arg, 7));
                }
            }
        }
    }
    
    /**
     * Define job configurations
     */
    private function defineJobs() {
        $this->jobDefinitions = [
            'validation_not_started_10_days' => [
                'class' => 'EmailReminderValidationNotStarted10Days',
                'enabled' => true,
                'priority' => 1,
                'description' => 'Reminder for validations not started - 10 days prior',
                'schedule' => 'daily'
            ],
            'validation_not_started_30_days' => [
                'class' => 'EmailReminderValidationNotStarted30Days',
                'enabled' => true,
                'priority' => 2,
                'description' => 'Reminder for validations not started - 30 days prior',
                'schedule' => 'daily'
            ],
            'validation_in_progress_30_days' => [
                'class' => 'EmailReminderValidationInProgress30Days',
                'enabled' => true,
                'priority' => 3,
                'description' => 'Alert for validations in progress > 30 days',
                'schedule' => 'daily'
            ],
            'validation_in_progress_35_days' => [
                'class' => 'EmailReminderValidationInProgress35Days',
                'enabled' => true,
                'priority' => 4,
                'description' => 'Escalation for validations in progress > 35 days',
                'schedule' => 'daily'
            ],
            'validation_in_progress_38_days' => [
                'class' => 'EmailReminderValidationInProgress38Days',
                'enabled' => true,
                'priority' => 5,
                'description' => 'Critical alert for validations in progress > 38 days',
                'schedule' => 'daily'
            ]
        ];
    }
    
    /**
     * Main execution method
     */
    public function run() {
        try {
            $this->logger->logInfo('JobRunner', 'Starting EmailReminder job execution' . 
                ($this->isTestMode ? ' (TEST MODE)' : '') .
                ($this->specificJob ? " (Job: {$this->specificJob})" : '') .
                ($this->specificUnit ? " (Unit: {$this->specificUnit})" : '')
            );
            
            // Check if email reminders are globally enabled
            if (!EMAIL_REMINDER_JOBS_ENABLED && !$this->isTestMode) {
                $this->logger->logInfo('JobRunner', 'Email reminder jobs are globally disabled');
                return [
                    'success' => true,
                    'message' => 'Email reminder jobs are globally disabled',
                    'jobs_executed' => 0,
                    'total_emails_sent' => 0,
                    'total_emails_failed' => 0
                ];
            }
            
            // Perform pre-execution checks
            $preCheckResult = $this->performPreExecutionChecks();
            if (!$preCheckResult['success']) {
                return $preCheckResult;
            }
            
            // Get jobs to execute
            $jobsToExecute = $this->getJobsToExecute();
            
            if (empty($jobsToExecute)) {
                $this->logger->logInfo('JobRunner', 'No jobs to execute');
                return [
                    'success' => true,
                    'message' => 'No jobs to execute',
                    'jobs_executed' => 0,
                    'total_emails_sent' => 0,
                    'total_emails_failed' => 0
                ];
            }
            
            // Execute jobs
            $results = $this->executeJobs($jobsToExecute);
            
            // Perform cleanup
            $this->performCleanup();
            
            // Generate final report
            $finalReport = $this->generateFinalReport($results);
            
            $this->logger->logInfo('JobRunner', 'EmailReminder job execution completed: ' . json_encode($finalReport));
            
            return $finalReport;
            
        } catch (Exception $e) {
            $this->logger->logError('JobRunner', 'Job runner execution failed: ' . $e->getMessage());
            
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'jobs_executed' => 0,
                'total_emails_sent' => 0,
                'total_emails_failed' => 0
            ];
        }
    }
    
    /**
     * Perform pre-execution checks
     */
    private function performPreExecutionChecks() {
        try {
            // Check database connectivity
            $dbCheck = DB::queryFirstField("SELECT 1");
            if ($dbCheck !== 1) {
                throw new Exception("Database connectivity check failed");
            }
            
            // Check required tables exist
            $requiredTables = [
                'tbl_email_configuration',
                'units',
                'tbl_val_schedules',
                'equipments'
            ];
            
            foreach ($requiredTables as $table) {
                $tableExists = DB::queryFirstField("SELECT COUNT(*) FROM information_schema.tables WHERE table_name = %s AND table_schema = DATABASE()", $table);
                if (!$tableExists) {
                    throw new Exception("Required table '$table' does not exist");
                }
            }
            
            // Check PHPMailer is available
            if (!class_exists('PHPMailer\PHPMailer\PHPMailer')) {
                // Try to include it
                $phpmailerPath = __DIR__ . '/../core/phpmailer/src/PHPMailer.php';
                if (file_exists($phpmailerPath)) {
                    require_once($phpmailerPath);
                    require_once(__DIR__ . '/../core/phpmailer/src/Exception.php');
                    require_once(__DIR__ . '/../core/phpmailer/src/SMTP.php');
                } else {
                    throw new Exception("PHPMailer not found");
                }
            }
            
            $this->logger->logInfo('JobRunner', 'Pre-execution checks passed');
            
            return ['success' => true];
            
        } catch (Exception $e) {
            $this->logger->logError('JobRunner', 'Pre-execution check failed: ' . $e->getMessage());
            
            return [
                'success' => false,
                'message' => 'Pre-execution check failed: ' . $e->getMessage(),
                'jobs_executed' => 0,
                'total_emails_sent' => 0,
                'total_emails_failed' => 0
            ];
        }
    }
    
    /**
     * Get jobs to execute based on configuration and filters
     */
    private function getJobsToExecute() {
        $jobsToExecute = [];
        
        foreach ($this->jobDefinitions as $jobKey => $jobConfig) {
            // Check if job is enabled
            if (!$jobConfig['enabled']) {
                $this->logger->logInfo('JobRunner', "Job '$jobKey' is disabled, skipping");
                continue;
            }
            
            // Check if specific job filter is applied
            if ($this->specificJob && $jobKey !== $this->specificJob) {
                continue;
            }
            
            // Check if job class exists
            if (!class_exists($jobConfig['class'])) {
                $this->logger->logError('JobRunner', "Job class '{$jobConfig['class']}' not found");
                continue;
            }
            
            $jobsToExecute[] = [
                'key' => $jobKey,
                'config' => $jobConfig
            ];
        }
        
        // Sort by priority
        usort($jobsToExecute, function($a, $b) {
            return $a['config']['priority'] - $b['config']['priority'];
        });
        
        return $jobsToExecute;
    }
    
    /**
     * Execute jobs
     */
    private function executeJobs($jobsToExecute) {
        $results = [];
        $totalEmailsSent = 0;
        $totalEmailsFailed = 0;
        
        foreach ($jobsToExecute as $jobData) {
            $jobKey = $jobData['key'];
            $jobConfig = $jobData['config'];
            
            try {
                $this->logger->logInfo('JobRunner', "Executing job: $jobKey ({$jobConfig['class']})");
                
                // Instantiate job class
                $jobInstance = new $jobConfig['class']();
                
                // Execute job
                if ($this->isTestMode) {
                    $jobResult = $jobInstance->testRun($this->specificUnit);
                    $results[$jobKey] = [
                        'success' => true,
                        'status' => 'test_completed',
                        'message' => 'Test run completed',
                        'test_results' => $jobResult
                    ];
                } else {
                    $jobResult = $jobInstance->execute();
                    $results[$jobKey] = $jobResult;
                    
                    if (isset($jobResult['emails_sent'])) {
                        $totalEmailsSent += $jobResult['emails_sent'];
                    }
                    if (isset($jobResult['emails_failed'])) {
                        $totalEmailsFailed += $jobResult['emails_failed'];
                    }
                }
                
                $this->logger->logInfo('JobRunner', "Job $jobKey completed: " . $jobResult['status']);
                
            } catch (Exception $e) {
                $this->logger->logError('JobRunner', "Job $jobKey failed: " . $e->getMessage());
                
                $results[$jobKey] = [
                    'success' => false,
                    'status' => 'failed',
                    'message' => $e->getMessage(),
                    'emails_sent' => 0,
                    'emails_failed' => 1
                ];
                
                $totalEmailsFailed++;
            }
        }
        
        return [
            'results' => $results,
            'total_emails_sent' => $totalEmailsSent,
            'total_emails_failed' => $totalEmailsFailed
        ];
    }
    
    /**
     * Perform cleanup tasks
     */
    private function performCleanup() {
        try {
            $this->logger->logInfo('JobRunner', 'Performing cleanup tasks');
            
            // Clean old logs based on retention policy
            $cleanupResult = $this->logger->cleanOldLogs();
            
            if ($cleanupResult) {
                $this->logger->logInfo('JobRunner', 'Log cleanup completed: ' . json_encode($cleanupResult));
            }
            
        } catch (Exception $e) {
            $this->logger->logError('JobRunner', 'Cleanup failed: ' . $e->getMessage());
        }
    }
    
    /**
     * Generate final execution report
     */
    private function generateFinalReport($executionResults) {
        $endTime = microtime(true);
        $totalExecutionTime = round($endTime - $this->startTime, 2);
        
        $jobsExecuted = count($executionResults['results']);
        $successfulJobs = 0;
        $failedJobs = 0;
        $skippedJobs = 0;
        
        foreach ($executionResults['results'] as $jobResult) {
            if ($jobResult['success']) {
                if ($jobResult['status'] === 'skipped') {
                    $skippedJobs++;
                } else {
                    $successfulJobs++;
                }
            } else {
                $failedJobs++;
            }
        }
        
        return [
            'success' => $failedJobs === 0,
            'execution_time' => $totalExecutionTime,
            'jobs_executed' => $jobsExecuted,
            'successful_jobs' => $successfulJobs,
            'failed_jobs' => $failedJobs,
            'skipped_jobs' => $skippedJobs,
            'total_emails_sent' => $executionResults['total_emails_sent'],
            'total_emails_failed' => $executionResults['total_emails_failed'],
            'test_mode' => $this->isTestMode,
            'job_results' => $executionResults['results']
        ];
    }
    
    /**
     * Display help information
     */
    public static function displayHelp() {
        echo "EmailReminder Job Runner - Usage:\n\n";
        echo "php EmailReminderJobRunner.php [options]\n\n";
        echo "Options:\n";
        echo "  --test                    Run in test mode (no emails sent)\n";
        echo "  --job=JobName            Execute specific job only\n";
        echo "  --unit=UnitId            Test with specific unit only (requires --test)\n";
        echo "  --help                   Display this help message\n\n";
        echo "Available Jobs:\n";
        echo "  validation_not_started_10_days\n";
        echo "  validation_not_started_30_days\n";
        echo "  validation_in_progress_30_days\n";
        echo "  validation_in_progress_35_days\n";
        echo "  validation_in_progress_38_days\n\n";
        echo "Examples:\n";
        echo "  php EmailReminderJobRunner.php\n";
        echo "  php EmailReminderJobRunner.php --test\n";
        echo "  php EmailReminderJobRunner.php --job=validation_not_started_10_days\n";
        echo "  php EmailReminderJobRunner.php --test --unit=8\n\n";
    }
    
    /**
     * Get system status
     */
    public function getSystemStatus() {
        $healthStatus = $this->logger->getJobHealthStatus(24);
        $recentJobs = $this->logger->getRecentJobExecutions(10);
        
        return [
            'health' => $healthStatus,
            'recent_executions' => $recentJobs,
            'job_definitions' => $this->jobDefinitions,
            'configuration' => [
                'jobs_enabled' => EMAIL_REMINDER_JOBS_ENABLED,
                'smtp_host' => EMAIL_REMINDER_SMTP_HOST,
                'rate_limit' => EMAIL_REMINDER_RATE_LIMIT_PER_HOUR,
                'max_execution_time' => EMAIL_REMINDER_JOB_MAX_EXECUTION_TIME
            ]
        ];
    }
}

// Command line execution
if (php_sapi_name() === 'cli') {
    
    // Check for help argument
    if (isset($argv) && in_array('--help', $argv)) {
        EmailReminderJobRunner::displayHelp();
        exit(0);
    }
    
    try {
        $runner = new EmailReminderJobRunner();
        $result = $runner->run();
        
        // Output result for command line
        echo "EmailReminder Job Execution Result:\n";
        echo "Success: " . ($result['success'] ? 'Yes' : 'No') . "\n";
        echo "Jobs Executed: " . $result['jobs_executed'] . "\n";
        echo "Emails Sent: " . $result['total_emails_sent'] . "\n";
        echo "Emails Failed: " . $result['total_emails_failed'] . "\n";
        echo "Execution Time: " . $result['execution_time'] . " seconds\n";
        
        if (isset($result['test_mode']) && $result['test_mode']) {
            echo "\n*** TEST MODE - No emails were sent ***\n";
        }
        
        // Exit with appropriate code
        exit($result['success'] ? 0 : 1);
        
    } catch (Exception $e) {
        echo "EmailReminder Job Runner Error: " . $e->getMessage() . "\n";
        exit(1);
    }
}
?>