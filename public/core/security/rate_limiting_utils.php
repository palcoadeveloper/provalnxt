<?php
/**
 * Rate Limiting Utilities for ProVal HVAC Security
 * 
 * Provides comprehensive rate limiting functionality to protect against
 * brute force attacks and other malicious activities.
 * 
 * @version 1.0
 * @author ProVal Security Team
 */

if (!class_exists('InputValidator')) {
    require_once '../validation/input_validation_utils.php';
}
// Note: config.php is loaded by the main application

class RateLimiter {
    
    /**
     * Check if rate limiting is globally enabled
     * 
     * @return bool True if rate limiting is enabled
     */
    public static function isRateLimitingEnabled() {
        // Check database override first
        if (class_exists('DB')) {
            try {
                $dbSetting = DB::queryFirstRow("SELECT config_value FROM security_config WHERE config_key = 'rate_limiting_enabled'");
                if ($dbSetting) {
                    return filter_var($dbSetting['config_value'], FILTER_VALIDATE_BOOLEAN);
                }
            } catch (Exception $e) {
                error_log("Failed to fetch rate limiting enabled setting from database: " . $e->getMessage());
            }
        }
        
        // Fall back to configuration constant
        return defined('RATE_LIMITING_ENABLED') ? RATE_LIMITING_ENABLED : true;
    }
    
    /**
     * Get default rate limiting rules from configuration
     * 
     * @return array Default rate limiting rules with per-IP and system-wide limits
     */
    public static function getDefaultRules() {
        return [
            'login_attempts' => [
                'per_ip' => [
                    'max' => defined('RATE_LIMIT_LOGIN_MAX') ? RATE_LIMIT_LOGIN_MAX : 5,
                    'window' => defined('RATE_LIMIT_LOGIN_WINDOW') ? RATE_LIMIT_LOGIN_WINDOW : 300,
                    'lockout' => defined('RATE_LIMIT_LOGIN_LOCKOUT') ? RATE_LIMIT_LOGIN_LOCKOUT : 1800
                ],
                'system_wide' => [
                    'max' => defined('RATE_LIMIT_LOGIN_SYSTEM_MAX') ? RATE_LIMIT_LOGIN_SYSTEM_MAX : 1000,
                    'window' => defined('RATE_LIMIT_LOGIN_SYSTEM_WINDOW') ? RATE_LIMIT_LOGIN_SYSTEM_WINDOW : 300,
                    'lockout' => defined('RATE_LIMIT_LOGIN_SYSTEM_LOCKOUT') ? RATE_LIMIT_LOGIN_SYSTEM_LOCKOUT : 600
                ]
            ],
            'otp_generation' => [
                'per_ip' => [
                    'max' => defined('RATE_LIMIT_OTP_GENERATION_MAX') ? RATE_LIMIT_OTP_GENERATION_MAX : 3,
                    'window' => defined('RATE_LIMIT_OTP_GENERATION_WINDOW') ? RATE_LIMIT_OTP_GENERATION_WINDOW : 300,
                    'lockout' => defined('RATE_LIMIT_OTP_GENERATION_LOCKOUT') ? RATE_LIMIT_OTP_GENERATION_LOCKOUT : 900
                ],
                'system_wide' => [
                    'max' => defined('RATE_LIMIT_OTP_GENERATION_SYSTEM_MAX') ? RATE_LIMIT_OTP_GENERATION_SYSTEM_MAX : 200,
                    'window' => defined('RATE_LIMIT_OTP_GENERATION_SYSTEM_WINDOW') ? RATE_LIMIT_OTP_GENERATION_SYSTEM_WINDOW : 300,
                    'lockout' => defined('RATE_LIMIT_OTP_GENERATION_SYSTEM_LOCKOUT') ? RATE_LIMIT_OTP_GENERATION_SYSTEM_LOCKOUT : 600
                ]
            ],
            'otp_verification_attempts' => [
                'per_ip' => [
                    'max' => defined('RATE_LIMIT_OTP_VERIFICATION_MAX') ? RATE_LIMIT_OTP_VERIFICATION_MAX : 10,
                    'window' => defined('RATE_LIMIT_OTP_VERIFICATION_WINDOW') ? RATE_LIMIT_OTP_VERIFICATION_WINDOW : 300,
                    'lockout' => defined('RATE_LIMIT_OTP_VERIFICATION_LOCKOUT') ? RATE_LIMIT_OTP_VERIFICATION_LOCKOUT : 600
                ],
                'system_wide' => [
                    'max' => defined('RATE_LIMIT_OTP_VERIFICATION_SYSTEM_MAX') ? RATE_LIMIT_OTP_VERIFICATION_SYSTEM_MAX : 1000,
                    'window' => defined('RATE_LIMIT_OTP_VERIFICATION_SYSTEM_WINDOW') ? RATE_LIMIT_OTP_VERIFICATION_SYSTEM_WINDOW : 300,
                    'lockout' => defined('RATE_LIMIT_OTP_VERIFICATION_SYSTEM_LOCKOUT') ? RATE_LIMIT_OTP_VERIFICATION_SYSTEM_LOCKOUT : 600
                ]
            ],
            'otp_email_sending' => [
                'per_ip' => [
                    'max' => defined('RATE_LIMIT_OTP_EMAIL_MAX') ? RATE_LIMIT_OTP_EMAIL_MAX : 5,
                    'window' => defined('RATE_LIMIT_OTP_EMAIL_WINDOW') ? RATE_LIMIT_OTP_EMAIL_WINDOW : 300,
                    'lockout' => defined('RATE_LIMIT_OTP_EMAIL_LOCKOUT') ? RATE_LIMIT_OTP_EMAIL_LOCKOUT : 1800
                ],
                'system_wide' => [
                    'max' => defined('RATE_LIMIT_OTP_EMAIL_SYSTEM_MAX') ? RATE_LIMIT_OTP_EMAIL_SYSTEM_MAX : 500,
                    'window' => defined('RATE_LIMIT_OTP_EMAIL_SYSTEM_WINDOW') ? RATE_LIMIT_OTP_EMAIL_SYSTEM_WINDOW : 300,
                    'lockout' => defined('RATE_LIMIT_OTP_EMAIL_SYSTEM_LOCKOUT') ? RATE_LIMIT_OTP_EMAIL_SYSTEM_LOCKOUT : 900
                ]
            ],
            'password_reset' => [
                'per_ip' => [
                    'max' => defined('RATE_LIMIT_PASSWORD_RESET_MAX') ? RATE_LIMIT_PASSWORD_RESET_MAX : 3,
                    'window' => defined('RATE_LIMIT_PASSWORD_RESET_WINDOW') ? RATE_LIMIT_PASSWORD_RESET_WINDOW : 900,
                    'lockout' => defined('RATE_LIMIT_PASSWORD_RESET_LOCKOUT') ? RATE_LIMIT_PASSWORD_RESET_LOCKOUT : 3600
                ],
                'system_wide' => [
                    'max' => defined('RATE_LIMIT_PASSWORD_RESET_SYSTEM_MAX') ? RATE_LIMIT_PASSWORD_RESET_SYSTEM_MAX : 100,
                    'window' => defined('RATE_LIMIT_PASSWORD_RESET_SYSTEM_WINDOW') ? RATE_LIMIT_PASSWORD_RESET_SYSTEM_WINDOW : 900,
                    'lockout' => defined('RATE_LIMIT_PASSWORD_RESET_SYSTEM_LOCKOUT') ? RATE_LIMIT_PASSWORD_RESET_SYSTEM_LOCKOUT : 1800
                ]
            ],
            'api_requests' => [
                'per_ip' => [
                    'max' => defined('RATE_LIMIT_API_MAX') ? RATE_LIMIT_API_MAX : 100,
                    'window' => defined('RATE_LIMIT_API_WINDOW') ? RATE_LIMIT_API_WINDOW : 60,
                    'lockout' => defined('RATE_LIMIT_API_LOCKOUT') ? RATE_LIMIT_API_LOCKOUT : 300
                ],
                'system_wide' => [
                    'max' => defined('RATE_LIMIT_API_SYSTEM_MAX') ? RATE_LIMIT_API_SYSTEM_MAX : 10000,
                    'window' => defined('RATE_LIMIT_API_SYSTEM_WINDOW') ? RATE_LIMIT_API_SYSTEM_WINDOW : 60,
                    'lockout' => defined('RATE_LIMIT_API_SYSTEM_LOCKOUT') ? RATE_LIMIT_API_SYSTEM_LOCKOUT : 300
                ]
            ],
            'file_uploads' => [
                'per_ip' => [
                    'max' => defined('RATE_LIMIT_FILE_UPLOAD_MAX') ? RATE_LIMIT_FILE_UPLOAD_MAX : 10,
                    'window' => defined('RATE_LIMIT_FILE_UPLOAD_WINDOW') ? RATE_LIMIT_FILE_UPLOAD_WINDOW : 3600,
                    'lockout' => defined('RATE_LIMIT_FILE_UPLOAD_LOCKOUT') ? RATE_LIMIT_FILE_UPLOAD_LOCKOUT : 1800
                ],
                'system_wide' => [
                    'max' => defined('RATE_LIMIT_FILE_UPLOAD_SYSTEM_MAX') ? RATE_LIMIT_FILE_UPLOAD_SYSTEM_MAX : 500,
                    'window' => defined('RATE_LIMIT_FILE_UPLOAD_SYSTEM_WINDOW') ? RATE_LIMIT_FILE_UPLOAD_SYSTEM_WINDOW : 3600,
                    'lockout' => defined('RATE_LIMIT_FILE_UPLOAD_SYSTEM_LOCKOUT') ? RATE_LIMIT_FILE_UPLOAD_SYSTEM_LOCKOUT : 1800
                ]
            ],
            'form_submissions' => [
                'per_ip' => [
                    'max' => defined('RATE_LIMIT_FORM_SUBMISSION_MAX') ? RATE_LIMIT_FORM_SUBMISSION_MAX : 20,
                    'window' => defined('RATE_LIMIT_FORM_SUBMISSION_WINDOW') ? RATE_LIMIT_FORM_SUBMISSION_WINDOW : 300,
                    'lockout' => defined('RATE_LIMIT_FORM_SUBMISSION_LOCKOUT') ? RATE_LIMIT_FORM_SUBMISSION_LOCKOUT : 600
                ],
                'system_wide' => [
                    'max' => defined('RATE_LIMIT_FORM_SUBMISSION_SYSTEM_MAX') ? RATE_LIMIT_FORM_SUBMISSION_SYSTEM_MAX : 2000,
                    'window' => defined('RATE_LIMIT_FORM_SUBMISSION_SYSTEM_WINDOW') ? RATE_LIMIT_FORM_SUBMISSION_SYSTEM_WINDOW : 300,
                    'lockout' => defined('RATE_LIMIT_FORM_SUBMISSION_SYSTEM_LOCKOUT') ? RATE_LIMIT_FORM_SUBMISSION_SYSTEM_LOCKOUT : 600
                ]
            ]
        ];
    }
    
    /**
     * Check if an action is rate limited (dual-layer: per-IP and system-wide)
     * 
     * @param string $action Action being performed
     * @param string $identifier Identifier (IP address, user ID, etc.)
     * @param array $customRules Custom rate limiting rules
     * @return array Rate limit check result
     */
    public static function checkRateLimit($action, $identifier = null, $customRules = null) {
        // Check if rate limiting is globally enabled
        if (!self::isRateLimitingEnabled()) {
            return [
                'allowed' => true,
                'remaining' => 999,
                'reset_time' => time() + 3600,
                'lockout_expires' => null,
                'message' => 'Rate limiting disabled',
                'limit_type' => 'disabled'
            ];
        }
        
        $identifier = $identifier ?: self::getDefaultIdentifier();
        $rules = $customRules ?: self::getRulesForAction($action);
        
        // Check per-IP rate limiting first
        $perIpResult = self::checkPerIpRateLimit($action, $identifier, $rules['per_ip']);
        if (!$perIpResult['allowed']) {
            $perIpResult['limit_type'] = 'per_ip';
            return $perIpResult;
        }
        
        // Check system-wide rate limiting
        $systemWideResult = self::checkSystemWideRateLimit($action, $rules['system_wide']);
        if (!$systemWideResult['allowed']) {
            $systemWideResult['limit_type'] = 'system_wide';
            return $systemWideResult;
        }
        
        // Both checks passed
        return [
            'allowed' => true,
            'remaining' => min($perIpResult['remaining'], $systemWideResult['remaining']),
            'reset_time' => max($perIpResult['reset_time'], $systemWideResult['reset_time']),
            'lockout_expires' => null,
            'message' => 'Request allowed',
            'limit_type' => 'none',
            'per_ip_remaining' => $perIpResult['remaining'],
            'system_wide_remaining' => $systemWideResult['remaining']
        ];
    }
    
    /**
     * Check per-IP rate limiting for an action
     * 
     * @param string $action Action being performed
     * @param string $identifier IP address identifier
     * @param array $rules Per-IP rate limiting rules
     * @return array Rate limit check result
     */
    private static function checkPerIpRateLimit($action, $identifier, $rules) {
        $result = [
            'allowed' => true,
            'remaining' => $rules['max'],
            'reset_time' => time() + $rules['window'],
            'lockout_expires' => null,
            'message' => 'Request allowed'
        ];
        
        // Check if currently in lockout period
        $lockoutKey = "lockout_{$action}_{$identifier}";
        $lockoutData = self::getStoredData($lockoutKey);
        
        if ($lockoutData && time() < $lockoutData['expires']) {
            $result['allowed'] = false;
            $result['lockout_expires'] = $lockoutData['expires'];
            $result['message'] = 'IP temporarily locked due to too many attempts';
            
            self::logRateLimitEvent($action, $identifier, 'per_ip_lockout_active', [
                'expires' => date('Y-m-d H:i:s', $lockoutData['expires'])
            ]);
            
            return $result;
        }
        
        // Get current attempt count
        $attemptKey = "attempts_{$action}_{$identifier}";
        $attemptData = self::getStoredData($attemptKey);
        $now = time();
        
        // Initialize or clean old attempts
        if (!$attemptData || $now > $attemptData['window_end']) {
            $attemptData = [
                'count' => 0,
                'window_start' => $now,
                'window_end' => $now + $rules['window'],
                'attempts' => []
            ];
        }
        
        // Clean attempts outside current window
        $attemptData['attempts'] = array_filter($attemptData['attempts'], function($timestamp) use ($now, $rules) {
            return ($now - $timestamp) < $rules['window'];
        });
        
        $attemptData['count'] = count($attemptData['attempts']);
        
        // Check if limit exceeded
        if ($attemptData['count'] >= $rules['max']) {
            // Trigger lockout
            $lockoutExpires = $now + $rules['lockout'];
            self::storeData($lockoutKey, [
                'expires' => $lockoutExpires,
                'triggered_at' => $now,
                'attempt_count' => $attemptData['count']
            ], $rules['lockout']);
            
            $result['allowed'] = false;
            $result['lockout_expires'] = $lockoutExpires;
            $result['message'] = 'Too many attempts from this IP. Temporarily locked.';
            
            self::logRateLimitEvent($action, $identifier, 'per_ip_rate_limit_exceeded', [
                'attempts' => $attemptData['count'],
                'max_allowed' => $rules['max'],
                'lockout_duration' => $rules['lockout']
            ]);
            
            return $result;
        }
        
        // Record this attempt
        $attemptData['attempts'][] = $now;
        $attemptData['count'] = count($attemptData['attempts']);
        
        // Store updated attempt data
        self::storeData($attemptKey, $attemptData, $rules['window']);
        
        $result['remaining'] = $rules['max'] - $attemptData['count'];
        $result['reset_time'] = $attemptData['window_start'] + $rules['window'];
        
        return $result;
    }
    
    /**
     * Check system-wide rate limiting for an action
     * 
     * @param string $action Action being performed
     * @param array $rules System-wide rate limiting rules
     * @return array Rate limit check result
     */
    private static function checkSystemWideRateLimit($action, $rules) {
        $result = [
            'allowed' => true,
            'remaining' => $rules['max'],
            'reset_time' => time() + $rules['window'],
            'lockout_expires' => null,
            'message' => 'Request allowed'
        ];
        
        // Check if system is in lockout period
        $lockoutKey = "system_lockout_{$action}";
        $lockoutData = self::getStoredData($lockoutKey);
        
        if ($lockoutData && time() < $lockoutData['expires']) {
            $result['allowed'] = false;
            $result['lockout_expires'] = $lockoutData['expires'];
            $result['message'] = 'System temporarily locked due to too many requests';
            
            self::logRateLimitEvent($action, 'system', 'system_wide_lockout_active', [
                'expires' => date('Y-m-d H:i:s', $lockoutData['expires'])
            ]);
            
            return $result;
        }
        
        // Get current system-wide attempt count
        $attemptKey = "system_attempts_{$action}";
        $attemptData = self::getStoredData($attemptKey);
        $now = time();
        
        // Initialize or clean old attempts
        if (!$attemptData || $now > $attemptData['window_end']) {
            $attemptData = [
                'count' => 0,
                'window_start' => $now,
                'window_end' => $now + $rules['window'],
                'attempts' => []
            ];
        }
        
        // Clean attempts outside current window
        $attemptData['attempts'] = array_filter($attemptData['attempts'], function($timestamp) use ($now, $rules) {
            return ($now - $timestamp) < $rules['window'];
        });
        
        $attemptData['count'] = count($attemptData['attempts']);
        
        // Check if limit exceeded
        if ($attemptData['count'] >= $rules['max']) {
            // Trigger system-wide lockout
            $lockoutExpires = $now + $rules['lockout'];
            self::storeData($lockoutKey, [
                'expires' => $lockoutExpires,
                'triggered_at' => $now,
                'attempt_count' => $attemptData['count']
            ], $rules['lockout']);
            
            $result['allowed'] = false;
            $result['lockout_expires'] = $lockoutExpires;
            $result['message'] = 'System overloaded. Too many requests system-wide.';
            
            self::logRateLimitEvent($action, 'system', 'system_wide_rate_limit_exceeded', [
                'attempts' => $attemptData['count'],
                'max_allowed' => $rules['max'],
                'lockout_duration' => $rules['lockout']
            ]);
            
            return $result;
        }
        
        // Record this attempt
        $attemptData['attempts'][] = $now;
        $attemptData['count'] = count($attemptData['attempts']);
        
        // Store updated attempt data
        self::storeData($attemptKey, $attemptData, $rules['window']);
        
        $result['remaining'] = $rules['max'] - $attemptData['count'];
        $result['reset_time'] = $attemptData['window_start'] + $rules['window'];
        
        return $result;
    }
    
    /**
     * Record a successful action (resets per-IP rate limiting only)
     * Note: System-wide limits are not reset on success as they protect against distributed attacks
     * 
     * @param string $action Action that succeeded
     * @param string $identifier Identifier
     */
    public static function recordSuccess($action, $identifier = null) {
        // Skip if rate limiting is disabled
        if (!self::isRateLimitingEnabled()) {
            return;
        }
        
        $identifier = $identifier ?: self::getDefaultIdentifier();
        
        // Clear per-IP attempt counters (but not system-wide counters)
        $attemptKey = "attempts_{$action}_{$identifier}";
        $lockoutKey = "lockout_{$action}_{$identifier}";
        
        self::clearStoredData($attemptKey);
        self::clearStoredData($lockoutKey);
        
        // Still count this request in system-wide tracking
        self::recordSystemWideAttempt($action);
        
        self::logRateLimitEvent($action, $identifier, 'per_ip_success_reset');
    }
    
    /**
     * Record a failed action (updates both per-IP and system-wide counters)
     * 
     * @param string $action Action that failed
     * @param string $identifier Identifier
     * @param string $reason Failure reason
     */
    public static function recordFailure($action, $identifier = null, $reason = 'generic_failure') {
        // Skip if rate limiting is disabled
        if (!self::isRateLimitingEnabled()) {
            return;
        }
        
        $identifier = $identifier ?: self::getDefaultIdentifier();
        
        // Check rate limit to update counters (this already records attempts)
        $result = self::checkRateLimit($action, $identifier);
        
        self::logRateLimitEvent($action, $identifier, 'failure_recorded', [
            'reason' => $reason,
            'remaining_attempts' => $result['remaining'],
            'limit_type' => $result['limit_type'] ?? 'unknown'
        ]);
    }
    
    /**
     * Record a system-wide attempt (used for successful actions)
     * 
     * @param string $action Action being recorded
     */
    private static function recordSystemWideAttempt($action) {
        $rules = self::getRulesForAction($action);
        $systemRules = $rules['system_wide'];
        
        // Get current system-wide attempt count
        $attemptKey = "system_attempts_{$action}";
        $attemptData = self::getStoredData($attemptKey);
        $now = time();
        
        // Initialize or clean old attempts
        if (!$attemptData || $now > $attemptData['window_end']) {
            $attemptData = [
                'count' => 0,
                'window_start' => $now,
                'window_end' => $now + $systemRules['window'],
                'attempts' => []
            ];
        }
        
        // Clean attempts outside current window
        $attemptData['attempts'] = array_filter($attemptData['attempts'], function($timestamp) use ($now, $systemRules) {
            return ($now - $timestamp) < $systemRules['window'];
        });
        
        // Record this attempt
        $attemptData['attempts'][] = $now;
        $attemptData['count'] = count($attemptData['attempts']);
        
        // Store updated attempt data
        self::storeData($attemptKey, $attemptData, $systemRules['window']);
    }
    
    /**
     * Get rate limiting rules for a specific action
     * 
     * @param string $action Action name
     * @return array Rate limiting rules with per_ip and system_wide sections
     */
    private static function getRulesForAction($action) {
        // Check if rules are defined in database
        if (class_exists('DB')) {
            try {
                $dbRules = DB::queryFirstRow("SELECT config_value FROM security_config WHERE config_key = %s", "rate_limit_{$action}");
                if ($dbRules) {
                    $parsedRules = json_decode($dbRules['config_value'], true);
                    if ($parsedRules && isset($parsedRules['per_ip']) && isset($parsedRules['system_wide'])) {
                        return $parsedRules;
                    }
                }
            } catch (Exception $e) {
                error_log("Failed to fetch rate limit rules from database: " . $e->getMessage());
            }
        }
        
        // Fall back to default rules from configuration
        $defaultRules = self::getDefaultRules();
        return isset($defaultRules[$action]) ? $defaultRules[$action] : $defaultRules['api_requests'];
    }
    
    /**
     * Get default identifier for rate limiting (usually IP address)
     * 
     * @return string Default identifier
     */
    private static function getDefaultIdentifier() {
        return SecurityUtils::getClientIP();
    }
    
    /**
     * Store data with expiration
     * 
     * @param string $key Storage key
     * @param mixed $data Data to store
     * @param int $ttl Time to live in seconds
     */
    private static function storeData($key, $data, $ttl) {
        // Use session for now (could be improved with Redis/Memcache)
        if (!isset($_SESSION['rate_limit_data'])) {
            $_SESSION['rate_limit_data'] = [];
        }
        
        $_SESSION['rate_limit_data'][$key] = [
            'data' => $data,
            'expires' => time() + $ttl
        ];
        
        // Clean expired entries occasionally
        if (rand(1, 100) <= 5) { // 5% chance
            self::cleanExpiredData();
        }
    }
    
    /**
     * Get stored data
     * 
     * @param string $key Storage key
     * @return mixed Stored data or null if not found/expired
     */
    private static function getStoredData($key) {
        if (!isset($_SESSION['rate_limit_data'][$key])) {
            return null;
        }
        
        $stored = $_SESSION['rate_limit_data'][$key];
        
        if (time() > $stored['expires']) {
            unset($_SESSION['rate_limit_data'][$key]);
            return null;
        }
        
        return $stored['data'];
    }
    
    /**
     * Clear stored data
     * 
     * @param string $key Storage key
     */
    private static function clearStoredData($key) {
        if (isset($_SESSION['rate_limit_data'][$key])) {
            unset($_SESSION['rate_limit_data'][$key]);
        }
    }
    
    /**
     * Clean expired data from session
     */
    private static function cleanExpiredData() {
        if (!isset($_SESSION['rate_limit_data'])) {
            return;
        }
        
        $now = time();
        foreach ($_SESSION['rate_limit_data'] as $key => $stored) {
            if ($now > $stored['expires']) {
                unset($_SESSION['rate_limit_data'][$key]);
            }
        }
    }
    
    /**
     * Log rate limiting event
     * 
     * @param string $action Action being rate limited
     * @param string $identifier Identifier
     * @param string $event Event type
     * @param array $context Additional context
     */
    private static function logRateLimitEvent($action, $identifier, $event, $context = []) {
        $logContext = array_merge($context, [
            'action' => $action,
            'identifier' => $identifier,
            'user_agent' => isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : ''
        ]);
        
        if (class_exists('SecurityUtils')) {
            SecurityUtils::logSecurityEvent("rate_limit_{$event}", "Rate limiting event for action: {$action}", $logContext);
        } else {
            error_log("Rate Limit Event: {$event} - Action: {$action} - Identifier: {$identifier} - " . json_encode($logContext));
        }
    }
    
    /**
     * Get rate limiting statistics for monitoring
     * 
     * @return array Statistics
     */
    public static function getStatistics() {
        $stats = [
            'active_per_ip_lockouts' => 0,
            'active_system_wide_lockouts' => 0,
            'active_per_ip_rate_limits' => 0,
            'active_system_wide_rate_limits' => 0,
            'total_stored_data' => 0
        ];
        
        if (!isset($_SESSION['rate_limit_data'])) {
            return $stats;
        }
        
        $now = time();
        foreach ($_SESSION['rate_limit_data'] as $key => $stored) {
            if ($now <= $stored['expires']) {
                $stats['total_stored_data']++;
                
                if (strpos($key, 'system_lockout_') === 0) {
                    $stats['active_system_wide_lockouts']++;
                } elseif (strpos($key, 'lockout_') === 0) {
                    $stats['active_per_ip_lockouts']++;
                } elseif (strpos($key, 'system_attempts_') === 0) {
                    $stats['active_system_wide_rate_limits']++;
                } elseif (strpos($key, 'attempts_') === 0) {
                    $stats['active_per_ip_rate_limits']++;
                }
            }
        }
        
        return $stats;
    }
    
    /**
     * Clear all rate limiting data (admin function)
     */
    public static function clearAllData() {
        $_SESSION['rate_limit_data'] = [];
        
        if (class_exists('SecurityUtils')) {
            SecurityUtils::logSecurityEvent('rate_limit_data_cleared', 'All rate limiting data cleared by administrator');
        }
    }
    
    /**
     * Check if IP is whitelisted
     * 
     * @param string $ip IP address to check
     * @return bool True if whitelisted
     */
    private static function isWhitelisted($ip) {
        // Check for localhost/internal IPs
        $whitelistedRanges = [
            '127.0.0.0/8',
            '10.0.0.0/8', 
            '172.16.0.0/12',
            '192.168.0.0/16'
        ];
        
        foreach ($whitelistedRanges as $range) {
            if (self::ipInRange($ip, $range)) {
                return true;
            }
        }
        
        // Check database for custom whitelist
        if (class_exists('DB')) {
            try {
                $whitelisted = DB::queryFirstField("SELECT COUNT(*) FROM security_config WHERE config_key = 'whitelist_ip' AND config_value = %s", $ip);
                if ($whitelisted > 0) {
                    return true;
                }
            } catch (Exception $e) {
                error_log("Failed to check IP whitelist: " . $e->getMessage());
            }
        }
        
        return false;
    }
    
    /**
     * Check if IP is in a CIDR range
     * 
     * @param string $ip IP address
     * @param string $range CIDR range
     * @return bool True if in range
     */
    private static function ipInRange($ip, $range) {
        list($subnet, $bits) = explode('/', $range);
        $ip = ip2long($ip);
        $subnet = ip2long($subnet);
        $mask = -1 << (32 - $bits);
        $subnet &= $mask; // In case the supplied subnet wasn't correctly aligned
        return ($ip & $mask) == $subnet;
    }
    
    /**
     * Get current rate limiting configuration for all actions
     * 
     * @return array Current configuration with source information
     */
    public static function getCurrentConfiguration() {
        $config = [];
        $defaultRules = self::getDefaultRules();
        
        foreach ($defaultRules as $action => $rules) {
            $config[$action] = [
                'current_rules' => self::getRulesForAction($action),
                'default_rules' => $rules,
                'source' => 'config', // Will be updated if from database
                'description' => self::getActionDescription($action)
            ];
            
            // Check if rules are overridden in database
            if (class_exists('DB')) {
                try {
                    $dbRules = DB::queryFirstRow("SELECT config_value FROM security_config WHERE config_key = %s", "rate_limit_{$action}");
                    if ($dbRules) {
                        $config[$action]['source'] = 'database';
                    }
                } catch (Exception $e) {
                    // Database check failed, stick with config
                }
            }
        }
        
        return $config;
    }
    
    /**
     * Get human-readable description for rate limiting actions
     * 
     * @param string $action Action name
     * @return string Description
     */
    private static function getActionDescription($action) {
        $descriptions = [
            'login_attempts' => 'User login attempts (authentication)',
            'password_reset' => 'Password reset requests',
            'api_requests' => 'General API requests',
            'file_uploads' => 'File upload operations',
            'form_submissions' => 'Form submission operations'
        ];
        
        return isset($descriptions[$action]) ? $descriptions[$action] : 'Unknown action';
    }
    
    /**
     * Update rate limiting rules for a specific action in database
     * 
     * @param string $action Action name
     * @param array $rules New rules array with max, window, lockout
     * @return bool True if successful
     */
    public static function updateActionRules($action, $rules) {
        if (!class_exists('DB')) {
            return false;
        }
        
        // Validate rules structure
        if (!isset($rules['max']) || !isset($rules['window']) || !isset($rules['lockout'])) {
            return false;
        }
        
        // Validate rule values
        if (!is_int($rules['max']) || $rules['max'] < 1 || $rules['max'] > 1000) {
            return false;
        }
        if (!is_int($rules['window']) || $rules['window'] < 60 || $rules['window'] > 86400) {
            return false;
        }
        if (!is_int($rules['lockout']) || $rules['lockout'] < 60 || $rules['lockout'] > 86400) {
            return false;
        }
        
        try {
            // Check if rule exists
            $existing = DB::queryFirstRow("SELECT config_key FROM security_config WHERE config_key = %s", "rate_limit_{$action}");
            
            if ($existing) {
                // Update existing rule
                DB::update('security_config', [
                    'config_value' => json_encode($rules),
                    'updated_at' => date('Y-m-d H:i:s')
                ], [
                    'config_key' => "rate_limit_{$action}"
                ]);
            } else {
                // Insert new rule
                DB::insert('security_config', [
                    'config_key' => "rate_limit_{$action}",
                    'config_value' => json_encode($rules),
                    'description' => "Rate limiting rules for {$action}",
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s')
                ]);
            }
            
            // Log the configuration change
            if (class_exists('SecurityUtils')) {
                SecurityUtils::logSecurityEvent('rate_limit_config_updated', "Rate limit configuration updated for action: {$action}", [
                    'action' => $action,
                    'new_rules' => $rules,
                    'user_id' => isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'system'
                ]);
            }
            
            return true;
        } catch (Exception $e) {
            error_log("Failed to update rate limit rules: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Reset rate limiting rules for an action to configuration defaults
     * 
     * @param string $action Action name
     * @return bool True if successful
     */
    public static function resetToDefaults($action) {
        if (!class_exists('DB')) {
            return false;
        }
        
        try {
            DB::query("DELETE FROM security_config WHERE config_key = %s", "rate_limit_{$action}");
            
            // Log the reset
            if (class_exists('SecurityUtils')) {
                SecurityUtils::logSecurityEvent('rate_limit_config_reset', "Rate limit configuration reset to defaults for action: {$action}", [
                    'action' => $action,
                    'user_id' => isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'system'
                ]);
            }
            
            return true;
        } catch (Exception $e) {
            error_log("Failed to reset rate limit rules: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Enable rate limiting globally at runtime
     * 
     * @return bool True if successful
     */
    public static function enableRateLimiting() {
        if (!class_exists('DB')) {
            return false;
        }
        
        try {
            // Check if setting exists
            $existing = DB::queryFirstRow("SELECT config_key FROM security_config WHERE config_key = 'rate_limiting_enabled'");
            
            if ($existing) {
                // Update existing setting
                DB::update('security_config', [
                    'config_value' => 'true',
                    'updated_at' => date('Y-m-d H:i:s')
                ], [
                    'config_key' => 'rate_limiting_enabled'
                ]);
            } else {
                // Insert new setting
                DB::insert('security_config', [
                    'config_key' => 'rate_limiting_enabled',
                    'config_value' => 'true',
                    'description' => 'Global rate limiting enable/disable flag',
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s')
                ]);
            }
            
            // Log the change
            if (class_exists('SecurityUtils')) {
                SecurityUtils::logSecurityEvent('rate_limiting_enabled', 'Rate limiting enabled globally', [
                    'user_id' => isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'system'
                ]);
            }
            
            return true;
        } catch (Exception $e) {
            error_log("Failed to enable rate limiting: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Disable rate limiting globally at runtime
     * 
     * @return bool True if successful
     */
    public static function disableRateLimiting() {
        if (!class_exists('DB')) {
            return false;
        }
        
        try {
            // Check if setting exists
            $existing = DB::queryFirstRow("SELECT config_key FROM security_config WHERE config_key = 'rate_limiting_enabled'");
            
            if ($existing) {
                // Update existing setting
                DB::update('security_config', [
                    'config_value' => 'false',
                    'updated_at' => date('Y-m-d H:i:s')
                ], [
                    'config_key' => 'rate_limiting_enabled'
                ]);
            } else {
                // Insert new setting
                DB::insert('security_config', [
                    'config_key' => 'rate_limiting_enabled',
                    'config_value' => 'false',
                    'description' => 'Global rate limiting enable/disable flag',
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s')
                ]);
            }
            
            // Log the change
            if (class_exists('SecurityUtils')) {
                SecurityUtils::logSecurityEvent('rate_limiting_disabled', 'Rate limiting disabled globally', [
                    'user_id' => isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'system'
                ]);
            }
            
            return true;
        } catch (Exception $e) {
            error_log("Failed to disable rate limiting: " . $e->getMessage());
            return false;
        }
    }
}

/**
 * Simple rate limiting middleware function
 * 
 * @param string $action Action being performed
 * @param string $identifier Optional custom identifier
 * @return bool True if allowed, false if rate limited
 */
function checkRateLimit($action, $identifier = null) {
    // Skip if rate limiting is disabled globally
    if (!RateLimiter::isRateLimitingEnabled()) {
        return true;
    }
    
    $result = RateLimiter::checkRateLimit($action, $identifier);
    
    if (!$result['allowed']) {
        // Set appropriate HTTP headers
        http_response_code(429); // Too Many Requests
        header('Retry-After: ' . ($result['lockout_expires'] - time()));
        
        // Return JSON for AJAX requests
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
            strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
            header('Content-Type: application/json');
            echo json_encode([
                'status' => 'error',
                'message' => 'rate_limit_exceeded',
                'retry_after' => $result['lockout_expires'] - time(),
                'details' => $result['message']
            ]);
            exit();
        }
        
        return false;
    }
    
    return true;
}

/**
 * Record successful login/action
 * 
 * @param string $action Action that succeeded
 * @param string $identifier Optional custom identifier
 */
function recordSuccessfulAction($action, $identifier = null) {
    RateLimiter::recordSuccess($action, $identifier);
}

/**
 * Record failed login/action
 * 
 * @param string $action Action that failed
 * @param string $identifier Optional custom identifier
 * @param string $reason Failure reason
 */
function recordFailedAction($action, $identifier = null, $reason = 'generic_failure') {
    RateLimiter::recordFailure($action, $identifier, $reason);
}