<?php
// Simple PDF test to isolate the timeout issue
require_once('./core/config/config.php');
require_once 'core/config/db.class.php';

// Basic session check
if (!isset($_SESSION['user_name'])) {
    exit('No session');
}

echo "Starting PDF test...<br>";

try {
    include_once (__DIR__."/core/pdf/FPDF_CellFit_Sch.php");
    echo "FPDF library loaded successfully<br>";

    $pdf = new FPDF_CellFit();
    echo "FPDF object created<br>";

    $pdf->AddPage('L','A4');
    echo "Page added<br>";

    $pdf->SetFont('Arial','B',12);
    echo "Font set<br>";

    $pdf->Cell(0,12,'TEST PDF GENERATION', 1,1, 'C');
    echo "Cell added<br>";

    // Test the problematic methods
    $pdf->SetWidths(array(50,50,50));
    echo "SetWidths called<br>";

    $pdf->SetAligns(array('C', 'C', 'C'));
    echo "SetAligns called<br>";

    $pdf->Row(array('Col1','Col2','Col3'));
    echo "Row added<br>";

    // Output PDF
    header('Content-Type: application/pdf');
    header('Content-Disposition: inline; filename="test.pdf"');

    $pdf->Output('', 'I');
    echo "PDF output completed<br>";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "<br>";
} catch (Error $e) {
    echo "Fatal Error: " . $e->getMessage() . "<br>";
}
?>