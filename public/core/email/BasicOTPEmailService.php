<?php
/**
 * Basic OTP Email Service
 * Professional email service using PHPMailer with SMTP configuration
 * Provides reliable email delivery for OTP verification codes
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../security/rate_limiting_utils.php';
require_once __DIR__ . '/SMTPConnectionManager.php';

// PHPMailer includes
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

require_once __DIR__ . '/../phpmailer/src/Exception.php';
require_once __DIR__ . '/../phpmailer/src/PHPMailer.php';
require_once __DIR__ . '/../phpmailer/src/SMTP.php';

class BasicOTPEmailService {
    
    /**
     * Send OTP via email using PHP's built-in mail() function
     * @param string $recipientEmail Recipient email address
     * @param string $recipientName Recipient name
     * @param string $otpCode OTP code to send
     * @param int $validityMinutes OTP validity in minutes
     * @param string $employeeId Employee ID for logging
     * @param int $unitId Unit ID for logging
     * @return array Result of email sending operation
     */
    public function sendOTP($recipientEmail, $recipientName, $otpCode, $validityMinutes, $employeeId, $unitId) {
        // Log email attempt start (without sensitive data)
        error_log("[OTP EMAIL] Starting OTP email send for user: $employeeId");
        
        try {
            // Check rate limiting for OTP emails
            $rateLimitKey = 'otp_email_' . $recipientEmail;
            $rateLimitResult = RateLimiter::checkRateLimit($rateLimitKey, 5, 300); // 5 emails per 5 minutes
            
            if (!$rateLimitResult['allowed']) {
                if (function_exists('logSecurityEvent')) {
                    logSecurityEvent($employeeId, 'otp_email_rate_limited', 
                        "OTP email rate limited for: $recipientEmail", $unitId);
                }
                return [
                    'success' => false,
                    'error' => 'Too many OTP requests. Please try again later.',
                    'retry_after' => $rateLimitResult['lockout_expires'] - time()
                ];
            }
            
            // Email configuration
            $subject = 'ProVal HVAC - Security Verification Code';
            
            // Generate email content using optimized templates
            $templates = $this->generateOptimizedTemplates($recipientName, $otpCode, $validityMinutes);
            $htmlBody = $templates['html'];
            $textBody = $templates['text'];
            
            // Email configuration complete
            
            // Create PHPMailer instance and send email
            $success = $this->sendViaPHPMailer($recipientEmail, $recipientName, $subject, $htmlBody, $textBody);
            
            
            if ($success) {
                // Email sent successfully
                
                // Log security event
                if (function_exists('logSecurityEvent')) {
                    logSecurityEvent($employeeId, 'otp_email_sent', 
                        "OTP email sent successfully to: $recipientEmail", $unitId);
                }
                
                return [
                    'success' => true,
                    'message' => 'Verification code sent successfully'
                ];
            } else {
                // Email send failed
                
                throw new Exception("Failed to send email via PHPMailer SMTP");
            }
            
        } catch (Exception $e) {
            // Log failure without exposing sensitive information
            error_log("[OTP EMAIL] EXCEPTION: " . $e->getMessage());
            
            if (function_exists('logSecurityEvent')) {
                logSecurityEvent($employeeId, 'otp_email_failed', 
                    "OTP email failed to: $recipientEmail - " . $e->getMessage(), $unitId);
            }
            
            return [
                'success' => false,
                'error' => 'Failed to send verification code. Please try again.',
                'exception' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Send email using PHPMailer with SMTP configuration
     * @param string $recipientEmail Recipient email address
     * @param string $recipientName Recipient name
     * @param string $subject Email subject
     * @param string $htmlBody HTML email content
     * @param string $textBody Plain text email content
     * @return bool Success status
     */
    private function sendViaPHPMailer($recipientEmail, $recipientName, $subject, $htmlBody, $textBody) {
        try {
            // Use SMTP Connection Manager for improved performance
            $connectionManager = SMTPConnectionManager::getInstance();
            $result = $connectionManager->sendEmail($recipientEmail, $recipientName, $subject, $htmlBody, $textBody);
            
            if ($result) {
                // Log connection stats for monitoring
                $stats = $connectionManager->getConnectionStats();
                error_log("[OTP EMAIL] Email sent via " . ($stats['has_connection'] ? 'pooled' : 'new') . " connection");
            }
            
            return $result;
            
        } catch (Exception $e) {
            error_log("[OTP EMAIL] Connection Manager Exception: " . $e->getMessage());
            
            // Fallback to direct PHPMailer if connection manager fails
            return $this->sendViaDirectPHPMailer($recipientEmail, $recipientName, $subject, $htmlBody, $textBody);
        }
    }
    
    /**
     * Fallback method using direct PHPMailer (no connection pooling)
     */
    private function sendViaDirectPHPMailer($recipientEmail, $recipientName, $subject, $htmlBody, $textBody) {
        try {
            $mail = new PHPMailer(true);
            
            $mail->isSMTP();
            $mail->Host = EMAIL_REMINDER_SMTP_HOST;
            $mail->SMTPAuth = EMAIL_REMINDER_SMTP_AUTH_ENABLED;
            $mail->Username = EMAIL_REMINDER_SMTP_USERNAME;
            $mail->Password = EMAIL_REMINDER_SMTP_PASSWORD;
            $mail->SMTPSecure = EMAIL_REMINDER_SMTP_SECURE;
            $mail->Port = EMAIL_REMINDER_SMTP_PORT;
            $mail->SMTPDebug = 0;
            
            $mail->setFrom(EMAIL_REMINDER_SMTP_FROM_EMAIL, 'ProVal HVAC Security');
            $mail->addReplyTo(EMAIL_REMINDER_SMTP_FROM_EMAIL, 'ProVal HVAC Security');
            $mail->addAddress($recipientEmail, $recipientName);
            
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body = $htmlBody;
            $mail->AltBody = $textBody;
            
            $mail->addCustomHeader('X-Mailer', 'ProVal HVAC Security System');
            $mail->addCustomHeader('X-Priority', '1');
            
            return $mail->send();
            
        } catch (Exception $e) {
            error_log("[OTP EMAIL] Direct PHPMailer Exception: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Generate HTML email template for OTP
     * @param string $recipientName Recipient name
     * @param string $otpCode OTP code
     * @param int $validityMinutes Validity in minutes
     * @return string HTML email content
     */
    private function generateOTPEmailTemplate($recipientName, $otpCode, $validityMinutes) {
        $currentTime = date('Y-m-d H:i:s');
        $expiryTime = date('Y-m-d H:i:s', strtotime("+{$validityMinutes} minutes"));
        
        return "
<!DOCTYPE html>
<html>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>ProVal HVAC - Security Verification</title>
</head>
<body style='font-family: Arial, sans-serif; margin: 0; padding: 20px; background-color: #f8f9fa;'>
    <div style='max-width: 600px; margin: 0 auto; background-color: #ffffff; border-radius: 8px; overflow: hidden;'>
        <div style='background: #667eea; padding: 30px 20px; text-align: center;'>
            <h1 style='color: #ffffff; margin: 0; font-size: 24px;'>ProVal HVAC Security</h1>
        </div>
        
        <div style='padding: 30px 20px;'>
            <h2>Security Verification Required</h2>
            <p>Hello <strong>" . htmlspecialchars($recipientName) . "</strong>,</p>
            <p>Please use the verification code below to complete your login:</p>
            
            <div style='background-color: #f8f9fa; border: 2px solid #667eea; border-radius: 8px; padding: 25px; text-align: center; margin: 25px 0;'>
                <div style='color: #666; font-size: 16px; margin-bottom: 10px;'>Your Verification Code</div>
                <div style='font-size: 36px; font-weight: bold; color: #667eea; letter-spacing: 6px; margin: 15px 0;'>" . htmlspecialchars($otpCode) . "</div>
                <div style='color: #666; font-size: 14px; margin-top: 10px;'>Valid for <strong>{$validityMinutes} minutes</strong> only</div>
            </div>
            
            <p><strong>Expires:</strong> " . htmlspecialchars($expiryTime) . "</p>
            
            <div style='background-color: #fff3cd; border: 1px solid #ffeaa7; border-radius: 6px; padding: 15px; margin: 20px 0;'>
                <strong>Security Notice:</strong> This code can only be used once. Never share it with anyone.
                If you didn't request this login, contact your IT administrator immediately.
            </div>
        </div>
        
        <div style='background-color: #f8f9fa; padding: 20px; text-align: center; color: #6c757d; font-size: 12px;'>
            <p><strong>ProVal HVAC Validation System</strong></p>
            <p>&copy; " . date('Y') . " ProVal HVAC. All rights reserved.</p>
        </div>
    </div>
</body>
</html>";
    }
    
    /**
     * Generate plain text email template for OTP
     * @param string $recipientName Recipient name
     * @param string $otpCode OTP code
     * @param int $validityMinutes Validity in minutes
     * @return string Plain text email content
     */
    private function generatePlainTextTemplate($recipientName, $otpCode, $validityMinutes) {
        $currentTime = date('Y-m-d H:i:s');
        $expiryTime = date('Y-m-d H:i:s', strtotime("+{$validityMinutes} minutes"));
        
        return "ProVal HVAC Security - Verification Required

Hello " . $recipientName . ",

We've received a login request for your ProVal HVAC account. To complete the login process, please use the verification code below:

VERIFICATION CODE: " . $otpCode . "

Generated at: " . $currentTime . "
Expires at: " . $expiryTime . "
Valid for: " . $validityMinutes . " minutes only

SECURITY NOTICE:
- This code can only be used once
- Never share this code with anyone
- ProVal staff will never ask for your verification code
- If you didn't request this login, contact your IT administrator immediately

Didn't request this? If you didn't try to log in, please contact your system administrator immediately.

---
ProVal HVAC Validation System
This is an automated security notification.

© " . date('Y') . " ProVal HVAC. All rights reserved.";
    }
    
    /**
     * Send OTP email asynchronously for better performance
     * @param string $recipientEmail Recipient email address
     * @param string $recipientName Recipient name
     * @param string $otpCode OTP code to send
     * @param int $validityMinutes OTP validity in minutes
     * @param string $employeeId Employee ID for logging
     * @param int $unitId Unit ID for logging
     * @return array Result indicating if async process was started
     */
    public function sendOTPAsync($recipientEmail, $recipientName, $otpCode, $validityMinutes, $employeeId, $unitId) {
        // Check if async email sending is enabled
        if (!EMAIL_ASYNC_ENABLED) {
            // Fall back to synchronous sending
            return $this->sendOTP($recipientEmail, $recipientName, $otpCode, $validityMinutes, $employeeId, $unitId);
        }
        
        try {
            // Validate input parameters
            if (!filter_var($recipientEmail, FILTER_VALIDATE_EMAIL)) {
                return [
                    'success' => false,
                    'error' => 'Invalid email address',
                    'async' => false
                ];
            }
            
            // Check user-specific rate limiting before starting background process
            $rateLimitKey = 'otp_email_user_' . $employeeId;
            $rateLimitResult = RateLimiter::checkRateLimit($rateLimitKey, 1, EMAIL_OTP_RATE_LIMIT_SECONDS);
            
            if (!$rateLimitResult['allowed']) {
                return [
                    'success' => false,
                    'error' => 'Too many OTP requests. Please wait before requesting another.',
                    'retry_after' => EMAIL_OTP_RATE_LIMIT_SECONDS,
                    'async' => false
                ];
            }
            
            // Build command for background email sending
            $backgroundScript = __DIR__ . '/background_email_sender.php';
            $phpBinary = $this->getPhpBinary();
            
            // Escape arguments for shell execution
            $escapedArgs = [
                escapeshellarg($recipientEmail),
                escapeshellarg($recipientName),
                escapeshellarg($otpCode),
                escapeshellarg($validityMinutes),
                escapeshellarg($employeeId),
                escapeshellarg($unitId)
            ];
            
            $command = $phpBinary . ' ' . escapeshellarg($backgroundScript) . ' ' . implode(' ', $escapedArgs);
            
            // Start background process
            if (PHP_OS_FAMILY === 'Windows') {
                // Windows command
                $command = 'start /B ' . $command . ' > nul 2>&1';
            } else {
                // Unix/Linux command  
                $command .= ' > /dev/null 2>&1 &';
            }
            
            // Execute background process
            exec($command, $output, $returnCode);
            
            // Check if background process started successfully
            if ($returnCode === 0) {
                // Log the async email initiation
                error_log("[OTP EMAIL ASYNC] Started background email process for user: $employeeId");
                
                if (function_exists('logSecurityEvent')) {
                    logSecurityEvent($employeeId, 'otp_email_async_started', 
                        "OTP email background process initiated for: $recipientEmail", $unitId);
                }
                
                return [
                    'success' => true,
                    'message' => 'Verification code is being sent',
                    'async' => true
                ];
            } else {
                // Background process failed to start
                error_log("[OTP EMAIL ASYNC] Failed to start background process, return code: $returnCode");
                error_log("[OTP EMAIL ASYNC] Command was: $command");
                if (!empty($output)) {
                    error_log("[OTP EMAIL ASYNC] Process output: " . implode("\n", $output));
                }
                
                // Fall back to synchronous sending
                error_log("[OTP EMAIL ASYNC] Falling back to synchronous sending due to process start failure");
                return $this->sendOTP($recipientEmail, $recipientName, $otpCode, $validityMinutes, $employeeId, $unitId);
            }
            
        } catch (Exception $e) {
            error_log("[OTP EMAIL ASYNC] Failed to start background process: " . $e->getMessage());
            
            // Fall back to synchronous sending on failure
            error_log("[OTP EMAIL ASYNC] Falling back to synchronous email sending");
            return $this->sendOTP($recipientEmail, $recipientName, $otpCode, $validityMinutes, $employeeId, $unitId);
        }
    }
    
    /**
     * Optimized template generation with caching
     * @param string $recipientName Recipient name  
     * @param string $otpCode OTP code
     * @param int $validityMinutes Validity in minutes
     * @return array Both HTML and text templates
     */
    private function generateOptimizedTemplates($recipientName, $otpCode, $validityMinutes) {
        static $templateCache = [];
        
        // Generate cache key
        $cacheKey = 'template_' . $validityMinutes;
        
        // Check if template caching is enabled and template is cached
        if (EMAIL_TEMPLATE_CACHE_ENABLED && isset($templateCache[$cacheKey])) {
            $templates = $templateCache[$cacheKey];
        } else {
            // Generate base templates
            $templates = [
                'html' => $this->generateOptimizedHTMLTemplate($validityMinutes),
                'text' => $this->generateOptimizedTextTemplate($validityMinutes)
            ];
            
            // Cache the templates if caching is enabled
            if (EMAIL_TEMPLATE_CACHE_ENABLED) {
                $templateCache[$cacheKey] = $templates;
            }
        }
        
        // Replace variables in templates
        $currentTime = date('Y-m-d H:i:s');
        $expiryTime = date('Y-m-d H:i:s', strtotime("+{$validityMinutes} minutes"));
        
        $replacements = [
            '{RECIPIENT_NAME}' => htmlspecialchars($recipientName),
            '{OTP_CODE}' => htmlspecialchars($otpCode),
            '{VALIDITY_MINUTES}' => $validityMinutes,
            '{CURRENT_TIME}' => $currentTime,
            '{EXPIRY_TIME}' => $expiryTime,
            '{CURRENT_YEAR}' => date('Y')
        ];
        
        return [
            'html' => str_replace(array_keys($replacements), array_values($replacements), $templates['html']),
            'text' => str_replace(array_keys($replacements), array_values($replacements), $templates['text'])
        ];
    }
    
    /**
     * Generate optimized HTML template for caching
     */
    private function generateOptimizedHTMLTemplate($validityMinutes) {
        return "<!DOCTYPE html>
<html>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>ProVal HVAC - Security Verification</title>
</head>
<body style='font-family: Arial, sans-serif; margin: 0; padding: 20px; background-color: #f8f9fa;'>
    <div style='max-width: 600px; margin: 0 auto; background-color: #ffffff; border-radius: 8px; overflow: hidden;'>
        <div style='background: #667eea; padding: 30px 20px; text-align: center;'>
            <h1 style='color: #ffffff; margin: 0; font-size: 24px;'>ProVal HVAC Security</h1>
        </div>
        <div style='padding: 30px 20px;'>
            <h2>Security Verification Required</h2>
            <p>Hello <strong>{RECIPIENT_NAME}</strong>,</p>
            <p>Please use the verification code below to complete your login:</p>
            <div style='background-color: #f8f9fa; border: 2px solid #667eea; border-radius: 8px; padding: 25px; text-align: center; margin: 25px 0;'>
                <div style='color: #666; font-size: 16px; margin-bottom: 10px;'>Your Verification Code</div>
                <div style='font-size: 36px; font-weight: bold; color: #667eea; letter-spacing: 6px; margin: 15px 0;'>{OTP_CODE}</div>
                <div style='color: #666; font-size: 14px; margin-top: 10px;'>Valid for <strong>{VALIDITY_MINUTES} minutes</strong> only</div>
            </div>
            <p><strong>Expires:</strong> {EXPIRY_TIME}</p>
            <div style='background-color: #fff3cd; border: 1px solid #ffeaa7; border-radius: 6px; padding: 15px; margin: 20px 0;'>
                <strong>Security Notice:</strong> This code can only be used once. Never share it with anyone.
                If you didn't request this login, contact your IT administrator immediately.
            </div>
        </div>
        <div style='background-color: #f8f9fa; padding: 20px; text-align: center; color: #6c757d; font-size: 12px;'>
            <p><strong>ProVal HVAC Validation System</strong></p>
            <p>&copy; {CURRENT_YEAR} ProVal HVAC. All rights reserved.</p>
        </div>
    </div>
</body>
</html>";
    }
    
    /**
     * Generate optimized text template for caching
     */
    private function generateOptimizedTextTemplate($validityMinutes) {
        return "ProVal HVAC Security - Verification Required

Hello {RECIPIENT_NAME},

We've received a login request for your ProVal HVAC account. To complete the login process, please use the verification code below:

VERIFICATION CODE: {OTP_CODE}

Generated at: {CURRENT_TIME}
Expires at: {EXPIRY_TIME}
Valid for: {VALIDITY_MINUTES} minutes only

SECURITY NOTICE:
- This code can only be used once
- Never share this code with anyone
- ProVal staff will never ask for your verification code
- If you didn't request this login, contact your IT administrator immediately

---
ProVal HVAC Validation System
This is an automated security notification.

© {CURRENT_YEAR} ProVal HVAC. All rights reserved.";
    }
    
    /**
     * Get PHP binary path with fallback detection
     * @return string|null
     */
    private function getPhpBinary() {
        // Try PHP_BINARY constant first
        if (defined('PHP_BINARY') && PHP_BINARY && file_exists(PHP_BINARY)) {
            return PHP_BINARY;
        }
        
        // Common PHP binary locations
        $phpPaths = [
            '/usr/bin/php',
            '/usr/local/bin/php',
            '/opt/homebrew/bin/php',
            '/opt/homebrew/Cellar/php/8.4.5/bin/php', // Based on our test results
            '/bin/php',
            'php' // Let shell find it
        ];
        
        foreach ($phpPaths as $path) {
            if ($path === 'php') {
                // Test if php is available in PATH
                $output = null;
                $returnCode = null;
                @exec('which php 2>/dev/null', $output, $returnCode);
                if ($returnCode === 0 && !empty($output[0]) && file_exists($output[0])) {
                    return $output[0];
                }
            } elseif (file_exists($path)) {
                return $path;
            }
        }
        
        return null;
    }
    
    /**
     * Get email service performance statistics
     * @return array Performance and health metrics
     */
    public function getPerformanceStats() {
        $connectionManager = SMTPConnectionManager::getInstance();
        $connectionStats = $connectionManager->getConnectionStats();
        
        return [
            'async_enabled' => EMAIL_ASYNC_ENABLED,
            'connection_pooling_enabled' => EMAIL_CONNECTION_POOLING_ENABLED,
            'template_caching_enabled' => EMAIL_TEMPLATE_CACHE_ENABLED,
            'rate_limit_seconds' => EMAIL_OTP_RATE_LIMIT_SECONDS,
            'global_rate_limit_per_minute' => EMAIL_GLOBAL_RATE_LIMIT_PER_MINUTE,
            'connection_stats' => $connectionStats,
            'system_load' => sys_getloadavg()[0] ?? 0
        ];
    }
    
    /**
     * Health check for email service
     * @return array Health status
     */
    public function healthCheck() {
        try {
            $connectionManager = SMTPConnectionManager::getInstance();
            $stats = $connectionManager->getConnectionStats();
            
            return [
                'status' => 'healthy',
                'async_available' => EMAIL_ASYNC_ENABLED && function_exists('exec'),
                'connection_manager_available' => true,
                'smtp_connection_valid' => $stats['is_valid'],
                'last_check' => date('Y-m-d H:i:s')
            ];
        } catch (Exception $e) {
            return [
                'status' => 'unhealthy',
                'error' => $e->getMessage(),
                'last_check' => date('Y-m-d H:i:s')
            ];
        }
    }
}
?>