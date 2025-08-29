<?php


// Load configuration first
require_once(__DIR__ . '/../../config/config.php');
//session_start();


// Validate session timeout
require_once(__DIR__ . '/../../security/session_timeout_middleware.php');
validateActiveSession();
//require_once '../../config/db.class.php';



$remarks= DB::query("select t1.created_date_time as remark_timestamp , t1.remarks,t2.user_name
from approver_remarks t1, users t2
where t1.user_id=t2.user_id and test_wf_id=%s
order by t1.created_date_time asc", $_GET['test_val_wf_id']);



$output="";

foreach ($remarks as $row) {
    $output=$output."[ ". Date("d.m.Y H:i:s",strtotime($row['remark_timestamp'])) ." ] - ". $row['remarks']." - added by " .$row['user_name']."<br/>";
}

echo $output;