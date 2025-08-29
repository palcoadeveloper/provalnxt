<?php
session_start();

// Validate session timeout
require_once('../../security/session_timeout_middleware.php');
validateActiveSession();
require_once('../../config/config.php');
require_once('../../config/db.class.php');

header('Content-Type: application/json');

// Verify user is logged in
if (!isset($_SESSION['user_name'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'User not authenticated']);
    exit;
}

// Validate required parameters
if (!isset($_GET['test_id']) || !is_numeric($_GET['test_id'])) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid test ID']);
    exit;
}

$test_id = intval($_GET['test_id']);
$val_wf_id = $_GET['val_wf_id'] ?? '';
$test_val_wf_id = $_GET['test_val_wf_id'] ?? '';

// Validate workflow IDs are provided
if (empty($val_wf_id) || empty($test_val_wf_id)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Validation workflow ID and test workflow ID are required']);
    exit;
}

try {
    // Get active template data with test name and template version
    $active_template = DB::queryFirstRow("
        SELECT rt.*, t.test_name, u.user_name as uploaded_by_name 
        FROM raw_data_templates rt 
        LEFT JOIN tests t ON rt.test_id = t.test_id 
        LEFT JOIN users u ON rt.created_by = u.user_id 
        WHERE rt.test_id = %d AND rt.is_active = 1
    ", $test_id);

    if (!$active_template) {
        echo json_encode(['status' => 'error', 'message' => 'No active template found']);
        exit;
    }

    // Get download count for this specific workflow (Test ID + Val WF + Test WF)
    $workflow_specific_pattern = '%Test ID ' . $test_id . '%Val WF: ' . $val_wf_id . '%Test WF: ' . $test_val_wf_id . '%';
    $total_download_count = DB::queryFirstField("
        SELECT COUNT(*) FROM log 
        WHERE change_type = 'template_download' 
        AND change_description LIKE %s
    ", $workflow_specific_pattern);

    // Get download history for this specific workflow only
    $download_history = DB::query("
        SELECT 
            l.change_datetime, 
            u.user_name,
            l.change_description
        FROM log l 
        JOIN users u ON l.change_by = u.user_id 
        WHERE l.change_type = 'template_download' 
        AND l.change_description LIKE %s 
        ORDER BY l.change_datetime DESC 
        LIMIT 20
    ", $workflow_specific_pattern);

    // Calculate template version based on chronological order of templates for this test
    $version_count = DB::queryFirstField("
        SELECT COUNT(*) FROM raw_data_templates 
        WHERE test_id = %d AND created_at <= %s
        ORDER BY created_at ASC
    ", $test_id, $active_template['created_at']);
    
    // Version starts from 1.0, 2.0, 3.0, etc.
    $template_version = number_format($version_count, 1);

    // Return the data
    echo json_encode([
        'status' => 'success',
        'data' => [
            'template_id' => $active_template['id'],
            'test_name' => $active_template['test_name'],
            'template_version' => $template_version,
            'effective_date' => date('d.m.Y', strtotime($active_template['effective_date'])),
            'uploaded_by' => $active_template['uploaded_by_name'],
            'uploaded_date' => date('d.m.Y', strtotime($active_template['created_at'])),
            'val_wf_id' => $val_wf_id,
            'test_val_wf_id' => $test_val_wf_id,
            'total_download_count' => intval($total_download_count),
            'download_history' => $download_history
        ]
    ]);

} catch (Exception $e) {
    error_log("Get download history error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'An error occurred while fetching download history']);
}
?>