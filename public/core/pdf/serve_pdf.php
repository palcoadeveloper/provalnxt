<?php
// Load configuration first
require_once('../config/config.php');

// Session is already started by config.php via session_init.php

// Include XSS protection middleware
require_once('../security/xss_integration_middleware.php');

// Validate session timeout
require_once('../security/session_timeout_middleware.php');
validateActiveSession();

// Check for proper authentication
if (!isset($_SESSION['logged_in_user']) || !isset($_SESSION['user_name'])) {
    http_response_code(401);
    exit('Unauthorized');
}

// Get and validate file parameter
$file = isset($_GET['file']) ? $_GET['file'] : '';

if (empty($file)) {
    http_response_code(400);
    exit('File parameter required');
}

// Security: Prevent directory traversal attacks
$file = basename($file);
if (strpos($file, '..') !== false || strpos($file, '/') !== false || strpos($file, '\\') !== false) {
    http_response_code(400);
    exit('Invalid file parameter');
}

// Construct the file path
$uploadDir = realpath(__DIR__ . '/../../uploads/');
$filePath = $uploadDir . DIRECTORY_SEPARATOR . $file;

// Security: Ensure the file is within the uploads directory
if (!$uploadDir || strpos(realpath($filePath), $uploadDir) !== 0) {
    http_response_code(400);
    exit('Invalid file path');
}

// Check if file exists
if (!file_exists($filePath) || !is_file($filePath)) {
    http_response_code(404);
    exit('File not found');
}

// Get file info
$fileSize = filesize($filePath);
$fileExtension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

// Determine MIME type based on file extension
switch ($fileExtension) {
    case 'pdf':
        $mimeType = 'application/pdf';
        break;
    case 'jpg':
    case 'jpeg':
        $mimeType = 'image/jpeg';
        break;
    case 'png':
        $mimeType = 'image/png';
        break;
    case 'gif':
        $mimeType = 'image/gif';
        break;
    default:
        $mimeType = 'application/octet-stream';
        break;
}

// Clear any previous output
if (ob_get_level()) {
    ob_end_clean();
}

// Set security headers
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Content-Security-Policy: default-src \'self\'; object-src \'self\';');

// Set PDF headers
header('Content-Type: ' . $mimeType);
header('Content-Length: ' . $fileSize);
header('Accept-Ranges: bytes');

// For PDFs, inline display instead of download
if ($fileExtension === 'pdf') {
    header('Content-Disposition: inline; filename="' . addslashes($file) . '"');
} else {
    header('Content-Disposition: attachment; filename="' . addslashes($file) . '"');
}

// Handle byte range requests for better PDF viewing
$ranges = null;
if (isset($_SERVER['HTTP_RANGE']) && $fileExtension === 'pdf') {
    $ranges = $_SERVER['HTTP_RANGE'];
    
    if (preg_match('/bytes=(\d+)-(\d*)/', $ranges, $matches)) {
        $start = intval($matches[1]);
        $end = !empty($matches[2]) ? intval($matches[2]) : $fileSize - 1;
        
        if ($start < $fileSize && $end < $fileSize && $start <= $end) {
            http_response_code(206); // Partial Content
            header("Content-Range: bytes $start-$end/$fileSize");
            header('Content-Length: ' . ($end - $start + 1));
            
            // Output the requested range
            $fp = fopen($filePath, 'rb');
            fseek($fp, $start);
            $remaining = $end - $start + 1;
            
            while ($remaining > 0 && !feof($fp)) {
                $chunkSize = min(8192, $remaining);
                echo fread($fp, $chunkSize);
                $remaining -= $chunkSize;
                
                if (connection_aborted()) {
                    break;
                }
            }
            
            fclose($fp);
            exit;
        }
    }
}

// Output the full file
$fp = fopen($filePath, 'rb');
if ($fp) {
    while (!feof($fp) && !connection_aborted()) {
        echo fread($fp, 8192);
        flush();
    }
    fclose($fp);
} else {
    http_response_code(500);
    exit('Unable to read file');
}
?>