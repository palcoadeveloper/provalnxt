<?php 

session_start();

// Validate session timeout
require_once('../../security/session_timeout_middleware.php');
validateActiveSession();
date_default_timezone_set("Asia/Kolkata");
include_once ("../../config/db.class.php");

DB::insert('validation_reports', [
    'val_wf_id' => $_POST['routine_test_wf_id'],
    'justification'=>$_POST['justification'],
    
    'deviation_remark_val_begin' => $_POST['deviation_remark']
    
]);



echo "success";
