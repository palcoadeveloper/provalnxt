<?php


session_start();


// Validate session timeout
require_once('../../security/session_timeout_middleware.php');
validateActiveSession();
include_once ("../../config/db.class.php");

date_default_timezone_set("Asia/Kolkata");
//var_dump($_GET); 
//var_dump($_SESSION);


DB::query("update tbl_val_wf_approval_tracking_details set level2_unit_head_approval_datetime=%?, level2_unit_head_approval_by=%i, level2_unit_head_approval_remarks=%s  where val_wf_id=%s and val_wf_approval_trcking_id=%d",DB::sqleval("NOW()"),$_SESSION['user_id'],$_GET['level2_approver_remark'],$_GET['val_wf_id'],$_GET['val_wf_tracking_id']);

if(!empty($_GET['deviation_remark']))
{
//DB::query("update validation_reports set deviation=concat(ifnull(deviation,''),' ', %s) where val_wf_id=%s",$_GET['deviation_remark'],$_GET['val_wf_id']);
DB::query("update validation_reports set deviation= %s where val_wf_id=%s",$_GET['deviation_remark'],$_GET['val_wf_id']);
}
DB::query("update tbl_val_wf_tracking_details set val_wf_current_stage='4', stage_assigned_datetime=%? where val_wf_id=%s",DB::sqleval("NOW()"),$_GET['val_wf_id']);

DB::insert('log', [
    
    'change_type' => 'tran_level2app_uh',
    'table_name'=>'',
    'change_description'=>'Level2 approved.UserID:'.intval($_SESSION['user_id']).'WfID:'.$_GET['val_wf_id'],
    'change_by'=>$_SESSION['user_id'],
    'unit_id' => $_SESSION['unit_id']
]);



header('Location: ..\manageprotocols.php');