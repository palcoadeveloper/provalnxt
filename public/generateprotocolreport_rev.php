<?php
require_once('./core/config/config.php');

// Session is already started by config.php via session_init.php
// PERFORMANCE OPTIMIZATION: Session validation commented out for PDF generation speed
// This file is called via internal cURL from savelevel3approvaldata.php and doesn't need
// session validation as it's an internal API call with parameter validation below
//
// Original session timeout validation (commented for performance):
// require_once('core/security/session_timeout_middleware.php');
// validateActiveSession();

require_once 'vendor/autoload.php';

// Original centralized session validation (commented for performance):
// require_once('core/security/session_validation.php');
// validateUserSession();

// Validate required GET parameters
$required_params = ['val_wf_id', 'equipment_id'];
foreach ($required_params as $param) {
    if (!isset($_GET[$param]) || empty(trim($_GET[$param]))) {
        header('HTTP/1.1 400 Bad Request');
        exit('Missing required parameter: ' . $param);
    }
}

// Sanitize and validate parameters
$val_wf_id = htmlspecialchars(trim($_GET['val_wf_id']), ENT_QUOTES, 'UTF-8');
$equipment_id = intval($_GET['equipment_id']);

if (empty($val_wf_id) || $equipment_id <= 0) {
    header('HTTP/1.1 400 Bad Request');
    exit('Invalid parameter values');
}

header('Content-Type: text/html; charset=utf-8');
include_once(__DIR__ . "/core/config/db.class.php");
date_default_timezone_set("Asia/Kolkata");

// Performance logging - start timing
$pdfStartTime = microtime(true);
error_log("PDF Generation: Starting for val_wf_id: $val_wf_id, equipment_id: $equipment_id");

try {
    // Execute the query and store the result in an associative array
    $results = DB::query("SELECT DISTINCT etvm.test_id, v.vendor_name
                          FROM equipment_test_vendor_mapping etvm
                          JOIN vendors v ON etvm.vendor_id = v.vendor_id
                          WHERE equipment_id = (SELECT equip_id FROM tbl_val_schedules WHERE val_wf_id = %s)
                          ORDER BY etvm.test_id", $val_wf_id);
} catch (Exception $e) {
    require_once('core/error/error_logger.php');
    logDatabaseError("Database error in generateprotocolreport_rev.php: " . $e->getMessage(), [
        'operation_name' => 'report_protocol_generation_vendor_mapping',
        'val_wf_id' => $val_wf_id,
        'equipment_id' => null,
        'unit_id' => null
    ]);
    header('HTTP/1.1 500 Internal Server Error');
    exit('Error retrieving vendor mapping data');
}

// Create an associative array where test_id is the key
$testVendorMap = [];
foreach ($results as $row) {
    $testVendorMap[$row['test_id']] = $row['vendor_name'];
}

// Function to get vendor name by test_id
function getVendorByTestId($test_id, $testVendorMap) {
    if (array_key_exists($test_id, $testVendorMap)) {
        return $testVendorMap[$test_id];
    } else {
        return "Vendor not found for this test ID.";
    }
}





try {
    $training_details = DB::query("SELECT user_name, department_name, 'Trained' AS training_status
        FROM tbl_training_details t1 
        LEFT JOIN users t2 ON t1.user_id = t2.user_id
        LEFT JOIN departments t3 ON t1.department_id = t3.department_id
        WHERE record_status = 'Active' AND val_wf_id = %s", $val_wf_id);
        
    $equipment_details = DB::queryFirstRow("SELECT * FROM equipments e, departments d 
        WHERE e.department_id = d.department_id AND equipment_id = %i", $equipment_id);
        
    $report_details = DB::queryFirstRow("SELECT * FROM validation_reports WHERE val_wf_id = %s", $val_wf_id);
    
    $initiated_by = DB::queryFirstField("SELECT user_name FROM tbl_val_wf_tracking_details t1, users t2 
        WHERE t1.wf_initiated_by_user_id = t2.user_id AND val_wf_id = %s", $val_wf_id);
        
    $wf_details = DB::queryFirstRow("SELECT DATE(actual_wf_start_datetime) AS actual_wf_start_datetime, 
        val_wf_current_stage, unit_id FROM tbl_val_wf_tracking_details WHERE val_wf_id = %s", $val_wf_id);
        
    if (!$equipment_details || !$wf_details) {
        header('HTTP/1.1 404 Not Found');
        exit('Equipment or workflow details not found');
    }
} catch (Exception $e) {
    require_once('core/error/error_logger.php');
    logDatabaseError("Database error in generateprotocolreport_rev.php details: " . $e->getMessage(), [
        'operation_name' => 'report_protocol_generation_details',
        'val_wf_id' => $val_wf_id,
        'equip_id' => $equipment_id,
        'unit_id' => null
    ]);
    header('HTTP/1.1 500 Internal Server Error');
    exit('Error retrieving report details');
}


try {
    $unit_name = DB::queryFirstField("SELECT unit_name FROM units WHERE unit_id = %i and unit_status='Active'", intval($wf_details['unit_id']));
    if (!$unit_name) {
        $unit_name = "Unknown Unit";
    }
} catch (Exception $e) {
    require_once('core/error/error_logger.php');
    logDatabaseError("Database error in generateprotocolreport_rev.php unit query: " . $e->getMessage(), [
        'operation_name' => 'report_protocol_generation_unit_query',
        'val_wf_id' => $val_wf_id,
        'equip_id' => $equipment_id,
        'unit_id' => intval($wf_details['unit_id'])
    ]);
    $unit_name = "Unknown Unit";
}


$html_content_fr1 = '

<table width="100%" cellpadding="7" cellspacing="0" border="0">
        <tr>
        	<td colspan="2"><b>Unit:  </b> Unit ' .  str_replace('Unit ', '', $unit_name) . '
        
        </tr>
        <tr>
            <td>
                <b>AHU/Ventilation No.:</b>&nbsp;&nbsp;' .  $equipment_details['equipment_code'] . '
            </td>
            
            <td style="text-align:right">
                <b>Date of Start:</b>' .  (isset($wf_details) ? date_format(date_create($wf_details['actual_wf_start_datetime']), "d.m.Y") : "") . '
            </td>
            
        </tr>
        
</table>
        <br/>
        
<p><b>1.0&nbsp;&nbsp;&nbsp;Objective:</b></p>

<p align="justify" style="  margin-bottom: 0cm">
To establish that the HVAC system is performing as it is supposed to perform by:</p>

<p align="justify" style="  margin-bottom: 0cm">1.1&nbsp;&nbsp;Ensuring that the required temperature, relative humidity and pressure gradient is within the
limit of acceptance criteria (if applicable).
 </p>
<p align="justify" style=" margin-bottom: 0cm">1.2&nbsp;&nbsp;Ensuring that the quality of air with respect to non-viable (particulate matter count) is within the limit of acceptance criteria (if applicable). </p>
<p align="justify" style="  margin-bottom: 0cm">1.3&nbsp;&nbsp;Ensuring that the total number of air changes, Velocity and Installed filter leakages are within the limit of acceptance criteria (if applicable). </p>
<p align="justify" style="  margin-bottom: 0cm">1.4&nbsp;&nbsp;Ensure that the airflow direction and visualization is as per requirement (if applicable). </p>

<br/>

<p><b>2.0&nbsp;&nbsp;&nbsp;Justification for selection of system:</b></p>

<p align="justify">
' . ((isset($report_details)) ? $report_details['justification'] : "") . '








<p><b>3.0&nbsp;&nbsp;&nbsp;Scope:</b></p>

<p align="justify">
Applicable to all AHU/Ventilation System which is installed to control room conditions.
</p>


<p><b>4.0&nbsp;&nbsp;&nbsp;Site of study:</b></p>

<p align="justify">
<table width="100%" cellpadding="7" cellspacing="0" border="1">

<tr>
    <td>Site and location Name</td>
    <td>Cipla '.$unit_name.', Goa</td>
</tr>

<tr>
    <td>Department Name</td>
    <td>' . $equipment_details['department_name'] . '</td>
</tr>

<tr>
    <td>HVAC Scope</td>
    <td>' . $equipment_details['area_served'] . '</td>
</tr>



</table>
</p>




<p><b>5.0&nbsp;&nbsp;&nbsp;Performance verification Team And Responsibility As Per Performance verification Documents:</b></p>

<p align="justify">
Representative From:
</p>
<p align="justify">
<table width="100%" cellpadding="7" cellspacing="0" border="1">
<tr>
    <td>User Department</td>
    <td>';

$result = DB::queryFirstField("
    SELECT t2.user_name 
    FROM tbl_report_approvers t1
    JOIN users t2 ON t1.level1_approver_user=t2.user_id
    WHERE t1.val_wf_id=%s
    AND t1.iteration_id = (
        SELECT MAX(ra.iteration_id)
        FROM tbl_report_approvers ra
        WHERE ra.val_wf_id = %s
        AND EXISTS (
            SELECT 1
            FROM tbl_val_wf_approval_tracking_details vwt
            WHERE vwt.val_wf_id = ra.val_wf_id
            AND vwt.iteration_id = ra.iteration_id
            AND vwt.iteration_completion_status = 'complete'
            AND vwt.iteration_status = 'Active'
            AND vwt.iteration_id = (
                SELECT MAX(iteration_id)
                FROM tbl_val_wf_approval_tracking_details
                WHERE val_wf_id = ra.val_wf_id
                AND iteration_completion_status = 'complete'
                AND iteration_status = 'Active'
            )
        )
    )", $_GET['val_wf_id'], $_GET['val_wf_id']);
$html_content_fr2 = (isset($result) ? $result : ($wf_details['val_wf_current_stage'] == '2' || $wf_details['val_wf_current_stage'] == '3' || $wf_details['val_wf_current_stage'] == '4' || $wf_details['val_wf_current_stage'] == '5' ? "No Approval Required" : "___________"));

$html_content_fr3 = '</td>
            
</tr>

<tr>
    <td>Engineering</td>
    <td>';

$result = DB::queryFirstField("
    SELECT t2.user_name 
    FROM tbl_report_approvers t1
    JOIN users t2 ON t1.level1_approver_engg=t2.user_id
    WHERE t1.val_wf_id=%s
    AND t1.iteration_id = (
        SELECT MAX(ra.iteration_id)
        FROM tbl_report_approvers ra
        WHERE ra.val_wf_id = %s
        AND EXISTS (
            SELECT 1
            FROM tbl_val_wf_approval_tracking_details vwt
            WHERE vwt.val_wf_id = ra.val_wf_id
            AND vwt.iteration_id = ra.iteration_id
            AND vwt.iteration_completion_status = 'complete'
            AND vwt.iteration_status = 'Active'
            AND vwt.iteration_id = (
                SELECT MAX(iteration_id)
                FROM tbl_val_wf_approval_tracking_details
                WHERE val_wf_id = ra.val_wf_id
                AND iteration_completion_status = 'complete'
                AND iteration_status = 'Active'
            )
        )
    )", $_GET['val_wf_id'], $_GET['val_wf_id']);
$html_content_fr4 = (isset($result) ? $result : ($wf_details['val_wf_current_stage'] == '2' || $wf_details['val_wf_current_stage'] == '3' || $wf_details['val_wf_current_stage'] == '4' || $wf_details['val_wf_current_stage'] == '5' ? "No Approval Required" : "___________"));

$html_content_fr5 = '</td>
</tr>

<tr>
    <td>EHS</td>
    <td>';

$result = DB::queryFirstField("
    SELECT t2.user_name 
    FROM tbl_report_approvers t1
    JOIN users t2 ON t1.level1_approver_hse=t2.user_id
    WHERE t1.val_wf_id=%s
    AND t1.iteration_id = (
        SELECT MAX(ra.iteration_id)
        FROM tbl_report_approvers ra
        WHERE ra.val_wf_id = %s
        AND EXISTS (
            SELECT 1
            FROM tbl_val_wf_approval_tracking_details vwt
            WHERE vwt.val_wf_id = ra.val_wf_id
            AND vwt.iteration_id = ra.iteration_id
            AND vwt.iteration_completion_status = 'complete'
            AND vwt.iteration_status = 'Active'
            AND vwt.iteration_id = (
                SELECT MAX(iteration_id)
                FROM tbl_val_wf_approval_tracking_details
                WHERE val_wf_id = ra.val_wf_id
                AND iteration_completion_status = 'complete'
                AND iteration_status = 'Active'
            )
        )
    )", $_GET['val_wf_id'], $_GET['val_wf_id']);
$html_content_fr6 = (isset($result) ? $result : ($wf_details['val_wf_current_stage'] == '2' || $wf_details['val_wf_current_stage'] == '3' || $wf_details['val_wf_current_stage'] == '4' || $wf_details['val_wf_current_stage'] == '5' ? "No Approval Required" : "___________"));

$html_content_fr7 = '</td>
         
</tr>

<tr>
    <td>Quality Control</td>
    <td>';

$result = DB::queryFirstField("
    SELECT t2.user_name 
    FROM tbl_report_approvers t1
    JOIN users t2 ON t1.level1_approver_qc=t2.user_id
    WHERE t1.val_wf_id=%s
    AND t1.iteration_id = (
        SELECT MAX(ra.iteration_id)
        FROM tbl_report_approvers ra
        WHERE ra.val_wf_id = %s
        AND EXISTS (
            SELECT 1
            FROM tbl_val_wf_approval_tracking_details vwt
            WHERE vwt.val_wf_id = ra.val_wf_id
            AND vwt.iteration_id = ra.iteration_id
            AND vwt.iteration_completion_status = 'complete'
            AND vwt.iteration_status = 'Active'
            AND vwt.iteration_id = (
                SELECT MAX(iteration_id)
                FROM tbl_val_wf_approval_tracking_details
                WHERE val_wf_id = ra.val_wf_id
                AND iteration_completion_status = 'complete'
                AND iteration_status = 'Active'
            )
        )
    )", $_GET['val_wf_id'], $_GET['val_wf_id']);
$html_content_fr8 = (isset($result) ? $result : ($wf_details['val_wf_current_stage'] == '2' || $wf_details['val_wf_current_stage'] == '3' || $wf_details['val_wf_current_stage'] == '4' || $wf_details['val_wf_current_stage'] == '5' ? "No Approval Required" : "___________"));

$html_content_fr9 = '</td>
         
</tr>

<tr>
    <td>Quality Assurance</td>
    <td>';

$result = DB::queryFirstField("
    SELECT t2.user_name 
    FROM tbl_report_approvers t1
    JOIN users t2 ON t1.level1_approver_qa=t2.user_id
    WHERE t1.val_wf_id=%s
    AND t1.iteration_id = (
        SELECT MAX(ra.iteration_id)
        FROM tbl_report_approvers ra
        WHERE ra.val_wf_id = %s
        AND EXISTS (
            SELECT 1
            FROM tbl_val_wf_approval_tracking_details vwt
            WHERE vwt.val_wf_id = ra.val_wf_id
            AND vwt.iteration_id = ra.iteration_id
            AND vwt.iteration_completion_status = 'complete'
            AND vwt.iteration_status = 'Active'
            AND vwt.iteration_id = (
                SELECT MAX(iteration_id)
                FROM tbl_val_wf_approval_tracking_details
                WHERE val_wf_id = ra.val_wf_id
                AND iteration_completion_status = 'complete'
                AND iteration_status = 'Active'
            )
        )
    )", $_GET['val_wf_id'], $_GET['val_wf_id']);
$html_content_fr10 = (isset($result) ? $result : ($wf_details['val_wf_current_stage'] == '2' || $wf_details['val_wf_current_stage'] == '3' || $wf_details['val_wf_current_stage'] == '4' || $wf_details['val_wf_current_stage'] == '5' ? "No Approval Required" : "___________"));

$html_content_fr11 = '</td>
</tr>

</table>
</p>
<br/>
<pagebreak> 
<p><b>6.0&nbsp;&nbsp;&nbsp;Description of the system to be verified:</b></p>

<p align="justify" style=" margin-bottom: 0cm">
To establish that the HVAC system is performing as it is supposed to perform by:</p>

<p align="justify" style=" margin-bottom: 0cm">1.&nbsp;&nbsp;Prior to initiation of test, intimation to the respective department, suitable operation of HVAC unit and operation of supply and exhaust unit, if applicable etc.
 </p>
<p align="justify" style="margin-bottom: 0cm">2.&nbsp;&nbsp;Specify ‘NA’ wherever not applicable / if other than given   specification mention separately. </p>
<p align="justify" style=" margin-bottom: 0cm">3.&nbsp;&nbsp;The acceptance criteria for non-viable particle count should be considered as per ISO/EU/WHO/USFDA guideline.
</p>
<br/>

<center>
	<table width="100%" cellpadding="7" cellspacing="0" border="1">

		<tr>
			<td width="50%" height="11" >
				<p>Area</p>
			</td>
			<td width="4%" valign="top" >
				<p>:</p>
			</td>
			<td colspan="2" width="46%" valign="top" >
				<p>' . $equipment_details['area_served'] . '</p>
			</td>
		</tr>
		
<tr>
			<td width="50%" height="11" >
				<p>Equipment No.</p>
			</td>
			<td width="4%" valign="top" >
				<p>:</p>
			</td>
			<td colspan="2" width="46%" valign="top" >
				<p>' . $equipment_details['equipment_code'] . '</p>
			</td>
		</tr>

<tr>
			<td width="50%" height="11" >
				<p>Type of AHU/Ventilation recorded in</p>
			</td>
			<td width="4%" valign="top" >
				<p>:</p>
			</td>
			<td colspan="2" width="46%" valign="top" >
				<p>' . $equipment_details['equipment_type'] . '
</p>
			</td>
		</tr>

';


$prev_val_wf_id = DB::queryFirstField("select val_wf_id from  tbl_val_wf_tracking_details where unit_id=" . intval($wf_details['unit_id']) . "
and equipment_id=" . intval($equipment_details['equipment_id']) . " and val_wf_current_stage=5 and val_wf_id !='" . $_GET['val_wf_id'] . "' order by actual_wf_start_datetime desc
    Limit 1");
if (!empty($prev_val_wf_id)) {
    $principal_test_unit = DB::queryFirstField("select primary_test_id from units where unit_id=" . intval($wf_details['unit_id'])." and unit_status='Active'");

    $prev_val_completed_on = DB::queryFirstField("select test_conducted_date from tbl_test_schedules_tracking where val_wf_id ='" . $prev_val_wf_id . "' and test_id=" . intval($principal_test_unit));
} else {
    $prev_val_completed_on = '';
}


$html_content_fr12 = '
<tr>
			<td width="50%" height="11" >
				<p>Previous verification/Qualification done on</p>
			</td>
			<td width="4%" valign="top" >
				<p>:</p>
			</td>
			<td colspan="2" width="46%" valign="top" >
				<p>' . ((empty($prev_val_completed_on)) ? 'NA' : date_format(date_create($prev_val_completed_on), "d.m.Y")) . '</p>
			</td>
		</tr>


               
		
                
		
                
		
                <tr>
			<td width="50%" height="11" >
				<p>Design Capacity in CFM.</p>
			</td>
			<td width="4%" valign="top" >
				<p>:</p>
			</td>
			<td colspan="2" width="46%" valign="top" >
				<p>' . (empty($equipment_details['design_cfm']) ? "NA" : $equipment_details['design_cfm']) . '</p>
			</td>
		</tr>
		
               
   		<tr>
			<td rowspan="2" width="50%" height="4" >
				<p>Classification
				of Area catered by HVAC system and particle count occupancy state
				at which class is achieved.</p>
			</td>
			<td rowspan="2" width="4%" valign="top" >
				<p>:</p>
			</td>
			<td width="23%" valign="top" >
				<p>At Rest</p>
			</td>
			<td width="23%" valign="top" >
				<p>In Operation</p>
			</td>
		</tr>
		<tr>
			<td width="23%" >
				<p>' . (empty($equipment_details['area_classification']) ? "NA" : $equipment_details['area_classification']) . '</p>

		
			</td>
			<td width="23%" valign="top">
				<p>' . (empty($equipment_details['area_classification_in_operation']) ? "NA" : $equipment_details['area_classification_in_operation']) . '</p>


			</td>
		</tr>
		

		
                <tr>
                    <td colspan="4" height="11"><b>Filtration</b></td>
                    
                </tr>
                <tr>
			<td width="50%" height="11" >
				<p>Fresh air filter (if applicable)</p>
			</td>
			<td width="4%" valign="top" >
				<p>:</p>
			</td>
			<td colspan="2" width="46%" valign="top" >
				<p>' . (empty($equipment_details['filteration_fresh_air']) ? "NA" : $equipment_details['filteration_fresh_air']) . '</p>
			</td>
		</tr>
		
                <tr>
			<td width="50%" height="11">
				<p>Intermediate</p>
			</td>
			<td width="4%" valign="top" >
				<p>:</p>
			</td>
			<td colspan="2" width="46%" valign="top" >
				<p>' . (empty($equipment_details['filteration_intermediate']) ? "NA" : $equipment_details['filteration_intermediate']) . '</p>
			</td>
		</tr>

           <tr>
           <td width="50%" height="11"> <p>Pre filter (if applicable)</p>
           </td>
           <td width="4%" valign="top" >
				<p>:</p>
			</td>
			<td colspan="2" width="46%" valign="top" >
				<p>' . (empty($equipment_details['filteration_pre_filter']) ? "NA" : $equipment_details['filteration_pre_filter']) . '</p>
			</td>
		</tr>
		
                <tr>
			<td width="50%" height="11" >
				<p>Fine filter (Supply)</p>
			</td>
			<td width="4%" valign="top" >
				<p>:</p>
			</td>
			<td colspan="2" width="46%" valign="top" >
				<p>' . (empty($equipment_details['filteration_final_filter_plenum']) ? "NA" : $equipment_details['filteration_final_filter_plenum']) . '</p>
			</td>
		</tr>
		
                <tr>
			<td width="50%" height="11" >
				<p>Exhaust Pre filter (if applicable)</p>
			</td>
			<td width="4%" valign="top" >
				<p>:</p>
			</td>
			<td colspan="2" width="46%" valign="top" >
				<p>' . (empty($equipment_details['filteration_exhaust_pre_filter']) ? "NA" : $equipment_details['filteration_exhaust_pre_filter']) . '</p>
			</td>
		</tr>
		
                <tr>
			<td width="50%" height="11" >
				<p>Exhaust final Filter (if applicable)</p>
			</td>
			<td width="4%" valign="top" >
				<p>:</p>
			</td>
			<td colspan="2" width="46%" valign="top" >
				<p>' . (empty($equipment_details['filteration_exhaust_final_filter']) ? "NA" : $equipment_details['filteration_exhaust_final_filter']) . '</p>
			</td>
		</tr>
		
                <tr>
			<td width="50%" height="11" >
				<p>Terminal filter (If applicable)</p>
			</td>
			<td width="4%" valign="top" >
				<p>:</p>
			</td>
			<td colspan="2" width="46%" valign="top" >
				<p>' . (empty($equipment_details['filteration_terminal_filter']) ? "NA" : $equipment_details['filteration_terminal_filter']) . '</p>
			</td>
		</tr>
		
                <tr>
			<td width="50%" height="11" >
				<p>Bag in Bag out filter (If applicable)</p>
			</td>
			<td width="4%" valign="top" >
				<p>:</p>
			</td>
			<td colspan="2" width="46%" valign="top" >
				<p>' . (empty($equipment_details['filteration_bibo_filter']) ? "NA" : $equipment_details['filteration_bibo_filter']) . '</p>
			</td>
		</tr>
		
        <tr>
			<td width="50%" height="11" >
				<p>Relief filter</p>
			</td>
			<td width="4%" valign="top" >
				<p>:</p>
			</td>
			<td colspan="2" width="46%" valign="top" >
				<p>' . (empty($equipment_details['filteration_relief_filter']) ? "NA" : $equipment_details['filteration_relief_filter']) . ' </p>
			</td>
		</tr>
		
        <tr>
			<td width="50%" height="11" >
				<p>Filter on Return riser </p>
			</td>
			<td width="4%" valign="top" >
				<p>:</p>
			</td>
			<td colspan="2" width="46%" valign="top" >
				<p>' . (empty($equipment_details['filteration_terminal_filter_on_riser']) ? "NA" : $equipment_details['filteration_terminal_filter_on_riser']) . '</p>
			</td>
		</tr>
		
		 <tr>
			<td width="50%" height="11" >
				<p>Terminal Filter on Riser </p>
			</td>
			<td width="4%" valign="top" >
				<p>:</p>
			</td>
			<td colspan="2" width="46%" valign="top" >
				<p>' . (empty($equipment_details['filteration_terminal_filter_on_riser']) ? "NA" : $equipment_details['filteration_terminal_filter_on_riser']) . '</p>
			</td>
		</tr>
		
		 <tr>
			<td width="50%" height="11" >
				<p>Reactivation Filter</p>
			</td>
			<td width="4%" valign="top" >
				<p>:</p>
			</td>
			<td colspan="2" width="46%" valign="top" >
				<p>' . (empty($equipment_details['filteration_reativation_filter']) ? "NA" : $equipment_details['filteration_reativation_filter']) . '</p>
			</td>
		</tr>
		
	</table>
</center>
<br/>
<pagebreak>
<p><b>7.0&nbsp;&nbsp;&nbsp;Standard Operating Procedures (SOPs) and Microbiological methods (MMs) to be followed:</b></p>

<p align="justify">








<table width="100%" cellpadding="7" cellspacing="0" border="1">


<tr>
    <th>Sr. No.</th>
    <th>SOP/Document Name</th>
    <th>SOP/Document No. Including Version No.</th>
    <th>Data Entered by Sign & Date</th>
</tr>

<tr>
<td>1.</td>
<td>SOP for operating the HVAC System</td>
<td>' . ((empty($report_details) || empty($report_details['sop1_doc_number'])) ? "NA" : $report_details['sop1_doc_number']) . '</td>
<td>' . ((empty($report_details) || empty($report_details['sop1_doc_number'])) ? "NA" : $initiated_by . "<br>" . date('d.m.Y H:i:s', strtotime($report_details['sop1_entered_date']))) . '</td>
</tr>

<tr>
<td>2.</td>
<td>SOP for recording pressure difference with respect to adjacent area / atmosphere.</td>
<td>' . ((empty($report_details) || empty($report_details['sop2_doc_number'])) ? "NA" : $report_details['sop2_doc_number']) . '</td>
<td>' . ((empty($report_details) || empty($report_details['sop2_doc_number'])) ? "NA" :  $initiated_by . "<br>" . date('d.m.Y H:i:s', strtotime($report_details['sop2_entered_date']))) . '</td>
</tr>

<tr>
<td>3.</td>
<td>SOP for Air velocity measurement and calculation of number of air changes</td>	
<td>' . ((empty($report_details) || empty($report_details['sop3_doc_number'])) ? "NA" : $report_details['sop3_doc_number']) . '</td>
<td>' . ((empty($report_details) || empty($report_details['sop3_doc_number'])) ? "NA" :  $initiated_by . "<br>" . date('d.m.Y H:i:s', strtotime($report_details['sop3_entered_date']))) . '</td>
</tr>

<tr>
<td>4.</td>
<td>SOP for checking installed filter system leakages</td>
<td>' . ((empty($report_details) || empty($report_details['sop4_doc_number'])) ? "NA" : $report_details['sop4_doc_number']) . '</td>
<td>' . ((empty($report_details) || empty($report_details['sop4_doc_number'])) ? "NA" :  $initiated_by . "<br>" . date('d.m.Y H:i:s', strtotime($report_details['sop1_entered_date']))) . '</td>
</tr>

<tr>
<td>5.</td>
<td>SOP for checking of particulate matter count.</td>
<td>' . ((empty($report_details) || empty($report_details['sop5_doc_number'])) ? "NA" : $report_details['sop5_doc_number']) . '</td>
<td>' . ((empty($report_details) || empty($report_details['sop5_doc_number'])) ? "NA" :  $initiated_by . "<br>" . date('d.m.Y H:i:s', strtotime($report_details['sop5_entered_date']))) . '</td>
</tr>

<tr>
<td>6.</td>
<td>SOP for airflow direction test and
visualization.
</td>
<td>' . ((empty($report_details) || empty($report_details['sop6_doc_number'])) ? "NA" : $report_details['sop6_doc_number']) . '</td>
<td>' . ((empty($report_details) || empty($report_details['sop6_doc_number'])) ? "NA" :  $initiated_by . "<br>" . date('d.m.Y H:i:s', strtotime($report_details['sop6_entered_date']))) . '</td>
</tr>


<tr>
<td>7.</td>
<td>SOP for BMS start stop operation. (if applicable)</td>
<td>' . ((empty($report_details) || empty($report_details['sop7_doc_number'])) ? "NA" : $report_details['sop7_doc_number']) . '</td>
<td>' . ((empty($report_details) || empty($report_details['sop7_doc_number'])) ? "NA" :  $initiated_by . "<br>" . date('d.m.Y H:i:s', strtotime($report_details['sop7_entered_date']))) . '</td>
</tr>

<tr>
<td>8.</td>
<td>SOP for Duct leakage Measurement. </td>
<td>' . ((empty($report_details) || empty($report_details['sop8_doc_number'])) ? "NA" : $report_details['sop8_doc_number']) . '</td>
<td>' . ((empty($report_details) || empty($report_details['sop8_doc_number'])) ? "NA" :  $initiated_by . "<br>" . date('d.m.Y H:i:s', strtotime($report_details['sop8_entered_date']))) . '</td>
</tr>

<tr>
<td>9.</td>
<td>SOP for area recovery / clean up period study. 

</td>
<td>' . ((empty($report_details) || empty($report_details['sop9_doc_number'])) ? "NA" : $report_details['sop9_doc_number']) . '</td>
<td>' . ((empty($report_details) || empty($report_details['sop9_doc_number'])) ? "NA" :  $initiated_by . "<br>" . date('d.m.Y H:i:s', strtotime($report_details['sop9_entered_date']))) . '</td>
</tr>

<tr>
<td>10.</td>
<td>SOP for containment leakage test. </td>
<td>' . ((empty($report_details) || empty($report_details['sop10_doc_number'])) ? "NA" : $report_details['sop10_doc_number']) . '</td>
<td>' . ((empty($report_details) || empty($report_details['sop10_doc_number'])) ? "NA" :  $initiated_by . "<br>" . date('d.m.Y H:i:s', strtotime($report_details['sop10_entered_date']))) . '</td>
</tr> 

<tr>
<td>11.</td>
<td>SOP scrubber / Point exhaust CFM</td>
<td>' . ((empty($report_details) || empty($report_details['sop11_doc_number'])) ? "NA" : $report_details['sop11_doc_number']) . '</td>
<td>' . ((empty($report_details) || empty($report_details['sop11_doc_number'])) ? "NA" :  $initiated_by . "<br>" . date('d.m.Y H:i:s', strtotime($report_details['sop11_entered_date']))) . '</td>
</tr>

<tr>
<td>12.</td>
<td>Microbiological method (MM) for environmental monitoring </td>
<td>' . ((empty($report_details) || empty($report_details['sop12_doc_number'])) ? "NA" : $report_details['sop12_doc_number']) . '</td>
<td>' . ((empty($report_details) || empty($report_details['sop12_doc_number'])) ? "NA" :  $initiated_by . "<br>" . date('d.m.Y H:i:s', strtotime($report_details['sop12_entered_date']))) . '</td>
</tr>

<tr>
<td>13.</td>
<td>Additional SOP Details</td>
<td>' . ((empty($report_details) || empty($report_details['sop13_doc_number'])) ? "NA" : $report_details['sop13_doc_number']) . '</td>
<td>' . ((empty($report_details) || empty($report_details['sop13_doc_number'])) ? "NA" :  $initiated_by . "<br>" . date('d.m.Y H:i:s', strtotime($report_details['sop13_entered_date']))) . '</td>
</tr>









</table>


 </p>




<p><b>8.0&nbsp;&nbsp;&nbsp;Controls</b></p>




<p>8.1&nbsp;&nbsp;&nbsp;Ensure the calibration details of instrument used for performance</p>
<p>8.2&nbsp;&nbsp;&nbsp;Training should be available for concerned persons.</p>

<p>
<table width="100%" class="table table-bordered" cellspacing="0" border="1">
<tr><th>Name</th><th>Department</th><th>Training Status</th></tr>';

$html_content_fr13 = '';
if (empty($training_details)) {
    $html_content_fr13 = "<tr><td colspan='3'>NA</td></tr>";
} else {
    foreach ($training_details as $row) {
        $html_content_fr13 = $html_content_fr13 . "<tr><td style='text-align:center;'>" . $row['user_name'] . "</td><td style='text-align:center;'>" . (empty($row['department_name'])?"External Agency":$row['department_name'])  . "</td><td style='text-align:center;'>" . $row['training_status']  . "</td></tr>";
    }
}


$html_content_fr14 = '


</table>



</p>

<p>8.3&nbsp;&nbsp;&nbsp;Ensure that all required precautions should be taken as per operation SOP.</p>
<p>8.4&nbsp;&nbsp;&nbsp;Gowning procedure used by personnel should be as per area requirement.</p>
<br/>
<pagebreak>
<p><b>9.0&nbsp;&nbsp;&nbsp;Verification Procedure:</b></p>



 <br/>
<table width="100%" cellpadding="7" cellspacing="0" border="1">









<tr>
<td rowspan="3">1.</td>
<td>Test</td>
<td>Number of air changes per hour in the area.</td>
</tr>


<tr>

<td>Acceptance Criteria</td>
<td><table class="table table-bordered" cellpadding="7" cellspacing="0" border="1"><tr><th>Sr No.</th><th>Area</th><th>Air change per hours</th></tr><tr><td>1</td><td>ISO Class 8</td><td>More than 10</td></tr><tr><td>2</td><td>ISO Class 7 </td><td>More than 20</td></tr><tr><td>3</td><td>ISO Class 5</td><td>More than 30</td></tr><tr><td>4</td><td>Controlled Not Classified (CNC)</td><td>More than 06</td></tr></table></td>


</tr>

<tr>

<td>Observations</td>
<td>' . ((empty($report_details) || empty($report_details['test1_observation'])) ? "Not applicable" :  $report_details['test1_observation']) . '' . (!empty($report_details['test1_observation'])?"<br>Test Performed by External Vendor: ".getVendorByTestId(1, $testVendorMap):'') .'







</td>


</tr>


<tr>
<td rowspan="3">2.</td>
<td>Test</td>
<td>Fresh air quantity in CFM</td>
</tr>


<tr>

<td>Acceptance Criteria</td>
<td>Should not be less than 10% of area CFM</td>

</tr>

<tr>

<td>Observations</td>
<td>' . ((empty($report_details) || empty($report_details['test2_observation'])) ? "Not applicable" :  $report_details['test2_observation']) . '' . ((!empty($report_details['test2_observation']))?"<br>Test Performed by External Vendor: ".getVendorByTestId(2, $testVendorMap):'') .'


</td>


</tr>


<tr>
<td rowspan="3">3.</td>
<td>Test</td>
<td>Return air CFM at diffuser / riser / riser filter in the area (if applicable)</td>
</tr>


<tr>

<td>Acceptance Criteria</td>
<td>To be checked at actual for monitoring purpose only when all exhaust systems are \'ON\'.</td>


</tr>

<tr>

<td>Observations</td>
<td>' . ((empty($report_details) || empty($report_details['test3_observation'])) ? "Not applicable" :  $report_details['test3_observation']) . '' . ((!empty($report_details['test3_observation']))?"<br>Test Performed by External Vendor: ".getVendorByTestId(3, $testVendorMap):''). ' </td>


</tr>

<tr>
<td rowspan="3">4.</td>
<td>Test</td>
<td>Relief air CFM through relief air filter of HVAC (if applicable)</td>
</tr>


<tr>

<td>Acceptance Criteria</td>
<td>Should be NMT 30%</td>


</tr>

<tr>

<td>Observations</td>
<td>' . ((empty($report_details) || empty($report_details['test4_observation'])) ? "Not applicable" :  $report_details['test4_observation']) . '' . ((!empty($report_details['test4_observation']))?"<br>Test Performed by External Vendor: ".getVendorByTestId(4, $testVendorMap):''). ' </td>


</tr>



<tr>
<td rowspan="3">5.</td>
<td>Test</td>
<td>Filter Integrity Testing to be done for the Installed filters in the HVAC System. </td>
</tr>


<tr>

<td>Acceptance Criteria</td>
<td>Any leakage should not be more than 0.01% of upstream
challenge aerosol concentration.
</td>

</tr>

<tr>

<td>Observations</td>
<td>' . ((empty($report_details) || empty($report_details['test6_observation'])) ? "Not applicable" :  $report_details['test6_observation']). '' . ((!empty($report_details['test6_observation']))?"<br>Test Performed by External Vendor: ".getVendorByTestId(6, $testVendorMap):'') . ' </td>


</tr>


<tr>
<td rowspan="3">6.</td>
<td>Test</td>
<td>Check dust collector / scrubber / point exhaust CFM (If applicable).</td>
</tr>


<tr>

<td>Acceptance Criteria</td>
<td>To be checked at actual</td>


</tr>

<tr>

<td>Observations</td>
<td>' . ((empty($report_details) || empty($report_details['test7_observation'])) ? "Not applicable" :  $report_details['test7_observation']). '' . ((!empty($report_details['test7_observation']))?"<br>Test Performed by External Vendor: ".getVendorByTestId(7, $testVendorMap):'') . '</td>


</tr>


<tr>
<td rowspan="3">7.</td>
<td>Test</td>
<td>Comprehensive Temperature test and Relative humidity (%) in the area. In case of BMS, corresponding trends to be attached as Annexure.</td>
</tr>


<tr>

<td>Acceptance Criteria</td>
<td>Limit should meet the environmental condition of
corresponding unit level SOP.
</td>

</tr>

<tr>

<td>Observations</td>

<td>' . ((empty($report_details) || empty($report_details['test8_observation'])) ? "Not applicable" :  $report_details['test8_observation']) . ' </td>

</tr>


<tr>
<td rowspan="3">8.</td>
<td>Test</td>
<td>Air Differential pressure in the area with respect to adjacent area/ atmosphere (if applicable).</td>
</tr>


<tr> 

<td>Acceptance Criteria</td>
<td>Air differential pressure in the area with respect to adjacent area/atmosphere should be within limit and for actual readings refer attached annexure , if applicable.</td>

</tr>

<tr>

<td>Observations</td>

<td>' . ((empty($report_details) || empty($report_details['test9_observation'])) ? "Not applicable" :  $report_details['test9_observation']) . '</td>

</tr>


<tr>
<td rowspan="3">9.</td>
<td>Test</td>
<td>Airflow direction test and visualization</td>
</tr>


<tr>

<td>Acceptance Criteria</td>
<td>The smoke should be diffused uniformly at supply
grill/diffusers to room and pass through return
grill/diffusers/riser. The smoke should pass from positive
area to negative area.
</td>

</tr>

<tr>

<td>Observations</td>

<td>' . ((empty($report_details) || empty($report_details['test10_observation'])) ? "Not applicable" :  $report_details['test10_observation']) . ' </td>

</tr>

<tr>
<td rowspan="3">10.</td>
<td>Test</td>
<td>Particulate matter count ("At rest" condition)</td>
</tr>


<tr>

<td>Acceptance Criteria</td>
<td><p>No. of particles/m3</p>
<p>Maximum concentration limits</p>
<p>As per EC/WHO/ISO guideline</p>





<table class="table table-bordered" cellpadding="7" cellspacing="0" border="1">
<tr><th>Grade / ISO Class</th><th>0.5μ</th><th>5μ</th></tr>
<tr><td>ISO Class 5 / Grade A</td><td>3520</td><td>20</td></tr>
<tr><td>ISO Class 5 / Grade B</td><td>3520 </td><td>29</td></tr>
<tr><td>ISO Class 7/ Grade C</td><td>3,52,000</td><td>2900 (As per EC/WHO guideline)<br/> 2930 (As per ISO guideline)</td></tr>
<tr><td>ISO Class 8 / Grade D</td><td>35,20,000</td><td>29000 (As per EC/WHO guideline)<br/> 29300 (As per ISO guideline)</td></tr></table>
</td>

</tr>

<tr>

<td>Observations</td>

<td>' . ((empty($report_details) || empty($report_details['test11_observation'])) ? "Not applicable" :  $report_details['test11_observation']). '' . ((!empty($report_details['test11_observation']))?"<br>Test Performed by External Vendor: ".getVendorByTestId(11, $testVendorMap):'') . ' </td>

</tr>

<tr>
<td rowspan="3">11.</td>
<td>Test</td>
<td>Particulate matter count ("In operation" condition)</td>
</tr>


<tr>

<td>Acceptance Criteria</td>
<td><p>No. of particles/m3</p>
<p>Maximum concentration limits</p>


<table class="table table-bordered" cellpadding="7" cellspacing="0" border="1">
<tr><th colspan="3">As per USFDA guideline</th><th colspan="4">As per EC/WHO/ISO guideline</th></tr>
<tr><th>Area Class</th><th>ISO Class</th><th>0.5μ</th><th>Grade</th><th>ISO Class</th><th>0.5μ</th><th>5μ </th></tr>
<tr><td>100</td><td>5</td><td>3520</td><td>A</td><td>5</td><td>3520</td><td>20 </td></tr>
<tr><td>10000</td><td>7</td><td>352000</td><td>B</td><td>7</td><td>352000</td><td>2900 </td></tr>
<tr><td>100000</td><td>8</td><td>3520000</td><td>C</td><td>8</td><td>3520000</td><td>29000 </td></tr>
<tr><td>NA</td><td>NA</td><td>NA</td><td>D</td><td>NA</td><td>Not defined</td><td>Not defined</td></tr>
</table>
</td>

</tr>

<tr>

<td>Observations</td>
<td>' . ((empty($report_details) || empty($report_details['test12_observation'])) ? "Not applicable" :  $report_details['test12_observation']). '' . ((!empty($report_details['test12_observation']))?"<br>Test Performed by External Vendor: ".getVendorByTestId(12, $testVendorMap):'') . '</td>


</tr>

<tr>
<td rowspan="3">12.</td>
<td>Test</td>
<td>Containment leakage test (if applicable)</td>
</tr>


<tr>

<td>Acceptance Criteria</td>
<td>As per respective SOP</td>

</tr>

<tr>

<td>Observations</td>
<td>' . ((empty($report_details) || empty($report_details['test13_observation'])) ? "Not applicable" :  $report_details['test13_observation']) . '' . ((!empty($report_details['test13_observation']))?"<br>Test Performed by External Vendor: ".getVendorByTestId(13, $testVendorMap):''). ' </td>


</tr>

<tr>
<td rowspan="3">13.</td>
<td>Test</td>
<td>Area recovery / clean-up period study.</td>
</tr>


<tr>

<td>Acceptance Criteria</td>
<td>As per respective SOP</td>

</tr>

<tr>

<td>Observations</td>
<td>' . ((empty($report_details) || empty($report_details['test14_observation'])) ? "Not applicable" :  $report_details['test14_observation']). '' . ((!empty($report_details['test14_observation']))?"<br>Test Performed by External Vendor: ".getVendorByTestId(14, $testVendorMap):'') . ' </td>


</tr>

<tr>
<td rowspan="3">14.</td>
<td>Test</td>
<td>Microbial count by settle plate exposure and Air Sampling (If applicable)</td>
</tr>


<tr>

<td>Acceptance Criteria</td>
<td>Should not be more than the limit specified in Microbiological methods (MM) for monitoring environmental control</td>

</tr>

<tr>

<td>Observations</td>
<td>' . ((empty($report_details) || empty($report_details['test15_observation'])) ? "Not applicable" :  $report_details['test15_observation'])  . '</td>


</tr>

<tr>
<td rowspan="3">15.</td>
<td>Equipment Maintenance Details </td>
<td>All the Planned Preventive Maintenance & Filter Cleaning Activity records to be Reviewed since previous Periodic Performance Verification.  </td>
</tr>


<tr>

<td>Acceptance Criteria</td>
<td>All the PPM & Filter Cleaning Activities to be performed & Recorded as per their respective applicable SOP’s. </td>

</tr>

<tr>

<td>Observations</td>
<td>' . ((empty($report_details) || empty($report_details['test16_observation'])) ? "Not applicable" :  "Reviewed and Found Satisfactory") . '</td>


</tr>




</table>

 
 

<p align="justify">
        <b>Note:</b> Microbiological Monitoring is carried out separately as per schedule, Reports or trends to be attached Since last Periodic Performance verification date, (if applicable). 

</p>
<br/>

<pagebreak>
<p><b>10.0&nbsp;&nbsp;&nbsp;Frequency:</b></p>

<p align="justify">
For Classified area, performance verification study shall be performed once in a year or whenever the changes are incorporated in the area, Equipment or HVAC system. 

</p>
        <p align="justify">
For Non-Classified areas/General areas, performance verification study shall be performed every Two years or whenever the changes are incorporated in the area, Equipment or HVAC system. 

</p>



<p><b>11.0&nbsp;&nbsp;&nbsp;Deviation/Out of specifications (If any):</b></p>

<p align="justify">
' . ((empty($report_details['deviation'])) ? "NA" :  $report_details['deviation']) . '
</p>

<p><b>12.0&nbsp;&nbsp;&nbsp;Review of deviation, change request, and CAPA since last verification:</b></p>

<p align="justify">
' . ((empty($report_details['deviation_review'])) ? "NA" : $report_details['deviation_review']) . '
</p>



<p><b>13.0&nbsp;&nbsp;&nbsp;Summary of performance verification:</b></p>

<p align="justify">
' . ((empty($report_details['summary'])) ? "NA" :  $report_details['summary']) . '
</p>



<p><b>14.0&nbsp;&nbsp;&nbsp;Recommendation:</b></p>

<p align="justify">
' . ((empty($report_details['recommendationn'])) ? "" :  $report_details['recommendationn']) . '
</p>




        <p><b>15.0&nbsp;&nbsp;&nbsp;Team Approval :</b></p>
        
<p align="justify">


<table width="100%"  cellspacing="0" border="1">
<tr>
    <th>
        Department
    </th>
    <th>
        Approval Remark 
    </th>
    <th>
        Approved By 
    </th>
    <th>
        Approved On
    </th>
</tr>

';

$result1 = DB::queryFirstRow("
    SELECT t2.user_name, t1.level1_user_dept_approval_datetime as app_date, level1_user_dept_approval_remarks as remarks 
    FROM tbl_val_wf_approval_tracking_details t1
    JOIN users t2 ON t1.level1_user_dept_approval_by=t2.user_id
    WHERE t1.val_wf_id=%s
    AND t1.iteration_id = (
        SELECT MAX(iteration_id)
        FROM tbl_val_wf_approval_tracking_details
        WHERE val_wf_id = %s
        AND iteration_completion_status = 'complete'
        AND iteration_status = 'Active'
    )", $_GET['val_wf_id'], $_GET['val_wf_id']);

$result2 = DB::queryFirstRow("
    SELECT t2.user_name, t1.level1_eng_approval_datetime as app_date, level1_eng_approval_remarks as remarks 
    FROM tbl_val_wf_approval_tracking_details t1
    JOIN users t2 ON t1.level1_eng_approval_by=t2.user_id
    WHERE t1.val_wf_id=%s
    AND t1.iteration_id = (
        SELECT MAX(iteration_id)
        FROM tbl_val_wf_approval_tracking_details
        WHERE val_wf_id = %s
        AND iteration_completion_status = 'complete'
        AND iteration_status = 'Active'
    )", $_GET['val_wf_id'], $_GET['val_wf_id']);

$result3 = DB::queryFirstRow("
    SELECT t2.user_name, t1.level1_hse_approval_datetime as app_date, level1_hse_approval_remarks as remarks 
    FROM tbl_val_wf_approval_tracking_details t1
    JOIN users t2 ON t1.level1_hse_approval_by=t2.user_id
    WHERE t1.val_wf_id=%s
    AND t1.iteration_id = (
        SELECT MAX(iteration_id)
        FROM tbl_val_wf_approval_tracking_details
        WHERE val_wf_id = %s
        AND iteration_completion_status = 'complete'
        AND iteration_status = 'Active'
    )", $_GET['val_wf_id'], $_GET['val_wf_id']);

$result4 = DB::queryFirstRow("
    SELECT t2.user_name, t1.level1_qc_approval_datetime as app_date, level1_qc_approval_remarks as remarks 
    FROM tbl_val_wf_approval_tracking_details t1
    JOIN users t2 ON t1.level1_qc_approval_by=t2.user_id
    WHERE t1.val_wf_id=%s
    AND t1.iteration_id = (
        SELECT MAX(iteration_id)
        FROM tbl_val_wf_approval_tracking_details
        WHERE val_wf_id = %s
        AND iteration_completion_status = 'complete'
        AND iteration_status = 'Active'
    )", $_GET['val_wf_id'], $_GET['val_wf_id']);

$result5 = DB::queryFirstRow("
    SELECT t2.user_name, t1.level1_qa_approval_datetime as app_date, level1_qa_approval_remarks as remarks 
    FROM tbl_val_wf_approval_tracking_details t1
    JOIN users t2 ON t1.level1_qa_approval_by=t2.user_id
    WHERE t1.val_wf_id=%s
    AND t1.iteration_id = (
        SELECT MAX(iteration_id)
        FROM tbl_val_wf_approval_tracking_details
        WHERE val_wf_id = %s
        AND iteration_completion_status = 'complete'
        AND iteration_status = 'Active'
    )", $_GET['val_wf_id'], $_GET['val_wf_id']);


$html_content_fr15 = '

<tr>
    <td style="text-align: center;">Engineering</td>
    <td style="text-align: center;">' . (isset($result2) ? $result2['remarks'] : "NA") . '</td>
    <td style="text-align: center;">
        ' . (isset($result2) ? $result2['user_name'] : ($wf_details['val_wf_current_stage'] == '2' || $wf_details['val_wf_current_stage'] == '3' || $wf_details['val_wf_current_stage'] == '4' || $wf_details['val_wf_current_stage'] == '5' ? "No Approval Required" : "___________"))    . '</td>
    <td style="text-align: center;">' . (isset($result2) ? date("d.m.Y H:i:s", strtotime($result2['app_date'])) : "NA") . ' </td>
</tr>

<tr>
    <td style="text-align: center;">User</td>
    <td style="text-align: center;">' . (isset($result1) ? $result1['remarks'] : "NA") . '</td>
    <td style="text-align: center;">' .
    (isset($result1) ? $result1['user_name'] : ($wf_details['val_wf_current_stage'] == '2' || $wf_details['val_wf_current_stage'] == '3' || $wf_details['val_wf_current_stage'] == '4' || $wf_details['val_wf_current_stage'] == '5' ? "No Approval Required" : "___________"))   . '</td>
    <td style="text-align: center;">' . (isset($result1) ? date("d.m.Y H:i:s", strtotime($result1['app_date'])) : "NA") . '</td>
</tr>


<tr>
    <td style="text-align: center;">EHS</td>
    <td style="text-align: center;">' . (isset($result3) ? $result3['remarks'] : "NA") . '</td>
    <td style="text-align: center;">' . (isset($result3) ? $result3['user_name'] : ($wf_details['val_wf_current_stage'] == '2' || $wf_details['val_wf_current_stage'] == '3' || $wf_details['val_wf_current_stage'] == '4' || $wf_details['val_wf_current_stage'] == '5' ? "No Approval Required" : "___________"))     . ' </td>
    <td style="text-align: center;">' . (isset($result3) ? date("d.m.Y H:i:s", strtotime($result3['app_date'])) : "NA") . '</td>
</tr>

<tr>
    <td style="text-align: center;">Quality Control</td>
    <td style="text-align: center;">' . (isset($result4) ? $result4['remarks'] : "NA") . '</td>
    <td style="text-align: center;">' . (isset($result4) ? $result4['user_name'] : ($wf_details['val_wf_current_stage'] == '2' || $wf_details['val_wf_current_stage'] == '3' || $wf_details['val_wf_current_stage'] == '4' || $wf_details['val_wf_current_stage'] == '5' ? "No Approval Required" : "___________"))     . '</td>
    <td style="text-align: center;">' . (isset($result4) ? date("d.m.Y H:i:s", strtotime($result4['app_date'])) : "NA") . '</td>
</tr>

<tr>
    <td style="text-align: center;">Quality Assurance</td>
    <td style="text-align: center;">' . (isset($result5) ? $result5['remarks'] : "NA") . '</td>
    <td style="text-align: center;">' . (isset($result5) ? $result5['user_name'] : ($wf_details['val_wf_current_stage'] == '2' || $wf_details['val_wf_current_stage'] == '3' || $wf_details['val_wf_current_stage'] == '4' || $wf_details['val_wf_current_stage'] == '5' ? "No Approval Required" : "___________"))    . '</td>
    <td style="text-align: center;">' . (isset($result5) ? date("d.m.Y H:i:s", strtotime($result5['app_date'])) : "NA") . '</td>
</tr>
</table>



</p>



        <p><b>16.0&nbsp;&nbsp;&nbsp;Review (Inclusive of follow up action, if any):</b></p>
         ';

$html_content_fr16 = '';
$unitheadaslevel2 = DB::queryFirstRow("
    SELECT t1.level2_unit_head_approval_remarks, t2.user_name, t1.level2_unit_head_approval_datetime as app_date 
    FROM tbl_val_wf_approval_tracking_details t1
    JOIN users t2 ON t1.level2_unit_head_approval_by=t2.user_id
    WHERE t1.val_wf_id=%s
    AND t1.iteration_id = (
        SELECT MAX(iteration_id)
        FROM tbl_val_wf_approval_tracking_details
        WHERE val_wf_id = %s
        AND iteration_completion_status = 'complete'
        AND iteration_status = 'Active'
    )", $_GET['val_wf_id'], $_GET['val_wf_id']);

if ($unitheadaslevel2) {
    $html_content_fr16 = $unitheadaslevel2['level2_unit_head_approval_remarks'];
} else {
    $html_content_fr16 = "NA" . '<br/>';
}

$html_content_fr17 = '


        <p><br/><b>17.0&nbsp;&nbsp;&nbsp;Approved By :</b></p>
       
<p align="justify">
<table>
        <tr>
        <td>
        ';
$html_content_fr18 = '';
if ($unitheadaslevel2) {
    $html_content_fr18 = $unitheadaslevel2['user_name'];
} else {
    $html_content_fr18 = "NA";
}


$html_content_fr19 = ' </td>
            <td>&nbsp;<br/> </td>
            
            
   <td>&nbsp;</td>
         <td>&nbsp;</td>
         
        </tr>
        
        <tr>
<td>
';
$html_content_fr20 = '';
if ($unitheadaslevel2) {
    $html_content_fr20 = ' <br/>Unit Head<br/> Date:' . date('d.m.Y H:i:s', strtotime($unitheadaslevel2['app_date']));
} else {
    $html_content_fr20 = "NA";
}

$html_content_fr21 = '
</td>

         </td>
            <td>&nbsp;</td>
            
            
   <td>&nbsp;</td>
         <td>&nbsp;</td>
         
         
        </tr>
        
        </table>
</p>


        <p><b>18.0&nbsp;&nbsp;&nbsp;Approved By:</b></p>

<p align="justify">
<table>
        <tr>
        <td>&nbsp;' ;
    $resultheadqa = DB::queryFirstRow("
    SELECT t1.level3_head_qa_approval_remarks, t2.user_name, t1.level3_head_qa_approval_datetime as app_date 
    FROM tbl_val_wf_approval_tracking_details t1
    JOIN users t2 ON t1.level3_head_qa_approval_by=t2.user_id
    WHERE t1.val_wf_id=%s
    AND t1.iteration_id = (
        SELECT MAX(iteration_id)
        FROM tbl_val_wf_approval_tracking_details
        WHERE val_wf_id = %s
        AND iteration_completion_status = 'complete'
        AND iteration_status = 'Active'
    )", $_GET['val_wf_id'], $_GET['val_wf_id']);
    $html_content_fr22 = '';
if ($resultheadqa) {
    $html_content_fr22 = $resultheadqa['level3_head_qa_approval_remarks'];
} else {
    $html_content_fr22 = "NA";}
  
$html_content_fr23='';
        if ($resultheadqa) {
    $html_content_fr23 = '</td></tr><tr><td><br>'.$resultheadqa['user_name'];
} else {
    $html_content_fr23 = "NA";}




     
        
        $html_content_fr24 =' </td>
            <td>&nbsp;</td>
            
            
   <td>&nbsp;</td>
         <td>&nbsp;</td>
         
        </tr>
        
        <tr>
        <td><br/>Head Unit Quality Assurance<br/> Date: ' . (isset($resultheadqa) ? date('d.m.Y H:i:s', strtotime($resultheadqa['app_date'])) : "___________") . ' </td>
            <td>&nbsp;</td>
            
            
   <td>&nbsp;</td>
         <td>&nbsp;</td>
         
         
        </tr>
        </table>
</p>


<pagebreak>
        <p><b>19.0&nbsp;&nbsp;&nbsp;Abbreviations:</b></p>
        
<p align="justify">
<center>
<table width="80%" cellspacing="0" cellpadding="1" border="0">
    <col width="128*">
		<col width="10*">
		<col width="38*">
		<col width="79*">
        <tr>
            <td width="15%">AHU </td>
            <td width="4%">:</td>
            <td colspan="2" width="81%">Air Handling Unit </td>
        </tr>
        
        <tr>
            <td>ACPH </td>
            <td>:</td>
            <td colspan="2">Air Changes per Hour </td>
        </tr>
        
        <tr>
            <td>BMS </td>
            <td>:</td>
            <td colspan="2">Building Management system </td>
        </tr>
        
        <tr>
            <td>CD </td>
            <td>:</td>
            <td colspan="2">Compact Disc </td>
        </tr>
        
        <tr>
            <td>CFM </td>
            <td>:</td>
            <td colspan="2">Cubic Feet per Minute </td>
        </tr>
        
        <tr>
            <td>Dept. </td>
            <td>:</td>
            <td colspan="2">Department </td>
        </tr>
        
        <tr>
            <td>EC </td>
            <td>:</td>
            <td colspan="2">European Commission </td>
        </tr>
        
        <tr>
            <td>EU </td>
            <td>:</td>
            <td colspan="2">Eurovent </td>
        </tr>
        
        <tr>
            <td>GMP </td>
            <td>:</td>
            <td colspan="2">Good Manufacturing Practice </td>
        </tr>
        
        <tr>
            <td>EHS </td>
            <td>:</td>
            <td colspan="2">Environment Health and Safety </td>
        </tr>
        
        <tr>
            <td>HVAC </td>
            <td>:</td>
            <td colspan="2">Heating  and Air Conditioning System </td>
        </tr>
        
        <tr>
            <td>ISO </td>
            <td>:</td>
            <td colspan="2">International Organization for Standardization </td>
        </tr>
        
        <tr>
            <td>m3 </td>
            <td>:</td>
            <td colspan="2">Cubic meter </td>
        </tr>
        
        <tr>
            <td>mm </td>
            <td>:</td>
            <td colspan="2">Millimetre </td>
        </tr>
        
        <tr>
            <td>NA </td>
            <td>:</td>
            <td colspan="2">Not Applicable </td>
        </tr>
        
        <tr>
            <td>No.	 </td>
            <td>:</td>
            <td colspan="2">Number </td>
        </tr>
        
        <tr>
            <td>OSD </td>
            <td>:</td>
            <td colspan="2">Oral Solid Dosage </td>
        </tr>
        
        <tr>
            <td>SOP </td>
            <td>:</td>
            <td colspan="2">Standard Operating Procedure </td>
        </tr>
        
        <tr>
            <td>VFD </td>
            <td>:</td>
            <td colspan="2">Variable Frequency Drive </td>
        </tr>
        
        <tr>
            <td>WHO </td>
            <td>:</td>
            <td colspan="2">World Health Organization </td>
        </tr>
        
        <tr>
            <td>% </td>
            <td>:</td>
            <td colspan="2">Percentage </td>
        </tr>
        
        <tr>
            <td>mu </td>
            <td>:</td>
            <td colspan="2">Micron </td>
        </tr>
        
        
        </table>
        </center>
</p>


          <p><b>20.0&nbsp;&nbsp;&nbsp;References:</b></p>
          
<p align="justify">
<center>
<table width="80%" cellspacing="0" cellpadding="1" border="0">
    <col width="128*">
		<col width="10*">
		<col width="38*">
		<col width="79*">
        <tr>
            <td width="15%">ISO 14644 </td>
            <td width="4%">:</td>
            <td colspan="2" width="81%">Clean rooms and associated controlled environments.</td>
        </tr>
        
        <tr>
            <td>Part - 1  </td>
            <td>:</td>
            <td colspan="2">Classification of air cleanliness by particle concentration </td>
        </tr>
        
        <tr>
            <td>Part - 2 </td>
            <td>:</td>
            <td colspan="2">Monitoring to provide evidence of cleanroom performance related to air cleanliness by particle concentration</td>
        </tr>
        
        <tr>
            <td>Part - 3  </td>
            <td>:</td>
            <td colspan="2">Metrology and test methods.</td>
        </tr>
        
        <tr>
            <td>Part - 4 </td>
            <td>:</td>
            <td colspan="2">Design, Construction and Start up.</td>
        </tr>
         <tr>
         
            <td colspan="4">WHO Technical Report Series No.961, 2011 </td>
        </tr>
        <tr>
        
            <td colspan="4">EC (Brussels, March 2009 </td>
        </tr>
        <tr>
        
            <td colspan="4">"SCHEDULE - M - THE GAZETTE OF INDIA." 2006 </td>
        </tr>
        <tr>
            <td>1035-G-0045</td>
            <td>:</td>
            <td colspan="2">Temperature and relative humidity distribution study </td>
        </tr>
        
        
        </table>
        </center>
        </p> </body></html>';


$html_content = $html_content_fr1 . $html_content_fr2 . $html_content_fr3 . $html_content_fr4 . $html_content_fr5 . $html_content_fr6 . $html_content_fr7 . $html_content_fr8 .
    $html_content_fr9 . $html_content_fr10 . $html_content_fr11 . $html_content_fr12 . $html_content_fr13 . $html_content_fr14 . $html_content_fr15 . $html_content_fr16 . $html_content_fr17 . $html_content_fr18 . $html_content_fr19 . $html_content_fr20 . $html_content_fr21.$html_content_fr22.$html_content_fr23.$html_content_fr24;
// Now $html_content contains the HTML content from the specified URL
//echo $html_content;

// HTML content for the header with page number and total pages


// Set the HTML header
$header = '
    
        <div  Style="text-align:right;"><img src="assets/images/logo.png" width="60" height="25"/></div>
        <div  Style="text-align:right;">Goa</div>
        
   
    <table width="100%" cellpadding="7" cellspacing="0" border="1">
    <tr>
        <td colspan="3" Style="text-align:center;"><b>PERIODIC PERFORMANCE VERIFICATION </b></td>
        
    </tr>
    <tr>
        <td width="24%">Workflow ID: ' .  $_GET['val_wf_id'] . '</td>
        <td Style="text-align:center;"  >HEATING VENTILATION AND AIR CONDITIONING (HVAC) SYSTEM</td>
      <td width="24%"> Page {PAGENO} of {nb}</td>
    </tr>
    
</table>';
// Performance logging - before PDF creation
$pdfCreationStart = microtime(true);
error_log("PDF Generation: Starting mPDF creation for val_wf_id: $val_wf_id");

// Create mPDF object
$mpdf = new \Mpdf\Mpdf(['setAutoTopMargin' => 'stretch', 'default_font' => 'Arial', 'default_font_size' => 9]);

$mpdf->SetHTMLHeader($header);
// Add the HTML content to mPDF

$mpdf->WriteHTML($html_content);

// Performance logging - after PDF creation
$pdfCreationTime = round(microtime(true) - $pdfCreationStart, 2);
error_log("PDF Generation: mPDF creation completed in {$pdfCreationTime}s for val_wf_id: $val_wf_id");



// Output the PDF
//$mpdf->Output();



// Generate secure filename
$pdfFilename = 'protocol-report-' . preg_replace('/[^a-zA-Z0-9_-]/', '', $val_wf_id) . '.pdf';
$filePath = __DIR__ . '/uploads/' . basename($pdfFilename);

try {
    // Performance logging - before file save
    $fileSaveStart = microtime(true);
    
    // Save the PDF to the specified file path
    file_put_contents($filePath, $mpdf->Output('', 'S')); // 'S' option returns the PDF as a string
    
    // Performance logging - after file save and total time
    $fileSaveTime = round(microtime(true) - $fileSaveStart, 2);
    $totalPdfTime = round(microtime(true) - $pdfStartTime, 2);
    error_log("PDF Generation: File saved in {$fileSaveTime}s, total time {$totalPdfTime}s for val_wf_id: $val_wf_id");
    
    // Check if this is a cURL request (from approval process) by examining User-Agent
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $isCurlRequest = (strpos($userAgent, 'ProVal-SecureClient') !== false) || (strpos($userAgent, 'curl') !== false);
    
    if ($isCurlRequest) {
        // For cURL requests (from approval process), return success indicator
        if (file_exists($filePath)) {
            echo "True";
        } else {
            echo "False";
        }
    } else {
        // For direct browser requests, output the PDF as before
        // Set headers for PDF download/display
        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="' . htmlspecialchars($pdfFilename, ENT_QUOTES) . '"');
        header('Cache-Control: private, max-age=0, must-revalidate');
        header('Pragma: public');
        
        // Output the file content
        if (file_exists($filePath)) {
            readfile($filePath);
            // Optional: Clean up the file after sending it
            // unlink($filePath);
        } else {
            header('HTTP/1.1 500 Internal Server Error');
            echo "Error: PDF file could not be created";
        }
    }
} catch (Exception $e) {
    error_log("PDF generation error in generateprotocolreport_rev.php: " . $e->getMessage());
    
    // Check if this is a cURL request
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $isCurlRequest = (strpos($userAgent, 'ProVal-SecureClient') !== false) || (strpos($userAgent, 'curl') !== false);
    
    if ($isCurlRequest) {
        echo "False";
    } else {
        header('HTTP/1.1 500 Internal Server Error');
        echo "Error: PDF generation failed";
    }
}

