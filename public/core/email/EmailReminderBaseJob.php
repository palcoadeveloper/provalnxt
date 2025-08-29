<?php
/**
 * EmailReminderBaseJob - Abstract base class for all email reminder jobs
 * 
 * This abstract class provides the common framework that all email reminder jobs inherit.
 * It handles job lifecycle, logging, error handling, and provides template methods
 * for specific job implementations.
 * 
 * @author ProVal System
 * @version 1.0
 */

require_once(__DIR__ . '/config.php');
require_once(__DIR__ . '/db.class.php');
require_once(__DIR__ . '/EmailReminderService.php');
require_once(__DIR__ . '/EmailReminderLogger.php');

abstract class EmailReminderBaseJob {
    
    protected $jobName;
    protected $logger;
    protected $emailService;
    protected $jobExecutionId;
    protected $startTime;
    protected $endTime;
    protected $executionTimeLimit;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->logger = new EmailReminderLogger();
        $this->emailService = new EmailReminderService($this->logger);
        $this->executionTimeLimit = EMAIL_REMINDER_JOB_MAX_EXECUTION_TIME;
        $this->startTime = microtime(true);
        
        // Set execution time limit
        set_time_limit($this->executionTimeLimit);
        
        // Get job name from class name
        $this->jobName = $this->getJobNameFromClass();
    }
    
    /**
     * Main execution method - Template Method Pattern
     * This method defines the job execution workflow
     */
    public final function execute() {
        try {
            // Start job execution logging
            $this->jobExecutionId = $this->logger->logJobStart($this->jobName);
            
            $this->logger->logInfo($this->jobName, "Starting job execution");
            
            // Check if jobs are globally enabled
            if (!EMAIL_REMINDER_JOBS_ENABLED) {
                $this->logger->logInfo($this->jobName, "Email reminder jobs are globally disabled");
                $this->logger->logJobEnd($this->jobExecutionId, 'skipped', 'Email reminder jobs globally disabled', 0, 0);
                return [
                    'success' => true,
                    'status' => 'skipped',
                    'message' => 'Email reminder jobs are globally disabled',
                    'emails_sent' => 0,
                    'emails_failed' => 0
                ];
            }
            
            // Check if this specific job is enabled
            if (!$this->isJobEnabled()) {
                $this->logger->logInfo($this->jobName, "Job is disabled");
                $this->logger->logJobEnd($this->jobExecutionId, 'skipped', 'Job is disabled', 0, 0);
                return [
                    'success' => true,
                    'status' => 'skipped',
                    'message' => 'Job is disabled',
                    'emails_sent' => 0,
                    'emails_failed' => 0
                ];
            }
            
            // Validate job prerequisites
            $validationResult = $this->validatePrerequisites();
            if (!$validationResult['valid']) {
                $this->logger->logError($this->jobName, "Prerequisites validation failed: " . $validationResult['message']);
                $this->logger->logJobEnd($this->jobExecutionId, 'failed', $validationResult['message'], 0, 0);
                return [
                    'success' => false,
                    'status' => 'failed',
                    'message' => $validationResult['message'],
                    'emails_sent' => 0,
                    'emails_failed' => 0
                ];
            }
            
            // Get list of active units
            $units = $this->getActiveUnits();
            
            if (empty($units)) {
                $this->logger->logInfo($this->jobName, "No active units found");
                $this->logger->logJobEnd($this->jobExecutionId, 'completed', 'No active units found', 0, 0);
                return [
                    'success' => true,
                    'status' => 'completed',
                    'message' => 'No active units found',
                    'emails_sent' => 0,
                    'emails_failed' => 0
                ];
            }
            
            $totalEmailsSent = 0;
            $totalEmailsFailed = 0;
            $processedUnits = 0;
            
            // Process each unit
            foreach ($units as $unit) {
                try {
                    $this->logger->logInfo($this->jobName, "Processing unit: " . $unit['unit_id'] . " (" . $unit['unit_name'] . ")");
                    
                    // Check execution time
                    if ($this->isExecutionTimeLimitReached()) {
                        $this->logger->logWarning($this->jobName, "Execution time limit reached, stopping processing");
                        break;
                    }
                    
                    // Process specific unit - implemented by child classes
                    $unitResult = $this->processUnit($unit);
                    
                    if ($unitResult) {
                        $totalEmailsSent += $unitResult['emails_sent'];
                        $totalEmailsFailed += $unitResult['emails_failed'];
                        $processedUnits++;
                        
                        $this->logger->logInfo($this->jobName, 
                            "Unit {$unit['unit_id']} processed. Emails sent: {$unitResult['emails_sent']}, failed: {$unitResult['emails_failed']}");
                    }
                    
                } catch (Exception $e) {
                    $this->logger->logError($this->jobName, "Error processing unit {$unit['unit_id']}: " . $e->getMessage());
                    $totalEmailsFailed++;
                }
            }
            
            // Perform cleanup
            $this->performCleanup();
            
            $this->endTime = microtime(true);
            $executionTime = round($this->endTime - $this->startTime, 2);
            
            $this->logger->logInfo($this->jobName, 
                "Job completed. Units processed: $processedUnits, Emails sent: $totalEmailsSent, Failed: $totalEmailsFailed, Execution time: {$executionTime}s");
            
            $this->logger->logJobEnd($this->jobExecutionId, 'completed', 
                "Job completed successfully. Units: $processedUnits, Emails sent: $totalEmailsSent", 
                $totalEmailsSent, $totalEmailsFailed);
            
            return [
                'success' => true,
                'status' => 'completed',
                'message' => "Job completed successfully",
                'units_processed' => $processedUnits,
                'emails_sent' => $totalEmailsSent,
                'emails_failed' => $totalEmailsFailed,
                'execution_time' => $executionTime
            ];
            
        } catch (Exception $e) {
            $this->endTime = microtime(true);
            $executionTime = round($this->endTime - $this->startTime, 2);
            
            $this->logger->logError($this->jobName, "Job execution failed: " . $e->getMessage());
            
            if ($this->jobExecutionId) {
                $this->logger->logJobEnd($this->jobExecutionId, 'failed', $e->getMessage(), 0, 1);
            }
            
            return [
                'success' => false,
                'status' => 'failed', 
                'message' => $e->getMessage(),
                'emails_sent' => 0,
                'emails_failed' => 1,
                'execution_time' => $executionTime
            ];
        }
    }
    
    /**
     * Abstract method to be implemented by child classes
     * Process a specific unit and return email sending results
     * 
     * @param array $unit Unit information (unit_id, unit_name, etc.)
     * @return array Result with emails_sent and emails_failed counts
     */
    abstract protected function processUnit($unit);
    
    /**
     * Abstract method to get the email subject for this job type
     * 
     * @param array $unit Unit information
     * @return string Email subject
     */
    abstract protected function getEmailSubject($unit);
    
    /**
     * Abstract method to get data for the email content
     * 
     * @param array $unit Unit information
     * @return array Data for email template
     */
    abstract protected function getEmailData($unit);
    
    /**
     * Get job name from class name
     */
    private function getJobNameFromClass() {
        $className = get_class($this);
        
        // Convert CamelCase to snake_case and remove EmailReminder prefix
        $jobName = preg_replace('/EmailReminder/', '', $className);
        $jobName = strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $jobName));
        
        return $jobName;
    }
    
    /**
     * Check if job is enabled (can be overridden by child classes)
     */
    protected function isJobEnabled() {
        return true; // Default: enabled
    }
    
    /**
     * Validate prerequisites (can be overridden by child classes)
     */
    protected function validatePrerequisites() {
        return ['valid' => true, 'message' => ''];
    }
    
    /**
     * Get list of active units
     */
    protected function getActiveUnits() {
        try {
            return DB::query("SELECT unit_id, unit_name FROM units WHERE unit_status = 'Active' ORDER BY unit_id");
        } catch (Exception $e) {
            $this->logger->logError($this->jobName, "Failed to get active units: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Check if execution time limit is reached
     */
    protected function isExecutionTimeLimitReached() {
        $currentTime = microtime(true);
        $executionTime = $currentTime - $this->startTime;
        return $executionTime >= ($this->executionTimeLimit - 10); // Leave 10 seconds buffer
    }
    
    /**
     * Generate email HTML content using template
     */
    protected function generateEmailContent($unit, $data) {
        $subject = $this->getEmailSubject($unit);
        
        // Build email content
        $htmlContent = $this->buildEmailHTML($unit, $data, $subject);
        $textContent = $this->buildEmailText($unit, $data, $subject);
        
        return [
            'subject' => $subject,
            'html' => $htmlContent,
            'text' => $textContent
        ];
    }
    
    /**
     * Build HTML email content (can be overridden by child classes)
     */
    protected function buildEmailHTML($unit, $data, $subject) {
        $html = "<html><head><title>{$subject}</title></head><body>";
        $html .= "<h2>{$subject}</h2>";
        $html .= "<p>Dear User,</p>";
        $html .= "<p>Please find below the information from the ProVal system:</p>";
        
        if (!empty($data)) {
            $html .= "<table border='1' cellpadding='5' cellspacing='0'>";
            $html .= "<tr>";
            $html .= "<th>Unit Name</th>";
            $html .= "<th>Equipment Code</th>";
            $html .= "<th>Category</th>";
            $html .= "<th>Validation Start Date</th>";
            $html .= "</tr>";
            
            foreach ($data as $row) {
                $html .= "<tr>";
                $html .= "<td>" . htmlspecialchars($row['unit_name']) . "</td>";
                $html .= "<td>" . htmlspecialchars($row['equipment_code']) . "</td>";
                $html .= "<td>" . htmlspecialchars($row['equipment_category']) . "</td>";
                $html .= "<td>" . htmlspecialchars($row['val_wf_planned_start_date']) . "</td>";
                $html .= "</tr>";
            }
            
            $html .= "</table>";
        } else {
            $html .= "<p>No items found for this unit.</p>";
        }
        
        $html .= "<br><p>Please note that this is a system generated email.</p>";
        $html .= "<p>Best regards,<br>ProVal System</p>";
        $html .= "</body></html>";
        
        return $html;
    }
    
    /**
     * Build plain text email content (can be overridden by child classes)
     */
    protected function buildEmailText($unit, $data, $subject) {
        $text = "{$subject}\n\n";
        $text .= "Dear User,\n\n";
        $text .= "Please find below the information from the ProVal system:\n\n";
        
        if (!empty($data)) {
            foreach ($data as $row) {
                $text .= "Unit: " . $row['unit_name'] . "\n";
                $text .= "Equipment: " . $row['equipment_code'] . "\n";
                $text .= "Category: " . $row['equipment_category'] . "\n";
                $text .= "Date: " . $row['val_wf_planned_start_date'] . "\n";
                $text .= "---\n";
            }
        } else {
            $text .= "No items found for this unit.\n";
        }
        
        $text .= "\nPlease note that this is a system generated email.\n\n";
        $text .= "Best regards,\nProVal System";
        
        return $text;
    }
    
    /**
     * Send email for a unit using the email service
     */
    protected function sendUnitEmail($unit, $emailContent) {
        try {
            return $this->emailService->sendReminder(
                $this->jobName,
                $unit['unit_id'],
                $emailContent['subject'],
                $emailContent['html'],
                $emailContent['text']
            );
        } catch (Exception $e) {
            $this->logger->logError($this->jobName, "Failed to send email for unit {$unit['unit_id']}: " . $e->getMessage());
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'emails_sent' => 0,
                'emails_failed' => 1
            ];
        }
    }
    
    /**
     * Perform cleanup tasks (can be overridden by child classes)
     */
    protected function performCleanup() {
        // Default: no cleanup needed
        // Child classes can override this for specific cleanup tasks
    }
    
    /**
     * Get job statistics
     */
    public function getJobStats($days = 30) {
        return $this->logger->getJobStats($this->jobName, $days);
    }
    
    /**
     * Test job execution without sending emails
     */
    public function testRun($unitId = null) {
        $this->logger->logInfo($this->jobName, "Starting test run");
        
        // Temporarily disable email sending for test
        $originalEnabled = EMAIL_REMINDER_JOBS_ENABLED;
        
        // Get units to test
        if ($unitId) {
            $units = DB::query("SELECT unit_id, unit_name FROM units WHERE unit_id = %i AND unit_status = 'Active'", $unitId);
        } else {
            $units = $this->getActiveUnits();
            // Limit to first unit for testing
            $units = array_slice($units, 0, 1);
        }
        
        $results = [];
        
        foreach ($units as $unit) {
            try {
                $data = $this->getEmailData($unit);
                $emailContent = $this->generateEmailContent($unit, $data);
                
                $results[] = [
                    'unit_id' => $unit['unit_id'],
                    'unit_name' => $unit['unit_name'],
                    'data_count' => count($data),
                    'email_subject' => $emailContent['subject'],
                    'email_preview' => substr(strip_tags($emailContent['html']), 0, 200) . '...'
                ];
                
            } catch (Exception $e) {
                $results[] = [
                    'unit_id' => $unit['unit_id'],
                    'unit_name' => $unit['unit_name'],
                    'error' => $e->getMessage()
                ];
            }
        }
        
        $this->logger->logInfo($this->jobName, "Test run completed");
        
        return $results;
    }
}
?>