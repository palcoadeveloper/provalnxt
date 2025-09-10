<?php
/**
 * Enhanced PDF Regeneration API for Upload Document Approval
 * 
 * This endpoint is called during the document approval process to automatically
 * regenerate PDFs with witness details when specific conditions are met.
 * 
 * Conditions:
 * - test_wf_current_stage = 2 (document approval stage)
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
    
    // Sanitize test_wf_id (keep as string)
    $testWfId = htmlspecialchars(trim($_POST['test_wf_id']), ENT_QUOTES, 'UTF-8');
    
    // Log the regeneration attempt
    error_log("PDF Regeneration API called for test_wf_id: $testWfId by user: " . $_SESSION['user_name']);
    
    // Include the PDF regeneration functions
    require_once(__DIR__ . '/regenerate_witness_pdfs.php');
    
    // Check if conditions are met for PDF regeneration
    if (!shouldRegeneratePDFs($testWfId)) {
        echo json_encode([
            'success' => false,
            'error' => 'PDF regeneration conditions not met',
            'details' => 'Either test stage is not 2, paper on glass is not enabled, or data entry mode is not online'
        ]);
        exit();
    }
    
    // Prepare witness details from session
    $witnessDetails = [
        'test_wf_id' => $testWfId,
        'name' => $_SESSION['user_name'] ?? 'Unknown',
        'employee_id' => $_SESSION['employee_id'] ?? $_SESSION['user_id'] ?? 'N/A',
        'department' => $_SESSION['department_name'] ?? 'Unknown Department',
        'designation' => $_SESSION['designation'] ?? 'Approver',
        'approval_timestamp' => date('Y-m-d H:i:s')
    ];
    
    // Attempt PDF regeneration
    $regenerationResult = regeneratePDFsWithWitness($testWfId, $witnessDetails);
    
    if ($regenerationResult) {
        error_log("PDF Regeneration SUCCESS for test_wf_id: $testWfId");
        echo json_encode([
            'success' => true,
            'message' => 'PDFs regenerated successfully with witness details',
            'test_wf_id' => $testWfId,
            'witness' => $_SESSION['user_name']
        ]);
    } else {
        error_log("PDF Regeneration FAILED for test_wf_id: $testWfId");
        echo json_encode([
            'success' => false,
            'error' => 'PDF regeneration failed',
            'details' => 'Check server error logs for specific details'
        ]);
    }
    
} catch (Exception $e) {
    error_log("PDF Regeneration API Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Server error during PDF regeneration',
        'details' => $e->getMessage()
    ]);
}
?>