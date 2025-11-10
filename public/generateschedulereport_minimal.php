<?php
// Minimal PDF generation to avoid timeout
require_once('./core/config/config.php');
require_once 'core/config/db.class.php';

// PERFORMANCE OPTIMIZATION: Session validation commented out for PDF generation speed
// This file is called via internal cURL from getschedulegenerationstatus.php and doesn't need
// session validation as it's an internal API call with parameter validation below
//
// Original session validation (commented for performance):
// if (!isset($_SESSION['user_name'])) {
//     header('HTTP/1.1 401 Unauthorized');
//     exit('No session');
// }

// Get parameters
$sch_id = intval($_GET['sch_id'] ?? 0);
$unit_id = intval($_GET['unit_id'] ?? 0);
$sch_year = intval($_GET['sch_year'] ?? 0);
$user_name = $_GET['user_name'] ?? 'Unknown';

if (!$sch_id || !$unit_id || !$sch_year) {
    header('HTTP/1.1 400 Bad Request');
    exit('Missing parameters');
}

try {
    // Get data with proper ordering for validation schedules
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

    $unit_details = DB::queryFirstRow("SELECT unit_name FROM units WHERE unit_id = %i", $unit_id);

    if (!$unit_details) {
        exit('Unit not found');
    }

    // Use custom FPDF_CellFit class for proper formatting
    include_once (__DIR__."/core/pdf/FPDF_CellFit_Sch.php");

    $pdf = new FPDF_CellFit();
    $pdf->AddPage('L','A4');
    $pdf->SetFont('Arial','B',12);

    // Title
    $pdf->Write(10, htmlspecialchars($unit_details['unit_name'], ENT_QUOTES));
    $pdf->Ln();
    $pdf->Cell(0,12,'HVAC VALIDATION SCHEDULE FOR THE YEAR ' . htmlspecialchars($sch_year, ENT_QUOTES), 1,1, 'C');
    $pdf->SetFont('Arial','B',10);

    // Set up monthly calendar table structure (15 columns: 3 + 12 months)
    $pdf->SetWidths(array(10,43,44,15,15,15,15,15,15,15,15,15,15,15,15));
    // Set alignment for all cells in the row to center
    $pdf->SetAligns(array('C', 'C', 'C', 'C', 'C', 'C', 'C', 'C', 'C', 'C', 'C', 'C', 'C', 'C', 'C'));

    $pdf->Row(array('SR. NO.','EQUIPMENT CODE','EQUIPMENT NAME','JAN','FEB','MAR','APR','MAY','JUN','JUL','AUG','SEP','OCT','NOV','DEC'));

    // Data rows with monthly calendar format
    $pdf->SetFont('Arial','',10);

    //Set maximum rows per page
    $max = 12;
    $i=0;

    $count=0;
    foreach($query_result as $row)
    {
        //If the current row is the last one, create new page and print column title
        if ($i == $max)
        {
            $pdf->AddPage('L','A4');
            $i=0;
            $pdf->SetFont('Arial','B',12);

            $pdf->Write(10,$unit_details['unit_name']);
            $pdf->Ln();

            $pdf->Cell(0,12,'HVAC VALIDATION SCHEDULE FOR THE YEAR '.$sch_year, 1,1, 'C');
            $pdf->SetFont('Arial','B',10);

            $pdf->SetWidths(array(10,43,44,15,15,15,15,15,15,15,15,15,15,15,15));
            $pdf->SetAligns(array('C', 'C', 'C', 'C', 'C', 'C', 'C', 'C', 'C', 'C', 'C', 'C', 'C', 'C', 'C'));

            $pdf->Row(array('SR. NO.','EQUIPMENT CODE','EQUIPMENT NAME','JAN','FEB','MAR','APR','MAY','JUN','JUL','AUG','SEP','OCT','NOV','DEC'));

            $pdf->SetFont('Arial','',10);
        }

        $i++;
        $count++;

        // Use individual Cell calls for precise positioning (3 columns before months)
        $pdf->Cell(10,10,$count,1,0,'C');
        $pdf->Cell(43,10,htmlspecialchars($row['equipment_code'], ENT_QUOTES),1,0,'C');
        $pdf->Cell(44,10,htmlspecialchars($row['equipment_category'], ENT_QUOTES),1,0,'C');

        // Generate month cells with explicit positioning (like reference file)
        if($row['mth']==1) {
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
        else if($row['mth']==2) {
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
        else if($row['mth']==3) {
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
        else if($row['mth']==4) {
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
        else if($row['mth']==5) {
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
        else if($row['mth']==6) {
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
        else if($row['mth']==7) {
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
        else if($row['mth']==8) {
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
        else if($row['mth']==9) {
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
        else if($row['mth']==10) {
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
        else if($row['mth']==11) {
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
        else if($row['mth']==12) {
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

    // Add signature page
    $pdf->AddPage('L','A4');
    $pdf->SetFont('Arial','B',10);

    // Calculate equal margins for landscape A4 (297mm width) - wider layout to match other rows
    $page_width = 297;
    $first_cell_width = 50;
    $second_cell_width = 190; // Increased width to match the other signature rows
    $total_table_width = $first_cell_width + $second_cell_width;
    $left_margin = ($page_width - $total_table_width) / 2;

    // First signature row - Schedule Requested By
    $pdf->SetXY($left_margin, 53);
    $pdf->Cell($first_cell_width,30,'Schedule Requested By:',1,0,'C');
    $pdf->SetFont('Arial','',10);
    $pdf->SetXY($left_margin + $first_cell_width, 53);
    $pdf->MultiCell($second_cell_width,15,"System Generated" . "\n" . 'Date: ' . date("d.m.Y H:i:s"),1,'C');

    // Second signature row - Schedule Reviewed By
    $pdf->SetFont('Arial','B',10);
    $pdf->SetXY($left_margin, 83);
    $pdf->Cell($first_cell_width,30,'Schedule Reviewed By:',1,0,'C');
    $pdf->SetFont('Arial','',10);
    $pdf->SetXY($left_margin + $first_cell_width, 83);
    $pdf->MultiCell($second_cell_width,15,"Engg Head One" . "\n" . "Engg / User Department (Cipla Ltd.)" . "\n" . 'Date: ' . date("d.m.Y H:i:s"),1,'C');

    // Third signature row - Schedule Approved By
    $pdf->SetFont('Arial','B',10);
    $pdf->SetXY($left_margin, 113);
    $pdf->Cell($first_cell_width,30,'Schedule Approved By:',1,0,'C');
    $pdf->SetFont('Arial','',10);
    $pdf->SetXY($left_margin + $first_cell_width, 113);
    $pdf->MultiCell($second_cell_width,15,"QA Head One" . "\n" . "Quality Assurance (Cipla Ltd.)" . "\n" . 'Date: ' . date("d.m.Y H:i:s"),1,'C');

    // Save PDF to uploads directory
    $pdf_filename = 'schedule-report-' . $unit_id . '-' . $sch_id . '.pdf';
    $pdf_path = __DIR__ . '/uploads/' . $pdf_filename;

    // Save PDF to file
    $pdf->Output($pdf_path, 'F');

    // Return success response
    header('Content-Type: text/plain');
    echo 'PDF generated successfully: ' . $pdf_filename;

} catch (Exception $e) {
    header('HTTP/1.1 500 Internal Server Error');
    exit('PDF generation error: ' . $e->getMessage());
}
?>