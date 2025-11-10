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
$required_params = ['unit_id', 'vendor_id', 'report_year', 'vendor_name'];
foreach ($required_params as $param) {
    if (!isset($_GET[$param]) || empty(trim($_GET[$param]))) {
        header('HTTP/1.1 400 Bad Request');
        exit('Missing required parameter: ' . $param);
    }
}

// Validate numeric parameters (unit_id and vendor_id can be 'ALL')
if (($_GET['unit_id'] !== 'ALL' && !is_numeric($_GET['unit_id'])) ||
    ($_GET['vendor_id'] !== 'ALL' && !is_numeric($_GET['vendor_id'])) ||
    !is_numeric($_GET['report_year'])) {
    header('HTTP/1.1 400 Bad Request');
    exit('Invalid parameter format');
}

// Validate year format (4 digits, 20XX range)
if (!preg_match('/^20[0-9]{2}$/', $_GET['report_year'])) {
    header('HTTP/1.1 400 Bad Request');
    exit('Invalid year format');
}

// Sanitize parameters
$unit_id = $_GET['unit_id'];
$vendor_id = $_GET['vendor_id'] === 'ALL' ? 'ALL' : intval($_GET['vendor_id']);
$report_year = intval($_GET['report_year']);
$vendor_name = htmlspecialchars(trim($_GET['vendor_name']), ENT_QUOTES, 'UTF-8');

date_default_timezone_set("Asia/Kolkata");

try {
    // Build the base query for detailed test allocation data
    $base_query = "
        SELECT DISTINCT
            t.test_id,
            vs.val_wf_id,
            MONTH(vs.val_wf_planned_start_date) as month,
            YEAR(vs.val_wf_planned_start_date) as year,
            t.test_name,
            e.equipment_code,
            u.unit_name,
            u.unit_id,
            vs.val_wf_planned_start_date
        FROM tbl_val_schedules vs
        JOIN equipment_test_vendor_mapping etvm ON vs.equip_id = etvm.equipment_id
        JOIN tests t ON etvm.test_id = t.test_id
        JOIN equipments e ON vs.equip_id = e.equipment_id
        JOIN units u ON vs.unit_id = u.unit_id
        WHERE vs.val_wf_status = 'Active'
            AND etvm.vendor_id > 0
            AND YEAR(vs.val_wf_planned_start_date) = %i
            AND etvm.mapping_status = 'Active'";

    // Add unit filter if specific unit is selected
    if ($unit_id !== 'ALL') {
        $base_query .= " AND vs.unit_id = %i";
    }

    // Add vendor filter if specific vendor is selected
    if ($vendor_id !== 'ALL') {
        $base_query .= " AND etvm.vendor_id = %i";
    }

    $base_query .= "
        ORDER BY u.unit_name, YEAR(vs.val_wf_planned_start_date), MONTH(vs.val_wf_planned_start_date), t.test_id";

    // Execute query with appropriate parameters
    if ($unit_id !== 'ALL' && $vendor_id !== 'ALL') {
        $query_result = DB::query($base_query, $report_year, intval($unit_id), intval($vendor_id));
    } elseif ($unit_id !== 'ALL') {
        $query_result = DB::query($base_query, $report_year, intval($unit_id));
    } elseif ($vendor_id !== 'ALL') {
        $query_result = DB::query($base_query, $report_year, intval($vendor_id));
    } else {
        $query_result = DB::query($base_query, $report_year);
    }

    // Group results by unit
    $units_data = array();
    foreach ($query_result as $row) {
        $unit_name = $row['unit_name'];
        if (!isset($units_data[$unit_name])) {
            $units_data[$unit_name] = array();
        }
        $units_data[$unit_name][] = $row;
    }

    if (empty($query_result)) {
        header('HTTP/1.1 404 Not Found');
        exit('No test allocations found for the specified vendor and year');
    }

    // Get user details for footer
    $user_details = DB::queryFirstRow("SELECT user_name FROM users WHERE user_id = %i", $_SESSION['user_id']);
    $user_name = $user_details ? $user_details['user_name'] : 'Unknown User';

} catch (Exception $e) {
    error_log("Database error in generate_external_test_allocation_pdf.php: " . $e->getMessage());
    header('HTTP/1.1 500 Internal Server Error');
    exit('Database error occurred');
}

// Create PDF
$pdf = new FPDF_CellFit('P', 'mm', 'A4');
$pdf->AddPage();

// Add company logo if exists
if (file_exists('assets/images/logo.png')) {
    $pdf->Image('assets/images/logo.png', 10, 8, 30);
}

// Header
$pdf->SetFont('Arial', 'B', 16);
$pdf->Cell(0, 10, 'External Test Allocation Report', 0, 1, 'C');
$pdf->Ln(5);

// Report details
$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(50, 8, 'Vendor: ', 0, 0, 'L');
$pdf->SetFont('Arial', '', 12);
$pdf->Cell(0, 8, $vendor_name, 0, 1, 'L');

$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(50, 8, 'Year: ', 0, 0, 'L');
$pdf->SetFont('Arial', '', 12);
$pdf->Cell(0, 8, $report_year, 0, 1, 'L');

$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(50, 8, 'Generated on: ', 0, 0, 'L');
$pdf->SetFont('Arial', '', 12);
$pdf->Cell(0, 8, date('d.m.Y H:i:s'), 0, 1, 'L');

$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(50, 8, 'Generated by: ', 0, 0, 'L');
$pdf->SetFont('Arial', '', 12);
$pdf->Cell(0, 8, $user_name, 0, 1, 'L');

$pdf->Ln(10);

// Define column widths (without Unit column since it's now in section headers)
$col_widths = array(12, 25, 50, 22, 22, 54);
$headers = array('#', 'Test ID', 'Val WF ID', 'Month', 'Year', 'Equipment Code');

// Month name conversion
$month_names = array(
    1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April',
    5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August',
    9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December'
);

$overall_count = 1;
$total_allocations = 0;

// Generate unit-wise tables
foreach ($units_data as $unit_name => $unit_tests) {
    // Unit header - calculate total table width for proper alignment
    $table_width = array_sum($col_widths);
    $pdf->SetFont('Arial', 'B', 12);
    $pdf->SetFillColor(200, 200, 200);
    $pdf->Cell($table_width, 10, $unit_name, 1, 1, 'L', true);
    $pdf->Ln(2);

    // Table headers for this unit
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->SetFillColor(230, 230, 230);
    for ($i = 0; $i < count($headers); $i++) {
        $pdf->Cell($col_widths[$i], 8, $headers[$i], 1, 0, 'C', true);
    }
    $pdf->Ln();

    // Table data for this unit
    $pdf->SetFont('Arial', '', 9);
    $pdf->SetFillColor(255, 255, 255);

    $unit_count = 1;
    foreach ($unit_tests as $row) {
        // Check if we need a new page
        if ($pdf->GetY() > 250) { // Near bottom of page
            $pdf->AddPage();

            // Repeat unit header
            $pdf->SetFont('Arial', 'B', 12);
            $pdf->SetFillColor(200, 200, 200);
            $pdf->Cell($table_width, 10, $unit_name . ' (continued)', 1, 1, 'L', true);
            $pdf->Ln(2);

            // Repeat table headers
            $pdf->SetFont('Arial', 'B', 10);
            $pdf->SetFillColor(230, 230, 230);
            for ($i = 0; $i < count($headers); $i++) {
                $pdf->Cell($col_widths[$i], 8, $headers[$i], 1, 0, 'C', true);
            }
            $pdf->Ln();
            $pdf->SetFont('Arial', '', 9);
            $pdf->SetFillColor(255, 255, 255);
        }

        $month_name = isset($month_names[$row['month']]) ? $month_names[$row['month']] : $row['month'];

        // Print data row
        $pdf->Cell($col_widths[0], 6, $unit_count, 1, 0, 'C');
        $pdf->Cell($col_widths[1], 6, $row['test_id'], 1, 0, 'C');
        $pdf->CellFitScale($col_widths[2], 6, $row['val_wf_id'], 1, 0, 'C');
        $pdf->Cell($col_widths[3], 6, $month_name, 1, 0, 'C');
        $pdf->Cell($col_widths[4], 6, $row['year'], 1, 0, 'C');
        $pdf->CellFitScale($col_widths[5], 6, $row['equipment_code'], 1, 1, 'L');

        $unit_count++;
        $overall_count++;
        $total_allocations++;
    }

    // Unit summary
    $pdf->Ln(2);
    $pdf->SetFont('Arial', 'B', 9);
    $pdf->Cell(0, 6, 'Total tests for ' . $unit_name . ': ' . count($unit_tests), 0, 1, 'L');
    $pdf->Ln(5);
}

// Footer with total count
$pdf->Ln(5);
$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(0, 8, 'Total External Test Allocations: ' . $total_allocations, 0, 1, 'L');
$pdf->Cell(0, 8, 'Total Units: ' . count($units_data), 0, 1, 'L');

// File output - save to uploads directory for modal viewing
$filename = "external_test_allocation_" . $vendor_id . "_" . $report_year . "_" . date('Ymd_His') . ".pdf";
$filepath = "uploads/" . $filename;

try {
    // Ensure uploads directory exists
    if (!file_exists('uploads')) {
        mkdir('uploads', 0755, true);
    }

    // Save PDF file
    $pdf->Output('F', $filepath);

    // Return success response for modal viewing
    if (isset($_GET['ajax']) && $_GET['ajax'] == '1') {
        echo json_encode([
            'status' => 'success',
            'pdf_path' => $filepath,
            'filename' => $filename
        ]);
    } else {
        // Fallback: direct download if not AJAX request
        $pdf->Output('D', $filename);
    }
} catch (Exception $e) {
    error_log("PDF output error: " . $e->getMessage());
    if (isset($_GET['ajax']) && $_GET['ajax'] == '1') {
        echo json_encode([
            'status' => 'error',
            'message' => 'Error generating PDF'
        ]);
    } else {
        header('HTTP/1.1 500 Internal Server Error');
        exit('Error generating PDF');
    }
}
?>