<?php
// Load configuration first
require_once(__DIR__ . '/../../config/config.php');

// Session is already started by config.php via session_init.php

// Validate session timeout
require_once(__DIR__ . '/../../security/session_timeout_middleware.php');
validateActiveSession();

// Include rate limiting
require_once(__DIR__ . '/../../security/rate_limiting_utils.php');

require_once(__DIR__ . '/../../config/db.class.php');

// Apply rate limiting for API requests
if (!RateLimiter::checkRateLimit('api_request')) {
    http_response_code(429);
    echo json_encode([
        'status' => 'error',
        'message' => 'Rate limit exceeded. Too many requests.',
        'csrf_token' => generateCSRFToken()
    ]);
    exit();
}

// Validate CSRF token for POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !isset($_SESSION['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        http_response_code(403);
        echo json_encode([
            'status' => 'error',
            'message' => 'Invalid CSRF token',
            'csrf_token' => generateCSRFToken()
        ]);
        exit();
    }
}

/**
 * Validate uploads for offline paper-on-glass tests
 * @param string $test_wf_id Test workflow ID
 * @return array Validation result with status and message
 */
function validateTestUploads($test_wf_id) {
    try {
        // Get test data
        $test_data = DB::queryFirstRow(
            "SELECT test_wf_current_stage, data_entry_mode, test_id FROM tbl_test_schedules_tracking
            WHERE test_wf_id = %s",
            $test_wf_id
        );

        if (!$test_data) {
            return [
                'status' => 'error',
                'message' => 'Test not found'
            ];
        }

        // Check if test is in correct stage
        if ($test_data['test_wf_current_stage'] !== '1RRV') {
            return [
                'status' => 'error',
                'message' => 'Test is not in resubmittable stage'
            ];
        }

        // Only validate uploads for offline mode
        if ($test_data['data_entry_mode'] !== 'offline') {
            return [
                'status' => 'success',
                'message' => 'Upload validation not required for online mode'
            ];
        }

        // Check if paper-on-glass is enabled
        $paper_on_glass_check = DB::queryFirstRow(
            "SELECT paper_on_glass_enabled FROM tests WHERE test_id = %i",
            $test_data['test_id']
        );

        if (!$paper_on_glass_check || $paper_on_glass_check['paper_on_glass_enabled'] !== 'Yes') {
            return [
                'status' => 'success',
                'message' => 'Upload validation not required - paper-on-glass not enabled'
            ];
        }

        // Validate that required uploads exist with NULL action (pending approval)
        $required_uploads = DB::queryFirstRow("
            SELECT upload_path_raw_data, upload_path_test_certificate, upload_path_master_certificate
            FROM tbl_uploads
            WHERE test_wf_id = %s
            AND upload_action IS NULL
            AND upload_status = 'Active'
            ORDER BY uploaded_datetime DESC
            LIMIT 1
        ", $test_wf_id);

        if (!$required_uploads ||
            empty($required_uploads['upload_path_raw_data']) ||
            empty($required_uploads['upload_path_test_certificate']) ||
            empty($required_uploads['upload_path_master_certificate'])) {

            return [
                'status' => 'error',
                'message' => 'No uploaded documents found for offline test. Please upload Raw Data, Certificate and Master Certificate files before submitting.'
            ];
        }

        return [
            'status' => 'success',
            'message' => 'Upload validation passed'
        ];

    } catch (Exception $e) {
        error_log("Upload validation error: " . $e->getMessage());
        return [
            'status' => 'error',
            'message' => 'Validation failed due to system error'
        ];
    }
}

try {
    // Get test workflow ID from request
    $test_wf_id = '';
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $test_wf_id = isset($_POST['test_wf_id']) ? trim($_POST['test_wf_id']) : '';
    } else {
        $test_wf_id = isset($_GET['test_wf_id']) ? trim($_GET['test_wf_id']) : '';
    }

    if (empty($test_wf_id)) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Missing test workflow ID',
            'csrf_token' => generateCSRFToken()
        ]);
        exit();
    }

    // XSS detection on workflow ID
    require_once(__DIR__ . '/../../security/xss_prevention_utils.php');
    if (XSSPrevention::detectXSS($test_wf_id)) {
        XSSPrevention::logXSSAttempt($test_wf_id, 'upload_validation');
        echo json_encode([
            'status' => 'error',
            'message' => 'Invalid input detected',
            'csrf_token' => generateCSRFToken()
        ]);
        exit();
    }

    // Perform validation
    $validation_result = validateTestUploads($test_wf_id);

    // Add CSRF token to response
    $validation_result['csrf_token'] = generateCSRFToken();

    // Log validation attempt for security
    error_log("[UPLOAD VALIDATION] Test: $test_wf_id, Result: " . $validation_result['status'] . ", User: " . ($_SESSION['user_id'] ?? 'unknown'));

    echo json_encode($validation_result);

} catch (Exception $e) {
    error_log("Upload validation endpoint error: " . $e->getMessage());
    echo json_encode([
        'status' => 'error',
        'message' => 'System error occurred',
        'csrf_token' => generateCSRFToken()
    ]);
}
?>