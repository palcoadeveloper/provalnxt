<?php
/**
 * Enhanced Session Management for ProVal HVAC Security
 * 
 * Provides secure session handling with timeout management, security tracking,
 * and protection against session-based attacks.
 * 
 * @version 1.0
 * @author ProVal Security Team
 */

if (!class_exists('InputValidator')) {
    require_once '../validation/input_validation_utils.php';
}

class EnhancedSessionManager {
    
    const SESSION_TIMEOUT = 3600; // 1 hour default
    const ACTIVITY_TIMEOUT = 1800; // 30 minutes inactivity
    const MAX_SESSIONS_PER_USER = 3;
    const SESSION_REGENERATE_INTERVAL = 300; // 5 minutes
    
    private static $initialized = false;
    
    /**
     * Initialize secure session with enhanced security
     * 
     * @param array $options Session configuration options
     */
    public static function initialize($options = []) {
        if (self::$initialized) {
            return;
        }
        
        // Set secure session configuration
        ini_set('session.use_only_cookies', 1);
        ini_set('session.use_strict_mode', 1);
        ini_set('session.cookie_httponly', 1);
        ini_set('session.cookie_secure', isset($_SERVER['HTTPS']) ? 1 : 0);
        ini_set('session.cookie_samesite', 'Strict');
        ini_set('session.gc_maxlifetime', self::SESSION_TIMEOUT);
        ini_set('session.hash_function', 'sha256');
        ini_set('session.entropy_length', 32);
        
        // Set session name
        session_name('PROVAL_SESSION');
        
        // Set session save path (should be outside web root in production)
        $sessionPath = isset($options['save_path']) ? $options['save_path'] : sys_get_temp_dir() . '/proval_sessions';
        if (!is_dir($sessionPath)) {
            mkdir($sessionPath, 0700, true);
        }
        session_save_path($sessionPath);
        
        // Start session if not already started
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        self::$initialized = true;
        
        // Initialize session security
        self::initializeSessionSecurity();
    }
    
    /**
     * Initialize session security tracking
     */
    private static function initializeSessionSecurity() {
        $now = time();
        $sessionId = session_id();
        
        // Initialize session metadata if not exists
        if (!isset($_SESSION['security'])) {
            $_SESSION['security'] = [
                'created' => $now,
                'last_activity' => $now,
                'last_regeneration' => $now,
                'ip_address' => SecurityUtils::getClientIP(),
                'user_agent' => isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '',
                'login_time' => $now,
                'flags' => []
            ];
            
            // Log session creation
            self::logSessionEvent('session_created', 'New session created');
        }
        
        // Update last activity
        $_SESSION['security']['last_activity'] = $now;
        
        // Check for session fixation attempts
        self::validateSessionIntegrity();
        
        // Regenerate session ID periodically
        if ($now - $_SESSION['security']['last_regeneration'] > self::SESSION_REGENERATE_INTERVAL) {
            self::regenerateSessionId();
        }
    }
    
    /**
     * Validate active session and handle timeouts
     * 
     * @return bool True if session is valid
     */
    public static function validateActiveSession() {
        if (!self::$initialized) {
            self::initialize();
        }
        
        $now = time();
        
        // Check if session data exists
        if (!isset($_SESSION['security'])) {
            self::destroySession('invalid_session_data');
            return false;
        }
        
        $security = $_SESSION['security'];
        
        // Check absolute session timeout
        if ($now - $security['created'] > self::SESSION_TIMEOUT) {
            self::destroySession('session_timeout');
            return false;
        }
        
        // Check inactivity timeout
        if ($now - $security['last_activity'] > self::ACTIVITY_TIMEOUT) {
            self::destroySession('inactivity_timeout');
            return false;
        }
        
        // Check IP address consistency (optional - can be disabled for mobile users)
        if (defined('SESSION_CHECK_IP') && SESSION_CHECK_IP) {
            $currentIP = SecurityUtils::getClientIP();
            if ($security['ip_address'] !== $currentIP) {
                self::destroySession('ip_address_changed', [
                    'original_ip' => $security['ip_address'],
                    'current_ip' => $currentIP
                ]);
                return false;
            }
        }
        
        // Check user agent consistency (basic bot protection)
        if (defined('SESSION_CHECK_USER_AGENT') && SESSION_CHECK_USER_AGENT) {
            $currentUserAgent = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '';
            if ($security['user_agent'] !== $currentUserAgent) {
                self::destroySession('user_agent_changed', [
                    'original_ua' => substr($security['user_agent'], 0, 100),
                    'current_ua' => substr($currentUserAgent, 0, 100)
                ]);
                return false;
            }
        }
        
        // Update last activity
        $_SESSION['security']['last_activity'] = $now;
        
        // Update database session tracking
        self::updateSessionTracking();
        
        return true;
    }
    
    /**
     * Create secure login session
     * 
     * @param array $userData User data to store in session
     * @return bool True if session created successfully
     */
    public static function createLoginSession($userData) {
        if (!self::$initialized) {
            self::initialize();
        }
        
        // Regenerate session ID to prevent fixation
        self::regenerateSessionId();
        
        $now = time();
        $sessionId = session_id();
        
        // Check for maximum sessions per user
        if (isset($userData['user_id'])) {
            $activeSessions = self::getActiveSessionsForUser($userData['user_id']);
            if (count($activeSessions) >= self::MAX_SESSIONS_PER_USER) {
                // Terminate oldest session
                self::terminateOldestSession($userData['user_id']);
            }
        }
        
        // Store user data in session
        foreach ($userData as $key => $value) {
            $_SESSION[$key] = $value;
        }
        
        // Update security metadata
        $_SESSION['security']['login_time'] = $now;
        $_SESSION['security']['last_activity'] = $now;
        $_SESSION['security']['flags'][] = 'authenticated';
        
        // Log to database
        if (class_exists('DB') && isset($userData['user_id'])) {
            try {
                DB::insert('session_security', [
                    'session_id' => $sessionId,
                    'user_id' => $userData['user_id'],
                    'ip_address' => $_SESSION['security']['ip_address'],
                    'user_agent' => substr($_SESSION['security']['user_agent'], 0, 255),
                    'security_flags' => json_encode($_SESSION['security']['flags'])
                ]);
            } catch (Exception $e) {
                error_log("Failed to log session creation: " . $e->getMessage());
            }
        }
        
        self::logSessionEvent('login_session_created', 'User logged in successfully', [
            'user_id' => isset($userData['user_id']) ? $userData['user_id'] : null,
            'username' => isset($userData['user_name']) ? $userData['user_name'] : null
        ]);
        
        return true;
    }
    
    /**
     * Destroy session securely
     * 
     * @param string $reason Reason for session destruction
     * @param array $context Additional context
     */
    public static function destroySession($reason = 'manual_logout', $context = []) {
        $sessionId = session_id();
        $userId = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
        
        // Log session destruction
        self::logSessionEvent('session_destroyed', "Session destroyed: $reason", array_merge($context, [
            'reason' => $reason,
            'user_id' => $userId
        ]));
        
        // Update database record
        if (class_exists('DB') && $sessionId) {
            try {
                DB::update('session_security', [
                    'logout_time' => date('Y-m-d H:i:s'),
                    'logout_reason' => $reason,
                    'is_active' => false
                ], 'session_id = %s', $sessionId);
            } catch (Exception $e) {
                error_log("Failed to update session logout: " . $e->getMessage());
            }
        }
        
        // Clear session data
        $_SESSION = [];
        
        // Destroy session cookie
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
        
        // Destroy session
        session_destroy();
        
        // Redirect to login if this was a timeout
        if (in_array($reason, ['session_timeout', 'inactivity_timeout', 'invalid_session_data'])) {
            $redirectUrl = defined('BASE_URL') ? BASE_URL . 'login.php?msg=session_expired' : '/login.php?msg=session_expired';
            header('Location: ' . $redirectUrl);
            exit();
        }
    }
    
    /**
     * Regenerate session ID for security
     */
    private static function regenerateSessionId() {
        $oldSessionId = session_id();
        
        // Regenerate session ID
        session_regenerate_id(true);
        
        $newSessionId = session_id();
        $_SESSION['security']['last_regeneration'] = time();
        
        // Update database record if exists
        if (class_exists('DB') && $oldSessionId !== $newSessionId) {
            try {
                DB::update('session_security', [
                    'session_id' => $newSessionId
                ], 'session_id = %s', $oldSessionId);
            } catch (Exception $e) {
                error_log("Failed to update session ID in database: " . $e->getMessage());
            }
        }
        
        self::logSessionEvent('session_id_regenerated', 'Session ID regenerated for security');
    }
    
    /**
     * Validate session integrity
     */
    private static function validateSessionIntegrity() {
        // Check for session data tampering
        if (isset($_SESSION['security']['checksum'])) {
            $currentChecksum = self::calculateSessionChecksum();
            if ($_SESSION['security']['checksum'] !== $currentChecksum) {
                self::logSessionEvent('session_tampering_detected', 'Session data integrity check failed');
                $_SESSION['security']['flags'][] = 'tampering_detected';
            }
        }
        
        // Update checksum
        $_SESSION['security']['checksum'] = self::calculateSessionChecksum();
    }
    
    /**
     * Calculate session data checksum
     * 
     * @return string Checksum
     */
    private static function calculateSessionChecksum() {
        $sessionData = $_SESSION;
        unset($sessionData['security']['checksum']); // Exclude checksum itself
        unset($sessionData['security']['last_activity']); // Exclude frequently changing data
        
        return hash('sha256', serialize($sessionData) . session_id());
    }
    
    /**
     * Get active sessions for a user
     * 
     * @param int $userId User ID
     * @return array Active sessions
     */
    private static function getActiveSessionsForUser($userId) {
        if (!class_exists('DB')) {
            return [];
        }
        
        try {
            return DB::query("SELECT session_id, login_time, last_activity FROM session_security 
                            WHERE user_id = %s AND is_active = TRUE 
                            ORDER BY last_activity DESC", $userId);
        } catch (Exception $e) {
            error_log("Failed to get active sessions: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Terminate oldest session for a user
     * 
     * @param int $userId User ID
     */
    private static function terminateOldestSession($userId) {
        if (!class_exists('DB')) {
            return;
        }
        
        try {
            $oldestSession = DB::queryFirstRow("SELECT session_id FROM session_security 
                                              WHERE user_id = %s AND is_active = TRUE 
                                              ORDER BY last_activity ASC LIMIT 1", $userId);
            
            if ($oldestSession) {
                DB::update('session_security', [
                    'logout_time' => date('Y-m-d H:i:s'),
                    'logout_reason' => 'max_sessions_exceeded',
                    'is_active' => false
                ], 'session_id = %s', $oldestSession['session_id']);
                
                self::logSessionEvent('session_terminated', 'Oldest session terminated due to max sessions limit', [
                    'terminated_session' => $oldestSession['session_id'],
                    'user_id' => $userId
                ]);
            }
        } catch (Exception $e) {
            error_log("Failed to terminate oldest session: " . $e->getMessage());
        }
    }
    
    /**
     * Update session tracking in database
     */
    private static function updateSessionTracking() {
        if (!class_exists('DB')) {
            return;
        }
        
        $sessionId = session_id();
        
        try {
            DB::update('session_security', [
                'last_activity' => date('Y-m-d H:i:s'),
                'security_flags' => json_encode($_SESSION['security']['flags'] ?? [])
            ], 'session_id = %s', $sessionId);
        } catch (Exception $e) {
            // Session might not exist in database yet, which is OK
        }
    }
    
    /**
     * Get session statistics for monitoring
     * 
     * @return array Session statistics
     */
    public static function getSessionStatistics() {
        $stats = [
            'current_session_age' => 0,
            'time_since_last_activity' => 0,
            'regenerations' => 0,
            'security_flags' => []
        ];
        
        if (isset($_SESSION['security'])) {
            $now = time();
            $security = $_SESSION['security'];
            
            $stats['current_session_age'] = $now - $security['created'];
            $stats['time_since_last_activity'] = $now - $security['last_activity'];
            $stats['regenerations'] = isset($security['regeneration_count']) ? $security['regeneration_count'] : 0;
            $stats['security_flags'] = $security['flags'] ?? [];
        }
        
        return $stats;
    }
    
    /**
     * Log session-related security events
     * 
     * @param string $event Event type
     * @param string $description Event description
     * @param array $context Additional context
     */
    private static function logSessionEvent($event, $description, $context = []) {
        $logContext = array_merge($context, [
            'session_id' => session_id(),
            'user_id' => isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null,
            'ip_address' => SecurityUtils::getClientIP()
        ]);
        
        if (class_exists('SecurityUtils')) {
            SecurityUtils::logSecurityEvent($event, $description, $logContext);
        } else {
            error_log("Session Event: $event - $description - " . json_encode($logContext));
        }
    }
    
    /**
     * Clean up expired sessions (should be called periodically)
     */
    public static function cleanupExpiredSessions() {
        if (!class_exists('DB')) {
            return;
        }
        
        try {
            $expiredTime = date('Y-m-d H:i:s', time() - self::SESSION_TIMEOUT);
            
            $result = DB::update('session_security', [
                'logout_time' => date('Y-m-d H:i:s'),
                'logout_reason' => 'expired',
                'is_active' => false
            ], 'is_active = TRUE AND last_activity < %s', $expiredTime);
            
            if ($result > 0) {
                self::logSessionEvent('expired_sessions_cleaned', "Cleaned up $result expired sessions");
            }
        } catch (Exception $e) {
            error_log("Failed to cleanup expired sessions: " . $e->getMessage());
        }
    }
}

/**
 * Backward compatibility function
 */
function validateActiveSession() {
    return EnhancedSessionManager::validateActiveSession();
}

/**
 * Initialize enhanced session management
 */
function initializeSecureSession($options = []) {
    EnhancedSessionManager::initialize($options);
}

/**
 * Create secure login session
 */
function createSecureLoginSession($userData) {
    return EnhancedSessionManager::createLoginSession($userData);
}

/**
 * Destroy session securely
 */
function destroySecureSession($reason = 'manual_logout', $context = []) {
    EnhancedSessionManager::destroySession($reason, $context);
}