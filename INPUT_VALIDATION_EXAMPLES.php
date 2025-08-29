<?php
/**
 * ProVal HVAC - Input Validation Examples & Patterns
 * 
 * This file provides practical examples of secure input validation patterns
 * used throughout the ProVal HVAC system.
 * 
 * @version 1.0
 * @author ProVal Security Team
 */

require_once('./core/config.php');
require_once('core/input_validation_utils.php');
require_once('core/secure_transaction_wrapper.php');

// =======================================================================================
// 1. TEXT INPUT CLEANING (Standard Implementation)
// =======================================================================================

/**
 * Standard text cleaning function used throughout the system
 * Use this for general text inputs like comments, descriptions, etc.
 */
function cleanTextInput($text) {
    if (is_null($text)) return '';
    
    // Convert all types of line breaks to a standard format
    $text = str_replace(["\r\n", "\r"], "\n", $text);
    
    // Replace multiple consecutive line breaks with a single one
    $text = preg_replace('/\n{3,}/', "\n\n", $text);
    
    // Trim whitespace from beginning and end
    $text = trim($text);
    
    return $text;
}

// Example usage in actual implementation:
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Clean all input data before processing
    $cleanDeviation = cleanTextInput($_POST['deviation'] ?? '');
    $cleanSummary = cleanTextInput($_POST['summary'] ?? '');
    $cleanRecommendation = cleanTextInput($_POST['recommendation'] ?? '');
    
    // Save to database using secure transactions
    try {
        executeSecureTransaction(function() use ($cleanDeviation, $cleanSummary, $cleanRecommendation, $reportId) {
            DB::query("UPDATE validation_reports SET 
                        deviation=%s, summary=%s, recommendation=%s 
                        WHERE report_id=%i AND user_id=%i", 
                        $cleanDeviation, $cleanSummary, $cleanRecommendation, 
                        $reportId, $_SESSION['user_id']);
        }, 'validation_report_update');
    } catch (SecurityException $e) {
        error_log("Secure transaction failed: " . $e->getMessage());
    }
}

// =======================================================================================
// 2. COMPREHENSIVE INPUT VALIDATION PATTERNS
// =======================================================================================

/**
 * Example: User Management Form Validation
 */
function validateUserManagementInput($postData) {
    $validationRules = [
        'employee_name' => [
            'required' => true,
            'validator' => 'validateText',
            'params' => [InputValidator::MAX_LENGTH_MEDIUM, true, false] // Max 255 chars, required, no HTML
        ],
        'email' => [
            'required' => true,
            'validator' => 'validateEmail'
        ],
        'phone' => [
            'required' => false,
            'validator' => 'validateText',
            'params' => [20, false, false] // Max 20 chars for phone
        ],
        'department_id' => [
            'required' => true,
            'validator' => 'validateInteger',
            'params' => [1, 999999] // Between 1 and 999999
        ],
        'role' => [
            'required' => true,
            'validator' => 'validateText',
            'params' => [50, true, false]
        ]
    ];
    
    return InputValidator::validatePostData($validationRules, $postData);
}

/**
 * Example: Equipment Details Validation
 */
function validateEquipmentInput($postData) {
    $validationRules = [
        'equipment_name' => [
            'required' => true,
            'validator' => 'validateText',
            'params' => [InputValidator::MAX_LENGTH_MEDIUM, true, false]
        ],
        'equipment_code' => [
            'required' => true,
            'validator' => 'validateText',
            'params' => [50, true, false]
        ],
        'location' => [
            'required' => true,
            'validator' => 'validateText',
            'params' => [InputValidator::MAX_LENGTH_MEDIUM, true, false]
        ],
        'installation_date' => [
            'required' => false,
            'validator' => 'validateDate'
        ],
        'capacity' => [
            'required' => false,
            'validator' => 'validateInteger',
            'params' => [0, 999999]
        ]
    ];
    
    return InputValidator::validatePostData($validationRules, $postData);
}

/**
 * Example: Report Data Validation with Rich Text
 */
function validateReportInput($postData) {
    $validationRules = [
        'test_name' => [
            'required' => true,
            'validator' => 'validateText',
            'params' => [InputValidator::MAX_LENGTH_MEDIUM, true, false]
        ],
        'observation' => [
            'required' => true,
            'validator' => 'validateText',
            'params' => [InputValidator::MAX_LENGTH_LONG, true, true] // Allow limited HTML
        ],
        'deviation' => [
            'required' => false,
            'validator' => 'validateText',
            'params' => [InputValidator::MAX_LENGTH_LONG, false, true] // Allow limited HTML
        ],
        'recommendation' => [
            'required' => false,
            'validator' => 'validateText',
            'params' => [InputValidator::MAX_LENGTH_LONG, false, true] // Allow limited HTML
        ],
        'test_date' => [
            'required' => true,
            'validator' => 'validateDate'
        ],
        'workflow_id' => [
            'required' => true,
            'validator' => 'validateWorkflowId'
        ]
    ];
    
    return InputValidator::validatePostData($validationRules, $postData);
}

// =======================================================================================
// 3. SPECIALIZED VALIDATION FUNCTIONS
// =======================================================================================

/**
 * Custom validation for ProVal-specific data types
 */
class ProValValidator extends InputValidator {
    
    /**
     * Validate equipment code format (e.g., "EQ-HVAC-001")
     */
    public static function validateEquipmentCode($code) {
        $code = self::sanitizeString($code);
        
        if (!preg_match('/^[A-Z]{2,3}-[A-Z]{3,5}-[0-9]{3,4}$/', $code)) {
            return false;
        }
        
        if (strlen($code) > 20) {
            return false;
        }
        
        return $code;
    }
    
    /**
     * Validate test protocol ID format
     */
    public static function validateProtocolId($protocolId) {
        $protocolId = self::sanitizeString($protocolId);
        
        if (!preg_match('/^PROTO-[0-9]{4}-[A-Z0-9]{3,6}$/', $protocolId)) {
            return false;
        }
        
        return $protocolId;
    }
    
    /**
     * Validate temperature range input
     */
    public static function validateTemperature($temp, $min = -50, $max = 200) {
        $temp = filter_var($temp, FILTER_VALIDATE_FLOAT);
        
        if ($temp === false) {
            return false;
        }
        
        if ($temp < $min || $temp > $max) {
            return false;
        }
        
        return $temp;
    }
    
    /**
     * Validate humidity percentage
     */
    public static function validateHumidity($humidity) {
        $humidity = filter_var($humidity, FILTER_VALIDATE_FLOAT);
        
        if ($humidity === false) {
            return false;
        }
        
        if ($humidity < 0 || $humidity > 100) {
            return false;
        }
        
        return $humidity;
    }
    
    /**
     * Validate HVAC system status
     */
    public static function validateSystemStatus($status) {
        $validStatuses = ['operational', 'maintenance', 'offline', 'testing', 'calibration'];
        
        $status = self::sanitizeString(strtolower($status));
        
        if (!in_array($status, $validStatuses)) {
            return false;
        }
        
        return $status;
    }
}

// =======================================================================================
// 4. PRACTICAL USAGE EXAMPLES
// =======================================================================================

/**
 * Example: Processing Validation Report Submission
 */
function processValidationReport() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return false;
    }
    
    // Update session activity
    updateSessionActivity();
    
    // Define comprehensive validation rules
    $validationRules = [
        'equipment_id' => [
            'required' => true,
            'validator' => 'validateInteger',
            'params' => [1]
        ],
        'test_date' => [
            'required' => true,
            'validator' => 'validateDate'
        ],
        'temperature_reading' => [
            'required' => false,
            'validator' => [ProValValidator::class, 'validateTemperature'],
            'params' => [-20, 80] // HVAC temperature range
        ],
        'humidity_reading' => [
            'required' => false,
            'validator' => [ProValValidator::class, 'validateHumidity']
        ],
        'system_status' => [
            'required' => true,
            'validator' => [ProValValidator::class, 'validateSystemStatus']
        ],
        'observations' => [
            'required' => true,
            'validator' => 'validateText',
            'params' => [InputValidator::MAX_LENGTH_LONG, true, true]
        ],
        'deviations' => [
            'required' => false,
            'validator' => 'validateText',
            'params' => [InputValidator::MAX_LENGTH_LONG, false, true]
        ],
        'recommendations' => [
            'required' => false,
            'validator' => 'validateText',
            'params' => [InputValidator::MAX_LENGTH_LONG, false, true]
        ]
    ];
    
    // Validate input
    $validation = InputValidator::validatePostData($validationRules, $_POST);
    
    if ($validation['valid']) {
        $data = $validation['data'];
        
        try {
            // Extend session for database operation
            extendSessionForTransaction('report_submission');
            
            // Insert using parameterized query
            DB::query("INSERT INTO validation_reports 
                       (equipment_id, test_date, temperature_reading, humidity_reading, 
                        system_status, observations, deviations, recommendations, 
                        created_by, created_at) 
                       VALUES (%i, %s, %f, %f, %s, %s, %s, %s, %i, NOW())",
                       $data['equipment_id'],
                       $data['test_date'],
                       $data['temperature_reading'],
                       $data['humidity_reading'],
                       $data['system_status'],
                       $data['observations'],
                       $data['deviations'],
                       $data['recommendations'],
                       $_SESSION['user_id']);
            
            // Complete transaction
            completeTransaction();
            
            // Log success
            SecurityUtils::logSecurityEvent('report_submitted', 'Validation report submitted', [
                'equipment_id' => $data['equipment_id'],
                'user_id' => $_SESSION['user_id']
            ]);
            
            return ['success' => true, 'message' => 'Report submitted successfully'];
            
        } catch (Exception $e) {
            error_log("Report submission error: " . $e->getMessage());
            SecurityUtils::logSecurityEvent('report_submission_failed', 'Database error during report submission');
            
            return ['success' => false, 'message' => 'Failed to submit report. Please try again.'];
        }
        
    } else {
        // Log validation failure
        SecurityUtils::logSecurityEvent('invalid_report_input', 'Report submission failed validation', [
            'errors' => $validation['errors']
        ]);
        
        return ['success' => false, 'errors' => $validation['errors']];
    }
}

// =======================================================================================
// 5. FILE UPLOAD VALIDATION EXAMPLES
// =======================================================================================

/**
 * Example: Process Document Upload for Validation Reports
 */
function processValidationDocumentUpload() {
    if (!isset($_FILES['document']) || $_FILES['document']['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'message' => 'No file uploaded or upload error'];
    }
    
    // Define allowed types for validation documents
    $allowedTypes = ['pdf', 'docx', 'xlsx'];
    $maxSize = 10 * 1024 * 1024; // 10MB for validation documents
    
    $validation = FileUploadValidator::validateFile($_FILES['document'], $allowedTypes, $maxSize);
    
    if ($validation['valid']) {
        $safeFilename = $validation['sanitized_name'];
        $uploadDir = 'uploads/validation_documents/';
        
        // Ensure upload directory exists and is secure
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        
        $uploadPath = $uploadDir . date('Y-m-d_His_') . $safeFilename;
        
        // Additional security: Scan for malware
        if (FileUploadValidator::scanForMalware($_FILES['document']['tmp_name'])) {
            
            if (move_uploaded_file($_FILES['document']['tmp_name'], $uploadPath)) {
                
                // Save file info to database
                try {
                    DB::query("INSERT INTO validation_documents 
                               (filename, original_name, file_path, file_size, uploaded_by, created_at) 
                               VALUES (%s, %s, %s, %i, %i, NOW())",
                               $safeFilename,
                               $_FILES['document']['name'],
                               $uploadPath,
                               $_FILES['document']['size'],
                               $_SESSION['user_id']);
                    
                    // Log successful upload
                    SecurityUtils::logSecurityEvent('document_uploaded', 'Validation document uploaded', [
                        'filename' => $safeFilename,
                        'size' => $_FILES['document']['size']
                    ]);
                    
                    return ['success' => true, 'filename' => $safeFilename, 'path' => $uploadPath];
                    
                } catch (Exception $e) {
                    // Clean up uploaded file if database insert fails
                    unlink($uploadPath);
                    error_log("Document upload database error: " . $e->getMessage());
                    
                    return ['success' => false, 'message' => 'Failed to save document information'];
                }
                
            } else {
                return ['success' => false, 'message' => 'Failed to move uploaded file'];
            }
            
        } else {
            SecurityUtils::logSecurityEvent('malicious_document_blocked', 'Malicious document upload blocked', [
                'filename' => $_FILES['document']['name']
            ]);
            
            return ['success' => false, 'message' => 'File upload blocked: Security violation detected'];
        }
        
    } else {
        return ['success' => false, 'errors' => $validation['errors']];
    }
}

// =======================================================================================
// 6. AJAX REQUEST VALIDATION
// =======================================================================================

/**
 * Example: AJAX endpoint for equipment search with validation
 */
function handleEquipmentSearchAjax() {
    // Verify this is an AJAX request
    if (empty($_SERVER['HTTP_X_REQUESTED_WITH']) || 
        strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) != 'xmlhttprequest') {
        return ['error' => 'Invalid request type'];
    }
    
    // Update session activity for AJAX requests
    updateSessionActivity();
    
    // Validate search parameters
    $validationRules = [
        'search_term' => [
            'required' => true,
            'validator' => 'validateText',
            'params' => [100, true, false] // Max 100 chars, required, no HTML
        ],
        'department_id' => [
            'required' => false,
            'validator' => 'validateInteger',
            'params' => [1]
        ],
        'status' => [
            'required' => false,
            'validator' => [ProValValidator::class, 'validateSystemStatus']
        ],
        'limit' => [
            'required' => false,
            'validator' => 'validateInteger',
            'params' => [1, 100] // Max 100 results
        ]
    ];
    
    $validation = InputValidator::validatePostData($validationRules, $_REQUEST);
    
    if ($validation['valid']) {
        $data = $validation['data'];
        
        try {
            // Build secure query
            $whereClause = "WHERE equipment_name LIKE %s";
            $params = ['%' . $data['search_term'] . '%'];
            
            if (!empty($data['department_id'])) {
                $whereClause .= " AND department_id = %i";
                $params[] = $data['department_id'];
            }
            
            if (!empty($data['status'])) {
                $whereClause .= " AND status = %s";
                $params[] = $data['status'];
            }
            
            $limit = $data['limit'] ?? 20;
            $query = "SELECT equipment_id, equipment_name, equipment_code, location, status 
                      FROM equipment $whereClause 
                      ORDER BY equipment_name 
                      LIMIT $limit";
            
            $results = DB::query($query, ...$params);
            
            // Sanitize output for JSON response
            $safeResults = [];
            foreach ($results as $row) {
                $safeResults[] = [
                    'id' => (int)$row['equipment_id'],
                    'name' => htmlspecialchars($row['equipment_name'], ENT_QUOTES, 'UTF-8'),
                    'code' => htmlspecialchars($row['equipment_code'], ENT_QUOTES, 'UTF-8'),
                    'location' => htmlspecialchars($row['location'], ENT_QUOTES, 'UTF-8'),
                    'status' => htmlspecialchars($row['status'], ENT_QUOTES, 'UTF-8')
                ];
            }
            
            return ['success' => true, 'data' => $safeResults, 'count' => count($safeResults)];
            
        } catch (Exception $e) {
            error_log("Equipment search error: " . $e->getMessage());
            return ['error' => 'Search failed. Please try again.'];
        }
        
    } else {
        SecurityUtils::logSecurityEvent('invalid_ajax_search', 'Invalid AJAX search parameters', [
            'errors' => $validation['errors']
        ]);
        
        return ['error' => 'Invalid search parameters', 'details' => $validation['errors']];
    }
}

// =======================================================================================
// 7. SECURITY TESTING FUNCTIONS
// =======================================================================================

/**
 * Test input validation with various attack patterns
 */
function testInputValidation() {
    $testInputs = [
        // XSS attempts
        '<script>alert("XSS")</script>',
        'javascript:alert("XSS")',
        '<img src="x" onerror="alert(1)">',
        
        // SQL injection attempts
        "'; DROP TABLE users; --",
        "1' OR '1'='1",
        "admin'--",
        
        // Path traversal
        '../../../etc/passwd',
        '..\\..\\windows\\system32\\config\\sam',
        
        // Command injection
        '; rm -rf /',
        '| nc -l -p 1234 -e /bin/bash',
        
        // Valid inputs
        'Normal text input',
        'user@example.com',
        '123',
        '2023-12-25'
    ];
    
    echo "<h2>Input Validation Security Test</h2>\n";
    
    foreach ($testInputs as $input) {
        $sanitized = InputValidator::sanitizeString($input);
        $suspicious = SecurityUtils::detectSuspiciousPatterns($input);
        
        echo "<div style='margin: 10px; padding: 10px; border: 1px solid #ccc;'>\n";
        echo "<strong>Input:</strong> " . htmlspecialchars($input) . "<br>\n";
        echo "<strong>Sanitized:</strong> " . htmlspecialchars($sanitized) . "<br>\n";
        echo "<strong>Suspicious:</strong> " . ($suspicious ? 'YES' : 'NO') . "<br>\n";
        echo "</div>\n";
    }
}

// =======================================================================================
// 8. REAL-WORLD IMPLEMENTATION EXAMPLES
// =======================================================================================

// Example from actual ProVal HVAC codebase usage:

/*
// In updatetaskdetails.php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Update session activity
    updateSessionActivity();
    
    // Clean all input data before saving
    $cleanDeviation = cleanTextInput($_POST['deviation'] ?? '');
    $cleanSummary = cleanTextInput($_POST['summary'] ?? '');
    $cleanRecommendation = cleanTextInput($_POST['recommendation'] ?? '');
    
    // Validate required fields
    if (empty($cleanDeviation)) {
        $error_message = "Deviation field is required.";
    } else {
        // Save using parameterized query
        DB::query("UPDATE validation_reports SET 
                    deviation=%s, summary=%s, recommendation=%s, 
                    last_updated=NOW(), updated_by=%i
                    WHERE report_id=%i AND user_id=%i", 
                    $cleanDeviation, $cleanSummary, $cleanRecommendation,
                    $_SESSION['user_id'], $reportId, $_SESSION['user_id']);
        
        $success_message = "Report updated successfully.";
    }
}
*/

// =======================================================================================
// 6. SECURE TRANSACTION EXAMPLES WITH INPUT VALIDATION
// =======================================================================================

/**
 * Example: Complete validation workflow with secure transactions
 * This shows how to combine input validation with secure transaction management
 */
function secureValidationWorkflow() {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        
        // Step 1: Validate all inputs
        $validationRules = [
            'title' => [
                'required' => true,
                'validator' => 'validateText',
                'params' => [InputValidator::MAX_LENGTH_SHORT, true, false]
            ],
            'description' => [
                'required' => true,
                'validator' => 'validateText', 
                'params' => [InputValidator::MAX_LENGTH_LONG, false, true]
            ],
            'priority' => [
                'required' => true,
                'validator' => 'validateEnum',
                'params' => [['high', 'medium', 'low']]
            ],
            'assigned_team' => [
                'required' => true,
                'validator' => 'validateInteger',
                'params' => [1, 999999]
            ]
        ];
        
        $validation = InputValidator::validatePostData($validationRules, $_POST);
        
        if ($validation['valid']) {
            $data = $validation['data'];
            
            // Step 2: Execute complex transaction with session validation
            try {
                $workflowId = executeSecureTransaction(function() use ($data) {
                    
                    // Create main validation record
                    DB::query("INSERT INTO validation_workflows (title, description, priority, created_by, status) 
                               VALUES (%s, %s, %s, %i, %s)",
                               $data['title'], $data['description'], $data['priority'], 
                               $_SESSION['user_id'], 'pending');
                    
                    $workflowId = DB::insertId();
                    
                    // Assign to team
                    DB::query("INSERT INTO workflow_assignments (workflow_id, team_id, assigned_at, assigned_by) 
                               VALUES (%i, %i, NOW(), %i)",
                               $workflowId, $data['assigned_team'], $_SESSION['user_id']);
                    
                    // Create audit log entry
                    DB::query("INSERT INTO audit_log (action, table_name, record_id, user_id, details) 
                               VALUES (%s, %s, %i, %i, %s)",
                               'workflow_created', 'validation_workflows', $workflowId, 
                               $_SESSION['user_id'], json_encode($data));
                    
                    return $workflowId;
                    
                }, 'validation_workflow_creation');
                
                // Success - log and return
                if (class_exists('SecurityUtils')) {
                    SecurityUtils::logSecurityEvent('workflow_created', 'Validation workflow created successfully', [
                        'workflow_id' => $workflowId,
                        'title' => $data['title']
                    ]);
                }
                
                return ['success' => true, 'workflow_id' => $workflowId];
                
            } catch (SecurityException $e) {
                // Session validation failed or transaction rolled back
                error_log("Secure workflow creation failed: " . $e->getMessage());
                return ['success' => false, 'message' => 'Workflow creation failed due to security constraints'];
                
            } catch (Exception $e) {
                // Other errors
                error_log("Workflow creation error: " . $e->getMessage());
                return ['success' => false, 'message' => 'An error occurred while creating the workflow'];
            }
            
        } else {
            // Input validation failed
            return ['success' => false, 'errors' => $validation['errors']];
        }
    }
}

?>

<!DOCTYPE html>
<html>
<head>
    <title>ProVal HVAC - Input Validation Examples</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .example { background: #f5f5f5; padding: 15px; margin: 10px 0; border-left: 4px solid #007cba; }
        .security-note { background: #fff3cd; padding: 10px; border: 1px solid #ffeaa7; margin: 10px 0; }
        code { background: #f8f9fa; padding: 2px 4px; border-radius: 3px; }
    </style>
</head>
<body>

<h1>ProVal HVAC - Input Validation Examples</h1>

<div class="security-note">
    <strong>Security Notice:</strong> This file contains examples and patterns for secure input validation. 
    Use these patterns consistently across all ProVal HVAC development.
</div>

<div class="example">
    <h3>Quick Reference</h3>
    <ul>
        <li><code>cleanTextInput($text)</code> - Standard text cleaning for general inputs</li>
        <li><code>InputValidator::sanitizeString($input)</code> - XSS-safe string sanitization</li>
        <li><code>InputValidator::validateEmail($email)</code> - Email validation</li>
        <li><code>InputValidator::validateInteger($int, $min, $max)</code> - Integer validation with range</li>
        <li><code>InputValidator::validatePostData($rules, $data)</code> - Batch validation</li>
        <li><code>FileUploadValidator::validateFile($file, $types, $maxSize)</code> - File upload validation</li>
        <li><code>executeSecureTransaction($callback, $operationName)</code> - Simple secure transactions</li>
        <li><code>SecureTransaction</code> class - Manual transaction control with session validation</li>
    </ul>
</div>

<?php
// Run security test if requested
if (isset($_GET['test']) && $_GET['test'] === 'security') {
    testInputValidation();
}
?>

<div class="example">
    <p><a href="?test=security">Run Input Validation Security Test</a></p>
    <p><strong>Remember:</strong> Always validate input, use parameterized queries, and log security events!</p>
</div>

</body>
</html>