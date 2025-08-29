<?php
/**
 * Security Manager Class
 * 
 * Centralized management of HTTP security headers with context-aware policies.
 * Provides secure defaults with controlled exceptions for specific use cases.
 */

require_once(__DIR__ . '/security_config.php');

class SecurityManager {
    
    private static $instance = null;
    private $currentContext = 'default';
    private $appliedHeaders = [];
    private $auditLog = [];
    
    /**
     * Get singleton instance
     */
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        // Private constructor for singleton
    }
    
    /**
     * Set security context for current request
     * 
     * @param string $context Security context name
     * @param string $reason Reason for context change (for auditing)
     * @return self
     */
    public function setContext($context, $reason = '') {
        global $SECURITY_CONFIG;
        
        // Validate context exists
        if ($context !== 'default' && !isset($SECURITY_CONFIG['contexts'][$context])) {
            error_log("SecurityManager: Invalid security context '$context' requested");
            return $this;
        }
        
        $previousContext = $this->currentContext;
        $this->currentContext = $context;
        
        // Log context change for audit
        $this->logSecurityEvent('context_change', [
            'previous_context' => $previousContext,
            'new_context' => $context,
            'reason' => $reason,
            'script' => $_SERVER['SCRIPT_NAME'] ?? 'unknown',
            'timestamp' => date('Y-m-d H:i:s')
        ]);
        
        return $this;
    }
    
    /**
     * Apply security headers based on current context
     * 
     * @param bool $force Force application even if headers already sent
     * @return self
     */
    public function applyHeaders($force = false) {
        if (headers_sent() && !$force) {
            error_log("SecurityManager: Cannot apply headers - headers already sent");
            return $this;
        }
        
        $config = getSecurityConfig($this->currentContext);
        $environment = defined('ENVIRONMENT') ? ENVIRONMENT : 'development';
        
        // Clear any existing frame-related headers if we need to override them
        if ($force) {
            header_remove('X-Frame-Options');
            header_remove('Content-Security-Policy');
        }
        
        // Apply X-Frame-Options
        if (!empty($config['x_frame_options'])) {
            $this->setHeader('X-Frame-Options', $config['x_frame_options']);
        }
        
        // Apply XSS Protection
        if (!empty($config['xss_protection'])) {
            $this->setHeader('X-XSS-Protection', $config['xss_protection']);
        }
        
        // Apply Content Type Options
        if (!empty($config['content_type_options'])) {
            $this->setHeader('X-Content-Type-Options', $config['content_type_options']);
        }
        
        // Apply Referrer Policy
        if (!empty($config['referrer_policy'])) {
            $this->setHeader('Referrer-Policy', $config['referrer_policy']);
        }
        
        // Apply HSTS if enabled
        if (!empty($config['hsts_enabled'])) {
            $hsts_value = 'max-age=' . $config['hsts_max_age'];
            if (!empty($config['hsts_include_subdomains'])) {
                $hsts_value .= '; includeSubDomains';
            }
            if (!empty($config['hsts_preload'])) {
                $hsts_value .= '; preload';
            }
            $this->setHeader('Strict-Transport-Security', $hsts_value);
        }
        
        // Apply Content Security Policy
        if (!empty($config['csp_enabled'])) {
            $csp = $this->buildCSP($config);
            $this->setHeader('Content-Security-Policy', $csp);
        }
        
        // Apply Permissions Policy if enabled
        if (!empty($config['permissions_policy_enabled'])) {
            $this->setHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');
        }
        
        // Apply any additional headers for environment
        if (!empty($config['additional_headers'])) {
            foreach ($config['additional_headers'] as $name => $value) {
                $this->setHeader($name, $value);
            }
        }
        
        return $this;
    }
    
    /**
     * Build Content Security Policy string
     * 
     * @param array $config Security configuration
     * @return string CSP header value
     */
    private function buildCSP($config) {
        $csp_parts = [];
        
        // Build CSP directives
        $directives = [
            'default-src' => $config['csp_default_src'] ?? "'self'",
            'script-src' => $config['csp_script_src'] ?? "'self'",
            'style-src' => $config['csp_style_src'] ?? "'self'", 
            'img-src' => $config['csp_img_src'] ?? "'self'",
            'connect-src' => $config['csp_connect_src'] ?? "'self'",
            'font-src' => $config['csp_font_src'] ?? "'self'",
            'object-src' => $config['csp_object_src'] ?? "'none'",
            'media-src' => $config['csp_media_src'] ?? "'self'",
            'frame-src' => $config['csp_frame_src'] ?? "'none'",
            'form-action' => $config['csp_form_action'] ?? "'self'"
        ];
        
        // Add frame-ancestors based on context
        if (!empty($config['frame_ancestors'])) {
            $directives['frame-ancestors'] = $config['frame_ancestors'];
        }
        
        // Handle any CSP overrides from context
        if (!empty($config['csp_frame_ancestors'])) {
            $directives['frame-ancestors'] = $config['csp_frame_ancestors'];
        }
        
        // Build CSP string
        foreach ($directives as $directive => $value) {
            if (!empty($value)) {
                $csp_parts[] = $directive . ' ' . $value;
            }
        }
        
        // Add dynamic base origin
        $currentHost = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $protocol = isset($_SERVER['HTTPS']) ? 'https://' : 'http://';
        $baseOrigin = $protocol . $currentHost;
        
        return implode('; ', $csp_parts) . '; form-action \'self\' ' . $baseOrigin . ';';
    }
    
    /**
     * Set individual header with logging
     * 
     * @param string $name Header name
     * @param string $value Header value
     */
    private function setHeader($name, $value) {
        if (!headers_sent()) {
            header($name . ': ' . $value);
            $this->appliedHeaders[$name] = $value;
            
            // Log header application
            $this->logSecurityEvent('header_applied', [
                'header' => $name,
                'value' => $value,
                'context' => $this->currentContext,
                'script' => $_SERVER['SCRIPT_NAME'] ?? 'unknown'
            ]);
        }
    }
    
    /**
     * Get current security context
     * 
     * @return string Current context name
     */
    public function getCurrentContext() {
        return $this->currentContext;
    }
    
    /**
     * Get applied headers
     * 
     * @return array Applied headers
     */
    public function getAppliedHeaders() {
        return $this->appliedHeaders;
    }
    
    /**
     * Log security events for audit trail
     * 
     * @param string $event_type Type of security event
     * @param array $details Event details
     */
    private function logSecurityEvent($event_type, $details = []) {
        global $SECURITY_CONFIG;
        
        $audit_entry = [
            'timestamp' => date('Y-m-d H:i:s'),
            'event_type' => $event_type,
            'context' => $this->currentContext,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
            'script' => $_SERVER['SCRIPT_NAME'] ?? 'unknown',
            'details' => $details
        ];
        
        $this->auditLog[] = $audit_entry;
        
        // Log to error log if configured
        if (!empty($SECURITY_CONFIG['audit']['log_security_events'])) {
            error_log("SecurityManager: " . $event_type . " - Context: " . $this->currentContext . " - " . json_encode($details));
        }
        
        // TODO: Store in database audit table if configured
        // This would require database connection and should be implemented based on your DB setup
    }
    
    /**
     * Get audit log for current request
     * 
     * @return array Audit log entries
     */
    public function getAuditLog() {
        return $this->auditLog;
    }
    
    /**
     * Get security context information
     * 
     * @param string $context Context name (optional, uses current if not specified)
     * @return array Context information including rationale
     */
    public function getContextInfo($context = null) {
        global $SECURITY_CONFIG;
        
        if ($context === null) {
            $context = $this->currentContext;
        }
        
        if ($context === 'default') {
            return [
                'name' => 'default',
                'description' => 'Default restrictive security policy',
                'rationale' => 'Maximum security protection for general pages'
            ];
        }
        
        return $SECURITY_CONFIG['contexts'][$context] ?? null;
    }
    
    /**
     * Validate if current script should use specific context
     * 
     * @param string $context Context to validate
     * @return bool Whether context is appropriate for current script
     */
    public function validateContextForScript($context) {
        $script_name = basename($_SERVER['SCRIPT_NAME'] ?? '');
        $context_info = $this->getContextInfo($context);
        
        if (!$context_info || !isset($context_info['applies_to'])) {
            return false;
        }
        
        foreach ($context_info['applies_to'] as $applicable_script) {
            if (strpos($applicable_script, $script_name) !== false || 
                strpos($script_name, $applicable_script) !== false) {
                return true;
            }
        }
        
        return false;
    }
}

/**
 * Convenience function to get SecurityManager instance
 * 
 * @return SecurityManager
 */
function getSecurityManager() {
    return SecurityManager::getInstance();
}

/**
 * Convenience function to set security context
 * 
 * @param string $context Security context
 * @param string $reason Reason for context change
 * @return SecurityManager
 */
function setSecurityContext($context, $reason = '') {
    return SecurityManager::getInstance()->setContext($context, $reason);
}

/**
 * Convenience function to apply security headers
 * 
 * @param string $context Optional context to set before applying headers
 * @param string $reason Reason for context (if setting context)
 * @return SecurityManager
 */
function applySecurityHeaders($context = null, $reason = '') {
    $manager = SecurityManager::getInstance();
    
    if ($context !== null) {
        $manager->setContext($context, $reason);
    }
    
    return $manager->applyHeaders();
}
?>