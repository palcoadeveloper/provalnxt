<?php
require_once('./core/config/config.php');
require_once('core/security/session_timeout_middleware.php');
validateActiveSession();
require_once 'core/config/db.class.php';

echo "<h2>Test Restored PDF Format</h2>";

// Get a recent schedule to test with
$recent_schedule = DB::queryFirstRow(
    "SELECT schedule_id, unit_id, schedule_year
     FROM tbl_val_wf_schedule_requests
     WHERE unit_id = 7
     ORDER BY schedule_id DESC
     LIMIT 1"
);

if (!$recent_schedule) {
    echo "<p style='color: red;'>No schedule found for unit 7</p>";
    exit;
}

$sch_id = $recent_schedule['schedule_id'];
$unit_id = $recent_schedule['unit_id'];
$sch_year = $recent_schedule['schedule_year'];
$user_name = $_SESSION['user_name'] ?? 'test_user';

echo "<p><strong>Testing restored format with:</strong></p>";
echo "<ul>";
echo "<li>Schedule ID: $sch_id</li>";
echo "<li>Unit ID: $unit_id</li>";
echo "<li>Year: $sch_year</li>";
echo "</ul>";

// Test the restored minimal PDF generator
$pdf_url = BASE_URL . "generateschedulereport_minimal.php?unit_id=" . $unit_id . "&sch_id=" . $sch_id . "&sch_year=" . $sch_year . "&user_name=" . urlencode($user_name);

echo "<p><strong>Generate PDF with Restored Format:</strong><br>";
echo "<a href='$pdf_url' target='_blank'>$pdf_url</a></p>";

// Check if PDF exists
$expected_pdf = "uploads/schedule-report-{$unit_id}-{$sch_id}.pdf";
$pdf_file_path = __DIR__ . "/$expected_pdf";

echo "<p><strong>Expected PDF:</strong> $expected_pdf</p>";

if (file_exists($pdf_file_path)) {
    $size = filesize($pdf_file_path);
    $modified = date('Y-m-d H:i:s', filemtime($pdf_file_path));
    echo "<p><strong>Current PDF:</strong> Exists (Size: $size bytes, Modified: $modified)</p>";

    echo "<p><a href='core/pdf/view_pdf_with_footer.php?pdf_path=$expected_pdf' target='_blank'>View Current PDF in Modal</a></p>";
    echo "<p><a href='$expected_pdf' target='_blank'>View Current PDF Directly</a></p>";
} else {
    echo "<p><strong>Current PDF:</strong> Not found</p>";
}

echo "<h3>Format Comparison</h3>";
echo "<p><strong>Original Format Features:</strong></p>";
echo "<ul>";
echo "<li>✓ Monthly calendar layout (12 columns for Jan-Dec)</li>";
echo "<li>✓ FPDF_CellFit custom class with SetWidths/Row methods</li>";
echo "<li>✓ Professional header with unit name and title</li>";
echo "<li>✓ Equipment code sorting with proper order</li>";
echo "<li>✓ Day numbers placed under appropriate month columns</li>";
echo "<li>✓ Signature page with requester details</li>";
echo "<li>✓ 12 rows per page with proper pagination</li>";
echo "</ul>";

echo "<p><em>Click the generation link above to create a PDF with the restored format, then view it using the modal viewer to verify all features are working correctly.</em></p>";
?>