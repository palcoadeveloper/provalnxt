<?php

// Load configuration first
require_once(__DIR__ . '/../../config/config.php');

// Session is already started by config.php via session_init.php
// Validate session timeout
require_once('../../security/session_timeout_middleware.php');
validateActiveSession();
include_once(__DIR__ . "/../../pdf/FPDF_CellFit_Audit.php");

require_once(__DIR__ . '/../../../vendor/autoload.php');
include_once ("../../config/db.class.php");
date_default_timezone_set("Asia/Kolkata");

//Show All PHP Errors
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if(!isset($_SESSION['user_name']))
{
   header('Location:'.BASE_URL .'login.php');
   exit;
}
 
$unit_id=$_GET['unit_id']==99?0:$_GET['unit_id'];

if($_GET['unit_id']==99)
{
    $unit_id=0;
}
else if($_GET['unit_id']=='select')
{
    $unit_id=null;
}
else
{
    $unit_id=$_GET['unit_id'];
}

// Define the audit category SQL conditions
if($_GET['audittrailcategory']=='tran_login_int_emp')
{
    $audit_category="('tran_login_int_emp')";
}
else if($_GET['audittrailcategory']=='tran_login_ext_emp')
{
    $audit_category="('tran_login_ext_emp')";
}
else if($_GET['audittrailcategory']=='tran_logout')
{
    $audit_category="('tran_logout')";
}
else if($_GET['audittrailcategory']=='tran_password_reset')
{
    $audit_category="('acct_locked_resetf', 'acct_inactive_resetf', 'tran_password_reset', 'tran_password_resetf', 'tran_password_resetf')";
}
else if($_GET['audittrailcategory']=='tran_schgen')
{
    $audit_category="('tran_traindtls_removed', 'tran_rsch_gen', 'tran_rsch_rej_eng', 'tran_rsch_rej_qa', 'tran_rsch_app_eng', 'tran_rsch_app_qa', 'tran_vsch_gen', 'tran_vsch_app_eng', 'tran_vsch_app_qa', 'tran_vsch_rej_eng', 'tran_vsch_rej_qa')";
}
else if($_GET['audittrailcategory']=='tran_valbgn')
{
    $audit_category="('tran_rtbgn', 'tran_valbgn', 'tran_ireview_approve', 'tran_ereview_approve', 'tran_file_upload', 'tran_iupload_files_app', 'tran_eupload_files_app', 'tran_upload_files_rej', 'tran_teamapp_eng', 'tran_teamapp_qa', 'tran_teamapp_ehs', 'tran_teamapp_qc', 'tran_teamapp_user', 'tran_level2app_uh', 'tran_level3app_qh', 'tran_print_report')";
}
else if($_GET['audittrailcategory']=='tran_approve')
{
    $audit_category="('tran_rtbgn', 'tran_valbgn', 'tran_ireview_approve', 'tran_ereview_approve', 'tran_file_upload', 'tran_iupload_files_app', 'tran_eupload_files_app', 'tran_upload_files_rej', 'tran_teamapp_eng', 'tran_teamapp_qa', 'tran_teamapp_ehs', 'tran_teamapp_qc', 'tran_teamapp_user', 'tran_level2app_uh', 'tran_level3app_qh', 'tran_print_report')";
}
else if($_GET['audittrailcategory']=='tran_review_approve')
{
    $audit_category="('tran_rtbgn', 'tran_valbgn', 'tran_ireview_approve', 'tran_ereview_approve', 'tran_file_upload', 'tran_iupload_files_app', 'tran_eupload_files_app', 'tran_upload_files_rej', 'tran_teamapp_eng', 'tran_teamapp_qa', 'tran_teamapp_ehs', 'tran_teamapp_qc', 'tran_teamapp_user', 'tran_level2app_uh', 'tran_level3app_qh', 'tran_print_report')";
}
else if($_GET['audittrailcategory']=='master_update')
{
    $audit_category="('master_add_eq', 'master_update_eq', 'master_add_etv', 'master_update_etv', 'master_add_test', 'master_update_test', 'master_users_add', 'master_users_addc', 'master_users_updatec', 'master_users_updatev', 'master_add_vendors', 'master_update_vendors')";
}

// Store original date formats
$start_from = $_GET['start_from'];
$start_to = $_GET['start_to'];

// Convert date formats for SQL queries if dates are provided
$sql_start_from = !empty($start_from) ? date('Y-m-d', strtotime($start_from)) : null;
$sql_start_to = !empty($start_to) ? date('Y-m-d', strtotime($start_to)) : null;

// Query construction
if($_GET['audittrailcategory'] != 'any')
{
    if(empty($start_from) && empty($start_to))
    {
        if($unit_id === null)
        {
            $results = DB::query("SELECT change_type, change_description, user_name, change_by, change_datetime 
                                FROM log l 
                                LEFT JOIN users u ON l.change_by = u.user_id 
                                WHERE change_type IN $audit_category 
                                AND change_datetime <= CURRENT_TIMESTAMP() 
                                ORDER BY change_datetime");
        }
        else
        {
            $results = DB::query("SELECT change_type, change_description, user_name, change_by, change_datetime 
                                FROM log l 
                                LEFT JOIN users u ON l.change_by = u.user_id 
                                WHERE change_type IN $audit_category 
                                AND change_datetime <= CURRENT_TIMESTAMP() 
                                AND l.unit_id = $unit_id 
                                ORDER BY change_datetime");
        }
    }
    else if(!empty($start_from) && empty($start_to))
    {
        if($unit_id === null)
        {
            $results = DB::query("SELECT change_type, change_description, user_name, change_by, change_datetime 
                                FROM log l 
                                LEFT JOIN users u ON l.change_by = u.user_id 
                                WHERE change_type IN $audit_category 
                                AND DATE(change_datetime) BETWEEN '$sql_start_from' AND CURRENT_DATE() 
                                ORDER BY change_datetime");
        }
        else
        {
            $results = DB::query("SELECT change_type, change_description, user_name, change_by, change_datetime 
                                FROM log l 
                                LEFT JOIN users u ON l.change_by = u.user_id 
                                WHERE change_type IN $audit_category 
                                AND DATE(change_datetime) BETWEEN '$sql_start_from' AND CURRENT_DATE() 
                                AND l.unit_id = $unit_id 
                                ORDER BY change_datetime");
        }
    }
    else if(empty($start_from) && !empty($start_to))
    {
        if($unit_id === null)
        {
            $results = DB::query("SELECT change_type, change_description, user_name, change_by, change_datetime 
                                FROM log l 
                                LEFT JOIN users u ON l.change_by = u.user_id 
                                WHERE change_type IN $audit_category 
                                AND DATE(change_datetime) <= '$sql_start_to' 
                                ORDER BY change_datetime");
        }
        else
        {
            $results = DB::query("SELECT change_type, change_description, user_name, change_by, change_datetime 
                                FROM log l 
                                LEFT JOIN users u ON l.change_by = u.user_id 
                                WHERE change_type IN $audit_category 
                                AND DATE(change_datetime) <= '$sql_start_to' 
                                AND l.unit_id = $unit_id 
                                ORDER BY change_datetime");
        }
    }
    else if(!empty($start_from) && !empty($start_to))
    {
        if($unit_id === null)
        {
            $results = DB::query("SELECT change_type, change_description, user_name, change_by, change_datetime 
                                FROM log l 
                                LEFT JOIN users u ON l.change_by = u.user_id 
                                WHERE change_type IN $audit_category 
                                AND DATE(change_datetime) BETWEEN '$sql_start_from' AND '$sql_start_to' 
                                ORDER BY change_datetime");
        }
        else
        {
            $results = DB::query("SELECT change_type, change_description, user_name, change_by, change_datetime 
                                FROM log l 
                                LEFT JOIN users u ON l.change_by = u.user_id 
                                WHERE change_type IN $audit_category 
                                AND DATE(change_datetime) BETWEEN '$sql_start_from' AND '$sql_start_to' 
                                AND l.unit_id = $unit_id 
                                ORDER BY change_datetime");
        }
    }
}
else // For 'any' category
{
    if(empty($start_from) && empty($start_to))
    {
        if($unit_id === null)
        {
            $results = DB::query("SELECT change_type, change_description, user_name, change_by, change_datetime 
                                FROM log l 
                                LEFT JOIN users u ON l.change_by = u.user_id 
                                WHERE change_datetime <= CURRENT_TIMESTAMP() 
                                ORDER BY change_datetime");
        }
        else
        {
            $results = DB::query("SELECT change_type, change_description, user_name, change_by, change_datetime 
                                FROM log l 
                                LEFT JOIN users u ON l.change_by = u.user_id 
                                WHERE change_datetime <= CURRENT_TIMESTAMP() 
                                AND l.unit_id = $unit_id 
                                ORDER BY change_datetime");
        }
    }
    else if(!empty($start_from) && empty($start_to))
    {
        if($unit_id === null)
        {
            $results = DB::query("SELECT change_type, change_description, user_name, change_by, change_datetime 
                                FROM log l 
                                LEFT JOIN users u ON l.change_by = u.user_id 
                                WHERE DATE(change_datetime) BETWEEN '$sql_start_from' AND CURRENT_DATE() 
                                ORDER BY change_datetime");
        }
        else
        {
            $results = DB::query("SELECT change_type, change_description, user_name, change_by, change_datetime 
                                FROM log l 
                                LEFT JOIN users u ON l.change_by = u.user_id 
                                WHERE DATE(change_datetime) BETWEEN '$sql_start_from' AND CURRENT_DATE() 
                                AND l.unit_id = $unit_id 
                                ORDER BY change_datetime");
        }
    }
    else if(empty($start_from) && !empty($start_to))
    {
        if($unit_id === null)
        {
            $results = DB::query("SELECT change_type, change_description, user_name, change_by, change_datetime 
                                FROM log l 
                                LEFT JOIN users u ON l.change_by = u.user_id 
                                WHERE DATE(change_datetime) <= '$sql_start_to' 
                                ORDER BY change_datetime");
        }
        else
        {
            $results = DB::query("SELECT change_type, change_description, user_name, change_by, change_datetime 
                                FROM log l 
                                LEFT JOIN users u ON l.change_by = u.user_id 
                                WHERE DATE(change_datetime) <= '$sql_start_to' 
                                AND l.unit_id = $unit_id 
                                ORDER BY change_datetime");
        }
    }
    else if(!empty($start_from) && !empty($start_to))
    {
        if($unit_id === null)
        {
            $results = DB::query("SELECT change_type, change_description, user_name, change_by, change_datetime 
                                FROM log l 
                                LEFT JOIN users u ON l.change_by = u.user_id 
                                WHERE DATE(change_datetime) BETWEEN '$sql_start_from' AND '$sql_start_to' 
                                ORDER BY change_datetime");
        }
        else
        {
            $results = DB::query("SELECT change_type, change_description, user_name, change_by, change_datetime 
                                FROM log l 
                                LEFT JOIN users u ON l.change_by = u.user_id 
                                WHERE DATE(change_datetime) BETWEEN '$sql_start_from' AND '$sql_start_to' 
                                AND l.unit_id = $unit_id 
                                ORDER BY change_datetime");
        }
    }
}

// Generate PDF for modal viewing
$html1 = '<html><head>
<style>
  table {
    font-family: sans-serif;
    font-size: 12px;
    border-collapse: collapse;
    border-color: gray;
    border-width: 1px;
}

td {
    padding: 5px;
    border: 1px solid gray;
    vertical-align: middle;
    border-color: gray;
}

th {
    padding: 5px;
    border: 1px solid gray;
    vertical-align: middle;
    border-color: gray;
}
</style>
</head>
<body><table border=1 width="100%">
<thead>
    <tr>
        <th>Change Type</th>
        <th>Change Description</th>
        <th>Change By</th>
        <th>Change DateTime</th>
    </tr>
</thead>';

$html2 = '';
foreach ($results as $row) {
    $html2 .= '<tr><td>'. $row['change_type'].'</td>';
    $html2 .= '<td>'. $row['change_description'].'</td>';
    $html2 .= '<td>'. (!empty($row["user_name"]) ? $row["user_name"] : "-").'</td>';
    $html2 .= '<td>'.date('d.m.Y H:i:s', strtotime($row['change_datetime'])).'</td></tr>';
}     
            
$html3 = '</table></body></html>';

$from_date = '';
$to_date = '';

if(empty($start_from)) {
    $from_date = "Since inception";
} else {
    $from_date = date('d.m.Y', strtotime($start_from));
}

if(empty($start_to)) {
    $to_date = date('d.m.Y');
} else {
    $to_date = date('d.m.Y', strtotime($start_to));
}

// Set the HTML header
$header = '
    <div Style="text-align:right;"><img src="../../../assets/images/logo.png" width="48" height="20"/></div>
    <div Style="text-align:right;">Goa</div>
    
    <table width="100%" cellpadding="7" cellspacing="0" border="1">
    <tr>
        <td colspan="3" Style="text-align:center;"><b>AUDIT TRAIL LOG REPORT - UNIT '.$unit_id.' </b></td>
    </tr>
    <tr>
        <td width="40%">From Date: '.$from_date.'</td>
        <td width="40%">To Date: '.$to_date.'</td>
        <td width="20%"> Page {PAGENO} of {nb}</td>
    </tr>
    </table>';

// Create mPDF object
$mpdf = new \Mpdf\Mpdf(['setAutoTopMargin' => 'stretch', 'setAutoBottomMargin' => 'stretch', 'default_font' => 'Arial', 'default_font_size' => 9]);

$mpdf->SetHTMLHeader($header);

// Set the HTML footer
$requester_details=DB::queryFirstRow("select user_name, department_name, unit_name,u.unit_id
from users u left join departments d
on u.department_id=d.department_id
left join units un
on u.unit_id=un.unit_id where user_id=".$_SESSION['user_id']);
$user_name = isset($_SESSION['user_name']) ? $_SESSION['user_name'] : 'System User';
$department_name = isset($_SESSION['department_name']) ? $_SESSION['department_name'] : 'Admin';
$unit_name = isset($_SESSION['unit_name']) ? $_SESSION['unit_name'] : 'Unit';

$footer = '
    <div style="text-align:center; font-size: 8px; color: #666;">
        '.ucwords('Document printed by '.$user_name.' '.$department_name.'-'.$unit_name.'/GOA '.date("d.m.Y H:i:s")).'
    </div>';

$mpdf->SetHTMLFooter($footer);

// Add the HTML content to mPDF
$mpdf->WriteHTML($html1.$html2.$html3);

// Clear any output buffers
while (ob_get_level()) {
    ob_end_clean();
}

// Output the PDF inline for modal viewing
$mpdf->Output('AuditTrailLogReport-' . date('d.m.Y') . '.pdf', 'I');
?> 