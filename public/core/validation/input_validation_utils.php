<?php
/**
 * ProVal HVAC - Input Validation and Security Utilities
 * 
 * Comprehensive input validation, sanitization, and security utilities
 * for the ProVal HVAC validation system.
 * 
 * @version 1.0
 * @author ProVal Security Team
 */

/**
 * Input validation and sanitization class
 */
class InputValidator {
    
    // Common validation patterns
    const PATTERN_ALPHANUMERIC = '/^[a-zA-Z0-9]+$/';
    const PATTERN_ALPHANUMERIC_SPACE = '/^[a-zA-Z0-9\s]+$/';
    const PATTERN_EMAIL = '/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/';
    const PATTERN_PHONE = '/^[\+]?[0-9\s\-\(\)]+$/';
    const PATTERN_DATE = '/^\d{4}-\d{2}-\d{2}$/';
    const PATTERN_DATETIME = '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/';
    const PATTERN_WORKFLOW_ID = '/^[A-Z0-9\-]+$/';
    const PATTERN_SAFE_FILENAME = '/^[a-zA-Z0-9._\-]+$/';
    
    // Maximum lengths for different field types
    const MAX_LENGTH_SHORT = 50;
    const MAX_LENGTH_MEDIUM = 255;
    const MAX_LENGTH_LONG = 1000;
    const MAX_LENGTH_TEXT = 5000;
    
    /**
     * Sanitize string input to prevent XSS
     * 
     * @param string $input The input to sanitize
     * @param bool $allowHtml Whether to allow limited HTML tags
     * @return string Sanitized input
     */
    public static function sanitizeString($input, $allowHtml = false) {
        if (!is_string($input)) {
            return '';
        }
        
        // Remove null bytes
        $input = str_replace("\0", '', $input);
        
        if ($allowHtml) {
            // Allow only safe HTML tags
            $allowedTags = '<p><br><strong><em><u><ol><ul><li>';
            $input = strip_tags($input, $allowedTags);
        } else {
            // Remove all HTML tags and encode special characters
            $input = htmlspecialchars(strip_tags($input), ENT_QUOTES, 'UTF-8');
        }
        
        // Trim whitespace
        return trim($input);
    }
    
    /**
     * Validate and sanitize integer input
     * 
     * @param mixed $input The input to validate
     * @param int $min Minimum allowed value
     * @param int $max Maximum allowed value
     * @return int|false Validated integer or false on failure
     */
    public static function validateInteger($input, $min = null, $max = null) {
        $value = filter_var($input, FILTER_VALIDATE_INT);
        
        if ($value === false) {
            return false;
        }
        
        if ($min !== null && $value < $min) {
            return false;
        }
        
        if ($max !== null && $value > $max) {
            return false;
        }
        
        return $value;
    }
    
    /**
     * Validate email address
     * 
     * @param string $email Email to validate
     * @return string|false Valid email or false on failure
     */
    public static function validateEmail($email) {
        $email = self::sanitizeString($email);
        
        if (!preg_match(self::PATTERN_EMAIL, $email)) {
            return false;
        }
        
        if (strlen($email) > self::MAX_LENGTH_MEDIUM) {
            return false;
        }
        
        return filter_var($email, FILTER_VALIDATE_EMAIL);
    }
    
    /**
     * Validate date string
     * 
     * @param string $date Date string to validate
     * @param string $format Expected date format (default: Y-m-d)
     * @return string|false Valid date or false on failure
     */
    public static function validateDate($date, $format = 'Y-m-d') {
        $date = self::sanitizeString($date);
        
        $datetime = DateTime::createFromFormat($format, $date);
        
        if ($datetime === false || $datetime->format($format) !== $date) {
            return false;
        }
        
        return $date;
    }
    
    /**
     * Validate workflow ID format
     * 
     * @param string $workflowId Workflow ID to validate
     * @return string|false Valid workflow ID or false on failure
     */
    public static function validateWorkflowId($workflowId) {
        $workflowId = self::sanitizeString($workflowId);
        
        if (!preg_match(self::PATTERN_WORKFLOW_ID, $workflowId)) {
            return false;
        }
        
        if (strlen($workflowId) > self::MAX_LENGTH_SHORT) {
            return false;
        }
        
        return $workflowId;
    }
    
    /**
     * Validate text length and content
     * 
     * @param string $text Text to validate
     * @param int $maxLength Maximum allowed length
     * @param bool $required Whether the field is required
     * @param bool $allowHtml Whether to allow HTML tags
     * @return string|false Valid text or false on failure
     */
    public static function validateText($text, $maxLength = self::MAX_LENGTH_LONG, $required = true, $allowHtml = false) {
        $text = self::sanitizeString($text, $allowHtml);
        
        if ($required && empty($text)) {
            return false;
        }
        
        if (strlen($text) > $maxLength) {
            return false;
        }
        
        return $text;
    }
    
    /**
     * Validate filename for uploads
     * 
     * @param string $filename Filename to validate
     * @return string|false Valid filename or false on failure
     */
    public static function validateFilename($filename) {
        $filename = self::sanitizeString($filename);
        
        // Check for directory traversal attempts
        if (strpos($filename, '..') !== false || strpos($filename, '/') !== false || strpos($filename, '\\') !== false) {
            return false;
        }
        
        if (!preg_match(self::PATTERN_SAFE_FILENAME, $filename)) {
            return false;
        }
        
        if (strlen($filename) > self::MAX_LENGTH_MEDIUM) {
            return false;
        }
        
        return $filename;
    }
    
    /**
     * Validate array of values
     * 
     * @param array $values Array to validate
     * @param callable $validator Validation function to apply to each value
     * @param int $maxItems Maximum number of items allowed
     * @return array|false Valid array or false on failure
     */
    public static function validateArray($values, $validator, $maxItems = 100) {
        if (!is_array($values)) {
            return false;
        }
        
        if (count($values) > $maxItems) {
            return false;
        }
        
        $validatedValues = [];
        
        foreach ($values as $value) {
            $validatedValue = call_user_func($validator, $value);
            
            if ($validatedValue === false) {
                return false;
            }
            
            $validatedValues[] = $validatedValue;
        }
        
        return $validatedValues;
    }
    
    /**
     * Validate CSRF token
     * 
     * @param string $token Token to validate
     * @param string $sessionToken Expected token from session
     * @return bool True if valid, false otherwise
     */
    public static function validateCSRFToken($token, $sessionToken) {
        if (empty($token) || empty($sessionToken)) {
            return false;
        }
        
        // Use hash_equals to prevent timing attacks
        return hash_equals($sessionToken, $token);
    }
    
    /**
     * Generate secure random token
     * 
     * @param int $length Token length in bytes
     * @return string Generated token
     */
    public static function generateSecureToken($length = 32) {
        return bin2hex(random_bytes($length));
    }
    
    /**
     * Validate and sanitize POST data
     * 
     * @param array $rules Validation rules array
     * @param array $data Data to validate (defaults to $_POST)
     * @return array Array with 'valid' boolean and 'data' or 'errors'
     */
    public static function validatePostData($rules, $data = null) {
        if ($data === null) {
            $data = $_POST;
        }
        
        $validatedData = [];
        $errors = [];
        
        foreach ($rules as $field => $rule) {
            $value = isset($data[$field]) ? $data[$field] : null;
            $required = isset($rule['required']) ? $rule['required'] : false;
            $validator = isset($rule['validator']) ? $rule['validator'] : 'sanitizeString';
            $params = isset($rule['params']) ? $rule['params'] : [];
            
            // Check if field is required
            if ($required && ($value === null || $value === '')) {
                $errors[$field] = 'Field is required';
                continue;
            }
            
            // Skip validation if field is not required and empty
            if (!$required && ($value === null || $value === '')) {
                $validatedData[$field] = '';
                continue;
            }
            
            // Apply validator
            if (method_exists(self::class, $validator)) {
                $validatedValue = call_user_func_array([self::class, $validator], array_merge([$value], $params));
            } else {
                $validatedValue = call_user_func_array($validator, array_merge([$value], $params));
            }
            
            if ($validatedValue === false) {
                $errors[$field] = 'Invalid value';
            } else {
                $validatedData[$field] = $validatedValue;
            }
        }
        
        return [
            'valid' => empty($errors),
            'data' => $validatedData,
            'errors' => $errors
        ];
    }
}

/**
 * Security utilities class
 */
class SecurityUtils {
    
    /**
     * Log security event
     * 
     * @param string $event Event type
     * @param string $description Event description
     * @param array $context Additional context
     */
    public static function logSecurityEvent($event, $description, $context = []) {
        $logData = [
            'timestamp' => date('Y-m-d H:i:s'),
            'event' => $event,
            'description' => $description,
            'ip_address' => self::getClientIP(),
            'user_agent' => isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '',
            'session_id' => session_id(),
            'user_id' => isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null,
            'context' => $context
        ];
        
        // Log to database if available
        if (class_exists('DB')) {
            try {
                DB::insert('security_log', [
                    'event_type' => $event,
                    'description' => $description,
                    'ip_address' => $logData['ip_address'],
                    'user_agent' => substr($logData['user_agent'], 0, 255),
                    'user_id' => $logData['user_id'],
                    'context_data' => json_encode($context),
                    'created_at' => $logData['timestamp']
                ]);
            } catch (Exception $e) {
                // Fall back to file logging if database fails
                error_log("Security Event: " . json_encode($logData));
            }
        } else {
            // Log to file
            error_log("Security Event: " . json_encode($logData));
        }
    }
    
    /**
     * Get client IP address
     * 
     * @return string Client IP address
     */
    public static function getClientIP() {
        $ipKeys = ['HTTP_CF_CONNECTING_IP', 'HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED', 'HTTP_FORWARDED_FOR', 'HTTP_FORWARDED', 'REMOTE_ADDR'];
        
        foreach ($ipKeys as $key) {
            if (array_key_exists($key, $_SERVER) === true) {
                $ip = $_SERVER[$key];
                if (strpos($ip, ',') !== false) {
                    $ip = explode(',', $ip)[0];
                }
                $ip = trim($ip);
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }
        
        return isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : 'unknown';
    }
    
    /**
     * Check for suspicious patterns in input
     * 
     * @param string $input Input to check
     * @return bool True if suspicious patterns found
     */
    public static function detectSuspiciousPatterns($input) {
        // Ensure input is a string to prevent preg_match errors
        if (!is_string($input)) {
            return false;
        }
        
        // Simple string-based checks instead of complex regex to avoid delimiter issues
        $input_lower = strtolower($input);
        
        // Check for SQL injection patterns
        if (strpos($input_lower, 'union select') !== false) return true;
        if (strpos($input_lower, 'insert into') !== false) return true;
        if (strpos($input_lower, 'delete from') !== false) return true;
        if (strpos($input_lower, 'drop table') !== false) return true;
        if (strpos($input_lower, 'update ') !== false && strpos($input_lower, ' set ') !== false) return true;
        
        // Check for XSS patterns
        if (strpos($input_lower, '<script') !== false) return true;
        if (strpos($input_lower, 'javascript:') !== false) return true;
        if (strpos($input_lower, 'vbscript:') !== false) return true;
        if (strpos($input_lower, 'onload=') !== false) return true;
        if (strpos($input_lower, 'onerror=') !== false) return true;
        if (strpos($input_lower, 'onclick=') !== false) return true;
        
        // Check for path traversal
        if (strpos($input, '../') !== false) return true;
        if (strpos($input, '..\\') !== false) return true;
        
        return false;
    }
    
    /**
     * Rate limiting check
     * 
     * @param string $action Action being rate limited
     * @param int $maxAttempts Maximum attempts allowed
     * @param int $timeWindow Time window in seconds
     * @return bool True if rate limit exceeded
     */
    public static function checkRateLimit($action, $maxAttempts = 5, $timeWindow = 300) {
        $clientIP = self::getClientIP();
        $key = $action . '_' . $clientIP;
        
        // Use session storage for rate limiting (could be improved with Redis/Memcache)
        if (!isset($_SESSION['rate_limits'])) {
            $_SESSION['rate_limits'] = [];
        }
        
        $now = time();
        
        // Clean old entries
        if (isset($_SESSION['rate_limits'][$key])) {
            $_SESSION['rate_limits'][$key] = array_filter(
                $_SESSION['rate_limits'][$key],
                function($timestamp) use ($now, $timeWindow) {
                    return ($now - $timestamp) < $timeWindow;
                }
            );
        } else {
            $_SESSION['rate_limits'][$key] = [];
        }
        
        // Check if rate limit exceeded
        if (count($_SESSION['rate_limits'][$key]) >= $maxAttempts) {
            self::logSecurityEvent('rate_limit_exceeded', "Rate limit exceeded for action: $action", [
                'action' => $action,
                'ip' => $clientIP,
                'attempts' => count($_SESSION['rate_limits'][$key])
            ]);
            return true;
        }
        
        // Add current attempt
        $_SESSION['rate_limits'][$key][] = $now;
        
        return false;
    }
    
    /**
     * Enhanced password validation
     * 
     * @param string $password Password to validate
     * @param int $minLength Minimum length required
     * @return array Validation result with strength score
     */
    public static function validatePassword($password, $minLength = 8) {
        $result = [
            'valid' => false,
            'strength' => 0,
            'issues' => []
        ];
        
        if (strlen($password) < $minLength) {
            $result['issues'][] = "Password must be at least $minLength characters long";
        }
        
        if (!preg_match('/[a-z]/', $password)) {
            $result['issues'][] = 'Password must contain at least one lowercase letter';
        } else {
            $result['strength'] += 1;
        }
        
        if (!preg_match('/[A-Z]/', $password)) {
            $result['issues'][] = 'Password must contain at least one uppercase letter';
        } else {
            $result['strength'] += 1;
        }
        
        if (!preg_match('/[0-9]/', $password)) {
            $result['issues'][] = 'Password must contain at least one number';
        } else {
            $result['strength'] += 1;
        }
        
        if (!preg_match('/[^a-zA-Z0-9]/', $password)) {
            $result['issues'][] = 'Password must contain at least one special character';
        } else {
            $result['strength'] += 1;
        }
        
        if (strlen($password) >= 12) {
            $result['strength'] += 1;
        }
        
        $result['valid'] = empty($result['issues']);
        
        return $result;
    }
}

/**
 * File upload security utilities
 */
class FileUploadValidator {
    
    // Allowed file types and their MIME types
    const ALLOWED_TYPES = [
        'pdf' => ['application/pdf'],
        'doc' => ['application/msword'],
        'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
        'xls' => ['application/vnd.ms-excel'],
        'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
        'jpg' => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
        'png' => ['image/png'],
        'gif' => ['image/gif'],
        'txt' => ['text/plain']
    ];
    
    const MAX_FILE_SIZE = 10485760; // 10MB
    
    /**
     * Validate uploaded file
     * 
     * @param array $file $_FILES array element
     * @param array $allowedTypes Allowed file extensions
     * @param int $maxSize Maximum file size in bytes
     * @return array Validation result
     */
    public static function validateFile($file, $allowedTypes = null, $maxSize = null) {
        $result = [
            'valid' => false,
            'errors' => [],
            'sanitized_name' => ''
        ];
        
        // Check if file was uploaded
        if (!isset($file['tmp_name']) || empty($file['tmp_name'])) {
            $result['errors'][] = 'No file uploaded';
            return $result;
        }
        
        // Check for upload errors
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $result['errors'][] = 'File upload error: ' . $file['error'];
            return $result;
        }
        
        // Validate file size
        $maxSize = $maxSize ?: self::MAX_FILE_SIZE;
        if ($file['size'] > $maxSize) {
            $result['errors'][] = 'File size exceeds limit (' . number_format($maxSize / 1024 / 1024, 2) . 'MB)';
            return $result;
        }
        
        // Validate filename
        $filename = InputValidator::validateFilename($file['name']);
        if ($filename === false) {
            $result['errors'][] = 'Invalid filename';
            return $result;
        }
        
        // Get file extension
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        
        // Check allowed types
        $allowedTypes = $allowedTypes ?: array_keys(self::ALLOWED_TYPES);
        if (!in_array($extension, $allowedTypes)) {
            $result['errors'][] = 'File type not allowed';
            return $result;
        }
        
        // Validate MIME type
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        
        if (!in_array($mimeType, self::ALLOWED_TYPES[$extension])) {
            $result['errors'][] = 'File content does not match extension';
            return $result;
        }
        
        // Generate sanitized filename
        $basename = pathinfo($filename, PATHINFO_FILENAME);
        $basename = preg_replace('/[^a-zA-Z0-9._-]/', '_', $basename);
        $result['sanitized_name'] = $basename . '.' . $extension;
        
        $result['valid'] = true;
        
        return $result;
    }
    
    /**
     * Scan file for malware (placeholder - would integrate with actual scanner)
     * 
     * @param string $filePath Path to file to scan
     * @return bool True if file is clean
     */
    public static function scanForMalware($filePath) {
        // Placeholder for malware scanning
        // In production, this would integrate with ClamAV or similar
        
        // Basic checks
        $handle = fopen($filePath, 'rb');
        $chunk = fread($handle, 1024);
        fclose($handle);
        
        // Look for obvious malicious patterns
        $maliciousPatterns = [
            'eval(',
            'exec(',
            'system(',
            'shell_exec(',
            'passthru(',
            '<?php',
            '<script',
            'javascript:'
        ];
        
        foreach ($maliciousPatterns as $pattern) {
            if (strpos($chunk, $pattern) !== false) {
                SecurityUtils::logSecurityEvent('malicious_file_upload', 'Malicious pattern detected in uploaded file', [
                    'pattern' => $pattern,
                    'file' => basename($filePath)
                ]);
                return false;
            }
        }
        
        return true;
    }
}