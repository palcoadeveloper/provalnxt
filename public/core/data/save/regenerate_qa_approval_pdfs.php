<?php
/**
 * QA Approval PDF Regeneration API
 * 
 * This endpoint is called during QA approval process to regenerate
 * test certificate PDFs with QA approval details section added.
 * 
 * Conditions:
 * - test_wf_current_stage = 3A (QA approval stage)
 * - paper_on_glass_enabled = 'Yes'
 * - data_entry_mode = 'online'
 */

// Load configuration and security
require_once(__DIR__ . '/../../config/config.php');
require_once(__DIR__ . '/../../security/session_timeout_middleware.php');
validateActiveSession();
require_once(__DIR__ . '/../../config/db.class.php');

// Security headers
header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');

// Validate request method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit();
}

// Validate CSRF token
if (!isset($_POST['csrf_token']) || !isset($_SESSION['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    http_response_code(403);
    echo json_encode(['error' => 'Invalid CSRF token']);
    exit();
}

try {
    // Validate required parameters
    if (!isset($_POST['test_wf_id']) || empty($_POST['test_wf_id'])) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'test_wf_id parameter is required'
        ]);
        exit();
    }
    
    // Sanitize parameters
    $testWfId = htmlspecialchars(trim($_POST['test_wf_id']), ENT_QUOTES, 'UTF-8');
    
    // Log the regeneration attempt
    error_log("QA Approval PDF Regeneration API called for test_wf_id: $testWfId by user: " . $_SESSION['user_name']);
    
    // Step 1: Check if conditions are met for QA PDF regeneration
    $testConditions = DB::queryFirstRow("
        SELECT ts.test_wf_current_stage, t.paper_on_glass_enabled, ts.data_entry_mode
        FROM tbl_test_schedules_tracking ts
        LEFT JOIN tests t ON t.test_id = ts.test_id
        WHERE ts.test_wf_id = %s
    ", $testWfId);
    
    if (!$testConditions) {
        echo json_encode([
            'success' => false,
            'error' => 'Test record not found'
        ]);
        exit();
    }
    
    // Check if conditions are met: stage 3A + paper on glass + online mode
    if ($testConditions['test_wf_current_stage'] != '3A' || 
        $testConditions['paper_on_glass_enabled'] != 'Yes' || 
        $testConditions['data_entry_mode'] != 'online') {
        
        echo json_encode([
            'success' => false,
            'error' => 'QA PDF regeneration conditions not met',
            'details' => 'Stage must be 3A, paper on glass enabled, and data entry mode online'
        ]);
        exit();
    }
    
    // Step 2: Get approved test certificate files
    $approvedFiles = DB::query("
        SELECT upload_id, upload_path_test_certificate
        FROM tbl_uploads 
        WHERE test_wf_id = %s 
          AND upload_action = 'Approved'
          AND upload_path_test_certificate IS NOT NULL 
          AND upload_path_test_certificate != ''
    ", $testWfId);
    
    if (empty($approvedFiles)) {
        echo json_encode([
            'success' => false,
            'error' => 'No approved test certificate files found for regeneration'
        ]);
        exit();
    }
    
    // Step 3: Include ACPH PDF generator for regeneration functionality
    require_once(__DIR__ . '/../../pdf/acph_pdf_generator.php');
    
    // Prepare QA approval details
    $qaApprovalDetails = [
        'test_wf_id' => $testWfId,
        'approved_by' => $_SESSION['user_name'] ?? 'Unknown QA User',
        'approved_by_id' => $_SESSION['user_id'] ?? null,
        'approved_on' => date('Y-m-d H:i:s'),
        'department' => 'Quality Assurance',
        'company' => 'Cipla Ltd.'
    ];
    
    $regenerationResults = [];
    $totalFiles = count($approvedFiles);
    $successCount = 0;
    
    foreach ($approvedFiles as $file) {
        error_log("Regenerating QA approval PDF for upload_id: {$file['upload_id']}");
        
        $regenerationResult = regenerateTestCertificateWithQAApproval($testWfId, $qaApprovalDetails, $file);
        
        if ($regenerationResult['success']) {
            // Update database with new file path
            DB::update('tbl_uploads', 
                ['upload_path_test_certificate' => $regenerationResult['upload_path']], 
                'upload_id=%i', 
                $file['upload_id']
            );
            $regenerationResults[$file['upload_id']] = true;
            $successCount++;
            error_log("SUCCESS: QA approval PDF regenerated for upload_id: {$file['upload_id']} - {$regenerationResult['filename']}");
        } else {
            $regenerationResults[$file['upload_id']] = false;
            error_log("ERROR: Failed to regenerate QA approval PDF for upload_id: {$file['upload_id']} - {$regenerationResult['error']}");
        }
    }
    
    // Step 4: Log the regeneration activity
    DB::insert('log', [
        'change_type' => 'qa_approval_pdf_regeneration',
        'table_name' => 'tbl_uploads',
        'change_description' => "QA approval PDFs regenerated for test_wf_id: $testWfId. $successCount of $totalFiles files regenerated successfully. QA: " . ($_SESSION['user_name'] ?? 'N/A'),
        'change_by' => $_SESSION['user_id'] ?? 0,
        'unit_id' => $_SESSION['unit_id'] ?? null
    ]);
    
    if ($successCount == $totalFiles) {
        error_log("QA Approval PDF regeneration completed successfully for test_wf_id: $testWfId - all $totalFiles files regenerated");
        echo json_encode([
            'success' => true,
            'message' => "Successfully regenerated $totalFiles test certificate(s) with QA approval details",
            'files_processed' => $totalFiles,
            'test_wf_id' => $testWfId
        ]);
    } elseif ($successCount > 0) {
        error_log("QA Approval PDF regeneration partially completed for test_wf_id: $testWfId - $successCount of $totalFiles files regenerated");
        echo json_encode([
            'success' => false,
            'error' => "Only $successCount of $totalFiles files were successfully regenerated",
            'files_processed' => $successCount,
            'total_files' => $totalFiles
        ]);
    } else {
        error_log("QA Approval PDF regeneration failed for test_wf_id: $testWfId - no files regenerated");
        echo json_encode([
            'success' => false,
            'error' => 'Failed to regenerate any test certificate files',
            'files_processed' => 0,
            'total_files' => $totalFiles
        ]);
    }
    
} catch (Exception $e) {
    error_log("QA Approval PDF Regeneration API Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Server error during QA approval PDF regeneration',
        'details' => $e->getMessage()
    ]);
}
?>