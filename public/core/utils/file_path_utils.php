<?php
/**
 * ProVal HVAC - File Path Utilities
 * 
 * Security Level: High
 * Authentication Required: Yes
 * Input Sources: Database, File System
 * 
 * Utilities for reading and constructing file paths to files in the public/uploads/ folder
 * Based on analysis of existing file access patterns in the ProVal HVAC system
 */

// Security template - mandatory for all PHP files
require_once(__DIR__ . '/../config/config.php');
require_once(__DIR__ . '/../security/session_timeout_middleware.php');
validateActiveSession();
require_once(__DIR__ . '/../config/db.class.php');

// Timezone setting for audit logs
date_default_timezone_set("Asia/Kolkata");

/**
 * File Path Utilities Class
 * Handles secure file path construction and validation for uploads/ folder
 */
class FilePathUtils {
    
    // Base uploads directory relative to public/
    const UPLOADS_BASE_DIR = 'uploads/';
    
    // Subdirectories in uploads/
    const SUBDIR_TEMPLATES = 'templates/';
    const SUBDIR_CERTIFICATES = 'certificates/';
    
    // File path patterns used in database storage
    const DB_PATH_PREFIX = '../../uploads/'; // 6 characters before uploads/
    const CORE_VALIDATION_PREFIX = '../../uploads/'; // From core/validation/
    const CORE_DATA_PREFIX = '../../../uploads/'; // From core/data/save/
    
    /**
     * Get absolute path to uploads directory
     * @return string Absolute path to uploads directory
     */
    public static function getUploadsDir() {
        return realpath(__DIR__ . '/../../uploads/');
    }
    
    /**
     * Get relative path to uploads directory from public root
     * @return string Relative path (uploads/)
     */
    public static function getUploadsRelativePath() {
        return self::UPLOADS_BASE_DIR;
    }
    
    /**
     * Convert database-stored path to web-accessible URL
     * Based on pattern found in getuploadedfiles.php: BASE_URL.substr($path, 6)
     * 
     * @param string $dbPath Database stored path (e.g., "../../uploads/filename.pdf")
     * @return string Web URL (e.g., "uploads/filename.pdf")
     */
    public static function dbPathToWebUrl($dbPath) {
        if (empty($dbPath)) {
            return '';
        }
        
        // Remove the first 6 characters (../../) to get uploads/filename.pdf
        if (strlen($dbPath) > 6) {
            return substr($dbPath, 6);
        }
        
        // Fallback: if path doesn't match expected pattern, use basename
        return self::UPLOADS_BASE_DIR . basename($dbPath);
    }
    
    /**
     * Convert database path to absolute file system path
     * 
     * @param string $dbPath Database stored path
     * @return string Absolute file system path
     */
    public static function dbPathToAbsolutePath($dbPath) {
        if (empty($dbPath)) {
            return '';
        }
        
        $uploadsDir = self::getUploadsDir();
        if (!$uploadsDir) {
            return '';
        }
        
        // Extract filename from database path
        $filename = basename($dbPath);
        
        return $uploadsDir . DIRECTORY_SEPARATOR . $filename;
    }
    
    /**
     * Create database storage path from filename
     * Uses the standard pattern: ../../uploads/filename
     * 
     * @param string $filename Just the filename
     * @return string Database storage path
     */
    public static function createDbPath($filename) {
        return self::DB_PATH_PREFIX . $filename;
    }
    
    /**
     * Create full URL with BASE_URL
     * 
     * @param string $relativePath Relative path (e.g., "uploads/file.pdf")
     * @return string Full URL
     */
    public static function createFullUrl($relativePath) {
        return BASE_URL . $relativePath;
    }
    
    /**
     * Validate file exists in uploads directory
     * 
     * @param string $filename Filename to check
     * @param string $subdirectory Optional subdirectory (e.g., 'templates/')
     * @return bool True if file exists
     */
    public static function fileExists($filename, $subdirectory = '') {
        $uploadsDir = self::getUploadsDir();
        if (!$uploadsDir) {
            return false;
        }
        
        $filePath = $uploadsDir . DIRECTORY_SEPARATOR . $subdirectory . $filename;
        return file_exists($filePath) && is_file($filePath);
    }
    
    /**
     * Get secure file path for serving
     * Validates path is within uploads directory
     * 
     * @param string $filename Filename
     * @param string $subdirectory Optional subdirectory
     * @return string|false Absolute path if valid, false if invalid
     */
    public static function getSecureFilePath($filename, $subdirectory = '') {
        // Sanitize filename - remove directory traversal attempts
        $filename = basename($filename);
        $subdirectory = str_replace(['../', '..\\', './'], '', $subdirectory);
        
        $uploadsDir = self::getUploadsDir();
        if (!$uploadsDir) {
            return false;
        }
        
        $filePath = $uploadsDir . DIRECTORY_SEPARATOR . $subdirectory . $filename;
        
        // Ensure the resolved path is within uploads directory
        $realPath = realpath($filePath);
        if ($realPath === false || strpos($realPath, $uploadsDir) !== 0) {
            return false;
        }
        
        return $realPath;
    }
    
    /**
     * List files in uploads directory or subdirectory
     * 
     * @param string $subdirectory Optional subdirectory to list
     * @param array $allowedExtensions Optional array of allowed extensions
     * @return array Array of filenames
     */
    public static function listFiles($subdirectory = '', $allowedExtensions = []) {
        $uploadsDir = self::getUploadsDir();
        if (!$uploadsDir) {
            return [];
        }
        
        $targetDir = $uploadsDir . DIRECTORY_SEPARATOR . $subdirectory;
        if (!is_dir($targetDir)) {
            return [];
        }
        
        $files = [];
        $iterator = new DirectoryIterator($targetDir);
        
        foreach ($iterator as $file) {
            if ($file->isDot() || !$file->isFile()) {
                continue;
            }
            
            $filename = $file->getFilename();
            
            // Filter by allowed extensions if specified
            if (!empty($allowedExtensions)) {
                $extension = strtolower($file->getExtension());
                if (!in_array($extension, $allowedExtensions)) {
                    continue;
                }
            }
            
            $files[] = $filename;
        }
        
        sort($files);
        return $files;
    }
    
    /**
     * Get files from tbl_uploads table
     * 
     * @param string|null $testWfId Optional filter by test workflow ID
     * @param string|null $uploadAction Optional filter by upload action (Approved/Rejected)
     * @return array Array of upload records
     */
    public static function getUploadedFiles($testWfId = null, $uploadAction = null) {
        $query = "SELECT upload_id, test_wf_id, upload_path_raw_data, 
                         upload_path_master_certificate, upload_path_test_certificate, 
                         upload_path_other_doc, uploaded_datetime, upload_remarks, 
                         upload_type, upload_action, val_wf_id
                  FROM tbl_uploads t1";
        
        $conditions = [];
        $params = [];
        
        if ($testWfId !== null) {
            $conditions[] = "test_wf_id = %s";
            $params[] = $testWfId;
        }
        
        if ($uploadAction !== null) {
            $conditions[] = "upload_action = %s";
            $params[] = $uploadAction;
        }
        
        if (!empty($conditions)) {
            $query .= " WHERE " . implode(" AND ", $conditions);
        }
        
        $query .= " ORDER BY uploaded_datetime DESC";
        
        return DB::query($query, ...$params);
    }
    
    /**
     * Get file info with web URLs for a specific upload record
     * 
     * @param array $uploadRecord Upload record from database
     * @return array Processed file info with web URLs
     */
    public static function processUploadRecord($uploadRecord) {
        $processed = $uploadRecord;
        
        // Convert database paths to web URLs
        $pathFields = [
            'upload_path_raw_data',
            'upload_path_master_certificate', 
            'upload_path_test_certificate',
            'upload_path_other_doc'
        ];
        
        foreach ($pathFields as $field) {
            if (!empty($uploadRecord[$field])) {
                $processed[$field . '_web_url'] = self::dbPathToWebUrl($uploadRecord[$field]);
                $processed[$field . '_full_url'] = self::createFullUrl(
                    self::dbPathToWebUrl($uploadRecord[$field])
                );
                $processed[$field . '_filename'] = basename($uploadRecord[$field]);
            }
        }
        
        return $processed;
    }
    
    /**
     * Generate secure download link HTML
     * Based on patterns found in getuploadedfiles.php
     * 
     * @param string $filePath Database file path
     * @param string $fileType File type (raw_data, master_certificate, test_certificate, other_doc)
     * @param int $uploadId Upload ID
     * @param string $testWfId Test workflow ID
     * @param string $linkText Link text (default: "Download")
     * @return string HTML link or dash if no file
     */
    public static function generateDownloadLink($filePath, $fileType, $uploadId, $testWfId, $linkText = "Download") {
        if (empty($filePath)) {
            return "-";
        }
        
        $webUrl = self::dbPathToWebUrl($filePath);
        $fullUrl = self::createFullUrl($webUrl);
        
        return sprintf(
            '<a href="%s" data-file-type="%s" data-upload-id="%d" data-test-wf-id="%s" ' .
            'class="file-download-link" data-toggle="modal" data-target="#imagepdfviewerModal">%s</a>',
            htmlspecialchars($webUrl, ENT_QUOTES, 'UTF-8'),
            htmlspecialchars($fileType, ENT_QUOTES, 'UTF-8'),
            intval($uploadId),
            htmlspecialchars($testWfId, ENT_QUOTES, 'UTF-8'),
            htmlspecialchars($linkText, ENT_QUOTES, 'UTF-8')
        );
    }
    
    /**
     * Create template file path
     * 
     * @param string $filename Template filename
     * @return string Template file path for database storage
     */
    public static function createTemplatePath($filename) {
        return self::DB_PATH_PREFIX . self::SUBDIR_TEMPLATES . $filename;
    }
    
    /**
     * Create certificate file path
     * 
     * @param string $filename Certificate filename  
     * @return string Certificate file path for database storage
     */
    public static function createCertificatePath($filename) {
        return self::DB_PATH_PREFIX . self::SUBDIR_CERTIFICATES . $filename;
    }
    
    /**
     * Get report file paths (direct uploads/ access pattern)
     * These files are created directly in uploads/ by report generation scripts
     * 
     * @param string $reportType Type of report (schedule, protocol, plannedvsactual, rt-schedule)
     * @param int $unitId Unit ID
     * @param string $identifier Report identifier (schedule ID, workflow ID, etc.)
     * @return array Report file info
     */
    public static function getReportFilePath($reportType, $unitId, $identifier) {
        $filename = '';
        
        switch ($reportType) {
            case 'schedule':
                $filename = "schedule-report-{$unitId}-{$identifier}.pdf";
                break;
            case 'rt-schedule': 
                $filename = "rt-schedule-report-{$unitId}-{$identifier}.pdf";
                break;
            case 'protocol':
                $filename = "protocol-report-{$identifier}.pdf";
                break;
            case 'plannedvsactual':
                $filename = "plannedvsactual-report-{$unitId}-{$identifier}.pdf";
                break;
            case 'plannedvsactualrt':
                $filename = "plannedvsactualrt-report-{$unitId}-{$identifier}.pdf";
                break;
            default:
                return ['error' => 'Invalid report type'];
        }
        
        return [
            'filename' => $filename,
            'relative_path' => self::UPLOADS_BASE_DIR . $filename,
            'full_url' => self::createFullUrl(self::UPLOADS_BASE_DIR . $filename),
            'absolute_path' => self::getUploadsDir() . DIRECTORY_SEPARATOR . $filename,
            'pdf_viewer_url' => 'core/pdf/view_pdf_with_footer.php?pdf_path=' . urlencode(self::UPLOADS_BASE_DIR . $filename)
        ];
    }
    
    /**
     * Clean up old files (utility function)
     * 
     * @param int $daysOld Files older than this many days
     * @param array $filePatterns Optional patterns to match (e.g., ['*.tmp', '*-temp-*'])
     * @return array Cleanup results
     */
    public static function cleanupOldFiles($daysOld = 30, $filePatterns = []) {
        $uploadsDir = self::getUploadsDir();
        if (!$uploadsDir) {
            return ['error' => 'Uploads directory not found'];
        }
        
        $cutoffTime = time() - ($daysOld * 24 * 60 * 60);
        $deletedFiles = [];
        $errors = [];
        
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($uploadsDir, RecursiveDirectoryIterator::SKIP_DOTS)
        );
        
        foreach ($iterator as $file) {
            if (!$file->isFile()) {
                continue;
            }
            
            // Check if file matches patterns (if specified)
            if (!empty($filePatterns)) {
                $filename = $file->getFilename();
                $matches = false;
                foreach ($filePatterns as $pattern) {
                    if (fnmatch($pattern, $filename)) {
                        $matches = true;
                        break;
                    }
                }
                if (!$matches) {
                    continue;
                }
            }
            
            // Check file age
            if ($file->getMTime() < $cutoffTime) {
                $filepath = $file->getRealPath();
                if (unlink($filepath)) {
                    $deletedFiles[] = $filepath;
                } else {
                    $errors[] = "Failed to delete: " . $filepath;
                }
            }
        }
        
        return [
            'deleted_count' => count($deletedFiles),
            'deleted_files' => $deletedFiles,
            'errors' => $errors
        ];
    }
    
    /**
     * Validate upload directory structure and permissions
     * 
     * @return array Validation results
     */
    public static function validateUploadStructure() {
        $uploadsDir = self::getUploadsDir();
        $results = [
            'base_dir_exists' => false,
            'base_dir_writable' => false,
            'subdirs' => [],
            'errors' => []
        ];
        
        // Check base directory
        if ($uploadsDir && is_dir($uploadsDir)) {
            $results['base_dir_exists'] = true;
            $results['base_dir_writable'] = is_writable($uploadsDir);
        } else {
            $results['errors'][] = 'Base uploads directory does not exist or is not accessible';
        }
        
        // Check subdirectories
        $subdirs = [
            'templates' => self::SUBDIR_TEMPLATES,
            'certificates' => self::SUBDIR_CERTIFICATES
        ];
        
        foreach ($subdirs as $name => $path) {
            $fullPath = $uploadsDir . DIRECTORY_SEPARATOR . rtrim($path, '/');
            $results['subdirs'][$name] = [
                'exists' => is_dir($fullPath),
                'writable' => is_dir($fullPath) ? is_writable($fullPath) : false,
                'path' => $fullPath
            ];
        }
        
        return $results;
    }
}

?>