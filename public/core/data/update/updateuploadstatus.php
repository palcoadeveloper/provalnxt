<?php

// Load configuration first
require_once(__DIR__ . '/../../config/config.php');
// Include XSS protection middleware (auto-initializes)
require_once('../../security/xss_integration_middleware.php');

// Session is already started by config.php via session_init.php

// Validate session timeout
require_once('../../security/session_timeout_middleware.php');
validateActiveSession(); 

// Include rate limiting
require_once('../../security/rate_limiting_utils.php');

// Include secure transaction wrapper
require_once('../../security/secure_transaction_wrapper.php');

include_once '../../config/db.class.php';
date_default_timezone_set("Asia/Kolkata");

// Apply rate limiting for form submissions
if (!RateLimiter::checkRateLimit('form_submission')) {
    http_response_code(429);
    echo json_encode(['error' => 'Rate limit exceeded. Too many form submissions.']);
    exit();
}

// Validate CSRF token for POST requests using simple approach (consistent with rest of application)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !isset($_SESSION['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        http_response_code(403);
        echo json_encode(['error' => 'Invalid CSRF token']);
        exit();
    }
}

// FPDI includes for PDF processing
use setasign\Fpdi\Fpdi;
use setasign\Fpdi\PdfReader;

// FPDF is loaded via vendor autoloader below

// Added for enhanced PDF parsing
require_once(__DIR__ . '/../../../vendor/autoload.php');
require_once(__DIR__ . '/../../../vendor/setasign/fpdi_pdf-parser/src/autoload.php');

use setasign\Fpdi\PdfParser\PdfParser;
use setasign\Fpdi\PdfParser\StreamReader;
use setasign\FpdiPdfParser\PdfParser\PdfParser as FpdiPdfParser;
use setasign\FpdiPdfParser\PdfParser\CrossReference\CompressedReader;
use setasign\FpdiPdfParser\PdfParser\CrossReference\CorruptedReader;

$parser = 'fpdi-pdf-parser';

$parserParams = [
    FpdiPdfParser::PARAM_PASSWORD => '',
    FpdiPdfParser::PARAM_IGNORE_PERMISSIONS => false
];

// Extend FPDI to define which parser to use and to access some information of the used parser instance
class Pdf extends \setasign\Fpdi\Fpdi
{
    protected $pdfParserClass = null;

    public function setPdfParserClass($pdfParserClass)
    {
        $this->pdfParserClass = $pdfParserClass;
    }

    protected function getPdfParserInstance(
        StreamReader $streamReader,
        array $parserParams = []
    ) {
        if ($this->pdfParserClass !== null) {
            return new $this->pdfParserClass($streamReader, $parserParams);
        }

        return parent::getPdfParserInstance($streamReader, $parserParams);
    }

    public function getXrefInfo()
    {
        foreach (array_keys($this->readers) as $readerId) {
            $crossReference = $this->getPdfReader($readerId)->getParser()->getCrossReference();
            $readers = $crossReference->getReaders();
            foreach ($readers as $reader) {
                if ($reader instanceof CompressedReader) {
                    return 'compressed';
                }

                if ($reader instanceof CorruptedReader) {
                    return 'corrupted';
                }
            }
        }

        return 'normal';
    }

    public function isSourceFileEncrypted()
    {
        $reader = $this->getPdfReader($this->currentReaderId);
        if ($reader && $reader->getParser() instanceof FpdiPdfParser) {
            return $reader->getParser()->getSecHandler() !== null;
        }

        return false;
    }
}

// Input validation helper
class UploadStatusValidator {
    public static function validateUploadUpdateData() {
        $required_fields = ['action', 'up_id'];
        $validated_data = [];
        
        foreach ($required_fields as $field) {
            if (!isset($_POST[$field]) || empty($_POST[$field])) {
                throw new InvalidArgumentException("Missing required field: $field");
            }
            
            $value = $_POST[$field];
            
            // XSS detection on action field
            if ($field === 'action' && XSSPrevention::detectXSS($value)) {
                XSSPrevention::logXSSAttempt($value, 'update_upload_status');
                throw new InvalidArgumentException("Invalid input detected in action");
            }
            
            $validated_data[$field] = $value;
        }
        
        // Validate upload ID
        if (!is_numeric($validated_data['up_id'])) {
            throw new InvalidArgumentException("Invalid upload ID");
        }
        
        $validated_data['up_id'] = intval($validated_data['up_id']);
        
        // Validate action
        $valid_actions = ['approve-level2', 'approve', 'reject'];
        if (!in_array($validated_data['action'], $valid_actions)) {
            throw new InvalidArgumentException("Invalid action specified");
        }
        
        // Validate optional fields
        $optional_fields = ['wf_stage', 'val_wf_stage', 'val_wf_id', 'test_val_wf_id'];
        foreach ($optional_fields as $field) {
            if (isset($_POST[$field])) {
                $value = $_POST[$field];
                if (XSSPrevention::detectXSS($value)) {
                    XSSPrevention::logXSSAttempt($value, 'update_upload_status');
                    throw new InvalidArgumentException("Invalid input detected in $field");
                }
                $validated_data[$field] = $value;
            }
        }
        
        return $validated_data;
    }
    
}

try {
    // Validate input data
    $validated_data = UploadStatusValidator::validateUploadUpdateData();
    
    // Execute secure transaction based on action
    $result = executeSecureTransaction(function() use ($validated_data) {
        global $parser, $parserParams;
        
        if ($validated_data['action'] == 'approve-level2') {
            // Level 2 approval
            DB::query("UPDATE tbl_uploads SET upload_action = 'Approved' WHERE upload_id = %i", $validated_data['up_id']);
            
            $affected_rows = DB::affectedRows();
            if ($affected_rows === 0) {
                throw new Exception("No upload record was updated for level 2 approval");
            }
            
            return 'level2_approved';
        }
        elseif ($validated_data['action'] == 'approve') {
            // Regular approval with potential PDF processing
            if ($_SESSION['logged_in_user'] == 'employee') {
                $uploadPath_test_cert = DB::queryFirstField(
                    "SELECT upload_path_test_certificate FROM tbl_uploads WHERE upload_id = %i",
                    $validated_data['up_id']
                );
                
                if (!empty($uploadPath_test_cert)) {
                    // Convert relative path to work from current script location
                    // Database has ../../uploads/ (from /core/validation/) but we're in /core/data/update/ so we need ../../../uploads/
                    $adjustedPath = str_replace('../../uploads/', '../../../uploads/', $uploadPath_test_cert);
                    
                    // PDF processing with security checks
                    try {
                        $pdf = new Pdf();
                        if ($parser === 'default') {
                            $pdf->setPdfParserClass(PdfParser::class);
                        }
                        
                        $pageCount = $pdf->setSourceFileWithParserParams($adjustedPath, $parserParams);
                        
                        for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {
                            $tplIdx = $pdf->importPage($pageNo, PdfReader\PageBoundaries::MEDIA_BOX);
                            $s = $pdf->getTemplatesize($tplIdx);
                            
                            $pdf->addPage($s['orientation'], $s);
                            $pdf->useTemplate($tplIdx);
                        }
                        
                        // Add approval stamp if required
                        if ((isset($validated_data['wf_stage']) && $validated_data['wf_stage'] == '1') || 
                            (isset($validated_data['val_wf_stage']))) {
                            $pdf->addPage($s['orientation'], $s);
                        }
                        
                        $pdf->SetFont('Arial','B',10);
                        $pdf->SetXY(20, 103);
                        $pdf->Cell(50,30,'Certificate Reviewed By:',1,0,'C');
                        $pdf->SetFont('Arial','',10);
                        $pdf->MultiCell(0,10,$_SESSION['user_name']."\n".' Date: '.date("d.m.Y h:i:s A")."\n"."Engg / User Department (Cipla Ltd.)",1,'C');
                        
                        // Create new file path using simple string replacement like archived version
                        $new_path_adjusted = substr($adjustedPath, 0, -4) . "R.pdf";
                        $new_path_for_db = substr($uploadPath_test_cert, 0, -4) . "R.pdf";
                        
                        $pdf->Output($new_path_adjusted,'F');
                        
                        // Update database with original relative path format
                        DB::query("UPDATE tbl_uploads SET upload_path_test_certificate = %s WHERE upload_id = %i", 
                                 $new_path_for_db, $validated_data['up_id']);
                        
                    } catch (Exception $pdf_error) {
                        error_log("PDF processing error: " . $pdf_error->getMessage());
                        throw new Exception("PDF processing failed: " . $pdf_error->getMessage());
                    }
                }
                
                // Update approval status
                DB::query("UPDATE tbl_uploads SET upload_action = 'Approved' WHERE upload_id = %i", $validated_data['up_id']);
                
            } else {
                // Non-employee approval (no PDF processing)
                DB::query("UPDATE tbl_uploads SET upload_action = 'Approved' WHERE upload_id = %i", $validated_data['up_id']);
            }
            
            $affected_rows = DB::affectedRows();
            if ($affected_rows === 0) {
                throw new Exception("No upload record was updated for approval");
            }
            
            // Generate log description
            $unit_id = !empty($_SESSION['unit_id']) ? $_SESSION['unit_id'] : 0;
            $referer = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '';
            $isFromSubmissionPage = strpos($referer, 'pendingforlevel1submission.php') !== false;
            
            $description = '';
            if (isset($validated_data['val_wf_id']) && isset($validated_data['val_wf_stage']) && !$isFromSubmissionPage) {
                $description = 'Uploaded files approved at level '. $validated_data['val_wf_stage'].
                              '. Upload ID:'. $validated_data['up_id'] .' Val WF ID:'.$validated_data['val_wf_id'];
            } elseif (isset($validated_data['val_wf_id'])) {
                $description = 'Uploaded files approved. Upload ID:'. $validated_data['up_id'] .' Val WF ID:'.$validated_data['val_wf_id'];
            } else {
                $description = 'Uploaded files approved. Upload ID:'. $validated_data['up_id'] .' Test WF ID:'.$validated_data['test_val_wf_id'];
            }
            
            DB::insert('log', [
                'change_type' => 'tran_upload_files_app',
                'table_name' => 'tbl_uploads',
                'change_description' => $description,
                'change_by' => $_SESSION['user_id'],
                'unit_id' => $unit_id
            ]);
            
            return 'approved';
        }
        else { // reject action
            // Rejection
            DB::query("UPDATE tbl_uploads SET upload_action = 'Rejected' WHERE upload_id = %i", $validated_data['up_id']);
            
            $affected_rows = DB::affectedRows();
            if ($affected_rows === 0) {
                throw new Exception("No upload record was updated for rejection");
            }
            
            // Generate log description
            $unit_id = !empty($_SESSION['unit_id']) ? $_SESSION['unit_id'] : 0;
            $referer = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '';
            $isFromSubmissionPage = strpos($referer, 'pendingforlevel1submission.php') !== false;
            
            $description = '';
            if (isset($validated_data['val_wf_id']) && isset($validated_data['val_wf_stage']) && !$isFromSubmissionPage) {
                $description = 'Uploaded files rejected at level '. $validated_data['val_wf_stage'].
                              '. Upload ID:'. $validated_data['up_id'] .' Val WF ID:'.$validated_data['val_wf_id'];
            } elseif (isset($validated_data['val_wf_id'])) {
                $description = 'Uploaded files rejected. Upload ID:'. $validated_data['up_id'] .' Val WF ID:'.$validated_data['val_wf_id'];
            } else {
                $description = 'Uploaded files rejected. Upload ID:'. $validated_data['up_id'] .' Test WF ID:'.$validated_data['test_val_wf_id'];
            }
            
            DB::insert('log', [
                'change_type' => 'tran_upload_files_rej',
                'table_name' => 'tbl_uploads',
                'change_description' => $description,
                'change_by' => $_SESSION['user_id'],
                'unit_id' => $unit_id
            ]);
            
            return 'rejected';
        }
    });
    
    // Return success response
    echo json_encode([
        'status' => 'success',
        'message' => 'Upload status updated successfully',
        'action_result' => $result
    ]);
    
} catch (InvalidArgumentException $e) {
    error_log("Upload status validation error: " . $e->getMessage());
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
} catch (Exception $e) {
    error_log("Upload status update error: " . $e->getMessage());
    echo json_encode([
        'status' => 'error',
        'message' => 'Database error occurred: ' . $e->getMessage()
    ]);
}

?>