<?php


// Include security middleware
require_once('../security/xss_integration_middleware.php');
require_once('../security/secure_file_upload_utils.php');
require_once('../security/rate_limiting_utils.php');



require_once '../config/db.class.php';
date_default_timezone_set("Asia/Kolkata");

use setasign\Fpdi\Fpdi;
use setasign\Fpdi\PdfReader;

require_once(__DIR__ . "/fpdf/fpdf.php");
require_once(__DIR__ . "/fpdf/fpdi/autoload.php");
require_once(__DIR__ . "/misc_php_functions.php");
date_default_timezone_set("Asia/Kolkata");

//Added on 02-Jul-24
require_once(__DIR__ . '/../../vendor/autoload.php');
// Note: fpdi_pdf-parser package not available, using basic FPDI
// require_once(__DIR__ . '/../../vendor/setasign/fpdi_pdf-parser/src/autoload.php');

use setasign\Fpdi\PdfParser\PdfParser;
use setasign\Fpdi\PdfParser\StreamReader;
// Note: FpdiPdfParser classes not available without fpdi_pdf-parser package
// use setasign\FpdiPdfParser\PdfParser\PdfParser as FpdiPdfParser;
// use setasign\FpdiPdfParser\PdfParser\CrossReference\CompressedReader;
// use setasign\FpdiPdfParser\PdfParser\CrossReference\CorruptedReader;

$parser = 'fpdi-pdf-parser';

$parserParams = [
    // Note: Using basic parameters since FpdiPdfParser is not available
    // FpdiPdfParser::PARAM_PASSWORD => '',
    // FpdiPdfParser::PARAM_IGNORE_PERMISSIONS => false
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

$uploadDirectory = "../uploads/";

$errors = []; // Store errors here
$uploadResults = []; // Store upload results

// Get and validate form inputs safely
$test_wf_id = safe_post('test_wf_id', 'string', '');
$test_id = safe_post('test_id', 'int', 0);
$val_wf_id = safe_post('val_wf_id', 'string', '');

// Process the certificate file
// You can modify this filename as needed, or make it dynamic based on form input
$sourceFileName = "T-72057-72-14-1752229374-tcert-1752744274-A.pdf";
$sourceFile = "../uploads/" . $sourceFileName;
$fileKey = 'certificate_file';
$uploadResult = [
    'file_path' => $sourceFile
];

echo "Processing certificate: " . htmlspecialchars($sourceFileName) . "<br>";

if (file_exists($sourceFile)) {
    // Ensure source file is readable by web server
    if (!is_readable($sourceFile)) {
        // Try to fix permissions, suppress warnings if not permitted
        @chmod($sourceFile, 0644);
        // Check again if it's readable after chmod attempt
        if (!is_readable($sourceFile)) {
            $errors[] = "Cannot read source file: " . $sourceFile . " (permission denied)";
        }
    }
    try {
                    $pdf = new Pdf();
                    if ($parser === 'default') {
                        $pdf->setPdfParserClass(PdfParser::class);
                    }
                    
                    $pageCount = $pdf->setSourceFileWithParserParams($sourceFile, $parserParams);
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
                    $pdf->MultiCell(0, 10, "Lavkush Kumar" . "\n" . 'Date: ' . "17.07.2025 02:54:34 PM", 1, 'C');
                   
                    $pdf->SetFont('Arial','B',10);
                    $pdf->SetXY(20, 103);
                    $pdf->Cell(50,30,'Certificate Reviewed By:',1,0,'C');
                    $pdf->SetFont('Arial','',10);
                    $pdf->MultiCell(0,10,"Viraj Naik"."\n".' Date: '."19.07.2025 03:48:55 PM"."\n"."Engg / User Department (Cipla Ltd.)",1,'C');
                    







                    // Generate new filename with -I suffix for vendor processed certificate
                    $pathInfo = pathinfo($uploadResult['file_path']);
                    $processedPath = $pathInfo['dirname'] . '/' . $pathInfo['filename'] . '-I.' . $pathInfo['extension'];
                    
                    // Remove existing output file if it exists to prevent permission conflicts
                    if (file_exists($processedPath)) {
                        unlink($processedPath);
                    }
                    
                    $pdf->Output($processedPath, 'F');
                    
                    // Update the path to the processed certificate
                    $uploadResults[$fileKey]['file_path'] = $processedPath;
                    
                } catch (Exception $e) {
                    $errors[] = "Error processing certificate: " . $e->getMessage();
                }
} else {
    $errors[] = "Source file not found: " . $sourceFile;
}

// Output results
if (!empty($errors)) {
    foreach ($errors as $error) {
        echo "Error: " . htmlspecialchars($error) . "<br>";
    }
} else {
    echo "Certificate processed successfully!<br>";
    if (isset($uploadResults[$fileKey]['file_path'])) {
        echo "Processed file: " . htmlspecialchars($uploadResults[$fileKey]['file_path']) . "<br>";
    }
}







?>