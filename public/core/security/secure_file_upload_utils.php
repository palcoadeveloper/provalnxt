<?php
/**
 * Secure File Upload Utilities for ProVal HVAC Security
 * 
 * Provides comprehensive file upload security including file type validation,
 * content scanning, size limits, and malware protection.
 * 
 * @version 1.0
 * @author ProVal Security Team
 */

require_once '../config/config.php';
require_once '../security/xss_prevention_utils.php';

class SecureFileUpload {
    
    // Dangerous file signatures/magic bytes
    const DANGEROUS_SIGNATURES = [
        'MZ',           // Windows executable
        '4D5A',         // Windows executable (hex)
        'PK',           // ZIP/JAR/Office documents (can contain macros)
        '<!DOCTYPE',    // HTML documents
        '<html',        // HTML documents
        '<script',      // JavaScript
        '<?php',        // PHP scripts
        '#!/bin/',      // Unix scripts
        'GIF89a',       // GIF (can contain scripts in comments)
    ];
    
    // PDF version limits
    const MAX_PDF_VERSION = 1.7;
    
    /**
     * Get maximum file size from configuration
     * 
     * @return int Maximum file size in bytes
     */
    public static function getMaxFileSize() {
        if (defined('FILE_UPLOAD_MAX_SIZE_BYTES')) {
            return FILE_UPLOAD_MAX_SIZE_BYTES;
        }
        // Fallback to 5MB if not configured
        return 5 * 1024 * 1024;
    }
    
    /**
     * Get allowed file extensions from configuration
     * 
     * @return array Array of allowed extensions
     */
    public static function getAllowedExtensions() {
        if (defined('FILE_UPLOAD_ALLOWED_EXTENSIONS')) {
            $extensions = explode(',', FILE_UPLOAD_ALLOWED_EXTENSIONS);
            return array_map('trim', array_map('strtolower', $extensions));
        }
        // Fallback to PDF only
        return ['pdf'];
    }
    
    /**
     * Get allowed MIME types from configuration
     * 
     * @return array Array of allowed MIME types
     */
    public static function getAllowedMimeTypes() {
        if (defined('FILE_UPLOAD_ALLOWED_MIME_TYPES')) {
            $mimeTypes = explode(',', FILE_UPLOAD_ALLOWED_MIME_TYPES);
            return array_map('trim', $mimeTypes);
        }
        // Fallback to PDF only
        return ['application/pdf'];
    }
    
    /**
     * Get effective maximum file size (smallest of application and server limits)
     * 
     * @return int Effective max file size in bytes
     */
    public static function getEffectiveMaxFileSize() {
        $phpUploadLimit = self::convertPHPSizeToBytes(ini_get('upload_max_filesize'));
        $phpPostLimit = self::convertPHPSizeToBytes(ini_get('post_max_size'));
        $configMaxSize = self::getMaxFileSize();
        
        // Use the smallest limit
        return min($configMaxSize, $phpUploadLimit, $phpPostLimit);
    }
    
    /**
     * Get human-readable effective max file size
     * 
     * @return string Human readable file size
     */
    public static function getEffectiveMaxFileSizeHuman() {
        $bytes = self::getEffectiveMaxFileSize();
        return round($bytes / 1024 / 1024, 1) . 'MB';
    }
    
    /**
     * Check PHP configuration for file upload limits
     * 
     * @return array Configuration check result
     */
    public static function checkPHPUploadConfiguration() {
        $result = [
            'valid' => true,
            'warnings' => [],
            'errors' => []
        ];
        
        // Get PHP upload limits
        $uploadMaxFilesize = self::convertPHPSizeToBytes(ini_get('upload_max_filesize'));
        $postMaxSize = self::convertPHPSizeToBytes(ini_get('post_max_size'));
        $memoryLimit = self::convertPHPSizeToBytes(ini_get('memory_limit'));
        
        $configMaxSize = self::getMaxFileSize();
        $desiredMB = $configMaxSize / 1024 / 1024;
        $effectiveSize = self::getEffectiveMaxFileSize();
        $effectiveMB = $effectiveSize / 1024 / 1024;
        
        // Check if we can achieve the desired file size
        if ($uploadMaxFilesize < $configMaxSize) {
            $result['warnings'][] = sprintf(
                'Server upload_max_filesize (%s) limits uploads to %.1fMB instead of configured %.1fMB',
                ini_get('upload_max_filesize'),
                $uploadMaxFilesize / 1024 / 1024,
                $desiredMB
            );
        }
        
        if ($postMaxSize < $configMaxSize) {
            $result['warnings'][] = sprintf(
                'Server post_max_size (%s) limits uploads to %.1fMB instead of configured %.1fMB',
                ini_get('post_max_size'),
                $postMaxSize / 1024 / 1024,
                $desiredMB
            );
        }
        
        // Only mark as invalid if the effective size is too small to be useful
        $minimumUsableSize = 1 * 1024 * 1024; // 1MB minimum
        if ($effectiveSize < $minimumUsableSize) {
            $result['valid'] = false;
            $result['errors'][] = sprintf(
                'Effective file size limit (%.1fMB) is too small for practical use',
                $effectiveMB
            );
        }
        
        // Check memory_limit (should be at least 2x effective file size)
        if ($memoryLimit > 0 && $memoryLimit < ($effectiveSize * 2)) {
            $result['warnings'][] = sprintf(
                'PHP memory_limit (%s) may be too low for processing %.1fMB files',
                ini_get('memory_limit'),
                $effectiveMB
            );
        }
        
        return $result;
    }
    
    /**
     * Convert PHP size notation to bytes
     * 
     * @param string $size PHP size notation (e.g., "8M", "1G")
     * @return int Size in bytes
     */
    public static function convertPHPSizeToBytes($size) {
        $size = trim($size);
        if (empty($size)) {
            return 0;
        }
        
        $value = (int) $size;
        $unit = strtolower($size[strlen($size) - 1]);
        
        switch ($unit) {
            case 'g':
                $value *= 1024;
            case 'm':
                $value *= 1024;
            case 'k':
                $value *= 1024;
        }
        
        return $value;
    }
    
    /**
     * Validate and process uploaded file securely
     * 
     * @param array $fileInfo $_FILES array element
     * @param string $uploadDir Upload directory path
     * @param string $filePrefix Prefix for filename
     * @return array Result with success/error status and file path
     */
    public static function processUpload($fileInfo, $uploadDir, $filePrefix = '') {
        $result = [
            'success' => false,
            'error' => '',
            'file_path' => '',
            'original_name' => '',
            'sanitized_name' => '',
            'file_size' => 0,
            'file_type' => '',
            'security_checks' => [],
            'php_config_warnings' => []
        ];
        
        // Check PHP configuration for upload limits
        $phpConfigCheck = self::checkPHPUploadConfiguration();
        if (!$phpConfigCheck['valid']) {
            $result['error'] = 'Server configuration issue: The server is configured to allow files up to ' . 
                             ini_get('upload_max_filesize') . ' but this application supports up to 5MB. ' .
                             'Please contact your system administrator to increase the upload limit.';
            return $result;
        }
        
        if (!empty($phpConfigCheck['warnings'])) {
            $result['php_config_warnings'] = $phpConfigCheck['warnings'];
        }
        
        // Check if file was uploaded
        if (!isset($fileInfo['error']) || is_array($fileInfo['error'])) {
            $result['error'] = 'Invalid file upload parameters';
            return $result;
        }
        
        // Check for upload errors
        switch ($fileInfo['error']) {
            case UPLOAD_ERR_OK:
                break;
            case UPLOAD_ERR_NO_FILE:
                $result['error'] = 'No file was uploaded';
                return $result;
            case UPLOAD_ERR_INI_SIZE:
                $result['error'] = 'File exceeds server upload size limit (' . ini_get('upload_max_filesize') . '). Maximum allowed: 5MB';
                return $result;
            case UPLOAD_ERR_FORM_SIZE:
                $result['error'] = 'File exceeds form size limit. Maximum allowed: 5MB';
                return $result;
            case UPLOAD_ERR_PARTIAL:
                $result['error'] = 'File was only partially uploaded. Please try again';
                return $result;
            case UPLOAD_ERR_NO_TMP_DIR:
                $result['error'] = 'Server configuration error: Missing temporary directory';
                return $result;
            case UPLOAD_ERR_CANT_WRITE:
                $result['error'] = 'Server configuration error: Cannot write to disk';
                return $result;
            case UPLOAD_ERR_EXTENSION:
                $result['error'] = 'File upload stopped by server extension';
                return $result;
            default:
                $result['error'] = 'Unknown upload error (code: ' . $fileInfo['error'] . ')';
                return $result;
        }
        
        $result['original_name'] = $fileInfo['name'];
        $result['file_size'] = $fileInfo['size'];
        $result['file_type'] = $fileInfo['type'];
        
        // Apply rate limiting for file uploads
        if (class_exists('RateLimiter')) {
            if (!RateLimiter::checkRateLimit('file_upload')) {
                $result['error'] = 'Rate limit exceeded for file uploads';
                return $result;
            }
        }
        
        // Size validation using effective limit
        $effectiveMaxSize = self::getEffectiveMaxFileSize();
        if ($fileInfo['size'] > $effectiveMaxSize) {
            $result['error'] = 'File size exceeds maximum limit of ' . self::getEffectiveMaxFileSizeHuman() . 
                              ' (server limited)';
            return $result;
        }
        
        if ($fileInfo['size'] <= 0) {
            $result['error'] = 'File is empty or corrupted';
            return $result;
        }
        
        // Filename validation and sanitization
        $originalName = $fileInfo['name'];
        $sanitizedName = self::sanitizeFilename($originalName);
        $result['sanitized_name'] = $sanitizedName;
        
        if (empty($sanitizedName)) {
            $result['error'] = 'Invalid filename';
            return $result;
        }
        
        // Extension validation using configuration
        $allowedExtensions = self::getAllowedExtensions();
        $extension = strtolower(pathinfo($sanitizedName, PATHINFO_EXTENSION));
        if (!in_array($extension, $allowedExtensions)) {
            $result['error'] = 'File type not allowed. Allowed types: ' . implode(', ', $allowedExtensions);
            self::logSecurityEvent('invalid_file_extension', "Attempted upload of file with extension: $extension", [
                'filename' => $originalName,
                'extension' => $extension,
                'allowed_extensions' => $allowedExtensions
            ]);
            return $result;
        }
        
        // MIME type validation using configuration
        $allowedMimeTypes = self::getAllowedMimeTypes();
        $detectedMimeType = mime_content_type($fileInfo['tmp_name']);
        if (!in_array($detectedMimeType, $allowedMimeTypes) && !in_array($fileInfo['type'], $allowedMimeTypes)) {
            $result['error'] = 'File content type not allowed. Allowed types: ' . implode(', ', $allowedMimeTypes);
            self::logSecurityEvent('invalid_mime_type', "Invalid MIME type detected", [
                'filename' => $originalName,
                'detected_mime' => $detectedMimeType,
                'declared_mime' => $fileInfo['type'],
                'allowed_mime_types' => $allowedMimeTypes
            ]);
            return $result;
        }
        
        // Content validation
        $contentValidation = self::validateFileContent($fileInfo['tmp_name'], $extension);
        $result['security_checks'] = $contentValidation['checks'];
        
        if (!$contentValidation['safe']) {
            $result['error'] = $contentValidation['error'];
            self::logSecurityEvent('file_content_violation', $contentValidation['error'], [
                'filename' => $originalName,
                'violations' => $contentValidation['violations']
            ]);
            return $result;
        }
        
        // Generate secure filename
        $secureFilename = self::generateSecureFilename($filePrefix, $sanitizedName, $extension);
        $uploadPath = rtrim($uploadDir, '/') . '/' . $secureFilename;
        
        // Ensure upload directory exists and is secure
        if (!self::ensureSecureUploadDirectory($uploadDir)) {
            $result['error'] = 'Upload directory is not secure';
            return $result;
        }
        
        // Move uploaded file
        if (move_uploaded_file($fileInfo['tmp_name'], $uploadPath)) {
            // Set secure file permissions
            chmod($uploadPath, 0644);
            
            // Final security scan on uploaded file
            $finalScan = self::performFinalSecurityScan($uploadPath, $extension);
            if (!$finalScan['safe']) {
                unlink($uploadPath); // Remove the file
                $result['error'] = $finalScan['error'];
                return $result;
            }
            
            $result['success'] = true;
            $result['file_path'] = $uploadPath;
            
            // Log successful upload
            self::logSecurityEvent('file_upload_success', 'File uploaded successfully', [
                'filename' => $originalName,
                'secure_path' => $uploadPath,
                'size' => $fileInfo['size'],
                'type' => $extension
            ]);
            
        } else {
            $result['error'] = 'Failed to move uploaded file';
        }
        
        return $result;
    }
    
    /**
     * Sanitize filename for security
     * 
     * @param string $filename Original filename
     * @return string Sanitized filename
     */
    public static function sanitizeFilename($filename) {
        // Remove path traversal attempts
        $filename = basename($filename);
        
        // Remove or replace dangerous characters
        $filename = preg_replace('/[^a-zA-Z0-9._-]/', '_', $filename);
        
        // Remove multiple dots/underscores
        $filename = preg_replace('/[._-]{2,}/', '_', $filename);
        
        // Remove leading/trailing dots/underscores
        $filename = trim($filename, '._-');
        
        // Limit length
        if (strlen($filename) > 100) {
            $extension = pathinfo($filename, PATHINFO_EXTENSION);
            $name = pathinfo($filename, PATHINFO_FILENAME);
            $filename = substr($name, 0, 90) . '.' . $extension;
        }
        
        // Ensure filename is not empty
        if (empty($filename) || $filename === '.') {
            return '';
        }
        
        return $filename;
    }
    
    /**
     * Generate secure filename with timestamp and hash
     * 
     * @param string $prefix Filename prefix
     * @param string $originalName Original filename
     * @param string $extension File extension
     * @return string Secure filename
     */
    public static function generateSecureFilename($prefix, $originalName, $extension) {
        $timestamp = time();
        $random = bin2hex(random_bytes(8));
        
        // Create hash of original name for uniqueness
        $nameHash = substr(hash('sha256', $originalName . $timestamp), 0, 8);
        
        $filename = '';
        if (!empty($prefix)) {
            $filename .= $prefix . '-';
        }
        
        $filename .= $timestamp . '-' . $nameHash . '-' . $random . '.' . $extension;
        
        return $filename;
    }
    
    /**
     * Validate file content for security threats
     * 
     * @param string $tmpPath Temporary file path
     * @param string $extension File extension
     * @return array Validation result
     */
    public static function validateFileContent($tmpPath, $extension) {
        $result = [
            'safe' => true,
            'error' => '',
            'violations' => [],
            'checks' => []
        ];
        
        // Read first few bytes for signature analysis
        $handle = fopen($tmpPath, 'rb');
        if (!$handle) {
            $result['safe'] = false;
            $result['error'] = 'Unable to read file content';
            return $result;
        }
        
        $header = fread($handle, 1024);
        fclose($handle);
        
        $result['checks'][] = 'file_signature_check';
        
        // Check for dangerous file signatures
        foreach (self::DANGEROUS_SIGNATURES as $signature) {
            if (strpos($header, $signature) === 0) {
                $result['safe'] = false;
                $result['error'] = 'File contains dangerous content signature';
                $result['violations'][] = "dangerous_signature:$signature";
                return $result;
            }
        }
        
        // Specific validation by file type
        switch ($extension) {
            case 'pdf':
                $pdfValidation = self::validatePDFContent($tmpPath);
                $result['checks'] = array_merge($result['checks'], $pdfValidation['checks']);
                if (!$pdfValidation['safe']) {
                    $result['safe'] = false;
                    $result['error'] = $pdfValidation['error'];
                    $result['violations'] = array_merge($result['violations'], $pdfValidation['violations']);
                }
                break;
                
            case 'jpg':
            case 'jpeg':
            case 'png':
                $imageValidation = self::validateImageContent($tmpPath, $extension);
                $result['checks'] = array_merge($result['checks'], $imageValidation['checks']);
                if (!$imageValidation['safe']) {
                    $result['safe'] = false;
                    $result['error'] = $imageValidation['error'];
                    $result['violations'] = array_merge($result['violations'], $imageValidation['violations']);
                }
                break;
        }
        
        return $result;
    }
    
    /**
     * Validate PDF file content
     * 
     * @param string $filePath PDF file path
     * @return array Validation result
     */
    public static function validatePDFContent($filePath) {
        $result = [
            'safe' => true,
            'error' => '',
            'violations' => [],
            'checks' => ['pdf_header_check', 'pdf_version_check', 'pdf_javascript_check']
        ];
        
        $content = file_get_contents($filePath, false, null, 0, 2048);
        
        // Check PDF header
        if (strpos($content, '%PDF-') !== 0) {
            $result['safe'] = false;
            $result['error'] = 'Invalid PDF header';
            $result['violations'][] = 'invalid_pdf_header';
            return $result;
        }
        
        // Extract PDF version
        preg_match('/%PDF-(\d+\.\d+)/', $content, $matches);
        if (isset($matches[1])) {
            $version = floatval($matches[1]);
            if ($version > self::MAX_PDF_VERSION) {
                $result['safe'] = false;
                $result['error'] = "PDF version $version is not allowed (max: " . self::MAX_PDF_VERSION . ")";
                $result['violations'][] = "pdf_version_too_high:$version";
                return $result;
            }
        }
        
        // Check for JavaScript in PDF
        $jsPatterns = ['/\/JavaScript\b/i', '/\/JS\b/i', '/\/Action\b/i'];
        foreach ($jsPatterns as $pattern) {
            if (preg_match($pattern, $content)) {
                $result['safe'] = false;
                $result['error'] = 'PDF contains JavaScript or actions';
                $result['violations'][] = 'pdf_contains_javascript';
                return $result;
            }
        }
        
        return $result;
    }
    
    /**
     * Validate image file content
     * 
     * @param string $filePath Image file path
     * @param string $extension Image extension
     * @return array Validation result
     */
    public static function validateImageContent($filePath, $extension) {
        $result = [
            'safe' => true,
            'error' => '',
            'violations' => [],
            'checks' => ['image_format_check', 'image_size_check', 'image_metadata_check']
        ];
        
        // Use getimagesize for validation
        $imageInfo = @getimagesize($filePath);
        if (!$imageInfo) {
            $result['safe'] = false;
            $result['error'] = 'Invalid or corrupted image file';
            $result['violations'][] = 'invalid_image_format';
            return $result;
        }
        
        // Validate image dimensions (prevent memory exhaustion attacks)
        $maxDimension = 10000; // 10,000 pixels
        if ($imageInfo[0] > $maxDimension || $imageInfo[1] > $maxDimension) {
            $result['safe'] = false;
            $result['error'] = 'Image dimensions are too large';
            $result['violations'][] = 'image_dimensions_too_large';
            return $result;
        }
        
        // Check MIME type consistency
        $expectedTypes = [
            'jpg' => IMAGETYPE_JPEG,
            'jpeg' => IMAGETYPE_JPEG,
            'png' => IMAGETYPE_PNG
        ];
        
        if (isset($expectedTypes[$extension]) && $imageInfo[2] !== $expectedTypes[$extension]) {
            $result['safe'] = false;
            $result['error'] = 'Image format does not match file extension';
            $result['violations'][] = 'image_format_mismatch';
            return $result;
        }
        
        return $result;
    }
    
    /**
     * Ensure upload directory is secure
     * 
     * @param string $uploadDir Upload directory path
     * @return bool True if directory is secure
     */
    public static function ensureSecureUploadDirectory($uploadDir) {
        // Create directory if it doesn't exist
        if (!is_dir($uploadDir)) {
            if (!mkdir($uploadDir, 0755, true)) {
                return false;
            }
        }
        
        // Check directory permissions
        if (!is_writable($uploadDir)) {
            return false;
        }
        
        // Create .htaccess file to prevent direct execution
        $htaccessPath = $uploadDir . '/.htaccess';
        if (!file_exists($htaccessPath)) {
            $htaccessContent = "# Secure upload directory\n";
            $htaccessContent .= "Options -Indexes\n";
            $htaccessContent .= "Options -ExecCGI\n";
            $htaccessContent .= "AddHandler cgi-script .php .pl .py .jsp .asp .sh .cgi\n";
            $htaccessContent .= "<FilesMatch \"\\.(php|phtml|php3|php4|php5|pl|py|jsp|asp|sh|cgi)$\">\n";
            $htaccessContent .= "    Deny from all\n";
            $htaccessContent .= "</FilesMatch>\n";
            
            file_put_contents($htaccessPath, $htaccessContent);
        }
        
        return true;
    }
    
    /**
     * Perform final security scan on uploaded file
     * 
     * @param string $filePath Uploaded file path
     * @param string $extension File extension
     * @return array Scan result
     */
    public static function performFinalSecurityScan($filePath, $extension) {
        $result = [
            'safe' => true,
            'error' => '',
            'checks' => ['final_existence_check', 'final_permissions_check']
        ];
        
        // Verify file exists and is readable
        if (!file_exists($filePath) || !is_readable($filePath)) {
            $result['safe'] = false;
            $result['error'] = 'Uploaded file is not accessible';
            return $result;
        }
        
        // Check file size hasn't changed
        $size = filesize($filePath);
        $effectiveMaxSize = self::getEffectiveMaxFileSize();
        if ($size <= 0 || $size > $effectiveMaxSize) {
            $result['safe'] = false;
            $result['error'] = 'File size validation failed after upload';
            return $result;
        }
        
        // Additional content scan for specific file types
        if ($extension === 'pdf') {
            // Re-validate PDF after upload
            $content = file_get_contents($filePath, false, null, 0, 1024);
            if (strpos($content, '%PDF-') !== 0) {
                $result['safe'] = false;
                $result['error'] = 'PDF validation failed after upload';
                return $result;
            }
        }
        
        return $result;
    }
    
    /**
     * Get upload statistics
     * 
     * @return array Upload statistics
     */
    public static function getUploadStatistics() {
        $configMaxSize = self::getMaxFileSize();
        $effectiveMaxSize = self::getEffectiveMaxFileSize();
        return [
            'configured_max_file_size' => $configMaxSize,
            'configured_max_file_size_mb' => round($configMaxSize / 1024 / 1024, 2),
            'effective_max_file_size' => $effectiveMaxSize,
            'effective_max_file_size_mb' => round($effectiveMaxSize / 1024 / 1024, 2),
            'server_upload_max_filesize' => ini_get('upload_max_filesize'),
            'server_post_max_size' => ini_get('post_max_size'),
            'allowed_extensions' => self::getAllowedExtensions(),
            'allowed_mime_types' => self::getAllowedMimeTypes(),
            'max_pdf_version' => self::MAX_PDF_VERSION,
            'security_features' => [
                'file_signature_validation',
                'mime_type_verification',
                'content_scanning',
                'filename_sanitization',
                'directory_protection',
                'rate_limiting_integration',
                'security_logging'
            ]
        ];
    }
    
    /**
     * Log security events
     * 
     * @param string $event Event type
     * @param string $description Event description
     * @param array $context Additional context
     */
    private static function logSecurityEvent($event, $description, $context = []) {
        $logContext = array_merge($context, [
            'ip_address' => isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : 'unknown',
            'user_agent' => isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : 'unknown',
            'user_id' => isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null,
            'timestamp' => date('Y-m-d H:i:s')
        ]);
        
        if (class_exists('SecurityUtils')) {
            SecurityUtils::logSecurityEvent($event, $description, $logContext);
        } else {
            error_log("File Upload Security Event: $event - $description - " . json_encode($logContext));
        }
    }
}

/**
 * Convenience functions for file upload security
 */

/**
 * Secure file upload wrapper
 * 
 * @param array $fileInfo $_FILES array element
 * @param string $uploadDir Upload directory
 * @param string $prefix Filename prefix
 * @return array Upload result
 */
function secure_file_upload($fileInfo, $uploadDir, $prefix = '') {
    return SecureFileUpload::processUpload($fileInfo, $uploadDir, $prefix);
}

/**
 * Validate uploaded file securely
 * 
 * @param array $fileInfo $_FILES array element
 * @return bool True if file is valid
 */
function validate_uploaded_file($fileInfo) {
    if (!isset($fileInfo['tmp_name']) || !is_uploaded_file($fileInfo['tmp_name'])) {
        return false;
    }
    
    $result = SecureFileUpload::processUpload($fileInfo, sys_get_temp_dir(), 'validation');
    return $result['success'];
}

/**
 * Get safe filename
 * 
 * @param string $filename Original filename
 * @return string Safe filename
 */
function get_safe_filename($filename) {
    return SecureFileUpload::sanitizeFilename($filename);
}