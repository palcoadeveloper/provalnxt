<?php
// Include config first to get environment settings
require_once(__DIR__ . '/../config/config.php');

// Set PDF viewer security context before any output
setSecurityContext('pdf_viewer', 'PDF viewing in modal window');

// Start output buffering to catch any potential output
ob_start();

// Session is already started by config.php via session_init.php
// Validate session timeout
require_once(__DIR__ . '/../security/session_timeout_middleware.php');
validateActiveSession();
require_once(__DIR__ . '/../config/db.class.php');
if(!isset($_SESSION['user_name']))
{
    header('Location: ' . BASE_URL . 'login.php');
    exit;
}

date_default_timezone_set("Asia/Kolkata");

// Include composer packages
use setasign\Fpdi\Fpdi;
use setasign\Fpdi\PdfReader;

require_once(__DIR__ . '/../../vendor/setasign/fpdf/fpdf.php');
require_once(__DIR__ . '/../../vendor/autoload.php');

// Custom FPDI class with footer
class FPDIWithFooter extends FPDI
{
    public $footer_text;
    
    public function setFooterText($f_text) {
        $this->footer_text = $f_text;
    }

    public function Header()
    {
        // No header
    }

    public function Footer()
    {
        $this->SetFont('Times','',10);
        
        // Get the width of the footer text
        $footer_width = $this->GetStringWidth($this->footer_text);
        
        // Calculate X position for center alignment
        $page_width = $this->GetPageWidth();
        $x = ($page_width - $footer_width) / 2;
        
        // Set position and write the text
        $this->SetXY($x, -15);
        $this->Write(8, $this->footer_text);
    }
}

// Clear any output that might have been generated
ob_clean();

// Validate input parameters
if (!isset($_GET['pdf_path']) || empty($_GET['pdf_path'])) {
    die('PDF path is required');
}

// Get the PDF path
$pdf_path = $_GET['pdf_path'];

// Sanitize the PDF path to prevent directory traversal
$pdf_path = str_replace(['../', '../', '..\\', '..\\\\'], '', $pdf_path);

// Handle the path correctly - convert to absolute path from document root  
if (strpos($pdf_path, 'uploads/') === 0) {
    // Direct uploads path
    $full_path = __DIR__ . '/../../' . $pdf_path;
} else if (strpos($pdf_path, 'core/uploads/') === 0) {
    // Core uploads path
    $full_path = __DIR__ . '/../../' . $pdf_path;
} else {
    // Default to uploads directory
    $full_path = __DIR__ . '/../../uploads/' . basename($pdf_path);
}

// Log the path resolution for debugging
error_log("PDF viewer - Original path: " . $_GET['pdf_path'] . ", Resolved path: " . $full_path);

// Check if file exists
if (!file_exists($full_path)) {
    error_log("PDF file not found: " . $full_path);
    die('PDF file not found: ' . basename($pdf_path) . '. Please check if the file exists and try again.');
}

// Get user details for the footer
$requester_details = DB::queryFirstRow("SELECT user_name, department_name, unit_name, u.unit_id
FROM users u 
LEFT JOIN departments d ON u.department_id=d.department_id
LEFT JOIN units un ON u.unit_id=un.unit_id 
WHERE user_id=%i", $_SESSION['user_id']);

// Log the view action
DB::insert('log', [
    'change_type' => 'tran_view_schedule',
    'table_name' => '',
    'change_description' => 'User '.$requester_details['user_name']. ' viewed schedule PDF: '.$pdf_path,
    'change_by' => $_SESSION['user_id'],
    'unit_id' => $requester_details['unit_id'] 
]);

// Create PDF with footer
$pdf = new FPDIWithFooter();

// Set footer text
$text = ucwords('Document printed by '.$requester_details['user_name']." ".$requester_details['department_name']."-".$requester_details['unit_name']."/GOA ".date("d.m.Y H:i:s"));
$pdf->setFooterText($text);

// Process the PDF
try {
    $pageCount = $pdf->setSourceFile($full_path);
    
    for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {
        // Import a page
        $templateId = $pdf->importPage($pageNo);
        
        // Get the size of the imported page
        $size = $pdf->getTemplateSize($templateId);

        // Create a page (landscape or portrait depending on the imported page size)
        if ($size[0] > $size[1]) {
            $pdf->AddPage('L', array($size[0], $size[1]));
        } else {
            $pdf->AddPage('P', array($size[0], $size[1]));
        }

        // Use the imported page
        $pdf->useTemplate($templateId);
    }
    
    // Clear any output buffers
    while (ob_get_level()) {
        ob_end_clean();
    }
    
    // Apply security headers for PDF viewer context
    // The security context was set at the beginning of the script
    getSecurityManager()->applyHeaders(true); // Force application even after output started
    
    // Output the PDF directly to the browser
    $pdf->Output('I', basename($pdf_path));
} catch (Exception $e) {
    // Clear any output buffers
    while (ob_get_level()) {
        ob_end_clean();
    }
    error_log('PDF processing error for file ' . $full_path . ': ' . $e->getMessage());
    die('Error processing PDF: ' . $e->getMessage() . '. Please check if the PDF file is valid.');
}
