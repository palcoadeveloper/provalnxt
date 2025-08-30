<?php
/**
 * SMTP Connection Manager
 * Provides connection pooling and reuse for PHPMailer SMTP connections
 * Reduces SMTP server load by maintaining persistent connections
 */

require_once __DIR__ . '/../config/config.php';

// PHPMailer includes
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

require_once __DIR__ . '/../phpmailer/src/Exception.php';
require_once __DIR__ . '/../phpmailer/src/PHPMailer.php';
require_once __DIR__ . '/../phpmailer/src/SMTP.php';

class SMTPConnectionManager {
    private static $instance = null;
    private static $connection = null;
    private static $connectionTime = null;
    private static $lastActivity = null;
    
    private function __construct() {
        // Singleton pattern
    }
    
    /**
     * Get singleton instance
     */
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Get SMTP connection (create or reuse existing)
     * @return PHPMailer|null
     */
    public function getConnection() {
        try {
            // Check if connection pooling is enabled
            if (!EMAIL_CONNECTION_POOLING_ENABLED) {
                return $this->createNewConnection();
            }
            
            // Check if we have a valid existing connection
            if ($this->isConnectionValid()) {
                // Update last activity time
                self::$lastActivity = time();
                return self::$connection;
            }
            
            // Create new connection
            return $this->createNewConnection();
            
        } catch (Exception $e) {
            error_log("[SMTP Connection Manager] Error getting connection: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Create a new SMTP connection
     * @return PHPMailer|null
     */
    private function createNewConnection() {
        try {
            // Close existing connection if any
            $this->closeConnection();
            
            // Create new PHPMailer instance
            $mail = new PHPMailer(true);
            
            // Configure SMTP settings
            $mail->isSMTP();
            $mail->Host = EMAIL_REMINDER_SMTP_HOST;
            $mail->SMTPAuth = EMAIL_REMINDER_SMTP_AUTH_ENABLED;
            $mail->Username = EMAIL_REMINDER_SMTP_USERNAME;
            $mail->Password = EMAIL_REMINDER_SMTP_PASSWORD;
            $mail->SMTPSecure = EMAIL_REMINDER_SMTP_SECURE;
            $mail->Port = EMAIL_REMINDER_SMTP_PORT;
            $mail->SMTPDebug = 0; // No debug output
            
            // Enable keepalive for connection pooling
            if (EMAIL_CONNECTION_POOLING_ENABLED) {
                $mail->SMTPKeepAlive = true;
                $mail->Timeout = 30; // Connection timeout
                $mail->SMTPOptions = [
                    'ssl' => [
                        'verify_peer' => false,
                        'verify_peer_name' => false,
                        'allow_self_signed' => true
                    ]
                ];
            }
            
            // Set sender configuration
            $mail->setFrom(EMAIL_REMINDER_SMTP_FROM_EMAIL, 'ProVal HVAC Security');
            $mail->addReplyTo(EMAIL_REMINDER_SMTP_FROM_EMAIL, 'ProVal HVAC Security');
            
            // Test connection if pooling is enabled
            if (EMAIL_CONNECTION_POOLING_ENABLED) {
                if (!$mail->smtpConnect()) {
                    error_log("[SMTP Connection Manager] Failed to establish SMTP connection");
                    return null;
                }
            }
            
            // Store connection details
            self::$connection = $mail;
            self::$connectionTime = time();
            self::$lastActivity = time();
            
            error_log("[SMTP Connection Manager] Created new SMTP connection");
            return $mail;
            
        } catch (Exception $e) {
            error_log("[SMTP Connection Manager] Failed to create connection: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Check if current connection is valid
     * @return bool
     */
    private function isConnectionValid() {
        // No connection exists
        if (self::$connection === null) {
            return false;
        }
        
        // Connection pooling disabled
        if (!EMAIL_CONNECTION_POOLING_ENABLED) {
            return false;
        }
        
        // Check connection age
        $currentTime = time();
        $connectionAge = $currentTime - self::$connectionTime;
        $timeSinceLastActivity = $currentTime - self::$lastActivity;
        
        // Connection too old
        if ($connectionAge > EMAIL_CONNECTION_KEEPALIVE_TIME) {
            error_log("[SMTP Connection Manager] Connection expired (age: {$connectionAge}s)");
            return false;
        }
        
        // No activity for too long
        if ($timeSinceLastActivity > (EMAIL_CONNECTION_KEEPALIVE_TIME / 2)) {
            error_log("[SMTP Connection Manager] Connection inactive for too long ({$timeSinceLastActivity}s)");
            return false;
        }
        
        // Check if SMTP connection is still alive
        try {
            if (self::$connection->SMTPKeepAlive && !self::$connection->smtpConnect()) {
                error_log("[SMTP Connection Manager] SMTP connection test failed");
                return false;
            }
        } catch (Exception $e) {
            error_log("[SMTP Connection Manager] SMTP connection validation error: " . $e->getMessage());
            return false;
        }
        
        return true;
    }
    
    /**
     * Send email using managed connection
     * @param string $recipientEmail
     * @param string $recipientName  
     * @param string $subject
     * @param string $htmlBody
     * @param string $textBody
     * @return bool
     */
    public function sendEmail($recipientEmail, $recipientName, $subject, $htmlBody, $textBody) {
        $mail = $this->getConnection();
        
        if ($mail === null) {
            return false;
        }
        
        try {
            // Clear any previous recipients
            $mail->clearAddresses();
            $mail->clearAttachments();
            $mail->clearCustomHeaders();
            
            // Set recipient
            $mail->addAddress($recipientEmail, $recipientName);
            
            // Set content
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body = $htmlBody;
            $mail->AltBody = $textBody;
            
            // Custom headers
            $mail->addCustomHeader('X-Mailer', 'ProVal HVAC Security System');
            $mail->addCustomHeader('X-Priority', '1');
            
            // Send email
            $result = $mail->send();
            
            // Update last activity time
            self::$lastActivity = time();
            
            return $result;
            
        } catch (Exception $e) {
            error_log("[SMTP Connection Manager] Send email failed: " . $e->getMessage());
            
            // If connection failed, try creating a new one
            if (strpos($e->getMessage(), 'SMTP connect()') !== false || 
                strpos($e->getMessage(), 'connection') !== false) {
                error_log("[SMTP Connection Manager] Connection issue detected, creating new connection");
                $this->closeConnection();
                
                // Retry with new connection
                $newMail = $this->getConnection();
                if ($newMail !== null) {
                    try {
                        $newMail->clearAddresses();
                        $newMail->addAddress($recipientEmail, $recipientName);
                        $newMail->isHTML(true);
                        $newMail->Subject = $subject;
                        $newMail->Body = $htmlBody;
                        $newMail->AltBody = $textBody;
                        return $newMail->send();
                    } catch (Exception $retryException) {
                        error_log("[SMTP Connection Manager] Retry also failed: " . $retryException->getMessage());
                    }
                }
            }
            
            return false;
        }
    }
    
    /**
     * Close current SMTP connection
     */
    public function closeConnection() {
        if (self::$connection !== null) {
            try {
                if (self::$connection->SMTPKeepAlive) {
                    self::$connection->smtpClose();
                }
            } catch (Exception $e) {
                // Ignore close errors
            }
            self::$connection = null;
            self::$connectionTime = null;
            self::$lastActivity = null;
            error_log("[SMTP Connection Manager] Closed SMTP connection");
        }
    }
    
    /**
     * Get connection statistics
     * @return array
     */
    public function getConnectionStats() {
        $currentTime = time();
        
        return [
            'has_connection' => self::$connection !== null,
            'connection_age' => self::$connectionTime ? ($currentTime - self::$connectionTime) : 0,
            'last_activity_age' => self::$lastActivity ? ($currentTime - self::$lastActivity) : 0,
            'is_valid' => $this->isConnectionValid(),
            'pooling_enabled' => EMAIL_CONNECTION_POOLING_ENABLED,
            'keepalive_time' => EMAIL_CONNECTION_KEEPALIVE_TIME
        ];
    }
    
    /**
     * Cleanup old connections (can be called by scheduled job)
     */
    public static function cleanup() {
        $instance = self::getInstance();
        if (!$instance->isConnectionValid()) {
            $instance->closeConnection();
        }
    }
}
?>