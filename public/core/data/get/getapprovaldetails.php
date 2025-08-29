<?php

// Load configuration first
require_once(__DIR__ . '/../../config/config.php');
if(!isset($_SESSION))
{
    session_start();

// Validate session timeout
require_once('../../security/session_timeout_middleware.php');
validateActiveSession();
} 

include_once '../../config/db.class.php';
date_default_timezone_set("Asia/Kolkata");

// Verify CSRF token
if (!isset($_SESSION['csrf_token']) || !isset($_GET['csrf_token']) || $_SESSION['csrf_token'] !== $_GET['csrf_token']) {
    echo '<div class="alert alert-danger">Invalid session. Please refresh the page and try again.</div>';
    exit;
}

// Security check
if (!isset($_GET['val_wf_id']) || !isset($_GET['iteration_id'])) {
    echo '<div class="alert alert-danger">Missing required parameters.</div>';
    exit;
}

$val_wf_id = $_GET['val_wf_id'];
$iteration_id = $_GET['iteration_id'];

// Get approval details with user names
$query = "SELECT 
    t1.*,
    u1.user_name AS user_dept_approver_name,
    u2.user_name AS eng_approver_name,
    u3.user_name AS hse_approver_name,
    u4.user_name AS qc_approver_name,
    u5.user_name AS qa_approver_name,
    u6.user_name AS head_qa_level2_approver_name,
    u7.user_name AS unit_head_level3_approver_name,
    u8.user_name AS unit_head_level2_approver_name,
    u9.user_name AS head_qa_level3_approver_name
FROM 
    tbl_val_wf_approval_tracking_details t1
LEFT JOIN 
    users u1 ON t1.level1_user_dept_approval_by = u1.user_id
LEFT JOIN 
    users u2 ON t1.level1_eng_approval_by = u2.user_id
LEFT JOIN 
    users u3 ON t1.level1_hse_approval_by = u3.user_id
LEFT JOIN 
    users u4 ON t1.level1_qc_approval_by = u4.user_id
LEFT JOIN 
    users u5 ON t1.level1_qa_approval_by = u5.user_id
LEFT JOIN 
    users u6 ON t1.level2_head_qa_approval_by = u6.user_id
LEFT JOIN 
    users u7 ON t1.level3_unit_head_approval_by = u7.user_id
LEFT JOIN 
    users u8 ON t1.level2_unit_head_approval_by = u8.user_id
LEFT JOIN 
    users u9 ON t1.level3_head_qa_approval_by = u9.user_id
WHERE 
    t1.val_wf_id = %s
    AND t1.iteration_id = %i";

$result = DB::queryFirstRow($query, $val_wf_id, $iteration_id);

if (empty($result)) {
    echo '<div class="alert alert-warning">No approval details found for this iteration.</div>';
    exit;
}

// Format the data for display
$output = "<div class='pl-0 pr-3 pt-0 pb-3'><h5 class='mb-3 text-left'>Approval Details for Iteration #" . htmlspecialchars($iteration_id) . "</h5></div>";
$output .= "<table class='table table-bordered table-striped'>";
$output .= "<thead><tr><th>Approval/Sent Back Date/Time</th><th>Approved/Sent Back By</th><th>Remarks</th></tr></thead>";
$output .= "<tbody>";

// Helper function to format date
function formatDate($date) {
    if (empty($date)) return "-";
    return date('d.m.Y H:i:s', strtotime($date));
}

// Helper function to render a row
function renderRow($label, $datetime, $approver, $remarks) {
    if (empty($datetime) && empty($approver)) return "";
    
    $html = "<tr>";
    $html .= "<td>" . (empty($datetime) ? "-" : formatDate($datetime)) . "</td>";
    $html .= "<td>" . (empty($approver) ? "-" : htmlspecialchars($approver)) . "</td>";
    $html .= "<td>" . (empty($remarks) ? "-" : htmlspecialchars($remarks)) . "</td>";
    $html .= "</tr>";
    
    return $html;
}

// User Department (Level 1)
if (!empty($result['level1_user_dept_approval_datetime']) || !empty($result['user_dept_approver_name'])) {
    $output .= "<tr class='table-primary'><th colspan='3'>User Department Approval (Level 1)</th></tr>";
    $output .= renderRow(
        "User Department", 
        $result['level1_user_dept_approval_datetime'], 
        $result['user_dept_approver_name'], 
        $result['level1_user_dept_approval_remarks']
    );
}

// Engineering (Level 1)
if (!empty($result['level1_eng_approval_datetime']) || !empty($result['eng_approver_name'])) {
    $output .= "<tr class='table-primary'><th colspan='3'>Engineering Approval (Level 1)</th></tr>";
    $output .= renderRow(
        "Engineering", 
        $result['level1_eng_approval_datetime'], 
        $result['eng_approver_name'], 
        $result['level1_eng_approval_remarks']
    );
}

// EHS (Level 1)
if (!empty($result['level1_hse_approval_datetime']) || !empty($result['hse_approver_name'])) {
    $output .= "<tr class='table-primary'><th colspan='3'>EHS Approval (Level 1)</th></tr>";
    $output .= renderRow(
        "EHS", 
        $result['level1_hse_approval_datetime'], 
        $result['hse_approver_name'], 
        $result['level1_hse_approval_remarks']
    );
}

// Quality Control (Level 1)
if (!empty($result['level1_qc_approval_datetime']) || !empty($result['qc_approver_name'])) {
    $output .= "<tr class='table-primary'><th colspan='3'>Quality Control Approval (Level 1)</th></tr>";
    $output .= renderRow(
        "Quality Control", 
        $result['level1_qc_approval_datetime'], 
        $result['qc_approver_name'], 
        $result['level1_qc_approval_remarks']
    );
}

// Quality Assurance (Level 1)
if (!empty($result['level1_qa_approval_datetime']) || !empty($result['qa_approver_name'])) {
    $output .= "<tr class='table-primary'><th colspan='3'>Quality Assurance Approval (Level 1)</th></tr>";
    $output .= renderRow(
        "Quality Assurance", 
        $result['level1_qa_approval_datetime'], 
        $result['qa_approver_name'], 
        $result['level1_qa_approval_remarks']
    );
}

// QA Head (Level 2)
if (!empty($result['level2_head_qa_approval_datetime']) || !empty($result['head_qa_level2_approver_name'])) {
    $output .= "<tr class='table-primary'><th colspan='3'>QA Head Approval (Level 2)</th></tr>";
    $output .= renderRow(
        "QA Head", 
        $result['level2_head_qa_approval_datetime'], 
        $result['head_qa_level2_approver_name'], 
        $result['level2_head_qa_approval_remarks']
    );
}

// Unit Head (Level 2)
if (!empty($result['level2_unit_head_approval_datetime']) || !empty($result['unit_head_level2_approver_name'])) {
    $output .= "<tr class='table-primary'><th colspan='3'>Unit Head Approval (Level 2)</th></tr>";
    $output .= renderRow(
        "Unit Head", 
        $result['level2_unit_head_approval_datetime'], 
        $result['unit_head_level2_approver_name'], 
        $result['level2_unit_head_approval_remarks']
    );
}

// Unit Head (Level 3)
if (!empty($result['level3_unit_head_approval_datetime']) || !empty($result['unit_head_level3_approver_name'])) {
    $output .= "<tr class='table-primary'><th colspan='3'>Unit Head Approval (Level 3)</th></tr>";
    $output .= renderRow(
        "Unit Head", 
        $result['level3_unit_head_approval_datetime'], 
        $result['unit_head_level3_approver_name'], 
        $result['level3_unit_head_approval_remarks']
    );
}

// QA Head (Level 3)
if (!empty($result['level3_head_qa_approval_datetime']) || !empty($result['head_qa_level3_approver_name'])) {
    $output .= "<tr class='table-primary'><th colspan='3'>QA Head Approval (Level 3)</th></tr>";
    $output .= renderRow(
        "QA Head", 
        $result['level3_head_qa_approval_datetime'], 
        $result['head_qa_level3_approver_name'], 
        $result['level3_head_qa_approval_remarks']
    );
}

$output .= "</tbody></table>";

if (!empty($result['protocol_report_path'])) {
    $output .= "<div class='mt-3'><strong>Protocol Report Path:</strong> " . htmlspecialchars($result['protocol_report_path']) . "</div>";
}

echo $output;
?>