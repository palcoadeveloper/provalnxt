<?php
/**
 * Centralized Security Configuration
 * 
 * This file defines security policies and contexts for the application.
 * Security contexts allow controlled exceptions to default security policies
 * for specific use cases while maintaining overall security.
 */

if (!defined('SECURITY_CONFIG_LOADED')) {
    define('SECURITY_CONFIG_LOADED', true);

    // Global security configuration
    $GLOBALS['SECURITY_CONFIG'] = [
        
        // Default security settings - most restrictive
        'default' => [
            'x_frame_options' => 'DENY',
            'frame_ancestors' => "'none'",
            'xss_protection' => '1; mode=block',
            'content_type_options' => 'nosniff',
            'referrer_policy' => 'strict-origin-when-cross-origin',
            'hsts_enabled' => false, // Can be enabled per environment
            'hsts_max_age' => 31536000,
            'hsts_include_subdomains' => true,
            'hsts_preload' => true,
            'csp_enabled' => true,
            'csp_default_src' => "'self'",
            'csp_script_src' => "'self' 'unsafe-inline'",
            'csp_style_src' => "'self' 'unsafe-inline'",
            'csp_img_src' => "'self' data:",
            'csp_connect_src' => "'self'",
            'csp_font_src' => "'self'",
            'csp_object_src' => "'none'",
            'csp_media_src' => "'self'",
            'csp_frame_src' => "'none'",
            'csp_form_action' => "'self'",
            'permissions_policy_enabled' => false
        ],

        // Security contexts for specific use cases
        'contexts' => [
            
            // PDF viewer context - allows embedding in modals
            'pdf_viewer' => [
                'x_frame_options' => 'SAMEORIGIN',
                'frame_ancestors' => "'self'",
                'description' => 'PDF content for modal viewing',
                'applies_to' => ['view_pdf_with_footer.php', 'PDF viewer endpoints'],
                'rationale' => 'Required for PDF display in modal windows within same origin'
            ],

            // Iframe content context - for content meant to be embedded
            'iframe_content' => [
                'x_frame_options' => 'SAMEORIGIN', 
                'frame_ancestors' => "'self'",
                'description' => 'Content designed to be embedded in iframes',
                'applies_to' => ['Template viewers', 'Report generators'],
                'rationale' => 'Allows embedding while maintaining same-origin security'
            ],

            // API endpoints context - modified CSP for AJAX
            'api_endpoint' => [
                'csp_frame_ancestors' => "'none'", // APIs shouldn't be framed
                'description' => 'API endpoints with specific CSP requirements',
                'applies_to' => ['AJAX endpoints', 'API calls'],
                'rationale' => 'APIs have different CSP requirements than pages'
            ],

            // Login context - enhanced security for authentication
            'login' => [
                'x_frame_options' => 'DENY',
                'frame_ancestors' => "'none'",
                'csp_form_action' => "'self'",
                'description' => 'Enhanced security for login/authentication pages',
                'applies_to' => ['login.php', 'authentication endpoints'],
                'rationale' => 'Login pages require maximum protection against clickjacking'
            ]
        ],

        // Environment-specific overrides
        'environments' => [
            'development' => [
                'hsts_enabled' => false,
                'csp_script_src' => "'self' 'unsafe-inline' 'unsafe-eval'", // Allow eval for dev tools
                'additional_headers' => [
                    'X-Development-Mode' => 'enabled'
                ]
            ],
            'staging' => [
                'hsts_enabled' => true,
                'hsts_max_age' => 86400 // 1 day for staging
            ],
            'production' => [
                'hsts_enabled' => true,
                'hsts_max_age' => 31536000, // 1 year
                'hsts_include_subdomains' => true,
                'hsts_preload' => true,
                'permissions_policy_enabled' => true
            ]
        ],

        // Security audit configuration
        'audit' => [
            'log_context_changes' => true,
            'log_header_overrides' => true,
            'log_security_events' => true,
            'audit_table' => 'security_audit_log'
        ]
    ];

    /**
     * Get security configuration for a specific context
     * 
     * @param string $context The security context (default, pdf_viewer, etc.)
     * @param string $environment Current environment (development, staging, production)
     * @return array Merged security configuration
     */
    function getSecurityConfig($context = 'default', $environment = null) {
        global $SECURITY_CONFIG;
        
        if (!$environment) {
            $environment = defined('ENVIRONMENT') ? ENVIRONMENT : 'development';
        }
        
        // Start with default configuration
        $config = $SECURITY_CONFIG['default'];
        
        // Apply context-specific overrides
        if ($context !== 'default' && isset($SECURITY_CONFIG['contexts'][$context])) {
            $contextConfig = $SECURITY_CONFIG['contexts'][$context];
            foreach ($contextConfig as $key => $value) {
                if ($key !== 'description' && $key !== 'applies_to' && $key !== 'rationale') {
                    $config[$key] = $value;
                }
            }
        }
        
        // Apply environment-specific overrides
        if (isset($SECURITY_CONFIG['environments'][$environment])) {
            $envConfig = $SECURITY_CONFIG['environments'][$environment];
            foreach ($envConfig as $key => $value) {
                $config[$key] = $value;
            }
        }
        
        return $config;
    }

    /**
     * Get list of available security contexts
     * 
     * @return array List of available contexts with descriptions
     */
    function getAvailableSecurityContexts() {
        global $SECURITY_CONFIG;
        
        $contexts = ['default' => 'Default restrictive security policy'];
        
        foreach ($SECURITY_CONFIG['contexts'] as $name => $context) {
            $contexts[$name] = $context['description'] ?? $name;
        }
        
        return $contexts;
    }
}
?>