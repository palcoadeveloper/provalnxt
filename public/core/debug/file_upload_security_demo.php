<?php
/**
 * File Upload Security Demonstration for ProVal HVAC Security
 * 
 * This file demonstrates the comprehensive file upload security system
 * and shows how to properly secure file uploads in the application.
 * 
 * @version 1.0
 * @author ProVal Security Team
 */

require_once '../security/secure_file_upload_utils.php';
require_once '../config/config.php';

session_start();

echo "<h1>ProVal HVAC - File Upload Security Demo</h1>\n";

// Get upload statistics
$stats = SecureFileUpload::getUploadStatistics();

echo "<div style='background-color: #f0f0f0; padding: 10px; border-left: 5px solid green; margin-bottom: 20px;'>\n";
echo "<h2>File Upload Security: <span style='color: green'>ACTIVE</span></h2>\n";
echo "<p>Comprehensive file upload security is <strong>ACTIVE</strong> for this system.</p>\n";
echo "</div>\n";

echo "<h2>Security Configuration</h2>\n";
echo "<table border='1' cellpadding='5' cellspacing='0'>\n";
echo "<tr><th>Setting</th><th>Value</th><th>Description</th></tr>\n";
echo "<tr><td><strong>Max File Size</strong></td><td>{$stats['max_file_size_mb']} MB</td><td>Maximum allowed file size per upload</td></tr>\n";
echo "<tr><td><strong>Allowed Extensions</strong></td><td>" . implode(', ', $stats['allowed_extensions']) . "</td><td>File extensions permitted for upload</td></tr>\n";
echo "<tr><td><strong>Max PDF Version</strong></td><td>{$stats['max_pdf_version']}</td><td>Maximum PDF version allowed</td></tr>\n";
echo "<tr><td><strong>MIME Type Validation</strong></td><td>Enabled</td><td>Server-side MIME type verification</td></tr>\n";
echo "<tr><td><strong>Content Scanning</strong></td><td>Enabled</td><td>File content analysis for malicious patterns</td></tr>\n";
echo "<tr><td><strong>Rate Limiting</strong></td><td>Enabled</td><td>Upload frequency restrictions per user/IP</td></tr>\n";
echo "</table>\n";

echo "<h2>Security Features</h2>\n";
echo "<ul>\n";
foreach ($stats['security_features'] as $feature) {
    $featureDisplay = ucwords(str_replace('_', ' ', $feature));
    echo "<li><strong>$featureDisplay</strong></li>\n";
}
echo "</ul>\n";

echo "<h2>Rate Limiting Configuration</h2>\n";
echo "<table border='1' cellpadding='5' cellspacing='0'>\n";
echo "<tr><th>Parameter</th><th>Per-IP Value</th><th>System-Wide Value</th></tr>\n";
echo "<tr><td>Maximum Uploads</td><td>" . RATE_LIMIT_FILE_UPLOAD_MAX . "</td><td>" . RATE_LIMIT_FILE_UPLOAD_SYSTEM_MAX . "</td></tr>\n";
echo "<tr><td>Time Window</td><td>" . formatDuration(RATE_LIMIT_FILE_UPLOAD_WINDOW) . "</td><td>" . formatDuration(RATE_LIMIT_FILE_UPLOAD_SYSTEM_WINDOW) . "</td></tr>\n";
echo "<tr><td>Lockout Duration</td><td>" . formatDuration(RATE_LIMIT_FILE_UPLOAD_LOCKOUT) . "</td><td>" . formatDuration(RATE_LIMIT_FILE_UPLOAD_SYSTEM_LOCKOUT) . "</td></tr>\n";
echo "</table>\n";

echo "<h2>Allowed MIME Types</h2>\n";
echo "<ul>\n";
foreach ($stats['allowed_mime_types'] as $mimeType) {
    echo "<li><code>$mimeType</code></li>\n";
}
echo "</ul>\n";

echo "<h2>File Validation Process</h2>\n";
echo "<ol>\n";
echo "<li><strong>Upload Validation:</strong> Check file size, name, and upload errors</li>\n";
echo "<li><strong>Rate Limiting:</strong> Verify user hasn't exceeded upload limits</li>\n";
echo "<li><strong>Extension Check:</strong> Validate file extension against whitelist</li>\n";
echo "<li><strong>MIME Type Verification:</strong> Server-side MIME type detection and validation</li>\n";
echo "<li><strong>Content Analysis:</strong> Scan file content for malicious patterns</li>\n";
echo "<li><strong>File Type Specific Validation:</strong>\n";
echo "   <ul>\n";
echo "   <li><strong>PDF:</strong> Version check, JavaScript detection, header validation</li>\n";
echo "   <li><strong>Images:</strong> Format verification, dimension limits, metadata scanning</li>\n";
echo "   </ul>\n";
echo "</li>\n";
echo "<li><strong>Secure Filename Generation:</strong> Create unique, safe filenames</li>\n";
echo "<li><strong>Directory Security:</strong> Ensure upload directory has proper protections</li>\n";
echo "<li><strong>Final Security Scan:</strong> Post-upload verification of file integrity</li>\n";
echo "<li><strong>Security Logging:</strong> Log all upload attempts and security events</li>\n";
echo "</ol>\n";

echo "<h2>Security Threats Prevented</h2>\n";
echo "<table border='1' cellpadding='5' cellspacing='0'>\n";
echo "<tr><th>Threat Type</th><th>Prevention Method</th><th>Status</th></tr>\n";
echo "<tr><td>Malicious File Upload</td><td>Extension whitelist + MIME validation</td><td>✅ Protected</td></tr>\n";
echo "<tr><td>File Size DoS</td><td>Size limits + rate limiting</td><td>✅ Protected</td></tr>\n";
echo "<tr><td>Path Traversal</td><td>Filename sanitization + secure paths</td><td>✅ Protected</td></tr>\n";
echo "<tr><td>Executable Upload</td><td>Signature detection + content scanning</td><td>✅ Protected</td></tr>\n";
echo "<tr><td>PDF JavaScript</td><td>PDF content analysis</td><td>✅ Protected</td></tr>\n";
echo "<tr><td>Image Exploits</td><td>Format validation + dimension limits</td><td>✅ Protected</td></tr>\n";
echo "<tr><td>Directory Traversal</td><td>Secure directory setup + .htaccess</td><td>✅ Protected</td></tr>\n";
echo "<tr><td>Rate Limiting Bypass</td><td>Dual-layer rate limiting</td><td>✅ Protected</td></tr>\n";
echo "</table>\n";

echo "<h2>Implementation Example</h2>\n";
echo "<h3>Secure File Upload Usage:</h3>\n";
echo "<pre><code>\n";
echo "// Include security utilities\n";
echo "require_once('../security/secure_file_upload_utils.php');\n";
echo "require_once('../security/rate_limiting_utils.php');\n";
echo "\n";
echo "// Check rate limiting\n";
echo "if (!RateLimiter::checkRateLimit('file_upload')) {\n";
echo "    http_response_code(429);\n";
echo "    echo json_encode(['error' => 'Rate limit exceeded']);\n";
echo "    exit();\n";
echo "}\n";
echo "\n";
echo "// Process upload securely\n";
echo "\\$result = SecureFileUpload::processUpload(\n";
echo "    \\$_FILES['upload_file'],\n";
echo "    '/path/to/upload/directory/',\n";
echo "    'document-prefix'\n";
echo ");\n";
echo "\n";
echo "if (\\$result['success']) {\n";
echo "    echo 'File uploaded: ' . \\$result['file_path'];\n";
echo "} else {\n";
echo "    echo 'Upload failed: ' . \\$result['error'];\n";
echo "}\n";
echo "</code></pre>\n";

echo "<h2>Directory Security</h2>\n";
echo "<p>Upload directories are automatically secured with:</p>\n";
echo "<ul>\n";
echo "<li><strong>.htaccess file:</strong> Prevents direct execution of uploaded files</li>\n";
echo "<li><strong>Index protection:</strong> Disables directory browsing</li>\n";
echo "<li><strong>CGI restrictions:</strong> Blocks server-side script execution</li>\n";
echo "<li><strong>File type blocking:</strong> Denies access to dangerous file types</li>\n";
echo "</ul>\n";

echo "<h3>Generated .htaccess Content:</h3>\n";
echo "<pre style='background-color: #f5f5f5; padding: 10px; border: 1px solid #ddd;'>\n";
echo "# Secure upload directory\n";
echo "Options -Indexes\n";
echo "Options -ExecCGI\n";
echo "AddHandler cgi-script .php .pl .py .jsp .asp .sh .cgi\n";
echo "&lt;FilesMatch \"\\.(php|phtml|php3|php4|php5|pl|py|jsp|asp|sh|cgi)$\"&gt;\n";
echo "    Deny from all\n";
echo "&lt;/FilesMatch&gt;\n";
echo "</pre>\n";

echo "<h2>Security Logging</h2>\n";
echo "<p>All file upload activities are logged with the following information:</p>\n";
echo "<ul>\n";
echo "<li>Upload timestamp and user identification</li>\n";
echo "<li>Original filename and sanitized filename</li>\n";
echo "<li>File size, type, and security check results</li>\n";
echo "<li>Security violations and attempted attacks</li>\n";
echo "<li>Rate limiting events and lockouts</li>\n";
echo "<li>File processing results and errors</li>\n";
echo "</ul>\n";

echo "<h2>Best Practices for Developers</h2>\n";
echo "<ol>\n";
echo "<li><strong>Always use SecureFileUpload::processUpload()</strong> instead of move_uploaded_file()</li>\n";
echo "<li><strong>Apply rate limiting</strong> before processing uploads</li>\n";
echo "<li><strong>Validate CSRF tokens</strong> for upload forms</li>\n";
echo "<li><strong>Use secure filenames</strong> generated by the system</li>\n";
echo "<li><strong>Store uploads outside web root</strong> when possible</li>\n";
echo "<li><strong>Implement proper error handling</strong> and logging</li>\n";
echo "<li><strong>Regularly review upload logs</strong> for security events</li>\n";
echo "</ol>\n";

if (!empty($_POST)) {
    echo "<h2>POST Data Analysis</h2>\n";
    echo "<p>Current POST data (if any) has been processed through XSS protection:</p>\n";
    echo "<pre>" . htmlspecialchars(print_r($_POST, true)) . "</pre>\n";
}

/**
 * Format duration in seconds to human readable format
 */
function formatDuration($seconds) {
    if ($seconds < 60) {
        return $seconds . ' seconds';
    } elseif ($seconds < 3600) {
        return ($seconds / 60) . ' minutes';
    } else {
        return ($seconds / 3600) . ' hours';
    }
}

echo "<hr>\n";
echo "<p><em>This demonstration shows the comprehensive file upload security system in ProVal HVAC. All file uploads are automatically secured and monitored.</em></p>\n";
?>