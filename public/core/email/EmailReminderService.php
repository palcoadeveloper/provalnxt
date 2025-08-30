<?php
/**
 * EmailReminderService - Centralized email sending service for the EmailReminder framework
 * 
 * This service handles all email sending operations for the validation reminder system,
 * including SMTP configuration, recipient management, retry logic, and comprehensive logging.
 * 
 * @author ProVal System
 * @version 1.0
 */

require_once(__DIR__ . '/../config/config.php');
require_once(__DIR__ . '/../config/db.class.php');

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

class EmailReminderService {
    
    private $mail;
    private $logger;
    private $currentEmailLogId;
    private $rateLimitCheck = [];
    
    public function __construct($logger = null) {
        $this->logger = $logger;
        $this->initializeSMTP();
    }
    
    /**
     * Initialize SMTP configuration using constants from config.php
     */
    private function initializeSMTP() {
        $this->mail = new PHPMailer(true);
        
        try {
            // Server settings
            $this->mail->isSMTP();
            $this->mail->Host = EMAIL_REMINDER_SMTP_HOST;
            $this->mail->SMTPAuth = EMAIL_REMINDER_SMTP_AUTH_ENABLED;
            $this->mail->Username = EMAIL_REMINDER_SMTP_USERNAME;
            $this->mail->Password = EMAIL_REMINDER_SMTP_PASSWORD;
            $this->mail->SMTPSecure = EMAIL_REMINDER_SMTP_SECURE;
            $this->mail->Port = EMAIL_REMINDER_SMTP_PORT;
            $this->mail->SMTPDebug = EMAIL_REMINDER_SMTP_DEBUG_LEVEL;
            
            // Set default sender
            $this->mail->setFrom(EMAIL_REMINDER_SMTP_FROM_EMAIL, EMAIL_REMINDER_SMTP_FROM_NAME);
            
            // Enable HTML emails by default
            $this->mail->isHTML(true);
            
        } catch (Exception $e) {
            $this->logError("SMTP initialization failed: " . $e->getMessage());
            throw new Exception("EmailReminder SMTP configuration failed: " . $e->getMessage());
        }
    }
    
    /**
     * Send email reminder to configured recipients for a specific job and unit
     * 
     * @param string $jobName Name of the job (e.g., 'rem_val_not_started_10days_prior')
     * @param int $unitId Unit ID for recipient lookup
     * @param string $subject Email subject
     * @param string $htmlBody HTML email body
     * @param string $textBody Plain text email body (optional)
     * @return array Result with success status and details
     */
    public function sendReminder($jobName, $unitId, $subject, $htmlBody, $textBody = '') {
        
        // Check if email reminders are globally enabled
        if (!EMAIL_REMINDER_JOBS_ENABLED) {
            return [
                'success' => false,
                'message' => 'Email reminders are globally disabled',
                'emails_sent' => 0,
                'emails_failed' => 0
            ];
        }
        
        // Check rate limiting
        if (!$this->checkRateLimit()) {
            return [
                'success' => false,
                'message' => 'Rate limit exceeded',
                'emails_sent' => 0,
                'emails_failed' => 0
            ];
        }
        
        try {
            // Get email configuration from database
            $emailConfig = $this->getEmailConfiguration($unitId, $jobName);
            
            if (empty($emailConfig)) {
                $this->logError("No email configuration found for job '$jobName' and unit '$unitId'");
                return [
                    'success' => false,
                    'message' => "No email configuration found for job '$jobName' and unit '$unitId'",
                    'emails_sent' => 0,
                    'emails_failed' => 0
                ];
            }
            
            // Parse recipients
            $recipients = $this->parseRecipients($emailConfig);
            
            if (empty($recipients['to']) && empty($recipients['cc']) && empty($recipients['bcc'])) {
                return [
                    'success' => false,
                    'message' => 'No valid recipients found',
                    'emails_sent' => 0,
                    'emails_failed' => 0
                ];
            }
            
            // Log email start
            $this->currentEmailLogId = $this->logEmailStart($jobName, $unitId, $subject, $htmlBody, $textBody, $recipients);
            
            // Send email with retry logic
            $result = $this->sendEmailWithRetry($subject, $htmlBody, $textBody, $recipients);
            
            // Log final result
            $this->logEmailResult($this->currentEmailLogId, $result);
            
            return $result;
            
        } catch (Exception $e) {
            $this->logError("Failed to send reminder email: " . $e->getMessage());
            
            if ($this->currentEmailLogId) {
                $this->logEmailResult($this->currentEmailLogId, [
                    'success' => false,
                    'message' => $e->getMessage(),
                    'emails_sent' => 0,
                    'emails_failed' => 1
                ]);
            }
            
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'emails_sent' => 0,
                'emails_failed' => 1
            ];
        }
    }
    
    /**
     * Get email configuration from database for specific unit and job
     */
    private function getEmailConfiguration($unitId, $jobName) {
        try {
            return DB::queryFirstRow(
                "SELECT email_ids_to, email_ids_cc, email_ids_bcc 
                 FROM tbl_email_configuration 
                 WHERE unit_id = %i AND event_name = %s", 
                $unitId, 
                $jobName
            );
        } catch (Exception $e) {
            $this->logError("Database error getting email configuration: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Parse recipients from semicolon-separated strings
     */
    private function parseRecipients($emailConfig) {
        $recipients = [
            'to' => $this->parseEmailAddresses($emailConfig['email_ids_to']),
            'cc' => $this->parseEmailAddresses($emailConfig['email_ids_cc']),
            'bcc' => $this->parseEmailAddresses($emailConfig['email_ids_bcc'])
        ];
        
        return $recipients;
    }
    
    /**
     * Parse and validate semicolon-separated email addresses
     */
    private function parseEmailAddresses($emailString) {
        if (empty($emailString)) {
            return [];
        }
        
        $emails = explode(';', $emailString);
        $validEmails = [];
        
        foreach ($emails as $email) {
            $email = trim($email);
            if (!empty($email)) {
                if (EMAIL_REMINDER_VALIDATE_EMAIL_ADDRESSES) {
                    if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                        $validEmails[] = $email;
                    } else {
                        $this->logError("Invalid email address: $email");
                    }
                } else {
                    $validEmails[] = $email;
                }
            }
        }
        
        return $validEmails;
    }
    
    /**
     * Send email with retry logic
     */
    private function sendEmailWithRetry($subject, $htmlBody, $textBody, $recipients) {
        $attempts = 0;
        $maxRetries = EMAIL_REMINDER_MAX_RETRIES;
        $retryDelay = EMAIL_REMINDER_RETRY_DELAY;
        
        while ($attempts <= $maxRetries) {
            try {
                $result = $this->attemptEmailSend($subject, $htmlBody, $textBody, $recipients);
                
                if ($result['success']) {
                    return $result;
                }
                
                $attempts++;
                if ($attempts <= $maxRetries) {
                    $this->logError("Email send attempt $attempts failed, retrying in $retryDelay seconds: " . $result['message']);
                    sleep($retryDelay);
                }
                
            } catch (Exception $e) {
                $attempts++;
                if ($attempts <= $maxRetries) {
                    $this->logError("Email send attempt $attempts failed with exception, retrying: " . $e->getMessage());
                    sleep($retryDelay);
                } else {
                    throw $e;
                }
            }
        }
        
        return [
            'success' => false,
            'message' => "Failed after $maxRetries retry attempts",
            'emails_sent' => 0,
            'emails_failed' => 1
        ];
    }
    
    /**
     * Attempt to send email
     */
    private function attemptEmailSend($subject, $htmlBody, $textBody, $recipients) {
        try {
            // Clear any previous recipients
            $this->mail->clearAddresses();
            $this->mail->clearAttachments();
            
            // Set subject and body
            $this->mail->Subject = $subject;
            $this->mail->msgHTML($htmlBody);
            
            if (!empty($textBody)) {
                $this->mail->AltBody = $textBody;
            }
            
            $emailsSent = 0;
            $emailsFailed = 0;
            $failedRecipients = [];
            
            // Add TO recipients
            foreach ($recipients['to'] as $email) {
                try {
                    $this->mail->addAddress($email);
                    $this->logRecipientAttempt($this->currentEmailLogId, $email, 'to', 'added');
                } catch (Exception $e) {
                    $emailsFailed++;
                    $failedRecipients[] = $email;
                    $this->logRecipientAttempt($this->currentEmailLogId, $email, 'to', 'failed', $e->getMessage());
                }
            }
            
            // Add CC recipients
            foreach ($recipients['cc'] as $email) {
                try {
                    $this->mail->addCC($email);
                    $this->logRecipientAttempt($this->currentEmailLogId, $email, 'cc', 'added');
                } catch (Exception $e) {
                    $emailsFailed++;
                    $failedRecipients[] = $email;
                    $this->logRecipientAttempt($this->currentEmailLogId, $email, 'cc', 'failed', $e->getMessage());
                }
            }
            
            // Add BCC recipients
            foreach ($recipients['bcc'] as $email) {
                try {
                    $this->mail->addBCC($email);
                    $this->logRecipientAttempt($this->currentEmailLogId, $email, 'bcc', 'added');
                } catch (Exception $e) {
                    $emailsFailed++;
                    $failedRecipients[] = $email;
                    $this->logRecipientAttempt($this->currentEmailLogId, $email, 'bcc', 'failed', $e->getMessage());
                }
            }
            
            // Attempt to send
            if ($this->mail->send()) {
                $emailsSent = count($recipients['to']) + count($recipients['cc']) + count($recipients['bcc']) - $emailsFailed;
                
                // Log successful sends
                foreach (['to', 'cc', 'bcc'] as $type) {
                    foreach ($recipients[$type] as $email) {
                        if (!in_array($email, $failedRecipients)) {
                            $this->logRecipientAttempt($this->currentEmailLogId, $email, $type, 'sent');
                        }
                    }
                }
                
                return [
                    'success' => true,
                    'message' => 'Email sent successfully',
                    'emails_sent' => $emailsSent,
                    'emails_failed' => $emailsFailed,
                    'failed_recipients' => $failedRecipients
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'Failed to send email: ' . $this->mail->ErrorInfo,
                    'emails_sent' => 0,
                    'emails_failed' => count($recipients['to']) + count($recipients['cc']) + count($recipients['bcc'])
                ];
            }
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Email sending exception: ' . $e->getMessage(),
                'emails_sent' => 0,
                'emails_failed' => count($recipients['to']) + count($recipients['cc']) + count($recipients['bcc'])
            ];
        }
    }
    
    /**
     * Check rate limiting
     */
    private function checkRateLimit() {
        $currentHour = date('Y-m-d H');
        
        if (!isset($this->rateLimitCheck[$currentHour])) {
            // Count emails sent in current hour from database
            $emailCount = DB::queryFirstField(
                "SELECT COUNT(*) FROM tbl_email_reminder_logs 
                 WHERE sent_datetime >= %s AND sent_datetime < %s",
                $currentHour . ':00:00',
                date('Y-m-d H:i:s', strtotime($currentHour . ':00:00') + 3600)
            );
            
            $this->rateLimitCheck[$currentHour] = intval($emailCount);
        }
        
        return $this->rateLimitCheck[$currentHour] < EMAIL_REMINDER_RATE_LIMIT_PER_HOUR;
    }
    
    /**
     * Log email start
     */
    private function logEmailStart($jobName, $unitId, $subject, $htmlBody, $textBody, $recipients) {
        try {
            $totalRecipients = count($recipients['to']) + count($recipients['cc']) + count($recipients['bcc']);
            
            $emailLogId = DB::insert('tbl_email_reminder_logs', [
                'job_name' => $jobName,
                'unit_id' => $unitId,
                'email_subject' => $subject,
                'email_body_html' => $htmlBody,
                'email_body_text' => $textBody,
                'sender_email' => EMAIL_REMINDER_SMTP_FROM_EMAIL,
                'sender_name' => EMAIL_REMINDER_SMTP_FROM_NAME,
                'sent_datetime' => date('Y-m-d H:i:s'),
                'delivery_status' => 'pending',
                'total_recipients' => $totalRecipients,
                'successful_sends' => 0,
                'failed_sends' => 0
            ]);
            
            // Log individual recipients
            foreach (['to', 'cc', 'bcc'] as $type) {
                foreach ($recipients[$type] as $email) {
                    DB::insert('tbl_email_reminder_recipients', [
                        'email_log_id' => $emailLogId,
                        'recipient_email' => $email,
                        'recipient_type' => $type,
                        'delivery_status' => 'pending',
                        'delivery_datetime' => date('Y-m-d H:i:s')
                    ]);
                }
            }
            
            return $emailLogId;
            
        } catch (Exception $e) {
            $this->logError("Failed to log email start: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Log email final result
     */
    private function logEmailResult($emailLogId, $result) {
        if (!$emailLogId) return;
        
        try {
            DB::update('tbl_email_reminder_logs', [
                'delivery_status' => $result['success'] ? 'sent' : 'failed',
                'successful_sends' => $result['emails_sent'],
                'failed_sends' => $result['emails_failed'],
                'error_message' => isset($result['message']) ? $result['message'] : null,
                'smtp_response' => $this->mail->ErrorInfo ?: 'Success'
            ], 'email_log_id = %i', $emailLogId);
            
        } catch (Exception $e) {
            $this->logError("Failed to log email result: " . $e->getMessage());
        }
    }
    
    /**
     * Log individual recipient attempt
     */
    private function logRecipientAttempt($emailLogId, $email, $type, $status, $errorMessage = null) {
        if (!$emailLogId) return;
        
        try {
            DB::update('tbl_email_reminder_recipients', [
                'delivery_status' => $status,
                'delivery_datetime' => date('Y-m-d H:i:s'),
                'smtp_response' => $errorMessage ?: ($status === 'sent' ? 'Delivered' : $status)
            ], 'email_log_id = %i AND recipient_email = %s AND recipient_type = %s', 
            $emailLogId, $email, $type);
            
        } catch (Exception $e) {
            $this->logError("Failed to log recipient attempt: " . $e->getMessage());
        }
    }
    
    /**
     * Log error message
     */
    private function logError($message) {
        if ($this->logger) {
            $this->logger->logError('EmailReminderService', $message);
        } else {
            error_log("EmailReminderService Error: " . $message);
        }
    }
    
    /**
     * Get email statistics for reporting
     */
    public function getEmailStats($startDate = null, $endDate = null) {
        $whereClause = "1=1";
        $params = [];
        
        if ($startDate) {
            $whereClause .= " AND sent_datetime >= %s";
            $params[] = $startDate;
        }
        
        if ($endDate) {
            $whereClause .= " AND sent_datetime <= %s";
            $params[] = $endDate;
        }
        
        return DB::queryFirstRow(
            "SELECT 
                COUNT(*) as total_emails,
                SUM(successful_sends) as total_successful,
                SUM(failed_sends) as total_failed,
                COUNT(CASE WHEN delivery_status = 'sent' THEN 1 END) as emails_sent,
                COUNT(CASE WHEN delivery_status = 'failed' THEN 1 END) as emails_failed
             FROM tbl_email_reminder_logs 
             WHERE $whereClause",
            ...$params
        );
    }
}
?>