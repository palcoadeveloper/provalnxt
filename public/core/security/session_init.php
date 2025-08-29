<?php
// Set secure session cookie parameters before starting session
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$hostname = parse_url('http://' . $host, PHP_URL_HOST) ?: 'localhost';

// For IP addresses or localhost, don't set domain (let browser handle it)
$domain = (filter_var($hostname, FILTER_VALIDATE_IP) || $hostname === 'localhost') ? '' : $hostname;

// Extract path from BASE_URL if defined, otherwise use default
$sessionPath = '/';
if (defined('BASE_URL')) {
    $parsedUrl = parse_url(BASE_URL);
    $sessionPath = $parsedUrl['path'] ?? '/';
    // Ensure path ends with slash for session cookie
    $sessionPath = rtrim($sessionPath, '/') . '/';
}

$cookieParams = [
    'lifetime' => 0,
    'path' => $sessionPath,
    'domain' => $domain,
    'secure' => isset($_SERVER['HTTPS']), // Only send over HTTPS if available
    'httponly' => true, // Prevent JavaScript access
    'samesite' => 'Lax' // Allow same-site form submissions
];

error_log("Session cookie params: " . print_r($cookieParams, true));
session_set_cookie_params($cookieParams);

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Initialize session activity tracking for new sessions
if (!isset($_SESSION['last_activity'])) {
    $_SESSION['last_activity'] = time();
}
?> 