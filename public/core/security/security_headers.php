<?php
/**
 * Security Headers - Legacy Compatibility Layer
 * 
 * This file now uses the centralized Security Manager system for consistent
 * and context-aware security header management. The new system provides:
 * 
 * - Context-aware security policies (default, pdf_viewer, etc.)
 * - Environment-specific configurations 
 * - Centralized management in security_config.php
 * - Audit logging of security context changes
 * - Secure defaults with controlled exceptions
 * 
 * Previous behavior (hardcoded headers) is maintained for compatibility
 * but now managed through the centralized configuration system.
 */

require_once(__DIR__ . '/security_manager.php');

// Determine appropriate security context based on current script
$script_name = basename($_SERVER['SCRIPT_NAME'] ?? '');
$context = 'default';
$reason = 'Legacy security_headers.php inclusion';

// Auto-detect context based on script name patterns
if (strpos($script_name, 'login') !== false) {
    $context = 'login';
    $reason = 'Login page detected';
} elseif (strpos($script_name, 'pdf') !== false || strpos($script_name, 'view_') !== false) {
    // Don't auto-set pdf_viewer context here - let specific scripts control this
    // This prevents unintended context changes
    $context = 'default';
    $reason = 'PDF-related script detected but using default context';
}

// Apply security headers using the new system
try {
    applySecurityHeaders($context, $reason);
} catch (Exception $e) {
    // Fallback to basic security headers if the new system fails
    error_log("SecurityManager failed, falling back to basic headers: " . $e->getMessage());
    
    // Basic fallback headers
    if (!headers_sent()) {
        header("X-Frame-Options: DENY");
        header("X-XSS-Protection: 1; mode=block");
        header("X-Content-Type-Options: nosniff");
        
        // Basic CSP
        $currentHost = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $protocol = isset($_SERVER['HTTPS']) ? 'https://' : 'http://';
        $baseOrigin = $protocol . $currentHost;
        
        header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; connect-src 'self'; font-src 'self'; object-src 'none'; media-src 'self'; frame-src 'none'; frame-ancestors 'none'; form-action 'self' " . $baseOrigin . ";");
        header("Referrer-Policy: strict-origin-when-cross-origin");
    }
}

/**
 * Legacy documentation preserved for reference:
 * 
 * The headers that were previously set here are now managed through the
 * centralized security configuration system which provides:
 * 
 * X-Frame-Options: Context-aware (DENY by default, SAMEORIGIN for PDF viewers)
 * X-XSS-Protection: 1; mode=block (consistent across contexts)
 * X-Content-Type-Options: nosniff (consistent across contexts) 
 * Content-Security-Policy: Dynamic with context-specific frame-ancestors
 * Referrer-Policy: strict-origin-when-cross-origin (consistent)
 * Strict-Transport-Security: Environment-specific (disabled in dev, enabled in prod)
 * Permissions-Policy: Environment-specific (enabled in production)
 * 
 * The new system maintains the same security posture while adding flexibility
 * for legitimate use cases like PDF modal viewing.
 */
?>