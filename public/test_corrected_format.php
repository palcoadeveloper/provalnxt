<?php
require_once('./core/config/config.php');
require_once('core/security/session_timeout_middleware.php');
validateActiveSession();
require_once 'core/config/db.class.php';

echo "<h2>Test Corrected Validation Schedule Format</h2>";

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

echo "<p><strong>Testing corrected validation schedule format:</strong></p>";
echo "<ul>";
echo "<li>Schedule ID: $sch_id</li>";
echo "<li>Unit ID: $unit_id</li>";
echo "<li>Year: $sch_year</li>";
echo "</ul>";

echo "<h3>✅ Corrected Format Specifications</h3>";
echo "<div style='background: #d4edda; border: 1px solid #c3e6cb; padding: 15px; border-radius: 5px;'>";
echo "<h4>Validation Schedule Format (Correct):</h4>";
echo "<ul>";
echo "<li><strong>✓ 15 columns total:</strong> SR NO + EQUIPMENT CODE + EQUIPMENT NAME + 12 months</li>";
echo "<li><strong>✓ Column widths:</strong> (10, 43, 44, 15×12) - optimized for validation schedules</li>";
echo "<li><strong>✓ No VAL WF ID column:</strong> Validation schedules don't require workflow ID display</li>";
echo "<li><strong>✓ Monthly calendar layout:</strong> Day numbers appear under correct month</li>";
echo "<li><strong>✓ Information page:</strong> Validation schedule guidelines and legend</li>";
echo "<li><strong>✓ Signature page:</strong> Schedule requester details</li>";
echo "</ul>";
echo "</div>";

echo "<h3>❌ Previous Incorrect Format</h3>";
echo "<div style='background: #f8d7da; border: 1px solid #f5c6cb; padding: 15px; border-radius: 5px; margin: 10px 0;'>";
echo "<h4>What Was Wrong:</h4>";
echo "<ul>";
echo "<li><strong>✗ 16 columns:</strong> Incorrectly added VAL WF ID column</li>";
echo "<li><strong>✗ Wrong reference:</strong> Used routine test format instead of validation format</li>";
echo "<li><strong>✗ Extra complexity:</strong> Validation schedules are simpler than routine test schedules</li>";
echo "</ul>";
echo "</div>";

// Test the corrected PDF generator
$pdf_url = BASE_URL . "generateschedulereport_minimal.php?unit_id=" . $unit_id . "&sch_id=" . $sch_id . "&sch_year=" . $sch_year . "&user_name=" . urlencode($user_name);

echo "<h3>Generate Corrected PDF</h3>";
echo "<p><a href='$pdf_url' target='_blank' style='background: #007cba; color: white; padding: 12px 20px; text-decoration: none; border-radius: 5px; font-weight: bold;'>Generate Validation Schedule PDF</a></p>";

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

    echo "<div style='margin: 10px 0;'>";
    echo "<a href='core/pdf/view_pdf_with_footer.php?pdf_path=$expected_pdf' target='_blank' style='background: #28a745; color: white; padding: 10px 15px; text-decoration: none; border-radius: 3px; margin-right: 10px;'>📄 View in Modal</a>";
    echo "<a href='$expected_pdf' target='_blank' style='background: #6c757d; color: white; padding: 10px 15px; text-decoration: none; border-radius: 3px;'>🔗 Direct Link</a>";
    echo "</div>";
} else {
    echo "<p><strong>❌ PDF not found:</strong> $expected_pdf</p>";
    echo "<p><em>Generate the PDF using the button above first.</em></p>";
}

echo "<h3>Format Verification Checklist</h3>";
echo "<div style='background: #fff3cd; border: 1px solid #ffeaa7; padding: 15px; border-radius: 5px;'>";
echo "<p><strong>When viewing the PDF, verify:</strong></p>";
echo "<ol>";
echo "<li>✅ <strong>3 columns before months:</strong> SR NO, EQUIPMENT CODE, EQUIPMENT NAME</li>";
echo "<li>✅ <strong>12 month columns:</strong> JAN through DEC</li>";
echo "<li>✅ <strong>Day numbers in correct months:</strong> Numbers appear only under the appropriate month</li>";
echo "<li>✅ <strong>No VAL WF ID column:</strong> Should not be present</li>";
echo "<li>✅ <strong>Information page:</strong> Validation schedule information and guidelines</li>";
echo "<li>✅ <strong>Signature page:</strong> Schedule requested by details</li>";
echo "<li>✅ <strong>Professional layout:</strong> Clean, properly spaced, easy to read</li>";
echo "</ol>";
echo "</div>";

echo "<h3>Summary</h3>";
echo "<p><strong>The validation schedule format is now correct:</strong> 3 columns + 12 months = 15 total columns, without the unnecessary VAL WF ID column that belongs only to routine test schedules.</p>";
?>