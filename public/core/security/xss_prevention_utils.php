<?php
/**
 * XSS Prevention Utilities for ProVal HVAC Security
 * 
 * Provides comprehensive XSS prevention, output encoding, and content filtering
 * to protect against cross-site scripting attacks.
 * 
 * @version 1.0
 * @author ProVal Security Team
 */

class XSSPrevention {
    
    // Dangerous HTML tags that should be stripped
    const DANGEROUS_TAGS = [
        'script', 'iframe', 'object', 'embed', 'form', 'input', 'textarea',
        'select', 'option', 'button', 'link', 'meta', 'style', 'title',
        'base', 'frame', 'frameset', 'applet', 'body', 'html', 'head'
    ];
    
    // Dangerous attributes that should be removed
    const DANGEROUS_ATTRIBUTES = [
        'onload', 'onerror', 'onclick', 'onmouseover', 'onmouseout', 'onfocus',
        'onblur', 'onchange', 'onsubmit', 'onreset', 'onselect', 'onresize',
        'onscroll', 'onunload', 'onbeforeunload', 'oncontextmenu', 'ondrag',
        'ondrop', 'onkeydown', 'onkeypress', 'onkeyup', 'onmousedown',
        'onmousemove', 'onmouseup', 'onwheel', 'ontouchstart', 'ontouchmove',
        'ontouchend', 'javascript:', 'vbscript:', 'data:', 'mocha:', 'livescript:'
    ];
    
    // Safe HTML tags (if HTML is allowed)
    const SAFE_TAGS = [
        'p', 'br', 'strong', 'em', 'u', 'b', 'i', 'ul', 'ol', 'li',
        'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'blockquote', 'code', 'pre'
    ];
    
    /**
     * Clean user input to prevent XSS attacks
     * 
     * @param string $input User input to clean
     * @param bool $allowHtml Whether to allow safe HTML tags
     * @param bool $strict Use strict filtering mode
     * @return string Cleaned input
     */
    public static function cleanInput($input, $allowHtml = false, $strict = true) {
        if (!is_string($input)) {
            // Preserve non-string values (integers, booleans, etc.) instead of converting to empty string
            return $input;
        }
        
        // Remove null bytes and control characters
        $input = str_replace("\0", '', $input);
        $input = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $input);
        
        if ($strict) {
            // Strict mode: encode everything
            return htmlspecialchars($input, ENT_QUOTES | ENT_HTML5, 'UTF-8', true);
        }
        
        if (!$allowHtml) {
            // Strip all HTML tags and encode special characters
            $input = strip_tags($input);
            return htmlspecialchars($input, ENT_QUOTES | ENT_HTML5, 'UTF-8', true);
        }
        
        // Allow limited safe HTML
        $input = self::stripDangerousContent($input);
        $input = self::cleanAttributes($input);
        
        // Use HTMLPurifier if available, otherwise basic filtering
        if (class_exists('HTMLPurifier')) {
            $config = HTMLPurifier_Config::createDefault();
            $config->set('HTML.Allowed', implode(',', self::SAFE_TAGS));
            $config->set('HTML.ForbiddenAttributes', '*@style,*@class,*@id');
            $purifier = new HTMLPurifier($config);
            return $purifier->purify($input);
        }
        
        // Basic safe HTML filtering
        $allowedTags = '<' . implode('><', self::SAFE_TAGS) . '>';
        $input = strip_tags($input, $allowedTags);
        
        return $input;
    }
    
    /**
     * Strip dangerous HTML content
     * 
     * @param string $input Input to clean
     * @return string Cleaned input
     */
    private static function stripDangerousContent($input) {
        // Remove dangerous tags
        foreach (self::DANGEROUS_TAGS as $tag) {
            $input = preg_replace('/<' . $tag . '[^>]*>/i', '', $input);
            $input = preg_replace('/<\/' . $tag . '>/i', '', $input);
        }
        
        // Remove javascript: and other dangerous protocols
        $input = preg_replace('/javascript:/i', '', $input);
        $input = preg_replace('/vbscript:/i', '', $input);
        $input = preg_replace('/data:/i', '', $input);
        $input = preg_replace('/mocha:/i', '', $input);
        $input = preg_replace('/livescript:/i', '', $input);
        
        // Remove HTML comments (potential for hiding malicious code)
        $input = preg_replace('/<!--.*?-->/s', '', $input);
        
        // Remove CDATA sections
        $input = preg_replace('/<!\[CDATA\[.*?\]\]>/s', '', $input);
        
        return $input;
    }
    
    /**
     * Clean dangerous attributes from HTML
     * 
     * @param string $input Input to clean
     * @return string Cleaned input
     */
    private static function cleanAttributes($input) {
        // Remove event handlers and dangerous attributes
        foreach (self::DANGEROUS_ATTRIBUTES as $attr) {
            $input = preg_replace('/' . preg_quote($attr, '/') . '\s*=\s*["\'][^"\']*["\']/i', '', $input);
            $input = preg_replace('/' . preg_quote($attr, '/') . '\s*=\s*[^>\s]*/i', '', $input);
        }
        
        // Remove style attributes (can contain JavaScript)
        $input = preg_replace('/\bstyle\s*=\s*["\'][^"\']*["\']/i', '', $input);
        $input = preg_replace('/\bstyle\s*=\s*[^>\s]*/i', '', $input);
        
        return $input;
    }
    
    /**
     * Detect potential XSS patterns
     * 
     * @param string $input Input to check
     * @return bool True if suspicious patterns found
     */
    public static function detectXSS($input) {
        $xssPatterns = [
            '/<script[^>]*>.*?<\/script>/is',
            '/javascript:/i',
            '/vbscript:/i',
            '/onload\s*=/i',
            '/onerror\s*=/i',
            '/<iframe[^>]*>/i',
            '/<object[^>]*>/i',
            '/<embed[^>]*>/i',
            '/document\.cookie/i',
            '/document\.write/i',
            '/window\.location/i',
            '/alert\s*\(/i',
            '/confirm\s*\(/i',
            '/prompt\s*\(/i',
            '/eval\s*\(/i',
            '/expression\s*\(/i',
            '/setTimeout\s*\(/i',
            '/setInterval\s*\(/i'
        ];
        
        foreach ($xssPatterns as $pattern) {
            if (preg_match($pattern, $input)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Safe output encoding for different contexts
     * 
     * @param string $data Data to encode
     * @param string $context Output context (html, attr, js, css, url)
     * @return string Encoded data
     */
    public static function encode($data, $context = 'html') {
        if (!is_string($data)) {
            return '';
        }
        
        switch ($context) {
            case 'html':
                return htmlspecialchars($data, ENT_QUOTES | ENT_HTML5, 'UTF-8', true);
                
            case 'attr':
                // HTML attribute context
                return htmlspecialchars($data, ENT_QUOTES | ENT_HTML5, 'UTF-8', true);
                
            case 'js':
                // JavaScript context
                $data = json_encode($data, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
                return $data;
                
            case 'css':
                // CSS context - very restrictive
                return preg_replace('/[^a-zA-Z0-9\-_]/', '', $data);
                
            case 'url':
                // URL context
                return urlencode($data);
                
            default:
                return htmlspecialchars($data, ENT_QUOTES | ENT_HTML5, 'UTF-8', true);
        }
    }
    
    /**
     * Safe JSON encoding for JavaScript output
     * 
     * @param mixed $data Data to encode
     * @return string Safe JSON string
     */
    public static function jsonEncode($data) {
        return json_encode($data, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE);
    }
    
    /**
     * Clean filename for uploads
     * 
     * @param string $filename Original filename
     * @return string Cleaned filename
     */
    public static function cleanFilename($filename) {
        // Remove path traversal attempts
        $filename = basename($filename);
        
        // Remove dangerous characters
        $filename = preg_replace('/[^a-zA-Z0-9._-]/', '_', $filename);
        
        // Limit length
        if (strlen($filename) > 100) {
            $filename = substr($filename, 0, 100);
        }
        
        return $filename;
    }
    
    /**
     * Validate and clean URL
     * 
     * @param string $url URL to validate
     * @param array $allowedSchemes Allowed URL schemes
     * @return string|false Clean URL or false if invalid
     */
    public static function cleanUrl($url, $allowedSchemes = ['http', 'https']) {
        $url = trim($url);
        
        // Basic URL validation
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return false;
        }
        
        $parsed = parse_url($url);
        
        // Check scheme
        if (!isset($parsed['scheme']) || !in_array(strtolower($parsed['scheme']), $allowedSchemes)) {
            return false;
        }
        
        // Rebuild URL to ensure it's clean
        $cleanUrl = $parsed['scheme'] . '://';
        
        if (isset($parsed['user'])) {
            $cleanUrl .= urlencode($parsed['user']);
            if (isset($parsed['pass'])) {
                $cleanUrl .= ':' . urlencode($parsed['pass']);
            }
            $cleanUrl .= '@';
        }
        
        if (isset($parsed['host'])) {
            $cleanUrl .= $parsed['host'];
        }
        
        if (isset($parsed['port'])) {
            $cleanUrl .= ':' . $parsed['port'];
        }
        
        if (isset($parsed['path'])) {
            $cleanUrl .= $parsed['path'];
        }
        
        if (isset($parsed['query'])) {
            $cleanUrl .= '?' . $parsed['query'];
        }
        
        if (isset($parsed['fragment'])) {
            $cleanUrl .= '#' . $parsed['fragment'];
        }
        
        return $cleanUrl;
    }
    
    /**
     * Log XSS attempts
     * 
     * @param string $input Malicious input
     * @param string $context Where the XSS was detected
     */
    public static function logXSSAttempt($input, $context = 'unknown') {
        $logData = [
            'input' => substr($input, 0, 500), // Limit input size in log
            'context' => $context,
            'ip_address' => SecurityUtils::getClientIP(),
            'user_agent' => isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '',
            'user_id' => isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null,
            'referer' => isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '',
            'request_uri' => isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : ''
        ];
        
        if (class_exists('SecurityUtils')) {
            SecurityUtils::logSecurityEvent('xss_attempt', 'XSS attack attempt detected', $logData);
        } else {
            error_log("XSS Attempt: " . json_encode($logData));
        }
    }
    
    /**
     * Filter request data for XSS
     * 
     * @param array $data Request data ($_GET, $_POST, etc.)
     * @param bool $strict Use strict filtering
     * @return array Filtered data
     */
    public static function filterRequestData($data, $strict = false) {
        $filtered = [];
        
        foreach ($data as $key => $value) {
            // Clean the key itself
            $cleanKey = self::cleanInput($key, false, true);
            
            if (is_array($value)) {
                $filtered[$cleanKey] = self::filterRequestData($value, $strict);
            } else {
                $cleanValue = self::cleanInput($value, false, $strict);
                
                // Log if XSS detected
                if (self::detectXSS($value)) {
                    self::logXSSAttempt($value, "request_data_key_{$key}");
                }
                
                $filtered[$cleanKey] = $cleanValue;
            }
        }
        
        return $filtered;
    }
}

/**
 * Convenience functions for common use cases
 */

/**
 * Safe HTML output
 * 
 * @param string $data Data to output
 * @return string Safe HTML
 */
function safe_html($data) {
    return XSSPrevention::encode($data, 'html');
}

/**
 * Safe attribute output
 * 
 * @param string $data Data for HTML attribute
 * @return string Safe attribute value
 */
function safe_attr($data) {
    return XSSPrevention::encode($data, 'attr');
}

/**
 * Safe JavaScript output
 * 
 * @param mixed $data Data for JavaScript
 * @return string Safe JavaScript value
 */
function safe_js($data) {
    return XSSPrevention::jsonEncode($data);
}

/**
 * Safe URL output
 * 
 * @param string $data URL data
 * @return string Safe URL
 */
function safe_url($data) {
    $cleanUrl = XSSPrevention::cleanUrl($data);
    return $cleanUrl !== false ? $cleanUrl : '';
}

/**
 * Clean user input (backward compatible)
 * 
 * @param string $input Input to clean
 * @param bool $allowHtml Allow HTML tags
 * @return string Cleaned input
 */
function clean_input($input, $allowHtml = false) {
    return XSSPrevention::cleanInput($input, $allowHtml);
}