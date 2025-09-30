<?php
// Test PDF structure validation
require_once('./core/config/config.php');
require_once('core/security/session_timeout_middleware.php');
validateActiveSession();

// Test a sample PDF file
$test_pdf = 'uploads/schedule-report-7-114.pdf';
$full_path = __DIR__ . '/' . $test_pdf;

echo "<h2>PDF Structure Test</h2>";
echo "<p>Testing PDF: $test_pdf</p>";

if (file_exists($full_path)) {
    echo "<p style='color: green;'>✓ PDF file exists</p>";

    // Read first few bytes to check PDF header
    $handle = fopen($full_path, 'rb');
    $header = fread($handle, 10);
    fclose($handle);

    if (strpos($header, '%PDF') === 0) {
        echo "<p style='color: green;'>✓ PDF header is valid</p>";
    } else {
        echo "<p style='color: red;'>✗ PDF header is invalid: " . bin2hex($header) . "</p>";
    }

    // Check file size
    $filesize = filesize($full_path);
    echo "<p>File size: " . $filesize . " bytes</p>";

    if ($filesize > 100) {
        echo "<p style='color: green;'>✓ File size looks reasonable</p>";
    } else {
        echo "<p style='color: red;'>✗ File size too small</p>";
    }

    // Try to open with basic validation
    echo "<p><a href='$test_pdf' target='_blank'>Open PDF directly</a></p>";
    echo "<p><a href='core/pdf/view_pdf_with_footer.php?pdf_path=$test_pdf' target='_blank'>Open with footer viewer</a></p>";

} else {
    echo "<p style='color: red;'>✗ PDF file not found</p>";
}
?>