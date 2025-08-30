<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.class.php';
require_once __DIR__ . '/rate_limiting_utils.php';
require_once __DIR__ . '/auth_utils.php';

class TwoFactorAuth {
    
    /**
     * Check if 2FA is enabled for a given unit
     * @param int $unitId The unit ID to check
     * @return array Unit's 2FA configuration or false if not found
     */
    public static function getUnitTwoFactorConfig($unitId) {
        try {
            $config = DB::queryFirstRow("
                SELECT two_factor_enabled, otp_validity_minutes, otp_digits, otp_resend_delay_seconds 
                FROM units 
                WHERE unit_id = %i", 
                $unitId
            );
            
            if (!$config) {
                return false;
            }
            
            // Ensure default values if columns don't exist yet
            $config['two_factor_enabled'] = $config['two_factor_enabled'] ?? 'No';
            $config['otp_validity_minutes'] = (int)($config['otp_validity_minutes'] ?? 5);
            $config['otp_digits'] = (int)($config['otp_digits'] ?? 6);
            $config['otp_resend_delay_seconds'] = (int)($config['otp_resend_delay_seconds'] ?? 60);
            
            // Validate configuration values
            $config['otp_validity_minutes'] = max(1, min(15, $config['otp_validity_minutes']));
            $config['otp_digits'] = max(4, min(8, $config['otp_digits']));
            $config['otp_resend_delay_seconds'] = max(30, min(300, $config['otp_resend_delay_seconds']));
            
            return $config;
        } catch (Exception $e) {
            error_log("Error fetching 2FA config for unit $unitId: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Generate a secure numeric OTP
     * @param int $digits Number of digits (4-8)
     * @return string The generated OTP
     */
    public static function generateOTP($digits = 6) {
        $digits = max(4, min(8, $digits));
        $otp = '';
        
        for ($i = 0; $i < $digits; $i++) {
            $otp .= random_int(0, 9);
        }
        
        return $otp;
    }
    
    /**
     * Create an OTP session for a user
     * @param int $userId User ID
     * @param int $unitId Unit ID
     * @param string $employeeId Employee ID
     * @param string $ipAddress User's IP address
     * @param string $userAgent User's browser agent
     * @return array|false OTP session data or false on failure
     */
    public static function createOTPSession($userId, $unitId, $employeeId, $ipAddress, $userAgent = '') {
        try {
            // Check rate limiting for OTP generation
            $rateLimitKey = 'otp_generation_' . $ipAddress . '_' . $employeeId;
            $rateLimitResult = RateLimiter::checkRateLimit($rateLimitKey, 3, 300); // 3 attempts per 5 minutes
            
            if (!$rateLimitResult['allowed']) {
                logSecurityEvent($userId, 'otp_generation_rate_limited', $userId, $unitId);
                return false;
            }
            
            // Get unit's 2FA configuration
            $config = self::getUnitTwoFactorConfig($unitId);
            if (!$config || $config['two_factor_enabled'] !== 'Yes') {
                return false;
            }
            
            // Clean up any existing sessions for this user
            self::cleanupUserOTPSessions($userId);
            
            // Generate OTP and calculate expiry
            $otp = self::generateOTP($config['otp_digits']);
            $expiryTime = date('Y-m-d H:i:s', time() + ($config['otp_validity_minutes'] * 60));
            $sessionToken = bin2hex(random_bytes(32));
            
            // Insert new OTP session
            $otpSessionId = DB::insert('user_otp_sessions', [
                'user_id' => $userId,
                'unit_id' => $unitId,
                'employee_id' => $employeeId,
                'otp_code' => $otp,
                'expires_at' => $expiryTime,
                'ip_address' => $ipAddress,
                'user_agent' => $userAgent,
                'session_token' => $sessionToken
            ]);
            
            if (!$otpSessionId) {
                error_log("Failed to create OTP session for user $userId");
                return false;
            }
            
            // Log security event
            logSecurityEvent($userId, 'otp_session_created', $userId, $unitId);
            
            return [
                'otp_session_id' => $otpSessionId,
                'otp_code' => $otp,
                'expires_at' => $expiryTime,
                'session_token' => $sessionToken,
                'validity_minutes' => $config['otp_validity_minutes']
            ];
            
        } catch (Exception $e) {
            error_log("Error creating OTP session: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Verify an OTP code
     * @param string $otpCode The OTP code to verify
     * @param string $sessionToken The session token
     * @param string $ipAddress User's IP address
     * @return array|false Verification result or false on failure
     */
    public static function verifyOTP($otpCode, $sessionToken, $ipAddress) {
        try {
            // Find the OTP session
            $otpSession = DB::queryFirstRow("
                SELECT otp_session_id, user_id, unit_id, employee_id, otp_code, 
                       expires_at, is_used, attempts_count 
                FROM user_otp_sessions 
                WHERE session_token = %s AND ip_address = %s", 
                $sessionToken, $ipAddress
            );
            
            if (!$otpSession) {
                return ['success' => false, 'error' => 'Invalid session'];
            }
            
            // Check if OTP is already used
            if ($otpSession['is_used'] === 'Yes') {
                logSecurityEvent($otpSession['user_id'], 'otp_reuse_attempt', $otpSession['user_id'], $otpSession['unit_id']);
                return ['success' => false, 'error' => 'OTP already used'];
            }
            
            // Check if OTP is expired
            if (strtotime($otpSession['expires_at']) < time()) {
                self::markOTPAsUsed($otpSession['otp_session_id']);
                logSecurityEvent($otpSession['user_id'], 'otp_expired', $otpSession['user_id'], $otpSession['unit_id']);
                return ['success' => false, 'error' => 'OTP expired'];
            }
            
            // Increment attempt count
            DB::update('user_otp_sessions', 
                ['attempts_count' => $otpSession['attempts_count'] + 1],
                'otp_session_id = %i', $otpSession['otp_session_id']
            );
            
            // Check for too many attempts
            if ($otpSession['attempts_count'] >= 4) { // 5 total attempts allowed
                self::markOTPAsUsed($otpSession['otp_session_id']);
                logSecurityEvent($otpSession['user_id'], 'otp_max_attempts_exceeded', $otpSession['user_id'], $otpSession['unit_id']);
                return ['success' => false, 'error' => 'Maximum attempts exceeded'];
            }
            
            // Verify OTP code
            if ($otpCode !== $otpSession['otp_code']) {
                logSecurityEvent($otpSession['user_id'], 'otp_verification_failed', $otpSession['user_id'], $otpSession['unit_id']);
                return [
                    'success' => false, 
                    'error' => 'Invalid OTP',
                    'attempts_remaining' => 4 - $otpSession['attempts_count']
                ];
            }
            
            // OTP verification successful
            self::markOTPAsUsed($otpSession['otp_session_id']);
            logSecurityEvent($otpSession['user_id'], 'otp_verification_successful', $otpSession['user_id'], $otpSession['unit_id']);
            
            return [
                'success' => true,
                'user_id' => $otpSession['user_id'],
                'unit_id' => $otpSession['unit_id'],
                'employee_id' => $otpSession['employee_id']
            ];
            
        } catch (Exception $e) {
            error_log("Error verifying OTP: " . $e->getMessage());
            return ['success' => false, 'error' => 'Verification failed'];
        }
    }
    
    /**
     * Check if user can request OTP resend
     * @param string $sessionToken The session token
     * @param string $ipAddress User's IP address
     * @return array Resend eligibility information
     */
    public static function canResendOTP($sessionToken, $ipAddress) {
        try {
            $config = DB::queryFirstRow("
                SELECT u.otp_resend_delay_seconds, s.created_at 
                FROM user_otp_sessions s 
                JOIN units u ON s.unit_id = u.unit_id 
                WHERE s.session_token = %s AND s.ip_address = %s AND s.is_used = 'No'", 
                $sessionToken, $ipAddress
            );
            
            if (!$config) {
                return ['can_resend' => false, 'wait_time' => 0];
            }
            
            $delaySeconds = (int)($config['otp_resend_delay_seconds'] ?? 60);
            $timeSinceCreation = time() - strtotime($config['created_at']);
            
            if ($timeSinceCreation < $delaySeconds) {
                return [
                    'can_resend' => false,
                    'wait_time' => $delaySeconds - $timeSinceCreation
                ];
            }
            
            return ['can_resend' => true, 'wait_time' => 0];
            
        } catch (Exception $e) {
            error_log("Error checking OTP resend eligibility: " . $e->getMessage());
            return ['can_resend' => false, 'wait_time' => 60];
        }
    }
    
    /**
     * Mark OTP as used
     * @param int $otpSessionId OTP session ID
     */
    private static function markOTPAsUsed($otpSessionId) {
        try {
            DB::update('user_otp_sessions', 
                ['is_used' => 'Yes'], 
                'otp_session_id = %i', $otpSessionId
            );
        } catch (Exception $e) {
            error_log("Error marking OTP as used: " . $e->getMessage());
        }
    }

    /**
     * Cancel an OTP session (mark as used for security)
     * @param string $sessionToken OTP session token
     * @param string $ipAddress Client IP address for validation
     * @return bool True if cancellation was successful
     */
    public static function cancelOTPSession($sessionToken, $ipAddress) {
        try {
            // Validate input
            if (empty($sessionToken) || empty($ipAddress)) {
                return false;
            }
            
            // Update the session to mark it as used (cancelled)
            $rowsUpdated = DB::update('user_otp_sessions', [
                'is_used' => 'Yes'
            ], 'session_token = %s AND ip_address = %s AND is_used = %s', 
               $sessionToken, $ipAddress, 'No');
            
            if ($rowsUpdated > 0) {
                // Log the cancellation for security auditing
                if (function_exists('logSecurityEvent')) {
                    $session = DB::queryFirstRow(
                        "SELECT user_id, unit_id FROM user_otp_sessions WHERE session_token = %s",
                        $sessionToken
                    );
                    if ($session) {
                        logSecurityEvent($session['user_id'], 'otp_session_cancelled', $session['user_id'], $session['unit_id']);
                    }
                }
                
                error_log("[2FA CANCEL] OTP session cancelled for token: " . substr($sessionToken, 0, 8) . "... from IP: $ipAddress");
                return true;
            }
            
            return false;
        } catch (Exception $e) {
            error_log("[2FA CANCEL ERROR] Failed to cancel OTP session: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Clean up expired OTP sessions
     */
    public static function cleanupExpiredSessions() {
        try {
            DB::delete('user_otp_sessions', 'expires_at < NOW()');
        } catch (Exception $e) {
            error_log("Error cleaning up expired OTP sessions: " . $e->getMessage());
        }
    }
    
    /**
     * Clean up OTP sessions for a specific user
     * @param int $userId User ID
     */
    private static function cleanupUserOTPSessions($userId) {
        try {
            DB::update('user_otp_sessions', 
                ['is_used' => 'Yes'], 
                'user_id = %i AND is_used = %s', $userId, 'No'
            );
        } catch (Exception $e) {
            error_log("Error cleaning up user OTP sessions: " . $e->getMessage());
        }
    }
    
    /**
     * Get OTP session by token
     * @param string $sessionToken Session token
     * @param string $ipAddress IP address
     * @return array|false Session data or false
     */
    public static function getOTPSession($sessionToken, $ipAddress) {
        try {
            return DB::queryFirstRow("
                SELECT otp_session_id, user_id, unit_id, employee_id, 
                       expires_at, is_used, attempts_count,
                       TIMESTAMPDIFF(SECOND, NOW(), expires_at) as seconds_remaining
                FROM user_otp_sessions 
                WHERE session_token = %s AND ip_address = %s", 
                $sessionToken, $ipAddress
            );
        } catch (Exception $e) {
            error_log("Error getting OTP session: " . $e->getMessage());
            return false;
        }
    }
}
?>