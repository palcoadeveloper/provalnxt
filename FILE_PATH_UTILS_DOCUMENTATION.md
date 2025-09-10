# ProVal HVAC - File Path Utils Documentation

## Overview

The `FilePathUtils` class provides a comprehensive, secure interface for handling file paths and operations within the ProVal HVAC system's `public/uploads/` directory. This utility consolidates file path logic found across 20+ files in the codebase and provides consistent, secure methods for file operations.

**Location:** `public/core/utils/file_path_utils.php`

**Security Level:** High - Includes path validation and directory traversal protection

---

## File System Structure

### Base Directory Structure
```
public/
├── uploads/                          # Main uploads directory
│   ├── templates/                    # Test templates
│   ├── certificates/                 # Instrument certificates  
│   ├── schedule-report-*.pdf         # Generated schedule reports
│   ├── protocol-report-*.pdf         # Protocol reports
│   ├── plannedvsactual-report-*.pdf  # Planning reports
│   ├── rt-schedule-report-*.pdf      # Routine test schedule reports
│   └── [various uploaded files]     # User uploaded documents
└── core/
    └── utils/
        └── file_path_utils.php       # This utility class
```

### Database Storage Patterns

Files are referenced in the `tbl_uploads` table using these path patterns:

| Context | Stored Path Pattern | Web URL Pattern |
|---------|-------------------|-----------------|
| From `core/validation/` | `../../uploads/filename.pdf` | `uploads/filename.pdf` |
| From `core/data/save/` | `../../../uploads/filename.pdf` | `uploads/filename.pdf` |
| Web Display | `BASE_URL + uploads/filename.pdf` | Full URL |

---

## Database Integration

### tbl_uploads Table Structure

The `tbl_uploads` table contains these key file path columns:

```sql
upload_path_raw_data           -- Raw data files
upload_path_master_certificate -- Master certificates  
upload_path_test_certificate   -- Test certificates
upload_path_other_doc          -- Other documents
test_wf_id                     -- Links to test workflows
upload_action                  -- Approved/Rejected status
```

### File Type Categories

1. **Upload Files** (stored in `tbl_uploads`)
   - Raw data documents
   - Master certificates
   - Test certificates  
   - Other documentation

2. **Generated Reports** (direct file creation)
   - Schedule reports
   - Protocol reports
   - Planned vs actual reports
   - Routine test reports

3. **Template Files** (`uploads/templates/`)
   - Test templates
   - PDF templates with footers

4. **Certificate Files** (`uploads/certificates/`)
   - Instrument calibration certificates

---

## API Reference

### Core Path Methods

#### `dbPathToWebUrl($dbPath)`
Converts database-stored paths to web-accessible URLs.

```php
// Database path: "../../uploads/test-file.pdf"
$webUrl = FilePathUtils::dbPathToWebUrl($dbPath);
// Returns: "uploads/test-file.pdf"
```

#### `dbPathToAbsolutePath($dbPath)`
Converts database paths to absolute file system paths.

```php
$absolutePath = FilePathUtils::dbPathToAbsolutePath("../../uploads/cert.pdf");
// Returns: "/full/path/to/public/uploads/cert.pdf"
```

#### `createDbPath($filename)`
Creates database storage path from filename.

```php
$dbPath = FilePathUtils::createDbPath("document.pdf");
// Returns: "../../uploads/document.pdf"
```

#### `createFullUrl($relativePath)`
Creates complete URL with BASE_URL.

```php
$fullUrl = FilePathUtils::createFullUrl("uploads/file.pdf");
// Returns: "https://yoursite.com/uploads/file.pdf"
```

### File Operations

#### `fileExists($filename, $subdirectory = '')`
Safely checks if file exists in uploads directory.

```php
// Check if file exists in main uploads/
if (FilePathUtils::fileExists("report.pdf")) {
    // File exists
}

// Check in subdirectory
if (FilePathUtils::fileExists("template.pdf", "templates/")) {
    // Template exists
}
```

#### `getSecureFilePath($filename, $subdirectory = '')`
Returns validated absolute path within uploads directory.

```php
$securePath = FilePathUtils::getSecureFilePath("user-file.pdf");
// Returns absolute path if valid, false if invalid/unsafe
```

#### `listFiles($subdirectory = '', $allowedExtensions = [])`
Lists files in uploads directory with optional filtering.

```php
// List all PDF files in templates/
$templates = FilePathUtils::listFiles("templates/", ["pdf"]);

// List all files in main directory
$allFiles = FilePathUtils::listFiles();
```

### Database Operations

#### `getUploadedFiles($testWfId = null, $uploadAction = null)`
Retrieves file records from `tbl_uploads` table.

```php
// Get all approved files for a test
$files = FilePathUtils::getUploadedFiles("TEST-001", "Approved");

// Get all files for a test (any status)
$allFiles = FilePathUtils::getUploadedFiles("TEST-001");

// Get all uploaded files
$allUploads = FilePathUtils::getUploadedFiles();
```

#### `processUploadRecord($uploadRecord)`
Processes database record to include web URLs.

```php
$record = DB::queryFirstRow("SELECT * FROM tbl_uploads WHERE upload_id = 123");
$processedRecord = FilePathUtils::processUploadRecord($record);

// Now includes additional fields:
// upload_path_raw_data_web_url
// upload_path_raw_data_full_url  
// upload_path_raw_data_filename
// (same for other path fields)
```

### HTML Generation

#### `generateDownloadLink($filePath, $fileType, $uploadId, $testWfId, $linkText = "Download")`
Generates secure download link HTML matching system patterns.

```php
$link = FilePathUtils::generateDownloadLink(
    "../../uploads/certificate.pdf",
    "test_certificate", 
    123,
    "TEST-001",
    "View Certificate"
);

// Returns:
// <a href="uploads/certificate.pdf" 
//    data-file-type="test_certificate" 
//    data-upload-id="123" 
//    data-test-wf-id="TEST-001"
//    class="file-download-link" 
//    data-toggle="modal" 
//    data-target="#imagepdfviewerModal">View Certificate</a>
```

### Report File Handling

#### `getReportFilePath($reportType, $unitId, $identifier)`
Handles generated report files with standardized naming.

```php
// Schedule report
$reportInfo = FilePathUtils::getReportFilePath("schedule", 1, 25);
/* Returns:
[
    'filename' => 'schedule-report-1-25.pdf',
    'relative_path' => 'uploads/schedule-report-1-25.pdf',
    'full_url' => 'https://site.com/uploads/schedule-report-1-25.pdf',
    'absolute_path' => '/path/to/uploads/schedule-report-1-25.pdf',
    'pdf_viewer_url' => 'core/pdf/view_pdf_with_footer.php?pdf_path=uploads%2Fschedule-report-1-25.pdf'
]
*/

// Protocol report  
$protocolInfo = FilePathUtils::getReportFilePath("protocol", 0, "VAL-001");
// Returns info for 'protocol-report-VAL-001.pdf'

// Routine test schedule
$rtInfo = FilePathUtils::getReportFilePath("rt-schedule", 2, 15);
// Returns info for 'rt-schedule-report-2-15.pdf'
```

**Supported Report Types:**
- `schedule` - Validation schedule reports
- `rt-schedule` - Routine test schedule reports  
- `protocol` - Protocol reports
- `plannedvsactual` - Planned vs actual reports
- `plannedvsactualrt` - Planned vs actual routine test reports

### Subdirectory Helpers

#### `createTemplatePath($filename)`
Creates template file database path.

```php
$templatePath = FilePathUtils::createTemplatePath("test_1_template.pdf");
// Returns: "../../uploads/templates/test_1_template.pdf"
```

#### `createCertificatePath($filename)`
Creates certificate file database path.

```php
$certPath = FilePathUtils::createCertificatePath("calibration_cert.pdf");
// Returns: "../../uploads/certificates/calibration_cert.pdf"
```

### System Utilities

#### `getUploadsDir()`
Returns absolute path to uploads directory.

```php
$uploadsDir = FilePathUtils::getUploadsDir();
// Returns: "/full/path/to/public/uploads" or false if not found
```

#### `validateUploadStructure()`
Validates directory structure and permissions.

```php
$validation = FilePathUtils::validateUploadStructure();
/* Returns:
[
    'base_dir_exists' => true,
    'base_dir_writable' => true,
    'subdirs' => [
        'templates' => ['exists' => true, 'writable' => true, 'path' => '...'],
        'certificates' => ['exists' => true, 'writable' => true, 'path' => '...']
    ],
    'errors' => []
]
*/
```

#### `cleanupOldFiles($daysOld = 30, $filePatterns = [])`
Utility for cleaning up old files.

```php
// Clean up files older than 30 days
$cleanup = FilePathUtils::cleanupOldFiles(30);

// Clean up specific temporary file patterns
$cleanup = FilePathUtils::cleanupOldFiles(7, ["*-temp-*", "*.tmp"]);
/* Returns:
[
    'deleted_count' => 5,
    'deleted_files' => ['/path/to/file1.tmp', ...],
    'errors' => []
]
*/
```

---

## Usage Patterns & Examples

### Pattern 1: Display Upload Files (Most Common)

**Scenario:** Show download links for uploaded files in a test workflow.

```php
// Get uploaded files for a test
$uploadedFiles = FilePathUtils::getUploadedFiles($_GET['test_val_wf_id'], 'Approved');

echo '<table>';
foreach ($uploadedFiles as $file) {
    echo '<tr>';
    
    // Raw Data
    echo '<td>' . FilePathUtils::generateDownloadLink(
        $file['upload_path_raw_data'],
        'raw_data',
        $file['upload_id'], 
        $file['test_wf_id']
    ) . '</td>';
    
    // Test Certificate  
    echo '<td>' . FilePathUtils::generateDownloadLink(
        $file['upload_path_test_certificate'],
        'test_certificate',
        $file['upload_id'],
        $file['test_wf_id']
    ) . '</td>';
    
    echo '</tr>';
}
echo '</table>';
```

### Pattern 2: File Upload Processing

**Scenario:** Process uploaded file and store in database.

```php
if (isset($_FILES['upload']) && $_FILES['upload']['error'] === UPLOAD_ERR_OK) {
    
    // Generate unique filename
    $originalName = $_FILES['upload']['name'];
    $extension = pathinfo($originalName, PATHINFO_EXTENSION);
    $uniqueFilename = time() . '_' . bin2hex(random_bytes(8)) . '.' . $extension;
    
    // Get secure upload path
    $uploadPath = FilePathUtils::getSecureFilePath($uniqueFilename);
    
    if ($uploadPath && move_uploaded_file($_FILES['upload']['tmp_name'], $uploadPath)) {
        
        // Create database path
        $dbPath = FilePathUtils::createDbPath($uniqueFilename);
        
        // Insert into database
        DB::insert('tbl_uploads', [
            'test_wf_id' => $_POST['test_wf_id'],
            'upload_path_test_certificate' => $dbPath,
            'upload_type' => 'test_certificate',
            'uploaded_datetime' => date('Y-m-d H:i:s'),
            'upload_action' => null
        ]);
        
        echo "File uploaded successfully";
    }
}
```

### Pattern 3: Report Generation

**Scenario:** Generate and reference a report file.

```php
// Generate report
$unitId = $_SESSION['unit_id'];
$scheduleId = $_POST['schedule_id'];

// Get report file info
$reportInfo = FilePathUtils::getReportFilePath('schedule', $unitId, $scheduleId);

// Generate PDF to the specified path
$pdf = new FPDF();
// ... PDF generation code ...
$pdf->Output($reportInfo['absolute_path'], 'F');

// Provide download link
echo '<a href="' . $reportInfo['pdf_viewer_url'] . '" data-toggle="modal" data-target="#imagepdfviewerModal" class="btn btn-success">Download Report</a>';
```

### Pattern 4: Template Management

**Scenario:** Manage test templates in templates/ subdirectory.

```php
// List available templates
$templates = FilePathUtils::listFiles('templates/', ['pdf']);

echo '<ul>';
foreach ($templates as $template) {
    $templatePath = FilePathUtils::createTemplatePath($template);
    $webUrl = FilePathUtils::dbPathToWebUrl($templatePath);
    
    echo '<li>';
    echo '<a href="' . $webUrl . '" data-toggle="modal" data-target="#imagepdfviewerModal">';
    echo htmlspecialchars($template);
    echo '</a>';
    echo '</li>';
}
echo '</ul>';
```

### Pattern 5: Security Validation

**Scenario:** Validate file access before serving.

```php
$requestedFile = $_GET['file'] ?? '';

// Validate file path security  
$securePath = FilePathUtils::getSecureFilePath($requestedFile);

if ($securePath && file_exists($securePath)) {
    // File is safe to serve
    $webUrl = FilePathUtils::createFullUrl('uploads/' . basename($requestedFile));
    header('Location: ' . $webUrl);
} else {
    // Invalid or unsafe file request
    http_response_code(404);
    echo "File not found";
}
```

---

## Integration with Existing Pages

### Pages Using FilePathUtils

The utility can be integrated into these existing pages:

#### File Display Pages
- `public/core/data/get/getuploadedfiles.php`
- `public/core/data/get/getuploadedfilesonlevel1.php` 
- `public/assets/inc/_testdetails.php`
- `public/assets/inc/_protocoltext.php`

**Integration example for getuploadedfiles.php:**

```php
// Replace existing manual path construction with:
require_once(__DIR__ . '/../utils/file_path_utils.php');

$uploadedFiles = FilePathUtils::getUploadedFiles($_GET['test_val_wf_id']);

foreach ($uploadedFiles as $row) {
    $processedRow = FilePathUtils::processUploadRecord($row);
    
    // Use generated download links
    $output .= '<td>' . FilePathUtils::generateDownloadLink(
        $row['upload_path_raw_data'],
        'raw_data', 
        $row['upload_id'],
        $row['test_wf_id']
    ) . '</td>';
}
```

#### Report Generation Pages
- `public/generateschedulereport.php`
- `public/generateprotocolreport_rev.php`
- `public/generateplannedvsactualrpt.php`

**Integration example:**

```php
// Replace manual path construction:
// $pdfPath = __DIR__ . '/uploads/' . basename($pdfFilename);

// With utility method:
require_once(__DIR__ . '/core/utils/file_path_utils.php');

$reportInfo = FilePathUtils::getReportFilePath('schedule', $unit_id, $sch_id);
$pdf->Output($reportInfo['absolute_path'], 'F');
```

#### File Upload Pages
- `public/core/validation/fileupload.php`
- `public/core/data/save/createreportdata.php`

**Integration example:**

```php
// Replace manual path handling with:
$securePath = FilePathUtils::getSecureFilePath($uniqueFilename);
if ($securePath) {
    $dbPath = FilePathUtils::createDbPath($uniqueFilename);
    // ... rest of upload logic
}
```

---

## Security Considerations

### Path Validation
The utility includes several security measures:

1. **Directory Traversal Prevention**
   - `basename()` extraction removes directory components
   - `realpath()` validation ensures paths stay within uploads/
   - Path sanitization removes `../` patterns

2. **File Access Control**
   - Session validation required (via security template)
   - Files served through controlled endpoints
   - No direct file system access

3. **Database Integration**
   - Parameterized queries prevent SQL injection
   - Input validation on all user parameters
   - Audit trail maintenance

### Safe Practices

```php
// GOOD - Use utility methods
$securePath = FilePathUtils::getSecureFilePath($_GET['file']);

// BAD - Direct path construction
$path = $_GET['file']; // Vulnerable to traversal

// GOOD - Validate before serving  
if (FilePathUtils::fileExists($filename)) {
    // Safe to serve
}

// BAD - No validation
include($_GET['file']); // Dangerous
```

---

## Troubleshooting

### Common Issues

#### 1. Files Not Found
**Problem:** `fileExists()` returns false for valid files

**Solution:**
```php
// Check directory structure
$validation = FilePathUtils::validateUploadStructure();
if (!$validation['base_dir_exists']) {
    // Create uploads directory
    mkdir(FilePathUtils::getUploadsDir(), 0755, true);
}
```

#### 2. Permission Errors  
**Problem:** Cannot write files to uploads directory

**Solution:**
```php
$validation = FilePathUtils::validateUploadStructure();
if (!$validation['base_dir_writable']) {
    // Fix permissions
    chmod(FilePathUtils::getUploadsDir(), 0755);
}
```

#### 3. Database Path Mismatches
**Problem:** Links don't work due to path format differences

**Solution:**
```php
// Always use utility conversion methods
$webUrl = FilePathUtils::dbPathToWebUrl($dbPath);
// Don't manually manipulate paths
```

#### 4. Missing Subdirectories
**Problem:** Template/certificate operations fail

**Solution:**
```php
$validation = FilePathUtils::validateUploadStructure();
foreach ($validation['subdirs'] as $name => $info) {
    if (!$info['exists']) {
        mkdir($info['path'], 0755, true);
    }
}
```

### Debug Mode

Enable debug output for troubleshooting:

```php
// Add debug output to your page
if (defined('DEBUG_MODE') && DEBUG_MODE) {
    echo '<pre>';
    print_r(FilePathUtils::validateUploadStructure());
    echo '</pre>';
}
```

### Logging

The utility integrates with ProVal's logging system:

```php
// File operations are logged automatically via security template
// Manual logging for custom operations:
DB::insert('log', [
    'change_type' => 'file_access',
    'table_name' => 'file_system', 
    'change_description' => 'File accessed: ' . $filename,
    'change_by' => $_SESSION['user_id'],
    'unit_id' => $_SESSION['unit_id']
]);
```

---

## Performance Considerations

### Caching
For frequently accessed file listings:

```php
// Cache file listings in session
if (!isset($_SESSION['template_cache'])) {
    $_SESSION['template_cache'] = FilePathUtils::listFiles('templates/', ['pdf']);
}
$templates = $_SESSION['template_cache'];
```

### Batch Operations
For processing multiple files:

```php
// Get all files at once rather than individual queries
$allFiles = FilePathUtils::getUploadedFiles();

// Process in batch
foreach ($allFiles as $file) {
    $processed = FilePathUtils::processUploadRecord($file);
    // ... bulk processing
}
```

### Directory Scanning
For large directories, consider pagination:

```php
function getFilesPaginated($page = 1, $limit = 50) {
    $files = FilePathUtils::listFiles();
    $offset = ($page - 1) * $limit;
    return array_slice($files, $offset, $limit);
}
```

---

## Migration Guide

### Updating Existing Code

To migrate existing file handling code to use FilePathUtils:

#### Step 1: Identify Current Patterns
Look for these patterns in your code:
- Manual path construction: `"uploads/" . $filename`
- Database path handling: `substr($path, 6)`  
- Direct file operations: `file_exists("../uploads/" . $file)`

#### Step 2: Replace with Utility Methods
```php
// Before:
$webPath = "uploads/" . basename($row['upload_path_raw_data']);

// After:
$webPath = FilePathUtils::dbPathToWebUrl($row['upload_path_raw_data']);
```

#### Step 3: Add Security Template
If not already present, add to your PHP files:
```php
require_once(__DIR__ . '/path/to/core/utils/file_path_utils.php');
```

#### Step 4: Test Integration
Use the validation method to ensure everything works:
```php
$validation = FilePathUtils::validateUploadStructure();
if (!empty($validation['errors'])) {
    // Handle setup issues
}
```

---

## API Summary

### Quick Reference

| Method | Purpose | Returns |
|--------|---------|---------|
| `dbPathToWebUrl($path)` | DB path → web URL | String |
| `dbPathToAbsolutePath($path)` | DB path → absolute path | String |
| `createDbPath($filename)` | Filename → DB path | String |
| `createFullUrl($relative)` | Relative → full URL | String |
| `fileExists($filename, $subdir)` | Check file exists | Boolean |
| `getSecureFilePath($filename, $subdir)` | Get validated path | String\|False |
| `listFiles($subdir, $extensions)` | List directory files | Array |
| `getUploadedFiles($testId, $action)` | Query tbl_uploads | Array |
| `processUploadRecord($record)` | Add web URLs to record | Array |
| `generateDownloadLink(...)` | Create HTML download link | String |
| `getReportFilePath($type, $unit, $id)` | Get report file info | Array |
| `validateUploadStructure()` | Check directory setup | Array |

---

## Conclusion

The FilePathUtils class provides a secure, consistent interface for all file operations in the ProVal HVAC system. It consolidates the various path handling patterns found throughout the codebase and adds proper security validation.

**Key Benefits:**
- ✅ **Security** - Path validation and traversal protection
- ✅ **Consistency** - Standardized file handling across all pages  
- ✅ **Maintainability** - Centralized logic for easy updates
- ✅ **Integration** - Works with existing database and UI patterns
- ✅ **Performance** - Efficient file operations and validation

For questions or issues with FilePathUtils, refer to the troubleshooting section or review the existing implementations in the pages listed in the integration guide.

---

**Last Updated:** December 2024  
**Version:** 1.0  
**Compatibility:** ProVal HVAC v4.x