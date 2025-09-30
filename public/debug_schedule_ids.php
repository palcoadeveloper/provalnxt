<?php
require_once('./core/config/config.php');
require_once('core/security/session_timeout_middleware.php');
validateActiveSession();
require_once('core/config/db.class.php');

echo "<h2>Schedule ID Debug</h2>";

// Check latest schedule requests for unit 7
echo "<h3>Latest Schedule Requests for Unit 7:</h3>";
$schedules = DB::query("SELECT schedule_id, schedule_year, schedule_generation_datetime, schedule_request_status
                       FROM tbl_val_wf_schedule_requests
                       WHERE unit_id = 7
                       ORDER BY schedule_id DESC
                       LIMIT 10");

echo "<table border='1'>";
echo "<tr><th>Schedule ID</th><th>Year</th><th>Generated</th><th>Status</th><th>PDF Expected</th><th>PDF Exists</th></tr>";

foreach ($schedules as $schedule) {
    $pdf_filename = "schedule-report-7-{$schedule['schedule_id']}.pdf";
    $pdf_path = __DIR__ . "/uploads/" . $pdf_filename;
    $pdf_exists = file_exists($pdf_path) ? "✓" : "✗";

    echo "<tr>";
    echo "<td>{$schedule['schedule_id']}</td>";
    echo "<td>{$schedule['schedule_year']}</td>";
    echo "<td>{$schedule['schedule_generation_datetime']}</td>";
    echo "<td>{$schedule['schedule_request_status']}</td>";
    echo "<td>$pdf_filename</td>";
    echo "<td>$pdf_exists</td>";
    echo "</tr>";
}

echo "</table>";

// Check what PDF files actually exist
echo "<h3>Actual PDF Files in uploads/:</h3>";
$pdf_files = glob(__DIR__ . "/uploads/schedule-report-7-*.pdf");
sort($pdf_files);
foreach (array_slice($pdf_files, -10) as $file) {
    $filename = basename($file);
    $filesize = filesize($file);
    $modified = date("Y-m-d H:i:s", filemtime($file));
    echo "<p>$filename (Size: $filesize bytes, Modified: $modified)</p>";
}
?>