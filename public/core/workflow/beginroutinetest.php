<?php
// Start the session
session_start();


// Validate session timeout
require_once('../security/session_timeout_middleware.php');
validateActiveSession();
require_once '../config/db.class.php';
date_default_timezone_set("Asia/Kolkata");
//var_dump($_GET);
$results =DB::query("call start_routine_test_task(%i,%i,%s,%s,%i)", intval($_GET['u']), intval($_GET['e']), $_GET['d'],$_GET['w'],intval($_GET['l']));



if(empty($results))
{
  //  echo "Nothing is pending";
}
else
{
  //  echo "pending";
    
    DB::insert('log', [
        
        'change_type' => 'tran_rtbgn',
        'table_name'=>'',
        'change_description'=>'Routine Test begin. WorkflowID:'.$_GET['w'],
        'change_by'=>$_SESSION['user_id'],
        'unit_id' => $_SESSION['unit_id']
    ]);
   header('Location: ..\manageroutinetests.php');
    
}