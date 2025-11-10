<?php
require_once('../../config/config.php');

// Validate session timeout
require_once('../../security/session_timeout_middleware.php');
validateActiveSession();

// Use centralized session validation
require_once('../../security/session_validation.php');
validateUserSession();

require_once("../../config/db.class.php");

// Set content type to JSON
header('Content-Type: application/json');

// Additional security validation - validate user type
$userType = $_SESSION['logged_in_user'] ?? '';
if (!in_array($userType, ['employee', 'vendor'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized access']);
    exit();
}

// Only allow GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit();
}

try {
    // Get input parameters
    $test_val_wf_id = $_GET['test_val_wf_id'] ?? '';

    // Validate required parameters
    if (empty($test_val_wf_id)) {
        throw new InvalidArgumentException("Missing required parameter: test_val_wf_id");
    }

    // Get test information to determine if validation is needed
    $test_info = DB::queryFirstRow("
        SELECT
            ts.test_wf_current_stage,
            t.paper_on_glass_enabled,
            t.test_performed_by
        FROM tbl_test_schedules_tracking ts
        INNER JOIN tests t ON t.test_id = ts.test_id
        WHERE ts.test_wf_id = %s
    ", $test_val_wf_id);

    if (!$test_info) {
        throw new Exception("Test workflow not found: " . $test_val_wf_id);
    }

    // Check if validation applies:
    // 1. Paper-on-glass is NOT enabled
    // 2. Current stage is 1 (New Task) OR 3B (Engineering Rejected)
    // 3. Test type is External
    $is_paper_on_glass = ($test_info['paper_on_glass_enabled'] ?? 'No') === 'Yes';
    $is_stage_1_or_3b = ($test_info['test_wf_current_stage'] === '1' || $test_info['test_wf_current_stage'] === '3B');
    $is_external = strtolower($test_info['test_performed_by'] ?? '') === 'external';

    // If validation doesn't apply, return success
    if ($is_paper_on_glass || !$is_stage_1_or_3b || !$is_external) {
        echo json_encode([
            'status' => 'success',
            'isValid' => true,
            'message' => 'Validation not applicable - skipped',
            'debug' => [
                'is_paper_on_glass' => $is_paper_on_glass,
                'is_stage_1_or_3b' => $is_stage_1_or_3b,
                'current_stage' => $test_info['test_wf_current_stage'],
                'is_external' => $is_external
            ]
        ]);
        exit();
    }

    // Validation applies - check for required file uploads
    // Look for the most recent upload record that is neither approved nor rejected
    $uploaded_files = DB::queryFirstRow("
        SELECT
            upload_path_raw_data,
            upload_path_test_certificate,
            upload_path_master_certificate
        FROM tbl_uploads
        WHERE test_wf_id = %s
        AND upload_action IS NULL
        AND upload_status = 'Active'
        ORDER BY uploaded_datetime DESC
        LIMIT 1
    ", $test_val_wf_id);

    $missing_files = [];

    // Check each required file
    if (!$uploaded_files || empty($uploaded_files['upload_path_raw_data'])) {
        $missing_files[] = 'Raw Data';
    }

    if (!$uploaded_files || empty($uploaded_files['upload_path_test_certificate'])) {
        $missing_files[] = 'Test Certificate';
    }

    if (!$uploaded_files || empty($uploaded_files['upload_path_master_certificate'])) {
        $missing_files[] = 'Master Certificate';
    }

    // Return validation result
    if (!empty($missing_files)) {
        $message = "For external tests, the following files must be uploaded before submission: " . implode(', ', $missing_files);

        echo json_encode([
            'status' => 'error',
            'isValid' => false,
            'message' => $message,
            'missing_files' => $missing_files
        ]);
    } else {
        echo json_encode([
            'status' => 'success',
            'isValid' => true,
            'message' => 'All required files are uploaded'
        ]);
    }

} catch (InvalidArgumentException $e) {
    error_log("Validation error in validatenon_paper_on_glass_files.php: " . $e->getMessage());
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'isValid' => false,
        'message' => $e->getMessage()
    ]);
} catch (Exception $e) {
    error_log("Database error in validatenon_paper_on_glass_files.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'isValid' => false,
        'message' => 'Failed to validate files. Please try again.'
    ]);
}
?>
