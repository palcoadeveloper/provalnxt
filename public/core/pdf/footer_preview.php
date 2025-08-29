<?php
/**
 * Footer Preview Handler
 * 
 * This endpoint generates a preview of what the PDF footer will contain
 * for a specific template without actually generating the PDF.
 */

session_start();

// Validate session timeout
require_once('../security/session_timeout_middleware.php');
validateActiveSession();
require_once('../config/config.php');
require_once('../config/db.class.php');
require_once('../pdf/pdf_footer_service.php');

// Set JSON response header
header('Content-Type: application/json');

// Verify user is logged in
if (!isset($_SESSION['user_name'])) {
    echo json_encode(['status' => 'error', 'message' => 'User not authenticated']);
    exit;
}

// Validate template ID
if (!isset($_GET['template_id']) || !is_numeric($_GET['template_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid template ID']);
    exit;
}

$template_id = intval($_GET['template_id']);

try {
    // Initialize PDF footer service
    $pdf_service = new PDFFooterService();
    
    // Get template metadata
    $footer_data = $pdf_service->getTemplateMetadata($template_id, $_SESSION['user_id']);
    
    if (empty($footer_data)) {
        echo json_encode(['status' => 'error', 'message' => 'Template not found or metadata unavailable']);
        exit;
    }
    
    // Use reflection to access the private generateFooterHTML method
    $reflection = new ReflectionClass($pdf_service);
    $method = $reflection->getMethod('generateFooterHTML');
    $method->setAccessible(true);
    
    // Generate footer HTML
    $footer_html = $method->invoke($pdf_service, $footer_data);
    
    // Return success response with footer HTML
    echo json_encode([
        'status' => 'success',
        'footer_html' => $footer_html,
        'metadata' => $footer_data,
        'message' => 'Footer preview generated successfully'
    ]);
    
} catch (Exception $e) {
    error_log("Footer preview error: " . $e->getMessage());
    echo json_encode([
        'status' => 'error', 
        'message' => 'An error occurred while generating footer preview: ' . $e->getMessage()
    ]);
}
?>