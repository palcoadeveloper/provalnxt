<?php

// Load configuration first
require_once('../config/config.php');

// Session is already started by config.php via session_init.php

// Include security middleware
require_once('../security/xss_integration_middleware.php');
require_once('../security/secure_file_upload_utils.php');
require_once('../security/rate_limiting_utils.php');

// Validate session timeout
require_once('../security/session_timeout_middleware.php');
validateActiveSession();

require_once '../config/db.class.php';
date_default_timezone_set("Asia/Kolkata");

// Check for POST content length exceeded (this happens before PHP processes POST data)
$contentLength = isset($_SERVER['CONTENT_LENGTH']) ? (int)$_SERVER['CONTENT_LENGTH'] : 0;
$postMaxSize = SecureFileUpload::convertPHPSizeToBytes(ini_get('post_max_size'));

if ($contentLength > $postMaxSize && $postMaxSize > 0) {
    http_response_code(413); // Payload Too Large
    $maxSizeMB = round($postMaxSize / 1024 / 1024, 1);
    $actualSizeMB = round($contentLength / 1024 / 1024, 1);
    echo json_encode([
        'error' => "Upload size ({$actualSizeMB}MB) exceeds server limit ({$maxSizeMB}MB). Please contact your system administrator to increase the upload limit or reduce file size."
    ]);
    exit();
}

// Apply rate limiting for file uploads
if (!RateLimiter::checkRateLimit('file_upload')) {
    http_response_code(429);
    echo json_encode(['error' => 'Rate limit exceeded for file uploads. Please try again later.']);
    exit();
}

// Validate CSRF token using simple approach (consistent with rest of application)
if (!isset($_POST['csrf_token'])) {
    http_response_code(400);
    echo json_encode(['error' => 'CSRF token is missing. This may be due to file size exceeding server limits. Please try a smaller file or contact your system administrator.']);
    exit();
}

if (!isset($_SESSION['csrf_token'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Session CSRF token is missing. Please refresh the page and try again.']);
    exit();
}

if ($_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    http_response_code(403);
    echo json_encode(['error' => 'Invalid CSRF token. Please refresh the page and try again.']);
    exit();
}

use setasign\Fpdi\Fpdi;
use setasign\Fpdi\PdfReader;

require_once(__DIR__ . "/misc_php_functions.php");
date_default_timezone_set("Asia/Kolkata");

//Added on 02-Jul-24
require_once(__DIR__ . '/../../vendor/autoload.php');
require_once(__DIR__ . '/../../vendor/setasign/fpdi_pdf-parser/src/autoload.php');

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

$uploadDirectory = "../../uploads/";

// Check PHP configuration for file uploads before processing
$phpConfigCheck = SecureFileUpload::checkPHPUploadConfiguration();
if (!$phpConfigCheck['valid']) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Server configuration error: ' . implode(', ', $phpConfigCheck['errors']) . 
                   '. Please contact your system administrator.'
    ]);
    exit();
}

// Ensure upload directory is secure
if (!SecureFileUpload::ensureSecureUploadDirectory($uploadDirectory)) {
    echo json_encode(['error' => 'Upload directory configuration error']);
    exit();
}

$errors = []; // Store errors here
$uploadResults = []; // Store upload results

// Get and validate form inputs safely
$test_wf_id = safe_post('test_wf_id', 'string', '');
$test_id = safe_post('test_id', 'int', 0);
$val_wf_id = safe_post('val_wf_id', 'string', '');

// Basic validation
if (empty($test_wf_id) && empty($val_wf_id)) {
    echo json_encode(['error' => 'Workflow ID is required']);
    exit();
}

// For validation workflows (val_wf_id), test_id can be 0, but for test workflows (test_wf_id), test_id must be > 0
if (!empty($test_wf_id) && $test_id <= 0) {
    echo json_encode(['error' => 'Valid test ID is required for test workflows']);
    exit();
}

// For validation workflows, set test_id to 0 if not provided
if (!empty($val_wf_id) && $test_id <= 0) {
    $test_id = 0;
}

// Define file upload mappings
$fileUploads = [
    'upload_file_raw_data' => 'data',
    'upload_file_master' => 'mcert', 
    'upload_file_certificate' => 'tcert',
    'upload_file_other' => 'odoc'
];

// Process each file upload securely
foreach ($fileUploads as $fileKey => $prefix) {
    if (isset($_FILES[$fileKey]) && $_FILES[$fileKey]['error'] !== UPLOAD_ERR_NO_FILE) {
        // Generate appropriate prefix for filename
        $filePrefix = $test_wf_id . '-' . $prefix;
        
        // Process upload securely
        $uploadResult = SecureFileUpload::processUpload(
            $_FILES[$fileKey], 
            $uploadDirectory, 
            $filePrefix
        );
        
        if (!$uploadResult['success']) {
            $errors[] = "Error uploading " . str_replace('_', ' ', $fileKey) . ": " . $uploadResult['error'];
            
            // Log the upload failure
            SecurityUtils::logSecurityEvent('file_upload_failed', 
                'File upload failed: ' . $uploadResult['error'], [
                    'file_key' => $fileKey,
                    'original_name' => $uploadResult['original_name'],
                    'error' => $uploadResult['error']
                ]);
        } else {
            $uploadResults[$fileKey] = $uploadResult;
            
            // Special processing for certificate files from vendors
            if ($fileKey === 'upload_file_certificate' && isset($_SESSION['logged_in_user']) && $_SESSION['logged_in_user'] === 'vendor') {
                try {
                    $pdf = new Pdf();
                    if ($parser === 'default') {
                        $pdf->setPdfParserClass(PdfParser::class);
                    }
                    
                    $pageCount = $pdf->setSourceFileWithParserParams($uploadResult['file_path'], $parserParams);
                    for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {
                        $tplIdx = $pdf->importPage($pageNo, PdfReader\PageBoundaries::MEDIA_BOX);
                        $s = $pdf->getTemplatesize($tplIdx);
                        
                        $pdf->addPage($s['orientation'], $s);
                        $pdf->useTemplate($tplIdx);
                    }
                    
                    // Add vendor signature page
                    $pdf->addPage($s['orientation'], $s);
                    $pdf->SetFont('Arial', 'B', 10);
                    $pdf->SetXY(20, 83);
                    $pdf->Cell(50, 20, 'Certificate Issued By:', 1, 0, 'C');
                    $pdf->SetFont('Arial', '', 10);
                    $pdf->MultiCell(0, 10, $_SESSION['user_name'] . "\n" . 'Date: ' . date("d.m.Y h:i:s A"), 1, 'C');
                    
                    // Generate new filename with -I suffix for vendor processed certificate
                    $pathInfo = pathinfo($uploadResult['file_path']);
                    $processedPath = $pathInfo['dirname'] . '/' . $pathInfo['filename'] . '-I.' . $pathInfo['extension'];
                    
                    $pdf->Output($processedPath, 'F');
                    
                    // Update the path to the processed certificate
                    $uploadResults[$fileKey]['file_path'] = $processedPath;
                    
                } catch (Exception $e) {
                    $errors[] = "Error processing certificate: " . $e->getMessage();
                }
            }
        }
    }
}

// If there were errors during secure upload, display them
if (!empty($errors)) {
    $errorString = implode(", ", $errors);
    echo json_encode(['error' => $errorString]);
    exit();
}

// If no files were uploaded at all
if (empty($uploadResults)) {
    echo json_encode(['error' => 'No files were uploaded']);
    exit();
}

// Prepare database paths from successful uploads
$uploadPath_test_data = '';
$uploadPath_master_cert = '';
$uploadPath_test_cert = '';
$uploadPath_other = '';

foreach ($uploadResults as $fileKey => $result) {
    switch ($fileKey) {
        case 'upload_file_raw_data':
            $uploadPath_test_data = $result['file_path'];
            break;
        case 'upload_file_master':
            $uploadPath_master_cert = $result['file_path'];
            break;
        case 'upload_file_certificate':
            $uploadPath_test_cert = $result['file_path'];
            break;
        case 'upload_file_other':
            $uploadPath_other = $result['file_path'];
            break;
    }
}

// Insert upload record into database
DB::insert('tbl_uploads', [
    'upload_path_raw_data' => $uploadPath_test_data,
    'upload_path_master_certificate' => $uploadPath_master_cert,
    'upload_path_test_certificate' => $uploadPath_test_cert,
    'upload_path_other_doc' => $uploadPath_other,
    'test_wf_id' => $test_wf_id,
    'uploaded_by' => $_SESSION["user_id"],
    'test_id' => $test_id,
    'val_wf_id' => $val_wf_id,
    'uploaded_datetime' => DB::sqleval("NOW()")
]);

// Add logging
$unit_id = !empty($_SESSION['unit_id']) ? $_SESSION['unit_id'] : 0;

// Determine appropriate log message based on workflow context
if (!empty($val_wf_id) && !empty($test_wf_id)) {
    $log_description = 'Files uploaded for Test WF ID: ' . $test_wf_id . ' (Val WF ID: ' . $val_wf_id . ')';
} elseif (!empty($val_wf_id)) {
    // Check page context from HTTP_REFERER
    $referer = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '';
    if (strpos($referer, 'pendingforlevel1submission.php') !== false) {
        $log_description = 'Files uploaded for VAL WF ID: ' . $val_wf_id . ' - Team Approval Submission Pending.';
    } elseif (strpos($referer, 'pendingforlevel1approval.php') !== false) {
        $department_name = DB::queryFirstField("SELECT department_name FROM departments WHERE department_id = %i", $_SESSION['department_id']);
        $department_name = $department_name ?: 'Unknown Department';
        $log_description = 'Files uploaded for VAL WF ID: ' . $val_wf_id . ' - Team Approval ' . $department_name . '.';
    } elseif (strpos($referer, 'pendingforlevel2approval.php') !== false) {
        $log_description = 'Files uploaded for VAL WF ID: ' . $val_wf_id . ' - Unit Head Approval.';
    } elseif (strpos($referer, 'pendingforlevel3approval.php') !== false) {
        $log_description = 'Files uploaded for VAL WF ID: ' . $val_wf_id . ' - QA Head Approval.';
    } else {
        $log_description = 'Files uploaded for VAL WF ID: ' . $val_wf_id . ' - Approval Process.';
    }
} elseif (!empty($test_wf_id)) {
    $log_description = 'Files uploaded for Test WF ID: ' . $test_wf_id;
} else {
    $log_description = 'Files uploaded - Workflow ID not specified';
}

DB::insert('log', [
    'change_type' => 'tran_file_upload',
    'table_name' => 'tbl_uploads',
    'change_description' => $log_description,
    'change_by' => $_SESSION['user_id'],
    'unit_id' => $unit_id
]);

// Check if database insertion was successful
if (DB::affectedRows() > 0) {
    echo "Files uploaded successfully!";
    
    // Log successful upload
    SecurityUtils::logSecurityEvent('file_upload_success', 'Files uploaded successfully', [
        'test_wf_id' => $test_wf_id,
        'val_wf_id' => $val_wf_id,
        'file_count' => count($uploadResults),
        'user_id' => $_SESSION['user_id']
    ]);
} else {
    echo json_encode(['error' => 'Database error: Failed to record file upload']);
}
?>