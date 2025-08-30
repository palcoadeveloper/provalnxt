<?php
/**
 * Smart OTP Email Sender
 * Intelligently chooses between asynchronous and synchronous email sending
 * based on system conditions and requirements
 */

require_once __DIR__ . '/BasicOTPEmailService.php';

class SmartOTPEmailSender {
    private $emailService;
    private $performanceThreshold = 2000; // milliseconds
    
    public function __construct() {
        $this->emailService = new BasicOTPEmailService();
    }
    
    /**
     * Send OTP email with intelligent async/sync selection
     * @param string $recipientEmail
     * @param string $recipientName
     * @param string $otpCode
     * @param int $validityMinutes
     * @param string $employeeId
     * @param int $unitId
     * @param bool $isLoginFlow Whether this is during initial login flow
     * @return array
     */
    public function sendOTP($recipientEmail, $recipientName, $otpCode, $validityMinutes, $employeeId, $unitId, $isLoginFlow = false) {
        // FORCE ASYNC MODE: Commented out synchronous checks to eliminate 2.27s delay
        // This ensures all emails are sent asynchronously for maximum performance
        /*
        // Conditions for forcing synchronous sending
        if ($this->shouldUseSynchronous($isLoginFlow)) {
            if ($isLoginFlow) {
                error_log("[SMART EMAIL] Using synchronous sending for login flow due to system conditions");
            } else {
                error_log("[SMART EMAIL] Using synchronous sending due to system conditions");
            }
            return $this->emailService->sendOTP($recipientEmail, $recipientName, $otpCode, $validityMinutes, $employeeId, $unitId);
        }
        */
        
        // Always try asynchronous first (forced async mode)
        error_log("[SMART EMAIL] FORCED ASYNC MODE: Skipping synchronous checks for maximum performance");
        
        // Debug async availability
        $asyncAvailable = $this->isAsyncAvailable();
        if (!$asyncAvailable) {
            // Log why async is not available
            $reasons = [];
            if (!defined('EMAIL_ASYNC_ENABLED') || !EMAIL_ASYNC_ENABLED) {
                $reasons[] = 'EMAIL_ASYNC_ENABLED not set or false';
            }
            if (!function_exists('exec')) {
                $reasons[] = 'exec() function not available';
            }
            $backgroundScript = __DIR__ . '/background_email_sender.php';
            if (!file_exists($backgroundScript)) {
                $reasons[] = 'background script does not exist: ' . $backgroundScript;
            } elseif (!is_readable($backgroundScript)) {
                $reasons[] = 'background script not readable: ' . $backgroundScript;
            }
            $phpBinary = $this->getPhpBinary();
            if (!$phpBinary) {
                $reasons[] = 'PHP binary could not be determined';
            } elseif (!file_exists($phpBinary)) {
                $reasons[] = 'PHP binary does not exist: ' . $phpBinary;
            }
            error_log("[SMART EMAIL] ASYNC NOT AVAILABLE: " . implode(', ', $reasons));
        }
        
        if ($asyncAvailable) {
            $result = $this->emailService->sendOTPAsync($recipientEmail, $recipientName, $otpCode, $validityMinutes, $employeeId, $unitId);
            
            // If async succeeded or failed with fallback, return the result
            if (isset($result['success'])) {
                if ($result['success']) {
                    error_log("[SMART EMAIL] Successfully initiated async email for user: $employeeId");
                } else {
                    error_log("[SMART EMAIL] Async email failed, result: " . json_encode($result));
                }
                return $result;
            }
        }
        
        // Fallback to synchronous
        error_log("[SMART EMAIL] Falling back to synchronous email for user: $employeeId");
        return $this->emailService->sendOTP($recipientEmail, $recipientName, $otpCode, $validityMinutes, $employeeId, $unitId);
    }
    
    /**
     * Check if asynchronous email sending is available
     * @return bool
     */
    private function isAsyncAvailable() {
        // Check if async is enabled in configuration
        if (!defined('EMAIL_ASYNC_ENABLED') || !EMAIL_ASYNC_ENABLED) {
            return false;
        }
        
        // Check if exec function is available
        if (!function_exists('exec')) {
            return false;
        }
        
        // Check if background script exists
        $backgroundScript = __DIR__ . '/background_email_sender.php';
        if (!file_exists($backgroundScript) || !is_readable($backgroundScript)) {
            return false;
        }
        
        // Check if PHP binary is available
        $phpBinary = $this->getPhpBinary();
        if (!$phpBinary || !file_exists($phpBinary)) {
            return false;
        }
        
        return true;
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
     * Determine if synchronous sending should be forced
     * @param bool $isLoginFlow Whether this is during initial login flow
     * @return bool
     */
    private function shouldUseSynchronous($isLoginFlow = false) {
        // Force sync in CLI mode (for scripts, cron jobs, etc.)
        if (php_sapi_name() === 'cli') {
            error_log("[SMART EMAIL] Forcing sync: CLI mode detected");
            return true;
        }
        
        // For login flow, be more aggressive about using async
        if ($isLoginFlow) {
            // Only force sync for login if explicitly configured or under extreme load
            if (defined('FORCE_SYNC_LOGIN_EMAIL') && FORCE_SYNC_LOGIN_EMAIL) {
                error_log("[SMART EMAIL] Forcing sync: FORCE_SYNC_LOGIN_EMAIL enabled");
                return true;
            }
            
            // Check for extreme load conditions only during login
            if ($this->isSystemUnderExtremeLoad()) {
                error_log("[SMART EMAIL] Forcing sync: System under extreme load during login");
                return true;
            }
            
            error_log("[SMART EMAIL] Using async for login flow");
            return false;
        }
        
        // Force sync if critical email (could be extended with more logic)
        if (defined('FORCE_SYNC_EMAIL') && FORCE_SYNC_EMAIL) {
            error_log("[SMART EMAIL] Forcing sync: FORCE_SYNC_EMAIL enabled");
            return true;
        }
        
        // Force sync under high load conditions (for non-login operations)
        if ($this->isSystemUnderHighLoad()) {
            error_log("[SMART EMAIL] Forcing sync: System under high load");
            return true;
        }
        
        return false;
    }
    
    /**
     * Check if system is under high load
     * @return bool
     */
    private function isSystemUnderHighLoad() {
        // Check server load average on Unix systems - relaxed threshold
        if (PHP_OS_FAMILY !== 'Windows' && function_exists('sys_getloadavg')) {
            $load = sys_getloadavg();
            if ($load && $load[0] > 4.0) { // 1-minute load average > 4.0 (relaxed from 2.0)
                error_log("[SMART EMAIL] High load detected: " . $load[0]);
                return true;
            }
        }
        
        // Check memory usage - relaxed threshold
        $memoryUsage = memory_get_usage(true);
        $memoryLimit = $this->parseMemoryLimit(ini_get('memory_limit'));
        if ($memoryLimit > 0 && $memoryUsage > ($memoryLimit * 0.90)) { // 90% memory usage (relaxed from 80%)
            error_log("[SMART EMAIL] High memory usage: " . round(($memoryUsage / $memoryLimit) * 100) . "%");
            return true;
        }
        
        return false;
    }
    
    /**
     * Check if system is under extreme load (used for login flow)
     * @return bool
     */
    private function isSystemUnderExtremeLoad() {
        // Check server load average - very high threshold for login flow
        if (PHP_OS_FAMILY !== 'Windows' && function_exists('sys_getloadavg')) {
            $load = sys_getloadavg();
            if ($load && $load[0] > 8.0) { // 1-minute load average > 8.0
                error_log("[SMART EMAIL] Extreme load detected: " . $load[0]);
                return true;
            }
        }
        
        // Check memory usage - very high threshold
        $memoryUsage = memory_get_usage(true);
        $memoryLimit = $this->parseMemoryLimit(ini_get('memory_limit'));
        if ($memoryLimit > 0 && $memoryUsage > ($memoryLimit * 0.95)) { // 95% memory usage
            error_log("[SMART EMAIL] Extreme memory usage: " . round(($memoryUsage / $memoryLimit) * 100) . "%");
            return true;
        }
        
        return false;
    }
    
    /**
     * Parse PHP memory limit setting
     * @param string $limit
     * @return int
     */
    private function parseMemoryLimit($limit) {
        if ($limit == -1) {
            return -1; // Unlimited
        }
        
        $value = (int) $limit;
        $unit = strtoupper(substr($limit, -1));
        
        switch ($unit) {
            case 'G':
                $value *= 1024;
            case 'M':
                $value *= 1024;
            case 'K':
                $value *= 1024;
        }
        
        return $value;
    }
    
    /**
     * Get health check information
     * @return array
     */
    public function healthCheck() {
        return [
            'async_available' => $this->isAsyncAvailable(),
            'should_use_sync' => false, // FORCED ASYNC MODE: Always return false
            'forced_async_mode' => true, // Indicate that sync checks are disabled
            'system_load' => $this->isSystemUnderHighLoad(),
            'email_service_health' => $this->emailService->healthCheck()
        ];
    }
    
    /**
     * Get performance statistics
     * @return array
     */
    public function getPerformanceStats() {
        $stats = $this->emailService->getPerformanceStats();
        $stats['smart_sender'] = [
            'async_available' => $this->isAsyncAvailable(),
            'current_mode' => $this->shouldUseSynchronous() ? 'synchronous' : 'asynchronous'
        ];
        return $stats;
    }
}
?>