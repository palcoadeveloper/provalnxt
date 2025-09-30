<?php
require_once('./core/config/config.php');
require_once('core/security/session_timeout_middleware.php');
validateActiveSession();
require_once 'core/config/db.class.php';

echo "<h2>PDF Generation Test</h2>";

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

echo "<p><strong>Testing with:</strong></p>";
echo "<ul>";
echo "<li>Schedule ID: $sch_id</li>";
echo "<li>Unit ID: $unit_id</li>";
echo "<li>Year: $sch_year</li>";
echo "<li>User: $user_name</li>";
echo "</ul>";

// Test the minimal PDF generator URL
$pdf_url = BASE_URL . "generateschedulereport_minimal.php?unit_id=" . $unit_id . "&sch_id=" . $sch_id . "&sch_year=" . $sch_year . "&user_name=" . urlencode($user_name);

echo "<p><strong>PDF Generation URL:</strong><br>";
echo "<a href='$pdf_url' target='_blank'>$pdf_url</a></p>";

// Check if PDF already exists
$expected_pdf = "uploads/schedule-report-{$unit_id}-{$sch_id}.pdf";
$pdf_file_path = __DIR__ . "/$expected_pdf";

echo "<p><strong>Expected PDF:</strong> $expected_pdf</p>";
echo "<p><strong>PDF exists:</strong> " . (file_exists($pdf_file_path) ? "✓ YES" : "✗ NO") . "</p>";

if (file_exists($pdf_file_path)) {
    $size = filesize($pdf_file_path);
    $modified = date('Y-m-d H:i:s', filemtime($pdf_file_path));
    echo "<p><strong>File size:</strong> $size bytes</p>";
    echo "<p><strong>Modified:</strong> $modified</p>";

    echo "<p><a href='core/pdf/view_pdf_with_footer.php?pdf_path=$expected_pdf' target='_blank'>Test PDF Viewer</a></p>";
}

// Test cURL generation
echo "<h3>Testing PDF Generation via cURL:</h3>";

$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => $pdf_url,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 30,
    CURLOPT_CONNECTTIMEOUT => 5,
    CURLOPT_FOLLOWLOCATION => false,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_SSL_VERIFYHOST => 0,
    CURLOPT_USERAGENT => 'ProVal-Internal/1.0'
]);

$output = curl_exec($ch);
$curl_error = curl_error($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "<p><strong>HTTP Code:</strong> $http_code</p>";

if ($curl_error) {
    echo "<p style='color: red;'><strong>cURL Error:</strong> $curl_error</p>";
} else {
    echo "<p style='color: green;'><strong>Response:</strong></p>";
    echo "<pre>" . htmlspecialchars(substr($output, 0, 500)) . "</pre>";

    // Check if PDF was created
    if (file_exists($pdf_file_path)) {
        $new_size = filesize($pdf_file_path);
        $new_modified = date('Y-m-d H:i:s', filemtime($pdf_file_path));
        echo "<p style='color: green;'><strong>PDF file updated!</strong></p>";
        echo "<p><strong>New size:</strong> $new_size bytes</p>";
        echo "<p><strong>New modified:</strong> $new_modified</p>";
    }
}
?>