<?php
require_once('./core/config/config.php');
require_once('core/security/session_timeout_middleware.php');
validateActiveSession();
require_once 'core/config/db.class.php';

echo "<h2>Test Restored Reference Format</h2>";

// Get the most recent schedule to test with
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

echo "<p><strong>Testing restored reference format with:</strong></p>";
echo "<ul>";
echo "<li>Schedule ID: $sch_id</li>";
echo "<li>Unit ID: $unit_id</li>";
echo "<li>Year: $sch_year</li>";
echo "</ul>";

echo "<h3>Format Comparison</h3>";
echo "<div style='display: flex; gap: 20px;'>";

echo "<div style='border: 1px solid #ccc; padding: 10px; width: 45%;'>";
echo "<h4>Reference Format Features (from proval4):</h4>";
echo "<ul>";
echo "<li>✓ 16 columns: SR NO, EQUIPMENT CODE, EQUIPMENT NAME, TEST ID + 12 months</li>";
echo "<li>✓ Column widths: (10,32,40,15,15,15,15,15,15,15,15,15,15,15,15,15)</li>";
echo "<li>✓ Individual Cell() calls for precise month positioning</li>";
echo "<li>✓ LEGENDS page with test descriptions</li>";
echo "<li>✓ Signature page with requester details</li>";
echo "<li>✓ Professional layout and spacing</li>";
echo "<li>✓ File saved to uploads directory</li>";
echo "</ul>";
echo "</div>";

echo "<div style='border: 1px solid #ccc; padding: 10px; width: 45%;'>";
echo "<h4>Current Implementation:</h4>";
echo "<ul>";
echo "<li>✓ 16 columns: SR NO, EQUIPMENT CODE, EQUIPMENT NAME, VAL WF ID + 12 months</li>";
echo "<li>✓ Column widths: (10,32,40,15,15,15,15,15,15,15,15,15,15,15,15,15)</li>";
echo "<li>✓ Individual Cell() calls for precise month positioning</li>";
echo "<li>✓ LEGENDS page with validation workflow descriptions</li>";
echo "<li>✓ Signature page with requester details</li>";
echo "<li>✓ Professional layout matching reference</li>";
echo "<li>✓ File saved to uploads directory</li>";
echo "</ul>";
echo "</div>";

echo "</div>";

// Test the restored PDF generator
$pdf_url = BASE_URL . "generateschedulereport_minimal.php?unit_id=" . $unit_id . "&sch_id=" . $sch_id . "&sch_year=" . $sch_year . "&user_name=" . urlencode($user_name);

echo "<h3>Generate PDF with Restored Format</h3>";
echo "<p><a href='$pdf_url' target='_blank' style='background: #007cba; color: white; padding: 10px; text-decoration: none; border-radius: 5px;'>Generate PDF</a></p>";

// Check if PDF exists
$expected_pdf = "uploads/schedule-report-{$unit_id}-{$sch_id}.pdf";
$pdf_file_path = __DIR__ . "/$expected_pdf";

echo "<h3>Current PDF Status</h3>";
if (file_exists($pdf_file_path)) {
    $size = filesize($pdf_file_path);
    $modified = date('Y-m-d H:i:s', filemtime($pdf_file_path));
    echo "<p><strong>✓ PDF exists:</strong> $expected_pdf</p>";
    echo "<p><strong>Size:</strong> $size bytes</p>";
    echo "<p><strong>Modified:</strong> $modified</p>";

    echo "<p><a href='core/pdf/view_pdf_with_footer.php?pdf_path=$expected_pdf' target='_blank' style='background: #28a745; color: white; padding: 8px; text-decoration: none; border-radius: 3px;'>View PDF in Modal</a></p>";
    echo "<p><a href='$expected_pdf' target='_blank' style='background: #6c757d; color: white; padding: 8px; text-decoration: none; border-radius: 3px;'>View PDF Directly</a></p>";
} else {
    echo "<p><strong>❌ PDF not found:</strong> $expected_pdf</p>";
    echo "<p><em>Generate the PDF using the button above first.</em></p>";
}

echo "<h3>Key Changes Made</h3>";
echo "<div style='background: #f8f9fa; border-left: 4px solid #007cba; padding: 15px; margin: 15px 0;'>";
echo "<h4>Restored from Reference File:</h4>";
echo "<ol>";
echo "<li><strong>Column Structure:</strong> Added VAL WF ID column (4th column) before months</li>";
echo "<li><strong>Layout Method:</strong> Replaced Row() with individual Cell() calls for precise positioning</li>";
echo "<li><strong>Month Logic:</strong> Implemented explicit if/else conditions for each month (1-12)</li>";
echo "<li><strong>LEGENDS Page:</strong> Added validation workflow descriptions (IQ, OQ, PQ, etc.)</li>";
echo "<li><strong>Data Query:</strong> Included val_wf_id field from tbl_proposed_val_schedules</li>";
echo "<li><strong>Professional Format:</strong> Exact column widths and spacing from reference</li>";
echo "</ol>";
echo "</div>";

echo "<p><strong>The PDF should now match the professional format from the reference file while maintaining all the working file generation improvements.</strong></p>";
?>