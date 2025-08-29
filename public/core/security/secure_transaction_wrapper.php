<?php
/**
 * ProVal HVAC - Secure Transaction Wrapper
 * 
 * Provides secure database transactions with mandatory session validation
 * and automatic rollback capabilities for all transaction failures.
 * 
 * Security Level: Critical
 * Authentication Required: Yes (enforced at transaction level)
 * 
 * @version 1.0
 * @author ProVal Security Team
 */

// Load required dependencies
require_once(__DIR__ . '/../config/config.php');
require_once(__DIR__ . '/session_timeout_middleware.php');
require_once(__DIR__ . '/../config/db.class.php');

/**
 * Secure Transaction Wrapper Class
 * 
 * Ensures all database transactions are protected with:
 * - Mandatory session validation before transaction start
 * - Automatic rollback on session failures or exceptions
 * - Complete audit trail of transaction operations
 * - Integration with existing session management
 */
class SecureTransaction {
    
    private static $activeTransactions = [];
    private static $transactionCounter = 0;
    
    private $transactionId;
    private $operation;
    private $startTime;
    private $sessionValidated;
    private $transactionStarted;
    private $userId;
    private $userType;
    
    /**
     * Constructor - validates session and prepares transaction
     */
    public function __construct($operation = 'secure_transaction') {
        $this->transactionId = ++self::$transactionCounter;
        $this->operation = $operation;
        $this->startTime = time();
        $this->sessionValidated = false;
        $this->transactionStarted = false;
        
        // Store user info for logging
        $this->userId = $_SESSION['employee_id'] ?? $_SESSION['vendor_id'] ?? null;
        $this->userType = isset($_SESSION['employee_id']) ? 'employee' : 'vendor';
        
        self::$activeTransactions[$this->transactionId] = $this;
    }
    
    /**
     * Static method to execute a simple secure transaction
     * 
     * @param callable $operations Function containing transaction operations
     * @param string $operationName Name for logging purposes
     * @return mixed Result from operations callback
     * @throws Exception If transaction fails or session is invalid
     */
    public static function execute(callable $operations, $operationName = 'secure_operation') {
        $transaction = new self($operationName);
        
        try {
            $transaction->begin();
            $result = $operations();
            $transaction->commit();
            return $result;
            
        } catch (Exception $e) {
            $transaction->rollback();
            throw $e;
        }
    }
    
    /**
     * Begin secure transaction with session validation
     * 
     * @throws SecurityException If session validation fails
     * @throws DatabaseException If transaction cannot be started
     */
    public function begin() {
        try {
            // Step 1: Validate active session
            $this->validateSession();
            
            // Step 2: Extend session for transaction
            extendSessionForTransaction($this->operation);
            
            // Step 3: Start database transaction
            DB::startTransaction();
            $this->transactionStarted = true;
            
            // Step 4: Log transaction start
            $this->logSecurityEvent('transaction_started', 'Secure transaction initiated', [
                'transaction_id' => $this->transactionId,
                'operation' => $this->operation,
                'user_id' => $this->userId,
                'user_type' => $this->userType
            ]);
            
            error_log("SecureTransaction {$this->transactionId}: Started '{$this->operation}' for user {$this->userId}");
            
        } catch (Exception $e) {
            $this->cleanup();
            $this->logSecurityEvent('transaction_start_failed', 'Failed to start secure transaction', [
                'transaction_id' => $this->transactionId,
                'operation' => $this->operation,
                'error' => $e->getMessage(),
                'user_id' => $this->userId
            ]);
            
            throw new SecurityException("Secure transaction failed to start: " . $e->getMessage());
        }
    }
    
    /**
     * Commit transaction with final session validation
     * 
     * @throws SecurityException If session becomes invalid during transaction
     * @throws DatabaseException If commit fails
     */
    public function commit() {
        try {
            // Re-validate session before commit
            $this->validateSession();
            
            if (!$this->transactionStarted) {
                throw new SecurityException("Cannot commit: Transaction not started");
            }
            
            // Commit database transaction
            DB::commit();
            
            // Complete session transaction
            completeTransaction();
            
            // Log successful commit
            $this->logSecurityEvent('transaction_committed', 'Secure transaction committed successfully', [
                'transaction_id' => $this->transactionId,
                'operation' => $this->operation,
                'duration' => time() - $this->startTime,
                'user_id' => $this->userId
            ]);
            
            error_log("SecureTransaction {$this->transactionId}: Committed successfully for user {$this->userId}");
            
            $this->cleanup();
            
        } catch (Exception $e) {
            // Auto-rollback on commit failure
            $this->rollback();
            throw new SecurityException("Secure transaction commit failed: " . $e->getMessage());
        }
    }
    
    /**
     * Rollback transaction with security logging
     * 
     * @param string $reason Optional reason for rollback
     */
    public function rollback($reason = 'explicit_rollback') {
        try {
            if ($this->transactionStarted) {
                DB::rollback();
                error_log("SecureTransaction {$this->transactionId}: Rolled back due to: {$reason}");
            }
            
            // Log rollback event
            $this->logSecurityEvent('transaction_rolled_back', 'Secure transaction rolled back', [
                'transaction_id' => $this->transactionId,
                'operation' => $this->operation,
                'reason' => $reason,
                'duration' => time() - $this->startTime,
                'user_id' => $this->userId
            ]);
            
        } catch (Exception $e) {
            // Log rollback failure
            error_log("SecureTransaction {$this->transactionId}: Rollback failed - " . $e->getMessage());
            
            $this->logSecurityEvent('transaction_rollback_failed', 'Failed to rollback transaction', [
                'transaction_id' => $this->transactionId,
                'operation' => $this->operation,
                'error' => $e->getMessage(),
                'user_id' => $this->userId
            ]);
        } finally {
            $this->cleanup();
        }
    }
    
    /**
     * Validate current session is active and authorized for transactions
     * 
     * @throws SecurityException If session is invalid
     */
    private function validateSession() {
        // Check session exists
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        // Validate user is logged in
        if (!isset($_SESSION['employee_id']) && !isset($_SESSION['vendor_id'])) {
            throw new SecurityException("No valid user session for transaction");
        }
        
        // Check for session destruction marker
        if (isset($_SESSION['session_destroyed'])) {
            throw new SecurityException("Session has been destroyed - cannot perform transactions");
        }
        
        // Validate session timeout
        if (isset($_SESSION['last_activity'])) {
            $inactiveTime = time() - $_SESSION['last_activity'];
            if ($inactiveTime > SESSION_TIMEOUT) {
                throw new SecurityException("Session expired - cannot perform transactions");
            }
        }
        
        // Additional security checks
        if (isset($_SESSION['ip_address']) && $_SESSION['ip_address'] !== $this->getClientIP()) {
            throw new SecurityException("IP address mismatch detected during transaction");
        }
        
        $this->sessionValidated = true;
        
        // Log session validation
        error_log("SecureTransaction {$this->transactionId}: Session validated for user {$this->userId}");
    }
    
    /**
     * Get client IP address securely
     */
    private function getClientIP() {
        // Check for various forwarded IP headers
        $ipKeys = ['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED', 
                   'HTTP_X_CLUSTER_CLIENT_IP', 'HTTP_FORWARDED_FOR', 'HTTP_FORWARDED', 'REMOTE_ADDR'];
        
        foreach ($ipKeys as $key) {
            if (!empty($_SERVER[$key])) {
                $ip = $_SERVER[$key];
                // Take the first IP if comma-separated list
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    }
    
    /**
     * Log security events if SecurityUtils class is available
     */
    private function logSecurityEvent($eventType, $description, $context = []) {
        // Try to log with SecurityUtils if available
        if (class_exists('SecurityUtils')) {
            SecurityUtils::logSecurityEvent($eventType, $description, $context);
        } else {
            // Fallback to error log
            $logData = [
                'event' => $eventType,
                'description' => $description,
                'context' => $context,
                'timestamp' => date('Y-m-d H:i:s'),
                'ip' => $this->getClientIP()
            ];
            error_log("SECURITY EVENT: " . json_encode($logData));
        }
    }
    
    /**
     * Clean up transaction resources
     */
    private function cleanup() {
        // Remove from active transactions
        unset(self::$activeTransactions[$this->transactionId]);
        
        // Clear transaction markers
        $this->transactionStarted = false;
        $this->sessionValidated = false;
        
        // Complete transaction in session
        if (function_exists('completeTransaction')) {
            completeTransaction();
        }
    }
    
    /**
     * Get information about active transactions
     * 
     * @return array Active transaction information
     */
    public static function getActiveTransactions() {
        $active = [];
        foreach (self::$activeTransactions as $id => $transaction) {
            $active[$id] = [
                'transaction_id' => $transaction->transactionId,
                'operation' => $transaction->operation,
                'start_time' => $transaction->startTime,
                'duration' => time() - $transaction->startTime,
                'user_id' => $transaction->userId,
                'user_type' => $transaction->userType
            ];
        }
        return $active;
    }
    
    /**
     * Emergency cleanup of all active transactions
     * Called during session destruction or system shutdown
     */
    public static function emergencyCleanup($reason = 'system_shutdown') {
        foreach (self::$activeTransactions as $transaction) {
            $transaction->rollback($reason);
        }
        self::$activeTransactions = [];
        error_log("SecureTransaction: Emergency cleanup performed - {$reason}");
    }
    
    /**
     * Destructor - ensures cleanup if transaction not properly closed
     */
    public function __destruct() {
        if ($this->transactionStarted) {
            $this->rollback('transaction_not_properly_closed');
            error_log("SecureTransaction {$this->transactionId}: Auto-rollback in destructor");
        }
    }
}

/**
 * Security Exception for transaction-related security failures
 */
class SecurityException extends Exception {
    public function __construct($message, $code = 0, Exception $previous = null) {
        // Log all security exceptions
        error_log("SECURITY EXCEPTION: " . $message);
        parent::__construct($message, $code, $previous);
    }
}

/**
 * Convenience function for simple secure transactions
 * 
 * @param callable $operations Function containing transaction operations
 * @param string $operationName Name for logging purposes
 * @return mixed Result from operations callback
 */
function executeSecureTransaction(callable $operations, $operationName = 'secure_operation') {
    return SecureTransaction::execute($operations, $operationName);
}

/**
 * Register emergency cleanup on session destruction
 */
register_shutdown_function(function() {
    if (count(SecureTransaction::getActiveTransactions()) > 0) {
        SecureTransaction::emergencyCleanup('php_shutdown');
    }
});