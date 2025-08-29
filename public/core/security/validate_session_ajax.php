<?php
// AJAX Session Validation Endpoint
// Validates if current user session is still active and valid

// Start session and include required security middleware
session_start();

// Include security middleware and configuration
require_once __DIR__ . '/../config/security_headers.php';
require_once __DIR__ . '/../security/session_timeout_middleware.php';
require_once __DIR__ . '/../config/config.php';

// Set JSON response headers
header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

try {
    // Validate CSRF token for security
    if (!isset($_POST['csrf_token'])) {
        http_response_code(400);
        echo json_encode([
            'status' => 'error',
            'message' => 'CSRF token required',
            'session_valid' => false
        ]);
        exit;
    }

    if (!isset($_SESSION['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        http_response_code(403);
        echo json_encode([
            'status' => 'error',
            'message' => 'Invalid CSRF token',
            'session_valid' => false
        ]);
        exit;
    }

    // Use existing session validation middleware
    $sessionResult = validateActiveSession();
    
    if ($sessionResult['valid']) {
        // Session is valid and active
        echo json_encode([
            'status' => 'success',
            'message' => 'Session is valid',
            'session_valid' => true,
            'user_id' => $_SESSION['logged_in_user'] ?? null,
            'session_timeout' => $_SESSION['session_timeout'] ?? null
        ]);
    } else {
        // Session is invalid or expired
        http_response_code(401);
        echo json_encode([
            'status' => 'error',
            'message' => $sessionResult['message'] ?? 'Session expired or invalid',
            'session_valid' => false,
            'redirect_url' => BASE_URL . 'login.php'
        ]);
    }

} catch (Exception $e) {
    // Handle any errors gracefully
    error_log("Session validation error: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Session validation failed',
        'session_valid' => false
    ]);
}
?>