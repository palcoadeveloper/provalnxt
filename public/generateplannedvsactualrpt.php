<?php

require_once("core/config/config.php");

// Session is already started by config.php via session_init.php
// Validate session timeout
require_once('core/security/session_timeout_middleware.php');
validateActiveSession();
require_once('core/config/db.class.php');

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
include_once (__DIR__."/core/pdf/FPDF_CellFit_Sch.php");


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
        WHERE user_id = %i", $user_id);
        
    if (!$requester_details) {
        header('HTTP/1.1 404 Not Found');
        exit('User not found');
    }
} catch (Exception $e) {
    require_once('core/error/error_logger.php');
    logDatabaseError("Database error in generateplannedvsactualrpt.php: " . $e->getMessage(), [
        'operation_name' => 'report_planned_vs_actual',
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
    $footer_text = '';

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


$query_result=DB::query("SELECT 
    t1.unit_id, 
    t2.equipment_code, 
    t2.equipment_category, 
    t1.val_wf_planned_start_date,
    DAY(t1.val_wf_planned_start_date) AS planned_day, 
    MONTH(t1.val_wf_planned_start_date) AS planned_mth, 

    -- Get actual conducted date
    CASE 
        WHEN YEAR(t4.test_conducted_date) = %i THEN IFNULL(DAY(t4.test_conducted_date), '') 
        ELSE IFNULL(DAY(test_conducted_date), '')
    END AS act_day,

    -- Get actual conducted month
    CASE 
        WHEN YEAR(t4.test_conducted_date) = %i THEN IFNULL(MONTH(t4.test_conducted_date), '') 
        ELSE IFNULL(MONTH(test_conducted_date), '')
    END AS act_mth,

    -- Get actual conducted year
    CASE 
        WHEN YEAR(t4.test_conducted_date) = %i THEN IFNULL(YEAR(t4.test_conducted_date), '') 
        ELSE IFNULL(Year(test_conducted_date), '')
    END AS act_year

FROM 
    tbl_val_schedules t1
    LEFT JOIN equipments t2 ON t1.equip_id = t2.equipment_id
    LEFT JOIN tbl_val_wf_tracking_details t3 ON t1.val_wf_id = t3.val_wf_id
    
    -- Join with test tracking table using primary_test_id first, if not found then secondary_test_id
    LEFT JOIN tbl_test_schedules_tracking t4 
        ON t1.val_wf_id = t4.val_wf_id 
        AND t4.test_id = (
            SELECT 
                CASE 
                    WHEN EXISTS (
                        SELECT 1 FROM tbl_test_schedules_tracking 
                        WHERE test_id = u.primary_test_id 
                        AND val_wf_id = t1.val_wf_id
                    ) 
                    THEN u.primary_test_id
                    ELSE u.secondary_test_id
                END 
            FROM units u 
            WHERE u.unit_status='Active' and u.unit_id = %i
        )

WHERE 
    YEAR(t1.val_wf_planned_start_date) = %i 
    AND t1.unit_id = %i

ORDER BY 
    LENGTH(SUBSTRING_INDEX(equipment_code, '-', 1)),
    CAST(SUBSTRING_INDEX(equipment_code, '-', -1) AS SIGNED),
    equipment_code",intval($_GET['sch_year']),intval($_GET['sch_year']),intval($_GET['sch_year']),intval($_GET['unit_id']),intval($_GET['sch_year']),intval($_GET['unit_id']));



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
$pdf->Cell(0,12,'HVAC VALIDATION PLANNED VS ACTUAL SCHEDULE FOR THE YEAR ' . htmlspecialchars($sch_year, ENT_QUOTES), 1,1, 'C');
$pdf->SetFont('Arial','B',10);

$pdf->SetWidths(array(10,41,46,15,15,15,15,15,15,15,15,15,15,15,15));
 // Set alignment for all cells in the row to center
$pdf->SetAligns(array('C', 'C', 'C', 'C', 'C', 'C', 'C', 'C', 'C', 'C', 'C', 'C', 'C', 'C', 'C','C'));
      
$pdf->Row(array('SR. NO.','EQUIPMENT CODE','EQUIPMENT NAME','JAN','FEB','MAR','APR','MAY','JUN','JUL','AUG','SEP','OCT','NOV','DEC'));
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
        
        $pdf->Cell(0,12,'HVAC VALIDATION PLANNED VS ACTUAL SCHEDULE FOR THE YEAR ' . htmlspecialchars($sch_year, ENT_QUOTES), 1,1, 'C');
        $pdf->SetFont('Arial','B',10);
        
        $pdf->SetWidths(array(10,41,46,15,15,15,15,15,15,15,15,15,15,15,15));
                // Set alignment for all cells in the row to center
$pdf->SetAligns(array('C', 'C', 'C', 'C', 'C', 'C', 'C', 'C', 'C', 'C', 'C', 'C', 'C', 'C', 'C','C'));
        $pdf->Row(array('SR. NO.','EQUIPMENT CODE','EQUIPMENT NAME','JAN','FEB','MAR','APR','MAY','JUN','JUL','AUG','SEP','OCT','NOV','DEC'));
        
        $pdf->SetFont('Arial','',10);
        
    }
    
    
    $i++;// Added on 31-12-2021
   $count++;
   
   $pdf->Cell(10,20,$count,1,0,'C');
   $pdf->Cell(41,20,$row['equipment_code'],1,0,'C');
   $pdf->Cell(41,20,$row['equipment_category'],1,0,'C');
   $pdf->Cell(5,10,'P',1,0,'C');
  // Define total months
$totalMonths = 12;

for ($month = 1; $month <= $totalMonths; $month++) {
    // If this month matches the planned month, print the planned_day, otherwise print an empty cell
    $pdf->Cell(15, 10, ($row['planned_mth'] == $month) ? $row['planned_day'] : '', 1, 0, 'C');
}

// After printing the "Planned" row and before starting the "Actual" row
$pdf->Ln(); 
$pdf->SetX(10); // Reset to left margin
$pdf->Cell(10, 10, '', 0, 0); // Empty cell for SR NO column
$pdf->Cell(41, 10, '', 0, 0); // Empty cell for EQUIPMENT CODE column
$pdf->Cell(41, 10, '', 0, 0); // Empty cell for EQUIPMENT NAME column
$pdf->Cell(5, 10, 'A', 1, 0, 'C');

// For printing the months in the "Actual" row
for ($month = 1; $month <= $totalMonths; $month++) {
    $xPosition = $pdf->GetX();
    $yPosition = $pdf->GetY();
    
    if ($row['act_mth'] == $month) {
        if ($row['act_year'] != intval($_GET['sch_year']) && !empty($row['act_year'])) {
            $pdf->Cell(15, 5, $row['act_day'], 'LTR', 0, 'C'); 
            $pdf->SetXY($xPosition, $yPosition + 5);
            $pdf->Cell(15, 5, "[" . $row['act_year'] . "]", 'LBR', 0, 'C'); 
            $pdf->SetXY($xPosition + 15, $yPosition); // Restore position for next cell
        } else {
            $pdf->Cell(15, 10, $row['act_day'], 1, 0, 'C');
        }
    } else {
        $pdf->Cell(15, 10, '', 1, 0, 'C');
    }
}

// After the "Actual" row, move to the next equipment item line
$pdf->Ln(10); // Move down a full row height

}
//$pdf->Output('uploads\schedule-report-'.$_GET['unit_id'].'-'.$_GET['sch_id'].'.pdf','F');
// Generate the PDF filename with sanitized values
$pdfFilename = 'plannedvsactual-report-' . $unit_id . '-' . $sch_year . '.pdf';
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
    error_log("PDF generation error in generateplannedvsactualrpt.php: " . $e->getMessage());
    header('HTTP/1.1 500 Internal Server Error');
    echo "Error: PDF generation failed";
}






?>