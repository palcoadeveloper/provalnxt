<?php
// Include config first to get environment settings
require_once(__DIR__ . '/../config/config.php');

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

// Custom FPDI class with template footer
class FPDIWithTemplateFooter extends FPDI
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
        $this->SetFont('Arial','',8);
        
        // Split footer into multiple lines if too long
        $lines = $this->splitFooterText($this->footer_text);
        
        $y_position = -20; // Start position from bottom
        foreach ($lines as $line) {
            // Get the width of the footer text
            $footer_width = $this->GetStringWidth($line);
            
            // Calculate X position for center alignment
            $page_width = $this->GetPageWidth();
            $x = ($page_width - $footer_width) / 2;
            
            // Set position and write the text
            $this->SetXY($x, $y_position);
            $this->Write(6, $line);
            
            $y_position += 5; // Move down for next line
        }
    }
    
    private function splitFooterText($text) {
        // Split long footer text into multiple lines
        $max_length = 120; // Approximate characters per line
        
        if (strlen($text) <= $max_length) {
            return [$text];
        }
        
        // Split by pipe separator for workflow info
        $parts = explode(' | ', $text);
        $lines = [];
        $current_line = '';
        
        foreach ($parts as $part) {
            if (strlen($current_line . $part) <= $max_length) {
                $current_line = $current_line ? $current_line . ' | ' . $part : $part;
            } else {
                if ($current_line) {
                    $lines[] = $current_line;
                }
                $current_line = $part;
            }
        }
        
        if ($current_line) {
            $lines[] = $current_line;
        }
        
        return $lines;
    }
}

// Clear any output that might have been generated
ob_clean();

// Validate input parameters
if (!isset($_GET['template_id']) || !is_numeric($_GET['template_id'])) {
    die('Template ID is required');
}

$template_id = intval($_GET['template_id']);
$val_wf_id = $_GET['val_wf_id'] ?? '';
$test_val_wf_id = $_GET['test_val_wf_id'] ?? '';

// Get template details
$template = DB::queryFirstRow("
    SELECT rt.*, t.test_name 
    FROM raw_data_templates rt 
    LEFT JOIN tests t ON rt.test_id = t.test_id 
    WHERE rt.id = %d", $template_id);

if (!$template) {
    die('Template not found');
}

// Check if file exists
if (!file_exists($template['file_path'])) {
    die('Template file not found: ' . $template['file_path']);
}

// Get user details for the footer
$requester_details = DB::queryFirstRow("SELECT user_name, department_name, unit_name, u.unit_id
FROM users u 
LEFT JOIN departments d ON u.department_id=d.department_id
LEFT JOIN units un ON u.unit_id=un.unit_id 
WHERE user_id=%i", $_SESSION['user_id']);

// Get download count for this specific workflow
$workflow_download_count = DB::queryFirstField("SELECT COUNT(*) FROM log 
    WHERE change_type = 'template_download' 
    AND change_description LIKE %s", '%Test ID ' . $template['test_id'] . '%');

// Log the view action
DB::insert('log', [
    'change_type' => 'template_view',
    'table_name' => 'raw_data_templates',
    'change_description' => 'Template viewed: Test ID '.$template['test_id'].' (Template ID: '.$template_id.') by '.$requester_details['user_name'],
    'change_by' => $_SESSION['user_id'],
    'unit_id' => $requester_details['unit_id'] 
]);

// Create PDF with footer
$pdf = new FPDIWithTemplateFooter();

// Build comprehensive footer text with workflow information
$footer_parts = [];

// Basic info - only include department if it exists
$user_info = 'Template downloaded by ' . $requester_details['user_name'];
if (!empty($requester_details['department_name'])) {
    $user_info .= ' (' . $requester_details['department_name'] . ')';
}
$footer_parts[] = $user_info;

// Workflow IDs if available
if (!empty($val_wf_id)) {
    $footer_parts[] = 'Validation WF: ' . $val_wf_id;
}
if (!empty($test_val_wf_id)) {
    $footer_parts[] = 'Test WF: ' . $test_val_wf_id;
}

// Count and timestamp
$footer_parts[] = 'Download #' . $workflow_download_count;
$footer_parts[] = date("d.m.Y H:i:s");

// Template info
$footer_parts[] = 'ProVal 4.0 - Test ID: ' . $template['test_id'];

$footer_text = implode(' | ', $footer_parts);
$pdf->setFooterText($footer_text);

// Process the PDF
try {
    $pageCount = $pdf->setSourceFile($template['file_path']);
    
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
    
    // Output the PDF directly to the browser for inline viewing
    $filename = 'template_' . $template['test_id'] . '_' . date('Y-m-d_H-i-s') . '.pdf';
    $pdf->Output('I', $filename);
} catch (Exception $e) {
    // Clear any output buffers
    while (ob_get_level()) {
        ob_end_clean();
    }
    die('Error processing PDF: ' . $e->getMessage());
}
?>