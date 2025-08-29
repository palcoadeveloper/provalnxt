<?php
if(!isset($_SESSION))
{
    session_start();

// Validate session timeout
require_once('../../security/session_timeout_middleware.php');
validateActiveSession();
} 

include_once '../../config/db.class.php';
date_default_timezone_set("Asia/Kolkata");
/*use setasign\Fpdi\Fpdi;
 use setasign\Fpdi\PdfReader;
 require_once ('fpdf\fpdf.php');
 require_once('fpdf\fpdi\autoload.php');*/

use setasign\Fpdi\Fpdi;
use setasign\Fpdi\PdfReader;

require_once (__DIR__."/fpdf/fpdf.php");
require_once (__DIR__."/fpdf/fpdi/autoload.php");

//Added on 02-Jul-24
require_once '../vendor/autoload.php';
require_once '../vendor/setasign/fpdi_pdf-parser/src/autoload.php';

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


if($_POST['action']=='approve-level2'){

    DB::query("update tbl_uploads set upload_action='Approved' where upload_id=%i",intval($_POST['up_id']));
    
   // echo "Level 2 approve";   
}

else if($_POST['action']=='approve'){
    
    if($_SESSION['logged_in_user']=='employee')
    {
        
        $uploadPath_test_cert=DB::queryFirstField("select upload_path_test_certificate from tbl_uploads where upload_id=%i",intval($_POST['up_id']));
        
        if(!empty($uploadPath_test_cert))
        {
          //  $pdf = new Fpdi();
               $pdf = new Pdf();
if ($parser === 'default') {
    $pdf->setPdfParserClass(PdfParser::class);
}
            
            //$pageCount = $pdf->setSourceFile($uploadPath_test_cert);
            $pageCount = $pdf->setSourceFileWithParserParams($uploadPath_test_cert, $parserParams);
            
         //   $pageCount = $pdf->setSourceFile($uploadPath_test_cert);
            
            for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {
                $tplIdx = $pdf->importPage($pageNo, PdfReader\PageBoundaries::MEDIA_BOX);
                $s = $pdf->getTemplatesize($tplIdx);
                
                $pdf->addPage($s['orientation'], $s);
                
                $pdf->useTemplate($tplIdx);
                

                
            }
            
            
            if(( isset($_POST['wf_stage']) && $_POST['wf_stage']=='1') || (isset($_POST['val_wf_stage'])) ){
                $pdf->addPage($s['orientation'], $s);
            }
            
            $pdf->SetFont('Arial','B',10);
            $pdf->SetXY(20, 103);
            $pdf->Cell(50,30,'Certificate Reviewed By:',1,0,'C');
            $pdf->SetFont('Arial','',10);
            $pdf->MultiCell(0,10,$_SESSION['user_name']."\n".' Date: '.date("d.m.Y h:i:s A")."\n"."Engg / User Department (Cipla Ltd.)",1,'C');
            
            
            
            
            
            $new_path=substr($uploadPath_test_cert, 0, -5)."R.pdf";
            
            $pdf->Output($new_path,'F');
            
            $uploadPath_test_cert=DB::query("update tbl_uploads set upload_path_test_certificate=%s where upload_id=%i",$new_path,intval($_POST['up_id']));
            
             DB::query("update tbl_uploads set upload_action='Approved' where upload_id=%i",intval($_POST['up_id']));
            $unit_id = !empty($_SESSION['unit_id']) ? $_SESSION['unit_id'] : 0;
            // Check if this is from pendingforlevel1submission.php to avoid showing level information
            $referer = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '';
            $isFromSubmissionPage = strpos($referer, 'pendingforlevel1submission.php') !== false;
            
            $description = '';
            if (isset($_POST['val_wf_id']) && isset($_POST['val_wf_stage']) && !$isFromSubmissionPage) {
                $description = 'Uploaded files approved at level '. $_POST['val_wf_stage'].'. Upload ID:'.intval($_POST['up_id']).' Val WF ID:'.$_POST['val_wf_id'];
            } elseif (isset($_POST['val_wf_id'])) {
                $description = 'Uploaded files approved. Upload ID:'.intval($_POST['up_id']).' Val WF ID:'.$_POST['val_wf_id'];
            } else {
                $description = 'Uploaded files approved. Upload ID:'.intval($_POST['up_id']).' Test WF ID:'.$_POST['test_val_wf_id'];
            }
            
            DB::insert('log', [
                'change_type' => 'tran_upload_files_app',
                'table_name' => 'tbl_uploads',
                'change_description' => $description,
                'change_by' => $_SESSION['user_id'],
                'unit_id' => $unit_id 
            ]);
            
            
        }
        else
        {

            DB::query("update tbl_uploads set upload_action='Approved' where upload_id=%i",intval($_POST['up_id']));
            $unit_id = !empty($_SESSION['unit_id']) ? $_SESSION['unit_id'] : 0;
      

            // Check if this is from pendingforlevel1submission.php to avoid showing level information
            $referer = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '';
            $isFromSubmissionPage = strpos($referer, 'pendingforlevel1submission.php') !== false;
            
            $description = '';
            if (isset($_POST['val_wf_id']) && isset($_POST['val_wf_stage']) && !$isFromSubmissionPage) {
                $description = 'Uploaded files approved at level '. $_POST['val_wf_stage'].'. Upload ID:'.intval($_POST['up_id']).' Val WF ID:'.$_POST['val_wf_id'];
            } elseif (isset($_POST['val_wf_id'])) {
                $description = 'Uploaded files approved. Upload ID:'.intval($_POST['up_id']).' Val WF ID:'.$_POST['val_wf_id'];
            } else {
                $description = 'Uploaded files approved. Upload ID:'.intval($_POST['up_id']).' Test WF ID:'.$_POST['test_val_wf_id'];
            }
            
            DB::insert('log', [
                'change_type' => 'tran_upload_files_app',
                'table_name' => 'tbl_uploads',
                'change_description' => $description,
                'change_by' => $_SESSION['user_id'],
                'unit_id' => $unit_id
            ]);


        }
        
        
        
    }
    


}
else 
{
    DB::query("update tbl_uploads set upload_action='Rejected' where upload_id=%i",intval($_POST['up_id']));
     $unit_id = !empty($_SESSION['unit_id']) ? $_SESSION['unit_id'] : 0;
   

            // Check if this is from pendingforlevel1submission.php to avoid showing level information
            $referer = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '';
            $isFromSubmissionPage = strpos($referer, 'pendingforlevel1submission.php') !== false;
            
            $description = '';
            if (isset($_POST['val_wf_id']) && isset($_POST['val_wf_stage']) && !$isFromSubmissionPage) {
                $description = 'Uploaded files rejected at level '. $_POST['val_wf_stage'].'. Upload ID:'.intval($_POST['up_id']).' Val WF ID:'.$_POST['val_wf_id'];
            } elseif (isset($_POST['val_wf_id'])) {
                $description = 'Uploaded files rejected. Upload ID:'.intval($_POST['up_id']).' Val WF ID:'.$_POST['val_wf_id'];
            } else {
                $description = 'Uploaded files rejected. Upload ID:'.intval($_POST['up_id']).' Test WF ID:'.$_POST['test_val_wf_id'];
            }
            
            DB::insert('log', [
                'change_type' => 'tran_upload_files_rej',
                'table_name' => 'tbl_uploads',
                'change_description' => $description,
                'change_by' => $_SESSION['user_id'],
                'unit_id' => $unit_id
            ]);
    
}

