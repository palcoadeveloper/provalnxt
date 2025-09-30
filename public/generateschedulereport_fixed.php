<?php
// Fixed version of generateschedulereport.php without HTML output
// This version bypasses the problematic includes that output HTML

// Direct includes without session validation that might output HTML
require_once('./core/config/config.php');
require_once 'core/config/db.class.php';
include_once (__DIR__."/core/pdf/FPDF_CellFit_Sch.php");

// Enhanced session check without HTML output
if (!isset($_SESSION['user_name']) || empty($_SESSION['user_name'])) {
    header('HTTP/1.1 401 Unauthorized');
    header('Content-Type: text/plain');
    exit('Authentication required - No valid session');
}

// Additional session validation
if (!isset($_SESSION['user_id']) || !isset($_SESSION['unit_id'])) {
    header('HTTP/1.1 401 Unauthorized');
    header('Content-Type: text/plain');
    exit('Authentication required - Incomplete session data');
}

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

    $unit_details = DB::queryFirstRow("SELECT unit_name, unit_site FROM units WHERE unit_id = %i and unit_status='Active'", $unit_id);

    if (!$unit_details) {
        header('HTTP/1.1 404 Not Found');
        exit('Unit not found');
    }
} catch (Exception $e) {
    error_log("Database error in generateschedulereport_fixed.php: " . $e->getMessage());
    header('HTTP/1.1 500 Internal Server Error');
    exit('Error retrieving schedule data');
}

// Create PDF
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
        $pdf->SetAligns(array('C', 'C', 'C', 'C', 'C', 'C', 'C', 'C', 'C', 'C', 'C', 'C', 'C', 'C', 'C','C'));

        $pdf->Row(array('SR. NO.','EQUIPMENT CODE','EQUIPMENT NAME','JAN','FEB','MAR','APR','MAY','JUN','JUL','AUG','SEP','OCT','NOV','DEC'));

        $pdf->SetFont('Arial','',10);
    }

    $i++;
    $count++;
    $pdf->Cell(10,10,$count,1,0,'C');
    $pdf->Cell(43,10,$row['equipment_code'],1,0,'C');
    $pdf->Cell(44,10,$row['equipment_category'],1,0,'C');

    // Generate month cells based on month
    for ($month = 1; $month <= 12; $month++) {
        if ($row['mth'] == $month) {
            $pdf->Cell(15,10,$row['day'],1,0,'C');
        } else {
            $pdf->Cell(15,10,'',1,0,'C');
        }
    }
    $pdf->Ln();
}

$pdf->AddPage('L','A4');
$pdf->SetFont('Arial','B',10);

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
$pdf->MultiCell($second_cell_width,15,"System Generated" . "\n" . 'Date: ' . date("d.m.Y H:i:s"),1,'C');

// Output PDF directly to browser
header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="schedule-report-' . $unit_id . '-' . $sch_id . '.pdf"');
header('Cache-Control: private, max-age=0, must-revalidate');
header('Pragma: public');

$pdf->Output('', 'I'); // Output directly to browser
?>