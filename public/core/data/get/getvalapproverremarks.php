<?php


// Load configuration first
require_once(__DIR__ . '/../../config/config.php');
//session_start();


// Validate session timeout
require_once('../../security/session_timeout_middleware.php');
validateActiveSession();
//require_once '../../config/db.class.php';

date_default_timezone_set("Asia/Kolkata");

$remarks= DB::query("select concat('[',DATE_FORMAT(t1.created_date_time, '%d.%m.%Y %H:%i:%s'),'] - ', t1.remarks,' - added by ',t2.user_name) as 'remark'
from approver_remarks t1, users t2
where t1.user_id=t2.user_id and val_wf_id=%s
and test_wf_id='' order by t1.created_date_time asc", $_GET['val_wf_id']);



$output="";

foreach ($remarks as $row) {
    $output=$output. $row['remark']."<br/>";
}

echo $output;