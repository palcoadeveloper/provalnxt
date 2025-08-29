<?php
/**
 * Test script to verify file upload configuration
 */

require_once '../security/secure_file_upload_utils.php';

echo "=== File Upload Configuration Test ===\n\n";

// Test PHP configuration
$phpConfig = SecureFileUpload::checkPHPUploadConfiguration();

echo "1. PHP Upload Configuration:\n";
echo "   - upload_max_filesize: " . ini_get('upload_max_filesize') . "\n";
echo "   - post_max_size: " . ini_get('post_max_size') . "\n";
echo "   - memory_limit: " . ini_get('memory_limit') . "\n";
echo "   - Configuration valid: " . ($phpConfig['valid'] ? 'YES' : 'NO') . "\n";

if (!empty($phpConfig['errors'])) {
    echo "   - Errors:\n";
    foreach ($phpConfig['errors'] as $error) {
        echo "     * " . $error . "\n";
    }
}

if (!empty($phpConfig['warnings'])) {
    echo "   - Warnings:\n";
    foreach ($phpConfig['warnings'] as $warning) {
        echo "     * " . $warning . "\n";
    }
}

echo "\n";

// Test upload statistics
$stats = SecureFileUpload::getUploadStatistics();
echo "2. Application Upload Settings:\n";
echo "   - Maximum file size: " . $stats['max_file_size_mb'] . " MB (" . $stats['max_file_size'] . " bytes)\n";
echo "   - Allowed extensions: " . implode(', ', $stats['allowed_extensions']) . "\n";
echo "   - Allowed MIME types: " . implode(', ', $stats['allowed_mime_types']) . "\n";
echo "   - Maximum PDF version: " . $stats['max_pdf_version'] . "\n";

echo "\n=== Test Complete ===\n";

?>