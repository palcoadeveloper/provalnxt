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



$uploadDirectory = "../../uploads/";

$errors = []; // Store errors here

$fileExtensionsAllowed = ['jpeg','jpg','png','pdf']; // These will be the only file extensions allowed




$fileName_raw = $_FILES['upload_file_raw_data']['name'];
$fileSize_raw = $_FILES['upload_file_raw_data']['size'];
$fileTmpName_raw  = $_FILES['upload_file_raw_data']['tmp_name'];
$fileType_raw = $_FILES['upload_file_raw_data']['type'];

$tmp_raw=explode('.',$fileName_raw);
$fileExtension_raw = strtolower(end($tmp_raw));


$fileName_mas = $_FILES['upload_file_master']['name'];
$fileSize_mas = $_FILES['upload_file_master']['size'];
$fileTmpName_mas  = $_FILES['upload_file_master']['tmp_name'];
$fileType_mas = $_FILES['upload_file_master']['type'];

$tmp_mas=explode('.',$fileName_mas);
$fileExtension_mas = strtolower(end($tmp_mas));


$fileName_cert = $_FILES['upload_file_certificate']['name'];
$fileSize_cert = $_FILES['upload_file_certificate']['size'];
$fileTmpName_cert  = $_FILES['upload_file_certificate']['tmp_name'];
$fileType_cert = $_FILES['upload_file_certificate']['type'];

$tmp_cert=explode('.',$fileName_cert);
$fileExtension_cert = strtolower(end($tmp_cert));


$fileName_other = $_FILES['upload_file_other']['name'];
$fileSize_other = $_FILES['upload_file_other']['size'];
$fileTmpName_other  = $_FILES['upload_file_other']['tmp_name'];
$fileType_other = $_FILES['upload_file_other']['type'];

$tmp_other=explode('.',$fileName_other);
$fileExtension_other = strtolower(end($tmp_other));



/*if($fileName_raw=="" || $fileName_cert=="" ||$fileName_mas=="")
{
    echo "Error: Files missing";
}
else */ if ( (! empty($fileExtension_other) && ! in_array($fileExtension_other,$fileExtensionsAllowed)) || (! empty($fileExtension_raw) && ! in_array($fileExtension_raw,$fileExtensionsAllowed)) || (! empty($fileExtension_mas) && ! in_array($fileExtension_mas,$fileExtensionsAllowed))|| (! empty($fileExtension_cert) && ! in_array($fileExtension_cert,$fileExtensionsAllowed)))
{
   
    
    $errors[] = "Error: Invalid file extension for one of the files. Please upload a JPEG or PNG file";
    echo "Error: Invalid file extension for one of the files. Please upload a JPEG or PNG file".pathinfo($fileName_cert, PATHINFO_EXTENSION);$fileExtension_raw.$fileExtension_mas.$fileExtension_cert;
}
else  if ((! empty($fileExtension_other) && $fileSize_other > 4000000) ||(! empty($fileExtension_raw) && $fileSize_raw > 4000000) || (! empty($fileExtension_mas) && $fileSize_mas > 4000000) || (! empty($fileExtension_cert) && $fileSize_cert > 4000000)) {
    $errors[] = "Error: File exceeds maximum size (4MB)";
    echo "Error: File exceeds maximum size (4MB)";
}
else 
{
    // Determine which workflow ID to use for filenames
    $workflow_id = !empty($_POST["test_wf_id"]) ? $_POST["test_wf_id"] : $_POST["val_wf_id"];
    error_log("File Upload Debug: workflow_id resolved to: " . $workflow_id . " (test_wf_id: " . ($_POST["test_wf_id"] ?? 'empty') . ", val_wf_id: " . ($_POST["val_wf_id"] ?? 'empty') . ")");
    
    $uploadPath_test_data="";
    $uploadPath_master_cert="";
    $uploadPath_test_cert="";
    $uploadPath_other="";
    if(! empty($fileExtension_raw))
    {
        $uploadPath_test_data =  $uploadDirectory .$workflow_id. "-data-".strtotime("now").".".$fileExtension_raw;
       
     
        $didUpload_raw = move_uploaded_file($fileTmpName_raw, $uploadPath_test_data);
  //checkAndDowngradePdfVersion($uploadPath_test_data);
    }
    
    if(! empty($fileExtension_mas))
    {
        $uploadPath_master_cert =  $uploadDirectory .$workflow_id. "-mcert-".strtotime("now").".".$fileExtension_mas;
        
        $didUpload_mas = move_uploaded_file($fileTmpName_mas, $uploadPath_master_cert);
        
    }
    
    if(! empty($fileExtension_cert))
    {
        $uploadPath_test_cert =  $uploadDirectory .$workflow_id. "-tcert-".strtotime("now").".".$fileExtension_cert;
        
        $didUpload_cert = move_uploaded_file($fileTmpName_cert, $uploadPath_test_cert);
        
        if($_SESSION['logged_in_user']=='vendor')
        {
            error_log("FPDI Debug: About to process certificate file: " . $uploadPath_test_cert);
            
            // Check if file exists before processing
            if (!file_exists($uploadPath_test_cert)) {
                error_log("FPDI Debug: Certificate file does not exist: " . $uploadPath_test_cert);
                throw new Exception("Certificate file not found: " . $uploadPath_test_cert);
            }
            
            $pdf = new Fpdi();
            
            $pageCount = $pdf->setSourceFile($uploadPath_test_cert);
            
            for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {
                $tplIdx = $pdf->importPage($pageNo, PdfReader\PageBoundaries::MEDIA_BOX);
                $s = $pdf->getTemplatesize($tplIdx);
                
                $pdf->addPage($s['orientation'], $s);
                
                $pdf->useTemplate($tplIdx);
                
            }
            $pdf->addPage($s['orientation'], $s);
            
            // $pdf->useTemplate($tplIdx);
            
            // font and color selection
            $pdf->SetFont('Arial','B',10);
            
            // now write some text above the imported page
            
            $pdf->SetXY(20, 83);
            $pdf->Cell(50,20,'Certificate Issued By:',1,0,'C');
            $pdf->SetFont('Arial','',10);
            $pdf->MultiCell(0,10,$_SESSION['user_name']."\n".'Date: '.date("d.m.Y h:i:s A"),1,'C');
            
            
            
            
            //$pdf->Write(2, 'Certificate issued by:'.$_SESSION['user_name'].' '.date("d-M-Y h:i:s A"));
            
            
            $uploadPath_test_cert =  $uploadDirectory .$workflow_id. "-tcert-".strtotime("now")."-I".".".$fileExtension_cert;
            
            $pdf->Output($uploadPath_test_cert,'F');
            
        
        }
        
    }
    
    if(! empty($fileExtension_other))
    {
        $uploadPath_other =  $uploadDirectory .$workflow_id. "-odoc-".strtotime("now").".".$fileExtension_other;
        $didUpload_other = move_uploaded_file($fileTmpName_other, $uploadPath_other);
    }
    
    DB::insert('tbl_uploads', [
        'upload_path_raw_data' => $uploadPath_test_data,
        'upload_path_master_certificate' => $uploadPath_master_cert,
        'upload_path_test_certificate' => $uploadPath_test_cert,
        'upload_path_other_doc' => $uploadPath_other,
        
        
        'test_wf_id' => (isset($_POST["test_wf_id"])?$_POST["test_wf_id"]:""),
        'uploaded_by'=>$_SESSION["user_id"],
        'test_id'=>$_POST["test_id"],
        'val_wf_id'=>(isset($_POST["val_wf_id"])?$_POST["val_wf_id"]:"") ,
        'uploaded_datetime' => DB::sqleval("NOW()") // NOW() is evaluated by MySQL
    ]);
    
    // Add logging functionality
    $unit_id = !empty($_SESSION['unit_id']) ? $_SESSION['unit_id'] : 0;
    
    // Determine the appropriate log message based on which workflow is being used
    if (!empty($_POST['val_wf_id']) && !empty($_POST['test_wf_id'])) {
        // This is from updatetaskdetails.php (test execution workflow)
        $log_description = 'Files uploaded for Test WF ID: ' . $_POST['test_wf_id'] . ' (Val WF ID: ' . $_POST['val_wf_id'] . ')';
    } elseif (!empty($_POST['val_wf_id'])) {
        // This is from validation approval pages
        // Check which page this is from based on HTTP_REFERER
        $referer = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '';
        if (strpos($referer, 'pendingforlevel1submission.php') !== false) {
            // This is from submission page
            $log_description = 'Files uploaded for VAL WF ID: ' . $_POST['val_wf_id'] . ' - Team Approval Submission Pending.';
        } elseif (strpos($referer, 'pendingforlevel1approval.php') !== false) {
            // This is from level 1 approval page
            $department_name = DB::queryFirstField("SELECT department_name FROM departments WHERE department_id = %i", $_SESSION['department_id']);
            if (empty($department_name)) {
                $department_name = 'Unknown Department';
            }
            $log_description = 'Files uploaded for VAL WF ID: ' . $_POST['val_wf_id'] . ' - Team Approval ' . $department_name . '.';
        } elseif (strpos($referer, 'pendingforlevel2approval.php') !== false) {
            // This is from level 2 approval page
            $log_description = 'Files uploaded for VAL WF ID: ' . $_POST['val_wf_id'] . ' - Unit Head Approval.';
        } elseif (strpos($referer, 'pendingforlevel3approval.php') !== false) {
            // This is from level 3 approval page
            $log_description = 'Files uploaded for VAL WF ID: ' . $_POST['val_wf_id'] . ' - QA Head Approval.';
        } else {
            // Fallback for other validation approval pages
            $log_description = 'Files uploaded for VAL WF ID: ' . $_POST['val_wf_id'] . ' - Approval Process.';
        }
    } elseif (!empty($_POST['test_wf_id'])) {
        // Fallback for test workflow only
        $log_description = 'Files uploaded for Test WF ID: ' . $_POST['test_wf_id'];
    } else {
        // Fallback if neither is available
        $log_description = 'Files uploaded - Workflow ID not specified';
    }
    
    DB::insert('log', [
        'change_type' => 'tran_file_upload',
        'table_name' => 'tbl_uploads',
        'change_description' => $log_description,
        'change_by' => $_SESSION['user_id'],
        'unit_id' => $unit_id
    ]);
    
    if(DB::affectedRows()>0)
    {
        echo "Files uploaded successfully!";
    }
    
    
    
    
    
    
    

    
    
   
    
    
    
    
    
    
}


?>
