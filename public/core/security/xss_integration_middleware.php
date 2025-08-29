<?php
/**
 * XSS Integration Middleware for ProVal HVAC Security
 * 
 * Automatically applies XSS prevention to all request data and provides
 * a centralized way to handle input sanitization across the application.
 * 
 * This middleware should be included early in request processing to ensure
 * all user input is properly sanitized before use.
 * 
 * @version 1.0
 * @author ProVal Security Team
 */

require_once 'xss_prevention_utils.php';

class XSSIntegrationMiddleware {
    
    private static $initialized = false;
    private static $originalData = [];
    
    /**
     * Initialize XSS protection for the current request
     */
    public static function initialize() {
        if (self::$initialized) {
            return;
        }
        
        // Store original data for logging purposes
        self::$originalData = [
            'GET' => $_GET,
            'POST' => $_POST,
            'COOKIE' => $_COOKIE
        ];
        
        // Filter all request data
        $_GET = XSSPrevention::filterRequestData($_GET, false);
        $_POST = XSSPrevention::filterRequestData($_POST, false);
        $_COOKIE = XSSPrevention::filterRequestData($_COOKIE, true); // Strict for cookies
        
        // Also filter request data
        if (isset($_REQUEST)) {
            $_REQUEST = XSSPrevention::filterRequestData($_REQUEST, false);
        }
        
        self::$initialized = true;
        
        // Log if any XSS was detected and filtered
        self::logFilteredData();
    }
    
    /**
     * Get sanitized input with additional validation
     * 
     * @param string $key Input key
     * @param string $source Data source ('GET', 'POST', 'COOKIE')
     * @param string $type Expected data type ('string', 'int', 'float', 'email', 'url', 'filename')
     * @param mixed $default Default value if key doesn't exist
     * @return mixed Sanitized value
     */
    public static function getSafeInput($key, $source = 'POST', $type = 'string', $default = null) {
        $data = null;
        
        switch (strtoupper($source)) {
            case 'GET':
                $data = $_GET[$key] ?? $default;
                break;
            case 'POST':
                $data = $_POST[$key] ?? $default;
                break;
            case 'COOKIE':
                $data = $_COOKIE[$key] ?? $default;
                break;
            case 'REQUEST':
                $data = $_REQUEST[$key] ?? $default;
                break;
            default:
                return $default;
        }
        
        if ($data === null) {
            return $default;
        }
        
        // Additional type-specific validation
        switch ($type) {
            case 'int':
                return filter_var($data, FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE) ?? $default;
                
            case 'float':
                return filter_var($data, FILTER_VALIDATE_FLOAT, FILTER_NULL_ON_FAILURE) ?? $default;
                
            case 'email':
                return filter_var($data, FILTER_VALIDATE_EMAIL) ?: $default;
                
            case 'url':
                $cleanUrl = XSSPrevention::cleanUrl($data);
                return $cleanUrl !== false ? $cleanUrl : $default;
                
            case 'filename':
                return XSSPrevention::cleanFilename($data);
                
            case 'string':
            default:
                // Already filtered by middleware, but apply additional cleaning if needed
                return XSSPrevention::cleanInput($data, false, false);
        }
    }
    
    /**
     * Get raw (unfiltered) input for comparison/logging
     * 
     * @param string $key Input key
     * @param string $source Data source
     * @return mixed Raw value
     */
    public static function getRawInput($key, $source = 'POST') {
        switch (strtoupper($source)) {
            case 'GET':
                return self::$originalData['GET'][$key] ?? null;
            case 'POST':
                return self::$originalData['POST'][$key] ?? null;
            case 'COOKIE':
                return self::$originalData['COOKIE'][$key] ?? null;
            default:
                return null;
        }
    }
    
    /**
     * Check if input was modified by XSS filtering
     * 
     * @param string $key Input key
     * @param string $source Data source
     * @return bool True if input was modified
     */
    public static function wasInputModified($key, $source = 'POST') {
        $original = self::getRawInput($key, $source);
        $filtered = self::getSafeInput($key, $source, 'string', null);
        
        return $original !== $filtered;
    }
    
    /**
     * Safely truncate values for logging, handling arrays and other data types
     */
    private static function safeSubstr($value, $start = 0, $length = 200) {
        if (is_array($value)) {
            // Convert array to JSON string representation for logging
            $jsonString = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR);
            return substr($jsonString, $start, $length);
        }
        
        if (is_string($value)) {
            return substr($value, $start, $length);
        }
        
        // Handle other types (null, boolean, numeric) by converting to string
        return substr((string)$value, $start, $length);
    }
    
    /**
     * Log data that was filtered for XSS
     */
    private static function logFilteredData() {
        $modifiedInputs = [];
        
        // Check GET data
        foreach (self::$originalData['GET'] as $key => $value) {
            if (self::wasInputModified($key, 'GET')) {
                $modifiedInputs[] = [
                    'source' => 'GET',
                    'key' => $key,
                    'original' => self::safeSubstr($value, 0, 200), // Safely handle arrays and other types
                    'filtered' => self::safeSubstr($_GET[$key], 0, 200)
                ];
            }
        }
        
        // Check POST data
        foreach (self::$originalData['POST'] as $key => $value) {
            if (self::wasInputModified($key, 'POST')) {
                $modifiedInputs[] = [
                    'source' => 'POST',
                    'key' => $key,
                    'original' => self::safeSubstr($value, 0, 200), // Safely handle arrays and other types
                    'filtered' => self::safeSubstr($_POST[$key], 0, 200)
                ];
            }
        }
        
        // Log if any inputs were modified
        if (!empty($modifiedInputs)) {
            if (class_exists('SecurityUtils')) {
                SecurityUtils::logSecurityEvent('xss_filtering_applied', 
                    'XSS filtering modified user input', [
                        'modified_inputs' => $modifiedInputs,
                        'count' => count($modifiedInputs)
                    ]);
            } else {
                error_log("XSS Filtering Applied: " . json_encode($modifiedInputs));
            }
        }
    }
    
    /**
     * Apply output encoding based on context
     * 
     * @param mixed $data Data to encode
     * @param string $context Output context ('html', 'attr', 'js', 'css', 'url')
     * @return string Encoded data
     */
    public static function encode($data, $context = 'html') {
        return XSSPrevention::encode($data, $context);
    }
    
    /**
     * Check if XSS protection is active
     * 
     * @return bool True if XSS protection is initialized
     */
    public static function isInitialized() {
        return self::$initialized;
    }
    
    /**
     * Get statistics about XSS filtering for the current request
     * 
     * @return array Statistics
     */
    public static function getFilteringStats() {
        $stats = [
            'initialized' => self::$initialized,
            'inputs_processed' => 0,
            'inputs_modified' => 0,
            'sources_processed' => []
        ];
        
        if (!self::$initialized) {
            return $stats;
        }
        
        foreach (['GET', 'POST', 'COOKIE'] as $source) {
            if (isset(self::$originalData[$source])) {
                $sourceCount = count(self::$originalData[$source]);
                $sourceModified = 0;
                
                foreach (self::$originalData[$source] as $key => $value) {
                    if (self::wasInputModified($key, $source)) {
                        $sourceModified++;
                    }
                }
                
                $stats['inputs_processed'] += $sourceCount;
                $stats['inputs_modified'] += $sourceModified;
                $stats['sources_processed'][$source] = [
                    'total' => $sourceCount,
                    'modified' => $sourceModified
                ];
            }
        }
        
        return $stats;
    }
}

/**
 * Convenience functions for common use cases
 */

/**
 * Initialize XSS protection (should be called early in request processing)
 */
function init_xss_protection() {
    XSSIntegrationMiddleware::initialize();
}

/**
 * Get safe input with type validation
 * 
 * @param string $key Input key
 * @param string $source Data source
 * @param string $type Data type
 * @param mixed $default Default value
 * @return mixed Safe input
 */
function safe_input($key, $source = 'POST', $type = 'string', $default = null) {
    return XSSIntegrationMiddleware::getSafeInput($key, $source, $type, $default);
}

/**
 * Safe GET parameter
 * 
 * @param string $key Parameter key
 * @param string $type Data type
 * @param mixed $default Default value
 * @return mixed Safe value
 */
function safe_get($key, $type = 'string', $default = null) {
    return XSSIntegrationMiddleware::getSafeInput($key, 'GET', $type, $default);
}

/**
 * Safe POST parameter
 * 
 * @param string $key Parameter key
 * @param string $type Data type
 * @param mixed $default Default value
 * @return mixed Safe value
 */
function safe_post($key, $type = 'string', $default = null) {
    return XSSIntegrationMiddleware::getSafeInput($key, 'POST', $type, $default);
}

/**
 * Safe COOKIE value
 * 
 * @param string $key Cookie key
 * @param string $type Data type
 * @param mixed $default Default value
 * @return mixed Safe value
 */
function safe_cookie($key, $type = 'string', $default = null) {
    return XSSIntegrationMiddleware::getSafeInput($key, 'COOKIE', $type, $default);
}

/**
 * Output safe HTML
 * 
 * @param mixed $data Data to output
 * @return string Safe HTML
 */
function echo_safe($data) {
    echo XSSIntegrationMiddleware::encode($data, 'html');
}

/**
 * Output safe HTML attribute
 * 
 * @param mixed $data Data for attribute
 * @return string Safe attribute value
 */
function echo_attr($data) {
    echo XSSIntegrationMiddleware::encode($data, 'attr');
}

/**
 * Output safe JavaScript value
 * 
 * @param mixed $data Data for JavaScript
 * @return string Safe JavaScript
 */
function echo_js($data) {
    echo XSSIntegrationMiddleware::encode($data, 'js');
}

// Auto-initialize XSS protection when this file is included
// This ensures all request data is filtered as early as possible
init_xss_protection();