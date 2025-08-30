<?php
// Security middleware to enforce HTTPS and security headers
// This file should be included at the start of every PHP file

// Function to check if the request is secure
function isSecureRequest() {
    return (
        (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
        $_SERVER['SERVER_PORT'] == HTTPS_PORT ||
        (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https') ||
        (!empty($_SERVER['HTTP_X_FORWARDED_SSL']) && $_SERVER['HTTP_X_FORWARDED_SSL'] == 'on')
    );
}

// Function to get the current URL
function getCurrentUrl() {
    $protocol = isSecureRequest() ? 'https' : 'http';
    return $protocol . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
}

// Function to redirect to HTTPS
function redirectToHttps() {
    // For API/AJAX requests, return JSON error
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
        strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
        header('Content-Type: application/json');
        echo json_encode([
            'status' => 'error',
            'message' => 'security_error',
            'type' => 'https_required',
            'redirect' => getCurrentUrl()
        ]);
        exit();
    }
    
    // For regular requests, redirect to the HTTPS required page
    $httpsRequiredUrl = BASE_URL . 'https_required.php';
    header('Location: ' . $httpsRequiredUrl);
    exit();
}

// Function to set security headers
function setSecurityHeaders() {
    // Skip headers if running in CLI mode
    if (php_sapi_name() === 'cli' || defined('CLI_MODE')) {
        return;
    }
    
    if (ENABLE_SECURITY_HEADERS) {
        // HSTS header
        $hstsHeader = 'max-age=' . HSTS_MAX_AGE;
        if (HSTS_INCLUDE_SUBDOMAINS) {
            $hstsHeader .= '; includeSubDomains';
        }
        if (HSTS_PRELOAD) {
            $hstsHeader .= '; preload';
        }
        header("Strict-Transport-Security: " . $hstsHeader);
        
        // Other security headers
        header("X-Content-Type-Options: nosniff");
        header("X-Frame-Options: SAMEORIGIN");
        header("X-XSS-Protection: 1; mode=block");
        
        // Enhanced Content Security Policy for ProVal HVAC
        $cspPolicy = "default-src 'self'; " .
                    "script-src 'self' 'unsafe-inline' 'unsafe-eval' cdn.jsdelivr.net cdnjs.cloudflare.com; " .
                    "style-src 'self' 'unsafe-inline' cdn.jsdelivr.net cdnjs.cloudflare.com fonts.googleapis.com; " .
                    "font-src 'self' fonts.googleapis.com fonts.gstatic.com data:; " .
                    "img-src 'self' data: blob:; " .
                    "connect-src 'self'; " .
                    "frame-src 'self'; " .
                    "frame-ancestors 'self'; " .
                    "base-uri 'self'; " .
                    "form-action 'self'; " .
                    "upgrade-insecure-requests;";
        
        header("Content-Security-Policy: " . $cspPolicy);
        header("Referrer-Policy: strict-origin-when-cross-origin");
        header("Permissions-Policy: geolocation=(), microphone=(), camera=(), payment=(), usb=()");
    }
}

// Get the current script name
$currentScript = basename($_SERVER['SCRIPT_NAME']);

// Check if HTTPS is required and enforce it
if (FORCE_HTTPS && php_sapi_name() !== 'cli' && !defined('CLI_MODE')) {
    // For all requests, enforce HTTPS
    if (!isSecureRequest()) {
        // For API/AJAX requests, return JSON error
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
            strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
            header('Content-Type: application/json');
            echo json_encode([
                'status' => 'error',
                'message' => 'security_error',
                'type' => 'https_required',
                'redirect' => getCurrentUrl()
            ]);
            exit();
        }
        
        // For regular requests, redirect to HTTPS required page
        redirectToHttps();
    }
}

// Set security headers
setSecurityHeaders(); 