<?php
require_once('./core/config/config.php');

// Session is already started by config.php via session_init.php
// Validate session timeout
require_once('core/security/session_timeout_middleware.php');
validateActiveSession();
require_once('core/config/db.class.php');

// Check for proper authentication
// Use centralized session validation
require_once('core/security/session_validation.php');
validateUserSession();

// Validate and sanitize URL parameters
$required_params = ['user_name', 'user_id', 'sch_year', 'unit_id', 'sch_id'];
foreach ($required_params as $param) {
    if (!isset($_GET[$param]) || empty(trim($_GET[$param]))) {
        header('HTTP/1.1 400 Bad Request');
        exit('Missing required parameter: ' . $param);
    }
}

// Validate numeric parameters
if (!is_numeric($_GET['user_id']) || !is_numeric($_GET['sch_year']) || !is_numeric($_GET['unit_id']) || !is_numeric($_GET['sch_id'])) {
    header('HTTP/1.1 400 Bad Request');
    exit('Invalid parameter format');
}

// Validate year format (4 digits, 20XX range)
if (!preg_match('/^20[0-9]{2}$/', $_GET['sch_year'])) {
    header('HTTP/1.1 400 Bad Request');
    exit('Invalid year format');
}

require_once(__DIR__ . '/core/pdf/FPDF_CellFit_Sch.php');

date_default_timezone_set("Asia/Kolkata");

// Sanitize input parameters
$user_name = htmlspecialchars(trim($_GET['user_name']), ENT_QUOTES, 'UTF-8');
$user_id = intval($_GET['user_id']);
$sch_year = intval($_GET['sch_year']);
$unit_id = intval($_GET['unit_id']);
$sch_id = intval($_GET['sch_id']);

// Changed the Order By clause on 31-12-2021

/*
$query_result=DB::query("select t1.unit_id, t1.test_id, equipment_code,equipment_category,routine_test_wf_planned_start_date,day(routine_test_wf_planned_start_date) day,month(routine_test_wf_planned_start_date) mth
from tbl_proposed_routine_test_schedules t1, equipments t2 
where t1.equip_id=t2.equipment_id and schedule_id=%d
order by equipment_code", intval($_GET['sch_id']));
*/

/* 
$query_result=DB::query("select t1.unit_id, t1.test_id, equipment_code,equipment_category,routine_test_wf_planned_start_date,day(routine_test_wf_planned_start_date) day,month(routine_test_wf_planned_start_date) mth
from tbl_proposed_routine_test_schedules t1, equipments t2
where t1.equip_id=t2.equipment_id and schedule_id=%d
order by LENGTH(SUBSTRING_INDEX(equipment_code, '-', 1)),
    CAST(SUBSTRING_INDEX(equipment_code, '-', -1) AS SIGNED),
    equipment_code,test_id,mth,day", intval($_GET['sch_id']));
*/

try {
    $query_result = DB::query("SELECT t1.unit_id, t1.test_id, equipment_code, equipment_category, routine_test_wf_planned_start_date,
        DAY(routine_test_wf_planned_start_date) AS day, MONTH(routine_test_wf_planned_start_date) AS mth
        FROM tbl_proposed_routine_test_schedules t1, equipments t2
        WHERE t1.equip_id = t2.equipment_id AND schedule_id = ?
        ORDER BY SUBSTRING_INDEX(equipment_code, '-', 1),
            CAST(SUBSTRING_INDEX(equipment_code, '-', -1) AS UNSIGNED),
            test_id,
            mth,
            day", [$sch_id]);
} catch (Exception $e) {
    require_once('core/error/error_logger.php');
    logDatabaseError("Database error in generatertschedulereport.php query: " . $e->getMessage(), [
        'operation_name' => 'report_rt_schedule_generation_query',
        'unit_id' => $unit_id,
        'val_wf_id' => null,
        'equip_id' => null
    ]);
    header('HTTP/1.1 500 Internal Server Error');
    exit('Error retrieving schedule data');
}

try {
    $unit_details = DB::queryFirstRow("SELECT unit_name, unit_site FROM units WHERE unit_id = %i and unit_status='Active'", $unit_id);
    
    if (!$unit_details) {
        header('HTTP/1.1 404 Not Found');
        exit('Unit not found');
    }
} catch (Exception $e) {
    require_once('core/error/error_logger.php');
    logDatabaseError("Database error in generatertschedulereport.php unit query: " . $e->getMessage(), [
        'operation_name' => 'report_rt_schedule_generation_unit_query',
        'unit_id' => $unit_id,
        'val_wf_id' => null,
        'equip_id' => null
    ]);
    header('HTTP/1.1 500 Internal Server Error');
    exit('Database error occurred');
}

$pdf = new FPDF_CellFit();
$pdf->AddPage('L','A4');
$pdf->SetFont('Arial','B',12);
$pdf->Write(10, htmlspecialchars($unit_details['unit_name'], ENT_QUOTES));
$pdf->Ln();
$pdf->Cell(0,12,'HVAC ROUTINE TEST SCHEDULE FOR THE YEAR ' . htmlspecialchars($sch_year, ENT_QUOTES), 1,1, 'C');
$pdf->SetFont('Arial','B',10);

//$pdf->SetWidths(array(10,36,36,15,15,15,15,15,15,15,15,15,15,15,15,15));
$pdf->SetWidths(array(10,32,40,15,15,15,15,15,15,15,15,15,15,15,15,15));
// Set alignment for all cells in the row to center
$pdf->SetAligns(array('C', 'C', 'C', 'C', 'C', 'C', 'C', 'C', 'C', 'C', 'C', 'C', 'C', 'C', 'C','C'));

$pdf->Row(array('SR. NO.','EQUIPMENT CODE','EQUIPMENT NAME','TEST ID','JAN','FEB','MAR','APR','MAY','JUN','JUL','AUG','SEP','OCT','NOV','DEC'));
$pdf->SetFont('Arial','',10);
$count=0;
$output="";


//Set maximum rows per page
$max = 12;
$i=0;


foreach($query_result as $row)
{
    
    //If the current row is the last one, create new page and print column title
    if ($i == $max)
    {
        $pdf->AddPage('L','A4'); // Added on 31-12-2021
        $i=0;
        $pdf->SetFont('Arial','B',12);
        
        $pdf->Write(10,'Unit ' . htmlspecialchars($unit_id, ENT_QUOTES)); // Added on 31-12-2021
        $pdf->Ln(); // Added on 31-12-2021
        
        $pdf->Cell(0,12,'HVAC ROUTINE TEST SCHEDULE FOR THE YEAR ' . htmlspecialchars($sch_year, ENT_QUOTES), 1,1, 'C');
        $pdf->SetFont('Arial','B',10);
        
        //$pdf->SetWidths(array(10,36,36,15,15,15,15,15,15,15,15,15,15,15,15,15));
        $pdf->SetWidths(array(10,32,40,15,15,15,15,15,15,15,15,15,15,15,15,15));
        $pdf->Row(array('SR. NO.','EQUIPMENT CODE','EQUIPMENT NAME','TEST ID','JAN','FEB','MAR','APR','MAY','JUN','JUL','AUG','SEP','OCT','NOV','DEC'));
        $pdf->SetFont('Arial','',10);
        
    }
   // else 
    //{
        $i++;
        $count++;
        $pdf->Cell(10,10,$count,1,0,'C');
        $pdf->Cell(32,10,htmlspecialchars($row['equipment_code'], ENT_QUOTES),1,0,'C');
        $pdf->Cell(40,10,htmlspecialchars($row['equipment_category'], ENT_QUOTES),1,0,'C');
        $pdf->Cell(15,10,htmlspecialchars($row['test_id'], ENT_QUOTES),1,0,'C');
        if($row['mth']==1)
        {
            $pdf->Cell(15,10,$row['day'],1,0,'C');
            $pdf->Cell(15,10,'',1,0,'C');
            $pdf->Cell(15,10,'',1,0,'C');
            $pdf->Cell(15,10,'',1,0,'C');
            $pdf->Cell(15,10,'',1,0,'C');
            $pdf->Cell(15,10,'',1,0,'C');
            $pdf->Cell(15,10,'',1,0,'C');
            $pdf->Cell(15,10,'',1,0,'C');
            $pdf->Cell(15,10,'',1,0,'C');
            $pdf->Cell(15,10,'',1,0,'C');
            $pdf->Cell(15,10,'',1,0,'C');
            $pdf->Cell(15,10,'',1,1,'C');
        }
        else if($row['mth']==2)
        {
            
            $pdf->Cell(15,10,'',1,0,'C');
            $pdf->Cell(15,10,$row['day'],1,0,'C');
            $pdf->Cell(15,10,'',1,0,'C');
            $pdf->Cell(15,10,'',1,0,'C');
            $pdf->Cell(15,10,'',1,0,'C');
            $pdf->Cell(15,10,'',1,0,'C');
            $pdf->Cell(15,10,'',1,0,'C');
            $pdf->Cell(15,10,'',1,0,'C');
            $pdf->Cell(15,10,'',1,0,'C');
            $pdf->Cell(15,10,'',1,0,'C');
            $pdf->Cell(15,10,'',1,0,'C');
            $pdf->Cell(15,10,'',1,1,'C');
        }
        else if($row['mth']==3)
        {
            
            $pdf->Cell(15,10,'',1,0,'C');
            $pdf->Cell(15,10,'',1,0,'C');
            $pdf->Cell(15,10,$row['day'],1,0,'C');
            $pdf->Cell(15,10,'',1,0,'C');
            $pdf->Cell(15,10,'',1,0,'C');
            $pdf->Cell(15,10,'',1,0,'C');
            $pdf->Cell(15,10,'',1,0,'C');
            $pdf->Cell(15,10,'',1,0,'C');
            $pdf->Cell(15,10,'',1,0,'C');
            $pdf->Cell(15,10,'',1,0,'C');
            $pdf->Cell(15,10,'',1,0,'C');
            $pdf->Cell(15,10,'',1,1,'C');
        }
        else if($row['mth']==4)
        {
            
            $pdf->Cell(15,10,'',1,0,'C');
            $pdf->Cell(15,10,'',1,0,'C');
            $pdf->Cell(15,10,'',1,0,'C');
            $pdf->Cell(15,10,$row['day'],1,0,'C');
            $pdf->Cell(15,10,'',1,0,'C');
            $pdf->Cell(15,10,'',1,0,'C');
            $pdf->Cell(15,10,'',1,0,'C');
            $pdf->Cell(15,10,'',1,0,'C');
            $pdf->Cell(15,10,'',1,0,'C');
            $pdf->Cell(15,10,'',1,0,'C');
            $pdf->Cell(15,10,'',1,0,'C');
            $pdf->Cell(15,10,'',1,1,'C');
        }
        else if($row['mth']==5)
        {
            
            $pdf->Cell(15,10,'',1,0,'C');
            $pdf->Cell(15,10,'',1,0,'C');
            $pdf->Cell(15,10,'',1,0,'C');
            $pdf->Cell(15,10,'',1,0,'C');
            $pdf->Cell(15,10,$row['day'],1,0,'C');
            $pdf->Cell(15,10,'',1,0,'C');
            $pdf->Cell(15,10,'',1,0,'C');
            $pdf->Cell(15,10,'',1,0,'C');
            $pdf->Cell(15,10,'',1,0,'C');
            $pdf->Cell(15,10,'',1,0,'C');
            $pdf->Cell(15,10,'',1,0,'C');
            $pdf->Cell(15,10,'',1,1,'C');
        }
        else if($row['mth']==6)
        {
            
            $pdf->Cell(15,10,'',1,0,'C');
            $pdf->Cell(15,10,'',1,0,'C');
            $pdf->Cell(15,10,'',1,0,'C');
            $pdf->Cell(15,10,'',1,0,'C');
            $pdf->Cell(15,10,'',1,0,'C');
            $pdf->Cell(15,10,$row['day'],1,0,'C');
            $pdf->Cell(15,10,'',1,0,'C');
            $pdf->Cell(15,10,'',1,0,'C');
            $pdf->Cell(15,10,'',1,0,'C');
            $pdf->Cell(15,10,'',1,0,'C');
            $pdf->Cell(15,10,'',1,0,'C');
            $pdf->Cell(15,10,'',1,1,'C');
        }
        else if($row['mth']==7)
        {
            
            $pdf->Cell(15,10,'',1,0,'C');
            $pdf->Cell(15,10,'',1,0,'C');
            $pdf->Cell(15,10,'',1,0,'C');
            $pdf->Cell(15,10,'',1,0,'C');
            $pdf->Cell(15,10,'',1,0,'C');
            $pdf->Cell(15,10,'',1,0,'C');
            $pdf->Cell(15,10,$row['day'],1,0,'C');
            $pdf->Cell(15,10,'',1,0,'C');
            $pdf->Cell(15,10,'',1,0,'C');
            $pdf->Cell(15,10,'',1,0,'C');
            $pdf->Cell(15,10,'',1,0,'C');
            $pdf->Cell(15,10,'',1,1,'C');
        }
        else if($row['mth']==8)
        {
            
            $pdf->Cell(15,10,'',1,0,'C');
            $pdf->Cell(15,10,'',1,0,'C');
            $pdf->Cell(15,10,'',1,0,'C');
            $pdf->Cell(15,10,'',1,0,'C');
            $pdf->Cell(15,10,'',1,0,'C');
            $pdf->Cell(15,10,'',1,0,'C');
            $pdf->Cell(15,10,'',1,0,'C');
            $pdf->Cell(15,10,$row['day'],1,0,'C');
            $pdf->Cell(15,10,'',1,0,'C');
            $pdf->Cell(15,10,'',1,0,'C');
            $pdf->Cell(15,10,'',1,0,'C');
            $pdf->Cell(15,10,'',1,1,'C');
        }
        else if($row['mth']==9)
        {
            
            $pdf->Cell(15,10,'',1,0,'C');
            $pdf->Cell(15,10,'',1,0,'C');
            $pdf->Cell(15,10,'',1,0,'C');
            $pdf->Cell(15,10,'',1,0,'C');
            $pdf->Cell(15,10,'',1,0,'C');
            $pdf->Cell(15,10,'',1,0,'C');
            $pdf->Cell(15,10,'',1,0,'C');
            $pdf->Cell(15,10,'',1,0,'C');
            $pdf->Cell(15,10,$row['day'],1,0,'C');
            $pdf->Cell(15,10,'',1,0,'C');
            $pdf->Cell(15,10,'',1,0,'C');
            $pdf->Cell(15,10,'',1,1,'C');
        }
        else if($row['mth']==10)
        {
            
            $pdf->Cell(15,10,'',1,0,'C');
            $pdf->Cell(15,10,'',1,0,'C');
            $pdf->Cell(15,10,'',1,0,'C');
            $pdf->Cell(15,10,'',1,0,'C');
            $pdf->Cell(15,10,'',1,0,'C');
            $pdf->Cell(15,10,'',1,0,'C');
            $pdf->Cell(15,10,'',1,0,'C');
            $pdf->Cell(15,10,'',1,0,'C');
            $pdf->Cell(15,10,'',1,0,'C');
            $pdf->Cell(15,10,$row['day'],1,0,'C');
            $pdf->Cell(15,10,'',1,0,'C');
            $pdf->Cell(15,10,'',1,1,'C');
        }
        else if($row['mth']==11)
        {
            
            $pdf->Cell(15,10,'',1,0,'C');
            $pdf->Cell(15,10,'',1,0,'C');
            $pdf->Cell(15,10,'',1,0,'C');
            $pdf->Cell(15,10,'',1,0,'C');
            $pdf->Cell(15,10,'',1,0,'C');
            $pdf->Cell(15,10,'',1,0,'C');
            $pdf->Cell(15,10,'',1,0,'C');
            $pdf->Cell(15,10,'',1,0,'C');
            $pdf->Cell(15,10,'',1,0,'C');
            $pdf->Cell(15,10,'',1,0,'C');
            $pdf->Cell(15,10,$row['day'],1,0,'C');
            $pdf->Cell(15,10,'',1,1,'C');
        }
        else if($row['mth']==12)
        {
            
            $pdf->Cell(15,10,'',1,0,'C');
            $pdf->Cell(15,10,'',1,0,'C');
            $pdf->Cell(15,10,'',1,0,'C');
            $pdf->Cell(15,10,'',1,0,'C');
            $pdf->Cell(15,10,'',1,0,'C');
            $pdf->Cell(15,10,'',1,0,'C');
            $pdf->Cell(15,10,'',1,0,'C');
            $pdf->Cell(15,10,'',1,0,'C');
            $pdf->Cell(15,10,'',1,0,'C');
            $pdf->Cell(15,10,'',1,0,'C');
            $pdf->Cell(15,10,'',1,0,'C');
            $pdf->Cell(15,10,$row['day'],1,1,'C');
        }
        
    //}
        
    
    
       
}


$pdf->AddPage('L','A4');
$pdf->SetFont('Arial','B',12);
$pdf->Write(10,'LEGENDS :- ');
$pdf->Ln();

$pdf->SetWidths(array(25,95));
// Set alignment for all cells in the row to center
$pdf->SetAligns(array('C', 'C'));


$pdf->Cell(25,10,'Test ID',1,0,'C');
$pdf->Cell(95,10,'Test Description',1,1,'C');
$pdf->SetFont('Arial','',12);
$pdf->Cell(25,10,'1',1,0,'C');
$pdf->Cell(95,10,'Air changes Per Hour',1,1,'C');

$pdf->Cell(25,10,'2',1,0,'C');
$pdf->Cell(95,10,'Fresh air CFM',1,1,'C');

$pdf->Cell(25,10,'3',1,0,'C');
$pdf->Cell(95,10,'Return air CFM',1,1,'C');

$pdf->Cell(25,10,'4',1,0,'C');
$pdf->Cell(95,10,'Relief air CFM',1,1,'C');

$pdf->Cell(25,10,'6',1,0,'C');
$pdf->Cell(95,10,'Filter integrity test',1,1,'C');

$pdf->Cell(25,10,'7',1,0,'C');
$pdf->Cell(95,10,'Dust collector/Scrubber/Point exhaust CFM',1,1,'C');

$pdf->Cell(25,10,'11',1,0,'C');
$pdf->Cell(95,10,'Particle matter count at rest condition',1,1,'C');

$pdf->Cell(25,10,'12',1,0,'C');
$pdf->Cell(95,10,'Particle matter count in operation condition',1,1,'C');

$pdf->Cell(25,10,'13',1,0,'C');
$pdf->Cell(95,10,'Containment leakage test',1,1,'C');

$pdf->Cell(25,10,'14',1,0,'C');
$pdf->Cell(95,10,'Area recovery/clean-up period study',1,1,'C');

$pdf->Cell(25,10,'17',1,0,'C');
$pdf->Cell(95,10,'Air Velocity study',1,1,'C');

$pdf->Cell(25,10,'18',1,0,'C');
$pdf->Cell(95,10,'Lead time study',1,1,'C');


$pdf->AddPage('L','A4');
// font and color selection
$pdf->SetFont('Arial','B',10);

// now write some text above the imported page

// Calculate equal margins for landscape A4 (297mm width)
$page_width = 297;
$first_cell_width = 50;
$second_cell_width = 120; // Desired width for second cell
$total_table_width = $first_cell_width + $second_cell_width;
$left_margin = ($page_width - $total_table_width) / 2;

$pdf->SetXY($left_margin, 53);
$pdf->Cell($first_cell_width,30,'Schedule Requested By:',1,0,'C');
$pdf->SetFont('Arial','',10);

// Set X position for second cell (aligned with first cell)
$pdf->SetXY($left_margin + $first_cell_width, 53);
$pdf->MultiCell($second_cell_width,15,"System Generated"."\n".'Date: '.date("d.m.Y H:i:s"),1,'C');





// Generate the PDF filename with sanitized values
$pdfFilename = 'rt-schedule-report-' . $unit_id . '-' . $sch_id . '.pdf';
$pdfPath = __DIR__ . '/uploads/' . basename($pdfFilename);

// Save PDF to file and also output to browser
try {
    $pdf->Output($pdfPath, 'F');
    
    // Check if file was created successfully
    if (file_exists($pdfPath)) {
        // Set headers for PDF display
        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="' . htmlspecialchars($pdfFilename, ENT_QUOTES) . '"');
        header('Cache-Control: private, max-age=0, must-revalidate');
        header('Pragma: public');
        
        // Output the file content
        readfile($pdfPath);
        
        // Optional: Clean up the file after sending it
        // unlink($pdfPath);
    } else {
        // If PDF generation failed, return error
        header('HTTP/1.1 500 Internal Server Error');
        echo "Error: Unable to generate PDF report";
    }
} catch (Exception $e) {
    error_log("PDF generation error in generatertschedulereport.php: " . $e->getMessage());
    header('HTTP/1.1 500 Internal Server Error');
    echo "Error: PDF generation failed";
}






?>