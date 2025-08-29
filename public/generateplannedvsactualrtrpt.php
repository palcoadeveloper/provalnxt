<?php
require_once("core/config/config.php");

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
$required_params = ['user_name', 'user_id', 'sch_year', 'unit_id'];
foreach ($required_params as $param) {
    if (!isset($_GET[$param]) || empty(trim($_GET[$param]))) {
        header('HTTP/1.1 400 Bad Request');
        exit('Missing required parameter: ' . $param);
    }
}

// Validate numeric parameters
if (!is_numeric($_GET['user_id']) || !is_numeric($_GET['sch_year']) || !is_numeric($_GET['unit_id'])) {
    header('HTTP/1.1 400 Bad Request');
    exit('Invalid parameter format');
}

// Validate year format (4 digits, 20XX range)
if (!preg_match('/^20[0-9]{2}$/', $_GET['sch_year'])) {
    header('HTTP/1.1 400 Bad Request');
    exit('Invalid year format');
}

require_once(__DIR__."/core/pdf/FPDF_CellFit_Sch.php");

date_default_timezone_set("Asia/Kolkata");
// Sanitize input parameters
$user_name = htmlspecialchars(trim($_GET['user_name']), ENT_QUOTES, 'UTF-8');
$user_id = intval($_GET['user_id']);
$sch_year = intval($_GET['sch_year']);
$unit_id = intval($_GET['unit_id']);

try {
    $requester_details = DB::queryFirstRow("SELECT user_name, department_name, unit_name, u.unit_id
        FROM users u 
        LEFT JOIN departments d ON u.department_id = d.department_id
        LEFT JOIN units un ON u.unit_id = un.unit_id 
        WHERE user_id = %d", $user_id);
        
    if (!$requester_details) {
        header('HTTP/1.1 404 Not Found');
        exit('User not found');
    }
} catch (Exception $e) {
    require_once('core/error/error_logger.php');
    logDatabaseError("Database error in generateplannedvsactualrtrpt.php: " . $e->getMessage(), [
        'operation_name' => 'report_planned_vs_actual_rt',
        'unit_id' => $unit_id,
        'val_wf_id' => null, // Not applicable for these reports
        'equip_id' => null   // Multiple equipment in reports
    ]);
    header('HTTP/1.1 500 Internal Server Error');
    exit('Database error occurred');
}
$department_name=$requester_details['department_name'];
$unit_name=$requester_details['unit_name'];
//Commented out as the generic functionality takes care of printing the footer
 //$footer_text = ucwords('Document printed by '.$user_name." ".$department_name."-".$unit_name."/GOA ".date("d.m.Y H:i:s"));
$footer_text='';

// Extend FPDF class to add footer
class PDF_With_Footer extends FPDF_CellFit
{
    var $footer_text;
    
    function setFooterText($text) {
        $this->footer_text = $text;
    }
    
    function Footer()
    {
        // Position at 15 mm from bottom
        $this->SetY(-15);
        // Set font
        $this->SetFont('Arial','I',8);
        // Print footer text
        $this->Cell(0,10,$this->footer_text,0,0,'L');
        // Add page number
        $this->Cell(0,10,'Page '.$this->PageNo().'/{nb}',0,0,'R');
    }
}

try {
    $query_result = DB::query("SELECT 
        t1.unit_id, t1.test_id, equipment_code, equipment_category, routine_test_wf_planned_start_date,
        DAY(routine_test_wf_planned_start_date) AS planned_day, 
        MONTH(routine_test_wf_planned_start_date) AS planned_mth,
        CASE 
            WHEN YEAR(test_conducted_date) = %i THEN IFNULL(DAY(test_conducted_date), '') 
            ELSE IFNULL(DAY(test_conducted_date), '')
        END AS act_day,
        CASE 
            WHEN YEAR(test_conducted_date) = %i THEN IFNULL(MONTH(test_conducted_date), '') 
            ELSE IFNULL(MONTH(test_conducted_date), '') 
        END AS act_mth,
        CASE 
            WHEN YEAR(test_conducted_date) = %i THEN IFNULL(YEAR(test_conducted_date), '') 
            ELSE IFNULL(YEAR(test_conducted_date), '')
        END AS act_year
        FROM tbl_routine_test_schedules t1 
        LEFT JOIN equipments t2 ON t1.equip_id = t2.equipment_id
        LEFT JOIN tbl_routine_test_wf_tracking_details t3 ON t1.routine_test_wf_id = t3.routine_test_wf_id
        LEFT JOIN tbl_test_schedules_tracking t4 ON t1.routine_test_wf_id = t4.val_wf_id
        WHERE YEAR(t1.routine_test_wf_planned_start_date) = %i AND t1.unit_id = %i
        ORDER BY LENGTH(equipment_code), equipment_code, test_id, planned_mth, planned_day",
        $sch_year, $sch_year, $sch_year, $sch_year, $unit_id);
} catch (Exception $e) {
    require_once('core/error/error_logger.php');
    logDatabaseError("Database error in generateplannedvsactualrtrpt.php query: " . $e->getMessage(), [
        'operation_name' => 'report_planned_vs_actual_rt_query',
        'unit_id' => $unit_id,
        'val_wf_id' => null,
        'equip_id' => null
    ]);
    header('HTTP/1.1 500 Internal Server Error');
    exit('Error retrieving schedule data');
}

// Use our extended PDF class
$pdf = new PDF_With_Footer();
// Set the footer text
$pdf->setFooterText($footer_text);
// Enable page numbers
$pdf->AliasNbPages();

$pdf->AddPage('L','A4');
$pdf->SetFont('Arial','B',12);
$pdf->Write(10,'Unit ' . htmlspecialchars($unit_id, ENT_QUOTES));
$pdf->Ln();
$pdf->Cell(278,12,'HVAC ROUTINE TESTS PLANNED VS ACTUAL SCHEDULE FOR THE YEAR ' . htmlspecialchars($sch_year, ENT_QUOTES), 1,1, 'C');
$pdf->SetFont('Arial','B',10);

//$pdf->SetWidths(array(10,23,53,12,15,15,15,15,15,15,15,15,15,15,15,15));
  $pdf->SetWidths(array(10,33,43,12,15,15,15,15,15,15,15,15,15,15,15,15));
// Set alignment for all cells in the row to center
$pdf->SetAligns(array('C', 'C', 'C','C',  'C', 'C', 'C', 'C', 'C', 'C', 'C', 'C', 'C', 'C', 'C','C'));
$pdf->Row(array('SR. NO.','EQUIPMENT CODE','EQUIPMENT NAME','TEST ID','JAN','FEB','MAR','APR','MAY','JUN','JUL','AUG','SEP','OCT','NOV','DEC'));
$pdf->SetFont('Arial','',10);

//Set maximum rows per page
$max = 6; // Added on 31-12-2021
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
        
        $pdf->Write(10,'Unit ' . htmlspecialchars($unit_id, ENT_QUOTES)); // Added on 31-12-2021
        $pdf->Ln(); // Added on 31-12-2021
        
        $pdf->Cell(278,12,'HVAC ROUTINE TESTS PLANNED VS ACTUAL SCHEDULE FOR THE YEAR ' . htmlspecialchars($sch_year, ENT_QUOTES), 1,1, 'C');
        $pdf->SetFont('Arial','B',10);
        
        $pdf->SetWidths(array(10,33,43,12,15,15,15,15,15,15,15,15,15,15,15,15));
// Set alignment for all cells in the row to center
$pdf->SetAligns(array('C', 'C', 'C','C',  'C', 'C', 'C', 'C', 'C', 'C', 'C', 'C', 'C', 'C', 'C','C'));
        
        $pdf->Row(array('SR. NO.','EQUIPMENT CODE','EQUIPMENT NAME','TEST ID','JAN','FEB','MAR','APR','MAY','JUN','JUL','AUG','SEP','OCT','NOV','DEC'));
        
        $pdf->SetFont('Arial','',10);
        
    }
    
    
    $i++;// Added on 31-12-2021
   $count++;
   
   $pdf->Cell(10,20,$count,1,0,'C');
   $pdf->Cell(33,20,$row['equipment_code'],1,0,'C');
   $pdf->Cell(38,20,$row['equipment_category'],1,0,'C');
   
   $pdf->Cell(5,10,'P',1,0,'C');
   $pdf->Cell(12,10,$row['test_id'],1,0,'C');
   // Define number of months
$totalMonths = 12;

for ($month = 1; $month <= $totalMonths; $month++) {
    // If the planned month matches, print `planned_day`, otherwise print an empty cell
    $pdf->Cell(15, 10, ($row['planned_mth'] == $month) ? $row['planned_day'] : '', 1, 0, 'C');
}

// Move to the next row after completing all 12 months
$pdf->Ln();
 
$pdf->SetX(91);

   $pdf->Cell(5,10,'A',1,0,'C');
    $pdf->Cell(12,10,$row['test_id'],1,0,'C');
  
// Define number of months
$totalMonths = 12;

// Store the starting Y position for the row
$yPosition = $pdf->GetY();

for ($month = 1; $month <= $totalMonths; $month++) {
    $xPosition = $pdf->GetX(); // Get current X position before printing

    if ($row['act_mth'] == $month) {
        // If act_year is different from sch_year, split into two rows
        if ($row['act_year'] != intval($_GET['sch_year']) && !empty($row['act_year'])) {
            // Print both act_day and act_year inside the same cell with a line break
            //$pdf->Cell(15, 10, $row['act_day'] . "\n[" . $row['act_year'] . "]", 1, 0, 'C');

// Print act_day in the top half of the cell
    $pdf->Cell(15, 5, $row['act_day'], 'LTR', 0, 'C'); 

    // Move cursor downward to print act_year in the same cell
    $pdf->SetXY($xPosition, $yPosition + 5);
    $pdf->Cell(15, 5, "[" . $row['act_year'] . "]", 'LBR', 0, 'C'); 

    // Check if this is the last cell in the row (Adjust column count as needed)
    if ($xPosition + 15 >= $pdf->GetPageWidth() - 10) { 
        //$pdf->Ln(); // Move to the next line
    } else {
        $pdf->SetXY($xPosition + 15, $yPosition); // Move to the next cell in the row
    }
            
        } else {
            // Print only act_day in a normal full-height cell
            $pdf->Cell(15, 10, $row['act_day'], 1, 0, 'C');
        }
    } else {
        // Empty cell for months with no actual test conducted
        $pdf->Cell(15, 10, '', 1, 0, 'C');
    }
}

// Move to the next row **only once after all 12 months**
$pdf->Ln(); 
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
// Generate the PDF filename with sanitized values
$pdfFilename = 'plannedvsactualrt-report-' . $unit_id . '-' . $sch_year . '.pdf';
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
    error_log("PDF generation error in generateplannedvsactualrtrpt.php: " . $e->getMessage());
    header('HTTP/1.1 500 Internal Server Error');
    echo "Error: PDF generation failed";
}
?>