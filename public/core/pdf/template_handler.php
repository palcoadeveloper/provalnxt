<?php
session_start();

// Validate session timeout
require_once('../security/session_timeout_middleware.php');
validateActiveSession();
require_once('../config/config.php');
require_once('../config/db.class.php');
require_once('../validation/misc_php_functions.php');
require_once('../pdf/pdf_footer_service.php');

// Get the action to determine if we need special CSP handling
$action = $_GET['action'] ?? $_POST['action'] ?? '';

// For view action, we need to allow iframe embedding by relaxing CSP
if ($action === 'view') {
    // Override the CSP policy set by security_middleware.php for PDF viewing
    $relaxed_csp = "default-src 'self'; " .
                   "script-src 'self' 'unsafe-inline' 'unsafe-eval' cdn.jsdelivr.net cdnjs.cloudflare.com; " .
                   "style-src 'self' 'unsafe-inline' cdn.jsdelivr.net cdnjs.cloudflare.com fonts.googleapis.com; " .
                   "font-src 'self' fonts.googleapis.com fonts.gstatic.com data:; " .
                   "img-src 'self' data: blob:; " .
                   "connect-src 'self'; " .
                   "frame-src 'self' data: blob:; " .
                   "frame-ancestors 'self'; " .
                   "object-src 'self'; " .
                   "media-src 'self' data: blob:; " .
                   "base-uri 'self'; " .
                   "form-action 'self';";
    
    header("Content-Security-Policy: " . $relaxed_csp);
    header("X-Frame-Options: SAMEORIGIN");
}

// Action already extracted above for CSP handling

// Only set JSON response header for non-view actions
if ($action !== 'view') {
    header('Content-Type: application/json');
}

// Verify user is logged in
if (!isset($_SESSION['user_name'])) {
    if ($action === 'view') {
        http_response_code(401);
        echo 'User not authenticated';
    } else {
        echo json_encode(['status' => 'error', 'message' => 'User not authenticated']);
    }
    exit;
}

// Ensure user_id is valid for database operations
if (!isset($_SESSION['user_id']) || !is_numeric($_SESSION['user_id'])) {
    if ($action === 'view') {
        http_response_code(401);
        echo 'Invalid user session';
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Invalid user session. Please log in again.']);
    }
    exit;
}

// Verify CSRF token for state-changing operations (not needed for view action)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action !== 'view') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid CSRF token']);
        exit;
    }
}

// Template storage directory
$template_dir = __DIR__ . '/../uploads/templates/';

// Create templates directory if it doesn't exist
if (!is_dir($template_dir)) {
    if (!mkdir($template_dir, 0755, true)) {
        echo json_encode(['status' => 'error', 'message' => 'Failed to create templates directory']);
        exit;
    }
}

// Action already extracted above

try {
    switch ($action) {
        case 'upload':
            handleTemplateUpload();
            break;
            
        case 'download':
            handleTemplateDownload();
            break;
            
        case 'view':
            handleTemplateView();
            break;
            
        case 'activate':
            handleTemplateActivation();
            break;
            
        case 'deactivate':
            handleTemplateDeactivation();
            break;
            
        default:
            echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
            break;
    }
} catch (Exception $e) {
    error_log("Template handler error: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'An unexpected error occurred']);
}

function handleTemplateUpload() {
    global $template_dir;
    
    
    // Validate required fields
    if (!isset($_POST['test_id']) || !is_numeric($_POST['test_id'])) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid test ID']);
        return;
    }
    
    if (!isset($_POST['effective_date'])) {
        echo json_encode(['status' => 'error', 'message' => 'Effective date is required']);
        return;
    }
    
    // Validate file upload
    if (!isset($_FILES['template_file']) || $_FILES['template_file']['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(['status' => 'error', 'message' => 'No file uploaded or upload error']);
        return;
    }
    
    $file = $_FILES['template_file'];
    $test_id = intval($_POST['test_id']);
    $effective_date = $_POST['effective_date'];
    
    // Validate file type
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime_type = $finfo->file($file['tmp_name']);
    
    if ($mime_type !== 'application/pdf') {
        echo json_encode(['status' => 'error', 'message' => 'Only PDF files are allowed']);
        return;
    }
    
    // Validate file size (10MB max)
    if ($file['size'] > 10 * 1024 * 1024) {
        echo json_encode(['status' => 'error', 'message' => 'File size must be less than 10MB']);
        return;
    }
    
    // Validate effective date
    if (!strtotime($effective_date)) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid effective date']);
        return;
    }
    
    // Check if test exists
    $test_exists = DB::queryFirstField("SELECT COUNT(*) FROM tests WHERE test_id = %d", $test_id);
    if (!$test_exists) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid test ID']);
        return;
    }
    
    // Generate secure filename
    $file_extension = 'pdf';
    $timestamp = time();
    $secure_filename = "test_{$test_id}_template_{$timestamp}.{$file_extension}";
    $file_path = $template_dir . $secure_filename;
    
    // Move uploaded file
    if (!move_uploaded_file($file['tmp_name'], $file_path)) {
        echo json_encode(['status' => 'error', 'message' => 'Failed to save uploaded file']);
        return;
    }
    
    // Begin database transaction
    DB::startTransaction();
    
    try {
        // Check current state
        $active_template = DB::queryFirstRow("SELECT id FROM raw_data_templates WHERE test_id = %d AND is_active = 1", $test_id);
        
        // Check if there's a currently active template
        if ($active_template) {
            // Deactivate the current active template and set effective_till_date
            DB::query("UPDATE raw_data_templates SET is_active = 0, effective_till_date = %s WHERE id = %d", 
                $effective_date, $active_template['id']);
            
            // Log the automatic deactivation of previous active template
            $unit_id = !empty($_SESSION['unit_id']) ? $_SESSION['unit_id'] : 0;
            DB::insert('log', [
                'change_type' => 'template_auto_deactivation',
                'table_name' => 'raw_data_templates',
                'change_description' => "Raw data template auto-deactivated: Test ID {$test_id} (Template ID: {$active_template['id']}) - Due to new template upload",
                'change_by' => $_SESSION['user_id'],
                'unit_id' => $unit_id
            ]);
        }
        
        // Always insert a new template record to maintain complete history
        DB::insert('raw_data_templates', [
            'test_id' => $test_id,
            'file_path' => $file_path,
            'effective_date' => $effective_date,
            'is_active' => 1,
            'created_by' => $_SESSION['user_id'],
            'created_at' => DB::sqleval('NOW()'),
            'download_count' => 0
        ]);
        $template_id = DB::insertId();
        
        // Log the upload action
        $unit_id = !empty($_SESSION['unit_id']) ? $_SESSION['unit_id'] : 0;
        DB::insert('log', [
            'change_type' => 'template_upload',
            'table_name' => 'raw_data_templates',
            'change_description' => "Raw data template uploaded for Test ID: {$test_id} (Template ID: {$template_id}, Effective Date: {$effective_date}, Status: Active)",
            'change_by' => $_SESSION['user_id'],
            'unit_id' => $unit_id
        ]);
        
        DB::commit();
        
        echo json_encode([
            'status' => 'success', 
            'message' => 'Template uploaded successfully and set as active',
            'template_id' => $template_id
        ]);
        
    } catch (Exception $e) {
        DB::rollback();
        
        // Clean up uploaded file on database error
        if (file_exists($file_path)) {
            unlink($file_path);
        }
        
        error_log("Template upload database error: " . $e->getMessage());
        echo json_encode(['status' => 'error', 'message' => 'Database error occurred during upload']);
    }
}

function handleTemplateDownload() {
    if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Invalid template ID']);
        return;
    }
    
    $template_id = intval($_GET['id']);
    $val_wf_id = $_GET['val_wf_id'] ?? '';
    $test_val_wf_id = $_GET['test_val_wf_id'] ?? '';
    $increment_only = isset($_GET['increment_only']) && $_GET['increment_only'] == '1';
    
    // Get template details
    $template = DB::queryFirstRow("
        SELECT rt.*, t.test_name 
        FROM raw_data_templates rt 
        LEFT JOIN tests t ON rt.test_id = t.test_id 
        WHERE rt.id = %d", $template_id);
    
    if (!$template) {
        http_response_code(404);
        echo json_encode(['status' => 'error', 'message' => 'Template not found']);
        return;
    }
    
    // Check if file exists
    if (!file_exists($template['file_path'])) {
        http_response_code(404);
        echo json_encode(['status' => 'error', 'message' => 'Template file not found']);
        return;
    }
    
    // Increment download counter
    DB::query("UPDATE raw_data_templates SET download_count = download_count + 1 WHERE id = %d", $template_id);
    
    // Get download count for this specific workflow (before adding current download)
    $workflow_download_count = 1;
    if (!empty($val_wf_id) && !empty($test_val_wf_id)) {
        $workflow_specific_pattern = '%Test ID ' . $template['test_id'] . '%Val WF: ' . $val_wf_id . '%Test WF: ' . $test_val_wf_id . '%';
        $workflow_download_count = DB::queryFirstField("SELECT COUNT(*) + 1 FROM log 
            WHERE change_type = 'template_download' 
            AND change_description LIKE %s", $workflow_specific_pattern);
    }
    
    // Log the download with workflow information
    $unit_id = !empty($_SESSION['unit_id']) ? $_SESSION['unit_id'] : 0;
    $log_description = "Raw data template downloaded: Test ID {$template['test_id']} (Template ID: {$template_id})";
    if (!empty($val_wf_id) && !empty($test_val_wf_id)) {
        $log_description .= " - Val WF: {$val_wf_id}, Test WF: {$test_val_wf_id}";
    }
    
    DB::insert('log', [
        'change_type' => 'template_download',
        'table_name' => 'raw_data_templates',
        'change_description' => $log_description,
        'change_by' => $_SESSION['user_id'],
        'unit_id' => $unit_id
    ]);
    
    // If increment_only is true, just return success without file download
    if ($increment_only) {
        echo json_encode(['status' => 'success', 'message' => 'Download count incremented']);
        return;
    }
    
    // Generate PDF with dynamic footer
    try {
        error_log("Starting PDF generation for template ID: {$template_id}");
        
        $pdf_service = new PDFFooterService();
        
        // Get template metadata for footer with workflow information
        if (!empty($val_wf_id) && !empty($test_val_wf_id)) {
            $footer_data = $pdf_service->getTemplateMetadataForWorkflow($template_id, $_SESSION['user_id'], $val_wf_id, $test_val_wf_id, $workflow_download_count);
        } else {
            $footer_data = $pdf_service->getTemplateMetadata($template_id, $_SESSION['user_id']);
        }
        
        // Generate PDF with footer (includes retry logic and validation)
        $pdf_content = $pdf_service->generatePDFWithFooter($template['file_path'], $footer_data);
        
        if ($pdf_content === false || empty($pdf_content)) {
            throw new Exception("PDF generation returned empty or false result");
        }
        
        // Final validation before serving
        $final_validation = $pdf_service->validatePDFContent($pdf_content);
        if (!$final_validation['valid']) {
            throw new Exception("Final PDF validation failed: " . implode(', ', $final_validation['errors']));
        }
        
        error_log("PDF generation successful for template ID: {$template_id}, size: " . strlen($pdf_content) . " bytes");
        
        // Set headers for file download
        $display_name = "template_test_{$template['test_id']}_" . date('Y-m-d', strtotime($template['effective_date'])) . "_v" . ($footer_data['template_version'] ?? '1.0') . ".pdf";
        
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $display_name . '"');
        header('Content-Length: ' . strlen($pdf_content));
        header('Cache-Control: no-cache, must-revalidate');
        header('Pragma: no-cache');
        
        // Output PDF content with footer
        echo $pdf_content;
        exit;
        
    } catch (Exception $e) {
        // Enhanced fallback with validation
        error_log("PDF Footer generation failed for template ID {$template_id}: " . $e->getMessage());
        error_log("Falling back to original template file: " . $template['file_path']);
        
        // Validate original template before serving as fallback
        $pdf_service = new PDFFooterService();
        $original_validation = $pdf_service->validatePDFIntegrity($template['file_path'], false);
        
        if (!$original_validation['valid']) {
            error_log("Original template file is also corrupted: " . implode(', ', $original_validation['errors']));
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'Template file is corrupted and cannot be served']);
            return;
        }
        
        // Set headers for file download (fallback)
        $display_name = "template_test_{$template['test_id']}_" . date('Y-m-d', strtotime($template['effective_date'])) . ".pdf";
        
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $display_name . '"');
        header('Content-Length: ' . filesize($template['file_path']));
        header('Cache-Control: no-cache, must-revalidate');
        header('Pragma: no-cache');
        
        error_log("Serving original template file as fallback for template ID: {$template_id}");
        
        // Output original file
        readfile($template['file_path']);
        exit;
    }
}

function handleTemplateView() {
    if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
        http_response_code(400);
        echo 'Invalid template ID';
        return;
    }
    
    $template_id = intval($_GET['id']);
    $val_wf_id = $_GET['val_wf_id'] ?? '';
    $test_val_wf_id = $_GET['test_val_wf_id'] ?? '';
    
    // Get template details
    $template = DB::queryFirstRow("
        SELECT rt.*, t.test_name 
        FROM raw_data_templates rt 
        LEFT JOIN tests t ON rt.test_id = t.test_id 
        WHERE rt.id = %d", $template_id);
    
    if (!$template) {
        http_response_code(404);
        echo 'Template not found';
        return;
    }
    
    // Check if file exists
    if (!file_exists($template['file_path'])) {
        http_response_code(404);
        echo 'Template file not found';
        return;
    }
    
    // For viewing in modal, we'll generate PDF with footer if workflow IDs are provided
    $debug = isset($_GET['debug']) && $_GET['debug'] == '1';
    error_log("Template view debug - val_wf_id: $val_wf_id, test_val_wf_id: $test_val_wf_id");
    
    if ($debug) {
        echo "<h3>Debug Information</h3>";
        echo "<p>Template ID: $template_id</p>";
        echo "<p>Val WF ID: $val_wf_id</p>";
        echo "<p>Test Val WF ID: $test_val_wf_id</p>";
        echo "<p>Template path: " . $template['file_path'] . "</p>";
        echo "<p>File exists: " . (file_exists($template['file_path']) ? 'Yes' : 'No') . "</p>";
    }
    
    if (!empty($val_wf_id) && !empty($test_val_wf_id)) {
        error_log("Template view: Generating PDF with footer for template_id: $template_id");
        
        if ($debug) {
            echo "<p>Attempting to generate PDF with footer...</p>";
        }
        
        try {
            $pdf_service = new PDFFooterService();
            
            // Get current download count for this workflow (for viewing, don't increment)
            $workflow_specific_pattern = '%Test ID ' . $template['test_id'] . '%Val WF: ' . $val_wf_id . '%Test WF: ' . $test_val_wf_id . '%';
            $workflow_download_count = DB::queryFirstField("SELECT COUNT(*) FROM log 
                WHERE change_type = 'template_download' 
                AND change_description LIKE %s", $workflow_specific_pattern);
            
            if ($debug) {
                echo "<p>Download count: $workflow_download_count</p>";
            }
            
            // Get template metadata for footer with workflow information
            $footer_data = $pdf_service->getTemplateMetadataForWorkflow($template_id, $_SESSION['user_id'], $val_wf_id, $test_val_wf_id, $workflow_download_count);
            error_log("Template view: Footer data retrieved: " . json_encode($footer_data));
            
            if ($debug) {
                echo "<p>Footer data: " . json_encode($footer_data, JSON_PRETTY_PRINT) . "</p>";
            }
            
            // Generate PDF with footer (now includes retry logic and validation)
            $pdf_content = $pdf_service->generatePDFWithFooter($template['file_path'], $footer_data);
            
            if ($pdf_content === false || empty($pdf_content)) {
                throw new Exception("PDF generation returned empty or false result");
            }
            
            // Validate generated PDF for viewing
            $content_validation = $pdf_service->validatePDFContent($pdf_content);
            if (!$content_validation['valid']) {
                throw new Exception("Generated PDF validation failed for viewing: " . implode(', ', $content_validation['errors']));
            }
            
            error_log("Template view: PDF generation successful, size: " . strlen($pdf_content) . " bytes");
            
            if ($debug) {
                echo "<p>PDF generation: SUCCESS</p>";
                echo "<p>Generated PDF size: " . strlen($pdf_content) . " bytes</p>";
                echo "<h4>PDF Footer Generated Successfully!</h4>";
                echo "<p>The PDF should now contain footer with workflow information.</p>";
                return; // Don't output PDF in debug mode
            }
            
            header('Content-Type: application/pdf');
            header('Content-Disposition: inline; filename="' . basename($template['file_path']) . '"');
            header('Content-Length: ' . strlen($pdf_content));
            header('Cache-Control: no-cache, must-revalidate');
            header('Pragma: no-cache');
            header('X-Content-Type-Options: nosniff');
            header('Accept-Ranges: bytes');
            
            echo $pdf_content;
            exit;
            
        } catch (Exception $e) {
            error_log("PDF Footer generation failed for view: " . $e->getMessage());
            error_log("PDF Footer generation stack trace: " . $e->getTraceAsString());
            
            if ($debug) {
                echo "<p style='color: red;'>Exception: " . $e->getMessage() . "</p>";
                echo "<p style='color: orange;'>Falling back to original file</p>";
            }
            // Fall through to original file
        }
    } else {
        error_log("Template view: No workflow IDs provided, serving original file");
        if ($debug) {
            echo "<p style='color: orange;'>No workflow IDs provided, serving original file without footer</p>";
        }
    }
    
    // Set headers for inline PDF viewing (fallback)
    header('Content-Type: application/pdf');
    header('Content-Disposition: inline; filename="' . basename($template['file_path']) . '"');
    header('Content-Length: ' . filesize($template['file_path']));
    header('Cache-Control: no-cache, must-revalidate');
    header('Pragma: no-cache');
    header('X-Content-Type-Options: nosniff');
    header('Accept-Ranges: bytes');
    
    // Output the PDF file for inline viewing
    readfile($template['file_path']);
    exit;
}

function handleTemplateActivation() {
    if (!isset($_POST['template_id']) || !is_numeric($_POST['template_id'])) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid template ID']);
        return;
    }
    
    if (!isset($_POST['effective_from_date'])) {
        echo json_encode(['status' => 'error', 'message' => 'Effective from date is required']);
        return;
    }
    
    $template_id = intval($_POST['template_id']);
    $effective_from_date = $_POST['effective_from_date'];
    
    // Validate effective from date
    if (!strtotime($effective_from_date)) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid effective from date']);
        return;
    }
    
    // Ensure effective from date is not in the past (allow current date)
    if (strtotime($effective_from_date) < strtotime(date('Y-m-d'))) {
        echo json_encode(['status' => 'error', 'message' => 'Effective from date cannot be in the past']);
        return;
    }
    
    // Get template details
    $template = DB::queryFirstRow("SELECT * FROM raw_data_templates WHERE id = %d", $template_id);
    
    if (!$template) {
        echo json_encode(['status' => 'error', 'message' => 'Template not found']);
        return;
    }
    
    if ($template['is_active']) {
        echo json_encode(['status' => 'error', 'message' => 'Template is already active']);
        return;
    }
    
    // Begin database transaction
    DB::startTransaction();
    
    try {
        // Deactivate current active template for this test with effective_till_date
        // Calculate effective_till_date as one day before the new effective_from_date
        $effective_till_date = date('Y-m-d', strtotime($effective_from_date . ' -1 day'));
        
        // Get current active template info for logging
        $current_active = DB::queryFirstRow("SELECT id FROM raw_data_templates WHERE test_id = %d AND is_active = 1", $template['test_id']);
        
        DB::query("UPDATE raw_data_templates SET is_active = 0, effective_till_date = %s WHERE test_id = %d AND is_active = 1", 
                  $effective_till_date, $template['test_id']);
        
        // Log the automatic deactivation of previous template
        if ($current_active) {
            $unit_id = !empty($_SESSION['unit_id']) ? $_SESSION['unit_id'] : 0;
            DB::insert('log', [
                'change_type' => 'template_auto_deactivation',
                'table_name' => 'raw_data_templates',
                'change_description' => "Raw data template auto-deactivated: Test ID {$template['test_id']} (Template ID: {$current_active['id']}, Effective Till: {$effective_till_date}) - Due to new template activation",
                'change_by' => $_SESSION['user_id'],
                'unit_id' => $unit_id
            ]);
        }
        
        // Activate the selected template with new effective_date
        DB::query("UPDATE raw_data_templates SET is_active = 1, effective_date = %s WHERE id = %d", 
                  $effective_from_date, $template_id);
        
        // Log the activation
        $unit_id = !empty($_SESSION['unit_id']) ? $_SESSION['unit_id'] : 0;
        DB::insert('log', [
            'change_type' => 'template_activation',
            'table_name' => 'raw_data_templates',
            'change_description' => "Raw data template activated: Test ID {$template['test_id']} (Template ID: {$template_id}, Effective From: {$effective_from_date})",
            'change_by' => $_SESSION['user_id'],
            'unit_id' => $unit_id
        ]);
        
        DB::commit();
        
        echo json_encode([
            'status' => 'success', 
            'message' => 'Template activated successfully'
        ]);
        
    } catch (Exception $e) {
        DB::rollback();
        error_log("Template activation database error: " . $e->getMessage());
        echo json_encode(['status' => 'error', 'message' => 'Database error occurred during activation']);
    }
}

function handleTemplateDeactivation() {
    if (!isset($_POST['template_id']) || !is_numeric($_POST['template_id'])) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid template ID']);
        return;
    }
    
    if (!isset($_POST['effective_till_date'])) {
        echo json_encode(['status' => 'error', 'message' => 'Effective till date is required']);
        return;
    }
    
    $template_id = intval($_POST['template_id']);
    
    // Always use current system date for deactivation as per requirements
    $effective_till_date = date('Y-m-d');
    
    // Get template details
    $template = DB::queryFirstRow("SELECT * FROM raw_data_templates WHERE id = %d", $template_id);
    
    if (!$template) {
        echo json_encode(['status' => 'error', 'message' => 'Template not found']);
        return;
    }
    
    if (!$template['is_active']) {
        echo json_encode(['status' => 'error', 'message' => 'Template is already inactive']);
        return;
    }
    
    // Note: We allow deactivation of the only active template since user provides effective_till_date
    // This supports the business requirement to mark templates ineffective with specific dates
    
    // Begin database transaction
    DB::startTransaction();
    
    try {
        // Deactivate the selected template with effective till date
        DB::query("UPDATE raw_data_templates SET is_active = 0, effective_till_date = %s WHERE id = %d", $effective_till_date, $template_id);
        
        // Log the deactivation
        $unit_id = !empty($_SESSION['unit_id']) ? $_SESSION['unit_id'] : 0;
        DB::insert('log', [
            'change_type' => 'template_deactivation',
            'table_name' => 'raw_data_templates',
            'change_description' => "Raw data template deactivated: Test ID {$template['test_id']} (Template ID: {$template_id}, Effective Till: {$effective_till_date})",
            'change_by' => $_SESSION['user_id'],
            'unit_id' => $unit_id
        ]);
        
        DB::commit();
        
        echo json_encode([
            'status' => 'success', 
            'message' => 'Template deactivated successfully'
        ]);
        
    } catch (Exception $e) {
        DB::rollback();
        error_log("Template deactivation database error: " . $e->getMessage());
        echo json_encode(['status' => 'error', 'message' => 'Database error occurred during deactivation']);
    }
}

// Security function to sanitize file paths
function sanitizeFilePath($path) {
    // Remove any path traversal attempts
    $path = str_replace(['../', '.\\', '..\\'], '', $path);
    // Remove any null bytes
    $path = str_replace(chr(0), '', $path);
    return $path;
}

// Function to validate PDF file integrity
function validatePDFIntegrity($file_path) {
    $handle = fopen($file_path, 'rb');
    if (!$handle) {
        return false;
    }
    
    // Check PDF header
    $header = fread($handle, 5);
    fclose($handle);
    
    return strpos($header, '%PDF') === 0;
}
?>