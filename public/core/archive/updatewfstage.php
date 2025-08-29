<?php
// Suppress deprecation warnings
error_reporting(E_ALL & ~E_DEPRECATED);
// Or use output buffering
ob_start();

// Start the session
session_start();

// Validate session timeout
require_once('../../security/session_timeout_middleware.php');
validateActiveSession();
date_default_timezone_set("Asia/Kolkata");
include_once ("../../config/db.class.php");
include_once '../../workflow/wf_ext_test.php';

// Rest of your code...

/*use setasign\Fpdi\Fpdi;
 use setasign\Fpdi\PdfReader;
 require_once ('fpdf\fpdf.php');
 require_once('fpdf\fpdi\autoload.php');*/

use setasign\Fpdi\Fpdi;
use setasign\Fpdi\PdfReader;

require_once (__DIR__."/fpdf/fpdf.php");
require_once (__DIR__."/fpdf/fpdi/autoload.php");

/**
 * Function to reject all uploaded files for a given workflow ID
 * Similar to individual file rejection but applied to all files
 * @param string $test_wf_id The workflow ID
 */
function rejectAllFilesForWorkflow($test_wf_id) {
    // Get all uploaded files for this workflow that are not already rejected
    $uploaded_files = DB::query("SELECT upload_id FROM tbl_uploads WHERE test_wf_id = %s AND (upload_action IS NULL OR upload_action != 'Rejected')", $test_wf_id);
    
    if (!empty($uploaded_files)) {
        // Update all files to rejected status
        DB::query("UPDATE tbl_uploads SET upload_action = 'Rejected' WHERE test_wf_id = %s AND (upload_action IS NULL OR upload_action != 'Rejected')", $test_wf_id);
        
        // Log each file rejection for audit trail
        $unit_id = !empty($_SESSION['unit_id']) ? $_SESSION['unit_id'] : 0;
        
        foreach ($uploaded_files as $file) {
            $description = 'File automatically rejected due to QA task rejection. Upload ID: ' . $file['upload_id'] . ' Test WF ID: ' . $test_wf_id;
            
            DB::insert('log', [
                'change_type' => 'tran_upload_files_rej',
                'table_name' => 'tbl_uploads',
                'change_description' => $description,
                'change_by' => $_SESSION['user_id'],
                'unit_id' => $unit_id
            ]);
        }
    }
}

$query="select test_wf_current_stage,test_type from tbl_test_schedules_tracking where test_wf_id='".$_GET['test_val_wf_id']."'";
$results = DB::queryFirstRow($query);

if(empty($results))
{
 //   echo "Nothing is pending";
}
else
{
    if(isset($_GET['test_type'])) //Internal Test
    {
    
        
        DB::insert('audit_trail', [
            'val_wf_id' => $_GET['val_wf_id'],
            'test_wf_id' => $_GET['test_val_wf_id'],
            'user_id'=>$_SESSION['user_id'],
            'user_type'=>$_SESSION['logged_in_user'],
            'time_stamp'=>DB::sqleval("NOW()"),
            'wf_stage'=>5
        ]);
        
        $unit_id = (isset($_SESSION['unit_id']) && $_SESSION['unit_id']=="") ? 0 : $_SESSION['unit_id'];
        
        
        DB::insert('log', [
            
            'change_type' => 'tran_ireview_approve',
            'table_name'=>'tbl_test_schedules_tracking',
            'change_description'=>'Internal test reviewed. UserID:'.intval($_SESSION['user_id']).' Test WfID:'.$_GET['test_val_wf_id'],
            'change_by'=>$_SESSION['user_id'],
            'unit_id' => $unit_id
        ]);
        
        
        
        DB::query("UPDATE tbl_test_schedules_tracking SET test_wf_current_stage=%s , test_conducted_date=%? ,certi_submission_date=%?, test_performed_by=%i WHERE val_wf_id=%s and test_wf_id=%s", 5,$_GET['test_conducted_date'],DB::sqleval("NOW()"),$_SESSION['user_id'],$_GET['val_wf_id'], $_GET['test_val_wf_id']);
        
        header('Location: ..\assignedcases.php');
        
    }
    else // External Test
    {
    $current_stage=$results['test_wf_current_stage'];
    
   //print("Current Stage".$current_stage);   
    $document = new Document();
    
    $document->setFiniteState($current_stage);
    $stateMachine = new Finite\StateMachine\StateMachine($document);
    $loader->load($stateMachine);
    $stateMachine->initialize();
  
    
    // Applying a transition
    $stateMachine->apply($_GET['action']);
    
    DB::insert('audit_trail', [
        'val_wf_id' => $_GET['val_wf_id'],
        'test_wf_id' => $_GET['test_val_wf_id'],
        'user_id'=>$_SESSION['user_id'],
        'user_type'=>$_SESSION['logged_in_user'],
        'time_stamp'=>DB::sqleval("NOW()"),
        'wf_stage'=>$document->getFiniteState()
    ]);
        $unit_id = (isset($_SESSION['unit_id']) && $_SESSION['unit_id']=="") ? 0 : $_SESSION['unit_id'];


    $change_description = '';

if ($_SESSION['logged_in_user'] === 'vendor') {
    $change_description = 'External test submitted by '.$_SESSION['user_domain_id'].'. Test WfID:'.$_GET['test_val_wf_id'];
} elseif ($_SESSION['logged_in_user'] === 'employee') {
    $change_description = 'External test reviewed by '.$_SESSION['user_domain_id'].'. Test WfID:'.$_GET['test_val_wf_id'];
} 

DB::insert('log', [
    'change_type' => 'tran_ereview_approve',
    'table_name' => 'tbl_test_schedules_tracking',
    'change_description' => $change_description,
    'change_by' => $_SESSION['user_id'],
    'unit_id' => $unit_id
]);
    
    
    
    if($_GET['action']=="qa_approve")
    {
        if($results['test_type']=='R')
        {
        
        DB::query("update tbl_routine_test_wf_tracking_details set routine_test_wf_current_stage='5', stage_assigned_datetime=%?,actual_wf_end_datetime=%? where routine_test_wf_id=%s",DB::sqleval("NOW()"),DB::sqleval("NOW()"),$_GET['val_wf_id']);
        
        // Auto-schedule subsequent routine tests for routine tests after database update
    if($results['test_type']=='R' && isset($_GET['val_wf_id']) && !empty($_GET['test_conducted_date'])) {
        require_once '../../workflow/routine_auto_scheduler.php';
        
        $unit_id = (isset($_SESSION['unit_id']) && $_SESSION['unit_id']!="") ? $_SESSION['unit_id'] : 0;
        $auto_schedule_result = autoScheduleSubsequentRoutineTests(
            $_GET['val_wf_id'], 
            $_GET['test_conducted_date'], 
            $_SESSION['user_id'], 
            $unit_id
        );
        
        if (!$auto_schedule_result) {
            error_log("Auto-scheduling failed for routine test: " . $_GET['val_wf_id']);
        }
    }
        
        
        
        
        
        }
        
        // Auto-schedule subsequent validations for validation tests after database update
        if($results['test_type']=='V' && isset($_GET['val_wf_id']) && !empty($_GET['test_conducted_date'])) {
            require_once '../../workflow/validation_auto_scheduler.php';
            
            $unit_id = (isset($_SESSION['unit_id']) && $_SESSION['unit_id']!="") ? $_SESSION['unit_id'] : 0;
            
            // Get test_id and test_sch_id from the current test being processed
            $test_info = DB::queryFirstRow("
                SELECT test_id, test_sch_id 
                FROM tbl_test_schedules_tracking 
                WHERE test_wf_id = %s
            ", $_GET['test_val_wf_id']);
            
            if ($test_info) {
                $auto_schedule_result = autoScheduleSubsequentValidations(
                    $_GET['val_wf_id'], 
                    $test_info['test_id'],
                    $test_info['test_sch_id'],
                    $_GET['test_conducted_date'], 
                    $_SESSION['user_id'], 
                    $unit_id
                );
                
                if (!$auto_schedule_result) {
                    error_log("Validation auto-scheduling failed for: " . $_GET['val_wf_id']);
                }
            } else {
                error_log("Could not retrieve test info for validation auto-scheduling: " . $_GET['test_val_wf_id']);
            }
        }
        
        
        $test_files=DB::query("select upload_path_test_certificate from tbl_uploads where test_wf_id=%s and upload_action='Approved'",$_GET['test_val_wf_id']);
    
        if(!empty($test_files)){
            
            foreach($test_files as $row){
                
                if(!empty($row['upload_path_test_certificate']))
                {
                    // $uploadPath_test_cert=DB::queryFirstField("select upload_path_test_certificate from tbl_uploads where upload_id=%i",intval($_POST['up_id']));
                    $pdf = new Fpdi();
                    
                    
                    $pageCount = $pdf->setSourceFile($row['upload_path_test_certificate']);
                    
                    for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {
                        $tplIdx = $pdf->importPage($pageNo, PdfReader\PageBoundaries::MEDIA_BOX);
                        $s = $pdf->getTemplatesize($tplIdx);
                        
                        $pdf->addPage($s['orientation'], $s);
                        
                        $pdf->useTemplate($tplIdx);
                        
                        // font and color selection
                        // $pdf->SetFont('Helvetica');
                        // $pdf->SetTextColor(200, 0, 0);
                        
                    }
                    
                    
                    
                    //$pdf->SetFont('Helvetica');
                    //$pdf->SetTextColor(200, 0, 0);
                    //$pdf->SetXY(40, 103);
                    //$pdf->Write(2, 'Certificate Approved By: '.$_SESSION['user_name'].' Quality Assurance (Cipla Ltd.)'.' '.date("d-M-Y h:i:s A"));
                    
                    $pdf->SetFont('Arial','B',10);
                    $pdf->SetXY(20, 133);
                    $pdf->Cell(50,30,'Certificate Approved By:',1,0,'C');
                    $pdf->SetFont('Arial','',10);
                    $pdf->MultiCell(0,10,$_SESSION['user_name']."\n".' Date: '.date("d.m.Y h:i:s A")."\n"."Quality Assurance (Cipla Ltd.)",1,'C');
                    
                    
                    
                    
                    $new_path=substr($row['upload_path_test_certificate'], 0, -5)."A.pdf";
                    
                    $pdf->Output($new_path,'F');
                    
                    //$uploadPath_test_cert=DB::query("update tbl_uploads set upload_path_test_certificate=%s where test_wf_id=%s and upload_action='Approved'",$new_path,$_GET['test_val_wf_id']);
                    $uploadPath_test_cert=DB::query("update tbl_uploads set upload_path_test_certificate=%s where upload_path_test_certificate=%s and test_wf_id=%s and upload_action='Approved'",$new_path,$row['upload_path_test_certificate'],$_GET['test_val_wf_id']);
                    
                    
                }
                
                
                
                
               
                
                
                
                
                
                
                
                
            }
            
            
            
        }
    
    }
    else if($_GET['action']=="qa_reject")
    {
        // When QA rejects the task, automatically reject all uploaded files
        rejectAllFilesForWorkflow($_GET['test_val_wf_id']);
    }
    
    
     //   DB::query("UPDATE tbl_test_schedules_tracking SET test_wf_current_stage=%s , test_conducted_date=%? , certi_submission_date=%?,test_performed_by=%i WHERE val_wf_id=%s and test_wf_id=%s", $document->getFiniteState(),$_GET['test_conducted_date'],DB::sqleval("NOW()"),$_SESSION['user_id'],$_GET['val_wf_id'], $_GET['test_val_wf_id']);
    DB::query("UPDATE tbl_test_schedules_tracking SET test_wf_current_stage=%s , test_conducted_date=%? , certi_submission_date=%?,test_performed_by=%i WHERE test_wf_id=%s", $document->getFiniteState(),$_GET['test_conducted_date'],DB::sqleval("NOW()"),$_SESSION['user_id'], $_GET['test_val_wf_id']);

    
    // Make sure to use the correct path format
    redirect('assignedcases.php');
    // If using output buffering:
    ob_end_flush();
    }
    
}

