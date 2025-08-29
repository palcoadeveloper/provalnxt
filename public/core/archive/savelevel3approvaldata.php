<?php


session_start();


// Validate session timeout
require_once('../../security/session_timeout_middleware.php');
validateActiveSession();
include_once ("../../config/db.class.php");

date_default_timezone_set("Asia/Kolkata");

DB::query("update tbl_val_wf_approval_tracking_details set iteration_completion_status = 'complete',iteration_status = 'Active', level3_head_qa_approval_datetime=%?, level3_head_qa_approval_by=%i, level3_head_qa_approval_remarks=%s where val_wf_id=%s and val_wf_approval_trcking_id=%d",DB::sqleval("NOW()"),$_SESSION['user_id'],$_GET['level3_approver_remark'],$_GET['val_wf_id'],$_GET['val_wf_tracking_id']);
if(!empty($_GET['deviation_remark']))
{
    //DB::query("update validation_reports set deviation=concat(deviation,' ', %s) where val_wf_id=%s",$_GET['deviation_remark'],$_GET['val_wf_id']);
    DB::query("update validation_reports set deviation=%s where val_wf_id=%s",$_GET['deviation_remark'],$_GET['val_wf_id']);
}

DB::query("update tbl_val_wf_tracking_details set val_wf_current_stage='5', stage_assigned_datetime=%?,actual_wf_end_datetime=%? where val_wf_id=%s",DB::sqleval("NOW()"),DB::sqleval("NOW()"),$_GET['val_wf_id']);
// Assuming $val_wf_id is the workflow ID you're updating


DB::insert('log', [
    
    'change_type' => 'tran_level3app_qh',
    'table_name'=>'',
    'change_description'=>'Level3 approved.UserID:'.intval($_SESSION['user_id']).'WfID:'.$_GET['val_wf_id'],
    'change_by'=>$_SESSION['user_id'],
    'unit_id' => $_SESSION['unit_id']
]);




// create a new cURL resource
$ch = curl_init();

// set URL and other appropriate options

curl_setopt($ch, CURLOPT_URL, BASE_URL."generateprotocolreport_rev.php?equipment_id=".$_GET['equipment_id']."&val_wf_id=".$_GET['val_wf_id']);
curl_setopt($ch, CURLOPT_HEADER, 0);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); 
// grab URL and pass it to the browser
$output=curl_exec($ch);
echo $output;
// close cURL resource, and free up system resources
curl_close($ch);

if($output==True){
    $protocol_report_path='uploads/protocol-report-'.$_GET['val_wf_id'].'.pdf';
    DB::query("update tbl_val_wf_approval_tracking_details set protocol_report_path=%s where val_wf_id=%s",$protocol_report_path,$_GET['val_wf_id']);
    
    
}





header('Location: ' . BASE_URL . 'manageprotocols.php');
