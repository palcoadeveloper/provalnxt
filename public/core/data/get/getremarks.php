<?php


// Load configuration first
require_once(__DIR__ . '/../../config/config.php');
//session_start();


// Validate session timeout
require_once(__DIR__ . '/../../security/session_timeout_middleware.php');
validateActiveSession();
//require_once '../../config/db.class.php';



// Get test_val_wf_id from parent scope or GET parameter
$test_wf_id = isset($test_val_wf_id) ? $test_val_wf_id : (isset($_GET['test_val_wf_id']) ? $_GET['test_val_wf_id'] : '');

if (empty($test_wf_id)) {
    error_log("getremarks.php: test_wf_id is empty");
    echo '<span class="text-muted">No remarks available.</span>';
    return;
}

$remarks= DB::query("select t1.created_date_time as remark_timestamp , t1.remarks,t2.user_name
from approver_remarks t1, users t2
where t1.user_id=t2.user_id and test_wf_id=%s
order by t1.created_date_time asc", $test_wf_id);



if (empty($remarks)) {
    echo '<span class="text-muted"><i class="mdi mdi-information-outline"></i> No approver remarks yet.</span>';
} else {
    $output = "";
    foreach ($remarks as $row) {
        $output .= '<div class="timeline-item">';
        $output .= '<span class="timeline-date"><i class="mdi mdi-clock-outline"></i> ' . Date('d.m.Y H:i:s', strtotime($row['remark_timestamp'])) . '</span>';
        $output .= '<div class="timeline-content">';
        $output .= '<strong>' . htmlspecialchars($row['user_name'], ENT_QUOTES, 'UTF-8') . ':</strong> ';
        $output .= htmlspecialchars($row['remarks'], ENT_QUOTES, 'UTF-8');
        $output .= '</div>';
        $output .= '</div>';
    }
    echo $output;
}