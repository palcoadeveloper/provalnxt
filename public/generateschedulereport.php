<?php
require_once('./core/config/config.php');

// Session is already started by config.php via session_init.php
// Validate session timeout
require_once('core/security/session_timeout_middleware.php');
validateActiveSession();

include_once (__DIR__."/core/config/db.class.php");
include_once (__DIR__."/core/pdf/FPDF_CellFit_Sch.php");

// Use centralized session validation
require_once('core/security/session_validation.php');
validateUserSession();

// Validate and sanitize URL parameters
$required_params = ['sch_id', 'unit_id', 'sch_year', 'user_name'];
foreach ($required_params as $param) {
    if (!isset($_GET[$param]) || empty(trim($_GET[$param]))) {
        header('HTTP/1.1 400 Bad Request');
        exit('Missing required parameter: ' . $param);
    }
}

// Validate numeric parameters
if (!is_numeric($_GET['sch_id']) || !is_numeric($_GET['unit_id']) || !is_numeric($_GET['sch_year'])) {
    header('HTTP/1.1 400 Bad Request');
    exit('Invalid parameter format');
}

// Sanitize parameters
$sch_id = intval($_GET['sch_id']);
$unit_id = intval($_GET['unit_id']);
$sch_year = intval($_GET['sch_year']);
$user_name = htmlspecialchars(trim($_GET['user_name']), ENT_QUOTES, 'UTF-8');

date_default_timezone_set("Asia/Kolkata");





try {
    $query_result = DB::query("SELECT
        t1.unit_id,
        equipment_code,
        equipment_category,
        val_wf_planned_start_date,
        DAY(val_wf_planned_start_date) AS day,
        MONTH(val_wf_planned_start_date) AS mth
        FROM tbl_proposed_val_schedules t1
        JOIN equipments t2 ON t1.equip_id = t2.equipment_id
        WHERE schedule_id = %i
        ORDER BY LENGTH(SUBSTRING_INDEX(equipment_code, '-', 1)),
        CAST(SUBSTRING_INDEX(equipment_code, '-', -1) AS SIGNED),
        equipment_code", $sch_id);

    $unit_details = DB::queryFirstRow("SELECT unit_name, unit_site FROM units WHERE unit_id = %i", $unit_id);
    
    if (!$unit_details) {
        header('HTTP/1.1 404 Not Found');
        exit('Unit not found');
    }
} catch (Exception $e) {
    require_once('core/error/error_logger.php');
    logDatabaseError("Database error in generateschedulereport.php: " . $e->getMessage(), [
        'operation_name' => 'report_schedule_generation',
        'unit_id' => $unit_id,
        'val_wf_id' => null,
        'equip_id' => null
    ]);
    header('HTTP/1.1 500 Internal Server Error');
    exit('Error retrieving schedule data');
}

$pdf = new FPDF_CellFit();
$pdf->AddPage('L','A4');
$pdf->SetFont('Arial','B',12);
$pdf->Write(10, htmlspecialchars($unit_details['unit_name'], ENT_QUOTES));
$pdf->Ln();
$pdf->Cell(0,12,'HVAC VALIDATION SCHEDULE FOR THE YEAR ' . htmlspecialchars($sch_year, ENT_QUOTES), 1,1, 'C');
$pdf->SetFont('Arial','B',10);

$pdf->SetWidths(array(10,43,44,15,15,15,15,15,15,15,15,15,15,15,15));
// Set alignment for all cells in the row to center
$pdf->SetAligns(array('C', 'C', 'C', 'C', 'C', 'C', 'C', 'C', 'C', 'C', 'C', 'C', 'C', 'C', 'C','C'));

$pdf->Row(array('SR. NO.','EQUIPMENT CODE','EQUIPMENT NAME','JAN','FEB','MAR','APR','MAY','JUN','JUL','AUG','SEP','OCT','NOV','DEC'));
$pdf->SetFont('Arial','',10);

//Set maximum rows per page
$max = 12; // Added on 31-12-2021
$i=0; // Added on 31-12-2021


$count=0;
$output="";
foreach($query_result as $row)
{
    
    //If the current row is the last one, create new page and print column title
    if ($i == $max)
    {
        $pdf->AddPage('L','A4'); // Added on 31-12-2021
        $i=0;
        $pdf->SetFont('Arial','B',12);
        
        $pdf->Write(10,$unit_details['unit_name']); // Added on 31-12-2021
        $pdf->Ln(); // Added on 31-12-2021
        
        $pdf->Cell(0,12,'HVAC VALIDATION SCHEDULE FOR THE YEAR '.$_GET['sch_year'], 1,1, 'C');
        $pdf->SetFont('Arial','B',10);
        
        $pdf->SetWidths(array(10,43,44,15,15,15,15,15,15,15,15,15,15,15,15));
        // Set alignment for all cells in the row to center
$pdf->SetAligns(array('C', 'C', 'C', 'C', 'C', 'C', 'C', 'C', 'C', 'C', 'C', 'C', 'C', 'C', 'C','C'));

        $pdf->Row(array('SR. NO.','EQUIPMENT CODE','EQUIPMENT NAME','JAN','FEB','MAR','APR','MAY','JUN','JUL','AUG','SEP','OCT','NOV','DEC'));
        
        $pdf->SetFont('Arial','',10);
        
    }
    
    
    $i++;// Added on 31-12-2021
   $count++;
   $pdf->Cell(10,10,$count,1,0,'C');
   $pdf->Cell(43,10,$row['equipment_code'],1,0,'C');
   $pdf->Cell(44,10,$row['equipment_category'],1,0,'C');
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
 
       
}

$pdf->AddPage('L','A4');
// font and color selection
$pdf->SetFont('Arial','B',10);

// now write some text above the imported page

$pdf->SetXY(20, 53);
$pdf->Cell(50,30,'Schedule Requested By:',1,0,'C');
$pdf->SetFont('Arial','',10);
$pdf->MultiCell(0,10,htmlspecialchars($user_name, ENT_QUOTES) . "\n" . "Engineering (Cipla Ltd.)" . "\n" . 'Date: ' . date("d.m.Y H:i:s"),1,'C');

//$pdf->MultiCell(0,10,$_GET['user_name']."\n"."Engineering (Cipla Ltd.)"."\n".'Date: '.date("d-M-Y H:i:s",strtotime("2021-01-16 10:43:00")),1,'C');



// Generate secure filename and output PDF
$pdfFilename = 'schedule-report-' . $unit_id . '-' . $sch_id . '.pdf';
$pdfPath = __DIR__ . '/uploads/' . basename($pdfFilename);

try {
    $pdf->Output($pdfPath, 'F');
    
    // Set headers for PDF display
    header('Content-Type: application/pdf');
    header('Content-Disposition: inline; filename="' . htmlspecialchars($pdfFilename, ENT_QUOTES) . '"');
    header('Cache-Control: private, max-age=0, must-revalidate');
    header('Pragma: public');
    
    // Output the file content
    if (file_exists($pdfPath)) {
        readfile($pdfPath);
        // Optional: Clean up the file after sending it
        // unlink($pdfPath);
    } else {
        header('HTTP/1.1 500 Internal Server Error');
        echo "Error: PDF file could not be created";
    }
} catch (Exception $e) {
    require_once('core/error/error_logger.php');
    logDatabaseError("PDF generation error in generateschedulereport.php: " . $e->getMessage(), [
        'operation_name' => 'report_schedule_pdf_generation',
        'unit_id' => $unit_id,
        'val_wf_id' => null,
        'equip_id' => null
    ]);
    header('HTTP/1.1 500 Internal Server Error');
    echo "Error: PDF generation failed";
}






?>