<?php
// Test the path resolution logic
require_once('./core/config/config.php');
require_once('core/security/session_timeout_middleware.php');
validateActiveSession();

echo "<h2>Path Resolution Test</h2>";

// Test with an existing file
$test_path = 'uploads/schedule-report-7-95.pdf';
echo "<p><strong>Testing path:</strong> $test_path</p>";

// Simulate the logic from view_pdf_with_footer.php
$pdf_path = $test_path;
$pdf_path = str_replace(['../', '../', '..\\', '..\\\\'], '', $pdf_path);
$clean_path = ltrim($pdf_path, '/');

// Get the public directory path (simulate being in /public/core/pdf/)
$public_dir = dirname(dirname(__DIR__ . '/core/pdf/'));
echo "<p><strong>Public directory:</strong> $public_dir</p>";

if (strpos($clean_path, 'uploads/') === 0) {
    $full_path = $public_dir . '/' . $clean_path;
} else {
    $filename = basename($clean_path);
    $full_path = $public_dir . '/uploads/' . $filename;
}

echo "<p><strong>Original path:</strong> $pdf_path</p>";
echo "<p><strong>Clean path:</strong> $clean_path</p>";
echo "<p><strong>Resolved path:</strong> $full_path</p>";
echo "<p><strong>File exists:</strong> " . (file_exists($full_path) ? "✓ YES" : "✗ NO") . "</p>";

if (file_exists($full_path)) {
    $size = filesize($full_path);
    $modified = date('Y-m-d H:i:s', filemtime($full_path));
    echo "<p><strong>File size:</strong> $size bytes</p>";
    echo "<p><strong>Modified:</strong> $modified</p>";

    echo "<p><a href='core/pdf/view_pdf_with_footer.php?pdf_path=$test_path' target='_blank'>Test PDF Viewer</a></p>";
}
?>