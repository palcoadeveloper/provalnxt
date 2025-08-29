<?php
/**
 * PDF Footer Generation Service
 * 
 * This service handles dynamic footer generation for raw data templates,
 * adding workflow-specific information such as test IDs, validation IDs,
 * timestamps, and user information while maintaining professional appearance.
 */

// Include composer packages
use setasign\Fpdi\Fpdi;
use setasign\Fpdi\PdfReader;

require_once(__DIR__ . '/../../vendor/setasign/fpdf/fpdf.php');
require_once(__DIR__ . '/../../vendor/autoload.php');
require_once(__DIR__ . '/../config/db.class.php');

// Custom FPDI class with enhanced footer
class FPDIWithFooter extends FPDI
{
    public $footer_data;
    public $footer_config;
    
    public function setFooterData($data) {
        $this->footer_data = $data;
    }
    
    public function setFooterConfig($config) {
        $this->footer_config = $config;
    }

    public function Header()
    {
        // No header
    }

    public function Footer()
    {
        if (!$this->footer_data) {
            return;
        }
        
        // Extract footer data with defaults
        $test_id = $this->footer_data['test_id'] ?? 'N/A';
        $validation_workflow_id = $this->footer_data['validation_workflow_id'] ?? $this->footer_data['validation_id'] ?? '';
        $test_workflow_id = $this->footer_data['test_workflow_id'] ?? '';
        $template_version = $this->footer_data['template_version'] ?? '1.0';
        $downloaded_by = $this->footer_data['download_user_name'] ?? $this->footer_data['downloaded_by'] ?? 'Unknown User';
        $download_timestamp = $this->footer_data['download_datetime'] ?? $this->footer_data['download_timestamp'] ?? date('d.m.Y H:i:s');
        $download_count = $this->footer_data['download_count_workflow'] ?? 1;
        
        // Footer styling
        $font_size = $this->footer_config['font_size'] ?? 8;
        $border_width = 0.5;
        $padding = 3;
        
        // Get page dimensions
        $page_width = $this->GetPageWidth();
        $page_height = $this->GetPageHeight();
        
        // Calculate footer position and size with equal spacing
        $footer_height = 18; // Height for footer content
        $footer_y = $page_height - $padding - $footer_height; // Use same spacing as left/right
        $footer_x = $padding;
        $footer_width = $page_width - (2 * $padding);
        
        // Set font for footer
        $this->SetFont('Arial', '', $font_size);
        
        // Draw background box with borders
        $this->SetFillColor(248, 249, 250); // Light background #f8f9fa
        $this->SetDrawColor(221, 221, 221); // Border color #ddd
        $this->SetLineWidth($border_width);
        $this->Rect($footer_x, $footer_y, $footer_width, $footer_height, 'FD'); // Fill and Draw
        
        // Set text color
        $this->SetTextColor(51, 51, 51); // #333333
        
        // Calculate column widths (4 columns)
        $col_width = $footer_width / 4;
        $text_y = $footer_y + $padding;
        
        // Column 1: Template Info
        $this->SetXY($footer_x + $padding, $text_y);
        $this->SetFont('Arial', 'B', $font_size);
        $this->Cell($col_width - $padding, 3, 'Test ID:', 0, 1);
        $this->SetX($footer_x + $padding);
        $this->SetFont('Arial', '', $font_size);
        $this->Cell($col_width - $padding, 3, $test_id, 0, 1);
        $this->SetX($footer_x + $padding);
        $this->SetFont('Arial', 'B', $font_size);
        $this->Cell($col_width - $padding, 3, 'Version:', 0, 1);
        $this->SetX($footer_x + $padding);
        $this->SetFont('Arial', '', $font_size);
        $this->Cell($col_width - $padding, 3, $template_version, 0, 0);
        
        // Column 2: Workflow IDs
        $this->SetXY($footer_x + $col_width, $text_y);
        $this->SetFont('Arial', 'B', $font_size);
        $this->Cell($col_width - $padding, 3, 'Workflow IDs:', 0, 1);
        if ($validation_workflow_id) {
            $this->SetX($footer_x + $col_width);
            $this->SetFont('Arial', '', $font_size);
            $this->Cell($col_width - $padding, 3, 'Val WF: ' . $validation_workflow_id, 0, 1);
        }
        if ($test_workflow_id) {
            $this->SetX($footer_x + $col_width);
            $this->SetFont('Arial', '', $font_size);
            $this->Cell($col_width - $padding, 3, 'Test WF: ' . $test_workflow_id, 0, 0);
        }
        
        // Column 3: Download Info
        $this->SetXY($footer_x + (2 * $col_width)+($col_width/4), $text_y);
        $this->SetFont('Arial', 'B', $font_size);
        $this->Cell($col_width - $padding, 3, 'Download Info:', 0, 1);
        $this->SetX($footer_x + (2 * $col_width)+($col_width/4));
        $this->SetFont('Arial', '', $font_size);
        $this->Cell($col_width - $padding, 3, 'User: ' . $downloaded_by, 0, 1);
        $this->SetX($footer_x + (2 * $col_width)+($col_width/4));
        $this->Cell($col_width - $padding, 3, 'Count: ' . $download_count, 0, 0);
        
        // Column 4: Date & Time (right-aligned)
        $this->SetXY($footer_x + (3 * $col_width)+(1.5*$col_width/4), $text_y);
        $this->SetFont('Arial', 'B', $font_size);
        $this->Cell($col_width - $padding, 3, 'Date & Time:', 0, 1, 'L');
        $this->SetX($footer_x + (3 * $col_width)+(1.5*$col_width/4));
        $this->SetFont('Arial', '', $font_size);
        $this->Cell($col_width - $padding, 3, date('d.m.Y H:i:s', strtotime($download_timestamp)), 0, 1, 'L');
        $this->SetX($footer_x + (3 * $col_width)+(1.5*$col_width/4));
        $this->SetFont('Arial', '', $font_size - 1);
        $this->Cell($col_width - $padding, 3, 'Page ' . $this->PageNo() . ' of {nb}', 0, 0);
        
        // Center footer line
        $center_y = $footer_y + $footer_height - 4;
        $this->SetXY($footer_x, $center_y);
        $this->SetFont('Arial', 'I', $font_size - 1);
        $this->SetTextColor(102, 102, 102); // #666666
        $this->Cell($footer_width, 3, 'ProVal 4.0 - HVAC Validation Management System', 0, 0, 'C');
    }
}

class PDFFooterService {
    
    private $footer_config;
    
    public function __construct() {
        $this->footer_config = [
            'font_size' => 8,
            'font_family' => 'Arial',
            'line_height' => 1.2,
            'margin_bottom' => 3,
            'border_top' => true,
            'text_color' => '#333333',
            'background_color' => '#f8f9fa'
        ];
    }
    
    
    /**
     * Generate PDF with dynamic footer using FPDI
     * 
     * @param string $source_pdf_path Path to the source template PDF
     * @param array $footer_data Array containing footer information
     * @param string $output_path Optional output path (if null, returns PDF string)
     * @return string|bool PDF content as string or boolean success if output_path provided
     */
    public function generatePDFWithFooter($source_pdf_path, $footer_data, $output_path = null) {
        // Validate source PDF exists
        if (!file_exists($source_pdf_path)) {
            throw new Exception("Source PDF template not found: " . $source_pdf_path);
        }
        
        try {
            error_log("PDF Generation with FPDI for source: " . basename($source_pdf_path));
            
            // Create PDF with footer using FPDI
            $pdf = new FPDIWithFooter();
            
            // Set footer data and configuration
            $pdf->setFooterData($footer_data);
            $pdf->setFooterConfig($this->footer_config);
            
            // Set alias for page numbering
            $pdf->AliasNbPages('{nb}');
            
            // Process the source PDF
            $pageCount = $pdf->setSourceFile($source_pdf_path);
            
            for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {
                // Import the page
                $templateId = $pdf->importPage($pageNo);
                
                // Get the size of the imported page
                $size = $pdf->getTemplateSize($templateId);
                
                // Create a page with the same orientation and size as the imported page
                if ($size[0] > $size[1]) {
                    $pdf->AddPage('L', array($size[0], $size[1]));
                } else {
                    $pdf->AddPage('P', array($size[0], $size[1]));
                }
                
                // Use the imported page as template
                $pdf->useTemplate($templateId);
            }
            
            error_log("PDF Content Preservation: Successfully imported {$pageCount} pages with FPDI");
            
            // Generate PDF content
            $pdf_content = $pdf->Output('S');
            
            // Output or save the PDF
            if ($output_path) {
                return file_put_contents($output_path, $pdf_content) !== false;
            } else {
                return $pdf_content;
            }
            
        } catch (Exception $e) {
            error_log("PDF generation with FPDI error: " . $e->getMessage());
            throw $e;
        }
    }
    
    
    /**
     * Get template metadata for footer generation with workflow information
     * 
     * @param int $template_id Template ID
     * @param int $user_id User ID who is downloading
     * @param string $val_wf_id Validation Workflow ID
     * @param string $test_val_wf_id Test Workflow ID
     * @param int $download_count Download count for this workflow
     * @return array Footer data array
     */
    public function getTemplateMetadataForWorkflow($template_id, $user_id, $val_wf_id, $test_val_wf_id, $download_count = null) {
        try {
            // Get template details with test and user information
            $template_data = DB::queryFirstRow("
                SELECT 
                    rt.*, 
                    t.test_name, 
                    t.test_description,
                    u1.user_name as uploaded_by_name,
                    u2.user_name as downloaded_by_name
                FROM raw_data_templates rt 
                LEFT JOIN tests t ON rt.test_id = t.test_id 
                LEFT JOIN users u1 ON rt.created_by = u1.user_id 
                LEFT JOIN users u2 ON u2.user_id = %d
                WHERE rt.id = %d
            ", $user_id, $template_id);
            
            if (!$template_data) {
                throw new Exception("Template not found: " . $template_id);
            }
            
            // Get workflow-specific download count if not provided
            if ($download_count === null) {
                $workflow_specific_pattern = '%Test ID ' . $template_data['test_id'] . '%Val WF: ' . $val_wf_id . '%Test WF: ' . $test_val_wf_id . '%';
                $download_count = DB::queryFirstField("SELECT COUNT(*) + 1 FROM log 
                    WHERE change_type = 'template_download' 
                    AND change_description LIKE %s", $workflow_specific_pattern);
            }
            
            return [
                'template_id' => $template_data['id'],
                'test_id' => $template_data['test_id'],
                'test_name' => $template_data['test_name'],
                'test_description' => $template_data['test_description'],
                'validation_workflow_id' => $val_wf_id,
                'test_workflow_id' => $test_val_wf_id,
                'download_user_name' => $template_data['downloaded_by_name'],
                'download_datetime' => date('Y-m-d H:i:s'),
                'download_count_workflow' => $download_count,
                'effective_date' => $template_data['effective_date'],
                'uploaded_by' => $template_data['uploaded_by_name'],
                'created_at' => $template_data['created_at'],
                'template_version' => '1.0'
            ];
        } catch (Exception $e) {
            error_log("Error getting workflow template metadata: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Get template metadata for footer generation
     * 
     * @param int $template_id Template ID
     * @param int $user_id Current user ID
     * @return array Template metadata for footer
     */
    public function getTemplateMetadata($template_id, $user_id) {
        try {
            // Get template details with test and user information
            $template_data = DB::queryFirstRow("
                SELECT 
                    rt.*, 
                    t.test_name, 
                    t.test_description,
                    u1.user_name as uploaded_by_name,
                    u2.user_name as downloaded_by_name
                FROM raw_data_templates rt 
                LEFT JOIN tests t ON rt.test_id = t.test_id 
                LEFT JOIN users u1 ON rt.created_by = u1.user_id 
                LEFT JOIN users u2 ON u2.user_id = %d
                WHERE rt.id = %d
            ", $user_id, $template_id);
            
            if (!$template_data) {
                throw new Exception("Template not found with ID: " . $template_id);
            }
            
            // Check for associated validation workflow
            $validation_data = DB::queryFirstRow("
                SELECT val_wf_id, equip_id as equipment_id 
                FROM tbl_val_schedules 
                WHERE equip_id IN (
                    SELECT DISTINCT equipment_id 
                    FROM equipments 
                    LIMIT 1
                )
                ORDER BY val_wf_id DESC 
                LIMIT 1
            ");
            
            return [
                'test_id' => $template_data['test_id'],
                'test_name' => $template_data['test_name'],
                'validation_id' => $validation_data['val_wf_id'] ?? null,
                'template_version' => $this->calculateTemplateVersion($template_data['test_id'], $template_data['created_at']),
                'effective_date' => $template_data['effective_date'],
                'downloaded_by' => $template_data['downloaded_by_name'],
                'download_timestamp' => date('d.m.Y H:i:s'),
                'approval_status' => $this->determineApprovalStatus($template_data),
                'unique_identifier' => 'TPL-' . $template_data['test_id'] . '-' . date('Ymd-His')
            ];
            
        } catch (Exception $e) {
            error_log("Template metadata error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Calculate template version based on creation history
     * 
     * @param int $test_id Test ID
     * @param string $created_at Creation timestamp
     * @return string Version number (e.g., "1.0", "2.0")
     */
    private function calculateTemplateVersion($test_id, $created_at) {
        $version_count = DB::queryFirstField("
            SELECT COUNT(*) 
            FROM raw_data_templates 
            WHERE test_id = %d AND created_at <= %s
        ", $test_id, $created_at);
        
        return number_format($version_count, 1);
    }
    
    /**
     * Determine approval status based on template and workflow state
     * 
     * @param array $template_data Template data
     * @return string Approval status
     */
    private function determineApprovalStatus($template_data) {
        if ($template_data['is_active']) {
            return 'Active';
        } else {
            return 'Inactive';
        }
    }
    
    /**
     * Validate PDF file integrity with basic checks
     * 
     * @param string $file_path Path to PDF file
     * @param bool $check_content Whether to perform deep content validation (unused with FPDI)
     * @return array Validation result with details
     */
    public function validatePDFIntegrity($file_path, $check_content = true) {
        $result = [
            'valid' => false,
            'errors' => [],
            'file_size' => 0
        ];
        
        // Check if file exists
        if (!file_exists($file_path)) {
            $result['errors'][] = 'File does not exist';
            return $result;
        }
        
        $result['file_size'] = filesize($file_path);
        
        // Check minimum file size (PDF must be at least a few hundred bytes)
        if ($result['file_size'] < 200) {
            $result['errors'][] = 'File too small to be valid PDF';
            return $result;
        }
        
        // Check file permissions
        if (!is_readable($file_path)) {
            $result['errors'][] = 'File not readable';
            return $result;
        }
        
        $handle = fopen($file_path, 'rb');
        if (!$handle) {
            $result['errors'][] = 'Cannot open file';
            return $result;
        }
        
        // Check PDF header
        $header = fread($handle, 8);
        if (strpos($header, '%PDF') !== 0) {
            $result['errors'][] = 'Invalid PDF header';
            fclose($handle);
            return $result;
        }
        
        // Check for PDF trailer (basic structure validation)
        fseek($handle, -1024, SEEK_END);
        $trailer = fread($handle, 1024);
        if (strpos($trailer, '%%EOF') === false) {
            $result['errors'][] = 'Missing PDF trailer';
        }
        
        fclose($handle);
        
        // Basic content validation using FPDI
        if ($check_content && empty($result['errors'])) {
            try {
                $test_pdf = new FPDI();
                $pagecount = $test_pdf->setSourceFile($file_path);
                
                if ($pagecount < 1) {
                    $result['errors'][] = 'PDF has no readable pages';
                } else {
                    // Try to import first page to verify readability
                    $tplId = $test_pdf->importPage(1);
                    if (!$tplId) {
                        $result['errors'][] = 'Cannot import PDF page';
                    }
                }
                
                unset($test_pdf);
                
            } catch (Exception $e) {
                $result['errors'][] = 'PDF content validation failed: ' . $e->getMessage();
            }
        }
        
        $result['valid'] = empty($result['errors']);
        return $result;
    }
    
    /**
     * Validate PDF content from string
     * 
     * @param string $pdf_content PDF content as string
     * @return array Validation result with details
     */
    public function validatePDFContent($pdf_content) {
        $result = [
            'valid' => false,
            'errors' => [],
            'content_size' => strlen($pdf_content)
        ];
        
        // Check minimum content size
        if ($result['content_size'] < 200) {
            $result['errors'][] = 'PDF content too small';
            return $result;
        }
        
        // Check PDF header
        if (strpos($pdf_content, '%PDF') !== 0) {
            $result['errors'][] = 'Invalid PDF header in content';
            return $result;
        }
        
        // Check for PDF trailer
        if (strpos($pdf_content, '%%EOF') === false) {
            $result['errors'][] = 'Missing PDF trailer in content';
            return $result;
        }
        
        $result['valid'] = empty($result['errors']);
        return $result;
    }
    
    /**
     * Get footer configuration
     * 
     * @return array Footer configuration settings
     */
    public function getFooterConfig() {
        return $this->footer_config;
    }
    
    /**
     * Update footer configuration
     * 
     * @param array $config New configuration settings
     */
    public function setFooterConfig($config) {
        $this->footer_config = array_merge($this->footer_config, $config);
    }
}
?>